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

    $navItems = new Typecho_Widget_Helper_Form_Element_Textarea(
        'navItems',
        null,
        null,
        _t('顶部导航配置'),
        _t("留空则自动显示所有独立页面。每行一个导航项：标题|链接|Font Awesome 图标类。二级菜单在行首加 -，例如：\n归档|template:page-archives.php|fa-solid fa-box-archive\n- 分类|template:page-categories.php|fa-solid fa-folder\n- 标签|template:page-tags.php|fa-solid fa-tags\n外链|https://example.com|fa-solid fa-arrow-up-right-from-square\n链接支持完整 URL、/path、slug、slug:about、page:about、template:page-tags.php。")
    );
    $form->addInput($navItems);

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

    $friendsSubtitle = new Typecho_Widget_Helper_Form_Element_Text('friendsSubtitle', null, null, _t('友链页副标题'), _t('使用“友链页面”模板时显示在页面标题下方；页面正文会显示在友链页底部。'));

    $navShow = new Typecho_Widget_Helper_Form_Element_Radio(
        'navShow',
        array(
            1 => _t('显示'),
            0 => _t('隐藏')
        ),
        1,
        _t('顶部导航栏展示'),
        _t('控制该独立页面是否出现在自动生成的顶部导航栏中。手动导航配置不受此项影响。')
    );

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
    $layout->addItem($friendsSubtitle);
    $layout->addItem($navShow);
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
            $next = preg_replace_callback('/\[fold(?:\s+title=(?:"([^"]*)"|\'([^\']*)\'|([^\]\s]+)))?\]([\s\S]*?)\[\/fold\]/iu', function ($matches) {
                $title = '';
                foreach ([1, 2, 3] as $index) {
                    if (isset($matches[$index]) && trim($matches[$index]) !== '') {
                        $title = trim($matches[$index]);
                        break;
                    }
                }

                $body = isset($matches[4]) ? $matches[4] : '';
                return trim($title . ' ' . $body);
            }, $text);

            if ($next === $text) {
                break;
            }

            $text = $next;
        }

        $text = preg_replace('/\[mark(?:\s+color=(["\']?)[a-zA-Z]+\1)?\]([\s\S]*?)\[\/mark\]/iu', '$2', $text);
        $text = preg_replace('/\[(' . $colors . ')\]([\s\S]*?)\[\/\1\]/iu', '$2', $text);
        $text = preg_replace('/\[\/?(?:mark|fold|' . $colors . ')(?:\s+[^\]]*)?\]/iu', '', $text);

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

if (!function_exists('qiwiRenderShortcodeSegment')) {
    function qiwiRenderShortcodeSegment($html)
    {
        $colors = 'red|orange|yellow|green|cyan|blue|purple';
        $foldOpening = '\[fold(?:\s+title=(?:"[^"]*"|\'[^\']*\'|[^\]\s]+))?\]';

        $html = preg_replace('/<p>\s*(' . $foldOpening . ')\s*<br\s*\/?>\s*([\s\S]*?)<\/p>/iu', '$1<p>$2</p>', $html);
        $html = preg_replace('/<p>\s*(' . $foldOpening . ')\s*<\/p>/iu', '$1', $html);
        $html = preg_replace('/<p>([\s\S]*?)<br\s*\/?>\s*(\[\/fold\])\s*<\/p>/iu', '<p>$1</p>$2', $html);
        $html = preg_replace('/<p>\s*(\[\/fold\])\s*<\/p>/iu', '$1', $html);

        for ($i = 0; $i < 4; $i++) {
            $next = preg_replace_callback('/\[fold(?:\s+title=(?:"([^"]*)"|\'([^\']*)\'|([^\]\s]+)))?\]([\s\S]*?)\[\/fold\]/iu', function ($matches) {
                $title = '';
                foreach ([1, 2, 3] as $index) {
                    if (isset($matches[$index]) && trim($matches[$index]) !== '') {
                        $title = trim($matches[$index]);
                        break;
                    }
                }

                if ($title === '') {
                    $title = '展开内容';
                }

                $body = isset($matches[4]) ? $matches[4] : '';
                return '<details class="qiwi-fold"><summary>' . htmlspecialchars(strip_tags($title), ENT_QUOTES, 'UTF-8') . '</summary><div class="qiwi-fold-body">' . $body . '</div></details>';
            }, $html);

            if ($next === $html) {
                break;
            }

            $html = $next;
        }

        $html = preg_replace('/<p>\s*(<details class="qiwi-fold"[\s\S]*?<\/details>)\s*<\/p>/iu', '$1', $html);

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
    function qiwiRenderShortcodes($html)
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

            $parts[$index] = qiwiRenderShortcodeSegment($part);
        }

        return implode('', $parts);
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

        $fieldRows = $db->fetchAll($db->select('cid', 'int_value', 'str_value')
            ->from($prefix . 'fields')
            ->where('name = ?', 'navShow'));

        foreach ($fieldRows as $row) {
            $rawValue = isset($row['int_value']) && $row['int_value'] !== null ? $row['int_value'] : $row['str_value'];
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

            if (!empty($item['children']) && qiwiNavigationUsesFontAwesome($item['children'])) {
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
