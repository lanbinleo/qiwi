# Qiwi Theme Changelog

## [1.4.1] - 2026-05-03
- 新增 `QiwiSitemap` 伴生插件源码，提供标准 sitemap、robots.txt 与 RSS/Atom 发现链接。
- Sitemap 使用真实发布时间与修改时间生成 `lastmod`，并过滤未发布、加密和未来发布时间的内容。
- 支持文章、独立页面、分类、标签拆分 sitemap，以及按 CID 排除特定内容。
- Sitemap 浏览器可视化页面支持读取 Qiwi 主题头像配置，并展示 RSS 订阅入口。
- 移除旧版 `Sitemaplanbin.zip` 插件包，改为随主题维护的 MIT 插件实现。

## [1.4.0] - 2026-05-02
- 合并新版 Qiwi 设计系统 token，统一纸面、焦糖墨、批注色、字阶、间距、圆角与动效变量。
- 优化文章页标题区、正文标题间距、文章标签/版权附注与上一篇/下一篇导航。
- 文章底部新增 Z2 附注区域，合并版权说明与标签；上一篇/下一篇支持整块点击。
- 新增文章级版权说明字段，并新增全站默认版权说明；优先级为文章字段、全站默认、主题内置默认。
- 扩展正文短代码组件，新增 `[badge]`、`[callout]`、`[button]` 与 `[buttons]`，并增强 `[fold]` 的默认展开、收起与无分隔线样式配置。
- 前台与后台预览区新增折叠块展开/收起动画，保留无 JavaScript 时的原生 `details` 可用性。
- 后台主题设置新增“网站信息”分组，集中管理 Logo、页脚、自定义 CSS/JS、追踪代码与默认版权说明。
- 首页新增 Hero 区域，支持彩色短代码轮播、一言模式、淡入淡出/打字机切换、打字速度与停顿配置。
- 首页 Hero 一言请求加入超时兜底，轮播改为按次调度，并让文字选中高亮跟随当前强调色。
- 优化移动端导航为顶部下滑抽屉，支持二级菜单折叠、点击外部关闭与小屏滚动锁定。
- 重做首页侧边栏个人信息区，导航栏头像与侧边栏头像/文字/社交链接拆分配置。
- 侧边栏社交链接改为结构化编辑器，支持自定义数量、图标、悬浮名称和链接，并与原始数据双向同步。
- 后台主题设置重排为首页、导航栏、侧边栏、网站信息、关于页面、友链、归档、后台与安全、原始数据；结构化配置与原始数据双向同步。
- 新增整包配置导入/导出，支持 JSON 与 Base64，导入仅替换当前表单并保留手动保存确认。
- 后台可渲染文本输入加入短代码/颜色快捷工具栏，默认建议改为 placeholder 形式展示。
- 后台文章编辑器新增 Qiwi 快捷插入菜单，可直接插入彩色文字、背景标记和折叠块短代码。
- 文章列表、侧边栏标签和分类链接统一彩色 term 样式，正文表格样式也同步优化。

## [1.3.5] - 2026-05-01
- 更新检查默认改用 GitHub Contents API 读取 `update.json`，避免 raw 文件缓存导致版本提示延迟。
- 更新设置弹窗新增更新源选择，可在 GitHub API 与 Raw `update.json` 之间切换。
- 修复文章/页面关闭侧边目录后仍显示目录的问题，字段读取失败时会回查 Typecho `fields` 表。
- 新增后台文章/页面编辑器短代码预览样式，彩色文字、标记高亮与折叠面板可在预览区直接查看。
- 修复后台短代码预览在 Markdown 粗体、链接等跨节点内容中与前台渲染不一致的问题。

## [1.3.4] - 2026-05-01
- 新增前台版本弹窗开关，可关闭版本变化后的自动更新公告弹出，页脚版本号仍可手动查看日志。
- 后台友链编辑器支持分类与友链拖拽排序，新增友链或分类后会自动滚动并聚焦到新条目。
- 更新命令新增 Typecho 根目录设置弹窗，适配 Docker、挂载目录等需要手动指定宿主机路径的场景。
- 修复 Typecho 后台全局 `span` 间距影响折叠图标与“已是最新版”状态居中的问题。

## [1.3.3] - 2026-05-01
- 新增后台异步版本检查，使用静态 `update.json` 读取远程版本信息。
- 主题设置顶部新增更新提示卡片，支持加载状态、最新版提示和更新日志展开。
- 新增 Linux 更新脚本 `update.sh`，可在当前主题目录内直接执行 `git pull` 更新。
- 更新提示会根据当前主题目录生成命令，避免手动 `cd` 到错误位置。

## [1.3.2] - 2026-05-01
- 修复独立页面 LaTeX 资源未加载的问题，支持按内容自动检测公式并兼容 `$...$`、`$$...$$`、`\(...\)` 与 `\[...\]`。
- 新增文章与页面级侧边目录控制，默认显示，可按单篇内容关闭，并增强目录标题解析与重复锚点处理。
- 优化后台扩展字段展示，按文章/页面拆分相关设置，减少无关字段干扰。
- 重构主题设置后台为可视化 Tab 面板，支持导航、友链、书籍统计、首页、侧边栏、关于页面、安全与原始数据分组。
- 友链后台管理支持分类折叠、友链折叠预览、头像懒加载、展开编辑与图标化操作。
- 书籍统计新增可视化增删排序编辑器，并优化数字输入框和图标按钮样式。

