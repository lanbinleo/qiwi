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
    var COLOR_LABELS = {
        red: '红',
        orange: '橙',
        yellow: '黄',
        green: '绿',
        cyan: '青',
        blue: '蓝',
        purple: '紫'
    };
    var FOLD_RE = /\[fold([^\]]*)\]/i;
    var CLOSE_FOLD_RE = /\[\/fold\]/i;

    function sanitizeColor(color) {
        color = String(color || '').toLowerCase().trim();
        return COLORS.indexOf(color) !== -1 ? color : 'yellow';
    }

    function parseAttrs(text) {
        var attrs = {};
        String(text || '').replace(/([a-zA-Z][a-zA-Z0-9_-]*)\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s\]]+))/g, function(_, name, doubleQuoted, singleQuoted, bare) {
            attrs[String(name || '').toLowerCase()] = (doubleQuoted || singleQuoted || bare || '').trim();
            return _;
        });
        return attrs;
    }

    function boolAttr(attrs, name, fallback) {
        if (!Object.prototype.hasOwnProperty.call(attrs, name)) return fallback;

        var value = String(attrs[name] || '').toLowerCase().trim();
        if (['1', 'true', 'yes', 'on', 'open'].indexOf(value) !== -1) return true;
        if (['0', 'false', 'no', 'off', 'closed'].indexOf(value) !== -1) return false;
        return fallback;
    }

    function foldOptionsFromAttrs(attrs) {
        var variant = String(attrs.variant || attrs.style || '').toLowerCase().trim();
        var noDivider = ['plain', 'clean', 'no-divider', 'nodivider'].indexOf(variant) !== -1 || boolAttr(attrs, 'divider', true) === false;
        var isOpen = boolAttr(attrs, 'open', true);

        if (String(attrs.default || '').toLowerCase().trim() === 'closed' || boolAttr(attrs, 'closed', false)) {
            isOpen = false;
        }

        return {
            title: String(attrs.title || '').trim() || '展开内容',
            isOpen: isOpen,
            noDivider: noDivider
        };
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

    function makeFold(options) {
        var details = document.createElement('details');
        details.className = 'qiwi-fold';
        if (options && options.noDivider) {
            details.className += ' qiwi-fold-no-divider';
        }
        if (!options || options.isOpen !== false) {
            details.open = true;
        }

        var summary = document.createElement('summary');
        summary.textContent = options && options.title ? options.title : '展开内容';

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

            var foldOptions = foldOptionsFromAttrs(parseAttrs(openMatch[1] || ''));
            var openTop = directChildOf(openNode, root);
            var closeTop = directChildOf(closeNode, root);
            if (!openTop || !closeTop) break;

            openNode.nodeValue = openNode.nodeValue.replace(FOLD_RE, '');
            closeNode.nodeValue = closeNode.nodeValue.replace(CLOSE_FOLD_RE, '');

            var fold = makeFold(foldOptions);
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

    function getEditorTextarea() {
        return document.getElementById('text') || document.querySelector('textarea[name="text"]');
    }

    function dispatchEditorInput(textarea) {
        ['input', 'change', 'keyup'].forEach(function(type) {
            textarea.dispatchEvent(new Event(type, {
                bubbles: true
            }));
        });
        scheduleEnhance();
    }

    function insertIntoEditor(snippetFactory) {
        var textarea = getEditorTextarea();
        if (!textarea) return;

        var start = textarea.selectionStart || 0;
        var end = textarea.selectionEnd || start;
        var selected = textarea.value.slice(start, end);
        var snippet = snippetFactory(selected);
        var nextCursor = start + snippet.length;

        textarea.focus();

        if (typeof textarea.setRangeText === 'function') {
            textarea.setRangeText(snippet, start, end, 'end');
        } else {
            textarea.value = textarea.value.slice(0, start) + snippet + textarea.value.slice(end);
            textarea.selectionStart = nextCursor;
            textarea.selectionEnd = nextCursor;
        }

        dispatchEditorInput(textarea);
    }

    function wrapInline(openTag, closeTag, fallback) {
        insertIntoEditor(function(selected) {
            var content = selected || fallback;
            return openTag + content + closeTag;
        });
    }

    function insertFoldSnippet(isPlain) {
        insertIntoEditor(function(selected) {
            var content = selected || '这里是折叠内容';
            var attrs = ' title="展开内容" open=true';
            if (isPlain) {
                attrs += ' variant="plain"';
            }
            return '[fold' + attrs + ']\n' + content + '\n[/fold]';
        });
    }

    function makeToolButton(label, title, onClick) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'qiwi-editor-tool-button';
        button.textContent = label;
        button.title = title;
        button.addEventListener('click', function(event) {
            event.preventDefault();
            onClick();
        });
        return button;
    }

    function buildInsertMenu(anchor) {
        var menu = document.createElement('div');
        menu.className = 'qiwi-editor-insert-menu';
        menu.hidden = true;

        function addGroup(title, buttons) {
            var group = document.createElement('div');
            group.className = 'qiwi-editor-tool-group';

            var heading = document.createElement('div');
            heading.className = 'qiwi-editor-tool-title';
            heading.textContent = title;
            group.appendChild(heading);

            var row = document.createElement('div');
            row.className = 'qiwi-editor-tool-row';
            buttons.forEach(function(button) {
                row.appendChild(button);
            });
            group.appendChild(row);
            menu.appendChild(group);
        }

        addGroup('文字颜色', COLORS.map(function(color) {
            return makeToolButton(COLOR_LABELS[color] || color, '插入 ' + color + ' 彩色文字', function() {
                wrapInline('[' + color + ']', '[/' + color + ']', '彩色文字');
                menu.hidden = true;
                anchor.setAttribute('aria-expanded', 'false');
            });
        }));

        addGroup('背景标记', COLORS.map(function(color) {
            return makeToolButton(COLOR_LABELS[color] || color, '插入 ' + color + ' 背景标记', function() {
                wrapInline('[mark color=' + color + ']', '[/mark]', '高亮文字');
                menu.hidden = true;
                anchor.setAttribute('aria-expanded', 'false');
            });
        }));

        addGroup('折叠块', [
            makeToolButton('默认展开', '插入默认展开的 fold', function() {
                insertFoldSnippet(false);
                menu.hidden = true;
                anchor.setAttribute('aria-expanded', 'false');
            }),
            makeToolButton('无分隔线', '插入无标题分隔线的 fold', function() {
                insertFoldSnippet(true);
                menu.hidden = true;
                anchor.setAttribute('aria-expanded', 'false');
            })
        ]);

        return menu;
    }

    function initToolbarEnhancer() {
        var row = document.getElementById('wmd-button-row');
        if (!row || row.dataset.qiwiEditorTools === '1') return;

        row.dataset.qiwiEditorTools = '1';

        var button = document.createElement('li');
        button.className = 'wmd-button qiwi-wmd-button';
        button.id = 'qiwi-shortcode-button';
        button.title = 'Qiwi 快捷插入';
        button.setAttribute('role', 'button');
        button.setAttribute('tabindex', '0');
        button.setAttribute('aria-haspopup', 'true');
        button.setAttribute('aria-expanded', 'false');
        button.innerHTML = '<span>Q</span>';

        var left = 500;
        Array.prototype.forEach.call(row.children, function(item) {
            var value = parseInt(item.style && item.style.left ? item.style.left : '', 10);
            if (!isNaN(value)) {
                left = Math.max(left, value + 50);
            }
        });
        button.style.left = left + 'px';
        row.style.minWidth = Math.max(row.scrollWidth, left + 28) + 'px';

        var menu = buildInsertMenu(button);
        row.appendChild(button);
        row.parentNode.insertBefore(menu, row.nextSibling);

        function toggleMenu() {
            var isOpen = menu.hidden;
            menu.hidden = !isOpen;
            button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (isOpen) {
                var firstButton = menu.querySelector('button');
                if (firstButton) firstButton.focus();
            }
        }

        button.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            toggleMenu();
        });

        button.addEventListener('keydown', function(event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggleMenu();
            } else if (event.key === 'Escape') {
                menu.hidden = true;
                button.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('click', function(event) {
            if (menu.hidden || menu.contains(event.target) || button.contains(event.target)) return;
            menu.hidden = true;
            button.setAttribute('aria-expanded', 'false');
        });
    }

    window.QIWI_ADMIN_EDITOR_SHORTCODES.renderShortcodes = renderShortcodes;
    window.QIWI_ADMIN_EDITOR_SHORTCODES.enhanceAll = enhanceAllPreviews;
    window.QIWI_ADMIN_EDITOR_SHORTCODES.enhancePreview = enhancePreview;

    var scheduleEnhance = debounce(enhanceAllPreviews, 80);

    document.addEventListener('DOMContentLoaded', function() {
        enhanceAllPreviews();
        initToolbarEnhancer();

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
