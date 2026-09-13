# Qiwi 2.1.5 → 2.2.0 线上迁移手册

2.2.0 的插件结构发生了一次整合：`QiwiSitemap` 与 `QiwiCommentMail` 两个独立伴生插件并入了 `QiwiTheme`，全部设置收进主题设置页。本手册描述线上站从 2.1.5 升级到 2.2.0 的完整操作路径。

## 背景：这个版本动了什么

| 组件 | 变化 | 对迁移的影响 |
| --- | --- | --- |
| `QiwiTheme` | 并入站点地图 / 订源模块（原 QiwiSitemap）和评论邮件模块（原 QiwiCommentMail），新增邮件控制台面板；新增 Umami 来访统计只读联动、Obsidian 随笔推送端点 | 需要停用再启用一次，激活时自动迁移配置 |
| `QiwiSitemap` / `QiwiCommentMail` | 已从仓库删除，功能与设置全部并入 QiwiTheme 与主题配置 | 服务器上的旧目录要删除；设置自动导入，无需手工搬运 |
| 主题配置 | 新增「站点地图 / 订源」「邮件通知」两个页签共 39 项配置；Umami、Obsidian 推送令牌等新字段 | 已有的 36 项配置不受影响，新字段只是追加 |
| `QiwiCap` / `Geetest` | 仅 `@version` 号更新，无任何功能与钩子变化 | **无需重新激活，无需任何操作** |
| 里程页 | 新增写作 / 来访 / Obsidian 三口径热力图 | 无迁移动作，配置在主题设置里 |

硬性不变量（迁移前后应完全一致，可放心）：`/sitemap.xml` 等 8 条路由 URL、`/action/qiwi-comment-mail?do=deliverMail&key=…` 外部定时任务地址、邮件队列表 `qiwi_comment_mail_queue` 及其中排队任务、文集 / 点赞 / IP 归属地等既有数据表。

## 一、升级前备份（必做）

1. **全站目录**：含 `usr/themes`、`usr/plugins`、`config.inc.php`。
2. **数据库**：至少备 `options` 整表（重点是 `theme:qiwi`、`plugins`、`plugin:*` 行）和 `qiwi_comment_mail_queue` 表。
3. **记录现状**：后台「插件」页的启用清单截图；若配置过邮件外部定时任务，把 cron 里的完整 URL 抄下来。
4. **邮件队列**：如有待发送 / 失败任务，建议先在旧控制台点「立即处理队列」让它们尽量发完（不强制——队列数据升级后保留，只是迁移完成前旧 worker 入口不可用）。

## 二、文件部署

### 路线 A：update.sh（推荐）

```bash
cd <线上主题目录>   # 例如 /opt/1panel/apps/typecho/.../usr/themes/qiwi
bash update.sh
```

自动完成：拉取最新代码 → 同步 `usr/plugins/` 下的 `Geetest`、`QiwiCap`、`QiwiTheme` → **自动删除** `usr/plugins/QiwiSitemap` 与 `usr/plugins/QiwiCommentMail` 旧目录。

### 路线 B：手动复制

**要复制 / 更新的：**

- `usr/themes/qiwi/` 整个主题目录
- `usr/plugins/QiwiTheme/` 整个目录——注意它现在还包含 `Sitemap.php`、`SitemapAction.php`、`Mail.php`、`MailAction.php`、`MailConsole.php`、`page/mail.php`、`PHPMailer/`、`template/`、`sql/`、`lib/`，一个都不能少
- `usr/plugins/QiwiCap/`（仅版本号变化，建议一并更新保持版本一致；不更新也能正常跑）
- `usr/plugins/Geetest/`（仅当你在用 Geetest 时）

**不要复制 / 需要从服务器删除的：**

- `usr/plugins/QiwiSitemap/` —— 功能已并入 QiwiTheme，留着会在后台显示为无效插件
- `usr/plugins/QiwiCommentMail/` —— 同上

**不要动的：**

- `usr/plugins/QiwiCap` 与 `usr/plugins/Geetest` 不能同时启用的老规矩不变；本次**不需要**为验证码做任何重新激活，配置全部保留。

## 三、后台操作（核心步骤，按顺序）

