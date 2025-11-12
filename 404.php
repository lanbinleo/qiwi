<?php
/**
 * 404错误页面
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<div class="main-layout">
    <!-- 左侧留白 -->
    <div class="layout-spacer-left"></div>

    <!-- 主要内容 -->
    <div class="main-content">
        <div class="error-page">
            <div class="error-content">
                <h1 class="error-code">404</h1>
                <h2 class="error-title">页面未找到</h2>
                <p class="error-message">你访问的页面不存在或已被删除</p>
                <div class="error-actions">
                    <a href="<?php $this->options->siteUrl(); ?>" class="submit-button">返回首页</a>
                </div>
            </div>
        </div>
    </div>

    <!-- 侧边栏 -->
    <aside class="sidebar">
        <?php $this->need('sidebar.php'); ?>
    </aside>

    <!-- 右侧留白 -->
    <div class="layout-spacer-right"></div>
</div>

<?php $this->need('footer.php'); ?>
