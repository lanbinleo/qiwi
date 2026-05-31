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

// 判断是否显示头图
$showThumbnail = $this->fields->showThumbnail;
$thumbnail = $this->fields->thumbnail;
$shouldShowThumbnail = (($showThumbnail == 1 || $showThumbnail == 3) && !empty($thumbnail));
?>

<li class="article-item">
    <div class="article-item-inner">
        <div class="article-content">
            <div class="article-meta">
                <span><?php echo htmlspecialchars(qiwiFormatPostRelativeTime($this->created), ENT_QUOTES, 'UTF-8'); ?></span>
                <span><?php echo $readingTime; ?> 分钟阅读</span>
                <span><?php echo (int) $postViews; ?> 浏览</span>
                <?php if ($postCommentCount > 0): ?>
                <span><?php echo (int) $postCommentCount; ?> 评论</span>
                <?php endif; ?>
                <?php if ($this->fields->isSticky == 1): ?>
                <span class="meta-sticky">置顶</span>
                <?php endif; ?>
            </div>
            <h2 class="article-title">
                <a href="<?php $this->permalink(); ?>" class="article-title-link"><?php $this->title(); ?></a>
            </h2>
            <p class="article-excerpt">
                <?php
                $excerptLength = $shouldShowThumbnail ? 85 : 125;
                if ($this->fields->excerpt) {
                    echo htmlspecialchars(qiwiExtractPlainText($this->fields->excerpt), ENT_QUOTES, 'UTF-8');
                } else {
                    // 如果有头图，展示少一些，这样比例更协调
                    echo htmlspecialchars(qiwiExcerptText($this->content, $excerptLength), ENT_QUOTES, 'UTF-8');
                }
                ?>
            </p>
            <?php if ($this->tags): ?>
            <div class="article-tags">
                <?php echo qiwiRenderTermLinks($this->tags, 'tag'); ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($shouldShowThumbnail): ?>
        <div class="article-thumbnail-wrapper">
            <img src="<?php echo htmlspecialchars($thumbnail, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php $this->title(); ?>" class="article-thumbnail" loading="lazy" decoding="async" width="420" height="236" sizes="(max-width: 768px) 100vw, 30vw">
        </div>
        <?php endif; ?>
    </div>
</li>
