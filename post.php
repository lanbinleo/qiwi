<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('header.php'); ?>

<div class="container main-wrapper">
  <main class="content-area">
    <div class="post-card-container">
      <article class="single-post" itemscope itemtype="http://schema.org/BlogPosting">
        
        <?php
        $showThumbnail = $this->fields->showThumbnail;
        $thumbnail = $this->fields->thumbnail;
        if (($showThumbnail == 2 || $showThumbnail == 3) && !empty($thumbnail)): ?>
        <div class="post-thumbnail-single">
          <img src="<?php echo $thumbnail; ?>" alt="<?php $this->title() ?>" loading="lazy">
        </div>
        <?php endif; ?>
        
        <!-- Post Header -->
        <header class="post-header">
        <h1 class="post-title" itemprop="name headline">
          <?php $this->title() ?>
        </h1>
        
        <div class="post-meta">
          <span class="post-author" itemprop="author" itemscope itemtype="http://schema.org/Person">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <a itemprop="name" href="<?php $this->author->permalink(); ?>" rel="author">
              <?php $this->author(); ?>
            </a>
          </span>
          
          <span class="post-date">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
              <line x1="16" y1="2" x2="16" y2="6"></line>
              <line x1="8" y1="2" x2="8" y2="6"></line>
              <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <time datetime="<?php $this->date('c'); ?>" itemprop="datePublished">
              <?php $this->date('Y年m月d日'); ?>
            </time>
          </span>
          
          <?php if ($this->category): ?>
          <span class="post-category">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
              <line x1="7" y1="7" x2="7.01" y2="7"></line>
            </svg>
            <?php $this->category(','); ?>
          </span>
          <?php endif; ?>
          <span class="post-edit-btn">
          <?php if ($this->user->hasLogin()): ?>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
              </svg>
            <a href="/admin/write-post.php?cid=<?php echo $this->cid; ?>"  title="编辑文章">
              <span>编辑文章</span>
            </a>
          <?php endif; ?>
          </span>

          <span class="post-views">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
            <?php Typecho_Widget::widget('Widget_Stat')->to($stat); ?>
            <?php $this->viewsNum('%d 次阅读'); ?>
          </span>
        </div>
      </header>

      <!-- Post Content -->
      <div class="post-content" itemprop="articleBody">
        <?php $this->content(); ?>
      </div>

      <!-- Post Tags -->
      <?php if ($this->tags): ?>
      <div class="post-tags">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
          <line x1="7" y1="7" x2="7.01" y2="7"></line>
        </svg>
        <span class="tags-label">标签:</span>
        <div class="tags-list" itemprop="keywords">
          <?php $this->tags(', ', true, ''); ?>
        </div>
      </div>
      <?php endif; ?>

        <!-- Post Navigation -->
        <nav class="post-navigation">
          <div class="nav-next">
            <?php $this->theNext('
              <div class="nav-post">
                <span class="nav-label">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15,18 9,12 15,6"></polyline>
                  </svg>
                  下一篇
                </span>
                <span class="nav-title">%s</span>
              </div>
            ', ''); ?>
          </div>
          
          <div class="nav-previous">
            <?php $this->thePrev('
              <div class="nav-post">
                <span class="nav-label nav-label-right">
                  上一篇
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9,18 15,12 9,6"></polyline>
                  </svg>
                </span>
                <span class="nav-title">%s</span>
              </div>
            ', ''); ?>
          </div>
        </nav>

      </article>
    </div>

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
