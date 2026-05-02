<?php
ob_start();
$this->need('version.php');
$versionDrawerOutput = ob_get_clean();
$themeVersion = '';

if (preg_match('/^\s*([0-9][^<\s]*)/', $versionDrawerOutput, $matches)) {
    $themeVersion = $matches[1];
    $versionDrawerOutput = preg_replace('/^\s*' . preg_quote($themeVersion, '/') . '\s*/', '', $versionDrawerOutput, 1);
}
?>

<!-- 页脚 -->
<footer class="site-footer">
    <div class="footer-content">
        <div class="footer-info">
            <p>&copy; <?php echo date('Y'); ?> <a href="<?php $this->options->siteUrl(); ?>"><?php $this->options->title(); ?></a>. 由 <a href="https://typecho.org" target="_blank" rel="noopener">Typecho</a> 强力驱动.</p>
            <?php if ($this->options->footerInfo): ?>
                <p class="site-description"><?php $this->options->footerInfo() ?></p>
            <?php elseif ($this->options->description): ?>
                <p class="site-description"><?php $this->options->description() ?></p>
            <?php endif; ?>
        </div>

        <div class="footer-meta">
            <div class="footer-links">
                <a href="<?php $this->options->feedUrl(); ?>" target="_blank" rel="noopener" title="RSS 订阅">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 11a9 9 0 0 1 9 9"></path>
                        <path d="M4 4a16 16 0 0 1 16 16"></path>
                        <circle cx="5" cy="19" r="1"></circle>
                    </svg>
                    RSS
                </a>
                <?php if ($this->user->hasLogin()): ?>
                <a href="<?php $this->options->adminUrl(); ?>" target="_blank" rel="noopener" title="管理后台">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    管理
                </a>
                <?php endif; ?>
            </div>

            <div class="theme-info">
                <span class="theme-name"><a href="https://github.com/lanbinleo/qiwi" target="_blank">Qiwi Theme</a></span>
                <button class="theme-version" type="button" onclick="window.showQiwiVersionDrawer()" title="查看更新日志">v<?php echo htmlspecialchars($themeVersion, ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
        </div>
    </div>
</footer>

<?php echo $versionDrawerOutput; ?>

<!-- 主题切换脚本 -->
<script>
function updateThemeToggleButtons(theme) {
    document.querySelectorAll('.theme-toggle').forEach(function(toggleBtn) {
        toggleBtn.textContent = theme === 'light' ? '◐' : '◑';
        toggleBtn.setAttribute('aria-label', theme === 'light' ? '切换到深色模式' : '切换到浅色模式');
    });
}

// 立即执行主题初始化，避免闪烁
(function() {
    const htmlElement = document.documentElement;
    const savedTheme = localStorage.getItem('theme-preference');

    // 如果有保存的主题偏好，使用保存的
    if (savedTheme === 'dark') {
        htmlElement.setAttribute('data-theme', 'dark');
    } else if (savedTheme === 'light') {
        htmlElement.setAttribute('data-theme', 'light');
    } else {
        // 没有保存的偏好，默认使用浅色模式
        htmlElement.setAttribute('data-theme', 'light');
        localStorage.setItem('theme-preference', 'light');
    }

    updateThemeToggleButtons(htmlElement.getAttribute('data-theme'));
})();

function toggleTheme() {
    const htmlElement = document.documentElement;
    const currentTheme = htmlElement.getAttribute('data-theme');
    let newTheme;

    if (currentTheme === 'light') {
        htmlElement.setAttribute('data-theme', 'dark');
        newTheme = 'dark';
    } else {
        htmlElement.setAttribute('data-theme', 'light');
        newTheme = 'light';
    }

    localStorage.setItem('theme-preference', newTheme);
    updateThemeToggleButtons(newTheme);
}

function initMobileNavigation() {
    const navToggle = document.querySelector('.nav-toggle');
    const navMenu = document.getElementById('site-navigation-menu');
    const submenuToggles = document.querySelectorAll('.nav-submenu-toggle');

    if (!navToggle || !navMenu) {
        return;
    }

    document.body.classList.add('nav-js-enabled');

    const setSubmenuState = function(toggle, isOpen) {
        const navItem = toggle.closest('.nav-item-has-children');
        if (!navItem) return;

        navItem.classList.toggle('is-submenu-open', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    const setMenuState = function(isOpen) {
        navMenu.classList.toggle('is-open', isOpen);
        document.body.classList.toggle('nav-open', isOpen);
        navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

        submenuToggles.forEach(function(toggle) {
            const navItem = toggle.closest('.nav-item-has-children');
            const hasCurrentChild = navItem && navItem.querySelector('.nav-submenu a.current');

            if (isOpen && hasCurrentChild) {
                setSubmenuState(toggle, true);
                return;
            }

            if (!isOpen) {
                setSubmenuState(toggle, false);
            }
        });

    };

    navToggle.addEventListener('click', function() {
        setMenuState(!navMenu.classList.contains('is-open'));
    });

    submenuToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            setSubmenuState(toggle, toggle.getAttribute('aria-expanded') !== 'true');
        });
    });

    navMenu.querySelectorAll('a').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                setMenuState(false);
            }
        });
    });

    document.addEventListener('click', function(event) {
        if (
            window.innerWidth <= 768
            && navMenu.classList.contains('is-open')
            && !navMenu.contains(event.target)
            && !navToggle.contains(event.target)
        ) {
            setMenuState(false);
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            setMenuState(false);
        }
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            setMenuState(false);
        }
    });
}