## [1.3.1] - 2026-04-30
- 优化正文图片展示，移除 hover 浮动、阴影与自动图注，新增桌面端图片灯箱预览。
- 灯箱支持从原图位置缩放打开与关闭，修复遮罩层级、滚动锁定与顶部导航残留问题。
- 移动端禁用图片灯箱，保留普通图片浏览体验。
- 调整时光机页面动态流样式，修复正文继承文章底部间距导致说说间隔过大的问题。
- 时光机列表仅首条展示头像，后续动态保留头像占位以保持内容对齐，并移除条目分隔线。
- 修复自动导航读取独立页面展示字段时可能误判隐藏的问题。
- 友链页面正文移动到友链列表之后、申请表单之前，并微调补充说明文字尺寸。
- 时光机图床设置按钮改用 Font Awesome 图标，并增加缺失元素时的空值保护。

## [1.3.0] - 2026-04-30
- 新增可配置顶部导航，支持页面 slug、页面模板、站内路径、外链、二级菜单与可选 Font Awesome 图标。
- 独立页面新增”顶部导航栏展示”字段，可控制页面是否出现在自动生成的导航栏中。
- 新增内容短代码渲染，支持 `[red]...[/red]` 等七色文字、`[mark color=”blue”]...[/mark]` 淡色标注与 `[fold title=”...”]...[/fold]` 折叠面板。
- 优化原生 `details/summary` 在正文、页面简介与归档说明中的显示样式。
- 友链页面新增副标题字段，并将页面正文移动到友链页底部作为补充信息区域。
- 优化标签云为纯文字样式，并改进分类/标签/归档页面链接解析逻辑。
- 修正导航按钮与移动端导航项圆角为 8px，统一设计系统圆角体系。
- 移除移动端代码块复制按钮的全宽覆盖，恢复自然尺寸。
- 统一 body 过渡动画为设计系统变量 `var(--transition)`。
- 清理导航列表冗余的 `list-style` 声明。

## [1.2.7] - 2026-03-22
- 新增文章浏览量记录与展示能力，详情页现在会显示浏览量。
- 调整文章列表时间展示，统一改为更自然的相对时间格式。
- 补充文章详情页元信息，展示完整发布时间、阅读时长与评论数量。
- 为浏览量统计增加轻量去重逻辑，同一访客 1 小时内重复访问同一文章不会重复计数。

## [1.2.6] - 2026-03-19
- 新增首页即刻条，可在主题设置中选择展示位置与时间显示方式。
- 支持从时间机器页面提取最新动态并在首页轮播展示。
- 修复纯图片或纯代码动态在首页即刻条中被静默跳过的问题，现在会显示兜底提示文案。

## [1.2.5] - 2026-03-19
- 新增文章目录 TOC，支持回到顶部、前往评论与当前标题高亮。
- 调整单篇文章双栏布局，改为正文在左、目录在右，同时整理侧边栏粘性结构。
- 修复无二级标题或脚本不可用时仍保留 TOC 空白栏的问题。
- 修复目录跳转后标题被顶部粘性导航遮挡的问题。

## [1.2.4] - 2026-03-18
- 修复移动端首页与归档页中侧边栏仍保持左右并列的问题，小屏下改为位于主内容下方。
- 恢复页脚版本更新抽屉的正常结构与弹层交互，避免更新内容直接在页面底部展开。
- 保留移动端导航折叠，并继续优化小屏下页脚、分页和正文区域的可读性。

## [1.1.3] - 2025-09-29
- 新增了验证码功能，提升评论区的安全性和防垃圾评论能力。

## [1.1.2-alpha] - 2025-09-26
### Feature Enhancements

This release introduces a day/night mode toggle, major improvements to the friends page, and optimizations for theme initialization.

### Added
- **Day/Night Mode Toggle**: Added a toggle button for switching between light and dark themes, with persistent user preference.
- **Friends Editor**: Introduced a visual editor for managing friends, supporting JSON input for easier configuration.
- **Friends Page Comments**: Implemented a comments section on the friends page, including submission handling and custom styling.

### Changed
- **Theme Toggle Button**: Enhanced the appearance and interactivity of the theme toggle button for a better user experience.
- **Friends Page**: Refactored styles and updated the page description for improved clarity and a more welcoming tone.
- **Friends Page Comments**: Enhanced the comment section with improved layout and styling.

### Fixed
- **Theme Flicker**: Optimized theme initialization to prevent flickering when loading the site.

## [1.1.1-alpha] - 2025-09-25
### Major Feature Update

This alpha release introduces significant enhancements, including the "Timemachine" feature for interactive commenting, a redesigned post layout, improved navigation, and extensive theme customization options.

