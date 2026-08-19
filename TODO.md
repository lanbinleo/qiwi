# Qiwi 2.0 后续开发事项

本文记录 2.0 设计更新后仍保留的 v1 兼容代码。处理时应逐项确认前端调用、后台配置、导入导出和历史数据兼容，不与无关功能调整同时进行。

## 旧侧边栏配置清理

Qiwi 2.0 使用 `.v2-side` 作为桌面侧栏，旧版 `sidebar.php` 在 v2 页面中已被隐藏，但相关模板、函数和后台选项仍然存在。

### 待评估移除的选项

- `sidebarProfileText`
  - 当前仅供旧版 `sidebar.php` 个人简介使用。
  - v2 侧栏不显示这段文字。
- `showSidebarAnnouncement`
  - 当前仅控制旧版侧栏公告模块。
- `sidebarAnnouncement`
  - 当前仅提供旧版侧栏公告正文。
- `sidebarBlock`
  - 当前控制旧版最近文章、分类、归档和标签模块。
- `sidebarSocialLinks`
  - 当前仅供旧版侧栏社交链接列表使用。
  - 后台包含对应的结构化编辑器和原始数据字段。
- `enableHitokoto`
  - 需要确认现有页面是否仍有一言 DOM 容器；若无调用，应同时删除页脚加载逻辑。

### 暂时保留的选项

- `sidebarProfileAvatar`
  - v2 桌面侧栏头像仍在读取。
- `sidebarMomentCount`
  - 首页顶部最近动态仍在读取，字段名称保留以兼容已有设置。
- `enableBusuanzi`
  - 仍参与统计脚本和相关输出判断，不应随旧侧栏直接删除。

## 建议处理顺序

1. 清点所有页面模板中的 `sidebar.php` 引用，确认 v2 不再需要旧侧栏 HTML。
2. 删除旧侧栏模板输出和仅供其使用的 PHP 辅助函数。
3. 从 `functions.php` 删除确认无调用的 Typecho 选项字段。
4. 从 `assets/js/admin-config.js` 删除字段移动、推荐值、结构化编辑器和导入导出键名。
5. 确认没有外部发布流程依赖后，评估归档或删除未加载的 `assets/css/style.css`。
6. 检查数据库已有旧选项的兼容策略。默认停止读取，不主动删除用户数据。

## 旧版样式文件

- Qiwi 2.0 前端当前只加载 `assets/css/v2.css`，没有加载 `assets/css/style.css`。
- `style.css` 仍保存 v1 的 Hero、旧侧栏和旧页面组件样式，但不参与当前前端渲染。
- 后续确认文档、发布包和外部引用均不依赖该文件后，可考虑整体归档或删除，不必逐段迁移到 `v2.css`。
- 项目说明中将 `style.css` 描述为主样式的内容已经过时，后续应同步更新开发文档。

## 已知问题记录（2.1.1 排查遗留）

以下条目在 2.1.2 中已处理或确认为设计行为，仅作记录。

### 站点根资源映射（已文档化）

- `qiwiGetMappedAssetUrl`（`functions.php`）把主题资源映射到站点根 `/assets/`，这是 f9f9632 以来的部署约定。
- 部署需把站点根 `/assets/` 镜像或反代到 `usr/themes/qiwi/assets/`，要求与 nginx 示例已写入 `README.md` 的「静态资源站点根映射」。
- 不做运行时自动回退：生产环境通过 nginx 反代 `/assets/` 提供缓存，站点根没有物理文件，`file_exists` 探测会误判并绕过缓存层。

### getArchiveSlug 的 Typecho 1.2 退化（保留守卫）

- `getArchiveSlug()` 仅 Typecho 1.3 存在；1.2 下 `method_exists` 守卫使 slug 为空，thread- 伪分类退化为普通分类页。
- 经确认保留 1.2 兼容守卫，该退化行为为已知设计。

### 管理员自定义输出为存储型 XSS 面（备查）

- `customCSS`/`customJS`/`trackingCode`/`footerInfo` 原样输出，属管理员输入的存储型 XSS 面，为 Typecho 主题惯例，记录备查。

## 完成条件

- `rg` 检查不到已删除选项在主题运行代码中的引用。
- 后台设置不再出现无效字段或空白面板。
- 配置导入导出不再携带已删除字段，并能忽略旧备份中的未知字段。
- 首页最近动态、桌面侧栏头像和访问统计保持可用。
- 所有 PHP 文件分别通过 PHP 7.3 和 PHP 8.2 lint。
- `assets/js/admin-config.js` 通过 `node --check`。
