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
$qiwiCleanMetaValue = function ($value) {
    if (is_array($value) || is_object($value)) {
        return '';
    }

    $value = trim((string) $value);
    if ($value === '' || $value === '0') {
        return '';
    }

    return $value;
};
$qiwiCleanMetaText = function ($value) use ($qiwiCleanMetaValue) {
    $value = $qiwiCleanMetaValue($value);
    if ($value === '') {
        return '';
    }

    return trim(preg_replace('/\s+/u', ' ', strip_tags($value)));
};
$qiwiToAbsoluteUrl = function ($url) use ($qiwiCleanMetaValue) {
    $url = $qiwiCleanMetaValue($url);
    if ($url === '') {
        return '';
    }

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
    if ($qiwiCleanMetaValue($qiwiFieldExcerpt) !== '' && function_exists('qiwiExtractPlainText')) {
        $qiwiDescription = qiwiExtractPlainText($qiwiFieldExcerpt);
    } elseif (function_exists('qiwiExcerptText')) {
        $qiwiDescription = qiwiExcerptText($this->content, 150);
    } else {
        $qiwiDescription = $qiwiCapture(function () { $this->excerpt(150, ''); });
    }
} else {
    $qiwiDescription = $qiwiCapture(function () { $this->options->description(); });
}
$qiwiDescription = $qiwiCleanMetaText($qiwiDescription);
if ($qiwiDescription === '') {
    $qiwiDescription = $qiwiCleanMetaText($qiwiCapture(function () { $this->options->description(); }));
}
if ($qiwiDescription === '') {
    $qiwiDescription = $qiwiSiteTitle;
}

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

