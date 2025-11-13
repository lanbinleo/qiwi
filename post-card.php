<?php
/**
 * Post Card Component - 文章列表项组件
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 计算字数和阅读时间
$content = $this->content;
$wordCount = mb_strlen(strip_tags($content), 'UTF-8');
$speed = 300 + ($wordCount > 1000 ? 100 : 0) + ($wordCount > 2000 ? 100 : 0) + ($wordCount > 3000 ? 100 : 0);
$readingTime = max(1, round($wordCount / $speed));

// 格式化字数显示
if ($wordCount > 1000) {
    $wordCountDisplay = number_format($wordCount / 1000, 1) . 'k字';
} else {
    $wordCountDisplay = $wordCount . '字';
}

// 判断是否显示头图
$showThumbnail = $this->fields->showThumbnail;
$thumbnail = $this->fields->thumbnail;
$shouldShowThumbnail = (($showThumbnail == 1 || $showThumbnail == 3) && !empty($thumbnail));
?>

<li class="article-item">
    <div class="article-item-inner">
        <div class="article-content">
            <div class="article-meta">
                <span><?php $this->date('Y-m-d'); ?></span>
                <span><?php echo $readingTime; ?> 分钟阅读</span>
                <?php if ($this->categories): ?>
                <span class="meta-category">
                    <?php $this->category(','); ?>
                </span>
                <?php endif; ?>
            </div>
            <h2 class="article-title">
                <a href="<?php $this->permalink(); ?>" class="article-title-link"><?php $this->title(); ?></a>
            </h2>
            <p class="article-excerpt">
                <?php
                if ($this->fields->excerpt) {
                    echo $this->fields->excerpt;
                } else {
                    // 如果有头图，展示少一些，这样比例更协调
                    $excerptLength = $shouldShowThumbnail ? 85 : 125;
                    $this->excerpt($excerptLength, '...');
                }
                ?>
            </p>
            <?php if ($this->tags): ?>
            <div class="article-tags">
                <?php $this->tags(' ', true, ''); ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($shouldShowThumbnail): ?>
        <div class="article-thumbnail-wrapper">
            <img src="<?php echo $thumbnail; ?>" alt="<?php $this->title(); ?>" class="article-thumbnail" loading="lazy">
        </div>
        <?php endif; ?>
    </div>
</li>
