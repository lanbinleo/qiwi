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

        echo '<div class="qiwi-heatmap" data-hm-kind="' . htmlspecialchars($kind, ENT_QUOTES, 'UTF-8') . '" data-hm-days="' . $daysJson . '" role="img" aria-label="' . htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') . '">';
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

$readerCells = [];
$readerDaysRaw = [];
if (is_array($umamiReader) && !empty($umamiReader['daily'])) {
    $maxVisits = 0;
    foreach ($umamiReader['daily'] as $info) {
        $maxVisits = max($maxVisits, (int) $info['visits']);
    }
    foreach ($umamiReader['daily'] as $day => $info) {
        $visits = (int) $info['visits'];
        $readerDaysRaw[$day] = ['v' => $visits, 'w' => (int) $info['views']];
        if ($visits <= 0) {
            continue;
        }
        $readerCells[$day] = [
            'level' => max(1, min(4, (int) ceil($visits * 4 / max(1, $maxVisits)))),
            'tone' => 'reader',
            'title' => date('Y年n月j日', strtotime($day . ' 00:00:00')) . ' · 到访 ' . $visits . ' · 浏览 ' . (int) $info['views'],
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
                <button type="button" class="hm-toggle" data-hm-toggle-kind="reader" data-hm-mode="visits" hidden aria-label="切换统计口径，当前：访客"><span class="hm-toggle-dot"></span><span class="hm-toggle-text">访客</span></button>
            </div>
            <?php qiwiArchivesRenderHeatmap(
                $readerCells,
                '来访记录热力图（过去一年）',
                'reader',
                '到访',
                '',
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
        tip.textContent = tipText(grid, cell.getAttribute('data-hm-day'));
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

    // 来访口径：访客（sessions）/ 浏览（pageviews），深浅按所选口径对最大值分四档
    function applyReaderMode(section, mode) {
        var grid = gridOf(section);
        if (!grid || grid.getAttribute('data-hm-kind') !== 'reader') {
            return;
        }
        var data = daysMapOf(grid) || {};
        var field = mode === 'views' ? 'w' : 'v';
        var max = 0;
        for (var day in data) {
            if (Object.prototype.hasOwnProperty.call(data, day)) {
                max = Math.max(max, data[day][field] || 0);
            }
        }
        var cells = grid.querySelectorAll('.hm-cell[data-hm-day]');
        for (var j = 0; j < cells.length; j++) {
            var cell = cells[j];
            var info = data[cell.getAttribute('data-hm-day')];
            var value = info ? (info[field] || 0) : 0;
            var level = value > 0 ? Math.max(1, Math.min(4, Math.ceil(value * 4 / Math.max(1, max)))) : 0;
            cell.className = level > 0 ? 'hm-cell hm-l' + level + ' hm-tone-reader' : 'hm-cell hm-l0';
        }
        var metric = section.querySelector('.heatmap-legend-metric');
        if (metric) {
            metric.textContent = mode === 'views' ? '浏览' : '到访';
        }
    }

    var TOGGLE_MODES = {
        writing: <?php echo json_encode($writingToggleModes, JSON_UNESCAPED_UNICODE); ?>,
        reader: [
            { key: 'visits', label: '访客' },
            { key: 'views', label: '浏览' }
        ]
    };

    // PJAX 幂等：全局委托只绑一次，每次渲染只需初始化当前 DOM
    if (!window.__qiwiHeatmapBound) {
        window.__qiwiHeatmapBound = true;

        document.addEventListener('mouseover', function (event) {
            var cell = event.target.closest ? event.target.closest('.hm-cell[data-hm-day]') : null;
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
            if (event.target.closest && event.target.closest('.hm-cell[data-hm-day]')) {
                hideTooltip();
            }
        });
        document.addEventListener('touchstart', function (event) {
            var cell = event.target.closest ? event.target.closest('.hm-cell[data-hm-day]') : null;
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
            toggle.setAttribute('aria-label', '切换统计口径，当前：' + next.label);
            if (kind === 'reader') {
                applyReaderMode(section, next.key);
            } else {
                applyFilter(section, next.key);
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
                var toggle = section.querySelector('.hm-toggle');
                applyReaderMode(section, toggle ? toggle.getAttribute('data-hm-mode') || 'visits' : 'visits');
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
