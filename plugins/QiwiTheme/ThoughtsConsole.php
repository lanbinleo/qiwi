<?php

namespace TypechoPlugin\QiwiTheme;

use \Typecho\{Widget};
use \Typecho\Db;

/**
 * Qiwi 段落想法审核台数据层。
 *
 * @package QiwiTheme
 * @author  Leo 里奥
 * @version 2.3.0
 * @link    https://bboreo.com/
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class ThoughtsConsole extends Widget
{
    public function execute()
    {
        $this->widget('Widget_User')->pass('administrator');
    }

    public function thoughtStats(): array
    {
        \QiwiTheme_Thoughts::dbInstall();

        $db = Db::get();
        $stats = [
            'waiting' => 0,
            'approved' => 0,
        ];

        $rows = $db->fetchAll('SELECT status, COUNT(*) AS total FROM ' . \QiwiTheme_Thoughts::tableName() . ' GROUP BY status');
        foreach ($rows as $row) {
            $status = (string)$row['status'];
            if (array_key_exists($status, $stats)) {
                $stats[$status] = (int)$row['total'];
            }
        }

        return $stats;
    }

    public function thoughtRows(string $status = 'all', int $limit = 50, int $offset = 0): array
    {
        \QiwiTheme_Thoughts::dbInstall();

        $db = Db::get();
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $select = $db->select()->from(\QiwiTheme_Thoughts::tableName());
        if ($status === 'waiting' || $status === 'approved') {
            $select->where('status = ?', $status);
        }
        $rows = $db->fetchAll($select
            ->order('created', Db::SORT_DESC)
            ->order('id', Db::SORT_DESC)
            ->limit($limit)
            ->offset($offset));

        if (empty($rows)) {
            return [];
        }

        $cids = [];
        foreach ($rows as $row) {
            $cids[(int)$row['cid']] = true;
        }

        $titles = [];
        try {
            $titleRows = $db->fetchAll($db->select('cid', 'title')
                ->from('table.contents')
                ->where('cid IN (' . implode(',', array_map('intval', array_keys($cids))) . ')'));
            foreach ((array)$titleRows as $titleRow) {
                $titles[(int)$titleRow['cid']] = (string)$titleRow['title'];
            }
        } catch (\Exception $e) {
        } catch (\Throwable $e) {
        }

        $items = [];
        foreach ($rows as $row) {
            $row['postTitle'] = isset($titles[(int)$row['cid']]) ? $titles[(int)$row['cid']] : '';
            $row['html'] = \QiwiTheme_Thoughts::renderContent($row);
            $row['createdText'] = !empty($row['created']) ? date('Y-m-d H:i:s', (int)$row['created']) : '-';
            $items[] = $row;
        }

        return $items;
    }

    public function statusLabel(string $status): string
    {
        return [
            'waiting' => '待审核',
            'approved' => '已通过',
        ][$status] ?? $status;
    }

    public function statusView(string $status): array
    {
        return [
            'waiting' => ['icon' => '○', 'class' => 'waiting'],
            'approved' => ['icon' => '✓', 'class' => 'approved'],
        ][$status] ?? ['icon' => '?', 'class' => 'unknown'];
    }

    public function excerpt($text, int $length = 60): string
    {
        $text = trim(strip_tags((string)$text));
        return mb_strlen($text, 'UTF-8') > $length ? mb_substr($text, 0, $length, 'UTF-8') . '…' : $text;
    }
}
