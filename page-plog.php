<?php
/**
 * Plog 页面模板
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

if (!function_exists('qiwiPlogEscape')) {
    function qiwiPlogEscape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('qiwiPlogHtmlAttr')) {
    function qiwiPlogHtmlAttr($html, $name)
    {
        $name = preg_quote((string) $name, '/');
        if (preg_match('/\b' . $name . '\s*=\s*(["\'])(.*?)\1/isu', (string) $html, $matches)) {
            return html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (preg_match('/\b' . $name . '\s*=\s*([^\s>]+)/isu', (string) $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }
}

if (!function_exists('qiwiPlogNormalizeRawContent')) {
    function qiwiPlogNormalizeRawContent($raw)
    {
        $raw = html_entity_decode((string) $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $raw = preg_replace('/^\xEF\xBB\xBF/u', '', $raw);
        $raw = preg_replace('/<!--\s*markdown\s*-->/iu', '', $raw);
        $raw = preg_replace('/<br\s*\/?>/iu', "\n", $raw);
        $raw = preg_replace('/<h1\b[^>]*>([\s\S]*?)<\/h1>/iu', "\n# $1\n", $raw);
        $raw = preg_replace('/<h2\b[^>]*>([\s\S]*?)<\/h2>/iu', "\n## $1\n", $raw);
        $raw = preg_replace_callback('/<img\b[^>]*>/iu', function ($matches) {
            $src = qiwiPlogHtmlAttr($matches[0], 'src');
            if ($src === '') {
                return '';
            }

            $alt = qiwiPlogHtmlAttr($matches[0], 'alt');
            return "\n![" . str_replace(["\r", "\n", ']'], [' ', ' ', ''], $alt) . "](" . $src . ")\n";
        }, $raw);
        $raw = preg_replace('/<\/p>\s*<p\b[^>]*>/iu', "\n\n", $raw);
        $raw = preg_replace('/<\/?(?:p|div|section|article)\b[^>]*>/iu', "\n", $raw);
        $raw = strip_tags($raw);
        $raw = preg_replace("/\n{3,}/u", "\n\n", $raw);

        return trim($raw);
    }
}

if (!function_exists('qiwiPlogParseDate')) {
    function qiwiPlogParseDate($value)
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return [0, '', false];
        }

        if (preg_match('/^\d{10,13}$/', $raw)) {
            $timestamp = (int) $raw;
            if (strlen($raw) === 13) {
                $timestamp = (int) floor($timestamp / 1000);
            }
            return [$timestamp, date('Y.m.d H:i', $timestamp), true];
        }

        $normalized = preg_replace('/^(\d{4})(\d{2})(\d{2})(\s+\d{1,2}:\d{2}(?::\d{2})?)?$/u', '$1-$2-$3$4', $raw);
        $normalized = preg_replace('/^(\d{4})[\/.](\d{1,2})[\/.](\d{1,2})(\s+\d{1,2}:\d{2}(?::\d{2})?)?$/u', '$1-$2-$3$4', $normalized);

        $timestamp = strtotime($normalized);
        if ($timestamp === false) {
            return [0, $raw, false];
        }

        $hasTime = preg_match('/\d{1,2}:\d{2}(?::\d{2})?$/u', $raw) === 1;
        return [$timestamp, $hasTime ? date('Y.m.d H:i', $timestamp) : date('Y.m.d', $timestamp), $hasTime];
    }
}

if (!function_exists('qiwiPlogNormalizeDateDisplay')) {
    function qiwiPlogNormalizeDateDisplay($value, $fallback = 'show')
    {
        $value = strtolower(trim((string) $value));
        $map = [
            'show' => 'show',
            '显示' => 'show',
            '1' => 'show',
            'true' => 'show',
            'yes' => 'show',
            'on' => 'show',
            'hide' => 'hide',
            '隐藏' => 'hide',
            '0' => 'hide',
            'false' => 'hide',
            'no' => 'hide',
            'off' => 'hide',
            'inherit' => 'inherit',
            'default' => 'inherit',
            '默认' => 'inherit',
        ];

        return isset($map[$value]) ? $map[$value] : $fallback;
    }
}

if (!function_exists('qiwiPlogNormalizeDatePrecision')) {
    function qiwiPlogNormalizeDatePrecision($value, $fallback = 'auto')
    {
        $value = strtolower(trim((string) $value));
        $map = [
            'auto' => 'auto',
            '自动' => 'auto',
            'date' => 'date',
            'day' => 'date',
            '日期' => 'date',
            'datetime' => 'datetime',
            'time' => 'datetime',
            'full' => 'datetime',
            '详细' => 'datetime',
            'inherit' => 'inherit',
            'default' => 'inherit',
            '默认' => 'inherit',
        ];

        return isset($map[$value]) ? $map[$value] : $fallback;
    }
}

if (!function_exists('qiwiPlogFormatDateLabel')) {
    function qiwiPlogFormatDateLabel($photo, $globalDisplay, $globalPrecision)
    {
        $display = isset($photo['date_display']) ? qiwiPlogNormalizeDateDisplay($photo['date_display'], 'inherit') : 'inherit';
        if ($display === 'inherit') {
            $display = qiwiPlogNormalizeDateDisplay($globalDisplay, 'show');
        }
        if ($display === 'hide') {
            return '';
        }

        $timestamp = isset($photo['date_ts']) ? (int) $photo['date_ts'] : 0;
        $rawLabel = isset($photo['date_raw_label']) ? (string) $photo['date_raw_label'] : '';
        if ($timestamp <= 0) {
            return $rawLabel;
        }

        $precision = isset($photo['date_precision']) ? qiwiPlogNormalizeDatePrecision($photo['date_precision'], 'inherit') : 'inherit';
        if ($precision === 'inherit') {
            $precision = qiwiPlogNormalizeDatePrecision($globalPrecision, 'auto');
        }
        if ($precision === 'datetime' || ($precision === 'auto' && !empty($photo['date_has_time']))) {
            return date('Y.m.d H:i', $timestamp);
        }

        return date('Y.m.d', $timestamp);
    }
}

if (!function_exists('qiwiPlogNormalizeMode')) {
    function qiwiPlogNormalizeMode($mode)
    {
        $mode = strtolower(trim((string) $mode));
        $map = [
            'grid' => 'grid',
            '网格' => 'grid',
            'masonry' => 'masonry',
            'waterfall' => 'masonry',
            '瀑布流' => 'masonry',
            '瀑布' => 'masonry',
            'justified' => 'justified',
            'gallery' => 'justified',
            '画廊' => 'justified',
            'stream' => 'stream',
            'timeline' => 'stream',
            '时间流' => 'stream',
        ];

        return isset($map[$mode]) ? $map[$mode] : 'grid';
    }
}

if (!function_exists('qiwiPlogNormalizeSort')) {
    function qiwiPlogNormalizeSort($sort)
    {
        $sort = strtolower(trim((string) $sort));
        $map = [
            'date-desc' => 'date-desc',
            'date_desc' => 'date-desc',
            'newest' => 'date-desc',
            '最新' => 'date-desc',
            'date-asc' => 'date-asc',
            'date_asc' => 'date-asc',
            'oldest' => 'date-asc',
            '最早' => 'date-asc',
            'title-asc' => 'title-asc',
            'title' => 'title-asc',
            '标题' => 'title-asc',
            'title-desc' => 'title-desc',
            'album-asc' => 'album-asc',
            'album' => 'album-asc',
            '图集' => 'album-asc',
            'album-desc' => 'album-desc',
            'manual' => 'manual',
            'order' => 'manual',
            'source' => 'manual',
            '手动' => 'manual',
        ];

        return isset($map[$sort]) ? $map[$sort] : 'date-desc';
    }
}

if (!function_exists('qiwiPlogParseImageLine')) {
    function qiwiPlogParseImageLine($line)
    {
        if (!preg_match('/!\[([^\]]*)\]\(\s*([^\s\)]+)(?:\s+"([^"]*)")?\s*\)/u', $line, $matches)) {
            return null;
        }

        return [
            'title' => trim($matches[1]),
            'src' => trim($matches[2]),
            'full' => trim($matches[2]),
            'thumb' => trim($matches[2]),
            'desc' => isset($matches[3]) ? trim($matches[3]) : '',
            'album' => '',
            'date_raw' => '',
            'date_ts' => 0,
            'date_label' => '',
            'date_raw_label' => '',
            'date_has_time' => false,
            'date_display' => 'inherit',
            'date_precision' => 'inherit',
            'w' => 4,
            'h' => 3,
            'order' => 0,
        ];
    }
}

if (!function_exists('qiwiPlogKeyName')) {
    function qiwiPlogKeyName($key)
    {
        $key = strtolower(trim((string) $key));
        $map = [
            'title' => 'title',
            '标题' => 'title',
            'desc' => 'desc',
            'description' => 'desc',
            '描述' => 'desc',
            'album' => 'album',
            '图集' => 'album',
            'date' => 'date',
            'time' => 'date',
            'timestamp' => 'date',
            'ts' => 'date',
            'uploaded' => 'date',
            'upload-date' => 'date',
            'uploaddate' => 'date',
            'upload_date' => 'date',
            '上传日期' => 'date',
            '日期' => 'date',
            '时间' => 'date',
            'datedisplay' => 'dateDisplay',
            'date-display' => 'dateDisplay',
            'date_display' => 'dateDisplay',
            'showdate' => 'dateDisplay',
            'show-date' => 'dateDisplay',
            'show_date' => 'dateDisplay',
            '展示时间' => 'dateDisplay',
            '显示时间' => 'dateDisplay',
            'dateprecision' => 'datePrecision',
            'date-precision' => 'datePrecision',
            'date_precision' => 'datePrecision',
            'timeprecision' => 'datePrecision',
            'time-precision' => 'datePrecision',
            'time_precision' => 'datePrecision',
            '时间颗粒度' => 'datePrecision',
            '时间精度' => 'datePrecision',
            'sort' => 'sort',
            '排序' => 'sort',
            'mode' => 'mode',
            '模式' => 'mode',
            'thumb' => 'thumb',
            'thumbnail' => 'thumb',
            'full' => 'full',
            'original' => 'full',
            'src' => 'src',
            'url' => 'src',
            'w' => 'w',
            'width' => 'w',
            'h' => 'h',
            'height' => 'h',
        ];

        return isset($map[$key]) ? $map[$key] : '';
    }
}

if (!function_exists('qiwiPlogFinalizePhoto')) {
    function qiwiPlogFinalizePhoto($photo)
    {
        if (empty($photo) || trim((string) $photo['src']) === '') {
            return null;
        }

        if (trim((string) $photo['title']) === '') {
            $photo['title'] = '未命名照片';
        }

        if (empty($photo['thumb'])) {
            $photo['thumb'] = $photo['src'];
        }

        if (empty($photo['full'])) {
            $photo['full'] = $photo['src'];
        }

        $photo['w'] = max(1, (int) $photo['w']);
        $photo['h'] = max(1, (int) $photo['h']);

        list($timestamp, $label, $hasTime) = qiwiPlogParseDate($photo['date_raw']);
        $photo['date_ts'] = $timestamp;
        $photo['date_raw_label'] = $label;
        $photo['date_has_time'] = $hasTime;
        $photo['date_display'] = qiwiPlogNormalizeDateDisplay(isset($photo['date_display']) ? $photo['date_display'] : 'inherit', 'inherit');
        $photo['date_precision'] = qiwiPlogNormalizeDatePrecision(isset($photo['date_precision']) ? $photo['date_precision'] : 'inherit', 'inherit');

        return $photo;
    }
}

if (!function_exists('qiwiPlogSortPhotos')) {
    function qiwiPlogSortPhotos($photos, $sort)
    {
        $sort = qiwiPlogNormalizeSort($sort);
        if ($sort === 'manual') {
            return $photos;
        }

        usort($photos, function ($a, $b) use ($sort) {
            $timeA = isset($a['date_ts']) ? (int) $a['date_ts'] : 0;
            $timeB = isset($b['date_ts']) ? (int) $b['date_ts'] : 0;
            $titleA = isset($a['title']) ? (string) $a['title'] : '';
            $titleB = isset($b['title']) ? (string) $b['title'] : '';
            $albumA = isset($a['album']) ? (string) $a['album'] : '';
            $albumB = isset($b['album']) ? (string) $b['album'] : '';
            $orderA = isset($a['order']) ? (int) $a['order'] : 0;
            $orderB = isset($b['order']) ? (int) $b['order'] : 0;

            if ($sort === 'date-asc') {
                if ($timeA !== $timeB) {
                    return $timeA - $timeB;
                }
                return $orderA - $orderB;
            }

            if ($sort === 'title-asc' || $sort === 'title-desc') {
                $result = strcasecmp($titleA, $titleB);
                if ($result === 0) {
                    $result = $orderA - $orderB;
                }
                return $sort === 'title-desc' ? -$result : $result;
            }

            if ($sort === 'album-asc' || $sort === 'album-desc') {
                $result = strcasecmp($albumA . $titleA, $albumB . $titleB);
                if ($result === 0) {
                    $result = $orderA - $orderB;
                }
                return $sort === 'album-desc' ? -$result : $result;
            }

            if ($timeA !== $timeB) {
                return $timeB - $timeA;
            }
            return $orderB - $orderA;
        });

        return $photos;
    }
}

if (!function_exists('qiwiPlogParseContent')) {
    function qiwiPlogParseContent($raw, $fallbackTitle)
    {
        $raw = qiwiPlogNormalizeRawContent($raw);
        $raw = str_replace(["\r\n", "\r"], "\n", (string) $raw);
        $lines = explode("\n", $raw);
        $data = [
            'title' => trim((string) $fallbackTitle),
            'description' => '',
            'mode' => 'grid',
            'sort' => 'date-desc',
            'dateDisplay' => 'show',
            'datePrecision' => 'auto',
            'photos' => [],
        ];

        $descriptionLines = [];
        $current = null;
        $order = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            $image = qiwiPlogParseImageLine($trimmed);

            if ($image) {
                $finalPhoto = qiwiPlogFinalizePhoto($current);
                if ($finalPhoto) {
                    $data['photos'][] = $finalPhoto;
                }

                $current = $image;
                $current['order'] = ++$order;
                continue;
            }

            if (preg_match('/^\s*([^:：]{1,32})\s*[:：]\s*(.*?)\s*$/u', $line, $matches)) {
                $name = qiwiPlogKeyName($matches[1]);
                $value = trim($matches[2]);

                if ($current) {
                    if ($name === 'date') {
                        $current['date_raw'] = $value;
                    } elseif (in_array($name, ['title', 'desc', 'album', 'thumb', 'full'], true)) {
                        $current[$name] = $value;
                    } elseif ($name === 'dateDisplay') {
                        $current['date_display'] = qiwiPlogNormalizeDateDisplay($value, 'inherit');
                    } elseif ($name === 'datePrecision') {
                        $current['date_precision'] = qiwiPlogNormalizeDatePrecision($value, 'inherit');
                    } elseif ($name === 'src') {
                        $current['src'] = $value;
                        $current['thumb'] = $value;
                        $current['full'] = $value;
                    } elseif ($name === 'w' || $name === 'h') {
                        $current[$name] = max(1, (int) $value);
                    }
                } else {
                    if ($name === 'mode') {
                        $data['mode'] = qiwiPlogNormalizeMode($value);
                    } elseif ($name === 'sort') {
                        $data['sort'] = qiwiPlogNormalizeSort($value);
                    } elseif ($name === 'dateDisplay') {
                        $data['dateDisplay'] = qiwiPlogNormalizeDateDisplay($value, 'show');
                    } elseif ($name === 'datePrecision') {
                        $data['datePrecision'] = qiwiPlogNormalizeDatePrecision($value, 'auto');
                    } elseif ($name === 'title') {
                        $data['title'] = $value;
                    } elseif ($name === 'desc') {
                        $descriptionLines[] = $value;
                    }
                }

                continue;
            }

            if ($current) {
                if ($trimmed !== '') {
                    $current['desc'] = trim($current['desc'] . "\n" . $trimmed);
                }
                continue;
            }

            if (preg_match('/^#\s+(.+)$/u', $trimmed, $matches)) {
                $data['title'] = trim($matches[1]);
                continue;
            }

            if ($trimmed !== '') {
                $descriptionLines[] = $trimmed;
            }
        }

        $finalPhoto = qiwiPlogFinalizePhoto($current);
        if ($finalPhoto) {
            $data['photos'][] = $finalPhoto;
        }

        $data['description'] = trim(implode("\n", $descriptionLines));
        $data['mode'] = qiwiPlogNormalizeMode($data['mode']);
        $data['sort'] = qiwiPlogNormalizeSort($data['sort']);
        $data['dateDisplay'] = qiwiPlogNormalizeDateDisplay($data['dateDisplay'], 'show');
        $data['datePrecision'] = qiwiPlogNormalizeDatePrecision($data['datePrecision'], 'auto');
        $data['photos'] = qiwiPlogSortPhotos($data['photos'], $data['sort']);

        foreach ($data['photos'] as $index => $photo) {
            $data['photos'][$index]['date_label'] = qiwiPlogFormatDateLabel($photo, $data['dateDisplay'], $data['datePrecision']);
        }

        return $data;
    }
}

$rawContent = function_exists('qiwiGetRawContentForDetection') ? qiwiGetRawContentForDetection($this) : '';
$plog = qiwiPlogParseContent($rawContent, $this->title);
$photos = $plog['photos'];
$mode = $plog['mode'];

$this->need('header.php');
?>

<div class="plog-page plog-mode-<?php echo qiwiPlogEscape($mode); ?>" data-plog-page>
    <header class="plog-page-header">
        <h1><?php echo qiwiPlogEscape($plog['title']); ?></h1>
        <?php if ($plog['description'] !== ''): ?>
        <p><?php echo nl2br(qiwiPlogEscape($plog['description']), false); ?></p>
        <?php endif; ?>
    </header>

    <?php if (empty($photos)): ?>
    <section class="plog-empty">
        <p>还没有添加照片。</p>
    </section>
    <?php elseif ($mode === 'stream'): ?>
    <section class="plog-stream" aria-label="<?php echo qiwiPlogEscape($plog['title']); ?>">
        <?php foreach ($photos as $index => $photo): ?>
        <article class="plog-entry">
            <button class="plog-entry-media" type="button" data-plog-index="<?php echo (int) $index; ?>" style="aspect-ratio: <?php echo (int) $photo['w']; ?> / <?php echo (int) $photo['h']; ?>;">
                <img src="<?php echo qiwiPlogEscape($photo['thumb']); ?>" alt="<?php echo qiwiPlogEscape($photo['title']); ?>" loading="lazy" decoding="async">
            </button>
            <div class="plog-entry-meta">
                <div class="plog-entry-top">
                    <h2><?php echo qiwiPlogEscape($photo['title']); ?></h2>
                    <?php if ($photo['date_label'] !== ''): ?><time><?php echo qiwiPlogEscape($photo['date_label']); ?></time><?php endif; ?>
                </div>
                <?php if ($photo['desc'] !== ''): ?><p><?php echo nl2br(qiwiPlogEscape($photo['desc']), false); ?></p><?php endif; ?>
                <?php if ($photo['album'] !== ''): ?><div class="plog-album-line"><span>图集</span><em><?php echo qiwiPlogEscape($photo['album']); ?></em></div><?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </section>
    <?php else: ?>
    <?php $galleryClass = $mode === 'justified' ? 'plog-justified' : ($mode === 'masonry' ? 'plog-masonry' : 'plog-grid'); ?>
    <section class="<?php echo qiwiPlogEscape($galleryClass); ?>" aria-label="<?php echo qiwiPlogEscape($plog['title']); ?>" data-plog-gallery>
        <?php foreach ($photos as $index => $photo): ?>
        <button class="plog-item" type="button" data-plog-index="<?php echo (int) $index; ?>" data-plog-ratio="<?php echo qiwiPlogEscape($photo['w'] / $photo['h']); ?>" style="aspect-ratio: <?php echo (int) $photo['w']; ?> / <?php echo (int) $photo['h']; ?>;">
            <img src="<?php echo qiwiPlogEscape($photo['thumb']); ?>" alt="<?php echo qiwiPlogEscape($photo['title']); ?>" loading="lazy" decoding="async">
            <span class="plog-overlay">
                <strong><?php echo qiwiPlogEscape($photo['title']); ?></strong>
                <?php if ($photo['desc'] !== ''): ?><span><?php echo qiwiPlogEscape($photo['desc']); ?></span><?php endif; ?>
                <?php if ($photo['album'] !== '' || $photo['date_label'] !== ''): ?><em><?php echo qiwiPlogEscape(trim($photo['album'] . ($photo['album'] !== '' && $photo['date_label'] !== '' ? ' · ' : '') . $photo['date_label'])); ?></em><?php endif; ?>
            </span>
        </button>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>
</div>

<?php if (!empty($photos)): ?>
<div class="plog-lightbox" data-plog-lightbox hidden>
    <button class="plog-lightbox-close" type="button" data-plog-close aria-label="关闭图片预览">&times;</button>
    <button class="plog-lightbox-nav plog-lightbox-prev" type="button" data-plog-prev aria-label="上一张">&#8249;</button>
    <figure class="plog-lightbox-content">
        <span class="plog-lightbox-spinner" data-plog-lightbox-spinner aria-hidden="true"></span>
        <img src="" alt="" decoding="async" data-plog-lightbox-image>
        <figcaption>
            <strong data-plog-lightbox-title></strong>
            <span data-plog-lightbox-desc></span>
            <em data-plog-lightbox-album></em>
            <time data-plog-lightbox-date></time>
        </figcaption>
    </figure>
    <button class="plog-lightbox-nav plog-lightbox-next" type="button" data-plog-next aria-label="下一张">&#8250;</button>
    <div class="plog-lightbox-counter" data-plog-counter></div>
</div>

<script>
(function() {
    if (window.qiwiPlogController) window.qiwiPlogController.abort();
    window.qiwiPlogController = new AbortController();
    var qiwiPlogSignal = window.qiwiPlogController.signal;
    var photos = <?php echo json_encode(array_values($photos), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    if (!photos.length) return;

    var lightbox = document.querySelector('[data-plog-lightbox]');
    var image = document.querySelector('[data-plog-lightbox-image]');
    var title = document.querySelector('[data-plog-lightbox-title]');
    var desc = document.querySelector('[data-plog-lightbox-desc]');
    var album = document.querySelector('[data-plog-lightbox-album]');
    var date = document.querySelector('[data-plog-lightbox-date]');
    var counter = document.querySelector('[data-plog-counter]');
    var spinner = document.querySelector('[data-plog-lightbox-spinner]');
    var currentIndex = 0;
    var loadRequest = 0;
    var spinnerTimer = null;
    var closeTimer = null;
    var lastTrigger = null;

    function setLoaded(event) {
        var holder = event.target.closest('.plog-item, .plog-entry-media');
        if (holder) holder.classList.add('is-loaded');
    }

    document.querySelectorAll('.plog-item img, .plog-entry-media img').forEach(function(img) {
        img.addEventListener('load', setLoaded);
        img.addEventListener('error', setLoaded);
        if (img.complete) {
            var holder = img.closest('.plog-item, .plog-entry-media');
            if (holder) holder.classList.add('is-loaded');
        }
    });

    function updateLightboxMeta(photo) {
        title.textContent = photo.title || '';
        desc.textContent = photo.desc || '';
        desc.hidden = !photo.desc;
        album.textContent = photo.album || '';
        album.hidden = !photo.album;
        date.textContent = photo.date_label || '';
        date.hidden = !photo.date_label;
        counter.textContent = (currentIndex + 1) + ' / ' + photos.length;
    }

    function stopSpinner() {
        if (spinnerTimer) window.clearTimeout(spinnerTimer);
        spinnerTimer = null;
        if (spinner) spinner.hidden = true;
    }

    function loadLightboxImage(photo) {
        var request = ++loadRequest;
        stopSpinner();
        spinnerTimer = window.setTimeout(function() {
            if (spinner && request === loadRequest) spinner.hidden = false;
        }, 180);

        var preload = new Image();
        preload.decoding = 'async';
        var source = photo.full || photo.src || photo.thumb;
        function commit() {
            if (request !== loadRequest) return;
            stopSpinner();
            image.classList.remove('is-loaded');
            image.src = preload.src || source;
            image.alt = photo.title || '';
            window.requestAnimationFrame(function() {
                if (request === loadRequest) image.classList.add('is-loaded');
            });
        }
        preload.onload = function() {
            var decoded = typeof preload.decode === 'function' ? preload.decode() : Promise.resolve();
            decoded.catch(function() {}).then(commit);
        };
        preload.onerror = function() {
            if (photo.thumb && source !== photo.thumb) {
                preload.src = photo.thumb;
                return;
            }
            commit();
        };
        preload.src = source;
        if (preload.complete && preload.naturalWidth > 0) preload.onload();
    }

    function openLightbox(index, trigger) {
        currentIndex = (index + photos.length) % photos.length;
        var photo = photos[currentIndex];
        if (trigger) lastTrigger = trigger;
        if (closeTimer) window.clearTimeout(closeTimer);
        updateLightboxMeta(photo);
        if (lightbox.hidden) {
            image.classList.remove('is-loaded');
            image.onload = function() { image.classList.add('is-loaded'); };
            image.src = photo.thumb || photo.src || photo.full;
            image.alt = photo.title || '';
            lightbox.hidden = false;
            window.requestAnimationFrame(function() { lightbox.classList.add('is-open'); });
        }
        document.documentElement.classList.add('plog-lightbox-open');
        loadLightboxImage(photo);
        var closeButton = lightbox.querySelector('[data-plog-close]');
        if (closeButton) closeButton.focus({ preventScroll: true });
    }

    function closeLightbox() {
        if (lightbox.hidden) return;
        loadRequest += 1;
        stopSpinner();
        lightbox.classList.remove('is-open');
        document.documentElement.classList.remove('plog-lightbox-open');
        closeTimer = window.setTimeout(function() {
            lightbox.hidden = true;
            image.classList.remove('is-loaded');
            image.removeAttribute('src');
        }, 230);
        if (lastTrigger && lastTrigger.isConnected) lastTrigger.focus({ preventScroll: true });
    }

    function moveLightbox(step) {
        openLightbox(currentIndex + step);
    }

    document.addEventListener('click', function(event) {
        var trigger = event.target.closest('[data-plog-index]');
        if (trigger) {
            openLightbox(parseInt(trigger.getAttribute('data-plog-index'), 10) || 0, trigger);
            return;
        }

        if (event.target === lightbox) {
            closeLightbox();
        }
    }, { signal: qiwiPlogSignal });

    document.querySelector('[data-plog-prev]').addEventListener('click', function() { moveLightbox(-1); }, { signal: qiwiPlogSignal });
    document.querySelector('[data-plog-next]').addEventListener('click', function() { moveLightbox(1); }, { signal: qiwiPlogSignal });
    document.querySelector('[data-plog-close]').addEventListener('click', closeLightbox, { signal: qiwiPlogSignal });

    document.addEventListener('keydown', function(event) {
        if (lightbox.hidden) return;
        if (event.key === 'Escape') closeLightbox();
        if (event.key === 'ArrowLeft') moveLightbox(-1);
        if (event.key === 'ArrowRight') moveLightbox(1);
        if (event.key === 'Tab') {
            var controls = Array.prototype.slice.call(lightbox.querySelectorAll('button:not([disabled])'));
            if (!controls.length) return;
            var first = controls[0];
            var last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    }, { signal: qiwiPlogSignal });

    function layoutJustified() {
        var gallery = document.querySelector('.plog-justified[data-plog-gallery]');
        if (!gallery) return;

        var targetHeight = window.innerWidth < 700 ? 180 : 260;
        var gap = window.innerWidth < 700 ? 6 : 8;
        var width = gallery.clientWidth;
        var rows = [];
        var row = [];
        var rowWidth = 0;

        Array.prototype.slice.call(gallery.children).forEach(function(item) {
            var ratio = parseFloat(item.getAttribute('data-plog-ratio')) || 1.333;
            row.push({ item: item, ratio: ratio });
            rowWidth += ratio * targetHeight + (row.length > 1 ? gap : 0);
            if (rowWidth >= width) {
                rows.push(row);
                row = [];
                rowWidth = 0;
            }
        });

        if (row.length) rows.push(row);

        rows.forEach(function(items) {
            var totalRatio = items.reduce(function(sum, entry) { return sum + entry.ratio; }, 0);
            var height = Math.max(140, Math.floor((width - gap * (items.length - 1)) / totalRatio));
            items.forEach(function(entry) {
                entry.item.style.width = Math.floor(height * entry.ratio) + 'px';
                entry.item.style.height = height + 'px';
                entry.item.style.aspectRatio = 'auto';
            });
        });
    }

    layoutJustified();
    var timer = null;
    window.addEventListener('resize', function() {
        window.clearTimeout(timer);
        timer = window.setTimeout(layoutJustified, 120);
    }, { signal: qiwiPlogSignal });
})();
</script>
<?php endif; ?>

<?php if ($this->allow('comment')): ?>
<div class="comments-wrapper plog-comments">
    <?php $this->need('comments.php'); ?>
</div>
<?php endif; ?>

<?php $this->need('footer.php'); ?>