function initArticleToc() {
    var tocContainer = document.querySelector('.article-toc');
    var articlePage = tocContainer ? tocContainer.closest('.article-page, .about-page, .timemachine-page, .friends-page') : null;
    var articleBody = articlePage ? articlePage.querySelector('.article-body') : document.querySelector('.article-body');
    if (!tocContainer || !articleBody) return;

    function getHeadingText(heading) {
        var clone = heading.cloneNode(true);
        clone.querySelectorAll('.header-anchor, .anchor, .heading-anchor, a[href^="#"]').forEach(function(anchor) {
            var text = anchor.textContent.replace(/\s+/g, '').trim();
            if (text === '' || text === '#' || text === '¶') {
                anchor.remove();
            }
        });
        return clone.textContent.replace(/\s+/g, ' ').trim();
    }

    function isUsableHeading(heading) {
        if (!getHeadingText(heading)) return false;

        var parent = heading.parentElement;
        while (parent && parent !== articleBody) {
            if (parent.tagName === 'DETAILS' && !parent.open) {
                return false;
            }
            parent = parent.parentElement;
        }

        return true;
    }

    function slugifyHeading(text, fallback) {
        var slug = text.toLowerCase()
            .replace(/<[^>]+>/g, '')
            .replace(/[\s\/\\?%*:|"<>.,;()[\]{}+=!@#$^&~`]+/g, '-')
            .replace(/^-+|-+$/g, '');
        return slug || fallback;
    }

    function uniqueHeadingId(heading, text, index, usedIds) {
        var currentId = heading.id ? heading.id.trim() : '';
        if (currentId && !usedIds[currentId]) {
            usedIds[currentId] = true;
            return currentId;
        }

        var base = slugifyHeading(text, 'heading-' + index);
        var id = base;
        var counter = 2;
        while (usedIds[id] || document.getElementById(id)) {
            id = base + '-' + counter;
            counter++;
        }

        heading.id = id;
        usedIds[id] = true;
        return id;
    }

    var headings = Array.prototype.slice.call(articleBody.querySelectorAll('h2, h3, h4')).filter(isUsableHeading);
    if (headings.length === 0) {
        if (articlePage) articlePage.classList.remove('has-toc');
        tocContainer.style.display = 'none';
        return;
    }

    if (articlePage) articlePage.classList.add('has-toc');

    var usedIds = {};
    document.querySelectorAll('[id]').forEach(function(element) {
        if (!articleBody.contains(element)) {
            usedIds[element.id] = true;
        }
    });

    // Create sticky inner wrapper
    var inner = document.createElement('div');
    inner.className = 'toc-inner';

    // Back to top
    var backTop = document.createElement('button');
    backTop.className = 'toc-back-top';
    backTop.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18,15 12,9 6,15"></polyline></svg><span>回到顶部</span>';
    backTop.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    inner.appendChild(backTop);

    // Separator
    var sep1 = document.createElement('hr');
    sep1.className = 'toc-separator';
    inner.appendChild(sep1);

    // Title
    var title = document.createElement('p');
    title.className = 'toc-title';
    title.textContent = '目录';
    inner.appendChild(title);

    // TOC list
    var list = document.createElement('ul');
    list.className = 'toc-list';

    headings.forEach(function(h, index) {
        var headingText = getHeadingText(h);
        var headingId = uniqueHeadingId(h, headingText, index, usedIds);
        var level = parseInt(h.tagName.charAt(1));
        var li = document.createElement('li');
        li.className = 'toc-item level-' + level;
        var a = document.createElement('a');
        a.className = 'toc-link';
        a.href = '#' + headingId;
        a.textContent = headingText;
        a.title = headingText;
        a.addEventListener('click', function(e) {
            e.preventDefault();
            h.scrollIntoView({ behavior: 'smooth' });
            history.replaceState(null, null, '#' + encodeURIComponent(headingId));
        });
        li.appendChild(a);
        list.appendChild(li);
    });

    inner.appendChild(list);

    // Separator
    var sep2 = document.createElement('hr');
    sep2.className = 'toc-separator';
    inner.appendChild(sep2);

    // Go to comments
    var commentsSection = document.querySelector('.comments-wrapper');
    if (commentsSection) {
        var goComments = document.createElement('button');
        goComments.className = 'toc-go-comments';
        goComments.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 21 1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"></path></svg><span>前往评论</span>';
        goComments.addEventListener('click', function() {
            commentsSection.scrollIntoView({ behavior: 'smooth' });
        });
        inner.appendChild(goComments);
    }

    tocContainer.appendChild(inner);

    // Scroll spy with IntersectionObserver
    var tocLinks = inner.querySelectorAll('.toc-link');
    var currentActive = null;

    if (!('IntersectionObserver' in window)) {
        if (tocLinks[0]) tocLinks[0].classList.add('is-active');
        return;
    }

    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                var id = entry.target.id;
                if (currentActive) currentActive.classList.remove('is-active');
                tocLinks.forEach(function(link) {
                    if (link.getAttribute('href') === '#' + id) {
                        link.classList.add('is-active');
                        currentActive = link;
                    }
                });
            }
        });
    }, { rootMargin: '-80px 0px -75% 0px' });

    headings.forEach(function(h) { observer.observe(h); });
}

