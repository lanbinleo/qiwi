<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php
$homeJikeData = isset($this->homeJikeData) ? $this->homeJikeData : null;
$homeJikeTimeMode = isset($this->homeJikeTimeMode) ? $this->homeJikeTimeMode : 'absolute';
if (empty($homeJikeData['items']) || empty($homeJikeData['permalink'])) return;
?>

<section class="home-jike" aria-label="即刻动态">
    <div class="home-jike-shell" data-home-jike>
        <div class="home-jike-viewport">
            <div class="home-jike-track">
                <?php foreach ($homeJikeData['items'] as $item): ?>
                <?php $timeLabel = $homeJikeTimeMode === 'relative' ? $item['relative_date_label'] : $item['date_label']; ?>
                <a class="home-jike-item" href="<?php echo htmlspecialchars($homeJikeData['permalink'], ENT_QUOTES, 'UTF-8'); ?>" title="前往 <?php echo htmlspecialchars($homeJikeData['title'], ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="home-jike-label">即刻</span>
                    <span class="home-jike-text"><?php echo htmlspecialchars($item['excerpt'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <time class="home-jike-date" datetime="<?php echo htmlspecialchars($item['datetime'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></time>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
