<?php
$version = "1.1.2-alpha";
$releaseNotes = [
    "1.1.2" => "- 正式发布 1.1.2 版本，并进行1.1.3-alpha的开发\n **若页面显示有问题，请务必尝试清除浏览器缓存后再访问。**",
    "1.1.2-alpha" => <<<EOT
  本次发布引入了昼夜模式切换、友链页面的重大改进，以及主题初始化的优化。

  **若页面显示有问题，请务必尝试清除浏览器缓存后再访问。**

  **新增 New Features**
  - **昼夜模式切换**：新增了明暗主题切换按钮，支持用户偏好持久化保存。
  - **友链可视化编辑器**：引入了可视化编辑器，支持通过 JSON 输入更方便地管理友链。
  - **友链页面评论**：在友链页面实现了评论区，支持评论提交和自定义样式。

  **改进 Improvements**
  - **主题切换按钮**：优化了主题切换按钮的外观和交互体验。
  - **友链页面**：重构了页面样式，更新了页面描述，使其更清晰、更具亲和力。
  - **友链评论区**：优化了评论区的布局和样式。

  **修复 Fixes**
  - **主题闪烁**：优化了主题初始化逻辑，解决了页面加载时的闪烁问题。
EOT,
    "1.1.1" => "- 正式发布 1.1.1 版本，并进行1.1.2-alpha的开发",
    "1.1.1-alpha" => <<<EOT
**Major Feature Update**

- **Timemachine Feature**: New page template for posting and viewing comments with Markdown support and a floating action button.
- **Enhanced Post Layout**: Redesigned single post layout with a new card container.
- **Floating Navigation**: Added a floating navigation bar that appears on scroll.
- **Theme Options**: Background image, new layout options, custom footer info, tracking code.
- **Post Card Component**: Reusable post card with metadata and reading time.
- **Traditional Pagination**: Standard numbered pagination for archives.

**Changed**
- Improved previous/next post links, sidebar comments widget, floating nav logic, stylesheet path.

**Fixed**
- Floating nav visibility, Timemachine comment submission, recent comments filtering, Timemachine UI, thumbnail display.

**Removed**
- AJAX Load More in favor of traditional pagination.
EOT,
    "1.0.2-alpha" => <<<EOT
**Pre-release Updates**

- Support for custom post excerpts in theme
- Enhanced thumbnail display options and styles
- Edit button for logged-in users
- Options for thumbnail display and expiry in theme fields
- LICENSE and SECURITY files

**Fixed**
- Excerpt display logic, theme version in footer and index, license header

**Changed**
- Added version name "Qiwer" to initial stable release in CHANGELOG
EOT,
    "1.0.0" => <<<EOT
**Initial Stable Release**

- Complete theme structure: index, post, page, header, footer, sidebar, functions, archive, 404, comments
- Dark minimalist design, responsive layout, post/page rendering, sidebar widgets, comment system, search, navigation, pagination, LaTeX support, advanced content features, theme configuration, accessibility, performance

**Changed**
- Version bumped from 0.0.1 to 1.0.0, updated documentation

**Known Issues**
- LaTeX rendering requires manual enabling per post
- Search is basic
EOT,
];

$releaseDate = [
    "1.1.2" => "2025-09-28",
    "1.1.2-alpha" => "2025-09-27",
    "1.1.1" => "2025-09-26",
    "1.1.1-alpha" => "2025-09-25",
    "1.0.2-alpha" => "2025-09-06",
    "1.0.0" => "2025-09-06"
];

// 1) 输出明文版本
echo $version;
?>

