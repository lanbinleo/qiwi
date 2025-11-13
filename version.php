<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$version = "1.1.6";
$releaseNotes = [
    "1.1.6" => <<<EOT
- 增加了密码保护文章的适配
EOT,
    "1.1.5" => <<<EOT
- 增加了代码块的高亮展示，并修复了alpha中的漏洞
- 加入了浮动导航栏的主题切换按钮
- 时光机页面加上了头像显示，修改了编辑部分
- 优化了部分css样式
EOT,
    "1.1.4" => <<<EOT
- 修改了关于页面头像的css动画
- 修改了文章左右显示的CSS，现在会交替左右显示了
EOT,
    "1.1.3" => <<<EOT
- 正式发布1.1.3版本，完成了关于页面和验证码页面等修复。
EOT,
    "1.1.3-alpha" => <<<EOT
- 新增验证码功能，提升评论区安全性
- 新增关于页面及其样式
- 版本号优化：以后任何更新都会直接开一个小版本，大版本会在一定时间后更新，后续将没有alpha版本
EOT,
    "1.1.2" => <<<EOT
- 正式发布 1.1.2 版本
- 开始 1.1.3-alpha 的开发
- **若页面显示有问题，请务必尝试清除浏览器缓存后再访问。**
EOT,
    "1.1.2-alpha" => <<<EOT
- 新增昼夜模式切换功能，支持用户偏好持久化保存
- 引入友链可视化编辑器，支持通过 JSON 输入管理友链
- 在友链页面新增评论区
- 优化主题切换按钮的外观和交互体验
- 重构友链页面样式，更新页面描述
- 优化友链评论区的布局和样式
- 优化主题初始化逻辑，解决页面加载时的闪烁问题
- **若页面显示有问题，请务必尝试清除浏览器缓存后再访问。**
EOT,
    "1.1.1" => "- 正式发布 1.1.1 版本\n- 开始 1.1.2-alpha 的开发",
    "1.1.1-alpha" => <<<EOT
- 新增时间机页面，支持 Markdown 和浮动按钮
- 重新设计文章布局，使用新的卡片容器
- 新增滚动时出现的浮动导航栏
- 新增主题选项：背景图、布局、自定义页脚、追踪代码
- 新增可复用的文章卡片组件，包含元数据和阅读时间
- 新增传统的数字分页功能
- 改进了文章上下篇链接、侧边栏评论组件、浮动导航逻辑和样式表路径
- 修复了浮动导航可见性、时间机评论提交、最新评论过滤、时间机界面和缩略图显示问题
- 移除了 AJAX 加载更多功能，改用传统分页
EOT,
    "1.0.2-alpha" => <<<EOT
- 支持在主题中自定义文章摘要
- 增强缩略图显示选项和样式
- 为登录用户添加编辑按钮
- 在主题设置中增加缩略图显示和过期选项
- 新增 LICENSE 和 SECURITY 文件
- 修复了摘要显示逻辑、页脚和首页的主题版本号、许可证头部
- 在更新日志中为初始稳定版添加了 "Qiwer" 版本代号
EOT,
    "1.0.0" => <<<EOT
- 初始稳定版发布，包含完整的主题结构：首页、文章、页面、页眉、页脚、侧边栏、函数、归档、404、评论
- 采用深色极简设计，响应式布局，支持文章/页面渲染、侧边栏小工具、评论系统、搜索、导航、分页、LaTeX、高级内容功能、主题配置、无障碍访问和性能优化
- 版本号从 0.0.1 提升至 1.0.0，并更新了文档
- 已知问题：LaTeX 渲染需在每篇文章中手动启用；搜索功能较基础
EOT,
];

$releaseDate = [
    "1.1.6" => "2025-10-20",
    "1.1.5" => "2025-10-10",
    "1.1.4" => "2025-10-01",
    "1.1.3" => "2025-10-01",
    "1.1.3-alpha" => "2025-10-1",
    "1.1.2" => "2025-09-28",
    "1.1.2-alpha" => "2025-09-27",
    "1.1.1" => "2025-09-26",
    "1.1.1-alpha" => "2025-09-25",
    "1.0.2-alpha" => "2025-09-06",
    "1.0.0" => "2025-09-06"
];

