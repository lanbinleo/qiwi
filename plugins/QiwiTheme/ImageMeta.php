<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Qiwi 图片尺寸缓存模块：按 URL 缓存正文图片的原始宽高，供主题在渲染时
 * 给 <img> 注入 width/height 属性，浏览器即可在图片加载前按真实比例预留
 * 空间，避免加载完成后页面跳动（CLS）。
 *
 * 数据来源有两条互补路径：
 *  1. 浏览器回传：正文图片懒加载成功后，v2.js 用 sendBeacon 把
 *     naturalWidth/naturalHeight 报给 /action/qiwi-theme?do=image-meta；
 *  2. 服务端预热：渲染时发现未缓存的 URL，请求结束后（fastcgi_finish_request
 *     之后）分段抓取图片头部字节解析尺寸，写入同一张表。
 * 图片 URL 通常带内容哈希、内容不可变，成功记录视为永久有效；失败记录
 * 7 天后才允许重试。所有方法失败静默降级，不影响页面输出。
 *
 * @package QiwiTheme
 * @author  Leo 里奥
 * @version 2.4.1
 * @link    https://bboreo.com/
 */
class QiwiTheme_ImageMeta
{
    const TABLE = 'qiwi_image_meta';

    // 存储与匹配的 URL 长度上限
    const URL_MAX = 1000;
    // 宽高合法范围
    const DIM_MIN = 1;
    const DIM_MAX = 50000;
    // 首次抓取字节数（JPEG/PNG/GIF/WebP 头部足够）与解析失败后的补抓上限
    const FETCH_BYTES = 32768;
    const FETCH_RETRY_BYTES = 262144;
    const FETCH_TIMEOUT = 5;
    // 单次请求最多预热条数与总时间预算（秒）
    const WARM_PER_REQUEST = 4;
    const WARM_TIME_BUDGET = 8;
    // 失败记录的重试间隔（秒）
    const FAIL_RETRY_AFTER = 604800;
    // getMeta 分块查询的批大小（IN 子句里的哈希数）
    const LOOKUP_CHUNK = 60;

    private static $_warmQueue = array();
    private static $_warmScheduled = false;
    private static $_activeCache = null;

    /**
     * QiwiTheme 是否处于激活状态。Typecho 的自动加载不检查激活，
     * 插件停用后类仍可被加载，因此主题侧读写缓存前必须先过这道门。
     */
    public static function isActive()
    {
        if (self::$_activeCache !== null) {
            return self::$_activeCache;
        }

        try {
            $options = Typecho_Widget::widget('Widget_Options');
            $activated = isset($options->plugins) && is_array($options->plugins) && isset($options->plugins['activated'])
                ? $options->plugins['activated']
                : array();
            self::$_activeCache = !empty($activated['QiwiTheme']);
        } catch (Exception $e) {
            self::$_activeCache = false;
        } catch (Throwable $e) {
            self::$_activeCache = false;
        }
        return self::$_activeCache;
    }

    public static function tableName()
    {
        $db = Typecho_Db::get();
        return $db->getPrefix() . self::TABLE;
    }

