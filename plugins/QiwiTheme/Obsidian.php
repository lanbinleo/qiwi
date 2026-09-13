<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 里程页「写作经历」私人随笔层：接收 Obsidian 写作数据并只读缓存。
 *
 * 自包含模块，刻意不依赖 QiwiTheme_Plugin 的内部方法，便于随插件重构平移。
 *
 * 数据流：本地采集端（tools/obsidian-sync/push-writing.ps1，未来可为 Obsidian 插件）
 * 携带共享令牌 POST {schema:2, days:{'Y-m-d':篇数}, replace?:bool} 到 do=obsidian-push
 * 端点；校验通过后写入独立 options 行，页面渲染仅通过 peekStats() 只读，零网络。
 *
 * 防覆盖语义：默认 merge——推送只更新 payload 中出现的日子，缺席的日子永远保留，
 * 一次错误的重扫抹不掉历史；仅显式 replace:true（脚本 -Rebuild 人工触发）才全量替换。
 * 隐私红线：这里只存「日期→篇数」聚合，任何标题/内容/路径都不应到达本模块。
 */
class QiwiTheme_Obsidian
{
    const CACHE_OPTION = 'qiwi_theme_obsidian_cache';
    const MAX_DAYS = 4000;
    const MAX_COUNT = 999;
    const MAX_BODY_BYTES = 65536;

    /**
     * 页面渲染专用：只读缓存，绝不发起网络请求。
     *
     * @return array|null {daily: ['Y-m-d' => 篇数], pushedAt: int}，无数据时 null
     */
    public static function peekStats()
    {
        $cache = self::readCache();
        if (!isset($cache['daily']) || !is_array($cache['daily']) || empty($cache['daily'])) {
            return null;
        }

        return array(
            'daily' => $cache['daily'],
            'pushedAt' => isset($cache['pushedAt']) ? (int) $cache['pushedAt'] : 0,
        );
    }

    /**
     * do=obsidian-push 端点处理。
     *
     * @return array [响应数组, HTTP 状态码]
     */
    public static function handlePush()
    {
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return array(array('success' => false, 'message' => 'Method not allowed'), 405);
        }

        $token = self::themeOption('obsidianPushToken');
        if ($token === '') {
            return array(array('success' => false, 'message' => 'Push disabled'), 403);
        }
        $provided = isset($_SERVER['HTTP_X_QIWI_TOKEN']) ? (string) $_SERVER['HTTP_X_QIWI_TOKEN'] : '';
        if ($provided === '' || !hash_equals($token, $provided)) {
            return array(array('success' => false, 'message' => 'Forbidden'), 403);
        }

        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return array(array('success' => false, 'message' => 'Empty body'), 400);
        }
        if (strlen($raw) > self::MAX_BODY_BYTES) {
            return array(array('success' => false, 'message' => 'Payload too large'), 413);
        }

        $body = json_decode($raw, true);
        if (!is_array($body) || !isset($body['days']) || !is_array($body['days'])) {
            return array(array('success' => false, 'message' => 'Bad payload'), 400);
        }
        if (!isset($body['schema']) || (int) $body['schema'] !== 2) {
            return array(array('success' => false, 'message' => 'Unsupported schema'), 400);
        }

        $incoming = array();
        foreach ($body['days'] as $day => $count) {
            $day = (string) $day;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
                continue;
            }
            $count = (int) $count;
            if ($count <= 0) {
                continue;
            }
            $incoming[$day] = min($count, self::MAX_COUNT);
        }
        if (empty($incoming)) {
            return array(array('success' => false, 'message' => 'No valid days'), 400);
        }
        if (count($incoming) > self::MAX_DAYS) {
            return array(array('success' => false, 'message' => 'Too many days'), 400);
        }

        $replace = !empty($body['replace']);
        $cache = self::readCache();
        $existing = (isset($cache['daily']) && is_array($cache['daily'])) ? $cache['daily'] : array();
        // merge：payload 覆盖同名日，缺席的旧日保留；仅 replace:true 整包替换
        $merged = $replace ? $incoming : array_merge($existing, $incoming);
        ksort($merged);
        if (count($merged) > self::MAX_DAYS) {
            // 按日期升序裁掉最旧的超额日，防止缓存行无限膨胀
            $merged = array_slice($merged, -self::MAX_DAYS, null, true);
        }

        self::writeCache(array('pushedAt' => time(), 'daily' => $merged));

        return array(array(
            'success' => true,
            'days' => count($merged),
            'updated' => count($incoming),
            'retained' => !$replace,
        ), 200);
    }

    /**
     * 读主题配置（与 QiwiTheme_Plugin::getThemeOption 同逻辑：
     * 本地 Typecho 把主题配置存成 JSON，旧站可能是 PHP serialize）。
     * 刻意本地实现以隔离插件重构带来的变动。
     */
    private static function themeOption($name, $default = '')
    {
        try {
            $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
            $row = $db->fetchRow($db->select('value')
                ->from('table.options')
                ->where('name = ?', 'theme:qiwi')
                ->limit(1));
        } catch (Exception $e) {
            return $default;
        } catch (Throwable $e) {
            return $default;
        }

        if (empty($row['value'])) {
            return $default;
        }

        $raw = (string) $row['value'];
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = @unserialize($raw);
        }
        if (!is_array($data) || !isset($data[$name]) || $data[$name] === null || (string) $data[$name] === '') {
            return $default;
        }

        return (string) $data[$name];
    }

    private static function readCache()
    {
        try {
            $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
            $row = $db->fetchRow($db->select('value')
                ->from('table.options')
                ->where('name = ?', self::CACHE_OPTION)
                ->limit(1));
        } catch (Exception $e) {
            return array();
        } catch (Throwable $e) {
            return array();
        }

        if (empty($row['value'])) {
            return array();
        }

        $data = @unserialize((string) $row['value']);
        return is_array($data) ? $data : array();
    }

    private static function writeCache(array $data)
    {
        try {
            $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
            $existing = $db->fetchRow($db->select('name')
                ->from('table.options')
                ->where('name = ?', self::CACHE_OPTION)
                ->limit(1));
            if (!empty($existing)) {
                $db->query($db->update('table.options')
                    ->rows(array('value' => serialize($data)))
                    ->where('name = ?', self::CACHE_OPTION));
            } else {
                $db->query($db->insert('table.options')
                    ->rows(array(
                        'name' => self::CACHE_OPTION,
                        'user' => 0,
                        'value' => serialize($data),
                    )));
            }
        } catch (Exception $e) {
            return;
        } catch (Throwable $e) {
            return;
        }
    }
}
