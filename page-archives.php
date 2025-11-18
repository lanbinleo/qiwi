<?php
/**
 * 归档页面
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');

// 获取数据库
$db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
$prefix = $db->getPrefix();

// 获取所有已发布的文章，按时间倒序
$select = $db->select()->from($prefix.'contents')
    ->where('type = ?', 'post')
    ->where('status = ?', 'publish')
    ->order('created', $db::SORT_DESC);

$posts = $db->fetchAll($select);

// 按年份分组
$postsByYear = [];
foreach ($posts as $post) {
    $year = date('Y', $post['created']);
    if (!isset($postsByYear[$year])) {
        $postsByYear[$year] = [];
    }
    $postsByYear[$year][] = $post;
}

// 统计总文章数
$totalPosts = count($posts);

// 计算字数的函数
function getWordCount($text) {
    // 移除HTML标签
    $text = strip_tags($text);
    // 统计中文字符
    preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $text, $matches);
    $chineseCount = count($matches[0]);
    // 统计英文单词
    $text = preg_replace('/[\x{4e00}-\x{9fa5}]/u', '', $text);
    $englishCount = str_word_count($text);

    return $chineseCount + $englishCount;
}
?>

<div class="main-layout">
    <!-- 左侧留白 -->
    <div class="layout-spacer-left"></div>

    <!-- 主要内容 -->
    <div class="main-content">
        <header class="archive-header">
            <h1 class="archive-title"><?php $this->title(); ?></h1>
            <?php if ($this->content()): ?>
                <div class="archive-description"><?php $this->content(); ?></div>
            <?php endif; ?>
            <div class="archive-stats">
                <span class="stat-item">共 <?php echo $totalPosts; ?> 篇文章</span>
            </div>
        </header>

        <?php if (!empty($postsByYear)): ?>
        <div class="archives-timeline">
            <?php foreach ($postsByYear as $year => $yearPosts): ?>
            <div class="archive-year-section">
                <h2 class="year-title"><?php echo $year; ?></h2>
                <ul class="archive-post-list">
                    <?php foreach ($yearPosts as $post): ?>
                    <?php
                        $wordCount = getWordCount($post['text']);
                        $permalink = \Typecho\Router::url('post', [
                            'cid' => $post['cid'],
                            'slug' => $post['slug']
                        ], $this->options->index);
                    ?>
                    <li class="archive-post-item">
                        <time class="post-date"><?php echo date('m-d', $post['created']); ?></time>
                        <a href="<?php echo $permalink; ?>" class="post-title-link">
                            <?php echo htmlspecialchars($post['title']); ?>
                        </a>
                        <span class="post-wordcount"><?php echo number_format($wordCount); ?> 字</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-posts">
            <h2>暂无文章</h2>
            <p>还没有发布任何文章。</p>
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