<!-- 2) 公告模态框（将被JS移动到body下，避免嵌入在span中） -->
<style id="qiwi-version-modal-style">
  .notice { position: fixed; inset: 0; display: none; z-index: 99999; }
  .notice.show { display: block; }
  .notice__overlay {
    position: absolute; inset: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
  }
  .notice__content {
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    background: var(--color-bg-secondary);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-lg);
    width: min(680px, 92vw);
    height: 60vh;
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-lg);
  }
  .notice__header {
    padding: var(--spacing-lg);
    border-bottom: 1px solid var(--color-border);
    background: var(--color-bg-primary);
  }
  .notice__title {
    margin: 0 0 var(--spacing-xs) 0;
    color: var(--color-text-primary);
    font-size: 1.25rem;
    font-weight: 600;
  }
  .notice__meta { display: flex; align-items: center; gap: 8px; font-weight: 600; }
  .notice__ver {
    font-family: var(--font-family-code);
    padding: 2px 6px;
    border-radius: var(--border-radius-sm);
    background: var(--color-bg-tertiary);
    border: 1px solid var(--color-border);
  }
  .notice__ver--old { color: #ff6b6b; border-color: rgba(255,107,107,.3); background: rgba(255,107,107,.08); }
  .notice__ver--new { color: #3adb76; border-color: rgba(58,219,118,.3); background: rgba(58,219,118,.08); }
  .notice__arrow { color: var(--color-text-muted); }
  .notice__released { margin-top: 6px; color: var(--color-text-muted); font-size: var(--font-size-xs); }
  .notice__body { padding: var(--spacing-lg); color: var(--color-text-secondary); flex: 1; overflow-y: auto; }
  .notice__section { margin-bottom: var(--spacing-lg); }
  .notice__section-title { margin: 0 0 var(--spacing-sm) 0; color: var(--color-text-primary); font-size: 1rem; }
  .notice__markdown p { margin: 0 0 8px 0; }
  .notice__markdown ul, .notice__markdown ol { padding-left: 1.25em; margin: 0 0 8px 0; }
  .notice__markdown code {
    background: var(--color-bg-tertiary);
    color: var(--color-accent);
    padding: 2px 6px;
    border-radius: var(--border-radius-sm);
    font-family: var(--font-family-code);
  }
  .notice__history { }
  .notice__history-item { padding: var(--spacing-sm) 0; border-top: 1px dashed var(--color-border); }
  .notice__history-item:first-child { border-top: none; }
  .notice__history-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 6px; }
  .notice__history-version { font-weight: 600; color: var(--color-accent); }
  .notice__history-date { color: var(--color-text-muted); font-size: var(--font-size-xs); }
  .notice__footer {
    padding: var(--spacing-md) var(--spacing-lg);
    border-top: 1px solid var(--color-border);
    display: flex; justify-content: flex-end;
    background: var(--color-bg-secondary);
  }
  .notice__btn {
    background: linear-gradient(135deg, var(--color-accent) 0%, var(--color-accent-hover) 100%);
    color: var(--color-bg-primary);
    border: none;
    border-radius: var(--border-radius-sm);
    padding: 8px 14px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: var(--shadow-sm);
    transition: all 0.2s ease;
  }
  .notice__btn:hover { filter: brightness(1.05); transform: translateY(-1px); }
  .notice__btn:active { transform: translateY(0); }
  .notice__close {
    position: absolute; top: 10px; right: 12px;
    background: none; border: none; color: var(--color-text-muted);
    font-size: 22px; cursor: pointer; border-radius: var(--border-radius-sm);
    transition: all 0.2s ease;
  }
  .notice__close:hover { color: var(--color-text-primary); background: var(--color-bg-tertiary); }

  @media (max-width: 480px) {
    .notice__content { width: 96vw; }
  }
</style>

<div class="notice" id="qiwi-version-modal" aria-hidden="true" role="dialog">
  <div class="notice__overlay" data-qvm-close></div>
  <div class="notice__content" role="document" aria-labelledby="qvm-title" aria-describedby="qvm-desc">
    <button class="notice__close" type="button" aria-label="Close" title="Close" data-qvm-close>&times;</button>
    <div class="notice__header">
      <h3 id="qvm-title" class="notice__title">Qiwi主题更新啦！</h3>
      <br>
      <div class="notice__meta">
        <span class="notice__ver notice__ver--old notice__ver-old-text"></span>
        <span class="notice__arrow">→</span>
        <span class="notice__ver notice__ver--new notice__ver-new-text"></span>
      </div>
      <div class="notice__released" id="qvm-released"></div>
    </div>
    <div class="notice__body">
      <section class="notice__section">
        <h4 class="notice__section-title">新功能</h4>
        <div class="notice__markdown" id="qvm-current-note"></div>
      </section>
      <section class="notice__section">
        <h4 class="notice__section-title">所有更新日志</h4>
        <div class="notice__history" id="qvm-history"></div>
      </section>
    </div>
    <div class="notice__footer">
      <button type="button" class="notice__btn" id="qvm-ack">收到</button>
    </div>
  </div>
</div>

<script>
(function(){
  try {
    if (window.QIWI_VERSION_MODAL_INIT) return;
    window.QIWI_VERSION_MODAL_INIT = true;

    var CURRENT_VERSION = <?php echo json_encode($version, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var RELEASE_NOTES   = <?php echo json_encode($releaseNotes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var RELEASE_DATES   = <?php echo json_encode($releaseDate, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var STORAGE_KEY = 'qiwi_theme_version';
    var lastVersion = null;

    try { lastVersion = localStorage.getItem(STORAGE_KEY); } catch(e) {}

    var shouldShow = !lastVersion || lastVersion !== CURRENT_VERSION;

    var modal = document.getElementById('qiwi-version-modal');
    if (!modal) return;

    // 将模态框移动到body，避免嵌套在<span>里导致语义问题
    if (modal.parentElement && modal.parentElement !== document.body) {
      document.body.appendChild(modal);
    }

    var elOld = modal.querySelector('.notice__ver-old-text');
    var elNew = modal.querySelector('.notice__ver-new-text');
    var elReleased = document.getElementById('qvm-released');
    var elCurrentNote = document.getElementById('qvm-current-note');
    var elHistory = document.getElementById('qvm-history');
    var btnAck = document.getElementById('qvm-ack');

    elOld.textContent = lastVersion ? lastVersion : 'First visit';
    elNew.textContent = CURRENT_VERSION;

    var currentDate = RELEASE_DATES[CURRENT_VERSION] || '';
    if (currentDate) {
      elReleased.textContent = 'Released on ' + currentDate;
    }

    // 简易 Markdown 渲染（列表/段落/行内code/粗斜体）
    function inline(s) {
      return s
        .replace(/&/g,'&').replace(/</g,'<').replace(/>/g,'>')
        .replace(/`([^`]+)`/g,'<code>$1</code>')
        .replace(/\*\*([^*]+)\*\*/g,'<strong>$1</strong>')
        .replace(/\*([^*]+)\*/g,'<em>$1</em>');
    }

    function mdToHtml(md) {
      if (!md) return '';
      // 将字面值 "\n" 转换为真实换行
      md = String(md).replace(/\\n/g, '\n');
      md = md.replace(/\r\n?/g,'\n').trim();
      var lines = md.split('\n');
      var html = '';
      var inUl = false, inOl = false;

      function closeLists(){
        if (inUl) { html += '</ul>'; inUl = false; }
        if (inOl) { html += '</ol>'; inOl = false; }
      }

      lines.forEach(function(line){
        var li = line.match(/^\s*[-*]\s+(.*)$/);
        var oi = line.match(/^\s*\d+\.\s+(.*)$/);
        if (li) {
          if (!inUl) { closeLists(); html += '<ul>'; inUl = true; }
          html += '<li>' + inline(li[1]) + '</li>';
        } else if (oi) {
          if (!inOl) { closeLists(); html += '<ol>'; inOl = true; }
          html += '<li>' + inline(oi[1]) + '</li>';
        } else if (line.trim() === '') {
          closeLists(); html += '<br>';
        } else {
          closeLists(); html += '<p>' + inline(line.trim()) + '</p>';
        }
      });
      closeLists();
      return html;
    }

    // 当前版本的 release note
    elCurrentNote.innerHTML = mdToHtml(RELEASE_NOTES[CURRENT_VERSION] || '');

    // 全部历史
    var versions = Array.from(new Set(
      Object.keys(RELEASE_NOTES).concat(Object.keys(RELEASE_DATES))
    ));
    versions.sort(function(a,b){
      var da = Date.parse(RELEASE_DATES[a] || '') || 0;
      var db = Date.parse(RELEASE_DATES[b] || '') || 0;
      return db - da || (a < b ? 1 : -1);
    });

    versions.forEach(function(v){
      var note = RELEASE_NOTES[v] || '';
      var date = RELEASE_DATES[v] || '';
      var item = document.createElement('div');
      item.className = 'notice__history-item';
      item.innerHTML =
        '<div class="notice__history-head">' +
          '<span class="notice__history-version">' + v + '</span>' +
          (date ? '<span class="notice__history-date">' + date + '</span>' : '') +
        '</div>' +
        '<div class="notice__markdown">' + mdToHtml(note) + '</div>';
      elHistory.appendChild(item);
    });

    function openModal(){
      modal.setAttribute('aria-hidden','false');
      modal.classList.add('show');
      // 锁滚动
      document.documentElement.style.overflow = 'hidden';
    }

    function closeModal(){
      modal.setAttribute('aria-hidden','true');
      modal.classList.remove('show');
      document.documentElement.style.overflow = '';
      try { localStorage.setItem(STORAGE_KEY, CURRENT_VERSION); } catch(e) {}
    }

    modal.addEventListener('click', function(e){
      if (e.target && e.target.hasAttribute('data-qvm-close')) closeModal();
    });
    modal.querySelectorAll('[data-qvm-close]').forEach(function(el){
      el.addEventListener('click', closeModal);
    });
    btnAck.addEventListener('click', closeModal);
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && modal.classList.contains('show')) closeModal();
    });

    if (shouldShow) openModal();
  } catch (e) {
    console && console.warn && console.warn('Qiwi version modal error:', e);
  }
})();
</script>