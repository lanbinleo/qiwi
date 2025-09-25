<?php
/**
 * 友链
 *
 * @package custom
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');

// 检查是否有评论提交
$commentSubmitted = false;
$submissionError = null;
if (isset($_POST['text']) && !empty($_POST['text'])) {
    $commentSubmitted = true;
}

// 获取当前页面ID用于评论查询
$pageId = $this->cid;
$pageSize = 10; // 每页显示的评论数量
$currentPage = isset($_GET['comment_page']) ? max(1, intval($_GET['comment_page'])) : 1;

// 获取当前页面作者信息
$authorUid = $this->author->uid;
$authorName = $this->author->screenName;
$authorMail = $this->author->mail;
$authorUrl = $this->author->url;

// 获取数据库实例
if (class_exists('Typecho_Db')) {
    $db = Typecho_Db::get();
    $prefix = $db->getPrefix();
} else {
    $db = \Typecho\Db::get();
    $prefix = $db->getPrefix();
}

// 获取该页面的评论
$select = $db->select()->from($prefix.'comments')
    ->where('cid = ?', $pageId)
    ->where('status = ?', 'approved')
    ->order('created', $db::SORT_DESC)
    ->page($currentPage, $pageSize);

$comments = $db->fetchAll($select);

// 获取总数
$totalSelect = $db->select('COUNT(coid) AS total')->from($prefix.'comments')
    ->where('cid = ?', $pageId)
    ->where('status = ?', 'approved');

$totalResult = $db->fetchRow($totalSelect);
$total = $totalResult ? $totalResult['total'] : 0;
$totalPages = ceil($total / $pageSize);
?>

<!-- 友链页面样式覆盖，确保无侧边栏 -->
<!-- <style>
/* 友链页面特殊样式 */
body.friends-page {
    background: var(--color-bg-primary);
}

body.friends-page .main-wrapper {
    display: block;
    max-width: 100%;
}

body.friends-page .content-area {
    max-width: 100%;
    margin: 0;
    padding: 0;
}

body.friends-page .sidebar {
    display: none !important;
}

body.friends-page .site-main {
    padding: 0;
    margin: 0;
    max-width: 100%;
}

body.friends-page .container {
    max-width: var(--container-max-width);
    margin: 0 auto;
    padding: 0 var(--spacing-md);
}
</style> -->

