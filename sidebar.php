<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>

<!-- 侧边栏标题 -->
<div class="sidebar-header">
    <h1 class="site-title"><?php $this->options->title(); ?></h1>
    <?php if ($this->options->description): ?>
        <p class="site-motto"><?php $this->options->description(); ?></p>
    <?php endif; ?>
</div>

<!-- 最新文章 -->
<?php if (!empty($this->options->sidebarBlock) && in_array('ShowRecentPosts', $this->options->sidebarBlock)): ?>
<div class="sidebar-section">
    <h3 class="sidebar-title">最新文章</h3>
    <ul class="sidebar-list">
        <?php \Widget\Contents\Post\Recent::alloc()->to($recent); ?>
        <?php while ($recent->next()): ?>
            <li><a href="<?php $recent->permalink(); ?>"><?php $recent->title(); ?></a></li>
        <?php endwhile; ?>
    </ul>
</div>
<?php endif; ?>

<!-- 分类 -->
<?php if (!empty($this->options->sidebarBlock) && in_array('ShowCategory', $this->options->sidebarBlock)): ?>
<div class="sidebar-section">
    <h3 class="sidebar-title">分类</h3>
    <ul class="sidebar-list">
        <?php \Widget\Metas\Category\Rows::alloc()->to($category); ?>
        <?php while ($category->next()): ?>
            <li>
                <a href="<?php $category->permalink(); ?>">
                    <?php $category->name(); ?>
                    <span class="item-count">(<?php $category->count(); ?>)</span>
                </a>
            </li>
        <?php endwhile; ?>
    </ul>
</div>
<?php endif; ?>

<!-- 标签云 -->
<?php \Widget\Metas\Tag\Cloud::alloc()->to($tags); ?>
<?php if($tags->have()): ?>
<div class="sidebar-section">
    <h3 class="sidebar-title">标签</h3>
    <div class="article-tags">
        <?php while ($tags->next()): ?>
            <a href="<?php $tags->permalink(); ?>" class="tag"><?php $tags->name(); ?></a>
        <?php endwhile; ?>
    </div>
</div>
<?php endif; ?>
