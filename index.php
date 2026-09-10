<?php
/**
 * Qiwi Theme - 首页
 *
 * @package Qiwi
 * @author Leo
 * @version 2.1.4
 * @link https://bboreo.com
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');

// 获取当前页码和每页文章数
$currentPage = $this->_currentPage;
$pageSize = $this->parameter->pageSize;
$postsToDisplay = [];
$hasContent = false;
$sidebarMomentCount = function_exists('qiwiGetPositiveIntOption') ? qiwiGetPositiveIntOption($this, 'sidebarMomentCount', 4, 1, 8) : 4;
$homeJikeData = function_exists('qiwiGetHomepageJikeData') ? qiwiGetHomepageJikeData($sidebarMomentCount) : null;
$hasHomeJike = !empty($homeJikeData['items']);

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
                ->where('table.contents.created < ?', $this->options->time)
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

                if (function_exists('qiwiGetContentVisibility') && !qiwiGetContentVisibility($widget, 'home')) {
                    continue;
                }

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

    // 5. 合并：置顶全量在前，非置顶补足剩余名额；置顶数超过 pageSize 时首页总数以置顶数为准
    $stickyCount = count($stickyPosts);
    $normalCount = max(0, $pageSize - $stickyCount);

    $postsToDisplay = array_merge($stickyPosts, array_slice($normalPosts, 0, $normalCount));
    $hasContent = !empty($postsToDisplay);

} else {
    // === 第 2 页及以后：只显示非置顶文章，并补偿首页被置顶挤占的名额 ===
    // 首页展示了全部 S 篇置顶 + 自然序前 (pageSize - S) 篇非置顶；若第 k 页仍从自然序
    // 第 (k-1)*pageSize 篇开始，就会有 S 篇非置顶文章永远不在首页列表出现。
    // 因此非置顶流在第 k 页的起点应为 (k-1)*pageSize - S。

    $db = Typecho_Db::get();
    $stickyQuery = $db->select('table.fields.cid')->from('table.fields')
        ->where('table.fields.name = ?', 'isSticky')
        ->where('table.fields.str_value = ?', '1');
    $stickyCids = array_map('intval', array_column($db->fetchAll($stickyQuery), 'cid'));

    // S 只统计首页真正展示的置顶：已发布、非定时、且首页可见（与 Widget 查询同口径）。
    $stickyCount = 0;
    if (!empty($stickyCids)) {
        $stickyCountSelect = $db->select('table.contents.cid')->from('table.contents')
            ->where('table.contents.cid IN ?', $stickyCids)
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.created < ?', $this->options->time);
        if (function_exists('qiwiApplyContentVisibilityToArchiveSelect')) {
            qiwiApplyContentVisibilityToArchiveSelect($this, $stickyCountSelect);
        }
        $stickyCount = count($db->fetchAll($stickyCountSelect));
    }

    $normalOffset = max(0, ($currentPage - 1) * $pageSize - $stickyCount);
    $normalSelect = $this->select()
        ->where('table.contents.type = ?', 'post')
        ->where('table.contents.status = ?', 'publish')
        ->where('table.contents.created < ?', $this->options->time)
        ->order('table.contents.created', Typecho_Db::SORT_DESC)
        ->offset($normalOffset)
        ->limit($pageSize);
    if (!empty($stickyCids)) {
        $normalSelect->where('table.contents.cid NOT IN ?', $stickyCids);
    }
    if (function_exists('qiwiApplyContentVisibilityToArchiveSelect')) {
        qiwiApplyContentVisibilityToArchiveSelect($this, $normalSelect);
    }

    foreach ($db->fetchAll($normalSelect) as $postData) {
        $widget = clone $this;
        foreach ($postData as $key => $value) {
            $widget->$key = $value;
        }

        $fieldsData = $db->fetchAll($db->select()->from('table.fields')
            ->where('cid = ?', $postData['cid']));
        $fields = new stdClass();
        foreach ($fieldsData as $field) {
            $fieldName = $field['name'];
            $fields->$fieldName = $field['str_value'] ? $field['str_value'] : $field['int_value'];
        }
        $widget->fields = $fields;

        $postsToDisplay[] = [
            'widget' => $widget,
            'isSticky' => false
        ];
        $hasContent = true;
    }
}

$postStatsCids = array();
foreach ($postsToDisplay as $postData) {
    if (!empty($postData['widget']) && isset($postData['widget']->cid)) {
        $postStatsCids[] = (int) $postData['widget']->cid;
    }
}
if (function_exists('qiwiPrimePostStatsCache')) {
    qiwiPrimePostStatsCache($postStatsCids);
}
?>

<div class="main-layout home-main-layout">
    <!-- 左侧留白 -->
    <div class="layout-spacer-left"></div>

    <!-- 主要内容 -->
    <div class="main-content">
        <?php if ($currentPage == 1 && $hasHomeJike && !empty($homeJikeData['items'][0])): ?>
        <a class="latest-moment" aria-label="最近动态" data-latest-moment href="<?php echo htmlspecialchars($homeJikeData['permalink'], ENT_QUOTES, 'UTF-8'); ?>">
            <span class="dot" aria-hidden="true"></span>
            <span class="latest-moment-items">
                <?php foreach ($homeJikeData['items'] as $momentIndex => $latestMoment): ?>
                <b class="latest-moment-item<?php echo $momentIndex === 0 ? ' is-active' : ''; ?>" data-latest-moment-item aria-hidden="<?php echo $momentIndex === 0 ? 'false' : 'true'; ?>">
                    <time datetime="<?php echo htmlspecialchars($latestMoment['datetime'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($latestMoment['relative_date_label'], ENT_QUOTES, 'UTF-8'); ?></time>
                    <span aria-hidden="true"> · </span><?php echo isset($latestMoment['excerpt_html']) ? $latestMoment['excerpt_html'] : htmlspecialchars($latestMoment['excerpt'], ENT_QUOTES, 'UTF-8'); ?>
                </b>
                <?php endforeach; ?>
            </span>
        </a>
        <?php endif; ?>
        <?php if ($hasContent): ?>
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

    <!-- 右侧留白 -->
    <div class="layout-spacer-right"></div>
</div>

<?php $this->need('footer.php'); ?>
