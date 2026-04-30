<?php
/**
 * 404错误页面
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<div class="main-layout error-layout">
    <div class="main-content error-main">
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
</div>

<?php $this->need('footer.php'); ?>