<div class="friends-page">
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="8.5" cy="7" r="4"></circle>
                    <line x1="20" y1="8" x2="20" y2="14"></line>
                    <line x1="23" y1="11" x2="17" y2="11"></line>
                </svg>
                友情链接
            </h1>
            <p class="page-description">感谢这些优秀的朋友们，让我们一起构建更美好的网络世界</p>
        </div>

        <div class="friends-container">
            <?php
            $friendsJson = $this->options->friendsData;
            if ($friendsJson) {
                $friendsData = json_decode($friendsJson, true);
                if ($friendsData && is_array($friendsData)) {
                    foreach ($friendsData as $category => $friends) {
                        if (is_array($friends) && !empty($friends)) {
                            ?>
                            <div class="friends-category">
                                <div class="category-header">
                                    <h2 class="category-title">
                                        <?php
                                        $categoryIcons = [
                                            '技术' => '💻',
                                            '推荐' => '⭐',
                                            '生活' => '🌟',
                                            '博客' => '📝',
                                            '设计' => '🎨',
                                            '其他' => '🔗'
                                        ];
                                        $icon = isset($categoryIcons[$category]) ? $categoryIcons[$category] : '🔗';
                                        echo $icon . ' ' . htmlspecialchars($category);
                                        ?>
                                    </h2>
                                    <div class="category-count">
                                        <span class="count-number"><?php echo count($friends); ?></span>
                                    </div>
                                </div>

                                <div class="friends-grid">
                                    <?php foreach ($friends as $friend): ?>
                                        <div class="friend-card" onclick="window.open('<?php echo htmlspecialchars($friend['url']); ?>', '_blank')">
                                            <?php if (isset($friend['tags']) && is_array($friend['tags']) && !empty($friend['tags'])): ?>
                                                <div class="friend-tag-badge" style="background-color: <?php echo htmlspecialchars($friend['tags'][0]['color']); ?>; color: white;">
                                                    <?php echo htmlspecialchars($friend['tags'][0]['name']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="friend-content">
                                                <div class="friend-avatar">
                                                    <img src="<?php echo htmlspecialchars($friend['avatar']); ?>" alt="<?php echo htmlspecialchars($friend['name']); ?>" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSJjdXJyZW50Q29sb3IiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj4KPGNpcmNsZSBjeD0iMTIiIGN5PSIxMiIgcj0iMTAiPjwvY2lyY2xlPgo8Y2lyY2xlIGN4PSIxMiIgY3k9IjgiIHI9IjQiPjwvY2lyY2xlPgo8cGF0aCBkPSIxNiAyMXYtMmE0IDQgMCAwIDAtNC00SDVhNCA0IDAgMCAwLTQgNHYyIj48L3BhdGg+Cjwvc3ZnPg==';">
                                                </div>
                                                <div class="friend-info">
                                                    <h3 class="friend-name"><?php echo htmlspecialchars($friend['name']); ?></h3>
                                                    <p class="friend-description"><?php echo htmlspecialchars($friend['description']); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php
                        }
                    }
                } else {
                    echo '<div class="no-friends"><p>暂无友链数据，请在主题设置中配置友链信息。</p></div>';
                }
            } else {
                echo '<div class="no-friends"><p>暂无友链数据，请在主题设置中配置友链信息。</p></div>';
            }
            ?>
        </div>

        <!-- 友链申请评论区域 -->
        <div class="friends-comments-section">
            <div class="comments-header">
                <h3 class="comments-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    友链申请 & 评论
                </h3>
                <p class="comments-subtitle">欢迎申请友链或留下您的宝贵意见</p>
            </div>

            <?php if ($total > 0): ?>
            <div class="comments-list">
                <div class="comments-count">
                    <span class="count-number"><?php echo $total; ?></span>
                    <span class="count-label">条评论</span>
                </div>

                <?php foreach ($comments as $comment): ?>
                <div class="comment-item">
                    <div class="comment-header">
                        <div class="comment-author">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <span class="author-name"><?php echo htmlspecialchars($comment['author'], ENT_QUOTES); ?></span>
                            <?php if (!empty($comment['url'])): ?>
                            <a href="<?php echo htmlspecialchars($comment['url'], ENT_QUOTES); ?>" target="_blank" rel="noopener noreferrer" class="author-link">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15,3 21,3 21,9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>
                            </a>
                            <?php endif; ?>
                        </div>
                        <div class="comment-time">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12,6 12,12 16,14"></polyline>
                            </svg>
                            <?php echo date('Y年m月d日 H:i', $comment['created']); ?>
                        </div>
                    </div>
                    <div class="comment-content">
                        <?php echo nl2br(htmlspecialchars($comment['text'], ENT_QUOTES)); ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- 评论分页 -->
                <?php if ($totalPages > 1): ?>
                <div class="comments-pagination">
                    <div class="pagination-nav">
                        <?php
                        $baseUrl = $this->permalink;

                        // 上一页
                        if ($currentPage > 1): ?>
                        <a href="<?php echo $baseUrl . '?comment_page=' . ($currentPage - 1); ?>" class="pagination-btn prev-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="15,18 9,12 15,6"></polyline>
                            </svg>
                            上一页
                        </a>
                        <?php else: ?>
                        <span class="pagination-btn prev-btn disabled">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="15,18 9,12 15,6"></polyline>
                            </svg>
                            上一页
                        </span>
                        <?php endif; ?>

                        <!-- 页码列表 -->
                        <div class="pagination-numbers">
                            <?php
                            // 计算显示的页码范围
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($totalPages, $currentPage + 2);

                            // 如果是前几页，显示更多后面的页码
                            if ($currentPage <= 3) {
                                $endPage = min($totalPages, 5);
                            }

                            // 如果是后几页，显示更多前面的页码
                            if ($currentPage > $totalPages - 3) {
                                $startPage = max(1, $totalPages - 4);
                            }

                            // 显示第一页
                            if ($startPage > 1): ?>
                                <a href="<?php echo $baseUrl . '?comment_page=1'; ?>" class="pagination-number">1</a>
                                <?php if ($startPage > 2): ?>
                                    <span class="pagination-ellipsis">...</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- 显示页码范围 -->
                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <?php if ($i == $currentPage): ?>
                                    <span class="pagination-number current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="<?php echo $baseUrl . '?comment_page=' . $i; ?>" class="pagination-number"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <!-- 显示最后一页 -->
                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <span class="pagination-ellipsis">...</span>
                                <?php endif; ?>
                                <a href="<?php echo $baseUrl . '?comment_page=' . $totalPages; ?>" class="pagination-number"><?php echo $totalPages; ?></a>
                            <?php endif; ?>
                        </div>

                        <!-- 下一页 -->
                        <?php if ($currentPage < $totalPages): ?>
                        <a href="<?php echo $baseUrl . '?comment_page=' . ($currentPage + 1); ?>" class="pagination-btn next-btn">
                            下一页
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9,18 15,12 9,6"></polyline>
                            </svg>
                        </a>
                        <?php else: ?>
                        <span class="pagination-btn next-btn disabled">
                            下一页
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9,18 15,12 9,6"></polyline>
                            </svg>
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="pagination-info">
                        第 <?php echo $currentPage; ?> 页 / 共 <?php echo $totalPages; ?> 页 (共 <?php echo $total; ?> 条评论)
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- 友链申请表单 -->
            <?php if ($this->allow('comment')): ?>
            <div class="friends-comment-form">
                <div class="form-header">
                    <h4>申请友链或留言</h4>
                    <p>请填写您的网站信息，我们会在审核后添加您的友链</p>
                </div>

                <form method="post" action="<?php $this->commentUrl() ?>" class="comment-form" role="form">
                    <?php if ($this->user->hasLogin()): ?>
                        <p class="login-info">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            登录身份: <a href="<?php $this->options->profileUrl(); ?>"><?php $this->user->screenName(); ?></a>
                        </p>
                    <?php else: ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="author" class="required">网站名称 *</label>
                                <input type="text" name="author" id="author" class="text" value="<?php $this->remember('author'); ?>" required placeholder="请输入您的网站名称"/>
                            </div>
                            <div class="form-group">
                                <label for="mail" class="required">邮箱 *</label>
                                <input type="email" name="mail" id="mail" class="text" value="<?php $this->remember('mail'); ?>" required placeholder="用于接收审核通知"/>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="url" class="required">网站地址 *</label>
                                <input type="url" name="url" id="url" class="text" placeholder="https://" value="<?php $this->remember('url'); ?>" required/>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="textarea" class="required">申请说明 *</label>
                        <textarea rows="6" cols="50" name="text" id="textarea" class="textarea" required placeholder="申请友链请输入网站标题、描述和你的头像，感谢！"><?php $this->remember('text'); ?></textarea>
                    </div>

                    <?php
                    // 修复: 使用一致的referer策略
                    $referer_source = $this->request->getReferer() ?? $this->request->getRequestUrl();

                    $token = '';
                    if (class_exists('Typecho_Widget_Helper_Form_Element_Hidden')) {
                        $security = new Typecho_Widget_Helper_Form_Element_Hidden('_');
                        $token = $this->security->getToken($referer_source);
                        $security->value($token);
                        echo '<input type="hidden" name="_" value="' . $security->value . '">';
                    } else if (method_exists($this, 'security')) {
                        $token = $this->security->getToken($referer_source);
                        echo '<input type="hidden" name="_" value="' . $token . '">';
                    } else {
                        $widget = $this->widget('Widget_Security');
                        $token = $widget->getToken($referer_source);
                        echo '<input type="hidden" name="_" value="' . $token . '">';
                    }
                    ?>

                    <div class="form-actions">
                        <button type="submit" class="submit-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                <polygon points="22,2 15,22 11,13 2,9 22,2"></polygon>
                            </svg>
                            提交申请
                        </button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="comment-closed-notice">
                <p>评论功能已关闭，无法提交友链申请。</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.friends-page {
    min-height: calc(100vh - 200px);
    padding: var(--spacing-xl) 0;
}

.friends-page .page-header {
    text-align: center;
    margin-bottom: var(--spacing-xxl);
    padding-bottom: var(--spacing-xl);
    border-bottom: 1px solid var(--color-border);
}

.friends-page .page-title {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-sm);
    font-size: 2.5rem;
    margin-bottom: var(--spacing-md);
    color: var(--color-text-primary);
}

