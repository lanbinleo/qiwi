<?php

namespace TypechoPlugin\QiwiCommentMail;

/**
 * QiwiCommentMail
 * Typecho 异步评论邮件提醒插件。基于 CommentToMail 原版维护，感谢 xcsoft 的原始贡献。
 *
 * @license GNU General Public License 3.0
 */

use \Utils\Helper;
use \Typecho\{Widget, Db};
use \TypechoPlugin\QiwiCommentMail\lib\Email;
use PHPMailer\PHPMailer\PHPMailer;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once 'PHPMailer/SMTP.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/Exception.php';

class Action extends Widget implements \Widget\ActionInterface
{
    /**
     * @var Db
     */
    private $_db;

    /**
     * @var \Typecho\Config
     */
    private $_cfg;

    /**
     * @var \Widget\Options
     */
    private $_options;

    /**
     * @var object
     */
    private $_user;

    /**
     * @var string
     */
    private $_templateDir = __DIR__ . '/template/';

    /**
     * @var Email
     */
    private $_email;

    /**
     * @var resource|null
     */
    private $_workerLock;

    public function action()
    {
        $this->init();

        $this->on($this->request->is('do=deliverMail'))->deliverMail($this->request->key);

        if (!$this->_user->hasLogin()) $this->response->redirect($this->_options->loginUrl);
        $this->_user->pass('administrator');

        \Typecho\Widget::widget('Widget_Security')->protect();

        $this->on($this->request->is('do=testMail'))->testMail();
        $this->on($this->request->is('do=editTheme'))->editTheme($this->request->edit);
        $this->on($this->request->is('do=runQueue'))->deliverMail(null, false, false);
        $this->on($this->request->is('do=retryQueue'))->retryQueue();
        $this->on($this->request->is('do=clearLogs'))->clearLogs();
    }

    public function init()
    {
        Plugin::ensureQueueTable();

        $this->_db = Db::get();
        $this->_user = $this->widget('\Widget\User');
        $this->_options = $this->widget('\Widget\Options');
        try {
            $this->_cfg = Helper::options()->plugin('QiwiCommentMail');
        } catch (\Exception $e) {
            $this->_cfg = new \stdClass();
        } catch (\Throwable $e) {
            $this->_cfg = new \stdClass();
        }
    }

    private function deliverMail(?string $key, bool $checkKey = true, bool $throwJson = true): void
    {
        if ($checkKey && !hash_equals((string)$this->cfgValue('key', ''), (string)$key)) {
            $this->response->throwJson([
                'code' => -1,
                'msg' => 'Permission denied'
            ]);
        }

        $result = [
            'code' => 0,
            'msg' => 'success',
            'count' => [
                'all' => 0,
                'processed' => 0,
                'success' => 0,
                'retry' => 0,
                'fail' => 0,
            ],
        ];

        if (!$this->acquireWorkerLock()) {
            $result['code'] = 1;
            $result['msg'] = 'worker_locked';
            $this->finishQueueResponse($result, $throwJson);
            return;
        }

        try {
            $tasks = $this->dueTasks();
            $result['count']['all'] = count($tasks);
            $lastStartedAt = 0.0;

            foreach ($tasks as $task) {
                if (!$this->claimTask((int)$task['id'])) {
                    continue;
                }

                $result['count']['processed']++;
                $sendResult = $this->sendTask($task, $lastStartedAt);

                if (!empty($sendResult['ok'])) {
                    $this->markTaskSent((int)$task['id']);
                    $result['count']['success']++;
                    continue;
                }

                $status = $this->markTaskFailed(
                    $task,
                    (string)($sendResult['message'] ?? '邮件发送失败'),
                    !empty($sendResult['temporary']),
                    (int)($sendResult['retry_after'] ?? 0)
                );

                if ($status === 'pending') {
                    $result['count']['retry']++;
                } else {
                    $result['count']['fail']++;
                }
            }

            $this->cleanupOldLogs();
        } finally {
            $this->releaseWorkerLock();
        }

        $this->finishQueueResponse($result, $throwJson);
    }

