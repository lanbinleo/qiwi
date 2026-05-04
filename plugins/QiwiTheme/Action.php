<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class QiwiTheme_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function execute()
    {
        if ($this->isGotoRequest()) {
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
        $this->json(array('success' => false, 'message' => 'Unknown action'), 404);
    }

    public function goto()
    {
        $url = QiwiTheme_Plugin::decodeGotoUrl($this->request->get('url', ''));
        if ($url === '') {
            $this->response->setStatus(400);
            $this->response->setContentType('text/plain');
            echo "Invalid external link\n";
            exit;
        }

        QiwiTheme_Plugin::recordExternalLinkClick($url);
        header('Location: ' . $url, true, 302);
        exit;
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
