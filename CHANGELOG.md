# Qiwi Theme Changelog

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

