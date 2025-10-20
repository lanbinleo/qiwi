# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

QIWI 是一个为 Typecho 博客系统设计的现代化深色主题。当前版本：1.1.5。

这是一个纯 PHP + CSS + JavaScript 项目，无需构建工具。所有资源文件直接包含在主题目录中，通过 Typecho 系统动态加载。

## Architecture

### Core Template System

QIWI 使用基于 Typecho 的 PHP 模板系统，核心文件组织如下：

**主模板文件**：
- `index.php` - 首页文章列表
- `post.php` - 单篇文章详情页
- `page.php` - 通用页面模板
- `archive.php` - 分类/标签/搜索结果页
- `404.php` - 错误页面

**特殊页面模板**（通过文件名匹配）：
- `page-about.php` - 关于页面（带头像卡片和个人简介）
- `page-friends.php` - 友链页面（支持 JSON 配置和分类）
- `page-timemachine.php` - 时光机页面（评论互动墙）

**可复用组件**：
- `post-card.php` - 文章卡片组件，被首页和归档页复用。包含自动字数统计、阅读时间计算、灵活的头图布局（顶部/左右交替）
- `header.php` - 页面头部，包含导航、搜索、主题切换按钮
- `footer.php` - 页脚，包含代码高亮初始化、交互脚本、统计代码
- `sidebar.php` - 侧边栏，支持最新文章/评论/分类/归档等多种小工具
- `comments.php` - 评论表单和列表组件

### Configuration System

**全局配置**（`functions.php` - `themeConfig()`)：
- 后台可配置项通过 Typecho 后台保存到数据库
- 主要配置包括：
  - `logoUrl` - 站点 logo
  - `sidebarBlock` - 侧边栏组件选择
  - `aboutBio/aboutAvatar` - 关于页面内容
  - `backgroundImages` - 全局背景图片 URL 列表
  - `backgroundMask` - 背景遮罩透明度
  - `homeThumbnailLayout` - 首页头图布局模式（top/side）
  - `customCSS/customJS` - 自定义代码注入
  - `friendsData` - 友链 JSON 数据
  - `enabledCaptcha` - Geetest 验证码配置

**文章级配置**（`functions.php` - `themeFields()`)：
- 每篇文章可在编辑页面配置：
  - `isLatex` - 启用 LaTeX 渲染（条件加载 KaTeX CDN）
  - `thumbnail` - 自定义头图 URL
  - `showThumbnail` - 头图显示位置（0-3）
  - `thumbnailLayout` - 覆盖全局头图布局
  - `excerpt` - 自定义摘要
  - `enableExpiryProtection/expiryDuration` - 文章过期提醒

### CSS Architecture

**样式文件组织**（`assets/css/`）：

1. **`style.css`** - 主样式表
   - 基于 CSS 自定义属性（CSS Variables）驱动
   - 通过 `[data-theme="light/dark"]` 属性切换深色/浅色主题
   - 关键变量系统：
     ```css
     :root {
       --color-bg-primary: #0a0a0a;    /* 主背景色 */
       --color-bg-secondary: #161616;  /* 次背景色 */
       --color-accent: #d99f00;        /* 强调色（深色模式金色） */
       --spacing-xs/sm/md/lg/xl/xxl;   /* 统一间距系统 */
       --font-family-base;             /* 字体栈 */
     }
     [data-theme="light"] {
       --color-accent: #007bff;        /* 浅色模式蓝色 */
       /* ... 其他浅色变量 */
     }
     ```

2. **`enhanced-style.css`** - 增强功能样式
   - 浮动导航栏（`.floating-nav`）
   - 全局背景图片（`.global-background`）
   - 图文左右布局（`.layout-side`）

3. **`friends-style.css`** - 友链页面专用样式

### JavaScript Functionality

**主脚本**（`assets/js/enhanced-script.js`）：

核心功能模块：
- `initFloatingNav()` - 浮动导航栏：监听滚动，动态显示/隐藏克隆的导航栏
- `initBackgroundImages()` - 背景图片管理：从配置中随机选择背景并预加载
- `createThemeToggleBtn()` - 主题切换：深色/浅色切换 + 代码高亮主题同步
- `initScrollEffects()` - 滚动效果：返回顶部按钮、搜索框焦点样式
- `initSearchEnhancements()` - 搜索增强：输入框交互优化

**配置注入机制**：
- `header.php` 中通过 `<script>` 标签注入 `window.qiwiThemeConfig` 对象
- 包含背景图片、遮罩透明度等前端需要的配置
- JavaScript 从该对象读取配置并执行相应逻辑

### Page Rendering Flow

典型的页面渲染流程：

```
1. header.php 执行
   ├── 输出 <head> 和主题配置脚本（window.qiwiThemeConfig）
   ├── 条件加载 LaTeX CDN（如果文章启用了 isLatex）
   ├── 加载 enhanced-script.js（defer）
   └── 输出导航和搜索栏

2. 特定页面模板执行（index/post/page 等）
   ├── 查询数据库获取内容
   ├── 可能包含 post-card.php 或其他组件
   └── 输出主内容区

3. sidebar.php 执行（大多数页面）
   └── 根据配置显示侧边栏小工具

4. footer.php 执行
   ├── 初始化代码高亮（highlight.js）
   ├── 加载其他交互脚本
   ├── 注入自定义 JS 和统计代码
   └── 输出版本信息对话框
```

