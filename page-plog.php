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

if (!function_exists('qiwiPlogParseDate')) {
    function qiwiPlogParseDate($value)
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return [0, ''];
        }

        if (preg_match('/^\d{10,13}$/', $raw)) {
            $timestamp = (int) $raw;
            if (strlen($raw) === 13) {
                $timestamp = (int) floor($timestamp / 1000);
            }
            return [$timestamp, date('Y.m.d H:i', $timestamp)];
        }

        $normalized = preg_replace('/^(\d{4})(\d{2})(\d{2})(\s+\d{1,2}:\d{2}(?::\d{2})?)?$/u', '$1-$2-$3$4', $raw);
        $normalized = preg_replace('/^(\d{4})[\/.](\d{1,2})[\/.](\d{1,2})(\s+\d{1,2}:\d{2}(?::\d{2})?)?$/u', '$1-$2-$3$4', $normalized);

        $timestamp = strtotime($normalized);
        if ($timestamp === false) {
            return [0, $raw];
        }

        $hasTime = preg_match('/\d{1,2}:\d{2}(?::\d{2})?$/u', $raw) === 1;
        return [$timestamp, $hasTime ? date('Y.m.d H:i', $timestamp) : date('Y.m.d', $timestamp)];
    }
}

if (!function_exists('qiwiPlogNormalizeMode')) {
    function qiwiPlogNormalizeMode($mode)
    {
        $mode = strtolower(trim((string) $mode));
        $map = [
            'grid' => 'grid',
            '网格' => 'grid',
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

        list($timestamp, $label) = qiwiPlogParseDate($photo['date_raw']);
        $photo['date_ts'] = $timestamp;
        $photo['date_label'] = $label;

        return $photo;
    }
}

if (!function_exists('qiwiPlogParseContent')) {
    function qiwiPlogParseContent($raw, $fallbackTitle)
    {
        $raw = str_replace(["\r\n", "\r"], "\n", (string) $raw);
        $lines = explode("\n", $raw);
        $data = [
            'title' => trim((string) $fallbackTitle),
            'description' => '',
            'mode' => 'grid',
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

        usort($data['photos'], function ($a, $b) {
            $timeA = isset($a['date_ts']) ? (int) $a['date_ts'] : 0;
            $timeB = isset($b['date_ts']) ? (int) $b['date_ts'] : 0;
            if ($timeA !== $timeB) {
                return $timeB - $timeA;
            }
            return (int) $b['order'] - (int) $a['order'];
        });

        $data['description'] = trim(implode("\n", $descriptionLines));
        $data['mode'] = qiwiPlogNormalizeMode($data['mode']);

        return $data;
    }
}

$rawContent = function_exists('qiwiGetRawContentForDetection') ? qiwiGetRawContentForDetection($this) : '';
$plog = qiwiPlogParseContent($rawContent, $this->title);
$photos = $plog['photos'];
$mode = $plog['mode'];

$this->need('header.php');
?>

<main class="plog-page plog-mode-<?php echo qiwiPlogEscape($mode); ?>" data-plog-page>
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
    <section class="<?php echo $mode === 'justified' ? 'plog-justified' : 'plog-grid'; ?>" aria-label="<?php echo qiwiPlogEscape($plog['title']); ?>" data-plog-gallery>
        <?php foreach ($photos as $index => $photo): ?>
        <button class="plog-item" type="button" data-plog-index="<?php echo (int) $index; ?>" data-plog-ratio="<?php echo qiwiPlogEscape($photo['w'] / $photo['h']); ?>" style="aspect-ratio: <?php echo (int) $photo['w']; ?> / <?php echo (int) $photo['h']; ?>;">
            <img src="<?php echo qiwiPlogEscape($photo['thumb']); ?>" alt="<?php echo qiwiPlogEscape($photo['title']); ?>" loading="lazy" decoding="async">
            <span class="plog-overlay">
                <strong><?php echo qiwiPlogEscape($photo['title']); ?></strong>
                <?php if ($photo['desc'] !== ''): ?><span><?php echo qiwiPlogEscape($photo['desc']); ?></span><?php endif; ?>
                <?php if ($photo['album'] !== ''): ?><em><?php echo qiwiPlogEscape($photo['album']); ?></em><?php endif; ?>
            </span>
        </button>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>
</main>

<?php if (!empty($photos)): ?>
<div class="plog-lightbox" data-plog-lightbox hidden>
    <button class="plog-lightbox-close" type="button" data-plog-close aria-label="关闭图片预览">&times;</button>
    <button class="plog-lightbox-nav plog-lightbox-prev" type="button" data-plog-prev aria-label="上一张">&#8249;</button>
    <figure class="plog-lightbox-content">
        <img src="" alt="" data-plog-lightbox-image>
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
    var photos = <?php echo json_encode(array_values($photos), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    if (!photos.length) return;

    var lightbox = document.querySelector('[data-plog-lightbox]');
    var image = document.querySelector('[data-plog-lightbox-image]');
    var title = document.querySelector('[data-plog-lightbox-title]');
    var desc = document.querySelector('[data-plog-lightbox-desc]');
    var album = document.querySelector('[data-plog-lightbox-album]');
    var date = document.querySelector('[data-plog-lightbox-date]');
    var counter = document.querySelector('[data-plog-counter]');
    var currentIndex = 0;

    function setLoaded(event) {
        var holder = event.target.closest('.plog-item, .plog-entry-media');
        if (holder) holder.classList.add('is-loaded');
    }

    document.querySelectorAll('.plog-item img, .plog-entry-media img').forEach(function(img) {
        if (img.complete) {
            img.closest('.plog-item, .plog-entry-media').classList.add('is-loaded');
        } else {
            img.addEventListener('load', setLoaded);
        }
    });

    function openLightbox(index) {
        currentIndex = (index + photos.length) % photos.length;
        var photo = photos[currentIndex];
        image.classList.remove('is-loaded');
        image.onload = function() { image.classList.add('is-loaded'); };
        image.src = photo.full || photo.src || photo.thumb;
        image.alt = photo.title || '';
        title.textContent = photo.title || '';
        desc.textContent = photo.desc || '';
        desc.hidden = !photo.desc;
        album.textContent = photo.album || '';
        album.hidden = !photo.album;
        date.textContent = photo.date_label || '';
        date.hidden = !photo.date_label;
        counter.textContent = (currentIndex + 1) + ' / ' + photos.length;
        lightbox.hidden = false;
        lightbox.classList.add('is-open');
        document.documentElement.classList.add('plog-lightbox-open');
    }

    function closeLightbox() {
        lightbox.classList.remove('is-open');
        lightbox.hidden = true;
        document.documentElement.classList.remove('plog-lightbox-open');
    }

    function moveLightbox(step) {
        openLightbox(currentIndex + step);
    }

    document.addEventListener('click', function(event) {
        var trigger = event.target.closest('[data-plog-index]');
        if (trigger) {
            openLightbox(parseInt(trigger.getAttribute('data-plog-index'), 10) || 0);
            return;
        }

        if (event.target.matches('[data-plog-close]') || event.target === lightbox) {
            closeLightbox();
        }
    });

    document.querySelector('[data-plog-prev]').addEventListener('click', function() { moveLightbox(-1); });
    document.querySelector('[data-plog-next]').addEventListener('click', function() { moveLightbox(1); });
    document.querySelector('[data-plog-close]').addEventListener('click', closeLightbox);

    document.addEventListener('keydown', function(event) {
        if (lightbox.hidden) return;
        if (event.key === 'Escape') closeLightbox();
        if (event.key === 'ArrowLeft') moveLightbox(-1);
        if (event.key === 'ArrowRight') moveLightbox(1);
    });

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
    });
})();
</script>
<?php endif; ?>

<?php if ($this->allow('comment')): ?>
<div class="comments-wrapper plog-comments">
    <?php $this->need('comments.php'); ?>
</div>
<?php endif; ?>

<?php $this->need('footer.php'); ?>
