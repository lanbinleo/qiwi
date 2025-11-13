<?php
/**
 * 友链页面模板
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<div class="friends-page">
    <!-- 左侧留白 -->
    <div class="layout-spacer-left"></div>

    <!-- 主内容 -->
    <div class="friends-main">
        <!-- 页面头部 -->
        <header class="friends-header">
            <h1 class="page-title"><?php $this->title(); ?></h1>
            <?php if ($this->content()): ?>
                <div class="page-intro"><?php $this->content(); ?></div>
            <?php endif; ?>
        </header>

        <!-- 友链列表 -->
        <div class="friends-container">
            <?php
            $friendsJson = $this->options->friendsData;
            if ($friendsJson) {
                $friendsData = json_decode($friendsJson, true);
                if ($friendsData && is_array($friendsData)) {
                    foreach ($friendsData as $category => $friends) {
                        if (is_array($friends) && !empty($friends)):
            ?>
            <div class="friends-category">
                <h2 class="category-title"><?php echo htmlspecialchars($category); ?></h2>
                <div class="friends-grid">
                    <?php foreach ($friends as $friend): ?>
                    <a href="<?php echo htmlspecialchars($friend['url']); ?>"
                       target="_blank"
                       rel="noopener"
                       class="friend-card">
                        <div class="friend-avatar">
                            <img src="<?php echo htmlspecialchars($friend['avatar']); ?>"
                                 alt="<?php echo htmlspecialchars($friend['name']); ?>"
                                 onerror="this.src='https://gravatar.loli.net/avatar/default?s=96&d=mp'">
                        </div>
                        <div class="friend-info">
                            <h3 class="friend-name"><?php echo htmlspecialchars($friend['name']); ?></h3>
                            <p class="friend-desc"><?php echo htmlspecialchars($friend['description']); ?></p>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
                        endif;
                    }
                } else {
                    echo '<p class="no-friends">暂无友链数据，请在主题设置中配置友链信息。</p>';
                }
            } else {
                echo '<p class="no-friends">暂无友链数据，请在主题设置中配置友链信息。</p>';
            }
            ?>
        </div>

        <!-- 友链申请 -->
        <?php if ($this->allow('comment')): ?>
        <div class="friends-apply">
            <h3 class="apply-title">申请友链</h3>
            <p class="apply-desc">欢迎交换友链，请填写以下信息</p>

            <form method="post" action="<?php $this->commentUrl(); ?>" class="apply-form">
                <?php if (!$this->user->hasLogin()): ?>
                <div class="form-row">
                    <div class="form-field">
                        <label for="author">网站名称 *</label>
                        <input type="text" name="author" id="author" value="<?php $this->remember('author'); ?>" required />
                    </div>
                    <div class="form-field">
                        <label for="mail">邮箱 *</label>
                        <input type="email" name="mail" id="mail" value="<?php $this->remember('mail'); ?>" required />
                    </div>
                </div>
                <div class="form-field">
                    <label for="url">网站地址 *</label>
                    <input type="url" name="url" id="url" placeholder="https://" value="<?php $this->remember('url'); ?>" required />
                </div>
                <?php else: ?>
                <p class="logged-in-as">
                    登录身份: <a href="<?php $this->options->profileUrl(); ?>"><?php $this->user->screenName(); ?></a>
                </p>
                <?php endif; ?>

                <div class="form-field">
                    <label for="textarea">申请说明 *</label>
                    <textarea name="text" id="textarea" rows="4" placeholder="请提供网站描述、头像链接等信息..." required><?php $this->remember('text'); ?></textarea>
                </div>

                <?php
                $referer = $this->request->getReferer() ?? $this->request->getRequestUrl();
                $token = method_exists($this, 'security') ?
                    $this->security->getToken($this->permalink) :
                    $this->widget('Widget_Security')->getToken($this->permalink);
                echo '<input type="hidden" name="_" value="' . $token . '">';
                ?>

                <?php if ($this->options->enabledCaptcha): ?>
                <div class="captcha-script">
                    <div id="captcha"></div><?php Geetest_Plugin::commentCaptchaRender(); ?>
                    <script src="https://cdn.jsdelivr.net/npm/jquery@2.2.4/dist/jquery.min.js"></script>
                </div>
                <?php endif; ?>
                <br>

                <button type="submit" class="submit-button" id="sub_btn">提交申请</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- 右侧留白 -->
    <div class="layout-spacer-right"></div>
</div>

<?php $this->need('footer.php'); ?>
