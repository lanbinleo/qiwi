<?php 
/**
 * 时光机
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$this->need('header.php');

// 检查是否有评论提交
$commentSubmitted = false;
$submissionError = null;
if (isset($_POST['text']) && !empty($_POST['text'])) {
    // Debug: 记录提交信息
    error_log("TimeMachine Debug - 收到评论提交: " . date('Y-m-d H:i:s'));
    error_log("TimeMachine Debug - POST数据: " . json_encode($_POST));
    error_log("TimeMachine Debug - Referer: " . ($this->request->getReferer() ?? 'null'));
    error_log("TimeMachine Debug - 请求方法: " . $_SERVER['REQUEST_METHOD']);

    $commentSubmitted = true;
}

// 获取当前页面ID用于评论查询
$pageId = $this->cid;
$pageSize = 10; // 每页显示的说说数量
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// 获取当前页面作者信息
$authorUid = $this->author->uid;
$authorName = $this->author->screenName;
$authorMail = $this->author->mail;
$authorUrl = $this->author->url;

// 获取数据库实例
if (class_exists('Typecho_Db')) {
    $db = Typecho_Db::get();
    $prefix = $db->getPrefix();
} else {
    $db = \Typecho\Db::get();
    $prefix = $db->getPrefix();
}

// 获取该页面的作者评论（说说）
$select = $db->select()->from($prefix.'comments')
    ->where('cid = ?', $pageId)
    ->where('status = ?', 'approved')
    ->where('authorId = ?', $authorUid)
    ->order('created', $db::SORT_DESC)
    ->page($currentPage, $pageSize);

$comments = $db->fetchAll($select);

// 获取总数
$totalSelect = $db->select('COUNT(coid) AS total')->from($prefix.'comments')
    ->where('cid = ?', $pageId)
    ->where('status = ?', 'approved')
    ->where('authorId = ?', $authorUid);

$totalResult = $db->fetchRow($totalSelect);
$total = $totalResult ? $totalResult['total'] : 0;
$totalPages = ceil($total / $pageSize);

// 移除Ajax处理逻辑，现在使用传统分页导航

// Markdown渲染函数（支持图片）
function renderMarkdown($text) {
    if (empty($text)) return '';
    
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // 处理代码块
    $text = preg_replace_callback('/```(.*?)```/s', function($matches) {
        return '<pre><code>' . trim($matches[1]) . '</code></pre>';
    }, $text);
    
    // 处理图片（新增）
    $text = preg_replace_callback('/!\[([^\]]*)\]\(([^\)]+)\)/', function($matches) {
        $altText = trim($matches[1]);
        $imageUrl = trim($matches[2]);
        
        if (empty($imageUrl)) return $altText;
        
        return '<img src="' . htmlspecialchars($imageUrl, ENT_QUOTES) . '" alt="' . htmlspecialchars($altText, ENT_QUOTES) . '" class="timemachine-image" loading="lazy">';
    }, $text);
    
    // 处理粗体
    $text = preg_replace('/\*\*((?:(?!\*\*).)+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/__((?:(?!__).)+?)__/', '<strong>$1</strong>', $text);
    
    // 处理斜体
    $text = preg_replace('/(?<!\*)\*([^\*\n]+?)\*(?!\*)/', '<em>$1</em>', $text);
    $text = preg_replace('/(?<!_)_([^_\n]+?)_(?!_)/', '<em>$1</em>', $text);
    
    // 处理行内代码
    $text = preg_replace('/`([^`\n]+?)`/', '<code>$1</code>', $text);
    
    // 处理链接
    $text = preg_replace_callback('/\[([^\]]*)\]\(([^\)]+)\)/', function($matches) {
        $linkText = trim($matches[1]);
        $linkUrl = trim($matches[2]);
        
        if (empty($linkUrl) || preg_match('/^javascript:/i', $linkUrl)) {
            return $linkText;
        }
        
        if (!preg_match('/^https?:\/\//', $linkUrl) && !preg_match('/^\//', $linkUrl)) {
            $linkUrl = 'http://' . $linkUrl;
        }
        
        return '<a href="' . htmlspecialchars($linkUrl, ENT_QUOTES) . '" target="_blank" rel="noopener noreferrer">' . $linkText . '</a>';
    }, $text);
    
    // 处理换行
    $text = nl2br($text);
    
    return $text;
}
?>

<div class="container main-wrapper">
  <main class="content-area">
    <article class="single-page timemachine-page" itemscope itemtype="http://schema.org/WebPage">

      <!-- 页面头部 -->
      <header class="page-header">
        <h1 class="page-title" itemprop="name headline">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; vertical-align: middle;">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12,6 12,12 16,14"></polyline>
          </svg>
          <?php $this->title() ?>
        </h1>
        
        <?php if ($this->content()): ?>
        <div class="page-content" itemprop="text">
          <?php $this->content(); ?>
        </div>
        <?php endif; ?>
        
        <div class="timemachine-stats">
          <span class="stats-item">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="m3 21 1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"></path>
            </svg>
            共 <?php echo $total; ?> 条说说
          </span>
          
          <!-- 一键到底部按钮 -->
          <?php if ($this->user->hasLogin() && ($this->user->uid === $authorUid || $this->user->group === 'administrator')): ?>
          <button id="go-to-publisher" class="go-to-publisher-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M7 13l3 3 7-7"></path>
              <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path>
            </svg>
            写说说
          </button>
          <?php endif; ?>
        </div>
      </header>

      <!-- 说说列表 -->
      <div class="timemachine-content">
        <?php if ($total > 0): ?>
        <div id="timemachine-list" class="timemachine-list">
          <?php foreach ($comments as $comment): ?>
          <article class="timemachine-item" data-id="<?php echo $comment['coid']; ?>">
            <div class="timemachine-meta">
              <div class="author-avatar">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                  <circle cx="12" cy="7" r="4"></circle>
                </svg>
              </div>
              <div class="meta-info">
                <span class="author-name"><?php echo htmlspecialchars($authorName, ENT_QUOTES); ?></span>
                <time class="publish-time" datetime="<?php echo date('c', $comment['created']); ?>" data-timestamp="<?php echo $comment['created']; ?>">
                  <?php echo date('Y年m月d日 H:i', $comment['created']); ?>
                </time>
              </div>
            </div>
            
            <div class="timemachine-text">
              <?php echo renderMarkdown($comment['text']); ?>
            </div>
            
            <div class="timemachine-actions">
              <span class="action-time">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <circle cx="12" cy="12" r="10"></circle>
                  <polyline points="12,6 12,12 16,14"></polyline>
                </svg>
                <span class="time-ago" data-timestamp="<?php echo $comment['created']; ?>">
                计算中...
              </span>
              </span>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
        
        <!-- 分页导航 -->
        <?php if ($totalPages > 1): ?>
        <div class="timemachine-pagination">
          <div class="pagination-nav">
            <?php
            $baseUrl = $this->permalink;
            
            // 上一页
            if ($currentPage > 1): ?>
            <a href="<?php echo $baseUrl . '?page=' . ($currentPage - 1); ?>" class="pagination-btn prev-btn">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15,18 9,12 15,6"></polyline>
              </svg>
              上一页
            </a>
            <?php else: ?>
            <span class="pagination-btn prev-btn disabled">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15,18 9,12 15,6"></polyline>
              </svg>
              上一页
            </span>
            <?php endif; ?>
            
            <!-- 页码列表 -->
            <div class="pagination-numbers">
              <?php
              // 计算显示的页码范围
              $startPage = max(1, $currentPage - 2);
              $endPage = min($totalPages, $currentPage + 2);
              
              // 如果是前几页，显示更多后面的页码
              if ($currentPage <= 3) {
                $endPage = min($totalPages, 5);
              }
              
              // 如果是后几页，显示更多前面的页码
              if ($currentPage > $totalPages - 3) {
                $startPage = max(1, $totalPages - 4);
              }
              
              // 显示第一页
              if ($startPage > 1): ?>
                <a href="<?php echo $baseUrl . '?page=1'; ?>" class="pagination-number">1</a>
                <?php if ($startPage > 2): ?>
                  <span class="pagination-ellipsis">...</span>
                <?php endif; ?>
              <?php endif; ?>
              
              <!-- 显示页码范围 -->
              <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <?php if ($i == $currentPage): ?>
                  <span class="pagination-number current"><?php echo $i; ?></span>
                <?php else: ?>
                  <a href="<?php echo $baseUrl . '?page=' . $i; ?>" class="pagination-number"><?php echo $i; ?></a>
                <?php endif; ?>
              <?php endfor; ?>
              
              <!-- 显示最后一页 -->
              <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                  <span class="pagination-ellipsis">...</span>
                <?php endif; ?>
                <a href="<?php echo $baseUrl . '?page=' . $totalPages; ?>" class="pagination-number"><?php echo $totalPages; ?></a>
              <?php endif; ?>
            </div>
            
            <!-- 下一页 -->
            <?php if ($currentPage < $totalPages): ?>
            <a href="<?php echo $baseUrl . '?page=' . ($currentPage + 1); ?>" class="pagination-btn next-btn">
              下一页
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9,18 15,12 9,6"></polyline>
              </svg>
            </a>
            <?php else: ?>
            <span class="pagination-btn next-btn disabled">
              下一页
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9,18 15,12 9,6"></polyline>
              </svg>
            </span>
            <?php endif; ?>
          </div>
          
          <div class="pagination-info">
            第 <?php echo $currentPage; ?> 页 / 共 <?php echo $totalPages; ?> 页 (共 <?php echo $total; ?> 条说说)
          </div>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="empty-timemachine">
          <div class="empty-icon">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <polyline points="12,6 12,12 16,14"></polyline>
            </svg>
          </div>
          <h3>还没有任何说说</h3>
          <p>时光机还是空的，快来记录第一条想法吧！</p>
        </div>
        <?php endif; ?>
      </div>

      <br><br>

      <!-- 说说发布区域（移到底部） -->
      <?php if ($this->user->hasLogin() && ($this->user->uid === $authorUid || $this->user->group === 'administrator')): ?>
      <div id="timemachine-publisher" class="timemachine-publisher">
        <div class="publisher-header">
          <h3>发布新的说说</h3>
          <p>记录此刻的想法和感受...</p>
          <button id="open-settings" class="settings-btn">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
              <path fill-rule="evenodd" clip-rule="evenodd" d="M11.0175 19C10.6601 19 10.3552 18.7347 10.297 18.373C10.2434 18.0804 10.038 17.8413 9.76171 17.75C9.53658 17.6707 9.31645 17.5772 9.10261 17.47C8.84815 17.3365 8.54289 17.3565 8.30701 17.522C8.02156 17.7325 7.62943 17.6999 7.38076 17.445L6.41356 16.453C6.15326 16.186 6.11944 15.7651 6.33361 15.458C6.49878 15.2105 6.52257 14.8914 6.39601 14.621C6.31262 14.4332 6.23906 14.2409 6.17566 14.045C6.08485 13.7363 5.8342 13.5051 5.52533 13.445C5.15287 13.384 4.8779 13.0559 4.87501 12.669V11.428C4.87303 10.9821 5.18705 10.6007 5.61601 10.528C5.94143 10.4645 6.21316 10.2359 6.33751 9.921C6.37456 9.83233 6.41356 9.74433 6.45451 9.657C6.61989 9.33044 6.59705 8.93711 6.39503 8.633C6.1424 8.27288 6.18119 7.77809 6.48668 7.464L7.19746 6.735C7.54802 6.37532 8.1009 6.32877 8.50396 6.625L8.52638 6.641C8.82735 6.84876 9.21033 6.88639 9.54428 6.741C9.90155 6.60911 10.1649 6.29424 10.2375 5.912L10.2473 5.878C10.3275 5.37197 10.7536 5.00021 11.2535 5H12.1115C12.6248 4.99976 13.0629 5.38057 13.1469 5.9L13.1625 5.97C13.2314 6.33617 13.4811 6.63922 13.8216 6.77C14.1498 6.91447 14.5272 6.87674 14.822 6.67L14.8707 6.634C15.2842 6.32834 15.8528 6.37535 16.2133 6.745L16.8675 7.417C17.1954 7.75516 17.2366 8.28693 16.965 8.674C16.7522 8.99752 16.7251 9.41325 16.8938 9.763L16.9358 9.863C17.0724 10.2045 17.3681 10.452 17.7216 10.521C18.1837 10.5983 18.5235 11.0069 18.525 11.487V12.6C18.5249 13.0234 18.2263 13.3846 17.8191 13.454C17.4842 13.5199 17.2114 13.7686 17.1083 14.102C17.0628 14.2353 17.0121 14.3687 16.9562 14.502C16.8261 14.795 16.855 15.1364 17.0323 15.402C17.2662 15.7358 17.2299 16.1943 16.9465 16.485L16.0388 17.417C15.7792 17.6832 15.3698 17.7175 15.0716 17.498C14.8226 17.3235 14.5001 17.3043 14.2331 17.448C14.0428 17.5447 13.8475 17.6305 13.6481 17.705C13.3692 17.8037 13.1636 18.0485 13.1099 18.346C13.053 18.7203 12.7401 18.9972 12.3708 19H11.0175Z"stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
              <path fill-rule="evenodd" clip-rule="evenodd" d="M13.9747 12C13.9747 13.2885 12.9563 14.333 11.7 14.333C10.4437 14.333 9.42533 13.2885 9.42533 12C9.42533 10.7115 10.4437 9.66699 11.7 9.66699C12.9563 9.66699 13.9747 10.7115 13.9747 12Z" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            图床设置
          </button>
        </div>
        
        <?php if ($this->allow('comment')): ?>
        <div class="timemachine-form-wrapper">
          <form method="post" action="<?php $this->commentUrl() ?>" id="timemachine-form" class="timemachine-form" role="form">
            <div class="publisher-content">
              <textarea name="text" id="timemachine-textarea" placeholder="想说些什么呢？支持 Markdown 语法和图片上传（粘贴图片自动上传）..." rows="6" required><?php $this->remember('text'); ?></textarea>
              <div class="upload-progress" id="upload-progress" style="display: none;">
                <div class="progress-bar">
                  <div class="progress-fill"></div>
                </div>
                <span class="progress-text">上传中...</span>
              </div>
            </div>
            
            <!-- 隐藏字段 -->
            <input type="hidden" name="author" value="<?php echo htmlspecialchars($authorName, ENT_QUOTES); ?>">
            <input type="hidden" name="mail" value="<?php echo htmlspecialchars($authorMail, ENT_QUOTES); ?>">
            <input type="hidden" name="url" value="<?php echo htmlspecialchars($authorUrl, ENT_QUOTES); ?>">
            
            <?php
            // 修复: 使用一致的referer策略，避免新开页面和刷新页面token不一致
            $referer_source = $this->request->getReferer() ?? $this->request->getRequestUrl();

            $token = '';
            if (class_exists('Typecho_Widget_Helper_Form_Element_Hidden')) {
                $security = new Typecho_Widget_Helper_Form_Element_Hidden('_');
                $token = $this->security->getToken($referer_source);
                $security->value($token);
                echo '<input type="hidden" name="_" value="' . $security->value . '">';
            } else if (method_exists($this, 'security')) {
                $token = $this->security->getToken($referer_source);
                echo '<input type="hidden" name="_" value="' . $token . '">';
            } else {
                $widget = $this->widget('Widget_Security');
                $token = $widget->getToken($referer_source);
                echo '<input type="hidden" name="_" value="' . $token . '">';
            }

            // Debug: 输出token信息到控制台
            $debug_info = [
                'generated_token' => $token,
                'referer_source' => $referer_source,
                'actual_referer' => $this->request->getReferer(),
                'current_url' => $this->request->getRequestUrl(),
                'request_method' => $_SERVER['REQUEST_METHOD'],
                'is_ajax' => isset($_GET['ajax']),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ];
            echo '<script>console.log("Token Debug Info:", ' . json_encode($debug_info) . ');</script>';
            ?>
            
            <div class="publisher-actions">
              <div class="publisher-tips">
                <span class="tip-item">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14,2 14,8 20,8"></polyline>
                  </svg>
                  支持 Markdown
                </span>
                <span class="tip-item">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                    <polyline points="21,15 16,10 5,21"></polyline>
                  </svg>
                  粘贴上传图片
                </span>
              </div>
              <button type="submit" class="publish-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="22" y1="2" x2="11" y2="13"></line>
                  <polygon points="22,2 15,22 11,13 2,9 22,2"></polygon>
                </svg>
                发布说说
              </button>
            </div>
          </form>
        </div>
        <?php else: ?>
        <div class="comment-closed-notice">
          <p>评论功能已关闭，无法发布说说。请在后台开启该页面的评论功能。</p>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- 右下角浮动加号按钮 -->
      <?php if ($this->user->hasLogin() && ($this->user->uid === $authorUid || $this->user->group === 'administrator')): ?>
      <button id="floating-add-btn" class="floating-add-btn" title="快速发布说说">
        +
      </button>
      <?php endif; ?>

    </article>
  </main>

  <aside class="sidebar">
    <?php $this->need('sidebar.php'); ?>
  </aside>
</div>

<!-- 设置Modal -->
<div id="settings-modal" class="settings-modal" style="display: none;">
  <div class="modal-overlay"></div>
  <div class="modal-content">
    <div class="modal-header">
      <h3>图床设置</h3>
      <button class="modal-close">&times;</button>
    </div>
    <div class="modal-body">
      <form id="settings-form">
        <div class="form-group">
          <label for="base-url">图床API地址:</label>
          <input type="url" id="base-url" placeholder="https://p.maxqi.top/api/v1" value="https://p.maxqi.top/api/v1">
        </div>
        <div class="form-group">
          <label for="email">邮箱:</label>
          <input type="email" id="email" placeholder="your@email.com">
        </div>
        <div class="form-group">
          <label for="password">密码:</label>
          <input type="password" id="password" placeholder="密码">
        </div>
        <div class="form-group">
          <label for="token">Token (自动生成):</label>
          <input type="text" id="token" placeholder="将根据邮箱密码自动生成" readonly>
        </div>
        <div class="form-actions">
          <button type="button" id="generate-token" class="btn-primary">生成Token</button>
          <button type="submit" class="btn-success">保存设置</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// 时光机类 - 重构代码结构
class Timemachine {
    constructor() {
        this.loadStoredSettings();
        this.initEventListeners();
    }

    // 加载本地存储的设置
    loadStoredSettings() {
        const settings = localStorage.getItem('timemachine_settings');
        if (settings) {
            this.settings = JSON.parse(settings);
        } else {
            this.settings = {
                baseUrl: 'https://p.maxqi.top/api/v1',
                email: '',
                password: '',
                token: ''
            };
        }
    }

    // 保存设置到本地存储
    saveSettings() {
        localStorage.setItem('timemachine_settings', JSON.stringify(this.settings));
    }

    // 初始化事件监听
    initEventListeners() {
        // 一键到底部按钮
        const goToPublisherBtn = document.getElementById('go-to-publisher');
        if (goToPublisherBtn) {
            goToPublisherBtn.addEventListener('click', () => {
                document.getElementById('timemachine-publisher').scrollIntoView({
                    behavior: 'smooth'
                });
            });
        }

        // 右下角浮动加号按钮
        const floatingAddBtn = document.getElementById('floating-add-btn');
        if (floatingAddBtn) {
            floatingAddBtn.addEventListener('click', () => {
                document.getElementById('timemachine-publisher').scrollIntoView({
                    behavior: 'smooth'
                });
                // 滚动后聚焦到文本框
                setTimeout(() => {
                    const textarea = document.getElementById('timemachine-textarea');
                    if (textarea) {
                        textarea.focus();
                    }
                }, 500);
            });
        }

        // 滚动显示/隐藏浮动按钮
        this.initScrollHandler();

        // 移除加载更多功能，现在使用传统分页导航

        // 设置Modal相关
        this.initSettingsModal();

        // 表单提交
        const form = document.getElementById('timemachine-form');
        if (form) {
            form.addEventListener('submit', this.handleFormSubmit.bind(this));
        }

        // 粘贴图片上传
        const textarea = document.getElementById('timemachine-textarea');
        if (textarea) {
            textarea.addEventListener('paste', this.handlePaste.bind(this));
        }

        // 检查提交成功状态
        <?php if ($commentSubmitted): ?>
        console.log('Debug: 提交成功，准备重定向...');
        console.log('Debug: 提交时间:', new Date().toLocaleString());
        setTimeout(() => {
            console.log('Debug: 执行重定向到:', window.location.pathname);
            window.location.href = window.location.pathname;
        }, 1000);
        <?php endif; ?>
    }

    // 初始化滚动处理
    initScrollHandler() {
        const floatingAddBtn = document.getElementById('floating-add-btn');
        if (!floatingAddBtn) return;

        let ticking = false;

        const updateButtonVisibility = () => {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const windowHeight = window.innerHeight;
            const documentHeight = document.documentElement.scrollHeight;

            // 当滚动到页面高度的一半时显示按钮
            if (scrollTop > windowHeight * 0.5) {
                floatingAddBtn.classList.add('show');
            } else {
                floatingAddBtn.classList.remove('show');
            }

            // 当接近底部时隐藏按钮
            if (scrollTop + windowHeight > documentHeight - 100) {
                floatingAddBtn.classList.remove('show');
            }

            ticking = false;
        };

        const requestTick = () => {
            if (!ticking) {
                requestAnimationFrame(updateButtonVisibility);
                ticking = true;
            }
        };

        window.addEventListener('scroll', requestTick, { passive: true });

        // 初始检查
        updateButtonVisibility();
    }

    // 初始化设置Modal
    initSettingsModal() {
        const modal = document.getElementById('settings-modal');
        const openBtn = document.getElementById('open-settings');
        const closeBtn = modal.querySelector('.modal-close');
        const overlay = modal.querySelector('.modal-overlay');
        const form = document.getElementById('settings-form');
        const generateBtn = document.getElementById('generate-token');

        // 填充已保存的设置
        this.fillSettingsForm();

        openBtn.addEventListener('click', () => {
            modal.style.display = 'block';
        });

        [closeBtn, overlay].forEach(el => {
            el.addEventListener('click', () => {
                modal.style.display = 'none';
            });
        });

        generateBtn.addEventListener('click', this.generateToken.bind(this));
        form.addEventListener('submit', this.saveSettingsForm.bind(this));
    }

    // 填充设置表单
    fillSettingsForm() {
        document.getElementById('base-url').value = this.settings.baseUrl;
        document.getElementById('email').value = this.settings.email;
        document.getElementById('password').value = this.settings.password;
        document.getElementById('token').value = this.settings.token;
    }

    // 生成Token
    async generateToken() {
        const baseUrl = document.getElementById('base-url').value;
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;

        if (!baseUrl || !email || !password) {
            alert('请填写完整的图床地址、邮箱和密码');
            return;
        }

        const generateBtn = document.getElementById('generate-token');
        const originalText = generateBtn.textContent;
        generateBtn.textContent = '生成中...';
        generateBtn.disabled = true;

        try {
            const response = await fetch(`${baseUrl}/tokens`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ email, password })
            });

            const data = await response.json();

            if (data.status && data.data && data.data.token) {
                document.getElementById('token').value = data.data.token;
                alert('Token生成成功！');
            } else {
                throw new Error(data.message || '生成Token失败');
            }
        } catch (error) {
            console.error('生成Token失败:', error);
            alert('生成Token失败: ' + error.message);
        } finally {
            generateBtn.textContent = originalText;
            generateBtn.disabled = false;
        }
    }

    // 保存设置表单
    saveSettingsForm(e) {
        e.preventDefault();
        
        this.settings = {
            baseUrl: document.getElementById('base-url').value,
            email: document.getElementById('email').value,
            password: document.getElementById('password').value,
            token: document.getElementById('token').value
        };

        this.saveSettings();
        document.getElementById('settings-modal').style.display = 'none';
        alert('设置已保存！');
    }

    // 处理粘贴事件
    async handlePaste(e) {
        const items = e.clipboardData.items;
        
        for (let item of items) {
            if (item.type.indexOf('image') !== -1) {
                e.preventDefault();
                const file = item.getAsFile();
                await this.uploadImage(file);
                break;
            }
        }
    }

    // 上传图片
    async uploadImage(file) {
        if (!this.settings.token) {
            alert('请先配置图床设置！');
            return;
        }

        const progressEl = document.getElementById('upload-progress');
        const progressFill = progressEl.querySelector('.progress-fill');
        const progressText = progressEl.querySelector('.progress-text');
        const textarea = document.getElementById('timemachine-textarea');

        progressEl.style.display = 'block';
        progressText.textContent = '上传中...';

        try {
            const formData = new FormData();
            formData.append('file', file);

            const response = await fetch(`${this.settings.baseUrl}/upload`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.settings.token}`,
                    'Accept': 'application/json'
                },
                body: formData
            });

            const data = await response.json();

            if (data.status && data.data && data.data.links) {
                const imageUrl = data.data.links.url;
                const markdown = `![${data.data.name}](${imageUrl})`;
                
                // 插入到光标位置
                const cursorPos = textarea.selectionStart;
                const textBefore = textarea.value.substring(0, cursorPos);
                const textAfter = textarea.value.substring(textarea.selectionEnd);
                textarea.value = textBefore + markdown + textAfter;
                
                // 更新光标位置
                textarea.selectionStart = textarea.selectionEnd = cursorPos + markdown.length;
                textarea.focus();
                
                progressText.textContent = '上传成功！';
                setTimeout(() => {
                    progressEl.style.display = 'none';
                }, 1000);
            } else {
                throw new Error(data.message || '上传失败');
            }
        } catch (error) {
            console.error('上传失败:', error);
            progressText.textContent = '上传失败: ' + error.message;
            setTimeout(() => {
                progressEl.style.display = 'none';
            }, 3000);
        }
    }

    // 处理表单提交
    handleFormSubmit(e) {
        const textarea = document.getElementById('timemachine-textarea');
        const submitBtn = e.target.querySelector('.publish-btn');
        const form = e.target;

        // Debug: 打印表单数据
        const formToken = form.querySelector('input[name="_"]')?.value;
        console.log('Debug: 表单提交前数据:', {
            text: textarea.value,
            formToken: formToken,
            formAction: form.action,
            currentUrl: window.location.href,
            referer: document.referrer,
            timestamp: new Date().toISOString()
        });

        // 检查token是否为空或无效
        if (!formToken || formToken.trim() === '') {
            console.warn('Debug: Token为空，可能存在问题！');
            alert('Token验证失败，请刷新页面重试');
            e.preventDefault();
            return;
        }

        if (textarea.value.trim() === '') {
            e.preventDefault();
            alert('请输入说说内容！');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="loading-icon">
                <line x1="12" y1="2" x2="12" y2="6"></line>
                <line x1="12" y1="18" x2="12" y2="22"></line>
                <line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line>
                <line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line>
                <line x1="2" y1="12" x2="6" y2="12"></line>
                <line x1="18" y1="12" x2="22" y2="12"></line>
                <line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line>
                <line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line>
            </svg>
            发布中...
        `;
    }

    // 移除了加载更多功能和相关方法，现在使用传统分页导航

    // 简单的相对时间计算
    getTimeAgo(timestamp) {
        const now = Math.floor(Date.now() / 1000);
        const diff = now - timestamp;
        
        if (diff < 3600) {
            return Math.floor(diff / 60) + ' 分钟前';
        } else if (diff < 86400) {
            return Math.floor(diff / 3600) + ' 小时前';
        } else if (diff < 2592000) {
            return Math.floor(diff / 86400) + ' 天前';
        } else {
            return new Date(timestamp * 1000).getFullYear() + '年' + 
                   (new Date(timestamp * 1000).getMonth() + 1) + '月' + 
                   new Date(timestamp * 1000).getDate() + '日';
        }
    }

    // 更新所有时间显示为本地时区
    updateTimeDisplay() {
        const timeElements = document.querySelectorAll('.time-ago');
        timeElements.forEach(element => {
            const timestamp = parseInt(element.dataset.timestamp);
            if (timestamp) {
                element.textContent = this.getTimeAgo(timestamp);
            }
        });

        // 更新时间元素
        const timeElements2 = document.querySelectorAll('.publish-time');
        timeElements2.forEach(element => {
            const timestamp = parseInt(element.dataset.timestamp);
            if (timestamp) {
                const date = new Date(timestamp * 1000);
                element.textContent = date.toLocaleString('zh-CN', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
        });
    }

    // 简单的客户端Markdown渲染（支持图片）
    renderSimpleMarkdown(text) {
        return text.replace(/&/g, '&amp;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;')
                  .replace(/"/g, '&quot;')
                  .replace(/'/g, '&#x27;')
                  .replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1" class="timemachine-image" loading="lazy">')
                  .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                  .replace(/(?<!\*)\*(.*?)\*(?!\*)/g, '<em>$1</em>')
                  .replace(/`([^`]+?)`/g, '<code>$1</code>')
                  .replace(/\[([^\]]*)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>')
                  .replace(/\n/g, '<br>');
    }
}

// 初始化时光机
document.addEventListener('DOMContentLoaded', function() {
    const timemachine = new Timemachine();
    // 初始化时更新所有时间显示
    timemachine.updateTimeDisplay();
});
</script>


<?php $this->need('footer.php'); ?>
