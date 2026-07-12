<?php
ob_start();
$this->need('version.php');
$versionDrawerOutput = ob_get_clean();
$themeVersion = '';
$v2FooterMotto = trim((string) qiwiGetOptionValue($this, 'v2FooterMotto', '向内求索，向外生长'));

if (preg_match('/^\s*([0-9][^<\s]*)/', $versionDrawerOutput, $matches)) {
    $themeVersion = $matches[1];
    $versionDrawerOutput = preg_replace('/^\s*' . preg_quote($themeVersion, '/') . '\s*/', '', $versionDrawerOutput, 1);
}
?>

<!-- 页脚 -->
<footer class="site-footer">
    <div class="foot-freq" aria-hidden="true"></div>
    <div class="footer-content">
        <div class="footer-info">
            <?php if ($v2FooterMotto !== ''): ?><p class="footer-motto"><?php echo htmlspecialchars($v2FooterMotto, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            <?php if ($this->options->footerInfo): ?>
                <p class="site-description"><?php $this->options->footerInfo() ?></p>
            <?php endif; ?>
            <p>&copy; <?php echo date('Y'); ?> <a href="<?php $this->options->siteUrl(); ?>"><?php $this->options->title(); ?></a> · Typecho</p>
        </div>

        <div class="footer-meta">
            <div class="footer-links">
                <?php if ($this->options->enableTravellings == 1): ?><a href="https://www.travellings.cn/go.html" target="_blank" rel="noopener noreferrer">开往</a><?php endif; ?>
                <a href="<?php $this->options->feedUrl(); ?>" target="_blank" rel="noopener" title="RSS 订阅">
                    RSS
                </a>
                <?php if ($this->user->hasLogin()): ?>
                <a href="<?php $this->options->adminUrl(); ?>" target="_blank" rel="noopener" title="管理后台">
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

</main>
</div>
<div class="sr-only" id="qiwi-pjax-status" aria-live="polite" aria-atomic="true"></div>
<?php echo $versionDrawerOutput; ?>
<?php if (function_exists('qiwiBusuanziScriptEnabled') && qiwiBusuanziScriptEnabled($this)): ?>
<script defer src="//cdn.busuanzi.cc/busuanzi/3.6.9/busuanzi.min.js"></script>
<?php endif; ?>

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
        // V2 默认使用深色纸面
        htmlElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('theme-preference', 'dark');
    }

    updateThemeToggleButtons(htmlElement.getAttribute('data-theme'));
})();

function toggleTheme(event) {
    const htmlElement = document.documentElement;
    const currentTheme = htmlElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const source = event && event.currentTarget instanceof Element ? event.currentTarget : null;
    const rect = source ? source.getBoundingClientRect() : null;
    const x = rect ? rect.left + rect.width / 2 : window.innerWidth / 2;
    const y = rect ? rect.top + rect.height / 2 : window.innerHeight / 2;
    const radius = Math.hypot(Math.max(x, window.innerWidth - x), Math.max(y, window.innerHeight - y));

    function applyTheme() {
        htmlElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme-preference', newTheme);
        updateThemeToggleButtons(newTheme);
    }

    if (reduceMotion || typeof document.startViewTransition !== 'function') {
        applyTheme();
        return;
    }

    const transition = document.startViewTransition(applyTheme);
    transition.ready.then(function() {
        htmlElement.animate({
            clipPath: [
                'circle(0 at ' + x + 'px ' + y + 'px)',
                'circle(' + radius + 'px at ' + x + 'px ' + y + 'px)'
            ]
        }, {
            duration: 560,
            easing: 'cubic-bezier(.22,.61,.36,1)',
            pseudoElement: '::view-transition-new(root)'
        });
    }).catch(function() {});
}

