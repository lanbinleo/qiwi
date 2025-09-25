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
                                                    <img src="<?php echo htmlspecialchars($friend['avatar']); ?>" alt="<?php echo htmlspecialchars($friend['name']); ?>" onerror="this.src='">
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