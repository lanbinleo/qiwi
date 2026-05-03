<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$threadTitle = method_exists($this, 'getArchiveTitle') ? (string) $this->getArchiveTitle() : '';
if ($threadTitle === '') {
    ob_start();
    $this->archiveTitle(['category' => '%s'], '', '');
    $threadTitle = trim(ob_get_clean());
}

$threadSlug = method_exists($this, 'getArchiveSlug') ? (string) $this->getArchiveSlug() : '';
$threadDescription = method_exists($this, 'getDescription') ? (string) $this->getDescription() : '';
$threadMid = isset($this->mid) ? (int) $this->mid : 0;
if ($threadMid <= 0 && $threadSlug !== '') {
    try {
        $db = Typecho_Db::get();
        $threadMeta = $db->fetchRow($db->select('mid', 'description')
            ->from('table.metas')
            ->where('type = ?', 'category')
            ->where('slug = ?', $threadSlug)
            ->limit(1));
        if ($threadMeta) {
            $threadMid = (int) $threadMeta['mid'];
            if ($threadDescription === '' && isset($threadMeta['description'])) {
                $threadDescription = (string) $threadMeta['description'];
            }
        }
    } catch (Exception $e) {
        $threadMid = 0;
    }
}
$threadData = function_exists('qiwiGetThreadData') ? qiwiGetThreadData($threadMid, $threadDescription) : qiwiParseThreadData($threadDescription);
$threadPosts = [];

$postByCid = [];
$postBySlug = [];

$usedPostIds = [];
$threadBlocks = [];
$hasCustomBlocks = !empty($threadData['blocks']);

if (!$hasCustomBlocks) {
    if ($threadMid > 0 && function_exists('qiwiThreadPostFromRow')) {
        try {
            $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
            Typecho_Widget::widget('Widget_Options')->to($options);
            $categoryIds = [$threadMid];
            if (class_exists('\Widget\Metas\Category\Rows')) {
                \Widget\Metas\Category\Rows::alloc('current=' . $threadMid)->to($categoryRows);
                $categoryIds = array_merge($categoryIds, (array) $categoryRows->getAllChildren($threadMid));
            }

            $rows = $db->fetchAll($db->select('table.contents.cid', 'table.contents.title', 'table.contents.slug', 'table.contents.created', 'table.contents.modified', 'table.contents.text')
                ->from('table.contents')
                ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
                ->where('table.relationships.mid IN ?', array_values(array_unique(array_map('intval', $categoryIds))))
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.status = ?', 'publish')
                ->where('(table.contents.password IS NULL OR table.contents.password = ?)', '')
                ->where('table.contents.created < ?', $options->gmtTime)
                ->group('table.contents.cid'));

            foreach ($rows as $row) {
                $post = qiwiThreadPostFromRow($row);
                if ($post) {
                    $threadPosts[] = $post;
                }
            }
        } catch (Exception $e) {
            $threadPosts = [];
        } catch (Throwable $e) {
            $threadPosts = [];
        }
    }

    usort($threadPosts, function($a, $b) use ($threadData) {
        if ($a['created'] === $b['created']) {
            return $a['cid'] - $b['cid'];
        }

        return $threadData['order'] === 'desc' ? $b['created'] - $a['created'] : $a['created'] - $b['created'];
    });

    foreach ($threadPosts as $post) {
        $postByCid[$post['cid']] = $post;
        if ($post['slug'] !== '') {
            $postBySlug[$post['slug']] = $post;
        }
    }
}

foreach ($threadData['blocks'] as $block) {
    if ($block['type'] !== 'post') {
        $threadBlocks[] = ['kind' => 'markdown', 'block' => $block];
        continue;
    }

    $post = null;
    if ($block['cid'] > 0 && isset($postByCid[$block['cid']])) {
        $post = $postByCid[$block['cid']];
    } elseif ($block['slug'] !== '' && isset($postBySlug[$block['slug']])) {
        $post = $postBySlug[$block['slug']];
    } elseif (function_exists('qiwiThreadFetchPost')) {
        $post = qiwiThreadFetchPost($block['cid'], $block['slug']);
    }

    if ($post === null) {
        continue;
    }

    $usedPostIds[$post['cid']] = true;
    $threadBlocks[] = ['kind' => 'post', 'block' => $block, 'post' => $post];

    if (!isset($postByCid[$post['cid']])) {
        $threadPosts[] = $post;
        $postByCid[$post['cid']] = $post;
        if ($post['slug'] !== '') {
            $postBySlug[$post['slug']] = $post;
        }
    }
}

if (!$hasCustomBlocks) {
    foreach ($threadPosts as $post) {
        $threadBlocks[] = ['kind' => 'post', 'block' => ['type' => 'post'], 'post' => $post];
        $usedPostIds[$post['cid']] = true;
    }
}

$threadDisplayPosts = [];
foreach ($threadBlocks as $item) {
    if ($item['kind'] === 'post' && !empty($item['post'])) {
        $threadDisplayPosts[] = $item['post'];
    }
}

$threadPostCount = count($threadDisplayPosts);
$lastUpdated = 0;
foreach ($threadDisplayPosts as $post) {
    $lastUpdated = max($lastUpdated, $post['modified']);
}

