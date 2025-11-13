<?php 
$VERSION = '1.1.0';
if (!defined('__TYPECHO_ROOT_DIR__')) exit; 
?>

<footer class="site-footer" role="contentinfo">
    <div class="container">
        <div class="footer-content">
            <div class="footer-info">
                <p>&copy; <?php echo date('Y'); ?> <a href="<?php $this->options->siteUrl(); ?>"><?php $this->options->title(); ?></a>.
                <?php _e('由 <a href="https://typecho.org" target="_blank" rel="noopener">Typecho</a> 强力驱动'); ?>.</p>
                
                <?php if ($this->options->footerInfo): ?>
                    <p class="site-description"><?php $this->options->footerInfo() ?></p>
                <?php elseif ($this->options->description): ?>
                    <p class="site-description"><?php $this->options->description() ?></p>
                <?php endif; ?>
                
            </div>
            
            <div class="footer-meta">
                <div class="footer-links">
                    <a href="<?php $this->options->feedUrl(); ?>" target="_blank" rel="noopener" title="RSS 订阅">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 11a9 9 0 0 1 9 9"></path>
                            <path d="M4 4a16 16 0 0 1 16 16"></path>
                            <circle cx="5" cy="19" r="1"></circle>
                        </svg>
                        RSS
                    </a>
                    
                    <?php if ($this->user->hasLogin()): ?>
                        <a href="<?php $this->options->adminUrl(); ?>" target="_blank" rel="noopener" title="管理后台">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            管理
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="theme-info">
                    <span class="theme-name"><a href="https://github.com/lanbinleo/qiwi">Qiwi Theme</a></span>
                    <span class="theme-version">v<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; $this->need('version.php'); ?></span>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Back to top button -->
<button id="back-to-top" class="back-to-top" title="返回顶部">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="12" y1="19" x2="12" y2="5"></line>
        <polyline points="5,12 12,5 19,12"></polyline>
    </svg>
</button>

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

<script>
// Back to top functionality
(function() {
    const backToTopButton = document.getElementById('back-to-top');
    
    // Show/hide button based on scroll position
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            backToTopButton.classList.add('show');
        } else {
            backToTopButton.classList.remove('show');
        }
    });
    
    // Smooth scroll to top
    backToTopButton.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
    
    // Search form enhancements
    const searchInputs = document.querySelectorAll('.search-input');
    searchInputs.forEach(function(input) {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    });
    
    // Post card click functionality
    const postCards = document.querySelectorAll('.post-card');
    postCards.forEach(function(card) {
        card.addEventListener('click', function(e) {
            // Don't trigger if clicking on a link inside the card
            if (e.target.tagName.toLowerCase() === 'a') {
                return;
            }
            
            const href = this.getAttribute('data-href');
            if (href) {
                window.location.href = href;
            }
        });
        
        // Add pointer cursor and prevent text selection
        card.style.cursor = 'pointer';
        card.style.userSelect = 'none';
    });
})();

</script>
	

<?php if ($this->is('post') && $this->fields->isLatex == 1): ?>
<script type = "text/javascript" >
  document.addEventListener("DOMContentLoaded", function() {
    renderMathInElement(document.body, {
      delimiters: [{
          left: "$$",
          right: "$$",
          display: true
      }, {
          left: "$",
          right: "$",
          display: false
      }],
      ignoredTags: ["script", "noscript", "style", "textarea", "pre", "code"],
      ignoredClasses: ["nokatex"]
    });
  });
</script>
<?php endif; ?>

<script>
// 立即执行主题初始化，避免闪烁
(function() {
    const htmlElement = document.documentElement;
    const savedTheme = localStorage.getItem('theme-preference');
    
    // 立即设置主题，避免闪烁
    if (savedTheme === 'light') {
        htmlElement.setAttribute('data-theme', 'light');
    } else if (savedTheme === 'dark') {
        htmlElement.removeAttribute('data-theme');
    } else {
        // 如果没有保存的偏好，使用系统偏好
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (!prefersDark) {
            htmlElement.setAttribute('data-theme', 'light');
        }
    }
})();

// DOM 加载完成后绑定事件
document.addEventListener('DOMContentLoaded', () => {
    const themeToggleButton = document.getElementById('theme-toggle-btn');
    const htmlElement = document.documentElement;

    // 切换代码高亮主题的函数
    function switchCodeHighlightTheme(theme) {
        const lightTheme = document.getElementById('hljs-light-theme');
        const darkTheme = document.getElementById('hljs-dark-theme');

        if (theme === 'dark') {
            lightTheme.disabled = true;
            darkTheme.disabled = false;
        } else {
            lightTheme.disabled = false;
            darkTheme.disabled = true;
        }
    }

    // 初始化代码高亮主题
    const currentTheme = htmlElement.getAttribute('data-theme') ||
                       (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    switchCodeHighlightTheme(currentTheme);

    if (themeToggleButton) {
        themeToggleButton.addEventListener('click', () => {
            // 执行主题切换逻辑
            performThemeToggle();
        });
    }

    // 主题切换函数（供多个按钮调用）
    function performThemeToggle() {
        // 添加切换动画类到所有主题切换按钮
        const allThemeButtons = document.querySelectorAll('.theme-toggle-btn, .floating-theme-toggle-btn');
        allThemeButtons.forEach(button => {
            button.classList.add('switching');
        });

        // 检查当前是什么主题
        const currentTheme = htmlElement.getAttribute('data-theme');
        let newTheme;

        if (currentTheme === 'light') {
            // 如果是日间，切换到夜间
            htmlElement.removeAttribute('data-theme');
            newTheme = 'dark';
        } else {
            // 如果是夜间或未设置，切换到日间
            htmlElement.setAttribute('data-theme', 'light');
            newTheme = 'light';
        }

        // 切换代码高亮主题
        switchCodeHighlightTheme(newTheme);

        // 保存用户偏好到 localStorage
        localStorage.setItem('theme-preference', newTheme);

        // 移除动画类
        setTimeout(() => {
            allThemeButtons.forEach(button => {
                button.classList.remove('switching');
            });
        }, 500);
    }

    // 为了兼容性，将performThemeToggle函数暴露到全局
    window.performThemeToggle = performThemeToggle;

    // 监听系统主题变化
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        // 只有在用户没有手动设置偏好时才跟随系统
        const savedTheme = localStorage.getItem('theme-preference');
        if (!savedTheme) {
            if (e.matches) {
                htmlElement.removeAttribute('data-theme');
                switchCodeHighlightTheme('dark');
            } else {
                htmlElement.setAttribute('data-theme', 'light');
                switchCodeHighlightTheme('light');
            }
        }
    });
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
