/**
 * QIWI THEME - ENHANCED FEATURES SCRIPT
 * 浮动导航栏 + 背景图片 + 交互增强
 */

(function() {
    'use strict';

    // 全局变量
    let floatingNav = null;
    let lastScrollY = 0;
    let scrollTimer = null;

    // 初始化所有功能
    function init() {
        initFloatingNav();
        initBackgroundImages();
        initScrollEffects();
        initSearchEnhancements();
    }

    /**
     * 初始化浮动导航栏
     */
    function initFloatingNav() {
        // 创建浮动导航栏
        createFloatingNav();
        
        // 监听滚动事件
        window.addEventListener('scroll', handleScroll, { passive: true });
        
        // 监听窗口大小变化
        window.addEventListener('resize', debounce(handleResize, 250));
    }

    /**
     * 创建浮动导航栏DOM结构
     */
    function createFloatingNav() {
        const header = document.querySelector('.site-header');
        if (!header) return;

        // 获取原始导航数据
        const siteBrand = document.querySelector('.site-title, .site-logo');
        const navLinks = document.querySelectorAll('.site-nav a:not(.search-button)');
        const searchForm = document.querySelector('.search-form');

        // 创建浮动导航栏
        floatingNav = document.createElement('nav');
        floatingNav.className = 'floating-nav';
        floatingNav.setAttribute('aria-label', '浮动导航栏');

        const navContent = document.createElement('div');
        navContent.className = 'nav-content';

        // 品牌/标题
        const navBrand = document.createElement('a');
        navBrand.href = siteBrand ? siteBrand.href || '/' : '/';
        navBrand.className = 'nav-brand';
        navBrand.textContent = siteBrand ? siteBrand.textContent.trim() : '首页';

        // 导航链接
        const navLinksContainer = document.createElement('div');
        navLinksContainer.className = 'nav-links';

        navLinks.forEach(link => {
            const navLink = document.createElement('a');
            navLink.href = link.href;
            navLink.textContent = link.textContent.trim();
            navLink.className = link.classList.contains('current') ? 'current' : '';
            navLinksContainer.appendChild(navLink);
        });

        // 搜索框
        let navSearch = null;
        if (searchForm) {
            navSearch = document.createElement('div');
            navSearch.className = 'nav-search';
            
            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.name = 's';
            searchInput.placeholder = '搜索...';
            searchInput.className = 'search-input';

            const searchButton = document.createElement('button');
            searchButton.type = 'submit';
            searchButton.innerHTML = `
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
            `;

            const form = document.createElement('form');
            form.method = 'post';
            form.action = searchForm.querySelector('form').action;
            form.appendChild(searchInput);
            form.appendChild(searchButton);

            navSearch.appendChild(form);
        }

        // 组装导航栏
        navContent.appendChild(navBrand);
        navContent.appendChild(navLinksContainer);
        if (navSearch) {
            navContent.appendChild(navSearch);
        }

        floatingNav.appendChild(navContent);
        document.body.appendChild(floatingNav);
    }

    /**
     * 处理滚动事件
     */
    function handleScroll() {
        if (!floatingNav) return;

        clearTimeout(scrollTimer);
        scrollTimer = setTimeout(() => {
            const currentScrollY = window.pageYOffset;
            const headerHeight = document.querySelector('.site-header')?.offsetHeight || 100;

            // 判断是否显示浮动导航栏
            if (currentScrollY > headerHeight + 50) {
                if (currentScrollY < lastScrollY) {
                    // 向上滚动时显示
                    showFloatingNav();
                } else {
                    // 向下滚动时隐藏
                    hideFloatingNav();
                }
            } else {
                // 接近顶部时隐藏
                hideFloatingNav();
            }

            lastScrollY = currentScrollY;
        }, 10);
    }

    /**
     * 显示浮动导航栏
     */
    function showFloatingNav() {
        if (floatingNav && !floatingNav.classList.contains('show')) {
            floatingNav.classList.add('show');
        }
    }

    /**
     * 隐藏浮动导航栏
     */
    function hideFloatingNav() {
        if (floatingNav && floatingNav.classList.contains('show')) {
            floatingNav.classList.remove('show');
        }
    }

    /**
     * 初始化背景图片功能
     */
    function initBackgroundImages() {
        // 从PHP传递的数据中获取背景图片配置
        const bgConfig = window.qiwiThemeConfig || {};
        const backgroundImages = bgConfig.backgroundImages || [];
        const maskOpacity = bgConfig.backgroundMask || '0.5';

        if (backgroundImages.length === 0) return;

        // 设置CSS变量
        document.documentElement.style.setProperty('--bg-mask-opacity', maskOpacity);

        // 随机选择一张背景图片
        const randomImage = backgroundImages[Math.floor(Math.random() * backgroundImages.length)];
        
        if (randomImage && randomImage.trim()) {
            setBackgroundImage(randomImage.trim());
        }
    }

    /**
     * 设置背景图片
     */
    function setBackgroundImage(imageUrl) {
        // 预加载图片
        const img = new Image();
        img.onload = function() {
            // 创建背景层
            const bgElement = document.createElement('div');
            bgElement.className = 'global-background';
            bgElement.style.backgroundImage = `url(${imageUrl})`;
            
            // 添加到body
            document.body.appendChild(bgElement);
            document.body.classList.add('has-background');

            // 淡入效果
            setTimeout(() => {
                bgElement.style.opacity = '1';
            }, 100);
        };
        
        img.onerror = function() {
            console.warn('Failed to load background image:', imageUrl);
        };
        
        img.src = imageUrl;
    }

    /**
     * 初始化滚动效果
     */
    function initScrollEffects() {
        // 平滑滚动到顶部
        const backToTopBtn = document.querySelector('.back-to-top');
        if (backToTopBtn) {
            backToTopBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        }

        // 导航栏链接平滑滚动
        document.addEventListener('click', function(e) {
            const link = e.target.closest('a[href^="#"]');
            if (link) {
                e.preventDefault();
                const target = document.querySelector(link.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    }

    /**
     * 增强搜索功能
     */
    function initSearchEnhancements() {
        const searchInputs = document.querySelectorAll('.search-input');
        
        searchInputs.forEach(input => {
            // 搜索框焦点效果
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });

            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });

            // 回车搜索
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    const form = this.closest('form');
                    if (form) {
                        form.submit();
                    }
                }
            });
        });
    }

    /**
     * 处理窗口大小变化
     */
    function handleResize() {
        // 重新计算浮动导航栏位置
        if (floatingNav) {
            hideFloatingNav();
            setTimeout(() => {
                handleScroll();
            }, 100);
        }
    }

    /**
     * 防抖函数
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * 节流函数
     */
    function throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    /**
     * 检测用户偏好设置
     */
    function detectUserPreferences() {
        // 检测是否偏好减少动画
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.documentElement.classList.add('reduce-motion');
        }

        // 检测用户颜色主题偏好
        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark-preference');
        }
    }

    /**
     * 错误处理
     */
    function handleError(error, context) {
        console.warn(`Qiwi Theme Enhanced Script Error in ${context}:`, error);
    }

    // 页面加载完成后初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            try {
                detectUserPreferences();
                init();
            } catch (error) {
                handleError(error, 'initialization');
            }
        });
    } else {
        try {
            detectUserPreferences();
            init();
        } catch (error) {
            handleError(error, 'immediate initialization');
        }
    }

    // 导出一些函数供外部调用
    window.qiwiEnhanced = {
        showFloatingNav: showFloatingNav,
        hideFloatingNav: hideFloatingNav,
        setBackgroundImage: setBackgroundImage
    };

})();