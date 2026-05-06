<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$target = Typecho_Common::url('options-theme.php', $options->adminUrl);
$response->redirect($target);
