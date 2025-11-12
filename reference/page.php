<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('header.php'); ?>

<div class="container main-wrapper">
  <main class="content-area">
    <article class="single-page" itemscope itemtype="http://schema.org/WebPage">

      <!-- Page Header -->
      <header class="page-header">
        <h1 class="page-title" itemprop="name headline">
          <?php $this->title() ?>
        </h1>

        <?php if ($this->is('page') && $this->getPageType() !== 'post'): ?>
        <div class="page-meta">
          <span class="page-date">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
              <line x1="16" y1="2" x2="16" y2="6"></line>
              <line x1="8" y1="2" x2="8" y2="6"></line>
              <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            最后更新: <?php echo date('Y年m月d日', $this->modified); ?>
          </span>
        </div>
        <?php endif; ?>
      </header>

      <!-- Page Content -->
      <div class="page-content" itemprop="text">
        <?php $this->content(); ?>
      </div>

    </article>

    <!-- Comments Section -->
    <div class="comments-section">
      <?php $this->need('comments.php'); ?>
    </div>

  </main>

  <aside class="sidebar">
    <?php $this->need('sidebar.php'); ?>
  </aside>
</div>

</main>
<?php $this->need('footer.php'); ?>
