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

if (!function_exists('qiwiCanRenderCaptcha')) {
    function qiwiCanRenderCaptcha()
    {
        if (!class_exists('Geetest_Plugin') || !method_exists('Geetest_Plugin', 'commentCaptchaRender')) {
            return false;
        }

        try {
            $options = Typecho_Widget::widget('Widget_Options');
            if (empty($options->plugins['activated']['Geetest'])) {
                return false;
            }

            $pluginOptions = Helper::options()->plugin('Geetest');
            $enabledPages = isset($pluginOptions->isOpenGeetestPage) ? $pluginOptions->isOpenGeetestPage : [];

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
        if (!qiwiCanRenderCaptcha()) {
            return false;
        }

        try {
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
        foreach (['content', 'text'] as $property) {
            if (isset($widget->{$property}) && trim((string) $widget->{$property}) !== '') {
                return (string) $widget->{$property};
            }
        }

        if (!empty($widget) && isset($widget->cid)) {
            $cid = (int) $widget->cid;
            if ($cid > 0) {
                $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
                $prefix = $db->getPrefix();
                $row = $db->fetchRow($db->select('text')
                    ->from($prefix . 'contents')
                    ->where('cid = ?', $cid)
                    ->limit(1));

                if (!empty($row['text'])) {
                    return (string) $row['text'];
                }
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

if (!function_exists('qiwiAdminConfigEnhancerAssets')) {
    function qiwiAdminConfigEnhancerAssets()
    {
        $adminConfigCssVersion = file_exists(__DIR__ . '/assets/css/admin-config.css') ? filemtime(__DIR__ . '/assets/css/admin-config.css') : time();
        $adminConfigJsVersion = file_exists(__DIR__ . '/assets/js/admin-config.js') ? filemtime(__DIR__ . '/assets/js/admin-config.js') : time();
        $css = htmlspecialchars(qiwiGetMappedAssetUrl('assets/css/admin-config.css') . '?v=' . $adminConfigCssVersion, ENT_QUOTES, 'UTF-8');
        $js = htmlspecialchars(qiwiGetMappedAssetUrl('assets/js/admin-config.js') . '?v=' . $adminConfigJsVersion, ENT_QUOTES, 'UTF-8');
        $fa = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css';
        $metadata = qiwiGetLocalUpdateMetadata();
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

if (!function_exists('qiwiGetExternalLinkGotoBase')) {
    function qiwiGetExternalLinkGotoBase($options = null)
    {
        if (!class_exists('QiwiTheme_Plugin') || !method_exists('QiwiTheme_Plugin', 'decodeGotoUrl')) {
            return '';
        }

        try {
            if ($options === null) {
                $options = Typecho_Widget::widget('Widget_Options');
            }

            if (Typecho_Router::get('qiwi_theme_goto_route') !== null) {
                return Typecho_Router::url('qiwi_theme_goto_route', [], $options->index);
            }

            $actionTable = [];
            if (isset($options->actionTable)) {
                $actionTable = qiwiDecodeTypechoTableOption($options->actionTable);
            }

            if (isset($actionTable['qiwi-theme']) && $actionTable['qiwi-theme'] === 'QiwiTheme_Action') {
                return Typecho_Common::url('/action/qiwi-theme?do=goto', $options->index);
            }
        } catch (Exception $e) {
            return '';
        } catch (Throwable $e) {
            return '';
        }

        return '';
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

    // Captcha Script 是否安装 Qiwi GTest 插件并启用；单选题
    $enabledCaptcha = new Typecho_Widget_Helper_Form_Element_Radio(
        'enabledCaptcha',
        array(
            '1' => _t('启用'),
            '0' => _t('关闭')
        ),
        '0',
        _t('启用验证码'),
        _t('如果你已经安装并启用了 Qiwi GTest 插件，可以选择启用验证码功能。')
    );

    $form->addInput($enabledCaptcha);

    $sidebarBlock = new \Typecho\Widget\Helper\Form\Element\Checkbox(
        'sidebarBlock',
        [
            'ShowRecentPosts'    => _t('显示最新文章'),
            'ShowCategory'       => _t('显示分类'),
            'ShowArchive'        => _t('显示归档'),
            'ShowTags'           => _t('显示标签'),
        ],
        ['ShowRecentPosts', 'ShowCategory', 'ShowTags'],
        _t('侧边栏显示')
    );

    $form->addInput($sidebarBlock->multiMode());

    $sidebarSocialLinks = new Typecho_Widget_Helper_Form_Element_Textarea(
        'sidebarSocialLinks',
        null,
        null,
        _t('侧边栏社交链接 - 原始数据'),
        _t("结构化编辑器会自动同步到这里。每行一个链接：标题|链接|Font Awesome 图标类。可用于手动微调或兼容旧版配置。")
    );
    $form->addInput($sidebarSocialLinks);

    $sidebarProfileAvatar = new Typecho_Widget_Helper_Form_Element_Text(
        'sidebarProfileAvatar',
        null,
        null,
        _t('侧边栏 - 头像'),
        _t('侧边栏顶部展示的头像 URL。留空时兼容旧版“关于页面头像”，再留空则使用默认头像。')
    );
    $form->addInput($sidebarProfileAvatar);

    $sidebarProfileText = new Typecho_Widget_Helper_Form_Element_Textarea(
        'sidebarProfileText',
        null,
        null,
        _t('侧边栏 - 文字'),
        _t('显示在侧边栏头像下方的一小段文字。留空时兼容旧版“关于页面简介”。')
    );
    $form->addInput($sidebarProfileText);

    $showSidebarAnnouncement = new Typecho_Widget_Helper_Form_Element_Radio(
        'showSidebarAnnouncement',
        array(
            1 => _t('显示'),
            0 => _t('不显示')
        ),
        0,
        _t('侧边栏 - 公告显示'),
        _t('关闭或公告内容留空时，前台不会输出公告区域。')
    );
    $form->addInput($showSidebarAnnouncement);

    $sidebarAnnouncement = new Typecho_Widget_Helper_Form_Element_Textarea(
        'sidebarAnnouncement',
        null,
        '',
        _t('侧边栏 - 公告'),
        _t('显示在个人信息下方。支持魔法标签：[PV]、[UV]、[TODAY_PV]、[TODAY_UV]、[PAGE_PV]、[PAGE_UV]、[province]。')
    );
    $form->addInput($sidebarAnnouncement);

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

    // 一言打字机效果
    $enableHitokoto = new Typecho_Widget_Helper_Form_Element_Radio(
        'enableHitokoto',
        array(1 => _t('启用'),
              0 => _t('关闭')),
        1,
        _t('一言打字机效果'),
        _t('在侧边栏个人简介处启用一言打字机效果，默认启用')
    );
    $form->addInput($enableHitokoto);

    // 开往功能
    $enableTravellings = new Typecho_Widget_Helper_Form_Element_Radio(
        'enableTravellings',
        array(1 => _t('启用'),
              0 => _t('关闭')),
        1,
        _t('开往（Travellings）'),
        _t('在顶部导航栏显示"开往"链接，默认启用')
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
        _t("留空则自动显示所有独立页面。每行一个导航项：标题|链接|Font Awesome 图标类。二级菜单在行首加 -，例如：\n归档|template:page-archives.php|fa-solid fa-box-archive\n- 分类|template:page-categories.php|fa-solid fa-folder\n- 标签|template:page-tags.php|fa-solid fa-tags\n外链|https://example.com|fa-solid fa-arrow-up-right-from-square\n链接支持完整 URL、/path、slug、slug:about、page:about、template:page-tags.php。") . qiwiAdminConfigEnhancerAssets()
    );
    $form->addInput($navItems);

    // 说说展示位置
    $jikePosition = new Typecho_Widget_Helper_Form_Element_Radio(
        'jikePosition',
        array(
            'off'    => _t('关闭'),
            'sidebar' => _t('右侧栏（说说时间线）'),
        ),
        'sidebar',
        _t('侧边栏说说'),
        _t('在全站侧边栏展示时光机页面的最新说说。需要已发布一个使用“时间机器”模板的独立页面。')
    );
    $form->addInput($jikePosition);

    $jikeTimeMode = new Typecho_Widget_Helper_Form_Element_Radio(
        'jikeTimeMode',
        array(
            'absolute' => _t('纯日期'),
            'relative' => _t('相对时间'),
        ),
        'absolute',
        _t('侧边栏说说 - 时间显示'),
        _t('纯日期显示为 MM-DD；相对时间支持“刚刚 / X分钟前 / X小时前 / X天前”，超过 3 天后自动回退为 MM-DD。')
    );
    $form->addInput($jikeTimeMode);

    $sidebarMomentCount = new Typecho_Widget_Helper_Form_Element_Text(
        'sidebarMomentCount',
        null,
        '4',
        _t('侧边栏说说 - 展示数量'),
        _t('侧边栏展示的最新说说数量，建议 3-6 条。')
    );
    $form->addInput($sidebarMomentCount);

    $homeHeroEyebrow = new Typecho_Widget_Helper_Form_Element_Text(
        'homeHeroEyebrow',
        null,
        '写作 · 技术 · 生活 · 随笔',
        _t('首页 Hero - 小字标签'),
        _t('显示在首页大标题上方，建议用短词并以 · 分隔。')
    );
    $form->addInput($homeHeroEyebrow);

    $homeHeroLines = new Typecho_Widget_Helper_Form_Element_Textarea(
        'homeHeroLines',
        null,
        "把[caramel]生活[/caramel]写成笔记\n在[green]结构[/green]里寻找回声\n持续记录，[cyan]慢慢理解[/cyan]",
        _t('首页 Hero - 轮播句子'),
        _t('每行一句。支持 [caramel]文字[/caramel]、[red]、[orange]、[yellow]、[green]、[cyan]、[blue]、[purple] 标注高亮。')
    );
    $form->addInput($homeHeroLines);

    $homeHeroQuote = new Typecho_Widget_Helper_Form_Element_Textarea(
        'homeHeroQuote',
        null,
        null,
        _t('首页 Hero - 简短说明'),
        _t('显示在首页 Hero 大标题下方。留空时兼容旧版“关于页面简介”。')
    );
    $form->addInput($homeHeroQuote);

    $homeHeroSwitchInterval = new Typecho_Widget_Helper_Form_Element_Text(
        'homeHeroSwitchInterval',
        null,
        '5200',
        _t('首页 Hero - 文字切换时间'),
        _t('单位毫秒。建议 3500-9000，例如 5200。')
    );
    $form->addInput($homeHeroSwitchInterval);

    $homeHeroTypingSpeed = new Typecho_Widget_Helper_Form_Element_Text(
        'homeHeroTypingSpeed',
        null,
        '92',
        _t('首页 Hero - 打字速度'),
        _t('单位毫秒，每个字符出现的间隔。仅打字机模式生效，建议 60-160。')
    );
    $form->addInput($homeHeroTypingSpeed);

    $homeHeroDeletingSpeed = new Typecho_Widget_Helper_Form_Element_Text(
        'homeHeroDeletingSpeed',
        null,
        '24',
        _t('首页 Hero - 删除速度'),
        _t('单位毫秒，每个字符删除的间隔。仅打字机模式生效，建议 15-80。')
    );
    $form->addInput($homeHeroDeletingSpeed);

    $homeHeroTypingPause = new Typecho_Widget_Helper_Form_Element_Text(
        'homeHeroTypingPause',
        null,
        '220',
        _t('首页 Hero - 空白停顿'),
        _t('单位毫秒。删除完上一句后，空一小会儿再打出下一句。仅打字机模式生效。')
    );
    $form->addInput($homeHeroTypingPause);

    $homeHeroAnimation = new Typecho_Widget_Helper_Form_Element_Radio(
        'homeHeroAnimation',
        array(
            'fade' => _t('淡入淡出'),
            'typewriter' => _t('打字机（先快速删除，再打出）'),
        ),
        'fade',
        _t('首页 Hero - 切换方式'),
        _t('打字机模式会先较快删除上一句，再逐字打出下一句；动效关闭偏好下会自动停用切换。')
    );
    $form->addInput($homeHeroAnimation);

    $homeHeroHitokotoMode = new Typecho_Widget_Helper_Form_Element_Radio(
        'homeHeroHitokotoMode',
        array(
            'list' => _t('列表轮播'),
            'loop-hitokoto' => _t('列表循环，并隔条插入一言'),
            'hitokoto-after-list' => _t('列表播完一遍后切换为一言'),
        ),
        'list',
        _t('首页 Hero - 一言模式'),
        _t('一言来自 hitokoto 接口；接口失败时会继续显示本地列表。')
    );
    $form->addInput($homeHeroHitokotoMode);

    // 关于页面信息
    $aboutBio = new Typecho_Widget_Helper_Form_Element_Text('aboutBio', null, null, _t('关于页面 - 简介'), _t('在这里填写你的简介，将显示在关于页面的个人信息卡片中'));
    $aboutAvatar = new Typecho_Widget_Helper_Form_Element_Text('aboutAvatar', null, null, _t('关于页面 - 头像'), _t('在这里填写你的头像URL地址，将显示在关于页面的个人信息卡片中，留空则显示默认头像'));

    $form->addInput($aboutBio);
    $form->addInput($aboutAvatar);

    // 自定义CSS / JS / 页脚信息 / JS追踪代码
    $customCSS = new Typecho_Widget_Helper_Form_Element_Textarea('customCSS', null, null, _t('自定义 CSS'), _t('在这里填写自定义 CSS 代码'));
    $customJS = new Typecho_Widget_Helper_Form_Element_Textarea('customJS', null, null, _t('自定义 JS'), _t('在这里填写自定义 JS代码'));
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

    $layout->addItem($isLatex);
    $layout->addItem($tocDisplay);

    if ($isPostEditor || $isUnknownEditor) {
        $layout->addItem($excerpt);
        $layout->addItem($copyrightInfo);
        $layout->addItem($showThumbnail);
        $layout->addItem($thumbnail);
        $layout->addItem($isSticky);
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

        $text = preg_replace('/\[mark(?:\s+color=(["\']?)[a-zA-Z]+\1)?\]([\s\S]*?)\[\/mark\]/iu', '$2', $text);
        $text = preg_replace('/\[badge(?:\s+[^\]]*)?\]([\s\S]*?)\[\/badge\]/iu', '$1', $text);
        $text = preg_replace('/\[button(?:\s+[^\]]*)?\]([\s\S]*?)\[\/button\]/iu', '$1', $text);
        $text = preg_replace('/\[buttons(?:\s+[^\]]*)?\]([\s\S]*?)\[\/buttons\]/iu', '$1', $text);

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
        $text = preg_replace('/\[\/?(?:mark|fold|badge|button|buttons|callout|' . $colors . ')(?:\s+[^\]]*)?\]/iu', '', $text);

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

if (!function_exists('qiwiRenderShortcodeSegment')) {
    function qiwiRenderShortcodeSegment($html, array $context = [])
    {
        $colors = 'red|orange|yellow|green|cyan|blue|purple';
        $foldOpening = '\[fold(?:\s+[^\]]*)?\]';
        $calloutOpening = '\[callout(?:\s+[^\]]*)?\]';
        $buttonsOpening = '\[buttons(?:\s+[^\]]*)?\]';
        $isCopyrightContext = !empty($context['copyright_context']);
        $copyrightOpening = '\[(?:default|thread|collection|no-repost|no-reprint|no-redistribute|ai-generated|ai-assisted)(?:\s+[^\]]*)?\]';

        $html = preg_replace('/<p>\s*(' . $foldOpening . ')\s*<br\s*\/?>\s*([\s\S]*?)<\/p>/iu', '$1<p>$2</p>', $html);
        $html = preg_replace('/<p>\s*(' . $foldOpening . ')\s*<\/p>/iu', '$1', $html);
        $html = preg_replace('/<p>([\s\S]*?)<br\s*\/?>\s*(\[\/fold\])\s*<\/p>/iu', '<p>$1</p>$2', $html);
        $html = preg_replace('/<p>\s*(\[\/fold\])\s*<\/p>/iu', '$1', $html);
        $html = preg_replace('/<p>\s*(' . $calloutOpening . ')\s*<br\s*\/?>\s*([\s\S]*?)<\/p>/iu', '$1<p>$2</p>', $html);
        $html = preg_replace('/<p>\s*(' . $calloutOpening . ')\s*<\/p>/iu', '$1', $html);
        $html = preg_replace('/<p>([\s\S]*?)<br\s*\/?>\s*(\[\/callout\])\s*<\/p>/iu', '<p>$1</p>$2', $html);
        $html = preg_replace('/<p>\s*(\[\/callout\])\s*<\/p>/iu', '$1', $html);
        $html = preg_replace('/<p>\s*(' . $buttonsOpening . ')\s*<br\s*\/?>\s*([\s\S]*?)<\/p>/iu', '$1<span>$2</span>', $html);
        $html = preg_replace('/<p>\s*(' . $buttonsOpening . ')\s*<\/p>/iu', '$1', $html);
        $html = preg_replace('/<p>([\s\S]*?)<br\s*\/?>\s*(\[\/buttons\])\s*<\/p>/iu', '<span>$1</span>$2', $html);
        $html = preg_replace('/<p>\s*(\[\/buttons\])\s*<\/p>/iu', '$1', $html);
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
            if (preg_match('/^\[(?:callout|buttons|fold)(?:\s+[^\]]*)?\]/iu', $block)
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
        return qiwiRenderShortcodes(ob_get_clean());
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

if (!function_exists('qiwiRenderPlainCommentContent')) {
    function qiwiRenderPlainCommentContent($text)
    {
        $text = htmlspecialchars((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return nl2br($text, false);
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

        return nl2br($html, false);
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

if (!function_exists('qiwiGetSidebarProfileText')) {
    function qiwiGetSidebarProfileText($widget)
    {
        $text = trim((string) qiwiGetOptionValue($widget, 'sidebarProfileText', ''));
        if ($text === '') {
            $text = trim((string) qiwiGetOptionValue($widget, 'aboutBio', ''));
        }

        return $text;
    }
}

if (!function_exists('qiwiBusuanziScriptEnabled')) {
    function qiwiBusuanziScriptEnabled($widget = null)
    {
        return (string) qiwiGetOptionValue($widget, 'enableBusuanzi', '0') === '1';
    }
}

if (!function_exists('qiwiGetCurrentVisitorLocationLabel')) {
    function qiwiGetCurrentVisitorLocationLabel()
    {
        if (class_exists('QiwiTheme_Plugin') && method_exists('QiwiTheme_Plugin', 'currentVisitorLocationLabelFromCache')) {
            $label = QiwiTheme_Plugin::currentVisitorLocationLabelFromCache();
            return $label !== '' ? $label : '未知';
        }

        if (class_exists('QiwiTheme_Plugin') && method_exists('QiwiTheme_Plugin', 'currentVisitorLocationLabel')) {
            $label = QiwiTheme_Plugin::currentVisitorLocationLabel();
            return $label !== '' ? $label : '未知';
        }

        return '未知';
    }
}

if (!function_exists('qiwiRenderSidebarAnnouncement')) {
    function qiwiRenderSidebarAnnouncement($widget)
    {
        if ((string) qiwiGetOptionValue($widget, 'showSidebarAnnouncement', '0') !== '1') {
            return '';
        }

        $text = trim((string) qiwiGetOptionValue($widget, 'sidebarAnnouncement', ''));
        if ($text === '') {
            return '';
        }

        $tokens = [
            '[PV]' => '<span id="busuanzi_site_pv">--</span>',
            '[UV]' => '<span id="busuanzi_site_uv">--</span>',
            '[TODAY_PV]' => '<span id="busuanzi_today_site_pv">--</span>',
            '[TODAY_UV]' => '<span id="busuanzi_today_site_uv">--</span>',
            '[PAGE_PV]' => '<span id="busuanzi_page_pv">--</span>',
            '[PAGE_UV]' => '<span id="busuanzi_page_uv">--</span>',
            '[province]' => htmlspecialchars(qiwiGetCurrentVisitorLocationLabel(), ENT_QUOTES, 'UTF-8'),
        ];

        $parts = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $escaped = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
            $parts[] = '<p>' . strtr($escaped, $tokens) . '</p>';
        }

        return implode('', $parts);
    }
}

if (!function_exists('qiwiNormalizeSidebarEmailUrl')) {
    function qiwiNormalizeSidebarEmailUrl($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return stripos($value, 'mailto:') === 0 ? $value : 'mailto:' . $value;
    }
}

if (!function_exists('qiwiGetHomeHeroColorNames')) {
    function qiwiGetHomeHeroColorNames()
    {
        return ['caramel', 'red', 'orange', 'yellow', 'green', 'cyan', 'blue', 'purple'];
    }
}

if (!function_exists('qiwiRenderHomeHeroLine')) {
    function qiwiRenderHomeHeroLine($line)
    {
        $line = trim((string) $line);
        if ($line === '') {
            return '';
        }

        $colors = implode('|', qiwiGetHomeHeroColorNames());
        $pattern = '/\[(' . $colors . ')\]([\s\S]*?)\[\/\1\]/iu';
        $html = '';
        $offset = 0;

        if (preg_match_all($pattern, $line, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $index => $match) {
                $start = $match[1];
                $length = strlen($match[0]);
                if ($start > $offset) {
                    $html .= htmlspecialchars(substr($line, $offset, $start - $offset), ENT_QUOTES, 'UTF-8');
                }

                $color = strtolower($matches[1][$index][0]);
                $text = $matches[2][$index][0];
                $html .= '<span class="home-hero-accent home-hero-accent-' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . '">'
                    . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
                $offset = $start + $length;
            }
        }

        if ($offset < strlen($line)) {
            $html .= htmlspecialchars(substr($line, $offset), ENT_QUOTES, 'UTF-8');
        }

        return $html !== '' ? $html : htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('qiwiStripHomeHeroMarks')) {
    function qiwiStripHomeHeroMarks($line)
    {
        $colors = implode('|', qiwiGetHomeHeroColorNames());
        return trim(preg_replace('/\[(' . $colors . ')\]([\s\S]*?)\[\/\1\]/iu', '$2', (string) $line));
    }
}

if (!function_exists('qiwiGetHomeHeroItems')) {
    function qiwiGetHomeHeroItems($widget)
    {
        $raw = (string) qiwiGetOptionValue($widget, 'homeHeroLines', '');
        if (trim($raw) === '') {
            $raw = "把[caramel]生活[/caramel]写成笔记\n在[green]结构[/green]里寻找回声\n持续记录，[cyan]慢慢理解[/cyan]";
        }

        $items = [];
        foreach (preg_split('/\R/u', $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $html = qiwiRenderHomeHeroLine($line);
            if ($html === '') {
                continue;
            }

            $items[] = [
                'html' => $html,
                'text' => qiwiStripHomeHeroMarks($line),
            ];

            if (count($items) >= 16) {
                break;
            }
        }

        if (empty($items)) {
            $title = trim((string) qiwiGetOptionValue($widget, 'title', 'Qiwi'));
            $items[] = [
                'html' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
                'text' => $title,
            ];
        }

        return $items;
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

            $parts = array_map('trim', explode('|', $line, 3));
            $title = $parts[0];
            $target = isset($parts[1]) ? $parts[1] : '#';
            $icon = isset($parts[2]) ? qiwiSanitizeIconClass($parts[2]) : '';
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

if (!function_exists('qiwiNavigationUsesFontAwesome')) {
    function qiwiNavigationUsesFontAwesome($items)
    {
        foreach ((array) $items as $item) {
            if (!empty($item['icon'])) {
                return true;
            }

            if (!empty($item['children'])) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('qiwiGetSidebarSocialLinks')) {
    function qiwiGetSidebarSocialLinks($widget)
    {
        $config = trim((string) qiwiGetOptionValue($widget, 'sidebarSocialLinks', ''));
        $links = [];

        foreach (preg_split('/\r\n|\r|\n/', $config) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $parts = array_map('trim', explode('|', $line, 3));
            $title = isset($parts[0]) ? $parts[0] : '';
            $target = isset($parts[1]) ? $parts[1] : '';
            $icon = isset($parts[2]) ? qiwiSanitizeIconClass($parts[2]) : '';

            if ($title === '' || $target === '') {
                continue;
            }

            if (strpos($target, '@') !== false && !preg_match('/^[a-z][a-z0-9+.-]*:/i', $target)) {
                $target = qiwiNormalizeSidebarEmailUrl($target);
            }

            $resolved = qiwiResolveNavigationTarget($widget, $target);
            $links[] = [
                'title' => $title,
                'url' => $resolved['url'],
                'external' => $resolved['external'] && !preg_match('/^(mailto|tel):/i', $resolved['url']),
                'icon' => $icon,
                'kind' => '',
            ];

            if (count($links) >= 12) {
                break;
            }
        }

        return $links;
    }
}

if (!function_exists('qiwiSidebarSocialUsesFontAwesome')) {
    function qiwiSidebarSocialUsesFontAwesome($widget)
    {
        foreach (qiwiGetSidebarSocialLinks($widget) as $link) {
            if (!empty($link['icon'])) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('qiwiFormatJikeRelativeTime')) {
    function qiwiFormatJikeRelativeTime($timestamp)
    {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) {
            return '';
        }

        $diff = max(0, time() - $timestamp);

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
    function qiwiFormatPostRelativeTime($timestamp)
    {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) {
            return '';
        }

        $now = time();
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
        $default = 'mp';

        if (preg_match('/^([1-9][0-9]{4,11})@qq\.com$/i', $mail, $matches)) {
            $qqAvatar = 'https://q1.qlogo.cn/g?b=qq&nk=' . rawurlencode($matches[1]) . '&s=100';
            $default = rawurlencode($qqAvatar);
        }

        return 'https://gravatar.loli.net/avatar/' . md5($mail) . '?s=' . $size . '&d=' . $default;
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

        $cookieName = 'qiwi_post_viewed_' . $cid;
        if (isset($_COOKIE[$cookieName])) {
            return $currentViews;
        }

        $updatedViews = qiwiSetPostViews($cid, $currentViews + 1);
        setcookie($cookieName, '1', time() + 3600, '/');
        $_COOKIE[$cookieName] = '1';

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
            if ($excerpt === '') {
                $excerpt = qiwiFallbackJikeExcerpt($comment['text']);
            }

            if ($excerpt === '') {
                continue;
            }

            $items[] = [
                'coid' => (int) $comment['coid'],
                'excerpt' => $excerpt,
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
