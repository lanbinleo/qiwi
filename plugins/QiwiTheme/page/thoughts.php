<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Qiwi 段落想法审核台。
 *
 * @package QiwiTheme
 * @author  Leo 里奥
 * @version 2.3.0
 * @link    https://bboreo.com/
 */

require_once 'header.php';
require_once 'menu.php';

use \Typecho\Widget;
use TypechoPlugin\QiwiTheme\ThoughtsConsole;

Widget::widget('Widget_Security')->to($security);

$current = (string)$request->get('status', 'waiting');
if (!in_array($current, ['waiting', 'approved', 'all'], true)) {
    $current = 'waiting';
}
$page = max(1, (int)$request->get('page', 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$console = Widget::widget('TypechoPlugin\QiwiTheme\ThoughtsConsole');
$stats = $console->thoughtStats();
$rows = $console->thoughtRows($current, $perPage + 1, $offset);
$hasMore = count($rows) > $perPage;
$rows = array_slice($rows, 0, $perPage);

$escape = function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$actionUrl = function ($do, array $query = []) use ($security) {
    $query = array_merge(['do' => $do], $query);
    return $security->getIndex('/action/qiwi-theme?' . http_build_query($query));
};

$panelUrl = function ($status, $page) {
    return 'extending.php?panel=QiwiTheme/page/thoughts.php&status=' . rawurlencode($status) . '&page=' . (int)$page;
};

$thoughtsPanel = 'QiwiTheme/page/thoughts.php';
?>
<div class="main">
    <div class="body container">
        <div class="typecho-page-title">
            <h2>Qiwi 想法审核</h2>
        </div>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12">
                <ul class="typecho-option-tabs fix-tabs clearfix">
                    <li <?= $current === 'waiting' ? ' class="current"' : '' ?>>
                        <a href="<?php $options->adminUrl($panelUrl('waiting', 1)); ?>">待审核<?= $stats['waiting'] > 0 ? ' (' . (int)$stats['waiting'] . ')' : '' ?></a>
                    </li>
                    <li <?= $current === 'approved' ? ' class="current"' : '' ?>>
                        <a href="<?php $options->adminUrl($panelUrl('approved', 1)); ?>">已通过<?= $stats['approved'] > 0 ? ' (' . (int)$stats['approved'] . ')' : '' ?></a>
                    </li>
                    <li <?= $current === 'all' ? ' class="current"' : '' ?>>
                        <a href="<?php $options->adminUrl($panelUrl('all', 1)); ?>">全部</a>
                    </li>
                    <li>
                        <a href="<?php $options->adminUrl('options-theme.php'); ?>">想法设置（主题设置）</a>
                    </li>
                </ul>

                <style>
                    .qtw-list { margin: 0 0 16px; }
                    .qtw-card {
                        border: 1px solid #dcdcdc;
                        border-radius: 8px;
                        padding: 14px 16px;
                        margin: 0 0 12px;
                        background: #fff;
                    }
                    .qtw-card.is-waiting { border-left: 3px solid #f0ad4e; }
                    .qtw-card.is-approved { border-left: 3px solid #5cb85c; }
                    .qtw-quote {
                        margin: 0 0 10px;
                        padding: 6px 10px;
                        border-left: 2px solid #bbb;
                        background: #f7f7f7;
                        color: #666;
                        font-size: 13px;
                        line-height: 1.7;
                    }
                    .qtw-content { font-size: 14px; line-height: 1.8; color: #333; }
                    .qtw-content p { margin: 0 0 6px; }
                    .qtw-content img { max-width: 100%; height: auto; }
                    /* 表情：固定高度占位（lazy 加载前不塌陷、加载后不跳），与文字底部对齐 */
                    .qtw-content img.comment-sticker {
                        display: inline-block;
                        width: auto;
                        height: 3.2em;
                        max-height: none;
                        margin: 0 .1em;
                        vertical-align: bottom;
                        object-fit: contain;
                    }
                    .qtw-meta {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 10px;
                        align-items: center;
                        margin-top: 10px;
                        color: #999;
                        font-size: 12px;
                    }
                    .qtw-meta strong { color: #555; font-weight: 600; }
                    .qtw-status { font-weight: 600; }
                    .qtw-status.is-waiting { color: #f0ad4e; }
                    .qtw-status.is-approved { color: #5cb85c; }
                    .qtw-actions { display: flex; gap: 8px; margin-left: auto; }
                    .qtw-actions form { display: inline; margin: 0; }
                    .qtw-actions button {
                        padding: 4px 12px;
                        border: 1px solid #ccc;
                        border-radius: 4px;
                        background: #fff;
                        color: #444;
                        cursor: pointer;
                        font-size: 12px;
                    }
                    .qtw-actions button:hover { background: #f5f5f5; }
                    .qtw-actions button.is-primary { border-color: #467bcb; background: #467bcb; color: #fff; }
                    .qtw-actions button.is-primary:hover { background: #3a69b5; }
                    .qtw-actions button.is-danger { border-color: #d43f3a; color: #d43f3a; }
                    .qtw-actions button.is-danger:hover { background: #fdf0ef; }
                    .qtw-empty { padding: 30px 0; text-align: center; color: #999; }
                    .qtw-pager { display: flex; gap: 8px; margin: 8px 0 20px; }
                    .qtw-pager a, .qtw-pager span {
                        padding: 4px 12px;
                        border: 1px solid #ddd;
                        border-radius: 4px;
                        color: #666;
                        text-decoration: none;
                    }
                    .qtw-pager span { color: #bbb; }
                </style>

                <div class="qtw-list">
                    <?php if (empty($rows)) : ?>
                        <p class="qtw-empty">这里还没有想法。</p>
                    <?php else : foreach ($rows as $row) :
                        $view = $console->statusView((string)$row['status']);
                    ?>
                        <div class="qtw-card is-<?= $escape($view['class']) ?>">
                            <p class="qtw-quote">“<?= $escape($console->excerpt($row['quote'], 80)) ?>”</p>
                            <div class="qtw-content"><?= $row['html'] ?></div>
                            <div class="qtw-meta">
                                <span class="qtw-status is-<?= $escape($view['class']) ?>"><?= $view['icon'] ?> <?= $escape($console->statusLabel((string)$row['status'])) ?></span>
                                <span><strong><?= $escape($row['author']) ?></strong><?= $row['mail'] !== '' ? ' · ' . $escape($row['mail']) : '' ?><?= (int)$row['user_id'] > 0 ? ' · 作者' : '' ?></span>
                                <?php if ($row['postTitle'] !== '') : ?><span>《<?= $escape($row['postTitle']) ?>》</span><?php endif; ?>
                                <span><?= $escape($row['createdText']) ?></span>
                                <span title="<?= $escape($row['ip']) ?>"><?= $escape($console->excerpt($row['ip'], 24)) ?></span>
                                <span class="qtw-actions">
                                    <?php if ($row['status'] === 'waiting') : ?>
                                    <form method="post" action="<?= $escape($actionUrl('thoughts-approve', ['id' => (int)$row['id']])) ?>">
                                        <button type="submit" class="is-primary">通过</button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="post" action="<?= $escape($actionUrl('thoughts-delete', ['id' => (int)$row['id']])) ?>" onsubmit="return confirm('确定删除这条想法吗？');">
                                        <button type="submit" class="is-danger">删除</button>
                                    </form>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <?php if ($page > 1 || $hasMore) : ?>
                <div class="qtw-pager">
                    <?php if ($page > 1) : ?>
                        <a href="<?php $options->adminUrl($panelUrl($current, $page - 1)); ?>">上一页</a>
                    <?php else : ?>
                        <span>上一页</span>
                    <?php endif; ?>
                    <span>第 <?= (int)$page ?> 页</span>
                    <?php if ($hasMore) : ?>
                        <a href="<?php $options->adminUrl($panelUrl($current, $page + 1)); ?>">下一页</a>
                    <?php else : ?>
                        <span>下一页</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
require_once 'copyright.php';
require_once 'common-js.php';
require_once 'footer.php';
?>