.friends-page .page-title svg {
    color: var(--color-accent);
    flex-shrink: 0;
}

.friends-page .page-description {
    font-size: var(--font-size-lg);
    color: var(--color-text-secondary);
    max-width: 600px;
    margin: 0 auto;
    line-height: var(--line-height-base);
}

.friends-container {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xxl);
}

.friends-category {
    background-color: var(--color-bg-secondary);
    border-radius: var(--border-radius-lg);
    padding: var(--spacing-xl);
    border: 1px solid var(--color-border);
    transition: all 0.3s ease;
}

.friends-category:hover {
    border-color: var(--color-accent);
    box-shadow: var(--shadow-md);
}

.category-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--spacing-xl);
    padding-bottom: var(--spacing-md);
    border-bottom: 2px solid var(--color-accent);
}

.category-title {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    font-size: 1.5rem;
    color: var(--color-text-primary);
    margin: 0;
}

.category-count {
    background: linear-gradient(135deg, var(--color-accent) 0%, var(--color-accent-hover) 100%);
    color: var(--color-bg-primary);
    padding: var(--spacing-xs) var(--spacing-sm);
    border-radius: var(--border-radius-lg);
    font-size: var(--font-size-sm);
    font-weight: 600;
}

.count-number {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
}

.friends-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: var(--spacing-lg);
}

