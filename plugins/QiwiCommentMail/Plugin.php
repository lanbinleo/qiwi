<?php

namespace TypechoPlugin\QiwiCommentMail;

use \Typecho\Plugin\PluginInterface;
use \Utils\Helper;
use \Typecho\{Widget, Db};
use \Typecho\Widget\Helper\Form\Element\{Password, Text, Radio, Checkbox, Textarea};

/**
 * Qiwi 评论邮件提醒插件。基于 CommentToMail 原版维护，感谢 xcsoft 的原始贡献。
 *
 * @package QiwiCommentMail
 * @author  Leo 里奥
 * @version 1.5.4
 * @link https://bboreo.com/
 * @LastEditDate 20260623
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once 'Log.php';

class Plugin implements PluginInterface
{
    const TABLE = 'qiwi_comment_mail_queue';

    /**
     * action name
     *
     * @var string
     */
    public static $_action = 'qiwi-comment-mail';

    /**
     * @var string
     */
    public static $_panel  = 'QiwiCommentMail/page/console.php';

    private static $_queueTableReady = false;

    public static function activate()
    {
        $msg = self::dbInstall();

        try {
            if (\Typecho\Plugin::exists('CommentToMail')) {
                \Typecho\Plugin::deactivate('CommentToMail');
            }
        } catch (\Exception $e) {
        } catch (\Throwable $e) {
        }

        Helper::removeAction('comment-to-mail');
        Helper::removePanel(1, 'CommentToMail/page/console.php');

        \Typecho\Plugin::factory('\Widget\Feedback')->finishComment = [__CLASS__, 'handleCommentFinished'];
        \Typecho\Plugin::factory('\Widget\Comments\Edit')->mark = [__CLASS__, 'handleCommentApproved'];

        Helper::addAction(self::$_action, 'TypechoPlugin\QiwiCommentMail\Action');
        Helper::addPanel(1, self::$_panel, 'Qiwi 评论邮件', 'Qiwi 评论邮件控制台', 'administrator');
        return _t($msg);
    }

    public static function deactivate()
    {
        Helper::removeAction(self::$_action);
        Helper::removePanel(1, self::$_panel);
    }

    public static function config(\Typecho\Widget\Helper\Form $form)
    {
        $options = Widget::widget('Widget_Options');

        $mode = new Radio(
            'mode',
            [
                'smtp' => 'smtp',
                'resend' => 'Resend API',
                'mail' => 'mail()',
                'sendmail' => 'sendmail()'
            ],
            'smtp',
            '发信方式'
        );
        $form->addInput($mode);

        $host = new Text(
            'host',
            null,
            '',
            _t('SMTP地址'),
            _t('使用 SMTP 时填写 SMTP 服务器地址。使用 Resend API 时可留空。')
        );
        $form->addInput($host);

        $port = new Text(
            'port',
            null,
            '25',
            _t('SMTP端口'),
            _t('SMTP服务端口, 一般为25. SSL一般为465')
        );
        $port->input->setAttribute('class', 'mini');
        $form->addInput($port->addRule('isInteger', _t('端口号必须为数字')));

        $user = new Text(
            'user',
            null,
            null,
            _t('SMTP用户'),
            _t('SMTP服务验证用户名, 一般为邮箱账户。使用 SMTP 时也会作为默认发件邮箱。')
        );
        $form->addInput($user);

        $pass = new Password(
            'pass',
            null,
            null,
            _t('SMTP密码')
        );
        $form->addInput($pass);

        $validate = new Checkbox(
            'validate',
            [
                'validate' => '服务器需要验证',
                'ssl' => 'ssl加密',
                'tls' => 'tls加密',
                'solve544' => '启用抄送以规避 544 错误'
            ],
            ['validate'],
            'SMTP验证'
        );
        $form->addInput($validate);

        $resendApiKey = new Password(
            'resendApiKey',
            null,
            null,
            _t('Resend API Key'),
            _t('发信方式选择 Resend API 时填写, 例如 re_xxxxxxxxx。')
        );
        $form->addInput($resendApiKey);

        $resendFrom = new Text(
            'resendFrom',
            null,
            null,
            _t('Resend 发件邮箱'),
            _t('必须是 Resend 已验证域名下的邮箱地址, 例如 no-reply@example.com。发件人名称使用下方“发件人名称”。')
        );
        $form->addInput($resendFrom->addRule('email', _t('请填写正确的 Resend 发件邮箱!')));

        $resendApiUrl = new Text(
            'resendApiUrl',
            null,
            'https://api.resend.com/emails',
            _t('Resend API 地址'),
            _t('默认即可。如需代理或自建网关, 请填写完整 HTTPS 地址。')
        );
        $form->addInput($resendApiUrl);

        $resendCaFile = new Text(
            'resendCaFile',
            null,
            null,
            _t('Resend CA 证书路径'),
            _t('可选。Windows 或 phpstudy 无法验证 HTTPS 证书时填写 cacert.pem 的绝对路径；正常环境留空。')
        );
        $form->addInput($resendCaFile);

        $fromName = new Text(
            'fromName',
            null,
            null,
            _t('发件人名称'),
            _t('发件人名称, 留空则使用博客标题')
        );
        $form->addInput($fromName);

        $mail = new Text(
            'mail',
            null,
            null,
            _t('管理员接收邮件地址'),
            _t('接收管理员通知的邮箱。留空则使用文章作者个人设置中的邮箱地址。')
        );
        $form->addInput($mail->addRule('email', _t('请填写正确的邮件地址!')));

        $contactme = new Text(
            'contactme',
            null,
            null,
            _t('模板中“联系我”的邮件地址'),
            _t('联系我用的邮件地址, 留空则使用文章作者个人设置中的邮件地址。')
        );
        $form->addInput($contactme->addRule('email', _t('请填写正确的邮件地址!')));

        $titleForOwner = new Text(
            'titleForOwner',
            null,
            '[{{title}}] 一文有新的评论',
            _t('管理员通知邮件标题')
        );
        $form->addInput($titleForOwner->addRule('required', _t('管理员通知邮件标题不能为空')));

        $titleForGuest = new Text(
            'titleForGuest',
            null,
            '您在 [{{title}}] 的评论有了回复',
            _t('用户回复通知邮件标题')
        );
        $form->addInput($titleForGuest->addRule('required', _t('用户回复通知邮件标题不能为空')));

        $templateHelp = _t('支持变量: {{siteTitle}}, {{title}}, {{author}}, {{author_p}}, {{ip}}, {{mail}}, {{permalink}}, {{manage}}, {{text}}, {{text_p}}, {{contactme}}, {{time}}, {{status}}。留空时使用插件 template 目录中的默认模板。');

        $ownerTemplate = new Textarea(
            'ownerTemplate',
            null,
            self::defaultTemplate('owner'),
            _t('管理员通知邮件模板'),
            $templateHelp
        );
        $ownerTemplate->input->setAttribute('class', 'w-100 mono');
        $form->addInput($ownerTemplate);

        $guestTemplate = new Textarea(
            'guestTemplate',
            null,
            self::defaultTemplate('guest'),
            _t('用户回复通知邮件模板'),
            $templateHelp
        );
        $guestTemplate->input->setAttribute('class', 'w-100 mono');
        $form->addInput($guestTemplate);

        $status = new Checkbox(
            'status',
            [
                'approved' => '提醒已通过评论',
                'waiting' => '提醒待审核评论',
                'spam' => '提醒垃圾评论'
            ],
            ['approved', 'waiting'],
            '管理员提醒状态',
            _t('该选项仅针对管理员通知。待审核评论会固定提醒管理员，用户回复通知只会在回复已通过后发送。')
        );
        $form->addInput($status);

        $other = new Checkbox(
            'other',
            [
                'to_owner' => '有新评论及回复时, 发邮件通知管理员。',
                'to_guest' => '评论被公开回复时, 发邮件通知被回复者。',
                'to_me' => '自己回复自己时也发邮件。',
                'auto_process' => '评论入队后自动处理邮件队列。',
            ],
            ['to_owner', 'to_guest', 'auto_process'],
            '通知与队列设置',
            null
        );
        $form->addInput($other->multiMode());

        $batchSize = new Text(
            'batchSize',
            null,
            '2',
            _t('每次最多处理邮件数'),
            _t('一次 worker 最多处理多少封邮件。建议 1 到 2 封。')
        );
        $batchSize->input->setAttribute('class', 'mini');
        $form->addInput($batchSize->addRule('isInteger', _t('每次最多处理邮件数必须为数字')));

        $rateLimitPerSecond = new Text(
            'rateLimitPerSecond',
            null,
            '2',
            _t('每秒最多发送邮件数'),
            _t('默认 2，适合 Resend 等常见 API 限制。该限制按邮件任务计算。')
        );
        $rateLimitPerSecond->input->setAttribute('class', 'mini');
        $form->addInput($rateLimitPerSecond->addRule('isInteger', _t('每秒最多发送邮件数必须为数字')));

        $maxAttempts = new Text(
            'maxAttempts',
            null,
            '5',
            _t('最大重试次数'),
            _t('超过次数后任务标记为失败, 可在后台手动重试。')
        );
        $maxAttempts->input->setAttribute('class', 'mini');
        $form->addInput($maxAttempts->addRule('isInteger', _t('最大重试次数必须为数字')));

        $logKeepDays = new Text(
            'logKeepDays',
            null,
            '30',
            _t('日志保留天数'),
            _t('成功发送记录会保留指定天数, 失败记录会一直保留到手动清理或重试成功。')
        );
        $logKeepDays->input->setAttribute('class', 'mini');
        $form->addInput($logKeepDays->addRule('isInteger', _t('日志保留天数必须为数字')));

        $entryUrl = ($options->rewrite) ? $options->siteUrl : $options->siteUrl . 'index.php';
        $deliverMailUrl = rtrim($entryUrl, '/') . '/action/' . self::$_action . '?do=deliverMail&key={KEY}';
        $key = new Text(
            'key',
            null,
            \Typecho\Common::randString(16),
            _t('Key'),
            _t('外部定时任务地址为 ' . $deliverMailUrl . '。自动处理无法使用时，可用该地址定时触发。')
        );
        $form->addInput($key->addRule('required', _t('key 不能为空.')));
    }

    public static function personalConfig(\Typecho\Widget\Helper\Form $form)
    {
    }

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
            return 'QiwiCommentMail 邮件任务表已准备完成, 请继续设置发信信息';
        } catch (\Typecho\Db\Exception $e) {
            throw new \Typecho\Plugin\Exception('数据表建立失败, 插件启用失败。错误代码:' . $e->getCode());
        }
    }

    public static function handleCommentFinished($comment)
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

    private static function defaultTemplate($name)
    {
        $file = __DIR__ . '/template/' . $name . '.html';
        return file_exists($file) ? file_get_contents($file) : '';
    }

    private static function cfg()
    {
        try {
            return Helper::options()->plugin('QiwiCommentMail');
        } catch (\Exception $e) {
            return new \stdClass();
        } catch (\Throwable $e) {
            return new \stdClass();
        }
    }

    private static function cfgValue($cfg, $key, $default = '')
    {
        return isset($cfg->{$key}) ? $cfg->{$key} : $default;
    }

    private static function cfgArray($cfg, $key, array $default = [])
    {
        $value = self::cfgValue($cfg, $key, $default);
        if (empty($value)) return [];
        return is_array($value) ? $value : [$value];
    }

    private static function cfgEnabled($cfg, $key, $value, array $default = [])
    {
        return in_array($value, self::cfgArray($cfg, $key, $default), true);
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
        if (!self::cfgEnabled($cfg, 'other', 'to_owner', ['to_owner', 'to_guest', 'auto_process'])) {
            return false;
        }
        $status = (string)$data['status'];
        if ($status !== 'waiting' && !in_array($status, self::cfgArray($cfg, 'status', ['approved', 'waiting']), true)) {
            return false;
        }
        if (self::isTimeMachineAuthorMoment($data)) {
            return false;
        }
        if (!self::cfgEnabled($cfg, 'other', 'to_me', ['to_owner', 'to_guest', 'auto_process'])
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
        if (!self::cfgEnabled($cfg, 'other', 'to_guest', ['to_owner', 'to_guest', 'auto_process'])) {
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

        if (!self::cfgEnabled($cfg, 'other', 'to_me', ['to_owner', 'to_guest', 'auto_process'])) {
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
        $mail = trim((string)self::cfgValue($cfg, 'mail', ''));
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
        if (!self::cfgEnabled($cfg, 'other', 'auto_process', ['to_owner', 'to_guest', 'auto_process'])) {
            return;
        }

        $key = (string)self::cfgValue($cfg, 'key', '');
        if ($key === '') {
            return;
        }

        $options = Widget::widget('Widget_Options');
        $entryUrl = ($options->rewrite) ? $options->siteUrl : $options->siteUrl . 'index.php';
        $deliverUrl = rtrim($entryUrl, '/') . '/action/' . self::$_action . '?do=deliverMail&key=' . rawurlencode($key);
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
        $request = "GET {$target} HTTP/1.1\r\nHost: {$hostHeader}\r\nUser-Agent: QiwiCommentMail/1.5.4\r\nConnection: close\r\n\r\n";
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