function initHomeHero() {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    function escapeHtml(text) {
        return String(text).replace(/[&<>"']/g, function(char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function fetchHitokotoItem() {
        var controller = window.AbortController ? new AbortController() : null;
        var timeout = null;
        var timedOut = false;

        var request = fetch('https://v1.hitokoto.cn', controller ? { signal: controller.signal } : undefined)
            .then(function(response) {
                if (!response.ok) return null;
                return response.json();
            })
            .catch(function() { return null; });

        var timeoutFallback = new Promise(function(resolve) {
            timeout = window.setTimeout(function() {
                timedOut = true;
                if (controller) controller.abort();
                resolve(null);
            }, 2800);
        });

        return Promise.race([request, timeoutFallback])
            .then(function(data) {
                if (!timedOut && timeout) window.clearTimeout(timeout);
                if (!data || !data.hitokoto) return null;
                var source = data.from || '';
                if (data.from_who) {
                    source = source ? source + ' - ' + data.from_who : data.from_who;
                }
                var text = source ? data.hitokoto + ' —— ' + source : data.hitokoto;
                return {
                    html: escapeHtml(data.hitokoto),
                    text: data.hitokoto
                };
            })
            .catch(function() { return null; });
    }

    document.querySelectorAll('[data-home-hero]').forEach(function(hero) {
        var line = hero.querySelector('.home-hero-line');
        if (!line) return;

        function updateHeroSelectionColor(source) {
            var root = document.createElement('div');
            var accent = null;
            root.innerHTML = source || line.innerHTML || '';

            var accentNode = root.querySelector('[class*="home-hero-accent-"]');
            if (accentNode) {
                String(accentNode.className || '').split(/\s+/).some(function(className) {
                    var match = className.match(/^home-hero-accent-(caramel|red|orange|yellow|green|cyan|blue|purple)$/);
                    if (!match) return false;
                    accent = match[1];
                    return true;
                });
            }

            if (!accent) {
                line.style.removeProperty('--home-hero-selection-color');
                return;
            }

            line.style.setProperty(
                '--home-hero-selection-color',
                accent === 'caramel' ? 'var(--caramel)' : 'var(--hue-' + accent + ')'
            );
        }

        updateHeroSelectionColor();

        var mode = hero.getAttribute('data-home-hero-mode') || 'list';
        var items = [];
        try {
            items = JSON.parse(hero.getAttribute('data-home-hero-items') || '[]');
        } catch (error) {
            items = [];
        }

        if (!items.length) return;
        if (items.length <= 1 && mode === 'list') return;

        var animation = hero.getAttribute('data-home-hero-animation') || 'fade';
        var interval = parseInt(hero.getAttribute('data-home-hero-interval') || '5200', 10);
        if (!interval || interval < 1500) interval = 5200;
        var typingSpeed = parseInt(hero.getAttribute('data-home-hero-typing-speed') || '92', 10);
        var deletingSpeed = parseInt(hero.getAttribute('data-home-hero-deleting-speed') || '24', 10);
        var typingPause = parseInt(hero.getAttribute('data-home-hero-typing-pause') || '220', 10);
        if (!typingSpeed || typingSpeed < 20) typingSpeed = 92;
        if (!deletingSpeed || deletingSpeed < 10) deletingSpeed = 24;
        if (isNaN(typingPause) || typingPause < 0) typingPause = 220;
        if (animation === 'typewriter') {
            line.classList.add('is-typewriter');
        }

        var index = 0;
        var hasCompletedList = false;
        var shouldInsertHitokoto = false;
        var rotationTimer = null;
        var rotationPaused = false;
        var typingTimer = null;
        var animationToken = 0;

        function localNext() {
            index = (index + 1) % items.length;
            return items[index];
        }

        function showItem(item) {
            if (!item || !item.html) return;
            animationToken++;
            updateHeroSelectionColor(item.html);
            var token = animationToken;
            if (typingTimer) {
                window.clearTimeout(typingTimer);
                typingTimer = null;
            }

            if (animation === 'typewriter') {
                typeItem(item, token);
                return;
            }

            line.classList.add('is-switching');
            window.setTimeout(function() {
                if (token !== animationToken) return;
                line.innerHTML = item.html;
                updateHeroSelectionColor(item.html);
                line.classList.remove('is-switching');
            }, 180);
        }

        function typeItem(item, token) {
            var currentText = line.textContent || '';

            function renderText(text) {
                line.textContent = text;
            }

            function htmlToTokens(html) {
                var root = document.createElement('div');
                root.innerHTML = html || '';
                var tokens = [];

                function walk(node, classes) {
                    if (node.nodeType === 3) {
                        Array.prototype.forEach.call(node.nodeValue || '', function(char) {
                            tokens.push({ char: char, classes: classes.slice() });
                        });
                        return;
                    }

                    if (node.nodeType !== 1) return;

                    var nextClasses = classes.slice();
                    if (node.className) {
                        String(node.className).split(/\s+/).forEach(function(className) {
                            if (className && className.indexOf('home-hero-accent') === 0) {
                                nextClasses.push(className);
                            }
                        });
                    }

                    Array.prototype.forEach.call(node.childNodes, function(child) {
                        walk(child, nextClasses);
                    });
                }

                Array.prototype.forEach.call(root.childNodes, function(child) {
                    walk(child, []);
                });

                return tokens;
            }

            function renderTokens(tokens, count) {
                var html = '';
                var activeClasses = '';
                var hasOpenSpan = false;

                function closeSpan() {
                    if (hasOpenSpan) {
                        html += '</span>';
                        hasOpenSpan = false;
                        activeClasses = '';
                    }
                }

                tokens.slice(0, count).forEach(function(token) {
                    var className = token.classes.join(' ');
                    if (className !== activeClasses) {
                        closeSpan();
                        if (className) {
                            html += '<span class="' + className + '">';
                            activeClasses = className;
                            hasOpenSpan = true;
                        }
                    }
                    html += escapeHtml(token.char);
                });

                closeSpan();
                line.innerHTML = html;
            }

            var nextTokens = htmlToTokens(item.html);

            function deleteStep() {
                if (token !== animationToken) return;
                if (currentText.length > 0) {
                    currentText = currentText.slice(0, -1);
                    renderText(currentText);
                    typingTimer = window.setTimeout(deleteStep, deletingSpeed);
                    return;
                }
                typingTimer = window.setTimeout(typeStep, typingPause);
            }

            var cursor = 0;
            function typeStep() {
                if (token !== animationToken) return;
                if (cursor < nextTokens.length) {
                    cursor++;
                    renderTokens(nextTokens, cursor);
                    typingTimer = window.setTimeout(typeStep, typingSpeed);
                    return;
                }
                line.innerHTML = item.html;
                updateHeroSelectionColor(item.html);
                typingTimer = null;
            }

            deleteStep();
        }

        function nextItem() {
            if (mode === 'list') {
                showItem(localNext());
                scheduleNextRotation();
                return;
            }

            if (!hasCompletedList) {
                index += 1;
                if (index < items.length) {
                    showItem(items[index]);
                    scheduleNextRotation();
                    return;
                }

                hasCompletedList = true;
                index = 0;
                if (mode === 'loop-hitokoto') {
                    shouldInsertHitokoto = true;
                    showItem(items[index]);
                    index = (index + 1) % items.length;
                    scheduleNextRotation();
                    return;
                }
            }

            if (mode === 'loop-hitokoto') {
                if (shouldInsertHitokoto) {
                    var fallbackItem = items[index];
                    index = (index + 1) % items.length;
                    fetchHitokotoItem().then(function(item) {
                        showItem(item || fallbackItem);
                        scheduleNextRotation();
                    });
                    shouldInsertHitokoto = false;
                    return;
                }

                showItem(items[index]);
                index = (index + 1) % items.length;
                shouldInsertHitokoto = true;
                scheduleNextRotation();
                return;
            }

            fetchHitokotoItem().then(function(item) {
                showItem(item || localNext());
                scheduleNextRotation();
            });
        }

        function scheduleNextRotation(delay) {
            if (rotationPaused || document.hidden) return;

            if (rotationTimer) {
                window.clearTimeout(rotationTimer);
            }

            rotationTimer = window.setTimeout(function() {
                rotationTimer = null;
                nextItem();
            }, typeof delay === 'number' ? delay : interval);
        }

        function startRotation() {
            rotationPaused = false;
            if (!rotationTimer) scheduleNextRotation(interval);
        }

        function stopRotation() {
            rotationPaused = true;
            if (rotationTimer) {
                window.clearTimeout(rotationTimer);
                rotationTimer = null;
            }
        }

        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopRotation();
            } else {
                startRotation();
            }
        });

        startRotation();
    });
}

