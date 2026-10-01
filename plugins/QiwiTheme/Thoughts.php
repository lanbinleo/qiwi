<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Qiwi 段落想法模块：读者在文章正文框选一段文字，就地写下一条「想法」（轻评论），
 * 人工审核通过后对所有人可见。数据单独存放于 qiwi_thoughts 表，不与 Typecho
 * 评论表耦合；游客内容按纯文本 + 表情包渲染，与评论区安全模型保持一致。
 *
 * 想法只存「字符轴」上的起止偏移：正文所有可批注文本（排除代码块）按文档序
 * 拼成一条长字符串，[start, end) 即选区。前端负责把偏移换算回 DOM 并渲染虚线
 * 标记（原文改动时前端用锚点就地重定位），本类只做数字区间的重叠校验与长度兜底，
 * 不在后端重算字符轴 —— 渲染与提交两端都以浏览器 DOM 为准。
 *
 * @package QiwiTheme
 * @author  Leo 里奥
 * @version 2.3.0
 * @link    https://bboreo.com/
 */
class QiwiTheme_Thoughts
{
    const TABLE = 'qiwi_thoughts';

    // 选区前后锚点长度（字符）
    const ANCHOR_LENGTH = 32;
    // 选区/想法长度硬上限，配置值只能比它小
    const SELECTION_HARD_MAX = 500;
    const TEXT_HARD_MAX = 1000;
    // 表情包 token 硬上限
    const AUTHOR_MAX = 64;

    private static $_cfgCache = null;

    public static function tableName()
    {
        $db = Typecho_Db::get();
        return $db->getPrefix() . self::TABLE;
    }

