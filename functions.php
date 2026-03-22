<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

function themeConfig($form)
{
    $logoUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'logoUrl',
        null,
        null,
        _t('站点 LOGO 地址'),
        _t('在这里填入一个图片 URL 地址, 以在网站标题前加上一个 LOGO')
    );

    $form->addInput($logoUrl);

    // Captcha Script 是否安装Geetest插件并启用 https://github.com/CairBin/typecho-plugin-geetest.git；单选题
    $enabledCaptcha = new Typecho_Widget_Helper_Form_Element_Radio(
        'enabledCaptcha',
        array(
            '1' => _t('启用'),
            '0' => _t('关闭')
        ),
        '0',
        _t('启用验证码'),
        _t('如果你已经安装并启用了 Geetest 插件，可以选择启用验证码功能<br>https://github.com/CairBin/typecho-plugin-geetest.git')
    );

    $form->addInput($enabledCaptcha);

    $sidebarBlock = new \Typecho\Widget\Helper\Form\Element\Checkbox(
        'sidebarBlock',
        [
            'ShowRecentPosts'    => _t('显示最新文章'),
            'ShowCategory'       => _t('显示分类'),
        ],
        ['ShowRecentPosts', 'ShowCategory'],
        _t('侧边栏显示')
    );

    $form->addInput($sidebarBlock->multiMode());

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

    // 即刻条展示位置
    $jikePosition = new Typecho_Widget_Helper_Form_Element_Radio(
        'jikePosition',
        array(
            'off'    => _t('关闭'),
            'top'    => _t('页面顶部（横跨内容区与侧边栏上方）'),
            'inline' => _t('文章列表内（嵌入文章列表顶部）'),
        ),
        'inline',
        _t('首页即刻条'),
        _t('在首页展示即刻/时间机器的最新动态。需要已发布一个使用"时间机器"模板的独立页面。')
    );
    $form->addInput($jikePosition);

    $jikeTimeMode = new Typecho_Widget_Helper_Form_Element_Radio(
        'jikeTimeMode',
        array(
            'absolute' => _t('纯日期'),
            'relative' => _t('相对时间'),
        ),
        'absolute',
        _t('即刻时间显示'),
        _t('纯日期显示为 MM-DD；相对时间支持“刚刚 / X分钟前 / X小时前 / X天前”，超过 3 天后自动回退为 MM-DD。')
    );
    $form->addInput($jikeTimeMode);

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

    $form->addInput($customCSS);
    $form->addInput($customJS);
    $form->addInput($trackingCode);
    $form->addInput($footerInfo);

    // === 友链配置 ===
    $friendsData = new Typecho_Widget_Helper_Form_Element_Textarea(
        'friendsData',
        null,
        null,
        _t('友链数据 (JSON格式)'),
        _t('在这里填入友链数据，格式为JSON。')
    );
    $form->addInput($friendsData);

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
    $isLatex = new Typecho_Widget_Helper_Form_Element_Radio('isLatex',
    array(1 => _t('启用'),
    0 => _t('关闭')),
    0, _t('LaTeX 渲染'), _t('默认关闭增加网页访问速度，如文章内存在LaTeX语法则需要启用'));

    // 设置文章简介
    $excerpt = new Typecho_Widget_Helper_Form_Element_Textarea('excerpt', null, null, _t('文章简介'), _t('在这里填写文章的简介，将在文章列表中显示，为空则默认摘录正文前200个字符'));

    // 设置头图URL
    $thumbnail = new Typecho_Widget_Helper_Form_Element_Text('thumbnail', null, null, _t('文章头图'), _t('在这里填写文章的头图URL地址'));

    // 是否展示头图（不展示，首页展示，文章页展示，都展示）
    $showThumbnail = new Typecho_Widget_Helper_Form_Element_Radio('showThumbnail',
        array(0 => _t('不展示'),
              3 => _t('都展示'),
              1 => _t('首页展示'),
              2 => _t('文章页展示')),
        3, _t('展示头图'), _t('是否在文章列表中展示头图'));

    // 是否置顶文章
    $isSticky = new Typecho_Widget_Helper_Form_Element_Radio('isSticky',
        array(1 => _t('是'),
              0 => _t('否')),
        0, _t('置顶文章'), _t('置顶的文章将在首页优先显示'));

    $layout->addItem($isLatex);
    $layout->addItem($excerpt);
    $layout->addItem($showThumbnail);
    $layout->addItem($thumbnail);
    $layout->addItem($isSticky);
}

if (!function_exists('qiwiExtractPlainText')) {
    function qiwiExtractPlainText($text)
    {
        if ($text === null || $text === '') {
            return '';
        }

        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);

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
            ->where('authorId = ?', $page['authorId'])
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