function initMobileNavigation() {
    const navToggle = document.querySelector('.nav-toggle');
    const navMenu = document.getElementById('site-navigation-menu');
    const submenuToggles = document.querySelectorAll('.nav-submenu-toggle');
    const mobileNavMedia = window.matchMedia ? window.matchMedia('(max-width: 768px)') : null;

    if (!navToggle || !navMenu) {
        return;
    }

    document.body.classList.add('nav-js-enabled');

    const isMobileNav = function() {
        return !mobileNavMedia || mobileNavMedia.matches;
    };

    const preventBackgroundScroll = function(event) {
        if (!navMenu.classList.contains('is-open')) {
            return;
        }

        if (navMenu.contains(event.target)) {
            return;
        }

        event.preventDefault();
    };

    const lockPageScroll = function() {
        if (!isMobileNav() || document.body.dataset.qiwiNavScrollLocked === '1') {
            return;
        }

        document.body.dataset.qiwiNavScrollLocked = '1';
        document.documentElement.classList.add('nav-open');
        document.addEventListener('wheel', preventBackgroundScroll, { passive: false });
        document.addEventListener('touchmove', preventBackgroundScroll, { passive: false });
    };

    const unlockPageScroll = function() {
        if (document.body.dataset.qiwiNavScrollLocked !== '1') {
            document.documentElement.classList.remove('nav-open');
            return;
        }

        delete document.body.dataset.qiwiNavScrollLocked;
        document.documentElement.classList.remove('nav-open');
        document.removeEventListener('wheel', preventBackgroundScroll);
        document.removeEventListener('touchmove', preventBackgroundScroll);
    };

    const setFocusableTree = function(root, isFocusable) {
        if (!root) return;

        root.querySelectorAll('a, button').forEach(function(control) {
            if (isFocusable) {
                if (control.dataset.qiwiPreviousTabindex) {
                    control.setAttribute('tabindex', control.dataset.qiwiPreviousTabindex);
                    delete control.dataset.qiwiPreviousTabindex;
                } else if (control.dataset.qiwiManagedTabindex === '1') {
                    control.removeAttribute('tabindex');
                    delete control.dataset.qiwiManagedTabindex;
                }
                return;
            }

            if (!control.dataset.qiwiManagedTabindex) {
                if (control.hasAttribute('tabindex')) {
                    control.dataset.qiwiPreviousTabindex = control.getAttribute('tabindex');
                }
                control.dataset.qiwiManagedTabindex = '1';
            }
            control.setAttribute('tabindex', '-1');
        });
    };

    const setTreeHidden = function(root, isHidden) {
        if (!root) return;

        if (isHidden) {
            root.setAttribute('aria-hidden', 'true');
            if ('inert' in root) {
                root.inert = true;
            }
            return;
        }

        root.removeAttribute('aria-hidden');
        if ('inert' in root) {
            root.inert = false;
        }
    };

    const updateMenuAccessibility = function(isOpen) {
        if (!isMobileNav()) {
            setTreeHidden(navMenu, false);
            setFocusableTree(navMenu, true);
            return;
        }

        setTreeHidden(navMenu, !isOpen);
        setFocusableTree(navMenu, isOpen);
    };

    const setSubmenuState = function(toggle, isOpen) {
        const navItem = toggle.closest('.nav-item-has-children');
        if (!navItem) return;

        const submenu = navItem.querySelector('.nav-submenu');
        navItem.classList.toggle('is-submenu-open', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

        if (!isMobileNav()) {
            setTreeHidden(submenu, false);
            setFocusableTree(submenu, true);
            return;
        }

        setTreeHidden(submenu, !isOpen);
        setFocusableTree(submenu, isOpen);
    };

    const setMenuState = function(isOpen) {
        isOpen = Boolean(isOpen && isMobileNav());
        navMenu.classList.toggle('is-open', isOpen);
        document.body.classList.toggle('nav-open', isOpen);
        navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (isOpen) {
            lockPageScroll();
        } else {
            unlockPageScroll();
        }
        updateMenuAccessibility(isOpen);

        submenuToggles.forEach(function(toggle) {
            const navItem = toggle.closest('.nav-item-has-children');
            const hasCurrentChild = navItem && navItem.querySelector('.nav-submenu a.current');

            setSubmenuState(toggle, Boolean(isOpen && hasCurrentChild));
        });

    };

    setMenuState(false);

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
        } else {
            updateMenuAccessibility(navMenu.classList.contains('is-open'));
        }
    });
}

