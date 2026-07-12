<?php
/**
 * 时光机页面模板
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
$pageContent = qiwiGetContent($this);

// 获取数据
$pageId = $this->cid;
$authorUid = $this->author->uid;
$isMomentManager = $this->user->hasLogin()
    && ((int) $this->user->uid === (int) $authorUid || (isset($this->user->group) && $this->user->group === 'administrator'));
$pageSize = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// 获取数据库
$db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
$prefix = $db->getPrefix();

$rememberAuthor = function_exists('qiwi_capture_remember') ? qiwi_capture_remember($this, 'author') : trim((string) $this->remember('author', true));
$rememberMail = function_exists('qiwi_capture_remember') ? qiwi_capture_remember($this, 'mail') : trim((string) $this->remember('mail', true));
$canShowOwnWaitingReplies = !$this->user->hasLogin() && $rememberAuthor !== '' && $rememberMail !== '';

// 查询说说（作者的评论），并允许访客看见自己的待审核顶层评论。
$select = $db->select()->from($prefix.'comments')
    ->where('cid = ?', $pageId)
    ->where('type = ?', 'comment')
    ->where('(parent IS NULL OR parent = ?)', 0)
    ->order('created', $db::SORT_DESC)
    ->page($currentPage, $pageSize);

if ($canShowOwnWaitingReplies) {
    $select->where(
        '((status = ? AND authorId = ?) OR (status = ? AND author = ? AND mail = ?))',
        'approved',
        $authorUid,
        'waiting',
        $rememberAuthor,
        $rememberMail
    );
} else {
    $select->where('status = ?', 'approved')
        ->where('authorId = ?', $authorUid);
}

$comments = $db->fetchAll($select);
$momentCoids = [];
foreach ($comments as $comment) {
    if (isset($comment['coid'])) {
        $momentCoids[] = (int) $comment['coid'];
    }
}

$momentLikeCounts = class_exists('QiwiTheme_Plugin') ? QiwiTheme_Plugin::momentLikeCounts($momentCoids) : [];
$momentLikedHash = '';
if ($this->user->hasLogin() && class_exists('QiwiTheme_Plugin')) {
    $userMailHash = QiwiTheme_Plugin::momentMailHash(isset($this->user->mail) ? $this->user->mail : '');
    $momentLikedHash = $userMailHash !== ''
        ? sha1('mail:' . $userMailHash)
        : sha1('user:' . (int) $this->user->uid);
} elseif (isset($_COOKIE['qiwi_moment_like_mail_hash']) && preg_match('/^[a-f0-9]{40}$/i', (string) $_COOKIE['qiwi_moment_like_mail_hash'])) {
    $momentLikedHash = sha1('mail:' . strtolower((string) $_COOKIE['qiwi_moment_like_mail_hash']));
} elseif (isset($_COOKIE['qiwi_moment_like_id']) && preg_match('/^[a-zA-Z0-9]{20,}$/', (string) $_COOKIE['qiwi_moment_like_id'])) {
    $momentLikedHash = sha1('visitor:' . (string) $_COOKIE['qiwi_moment_like_id']);
}

$momentLiked = [];
if ($momentLikedHash !== '' && class_exists('QiwiTheme_Plugin')) {
    foreach ($momentCoids as $coid) {
        $momentLiked[$coid] = QiwiTheme_Plugin::hasMomentLiked($coid, $momentLikedHash);
    }
}

$momentRepliesByParent = [];
$momentReplyCounts = [];
if (!empty($momentCoids)) {
    $replySelect = $db->select()->from($prefix.'comments')
        ->where('cid = ?', $pageId)
        ->where('type = ?', 'comment')
        ->where('parent > ?', 0)
        ->order('created', $db::SORT_ASC);

    if ($canShowOwnWaitingReplies) {
        $replySelect->where(
            '(status = ? OR (status = ? AND author = ? AND mail = ?))',
            'approved',
            'waiting',
            $rememberAuthor,
            $rememberMail
        );
    } else {
        $replySelect->where('status = ?', 'approved');
    }

    $replyRows = $db->fetchAll($replySelect);

    foreach ($replyRows as $reply) {
        $parent = isset($reply['parent']) ? (int) $reply['parent'] : 0;
        if ($parent <= 0) {
            continue;
        }
        if (!isset($momentRepliesByParent[$parent])) {
            $momentRepliesByParent[$parent] = [];
        }
        $momentRepliesByParent[$parent][] = $reply;
    }

    $countMomentReplies = function ($parent) use (&$countMomentReplies, &$momentRepliesByParent) {
        $count = 0;
        if (empty($momentRepliesByParent[$parent])) {
            return $count;
        }
        foreach ($momentRepliesByParent[$parent] as $reply) {
            $count++;
            $count += $countMomentReplies((int) $reply['coid']);
        }
        return $count;
    };

    foreach ($momentCoids as $coid) {
        $momentReplyCounts[$coid] = $countMomentReplies($coid);
    }
}

// 获取总数
if ($canShowOwnWaitingReplies) {
    $totalResult = $db->fetchRow($db->select('COUNT(coid) AS total')
        ->from($prefix.'comments')
        ->where('cid = ?', $pageId)
        ->where('type = ?', 'comment')
        ->where('(parent IS NULL OR parent = ?)', 0)
        ->where(
            '((status = ? AND authorId = ?) OR (status = ? AND author = ? AND mail = ?))',
            'approved',
            $authorUid,
            'waiting',
            $rememberAuthor,
            $rememberMail
        ));
} else {
    $totalResult = $db->fetchRow($db->select('COUNT(coid) AS total')
        ->from($prefix.'comments')
        ->where('cid = ?', $pageId)
        ->where('status = ?', 'approved')
        ->where('type = ?', 'comment')
        ->where('authorId = ?', $authorUid)
        ->where('(parent IS NULL OR parent = ?)', 0));
}

$total = $totalResult ? $totalResult['total'] : 0;
$totalPages = ceil($total / $pageSize);

// Markdown 渲染
function renderMarkdown($text) {
    if (empty($text)) return '';

    $lines = explode("\n", $text);
    $result = [];
    $inQuote = false;
    $quoteLines = [];

    foreach ($lines as $line) {
        if (preg_match('/^>\s?(.*)$/', $line, $matches)) {
            // 这是引用行
            if (!$inQuote) {
                $inQuote = true;
            }
            $quoteLines[] = $matches[1];
        } else {
            // 不是引用行
            if ($inQuote) {
                // 结束之前的引用块，对引用内容进行转义
                $quoteContent = htmlspecialchars(implode("\n", $quoteLines), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                // 保留换行，稍后统一处理
                $result[] = '<blockquote>' . $quoteContent . '</blockquote>';
                $quoteLines = [];
                $inQuote = false;
            }
            // 对非引用行进行转义
            $result[] = htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    // 处理最后可能还在引用中的情况
    if ($inQuote) {
        $quoteContent = htmlspecialchars(implode("\n", $quoteLines), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $result[] = '<blockquote>' . $quoteContent . '</blockquote>';
    }

    $text = implode("\n", $result);

    // 处理其他 Markdown 语法
    $text = preg_replace('/!\[([^\]]*)\]\(([^\)]+)\)/', '<img src="$2" alt="$1" class="moment-image qiwi-content-image" loading="lazy">', $text);
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/`([^`]+?)`/', '<code>$1</code>', $text);
    $text = preg_replace('/\[([^\]]*)\]\(([^\)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $text);
    $text = renderMomentAutolinks($text);
    if (function_exists('qiwiRenderShortcodes')) {
        $text = qiwiRenderShortcodes($text);
    }

    return renderMomentParagraphs($text);
}

function renderMomentParagraphs($html) {
    $html = str_replace(array("\r\n", "\r"), "\n", (string) $html);
    $blocks = preg_split('/\n{2,}/', $html);
    $paragraphs = [];

    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') {
            continue;
        }

        if (preg_match('/^<blockquote\b([^>]*)>([\s\S]*)<\/blockquote>$/u', $block, $matches)) {
            $paragraphs[] = '<blockquote' . $matches[1] . '>' . nl2br(trim($matches[2]), false) . '</blockquote>';
            continue;
        }

        if (preg_match('/^<(aside|details|div|ul|ol|pre|table|figure)\b[\s\S]*<\/\1>$/u', $block)) {
            $paragraphs[] = $block;
            continue;
        }

        $paragraphs[] = '<p>' . nl2br($block, false) . '</p>';
    }

    return implode('', $paragraphs);
}

function renderMomentAutolinks($html) {
    $parts = preg_split('/(<a\b[\s\S]*?<\/a>|<code\b[\s\S]*?<\/code>|<img\b[^>]*>)/iu', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $index => $part) {
        if (preg_match('/^<(a|code|img)\b/iu', $part)) {
            continue;
        }

        $parts[$index] = preg_replace_callback('/((?:https?:\/\/|www\.)[a-z0-9][a-z0-9.-]*(?::\d+)?(?:\/[^\s<>"\'`，。！？；：、（）【】《》「」『』\x{3000}]*)?|(?:[a-z0-9-]+\.)+[a-z]{2,}(?:\/[^\s<>"\'`，。！？；：、（）【】《》「」『』\x{3000}]*)?)/iu', function ($matches) {
            $raw = $matches[0];
            $trailing = '';
            while (preg_match('/[.,!?;:，。！？；：、）)\]]$/u', $raw)) {
                $trailing = mb_substr($raw, -1, 1, 'UTF-8') . $trailing;
                $raw = mb_substr($raw, 0, mb_strlen($raw, 'UTF-8') - 1, 'UTF-8');
            }

            if ($raw === '') {
                return $matches[0];
            }

            $url = preg_match('/^https?:\/\//i', $raw) ? $raw : 'https://' . $raw;
            $decodedUrl = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $host = parse_url($decodedUrl, PHP_URL_HOST);
            if (!$host) {
                return $matches[0];
            }

            $label = renderMomentLinkDomain($host);
            $safeUrl = htmlspecialchars($decodedUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return '<a href="' . $safeUrl . '" target="_blank" rel="noopener">' . $safeLabel . '</a>' . $trailing;
        }, $part);
    }

    return implode('', $parts);
}

function renderMomentLinkDomain($host) {
    $host = preg_replace('/^www\./i', '', strtolower((string) $host));
    $parts = array_values(array_filter(explode('.', $host)));
    $count = count($parts);
    if ($count >= 3 && strlen($parts[$count - 1]) === 2 && strlen($parts[$count - 2]) <= 3) {
        return implode('.', array_slice($parts, -3));
    }
    if ($count >= 2) {
        return implode('.', array_slice($parts, -2));
    }
    return $host;
}

function renderMomentAvatar($mail, $fallback, $size = 48) {
    $mail = trim((string) $mail);
    if ($mail !== '') {
        if (function_exists('qiwiGetCommentAvatarUrl')) {
            return qiwiGetCommentAvatarUrl($mail, $size);
        }

        return 'https://gravatar.loli.net/avatar/' . md5(strtolower($mail)) . '?s=' . (int) $size . '&d=mp';
    }

    return $fallback;
}

function renderMomentCommentText($text) {
    $text = htmlspecialchars((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/`([^`]+?)`/', '<code>$1</code>', $text);
    return nl2br($text);
}

function renderTrustedMomentCommentText($text) {
    if (function_exists('qiwiRenderTrustedCommentContent')) {
        return qiwiRenderTrustedCommentContent($text);
    }

    return renderMomentCommentText($text);
}

function renderMomentReplyTree($parent, $repliesByParent, $authorUid, $ownerAvatar, $level = 0) {
    if (empty($repliesByParent[$parent])) {
        return;
    }

    echo '<div class="moment-comments-list" data-comment-level="' . (int) $level . '">';
    foreach ($repliesByParent[$parent] as $reply) {
        $isOwner = isset($reply['authorId']) && (int) $reply['authorId'] === (int) $authorUid;
        $avatar = $isOwner ? $ownerAvatar : renderMomentAvatar(isset($reply['mail']) ? $reply['mail'] : '', $ownerAvatar, 40);
        $coid = isset($reply['coid']) ? (int) $reply['coid'] : 0;
        $isTrustedReply = isset($reply['authorId']) && (int) $reply['authorId'] > 0;
        $canCopyReplyLink = function_exists('qiwiUserHasLogin') ? qiwiUserHasLogin() : false;
        $isWaiting = isset($reply['status']) && (string) $reply['status'] === 'waiting';
        $created = isset($reply['created']) ? (int) $reply['created'] : 0;
        $location = function_exists('qiwiGetCommentLocationLabel') ? qiwiGetCommentLocationLabel($reply) : '';
        echo '<article class="moment-comment' . ($isWaiting ? ' is-waiting' : '') . ($isTrustedReply ? ' is-trusted-comment' : '') . '" id="comment-' . $coid . '">';
        echo '<img class="moment-comment-avatar" src="' . htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') . '" alt="">';
        echo '<div class="moment-comment-body">';
        echo '<div class="moment-comment-meta"><span class="moment-comment-author-row"><span class="moment-comment-author">' . htmlspecialchars(isset($reply['author']) ? $reply['author'] : '', ENT_QUOTES, 'UTF-8') . '</span>';
        // if ($isOwner) {
        //     echo '<span class="moment-owner-badge" title="UP 主亲自回复"><i class="fa-solid fa-check" aria-hidden="true"></i><span>UP 主</span></span>';
        // }
        echo '<time class="moment-comment-time" datetime="' . htmlspecialchars(gmdate('c', $created), ENT_QUOTES, 'UTF-8') . '" data-qiwi-local-time data-timestamp="' . $created . '">' . htmlspecialchars(date('Y-m-d H:i', $created), ENT_QUOTES, 'UTF-8') . '</time>';
        if ($location !== '') {
            echo '<span class="moment-comment-location">' . htmlspecialchars($location, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        echo '</span>';
        if ($isWaiting) {
            echo '<span class="moment-comment-status-note">您的评论正在等待审核</span>';
        }
        echo '</div>';
        echo '<div class="moment-comment-text">' . ($isTrustedReply ? renderTrustedMomentCommentText(isset($reply['text']) ? $reply['text'] : '') : renderMomentCommentText(isset($reply['text']) ? $reply['text'] : '')) . '</div>';
        echo '<div class="moment-comment-actions">';
        echo '<button type="button" class="moment-comment-reply" data-moment-reply="' . $coid . '">回复</button>';
        if ($canCopyReplyLink && $coid > 0) {
            echo '<button type="button" class="moment-comment-copy-link qiwi-copy-link" data-qiwi-copy-link="#comment-' . $coid . '" aria-label="复制评论链接" title="复制评论链接"><i class="fa-solid fa-link" aria-hidden="true"></i></button>';
        }
        echo '</div>';
        renderMomentReplyTree($coid, $repliesByParent, $authorUid, $ownerAvatar, $level + 1);
        echo '</div></article>';
    }
    echo '</div>';
}
?>

<div class="timemachine-page">
    <!-- 左侧留白 -->
    <div class="layout-spacer-left"></div>

    <!-- 主内容 -->
    <div class="timemachine-main">
        <!-- 页面头部 -->
        <header class="timemachine-header">
            <h1 class="page-title"><?php $this->title(); ?></h1>
            <?php if (qiwiHasRenderedContent($pageContent)): ?>
                <div class="page-intro"><?php echo $pageContent; ?></div>
            <?php endif; ?>
            <div class="page-stats">
                <span class="stat-item">共 <?php echo $total; ?> 条记录</span>
            </div>
        </header>

        <!-- 说说列表 -->
        <?php if ($total > 0): ?>
        <?php
            $ownerAvatar = $this->options->aboutAvatar ?: 'https://gravatar.loli.net/avatar/default?s=96&d=mp';
            $momentLikeEndpoint = '';
            $momentLikeToken = '';
            $momentCommentToken = '';
            try {
                Typecho_Widget::widget('Widget_Security')->to($security);
                $momentLikeEndpoint = class_exists('QiwiTheme_Plugin') ? $security->getIndex('/action/qiwi-theme?do=moment-like') : '';
                $momentLikeQuery = $momentLikeEndpoint !== '' ? parse_url($momentLikeEndpoint, PHP_URL_QUERY) : '';
                if (is_string($momentLikeQuery) && $momentLikeQuery !== '') {
                    parse_str($momentLikeQuery, $momentLikeParams);
                    $momentLikeToken = isset($momentLikeParams['_']) ? (string) $momentLikeParams['_'] : '';
                }
                $momentCommentToken = $security->getToken($this->permalink);
            } catch (Exception $e) {
                $momentLikeEndpoint = '';
                $momentLikeToken = '';
                $momentCommentToken = '';
            } catch (Throwable $e) {
                $momentLikeEndpoint = '';
                $momentLikeToken = '';
                $momentCommentToken = '';
            }
        ?>
        <div class="moments-list">
            <?php foreach ($comments as $comment): ?>
            <?php
                $coid = isset($comment['coid']) ? (int) $comment['coid'] : 0;
                $likeCount = isset($momentLikeCounts[$coid]) ? (int) $momentLikeCounts[$coid] : 0;
                $replyCount = isset($momentReplyCounts[$coid]) ? (int) $momentReplyCounts[$coid] : 0;
                $isLiked = !empty($momentLiked[$coid]);
                $isWaitingMoment = isset($comment['status']) && (string) $comment['status'] === 'waiting';
                $isOwnerMoment = isset($comment['authorId']) && (int) $comment['authorId'] === (int) $authorUid;
                $momentAuthorName = $isOwnerMoment ? (string) $this->author->screenName : (isset($comment['author']) ? (string) $comment['author'] : '');
                $momentAvatar = $isOwnerMoment ? $ownerAvatar : renderMomentAvatar(isset($comment['mail']) ? $comment['mail'] : '', $ownerAvatar, 48);
            ?>
            <article class="moment-item<?php if ($isWaitingMoment): ?> is-waiting<?php endif; ?>" id="comment-<?php echo $coid; ?>" data-moment-id="<?php echo $coid; ?>">
                <div class="moment-avatar">
                    <img src="<?php echo htmlspecialchars($momentAvatar, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="avatar">
                </div>
                <div class="moment-content">
                    <div class="moment-header">
                        <span class="moment-author-stack">
                            <span class="moment-author"><?php echo htmlspecialchars($momentAuthorName, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($isWaitingMoment): ?>
                            <span class="moment-comment-status-note">您的评论正在等待审核</span>
                            <?php endif; ?>
                        </span>
                        <!-- <span class="moment-owner-badge" title="UP 主亲自发布"><i class="fa-solid fa-check" aria-hidden="true"></i><span>UP 主</span></span> -->
                    </div>
                    <div class="moment-text article-body">
                        <?php echo renderMarkdown($comment['text']); ?>
                    </div>
                    <div class="moment-footer">
                        <time class="moment-time"
                              datetime="<?php echo htmlspecialchars(gmdate('c', (int) $comment['created']), ENT_QUOTES, 'UTF-8'); ?>"
                              data-qiwi-local-time
                              data-timestamp="<?php echo (int) $comment['created']; ?>"><?php echo date('Y-m-d H:i', (int) $comment['created']); ?></time>
                        <div class="moment-actions">
                            <?php if ($this->user->hasLogin()): ?>
                            <button type="button"
                                    class="moment-action moment-copy-link qiwi-copy-link"
                                    data-qiwi-copy-link="#comment-<?php echo $coid; ?>"
                                    aria-label="复制说说链接"
                                    title="复制说说链接">
                                <i class="fa-solid fa-link" aria-hidden="true"></i>
                            </button>
                            <?php endif; ?>
                            <button type="button"
                                    class="moment-action moment-like-button<?php if ($isLiked): ?> is-active<?php endif; ?>"
                                    data-moment-like="<?php echo $coid; ?>"
                                    aria-pressed="<?php echo $isLiked ? 'true' : 'false'; ?>"
                                    <?php if ($momentLikeEndpoint === ''): ?>disabled<?php endif; ?>>
                                <i class="<?php echo $isLiked ? 'fa-solid' : 'fa-regular'; ?> fa-heart" aria-hidden="true"></i>
                                <span data-moment-like-count><?php echo $likeCount; ?></span>
                            </button>
                            <button type="button"
                                    class="moment-action moment-comment-button"
                                    data-moment-comment-toggle="<?php echo $coid; ?>"
                                    aria-expanded="false"
                                    aria-controls="moment-comment-composer">
                                <i class="fa-regular fa-comment" aria-hidden="true"></i>
                                <span><?php echo $replyCount; ?></span>
                            </button>
                        </div>
                    </div>
                    <?php if ($replyCount > 0): ?>
                    <section class="moment-comments" aria-label="评论">
                        <?php renderMomentReplyTree($coid, $momentRepliesByParent, $authorUid, $ownerAvatar); ?>
                    </section>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <?php if ($this->allow('comment')): ?>
        <?php
            $rememberUrl = function_exists('qiwi_capture_remember') ? qiwi_capture_remember($this, 'url') : trim((string) $this->remember('url', true));
            $hasRememberedProfile = $rememberAuthor !== '' && $rememberMail !== '';
        ?>
        <div class="moment-reply-composer" id="moment-comment-composer" data-moment-comment-composer>
            <form method="post" action="<?php $this->commentUrl(); ?>" class="moment-reply-form">
                <input type="hidden" name="parent" value="" data-moment-reply-parent>
                <?php if ($momentCommentToken !== ''): ?>
                <input type="hidden" name="_" value="<?php echo htmlspecialchars($momentCommentToken, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>
                <?php if ($this->user->hasLogin()): ?>
                <p class="moment-reply-login">以 <?php echo htmlspecialchars($this->user->screenName, ENT_QUOTES, 'UTF-8'); ?> 的身份评论</p>
                <?php else: ?>
                <div class="moment-reply-profile-modal" id="moment-reply-profile-modal" data-moment-profile-modal role="dialog" aria-modal="true" aria-labelledby="moment-reply-profile-title">
                    <button class="moment-reply-profile-backdrop" type="button" data-moment-profile-close tabindex="-1" aria-label="关闭身份设置"></button>
                    <div class="moment-reply-profile-panel">
                        <div class="moment-reply-profile-header">
                            <div>
                                <h4 id="moment-reply-profile-title">评论身份</h4>
                                <p>保存后下次会自动带上，不用每次重新填写。</p>
                            </div>
                            <button class="moment-reply-profile-close" type="button" data-moment-profile-close aria-label="关闭">×</button>
                        </div>
                        <div class="moment-reply-fields">
                            <div class="moment-reply-field">
                                <label for="moment-reply-author">称呼 *</label>
                                <input type="text" name="author" id="moment-reply-author" value="<?php echo htmlspecialchars($rememberAuthor, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="name" required>
                            </div>
                            <div class="moment-reply-field">
                                <label for="moment-reply-mail">Email *</label>
                                <input type="email" name="mail" id="moment-reply-mail" value="<?php echo htmlspecialchars($rememberMail, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="email" required>
                            </div>
                            <div class="moment-reply-field">
                                <label for="moment-reply-url">网站</label>
                                <input type="url" name="url" id="moment-reply-url" value="<?php echo htmlspecialchars($rememberUrl, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="url" placeholder="https://">
                            </div>
                        </div>
                        <div class="moment-reply-profile-actions">
                            <button type="button" class="moment-reply-submit moment-reply-profile-save" data-moment-profile-save>保存身份</button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="moment-reply-text-field">
                    <label for="moment-reply-text">内容 *</label>
                    <textarea name="text" id="moment-reply-text" rows="3" placeholder="写一条评论…" required><?php $this->remember('text'); ?></textarea>
                </div>
                <?php if ($this->options->enabledCaptcha && !$isMomentManager && function_exists('qiwiCanRenderCaptcha') && qiwiCanRenderCaptcha()): ?>
                <div class="captcha-script">
                    <?php qiwiRenderCaptcha(); ?>
                </div>
                <?php endif; ?>
                <div class="moment-reply-footer" data-has-profile="<?php echo $hasRememberedProfile ? 'true' : 'false'; ?>">
                    <?php if (!$this->user->hasLogin()): ?>
                    <button type="button" class="moment-reply-profile-toggle" data-moment-profile-toggle aria-expanded="false" aria-controls="moment-reply-profile-modal">
                        <span class="moment-reply-identity-label">以</span>
                        <span class="moment-reply-identity-value" data-moment-identity-label><?php echo $hasRememberedProfile ? htmlspecialchars($rememberAuthor, ENT_QUOTES, 'UTF-8') : '未设置'; ?></span>
                        <span class="moment-reply-identity-label">的身份评论</span>
                    </button>
                    <?php endif; ?>
                    <div class="moment-reply-actions">
                        <button type="submit" class="moment-reply-submit">提交评论</button>
                        <button type="button" class="moment-reply-cancel" data-moment-comment-cancel>取消</button>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- 分页 -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination-wrapper">
            <nav class="page-navigator">
                <?php if ($currentPage > 1): ?>
                    <a href="<?php echo $this->permalink . '?page=' . ($currentPage - 1); ?>">上一页</a>
                <?php endif; ?>

                <span class="current">第 <?php echo $currentPage; ?> / <?php echo $totalPages; ?> 页</span>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="<?php echo $this->permalink . '?page=' . ($currentPage + 1); ?>">下一页</a>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty-moments">
            <p>还没有任何记录，快来发布第一条吧！</p>
        </div>
        <?php endif; ?>

        <!-- 发布表单 -->
        <?php if ($isMomentManager): ?>
        <div class="moment-publisher">
            <div class="publisher-header">
                <h3 class="publisher-title">发布新的记录</h3>
                <button id="open-settings" class="settings-btn" type="button" title="图床设置">
                    <i class="fa-solid fa-gear" aria-hidden="true"></i>
                    图床设置
                </button>
            </div>
            <?php if ($this->allow('comment')): ?>
            <form method="post" action="<?php $this->commentUrl(); ?>" class="publisher-form" id="moment-form">
                <div class="moment-editor" data-moment-editor>
                    <div class="moment-editor-toolbar" role="toolbar" aria-label="Markdown 工具栏">
                        <button type="button" class="moment-editor-tool" data-md-action="bold" title="粗体 Ctrl+B" aria-label="粗体"><i class="fa-solid fa-bold" aria-hidden="true"></i></button>
                        <button type="button" class="moment-editor-tool" data-md-action="italic" title="斜体 Ctrl+I" aria-label="斜体"><i class="fa-solid fa-italic" aria-hidden="true"></i></button>
                        <button type="button" class="moment-editor-tool" data-md-action="link" title="链接 Ctrl+K" aria-label="链接"><i class="fa-solid fa-link" aria-hidden="true"></i></button>
                        <button type="button" class="moment-editor-tool" data-md-action="image" title="图片" aria-label="图片"><i class="fa-regular fa-image" aria-hidden="true"></i></button>
                        <button type="button" class="moment-editor-tool" data-md-action="quote" title="引用" aria-label="引用"><i class="fa-solid fa-quote-left" aria-hidden="true"></i></button>
                        <button type="button" class="moment-editor-tool" data-md-action="code" title="行内代码 Ctrl+E" aria-label="行内代码"><i class="fa-solid fa-code" aria-hidden="true"></i></button>
                    </div>
                    <textarea name="text" id="moment-textarea" placeholder="想说些什么？支持 Markdown 语法和图片上传（粘贴图片自动上传）..." rows="6" required></textarea>
                </div>

                <!-- 上传进度条 -->
                <div class="upload-progress" id="upload-progress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <span class="progress-text">上传中...</span>
                </div>

                <input type="hidden" name="author" value="<?php echo htmlspecialchars($this->author->screenName); ?>">
                <input type="hidden" name="mail" value="<?php echo htmlspecialchars($this->author->mail); ?>">
                <input type="hidden" name="url" value="<?php echo htmlspecialchars($this->author->url); ?>">

                <?php
                $token = method_exists($this, 'security') ?
                    $this->security->getToken($this->permalink) :
                    $this->widget('Widget_Security')->getToken($this->permalink);
                echo '<input type="hidden" name="_" value="' . $token . '">';
                ?>

                <button type="submit" class="submit-button" id="sub_btn">发布</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 右侧留白 -->
    <div class="layout-spacer-right"></div>
</div>

<!-- 图床设置Modal -->
<div id="settings-modal" class="settings-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3>图床设置</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="settings-form">
                <div class="form-group">
                    <label for="base-url">图床API地址</label>
                    <input type="url" id="base-url" placeholder="https://p.bboreo.com/api/v1" value="https://p.bboreo.com/api/v1">
                </div>
                <div class="form-group">
                    <label for="email">邮箱</label>
                    <input type="email" id="email" placeholder="your@email.com">
                </div>
                <div class="form-group">
                    <label for="password">密码</label>
                    <input type="password" id="password" placeholder="密码">
                </div>
                <div class="form-group">
                    <label for="token">Token（自动生成）</label>
                    <input type="text" id="token" placeholder="将根据邮箱密码自动生成" readonly>
                </div>
                <div class="form-actions">
                    <button type="button" id="generate-token" class="btn-secondary">生成Token</button>
                    <button type="submit" class="btn-primary">保存设置</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.QIWI_MOMENTS = {
    likeEndpoint: <?php echo json_encode(isset($momentLikeEndpoint) ? $momentLikeEndpoint : '', JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    likeToken: <?php echo json_encode(isset($momentLikeToken) ? $momentLikeToken : '', JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
};

(function() {
if (window.qiwiTimemachineController) window.qiwiTimemachineController.abort();
window.qiwiTimemachineController = new AbortController();
var qiwiTimemachineSignal = window.qiwiTimemachineController.signal;
function initMomentInteractions() {
    const config = window.QIWI_MOMENTS || {};
    const composer = document.querySelector('[data-moment-comment-composer]');
    const parentInput = composer ? composer.querySelector('[data-moment-reply-parent]') : null;
    const cancelButton = composer ? composer.querySelector('[data-moment-comment-cancel]') : null;
    const form = composer ? composer.querySelector('.moment-reply-form') : null;
    const profileModal = form ? form.querySelector('[data-moment-profile-modal]') : null;
    const profileToggle = form ? form.querySelector('[data-moment-profile-toggle]') : null;
    const profileCloseButtons = form ? form.querySelectorAll('[data-moment-profile-close]') : [];
    const profileSaveButton = form ? form.querySelector('[data-moment-profile-save]') : null;
    const authorInput = form ? form.querySelector('#moment-reply-author') : null;
    const mailInput = form ? form.querySelector('#moment-reply-mail') : null;
    const urlInput = form ? form.querySelector('#moment-reply-url') : null;
    const textInput = form ? form.querySelector('#moment-reply-text') : null;
    const identityLabel = form ? form.querySelector('[data-moment-identity-label]') : null;
    const profileStorageKey = 'qiwi-comment-profile';

    if (composer) {
        composer.hidden = true;
    }

    const readProfile = function() {
        try {
            return JSON.parse(localStorage.getItem(profileStorageKey) || '{}');
        } catch (error) {
            return {};
        }
    };

    const saveProfile = function() {
        try {
            localStorage.setItem(profileStorageKey, JSON.stringify({
                author: authorInput ? authorInput.value.trim() : '',
                mail: mailInput ? mailInput.value.trim() : '',
                url: urlInput ? urlInput.value.trim() : ''
            }));
        } catch (error) {}
    };

    const hasProfile = function() {
        return !!(authorInput && authorInput.value.trim() && mailInput && mailInput.value.trim());
    };

    const updateIdentityLabel = function() {
        if (!identityLabel) return;
        identityLabel.textContent = hasProfile() ? authorInput.value.trim() : '未设置';
    };

    const getInvalidControl = function(controls) {
        for (let i = 0; i < controls.length; i++) {
            if (controls[i] && !controls[i].checkValidity()) {
                return controls[i];
            }
        }

        return null;
    };

    const reportInvalid = function(control) {
        if (!control) return;
        window.setTimeout(function() {
            control.focus();
            control.reportValidity();
        }, 0);
    };

    const openProfile = function() {
        if (!form || !profileToggle) return;
        form.classList.add('is-profile-open');
        document.documentElement.classList.add('comment-profile-open');
        document.body.classList.add('comment-profile-open');
        profileToggle.setAttribute('aria-expanded', 'true');
        window.setTimeout(function() {
            if (authorInput && !authorInput.value.trim()) {
                authorInput.focus();
            } else if (mailInput && !mailInput.value.trim()) {
                mailInput.focus();
            }
        }, 0);
    };

    const closeProfile = function(restoreFocus = true) {
        if (!form || !profileToggle) return;
        form.classList.remove('is-profile-open');
        document.documentElement.classList.remove('comment-profile-open');
        document.body.classList.remove('comment-profile-open');
        profileToggle.setAttribute('aria-expanded', 'false');
        if (restoreFocus) {
            profileToggle.focus();
        }
    };

    if (form && profileModal && profileToggle) {
        const storedProfile = readProfile();
        if (authorInput && !authorInput.value && storedProfile.author) authorInput.value = storedProfile.author;
        if (mailInput && !mailInput.value && storedProfile.mail) mailInput.value = storedProfile.mail;
        if (urlInput && !urlInput.value && storedProfile.url) urlInput.value = storedProfile.url;

        form.noValidate = true;
        form.classList.add('is-enhanced');
        profileToggle.setAttribute('aria-expanded', 'false');
        updateIdentityLabel();

        profileToggle.addEventListener('click', openProfile);

        profileCloseButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                closeProfile();
            });
        });

        if (profileSaveButton) {
            profileSaveButton.addEventListener('click', function() {
                const invalidProfileControl = getInvalidControl([authorInput, mailInput, urlInput]);
                if (invalidProfileControl) {
                    reportInvalid(invalidProfileControl);
                    return;
                }
                saveProfile();
                updateIdentityLabel();
                closeProfile();
            });
        }

        form.addEventListener('submit', function(event) {
            if (!hasProfile()) {
                event.preventDefault();
                openProfile();
                reportInvalid(getInvalidControl([authorInput, mailInput]));
                return;
            }

            const invalidProfileControl = getInvalidControl([authorInput, mailInput, urlInput]);
            if (invalidProfileControl) {
                event.preventDefault();
                openProfile();
                reportInvalid(invalidProfileControl);
                return;
            }

            const invalidTextControl = getInvalidControl([textInput]);
            if (invalidTextControl) {
                event.preventDefault();
                reportInvalid(invalidTextControl);
                return;
            }

            saveProfile();
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && form.classList.contains('is-profile-open')) {
                closeProfile();
            }
        }, { signal: qiwiTimemachineSignal });
    }

    const placeComposer = function(parentId, target) {
        if (!composer || !parentInput || !target) return;
        const body = target.closest('.moment-content, .moment-comment-body');
        if (!body) return;

        parentInput.value = parentId;
        composer.hidden = false;
        body.appendChild(composer);

        const textarea = textInput || composer.querySelector('textarea');
        if (textarea) textarea.focus();
    };

    document.querySelectorAll('[data-moment-comment-toggle]').forEach(function(button) {
        button.addEventListener('click', function() {
            const parentId = button.getAttribute('data-moment-comment-toggle');
            const expanded = button.getAttribute('aria-expanded') === 'true';
            document.querySelectorAll('[data-moment-comment-toggle]').forEach(function(other) {
                other.setAttribute('aria-expanded', 'false');
            });

            if (expanded) {
                closeProfile(false);
                if (composer) composer.hidden = true;
                return;
            }

            button.setAttribute('aria-expanded', 'true');
            placeComposer(parentId, button);
        });
    });

    document.querySelectorAll('[data-moment-reply]').forEach(function(button) {
        button.addEventListener('click', function() {
            placeComposer(button.getAttribute('data-moment-reply'), button);
        });
    });

    if (cancelButton && composer) {
        cancelButton.addEventListener('click', function() {
            closeProfile(false);
            composer.hidden = true;
            document.querySelectorAll('[data-moment-comment-toggle]').forEach(function(button) {
                button.setAttribute('aria-expanded', 'false');
            });
        });
    }

    document.querySelectorAll('[data-moment-like]').forEach(function(button) {
        button.addEventListener('click', async function() {
            const coid = button.getAttribute('data-moment-like');
            if (!config.likeEndpoint || !coid || button.disabled) return;

            const count = button.querySelector('[data-moment-like-count]');
            const icon = button.querySelector('i');
            button.disabled = true;

            try {
                const form = new FormData();
                form.append('coid', coid);
                if (config.likeToken) {
                    form.append('_', config.likeToken);
                }
                const profile = readProfile();
                if (profile && profile.author) {
                    form.append('author', profile.author);
                }
                if (profile && profile.mail) {
                    form.append('mail', profile.mail);
                }
                const response = await fetch(config.likeEndpoint, {
                    method: 'POST',
                    body: form,
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();
                if (!data || !data.success) {
                    throw new Error(data && data.message ? data.message : '点赞失败');
                }

                button.classList.toggle('is-active', !!data.liked);
                button.setAttribute('aria-pressed', data.liked ? 'true' : 'false');
                if (count) count.textContent = data.count || 0;
                if (icon) {
                    icon.classList.toggle('fa-solid', !!data.liked);
                    icon.classList.toggle('fa-regular', !data.liked);
                }
            } catch (error) {
                console.error(error);
            } finally {
                button.disabled = false;
            }
        });
    });
}
window.initMomentInteractions = initMomentInteractions;

// 时光机图片上传功能
class TimemachineUploader {
    constructor() {
        this.loadStoredSettings();
        this.initEventListeners();
        this.initMarkdownEditor();
    }

    // 加载本地存储的设置
    loadStoredSettings() {
        const settings = localStorage.getItem('timemachine_settings');
        if (settings) {
            this.settings = JSON.parse(settings);
        } else {
            this.settings = {
                baseUrl: 'https://p.bboreo.com/api/v1',
                email: '',
                password: '',
                token: ''
            };
        }
    }

    // 保存设置到本地存储
    saveSettings() {
        localStorage.setItem('timemachine_settings', JSON.stringify(this.settings));
    }

    // 初始化事件监听
    initEventListeners() {
        // 设置Modal相关
        this.initSettingsModal();

        // 粘贴图片上传
        const textarea = document.getElementById('moment-textarea');
        if (textarea) {
            textarea.addEventListener('paste', this.handlePaste.bind(this));
        }
    }

    initMarkdownEditor() {
        const editor = document.querySelector('[data-moment-editor]');
        const textarea = document.getElementById('moment-textarea');
        if (!editor || !textarea) {
            return;
        }

        const wrapSelection = (before, after, placeholder) => {
            const start = textarea.selectionStart || 0;
            const end = textarea.selectionEnd || start;
            const selected = textarea.value.slice(start, end) || placeholder || '';
            const snippet = before + selected + after;
            textarea.focus();
            if (typeof textarea.setRangeText === 'function') {
                textarea.setRangeText(snippet, start, end, 'select');
            } else {
                textarea.value = textarea.value.slice(0, start) + snippet + textarea.value.slice(end);
                textarea.selectionStart = start;
                textarea.selectionEnd = start + snippet.length;
            }
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        };

        const prefixLines = (prefix) => {
            const start = textarea.selectionStart || 0;
            const end = textarea.selectionEnd || start;
            const value = textarea.value;
            const lineStart = value.lastIndexOf('\n', Math.max(0, start - 1)) + 1;
            const lineEnd = end < value.length ? value.indexOf('\n', end) : value.length;
            const safeLineEnd = lineEnd === -1 ? value.length : lineEnd;
            const block = value.slice(lineStart, safeLineEnd);
            const next = block.split('\n').map((line) => line.indexOf(prefix) === 0 ? line.slice(prefix.length) : prefix + line).join('\n');
            textarea.focus();
            if (typeof textarea.setRangeText === 'function') {
                textarea.setRangeText(next, lineStart, safeLineEnd, 'select');
            } else {
                textarea.value = value.slice(0, lineStart) + next + value.slice(safeLineEnd);
                textarea.selectionStart = lineStart;
                textarea.selectionEnd = lineStart + next.length;
            }
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        };

        const insertLink = () => {
            const selected = textarea.value.slice(textarea.selectionStart || 0, textarea.selectionEnd || textarea.selectionStart || 0);
            const href = window.prompt('链接 URL', selected && /^https?:\/\//i.test(selected) ? selected : 'https://');
            if (!href) return;
            wrapSelection('[', '](' + href + ')', selected && !/^https?:\/\//i.test(selected) ? selected : '链接文字');
        };

        const insertImage = () => {
            const src = window.prompt('图片 URL', 'https://');
            if (!src) return;
            wrapSelection('![', '](' + src + ')', '图片描述');
        };

        const runAction = (action) => {
            if (action === 'bold') wrapSelection('**', '**', '文字');
            if (action === 'italic') wrapSelection('*', '*', '文字');
            if (action === 'code') wrapSelection('`', '`', 'code');
            if (action === 'quote') prefixLines('> ');
            if (action === 'link') insertLink();
            if (action === 'image') insertImage();
        };

        editor.addEventListener('click', (event) => {
            const button = event.target.closest('[data-md-action]');
            if (!button) return;
            runAction(button.getAttribute('data-md-action'));
        });

        textarea.addEventListener('keydown', (event) => {
            const key = String(event.key || '').toLowerCase();
            const modifier = event.ctrlKey || event.metaKey;
            if (!modifier) return;
            if (key === 'b') {
                event.preventDefault();
                runAction('bold');
            } else if (key === 'i') {
                event.preventDefault();
                runAction('italic');
            } else if (key === 'k') {
                event.preventDefault();
                runAction('link');
            } else if (key === 'e') {
                event.preventDefault();
                runAction('code');
            } else if (key === 'enter') {
                event.preventDefault();
                const form = textarea.closest('form');
                if (form && typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else if (form) {
                    form.submit();
                }
            }
        });
    }

    // 初始化设置Modal
    initSettingsModal() {
        const modal = document.getElementById('settings-modal');
        const openBtn = document.getElementById('open-settings');
        if (!modal || !openBtn) {
            return;
        }

        const closeBtn = modal.querySelector('.modal-close');
        const overlay = modal.querySelector('.modal-overlay');
        const form = document.getElementById('settings-form');
        const generateBtn = document.getElementById('generate-token');
        if (!closeBtn || !overlay || !form || !generateBtn) {
            return;
        }

        // 填充已保存的设置
        this.fillSettingsForm();

        openBtn.addEventListener('click', () => {
            modal.style.display = 'block';
            setTimeout(() => modal.classList.add('show'), 10);
        });

        const closeModal = () => {
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 300);
        };

        closeBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', closeModal);

        generateBtn.addEventListener('click', this.generateToken.bind(this));
        form.addEventListener('submit', this.saveSettingsForm.bind(this));
    }

    // 填充设置表单
    fillSettingsForm() {
        const baseUrl = document.getElementById('base-url');
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        const token = document.getElementById('token');
        if (!baseUrl || !email || !password || !token) {
            return;
        }

        baseUrl.value = this.settings.baseUrl;
        email.value = this.settings.email;
        password.value = this.settings.password;
        token.value = this.settings.token;
    }

    // 生成Token
    async generateToken() {
        const baseUrl = document.getElementById('base-url').value;
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;

        if (!baseUrl || !email || !password) {
            alert('请填写完整的图床地址、邮箱和密码');
            return;
        }

        const generateBtn = document.getElementById('generate-token');
        const originalText = generateBtn.textContent;
        generateBtn.textContent = '生成中...';
        generateBtn.disabled = true;

        try {
            const response = await fetch(`${baseUrl}/tokens`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ email, password })
            });

            const data = await response.json();

            if (data.status && data.data && data.data.token) {
                document.getElementById('token').value = data.data.token;
                alert('Token生成成功！');
            } else {
                throw new Error(data.message || '生成Token失败');
            }
        } catch (error) {
            console.error('生成Token失败:', error);
            alert('生成Token失败: ' + error.message);
        } finally {
            generateBtn.textContent = originalText;
            generateBtn.disabled = false;
        }
    }

    // 保存设置表单
    saveSettingsForm(e) {
        e.preventDefault();

        this.settings = {
            baseUrl: document.getElementById('base-url').value,
            email: document.getElementById('email').value,
            password: document.getElementById('password').value,
            token: document.getElementById('token').value
        };

        this.saveSettings();

        // 关闭Modal
        const modal = document.getElementById('settings-modal');
        modal.classList.remove('show');
        setTimeout(() => modal.style.display = 'none', 300);

        alert('设置已保存！');
    }

    // 处理粘贴事件
    async handlePaste(e) {
        const items = e.clipboardData.items;

        for (let item of items) {
            if (item.type.indexOf('image') !== -1) {
                e.preventDefault();
                const file = item.getAsFile();
                await this.uploadImage(file);
                break;
            }
        }
    }

    // 上传图片
    async uploadImage(file) {
        if (!this.settings.token) {
            alert('请先配置图床设置！');
            return;
        }

        const progressEl = document.getElementById('upload-progress');
        const textarea = document.getElementById('moment-textarea');
        if (!progressEl || !textarea) {
            return;
        }

        const progressFill = progressEl.querySelector('.progress-fill');
        const progressText = progressEl.querySelector('.progress-text');
        if (!progressFill || !progressText) {
            return;
        }

        progressEl.style.display = 'flex';
        progressText.textContent = '上传中...';

        try {
            const formData = new FormData();
            formData.append('file', file);

            const response = await fetch(`${this.settings.baseUrl}/upload`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.settings.token}`,
                    'Accept': 'application/json'
                },
                body: formData
            });

            const data = await response.json();

            if (data.status && data.data && data.data.links) {
                const imageUrl = data.data.links.url;
                const markdown = `![${data.data.name}](${imageUrl})`;

                // 插入到光标位置
                const cursorPos = textarea.selectionStart;
                const textBefore = textarea.value.substring(0, cursorPos);
                const textAfter = textarea.value.substring(textarea.selectionEnd);
                textarea.value = textBefore + markdown + textAfter;

                // 更新光标位置
                textarea.selectionStart = textarea.selectionEnd = cursorPos + markdown.length;
                textarea.focus();

                progressText.textContent = '上传成功！';
                setTimeout(() => {
                    progressEl.style.display = 'none';
                }, 1000);
            } else {
                throw new Error(data.message || '上传失败');
            }
        } catch (error) {
            console.error('上传失败:', error);
            progressText.textContent = '上传失败: ' + error.message;
            setTimeout(() => {
                progressEl.style.display = 'none';
            }, 3000);
        }
    }
}

// 初始化上传器
document.addEventListener('DOMContentLoaded', function() {
    initMomentInteractions();
    if (document.getElementById('moment-textarea') || document.getElementById('open-settings')) {
        const uploader = new TimemachineUploader();
    }
});
})();
</script>

<?php $this->need('footer.php'); ?>