    /**
     * 把内容里的 src 属性值归一化为缓存键：
     * - 先做 HTML 实体解码（浏览器 getAttribute 已解码，两端键需一致）；
     * - 完整 http(s) 地址原样保留；
     * - 站根相对地址补上站点 scheme + host；
     * - 其余（协议相对、纯相对、data: 等）返回空串，不参与缓存。
     */
    public static function normalizeUrl($url)
    {
        $url = trim((string) $url);
        if ($url === '' || strlen($url) > self::URL_MAX) {
            return '';
        }
        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = trim($url);
        if ($url === '' || strlen($url) > self::URL_MAX || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return '';
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        if (strpos($url, '//') === 0 || strpos($url, '/') !== 0) {
            return '';
        }

        try {
            $options = Typecho_Widget::widget('Widget_Options');
            $siteUrl = isset($options->siteUrl) ? (string) $options->siteUrl : '';
        } catch (Exception $e) {
            $siteUrl = '';
        } catch (Throwable $e) {
            $siteUrl = '';
        }
        if (!preg_match('~^(https?)://([^/?#]+)~i', $siteUrl, $match)) {
            return '';
        }
        return strtolower($match[1]) . '://' . $match[2] . $url;
    }

    /**
     * 批量查询缓存，按 LOOKUP_CHUNK 分块查询、不限总数。返回以归一化 URL
     * 为键的数组：array('ok' => bool, 'w' => int, 'h' => int, 'updated' => int)
     */
    public static function getMeta(array $urls)
    {
        $norms = array();
        foreach ($urls as $url) {
            $norm = self::normalizeUrl($url);
            if ($norm !== '') {
                $norms[$norm] = true;
            }
        }
        if (empty($norms) || !self::dbInstall()) {
            return array();
        }

        try {
            $db = Typecho_Db::get();
            $hashToNorm = array();
            $hashes = array();
            foreach (array_keys($norms) as $norm) {
                $hash = sha1($norm);
                $hashToNorm[$hash] = $norm;
                $hashes[] = $hash;
            }
            // sha1 恒为 40 位十六进制，可安全内联拼接
            $hashes = array_values(array_filter($hashes, function ($hash) {
                return is_string($hash) && preg_match('/^[0-9a-f]{40}$/', $hash);
            }));
            if (empty($hashes)) {
                return array();
            }

            $meta = array();
            foreach (array_chunk($hashes, self::LOOKUP_CHUNK) as $chunk) {
                $rows = $db->fetchAll(
                    $db->select('url_hash', 'width', 'height', 'status', 'updated')
                        ->from(self::tableName())
                        ->where("url_hash IN ('" . implode("','", $chunk) . "')")
                );
                foreach ($rows as $row) {
                    $hash = isset($row['url_hash']) ? (string) $row['url_hash'] : '';
                    if ($hash === '' || !isset($hashToNorm[$hash])) {
                        continue;
                    }
                    $width = isset($row['width']) ? (int) $row['width'] : 0;
                    $height = isset($row['height']) ? (int) $row['height'] : 0;
                    $meta[$hashToNorm[$hash]] = array(
                        'ok' => isset($row['status']) && $row['status'] === 'ok' && $width > 0 && $height > 0,
                        'w' => $width,
                        'h' => $height,
                        'updated' => isset($row['updated']) ? (int) $row['updated'] : 0,
                    );
                }
            }
            return $meta;
        } catch (Exception $e) {
            return array();
        } catch (Throwable $e) {
            return array();
        }
    }

    /**
     * 记录浏览器回传或服务端解析出的尺寸（键为归一化 URL）。
     * 已有成功记录不被失败覆盖；数值相同则跳过写入，避免每次访问都写库。
     */
    public static function remember($url, $width, $height)
    {
        $norm = self::normalizeUrl($url);
        $width = (int) $width;
        $height = (int) $height;
        if ($norm === ''
            || $width < self::DIM_MIN || $width > self::DIM_MAX
            || $height < self::DIM_MIN || $height > self::DIM_MAX) {
            return false;
        }
        return self::writeMeta($norm, $width, $height, true);
    }

    private static function writeMeta($norm, $width, $height, $ok)
    {
        if (!self::dbInstall()) {
            return false;
        }

        try {
            $db = Typecho_Db::get();
            $table = self::tableName();
            $now = time();
            $hash = sha1($norm);
            $status = $ok ? 'ok' : 'fail';

            $row = $db->fetchRow(
                $db->select('width', 'height', 'status')
                    ->from($table)
                    ->where('url_hash = ?', $hash)
                    ->limit(1)
            );
            if (!empty($row)) {
                $wasOk = isset($row['status']) && $row['status'] === 'ok';
                if ($wasOk && !$ok) {
                    return false;
                }
                if ($wasOk && $ok && (int) $row['width'] === $width && (int) $row['height'] === $height) {
                    return true;
                }
                $db->query(
                    $db->update($table)
                        ->rows(array('width' => $width, 'height' => $height, 'status' => $status, 'updated' => $now))
                        ->where('url_hash = ?', $hash)
                );
                return true;
            }

            try {
                $db->query($db->insert($table)->rows(array(
                    'url_hash' => $hash,
                    'url' => $norm,
                    'width' => $width,
                    'height' => $height,
                    'status' => $status,
                    'updated' => $now,
                )));
                return true;
            } catch (Exception $e) {
                // 并发下唯一键冲突，退回更新
            } catch (Throwable $e) {
            }

            $db->query(
                $db->update($table)
                    ->rows(array('width' => $width, 'height' => $height, 'status' => $status, 'updated' => $now))
                    ->where('url_hash = ?', $hash)
            );
            return true;
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * 服务端解析图片尺寸：分段抓取头部字节后用 getimagesizefromstring 解析。
     * 首次 32KB 不够（如超大 EXIF / 渐进 JPEG）时补抓一次 256KB。
     * 返回 array('w' => int, 'h' => int) 或 null。
     */
    public static function fetchDimensions($url)
    {
        $norm = self::normalizeUrl($url);
        if ($norm === '') {
            return null;
        }

        foreach (array(self::FETCH_BYTES, self::FETCH_RETRY_BYTES) as $cap) {
            $data = self::fetchBytes($norm, $cap);
            if ($data === '') {
                return null;
            }
            $info = @getimagesizefromstring($data);
            if (is_array($info) && !empty($info[0]) && !empty($info[1])) {
                return array('w' => (int) $info[0], 'h' => (int) $info[1]);
            }
        }
        return null;
    }

    /**
     * 抓取 URL 前缀字节。curl 优先（写回调截断，服务器忽略 Range 时也不会
     * 拉满整图）；无 curl 时退回流封装。只读图片头部、不携带任何凭据，
     * 证书校验失败仅影响尺寸解析，因此放低为不校验以兼容缺少 CA 包的环境。
     */
    private static function fetchBytes($url, $maxBytes)
    {
        $maxBytes = (int) $maxBytes;
        if ($maxBytes <= 0) {
            return '';
        }

        if (function_exists('curl_init')) {
            try {
                $data = '';
                $ch = curl_init($url);
                if ($ch === false) {
                    return '';
                }
                curl_setopt_array($ch, array(
                    CURLOPT_HEADER => false,
                    CURLOPT_RETURNTRANSFER => false,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_TIMEOUT => self::FETCH_TIMEOUT,
                    CURLOPT_RANGE => '0-' . ($maxBytes - 1),
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$data, $maxBytes) {
                        $data .= $chunk;
                        return strlen($data) > $maxBytes + 65536 ? 0 : strlen($chunk);
                    },
                ));
                @curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($code >= 400) {
                    return '';
                }
                return $data;
            } catch (Exception $e) {
                return '';
            } catch (Throwable $e) {
                return '';
            }
        }

        try {
            $context = stream_context_create(array('http' => array(
                'header' => 'Range: bytes=0-' . ($maxBytes - 1) . "\r\n",
                'timeout' => self::FETCH_TIMEOUT,
                'follow_location' => 1,
                'max_redirects' => 3,
            )));
            $fh = @fopen($url, 'rb', false, $context);
            if (!$fh) {
                return '';
            }
            $data = stream_get_contents($fh, $maxBytes + 65536);
            fclose($fh);
            return is_string($data) ? $data : '';
        } catch (Exception $e) {
            return '';
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * 渲染时登记未命中（或失败过期）的 URL，请求结束后预热。
     * 内部自行查询缓存判断缺失，保证与 getMeta 的分块结果一致。
     */
    public static function warmMissing(array $urls)
    {
        try {
            if (!self::isActive()) {
                return;
            }

            $norms = array();
            foreach ($urls as $url) {
                $norm = self::normalizeUrl($url);
                if ($norm !== '') {
                    $norms[$norm] = true;
                }
            }
            if (empty($norms)) {
                return;
            }

            $keys = array_keys($norms);
            $meta = self::getMeta($keys);
            $now = time();
            $pending = array();
            foreach ($keys as $norm) {
                if (isset($meta[$norm])) {
                    $row = $meta[$norm];
                    if (!empty($row['ok'])) {
                        continue;
                    }
                    if (isset($row['updated']) && ((int) $row['updated'] + self::FAIL_RETRY_AFTER) > $now) {
                        continue;
                    }
                }
                $pending[] = $norm;
            }
            if (empty($pending)) {
                return;
            }

            self::$_warmQueue = array_slice(array_merge(self::$_warmQueue, $pending), 0, self::WARM_PER_REQUEST);
            if (!empty(self::$_warmQueue) && !self::$_warmScheduled) {
                self::$_warmScheduled = true;
                register_shutdown_function(array(__CLASS__, 'runWarmQueue'));
            }
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
    }

    /**
     * shutdown 阶段执行预热：先尝试 fastcgi_finish_request 把响应发给访客，
     * 再在时间预算内逐个抓取解析。
     */
    public static function runWarmQueue()
    {
        $queue = self::$_warmQueue;
        self::$_warmQueue = array();
        if (empty($queue)) {
            return;
        }

        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }

        $deadline = microtime(true) + self::WARM_TIME_BUDGET;
        foreach ($queue as $norm) {
            if (microtime(true) > $deadline) {
                break;
            }
            $dims = self::fetchDimensions($norm);
            if (is_array($dims)) {
                self::writeMeta($norm, $dims['w'], $dims['h'], true);
            } else {
                self::writeMeta($norm, 0, 0, false);
            }
        }
    }

    public static function dbInstall()
    {
        static $installed = null;
        if ($installed !== null) {
            return $installed;
        }

        try {
            $db = Typecho_Db::get();
            $table = self::tableName();
            $adapter = strtolower(get_class($db->getAdapter()));

            if (strpos($adapter, 'sqlite') !== false) {
                $sql = 'CREATE TABLE IF NOT EXISTS "' . $table . '" (
                    "url_hash" varchar(40) NOT NULL PRIMARY KEY,
                    "url" TEXT NOT NULL,
                    "width" INTEGER NOT NULL DEFAULT 0,
                    "height" INTEGER NOT NULL DEFAULT 0,
                    "status" varchar(8) NOT NULL DEFAULT "fail",
                    "updated" INTEGER NOT NULL DEFAULT 0
                )';
            } else {
                $sql = 'CREATE TABLE IF NOT EXISTS `' . $table . '` (
                    `url_hash` varchar(40) NOT NULL,
                    `url` varchar(1000) NOT NULL DEFAULT "",
                    `width` int(10) unsigned NOT NULL DEFAULT 0,
                    `height` int(10) unsigned NOT NULL DEFAULT 0,
                    `status` varchar(8) NOT NULL DEFAULT "fail",
                    `updated` int(10) unsigned NOT NULL DEFAULT 0,
                    PRIMARY KEY (`url_hash`),
                    KEY `updated` (`updated`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
            }

            $db->query($sql);
            $installed = true;
        } catch (Exception $e) {
            $installed = false;
        } catch (Throwable $e) {
            $installed = false;
        }
        return $installed;
    }
}