.friend-card {
    background-color: var(--color-bg-tertiary);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-md);
    padding: var(--spacing-lg);
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    display: flex;
    flex-direction: column;
    min-height: 160px;
}

.friend-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: var(--color-accent);
    background-color: var(--color-bg-secondary);
}

.friend-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
    margin-bottom: var(--spacing-md);
    flex-shrink: 0;
    border: 3px solid var(--color-accent);
    transition: all 0.3s ease;
}

.friend-card:hover .friend-avatar {
    transform: scale(1.1);
    border-color: var(--color-accent-hover);
}

.friend-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: all 0.3s ease;
}

.friend-info {
    flex: 1;
    margin-bottom: var(--spacing-md);
}

.friend-name {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--color-text-primary);
    margin: 0 0 var(--spacing-xs) 0;
    transition: color 0.3s ease;
}

.friend-card:hover .friend-name {
    color: var(--color-accent);
}

.friend-description {
    color: var(--color-text-secondary);
    font-size: var(--font-size-sm);
    line-height: var(--line-height-base);
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.friend-tags {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-xs);
    margin-top: auto;
}

.friend-tag {
    padding: 4px 8px;
    border-radius: var(--border-radius-sm);
    font-size: var(--font-size-xs);
    font-weight: 500;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.friend-tag:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-sm);
}

.no-friends {
    text-align: center;
    padding: var(--spacing-xxl);
    color: var(--color-text-muted);
    background-color: var(--color-bg-secondary);
    border-radius: var(--border-radius-lg);
    border: 2px dashed var(--color-border);
}