function initHomeJike() {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    document.querySelectorAll('[data-home-jike]').forEach(function(module) {
        var viewport = module.querySelector('.home-jike-viewport');
        var track = module.querySelector('.home-jike-track');
        if (!viewport || !track) return;

        var items = track.querySelectorAll('.home-jike-item');
        if (items.length <= 1) return;

        var timer = null;
        var isAnimating = false;

        function getStepHeight() {
            return viewport.clientHeight;
        }

        function stopRotation() {
            if (timer) {
                window.clearInterval(timer);
                timer = null;
            }
        }

        function rotateOnce() {
            if (isAnimating) return;

            isAnimating = true;
            track.style.transition = 'transform 420ms cubic-bezier(0.33, 1, 0.68, 1)';
            track.style.transform = 'translateY(-' + getStepHeight() + 'px)';
        }

        function startRotation() {
            if (timer) return;
            timer = window.setInterval(rotateOnce, 4200);
        }

        track.addEventListener('transitionend', function(event) {
            if (event.propertyName !== 'transform') return;

            track.appendChild(track.firstElementChild);
            track.style.transition = 'none';
            track.style.transform = 'translateY(0)';
            track.offsetHeight;
            isAnimating = false;
        });

        module.addEventListener('mouseenter', stopRotation);
        module.addEventListener('mouseleave', startRotation);
        module.addEventListener('focusin', stopRotation);
        module.addEventListener('focusout', function(event) {
            if (!module.contains(event.relatedTarget)) {
                startRotation();
            }
        });

        startRotation();
    });
}

