<?php
/**
 * 友链
 *
 * @package custom
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
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
</style>

<?php $this->need('footer.php'); ?>