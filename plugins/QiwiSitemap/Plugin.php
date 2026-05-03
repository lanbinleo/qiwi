<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Qiwi companion sitemap and feed discovery plugin.
 *
 * @package QiwiSitemap
 * @author  MaxQiwi
 * @version 1.4.3
 * @link    https://www.maxqi.top/
 */
class QiwiSitemap_Plugin implements Typecho_Plugin_Interface
{
    private static $feedContext = null;

    private static $routes = array(
        array('name' => 'index', 'url' => '/sitemap.xml', 'action' => 'action'),
        array('name' => 'posts', 'url' => '/sitemap-posts.xml', 'action' => 'posts'),
        array('name' => 'pages', 'url' => '/sitemap-pages.xml', 'action' => 'pages'),
        array('name' => 'categories', 'url' => '/sitemap-categories.xml', 'action' => 'categories'),
        array('name' => 'tags', 'url' => '/sitemap-tags.xml', 'action' => 'tags'),
        array('name' => 'moments', 'url' => '/timemachine.xml', 'action' => 'moments'),
        array('name' => 'xsl', 'url' => '/sitemap.xsl', 'action' => 'xsl'),
        array('name' => 'robots', 'url' => '/robots.txt', 'action' => 'robots'),
    );

    private static $legacyRoutes = array(
        'sitemap_action_route',
        'sitemap_tags_route',
        'sitemap_category_route',
    );

    public static function activate()
    {
        self::removeRoutes();

        foreach (self::$routes as $route) {
            Helper::addRoute(
                'qiwi_sitemap_' . $route['name'] . '_route',
                $route['url'],
                'QiwiSitemap_Action',
                $route['action']
            );
        }

        Typecho_Plugin::factory('Widget_Archive')->header = array(__CLASS__, 'header');
        Typecho_Plugin::factory('Widget_Archive')->handleInit = array(__CLASS__, 'handleInit');
        Typecho_Plugin::factory('Widget_Archive')->feedItem = array(__CLASS__, 'feedItem');
        Typecho_Plugin::factory('Widget_Archive')->commentFeedItem = array(__CLASS__, 'commentFeedItem');

        return _t('Qiwi Sitemap 已启用：/sitemap.xml、/timemachine.xml、/robots.txt 和 RSS/Atom 发现链接已准备好。');
    }

    public static function deactivate()
    {
        self::removeRoutes();
    }

