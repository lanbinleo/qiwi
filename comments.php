<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>

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
    <div id="<?php $this->respondId(); ?>" class="comment-respond">
        <h3 class="comment-respond-title">
            <?php $comments->cancelReply('取消回复'); ?>
            <?php _e('发表评论'); ?>
        </h3>

        <form method="post" action="<?php $this->commentUrl() ?>" id="comment-form" class="comment-form">
            <?php if ($this->user->hasLogin()): ?>
            <p class="logged-in-as">
                <?php _e('登录身份: '); ?>
                <a href="<?php $this->options->profileUrl(); ?>"><?php $this->user->screenName(); ?></a>
                <a href="<?php $this->options->logoutUrl(); ?>" title="退出登录"><?php _e('退出'); ?> »</a>
            </p>
            <?php else: ?>
            <div class="comment-form-fields">
                <div class="form-field">
                    <label for="author"><?php _e('称呼'); ?> *</label>
                    <input type="text" name="author" id="author" value="<?php $this->remember('author'); ?>" required />
                </div>
                <div class="form-field">
                    <label for="mail"><?php _e('Email'); ?> *</label>
                    <input type="email" name="mail" id="mail" value="<?php $this->remember('mail'); ?>" required />
                </div>
                <div class="form-field">
                    <label for="url"><?php _e('网站'); ?></label>
                    <input type="url" name="url" id="url" placeholder="http://" value="<?php $this->remember('url'); ?>" />
                </div>
            </div>
            <?php endif; ?>

            <div class="form-field">
                <label for="textarea"><?php _e('内容'); ?> *</label>
                <textarea rows="6" name="text" id="textarea" required><?php $this->remember('text'); ?></textarea>
            </div>

            <?php if ($this->options->enabledCaptcha): ?>
                <div class="captcha-script">
                    <div id="captcha"></div><?php Geetest_Plugin::commentCaptchaRender(); ?>
                    <script src="https://cdn.jsdelivr.net/npm/jquery@2.2.4/dist/jquery.min.js"></script>
                </div>
            <?php endif; ?>
            <br>

            <button type="submit" class="submit-button"><?php _e('提交评论'); ?></button>
        </form>
    </div>
    <?php else: ?>
    <p class="comments-closed"><?php _e('评论已关闭'); ?></p>
    <?php endif; ?>
</div>
