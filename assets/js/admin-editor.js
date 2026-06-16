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

    function normalizePlogMode(mode) {
        mode = String(mode || '').toLowerCase().trim();
        if (['masonry', 'waterfall', '瀑布流', '瀑布'].indexOf(mode) !== -1) return 'masonry';
        if (['justified', 'gallery', '画廊'].indexOf(mode) !== -1) return 'justified';
        if (['stream', 'timeline', '时间流'].indexOf(mode) !== -1) return 'stream';
        return 'grid';
    }

    function normalizePlogSort(sort) {
        sort = String(sort || '').toLowerCase().trim();
        if (['date-asc', 'date_asc', 'oldest', '最早'].indexOf(sort) !== -1) return 'date-asc';
        if (['title-asc', 'title', '标题'].indexOf(sort) !== -1) return 'title-asc';
        if (['title-desc'].indexOf(sort) !== -1) return 'title-desc';
        if (['album-asc', 'album', '图集'].indexOf(sort) !== -1) return 'album-asc';
        if (['album-desc'].indexOf(sort) !== -1) return 'album-desc';
        if (['manual', 'order', 'source', '手动'].indexOf(sort) !== -1) return 'manual';
        return 'date-desc';
    }

    function normalizePlogDateDisplay(value, fallback) {
        value = String(value || '').toLowerCase().trim();
        if (['hide', '0', 'false', 'no', 'off', '隐藏'].indexOf(value) !== -1) return 'hide';
        if (['inherit', 'default', '默认'].indexOf(value) !== -1) return 'inherit';
        if (['show', '1', 'true', 'yes', 'on', '显示'].indexOf(value) !== -1) return 'show';
        return fallback || 'show';
    }

    function normalizePlogDatePrecision(value, fallback) {
        value = String(value || '').toLowerCase().trim();
        if (['date', 'day', '日期'].indexOf(value) !== -1) return 'date';
        if (['datetime', 'time', 'full', '详细'].indexOf(value) !== -1) return 'datetime';
        if (['inherit', 'default', '默认'].indexOf(value) !== -1) return 'inherit';
        if (['auto', '自动'].indexOf(value) !== -1) return 'auto';
        return fallback || 'auto';
    }

    function plogKeyName(key) {
        key = String(key || '').toLowerCase().trim();
        var map = {
            title: 'title',
            '标题': 'title',
            desc: 'desc',
            description: 'desc',
            '描述': 'desc',
            album: 'album',
            '图集': 'album',
            date: 'date',
            time: 'date',
            timestamp: 'date',
            uploaded: 'date',
            '上传日期': 'date',
            '日期': 'date',
            '时间': 'date',
            datedisplay: 'dateDisplay',
            'date-display': 'dateDisplay',
            date_display: 'dateDisplay',
            showdate: 'dateDisplay',
            'show-date': 'dateDisplay',
            show_date: 'dateDisplay',
            '展示时间': 'dateDisplay',
            '显示时间': 'dateDisplay',
            dateprecision: 'datePrecision',
            'date-precision': 'datePrecision',
            date_precision: 'datePrecision',
            timeprecision: 'datePrecision',
            'time-precision': 'datePrecision',
            time_precision: 'datePrecision',
            '时间颗粒度': 'datePrecision',
            '时间精度': 'datePrecision',
            sort: 'sort',
            '排序': 'sort',
            mode: 'mode',
            '模式': 'mode',
            thumb: 'thumb',
            thumbnail: 'thumb',
            full: 'full',
            original: 'full',
            src: 'src',
            url: 'src',
            w: 'w',
            width: 'w',
            h: 'h',
            height: 'h'
        };
        return map[key] || '';
    }

    function makeEmptyPlogPhoto() {
        return {
            title: '',
            src: '',
            thumb: '',
            full: '',
            desc: '',
            album: '',
            date: '',
            dateDisplay: 'inherit',
            datePrecision: 'inherit',
            w: '4',
            h: '3'
        };
    }

    function parsePlogImageLine(line) {
        var match = String(line || '').match(/!\[([^\]]*)\]\(\s*([^\s\)]+)(?:\s+"([^"]*)")?\s*\)/);
        if (!match) return null;
        var photo = makeEmptyPlogPhoto();
        photo.title = (match[1] || '').trim();
        photo.src = (match[2] || '').trim();
        photo.thumb = photo.src;
        photo.full = photo.src;
        photo.desc = (match[3] || '').trim();
        return photo;
    }

    function parsePlogContent(text) {
        var lines = String(text || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
        var data = {
            title: '',
            description: '',
            mode: 'grid',
            sort: 'date-desc',
            dateDisplay: 'show',
            datePrecision: 'auto',
            photos: []
        };
        var description = [];
        var current = null;

        function finishPhoto() {
            if (!current) return;
            if (String(current.src || '').trim() !== '') {
                if (!String(current.title || '').trim()) current.title = '未命名照片';
                if (!String(current.thumb || '').trim()) current.thumb = current.src;
                if (!String(current.full || '').trim()) current.full = current.src;
                current.dateDisplay = normalizePlogDateDisplay(current.dateDisplay, 'inherit');
                current.datePrecision = normalizePlogDatePrecision(current.datePrecision, 'inherit');
                data.photos.push(current);
            }
            current = null;
        }

        lines.forEach(function(line) {
            var trimmed = line.trim();
            var image = parsePlogImageLine(trimmed);
            if (image) {
                finishPhoto();
                current = image;
                return;
            }

            var pair = line.match(/^\s*([^:：]{1,32})\s*[:：]\s*(.*?)\s*$/);
            if (pair) {
                var key = plogKeyName(pair[1]);
                var value = (pair[2] || '').trim();
                if (current) {
                    if (key === 'date') current.date = value;
                    if (key === 'title') current.title = value;
                    if (key === 'desc') current.desc = value;
                    if (key === 'album') current.album = value;
                    if (key === 'thumb') current.thumb = value;
                    if (key === 'full') current.full = value;
                    if (key === 'src') {
                        current.src = value;
                        current.thumb = value;
                        current.full = value;
                    }
                    if (key === 'dateDisplay') current.dateDisplay = normalizePlogDateDisplay(value, 'inherit');
                    if (key === 'datePrecision') current.datePrecision = normalizePlogDatePrecision(value, 'inherit');
                    if (key === 'w' || key === 'h') current[key] = String(Math.max(1, parseInt(value, 10) || 1));
                } else {
                    if (key === 'mode') data.mode = normalizePlogMode(value);
                    if (key === 'sort') data.sort = normalizePlogSort(value);
                    if (key === 'dateDisplay') data.dateDisplay = normalizePlogDateDisplay(value, 'show');
                    if (key === 'datePrecision') data.datePrecision = normalizePlogDatePrecision(value, 'auto');
                    if (key === 'title') data.title = value;
                    if (key === 'desc') description.push(value);
                }
                return;
            }

            if (current) {
                if (trimmed) {
                    current.desc = String(current.desc || '').trim()
                        ? String(current.desc || '').trim() + '\n' + trimmed
                        : trimmed;
                }
                return;
            }

            var heading = trimmed.match(/^#\s+(.+)$/);
            if (heading) {
                data.title = heading[1].trim();
                return;
            }

            if (trimmed) description.push(trimmed);
        });

        finishPhoto();
        data.description = description.join('\n').trim();
        return data;
    }

    function plogSafeLine(value) {
        return String(value || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n').trim();
    }

    function plogEscapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function(char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function renderPlogContent(data) {
        var lines = [];
        var title = plogSafeLine(data.title) || 'Plog';
        lines.push('# ' + title.replace(/\n+/g, ' '));
        var description = plogSafeLine(data.description);
        if (description) {
            lines.push(description);
        }
        lines.push('mode: ' + normalizePlogMode(data.mode));
        lines.push('sort: ' + normalizePlogSort(data.sort));
        lines.push('dateDisplay: ' + normalizePlogDateDisplay(data.dateDisplay, 'show'));
        lines.push('datePrecision: ' + normalizePlogDatePrecision(data.datePrecision, 'auto'));

        (data.photos || []).forEach(function(photo) {
            var src = plogSafeLine(photo.src);
            if (!src) return;
            var title = plogSafeLine(photo.title) || '未命名照片';
            lines.push('');
            lines.push('![' + title.replace(/\]/g, '\\]') + '](' + src.replace(/\s+/g, '%20') + ')');
            lines.push('title: ' + title.replace(/\n+/g, ' '));
            var desc = plogSafeLine(photo.desc);
            if (desc) {
                var descLines = desc.split('\n');
                lines.push('desc: ' + descLines.shift());
                descLines.forEach(function(line) {
                    if (line.trim()) lines.push(line.trim());
                });
            }
            if (plogSafeLine(photo.album)) lines.push('album: ' + plogSafeLine(photo.album).replace(/\n+/g, ' '));
            if (plogSafeLine(photo.date)) lines.push('date: ' + plogSafeLine(photo.date).replace(/\n+/g, ' '));
            if (normalizePlogDateDisplay(photo.dateDisplay, 'inherit') !== 'inherit') {
                lines.push('dateDisplay: ' + normalizePlogDateDisplay(photo.dateDisplay, 'inherit'));
            }
            if (normalizePlogDatePrecision(photo.datePrecision, 'inherit') !== 'inherit') {
                lines.push('datePrecision: ' + normalizePlogDatePrecision(photo.datePrecision, 'inherit'));
            }
            if (plogSafeLine(photo.thumb) && plogSafeLine(photo.thumb) !== src) lines.push('thumb: ' + plogSafeLine(photo.thumb));
            if (plogSafeLine(photo.full) && plogSafeLine(photo.full) !== src) lines.push('full: ' + plogSafeLine(photo.full));
            lines.push('w: ' + Math.max(1, parseInt(photo.w, 10) || 4));
            lines.push('h: ' + Math.max(1, parseInt(photo.h, 10) || 3));
        });

        return lines.join('\n').replace(/\n{3,}/g, '\n\n').trim() + '\n';
    }

    function parsePlogDateForSort(value) {
        value = String(value || '').trim();
        if (!value) return 0;
        if (/^\d{10,13}$/.test(value)) {
            var timestamp = parseInt(value, 10) || 0;
            return value.length === 13 ? Math.floor(timestamp / 1000) : timestamp;
        }
        var normalized = value
            .replace(/^(\d{4})(\d{2})(\d{2})(\s+\d{1,2}:\d{2}(?::\d{2})?)?$/, '$1-$2-$3$4')
            .replace(/^(\d{4})[/.](\d{1,2})[/.](\d{1,2})(\s+\d{1,2}:\d{2}(?::\d{2})?)?$/, '$1-$2-$3$4');
        var parsed = Date.parse(normalized.replace(' ', 'T'));
        return isNaN(parsed) ? 0 : Math.floor(parsed / 1000);
    }

    function parsePlogDateParts(value) {
        value = String(value || '').trim();
        if (!value) return null;

        if (/^\d{10,13}$/.test(value)) {
            var timestamp = parseInt(value, 10) || 0;
            var date = new Date(value.length === 13 ? timestamp : timestamp * 1000);
            if (!isNaN(date.getTime())) {
                return {
                    date: date,
                    hasTime: true
                };
            }
        }

        var normalized = value
            .replace(/^(\d{4})(\d{2})(\d{2})(\s+\d{1,2}:\d{2}(?::\d{2})?)?$/, '$1-$2-$3$4')
            .replace(/^(\d{4})[/.](\d{1,2})[/.](\d{1,2})(\s+\d{1,2}:\d{2}(?::\d{2})?)?$/, '$1-$2-$3$4');
        var match = normalized.match(/^(\d{4})-(\d{1,2})-(\d{1,2})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$/);
        if (match) {
            var parsed = new Date(
                parseInt(match[1], 10),
                parseInt(match[2], 10) - 1,
                parseInt(match[3], 10),
                parseInt(match[4] || '0', 10),
                parseInt(match[5] || '0', 10),
                parseInt(match[6] || '0', 10)
            );
            if (!isNaN(parsed.getTime())) {
                return {
                    date: parsed,
                    hasTime: !!match[4]
                };
            }
        }

        var fallback = new Date(normalized);
        if (!isNaN(fallback.getTime())) {
            return {
                date: fallback,
                hasTime: /\d{1,2}:\d{2}/.test(value)
            };
        }

        return null;
    }

    function padPlogDatePart(value) {
        return String(value).padStart(2, '0');
    }

    function formatPlogDateParts(parts, precision) {
        if (!parts || !parts.date) return '';
        precision = normalizePlogDatePrecision(precision, 'auto');
        var date = parts.date;
        var output = [
            date.getFullYear(),
            padPlogDatePart(date.getMonth() + 1),
            padPlogDatePart(date.getDate())
        ].join('-');

        if (precision === 'datetime' || (precision === 'auto' && parts.hasTime)) {
            output += ' ' + padPlogDatePart(date.getHours()) + ':' + padPlogDatePart(date.getMinutes());
        }

        return output;
    }

    function effectivePlogDatePrecision(photo, data) {
        var precision = normalizePlogDatePrecision(photo && photo.datePrecision, 'inherit');
        if (precision === 'inherit') {
            precision = normalizePlogDatePrecision(data && data.datePrecision, 'auto');
        }
        return precision;
    }

    function normalizePlogPhotoDate(photo, data) {
        if (!photo || !photo.date) return;
        var parts = parsePlogDateParts(photo.date);
        if (!parts) return;
        photo.date = formatPlogDateParts(parts, effectivePlogDatePrecision(photo, data)) || photo.date;
    }

    function normalizePlogDates(data) {
        (data.photos || []).forEach(function(photo) {
            normalizePlogPhotoDate(photo, data);
        });
        return data;
    }

    function sortPlogPhotos(photos, sort) {
        var next = (photos || []).slice();
        sort = normalizePlogSort(sort);
        next.sort(function(a, b) {
            if (sort === 'date-asc') return parsePlogDateForSort(a.date) - parsePlogDateForSort(b.date);
            if (sort === 'title-asc') return String(a.title || '').localeCompare(String(b.title || ''), 'zh-Hans-CN');
            if (sort === 'title-desc') return String(b.title || '').localeCompare(String(a.title || ''), 'zh-Hans-CN');
            if (sort === 'album-asc') return (String(a.album || '') + String(a.title || '')).localeCompare(String(b.album || '') + String(b.title || ''), 'zh-Hans-CN');
            if (sort === 'album-desc') return (String(b.album || '') + String(b.title || '')).localeCompare(String(a.album || '') + String(a.title || ''), 'zh-Hans-CN');
            return parsePlogDateForSort(b.date) - parsePlogDateForSort(a.date);
        });
        return next;
    }

    function initPlogEditorEnhancer() {
        var textarea = getEditorTextarea();
        if (!textarea || textarea.dataset.qiwiPlogEditor === '1') return;

        var templateField = document.querySelector('[name="template"], #template');
        var rawHost = textarea.closest('.wmd-panel') || textarea.parentNode;
        if (!rawHost || !rawHost.parentNode) return;

        textarea.dataset.qiwiPlogEditor = '1';

        var marker = document.createComment('qiwi-plog-editor');
        rawHost.parentNode.insertBefore(marker, rawHost);

        var shell = document.createElement('section');
        shell.className = 'qiwi-plog-editor';
        shell.hidden = true;
        shell.innerHTML =
            '<div class="qiwi-plog-editor-head">' +
                '<div><strong>Plog 编辑器</strong><span data-plog-status></span></div>' +
                '<div class="qiwi-plog-tabs" role="tablist">' +
                    '<button type="button" class="qiwi-plog-tab is-active" data-plog-tab="visual" aria-selected="true">可视化</button>' +
                    '<button type="button" class="qiwi-plog-tab" data-plog-tab="raw" aria-selected="false">Raw</button>' +
                '</div>' +
            '</div>' +
            '<div class="qiwi-plog-pane is-active" data-plog-pane="visual">' +
                '<div class="qiwi-plog-fields">' +
                    '<label>标题<input type="text" data-plog-field="title"></label>' +
                    '<label>展示模式<select data-plog-field="mode">' +
                        '<option value="grid">网格</option>' +
                        '<option value="masonry">瀑布流</option>' +
                        '<option value="justified">画廊</option>' +
                        '<option value="stream">时间流</option>' +
                    '</select></label>' +
                    '<label>前台排序<select data-plog-field="sort">' +
                        '<option value="date-desc">日期新到旧</option>' +
                        '<option value="date-asc">日期旧到新</option>' +
                        '<option value="title-asc">标题 A-Z</option>' +
                        '<option value="title-desc">标题 Z-A</option>' +
                        '<option value="album-asc">图集 A-Z</option>' +
                        '<option value="album-desc">图集 Z-A</option>' +
                        '<option value="manual">按正文顺序</option>' +
                    '</select></label>' +
                    '<label>时间展示<select data-plog-field="dateDisplay">' +
                        '<option value="show">显示</option>' +
                        '<option value="hide">隐藏</option>' +
                    '</select></label>' +
                    '<label>时间颗粒度<select data-plog-field="datePrecision">' +
                        '<option value="auto">自动</option>' +
                        '<option value="date">只显示日期</option>' +
                        '<option value="datetime">显示详细时间</option>' +
                    '</select></label>' +
                    '<label class="qiwi-plog-wide">描述<textarea rows="3" data-plog-field="description"></textarea></label>' +
                '</div>' +
                '<div class="qiwi-plog-toolbar">' +
                    '<button type="button" class="qiwi-plog-button is-primary" data-plog-action="add">添加图片</button>' +
                    '<button type="button" class="qiwi-plog-button" data-plog-sort-once="date-desc">按日期新到旧</button>' +
                    '<button type="button" class="qiwi-plog-button" data-plog-sort-once="date-asc">按日期旧到新</button>' +
                    '<button type="button" class="qiwi-plog-button" data-plog-sort-once="title-asc">按标题</button>' +
                    '<button type="button" class="qiwi-plog-button" data-plog-sort-once="album-asc">按图集</button>' +
                '</div>' +
                '<div class="qiwi-plog-photo-list" data-plog-photo-list></div>' +
            '</div>' +
            '<div class="qiwi-plog-pane qiwi-plog-raw" data-plog-pane="raw">' +
                '<div class="qiwi-plog-raw-head"><strong>Raw</strong><span>保存时提交这里的正文</span></div>' +
            '</div>';

        marker.parentNode.insertBefore(shell, marker.nextSibling);
        var rawColumn = shell.querySelector('.qiwi-plog-raw');

        var fields = {};
        shell.querySelectorAll('[data-plog-field]').forEach(function(field) {
            fields[field.getAttribute('data-plog-field')] = field;
        });
        var list = shell.querySelector('[data-plog-photo-list]');
        var status = shell.querySelector('[data-plog-status]');
        var data = parsePlogContent(textarea.value);
        var isRendering = false;
        var isSyncing = false;
        var draggedPhoto = null;

        function isPlogTemplate() {
            if (!templateField) return false;
            var value = String(templateField.value || '').toLowerCase().trim();
            return value === 'page-plog.php' || value === 'page-plog';
        }

        function templateLabelLooksPlog() {
            if (!templateField || !templateField.options) return false;
            var option = templateField.options[templateField.selectedIndex];
            return !!(option && /plog/i.test(option.textContent || ''));
        }

        function renderPhotos() {
            list.innerHTML = '';
            if (!data.photos.length) {
                var empty = document.createElement('div');
                empty.className = 'qiwi-plog-empty';
                empty.textContent = '还没有图片。';
                list.appendChild(empty);
                return;
            }

            data.photos.forEach(function(photo, index) {
                var card = document.createElement('article');
                var expanded = photo._open === true;
                card.className = 'qiwi-plog-photo' + (expanded ? ' is-open' : '');
                card.dataset.plogIndex = String(index);
                var preview = plogSafeLine(photo.thumb) || plogSafeLine(photo.src);
                card.innerHTML =
                    '<button type="button" class="qiwi-plog-photo-summary" data-plog-photo-action="toggle" aria-expanded="' + (expanded ? 'true' : 'false') + '">' +
                        '<span class="qiwi-plog-photo-drag" data-plog-drag draggable="true" title="拖拽排序" aria-hidden="true">↕</span>' +
                        '<span class="qiwi-plog-photo-preview">' +
                            (preview ? '<img src="' + plogEscapeHtml(preview) + '" alt="">' : '<span>IMG</span>') +
                        '</span>' +
                        '<span class="qiwi-plog-photo-title">' +
                            '<strong>' + plogEscapeHtml(photo.title || '未命名照片') + '</strong>' +
                            '<em>' + plogEscapeHtml([photo.album, photo.date].filter(Boolean).join(' · ') || '未填写时间') + '</em>' +
                        '</span>' +
                        '<span class="qiwi-plog-photo-arrow">⌄</span>' +
                    '</button>' +
                    '<div class="qiwi-plog-photo-editor">' +
                        '<div class="qiwi-plog-photo-top">' +
                            '<div class="qiwi-plog-photo-actions">' +
                                '<button type="button" data-plog-photo-action="up" aria-label="上移">↑</button>' +
                                '<button type="button" data-plog-photo-action="down" aria-label="下移">↓</button>' +
                                '<button type="button" data-plog-photo-action="delete" aria-label="删除">×</button>' +
                            '</div>' +
                        '</div>' +
                        '<div class="qiwi-plog-photo-fields">' +
                            '<label>标题<input type="text" data-plog-photo-field="title" value="' + plogEscapeHtml(photo.title || '') + '"></label>' +
                            '<label>图片 URL<input type="url" data-plog-photo-field="src" value="' + plogEscapeHtml(photo.src || '') + '"></label>' +
                            '<label>缩略图<input type="url" data-plog-photo-field="thumb" value="' + plogEscapeHtml(photo.thumb || '') + '"></label>' +
                            '<label>原图<input type="url" data-plog-photo-field="full" value="' + plogEscapeHtml(photo.full || '') + '"></label>' +
                            '<label>图集<input type="text" data-plog-photo-field="album" value="' + plogEscapeHtml(photo.album || '') + '"></label>' +
                            '<label>时间<input type="text" data-plog-photo-field="date" value="' + plogEscapeHtml(photo.date || '') + '" placeholder="2026-06-15 19:50"></label>' +
                            '<label>时间展示<select data-plog-photo-field="dateDisplay">' +
                                '<option value="inherit">跟随全局</option>' +
                                '<option value="show">显示</option>' +
                                '<option value="hide">隐藏</option>' +
                            '</select></label>' +
                            '<label>时间颗粒度<select data-plog-photo-field="datePrecision">' +
                                '<option value="inherit">跟随全局</option>' +
                                '<option value="auto">自动</option>' +
                                '<option value="date">只显示日期</option>' +
                                '<option value="datetime">显示详细时间</option>' +
                            '</select></label>' +
                            '<label>宽<input type="number" min="1" step="1" data-plog-photo-field="w" value="' + plogEscapeHtml(photo.w || '4') + '"></label>' +
                            '<label>高<input type="number" min="1" step="1" data-plog-photo-field="h" value="' + plogEscapeHtml(photo.h || '3') + '"></label>' +
                            '<label class="qiwi-plog-wide">描述<textarea rows="2" data-plog-photo-field="desc">' + plogEscapeHtml(photo.desc || '') + '</textarea></label>' +
                        '</div>' +
                    '</div>';
                list.appendChild(card);
                var display = card.querySelector('[data-plog-photo-field="dateDisplay"]');
                var precision = card.querySelector('[data-plog-photo-field="datePrecision"]');
                if (display) display.value = normalizePlogDateDisplay(photo.dateDisplay, 'inherit');
                if (precision) precision.value = normalizePlogDatePrecision(photo.datePrecision, 'inherit');
            });
        }

        function render() {
            isRendering = true;
            normalizePlogDates(data);
            fields.title.value = data.title || '';
            fields.description.value = data.description || '';
            fields.mode.value = normalizePlogMode(data.mode);
            fields.sort.value = normalizePlogSort(data.sort);
            fields.dateDisplay.value = normalizePlogDateDisplay(data.dateDisplay, 'show');
            fields.datePrecision.value = normalizePlogDatePrecision(data.datePrecision, 'auto');
            renderPhotos();
            if (status) status.textContent = data.photos.length + ' 张图片';
            isRendering = false;
        }

        function syncRaw() {
            if (isRendering) return;
            isSyncing = true;
            normalizePlogDates(data);
            textarea.value = renderPlogContent(data);
            dispatchEditorInput(textarea);
            isSyncing = false;
            if (status) status.textContent = data.photos.length + ' 张图片，已同步';
        }

        function readGlobalFields() {
            data.title = fields.title.value;
            data.description = fields.description.value;
            data.mode = normalizePlogMode(fields.mode.value);
            data.sort = normalizePlogSort(fields.sort.value);
            data.dateDisplay = normalizePlogDateDisplay(fields.dateDisplay.value, 'show');
            data.datePrecision = normalizePlogDatePrecision(fields.datePrecision.value, 'auto');
        }

        function reorderPhotosFromDom() {
            var next = [];
            list.querySelectorAll('.qiwi-plog-photo').forEach(function(card) {
                var index = parseInt(card.dataset.plogIndex, 10);
                if (data.photos[index]) next.push(data.photos[index]);
            });
            if (next.length === data.photos.length) {
                data.photos = next;
                data.sort = 'manual';
            }
        }

        function openPlogTab(name) {
            name = name === 'raw' ? 'raw' : 'visual';
            shell.querySelectorAll('[data-plog-tab]').forEach(function(button) {
                var active = button.getAttribute('data-plog-tab') === name;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            shell.querySelectorAll('[data-plog-pane]').forEach(function(pane) {
                pane.classList.toggle('is-active', pane.getAttribute('data-plog-pane') === name);
            });
        }

        function placeRawHost(active) {
            var parent = marker.parentNode;
            if (!parent) return;
            if (active) {
                if (rawHost.parentNode !== rawColumn) rawColumn.appendChild(rawHost);
            } else if (rawHost.parentNode !== parent) {
                parent.insertBefore(rawHost, shell.nextSibling);
            }
        }

        function setActive(active) {
            placeRawHost(active);
            shell.hidden = !active;
            document.body.classList.toggle('qiwi-plog-editor-page', active);
            if (active) {
                data = parsePlogContent(textarea.value);
                if (!data.title) {
                    var titleField = document.getElementById('title') || document.querySelector('[name="title"]');
                    data.title = titleField ? titleField.value || 'Plog' : 'Plog';
                }
                render();
                syncRaw();
            }
        }

        function updateFromVisualTarget(target) {
            if (!target || isRendering) return;
            if (target.hasAttribute('data-plog-field')) {
                var globalField = target.getAttribute('data-plog-field');
                readGlobalFields();
                if (globalField === 'datePrecision') {
                    normalizePlogDates(data);
                    render();
                }
                syncRaw();
                return;
            }

            var photoField = target.getAttribute('data-plog-photo-field');
            if (!photoField) return;
            var card = target.closest('.qiwi-plog-photo');
            var index = card ? parseInt(card.dataset.plogIndex, 10) : -1;
            if (!data.photos[index]) return;
            data.photos[index][photoField] = target.value;
            if (photoField === 'src') {
                if (!data.photos[index].thumb) data.photos[index].thumb = target.value;
                if (!data.photos[index].full) data.photos[index].full = target.value;
            }
            if (photoField === 'date' || photoField === 'datePrecision') {
                normalizePlogPhotoDate(data.photos[index], data);
                render();
            }
            syncRaw();
        }

        shell.addEventListener('input', function(event) {
            updateFromVisualTarget(event.target);
        });

        shell.addEventListener('change', function(event) {
            updateFromVisualTarget(event.target);
        });

        shell.addEventListener('click', function(event) {
            var add = event.target.closest('[data-plog-action="add"]');
            if (add) {
                event.preventDefault();
                var photo = makeEmptyPlogPhoto();
                photo._open = true;
                data.photos.push(photo);
                data.sort = 'manual';
                render();
                syncRaw();
                var last = list.querySelector('.qiwi-plog-photo:last-child input[data-plog-photo-field="title"]');
                if (last) last.focus();
                return;
            }

            var tab = event.target.closest('[data-plog-tab]');
            if (tab) {
                event.preventDefault();
                openPlogTab(tab.getAttribute('data-plog-tab'));
                return;
            }

            var sortButton = event.target.closest('[data-plog-sort-once]');
            if (sortButton) {
                event.preventDefault();
                data.photos = sortPlogPhotos(data.photos, sortButton.getAttribute('data-plog-sort-once'));
                data.sort = 'manual';
                render();
                syncRaw();
                return;
            }

            var actionButton = event.target.closest('[data-plog-photo-action]');
            if (!actionButton) return;
            if (event.target.closest('[data-plog-drag]')) return;
            event.preventDefault();
            var card = actionButton.closest('.qiwi-plog-photo');
            var index = card ? parseInt(card.dataset.plogIndex, 10) : -1;
            if (!data.photos[index]) return;
            var action = actionButton.getAttribute('data-plog-photo-action');
            if (action === 'toggle') {
                data.photos[index]._open = data.photos[index]._open !== true;
                render();
                return;
            } else if (action === 'delete') {
                data.photos.splice(index, 1);
            } else if (action === 'up' && index > 0) {
                data.photos.splice(index - 1, 0, data.photos.splice(index, 1)[0]);
            } else if (action === 'down' && index + 1 < data.photos.length) {
                data.photos.splice(index + 1, 0, data.photos.splice(index, 1)[0]);
            }
            data.sort = 'manual';
            render();
            syncRaw();
        });

        list.addEventListener('dragstart', function(event) {
            var handle = event.target.closest('[data-plog-drag]');
            if (!handle) return;
            draggedPhoto = handle.closest('.qiwi-plog-photo');
            if (!draggedPhoto) return;
            draggedPhoto.classList.add('is-dragging');
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', 'plog-photo');
            }
        });

        list.addEventListener('dragover', function(event) {
            if (!draggedPhoto) return;
            var target = event.target.closest('.qiwi-plog-photo');
            if (!target || target === draggedPhoto) return;
            event.preventDefault();
            var rect = target.getBoundingClientRect();
            var before = event.clientY < rect.top + rect.height / 2;
            list.insertBefore(draggedPhoto, before ? target : target.nextSibling);
        });

        list.addEventListener('drop', function(event) {
            if (!draggedPhoto) return;
            event.preventDefault();
            draggedPhoto.classList.remove('is-dragging');
            reorderPhotosFromDom();
            draggedPhoto = null;
            render();
            syncRaw();
        });

        list.addEventListener('dragend', function() {
            if (draggedPhoto) {
                draggedPhoto.classList.remove('is-dragging');
                reorderPhotosFromDom();
                render();
                syncRaw();
            }
            draggedPhoto = null;
        });

        textarea.addEventListener('input', function() {
            if (isSyncing || shell.hidden) return;
            data = parsePlogContent(textarea.value);
            normalizePlogDates(data);
            render();
        });

        if (templateField) {
            templateField.addEventListener('change', function() {
                setActive(isPlogTemplate());
            });
        }

        setActive(isPlogTemplate() || (!templateField && templateLabelLooksPlog()));
    }

    window.QIWI_ADMIN_EDITOR_SHORTCODES.renderShortcodes = renderShortcodes;
    window.QIWI_ADMIN_EDITOR_SHORTCODES.enhanceAll = enhanceAllPreviews;
    window.QIWI_ADMIN_EDITOR_SHORTCODES.enhancePreview = enhancePreview;

    var scheduleEnhance = debounce(enhanceAllPreviews, 80);

    document.addEventListener('DOMContentLoaded', function() {
        enhanceAllPreviews();
        initToolbarEnhancer();
        initPlogEditorEnhancer();

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
