<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class QiwiTheme_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function execute()
    {
        if ($this->isMomentLikeRequest() || $this->isPostLikeRequest() || $this->isExternalLinkRequest() || $this->isAttachmentDownloadRequest()) {
            return;
        }

        Typecho_Widget::widget('Widget_User')->pass('editor');
    }

    public function action()
    {
        if ($this->request->is('do=external-link')) {
            $this->externalLink();
        }
        if ($this->request->is('do=attachment-download')) {
            $this->attachmentDownload();
        }
        if ($this->request->is('do=redact-reveal')) {
            $this->redactReveal();
        }

        Typecho_Widget::widget('Widget_Security')->protect();
        $this->on($this->request->is('do=read-thread'))->readThread();
        $this->on($this->request->is('do=save-thread'))->saveThread();
        $this->on($this->request->is('do=posts'))->posts();
        $this->on($this->request->is('do=moment-like'))->momentLike();
        $this->on($this->request->is('do=post-like'))->postLike();
        $this->on($this->request->is('do=rebuild-ip-locations'))->rebuildIpLocations();
        $this->json(array('success' => false, 'message' => 'Unknown action'), 404);
    }

    public function externalLink()
    {
        if (!$this->request->isPost()) {
            $this->json(array('success' => false, 'message' => 'Method not allowed'), 405);
        }

        Typecho_Widget::widget('Widget_Security')->protect();
        $url = trim((string) $this->request->get('url', ''));
        $source = trim((string) $this->request->get('source', ''));
        $ok = QiwiTheme_Plugin::recordExternalLinkClick($url, $source);
        $this->json(array('success' => $ok));
    }

    public function attachmentDownload()
    {
        if (!$this->request->isPost()) {
            $this->json(array('success' => false, 'message' => '仅支持 POST 下载请求。'), 405);
        }

        $attachmentId = (int) $this->request->get('attachment_id', 0);
        $contentId = (int) $this->request->get('content_id', 0);
        $requestedName = $this->request->get('download_name', '');
        $captchaState = $this->attachmentCaptchaState();
        if ($captchaState['mode'] === 'error') {
            $this->json(array('success' => false, 'message' => $captchaState['message']), 503);
        }
        if ($captchaState['mode'] === 'required' && !$this->verifyAttachmentCaptcha()) {
            $this->json(array('success' => false, 'message' => '人机验证未通过或已经失效，请重新验证。'), 403);
        }

        $attachment = $this->publicAttachment($attachmentId, $contentId);
        if (empty($attachment)) {
            $this->json(array('success' => false, 'message' => '附件不存在或不属于当前内容。'), 404);
        }

        $path = $this->attachmentLocalPath($attachment['path']);
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            $this->json(array('success' => false, 'message' => '附件文件当前不可读取。'), 404);
        }

        $originalName = isset($attachment['name']) && trim((string) $attachment['name']) !== ''
            ? trim((string) $attachment['name'])
            : 'attachment.' . (isset($attachment['type']) ? $attachment['type'] : 'bin');
        $name = $this->attachmentDownloadName($requestedName, $originalName, isset($attachment['type']) ? $attachment['type'] : '');
        $mime = isset($attachment['mime']) && trim((string) $attachment['mime']) !== ''
            ? trim((string) $attachment['mime'])
            : 'application/octet-stream';
        $fallbackName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
        if ($fallbackName === '') {
            $fallbackName = 'attachment';
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . str_replace(array("\r", "\n"), '', $mime));
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: attachment; filename="' . $fallbackName . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        header('X-Qiwi-Attachment: 1');
        readfile($path);
        exit;
    }

    public function redactReveal()
    {
        if (!$this->request->isPost()) {
            $this->json(array('success' => false, 'message' => 'Method not allowed'), 405);
        }

        // 该接口只读不写，身份与签名即全部防线；不依赖 referer 校验。
        $user = Typecho_Widget::widget('Widget_User');
        if (!$user->hasLogin() || !$user->pass('administrator', true)) {
            $this->json(array('success' => false, 'message' => '仅管理员可查看涂黑内容。'), 403);
        }

        $cid = (int) $this->request->get('cid', 0);
        $index = (int) $this->request->get('index', 0);
        $sign = trim((string) $this->request->get('sign', ''));
        if ($cid <= 0 || $index <= 0 || $sign === '') {
            $this->json(array('success' => false, 'message' => '参数无效。'), 400);
        }

        $db = Typecho_Db::get();
        $row = $db->fetchRow(
            $db->select('text')
                ->from('table.contents')
                ->where('cid = ?', $cid)
                ->limit(1)
        );
        if (empty($row) || !isset($row['text'])) {
            $this->json(array('success' => false, 'message' => '内容不存在。'), 404);
        }

        $rawText = (string) $row['text'];
        $value = $cid . ':' . $index . ':' . substr(md5($rawText), 0, 8);
        if (!QiwiTheme_Plugin::verifySignedValue('redact-reveal', $value, $sign)) {
            $this->json(array('success' => false, 'message' => '凭证无效或内容已修改。'), 403);
        }

        // 与渲染侧完全相同的涂黑正则，按出现顺序取第 index 段；
        // 极端情况下（代码块内也写 ||…||）索引可能与页面顺序错位，仅影响管理员预览。
        if (!preg_match_all('/(?<![A-Za-z0-9_\/])\|\|(?=[^\s|])((?:[^|\n]|\|(?!\|))*?)(?<!\s)\|\|(?!\|)/iu', $rawText, $matches) || !isset($matches[1][$index - 1])) {
            $this->json(array('success' => false, 'message' => '未找到对应的涂黑内容。'), 404);
        }

        $text = trim($matches[1][$index - 1]);
        $this->response->setHeader('Cache-Control', 'no-store');
        $this->json(array('success' => true, 'text' => $text));
    }

    public function readThread()
    {
        $mid = (int) $this->request->get('mid', 0);
        $this->json(array(
            'success' => true,
            'mid' => $mid,
            'data' => QiwiTheme_Plugin::getThreadData($mid),
        ));
    }

    public function saveThread()
    {
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $this->request->from('mid', 'data');
        }

        $mid = isset($payload['mid']) ? (int) $payload['mid'] : 0;
        $data = isset($payload['data']) ? (string) $payload['data'] : '';
        $decoded = json_decode($data, true);
        if ($mid <= 0 || !is_array($decoded) || !isset($decoded['schema']) || $decoded['schema'] !== 'qiwi-thread') {
            $this->json(array('success' => false, 'message' => 'Invalid Thread payload'), 400);
        }

        try {
            if (!QiwiTheme_Plugin::saveThreadData($mid, $data)) {
                $this->json(array('success' => false, 'message' => 'Thread data was not saved'), 500);
            }
        } catch (Exception $e) {
            $this->json(array('success' => false, 'message' => 'Database error while saving Thread data'), 500);
        }

        $this->json(array('success' => true, 'mid' => $mid));
    }

    public function posts()
    {
        $query = trim((string) $this->request->get('q', ''));
        $page = max(1, (int) $this->request->get('page', 1));
        $limit = min(30, max(6, (int) $this->request->get('limit', 12)));
        $offset = ($page - 1) * $limit;

        $db = Typecho_Db::get();
        Typecho_Widget::widget('Widget_Options')->to($options);
        $select = $db->select('cid', 'title', 'slug', 'created', 'modified', 'text')
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->where('status = ?', 'publish')
            ->where('(password IS NULL OR password = ?)', '')
            ->where('created < ?', $options->gmtTime)
            ->order('created', Typecho_Db::SORT_DESC)
            ->limit($limit)
            ->offset($offset);

        if ($query !== '') {
            $like = '%' . str_replace(array('%', '_'), array('\\%', '\\_'), $query) . '%';
            if (ctype_digit($query)) {
                $select->where('(cid = ? OR title LIKE ? OR slug LIKE ? OR text LIKE ?)', (int) $query, $like, $like, $like);
            } else {
                $select->where('(title LIKE ? OR slug LIKE ? OR text LIKE ?)', $like, $like, $like);
            }
        }

        $rows = $db->fetchAll($select);
        $items = array();
        foreach ($rows as $row) {
            $items[] = array(
                'cid' => (int) $row['cid'],
                'title' => (string) $row['title'],
                'slug' => (string) $row['slug'],
                'created' => (int) $row['created'],
                'date' => date('Y-m-d', (int) $row['created']),
                'excerpt' => $this->excerpt((string) $row['text']),
                'permalink' => $this->permalink($row),
            );
        }

        $this->json(array(
            'success' => true,
            'items' => $items,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => count($items) === $limit,
        ));
    }

    public function momentLike()
    {
        if (!$this->request->isPost()) {
            $this->json(array('success' => false, 'message' => 'Method not allowed'), 405);
        }

        $coid = (int) $this->request->get('coid', 0);
        if (!$this->isPublicMoment($coid)) {
            $this->json(array('success' => false, 'message' => 'Moment not found'), 404);
        }

        $result = QiwiTheme_Plugin::toggleMomentLike($coid, $this->momentLikeIdentity());
        $payload = array(
            'success' => true,
            'coid' => $coid,
            'liked' => !empty($result['liked']),
            'count' => isset($result['count']) ? (int) $result['count'] : 0,
        );
        if (!empty($result['proof'])) {
            $payload['proof'] = (string) $result['proof'];
        }
        if (!empty($result['protected'])) {
            $payload['protected'] = true;
            $payload['message'] = '请在点赞时使用的设备上取消点赞';
        }
        $this->json($payload);
    }

    public function postLike()
    {
        if (!$this->request->isPost()) {
            $this->json(array('success' => false, 'message' => 'Method not allowed'), 405);
        }

        $cid = (int) $this->request->get('cid', 0);
        if (!$this->isPublicPost($cid)) {
            $this->json(array('success' => false, 'message' => 'Post not found'), 404);
        }

        $result = QiwiTheme_Plugin::addPostLike($cid, $this->postLikeIdentity());
        $this->json(array(
            'success' => true,
            'cid' => $cid,
            'liked' => !empty($result['liked']),
            'created' => !empty($result['created']),
            'count' => isset($result['count']) ? (int) $result['count'] : 0,
        ));
    }

    public function rebuildIpLocations()
    {
        if (!$this->request->isPost()) {
            $this->json(array('success' => false, 'message' => 'Method not allowed'), 405);
        }

        $limit = (int) $this->request->get('limit', 20);
        $mode = trim((string) $this->request->get('mode', 'missing'));
        $result = QiwiTheme_Plugin::rebuildIpLocationCache($limit, $mode);
        $this->json(array(
            'success' => true,
            'result' => $result,
        ));
    }

    private function permalink(array $row)
    {
        try {
            $type = isset($row['type']) ? (string) $row['type'] : 'post';
            if (Typecho_Router::get($type) === null) {
                return '';
            }

            if (isset($row['slug'])) {
                $row['slug'] = rawurlencode($row['slug']);
            }

            $date = new Typecho_Date($row['created']);
            $row['date'] = $date;
            $row['year'] = $date->year;
            $row['month'] = $date->month;
            $row['day'] = $date->day;

            Typecho_Widget::widget('Widget_Options')->to($options);
            return Typecho_Common::url(Typecho_Router::url($type, $row), $options->index);
        } catch (Exception $e) {
            return '';
        }
    }

    private function excerpt($text)
    {
        $text = trim(strip_tags(preg_replace('/\s+/u', ' ', (string) $text)));
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, 90, 'UTF-8');
        }

        return substr($text, 0, 180);
    }

    private function isPublicPost($cid)
    {
        $cid = (int) $cid;
        if ($cid <= 0) {
            return false;
        }

        try {
            $db = Typecho_Db::get();
            $row = $db->fetchRow($db->select('cid')
                ->from('table.contents')
                ->where('cid = ?', $cid)
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
                ->limit(1));

            return !empty($row);
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }
    private function isPublicMoment($coid)
    {
        $coid = (int) $coid;
        if ($coid <= 0) {
            return false;
        }

        try {
            $db = Typecho_Db::get();
            $row = $db->fetchRow($db->select('coid')
                ->from('table.comments')
                ->join('table.contents', 'table.comments.cid = table.contents.cid')
                ->where('coid = ?', $coid)
                ->where('table.comments.status = ?', 'approved')
                ->where('table.comments.type = ?', 'comment')
                ->where('table.comments.authorId = table.contents.authorId')
                ->where('(table.comments.parent IS NULL OR table.comments.parent = ?)', 0)
                ->where('table.contents.type = ?', 'page')
                ->where('table.contents.status = ?', 'publish')
                ->where('(table.contents.template = ? OR table.contents.template = ?)', 'page-timemachine.php', 'page-timemachine')
                ->limit(1));

            return !empty($row);
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function postLikeIdentity()
    {
        try {
            Typecho_Widget::widget('Widget_User')->to($user);
            if ($user && $user->hasLogin()) {
                $userMailHash = QiwiTheme_Plugin::momentMailHash(isset($user->mail) ? $user->mail : '');
                if ($userMailHash !== '') {
                    return array(
                        'identity_hash' => sha1('mail:' . $userMailHash),
                        'identity_type' => 'mail',
                        'user_id' => (int) $user->uid,
                        'author' => isset($user->screenName) ? (string) $user->screenName : '',
                        'mail_hash' => $userMailHash,
                    );
                }

                return array(
                    'identity_hash' => sha1('user:' . (int) $user->uid),
                    'identity_type' => 'user',
                    'user_id' => (int) $user->uid,
                    'author' => isset($user->screenName) ? (string) $user->screenName : '',
                    'mail_hash' => '',
                );
            }
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }

        $cookieName = 'qiwi_post_like_id';
        $value = isset($_COOKIE[$cookieName]) ? preg_replace('/[^a-zA-Z0-9]/', '', (string) $_COOKIE[$cookieName]) : '';
        if ($value === '' || strlen($value) < 20) {
            $value = $this->randomLikeIdentity();
            $this->setMomentLikeCookie($cookieName, $value);
            $_COOKIE[$cookieName] = $value;
        }

        return array(
            'identity_hash' => sha1('visitor:' . $value),
            'identity_type' => 'cookie',
            'user_id' => 0,
            'author' => '',
            'mail_hash' => '',
        );
    }
    private function momentLikeIdentity()
    {
        $author = trim((string) $this->request->get('author', ''));
        $mailHash = QiwiTheme_Plugin::momentMailHash($this->request->get('mail', ''));
        if ($mailHash !== '') {
            $this->setMomentLikeCookie('qiwi_moment_like_mail_hash', $mailHash);
            return array(
                'identity_hash' => sha1('mail:' . $mailHash),
                'previous_identity_hash' => $this->momentLikeCookieIdentityHash(),
                'identity_type' => 'mail',
                'user_id' => 0,
                'author' => $author,
                'mail_hash' => $mailHash,
                // 访客自报邮箱未经验证，取消点赞需回传点赞时签发的凭证。
                'proof_required' => true,
                'proof' => trim((string) $this->request->get('proof', '')),
            );
        }

        try {
            Typecho_Widget::widget('Widget_User')->to($user);
            if ($user && $user->hasLogin()) {
                $userMailHash = QiwiTheme_Plugin::momentMailHash(isset($user->mail) ? $user->mail : '');
                if ($userMailHash !== '') {
                    $this->setMomentLikeCookie('qiwi_moment_like_mail_hash', $userMailHash);
                    return array(
                        'identity_hash' => sha1('mail:' . $userMailHash),
                        'previous_identity_hash' => $this->momentLikeCookieIdentityHash(),
                        'identity_type' => 'mail',
                        'user_id' => (int) $user->uid,
                        'author' => isset($user->screenName) ? (string) $user->screenName : '',
                        'mail_hash' => $userMailHash,
                    );
                }

                return array(
                    'identity_hash' => sha1('user:' . (int) $user->uid),
                    'identity_type' => 'user',
                    'user_id' => (int) $user->uid,
                    'author' => isset($user->screenName) ? (string) $user->screenName : '',
                    'mail_hash' => '',
                );
            }
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }

        $cookieName = 'qiwi_moment_like_id';
        $value = isset($_COOKIE[$cookieName]) ? preg_replace('/[^a-zA-Z0-9]/', '', (string) $_COOKIE[$cookieName]) : '';
        if ($value === '' || strlen($value) < 20) {
            $value = $this->randomLikeIdentity();
            $this->setMomentLikeCookie($cookieName, $value);
            $_COOKIE[$cookieName] = $value;
        }

        return array(
            'identity_hash' => sha1('visitor:' . $value),
            'identity_type' => 'cookie',
            'user_id' => 0,
            'author' => '',
            'mail_hash' => '',
        );
    }

    private function momentLikeCookieIdentityHash()
    {
        $value = isset($_COOKIE['qiwi_moment_like_id']) ? preg_replace('/[^a-zA-Z0-9]/', '', (string) $_COOKIE['qiwi_moment_like_id']) : '';
        return $value !== '' && strlen($value) >= 20 ? sha1('visitor:' . $value) : '';
    }

    private function setMomentLikeCookie($name, $value)
    {
        setcookie($name, $value, time() + 31536000, '/');
        $_COOKIE[$name] = $value;
    }

    private function randomLikeIdentity()
    {
        if (function_exists('random_bytes')) {
            try {
                return bin2hex(random_bytes(16));
            } catch (Exception $e) {
            }
        }

        return sha1(uniqid('', true) . mt_rand());
    }

    private function isMomentLikeRequest()
    {
        return $this->request && $this->request->is('do=moment-like');
    }

    private function isPostLikeRequest()
    {
        return $this->request && $this->request->is('do=post-like');
    }

    private function isExternalLinkRequest()
    {
        return $this->request && $this->request->is('do=external-link');
    }

    private function isAttachmentDownloadRequest()
    {
        return $this->request && $this->request->is('do=attachment-download');
    }

    private function attachmentCaptchaState()
    {
        try {
            Typecho_Widget::widget('Widget_Options')->to($options);
            if (!isset($options->enabledCaptcha) || (string) $options->enabledCaptcha !== '1') {
                return array('mode' => 'disabled', 'message' => '');
            }

            $activated = isset($options->plugins['activated']) && is_array($options->plugins['activated'])
                ? $options->plugins['activated']
                : array();
            if (!empty($activated['QiwiCap'])) {
                $available = class_exists('QiwiCap_Plugin')
                    && method_exists('QiwiCap_Plugin', 'canRenderAttachmentCaptcha')
                    && method_exists('QiwiCap_Plugin', 'verifyCaptcha')
                    && QiwiCap_Plugin::canRenderAttachmentCaptcha();

                return $available
                    ? array('mode' => 'required', 'message' => '')
                    : array('mode' => 'error', 'message' => 'Qiwi CAP 未完成配置，附件下载暂时不可用。');
            }

            if (!empty($activated['Geetest'])) {
                return array('mode' => 'disabled', 'message' => '');
            }

            return array('mode' => 'error', 'message' => '已启用附件验证码，但没有可用的 Qiwi CAP 服务。');
        } catch (Exception $e) {
            return array('mode' => 'error', 'message' => '附件验证码状态读取失败，下载暂时不可用。');
        } catch (Throwable $e) {
            return array('mode' => 'error', 'message' => '附件验证码状态读取失败，下载暂时不可用。');
        }
    }

    private function verifyAttachmentCaptcha()
    {
        try {
            return class_exists('QiwiCap_Plugin')
                && method_exists('QiwiCap_Plugin', 'verifyCaptcha')
                && QiwiCap_Plugin::verifyCaptcha() === true;
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function attachmentDownloadName($requested, $fallback, $extension)
    {
        $name = $this->sanitizeAttachmentName($requested);
        if ($name === '') {
            $name = $this->sanitizeAttachmentName($fallback);
        }
        if ($name === '') {
            $name = 'attachment';
        }

        $extension = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', (string) $extension));
        if ($extension === '') {
            return $name;
        }

        $currentExtension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if ($currentExtension === $extension) {
            return $name;
        }

        if ($currentExtension !== '') {
            $name = $this->sanitizeAttachmentName(substr($name, 0, -strlen($currentExtension) - 1));
        }

        return ($name !== '' ? $name : 'attachment') . '.' . $extension;
    }

    private function sanitizeAttachmentName($name)
    {
        if (is_array($name) || is_object($name)) {
            return '';
        }

        $name = html_entity_decode(strip_tags((string) $name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name);
        $name = str_replace(array('/', '\\'), '-', $name);
        $name = preg_replace('/\s+/u', ' ', $name);
        return trim((string) $name, " .\t\n\r\0\x0B");
    }

    private function publicAttachment($attachmentId, $contentId)
    {
        $attachmentId = (int) $attachmentId;
        $contentId = (int) $contentId;
        if ($attachmentId <= 0 || $contentId <= 0) {
            return array();
        }

        try {
            $db = Typecho_Db::get();
            Typecho_Widget::widget('Widget_Options')->to($options);
            $parent = $db->fetchRow($db->select('cid', 'text')
                ->from('table.contents')
                ->where('cid = ?', $contentId)
                ->where('type IN ?', array('post', 'page'))
                ->where('status = ?', 'publish')
                ->where('(password IS NULL OR password = ?)', '')
                ->where('created < ?', $options->gmtTime)
                ->limit(1));
            if (empty($parent)) {
                return array();
            }
            if (!$this->contentReferencesAttachment(isset($parent['text']) ? $parent['text'] : '', $attachmentId)) {
                return array();
            }

            $row = $db->fetchRow($db->select('cid', 'title', 'text', 'parent')
                ->from('table.contents')
                ->where('cid = ?', $attachmentId)
                ->where('parent = ?', $contentId)
                ->where('type = ?', 'attachment')
                ->where('status = ?', 'publish')
                ->limit(1));
            if (empty($row)) {
                return array();
            }

            $data = json_decode(isset($row['text']) ? (string) $row['text'] : '', true);
            if (!is_array($data) || empty($data['path'])) {
                return array();
            }

            return array(
                'name' => isset($data['name']) && trim((string) $data['name']) !== '' ? trim((string) $data['name']) : (string) $row['title'],
                'path' => (string) $data['path'],
                'type' => isset($data['type']) ? strtolower(trim((string) $data['type'])) : '',
                'mime' => isset($data['mime']) ? trim((string) $data['mime']) : 'application/octet-stream',
            );
        } catch (Exception $e) {
            return array();
        } catch (Throwable $e) {
            return array();
        }
    }

    private function contentReferencesAttachment($content, $attachmentId)
    {
        $attachmentId = (int) $attachmentId;
        if ($attachmentId <= 0) {
            return false;
        }

        $content = html_entity_decode((string) $content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!preg_match_all('/\[(?:attachment|file)\b([^\]]*)\]/iu', $content, $matches)) {
            return false;
        }

        foreach ($matches[1] as $attrsText) {
            if (preg_match('/(?:^|\s)id\s*=\s*(?:"(\d+)"|\'(\d+)\'|(\d+))(?:\s|$)/iu', (string) $attrsText, $idMatch)) {
                foreach (array(1, 2, 3) as $index) {
                    if (!empty($idMatch[$index]) && (int) $idMatch[$index] === $attachmentId) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function attachmentLocalPath($relativePath)
    {
        $relativePath = str_replace('\\', '/', trim((string) $relativePath));
        if ($relativePath === '' || strpos($relativePath, "\0") !== false || preg_match('#(?:^|/)\.\.(?:/|$)#', $relativePath)) {
            return '';
        }

        $root = defined('__TYPECHO_UPLOAD_ROOT_DIR__') ? __TYPECHO_UPLOAD_ROOT_DIR__ : __TYPECHO_ROOT_DIR__;
        $rootReal = realpath($root);
        if ($rootReal === false) {
            return '';
        }

        $candidate = rtrim($rootReal, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relativePath, '/'));
        $candidateReal = realpath($candidate);
        if ($candidateReal === false) {
            return '';
        }

        $rootPrefix = rtrim(str_replace('\\', '/', $rootReal), '/') . '/';
        $candidateNormalized = str_replace('\\', '/', $candidateReal);
        return strpos($candidateNormalized, $rootPrefix) === 0 ? $candidateReal : '';
    }

    private function json($payload, $status = 200)
    {
        if ($status !== 200) {
            $this->response->setStatus($status);
        }

        $this->response->setContentType('application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
