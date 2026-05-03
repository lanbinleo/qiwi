<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class QiwiSitemap_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        if (!$this->boolOption('enableSitemap', true)) {
            $this->notFound();
            return;
        }

        $entries = array();

        if ($this->boolOption('enablePosts', true)) {
            $entries[] = array(
                'loc' => $this->routeUrl('/sitemap-posts.xml'),
                'lastmod' => $this->latestContentTime('post'),
            );
        }

        if ($this->boolOption('enablePages', true)) {
            $entries[] = array(
                'loc' => $this->routeUrl('/sitemap-pages.xml'),
                'lastmod' => $this->latestContentTime('page'),
            );
        }

        if ($this->boolOption('enableCategories', true)) {
            $entries[] = array(
                'loc' => $this->routeUrl('/sitemap-categories.xml'),
                'lastmod' => $this->latestTaxonomyTime('category'),
            );
        }

        if ($this->boolOption('enableTags', true)) {
            $entries[] = array(
                'loc' => $this->routeUrl('/sitemap-tags.xml'),
                'lastmod' => $this->latestTaxonomyTime('tag'),
            );
        }

        $xml = $this->xmlHeader();
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($entries as $entry) {
            $xml .= "\t<sitemap>\n";
            $xml .= "\t\t<loc>" . $this->xml($entry['loc']) . "</loc>\n";
            if (!empty($entry['lastmod'])) {
                $xml .= "\t\t<lastmod>" . $this->xml($this->dateForSitemap($entry['lastmod'])) . "</lastmod>\n";
            }
            $xml .= "\t</sitemap>\n";
        }
        $xml .= '</sitemapindex>';

        $this->outputXml($xml);
    }

    public function posts()
    {
        if (!$this->boolOption('enableSitemap', true) || !$this->boolOption('enablePosts', true)) {
            $this->notFound();
            return;
        }

        $this->outputUrlSet($this->contentNodes('post'));
    }

    public function pages()
    {
        if (!$this->boolOption('enableSitemap', true) || !$this->boolOption('enablePages', true)) {
            $this->notFound();
            return;
        }

        $this->outputUrlSet($this->contentNodes('page'));
    }

    public function categories()
    {
        if (!$this->boolOption('enableSitemap', true) || !$this->boolOption('enableCategories', true)) {
            $this->notFound();
            return;
        }

        $this->outputUrlSet($this->taxonomyNodes('category'));
    }

    public function tags()
    {
        if (!$this->boolOption('enableSitemap', true) || !$this->boolOption('enableTags', true)) {
            $this->notFound();
            return;
        }

        $this->outputUrlSet($this->taxonomyNodes('tag'));
    }

    public function robots()
    {
        if (!$this->boolOption('enableRobots', true)) {
            $this->notFound();
            return;
        }

        $lines = array(
            'User-agent: *',
            'Allow: /',
            'Sitemap: ' . $this->routeUrl('/sitemap.xml'),
        );

        $feedUrl = $this->feedUrl();
        if ($feedUrl !== '') {
            $lines[] = '# RSS: ' . $feedUrl;
        }

        $this->outputText(implode("\n", $lines) . "\n");
    }

    public function xsl()
    {
        if (!$this->boolOption('enableXsl', true)) {
            $this->notFound();
            return;
        }

        $options = $this->siteOptions();
        $title = $this->xml($this->optionValue($options, 'title'));
        $description = $this->xml($this->optionValue($options, 'description'));
        $homeUrl = $this->xml(rtrim($this->optionValue($options, 'siteUrl'), '/') . '/');
        $feedUrl = $this->xml($this->feedUrl());
        $avatarUrl = $this->xml($this->avatarUrl());

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $xml .= '<xsl:output method="html" encoding="UTF-8" doctype-system="about:legacy-compat"/>' . "\n";
        $xml .= '<xsl:template match="/">' . "\n";
        $xml .= '<html lang="zh-CN"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/><title>' . $title . ' Sitemap</title>';
        $xml .= '<style>
            :root{color-scheme:light dark;--bg:#f7f4ef;--card:#fffdf8;--text:#2b2925;--muted:#746f66;--line:#ded7cc;--accent:#2f7d68;--accent-2:#b64b40}
            @media (prefers-color-scheme:dark){:root{--bg:#181715;--card:#211f1b;--text:#f2eee7;--muted:#b9afa2;--line:#37322b;--accent:#7fc7ad;--accent-2:#e28b7e}}
            *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:16px/1.65 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
            main{width:min(1120px,calc(100% - 32px));margin:0 auto;padding:44px 0}
            header{display:flex;align-items:center;gap:18px;margin-bottom:28px}.avatar{width:68px;height:68px;border-radius:18px;object-fit:cover;border:1px solid var(--line);background:var(--card)}
            h1{margin:0;font-size:clamp(28px,4vw,44px);line-height:1.12;letter-spacing:0}p{margin:.35rem 0;color:var(--muted)}
            nav{display:flex;flex-wrap:wrap;gap:10px;margin:20px 0 28px}a{color:var(--accent);text-decoration:none}a:hover{text-decoration:underline}
            .pill{display:inline-flex;align-items:center;min-height:36px;padding:0 14px;border:1px solid var(--line);border-radius:999px;background:var(--card);color:var(--text)}
            .panel{overflow:hidden;border:1px solid var(--line);border-radius:8px;background:var(--card);box-shadow:0 12px 32px rgba(0,0,0,.05)}
            table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{padding:13px 16px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
            th{font-size:13px;color:var(--muted);font-weight:700;text-transform:uppercase}tr:last-child td{border-bottom:0}.loc{word-break:break-all}.date{width:190px;color:var(--muted);white-space:nowrap}
            .empty{padding:24px;color:var(--muted)}@media (max-width:640px){main{width:min(100% - 20px,1120px);padding:26px 0}header{align-items:flex-start}.avatar{width:54px;height:54px;border-radius:14px}th,td{padding:11px 12px}.date{width:118px;white-space:normal}}
        </style></head><body><main>';
        $xml .= '<header>';
        if ($avatarUrl !== '') {
            $xml .= '<img class="avatar" src="' . $avatarUrl . '" alt=""/>';
        }
        $xml .= '<div><h1>' . $title . ' Sitemap</h1>';
        if ($description !== '') {
            $xml .= '<p>' . $description . '</p>';
        }
        $xml .= '</div></header><nav><a class="pill" href="' . $homeUrl . '">博客首页</a>';
        if ($feedUrl !== '') {
            $xml .= '<a class="pill" href="' . $feedUrl . '">RSS 订阅</a>';
        }
        $xml .= '</nav>';
        $xml .= '<xsl:choose>';
        $xml .= '<xsl:when test="sitemap:sitemapindex"><div class="panel"><table><thead><tr><th>Sitemap</th><th class="date">Last Modified</th></tr></thead><tbody><xsl:for-each select="sitemap:sitemapindex/sitemap:sitemap"><tr><td class="loc"><a href="{sitemap:loc}"><xsl:value-of select="sitemap:loc"/></a></td><td class="date"><xsl:value-of select="sitemap:lastmod"/></td></tr></xsl:for-each></tbody></table></div></xsl:when>';
        $xml .= '<xsl:when test="sitemap:urlset"><div class="panel"><table><thead><tr><th>URL</th><th class="date">Last Modified</th></tr></thead><tbody><xsl:for-each select="sitemap:urlset/sitemap:url"><tr><td class="loc"><a href="{sitemap:loc}"><xsl:value-of select="sitemap:loc"/></a></td><td class="date"><xsl:value-of select="sitemap:lastmod"/></td></tr></xsl:for-each></tbody></table></div></xsl:when>';
        $xml .= '<xsl:otherwise><div class="panel"><div class="empty">没有可显示的 sitemap 数据。</div></div></xsl:otherwise>';
        $xml .= '</xsl:choose></main></body></html>' . "\n";
        $xml .= '</xsl:template></xsl:stylesheet>';

        $this->outputXml($xml, 'text/xsl; charset=UTF-8');
    }

    private function outputUrlSet(array $nodes)
    {
        $xml = $this->xmlHeader();
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($nodes as $node) {
            $xml .= "\t<url>\n";
            $xml .= "\t\t<loc>" . $this->xml($node['loc']) . "</loc>\n";
            if (!empty($node['lastmod'])) {
                $xml .= "\t\t<lastmod>" . $this->xml($this->dateForSitemap($node['lastmod'])) . "</lastmod>\n";
            }
            $xml .= "\t</url>\n";
        }
        $xml .= '</urlset>';

        $this->outputXml($xml);
    }

    private function contentNodes($type)
    {
        $db = Typecho_Db::get();
        $options = $this->siteOptions();
        $excluded = $this->excludedCids();
        $select = $db->select()->from('table.contents')
            ->where('table.contents.type = ?', $type)
            ->where('table.contents.status = ?', 'publish')
            ->where('(table.contents.password IS NULL OR table.contents.password = ?)', '')
            ->where('table.contents.created < ?', $options->gmtTime)
            ->order('table.contents.modified', Typecho_Db::SORT_DESC)
            ->order('table.contents.created', Typecho_Db::SORT_DESC);

        if (!empty($excluded)) {
            $select->where('table.contents.cid NOT IN (' . implode(',', $excluded) . ')');
        }

        $rows = $db->fetchAll($select);
        $nodes = array();
        foreach ($rows as $row) {
            $url = $this->contentUrl($row);
            if ($url === '') {
                continue;
            }

            $nodes[] = array(
                'loc' => $url,
                'lastmod' => $this->contentLastmod($row),
            );
        }

        return $nodes;
    }

    private function taxonomyNodes($type)
    {
        $db = Typecho_Db::get();
        $select = $db->select()->from('table.metas')
            ->where('table.metas.type = ?', $type)
            ->order($type === 'tag' ? 'table.metas.count' : 'table.metas.order', Typecho_Db::SORT_DESC)
            ->order('table.metas.mid', Typecho_Db::SORT_ASC);

        $rows = $db->fetchAll($select);
        $nodes = array();
        foreach ($rows as $row) {
            $url = $this->taxonomyUrl($row);
            if ($url === '') {
                continue;
            }

            $nodes[] = array(
                'loc' => $url,
                'lastmod' => $this->latestTaxonomyItemTime($row['mid']),
            );
        }

        return $nodes;
    }

    private function contentUrl(array $row)
    {
        $type = isset($row['type']) ? $row['type'] : '';
        if ($type === '' || Typecho_Router::get($type) === null) {
            return '';
        }

        if ($type === 'post') {
            $categories = $this->categorySlugs($row['cid']);
            if (!empty($categories)) {
                $row['category'] = rawurlencode($categories[0]);
            }
        }

        if (isset($row['slug'])) {
            $row['slug'] = rawurlencode($row['slug']);
        }

        $date = new Typecho_Date($row['created']);
        $row['date'] = $date;
        $row['year'] = $date->year;
        $row['month'] = $date->month;
        $row['day'] = $date->day;

        return Typecho_Common::url(Typecho_Router::url($type, $row), $this->siteOptions()->index);
    }

    private function taxonomyUrl(array $row)
    {
        $type = isset($row['type']) ? $row['type'] : '';
        if ($type === '' || Typecho_Router::get($type) === null) {
            return '';
        }

        if (isset($row['slug'])) {
            $row['slug'] = rawurlencode($row['slug']);
        }

        return Typecho_Common::url(Typecho_Router::url($type, $row), $this->siteOptions()->index);
    }

    private function categorySlugs($cid)
    {
        $db = Typecho_Db::get();
        $rows = $db->fetchAll($db->select()->from('table.metas')
            ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
            ->where('table.relationships.cid = ?', $cid)
            ->where('table.metas.type = ?', 'category')
            ->order('table.metas.order', Typecho_Db::SORT_ASC)
            ->order('table.metas.mid', Typecho_Db::SORT_ASC));

        $slugs = array();
        foreach ($rows as $row) {
            if (isset($row['slug']) && $row['slug'] !== '') {
                $slugs[] = $row['slug'];
            }
        }

        return $slugs;
    }

    private function latestContentTime($type)
    {
        $rows = $this->contentNodes($type);
        $latest = 0;
        foreach ($rows as $row) {
            if (!empty($row['lastmod'])) {
                $latest = max($latest, (int) $row['lastmod']);
            }
        }

        return $latest > 0 ? $latest : null;
    }

    private function latestTaxonomyTime($type)
    {
        $rows = $this->taxonomyNodes($type);
        $latest = 0;
        foreach ($rows as $row) {
            if (!empty($row['lastmod'])) {
                $latest = max($latest, (int) $row['lastmod']);
            }
        }

        return $latest > 0 ? $latest : null;
    }

    private function latestTaxonomyItemTime($mid)
    {
        $db = Typecho_Db::get();
        $options = $this->siteOptions();
        $excluded = $this->excludedCids();
        $select = $db->select()->from('table.contents')
            ->join('table.relationships', 'table.relationships.cid = table.contents.cid')
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.status = ?', 'publish')
            ->where('(table.contents.password IS NULL OR table.contents.password = ?)', '')
            ->where('table.contents.created < ?', $options->gmtTime)
            ->where('table.relationships.mid = ?', $mid)
            ->order('table.contents.modified', Typecho_Db::SORT_DESC)
            ->order('table.contents.created', Typecho_Db::SORT_DESC)
            ->limit(1);

        if (!empty($excluded)) {
            $select->where('table.contents.cid NOT IN (' . implode(',', $excluded) . ')');
        }

        $row = $db->fetchRow($select);
        return $row ? $this->contentLastmod($row) : null;
    }

    private function contentLastmod(array $row)
    {
        $created = isset($row['created']) ? (int) $row['created'] : 0;
        $modified = isset($row['modified']) ? (int) $row['modified'] : 0;

        return max($created, $modified);
    }

    private function xmlHeader()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        if ($this->boolOption('enableXsl', true)) {
            $xml .= '<?xml-stylesheet type="text/xsl" href="' . $this->xml($this->routeUrl('/sitemap.xsl')) . '"?>' . "\n";
        }

        return $xml;
    }

    private function xml($value)
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function dateForSitemap($timestamp)
    {
        return date('c', (int) $timestamp);
    }

    private function avatarUrl()
    {
        $settingsAvatar = trim($this->option('avatarUrl', ''));
        if ($settingsAvatar !== '') {
            return $settingsAvatar;
        }

        $options = $this->siteOptions();
        foreach (array('sidebarProfileAvatar', 'aboutAvatar', 'logoUrl') as $name) {
            $value = $this->optionValue($options, $name);
            if ($value !== '') {
                return $value;
            }
        }

        return 'https://gravatar.loli.net/avatar/default?s=160&d=mp';
    }

    private function feedUrl()
    {
        $options = $this->siteOptions();
        $feedUrl = $this->optionValue($options, 'feedUrl');
        if ($feedUrl !== '') {
            return $feedUrl;
        }

        $siteUrl = rtrim($this->optionValue($options, 'siteUrl'), '/');
        return $siteUrl !== '' ? $siteUrl . '/feed/' : '';
    }

    private function routeUrl($path)
    {
        return Typecho_Common::url($path, $this->siteOptions()->index);
    }

    private function excludedCids()
    {
        $raw = $this->option('excludedCids', '');
        if ($raw === '') {
            return array();
        }

        $items = preg_split('/[\s,，]+/', $raw);
        $ids = array();
        foreach ($items as $item) {
            $id = (int) $item;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private function boolOption($name, $default)
    {
        return $this->option($name, $default ? '1' : '0') === '1';
    }

    private function option($name, $default)
    {
        $settings = $this->pluginOptions();
        return isset($settings->{$name}) && $settings->{$name} !== '' ? (string) $settings->{$name} : $default;
    }

    private function optionValue($options, $name)
    {
        $value = $options->{$name};
        return $value !== null ? trim((string) $value) : '';
    }

    private function pluginOptions()
    {
        try {
            return Helper::options()->plugin('QiwiSitemap');
        } catch (Exception $e) {
            return new stdClass();
        }
    }

    private function siteOptions()
    {
        return Typecho_Widget::widget('Widget_Options');
    }

    private function outputXml($xml, $contentType = 'application/xml; charset=UTF-8')
    {
        header('Content-Type: ' . $contentType);
        echo $xml;
    }

    private function outputText($text)
    {
        header('Content-Type: text/plain; charset=UTF-8');
        echo $text;
    }

    private function notFound()
    {
        if (!headers_sent()) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
            header('Content-Type: text/plain; charset=UTF-8');
        }
        echo "404 Not Found\n";
    }
}