    private static function removeRoutes()
    {
        foreach (self::$legacyRoutes as $routeName) {
            Helper::removeRoute($routeName);
        }

        foreach (self::$routes as $route) {
            Helper::removeRoute('qiwi_sitemap_' . $route['name'] . '_route');
        }
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $linkSummary = new Typecho_Widget_Helper_Form_Element_Fake('qiwiSitemapLinks', '');
        $linkSummary->input->setAttribute('type', 'hidden');
        $linkSummary->label(_t('可用链接'));
        $linkSummary->description(self::linksDescription());
        $form->addInput($linkSummary);

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
            _t('为 RSS 频道补充 image，为 Atom 补充 icon/logo，并给订阅条目追加头像缩略图，帮助阅读器识别博主头像。')
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
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    public static function header($archive = null)
    {
        $settings = self::settings();
        if (self::setting($settings, 'enableFeedDiscovery', '1') !== '1') {
            return;
        }

        $options = Helper::options();
        $siteTitle = self::escapeHtml($options->title);
        $sitemapUrl = self::routeUrl('/sitemap.xml', $options);
        $rssUrl = self::normalUrl(self::optionValue($options, 'feedUrl'));
        $rss1Url = self::normalUrl(self::optionValue($options, 'feedRssUrl'));
        $atomUrl = self::normalUrl(self::optionValue($options, 'feedAtomUrl'));
        $momentsUrl = self::routeUrl('/timemachine.xml', $options);

        echo "\n" . '<link rel="sitemap" type="application/xml" title="' . $siteTitle . ' Sitemap" href="' . self::escapeHtml($sitemapUrl) . '">' . "\n";

        if ($rssUrl !== '') {
            echo '<link rel="alternate" type="application/rss+xml" title="' . $siteTitle . ' RSS" href="' . self::escapeHtml($rssUrl) . '">' . "\n";
        }

        if ($rss1Url !== '' && $rss1Url !== $rssUrl) {
            echo '<link rel="alternate" type="application/rdf+xml" title="' . $siteTitle . ' RSS 1.0" href="' . self::escapeHtml($rss1Url) . '">' . "\n";
        }

        if ($atomUrl !== '' && $atomUrl !== $rssUrl) {
            echo '<link rel="alternate" type="application/atom+xml" title="' . $siteTitle . ' Atom" href="' . self::escapeHtml($atomUrl) . '">' . "\n";
        }

        if (self::setting($settings, 'enableMomentsFeed', '1') === '1') {
            echo '<link rel="alternate" type="application/rss+xml" title="' . $siteTitle . ' 说说 RSS" href="' . self::escapeHtml($momentsUrl) . '">' . "\n";
        }
    }

    public static function handleInit($archive, $select)
    {
        $settings = self::settings();
        $parameter = $archive->parameter;
        if (empty($parameter) || $parameter->type !== 'feed') {
            return;
        }

        $options = Helper::options();
        $enableAvatar = self::setting($settings, 'enableFeedAvatar', '1') === '1';
        $enableShortcodes = self::setting($settings, 'enableFeedShortcodeCompat', '1') === '1';
        $avatarUrl = $enableAvatar ? self::avatarUrl($settings, $options) : '';

        if (!$enableShortcodes && ($avatarUrl === '')) {
            return;
        }

        self::$feedContext = array(
            'avatarUrl' => $avatarUrl,
            'title' => self::optionValue($options, 'title'),
            'homeUrl' => rtrim(self::optionValue($options, 'siteUrl'), '/') . '/',
            'feedType' => (string) $archive->feedType,
            'enableAvatar' => $enableAvatar && $avatarUrl !== '',
            'enableShortcodes' => $enableShortcodes,
        );

        ob_start(array(__CLASS__, 'filterFeedXml'));
    }

    public static function feedItem($feedType, $archive)
    {
        return self::feedAvatarSuffix($feedType);
    }

    public static function commentFeedItem($feedType, $comments)
    {
        return self::feedAvatarSuffix($feedType);
    }

    public static function filterFeedXml($xml)
    {
        if (self::$feedContext === null || trim($xml) === '') {
            return $xml;
        }

        if (!empty(self::$feedContext['enableShortcodes'])) {
            $xml = self::filterFeedShortcodes($xml);
        }

        if (empty(self::$feedContext['enableAvatar'])) {
            return $xml;
        }

        $avatarUrl = self::xml(self::$feedContext['avatarUrl']);
        $title = self::xml(self::$feedContext['title']);
        $homeUrl = self::xml(self::$feedContext['homeUrl']);
        $feedType = self::$feedContext['feedType'];

        if ($feedType === 'ATOM 1.0' && strpos($xml, '<icon>') === false) {
            return preg_replace_callback(
                '/(<feed\b[^>]*>\s*)/s',
                function ($matches) use ($avatarUrl) {
                    return $matches[1] . '<icon>' . $avatarUrl . '</icon>' . "\n" . '<logo>' . $avatarUrl . '</logo>' . "\n";
                },
                $xml,
                1
            );
        }

        if ($feedType === 'RSS 1.0' && strpos($xml, '<image rdf:') === false) {
            $xml = preg_replace_callback(
                '/(<description>.*?<\/description>\s*)/s',
                function ($matches) use ($avatarUrl) {
                    return $matches[1] . '<image rdf:resource="' . $avatarUrl . '" />' . "\n";
                },
                $xml,
                1
            );

            return preg_replace_callback(
                '/<\/rdf:RDF>\s*$/',
                function () use ($avatarUrl, $title, $homeUrl) {
                    return '<image rdf:about="' . $avatarUrl . '">' . "\n" .
                        '<title>' . $title . '</title>' . "\n" .
                        '<url>' . $avatarUrl . '</url>' . "\n" .
                        '<link>' . $homeUrl . '</link>' . "\n" .
                        '</image>' . "\n" .
                        '</rdf:RDF>';
                },
                $xml,
                1
            );
        }

        if ($feedType === 'RSS 2.0' && strpos($xml, '<image>') === false) {
            $image = '<image>' . "\n" .
                '<url>' . $avatarUrl . '</url>' . "\n" .
                '<title>' . $title . '</title>' . "\n" .
                '<link>' . $homeUrl . '</link>' . "\n" .
                '</image>' . "\n";

            return preg_replace_callback(
                '/(<channel>\s*)/s',
                function ($matches) use ($image) {
                    return $matches[1] . $image;
                },
                $xml,
                1
            );
        }

        return $xml;
    }

    private static function settings()
    {
        try {
            return Helper::options()->plugin('QiwiSitemap');
        } catch (Exception $e) {
            return new stdClass();
        }
    }

    private static function setting($settings, $name, $default)
    {
        return isset($settings->{$name}) && $settings->{$name} !== '' ? (string) $settings->{$name} : $default;
    }

    private static function optionValue($options, $name)
    {
        $value = $options->{$name};
        return $value !== null ? trim((string) $value) : '';
    }

    private static function routeUrl($path, $options)
    {
        return Typecho_Common::url($path, $options->index);
    }

    private static function feedAvatarSuffix($feedType)
    {
        $settings = self::settings();
        if (self::setting($settings, 'enableFeedAvatar', '1') !== '1') {
            return null;
        }

        $avatarUrl = self::avatarUrl($settings, Helper::options());
        if ($avatarUrl === '') {
            return null;
        }

        return '<media:thumbnail xmlns:media="http://search.yahoo.com/mrss/" url="' . self::xml($avatarUrl) . '" />' . "\n";
    }

    public static function renderFeedHtml($html)
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

            $parts[$index] = self::renderFeedHtmlSegment($part);
        }

