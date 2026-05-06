<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class QiwiTheme_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function execute()
    {
        if ($this->isGotoRequest() || $this->isMomentLikeRequest()) {
            return;
        }

        Typecho_Widget::widget('Widget_User')->pass('editor');
    }

    public function action()
    {
        if ($this->request->is('do=goto')) {
            $this->goto();
        }

        Typecho_Widget::widget('Widget_Security')->protect();
        $this->on($this->request->is('do=read-thread'))->readThread();
        $this->on($this->request->is('do=save-thread'))->saveThread();
        $this->on($this->request->is('do=posts'))->posts();
        $this->on($this->request->is('do=moment-like'))->momentLike();
        $this->json(array('success' => false, 'message' => 'Unknown action'), 404);
    }

    public function goto()
    {
        $url = QiwiTheme_Plugin::decodeGotoUrl($this->request->get('url', ''));
        if ($url === '') {
            $this->response->setStatus(400);
            $this->response->throwContent("Invalid external link\n", 'text/plain');
        }

        QiwiTheme_Plugin::recordExternalLinkClick($url);
        $this->response->redirect($url);
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

        $result = QiwiTheme_Plugin::toggleMomentLike($coid, $this->momentLikeIdentityHash());
        $this->json(array(
            'success' => true,
            'coid' => $coid,
            'liked' => !empty($result['liked']),
            'count' => isset($result['count']) ? (int) $result['count'] : 0,
        ));
    }

    private function permalink(array $row)
    {
        try {
            if (Typecho_Router::get('post') === null) {
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
            return Typecho_Common::url(Typecho_Router::url('post', $row), $options->index);
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

    private function momentLikeIdentityHash()
    {
        try {
            Typecho_Widget::widget('Widget_User')->to($user);
            if ($user && $user->hasLogin()) {
                return sha1('user:' . (int) $user->uid);
            }
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }

        $cookieName = 'qiwi_moment_like_id';
        $value = isset($_COOKIE[$cookieName]) ? preg_replace('/[^a-zA-Z0-9]/', '', (string) $_COOKIE[$cookieName]) : '';
        if ($value === '' || strlen($value) < 20) {
            $value = $this->randomLikeIdentity();
            setcookie($cookieName, $value, time() + 31536000, '/');
            $_COOKIE[$cookieName] = $value;
        }

        return sha1('visitor:' . $value);
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

    private function isGotoRequest()
    {
        if ($this->request && $this->request->is('do=goto')) {
            return true;
        }

        if ($this->request && method_exists($this->request, 'getPathInfo')) {
            $pathInfo = rtrim((string) $this->request->getPathInfo(), '/');
            if ($pathInfo === '/goto' || $pathInfo === '/index.php/goto') {
                return true;
            }
        }

        $path = isset($_SERVER['REQUEST_URI']) ? parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
        $path = '/' . ltrim(rtrim((string) $path, '/'), '/');
        return preg_match('#(?:^|/)(?:index\.php/)?goto$#i', $path) === 1;
    }

    private function isMomentLikeRequest()
    {
        return $this->request && $this->request->is('do=moment-like');
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
