# Qiwi Companion Plugins

这个目录用于存放随 Qiwi 主题维护的伴生插件源码。这里的插件不会被 Typecho 自动启用；启用前需要把对应插件目录复制到站点的 `usr/plugins/` 目录。

当前插件：

- `QiwiSitemap`：标准 sitemap、robots.txt、RSS/Atom 发现链接和 Qiwi 风格 sitemap 可视化页面。

运行主题根目录的 `update.sh` 更新主题时，会自动把这里的伴生插件同步到 Typecho 的 `usr/plugins/` 目录。首次启用仍需要进入 Typecho 后台启用对应插件。
