# Qiwi 2.0 后续开发事项

本文记录 2.0 设计更新后仍保留的 v1 兼容代码，以及排查后决定暂不处理的事项。处理时应逐项确认前端调用、后台配置、导入导出和历史数据兼容，不与无关功能调整同时进行。

## 旧侧边栏配置清理（2.1.3 已完成）

Qiwi 2.0 使用 `.v2-side` 作为桌面侧栏。旧版 `sidebar.php` 在 v2 页面中被 `display: none` 整体隐藏，但此前仍被 5 个列表模板 include，每次列表页都白跑最近文章、分类、归档、标签四组查询。2.1.3 已完成退役：

- 删除 `sidebar.php`，并从 `index.php`、`archive.php`、`page-archives.php`、`page-categories.php`、`page-tags.php` 移除 `<aside class="sidebar">` 与 include。
- 删除仅供旧侧栏使用的 PHP 辅助函数：`qiwiGetSidebarProfileText`、`qiwiRenderSidebarAnnouncement`（含 `[province]` 访客归属地链路与 QiwiTheme 的 `clientIp()`）、`qiwiGetSidebarSocialLinks`、`qiwiSidebarSocialUsesFontAwesome`、`qiwiNormalizeSidebarEmailUrl`。
- 从 `functions.php` 移除选项 `sidebarProfileText`、`showSidebarAnnouncement`、`sidebarAnnouncement`、`sidebarBlock`、`sidebarSocialLinks`、`enableHitokoto`；`assets/js/admin-config.js` 同步移除"侧边栏"tab、社交链接结构化编辑器、字段移动、推荐值和导入导出键名；`assets/css/admin-config.css` 删除对应孤儿样式。
- 数据库中已有的旧选项值不主动删除，主题停止读取即可；旧配置备份导入时这些键按未知键忽略。

### 保留的选项

- `sidebarProfileAvatar`
  - v2 桌面侧栏头像仍在读取（`header.php`），后台已移入"导航栏"tab 并改名为"导航栏 - 头像"。字段名保留以兼容已有设置。
- `sidebarMomentCount`
  - 首页顶部最近动态仍在读取，字段名称保留以兼容已有设置。
- `enableBusuanzi`
  - 仍控制 `footer.php` 的不蒜子脚本加载，后台已移入"网站信息"tab。

## 旧版样式文件

- Qiwi 2.0 前端当前只加载 `assets/css/v2.css`，没有加载 `assets/css/style.css`。
- `style.css` 仍保存 v1 的 Hero、旧侧栏、链接预览、即刻条等旧组件样式，但不参与当前前端渲染。
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

## 已知问题记录（2.1.3 审计遗留）

以下条目在 2.1.3 全面审计中确认存在，但评估后决定不在本版处理，仅作记录。

### 验证码插件复制了核心登录流程（维护性风险）

- `plugins/QiwiCap/Plugin.php` 与 `plugins/Geetest/Plugin.php` 的 `login()` 各自完整复刻了 `Widget_User::login()`（查用户、`PasswordHash` 校验、`commitLogin`/`loginSucceed`/`loginFail`），因为 Typecho 没有"密码校验前"的登录验证码钩子。
- 当前 Typecho 1.2/1.3 下工作正常，但核心升级改动登录实现时两份拷贝可能同时偏离。后续可考虑只包一层"验证前置 + 调用原实现"。

### 访客邮箱身份仍是自报身份

- 说说点赞用访客自报邮箱做跨设备去重身份。2.1.3 已用 HMAC 凭证保护"取消点赞"，但知道他人邮箱的访客仍可以对方身份**新增**点赞（影响仅为计数 +1 与后台台账显示）。彻底解决需要邮箱验证，暂不引入。

### `enabledCaptcha` 从未保存过的站点

- 2.1.3 起主题与两个验证码插件对 `enabledCaptcha` 未保存的默认语义统一为"关闭"。此前依赖"未保存即启用"的站点升级后需要到主题设置里显式开启一次验证码。

## 完成条件（旧侧栏清理，已满足）

- `rg` 检查不到已删除选项在主题运行代码中的引用。
- 后台设置不再出现无效字段或空白面板。
- 配置导入导出不再携带已删除字段，并能忽略旧备份中的未知字段。
- 首页最近动态、桌面侧栏头像和访问统计保持可用。
- 所有 PHP 文件分别通过 PHP 7.3 和 PHP 8.2 lint。
- `assets/js/admin-config.js` 通过 `node --check`。
