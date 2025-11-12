<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<!DOCTYPE html>
<html lang="<?php $this->options->lang(); ?>">
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="renderer" content="webkit">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php $this->archiveTitle([
            'category' => _t('分类 %s 下的文章'),
            'search'   => _t('包含关键字 %s 的文章'),
            'tag'      => _t('标签 %s 下的文章'),
            'author'   => _t('%s 发布的文章')
        ], '', ' - '); ?><?php $this->options->title(); ?></title>

    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?php $this->options->themeUrl('assets/css/style.css'); ?>">

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
<nav class="navbar">
    <div class="navbar-inner">
        <div class="navbar-title">
            <a href="<?php $this->options->siteUrl(); ?>">
                <?php $this->options->title(); ?>
            </a>
        </div>
        <ul class="nav-links">
            <li><a href="<?php $this->options->siteUrl(); ?>"<?php if ($this->is('index')): ?> class="current"<?php endif; ?>>首页</a></li>

            <?php \Widget\Contents\Page\Rows::alloc()->to($pages); ?>
            <?php while ($pages->next()): ?>
                <li><a href="<?php $pages->permalink(); ?>"<?php if ($this->is('page', $pages->slug)): ?> class="current"<?php endif; ?>><?php $pages->title(); ?></a></li>
            <?php endwhile; ?>

            <li><button class="theme-toggle" onclick="toggleTheme()">◐</button></li>
        </ul>
    </div>
</nav>
