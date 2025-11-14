<?php
/**
 * Qiwi Theme - 首页
 *
 * @package Qiwi
 * @author MaxQi
 * @version 1.1.6
 * @link http://mura.ink
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');

// 获取当前页码和每页文章数
$currentPage = $this->_currentPage;
$pageSize = $this->parameter->pageSize;
$postsToDisplay = [];
$hasContent = false;

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

<div class="main-layout">
    <!-- 左侧留白 -->
    <div class="layout-spacer-left"></div>

    <!-- 主要内容 -->
    <div class="main-content">
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

    <!-- 侧边栏 -->
    <aside class="sidebar">
        <?php $this->need('sidebar.php'); ?>
    </aside>

    <!-- 右侧留白 -->
    <div class="layout-spacer-right"></div>
</div>

<?php $this->need('footer.php'); ?>
