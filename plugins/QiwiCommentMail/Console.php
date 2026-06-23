<?php

namespace TypechoPlugin\QiwiCommentMail;

use \Typecho\{Widget};
use \Typecho\Db;
use \Typecho\Widget\Helper\Form;
use \Typecho\Widget\Helper\Form\Element\{Text, Hidden, Submit, Textarea};

/**
 * QiwiCommentMail
 * Typecho 异步评论邮件提醒插件。基于 CommentToMail 原版维护，感谢 xcsoft 的原始贡献。
 *
 * @license GNU General Public License 3.0
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class Console extends Widget
{
    /**
     * @var string
     */
    private $_templateDir = __DIR__ . '/template/';

    /**
     * @var string
     */
    private $_currentFile;

    public function execute()
    {
        $this->widget('Widget_User')->pass('administrator');
        $files = glob($this->_templateDir . '*.html');
        $this->_currentFile = basename((string)$this->request->get('file', 'owner.html'));

        if (preg_match('/^[A-Za-z0-9_.-]+\.html$/', $this->_currentFile) && file_exists($this->_templateDir . $this->_currentFile)) {
            foreach ($files as $file) {
                if (!file_exists($file)) continue;
                $file = basename($file);
                $this->push([
                    'file' => $file,
                    'current' => ($file == $this->_currentFile)
                ]);
            }
            return;
        }

        throw new \Typecho\Widget\Exception('模板文件不存在', 404);
    }

    public function getMenuTitle(): string
    {
        return _t('编辑文件 %s', $this->_currentFile);
    }

    public function currentContent(): string
    {
        return htmlspecialchars(file_get_contents($this->_templateDir . $this->_currentFile), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function currentIsWriteable(): bool
    {
        return is_writeable($this->_templateDir . $this->_currentFile);
    }

    public function currentFile(): string
    {
        return $this->_currentFile;
    }

    public function queueStats(): array
    {
        Plugin::ensureQueueTable();

        $db = Db::get();
        $stats = [
            'pending' => 0,
            'sending' => 0,
            'sent' => 0,
            'failed' => 0,
        ];

        $rows = $db->fetchAll('SELECT status, COUNT(*) AS total FROM ' . Plugin::queueTableName() . ' GROUP BY status');
        foreach ($rows as $row) {
            $status = (string)$row['status'];
            if (array_key_exists($status, $stats)) {
                $stats[$status] = (int)$row['total'];
            }
        }

        return $stats;
    }

    public function queueRows(int $limit = 50): array
    {
        Plugin::ensureQueueTable();

        $db = Db::get();
        $limit = max(1, min(100, $limit));
        $rows = $db->fetchAll("SELECT id, dedupe_key, coid, cid, parent, recipient_type, event, recipient_mail, recipient_name, payload, status, attempts, last_error, next_retry, locked_until, sent_at, created, updated FROM " . Plugin::queueTableName() . " ORDER BY id DESC LIMIT {$limit}");

        foreach ($rows as &$row) {
            $payload = $this->decodePayload((string)$row['payload']);
            $comment = $payload && !empty($payload['comment']) && is_array($payload['comment'])
                ? $payload['comment']
                : [];
            $row['summary'] = [
                'author' => (string)($comment['author'] ?? ''),
                'mail' => (string)($comment['mail'] ?? ''),
                'title' => (string)($comment['title'] ?? '任务内容无法解析'),
                'permalink' => (string)($comment['permalink'] ?? ''),
            ];
        }

        return $rows;
    }

    public function statusLabel(string $status): string
    {
        return [
            'pending' => '待发送',
            'sending' => '处理中',
            'sent' => '已发送',
            'failed' => '失败',
        ][$status] ?? '未知';
    }

    public function recipientLabel(string $type): string
    {
        return [
            'owner' => '管理员通知',
            'guest' => '用户通知',
        ][$type] ?? '未知';
    }

    public function eventLabel(string $event): string
    {
        return [
            'new_comment' => '新评论',
            'reply_published' => '公开回复',
            'reply_approved' => '审核通过',
        ][$event] ?? $event;
    }

    public function formatTime($timestamp): string
    {
        $timestamp = (int)$timestamp;
        return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : '-';
    }

    private function decodePayload(string $payload): ?array
    {
        $decoded = json_decode($payload, true);
        if (!is_array($decoded) || ($decoded['schema'] ?? '') !== 'qiwi-comment-mail-task') {
            return null;
        }
        return $decoded;
    }

    public function testMailForm(): Form
    {
        $options = Widget::widget('Widget_Options');
        Widget::widget('Widget_Security')->to($security);
        $form = new Form(
            $security->getIndex('/action/' . Plugin::$_action . '?do=testMail'),
            Form::POST_METHOD
        );

        $toName = new Text('toName', null, null, _t('收件人名称'), _t('为空则使用博主昵称'));
        $form->addInput($toName);

        $to = new Text('to', null, null, _t('收件人邮箱'), _t('为空则使用博主邮箱'));
        $form->addInput($to);

        $title = new Text('title', null, null, _t('邮件标题 *'));
        $form->addInput($title);

        $content = new Textarea('content', null, null, _t('邮件内容 *'));
        $content->input->setAttribute('class', 'w-100 mono');
        $form->addInput($content);

        $do = new Hidden('do');
        $form->addInput($do);

        $submit = new Submit();
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        $do->value('testMail');
        $submit->value('发送邮件');

        $to->addRule('email', _t('非法的邮件地址'));
        $title->addRule('required', _t('邮件标题不能为空'));
        $content->addRule('required', _t('邮件内容不能为空'));

        return $form;
    }
}
