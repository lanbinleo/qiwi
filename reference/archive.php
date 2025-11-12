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
                        <?php $this->need('post-card.php'); ?>
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
