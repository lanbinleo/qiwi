<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$archivesPageUrl = function_exists('qiwiGetCustomPageUrl') ? qiwiGetCustomPageUrl($this, ['page-archives.php', 'page-archives']) : '';
$categoriesPageUrl = function_exists('qiwiGetCustomPageUrl') ? qiwiGetCustomPageUrl($this, ['page-categories.php', 'page-categories']) : '';
$tagsPageUrl = function_exists('qiwiGetCustomPageUrl') ? qiwiGetCustomPageUrl($this, ['page-tags.php', 'page-tags']) : '';

if ($archivesPageUrl === '' && function_exists('qiwiGetPageUrlBySlug')) {
    $archivesPageUrl = qiwiGetPageUrlBySlug($this, ['archives', 'archive']);
}

if ($categoriesPageUrl === '' && function_exists('qiwiGetPageUrlBySlug')) {
    $categoriesPageUrl = qiwiGetPageUrlBySlug($this, ['category', 'categories']);
}

if ($tagsPageUrl === '' && function_exists('qiwiGetPageUrlBySlug')) {
    $tagsPageUrl = qiwiGetPageUrlBySlug($this, ['tags', 'tag']);
}
?>

<!-- 侧边栏标题 -->
<div class="sidebar-header">
    <h1 class="site-title"><?php $this->author->screenName(); ?></h1>
    <?php if ($this->options->enableHitokoto == 1 && $this->options->aboutBio): ?>
        <!-- 一言打字机容器 -->
        <div class="site-motto-wrapper"
             data-enable-hitokoto="true"
             data-bio="<?php echo htmlspecialchars($this->options->aboutBio); ?>">
            <p class="site-motto">
                <span class="motto-text"></span>
                <span class="typing-cursor">|</span>
            </p>
        </div>
    <?php elseif ($this->options->aboutBio): ?>
        <!-- 普通显示模式 -->
        <p class="site-motto"><?php echo $this->options->aboutBio; ?></p>
    <?php endif; ?>
</div>

<div class="sidebar-sticky">
<!-- 最新文章 -->
<?php if (!empty($this->options->sidebarBlock) && in_array('ShowRecentPosts', $this->options->sidebarBlock)): ?>
<div class="sidebar-section">
    <h3 class="sidebar-title">最新文章</h3>
    <ul class="sidebar-list recent-post-list">
        <?php \Widget\Contents\Post\Recent::alloc()->to($recent); ?>
        <?php while ($recent->next()): ?>
            <li><a href="<?php $recent->permalink(); ?>" title="<?php $recent->title(); ?>"><?php $recent->title(); ?></a></li>
        <?php endwhile; ?>
    </ul>
</div>
<?php endif; ?>

<!-- 分类 -->
<?php if (!empty($this->options->sidebarBlock) && in_array('ShowCategory', $this->options->sidebarBlock)): ?>
<div class="sidebar-section">
    <h3 class="sidebar-title">
        <a href="<?php echo htmlspecialchars($categoriesPageUrl, ENT_QUOTES, 'UTF-8'); ?>">分类</a>
    </h3>
    <ul class="sidebar-list">
        <?php \Widget\Metas\Category\Rows::alloc()->to($category); ?>
        <?php while ($category->next()): ?>
            <?php if ((int) $category->count <= 0) continue; ?>
            <li>
                <a href="<?php $category->permalink(); ?>" class="cat cat-plain">
                    <span class="cat-name"><?php $category->name(); ?></span>
                    <span class="count"><?php $category->count(); ?></span>
                </a>
            </li>
        <?php endwhile; ?>
    </ul>
</div>
<?php endif; ?>

<!-- 时间归档 -->
<div class="sidebar-section">
    <h3 class="sidebar-title">
        <a href="<?php echo htmlspecialchars($archivesPageUrl, ENT_QUOTES, 'UTF-8'); ?>">归档</a>
    </h3>
    <ul class="sidebar-list">
        <?php \Widget\Contents\Post\Date::alloc('type=year&format=Y年')
            ->parse('<li><a href="{permalink}" class="cat cat-plain" title="查看 {date} 的文章"><span class="cat-name">{date}</span> <span class="count">{count}</span></a></li>'); ?>
    </ul>
</div>

<!-- 标签云 -->
<?php \Widget\Metas\Tag\Cloud::alloc()->to($tags); ?>
<?php if($tags->have()): ?>
<div class="sidebar-section">
    <h3 class="sidebar-title">
        <a href="<?php echo htmlspecialchars($tagsPageUrl, ENT_QUOTES, 'UTF-8'); ?>">标签</a>
    </h3>
    <div class="article-tags">
        <?php $tagIndex = 0; $tagTotal = 0; $visibleTagLimit = 12; ?>
        <?php while ($tags->next()): ?>
            <?php if ((int) $tags->count <= 0) continue; ?>
            <?php $tagTotal++; ?>
            <?php if ($tagIndex < $visibleTagLimit): ?>
                <a href="<?php $tags->permalink(); ?>" class="tag"><?php $tags->name(); ?></a>
                <?php $tagIndex++; ?>
            <?php endif; ?>
        <?php endwhile; ?>
    </div>
    <?php if ($tagTotal > 0): ?>
        <a class="sidebar-more-link" href="<?php echo htmlspecialchars($tagsPageUrl, ENT_QUOTES, 'UTF-8'); ?>">查看全部 <?php echo (int) $tagTotal; ?> 个标签</a>
    <?php endif; ?>
</div>
<?php endif; ?>
</div><!-- /.sidebar-sticky -->
