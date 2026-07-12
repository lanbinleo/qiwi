# Qiwi Companion Plugins

这个目录用于存放随 Qiwi 主题维护的伴生插件源码。这里的插件不会被 Typecho 自动启用；启用前需要把对应插件目录复制到站点的 `usr/plugins/` 目录。

当前插件：

- `QiwiCap`：推荐的自托管 CAP proof-of-work 评论/登录验证码插件。
- `Geetest`：Qiwi GTest 特殊版评论/登录验证码插件，保留 Geetest 技术标识以兼容 Typecho 插件加载和既有配置。
- `QiwiSitemap`：标准 sitemap、robots.txt、RSS/Atom 发现链接和 Qiwi 风格 sitemap 可视化页面。
- `QiwiTheme`：Qiwi 主题伴生插件，提供 Thread 数据存储、后台增强接口、说说点赞与外链跳转统计。
- `QiwiCommentMail`：Qiwi 评论邮件提醒插件，基于 CommentToMail 原版维护，感谢 xcsoft 的原始贡献。

运行主题根目录的 `update.sh` 更新主题时，会自动把这里的伴生插件同步到 Typecho 的 `usr/plugins/` 目录。首次启用仍需要进入 Typecho 后台启用对应插件。

`QiwiCap` 与 `Geetest` 会注册相同的评论和登录验证接口，必须二选一。新部署推荐使用 `QiwiCap`。
