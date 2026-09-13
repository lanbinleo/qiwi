<?php
/**
 * 归档页面
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');

// 计算可见文本字数
function getWordCount($text) {
    if (function_exists('qiwiCountReadableWords')) {
        return qiwiCountReadableWords($text);
    }

    $text = trim(strip_tags(html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
}

// 渲染格子时同时输出图例：左「少」右为口径短词（写作/到访），额外说明仅写作板块需要
if (!function_exists('qiwiArchivesRenderHeatmap')) {
    // 注意：$legendNote 按可信 HTML 原样输出（调用方拼色点标记），不接受任何用户输入
    function qiwiArchivesRenderHeatmap(array $cells, $ariaLabel, $legendTone, $legendMetric, $legendNote, $kind, array $daysRaw = [])
    {
        $todayTs = strtotime(date('Y-m-d 00:00:00'));
        $weekday = (int) date('N', $todayTs); // 1（周一）.. 7（周日）
        $weekStartTs = $todayTs - ($weekday - 1) * 86400;
        $columnCount = 53;
        $gridStartTs = $weekStartTs - ($columnCount - 1) * 7 * 86400;

        $monthLabels = '';
        $prevMonth = '';
        for ($i = 0; $i < $columnCount; $i++) {
            $columnTs = $gridStartTs + $i * 7 * 86400;
            $month = date('n', $columnTs);
            if ($i > 0 && $month !== $prevMonth) {
                $monthLabels .= '<span style="grid-column:' . ($i + 1) . '">' . $month . '月</span>';
            }
            $prevMonth = $month;
        }

        $daysJson = htmlspecialchars(json_encode($daysRaw, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

        echo '<div class="qiwi-heatmap" data-hm-kind="' . htmlspecialchars($kind, ENT_QUOTES, 'UTF-8') . '" data-hm-days="' . $daysJson . '" data-hm-view="day" role="img" aria-label="' . htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') . '">';
        echo '<div class="heatmap-scroll"><div class="heatmap-frame">';
        echo '<div class="heatmap-months" style="grid-template-columns:repeat(' . $columnCount . ',var(--hm-cell))">' . $monthLabels . '</div>';
        echo '<div class="heatmap-body">';
        echo '<div class="heatmap-weekdays"><span>一</span><span></span><span>三</span><span></span><span>五</span><span></span><span>日</span></div>';
        echo '<div class="heatmap-weeks">';
        for ($i = 0; $i < $columnCount; $i++) {
            $columnTs = $gridStartTs + $i * 7 * 86400;
            echo '<div class="heatmap-col">';
            for ($row = 0; $row < 7; $row++) {
                $dayTs = $columnTs + $row * 86400;
                if ($dayTs > $todayTs) {
                    echo '<span class="hm-cell hm-future"></span>';
                    continue;
                }

                $dayKey = date('Y-m-d', $dayTs);
                $title = date('Y年n月j日', $dayTs);
                $classes = 'hm-cell hm-l0';
                if (isset($cells[$dayKey])) {
                    $level = max(0, min(4, (int) $cells[$dayKey]['level']));
                    if ($level > 0) {
                        $classes = 'hm-cell hm-l' . $level . ' hm-tone-' . $cells[$dayKey]['tone'];
                    }
                    if (!empty($cells[$dayKey]['title'])) {
                        $title = $cells[$dayKey]['title'];
                    }
                }
                echo '<span class="' . $classes . '" data-hm-day="' . $dayKey . '" title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"></span>';
            }
            echo '</div>';
        }
        echo '</div></div></div></div>';

        echo '<div class="heatmap-legend"><span class="heatmap-legend-scale">少';
        for ($level = 0; $level <= 4; $level++) {
            $class = 'hm-cell hm-l' . $level;
            if ($level > 0) {
                $class .= ' hm-tone-' . $legendTone;
            }
            echo '<span class="' . $class . '"></span>';
        }
        echo '<span class="heatmap-legend-metric">' . htmlspecialchars($legendMetric, ENT_QUOTES, 'UTF-8') . '</span></span>';
        if ($legendNote !== '') {
            echo '<span class="heatmap-legend-note">' . $legendNote . '</span>';
        }
        echo '</div></div>';
    }
}

// 获取数据库
$db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
$prefix = $db->getPrefix();

// 获取所有已发布的文章，按时间倒序（排除尚未到发布时间的定时文章）
$select = $db->select()->from($prefix.'contents')
    ->where('type = ?', 'post')
    ->where('status = ?', 'publish')
    ->where('created < ?', $this->options->time)
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
// 计算总字数（逐篇只算一次，时间线复用同一份结果）
$totalWords = 0;
$wordCounts = [];
foreach ($posts as $post) {
    $wordCount = getWordCount($post['text']);
    $wordCounts[(int) $post['cid']] = $wordCount;
    $totalWords += $wordCount;
}

// 统计时光机说说，和文章字数分开展示
$momentRows = $db->fetchAll($db->select('table.comments.text', 'table.comments.created')
    ->from('table.comments')
    ->join('table.contents', 'table.comments.cid = table.contents.cid')
    ->where('table.comments.status = ?', 'approved')
    ->where('table.comments.type = ?', 'comment')
    ->where('table.comments.authorId = table.contents.authorId')
    ->where('(table.comments.parent IS NULL OR table.comments.parent = ?)', 0)
    ->where('table.contents.type = ?', 'page')
    ->where('table.contents.status = ?', 'publish')
    ->where('(table.contents.template = ? OR table.contents.template = ?)', 'page-timemachine.php', 'page-timemachine'));
$totalMoments = count($momentRows);
$totalMomentWords = 0;
foreach ($momentRows as $moment) {
    $totalMomentWords += getWordCount(isset($moment['text']) ? $moment['text'] : '');
}

// === 写作热力图（文章与说说按天归档） ===
$heatmapDaysRaw = [];
foreach ($posts as $post) {
    $day = date('Y-m-d', $post['created']);
    if (!isset($heatmapDaysRaw[$day])) {
        $heatmapDaysRaw[$day] = ['p' => 0, 'm' => 0];
    }
    $heatmapDaysRaw[$day]['p']++;
}
foreach ($momentRows as $moment) {
    if (empty($moment['created'])) {
        continue;
    }
    $day = date('Y-m-d', $moment['created']);
    if (!isset($heatmapDaysRaw[$day])) {
        $heatmapDaysRaw[$day] = ['p' => 0, 'm' => 0];
    }
    $heatmapDaysRaw[$day]['m']++;
}

// === 私人随笔层（Obsidian「当天新建笔记数」，渲染只读插件缓存，零网络） ===
$hasObsidian = false;
if (class_exists('QiwiTheme_Obsidian')) {
    $obsidianStats = QiwiTheme_Obsidian::peekStats();
    if (is_array($obsidianStats) && !empty($obsidianStats['daily'])) {
        foreach ($obsidianStats['daily'] as $obsidianDay => $obsidianCount) {
            $obsidianDay = (string) $obsidianDay;
            $obsidianCount = (int) $obsidianCount;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $obsidianDay) || $obsidianCount <= 0) {
                continue;
            }
            if (!isset($heatmapDaysRaw[$obsidianDay])) {
                $heatmapDaysRaw[$obsidianDay] = ['p' => 0, 'm' => 0];
            }
            $heatmapDaysRaw[$obsidianDay]['o'] = $obsidianCount;
            $hasObsidian = true;
        }
    }
}
$writingToggleModes = [
    ['key' => 'all', 'label' => '全部'],
    ['key' => 'posts', 'label' => '文章'],
    ['key' => 'moments', 'label' => '说说'],
];
if ($hasObsidian) {
    $writingToggleModes = [
        ['key' => 'all', 'label' => '全部'],
        ['key' => 'blog', 'label' => '博客'],
        ['key' => 'posts', 'label' => '文章'],
        ['key' => 'moments', 'label' => '说说'],
        ['key' => 'obsidian', 'label' => '随笔'],
    ];
}

// 热力图窗口起点：本周周一往前推 52 周（与渲染函数同口径）
$heatmapWindowStart = date('Y-m-d', strtotime(date('Y-m-d 00:00:00')) - (((int) date('N') - 1) + 52 * 7) * 86400);
$pastYearActiveDays = 0;
foreach ($heatmapDaysRaw as $day => $info) {
    if ($info['p'] + $info['m'] + (isset($info['o']) ? $info['o'] : 0) > 0 && strcmp($day, $heatmapWindowStart) >= 0) {
        $pastYearActiveDays++;
    }
}

// 默认视图着色（深浅按当天条数，有文章用琥珀、纯说说用青、纯随笔用紫）
$heatmapCells = [];
foreach ($heatmapDaysRaw as $day => $info) {
    $obsidian = isset($info['o']) ? (int) $info['o'] : 0;
    $count = $info['p'] + $info['m'] + $obsidian;
    if ($count <= 0) {
        continue;
    }
    $parts = [];
    if ($info['p'] > 0) {
        $parts[] = '文章 ' . $info['p'];
    }
    if ($info['m'] > 0) {
        $parts[] = '说说 ' . $info['m'];
    }
    if ($obsidian > 0) {
        $parts[] = '随笔 ' . $obsidian;
    }
    $heatmapCells[$day] = [
        'level' => $count >= 4 ? 4 : $count,
        'tone' => $info['p'] > 0 ? 'post' : ($info['m'] > 0 ? 'moment' : 'obsidian'),
        'title' => date('Y年n月j日', strtotime($day . ' 00:00:00')) . ' · ' . implode(' · ', $parts),
    ];
}

// === 累计阅读（主题自带 views 字段，按文章访客去重，展示在读者板块） ===
$totalViews = 0;
$postCidSet = [];
foreach ($posts as $post) {
    $postCidSet[(int) $post['cid']] = true;
}
if (!empty($postCidSet)) {
    $viewsFieldName = function_exists('qiwiGetPostViewsFieldName') ? qiwiGetPostViewsFieldName() : 'qiwiViews';
    $viewRows = $db->fetchAll($db->select('cid', 'int_value', 'str_value')
        ->from($prefix . 'fields')
        ->where('name = ?', $viewsFieldName));
    foreach ($viewRows as $viewRow) {
        $cid = (int) $viewRow['cid'];
        if (!isset($postCidSet[$cid])) {
            continue;
        }
        $viewsValue = (isset($viewRow['int_value']) && $viewRow['int_value'] !== null)
            ? (int) $viewRow['int_value']
            : (int) $viewRow['str_value'];
        $totalViews += max(0, $viewsValue);
    }
}

// === 来访记录（Umami Share URL 只读联动） ===
// 页面渲染只读缓存（stale-while-revalidate）：缓存缺失/过期时不在渲染期拉取，
// 而是埋下异步刷新标记，由浏览器对插件刷新端点发 fire-and-forget 请求补数据。
$umamiApiBase = trim((string) $this->options->umamiApiBase);
$umamiShareId = trim((string) $this->options->umamiShareId);
$umamiReader = null;
$umamiNeedsRefresh = false;
$umamiRefreshEndpoint = '';
if ($umamiApiBase !== '' && $umamiShareId !== '' && class_exists('QiwiTheme_Plugin')) {
    if (function_exists('qiwiGetThemeActionEndpoint')) {
        $umamiRefreshEndpoint = qiwiGetThemeActionEndpoint('umami-refresh', $this->options);
    }
    if (method_exists('QiwiTheme_Plugin', 'peekUmamiReaderStats')) {
        $umamiPeek = QiwiTheme_Plugin::peekUmamiReaderStats();
        if ($umamiPeek !== null) {
            $umamiReader = $umamiPeek['payload'];
            $umamiNeedsRefresh = !$umamiPeek['fresh'];
        } else {
            $umamiNeedsRefresh = true;
        }
    }
}

// 来访格子：日视图固定「全部」口径，按 到访+浏览 合计分四档纯色着色（深浅即总量）
$readerCells = [];
$readerDaysRaw = [];
if (is_array($umamiReader) && !empty($umamiReader['daily'])) {
    $maxTraffic = 0;
    foreach ($umamiReader['daily'] as $info) {
        $maxTraffic = max($maxTraffic, (int) $info['visits'] + (int) $info['views']);
    }
    foreach ($umamiReader['daily'] as $day => $info) {
        $visits = (int) $info['visits'];
        $views = (int) $info['views'];
        $readerDaysRaw[$day] = ['v' => $visits, 'w' => $views];
        $traffic = $visits + $views;
        if ($traffic <= 0) {
            continue;
        }
        $readerCells[$day] = [
            'level' => max(1, min(4, (int) ceil($traffic * 4 / max(1, $maxTraffic)))),
            'tone' => 'reader',
            'title' => date('Y年n月j日', strtotime($day . ' 00:00:00')) . ' · 到访 ' . $visits . ' · 浏览 ' . $views,
        ];
    }
}

// 计算写作天数（从第一篇文章到现在）
$writingDays = 0;
if (!empty($posts)) {
    $firstPostTime = end($posts)['created']; // 数组已按时间倒序，最后一个是最早的
    $writingDays = floor((time() - $firstPostTime) / 86400); // 86400秒 = 1天
}

// 解析书籍参考配置（支持多本书）
$books = [];
if ($this->options->bookReference) {
    $bookEntries = array_map('trim', explode('&&', $this->options->bookReference));
    foreach ($bookEntries as $entry) {
        $parts = array_map('trim', explode(',', $entry));
        if (count($parts) === 2) {
            $bookName = $parts[0];
            $bookWords = intval($parts[1]);
            if ($bookWords > 0) {
                $bookEquivalent = number_format($totalWords / $bookWords, 2, '.', '');
                $books[] = [
                    'name' => $bookName,
                    'words' => $bookWords,
                    'equivalent' => $bookEquivalent
                ];
            }
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
$pageContent = qiwiGetContent($this);
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
                <?php if (!empty($books)): ?>
                <li class="stats-item book-roller-container">
                    相当于
                    <span class="book-number-roller">
                        <span class="roller-wrapper roller-wrapper-<?php echo count($books); ?>">
                            <?php foreach ($books as $book): ?>
                            <span class="roller-item"><?php echo $book['equivalent']; ?></span>
                            <?php endforeach; ?>
                        </span>
                    </span>
                    本
                    <span class="book-name-roller">
                        <span class="roller-wrapper roller-wrapper-<?php echo count($books); ?>">
                            <?php foreach ($books as $book): ?>
                            <span class="roller-item"><?php echo htmlspecialchars($book['name']); ?></span>
                            <?php endforeach; ?>
                        </span>
                    </span>
                </li>
                <?php endif; ?>
                <?php if ($nextMilestone): ?>
                <li class="stats-item">
                    距离下一个里程碑（<?php echo number_format($nextMilestone); ?> 字）还有 <span class="stats-highlight"><?php echo number_format($wordsToNext); ?></span> 字
                </li>
                <?php endif; ?>
                <?php if ($totalMoments > 0): ?>
                <li class="stats-item">除了文章外，还写了 <?php echo number_format($totalMoments); ?> 条说说<br>
                </li>
                <li class="stats-item">
                    共 <span class="stats-highlight"><?php echo number_format($totalMomentWords); ?></span> 字
                </li>
                <?php endif; ?>
            </ul>
        </div>

        <?php if (qiwiHasRenderedContent($pageContent)): ?>
            <div class="archive-description"><?php echo $pageContent; ?></div>
        <?php endif; ?>

        <?php if ($umamiNeedsRefresh && $umamiRefreshEndpoint !== ''): ?>
        <div class="hm-refresh-marker" data-hm-refresh-url="<?php echo htmlspecialchars($umamiRefreshEndpoint, ENT_QUOTES, 'UTF-8'); ?>" hidden></div>
        <?php endif; ?>

        <?php if (!empty($heatmapCells)): ?>
        <section class="archives-heatmap-section">
            <div class="heatmap-section-head">
                <h2 class="heatmap-section-title">写作经历<span class="heatmap-section-note">共 <?php echo (int) $pastYearActiveDays; ?> 天在写作</span></h2>
                <button type="button" class="hm-toggle" data-hm-toggle-kind="writing" data-hm-mode="all" hidden aria-label="切换热力图口径，当前：全部"><span class="hm-toggle-dot"></span><span class="hm-toggle-text">全部</span></button>
                <button type="button" class="hm-toggle hm-toggle-view" data-hm-toggle-kind="view" data-hm-mode="day" hidden aria-label="切换热力图视图，当前：日"><span class="hm-toggle-text">日</span></button>
            </div>
            <?php qiwiArchivesRenderHeatmap(
                $heatmapCells,
                '写作经历热力图（过去一年），共 ' . (int) $pastYearActiveDays . ' 天在写作',
                'post',
                '写作',
                '<span class="hm-dot hm-tone-post"></span>有文章 <span class="hm-dot hm-tone-moment"></span>仅说说' . ($hasObsidian ? ' <span class="hm-dot hm-tone-obsidian"></span>仅随笔' : ''),
                'writing',
                $heatmapDaysRaw
            ); ?>
        </section>
        <?php endif; ?>

        <?php if (is_array($umamiReader) && !empty($readerCells)): ?>
        <section class="archives-heatmap-section" data-hm-reader>
            <div class="heatmap-section-head">
                <h2 class="heatmap-section-title">来访记录<?php if ($totalViews > 0): ?><span class="heatmap-section-note">文章累计阅读 <?php echo number_format($totalViews); ?> 次</span><?php endif; ?></h2>
                <button type="button" class="hm-toggle" data-hm-toggle-kind="reader" data-hm-mode="combined" hidden aria-label="切换统计口径，当前：全部"><span class="hm-toggle-dot"></span><span class="hm-toggle-text">全部</span></button>
                <button type="button" class="hm-toggle hm-toggle-view" data-hm-toggle-kind="view" data-hm-mode="day" hidden aria-label="切换热力图视图，当前：日"><span class="hm-toggle-text">日</span></button>
            </div>
            <?php qiwiArchivesRenderHeatmap(
                $readerCells,
                '来访记录热力图（过去一年）',
                'reader',
                '来访',
                '<span class="hm-dot hm-tone-visitors"></span>访客 <span class="hm-dot hm-tone-views"></span>浏览',
                'reader',
                $readerDaysRaw
            ); ?>
            <div class="heatmap-summary">
                <p>过去一年 · 独立读者 <span class="hm-num"><?php echo number_format((int) $umamiReader['visitors']); ?></span> · 浏览 <span class="hm-num"><?php echo number_format((int) $umamiReader['pageviews']); ?></span> 次</p>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!empty($postsByYear)): ?>
        <div class="archives-timeline">
            <?php foreach ($postsByYear as $year => $yearPosts): ?>
            <div class="archive-year-section">
                <h2 class="year-title"><?php echo htmlspecialchars($year); ?></h2>
                <ul class="archive-post-list">
                    <?php foreach ($yearPosts as $post): ?>
                    <?php
                        $wordCount = isset($wordCounts[(int) $post['cid']]) ? $wordCounts[(int) $post['cid']] : 0;
                        $permalink = \Typecho\Router::url('post', [
                            'cid' => $post['cid'],
                            'slug' => $post['slug']
                        ], $this->options->index);
                    ?>
                    <li class="archive-post-item">
                        <time class="post-date"><?php echo date('m-d', $post['created']); ?></time>
                        <a href="<?php echo htmlspecialchars($permalink); ?>" class="post-title-link">
                            <?php echo $post['title']; ?>
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

    <!-- 右侧留白 -->
    <div class="layout-spacer-right"></div>
</div>

<script>
(function () {
    'use strict';

    function heatmapSectionOf(el) {
        for (var node = el; node && node.nodeType === 1; node = node.parentElement) {
            if (node.classList && node.classList.contains('archives-heatmap-section')) {
                return node;
            }
        }
        return null;
    }

    function gridOf(section) {
        return section ? section.querySelector('.qiwi-heatmap') : null;
    }

    function daysMapOf(grid) {
        if (!grid) {
            return null;
        }
        try {
            return JSON.parse(grid.getAttribute('data-hm-days') || '{}');
        } catch (error) {
            return null;
        }
    }

    function dayText(day) {
        var year = parseInt(day.slice(0, 4), 10);
        var month = parseInt(day.slice(5, 7), 10);
        var date = parseInt(day.slice(8, 10), 10);
        return year + '年' + month + '月' + date + '日';
    }

    function tipText(grid, day) {
        var data = daysMapOf(grid);
        var info = data ? data[day] : null;
        var kind = grid.getAttribute('data-hm-kind');
        var text = dayText(day);
        if (!info) {
            return text + ' · 无记录';
        }
        if (kind === 'reader') {
            return text + ' · 到访 ' + (info.v || 0) + ' · 浏览 ' + (info.w || 0);
        }
        var parts = [];
        if (info.p > 0) {
            parts.push('文章 ' + info.p);
        }
        if (info.m > 0) {
            parts.push('说说 ' + info.m);
        }
        if (info.o > 0) {
            parts.push('随笔 ' + info.o);
        }
        return parts.length > 0 ? text + ' · ' + parts.join(' · ') : text + ' · 无记录';
    }

    var tooltipEl = null;
    var hideTimer = 0;

    function ensureTooltip() {
        if (!tooltipEl || !tooltipEl.isConnected) {
            tooltipEl = document.createElement('div');
            tooltipEl.className = 'hm-tooltip';
            tooltipEl.setAttribute('aria-hidden', 'true');
            document.body.appendChild(tooltipEl);
        }
        return tooltipEl;
    }

    function showTooltip(grid, cell) {
        var tip = ensureTooltip();
        var custom = cell.getAttribute('data-hm-tip');
        tip.textContent = custom !== null && custom !== '' ? custom : tipText(grid, cell.getAttribute('data-hm-day'));
        tip.classList.add('is-visible');

        var rect = cell.getBoundingClientRect();
        var tipRect = tip.getBoundingClientRect();
        var margin = 8;
        var left = rect.left + rect.width / 2 - tipRect.width / 2;
        left = Math.max(margin, Math.min(left, window.innerWidth - tipRect.width - margin));
        var top = rect.top - tipRect.height - margin;
        if (top < margin) {
            top = rect.bottom + margin;
        }
        tip.style.left = Math.round(left) + 'px';
        tip.style.top = Math.round(top) + 'px';
    }

    function hideTooltip() {
        if (tooltipEl && tooltipEl.classList.contains('is-visible')) {
            tooltipEl.classList.remove('is-visible');
        }
    }

    // 写作口径：全部 / 博客（文章+说说）/ 只看文章 / 只看说说 / 只看随笔（与服务端着色同一套阈值）
    function applyFilter(section, mode) {
        var grid = gridOf(section);
        if (!grid || grid.getAttribute('data-hm-kind') !== 'writing') {
            return;
        }
        var data = daysMapOf(grid) || {};
        var cells = grid.querySelectorAll('.hm-cell[data-hm-day]');
        for (var i = 0; i < cells.length; i++) {
            var cell = cells[i];
            var info = data[cell.getAttribute('data-hm-day')];
            var posts = info ? (info.p || 0) : 0;
            var moments = info ? (info.m || 0) : 0;
            var obsidian = info ? (info.o || 0) : 0;
            var level = 0;
            var tone = 'post';
            if (mode === 'posts') {
                if (posts > 0) {
                    level = posts >= 4 ? 4 : posts;
                    tone = 'post';
                }
            } else if (mode === 'moments') {
                if (moments > 0) {
                    level = moments >= 4 ? 4 : moments;
                    tone = 'moment';
                }
            } else if (mode === 'blog') {
                var blogCount = posts + moments;
                if (blogCount > 0) {
                    level = blogCount >= 4 ? 4 : blogCount;
                    tone = posts > 0 ? 'post' : 'moment';
                }
            } else if (mode === 'obsidian') {
                if (obsidian > 0) {
                    level = obsidian >= 4 ? 4 : obsidian;
                    tone = 'obsidian';
                }
            } else {
                var count = posts + moments + obsidian;
                if (count > 0) {
                    level = count >= 4 ? 4 : count;
                    tone = posts > 0 ? 'post' : (moments > 0 ? 'moment' : 'obsidian');
                }
            }
            var classes = 'hm-cell hm-l' + level;
            if (level > 0) {
                classes += ' hm-tone-' + tone;
            }
            cell.className = classes;
        }
    }

    // 来访日视图：按当前口径纯色分四档（全部=访客+浏览合计、访客、浏览），与 PHP 着色同一套规则
    function applyReaderDay(section, mode) {
        var grid = gridOf(section);
        if (!grid || grid.getAttribute('data-hm-kind') !== 'reader') {
            return;
        }
        var data = daysMapOf(grid) || {};
        var field = mode === 'visits' ? 'v' : (mode === 'views' ? 'w' : null);
        var max = 0;
        for (var day in data) {
            if (Object.prototype.hasOwnProperty.call(data, day)) {
                var sum = field ? (data[day][field] || 0) : (data[day].v || 0) + (data[day].w || 0);
                max = Math.max(max, sum);
            }
        }
        var cells = grid.querySelectorAll('.hm-cell[data-hm-day]');
        for (var j = 0; j < cells.length; j++) {
            var cell = cells[j];
            var info = data[cell.getAttribute('data-hm-day')];
            var total = info ? (field ? (info[field] || 0) : (info.v || 0) + (info.w || 0)) : 0;
            var level = total > 0 ? Math.max(1, Math.min(4, Math.ceil(total * 4 / Math.max(1, max)))) : 0;
            cell.className = level > 0 ? 'hm-cell hm-l' + level + ' hm-tone-reader' : 'hm-cell hm-l0';
        }
        var metric = section.querySelector('.heatmap-legend-metric');
        if (metric) {
            metric.textContent = mode === 'visits' ? '访客' : (mode === 'views' ? '浏览' : '来访');
        }
    }

    /* ===== 周/累计柱状图视图：把一根 7 格的条当柱状图，自底向上按量填充 ===== */

    function escAttr(text) {
        return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function rangeText(startDay, endDay) {
        if (startDay === endDay) {
            return dayText(startDay);
        }
        var startYear = parseInt(startDay.slice(0, 4), 10);
        var startMonth = parseInt(startDay.slice(5, 7), 10);
        var startDayNum = parseInt(startDay.slice(8, 10), 10);
        var endYear = parseInt(endDay.slice(0, 4), 10);
        var endMonth = parseInt(endDay.slice(5, 7), 10);
        var endDayNum = parseInt(endDay.slice(8, 10), 10);
        var tail = '';
        if (endYear !== startYear) {
            tail += endYear + '年';
        }
        if (endMonth !== startMonth) {
            tail += endMonth + '月';
        }
        return startYear + '年' + startMonth + '月' + startDayNum + '日–' + tail + endDayNum + '日';
    }

    function currentViewOf(section) {
        var grid = gridOf(section);
        return grid ? (grid.getAttribute('data-hm-view') || 'day') : 'day';
    }

    function currentModeOf(section, kind) {
        var pill = section ? section.querySelector('.hm-toggle[data-hm-toggle-kind="' + kind + '"]') : null;
        var mode = pill ? pill.getAttribute('data-hm-mode') : '';
        if (kind === 'reader') {
            return mode === 'visits' || mode === 'views' ? mode : 'combined';
        }
        return mode || 'all';
    }

    // 日视图的 53 列星期一起始日在服务端算好，这里直接从格子 data-hm-day 收集，避免时区漂移
    function dayColumnsOf(grid) {
        if (grid.__qiwiDayColumns) {
            return grid.__qiwiDayColumns;
        }
        var columns = [];
        var colNodes = grid.querySelectorAll('.heatmap-weeks .heatmap-col');
        for (var i = 0; i < colNodes.length; i++) {
            var cells = colNodes[i].querySelectorAll('.hm-cell[data-hm-day]');
            var days = [];
            for (var j = 0; j < cells.length; j++) {
                days.push(cells[j].getAttribute('data-hm-day'));
            }
            columns.push(days);
        }
        grid.__qiwiDayColumns = columns;
        return columns;
    }

    // 首次离开日视图前缓存服务端渲染的标记，切回日视图时原样还原
    function stashDayMarkup(grid) {
        dayColumnsOf(grid);
        if (grid.__qiwiDayStash) {
            return;
        }
        var weeksEl = grid.querySelector('.heatmap-weeks');
        var railEl = grid.querySelector('.heatmap-weekdays');
        grid.__qiwiDayStash = {
            weeks: weeksEl.innerHTML,
            rail: railEl ? railEl.innerHTML : '',
            aria: grid.getAttribute('aria-label')
        };
    }

    function aggregateDays(data, days) {
        var agg = { p: 0, m: 0, o: 0, v: 0, w: 0 };
        for (var i = 0; i < days.length; i++) {
            var info = data[days[i]];
            if (!info) {
                continue;
            }
            agg.p += info.p || 0;
            agg.m += info.m || 0;
            agg.o += info.o || 0;
            agg.v += info.v || 0;
            agg.w += info.w || 0;
        }
        return agg;
    }

    function bucketValue(agg, kind, mode) {
        if (kind === 'reader') {
            if (mode === 'visits') {
                return agg.v;
            }
            if (mode === 'views') {
                return agg.w;
            }
            return agg.v + agg.w;
        }
        if (mode === 'posts') {
            return agg.p;
        }
        if (mode === 'moments') {
            return agg.m;
        }
        if (mode === 'obsidian') {
            return agg.o;
        }
        if (mode === 'blog') {
            return agg.p + agg.m;
        }
        return agg.p + agg.m + agg.o;
    }

    function bucketTone(agg, mode) {
        if (mode === 'posts') {
            return 'post';
        }
        if (mode === 'moments') {
            return 'moment';
        }
        if (mode === 'obsidian') {
            return 'obsidian';
        }
        if (mode === 'blog') {
            return agg.p > 0 ? 'post' : 'moment';
        }
        if (agg.p >= agg.m && agg.p >= agg.o) {
            return 'post';
        }
        return agg.m >= agg.o ? 'moment' : 'obsidian';
    }

    // 最大余数法把已填格分给各来源，自底向上按传入顺序排列（写作：文章→说说→随笔；来访：访客→浏览）
    function distributeSegments(cells, parts) {
        var total = 0;
        for (var i = 0; i < parts.length; i++) {
            total += parts[i].count;
        }
        if (cells <= 0 || total <= 0) {
            return [];
        }
        var floors = [];
        var remainders = [];
        var used = 0;
        for (var j = 0; j < parts.length; j++) {
            var raw = cells * parts[j].count / total;
            floors.push(Math.floor(raw));
            remainders.push(raw - Math.floor(raw));
            used += floors[j];
        }
        var order = [];
        for (var t = 0; t < parts.length; t++) {
            order.push(t);
        }
        order.sort(function (a, b) {
            return (remainders[b] - remainders[a]) || (a - b);
        });
        for (var left = cells - used, u = 0; u < left; u++) {
            floors[order[u]]++;
        }
        var segments = [];
        for (var s = 0; s < parts.length; s++) {
            if (floors[s] > 0) {
                segments.push({ tone: parts[s].tone, cells: floors[s] });
            }
        }
        return segments;
    }

    function bucketTip(kind, agg, label, cum) {
        if (kind === 'reader') {
            return label + ' · ' + (cum ? '累计到访 ' : '到访 ') + agg.v + ' · ' + (cum ? '累计浏览 ' : '浏览 ') + agg.w;
        }
        var prefix = cum ? '累计' : '';
        var parts = [];
        if (agg.p > 0) {
            parts.push(prefix + '文章 ' + agg.p);
        }
        if (agg.m > 0) {
            parts.push(prefix + '说说 ' + agg.m);
        }
        if (agg.o > 0) {
            parts.push(prefix + '随笔 ' + agg.o);
        }
        return parts.length > 0 ? label + ' · ' + parts.join(' · ') : label + ' · 无记录';
    }

    // 柱状视图左侧轨道：0–100 刻度（顶格 = 当前口径/视图下的满格值），50 落在 7 格正中那格
    var RAIL_SCALE_HTML = '<span class="hm-rail-num">100</span><span></span><span></span><span class="hm-rail-num">50</span><span></span><span></span><span class="hm-rail-num">0</span>';

    function alignHeatmap(section) {
        var scroll = section.querySelector('.heatmap-scroll');
        if (scroll && scroll.scrollWidth > scroll.clientWidth + 4) {
            scroll.scrollLeft = scroll.scrollWidth;
        }
    }

    function renderBars(section, view) {
        var grid = gridOf(section);
        if (!grid || (view !== 'week' && view !== 'cum')) {
            return;
        }
        var kind = grid.getAttribute('data-hm-kind');
        var data = daysMapOf(grid) || {};
        var mode = currentModeOf(section, kind);
        var columns = dayColumnsOf(grid);
        stashDayMarkup(grid);
        var stash = grid.__qiwiDayStash;

        // 周桶：值与成分按周聚合；累计视图再折叠为截至该周最后一天的累计值
        var buckets = [];
        var cum = { p: 0, m: 0, o: 0, v: 0, w: 0 };
        for (var w = 0; w < columns.length; w++) {
            var days = columns[w];
            var agg = aggregateDays(data, days);
            var bucket = { agg: agg, label: '' };
            if (view === 'cum') {
                cum.p += agg.p;
                cum.m += agg.m;
                cum.o += agg.o;
                cum.v += agg.v;
                cum.w += agg.w;
                bucket.agg = { p: cum.p, m: cum.m, o: cum.o, v: cum.v, w: cum.w };
                if (days.length > 0) {
                    bucket.label = '截至' + dayText(days[days.length - 1]);
                }
            } else if (days.length > 0) {
                bucket.label = rangeText(days[0], days[days.length - 1]);
            }
            buckets.push(bucket);
        }

        var totals = [];
        var maxTotal = 0;
        for (var b = 0; b < buckets.length; b++) {
            totals.push(bucketValue(buckets[b].agg, kind, mode));
            if (totals[b] > maxTotal) {
                maxTotal = totals[b];
            }
        }
        if (view === 'cum' && buckets.length > 0) {
            maxTotal = totals[buckets.length - 1]; // 累计视图满格 = 最终累计值
        }

        var colsHtml = '';
        for (var k = 0; k < buckets.length; k++) {
            var item = buckets[k];
            var total = totals[k];
            var level = total > 0 && maxTotal > 0 ? Math.max(1, Math.min(7, Math.ceil(total * 7 / maxTotal))) : 0;
            var segments = [];
            if (level > 0) {
                if (kind === 'reader') {
                    if (mode === 'visits') {
                        segments = [{ tone: 'visitors', cells: level }];
                    } else if (mode === 'views') {
                        segments = [{ tone: 'views', cells: level }];
                    } else {
                        segments = distributeSegments(level, [
                            { tone: 'visitors', count: item.agg.v },
                            { tone: 'views', count: item.agg.w }
                        ]);
                    }
                } else if (mode === 'all') {
                    segments = distributeSegments(level, [
                        { tone: 'post', count: item.agg.p },
                        { tone: 'moment', count: item.agg.m },
                        { tone: 'obsidian', count: item.agg.o }
                    ]);
                } else {
                    segments = [{ tone: bucketTone(item.agg, mode), cells: level }];
                }
            }
            var cellTones = [];
            for (var s = 0; s < segments.length; s++) {
                for (var r = 0; r < segments[s].cells; r++) {
                    cellTones.push(segments[s].tone);
                }
            }
            var tipAttr = ' data-hm-tip="' + escAttr(bucketTip(kind, item.agg, item.label, view === 'cum')) + '"';
            var colHtml = '';
            for (var row = 6; row >= 0; row--) {
                var tone = cellTones[row];
                if (tone) {
                    colHtml += '<span class="hm-cell hm-fill hm-tone-' + tone + ' hm-b' + level + '"' + tipAttr + '></span>';
                } else {
                    colHtml += '<span class="hm-cell"' + tipAttr + '></span>';
                }
            }
            colsHtml += '<div class="heatmap-col">' + colHtml + '</div>';
        }

        grid.querySelector('.heatmap-weeks').innerHTML = colsHtml;
        var railEl = grid.querySelector('.heatmap-weekdays');
        if (railEl) {
            railEl.innerHTML = RAIL_SCALE_HTML;
        }
        grid.setAttribute('data-hm-view', view);
        if (stash && stash.aria) {
            grid.setAttribute('aria-label', stash.aria + '，' + (view === 'week' ? '周' : '累计') + '视图');
        }
        alignHeatmap(section);
    }

    function replayMode(section) {
        var grid = gridOf(section);
        if (!grid) {
            return;
        }
        var kind = grid.getAttribute('data-hm-kind');
        if (kind === 'reader') {
            applyReaderDay(section, currentModeOf(section, kind));
        } else {
            applyFilter(section, currentModeOf(section, kind));
        }
    }

    function applyView(section, view) {
        var grid = gridOf(section);
        if (!grid) {
            return;
        }
        if (view === 'week' || view === 'cum') {
            renderBars(section, view);
            return;
        }
        var stash = grid.__qiwiDayStash;
        if (stash) {
            var railEl = grid.querySelector('.heatmap-weekdays');
            if (railEl) {
                railEl.innerHTML = stash.rail;
            }
            grid.querySelector('.heatmap-weeks').innerHTML = stash.weeks;
            if (stash.aria) {
                grid.setAttribute('aria-label', stash.aria);
            }
            grid.__qiwiDayStash = null;
        }
        grid.setAttribute('data-hm-view', 'day');
        replayMode(section);
        alignHeatmap(section);
    }

    var TOGGLE_MODES = {
        writing: <?php echo json_encode($writingToggleModes, JSON_UNESCAPED_UNICODE); ?>,
        reader: [
            { key: 'combined', label: '全部' },
            { key: 'visits', label: '访客' },
            { key: 'views', label: '浏览' }
        ],
        view: [
            { key: 'day', label: '日' },
            { key: 'week', label: '周' },
            { key: 'cum', label: '累' }
        ]
    };

    // PJAX 幂等：全局委托只绑一次，每次渲染只需初始化当前 DOM
    if (!window.__qiwiHeatmapBound) {
        window.__qiwiHeatmapBound = true;

        document.addEventListener('mouseover', function (event) {
            var cell = event.target.closest ? event.target.closest('.hm-cell[data-hm-day], .hm-cell[data-hm-tip]') : null;
            if (!cell) {
                return;
            }
            var section = heatmapSectionOf(cell);
            var grid = gridOf(section);
            if (grid) {
                showTooltip(grid, cell);
            }
        });
        document.addEventListener('mouseout', function (event) {
            if (event.target.closest && event.target.closest('.hm-cell[data-hm-day], .hm-cell[data-hm-tip]')) {
                hideTooltip();
            }
        });
        document.addEventListener('touchstart', function (event) {
            var cell = event.target.closest ? event.target.closest('.hm-cell[data-hm-day], .hm-cell[data-hm-tip]') : null;
            if (!cell) {
                return;
            }
            var section = heatmapSectionOf(cell);
            var grid = gridOf(section);
            if (!grid) {
                return;
            }
            showTooltip(grid, cell);
            clearTimeout(hideTimer);
            hideTimer = setTimeout(hideTooltip, 1600);
        }, { passive: true });
        window.addEventListener('scroll', hideTooltip, { passive: true });

        document.addEventListener('click', function (event) {
            var toggle = event.target.closest ? event.target.closest('.hm-toggle') : null;
            if (!toggle) {
                return;
            }
            var section = heatmapSectionOf(toggle);
            if (!section) {
                return;
            }
            var kind = toggle.getAttribute('data-hm-toggle-kind');
            var modes = TOGGLE_MODES[kind];
            if (!modes) {
                return;
            }
            var currentKey = toggle.getAttribute('data-hm-mode');
            var index = -1;
            for (var i = 0; i < modes.length; i++) {
                if (modes[i].key === currentKey) {
                    index = i;
                    break;
                }
            }
            var next = modes[(index + 1) % modes.length];
            toggle.setAttribute('data-hm-mode', next.key);
            var textEl = toggle.querySelector('.hm-toggle-text');
            if (textEl) {
                textEl.textContent = next.label;
            }
            toggle.setAttribute('aria-label', (kind === 'view' ? '切换热力图视图' : '切换统计口径') + '，当前：' + next.label);
            if (kind === 'view') {
                applyView(section, next.key);
            } else {
                if (kind === 'reader') {
                    applyReaderDay(section, next.key);
                } else {
                    applyFilter(section, next.key);
                }
                var view = currentViewOf(section);
                if (view !== 'day') {
                    renderBars(section, view);
                }
            }
        });
    }

    // JS 可用时：去掉原生 title（避免慢速系统 tooltip 与自定义浮层叠加），并放出切换按钮
    var grids = document.querySelectorAll('.archives-heatmap-section .qiwi-heatmap');
    for (var i = 0; i < grids.length; i++) {
        var cells = grids[i].querySelectorAll('.hm-cell[data-hm-day]');
        for (var j = 0; j < cells.length; j++) {
            cells[j].removeAttribute('title');
        }
    }
    var toggles = document.querySelectorAll('.archives-heatmap-section .hm-toggle');
    for (var k = 0; k < toggles.length; k++) {
        toggles[k].hidden = false;
    }
    // 内容宽于容器时默认滚到最右（最新的一周），左侧星期标签为 sticky 轨道
    var scrolls = document.querySelectorAll('.archives-heatmap-section .heatmap-scroll');
    for (var s = 0; s < scrolls.length; s++) {
        if (scrolls[s].scrollWidth > scrolls[s].clientWidth + 4) {
            scrolls[s].scrollLeft = scrolls[s].scrollWidth;
        }
    }

    // stale-while-revalidate：缓存缺失/过期时，后台异步打刷新端点，成功后原地更新
    var marker = document.querySelector('.hm-refresh-marker');
    if (marker) {
        fetch(marker.getAttribute('data-hm-refresh-url'), { credentials: 'same-origin' })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (json) {
                if (!json || !json.success || !json.data) {
                    return;
                }
                var section = document.querySelector('.archives-heatmap-section[data-hm-reader]');
                if (!section) {
                    return; // 本次渲染没有缓存可展示，刷新后的下次访问自然出现
                }
                var grid = section.querySelector('.qiwi-heatmap');
                // 端点返回 {visits,views}，页面热力图数据用短键 {v,w}，这里做一次映射，
                // 否则原地刷新后所有格子会因读到 0 而被清空。
                var incoming = json.data.daily || {};
                var mapped = {};
                Object.keys(incoming).forEach(function (day) {
                    var info = incoming[day] || {};
                    mapped[day] = {
                        v: Number(info.v != null ? info.v : info.visits) || 0,
                        w: Number(info.w != null ? info.w : info.views) || 0
                    };
                });
                grid.setAttribute('data-hm-days', JSON.stringify(mapped));
                applyReaderDay(section, currentModeOf(section, 'reader'));
                var view = currentViewOf(section);
                if (view !== 'day') {
                    renderBars(section, view);
                }
                var nums = section.querySelectorAll('.heatmap-summary .hm-num');
                if (nums.length >= 2) {
                    nums[0].textContent = Number(json.data.visitors || 0).toLocaleString();
                    nums[1].textContent = Number(json.data.pageviews || 0).toLocaleString();
                }
            })
            .catch(function () {});
    }
})();
</script>

<?php $this->need('footer.php'); ?>
