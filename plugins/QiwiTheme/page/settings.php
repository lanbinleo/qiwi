<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

Typecho_Widget::widget('Widget_User')->pass('administrator');

if (!function_exists('qiwi_theme_admin_h')) {
    function qiwi_theme_admin_h($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$adminScript = isset($_SERVER['SCRIPT_FILENAME']) ? (string) $_SERVER['SCRIPT_FILENAME'] : '';
$adminDir = $adminScript !== '' ? dirname($adminScript) : __TYPECHO_ROOT_DIR__ . '/admin';
$themeSettingsUrl = Typecho_Common::url('options-theme.php', $options->adminUrl);
$likeRecords = class_exists('QiwiTheme_Plugin') ? QiwiTheme_Plugin::getMomentLikeRecords(100) : array();
$identityLabels = array(
    'mail' => '邮箱',
    'user' => '登录用户',
    'cookie' => 'Cookie',
);

include $adminDir . '/header.php';
include $adminDir . '/menu.php';
?>
<div class="main">
    <div class="body container">
        <div class="typecho-page-title">
            <h2>Qiwi 设置</h2>
        </div>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12">
                <div class="typecho-list-operate clearfix">
                    <div class="operate">
                        <a class="btn primary" href="<?php echo qiwi_theme_admin_h($themeSettingsUrl); ?>">进入主题设置</a>
                    </div>
                </div>

                <div class="typecho-list">
                    <h3>说说点赞记录</h3>
                    <p class="description">邮箱点赞只保存邮箱 hash，用于和评论身份做弱关联；没有邮箱的点赞继续显示为 Cookie 身份。</p>

                    <?php if (empty($likeRecords)): ?>
                    <div class="message notice">还没有点赞记录。</div>
                    <?php else: ?>
                    <table class="typecho-list-table">
                        <colgroup>
                            <col width="16%">
                            <col width="22%">
                            <col width="24%">
                            <col width="24%">
                            <col width="14%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>时间</th>
                                <th>点赞身份</th>
                                <th>邮箱关联</th>
                                <th>对应说说</th>
                                <th>来源</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($likeRecords as $record): ?>
                            <?php
                                $identityType = isset($record['identityType']) ? (string) $record['identityType'] : 'cookie';
                                $identityLabel = isset($identityLabels[$identityType]) ? $identityLabels[$identityType] : $identityType;
                                $author = trim((string) (isset($record['author']) ? $record['author'] : ''));
                                $mailHash = trim((string) (isset($record['mailHash']) ? $record['mailHash'] : ''));
                                $matches = isset($record['matches']) && is_array($record['matches']) ? $record['matches'] : array();
                                $moment = isset($record['moment']) && is_array($record['moment']) ? $record['moment'] : array();
                            ?>
                            <tr>
                                <td><?php echo !empty($record['created']) ? date('Y-m-d H:i', (int) $record['created']) : '-'; ?></td>
                                <td>
                                    <strong><?php echo qiwi_theme_admin_h($author !== '' ? $author : $identityLabel); ?></strong>
                                    <br>
                                    <span class="description"><?php echo qiwi_theme_admin_h($identityLabel); ?><?php if (!empty($record['userId'])): ?> · UID <?php echo (int) $record['userId']; ?><?php endif; ?></span>
                                </td>
                                <td>
                                    <?php if ($mailHash !== ''): ?>
                                        <code><?php echo qiwi_theme_admin_h(substr($mailHash, 0, 12)); ?>...</code>
                                        <?php if (!empty($matches)): ?>
                                            <br>
                                            <span class="description">匹配评论者：
                                            <?php
                                                $names = array();
                                                foreach (array_slice($matches, 0, 3) as $match) {
                                                    $names[] = trim((string) (isset($match['author']) ? $match['author'] : ''));
                                                }
                                                echo qiwi_theme_admin_h(implode('、', array_filter($names)));
                                            ?>
                                            </span>
                                        <?php else: ?>
                                            <br><span class="description">暂未匹配评论</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="description">无邮箱</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong>#<?php echo (int) $record['coid']; ?></strong>
                                    <?php if (!empty($moment['excerpt'])): ?>
                                        <br><span class="description"><?php echo qiwi_theme_admin_h($moment['excerpt']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo !empty($moment['title']) ? qiwi_theme_admin_h($moment['title']) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include $adminDir . '/footer.php'; ?>
