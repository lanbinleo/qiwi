(function () {
    'use strict';

    var containerSelector = '#qiwi-pjax';
    var activeRequest = null;
    var navigationId = 0;
    var dynamicPageListeners = [];
    var tocObserver = null;
    var tocProgressCleanup = null;
    var latestMomentTimer = null;
    var stickerPackCache = {};
    var pjaxReady = Boolean(window.fetch && window.DOMParser && window.history && window.history.pushState);

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
            document.head.appendChild(clone);
        });
    }

    function executeScripts(root) {
        var scripts = Array.prototype.slice.call(root.querySelectorAll('script'));
        var chain = Promise.resolve();
        var nativeAddEventListener = EventTarget.prototype.addEventListener;
        EventTarget.prototype.addEventListener = function (type, listener, options) {
            if (this === document && type === 'DOMContentLoaded' && document.readyState !== 'loading') {
                Promise.resolve().then(function () { listener.call(document, new Event('DOMContentLoaded')); });
                return;
            }
            dynamicPageListeners.push({ target: this, type: type, listener: listener, options: options });
            return nativeAddEventListener.call(this, type, listener, options);
        };
        scripts.forEach(function (oldScript) {
            chain = chain.then(function () {
                return new Promise(function (resolve) {
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
                        script.addEventListener('load', resolve, { once: true });
                        script.addEventListener('error', resolve, { once: true });
                        oldScript.replaceWith(script);
                    } else {
                        script.textContent = oldScript.textContent;
                        oldScript.replaceWith(script);
                        resolve();
                    }
                });
            });
        });
        return chain.finally(function () {
            EventTarget.prototype.addEventListener = nativeAddEventListener;
        });
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
            font: { readable: '易读', plain: '普通' },
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
                    var stickerToggle = form.querySelector('[data-comment-sticker-toggle]');
                    if (stickerToggle) stickerToggle.setAttribute('aria-expanded', 'false');
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
                try { localStorage.setItem('qiwi-comment-profile', JSON.stringify({ author: author.value.trim(), mail: mail.value.trim(), url: url ? url.value.trim() : '' })); } catch (error) {}
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
        root.querySelectorAll('.comment-form').forEach(function (form) {
            if (form.dataset.v2StickersReady === '1') return;
            form.dataset.v2StickersReady = '1';
            var panel = form.querySelector('[data-comment-sticker-panel]');
            var toggle = form.querySelector('[data-comment-sticker-toggle]');
            var textarea = form.querySelector('textarea[name="text"]');
            var configNode = form.querySelector('[data-comment-sticker-packs]');
            if (!panel || !toggle || !textarea || !configNode) return;
            var tabs = panel.querySelector('[data-comment-sticker-tabs]');
            var grid = panel.querySelector('[data-comment-sticker-grid]');
            var status = panel.querySelector('[data-comment-sticker-status]');
            var close = panel.querySelector('[data-comment-sticker-close]');
            var packs = [];
            var loaded = false;

            try { packs = JSON.parse(configNode.textContent || '[]'); } catch (error) { packs = []; }
            if (!packs.length) return;

            function setOpen(open) {
                if (open) {
                    form.classList.remove('is-profile-open');
                    var profileToggle = form.querySelector('[data-comment-profile-toggle]');
                    if (profileToggle) profileToggle.setAttribute('aria-expanded', 'false');
                }
                form.classList.toggle('is-sticker-open', open);
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

    function initToc(root) {
        if (tocObserver) {
            tocObserver.disconnect();
            tocObserver = null;
        }
        if (tocProgressCleanup) {
            tocProgressCleanup();
            tocProgressCleanup = null;
        }
        var toc = root.querySelector('.article-toc');
        var body = root.querySelector('.article-body');
        if (!body && toc) body = toc.closest('.about-page, .friends-page, .timemachine-page');
        if (!toc || !body || toc.children.length) return;
        var headings = Array.prototype.slice.call(body.querySelectorAll('h2, h3, h4'));
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
        var progress = document.createElement('span');
        progress.className = 'toc-progress';
        progress.setAttribute('aria-hidden', 'true');
        toc.insertBefore(progress, list);
        var mobileProgress = document.createElement('span');
        mobileProgress.className = 'toc-mobile-progress';
        mobileProgress.setAttribute('aria-hidden', 'true');
        toc.appendChild(mobileProgress);

        title.addEventListener('click', function () {
            var open = toc.classList.toggle('is-open');
            title.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        toc.appendChild(list);

        if ('IntersectionObserver' in window) {
            var links = Array.prototype.slice.call(toc.querySelectorAll('.toc-link'));
            tocObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    links.forEach(function (link) {
                        var active = link.getAttribute('href') === '#' + entry.target.id;
                        link.classList.toggle('is-active', active);
                        if (active) {
                            toc.querySelectorAll('.toc-item.is-section-active').forEach(function (item) { item.classList.remove('is-section-active'); });
                            var section = link.closest('.toc-item.level-2') || link.closest('.toc-item');
                            if (section) section.classList.add('is-section-active');
                        }
                    });
                });
            }, { rootMargin: '-80px 0px -72% 0px' });
            headings.forEach(function (heading) { tocObserver.observe(heading); });
        }

        var frame = null;
        function updateProgress() {
            frame = null;
            var rect = body.getBoundingClientRect();
            var start = window.scrollY + rect.top - window.innerHeight * .24;
            var distance = Math.max(1, rect.height - window.innerHeight * .52);
            var value = Math.max(0, Math.min(1, (window.scrollY - start) / distance));
            toc.style.setProperty('--toc-progress', (value * 100).toFixed(2) + '%');
        }
        function requestProgress() {
            if (frame !== null) return;
            frame = window.requestAnimationFrame(updateProgress);
        }
        window.addEventListener('scroll', requestProgress, { passive: true });
        window.addEventListener('resize', requestProgress);
        tocProgressCleanup = function () {
            window.removeEventListener('scroll', requestProgress);
            window.removeEventListener('resize', requestProgress);
            if (frame !== null) window.cancelAnimationFrame(frame);
        };
        updateProgress();
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

    function initPjaxPage(root, afterPjax) {
        initReadingPreferences(root);
        initCommentProfiles(root);
        initCommentStickers(root);
        initLatestMoment(root);
        initToc(root);
        initCodeBlocks(root);
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

                updateHead(nextDocument);
                container.innerHTML = nextContainer.innerHTML;
                if (!options.popstate) history.pushState({ qiwiPjax: true, qiwiScrollY: 0 }, '', target.href);
                updateNavigation(target.href);

                return executeScripts(container).then(function () {
                    initPjaxPage(container, true);
                    var scrollY = requestedScrollY;
                    if (target.hash) {
                        var anchor = document.getElementById(decodeURIComponent(target.hash.slice(1)));
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
            if (error && error.name === 'AbortError' && requestId !== navigationId) return;
            window.location.assign(target.href);
        }).finally(function () {
            window.clearTimeout(timeout);
            if (requestId === navigationId) activeRequest = null;
        });
    }

    function getLightboxTargetRect(preview) {
        var padding = window.innerWidth <= 700 ? 18 : 40;
        var availableWidth = Math.max(1, Math.min(1200, window.innerWidth - padding * 2));
        var availableHeight = Math.max(1, window.innerHeight - padding * 2);
        var naturalWidth = preview.naturalWidth || availableWidth;
        var naturalHeight = preview.naturalHeight || availableHeight;
        var scale = Math.min(1, availableWidth / naturalWidth, availableHeight / naturalHeight);
        var width = Math.max(1, naturalWidth * scale);
        var height = Math.max(1, naturalHeight * scale);
        return {
            left: (window.innerWidth - width) / 2,
            top: (window.innerHeight - height) / 2,
            width: width,
            height: height
        };
    }

    function clearLightbox(lightbox) {
        var preview = lightbox.querySelector('img');
        var source = lightbox._qiwiSource;
        if (lightbox._qiwiCloseTimer) window.clearTimeout(lightbox._qiwiCloseTimer);
        lightbox._qiwiCloseTimer = null;
        if (source && source.classList) source.classList.remove('v2-lightbox-source');
        lightbox._qiwiSource = null;
        lightbox.classList.remove('is-visible', 'is-open', 'is-closing');
        preview.classList.remove('is-prepared', 'is-open');
        preview.removeAttribute('src');
        preview.removeAttribute('style');
        document.documentElement.classList.remove('v2-lightbox-open');
    }

    function closeLightbox(lightbox) {
        if (!lightbox || !lightbox.classList.contains('is-visible') || lightbox.classList.contains('is-closing')) return;
        var preview = lightbox.querySelector('img');
        var source = lightbox._qiwiSource;
        var sourceRect = source && source.isConnected ? source.getBoundingClientRect() : null;
        lightbox._qiwiRequest = (lightbox._qiwiRequest || 0) + 1;
        lightbox.classList.add('is-closing');
        lightbox.classList.remove('is-open');

        if (!prefersReducedMotion() && sourceRect && sourceRect.width > 0 && sourceRect.height > 0) {
            var targetLeft = parseFloat(preview.style.left) || 0;
            var targetTop = parseFloat(preview.style.top) || 0;
            var targetWidth = parseFloat(preview.style.width) || preview.offsetWidth || 1;
            var targetHeight = parseFloat(preview.style.height) || preview.offsetHeight || 1;
            preview.style.transform = 'translate(' + (sourceRect.left - targetLeft) + 'px,' + (sourceRect.top - targetTop) + 'px) scale(' + (sourceRect.width / targetWidth) + ',' + (sourceRect.height / targetHeight) + ')';
        } else {
            preview.style.opacity = '0';
        }
        preview.classList.remove('is-open');

        lightbox._qiwiCloseTimer = window.setTimeout(function () { clearLightbox(lightbox); }, prefersReducedMotion() ? 0 : 370);
    }

    function openLightbox(source) {
        var lightbox = document.querySelector('.v2-lightbox');
        if (!lightbox) {
            lightbox = document.createElement('button');
            lightbox.type = 'button';
            lightbox.className = 'v2-lightbox';
            lightbox.setAttribute('aria-label', '关闭图片预览');
            lightbox.innerHTML = '<img alt="">';
            document.body.appendChild(lightbox);
            lightbox.addEventListener('click', function () { closeLightbox(lightbox); });
        }
        if (lightbox.classList.contains('is-visible')) return;

        var preview = lightbox.querySelector('img');
        var requestId = (lightbox._qiwiRequest || 0) + 1;
        lightbox._qiwiRequest = requestId;
        lightbox._qiwiSource = source;
        preview.alt = source.alt || '';
        preview.src = source.currentSrc || source.src;
        lightbox.classList.add('is-visible');
        document.documentElement.classList.add('v2-lightbox-open');

        var decoded = typeof preview.decode === 'function' ? preview.decode() : Promise.resolve();
        decoded.catch(function () {}).then(function () {
            if (lightbox._qiwiRequest !== requestId || !preview.src) return;
            var sourceRect = source.getBoundingClientRect();
            var targetRect = getLightboxTargetRect(preview);
            preview.style.left = targetRect.left + 'px';
            preview.style.top = targetRect.top + 'px';
            preview.style.width = targetRect.width + 'px';
            preview.style.height = targetRect.height + 'px';
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
                });
            });
        });
    }

    document.addEventListener('click', function (event) {
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
                }).catch(function () {}).finally(function () {
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

        var image = event.target.closest('#qiwi-pjax .article-body img, #qiwi-pjax .article-hero, #qiwi-pjax .moment-image');
        if (image) {
            event.preventDefault();
            openLightbox(image);
            return;
        }

        var link = event.target.closest('a[href]');
        if (!shouldHandleLink(event, link)) return;
        event.preventDefault();
        navigate(link.href);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            var lightbox = document.querySelector('.v2-lightbox.is-visible');
            if (lightbox) { event.preventDefault(); closeLightbox(lightbox); }
            return;
        }
        if (event.key !== 'Enter' && event.key !== ' ') return;
        var card = event.target.closest('[data-post-url]');
        if (!card || event.target !== card) return;
        event.preventDefault();
        navigate(card.getAttribute('data-post-url'));
    });

    window.addEventListener('popstate', function () {
        if (!pjaxReady) return;
        navigate(window.location.href, { popstate: true });
    });

    initGlobalNavigation();
    initPjaxPage(document, false);
    try { history.replaceState({ qiwiPjax: true, qiwiScrollY: window.scrollY }, '', window.location.href); } catch (error) {}
    window.QiwiPJAX = { navigate: navigate, refresh: function () { initPjaxPage(currentContainer() || document); } };
})();
