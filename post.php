<?php
/**
 * 文章详情页 - Medium 风格
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$postViews = qiwiRecordPostView($this->cid);
$qiwiShowToc = qiwiShouldShowToc($this);
$this->need('header.php');
$qiwiCopyrightHtml = function_exists('qiwiGetPostCopyrightHtml') ? qiwiGetPostCopyrightHtml($this) : '';
$qiwiCategoryLinks = function_exists('qiwiRenderTermLinks')
    ? qiwiRenderTermLinks(isset($this->categories) ? $this->categories : array(), 'post-date-category-link', 'category')
    : '';
$qiwiPostLikeEndpoint = function_exists('qiwiGetThemeActionEndpoint') ? qiwiGetThemeActionEndpoint('post-like', $this->options) : '';
$qiwiPostLikeCount = 0;
$qiwiPostLiked = false;
if (class_exists('QiwiTheme_Plugin')) {
    $qiwiPostLikeCounts = QiwiTheme_Plugin::postLikeCounts(array((int) $this->cid));
    $qiwiPostLikeCount = isset($qiwiPostLikeCounts[(int) $this->cid]) ? (int) $qiwiPostLikeCounts[(int) $this->cid] : 0;
    $qiwiPostLikeIdentityHash = QiwiTheme_Plugin::currentPostLikeIdentityHash();
    $qiwiPostLiked = $qiwiPostLikeIdentityHash !== '' && QiwiTheme_Plugin::hasPostLiked((int) $this->cid, $qiwiPostLikeIdentityHash);
}
$qiwiPostSupportEnabled = (string) qiwiGetOptionValue($this, 'postSupportEnabled', '0') === '1';
$qiwiPostSupportQrUrl = trim((string) qiwiGetOptionValue($this, 'postSupportQrUrl', ''));
$qiwiPostSupportTopText = trim((string) qiwiGetOptionValue($this, 'postSupportTopText', '请我喝一杯咖啡吧'));
$qiwiPostSupportBottomText = trim((string) qiwiGetOptionValue($this, 'postSupportBottomText', '或者评论一下分享你的感受'));
$qiwiPostSupportVisible = $qiwiPostSupportEnabled && $qiwiPostSupportQrUrl !== '';

// 段落想法：QiwiTheme 模块可用 ×（单篇覆盖 || 主题全局开关），提交端点由签名 URL 提供
$qiwiThoughtsEnabled = function_exists('qiwiShouldEnableThoughts') && qiwiShouldEnableThoughts($this) && class_exists('QiwiTheme_Thoughts');
$qiwiThoughtsEndpoint = $qiwiThoughtsEnabled && function_exists('qiwiGetThemeActionEndpoint') ? qiwiGetThemeActionEndpoint('thought-submit', $this->options) : '';
if ($qiwiThoughtsEnabled && $qiwiThoughtsEndpoint === '') {
    $qiwiThoughtsEnabled = false;
}
$qiwiThoughtsPayload = null;
$qiwiThoughtsCount = 0;
$qiwiThoughtsIsAdmin = false;
$qiwiThoughtsCaptcha = false;
$qiwiThoughtStickerPacks = array();
if ($qiwiThoughtsEnabled) {
    try {
        $qiwiThoughtsIsAdmin = $this->user->hasLogin() && $this->user->pass('administrator', true);
    } catch (Exception $e) {
        $qiwiThoughtsIsAdmin = false;
    } catch (Throwable $e) {
        $qiwiThoughtsIsAdmin = false;
    }
    $qiwiThoughtsCaptcha = !$qiwiThoughtsIsAdmin
        && $this->options->enabledCaptcha
        && function_exists('qiwiCanRenderCaptcha')
        && qiwiCanRenderCaptcha();
    $ownThoughtIds = function_exists('qiwiGetOwnThoughtIds') ? qiwiGetOwnThoughtIds() : array();
    $qiwiThoughtsPayload = QiwiTheme_Thoughts::pagePayload((int) $this->cid, $ownThoughtIds);
    foreach ($qiwiThoughtsPayload['items'] as $qiwiThoughtItem) {
        if ($qiwiThoughtItem['status'] === 'approved') {
            $qiwiThoughtsCount++;
        }
    }
    $qiwiThoughtsPayload['endpoint'] = $qiwiThoughtsEndpoint;
    $qiwiThoughtsPayload['isAdmin'] = $qiwiThoughtsIsAdmin;
    $qiwiThoughtsPayload['captcha'] = $qiwiThoughtsCaptcha;
    if (function_exists('qiwiGetSelectableStickerPacks')) {
        $qiwiThoughtStickerPacks = qiwiGetSelectableStickerPacks();
    }
}

ob_start();
$this->thePrev('%s', '');
$qiwiPrevPostLink = trim(ob_get_clean());
$qiwiPrevPostHref = '';
// 标题先解码：个别文章标题入库时已被预转义（存的是 &amp;），与 href 同步归一后再由模板统一单次转义。
$qiwiPrevPostTitle = html_entity_decode(trim(strip_tags($qiwiPrevPostLink)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
if ($qiwiPrevPostLink !== '' && preg_match('/href=(["\'])(.*?)\1/i', $qiwiPrevPostLink, $matches)) {
    $qiwiPrevPostHref = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

ob_start();
$this->theNext('%s', '');
$qiwiNextPostLink = trim(ob_get_clean());
$qiwiNextPostHref = '';
$qiwiNextPostTitle = html_entity_decode(trim(strip_tags($qiwiNextPostLink)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
if ($qiwiNextPostLink !== '' && preg_match('/href=(["\'])(.*?)\1/i', $qiwiNextPostLink, $matches)) {
    $qiwiNextPostHref = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
?>

<div class="article-page">
    <!-- 文章主体 -->
    <div class="article-main">
        <article class="single-post" itemscope itemtype="http://schema.org/BlogPosting">
            <!-- 文章头部 -->
            <header class="article-header">
                <h1 class="article-header-title" itemprop="name headline">
                    <?php $this->title(); ?>
                </h1>
                <div class="article-header-meta">
                    <img src="<?php echo htmlspecialchars(function_exists('qiwiGetCommentAvatarUrl') ? qiwiGetCommentAvatarUrl($this->author->mail, 96) : 'https://gravatar.loli.net/avatar/' . md5($this->author->mail) . '?s=96&d=mp', ENT_QUOTES, 'UTF-8'); ?>" alt="<?php $this->author(); ?>" class="author-avatar">
                    <div class="author-info">
                        <span class="author-name"><?php $this->author(); ?></span>
                        <div class="post-date-row">
                            <span class="post-date"><?php $this->date('Y-m-d H:i'); ?><?php if ($qiwiCategoryLinks !== ''): ?> · <span class="post-date-categories"><?php echo $qiwiCategoryLinks; ?></span><?php endif; ?> · <?php
                                $content = $this->content;
                                $wordCount = function_exists('qiwiCountReadableWords')
                                    ? qiwiCountReadableWords($content)
                                    : mb_strlen(strip_tags($content), 'UTF-8');
                                $articleCommentCount = function_exists('qiwiGetCommentCountIncludingReplies') ? qiwiGetCommentCountIncludingReplies($this->cid) : (int) $this->commentsNum;
                                echo function_exists('qiwiFormatPostWordCount') ? qiwiFormatPostWordCount($wordCount) : (int) $wordCount . '字';
                            ?> · <?php echo (int) $postViews; ?> 次浏览<?php if ($qiwiThoughtsEnabled && $qiwiThoughtsCount > 0): ?> · <?php echo (int) $qiwiThoughtsCount; ?> 条想法<?php endif; ?><?php if ($articleCommentCount > 0): ?> · <a href="#comments"><?php echo (int) $articleCommentCount; ?> 条评论</a><?php endif; ?></span>
                            <div class="article-reading-control" data-reading-control>
                                <button type="button" class="article-reading-trigger" data-reading-trigger aria-expanded="false" aria-haspopup="true">
                                    <span data-reading-label="font">易读</span><span aria-hidden="true">/</span><span data-reading-label="spacing">宽</span><span aria-hidden="true">/</span><span data-reading-label="size">中</span>
                                </button>
                                <div class="article-reading-popover" data-reading-popover role="group" aria-label="阅读设置">
                                    <div class="article-reading-group" data-reading-group="font" aria-label="字体">
                                        <span>字体</span>
                                        <button type="button" data-reading-option="font" data-reading-value="readable">易读</button>
                                        <button type="button" data-reading-option="font" data-reading-value="plain">普通</button>
                                        <button type="button" data-reading-option="font" data-reading-value="mono">等宽</button>
                                    </div>
                                    <div class="article-reading-group" data-reading-group="spacing" aria-label="间距">
                                        <span>间距</span>
                                        <button type="button" data-reading-option="spacing" data-reading-value="wide">宽</button>
                                        <button type="button" data-reading-option="spacing" data-reading-value="compact">窄</button>
                                    </div>
                                    <div class="article-reading-group" data-reading-group="size" aria-label="字号">
                                        <span>字号</span>
                                        <button type="button" data-reading-option="size" data-reading-value="large">大</button>
                                        <button type="button" data-reading-option="size" data-reading-value="medium">中</button>
                                        <button type="button" data-reading-option="size" data-reading-value="small">小</button>
                                    </div>
                                    <?php if ($qiwiThoughtsEnabled): ?>
                                    <div class="article-reading-group" data-reading-group="thoughts" aria-label="想法">
                                        <span>想法</span>
                                        <button type="button" data-thoughts-toggle="on" aria-pressed="true">显示</button>
                                        <button type="button" data-thoughts-toggle="off" aria-pressed="false">关闭</button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- 文章头图 -->
            <?php
            $showThumbnail = $this->fields->showThumbnail;
            $thumbnail = $this->fields->thumbnail;
            if (($showThumbnail == 2 || $showThumbnail == 3) && !empty($thumbnail)):
            ?>
            <img src="<?php echo htmlspecialchars($thumbnail, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php $this->title(); ?>" class="article-hero" loading="eager" fetchpriority="high" decoding="async" width="1200" height="675">
            <?php endif; ?>

            <?php if ($qiwiShowToc): ?>
            <nav class="article-toc" aria-label="文章目录"></nav>
            <?php endif; ?>

            <!-- 文章内容 -->
            <div class="article-body" itemprop="articleBody">
                <?php qiwiContent($this); ?>
            </div>

            <?php if ($qiwiThoughtsEnabled && is_array($qiwiThoughtsPayload)): ?>
            <!-- 段落想法：数据与表单模板（JS 就地取用；模板本身不在 .article-body 内，不参与字符轴） -->
            <div class="thought-ui-template" data-thought-template hidden>
                <script type="application/json" data-thoughts-data><?php echo json_encode($qiwiThoughtsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
                <form class="comment-form thought-form" data-thought-form action="<?php echo htmlspecialchars($qiwiThoughtsEndpoint, ENT_QUOTES, 'UTF-8'); ?>" method="post">
                    <?php if (!$qiwiThoughtsIsAdmin): ?>
                    <!-- 身份：与评论区共用本地身份；已保存时 JS 折叠为一行摘要，无 JS 时字段始终可见 -->
                    <div class="thought-identity" data-thought-identity>
                        <div class="thought-identity-summary" data-thought-identity-summary hidden>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0"/></svg>
                            <span>以 <strong data-thought-identity-name></strong> 的身份发布</span>
                            <button type="button" class="thought-identity-edit" data-thought-identity-edit aria-expanded="false" aria-controls="thought-identity-fields">修改</button>
                        </div>
                        <!-- 外层负责高度伸缩（grid 0fr ↔ 1fr），内层裁切内容 -->
                        <div class="thought-identity-collapse" id="thought-identity-fields" data-thought-identity-fields>
                            <div class="thought-form-fields">
                                <div class="form-field">
                                    <label for="thought-author" class="sr-only">称呼</label>
                                    <input type="text" name="author" id="thought-author" data-thought-author placeholder="称呼 *" maxlength="64" autocomplete="name" required>
                                </div>
                                <div class="form-field">
                                    <label for="thought-mail" class="sr-only">Email</label>
                                    <input type="email" name="mail" id="thought-mail" data-thought-mail placeholder="Email *" maxlength="200" autocomplete="email" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="form-field thought-text-field">
                        <label for="thought-text" class="sr-only">想法</label>
                        <textarea rows="3" name="text" id="thought-text" data-thought-text placeholder="写下这段文字带给你的想法…" required></textarea>
                        <?php if (!empty($qiwiThoughtStickerPacks)): ?>
                        <div class="comment-sticker-panel" data-comment-sticker-panel aria-hidden="true">
                            <div class="comment-sticker-tabs" data-comment-sticker-tabs role="tablist" aria-label="表情包分类"></div>
                            <button type="button" class="comment-sticker-close" data-comment-sticker-close aria-label="关闭表情包">×</button>
                            <div class="comment-sticker-grid" data-comment-sticker-grid></div>
                            <p class="comment-sticker-status" data-comment-sticker-status>正在读取表情包…</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($qiwiThoughtsCaptcha): ?>
                    <div class="captcha-script thought-captcha" data-thought-captcha>
                        <?php qiwiRenderCaptcha(); ?>
                    </div>
                    <?php endif; ?>
                    <div class="thought-form-footer">
                        <?php if (!empty($qiwiThoughtStickerPacks)): ?>
                        <button type="button" class="comment-sticker-toggle" data-comment-sticker-toggle aria-expanded="false" title="选择表情包">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M8.5 14.2s1.2 1.8 3.5 1.8 3.5-1.8 3.5-1.8M9 9.5h.01M15 9.5h.01"/></svg>
                            <span class="sr-only">展开表情包</span>
                        </button>
                        <script type="application/json" data-comment-sticker-packs><?php echo json_encode($qiwiThoughtStickerPacks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
                        <?php endif; ?>
                        <span class="thought-char-count" data-thought-counter aria-live="polite"></span>
                        <button type="submit" class="submit-button thought-send-button" data-thought-submit>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 12 16-8-6.5 16-2.3-6.2L4 12Z"/><path d="m11.2 13.8 4.4-4.4"/></svg>
                            <span>发布</span>
                        </button>
                    </div>
                    <p class="thought-form-status" data-thought-status role="status" aria-live="polite"></p>
                </form>
            </div>
            <?php endif; ?>

            <section class="post-reactions<?php if ($qiwiPostLiked): ?> is-liked<?php endif; ?>" aria-label="文章反馈">
                <button
                    type="button"
                    class="post-like-button<?php if ($qiwiPostLiked): ?> is-liked has-count<?php endif; ?>"
                    data-post-like
                    data-post-id="<?php echo (int) $this->cid; ?>"
                    data-like-endpoint="<?php echo htmlspecialchars($qiwiPostLikeEndpoint, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-pressed="<?php echo $qiwiPostLiked ? 'true' : 'false'; ?>">
                    <i class="<?php echo $qiwiPostLiked ? 'fa-solid' : 'fa-regular'; ?> fa-heart" aria-hidden="true" data-like-icon></i>
                    <span data-like-label><?php echo $qiwiPostLiked ? '已喜欢' : '喜欢文章'; ?></span>
                    <span class="post-like-count" data-like-count<?php if (!$qiwiPostLiked): ?> hidden<?php endif; ?>><?php echo (int) $qiwiPostLikeCount; ?></span>
                </button>

                <?php if ($qiwiPostSupportVisible): ?>
                <div class="post-support" data-post-support>
                    <button type="button" class="post-support-button" aria-haspopup="true" aria-expanded="false">
                        <i class="fa-solid fa-mug-hot" aria-hidden="true"></i>
                        <span>支持作者</span>
                    </button>
                    <div class="post-support-popover" role="group" aria-label="支持作者">
                        <?php if ($qiwiPostSupportTopText !== ''): ?><p class="post-support-text"><?php echo htmlspecialchars($qiwiPostSupportTopText, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                        <img src="<?php echo htmlspecialchars($qiwiPostSupportQrUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="支持作者二维码" loading="lazy" decoding="async">
                        <?php if ($qiwiPostSupportBottomText !== ''): ?><p class="post-support-text is-bottom"><?php echo htmlspecialchars($qiwiPostSupportBottomText, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </section>

            <?php if ($qiwiCopyrightHtml !== '' || $this->tags): ?>
            <section class="post-endnote" aria-label="文章附注">
                <?php if ($qiwiCopyrightHtml !== ''): ?>
                <div class="post-copyright">
                    <div class="post-copyright-sheet">
                        <div class="post-endnote-label">Copyright</div>
                        <div class="post-copyright-body">
                            <?php echo $qiwiCopyrightHtml; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($this->tags): ?>
                <div class="post-tags">
                    <span class="post-endnote-label">Tags</span>
                    <div class="article-tags">
                        <?php $this->tags(' ', true, ''); ?>
                    </div>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <!-- 文章导航 -->
            <nav class="post-navigation">
                <?php if ($qiwiPrevPostHref !== ''): ?>
                <a class="post-navigation-item nav-previous" href="<?php echo htmlspecialchars($qiwiPrevPostHref, ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="post-navigation-label">上一篇</span>
                    <span class="post-navigation-link"><?php echo htmlspecialchars($qiwiPrevPostTitle, ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
                <?php else: ?>
                <div class="post-navigation-item nav-previous is-empty">
                    <span class="post-navigation-label">上一篇</span>
                    <span class="post-navigation-empty">没有更早的文章了</span>
                </div>
                <?php endif; ?>

                <?php if ($qiwiNextPostHref !== ''): ?>
                <a class="post-navigation-item nav-next" href="<?php echo htmlspecialchars($qiwiNextPostHref, ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="post-navigation-label">下一篇</span>
                    <span class="post-navigation-link"><?php echo htmlspecialchars($qiwiNextPostTitle, ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
                <?php else: ?>
                <div class="post-navigation-item nav-next is-empty">
                    <span class="post-navigation-label">下一篇</span>
                    <span class="post-navigation-empty">没有更新的文章了</span>
                </div>
                <?php endif; ?>
            </nav>
        </article>

        <!-- 评论区 -->
        <?php if ($this->allow('comment')): ?>
        <div class="comments-wrapper">
            <?php $this->need('comments.php'); ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php $this->need('footer.php'); ?>
