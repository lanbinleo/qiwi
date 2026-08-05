<?php
/**
 * 关于页面模板
 *
 * @package custom
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$qiwiShowToc = qiwiShouldShowToc($this);
$this->need('header.php');

$aboutRawContent = qiwiGetContent($this);
$aboutTabPattern = '/<p>\s*==\s*(.+?)\s*==\s*<\/p>/iu';
$aboutTabSplits = preg_split($aboutTabPattern, $aboutRawContent, -1, PREG_SPLIT_DELIM_CAPTURE);

$aboutTabs = array();
if ($aboutTabSplits !== false && count($aboutTabSplits) >= 3) {
    $aboutPreIntro = trim($aboutTabSplits[0]);
    if ($aboutPreIntro !== '') {
        $aboutTabs[] = array(
            'title' => $this->author->screenName(),
            'html' => $aboutPreIntro,
            'isIntro' => true,
        );
    }
    for ($i = 1; $i < count($aboutTabSplits); $i += 2) {
        $tabTitle = trim($aboutTabSplits[$i]);
        $tabHtml = isset($aboutTabSplits[$i + 1]) ? trim($aboutTabSplits[$i + 1]) : '';
        if ($tabTitle === '' && $tabHtml === '') continue;
        $aboutTabs[] = array(
            'title' => $tabTitle !== '' ? $tabTitle : '未命名',
            'html' => $tabHtml,
            'isIntro' => false,
        );
    }
}

$aboutMultiTab = count($aboutTabs) >= 2;
$aboutCommentsOpen = (bool) $this->allow('comment');
$aboutCommentTabIdx = -1;
if ($aboutMultiTab && $aboutCommentsOpen) {
    foreach ($aboutTabs as $idx => $tab) {
        if (isset($tab['isIntro']) && $tab['isIntro']) continue;
        if (preg_match('/(联系|留言|contact|guestbook|message)/iu', $tab['title'])) {
            $aboutCommentTabIdx = $idx;
            break;
        }
    }
    if ($aboutCommentTabIdx < 0) {
        $aboutCommentTabIdx = count($aboutTabs) - 1;
    }
}

$aboutTabIdBase = 'about-tab';
$aboutPanelIdBase = 'about-panel';
$aboutTabStorageKey = 'qiwi:about-active-tab:' . rtrim((string) $this->permalink, '/');
?>

<div class="about-page">
    <div class="layout-spacer-left"></div>

    <div class="about-main">
        <div class="about-header">
            <img src="<?php echo $this->options->aboutAvatar ?: 'https://gravatar.loli.net/avatar/default?s=240&d=mp'; ?>"
                 alt="<?php $this->author->screenName(); ?>"
                 class="about-avatar"
                 onerror="this.src='https://gravatar.loli.net/avatar/default?s=240&d=mp'">
            <div class="about-identity">
                <h1 class="about-name"><?php $this->author->screenName(); ?></h1>
                <?php if ($this->options->aboutBio): ?>
                    <p class="about-bio"><?php echo $this->options->aboutBio; ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($qiwiShowToc): ?>
        <nav class="article-toc" aria-label="页面目录"></nav>
        <?php endif; ?>

        <?php if ($aboutMultiTab): ?>
        <div class="about-tabs" data-about-tabs>
            <div class="about-tabs-head">
                <div class="about-tab-list" role="tablist" aria-label="关于页面内容">
                    <?php foreach ($aboutTabs as $idx => $tab):
                        $tabId = $aboutTabIdBase . '-' . $idx;
                        $panelId = $aboutPanelIdBase . '-' . $idx;
                        $isFirst = $idx === 0;
                    ?>
                    <button type="button"
                            class="about-tab<?php echo $isFirst ? ' is-active' : ''; ?>"
                            id="<?php echo $tabId; ?>"
                            role="tab"
                            aria-selected="<?php echo $isFirst ? 'true' : 'false'; ?>"
                            aria-controls="<?php echo $panelId; ?>"
                            tabindex="<?php echo $isFirst ? 0 : -1; ?>"
                            data-about-tab="<?php echo $idx; ?>"><?php echo htmlspecialchars($tab['title'], ENT_QUOTES, 'UTF-8'); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php foreach ($aboutTabs as $idx => $tab):
                $tabId = $aboutTabIdBase . '-' . $idx;
                $panelId = $aboutPanelIdBase . '-' . $idx;
                $isFirst = $idx === 0;
                $showComments = ($idx === $aboutCommentTabIdx);
            ?>
            <div class="about-tab-panel<?php echo $isFirst ? ' is-active' : ''; ?> article-body"
                 id="<?php echo $panelId; ?>"
                 role="tabpanel"
                 aria-labelledby="<?php echo $tabId; ?>"
                 data-about-panel="<?php echo $idx; ?>"
                 <?php echo $isFirst ? '' : 'hidden'; ?>>
                <?php echo $tab['html']; ?>
                <?php if ($showComments): ?>
                <div class="comments-wrapper">
                    <?php $this->need('comments.php'); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <script>
        (function() {
            var root = document.querySelector('[data-about-tabs]');
            if (!root || root.dataset.aboutTabsReady === '1') return;
            root.dataset.aboutTabsReady = '1';

            var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-about-tab]'));
            var panels = Array.prototype.slice.call(root.querySelectorAll('[data-about-panel]'));
            if (tabs.length < 2 || panels.length < 2) return;

            var storageKey = <?php echo json_encode($aboutTabStorageKey, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

            function readStoredTab() {
                try {
                    var v = parseInt(localStorage.getItem(storageKey) || '0', 10);
                    return (isNaN(v) || v < 0 || v >= tabs.length) ? 0 : v;
                } catch (e) { return 0; }
            }
            function writeStoredTab(i) {
                try { localStorage.setItem(storageKey, String(i)); } catch (e) {}
            }

            var currentIdx = 0;
            var firstRun = true;
            var swipe = null;

            function cleanupSwipe(prevPanel, nextPanel, container, anims) {
                if (prevPanel) {
                    prevPanel.hidden = true;
                    prevPanel.classList.remove('is-active');
                    prevPanel.style.removeProperty('position');
                    prevPanel.style.removeProperty('top');
                    prevPanel.style.removeProperty('left');
                    prevPanel.style.removeProperty('width');
                    prevPanel.style.removeProperty('will-change');
                    prevPanel.style.removeProperty('transform');
                    prevPanel.style.removeProperty('opacity');
                }
                if (nextPanel) {
                    nextPanel.style.removeProperty('position');
                    nextPanel.style.removeProperty('top');
                    nextPanel.style.removeProperty('left');
                    nextPanel.style.removeProperty('width');
                    nextPanel.style.removeProperty('will-change');
                    nextPanel.style.removeProperty('transform');
                    nextPanel.style.removeProperty('opacity');
                }
                if (container) {
                    container.style.removeProperty('height');
                    container.style.removeProperty('overflow');
                }
                if (anims) {
                    try { anims.prevAnim.cancel(); } catch (e) {}
                    try { anims.nextAnim.cancel(); } catch (e) {}
                    try { anims.containerAnim.cancel(); } catch (e) {}
                }
            }

            function cancelSwipe() {
                if (!swipe) return;
                cleanupSwipe(swipe.prevPanel, swipe.nextPanel, swipe.container, swipe);
                swipe = null;
            }

            function activate(idx, persist) {
                idx = Math.max(0, Math.min(tabs.length - 1, parseInt(idx, 10) || 0));
                var prevIdx = currentIdx;
                var direction = idx > prevIdx ? 1 : (idx < prevIdx ? -1 : 0);
                var doSwipe = !firstRun && direction !== 0 && idx !== prevIdx;
                firstRun = false;

                cancelSwipe();

                tabs.forEach(function(tab, i) {
                    var active = i === idx;
                    tab.classList.toggle('is-active', active);
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                    tab.setAttribute('tabindex', active ? '0' : '-1');
                });

                if (doSwipe) {
                    var prevPanel = panels[prevIdx];
                    var nextPanel = panels[idx];
                    var supportsAnim = typeof prevPanel.animate === 'function'
                        && !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
                    if (supportsAnim) {
                        var container = root;
                        var headEl = container.querySelector('.about-tabs-head');
                        var headH = 0;
                        if (headEl) {
                            var headCs = window.getComputedStyle(headEl);
                            headH = headEl.offsetTop + headEl.offsetHeight + (parseFloat(headCs.marginBottom) || 0);
                        }
                        var swipeOpts = { duration: 240, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' };

                        nextPanel.hidden = false;
                        nextPanel.classList.add('is-active');

                        prevPanel.style.position = 'absolute';
                        prevPanel.style.top = headH + 'px';
                        prevPanel.style.left = '0';
                        prevPanel.style.width = '100%';
                        prevPanel.style.willChange = 'transform, opacity';

                        var prevH = prevPanel.offsetHeight;
                        var nextH = nextPanel.offsetHeight;

                        container.style.height = (headH + prevH) + 'px';
                        container.style.overflow = 'hidden';

                        var prevAnim = prevPanel.animate([
                            { transform: 'translateX(0)', opacity: 1 },
                            { transform: 'translateX(' + (-direction * 28) + 'px)', opacity: 0 }
                        ], Object.assign({ fill: 'forwards' }, swipeOpts));

                        var nextAnim = nextPanel.animate([
                            { transform: 'translateX(' + (direction * 28) + 'px)', opacity: 0 },
                            { transform: 'translateX(0)', opacity: 1 }
                        ], Object.assign({ fill: 'backwards' }, swipeOpts));

                        var containerAnim = container.animate([
                            { height: (headH + prevH) + 'px' },
                            { height: (headH + nextH) + 'px' }
                        ], Object.assign({ fill: 'forwards' }, swipeOpts));

                        swipe = { prevPanel: prevPanel, nextPanel: nextPanel, container: container, prevAnim: prevAnim, nextAnim: nextAnim, containerAnim: containerAnim };

                        var pending = 3;
                        var finished = false;
                        function done() {
                            pending--;
                            if (pending > 0 || finished) return;
                            finished = true;
                            cleanupSwipe(prevPanel, nextPanel, container, { prevAnim: prevAnim, nextAnim: nextAnim, containerAnim: containerAnim });
                            if (swipe && swipe.prevPanel === prevPanel) swipe = null;
                        }
                        prevAnim.onfinish = done;
                        nextAnim.onfinish = done;
                        containerAnim.onfinish = done;
                        prevAnim.oncancel = done;
                        nextAnim.oncancel = done;
                        containerAnim.oncancel = done;
                    } else {
                        panels.forEach(function(panel, i) {
                            var active = i === idx;
                            panel.classList.toggle('is-active', active);
                            panel.hidden = !active;
                        });
                    }
                } else {
                    panels.forEach(function(panel, i) {
                        var active = i === idx;
                        panel.classList.toggle('is-active', active);
                        panel.hidden = !active;
                    });
                }

                currentIdx = idx;
                if (persist !== false) writeStoredTab(idx);
            }

            tabs.forEach(function(tab, idx) {
                tab.addEventListener('click', function() { activate(idx); });
                tab.addEventListener('keydown', function(event) {
                    if (!/^(ArrowLeft|ArrowRight|Home|End)$/.test(event.key)) return;
                    event.preventDefault();
                    var next;
                    if (event.key === 'Home') next = 0;
                    else if (event.key === 'End') next = tabs.length - 1;
                    else {
                        var offset = event.key === 'ArrowLeft' ? -1 : 1;
                        next = (idx + offset + tabs.length) % tabs.length;
                    }
                    activate(next);
                    tabs[next].focus();
                });
            });

            activate(readStoredTab(), false);
        })();
        </script>

        <?php else: ?>

        <div class="about-section">
            <div class="article-body">
                <?php if (count($aboutTabs) === 1 && !empty($aboutTabs[0]['html'])): ?>
                <?php echo $aboutTabs[0]['html']; ?>
                <?php else: ?>
                <?php echo $aboutRawContent; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($aboutCommentsOpen): ?>
        <div class="comments-wrapper">
            <?php $this->need('comments.php'); ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <div class="layout-spacer-right"></div>
</div>

<?php $this->need('footer.php'); ?>
