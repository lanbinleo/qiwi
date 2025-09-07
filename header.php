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
<body<?php if ($this->is('single')): ?> class="single-post"<?php endif; ?>>

<header class="site-header">
    <div class="container">
        <div class="site-branding">
            <?php if ($this->options->logoUrl): ?>
                <a href="<?php $this->options->siteUrl(); ?>" class="site-logo">
                    <img src="<?php $this->options->logoUrl(); ?>" alt="<?php $this->options->title(); ?>">
                </a>
            <?php else: ?>
                <a href="<?php $this->options->siteUrl(); ?>" class="site-title">
                    <?php $this->options->title(); ?>
                </a>
            <?php endif; ?>
            
            <?php if ($this->options->description): ?>
                <p class="site-description"><?php $this->options->description(); ?></p>
            <?php endif; ?>
        </div>

        <nav class="site-nav" role="navigation">
            <a href="<?php $this->options->siteUrl(); ?>"<?php if ($this->is('index')): ?> class="current"<?php endif; ?>>
                首页
            </a>
            
            <?php \Widget\Contents\Page\Rows::alloc()->to($pages); ?>
            <?php while ($pages->next()): ?>
                <a href="<?php $pages->permalink(); ?>"<?php if ($this->is('page', $pages->slug)): ?> class="current"<?php endif; ?> title="<?php $pages->title(); ?>">
                    <?php $pages->title(); ?>
                </a>
            <?php endwhile; ?>
            
            <div class="search-form">
                <form method="post" action="<?php $this->options->siteUrl(); ?>" role="search">
                    <input type="text" id="s" name="s" class="search-input" placeholder="搜索..." value="<?php $this->archiveSlug(); ?>">
                    <button type="submit" class="search-button">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                    </button>
                </form>
            </div>
        </nav>
    </div>
</header>

<main class="site-main">
