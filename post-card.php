<?php
/**
 * Post Card Component - 文章列表项组件
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 计算字数和阅读时间
$content = $this->content;
$wordCount = function_exists('qiwiCountReadableWords')
    ? qiwiCountReadableWords($content)
    : mb_strlen(strip_tags($content), 'UTF-8');
$readingTime = function_exists('qiwiEstimateReadingMinutes')
    ? qiwiEstimateReadingMinutes($wordCount)
    : max(1, (int) round($wordCount / 300));

$postStats = function_exists('qiwiGetPostStats')
    ? qiwiGetPostStats($this->cid)
    : array(
        'views' => function_exists('qiwiGetPostViews') ? qiwiGetPostViews($this->cid) : 0,
        'comments' => function_exists('qiwiGetCommentCountIncludingReplies') ? qiwiGetCommentCountIncludingReplies($this->cid) : (int) $this->commentsNum,
    );
$postViews = isset($postStats['views']) ? (int) $postStats['views'] : 0;
$postCommentCount = isset($postStats['comments']) ? (int) $postStats['comments'] : (int) $this->commentsNum;
$postCategoryLinks = function_exists('qiwiRenderTermLinks')
    ? qiwiRenderTermLinks(isset($this->categories) ? $this->categories : array(), 'meta-category-link', 'category')
    : '';
$showThumbnail = $this->fields->showThumbnail;
$thumbnail = trim((string) $this->fields->thumbnail);
$shouldShowThumbnail = (($showThumbnail == 1 || $showThumbnail == 3) && $thumbnail !== '');

?>

<li class="article-item post-item<?php if ($shouldShowThumbnail): ?> has-thumbnail<?php endif; ?>" data-post-url="<?php $this->permalink(); ?>" tabindex="0" aria-label="阅读《<?php $this->title(); ?>》">
    <div class="article-item-inner">
        <div class="article-content">
            <div class="post-head">
                <?php if ($this->fields->isSticky == 1): ?><span class="pin-tag">置顶</span><?php endif; ?>
                <h2 class="article-title"><a href="<?php $this->permalink(); ?>" class="article-title-link post-title"><?php $this->title(); ?></a></h2>
                <?php if ($postCategoryLinks !== ''): ?><span class="post-cat"><?php echo $postCategoryLinks; ?></span><?php endif; ?>
            </div>
            <p class="article-excerpt post-excerpt">
                <?php
                if ($this->fields->excerpt) {
                    echo htmlspecialchars(qiwiExtractPlainText($this->fields->excerpt), ENT_QUOTES, 'UTF-8');
                } else {
                    echo htmlspecialchars(qiwiExcerptText($this->content, $shouldShowThumbnail ? 110 : 150), ENT_QUOTES, 'UTF-8');
                }
                ?>
            </p>
            <div class="post-foot">
                <span><?php echo htmlspecialchars(qiwiFormatPostRelativeTime($this->created), ENT_QUOTES, 'UTF-8'); ?> · <?php echo $readingTime; ?> 分钟阅读 · <?php echo (int) $postViews; ?> 浏览<?php if ($postCommentCount > 0): ?> · <?php echo (int) $postCommentCount; ?> 评论<?php endif; ?></span>
                <a class="read-more" href="<?php $this->permalink(); ?>">阅读全文</a>
            </div>
        </div>
        <?php if ($shouldShowThumbnail): ?>
        <div class="article-thumbnail-wrapper">
            <img src="<?php echo htmlspecialchars($thumbnail, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php $this->title(); ?>" class="article-thumbnail" loading="lazy" decoding="async" width="420" height="236" sizes="(max-width: 560px) 100vw, 180px">
        </div>
        <?php endif; ?>
    </div>
</li>