    public static function dbInstall()
    {
        try {
            $db = Typecho_Db::get();
            $table = self::tableName();
            $adapter = strtolower(get_class($db->getAdapter()));

            if (strpos($adapter, 'sqlite') !== false) {
                $sql = 'CREATE TABLE IF NOT EXISTS "' . $table . '" (
                    "id" INTEGER PRIMARY KEY AUTOINCREMENT,
                    "cid" INTEGER NOT NULL,
                    "axis_start" INTEGER NOT NULL DEFAULT 0,
                    "axis_end" INTEGER NOT NULL DEFAULT 0,
                    "quote" TEXT NOT NULL DEFAULT "",
                    "anchor_before" varchar(128) NOT NULL DEFAULT "",
                    "anchor_after" varchar(128) NOT NULL DEFAULT "",
                    "author" varchar(64) NOT NULL DEFAULT "",
                    "mail" varchar(200) NOT NULL DEFAULT "",
                    "mail_hash" varchar(64) NOT NULL DEFAULT "",
                    "user_id" INTEGER NOT NULL DEFAULT 0,
                    "text" TEXT NOT NULL,
                    "status" varchar(16) NOT NULL DEFAULT "waiting",
                    "ip" varchar(64) NOT NULL DEFAULT "",
                    "ip_hash" varchar(64) NOT NULL DEFAULT "",
                    "user_agent" varchar(500) NOT NULL DEFAULT "",
                    "created" INTEGER NOT NULL DEFAULT 0
                )';
                $indexes = array(
                    'CREATE INDEX IF NOT EXISTS "' . $table . '_cid_status" ON "' . $table . '" ("cid", "status")',
                    'CREATE INDEX IF NOT EXISTS "' . $table . '_ip_created" ON "' . $table . '" ("ip_hash", "created")',
                    'CREATE INDEX IF NOT EXISTS "' . $table . '_status_created" ON "' . $table . '" ("status", "created")',
                );
            } else {
                $sql = 'CREATE TABLE IF NOT EXISTS `' . $table . '` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `cid` int(10) unsigned NOT NULL,
                    `axis_start` int(10) unsigned NOT NULL DEFAULT 0,
                    `axis_end` int(10) unsigned NOT NULL DEFAULT 0,
                    `quote` text NOT NULL,
                    `anchor_before` varchar(128) NOT NULL DEFAULT "",
                    `anchor_after` varchar(128) NOT NULL DEFAULT "",
                    `author` varchar(64) NOT NULL DEFAULT "",
                    `mail` varchar(200) NOT NULL DEFAULT "",
                    `mail_hash` varchar(64) NOT NULL DEFAULT "",
                    `user_id` int(10) unsigned NOT NULL DEFAULT 0,
                    `text` text NOT NULL,
                    `status` varchar(16) NOT NULL DEFAULT "waiting",
                    `ip` varchar(64) NOT NULL DEFAULT "",
                    `ip_hash` varchar(64) NOT NULL DEFAULT "",
                    `user_agent` varchar(500) NOT NULL DEFAULT "",
                    `created` int(10) unsigned NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    KEY `cid_status` (`cid`, `status`),
                    KEY `ip_created` (`ip_hash`, `created`),
                    KEY `status_created` (`status`, `created`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
                $indexes = array();
            }

            $db->query($sql);
            foreach ($indexes as $indexSql) {
                $db->query($indexSql);
            }
            return true;
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * 批量读取 thoughts* 主题配置（JSON 优先 + unserialize 回退），带默认值与静态缓存。
     */
    public static function cfg()
    {
        if (self::$_cfgCache !== null) {
            return self::$_cfgCache;
        }

        $map = class_exists('QiwiTheme_Plugin') ? QiwiTheme_Plugin::getThemeOptionMap() : null;
        if (!is_array($map)) {
            $map = array();
        }

        $cfg = new stdClass();
        $keys = array(
            'thoughtsEnabled' => '1',
            'thoughtsMaxLength' => '200',
            'thoughtsMinSelection' => '2',
            'thoughtsMaxSelection' => '200',
            'thoughtsSubmitInterval' => '60',
            'thoughtsPendingLimit' => '20',
            'thoughtsMailNotify' => '1',
        );

        foreach ($keys as $key => $default) {
            $value = isset($map[$key]) ? $map[$key] : null;
            $cfg->{$key} = ($value !== null && $value !== '' && !is_array($value)) ? (string) $value : $default;
        }

        self::$_cfgCache = $cfg;
        return $cfg;
    }

    public static function enabled()
    {
        return self::cfg()->thoughtsEnabled === '1';
    }

    public static function cfgInt($key, $default, $min, $max)
    {
        $value = (int) self::cfg()->{$key};
        if ($value < $min) {
            $value = (int) $min;
        }
        if ($value > $max) {
            $value = (int) $max;
        }
        return $value;
    }

    public static function maxLength()
    {
        return self::cfgInt('thoughtsMaxLength', 200, 1, self::TEXT_HARD_MAX);
    }

    public static function minSelection()
    {
        return self::cfgInt('thoughtsMinSelection', 2, 1, self::SELECTION_HARD_MAX);
    }

    public static function maxSelection()
    {
        return self::cfgInt('thoughtsMaxSelection', 200, self::minSelection(), self::SELECTION_HARD_MAX);
    }

    public static function mailHash($mail)
    {
        $mail = strtolower(trim((string) $mail));
        return $mail === '' ? '' : sha1($mail);
    }

    /**
     * 提交一条想法。返回 array('ok' => bool, 'message' => string, 'thought' => array|null)。
     * 所有展示前均有人工审核兜底，这里的服务端校验只做长度 / 频率 / 重叠的数字与文本边界。
     */
    public static function submit(array $input)
    {
        if (!self::enabled()) {
            return array('ok' => false, 'message' => '想法功能未启用。', 'thought' => null);
        }

        $db = Typecho_Db::get();
        if (!self::dbInstall()) {
            return array('ok' => false, 'message' => '想法数据表尚未就绪，请联系站长。', 'thought' => null);
        }
        $table = self::tableName();

        $cid = (int) (isset($input['cid']) ? $input['cid'] : 0);
        $start = (int) (isset($input['start']) ? $input['start'] : 0);
        $end = (int) (isset($input['end']) ? $input['end'] : 0);
        $quote = (string) (isset($input['quote']) ? $input['quote'] : '');
        $text = trim((string) (isset($input['text']) ? $input['text'] : ''));
        $author = trim((string) (isset($input['author']) ? $input['author'] : ''));
        $mail = strtolower(trim((string) (isset($input['mail']) ? $input['mail'] : '')));
        $isAdmin = !empty($input['isAdmin']);
        $userId = (int) (isset($input['userId']) ? $input['userId'] : 0);

        if ($cid <= 0 || $start < 0 || $end <= $start) {
            return array('ok' => false, 'message' => '请先选择一段文字再写想法。', 'thought' => null);
        }

        $length = $end - $start;
        $minSel = self::minSelection();
        $maxSel = self::maxSelection();
        if ($length < $minSel) {
            return array('ok' => false, 'message' => '选择的文字太短了，至少需要 ' . $minSel . ' 个字符。', 'thought' => null);
        }
        if ($length > $maxSel) {
            return array('ok' => false, 'message' => '选择的文字太长了，最多 ' . $maxSel . ' 个字符。', 'thought' => null);
        }
        if (mb_strlen($quote, 'UTF-8') !== $length || preg_match('~[\x00-\x08\x0B\x0C\x0E-\x1F]~u', $quote)) {
            return array('ok' => false, 'message' => '选区信息校验失败，请重新选择文字。', 'thought' => null);
        }

        if ($author === '' || mb_strlen($author, 'UTF-8') > self::AUTHOR_MAX) {
            return array('ok' => false, 'message' => $author === '' ? '称呼不能为空。' : '称呼太长了。', 'thought' => null);
        }
        if ($mail !== '' && (!preg_match('/^[_a-z0-9-\.+]+@[_a-z0-9-]+\.[_a-z0-9-]+$/i', $mail) || mb_strlen($mail) > 200)) {
            return array('ok' => false, 'message' => '邮箱格式不正确。', 'thought' => null);
        }

        $maxLength = self::maxLength();
        if ($text === '' || mb_strlen($text, 'UTF-8') > $maxLength) {
            return array('ok' => false, 'message' => $text === '' ? '想法内容不能为空。' : '想法最多 ' . $maxLength . ' 个字。', 'thought' => null);
        }
        if (preg_match('~[\x00-\x08\x0B\x0C\x0E-\x1F]~u', $text)) {
            return array('ok' => false, 'message' => '想法内容包含非法字符。', 'thought' => null);
        }

        $ip = trim((string) (isset($input['ip']) ? $input['ip'] : ''));
        $ipHash = $ip === '' ? '' : sha1($ip);
        $userAgent = mb_substr(trim((string) (isset($input['userAgent']) ? $input['userAgent'] : '')), 0, 500, 'UTF-8');

        try {
            // 频控与待审上限：管理员豁免（作者本人内容直接发布）
            if (!$isAdmin) {
                $interval = self::cfgInt('thoughtsSubmitInterval', 60, 5, 86400);
                if ($ipHash !== '' && $interval > 0) {
                    $recent = $db->fetchObject($db->select(array('COUNT(*)' => 'total'))
                        ->from($table)
                        ->where('ip_hash = ?', $ipHash)
                        ->where('created > ?', time() - $interval)
                        ->limit(1));
                    if ($recent && (int) $recent->total > 0) {
                        return array('ok' => false, 'message' => '提交太频繁了，请 ' . $interval . ' 秒后再试。', 'thought' => null);
                    }
                }

                $pendingLimit = self::cfgInt('thoughtsPendingLimit', 20, 1, 200);
                $pending = $db->fetchObject($db->select(array('COUNT(*)' => 'total'))
                    ->from($table)
                    ->where('cid = ?', $cid)
                    ->where('status = ?', 'waiting')
                    ->limit(1));
                if ($pending && (int) $pending->total >= $pendingLimit) {
                    return array('ok' => false, 'message' => '这篇文章的待审核想法已满，稍后再试试。', 'thought' => null);
                }
            }

            // 重叠校验：与现有任何区间（待审 + 已通过）相交即拒绝；完全相同区间 = 同一选区追加想法，放行
            $existing = $db->fetchAll($db->select('axis_start', 'axis_end')
                ->from($table)
                ->where('cid = ?', $cid)
                ->where('status IN ?', array('waiting', 'approved')));
            foreach ($existing as $row) {
                $rowStart = (int) $row['axis_start'];
                $rowEnd = (int) $row['axis_end'];
                if ($start === $rowStart && $end === $rowEnd) {
                    continue;
                }
                if ($start < $rowEnd && $end > $rowStart) {
                    return array('ok' => false, 'message' => '这段文字已有想法，不能与其他想法的选区重叠。', 'thought' => null);
                }
            }

            $status = $isAdmin ? 'approved' : 'waiting';
            $created = time();
            $row = array(
                'cid' => $cid,
                'axis_start' => $start,
                'axis_end' => $end,
                'quote' => $quote,
                'anchor_before' => mb_substr((string) $input['anchorBefore'], 0, self::ANCHOR_LENGTH, 'UTF-8'),
                'anchor_after' => mb_substr((string) $input['anchorAfter'], 0, self::ANCHOR_LENGTH, 'UTF-8'),
                'author' => $author,
                'mail' => $mail,
                'mail_hash' => self::mailHash($mail),
                'user_id' => $userId,
                'text' => $text,
                'status' => $status,
                'ip' => $ip,
                'ip_hash' => $ipHash,
                'user_agent' => $userAgent,
                'created' => $created,
            );
            // Typecho 的 Db::query() 对 INSERT 直接返回自增 id
            $insertId = (int) $db->query($db->insert($table)->rows($row));

            $row['id'] = $insertId > 0 ? $insertId : 0;
            return array('ok' => true, 'message' => $isAdmin ? '想法已发布。' : '想法已提交，审核通过后会对所有人可见。', 'thought' => self::toFront($row));
        } catch (Exception $e) {
            return array('ok' => false, 'message' => '想法提交失败，请稍后再试。', 'thought' => null);
        } catch (Throwable $e) {
            return array('ok' => false, 'message' => '想法提交失败，请稍后再试。', 'thought' => null);
        }
    }

    /**
     * 文章页数据：已通过 + 自己的待审（ownIds 来自签名 cookie）。
     */
    public static function thoughtsForPost($cid, array $ownIds = array())
    {
        $cid = (int) $cid;
        if ($cid <= 0 || !self::dbInstall()) {
            return array();
        }

        $ownIds = array_values(array_filter(array_map('intval', $ownIds), function ($id) {
            return $id > 0;
        }));

        try {
            $db = Typecho_Db::get();
            $select = $db->select('*')
                ->from(self::tableName())
                ->where('cid = ?', $cid);
            if (empty($ownIds)) {
                $select->where('status = ?', 'approved');
            } else {
                $select->where("(status = 'approved' OR (status = 'waiting' AND id IN (" . implode(',', array_map('intval', $ownIds)) . ")))");
            }
            $rows = $db->fetchAll($select->order('created', Typecho_Db::SORT_ASC)->order('id', Typecho_Db::SORT_ASC));
            return is_array($rows) ? $rows : array();
        } catch (Exception $e) {
            return array();
        } catch (Throwable $e) {
            return array();
        }
    }

    /**
     * 已通过想法数（文章 meta 计数用）。
     */
    public static function thoughtCounts(array $cids)
    {
        $ids = array();
        foreach ($cids as $cid) {
            $cid = (int) $cid;
            if ($cid > 0) {
                $ids[$cid] = $cid;
            }
        }
        if (empty($ids) || !self::dbInstall()) {
            return array();
        }

        try {
            $db = Typecho_Db::get();
            $rows = $db->fetchAll($db->select('cid', array('COUNT(*)' => 'total'))
                ->from(self::tableName())
                ->where('cid IN (' . implode(',', array_map('intval', $ids)) . ')')
                ->where('status = ?', 'approved')
                ->group('cid'));
            $counts = array();
            foreach ((array) $rows as $row) {
                $counts[(int) $row['cid']] = (int) $row['total'];
            }
            return $counts;
        } catch (Exception $e) {
            return array();
        } catch (Throwable $e) {
            return array();
        }
    }

    public static function approve($id)
    {
        $id = (int) $id;
        if ($id <= 0 || !self::dbInstall()) {
            return false;
        }

        try {
            $db = Typecho_Db::get();
            $db->query($db->update(self::tableName())
                ->rows(array('status' => 'approved'))
                ->where('id = ?', $id)
                ->where('status = ?', 'waiting'));
            return true;
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function deleteThought($id)
    {
        $id = (int) $id;
        if ($id <= 0 || !self::dbInstall()) {
            return false;
        }

        try {
            $db = Typecho_Db::get();
            $db->query($db->delete(self::tableName())->where('id = ?', $id));
            return true;
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function getThought($id)
    {
        $id = (int) $id;
        if ($id <= 0 || !self::dbInstall()) {
            return null;
        }

        try {
            $db = Typecho_Db::get();
            $row = $db->fetchRow($db->select()->from(self::tableName())->where('id = ?', $id)->limit(1));
            return $row ? $row : null;
        } catch (Exception $e) {
            return null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * 想法内容渲染：游客 = 纯文本 + 表情包 + 分段（与评论区一致，不开放 Markdown）；
     * 登录作者（user_id>0）= 受信渲染。主题函数不可用时退化为纯转义。
     */
    public static function renderContent(array $row)
    {
        $text = (string) (isset($row['text']) ? $row['text'] : '');
        if ((int) (isset($row['user_id']) ? $row['user_id'] : 0) > 0 && function_exists('qiwiRenderTrustedCommentContent')) {
            return qiwiRenderTrustedCommentContent($text);
        }
        if (function_exists('qiwiRenderPlainCommentContent')) {
            return qiwiRenderPlainCommentContent($text);
        }
        return '<p>' . nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) . '</p>';
    }

    /**
     * 组装文章页嵌入 JSON：配置 + 想法条目（内容在服务端渲染成 HTML）。
     */
    public static function pagePayload($cid, array $ownIds = array())
    {
        $rows = self::thoughtsForPost($cid, $ownIds);
        $items = array();
        foreach ($rows as $row) {
            $items[] = self::toFront($row);
        }

        return array(
            'cid' => (int) $cid,
            'minSelection' => self::minSelection(),
            'maxSelection' => self::maxSelection(),
            'maxLength' => self::maxLength(),
            'items' => $items,
        );
    }

    public static function toFront(array $row)
    {
        $mail = (string) (isset($row['mail']) ? $row['mail'] : '');
        $avatar = '';
        if ($mail !== '' && function_exists('qiwiGetCommentAvatarUrl')) {
            $avatar = qiwiGetCommentAvatarUrl($mail, 48);
        }

        return array(
            'id' => (int) $row['id'],
            'cid' => (int) $row['cid'],
            'start' => (int) $row['axis_start'],
            'end' => (int) $row['axis_end'],
            'quote' => (string) $row['quote'],
            'anchorBefore' => (string) (isset($row['anchor_before']) ? $row['anchor_before'] : ''),
            'anchorAfter' => (string) (isset($row['anchor_after']) ? $row['anchor_after'] : ''),
            'status' => (string) $row['status'],
            'author' => (string) (isset($row['author']) ? $row['author'] : ''),
            'authorLabel' => (int) (isset($row['user_id']) ? $row['user_id'] : 0) > 0 ? '作者' : '',
            'avatar' => $avatar,
            'html' => self::renderContent($row),
            'date' => !empty($row['created']) ? date('Y-m-d H:i', (int) $row['created']) : '',
        );
    }

    /**
     * 提交成功后，把本浏览器提交过的想法 id 写入签名 cookie，
     * 模板据此向访客展示"自己的待审核想法"。与 own-comments 同一套签名体系。
     */
    public static function rememberOwnThought($id)
    {
        $id = (int) $id;
        if ($id <= 0 || headers_sent() || !class_exists('QiwiTheme_Plugin')) {
            return;
        }

        $ids = array_values(array_diff(self::ownThoughtIds(), array($id)));
        $ids[] = $id;
        $ids = array_slice($ids, -50);
        $payload = implode('.', $ids);
        $value = $payload . '|' . QiwiTheme_Plugin::signValue('own-thoughts', $payload);
        setcookie('qiwi_own_thoughts', $value, time() + 30 * 86400, '/', '', false, true);
        $_COOKIE['qiwi_own_thoughts'] = $value;
    }

    public static function ownThoughtIds()
    {
        $raw = isset($_COOKIE['qiwi_own_thoughts']) ? (string) $_COOKIE['qiwi_own_thoughts'] : '';
        if ($raw === '' || strlen($raw) > 1024 || strpos($raw, '|') === false || !class_exists('QiwiTheme_Plugin')) {
            return array();
        }

        list($payload, $signature) = explode('|', $raw, 2);
        if (!preg_match('/^\d+(?:\.\d+)*$/', $payload) || !QiwiTheme_Plugin::verifySignedValue('own-thoughts', $payload, $signature)) {
            return array();
        }

        $ids = array();
        foreach (explode('.', $payload) as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}
