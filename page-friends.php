<?php
/**
 * 友链页面模板
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$qiwiShowToc = qiwiShouldShowToc($this);
$this->need('header.php');
$pageContent = qiwiGetContent($this);
$friendsSubtitle = trim((string) $this->fields->friendsSubtitle);
$friendFeedEnabled = (string) $this->options->friendFeedEnabled === '1';
$friendFeedBaseUrl = rtrim(trim((string) $this->options->friendFeedBaseUrl), "/ \t\n\r\0\x0B");
$friendFeedLimit = (int) $this->options->friendFeedLimit;
if ($friendFeedLimit <= 0) {
    $friendFeedLimit = 10;
}
$friendFeedLimit = min(100, max(1, $friendFeedLimit));
$friendFeedReady = $friendFeedEnabled && $friendFeedBaseUrl !== '';
$friendsRememberAuthor = trim((string) $this->remember('author', true));
$friendsRememberMail = trim((string) $this->remember('mail', true));
$friendsWaitingCount = 0;

if (!$this->user->hasLogin() && $friendsRememberAuthor !== '' && $friendsRememberMail !== '') {
    $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
    $prefix = $db->getPrefix();
    $friendsWaitingCount = (int) $db->fetchObject($db->select('COUNT(coid) AS total')
        ->from($prefix . 'comments')
        ->where('cid = ?', $this->cid)
        ->where('status = ?', 'waiting')
        ->where('author = ?', $friendsRememberAuthor)
        ->where('mail = ?', $friendsRememberMail))->total;
}
?>

<div class="friends-page">
    <!-- 左侧留白 -->
    <div class="layout-spacer-left"></div>

    <!-- 主内容 -->
    <div class="friends-main">
        <!-- 页面头部 -->
        <header class="friends-header">
            <h1 class="page-title"><?php $this->title(); ?></h1>
            <?php if ($friendsSubtitle !== ''): ?>
                <p class="page-intro"><?php echo htmlspecialchars($friendsSubtitle, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </header>

        <!-- 友链列表 -->
        <?php if ($friendFeedReady): ?>
        <div class="friends-tabs" data-friends-tabs>
            <div class="friends-tabs-head">
                <div class="friends-tab-list" role="tablist" aria-label="友链页面内容">
                    <button type="button" class="friends-tab is-active" id="friends-tab-links" role="tab" aria-selected="true" aria-controls="friends-panel-links" data-friends-tab="links">友链</button>
                    <button type="button" class="friends-tab" id="friends-tab-feed" role="tab" aria-selected="false" aria-controls="friends-panel-feed" data-friends-tab="feed">动态</button>
                </div>
                <nav class="friend-feed-pager friend-feed-pager-top" aria-label="朋友圈动态分页" data-friend-feed-pager>
                    <button type="button" data-feed-page-action="first">首页</button>
                    <button type="button" data-feed-page-action="prev">上一页</button>
                    <span data-feed-page-label>第 1 页</span>
                    <button type="button" data-feed-page-action="next">下一页</button>
                </nav>
            </div>
        <?php endif; ?>

        <div class="friends-container<?php echo $friendFeedReady ? ' friends-tab-panel is-active' : ''; ?>"<?php echo $friendFeedReady ? ' id="friends-panel-links" role="tabpanel" aria-labelledby="friends-tab-links" data-friends-panel="links"' : ''; ?>>
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

        <?php if ($friendFeedReady): ?>
        <div class="friends-feed friends-tab-panel" id="friends-panel-feed" role="tabpanel" aria-labelledby="friends-tab-feed" data-friends-panel="feed" hidden>
            <ul class="friend-feed-list" data-friend-feed-list>
                <li class="friend-feed-state">正在读取最近文章...</li>
            </ul>
            <nav class="friend-feed-pager friend-feed-pager-bottom" aria-label="朋友圈动态分页" data-friend-feed-pager>
                <button type="button" data-feed-page-action="first">首页</button>
                <button type="button" data-feed-page-action="prev">上一页</button>
                <span data-feed-page-label>第 1 页</span>
                <button type="button" data-feed-page-action="next">下一页</button>
            </nav>
        </div>
        </div>

        <script>
        (function() {
            var root = document.querySelector('[data-friends-tabs]');
            if (!root) return;

            var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-friends-tab]'));
            var panels = Array.prototype.slice.call(root.querySelectorAll('[data-friends-panel]'));
            var list = root.querySelector('[data-friend-feed-list]');
            var pagers = Array.prototype.slice.call(root.querySelectorAll('[data-friend-feed-pager]'));
            var pageButtons = Array.prototype.slice.call(root.querySelectorAll('[data-feed-page-action]'));
            var pageLabels = Array.prototype.slice.call(root.querySelectorAll('[data-feed-page-label]'));
            var activeTabKey = 'qiwi:friends-active-tab:' + window.location.pathname;
            var feedPageKey = 'qiwi:friends-feed-page:' + window.location.pathname + ':' + configSafeKey(<?php echo json_encode($friendFeedBaseUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
            var pageSize = <?php echo (int) $friendFeedLimit; ?>;
            var currentPage = readStoredPage();
            var isLoadingFeed = false;
            var hasNextPage = true;
            var randomImageEndpoints = [
                'https://bing.biturl.top/?resolution=1920&format=json&index=random&mkt=zh-CN',
                'https://bingw.jasonzeng.dev/?index=random&resolution=1920x1080&format=json'
            ];
            var config = {
                baseUrl: <?php echo json_encode($friendFeedBaseUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
                limit: pageSize
            };

            function configSafeKey(value) {
                return String(value || '').replace(/[^a-z0-9]+/gi, '-').slice(0, 80);
            }

            function readStoredPage() {
                try {
                    return Math.max(1, parseInt(localStorage.getItem(feedPageKey) || '1', 10) || 1);
                } catch (error) {
                    return 1;
                }
            }

            function writeStoredPage(page) {
                try {
                    localStorage.setItem(feedPageKey, String(page));
                } catch (error) {}
            }

            function readStoredTab() {
                try {
                    var value = localStorage.getItem(activeTabKey);
                    return value === 'feed' ? 'feed' : 'links';
                } catch (error) {
                    return 'links';
                }
            }

            function writeStoredTab(target) {
                try {
                    localStorage.setItem(activeTabKey, target === 'feed' ? 'feed' : 'links');
                } catch (error) {}
            }

            function updatePager() {
                pageLabels.forEach(function(label) {
                    label.textContent = '第 ' + currentPage + ' 页';
                });
                pageButtons.forEach(function(button) {
                    var action = button.getAttribute('data-feed-page-action');
                    button.disabled = isLoadingFeed || (action !== 'next' && currentPage <= 1) || (action === 'next' && !hasNextPage);
                });
            }

            function scrollFeedTop() {
                if (!root.scrollIntoView) return;
                root.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            function activate(target, persist) {
                tabs.forEach(function(tab) {
                    var active = tab.getAttribute('data-friends-tab') === target;
                    tab.classList.toggle('is-active', active);
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                    tab.setAttribute('tabindex', active ? '0' : '-1');
                });
                panels.forEach(function(panel) {
                    var active = panel.getAttribute('data-friends-panel') === target;
                    panel.classList.toggle('is-active', active);
                    panel.hidden = !active;
                });
                root.classList.toggle('is-feed-active', target === 'feed');
                if (persist !== false) writeStoredTab(target);
                if (target === 'feed') loadFeed(currentPage, false);
                updatePager();
            }

            function escapeHtml(value) {
                return String(value || '').replace(/[&<>"']/g, function(char) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    }[char];
                });
            }

            function formatDate(value) {
                var date = new Date(value || '');
                if (isNaN(date.getTime())) return '';
                return new Intl.DateTimeFormat('zh-CN', {
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                }).format(date);
            }

            function safeHttpUrl(value) {
                value = String(value || '').replace(/^\s+|\s+$/g, '');
                if (!value) return '#';
                try {
                    var url = new URL(value, window.location.href);
                    return /^(https?:)$/.test(url.protocol) ? url.href : '#';
                } catch (error) {
                    return '#';
                }
            }

            function safeImageUrl(value) {
                var url = safeHttpUrl(value);
                if (url === '#') return '#';
                if (/\.(?:avif|bmp|gif|jpe?g|png|webp)(?:[?#]|$)/i.test(url)) return url;
                if (/\/th\?/i.test(url)) return url;
                return '#';
            }

            function cssUrl(value) {
                return String(value || '').replace(/["\\\r\n]/g, function(char) {
                    return '\\' + char;
                });
            }

            function setFeedBackground(item, url) {
                if (!item || !url || url === '#') return;
                item.style.setProperty('--friend-feed-bg', 'url("' + cssUrl(url) + '")');
                item.classList.add('has-background');
            }

            function fallbackImageCacheKey(entry) {
                return 'qiwi:friend-feed-bg:' + String((entry && (entry.id || entry.url)) || '').slice(0, 160);
            }

            function readCachedFallbackImage(key) {
                if (!key) return '';
                try {
                    return localStorage.getItem(key) || '';
                } catch (error) {
                    return '';
                }
            }

            function writeCachedFallbackImage(key, url) {
                if (!key || !url || url === '#') return;
                try {
                    localStorage.setItem(key, url);
                } catch (error) {}
            }

            function fetchRandomImage(index) {
                index = index || 0;
                var endpoint = randomImageEndpoints[index];
                if (!endpoint || !window.fetch) return Promise.reject(new Error('no image api'));

                return fetch(endpoint, { cache: 'no-store' })
                    .then(function(response) {
                        if (!response.ok) throw new Error('HTTP ' + response.status);
                        return response.json();
                    })
                    .then(function(data) {
                        var url = safeImageUrl(data && (data.url || data.image || data.image_url));
                        if (url === '#') throw new Error('empty image url');
                        return url;
                    })
                    .catch(function(error) {
                        if (index + 1 < randomImageEndpoints.length) {
                            return fetchRandomImage(index + 1);
                        }
                        throw error;
                    });
            }

            function resolveFallbackBackground(item) {
                if (!item) return;
                var cacheKey = item.getAttribute('data-feed-fallback-key') || '';
                var cached = safeImageUrl(readCachedFallbackImage(cacheKey));
                if (cached !== '#') {
                    setFeedBackground(item, cached);
                    return;
                }

                fetchRandomImage(0)
                    .then(function(url) {
                        writeCachedFallbackImage(cacheKey, url);
                        setFeedBackground(item, url);
                    })
                    .catch(function() {});
            }

            function applyFeedBackgrounds() {
                Array.prototype.slice.call(list.querySelectorAll('[data-feed-bg]')).forEach(function(item) {
                    setFeedBackground(item, item.getAttribute('data-feed-bg'));
                });
                Array.prototype.slice.call(list.querySelectorAll('[data-feed-fallback-bg]')).forEach(resolveFallbackBackground);
            }

            function renderEntries(entries) {
                if (!list) return;
                if (!entries.length) {
                    list.innerHTML = '<li class="friend-feed-state">暂时还没有抓取到文章。</li>';
                    return;
                }

                list.innerHTML = entries.map(function(entry) {
                    var author = String(entry.author || '').replace(/^\s+|\s+$/g, '');
                    var site = entry.feed_title || '未命名站点';
                    var time = formatDate(entry.published_at || entry.fetched_at);
                    var url = safeHttpUrl(entry.url);
                    var siteUrl = safeHttpUrl(entry.feed_site_url);
                    var imageUrl = safeImageUrl(entry.image_url);
                    var hasImage = imageUrl !== '#';
                    var fallbackKey = fallbackImageCacheKey(entry);
                    return '<li class="friend-feed-item" ' + (hasImage ? 'data-feed-bg="' + escapeHtml(imageUrl) + '"' : 'data-feed-fallback-bg="1" data-feed-fallback-key="' + escapeHtml(fallbackKey) + '"') + '>' +
                        '<span class="friend-feed-bg" aria-hidden="true"></span>' +
                        '<article class="friend-feed-card">' +
                            '<div class="friend-feed-content">' +
                            '<div class="article-meta friend-feed-meta">' +
                                (time ? '<span>' + escapeHtml(time) + '</span>' : '') +
                                (author && author !== site ? '<span>' + escapeHtml(author) + '</span>' : '') +
                                '<span><a class="friend-feed-site-link" href="' + escapeHtml(siteUrl) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(site) + '</a></span>' +
                            '</div>' +
                            '<h2 class="article-title friend-feed-title"><a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer" class="article-title-link">' + escapeHtml(entry.title || '未命名文章') + '</a></h2>' +
                            '<p class="article-excerpt friend-feed-summary">' + escapeHtml(entry.summary || '这篇文章暂时没有摘要。') + '</p>' +
                            '</div>' +
                        '</article>' +
                    '</li>';
                }).join('');
                applyFeedBackgrounds();
            }

            function loadFeed(page, shouldScroll) {
                page = Math.max(1, parseInt(page || 1, 10) || 1);
                if (!list || isLoadingFeed) return;
                if (!window.fetch) {
                    list.innerHTML = '<li class="friend-feed-state">当前浏览器无法读取朋友圈动态。</li>';
                    return;
                }
                isLoadingFeed = true;
                currentPage = page;
                writeStoredPage(currentPage);
                updatePager();
                list.innerHTML = '<li class="friend-feed-state">正在读取最近文章...</li>';
                var endpoint = config.baseUrl.replace(/\/+$/g, '') + '/api/public/entries?limit=' + encodeURIComponent(pageSize) + '&offset=' + encodeURIComponent((currentPage - 1) * pageSize);
                fetch(endpoint, { cache: 'no-store' })
                    .then(function(response) {
                        if (!response.ok) throw new Error('HTTP ' + response.status);
                        return response.json();
                    })
                    .then(function(payload) {
                        var entries = Array.isArray(payload && payload.data) ? payload.data : [];
                        hasNextPage = entries.length >= pageSize;
                        renderEntries(entries);
                        if (shouldScroll) scrollFeedTop();
                    })
                    .catch(function() {
                        hasNextPage = false;
                        list.innerHTML = '<li class="friend-feed-state">暂时无法读取朋友圈动态。</li>';
                    })
                    .then(function() {
                        isLoadingFeed = false;
                        updatePager();
                    });
            }

            tabs.forEach(function(tab) {
                tab.addEventListener('click', function() {
                    activate(tab.getAttribute('data-friends-tab'));
                });
                tab.addEventListener('keydown', function(event) {
                    if (!/^(ArrowLeft|ArrowRight)$/.test(event.key)) return;
                    event.preventDefault();
                    var index = tabs.indexOf(tab);
                    var offset = event.key === 'ArrowLeft' ? -1 : 1;
                    var next = tabs[(index + offset + tabs.length) % tabs.length];
                    activate(next.getAttribute('data-friends-tab'));
                    next.focus();
                });
            });

            pageButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    var action = button.getAttribute('data-feed-page-action');
                    if (action === 'first') loadFeed(1, true);
                    if (action === 'prev') loadFeed(Math.max(1, currentPage - 1), true);
                    if (action === 'next') loadFeed(currentPage + 1, true);
                });
            });

            updatePager();
            activate(readStoredTab(), false);
        })();
        </script>
        <?php endif; ?>

        <?php if (qiwiHasRenderedContent($pageContent)): ?>
        <div class="friends-extra article-body">
            <?php echo $pageContent; ?>
        </div>
        <?php endif; ?>

        <!-- 友链申请 -->
        <?php if ($this->allow('comment')): ?>
        <div class="friends-apply">
            <h3 class="apply-title">申请友链</h3>
            <p class="apply-desc">欢迎交换友链，请填写以下信息</p>
            <?php if ($friendsWaitingCount > 0): ?>
            <p class="friends-apply-status">您的评论正在等待审核</p>
            <?php endif; ?>

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

                <?php if ($this->options->enabledCaptcha && function_exists('qiwiCanRenderCaptcha') && qiwiCanRenderCaptcha()): ?>
                <div class="captcha-script">
                    <?php qiwiRenderCaptcha(); ?>
                    <script src="https://cdn.jsdelivr.net/npm/jquery@2.2.4/dist/jquery.min.js"></script>
                </div>
                <?php endif; ?>
                <br>

                <button type="submit" class="submit-button" id="sub_btn">提交申请</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($qiwiShowToc): ?>
    <!-- 页面目录 -->
    <nav class="article-toc" aria-label="页面目录"></nav>
    <?php endif; ?>

    <!-- 右侧留白 -->
    <div class="layout-spacer-right"></div>
</div>

<?php $this->need('footer.php'); ?>