.no-friends p {
    margin: 0;
    font-size: var(--font-size-lg);
}

/* 响应式设计 */
@media (max-width: 768px) {
    .friends-page {
        padding: var(--spacing-lg) 0;
    }

    .friends-page .page-title {
        font-size: 2rem;
        flex-direction: column;
        gap: var(--spacing-xs);
    }

    .friends-page .page-description {
        font-size: var(--font-size-base);
    }

    .friends-container {
        gap: var(--spacing-xl);
    }

    .friends-category {
        padding: var(--spacing-lg);
    }

    .category-header {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--spacing-sm);
    }

    .category-title {
        font-size: 1.25rem;
    }

    .friends-grid {
        grid-template-columns: 1fr;
        gap: var(--spacing-md);
    }

    .friend-card {
        padding: var(--spacing-md);
        min-height: 140px;
    }

    .friend-avatar {
        width: 50px;
        height: 50px;
        margin-bottom: var(--spacing-sm);
    }
}

@media (max-width: 480px) {
    .friends-page .page-title {
        font-size: 1.75rem;
    }

    .friends-category {
        padding: var(--spacing-md);
    }

    .friend-card {
        padding: var(--spacing-sm);
    }

    .friend-name {
        font-size: 1.125rem;
    }

    .friend-description {
        font-size: var(--font-size-xs);
    }
}

/* ===== 友链评论样式 ===== */
.friends-comments-section {
    background: rgba(22, 22, 22, 0.8);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(217, 159, 0, 0.1);
    border-radius: var(--border-radius-lg);
    padding: var(--spacing-xl);
    margin-top: var(--spacing-xxl);
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
}

.friends-comments-section:hover {
    border-color: var(--color-accent);
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.comments-header {
    text-align: center;
    margin-bottom: var(--spacing-xl);
    padding-bottom: var(--spacing-lg);
    border-bottom: 2px solid var(--color-accent);
}

.comments-title {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-sm);
    font-size: 1.5rem;
    margin-bottom: var(--spacing-sm);
    color: var(--color-text-primary);
    font-weight: 600;
}

.comments-title svg {
    color: var(--color-accent);
    flex-shrink: 0;
}

.comments-subtitle {
    font-size: var(--font-size-sm);
    color: var(--color-text-secondary);
    margin: 0;
    opacity: 0.9;
}

/* 评论列表样式 */
.comments-list {
    margin-bottom: var(--spacing-xl);
}

.comments-count {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-xs);
    background: linear-gradient(135deg, var(--color-accent) 0%, var(--color-accent-hover) 100%);
    color: var(--color-bg-primary);
    padding: var(--spacing-xs) var(--spacing-md);
    border-radius: var(--border-radius-lg);
    font-size: var(--font-size-sm);
    font-weight: 600;
    margin-bottom: var(--spacing-lg);
    box-shadow: var(--shadow-sm);
}

.count-number {
    font-size: 1.2rem;
}

.count-label {
    opacity: 0.9;
}

