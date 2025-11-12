<?php
/**
 * 时光机页面模板
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');

// 获取数据
$pageId = $this->cid;
$authorUid = $this->author->uid;
$pageSize = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// 获取数据库
$db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
$prefix = $db->getPrefix();

// 查询说说（作者的评论）
$select = $db->select()->from($prefix.'comments')
    ->where('cid = ?', $pageId)
    ->where('status = ?', 'approved')
    ->where('authorId = ?', $authorUid)
    ->order('created', $db::SORT_DESC)
    ->page($currentPage, $pageSize);

$comments = $db->fetchAll($select);

// 获取总数
$totalResult = $db->fetchRow($db->select('COUNT(coid) AS total')
    ->from($prefix.'comments')
    ->where('cid = ?', $pageId)
    ->where('status = ?', 'approved')
    ->where('authorId = ?', $authorUid));

$total = $totalResult ? $totalResult['total'] : 0;
$totalPages = ceil($total / $pageSize);

// Markdown渲染
function renderMarkdown($text) {
    if (empty($text)) return '';
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/!\[([^\]]*)\]\(([^\)]+)\)/', '<img src="$2" alt="$1" class="moment-image" loading="lazy">', $text);
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/`([^`]+?)`/', '<code>$1</code>', $text);
    $text = preg_replace('/\[([^\]]*)\]\(([^\)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $text);
    return nl2br($text);
}
?>

<div class="timemachine-page">
    <!-- 左侧留白 -->
    <div class="layout-spacer-left"></div>

    <!-- 主内容 -->
    <div class="timemachine-main">
        <!-- 页面头部 -->
        <header class="timemachine-header">
            <h1 class="page-title"><?php $this->title(); ?></h1>
            <?php if ($this->content()): ?>
                <div class="page-intro"><?php $this->content(); ?></div>
            <?php endif; ?>
            <div class="page-stats">
                <span class="stat-item">共 <?php echo $total; ?> 条记录</span>
            </div>
        </header>

        <!-- 说说列表 -->
        <?php if ($total > 0): ?>
        <div class="moments-list">
            <?php foreach ($comments as $comment): ?>
            <article class="moment-item">
                <div class="moment-avatar">
                    <img src="<?php echo $this->options->aboutAvatar ?: 'https://gravatar.loli.net/avatar/default?s=96&d=mp'; ?>"
                         alt="avatar">
                </div>
                <div class="moment-content">
                    <div class="moment-header">
                        <span class="moment-author"><?php $this->author->screenName(); ?></span>
                        <time class="moment-time"><?php echo date('Y-m-d H:i', $comment['created']); ?></time>
                    </div>
                    <div class="moment-text">
                        <?php echo renderMarkdown($comment['text']); ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <!-- 分页 -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination-wrapper">
            <nav class="page-navigator">
                <?php if ($currentPage > 1): ?>
                    <a href="<?php echo $this->permalink . '?page=' . ($currentPage - 1); ?>">上一页</a>
                <?php endif; ?>

                <span class="current">第 <?php echo $currentPage; ?> / <?php echo $totalPages; ?> 页</span>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="<?php echo $this->permalink . '?page=' . ($currentPage + 1); ?>">下一页</a>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty-moments">
            <p>还没有任何记录，快来发布第一条吧！</p>
        </div>
        <?php endif; ?>

        <!-- 发布表单 -->
        <?php if ($this->user->hasLogin() && ($this->user->uid === $authorUid || $this->user->group === 'administrator')): ?>
        <div class="moment-publisher">
            <h3 class="publisher-title">发布新的记录</h3>
            <?php if ($this->allow('comment')): ?>
            <form method="post" action="<?php $this->commentUrl(); ?>" class="publisher-form">
                <textarea name="text" placeholder="想说些什么？支持 Markdown 语法..." rows="4" required></textarea>

                <input type="hidden" name="author" value="<?php echo htmlspecialchars($this->author->screenName); ?>">
                <input type="hidden" name="mail" value="<?php echo htmlspecialchars($this->author->mail); ?>">
                <input type="hidden" name="url" value="<?php echo htmlspecialchars($this->author->url); ?>">

                <?php
                $referer = $this->request->getReferer() ?? $this->request->getRequestUrl();
                $token = method_exists($this, 'security') ?
                    $this->security->getToken($referer) :
                    $this->widget('Widget_Security')->getToken($referer);
                echo '<input type="hidden" name="_" value="' . $token . '">';
                ?>

                <button type="submit" class="submit-button">发布</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 右侧留白 -->
    <div class="layout-spacer-right"></div>
</div>

<?php $this->need('footer.php'); ?>