1. **「插件」页**：若列表里还有 `QiwiSitemap` / `QiwiCommentMail` 且显示启用，逐个点「停用」。
   （停用时它们会自动把配置写进备份行，不影响后续自动导入；如果列表里已经没有它们——例如 update.sh 已删除目录——直接跳过。）
2. **「插件」页**：停用 `QiwiTheme`，再重新**启用**。
   - 激活成功提示里应包含「已自动导入原 QiwiSitemap / QiwiCommentMail 的配置并注销旧插件」；
   - 若提示「评论邮件队列表创建失败」，说明数据库异常，先排查再重试，此状态下邮件通知不可用；
   - 插件列表此时应只剩 `QiwiTheme` + `QiwiCap`（+ 可选 `Geetest`），无旧插件残留。
3. **主题设置**（后台「外观」或「Qiwi 设置」面板）：
   - 「站点地图 / 订源」页签：原 QiwiSitemap 的各开关应保持你原来的取值；
   - 「邮件通知」页签：SMTP / Resend 配置、管理员邮箱、邮件标题、模板应已自动导入；「邮件队列触发密钥」应与你原来的 Key 一致（外部 cron 无需任何改动）；
   - 页签顶部若出现橙色警告条，按提示重新激活 QiwiTheme 即可。
4. **保存一次主题设置**（重要）：邮件队列触发密钥在首次保存后才固化生效；不保存的话，新评论只入队、不会自动触发发信。

> 顺序宽容性说明：即使第 1 步没做、直接做了第 2 步，QiwiTheme 激活时也会自动注销旧插件并导入配置，结果相同；推荐顺序只是让后台状态最干净。

## 四、升级后验证清单

- [ ] `/sitemap.xml` 返回 XML（浏览器打开有可视化表格）
- [ ] `/robots.txt` 含 `Sitemap:` 行
- [ ] `/timemachine.xml` 返回 RSS（若启用时光机 RSS）
- [ ] 首页源码 head 内有 `<link rel="sitemap">` 与 RSS/Atom `<link rel="alternate">`
- [ ] 发一条测试评论 → 后台「Qiwi 评论邮件」控制台出现队列记录 → 稍后状态变「已发送」；失败可点「重试」
- [ ] 外部定时任务（若有）：URL 完全不变，手动 `curl '…/action/qiwi-comment-mail?do=deliverMail&key=<原密钥>'` 应返回 `{"code":0,…}`；密钥错误时应返回 `Permission denied`
- [ ] 里程页热力图正常渲染；配置了 Umami 后「读者来访」热力出现
- [ ] Obsidian 推送（若启用）：跑一次 `tools/obsidian-sync/push-writing.ps1`，返回 `success:true`
- [ ] 后台插件列表无 QiwiSitemap / QiwiCommentMail 残留

## 五、明确不会迁移 / 不受影响的东西

- `qiwi_comment_mail_queue` 表与其中全部数据：原样保留
- 文集（thread）、说说点赞、文章点赞、IP 归属地、外链统计的表与数据：不受影响
- QiwiCap 的配置、PoW 参数、验证码展示：不受影响
- 主题原有 36 项配置：不受影响（新配置只是追加进同一行）
- Umami / Obsidian 缓存行：随用随建，无需迁移

## 六、回滚方案（万一需要）

1. **文件回滚**：主题目录 `git checkout v2.1.5`（或用 2.1.5 发布包覆盖）；从 2.1.5 包把 `usr/plugins/QiwiSitemap`、`usr/plugins/QiwiCommentMail` 复制回服务器；`usr/plugins/QiwiTheme` 也回滚到 2.1.5 版本。
2. **配置注意**：迁移完成后，旧插件的 `plugin:` 配置行和备份行已被删除（值已搬进主题配置行）。回滚到独立插件版后，**邮件设置需要手工重填**——可以对照后台主题设置「邮件通知」页签（或 `theme:qiwi` 配置行）把 SMTP 地址、账号、密码、队列密钥抄回旧插件表单；站点地图开关同理。
3. **数据无需处理**：队列表与历史数据表名未变，2.1.5 的旧插件直接续用。
4. **回滚前建议**：先在主题设置「原始数据」页签导出整包配置留档——注意导出**不含** SMTP 密码、Resend API Key、邮件队列密钥，这三项要单独记录。
