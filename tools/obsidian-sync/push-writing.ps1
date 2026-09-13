<#
.SYNOPSIS
    Qiwi 里程页「写作经历」随笔层采集脚本。

.DESCRIPTION
    统计 Obsidian vault 里「当天新建的笔记数」并推送给博客的 do=obsidian-push 端点，
    在里程页热力图上以紫色（随笔）层展示。

    数据源策略（已按真实 vault 验证：git 有 init/批量导入日失真，本机 NTFS ctime 干净可信）：
      · ctime 可信（默认）：直接用全部现存笔记的文件系统创建时间做直方图——这是金标准。
      · ctime 不可信（检测到单日大堆积，即换机/重 clone 特征）：
        以本地信源文件（vault 内 700 System/730 Meta/writing-heatmap.json，随 vault 提交到 GitHub）
        为历史底座，git 首提日期只补「信源快照之后」的新日子，再叠加未提交新文件的 ctime。
        这样导入日的失真数据永远不会进入统计。

    服务端为 merge 语义（推送只更新出现的日子，缺席的日子永远保留）；-Rebuild 才全量替换。
    隐私红线：只上送「日期 -> 篇数」，绝不涉及标题、内容或路径。

.PARAMETER DryRun
    只扫描并打印结果，不推送、不写本地信源文件。

.PARAMETER NoExport
    跳过写本地信源文件（默认每次都会刷新）。

.PARAMETER Rebuild
    以 replace:true 全量替换服务端缓存（人工确认后使用）。

.PARAMETER Days
    只推送最近 N 天（默认 0 = 全量；服务端上限 4000 天）。

.EXAMPLE
    .\push-writing.ps1 -DryRun
    .\push-writing.ps1 -Endpoint https://blog.example.com/action/qiwi-theme?do=obsidian-push -Token <令牌>
#>
param(
    [string]$VaultPath = 'D:\Desktop\写作人生',
    [string]$Endpoint = 'http://localhost:8063/action/qiwi-theme?do=obsidian-push',
    [string]$Token = '',
    [switch]$DryRun,
    [switch]$NoExport,
    [switch]$Rebuild,
    [int]$Days = 0
)

$ErrorActionPreference = 'Continue'