function initCommentProfile() {
    document.querySelectorAll('.comment-form').forEach(function(form) {
        var modal = form.querySelector('[data-comment-profile-modal]');
        var toggle = form.querySelector('[data-comment-profile-toggle]');
        if (!modal || !toggle) return;

        var authorInput = form.querySelector('#author');
        var mailInput = form.querySelector('#mail');
        var urlInput = form.querySelector('#url');
        var textInput = form.querySelector('#textarea');
        var label = form.querySelector('[data-comment-identity-label]');
        var closeButtons = form.querySelectorAll('[data-comment-profile-close]');
        var saveButton = form.querySelector('[data-comment-profile-save]');
        var storageKey = 'qiwi-comment-profile';

        function readProfile() {
            try {
                return JSON.parse(localStorage.getItem(storageKey) || '{}');
            } catch (error) {
                return {};
            }
        }

        function saveProfile() {
            try {
                localStorage.setItem(storageKey, JSON.stringify({
                    author: authorInput ? authorInput.value.trim() : '',
                    mail: mailInput ? mailInput.value.trim() : '',
                    url: urlInput ? urlInput.value.trim() : ''
                }));
            } catch (error) {}
        }

        function hasProfile() {
            return !!(authorInput && authorInput.value.trim() && mailInput && mailInput.value.trim());
        }

        function updateLabel() {
            if (!label) return;
            label.textContent = hasProfile() ? authorInput.value.trim() : '未设置';
        }

        function getInvalidControl(controls) {
            for (var i = 0; i < controls.length; i++) {
                if (controls[i] && !controls[i].checkValidity()) {
                    return controls[i];
                }
            }

            return null;
        }

        function reportInvalid(control) {
            if (!control) return;
            window.setTimeout(function() {
                control.focus();
                control.reportValidity();
            }, 0);
        }

        function openProfile() {
            form.classList.add('is-profile-open');
            document.documentElement.classList.add('comment-profile-open');
            document.body.classList.add('comment-profile-open');
            toggle.setAttribute('aria-expanded', 'true');
            window.setTimeout(function() {
                if (authorInput && !authorInput.value.trim()) {
                    authorInput.focus();
                } else if (mailInput && !mailInput.value.trim()) {
                    mailInput.focus();
                }
            }, 0);
        }

        function closeProfile() {
            form.classList.remove('is-profile-open');
            document.documentElement.classList.remove('comment-profile-open');
            document.body.classList.remove('comment-profile-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.focus();
        }

        var storedProfile = readProfile();
        if (authorInput && !authorInput.value && storedProfile.author) authorInput.value = storedProfile.author;
        if (mailInput && !mailInput.value && storedProfile.mail) mailInput.value = storedProfile.mail;
        if (urlInput && !urlInput.value && storedProfile.url) urlInput.value = storedProfile.url;

        form.noValidate = true;
        form.classList.add('is-enhanced');
        toggle.setAttribute('aria-expanded', 'false');
        updateLabel();

        toggle.addEventListener('click', openProfile);

        closeButtons.forEach(function(button) {
            button.addEventListener('click', closeProfile);
        });

        if (saveButton) {
            saveButton.addEventListener('click', function() {
                var invalidProfileControl = getInvalidControl([authorInput, mailInput, urlInput]);
                if (invalidProfileControl) {
                    reportInvalid(invalidProfileControl);
                    return;
                }
                saveProfile();
                updateLabel();
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

            var invalidProfileControl = getInvalidControl([authorInput, mailInput, urlInput]);
            if (invalidProfileControl) {
                event.preventDefault();
                openProfile();
                reportInvalid(invalidProfileControl);
                return;
            }

            var invalidContentControl = getInvalidControl([textInput]);
            if (invalidContentControl) {
                event.preventDefault();
                reportInvalid(invalidContentControl);
                return;
            }

            saveProfile();
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && form.classList.contains('is-profile-open')) {
                closeProfile();
            }
        });
    });
}

function initArticleImages() {
    var images = document.querySelectorAll('.article-body img');
    if (!images.length) return;

    var lightboxMedia = window.matchMedia ? window.matchMedia('(min-width: 769px)') : null;
    var activeTrigger = null;
    var lightbox = document.querySelector('.qiwi-lightbox');

    if (!lightbox) {
        lightbox = document.createElement('div');
        lightbox.className = 'qiwi-lightbox';
        lightbox.setAttribute('role', 'dialog');
        lightbox.setAttribute('aria-modal', 'true');
        lightbox.setAttribute('aria-label', '图片预览');
        lightbox.hidden = true;

        var closeButton = document.createElement('button');
        closeButton.className = 'qiwi-lightbox-button';
        closeButton.type = 'button';
        closeButton.setAttribute('aria-label', '关闭图片预览');

        var previewImage = document.createElement('img');
        previewImage.className = 'qiwi-lightbox-image';
        previewImage.alt = '';

        closeButton.appendChild(previewImage);
        lightbox.appendChild(closeButton);
        document.body.appendChild(lightbox);
    }

    var lightboxButton = lightbox.querySelector('.qiwi-lightbox-button');
    var lightboxImage = lightbox.querySelector('.qiwi-lightbox-image');
    if (!lightboxButton || !lightboxImage) return;

    var closeTimer = null;
    var targetRect = null;

    function getImageTargetRect(img) {
        var padding = Math.min(96, Math.max(40, Math.min(window.innerWidth, window.innerHeight) * 0.08));
        var maxWidth = Math.max(120, window.innerWidth - padding * 2);
        var maxHeight = Math.max(120, window.innerHeight - padding * 2);
        var naturalWidth = img.naturalWidth || img.width || img.getBoundingClientRect().width;
        var naturalHeight = img.naturalHeight || img.height || img.getBoundingClientRect().height;
        var scale = Math.min(maxWidth / naturalWidth, maxHeight / naturalHeight, 1);
        var width = naturalWidth * scale;
        var height = naturalHeight * scale;

        return {
            left: (window.innerWidth - width) / 2,
            top: (window.innerHeight - height) / 2,
            width: width,
            height: height
        };
    }

    function getTransform(fromRect, toRect) {
        return 'translate3d(' + fromRect.left + 'px, ' + fromRect.top + 'px, 0) scale(' +
            (fromRect.width / toRect.width) + ', ' + (fromRect.height / toRect.height) + ')';
    }

    function getTargetTransform(rect) {
        return 'translate3d(' + rect.left + 'px, ' + rect.top + 'px, 0) scale(1, 1)';
    }

    function preventPageScroll(event) {
        if (!lightbox.hidden) {
            event.preventDefault();
        }
    }

    function lockPageScroll() {
        document.documentElement.classList.add('qiwi-lightbox-open');
        document.body.classList.add('qiwi-lightbox-open');
        document.addEventListener('wheel', preventPageScroll, { passive: false });
        document.addEventListener('touchmove', preventPageScroll, { passive: false });
    }

    function unlockPageScroll() {
        document.documentElement.classList.remove('qiwi-lightbox-open');
        document.body.classList.remove('qiwi-lightbox-open');
        document.removeEventListener('wheel', preventPageScroll);
        document.removeEventListener('touchmove', preventPageScroll);
    }

    function closeLightbox() {
        if (lightbox.hidden) return;

        var sourceRect = activeTrigger ? activeTrigger.getBoundingClientRect() : targetRect;
        if (sourceRect && targetRect) {
            lightboxImage.style.transform = getTransform(sourceRect, targetRect);
        }

        lightbox.classList.remove('is-open');

        if (closeTimer) {
            window.clearTimeout(closeTimer);
        }

        closeTimer = window.setTimeout(function() {
            lightbox.hidden = true;
            lightboxImage.removeAttribute('src');
            lightboxImage.removeAttribute('style');
            unlockPageScroll();
            closeTimer = null;
        }, 300);

        if (activeTrigger && typeof activeTrigger.focus === 'function') {
            activeTrigger.focus();
        }

        activeTrigger = null;
    }

    function openLightbox(img) {
        if (lightboxMedia && !lightboxMedia.matches) {
            return;
        }

        activeTrigger = img;
        var sourceRect = img.getBoundingClientRect();
        targetRect = getImageTargetRect(img);

        if (closeTimer) {
            window.clearTimeout(closeTimer);
            closeTimer = null;
        }

        lightboxImage.src = img.currentSrc || img.src;
        lightboxImage.alt = img.getAttribute('alt') || '';
        lightbox.hidden = false;
        lockPageScroll();
        lightboxImage.style.width = targetRect.width + 'px';
        lightboxImage.style.height = targetRect.height + 'px';
        lightboxImage.style.transition = 'none';
        lightboxImage.style.transform = getTransform(sourceRect, targetRect);
        lightboxImage.offsetHeight;
        lightboxImage.style.transition = '';
        window.requestAnimationFrame(function() {
            lightbox.classList.add('is-open');
            lightboxImage.style.transform = getTargetTransform(targetRect);
        });
        lightboxButton.focus();
    }

    lightboxButton.addEventListener('click', closeLightbox);

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && !lightbox.hidden) {
            closeLightbox();
        } else if (!lightbox.hidden && [' ', 'PageDown', 'PageUp', 'Home', 'End', 'ArrowDown', 'ArrowUp'].indexOf(event.key) !== -1) {
            event.preventDefault();
        }
    });

    images.forEach(function(img) {
        var parent = img.parentElement;
        var imageTarget = parent && parent.tagName === 'A' && parent.children.length === 1 ? parent : img;

        img.classList.add('qiwi-content-image');

        if (imageTarget !== img) {
            imageTarget.classList.add('qiwi-image-link');
        }

        img.setAttribute('tabindex', '0');
        img.setAttribute('role', 'button');
        img.setAttribute('aria-label', '打开图片预览');

        img.addEventListener('click', function(event) {
            if (lightboxMedia && !lightboxMedia.matches) {
                return;
            }

            event.preventDefault();
            openLightbox(img);
        });

        img.addEventListener('keydown', function(event) {
            if (lightboxMedia && !lightboxMedia.matches) {
                return;
            }

            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openLightbox(img);
            }
        });
    });
}

