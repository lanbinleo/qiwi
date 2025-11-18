<?php
/**
 * 归档页面
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');

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

// === 写作统计数据 ===
// 计算总字数
$totalWords = 0;
foreach ($posts as $post) {
    $totalWords += getWordCount($post['text']);
}

// 计算写作天数（从第一篇文章到现在）
$writingDays = 0;
if (!empty($posts)) {
    $firstPostTime = end($posts)['created']; // 数组已按时间倒序，最后一个是最早的
    $writingDays = floor((time() - $firstPostTime) / 86400); // 86400秒 = 1天
}

// 解析书籍参考配置
$bookName = '';
$bookWords = 0;
$bookEquivalent = 0;
if ($this->options->bookReference) {
    $parts = array_map('trim', explode(',', $this->options->bookReference));
    if (count($parts) === 2) {
        $bookName = $parts[0];
        $bookWords = intval($parts[1]);
        if ($bookWords > 0) {
            $bookEquivalent = round($totalWords / $bookWords, 2);
        }
    }
}

// 计算距离下一阶段
$milestones = [100, 1000, 10000, 20000, 50000, 80000, 100000, 120000, 150000, 180000, 200000, 300000, 500000];
$nextMilestone = null;
foreach ($milestones as $milestone) {
    if ($totalWords < $milestone) {
        $nextMilestone = $milestone;
        break;
    }
}
$wordsToNext = $nextMilestone ? $nextMilestone - $totalWords : 0;
?>

<div class="main-layout">
    <!-- 左侧留白 -->
    <div class="layout-spacer-left"></div>

    <!-- 主要内容 -->
    <div class="main-content">
        <header class="archive-header">
            <h1 class="archive-title"><?php $this->title(); ?></h1>

            <div class="archive-stats">
                <span class="stat-item">共 <?php echo $totalPosts; ?> 篇文章</span>
            </div>
            
        </header>


        <!-- 写作统计区域 -->
        <div class="writing-stats">
            <ul class="stats-list">
                <li class="stats-item">
                    已写作 <span class="stats-highlight"><?php echo number_format($writingDays); ?></span> 天
                </li>
                <li class="stats-item">
                    共 <span class="stats-highlight"><?php echo number_format($totalWords); ?></span> 字
                </li>
                <?php if ($bookEquivalent > 0): ?>
                <li class="stats-item">
                    相当于 <span class="stats-highlight"><?php echo $bookEquivalent; ?></span> 本<?php echo htmlspecialchars($bookName); ?>
                </li>
                <?php endif; ?>
                <?php if ($nextMilestone): ?>
                <li class="stats-item">
                    距离下一个里程碑（<?php echo number_format($nextMilestone); ?> 字）还有 <span class="stats-highlight"><?php echo number_format($wordsToNext); ?></span> 字
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <br>

        <?php if ($this->content()): ?>
            <div class="archive-description"><?php $this->content(); ?></div>
        <?php endif; ?>

        <br>

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
