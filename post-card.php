<?php
/**
 * Post Card Component - Reusable post card template
 * Includes thumbnail, metadata, tags, word count and reading time
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// Calculate word count and reading time
$content = $this->content;
$wordCount = mb_strlen(strip_tags($content), 'UTF-8');
// if words > 1k, speed = 400+, >2k speed = 500+, >3k 600+
$speed = 300 + ($wordCount > 1000 ? 100 : 0) + ($wordCount > 2000 ? 100 : 0) + ($wordCount > 3000 ? 100 : 0);
$readingTime = max(1, round($wordCount / $speed)); // 300 characters per minute, minimum 1 minute

// Format word count display
if ($wordCount > 1000) {
    $wordCountDisplay = number_format($wordCount / 1000, 1) . 'k字';
} else {
    $wordCountDisplay = $wordCount . '字';
}
?>

<?php
// 决定布局方式
$showThumbnail = $this->fields->showThumbnail;
$thumbnail = $this->fields->thumbnail;
$thumbnailLayout = $this->fields->thumbnailLayout; // 文章单独设置
$globalLayout = $this->options->homeThumbnailLayout; // 全局设置

// 确定最终使用的布局
$finalLayout = 'top'; // 默认顶部布局
if ($thumbnailLayout && $thumbnailLayout !== 'default') {
    $finalLayout = $thumbnailLayout;
} elseif ($globalLayout) {
    $finalLayout = $globalLayout;
}

// 判断是否显示头图
$shouldShowThumbnail = (($showThumbnail == 1 || $showThumbnail == 3) && !empty($thumbnail));

// 获取文章索引用于交替布局
global $postIndex;
if (!isset($postIndex)) {
    $postIndex = 1;
}

// 设置文章卡片的CSS类
$cardClasses = 'post-preview post-card';
if ($shouldShowThumbnail && $finalLayout === 'side') {
    $cardClasses .= ' layout-side';
    // 根据文章索引决定图片位置
    if ($postIndex % 2 === 0) {
        $cardClasses .= ' layout-side-right'; // 偶数文章图片在右侧
    } else {
        $cardClasses .= ' layout-side-left';  // 奇数文章图片在左侧
    }
}
?>

<article class="<?php echo $cardClasses; ?>" data-href="<?php $this->permalink() ?>" itemscope itemtype="http://schema.org/BlogPosting">
  <?php if ($shouldShowThumbnail && $finalLayout === 'top'): ?>
  <div class="post-thumbnail">
    <a href="<?php $this->permalink() ?>">
      <img src="<?php echo $thumbnail; ?>" alt="<?php $this->title() ?>" loading="lazy">
    </a>
  </div>
  <?php endif; ?>
  
  <?php if ($shouldShowThumbnail && $finalLayout === 'side'): ?>
  <div class="post-thumbnail">
    <a href="<?php $this->permalink() ?>">
      <img src="<?php echo $thumbnail; ?>" alt="<?php $this->title() ?>" loading="lazy">
    </a>
  </div>
  <?php endif; ?>
  
  <div class="post-content-wrapper">
  <header class="post-preview-header">
    <h2 class="post-title" itemprop="name headline">
      <a href="<?php $this->permalink() ?>" itemprop="url"><?php $this->title() ?></a>
    </h2>
    
    <div class="post-meta">
      <span class="post-date">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
          <line x1="16" y1="2" x2="16" y2="6"></line>
          <line x1="8" y1="2" x2="8" y2="6"></line>
          <line x1="3" y1="10" x2="21" y2="10"></line>
        </svg>
        <time datetime="<?php $this->date('c'); ?>" itemprop="datePublished"><?php $this->date('Y-m-d'); ?></time>
      </span>
      
      <span class="post-comments" itemprop="interactionCount">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m3 21 1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"></path>
        </svg>
        <?php $this->commentsNum('%d 条评论'); ?>
      </span>
      
      <?php if ($this->category): ?>
      <span class="post-category">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M5 10H7C9 10 10 9 10 7V5C10 3 9 2 7 2H5C3 2 2 3 2 5V7C2 9 3 10 5 10Z" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M17 10H19C21 10 22 9 22 7V5C22 3 21 2 19 2H17C15 2 14 3 14 5V7C14 9 15 10 17 10Z" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M17 22H19C21 22 22 21 22 19V17C22 15 21 14 19 14H17C15 14 14 15 14 17V19C14 21 15 22 17 22Z" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M5 22H7C9 22 10 21 10 19V17C10 15 9 14 7 14H5C3 14 2 15 2 17V19C2 21 3 22 5 22Z" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <?php $this->category(','); ?>
      </span>
      <?php endif; ?>
      
      <?php if ($this->tags): ?>
      <span class="post-tags-meta">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
          <line x1="7" y1="7" x2="7.01" y2="7"></line>
        </svg>
        <?php $this->tags(',', true, 'none'); ?>
      </span>
      <?php endif; ?>
      
      <span>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor">
            <path xmlns="http://www.w3.org/2000/svg" d="M5 6.2C5 5.07989 5 4.51984 5.21799 4.09202C5.40973 3.71569 5.71569 3.40973 6.09202 3.21799C6.51984 3 7.07989 3 8.2 3H15.8C16.9201 3 17.4802 3 17.908 3.21799C18.2843 3.40973 18.5903 3.71569 18.782 4.09202C19 4.51984 19 5.07989 19 6.2V21L12 16L5 21V6.2Z" stroke-width="1.5" stroke-linejoin="round"/>
        </svg>
        <?php echo $wordCountDisplay; ?> / <?php echo $readingTime; ?>分钟
      </span>
      
      <?php if (false && $this->user->hasLogin()): ?>
      <span>
        <a href="/admin/write-post.php?cid=<?php echo $this->cid; ?>" class="post-edit-btn" title="编辑文章">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
          </svg>
          <span>&nbsp;编辑文章</span>
        </a>
      </span>
      <?php endif; ?>
    </div>
  </header>
  
  <div class="post-excerpt" itemprop="articleBody">
    <?php
    // 根据布局调整摘要长度
    $excerptLength = ($finalLayout === 'side') ? 150 : 200;
    
    if ($this->fields->excerpt): ?>
      <?php
      // 如果是左右布局且自定义摘要过长，进行裁剪
      $excerpt = $this->fields->excerpt;
      if ($finalLayout === 'side' && mb_strlen(strip_tags($excerpt), 'UTF-8') > $excerptLength) {
          $excerpt = mb_substr(strip_tags($excerpt), 0, $excerptLength, 'UTF-8') . '...';
      }
      echo $excerpt;
      ?>
    <?php else: ?>
      <?php $this->excerpt($excerptLength, '...'); ?>
    <?php endif; ?>
  </div>
  </div>
</article>