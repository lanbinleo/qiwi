# Qiwi CAP for Typecho

Qiwi CAP 将自托管的 [CAP](https://capjs.js.org/) proof-of-work CAPTCHA 接入 Typecho，可用于前台评论和后台登录。

CAP 的 challenge 在访客浏览器中完成，Typecho 只负责把 `cap-token` 发送到自托管 CAP Server 的 `/siteverify` 接口。Secret Key 不会输出到浏览器。

## 环境准备

1. 部署 CAP Standalone。
2. 在 CAP dashboard 中创建 site key，记录 Site Key 和对应的 Secret Key。
3. 确保 CAP Server URL 可以同时被访客浏览器和 Typecho 服务器访问。
4. 为 widget endpoint 配置正确的 CORS 规则。

## 安装和配置

1. 将 `QiwiCap` 目录放入 Typecho 的 `/usr/plugins`。
2. 如果 Qiwi GTest 正在运行，先停用它。两个插件不能同时启用。
3. 启用 Qiwi CAP，填写：
   - CAP Server URL，例如 `https://cap.example.com`
   - Site Key
   - Secret Key
   - Widget Script URL
4. 保存配置后，再选择需要保护的页面。

如果 Typecho 所在的 PHP 环境没有配置系统 CA，服务器验证可能提示 `unable to get local issuer certificate`。插件会自动检查 `curl.cainfo`、`openssl.cafile`、Linux 常见证书位置以及 phpstudy 常用的 Git for Windows CA bundle；也可以在插件设置中手动填写“CA 证书路径”。TLS 证书校验不会被关闭。

插件默认不启用评论或登录验证，避免配置尚未完成时影响站点操作。

## 评论表单接入

Qiwi 2.0 已通过通用验证码接口完成接入。启用主题设置中的“验证码”后，会优先使用已经启用并配置完成的 Qiwi CAP。

其他主题可以在评论 `<form>` 内部调用：

```php
<?php if (class_exists('QiwiCap_Plugin')): ?>
    <?php QiwiCap_Plugin::commentCaptchaRender(); ?>
<?php endif; ?>
```

`<cap-widget>` 会自动在当前表单中加入名为 `cap-token` 的隐藏字段。评论提交时，插件通过 `Widget_Feedback->comment` 验证 token。

验证码完成前，插件会禁用表单的提交按钮；`solve` 事件发生后才恢复提交。token 重置、过期或组件报错时，提交按钮会重新禁用。即使前端脚本被绕过，服务器仍会拒绝缺少或无效 token 的评论请求。

Typecho 点击“回复”或“取消回复”时会移动同一个评论表单。插件会识别这种 DOM 位置变化，保留尚未使用的验证 token，并避免把组件重连误报为“验证已失效”。

评论验证失败时，错误页面会显示原评论内容和复制按钮。Typecho 的评论记忆 Cookie 也会继续保存正文，返回原页面后可以接着编辑。

Qiwi 主题内部通过 `qiwiCanRenderCaptcha()` 和 `qiwiRenderCaptcha()` 选择验证码提供方，不在评论模板中依赖具体插件类。

## 后台登录

在插件设置中启用“后台登录”后，插件会在 Typecho 登录表单中加入 CAP widget。验证完成前登录按钮保持禁用，服务器会在密码校验前再次验证 token。

## 验证行为

- token 缺失、过长或验证失败时拒绝请求。
- CAP Server 超时、TLS 失败、非 2xx 响应或无效 JSON 均视为验证失败。
- 验证请求不会自动重试，因为 CAP token 是单次使用的。
- 插件不处理 trackback 和 pingback，这些请求无法在浏览器中完成 proof-of-work。
- 时光机页面中，页面作者和管理员发布内容时沿用主题既有的免验证规则。

## Widget Script

默认地址为：

```text
https://cdn.jsdelivr.net/npm/cap-widget@latest
```

正式部署建议填写固定版本地址，或将 widget 脚本放在自己的服务器上。
