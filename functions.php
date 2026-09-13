<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

if (!function_exists('qiwiGetFieldValue')) {
    function qiwiGetFieldValue($widget, $name, $default = null)
    {
        if (!empty($widget) && !empty($widget->fields) && isset($widget->fields->{$name})) {
            return $widget->fields->{$name};
        }

        if (!empty($widget) && !empty($widget->fields)) {
            try {
                $value = $widget->fields->{$name};
                if ($value !== null && $value !== '') {
                    return $value;
                }
            } catch (Exception $e) {
            } catch (Throwable $e) {
            }
        }

        $storedValue = qiwiGetStoredFieldValue($widget, $name, null);
        if ($storedValue !== null) {
            return $storedValue;
        }

        return $default;
    }
}

if (!function_exists('qiwiGetCaptchaProvider')) {
    function qiwiGetCaptchaProvider()
    {
        try {
            $options = Typecho_Widget::widget('Widget_Options');
            $activated = isset($options->plugins['activated']) && is_array($options->plugins['activated'])
                ? $options->plugins['activated']
                : array();

            if (!empty($activated['QiwiCap'])
                && class_exists('QiwiCap_Plugin')
                && method_exists('QiwiCap_Plugin', 'commentCaptchaRender')) {
                return 'QiwiCap';
            }

            if (!empty($activated['Geetest'])
                && class_exists('Geetest_Plugin')
                && method_exists('Geetest_Plugin', 'commentCaptchaRender')) {
                return 'Geetest';
            }
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }

        return '';
    }
}

