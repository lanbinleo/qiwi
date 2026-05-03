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

$homeJikeData = isset($this->homeJikeData) ? $this->homeJikeData : null;
$homeJikeTimeMode = isset($this->homeJikeTimeMode) ? $this->homeJikeTimeMode : 'absolute';
$homeJikePosition = isset($this->homeJikePosition)
    ? $this->homeJikePosition
    : (isset($this->options->jikePosition) ? $this->options->jikePosition : 'sidebar');
if ((string) $homeJikePosition === '1') {
    $homeJikePosition = 'sidebar';
} elseif ((string) $homeJikePosition === '0') {
    $homeJikePosition = 'off';
}
if (in_array($homeJikePosition, ['top', 'inline'], true)) {
    $homeJikePosition = 'sidebar';
}
$homeJikeTimeMode = in_array($homeJikeTimeMode, ['absolute', 'relative'], true) ? $homeJikeTimeMode : 'absolute';
if ($homeJikePosition === 'sidebar' && empty($homeJikeData) && function_exists('qiwiGetHomepageJikeData')) {
    $sidebarMomentCount = function_exists('qiwiGetPositiveIntOption') ? qiwiGetPositiveIntOption($this, 'sidebarMomentCount', 4, 1, 8) : 4;
    $homeJikeData = qiwiGetHomepageJikeData($sidebarMomentCount);
}
$showSidebarJike = $homeJikePosition === 'sidebar'
    && !empty($homeJikeData['items'])
    && !empty($homeJikeData['permalink']);
$sidebarBlocks = isset($this->options->sidebarBlock)
    ? (array) $this->options->sidebarBlock
    : ['ShowRecentPosts', 'ShowCategory', 'ShowArchive', 'ShowTags'];
$sidebarThreadItems = function_exists('qiwiGetThreadCategories') ? qiwiGetThreadCategories() : [];
$sidebarSocialLinks = function_exists('qiwiGetSidebarSocialLinks') ? qiwiGetSidebarSocialLinks($this) : [];
$sidebarProfileAvatar = function_exists('qiwiGetSidebarProfileAvatar') ? qiwiGetSidebarProfileAvatar($this) : 'https://gravatar.loli.net/avatar/default?s=160&d=mp';
$sidebarProfileText = function_exists('qiwiGetSidebarProfileText') ? qiwiGetSidebarProfileText($this) : '';
$sidebarIconSvg = function($kind) {
    $icons = [
        'github' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-3.16 19.49c.5.09.68-.22.68-.48v-1.7c-2.78.6-3.37-1.18-3.37-1.18-.45-1.15-1.11-1.46-1.11-1.46-.91-.62.07-.61.07-.61 1 .07 1.53 1.03 1.53 1.03.9 1.52 2.34 1.08 2.91.83.09-.65.35-1.08.63-1.33-2.22-.25-4.55-1.11-4.55-4.94 0-1.09.39-1.98 1.03-2.68-.1-.25-.45-1.27.1-2.65 0 0 .84-.27 2.75 1.02A9.56 9.56 0 0 1 12 6.99c.85 0 1.7.11 2.5.34 1.9-1.29 2.74-1.02 2.74-1.02.55 1.38.2 2.4.1 2.65.64.7 1.03 1.59 1.03 2.68 0 3.84-2.34 4.69-4.57 4.93.36.31.68.92.68 1.86v2.76c0 .27.18.58.69.48A10 10 0 0 0 12 2z"/></svg>',
        'bilibili' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.3 4.2 10 6h4l1.7-1.8a1 1 0 1 1 1.46 1.36L15.8 7h1.7A3.5 3.5 0 0 1 21 10.5v5A3.5 3.5 0 0 1 17.5 19h-11A3.5 3.5 0 0 1 3 15.5v-5A3.5 3.5 0 0 1 6.5 7h1.7L6.84 5.56A1 1 0 1 1 8.3 4.2zM6.5 9A1.5 1.5 0 0 0 5 10.5v5A1.5 1.5 0 0 0 6.5 17h11a1.5 1.5 0 0 0 1.5-1.5v-5A1.5 1.5 0 0 0 17.5 9h-11zm2 2.25a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0v-1a1 1 0 0 1 1-1zm7 0a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0v-1a1 1 0 0 1 1-1z"/></svg>',
        'email' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 6h15A2.5 2.5 0 0 1 22 8.5v7A2.5 2.5 0 0 1 19.5 18h-15A2.5 2.5 0 0 1 2 15.5v-7A2.5 2.5 0 0 1 4.5 6zm0 2a.5.5 0 0 0-.5.5v.28l8 4.45 8-4.45V8.5a.5.5 0 0 0-.5-.5h-15zM20 11.07l-7.51 4.17a1 1 0 0 1-.98 0L4 11.07v4.43a.5.5 0 0 0 .5.5h15a.5.5 0 0 0 .5-.5v-4.43z"/></svg>',
        'rss' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 17.5A1.5 1.5 0 1 1 5 20a1.5 1.5 0 0 1 0-3zm-1-7A9.5 9.5 0 0 1 13.5 20h-2A7.5 7.5 0 0 0 4 12.5v-2zm0-6A15.5 15.5 0 0 1 19.5 20h-2A13.5 13.5 0 0 0 4 6.5v-2z"/></svg>',
    ];

    return isset($icons[$kind]) ? $icons[$kind] : '';
};
?>

