<?php
/**
 * 分类页面
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');

$categoryItems = [];
\Widget\Metas\Category\Rows::alloc()->to($categories);
while ($categories->next()) {
    if ((int) $categories->count <= 0) {
        continue;
    }

    ob_start();
    $categories->permalink();
    $permalink = trim(ob_get_clean());

    ob_start();
    $categories->name();
    $name = trim(ob_get_clean());

    $description = '';
    if (!empty($categories->description)) {
        $description = trim(strip_tags((string) $categories->description));
    }

    $categoryItems[] = [
        'name' => $name,
        'permalink' => $permalink,
        'count' => (int) $categories->count,
        'description' => $description,
    ];
}

$totalCategories = count($categoryItems);
$totalCategorizedPosts = array_sum(array_column($categoryItems, 'count'));
$pageContent = qiwiGetContent($this);
?>

<div class="main-layout">
    <div class="layout-spacer-left"></div>

    <div class="main-content">
        <header class="archive-header">
            <h1 class="archive-title"><?php $this->title(); ?></h1>
            <div class="archive-stats">
                <span class="stat-item">共 <?php echo (int) $totalCategories; ?> 个分类</span>
                <span class="stat-item">收纳 <?php echo (int) $totalCategorizedPosts; ?> 篇文章</span>
            </div>
        </header>

        <?php if (qiwiHasRenderedContent($pageContent)): ?>
            <div class="archive-description"><?php echo $pageContent; ?></div>
        <?php endif; ?>

        <?php if (!empty($categoryItems)): ?>
        <div class="archives-timeline taxonomy-timeline">
            <div class="archive-year-section">
                <h2 class="year-title">全部分类</h2>
                <ul class="archive-post-list taxonomy-list">
                    <?php foreach ($categoryItems as $category): ?>
                    <li class="archive-post-item taxonomy-list-item">
                        <a href="<?php echo htmlspecialchars($category['permalink'], ENT_QUOTES, 'UTF-8'); ?>" class="post-title-link">
                            <?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <?php if ($category['description'] !== ''): ?>
                            <span class="taxonomy-description"><?php echo htmlspecialchars($category['description'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <span class="post-wordcount"><?php echo (int) $category['count']; ?> 篇</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-posts">
            <h2>暂无分类</h2>
            <p>还没有可以展示的分类。</p>
        </div>
        <?php endif; ?>
    </div>

    <aside class="sidebar">
        <?php $this->need('sidebar.php'); ?>
    </aside>

    <div class="layout-spacer-right"></div>
</div>

<?php $this->need('footer.php'); ?>
