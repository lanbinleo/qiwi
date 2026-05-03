<?php
/**
 * 分类页面
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');

$categoryItems = [];
$threadItems = [];
\Widget\Metas\Category\Rows::alloc()->to($categories);
while ($categories->next()) {
    $isThread = function_exists('qiwiIsThreadSlug') && qiwiIsThreadSlug($categories->slug);

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

    $threadData = null;
    $displayCount = (int) $categories->count;
    if ($isThread && function_exists('qiwiGetThreadData')) {
        $threadData = qiwiGetThreadData((int) $categories->mid, (string) $categories->description);
        $displayCount = function_exists('qiwiThreadConfiguredPostCount')
            ? qiwiThreadConfiguredPostCount($threadData, (int) $categories->count)
            : (int) $categories->count;
    }

    if ($displayCount <= 0 && (!$isThread || empty($threadData) || (trim((string) $threadData['summary']) === '' && trim((string) $threadData['subtitle']) === ''))) {
        continue;
    }

    $item = [
        'mid' => (int) $categories->mid,
        'name' => $name,
        'slug' => (string) $categories->slug,
        'permalink' => $permalink,
        'count' => $displayCount,
        'description' => $description,
        'threadData' => $threadData,
    ];

    if ($isThread) {
        $threadItems[] = $item;
    } else {
        $categoryItems[] = $item;
    }
}

$totalCategories = count($categoryItems);
$totalThreads = count($threadItems);
$totalCategorizedPosts = array_sum(array_column($categoryItems, 'count'));
$totalThreadPosts = array_sum(array_column($threadItems, 'count'));
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
                <?php if ($totalThreads > 0): ?>
                    <span class="stat-item">另有 <?php echo (int) $totalThreads; ?> 个专题</span>
                <?php endif; ?>
            </div>
        </header>

        <?php if (qiwiHasRenderedContent($pageContent)): ?>
            <div class="archive-description"><?php echo $pageContent; ?></div>
        <?php endif; ?>

        <?php if (!empty($categoryItems)): ?>
        <div class="archives-timeline taxonomy-timeline">
            <div class="archive-year-section">
                <h2 class="year-title">写作分类</h2>
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

        <?php if (!empty($threadItems)): ?>
        <div class="archives-timeline taxonomy-timeline thread-taxonomy-timeline">
            <div class="archive-year-section">
                <h2 class="year-title">专题文集</h2>
                <ul class="archive-post-list taxonomy-list">
                    <?php foreach ($threadItems as $thread): ?>
                    <?php
                        $threadData = is_array($thread['threadData']) ? $thread['threadData'] : (function_exists('qiwiGetThreadData') ? qiwiGetThreadData($thread['mid'], $thread['description']) : qiwiParseThreadData($thread['description']));
                        $threadSubtitle = trim((string) $threadData['subtitle']);
                        $threadField = trim((string) $threadData['field']);
                        $threadStatus = function_exists('qiwiThreadStatusLabel') ? qiwiThreadStatusLabel($threadData['status']) : '连载中';
                    ?>
                    <li class="archive-post-item taxonomy-list-item thread-taxonomy-item">
                        <a href="<?php echo htmlspecialchars($thread['permalink'], ENT_QUOTES, 'UTF-8'); ?>" class="post-title-link">
                            <?php echo htmlspecialchars($thread['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <span class="thread-taxonomy-meta">
                            <span><?php echo htmlspecialchars($threadStatus, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($threadField !== ''): ?><span><?php echo htmlspecialchars($threadField, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                            <span><?php echo (int) $thread['count']; ?> 篇</span>
                        </span>
                        <?php if ($threadSubtitle !== ''): ?>
                            <span class="taxonomy-description"><?php echo htmlspecialchars(qiwiExcerptText($threadSubtitle, 56), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <?php if (trim((string) $threadData['summary']) !== ''): ?>
                            <span class="taxonomy-description"><?php echo htmlspecialchars(qiwiExcerptText($threadData['summary'], 56), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <p class="archive-stats thread-taxonomy-stats">专题共收纳 <?php echo (int) $totalThreadPosts; ?> 篇文章。</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <aside class="sidebar">
        <?php $this->need('sidebar.php'); ?>
    </aside>

    <div class="layout-spacer-right"></div>
</div>

<?php $this->need('footer.php'); ?>
