<?php
/**
 * 关于页面模板
 *
 * @package custom
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<div class="about-page">
    <!-- 左侧留白 -->
    <div class="layout-spacer-left"></div>

    <!-- 关于页主体 -->
    <div class="about-main">
        <!-- 顶部个人信息 -->
        <div class="about-header">
            <img src="<?php echo $this->options->aboutAvatar ?: 'https://gravatar.loli.net/avatar/default?s=240&d=mp'; ?>"
                 alt="<?php $this->author->screenName(); ?>"
                 class="about-avatar"
                 onerror="this.src='https://gravatar.loli.net/avatar/default?s=240&d=mp'">
            <h1 class="about-name"><?php $this->author->screenName(); ?></h1>
            <?php if ($this->options->aboutBio): ?>
                <p class="about-bio"><?php echo $this->options->aboutBio; ?></p>
            <?php endif; ?>
        </div>

        <!-- 页面内容 -->
        <div class="about-section">
            <h2 class="about-section-title"><?php $this->title(); ?></h2>
            <div class="about-content">
                <?php $this->content(); ?>
            </div>
        </div>

        <!-- 评论区 -->
        <?php if ($this->allow('comment')): ?>
        <div class="comments-wrapper">
            <?php $this->need('comments.php'); ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 右侧留白 -->
    <div class="layout-spacer-right"></div>
</div>

<?php $this->need('footer.php'); ?>