function initQiwiFolds() {
    if (!('animate' in Element.prototype)) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    document.querySelectorAll('.qiwi-fold').forEach(function(fold) {
        var summary = fold.querySelector(':scope > summary');
        var body = fold.querySelector(':scope > .qiwi-fold-body');
        if (!summary || !body || fold.dataset.qiwiFoldEnhanced === '1') return;

        fold.dataset.qiwiFoldEnhanced = '1';

        summary.addEventListener('click', function(event) {
            if (fold.classList.contains('is-animating')) {
                event.preventDefault();
                return;
            }

            event.preventDefault();
            var isOpen = fold.hasAttribute('open');
            var startHeight = body.offsetHeight;
            var endHeight;

            fold.classList.add('is-animating');
            body.style.overflow = 'hidden';

            if (isOpen) {
                endHeight = 0;
            } else {
                fold.setAttribute('open', '');
                body.style.height = 'auto';
                endHeight = body.scrollHeight;
                body.style.height = '0px';
                body.style.opacity = '0';
            }

            var animation = body.animate([
                {
                    height: startHeight + 'px',
                    opacity: isOpen ? 1 : 0,
                    transform: isOpen ? 'translateY(0) scaleY(1)' : 'translateY(-4px) scaleY(0.98)'
                },
                {
                    height: endHeight + 'px',
                    opacity: isOpen ? 0 : 1,
                    transform: isOpen ? 'translateY(-4px) scaleY(0.98)' : 'translateY(0) scaleY(1)'
                }
            ], {
                duration: 320,
                easing: 'cubic-bezier(0.22, 1, 0.36, 1)'
            });

            animation.onfinish = function() {
                if (isOpen) {
                    fold.removeAttribute('open');
                }
                fold.classList.remove('is-animating');
                body.style.removeProperty('height');
                body.style.removeProperty('opacity');
                body.style.removeProperty('overflow');
            };

            animation.oncancel = animation.onfinish;
        });
    });
}