// 输出版本号
echo $version;
?>

<!-- 版本更新抽屉样式 -->
<style id="qiwi-version-drawer-style">
  .version-drawer {
    position: fixed;
    inset: 0;
    z-index: 99999;
    pointer-events: none;
    transition: opacity var(--transition);
    opacity: 0;
  }

  .version-drawer.show {
    pointer-events: auto;
    opacity: 1;
  }

  .version-drawer__overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    transition: opacity var(--transition);
  }

  .version-drawer__panel {
    position: absolute;
    right: 0;
    top: 0;
    height: 100%;
    width: min(480px, 85vw);
    background: var(--color-bg);
    border-left: 1px solid var(--color-border);
    display: flex;
    flex-direction: column;
    transform: translateX(100%);
    transition: transform 0.4s cubic-bezier(0.25, 0.1, 0.25, 1);
  }

  .version-drawer.show .version-drawer__panel {
    transform: translateX(0);
  }

  /* 头部 */
  .version-drawer__header {
    flex-shrink: 0;
    padding: calc(var(--spacing-unit) * 4) calc(var(--spacing-unit) * 4);
    border-bottom: 1px solid var(--color-border);
    background: var(--color-surface);
  }

  .version-drawer__close {
    position: absolute;
    top: calc(var(--spacing-unit) * 3);
    right: calc(var(--spacing-unit) * 3);
    background: none;
    border: none;
    color: var(--color-text-secondary);
    font-size: 24px;
    cursor: pointer;
    padding: calc(var(--spacing-unit));
    line-height: 1;
    transition: color var(--transition);
    border-radius: 6px;
  }

  .version-drawer__close:hover {
    color: var(--color-text-primary);
    background: var(--color-bg);
  }

  .version-drawer__title {
    font-size: 20px;
    font-weight: 600;
    color: var(--color-text-primary);
    margin-bottom: calc(var(--spacing-unit) * 3);
    letter-spacing: -0.02em;
  }

  .version-drawer__meta {
    display: flex;
    align-items: center;
    gap: calc(var(--spacing-unit) * 2);
  }

  .version-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    font-family: var(--font-mono);
    font-size: 13px;
    font-weight: 500;
    border-radius: 6px;
    border: 1px solid;
  }

  .version-badge--old {
    color: #ef4444;
    background: rgba(239, 68, 68, 0.08);
    border-color: rgba(239, 68, 68, 0.2);
  }

  .version-badge--new {
    color: #10b981;
    background: rgba(16, 185, 129, 0.08);
    border-color: rgba(16, 185, 129, 0.2);
  }

  .version-arrow {
    color: var(--color-text-tertiary);
    font-size: 14px;
  }

  .version-drawer__date {
    margin-top: calc(var(--spacing-unit) * 2);
    font-size: 13px;
    color: var(--color-text-tertiary);
  }

  /* 内容区 */
  .version-drawer__body {
    flex: 1;
    overflow-y: auto;
    padding: calc(var(--spacing-unit) * 4);
  }

  .version-section {
    margin-bottom: calc(var(--spacing-unit) * 6);
  }

  .version-section:last-child {
    margin-bottom: 0;
  }

  .version-section__title {
    font-size: 16px;
    font-weight: 600;
    color: var(--color-text-primary);
    margin-bottom: calc(var(--spacing-unit) * 3);
    letter-spacing: -0.01em;
  }

  .version-content {
    font-size: 15px;
    line-height: 1.7;
    color: var(--color-text-secondary);
  }

  .version-content p {
    margin-bottom: calc(var(--spacing-unit) * 2);
  }

  .version-content ul,
  .version-content ol {
    padding-left: calc(var(--spacing-unit) * 3);
    margin-bottom: calc(var(--spacing-unit) * 2);
  }

  .version-content li {
    margin-bottom: calc(var(--spacing-unit));
  }

  .version-content strong {
    font-weight: 600;
    color: var(--color-text-primary);
  }

  .version-content code {
    font-family: var(--font-mono);
    font-size: 0.9em;
    background: var(--color-surface);
    padding: 2px 6px;
    border-radius: 4px;
    border: 1px solid var(--color-border);
    color: var(--color-accent);
  }

  /* 历史版本列表 */
  .version-history__list {
    display: flex;
    flex-direction: column;
    gap: calc(var(--spacing-unit) * 3);
  }

  .version-history__item {
    padding: calc(var(--spacing-unit) * 3);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius);
  }

  .version-history__head {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: calc(var(--spacing-unit) * 2);
  }

  .version-history__version {
    font-family: var(--font-mono);
    font-size: 14px;
    font-weight: 600;
    color: var(--color-accent);
  }

  .version-history__date {
    font-size: 12px;
    color: var(--color-text-tertiary);
  }

  /* 底部按钮 */
  .version-drawer__footer {
    flex-shrink: 0;
    padding: calc(var(--spacing-unit) * 3) calc(var(--spacing-unit) * 4);
    border-top: 1px solid var(--color-border);
    background: var(--color-surface);
  }

  .version-drawer__button {
    width: 100%;
    padding: calc(var(--spacing-unit) * 2);
    font-size: 15px;
    font-weight: 500;
    color: var(--color-bg);
    background: var(--color-accent);
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all var(--transition);
  }

  .version-drawer__button:hover {
    opacity: 0.9;
    transform: translateY(-1px);
  }

  .version-drawer__button:active {
    transform: translateY(0);
  }

  /* 移动端优化 */
  @media (max-width: 768px) {
    .version-drawer__panel {
      width: 100%;
      height: 85vh;
      top: auto;
      bottom: 0;
      border-left: none;
      border-top: 1px solid var(--color-border);
      border-radius: var(--radius) var(--radius) 0 0;
      transform: translateY(100%);
    }

    .version-drawer.show .version-drawer__panel {
      transform: translateY(0);
    }
  }
