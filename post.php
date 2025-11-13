<?php
/**
 * 文章详情页 - Medium 风格
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<div class="article-page">
    <!-- 左侧留白 -->
    <div class="layout-spacer-left"></div>

    <!-- 文章主体 -->
    <div class="article-main">
        <article class="single-post" itemscope itemtype="http://schema.org/BlogPosting">
            <!-- 文章头部 -->
            <header class="article-header">
                <h1 class="article-header-title" itemprop="name headline">
                    <?php $this->title(); ?>
                </h1>
                <div class="article-header-meta">
                    <img src="https://gravatar.loli.net/avatar/<?php echo md5($this->author->mail); ?>?s=96&d=mp" alt="<?php $this->author(); ?>" class="author-avatar">
                    <div class="author-info">
                        <span class="author-name"><?php $this->author(); ?></span>
                        <span class="post-date"><?php $this->date('Y年m月d日'); ?> · <?php
                            $content = $this->content;
                            $wordCount = mb_strlen(strip_tags($content), 'UTF-8');
                            $speed = 300 + ($wordCount > 1000 ? 100 : 0) + ($wordCount > 2000 ? 100 : 0) + ($wordCount > 3000 ? 100 : 0);
                            $readingTime = max(1, round($wordCount / $speed));
                            echo $readingTime;
                        ?> 分钟阅读</span>
                    </div>
                </div>
            </header>

            <!-- 文章头图 -->
            <?php
            $showThumbnail = $this->fields->showThumbnail;
            $thumbnail = $this->fields->thumbnail;
            if (($showThumbnail == 2 || $showThumbnail == 3) && !empty($thumbnail)):
            ?>
            <img src="<?php echo $thumbnail; ?>" alt="<?php $this->title(); ?>" class="article-hero" loading="lazy">
            <?php endif; ?>

            <!-- 文章内容 -->
            <div class="article-body" itemprop="articleBody">
                <?php $this->content(); ?>
            </div>

            <!-- 文章标签 -->
            <?php if ($this->tags): ?>
            <div class="post-tags">
                <span class="tags-label">标签:</span>
                <div class="article-tags">
                    <?php $this->tags(' ', true, ''); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 文章导航 -->
            <nav class="post-navigation">
                <div class="nav-previous">
                    <?php $this->thePrev('%s', '没有更早的文章了'); ?>
                </div>
                <div class="nav-next">
                    <?php $this->theNext('%s', '没有更新的文章了'); ?>
                </div>
            </nav>
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
