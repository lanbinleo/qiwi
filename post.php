<?php
/**
 * 文章详情页 - Medium 风格
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$postViews = qiwiRecordPostView($this->cid);
$qiwiShowToc = qiwiShouldShowToc($this);
$this->need('header.php');
$qiwiCopyrightHtml = function_exists('qiwiGetPostCopyrightHtml') ? qiwiGetPostCopyrightHtml($this) : '';
$qiwiCategoryLinks = function_exists('qiwiRenderTermLinks')
    ? qiwiRenderTermLinks(isset($this->categories) ? $this->categories : array(), 'post-date-category-link', 'category')
    : '';
$qiwiPostLikeEndpoint = function_exists('qiwiGetThemeActionEndpoint') ? qiwiGetThemeActionEndpoint('post-like', $this->options) : '';
$qiwiPostLikeCount = 0;
$qiwiPostLiked = false;
if (class_exists('QiwiTheme_Plugin')) {
    $qiwiPostLikeCounts = QiwiTheme_Plugin::postLikeCounts(array((int) $this->cid));
    $qiwiPostLikeCount = isset($qiwiPostLikeCounts[(int) $this->cid]) ? (int) $qiwiPostLikeCounts[(int) $this->cid] : 0;
    $qiwiPostLikeIdentityHash = QiwiTheme_Plugin::currentPostLikeIdentityHash();
    $qiwiPostLiked = $qiwiPostLikeIdentityHash !== '' && QiwiTheme_Plugin::hasPostLiked((int) $this->cid, $qiwiPostLikeIdentityHash);
}
$qiwiPostSupportEnabled = (string) qiwiGetOptionValue($this, 'postSupportEnabled', '0') === '1';
$qiwiPostSupportQrUrl = trim((string) qiwiGetOptionValue($this, 'postSupportQrUrl', ''));
$qiwiPostSupportTopText = trim((string) qiwiGetOptionValue($this, 'postSupportTopText', '请我喝一杯咖啡吧'));
$qiwiPostSupportBottomText = trim((string) qiwiGetOptionValue($this, 'postSupportBottomText', '或者评论一下分享你的感受'));
$qiwiPostSupportVisible = $qiwiPostSupportEnabled && $qiwiPostSupportQrUrl !== '';

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
                    <img src="<?php echo htmlspecialchars(function_exists('qiwiGetCommentAvatarUrl') ? qiwiGetCommentAvatarUrl($this->author->mail, 96) : 'https://gravatar.loli.net/avatar/' . md5($this->author->mail) . '?s=96&d=mp', ENT_QUOTES, 'UTF-8'); ?>" alt="<?php $this->author(); ?>" class="author-avatar">
                    <div class="author-info">
                        <span class="author-name"><?php $this->author(); ?></span>
                        <div class="post-date-row">
                            <span class="post-date"><?php $this->date('Y-m-d H:i'); ?><?php if ($qiwiCategoryLinks !== ''): ?> · <span class="post-date-categories"><?php echo $qiwiCategoryLinks; ?></span><?php endif; ?> · <?php
                                $content = $this->content;
                                $wordCount = function_exists('qiwiCountReadableWords')
                                    ? qiwiCountReadableWords($content)
                                    : mb_strlen(strip_tags($content), 'UTF-8');
                                $articleCommentCount = function_exists('qiwiGetCommentCountIncludingReplies') ? qiwiGetCommentCountIncludingReplies($this->cid) : (int) $this->commentsNum;
                                echo function_exists('qiwiFormatPostWordCount') ? qiwiFormatPostWordCount($wordCount) : (int) $wordCount . '字';
                            ?> · <?php echo (int) $postViews; ?> 次浏览<?php if ($articleCommentCount > 0): ?> · <a href="#comments"><?php echo (int) $articleCommentCount; ?> 条评论</a><?php endif; ?></span>
                            <div class="article-reading-control" data-reading-control>
                                <button type="button" class="article-reading-trigger" data-reading-trigger aria-expanded="false" aria-haspopup="true">
                                    <span data-reading-label="font">易读</span><span aria-hidden="true">/</span><span data-reading-label="spacing">宽</span><span aria-hidden="true">/</span><span data-reading-label="size">中</span>
                                </button>
                                <div class="article-reading-popover" data-reading-popover role="group" aria-label="阅读设置">
                                    <div class="article-reading-group" data-reading-group="font" aria-label="字体">
                                        <span>字体</span>
                                        <button type="button" data-reading-option="font" data-reading-value="readable">易读</button>
                                        <button type="button" data-reading-option="font" data-reading-value="plain">普通</button>
                                    </div>
                                    <div class="article-reading-group" data-reading-group="spacing" aria-label="间距">
                                        <span>间距</span>
                                        <button type="button" data-reading-option="spacing" data-reading-value="wide">宽</button>
                                        <button type="button" data-reading-option="spacing" data-reading-value="compact">窄</button>
                                    </div>
                                    <div class="article-reading-group" data-reading-group="size" aria-label="字号">
                                        <span>字号</span>
                                        <button type="button" data-reading-option="size" data-reading-value="large">大</button>
                                        <button type="button" data-reading-option="size" data-reading-value="medium">中</button>
                                        <button type="button" data-reading-option="size" data-reading-value="small">小</button>
                                    </div>
                                </div>
                            </div>
                        </div>
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

            <section class="post-reactions" aria-label="文章反馈">
                <button
                    type="button"
                    class="post-like-button<?php if ($qiwiPostLiked): ?> is-liked has-count<?php endif; ?>"
                    data-post-like
                    data-post-id="<?php echo (int) $this->cid; ?>"
                    data-like-endpoint="<?php echo htmlspecialchars($qiwiPostLikeEndpoint, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-pressed="<?php echo $qiwiPostLiked ? 'true' : 'false'; ?>">
                    <i class="<?php echo $qiwiPostLiked ? 'fa-solid' : 'fa-regular'; ?> fa-heart" aria-hidden="true" data-like-icon></i>
                    <span data-like-label><?php echo $qiwiPostLiked ? '已喜欢' : '喜欢文章'; ?></span>
                    <span class="post-like-count" data-like-count<?php if (!$qiwiPostLiked): ?> hidden<?php endif; ?>><?php echo (int) $qiwiPostLikeCount; ?></span>
                </button>

                <?php if ($qiwiPostSupportVisible): ?>
                <div class="post-support" data-post-support<?php if (!$qiwiPostLiked): ?> hidden<?php endif; ?>>
                    <button type="button" class="post-support-button" aria-haspopup="true" aria-expanded="false">
                        <i class="fa-solid fa-mug-hot" aria-hidden="true"></i>
                        <span>支持作者</span>
                    </button>
                    <div class="post-support-popover" role="group" aria-label="支持作者">
                        <?php if ($qiwiPostSupportTopText !== ''): ?><p class="post-support-text"><?php echo htmlspecialchars($qiwiPostSupportTopText, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                        <img src="<?php echo htmlspecialchars($qiwiPostSupportQrUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="支持作者二维码" loading="lazy" decoding="async">
                        <?php if ($qiwiPostSupportBottomText !== ''): ?><p class="post-support-text is-bottom"><?php echo htmlspecialchars($qiwiPostSupportBottomText, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </section>

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
