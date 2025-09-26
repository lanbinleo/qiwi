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

<!-- Toggle Day/Night Mode Button -->
<button id="theme-toggle-btn" class="theme-toggle-btn" title="切换日间/夜间模式">
    <svg class="icon-sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="5"></circle>
        <line x1="12" y1="1" x2="12" y2="3"></line>
        <line x1="12" y1="21" x2="12" y2="23"></line>
        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
        <line x1="1" y1="12" x2="3" y2="12"></line>
        <line x1="21" y1="12" x2="23" y2="12"></line>
        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
    </svg>
    <svg class="icon-moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
    </svg>
</button>

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
document.addEventListener('DOMContentLoaded', () => {
    // 假设你有一个ID为 'theme-toggle-btn' 的按钮
    const themeToggleButton = document.getElementById('theme-toggle-btn');
    const htmlElement = document.documentElement; // 获取 <html> 元素

    // TODO: 在这里可以加上读取 localStorage 的逻辑，记住用户的选择
    
    if (themeToggleButton) {
        themeToggleButton.addEventListener('click', () => {
            // 检查当前是什么主题
            const currentTheme = htmlElement.getAttribute('data-theme');

            if (currentTheme === 'light') {
                // 如果是日间，切换到夜间 (移除属性)
                htmlElement.removeAttribute('data-theme');
            } else {
                // 如果是夜间或未设置，切换到日间
                htmlElement.setAttribute('data-theme', 'light');
            }
        });
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