## Development Workflow

### Testing Changes

由于这是 Typecho 主题，无独立的测试命令。测试需要在 Typecho 环境中进行：

1. **安装到 Typecho**：将主题放置在 `usr/themes/qiwi/` 目录
2. **激活主题**：在 Typecho 后台 -> 外观 -> 启用主题
3. **实时预览**：修改文件后刷新浏览器查看效果
4. **浏览器开发者工具**：检查 CSS/JS 是否正确加载

### Version Management

版本信息由 `version.php` 管理：
- 当前版本：`1.1.5`
- 包含版本对话框 HTML 和更新公告
- 使用 localStorage 追踪用户已查看的版本（`qiwiThemeVersion`）

更新版本时需要：
1. 修改 `version.php` 中的版本号和更新公告
2. 更新 `CHANGELOG.md`
3. 在 Git 中创建对应的 tag

### Code Modification Guidelines

**修改 PHP 模板时**：
- 确保 Typecho API 调用正确（`$this->xxx()`）
- 保持与其他模板的风格一致
- 注意 `<?php $this->need('xxx.php'); ?>` 引入其他组件
- 文章字段通过 `$this->fields->xxx` 访问

**修改 CSS 时**：
- 优先使用 CSS 变量而非硬编码颜色值
- 确保深色/浅色主题都能正常显示
- 使用统一的间距系统（`--spacing-xx`）
- 新增样式前检查是否已有类似样式可复用

**修改 JavaScript 时**：
- 保持函数式编程风格
- 新增功能在 `enhanced-script.js` 的 DOMContentLoaded 中调用
- 涉及主题配置时从 `window.qiwiThemeConfig` 读取
- 使用 `debounce()` 优化高频事件处理

## Key Features and Implementations

### 1. 深色/浅色主题切换
- CSS 通过 `[data-theme]` 属性切换
- JavaScript 监听点击事件，修改 `<html>` 的 `data-theme` 属性
- 代码高亮主题同步切换（通过修改 `<link>` 的 href）
- 用户偏好保存在 localStorage（`theme`）

### 2. 浮动导航栏
- 页面滚动超过 200px 时显示
- 从原始导航栏克隆并添加特殊样式（毛玻璃效果）
- 滚动到顶部时自动隐藏
- 包含返回顶部按钮和搜索框

### 3. 文章卡片布局模式
- **Top 模式**：头图在顶部，全宽显示
- **Side 模式**：图文左右布局，奇偶交替（`.layout-side-left/right`）
- 可在全局配置或单篇文章中设置
- `post-card.php` 根据配置动态生成 HTML 结构

### 4. 友链系统
- 后台配置 JSON 数据（`friendsData`）
- 支持分类（`category` 字段）
- 包含可视化编辑器（`assets/utils/friends-editor.html`）
- 友链卡片带头像、描述、链接、图标

### 5. LaTeX 数学公式支持
- 文章启用 `isLatex` 字段时条件加载 KaTeX CDN
- 在 `header.php` 中动态注入 `<link>` 和 `<script>`
- 不使用时不加载，优化性能

### 6. 代码高亮
- 使用 highlight.js CDN
- 支持多种语言和主题
- 深色/浅色模式分别使用不同高亮主题
- 代码块包含复制按钮（`footer.php` 中的脚本实现）

## Important Notes

### Typecho API Usage
- 文章循环：`<?php while($this->next()): ?>`
- 输出标题：`<?php $this->title() ?>`
- 输出内容：`<?php $this->content() ?>`
- 输出链接：`<?php $this->permalink() ?>`
- 输出选项：`<?php $this->options->xxx() ?>`
- 自定义字段：`<?php $this->fields->xxx ?>`

### File Paths
- 主题根目录通过 `$this->options->themeUrl` 获取
- 静态资源路径：`$this->options->themeUrl('assets/xxx')`
- 引入组件：`$this->need('component.php')`

### Theme Initialization Script
在 `header.php` 中有一段立即执行的脚本：
```javascript
(function() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        document.documentElement.setAttribute('data-theme', savedTheme);
    }
})();
```
**目的**：防止主题闪烁（FOUC - Flash of Unstyled Content）
**原理**：在页面渲染前立即读取并应用用户保存的主题偏好

### Common Pitfalls
- **修改配置后需清理缓存**：Typecho 可能缓存模板输出
- **JavaScript 变量命名冲突**：避免覆盖 `window.qiwiThemeConfig`
- **CSS 变量覆盖顺序**：确保 `[data-theme="light"]` 在 `:root` 之后定义
- **文章字段未定义检查**：使用 `<?php if($this->fields->xxx): ?>` 判断

## Reference Documentation

- Typecho 官方文档：http://docs.typecho.org/
- KaTeX 文档：https://katex.org/
- highlight.js 文档：https://highlightjs.org/
- 项目更新日志：见 `CHANGELOG.md`
- 开发文档：见 `dev-docs/` 目录
