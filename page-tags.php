<?php
/**
 * 标签云页面
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');

$tagItems = [];
\Widget\Metas\Tag\Cloud::alloc()->to($tags);
while ($tags->next()) {
    if ((int) $tags->count <= 0) {
        continue;
    }

    ob_start();
    $tags->permalink();
    $permalink = trim(ob_get_clean());

    ob_start();
    $tags->name();
    $name = trim(ob_get_clean());

    $tagItems[] = [
        'name' => $name,
        'permalink' => $permalink,
        'count' => (int) $tags->count,
    ];
}

$totalTags = count($tagItems);
$totalTaggedPosts = array_sum(array_column($tagItems, 'count'));
$pageContent = qiwiGetContent($this);
?>

<div class="main-layout">
    <div class="layout-spacer-left"></div>

    <div class="main-content">
        <header class="archive-header">
            <h1 class="archive-title"><?php $this->title(); ?></h1>
            <div class="archive-stats">
                <span class="stat-item">共 <?php echo (int) $totalTags; ?> 个标签</span>
                <span class="stat-item">关联 <?php echo (int) $totalTaggedPosts; ?> 篇文章</span>
            </div>
        </header>

        <?php if (qiwiHasRenderedContent($pageContent)): ?>
            <div class="archive-description"><?php echo $pageContent; ?></div>
        <?php endif; ?>

        <?php if (!empty($tagItems)): ?>
        <div class="taxonomy-cloud">
            <?php foreach ($tagItems as $tag): ?>
                <a href="<?php echo htmlspecialchars($tag['permalink'], ENT_QUOTES, 'UTF-8'); ?>" class="cat" title="<?php echo (int) $tag['count']; ?> 篇文章">
                    <span class="cat-name"><?php echo htmlspecialchars($tag['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="count"><?php echo (int) $tag['count']; ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-posts">
            <h2>暂无标签</h2>
            <p>还没有可以展示的标签。</p>
        </div>
        <?php endif; ?>
    </div>

    <aside class="sidebar">
        <?php $this->need('sidebar.php'); ?>
    </aside>

    <div class="layout-spacer-right"></div>
</div>

<?php $this->need('footer.php'); ?>