    private function finishQueueResponse(array $result, bool $throwJson): void
    {
        if ($throwJson) {
            $this->response->throwJson($result);
        }

        if ($result['msg'] === 'worker_locked') {
            $this->widget('Widget_Notice')->set(_t('已有邮件队列正在处理，请稍后再试'), 'notice');
        } else {
            $count = $result['count'];
            $this->widget('Widget_Notice')->set(
                _t('队列处理完成: 共 %d, 已处理 %d, 成功 %d, 等待重试 %d, 失败 %d', $count['all'], $count['processed'], $count['success'], $count['retry'], $count['fail']),
                $count['fail'] > 0 ? 'notice' : 'success'
            );
        }

        $this->response->goBack();
    }

    private function dueTasks(): array
    {
        $now = time();
        $limit = $this->queueLimit();
        $table = Plugin::queueTableName();

        return $this->_db->fetchAll("SELECT id, dedupe_key, coid, cid, parent, recipient_type, event, recipient_mail, recipient_name, payload, status, attempts, last_error, next_retry, locked_until, created, updated FROM {$table} WHERE (status = 'pending' OR (status = 'sending' AND (locked_until IS NULL OR locked_until <= {$now}))) AND (next_retry IS NULL OR next_retry <= {$now}) ORDER BY id ASC LIMIT {$limit}");
    }

    private function claimTask(int $id): bool
    {
        $now = time();
        $lockedUntil = $now + 300;
        $affected = $this->_db->query($this->_db->update(Plugin::queueTableName())->rows([
            'status' => 'sending',
            'locked_until' => $lockedUntil,
            'updated' => $now,
        ])->where("id = ? AND (status = ? OR (status = ? AND (locked_until IS NULL OR locked_until <= ?)))", $id, 'pending', 'sending', $now));

        return (int)$affected > 0;
    }

    private function sendTask(array $task, float &$lastStartedAt): array
    {
        $payload = $this->decodeTaskPayload((string)$task['payload']);
        if (!$payload) {
            return $this->failure('任务内容无法解析', false);
        }

        try {
            $prepared = $this->prepareEmail($task, $payload);
            if (empty($prepared['ok'])) {
                return $prepared;
            }

            $this->respectRateLimit($lastStartedAt);
            return $this->sendMail();
        } catch (\Exception $e) {
            return $this->failure($e->getMessage(), true);
        } catch (\Throwable $e) {
            return $this->failure($e->getMessage(), true);
        } finally {
            unset($this->_email);
        }
    }

    private function prepareEmail(array $task, array $payload): array
    {
        if (empty($payload['comment']) || !is_array($payload['comment'])) {
            return $this->failure('任务缺少评论内容', false);
        }

        $comment = $payload['comment'];
        $original = (!empty($payload['original']) && is_array($payload['original'])) ? $payload['original'] : null;
        $recipientType = (string)$task['recipient_type'];

        $this->_email = new Email();
        $this->_email->from = (string)(((string)$this->cfgValue('mode', 'smtp') === 'resend' && $this->cfgValue('resendFrom', '') !== '') ? $this->cfgValue('resendFrom', '') : $this->cfgValue('user', ''));
        $this->_email->fromName = (string)($this->cfgValue('fromName', '') !== '' ? $this->cfgValue('fromName', '') : $this->_options->title);
        $this->_email->reciver = (string)$task['recipient_mail'];
        $this->_email->reciverName = (string)$task['recipient_name'];

        if (!empty($comment['mail'])) {
            $this->_email->replyTo = (string)$comment['mail'];
            $this->_email->replyToName = !empty($comment['author']) ? (string)$comment['author'] : (string)$this->_options->title;
        }

        if ($recipientType === 'owner') {
            $this->prepareOwnerMail($comment);
            return $this->success();
        }

        if ($recipientType === 'guest') {
            if (!$original) {
                return $this->failure('用户通知缺少被回复评论', false);
            }
            $this->prepareGuestMail($comment, $original);
            return $this->success();
        }

        return $this->failure('未知收件类型: ' . $recipientType, false);
    }

