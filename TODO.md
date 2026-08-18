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

## 待修复的已知问题（2.1.1 排查遗留）

以下问题在 2.1.1 bugfix 排查中确认存在，但改动面或回归风险较高，未随该版本一起修复。处理时应逐项单独开分支验证，不与无关功能调整同时进行。

### PJAX 监听器猴补竞态与泄漏（中危）

- `assets/js/v2.js` 的 `executeScripts` 在异步脚本加载期间全局替换 `EventTarget.prototype.addEventListener`，把窗口期内注册的监听器记入 `dynamicPageListeners` 并在下次导航时移除。
- 快速连续导航时，前一次导航的脚本 promise 链不会被 AbortController 取消，其稍后注册的监听器永远不会被清理，造成重复绑定与内存泄漏；同时窗口期内无关代码注册的监听器也会被误伤移除。
- 建议方向：给 `executeScripts` 加互斥或导航链取消，且只记录 PJAX 容器子树内元素的监听器。

### 同页锚点点击触发整页 PJAX 重载（中危）

- 点击同页 `#锚点` 链接不被 click 拦截，浏览器 hash 导航触发 `popstate`，`v2.js` 的 popstate 处理器一律走 `navigate()` 重新拉取整页。
- 初次加载的页面有 `initCommentTargetHighlight` 兜底（preventDefault + pushState），PJAX 加载的页面没有，表现为评论回复锚点每次点击整页重载、滚动重置。
- 建议方向：popstate 处理器在仅 hash 变化（`event.state == null` 且 pathname/search 相同）时直接定位锚点并返回。

### 站点根资源映射无回退（按部署约定评估）

- `qiwiGetMappedAssetUrl`（`functions.php`）把所有主题资源映射到站点根 `/assets/`（`f9f9632` 的部署约定，生产环境镜像该目录）。
- 部署未镜像时 `v2.css`、`v2.js`、后台增强脚本全部 404 且无提示；本地 phpstudy 测试站的 `D:\phpstudy_pro\WWW\localhost\assets\` 目前只有 `fonts/`。
- 建议方向：根路径探测不到文件时回退 `themeUrl`，或至少在文档中写明部署要求。

### navItems 结构化编辑器双向同步有损（低危）

- `admin-config.js` 的 `parseNav`/`navToText` 会把标题中的 `|` 替换、行尾 `|` 剥离、纯 `-` 行丢弃；`functions.php` 的 PHP 解析会把无父项的子项当父项渲染，与后台所见不一致。
- 建议方向：引入 `\|` 转义，PHP/JS 对首个子项统一处理。

### 低危杂项

- `qiwiRecordPostView` 为每篇文章写独立 cookie `qiwi_post_viewed_{cid}`，多文浏览后 cookie 膨胀，可考虑合并为单一有序队列。
- `getArchiveSlug()` 仅 Typecho 1.3 存在，1.2 下 thread- 伪装分类有 `method_exists` 守卫但会静默退化为普通分类页；若不再支持 1.2 可移除守卫并在文档声明。
- `index.php` 置顶聚合的注释称“总数不超过 pageSize”，实际置顶数超限时总数以置顶数为准，与 `getTotal()` 分页口径不一致。
- `customCSS`/`customJS`/`trackingCode`/`footerInfo` 原样输出，属管理员输入的存储型 XSS 面，为 Typecho 主题惯例，记录备查。

## 完成条件

- `rg` 检查不到已删除选项在主题运行代码中的引用。
- 后台设置不再出现无效字段或空白面板。
- 配置导入导出不再携带已删除字段，并能忽略旧备份中的未知字段。
- 首页最近动态、桌面侧栏头像和访问统计保持可用。
- 所有 PHP 文件分别通过 PHP 7.3 和 PHP 8.2 lint。
- `assets/js/admin-config.js` 通过 `node --check`。
