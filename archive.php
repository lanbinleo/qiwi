<?php
/**
 * 归档/分类/标签页
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<div class="main-layout">
    <!-- 左侧留白 -->
    <div class="layout-spacer-left"></div>

    <!-- 主要内容 -->
    <div class="main-content">
        <header class="archive-header">
            <h1 class="archive-title">
                <?php $this->archiveTitle([
                    'category' => '分类: %s',
                    'search'   => '搜索: %s',
                    'tag'      => '标签: %s',
                    'author'   => '作者: %s'
                ], '', ''); ?>
            </h1>
        </header>

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
            <h2>没有找到内容</h2>
            <p>抱歉，没有找到符合条件的内容。</p>
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
