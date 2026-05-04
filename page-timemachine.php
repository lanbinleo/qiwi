<?php
/**
 * 时光机页面模板
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
$pageContent = qiwiGetContent($this);

// 获取数据
$pageId = $this->cid;
$authorUid = $this->author->uid;
$pageSize = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// 获取数据库
$db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
$prefix = $db->getPrefix();

// 查询说说（作者的评论）
$select = $db->select()->from($prefix.'comments')
    ->where('cid = ?', $pageId)
    ->where('status = ?', 'approved')
    ->where('authorId = ?', $authorUid)
    ->order('created', $db::SORT_DESC)
    ->page($currentPage, $pageSize);

$comments = $db->fetchAll($select);

// 获取总数
$totalResult = $db->fetchRow($db->select('COUNT(coid) AS total')
    ->from($prefix.'comments')
    ->where('cid = ?', $pageId)
    ->where('status = ?', 'approved')
    ->where('authorId = ?', $authorUid));

$total = $totalResult ? $totalResult['total'] : 0;
$totalPages = ceil($total / $pageSize);

// Markdown 渲染
function renderMarkdown($text) {
    if (empty($text)) return '';

    $lines = explode("\n", $text);
    $result = [];
    $inQuote = false;
    $quoteLines = [];

    foreach ($lines as $line) {
        if (preg_match('/^>\s?(.*)$/', $line, $matches)) {
            // 这是引用行
            if (!$inQuote) {
                $inQuote = true;
            }
            $quoteLines[] = $matches[1];
        } else {
            // 不是引用行
            if ($inQuote) {
                // 结束之前的引用块，对引用内容进行转义
                $quoteContent = htmlspecialchars(implode("\n", $quoteLines), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                // 保留换行，稍后统一处理
                $result[] = '<blockquote>' . $quoteContent . '</blockquote>';
                $quoteLines = [];
                $inQuote = false;
            }
            // 对非引用行进行转义
            $result[] = htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    // 处理最后可能还在引用中的情况
    if ($inQuote) {
        $quoteContent = htmlspecialchars(implode("\n", $quoteLines), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $result[] = '<blockquote>' . $quoteContent . '</blockquote>';
    }

    $text = implode("\n", $result);

    // 处理其他 Markdown 语法
    $text = preg_replace('/!\[([^\]]*)\]\(([^\)]+)\)/', '<img src="$2" alt="$1" class="moment-image" loading="lazy">', $text);
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/`([^`]+?)`/', '<code>$1</code>', $text);
    $text = preg_replace('/\[([^\]]*)\]\(([^\)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $text);
    $text = renderMomentAutolinks($text);
    if (function_exists('qiwiRenderShortcodes')) {
        $text = qiwiRenderShortcodes($text);
    }

    // 最后统一处理换行（包括引用块内部）
    return nl2br($text);
}

function renderMomentAutolinks($html) {
    $parts = preg_split('/(<a\b[\s\S]*?<\/a>|<code\b[\s\S]*?<\/code>|<img\b[^>]*>)/iu', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $index => $part) {
        if (preg_match('/^<(a|code|img)\b/iu', $part)) {
            continue;
        }

        $parts[$index] = preg_replace_callback('/((?:https?:\/\/|www\.)[a-z0-9][a-z0-9.-]*(?::\d+)?(?:\/[^\s<>"\'`，。！？；：、（）【】《》「」『』\x{3000}]*)?|(?:[a-z0-9-]+\.)+[a-z]{2,}(?:\/[^\s<>"\'`，。！？；：、（）【】《》「」『』\x{3000}]*)?)/iu', function ($matches) {
            $raw = $matches[0];
            $trailing = '';
            while (preg_match('/[.,!?;:，。！？；：、）)\]]$/u', $raw)) {
                $trailing = mb_substr($raw, -1, 1, 'UTF-8') . $trailing;
                $raw = mb_substr($raw, 0, mb_strlen($raw, 'UTF-8') - 1, 'UTF-8');
            }

            if ($raw === '') {
                return $matches[0];
            }

            $url = preg_match('/^https?:\/\//i', $raw) ? $raw : 'https://' . $raw;
            $decodedUrl = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $host = parse_url($decodedUrl, PHP_URL_HOST);
            if (!$host) {
                return $matches[0];
            }

            $label = renderMomentLinkDomain($host);
            $safeUrl = htmlspecialchars($decodedUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return '<a href="' . $safeUrl . '" target="_blank" rel="noopener">' . $safeLabel . '</a>' . $trailing;
        }, $part);
    }

    return implode('', $parts);
}

function renderMomentLinkDomain($host) {
    $host = preg_replace('/^www\./i', '', strtolower((string) $host));
    $parts = array_values(array_filter(explode('.', $host)));
    $count = count($parts);
    if ($count >= 3 && strlen($parts[$count - 1]) === 2 && strlen($parts[$count - 2]) <= 3) {
        return implode('.', array_slice($parts, -3));
    }
    if ($count >= 2) {
        return implode('.', array_slice($parts, -2));
    }
    return $host;
}
?>

<div class="timemachine-page">
    <!-- 左侧留白 -->
    <div class="layout-spacer-left"></div>

    <!-- 主内容 -->
    <div class="timemachine-main">
        <!-- 页面头部 -->
        <header class="timemachine-header">
            <h1 class="page-title"><?php $this->title(); ?></h1>
            <?php if (qiwiHasRenderedContent($pageContent)): ?>
                <div class="page-intro"><?php echo $pageContent; ?></div>
            <?php endif; ?>
            <div class="page-stats">
                <span class="stat-item">共 <?php echo $total; ?> 条记录</span>
            </div>
        </header>

        <!-- 说说列表 -->
        <?php if ($total > 0): ?>
        <div class="moments-list">
            <?php foreach ($comments as $index => $comment): ?>
            <article class="moment-item<?php if ($index === 0): ?> has-avatar<?php endif; ?>">
                <?php if ($index === 0): ?>
                <div class="moment-avatar">
                    <img src="<?php echo $this->options->aboutAvatar ?: 'https://gravatar.loli.net/avatar/default?s=96&d=mp'; ?>"
                         alt="avatar">
                </div>
                <?php else: ?>
                <div class="moment-avatar moment-avatar-placeholder" aria-hidden="true"></div>
                <?php endif; ?>
                <div class="moment-content">
                    <div class="moment-header">
                        <span class="moment-author"><?php $this->author->screenName(); ?></span>
                        <time class="moment-time"><?php echo date('Y-m-d H:i', $comment['created']); ?></time>
                    </div>
                    <div class="moment-text article-body">
                        <?php echo renderMarkdown($comment['text']); ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <!-- 分页 -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination-wrapper">
            <nav class="page-navigator">
                <?php if ($currentPage > 1): ?>
                    <a href="<?php echo $this->permalink . '?page=' . ($currentPage - 1); ?>">上一页</a>
                <?php endif; ?>

                <span class="current">第 <?php echo $currentPage; ?> / <?php echo $totalPages; ?> 页</span>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="<?php echo $this->permalink . '?page=' . ($currentPage + 1); ?>">下一页</a>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty-moments">
            <p>还没有任何记录，快来发布第一条吧！</p>
        </div>
        <?php endif; ?>

        <!-- 发布表单 -->
        <?php if ($this->user->hasLogin() && ($this->user->uid === $authorUid || $this->user->group === 'administrator')): ?>
        <div class="moment-publisher">
            <div class="publisher-header">
                <h3 class="publisher-title">发布新的记录</h3>
                <button id="open-settings" class="settings-btn" type="button" title="图床设置">
                    <i class="fa-solid fa-gear" aria-hidden="true"></i>
                    图床设置
                </button>
            </div>
            <?php if ($this->allow('comment')): ?>
            <form method="post" action="<?php $this->commentUrl(); ?>" class="publisher-form" id="moment-form">
                <div class="moment-editor" data-moment-editor>
                    <div class="moment-editor-toolbar" role="toolbar" aria-label="Markdown 工具栏">
                        <button type="button" class="moment-editor-tool" data-md-action="bold" title="粗体 Ctrl+B" aria-label="粗体"><i class="fa-solid fa-bold" aria-hidden="true"></i></button>
                        <button type="button" class="moment-editor-tool" data-md-action="italic" title="斜体 Ctrl+I" aria-label="斜体"><i class="fa-solid fa-italic" aria-hidden="true"></i></button>
                        <button type="button" class="moment-editor-tool" data-md-action="link" title="链接 Ctrl+K" aria-label="链接"><i class="fa-solid fa-link" aria-hidden="true"></i></button>
                        <button type="button" class="moment-editor-tool" data-md-action="image" title="图片" aria-label="图片"><i class="fa-regular fa-image" aria-hidden="true"></i></button>
                        <button type="button" class="moment-editor-tool" data-md-action="quote" title="引用" aria-label="引用"><i class="fa-solid fa-quote-left" aria-hidden="true"></i></button>
                        <button type="button" class="moment-editor-tool" data-md-action="code" title="行内代码 Ctrl+E" aria-label="行内代码"><i class="fa-solid fa-code" aria-hidden="true"></i></button>
                    </div>
                    <textarea name="text" id="moment-textarea" placeholder="想说些什么？支持 Markdown 语法和图片上传（粘贴图片自动上传）..." rows="6" required></textarea>
                </div>

                <!-- 上传进度条 -->
                <div class="upload-progress" id="upload-progress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <span class="progress-text">上传中...</span>
                </div>

                <input type="hidden" name="author" value="<?php echo htmlspecialchars($this->author->screenName); ?>">
                <input type="hidden" name="mail" value="<?php echo htmlspecialchars($this->author->mail); ?>">
                <input type="hidden" name="url" value="<?php echo htmlspecialchars($this->author->url); ?>">

                <?php
                $token = method_exists($this, 'security') ?
                    $this->security->getToken($this->permalink) :
                    $this->widget('Widget_Security')->getToken($this->permalink);
                echo '<input type="hidden" name="_" value="' . $token . '">';
                ?>

                <?php if ($this->options->enabledCaptcha): ?>
                <div class="captcha-script">
                    <div id="captcha"></div><?php Geetest_Plugin::commentCaptchaRender(); ?>
                    <script src="https://cdn.jsdelivr.net/npm/jquery@2.2.4/dist/jquery.min.js"></script>
                </div>
                <?php endif; ?>
                <br>

                <button type="submit" class="submit-button" id="sub_btn">发布</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 右侧留白 -->
    <div class="layout-spacer-right"></div>
</div>

<!-- 图床设置Modal -->
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
                    <label for="base-url">图床API地址</label>
                    <input type="url" id="base-url" placeholder="https://p.maxqi.top/api/v1" value="https://p.maxqi.top/api/v1">
                </div>
                <div class="form-group">
                    <label for="email">邮箱</label>
                    <input type="email" id="email" placeholder="your@email.com">
                </div>
                <div class="form-group">
                    <label for="password">密码</label>
                    <input type="password" id="password" placeholder="密码">
                </div>
                <div class="form-group">
                    <label for="token">Token（自动生成）</label>
                    <input type="text" id="token" placeholder="将根据邮箱密码自动生成" readonly>
                </div>
                <div class="form-actions">
                    <button type="button" id="generate-token" class="btn-secondary">生成Token</button>
                    <button type="submit" class="btn-primary">保存设置</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// 时光机图片上传功能
class TimemachineUploader {
    constructor() {
        this.loadStoredSettings();
        this.initEventListeners();
        this.initMarkdownEditor();
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
        // 设置Modal相关
        this.initSettingsModal();

        // 粘贴图片上传
        const textarea = document.getElementById('moment-textarea');
        if (textarea) {
            textarea.addEventListener('paste', this.handlePaste.bind(this));
        }
    }

    initMarkdownEditor() {
        const editor = document.querySelector('[data-moment-editor]');
        const textarea = document.getElementById('moment-textarea');
        if (!editor || !textarea) {
            return;
        }

        const wrapSelection = (before, after, placeholder) => {
            const start = textarea.selectionStart || 0;
            const end = textarea.selectionEnd || start;
            const selected = textarea.value.slice(start, end) || placeholder || '';
            const snippet = before + selected + after;
            textarea.focus();
            if (typeof textarea.setRangeText === 'function') {
                textarea.setRangeText(snippet, start, end, 'select');
            } else {
                textarea.value = textarea.value.slice(0, start) + snippet + textarea.value.slice(end);
                textarea.selectionStart = start;
                textarea.selectionEnd = start + snippet.length;
            }
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        };

        const prefixLines = (prefix) => {
            const start = textarea.selectionStart || 0;
            const end = textarea.selectionEnd || start;
            const value = textarea.value;
            const lineStart = value.lastIndexOf('\n', Math.max(0, start - 1)) + 1;
            const lineEnd = end < value.length ? value.indexOf('\n', end) : value.length;
            const safeLineEnd = lineEnd === -1 ? value.length : lineEnd;
            const block = value.slice(lineStart, safeLineEnd);
            const next = block.split('\n').map((line) => line.indexOf(prefix) === 0 ? line.slice(prefix.length) : prefix + line).join('\n');
            textarea.focus();
            if (typeof textarea.setRangeText === 'function') {
                textarea.setRangeText(next, lineStart, safeLineEnd, 'select');
            } else {
                textarea.value = value.slice(0, lineStart) + next + value.slice(safeLineEnd);
                textarea.selectionStart = lineStart;
                textarea.selectionEnd = lineStart + next.length;
            }
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        };

        const insertLink = () => {
            const selected = textarea.value.slice(textarea.selectionStart || 0, textarea.selectionEnd || textarea.selectionStart || 0);
            const href = window.prompt('链接 URL', selected && /^https?:\/\//i.test(selected) ? selected : 'https://');
            if (!href) return;
            wrapSelection('[', '](' + href + ')', selected && !/^https?:\/\//i.test(selected) ? selected : '链接文字');
        };

        const insertImage = () => {
            const src = window.prompt('图片 URL', 'https://');
            if (!src) return;
            wrapSelection('![', '](' + src + ')', '图片描述');
        };

        const runAction = (action) => {
            if (action === 'bold') wrapSelection('**', '**', '文字');
            if (action === 'italic') wrapSelection('*', '*', '文字');
            if (action === 'code') wrapSelection('`', '`', 'code');
            if (action === 'quote') prefixLines('> ');
            if (action === 'link') insertLink();
            if (action === 'image') insertImage();
        };

        editor.addEventListener('click', (event) => {
            const button = event.target.closest('[data-md-action]');
            if (!button) return;
            runAction(button.getAttribute('data-md-action'));
        });

        textarea.addEventListener('keydown', (event) => {
            const key = String(event.key || '').toLowerCase();
            const modifier = event.ctrlKey || event.metaKey;
            if (!modifier) return;
            if (key === 'b') {
                event.preventDefault();
                runAction('bold');
            } else if (key === 'i') {
                event.preventDefault();
                runAction('italic');
            } else if (key === 'k') {
                event.preventDefault();
                runAction('link');
            } else if (key === 'e') {
                event.preventDefault();
                runAction('code');
            } else if (key === 'enter') {
                event.preventDefault();
                const form = textarea.closest('form');
                if (form && typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else if (form) {
                    form.submit();
                }
            }
        });
    }

    // 初始化设置Modal
    initSettingsModal() {
        const modal = document.getElementById('settings-modal');
        const openBtn = document.getElementById('open-settings');
        if (!modal || !openBtn) {
            return;
        }

        const closeBtn = modal.querySelector('.modal-close');
        const overlay = modal.querySelector('.modal-overlay');
        const form = document.getElementById('settings-form');
        const generateBtn = document.getElementById('generate-token');
        if (!closeBtn || !overlay || !form || !generateBtn) {
            return;
        }

        // 填充已保存的设置
        this.fillSettingsForm();

        openBtn.addEventListener('click', () => {
            modal.style.display = 'block';
            setTimeout(() => modal.classList.add('show'), 10);
        });

        const closeModal = () => {
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 300);
        };

        closeBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', closeModal);

        generateBtn.addEventListener('click', this.generateToken.bind(this));
        form.addEventListener('submit', this.saveSettingsForm.bind(this));
    }

    // 填充设置表单
    fillSettingsForm() {
        const baseUrl = document.getElementById('base-url');
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        const token = document.getElementById('token');
        if (!baseUrl || !email || !password || !token) {
            return;
        }

        baseUrl.value = this.settings.baseUrl;
        email.value = this.settings.email;
        password.value = this.settings.password;
        token.value = this.settings.token;
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

        // 关闭Modal
        const modal = document.getElementById('settings-modal');
        modal.classList.remove('show');
        setTimeout(() => modal.style.display = 'none', 300);

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
        const textarea = document.getElementById('moment-textarea');
        if (!progressEl || !textarea) {
            return;
        }

        const progressFill = progressEl.querySelector('.progress-fill');
        const progressText = progressEl.querySelector('.progress-text');
        if (!progressFill || !progressText) {
            return;
        }

        progressEl.style.display = 'flex';
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
}

// 初始化上传器
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('moment-textarea') || document.getElementById('open-settings')) {
        const uploader = new TimemachineUploader();
    }
});
</script>

<?php $this->need('footer.php'); ?>
