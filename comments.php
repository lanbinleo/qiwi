<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

if (!function_exists('qiwi_capture_remember')) {
    function qiwi_capture_remember($widget, $key) {
        return trim((string) $widget->remember($key, true));
    }
}
?>

<div id="comments" class="comments-section">
    <?php $this->comments()->to($comments); ?>

    <?php if ($comments->have()): ?>
    <h3 class="comments-title">
        <?php $this->commentsNum(_t('暂无评论'), _t('仅有一条评论'), _t('已有 %d 条评论')); ?>
    </h3>

    <div class="comment-list">
        <?php while ($comments->next()): ?>
        <div id="<?php $comments->theId(); ?>" class="comment-item">
            <div class="comment-avatar">
                <img src="https://gravatar.loli.net/avatar/<?php echo md5($comments->mail); ?>?s=64&d=mp"
                     alt="avatar">
            </div>
            <div class="comment-content">
                <div class="comment-meta">
                    <span class="comment-author"><?php $comments->author(); ?></span>
                    <span class="comment-date"><?php $comments->date('Y-m-d H:i'); ?></span>
                </div>
                <div class="comment-text">
                    <?php $comments->content(); ?>
                </div>
                <div class="comment-reply">
                    <?php $comments->reply('回复'); ?>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <?php $comments->pageNav('« 前一页', '后一页 »'); ?>
    <?php endif; ?>

    <?php if ($this->allow('comment')): ?>
    <?php
        $rememberAuthor = qiwi_capture_remember($this, 'author');
        $rememberMail = qiwi_capture_remember($this, 'mail');
        $rememberUrl = qiwi_capture_remember($this, 'url');
        $hasRememberedProfile = $rememberAuthor !== '' && $rememberMail !== '';
    ?>
    <div id="<?php $this->respondId(); ?>" class="comment-respond">
        <div class="comment-respond-header">
            <h3 class="comment-respond-title"><?php _e('发表评论'); ?></h3>
            <?php $comments->cancelReply('取消回复'); ?>
        </div>

        <form method="post" action="<?php $this->commentUrl() ?>" id="comment-form" class="comment-form">
            <?php if ($this->user->hasLogin()): ?>
            <p class="logged-in-as">
                <?php _e('登录身份: '); ?>
                <a href="<?php $this->options->profileUrl(); ?>"><?php $this->user->screenName(); ?></a>
                <a href="<?php $this->options->logoutUrl(); ?>" title="退出登录"><?php _e('退出'); ?> »</a>
            </p>
            <?php else: ?>
            <div class="comment-profile-modal" data-comment-profile-modal role="dialog" aria-modal="true" aria-labelledby="comment-profile-title">
                <button class="comment-profile-backdrop" type="button" data-comment-profile-close tabindex="-1" aria-label="关闭身份设置"></button>
                <div class="comment-profile-panel">
                    <div class="comment-profile-header">
                        <div>
                            <h4 id="comment-profile-title">评论身份</h4>
                            <p>保存后下次会自动带上，不用每次重新填写。</p>
                        </div>
                        <button class="comment-profile-close" type="button" data-comment-profile-close aria-label="关闭">×</button>
                    </div>
                    <div class="comment-form-fields">
                        <div class="form-field">
                            <label for="author"><?php _e('称呼'); ?> *</label>
                            <input type="text" name="author" id="author" value="<?php echo htmlspecialchars($rememberAuthor, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="name" required />
                        </div>
                        <div class="form-field">
                            <label for="mail"><?php _e('Email'); ?> *</label>
                            <input type="email" name="mail" id="mail" value="<?php echo htmlspecialchars($rememberMail, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="email" required />
                        </div>
                        <div class="form-field">
                            <label for="url"><?php _e('网站'); ?></label>
                            <input type="url" name="url" id="url" placeholder="https://" value="<?php echo htmlspecialchars($rememberUrl, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="url" />
                        </div>
                    </div>
                    <div class="comment-profile-actions">
                        <button type="button" class="submit-button comment-profile-save" data-comment-profile-save>保存身份</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="form-field comment-text-field">
                <label for="textarea"><?php _e('内容'); ?> *</label>
                <textarea rows="5" name="text" id="textarea" placeholder="写下你的想法…" required><?php $this->remember('text'); ?></textarea>
            </div>

            <div class="comment-form-footer" data-has-profile="<?php echo $hasRememberedProfile ? 'true' : 'false'; ?>">
                <?php if ($this->options->enabledCaptcha): ?>
                    <div class="captcha-script">
                        <div id="captcha"></div><?php Geetest_Plugin::commentCaptchaRender(); ?>
                        <script src="https://cdn.jsdelivr.net/npm/jquery@2.2.4/dist/jquery.min.js"></script>
                    </div>
                <?php endif; ?>
                <?php if (!$this->user->hasLogin()): ?>
                    <button type="button" class="comment-profile-toggle" data-comment-profile-toggle>
                        <span class="comment-identity-label">以</span>
                        <span class="comment-identity-value" data-comment-identity-label><?php echo $hasRememberedProfile ? htmlspecialchars($rememberAuthor, ENT_QUOTES, 'UTF-8') : '未设置'; ?></span>
                        <span class="comment-identity-label">的身份评论</span>
                    </button>
                <?php endif; ?>
            </div>
            <button type="submit" class="submit-button" id="sub_btn"><?php _e('提交评论'); ?></button>

        </form>
    </div>
    <?php else: ?>
    <p class="comments-closed"><?php _e('评论已关闭'); ?></p>
    <?php endif; ?>
</div>
