<?php

namespace TypechoPlugin\QiwiTheme;

use \Utils\Helper;
use \Typecho\{Widget, Db};

/**
 * Qiwi 评论邮件提醒模块（原 QiwiCommentMail 独立插件并入，基于 CommentToMail 原版维护，
 * 感谢 xcsoft 的原始贡献）。
 *
 * 设置全部来自主题配置（theme:qiwi 配置行），配置键统一带 mail 前缀。
 * 队列表名、payload schema、worker 锁文件名、action 名与原插件保持一致，
 * 保证升级合并后存量队列任务与外部定时任务地址继续可用。
 *
 * @package QiwiTheme
 * @author  Leo 里奥
 * @version 2.2.0
 * @link https://bboreo.com/
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class Mail
{
    const TABLE = 'qiwi_comment_mail_queue';

    // 原 QiwiCommentMail 的 action 名。外部定时任务地址依赖它，不能改。
    const ACTION_NAME = 'qiwi-comment-mail';

    private static $_queueTableReady = false;
    private static $_cfgCache = null;

    public static function queueTableName()
    {
        $db = Db::get();
        return $db->getPrefix() . self::TABLE;
    }

    public static function ensureQueueTable()
    {
        if (self::$_queueTableReady) return;

        self::dbInstall();
        self::$_queueTableReady = true;
    }

    public static function dbInstall()
    {
        self::$_queueTableReady = false;
        $installDb = Db::get();

        $adapter = explode('_', $installDb->getAdapterName());
        $adapterTyp = array_pop($adapter);
        $type = $adapterTyp === 'Mysqli' ? 'Mysql' : $adapterTyp;
        $supportedAdapter = ['Mysql', 'Pgsql', 'SQLite'];
        if (!in_array($type, $supportedAdapter, true)) {
            throw new \Typecho\Plugin\Exception('数据表建立失败, 不支持的数据库驱动, (仅支持 Mysql, SQLite, PgSQL)');
        }

        $prefix = $installDb->getPrefix();
        $scripts = file_get_contents(__DIR__ . '/sql/' . $type . '.sql');
        $scripts = str_replace('typecho_', $prefix, $scripts);
        $scripts = explode(';', $scripts);

        try {
            foreach ($scripts as $script) {
                $script = trim($script);
                if ($script) $installDb->query($script, Db::WRITE);
            }
            self::$_queueTableReady = true;
            return true;
        } catch (\Typecho\Db\Exception $e) {
            throw new \Typecho\Plugin\Exception('数据表建立失败: ' . $e->getMessage());
        }
    }

    /**
     * 配置读取入口：一次读取主题配置行，组装一份带 mail 前缀键名的配置对象。
     * 数组型配置（SMTP 验证、提醒状态、通知开关）按数组语义归一。
     */
    public static function cfg()
    {
        if (self::$_cfgCache !== null) {
            return self::$_cfgCache;
        }

        $map = class_exists('\QiwiTheme_Plugin') ? \QiwiTheme_Plugin::getThemeOptionMap() : null;
        if (!is_array($map)) {
            $map = array();
        }

        $cfg = new \stdClass();
        $stringKeys = [
            'mailMode' => 'smtp',
            'mailHost' => '',
            'mailPort' => '25',
            'mailUser' => '',
            'mailPass' => '',
            'mailResendApiKey' => '',
            'mailResendFrom' => '',
            'mailResendApiUrl' => 'https://api.resend.com/emails',
            'mailResendCaFile' => '',
            'mailFromName' => '',
            'mailRecipient' => '',
            'mailContactme' => '',
            'mailTitleForOwner' => '[{{title}}] 一文有新的评论',
            'mailTitleForGuest' => '您在 [{{title}}] 的评论有了回复',
            'mailOwnerTemplate' => '',
            'mailGuestTemplate' => '',
            'mailBatchSize' => '2',
            'mailRateLimitPerSecond' => '2',
            'mailMaxAttempts' => '5',
            'mailLogKeepDays' => '30',
            'mailQueueKey' => '',
        ];

        foreach ($stringKeys as $key => $default) {
            $value = isset($map[$key]) ? $map[$key] : null;
            $cfg->{$key} = ($value !== null && $value !== '' && !is_array($value)) ? (string)$value : $default;
        }

        $arrayKeys = [
            'mailValidate' => ['validate'],
            'mailNotifyStatus' => ['approved', 'waiting'],
            'mailSwitches' => ['to_owner', 'to_guest', 'auto_process'],
        ];
        foreach ($arrayKeys as $key => $default) {
            $value = isset($map[$key]) ? $map[$key] : null;
            if (is_array($value) && !empty($value)) {
                $cfg->{$key} = $value;
            } elseif (is_string($value) && $value !== '') {
                $cfg->{$key} = [$value];
            } else {
                $cfg->{$key} = $default;
            }
        }

        self::$_cfgCache = $cfg;
        return $cfg;
    }

    public static function cfgValue($cfg, $key, $default = '')
    {
        return isset($cfg->{$key}) ? $cfg->{$key} : $default;
    }

    public static function cfgArray($cfg, $key, array $default = [])
    {
        $value = self::cfgValue($cfg, $key, $default);
        if (empty($value)) return [];
        return is_array($value) ? $value : [$value];
    }

    public static function cfgEnabled($cfg, $key, $value, array $default = [])
    {
        return in_array($value, self::cfgArray($cfg, $key, $default), true);
    }

    public static function handleCommentFinished($comment)
    {
        // 邮件入队失败绝不能影响评论主流程。
        try {
            self::handleCommentFinishedInternal($comment);
        } catch (\Exception $e) {
        } catch (\Throwable $e) {
        }
    }

    private static function handleCommentFinishedInternal($comment)
    {
        self::ensureQueueTable();

        $created = 0;
        $data = self::commentData($comment);
        if (!$data) {
            return;
        }

        if (self::shouldCreateOwnerTask($data)) {
            $created += self::insertOwnerTask($data);
        }

        if (self::shouldCreateGuestTask($data)) {
            $created += self::insertGuestTask($data, 'reply_published');
        }

        if ($created > 0) {
            self::wakeQueueWorker();
        }
    }

    public static function handleCommentApproved($comment, $edit, $status)
    {
        try {
            self::handleCommentApprovedInternal($comment, $edit, $status);
        } catch (\Exception $e) {
        } catch (\Throwable $e) {
        }
    }

    private static function handleCommentApprovedInternal($comment, $edit, $status)
    {
        if ($status !== 'approved') return;

        self::ensureQueueTable();

        if (is_object($edit)) {
            $edit->status = 'approved';
        }

        $data = self::commentData($edit);
        if (!$data) {
            return;
        }

        $created = self::shouldCreateGuestTask($data)
            ? self::insertGuestTask($data, 'reply_approved')
            : 0;

        if ($created > 0) {
            self::wakeQueueWorker();
        }
    }

    private static function commentData($comment)
    {
        if (!$comment) return null;

        $data = [
            'cid' => self::readField($comment, 'cid', 0),
            'coid' => self::readField($comment, 'coid', 0),
            'created' => self::readField($comment, 'created', time()),
            'ip' => self::readField($comment, 'ip', ''),
            'author' => self::readField($comment, 'author', ''),
            'mail' => self::readField($comment, 'mail', ''),
            'authorId' => self::readField($comment, 'authorId', 0),
            'ownerId' => self::readField($comment, 'ownerId', 0),
            'title' => self::readField($comment, 'title', ''),
            'text' => self::readField($comment, 'text', ''),
            'permalink' => self::readField($comment, 'permalink', ''),
            'status' => self::readField($comment, 'status', 'approved'),
            'parent' => self::readField($comment, 'parent', 0),
        ];

        $data['cid'] = (int)$data['cid'];
        $data['coid'] = (int)$data['coid'];
        $data['created'] = (int)$data['created'];
        $data['authorId'] = (int)$data['authorId'];
        $data['ownerId'] = (int)$data['ownerId'];
        $data['parent'] = (int)$data['parent'];
        foreach (['ip', 'author', 'mail', 'title', 'text', 'permalink', 'status'] as $key) {
            $data[$key] = (string)$data[$key];
        }

        if ($data['cid'] <= 0 || $data['coid'] <= 0) {
            return null;
        }

        $content = self::contentRow($data['cid']);
        if ($content) {
            if ($data['title'] === '' && isset($content['title'])) {
                $data['title'] = (string)$content['title'];
            }
            if ($data['ownerId'] <= 0 && isset($content['authorId'])) {
                $data['ownerId'] = (int)$content['authorId'];
            }
            if ($data['permalink'] === '' && !empty($content['permalink'])) {
                $data['permalink'] = rtrim((string)$content['permalink'], '#') . '#comment-' . $data['coid'];
            }
        }
        $data['permalink'] = self::absoluteUrl($data['permalink']);

        return $data;
    }

    private static function readField($source, $field, $default = '')
    {
        if (is_object($source)) {
            if (isset($source->{$field})) {
                return $source->{$field};
            }
            if ($field === 'permalink') {
                try {
                    $value = $source->{$field};
                    if ($value !== null) {
                        return $value;
                    }
                } catch (\Exception $e) {
                } catch (\Throwable $e) {
                }
            }
        }
        if (is_array($source) && isset($source[$field])) {
            return $source[$field];
        }
        return $default;
    }

    private static function absoluteUrl($url)
    {
        $url = trim((string)$url);
        if ($url === '' || preg_match('/^(https?:)?\/\//i', $url)) {
            return $url;
        }

        try {
            $siteUrl = (string)Widget::widget('Widget_Options')->siteUrl;
        } catch (\Exception $e) {
            $siteUrl = '';
        } catch (\Throwable $e) {
            $siteUrl = '';
        }

        if ($siteUrl === '') {
            return $url;
        }

        return rtrim($siteUrl, '/') . '/' . ltrim($url, '/');
    }

    private static function contentRow($cid)
    {
        static $cache = [];
        $cid = (int)$cid;
        if ($cid <= 0) return null;
        if (array_key_exists($cid, $cache)) return $cache[$cid];

        try {
            $db = Db::get();
            self::ensureRoutes();
            $contents = \Widget\Base\Contents::alloc();
            $row = $db->fetchRow($contents->select()
                ->where('table.contents.cid = ?', $cid)
                ->limit(1), [$contents, 'filter']);
            $cache[$cid] = $row ?: null;
            return $cache[$cid];
        } catch (\Exception $e) {
            $cache[$cid] = null;
            return null;
        } catch (\Throwable $e) {
            $cache[$cid] = null;
            return null;
        }
    }

    private static function ensureRoutes()
    {
        if (\Typecho\Router::get('post') && \Typecho\Router::get('page')) {
            return;
        }

        try {
            $options = Widget::widget('Widget_Options');
            if (!empty($options->routingTable)) {
                \Typecho\Router::setRoutes($options->routingTable);
            }
        } catch (\Exception $e) {
        } catch (\Throwable $e) {
        }
    }

    private static function userRow($uid)
    {
        static $cache = [];
        $uid = (int)$uid;
        if ($uid <= 0) return null;
        if (array_key_exists($uid, $cache)) return $cache[$uid];

        try {
            $db = Db::get();
            $row = $db->fetchRow($db->select('uid', 'mail', 'screenName', 'name')
                ->from('table.users')
                ->where('uid = ?', $uid)
                ->limit(1));
            $cache[$uid] = $row ?: null;
            return $cache[$uid];
        } catch (\Exception $e) {
            $cache[$uid] = null;
            return null;
        } catch (\Throwable $e) {
            $cache[$uid] = null;
            return null;
        }
    }

    private static function parentComment($coid)
    {
        static $cache = [];
        $coid = (int)$coid;
        if ($coid <= 0) return null;
        if (array_key_exists($coid, $cache)) return $cache[$coid];

        try {
            $db = Db::get();
            $row = $db->fetchRow($db->select('cid', 'coid', 'created', 'ip', 'author', 'mail', 'authorId', 'ownerId', 'text', 'status', 'parent')
                ->from('table.comments')
                ->where('coid = ?', $coid)
                ->limit(1));
            $cache[$coid] = $row ? self::commentData($row) : null;
            return $cache[$coid];
        } catch (\Exception $e) {
            $cache[$coid] = null;
            return null;
        } catch (\Throwable $e) {
            $cache[$coid] = null;
            return null;
        }
    }

    private static function isTimeMachineAuthorMoment(array $data)
    {
        if ((int)$data['parent'] > 0) return false;

        $content = self::contentRow((int)$data['cid']);
        if (!$content) return false;

        $template = isset($content['template']) ? (string)$content['template'] : '';
        $type = isset($content['type']) ? (string)$content['type'] : '';
        $contentAuthorId = isset($content['authorId']) ? (int)$content['authorId'] : 0;

        $isTimeMachine = $type === 'page' && in_array($template, ['page-timemachine.php', 'page-timemachine'], true);
        return $isTimeMachine
            && $contentAuthorId > 0
            && (int)$data['authorId'] === $contentAuthorId;
    }

    private static function shouldCreateOwnerTask(array $data)
    {
        $cfg = self::cfg();
        if (!self::cfgEnabled($cfg, 'mailSwitches', 'to_owner', ['to_owner', 'to_guest', 'auto_process'])) {
            return false;
        }
        $status = (string)$data['status'];
        if ($status !== 'waiting' && !in_array($status, self::cfgArray($cfg, 'mailNotifyStatus', ['approved', 'waiting']), true)) {
            return false;
        }
        if (self::isTimeMachineAuthorMoment($data)) {
            return false;
        }
        if (!self::cfgEnabled($cfg, 'mailSwitches', 'to_me', ['to_owner', 'to_guest', 'auto_process'])
            && (int)$data['authorId'] > 0
            && (int)$data['ownerId'] > 0
            && (int)$data['authorId'] === (int)$data['ownerId']) {
            return false;
        }

        $recipient = self::ownerRecipient($data, $cfg);
        return $recipient['mail'] !== '';
    }

    private static function shouldCreateGuestTask(array $data)
    {
        $cfg = self::cfg();
        if (!self::cfgEnabled($cfg, 'mailSwitches', 'to_guest', ['to_owner', 'to_guest', 'auto_process'])) {
            return false;
        }
        if ((int)$data['parent'] <= 0 || $data['status'] !== 'approved') {
            return false;
        }

        $original = self::parentComment((int)$data['parent']);
        if (!$original || trim((string)$original['mail']) === '') {
            return false;
        }

        if (self::isTimeMachineAuthorMoment($original)) {
            return false;
        }

        if (!self::cfgEnabled($cfg, 'mailSwitches', 'to_me', ['to_owner', 'to_guest', 'auto_process'])) {
            $sameMail = strtolower(trim((string)$data['mail'])) !== ''
                && strtolower(trim((string)$data['mail'])) === strtolower(trim((string)$original['mail']));
            $sameUser = (int)$data['authorId'] > 0
                && (int)$original['authorId'] > 0
                && (int)$data['authorId'] === (int)$original['authorId'];
            if ($sameMail || $sameUser) {
                return false;
            }
        }

        return true;
    }

    private static function ownerRecipient(array $data, $cfg)
    {
        $owner = self::userRow((int)$data['ownerId']);
        $mail = trim((string)self::cfgValue($cfg, 'mailRecipient', ''));
        if ($mail === '' && $owner && !empty($owner['mail'])) {
            $mail = (string)$owner['mail'];
        }

        $name = '';
        if ($owner) {
            $name = !empty($owner['screenName']) ? (string)$owner['screenName'] : (string)($owner['name'] ?? '');
        }
        if ($name === '') {
            $name = (string)Widget::widget('Widget_Options')->title;
        }

        return [
            'mail' => $mail,
            'name' => $name,
        ];
    }

    private static function insertOwnerTask(array $data)
    {
        $cfg = self::cfg();
        $recipient = self::ownerRecipient($data, $cfg);
        if ($recipient['mail'] === '') return 0;

        return self::insertTask('owner', 'new_comment', $data, null, $recipient);
    }

    private static function insertGuestTask(array $data, $event)
    {
        $original = self::parentComment((int)$data['parent']);
        if (!$original || trim((string)$original['mail']) === '') return 0;

        $recipient = [
            'mail' => trim((string)$original['mail']),
            'name' => (string)$original['author'],
        ];

        return self::insertTask('guest', $event, $data, $original, $recipient);
    }

    private static function insertTask($recipientType, $event, array $comment, $original, array $recipient)
    {
        $recipientMail = trim((string)$recipient['mail']);
        if ($recipientMail === '') return 0;

        $dedupeKey = $recipientType . ':' . (int)$comment['coid'];
        $db = Db::get();
        $table = self::queueTableName();

        try {
            $exists = $db->fetchRow($db->select('id')
                ->from($table)
                ->where('dedupe_key = ?', $dedupeKey)
                ->limit(1));
            if ($exists) {
                return 0;
            }

            $payload = json_encode([
                'schema' => 'qiwi-comment-mail-task',
                'version' => 1,
                'comment' => $comment,
                'original' => $original,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($payload === false) {
                return 0;
            }

            $now = time();
            $db->query($db->insert($table)->rows([
                'dedupe_key' => $dedupeKey,
                'coid' => (int)$comment['coid'],
                'cid' => (int)$comment['cid'],
                'parent' => (int)$comment['parent'],
                'recipient_type' => $recipientType,
                'event' => $event,
                'recipient_mail' => $recipientMail,
                'recipient_name' => (string)$recipient['name'],
                'payload' => $payload,
                'status' => 'pending',
                'attempts' => 0,
                'last_error' => '',
                'next_retry' => 0,
                'locked_until' => 0,
                'sent_at' => 0,
                'created' => $now,
                'updated' => $now,
            ]));
            return 1;
        } catch (\Exception $e) {
            return 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function wakeQueueWorker()
    {
        $cfg = self::cfg();
        if (!self::cfgEnabled($cfg, 'mailSwitches', 'auto_process', ['to_owner', 'to_guest', 'auto_process'])) {
            return;
        }

        $key = (string)self::cfgValue($cfg, 'mailQueueKey', '');
        if ($key === '') {
            return;
        }

        $options = Widget::widget('Widget_Options');
        $entryUrl = ($options->rewrite) ? $options->siteUrl : $options->siteUrl . 'index.php';
        $deliverUrl = rtrim($entryUrl, '/') . '/action/' . self::ACTION_NAME . '?do=deliverMail&key=' . rawurlencode($key);
        self::triggerQueueAsync($deliverUrl);
    }

    private static function triggerQueueAsync($url)
    {
        $parts = parse_url($url);
        if (empty($parts['host']) || empty($parts['scheme'])) return;

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) return;

        $host = $parts['host'];
        $port = isset($parts['port']) ? (int)$parts['port'] : ($scheme === 'https' ? 443 : 80);
        $target = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        $connectHost = in_array(strtolower($host), ['localhost', '::1'], true) ? '127.0.0.1' : $host;
        $transport = $scheme === 'https' ? 'ssl://' . $connectHost : $connectHost;
        $hostHeader = $host;
        if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
            $hostHeader .= ':' . $port;
        }

        $socket = @stream_socket_client($transport . ':' . $port, $errno, $errstr, 1, STREAM_CLIENT_CONNECT);
        if (!$socket) return;

        stream_set_timeout($socket, 1);
        $request = "GET {$target} HTTP/1.1\r\nHost: {$hostHeader}\r\nUser-Agent: QiwiCommentMail/2.2.0\r\nConnection: close\r\n\r\n";
        $written = 0;
        $length = strlen($request);
        while ($written < $length) {
            $sent = @fwrite($socket, substr($request, $written));
            if ($sent === false || $sent === 0) {
                break;
            }
            $written += $sent;
        }
        @fflush($socket);
        @fread($socket, 1);
        fclose($socket);
    }
}
