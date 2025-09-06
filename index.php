<?php
/**
 * Qiwi Theme, a personalized theme for Typecho
 *
 * @package Qiwi
 * @author MaXiwi
 * @version 1.0.1
 * @link http://mura.ink
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<div class="container main-wrapper">
  <main class="content-area">
    <?php if ($this->have()): ?>
      <ul class="post-list">
        <?php while($this->next()): ?>
          <li class="post-list-item">
            <article class="post-preview post-card" data-href="<?php $this->permalink() ?>">
              <?php
              $showThumbnail = $this->fields->showThumbnail;
              $thumbnail = $this->fields->thumbnail;
              if (($showThumbnail == 1 || $showThumbnail == 3) && !empty($thumbnail)): ?>
              <div class="post-thumbnail">
                <a href="<?php $this->permalink() ?>">
                  <img src="<?php echo $thumbnail; ?>" alt="<?php $this->title() ?>" loading="lazy">
                </a>
              </div>
              <?php endif; ?>
              
              <header class="post-preview-header">
                <h2 class="post-title">
                  <a href="<?php $this->permalink() ?>"><?php $this->title() ?></a>
                </h2>
                
                <div class="post-meta">
                  <span class="post-date">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                      <line x1="16" y1="2" x2="16" y2="6"></line>
                      <line x1="8" y1="2" x2="8" y2="6"></line>
                      <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <?php $this->date('Y-m-d'); ?>
                  </span>
                  
                  <span class="post-comments">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="m3 21 1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"></path>
                    </svg>
                    <?php $this->commentsNum('%d 条评论'); ?>
                  </span>
                  
                  <?php if ($this->category): ?>
                  <span class="post-category">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                      <line x1="7" y1="7" x2="7.01" y2="7"></line>
                    </svg>
                    <?php $this->category(','); ?>
                  </span>
                  <?php endif; ?>
                </div>
              </header>
              
              <div class="post-excerpt">
                <?php if ($this->fields->excerpt): ?>
                  <?php echo $this->fields->excerpt; ?>
                <?php else: ?>
                  <?php $this->excerpt(200, '...'); ?>
                <?php endif; ?>
              </div>
            </article>
          </li>
        <?php endwhile; ?>
      </ul>

      <!-- 分页导航 -->
      <div class="pagination-wrapper">
        <?php $this->pageNav('« 上一页', '下一页 »', 3, '...', [
          'wrapTag' => 'nav',
          'wrapClass' => 'page-navigator',
          'itemTag' => '',
          'textTag' => 'span',
          'currentClass' => 'current',
          'prevClass' => 'prev',
          'nextClass' => 'next'
        ]); ?>
      </div>

    <?php else: ?>
      <div class="empty-posts">
        <div class="empty-icon">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14,2 14,8 20,8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10,9 9,9 8,9"></polyline>
          </svg>
        </div>
        <h2>暂无文章</h2>
        <p>这里还没有任何文章，请稍后再来查看。</p>
      </div>
    <?php endif; ?>
  </main>

  <aside class="sidebar">
    <?php $this->need('sidebar.php'); ?>
  </aside>
</div>

</main>
<?php $this->need('footer.php'); ?>