<div class="sidebar-sticky">
<section class="sidebar-profile-card" aria-label="站点作者">
    <img class="sidebar-profile-avatar" src="<?php echo htmlspecialchars($sidebarProfileAvatar, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php $this->author->screenName(); ?>" loading="lazy" decoding="async" onerror="this.src='https://gravatar.loli.net/avatar/default?s=160&d=mp'">
    <div class="sidebar-profile-body">
        <h2 class="sidebar-profile-name"><?php $this->author->screenName(); ?></h2>
        <?php if ($sidebarProfileText !== ''): ?>
            <p class="sidebar-profile-quote"><?php echo htmlspecialchars($sidebarProfileText, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if (!empty($sidebarSocialLinks)): ?>
        <div class="sidebar-profile-links" aria-label="社交链接">
            <?php foreach ($sidebarSocialLinks as $link): ?>
                <a href="<?php echo htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($link['title'], ENT_QUOTES, 'UTF-8'); ?>"<?php if ($link['external']): ?> target="_blank" rel="noopener noreferrer"<?php endif; ?>>
                    <?php $inlineIcon = !empty($link['kind']) ? $sidebarIconSvg($link['kind']) : ''; ?>
                    <?php if ($inlineIcon !== ''): ?>
                        <?php echo $inlineIcon; ?>
                    <?php elseif (!empty($link['icon'])): ?>
                        <i class="<?php echo htmlspecialchars($link['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                    <?php else: ?>
                        <span><?php echo htmlspecialchars(mb_substr($link['title'], 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <span class="sr-only"><?php echo htmlspecialchars($link['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- 最新文章 -->
<?php if (in_array('ShowRecentPosts', $sidebarBlocks)): ?>
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
<?php if (in_array('ShowCategory', $sidebarBlocks)): ?>
<div class="sidebar-section">
    <h3 class="sidebar-title">
        <a href="<?php echo htmlspecialchars($categoriesPageUrl, ENT_QUOTES, 'UTF-8'); ?>">分类</a>
    </h3>
    <ul class="sidebar-list">
        <?php \Widget\Metas\Category\Rows::alloc()->to($category); ?>
        <?php while ($category->next()): ?>
            <?php if ((int) $category->count <= 0) continue; ?>
            <?php if (function_exists('qiwiIsThreadSlug') && qiwiIsThreadSlug($category->slug)) continue; ?>
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

<?php if (!empty($sidebarThreadItems)): ?>
<div class="sidebar-section">
    <h3 class="sidebar-title">专题</h3>
    <ul class="sidebar-list sidebar-thread-list">
        <?php foreach ($sidebarThreadItems as $thread): ?>
            <li>
                <a href="<?php echo htmlspecialchars($thread['permalink'], ENT_QUOTES, 'UTF-8'); ?>" class="cat cat-plain sidebar-thread-link">
                    <span class="cat-name"><?php echo htmlspecialchars($thread['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="count"><?php echo (int) $thread['count']; ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- 时间归档 -->
<?php if (in_array('ShowArchive', $sidebarBlocks)): ?>
<div class="sidebar-section">
    <h3 class="sidebar-title">
        <a href="<?php echo htmlspecialchars($archivesPageUrl, ENT_QUOTES, 'UTF-8'); ?>">归档</a>
    </h3>
    <ul class="sidebar-list">
        <?php \Widget\Contents\Post\Date::alloc('type=year&format=Y年')
            ->parse('<li><a href="{permalink}" class="cat cat-plain" title="查看 {date} 的文章"><span class="cat-name">{date}</span> <span class="count">{count}</span></a></li>'); ?>
    </ul>
</div>
<?php endif; ?>

<!-- 标签云 -->
<?php if (in_array('ShowTags', $sidebarBlocks)): ?>
<?php \Widget\Metas\Tag\Cloud::alloc()->to($tags); ?>
<?php if($tags->have()): ?>
<div class="sidebar-section">
    <h3 class="sidebar-title">
        <a href="<?php echo htmlspecialchars($tagsPageUrl, ENT_QUOTES, 'UTF-8'); ?>">标签</a>
    </h3>
    <div class="article-tags">
        <?php $visibleTagLimit = 12; $visibleTags = []; $tagTotal = 0; ?>
        <?php while ($tags->next()): ?>
            <?php if ((int) $tags->count <= 0) continue; ?>
            <?php $tagTotal++; ?>
            <?php if (count($visibleTags) < $visibleTagLimit): ?>
                <?php ob_start(); $tags->permalink(); $tagPermalink = trim(ob_get_clean()); ?>
                <?php $visibleTags[] = ['name' => $tags->name, 'url' => $tagPermalink]; ?>
            <?php endif; ?>
        <?php endwhile; ?>
        <?php $visibleTagTotal = count($visibleTags); ?>
        <?php foreach ($visibleTags as $tagIndex => $tag): ?>
            <?php $tagColor = function_exists('qiwiGetSequentialTermColor') ? qiwiGetSequentialTermColor($tagIndex, $visibleTagTotal) : 'cyan'; ?>
            <a href="<?php echo htmlspecialchars($tag['url'], ENT_QUOTES, 'UTF-8'); ?>" class="tag tag-plain qiwi-term qiwi-term-<?php echo htmlspecialchars($tagColor, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($tag['name'], ENT_QUOTES, 'UTF-8'); ?></a>
        <?php endforeach; ?>
    </div>
    <?php if ($tagTotal > 0): ?>
        <a class="sidebar-more-link" href="<?php echo htmlspecialchars($tagsPageUrl, ENT_QUOTES, 'UTF-8'); ?>">查看全部 <?php echo (int) $tagTotal; ?> 个标签</a>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if ($showSidebarJike): ?>
<div class="sidebar-section sidebar-jike-section">
    <h3 class="sidebar-title">
        <a href="<?php echo htmlspecialchars($homeJikeData['permalink'], ENT_QUOTES, 'UTF-8'); ?>">闲言碎语</a>
    </h3>
    <ol class="sidebar-jike-list">
        <?php foreach ($homeJikeData['items'] as $item): ?>
            <?php $timeLabel = $homeJikeTimeMode === 'relative' ? $item['relative_date_label'] : $item['date_label']; ?>
            <li class="sidebar-jike-item">
                <a href="<?php echo htmlspecialchars($homeJikeData['permalink'], ENT_QUOTES, 'UTF-8'); ?>">
                    <time datetime="<?php echo htmlspecialchars($item['datetime'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></time>
                    <span><?php echo htmlspecialchars($item['excerpt'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ol>
    <a class="sidebar-more-link" href="<?php echo htmlspecialchars($homeJikeData['permalink'], ENT_QUOTES, 'UTF-8'); ?>">查看更多</a>
</div>
<?php endif; ?>
</div><!-- /.sidebar-sticky -->
