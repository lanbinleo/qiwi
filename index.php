<?php
/**
 * Qiwi Theme - 首页
 *
 * @package Qiwi
 * @author MaxQi
 * @version 1.2.7
 * @link http://mura.ink
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');

// 获取当前页码和每页文章数
$currentPage = $this->_currentPage;
$pageSize = $this->parameter->pageSize;
$postsToDisplay = [];
$hasContent = false;
$sidebarMomentCount = function_exists('qiwiGetPositiveIntOption') ? qiwiGetPositiveIntOption($this, 'sidebarMomentCount', 4, 1, 8) : 4;
$homeJikeData = $currentPage == 1 ? qiwiGetHomepageJikeData($sidebarMomentCount) : null;
$hasHomeJike = !empty($homeJikeData['items']);
$jikePosition = $hasHomeJike ? ($this->options->jikePosition ?: 'sidebar') : 'off';
if (in_array($jikePosition, ['top', 'inline'], true)) {
    $jikePosition = 'sidebar';
}
$jikeTimeMode = $hasHomeJike ? ($this->options->jikeTimeMode ?: 'absolute') : 'absolute';
if ($jikePosition === 'off') $hasHomeJike = false;

$showHomeHero = $currentPage == 1;
$homeHeroItems = $showHomeHero ? qiwiGetHomeHeroItems($this) : [];
$homeHeroMode = $this->options->homeHeroHitokotoMode ?: 'list';
if (!in_array($homeHeroMode, ['list', 'loop-hitokoto', 'hitokoto-after-list'], true)) {
    $homeHeroMode = 'list';
}
$homeHeroEyebrow = trim((string) ($this->options->homeHeroEyebrow ?: '写作 · 技术 · 生活 · 随笔'));
$homeHeroQuote = trim((string) ($this->options->homeHeroQuote ?: $this->options->aboutBio));
$homeHeroSwitchInterval = function_exists('qiwiGetPositiveIntOption') ? qiwiGetPositiveIntOption($this, 'homeHeroSwitchInterval', 5200, 1500, 30000) : 5200;
$homeHeroTypingSpeed = function_exists('qiwiGetPositiveIntOption') ? qiwiGetPositiveIntOption($this, 'homeHeroTypingSpeed', 92, 20, 500) : 92;
$homeHeroDeletingSpeed = function_exists('qiwiGetPositiveIntOption') ? qiwiGetPositiveIntOption($this, 'homeHeroDeletingSpeed', 24, 10, 300) : 24;
$homeHeroTypingPause = function_exists('qiwiGetPositiveIntOption') ? qiwiGetPositiveIntOption($this, 'homeHeroTypingPause', 220, 0, 3000) : 220;
$homeHeroAnimation = $this->options->homeHeroAnimation ?: 'fade';
if (!in_array($homeHeroAnimation, ['fade', 'typewriter'], true)) {
    $homeHeroAnimation = 'fade';
}
$homeHeroInitial = !empty($homeHeroItems) ? $homeHeroItems[0] : ['html' => '', 'text' => ''];
$homeHeroJson = json_encode($homeHeroItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$aboutPageUrl = function_exists('qiwiGetCustomPageUrl') ? qiwiGetCustomPageUrl($this, ['page-about.php', 'page-about']) : '';
if ($aboutPageUrl === '' && function_exists('qiwiGetPageUrlBySlug')) {
    $aboutPageUrl = qiwiGetPageUrlBySlug($this, ['about']);
}

if ($currentPage == 1) {
    // === 首页：显示所有置顶文章 + 补充非置顶文章 ===

    // 1. 查询所有置顶文章（使用数据库直接查询）
    $db = Typecho_Db::get();

    // 查询置顶文章的 CID
    $stickyQuery = $db->select('table.fields.cid')->from('table.fields')
        ->where('table.fields.name = ?', 'isSticky')
        ->where('table.fields.str_value = ?', '1');

    $stickyResult = $db->fetchAll($stickyQuery);
    $stickyCids = array_column($stickyResult, 'cid');

    // 2. 分别收集置顶和非置顶文章
    $stickyPosts = [];
    $normalPosts = [];

    while($this->next()) {
        $post = [
            'widget' => clone $this,
            'isSticky' => in_array($this->cid, $stickyCids)
        ];

        if ($post['isSticky']) {
            $stickyPosts[] = $post;
        } else {
            $normalPosts[] = $post;
        }
    }

    // 3. 如果当前页的置顶文章不够，从数据库查询剩余的置顶文章
    if (!empty($stickyCids)) {
        $currentStickyCids = array_column($stickyPosts, 'widget');
        $currentStickyCids = array_map(function($w) { return $w->cid; }, $currentStickyCids);
        $missingStickyCids = array_diff($stickyCids, $currentStickyCids);

        if (!empty($missingStickyCids)) {
            // 查询缺失的置顶文章
            $missingSelect = $this->select()->where('table.contents.cid IN ?', $missingStickyCids)
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.status = ?', 'publish')
                ->order('table.contents.created', Typecho_Db::SORT_DESC);

            $missingQuery = $db->fetchAll($missingSelect);

            // 为每篇缺失的文章创建 widget 对象
            foreach ($missingQuery as $postData) {
                $widget = clone $this;
                foreach ($postData as $key => $value) {
                    $widget->$key = $value;
                }

                // 手动查询并加载自定义字段
                $fieldsQuery = $db->select()->from('table.fields')
                    ->where('cid = ?', $postData['cid']);
                $fieldsData = $db->fetchAll($fieldsQuery);

                // 创建 fields 对象
                $fields = new stdClass();
                foreach ($fieldsData as $field) {
                    $fieldName = $field['name'];
                    $fieldValue = $field['str_value'] ? $field['str_value'] : $field['int_value'];
                    $fields->$fieldName = $fieldValue;
                }

                $widget->fields = $fields;

                $stickyPosts[] = [
                    'widget' => $widget,
                    'isSticky' => true
                ];
            }
        }
    }

    // 4. 对置顶和非置顶分别排序
    usort($stickyPosts, function($a, $b) {
        return $b['widget']->created - $a['widget']->created;
    });
    usort($normalPosts, function($a, $b) {
        return $b['widget']->created - $a['widget']->created;
    });

    // 5. 合并：置顶在前，总数不超过 pageSize
    $stickyCount = count($stickyPosts);
    $normalCount = max(0, $pageSize - $stickyCount);

    $postsToDisplay = array_merge($stickyPosts, array_slice($normalPosts, 0, $normalCount));
    $hasContent = !empty($postsToDisplay);

} else {
    // === 第2页及以后：只显示非置顶文章 ===

    // 查询所有置顶文章的 CID
    $db = Typecho_Db::get();
    $stickyQuery = $db->select('table.fields.cid')->from('table.fields')
        ->where('table.fields.name = ?', 'isSticky')
        ->where('table.fields.str_value = ?', '1');

    $stickyResult = $db->fetchAll($stickyQuery);
    $stickyCids = array_column($stickyResult, 'cid');

    while($this->next()) {
        // 过滤掉置顶文章
        if (!in_array($this->cid, $stickyCids)) {
            $postsToDisplay[] = [
                'widget' => clone $this,
                'isSticky' => false
            ];
            $hasContent = true;
        }
    }
}
?>

<?php if ($showHomeHero): ?>
<section class="home-hero" data-home-hero data-home-hero-mode="<?php echo htmlspecialchars($homeHeroMode, ENT_QUOTES, 'UTF-8'); ?>" data-home-hero-animation="<?php echo htmlspecialchars($homeHeroAnimation, ENT_QUOTES, 'UTF-8'); ?>" data-home-hero-interval="<?php echo (int) $homeHeroSwitchInterval; ?>" data-home-hero-typing-speed="<?php echo (int) $homeHeroTypingSpeed; ?>" data-home-hero-deleting-speed="<?php echo (int) $homeHeroDeletingSpeed; ?>" data-home-hero-typing-pause="<?php echo (int) $homeHeroTypingPause; ?>" data-home-hero-items="<?php echo htmlspecialchars($homeHeroJson, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="home-hero-inner">
        <?php if ($homeHeroEyebrow !== ''): ?>
            <div class="home-hero-eyebrow"><?php echo htmlspecialchars($homeHeroEyebrow, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <h1 class="home-hero-title" aria-live="polite">
            <span class="home-hero-line"><?php echo $homeHeroInitial['html']; ?></span>
        </h1>
        <?php if ($homeHeroQuote !== ''): ?>
            <p class="home-hero-quote"><?php echo htmlspecialchars($homeHeroQuote, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <div class="home-hero-actions">
            <a class="home-hero-button home-hero-button-primary" href="#all-posts">浏览文章</a>
            <?php if ($aboutPageUrl !== ''): ?>
                <a class="home-hero-button home-hero-button-secondary" href="<?php echo htmlspecialchars($aboutPageUrl, ENT_QUOTES, 'UTF-8'); ?>">关于我</a>
            <?php endif; ?>
        </div>
        <a class="home-hero-scroll" href="#all-posts" aria-label="跳到全部文章"></a>
    </div>
</section>
<?php endif; ?>

<div class="main-layout home-main-layout<?php echo ($showHomeHero && $hasContent) ? ' has-home-section-header' : ''; ?>">
    <!-- 左侧留白 -->
    <div class="layout-spacer-left"></div>

    <!-- 主要内容 -->
    <div class="main-content">
        <?php if ($hasContent): ?>
        <?php if ($showHomeHero): ?>
        <header class="home-section-header" id="all-posts">
            <div class="home-section-eyebrow">文章列表</div>
            <h2>最近文章</h2>
        </header>
        <?php endif; ?>
        <ul class="article-list">
            <?php
            // 显示文章
            foreach ($postsToDisplay as $postData) {
                $post = $postData['widget'];
                // 将当前文章数据复制到 $this 的属性中
                foreach (get_object_vars($post) as $key => $value) {
                    $this->$key = $value;
                }
                $this->need('post-card.php');
            }
            ?>
        </ul>

        <!-- 分页导航 -->
        <?php if ($this->getTotal() > $this->parameter->pageSize): ?>
        <div class="pagination-wrapper">
            <?php $this->pageNav('« 上一页', '下一页 »', 3, '...', [
                'wrapTag' => 'nav',
                'wrapClass' => 'page-navigator',
                'itemTag' => '',
                'textTag' => 'span',
                'currentClass' => 'current',
                'prevClass' => 'prev',
                'nextClass' => 'next'
            ]); ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty-posts">
            <div class="empty-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14,2 14,8 20,8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10,9 9,9 8,9"></polyline>
                </svg>
            </div>
            <h2>暂无文章</h2>
            <p>这里还没有任何文章，请稍后再来查看。</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- 侧边栏 -->
    <aside class="sidebar">
        <?php $this->homeJikeData = $homeJikeData; ?>
        <?php $this->homeJikeTimeMode = $jikeTimeMode; ?>
        <?php $this->homeJikePosition = $jikePosition; ?>
        <?php $this->need('sidebar.php'); ?>
    </aside>

    <!-- 右侧留白 -->
    <div class="layout-spacer-right"></div>
</div>

<?php $this->need('footer.php'); ?>
