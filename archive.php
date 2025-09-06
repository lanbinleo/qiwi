<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('header.php'); ?>

<div class="container main-wrapper">
    <main class="content-area">
        <!-- Archive Header -->
        <header class="page-header">
            <h1 class="page-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: var(--spacing-xs);">
                    <polyline points="21,8 21,21 3,21 3,8"></polyline>
                    <rect x="1" y="3" width="22" height="5"></rect>
                    <line x1="10" y1="12" x2="14" y2="12"></line>
                </svg>
                <?php $this->archiveTitle([
                    'category' => _t('分类 %s 下的文章'),
                    'search'   => _t('包含关键字 %s 的文章'),
                    'tag'      => _t('标签 %s 下的文章'),
                    'author'   => _t('%s 发布的文章')
                ], '', ''); ?>
            </h1>
        </header>

        <?php if ($this->have()): ?>
            <ul class="post-list">
                <?php while ($this->next()): ?>
                    <li class="post-list-item">
                        <article class="post-preview post-card" data-href="<?php $this->permalink() ?>" itemscope itemtype="http://schema.org/BlogPosting">
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
                                    
                                    <span class="post-author" itemprop="author" itemscope itemtype="http://schema.org/Person">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="12" cy="7" r="4"></circle>
                                        </svg>
                                        <a itemprop="name" href="<?php $this->author->permalink(); ?>" rel="author"><?php $this->author(); ?></a>
                                    </span>
                                    
                                    <span class="post-comments" itemprop="interactionCount">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="m3 21 1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"></path>
                                        </svg>
                                        <a href="<?php $this->permalink() ?>#comments"><?php $this->commentsNum('%d 条评论', '1 条评论', '%d 条评论'); ?></a>
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
                                    <?php if (false&&$this->user->hasLogin()): ?>
                                        <a href="/admin/write-post.php?cid=<?php echo $this->cid; ?>" class="post-edit-btn" title="编辑文章">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                            <span>&nbsp;编辑文章</span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </header>
                            
                            <div class="post-excerpt" itemprop="articleBody">
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
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                </div>
                <h2><?php _e('没有找到内容'); ?></h2>
                <p><?php _e('抱歉，没有找到符合条件的内容。'); ?></p>
            </div>
        <?php endif; ?>
    </main>

    <aside class="sidebar">
        <?php $this->need('sidebar.php'); ?>
    </aside>
</div>

</main>
<?php $this->need('footer.php'); ?>