</style>

<!-- 版本更新抽屉 HTML -->
<div class="version-drawer" id="qiwi-version-drawer" aria-hidden="true" role="dialog">
  <div class="version-drawer__overlay" data-drawer-close></div>
  <div class="version-drawer__panel" role="document" aria-labelledby="drawer-title">
    <button class="version-drawer__close" type="button" aria-label="关闭" data-drawer-close>&times;</button>

    <div class="version-drawer__header">
      <h3 id="drawer-title" class="version-drawer__title">主题更新通知</h3>
      <div class="version-drawer__meta">
        <span class="version-badge version-badge--old" id="drawer-old-version"></span>
        <span class="version-arrow">→</span>
        <span class="version-badge version-badge--new" id="drawer-new-version"></span>
      </div>
      <div class="version-drawer__date" id="drawer-date"></div>
    </div>

    <div class="version-drawer__body">
      <section class="version-section">
        <h4 class="version-section__title">本次更新</h4>
        <div class="version-content" id="drawer-current-note"></div>
      </section>

      <section class="version-section">
        <h4 class="version-section__title">历史版本</h4>
        <div class="version-history__list" id="drawer-history"></div>
      </section>
    </div>

    <div class="version-drawer__footer">
      <button type="button" class="version-drawer__button" id="drawer-confirm">知道了</button>
    </div>
  </div>
</div>