$qiwiV2StyleVersion = @filemtime(__DIR__ . '/assets/css/v2.css');
$qiwiV2StyleAsset = 'assets/css/v2.css' . ($qiwiV2StyleVersion ? '?v=' . $qiwiV2StyleVersion : '');
$qiwiReadingFontVersion = @filemtime(__DIR__ . '/assets/fonts/lxgw-wenkai-screen/lxgwwenkaiscreen.css');
$qiwiReadingFontAsset = 'assets/fonts/lxgw-wenkai-screen/lxgwwenkaiscreen.css' . ($qiwiReadingFontVersion ? '?v=' . $qiwiReadingFontVersion : '');
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($qiwiLang, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="renderer" content="webkit">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($qiwiPageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <script>(function(){document.documentElement.classList.add('js');try{var t=localStorage.getItem('theme-preference')||'dark';document.documentElement.setAttribute('data-theme',t);}catch(e){document.documentElement.setAttribute('data-theme','dark');}})();</script>

    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(qiwiGetMappedAssetUrl($qiwiReadingFontAsset), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(qiwiGetMappedAssetUrl($qiwiV2StyleAsset), ENT_QUOTES, 'UTF-8'); ?>">
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
<body class="qiwi-v2">

<?php
$qiwiNavbarAvatar = function_exists('qiwiGetSidebarProfileAvatar')
    ? trim((string) qiwiGetSidebarProfileAvatar($this))
    : trim((string) $this->options->logoUrl);
$qiwiHomePostsUrl = rtrim((string) $this->options->siteUrl, '/') . '/';
$qiwiSiteDescription = trim((string) $this->options->description);
$qiwiEnglishTitle = trim((string) qiwiGetOptionValue($this, 'v2EnglishTitle', 'QIWI JOURNAL'));
$qiwiSidebarSlogan = trim((string) qiwiGetOptionValue($this, 'v2SidebarSlogan', '向内求索 · ON AIR'));
$qiwiRenderNavItems = function ($mobile = false) use ($qiwiNavItems, $qiwiHomePostsUrl) {
    $prefix = $mobile ? 'mobile' : 'desktop';
    ?>
    <a href="<?php echo htmlspecialchars($qiwiHomePostsUrl, ENT_QUOTES, 'UTF-8'); ?>"<?php if ($this->is('index')): ?> class="current" aria-current="page"<?php endif; ?>>首页</a>
    <?php foreach ($qiwiNavItems as $index => $item): ?>
        <?php
        $children = isset($item['children']) ? (array) $item['children'] : [];
        $isCurrent = !empty($item['slug']) && $this->is('page', $item['slug']);
        foreach ($children as $child) {
            if (!empty($child['slug']) && $this->is('page', $child['slug'])) {
                $isCurrent = true;
                break;
            }
        }
        ?>
        <div class="v2-nav-item<?php if (!empty($children)): ?> has-children<?php endif; ?>">
            <div class="v2-nav-row">
                <a href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>"<?php if ($isCurrent): ?> class="current" aria-current="page"<?php endif; ?><?php if ($item['external']): ?> target="_blank" rel="noopener noreferrer"<?php endif; ?>><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                <?php if (!empty($children)): ?>
                <button class="v2-submenu-toggle" type="button" aria-expanded="false" aria-controls="<?php echo $prefix; ?>-submenu-<?php echo (int) $index; ?>" aria-label="展开 <?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?> 子菜单">⌄</button>
                <?php endif; ?>
            </div>
            <?php if (!empty($children)): ?>
            <div class="v2-submenu" id="<?php echo $prefix; ?>-submenu-<?php echo (int) $index; ?>" hidden>
                <?php foreach ($children as $child): ?>
                <a href="<?php echo htmlspecialchars($child['url'], ENT_QUOTES, 'UTF-8'); ?>"<?php if (!empty($child['slug']) && $this->is('page', $child['slug'])): ?> class="current" aria-current="page"<?php endif; ?><?php if ($child['external']): ?> target="_blank" rel="noopener noreferrer"<?php endif; ?>><?php echo htmlspecialchars($child['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php
};
?>

<header class="v2-mobile-bar">
    <a class="v2-mobile-title" href="<?php $this->options->siteUrl(); ?>"><?php $this->options->title(); ?></a>
    <div class="v2-mobile-actions">
        <button class="theme-toggle v2-icon-button" type="button" onclick="toggleTheme(event)" aria-label="切换主题">◐</button>
        <button class="v2-menu-toggle v2-icon-button" type="button" aria-expanded="false" aria-controls="v2-mobile-nav" aria-label="打开菜单">☰</button>
    </div>
</header>

<nav class="v2-mobile-nav" id="v2-mobile-nav" aria-label="移动端导航" hidden>
    <?php $qiwiRenderNavItems(true); ?>
</nav>

<div class="v2-shell">
    <aside class="v2-side">
        <nav class="v2-nav" aria-label="主导航">
            <?php $qiwiRenderNavItems(false); ?>
        </nav>
        <div class="v2-side-tools">
            <button class="theme-toggle v2-theme-button" type="button" onclick="toggleTheme(event)" aria-label="切换主题">◐</button>
        </div>
        <div class="v2-side-logo">
            <?php if ($qiwiNavbarAvatar !== ''): ?>
            <img class="v2-side-avatar" src="<?php echo htmlspecialchars($qiwiNavbarAvatar, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" decoding="async">
            <?php endif; ?>
            <a class="v2-side-title" href="<?php $this->options->siteUrl(); ?>"><?php $this->options->title(); ?></a>
            <?php if ($qiwiEnglishTitle !== ''): ?><div class="v2-side-english"><?php echo htmlspecialchars($qiwiEnglishTitle, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            <?php if ($qiwiSidebarSlogan !== ''): ?><p class="v2-side-slogan"><?php echo htmlspecialchars($qiwiSidebarSlogan, ENT_QUOTES, 'UTF-8'); ?></p><?php elseif ($qiwiSiteDescription !== ''): ?><p class="v2-side-slogan"><?php echo htmlspecialchars($qiwiSiteDescription, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        </div>
    </aside>
    <main id="qiwi-pjax" class="v2-main" tabindex="-1">
