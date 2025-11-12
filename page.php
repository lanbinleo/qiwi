<?php
/**
 * 通用页面模板
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<div class="article-page">
    <!-- 左侧留白 -->
    <div class="layout-spacer-left"></div>

    <!-- 页面主体 -->
    <div class="article-main">
        <article class="single-page">
            <header class="article-header">
                <h1 class="article-header-title"><?php $this->title(); ?></h1>
            </header>

            <div class="article-body">
                <?php $this->content(); ?>
            </div>
        </article>

        <!-- 评论区 -->
        <?php if ($this->allow('comment')): ?>
        <div class="comments-wrapper">
            <?php $this->need('comments.php'); ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 右侧留白 -->
    <div class="layout-spacer-right"></div>
</div>

<?php $this->need('footer.php'); ?>
