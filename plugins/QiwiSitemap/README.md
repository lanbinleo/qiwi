# QiwiSitemap

QiwiSitemap 是 Qiwi 主题的伴生 Sitemap / RSS 发现插件。它独立于主题模板运行，适合随主题仓库维护，再同步到 Typecho 的插件目录启用。

## 功能

- 输出标准 `/sitemap.xml` sitemap index。
- 拆分输出 `/sitemap-posts.xml`、`/sitemap-pages.xml`、`/sitemap-categories.xml`、`/sitemap-tags.xml`。
- 使用文章和页面真实的 `modified` / `created` 时间生成 `lastmod`。
- 分类和标签页使用其下最新公开文章的修改时间作为 `lastmod`。
- 自动过滤未发布、加密、未来发布时间的内容。
- 支持按 CID 排除特定文章或独立页面。
- 输出 `/robots.txt`，包含 `Sitemap:` 地址和 RSS 订阅地址注释。
- 在页面 head 中补充 sitemap、RSS、Atom 发现链接。
- 输出 `/timemachine.xml` 说说 RSS，读取时光机页面作者自己的已审核评论。
- 将主题短代码转换为 RSS 阅读器更容易渲染的普通 HTML。
- 提供可视化 XSL 页面，并优先读取 Qiwi 主题头像配置。

## 安装

开发源码位于主题仓库：

```text
usr/themes/qiwi/plugins/QiwiSitemap
```

启用前，将整个 `QiwiSitemap` 目录复制到 Typecho 插件目录：

```text
usr/plugins/QiwiSitemap
```

然后进入 Typecho 后台启用 `QiwiSitemap`。

## 设计原则

这个插件不伪造更新时间，也不依赖 `priority` / `changefreq`。现代搜索引擎更看重准确、可验证的 URL 和 `lastmod`，因此插件默认只输出必要字段。

RSS 订阅不是 sitemap URL，插件会通过 HTML head discovery 和 robots 注释暴露订阅入口，避免把 feed 当成普通可索引页面写进 sitemap。

说说 RSS 也是订阅入口，不进入 sitemap。它默认自动查找 `page-timemachine.php` 页面；如果站点有多个时光机页面，可在插件设置中填写页面 CID 指定来源。

## 许可

MIT License，随 Qiwi 主题维护。
