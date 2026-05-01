(function() {
    function $(selector, root) {
        return (root || document).querySelector(selector);
    }

    function $all(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function(char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function trim(value) {
        return String(value || '').replace(/^\s+|\s+$/g, '');
    }

    function fieldByName(name) {
        return $('[name="' + name + '"], [name="' + name + '[]"], #' + name);
    }

    function fieldRow(input) {
        if (!input) return null;
        return input.closest('li') || input.closest('.typecho-option') || input.parentNode;
    }

    function moveField(name, target) {
        var input = fieldByName(name);
        var row = fieldRow(input);
        if (!row || !target) return null;
        target.appendChild(row);
        row.classList.add('qiwi-managed-field');
        if ($('input[type="radio"]', row)) {
            row.classList.add('qiwi-radio-field');
        }
        if ($('input[type="checkbox"]', row)) {
            row.classList.add('qiwi-checkbox-field');
        }
        return row;
    }

    function moveFields(names, target) {
        names.forEach(function(name) {
            moveField(name, target);
        });
    }

    function parseNav(text) {
        return String(text || '').split(/\r\n|\r|\n/).map(function(rawLine) {
            var line = trim(rawLine);
            if (!line || line.charAt(0) === '#') return null;
            var level = line.charAt(0) === '-' ? 'child' : 'parent';
            if (level === 'child') line = trim(line.slice(1));
            var parts = line.split('|');
            return {
                level: level,
                title: parts[0] || '',
                target: parts[1] || '',
                icon: parts.slice(2).join('|') || ''
            };
        }).filter(Boolean);
    }

    function navToText(rows) {
        return rows.map(function(row) {
            var prefix = row.level === 'child' ? '- ' : '';
            return prefix + [row.title, row.target, row.icon].map(function(value) {
                return String(value || '').replace(/\|/g, ' ');
            }).join('|').replace(/\|+$/g, '');
        }).filter(function(line) {
            return line.replace(/[-|\s]/g, '') !== '';
        }).join('\n');
    }

    function readNavRows(panel) {
        return $all('.qiwi-nav-row', panel).map(function(row) {
            return {
                level: $('[data-nav-field="level"]', row).value,
                title: $('[data-nav-field="title"]', row).value,
                target: $('[data-nav-field="target"]', row).value,
                icon: $('[data-nav-field="icon"]', row).value
            };
        });
    }

    function renderNavRow(item) {
        var row = document.createElement('div');
        row.className = 'qiwi-nav-row';
        row.setAttribute('data-level', item.level || 'parent');
        row.innerHTML =
            '<select data-nav-field="level" aria-label="层级">' +
                '<option value="parent"' + (item.level !== 'child' ? ' selected' : '') + '>主导航</option>' +
                '<option value="child"' + (item.level === 'child' ? ' selected' : '') + '>子菜单</option>' +
            '</select>' +
            '<input type="text" data-nav-field="title" placeholder="标题" value="' + escapeHtml(item.title) + '">' +
            '<input type="text" data-nav-field="target" placeholder="链接、slug 或 template:xxx.php" value="' + escapeHtml(item.target) + '">' +
            '<input type="text" data-nav-field="icon" placeholder="Font Awesome 图标类" value="' + escapeHtml(item.icon) + '">' +
            '<div class="qiwi-row-actions">' +
                '<button type="button" class="qiwi-admin-button qiwi-icon-button" data-nav-action="up" title="上移" aria-label="上移"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button>' +
                '<button type="button" class="qiwi-admin-button qiwi-icon-button" data-nav-action="down" title="下移" aria-label="下移"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i></button>' +
                '<button type="button" class="qiwi-admin-button qiwi-icon-button is-danger" data-nav-action="delete" title="删除" aria-label="删除"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>' +
            '</div>';
        return row;
    }

    function initNavEditor(panel, textarea) {
        var list = $('[data-qiwi-nav-list]', panel);

        function sync() {
            textarea.value = navToText(readNavRows(panel));
            $all('.qiwi-nav-row', panel).forEach(function(row) {
                row.setAttribute('data-level', $('[data-nav-field="level"]', row).value);
            });
        }

        function render(items) {
            list.innerHTML = '';
            if (!items.length) {
                var empty = document.createElement('div');
                empty.className = 'qiwi-admin-empty';
                empty.textContent = '当前为空，将自动显示全部可见独立页面。也可以点击上方按钮添加手动导航。';
                list.appendChild(empty);
                return;
            }
            items.forEach(function(item) {
                list.appendChild(renderNavRow(item));
            });
        }

        render(parseNav(textarea.value));

        $('[data-nav-add="parent"]', panel).addEventListener('click', function() {
            var empty = $('.qiwi-admin-empty', list);
            if (empty) empty.remove();
            list.appendChild(renderNavRow({ level: 'parent' }));
            sync();
        });

        $('[data-nav-add="child"]', panel).addEventListener('click', function() {
            var empty = $('.qiwi-admin-empty', list);
            if (empty) empty.remove();
            list.appendChild(renderNavRow({ level: 'child' }));
            sync();
        });

        list.addEventListener('input', sync);
        list.addEventListener('change', sync);
        list.addEventListener('click', function(event) {
            var button = event.target.closest('[data-nav-action]');
            if (!button) return;
            var row = button.closest('.qiwi-nav-row');
            if (!row) return;
            var action = button.getAttribute('data-nav-action');
            if (action === 'delete') row.remove();
            if (action === 'up' && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
            if (action === 'down' && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
            sync();
            if (!$all('.qiwi-nav-row', list).length) render([]);
        });
    }

    function parseFriends(text) {
        try {
            var data = JSON.parse(text || '{}');
            return data && typeof data === 'object' && !Array.isArray(data) ? data : {};
        } catch (error) {
            return {};
        }
    }

    function friendExtras(friend) {
        var extras = {};
        Object.keys(friend || {}).forEach(function(key) {
            if (['name', 'url', 'avatar', 'description'].indexOf(key) === -1) extras[key] = friend[key];
        });
        return extras;
    }

    function getFriendField(row, key) {
        var input = $('[data-friend-field="' + key + '"]', row);
        return input ? input.value : '';
    }

    function updateFriendSummary(row) {
        var name = trim(getFriendField(row, 'name')) || '未命名友链';
        var url = trim(getFriendField(row, 'url')) || '未填写链接';
        var avatar = trim(getFriendField(row, 'avatar'));
        var avatarImg = $('.qiwi-friend-avatar img', row);
        var nameEl = $('.qiwi-friend-summary-name', row);
        var urlEl = $('.qiwi-friend-summary-url', row);

        if (avatarImg) {
            avatarImg.src = avatar || 'https://gravatar.loli.net/avatar/default?s=96&d=mp';
            avatarImg.alt = name;
        }
        if (nameEl) nameEl.textContent = name;
        if (urlEl) urlEl.textContent = url;
    }

    function renderFriendRow(friend, expanded) {
        var row = document.createElement('div');
        var name = trim(friend && friend.name) || '未命名友链';
        var url = trim(friend && friend.url) || '未填写链接';
        var avatar = trim(friend && friend.avatar) || 'https://gravatar.loli.net/avatar/default?s=96&d=mp';
        var chevronSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>';

        row.className = 'qiwi-friend-row' + (expanded ? ' is-open' : '');
        row.setAttribute('data-extra', JSON.stringify(friendExtras(friend || {})));
        row.innerHTML =
            '<button type="button" class="qiwi-friend-summary" data-friend-action="toggle" aria-expanded="' + (expanded ? 'true' : 'false') + '">' +
                '<span class="qiwi-friend-avatar"><img src="' + escapeHtml(avatar) + '" alt="' + escapeHtml(name) + '" loading="lazy" onerror="this.src=\'https://gravatar.loli.net/avatar/default?s=96&d=mp\'"></span>' +
                '<span class="qiwi-friend-summary-text">' +
                    '<strong class="qiwi-friend-summary-name">' + escapeHtml(name) + '</strong>' +
                    '<span class="qiwi-friend-summary-url">' + escapeHtml(url) + '</span>' +
                '</span>' +
                '<span class="qiwi-friend-arrow">' + chevronSvg + '</span>' +
            '</button>' +
            '<div class="qiwi-friend-editor">' +
                '<label>名称<input type="text" data-friend-field="name" value="' + escapeHtml(friend && friend.name) + '"></label>' +
                '<label>链接<input type="url" data-friend-field="url" value="' + escapeHtml(friend && friend.url) + '"></label>' +
                '<label>头像<input type="url" data-friend-field="avatar" value="' + escapeHtml(friend && friend.avatar) + '"></label>' +
                '<label class="qiwi-friend-wide">描述<textarea rows="2" data-friend-field="description">' + escapeHtml(friend && friend.description) + '</textarea></label>' +
                '<div class="qiwi-row-actions"><button type="button" class="qiwi-admin-button qiwi-icon-button is-danger" data-friend-action="delete" title="删除友链" aria-label="删除友链"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button></div>' +
            '</div>';
        return row;
    }

    function setCategoryOpen(category, isOpen) {
        category.classList.toggle('is-open', isOpen);
        var button = $('[data-friend-action="toggle-category"]', category);
        if (button) button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    function renderCategory(name, friends, open) {
        var category = document.createElement('div');
        var chevronSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>';
        category.className = 'qiwi-friend-category' + (open ? ' is-open' : '');
        category.innerHTML =
            '<div class="qiwi-friend-category-header">' +
                '<button type="button" class="qiwi-friend-category-toggle" data-friend-action="toggle-category" aria-expanded="' + (open ? 'true' : 'false') + '"><span class="qiwi-friend-category-arrow">' + chevronSvg + '</span></button>' +
                '<input type="text" data-category-name value="' + escapeHtml(name) + '" placeholder="分类名称">' +
                '<div class="qiwi-row-actions">' +
                    '<button type="button" class="qiwi-admin-button" data-friend-action="add">添加友链</button>' +
                    '<button type="button" class="qiwi-admin-button qiwi-icon-button is-danger" data-friend-action="delete-category" title="删除分类" aria-label="删除分类"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>' +
                '</div>' +
            '</div>' +
            '<div class="qiwi-friend-category-body" data-friend-list></div>';
        var list = $('[data-friend-list]', category);
        (friends || []).forEach(function(friend) {
            list.appendChild(renderFriendRow(friend, false));
        });
        return category;
    }

    function initFriendsEditor(panel, textarea) {
        var list = $('[data-qiwi-friends-list]', panel);

        function readData() {
            var data = {};
            $all('.qiwi-friend-category', panel).forEach(function(category) {
                var name = trim($('[data-category-name]', category).value);
                if (!name) return;
                data[name] = $all('.qiwi-friend-row', category).map(function(row) {
                    var friend = {};
                    try {
                        friend = JSON.parse(row.getAttribute('data-extra') || '{}');
                    } catch (error) {
                        friend = {};
                    }
                    ['name', 'url', 'avatar', 'description'].forEach(function(key) {
                        friend[key] = getFriendField(row, key);
                    });
                    return friend;
                }).filter(function(friend) {
                    return friend.name || friend.url || friend.avatar || friend.description;
                });
            });
            return data;
        }

        function sync() {
            var data = readData();
            textarea.value = Object.keys(data).length ? JSON.stringify(data, null, 2) : '';
        }

        function render(data) {
            list.innerHTML = '';
            Object.keys(data).forEach(function(name) {
                list.appendChild(renderCategory(name, Array.isArray(data[name]) ? data[name] : [], true));
            });
            if (!Object.keys(data).length) {
                var empty = document.createElement('div');
                empty.className = 'qiwi-admin-empty';
                empty.textContent = '还没有友链分类，点击“添加分类”开始配置。';
                list.appendChild(empty);
            }
        }

        render(parseFriends(textarea.value));

        $('[data-friend-action="add-category"]', panel).addEventListener('click', function() {
            var empty = $('.qiwi-admin-empty', list);
            if (empty) empty.remove();
            list.appendChild(renderCategory('默认分类', [], true));
            sync();
        });

        list.addEventListener('input', function(event) {
            var row = event.target.closest('.qiwi-friend-row');
            if (row) updateFriendSummary(row);
            sync();
        });

        list.addEventListener('click', function(event) {
            var button = event.target.closest('[data-friend-action]');
            if (!button) return;
            var action = button.getAttribute('data-friend-action');
            var category = button.closest('.qiwi-friend-category');
            var row = button.closest('.qiwi-friend-row');

            if (action === 'toggle-category' && category) {
                setCategoryOpen(category, !category.classList.contains('is-open'));
            }

            if (action === 'toggle' && row) {
                var isOpen = !row.classList.contains('is-open');
                row.classList.toggle('is-open', isOpen);
                button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }

            if (action === 'add' && category) {
                $('[data-friend-list]', category).appendChild(renderFriendRow({}, true));
            }

            if (action === 'delete' && row && row.classList.contains('is-open')) {
                row.remove();
            }

            if (action === 'delete-category' && category) {
                category.remove();
            }

            sync();
            if (!$all('.qiwi-friend-category', list).length) render({});
        });
    }

    function parseBooks(text) {
        return String(text || '').split('&&').map(function(part) {
            var pieces = part.split(',');
            return {
                title: trim(pieces[0] || ''),
                words: trim(pieces.slice(1).join(',') || '')
            };
        }).filter(function(book) {
            return book.title || book.words;
        });
    }

    function booksToText(books) {
        return books.map(function(book) {
            if (!book.title && !book.words) return '';
            return book.title + ', ' + book.words;
        }).filter(Boolean).join('&&');
    }

    function renderBookRow(book) {
        var row = document.createElement('div');
        row.className = 'qiwi-book-row';
        row.innerHTML =
            '<input type="text" data-book-field="title" placeholder="书名" value="' + escapeHtml(book.title) + '">' +
            '<input type="number" min="0" step="1" data-book-field="words" placeholder="字数" value="' + escapeHtml(book.words) + '">' +
            '<div class="qiwi-row-actions">' +
                '<button type="button" class="qiwi-admin-button qiwi-icon-button" data-book-action="up" title="上移" aria-label="上移"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button>' +
                '<button type="button" class="qiwi-admin-button qiwi-icon-button" data-book-action="down" title="下移" aria-label="下移"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i></button>' +
                '<button type="button" class="qiwi-admin-button qiwi-icon-button is-danger" data-book-action="delete" title="删除" aria-label="删除"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>' +
            '</div>';
        return row;
    }

    function initBookEditor(panel, input) {
        var list = $('[data-qiwi-book-list]', panel);

        function readBooks() {
            return $all('.qiwi-book-row', list).map(function(row) {
                return {
                    title: $('[data-book-field="title"]', row).value,
                    words: $('[data-book-field="words"]', row).value
                };
            }).filter(function(book) {
                return book.title || book.words;
            });
        }

        function sync() {
            input.value = booksToText(readBooks());
        }

        function render(books) {
            list.innerHTML = '';
            if (!books.length) {
                var empty = document.createElement('div');
                empty.className = 'qiwi-admin-empty';
                empty.textContent = '还没有书籍统计条目。';
                list.appendChild(empty);
                return;
            }
            books.forEach(function(book) {
                list.appendChild(renderBookRow(book));
            });
        }

        render(parseBooks(input.value));

        $('[data-book-action="add"]', panel).addEventListener('click', function() {
            var empty = $('.qiwi-admin-empty', list);
            if (empty) empty.remove();
            list.appendChild(renderBookRow({ title: '', words: '' }));
            sync();
        });

        list.addEventListener('input', sync);
        list.addEventListener('click', function(event) {
            var button = event.target.closest('[data-book-action]');
            if (!button) return;
            var row = button.closest('.qiwi-book-row');
            if (!row) return;
            var action = button.getAttribute('data-book-action');
            if (action === 'delete') row.remove();
            if (action === 'up' && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
            if (action === 'down' && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
            sync();
            if (!$all('.qiwi-book-row', list).length) render([]);
        });
    }

    function getAdminConfig() {
        return window.QIWI_ADMIN_CONFIG || {};
    }

    function compareVersions(a, b) {
        var left = String(a || '').replace(/^v/i, '').split(/[.-]/);
        var right = String(b || '').replace(/^v/i, '').split(/[.-]/);
        var length = Math.max(left.length, right.length);

        for (var i = 0; i < length; i++) {
            var lRaw = left[i] || '0';
            var rRaw = right[i] || '0';
            var lNum = parseInt(lRaw, 10);
            var rNum = parseInt(rRaw, 10);

            if (!isNaN(lNum) || !isNaN(rNum)) {
                lNum = isNaN(lNum) ? 0 : lNum;
                rNum = isNaN(rNum) ? 0 : rNum;
                if (lNum !== rNum) return lNum > rNum ? 1 : -1;
                continue;
            }

            if (lRaw !== rRaw) return lRaw > rRaw ? 1 : -1;
        }

        return 0;
    }

    function normalizeNotes(notes) {
        if (Array.isArray(notes)) return notes;
        return String(notes || '').split(/\r\n|\r|\n/).map(function(line) {
            return trim(line.replace(/^[-*]\s*/, ''));
        }).filter(Boolean);
    }

    function readUpdateCache(key, ttl) {
        try {
            var cached = JSON.parse(localStorage.getItem(key) || 'null');
            if (cached && cached.time && Date.now() - cached.time < ttl) {
                return cached.data;
            }
        } catch (error) {}
        return null;
    }

    function writeUpdateCache(key, data) {
        try {
            localStorage.setItem(key, JSON.stringify({
                time: Date.now(),
                data: data
            }));
        } catch (error) {}
    }

    function renderUpdateDetails(panel, data, isOutdated) {
        var config = getAdminConfig();
        var notes = normalizeNotes(data && data.notes);
        var command = config.updateCommand || 'bash update.sh';
        var details = $('.qiwi-update-details', panel);
        var commandText = $('.qiwi-update-command code', panel);
        var copyButton = $('[data-update-copy]', panel);

        if (!details || !commandText || !copyButton) return;

        commandText.textContent = command;
        copyButton.disabled = false;

        details.innerHTML =
            '<div class="qiwi-update-log-title">' + (isOutdated ? '可用更新' : '当前版本说明') + '</div>' +
            '<ul class="qiwi-update-log">' +
                (notes.length ? notes.map(function(note) {
                    return '<li>' + escapeHtml(note) + '</li>';
                }).join('') : '<li>远程版本暂未填写更新说明。</li>') +
            '</ul>';
    }

    function setUpdateState(panel, state, data) {
        var config = getAdminConfig();
        var status = $('.qiwi-update-status', panel);
        var title = $('.qiwi-update-title', panel);
        var subtitle = $('.qiwi-update-subtitle', panel);
        var current = config.currentVersion || 'unknown';
        var latest = data && data.version ? String(data.version) : '';
        var isOutdated = latest && compareVersions(latest, current) > 0;

        panel.setAttribute('data-update-state', state);
        panel.classList.toggle('is-expandable', state === 'ready');

        if (state === 'checking') {
            status.innerHTML = '<span class="qiwi-update-spinner" aria-hidden="true"></span><span>检查中</span>';
            title.textContent = '正在检查 Qiwi 主题更新';
            subtitle.textContent = '当前版本 v' + current + '，正在读取远程 update.json';
            return;
        }

        if (state === 'error') {
            status.innerHTML = '<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span>检查失败</span>';
            title.textContent = '暂时无法检查更新';
            subtitle.textContent = '当前版本 v' + current + '，可以稍后重试或手动运行更新脚本。';
            return;
        }

        status.innerHTML = isOutdated ?
            '<i class="fa-solid fa-arrow-up" aria-hidden="true"></i><span>发现新版本</span>' :
            '<i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>已是最新版</span>';
        title.textContent = isOutdated ? 'Qiwi v' + latest + ' 可更新' : 'Qiwi v' + current + ' 已是最新版';
        subtitle.textContent = isOutdated ?
            '点击展开更新日志，确认后在服务器执行下方命令。' :
            '远程版本 v' + (latest || current) + '，点击可查看当前更新说明。';
        renderUpdateDetails(panel, data || {}, isOutdated);
    }

    function initUpdatePanel(anchorRow) {
        var config = getAdminConfig();
        if (!config.updateEndpoint || $('.qiwi-update-panel')) return;

        var panel = document.createElement('div');
        panel.className = 'qiwi-update-panel';
        panel.innerHTML =
            '<button type="button" class="qiwi-update-card" aria-expanded="false">' +
                '<span class="qiwi-update-main">' +
                    '<span class="qiwi-update-title">正在检查 Qiwi 主题更新</span>' +
                    '<span class="qiwi-update-subtitle">当前版本 v' + escapeHtml(config.currentVersion || 'unknown') + '</span>' +
                '</span>' +
                '<span class="qiwi-update-status"><span class="qiwi-update-spinner" aria-hidden="true"></span><span>检查中</span></span>' +
            '</button>' +
            '<div class="qiwi-update-expand" hidden>' +
                '<div class="qiwi-update-details"></div>' +
                '<div class="qiwi-update-command"><code></code><button type="button" class="qiwi-admin-button" data-update-copy>复制命令</button></div>' +
            '</div>';

        anchorRow.parentNode.insertBefore(panel, anchorRow);

        var card = $('.qiwi-update-card', panel);
        var expand = $('.qiwi-update-expand', panel);
        var copyButton = $('[data-update-copy]', panel);

        card.addEventListener('click', function() {
            if (!panel.classList.contains('is-expandable')) return;
            var expanded = card.getAttribute('aria-expanded') === 'true';
            card.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            expand.hidden = expanded;
        });

        copyButton.addEventListener('click', function(event) {
            event.stopPropagation();
            var command = config.updateCommand || 'bash update.sh';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(command).then(function() {
                    copyButton.textContent = '已复制';
                    setTimeout(function() { copyButton.textContent = '复制命令'; }, 1800);
                });
                return;
            }

            var textarea = document.createElement('textarea');
            textarea.value = command;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                copyButton.textContent = '已复制';
                setTimeout(function() { copyButton.textContent = '复制命令'; }, 1800);
            } catch (error) {}
            document.body.removeChild(textarea);
        });

        setUpdateState(panel, 'checking');

        var cacheKey = 'qiwi:update:' + config.updateEndpoint;
        var cached = readUpdateCache(cacheKey, config.cacheTtl || 21600000);
        if (cached) {
            setUpdateState(panel, 'ready', cached);
        }

        if (!window.fetch) {
            if (!cached) setUpdateState(panel, 'error');
            return;
        }

        fetch(config.updateEndpoint, { cache: 'no-store' })
            .then(function(response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(function(data) {
                writeUpdateCache(cacheKey, data);
                setUpdateState(panel, 'ready', data);
            })
            .catch(function() {
                if (!cached) setUpdateState(panel, 'error');
            });
    }

    function initTabs(panel) {
        var tabs = $all('.qiwi-admin-tab', panel);
        var panes = $all('.qiwi-admin-pane', panel);
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                var target = tab.getAttribute('data-qiwi-tab');
                tabs.forEach(function(item) {
                    item.classList.toggle('is-active', item === tab);
                });
                panes.forEach(function(pane) {
                    pane.classList.toggle('is-active', pane.getAttribute('data-qiwi-pane') === target);
                });
            });
        });
    }

    function init() {
        var navTextarea = fieldByName('navItems');
        var friendsTextarea = fieldByName('friendsData');
        var bookInput = fieldByName('bookReference');
        if (!navTextarea || !friendsTextarea || !bookInput || $('.qiwi-admin-panel')) return;

        var navRow = fieldRow(navTextarea);
        if (!navRow) return;

        initUpdatePanel(navRow);

        var panel = document.createElement('div');
        panel.className = 'qiwi-admin-panel';
        panel.innerHTML =
            '<div class="qiwi-admin-tabs">' +
                '<button type="button" class="qiwi-admin-tab is-active" data-qiwi-tab="home">首页</button>' +
                '<button type="button" class="qiwi-admin-tab" data-qiwi-tab="sidebar">侧边栏</button>' +
                '<button type="button" class="qiwi-admin-tab" data-qiwi-tab="nav">导航栏</button>' +
                '<button type="button" class="qiwi-admin-tab" data-qiwi-tab="friends">友链</button>' +
                '<button type="button" class="qiwi-admin-tab" data-qiwi-tab="books">书籍统计</button>' +
                '<button type="button" class="qiwi-admin-tab" data-qiwi-tab="about">关于页面</button>' +
                '<button type="button" class="qiwi-admin-tab" data-qiwi-tab="security">安全</button>' +
                '<button type="button" class="qiwi-admin-tab" data-qiwi-tab="raw">原始数据</button>' +
            '</div>' +
            '<div class="qiwi-admin-pane is-active" data-qiwi-pane="home"><div class="qiwi-admin-fields" data-qiwi-home-fields></div></div>' +
            '<div class="qiwi-admin-pane" data-qiwi-pane="sidebar"><div class="qiwi-admin-fields" data-qiwi-sidebar-fields></div></div>' +
            '<div class="qiwi-admin-pane" data-qiwi-pane="nav">' +
                '<div class="qiwi-admin-fields" data-qiwi-nav-fields></div>' +
                '<div class="qiwi-admin-toolbar">' +
                    '<button type="button" class="qiwi-admin-button" data-nav-add="parent">添加主导航</button>' +
                    '<button type="button" class="qiwi-admin-button" data-nav-add="child">添加子菜单</button>' +
                '</div>' +
                '<div data-qiwi-nav-list></div>' +
            '</div>' +
            '<div class="qiwi-admin-pane" data-qiwi-pane="friends">' +
                '<div class="qiwi-admin-toolbar">' +
                    '<button type="button" class="qiwi-admin-button" data-friend-action="add-category">添加分类</button>' +
                '</div>' +
                '<div data-qiwi-friends-list></div>' +
            '</div>' +
            '<div class="qiwi-admin-pane" data-qiwi-pane="books">' +
                '<div class="qiwi-admin-toolbar">' +
                    '<button type="button" class="qiwi-admin-button" data-book-action="add">添加书籍</button>' +
                '</div>' +
                '<div data-qiwi-book-list></div>' +
            '</div>' +
            '<div class="qiwi-admin-pane" data-qiwi-pane="about"><div class="qiwi-admin-fields" data-qiwi-about-fields></div></div>' +
            '<div class="qiwi-admin-pane" data-qiwi-pane="security"><div class="qiwi-admin-fields" data-qiwi-security-fields></div></div>' +
            '<div class="qiwi-admin-pane" data-qiwi-pane="raw"></div>';

        navRow.parentNode.insertBefore(panel, navRow);

        moveFields(['jikePosition', 'jikeTimeMode'], $('[data-qiwi-home-fields]', panel));
        moveFields(['sidebarBlock', 'enableHitokoto'], $('[data-qiwi-sidebar-fields]', panel));
        moveFields(['enableTravellings'], $('[data-qiwi-nav-fields]', panel));
        moveFields(['aboutBio', 'aboutAvatar'], $('[data-qiwi-about-fields]', panel));
        moveFields(['enabledCaptcha'], $('[data-qiwi-security-fields]', panel));

        var rawPane = $('[data-qiwi-pane="raw"]', panel);
        moveField('navItems', rawPane);
        moveField('friendsData', rawPane);
        moveField('bookReference', rawPane);

        initTabs(panel);
        initNavEditor(panel, navTextarea);
        initFriendsEditor(panel, friendsTextarea);
        initBookEditor(panel, bookInput);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
