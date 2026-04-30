<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$qiwiNavItems = function_exists('qiwiGetNavigationItems') ? qiwiGetNavigationItems($this) : [];
$qiwiTemplate = isset($this->template) ? (string) $this->template : '';
$qiwiUseFontAwesome = (function_exists('qiwiNavigationUsesFontAwesome') && qiwiNavigationUsesFontAwesome($qiwiNavItems))
    || in_array($qiwiTemplate, ['page-timemachine.php', 'page-timemachine'], true);
?>
<!DOCTYPE html>
<html lang="<?php $this->options->lang(); ?>">
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="renderer" content="webkit">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php $this->archiveTitle([
            'category' => _t('分类 %s 下的文章'),
            'search'   => _t('包含关键字 %s 的文章'),
            'tag'      => _t('标签 %s 下的文章'),
            'author'   => _t('%s 发布的文章')
        ], '', ' - '); ?><?php $this->options->title(); ?></title>

    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?php $this->options->themeUrl('assets/css/style.css'); ?>">
    <?php if ($qiwiUseFontAwesome): ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <?php endif; ?>

    <!-- Meta Tags -->
    <meta name="description" content="<?php if ($this->is('single')) $this->excerpt(150, ''); else $this->options->description(); ?>">
    <meta name="keywords" content="<?php if ($this->is('single')) $this->tags(',', false); else $this->options->keywords(); ?>">
    <meta name="author" content="<?php if ($this->is('single')) $this->author(); else $this->options->title(); ?>">

    <!-- Favicon -->
    <link rel="icon" href="<?php $this->options->themeUrl('favicon.ico'); ?>">

    <!-- RSS -->
    <link rel="alternate" type="application/rss+xml" title="<?php $this->options->title(); ?>" href="<?php $this->options->feedUrl(); ?>">

    <!-- EXTENSIONS: LATEX -->
    <?php if ($this->is('post') && $this->fields->isLatex == 1): ?>
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

<!-- 顶部导航栏 -->
<nav class="navbar" aria-label="主导航">
    <div class="navbar-inner">
        <div class="navbar-title">
            <a href="<?php $this->options->siteUrl(); ?>">
                <?php $this->options->title(); ?>
            </a>
        </div>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-navigation-menu" aria-label="切换导航菜单">
            <span class="nav-toggle-bar"></span>
            <span class="nav-toggle-bar"></span>
            <span class="nav-toggle-bar"></span>
        </button>
        <ul class="nav-links" id="site-navigation-menu">
            <li><a href="<?php $this->options->siteUrl(); ?>"<?php if ($this->is('index')): ?> class="current"<?php endif; ?>>首页</a></li>

            <?php foreach ($qiwiNavItems as $item): ?>
                <?php
                    $hasChildren = !empty($item['children']);
                    $isCurrent = !empty($item['slug']) && $this->is('page', $item['slug']);
                    foreach ($item['children'] as $child) {
                        if (!empty($child['slug']) && $this->is('page', $child['slug'])) {
                            $isCurrent = true;
                            break;
                        }
                    }
                ?>
                <li<?php if ($hasChildren): ?> class="nav-item-has-children"<?php endif; ?>>
                    <a href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>"<?php if ($isCurrent): ?> class="current"<?php endif; ?><?php if ($item['external']): ?> target="_blank" rel="noopener noreferrer"<?php endif; ?>><?php if (!empty($item['icon'])): ?><i class="<?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i> <?php endif; ?><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?><?php if ($hasChildren): ?> <i class="fa-solid fa-angle-down nav-caret" aria-hidden="true"></i><?php endif; ?></a>
                    <?php if ($hasChildren): ?>
                    <ul class="nav-submenu">
                        <?php foreach ($item['children'] as $child): ?>
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