    private function prepareOwnerMail(array $comment): void
    {
        $date = new \Typecho\Date((int)($comment['created'] ?? time()));
        $status = [
            'approved' => '通过',
            'waiting' => '待审',
            'spam' => '垃圾'
        ];

        $search = [
            '{{siteTitle}}',
            '{{title}}',
            '{{author}}',
            '{{ip}}',
            '{{mail}}',
            '{{permalink}}',
            '{{manage}}',
            '{{text}}',
            '{{time}}',
            '{{status}}'
        ];
        $replace = [
            $this->_options->title,
            (string)($comment['title'] ?? ''),
            (string)($comment['author'] ?? ''),
            (string)($comment['ip'] ?? ''),
            (string)($comment['mail'] ?? ''),
            (string)($comment['permalink'] ?? ''),
            $this->_options->siteUrl . __TYPECHO_ADMIN_DIR__ . 'manage-comments.php',
            (string)($comment['text'] ?? ''),
            $date->format('Y-m-d H:i:s'),
            $status[(string)($comment['status'] ?? '')] ?? (string)($comment['status'] ?? '')
        ];

        $this->_email->subject = str_replace($search, $replace, (string)$this->cfgValue('titleForOwner', '[{{title}}] 一文有新的评论'));
        $this->_email->msgHtml = str_replace($search, $replace, $this->getTemplate('owner'));
        $this->_email->altBody = "作者:" . (string)($comment['author'] ?? '') . "\r\n链接:" . (string)($comment['permalink'] ?? '') . "\r\n评论:\r\n" . (string)($comment['text'] ?? '');
    }

    private function prepareGuestMail(array $comment, array $original): void
    {
        $date = new \Typecho\Date((int)($comment['created'] ?? time()));
        $contactme = (string)$this->cfgValue('contactme', '');
        if ($contactme === '') {
            $owner = $this->ownerMail((int)($comment['ownerId'] ?? 0));
            $contactme = $owner;
        }

        $search = [
            '{{siteTitle}}',
            '{{title}}',
            '{{author_p}}',
            '{{author}}',
            '{{permalink}}',
            '{{text}}',
            '{{text_p}}',
            '{{contactme}}',
            '{{time}}'
        ];
        $replace = [
            $this->_options->title,
            (string)($comment['title'] ?? ''),
            (string)($original['author'] ?? ''),
            (string)($comment['author'] ?? ''),
            (string)($comment['permalink'] ?? ''),
            (string)($comment['text'] ?? ''),
            (string)($original['text'] ?? ''),
            $contactme,
            $date->format('Y-m-d H:i:s'),
        ];

        $this->_email->subject = str_replace($search, $replace, (string)$this->cfgValue('titleForGuest', '您在 [{{title}}] 的评论有了回复'));
        $this->_email->msgHtml = str_replace($search, $replace, $this->getTemplate('guest'));
        $this->_email->altBody = "作者:" . (string)($comment['author'] ?? '') . "\r\n链接:" . (string)($comment['permalink'] ?? '') . "\r\n评论:\r\n" . (string)($comment['text'] ?? '');
    }

