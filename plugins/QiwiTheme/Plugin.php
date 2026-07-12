<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Qiwi theme companion plugin.
 *
 * @package QiwiTheme
 * @author  Leo 里奥
 * @version 2.0.0
 * @link    https://bboreo.com/
 */
class QiwiTheme_Plugin implements Typecho_Plugin_Interface
{
    const TABLE = 'qiwi_threads';
    const MOMENT_LIKE_TABLE = 'qiwi_moment_likes';
    const POST_LIKE_TABLE = 'qiwi_post_likes';
    const IP_LOCATION_TABLE = 'qiwi_ip_locations';
    const SETTINGS_PANEL = 'QiwiTheme/page/settings.php';

    public static function activate()
    {
        self::installTable();
        self::installExternalLinkTable();
        self::installMomentLikeTable();
        self::installPostLikeTable();
        self::installIpLocationTable();
        Helper::removeAction('qiwi-thread-tools');
        Helper::removeRoute('qiwi_theme_goto_route');
        Helper::removePanel(1, self::SETTINGS_PANEL);
        Helper::addAction('qiwi-theme', 'QiwiTheme_Action');
        Helper::addRoute('qiwi_theme_goto_route', '/goto', 'QiwiTheme_Action', 'goto');
        Helper::addPanel(1, self::SETTINGS_PANEL, 'Qiwi 设置', '快速进入 Qiwi 主题设置', 'administrator');
        Typecho_Plugin::factory('admin/header.php')->header = array(__CLASS__, 'adminHeader');
        Typecho_Plugin::factory('Widget\Base\Metas')->filter = array(__CLASS__, 'metaFilter');
        Typecho_Plugin::factory('Widget_Feedback')->comment = array(__CLASS__, 'cacheCommentIpLocation');
        return _t('Qiwi Theme 伴生插件已启用，Thread 数据表、后台增强接口、主题设置面板入口、说说点赞、文章点赞、IP 归属地与外链点击统计已准备好。');
    }

