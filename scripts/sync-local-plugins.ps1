param(
    [string]$TypechoRoot = "D:\phpstudy_pro\WWW\localhost",
    [string]$PhpBin = "D:\phpstudy_pro\Extensions\php\php8.2.9nts\php.exe",
    [string[]]$Plugins = @("Geetest", "QiwiCap", "QiwiSitemap", "QiwiTheme", "QiwiCommentMail"),
    [string[]]$RefreshPlugins = @("QiwiTheme", "QiwiCommentMail"),
    [string[]]$ObsoletePlugins = @("CommentToMail")
)

$ErrorActionPreference = "Stop"

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$themeRoot = Split-Path -Parent $scriptDir
$sourceRoot = Join-Path $themeRoot "plugins"
$targetRoot = Join-Path $TypechoRoot "usr\plugins"

if (!(Test-Path -LiteralPath $sourceRoot)) {
    throw "Theme plugin source directory not found: $sourceRoot"
}

if (!(Test-Path -LiteralPath $PhpBin)) {
    throw "PHP binary not found: $PhpBin"
}

$phpRuntimeArgs = @("-n")
$phpExtensionDir = Join-Path (Split-Path -Parent $PhpBin) "ext"
if (Test-Path -LiteralPath $phpExtensionDir) {
    $phpRuntimeArgs += @("-d", "extension_dir=$phpExtensionDir")
    foreach ($extension in @("pdo_mysql", "mbstring")) {
        if (Test-Path -LiteralPath (Join-Path $phpExtensionDir "php_$extension.dll")) {
            $phpRuntimeArgs += @("-d", "extension=$extension")
        }
    }
}

New-Item -ItemType Directory -Force -Path $targetRoot | Out-Null
$resolvedTargetRoot = (Resolve-Path -LiteralPath $targetRoot).Path

function Remove-ObsoletePluginCopies {
    param([string[]]$PluginNames)

    foreach ($plugin in $PluginNames) {
        if ($plugin -match '[\\/:*?"<>|]') {
            throw "Invalid obsolete plugin name: $plugin"
        }

        $target = Join-Path $targetRoot $plugin
        if (!(Test-Path -LiteralPath $target)) {
            continue
        }

        $resolvedTarget = [System.IO.Path]::GetFullPath($target)
        if (!$resolvedTarget.StartsWith($resolvedTargetRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
            throw "Refusing to remove outside plugin directory: $resolvedTarget"
        }

        Remove-Item -LiteralPath $resolvedTarget -Recurse -Force
        Write-Host "Removed obsolete local plugin: $plugin"
    }
}

foreach ($plugin in $Plugins) {
    if ($plugin -match '[\\/:*?"<>|]') {
        throw "Invalid plugin name: $plugin"
    }

    $source = Join-Path $sourceRoot $plugin
    $target = Join-Path $targetRoot $plugin
    if (!(Test-Path -LiteralPath $source)) {
        Write-Host "Skip missing plugin source: $plugin"
        continue
    }

    $resolvedTarget = [System.IO.Path]::GetFullPath($target)
    if (!$resolvedTarget.StartsWith($resolvedTargetRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Refusing to write outside plugin directory: $resolvedTarget"
    }

    if (Test-Path -LiteralPath $target) {
        Remove-Item -LiteralPath $target -Recurse -Force
    }
    Copy-Item -LiteralPath $source -Destination $target -Recurse
    Write-Host "Synced plugin: $plugin"
}

if ($RefreshPlugins.Count -eq 0) {
    Remove-ObsoletePluginCopies -PluginNames $ObsoletePlugins
    exit 0
}

$helper = @'
<?php
if ($argc < 3) {
    fwrite(STDERR, "Usage: php refresh-typecho-plugins.php <typecho-root> <plugin> [...]\n");
    exit(1);
}

$typechoRoot = rtrim(str_replace('\\', '/', $argv[1]), '/');
$pluginNames = array_slice($argv, 2);
$config = $typechoRoot . '/config.inc.php';
if (!is_file($config)) {
    fwrite(STDERR, "Typecho config not found: {$config}\n");
    exit(1);
}

require_once $config;

if (!defined('__TYPECHO_CLASS_ALIASES__')) {
    define('__TYPECHO_CLASS_ALIASES__', array(
        'Typecho_Plugin_Interface' => '\Typecho\Plugin\PluginInterface',
        'Widget_Abstract_Metas' => '\Widget\Base\Metas',
        'Widget_Interface_Do' => '\Widget\ActionInterface',
        'Helper' => '\Utils\Helper',
    ));
}

\Widget\Options::alloc()->to($options);
$db = \Typecho\Db::get();
$row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', 'plugins')->limit(1));
$plugins = !empty($row['value']) ? @unserialize($row['value']) : array();
if (!is_array($plugins)) {
    $plugins = array();
}
if (!isset($plugins['activated']) || !is_array($plugins['activated'])) {
    $plugins['activated'] = array();
}
if (!isset($plugins['handles']) || !is_array($plugins['handles'])) {
    $plugins['handles'] = array();
}

\Typecho\Plugin::init($plugins);

foreach ($pluginNames as $pluginName) {
    $pluginName = trim((string) $pluginName);
    if ($pluginName === '') {
        continue;
    }

    list($pluginFileName, $className) = \Typecho\Plugin::portal($pluginName, $options->pluginDir);
    require_once $pluginFileName;
    if (!class_exists($className) || !method_exists($className, 'activate')) {
        fwrite(STDERR, "Plugin cannot be activated: {$pluginName}\n");
        exit(1);
    }

    if (\Typecho\Plugin::exists($pluginName)) {
        \Typecho\Plugin::deactivate($pluginName);
    }

    call_user_func(array($className, 'activate'));
    \Typecho\Plugin::activate($pluginName);
    echo "Refreshed plugin registration: {$pluginName}\n";
}

$exported = \Typecho\Plugin::export();
if (!empty($row)) {
    $db->query($db->update('table.options')
        ->rows(array('value' => serialize($exported)))
        ->where('name = ?', 'plugins'));
} else {
    $db->query($db->insert('table.options')->rows(array(
        'name' => 'plugins',
        'user' => 0,
        'value' => serialize($exported),
    )));
}
'@

$tempFile = Join-Path $env:TEMP ("qiwi-refresh-plugins-" + [System.Guid]::NewGuid().ToString("N") + ".php")
try {
    Set-Content -LiteralPath $tempFile -Value $helper -Encoding ASCII
    & $PhpBin @phpRuntimeArgs $tempFile $TypechoRoot @RefreshPlugins
    if ($LASTEXITCODE -ne 0) {
        throw "Plugin registration refresh failed with exit code $LASTEXITCODE"
    }
} finally {
    if (Test-Path -LiteralPath $tempFile) {
        Remove-Item -LiteralPath $tempFile -Force
    }
}

Remove-ObsoletePluginCopies -PluginNames $ObsoletePlugins
