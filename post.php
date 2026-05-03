<?php
/**
 * 文章详情页 - Medium 风格
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$postViews = qiwiRecordPostView($this->cid);
$qiwiShowToc = qiwiShouldShowToc($this);
$this->need('header.php');
$qiwiCopyrightHtml = function_exists('qiwiGetPostCopyrightHtml') ? qiwiGetPostCopyrightHtml($this) : '';

ob_start();
$this->thePrev('%s', '');
$qiwiPrevPostLink = trim(ob_get_clean());
$qiwiPrevPostHref = '';
$qiwiPrevPostTitle = trim(strip_tags($qiwiPrevPostLink));
if ($qiwiPrevPostLink !== '' && preg_match('/href=(["\'])(.*?)\1/i', $qiwiPrevPostLink, $matches)) {
    $qiwiPrevPostHref = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

ob_start();
$this->theNext('%s', '');
$qiwiNextPostLink = trim(ob_get_clean());
$qiwiNextPostHref = '';
$qiwiNextPostTitle = trim(strip_tags($qiwiNextPostLink));
if ($qiwiNextPostLink !== '' && preg_match('/href=(["\'])(.*?)\1/i', $qiwiNextPostLink, $matches)) {
    $qiwiNextPostHref = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
?>

<div class="article-page">
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
                        <span class="post-date"><?php $this->date('Y-m-d H:i'); ?> · <?php
                            $content = $this->content;
                            $wordCount = mb_strlen(strip_tags($content), 'UTF-8');
                            $speed = 300 + ($wordCount > 1000 ? 100 : 0) + ($wordCount > 2000 ? 100 : 0) + ($wordCount > 3000 ? 100 : 0);
                            $readingTime = max(1, round($wordCount / $speed));
                            echo $readingTime;
                        ?> 分钟阅读 · <?php echo (int) $postViews; ?> 次浏览 · <?php echo (int) $this->commentsNum; ?> 条评论</span>
                    </div>
                </div>
            </header>

            <!-- 文章头图 -->
            <?php
            $showThumbnail = $this->fields->showThumbnail;
            $thumbnail = $this->fields->thumbnail;
            if (($showThumbnail == 2 || $showThumbnail == 3) && !empty($thumbnail)):
            ?>
            <img src="<?php echo htmlspecialchars($thumbnail, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php $this->title(); ?>" class="article-hero" loading="eager" fetchpriority="high" decoding="async" width="1200" height="675">
            <?php endif; ?>

            <!-- 文章内容 -->
            <div class="article-body" itemprop="articleBody">
                <?php qiwiContent($this); ?>
            </div>

            <?php if ($qiwiCopyrightHtml !== '' || $this->tags): ?>
            <section class="post-endnote" aria-label="文章附注">
                <?php if ($qiwiCopyrightHtml !== ''): ?>
                <div class="post-copyright">
                    <div class="post-endnote-label">Copyright</div>
                    <div class="post-copyright-body">
                        <?php echo $qiwiCopyrightHtml; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($this->tags): ?>
                <div class="post-tags">
                    <span class="post-endnote-label">Tags</span>
                    <div class="article-tags">
                        <?php $this->tags(' ', true, ''); ?>
                    </div>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <!-- 文章导航 -->
            <nav class="post-navigation">
                <?php if ($qiwiPrevPostHref !== ''): ?>
                <a class="post-navigation-item nav-previous" href="<?php echo htmlspecialchars($qiwiPrevPostHref, ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="post-navigation-label">上一篇</span>
                    <span class="post-navigation-link"><?php echo htmlspecialchars($qiwiPrevPostTitle, ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
                <?php else: ?>
                <div class="post-navigation-item nav-previous is-empty">
                    <span class="post-navigation-label">上一篇</span>
                    <span class="post-navigation-empty">没有更早的文章了</span>
                </div>
                <?php endif; ?>

                <?php if ($qiwiNextPostHref !== ''): ?>
                <a class="post-navigation-item nav-next" href="<?php echo htmlspecialchars($qiwiNextPostHref, ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="post-navigation-label">下一篇</span>
                    <span class="post-navigation-link"><?php echo htmlspecialchars($qiwiNextPostTitle, ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
                <?php else: ?>
                <div class="post-navigation-item nav-next is-empty">
                    <span class="post-navigation-label">下一篇</span>
                    <span class="post-navigation-empty">没有更新的文章了</span>
                </div>
                <?php endif; ?>
            </nav>
        </article>

        <!-- 评论区 -->
        <?php if ($this->allow('comment')): ?>
        <div class="comments-wrapper">
            <?php $this->need('comments.php'); ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($qiwiShowToc): ?>
    <!-- 文章目录 -->
    <nav class="article-toc" aria-label="文章目录"></nav>
    <?php endif; ?>
</div>

<?php $this->need('footer.php'); ?>
