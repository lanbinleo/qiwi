# Obsidian 随笔层采集（push-writing.ps1）

把 Obsidian vault 里「当天新建的笔记数」推送给 Qiwi 里程页，在「写作经历」热力图上以紫色（随笔）层展示。
PowerShell 5.1+，零依赖（git 可选但推荐）。**只上送「日期 → 篇数」，绝不涉及标题、内容或路径。**

## 快速开始

1. 博客后台 → 主题设置 → 站点信息：填写「Obsidian 随笔推送令牌」（随机长字符串，留空 = 关闭入口）。
2. 本脚本默认按本地联调地址（`http://localhost:8063`）推送；上线后改 `-Endpoint` 为你的博客地址：
   `https://<博客>/action/qiwi-theme?do=obsidian-push`
3. 干跑核对：
   ```powershell
   .\push-writing.ps1 -DryRun
   ```
   确认笔记数、覆盖天数、无异常堆积警告。
4. 正式推送（首次会同时在 vault 里生成本地信源文件）：
   ```powershell
   .\push-writing.ps1 -Token <你的令牌>
   ```
5. 刷新里程页，「写作经历」出现紫色层；胶囊切换多出「随笔」档，tooltip 显示「随笔 N」。

## 数据源策略（为什么不是简单的「扫文件夹」）

按真实 vault 验证过的三层防护：

| 场景 | 数据源 | 说明 |
| --- | --- | --- |
| 本机（默认） | 文件系统创建时间（ctime） | NTFS ctime 干净可信（实测最堆一天仅 8 篇），是金标准 |
| 换机 / 重 clone | 本地信源文件 + git 新增 | 检测到「单日大堆积」（≥30 篇，或 ≥10 篇且占 60%+）即弃用 ctime：以信源文件为历史底座，git 首提日期只补信源快照之后的新日子，另叠加未提交新文件的 ctime。导入日的失真数据永远不会进来 |
| 博客侧 | merge 语义 | 推送只更新出现的日子，缺席的日子永远保留；一次错误重扫抹不掉历史 |

git 的坑（都已处理）：仓库 init 日与后续批量导入日的「首提日期」会失真成单日巨堆；结构重组若不做
rename 检测会把移动误计为新建。因此本机优先 ctime，git 只在 ctime 不可信时补近期增量（带 `-M`）。

## 本地信源文件

每次成功运行都会刷新 `700 System/730 Meta/writing-heatmap.json`（`-NoExport` 跳过）：

```json
{ "schema": 2, "generatedAt": "…ISO8601…", "days": { "2026-06-17": 8, "…": 1 } }
```

- obsidian-git 会把它随 vault 自动提交到 GitHub——init 之前那段只存在于本机 NTFS 的真实创建日由此获得云端备份；
- 它的历史版本也在 git 里，等于「存档的存档」；
- 博客缓存（插件 options 行）意外丢失时，在任何机器重跑脚本即可恢复：ctime 可信时直接重扫，不可信时以信源为底座。

## 参数

| 参数 | 说明 |
| --- | --- |
| `-VaultPath` | vault 目录，默认 `D:\Desktop\写作人生` |
| `-Endpoint` | 推送端点，默认本地 `http://localhost:8063/action/qiwi-theme?do=obsidian-push` |
| `-Token` | 与主题设置一致的推送令牌 |
| `-DryRun` | 只扫描打印，不推送、不写信源 |
| `-NoExport` | 跳过写本地信源文件 |
| `-Rebuild` | 全量替换服务端缓存（人工确认后使用；默认 merge） |
| `-Days N` | 只推送最近 N 天（默认全量，服务端上限 4000 天） |

排除目录（`.trash` `.obsidian` `.git` `.makemd` `.space` `.claude` `700 System`）与堆积阈值（10/30/60%）
在脚本头部「配置区」改。

## 定时运行（可选）

每天自动扫描推送（Windows 计划任务）：

```powershell
schtasks /Create /TN "Qiwi Obsidian Sync" /SC DAILY /ST 22:00 ^
  /TR "powershell.exe -NoProfile -ExecutionPolicy Bypass -File <脚本全路径> -Endpoint <端点> -Token <令牌>"
```

也可以不建计划任务：随笔层对实时性不敏感，手动或偶尔跑一次即可。

## 服务端契约

`POST /action/qiwi-theme?do=obsidian-push`，请求头 `X-Qiwi-Token: <令牌>`，JSON 体：

```json
{ "schema": 2, "days": { "2026-09-13": 3 }, "replace": false }
```

响应 `{ "success": true, "days": 68, "updated": 68, "retained": true }`。
未来若出现 Obsidian 插件版采集端，遵守同一契约与同一信源文件即可无缝替换本脚本。