// 整卡点击跳转
document.addEventListener('DOMContentLoaded', function() {
    initMobileNavigation();
    initArticleToc();
    initHomeHero();
    initHomeJike();
    initCommentProfile();
    initArticleImages();
    initQiwiFolds();
    updateThemeToggleButtons(document.documentElement.getAttribute('data-theme'));

    document.querySelectorAll('.article-item').forEach(item => {
        item.addEventListener('click', function(e) {
            if (e.target.closest('.tag') || e.target.closest('.meta-category a') || e.target.closest('.article-title-link')) {
                return;
            }
            const titleLink = this.querySelector('.article-title-link');
            if (titleLink) {
                window.location.href = titleLink.href;
            }
        });
    });
});
</script>

<!-- LaTeX 渲染 -->
<?php if (function_exists('qiwiShouldRenderLatex') && qiwiShouldRenderLatex($this)): ?>
<script type="text/javascript">
  document.addEventListener("DOMContentLoaded", function() {
    if (typeof renderMathInElement !== 'function') return;
    renderMathInElement(document.body, {
      delimiters: [{
          left: "$$",
          right: "$$",
          display: true
      }, {
          left: "$",
          right: "$",
          display: false
      }, {
          left: "\\(",
          right: "\\)",
          display: false
      }, {
          left: "\\[",
          right: "\\]",
          display: true
      }],
      throwOnError: false,
      strict: "ignore",
      ignoredTags: ["script", "noscript", "style", "textarea", "pre", "code"],
      ignoredClasses: ["nokatex"]
    });
  });