<script>
(function() {
  try {
    if (window.QIWI_VERSION_DRAWER_INIT) return;
    window.QIWI_VERSION_DRAWER_INIT = true;

    const CURRENT_VERSION = <?php echo json_encode($version, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const RELEASE_NOTES = <?php echo json_encode($releaseNotes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const RELEASE_DATES = <?php echo json_encode($releaseDate, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const STORAGE_KEY = 'qiwi_theme_version';

    let lastVersion = null;
    try { lastVersion = localStorage.getItem(STORAGE_KEY); } catch(e) {}

    const shouldShow = !lastVersion || lastVersion !== CURRENT_VERSION;

    const drawer = document.getElementById('qiwi-version-drawer');
    if (!drawer) return;

    const elOldVersion = document.getElementById('drawer-old-version');
    const elNewVersion = document.getElementById('drawer-new-version');
    const elDate = document.getElementById('drawer-date');
    const elCurrentNote = document.getElementById('drawer-current-note');
    const elHistory = document.getElementById('drawer-history');
    const btnConfirm = document.getElementById('drawer-confirm');

    // 设置版本信息
    elOldVersion.textContent = lastVersion || '首次访问';
    elNewVersion.textContent = CURRENT_VERSION;

    const currentDate = RELEASE_DATES[CURRENT_VERSION] || '';
    if (currentDate) {
      elDate.textContent = '发布于 ' + currentDate;
    }

    // Markdown 解析
    function escapeHtml(str) {
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    }

    function inline(s) {
      return escapeHtml(s)
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/\*([^*]+)\*/g, '<em>$1</em>');
    }

    function mdToHtml(md) {
      if (!md) return '';
      md = String(md).replace(/\\n/g, '\n').replace(/\r\n?/g, '\n').trim();
      const lines = md.split('\n');
      let html = '';
      let inUl = false, inOl = false;

      function closeLists() {
        if (inUl) { html += '</ul>'; inUl = false; }
        if (inOl) { html += '</ol>'; inOl = false; }
      }

      lines.forEach(function(line) {
        const li = line.match(/^\s*[-*]\s+(.*)$/);
        const oi = line.match(/^\s*\d+\.\s+(.*)$/);

        if (li) {
          if (!inUl) { closeLists(); html += '<ul>'; inUl = true; }
          html += '<li>' + inline(li[1]) + '</li>';
        } else if (oi) {
          if (!inOl) { closeLists(); html += '<ol>'; inOl = true; }
          html += '<li>' + inline(oi[1]) + '</li>';
        } else if (line.trim() === '') {
          closeLists();
        } else {
          closeLists();
          html += '<p>' + inline(line.trim()) + '</p>';
        }
      });
      closeLists();
      return html;
    }

    // 渲染当前版本更新内容
    elCurrentNote.innerHTML = mdToHtml(RELEASE_NOTES[CURRENT_VERSION] || '暂无更新说明');

    // 渲染历史版本
    const versions = Array.from(new Set(
      Object.keys(RELEASE_NOTES).concat(Object.keys(RELEASE_DATES))
    ));

    versions.sort(function(a, b) {
      const da = Date.parse(RELEASE_DATES[a] || '') || 0;
      const db = Date.parse(RELEASE_DATES[b] || '') || 0;
      return db - da || (a < b ? 1 : -1);
    });

    versions.forEach(function(v) {
      const note = RELEASE_NOTES[v] || '';
      const date = RELEASE_DATES[v] || '';

      const item = document.createElement('div');
      item.className = 'version-history__item';
      item.innerHTML =
        '<div class="version-history__head">' +
          '<span class="version-history__version">' + escapeHtml(v) + '</span>' +
          (date ? '<span class="version-history__date">' + escapeHtml(date) + '</span>' : '') +
        '</div>' +
        '<div class="version-content">' + mdToHtml(note) + '</div>';
      elHistory.appendChild(item);
    });

    // 打开/关闭抽屉
    function openDrawer() {
      drawer.setAttribute('aria-hidden', 'false');
      drawer.classList.add('show');
      document.documentElement.style.overflow = 'hidden';
    }

    function closeDrawer() {
      drawer.setAttribute('aria-hidden', 'true');
      drawer.classList.remove('show');
      document.documentElement.style.overflow = '';
      try { localStorage.setItem(STORAGE_KEY, CURRENT_VERSION); } catch(e) {}
    }

    // 暴露到全局
    window.showQiwiVersionDrawer = openDrawer;

    // 事件绑定
    // 1. 关闭按钮
    drawer.querySelectorAll('[data-drawer-close]').forEach(function(el) {
      el.addEventListener('click', function(e) {
        e.stopPropagation();
        closeDrawer();
      });
    });

    // 2. 确认按钮
    btnConfirm.addEventListener('click', closeDrawer);

    // 3. 阻止 panel 内部点击冒泡
    const panel = drawer.querySelector('.version-drawer__panel');
    if (panel) {
      panel.addEventListener('click', function(e) {
        e.stopPropagation();
      });
    }

    // 4. ESC 键关闭
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && drawer.classList.contains('show')) {
        closeDrawer();
      }
    });

    // 自动显示
    if (shouldShow) {
      setTimeout(openDrawer, 500);
    }
  } catch (e) {
    console.warn('Qiwi version drawer error:', e);
  }
})();
</script>
