<?php
/**
 * Qiwi Theme - 首页
 *
 * @package Qiwi
 * @author MaxQi
 * @version 1.1.6
 * @link http://mura.ink
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<div class="main-layout">
    <!-- 左侧留白 -->
    <div class="layout-spacer-left"></div>

    <!-- 主要内容 -->
    <div class="main-content">
        <?php if ($this->have()): ?>
        <ul class="article-list">
            <?php while($this->next()): ?>
                <?php $this->need('post-card.php'); ?>
            <?php endwhile; ?>
        </ul>

        <!-- 分页导航 -->
        <?php if ($this->getTotal() > $this->parameter->pageSize): ?>
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
        <?php endif; ?>

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
    </div>

    <!-- 侧边栏 -->
    <aside class="sidebar">
        <?php $this->need('sidebar.php'); ?>
    </aside>

    <!-- 右侧留白 -->
    <div class="layout-spacer-right"></div>
</div>

<?php $this->need('footer.php'); ?>