### Added
- **Timemachine Feature**: A new page template for posting and viewing comments with Markdown support and a floating action button for quick submissions.
- **Enhanced Post Layout**: Redesigned single post layout with a new card container for better readability.
- **Floating Navigation**: Added a floating navigation bar that appears on scroll for improved user experience on long pages.
- **Theme Options**:
  - Background image configuration.
  - New layout options for greater customization.
  - Custom footer information and tracking code options.
- **Post Card Component**: Created a reusable post card component displaying metadata and estimated reading time.
- **Traditional Pagination**: Implemented standard numbered pagination for archive pages.

### Changed
- **Post Navigation**: Enhanced previous/next post links with a disabled state and improved styling.
- **Sidebar Comments Widget**: Upgraded the recent comments widget to support filtering by multiple page slugs.
- **Floating Navigation Logic**: Improved responsiveness and visibility logic for the floating navigation bar.
- **Stylesheet Path**: Updated the header to use the correct stylesheet path within the `assets` directory.

### Fixed
- **Floating Navigation Visibility**: Corrected a bug where the floating navigation would not appear correctly during scrolling.
- **Comment Submission**: Improved the Timemachine comment submission process with better debugging and token validation.
- **Recent Comments Filtering**: The recent comments widget now correctly excludes comments from specific pages and limits the display count.
- **Timemachine UI**: Updated the SVG stroke color and button design for better visibility.
- **Thumbnail Display**: Corrected the default value for the `showThumbnail` option to ensure thumbnails are displayed correctly.

### Removed
- **AJAX Load More**: Removed the "Load More" button in favor of traditional pagination for improved accessibility and SEO.

## [1.0.2-alpha] - 2025-09-06
### Pre-release Updates

This alpha release includes enhancements to excerpt handling, thumbnail display, user editing capabilities, and licensing updates.

### Added
- Support for custom post excerpts in theme
- Enhanced thumbnail display options and styles for posts
- Edit button for logged-in users on post and archive pages
- Options for thumbnail display and expiry properties in theme fields
- LICENSE and SECURITY files with copyright and reporting information

### Fixed
- Excerpt display logic to use the correct method for rendering
- Theme version updated to 1.0.1 in footer and index files
- Theme version updated to 1.0.0 and added version.php for dynamic version display
- License header corrected to include "MIT LICENSE"

### Changed
- Added version name "Qiwer" to the initial stable release in CHANGELOG

## [1.0.0] - 2025-09-06
### Initial Stable Release

This is the first stable release of the Qiwi theme for Typecho, transitioning from the initial development version (0.0.1) to a fully featured, production-ready theme. Qiwi provides a clean, minimalist dark design focused on readability and modern web standards. The name of this version (1.0) is called Qiwer.

### Added
- **Core Theme Structure**: Complete WordPress-like theme files including `index.php`, `post.php`, `page.php`, `header.php`, `footer.php`, `sidebar.php`, `functions.php`, `archive.php`, `404.php`, and `comments.php`.
- **Dark Minimalist Design**: Comprehensive CSS styling with custom properties (variables) for colors, typography, spacing, and layout. Features a sophisticated dark color scheme with accent highlights.
- **Responsive Layout**: Mobile-first design with grid-based main wrapper, adaptive sidebar, and breakpoints for tablets and phones. Supports full-width content up to 1200px.
- **Post and Page Rendering**: 
  - Archive and single post previews with excerpts, metadata (date, comments, categories), and hover effects.
  - Full single post and page support with rich content rendering (images, blockquotes, code blocks, tables with alignment).
- **Sidebar Widgets**: Configurable sidebar with support for recent posts, recent comments, categories, archives, and other miscellaneous widgets. Sticky positioning on desktop.
- **Comment System**: Enhanced comment display with nested replies, author metadata, timestamps, and a styled comment form. Includes pagination for comment threads.
- **Search Functionality**: Integrated search form in the header with icon and placeholder support.
- **Navigation and Pagination**: Site navigation with dynamic page links, breadcrumb-style pagination for archives, and post navigation (previous/next).
- **LaTeX Support**: Optional KaTeX integration for mathematical rendering in posts, enabled via post fields.
- **Advanced Content Features**:
  - Tags display with cloud-style rendering.
  - Table support with responsive scrolling, alignment, and styling variants (compact, borderless).
  - Empty state handling for no posts.
  - Back-to-top button with smooth scroll.
- **Theme Configuration**: Admin panel options for logo URL and sidebar block selection (recent posts, comments, categories, archives, other).
- **Accessibility and UX**: Semantic HTML, SVG icons for metadata, focus states, print styles, and screen-reader utilities.
- **Performance and Compatibility**: Optimized CSS (1525 lines of unified styling), no external dependencies except optional KaTeX CDN.

### Changed
- **Version Update**: Bumped from 0.0.1 to 1.0.0, marking the theme as stable.
- **Documentation**: Updated README.md with installation instructions, customization guide, and feature list. Added screenshot reference.

### Documentation
- See [README.md](README.md) for setup and customization details.
- Theme uses MIT License.

### Known Issues
- LaTeX rendering requires manual enabling per post.
- Search is basic; advanced search not implemented yet.

This release establishes Qiwi as a robust, feature-complete theme for Typecho users seeking a modern dark interface.

