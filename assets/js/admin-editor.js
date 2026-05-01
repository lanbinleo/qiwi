(function() {
    if (window.QIWI_ADMIN_EDITOR_SHORTCODES_INIT) {
        if (window.QIWI_ADMIN_EDITOR_SHORTCODES && typeof window.QIWI_ADMIN_EDITOR_SHORTCODES.enhanceAll === 'function') {
            window.QIWI_ADMIN_EDITOR_SHORTCODES.enhanceAll();
        }
        return;
    }

    window.QIWI_ADMIN_EDITOR_SHORTCODES_INIT = true;
    window.QIWI_ADMIN_EDITOR_SHORTCODES = window.QIWI_ADMIN_EDITOR_SHORTCODES || {};

    var COLORS = ['red', 'orange', 'yellow', 'green', 'cyan', 'blue', 'purple'];
    var COLOR_PATTERN = COLORS.join('|');
    var FOLD_RE = /\[fold(?:\s+title=(?:"([^"]*)"|'([^']*)'|([^\]\s]+)))?\]/i;
    var CLOSE_FOLD_RE = /\[\/fold\]/i;

    function sanitizeColor(color) {
        color = String(color || '').toLowerCase().trim();
        return COLORS.indexOf(color) !== -1 ? color : 'yellow';
    }

    function isProtectedNode(node) {
        var element = node && node.nodeType === 1 ? node : node && node.parentElement;
        return !!(element && element.closest('pre, code, script, style, .qiwi-mark, [class*="qiwi-text-"]'));
    }

    function hasShortcodeText(element) {
        return /\[(?:fold|\/fold|mark|red|orange|yellow|green|cyan|blue|purple)(?:\s|\]|=)/i.test(element.textContent || '');
    }

    function getTextNodes(root) {
        var nodes = [];
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: function(node) {
                if (!node.nodeValue || isProtectedNode(node)) {
                    return NodeFilter.FILTER_REJECT;
                }

                return NodeFilter.FILTER_ACCEPT;
            }
        });

        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }

        return nodes;
    }

    function directChildOf(node, root) {
        var current = node && node.nodeType === 3 ? node.parentNode : node;
        while (current && current.parentNode !== root) {
            current = current.parentNode;
        }

        return current && current.parentNode === root ? current : null;
    }

    function isEffectivelyEmpty(element) {
        if (!element || element.nodeType !== 1) return false;

        var clone = element.cloneNode(true);
        clone.querySelectorAll('.line').forEach(function(line) {
            line.parentNode.removeChild(line);
        });

        return clone.textContent.trim() === '' && !clone.querySelector('img, iframe, video, audio, table, ul, ol, blockquote, pre, code');
    }

    function findTextNode(root, regex, afterNode) {
        var nodes = getTextNodes(root);
        var afterReached = !afterNode;

        for (var i = 0; i < nodes.length; i++) {
            if (!afterReached) {
                afterReached = nodes[i] === afterNode;
                continue;
            }

            if (regex.test(nodes[i].nodeValue)) {
                regex.lastIndex = 0;
                return nodes[i];
            }

            regex.lastIndex = 0;
        }

        return null;
    }

    function makeFold(title) {
        var details = document.createElement('details');
        details.className = 'qiwi-fold';

        var summary = document.createElement('summary');
        summary.textContent = title || '展开内容';

        var body = document.createElement('div');
        body.className = 'qiwi-fold-body';

        details.appendChild(summary);
        details.appendChild(body);

        return {
            details: details,
            body: body
        };
    }

    function enhanceFolds(root) {
        for (var i = 0; i < 4; i++) {
            var openNode = findTextNode(root, FOLD_RE);
            if (!openNode) break;

            var openMatch = openNode.nodeValue.match(FOLD_RE);
            if (!openMatch) break;

            var closeNode = findTextNode(root, CLOSE_FOLD_RE, openNode);
            if (!closeNode) break;

            var title = (openMatch[1] || openMatch[2] || openMatch[3] || '').trim() || '展开内容';
            var openTop = directChildOf(openNode, root);
            var closeTop = directChildOf(closeNode, root);
            if (!openTop || !closeTop) break;

            openNode.nodeValue = openNode.nodeValue.replace(FOLD_RE, '');
            closeNode.nodeValue = closeNode.nodeValue.replace(CLOSE_FOLD_RE, '');

            var fold = makeFold(title);
            var insertBefore = openTop.nextSibling;

            if (openTop === closeTop) {
                openTop.parentNode.insertBefore(fold.details, openTop.nextSibling);
                fold.body.appendChild(openTop);
                continue;
            }

            if (!insertBefore) break;

            root.insertBefore(fold.details, insertBefore);

            var current = insertBefore;
            while (current) {
                var next = current.nextSibling;
                fold.body.appendChild(current);
                if (current === closeTop) break;
                current = next;
            }

            if (isEffectivelyEmpty(openTop)) {
                openTop.parentNode.removeChild(openTop);
            }
        }
    }

    function collectTextMap(root) {
        var text = '';
        var ranges = [];

        getTextNodes(root).forEach(function(node) {
            var start = text.length;
            text += node.nodeValue;
            ranges.push({
                node: node,
                start: start,
                end: text.length
            });
        });

        return {
            text: text,
            ranges: ranges
        };
    }

    function boundaryFromOffset(map, offset, preferNext) {
        var ranges = map.ranges;
        if (!ranges.length) return null;

        for (var i = 0; i < ranges.length; i++) {
            if (preferNext && offset === ranges[i].end && i + 1 < ranges.length) {
                continue;
            }

            if (offset <= ranges[i].end) {
                return {
                    node: ranges[i].node,
                    offset: Math.max(0, Math.min(ranges[i].node.nodeValue.length, offset - ranges[i].start))
                };
            }
        }

        var last = ranges[ranges.length - 1];
        return {
            node: last.node,
            offset: last.node.nodeValue.length
        };
    }

    function textRange(root, start, end) {
        var map = collectTextMap(root);
        var startBoundary = boundaryFromOffset(map, start);
        var endBoundary = boundaryFromOffset(map, end);

        if (!startBoundary || !endBoundary) return null;

        var range = document.createRange();
        range.setStart(startBoundary.node, startBoundary.offset);
        range.setEnd(endBoundary.node, endBoundary.offset);
        return range;
    }

    function insertTextMarker(root, offset, preferNext) {
        var range = textRange(root, offset, offset);
        if (preferNext) {
            var map = collectTextMap(root);
            var boundary = boundaryFromOffset(map, offset, true);
            if (!boundary) return null;
            range = document.createRange();
            range.setStart(boundary.node, boundary.offset);
            range.setEnd(boundary.node, boundary.offset);
        }

        if (!range) return null;

        var marker = document.createComment('qiwi-shortcode');
        range.insertNode(marker);
        return marker;
    }

    function deleteTextBeforeMarker(root, start, marker) {
        var startBoundary = boundaryFromOffset(collectTextMap(root), start);
        if (!startBoundary || !marker.parentNode) return false;

        var range = document.createRange();
        range.setStart(startBoundary.node, startBoundary.offset);
        range.setEndBefore(marker);
        range.deleteContents();
        return true;
    }

    function deleteTextAfterMarker(root, marker, end) {
        var endBoundary = boundaryFromOffset(collectTextMap(root), end);
        if (!endBoundary || !marker.parentNode) return false;

        var range = document.createRange();
        range.setStartAfter(marker);
        range.setEnd(endBoundary.node, endBoundary.offset);
        range.deleteContents();
        return true;
    }

    function wrapMarkedRange(startMarker, endMarker, className) {
        if (!startMarker.parentNode || !endMarker.parentNode) return false;

        var range = document.createRange();
        range.setStartAfter(startMarker);
        range.setEndBefore(endMarker);

        var span = document.createElement('span');
        span.className = className;
        span.appendChild(range.extractContents());
        startMarker.parentNode.insertBefore(span, startMarker.nextSibling);

        startMarker.parentNode.removeChild(startMarker);
        endMarker.parentNode.removeChild(endMarker);
        return true;
    }

    function findInlineShortcode(root) {
        var map = collectTextMap(root);
        var pattern = new RegExp('\\[mark(?:\\s+color=(["\\\']?)([a-zA-Z]+)\\1)?\\]([\\s\\S]*?)\\[\\/mark\\]|\\[(' + COLOR_PATTERN + ')\\]([\\s\\S]*?)\\[\\/\\4\\]', 'i');
        var match = map.text.match(pattern);

        if (!match) return null;

        var isMark = match[3] !== undefined;
        var content = isMark ? match[3] : match[5];
        var closeLength = isMark ? '[/mark]'.length : ('[/' + match[4] + ']').length;
        var openLength = match[0].length - content.length - closeLength;
        var start = match.index;
        var contentStart = start + openLength;
        var closeStart = contentStart + content.length;

        return {
            start: start,
            contentStart: contentStart,
            closeStart: closeStart,
            end: start + match[0].length,
            contentLength: content.length,
            className: isMark ? 'qiwi-mark qiwi-mark-' + sanitizeColor(match[2] || 'yellow') : 'qiwi-text-' + sanitizeColor(match[4])
        };
    }

    function enhanceInlineShortcodes(root) {
        for (var i = 0; i < 80; i++) {
            var shortcode = findInlineShortcode(root);
            if (!shortcode) break;

            var endMarker = insertTextMarker(root, shortcode.closeStart, true);
            var startMarker = insertTextMarker(root, shortcode.contentStart);
            if (!startMarker || !endMarker) break;

            if (!deleteTextAfterMarker(root, endMarker, shortcode.end)) break;
            if (!deleteTextBeforeMarker(root, shortcode.start, startMarker)) break;
            if (!wrapMarkedRange(startMarker, endMarker, shortcode.className)) break;
        }
    }

    function enhancePreview(element) {
        if (!element || element.dataset.qiwiShortcodesRendering === '1') return;

        element.classList.add('qiwi-admin-preview-shortcodes');
        if (!hasShortcodeText(element)) return;

        element.dataset.qiwiShortcodesRendering = '1';
        enhanceFolds(element);
        enhanceInlineShortcodes(element);
        delete element.dataset.qiwiShortcodesRendering;
    }

    function enhanceAllPreviews() {
        document.querySelectorAll('#wmd-preview, .wmd-preview').forEach(enhancePreview);
    }

    function renderShortcodes(html) {
        var container = document.createElement('div');
        container.innerHTML = html || '';
        enhancePreview(container);
        return container.innerHTML;
    }

    function debounce(fn, delay) {
        var timer = null;
        return function() {
            window.clearTimeout(timer);
            timer = window.setTimeout(fn, delay);
        };
    }

    window.QIWI_ADMIN_EDITOR_SHORTCODES.renderShortcodes = renderShortcodes;
    window.QIWI_ADMIN_EDITOR_SHORTCODES.enhanceAll = enhanceAllPreviews;
    window.QIWI_ADMIN_EDITOR_SHORTCODES.enhancePreview = enhancePreview;

    var scheduleEnhance = debounce(enhanceAllPreviews, 80);

    document.addEventListener('DOMContentLoaded', function() {
        enhanceAllPreviews();

        document.addEventListener('input', function(event) {
            if (event.target && event.target.id === 'text') {
                scheduleEnhance();
            }
        }, true);

        var observer = new MutationObserver(function(mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var target = mutations[i].target;
                if (target && target.dataset && target.dataset.qiwiShortcodesRendering === '1') {
                    continue;
                }

                if (target && (target.id === 'wmd-preview' || (target.closest && target.closest('#wmd-preview')))) {
                    scheduleEnhance();
                    break;
                }
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true
        });
    });
})();
