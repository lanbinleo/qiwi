<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

if (!function_exists('qiwi_capture_remember')) {
    function qiwi_capture_remember($widget, $key) {
        return trim((string) $widget->remember($key, true));
    }
}

if (!function_exists('qiwi_get_comment_parent_info')) {
    function qiwi_get_comment_parent_info($comments) {
        $parentId = isset($comments->parent) ? (int) $comments->parent : 0;

        if ($parentId <= 0) {
            return null;
        }

        static $parentInfo = [];
        if (array_key_exists($parentId, $parentInfo)) {
            return $parentInfo[$parentId];
        }

        $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
        $row = $db->fetchRow($db->select('coid', 'type', 'author')->from('table.comments')->where('coid = ?', $parentId)->limit(1));
        $parentInfo[$parentId] = !empty($row['author']) ? [
            'author' => (string) $row['author'],
            'anchor' => '#' . (!empty($row['type']) ? (string) $row['type'] : 'comment') . '-' . (int) $row['coid'],
        ] : null;

        return $parentInfo[$parentId];
    }
}

if (!function_exists('threadedComments')) {
    function threadedComments($comments, $singleCommentOptions) {
        $commentStatus = isset($comments->status) ? (string) $comments->status : '';
        $isWaitingComment = $commentStatus === 'waiting';
        $commentCreated = isset($comments->created) ? (int) $comments->created : 0;
        $commentClasses = 'comment-item';
        $commentLevel = isset($comments->levels) ? (int) $comments->levels : 0;
        $commentCoid = isset($comments->coid) ? (int) $comments->coid : 0;
        $commentText = isset($comments->text) ? (string) $comments->text : '';
        $isTrustedComment = isset($comments->authorId) && (int) $comments->authorId > 0;
        $canCopyCommentLink = function_exists('qiwiUserHasLogin') && qiwiUserHasLogin() && $commentCoid > 0;
        $parentInfo = qiwi_get_comment_parent_info($comments);
        $commentLocation = function_exists('qiwiGetCommentLocationLabel') ? qiwiGetCommentLocationLabel($comments) : '';
        $avatarUrl = function_exists('qiwiGetCommentAvatarUrl')
            ? qiwiGetCommentAvatarUrl(isset($comments->mail) ? $comments->mail : '', 40)
            : 'https://gravatar.loli.net/avatar/' . md5(isset($comments->mail) ? $comments->mail : '') . '?s=40&d=mp';

        if ($isWaitingComment) {
            $commentClasses .= ' is-waiting';
        }

        if ($isTrustedComment) {
            $commentClasses .= ' is-trusted-comment';
        }

        if ($commentLevel > 0) {
            $commentClasses .= ' comment-child';
        }

        if ($commentLevel > 1) {
            $commentClasses .= ' comment-child-flattened';
        }
        ?>
        <div id="<?php $comments->theId(); ?>" class="<?php echo $commentClasses; ?>">
            <div class="comment-main">
                <div class="comment-avatar">
                    <img src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="avatar">
                </div>
                <div class="comment-content">
                    <div class="comment-author-line">
                        <span class="comment-author-row">
                            <span class="comment-author"><?php $comments->author(); ?></span>
                            <?php if (!empty($parentInfo['author'])): ?>
                            <span class="comment-reply-separator" aria-hidden="true">&gt;</span>
                            <a class="comment-reply-target" href="<?php echo htmlspecialchars($parentInfo['anchor'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($parentInfo['author'], ENT_QUOTES, 'UTF-8'); ?></a>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="comment-text">
                        <?php echo $isTrustedComment && function_exists('qiwiRenderTrustedCommentContent') ? qiwiRenderTrustedCommentContent($commentText) : qiwiRenderPlainCommentContent($commentText); ?>
                    </div>
                    <div class="comment-footnote">
                        <time class="comment-date"
                              datetime="<?php echo htmlspecialchars(gmdate('c', $commentCreated), ENT_QUOTES, 'UTF-8'); ?>"
                              data-qiwi-local-time
                              data-timestamp="<?php echo $commentCreated; ?>"><?php $comments->date('Y-m-d H:i'); ?></time>
                        <?php if ($commentLocation !== ''): ?>
                        <span class="comment-location"><?php echo htmlspecialchars($commentLocation, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <?php if ($isWaitingComment): ?>
                        <span class="comment-status-note">您的评论正在等待审核</span>
                        <?php endif; ?>
                        <span class="comment-reply">
                        <?php $comments->reply('回复'); ?>
                        </span>
                        <?php if ($canCopyCommentLink): ?>
                        <button type="button" class="comment-copy-link qiwi-copy-link" data-qiwi-copy-link="#comment-<?php echo $commentCoid; ?>" aria-label="复制评论链接" title="复制评论链接">
                            <i class="fa-solid fa-link" aria-hidden="true"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if ($comments->children): ?>
                <?php $comments->threadedComments(); ?>
            <?php endif; ?>
        </div>
        <?php
    }
}
?>

<div id="comments" class="comments-section">
    <?php $this->comments()->to($comments); ?>

    <?php if ($comments->have()): ?>
    <h3 class="comments-title">
        <?php
        $commentTotal = function_exists('qiwiGetCommentCountIncludingReplies')
            ? qiwiGetCommentCountIncludingReplies($this->cid)
            : 0;
        if ($commentTotal <= 0) {
            $comments->num(_t('暂无评论'), _t('仅有一条评论'), _t('已有 %d 条评论'));
        } elseif ($commentTotal === 1) {
            _e('仅有一条评论');
        } else {
            echo sprintf(_t('已有 %d 条评论'), $commentTotal);
        }
        ?>
    </h3>

    <?php $comments->listComments(['before' => '<div class="comment-list">', 'after' => '</div>']); ?>

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
            <h3 class="comment-respond-title" data-comment-heading><?php if ($this->user->hasLogin()) { $this->user->screenName(); echo ' 评论'; } elseif ($hasRememberedProfile) { echo htmlspecialchars($rememberAuthor, ENT_QUOTES, 'UTF-8') . ' 评论'; } else { echo '游客评论'; } ?></h3>
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
            <div class="comment-profile-modal" data-comment-profile-modal id="comment-profile-fields" role="group" aria-labelledby="comment-profile-title">
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
                        <button type="button" class="submit-button comment-profile-save" data-comment-profile-save>保存</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="form-field comment-text-field">
                <label for="textarea"><?php _e('内容'); ?> *</label>
                <textarea rows="5" name="text" id="textarea" placeholder="写下你的想法…" required><?php $this->remember('text'); ?></textarea>
            </div>

            <div class="comment-form-footer" data-has-profile="<?php echo $hasRememberedProfile ? 'true' : 'false'; ?>">
                <?php if ($this->options->enabledCaptcha && function_exists('qiwiCanRenderCaptcha') && qiwiCanRenderCaptcha()): ?>
                    <div class="captcha-script">
                        <?php qiwiRenderCaptcha(); ?>
                    </div>
                <?php endif; ?>
                <?php if (!$this->user->hasLogin()): ?>
                    <button type="button" class="comment-profile-toggle" data-comment-profile-toggle aria-expanded="false" aria-controls="comment-profile-fields" title="设置评论身份">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0"/></svg>
                        <span class="comment-identity-value sr-only" data-comment-identity-label><?php echo $hasRememberedProfile ? htmlspecialchars($rememberAuthor, ENT_QUOTES, 'UTF-8') : '未设置'; ?></span>
                    </button>
                <?php else: ?>
                    <a class="comment-profile-toggle is-logged" href="<?php $this->options->profileUrl(); ?>" title="打开个人资料">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0"/></svg>
                        <span class="sr-only">打开个人资料</span>
                    </a>
                <?php endif; ?>
                <button type="submit" class="submit-button comment-send-button" id="sub_btn" aria-label="提交评论">提交评论</button>
            </div>

        </form>
    </div>
    <?php else: ?>
    <p class="comments-closed"><?php _e('评论已关闭'); ?></p>
    <?php endif; ?>
</div>
