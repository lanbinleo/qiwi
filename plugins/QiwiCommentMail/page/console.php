<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * QiwiCommentMail
 * Typecho 异步评论邮件提醒插件。基于 CommentToMail 原版维护，感谢 xcsoft 的原始贡献。
 *
 * @license GNU General Public License 3.0
 */

require_once 'header.php';
require_once 'menu.php';

use \Typecho\Widget;
use \TypechoPlugin\QiwiCommentMail\Plugin;

Widget::widget('Widget_Security')->to($security);

$current = $request->get('act', 'index');
$theme = basename((string)$request->get('file', 'owner.html'));
$title = $current == 'index' ? $menu->title : ($current == 'queue' ? '队列与日志' : '编辑邮件模板 ' . $theme);

$actionUrl = function ($do, array $query = []) use ($security) {
    $query = array_merge(['do' => $do], $query);
    return $security->getIndex('/action/' . Plugin::$_action . '?' . http_build_query($query));
};

$escape = function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$shortTime = function ($timestamp) {
    $timestamp = (int)$timestamp;
    if ($timestamp <= 0) return '-';

    $now = time();
    $date = date('Y-m-d', $timestamp);
    $today = date('Y-m-d', $now);
    $todayStart = strtotime($today);
    $dateStart = strtotime($date);
    $dayDiff = (int)(($todayStart - $dateStart) / 86400);

    if ($date === $today) return '今天 ' . date('H:i', $timestamp);
    if ($dayDiff === 1) return '昨天 ' . date('H:i', $timestamp);
    if ($dayDiff === -1) return '明天 ' . date('H:i', $timestamp);
    if ($dayDiff > 1 && $dayDiff <= 6) return $dayDiff . '天前';
    if ($dayDiff < -1 && $dayDiff >= -6) return abs($dayDiff) . '天后';

    $lastMonth = date('Y-m', strtotime('first day of last month', $now));
    $nextMonth = date('Y-m', strtotime('first day of next month', $now));
    $month = date('Y-m', $timestamp);
    if ($month === $lastMonth) return '上个月';
    if ($month === $nextMonth) return '下个月';

    if (date('Y', $timestamp) === date('Y', $now)) {
        return date('n月j日', $timestamp);
    }

    return date('Y-m-d', $timestamp);
};

$timeCell = function ($timestamp) use ($escape, $shortTime) {
    $timestamp = (int)$timestamp;
    if ($timestamp <= 0) return '<span class="qcm-muted">-</span>';

    return '<span class="qcm-time" title="' . $escape(date('Y-m-d H:i:s', $timestamp)) . '">'
        . $escape($shortTime($timestamp))
        . '</span>';
};

$statusView = [
    'pending' => ['icon' => '○', 'class' => 'pending'],
    'sending' => ['icon' => '…', 'class' => 'sending'],
    'sent' => ['icon' => '✓', 'class' => 'sent'],
    'failed' => ['icon' => '!', 'class' => 'failed'],
];

$recipientShort = [
    'owner' => '管',
    'guest' => '客',
];

