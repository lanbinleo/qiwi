# Qiwi Companion Plugins

这个目录用于存放随 Qiwi 主题维护的伴生插件源码。这里的插件不会被 Typecho 自动启用；启用前需要把对应插件目录复制到站点的 `usr/plugins/` 目录。

当前插件：

- `QiwiCap`：推荐的自托管 CAP proof-of-work 评论/登录验证码插件。
- `Geetest`：Qiwi GTest 特殊版评论/登录验证码插件，保留 Geetest 技术标识以兼容 Typecho 插件加载和既有配置。
- `QiwiTheme`：Qiwi 主题伴生插件。除原有的 Thread 数据存储、后台增强接口、说说/文章点赞、IP 归属地、外链点击统计与正文高亮/涂黑标记外，自 2.2.0 起并入了原 `QiwiSitemap`（sitemap、robots.txt、RSS/Atom 订源）与原 `QiwiCommentMail`（评论邮件通知，基于 CommentToMail 原版维护，感谢 xcsoft 的原始贡献）的全部功能，设置统一收进主题设置页。

运行主题根目录的 `update.sh` 更新主题时，会自动把这里的伴生插件同步到 Typecho 的 `usr/plugins/` 目录，并清理已下线的旧伴生插件（`CommentToMail`、`QiwiSitemap`、`QiwiCommentMail`）。首次启用仍需要进入 Typecho 后台启用对应插件。

从 `QiwiSitemap` / `QiwiCommentMail` 独立插件升级：更新文件后，到后台「插件」页停用这两个旧插件（若仍在列表中），再停用并重新启用一次 `QiwiTheme`——激活时会自动把旧插件配置导入主题设置并完成注销。

`QiwiCap` 与 `Geetest` 会注册相同的评论和登录验证接口，必须二选一。新部署推荐使用 `QiwiCap`。