if (!function_exists('qiwiCanRenderCaptcha')) {
    function qiwiCanRenderCaptcha()
    {
        $provider = qiwiGetCaptchaProvider();
        if ($provider === 'QiwiCap') {
            try {
                return method_exists('QiwiCap_Plugin', 'canRenderCommentCaptcha')
                    ? QiwiCap_Plugin::canRenderCommentCaptcha()
                    : true;
            } catch (Exception $e) {
                return false;
            } catch (Throwable $e) {
                return false;
            }
        }

        if ($provider !== 'Geetest') {
            return false;
        }

        try {
            $pluginOptions = Helper::options()->plugin('Geetest');
            $enabledPages = isset($pluginOptions->isOpenGeetestPage) ? $pluginOptions->isOpenGeetestPage : array();
            return is_array($enabledPages) && in_array('typechoComment', $enabledPages, true);
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('qiwiRenderCaptcha')) {
    function qiwiRenderCaptcha()
    {
        $provider = qiwiGetCaptchaProvider();
        if ($provider === '' || !qiwiCanRenderCaptcha()) {
            return false;
        }

        try {
            if ($provider === 'QiwiCap') {
                return QiwiCap_Plugin::commentCaptchaRender() !== false;
            }

            Geetest_Plugin::commentCaptchaRender();
            return true;
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('qiwiGetStoredFieldValue')) {
    function qiwiGetStoredFieldValue($widget, $name, $default = null)
    {
        if (empty($widget) || !isset($widget->cid)) {
            return $default;
        }

        $cid = (int) $widget->cid;
        if ($cid <= 0) {
            return $default;
        }

        static $cache = [];
        $cacheKey = $cid . ':' . $name;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
        $prefix = $db->getPrefix();
        $row = $db->fetchRow($db->select('type', 'str_value', 'int_value', 'float_value')
            ->from($prefix . 'fields')
            ->where('cid = ?', $cid)
            ->where('name = ?', $name)
            ->limit(1));

        if (empty($row)) {
            $cache[$cacheKey] = $default;
            return $default;
        }

        $type = isset($row['type']) ? (string) $row['type'] : '';
        if ($type === 'int') {
            $cache[$cacheKey] = $row['int_value'];
            return $cache[$cacheKey];
        }

        if ($type === 'float') {
            $cache[$cacheKey] = $row['float_value'];
            return $cache[$cacheKey];
        }

        if ($row['str_value'] !== null && $row['str_value'] !== '') {
            $cache[$cacheKey] = $row['str_value'];
            return $cache[$cacheKey];
        }

        if ($row['int_value'] !== null) {
            $cache[$cacheKey] = $row['int_value'];
            return $cache[$cacheKey];
        }

        $cache[$cacheKey] = $default;
        return $default;
    }
}

if (!function_exists('qiwiGetContentVisibilityDefault')) {
    function qiwiGetContentVisibilityDefault($scope, $options = null)
    {
        $scope = $scope === 'rss' ? 'rss' : 'home';
        $field = $scope === 'rss' ? 'rssVisibilityDefault' : 'homeVisibilityDefault';
        $value = '';

        try {
            if ($options === null) {
                $options = Typecho_Widget::widget('Widget_Options');
            }
            $value = isset($options->{$field}) ? strtolower(trim((string) $options->{$field})) : '';
        } catch (Exception $e) {
            $value = '';
        } catch (Throwable $e) {
            $value = '';
        }

        return $value !== 'hide';
    }
}

if (!function_exists('qiwiGetContentVisibility')) {
    function qiwiGetContentVisibility($widget, $scope = 'home', $options = null)
    {
        $scope = $scope === 'rss' ? 'rss' : 'home';
        $field = $scope === 'rss' ? 'rssVisibility' : 'homeVisibility';
        $override = strtolower(trim((string) qiwiGetFieldValue($widget, $field, 'default')));
        if ($override === 'show') {
            return true;
        }
        if ($override === 'hide') {
            return false;
        }

        $categoryOverride = qiwiGetContentCategoryVisibility($widget, $scope, $options);
        if ($categoryOverride === 'show') {
            return true;
        }
        if ($categoryOverride === 'hide') {
            return false;
        }

        return qiwiGetContentVisibilityDefault($scope, $options);
    }
}

if (!function_exists('qiwiGetCategoryVisibilityMap')) {
    function qiwiGetCategoryVisibilityMap($options = null)
    {
        $map = array('mid' => array(), 'slug' => array());
        try {
            if ($options === null) {
                $options = Typecho_Widget::widget('Widget_Options');
            }
            $raw = isset($options->categoryVisibilityData) ? trim((string) $options->categoryVisibilityData) : '';
            $decoded = $raw !== '' ? json_decode(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'), true) : array();
            $items = is_array($decoded) && isset($decoded['items']) && is_array($decoded['items']) ? $decoded['items'] : array();
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $normalized = array(
                    'home' => isset($item['home']) && (string) $item['home'] === 'hide' ? 'hide' : 'show',
                    'rss' => isset($item['rss']) && (string) $item['rss'] === 'hide' ? 'hide' : 'show',
                );
                $mid = isset($item['mid']) ? (int) $item['mid'] : 0;
                $slug = isset($item['slug']) ? trim((string) $item['slug']) : '';
                if ($mid > 0) {
                    $map['mid'][$mid] = $normalized;
                }
                if ($slug !== '') {
                    $map['slug'][$slug] = $normalized;
                }
            }
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }

        return $map;
    }
}

if (!function_exists('qiwiGetCategoryVisibilityOverride')) {
    function qiwiGetCategoryVisibilityOverride($mid, $slug, $scope, $options = null)
    {
        $scope = $scope === 'rss' ? 'rss' : 'home';
        $map = qiwiGetCategoryVisibilityMap($options);
        $item = null;
        $mid = (int) $mid;
        $slug = trim((string) $slug);
        if ($mid > 0 && isset($map['mid'][$mid])) {
            $item = $map['mid'][$mid];
        } elseif ($slug !== '' && isset($map['slug'][$slug])) {
            $item = $map['slug'][$slug];
        }

        return $item && isset($item[$scope]) ? $item[$scope] : '';
    }
}

if (!function_exists('qiwiGetContentCategoryVisibility')) {
    function qiwiGetContentCategoryVisibility($widget, $scope = 'home', $options = null)
    {
        if (empty($widget) || !isset($widget->cid)) {
            return '';
        }

        $cid = (int) $widget->cid;
        if ($cid <= 0) {
            return '';
        }

        static $cache = array();
        $scope = $scope === 'rss' ? 'rss' : 'home';
        $cacheKey = $cid . ':' . $scope;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $hasShow = false;
        try {
            $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
            $rows = $db->fetchAll($db->select('table.metas.mid', 'table.metas.slug')
                ->from('table.relationships')
                ->join('table.metas', 'table.relationships.mid = table.metas.mid')
                ->where('table.relationships.cid = ?', $cid)
                ->where('table.metas.type = ?', 'category'));
            foreach ($rows as $row) {
                $override = qiwiGetCategoryVisibilityOverride(
                    isset($row['mid']) ? $row['mid'] : 0,
                    isset($row['slug']) ? $row['slug'] : '',
                    $scope,
                    $options
                );
                if ($override === 'hide') {
                    $cache[$cacheKey] = 'hide';
                    return 'hide';
                }
                if ($override === 'show') {
                    $hasShow = true;
                }
            }
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }

        $cache[$cacheKey] = $hasShow ? 'show' : '';
        return $cache[$cacheKey];
    }
}

if (!function_exists('qiwiIsHomeArchiveRequest')) {
    function qiwiIsHomeArchiveRequest($archive)
    {
        // handleInit 触发时 archiveType 还是默认的 index，尚未被 categoryHandle 等
        // handle 方法特化，is('index') 会误命中所有列表页，因此必须读路由参数。
        $parameterType = '';
        try {
            if (isset($archive->parameter) && isset($archive->parameter->type)) {
                $parameterType = strtolower(trim((string) $archive->parameter->type));
            }
        } catch (Exception $e) {
            $parameterType = '';
        } catch (Throwable $e) {
            $parameterType = '';
        }

        if ($parameterType === 'index' || $parameterType === 'index_page') {
            return true;
        }

        return method_exists($archive, 'getArchiveType') && $archive->getArchiveType() === 'front';
    }
}

if (!function_exists('qiwiApplyContentVisibilityToArchiveSelect')) {
    function qiwiApplyContentVisibilityToArchiveSelect($archive, $select)
    {
        if (empty($archive) || empty($select) || !method_exists($archive, 'is')) {
            return;
        }

        $scope = null;
        if ($archive->is('feed')) {
            $scope = 'rss';
        } elseif (qiwiIsHomeArchiveRequest($archive)) {
            $scope = 'home';
        }

        if ($scope === null) {
            return;
        }

        $fieldName = $scope === 'rss' ? 'rssVisibility' : 'homeVisibility';
        $defaultVisible = qiwiGetContentVisibilityDefault($scope);
        $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();

        $articleOverrideCids = array();
        $articleShowCids = array();
        try {
            $fieldRows = $db->fetchAll($db->select('cid', 'str_value')
                ->from('table.fields')
                ->where('name = ?', $fieldName)
                ->where('str_value IN ?', array('show', 'hide')));
        } catch (Exception $e) {
            $fieldRows = array();
        } catch (Throwable $e) {
            $fieldRows = array();
        }
        foreach ($fieldRows as $row) {
            $cid = isset($row['cid']) ? (int) $row['cid'] : 0;
            if ($cid <= 0) {
                continue;
            }
            $articleOverrideCids[$cid] = $cid;
            if (isset($row['str_value']) && (string) $row['str_value'] === 'show') {
                $articleShowCids[$cid] = $cid;
            }
        }
        $articleOverrideCids = array_values($articleOverrideCids);
        $articleShowCids = array_values($articleShowCids);

        $fetchCategoryCids = function (array $mids) use ($db) {
            if (empty($mids)) {
                return array();
            }

            $cids = array();
            $query = $db->select('table.relationships.cid')
                ->from('table.relationships')
                ->join('table.metas', 'table.metas.mid = table.relationships.mid')
                ->where('table.metas.type = ?', 'category')
                ->where('table.metas.mid IN ?', $mids);
            foreach ($db->fetchAll($query) as $row) {
                $cid = isset($row['cid']) ? (int) $row['cid'] : 0;
                if ($cid > 0) {
                    $cids[$cid] = $cid;
                }
            }
            return array_values($cids);
        };

        $categoryMap = qiwiGetCategoryVisibilityMap();
        $categoryShowMids = array();
        $categoryHideMids = array();
        foreach ($categoryMap['mid'] as $mid => $visibility) {
            $visibilityValue = isset($visibility[$scope]) ? $visibility[$scope] : '';
            if ($visibilityValue === 'show') {
                $categoryShowMids[] = (int) $mid;
            } elseif ($visibilityValue === 'hide') {
                $categoryHideMids[] = (int) $mid;
            }
        }
        $categoryShowMids = array_values(array_unique(array_filter($categoryShowMids)));
        $categoryHideMids = array_values(array_unique(array_filter($categoryHideMids)));
        $categoryShowCids = $fetchCategoryCids($categoryShowMids);
        $categoryHideCids = $fetchCategoryCids($categoryHideMids);

        $conditions = array();
        $conditionArgs = array();
        if (!empty($articleShowCids)) {
            $conditions[] = 'table.contents.cid IN ?';
            $conditionArgs[] = $articleShowCids;
        }

        $defaultConditions = array();
        $defaultArgs = array();
        if (!empty($articleOverrideCids)) {
            $defaultConditions[] = 'table.contents.cid NOT IN ?';
            $defaultArgs[] = $articleOverrideCids;
        }

        if ($defaultVisible) {
            if (!empty($categoryHideCids)) {
                $defaultConditions[] = 'table.contents.cid NOT IN ?';
                $defaultArgs[] = $categoryHideCids;
            }
            if (empty($defaultConditions)) {
                $defaultConditions[] = '1 = 1';
            }
            $conditions[] = '(' . implode(' AND ', $defaultConditions) . ')';
            $conditionArgs = array_merge($conditionArgs, $defaultArgs);
        } elseif (!empty($categoryShowCids)) {
            $defaultConditions[] = 'table.contents.cid IN ?';
            $defaultArgs[] = $categoryShowCids;
            if (!empty($categoryHideCids)) {
                $defaultConditions[] = 'table.contents.cid NOT IN ?';
                $defaultArgs[] = $categoryHideCids;
            }
            $conditions[] = '(' . implode(' AND ', $defaultConditions) . ')';
            $conditionArgs = array_merge($conditionArgs, $defaultArgs);
        }

        if (empty($conditions)) {
            $conditions[] = '1 = 0';
        }

        $condition = '(' . implode(' OR ', $conditions) . ')';
        if (empty($conditionArgs)) {
            $select->where($condition);
        } else {
            $select->where($condition, ...$conditionArgs);
        }
    }
}

if (!function_exists('qiwiShouldRenderLatex')) {
    function qiwiShouldRenderLatex($widget)
    {
        if (empty($widget) || !method_exists($widget, 'is') || !$widget->is('single')) {
            return false;
        }

        if ((string) qiwiGetFieldValue($widget, 'isLatex', '0') === '1') {
            return true;
        }

        $content = qiwiGetRawContentForDetection($widget);
        return qiwiContentMightContainLatex($content);
    }
}

if (!function_exists('qiwiGetRawContentForDetection')) {
    function qiwiGetRawContentForDetection($widget)
    {
        if (!empty($widget) && isset($widget->cid)) {
            $cid = (int) $widget->cid;
            if ($cid > 0) {
                try {
                    $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
                    $prefix = $db->getPrefix();
                    $row = $db->fetchRow($db->select('text')
                        ->from($prefix . 'contents')
                        ->where('cid = ?', $cid)
                        ->limit(1));

                    if (!empty($row['text'])) {
                        return (string) $row['text'];
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        foreach (['text', 'content'] as $property) {
            if (isset($widget->{$property}) && trim((string) $widget->{$property}) !== '') {
                return (string) $widget->{$property};
            }
        }

        return '';
    }
}

if (!function_exists('qiwiContentMightContainLatex')) {
    function qiwiContentMightContainLatex($content)
    {
        $content = (string) $content;
        if ($content === '') {
            return false;
        }

        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = preg_replace('/```[\s\S]*?```|~~~[\s\S]*?~~~/u', ' ', $content);
        $content = preg_replace('/<pre\b[\s\S]*?<\/pre>|<code\b[\s\S]*?<\/code>/iu', ' ', $content);

        if (preg_match('/\$\$[\s\S]+?\$\$/u', $content)) {
            return true;
        }

        if (preg_match('/\\\\\([\s\S]+?\\\\\)|\\\\\[[\s\S]+?\\\\\]/u', $content)) {
            return true;
        }

        return (bool) preg_match('/(?<!\$)\$(?=[^\r\n$]{1,500}\$)(?=[^\r\n$]*(?:\\\\|[=<>_^{}]|[∑√∞≤≥≈≠±×÷]))[^\r\n$]+\$(?!\$)/u', $content);
    }
}

if (!function_exists('qiwiShouldShowToc')) {
    function qiwiShouldShowToc($widget)
    {
        if (empty($widget) || !method_exists($widget, 'is') || !$widget->is('single')) {
            return false;
        }

        $value = (string) qiwiGetFieldValue($widget, 'tocDisplay', '1');
        if ($value === '1') {
            return true;
        }

        if ($value === '0') {
            return false;
        }

        if ($value === 'auto') {
            return $widget->is('post');
        }

        return true;
    }
}

if (!function_exists('qiwiGetThemeAssetUrl')) {
    function qiwiGetThemeAssetUrl($path)
    {
        $path = ltrim((string) $path, '/');
        $options = null;

        if (class_exists('\Widget\Options')) {
            \Widget\Options::alloc()->to($options);
        } elseif (class_exists('Widget_Options')) {
            Widget_Options::alloc()->to($options);
        }

        if (!empty($options) && method_exists($options, 'themeUrl')) {
            ob_start();
            $options->themeUrl($path);
            $url = trim(ob_get_clean());
            if ($url !== '') {
                return $url;
            }
        }

        if (!empty($options) && isset($options->themeUrl)) {
            return rtrim((string) $options->themeUrl, '/') . '/' . $path;
        }

        return $path;
    }
}

if (!function_exists('qiwiGetMappedAssetUrl')) {
    function qiwiGetMappedAssetUrl($path)
    {
        $path = ltrim((string) $path, '/');
        $options = null;

        if (class_exists('\Widget\Options')) {
            \Widget\Options::alloc()->to($options);
        } elseif (class_exists('Widget_Options')) {
            Widget_Options::alloc()->to($options);
        }

        $base = '';
        if (!empty($options) && isset($options->siteUrl)) {
            $base = rtrim((string) $options->siteUrl, '/');
        }

        return ($base !== '' ? $base : '') . '/' . $path;
    }
}

if (!function_exists('qiwiShellQuote')) {
    function qiwiShellQuote($value)
    {
        return "'" . str_replace("'", "'\"'\"'", (string) $value) . "'";
    }
}

if (!function_exists('qiwiGetLocalUpdateMetadata')) {
    function qiwiGetLocalUpdateMetadata()
    {
        $path = __DIR__ . '/update.json';
        if (!is_readable($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('qiwiGetThemeOptionSetting')) {
    function qiwiGetThemeOptionSetting($name, $default = '')
    {
        $options = null;

        if (class_exists('\Widget\Options')) {
            \Widget\Options::alloc()->to($options);
        } elseif (class_exists('Widget_Options')) {
            Widget_Options::alloc()->to($options);
        }

        if (!empty($options) && isset($options->{$name})) {
            return $options->{$name};
        }

        return $default;
    }
}

if (!function_exists('qiwiDecodeTypechoTableOption')) {
    function qiwiDecodeTypechoTableOption($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $decoded = @unserialize($value);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('qiwiGetThemeRelativeDirFromTypechoRoot')) {
    function qiwiGetThemeRelativeDirFromTypechoRoot()
    {
        $dir = str_replace('\\', '/', __DIR__);
        $pos = strrpos($dir, '/usr/');

        if ($pos !== false) {
            return ltrim(substr($dir, $pos + 1), '/');
        }

        return 'usr/themes/' . basename(__DIR__);
    }
}

if (!function_exists('qiwiGetAdminEditingContentType')) {
    function qiwiGetAdminEditingContentType()
    {
        $script = isset($_SERVER['SCRIPT_NAME']) ? strtolower(basename($_SERVER['SCRIPT_NAME'])) : '';
        if (strpos($script, 'write-page') !== false) {
            return 'page';
        }

        if (strpos($script, 'write-post') !== false) {
            return 'post';
        }

        $cid = 0;
        if (isset($_REQUEST['cid'])) {
            $cid = (int) $_REQUEST['cid'];
        }

        if ($cid > 0) {
            $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
            $prefix = $db->getPrefix();
            $row = $db->fetchRow($db->select('type')
                ->from($prefix . 'contents')
                ->where('cid = ?', $cid)
                ->limit(1));

            if (!empty($row['type']) && in_array($row['type'], ['post', 'page'], true)) {
                return $row['type'];
            }
        }

        return '';
    }
}

if (!function_exists('qiwiArchiveVisibilityHookRegistered')) {
    function qiwiArchiveVisibilityHookRegistered()
    {
        // 钩子注册记录只在插件激活时写入数据库；只更新插件文件不重新激活时，
        // 首页/RSS 可见性过滤会静默失效，这里读注册表以便后台给出提示。
        // handles 的键是 "类名:事件名"，值是回调数组或按优先级键包装的回调集合。
        try {
            $options = Typecho_Widget::widget('Widget_Options');
            $plugins = isset($options->plugins) && is_array($options->plugins) ? $options->plugins : array();
            $handles = isset($plugins['handles']) && is_array($plugins['handles']) ? $plugins['handles'] : array();
            foreach (array('Widget_Archive:handleInit', 'Typecho_Widget_Archive:handleInit') as $eventKey) {
                if (!isset($handles[$eventKey]) || !is_array($handles[$eventKey])) {
                    continue;
                }
                foreach ($handles[$eventKey] as $callback) {
                    if (!is_array($callback)
                        || !isset($callback[0], $callback[1])
                        || !is_string($callback[0])) {
                        continue;
                    }
                    if (ltrim($callback[0], '\\') === 'QiwiTheme_Plugin'
                        && (string) $callback[1] === 'handleArchiveInit') {
                        return true;
                    }
                }
            }
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }

        return false;
    }
}

if (!function_exists('qiwiCompanionModulesHealth')) {
    function qiwiCompanionModulesHealth()
    {
        // 站点地图与评论邮件已并入 QiwiTheme；注册记录只在插件激活时写入数据库。
        // 这里检测旧伴生插件残留和合并模块的注册状态，供主题设置后台提示。
        $status = array(
            'legacyPluginsActive' => array(),
            'sitemapRouteActive' => false,
            'mailHookActive' => false,
        );

        try {
            $options = Typecho_Widget::widget('Widget_Options');
            $plugins = isset($options->plugins) && is_array($options->plugins) ? $options->plugins : array();

            if (isset($plugins['activated']) && is_array($plugins['activated'])) {
                foreach (array('QiwiSitemap', 'QiwiCommentMail') as $name) {
                    if (isset($plugins['activated'][$name])) {
                        $status['legacyPluginsActive'][] = $name;
                    }
                }
            }

            $routingTable = $options->routingTable;
            if (is_array($routingTable) && isset($routingTable['qiwi_sitemap_index_route'])) {
                $status['sitemapRouteActive'] = true;
            }

            $handles = isset($plugins['handles']) && is_array($plugins['handles']) ? $plugins['handles'] : array();
            foreach (array('\Widget\Feedback:finishComment', 'Widget_Feedback:finishComment') as $eventKey) {
                if (!isset($handles[$eventKey]) || !is_array($handles[$eventKey])) {
                    continue;
                }
                foreach ($handles[$eventKey] as $callback) {
                    if (!is_array($callback) || !isset($callback[0], $callback[1]) || !is_string($callback[0])) {
                        continue;
                    }
                    if (ltrim($callback[0], '\\') === 'TypechoPlugin\QiwiTheme\Mail'
                        && (string) $callback[1] === 'handleCommentFinished') {
                        $status['mailHookActive'] = true;
                        break 2;
                    }
                }
            }
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }

        return $status;
    }
}

if (!function_exists('qiwiAdminConfigEnhancerAssets')) {
    function qiwiAdminConfigEnhancerAssets()
    {
        $adminConfigCssVersion = file_exists(__DIR__ . '/assets/css/admin-config.css') ? filemtime(__DIR__ . '/assets/css/admin-config.css') : time();
        $adminConfigJsVersion = file_exists(__DIR__ . '/assets/js/admin-config.js') ? filemtime(__DIR__ . '/assets/js/admin-config.js') : time();
        $css = htmlspecialchars(qiwiGetMappedAssetUrl('assets/css/admin-config.css') . '?v=' . $adminConfigCssVersion, ENT_QUOTES, 'UTF-8');
        $js = htmlspecialchars(qiwiGetMappedAssetUrl('assets/js/admin-config.js') . '?v=' . $adminConfigJsVersion, ENT_QUOTES, 'UTF-8');
        $fa = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css';
        $metadata = qiwiGetLocalUpdateMetadata();
        $categories = [];
        try {
            $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
            $rows = $db->fetchAll($db->select('mid', 'name', 'slug', 'parent')
                ->from('table.metas')
                ->where('type = ?', 'category')
                ->order('mid', 'ASC'));
            foreach ($rows as $row) {
                $categories[] = [
                    'mid' => isset($row['mid']) ? (int) $row['mid'] : 0,
                    'name' => isset($row['name']) ? (string) $row['name'] : '',
                    'slug' => isset($row['slug']) ? (string) $row['slug'] : '',
                    'parent' => isset($row['parent']) ? (int) $row['parent'] : 0,
                ];
            }
        } catch (Exception $e) {
            $categories = [];
        } catch (Throwable $e) {
            $categories = [];
        }

        $config = [
            'currentVersion' => isset($metadata['version']) ? (string) $metadata['version'] : '',
            'updateEndpoint' => 'https://api.github.com/repos/lanbinleo/qiwi/contents/update.json',
            'updateApiEndpoint' => 'https://api.github.com/repos/lanbinleo/qiwi/contents/update.json',
            'updateRawEndpoint' => 'https://raw.githubusercontent.com/lanbinleo/qiwi/main/update.json',
            'repositoryUrl' => 'https://github.com/lanbinleo/qiwi',
            'updateCommand' => 'cd ' . qiwiShellQuote(__DIR__) . ' && bash update.sh',
            'themeRelativeDir' => qiwiGetThemeRelativeDirFromTypechoRoot(),
            'cacheTtl' => 21600000,
            'showUpdateLog' => (string) qiwiGetThemeOptionSetting('showUpdateLog', '1') === '0' ? '0' : '1',
            'externalLinkStats' => function_exists('qiwiGetExternalLinkStats') ? qiwiGetExternalLinkStats(30) : [],
            'momentLikeRecords' => function_exists('qiwiGetMomentLikeRecords') ? qiwiGetMomentLikeRecords(100) : [],
            'postLikeRecords' => function_exists('qiwiGetPostLikeRecords') ? qiwiGetPostLikeRecords(100) : [],
            'postLikeArticleStats' => function_exists('qiwiGetPostLikeArticleStats') ? qiwiGetPostLikeArticleStats(100) : [],
            'ipLocationRebuildEndpoint' => qiwiGetThemeActionEndpoint('rebuild-ip-locations'),
            'categories' => $categories,
            'visibilityHookActive' => qiwiArchiveVisibilityHookRegistered(),
            'companionStatus' => function_exists('qiwiCompanionModulesHealth') ? qiwiCompanionModulesHealth() : null,
        ];
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return '<link rel="stylesheet" href="' . $fa . '" crossorigin="anonymous" referrerpolicy="no-referrer"><link rel="stylesheet" href="' . $css . '"><script>window.QIWI_ADMIN_CONFIG=' . $json . ';</script><script defer src="' . $js . '"></script>';
    }
}

if (!function_exists('qiwiGetExternalLinkStats')) {
    function qiwiGetExternalLinkStats($limit = 30)
    {
        if (class_exists('QiwiTheme_Plugin') && method_exists('QiwiTheme_Plugin', 'getExternalLinkStats')) {
            return QiwiTheme_Plugin::getExternalLinkStats($limit);
        }

        try {
            $limit = max(1, min(100, (int) $limit));
            $db = Typecho_Db::get();
            $table = $db->getPrefix() . 'qiwi_external_links';
            $rows = $db->fetchAll($db->select('url', 'host', 'COUNT(id) AS clicks', 'MAX(clicked) AS last_clicked')
                ->from($table)
                ->group('url_hash, url, host')
                ->order('last_clicked', Typecho_Db::SORT_DESC)
                ->limit($limit));

            $items = [];
            foreach ($rows as $row) {
                $items[] = [
                    'url' => isset($row['url']) ? (string) $row['url'] : '',
                    'host' => isset($row['host']) ? (string) $row['host'] : '',
                    'clicks' => isset($row['clicks']) ? (int) $row['clicks'] : 0,
                    'lastClicked' => !empty($row['last_clicked']) ? date('Y-m-d H:i', (int) $row['last_clicked']) : '',
                ];
            }

            return $items;
        } catch (Exception $e) {
            return [];
        } catch (Throwable $e) {
            return [];
        }

        return [];
    }
}

if (!function_exists('qiwiGetMomentLikeRecords')) {
    function qiwiGetMomentLikeRecords($limit = 100)
    {
        if (class_exists('QiwiTheme_Plugin') && method_exists('QiwiTheme_Plugin', 'getMomentLikeRecords')) {
            return QiwiTheme_Plugin::getMomentLikeRecords($limit);
        }

        return [];
    }
}


if (!function_exists('qiwiGetPostLikeRecords')) {
    function qiwiGetPostLikeRecords($limit = 100)
    {
        if (class_exists('QiwiTheme_Plugin') && method_exists('QiwiTheme_Plugin', 'getPostLikeRecords')) {
            return QiwiTheme_Plugin::getPostLikeRecords($limit);
        }

        return [];
    }
}

if (!function_exists('qiwiGetPostLikeArticleStats')) {
    function qiwiGetPostLikeArticleStats($limit = 100)
    {
        if (class_exists('QiwiTheme_Plugin') && method_exists('QiwiTheme_Plugin', 'getPostLikeArticleStats')) {
            return QiwiTheme_Plugin::getPostLikeArticleStats($limit);
        }

        return [];
    }
}

if (!function_exists('qiwiGetOwnCommentIds')) {
    /**
     * 当前浏览器提交过的评论 coid（来自 QiwiTheme 插件签发的签名 cookie）。
     * 用于向访客展示"自己的待审核评论"，未启用插件时返回空数组即不展示。
     */
    function qiwiGetOwnCommentIds()
    {
        if (!class_exists('QiwiTheme_Plugin') || !method_exists('QiwiTheme_Plugin', 'ownCommentIds')) {
            return [];
        }

        try {
            $ids = QiwiTheme_Plugin::ownCommentIds();
            return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
        } catch (Exception $e) {
            return [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('qiwiGetThemeActionEndpoint')) {
    function qiwiGetThemeActionEndpoint($action, $options = null)
    {
        $action = trim((string) $action);
        if ($action === '') {
            return '';
        }

        try {
            if ($options === null) {
                $options = Typecho_Widget::widget('Widget_Options');
            }

            $actionTable = [];
            if (isset($options->actionTable)) {
                $actionTable = qiwiDecodeTypechoTableOption($options->actionTable);
            }

            if (!isset($actionTable['qiwi-theme']) || $actionTable['qiwi-theme'] !== 'QiwiTheme_Action') {
                return '';
            }

            Typecho_Widget::widget('Widget_Security')->to($security);
            return $security->getIndex('/action/qiwi-theme?do=' . rawurlencode($action));
        } catch (Exception $e) {
            return '';
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('qiwiAdminEditorShortcodeAssets')) {
    function qiwiAdminEditorShortcodeAssets()
    {
        $cssVersion = is_readable(__DIR__ . '/assets/css/admin-editor.css') ? filemtime(__DIR__ . '/assets/css/admin-editor.css') : time();
        $jsVersion = is_readable(__DIR__ . '/assets/js/admin-editor.js') ? filemtime(__DIR__ . '/assets/js/admin-editor.js') : time();
        $css = htmlspecialchars(qiwiGetMappedAssetUrl('assets/css/admin-editor.css') . '?v=' . $cssVersion, ENT_QUOTES, 'UTF-8');
        $js = htmlspecialchars(qiwiGetMappedAssetUrl('assets/js/admin-editor.js') . '?v=' . $jsVersion, ENT_QUOTES, 'UTF-8');

        return '<link rel="stylesheet" href="' . $css . '"><script defer src="' . $js . '"></script>';
    }
}

function themeConfig($form)
{
    $logoUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'logoUrl',
        null,
        null,
        _t('导航栏头像 / 站点 LOGO 地址'),
        _t('在这里填入一个图片 URL 地址，会显示在顶部导航栏的网站标题前。')
    );

    $form->addInput($logoUrl);

    $v2EnglishTitle = new Typecho_Widget_Helper_Form_Element_Text(
        'v2EnglishTitle',
        null,
        'QIWI JOURNAL',
        _t('V2 侧栏 - 英文站名'),
        _t('显示在中文站名下方，例如 QIWI JOURNAL。')
    );
    $form->addInput($v2EnglishTitle);

    $v2SidebarSlogan = new Typecho_Widget_Helper_Form_Element_Text(
        'v2SidebarSlogan',
        null,
        '向内求索 · ON AIR',
        _t('V2 侧栏 - Slogan'),
        _t('显示在侧栏站标底部的短句。')
    );
    $form->addInput($v2SidebarSlogan);

    $v2FooterMotto = new Typecho_Widget_Helper_Form_Element_Text(
        'v2FooterMotto',
        null,
        '向内求索，向外生长',
        _t('V2 页脚 - Motto'),
        _t('显示在页脚 Frequency 下方。')
    );
    $form->addInput($v2FooterMotto);

    // 验证码由 Qiwi CAP 或兼容的 Qiwi GTest 伴生插件提供。
    $enabledCaptcha = new Typecho_Widget_Helper_Form_Element_Radio(
        'enabledCaptcha',
        array(
            '1' => _t('启用'),
            '0' => _t('关闭')
        ),
        '0',
        _t('启用验证码'),
        _t('安装并配置 Qiwi CAP（推荐）或 Qiwi GTest 后启用。两个验证码插件不能同时运行。')
    );

    $form->addInput($enabledCaptcha);

    $homeVisibilityDefault = new Typecho_Widget_Helper_Form_Element_Radio(
        'homeVisibilityDefault',
        array(
            'show' => _t('显示'),
            'hide' => _t('隐藏')
        ),
        'show',
        _t('文章默认 - 首页展示'),
        _t('文章没有单独设置时，是否显示在首页。分类设置和文章字段都可以单独覆盖这个默认值。')
    );
    $form->addInput($homeVisibilityDefault);

    $rssVisibilityDefault = new Typecho_Widget_Helper_Form_Element_Radio(
        'rssVisibilityDefault',
        array(
            'show' => _t('进入 RSS'),
            'hide' => _t('不进入 RSS')
        ),
        'show',
        _t('文章默认 - RSS 展示'),
        _t('文章没有单独设置时，是否进入整站 RSS / Atom。分类设置和文章字段都可以单独覆盖这个默认值。')
    );
    $form->addInput($rssVisibilityDefault);

    $categoryVisibilityData = new Typecho_Widget_Helper_Form_Element_Textarea(
        'categoryVisibilityData',
        null,
        null,
        _t('分类展示设置 - 原始数据'),
        _t('分类级“首页展示 / RSS 展示”由网站信息里的结构化编辑器管理。这里保留 JSON 作为兼容和导入导出的原始数据。')
    );
    $form->addInput($categoryVisibilityData);

    $sidebarProfileAvatar = new Typecho_Widget_Helper_Form_Element_Text(
        'sidebarProfileAvatar',
        null,
        null,
        _t('导航栏 - 头像'),
        _t('桌面侧栏顶部展示的头像 URL。留空时兼容旧版“关于页面头像”，再留空则使用默认头像。')
    );
    $form->addInput($sidebarProfileAvatar);

    $enableBusuanzi = new Typecho_Widget_Helper_Form_Element_Radio(
        'enableBusuanzi',
        array(
            1 => _t('加载'),
            0 => _t('不加载')
        ),
        0,
        _t('不蒜子统计脚本'),
        _t('开启后主题会加载 busuanzi.cc 的统计脚本；如果你已通过“JS 追踪代码”手动加入，可保持关闭。')
    );
    $form->addInput($enableBusuanzi);

    $umamiApiBase = new Typecho_Widget_Helper_Form_Element_Text(
        'umamiApiBase',
        null, null,
        _t('Umami 统计地址'),
        _t('填写自建 Umami 的根地址（例如 https://trace.example.com，仅支持 HTTPS），与下方分享 ID 一起填写后，归档页会展示「读者来访」热力图；需要 QiwiTheme 插件处于启用状态。')
    );
    $form->addInput($umamiApiBase);

    $umamiShareId = new Typecho_Widget_Helper_Form_Element_Text(
        'umamiShareId',
        null, null,
        _t('Umami 分享 ID'),
        _t('在 Umami 网站设置的 Share URL 中开启分享后，取链接里 /share/ 后面那串 ID 填在这里。分享链接本身只读且不含任何密钥。建议 Umami 网站时区与博客一致，热力图日期才会对齐。')
    );
    $form->addInput($umamiShareId);

    $obsidianPushToken = new Typecho_Widget_Helper_Form_Element_Text(
        'obsidianPushToken',
        null, null,
        _t('Obsidian 随笔推送令牌'),
        _t('随机长字符串（例如 32 位以上）。留空表示关闭推送入口；填写后，主题 tools/obsidian-sync 采集脚本（或未来的 Obsidian 插件）携带同一令牌即可把「当天新建笔记数」推进「写作经历」热力图。只会接收日期与篇数聚合，不涉及任何笔记内容。')
    );
    $form->addInput($obsidianPushToken);

    // === 站点地图 / 订源（由 QiwiTheme 插件的 Sitemap 模块提供） ===
    $enableSitemap = new Typecho_Widget_Helper_Form_Element_Radio(
        'enableSitemap',
        array('1' => _t('启用'), '0' => _t('关闭')),
        '1',
        _t('Sitemap 输出'),
        _t('关闭后 sitemap 路由仍存在，但会返回 404。')
    );
    $form->addInput($enableSitemap);

    $enablePosts = new Typecho_Widget_Helper_Form_Element_Radio(
        'enablePosts',
        array('1' => _t('包含'), '0' => _t('不包含')),
        '1',
        _t('包含文章'),
        _t('输出已发布、未加密、发布时间不晚于当前时间的文章。')
    );
    $form->addInput($enablePosts);

    $enablePages = new Typecho_Widget_Helper_Form_Element_Radio(
        'enablePages',
        array('1' => _t('包含'), '0' => _t('不包含')),
        '1',
        _t('包含独立页面'),
        _t('输出已发布、未加密、发布时间不晚于当前时间的独立页面。')
    );
    $form->addInput($enablePages);

    $enableCategories = new Typecho_Widget_Helper_Form_Element_Radio(
        'enableCategories',
        array('1' => _t('包含'), '0' => _t('不包含')),
        '1',
        _t('包含分类'),
        _t('分类页 lastmod 会使用该分类下最新公开文章的修改时间。')
    );
    $form->addInput($enableCategories);

    $enableTags = new Typecho_Widget_Helper_Form_Element_Radio(
        'enableTags',
        array('1' => _t('包含'), '0' => _t('不包含')),
        '1',
        _t('包含标签'),
        _t('标签页 lastmod 会使用该标签下最新公开文章的修改时间。')
    );
    $form->addInput($enableTags);

    $enableXsl = new Typecho_Widget_Helper_Form_Element_Radio(
        'enableXsl',
        array('1' => _t('启用'), '0' => _t('关闭')),
        '1',
        _t('可视化 XSL'),
        _t('给浏览器访问 sitemap 时使用。搜索引擎会读取原始 XML，不依赖这个样式。')
    );
    $form->addInput($enableXsl);

    $enableRobots = new Typecho_Widget_Helper_Form_Element_Radio(
        'enableRobots',
        array('1' => _t('启用'), '0' => _t('关闭')),
        '1',
        _t('robots.txt 输出'),
        _t('输出 Sitemap 地址，并用注释标出 RSS 订阅地址。若站点根目录已有实体 robots.txt，服务器通常会优先返回实体文件。')
    );
    $form->addInput($enableRobots);

    $enableMomentsFeed = new Typecho_Widget_Helper_Form_Element_Radio(
        'enableMomentsFeed',
        array('1' => _t('启用'), '0' => _t('关闭')),
        '1',
        _t('时光机 RSS'),
        _t('输出 /timemachine.xml，自动读取使用 page-timemachine.php 模板的独立页面，并只包含页面作者自己的已审核评论。')
    );
    $form->addInput($enableMomentsFeed);

    $momentsPageCid = new Typecho_Widget_Helper_Form_Element_Text(
        'momentsPageCid',
        null,
        null,
        _t('时光机页面 CID'),
        _t('通常留空自动查找 page-timemachine.php。若有多个时光机页面，可填写指定页面 CID。')
    );
    $form->addInput($momentsPageCid);

    $momentsFeedLimit = new Typecho_Widget_Helper_Form_Element_Text(
        'momentsFeedLimit',
        null,
        '20',
        _t('时光机 RSS 条数'),
        _t('默认输出最近 20 条，范围 1-100。')
    );
    $form->addInput($momentsFeedLimit);

    $enableFeedDiscovery = new Typecho_Widget_Helper_Form_Element_Radio(
        'enableFeedDiscovery',
        array('1' => _t('启用'), '0' => _t('关闭')),
        '1',
        _t('RSS / Atom 发现链接'),
        _t('在页面 head 中补充 RSS、Atom 与 sitemap 发现链接，便于浏览器、阅读器和爬虫识别订阅入口。')
    );
    $form->addInput($enableFeedDiscovery);

    $enableFeedShortcodeCompat = new Typecho_Widget_Helper_Form_Element_Radio(
        'enableFeedShortcodeCompat',
        array('1' => _t('启用'), '0' => _t('关闭')),
        '1',
        _t('RSS 短代码兼容'),
        _t('将 [fold]、[red]、[mark]、[badge]、[callout]、[button] 等主题短代码转换为阅读器更容易渲染的普通 HTML。')
    );
    $form->addInput($enableFeedShortcodeCompat);

    $enableFeedAvatar = new Typecho_Widget_Helper_Form_Element_Radio(
        'enableFeedAvatar',
        array('1' => _t('启用'), '0' => _t('关闭')),
        '1',
        _t('RSS / Atom 头像增强'),
        _t('为 RSS 频道补充 image，为 Atom 补充 icon/logo，帮助阅读器识别博客头像。文章条目封面会优先使用文章头图字段。')
    );
    $form->addInput($enableFeedAvatar);

    $avatarUrl = new Typecho_Widget_Helper_Form_Element_Text(
        'avatarUrl',
        null,
        null,
        _t('博客头像 URL'),
        _t('留空时会按 Qiwi 主题配置依次读取 sidebarProfileAvatar、aboutAvatar、logoUrl。用于 sitemap 浏览器可视化页面，以及 RSS/Atom 订阅头像增强。')
    );
    $form->addInput($avatarUrl);

    $excludedCids = new Typecho_Widget_Helper_Form_Element_Text(
        'excludedCids',
        null,
        null,
        _t('排除内容 CID'),
        _t('逗号分隔，例如 12,34,56。可用于排除不希望进入 sitemap 的文章或独立页面。')
    );
    $form->addInput($excludedCids);

    // === 邮件通知（由 QiwiTheme 插件的评论邮件模块提供） ===
    $mailMode = new Typecho_Widget_Helper_Form_Element_Radio(
        'mailMode',
        array(
            'smtp' => 'smtp',
            'resend' => 'Resend API',
            'mail' => 'mail()',
            'sendmail' => 'sendmail()'
        ),
        'smtp',
        _t('发信方式')
    );
    $form->addInput($mailMode);

    $mailHost = new Typecho_Widget_Helper_Form_Element_Text(
        'mailHost',
        null,
        '',
        _t('SMTP地址'),
        _t('使用 SMTP 时填写 SMTP 服务器地址。使用 Resend API 时可留空。')
    );
    $form->addInput($mailHost);

    $mailPort = new Typecho_Widget_Helper_Form_Element_Text(
        'mailPort',
        null,
        '25',
        _t('SMTP端口'),
        _t('SMTP服务端口, 一般为25. SSL一般为465')
    );
    $form->addInput($mailPort->addRule('isInteger', _t('端口号必须为数字')));

    $mailUser = new Typecho_Widget_Helper_Form_Element_Text(
        'mailUser',
        null,
        null,
        _t('SMTP用户'),
        _t('SMTP服务验证用户名, 一般为邮箱账户。使用 SMTP 时也会作为默认发件邮箱。')
    );
    $form->addInput($mailUser);

    $mailPass = new Typecho_Widget_Helper_Form_Element_Password(
        'mailPass',
        null,
        null,
        _t('SMTP密码'),
        _t('不会出现在主题设置整包导出里，仅保存在本站。')
    );
    $form->addInput($mailPass);

    $mailValidate = new Typecho_Widget_Helper_Form_Element_Checkbox(
        'mailValidate',
        array(
            'validate' => '服务器需要验证',
            'ssl' => 'ssl加密',
            'tls' => 'tls加密',
            'solve544' => '启用抄送以规避 544 错误'
        ),
        array('validate'),
        _t('SMTP验证')
    );
    $form->addInput($mailValidate);

    $mailResendApiKey = new Typecho_Widget_Helper_Form_Element_Password(
        'mailResendApiKey',
        null,
        null,
        _t('Resend API Key'),
        _t('发信方式选择 Resend API 时填写, 例如 re_xxxxxxxxx。不会出现在主题设置整包导出里。')
    );
    $form->addInput($mailResendApiKey);

    $mailResendFrom = new Typecho_Widget_Helper_Form_Element_Text(
        'mailResendFrom',
        null,
        null,
        _t('Resend 发件邮箱'),
        _t('必须是 Resend 已验证域名下的邮箱地址, 例如 no-reply@example.com。发件人名称使用下方“发件人名称”。')
    );
    $form->addInput($mailResendFrom->addRule('email', _t('请填写正确的 Resend 发件邮箱!')));

    $mailResendApiUrl = new Typecho_Widget_Helper_Form_Element_Text(
        'mailResendApiUrl',
        null,
        'https://api.resend.com/emails',
        _t('Resend API 地址'),
        _t('默认即可。如需代理或自建网关, 请填写完整 HTTPS 地址。')
    );
    $form->addInput($mailResendApiUrl);

    $mailResendCaFile = new Typecho_Widget_Helper_Form_Element_Text(
        'mailResendCaFile',
        null,
        null,
        _t('Resend CA 证书路径'),
        _t('可选。Windows 或 phpstudy 无法验证 HTTPS 证书时填写 cacert.pem 的绝对路径；正常环境留空。')
    );
    $form->addInput($mailResendCaFile);

    $mailFromName = new Typecho_Widget_Helper_Form_Element_Text(
        'mailFromName',
        null,
        null,
        _t('发件人名称'),
        _t('发件人名称, 留空则使用博客标题')
    );
    $form->addInput($mailFromName);

    $mailRecipient = new Typecho_Widget_Helper_Form_Element_Text(
        'mailRecipient',
        null,
        null,
        _t('管理员接收邮件地址'),
        _t('接收管理员通知的邮箱。留空则使用文章作者个人设置中的邮箱地址。')
    );
    $form->addInput($mailRecipient->addRule('email', _t('请填写正确的邮件地址!')));

    $mailContactme = new Typecho_Widget_Helper_Form_Element_Text(
        'mailContactme',
        null,
        null,
        _t('模板中“联系我”的邮件地址'),
        _t('联系我用的邮件地址, 留空则使用文章作者个人设置中的邮件地址。')
    );
    $form->addInput($mailContactme->addRule('email', _t('请填写正确的邮件地址!')));

    $mailTitleForOwner = new Typecho_Widget_Helper_Form_Element_Text(
        'mailTitleForOwner',
        null,
        '[{{title}}] 一文有新的评论',
        _t('管理员通知邮件标题')
    );
    $form->addInput($mailTitleForOwner->addRule('required', _t('管理员通知邮件标题不能为空')));

    $mailTitleForGuest = new Typecho_Widget_Helper_Form_Element_Text(
        'mailTitleForGuest',
        null,
        '您在 [{{title}}] 的评论有了回复',
        _t('用户回复通知邮件标题')
    );
    $form->addInput($mailTitleForGuest->addRule('required', _t('用户回复通知邮件标题不能为空')));

    $mailTemplateHelp = _t('支持变量: {{siteTitle}}, {{title}}, {{author}}, {{author_p}}, {{ip}}, {{mail}}, {{permalink}}, {{manage}}, {{text}}, {{text_p}}, {{contactme}}, {{time}}, {{status}}。留空时使用 QiwiTheme 插件 template 目录中的默认模板，也可在后台「Qiwi 评论邮件」控制台的“编辑邮件模板”里直接改文件。');

    $mailOwnerTemplate = new Typecho_Widget_Helper_Form_Element_Textarea(
        'mailOwnerTemplate',
        null,
        null,
        _t('管理员通知邮件模板'),
        $mailTemplateHelp
    );
    $form->addInput($mailOwnerTemplate);

    $mailGuestTemplate = new Typecho_Widget_Helper_Form_Element_Textarea(
        'mailGuestTemplate',
        null,
        null,
        _t('用户回复通知邮件模板'),
        $mailTemplateHelp
    );
    $form->addInput($mailGuestTemplate);

    $mailNotifyStatus = new Typecho_Widget_Helper_Form_Element_Checkbox(
        'mailNotifyStatus',
        array(
            'approved' => '提醒已通过评论',
            'waiting' => '提醒待审核评论',
            'spam' => '提醒垃圾评论'
        ),
        array('approved', 'waiting'),
        _t('管理员提醒状态'),
        _t('该选项仅针对管理员通知。待审核评论会固定提醒管理员，用户回复通知只会在回复已通过后发送。')
    );
    $form->addInput($mailNotifyStatus);

    $mailSwitches = new Typecho_Widget_Helper_Form_Element_Checkbox(
        'mailSwitches',
        array(
            'to_owner' => '有新评论及回复时, 发邮件通知管理员。',
            'to_guest' => '评论被公开回复时, 发邮件通知被回复者。',
            'to_me' => '自己回复自己时也发邮件。',
            'auto_process' => '评论入队后自动处理邮件队列。',
        ),
        array('to_owner', 'to_guest', 'auto_process'),
        _t('通知与队列设置'),
        null
    );
    $form->addInput($mailSwitches->multiMode());

    $mailBatchSize = new Typecho_Widget_Helper_Form_Element_Text(
        'mailBatchSize',
        null,
        '2',
        _t('每次最多处理邮件数'),
        _t('一次 worker 最多处理多少封邮件。建议 1 到 2 封。')
    );
    $form->addInput($mailBatchSize->addRule('isInteger', _t('每次最多处理邮件数必须为数字')));

    $mailRateLimitPerSecond = new Typecho_Widget_Helper_Form_Element_Text(
        'mailRateLimitPerSecond',
        null,
        '2',
        _t('每秒最多发送邮件数'),
        _t('默认 2，适合 Resend 等常见 API 限制。该限制按邮件任务计算。')
    );
    $form->addInput($mailRateLimitPerSecond->addRule('isInteger', _t('每秒最多发送邮件数必须为数字')));

    $mailMaxAttempts = new Typecho_Widget_Helper_Form_Element_Text(
        'mailMaxAttempts',
        null,
        '5',
        _t('最大重试次数'),
        _t('超过次数后任务标记为失败, 可在后台手动重试。')
    );
    $form->addInput($mailMaxAttempts->addRule('isInteger', _t('最大重试次数必须为数字')));

    $mailLogKeepDays = new Typecho_Widget_Helper_Form_Element_Text(
        'mailLogKeepDays',
        null,
        '30',
        _t('日志保留天数'),
        _t('成功发送记录会保留指定天数, 失败记录会一直保留到手动清理或重试成功。')
    );
    $form->addInput($mailLogKeepDays->addRule('isInteger', _t('日志保留天数必须为数字')));

    $qiwiMailOptions = Typecho_Widget::widget('Widget_Options');
    $qiwiMailEntryUrl = ($qiwiMailOptions->rewrite) ? $qiwiMailOptions->siteUrl : $qiwiMailOptions->siteUrl . 'index.php';
    $qiwiMailDeliverUrl = rtrim($qiwiMailEntryUrl, '/') . '/action/qiwi-comment-mail?do=deliverMail&key={KEY}';
    $mailQueueKey = new Typecho_Widget_Helper_Form_Element_Text(
        'mailQueueKey',
        null,
        \Typecho\Common::randString(16),
        _t('邮件队列触发密钥'),
        _t('外部定时任务地址为 ' . $qiwiMailDeliverUrl . '。自动处理无法使用时，可用该地址定时触发。密钥在首次保存主题设置后生效，未保存前队列不会自动触发。')
    );
    $form->addInput($mailQueueKey->addRule('required', _t('触发密钥不能为空')));

    // 开往功能
    $enableTravellings = new Typecho_Widget_Helper_Form_Element_Radio(
        'enableTravellings',
        array(1 => _t('启用'),
              0 => _t('关闭')),
        1,
        _t('开往（Travellings）'),
        _t('在 V2 页脚显示“开往”链接，默认启用。')
    );
    $form->addInput($enableTravellings);

    $showUpdateLog = new Typecho_Widget_Helper_Form_Element_Radio(
        'showUpdateLog',
        array(
            1 => _t('显示'),
            0 => _t('隐藏')
        ),
        1,
        _t('后台更新提示'),
        _t('控制主题设置页顶部的版本检查与更新日志卡片；隐藏后仍可在这里重新开启。')
    );
    $form->addInput($showUpdateLog);

    $showVersionDrawer = new Typecho_Widget_Helper_Form_Element_Radio(
        'showVersionDrawer',
        array(
            1 => _t('自动弹出'),
            0 => _t('不自动弹出')
        ),
        1,
        _t('前台版本弹窗'),
        _t('控制站点前台版本更新抽屉是否在版本变化后自动弹出；页脚版本号仍可手动打开更新日志。')
    );
    $form->addInput($showVersionDrawer);

    $navItems = new Typecho_Widget_Helper_Form_Element_Textarea(
        'navItems',
        null,
        null,
        _t('顶部导航配置'),
        _t("留空则自动显示所有独立页面。每行一个导航项：标题|链接|Font Awesome 图标类。二级菜单在行首加 -，例如：\n归档|template:page-archives.php|fa-solid fa-box-archive\n- 分类|template:page-categories.php|fa-solid fa-folder\n- 标签|template:page-tags.php|fa-solid fa-tags\n外链|https://example.com|fa-solid fa-arrow-up-right-from-square\n链接支持完整 URL、/path、slug、slug:about、page:about、template:page-tags.php。标题里的 | 写成 \\|、\\ 写成 \\\\ 可以原样保留。") . qiwiAdminConfigEnhancerAssets()
    );
    $form->addInput($navItems);

    $homeNavTitle = new Typecho_Widget_Helper_Form_Element_Text(
        'homeNavTitle',
        null,
        '首页',
        _t('首页导航名称'),
        _t('首页导航始终显示且不能删除；留空时恢复为“首页”。')
    );
    $form->addInput($homeNavTitle);

    $sidebarMomentCount = new Typecho_Widget_Helper_Form_Element_Text(
        'sidebarMomentCount',
        null,
        '4',
        _t('首页最近动态 - 展示数量'),
        _t('首页顶部最近动态轮播的数量，建议 3-6 条。')
    );
    $form->addInput($sidebarMomentCount);

    // 关于页面信息
    $aboutBio = new Typecho_Widget_Helper_Form_Element_Text('aboutBio', null, null, _t('关于页面 - 简介'), _t('在这里填写你的简介，将显示在关于页面的个人信息卡片中'));
    $aboutAvatar = new Typecho_Widget_Helper_Form_Element_Text('aboutAvatar', null, null, _t('关于页面 - 头像'), _t('在这里填写你的头像URL地址，将显示在关于页面的个人信息卡片中，留空则显示默认头像'));

    $form->addInput($aboutBio);
    $form->addInput($aboutAvatar);

    // 自定义CSS / JS / 页脚信息 / JS追踪代码
    $customCSS = new Typecho_Widget_Helper_Form_Element_Textarea('customCSS', null, null, _t('自定义 CSS'), _t('在这里填写自定义 CSS 代码'));
    $customJS = new Typecho_Widget_Helper_Form_Element_Textarea('customJS', null, null, _t('自定义 JS'), _t('在这里填写自定义 JS 代码'));
    $trackingCode = new Typecho_Widget_Helper_Form_Element_Text('trackingCode', null, null, _t('JS 追踪代码'), _t('在这里填写第三方统计 JS 代码'));
    $footerInfo = new Typecho_Widget_Helper_Form_Element_Text('footerInfo', null, null, _t('页脚信息'), _t('在这里填写页脚信息，支持 HTML'));
    $defaultCopyrightInfo = new Typecho_Widget_Helper_Form_Element_Textarea(
        'defaultCopyrightInfo',
        null,
        null,
        _t('默认版权说明'),
        _t("文章未单独填写版权说明时使用。支持短代码：[badge]、[callout]、[button]、[buttons]、[link]、[not-by-ai]、[noai]；支持版权魔法标签：[default]、[thread]、[no-repost]、[ai-generated]；支持占位符 {permalink}、{title}、{author}、{site}、{year}、{thread_title}。普通外链会自动解析。留空则使用主题内置默认文案。")
    );
    $defaultCopyrightLicense = new Typecho_Widget_Helper_Form_Element_Radio(
        'defaultCopyrightLicense',
        array(
            'cc-by-4' => _t('CC BY 4.0'),
            'cc-by-sa-4' => _t('CC BY-SA 4.0'),
            'cc-by-nd-4' => _t('CC BY-ND 4.0'),
            'cc-by-nc-4' => _t('CC BY-NC 4.0'),
            'cc-by-nc-sa-4' => _t('CC BY-NC-SA 4.0'),
            'cc-by-nc-nd-4' => _t('CC BY-NC-ND 4.0'),
            'cc0-1' => _t('CC0 1.0'),
            'all-rights-reserved' => _t('保留所有权利')
        ),
        'cc-by-nc-nd-4',
        _t('默认版权协议'),
        _t('用于主题内置默认版权说明的协议徽章和链接；文章自定义版权说明中使用 [default] 时也会读取这里。')
    );

    $form->addInput($customCSS);
    $form->addInput($customJS);
    $form->addInput($trackingCode);
    $form->addInput($footerInfo);
    $form->addInput($defaultCopyrightLicense->multiMode());
    $form->addInput($defaultCopyrightInfo);
    $postSupportEnabled = new Typecho_Widget_Helper_Form_Element_Radio(
        'postSupportEnabled',
        array(
            1 => _t('启用'),
            0 => _t('关闭')
        ),
        0,
        _t('文章 - 支持作者按钮'),
        _t('启用后，文章点赞按钮右侧会显示“支持作者”，悬浮展示收款二维码和说明文字。')
    );

    $postSupportQrUrl = new Typecho_Widget_Helper_Form_Element_Text(
        'postSupportQrUrl',
        null,
        null,
        _t('文章 - 支持作者二维码'),
        _t('填写收款二维码图片 URL；为空时不会显示支持作者按钮。')
    );

    $postSupportTopText = new Typecho_Widget_Helper_Form_Element_Text(
        'postSupportTopText',
        null,
        '请我喝一杯咖啡吧',
        _t('文章 - 支持作者上方文案'),
        _t('显示在二维码上方。')
    );

    $postSupportBottomText = new Typecho_Widget_Helper_Form_Element_Text(
        'postSupportBottomText',
        null,
        '或者评论一下分享你的感受',
        _t('文章 - 支持作者下方文案'),
        _t('显示在二维码下方。')
    );

    $form->addInput($postSupportEnabled->multiMode());
    $form->addInput($postSupportQrUrl);
    $form->addInput($postSupportTopText);
    $form->addInput($postSupportBottomText);

    // === 友链配置 ===
    $friendsData = new Typecho_Widget_Helper_Form_Element_Textarea(
        'friendsData',
        null,
        null,
        _t('友链数据 (JSON格式)'),
        _t('在这里填入友链数据，格式为JSON。')
    );
    $form->addInput($friendsData);

    $friendFeedEnabled = new Typecho_Widget_Helper_Form_Element_Radio(
        'friendFeedEnabled',
        array(
            1 => _t('启用'),
            0 => _t('关闭')
        ),
        0,
        _t('朋友圈 RSS - 前台动态'),
        _t('启用后，友链页面会增加“动态”标签页，通过 public API 展示友站近期文章。')
    );
    $form->addInput($friendFeedEnabled);

    $friendFeedBaseUrl = new Typecho_Widget_Helper_Form_Element_Text(
        'friendFeedBaseUrl',
        null,
        null,
        _t('朋友圈 RSS - Base URL'),
        _t('QiwiRss 服务地址，例如 http://127.0.0.1:8080 或 https://rss.example.com。')
    );
    $form->addInput($friendFeedBaseUrl);

    $friendFeedAdminToken = new Typecho_Widget_Helper_Form_Element_Text(
        'friendFeedAdminToken',
        null,
        null,
        _t('朋友圈 RSS - Admin Token'),
        _t('仅用于主题后台读取和保存 QiwiRss 远端配置，前台不会输出。')
    );
    $form->addInput($friendFeedAdminToken);

    $friendFeedLimit = new Typecho_Widget_Helper_Form_Element_Text(
        'friendFeedLimit',
        null,
        '10',
        _t('朋友圈 RSS - 每页文章数量'),
        _t('友链页面“动态”标签页每页展示的文章数量，默认 10。')
    );
    $form->addInput($friendFeedLimit);

    // === 归档页统计配置 ===
    $bookReference = new Typecho_Widget_Helper_Form_Element_Text(
        'bookReference',
        null,
        null,
        _t('书籍参考 (用于归档页统计)'),
        _t('格式："书名, 字数&&书名, 字数&&..."，例如："《球状闪电》, 210000&&《三体》, 330000&&《流浪地球》, 23000"')
    );
    $form->addInput($bookReference);
}

function themeFields($layout) {
    $contentType = qiwiGetAdminEditingContentType();
    $isPageEditor = $contentType === 'page';
    $isPostEditor = $contentType === 'post';
    $isUnknownEditor = $contentType === '';

    $isLatex = new Typecho_Widget_Helper_Form_Element_Radio('isLatex',
    array(1 => _t('启用'),
    0 => _t('关闭')),
    0, _t('通用 - LaTeX 渲染'), _t('默认关闭增加网页访问速度；文章或页面内存在 LaTeX 语法时启用。') . qiwiAdminEditorShortcodeAssets());

    $tocDisplay = new Typecho_Widget_Helper_Form_Element_Radio(
        'tocDisplay',
        array(
            '1' => _t('显示'),
            '0' => _t('隐藏'),
            'auto' => _t('兼容旧逻辑（文章开启，页面关闭）')
        ),
        '1',
        _t('通用 - 侧边目录'),
        _t('默认显示；可按当前文章/页面单独关闭。目录只会在正文存在 h2、h3 或 h4 标题时生成。')
    );

    // 设置文章简介
    $excerpt = new Typecho_Widget_Helper_Form_Element_Textarea('excerpt', null, null, _t('文章 - 简介'), _t('在这里填写文章的简介，将在文章列表中显示，为空则默认摘录正文前200个字符。'));

    $copyrightInfo = new Typecho_Widget_Helper_Form_Element_Textarea(
        'copyrightInfo',
        null,
        null,
        _t('文章 - 版权说明'),
        _t("留空则使用默认版权说明。支持短代码和版权魔法标签，例如：[default]、[thread]、[not-by-ai]、[noai]、[no-repost]、[ai-generated]。也可继续使用 [badge]、[callout]、[link href=\"{permalink}\"]原文链接[/link] 等写法。")
    );

    $friendsSubtitle = new Typecho_Widget_Helper_Form_Element_Text('friendsSubtitle', null, null, _t('页面 - 友链页副标题'), _t('使用“友链页面”模板时显示在页面标题下方；页面正文会显示在友链列表之后、申请表单之前。'));

    $navShow = new Typecho_Widget_Helper_Form_Element_Radio(
        'navShow',
        array(
            1 => _t('显示'),
            0 => _t('隐藏')
        ),
        1,
        _t('页面 - 顶部导航栏展示'),
        _t('控制该独立页面是否出现在自动生成的顶部导航栏中。手动导航配置不受此项影响。')
    );

    // 设置头图URL
    $thumbnail = new Typecho_Widget_Helper_Form_Element_Text('thumbnail', null, null, _t('文章 - 头图'), _t('在这里填写文章的头图 URL 地址。'));

    // 是否展示头图（不展示，首页展示，文章页展示，都展示）
    $showThumbnail = new Typecho_Widget_Helper_Form_Element_Radio('showThumbnail',
        array(0 => _t('不展示'),
              3 => _t('都展示'),
              1 => _t('首页展示'),
              2 => _t('文章页展示')),
        3, _t('文章 - 展示头图'), _t('控制头图在文章列表和文章详情页的展示位置。'));

    // 是否置顶文章
    $isSticky = new Typecho_Widget_Helper_Form_Element_Radio('isSticky',
        array(1 => _t('是'),
              0 => _t('否')),
        0, _t('文章 - 置顶文章'), _t('置顶的文章将在首页优先显示。'));

    $homeVisibility = new Typecho_Widget_Helper_Form_Element_Radio(
        'homeVisibility',
        array(
            'default' => _t('跟随主题设置'),
            'show' => _t('显示在首页'),
            'hide' => _t('不显示在首页')
        ),
        'default',
        _t('文章 - 首页展示'),
        _t('单篇文章可以覆盖主题设置中的首页展示默认值。')
    );

    $rssVisibility = new Typecho_Widget_Helper_Form_Element_Radio(
        'rssVisibility',
        array(
            'default' => _t('跟随主题设置'),
            'show' => _t('进入 RSS'),
            'hide' => _t('不进入 RSS')
        ),
        'default',
        _t('文章 - RSS 展示'),
        _t('单篇文章可以覆盖主题设置中的 RSS 展示默认值。')
    );

    $layout->addItem($isLatex);
    $layout->addItem($tocDisplay);

    if ($isPostEditor || $isUnknownEditor) {
        $layout->addItem($excerpt);
        $layout->addItem($copyrightInfo);
        $layout->addItem($showThumbnail);
        $layout->addItem($thumbnail);
        $layout->addItem($isSticky);
        $layout->addItem($homeVisibility);
        $layout->addItem($rssVisibility);
    }

    if ($isPageEditor || $isUnknownEditor) {
        $layout->addItem($friendsSubtitle);
        $layout->addItem($navShow);
    }
}

if (!function_exists('qiwiExtractPlainText')) {
    function qiwiExtractPlainText($text)
    {
        if ($text === null || $text === '') {
            return '';
        }

        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = qiwiStripReadableShortcodes($text);

        // Keep readable content while removing Markdown syntax.
        $text = preg_replace('/```[\s\S]*?```/u', ' ', $text);
        $text = preg_replace('/~~~[\s\S]*?~~~/u', ' ', $text);
        $text = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/u', ' ', $text);
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/u', '$1', $text);
        $text = preg_replace('/<((https?:\/\/|mailto:)[^>]+)>/iu', '$1', $text);
        $text = preg_replace('/^\s{0,3}>\s?/mu', '', $text);
        $text = preg_replace('/^\s{0,3}#{1,6}\s+/mu', '', $text);
        $text = preg_replace('/^\s{0,3}(?:[-+*]|\d+\.)\s+(?:\[[ xX]\]\s*)?/mu', '', $text);
        $text = preg_replace('/^\s{0,3}(?:[-*_]\s*){3,}$/mu', ' ', $text);
        $text = preg_replace('/~~(.*?)~~/u', '$1', $text);
        $text = preg_replace('/(\*\*|__)(.*?)\1/u', '$2', $text);
        $text = preg_replace('/(\*|_)(.*?)\1/u', '$2', $text);
        $text = preg_replace('/`([^`]+)`/u', '$1', $text);
        $text = preg_replace('/\\\([\\`*_{}\[\]()#+\-.!>~|])/u', '$1', $text);
        $text = str_replace('|', ' ', $text);
        $text = strip_tags($text);
        $text = preg_replace('/\s*\n+\s*/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }
}

if (!function_exists('qiwiExcerptText')) {
    function qiwiExcerptText($text, $length = 72)
    {
        $text = qiwiExtractPlainText($text);
        if ($text === '') {
            return '';
        }

        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $length, 'UTF-8')) . '…';
    }
}

if (!function_exists('qiwiCountReadableWords')) {
    function qiwiCountReadableWords($text)
    {
        $text = function_exists('qiwiExtractPlainText')
            ? qiwiExtractPlainText($text)
            : trim(strip_tags(html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($text === '') {
            return 0;
        }

        $count = 0;
        $cjkPattern = '/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u';
        if (preg_match_all($cjkPattern, $text, $matches)) {
            $count += count($matches[0]);
        }

        $latinText = preg_replace($cjkPattern, ' ', $text);
        if (preg_match_all('/[\p{L}\p{N}]+(?:[\'’.-][\p{L}\p{N}]+)*/u', $latinText, $matches)) {
            $count += count($matches[0]);
        }

        return $count;
    }
}

if (!function_exists('qiwiEstimateReadingMinutes')) {
    function qiwiEstimateReadingMinutes($wordCount)
    {
        $wordCount = max(0, (int) $wordCount);
        $speed = 300 + ($wordCount > 1000 ? 100 : 0) + ($wordCount > 2000 ? 100 : 0) + ($wordCount > 3000 ? 100 : 0);

        return max(1, (int) round($wordCount / $speed));
    }
}

if (!function_exists('qiwiFallbackJikeExcerpt')) {
    function qiwiFallbackJikeExcerpt($text)
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $hasImage = preg_match('/!\[[^\]]*\]\(([^)]+)\)|<img\b[^>]*>/iu', $text);
        $hasCode = preg_match('/```[\s\S]*?```|~~~[\s\S]*?~~~|`[^`\r\n]+`/u', $text);

        if ($hasImage && $hasCode) {
            return '[图片 / 代码片段] 点击查看详情';
        }

        if ($hasImage) {
            return '[图片] 点击查看详情';
        }

        if ($hasCode) {
            return '[代码片段] 点击查看详情';
        }

        return trim($text) !== '' ? '[动态] 点击查看详情' : '';
    }
}

if (!function_exists('qiwiStripReadableShortcodes')) {
    function qiwiStripReadableShortcodes($text)
    {
        $text = (string) $text;
        if ($text === '') {
            return '';
        }

        $colors = 'red|orange|yellow|green|cyan|blue|purple';

        for ($i = 0; $i < 4; $i++) {
            $next = preg_replace_callback('/\[fold([^\]]*)\]([\s\S]*?)\[\/fold\]/iu', function ($matches) {
                $attrs = qiwiParseShortcodeAttrs(isset($matches[1]) ? $matches[1] : '');
                $title = isset($attrs['title']) ? trim($attrs['title']) : '';
                $body = isset($matches[2]) ? $matches[2] : '';
                return trim($title . ' ' . $body);
            }, $text);

            if ($next === $text) {
                break;
            }

            $text = $next;
        }

        for ($i = 0; $i < 4; $i++) {
            $next = preg_replace_callback('/\[gear([^\]]*)\]([\s\S]*?)\[\/gear\]/iu', function ($matches) {
                $attrs = qiwiParseShortcodeAttrs(isset($matches[1]) ? $matches[1] : '');
                $title = isset($attrs['title']) ? trim($attrs['title']) : '';
                $body = isset($matches[2]) ? $matches[2] : '';
                return trim($title . ' ' . $body);
            }, $text);

            if ($next === $text) {
                break;
            }

            $text = $next;
        }

        for ($i = 0; $i < 4; $i++) {
            $next = preg_replace_callback('/\[reward([^\]]*)\]([\s\S]*?)\[\/reward\]/iu', function ($matches) {
                $attrs = qiwiParseShortcodeAttrs(isset($matches[1]) ? $matches[1] : '');
                $name = isset($attrs['name']) ? trim($attrs['name']) : '';
                $amount = isset($attrs['amount']) ? trim($attrs['amount']) : '';
                $body = isset($matches[2]) ? $matches[2] : '';
                return trim(($name !== '' ? $name : '匿名') . ' 打赏 ' . $amount . ' 元 ' . $body);
            }, $text);

            if ($next === $text) {
                break;
            }

            $text = $next;
        }

        $text = preg_replace('/\[mark(?:\s+color=(["\']?)[a-zA-Z]+\1)?\]([\s\S]*?)\[\/mark\]/iu', '$2', $text);
        // ||涂黑|| 的原文不允许进入任何纯文本出口（摘要、og:description、字数统计等）。
        $text = preg_replace('/(?<![A-Za-z0-9_\/])\|\|(?=[^\s|])((?:[^|\n]|\|(?!\|))*?)(?<!\s)\|\|(?!\|)/iu', ' ', $text) ?? $text;
        $text = preg_replace('/(?<![A-Za-z0-9_\/])==(?=[^\s=])(?:\[\s*[a-zA-Z]+\s*\])?((?:[^=\n]|=(?!=))*?)(?<!\s)==(?!=)/iu', '$1', $text) ?? $text;
        $text = preg_replace('/\[badge(?:\s+[^\]]*)?\]([\s\S]*?)\[\/badge\]/iu', '$1', $text);
        $text = preg_replace('/\[button(?:\s+[^\]]*)?\]([\s\S]*?)\[\/button\]/iu', '$1', $text);
        $text = preg_replace('/\[buttons(?:\s+[^\]]*)?\]([\s\S]*?)\[\/buttons\]/iu', '$1', $text);
        $text = preg_replace('/\[(?:attachment|file)\b[^\]]*\](?:\s*\[\/(?:attachment|file)\])?/iu', ' 附件 ', $text);

        for ($i = 0; $i < 4; $i++) {
            $next = preg_replace_callback('/\[callout(?:\s+[^\]]*)?\]([\s\S]*?)\[\/callout\]/iu', function ($matches) {
                return isset($matches[1]) ? $matches[1] : '';
            }, $text);

            if ($next === $text) {
                break;
            }

            $text = $next;
        }

        $text = preg_replace('/\[(' . $colors . ')\]([\s\S]*?)\[\/\1\]/iu', '$2', $text);
        $text = preg_replace('/\[\/?(?:mark|fold|badge|button|buttons|callout|attachment|file|gear|reward|' . $colors . ')(?:\s+[^\]]*)?\]/iu', '', $text);

        return $text;
    }
}

if (!function_exists('qiwiSanitizeShortcodeColor')) {
    function qiwiSanitizeShortcodeColor($color)
    {
        $color = strtolower(trim((string) $color));
        $allowed = ['red', 'orange', 'yellow', 'green', 'cyan', 'blue', 'purple'];
        return in_array($color, $allowed, true) ? $color : 'yellow';
    }
}

if (!function_exists('qiwiParseCategoryDescription')) {
    function qiwiParseCategoryDescription($description)
    {
        $raw = (string) $description;
        $result = array(
            'hasMeta' => false,
            'title' => '',
            'label' => '分类',
            'color' => '',
            'icon' => '',
            'description' => trim(strip_tags($raw)),
        );

        $matches = array();
        if (!preg_match('/^\s*\[qiwi-meta\]\s*(.*?)\s*\[\/qiwi-meta\](?:[ \t]*\r\n|[ \t]*\r|[ \t]*\n)?([\s\S]*)$/isu', $raw, $matches)) {
            return $result;
        }

        $result['hasMeta'] = true;
        $metaText = isset($matches[1]) ? $matches[1] : '';
        $body = isset($matches[2]) ? $matches[2] : '';
        $allowedKeys = array('title', 'label', 'color', 'icon');
        foreach (preg_split('/\r\n|\r|\n/', $metaText) as $line) {
            if (!preg_match('/^\s*([a-z][a-z0-9_-]*)\s*=\s*(.*?)\s*$/i', $line, $lineMatches)) {
                continue;
            }

            $key = strtolower($lineMatches[1]);
            if (!in_array($key, $allowedKeys, true)) {
                continue;
            }

            $value = trim($lineMatches[2]);
            if ($key === 'title') {
                $result['title'] = trim(strip_tags($value));
            } elseif ($key === 'label') {
                $result['label'] = strtolower($value) === 'none' ? '' : trim(strip_tags($value));
            } elseif ($key === 'color') {
                $result['color'] = in_array(strtolower($value), qiwiGetTermColorNames(), true) ? strtolower($value) : '';
            } elseif ($key === 'icon' && preg_match('/^[a-z0-9-]+$/i', $value)) {
                $result['icon'] = strtolower($value);
            }
        }

        $result['description'] = trim(strip_tags($body));
        return $result;
    }
}

if (!function_exists('qiwiGetCategoryMetaBySlug')) {
    function qiwiGetCategoryMetaBySlug($slug)
    {
        static $cache = array();
        $slug = trim((string) $slug);
        if ($slug === '') {
            return array();
        }
        if (array_key_exists($slug, $cache)) {
            return $cache[$slug];
        }

        $cache[$slug] = array();
        try {
            $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
            $row = $db->fetchRow($db->select('mid', 'name', 'slug', 'description')
                ->from('table.metas')
                ->where('type = ?', 'category')
                ->where('slug = ?', $slug)
                ->limit(1));
            if (!empty($row)) {
                $cache[$slug] = $row;
            }
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }

        return $cache[$slug];
    }
}

if (!function_exists('qiwiGetTermColorNames')) {
    function qiwiGetTermColorNames()
    {
        return ['red', 'orange', 'yellow', 'green', 'cyan', 'blue', 'purple'];
    }
}

if (!function_exists('qiwiGetSequentialTermColor')) {
    function qiwiGetSequentialTermColor($index, $total)
    {
        $total = max(1, (int) $total);
        $index = max(0, (int) $index);

        if ($total === 1) {
            $colors = ['red'];
        } elseif ($total === 2) {
            $colors = ['red', 'blue'];
        } elseif ($total === 3) {
            $colors = ['red', 'green', 'blue'];
        } elseif ($total === 4) {
            $colors = ['red', 'yellow', 'green', 'blue'];
        } elseif ($total === 5) {
            $colors = ['red', 'yellow', 'green', 'blue', 'purple'];
        } elseif ($total === 6) {
            $colors = ['red', 'orange', 'yellow', 'green', 'blue', 'purple'];
        } else {
            $colors = qiwiGetTermColorNames();
        }

        return $colors[$index % count($colors)];
    }
}

if (!function_exists('qiwiGetTermValue')) {
    function qiwiGetTermValue($term, $key, $default = '')
    {
        if (is_array($term) && isset($term[$key])) {
            return $term[$key];
        }

        if (is_object($term) && isset($term->{$key})) {
            return $term->{$key};
        }

        return $default;
    }
}

if (!function_exists('qiwiGetStableTermColor')) {
    function qiwiGetStableTermColor($term)
    {
        $colors = qiwiGetTermColorNames();
        $key = (string) qiwiGetTermValue($term, 'slug', '');
        if ($key === '') {
            $key = (string) qiwiGetTermValue($term, 'name', '');
        }
        if ($key === '') {
            $key = (string) qiwiGetTermValue($term, 'mid', '');
        }

        $index = (int) (sprintf('%u', crc32($key !== '' ? $key : 'term')) % count($colors));
        return $colors[$index];
    }
}

if (!function_exists('qiwiGetCategoryColorMap')) {
    function qiwiGetCategoryColorMap()
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = [];
        $rows = [];

        try {
            $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
            $sortAsc = class_exists('Typecho_Db') ? Typecho_Db::SORT_ASC : \Typecho\Db::SORT_ASC;
            $rows = $db->fetchAll($db->select('mid', 'name', 'slug')
                ->from('table.metas')
                ->where('type = ?', 'category')
                ->where('count > ?', 0)
                ->order('table.metas.order', $sortAsc)
                ->order('table.metas.mid', $sortAsc));
        } catch (Exception $e) {
            $rows = [];
        } catch (Throwable $e) {
            $rows = [];
        }

        $total = count($rows);
        foreach ($rows as $index => $row) {
            $color = qiwiGetSequentialTermColor($index, $total);
            foreach (['mid', 'slug', 'name'] as $key) {
                if (isset($row[$key]) && (string) $row[$key] !== '') {
                    $map[$key . ':' . (string) $row[$key]] = $color;
                }
            }
        }

        return $map;
    }
}

if (!function_exists('qiwiGetCategoryTermColor')) {
    function qiwiGetCategoryTermColor($term)
    {
        $map = qiwiGetCategoryColorMap();
        foreach (['mid', 'slug', 'name'] as $key) {
            $value = (string) qiwiGetTermValue($term, $key, '');
            if ($value !== '' && isset($map[$key . ':' . $value])) {
                return $map[$key . ':' . $value];
            }
        }

        return qiwiGetStableTermColor($term);
    }
}

if (!function_exists('qiwiRenderTermLinks')) {
    function qiwiRenderTermLinks($terms, $className = '', $colorMode = 'stable', $limit = 0)
    {
        if (empty($terms) || !is_array($terms)) {
            return '';
        }

        $limit = max(0, (int) $limit);
        $links = [];
        $total = count($terms);
        foreach ($terms as $index => $term) {
            if ($limit > 0 && count($links) >= $limit) {
                break;
            }

            if ($colorMode === 'category' && function_exists('qiwiIsThreadTerm') && qiwiIsThreadTerm($term)) {
                continue;
            }

            $name = trim((string) qiwiGetTermValue($term, 'name', ''));
            $url = trim((string) qiwiGetTermValue($term, 'permalink', '#'));
            if ($name === '') {
                continue;
            }

            if ($colorMode === 'category') {
                $color = qiwiGetCategoryTermColor($term);
            } elseif ($colorMode === 'sequence') {
                $color = qiwiGetSequentialTermColor($index, $total);
            } else {
                $color = qiwiGetStableTermColor($term);
            }
            $classes = trim('qiwi-term qiwi-term-' . $color . ' ' . $className);
            $links[] = '<a href="' . htmlspecialchars($url !== '' ? $url : '#', ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a>';
        }

        return implode('', $links);
    }
}

if (!function_exists('qiwiIsThreadSlug')) {
    function qiwiIsThreadSlug($slug)
    {
        return strpos((string) $slug, 'thread-') === 0;
    }
}

if (!function_exists('qiwiIsThreadTerm')) {
    function qiwiIsThreadTerm($term)
    {
        return qiwiIsThreadSlug(qiwiGetTermValue($term, 'slug', ''));
    }
}

if (!function_exists('qiwiDefaultThreadData')) {
    function qiwiDefaultThreadData($description = '')
    {
        $summary = function_exists('qiwiThreadCleanOptionalText')
            ? qiwiThreadCleanOptionalText(strip_tags((string) $description))
            : trim(strip_tags((string) $description));
        return [
            'schema' => 'qiwi-thread',
            'version' => 1,
            'subtitle' => '',
            'summary' => $summary,
            'status' => 'ongoing',
            'startedAt' => '',
            'field' => '',
            'order' => 'asc',
            'blocks' => [],
        ];
    }
}

if (!function_exists('qiwiNormalizeThreadStatus')) {
    function qiwiNormalizeThreadStatus($status)
    {
        $status = strtolower(trim((string) $status));
        return in_array($status, ['ongoing', 'completed', 'paused'], true) ? $status : 'ongoing';
    }
}

if (!function_exists('qiwiThreadStatusLabel')) {
    function qiwiThreadStatusLabel($status)
    {
        $labels = [
            'ongoing' => '连载中',
            'completed' => '已完成',
            'paused' => '暂缓',
        ];
        $status = qiwiNormalizeThreadStatus($status);
        return isset($labels[$status]) ? $labels[$status] : $labels['ongoing'];
    }
}

if (!function_exists('qiwiThreadCleanOptionalText')) {
    function qiwiThreadCleanOptionalText($value)
    {
        $text = trim((string) $value);
        $legacyHint = 'slug 以 thread- 开头时启用。完整结构保存在 Qiwi Theme 伴生插件表，分类描述只保留短摘要。';
        if ($text !== '' && trim(str_replace($legacyHint, '', $text)) === '') {
            return '';
        }

        return $text === '0' ? '' : $text;
    }
}

if (!function_exists('qiwiNormalizeThreadBlock')) {
    function qiwiNormalizeThreadBlock($block)
    {
        if (!is_array($block)) {
            return null;
        }

        $type = isset($block['type']) ? strtolower(trim((string) $block['type'])) : 'post';
        if (!in_array($type, ['post', 'text', 'markdown'], true)) {
            $type = 'post';
        }

        return [
            'type' => $type,
            'cid' => isset($block['cid']) ? (int) $block['cid'] : 0,
            'slug' => isset($block['slug']) ? qiwiThreadCleanOptionalText($block['slug']) : '',
            'title' => isset($block['title']) ? qiwiThreadCleanOptionalText($block['title']) : '',
            'label' => isset($block['label']) ? qiwiThreadCleanOptionalText($block['label']) : '',
            'role' => isset($block['role']) ? qiwiThreadCleanOptionalText($block['role']) : '',
            'note' => isset($block['note']) ? qiwiThreadCleanOptionalText($block['note']) : '',
            'content' => isset($block['content']) ? qiwiThreadCleanOptionalText($block['content']) : '',
        ];
    }
}

if (!function_exists('qiwiParseThreadData')) {
    function qiwiParseThreadData($description)
    {
        $description = trim((string) $description);
        $data = qiwiDefaultThreadData($description);

        if ($description === '') {
            return $data;
        }

        $decoded = json_decode(html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
        if (!is_array($decoded) || (isset($decoded['schema']) && $decoded['schema'] !== 'qiwi-thread')) {
            return $data;
        }

        foreach (['subtitle', 'summary', 'startedAt', 'field', 'order'] as $key) {
            if (isset($decoded[$key]) && !is_array($decoded[$key]) && !is_object($decoded[$key])) {
                $data[$key] = qiwiThreadCleanOptionalText($decoded[$key]);
            }
        }

        $data['schema'] = 'qiwi-thread';
        $data['version'] = isset($decoded['version']) ? max(1, (int) $decoded['version']) : 1;
        $data['status'] = qiwiNormalizeThreadStatus(isset($decoded['status']) ? $decoded['status'] : '');
        $data['order'] = in_array($data['order'], ['asc', 'desc'], true) ? $data['order'] : 'asc';

        if (isset($decoded['blocks']) && is_array($decoded['blocks'])) {
            $data['blocks'] = array_values(array_filter(array_map('qiwiNormalizeThreadBlock', $decoded['blocks'])));
        }

        return $data;
    }
}

if (!function_exists('qiwiGetStoredThreadData')) {
    function qiwiGetStoredThreadData($mid)
    {
        $mid = (int) $mid;
        if ($mid <= 0) {
            return '';
        }

        if (class_exists('QiwiTheme_Plugin') && method_exists('QiwiTheme_Plugin', 'getThreadData')) {
            return QiwiTheme_Plugin::getThreadData($mid);
        }

        if (class_exists('QiwiThreadTools_Plugin') && method_exists('QiwiThreadTools_Plugin', 'getThreadData')) {
            return QiwiThreadTools_Plugin::getThreadData($mid);
        }

        try {
            $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
            $table = $db->getPrefix() . 'qiwi_threads';
            $row = $db->fetchRow($db->select('data')->from($table)->where('mid = ?', $mid)->limit(1));
            return !empty($row['data']) ? (string) $row['data'] : '';
        } catch (Exception $e) {
            return '';
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('qiwiGetThreadData')) {
    function qiwiGetThreadData($mid, $description = '')
    {
        $stored = qiwiGetStoredThreadData($mid);
        return qiwiParseThreadData($stored !== '' ? $stored : $description);
    }
}

if (!function_exists('qiwiThreadConfiguredPostCount')) {
    function qiwiThreadConfiguredPostCount($threadData, $fallbackCount = 0)
    {
        if (!is_array($threadData) || empty($threadData['blocks']) || !is_array($threadData['blocks'])) {
            return max(0, (int) $fallbackCount);
        }

        $count = 0;
        foreach ($threadData['blocks'] as $block) {
            if (!is_array($block) || (isset($block['type']) && $block['type'] !== 'post')) {
                continue;
            }

            $cid = isset($block['cid']) ? (int) $block['cid'] : 0;
            $slug = isset($block['slug']) ? qiwiThreadCleanOptionalText($block['slug']) : '';
            if ($cid > 0 || $slug !== '') {
                $count++;
            }
        }

        return $count;
    }
}

if (!function_exists('qiwiThreadDisplayCount')) {
    function qiwiThreadDisplayCount($mid, $description = '', $fallbackCount = 0)
    {
        $data = qiwiGetThreadData($mid, $description);
        return qiwiThreadConfiguredPostCount($data, $fallbackCount);
    }
}

if (!function_exists('qiwiRenderThreadMarkdown')) {
    function qiwiRenderThreadMarkdown($text)
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        if (class_exists('\Utils\Markdown')) {
            return qiwiRenderShortcodes(\Utils\Markdown::convert($text));
        }

        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        return '<p>' . preg_replace('/\n{2,}/', '</p><p>', nl2br($escaped, false)) . '</p>';
    }
}

if (!function_exists('qiwiThreadPostPermalink')) {
    function qiwiThreadPostPermalink($row)
    {
        try {
            if (!is_array($row) || Typecho_Router::get('post') === null) {
                return '';
            }

            if (isset($row['slug'])) {
                $row['slug'] = rawurlencode($row['slug']);
            }

            $date = new Typecho_Date(isset($row['created']) ? (int) $row['created'] : 0);
            $row['date'] = $date;
            $row['year'] = $date->year;
            $row['month'] = $date->month;
            $row['day'] = $date->day;

            Typecho_Widget::widget('Widget_Options')->to($options);
            return Typecho_Common::url(Typecho_Router::url('post', $row), $options->index);
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists('qiwiThreadPostFromRow')) {
    function qiwiThreadPostFromRow($row, $permalink = '')
    {
        if (!is_array($row)) {
            return null;
        }

        $content = isset($row['text']) ? (string) $row['text'] : (isset($row['content']) ? (string) $row['content'] : '');
        if (function_exists('qiwiCountReadableWords')) {
            $wordCount = qiwiCountReadableWords($content);
        } else {
            $plain = trim(strip_tags($content));
            $wordCount = function_exists('mb_strlen') ? mb_strlen($plain, 'UTF-8') : strlen($plain);
        }
        $readingTime = function_exists('qiwiEstimateReadingMinutes')
            ? qiwiEstimateReadingMinutes($wordCount)
            : max(1, (int) round($wordCount / 300));
        $excerpt = qiwiThreadCleanOptionalText(function_exists('qiwiExcerptText') ? qiwiExcerptText($content, 128) : '');

        return [
            'cid' => isset($row['cid']) ? (int) $row['cid'] : 0,
            'slug' => isset($row['slug']) ? (string) $row['slug'] : '',
            'title' => isset($row['title']) ? (string) $row['title'] : '',
            'permalink' => $permalink !== '' ? $permalink : qiwiThreadPostPermalink($row),
            'created' => isset($row['created']) ? (int) $row['created'] : 0,
            'modified' => !empty($row['modified']) ? (int) $row['modified'] : (isset($row['created']) ? (int) $row['created'] : 0),
            'excerpt' => $excerpt,
            'readingTime' => $readingTime,
            'wordCount' => $wordCount,
        ];
    }
}

if (!function_exists('qiwiThreadFetchPost')) {
    function qiwiThreadFetchPost($cid = 0, $slug = '')
    {
        $cid = (int) $cid;
        $slug = trim((string) $slug);
        if ($cid <= 0 && $slug === '') {
            return null;
        }

        try {
            Typecho_Widget::widget('Widget_Options')->to($options);
            $db = Typecho_Db::get();
            $select = $db->select('cid', 'title', 'slug', 'created', 'modified', 'text')
                ->from('table.contents')
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
                ->where('(password IS NULL OR password = ?)', '')
                ->where('created < ?', $options->gmtTime)
                ->limit(1);

            if ($cid > 0) {
                $select->where('cid = ?', $cid);
            } else {
                $select->where('slug = ?', $slug);
            }

            $row = $db->fetchRow($select);
            return $row ? qiwiThreadPostFromRow($row) : null;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('qiwiGetThreadCategories')) {
    function qiwiGetThreadCategories()
    {
        $items = [];
        try {
            \Widget\Metas\Category\Rows::alloc()->to($categories);
            while ($categories->next()) {
                if (!qiwiIsThreadSlug($categories->slug)) {
                    continue;
                }

                ob_start();
                $categories->permalink();
                $permalink = trim(ob_get_clean());

                $description = (string) $categories->description;
                $threadData = qiwiGetThreadData((int) $categories->mid, $description);
                $count = qiwiThreadConfiguredPostCount($threadData, (int) $categories->count);
                if ($count <= 0 && trim((string) $threadData['summary']) === '' && trim((string) $threadData['subtitle']) === '') {
                    continue;
                }

                $items[] = [
                    'mid' => (int) $categories->mid,
                    'name' => (string) $categories->name,
                    'slug' => (string) $categories->slug,
                    'permalink' => $permalink,
                    'count' => $count,
                    'description' => $description,
                    'threadData' => $threadData,
                ];
            }
        } catch (Exception $e) {
            return [];
        } catch (Throwable $e) {
            return [];
        }

        return $items;
    }
}

if (!function_exists('qiwiParseShortcodeAttrs')) {
    function qiwiParseShortcodeAttrs($text)
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $attrs = [];

        if ($text === '') {
            return $attrs;
        }

        if (preg_match_all('/([a-zA-Z][a-zA-Z0-9_-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s\]]+))/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = strtolower($match[1]);
                $value = '';
                foreach ([2, 3, 4] as $index) {
                    if (isset($match[$index]) && $match[$index] !== '') {
                        $value = $match[$index];
                        break;
                    }
                }
                $attrs[$name] = trim($value);
            }
        }

        return $attrs;
    }
}

if (!function_exists('qiwiSanitizeShortcodeType')) {
    function qiwiSanitizeShortcodeType($type)
    {
        $type = strtolower(trim((string) $type));
        $allowed = ['note', 'info', 'success', 'warning', 'danger', 'quote'];
        return in_array($type, $allowed, true) ? $type : 'note';
    }
}

if (!function_exists('qiwiShortcodeTypeToColor')) {
    function qiwiShortcodeTypeToColor($type)
    {
        $map = [
            'note' => 'purple',
            'info' => 'cyan',
            'success' => 'green',
            'warning' => 'yellow',
            'danger' => 'red',
            'quote' => 'blue',
        ];

        return isset($map[$type]) ? $map[$type] : 'purple';
    }
}

if (!function_exists('qiwiSanitizeShortcodeVariant')) {
    function qiwiSanitizeShortcodeVariant($variant)
    {
        $variant = strtolower(trim((string) $variant));
        $allowed = ['soft', 'outline', 'solid', 'ghost'];
        return in_array($variant, $allowed, true) ? $variant : 'soft';
    }
}

if (!function_exists('qiwiSanitizeShortcodeTarget')) {
    function qiwiSanitizeShortcodeTarget($target)
    {
        $target = strtolower(trim((string) $target));
        return $target === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : '';
    }
}

if (!function_exists('qiwiShortcodeBoolAttr')) {
    function qiwiShortcodeBoolAttr($attrs, $name, $default = false)
    {
        if (!isset($attrs[$name])) {
            return $default;
        }

        $value = strtolower(trim((string) $attrs[$name]));
        if (in_array($value, ['1', 'true', 'yes', 'on', 'open'], true)) {
            return true;
        }

        if (in_array($value, ['0', 'false', 'no', 'off', 'closed'], true)) {
            return false;
        }

        return $default;
    }
}

if (!function_exists('qiwiSanitizeShortcodeUrl')) {
    function qiwiSanitizeShortcodeUrl($url)
    {
        $url = trim(html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') {
            return '#';
        }

        if (preg_match('/^(https?:\/\/|mailto:|tel:|\/|#)/i', $url)) {
            return $url;
        }

        return '#';
    }
}

if (!function_exists('qiwiAttachmentFileType')) {
    function qiwiAttachmentFileType($extension)
    {
        $extension = strtolower(trim((string) $extension));
        $groups = [
            'pdf' => ['pdf'],
            'word' => ['doc', 'docx', 'odt', 'rtf'],
            'excel' => ['xls', 'xlsx', 'ods', 'csv'],
            'powerpoint' => ['ppt', 'pptx', 'odp'],
            'text' => ['txt', 'md', 'log'],
            'archive' => ['zip', 'rar', '7z', 'tar', 'gz'],
            'code' => ['json', 'xml', 'yaml', 'yml', 'html', 'css', 'js', 'php'],
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'],
            'audio' => ['mp3', 'wav', 'flac', 'aac', 'ogg', 'm4a'],
            'video' => ['mp4', 'webm', 'mov', 'mkv', 'avi'],
        ];

        foreach ($groups as $type => $extensions) {
            if (in_array($extension, $extensions, true)) {
                return $type;
            }
        }

        return 'file';
    }
}

if (!function_exists('qiwiAttachmentIconClass')) {
    function qiwiAttachmentIconClass($type)
    {
        $icons = [
            'pdf' => 'fa-solid fa-file-pdf',
            'word' => 'fa-solid fa-file-word',
            'excel' => 'fa-solid fa-file-excel',
            'powerpoint' => 'fa-solid fa-file-powerpoint',
            'text' => 'fa-solid fa-file-lines',
            'archive' => 'fa-solid fa-file-zipper',
            'code' => 'fa-solid fa-file-code',
            'image' => 'fa-solid fa-file-image',
            'audio' => 'fa-solid fa-file-audio',
            'video' => 'fa-solid fa-file-video',
            'file' => 'fa-solid fa-file',
        ];

        return isset($icons[$type]) ? $icons[$type] : $icons['file'];
    }
}

if (!function_exists('qiwiFormatAttachmentSize')) {
    function qiwiFormatAttachmentSize($bytes)
    {
        $bytes = max(0, (int) $bytes);
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        foreach ($units as $index => $unit) {
            if ($value < 1024 || $index === count($units) - 1) {
                $precision = $value >= 100 ? 0 : ($value >= 10 ? 1 : 2);
                return rtrim(rtrim(number_format($value, $precision, '.', ''), '0'), '.') . ' ' . $unit;
            }
            $value /= 1024;
        }

        return $bytes . ' B';
    }
}

if (!function_exists('qiwiGetAttachmentRecord')) {
    function qiwiGetAttachmentRecord($attachmentId, $contentId)
    {
        $attachmentId = (int) $attachmentId;
        $contentId = (int) $contentId;
        if ($attachmentId <= 0 || $contentId <= 0) {
            return null;
        }

        try {
            $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
            $row = $db->fetchRow($db->select('cid', 'title', 'text', 'parent', 'status')
                ->from('table.contents')
                ->where('cid = ?', $attachmentId)
                ->where('type = ?', 'attachment')
                ->where('parent = ?', $contentId)
                ->where('status = ?', 'publish')
                ->limit(1));
            if (empty($row)) {
                return null;
            }

            $attachment = json_decode(isset($row['text']) ? (string) $row['text'] : '', true);
            if (!is_array($attachment) || empty($attachment['path'])) {
                return null;
            }

            return [
                'cid' => $attachmentId,
                'parent' => $contentId,
                'name' => isset($attachment['name']) && trim((string) $attachment['name']) !== ''
                    ? trim((string) $attachment['name'])
                    : trim((string) (isset($row['title']) ? $row['title'] : '附件')),
                'path' => (string) $attachment['path'],
                'type' => strtolower(trim((string) (isset($attachment['type']) ? $attachment['type'] : pathinfo((string) $attachment['path'], PATHINFO_EXTENSION)))),
                'mime' => trim((string) (isset($attachment['mime']) ? $attachment['mime'] : 'application/octet-stream')),
                'size' => max(0, (int) (isset($attachment['size']) ? $attachment['size'] : 0)),
            ];
        } catch (Exception $e) {
            return null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('qiwiAttachmentDownloadEndpoint')) {
    function qiwiAttachmentDownloadEndpoint($options = null)
    {
        return qiwiGetThemeActionEndpoint('attachment-download', $options);
    }
}

if (!function_exists('qiwiSanitizeAttachmentName')) {
    function qiwiSanitizeAttachmentName($name)
    {
        if (is_array($name) || is_object($name)) {
            return '';
        }

        $name = html_entity_decode(strip_tags((string) $name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name);
        $name = str_replace(['/', '\\'], '-', $name);
        $name = preg_replace('/\s+/u', ' ', $name);
        return trim((string) $name, " .\t\n\r\0\x0B");
    }
}

if (!function_exists('qiwiAttachmentDownloadName')) {
    function qiwiAttachmentDownloadName($requested, $fallback, $extension)
    {
        $name = qiwiSanitizeAttachmentName($requested);
        if ($name === '') {
            $name = qiwiSanitizeAttachmentName($fallback);
        }
        if ($name === '') {
            $name = 'attachment';
        }

        $extension = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', (string) $extension));
        if ($extension === '') {
            return $name;
        }

        $currentExtension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if ($currentExtension === $extension) {
            return $name;
        }

        if ($currentExtension !== '') {
            $stem = substr($name, 0, -strlen($currentExtension) - 1);
            $name = qiwiSanitizeAttachmentName($stem);
        }

        return ($name !== '' ? $name : 'attachment') . '.' . $extension;
    }
}

if (!function_exists('qiwiAttachmentCaptchaState')) {
    function qiwiAttachmentCaptchaState($options = null)
    {
        try {
            if ($options === null) {
                $options = Typecho_Widget::widget('Widget_Options');
            }

            if (!isset($options->enabledCaptcha) || (string) $options->enabledCaptcha !== '1') {
                return ['mode' => 'disabled', 'message' => ''];
            }

            $activated = isset($options->plugins['activated']) && is_array($options->plugins['activated'])
                ? $options->plugins['activated']
                : [];
            if (!empty($activated['QiwiCap'])) {
                $available = class_exists('QiwiCap_Plugin')
                    && method_exists('QiwiCap_Plugin', 'canRenderAttachmentCaptcha')
                    && method_exists('QiwiCap_Plugin', 'attachmentCaptchaRender')
                    && method_exists('QiwiCap_Plugin', 'verifyCaptcha')
                    && QiwiCap_Plugin::canRenderAttachmentCaptcha();

                return $available
                    ? ['mode' => 'required', 'message' => '']
                    : ['mode' => 'error', 'message' => 'Qiwi CAP 未完成配置，附件下载暂时不可用。'];
            }

            if (!empty($activated['Geetest'])) {
                return ['mode' => 'disabled', 'message' => ''];
            }

            return ['mode' => 'error', 'message' => '已启用附件验证码，但没有可用的 Qiwi CAP 服务。'];
        } catch (Exception $e) {
            return ['mode' => 'error', 'message' => '附件验证码状态读取失败，下载暂时不可用。'];
        } catch (Throwable $e) {
            return ['mode' => 'error', 'message' => '附件验证码状态读取失败，下载暂时不可用。'];
        }
    }
}

if (!function_exists('qiwiAttachmentDownloadControllerHtml')) {
    function qiwiAttachmentDownloadControllerHtml($endpoint, array $captchaState)
    {
        static $rendered = false;
        if ($rendered) {
            return '';
        }
        if ($endpoint === '') {
            return false;
        }

        $captchaRequired = isset($captchaState['mode']) && $captchaState['mode'] === 'required';
        $captchaHtml = '';
        if ($captchaRequired) {
            ob_start();
            $captchaRendered = QiwiCap_Plugin::attachmentCaptchaRender();
            $captchaHtml = ob_get_clean();
            if (!$captchaRendered || trim($captchaHtml) === '') {
                return false;
            }
        }

        $rendered = true;
        return '<form class="qiwi-attachment-download-form" data-attachment-download-form data-attachment-captcha-required="' . ($captchaRequired ? '1' : '0') . '" method="post" action="' . htmlspecialchars($endpoint, ENT_QUOTES, 'UTF-8') . '">'
            . '<input type="hidden" name="attachment_id" value=""><input type="hidden" name="content_id" value=""><input type="hidden" name="download_name" value="">'
            . ($captchaRequired ? '<div class="captcha-script qiwi-attachment-captcha">' . $captchaHtml . '</div>' : '')
            . '<p class="qiwi-attachment-download-status" data-attachment-download-status role="status" aria-live="polite">' . ($captchaRequired ? '请在这里完成人机验证。' : '') . '</p>'
            . '</form>';
    }
}

if (!function_exists('qiwiAttachmentShortcodeHtml')) {
    function qiwiAttachmentShortcodeHtml(array $attrs, array $context = [])
    {
        $attachmentId = isset($attrs['id']) ? (int) $attrs['id'] : 0;
        $contentId = isset($context['content_cid']) ? (int) $context['content_cid'] : 0;
        $record = qiwiGetAttachmentRecord($attachmentId, $contentId);
        if (empty($record)) {
            return '<span class="qiwi-attachment-error">附件不可用或不属于当前内容。</span>';
        }

        $endpoint = qiwiAttachmentDownloadEndpoint();
        $captchaState = qiwiAttachmentCaptchaState();
        if ($endpoint === '') {
            $captchaState = ['mode' => 'error', 'message' => 'Qiwi Theme 下载接口不可用。'];
        }
        $description = isset($attrs['description']) ? trim(strip_tags((string) $attrs['description'])) : '';
        if ($description === '' && isset($attrs['desc'])) {
            $description = trim(strip_tags((string) $attrs['desc']));
        }
        $displayName = isset($attrs['name']) ? qiwiSanitizeAttachmentName($attrs['name']) : '';
        if ($displayName === '') {
            $displayName = $record['name'];
        }
        $downloadName = qiwiAttachmentDownloadName(
            isset($attrs['download']) ? $attrs['download'] : '',
            $record['name'],
            $record['type']
        );
        $type = qiwiAttachmentFileType($record['type']);
        $extension = $record['type'] !== '' ? strtoupper($record['type']) : 'FILE';
        $meta = $extension . ' · ' . qiwiFormatAttachmentSize($record['size']);
        if ($description !== '') {
            $meta .= ' · ' . $description;
        }

        $controller = '';
        if ($captchaState['mode'] !== 'error') {
            $controller = qiwiAttachmentDownloadControllerHtml($endpoint, $captchaState);
            if ($controller === false) {
                $captchaState = ['mode' => 'error', 'message' => '附件验证组件输出失败，下载暂时不可用。'];
                $controller = '';
            }
        }

        $downloadAvailable = $captchaState['mode'] !== 'error';
        $captchaRequired = $captchaState['mode'] === 'required';
        $disabled = $downloadAvailable ? '' : ' disabled aria-disabled="true"';
        $buttonLabel = $downloadAvailable ? '下载' : '下载不可用';
        $buttonTooltip = $downloadAvailable
            ? ($captchaRequired ? '验证并下载：' : '下载：') . $downloadName
            : (isset($captchaState['message']) ? $captchaState['message'] : '附件下载暂时不可用');

        return '<div class="qiwi-attachment-wrap qiwi-attachment-type-' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '" data-attachment-root>'
            . '<div class="qiwi-attachment-card" data-attachment-card>'
            . '<span class="qiwi-attachment-icon" aria-hidden="true"><i class="' . htmlspecialchars(qiwiAttachmentIconClass($type), ENT_QUOTES, 'UTF-8') . '"></i><em>' . htmlspecialchars($extension, ENT_QUOTES, 'UTF-8') . '</em></span>'
            . '<span class="qiwi-attachment-info"><strong class="qiwi-attachment-tooltip" data-attachment-tooltip="' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . '" tabindex="0"><span>' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . '</span></strong><small class="qiwi-attachment-tooltip" data-attachment-tooltip="' . htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') . '" tabindex="0"><span>' . htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') . '</span></small></span>'
            . '<button type="button" class="qiwi-attachment-button qiwi-attachment-tooltip" data-attachment-tooltip="' . htmlspecialchars($buttonTooltip, ENT_QUOTES, 'UTF-8') . '" data-attachment-download data-attachment-id="' . (int) $record['cid'] . '" data-content-id="' . (int) $contentId . '" data-attachment-name="' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . '" data-attachment-download-name="' . htmlspecialchars($downloadName, ENT_QUOTES, 'UTF-8') . '"' . $disabled . '><i class="fa-solid fa-arrow-down qiwi-attachment-button-icon" aria-hidden="true"></i><span data-attachment-button-label>' . $buttonLabel . '</span><span class="qiwi-attachment-spinner" aria-hidden="true"></span></button>'
            . '</div>' . $controller . '</div>';
    }
}

if (!function_exists('qiwiRedactMarkerWidth')) {
    function qiwiRedactMarkerWidth($text)
    {
        $raw = (string) $text;
        $media = preg_match_all('/<(?:img|video|audio|picture|iframe|embed|object|svg|canvas)\b/i', $raw, $mediaMatches);
        $media = is_int($media) ? $media : 0;

        // 宽度按“实际可见文字”估算：剥掉标记与强调符号、解码 HTML 实体后再计数。
        $plain = html_entity_decode(strip_tags($raw), ENT_QUOTES, 'UTF-8');
        $plain = str_replace(array('==', '||', '~~', '**', '__', '```', '`'), '', $plain);
        $plain = trim(preg_replace('/\s+/u', ' ', $plain));
        $width = $media * 8;
        if ($plain !== '') {
            $total = preg_match_all('/./us', $plain, $totalMatches);
            $wide = preg_match_all('/[^\x00-\x7F]/u', $plain, $wideMatches);
            $total = is_int($total) ? $total : 0;
            $wide = is_int($wide) ? $wide : 0;
            $narrow = max(0, $total - $wide);
            $width += $wide + $narrow * 0.55;
        }

        return max(2, min(12, (int) round($width)));
    }
}

if (!function_exists('qiwiRenderInlineMarkers')) {
    // ==文字== / ==[color]文字== 糖果色低光高亮；||文字|| 在服务端剥离原文，只保留等宽占位色块。
    // (?<![A-Za-z0-9_/]) 与 (?<!\s) 边界：避免误吃 URL/base64 中的 ==、Markdown 表格竖线与宽松空格写法。
    // 正文里的 < 必须通过块级标签负向断言：HyperDown 输出的相邻块之间没有换行，一旦放行跨块，
    // <p>== 标题 ==</p> 分隔符（如 about 页 tab）会与后续标记误配对，把整段 HTML 吞进高亮/涂黑。
    // PCRE 回溯超限时 preg_replace_callback 返回 null，用 ?? 兜底保留原文，绝不丢内容。
    function qiwiRenderInlineMarkers($html)
    {
        $html = (string) $html;
        if ($html === '' || (strpos($html, '==') === false && strpos($html, '||') === false)) {
            return $html;
        }

        $blockTags = 'p|div|h[1-6]|ul|ol|li|dl|dt|dd|table|thead|tbody|tfoot|tr|td|th|blockquote|pre|figure|figcaption|section|article|aside|main|header|footer|nav|hr|form|fieldset|details|summary|img|video|audio|picture|iframe|embed|object|svg|canvas';
        $redactPattern = '/(?<![A-Za-z0-9_\/])\|\|(?=[^\s|])((?:[^|\n<]|\|(?!\|)|<(?!\/?(?:' . $blockTags . ')\b))*?)(?<!\s)\|\|(?!\|)/iu';
        $highlightPattern = '/(?<![A-Za-z0-9_\/])==(?=[^\s=])(?:\[\s*([a-zA-Z]+)\s*\])?((?:[^=\n<]|=(?!=)|<(?!\/?(?:' . $blockTags . ')\b))*?)(?<!\s)==(?!=)/iu';

        $html = preg_replace_callback($redactPattern, function ($matches) {
            $width = qiwiRedactMarkerWidth(isset($matches[1]) ? $matches[1] : '');
            return '<span class="qiwi-redact" style="--qiwi-redact-len:' . $width . '" role="img" aria-label="已隐藏内容"></span>';
        }, $html) ?? $html;

        $allowed = array('red', 'orange', 'yellow', 'green', 'cyan', 'blue', 'purple');
        $html = preg_replace_callback($highlightPattern, function ($matches) use ($allowed) {
            $inner = isset($matches[2]) ? $matches[2] : '';
            $word = isset($matches[1]) && $matches[1] !== '' ? strtolower(trim($matches[1])) : '';
            if ($word !== '' && !in_array($word, $allowed, true)) {
                $inner = '[' . $word . ']' . $inner;
                $word = '';
            }
            $class = 'qiwi-hl' . ($word !== '' ? ' qiwi-hl-' . $word : '');
            return '<mark class="' . $class . '">' . $inner . '</mark>';
        }, $html) ?? $html;

        return $html;
    }
}

if (!function_exists('qiwiRenderShortcodeSegment')) {
    function qiwiRenderShortcodeSegment($html, array $context = [])
    {
        $colors = 'red|orange|yellow|green|cyan|blue|purple';
        $foldOpening = '\[fold(?:\s+[^\]]*)?\]';
        $calloutOpening = '\[callout(?:\s+[^\]]*)?\]';
        $buttonsOpening = '\[buttons(?:\s+[^\]]*)?\]';
        $attachmentShortcode = '\[(?:attachment|file)\b[^\]]*\](?:\s*\[\/(?:attachment|file)\])?';
        $isCopyrightContext = !empty($context['copyright_context']);
        $copyrightOpening = '\[(?:default|thread|collection|no-repost|no-reprint|no-redistribute|ai-generated|ai-assisted)(?:\s+[^\]]*)?\]';

        $html = preg_replace('/<p>\s*(' . $foldOpening . ')\s*<br\s*\/?>\s*([\s\S]*?)<\/p>/iu', '$1<p>$2</p>', $html);
        $html = preg_replace('/<p>\s*(' . $foldOpening . ')\s*<\/p>/iu', '$1', $html);
        $html = preg_replace('/<p>([\s\S]*?)<br\s*\/?>\s*(\[\/fold\])\s*<\/p>/iu', '<p>$1</p>$2', $html);
        $html = preg_replace('/<p>\s*(\[\/fold\])\s*<\/p>/iu', '$1', $html);
        $gearOpening = '\[gear(?:\s+[^\]]*)?\]';
        $html = preg_replace('/<p>\s*(' . $gearOpening . ')\s*<br\s*\/?>\s*([\s\S]*?)<\/p>/iu', '$1<p>$2</p>', $html);
        $html = preg_replace('/<p>\s*(' . $gearOpening . ')\s*<\/p>/iu', '$1', $html);
        $html = preg_replace('/<p>([\s\S]*?)<br\s*\/?>\s*(\[\/gear\])\s*<\/p>/iu', '<p>$1</p>$2', $html);
        $html = preg_replace('/<p>\s*(\[\/gear\])\s*<\/p>/iu', '$1', $html);
        $html = preg_replace('/<p>\s*(' . $calloutOpening . ')\s*<br\s*\/?>\s*([\s\S]*?)<\/p>/iu', '$1<p>$2</p>', $html);
        $html = preg_replace('/<p>\s*(' . $calloutOpening . ')\s*<\/p>/iu', '$1', $html);
        $html = preg_replace('/<p>([\s\S]*?)<br\s*\/?>\s*(\[\/callout\])\s*<\/p>/iu', '<p>$1</p>$2', $html);
        $html = preg_replace('/<p>\s*(\[\/callout\])\s*<\/p>/iu', '$1', $html);
        $html = preg_replace('/<p>\s*(' . $buttonsOpening . ')\s*<br\s*\/?>\s*([\s\S]*?)<\/p>/iu', '$1<span>$2</span>', $html);
        $html = preg_replace('/<p>\s*(' . $buttonsOpening . ')\s*<\/p>/iu', '$1', $html);
        $html = preg_replace('/<p>([\s\S]*?)<br\s*\/?>\s*(\[\/buttons\])\s*<\/p>/iu', '<span>$1</span>$2', $html);
        $html = preg_replace('/<p>\s*(\[\/buttons\])\s*<\/p>/iu', '$1', $html);
        $html = preg_replace('/<p>\s*(' . $attachmentShortcode . ')\s*<\/p>/iu', '$1', $html);
        if ($isCopyrightContext) {
            $html = preg_replace('/<p>\s*(' . $copyrightOpening . ')\s*(?:<br\s*\/?>)?\s*<\/p>/iu', '$1', $html);
            $html = preg_replace('/<p>([\s\S]*?)<br\s*\/?>\s*(\[\/(?:default|thread|collection|no-repost|no-reprint|no-redistribute|ai-generated|ai-assisted)\])\s*<\/p>/iu', '<p>$1</p>$2', $html);
            $html = preg_replace('/<p>\s*(\[\/(?:default|thread|collection|no-repost|no-reprint|no-redistribute|ai-generated|ai-assisted)\])\s*<\/p>/iu', '$1', $html);
        }

        for ($i = 0; $i < 4; $i++) {
            $next = preg_replace_callback('/\[fold([^\]]*)\]([\s\S]*?)\[\/fold\]/iu', function ($matches) {
                $attrs = qiwiParseShortcodeAttrs(isset($matches[1]) ? $matches[1] : '');
                $title = isset($attrs['title']) ? trim($attrs['title']) : '';
                if ($title === '') {
                    $title = '展开内容';
                }

                $body = isset($matches[2]) ? $matches[2] : '';
                $isOpen = qiwiShortcodeBoolAttr($attrs, 'open', true);
                if (isset($attrs['default']) && strtolower(trim((string) $attrs['default'])) === 'closed') {
                    $isOpen = false;
                }
                if (qiwiShortcodeBoolAttr($attrs, 'closed', false)) {
                    $isOpen = false;
                }

                $variant = isset($attrs['variant']) ? strtolower(trim((string) $attrs['variant'])) : '';
                if ($variant === '' && isset($attrs['style'])) {
                    $variant = strtolower(trim((string) $attrs['style']));
                }
                $noDivider = in_array($variant, ['plain', 'clean', 'no-divider', 'nodivider'], true)
                    || qiwiShortcodeBoolAttr($attrs, 'divider', true) === false;
                $class = 'qiwi-fold' . ($noDivider ? ' qiwi-fold-no-divider' : '');
                $openAttr = $isOpen ? ' open' : '';

                return '<details class="' . $class . '"' . $openAttr . '><summary>' . htmlspecialchars(strip_tags($title), ENT_QUOTES, 'UTF-8') . '</summary><div class="qiwi-fold-body">' . $body . '</div></details>';
            }, $html);

            if ($next === $html) {
                break;
            }

            $html = $next;
        }

        $html = preg_replace('/<p>\s*(<details class="qiwi-fold(?:\s+[^"]*)?"[\s\S]*?<\/details>)\s*<\/p>/iu', '$1', $html);

        for ($i = 0; $i < 4; $i++) {
            $next = preg_replace_callback('/\[gear([^\]]*)\]([\s\S]*?)\[\/gear\]/iu', function ($matches) {
                $attrs = qiwiParseShortcodeAttrs(isset($matches[1]) ? $matches[1] : '');
                $title = isset($attrs['title']) ? trim($attrs['title']) : '';
                $image = isset($attrs['image']) ? trim($attrs['image']) : '';
                if (isset($attrs['img'])) { $image = trim($attrs['img']); }
                if (isset($attrs['src'])) { $image = trim($attrs['src']); }
                $body = isset($matches[2]) ? trim($matches[2]) : '';
                $body = preg_replace('/^(?:\s*<br\s*\/?>\s*)+/iu', '', $body);
                $body = preg_replace('/(?:\s*<br\s*\/?>\s*)+$/iu', '', $body);
                $body = preg_replace('/^(?:\s*<p>\s*<br\s*\/?>\s*<\/p>\s*)+/iu', '', $body);
                $body = preg_replace('/(?:\s*<p>\s*<br\s*\/?>\s*<\/p>\s*)+$/iu', '', $body);

                if ($title === '') {
                    return htmlspecialchars($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                if ($image === '') {
                    return '<div class="qiwi-gear-card"><div class="qiwi-gear-body"><h3 class="qiwi-gear-title">'
                        . htmlspecialchars(strip_tags($title), ENT_QUOTES, 'UTF-8')
                        . '</h3><div class="qiwi-gear-content">' . $body . '</div></div></div>';
                }

                return '<div class="qiwi-gear-card">'
                    . '<div class="qiwi-gear-image"><img src="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8')
                    . '" alt="' . htmlspecialchars(strip_tags($title), ENT_QUOTES, 'UTF-8') . '" loading="lazy"></div>'
                    . '<div class="qiwi-gear-body"><h3 class="qiwi-gear-title">'
                    . htmlspecialchars(strip_tags($title), ENT_QUOTES, 'UTF-8')
                    . '</h3><div class="qiwi-gear-content">' . $body . '</div></div>'
                    . '</div>';
            }, $html);

            if ($next === $html) {
                break;
            }

            $html = $next;
        }

        $html = preg_replace('/<p>\s*(<div class="qiwi-gear-card">[\s\S]*?<\/div>\s*<\/div>\s*<\/div>)\s*<\/p>/iu', '$1', $html);
        $html = preg_replace('/(<\/div>\s*<\/div>\s*<\/div>)\s*(?:<p><br\s*\/?><\/p>|<p>\s*<\/p>|<br\s*\/?>)*\s*(<div class="qiwi-gear-card">)/iu', '$1$2', $html);
        $html = preg_replace_callback('/(?:<div class="qiwi-gear-card">[\s\S]*?<\/div>\s*<\/div>\s*<\/div>\s*)+/iu', function ($matches) {
            return '<div class="qiwi-gear-grid">' . $matches[0] . '</div>';
        }, $html);

        $html = preg_replace('/<p>\s*(\[reward(?:\s+[^\]]*)?\])\s*<br\s*\/?>\s*([\s\S]*?)<\/p>/iu', '$1<p>$2</p>', $html);
        $html = preg_replace('/<p>\s*(\[reward(?:\s+[^\]]*)?\])\s*<\/p>/iu', '$1', $html);
        $html = preg_replace('/<p>([\s\S]*?)<br\s*\/?>\s*(\[\/reward\])\s*<\/p>/iu', '<p>$1</p>$2', $html);
        $html = preg_replace('/<p>\s*(\[\/reward\])\s*<\/p>/iu', '$1', $html);

        for ($i = 0; $i < 4; $i++) {
            $next = preg_replace_callback('/\[reward([^\]]*)\]([\s\S]*?)\[\/reward\]/iu', function ($matches) {
                $attrs = qiwiParseShortcodeAttrs(isset($matches[1]) ? $matches[1] : '');
                $name = isset($attrs['name']) ? trim($attrs['name']) : '';
                $amountRaw = isset($attrs['amount']) ? trim($attrs['amount']) : '';
                $note = isset($matches[2]) ? trim($matches[2]) : '';
                $note = preg_replace('/^(?:\s*<br\s*\/?>\s*)+/iu', '', $note);
                $note = preg_replace('/(?:\s*<br\s*\/?>\s*)+$/iu', '', $note);
                $note = preg_replace('/<p>\s*<\/p>/iu', '', $note);
                $time = isset($attrs['time']) ? trim($attrs['time']) : '';
                $tag = isset($attrs['tag']) ? trim($attrs['tag']) : '';

                if ($name === '' && $amountRaw === '') {
                    return htmlspecialchars($note, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                if ($name === '') {
                    $name = '匿名';
                }
                $amount = is_numeric($amountRaw)
                    ? number_format((float) $amountRaw, 2, '.', '')
                    : htmlspecialchars($amountRaw, ENT_QUOTES, 'UTF-8');

                $html = '<article class="qiwi-reward-card">'
                    . '<div class="qiwi-reward-head">'
                    . '<span class="qiwi-reward-name">' . htmlspecialchars(strip_tags($name), ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="qiwi-reward-amount"><span class="qiwi-reward-amount-cny">¥</span> ' . $amount . '</span>'
                    . '</div>';
                if ($note !== '') {
                    $html .= '<div class="qiwi-reward-note">' . $note . '</div>';
                }
                if ($time !== '' || $tag !== '') {
                    $html .= '<div class="qiwi-reward-meta">';
                    if ($time !== '') {
                        $html .= '<time>' . htmlspecialchars($time, ENT_QUOTES, 'UTF-8') . '</time>';
                    }
                    if ($tag !== '') {
                        $html .= '<span class="qiwi-reward-tag">' . htmlspecialchars(strip_tags($tag), ENT_QUOTES, 'UTF-8') . '</span>';
                    }
                    $html .= '</div>';
                }
                $html .= '</article>';
                return $html;
            }, $html);

            if ($next === $html) {
                break;
            }

            $html = $next;
        }

        $html = preg_replace('/<p>\s*(<article class="qiwi-reward-card">[\s\S]*?<\/article>)\s*<\/p>/iu', '$1', $html);
        $html = preg_replace('/(<\/article>)\s*(?:<p><br\s*\/?><\/p>|<p>\s*<\/p>|<br\s*\/?>)*\s*(<article class="qiwi-reward-card">)/iu', '$1$2', $html);
        $rewardCount = 0;
        $rewardTotal = 0.0;
        if (preg_match_all('/<article class="qiwi-reward-card">/iu', $html, $rewardCards) !== false) {
            $rewardCount = count($rewardCards[0]);
        }
        if (preg_match_all('/<span class="qiwi-reward-amount-cny">¥<\/span>\s*([\d.,]+)/iu', $html, $rewardAmounts) !== false) {
            foreach ($rewardAmounts[1] as $rewardAmt) {
                $rewardTotal += (float) str_replace(',', '', $rewardAmt);
            }
        }
        $html = preg_replace_callback('/(?:<article class="qiwi-reward-card">[\s\S]*?<\/article>\s*)+/iu', function ($matches) {
            return '<div class="qiwi-reward-grid">' . $matches[0] . '</div>';
        }, $html);
        if ($rewardCount > 0) {
            $rewardStats = '<div class="qiwi-reward-stats"><ul class="qiwi-reward-stats-list">'
                . '<li class="qiwi-reward-stats-item">已收到 <span class="qiwi-reward-stats-highlight">' . $rewardCount . '</span> 笔打赏，累计价值 <span class="qiwi-reward-stats-highlight">¥ ' . number_format($rewardTotal, 2, '.', ',') . '</span> 元</li>'
                . '</ul></div>';
            $html = preg_replace('/(<div class="qiwi-reward-grid">)/iu', $rewardStats . '$1', $html, 1);
        }

        for ($i = 0; $i < 4; $i++) {
            $next = preg_replace_callback('/\[callout([^\]]*)\]([\s\S]*?)\[\/callout\]/iu', function ($matches) {
                $attrs = qiwiParseShortcodeAttrs(isset($matches[1]) ? $matches[1] : '');
                $type = qiwiSanitizeShortcodeType(isset($attrs['type']) ? $attrs['type'] : (isset($attrs['color']) ? $attrs['color'] : 'note'));
                $color = isset($attrs['color']) ? qiwiSanitizeShortcodeColor($attrs['color']) : qiwiShortcodeTypeToColor($type);
                $title = isset($attrs['title']) ? trim($attrs['title']) : '';
                $body = isset($matches[2]) ? trim($matches[2]) : '';
                $class = 'qiwi-callout qiwi-callout-' . $color . ' qiwi-callout-type-' . $type;
                $titleHtml = $title !== '' ? '<div class="qiwi-callout-title">' . htmlspecialchars(strip_tags($title), ENT_QUOTES, 'UTF-8') . '</div>' : '';

                return '<aside class="' . $class . '">' . $titleHtml . '<div class="qiwi-callout-body">' . $body . '</div></aside>';
            }, $html);

            if ($next === $html) {
                break;
            }

            $html = $next;
        }

        $html = preg_replace('/<p>\s*(<aside class="qiwi-callout[\s\S]*?<\/aside>)\s*<\/p>/iu', '$1', $html);

        $html = preg_replace_callback('/\[badge([^\]]*)\]([\s\S]*?)\[\/badge\]/iu', function ($matches) {
            $attrs = qiwiParseShortcodeAttrs(isset($matches[1]) ? $matches[1] : '');
            $color = qiwiSanitizeShortcodeColor(isset($attrs['color']) ? $attrs['color'] : 'cyan');
            $variant = qiwiSanitizeShortcodeVariant(isset($attrs['variant']) ? $attrs['variant'] : 'soft');
            return '<span class="qiwi-badge qiwi-badge-' . $color . ' qiwi-badge-' . $variant . '">' . $matches[2] . '</span>';
        }, $html);

        $html = preg_replace_callback('/\[button\b([^\]]*)\]([\s\S]*?)\[\/button\]/iu', function ($matches) {
            $attrs = qiwiParseShortcodeAttrs(isset($matches[1]) ? $matches[1] : '');
            $href = qiwiSanitizeShortcodeUrl(isset($attrs['href']) ? $attrs['href'] : (isset($attrs['url']) ? $attrs['url'] : '#'));
            $color = qiwiSanitizeShortcodeColor(isset($attrs['color']) ? $attrs['color'] : 'cyan');
            $variant = qiwiSanitizeShortcodeVariant(isset($attrs['variant']) ? $attrs['variant'] : (isset($attrs['style']) ? $attrs['style'] : 'outline'));
            $target = qiwiSanitizeShortcodeTarget(isset($attrs['target']) ? $attrs['target'] : '');
            $label = trim($matches[2]) !== '' ? $matches[2] : htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
            return '<a class="qiwi-button qiwi-button-' . $color . ' qiwi-button-' . $variant . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"' . $target . '>' . $label . '</a>';
        }, $html);

        $html = preg_replace_callback('/\[link\b([^\]]*)\]([\s\S]*?)\[\/link\]/iu', function ($matches) {
            $attrs = qiwiParseShortcodeAttrs(isset($matches[1]) ? $matches[1] : '');
            $href = qiwiSanitizeShortcodeUrl(isset($attrs['href']) ? $attrs['href'] : (isset($attrs['url']) ? $attrs['url'] : '#'));
            $target = qiwiSanitizeShortcodeTarget(isset($attrs['target']) ? $attrs['target'] : '');
            $label = trim($matches[2]) !== '' ? $matches[2] : htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
            return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"' . $target . '>' . $label . '</a>';
        }, $html);

        $html = preg_replace_callback('/\[(?:attachment|file)\b([^\]]*)\](?:\s*\[\/(?:attachment|file)\])?/iu', function ($matches) use ($context) {
            $attrs = qiwiParseShortcodeAttrs(isset($matches[1]) ? $matches[1] : '');
            return qiwiAttachmentShortcodeHtml($attrs, $context);
        }, $html);

        if ($isCopyrightContext) {
            $html = qiwiRenderCopyrightMagicShortcodes($html, $context);
        }

        $html = preg_replace_callback('/\[(?:not-by-ai|notbyai|noai)([^\]]*)\](?:\s*\[\/(?:not-by-ai|notbyai|noai)\])?/iu', function ($matches) {
            $attrs = qiwiParseShortcodeAttrs(isset($matches[1]) ? $matches[1] : '');
            $href = qiwiSanitizeShortcodeUrl(isset($attrs['href']) ? $attrs['href'] : (isset($attrs['url']) ? $attrs['url'] : 'https://notbyai.fyi/'));
            $label = isset($attrs['label']) && trim($attrs['label']) !== '' ? trim($attrs['label']) : '本文非 AI 生成';
            return '<a class="qiwi-not-by-ai" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer"><span>NOT</span><strong>' . htmlspecialchars(strip_tags($label), ENT_QUOTES, 'UTF-8') . '</strong></a>';
        }, $html);

        for ($i = 0; $i < 4; $i++) {
            $next = preg_replace_callback('/\[buttons([^\]]*)\]([\s\S]*?)\[\/buttons\]/iu', function ($matches) {
                $attrs = qiwiParseShortcodeAttrs(isset($matches[1]) ? $matches[1] : '');
                $align = isset($attrs['align']) ? strtolower(trim($attrs['align'])) : 'left';
                if (!in_array($align, ['left', 'center', 'right'], true)) {
                    $align = 'left';
                }
                return '<div class="qiwi-buttons qiwi-buttons-' . $align . '">' . $matches[2] . '</div>';
            }, $html);

            if ($next === $html) {
                break;
            }

            $html = $next;
        }

        $html = preg_replace('/<p>\s*(<div class="qiwi-buttons[\s\S]*?<\/div>)\s*<\/p>/iu', '$1', $html);

        $html = qiwiRenderInlineMarkers($html);

        $html = preg_replace_callback('/\[mark(?:\s+color=(["\']?)([a-zA-Z]+)\1)?\]([\s\S]*?)\[\/mark\]/iu', function ($matches) {
            $color = qiwiSanitizeShortcodeColor(isset($matches[2]) && $matches[2] !== '' ? $matches[2] : 'yellow');
            return '<span class="qiwi-mark qiwi-mark-' . $color . '">' . $matches[3] . '</span>';
        }, $html);

        $html = preg_replace_callback('/\[(' . $colors . ')\]([\s\S]*?)\[\/\1\]/iu', function ($matches) {
            $color = qiwiSanitizeShortcodeColor($matches[1]);
            return '<span class="qiwi-text-' . $color . '">' . $matches[2] . '</span>';
        }, $html);

        return $html;
    }
}

if (!function_exists('qiwiRenderShortcodes')) {
    function qiwiRenderShortcodes($html, array $context = [])
    {
        $html = (string) $html;
        if ($html === '') {
            return '';
        }

        $parts = preg_split('/(<pre\b[\s\S]*?<\/pre>|<code\b[\s\S]*?<\/code>)/iu', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $index => $part) {
            if (preg_match('/^<(pre|code)\b/iu', $part)) {
                continue;
            }

            $parts[$index] = qiwiRenderShortcodeSegment($part, $context);
        }

        return implode('', $parts);
    }
}

if (!function_exists('qiwiNormalizeRichTextValue')) {
    function qiwiNormalizeRichTextValue($value)
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
        return $value === '0' ? '' : $value;
    }
}

if (!function_exists('qiwiPostRichTextContext')) {
    function qiwiPostRichTextContext($widget)
    {
        $capture = function ($method) use ($widget) {
            if (empty($widget)) {
                return '';
            }

            try {
                ob_start();
                $result = $widget->{$method}();
                $output = trim(ob_get_clean());
                if ($output !== '') {
                    return $output;
                }

                return is_scalar($result) ? trim((string) $result) : '';
            } catch (Exception $e) {
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
            } catch (Throwable $e) {
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
            }

            return '';
        };

        $title = '';
        if (!empty($widget) && isset($widget->title)) {
            $title = (string) $widget->title;
        }

        $siteTitle = trim((string) qiwiGetOptionValue($widget, 'title', ''));
        $siteUrl = trim((string) qiwiGetOptionValue($widget, 'siteUrl', ''));

        $permalink = $capture('permalink');
        if ($permalink === '' && !empty($widget) && isset($widget->permalink)) {
            $permalink = trim((string) $widget->permalink);
        }

        return [
            'permalink' => $permalink,
            'url' => $permalink,
            'post_url' => $permalink,
            'title' => $title,
            'post_title' => $title,
            'author' => $capture('author'),
            'site' => $siteTitle,
            'site_title' => $siteTitle,
            'site_url' => $siteUrl,
            'year' => date('Y'),
        ];
    }
}

if (!function_exists('qiwiApplyRichTextPlaceholders')) {
    function qiwiApplyRichTextPlaceholders($text, array $context = [])
    {
        if (empty($context)) {
            return (string) $text;
        }

        $replacements = [];
        foreach ($context as $key => $value) {
            $value = (string) $value;
            $replacements['{' . $key . '}'] = $value;
            $replacements['{{' . $key . '}}'] = $value;
        }

        return strtr((string) $text, $replacements);
    }
}

if (!function_exists('qiwiAutolinkDisplayDomain')) {
    function qiwiAutolinkDisplayDomain($url)
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./i', '', $host);
        $parts = array_values(array_filter(explode('.', $host)));
        $count = count($parts);
        if ($count >= 3 && strlen($parts[$count - 1]) === 2 && strlen($parts[$count - 2]) <= 3) {
            return implode('.', array_slice($parts, -3));
        }
        if ($count >= 2) {
            return implode('.', array_slice($parts, -2));
        }

        return $host !== '' ? $host : preg_replace('/^https?:\/\//i', '', (string) $url);
    }
}

if (!function_exists('qiwiAutolinkPlainUrls')) {
    function qiwiAutolinkPlainUrls($html, array $context = [])
    {
        $siteHost = '';
        if (!empty($context['site_url'])) {
            $siteHost = strtolower((string) parse_url((string) $context['site_url'], PHP_URL_HOST));
        }
        if ($siteHost === '' && !empty($context['permalink'])) {
            $siteHost = strtolower((string) parse_url((string) $context['permalink'], PHP_URL_HOST));
        }
        $siteHost = preg_replace('/^www\./i', '', $siteHost);

        $parts = preg_split('/(<a\b[\s\S]*?<\/a>|<code\b[\s\S]*?<\/code>|<pre\b[\s\S]*?<\/pre>|<script\b[\s\S]*?<\/script>|<style\b[\s\S]*?<\/style>|&lt;[\s\S]*?&gt;)/iu', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $index => $part) {
            if (preg_match('/^(?:<(a|code|pre|script|style)\b|&lt;)/iu', $part)) {
                continue;
            }

            $parts[$index] = preg_replace_callback('/((?:https?:\/\/|www\.)[a-z0-9][a-z0-9.-]*(?::\d+)?(?:\/[^\s<>"\'`，。！？；：、（）【】《》「」『』\x{3000}]*)?|(?:[a-z0-9-]+\.)+[a-z]{2,}(?:\/[^\s<>"\'`，。！？；：、（）【】《》「」『』\x{3000}]*)?)/iu', function ($matches) use ($siteHost) {
                $raw = $matches[0];
                $trailing = '';
                while (preg_match('/[.,!?;:，。！？；：、）)\]]$/u', $raw)) {
                    $trailing = function_exists('mb_substr') ? mb_substr($raw, -1, 1, 'UTF-8') . $trailing : substr($raw, -1) . $trailing;
                    $raw = function_exists('mb_substr') ? mb_substr($raw, 0, mb_strlen($raw, 'UTF-8') - 1, 'UTF-8') : substr($raw, 0, -1);
                }

                if ($raw === '') {
                    return $matches[0];
                }

                $url = preg_match('/^https?:\/\//i', $raw) ? $raw : 'https://' . $raw;
                $decodedUrl = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (!preg_match('/^https?:\/\//i', $decodedUrl) || !parse_url($decodedUrl, PHP_URL_HOST)) {
                    return $matches[0];
                }

                $targetHost = preg_replace('/^www\./i', '', strtolower((string) parse_url($decodedUrl, PHP_URL_HOST)));
                $isInternal = $siteHost !== '' && $targetHost === $siteHost;
                $safeUrl = htmlspecialchars($decodedUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $safeLabel = htmlspecialchars($isInternal ? $raw : qiwiAutolinkDisplayDomain($decodedUrl), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $target = $isInternal ? '' : ' target="_blank" rel="noopener noreferrer"';
                return '<a href="' . $safeUrl . '"' . $target . '>' . $safeLabel . '</a>' . $trailing;
            }, $part);
        }

        return implode('', $parts);
    }
}

if (!function_exists('qiwiRenderFieldRichText')) {
    function qiwiRenderFieldRichText($text, array $context = [])
    {
        $text = qiwiNormalizeRichTextValue(qiwiApplyRichTextPlaceholders($text, $context));
        if ($text === '') {
            return '';
        }

        $blocks = preg_split("/\n{2,}/u", $text);
        $html = '';
        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            $escaped = htmlspecialchars($block, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $escaped = nl2br($escaped, false);
            if (preg_match('/^\[(?:callout|buttons|fold|gear|reward)(?:\s+[^\]]*)?\]/iu', $block)
                || (!empty($context['copyright_context']) && preg_match('/^\[(?:default|thread|collection|not-by-ai|notbyai|noai|no-repost|no-reprint|no-redistribute|ai-generated|ai-assisted|禁止转载|不能转载|不可转载)(?:\s+[^\]]*)?\]/iu', $block))) {
                $html .= $escaped;
            } else {
                $html .= '<p>' . $escaped . '</p>';
            }
        }

        return qiwiAutolinkPlainUrls(qiwiRenderShortcodes($html, $context), $context);
    }
}

if (!function_exists('qiwiGetPostThreadCollection')) {
    function qiwiGetPostThreadCollection($widget)
    {
        if (empty($widget) || !isset($widget->cid)) {
            return null;
        }

        $cid = (int) $widget->cid;
        if ($cid <= 0) {
            return null;
        }

        try {
            $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
            $rows = $db->fetchAll($db->select('table.metas.mid', 'table.metas.name', 'table.metas.slug', 'table.metas.description', 'table.metas.count')
                ->from('table.metas')
                ->join('table.relationships', 'table.metas.mid = table.relationships.mid')
                ->where('table.relationships.cid = ?', $cid)
                ->where('table.metas.type = ?', 'category')
                ->where('table.metas.slug LIKE ?', 'thread-%')
                ->order('table.metas.order', class_exists('Typecho_Db') ? Typecho_Db::SORT_ASC : \Typecho\Db::SORT_ASC)
                ->order('table.metas.mid', class_exists('Typecho_Db') ? Typecho_Db::SORT_ASC : \Typecho\Db::SORT_ASC)
                ->limit(1));

            if (empty($rows)) {
                return null;
            }

            $row = $rows[0];
            $permalink = qiwiGetCategoryPermalink($row, $widget);
            if ($permalink === '') {
                return null;
            }

            return [
                'mid' => isset($row['mid']) ? (int) $row['mid'] : 0,
                'name' => isset($row['name']) ? (string) $row['name'] : '',
                'slug' => isset($row['slug']) ? (string) $row['slug'] : '',
                'permalink' => $permalink,
                'description' => isset($row['description']) ? (string) $row['description'] : '',
            ];
        } catch (Exception $e) {
            return null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('qiwiGetCategoryPermalink')) {
    function qiwiGetCategoryPermalink($row, $widget = null)
    {
        if (!is_array($row) || empty($row['slug'])) {
            return '';
        }

        try {
            $options = null;
            if (!empty($widget) && isset($widget->options)) {
                $options = $widget->options;
            } elseif (class_exists('\Widget\Options')) {
                \Widget\Options::alloc()->to($options);
            } elseif (class_exists('Widget_Options')) {
                Widget_Options::alloc()->to($options);
            }

            if (class_exists('Typecho_Router') && Typecho_Router::get('category') !== null && !empty($options)) {
                $data = $row;
                $data['slug'] = rawurlencode((string) $data['slug']);
                return Typecho_Common::url(Typecho_Router::url('category', $data), $options->index);
            }

            $siteUrl = !empty($options) && isset($options->siteUrl) ? rtrim((string) $options->siteUrl, '/') : '';
            return $siteUrl !== '' ? $siteUrl . '/category/' . rawurlencode((string) $row['slug']) . '/' : '';
        } catch (Exception $e) {
            return '';
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('qiwiCopyrightLicenseDefinitions')) {
    function qiwiCopyrightLicenseDefinitions()
    {
        return [
            'cc-by-4' => [
                'label' => 'CC BY 4.0',
                'url' => 'https://creativecommons.org/licenses/by/4.0/',
                'summary' => '署名 4.0 国际'
            ],
            'cc-by-sa-4' => [
                'label' => 'CC BY-SA 4.0',
                'url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
                'summary' => '署名-相同方式共享 4.0 国际'
            ],
            'cc-by-nd-4' => [
                'label' => 'CC BY-ND 4.0',
                'url' => 'https://creativecommons.org/licenses/by-nd/4.0/',
                'summary' => '署名-禁止演绎 4.0 国际'
            ],
            'cc-by-nc-4' => [
                'label' => 'CC BY-NC 4.0',
                'url' => 'https://creativecommons.org/licenses/by-nc/4.0/',
                'summary' => '署名-非商业性使用 4.0 国际'
            ],
            'cc-by-nc-sa-4' => [
                'label' => 'CC BY-NC-SA 4.0',
                'url' => 'https://creativecommons.org/licenses/by-nc-sa/4.0/',
                'summary' => '署名-非商业性使用-相同方式共享 4.0 国际'
            ],
            'cc-by-nc-nd-4' => [
                'label' => 'CC BY-NC-ND 4.0',
                'url' => 'https://creativecommons.org/licenses/by-nc-nd/4.0/',
                'summary' => '署名-非商业性使用-禁止演绎 4.0 国际'
            ],
            'cc0-1' => [
                'label' => 'CC0 1.0',
                'url' => 'https://creativecommons.org/publicdomain/zero/1.0/',
                'summary' => '公共领域贡献'
            ],
            'all-rights-reserved' => [
                'label' => '保留所有权利',
                'url' => '',
                'summary' => '未经许可不得转载、改编或再发布'
            ]
        ];
    }
}

if (!function_exists('qiwiNormalizeCopyrightLicense')) {
    function qiwiNormalizeCopyrightLicense($value)
    {
        $value = strtolower(trim((string) $value));
        $value = str_replace(['_', '.', ' '], '-', $value);
        $aliases = [
            'cc-by' => 'cc-by-4',
            'cc-by-4-0' => 'cc-by-4',
            'cc-by-sa' => 'cc-by-sa-4',
            'cc-by-sa-4-0' => 'cc-by-sa-4',
            'cc-by-nd' => 'cc-by-nd-4',
            'cc-by-nd-4-0' => 'cc-by-nd-4',
            'cc-by-nc' => 'cc-by-nc-4',
            'cc-by-nc-4-0' => 'cc-by-nc-4',
            'cc-by-nc-sa' => 'cc-by-nc-sa-4',
            'cc-by-nc-sa-4-0' => 'cc-by-nc-sa-4',
            'cc-by-nc-nd' => 'cc-by-nc-nd-4',
            'cc-by-nc-nd-4-0' => 'cc-by-nc-nd-4',
            'cc0' => 'cc0-1',
            'cc0-1-0' => 'cc0-1',
            'reserved' => 'all-rights-reserved',
            'all-rights' => 'all-rights-reserved'
        ];
        if (isset($aliases[$value])) {
            $value = $aliases[$value];
        }

        $definitions = qiwiCopyrightLicenseDefinitions();
        return isset($definitions[$value]) ? $value : 'cc-by-nc-nd-4';
    }
}

if (!function_exists('qiwiCopyrightLicenseComponentHtml')) {
    function qiwiCopyrightLicenseComponentHtml(array $context)
    {
        $licenseKey = qiwiNormalizeCopyrightLicense(isset($context['copyright_license']) ? $context['copyright_license'] : '');
        $license = qiwiCopyrightLicenseDefinitions()[$licenseKey];
        $label = htmlspecialchars($license['label'], ENT_QUOTES, 'UTF-8');
        $summary = htmlspecialchars($license['summary'], ENT_QUOTES, 'UTF-8');
        $content = '<span>许可协议</span><strong>' . $label . '</strong><em>' . $summary . '</em>';

        if ($license['url'] === '') {
            return '<span class="post-copyright-license post-copyright-license-static">' . $content . '</span>';
        }

        return '<a class="post-copyright-license" href="' . htmlspecialchars($license['url'], ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . $content . '</a>';
    }
}

if (!function_exists('qiwiCopyrightDefaultTemplateHtml')) {
    function qiwiCopyrightDefaultTemplateHtml(array $context)
    {
        $permalink = isset($context['permalink']) ? (string) $context['permalink'] : '';
        $author = isset($context['author']) && $context['author'] !== '' ? (string) $context['author'] : '作者';
        $siteTitle = isset($context['site_title']) && $context['site_title'] !== '' ? (string) $context['site_title'] : '本站';
        $permalinkEscaped = htmlspecialchars($permalink, ENT_QUOTES, 'UTF-8');

        return '<p><span class="qiwi-badge qiwi-badge-cyan qiwi-badge-soft">原创</span> 本文由 ' . htmlspecialchars($author, ENT_QUOTES, 'UTF-8') . ' 发布于 <a href="' . $permalinkEscaped . '">' . htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') . '</a>。</p>'
            . '<p>原文链接：<a href="' . $permalinkEscaped . '">' . $permalinkEscaped . '</a></p>'
            . '<div class="post-copyright-license-row">' . qiwiCopyrightLicenseComponentHtml($context) . '</div>';
    }
}

if (!function_exists('qiwiCopyrightHasMagicTags')) {
    function qiwiCopyrightHasMagicTags($text)
    {
        return preg_match('/\[(?:default|thread|collection|not-by-ai|notbyai|noai|no-repost|no-reprint|no-redistribute|ai-generated|ai-assisted)(?:\s+[^\]]*)?\]|\[(?:禁止转载|不能转载|不可转载)\]/iu', (string) $text) === 1;
    }
}

if (!function_exists('qiwiNormalizeCopyrightMagicWords')) {
    function qiwiNormalizeCopyrightMagicWords($text)
    {
        $lines = preg_split('/\r\n|\r|\n/u', (string) $text);
        foreach ($lines as $index => $line) {
            $trimmed = trim($line);
            if (in_array($trimmed, ['不能转载', '禁止转载', '不可转载'], true)) {
                $lines[$index] = '[no-repost]';
            } elseif (in_array($trimmed, ['非 AI 生成', '本文非 AI 生成', 'Not By AI'], true)) {
                $lines[$index] = '[not-by-ai]';
            } elseif (in_array($trimmed, ['AI 生成', '本文由 AI 生成'], true)) {
                $lines[$index] = '[ai-generated]';
            }
        }

        return implode("\n", $lines);
    }
}

if (!function_exists('qiwiCopyrightVisibleTextWithoutMagic')) {
    function qiwiCopyrightVisibleTextWithoutMagic($text)
    {
        $text = preg_replace('/\[(default|thread|collection|not-by-ai|notbyai|noai|no-repost|no-reprint|no-redistribute|ai-generated|ai-assisted|禁止转载|不能转载|不可转载)(?:\s+[^\]]*)?\](?:\s*\[\/\1\])?/iu', '', (string) $text);
        $text = preg_replace('/\[(default|thread|collection|no-repost|no-reprint|no-redistribute|ai-generated|ai-assisted)(?:\s+[^\]]*)?\][\s\S]*?\[\/\1\]/iu', '', $text);
        return trim(strip_tags($text));
    }
}

if (!function_exists('qiwiRenderCopyrightText')) {
    function qiwiRenderCopyrightText($text, array $context)
    {
        $context['copyright_context'] = true;
        $text = qiwiNormalizeCopyrightMagicWords($text);
        if (qiwiCopyrightHasMagicTags($text)
            && !preg_match('/\[default(?:\s+[^\]]*)?\]/iu', $text)
            && qiwiCopyrightVisibleTextWithoutMagic($text) === '') {
            $text = "[default]\n" . $text;
        }

        return qiwiRenderFieldRichText($text, $context);
    }
}

if (!function_exists('qiwiRenderCopyrightMagicShortcodes')) {
    function qiwiRenderCopyrightMagicShortcodes($html, array $context = [])
    {
        $html = preg_replace_callback('/\[default([^\]]*)\]([\s\S]*?)\[\/default\]/iu', function ($matches) use ($context) {
            $body = trim((string) (isset($matches[2]) ? $matches[2] : ''));
            if ($body === '') {
                return '<div class="post-copyright-default">' . qiwiCopyrightDefaultTemplateHtml($context) . '</div>';
            }

            return '<div class="post-copyright-default">' . $body . '</div>';
        }, $html);

        $html = preg_replace('/\[default([^\]]*)\](?:\s*\[\/default\])?/iu', '<div class="post-copyright-default">' . qiwiCopyrightDefaultTemplateHtml($context) . '</div>', $html);

        $html = preg_replace_callback('/\[(?:thread|collection)([^\]]*)\]([\s\S]*?)\[\/(?:thread|collection)\]/iu', function ($matches) use ($context) {
            return qiwiCopyrightThreadComponent($context, isset($matches[2]) ? $matches[2] : '', isset($matches[1]) ? $matches[1] : '');
        }, $html);
        $html = preg_replace_callback('/\[(?:thread|collection)([^\]]*)\](?:\s*\[\/(?:thread|collection)\])?/iu', function ($matches) use ($context) {
            return qiwiCopyrightThreadComponent($context, '', isset($matches[1]) ? $matches[1] : '');
        }, $html);

        $html = preg_replace_callback('/\[(?:no-repost|no-reprint|no-redistribute|禁止转载|不能转载|不可转载)([^\]]*)\]([\s\S]*?)\[\/(?:no-repost|no-reprint|no-redistribute|禁止转载|不能转载|不可转载)\]/iu', function ($matches) {
            return qiwiCopyrightNoticeComponent('no-repost', isset($matches[2]) ? $matches[2] : '', isset($matches[1]) ? $matches[1] : '');
        }, $html);
        $html = preg_replace_callback('/\[(?:no-repost|no-reprint|no-redistribute|禁止转载|不能转载|不可转载)([^\]]*)\](?:\s*\[\/(?:no-repost|no-reprint|no-redistribute|禁止转载|不能转载|不可转载)\])?/iu', function ($matches) {
            return qiwiCopyrightNoticeComponent('no-repost', '', isset($matches[1]) ? $matches[1] : '');
        }, $html);

        $html = preg_replace_callback('/\[(?:ai-generated|ai-assisted)([^\]]*)\]([\s\S]*?)\[\/(?:ai-generated|ai-assisted)\]/iu', function ($matches) {
            return qiwiCopyrightNoticeComponent('ai-generated', isset($matches[2]) ? $matches[2] : '', isset($matches[1]) ? $matches[1] : '');
        }, $html);
        $html = preg_replace_callback('/\[(?:ai-generated|ai-assisted)([^\]]*)\](?:\s*\[\/(?:ai-generated|ai-assisted)\])?/iu', function ($matches) {
            return qiwiCopyrightNoticeComponent('ai-generated', '', isset($matches[1]) ? $matches[1] : '');
        }, $html);

        return $html;
    }
}

if (!function_exists('qiwiCopyrightThreadComponent')) {
    function qiwiCopyrightThreadComponent(array $context, $body = '', $attrsText = '')
    {
        $title = isset($context['thread_title']) ? trim((string) $context['thread_title']) : '';
        $url = isset($context['thread_url']) ? trim((string) $context['thread_url']) : '';
        if ($title === '' || $url === '') {
            return '';
        }

        $attrs = qiwiParseShortcodeAttrs($attrsText);
        $label = trim((string) $body);
        if ($label === '' && isset($attrs['label']) && trim((string) $attrs['label']) !== '') {
            $label = trim((string) $attrs['label']);
        }
        if ($label === '') {
            $label = '本文收录于文集';
        }

        return '<div class="post-copyright-component post-copyright-thread"><span>' . htmlspecialchars(strip_tags($label), ENT_QUOTES, 'UTF-8') . '</span><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</a></div>';
    }
}

if (!function_exists('qiwiCopyrightNoticeComponent')) {
    function qiwiCopyrightNoticeComponent($type, $body = '', $attrsText = '')
    {
        $attrs = qiwiParseShortcodeAttrs($attrsText);
        $label = trim((string) $body);
        if ($label === '' && isset($attrs['label']) && trim((string) $attrs['label']) !== '') {
            $label = trim((string) $attrs['label']);
        }

        if ($type === 'ai-generated') {
            $label = $label !== '' ? $label : '本文包含 AI 生成或辅助生成内容';
            return '<div class="post-copyright-component post-copyright-ai"><span>AI</span><strong>' . htmlspecialchars(strip_tags($label), ENT_QUOTES, 'UTF-8') . '</strong></div>';
        }

        $label = $label !== '' ? $label : '本文不开放转载；如需引用，请保留作者与原文链接。';
        return '<div class="post-copyright-component post-copyright-no-repost"><span>转载说明</span><strong>' . htmlspecialchars(strip_tags($label), ENT_QUOTES, 'UTF-8') . '</strong></div>';
    }
}

if (!function_exists('qiwiGetPostCopyrightHtml')) {
    function qiwiGetPostCopyrightHtml($widget)
    {
        $context = qiwiPostRichTextContext($widget);
        $context['copyright_context'] = true;
        $context['copyright_license'] = qiwiNormalizeCopyrightLicense(qiwiGetOptionValue($widget, 'defaultCopyrightLicense', 'cc-by-nc-nd-4'));
        $thread = qiwiGetPostThreadCollection($widget);
        if (!empty($thread)) {
            $context['thread_title'] = $thread['name'];
            $context['thread_name'] = $thread['name'];
            $context['thread_url'] = $thread['permalink'];
            $context['thread_permalink'] = $thread['permalink'];
        }

        $custom = qiwiNormalizeRichTextValue(qiwiGetFieldValue($widget, 'copyrightInfo', ''));
        if ($custom !== '') {
            return qiwiRenderCopyrightText($custom, $context);
        }

        $themeDefault = qiwiNormalizeRichTextValue(qiwiGetOptionValue($widget, 'defaultCopyrightInfo', ''));
        if ($themeDefault !== '') {
            return qiwiRenderCopyrightText($themeDefault, $context);
        }

        return qiwiCopyrightDefaultTemplateHtml($context);
    }
}

if (!function_exists('qiwiGetContent')) {
    function qiwiGetContent($widget)
    {
        ob_start();
        $widget->content();
        return qiwiRenderShortcodes(ob_get_clean(), [
            'content_cid' => !empty($widget) && isset($widget->cid) ? (int) $widget->cid : 0,
        ]);
    }
}

if (!function_exists('qiwiCommentSafeUrl')) {
    function qiwiCommentSafeUrl($url)
    {
        $url = trim(html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            return $url;
        }

        return '';
    }
}

if (!function_exists('qiwiRenderCommentParagraphs')) {
    function qiwiRenderCommentParagraphs($html)
    {
        $html = trim(str_replace(["\r\n", "\r"], "\n", (string) $html));
        if ($html === '') {
            return '';
        }

        $paragraphs = preg_split('/[ \t]*\n+[ \t]*/u', $html, -1, PREG_SPLIT_NO_EMPTY);
        return '<p>' . implode('</p><p>', $paragraphs) . '</p>';
    }
}

if (!function_exists('qiwiGetCommentStickerPacks')) {
    function qiwiGetCommentStickerPacks()
    {
        return array(
            'wechat' => array(
                'id' => 'wechat',
                'label' => '微信',
                'source' => qiwiGetMappedAssetUrl('assets/emoji/wechat/manifest.json'),
                'assetBase' => qiwiGetMappedAssetUrl('assets/emoji/wechat/'),
                'extension' => '.png',
            ),
            'heo' => array(
                'id' => 'heo',
                'label' => 'Heo',
                'source' => 'https://cdn.jsdelivr.net/npm/sticker-heo@2022.7.5/twikoo.json',
                'assetBase' => 'https://cdn.jsdelivr.net/npm/sticker-heo@2022.7.5/Sticker-100/',
                'extension' => '.png',
            ),
        );
    }
}

if (!function_exists('qiwiRenderCommentStickers')) {
    function qiwiRenderCommentStickers($html)
    {
        $packs = qiwiGetCommentStickerPacks();

        return preg_replace_callback('/\[sticker:([a-z0-9_-]+)\/([^\]\r\n]{1,80})\]/iu', function ($matches) use ($packs) {
            $packId = strtolower($matches[1]);
            $name = trim(html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (!isset($packs[$packId]) || $name === '' || preg_match('~[\x00-\x1F\x7F/\\\\]~u', $name)) {
                return $matches[0];
            }

            $pack = $packs[$packId];
            $url = $pack['assetBase'] . rawurlencode($name) . $pack['extension'];
            return '<img src="' . htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '" alt="' . htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '" title="' . htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '" class="comment-sticker" loading="lazy" decoding="async">';
        }, $html);
    }
}

if (!function_exists('qiwiRenderHomepageMomentExcerpt')) {
    function qiwiRenderHomepageMomentExcerpt($text, $length = 72)
    {
        $text = qiwiExtractPlainText($text);
        if ($text === '') {
            return '';
        }

        $length = max(1, (int) $length);
        $remaining = $length;
        $excerpt = '';
        $truncated = false;
        $packs = qiwiGetCommentStickerPacks();
        $pattern = '/(\[sticker:(?:[a-z0-9_-]+)\/(?:[^\]\r\n]{1,80})\])/iu';
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        foreach ($parts as $part) {
            $isSticker = false;
            if (preg_match('/^\[sticker:([a-z0-9_-]+)\/([^\]\r\n]{1,80})\]$/iu', $part, $matches)) {
                $packId = strtolower($matches[1]);
                $name = trim(html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $isSticker = isset($packs[$packId]) && $name !== '' && !preg_match('~[\x00-\x1F\x7F/\\\\]~u', $name);
            }

            $partLength = $isSticker ? 1 : mb_strlen($part, 'UTF-8');
            if ($partLength <= $remaining) {
                $excerpt .= $part;
                $remaining -= $partLength;
                continue;
            }

            if (!$isSticker && $remaining > 0) {
                $excerpt .= mb_substr($part, 0, $remaining, 'UTF-8');
            }
            $truncated = true;
            break;
        }

        $excerpt = rtrim($excerpt);
        if ($truncated) {
            $excerpt .= '…';
        }

        return qiwiRenderCommentStickers(htmlspecialchars($excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}

if (!function_exists('qiwiRenderPlainCommentContent')) {
    function qiwiRenderPlainCommentContent($text)
    {
        $text = htmlspecialchars((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return qiwiRenderCommentParagraphs(qiwiRenderCommentStickers($text));
    }
}

if (!function_exists('qiwiRenderTrustedCommentContent')) {
    function qiwiRenderTrustedCommentContent($text)
    {
        $html = htmlspecialchars((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $html = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/u', function ($matches) {
            $url = qiwiCommentSafeUrl($matches[2]);
            if ($url === '') {
                return $matches[0];
            }

            $alt = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return '<img src="' . htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '" class="comment-image qiwi-content-image" loading="lazy" decoding="async">';
        }, $html);

        $html = preg_replace('/`([^`]+?)`/u', '<code>$1</code>', $html);
        $html = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/u', function ($matches) {
            $url = qiwiCommentSafeUrl($matches[2]);
            if ($url === '') {
                return $matches[1];
            }

            $target = preg_match('/^https?:\/\//i', $url) ? ' target="_blank" rel="noopener noreferrer"' : '';
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"' . $target . '>' . $matches[1] . '</a>';
        }, $html);

        return qiwiRenderCommentParagraphs(qiwiRenderCommentStickers($html));
    }
}

if (!function_exists('qiwiUserHasLogin')) {
    function qiwiUserHasLogin()
    {
        try {
            Typecho_Widget::widget('Widget_User')->to($user);
            return $user && $user->hasLogin();
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('qiwiHasRenderedContent')) {
    function qiwiHasRenderedContent($html)
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return false;
        }

        $html = preg_replace('/<!--[\s\S]*?-->/u', '', $html);
        if (trim($html) === '') {
            return false;
        }

        if (trim(strip_tags($html)) !== '') {
            return true;
        }

        return (bool) preg_match('/<(img|iframe|video|audio|canvas|svg|table|hr|blockquote|ul|ol|pre|code|details|embed|object)\b/iu', $html);
    }
}

if (!function_exists('qiwiContent')) {
    function qiwiContent($widget)
    {
        echo qiwiGetContent($widget);
    }
}

if (!function_exists('qiwiGetOptionValue')) {
    function qiwiGetOptionValue($widget, $name, $default = '')
    {
        if (!empty($widget) && !empty($widget->options) && isset($widget->options->{$name})) {
            return $widget->options->{$name};
        }

        if (class_exists('\Widget\Options')) {
            \Widget\Options::alloc()->to($options);
            if (isset($options->{$name})) {
                return $options->{$name};
            }
        }

        return $default;
    }
}

if (!function_exists('qiwiGetPositiveIntOption')) {
    function qiwiGetPositiveIntOption($widget, $name, $default, $min = 1, $max = 99)
    {
        $value = (int) qiwiGetOptionValue($widget, $name, $default);
        if ($value < $min) {
            return (int) $min;
        }
        if ($value > $max) {
            return (int) $max;
        }

        return $value;
    }
}

if (!function_exists('qiwiGetSidebarProfileAvatar')) {
    function qiwiGetSidebarProfileAvatar($widget)
    {
        $avatar = trim((string) qiwiGetOptionValue($widget, 'sidebarProfileAvatar', ''));
        if ($avatar === '') {
            $avatar = trim((string) qiwiGetOptionValue($widget, 'aboutAvatar', ''));
        }

        return $avatar !== '' ? $avatar : 'https://gravatar.loli.net/avatar/default?s=160&d=mp';
    }
}

if (!function_exists('qiwiBusuanziScriptEnabled')) {
    function qiwiBusuanziScriptEnabled($widget = null)
    {
        return (string) qiwiGetOptionValue($widget, 'enableBusuanzi', '0') === '1';
    }
}

if (!function_exists('qiwiGetPageRecords')) {
    function qiwiGetPageRecords()
    {
        static $records = null;
        if ($records !== null) {
            return $records;
        }

        $records = [];
        $templatesByCid = [];
        $navShowByCid = [];

        $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
        $prefix = $db->getPrefix();
        $rows = $db->fetchAll($db->select('cid', 'template')
            ->from($prefix . 'contents')
            ->where('type = ?', 'page')
            ->where('status = ?', 'publish'));

        foreach ($rows as $row) {
            $templatesByCid[(int) $row['cid']] = (string) $row['template'];
        }

        $fieldRows = $db->fetchAll($db->select('cid', 'type', 'int_value', 'str_value')
            ->from($prefix . 'fields')
            ->where('name = ?', 'navShow'));

        foreach ($fieldRows as $row) {
            $fieldType = isset($row['type']) ? (string) $row['type'] : '';
            $rawValue = $fieldType === 'int' ? $row['int_value'] : $row['str_value'];
            if ($rawValue === null || $rawValue === '') {
                $rawValue = isset($row['int_value']) ? $row['int_value'] : null;
            }
            $navShowByCid[(int) $row['cid']] = (string) $rawValue !== '0';
        }

        \Widget\Contents\Page\Rows::alloc()->to($pages);
        while ($pages->next()) {
            ob_start();
            $pages->permalink();
            $permalink = trim(ob_get_clean());

            ob_start();
            $pages->title();
            $title = trim(ob_get_clean());

            $cid = (int) $pages->cid;
            $records[] = [
                'cid' => $cid,
                'slug' => (string) $pages->slug,
                'title' => $title,
                'template' => isset($templatesByCid[$cid]) ? $templatesByCid[$cid] : (string) $pages->template,
                'permalink' => $permalink,
                'nav_show' => isset($navShowByCid[$cid]) ? $navShowByCid[$cid] : true,
            ];
        }

        return $records;
    }
}

if (!function_exists('qiwiFindPageRecord')) {
    function qiwiFindPageRecord($templates = [], $slugs = [])
    {
        $templates = array_filter((array) $templates);
        $slugs = array_filter((array) $slugs);

        foreach (qiwiGetPageRecords() as $page) {
            if (!empty($templates) && in_array((string) $page['template'], $templates, true)) {
                return $page;
            }
        }

        foreach (qiwiGetPageRecords() as $page) {
            if (!empty($slugs) && in_array((string) $page['slug'], $slugs, true)) {
                return $page;
            }
        }

        return null;
    }
}

if (!function_exists('qiwiGetCustomPageUrl')) {
    function qiwiGetCustomPageUrl($widget, $templates)
    {
        $page = qiwiFindPageRecord($templates, []);
        return $page ? $page['permalink'] : '';
    }
}

if (!function_exists('qiwiGetPageUrlBySlug')) {
    function qiwiGetPageUrlBySlug($widget, $slugs)
    {
        $page = qiwiFindPageRecord([], $slugs);
        if ($page) {
            return $page['permalink'];
        }

        $slugs = array_values(array_filter((array) $slugs));
        if (empty($slugs) || empty($widget) || empty($widget->options)) {
            return '';
        }

        return rtrim($widget->options->siteUrl, '/') . '/' . ltrim($slugs[0], '/');
    }
}

if (!function_exists('qiwiResolveNavigationTarget')) {
    function qiwiResolveNavigationTarget($widget, $target)
    {
        $target = trim((string) $target);
        $siteUrl = rtrim((string) qiwiGetOptionValue($widget, 'siteUrl', ''), '/');
        if ($target === '') {
            return ['url' => '#', 'slug' => '', 'external' => false];
        }

        if (preg_match('/^(https?:)?\/\//i', $target) || preg_match('/^(mailto|tel):/i', $target)) {
            return ['url' => $target, 'slug' => '', 'external' => true];
        }

        if (in_array(strtolower($target), ['feed', 'rss'], true)) {
            $feedUrl = trim((string) qiwiGetOptionValue($widget, 'feedUrl', ''));
            if ($feedUrl === '' && $siteUrl !== '') {
                $feedUrl = $siteUrl . '/feed/';
            }

            return ['url' => $feedUrl !== '' ? $feedUrl : '#', 'slug' => '', 'external' => false];
        }

        if ($target[0] === '#') {
            return ['url' => $target, 'slug' => '', 'external' => false];
        }

        if ($target[0] === '/') {
            return ['url' => $siteUrl . $target, 'slug' => '', 'external' => false];
        }

        if (strpos($target, 'template:') === 0) {
            $template = trim(substr($target, 9));
            $page = qiwiFindPageRecord([$template], []);
            return ['url' => $page ? $page['permalink'] : '#', 'slug' => $page ? $page['slug'] : '', 'external' => false];
        }

        if (strpos($target, 'page:') === 0 || strpos($target, 'slug:') === 0) {
            $slug = trim(substr($target, strpos($target, ':') + 1));
            $page = qiwiFindPageRecord([], [$slug]);
            return ['url' => $page ? $page['permalink'] : $siteUrl . '/' . ltrim($slug, '/'), 'slug' => $slug, 'external' => false];
        }

        $page = qiwiFindPageRecord([], [$target]);
        return ['url' => $page ? $page['permalink'] : $siteUrl . '/' . ltrim($target, '/'), 'slug' => $target, 'external' => false];
    }
}

if (!function_exists('qiwiSanitizeIconClass')) {
    function qiwiSanitizeIconClass($className)
    {
        $className = trim((string) $className);
        if ($className === '') {
            return '';
        }

        $classes = preg_split('/\s+/', $className);
        $safe = [];
        foreach ($classes as $class) {
            if (preg_match('/^(fa|fa-[a-z0-9-]+|fa[bsrltd]|fa-solid|fa-regular|fa-brands)$/i', $class)) {
                $safe[] = strtolower($class);
            }
        }

        return implode(' ', array_unique($safe));
    }
}

if (!function_exists('qiwiSplitNavLine')) {
    /**
     * 按未转义的 | 分割导航行，\| 表示字面 |，\\ 表示字面 \。
     * 与 assets/js/admin-config.js 的 navSplitFields 保持一致。
     */
    function qiwiSplitNavLine($line)
    {
        $parts = [];
        $current = '';
        $length = strlen($line);
        for ($i = 0; $i < $length; $i++) {
            $char = $line[$i];
            if ($char === '\\' && $i + 1 < $length) {
                $next = $line[$i + 1];
                $current .= ($next === '|' || $next === '\\') ? $next : $char . $next;
                $i++;
                continue;
            }
            if ($char === '|') {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $parts[] = $current;

        return $parts;
    }
}

if (!function_exists('qiwiGetNavigationItems')) {
    function qiwiGetNavigationItems($widget)
    {
        $config = trim((string) qiwiGetOptionValue($widget, 'navItems', ''));
        $items = [];

        if ($config === '') {
            foreach (qiwiGetPageRecords() as $page) {
                if (empty($page['nav_show'])) {
                    continue;
                }

                $items[] = [
                    'title' => $page['title'],
                    'url' => $page['permalink'],
                    'slug' => $page['slug'],
                    'external' => false,
                    'icon' => '',
                    'children' => [],
                ];
            }

            return $items;
        }

        $lastParentIndex = null;
        foreach (preg_split('/\r\n|\r|\n/', $config) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $isChild = strpos($line, '-') === 0;
            if ($isChild) {
                $line = trim(substr($line, 1));
            }

            // 与 admin-config.js 的 parseNav 对齐：标题/链接单独 trim，图标先拼接再整体 trim
            $parts = qiwiSplitNavLine($line);
            $title = trim($parts[0]);
            $target = isset($parts[1]) ? trim($parts[1]) : '#';
            $icon = isset($parts[2]) ? qiwiSanitizeIconClass(trim(implode('|', array_slice($parts, 2)))) : '';
            if ($title === '') {
                continue;
            }

            $resolved = qiwiResolveNavigationTarget($widget, $target);
            $item = [
                'title' => $title,
                'url' => $resolved['url'],
                'slug' => $resolved['slug'],
                'external' => $resolved['external'],
                'icon' => $icon,
                'children' => [],
            ];

            if ($isChild && $lastParentIndex !== null) {
                $items[$lastParentIndex]['children'][] = $item;
                continue;
            }

            $items[] = $item;
            $lastParentIndex = count($items) - 1;
        }

        return $items;
    }
}

if (!function_exists('qiwiCurrentTime')) {
    /**
     * 与 Typecho 核心比较 created 时使用的 options->time 保持同一口径。
     * 当前 1.3 核心里它等于 time()，但旧版核心中二者相差站点时区偏移，
     * 直接用裸 time() 会让相对时间整体偏移。
     */
    function qiwiCurrentTime()
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        try {
            $options = Typecho_Widget::widget('Widget_Options');
            if (isset($options->time) && (int) $options->time > 0) {
                $cached = (int) $options->time;
                return $cached;
            }
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }

        $cached = time();
        return $cached;
    }
}

if (!function_exists('qiwiFormatJikeRelativeTime')) {
    function qiwiFormatJikeRelativeTime($timestamp, $now = null)
    {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) {
            return '';
        }

        $now = $now === null ? qiwiCurrentTime() : (int) $now;
        $diff = max(0, $now - $timestamp);

        if ($diff < 300) {
            return '刚刚';
        }

        if ($diff < 3600) {
            return floor($diff / 60) . '分钟前';
        }

        if ($diff < 86400) {
            return floor($diff / 3600) . '小时前';
        }

        if ($diff < 259200) {
            return floor($diff / 86400) . '天前';
        }

        return date('m-d', $timestamp);
    }
}

if (!function_exists('qiwiFormatPostRelativeTime')) {
    function qiwiFormatPostRelativeTime($timestamp, $now = null)
    {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) {
            return '';
        }

        $now = $now === null ? qiwiCurrentTime() : (int) $now;
        $diff = max(0, $now - $timestamp);

        if ($diff < 300) {
            return '刚刚';
        }

        if ($diff < 3600) {
            return floor($diff / 60) . '分钟前';
        }

        if ($diff < 86400) {
            return floor($diff / 3600) . '小时前';
        }

        if ($diff < 259200) {
            return floor($diff / 86400) . '天前';
        }

        if (date('Y', $timestamp) === date('Y', $now)) {
            return date('m-d', $timestamp);
        }

        return date('Y-m-d', $timestamp);
    }
}

if (!function_exists('qiwiGetPostViewsFieldName')) {
    function qiwiGetPostViewsFieldName()
    {
        return 'qiwiViews';
    }
}

if (!function_exists('qiwiFormatPostWordCount')) {
    function qiwiFormatPostWordCount($wordCount)
    {
        $wordCount = max(0, (int) $wordCount);
        if ($wordCount >= 10000) {
            return rtrim(rtrim(number_format($wordCount / 10000, 1), '0'), '.') . '万字';
        }
        if ($wordCount >= 1000) {
            return rtrim(rtrim(number_format($wordCount / 1000, 1), '0'), '.') . 'k字';
        }

        return $wordCount . '字';
    }
}

if (!function_exists('qiwiGetCommentAvatarUrl')) {
    function qiwiGetCommentAvatarUrl($mail, $size = 48)
    {
        $mail = strtolower(trim((string) $mail));
        $size = max(24, min(160, (int) $size));

        if (preg_match('/^([1-9][0-9]{4,11})@qq\.com$/i', $mail, $matches)) {
            return 'https://q1.qlogo.cn/g?b=qq&nk=' . rawurlencode($matches[1]) . '&s=100';
        }

        return 'https://gravatar.loli.net/avatar/' . md5($mail) . '?s=' . $size . '&d=mp';
    }
}

if (!function_exists('qiwiGetCommentCountIncludingReplies')) {
    function qiwiGetCommentCountIncludingReplies($cid)
    {
        $cid = (int) $cid;
        if ($cid <= 0) {
            return 0;
        }

        try {
            $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
            $prefix = $db->getPrefix();
            $row = $db->fetchRow($db->select('COUNT(coid) AS total')
                ->from($prefix . 'comments')
                ->where('cid = ?', $cid)
                ->where('status = ?', 'approved')
                ->where('type = ?', 'comment'));

            return !empty($row['total']) ? (int) $row['total'] : 0;
        } catch (Exception $e) {
            return 0;
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('qiwiPrimePostStatsCache')) {
    function qiwiPrimePostStatsCache(array $cids)
    {
        static $cache = array(
            'views' => array(),
            'comments' => array(),
        );

        $cids = array_values(array_unique(array_filter(array_map('intval', $cids))));
        if (empty($cids)) {
            return $cache;
        }

        $missingViews = array();
        $missingComments = array();
        foreach ($cids as $cid) {
            if (!array_key_exists($cid, $cache['views'])) {
                $missingViews[] = $cid;
            }
            if (!array_key_exists($cid, $cache['comments'])) {
                $missingComments[] = $cid;
            }
        }

        if (empty($missingViews) && empty($missingComments)) {
            return $cache;
        }

        try {
            $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
            $prefix = $db->getPrefix();

            if (!empty($missingViews)) {
                foreach ($missingViews as $cid) {
                    $cache['views'][$cid] = 0;
                }

                $viewRows = $db->fetchAll($db->select('cid', 'type', 'int_value', 'str_value', 'float_value')
                    ->from($prefix . 'fields')
                    ->where('cid IN ?', $missingViews)
                    ->where('name = ?', qiwiGetPostViewsFieldName()));

                foreach ($viewRows as $row) {
                    $cid = isset($row['cid']) ? (int) $row['cid'] : 0;
                    if ($cid <= 0) {
                        continue;
                    }

                    $type = isset($row['type']) ? (string) $row['type'] : '';
                    if ($type === 'int') {
                        $cache['views'][$cid] = max(0, (int) $row['int_value']);
                        continue;
                    }

                    if ($type === 'float') {
                        $cache['views'][$cid] = max(0, (int) $row['float_value']);
                        continue;
                    }

                    if ($row['str_value'] !== null && $row['str_value'] !== '') {
                        $cache['views'][$cid] = max(0, (int) $row['str_value']);
                        continue;
                    }

                    if ($row['int_value'] !== null) {
                        $cache['views'][$cid] = max(0, (int) $row['int_value']);
                    }
                }
            }

            if (!empty($missingComments)) {
                foreach ($missingComments as $cid) {
                    $cache['comments'][$cid] = 0;
                }

                $commentRows = $db->fetchAll($db->select('cid', 'COUNT(coid) AS total')
                    ->from($prefix . 'comments')
                    ->where('cid IN ?', $missingComments)
                    ->where('status = ?', 'approved')
                    ->where('type = ?', 'comment')
                    ->group('cid'));

                foreach ($commentRows as $row) {
                    $cid = isset($row['cid']) ? (int) $row['cid'] : 0;
                    if ($cid > 0) {
                        $cache['comments'][$cid] = max(0, (int) $row['total']);
                    }
                }
            }
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }

        return $cache;
    }
}

if (!function_exists('qiwiGetPostStats')) {
    function qiwiGetPostStats($cid)
    {
        $cid = (int) $cid;
        if ($cid <= 0) {
            return array('views' => 0, 'comments' => 0);
        }

        $cache = qiwiPrimePostStatsCache(array($cid));
        return array(
            'views' => isset($cache['views'][$cid]) ? (int) $cache['views'][$cid] : 0,
            'comments' => isset($cache['comments'][$cid]) ? (int) $cache['comments'][$cid] : 0,
        );
    }
}

if (!function_exists('qiwiGetCommentLocationLabel')) {
    function qiwiGetCommentLocationLabel($comment)
    {
        $ip = '';
        if (is_object($comment) && isset($comment->ip)) {
            $ip = (string) $comment->ip;
        } elseif (is_array($comment) && isset($comment['ip'])) {
            $ip = (string) $comment['ip'];
        }

        if ($ip === '' || !class_exists('QiwiTheme_Plugin')) {
            return '未知';
        }

        if (method_exists('QiwiTheme_Plugin', 'ipLocationLabelFromCache')) {
            $label = QiwiTheme_Plugin::ipLocationLabelFromCache($ip);
            return $label !== '' ? $label : '未知';
        }

        if (method_exists('QiwiTheme_Plugin', 'ipLocationLabel')) {
            $label = QiwiTheme_Plugin::ipLocationLabel($ip);
            return $label !== '' ? $label : '未知';
        }

        return '未知';
    }
}

if (!function_exists('qiwiGetPostViews')) {
    function qiwiGetPostViews($cid)
    {
        $cid = (int) $cid;
        if ($cid <= 0) {
            return 0;
        }

        $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
        $prefix = $db->getPrefix();
        $row = $db->fetchRow($db->select('int_value', 'str_value')
            ->from($prefix . 'fields')
            ->where('cid = ?', $cid)
            ->where('name = ?', qiwiGetPostViewsFieldName())
            ->limit(1));

        if (empty($row)) {
            return 0;
        }

        if (isset($row['int_value']) && $row['int_value'] !== null) {
            return max(0, (int) $row['int_value']);
        }

        return max(0, (int) $row['str_value']);
    }
}

if (!function_exists('qiwiSetPostViews')) {
    function qiwiSetPostViews($cid, $views)
    {
        $cid = (int) $cid;
        $views = max(0, (int) $views);

        if ($cid <= 0) {
            return 0;
        }

        $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
        $prefix = $db->getPrefix();
        $fieldName = qiwiGetPostViewsFieldName();
        $existing = $db->fetchRow($db->select('cid')
            ->from($prefix . 'fields')
            ->where('cid = ?', $cid)
            ->where('name = ?', $fieldName)
            ->limit(1));

        if (!empty($existing)) {
            $db->query($db->update($prefix . 'fields')
                ->rows([
                    'type' => 'int',
                    'int_value' => $views,
                    'str_value' => null,
                    'float_value' => 0,
                ])
                ->where('cid = ?', $cid)
                ->where('name = ?', $fieldName));
        } else {
            $db->query($db->insert($prefix . 'fields')->rows([
                'cid' => $cid,
                'name' => $fieldName,
                'type' => 'int',
                'str_value' => null,
                'int_value' => $views,
                'float_value' => 0,
            ]));
        }

        return $views;
    }
}

if (!function_exists('qiwiRecordPostView')) {
    function qiwiRecordPostView($cid)
    {
        $cid = (int) $cid;
        if ($cid <= 0) {
            return 0;
        }

        $currentViews = qiwiGetPostViews($cid);

        if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper($_SERVER['REQUEST_METHOD']) !== 'GET') {
            return $currentViews;
        }

        // 单一 cookie 记录已计数的文章（最新在前，容量 50，1 小时滑动过期），
        // 避免旧方案每篇文章一个 qiwi_post_viewed_{cid} cookie 造成膨胀；
        // 过渡期内旧 cookie 仍参与去重判断，但不再写入。
        $queueCookie = 'qiwi_post_viewed';
        $viewed = [];
        if (isset($_COOKIE[$queueCookie]) && is_string($_COOKIE[$queueCookie])) {
            foreach (explode(',', $_COOKIE[$queueCookie]) as $viewedCid) {
                $viewedCid = (int) trim($viewedCid);
                if ($viewedCid > 0) {
                    $viewed[] = $viewedCid;
                }
            }
        }

        if (in_array($cid, $viewed, true) || isset($_COOKIE['qiwi_post_viewed_' . $cid])) {
            return $currentViews;
        }

        array_unshift($viewed, $cid);
        $viewed = array_slice(array_values(array_unique($viewed)), 0, 50);
        $queueValue = implode(',', $viewed);

        $updatedViews = qiwiSetPostViews($cid, $currentViews + 1);
        setcookie($queueCookie, $queueValue, time() + 3600, '/');
        $_COOKIE[$queueCookie] = $queueValue;

        return $updatedViews;
    }
}

if (!function_exists('qiwiGetHomepageJikeData')) {
    function qiwiGetHomepageJikeData($limit = 5)
    {
        $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
        $prefix = $db->getPrefix();

        $page = $db->fetchRow($db->select('cid', 'title', 'slug', 'authorId')
            ->from($prefix . 'contents')
            ->where('type = ?', 'page')
            ->where('status = ?', 'publish')
            ->where('(template = ? OR template = ?)', 'page-timemachine.php', 'page-timemachine')
            ->order('created', $db::SORT_DESC)
            ->limit(1));

        if (empty($page) || empty($page['cid'])) {
            return null;
        }

        $permalink = '';
        \Widget\Contents\Page\Rows::alloc()->to($pages);
        while ($pages->next()) {
            if ((int) $pages->cid === (int) $page['cid']) {
                ob_start();
                $pages->permalink();
                $permalink = trim(ob_get_clean());
                break;
            }
        }

        if ($permalink === '') {
            return null;
        }

        $comments = $db->fetchAll($db->select('coid', 'text', 'created')
            ->from($prefix . 'comments')
            ->where('cid = ?', $page['cid'])
            ->where('status = ?', 'approved')
            ->where('type = ?', 'comment')
            ->where('authorId = ?', $page['authorId'])
            ->where('(parent IS NULL OR parent = ?)', 0)
            ->order('created', $db::SORT_DESC)
            ->limit((int) $limit));

        if (empty($comments)) {
            return null;
        }

        $items = [];
        foreach ($comments as $comment) {
            $excerpt = qiwiExcerptText($comment['text']);
            $excerptHtml = qiwiRenderHomepageMomentExcerpt($comment['text']);
            if ($excerpt === '') {
                $excerpt = qiwiFallbackJikeExcerpt($comment['text']);
            }
            if ($excerptHtml === '' && $excerpt !== '') {
                $excerptHtml = htmlspecialchars($excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            if ($excerpt === '') {
                continue;
            }

            $items[] = [
                'coid' => (int) $comment['coid'],
                'excerpt' => $excerpt,
                'excerpt_html' => $excerptHtml,
                'datetime' => date('c', (int) $comment['created']),
                'date_label' => date('m-d', (int) $comment['created']),
                'relative_date_label' => qiwiFormatJikeRelativeTime((int) $comment['created']),
            ];
        }

        if (empty($items)) {
            return null;
        }

        return [
            'title' => $page['title'],
            'permalink' => $permalink,
            'items' => $items,
        ];
    }
}
