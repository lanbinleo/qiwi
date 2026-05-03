<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Qiwi theme companion plugin.
 *
 * @package QiwiTheme
 * @author  MaxQiwi
 * @version 1.4.3
 * @link    https://www.maxqi.top/
 */
class QiwiTheme_Plugin implements Typecho_Plugin_Interface
{
    const TABLE = 'qiwi_threads';

    public static function activate()
    {
        self::installTable();
        Helper::removeAction('qiwi-thread-tools');
        Helper::addAction('qiwi-theme', 'QiwiTheme_Action');
        Typecho_Plugin::factory('admin/header.php')->header = array(__CLASS__, 'adminHeader');
        Typecho_Plugin::factory('Widget\Base\Metas')->filter = array(__CLASS__, 'metaFilter');
        return _t('Qiwi Theme 伴生插件已启用，Thread 数据表与后台增强接口已准备好。');
    }

    public static function deactivate()
    {
        Helper::removeAction('qiwi-theme');
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $info = new Typecho_Widget_Helper_Form_Element_Fake('qiwiThemeInfo', '');
        $info->input->setAttribute('type', 'hidden');
        $info->label(_t('说明'));
        $info->description(_t('Qiwi 主题伴生插件。当前提供 thread-* 文集编辑器、Thread 数据存储与文章选择接口。'));
        $form->addInput($info);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    public static function adminHeader($header)
    {
        $script = isset($_SERVER['SCRIPT_NAME']) ? strtolower(basename($_SERVER['SCRIPT_NAME'])) : '';
        if ($script !== 'category.php') {
            return $header;
        }

        Typecho_Widget::widget('Widget_Options')->to($options);
        Typecho_Widget::widget('Widget_Security')->to($security);
        $theme = isset($options->theme) ? $options->theme : 'qiwi';
        $themeBase = rtrim($options->siteUrl, '/') . '/usr/themes/' . rawurlencode($theme) . '/';
        $css = htmlspecialchars($themeBase . 'assets/css/admin-config.css', ENT_QUOTES, 'UTF-8');
        $js = htmlspecialchars($themeBase . 'assets/js/admin-config.js', ENT_QUOTES, 'UTF-8');
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
