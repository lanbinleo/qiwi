<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$qiwiNavItems = function_exists('qiwiGetNavigationItems') ? qiwiGetNavigationItems($this) : [];
$qiwiTemplate = isset($this->template) ? (string) $this->template : '';
$qiwiUseFontAwesome = (function_exists('qiwiNavigationUsesFontAwesome') && qiwiNavigationUsesFontAwesome($qiwiNavItems))
    || (function_exists('qiwiSidebarSocialUsesFontAwesome') && qiwiSidebarSocialUsesFontAwesome($this))
    || in_array($qiwiTemplate, ['page-timemachine.php', 'page-timemachine'], true);
$qiwiUseLatex = function_exists('qiwiShouldRenderLatex') && qiwiShouldRenderLatex($this);
$qiwiCapture = function ($callback) {
    ob_start();
    $callback();
    return trim(ob_get_clean());
};
$qiwiNormalizeLang = function ($lang) {
    $lang = trim((string) $lang);
    if ($lang === '') {
        return 'zh-CN';
    }

    $parts = preg_split('/[-_]/', $lang);
    $primary = strtolower($parts[0]);
    if (empty($parts[1])) {
        return $primary;
    }

    $region = $parts[1];
    if (strlen($region) === 2) {
        $region = strtoupper($region);
    } else {
        $region = ucfirst(strtolower($region));
    }

    return $primary . '-' . $region;
};
$qiwiToAbsoluteUrl = function ($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $url)) {
        return $url;
    }

    if (strpos($url, '//') === 0) {
        return 'https:' . $url;
    }

    $siteUrl = rtrim((string) $this->options->siteUrl, '/');
    return $siteUrl . '/' . ltrim($url, '/');
};
$qiwiCanonicalFromPath = function ($url) {
    $siteUrl = rtrim((string) $this->options->siteUrl, '/');
    $path = parse_url((string) $url, PHP_URL_PATH);
    if ($path === null || $path === false || $path === '') {
        $path = '/';
    }

    return $siteUrl . '/' . ltrim($path, '/');
};
$qiwiLang = $qiwiNormalizeLang($qiwiCapture(function () { $this->options->lang(); }));
$qiwiSiteTitle = $qiwiCapture(function () { $this->options->title(); });
$qiwiTitlePrefix = $qiwiCapture(function () {
    $this->archiveTitle([
        'category' => _t('分类 %s 下的文章'),
        'search'   => _t('包含关键字 %s 的文章'),
        'tag'      => _t('标签 %s 下的文章'),
        'author'   => _t('%s 发布的文章')
    ], '', ' - ');
});
$qiwiSingleTitle = $this->is('single') ? $qiwiCapture(function () { $this->title(); }) : '';
$qiwiPageTitle = $qiwiTitlePrefix . $qiwiSiteTitle;
if ($this->is('single') && $qiwiSingleTitle !== '' && strpos($qiwiTitlePrefix, $qiwiSingleTitle) === false) {
    $qiwiPageTitle = $qiwiSingleTitle . ' - ' . $qiwiSiteTitle;
}

$qiwiDescription = '';
if ($this->is('single')) {
    $qiwiFieldExcerpt = function_exists('qiwiGetFieldValue') ? qiwiGetFieldValue($this, 'excerpt', '') : '';
    if (trim((string) $qiwiFieldExcerpt) !== '' && function_exists('qiwiExtractPlainText')) {
        $qiwiDescription = qiwiExtractPlainText($qiwiFieldExcerpt);
    } elseif (function_exists('qiwiExcerptText')) {
        $qiwiDescription = qiwiExcerptText($this->content, 150);
    } else {
        $qiwiDescription = $qiwiCapture(function () { $this->excerpt(150, ''); });
    }
} else {
    $qiwiDescription = $qiwiCapture(function () { $this->options->description(); });
}
$qiwiDescription = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $qiwiDescription)));

$qiwiKeywords = $this->is('single')
    ? $qiwiCapture(function () { $this->tags(',', false); })
    : $qiwiCapture(function () { $this->options->keywords(); });
$qiwiAuthor = $this->is('single')
    ? $qiwiCapture(function () { $this->author(); })
    : $qiwiSiteTitle;

$qiwiPermalink = $this->is('single') ? $qiwiCapture(function () { $this->permalink(); }) : '';
$qiwiRequestPath = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
$qiwiCanonicalUrl = $this->is('single') ? $qiwiCanonicalFromPath($qiwiPermalink) : $qiwiCanonicalFromPath($qiwiRequestPath);

