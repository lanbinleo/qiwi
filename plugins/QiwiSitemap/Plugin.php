<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Qiwi companion sitemap and feed discovery plugin.
 *
 * @package QiwiSitemap
 * @author  MaxQiwi
 * @version 1.0.0
 * @link    https://www.maxqi.top/
 */
class QiwiSitemap_Plugin implements Typecho_Plugin_Interface
{
    private static $routes = array(
        array('name' => 'index', 'url' => '/sitemap.xml', 'action' => 'action'),
        array('name' => 'posts', 'url' => '/sitemap-posts.xml', 'action' => 'posts'),
        array('name' => 'pages', 'url' => '/sitemap-pages.xml', 'action' => 'pages'),
        array('name' => 'categories', 'url' => '/sitemap-categories.xml', 'action' => 'categories'),
        array('name' => 'tags', 'url' => '/sitemap-tags.xml', 'action' => 'tags'),
        array('name' => 'xsl', 'url' => '/sitemap.xsl', 'action' => 'xsl'),
        array('name' => 'robots', 'url' => '/robots.txt', 'action' => 'robots'),
    );

    public static function activate()
    {
        foreach (self::$routes as $route) {
            Helper::addRoute(
                'qiwi_sitemap_' . $route['name'] . '_route',
                $route['url'],
                'QiwiSitemap_Action',
                $route['action']
            );
        }

        Typecho_Plugin::factory('Widget_Archive')->header = array(__CLASS__, 'header');

        return _t('Qiwi Sitemap 已启用：/sitemap.xml、/robots.txt 和 RSS/Atom 发现链接已准备好。');
    }

    public static function deactivate()
    {
        foreach (self::$routes as $route) {
            Helper::removeRoute('qiwi_sitemap_' . $route['name'] . '_route');
        }
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
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

        $enableFeedDiscovery = new Typecho_Widget_Helper_Form_Element_Radio(
            'enableFeedDiscovery',
            array('1' => _t('启用'), '0' => _t('关闭')),
            '1',
            _t('RSS / Atom 发现链接'),
            _t('在页面 head 中补充 RSS、Atom 与 sitemap 发现链接，便于浏览器、阅读器和爬虫识别订阅入口。')
        );
        $form->addInput($enableFeedDiscovery);

        $avatarUrl = new Typecho_Widget_Helper_Form_Element_Text(
            'avatarUrl',
            null,
            null,
            _t('博客头像 URL'),
            _t('留空时会按 Qiwi 主题配置依次读取 sidebarProfileAvatar、aboutAvatar、logoUrl。仅用于 sitemap 浏览器可视化页面。')
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
        $atomUrl = self::normalUrl(self::optionValue($options, 'feedAtomUrl'));

        echo "\n" . '<link rel="sitemap" type="application/xml" title="' . $siteTitle . ' Sitemap" href="' . self::escapeHtml($sitemapUrl) . '">' . "\n";

        if ($rssUrl !== '') {
            echo '<link rel="alternate" type="application/rss+xml" title="' . $siteTitle . ' RSS" href="' . self::escapeHtml($rssUrl) . '">' . "\n";
        }

        if ($atomUrl !== '' && $atomUrl !== $rssUrl) {
            echo '<link rel="alternate" type="application/atom+xml" title="' . $siteTitle . ' Atom" href="' . self::escapeHtml($atomUrl) . '">' . "\n";
        }
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
        return isset($options->{$name}) ? trim((string) $options->{$name}) : '';
    }

    private static function routeUrl($path, $options)
    {
        return Typecho_Common::url($path, $options->index);
    }

    private static function normalUrl($url)
    {
        return trim((string) $url);
    }

    private static function escapeHtml($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