# Windows PowerShell 5.1 在旧版 .NET 上可能默认不启用 TLS 1.2，推送 HTTPS 端点会失败
try {
    [Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12
} catch { }

# ==== 配置区 ====
$ExcludeDirs = @('.trash', '.obsidian', '.git', '.makemd', '.space', '.claude', '700 System')
$ExportRelPath = '700 System/730 Meta/writing-heatmap.json'
$SuspiciousDayCount = 10   # 单日超过此数打印堆积警告
$HardPileCount = 30        # 单日达到此数，无论占比如何都判定 ctime 不可信
$PileShare = 0.6           # 单日占比达到此值且 >= 10 个文件，判定 ctime 不可信

function Test-ExcludedPath([string]$RelPath) {
    foreach ($seg in ($RelPath -split '/')) {
        if ($ExcludeDirs -contains $seg) { return $true }
    }
    return $false
}

# git 输出统一按 UTF-8 解码（Windows 默认按 GBK 解会把中文文件名读花）
function Invoke-GitText([string[]]$GitArgs) {
    $prev = [Console]::OutputEncoding
    try {
        [Console]::OutputEncoding = New-Object System.Text.UTF8Encoding($false)
        & git -C $VaultPath -c core.quotepath=false @GitArgs 2>$null
    } finally {
        [Console]::OutputEncoding = $prev
    }
}

function Add-DayCount($Map, [string]$Day) {
    if ($Map.ContainsKey($Day)) { $Map[$Day] = [int]$Map[$Day] + 1 } else { $Map[$Day] = 1 }
}

Write-Host ('=== Qiwi 随笔层采集 · vault: ' + $VaultPath + ' ===')

if (-not (Test-Path -LiteralPath $VaultPath)) {
    Write-Error ('vault 目录不存在：' + $VaultPath)
    exit 1
}

# ---- 1) 现存笔记的 ctime 直方图（金标准） ----
$ctimeDays = @{}
$totalFiles = 0
Get-ChildItem -LiteralPath $VaultPath -Recurse -Filter *.md | ForEach-Object {
    $rel = $_.FullName.Substring($VaultPath.Length + 1) -replace '\\', '/'
    if (Test-ExcludedPath $rel) { return }
    Add-DayCount $ctimeDays $_.CreationTime.ToString('yyyy-MM-dd')
    $totalFiles++
}

# ---- 2) ctime 信任判定：单日大堆积 = 换机/重 clone 特征 ----
$trustCtime = $true
if ($totalFiles -gt 0) {
    $peak = $ctimeDays.GetEnumerator() | Sort-Object Value -Descending | Select-Object -First 1
    $pileShareValue = [double]$peak.Value / [double]$totalFiles
    if ([int]$peak.Value -ge $HardPileCount -or (([int]$peak.Value -ge 10) -and $pileShareValue -ge $PileShare)) {
        $trustCtime = $false
        Write-Warning ('检测到 ctime 大堆积（{0} 当天 {1}/{2} 个文件），疑似换机/重 clone：本机 ctime 不可信，切换为 信源 + git 新增 模式。' -f $peak.Key, $peak.Value, $totalFiles)
    }
}

# ---- 3) 合成统计 ----
$final = @{}
$modeText = ''
$gitRecentDays = 0
if ($trustCtime) {
    $modeText = 'ctime（本机可信）'
    foreach ($d in $ctimeDays.Keys) { $final[$d] = [int]$ctimeDays[$d] }
} else {
    # 3a) 本地信源：历史底座
    $exportPath = Join-Path $VaultPath ($ExportRelPath -replace '/', '\')
    $exportDays = @{}
    $cutoffDay = ''
    if (Test-Path -LiteralPath $exportPath) {
        try {
            $exportJson = Get-Content -LiteralPath $exportPath -Raw -Encoding UTF8 | ConvertFrom-Json
            if ($exportJson.schema -eq 2 -and $exportJson.days) {
                foreach ($prop in $exportJson.days.PSObject.Properties) {
                    $v = [int]$prop.Value
                    if ($v -gt 0) { $exportDays[[string]$prop.Name] = $v }
                }
                $cutoffDay = ([datetime]$exportJson.generatedAt).ToString('yyyy-MM-dd')
            }
        } catch {
            Write-Warning '本地信源文件解析失败，本次忽略它。'
        }
    }
    foreach ($d in $exportDays.Keys) { $final[$d] = [int]$exportDays[$d] }

    # 3b) git：只补信源快照之后的新日子（-M 跳过移动，避免重组结构产生假新建）
    $gitAvailable = $false
    if (Test-Path -LiteralPath (Join-Path $VaultPath '.git')) {
        try {
            $null = Invoke-GitText @('rev-parse', '--is-inside-work-tree')
            if ($LASTEXITCODE -eq 0) { $gitAvailable = $true }
        } catch { $gitAvailable = $false }
    }
    if ($gitAvailable) {
        $logLines = @()
        try { $logLines = @(Invoke-GitText @('log', '-M', '--reverse', '--format=@%as', '--name-only', '--diff-filter=A')) } catch {}
        $currentDay = $null
        foreach ($line in $logLines) {
            if ($null -eq $line) { continue }
            if ($line.StartsWith('@')) {
                $currentDay = [string]$line.Substring(1)
                continue
            }
            $path = $line.Trim()
            if ($path -eq '' -or $null -eq $currentDay) { continue }
            if ($path -notlike '*.md') { continue }
            if (Test-ExcludedPath $path) { continue }
            if ($cutoffDay -ne '' -and [string]$currentDay -le $cutoffDay) { continue }
            $wasAbsent = -not $final.ContainsKey($currentDay)
            Add-DayCount $final $currentDay
            if ($wasAbsent) { $gitRecentDays++ }
        }

        # 未提交的新文件：ctime 在新机器上是真实的
        $others = @()
        try { $others = @(Invoke-GitText @('ls-files', '--others', '--exclude-standard')) } catch {}
        foreach ($p in $others) {
            if ($null -eq $p) { continue }
            $p = $p.Trim()
            if ($p -eq '' -or $p -notlike '*.md' -or (Test-ExcludedPath $p)) { continue }
            $full = Join-Path $VaultPath ($p -replace '/', '\')
            if (-not (Test-Path -LiteralPath $full)) { continue }
            $day = (Get-Item -LiteralPath $full).CreationTime.ToString('yyyy-MM-dd')
            if ($cutoffDay -ne '' -and $day -le $cutoffDay) { continue }
            Add-DayCount $final $day
        }
    }

    if ($final.Count -eq 0) {
        Write-Error 'ctime 不可信，且既没有本地信源文件也没有可用的 git 历史，无法统计。请先在原机器跑一次生成信源文件。'
        exit 1
    }
    if ($cutoffDay -eq '') {
        $modeText = 'git 历史（无信源文件兜底，导入日可能有失真）'
        Write-Warning '没有本地信源文件：git 导入日的批量入库可能被误计为单日多篇，建议尽快在原机器生成信源。'
    } else {
        $modeText = ('信源底座 + git 补 ' + $cutoffDay + ' 之后 ' + $gitRecentDays + ' 天')
    }
}

# ---- 4) 最近 N 天过滤 ----
if ($Days -gt 0) {
    $cutoff = (Get-Date).AddDays(-$Days).ToString('yyyy-MM-dd')
    foreach ($d in @($final.Keys)) { if ([string]$d -lt $cutoff) { $final.Remove($d) } }
}

# ---- 5) 摘要与堆积警告 ----
$sorted = [ordered]@{}
foreach ($k in ($final.Keys | Sort-Object)) { $sorted[[string]$k] = [int]$final[$k] }

Write-Host ('数据源模式：' + $modeText)
if ($trustCtime) {
    Write-Host ('现存笔记 ' + $totalFiles + ' 篇（已排除 ' + ($ExcludeDirs -join '/') + '）')
}
if ($final.Count -gt 0) {
    $keys = @($sorted.Keys)
    Write-Host ('覆盖 ' + $final.Count + ' 天：' + $keys[0] + ' ~ ' + $keys[$keys.Count - 1])
} else {
    Write-Warning '没有统计到任何笔记日。'
}
$sus = @($final.GetEnumerator() | Where-Object { [int]$_.Value -gt $SuspiciousDayCount } | Sort-Object Name)
if ($sus.Count -gt 0) {
    Write-Warning ('以下单日笔记数超过 ' + $SuspiciousDayCount + ' 篇，若非真实写作（例如批量导入）请核对后再推送：')
    foreach ($s in $sus) { Write-Warning ('  ' + $s.Key + '  ' + $s.Value + ' 篇') }
}

# ---- 6) 写本地信源（DryRun / NoExport 除外） ----
$exportPath = Join-Path $VaultPath ($ExportRelPath -replace '/', '\')
if (-not $DryRun -and -not $NoExport -and $final.Count -gt 0) {
    $dir = Split-Path -Parent $exportPath
    if (-not (Test-Path -LiteralPath $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
    $payload = [ordered]@{
        schema      = 2
        generatedAt = (Get-Date).ToString('o')
        days        = $sorted
    }
    $json = $payload | ConvertTo-Json -Depth 4
    [System.IO.File]::WriteAllText($exportPath, $json, (New-Object System.Text.UTF8Encoding($false)))
    Write-Host ('已写本地信源：' + $exportPath)
}

# ---- 7) 推送 ----
if ($DryRun) {
    Write-Host 'DryRun：未推送、未写本地信源文件。'
    exit 0
}
if ([string]::IsNullOrWhiteSpace($Token)) {
    Write-Warning '未配置 -Token，本次已（按需）写本地信源但未推送。'
    exit 0
}

$bodyObj = [ordered]@{ schema = 2; days = $sorted }
if ($Rebuild) { $bodyObj['replace'] = $true }
$body = $bodyObj | ConvertTo-Json -Depth 4

try {
    $resp = Invoke-RestMethod -Method Post -Uri $Endpoint -ContentType 'application/json' -Headers @{ 'X-Qiwi-Token' = $Token } -Body $body
    $modeWord = 'merge（历史保留）'
    if ($resp.retained -eq $false) { $modeWord = '全量替换' }
    Write-Host ('推送成功：服务端现存 ' + $resp.days + ' 天（本次更新 ' + $resp.updated + ' 天，' + $modeWord + '）。')
} catch {
    $msg = $_.Exception.Message
    try {
        $stream = $_.Exception.Response.GetResponseStream()
        if ($stream) {
            $reader = New-Object System.IO.StreamReader($stream)
            $msg = $msg + ' | ' + $reader.ReadToEnd()
        }
    } catch {}
    Write-Error ('推送失败：' + $msg)
    exit 1
}