    private function sendMail(): array
    {
        if ((string)$this->cfgValue('mode', 'smtp') === 'resend') {
            return $this->sendByResend();
        }

        if (trim((string)$this->_email->reciver) === '') {
            return $this->failure('收件人邮箱不能为空', false);
        }
        if (trim((string)$this->_email->from) === '' && (string)$this->cfgValue('mode', 'smtp') === 'smtp') {
            return $this->failure('SMTP 用户或发件邮箱不能为空', false);
        }
        if ((string)$this->cfgValue('mode', 'smtp') === 'smtp' && trim((string)$this->cfgValue('host', '')) === '') {
            return $this->failure('SMTP 地址不能为空', false);
        }

        try {
            $mailer = new PHPMailer();
            $mailer->CharSet = 'UTF-8';
            $mailer->Encoding = 'base64';
            $mailer->Timeout = 30;

            switch ((string)$this->cfgValue('mode', 'smtp')) {
                case 'mail':
                    break;
                case 'sendmail':
                    $mailer->IsSendmail();
                    break;
                case 'smtp':
                default:
                    $mailer->IsSMTP();
                    if ($this->cfgEnabled('validate', 'validate')) $mailer->SMTPAuth = true;

                    if ($this->cfgEnabled('validate', 'ssl')) {
                        $mailer->SMTPSecure = 'ssl';
                    } else if ($this->cfgEnabled('validate', 'tls')) {
                        $mailer->SMTPSecure = 'tls';
                    }

                    $mailer->Host     = (string)$this->cfgValue('host', '');
                    $mailer->Port     = (int)$this->cfgValue('port', 25);
                    $mailer->Username = (string)$this->cfgValue('user', '');
                    $mailer->Password = (string)$this->cfgValue('pass', '');
                    break;
            }

            if (trim((string)$this->_email->from) !== '') {
                $mailer->SetFrom($this->_email->from, $this->_email->fromName);
            }
            if (isset($this->_email->replyTo) && isset($this->_email->replyToName)) {
                $mailer->AddReplyTo($this->_email->replyTo, $this->_email->replyToName);
            }
            $mailer->Subject = $this->_email->subject;
            $mailer->AltBody = $this->_email->altBody;
            if ($this->cfgEnabled('validate', 'solve544') && trim((string)$this->_email->from) !== '') {
                $mailer->AddCC($this->_email->from);
            }

            $mailer->MsgHTML($this->_email->msgHtml);
            $mailer->AddAddress($this->_email->reciver, $this->_email->reciverName);

            $ok = $mailer->Send();
            $error = $mailer->ErrorInfo;

            $mailer->ClearAddresses();
            $mailer->ClearReplyTos();

            return $ok ? $this->success() : $this->failure($error ?: 'PHPMailer 发送失败', $this->isTemporaryMailerError($error));
        } catch (\Exception $e) {
            return $this->failure($e->getMessage(), $this->isTemporaryMailerError($e->getMessage()));
        } catch (\Throwable $e) {
            return $this->failure($e->getMessage(), $this->isTemporaryMailerError($e->getMessage()));
        }
    }