$eventShort = [
    'new_comment' => '评',
    'reply_published' => '回',
    'reply_approved' => '审',
];
?>
<div class="main">
    <div class="body container">
        <div class="typecho-page-title">
            <h2><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
        </div>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12">
                <ul class="typecho-option-tabs fix-tabs clearfix">
                    <li <?= ($current == 'index' ? ' class="current"' : '') ?>>
                        <a href="<?php $options->adminUrl('extending.php?panel=' . Plugin::$_panel); ?>"><?php _e('邮件发送测试'); ?></a>
                    </li>
                    <li <?= ($current == 'theme' ? ' class="current"' : '') ?>>
                        <a href="<?php $options->adminUrl('extending.php?panel=' . Plugin::$_panel . '&act=theme'); ?>">
                            <?php _e('编辑邮件模板'); ?>
                        </a>
                    </li>
                    <li <?= ($current == 'queue' ? ' class="current"' : '') ?>>
                        <a href="<?php $options->adminUrl('extending.php?panel=' . Plugin::$_panel . '&act=queue'); ?>">
                            <?php _e('队列与日志'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php $options->adminUrl('options-plugin.php?config=QiwiCommentMail') ?>"><?php _e('插件设置'); ?></a>
                    </li>
                </ul>
            </div>
            <?php if ($current == 'index') : ?>
                <div class="typecho-edit-theme">
                    <div class="col-mb-12 content">
                        <?php Widget::widget('TypechoPlugin\QiwiCommentMail\Console')->testMailForm()->render(); ?>
                    </div>
                </div>
            <?php elseif ($current == 'queue') :
                $console = Widget::widget('TypechoPlugin\QiwiCommentMail\Console');
                $stats = $console->queueStats();
                $rows = $console->queueRows();
            ?>
                <div class="col-mb-12">
                    <style>
                        .qcm-queue-summary {
                            margin: 0 0 14px;
                        }
                        .qcm-queue-actions {
                            display: flex;
                            flex-wrap: wrap;
                            gap: 8px;
                            margin-bottom: 16px;
                        }
                        .qcm-table-wrap {
                            overflow-x: auto;
                            padding-bottom: 6px;
                        }
                        .qcm-queue-grid {
                            width: 1360px;
                            min-width: 1360px;
                        }
                        .qcm-queue-row {
                            display: grid;
                            grid-template-columns: 48px 64px 64px 64px 300px 230px 64px 102px 102px 102px 160px 60px;
                            align-items: center;
                            border-top: 1px solid #F0F0EC;
                        }
                        .qcm-queue-head {
                            border-top: 0;
                            border-bottom: 2px solid #F0F0EC;
                            font-weight: 700;
                        }
                        .qcm-queue-body .qcm-queue-row:hover .qcm-queue-cell {
                            background-color: #F6F6F3;
                        }
                        .qcm-queue-cell {
                            box-sizing: border-box;
                            overflow: hidden;
                            padding: 10px 8px;
                            padding-left: 8px;
                            padding-right: 8px;
                            text-overflow: ellipsis;
                            white-space: nowrap;
                            word-break: normal;
                        }
                        .qcm-queue-head .qcm-queue-cell {
                            padding-top: 0;
                            padding-bottom: 10px;
                        }
                        .qcm-center { text-align: center; }
                        .qcm-icon-cell,
                        .qcm-chip-cell,
                        .qcm-attempts {
                            text-align: center;
                        }
                        .qcm-status-icon {
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            width: 22px;
                            height: 22px;
                            border: 1px solid #D9D9D6;
                            border-radius: 50%;
                            font-size: 13px;
                            font-weight: 700;
                            line-height: 1;
                        }
                        .qcm-status-pending {
                            color: #8a6500;
                            background: #fff8df;
                            border-color: #ead48c;
                        }
                        .qcm-status-sending {
                            color: #467B96;
                            background: #edf6fa;
                            border-color: #b9d7e5;
                        }
                        .qcm-status-sent {
                            color: #3f7c4b;
                            background: #edf8ef;
                            border-color: #b7d8bd;
                        }
                        .qcm-status-failed {
                            color: #9a3f2f;
                            background: #fff1ec;
                            border-color: #e4b6aa;
                        }
                        .qcm-chip {
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            min-width: 22px;
                            height: 22px;
                            padding: 0 5px;
                            border: 1px solid #D9D9D6;
                            border-radius: 4px;
                            background: #F6F6F3;
                            color: #555;
                            font-weight: 600;
                            line-height: 1;
                        }
                        .qcm-line {
                            display: block;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            white-space: nowrap;
                        }
                        .qcm-main-line,
                        .qcm-recipient-line { max-width: 100%; }
                        .qcm-error-text {
                            display: block;
                            max-width: 100%;
                            overflow: hidden;
                            color: #9a3f2f;
                            text-overflow: ellipsis;
                            white-space: nowrap;
                        }
                        .qcm-muted,
                        .qcm-meta {
                            color: #999;
                        }
                        .qcm-time {
                            color: #555;
                        }
                        .qcm-retry-form {
                            margin: 0;
                        }
                        .qcm-retry-form .btn {
                            padding: 4px 10px;
                        }
                        .qcm-empty {
                            grid-column: 1 / -1;
                            text-align: center;
                        }
                        @media (max-width: 767px) {
                            .qcm-queue-grid {
                                width: 1180px;
                                min-width: 1180px;
                            }
                            .qcm-queue-row {
                                grid-template-columns: 48px 64px 64px 64px 220px 180px 64px 96px 96px 96px 150px 58px;
                            }
                        }
                    </style>
                    <p class="qcm-queue-summary">
                        待发送: <strong><?php echo (int)$stats['pending']; ?></strong>
                        &nbsp; 处理中: <strong><?php echo (int)$stats['sending']; ?></strong>
                        &nbsp; 已发送: <strong><?php echo (int)$stats['sent']; ?></strong>
                        &nbsp; 失败: <strong><?php echo (int)$stats['failed']; ?></strong>
                    </p>
                    <div class="qcm-queue-actions">
                        <form method="post" action="<?php echo htmlspecialchars($actionUrl('runQueue'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                            <button class="btn primary" type="submit">立即处理队列</button>
                        </form>
                        <form method="post" action="<?php echo htmlspecialchars($actionUrl('retryQueue'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                            <button class="btn" type="submit">重试全部失败</button>
                        </form>
                        <form method="post" action="<?php echo htmlspecialchars($actionUrl('clearLogs'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" onsubmit="return confirm('确定清理已完成和失败的发送日志吗？待发送和处理中的任务不会被删除。');">
                            <button class="btn" type="submit">清理日志</button>
                        </form>
                    </div>
                    <div class="qcm-table-wrap">
                        <div class="qcm-queue-grid" role="table" aria-label="QiwiCommentMail 队列与日志">
                            <div class="qcm-queue-row qcm-queue-head" role="row">
                                <div class="qcm-queue-cell" role="columnheader">ID</div>
                                <div class="qcm-queue-cell qcm-center" role="columnheader" title="状态">状态</div>
                                <div class="qcm-queue-cell qcm-center" role="columnheader" title="收件类型">类型</div>
                                <div class="qcm-queue-cell qcm-center" role="columnheader" title="触发事件">事件</div>
                                <div class="qcm-queue-cell" role="columnheader">文章 / 评论者</div>
                                <div class="qcm-queue-cell" role="columnheader">收件人</div>
                                <div class="qcm-queue-cell qcm-center" role="columnheader" title="尝试次数">次数</div>
                                <div class="qcm-queue-cell" role="columnheader" title="创建时间">创建</div>
                                <div class="qcm-queue-cell" role="columnheader" title="更新时间">更新</div>
                                <div class="qcm-queue-cell" role="columnheader" title="下次重试">重试</div>
                                <div class="qcm-queue-cell" role="columnheader">错误</div>
                                <div class="qcm-queue-cell" role="columnheader">操作</div>
                            </div>
                            <div class="qcm-queue-body" role="rowgroup">
                                <?php if (!$rows) : ?>
                                    <div class="qcm-queue-row" role="row">
                                        <div class="qcm-queue-cell qcm-empty" role="cell">暂无队列或日志</div>
                                    </div>
                                <?php endif; ?>
                                <?php foreach ($rows as $row) :
                                    $status = (string)$row['status'];
                                    $statusMeta = $statusView[$status] ?? ['icon' => '?', 'class' => 'unknown'];
                                    $statusLabel = $console->statusLabel($status);
                                    $recipientType = (string)$row['recipient_type'];
                                    $recipientLabel = $console->recipientLabel($recipientType);
                                    $event = (string)$row['event'];
                                    $eventLabel = $console->eventLabel($event);
                                    $commentAuthor = trim((string)$row['summary']['author']);
                                    $commentMail = trim((string)$row['summary']['mail']);
                                    $commentContact = trim($commentAuthor . ($commentMail !== '' ? ' <' . $commentMail . '>' : ''));
                                    $commentTitle = (string)$row['summary']['title'];
                                    $commentFull = trim($commentTitle . ($commentContact !== '' ? ' · ' . $commentContact : ''));
                                    $recipientName = trim((string)$row['recipient_name']);
                                    $recipientMail = trim((string)$row['recipient_mail']);
                                    $recipientFull = trim($recipientName . ($recipientMail !== '' ? ' <' . $recipientMail . '>' : ''));
                                    $error = trim((string)$row['last_error']);
                                ?>
                                    <div class="qcm-queue-row" role="row">
                                        <div class="qcm-queue-cell" role="cell"><?php echo (int)$row['id']; ?></div>
                                        <div class="qcm-queue-cell qcm-icon-cell" role="cell">
                                            <span class="qcm-status-icon qcm-status-<?php echo $escape($statusMeta['class']); ?>" title="<?php echo $escape($statusLabel); ?>" aria-label="<?php echo $escape($statusLabel); ?>">
                                                <?php echo $escape($statusMeta['icon']); ?>
                                            </span>
                                        </div>
                                        <div class="qcm-queue-cell qcm-chip-cell" role="cell">
                                            <span class="qcm-chip" title="<?php echo $escape($recipientLabel); ?>">
                                                <?php echo $escape($recipientShort[$recipientType] ?? '?'); ?>
                                            </span>
                                        </div>
                                        <div class="qcm-queue-cell qcm-chip-cell" role="cell">
                                            <span class="qcm-chip" title="<?php echo $escape($eventLabel); ?>">
                                                <?php echo $escape($eventShort[$event] ?? '?'); ?>
                                            </span>
                                        </div>
                                        <div class="qcm-queue-cell" role="cell">
                                            <span class="qcm-line qcm-main-line" title="<?php echo $escape($commentFull); ?>">
                                                <?php if (!empty($row['summary']['permalink'])) : ?>
                                                    <a href="<?php echo $escape($row['summary']['permalink']); ?>" target="_blank" rel="noopener noreferrer">
                                                        <?php echo $escape($commentTitle); ?>
                                                    </a>
                                                <?php else : ?>
                                                    <?php echo $escape($commentTitle); ?>
                                                <?php endif; ?>
                                                <?php if ($commentContact !== '') : ?>
                                                    <span class="qcm-meta"> · <?php echo $escape($commentContact); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="qcm-queue-cell" role="cell">
                                            <span class="qcm-line qcm-recipient-line" title="<?php echo $escape($recipientFull); ?>">
                                                <?php echo $escape($recipientName !== '' ? $recipientName : $recipientMail); ?>
                                                <?php if ($recipientName !== '' && $recipientMail !== '') : ?>
                                                    <span class="qcm-meta">&lt;<?php echo $escape($recipientMail); ?>&gt;</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="qcm-queue-cell qcm-attempts" role="cell"><?php echo (int)$row['attempts']; ?></div>
                                        <div class="qcm-queue-cell" role="cell"><?php echo $timeCell($row['created']); ?></div>
                                        <div class="qcm-queue-cell" role="cell"><?php echo $timeCell($row['updated']); ?></div>
                                        <div class="qcm-queue-cell" role="cell"><?php echo $timeCell($row['next_retry']); ?></div>
                                        <div class="qcm-queue-cell" role="cell">
                                            <?php if ($error !== '') : ?>
                                                <span class="qcm-error-text" title="<?php echo $escape($error); ?>"><?php echo $escape($error); ?></span>
                                            <?php else : ?>
                                                <span class="qcm-muted">-</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="qcm-queue-cell" role="cell">
                                            <?php if ($status === 'failed') : ?>
                                                <form class="qcm-retry-form" method="post" action="<?php echo $escape($actionUrl('retryQueue', ['id' => (int)$row['id']])); ?>">
                                                    <button class="btn" type="submit">重试</button>
                                                </form>
                                            <?php else : ?>
                                                <span class="qcm-muted">-</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else :
                Widget::widget('TypechoPlugin\QiwiCommentMail\Console')->to($files);
            ?>
                <div class="typecho-edit-theme">
                    <div class="col-mb-12 content">
                        <form method="post" name="theme" id="theme" action="<?php echo htmlspecialchars($actionUrl('editTheme'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                            <label for="content" class="sr-only"><?php _e('编辑源码'); ?></label>
                            <textarea name="content" id="content" class="w-100 mono" <?php if (!$files->currentIsWriteable()) echo 'readonly'; ?>><?php echo $files->currentContent(); ?></textarea>
                            <p class="submit">
                                <?php if ($files->currentIsWriteable()) : ?>
                                    <input type="hidden" name="edit" value="<?php echo htmlspecialchars($files->currentFile(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" />
                                    <button type="submit" class="btn primary"><?php _e('保存文件'); ?></button>
                                <?php else : ?>
                                    <em><?php _e('文件无写入权限'); ?></em>
                                <?php endif; ?>
                            </p>
                        </form>
                    </div>
                    <ul class="col-mb-12">
                        <li><strong>模板文件</strong></li>
                        <?php while ($files->next()) : ?>
                            <li <?php if ($files->current) echo "class='current'"; ?>>
                                <a href="<?php $options->adminUrl('extending.php?panel=' . Plugin::$_panel . '&act=theme' . '&file=' . rawurlencode($files->file)); ?>">
                                    <?php $files->file(); ?>
                                </a>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once 'copyright.php';
require_once 'common-js.php';
require_once 'footer.php';
?>
