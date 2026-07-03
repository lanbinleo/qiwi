<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class QiwiTheme_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function execute()
    {
        if ($this->isGotoRequest() || $this->isMomentLikeRequest() || $this->isPostLikeRequest() || $this->isExternalLinkRequest() || $this->isLinkPreviewRequest()) {
            return;
        }

        Typecho_Widget::widget('Widget_User')->pass('editor');
    }

    public function action()
    {
        if ($this->request->is('do=goto')) {
            $this->goto();
        }
        if ($this->request->is('do=external-link')) {
            $this->externalLink();
        }
        if ($this->request->is('do=link-preview')) {
            $this->linkPreview();
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

    public function goto()
    {
        $url = QiwiTheme_Plugin::decodeGotoUrl($this->request->get('url', ''));
        if ($url === '') {
            $this->response->setStatus(400);
            $this->response->throwContent("Invalid external link\n", 'text/plain');
        }

        QiwiTheme_Plugin::recordExternalLinkClick($url, isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '');
        $this->response->redirect($url);
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

    public function linkPreview()
    {
        $url = trim((string) $this->request->get('url', ''));
        $target = $this->normalizeInternalUrl($url);
        if ($target === '') {
            $this->json(array('success' => false, 'message' => 'Unsupported URL'), 400);
        }

        $fragmentValue = parse_url($target, PHP_URL_FRAGMENT);
        $fragment = $fragmentValue !== false && $fragmentValue !== null ? (string) $fragmentValue : '';
        if (preg_match('/^comment-(\d+)$/i', $fragment, $matches)) {
            $preview = $this->commentPreview((int) $matches[1], $target);
            if (!empty($preview)) {
                $this->json(array('success' => true, 'preview' => $preview));
            }

            $this->json(array('success' => false, 'message' => 'Preview not found'), 404);
        }

        $preview = $this->contentPreview($target);
        if (!empty($preview)) {
            $this->json(array('success' => true, 'preview' => $preview));
        }

        $this->json(array('success' => false, 'message' => 'Preview not found'), 404);
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
        $this->json(array(
            'success' => true,
            'coid' => $coid,
            'liked' => !empty($result['liked']),
            'count' => isset($result['count']) ? (int) $result['count'] : 0,
        ));
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

    private function normalizeInternalUrl($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        try {
            Typecho_Widget::widget('Widget_Options')->to($options);
            $siteUrl = rtrim((string) $options->siteUrl, '/') . '/';
            $base = $siteUrl;
            $target = Typecho_Common::url($url, $base);
            $siteParts = parse_url($siteUrl);
            $targetParts = parse_url($target);
            $siteHost = isset($siteParts['host']) ? strtolower((string) $siteParts['host']) : '';
            $targetHost = isset($targetParts['host']) ? strtolower((string) $targetParts['host']) : '';
            if ($siteHost === '' || $targetHost === '') {
                return '';
            }

            $normalizeHost = function ($host) {
                return preg_replace('/^www\./i', '', strtolower((string) $host));
            };

            if ($normalizeHost($siteHost) !== $normalizeHost($targetHost)) {
                return '';
            }

            $scheme = isset($targetParts['scheme']) ? (string) $targetParts['scheme'] : (isset($siteParts['scheme']) ? (string) $siteParts['scheme'] : 'http');
            $port = isset($targetParts['port']) ? ':' . (int) $targetParts['port'] : '';
            $path = isset($targetParts['path']) ? (string) $targetParts['path'] : '/';
            $query = isset($targetParts['query']) && $targetParts['query'] !== '' ? '?' . (string) $targetParts['query'] : '';
            $fragment = isset($targetParts['fragment']) && $targetParts['fragment'] !== '' ? '#' . (string) $targetParts['fragment'] : '';
            return $scheme . '://' . $targetHost . $port . $path . $query . $fragment;
        } catch (Exception $e) {
            return '';
        } catch (Throwable $e) {
            return '';
        }
    }

    private function contentPreview($url)
    {
        $row = $this->findContentByUrl($url);
        if (empty($row)) {
            return array();
        }

        $cid = isset($row['cid']) ? (int) $row['cid'] : 0;
        $text = isset($row['text']) ? (string) $row['text'] : '';
        $fragment = $this->urlFragment($url);
        $quote = $fragment !== '' ? $this->headingQuotePreview($text, $fragment) : array();
        $summary = !empty($quote['summary']) ? $quote['summary'] : $this->contentSummary($cid, $text);
        $permalink = $this->permalink($row);
        $previewUrl = $permalink;
        if (!empty($quote['fragment']) && $permalink !== '') {
            $previewUrl .= '#' . rawurlencode($quote['fragment']);
        }

        return array(
            'type' => !empty($quote) ? 'quote' : (isset($row['type']) && $row['type'] === 'page' ? 'page' : 'post'),
            'title' => !empty($quote['title']) ? (string) $quote['title'] : (isset($row['title']) ? (string) $row['title'] : ''),
            'summary' => $summary,
            'url' => $previewUrl,
            'date' => !empty($row['created']) ? date('Y-m-d', (int) $row['created']) : '',
            'meta' => !empty($quote) ? array('source' => isset($row['title']) ? (string) $row['title'] : '') : array(),
            'metas' => $this->contentMetas($cid),
        );
    }

    private function commentPreview($coid, $url)
    {
        $coid = (int) $coid;
        if ($coid <= 0) {
            return array();
        }

        try {
            $db = Typecho_Db::get();
            $row = $db->fetchRow($db->select(
                    'table.comments.coid',
                    'table.comments.cid',
                    'table.comments.parent',
                    'table.comments.author',
                    'table.comments.authorId',
                    'table.comments.text',
                    'table.comments.created',
                    'table.contents.title',
                    'table.contents.slug',
                    'table.contents.type',
                    'table.contents.status',
                    'table.contents.password',
                    'table.contents.authorId AS ownerId',
                    'table.contents.template'
                )
                ->from('table.comments')
                ->join('table.contents', 'table.comments.cid = table.contents.cid')
                ->where('table.comments.coid = ?', $coid)
                ->where('table.comments.status = ?', 'approved')
                ->where('table.comments.type = ?', 'comment')
                ->where('table.comments.authorId > ?', 0)
                ->where('table.contents.status = ?', 'publish')
                ->where('(table.contents.password IS NULL OR table.contents.password = ?)', '')
                ->limit(1));
        } catch (Exception $e) {
            return array();
        } catch (Throwable $e) {
            return array();
        }

        if (empty($row)) {
            return array();
        }

        $isMoment = (int) $row['authorId'] === (int) $row['ownerId']
            && (int) $row['parent'] === 0
            && (string) $row['type'] === 'page'
            && in_array((string) $row['template'], array('page-timemachine.php', 'page-timemachine'), true);
        $contentUrl = $this->permalink($row);
        $author = isset($row['author']) ? (string) $row['author'] : '';
        $momentText = $isMoment ? $this->momentPreviewText((string) $row['text']) : array();
        $summary = $isMoment && isset($momentText['summary']) ? $momentText['summary'] : $this->commentSummary((string) $row['text']);

        return array(
            'type' => $isMoment ? 'moment' : 'comment',
            'title' => $isMoment
                ? (isset($momentText['title']) && $momentText['title'] !== '' ? $momentText['title'] : '说说')
                : ($author !== '' ? $author . ' 的评论' : '评论'),
            'summary' => $summary,
            'url' => ($contentUrl !== '' ? $contentUrl : $url) . '#comment-' . (int) $row['coid'],
            'date' => !empty($row['created']) ? date('Y-m-d H:i', (int) $row['created']) : '',
            'meta' => array(
                'source' => isset($row['title']) ? (string) $row['title'] : '',
            ),
        );
    }

    private function findContentByUrl($url)
    {
        $target = $this->urlComparable($url, false);
        if ($target === '') {
            return array();
        }

        $parts = parse_url($url);
        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
            foreach (array('p', 'cid') as $key) {
                if (!empty($query[$key]) && ctype_digit((string) $query[$key])) {
                    $row = $this->contentRowByCid((int) $query[$key]);
                    if (!empty($row)) {
                        return $row;
                    }
                }
            }
        }

        $path = isset($parts['path']) ? trim((string) $parts['path'], '/') : '';
        if (preg_match('/(?:archives|post|p)\/(\d+)(?:\.html)?(?:\/|$)/i', $path, $matches)) {
            $row = $this->contentRowByCid((int) $matches[1]);
            if (!empty($row)) {
                return $row;
            }
        }

        try {
            $db = Typecho_Db::get();
            Typecho_Widget::widget('Widget_Options')->to($options);
            $rows = $db->fetchAll($db->select('cid', 'title', 'slug', 'created', 'modified', 'text', 'type')
                ->from('table.contents')
                ->where('type IN ?', array('post', 'page'))
                ->where('status = ?', 'publish')
                ->where('(password IS NULL OR password = ?)', '')
                ->where('created < ?', $options->gmtTime)
                ->order('created', Typecho_Db::SORT_DESC));
        } catch (Exception $e) {
            return array();
        } catch (Throwable $e) {
            return array();
        }

        foreach ($rows as $row) {
            $permalink = $this->permalink($row);
            if ($permalink !== '' && $this->urlComparable($permalink, false) === $target) {
                return $row;
            }
        }

        return array();
    }

    private function contentRowByCid($cid)
    {
        $cid = (int) $cid;
        if ($cid <= 0) {
            return array();
        }

        try {
            $db = Typecho_Db::get();
            Typecho_Widget::widget('Widget_Options')->to($options);
            $row = $db->fetchRow($db->select('cid', 'title', 'slug', 'created', 'modified', 'text', 'type')
                ->from('table.contents')
                ->where('cid = ?', $cid)
                ->where('type IN ?', array('post', 'page'))
                ->where('status = ?', 'publish')
                ->where('(password IS NULL OR password = ?)', '')
                ->where('created < ?', $options->gmtTime)
                ->limit(1));

            return !empty($row) ? $row : array();
        } catch (Exception $e) {
            return array();
        } catch (Throwable $e) {
            return array();
        }
    }

    private function urlComparable($url, $includeFragment = true)
    {
        $parts = parse_url((string) $url);
        if (empty($parts['host'])) {
            return '';
        }

        $path = isset($parts['path']) ? '/' . trim(rawurldecode((string) $parts['path']), '/') : '/';
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . (string) $parts['query'] : '';
        $fragment = $includeFragment && isset($parts['fragment']) && $parts['fragment'] !== '' ? '#' . (string) $parts['fragment'] : '';
        return strtolower(preg_replace('/^www\./i', '', (string) $parts['host'])) . $path . $query . $fragment;
    }

    private function urlFragment($url)
    {
        $fragment = parse_url((string) $url, PHP_URL_FRAGMENT);
        if ($fragment === false || $fragment === null) {
            return '';
        }

        return trim(rawurldecode((string) $fragment));
    }

    private function contentSummary($cid, $text)
    {
        $custom = trim((string) $this->fieldValue((int) $cid, 'excerpt'));
        $summary = ($custom !== '' && $custom !== '0') ? $custom : $text;
        return $this->plainSummary($summary, 120);
    }

    private function headingQuotePreview($text, $fragment)
    {
        $fragment = trim(rawurldecode((string) $fragment));
        if ($fragment === '') {
            return array();
        }

        $htmlQuote = $this->htmlHeadingQuotePreview($text, $fragment);
        if (!empty($htmlQuote)) {
            return $htmlQuote;
        }

        return $this->markdownHeadingQuotePreview($text, $fragment);
    }

    private function htmlHeadingQuotePreview($text, $fragment)
    {
        $text = (string) $text;
        if (!preg_match_all('/<h([2-4])\b([^>]*)>([\s\S]*?)<\/h\1>/iu', $text, $matches, PREG_OFFSET_CAPTURE)) {
            return array();
        }

        $usedIds = array();
        $count = count($matches[0]);
        for ($index = 0; $index < $count; $index++) {
            $level = (int) $matches[1][$index][0];
            $attrs = (string) $matches[2][$index][0];
            $title = $this->headingTitle($matches[3][$index][0]);
            $explicitId = '';
            if (preg_match('/\sid=(["\'])(.*?)\1/iu', $attrs, $idMatches)) {
                $explicitId = trim(html_entity_decode($idMatches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }

            $generatedId = $this->uniqueHeadingId($title, $index, $usedIds, $explicitId);
            if (!$this->fragmentMatchesHeading($fragment, $title, $generatedId)) {
                continue;
            }

            $start = $matches[0][$index][1] + strlen($matches[0][$index][0]);
            $end = strlen($text);
            for ($next = $index + 1; $next < $count; $next++) {
                $nextLevel = (int) $matches[1][$next][0];
                if ($nextLevel <= $level) {
                    $end = $matches[0][$next][1];
                    break;
                }
            }

            return array(
                'title' => $title,
                'summary' => $this->plainSummary(substr($text, $start, max(0, $end - $start)), 140),
                'fragment' => $generatedId,
            );
        }

        return array();
    }

    private function markdownHeadingQuotePreview($text, $fragment)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $text);
        $headings = array();
        $usedIds = array();

        foreach ($lines as $lineNumber => $line) {
            if (!preg_match('/^(#{2,4})\s+(.+?)\s*#*\s*$/u', trim((string) $line), $matches)) {
                continue;
            }

            $title = $this->headingTitle($matches[2]);
            $id = $this->uniqueHeadingId($title, count($headings), $usedIds, '');
            $headings[] = array(
                'line' => (int) $lineNumber,
                'level' => strlen($matches[1]),
                'title' => $title,
                'id' => $id,
            );
        }

        $total = count($headings);
        for ($index = 0; $index < $total; $index++) {
            $heading = $headings[$index];
            if (!$this->fragmentMatchesHeading($fragment, $heading['title'], $heading['id'])) {
                continue;
            }

            $endLine = count($lines);
            for ($next = $index + 1; $next < $total; $next++) {
                if ($headings[$next]['level'] <= $heading['level']) {
                    $endLine = $headings[$next]['line'];
                    break;
                }
            }

            $sectionLines = array_slice($lines, $heading['line'] + 1, max(0, $endLine - $heading['line'] - 1));
            return array(
                'title' => $heading['title'],
                'summary' => $this->plainSummary(implode("\n", $sectionLines), 140),
                'fragment' => $heading['id'],
            );
        }

        return array();
    }

    private function headingTitle($value)
    {
        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/<[^>]+>/u', ' ', $value);
        $value = preg_replace('/\[[^\]]*\]\([^\)]*\)/u', ' ', $value);
        $value = preg_replace('/[`*_#]+/u', ' ', $value);
        return trim(preg_replace('/\s+/u', ' ', $value));
    }

    private function uniqueHeadingId($title, $index, array &$usedIds, $explicitId = '')
    {
        $explicitId = trim((string) $explicitId);
        if ($explicitId !== '' && empty($usedIds[$explicitId])) {
            $usedIds[$explicitId] = true;
            return $explicitId;
        }

        $base = $this->slugifyHeading($title);
        if ($base === '') {
            $base = 'heading-' . (int) $index;
        }

        $id = $base;
        $counter = 2;
        while (!empty($usedIds[$id])) {
            $id = $base . '-' . $counter;
            $counter++;
        }

        $usedIds[$id] = true;
        return $id;
    }

    private function slugifyHeading($text)
    {
        $text = strtolower((string) $text);
        $text = preg_replace('/<[^>]+>/u', '', $text);
        $text = preg_replace('/[\s\/\\\\?%*:|"<>.,;()\[\]{}+=!@#$^&~`]+/u', '-', $text);
        return trim($text, '-');
    }

    private function fragmentMatchesHeading($fragment, $title, $id)
    {
        $fragment = trim(rawurldecode((string) $fragment));
        if ($fragment === '') {
            return false;
        }

        return $fragment === $id
            || $fragment === $title
            || $this->slugifyHeading($fragment) === $id;
    }

    private function commentSummary($text)
    {
        $text = preg_replace('/!\[[^\]]*\]\([^\)]*\)/u', ' [图片] ', (string) $text);
        return $this->plainSummary($text, 96);
    }

    private function momentPreviewText($text)
    {
        $text = preg_replace('/!\[[^\]]*\]\([^\)]*\)/u', "\n[图片]\n", (string) $text);
        $text = preg_replace('/\[([^\]]+)\]\([^\)]*\)/u', '$1', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/<[^>]+>/u', ' ', $text);
        $text = preg_replace('/[`*_>#-]+/u', ' ', $text);
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $cleanLines = array();

        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', (string) $line));
            if ($line !== '') {
                $cleanLines[] = $line;
            }
        }

        if (empty($cleanLines)) {
            return array('title' => '说说', 'summary' => '');
        }

        $title = preg_replace('/[，,。.!！?？;；:：、~～…]+$/u', '', $cleanLines[0]);
        $title = trim($title);
        if ($title === '') {
            $title = $cleanLines[0];
        }

        $rest = array_slice($cleanLines, 1);
        return array(
            'title' => $this->plainSummary($title, 48),
            'summary' => empty($rest) ? '' : $this->plainSummary(implode(' ', $rest), 96),
        );
    }

    private function plainSummary($text, $length)
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/<[^>]+>/u', ' ', $text);
        $text = preg_replace('/!\[[^\]]*\]\([^\)]*\)/u', ' [图片] ', $text);
        $text = preg_replace('/\[([^\]]+)\]\([^\)]*\)/u', '$1', $text);
        $text = preg_replace('/[`*_>#-]+/u', ' ', $text);
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return '';
        }

        $length = max(20, (int) $length);
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $length
                ? mb_substr($text, 0, $length, 'UTF-8') . '...'
                : $text;
        }

        return strlen($text) > $length * 2 ? substr($text, 0, $length * 2) . '...' : $text;
    }

    private function fieldValue($cid, $name)
    {
        $cid = (int) $cid;
        $name = trim((string) $name);
        if ($cid <= 0 || $name === '') {
            return '';
        }

        try {
            $db = Typecho_Db::get();
            $row = $db->fetchRow($db->select('str_value', 'int_value', 'float_value')
                ->from('table.fields')
                ->where('cid = ?', $cid)
                ->where('name = ?', $name)
                ->limit(1));
            if (empty($row)) {
                return '';
            }

            foreach (array('str_value', 'int_value', 'float_value') as $key) {
                if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                    return trim((string) $row[$key]);
                }
            }
        } catch (Exception $e) {
            return '';
        } catch (Throwable $e) {
            return '';
        }

        return '';
    }

    private function contentMetas($cid)
    {
        $cid = (int) $cid;
        if ($cid <= 0) {
            return array();
        }

        try {
            $db = Typecho_Db::get();
            $rows = $db->fetchAll($db->select('table.metas.name', 'table.metas.type')
                ->from('table.relationships')
                ->join('table.metas', 'table.relationships.mid = table.metas.mid')
                ->where('table.relationships.cid = ?', $cid)
                ->where('table.metas.type IN ?', array('category', 'tag')));
        } catch (Exception $e) {
            return array();
        } catch (Throwable $e) {
            return array();
        }

        $metas = array();
        foreach ($rows as $row) {
            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            if ($name !== '') {
                $metas[] = array(
                    'name' => $name,
                    'type' => isset($row['type']) ? (string) $row['type'] : '',
                );
            }
        }

        return array_slice($metas, 0, 4);
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

    private function isPostLikeRequest()
    {
        return $this->request && $this->request->is('do=post-like');
    }
    private function isExternalLinkRequest()
    {
        return $this->request && $this->request->is('do=external-link');
    }

    private function isLinkPreviewRequest()
    {
        return $this->request && $this->request->is('do=link-preview');
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