        return implode('', $parts);
    }

    private static function filterFeedShortcodes($xml)
    {
        return preg_replace_callback('/<!\[CDATA\[([\s\S]*?)\]\]>/u', function ($matches) {
            return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', self::renderFeedHtml($matches[1])) . ']]>';
        }, $xml);
    }

    private static function renderFeedHtmlSegment($html)
    {
        $colors = 'red|orange|yellow|green|cyan|blue|purple';

        $html = preg_replace_callback('/<details\b[^>]*class=(["\'])[^"\']*\bqiwi-fold\b[^"\']*\1[^>]*>\s*<summary>([\s\S]*?)<\/summary>\s*<div\b[^>]*class=(["\'])[^"\']*\bqiwi-fold-body\b[^"\']*\3[^>]*>([\s\S]*?)<\/div>\s*<\/details>/iu', function ($matches) {
            $title = trim(strip_tags($matches[2]));
            $body = isset($matches[4]) ? $matches[4] : '';
            return self::feedBlock($title, $body);
        }, $html);

        for ($i = 0; $i < 4; $i++) {
            $next = preg_replace_callback('/\[fold([^\]]*)\]([\s\S]*?)\[\/fold\]/iu', function ($matches) {
                $attrs = self::parseShortcodeAttrs(isset($matches[1]) ? $matches[1] : '');
                $title = isset($attrs['title']) ? trim($attrs['title']) : '';
                return self::feedBlock($title, isset($matches[2]) ? $matches[2] : '');
            }, $html);

            if ($next === $html) {
                break;
            }

            $html = $next;
        }

        for ($i = 0; $i < 4; $i++) {
            $next = preg_replace_callback('/\[callout([^\]]*)\]([\s\S]*?)\[\/callout\]/iu', function ($matches) {
                $attrs = self::parseShortcodeAttrs(isset($matches[1]) ? $matches[1] : '');
                $title = isset($attrs['title']) ? trim($attrs['title']) : '';
                $body = isset($matches[2]) ? $matches[2] : '';
                return '<blockquote>' . ($title !== '' ? '<p><strong>' . self::escapeHtml(strip_tags($title)) . '</strong></p>' : '') . $body . '</blockquote>';
            }, $html);

            if ($next === $html) {
                break;
            }

            $html = $next;
        }

        $html = preg_replace_callback('/\[button\b([^\]]*)\]([\s\S]*?)\[\/button\]/iu', function ($matches) {
            $attrs = self::parseShortcodeAttrs(isset($matches[1]) ? $matches[1] : '');
            $href = self::safeUrl(isset($attrs['href']) ? $attrs['href'] : (isset($attrs['url']) ? $attrs['url'] : '#'));
            $label = trim($matches[2]) !== '' ? $matches[2] : self::escapeHtml($href);
            return '<a href="' . self::escapeHtml($href) . '">' . $label . '</a>';
        }, $html);

        for ($i = 0; $i < 4; $i++) {
            $next = preg_replace('/\[buttons(?:\s+[^\]]*)?\]([\s\S]*?)\[\/buttons\]/iu', '<div>$1</div>', $html);
            if ($next === $html) {
                break;
            }
            $html = $next;
        }

        $html = preg_replace('/\[badge(?:\s+[^\]]*)?\]([\s\S]*?)\[\/badge\]/iu', '<strong>$1</strong>', $html);

        $html = preg_replace_callback('/\[mark(?:\s+color=(["\']?)([a-zA-Z]+)\1)?\]([\s\S]*?)\[\/mark\]/iu', function ($matches) {
            $color = self::shortcodeColor(isset($matches[2]) && $matches[2] !== '' ? $matches[2] : 'yellow', true);
            return '<span style="background-color:' . $color . ';">' . $matches[3] . '</span>';
        }, $html);

        $html = preg_replace_callback('/\[(' . $colors . ')\]([\s\S]*?)\[\/\1\]/iu', function ($matches) {
            return '<span style="color:' . self::shortcodeColor($matches[1], false) . ';">' . $matches[2] . '</span>';
        }, $html);

        $html = preg_replace_callback('/<span\b([^>]*class=(["\'])[^"\']*\bqiwi-text-(' . $colors . ')\b[^"\']*\2[^>]*)>/iu', function ($matches) {
            return '<span style="color:' . self::shortcodeColor($matches[3], false) . ';">';
        }, $html);

        $html = preg_replace_callback('/<span\b([^>]*class=(["\'])[^"\']*\bqiwi-mark-(' . $colors . ')\b[^"\']*\2[^>]*)>/iu', function ($matches) {
            return '<span style="background-color:' . self::shortcodeColor($matches[3], true) . ';">';
        }, $html);

        $html = preg_replace('/\[\/?(?:mark|fold|badge|button|buttons|callout|' . $colors . ')(?:\s+[^\]]*)?\]/iu', '', $html);

        return $html;
    }

    private static function feedBlock($title, $body)
    {
        $title = trim(strip_tags((string) $title));
        $titleHtml = $title !== '' ? '<p><strong>' . self::escapeHtml($title) . '</strong></p>' : '';
        return '<div>' . $titleHtml . '<div>' . $body . '</div></div>';
    }

    private static function parseShortcodeAttrs($text)
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $attrs = array();
        if ($text === '') {
            return $attrs;
        }

        if (preg_match_all('/([a-zA-Z][a-zA-Z0-9_-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s\]]+))/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = strtolower($match[1]);
                $value = '';
                foreach (array(2, 3, 4) as $index) {
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

    private static function safeUrl($url)
    {
        $url = trim(html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') {
            return '#';
        }

        return preg_match('/^(https?:\/\/|mailto:|tel:|\/|#)/i', $url) ? $url : '#';
    }

    private static function shortcodeColor($color, $isBackground)
    {
        $map = array(
            'red' => $isBackground ? '#fde2e2' : '#d64545',
            'orange' => $isBackground ? '#fdebd7' : '#d97a20',
            'yellow' => $isBackground ? '#fff2bf' : '#a87900',
            'green' => $isBackground ? '#dff3e5' : '#2f8f4e',
            'cyan' => $isBackground ? '#d9f3f5' : '#168c96',
            'blue' => $isBackground ? '#dfeaff' : '#286bd6',
            'purple' => $isBackground ? '#ede5ff' : '#7b55c7',
        );

        $color = strtolower(trim((string) $color));
        return isset($map[$color]) ? $map[$color] : ($isBackground ? $map['yellow'] : $map['blue']);
    }

    private static function avatarUrl($settings, $options)
    {
        $settingsAvatar = self::setting($settings, 'avatarUrl', '');
        if ($settingsAvatar !== '') {
            return $settingsAvatar;
        }

        foreach (array('sidebarProfileAvatar', 'aboutAvatar', 'logoUrl') as $name) {
            $value = self::optionValue($options, $name);
            if ($value !== '') {
                return $value;
            }
        }

        return 'https://gravatar.loli.net/avatar/default?s=160&d=mp';
    }

    private static function linksDescription()
    {
        $options = Helper::options();
        $links = array(
            _t('RSS 2.0') => self::optionValue($options, 'feedUrl'),
            _t('RSS 1.0') => self::optionValue($options, 'feedRssUrl'),
            _t('Atom 1.0') => self::optionValue($options, 'feedAtomUrl'),
            _t('评论 RSS 2.0') => self::optionValue($options, 'commentsFeedUrl'),
            _t('评论 RSS 1.0') => self::optionValue($options, 'commentsFeedRssUrl'),
            _t('评论 Atom 1.0') => self::optionValue($options, 'commentsFeedAtomUrl'),
            _t('说说 RSS') => self::routeUrl('/timemachine.xml', $options),
            _t('Sitemap') => self::routeUrl('/sitemap.xml', $options),
            _t('文章 Sitemap') => self::routeUrl('/sitemap-posts.xml', $options),
            _t('页面 Sitemap') => self::routeUrl('/sitemap-pages.xml', $options),
            _t('分类 Sitemap') => self::routeUrl('/sitemap-categories.xml', $options),
            _t('标签 Sitemap') => self::routeUrl('/sitemap-tags.xml', $options),
            _t('robots.txt') => self::routeUrl('/robots.txt', $options),
        );

        $items = array();
        foreach ($links as $label => $url) {
            if ($url === '') {
                continue;
            }

            $items[] = '<a href="' . self::escapeHtml($url) . '" target="_blank" rel="noopener noreferrer">' . self::escapeHtml($label) . '</a>';
        }

        return _t('当前可用订阅与索引入口：') . implode(' · ', $items);
    }

    private static function normalUrl($url)
    {
        return trim((string) $url);
    }

    private static function xml($value)
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function escapeHtml($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