/* 评论项样式 */
.comment-item {
    background: linear-gradient(135deg, #0a0a0a 0%, #161616 100%);
    border: 1px solid rgba(217, 159, 0, 0.2);
    border-radius: var(--border-radius-md);
    padding: var(--spacing-lg);
    margin-bottom: var(--spacing-md);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.comment-item:hover {
    border-color: var(--color-accent);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.comment-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(217, 159, 0, 0.05) 0%, rgba(217, 159, 0, 0.02) 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.comment-item:hover::before {
    opacity: 1;
}

.comment-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--spacing-md);
    flex-wrap: wrap;
    gap: var(--spacing-sm);
}

.comment-author {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    color: var(--color-accent);
    font-weight: 600;
    font-size: var(--font-size-base);
}

.author-name {
    color: var(--color-accent);
}

.author-link {
    color: var(--color-text-muted);
    transition: color 0.2s ease;
}

.author-link:hover {
    color: var(--color-accent);
}

.comment-time {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    color: var(--color-text-muted);
    font-size: var(--font-size-xs);
    opacity: 0.8;
}

.comment-content {
    color: var(--color-text-secondary);
    line-height: var(--line-height-base);
    font-size: var(--font-size-base);
    word-wrap: break-word;
    padding-left: var(--spacing-sm);
    border-left: 3px solid var(--color-accent);
    background: rgba(217, 159, 0, 0.05);
    padding: var(--spacing-md);
    border-radius: var(--border-radius-sm);
}

/* 评论表单样式 */
.friends-comment-form {
    background: linear-gradient(135deg, #0a0a0a 0%, #161616 100%);
    border: 2px dashed var(--color-accent);
    border-radius: var(--border-radius-lg);
    padding: var(--spacing-xl);
    transition: all 0.3s ease;
}

.friends-comment-form:hover {
    border-color: var(--color-accent-hover);
    box-shadow: var(--shadow-md);
}

.form-header {
    margin-bottom: var(--spacing-lg);
    text-align: center;
}

.form-header h4 {
    color: var(--color-text-primary);
    font-size: 1.25rem;
    margin-bottom: var(--spacing-xs);
    font-weight: 600;
}

.form-header p {
    color: var(--color-text-muted);
    font-size: var(--font-size-sm);
    margin: 0;
}

.comment-form {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spacing-md);
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.form-group label {
    color: var(--color-text-secondary);
    font-weight: 500;
    font-size: var(--font-size-sm);
}

.form-group label.required::after {
    content: " *";
    color: var(--color-accent);
}

.form-group input,
.form-group textarea {
    padding: var(--spacing-sm) var(--spacing-md);
    background-color: var(--color-bg-secondary);
    border: 2px solid var(--color-border);
    border-radius: var(--border-radius-md);
    color: var(--color-text-primary);
    font-family: var(--font-family-base);
    font-size: var(--font-size-base);
    transition: all 0.2s ease;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--color-accent);
    box-shadow: 0 0 0 3px rgba(217, 159, 0, 0.1);
    background-color: var(--color-bg-primary);
}

.form-group textarea {
    resize: vertical;
    min-height: 120px;
    line-height: var(--line-height-base);
}

.form-group input::placeholder,
.form-group textarea::placeholder {
    color: var(--color-text-muted);
    opacity: 0.7;
}

.login-info {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    color: var(--color-text-secondary);
    font-size: var(--font-size-sm);
    background: var(--color-bg-secondary);
    padding: var(--spacing-sm) var(--spacing-md);
    border-radius: var(--border-radius-md);
    border: 1px solid var(--color-border);
}

.login-info a {
    color: var(--color-accent);
    text-decoration: none;
}

.login-info a:hover {
    text-decoration: underline;
}

.form-actions {
    display: flex;
    justify-content: center;
    margin-top: var(--spacing-md);
}

.submit-btn {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    background: linear-gradient(135deg, var(--color-accent) 0%, var(--color-accent-hover) 100%);
    color: var(--color-bg-primary);
    border: none;
    padding: var(--spacing-md) var(--spacing-xl);
    border-radius: var(--border-radius-md);
    cursor: pointer;
    font-weight: 600;
    font-size: var(--font-size-base);
    transition: all 0.2s ease;
    box-shadow: var(--shadow-sm);
    min-width: 150px;
    justify-content: center;
}

.submit-btn:hover {
    background: linear-gradient(135deg, var(--color-accent-hover) 0%, var(--color-accent) 100%);
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

.submit-btn:active {
    transform: translateY(0);
}

/* 评论分页样式 */
.comments-pagination {
    margin-top: var(--spacing-xl);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--spacing-md);
}

.comments-pagination .pagination-nav {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-sm);
    flex-wrap: wrap;
}

