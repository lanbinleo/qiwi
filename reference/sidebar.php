<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>

<!-- Recent Posts Widget -->
<section class="widget">
    <h3 class="widget-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14,2 14,8 20,8"></polyline>
        </svg>
        最新文章
    </h3>
    <ul class="widget-list recent-posts">
        <?php \Widget\Contents\Post\Recent::alloc()->to($recent); ?>
        <?php while ($recent->next()): ?>
            <li class="recent-post-item">
                <a href="<?php $recent->permalink(); ?>" class="recent-post-link" title="<?php $recent->title(); ?>">
                    <span class="recent-post-title"><?php $recent->title(); ?></span>
                    <span class="recent-post-date"><?php $recent->date('m-d'); ?></span>
                </a>
            </li>
        <?php endwhile; ?>
    </ul>
</section>

<!-- Recent Comments Widget -->
<?php if (!empty($this->options->sidebarBlock) && in_array('ShowRecentComments', $this->options->sidebarBlock)) : ?>
<section class="widget">
    <h3 class="widget-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m3 21 1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"></path>
        </svg>
        最近评论
    </h3>
    <ul class="widget-list">
        <?php
        // 获取说说页面的cid - 支持多个可能的缩略名
        $possibleSlugs = ['talk', 'shuoshuo', 'moment', 'moments', '说说', 'tm']; // 添加可能的说说页面缩略名
        
        // 获取数据库实例
        $db = \Typecho\Db::get();
        $excludeCid = null;
        
        // 尝试查找说说页面
        foreach ($possibleSlugs as $slug) {
            try {
                $shuoshuoCid = $db->fetchObject($db->select('cid')->from('table.contents')->where('slug = ?', $slug));
                if ($shuoshuoCid && isset($shuoshuoCid->cid)) {
                    $excludeCid = $shuoshuoCid->cid;
                    break; // 找到了就停止查找
                }
            } catch (Exception $e) {
                // 继续查找下一个可能的slug
                continue;
            }
        }
        
        // 创建评论widget - 显示所有评论包括管理员的评论
        \Widget\Comments\Recent::alloc('pageSize=10&ignoreAuthor=0')->to($comments);
        ?>
        
        <?php $commentCount = 0; ?>
        <?php while ($comments->next()): ?>
            <?php
            // 只有当找到有效的说说页面cid时才进行过滤
            if ($excludeCid !== null && $comments->cid == $excludeCid) {
                continue;
            }
            
            // 限制显示数量
            if ($commentCount >= 5) {
                break;
            }
            $commentCount++;
            ?>
            <li class="comment-item">
                <div class="comment-meta">
                    <a href="<?php $comments->permalink(); ?>" class="comment-author">
                        <?php $comments->author(false); ?>
                    </a>
                    <span class="comment-date"><?php $comments->date('m-d'); ?></span>
                </div>
                <div class="comment-excerpt">
                    <?php $comments->excerpt(50, '...'); ?>
                </div>
            </li>
        <?php endwhile; ?>
        
        <?php if ($commentCount == 0): ?>
            <li class="no-comments">暂无评论</li>
        <?php endif; ?>
    </ul>
</section>
<?php endif; ?>


<!-- Categories Widget -->
<?php if (!empty($this->options->sidebarBlock) && in_array('ShowCategory', $this->options->sidebarBlock)) : ?>
<section class="widget">
    <h3 class="widget-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
        </svg>
        分类
    </h3>
    <div class="categories-list">
        <?php \Widget\Metas\Category\Rows::alloc()->listCategories('wrapClass=widget-list'); ?>
    </div>
</section>
<?php endif; ?>

<!-- Archive Widget -->
<?php if (!empty($this->options->sidebarBlock) && in_array('ShowArchive', $this->options->sidebarBlock)) : ?>
<section class="widget">
    <h3 class="widget-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="21,8 21,21 3,21 3,8"></polyline>
            <rect x="1" y="3" width="22" height="5"></rect>
            <line x1="10" y1="12" x2="14" y2="12"></line>
        </svg>
        归档
    </h3>
    <ul class="widget-list archive-list">
        <?php \Widget\Contents\Post\Date::alloc('type=month&format=Y年m月')
            ->parse('<li><a href="{permalink}" title="查看 {date} 的文章">{date}</a></li>'); ?>
    </ul>
</section>
<?php endif; ?>

<!-- Tags Cloud Widget -->
<?php \Widget\Metas\Tag\Cloud::alloc()->to($tags); ?>
<?php if($tags->have()): ?>
<section class="widget">
    <h3 class="widget-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
            <line x1="7" y1="7" x2="7.01" y2="7"></line>
        </svg>
        标签云
    </h3>
    <div class="tags-cloud">
        <?php while ($tags->next()): ?>
            <a href="<?php $tags->permalink(); ?>" class="tag-item" title="<?php $tags->count(); ?> 篇文章">
                <?php $tags->name(); ?>
            </a>
        <?php endwhile; ?>
    </div>
</section>
<?php endif; ?>


<!-- Meta Links Widget -->
<?php if (!empty($this->options->sidebarBlock) && in_array('ShowOther', $this->options->sidebarBlock)) : ?>
<section class="widget">
    <h3 class="widget-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
        </svg>
        功能链接
    </h3>
    <ul class="widget-list meta-links">
        <?php if ($this->user->hasLogin()): ?>
            <li>
                <a href="<?php $this->options->adminUrl(); ?>" target="_blank">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    进入后台 (<?php $this->user->screenName(); ?>)
                </a>
            </li>
            <li>
                <a href="<?php $this->options->logoutUrl(); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16,17 21,12 16,7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    退出登录
                </a>
            </li>
        <?php else: ?>
            <li>
                <a href="<?php $this->options->adminUrl('login.php'); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10,17 15,12 10,7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                    登录
                </a>
            </li>
        <?php endif; ?>
        <li>
            <a href="<?php $this->options->feedUrl(); ?>" target="_blank">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 11a9 9 0 0 1 9 9"></path>
                    <path d="M4 4a16 16 0 0 1 16 16"></path>
                    <circle cx="5" cy="19" r="1"></circle>
                </svg>
                文章 RSS
            </a>
        </li>
        <li>
            <a href="<?php $this->options->commentsFeedUrl(); ?>" target="_blank">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 11a9 9 0 0 1 9 9"></path>
                    <path d="M4 4a16 16 0 0 1 16 16"></path>
                    <circle cx="5" cy="19" r="1"></circle>
                </svg>
                评论 RSS
            </a>
        </li>
    </ul>
</section>
<?php endif; ?>