    private function sendByResend(): array
    {
        $apiKey = trim((string)$this->cfgValue('resendApiKey', ''));
        $from = trim((string)$this->cfgValue('resendFrom', $this->_email->from));
        $endpoint = trim((string)$this->cfgValue('resendApiUrl', ''));
        $endpoint = $endpoint ?: 'https://api.resend.com/emails';

        if ($apiKey === '') return $this->failure('Resend API Key 不能为空', false);
        if ($from === '') return $this->failure('Resend 发件邮箱不能为空', false);
        if (empty($this->_email->reciver)) return $this->failure('收件人邮箱不能为空', false);
        if (!filter_var($endpoint, FILTER_VALIDATE_URL)) return $this->failure('Resend API 地址格式不正确', false);
        if (stripos($endpoint, 'https://') !== 0) return $this->failure('Resend API 地址必须使用 HTTPS', false);

        $caFile = trim((string)$this->cfgValue('resendCaFile', ''));
        if ($caFile !== '' && (!is_file($caFile) || !is_readable($caFile))) {
            return $this->failure('Resend CA 证书路径不可读取: ' . $caFile, false);
        }

        $payload = [
            'from' => $this->formatResendFrom($from, $this->_email->fromName),
            'to' => [$this->_email->reciver],
            'subject' => $this->_email->subject,
            'html' => $this->_email->msgHtml,
            'text' => $this->_email->altBody,
        ];

        if (!empty($this->_email->replyTo)) {
            $payload['reply_to'] = [$this->_email->replyTo];
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) return $this->failure('Resend 请求内容编码失败', false);

        $response = '';
        $status = 0;
        $headers = [];

        if (function_exists('curl_init')) {
            $headerLines = [];
            $ch = curl_init($endpoint);
            $curlOptions = [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$headerLines) {
                    $headerLines[] = trim($header);
                    return strlen($header);
                },
            ];
            if ($caFile !== '') {
                $curlOptions[CURLOPT_CAINFO] = $caFile;
            }
            curl_setopt_array($ch, $curlOptions);

            $response = (string)curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $headers = $headerLines;

            if ($errno) {
                return $this->failure('Resend 请求失败: ' . $error, true);
            }
        } else {
            $streamOptions = [
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", [
                        'Authorization: Bearer ' . $apiKey,
                        'Content-Type: application/json',
                    ]),
                    'content' => $body,
                    'timeout' => 30,
                    'ignore_errors' => true,
                ],
            ];
            if ($caFile !== '') {
                $streamOptions['ssl'] = [
                    'cafile' => $caFile,
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ];
            }
            $context = stream_context_create($streamOptions);
            $raw = @file_get_contents($endpoint, false, $context);
            $response = $raw === false ? '' : (string)$raw;
            $headers = $http_response_header ?? [];
            $status = $this->httpStatusFromHeaders($headers);
        }

        if ($status >= 200 && $status < 300) {
            return $this->success();
        }

        $retryAfter = $this->retryAfterFromHeaders($headers);
        if ($status === 429) {
            return $this->failure('Resend 触发限流(429): ' . $this->shortText($response, 500), true, $retryAfter > 0 ? $retryAfter : 60);
        }
        if ($status === 0 || $status >= 500) {
            return $this->failure('Resend 临时错误(' . $status . '): ' . $this->shortText($response, 500), true, $retryAfter);
        }

        return $this->failure('Resend 返回错误(' . $status . '): ' . $this->shortText($response, 500), false);
    }

    private function markTaskSent(int $id): void
    {
        $now = time();
        $this->_db->query($this->_db->update(Plugin::queueTableName())->rows([
            'status' => 'sent',
            'updated' => $now,
            'sent_at' => $now,
            'last_error' => '',
            'next_retry' => 0,
            'locked_until' => 0,
        ])->where('id = ?', $id));
    }

    private function markTaskFailed(array $task, string $error, bool $temporary, int $retryAfter = 0): string
    {
        $attempts = (int)($task['attempts'] ?? 0) + 1;
        $failed = !$temporary || $attempts >= $this->maxAttempts();
        $backoff = $retryAfter > 0 ? $retryAfter : min(3600, (int)(60 * pow(2, max(0, $attempts - 1))));
        $status = $failed ? 'failed' : 'pending';

        $this->_db->query($this->_db->update(Plugin::queueTableName())->rows([
            'status' => $status,
            'updated' => time(),
            'attempts' => $attempts,
            'last_error' => $this->shortText($error, 2000),
            'next_retry' => $failed ? 0 : time() + $backoff,
            'locked_until' => 0,
        ])->where('id = ?', (int)$task['id']));

        return $status;
    }

    private function retryQueue(): void
    {
        if (!$this->request->isPost()) {
            throw new \Typecho\Widget\Exception(_t('Method Not Allowed'), 405);
        }

        $id = (int)$this->request->get('id', 0);
        $rows = [
            'status' => 'pending',
            'updated' => time(),
            'attempts' => 0,
            'last_error' => '',
            'next_retry' => 0,
            'locked_until' => 0,
        ];

        $query = $this->_db->update(Plugin::queueTableName())->rows($rows)->where('status = ?', 'failed');
        if ($id > 0) {
            $query->where('id = ?', $id);
        }

        $this->_db->query($query);
        $this->widget('Widget_Notice')->set(_t('失败任务已重新加入待发送列表'), 'success');
        $this->response->goBack();
    }

    private function clearLogs(): void
    {
        if (!$this->request->isPost()) {
            throw new \Typecho\Widget\Exception(_t('Method Not Allowed'), 405);
        }

        $this->_db->query($this->_db->delete(Plugin::queueTableName())->where('status = ? OR status = ?', 'sent', 'failed'));
        $this->widget('Widget_Notice')->set(_t('发送日志已清理'), 'success');
        $this->response->goBack();
    }

    private function cleanupOldLogs(): void
    {
        $days = (int)$this->cfgValue('logKeepDays', 30);
        if ($days < 1) return;

        $before = time() - ($days * 86400);
        $this->_db->query($this->_db->delete(Plugin::queueTableName())->where('status = ? AND updated > ? AND updated < ?', 'sent', 0, $before));
    }

    private function decodeTaskPayload(string $payload): ?array
    {
        $decoded = json_decode($payload, true);
        if (!is_array($decoded) || ($decoded['schema'] ?? '') !== 'qiwi-comment-mail-task') {
            return null;
        }
        return $decoded;
    }

    private function getTemplate(string $template = 'owner'): string
    {
        $cfgKey = $template . 'Template';
        if ($this->cfgValue($cfgKey, '') !== '') {
            return (string)$this->cfgValue($cfgKey, '');
        }

        $filename = $this->_templateDir . $template . '.html';

        if (!file_exists($filename)) {
            throw new \Typecho\Widget\Exception('模板文件' . $template . '不存在', 404);
        }

        return file_get_contents($filename);
    }

    public function testMail()
    {
        if (!$this->request->isPost()) {
            throw new \Typecho\Widget\Exception(_t('Method Not Allowed'), 405);
        }

        if (self::widget('TypechoPlugin\QiwiCommentMail\Console')->testMailForm()->validate()) {
            $this->response->goBack();
        }

        $email = $this->request->from('toName', 'to', 'title', 'content');

        $this->_email = new Email();
        $this->_email->from = (string)(((string)$this->cfgValue('mode', 'smtp') === 'resend' && $this->cfgValue('resendFrom', '') !== '') ? $this->cfgValue('resendFrom', '') : $this->cfgValue('user', ''));
        $this->_email->fromName = (string)($this->cfgValue('fromName', '') !== '' ? $this->cfgValue('fromName', '') : $this->_options->title);
        $this->_email->reciver = $email['to'] ? $email['to'] : $this->_user->mail;
        $this->_email->reciverName = $email['toName'] ? $email['toName'] : $this->_user->screenName;
        $this->_email->subject = $email['title'];
        $this->_email->altBody = $email['content'];
        $this->_email->msgHtml = $email['content'];

        $result = $this->sendMail();

        $this->widget('\Widget\Notice')->set(
            !empty($result['ok']) ? _t('邮件发送成功') : _t('邮件发送失败: ' . ($result['message'] ?? '未知错误')),
            !empty($result['ok']) ? 'success' : 'notice'
        );

        $this->response->goBack();
    }

    public function editTheme($file)
    {
        if (!$this->request->isPost()) {
            throw new \Typecho\Widget\Exception(_t('Method Not Allowed'), 405);
        }

        $path = $this->templatePath($file);

        if ($path && is_writeable($path)) {
            if (file_put_contents($path, (string)$this->request->content, LOCK_EX) !== false) {
                $this->widget('Widget_Notice')->set(_t("文件 %s 的更改已经保存", $file), 'success');
            } else {
                $this->widget('Widget_Notice')->set(_t("文件 %s 无法被写入", $file), 'error');
            }
            $this->response->goBack();
        }

        throw new \Typecho\Widget\Exception(_t('您编辑的模板文件不存在'));
    }

    private function templatePath($file): ?string
    {
        $file = basename((string)$file);
        if (!preg_match('/^[A-Za-z0-9_.-]+\.html$/', $file)) {
            return null;
        }

        $path = realpath($this->_templateDir . $file);
        $dir = realpath($this->_templateDir);
        if ($path === false || $dir === false || strpos($path, $dir . DIRECTORY_SEPARATOR) !== 0) {
            return null;
        }

        return $path;
    }

    private function acquireWorkerLock(): bool
    {
        $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'qiwi-comment-mail-' . sha1(__TYPECHO_ROOT_DIR__) . '.lock';
        $handle = @fopen($path, 'c');
        if (!$handle) return false;

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }

        $this->_workerLock = $handle;
        return true;
    }

    private function releaseWorkerLock(): void
    {
        if ($this->_workerLock) {
            flock($this->_workerLock, LOCK_UN);
            fclose($this->_workerLock);
            $this->_workerLock = null;
        }
    }

    private function respectRateLimit(float &$lastStartedAt): void
    {
        $rate = $this->rateLimitPerSecond();
        $interval = 1.0 / $rate;
        if ($lastStartedAt > 0) {
            $elapsed = microtime(true) - $lastStartedAt;
            if ($elapsed < $interval) {
                usleep((int)(($interval - $elapsed) * 1000000));
            }
        }
        $lastStartedAt = microtime(true);
    }

    private function formatResendFrom(string $email, string $name = ''): string
    {
        $email = trim($email);
        $name = trim($name);
        if ($name === '') return $email;

        $name = str_replace(['"', "\r", "\n"], ['', '', ''], $name);
        return $name . ' <' . $email . '>';
    }

    private function httpStatusFromHeaders(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', (string)$header, $matches)) {
                return (int)$matches[1];
            }
        }
        return 0;
    }

    private function retryAfterFromHeaders(array $headers): int
    {
        foreach ($headers as $header) {
            if (!preg_match('/^Retry-After:\s*(.+)$/i', (string)$header, $matches)) {
                continue;
            }

            $value = trim($matches[1]);
            if (ctype_digit($value)) {
                return max(0, (int)$value);
            }

            $time = strtotime($value);
            return $time ? max(0, $time - time()) : 0;
        }
        return 0;
    }

    private function isTemporaryMailerError($message): bool
    {
        $message = strtolower((string)$message);
        foreach (['authenticate', 'invalid address', 'provide_address', 'empty', '不能为空'] as $needle) {
            if (strpos($message, $needle) !== false) {
                return false;
            }
        }
        return true;
    }

    private function ownerMail(int $uid): string
    {
        if ($uid <= 0) return '';

        try {
            $row = $this->_db->fetchRow($this->_db->select('mail')
                ->from('table.users')
                ->where('uid = ?', $uid)
                ->limit(1));
            return $row && !empty($row['mail']) ? (string)$row['mail'] : '';
        } catch (\Exception $e) {
            return '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function queueLimit(): int
    {
        $limit = (int)$this->cfgValue('batchSize', 2);
        return max(1, min(20, $limit));
    }

    private function rateLimitPerSecond(): int
    {
        $rate = (int)$this->cfgValue('rateLimitPerSecond', 2);
        return max(1, min(10, $rate));
    }

    private function maxAttempts(): int
    {
        $attempts = (int)$this->cfgValue('maxAttempts', 5);
        return max(1, min(20, $attempts));
    }

    private function shortText(string $text, int $max): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $max, 'UTF-8');
        }

        return substr($text, 0, $max);
    }

    private function cfgValue(string $key, $default = '')
    {
        return isset($this->_cfg->{$key}) ? $this->_cfg->{$key} : $default;
    }

    private function cfgArray(string $key): array
    {
        if (empty($this->_cfg->{$key})) {
            return [];
        }
        return is_array($this->_cfg->{$key}) ? $this->_cfg->{$key} : [$this->_cfg->{$key}];
    }

    private function cfgEnabled(string $key, string $value): bool
    {
        return in_array($value, $this->cfgArray($key), true);
    }

    private function success(): array
    {
        return ['ok' => true];
    }

    private function failure(string $message, bool $temporary = true, int $retryAfter = 0): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'temporary' => $temporary,
            'retry_after' => $retryAfter,
        ];
    }
}