</script>
<?php endif; ?>

<!-- 一言打字机效果 -->
<?php if ($this->options->enableHitokoto == 1): ?>
<script src="<?php $this->options->themeUrl('assets/js/hitokoto.js'); ?>"></script>
<?php endif; ?>

<!-- Code highlighting with theme support -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/styles/github.min.css" id="hljs-light-theme">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/styles/github-dark.min.css" id="hljs-dark-theme" disabled>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/highlight.min.js"></script>
<script>
// 代码高亮和复制功能初始化
document.addEventListener('DOMContentLoaded', function() {
    // 初始化代码高亮
    hljs.highlightAll();

    // 为所有代码块添加包装器和复制功能
    const codeBlocks = document.querySelectorAll('pre code');

    codeBlocks.forEach(function(codeBlock) {
        // 获取代码语言
        const language = codeBlock.className.replace('hljs language-', '').replace('hljs', '') || 'text';

        // 创建代码块包装器
        const wrapper = document.createElement('div');
        wrapper.className = 'code-block-wrapper';

        // 创建代码块头部
        const header = document.createElement('div');
        header.className = 'code-block-header';

        // 添加语言标签
        const languageLabel = document.createElement('span');
        languageLabel.className = 'code-language';
        languageLabel.textContent = language;

        // 创建复制按钮
        const copyButton = document.createElement('button');
        copyButton.className = 'copy-button';
        copyButton.innerHTML = `
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
            </svg>
            <span class="copy-text">复制</span>
        `;

        // 组装头部
        header.appendChild(languageLabel);
        header.appendChild(copyButton);

        // 将原始的pre元素包装起来
        const preElement = codeBlock.parentElement;
        preElement.parentNode.insertBefore(wrapper, preElement);
        wrapper.appendChild(header);
        wrapper.appendChild(preElement);

        // 添加复制功能
        copyButton.addEventListener('click', function() {
            const code = codeBlock.textContent;

            // 使用现代的Clipboard API
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(code).then(function() {
                    showCopySuccess(copyButton);
                }).catch(function() {
                    // 回退到传统方法
                    fallbackCopyTextToClipboard(code, copyButton);
                });
            } else {
                // 回退到传统方法
                fallbackCopyTextToClipboard(code, copyButton);
            }
        });
    });

    // 复制成功的反馈
    function showCopySuccess(button) {
        const originalHTML = button.innerHTML;
        button.classList.add('copied');
        button.innerHTML = `
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20,6 9,17 4,12"></polyline>
            </svg>
            <span class="copy-text">已复制</span>
        `;

        // 2秒后恢复原始状态
        setTimeout(function() {
            button.classList.remove('copied');
            button.innerHTML = originalHTML;
        }, 2000);
    }

    // 传统的复制方法（回退方案）
    function fallbackCopyTextToClipboard(text, button) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            document.execCommand('copy');
            showCopySuccess(button);
        } catch (err) {
            console.error('复制失败:', err);
        }

        document.body.removeChild(textArea);
    }
});
</script>

<!-- Custom JavaScript -->
<?php if ($this->options->customJS): ?>
<script type="text/javascript">
    <?php echo $this->options->customJS; ?>
</script>
<?php endif; ?>

<!-- Tracking Code -->
<?php if ($this->options->trackingCode): ?>
<?php echo $this->options->trackingCode; ?>
<?php endif; ?>

<?php $this->footer(); ?>
</body>
</html>
