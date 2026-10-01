(function () {
    'use strict';

    var containerSelector = '#qiwi-pjax';
    var activeRequest = null;
    var navigationId = 0;
    var dynamicPageListeners = [];
    var lastKnownUrl = window.location.href;
    var tocObserver = null;
    var tocProgressCleanup = null;
    var momentTextFoldFrame = null;
    var latestMomentTimer = null;
    var stickerPackCache = {};
    var pjaxReady = Boolean(window.fetch && window.DOMParser && window.AbortController && window.history && window.history.pushState);

    function currentContainer() {
        return document.querySelector(containerSelector);
    }

    function prefersReducedMotion() {
        return Boolean(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    function setStatus(message) {
        var status = document.getElementById('qiwi-pjax-status');
        if (status) status.textContent = message || '';
    }

    function setMobileMenu(open) {
        var menu = document.getElementById('v2-mobile-nav');
        var button = document.querySelector('.v2-menu-toggle');
        if (!menu || !button) return;
        menu.hidden = !open;
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        button.setAttribute('aria-label', open ? '关闭菜单' : '打开菜单');
    }

    function initGlobalNavigation() {
        if (document.documentElement.dataset.qiwiV2Navigation === '1') return;
        document.documentElement.dataset.qiwiV2Navigation = '1';

        document.addEventListener('click', function (event) {
            var menuButton = event.target.closest('.v2-menu-toggle');
            if (menuButton) {
                setMobileMenu(menuButton.getAttribute('aria-expanded') !== 'true');
                return;
            }

            var submenuButton = event.target.closest('.v2-submenu-toggle');
            if (submenuButton) {
                var submenu = document.getElementById(submenuButton.getAttribute('aria-controls'));
                if (!submenu) return;
                var open = submenuButton.getAttribute('aria-expanded') !== 'true';
                submenuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
                submenu.hidden = !open;
                return;
            }

            if (!event.target.closest('.v2-mobile-nav, .v2-menu-toggle')) setMobileMenu(false);
            if (!event.target.closest('[data-reading-control]')) {
                document.querySelectorAll('[data-reading-control].is-open').forEach(function (control) {
                    control.classList.remove('is-open');
                    var trigger = control.querySelector('[data-reading-trigger]');
                    if (trigger) trigger.setAttribute('aria-expanded', 'false');
                });
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') setMobileMenu(false);
        });
    }

    function normalizeUrl(value) {
        try {
            return new URL(value, window.location.href);
        } catch (error) {
            return null;
        }
    }

    function shouldHandleLink(event, link) {
        if (!pjaxReady || !link || event.defaultPrevented || event.button !== 0) return false;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
        if (link.target && link.target !== '_self') return false;
        if (link.hasAttribute('download') || link.dataset.noPjax !== undefined) return false;

        var url = normalizeUrl(link.href);
        if (!url || url.origin !== window.location.origin) return false;
        if (!/^https?:$/.test(url.protocol)) return false;
        if (/\/(admin|action)\//i.test(url.pathname)) return false;
        if (/\.(?:xml|rss|atom|json|zip|pdf|jpe?g|png|gif|webp|svg|mp4|mp3)$/i.test(url.pathname)) return false;
        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return false;
        return true;
    }

    function locateAnchorTarget(hash) {
        if (!hash) return null;
        var anchorId = hash.charAt(0) === '#' ? hash.slice(1) : hash;
        if (anchorId === '') return null;
        try { anchorId = decodeURIComponent(anchorId); } catch (error) {}
        return document.getElementById(anchorId);
    }

    function highlightCommentItem(item) {
        if (!item) return;
        item.classList.remove('is-highlighted');
        void item.offsetHeight;
        item.classList.add('is-highlighted');
        item.addEventListener('animationend', function handler(event) {
            if (event.animationName !== 'qiwiCommentHighlight') return;
            item.removeEventListener('animationend', handler);
            item.classList.remove('is-highlighted');
        });
    }

    function revealAnchorFromHistory() {
        var anchor = locateAnchorTarget(window.location.hash);
        if (!anchor) return;
        anchor.scrollIntoView({ behavior: 'auto', block: anchor.classList.contains('comment-item') ? 'center' : 'start' });
        if (anchor.classList.contains('comment-item')) highlightCommentItem(anchor);
    }

    function updateNavigation(url) {
        var target = normalizeUrl(url);
        if (!target) return;
        document.querySelectorAll('.v2-nav a, .v2-mobile-nav a').forEach(function (link) {
            var linkUrl = normalizeUrl(link.href);
            var current = Boolean(linkUrl && linkUrl.origin === target.origin && linkUrl.pathname === target.pathname);
            link.classList.toggle('current', current);
            if (current) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        });
    }

    function updateHead(nextDocument) {
        var pendingScripts = [];
        document.title = nextDocument.title || document.title;
        ['description', 'keywords'].forEach(function (name) {
            var current = document.head.querySelector('meta[name="' + name + '"]');
            var next = nextDocument.head.querySelector('meta[name="' + name + '"]');
            if (current && next) current.setAttribute('content', next.getAttribute('content') || '');
        });
        ['canonical'].forEach(function (rel) {
            var current = document.head.querySelector('link[rel="' + rel + '"]');
            var next = nextDocument.head.querySelector('link[rel="' + rel + '"]');
            if (current && next) current.href = next.href;
        });

        nextDocument.head.querySelectorAll('link[rel="stylesheet"][href]').forEach(function (link) {
            var href = link.href;
            var exists = Array.prototype.some.call(document.styleSheets, function (sheet) { return sheet.href === href; });
            if (!href || exists) return;
            var clone = document.createElement('link');
            clone.rel = 'stylesheet';
            clone.href = href;
            if (link.crossOrigin) clone.crossOrigin = link.crossOrigin;
            document.head.appendChild(clone);
        });
        nextDocument.head.querySelectorAll('script[src]').forEach(function (script) {
            var exists = Array.prototype.some.call(document.scripts, function (item) { return item.src === script.src; });
            if (exists) return;
            var clone = document.createElement('script');
            clone.src = script.src;
            clone.async = false;
            pendingScripts.push(new Promise(function (resolve) {
                // 与 executeScripts 一致加 10s 兜底：CDN 脚本既不 load 也不 error 时，
                // 否则 headReady 永不 resolve，主列会一直停在 is-leaving（不可点击）。
                var timerId = window.setTimeout(resolve, 10000);
                var settle = function () {
                    window.clearTimeout(timerId);
                    resolve();
                };
                clone.addEventListener('load', settle, { once: true });
                clone.addEventListener('error', settle, { once: true });
            }));
            document.head.appendChild(clone);
        });

        return Promise.all(pendingScripts);
    }

    function initLatex(root, nextDocument) {
        if (!nextDocument || !nextDocument.head.querySelector('script[src*="auto-render.min.js"]')) return;
        if (typeof window.renderMathInElement !== 'function') return;
        window.renderMathInElement(root, {
            delimiters: [
                { left: '$$', right: '$$', display: true },
                { left: '$', right: '$', display: false },
                { left: '\\(', right: '\\)', display: false },
                { left: '\\[', right: '\\]', display: true }
            ],
            throwOnError: false,
            strict: 'ignore',
            ignoredTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code'],
            ignoredClasses: ['nokatex']
        });
    }

    function appendTypechoCommentTokenScript(nextDocument, root) {
        if (!nextDocument || !root) return;
        nextDocument.head.querySelectorAll('script:not([src])').forEach(function (oldScript) {
            var source = oldScript.textContent || '';
            if (source.indexOf("input.name = '_'") === -1 || source.indexOf('form.appendChild(input)') === -1) return;
            var script = document.createElement('script');
            Array.prototype.slice.call(oldScript.attributes).forEach(function (attribute) {
                script.setAttribute(attribute.name, attribute.value);
            });
            script.textContent = source;
            root.appendChild(script);
        });
    }

    var listenerPatchDepth = 0;
    var listenerPatchNative = null;

    // 记录范围必须包含 document/window：Typecho 反垃圾 token 脚本每次 PJAX
    // 执行都会往 window 挂 scroll/mousemove/keyup/touchstart 监听器，只能靠
    // dynamicPageListeners 在下次导航时移除，收窄到容器子树会让它们累积泄漏。
    function installListenerPatch() {
        if (listenerPatchDepth === 0) {
            listenerPatchNative = EventTarget.prototype.addEventListener;
            EventTarget.prototype.addEventListener = function (type, listener, options) {
                if (this === document && type === 'DOMContentLoaded' && document.readyState !== 'loading') {
                    Promise.resolve().then(function () { listener.call(document, new Event('DOMContentLoaded')); });
                    return;
                }
                dynamicPageListeners.push({ target: this, type: type, listener: listener, options: options });
                return listenerPatchNative.call(this, type, listener, options);
            };
        }
        listenerPatchDepth++;
    }

    function uninstallListenerPatch() {
        listenerPatchDepth = Math.max(0, listenerPatchDepth - 1);
        if (listenerPatchDepth === 0 && listenerPatchNative) {
            EventTarget.prototype.addEventListener = listenerPatchNative;
            listenerPatchNative = null;
        }
    }

    function executeScripts(root, requestId) {
        var scripts = Array.prototype.slice.call(root.querySelectorAll('script'));
        var chain = Promise.resolve();
        installListenerPatch();
        scripts.forEach(function (oldScript) {
            chain = chain.then(function () {
                return new Promise(function (resolve) {
                    // 过期导航的脚本链直接终止，避免旧链继续执行脚本、注册监听器
                    if (requestId !== undefined && requestId !== navigationId) {
                        try { oldScript.remove(); } catch (error) {}
                        resolve();
                        return;
                    }
                    var script = document.createElement('script');
                    Array.prototype.slice.call(oldScript.attributes).forEach(function (attribute) {
                        script.setAttribute(attribute.name, attribute.value);
                    });
                    if (oldScript.src) {
                        var existing = Array.prototype.some.call(document.scripts, function (item) { return item !== oldScript && item.src === oldScript.src; });
                        if (existing) {
                            oldScript.remove();
                            resolve();
                            return;
                        }
                        var timerId = null;
                        var settle = function () {
                            if (timerId !== null) window.clearTimeout(timerId);
                            resolve();
                        };
                        // 挂起的外部脚本加载加兜底超时，避免补丁窗口被无限拉长
                        timerId = window.setTimeout(settle, 10000);
                        script.addEventListener('load', settle, { once: true });
                        script.addEventListener('error', settle, { once: true });
                        // 按插入顺序执行；否则超时兜底放行后续脚本后，慢脚本可能晚于依赖它的脚本执行
                        if (!script.hasAttribute('async')) script.async = false;
                        oldScript.replaceWith(script);
                    } else {
                        script.textContent = oldScript.textContent;
                        oldScript.replaceWith(script);
                        resolve();
                    }
                });
            });
        });
        return chain.finally(uninstallListenerPatch);
    }

    function cleanupDynamicPageListeners() {
        dynamicPageListeners.forEach(function (entry) {
            try { entry.target.removeEventListener(entry.type, entry.listener, entry.options); } catch (error) {}
        });
        dynamicPageListeners = [];
    }

    function initLatestMoment(root) {
        if (latestMomentTimer !== null) {
            window.clearInterval(latestMomentTimer);
            latestMomentTimer = null;
        }
        var moment = root.querySelector('[data-latest-moment]');
        if (!moment || prefersReducedMotion()) return;
        var items = Array.prototype.slice.call(moment.querySelectorAll('[data-latest-moment-item]'));
        if (items.length < 2) return;
        var activeIndex = Math.max(0, items.findIndex(function (item) { return item.classList.contains('is-active'); }));
        latestMomentTimer = window.setInterval(function () {
            items[activeIndex].classList.remove('is-active');
            items[activeIndex].setAttribute('aria-hidden', 'true');
            activeIndex = (activeIndex + 1) % items.length;
            items[activeIndex].classList.add('is-active');
            items[activeIndex].setAttribute('aria-hidden', 'false');
        }, 5000);
    }

    function initReadingPreferences(root) {
        var defaults = { font: 'plain', spacing: 'wide', size: 'medium' };
        var labels = {
            font: { readable: '易读', plain: '普通', mono: '等宽' },
            spacing: { wide: '宽', compact: '窄' },
            size: { large: '大', medium: '中', small: '小' }
        };
        var stored = {};
        try { stored = JSON.parse(localStorage.getItem('qiwi-article-reading-v1') || localStorage.getItem('qiwi-reading-preferences') || '{}'); } catch (error) { stored = {}; }
        Object.keys(defaults).forEach(function (key) {
            if (!labels[key][stored[key]]) stored[key] = defaults[key];
        });
        root.querySelectorAll('[data-reading-control]').forEach(function (control) {
            if (control.dataset.v2Ready === '1') return;
            control.dataset.v2Ready = '1';
            var trigger = control.querySelector('[data-reading-trigger]');
            if (trigger) trigger.addEventListener('click', function (event) {
                event.stopPropagation();
                var open = control.classList.toggle('is-open');
                trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
            control.querySelectorAll('[data-reading-option]').forEach(function (button) {
                button.addEventListener('click', function (event) {
                    event.stopPropagation();
                    stored[button.dataset.readingOption] = button.dataset.readingValue;
                    try {
                        localStorage.setItem('qiwi-article-reading-v1', JSON.stringify(stored));
                        localStorage.setItem('qiwi-reading-preferences', JSON.stringify(stored));
                    } catch (error) {}
                    applyReadingPreferences(stored, root, labels);
                });
            });
        });
        applyReadingPreferences(stored, root, labels);
    }

    function applyReadingPreferences(preferences, scope, labels) {
        var html = document.documentElement;
        ['font', 'spacing', 'size'].forEach(function (key) {
            if (!preferences[key]) return;
            html.setAttribute('data-reading-' + key, preferences[key]);
            html.setAttribute('data-qiwi-reading-' + key, preferences[key]);
            (scope || document).querySelectorAll('[data-reading-control]').forEach(function (control) {
                var label = control.querySelector('[data-reading-label="' + key + '"]');
                if (label && labels && labels[key]) label.textContent = labels[key][preferences[key]] || '';
                control.querySelectorAll('[data-reading-option="' + key + '"]').forEach(function (button) {
                    var active = button.dataset.readingValue === preferences[key];
                    button.classList.toggle('is-active', active);
                    button.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
            });
        });
    }

    var confettiPromise = null;
    function celebrateLike(target) {
        if (!confettiPromise) {
            confettiPromise = new Promise(function (resolve) {
                if (window.confetti) { resolve(window.confetti); return; }
                var script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js';
                script.async = true;
                script.onload = function () { resolve(window.confetti || null); };
                script.onerror = function () { resolve(null); };
                document.head.appendChild(script);
            });
        }
        confettiPromise.then(function (confetti) {
            var rect = target.getBoundingClientRect();
            if (!confetti) {
                var layer = document.createElement('div');
                layer.className = 'v2-confetti';
                for (var i = 0; i < 24; i += 1) {
                    var piece = document.createElement('i');
                    piece.style.left = (rect.left + rect.width / 2) + 'px';
                    piece.style.top = (rect.top + rect.height / 2) + 'px';
                    piece.style.setProperty('--x', (Math.random() * 180 - 90) + 'px');
                    piece.style.setProperty('--y', (Math.random() * -150 - 30) + 'px');
                    piece.style.setProperty('--r', (Math.random() * 420 - 210) + 'deg');
                    layer.appendChild(piece);
                }
                document.body.appendChild(layer);
                window.setTimeout(function () { layer.remove(); }, 900);
                return;
            }
            confetti({
                particleCount: 58,
                spread: 62,
                scalar: .78,
                colors: ['#d4c4b0', '#e0ad7d', '#b3402e', '#8b7355'],
                origin: {
                    x: Math.max(0, Math.min(1, (rect.left + rect.width / 2) / window.innerWidth)),
                    y: Math.max(0, Math.min(1, (rect.top + rect.height / 2) / window.innerHeight))
                }
            });
        });
    }

    function initCommentProfiles(root) {
        root.querySelectorAll('.comment-form').forEach(function (form) {
            if (form.dataset.v2ProfileReady === '1') return;
            form.dataset.v2ProfileReady = '1';
            var panel = form.querySelector('[data-comment-profile-modal]');
            var toggle = form.querySelector('[data-comment-profile-toggle]');
            if (!panel || !toggle) return;
            var overflowOwner = form.closest('.comment-respond, .moment-reply-composer');
            var save = panel.querySelector('[data-comment-profile-save]');
            var author = panel.querySelector('[name="author"]');
            var mail = panel.querySelector('[name="mail"]');
            var url = panel.querySelector('[name="url"]');
            var fields = [author, mail, url].filter(Boolean);
            form.classList.add('is-enhanced');

            try {
                var stored = JSON.parse(localStorage.getItem('qiwi-comment-profile') || '{}');
                if (author && !author.value && stored.author) author.value = stored.author;
                if (mail && !mail.value && stored.mail) mail.value = stored.mail;
                if (url && !url.value && stored.url) url.value = stored.url;
            } catch (error) {}

            function setOpen(open) {
                if (open) {
                    form.classList.remove('is-sticker-open');
                    if (overflowOwner) overflowOwner.classList.remove('is-sticker-open');
                    var stickerToggle = form.querySelector('[data-comment-sticker-toggle]');
                    var stickerPanel = form.querySelector('[data-comment-sticker-panel]');
                    if (stickerToggle) stickerToggle.setAttribute('aria-expanded', 'false');
                    if (stickerPanel) stickerPanel.setAttribute('aria-hidden', 'true');
                }
                form.classList.toggle('is-profile-open', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                var hiddenLabel = toggle.querySelector('.sr-only');
                if (hiddenLabel) hiddenLabel.textContent = open ? '收起评论身份设置' : '展开评论身份设置';
                if (open && author && !author.value.trim()) window.setTimeout(function () { author.focus(); }, prefersReducedMotion() ? 0 : 180);
            }

            function firstInvalidField() {
                for (var i = 0; i < fields.length; i += 1) {
                    if (!fields[i].checkValidity()) return fields[i];
                }
                return null;
            }

            toggle.addEventListener('click', function () {
                setOpen(!form.classList.contains('is-profile-open'));
            });
            if (save) save.addEventListener('click', function () {
                var invalid = firstInvalidField();
                if (invalid) {
                    invalid.reportValidity();
                    return;
                }
                try { localStorage.setItem('qiwi-comment-profile', JSON.stringify({ author: author ? author.value.trim() : '', mail: mail ? mail.value.trim() : '', url: url ? url.value.trim() : '' })); } catch (error) {}
                setOpen(false);
            });
            fields.forEach(function (field) {
                field.addEventListener('invalid', function () { setOpen(true); });
            });
            form.addEventListener('submit', function (event) {
                var invalid = firstInvalidField();
                if (!invalid) return;
                event.preventDefault();
                setOpen(true);
                window.setTimeout(function () { invalid.reportValidity(); invalid.focus(); }, prefersReducedMotion() ? 0 : 180);
            });
        });
    }

    function initCommentStickers(root) {
        root.querySelectorAll('.comment-form, .moment-reply-form, .publisher-form').forEach(function (form) {
            if (form.dataset.v2StickersReady === '1') return;
            var panel = form.querySelector('[data-comment-sticker-panel]');
            var toggle = form.querySelector('[data-comment-sticker-toggle]');
            var textarea = form.querySelector('textarea[name="text"]');
            var configNode = form.querySelector('[data-comment-sticker-packs]');
            if (!panel || !toggle || !textarea || !configNode) return;
            var overflowOwner = form.closest('.comment-respond, .moment-reply-composer');
            var tabs = panel.querySelector('[data-comment-sticker-tabs]');
            var grid = panel.querySelector('[data-comment-sticker-grid]');
            var status = panel.querySelector('[data-comment-sticker-status]');
            var close = panel.querySelector('[data-comment-sticker-close]');
            var packs = [];
            var loaded = false;

            try { packs = JSON.parse(configNode.textContent || '[]'); } catch (error) { packs = []; }
            if (!packs.length) return;
            form.dataset.v2StickersReady = '1';

            function setOpen(open) {
                if (open) {
                    form.classList.remove('is-profile-open');
                    var profileToggle = form.querySelector('[data-comment-profile-toggle], [data-moment-profile-toggle]');
                    if (profileToggle) profileToggle.setAttribute('aria-expanded', 'false');
                    document.documentElement.classList.remove('comment-profile-open');
                    if (document.body) document.body.classList.remove('comment-profile-open');
                    var anchorRect = textarea.getBoundingClientRect();
                    var availableAbove = Math.max(220, anchorRect.bottom - 18);
                    panel.style.setProperty('--v2-sticker-panel-height', Math.min(420, availableAbove) + 'px');
                }
                form.classList.toggle('is-sticker-open', open);
                if (overflowOwner) overflowOwner.classList.toggle('is-sticker-open', open);
                panel.setAttribute('aria-hidden', open ? 'false' : 'true');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                var label = toggle.querySelector('.sr-only');
                if (label) label.textContent = open ? '收起表情包' : '展开表情包';
                if (open && !loaded) {
                    loaded = true;
                    selectPack(packs[0]);
                }
            }

            function stickerName(item, imageUrl) {
                try {
                    var pathname = new URL(imageUrl, window.location.href).pathname;
                    return decodeURIComponent(pathname.substring(pathname.lastIndexOf('/') + 1).replace(/\.[^.]+$/, ''));
                } catch (error) {
                    return String(item.text || '').replace(/^[^-]+-/, '');
                }
            }

            function normalizePack(data, pack) {
                var items = [];
                if (Array.isArray(data)) {
                    data.forEach(function (name) {
                        name = String(name || '').trim();
                        if (!name) return;
                        var imageUrl = new URL(pack.assetBase + encodeURIComponent(name) + (pack.extension || '.png'), window.location.href).href;
                        items.push({ name: name, src: imageUrl, packId: pack.id });
                    });
                    return items;
                }
                Object.keys(data || {}).forEach(function (key) {
                    var group = data[key];
                    (group && Array.isArray(group.container) ? group.container : []).forEach(function (item) {
                        var match = String(item.icon || '').match(/\bsrc=(['"])(.*?)\1/i);
                        if (!match || !match[2]) return;
                        var imageUrl = new URL(match[2].replace(/&amp;/g, '&'), window.location.href).href;
                        var name = stickerName(item, imageUrl);
                        if (name) items.push({ name: name, src: imageUrl, packId: pack.id });
                    });
                });
                return items;
            }

            function renderPack(pack, items) {
                grid.textContent = '';
                items.forEach(function (item) {
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'comment-sticker-item';
                    button.title = item.name;
                    button.setAttribute('aria-label', item.name);
                    var image = document.createElement('img');
                    image.src = item.src;
                    image.alt = '';
                    image.loading = 'lazy';
                    image.decoding = 'async';
                    button.appendChild(image);
                    button.addEventListener('click', function () {
                        var token = '[sticker:' + item.packId + '/' + item.name + ']';
                        var start = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : textarea.value.length;
                        var end = typeof textarea.selectionEnd === 'number' ? textarea.selectionEnd : start;
                        textarea.setRangeText(token, start, end, 'end');
                        textarea.dispatchEvent(new Event('input', { bubbles: true }));
                        setOpen(false);
                        textarea.focus();
                    });
                    grid.appendChild(button);
                });
                status.textContent = items.length ? '' : '这个表情包暂时没有可用内容。';
                status.hidden = Boolean(items.length);
                tabs.querySelectorAll('[role="tab"]').forEach(function (tab) {
                    var active = tab.dataset.stickerPack === pack.id;
                    tab.classList.toggle('is-active', active);
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                });
            }

            function selectPack(pack) {
                status.hidden = false;
                status.textContent = '正在读取表情包…';
                grid.textContent = '';
                if (stickerPackCache[pack.id]) {
                    renderPack(pack, stickerPackCache[pack.id]);
                    return;
                }
                fetch(pack.source, { credentials: 'omit' })
                    .then(function (response) { if (!response.ok) throw new Error(); return response.json(); })
                    .then(function (data) {
                        stickerPackCache[pack.id] = normalizePack(data, pack);
                        renderPack(pack, stickerPackCache[pack.id]);
                    })
                    .catch(function () {
                        status.hidden = false;
                        status.textContent = '表情包读取失败，请稍后重试。';
                    });
            }

            packs.forEach(function (pack) {
                var tab = document.createElement('button');
                tab.type = 'button';
                tab.className = 'comment-sticker-tab';
                tab.dataset.stickerPack = pack.id;
                tab.setAttribute('role', 'tab');
                tab.setAttribute('aria-selected', 'false');
                tab.textContent = pack.label;
                tab.addEventListener('click', function () { selectPack(pack); });
                tabs.appendChild(tab);
            });

            toggle.addEventListener('click', function () { setOpen(!form.classList.contains('is-sticker-open')); });
            if (close) close.addEventListener('click', function () { setOpen(false); textarea.focus(); });
        });
    }

    function initMomentImageGrids(root) {
        root.querySelectorAll('.moment-text').forEach(function (scope) {
            if (scope.dataset.v2ImageGridReady === '1') return;
            scope.dataset.v2ImageGridReady = '1';
            var children = Array.prototype.slice.call(scope.children);
            var sequence = [];

            function paragraphImages(element) {
                if (!element || element.tagName !== 'P') return [];
                var images = [];
                var valid = Array.prototype.every.call(element.childNodes, function (node) {
                    if (node.nodeType === 3) return !node.textContent.trim();
                    if (node.nodeType !== 1) return false;
                    if (node.tagName === 'BR') return true;
                    if (node.tagName === 'IMG' && node.classList.contains('moment-image') && !node.classList.contains('comment-sticker')) {
                        images.push(node);
                        return true;
                    }
                    return false;
                });
                return valid ? images : [];
            }

            function renderSequence() {
                if (!sequence.length) return;
                var firstParagraph = sequence[0].paragraph;
                var images = [];
                sequence.forEach(function (entry) { images = images.concat(entry.images); });
                for (var offset = 0; offset < images.length; offset += 9) {
                    var chunk = images.slice(offset, offset + 9);
                    if (chunk.length === 1) {
                        var paragraph = document.createElement('p');
                        paragraph.className = 'moment-single-image';
                        paragraph.appendChild(chunk[0]);
                        scope.insertBefore(paragraph, firstParagraph);
                        continue;
                    }
                    var grid = document.createElement('div');
                    grid.className = 'moment-image-grid is-count-' + chunk.length;
                    chunk.forEach(function (image) { grid.appendChild(image); });
                    scope.insertBefore(grid, firstParagraph);
                }
                sequence.forEach(function (entry) { entry.paragraph.remove(); });
                sequence = [];
            }

            children.forEach(function (child) {
                var images = paragraphImages(child);
                if (images.length) {
                    sequence.push({ paragraph: child, images: images });
                } else {
                    renderSequence();
                }
            });
            renderSequence();
        });
    }

    function isStandaloneMomentMedia(node) {
        if (!node || node.nodeType !== 1) return false;
        if (node.matches('.moment-image-grid, .moment-single-image, figure, video')) return true;
        if (node.matches('img:not(.comment-sticker)')) return true;
        if (!node.matches('p')) return false;

        var media = node.querySelector('img:not(.comment-sticker), figure, video');
        if (!media) return false;
        var clone = node.cloneNode(true);
        clone.querySelectorAll('img:not(.comment-sticker), figure, video, br').forEach(function (item) { item.remove(); });
        return !clone.textContent.trim();
    }

    function prepareMomentTextFold(target) {
        if (target.dataset.v2TextFoldReady === '1') return;
        target.dataset.v2TextFoldReady = '1';

        var nodes = Array.prototype.slice.call(target.childNodes);
        var segment = null;
        nodes.forEach(function (node) {
            if (isStandaloneMomentMedia(node)) {
                segment = null;
                return;
            }
            if (node.nodeType === 3 && !node.textContent.trim() && !segment) return;
            if (!segment) {
                segment = document.createElement('div');
                segment.className = 'qiwi-text-fold-segment';
                target.insertBefore(segment, node);
            }
            segment.appendChild(node);
        });

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'qiwi-text-fold-toggle';
        button.hidden = true;
        button.setAttribute('aria-expanded', 'false');
        button.innerHTML = '<span data-fold-label>展开</span><svg viewBox="0 0 16 16" aria-hidden="true"><path d="m4 6 4 4 4-4"></path></svg>';
        button.addEventListener('click', function () {
            var expanded = target.dataset.v2TextFoldExpanded !== '1';
            target.dataset.v2TextFoldExpanded = expanded ? '1' : '0';
            animateMomentTextFold(target);
        });
        target.appendChild(button);
    }

    var MOMENT_TEXT_FOLD_MAX_LINES = 4;
    var MOMENT_TEXT_FOLD_ELLIPSIS = '…';

    function restoreMomentTextFold(target) {
        var backup = target._v2TextFoldBackup;
        if (backup) {
            backup.forEach(function (entry) { entry.node.data = entry.data; });
            target._v2TextFoldBackup = null;
        }
        var hidden = target._v2TextFoldHidden;
        if (hidden) {
            hidden.forEach(function (element) { element.classList.remove('is-text-fold-hidden'); });
            target._v2TextFoldHidden = null;
        }
    }

    function animateMomentTextFold(target) {
        if (target._v2TextFoldAnimStop) target._v2TextFoldAnimStop();
        if (prefersReducedMotion()) {
            measureMomentTextFold(target);
            return;
        }

        var startHeight = target.getBoundingClientRect().height;
        target.style.height = startHeight + 'px';
        target.style.overflow = 'hidden';

        measureMomentTextFold(target);

        var endHeight = target.scrollHeight;
        target.classList.add('is-text-fold-animating');

        var timer = null;
        var finished = false;
        function finish() {
            if (finished) return;
            finished = true;
            target._v2TextFoldAnimStop = null;
            window.clearTimeout(timer);
            target.removeEventListener('transitionend', onEnd);
            target.classList.remove('is-text-fold-animating');
            target.style.removeProperty('height');
            target.style.removeProperty('overflow');
        }
        function onEnd(event) {
            if (event.propertyName === 'height') finish();
        }
        target._v2TextFoldAnimStop = finish;
        target.addEventListener('transitionend', onEnd);
        // 超时兜底，需要略大于 CSS transition 的 .26s，避免 transitionend 未触发时样式残留
        timer = window.setTimeout(finish, 320);
        window.requestAnimationFrame(function () {
            target.style.height = endHeight + 'px';
        });
    }

    function getMomentTextNodeRects(node) {
        var range = document.createRange();
        range.selectNodeContents(node);
        var rects = Array.prototype.filter.call(range.getClientRects(), function (rect) {
            return rect.width > 0 && rect.height > 0;
        });
        return rects;
    }

    function getMomentTextLines(textNodes) {
        var rects = [];
        textNodes.forEach(function (node) {
            getMomentTextNodeRects(node).forEach(function (rect) {
                rects.push({ node: node, top: rect.top, bottom: rect.bottom });
            });
        });

        rects.sort(function (a, b) { return a.top - b.top; });
        return rects.reduce(function (lines, rect) {
            var current = lines[lines.length - 1];
            if (current && rect.top < current.bottom - 1 && rect.bottom > current.top + 1) {
                current.top = Math.min(current.top, rect.top);
                current.bottom = Math.max(current.bottom, rect.bottom);
                if (current.nodes.indexOf(rect.node) === -1) current.nodes.push(rect.node);
            } else {
                lines.push({ top: rect.top, bottom: rect.bottom, nodes: [rect.node] });
            }
            return lines;
        }, []);
    }

    function truncateMomentTextNode(node, lineBottom) {
        var chars = Array.from(node.data);
        var low = 0;
        var high = chars.length;
        var best = 0;
        while (low <= high) {
            var mid = (low + high) >> 1;
            node.data = chars.slice(0, mid).join('') + MOMENT_TEXT_FOLD_ELLIPSIS;
            var overflows = Array.prototype.some.call(getMomentTextNodeRects(node), function (rect) {
                return rect.top >= lineBottom - 1;
            });
            if (overflows) {
                high = mid - 1;
            } else {
                best = mid;
                low = mid + 1;
            }
        }
        node.data = chars.slice(0, best).join('') + MOMENT_TEXT_FOLD_ELLIPSIS;
    }

    function measureMomentTextFold(target) {
        if (target._v2TextFoldAnimStop) target._v2TextFoldAnimStop();
        var segments = Array.prototype.slice.call(target.querySelectorAll(':scope > .qiwi-text-fold-segment'));
        var button = target.querySelector(':scope > .qiwi-text-fold-toggle');
        if (!segments.length || !button) return;

        var expanded = target.dataset.v2TextFoldExpanded === '1';
        restoreMomentTextFold(target);

        var textNodes = [];
        segments.forEach(function (segment) {
            var walker = document.createTreeWalker(segment, NodeFilter.SHOW_TEXT);
            var node;
            while ((node = walker.nextNode())) {
                if (node.data.trim()) textNodes.push(node);
            }
        });

        var lines = getMomentTextLines(textNodes);
        var overflow = lines.length > MOMENT_TEXT_FOLD_MAX_LINES;
        var cutSegment = null;

        if (overflow && !expanded) {
            var limitLine = lines[MOMENT_TEXT_FOLD_MAX_LINES - 1];
            var cutNode = null;
            var cutIndex = -1;
            for (var i = textNodes.length - 1; i >= 0; i--) {
                if (limitLine.nodes.indexOf(textNodes[i]) !== -1) {
                    cutNode = textNodes[i];
                    cutIndex = i;
                    break;
                }
            }

            if (cutNode) {
                var backup = [{ node: cutNode, data: cutNode.data }];
                truncateMomentTextNode(cutNode, limitLine.bottom);
                for (i = cutIndex + 1; i < textNodes.length; i++) {
                    backup.push({ node: textNodes[i], data: textNodes[i].data });
                    textNodes[i].data = '';
                }
                target._v2TextFoldBackup = backup;

                var hidden = [];
                segments.forEach(function (segment) {
                    Array.prototype.forEach.call(segment.children, function (child) {
                        var afterCut = (cutNode.compareDocumentPosition(child) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0;
                        if (afterCut && !child.textContent.trim()) {
                            child.classList.add('is-text-fold-hidden');
                            hidden.push(child);
                        }
                    });
                    if (segment.contains(cutNode)) cutSegment = segment;
                });
                target._v2TextFoldHidden = hidden;
            }
        }

        button.hidden = !overflow;
        button.setAttribute('aria-expanded', expanded && overflow ? 'true' : 'false');
        var label = button.querySelector('[data-fold-label]');
        if (label) label.textContent = expanded && overflow ? '收起' : '展开';
        if (cutSegment && button.previousElementSibling !== cutSegment) {
            cutSegment.insertAdjacentElement('afterend', button);
        } else if (!cutSegment) {
            target.appendChild(button);
        }
    }

    function refreshMomentTextFolds() {
        momentTextFoldFrame = null;
        document.querySelectorAll('.moment-text[data-v2-text-fold-ready="1"], .moment-comment-text[data-v2-text-fold-ready="1"], .comment-text[data-v2-text-fold-ready="1"]').forEach(measureMomentTextFold);
    }

    function requestMomentTextFoldRefresh() {
        if (momentTextFoldFrame !== null) return;
        momentTextFoldFrame = window.requestAnimationFrame(refreshMomentTextFolds);
    }

    function initMomentTextFolds(root) {
        root.querySelectorAll('.moment-text, .moment-comment-text, .comment-text').forEach(function (target) {
            prepareMomentTextFold(target);
            measureMomentTextFold(target);
        });
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(requestMomentTextFoldRefresh).catch(function () {});
        }
    }

    function initToc(root) {
        var toc = root.querySelector('.article-toc');
        var body = root.querySelector('.article-body');
        if (!body && toc) body = toc.closest('.about-page, .friends-page, .timemachine-page');
        // 已构建过的目录直接保留（含其滚动进度监听），避免 refresh() 先拆监听再提前返回
        if (toc && toc.children.length) return;
        if (tocObserver) {
            tocObserver.disconnect();
            tocObserver = null;
        }
        if (tocProgressCleanup) {
            tocProgressCleanup();
            tocProgressCleanup = null;
        }
        if (!toc || !body) return;
        // 折叠块与隐藏 Tab 面板里的标题不进目录，否则点击后 scrollIntoView 落在不可见元素上
        var headings = Array.prototype.slice.call(body.querySelectorAll('h2, h3, h4')).filter(function (heading) {
            return !heading.closest('[hidden], details:not([open]), .about-tab-panel:not(.is-active)');
        });
        if (headings.length < 2) { toc.hidden = true; return; }

        function headingText(heading) {
            var clone = heading.cloneNode(true);
            clone.querySelectorAll('.header-anchor, .anchor, .heading-anchor, a[href^="#"]').forEach(function (anchor) {
                if (!anchor.textContent.trim() || /^[#¶]$/.test(anchor.textContent.trim())) anchor.remove();
            });
            return clone.textContent.replace(/\s+/g, ' ').trim();
        }

        function uniqueHeadingId(heading, text, index) {
            if (heading.id && document.getElementById(heading.id) === heading) return heading.id;
            var base = text.toLowerCase()
                .replace(/[\s\/\\?%*:|"<>.,;()[\]{}+=!@#$^&~`]+/g, '-')
                .replace(/^-+|-+$/g, '') || 'section-' + (index + 1);
            var id = base;
            var suffix = 2;
            while (document.getElementById(id)) {
                id = base + '-' + suffix;
                suffix += 1;
            }
            heading.id = id;
            return id;
        }

        function childList(item) {
            var list = item.querySelector(':scope > .toc-children');
            if (list) return list;
            list = document.createElement('ul');
            list.className = 'toc-children';
            item.classList.add('has-children');
            item.appendChild(list);
            return list;
        }

        function revealHeading(heading, link) {
            var container = currentContainer();
            if (!container) return;
            container.classList.add('is-anchor-scrolling');
            window.setTimeout(function () {
                heading.scrollIntoView({ behavior: 'auto', block: 'start' });
                try {
                    history.replaceState(history.state, '', window.location.pathname + window.location.search + '#' + encodeURIComponent(heading.id));
                } catch (error) {}
                toc.querySelectorAll('.toc-link.is-active').forEach(function (item) { item.classList.remove('is-active'); });
                link.classList.add('is-active');
                window.requestAnimationFrame(function () {
                    window.requestAnimationFrame(function () { container.classList.remove('is-anchor-scrolling'); });
                });
            }, prefersReducedMotion() ? 0 : 150);
        }

        var title = document.createElement('button');
        title.type = 'button';
        title.className = 'toc-title';
        title.textContent = '目 录';
        title.setAttribute('aria-expanded', 'false');
        toc.appendChild(title);

        var list = document.createElement('ul');
        list.className = 'toc-list';
        var currentH2 = null;
        var currentH3 = null;
        var tocEntries = [];

        headings.forEach(function (heading, index) {
            var text = headingText(heading);
            if (!text) return;
            var level = parseInt(heading.tagName.slice(1), 10);
            var item = document.createElement('li');
            item.className = 'toc-item level-' + level;
            var link = document.createElement('a');
            link.className = 'toc-link level-' + heading.tagName.toLowerCase();
            link.href = '#' + uniqueHeadingId(heading, text, index);
            link.dataset.label = text;
            var linkText = document.createElement('span');
            linkText.className = 'toc-link-text';
            linkText.textContent = text;
            link.appendChild(linkText);
            link.addEventListener('click', function (event) {
                event.preventDefault();
                revealHeading(heading, link);
            });
            item.appendChild(link);
            tocEntries.push({ heading: heading, item: item, link: link });

            if (level === 2) {
                list.appendChild(item);
                currentH2 = item;
                currentH3 = null;
            } else if (level === 3 && currentH2) {
                childList(currentH2).appendChild(item);
                currentH3 = item;
            } else if (level === 4 && (currentH3 || currentH2)) {
                childList(currentH3 || currentH2).appendChild(item);
            } else {
                list.appendChild(item);
                currentH3 = level === 3 ? item : null;
            }
        });
        var endItem = document.createElement('li');
        endItem.className = 'toc-item toc-end';
        var endNode = document.createElement('span');
        endNode.className = 'toc-end-node';
        endNode.setAttribute('aria-label', '文章结尾');
        var endLabel = document.createElement('span');
        endLabel.className = 'toc-link-text';
        endLabel.textContent = '结 尾';
        endNode.appendChild(endLabel);
        endItem.appendChild(endNode);
        list.appendChild(endItem);
        var progress = document.createElement('span');
        progress.className = 'toc-progress';
        progress.setAttribute('aria-hidden', 'true');
        toc.appendChild(progress);
        var mobileProgress = document.createElement('span');
        mobileProgress.className = 'toc-mobile-progress';
        mobileProgress.setAttribute('aria-hidden', 'true');
        toc.appendChild(mobileProgress);

        title.addEventListener('click', function () {
            var open = toc.classList.toggle('is-open');
            title.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        toc.appendChild(list);
        var railEntries = tocEntries.filter(function (entry) { return entry.item.parentElement === list; });

        function railItemFor(item) {
            var railItem = item;
            while (railItem && railItem.parentElement !== list) {
                railItem = railItem.parentElement.closest('.toc-item');
            }
            return railItem;
        }

        tocEntries.forEach(function (entry) {
            var railItem = railItemFor(entry.item);
            entry.railIndex = railEntries.findIndex(function (railEntry) { return railEntry.item === railItem; });
        });

        var frame = null;
        var metricsFrame = null;
        var bodyBottom = 0;
        var documentHeight = 0;
        var headingTops = [];
        var railHeadingTops = [];
        var railNodeCenters = [];
        var endNodeCenter = 0;
        var tocHeight = 0;
        var lastActiveIndex = null;
        var lastComplete = null;

        function refreshMetrics() {
            metricsFrame = null;
            var scrollY = window.scrollY;
            var rect = body.getBoundingClientRect();
            bodyBottom = scrollY + rect.bottom;
            documentHeight = Math.max(document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0);
            headingTops = tocEntries.map(function (entry) {
                return scrollY + entry.heading.getBoundingClientRect().top;
            });
            railHeadingTops = railEntries.map(function (entry) {
                return scrollY + entry.heading.getBoundingClientRect().top;
            });
            tocHeight = toc.clientHeight;
            if (window.innerWidth >= 1100) {
                var tocTop = toc.getBoundingClientRect().top;
                var nodeCenter = function (item) {
                    var node = item.firstElementChild;
                    if (!node) return 5;
                    var nodeRect = node.getBoundingClientRect();
                    return nodeRect.top - tocTop + nodeRect.height / 2;
                };
                railNodeCenters = railEntries.map(function (entry) { return nodeCenter(entry.item); });
                endNodeCenter = nodeCenter(endItem);
            }
            lastActiveIndex = null;
            requestProgress();
        }

        function requestMetrics() {
            if (metricsFrame !== null) return;
            metricsFrame = window.requestAnimationFrame(refreshMetrics);
        }

        function updateProgress() {
            frame = null;
            var readingLine = window.scrollY + window.innerHeight * .24;
            var maxReadingLine = Math.max(0, documentHeight - window.innerHeight) + window.innerHeight * .24;
            var progressEnd = Math.min(bodyBottom, maxReadingLine);
            var activeIndex = -1;
            headingTops.forEach(function (headingTop, index) {
                if (headingTop <= readingLine + 1) activeIndex = index;
            });
            var complete = readingLine >= progressEnd - 1;
            var value = 0;
            var sectionProgress = 0;
            var activeRailIndex = -1;
            if (complete) {
                value = 1;
            } else if (activeIndex >= 0) {
                activeRailIndex = tocEntries[activeIndex].railIndex;
                if (activeRailIndex >= 0) {
                    var sectionStart = railHeadingTops[activeRailIndex];
                    var sectionEnd = activeRailIndex + 1 < railEntries.length
                        ? railHeadingTops[activeRailIndex + 1]
                        : progressEnd;
                    sectionProgress = Math.max(0, Math.min(1, (readingLine - sectionStart) / Math.max(1, sectionEnd - sectionStart)));
                    value = Math.min(1, (activeRailIndex + sectionProgress) / Math.max(1, railEntries.length));
                }
            }
            if (activeIndex !== lastActiveIndex || complete !== lastComplete) {
                toc.classList.toggle('is-complete', complete);
                toc.querySelectorAll('.toc-item.is-section-active').forEach(function (item) { item.classList.remove('is-section-active'); });
                tocEntries.forEach(function (entry, index) {
                    var active = !complete && index === activeIndex;
                    var passed = complete || index < activeIndex;
                    entry.link.classList.toggle('is-active', active);
                    entry.link.classList.toggle('is-passed', passed);
                    entry.item.classList.toggle('is-passed', passed);
                    if (active) {
                        var section = railItemFor(entry.item) || entry.item;
                        section.classList.add('is-section-active');
                    }
                });
                lastActiveIndex = activeIndex;
                lastComplete = complete;
            }
            var progressSize = Math.max(0, tocHeight - 10) * value;
            if (window.innerWidth >= 1100) {
                if (complete) {
                    progressSize = Math.max(0, endNodeCenter - 5);
                } else if (activeRailIndex >= 0) {
                    var startCenter = railNodeCenters[activeRailIndex];
                    var endCenter = activeRailIndex + 1 < railNodeCenters.length ? railNodeCenters[activeRailIndex + 1] : endNodeCenter;
                    progressSize = Math.max(0, startCenter + (endCenter - startCenter) * sectionProgress - 5);
                } else {
                    progressSize = 0;
                }
            }
            toc.style.setProperty('--toc-progress-scale', value.toFixed(4));
            toc.style.setProperty('--toc-progress-size', progressSize.toFixed(2) + 'px');
        }
        function requestProgress() {
            if (frame !== null) return;
            frame = window.requestAnimationFrame(updateProgress);
        }
        window.addEventListener('scroll', requestProgress, { passive: true });
        window.addEventListener('resize', requestMetrics);
        window.addEventListener('load', requestMetrics, { once: true });
        if ('ResizeObserver' in window) {
            tocObserver = new ResizeObserver(requestMetrics);
            tocObserver.observe(body);
        }
        tocProgressCleanup = function () {
            window.removeEventListener('scroll', requestProgress);
            window.removeEventListener('resize', requestMetrics);
            window.removeEventListener('load', requestMetrics);
            if (frame !== null) window.cancelAnimationFrame(frame);
            if (metricsFrame !== null) window.cancelAnimationFrame(metricsFrame);
        };
        refreshMetrics();
    }

    function copyCodeText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(text);
        return new Promise(function (resolve, reject) {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.top = '-1000px';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy') ? resolve() : reject(new Error('copy failed'));
            } catch (error) {
                reject(error);
            } finally {
                textarea.remove();
            }
        });
    }

    function initCodeBlocks(root) {
        var aliases = { txt: 'plaintext', text: 'plaintext', shell: 'bash', sh: 'bash', js: 'javascript', ts: 'typescript', html: 'xml' };
        root.querySelectorAll('pre code').forEach(function (code) {
            if (code.closest('.code-block-wrapper')) return;
            var classNames = Array.prototype.slice.call(code.classList);
            var languageClass = classNames.find(function (name) { return /^(?:language|lang)-/.test(name); });
            var rawLanguage = languageClass ? languageClass.replace(/^(?:language|lang)-/, '') : '';
            if (!rawLanguage && code.classList.contains('plaintext')) rawLanguage = 'plaintext';
            var highlightLanguage = aliases[rawLanguage] || rawLanguage;
            if (languageClass) code.classList.remove(languageClass);
            if (highlightLanguage && !code.classList.contains('language-' + highlightLanguage)) code.classList.add('language-' + highlightLanguage);
            if (window.hljs && typeof window.hljs.highlightElement === 'function') {
                try { window.hljs.highlightElement(code); } catch (error) {}
            }
            var pre = code.parentElement;
            if (!pre || !pre.parentNode) return;
            var wrapper = document.createElement('div');
            wrapper.className = 'code-block-wrapper';
            var header = document.createElement('div');
            header.className = 'code-block-header';
            var language = document.createElement('span');
            language.className = 'code-language';
            language.textContent = (rawLanguage || 'text').toUpperCase();
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'copy-button';
            button.setAttribute('aria-label', '复制代码');
            button.setAttribute('title', '复制代码');
            button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg><span class="sr-only copy-text">复制代码</span>';
            button.addEventListener('click', function () {
                copyCodeText(code.textContent).then(function () {
                    button.classList.add('is-copied');
                    button.setAttribute('aria-label', '已复制');
                    button.setAttribute('title', '已复制');
                    button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"></path></svg><span class="sr-only copy-text">已复制</span>';
                    window.setTimeout(function () {
                        button.classList.remove('is-copied');
                        button.setAttribute('aria-label', '复制代码');
                        button.setAttribute('title', '复制代码');
                        button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg><span class="sr-only copy-text">复制代码</span>';
                    }, 1500);
                }).catch(function () {});
            });
            header.appendChild(language);
            header.appendChild(button);
            pre.parentNode.insertBefore(wrapper, pre);
            wrapper.appendChild(header);
            wrapper.appendChild(pre);
        });
    }

    function initAttachmentDownloads(root) {
        var form = root.querySelector('[data-attachment-download-form]');
        if (!form || form.dataset.v2AttachmentReady === '1') return;
        form.dataset.v2AttachmentReady = '1';

        var captchaRequired = form.dataset.attachmentCaptchaRequired === '1';
        var status = form.querySelector('[data-attachment-download-status]');
        var attachmentInput = form ? form.querySelector('input[name="attachment_id"]') : null;
        var contentInput = form ? form.querySelector('input[name="content_id"]') : null;
        var downloadNameInput = form ? form.querySelector('input[name="download_name"]') : null;
        if (!status || !attachmentInput || !contentInput || !downloadNameInput) return;

        var currentTrigger = null;
        var phase = '';
        var fallbackTimer = null;

        function setDownloadStatus(message, isError) {
            status.textContent = message || '';
            status.classList.toggle('is-error', Boolean(isError));
        }

        function setTriggerState(trigger, state, label) {
            if (!trigger) return;
            var card = trigger.closest('[data-attachment-card]');
            var labelElement = trigger.querySelector('[data-attachment-button-label]');
            ['is-verifying', 'is-downloading', 'is-complete', 'is-error'].forEach(function (className) {
                if (card) card.classList.remove(className);
            });
            if (state && card) card.classList.add('is-' + state);
            trigger.disabled = state === 'verifying' || state === 'downloading' || state === 'complete';
            if (state === 'verifying' || state === 'downloading') trigger.setAttribute('aria-busy', 'true');
            else trigger.removeAttribute('aria-busy');
            if (state !== 'downloading' && state !== 'complete') {
                trigger.style.removeProperty('--qiwi-attachment-progress');
                if (card) card.classList.remove('is-download-indeterminate');
            }
            if (labelElement) labelElement.textContent = label || '下载';
        }

        function setDownloadProgress(trigger, percent, indeterminate) {
            if (!trigger) return;
            var card = trigger.closest('[data-attachment-card]');
            var labelElement = trigger.querySelector('[data-attachment-button-label]');
            if (card) card.classList.toggle('is-download-indeterminate', Boolean(indeterminate));
            if (indeterminate) {
                trigger.style.removeProperty('--qiwi-attachment-progress');
                if (labelElement) labelElement.textContent = '下载中';
                return;
            }

            percent = Math.max(0, Math.min(100, Math.round(Number(percent) || 0)));
            trigger.style.setProperty('--qiwi-attachment-progress', percent + '%');
            if (labelElement) labelElement.textContent = percent + '%';
        }

        function clearFallbackTimer() {
            if (fallbackTimer === null) return;
            window.clearTimeout(fallbackTimer);
            fallbackTimer = null;
        }

        function resetCaptcha() {
            if (!captchaRequired) return;
            form.dispatchEvent(new CustomEvent('qiwi:captcha-reset', { bubbles: false }));
        }

        function moveController(trigger) {
            var attachmentRoot = trigger ? trigger.closest('[data-attachment-root]') : null;
            if (!attachmentRoot || form.parentNode === attachmentRoot) return;
            form.dispatchEvent(new CustomEvent('qiwi:captcha-relocation-start', { bubbles: false }));
            attachmentRoot.appendChild(form);
            form.dispatchEvent(new CustomEvent('qiwi:captcha-relocation-end', { bubbles: false }));
        }

        function showManualCaptcha(message, isError) {
            if (!currentTrigger) return;
            clearFallbackTimer();
            phase = 'manual';
            form.classList.add('is-manual');
            setDownloadStatus(message || '请在这里完成人机验证。', isError);
            setTriggerState(currentTrigger, isError ? 'error' : '', isError ? '重新验证' : '等待验证');
        }

        function showDownloadError(message) {
            if (!currentTrigger) return;
            clearFallbackTimer();
            phase = 'error';
            form.classList.add('is-manual');
            setDownloadStatus(message || '附件下载失败，请重试。', true);
            setTriggerState(currentTrigger, 'error', captchaRequired ? '重新验证' : '重试');
        }

        function blobText(blob, callback) {
            if (!blob) {
                callback('');
                return;
            }
            if (typeof blob.text === 'function') {
                blob.text().then(callback).catch(function () { callback(''); });
                return;
            }

            var reader = new FileReader();
            reader.onload = function () { callback(typeof reader.result === 'string' ? reader.result : ''); };
            reader.onerror = function () { callback(''); };
            reader.readAsText(blob);
        }

        function responseErrorMessage(xhr, callback) {
            blobText(xhr.response, function (text) {
                var message = '';
                try {
                    var payload = JSON.parse(text || '{}');
                    message = payload && payload.message ? String(payload.message) : '';
                } catch (error) {}
                callback(message || '附件下载请求失败。');
            });
        }

        function completeDownload(blob) {
            var completedTrigger = currentTrigger;
            if (!completedTrigger) return;
            setDownloadProgress(completedTrigger, 100, false);

            var url = window.URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url;
            link.download = completedTrigger.dataset.attachmentDownloadName || completedTrigger.dataset.attachmentName || 'attachment';
            link.hidden = true;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(function () { window.URL.revokeObjectURL(url); }, 30000);

            setDownloadStatus('附件已经接收完成，浏览器正在保存。', false);
            resetCaptcha();
            phase = 'complete';
            setTriggerState(completedTrigger, 'complete', '完成');
            window.setTimeout(function () {
                setTriggerState(completedTrigger, '', '下载');
                if (currentTrigger === completedTrigger) {
                    currentTrigger = null;
                    phase = '';
                }
            }, 1600);
        }

        function failDownload(message) {
            resetCaptcha();
            showDownloadError(message);
        }

        function startDownload() {
            if (!currentTrigger || phase === 'downloading') return;

            clearFallbackTimer();
            phase = 'downloading';
            form.classList.add('is-downloading');
            form.classList.remove('is-manual');
            setTriggerState(currentTrigger, 'downloading', '0%');
            setDownloadProgress(currentTrigger, 0, false);
            setDownloadStatus(captchaRequired ? '验证通过，正在下载附件…' : '正在下载附件…', false);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', form.action, true);
            xhr.responseType = 'blob';
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onprogress = function (event) {
                if (!currentTrigger || phase !== 'downloading') return;
                if (!event.lengthComputable || event.total <= 0) {
                    setDownloadProgress(currentTrigger, 0, true);
                    setDownloadStatus('正在下载附件…', false);
                    return;
                }

                var percent = event.loaded / event.total * 100;
                setDownloadProgress(currentTrigger, percent, false);
                setDownloadStatus('正在下载附件… ' + Math.round(percent) + '%', false);
            };
            xhr.onload = function () {
                form.classList.remove('is-downloading');
                var attachmentHeader = String(xhr.getResponseHeader('X-Qiwi-Attachment') || '') === '1';
                if (xhr.status >= 200 && xhr.status < 300 && attachmentHeader) {
                    completeDownload(xhr.response);
                    return;
                }

                responseErrorMessage(xhr, failDownload);
            };
            xhr.onerror = function () {
                form.classList.remove('is-downloading');
                failDownload('附件下载连接失败，请重试。');
            };
            xhr.onabort = function () {
                form.classList.remove('is-downloading');
                failDownload('附件下载已经取消。');
            };
            xhr.send(new FormData(form));
        }

        function beginDownload(trigger) {
            if (!trigger || trigger.disabled || phase === 'verifying' || phase === 'downloading') return;
            if (currentTrigger && currentTrigger !== trigger) {
                setTriggerState(currentTrigger, '', '下载');
                resetCaptcha();
            }

            currentTrigger = trigger;
            moveController(trigger);
            attachmentInput.value = trigger.dataset.attachmentId || '';
            contentInput.value = trigger.dataset.contentId || '';
            downloadNameInput.value = trigger.dataset.attachmentDownloadName || trigger.dataset.attachmentName || 'attachment';
            form.classList.remove('is-manual');

            if (!captchaRequired) {
                startDownload();
                return;
            }

            phase = 'verifying';
            setDownloadStatus('正在进行人机验证…', false);
            setTriggerState(trigger, 'verifying', '验证中');
            clearFallbackTimer();
            fallbackTimer = window.setTimeout(function () {
                if (phase === 'verifying') showManualCaptcha('验证需要继续完成，请使用下方验证组件。', false);
            }, 12000);
            form.dispatchEvent(new CustomEvent('qiwi:captcha-execute', { bubbles: false }));
        }

        root.querySelectorAll('[data-attachment-download]').forEach(function (trigger) {
            if (trigger.dataset.v2AttachmentTrigger === '1') return;
            trigger.dataset.v2AttachmentTrigger = '1';
            trigger.addEventListener('click', function () { beginDownload(trigger); });
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
        });

        form.addEventListener('qiwi:captcha-manual-required', function (event) {
            var message = event.detail && event.detail.message ? event.detail.message : '';
            showManualCaptcha(message, false);
        });

        form.addEventListener('qiwi:captcha-error', function (event) {
            var message = event.detail && event.detail.message ? event.detail.message : '人机验证暂时没有完成，请重试。';
            showManualCaptcha(message, true);
        });

        form.addEventListener('qiwi:captcha-verified', function () {
            if (!currentTrigger || phase === 'downloading') return;
            startDownload();
        });
    }

    var backToTopFrame = null;
    function updateBackToTop() {
        backToTopFrame = null;
        var button = document.querySelector('[data-back-to-top]');
        if (!button) return;
        button.hidden = window.scrollY <= 160;
    }

    function requestBackToTopUpdate() {
        if (backToTopFrame !== null) return;
        backToTopFrame = window.requestAnimationFrame(updateBackToTop);
    }

    // 管理员点击涂黑条取回原文；普通访客的涂黑条不带这些数据属性，不会有任何交互。
    function initRedactReveal(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var bars = scope.querySelectorAll('.qiwi-redact[data-qiwi-reveal]');
        if (!bars.length) return;

        bars.forEach(function (bar) {
            if (bar.dataset.v2Ready === '1') return;
            bar.dataset.v2Ready = '1';
            bar.setAttribute('role', 'button');
            bar.tabIndex = 0;
            var reveal = function () {
                if (bar.classList.contains('is-revealed')) return;
                var base = bar.getAttribute('data-qiwi-reveal-url') || '/action/qiwi-theme';
                var url = base + (base.indexOf('?') >= 0 ? '&' : '?') + 'do=redact-reveal';
                var body = 'cid=' + encodeURIComponent(bar.getAttribute('data-qiwi-reveal-cid') || '')
                    + '&index=' + encodeURIComponent(bar.getAttribute('data-qiwi-reveal') || '')
                    + '&sign=' + encodeURIComponent(bar.getAttribute('data-qiwi-reveal-sign') || '');
                fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body,
                    credentials: 'same-origin'
                }).then(function (response) {
                    return response.json();
                }).then(function (data) {
                    if (!data || !data.success || typeof data.text !== 'string') {
                        bar.setAttribute('title', (data && data.message) || '取回失败');
                        return;
                    }
                    bar.textContent = data.text;
                    bar.classList.add('is-revealed');
                    bar.removeAttribute('role');
                    bar.removeAttribute('tabindex');
                    bar.removeAttribute('title');
                }).catch(function () {
                    bar.setAttribute('title', '取回失败，请稍后再试');
                });
            };
            bar.addEventListener('click', reveal);
            bar.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    reveal();
                }
            });
        });
    }

    // ============================================================
    // 段落想法：读者框选正文写"想法"，审核通过后所有人可见。
    // 字符轴：正文全部可批注文本节点（排除 pre/script/style 与代码块工具条）
    // 按文档序拼接，块级元素之间补一个换行；想法只存轴上的 [start, end)。
    // 想法文字包在 <mark class="thought-mark"> 里显示虚线，重画前先拆干净。
    // ============================================================
    var thoughtState = null;
    var thoughtBubble = null;
    var thoughtPopover = null;
    var thoughtBubbleTimer = null;
    var thoughtDraftTimer = null;
    var thoughtCloseTimer = null;

    var THOUGHT_EXCLUDED_TAGS = { PRE: true, SCRIPT: true, STYLE: true, NOSCRIPT: true };
    var THOUGHT_BLOCK_TAGS = {
        P: 1, DIV: 1, H1: 1, H2: 1, H3: 1, H4: 1, H5: 1, H6: 1,
        UL: 1, OL: 1, LI: 1, DL: 1, DT: 1, DD: 1,
        TABLE: 1, THEAD: 1, TBODY: 1, TFOOT: 1, TR: 1, TD: 1, TH: 1,
        BLOCKQUOTE: 1, FIGURE: 1, FIGCAPTION: 1, SECTION: 1, ARTICLE: 1,
        ASIDE: 1, MAIN: 1, HEADER: 1, FOOTER: 1, NAV: 1, HR: 1,
        DETAILS: 1, SUMMARY: 1, ADDRESS: 1, FORM: 1, FIELDSET: 1
    };
    var THOUGHT_ANCHOR = 32;

    function thoughtIsOff() {
        try { return localStorage.getItem('qiwi-thoughts-disabled') === '1'; } catch (error) { return false; }
    }

    function thoughtExcludedElement(el) {
        if (!el || el.nodeType !== 1) return false;
        if (THOUGHT_EXCLUDED_TAGS[el.tagName]) return true;
        return el.classList.contains('code-block-header')
            || el.classList.contains('code-language')
            || el.classList.contains('copy-button');
    }

    function thoughtBuildAxis(body) {
        var axis = '';
        var entries = [];

        function walk(node) {
            if (node.nodeType === 3) {
                var value = node.nodeValue;
                if (value === '') return false;
                entries.push({ node: node, start: axis.length, end: axis.length + value.length });
                axis += value;
                return true;
            }
            if (node.nodeType !== 1 || thoughtExcludedElement(node)) return false;
            var contributed = false;
            for (var child = node.firstChild; child; child = child.nextSibling) {
                if (walk(child)) contributed = true;
            }
            if (contributed && THOUGHT_BLOCK_TAGS[node.tagName] && node !== body) {
                axis += '\n';
            }
            return contributed;
        }

        walk(body);
        return { axis: axis, entries: entries };
    }

    function thoughtClearMarks(body) {
        Array.prototype.slice.call(body.querySelectorAll('.thought-mark')).forEach(function (mark) {
            var parent = mark.parentNode;
            if (!parent) return;
            while (mark.firstChild) parent.insertBefore(mark.firstChild, mark);
            parent.removeChild(mark);
            parent.normalize();
        });
    }

    // 先清干净再画：拆掉旧 <mark>、合并文本节点、按最新轴重包
    function thoughtDrawMarks() {
        var state = thoughtState;
        if (!state) return;
        thoughtClearMarks(state.body);
        var data = thoughtBuildAxis(state.body);
        state.axis = data.axis;
        state.entries = data.entries;

        if (thoughtIsOff()) return;

        Object.keys(state.ranges).forEach(function (key) {
            var range = state.ranges[key];
            var hasApproved = range.items.some(function (item) { return item.status === 'approved'; });
            Array.prototype.slice.call(state.entries).forEach(function (entry) {
                var start = Math.max(range.start, entry.start);
                var end = Math.min(range.end, entry.end);
                if (end <= start || !entry.node.parentNode) return;
                var domRange = document.createRange();
                try {
                    domRange.setStart(entry.node, start - entry.start);
                    domRange.setEnd(entry.node, end - entry.start);
                    var mark = document.createElement('mark');
                    mark.className = 'thought-mark' + (hasApproved ? '' : ' is-pending');
                    mark.setAttribute('data-thought-range', key);
                    mark.setAttribute('role', 'button');
                    mark.tabIndex = 0;
                    mark.setAttribute('aria-label', '查看这段文字的想法');
                    domRange.surroundContents(mark);
                } catch (error) {}
            });
        });

        // 包裹 <mark> 会把文本节点切碎，这里按最新 DOM 重新采集节点偏移表，
        // 否则下一次划选（mouseup）用旧引用换算轴偏移会失败。
        // mark 是行内元素、不参与块分隔，轴字符串在重画前后保持不变。
        var refreshed = thoughtBuildAxis(state.body);
        state.axis = refreshed.axis;
        state.entries = refreshed.entries;
    }

    // 新想法落地时在正文标记上轻闪一次，提示它挂在了哪里
    function thoughtFlashMark(key) {
        var state = thoughtState;
        if (!state || prefersReducedMotion()) return;
        state.body.querySelectorAll('.thought-mark[data-thought-range="' + key + '"]').forEach(function (mark) {
            mark.classList.remove('is-flash');
            void mark.offsetWidth;
            mark.classList.add('is-flash');
            mark.addEventListener('animationend', function () { mark.classList.remove('is-flash'); }, { once: true });
        });
    }

    function thoughtRangeKey(start, end) {
        return start + ':' + end;
    }

    function thoughtUpsertRange(state, item) {
        var key = thoughtRangeKey(item.start, item.end);
        if (!state.ranges[key]) {
            state.ranges[key] = { start: item.start, end: item.end, items: [] };
        }
        state.ranges[key].items.push(item);
        return state.ranges[key];
    }

    // 原文被改动时的抢救：偏移对不上就用原文 / 前后锚点在新轴上重新定位
    function thoughtReanchor(state, item) {
        var axis = state.axis;
        if (axis.slice(item.start, item.end) === item.quote) return true;

        var located = -1;
        var before = item.anchorBefore || '';
        var after = item.anchorAfter || '';
        if (before !== '') {
            var withBefore = axis.indexOf(before + item.quote);
            if (withBefore >= 0) located = withBefore + before.length;
        }
        if (located < 0 && after !== '') {
            var withAfter = axis.indexOf(item.quote + after);
            if (withAfter >= 0) located = withAfter;
        }
        if (located < 0) {
            located = axis.indexOf(item.quote);
        }
        if (located < 0) return false;

        item.start = located;
        item.end = located + item.quote.length;
        return true;
    }

    function thoughtSelectionOffsets(state, domRange) {
        var startAxis = null;
        var endAxis = null;
        state.entries.forEach(function (entry) {
            var node = entry.node;
            if (!node.parentNode) return;
            if (!domRange.intersectsNode(node)) return;
            var length = node.nodeValue.length;
            var start = entry.start;
            var end = entry.end;
            if (domRange.startContainer === node) start = entry.start + Math.max(0, Math.min(domRange.startOffset, length));
            if (domRange.endContainer === node) end = entry.start + Math.max(0, Math.min(domRange.endOffset, length));
            if (startAxis === null || start < startAxis) startAxis = start;
            if (endAxis === null || end > endAxis) endAxis = end;
        });
        if (startAxis === null || endAxis === null || endAxis <= startAxis) return null;

        // 去掉选区边缘的空白（含轴上的块分隔换行）
        var axis = state.axis;
        while (startAxis < endAxis && /[\s\u00a0]/.test(axis.charAt(startAxis))) startAxis += 1;
        while (endAxis > startAxis && /[\s\u00a0]/.test(axis.charAt(endAxis - 1))) endAxis -= 1;
        if (endAxis <= startAxis) return null;
        return { start: startAxis, end: endAxis };
    }

    function thoughtSelectionIsCoarse() {
        return Boolean(window.matchMedia && (window.matchMedia('(pointer: coarse)').matches || window.matchMedia('(max-width: 860px)').matches));
    }

    function thoughtGetBubble() {
        if (thoughtBubble && thoughtBubble.parentNode) return thoughtBubble;
        thoughtBubble = document.createElement('button');
        thoughtBubble.type = 'button';
        thoughtBubble.className = 'thought-bubble';
        thoughtBubble.textContent = '写想法';
        document.body.appendChild(thoughtBubble);
        thoughtBubble.addEventListener('click', function () {
            var pending = thoughtBubble._qiwiSelection;
            thoughtBubble._qiwiSelection = null;
            hideThoughtBubble();
            if (pending) thoughtOpenPopover(pending.start, pending.end, true);
        });
        return thoughtBubble;
    }

    function hideThoughtBubble() {
        if (thoughtBubbleTimer !== null) { window.clearTimeout(thoughtBubbleTimer); thoughtBubbleTimer = null; }
        if (thoughtBubble) {
            thoughtBubble.classList.remove('is-visible', 'is-error');
            thoughtBubble.disabled = false;
            thoughtBubble._qiwiSelection = null;
        }
    }

    function showThoughtBubble(rect, selection, message) {
        var bubble = thoughtGetBubble();
        if (thoughtBubbleTimer !== null) { window.clearTimeout(thoughtBubbleTimer); thoughtBubbleTimer = null; }
        if (message) {
            bubble.classList.add('is-error');
            bubble.textContent = message;
            bubble.disabled = true;
            thoughtBubbleTimer = window.setTimeout(hideThoughtBubble, 1800);
        } else {
            bubble.classList.remove('is-error');
            bubble.textContent = '写想法';
            bubble.disabled = false;
            bubble._qiwiSelection = selection;
        }
        var top = rect.bottom + window.scrollY + 8;
        var left = rect.left + window.scrollX + rect.width / 2;
        bubble.style.top = top + 'px';
        bubble.style.left = left + 'px';
        bubble.classList.add('is-visible');
    }

    function thoughtSelectionSettle() {
        var state = thoughtState;
        if (!state) return;
        hideThoughtBubble();
        if (thoughtIsOff()) return;

        var selection = window.getSelection();
        if (!selection || selection.rangeCount === 0 || selection.isCollapsed) return;
        var domRange = selection.getRangeAt(0);
        if (!state.body.contains(domRange.commonAncestorContainer)) return;

        var offsets = thoughtSelectionOffsets(state, domRange);
        if (!offsets) return;

        var config = state.config;
        var length = offsets.end - offsets.start;
        if (length < config.minSelection) return;
        if (length > config.maxSelection) {
            showThoughtBubble(domRange.getBoundingClientRect(), null, '最多选择 ' + config.maxSelection + ' 个字');
            return;
        }
        if (thoughtSelectionIsCoarse() && (state.axis.slice(offsets.start, offsets.end).split('\n').length > 3)) {
            showThoughtBubble(domRange.getBoundingClientRect(), null, '一次最多选择 3 个段落');
            return;
        }

        // 与已有选区的关系：完全落在同一选区 → 直接打开那个浮窗；部分重叠 → 提示
        var overlapKey = null;
        var containedKey = null;
        Object.keys(state.ranges).forEach(function (key) {
            var range = state.ranges[key];
            if (offsets.start < range.end && offsets.end > range.start) {
                if (offsets.start >= range.start && offsets.end <= range.end) containedKey = key;
                overlapKey = key;
            }
        });
        var rects = domRange.getClientRects();
        var rect = rects.length ? rects[rects.length - 1] : domRange.getBoundingClientRect();
        if (containedKey !== null) {
            thoughtOpenPopover(state.ranges[containedKey].start, state.ranges[containedKey].end, true);
            return;
        }
        if (overlapKey !== null) {
            showThoughtBubble(rect, null, '这段文字已有想法');
            return;
        }

        showThoughtBubble(rect, offsets, null);
    }

    function thoughtGetPopover() {
        if (thoughtPopover && thoughtPopover.parentNode) return thoughtPopover;
        thoughtPopover = document.createElement('div');
        thoughtPopover.className = 'thought-popover';
        thoughtPopover.setAttribute('data-thought-popover', '');
        thoughtPopover.setAttribute('role', 'dialog');
        thoughtPopover.setAttribute('aria-label', '段落想法');
        thoughtPopover.hidden = true;
        thoughtPopover.innerHTML =
            '<div class="thought-popover-header" data-thought-drag-handle>'
            + '<span class="thought-popover-title">想法</span>'
            + '<button type="button" class="thought-help-toggle" data-thought-help aria-expanded="false">什么是想法？</button>'
            + '<button type="button" class="thought-popover-close" data-thought-close aria-label="关闭想法浮窗">×</button>'
            + '</div>'
            + '<div class="thought-help" data-thought-help-block hidden>'
            + '<p>像在书上划线写批注：选中正文里的一段文字，点「写想法」，你的想法会挂在这段文字下面。</p>'
            + '<p>想法是轻评论，不支持回复与点赞；提交后需通过站长审核才会对其他读者可见。不喜欢这个功能的话，可以在文章顶部的阅读设置里关闭「想法」。</p>'
            + '</div>'
            + '<blockquote class="thought-quote" data-thought-quote></blockquote>'
            + '<div class="thought-list" data-thought-list></div>'
            + '<div class="thought-compose-toggle" data-thought-compose-toggle hidden>'
            + '<button type="button" class="thought-compose-button">写想法</button>'
            + '</div>'
            + '<div class="thought-form-slot" data-thought-form-slot></div>';
        document.body.appendChild(thoughtPopover);

        thoughtPopover.querySelector('[data-thought-close]').addEventListener('click', closeThoughtPopover);
        var helpToggle = thoughtPopover.querySelector('[data-thought-help]');
        helpToggle.addEventListener('click', function () {
            var block = thoughtPopover.querySelector('[data-thought-help-block]');
            var open = !block.hidden;
            block.hidden = open;
            helpToggle.setAttribute('aria-expanded', open ? 'false' : 'true');
        });
        thoughtPopover.querySelector('[data-thought-compose-toggle] button').addEventListener('click', function () {
            thoughtPopover.classList.add('is-composing');
        });
        bindThoughtDrag(thoughtPopover);
        return thoughtPopover;
    }

    function bindThoughtDrag(popover) {
        var handle = popover.querySelector('[data-thought-drag-handle]');
        var dragging = null;
        handle.addEventListener('pointerdown', function (event) {
            if (event.button !== 0 || event.target.closest('button')) return;
            var rect = popover.getBoundingClientRect();
            dragging = {
                x: event.clientX - rect.left,
                y: event.clientY - rect.top,
                id: event.pointerId
            };
            try { handle.setPointerCapture(event.pointerId); } catch (error) {}
            popover.classList.add('is-dragging');
            event.preventDefault();
        });
        handle.addEventListener('pointermove', function (event) {
            if (!dragging || event.pointerId !== dragging.id) return;
            popover.style.left = Math.max(8, Math.min(window.innerWidth - 80, event.clientX - dragging.x + window.scrollX)) + 'px';
            popover.style.top = Math.max(window.scrollY + 8, event.clientY - dragging.y + window.scrollY) + 'px';
            popover.style.bottom = 'auto';
        });
        function release(event) {
            if (!dragging || (event && event.pointerId !== dragging.id)) return;
            dragging = null;
            popover.classList.remove('is-dragging');
        }
        handle.addEventListener('pointerup', release);
        handle.addEventListener('pointercancel', release);
    }

    function closeThoughtPopover() {
        if (!thoughtPopover || thoughtPopover.hidden) return;
        saveThoughtDraft();
        var popover = thoughtPopover;
        popover.classList.remove('is-open');
        if (thoughtCloseTimer !== null) window.clearTimeout(thoughtCloseTimer);
        // 先淡出再隐藏；计时器兜底，不依赖 transitionend
        thoughtCloseTimer = window.setTimeout(function () {
            thoughtCloseTimer = null;
            popover.hidden = true;
            popover.classList.remove('is-composing');
        }, prefersReducedMotion() ? 0 : 140);
    }

    function thoughtDraftKey(state, start, end) {
        return state.config.cid + ':' + start + ':' + end;
    }

    function readThoughtDrafts() {
        try { return JSON.parse(localStorage.getItem('qiwi-thought-drafts') || '{}'); } catch (error) { return {}; }
    }

    function saveThoughtDraft() {
        var state = thoughtState;
        if (!state || !state.popoverRange) return;
        var textarea = state.form.querySelector('[data-thought-text]');
        if (!textarea) return;
        var value = textarea.value;
        var drafts = readThoughtDrafts();
        var key = thoughtDraftKey(state, state.popoverRange.start, state.popoverRange.end);
        if (value.trim() === '') delete drafts[key];
        else drafts[key] = value;
        var keys = Object.keys(drafts);
        if (keys.length > 30) {
            keys.slice(0, keys.length - 30).forEach(function (oldKey) { delete drafts[oldKey]; });
        }
        try { localStorage.setItem('qiwi-thought-drafts', JSON.stringify(drafts)); } catch (error) {}
    }

    function clearThoughtDraft(start, end) {
        var state = thoughtState;
        if (!state) return;
        var drafts = readThoughtDrafts();
        delete drafts[thoughtDraftKey(state, start, end)];
        try { localStorage.setItem('qiwi-thought-drafts', JSON.stringify(drafts)); } catch (error) {}
    }

    function renderThoughtList(state, range, freshId) {
        var popover = thoughtGetPopover();
        var list = popover.querySelector('[data-thought-list]');
        list.textContent = '';
        var visible = range.items.filter(function (item) { return item.status === 'approved' || item.status === 'waiting'; });
        if (!visible.length) {
            list.hidden = true;
            return;
        }
        list.hidden = false;
        var count = document.createElement('p');
        count.className = 'thought-list-count';
        count.textContent = visible.length + ' 条想法';
        list.appendChild(count);
        visible.forEach(function (item) {
            var node = document.createElement('div');
            node.className = 'thought-item' + (item.status === 'waiting' ? ' is-waiting' : '') + (freshId && item.id === freshId ? ' is-new' : '');
            var head = document.createElement('div');
            head.className = 'thought-item-head';
            var fallback = document.createElement('span');
            fallback.className = 'thought-item-avatar is-fallback';
            fallback.setAttribute('aria-hidden', 'true');
            fallback.textContent = (item.author || '匿').charAt(0).toUpperCase();
            if (item.avatar) {
                var img = document.createElement('img');
                img.className = 'thought-item-avatar';
                img.alt = '';
                img.width = 28;
                img.height = 28;
                img.referrerPolicy = 'no-referrer';
                // 头像源加载失败时退回首字母圆点，避免破图
                img.addEventListener('error', function () {
                    if (img.parentNode) img.parentNode.replaceChild(fallback, img);
                }, { once: true });
                img.addEventListener('load', function () { img.classList.add('is-loaded'); }, { once: true });
                img.src = item.avatar;
                if (img.complete && img.naturalWidth) img.classList.add('is-loaded');
                head.appendChild(img);
            } else {
                head.appendChild(fallback);
            }
            var meta = document.createElement('span');
            meta.className = 'thought-item-meta';
            var author = document.createElement('strong');
            author.textContent = item.author || '匿名';
            meta.appendChild(author);
            if (item.authorLabel) {
                var badge = document.createElement('em');
                badge.className = 'thought-item-badge';
                badge.textContent = item.authorLabel;
                meta.appendChild(badge);
            }
            var time = document.createElement('time');
            time.textContent = item.date || '';
            meta.appendChild(time);
            head.appendChild(meta);
            node.appendChild(head);
            if (item.status === 'waiting') {
                var status = document.createElement('p');
                status.className = 'thought-item-status';
                status.textContent = '待审核，通过后对所有人可见';
                node.appendChild(status);
            }
            var content = document.createElement('div');
            content.className = 'thought-item-content';
            content.innerHTML = item.html || '';
            node.appendChild(content);
            list.appendChild(node);
        });
    }

    function thoughtPositionPopover(popover, anchorRect) {
        var width = Math.min(380, window.innerWidth - 24);
        popover.style.width = width + 'px';
        popover.style.left = '0px';
        popover.style.top = '0px';
        popover.style.bottom = 'auto';
        if (thoughtCloseTimer !== null) {
            window.clearTimeout(thoughtCloseTimer);
            thoughtCloseTimer = null;
            popover.classList.remove('is-open');
        }
        popover.hidden = false;

        var rect = popover.getBoundingClientRect();
        var pageX = anchorRect.left + window.scrollX + anchorRect.width / 2 - width / 2;
        pageX = Math.max(window.scrollX + 12, Math.min(window.scrollX + window.innerWidth - width - 12, pageX));
        var below = anchorRect.bottom + window.scrollY + 10;
        var above = anchorRect.top + window.scrollY - rect.height - 10;
        var pageY = below;
        if (below + rect.height > window.scrollY + window.innerHeight - 8 && above > window.scrollY + 8) {
            pageY = above;
        }
        popover.style.left = Math.round(pageX) + 'px';
        popover.style.top = Math.round(pageY) + 'px';
        // 进入方向跟随锚点：在下方时自上落下，在上方时自下浮起
        popover.setAttribute('data-placement', pageY === below ? 'below' : 'above');
        if (!popover.classList.contains('is-open')) {
            void popover.offsetWidth;
            popover.classList.add('is-open');
        }
    }

    function thoughtOpenPopover(start, end, compose) {
        var state = thoughtState;
        if (!state) return;
        var popover = thoughtGetPopover();
        var key = thoughtRangeKey(start, end);
        var range = state.ranges[key] || { start: start, end: end, items: [] };
        state.popoverRange = { start: start, end: end };

        var quote = popover.querySelector('[data-thought-quote]');
        quote.textContent = state.axis.slice(start, end);

        renderThoughtList(state, range);

        var form = state.form;
        var slot = popover.querySelector('[data-thought-form-slot]');
        if (form.parentNode !== slot) slot.appendChild(form);
        var isAdmin = Boolean(state.config.isAdmin);
        form.classList.toggle('is-admin', isAdmin);
        popover.classList.toggle('is-composing', Boolean(compose));
        // 框选打开 = 直接表单；点虚线打开 = 列表 + 「写想法」按钮展开表单
        popover.querySelector('[data-thought-compose-toggle]').hidden = Boolean(compose);

        var helpToggle = popover.querySelector('[data-thought-help]');
        var helpBlock = popover.querySelector('[data-thought-help-block]');
        helpBlock.hidden = true;
        helpToggle.setAttribute('aria-expanded', 'false');

        var status = form.querySelector('[data-thought-status]');
        if (status) status.textContent = '';

        var textarea = form.querySelector('[data-thought-text]');
        if (textarea) {
            var drafts = readThoughtDrafts();
            textarea.value = drafts[thoughtDraftKey(state, start, end)] || '';
            updateThoughtCounter();
        }
        prefillThoughtIdentity();

        var anchorRect;
        var mark = state.body.querySelector('.thought-mark[data-thought-range="' + key + '"]');
        if (mark) {
            anchorRect = mark.getBoundingClientRect();
        } else {
            var selection = window.getSelection();
            var rects = selection && selection.rangeCount ? selection.getRangeAt(0).getClientRects() : [];
            anchorRect = rects.length ? rects[rects.length - 1] : { left: window.innerWidth / 2, top: 120, right: window.innerWidth / 2, bottom: 120, width: 0 };
        }
        thoughtPositionPopover(popover, anchorRect);
        hideThoughtBubble();
        if (compose && textarea && !thoughtSelectionIsCoarse()) textarea.focus({ preventScroll: true });
    }

    function prefillThoughtIdentity() {
        var state = thoughtState;
        if (!state || state.config.isAdmin) return;
        var author = state.form.querySelector('[data-thought-author]');
        var mail = state.form.querySelector('[data-thought-mail]');
        if (!author && !mail) return;
        try {
            var stored = JSON.parse(localStorage.getItem('qiwi-comment-profile') || '{}');
            if (author && !author.value && stored.author) author.value = stored.author;
            if (mail && !mail.value && stored.mail) mail.value = stored.mail;
        } catch (error) {}
        // 与评论区一致：身份完整且有效时折叠为一行摘要，否则直接展开字段
        var complete = Boolean(author && mail && author.value.trim() && mail.value.trim() && author.checkValidity() && mail.checkValidity());
        setThoughtIdentityOpen(!complete);
    }

    function setThoughtIdentityOpen(open) {
        var state = thoughtState;
        if (!state) return;
        var summary = state.form.querySelector('[data-thought-identity-summary]');
        var fields = state.form.querySelector('[data-thought-identity-fields]');
        var edit = state.form.querySelector('[data-thought-identity-edit]');
        var name = state.form.querySelector('[data-thought-identity-name]');
        var author = state.form.querySelector('[data-thought-author]');
        if (!summary || !fields) return;
        if (name && author) name.textContent = author.value.trim();
        var canCollapse = Boolean(author && author.value.trim());
        summary.hidden = !canCollapse;
        var expanded = !canCollapse || open;
        fields.classList.toggle('is-open', expanded);
        // 折叠时字段仍在 DOM 里做高度动画，需移出 Tab 序列与读屏；
        // 焦点还在字段内时先归还给「修改」按钮（元素变 inert 后浏览器会把焦点丢回 body）
        if (!expanded && edit && document.activeElement && fields.contains(document.activeElement)) {
            edit.focus();
        }
        if (expanded) fields.removeAttribute('inert'); else fields.setAttribute('inert', '');
        fields.setAttribute('aria-hidden', expanded ? 'false' : 'true');
        if (edit) {
            edit.setAttribute('aria-expanded', open ? 'true' : 'false');
            edit.textContent = open ? '收起' : '修改';
        }
    }

    function rememberThoughtIdentity() {
        var state = thoughtState;
        if (!state || state.config.isAdmin) return;
        var author = state.form.querySelector('[data-thought-author]');
        var mail = state.form.querySelector('[data-thought-mail]');
        try {
            var stored = JSON.parse(localStorage.getItem('qiwi-comment-profile') || '{}');
            if (author) stored.author = author.value.trim();
            if (mail) stored.mail = mail.value.trim();
            localStorage.setItem('qiwi-comment-profile', JSON.stringify(stored));
        } catch (error) {}
    }

    // 状态提示统一出口：文字 + 错误态 + 入场动画（切 class 重放，textContent 赋值不会触发 CSS animation）
    function showThoughtStatus(form, message, isError) {
        var status = form.querySelector('[data-thought-status]');
        if (!status) return;
        status.textContent = message || '';
        status.classList.toggle('is-error', Boolean(isError));
        if (message) {
            status.classList.remove('is-reveal');
            void status.offsetWidth;
            status.classList.add('is-reveal');
        }
    }

    function updateThoughtCounter() {
        var state = thoughtState;
        if (!state) return;
        var counter = state.form.querySelector('[data-thought-counter]');
        var textarea = state.form.querySelector('[data-thought-text]');
        if (!counter || !textarea) return;
        var length = textarea.value.length;
        counter.textContent = length > 0 ? length + ' / ' + state.config.maxLength : '';
        counter.classList.toggle('is-over', length > state.config.maxLength);
    }

    function thoughtCaptchaToken(form) {
        var widget = form.querySelector('cap-widget');
        if (!widget) return '';
        var hidden = widget.querySelector('input[name="cap-token"]');
        return String(widget.token || widget.tokenValue || (hidden && hidden.value) || '').trim();
    }

    function submitThought() {
        var state = thoughtState;
        if (!state || !state.popoverRange) return;
        var form = state.form;
        var config = state.config;
        var button = form.querySelector('[data-thought-submit]');
        var textarea = form.querySelector('[data-thought-text]');
        if (!button || !textarea) return;

        var text = textarea.value.trim();
        if (text === '') { showThoughtStatus(form, '想法内容不能为空。', true); textarea.focus(); return; }
        if (text.length > config.maxLength) { showThoughtStatus(form, '想法最多 ' + config.maxLength + ' 个字。', true); return; }

        var author = '';
        var mail = '';
        if (!config.isAdmin) {
            var authorInput = form.querySelector('[data-thought-author]');
            var mailInput = form.querySelector('[data-thought-mail]');
            author = authorInput ? authorInput.value.trim() : '';
            mail = mailInput ? mailInput.value.trim() : '';
            var identityError = '';
            var identityField = null;
            if (author === '') { identityError = '称呼不能为空。'; identityField = authorInput; }
            else if (mail === '') { identityError = '邮箱不能为空。'; identityField = mailInput; }
            else if (mailInput && !mailInput.checkValidity()) { identityError = '邮箱格式不正确。'; identityField = mailInput; }
            if (identityError) {
                setThoughtIdentityOpen(true);
                showThoughtStatus(form, identityError, true);
                (identityField || textarea).focus();
                return;
            }
        }

        if (config.captcha && thoughtCaptchaToken(form) === '') {
            showThoughtStatus(form, '请先完成人机验证。', true);
            return;
        }

        var start = state.popoverRange.start;
        var end = state.popoverRange.end;
        var payload = new FormData();
        payload.append('cid', String(config.cid));
        payload.append('start', String(start));
        payload.append('end', String(end));
        payload.append('quote', state.axis.slice(start, end));
        payload.append('anchorBefore', state.axis.slice(Math.max(0, start - THOUGHT_ANCHOR), start));
        payload.append('anchorAfter', state.axis.slice(end, end + THOUGHT_ANCHOR));
        payload.append('author', author);
        payload.append('mail', mail);
        payload.append('text', text);
        payload.append('cap-token', thoughtCaptchaToken(form));

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        showThoughtStatus(form, '正在提交…', false);

        fetch(config.endpoint, { method: 'POST', credentials: 'same-origin', body: payload })
            .then(function (response) {
                if (!response.ok && response.status !== 400) throw new Error('http ' + response.status);
                return response.json().catch(function () { throw new Error('bad json'); });
            })
            .then(function (data) {
                if (!data || !data.success) {
                    showThoughtStatus(form, (data && data.message) || '提交失败，请稍后再试。', true);
                    return;
                }
                var thought = data.thought || {};
                thought.anchorBefore = '';
                thought.anchorAfter = '';
                var key = thoughtRangeKey(thought.start, thought.end);
                if (!state.ranges[key]) state.ranges[key] = { start: thought.start, end: thought.end, items: [] };
                state.ranges[key].items.push(thought);
                state.popoverRange = { start: thought.start, end: thought.end };
                thoughtDrawMarks();
                clearThoughtDraft(thought.start, thought.end);
                rememberThoughtIdentity();
                setThoughtIdentityOpen(false);
                textarea.value = '';
                updateThoughtCounter();
                showThoughtStatus(form, data.message || '想法已提交。', false);
                renderThoughtList(state, state.ranges[key], thought.id);
                thoughtFlashMark(key);
                thoughtPopover.classList.remove('is-composing');
                thoughtPopover.querySelector('[data-thought-compose-toggle]').hidden = false;
                var widget = form.querySelector('cap-widget');
                if (widget && typeof widget.reset === 'function') {
                    try { widget.reset(); } catch (error) {}
                }
            })
            .catch(function () {
                showThoughtStatus(form, '提交失败，请稍后再试。', true);
            })
            .finally(function () {
                button.disabled = false;
                button.removeAttribute('aria-busy');
            });
    }

    function bindThoughtForm(form) {
        if (form.dataset.v2ThoughtReady === '1') return;
        form.dataset.v2ThoughtReady = '1';
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            submitThought();
        });
        // 身份字段改由 submitThought 统一校验并给出状态提示，避免浏览器原生气泡指向折叠中的字段
        form.noValidate = true;
        var identityEdit = form.querySelector('[data-thought-identity-edit]');
        if (identityEdit) {
            identityEdit.addEventListener('click', function () {
                var fields = form.querySelector('[data-thought-identity-fields]');
                var open = Boolean(fields && !fields.classList.contains('is-open'));
                setThoughtIdentityOpen(open);
                var author = form.querySelector('[data-thought-author]');
                if (open && author) author.focus();
            });
        }
        var textarea = form.querySelector('[data-thought-text]');
        if (textarea) {
            textarea.addEventListener('input', function () {
                updateThoughtCounter();
                if (thoughtDraftTimer !== null) window.clearTimeout(thoughtDraftTimer);
                thoughtDraftTimer = window.setTimeout(saveThoughtDraft, 400);
            });
        }
    }

    function syncThoughtToggleUI(root) {
        var scope = root || document;
        var off = thoughtIsOff();
        scope.querySelectorAll('[data-thoughts-toggle]').forEach(function (button) {
            var active = (button.dataset.thoughtsToggle === 'off') === off;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function registerThoughtGlobalListeners() {
        if (document.documentElement.dataset.qiwiV2Thoughts === '1') return;
        document.documentElement.dataset.qiwiV2Thoughts = '1';

        document.addEventListener('mouseup', function (event) {
            if (!thoughtState) return;
            if (event.target.closest && event.target.closest('.thought-popover, .thought-bubble')) return;
            window.setTimeout(thoughtSelectionSettle, 10);
        });
        document.addEventListener('touchend', function () {
            if (!thoughtState) return;
            window.setTimeout(thoughtSelectionSettle, 300);
        }, { passive: true });
        document.addEventListener('selectionchange', function () {
            if (!thoughtState) return;
            var selection = window.getSelection();
            if (!selection || selection.isCollapsed || !selection.rangeCount) hideThoughtBubble();
        });
    }

    function teardownThoughts() {
        if (thoughtBubbleTimer !== null) { window.clearTimeout(thoughtBubbleTimer); thoughtBubbleTimer = null; }
        if (thoughtDraftTimer !== null) { window.clearTimeout(thoughtDraftTimer); thoughtDraftTimer = null; }
        if (thoughtCloseTimer !== null) { window.clearTimeout(thoughtCloseTimer); thoughtCloseTimer = null; }
        if (thoughtBubble) { thoughtBubble.remove(); thoughtBubble = null; }
        if (thoughtPopover) { thoughtPopover.remove(); thoughtPopover = null; }
        thoughtState = null;
    }

    function initPostIdeas(root) {
        teardownThoughts();
        var template = root.querySelector('[data-thought-template]');
        if (!template) return;
        var dataNode = template.querySelector('[data-thoughts-data]');
        var form = template.querySelector('[data-thought-form]');
        if (!dataNode || !form) return;

        var config;
        try { config = JSON.parse(dataNode.textContent || '{}'); } catch (error) { return; }
        if (!config || !config.cid || !config.endpoint) return;
        var body = root.querySelector('.article-body');
        if (!body) return;

        config.minSelection = Math.max(1, parseInt(config.minSelection, 10) || 2);
        config.maxSelection = Math.max(config.minSelection, parseInt(config.maxSelection, 10) || 200);
        config.maxLength = Math.max(1, parseInt(config.maxLength, 10) || 200);

        var state = {
            config: config,
            body: body,
            form: form,
            ranges: {},
            axis: '',
            entries: [],
            popoverRange: null
        };

        var axisData = thoughtBuildAxis(body);
        state.axis = axisData.axis;
        state.entries = axisData.entries;

        (config.items || []).forEach(function (item) {
            if (!item || typeof item.start !== 'number' || typeof item.end !== 'number' || !item.quote) return;
            if (thoughtReanchor(state, item)) thoughtUpsertRange(state, item);
        });

        thoughtState = state;
        registerThoughtGlobalListeners();
        bindThoughtForm(form);
        syncThoughtToggleUI(root);
        root.querySelectorAll('[data-thoughts-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var off = button.dataset.thoughtsToggle === 'off';
                try {
                    if (off) localStorage.setItem('qiwi-thoughts-disabled', '1');
                    else localStorage.removeItem('qiwi-thoughts-disabled');
                } catch (error) {}
                syncThoughtToggleUI(document);
                thoughtDrawMarks();
                closeThoughtPopover();
            });
        });
        thoughtDrawMarks();
    }

    function initPjaxPage(root, afterPjax) {
        initReadingPreferences(root);
        initCommentProfiles(root);
        initCommentStickers(root);
        initMomentImageGrids(root);
        initLatestMoment(root);
        initToc(root);
        initCodeBlocks(root);
        initMomentTextFolds(root);
        initAttachmentDownloads(root);
        initRedactReveal(root);
        initPostIdeas(root);
        requestBackToTopUpdate();
        if (afterPjax) root.dataset.v2PjaxPage = '1';
        if (typeof window.initQiwiFolds === 'function') window.initQiwiFolds();
        if (typeof window.initQiwiExternalLinks === 'function') window.initQiwiExternalLinks();
        if (typeof window.initQiwiCopyLinks === 'function') window.initQiwiCopyLinks();
        if (typeof window.initQiwiLocalTimes === 'function') window.initQiwiLocalTimes();
    }

    function navigate(url, options) {
        options = options || {};
        var target = normalizeUrl(url);
        var container = currentContainer();
        if (!target || !container) {
            window.location.assign(url);
            return;
        }

        if (activeRequest) activeRequest.abort();
        activeRequest = new AbortController();
        var requestId = ++navigationId;
        var timeout = window.setTimeout(function () { activeRequest.abort(); }, 12000);
        var requestedScrollY = options.popstate && history.state && Number.isFinite(history.state.qiwiScrollY) ? history.state.qiwiScrollY : 0;

        if (!options.popstate) {
            try {
                history.replaceState(Object.assign({}, history.state, { qiwiScrollY: window.scrollY }), '', window.location.href);
            } catch (error) {}
        }

        container.classList.remove('is-entering');
        container.classList.add('is-leaving');
        var hidden = new Promise(function (resolve) { window.setTimeout(resolve, prefersReducedMotion() ? 0 : 190); });
        cleanupDynamicPageListeners();
        var visibleLightbox = document.querySelector('.v2-lightbox.is-visible');
        if (visibleLightbox) clearLightbox(visibleLightbox);
        if (latestMomentTimer !== null) {
            window.clearInterval(latestMomentTimer);
            latestMomentTimer = null;
        }
        if (tocObserver) {
            tocObserver.disconnect();
            tocObserver = null;
        }
        if (tocProgressCleanup) {
            tocProgressCleanup();
            tocProgressCleanup = null;
        }
        if (window.qiwiPlogController) { window.qiwiPlogController.abort(); window.qiwiPlogController = null; }
        if (window.qiwiTimemachineController) { window.qiwiTimemachineController.abort(); window.qiwiTimemachineController = null; }
        teardownThoughts();
        // 模板内联脚本加在 <html>/<body> 上的滚动锁类会随 DOM 替换失去清理入口，导航时统一移除。
        ['plog-lightbox-open', 'comment-profile-open', 'qiwi-lightbox-open'].forEach(function (lockClass) {
            document.documentElement.classList.remove(lockClass);
            if (document.body) document.body.classList.remove(lockClass);
        });
        setStatus('正在加载页面');
        setMobileMenu(false);

        fetch(target.href, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'Qiwi-PJAX', 'Accept': 'text/html' },
            signal: activeRequest.signal
        }).then(function (response) {
            if (!response.ok || !/text\/html/i.test(response.headers.get('content-type') || '')) throw new Error('Invalid PJAX response');
            return response.text();
        }).then(function (html) {
            return hidden.then(function () {
                if (requestId !== navigationId) return;
                var nextDocument = new DOMParser().parseFromString(html, 'text/html');
                var nextContainer = nextDocument.querySelector(containerSelector);
                if (!nextContainer) throw new Error('Missing PJAX container');

                var headReady = updateHead(nextDocument);
                container.innerHTML = nextContainer.innerHTML;
                appendTypechoCommentTokenScript(nextDocument, container);
                if (!options.popstate) history.pushState({ qiwiPjax: true, qiwiScrollY: 0 }, '', target.href);
                lastKnownUrl = window.location.href;
                updateNavigation(target.href);

                return headReady.then(function () {
                    return executeScripts(container, requestId);
                }).then(function () {
                    // 过期导航不收尾，避免滚动、状态与 page-loaded 事件作用到较新导航的内容
                    if (requestId !== navigationId) return;
                    initPjaxPage(container, true);
                    initLatex(container, nextDocument);
                    var scrollY = requestedScrollY;
                    if (target.hash) {
                        var anchorId = target.hash.slice(1);
                        try { anchorId = decodeURIComponent(anchorId); } catch (error) {}
                        var anchor = document.getElementById(anchorId);
                        if (anchor) anchor.scrollIntoView({ behavior: 'auto', block: 'start' });
                        else window.scrollTo(0, scrollY);
                    } else {
                        window.scrollTo(0, scrollY);
                    }
                    container.focus({ preventScroll: true });
                    container.classList.remove('is-leaving');
                    container.classList.add('is-entering');
                    window.setTimeout(function () { container.classList.remove('is-entering'); }, 320);
                    setStatus('页面已加载：' + document.title);
                    document.dispatchEvent(new CustomEvent('qiwi:page-loaded', { detail: { url: target.href, container: container } }));
                });
            });
        }).catch(function (error) {
            // 过期导航的任何失败都直接放弃；非过期失败（含 12 秒超时中断）回退整页加载
            if (requestId !== navigationId) return;
            window.location.assign(target.href);
        }).finally(function () {
            window.clearTimeout(timeout);
            if (requestId === navigationId) activeRequest = null;
        });
    }

    function getLightboxTargetRect(preview) {
        var padding = window.innerWidth <= 700 ? 18 : 40;
        var viewportWidth = Math.max(1, window.innerWidth - padding * 2);
        var portraitWidth = Math.min(720, viewportWidth);
        var availableHeight = Math.max(1, window.innerHeight - padding * 2);
        var naturalWidth = preview.naturalWidth || viewportWidth;
        var naturalHeight = preview.naturalHeight || availableHeight;
        var isPortrait = naturalHeight > naturalWidth;
        var scale = isPortrait
            ? Math.min(1, portraitWidth / naturalWidth)
            : Math.min(1, viewportWidth / naturalWidth, availableHeight / naturalHeight);
        var width = Math.max(1, naturalWidth * scale);
        var height = Math.max(1, naturalHeight * scale);
        return {
            left: (window.innerWidth - width) / 2,
            top: height < availableHeight ? (window.innerHeight - height) / 2 : padding,
            width: width,
            height: height,
            isPortrait: isPortrait
        };
    }

    function clearLightbox(lightbox) {
        var preview = lightbox.querySelector('img');
        var source = lightbox._qiwiSource;
        var previousFocus = lightbox._qiwiPreviousFocus;
        if (lightbox._qiwiCloseTimer) window.clearTimeout(lightbox._qiwiCloseTimer);
        if (lightbox._qiwiZoomTimer) window.clearTimeout(lightbox._qiwiZoomTimer);
        lightbox._qiwiCloseTimer = null;
        lightbox._qiwiZoomTimer = null;
        if (source && source.classList) source.classList.remove('v2-lightbox-source');
        lightbox._qiwiSource = null;
        lightbox.classList.remove('is-visible', 'is-open', 'is-closing');
        preview.classList.remove('is-prepared', 'is-open', 'is-portrait', 'is-zoomed');
        preview.removeAttribute('src');
        preview.removeAttribute('style');
        document.documentElement.classList.remove('v2-lightbox-open');
        lightbox._qiwiPreviousFocus = null;
        if (previousFocus && previousFocus !== document.body && previousFocus.isConnected && typeof previousFocus.focus === 'function') {
            try { previousFocus.focus({ preventScroll: true }); } catch (error) { previousFocus.focus(); }
        }
    }

    function closeLightbox(lightbox) {
        if (!lightbox || !lightbox.classList.contains('is-visible') || lightbox.classList.contains('is-closing')) return;
        var preview = lightbox.querySelector('img');
        var source = lightbox._qiwiSource;
        var sourceRect = source && source.isConnected ? source.getBoundingClientRect() : null;
        lightbox._qiwiRequest = (lightbox._qiwiRequest || 0) + 1;
        lightbox.classList.add('is-closing');
        lightbox.classList.remove('is-open');
        if (source && source.classList) source.classList.remove('v2-lightbox-source');
        if (lightbox._qiwiZoomTimer) window.clearTimeout(lightbox._qiwiZoomTimer);
        lightbox._qiwiZoomTimer = null;

        if (preview.classList.contains('is-zoomed') || preview.style.transformOrigin !== 'top left') {
            preview.style.transition = 'none';
            preview.classList.remove('is-zoomed');
            preview.style.transformOrigin = 'top left';
            preview.style.transform = 'translate(0, 0) scale(1)';
            preview.offsetWidth;
            preview.style.transition = '';
        }

        if (!prefersReducedMotion() && sourceRect && sourceRect.width > 0 && sourceRect.height > 0) {
            var previewRect = preview.getBoundingClientRect();
            preview.style.transform = 'translate(' + (sourceRect.left - previewRect.left) + 'px,' + (sourceRect.top - previewRect.top) + 'px) scale(' + (sourceRect.width / previewRect.width) + ',' + (sourceRect.height / previewRect.height) + ')';
        } else {
            preview.style.opacity = '0';
        }
        preview.classList.remove('is-open');

        lightbox._qiwiCloseTimer = window.setTimeout(function () { clearLightbox(lightbox); }, prefersReducedMotion() ? 0 : 240);
    }

    function openLightbox(source) {
        var lightbox = document.querySelector('.v2-lightbox');
        if (!lightbox) {
            lightbox = document.createElement('div');
            lightbox.className = 'v2-lightbox';
            lightbox.setAttribute('role', 'dialog');
            lightbox.setAttribute('aria-modal', 'true');
            lightbox.setAttribute('aria-label', '图片预览');
            lightbox.tabIndex = -1;
            lightbox.innerHTML = '<button type="button" class="v2-lightbox-close" aria-label="关闭图片预览">×</button><img alt="">';
            document.body.appendChild(lightbox);
            lightbox.addEventListener('click', function (event) {
                var image = lightbox.querySelector('img');
                if (event.target === image) {
                    if (!lightbox.classList.contains('is-open') || lightbox.classList.contains('is-closing')) return;
                    if (image.classList.contains('is-zoomed')) {
                        image.classList.remove('is-zoomed');
                        image.style.transform = 'translate(0, 0) scale(1)';
                        lightbox._qiwiZoomTimer = window.setTimeout(function () {
                            if (!image.classList.contains('is-zoomed')) image.style.transformOrigin = 'top left';
                            lightbox._qiwiZoomTimer = null;
                        }, 240);
                        return;
                    }
                    if (lightbox._qiwiZoomTimer) window.clearTimeout(lightbox._qiwiZoomTimer);
                    lightbox._qiwiZoomTimer = null;
                    var rect = image.getBoundingClientRect();
                    var originX = Math.max(0, Math.min(100, (event.clientX - rect.left) / rect.width * 100));
                    var originY = Math.max(0, Math.min(100, (event.clientY - rect.top) / rect.height * 100));
                    image.style.transformOrigin = originX + '% ' + originY + '%';
                    image.classList.add('is-zoomed');
                    image.style.transform = 'scale(2)';
                    return;
                }
                closeLightbox(lightbox);
            });
            lightbox.addEventListener('keydown', function (event) {
                if (event.key !== 'Tab') return;
                var focusable = Array.prototype.slice.call(lightbox.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'));
                if (!focusable.length) {
                    event.preventDefault();
                    lightbox.focus();
                    return;
                }
                var first = focusable[0];
                var last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            });
        }
        if (lightbox.classList.contains('is-visible')) return;

        var preview = lightbox.querySelector('img');
        var requestId = (lightbox._qiwiRequest || 0) + 1;
        lightbox._qiwiRequest = requestId;
        lightbox._qiwiSource = source;
        lightbox._qiwiPreviousFocus = document.activeElement;
        preview.alt = source.alt || '';
        preview.src = source.currentSrc || source.src;
        lightbox.scrollTop = 0;
        lightbox.scrollLeft = 0;
        lightbox.classList.add('is-visible');
        document.documentElement.classList.add('v2-lightbox-open');
        var closeButton = lightbox.querySelector('.v2-lightbox-close');
        try { (closeButton || lightbox).focus({ preventScroll: true }); } catch (error) { (closeButton || lightbox).focus(); }

        var decoded = typeof preview.decode === 'function' ? preview.decode() : Promise.resolve();
        decoded.catch(function () {}).then(function () {
            if (lightbox._qiwiRequest !== requestId || !preview.src) return;
            var sourceRect = source.getBoundingClientRect();
            var targetRect = getLightboxTargetRect(preview);
            preview.style.left = targetRect.left + 'px';
            preview.style.top = targetRect.top + 'px';
            preview.style.width = targetRect.width + 'px';
            preview.style.height = targetRect.height + 'px';
            preview.classList.toggle('is-portrait', targetRect.isPortrait);
            preview.classList.remove('is-zoomed');
            preview.style.transformOrigin = 'top left';
            if (!prefersReducedMotion() && sourceRect.width > 0 && sourceRect.height > 0) {
                preview.style.transform = 'translate(' + (sourceRect.left - targetRect.left) + 'px,' + (sourceRect.top - targetRect.top) + 'px) scale(' + (sourceRect.width / targetRect.width) + ',' + (sourceRect.height / targetRect.height) + ')';
            } else {
                preview.style.transform = 'none';
            }
            preview.classList.add('is-prepared');
            source.classList.add('v2-lightbox-source');
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    if (lightbox._qiwiRequest !== requestId) return;
                    lightbox.classList.add('is-open');
                    preview.classList.add('is-open');
                    preview.style.transform = 'translate(0, 0) scale(1)';
                });
            });
        });
    }

    document.addEventListener('click', function (event) {
        var backToTop = event.target.closest('[data-back-to-top]');
        if (backToTop) {
            event.preventDefault();
            window.scrollTo({ top: 0, behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
            return;
        }
        var likeButton = event.target.closest('[data-post-like]');
        if (likeButton) {
            event.preventDefault();
            if (likeButton.classList.contains('is-liked')) { celebrateLike(likeButton); return; }
            if (likeButton.dataset.likeBusy === '1') return;
            var endpoint = likeButton.dataset.likeEndpoint || '';
            var cid = likeButton.dataset.postId || '';
            if (!endpoint || !cid) return;
            likeButton.dataset.likeBusy = '1';
            likeButton.setAttribute('aria-busy', 'true');
            var payload = new URLSearchParams();
            payload.set('cid', cid);
            fetch(endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body: payload.toString() })
                .then(function (response) { if (!response.ok) throw new Error(); return response.json(); })
                .then(function (data) {
                    if (!data || !data.success) throw new Error();
                    likeButton.classList.add('is-liked', 'has-count');
                    var reactions = likeButton.closest('.post-reactions');
                    if (reactions) reactions.classList.add('is-liked');
                    likeButton.setAttribute('aria-pressed', 'true');
                    var label = likeButton.querySelector('[data-like-label]');
                    var count = likeButton.querySelector('[data-like-count]');
                    var icon = likeButton.querySelector('[data-like-icon]');
                    if (label) label.textContent = '已喜欢';
                    if (count) { count.textContent = String(data.count || 0); count.hidden = false; }
                    if (icon) { icon.classList.remove('fa-regular'); icon.classList.add('fa-solid'); }
                    celebrateLike(likeButton);
                }).catch(function () {
                    // 失败时给出可感知的反馈，而不是静默回滚
                    likeButton.classList.add('is-error');
                    setStatus('点赞失败，请稍后重试');
                    window.setTimeout(function () { likeButton.classList.remove('is-error'); }, 2400);
                }).finally(function () {
                    likeButton.dataset.likeBusy = '0';
                    likeButton.removeAttribute('aria-busy');
                });
            return;
        }

        var card = event.target.closest('[data-post-url]');
        if (card && !event.target.closest('a, button, input, textarea, select, label')) {
            event.preventDefault();
            navigate(card.getAttribute('data-post-url'));
            return;
        }

        var image = event.target.closest('#qiwi-pjax .article-body img, #qiwi-pjax .article-hero, #qiwi-pjax .moment-image, #qiwi-pjax .comment-item.is-trusted-comment .comment-text img, #qiwi-pjax .moment-comment.is-trusted-comment .moment-comment-text img');
        if (image) {
            if (image.classList.contains('comment-sticker')) return;
            event.preventDefault();
            openLightbox(image);
            return;
        }

        var thoughtMark = event.target.closest('.thought-mark[data-thought-range]');
        if (thoughtMark) {
            event.preventDefault();
            var thoughtStateRef = thoughtState;
            if (!thoughtStateRef) return;
            var thoughtKey = thoughtMark.getAttribute('data-thought-range');
            var thoughtRange = thoughtStateRef.ranges[thoughtKey];
            if (!thoughtRange) return;
            thoughtOpenPopover(thoughtRange.start, thoughtRange.end, false);
            return;
        }

        var commentAnchor = event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey
            ? event.target.closest('.comment-reply-target[href^="#"]')
            : null;
        if (commentAnchor && !event.defaultPrevented) {
            var anchorHash = commentAnchor.getAttribute('href') || '';
            var anchorTarget = locateAnchorTarget(anchorHash);
            if (anchorTarget) {
                event.preventDefault();
                try {
                    history.pushState(null, '', anchorHash);
                    lastKnownUrl = window.location.href;
                } catch (error) {}
                anchorTarget.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'center' });
                if (anchorTarget.classList.contains('comment-item')) highlightCommentItem(anchorTarget);
                return;
            }
        }

        var link = event.target.closest('a[href]');
        if (!shouldHandleLink(event, link)) return;
        event.preventDefault();
        navigate(link.href);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            var lightbox = document.querySelector('.v2-lightbox.is-visible');
            if (lightbox) { event.preventDefault(); closeLightbox(lightbox); return; }
            if (thoughtPopover && !thoughtPopover.hidden) { event.preventDefault(); closeThoughtPopover(); }
            return;
        }
        if (event.key !== 'Enter' && event.key !== ' ') return;
        var markTarget = event.target.closest ? event.target.closest('.thought-mark[data-thought-range]') : null;
        if (markTarget && event.target === markTarget) {
            event.preventDefault();
            var stateRef = thoughtState;
            if (!stateRef) return;
            var rangeRef = stateRef.ranges[markTarget.getAttribute('data-thought-range')];
            if (rangeRef) thoughtOpenPopover(rangeRef.start, rangeRef.end, false);
            return;
        }
        var card = event.target.closest('[data-post-url]');
        if (!card || event.target !== card) return;
        event.preventDefault();
        navigate(card.getAttribute('data-post-url'));
    });

    window.addEventListener('popstate', function () {
        if (!pjaxReady) return;
        var previousUrl = lastKnownUrl;
        lastKnownUrl = window.location.href;
        var previous = normalizeUrl(previousUrl);
        var current = normalizeUrl(window.location.href);
        // 同一页面的 hash 前后退只需定位锚点，不必重新拉取整页
        if (previous && current && previous.pathname === current.pathname && previous.search === current.search) {
            revealAnchorFromHistory();
            return;
        }
        navigate(window.location.href, { popstate: true });
    });
    window.addEventListener('scroll', requestBackToTopUpdate, { passive: true });
    window.addEventListener('resize', requestBackToTopUpdate);
    window.addEventListener('resize', requestMomentTextFoldRefresh);

    initGlobalNavigation();
    initPjaxPage(document, false);
    try { history.replaceState({ qiwiPjax: true, qiwiScrollY: window.scrollY }, '', window.location.href); } catch (error) {}
    window.QiwiPJAX = {
        navigate: navigate,
        refresh: function () { initPjaxPage(currentContainer() || document); },
        // 供多 Tab 页面在切换面板后重建目录（只收录当前可见面板的标题）
        rebuildToc: function () {
            var root = currentContainer() || document;
            var toc = root.querySelector('.article-toc');
            if (toc) {
                toc.innerHTML = '';
                toc.hidden = false;
            }
            initToc(root);
        }
    };
})();