.comments-pagination .pagination-btn {
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-xs);
    padding: var(--spacing-sm) var(--spacing-md);
    background-color: var(--color-bg-secondary);
    color: var(--color-text-secondary);
    border: 2px solid var(--color-border);
    border-radius: var(--border-radius-md);
    text-decoration: none;
    font-weight: 500;
    font-size: var(--font-size-sm);
    transition: all 0.2s ease;
    cursor: pointer;
    min-width: 100px;
    justify-content: center;
}

.comments-pagination .pagination-btn:hover {
    background-color: var(--color-accent);
    color: var(--color-bg-primary);
    border-color: var(--color-accent);
    text-decoration: none;
    transform: translateY(-1px);
    box-shadow: var(--shadow-sm);
}

.comments-pagination .pagination-btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
    background-color: var(--color-bg-tertiary);
    color: var(--color-text-muted);
}

.comments-pagination .pagination-numbers {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    margin: 0 var(--spacing-sm);
}

.comments-pagination .pagination-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background-color: var(--color-bg-secondary);
    color: var(--color-text-secondary);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-sm);
    text-decoration: none;
    font-weight: 500;
    font-size: var(--font-size-sm);
    transition: all 0.2s ease;
    cursor: pointer;
}

.comments-pagination .pagination-number:hover {
    background-color: var(--color-bg-tertiary);
    color: var(--color-text-primary);
    border-color: var(--color-accent);
    text-decoration: none;
    transform: translateY(-1px);
}

.comments-pagination .pagination-number.current {
    background-color: var(--color-accent);
    color: var(--color-bg-primary);
    border-color: var(--color-accent);
    cursor: default;
    transform: none;
    box-shadow: var(--shadow-sm);
}

.comments-pagination .pagination-ellipsis {
    color: var(--color-text-muted);
    font-weight: 500;
    font-size: var(--font-size-sm);
    padding: 0 var(--spacing-xs);
}

.comments-pagination .pagination-info {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
    background-color: var(--color-bg-secondary);
    padding: var(--spacing-xs) var(--spacing-sm);
    border-radius: var(--border-radius-sm);
    border: 1px solid var(--color-border);
    margin-top: var(--spacing-sm);
}

/* 评论关闭提示 */
.comment-closed-notice {
    background-color: var(--color-bg-secondary);
    border: 2px dashed var(--color-accent);
    border-radius: var(--border-radius-md);
    padding: var(--spacing-lg);
    text-align: center;
    margin-top: var(--spacing-lg);
}

.comment-closed-notice p {
    color: var(--color-text-secondary);
    margin: 0;
    font-size: var(--font-size-sm);
}

/* 响应式设计 */
@media (max-width: 768px) {
    .friends-comments-section {
        padding: var(--spacing-lg);
        margin-top: var(--spacing-xl);
    }

    .comments-title {
        font-size: 1.25rem;
        flex-direction: column;
        gap: var(--spacing-xs);
    }

    .comments-subtitle {
        font-size: var(--font-size-xs);
    }

    .comment-item {
        padding: var(--spacing-md);
    }

    .comment-header {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--spacing-xs);
    }

    .comment-content {
        font-size: var(--font-size-sm);
        padding: var(--spacing-sm);
    }

    .friends-comment-form {
        padding: var(--spacing-lg);
    }

    .form-row {
        grid-template-columns: 1fr;
        gap: var(--spacing-sm);
    }

    .form-header h4 {
        font-size: 1.125rem;
    }

    .form-header p {
        font-size: var(--font-size-xs);
    }

    .submit-btn {
        width: 100%;
        justify-content: center;
    }

    .comments-pagination .pagination-nav {
        flex-wrap: wrap;
        gap: var(--spacing-xs);
    }

    .comments-pagination .pagination-btn {
        min-width: 80px;
        padding: var(--spacing-xs) var(--spacing-sm);
        font-size: var(--font-size-xs);
    }

    .comments-pagination .pagination-number {
        width: 32px;
        height: 32px;
        font-size: var(--font-size-xs);
    }
}