    public static function deactivate()
    {
        Helper::removeAction('qiwi-theme');
        Helper::removeRoute('qiwi_theme_goto_route');
        Helper::removePanel(1, self::SETTINGS_PANEL);
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $info = new Typecho_Widget_Helper_Form_Element_Fake('qiwiThemeInfo', '');
        $info->input->setAttribute('type', 'hidden');
        $info->label(_t('说明'));
        $info->description(_t('Qiwi 主题伴生插件。当前提供 thread-* 文集编辑器、Thread 数据存储、文章选择接口、说说点赞、文章点赞、IP 归属地与外链点击统计。'));
        $form->addInput($info);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    public static function adminHeader($header)
    {
        $script = isset($_SERVER['SCRIPT_NAME']) ? strtolower(basename($_SERVER['SCRIPT_NAME'])) : '';
        self::ensureSettingsPanel();
        if ($script !== 'category.php') {
            return $header;
        }

        Typecho_Widget::widget('Widget_Options')->to($options);
        Typecho_Widget::widget('Widget_Security')->to($security);
        $assetBase = rtrim($options->siteUrl, '/') . '/assets/';
        $css = htmlspecialchars($assetBase . 'css/admin-config.css', ENT_QUOTES, 'UTF-8');
        $js = htmlspecialchars($assetBase . 'js/admin-config.js', ENT_QUOTES, 'UTF-8');
        $fa = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css';
        $mid = isset($_GET['mid']) ? (int) $_GET['mid'] : 0;
        $config = array(
            'mid' => $mid,
            'readEndpoint' => $security->getIndex('/action/qiwi-theme?do=read-thread'),
            'saveEndpoint' => $security->getIndex('/action/qiwi-theme?do=save-thread'),
            'postsEndpoint' => $security->getIndex('/action/qiwi-theme?do=posts'),
        );
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return $header
            . '<link rel="stylesheet" href="' . $fa . '" crossorigin="anonymous" referrerpolicy="no-referrer">'
            . '<link rel="stylesheet" href="' . $css . '">'
            . '<script>window.QIWI_THEME_TOOLS=' . $json . ';window.QIWI_THREAD_TOOLS=window.QIWI_THEME_TOOLS;</script>'
            . '<script defer src="' . $js . '"></script>';
    }

    private static function ensureSettingsPanel()
    {
        try {
            $panelTable = self::decodeTypechoTableOption(Helper::options()->panelTable);
            $files = isset($panelTable['file']) && is_array($panelTable['file']) ? $panelTable['file'] : array();
            if (in_array(urlencode(self::SETTINGS_PANEL), $files, true)) {
                return;
            }

            Helper::addPanel(1, self::SETTINGS_PANEL, 'Qiwi 设置', '快速进入 Qiwi 主题设置', 'administrator');
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
    }

    private static function decodeTypechoTableOption($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return array();
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $decoded = @unserialize($value);
        return is_array($decoded) ? $decoded : array();
    }

    public static function metaFilter($value, $widget)
    {
        if (!is_array($value)
            || !isset($value['type'], $value['mid'], $value['slug'])
            || $value['type'] !== 'category'
            || strpos((string) $value['slug'], 'thread-') !== 0) {
            return $value;
        }

        try {
            $request = $widget->request;
            if (!$request
                || !$request->isPost()
                || (!$request->is('do=insert') && !$request->is('do=update'))) {
                return $value;
            }

            $data = trim((string) $request->get('qiwiThreadData', ''));
            $decoded = json_decode($data, true);
            if ($data === ''
                || !is_array($decoded)
                || !isset($decoded['schema'])
                || $decoded['schema'] !== 'qiwi-thread') {
                return $value;
            }

            self::saveThreadData((int) $value['mid'], $data);
        } catch (Exception $e) {
            return $value;
        } catch (Throwable $e) {
            return $value;
        }

        return $value;
    }

    public static function tableName()
    {
        $db = Typecho_Db::get();
        return $db->getPrefix() . self::TABLE;
    }

    public static function installTable()
    {
        $db = Typecho_Db::get();
        $table = self::tableName();
        $adapter = strtolower(get_class($db->getAdapter()));

        if (strpos($adapter, 'sqlite') !== false) {
            $sql = 'CREATE TABLE IF NOT EXISTS "' . $table . '" (
                "mid" INTEGER NOT NULL PRIMARY KEY,
                "data" TEXT NOT NULL,
                "created" INTEGER NOT NULL DEFAULT 0,
                "modified" INTEGER NOT NULL DEFAULT 0
            )';
        } else {
            $sql = 'CREATE TABLE IF NOT EXISTS `' . $table . '` (
                `mid` int(10) unsigned NOT NULL,
                `data` longtext NOT NULL,
                `created` int(10) unsigned NOT NULL DEFAULT 0,
                `modified` int(10) unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (`mid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        }

        $db->query($sql);
    }

    public static function externalLinkTableName()
    {
        $db = Typecho_Db::get();
        return $db->getPrefix() . 'qiwi_external_links';
    }

    public static function momentLikeTableName()
    {
        $db = Typecho_Db::get();
        return $db->getPrefix() . self::MOMENT_LIKE_TABLE;
    }

    public static function postLikeTableName()
    {
        $db = Typecho_Db::get();
        return $db->getPrefix() . self::POST_LIKE_TABLE;
    }

    public static function ipLocationTableName()
    {
        $db = Typecho_Db::get();
        return $db->getPrefix() . self::IP_LOCATION_TABLE;
    }

    public static function installExternalLinkTable()
    {
        try {
            $db = Typecho_Db::get();
            $table = self::externalLinkTableName();
            $adapter = strtolower(get_class($db->getAdapter()));

            if (strpos($adapter, 'sqlite') !== false) {
                $sql = 'CREATE TABLE IF NOT EXISTS "' . $table . '" (
                    "id" INTEGER PRIMARY KEY AUTOINCREMENT,
                    "url_hash" varchar(40) NOT NULL,
                    "url" TEXT NOT NULL,
                    "host" varchar(255) NOT NULL DEFAULT "",
                    "source" TEXT,
                    "referer" TEXT,
                    "ip" varchar(64) NOT NULL DEFAULT "",
                    "user_agent" TEXT,
                    "clicked" INTEGER NOT NULL DEFAULT 0,
                    "created" INTEGER NOT NULL DEFAULT 0
                )';
            } else {
                $sql = 'CREATE TABLE IF NOT EXISTS `' . $table . '` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `url_hash` varchar(40) NOT NULL,
                    `url` text NOT NULL,
                    `host` varchar(255) NOT NULL DEFAULT "",
                    `source` text,
                    `referer` text,
                    `ip` varchar(64) NOT NULL DEFAULT "",
                    `user_agent` text,
                    `clicked` int(10) unsigned NOT NULL DEFAULT 0,
                    `created` int(10) unsigned NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    KEY `url_hash` (`url_hash`),
                    KEY `host` (`host`),
                    KEY `clicked` (`clicked`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
            }

            $db->query($sql);
            return true;
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function installMomentLikeTable()
    {
        try {
            $db = Typecho_Db::get();
            $table = self::momentLikeTableName();
            $adapter = strtolower(get_class($db->getAdapter()));

            if (strpos($adapter, 'sqlite') !== false) {
                $sql = 'CREATE TABLE IF NOT EXISTS "' . $table . '" (
                    "id" INTEGER PRIMARY KEY AUTOINCREMENT,
                    "coid" INTEGER NOT NULL,
                    "identity_hash" varchar(64) NOT NULL,
                    "identity_type" varchar(16) NOT NULL DEFAULT "cookie",
                    "user_id" INTEGER NOT NULL DEFAULT 0,
                    "author" varchar(200) NOT NULL DEFAULT "",
                    "mail_hash" varchar(64) NOT NULL DEFAULT "",
                    "created" INTEGER NOT NULL DEFAULT 0,
                    UNIQUE ("coid", "identity_hash")
                )';
            } else {
                $sql = 'CREATE TABLE IF NOT EXISTS `' . $table . '` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `coid` int(10) unsigned NOT NULL,
                    `identity_hash` varchar(64) NOT NULL,
                    `identity_type` varchar(16) NOT NULL DEFAULT "cookie",
                    `user_id` int(10) unsigned NOT NULL DEFAULT 0,
                    `author` varchar(200) NOT NULL DEFAULT "",
                    `mail_hash` varchar(64) NOT NULL DEFAULT "",
                    `created` int(10) unsigned NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `coid_identity` (`coid`, `identity_hash`),
                    KEY `coid` (`coid`),
                    KEY `mail_hash` (`mail_hash`),
                    KEY `created` (`created`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
            }

            $db->query($sql);
            self::migrateMomentLikeTable($adapter, $table);
            return true;
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function installPostLikeTable()
    {
        try {
            $db = Typecho_Db::get();
            $table = self::postLikeTableName();
            $adapter = strtolower(get_class($db->getAdapter()));

            if (strpos($adapter, 'sqlite') !== false) {
                $sql = 'CREATE TABLE IF NOT EXISTS "' . $table . '" (
                    "id" INTEGER PRIMARY KEY AUTOINCREMENT,
                    "cid" INTEGER NOT NULL,
                    "identity_hash" varchar(64) NOT NULL,
                    "identity_type" varchar(16) NOT NULL DEFAULT "cookie",
                    "user_id" INTEGER NOT NULL DEFAULT 0,
                    "author" varchar(200) NOT NULL DEFAULT "",
                    "mail_hash" varchar(64) NOT NULL DEFAULT "",
                    "created" INTEGER NOT NULL DEFAULT 0,
                    UNIQUE ("cid", "identity_hash")
                )';
            } else {
                $sql = 'CREATE TABLE IF NOT EXISTS `' . $table . '` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `cid` int(10) unsigned NOT NULL,
                    `identity_hash` varchar(64) NOT NULL,
                    `identity_type` varchar(16) NOT NULL DEFAULT "cookie",
                    `user_id` int(10) unsigned NOT NULL DEFAULT 0,
                    `author` varchar(200) NOT NULL DEFAULT "",
                    `mail_hash` varchar(64) NOT NULL DEFAULT "",
                    `created` int(10) unsigned NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `cid_identity` (`cid`, `identity_hash`),
                    KEY `cid` (`cid`),
                    KEY `mail_hash` (`mail_hash`),
                    KEY `created` (`created`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
            }

            $db->query($sql);
            return true;
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function installIpLocationTable()
    {
        static $installed = null;
        if ($installed === true) {
            return true;
        }

        try {
            $db = Typecho_Db::get();
            $table = self::ipLocationTableName();
            $adapter = strtolower(get_class($db->getAdapter()));

            if (strpos($adapter, 'sqlite') !== false) {
                $sql = 'CREATE TABLE IF NOT EXISTS "' . $table . '" (
                    "ip_hash" varchar(40) NOT NULL PRIMARY KEY,
                    "ip" varchar(64) NOT NULL DEFAULT "",
                    "country" varchar(64) NOT NULL DEFAULT "",
                    "subdivision" varchar(128) NOT NULL DEFAULT "",
                    "label" varchar(64) NOT NULL DEFAULT "",
                    "source" varchar(32) NOT NULL DEFAULT "",
                    "updated" INTEGER NOT NULL DEFAULT 0
                )';
            } else {
                $sql = 'CREATE TABLE IF NOT EXISTS `' . $table . '` (
                    `ip_hash` varchar(40) NOT NULL,
                    `ip` varchar(64) NOT NULL DEFAULT "",
                    `country` varchar(64) NOT NULL DEFAULT "",
                    `subdivision` varchar(128) NOT NULL DEFAULT "",
                    `label` varchar(64) NOT NULL DEFAULT "",
                    `source` varchar(32) NOT NULL DEFAULT "",
                    `updated` int(10) unsigned NOT NULL DEFAULT 0,
                    PRIMARY KEY (`ip_hash`),
                    KEY `updated` (`updated`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
            }

            $db->query($sql);
            $installed = true;
            return true;
        } catch (Exception $e) {
            $installed = false;
            return false;
        } catch (Throwable $e) {
            $installed = false;
            return false;
        }
    }

    private static function migrateMomentLikeTable($adapter, $table)
    {
        $db = Typecho_Db::get();
        $isSqlite = strpos($adapter, 'sqlite') !== false;
        $columns = $isSqlite
            ? array(
                'identity_type' => 'varchar(16) NOT NULL DEFAULT "cookie"',
                'user_id' => 'INTEGER NOT NULL DEFAULT 0',
                'author' => 'varchar(200) NOT NULL DEFAULT ""',
                'mail_hash' => 'varchar(64) NOT NULL DEFAULT ""',
            )
            : array(
                'identity_type' => 'varchar(16) NOT NULL DEFAULT "cookie"',
                'user_id' => 'int(10) unsigned NOT NULL DEFAULT 0',
                'author' => 'varchar(200) NOT NULL DEFAULT ""',
                'mail_hash' => 'varchar(64) NOT NULL DEFAULT ""',
            );

        foreach ($columns as $column => $definition) {
            try {
                $quotedTable = $isSqlite ? '"' . $table . '"' : '`' . $table . '`';
                $quotedColumn = $isSqlite ? '"' . $column . '"' : '`' . $column . '`';
                $db->query('ALTER TABLE ' . $quotedTable . ' ADD COLUMN ' . $quotedColumn . ' ' . $definition);
            } catch (Exception $e) {
            } catch (Throwable $e) {
            }
        }

        try {
            if ($isSqlite) {
                $db->query('CREATE INDEX IF NOT EXISTS "' . $table . '_mail_hash" ON "' . $table . '" ("mail_hash")');
                $db->query('CREATE INDEX IF NOT EXISTS "' . $table . '_created" ON "' . $table . '" ("created")');
            } else {
                $db->query('ALTER TABLE `' . $table . '` ADD KEY `mail_hash` (`mail_hash`)');
                $db->query('ALTER TABLE `' . $table . '` ADD KEY `created` (`created`)');
            }
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
    }

    public static function currentVisitorLocationLabel()
    {
        return self::ipLocationLabel(self::clientIp());
    }

    public static function currentVisitorLocationLabelFromCache()
    {
        return self::ipLocationLabelFromCache(self::clientIp());
    }

    public static function ipLocationLabel($ip)
    {
        $ip = trim((string) $ip);
        if (!self::isPublicIp($ip) || !self::installIpLocationTable()) {
            return '';
        }

        $hash = sha1($ip);
        $cached = self::readIpLocationCache($hash);
        if (!empty($cached['label']) && !empty($cached['updated']) && (time() - (int) $cached['updated']) < 2592000) {
            return (string) $cached['label'];
        }

        $location = self::fetchIpLocation($ip);
        if (empty($location['label'])) {
            if (!empty($cached['label'])) {
                return (string) $cached['label'];
            }
            return '';
        }

        self::writeIpLocationCache($hash, $ip, $location);
        return (string) $location['label'];
    }

    public static function ipLocationLabelFromCache($ip)
    {
        $ip = trim((string) $ip);
        if (!self::isPublicIp($ip) || !self::installIpLocationTable()) {
            return '';
        }

        $cached = self::readIpLocationCache(sha1($ip));
        return !empty($cached['label']) ? (string) $cached['label'] : '';
    }

    public static function cacheCommentIpLocation($comment)
    {
        try {
            if (is_array($comment) && !empty($comment['ip'])) {
                self::warmUpIpLocationCache((string) $comment['ip']);
            }
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }

        return $comment;
    }

    public static function warmUpIpLocationCache($ip, $force = false)
    {
        $ip = trim((string) $ip);
        if (!self::isPublicIp($ip) || !self::installIpLocationTable()) {
            return false;
        }

        $hash = sha1($ip);
        $cached = self::readIpLocationCache($hash);
        if (!$force && !empty($cached['label']) && !empty($cached['updated']) && (time() - (int) $cached['updated']) < 2592000) {
            return true;
        }

        $location = self::fetchIpLocation($ip);
        if (empty($location['label'])) {
            return false;
        }

        self::writeIpLocationCache($hash, $ip, $location);
        return true;
    }

    public static function rebuildIpLocationCache($limit = 20, $mode = 'missing')
    {
        $limit = max(1, min(50, (int) $limit));
        $mode = strtolower(trim((string) $mode));
        if (!in_array($mode, array('all', 'missing'), true)) {
            $mode = 'missing';
        }

        $result = array(
            'total' => 0,
            'pending' => 0,
            'scanned' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'remaining' => 0,
            'hasMore' => false,
        );

        if (!self::installIpLocationTable()) {
            return $result;
        }

        try {
            $db = Typecho_Db::get();
            $rows = $db->fetchAll($db->select('ip', 'MAX(created) AS last_used')
                ->from('table.comments')
                ->where('ip <> ?', '')
                ->group('ip'));
        } catch (Exception $e) {
            return $result;
        } catch (Throwable $e) {
            return $result;
        }

        if (empty($rows)) {
            return $result;
        }

        $hashes = array();
        foreach ($rows as $row) {
            if (!empty($row['ip'])) {
                $hashes[] = sha1((string) $row['ip']);
            }
        }

        $cacheMap = array();
        if (!empty($hashes)) {
            try {
                $cacheRows = $db->fetchAll($db->select('ip_hash', 'label', 'updated')
                    ->from(self::ipLocationTableName())
                    ->where('ip_hash IN ?', $hashes));
                foreach ($cacheRows as $cacheRow) {
                    if (empty($cacheRow['ip_hash'])) {
                        continue;
                    }
                    $cacheMap[(string) $cacheRow['ip_hash']] = array(
                        'label' => isset($cacheRow['label']) ? (string) $cacheRow['label'] : '',
                        'updated' => isset($cacheRow['updated']) ? (int) $cacheRow['updated'] : 0,
                    );
                }
            } catch (Exception $e) {
            } catch (Throwable $e) {
            }
        }

        $candidates = array();
        foreach ($rows as $row) {
            $ip = isset($row['ip']) ? trim((string) $row['ip']) : '';
            if ($ip === '' || !self::isPublicIp($ip)) {
                continue;
            }

            $hash = sha1($ip);
            $cache = isset($cacheMap[$hash]) ? $cacheMap[$hash] : array('label' => '', 'updated' => 0);
            $candidates[] = array(
                'ip' => $ip,
                'last_used' => isset($row['last_used']) ? (int) $row['last_used'] : 0,
                'label' => isset($cache['label']) ? (string) $cache['label'] : '',
                'updated' => isset($cache['updated']) ? (int) $cache['updated'] : 0,
            );
        }

        usort($candidates, function ($a, $b) use ($mode) {
            $aHasLabel = trim((string) $a['label']) !== '';
            $bHasLabel = trim((string) $b['label']) !== '';

            if ($mode === 'all') {
                $aRank = $aHasLabel ? (int) $a['updated'] : 0;
                $bRank = $bHasLabel ? (int) $b['updated'] : 0;
                if ($aRank !== $bRank) {
                    return $aRank < $bRank ? -1 : 1;
                }
            } elseif ($aHasLabel !== $bHasLabel) {
                return $aHasLabel ? 1 : -1;
            }

            if ($a['last_used'] !== $b['last_used']) {
                return $a['last_used'] < $b['last_used'] ? -1 : 1;
            }

            return strcmp($a['ip'], $b['ip']);
        });

        $result['total'] = count($candidates);
        $queue = array();
        foreach ($candidates as $candidate) {
            $hasLabel = trim((string) $candidate['label']) !== '';
            if ($mode === 'missing' && $hasLabel) {
                $result['skipped']++;
                continue;
            }

            $queue[] = $candidate;
        }

        $result['pending'] = count($queue);
        $batch = array_slice($queue, 0, $limit);

        foreach ($batch as $candidate) {
            $hasLabel = trim((string) $candidate['label']) !== '';
            if ($mode === 'missing' && $hasLabel) {
                $result['skipped']++;
                continue;
            }

            $result['scanned']++;
            if (self::warmUpIpLocationCache($candidate['ip'], $mode === 'all')) {
                $result['updated']++;
            } else {
                $result['failed']++;
            }
        }

        $result['remaining'] = max(0, $result['pending'] - $result['scanned']);
        $result['hasMore'] = $result['remaining'] > 0;

        return $result;
    }

    private static function clientIp()
    {
        $candidates = array();
        foreach (array('HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR') as $key) {
            if (!empty($_SERVER[$key])) {
                $candidates[] = (string) $_SERVER[$key];
            }
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            foreach (explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']) as $part) {
                $candidates[] = trim($part);
            }
        }

        foreach ($candidates as $candidate) {
            if (self::isPublicIp($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private static function isPublicIp($ip)
    {
        $ip = trim((string) $ip);
        return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private static function readIpLocationCache($hash)
    {
        try {
            $db = Typecho_Db::get();
            return $db->fetchRow($db->select('country', 'subdivision', 'label', 'source', 'updated')
                ->from(self::ipLocationTableName())
                ->where('ip_hash = ?', $hash)
                ->limit(1));
        } catch (Exception $e) {
            return array();
        } catch (Throwable $e) {
            return array();
        }
    }

    private static function writeIpLocationCache($hash, $ip, array $location)
    {
        try {
            $db = Typecho_Db::get();
            $table = self::ipLocationTableName();
            $db->query($db->delete($table)->where('ip_hash = ?', $hash));
            $db->query($db->insert($table)->rows(array(
                'ip_hash' => $hash,
                'ip' => substr((string) $ip, 0, 64),
                'country' => substr(isset($location['country']) ? (string) $location['country'] : '', 0, 64),
                'subdivision' => substr(isset($location['subdivision']) ? (string) $location['subdivision'] : '', 0, 128),
                'label' => substr((string) $location['label'], 0, 64),
                'source' => substr(isset($location['source']) ? (string) $location['source'] : '', 0, 32),
                'updated' => time(),
            )));
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
    }

    private static function fetchIpLocation($ip)
    {
        $countryPayload = self::fetchJson('https://api.country.is/' . rawurlencode($ip));
        $countryLocation = self::normalizeIpLocation($countryPayload, 'country.is');
        if (!empty($countryLocation['label']) && (!self::isChinaCountry($countryLocation['country']) || $countryLocation['subdivision'] !== '')) {
            return $countryLocation;
        }

        $ipwhoPayload = self::fetchJson('https://ipwho.is/' . rawurlencode($ip) . '?fields=success,country,country_code,region,region_code,city');
        $ipwhoLocation = self::normalizeIpLocation($ipwhoPayload, 'ipwho.is');
        if (!empty($ipwhoLocation['label'])) {
            return $ipwhoLocation;
        }

        return $countryLocation;
    }

    private static function fetchJson($url)
    {
        $body = '';
        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
                curl_setopt($ch, CURLOPT_USERAGENT, 'QiwiTheme/2.0.0');
                $body = curl_exec($ch);
                curl_close($ch);
            } else {
                $context = stream_context_create(array(
                    'http' => array(
                        'timeout' => 3,
                        'header' => "User-Agent: QiwiTheme/2.0.0\r\n",
                    ),
                ));
                $body = @file_get_contents($url, false, $context);
            }
        } catch (Exception $e) {
            return array();
        } catch (Throwable $e) {
            return array();
        }

        $data = json_decode((string) $body, true);
        return is_array($data) ? $data : array();
    }

    private static function normalizeIpLocation(array $payload, $source)
    {
        if (empty($payload)) {
            return array('country' => '', 'subdivision' => '', 'label' => '', 'source' => $source);
        }
        if (isset($payload['success']) && $payload['success'] === false) {
            return array('country' => '', 'subdivision' => '', 'label' => '', 'source' => $source);
        }

        $country = self::pickString($payload, array('country_code', 'countryCode', 'country', 'iso_code'));
        $countryName = self::pickString($payload, array('country_name', 'countryName', 'country'));
        $subdivision = self::pickSubdivision($payload);
        $continent = self::pickString($payload, array('continent_code', 'continentCode', 'continent', 'continent_name', 'continentName'));
        $label = self::formatIpLocationLabel($country, $countryName, $subdivision, $continent);

        return array(
            'country' => $country !== '' ? $country : $countryName,
            'subdivision' => $subdivision,
            'label' => $label,
            'source' => $source,
        );
    }

    private static function pickString(array $payload, array $keys)
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && !is_array($payload[$key])) {
                $value = trim((string) $payload[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    private static function pickSubdivision(array $payload)
    {
        foreach (array('subdivision', 'region', 'regionName', 'province', 'state') as $key) {
            if (!isset($payload[$key])) {
                continue;
            }
            if (is_array($payload[$key])) {
                foreach (array('name', 'code', 'isoCode', 'iso_code') as $subKey) {
                    if (!empty($payload[$key][$subKey])) {
                        return trim((string) $payload[$key][$subKey]);
                    }
                }
            } else {
                $value = trim((string) $payload[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        if (!empty($payload['region_code'])) {
            return trim((string) $payload['region_code']);
        }

        return '';
    }

    private static function isChinaCountry($country)
    {
        $country = strtoupper(trim((string) $country));
        return in_array($country, array('CN', 'CHN', 'CHINA', '中国'), true);
    }

    private static function formatIpLocationLabel($country, $countryName, $subdivision, $continent = '')
    {
        $country = trim((string) $country);
        $countryName = trim((string) $countryName);
        $countryCode = strtoupper($country);
        $subdivision = trim((string) $subdivision);
        $continent = trim((string) $continent);

        if (self::isChinaCountry($country) || self::isChinaCountry($countryName)) {
            $region = self::chinaRegionLabel($subdivision);
            return $region !== '' ? $region : '中国';
        }

        $countryLabel = self::countryLabel($countryCode !== '' ? $countryCode : $countryName);
        if ($countryLabel !== '') {
            return $countryLabel;
        }

        $countryLabel = self::countryLabel($countryName);
        if ($countryLabel !== '') {
            return $countryLabel;
        }

        $continentLabel = self::continentLabel($continent);
        if ($continentLabel !== '') {
            return $continentLabel;
        }

        return '未知';
    }

    private static function countryLabel($value)
    {
        $key = strtoupper(trim((string) $value));
        if ($key === '') {
            return '';
        }

        $map = array(
            'CN' => '中国', 'CHN' => '中国', 'CHINA' => '中国', '中国' => '中国',
            'HK' => '香港', 'HKG' => '香港', 'HONG KONG' => '香港', '香港' => '香港',
            'MO' => '澳门', 'MAC' => '澳门', 'MACAO' => '澳门', 'MACAU' => '澳门', '澳门' => '澳门',
            'TW' => '台湾', 'TWN' => '台湾', 'TAIWAN' => '台湾', '台湾' => '台湾',
            'SG' => '新加坡', 'SGP' => '新加坡', 'SINGAPORE' => '新加坡',
            'JP' => '日本', 'JPN' => '日本', 'JAPAN' => '日本',
            'KR' => '韩国', 'KOR' => '韩国', 'KOREA' => '韩国', 'SOUTH KOREA' => '韩国',
            'US' => '美国', 'USA' => '美国', 'UNITED STATES' => '美国',
            'GB' => '英国', 'GBR' => '英国', 'UNITED KINGDOM' => '英国',
            'CA' => '加拿大', 'CAN' => '加拿大', 'CANADA' => '加拿大',
            'AU' => '澳大利亚', 'AUS' => '澳大利亚', 'AUSTRALIA' => '澳大利亚',
            'DE' => '德国', 'DEU' => '德国', 'GERMANY' => '德国',
            'FR' => '法国', 'FRA' => '法国', 'FRANCE' => '法国',
            'RU' => '俄罗斯', 'RUS' => '俄罗斯', 'RUSSIA' => '俄罗斯',
            'IN' => '印度', 'IND' => '印度', 'INDIA' => '印度',
            'ID' => '印度尼西亚', 'IDN' => '印度尼西亚', 'INDONESIA' => '印度尼西亚',
            'MY' => '马来西亚', 'MYS' => '马来西亚', 'MALAYSIA' => '马来西亚',
            'TH' => '泰国', 'THA' => '泰国', 'THAILAND' => '泰国',
            'VN' => '越南', 'VNM' => '越南', 'VIETNAM' => '越南',
            'PH' => '菲律宾', 'PHL' => '菲律宾', 'PHILIPPINES' => '菲律宾',
            'BR' => '巴西', 'BRA' => '巴西', 'BRAZIL' => '巴西',
            'MX' => '墨西哥', 'MEX' => '墨西哥', 'MEXICO' => '墨西哥',
            'IT' => '意大利', 'ITA' => '意大利', 'ITALY' => '意大利',
            'ES' => '西班牙', 'ESP' => '西班牙', 'SPAIN' => '西班牙',
            'NL' => '荷兰', 'NLD' => '荷兰', 'NETHERLANDS' => '荷兰',
            'SE' => '瑞典', 'SWE' => '瑞典', 'SWEDEN' => '瑞典',
            'CH' => '瑞士', 'CHE' => '瑞士', 'SWITZERLAND' => '瑞士',
        );

        return isset($map[$key]) ? $map[$key] : '';
    }

    private static function continentLabel($value)
    {
        $key = strtoupper(trim((string) $value));
        if ($key === '') {
            return '';
        }

        $map = array(
            'AS' => '亚洲', 'ASIA' => '亚洲',
            'EU' => '欧洲', 'EUROPE' => '欧洲',
            'NA' => '北美洲', 'NORTH AMERICA' => '北美洲',
            'SA' => '南美洲', 'SOUTH AMERICA' => '南美洲',
            'AF' => '非洲', 'AFRICA' => '非洲',
            'OC' => '大洋洲', 'OCEANIA' => '大洋洲',
            'AN' => '南极洲', 'ANTARCTICA' => '南极洲',
        );

        return isset($map[$key]) ? $map[$key] : '';
    }

    private static function chinaRegionLabel($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $upper = strtoupper($value);
        $map = array(
            'CN-AH' => '安徽', 'AH' => '安徽', 'ANHUI' => '安徽',
            'CN-BJ' => '北京', 'BJ' => '北京', 'BEIJING' => '北京',
            'CN-CQ' => '重庆', 'CQ' => '重庆', 'CHONGQING' => '重庆',
            'CN-FJ' => '福建', 'FJ' => '福建', 'FUJIAN' => '福建',
            'CN-GD' => '广东', 'GD' => '广东', 'GUANGDONG' => '广东',
            'CN-GS' => '甘肃', 'GS' => '甘肃', 'GANSU' => '甘肃',
            'CN-GX' => '广西', 'GX' => '广西', 'GUANGXI' => '广西',
            'CN-GZ' => '贵州', 'GZ' => '贵州', 'GUIZHOU' => '贵州',
            'CN-HA' => '河南', 'HA' => '河南', 'HENAN' => '河南',
            'CN-HB' => '湖北', 'HB' => '湖北', 'HUBEI' => '湖北',
            'CN-HE' => '河北', 'HE' => '河北', 'HEBEI' => '河北',
            'CN-HI' => '海南', 'HI' => '海南', 'HAINAN' => '海南',
            'CN-HK' => '香港', 'HK' => '香港', 'HONG KONG' => '香港',
            'CN-HL' => '黑龙江', 'HL' => '黑龙江', 'HEILONGJIANG' => '黑龙江',
            'CN-HN' => '湖南', 'HN' => '湖南', 'HUNAN' => '湖南',
            'CN-JL' => '吉林', 'JL' => '吉林', 'JILIN' => '吉林',
            'CN-JS' => '江苏', 'JS' => '江苏', 'JIANGSU' => '江苏',
            'CN-JX' => '江西', 'JX' => '江西', 'JIANGXI' => '江西',
            'CN-LN' => '辽宁', 'LN' => '辽宁', 'LIAONING' => '辽宁',
            'CN-MO' => '澳门', 'MO' => '澳门', 'MACAO' => '澳门', 'MACAU' => '澳门',
            'CN-NM' => '内蒙古', 'NM' => '内蒙古', 'NEI MONGOL' => '内蒙古', 'INNER MONGOLIA' => '内蒙古',
            'CN-NX' => '宁夏', 'NX' => '宁夏', 'NINGXIA' => '宁夏',
            'CN-QH' => '青海', 'QH' => '青海', 'QINGHAI' => '青海',
            'CN-SC' => '四川', 'SC' => '四川', 'SICHUAN' => '四川',
            'CN-SD' => '山东', 'SD' => '山东', 'SHANDONG' => '山东',
            'CN-SH' => '上海', 'SH' => '上海', 'SHANGHAI' => '上海',
            'CN-SN' => '陕西', 'SN' => '陕西', 'SHAANXI' => '陕西',
            'CN-SX' => '山西', 'SX' => '山西', 'SHANXI' => '山西',
            'CN-TJ' => '天津', 'TJ' => '天津', 'TIANJIN' => '天津',
            'CN-TW' => '台湾', 'TW' => '台湾', 'TAIWAN' => '台湾',
            'CN-XJ' => '新疆', 'XJ' => '新疆', 'XINJIANG' => '新疆',
            'CN-XZ' => '西藏', 'XZ' => '西藏', 'TIBET' => '西藏', 'XIZANG' => '西藏',
            'CN-YN' => '云南', 'YN' => '云南', 'YUNNAN' => '云南',
            'CN-ZJ' => '浙江', 'ZJ' => '浙江', 'ZHEJIANG' => '浙江',
        );

        if (isset($map[$upper])) {
            return $map[$upper];
        }

        return preg_replace('/(特别行政区|壮族自治区|回族自治区|维吾尔自治区|自治区|省|市)$/u', '', $value);
    }

    public static function isExternalHttpUrl($url)
    {
        $url = trim((string) $url);
        if (!preg_match('/^https?:\/\//i', $url)) {
            return false;
        }

        $targetHost = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($targetHost === '') {
            return false;
        }

        Typecho_Widget::widget('Widget_Options')->to($options);
        $siteHost = strtolower((string) parse_url((string) $options->siteUrl, PHP_URL_HOST));
        if ($siteHost === '') {
            return true;
        }

        return preg_replace('/^www\./i', '', $targetHost) !== preg_replace('/^www\./i', '', $siteHost);
    }

    public static function decodeGotoUrl($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $decoded = base64_decode(strtr(rawurldecode($value), '-_', '+/'), true);
        if ($decoded === false) {
            return '';
        }

        $decoded = trim($decoded);
        if (preg_match('/[\r\n]/', $decoded)) {
            return '';
        }

        return self::isExternalHttpUrl($decoded) ? $decoded : '';
    }

    public static function recordExternalLinkClick($url, $source = '')
    {
        $url = trim((string) $url);
        if (!self::isExternalHttpUrl($url) || !self::installExternalLinkTable()) {
            return false;
        }

        try {
            $db = Typecho_Db::get();
            $table = self::externalLinkTableName();
            $now = time();

            $db->query($db->insert($table)->rows(array(
                'url_hash' => sha1($url),
                'url' => $url,
                'host' => strtolower((string) parse_url($url, PHP_URL_HOST)),
                'source' => substr(trim((string) $source), 0, 500),
                'referer' => isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '',
                'ip' => isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '',
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : '',
                'clicked' => $now,
                'created' => $now,
            )));

            return true;
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function getExternalLinkStats($limit = 30)
    {
        $limit = max(1, min(100, (int) $limit));
        if (!self::installExternalLinkTable()) {
            return array();
        }

        try {
            $db = Typecho_Db::get();
            $rows = $db->fetchAll($db->select('url', 'host', 'COUNT(id) AS clicks', 'MAX(clicked) AS last_clicked')
                ->from(self::externalLinkTableName())
                ->group('url_hash, url, host')
                ->order('last_clicked', Typecho_Db::SORT_DESC)
                ->limit($limit));

            $items = array();
            foreach ($rows as $row) {
                $items[] = array(
                    'url' => isset($row['url']) ? (string) $row['url'] : '',
                    'host' => isset($row['host']) ? (string) $row['host'] : '',
                    'clicks' => isset($row['clicks']) ? (int) $row['clicks'] : 0,
                    'lastClicked' => !empty($row['last_clicked']) ? date('Y-m-d H:i', (int) $row['last_clicked']) : '',
                );
            }

            return $items;
        } catch (Exception $e) {
            return array();
        } catch (Throwable $e) {
            return array();
        }
    }

    public static function postLikeCounts(array $cids)
    {
        $ids = array();
        foreach ($cids as $cid) {
            $cid = (int) $cid;
            if ($cid > 0) {
                $ids[$cid] = 0;
            }
        }

        if (empty($ids) || !self::installPostLikeTable()) {
            return $ids;
        }

        try {
            $db = Typecho_Db::get();
            $rows = $db->fetchAll($db->select('cid', 'COUNT(id) AS likes')
                ->from(self::postLikeTableName())
                ->where('cid IN (' . implode(',', array_keys($ids)) . ')')
                ->group('cid'));

            foreach ($rows as $row) {
                $cid = isset($row['cid']) ? (int) $row['cid'] : 0;
                if ($cid > 0 && isset($ids[$cid])) {
                    $ids[$cid] = isset($row['likes']) ? (int) $row['likes'] : 0;
                }
            }
        } catch (Exception $e) {
            return $ids;
        } catch (Throwable $e) {
            return $ids;
        }

        return $ids;
    }

    public static function getPostLikeRecords($limit = 100)
    {
        $limit = max(1, min(200, (int) $limit));
        if (!self::installPostLikeTable()) {
            return array();
        }

        try {
            $db = Typecho_Db::get();
            $rows = $db->fetchAll($db->select('id', 'cid', 'identity_hash', 'identity_type', 'user_id', 'author', 'mail_hash', 'created')
                ->from(self::postLikeTableName())
                ->order('created', Typecho_Db::SORT_DESC)
                ->limit($limit));
        } catch (Exception $e) {
            return array();
        } catch (Throwable $e) {
            return array();
        }

        if (empty($rows)) {
            return array();
        }

        $cids = array();
        $mailHashes = array();
        foreach ($rows as $row) {
            $cid = isset($row['cid']) ? (int) $row['cid'] : 0;
            if ($cid > 0) {
                $cids[$cid] = true;
            }

            $mailHash = isset($row['mail_hash']) ? trim((string) $row['mail_hash']) : '';
            if ($mailHash !== '') {
                $mailHashes[$mailHash] = true;
            }
        }

        $posts = self::postLikePostMap(array_keys($cids));
        $matches = self::momentLikeCommentMatches(array_keys($mailHashes));
        $records = array();

        foreach ($rows as $row) {
            $cid = isset($row['cid']) ? (int) $row['cid'] : 0;
            $mailHash = isset($row['mail_hash']) ? trim((string) $row['mail_hash']) : '';
            $records[] = array(
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'cid' => $cid,
                'identityType' => isset($row['identity_type']) && $row['identity_type'] !== '' ? (string) $row['identity_type'] : 'cookie',
                'userId' => isset($row['user_id']) ? (int) $row['user_id'] : 0,
                'author' => isset($row['author']) ? (string) $row['author'] : '',
                'mailHash' => $mailHash,
                'created' => isset($row['created']) ? (int) $row['created'] : 0,
                'createdText' => !empty($row['created']) ? date('Y-m-d H:i', (int) $row['created']) : '',
                'post' => isset($posts[$cid]) ? $posts[$cid] : array(),
                'matches' => isset($matches[$mailHash]) ? $matches[$mailHash] : array(),
            );
        }

        return $records;
    }

    public static function getPostLikeArticleStats($limit = 100)
    {
        $limit = max(1, min(300, (int) $limit));
        if (!self::installPostLikeTable()) {
            return array();
        }

        try {
            $db = Typecho_Db::get();
            $rows = $db->fetchAll($db->select('cid', 'COUNT(id) AS likes', 'MAX(created) AS last_created')
                ->from(self::postLikeTableName())
                ->group('cid')
                ->order('likes', Typecho_Db::SORT_DESC)
                ->order('last_created', Typecho_Db::SORT_DESC)
                ->limit($limit));
        } catch (Exception $e) {
            return array();
        } catch (Throwable $e) {
            return array();
        }

        if (empty($rows)) {
            return array();
        }

        $cids = array();
        foreach ($rows as $row) {
            $cid = isset($row['cid']) ? (int) $row['cid'] : 0;
            if ($cid > 0) {
                $cids[$cid] = true;
            }
        }

        $posts = self::postLikePostMap(array_keys($cids));
        $items = array();
        foreach ($rows as $row) {
            $cid = isset($row['cid']) ? (int) $row['cid'] : 0;
            $lastCreated = isset($row['last_created']) ? (int) $row['last_created'] : 0;
            $items[] = array(
                'cid' => $cid,
                'likes' => isset($row['likes']) ? (int) $row['likes'] : 0,
                'lastLiked' => $lastCreated,
                'lastLikedText' => $lastCreated > 0 ? date('Y-m-d H:i', $lastCreated) : '',
                'post' => isset($posts[$cid]) ? $posts[$cid] : array(),
            );
        }

        return $items;
    }

    private static function postLikePostMap(array $cids)
    {
        $ids = array();
        foreach ($cids as $cid) {
            $cid = (int) $cid;
            if ($cid > 0) {
                $ids[$cid] = true;
            }
        }

        if (empty($ids)) {
            return array();
        }

        $map = array();
        try {
            $db = Typecho_Db::get();
            $rows = $db->fetchAll($db->select('cid', 'title', 'slug', 'created', 'type', 'status')
                ->from('table.contents')
                ->where('cid IN (' . implode(',', array_keys($ids)) . ')'));
            foreach ($rows as $row) {
                $cid = isset($row['cid']) ? (int) $row['cid'] : 0;
                if ($cid <= 0) {
                    continue;
                }

                $map[$cid] = array(
                    'cid' => $cid,
                    'title' => isset($row['title']) ? (string) $row['title'] : '',
                    'slug' => isset($row['slug']) ? (string) $row['slug'] : '',
                    'type' => isset($row['type']) ? (string) $row['type'] : '',
                    'status' => isset($row['status']) ? (string) $row['status'] : '',
                    'created' => isset($row['created']) ? (int) $row['created'] : 0,
                    'permalink' => self::postLikePermalink($row),
                );
            }
        } catch (Exception $e) {
            return $map;
        } catch (Throwable $e) {
            return $map;
        }

        return $map;
    }

    private static function postLikePermalink($row)
    {
        try {
            if (!is_array($row) || !class_exists('Typecho_Router') || !class_exists('Typecho_Common')) {
                return '';
            }

            $type = isset($row['type']) ? (string) $row['type'] : 'post';
            $route = $type === 'page' ? 'page' : 'post';
            if (Typecho_Router::get($route) === null) {
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

            $options = Helper::options();
            return Typecho_Common::url(Typecho_Router::url($route, $row), $options->index);
        } catch (Exception $e) {
            return '';
        } catch (Throwable $e) {
            return '';
        }
    }
    public static function currentPostLikeIdentityHash()
    {
        try {
            Typecho_Widget::widget('Widget_User')->to($user);
            if ($user && $user->hasLogin()) {
                $mailHash = self::momentMailHash(isset($user->mail) ? $user->mail : '');
                if ($mailHash !== '') {
                    return sha1('mail:' . $mailHash);
                }

                return sha1('user:' . (int) $user->uid);
            }
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }

        $value = isset($_COOKIE['qiwi_post_like_id']) ? preg_replace('/[^a-zA-Z0-9]/', '', (string) $_COOKIE['qiwi_post_like_id']) : '';
        return $value !== '' && strlen($value) >= 20 ? sha1('visitor:' . $value) : '';
    }

    public static function hasPostLiked($cid, $identityHash = '')
    {
        $cid = (int) $cid;
        $identityHash = trim((string) $identityHash);
        if ($cid <= 0 || $identityHash === '' || !self::installPostLikeTable()) {
            return false;
        }

        try {
            $db = Typecho_Db::get();
            $row = $db->fetchRow($db->select('id')
                ->from(self::postLikeTableName())
                ->where('cid = ?', $cid)
                ->where('identity_hash = ?', $identityHash)
                ->limit(1));

            return !empty($row);
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function addPostLike($cid, $identity)
    {
        $cid = (int) $cid;
        if (!is_array($identity)) {
            $identity = array('identity_hash' => trim((string) $identity));
        }

        $identityHash = isset($identity['identity_hash']) ? trim((string) $identity['identity_hash']) : '';
        if ($cid <= 0 || $identityHash === '' || !self::installPostLikeTable()) {
            return array('liked' => false, 'count' => 0, 'created' => false);
        }

        try {
            $db = Typecho_Db::get();
            $table = self::postLikeTableName();
            $alreadyLiked = self::hasPostLiked($cid, $identityHash);
            if (!$alreadyLiked) {
                $db->query($db->insert($table)->rows(array(
                    'cid' => $cid,
                    'identity_hash' => $identityHash,
                    'identity_type' => isset($identity['identity_type']) ? substr((string) $identity['identity_type'], 0, 16) : 'cookie',
                    'user_id' => isset($identity['user_id']) ? max(0, (int) $identity['user_id']) : 0,
                    'author' => isset($identity['author']) ? substr(trim((string) $identity['author']), 0, 200) : '',
                    'mail_hash' => isset($identity['mail_hash']) ? substr(trim((string) $identity['mail_hash']), 0, 64) : '',
                    'created' => time(),
                )));
            }

            $counts = self::postLikeCounts(array($cid));
            return array('liked' => true, 'count' => isset($counts[$cid]) ? (int) $counts[$cid] : 0, 'created' => !$alreadyLiked);
        } catch (Exception $e) {
            $counts = self::postLikeCounts(array($cid));
            return array('liked' => self::hasPostLiked($cid, $identityHash), 'count' => isset($counts[$cid]) ? (int) $counts[$cid] : 0, 'created' => false);
        } catch (Throwable $e) {
            $counts = self::postLikeCounts(array($cid));
            return array('liked' => self::hasPostLiked($cid, $identityHash), 'count' => isset($counts[$cid]) ? (int) $counts[$cid] : 0, 'created' => false);
        }
    }

    public static function momentLikeCounts(array $coids)
    {
        $ids = array();
        foreach ($coids as $coid) {
            $coid = (int) $coid;
            if ($coid > 0) {
                $ids[$coid] = 0;
            }
        }

        if (empty($ids) || !self::installMomentLikeTable()) {
            return $ids;
        }

        try {
            $db = Typecho_Db::get();
            $rows = $db->fetchAll($db->select('coid', 'COUNT(id) AS likes')
                ->from(self::momentLikeTableName())
                ->where('coid IN (' . implode(',', array_keys($ids)) . ')')
                ->group('coid'));

            foreach ($rows as $row) {
                $coid = isset($row['coid']) ? (int) $row['coid'] : 0;
                if ($coid > 0 && isset($ids[$coid])) {
                    $ids[$coid] = isset($row['likes']) ? (int) $row['likes'] : 0;
                }
            }
        } catch (Exception $e) {
            return $ids;
        } catch (Throwable $e) {
            return $ids;
        }

        return $ids;
    }

    public static function normalizeMomentEmail($mail)
    {
        $mail = strtolower(trim((string) $mail));
        if ($mail === '' || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            return '';
        }

        return $mail;
    }

    public static function momentMailHash($mail)
    {
        $mail = self::normalizeMomentEmail($mail);
        return $mail === '' ? '' : sha1($mail);
    }

    public static function hasMomentLiked($coid, $identityHash = '')
    {
        $coid = (int) $coid;
        $identityHash = trim((string) $identityHash);
        if ($coid <= 0 || $identityHash === '' || !self::installMomentLikeTable()) {
            return false;
        }

        try {
            $db = Typecho_Db::get();
            $row = $db->fetchRow($db->select('id')
                ->from(self::momentLikeTableName())
                ->where('coid = ?', $coid)
                ->where('identity_hash = ?', $identityHash)
                ->limit(1));

            return !empty($row);
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function toggleMomentLike($coid, $identity)
    {
        $coid = (int) $coid;
        if (!is_array($identity)) {
            $identity = array('identity_hash' => trim((string) $identity));
        }

        $identityHash = isset($identity['identity_hash']) ? trim((string) $identity['identity_hash']) : '';
        if ($coid <= 0 || $identityHash === '' || !self::installMomentLikeTable()) {
            return array('liked' => false, 'count' => 0);
        }

        try {
            $db = Typecho_Db::get();
            $table = self::momentLikeTableName();
            if (self::hasMomentLiked($coid, $identityHash)) {
                $db->query($db->delete($table)
                    ->where('coid = ?', $coid)
                    ->where('identity_hash = ?', $identityHash));
                $liked = false;
            } else {
                $db->query($db->insert($table)->rows(array(
                    'coid' => $coid,
                    'identity_hash' => $identityHash,
                    'identity_type' => isset($identity['identity_type']) ? substr((string) $identity['identity_type'], 0, 16) : 'cookie',
                    'user_id' => isset($identity['user_id']) ? max(0, (int) $identity['user_id']) : 0,
                    'author' => isset($identity['author']) ? substr(trim((string) $identity['author']), 0, 200) : '',
                    'mail_hash' => isset($identity['mail_hash']) ? substr(trim((string) $identity['mail_hash']), 0, 64) : '',
                    'created' => time(),
                )));
                if (!empty($identity['previous_identity_hash'])
                    && $identity['previous_identity_hash'] !== $identityHash) {
                    $db->query($db->delete($table)
                        ->where('coid = ?', $coid)
                        ->where('identity_hash = ?', (string) $identity['previous_identity_hash']));
                }
                $liked = true;
            }

            $counts = self::momentLikeCounts(array($coid));
            return array('liked' => $liked, 'count' => isset($counts[$coid]) ? (int) $counts[$coid] : 0);
        } catch (Exception $e) {
            $counts = self::momentLikeCounts(array($coid));
            return array('liked' => self::hasMomentLiked($coid, $identityHash), 'count' => isset($counts[$coid]) ? (int) $counts[$coid] : 0);
        } catch (Throwable $e) {
            $counts = self::momentLikeCounts(array($coid));
            return array('liked' => self::hasMomentLiked($coid, $identityHash), 'count' => isset($counts[$coid]) ? (int) $counts[$coid] : 0);
        }
    }

    public static function getMomentLikeRecords($limit = 100)
    {
        $limit = max(1, min(200, (int) $limit));
        if (!self::installMomentLikeTable()) {
            return array();
        }

        try {
            $db = Typecho_Db::get();
            $rows = $db->fetchAll($db->select('id', 'coid', 'identity_hash', 'identity_type', 'user_id', 'author', 'mail_hash', 'created')
                ->from(self::momentLikeTableName())
                ->order('created', Typecho_Db::SORT_DESC)
                ->limit($limit));
        } catch (Exception $e) {
            return array();
        } catch (Throwable $e) {
            return array();
        }

        if (empty($rows)) {
            return array();
        }

        $coids = array();
        $mailHashes = array();
        foreach ($rows as $row) {
            $coid = isset($row['coid']) ? (int) $row['coid'] : 0;
            if ($coid > 0) {
                $coids[$coid] = true;
            }

            $mailHash = isset($row['mail_hash']) ? trim((string) $row['mail_hash']) : '';
            if ($mailHash !== '') {
                $mailHashes[$mailHash] = true;
            }
        }

        $moments = self::momentLikeMomentMap(array_keys($coids));
        $matches = self::momentLikeCommentMatches(array_keys($mailHashes));
        $records = array();

        foreach ($rows as $row) {
            $coid = isset($row['coid']) ? (int) $row['coid'] : 0;
            $mailHash = isset($row['mail_hash']) ? trim((string) $row['mail_hash']) : '';
            $records[] = array(
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'coid' => $coid,
                'identityType' => isset($row['identity_type']) && $row['identity_type'] !== '' ? (string) $row['identity_type'] : 'cookie',
                'userId' => isset($row['user_id']) ? (int) $row['user_id'] : 0,
                'author' => isset($row['author']) ? (string) $row['author'] : '',
                'mailHash' => $mailHash,
                'created' => isset($row['created']) ? (int) $row['created'] : 0,
                'createdText' => !empty($row['created']) ? date('Y-m-d H:i', (int) $row['created']) : '',
                'moment' => isset($moments[$coid]) ? $moments[$coid] : array(),
                'matches' => isset($matches[$mailHash]) ? $matches[$mailHash] : array(),
            );
        }

        return $records;
    }

    private static function momentLikeMomentMap(array $coids)
    {
        $map = array();
        $ids = array();
        foreach ($coids as $coid) {
            $coid = (int) $coid;
            if ($coid > 0) {
                $ids[$coid] = true;
            }
        }

        if (empty($ids)) {
            return $map;
        }

        try {
            $db = Typecho_Db::get();
            $rows = $db->fetchAll($db->select('table.comments.coid', 'table.comments.text', 'table.comments.created', 'table.contents.title')
                ->from('table.comments')
                ->join('table.contents', 'table.comments.cid = table.contents.cid')
                ->where('table.comments.coid IN (' . implode(',', array_keys($ids)) . ')'));
            foreach ($rows as $row) {
                $coid = isset($row['coid']) ? (int) $row['coid'] : 0;
                if ($coid <= 0) {
                    continue;
                }

                $map[$coid] = array(
                    'title' => isset($row['title']) ? (string) $row['title'] : '',
                    'excerpt' => self::momentLikeExcerpt(isset($row['text']) ? (string) $row['text'] : ''),
                    'created' => isset($row['created']) ? (int) $row['created'] : 0,
                );
            }
        } catch (Exception $e) {
            return $map;
        } catch (Throwable $e) {
            return $map;
        }

        return $map;
    }

    private static function momentLikeCommentMatches(array $mailHashes)
    {
        $wanted = array();
        foreach ($mailHashes as $hash) {
            $hash = trim((string) $hash);
            if ($hash !== '') {
                $wanted[$hash] = true;
            }
        }

        if (empty($wanted)) {
            return array();
        }

        $matches = array();
        try {
            $db = Typecho_Db::get();
            $rows = $db->fetchAll($db->select('coid', 'author', 'mail', 'created')
                ->from('table.comments')
                ->where('mail IS NOT NULL')
                ->where('mail <> ?', '')
                ->order('created', Typecho_Db::SORT_DESC)
                ->limit(5000));
            foreach ($rows as $row) {
                $mailHash = self::momentMailHash(isset($row['mail']) ? $row['mail'] : '');
                if ($mailHash === '' || !isset($wanted[$mailHash])) {
                    continue;
                }

                if (!isset($matches[$mailHash])) {
                    $matches[$mailHash] = array();
                }

                $author = trim((string) (isset($row['author']) ? $row['author'] : ''));
                $key = $author !== '' ? $author : 'comment-' . (isset($row['coid']) ? (int) $row['coid'] : 0);
                if (!isset($matches[$mailHash][$key])) {
                    $matches[$mailHash][$key] = array(
                        'author' => $author,
                        'coid' => isset($row['coid']) ? (int) $row['coid'] : 0,
                        'created' => isset($row['created']) ? (int) $row['created'] : 0,
                    );
                }
            }
        } catch (Exception $e) {
            return array();
        } catch (Throwable $e) {
            return array();
        }

        foreach ($matches as $hash => $items) {
            $matches[$hash] = array_values($items);
        }

        return $matches;
    }

    private static function momentLikeExcerpt($text, $length = 72)
    {
        $text = trim(strip_tags(preg_replace('/\s+/u', ' ', (string) $text)));
        if ($text === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $length
                ? mb_substr($text, 0, $length, 'UTF-8') . '...'
                : $text;
        }

        return strlen($text) > $length * 2 ? substr($text, 0, $length * 2) . '...' : $text;
    }

    public static function getThreadData($mid)
    {
        $mid = (int) $mid;
        if ($mid <= 0) {
            return '';
        }

        try {
            $db = Typecho_Db::get();
            $row = $db->fetchRow($db->select('data')->from(self::tableName())->where('mid = ?', $mid)->limit(1));
            return !empty($row['data']) ? (string) $row['data'] : '';
        } catch (Exception $e) {
            return '';
        }
    }

    public static function saveThreadData($mid, $data)
    {
        $mid = (int) $mid;
        if ($mid <= 0) {
            return false;
        }

        $data = trim((string) $data);
        if ($data === '') {
            return false;
        }

        self::installTable();

        $db = Typecho_Db::get();
        $table = self::tableName();
        $now = time();
        $exists = $db->fetchRow($db->select('mid')->from($table)->where('mid = ?', $mid)->limit(1));

        if ($exists) {
            $db->query($db->update($table)->rows(array(
                'data' => $data,
                'modified' => $now,
            ))->where('mid = ?', $mid));
        } else {
            $db->query($db->insert($table)->rows(array(
                'mid' => $mid,
                'data' => $data,
                'created' => $now,
                'modified' => $now,
            )));
        }

        return true;
    }
}