function initArticleToc() {
    if (document.body && document.body.classList.contains('qiwi-v2')) return;
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
    if (document.body && document.body.classList.contains('qiwi-v2')) return;
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

function initCommentTargetHighlight() {
    var highlightedClass = 'is-highlighted';

    function highlightComment(target) {
        if (!target) return;

        target.classList.remove(highlightedClass);
        target.offsetHeight;
        target.classList.add(highlightedClass);
    }

    document.querySelectorAll('.comment-reply-target[href^="#"]').forEach(function(link) {
        link.addEventListener('click', function(event) {
            var hash = link.getAttribute('href');
            if (!hash || hash === '#') return;

            var target = document.getElementById(hash.slice(1));
            if (!target) return;

            event.preventDefault();
            if (history.pushState) {
                history.pushState(null, '', hash);
            } else {
                window.location.hash = hash;
            }

            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            window.setTimeout(function() {
                highlightComment(target);
            }, 260);
        });
    });

    document.querySelectorAll('.comment-item').forEach(function(item) {
        item.addEventListener('animationend', function(event) {
            if (event.animationName === 'qiwiCommentHighlight') {
                item.classList.remove(highlightedClass);
            }
        });
    });
}

function initArticleImages() {
    if (document.body && document.body.classList.contains('qiwi-v2')) return;
    var images = document.querySelectorAll('.article-body img, .article-hero, .comment-item.is-trusted-comment .comment-text img, .moment-comment.is-trusted-comment .moment-comment-text img');
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

function initArticleCardThumbnails() {
    document.querySelectorAll('.article-thumbnail-wrapper .article-thumbnail').forEach(function(img) {
        var wrapper = img.closest('.article-thumbnail-wrapper');
        if (!wrapper) return;

        function updateOrientationClass() {
            if (!img.naturalWidth || !img.naturalHeight) return;
            wrapper.classList.toggle('is-portrait-thumbnail', img.naturalHeight > img.naturalWidth);
        }

        if (img.complete) {
            updateOrientationClass();
        } else {
            img.addEventListener('load', updateOrientationClass, { once: true });
        }
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

function initQiwiExternalLinks() {
    var siteUrl = <?php echo json_encode(rtrim((string) $this->options->siteUrl, '/') . '/', JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var configuredRecordEndpoint = <?php echo json_encode(function_exists('qiwiGetThemeActionEndpoint') ? qiwiGetThemeActionEndpoint('external-link', $this->options) : '', JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var siteHost = '';
    var recordEndpoint = String(configuredRecordEndpoint || '');

    try {
        siteHost = normalizeHost(new URL(siteUrl, window.location.href).hostname);
    } catch (error) {
        siteHost = normalizeHost(window.location.hostname);
    }

    function normalizeHost(host) {
        return String(host || '').toLowerCase().replace(/^www\./, '');
    }

    function isSkippableTextParent(element) {
        return !element || Boolean(element.closest('a, code, pre, script, style, textarea, input, button, kbd, samp'));
    }

    function isExternalHttpUrl(url) {
        try {
            var parsed = new URL(url, window.location.href);
            if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') return false;
            return normalizeHost(parsed.hostname) !== siteHost;
        } catch (error) {
            return false;
        }
    }

    function displayDomain(url) {
        try {
            var host = normalizeHost(new URL(url, window.location.href).hostname);
            var parts = host.split('.').filter(Boolean);
            if (parts.length >= 3 && parts[parts.length - 1].length === 2 && parts[parts.length - 2].length <= 3) {
                return parts.slice(-3).join('.');
            }
            if (parts.length >= 2) {
                return parts.slice(-2).join('.');
            }
            return host;
        } catch (error) {
            return url.replace(/^https?:\/\//i, '').replace(/^www\./i, '').split('/')[0];
        }
    }

    function enhanceAnchor(anchor) {
        if (!anchor || anchor.dataset.qiwiExternalEnhanced === '1') return;
        var original = anchor.dataset.qiwiExternalUrl || anchor.getAttribute('href') || '';
        try {
            original = new URL(original, window.location.href).toString();
        } catch (error) {
            return;
        }

        if (!isExternalHttpUrl(original)) return;

        anchor.dataset.qiwiExternalEnhanced = '1';
        anchor.dataset.qiwiExternalUrl = original;
        anchor.classList.add('qiwi-external-link');
        anchor.setAttribute('href', original);
        anchor.setAttribute('title', original);
        anchor.setAttribute('target', '_blank');
        anchor.setAttribute('rel', 'noopener noreferrer');
        if (recordEndpoint) {
            anchor.addEventListener('click', function() {
                recordExternalClick(original);
            });
        }
    }

    function recordExternalClick(url) {
        if (!recordEndpoint || !url) return;

        var payload = new URLSearchParams();
        payload.set('url', url);
        payload.set('source', window.location.pathname + window.location.search);

        try {
            if (navigator.sendBeacon && navigator.sendBeacon(recordEndpoint, payload)) {
                return;
            }
        } catch (error) {}

        try {
            fetch(recordEndpoint, {
                method: 'POST',
                body: payload,
                keepalive: true,
                credentials: 'same-origin'
            }).catch(function() {});
        } catch (error) {}
    }

    function autolinkTextNode(node) {
        if (!node || !node.nodeValue || isSkippableTextParent(node.parentElement)) return;
        var text = node.nodeValue;
        var pattern = /((?:https?:\/\/|www\.)[a-z0-9][a-z0-9.-]*(?::\d+)?(?:\/[^\s<>"'`，。！？；：、（）【】《》「」『』\u3000]*)?|(?:[a-z0-9-]+\.)+[a-z]{2,}(?:\/[^\s<>"'`，。！？；：、（）【】《》「」『』\u3000]*)?)/ig;
        var fragment = document.createDocumentFragment();
        var lastIndex = 0;
        var match;
        var changed = false;

        while ((match = pattern.exec(text)) !== null) {
            var raw = match[0];
            var start = match.index;
            var trailing = '';
            while (/[.,!?;:，。！？；：、）)\]]$/.test(raw)) {
                trailing = raw.slice(-1) + trailing;
                raw = raw.slice(0, -1);
            }

            var url = /^https?:\/\//i.test(raw) ? raw : 'https://' + raw;
            if (!raw || !isExternalHttpUrl(url)) continue;

            if (start > lastIndex) {
                fragment.appendChild(document.createTextNode(text.slice(lastIndex, start)));
            }

            var anchor = document.createElement('a');
            anchor.textContent = displayDomain(url);
            anchor.href = url;
            fragment.appendChild(anchor);
            enhanceAnchor(anchor);
            if (trailing) {
                fragment.appendChild(document.createTextNode(trailing));
            }

            lastIndex = match.index + match[0].length;
            changed = true;
        }

        if (!changed) return;
        if (lastIndex < text.length) {
            fragment.appendChild(document.createTextNode(text.slice(lastIndex)));
        }
        node.parentNode.replaceChild(fragment, node);
    }

    document.querySelectorAll('a[href]').forEach(enhanceAnchor);

    document.querySelectorAll('.article-body, .page-intro, .archive-description, .moment-text, .comment-item.is-trusted-comment .comment-text, .moment-comment.is-trusted-comment .moment-comment-text, .post-copyright-body, .about-bio, .friends-extra, .sidebar-announcement-body').forEach(function(scope) {
        var walker = document.createTreeWalker(scope, NodeFilter.SHOW_TEXT, {
            acceptNode: function(node) {
                return isSkippableTextParent(node.parentElement)
                    ? NodeFilter.FILTER_REJECT
                    : NodeFilter.FILTER_ACCEPT;
            }
        });
        var nodes = [];
        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }
        nodes.forEach(autolinkTextNode);
    });
}

function initQiwiLinkPreviews() {
    var endpoint = <?php echo json_encode(function_exists('qiwiGetThemeActionEndpoint') ? qiwiGetThemeActionEndpoint('link-preview', $this->options) : '', JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var siteUrl = <?php echo json_encode(rtrim((string) $this->options->siteUrl, '/') . '/', JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var siteHost = '';
    var hoverTimer = null;
    var activeLink = null;
    var lastEvent = null;
    var memoryCache = Object.create(null);
    var externalCacheKey = 'qiwi-link-preview-external-v1';
    var externalCache = {};

    try {
        siteHost = new URL(siteUrl, window.location.href).hostname.toLowerCase().replace(/^www\./, '');
    } catch (error) {
        siteHost = window.location.hostname.toLowerCase().replace(/^www\./, '');
    }

    try {
        externalCache = JSON.parse(localStorage.getItem(externalCacheKey) || '{}') || {};
    } catch (error) {
        externalCache = {};
    }

    function normalizeHost(host) {
        return String(host || '').toLowerCase().replace(/^www\./, '');
    }

    function parseUrl(url) {
        try {
            return new URL(url, window.location.href);
        } catch (error) {
            return null;
        }
    }

    function isInternalUrl(url) {
        var parsed = parseUrl(url);
        return parsed && (parsed.protocol === 'http:' || parsed.protocol === 'https:') && normalizeHost(parsed.hostname) === siteHost;
    }

    function isExternalUrl(url) {
        var parsed = parseUrl(url);
        return parsed && (parsed.protocol === 'http:' || parsed.protocol === 'https:') && normalizeHost(parsed.hostname) !== siteHost;
    }

    function displayHost(url) {
        var parsed = parseUrl(url);
        if (!parsed) return url;
        return normalizeHost(parsed.hostname);
    }

    function ensureTooltip() {
        var tooltip = document.querySelector('.qiwi-link-preview');
        if (tooltip) return tooltip;

        tooltip = document.createElement('div');
        tooltip.className = 'qiwi-link-preview';
        tooltip.setAttribute('role', 'tooltip');
        tooltip.innerHTML = '<div class="qiwi-link-preview-kind"></div><div class="qiwi-link-preview-title"></div><div class="qiwi-link-preview-summary"></div><div class="qiwi-link-preview-meta"></div>';
        document.body.appendChild(tooltip);
        return tooltip;
    }

    function positionTooltip(event) {
        var tooltip = ensureTooltip();
        var gap = 16;
        var rect = tooltip.getBoundingClientRect();
        var left = event.clientX + gap;
        var top = event.clientY + gap;
        var maxLeft = window.innerWidth - rect.width - gap;
        var maxTop = window.innerHeight - rect.height - gap;
        tooltip.style.left = Math.max(gap, Math.min(left, maxLeft)) + 'px';
        tooltip.style.top = Math.max(gap, Math.min(top, maxTop)) + 'px';
    }

    function hidePreview() {
        if (hoverTimer) {
            window.clearTimeout(hoverTimer);
            hoverTimer = null;
        }
        activeLink = null;
        var tooltip = document.querySelector('.qiwi-link-preview');
        if (tooltip) {
            tooltip.classList.remove('is-visible', 'is-external');
        }
    }

    function renderPreview(preview, external) {
        if (!preview || !activeLink || !lastEvent) return;
        var tooltip = ensureTooltip();
        tooltip.classList.toggle('is-external', Boolean(external));
        tooltip.querySelector('.qiwi-link-preview-kind').textContent = preview.kind || (external ? '外链' : '站内');
        tooltip.querySelector('.qiwi-link-preview-title').textContent = preview.title || activeLink.textContent.trim() || preview.url || '';
        tooltip.querySelector('.qiwi-link-preview-summary').textContent = preview.summary || '';
        tooltip.querySelector('.qiwi-link-preview-meta').textContent = preview.meta || displayHost(preview.url || activeLink.href);
        positionTooltip(lastEvent);
        tooltip.classList.add('is-visible');
    }

    function mapInternalPreview(payload) {
        var data = payload && payload.preview ? payload.preview : null;
        if (!data) return null;
        var labels = {
            post: '文章',
            page: '页面',
            quote: '引用',
            moment: '说说',
            comment: '评论'
        };
        var metas = [];
        if (data.date) metas.push(data.date);
        if (data.type !== 'comment' && data.meta && data.meta.author) metas.push(data.meta.author);
        if (data.meta && data.meta.source) metas.push(data.meta.source);
        if (Array.isArray(data.metas)) {
            data.metas.slice(0, 3).forEach(function(item) {
                if (item && item.name) metas.push(item.name);
            });
        }
        return {
            kind: labels[data.type] || '站内',
            title: data.title || '',
            summary: data.summary && String(data.summary).trim() !== '0' ? data.summary : '',
            meta: metas.join(' · '),
            url: data.url || ''
        };
    }

    function fetchInternalPreview(url) {
        if (!endpoint) return Promise.resolve(null);
        var key = 'internal:' + url;
        if (memoryCache[key]) return Promise.resolve(memoryCache[key]);

        var requestUrl = endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + 'url=' + encodeURIComponent(url);
        return fetch(requestUrl, { credentials: 'same-origin' })
            .then(function(response) {
                if (!response.ok) return null;
                return response.json();
            })
            .then(function(payload) {
                var preview = mapInternalPreview(payload);
                if (preview) {
                    memoryCache[key] = preview;
                }
                return preview;
            })
            .catch(function() {
                return null;
            });
    }

    function saveExternalCache() {
        try {
            localStorage.setItem(externalCacheKey, JSON.stringify(externalCache));
        } catch (error) {}
    }

    function fetchExternalPreview(url) {
        var key = 'external:' + url;
        var now = Date.now();
        var cached = externalCache[key];
        if (cached && cached.expires > now) {
            return Promise.resolve(cached.preview);
        }

        return fetch(url, { credentials: 'omit' })
            .then(function(response) {
                if (!response.ok) return null;
                return response.text();
            })
            .then(function(html) {
                if (!html) return null;
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var title = doc.querySelector('title');
                var desc = doc.querySelector('meta[name="description"], meta[property="og:description"]');
                var preview = {
                    kind: '外链',
                    title: title ? title.textContent.trim() : displayHost(url),
                    summary: desc ? desc.getAttribute('content') || '' : '',
                    meta: displayHost(url),
                    url: url
                };
                externalCache[key] = {
                    expires: now + 7 * 24 * 60 * 60 * 1000,
                    preview: preview
                };
                saveExternalCache();
                return preview;
            })
            .catch(function() {
                var preview = {
                    kind: '外链',
                    title: activeLink ? activeLink.textContent.trim() || displayHost(url) : displayHost(url),
                    summary: '',
                    meta: displayHost(url),
                    url: url
                };
                externalCache[key] = {
                    expires: now + 24 * 60 * 60 * 1000,
                    preview: preview
                };
                saveExternalCache();
                return preview;
            });
    }

    function bindLink(link) {
        if (!link || link.dataset.qiwiPreviewBound === '1') return;
        var href = link.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#' || /^mailto:|^tel:/i.test(href)) return;
        if (!isInternalUrl(href) && !isExternalUrl(href)) return;

        link.dataset.qiwiPreviewBound = '1';
        if (isExternalUrl(href)) {
            link.classList.add('qiwi-link-preview-external');
        }

        link.addEventListener('mouseenter', function(event) {
            activeLink = link;
            lastEvent = event;
            if (hoverTimer) {
                window.clearTimeout(hoverTimer);
            }
            hoverTimer = window.setTimeout(function() {
                var absoluteUrl = parseUrl(link.href).toString();
                var request = isInternalUrl(absoluteUrl) ? fetchInternalPreview(absoluteUrl) : fetchExternalPreview(absoluteUrl);
                request.then(function(preview) {
                    if (activeLink !== link || !preview) return;
                    renderPreview(preview, isExternalUrl(absoluteUrl));
                });
            }, 260);
        });

        link.addEventListener('mousemove', function(event) {
            lastEvent = event;
            var tooltip = document.querySelector('.qiwi-link-preview.is-visible');
            if (tooltip) {
                positionTooltip(event);
            }
        });

        link.addEventListener('mouseleave', hidePreview);
        link.addEventListener('focus', function(event) {
            activeLink = link;
            lastEvent = {
                clientX: link.getBoundingClientRect().left,
                clientY: link.getBoundingClientRect().bottom
            };
            var absoluteUrl = parseUrl(link.href).toString();
            var request = isInternalUrl(absoluteUrl) ? fetchInternalPreview(absoluteUrl) : fetchExternalPreview(absoluteUrl);
            request.then(function(preview) {
                if (activeLink !== link || !preview) return;
                renderPreview(preview, isExternalUrl(absoluteUrl));
            });
        });
        link.addEventListener('blur', hidePreview);
    }

    document.querySelectorAll('.article-body a[href], .moment-text a[href], .comment-item.is-trusted-comment .comment-text a[href], .moment-comment.is-trusted-comment .moment-comment-text a[href]').forEach(bindLink);
}

function initQiwiCopyLinks() {
    function buildUrl(value) {
        value = String(value || '').trim();
        if (value === '') return window.location.href;
        if (value.charAt(0) === '#') {
            return window.location.origin + window.location.pathname + window.location.search + value;
        }

        try {
            return new URL(value, window.location.href).toString();
        } catch (error) {
            return window.location.href;
        }
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }

        return new Promise(function(resolve, reject) {
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

    document.querySelectorAll('[data-qiwi-copy-link]').forEach(function(button) {
        if (button.dataset.qiwiCopyBound === '1') return;
        button.dataset.qiwiCopyBound = '1';

        button.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();

            var url = buildUrl(button.getAttribute('data-qiwi-copy-link'));
            copyText(url).then(function() {
                button.classList.add('is-copied');
                button.setAttribute('aria-label', '已复制链接');
                window.setTimeout(function() {
                    button.classList.remove('is-copied');
                    button.setAttribute('aria-label', button.classList.contains('moment-copy-link') ? '复制说说链接' : '复制评论链接');
                }, 1500);
            }).catch(function() {
                button.classList.add('is-copy-failed');
                window.setTimeout(function() {
                    button.classList.remove('is-copy-failed');
                }, 1500);
            });
        });
    });
}

function initQiwiLocalTimes() {
    var exactFormatter = null;
    try {
        exactFormatter = new Intl.DateTimeFormat(navigator.language || 'zh-CN', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        });
    } catch (error) {
        exactFormatter = null;
    }

    var timeZone = '';
    try {
        timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    } catch (error) {
        timeZone = '';
    }

    var pad = function(value) {
        return String(value).padStart(2, '0');
    };

    var formatDateTime = function(date, includeSeconds) {
        if (exactFormatter && includeSeconds) {
            return exactFormatter.format(date).replace(/\//g, '-');
        }

        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + ' '
            + pad(date.getHours()) + ':' + pad(date.getMinutes()) + (includeSeconds ? ':' + pad(date.getSeconds()) : '');
    };

    var startOfDay = function(date) {
        return new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
    };

    var formatRelativeTime = function(date, now) {
        var diffSeconds = Math.max(0, Math.floor((now.getTime() - date.getTime()) / 1000));

        if (diffSeconds < 60) {
            return diffSeconds <= 0 ? '刚刚' : diffSeconds + ' 秒前';
        }

        if (diffSeconds < 3600) {
            return Math.floor(diffSeconds / 60) + ' 分钟前';
        }

        if (diffSeconds < 86400) {
            return Math.floor(diffSeconds / 3600) + ' 小时前';
        }

        var dayDiff = Math.round((startOfDay(now) - startOfDay(date)) / 86400000);
        var time = pad(date.getHours()) + ':' + pad(date.getMinutes());

        if (dayDiff === 1) {
            return '昨天 ' + time;
        }

        if (dayDiff === 2) {
            return '前天 ' + time;
        }

        return formatDateTime(date, false);
    };

    var now = new Date();

    document.querySelectorAll('[data-qiwi-local-time][data-timestamp]').forEach(function(node) {
        var timestamp = parseInt(node.getAttribute('data-timestamp'), 10);
        if (!timestamp) return;

        var date = new Date(timestamp * 1000);
        if (Number.isNaN(date.getTime())) return;

        var exactLabel = formatDateTime(date, true);
        var title = timeZone ? exactLabel + ' ' + timeZone : exactLabel;
        node.textContent = formatRelativeTime(date, now);
        node.setAttribute('datetime', date.toISOString());
        node.setAttribute('title', title);
        node.setAttribute('aria-label', title);
    });
}

// 整卡点击跳转
document.addEventListener('DOMContentLoaded', function() {
    initMobileNavigation();
    initArticleToc();
    initHomeJike();
    initCommentProfile();
    initCommentTargetHighlight();
    initArticleImages();
    initArticleCardThumbnails();
    initQiwiFolds();
    initQiwiExternalLinks();
    initQiwiCopyLinks();
    initQiwiLocalTimes();
    updateThemeToggleButtons(document.documentElement.getAttribute('data-theme'));

    if (!document.body.classList.contains('qiwi-v2')) document.querySelectorAll('.article-item').forEach(item => {
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
<script src="<?php echo htmlspecialchars(qiwiGetMappedAssetUrl('assets/js/hitokoto.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>

<!-- Code highlighting; colors are owned by the active v2 stylesheet. -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/highlight.min.js"></script>

<script>
(function() {
    if (document.body && document.body.classList.contains('qiwi-v2')) return;
    var readingDefaults = { font: 'plain', spacing: 'wide', size: 'medium' };
    var readingLabels = {
        font: { readable: '易读', plain: '普通' },
        spacing: { wide: '宽', compact: '窄' },
        size: { large: '大', medium: '中', small: '小' }
    };
    var readingKey = 'qiwi-article-reading-v1';

    function readReadingPrefs() {
        try {
            var parsed = JSON.parse(localStorage.getItem(readingKey) || '{}');
            return {
                font: readingLabels.font[parsed.font] ? parsed.font : readingDefaults.font,
                spacing: readingLabels.spacing[parsed.spacing] ? parsed.spacing : readingDefaults.spacing,
                size: readingLabels.size[parsed.size] ? parsed.size : readingDefaults.size
            };
        } catch (error) {
            return Object.assign({}, readingDefaults);
        }
    }

    function saveReadingPrefs(prefs) {
        try {
            localStorage.setItem(readingKey, JSON.stringify(prefs));
        } catch (error) {}
    }

    function applyReadingPrefs(prefs) {
        document.documentElement.setAttribute('data-qiwi-reading-font', prefs.font);
        document.documentElement.setAttribute('data-qiwi-reading-spacing', prefs.spacing);
        document.documentElement.setAttribute('data-qiwi-reading-size', prefs.size);

        document.querySelectorAll('[data-reading-control]').forEach(function(control) {
            ['font', 'spacing', 'size'].forEach(function(group) {
                var label = control.querySelector('[data-reading-label="' + group + '"]');
                if (label) label.textContent = readingLabels[group][prefs[group]] || '';
                control.querySelectorAll('[data-reading-option="' + group + '"]').forEach(function(button) {
                    var active = button.getAttribute('data-reading-value') === prefs[group];
                    button.classList.toggle('is-active', active);
                    button.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
            });
        });
    }

    var readingPrefs = readReadingPrefs();
    applyReadingPrefs(readingPrefs);

    document.querySelectorAll('[data-reading-control]').forEach(function(control) {
        var trigger = control.querySelector('[data-reading-trigger]');
        if (trigger) {
            trigger.addEventListener('click', function(event) {
                event.stopPropagation();
                var open = !control.classList.contains('is-open');
                document.querySelectorAll('[data-reading-control].is-open').forEach(function(item) {
                    if (item !== control) {
                        item.classList.remove('is-open');
                        var itemTrigger = item.querySelector('[data-reading-trigger]');
                        if (itemTrigger) itemTrigger.setAttribute('aria-expanded', 'false');
                    }
                });
                control.classList.toggle('is-open', open);
                trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }

        control.querySelectorAll('[data-reading-option]').forEach(function(button) {
            button.addEventListener('click', function(event) {
                event.stopPropagation();
                var option = button.getAttribute('data-reading-option');
                var value = button.getAttribute('data-reading-value');
                if (!readingLabels[option] || !readingLabels[option][value]) return;
                readingPrefs[option] = value;
                saveReadingPrefs(readingPrefs);
                applyReadingPrefs(readingPrefs);
            });
        });
    });

    document.addEventListener('click', function() {
        document.querySelectorAll('[data-reading-control].is-open').forEach(function(control) {
            control.classList.remove('is-open');
            var trigger = control.querySelector('[data-reading-trigger]');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        });
    });

    document.addEventListener('keydown', function(event) {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('[data-reading-control].is-open').forEach(function(control) {
            control.classList.remove('is-open');
            var trigger = control.querySelector('[data-reading-trigger]');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        });
    });

    var confettiLoader = null;
    function loadConfetti() {
        if (window.confetti) return Promise.resolve(window.confetti);
        if (confettiLoader) return confettiLoader;
        confettiLoader = new Promise(function(resolve, reject) {
            var script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js';
            script.async = true;
            script.onload = function() { resolve(window.confetti); };
            script.onerror = reject;
            document.head.appendChild(script);
        });
        return confettiLoader;
    }

    function likeConfettiOrigin(target) {
        var width = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
        var height = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);
        var origin = {
            x: width / 2,
            y: height * 0.72
        };

        if (target && typeof target.getBoundingClientRect === 'function') {
            var rect = target.getBoundingClientRect();
            if (rect.width || rect.height) {
                origin.x = rect.left + rect.width / 2;
                origin.y = rect.top + rect.height / 2;
            }
        }

        return {
            x: width > 0 ? Math.min(1, Math.max(0, origin.x / width)) : 0.5,
            y: height > 0 ? Math.min(1, Math.max(0, origin.y / height)) : 0.72,
            px: origin.x,
            py: origin.y
        };
    }

    function fallbackConfetti(target) {
        var origin = likeConfettiOrigin(target);
        var layer = document.createElement('div');
        layer.className = 'qiwi-confetti-fallback';
        var colors = ['#d95f76', '#f0b35d', '#5fbf8f', '#58a6d6', '#9b7bd9'];
        for (var i = 0; i < 30; i += 1) {
            var piece = document.createElement('span');
            piece.className = 'qiwi-confetti-piece';
            piece.style.left = origin.px.toFixed(0) + 'px';
            piece.style.top = origin.py.toFixed(0) + 'px';
            piece.style.setProperty('--qiwi-confetti-color', colors[i % colors.length]);
            piece.style.setProperty('--qiwi-confetti-x', (Math.random() * 260 - 130).toFixed(0) + 'px');
            piece.style.setProperty('--qiwi-confetti-y', (Math.random() * 220 - 170).toFixed(0) + 'px');
            piece.style.setProperty('--qiwi-confetti-rotate', (Math.random() * 520 - 260).toFixed(0) + 'deg');
            piece.style.animationDelay = (Math.random() * 80).toFixed(0) + 'ms';
            layer.appendChild(piece);
        }
        document.body.appendChild(layer);
        setTimeout(function() {
            layer.remove();
        }, 1000);
    }

    function celebrateLike(target) {
        var origin = likeConfettiOrigin(target);
        loadConfetti().then(function(confetti) {
            if (!confetti) {
                fallbackConfetti(target);
                return;
            }
            confetti({ particleCount: 72, spread: 68, origin: { x: origin.x, y: origin.y }, scalar: 0.9 });
        }).catch(function() {
            fallbackConfetti(target);
        });
    }
})();
</script>
<?php $qiwiV2ScriptVersion = @filemtime(__DIR__ . '/assets/js/v2.js'); ?>
<script src="<?php echo htmlspecialchars(qiwiGetMappedAssetUrl('assets/js/v2.js' . ($qiwiV2ScriptVersion ? '?v=' . $qiwiV2ScriptVersion : '')), ENT_QUOTES, 'UTF-8'); ?>"></script>
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