$threadSummary = trim((string) $threadData['summary']);
$threadSubtitle = trim((string) $threadData['subtitle']);
$threadField = trim((string) $threadData['field']);
$threadStartedAt = trim((string) $threadData['startedAt']);
$threadStatus = function_exists('qiwiThreadStatusLabel') ? qiwiThreadStatusLabel($threadData['status']) : '连载中';
?>

<main class="thread-page">
    <div class="thread-shell">
        <a class="thread-back-link" href="<?php $this->options->siteUrl(); ?>">返回首页</a>

        <header class="thread-hero">
            <h1><?php echo htmlspecialchars($threadTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
            <?php if ($threadSubtitle !== ''): ?>
                <p class="thread-subtitle"><?php echo htmlspecialchars($threadSubtitle, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if ($threadSummary !== ''): ?>
                <div class="thread-summary"><?php echo qiwiRenderThreadMarkdown($threadSummary); ?></div>
            <?php endif; ?>

            <dl class="thread-meta-grid">
                <div>
                    <dt>状态</dt>
                    <dd><span class="thread-status-dot"></span><?php echo htmlspecialchars($threadStatus, ENT_QUOTES, 'UTF-8'); ?></dd>
                </div>
                <div>
                    <dt>文章</dt>
                    <dd><?php echo (int) $threadPostCount; ?> 篇</dd>
                </div>
                <?php if ($threadStartedAt !== ''): ?>
                <div>
                    <dt>开始</dt>
                    <dd><?php echo htmlspecialchars($threadStartedAt, ENT_QUOTES, 'UTF-8'); ?></dd>
                </div>
                <?php endif; ?>
                <?php if ($lastUpdated > 0): ?>
                <div>
                    <dt>最近</dt>
                    <dd><?php echo htmlspecialchars(date('Y-m-d', $lastUpdated), ENT_QUOTES, 'UTF-8'); ?></dd>
                </div>
                <?php endif; ?>
                <?php if ($threadField !== ''): ?>
                <div>
                    <dt>领域</dt>
                    <dd><?php echo htmlspecialchars($threadField, ENT_QUOTES, 'UTF-8'); ?></dd>
                </div>
                <?php endif; ?>
            </dl>
        </header>

        <?php if (!empty($threadBlocks)): ?>
        <section class="thread-route" aria-label="文集路线">
            <div class="thread-route-head">
                <p>阅读路线</p>
            </div>

            <div class="thread-blocks">
                <?php $threadPostIndex = 0; ?>
                <?php foreach ($threadBlocks as $item): ?>
                    <?php if ($item['kind'] === 'post'): ?>
                        <?php
                            $threadPostIndex++;
                            $post = $item['post'];
                            $block = $item['block'];
                            $label = trim((string) (isset($block['label']) ? $block['label'] : ''));
                            $role = trim((string) (isset($block['role']) ? $block['role'] : ''));
                            $note = trim((string) (isset($block['note']) ? $block['note'] : ''));
                            $note = function_exists('qiwiThreadCleanOptionalText') ? qiwiThreadCleanOptionalText($note) : ($note === '0' ? '' : $note);
                        ?>
                        <article class="thread-post-block">
                            <a class="thread-post-cover" href="<?php echo htmlspecialchars($post['permalink'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="阅读《<?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?>》"></a>
                            <div class="thread-post-marker">
                                <span><?php echo htmlspecialchars($label !== '' ? $label : str_pad((string) $threadPostIndex, 2, '0', STR_PAD_LEFT), ENT_QUOTES, 'UTF-8'); ?></span>
                                <time datetime="<?php echo htmlspecialchars(date('c', $post['created']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(date('Y-m-d', $post['created']), ENT_QUOTES, 'UTF-8'); ?></time>
                            </div>
                            <div class="thread-post-body">
                                <div class="thread-post-meta">
                                    <span><?php echo htmlspecialchars($role !== '' ? $role : '文章', ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span><?php echo (int) $post['readingTime']; ?> 分钟阅读</span>
                                </div>
                                <h2><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                                <?php if ($post['excerpt'] !== ''): ?>
                                    <p><?php echo htmlspecialchars($post['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                                <?php if ($note !== ''): ?>
                                    <div class="thread-post-note"><?php echo qiwiRenderThreadMarkdown($note); ?></div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php else: ?>
                        <?php
                            $block = $item['block'];
                            $title = trim((string) (isset($block['title']) ? $block['title'] : ''));
                            $content = trim((string) (isset($block['content']) ? $block['content'] : ''));
                        ?>
                        <?php if ($title !== '' || $content !== ''): ?>
                        <section class="thread-text-block<?php echo $item['kind'] === 'markdown' ? ' is-markdown' : ''; ?>">
                            <?php if ($title !== ''): ?>
                                <h2><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h2>
                            <?php endif; ?>
                            <?php if ($content !== ''): ?>
                                <div><?php echo $item['kind'] === 'markdown' ? qiwiRenderThreadMarkdown($content) : '<p>' . nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'), false) . '</p>'; ?></div>
                            <?php endif; ?>
                        </section>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
        <?php else: ?>
            <div class="empty-posts">
                <h2>这个文集还没有文章</h2>
                <p>在文集配置里添加文章块后，它们会按你设置的顺序出现在这里。</p>
            </div>
        <?php endif; ?>
    </div>
</main>