$qiwiThemeUrl = rtrim($qiwiCapture(function () { $this->options->themeUrl(); }), '/');
$qiwiThumbnail = $this->is('single') && function_exists('qiwiGetFieldValue') ? qiwiGetFieldValue($this, 'thumbnail', '') : '';
$qiwiOgImage = $qiwiToAbsoluteUrl($qiwiThumbnail);
if ($qiwiOgImage === '') {
    $qiwiOgImage = $qiwiToAbsoluteUrl($this->options->logoUrl);
}
if ($qiwiOgImage === '') {
    $qiwiOgImage = $qiwiThemeUrl . '/apple-touch-icon.png';
}

$qiwiIsArticle = $this->is('single') && !$this->is('page');
$qiwiJsonLd = null;
if ($qiwiIsArticle) {
    $qiwiJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => $qiwiSingleTitle,
        'description' => $qiwiDescription,
        'url' => $qiwiCanonicalUrl,
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $qiwiCanonicalUrl,
        ],
        'author' => [
            '@type' => 'Person',
            'name' => $qiwiAuthor,
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => $qiwiSiteTitle,
            'logo' => [
                '@type' => 'ImageObject',
                'url' => $qiwiThemeUrl . '/apple-touch-icon.png',
            ],
        ],
        'datePublished' => date('c', (int) $this->created),
        'dateModified' => date('c', !empty($this->modified) ? (int) $this->modified : (int) $this->created),
        'image' => [$qiwiOgImage],
    ];
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($qiwiLang, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="renderer" content="webkit">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($qiwiPageTitle, ENT_QUOTES, 'UTF-8'); ?></title>

    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?php $this->options->themeUrl('assets/css/style.css'); ?>">
    <?php if ($qiwiUseFontAwesome): ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <?php endif; ?>

    <!-- Meta Tags -->
    <meta name="description" content="<?php echo htmlspecialchars($qiwiDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if (trim($qiwiKeywords) !== ''): ?>
    <meta name="keywords" content="<?php echo htmlspecialchars($qiwiKeywords, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <meta name="author" content="<?php echo htmlspecialchars($qiwiAuthor, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($qiwiCanonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($qiwiSiteTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($qiwiPageTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($qiwiDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="<?php echo $qiwiIsArticle ? 'article' : 'website'; ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($qiwiCanonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($qiwiOgImage, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($qiwiIsArticle): ?>
    <meta property="article:published_time" content="<?php echo htmlspecialchars(date('c', (int) $this->created), ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="article:modified_time" content="<?php echo htmlspecialchars(date('c', !empty($this->modified) ? (int) $this->modified : (int) $this->created), ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($qiwiPageTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($qiwiDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($qiwiOgImage, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Favicon -->
    <link rel="icon" href="<?php $this->options->themeUrl('favicon.ico'); ?>" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php $this->options->themeUrl('favicon-32x32.png'); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php $this->options->themeUrl('apple-touch-icon.png'); ?>">

    <!-- RSS -->
    <link rel="alternate" type="application/rss+xml" title="<?php $this->options->title(); ?>" href="<?php $this->options->feedUrl(); ?>">

    <?php if ($qiwiJsonLd !== null): ?>
    <script type="application/ld+json"><?php echo json_encode($qiwiJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    <?php endif; ?>

    <!-- EXTENSIONS: LATEX -->
    <?php if ($qiwiUseLatex): ?>
    <script defer type="text/javascript" src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css" />
    <script defer type="text/javascript" src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"></script>
    <?php endif; ?>

    <!-- Custom CSS -->
    <?php if ($this->options->customCSS): ?>
    <style type="text/css">
        <?php echo $this->options->customCSS; ?>
    </style>
    <?php endif; ?>

    <!-- Typecho Header -->
    <?php $this->header(); ?>
</head>
<body>

<?php
$qiwiNavbarAvatar = trim((string) $this->options->logoUrl);
$qiwiHomePostsUrl = rtrim((string) $this->options->siteUrl, '/') . '/#all-posts';
?>

<!-- 顶部导航栏 -->
<nav class="navbar" aria-label="主导航">
    <div class="navbar-inner">
        <div class="navbar-title">
            <a href="<?php $this->options->siteUrl(); ?>">
                <?php if ($qiwiNavbarAvatar !== ''): ?>
                    <img class="navbar-avatar" src="<?php echo htmlspecialchars($qiwiNavbarAvatar, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" decoding="async">
                <?php endif; ?>
                <?php $this->options->title(); ?>
            </a>
        </div>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-navigation-menu" aria-label="切换导航菜单">
            <span class="nav-toggle-bar"></span>
            <span class="nav-toggle-bar"></span>
            <span class="nav-toggle-bar"></span>
        </button>
        <ul class="nav-links" id="site-navigation-menu">
            <li><a href="<?php echo htmlspecialchars($qiwiHomePostsUrl, ENT_QUOTES, 'UTF-8'); ?>"<?php if ($this->is('index')): ?> class="current"<?php endif; ?>>首页</a></li>

            <?php $qiwiNavIndex = 0; ?>
            <?php foreach ($qiwiNavItems as $item): ?>
                <?php
                    $qiwiNavIndex++;
                    $children = isset($item['children']) ? (array) $item['children'] : [];
                    $hasChildren = !empty($children);
                    $isCurrent = !empty($item['slug']) && $this->is('page', $item['slug']);
                    foreach ($children as $child) {
                        if (!empty($child['slug']) && $this->is('page', $child['slug'])) {
                            $isCurrent = true;
                            break;
                        }
                    }
                ?>
                <li<?php if ($hasChildren): ?> class="nav-item-has-children"<?php endif; ?>>
                    <?php if ($hasChildren): ?>
                    <div class="nav-item-row">
                    <?php endif; ?>
                    <a href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>"<?php if ($isCurrent): ?> class="current"<?php endif; ?><?php if ($item['external']): ?> target="_blank" rel="noopener noreferrer"<?php endif; ?>><?php if (!empty($item['icon'])): ?><i class="<?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i> <?php endif; ?><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?><?php if ($hasChildren): ?> <span class="nav-caret" aria-hidden="true"></span><?php endif; ?></a>
                    <?php if ($hasChildren): ?>
                    <button class="nav-submenu-toggle" type="button" aria-expanded="false" aria-controls="nav-submenu-<?php echo (int) $qiwiNavIndex; ?>" aria-label="展开 <?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?> 子菜单">
                        <span class="nav-caret" aria-hidden="true"></span>
                    </button>
                    </div>
                    <ul class="nav-submenu" id="nav-submenu-<?php echo (int) $qiwiNavIndex; ?>">
                        <?php foreach ($children as $child): ?>
                            <li>
                                <a href="<?php echo htmlspecialchars($child['url'], ENT_QUOTES, 'UTF-8'); ?>"<?php if (!empty($child['slug']) && $this->is('page', $child['slug'])): ?> class="current"<?php endif; ?><?php if ($child['external']): ?> target="_blank" rel="noopener noreferrer"<?php endif; ?>><?php if (!empty($child['icon'])): ?><i class="<?php echo htmlspecialchars($child['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i> <?php endif; ?><?php echo htmlspecialchars($child['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>

            <?php if ($this->options->enableTravellings == 1): ?>
            <li>
                <a href="https://www.travellings.cn/go.html" target="_blank" rel="noopener noreferrer" title="开往-友链接力">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" width="14" height="14" fill="currentColor" style="vertical-align: -2px; margin-right: 4px;">
                        <path d="M0 96C0 43 43 0 96 0L288 0c53 0 96 43 96 96l0 256c0 40.1-24.6 74.5-59.5 88.8l53.9 63.7c8.6 10.1 7.3 25.3-2.8 33.8s-25.3 7.3-33.8-2.8l-74-87.5-151.3 0-74 87.5c-8.6 10.1-23.7 11.4-33.8 2.8s-11.4-23.7-2.8-33.8l53.9-63.7C24.6 426.5 0 392.1 0 352L0 96zm64 32l0 96c0 17.7 14.3 32 32 32l72 0 0-160-72 0c-17.7 0-32 14.3-32 32zM216 256l72 0c17.7 0 32-14.3 32-32l0-96c0-17.7-14.3-32-32-32l-72 0 0 160zM96 384a32 32 0 1 0 0-64 32 32 0 1 0 0 64zm224-32a32 32 0 1 0 -64 0 32 32 0 1 0 64 0z"/>
                    </svg>开往
                </a>
            </li>
            <?php endif; ?>

            <li><button class="theme-toggle" type="button" onclick="toggleTheme()" aria-label="切换主题">◐</button></li>
        </ul>
    </div>
</nav>