@media (max-width: 480px) {
    .friends-comments-section {
        padding: var(--spacing-md);
    }

    .comments-title {
        font-size: 1.125rem;
    }

    .comment-item {
        padding: var(--spacing-sm);
    }

    .comment-author {
        font-size: var(--font-size-sm);
    }

    .comment-time {
        font-size: 10px;
    }

    .friends-comment-form {
        padding: var(--spacing-md);
    }

    .form-header h4 {
        font-size: 1rem;
    }

    .form-group input,
    .form-group textarea {
        padding: var(--spacing-xs) var(--spacing-sm);
        font-size: var(--font-size-sm);
    }

    .submit-btn {
        padding: var(--spacing-sm) var(--spacing-md);
        font-size: var(--font-size-sm);
    }

    .comments-pagination .pagination-btn {
        min-width: 60px;
        padding: 4px var(--spacing-xs);
        font-size: 10px;
    }

    .comments-pagination .pagination-number {
        width: 28px;
        height: 28px;
        font-size: 10px;
    }
}
</style>

<script>
// 友链评论提交处理
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($commentSubmitted): ?>
    // 显示提交成功消息
    const successMessage = document.createElement('div');
    successMessage.className = 'comment-success-message';
    successMessage.innerHTML = `
        <div class="success-content">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22,4 12,14.01 9,11.01"></polyline>
            </svg>
            <div class="success-text">
                <h4>提交成功！</h4>
                <p>您的友链申请已提交，我们会在审核后尽快处理。</p>
            </div>
        </div>
    `;

    // 添加样式
    successMessage.style.cssText = `
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: linear-gradient(135deg, var(--color-bg-secondary) 0%, var(--color-bg-tertiary) 100%);
        border: 2px solid var(--color-accent);
        border-radius: var(--border-radius-lg);
        padding: var(--spacing-xl);
        box-shadow: var(--shadow-lg);
        z-index: 9999;
        text-align: center;
        max-width: 400px;
        width: 90%;
        animation: successSlideIn 0.3s ease-out;
    `;

    const successContent = successMessage.querySelector('.success-content');
    if (successContent) {
        successContent.style.cssText = `
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        `;
    }

    const successText = successMessage.querySelector('.success-text');
    if (successText) {
        successText.style.cssText = `
            flex: 1;
            text-align: left;
        `;
    }

    const successTitle = successMessage.querySelector('h4');
    if (successTitle) {
        successTitle.style.cssText = `
            color: var(--color-text-primary);
            font-size: 1.25rem;
            margin: 0 0 var(--spacing-xs) 0;
            font-weight: 600;
        `;
    }

    const successDesc = successMessage.querySelector('p');
    if (successDesc) {
        successDesc.style.cssText = `
            color: var(--color-text-secondary);
            font-size: var(--font-size-sm);
            margin: 0;
            line-height: var(--line-height-base);
        `;
    }

    const successIcon = successMessage.querySelector('svg');
    if (successIcon) {
        successIcon.style.cssText = `
            color: var(--color-accent);
            flex-shrink: 0;
        `;
    }

    // 添加动画
    const style = document.createElement('style');
    style.textContent = `
        @keyframes successSlideIn {
            from {
                opacity: 0;
                transform: translate(-50%, -60%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }
    `;
    document.head.appendChild(style);

    document.body.appendChild(successMessage);

    // 3秒后自动关闭并刷新页面
    setTimeout(() => {
        successMessage.style.animation = 'successSlideOut 0.3s ease-in forwards';
        style.textContent += `
            @keyframes successSlideOut {
                from {
                    opacity: 1;
                    transform: translate(-50%, -50%);
                }
                to {
                    opacity: 0;
                    transform: translate(-50%, -40%);
                }
            }
        `;

        setTimeout(() => {
            if (successMessage.parentNode) {
                successMessage.parentNode.removeChild(successMessage);
            }
            // 刷新页面
            window.location.reload();
        }, 300);
    }, 3000);

    <?php endif; ?>
});
</script>

<?php $this->need('footer.php'); ?>