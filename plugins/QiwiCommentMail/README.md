## QiwiCommentMail

> Qiwi 评论邮件提醒插件。基于 CommentToMail 原版维护，感谢 xcsoft 的原始贡献。

适用版本: Typecho 1.2.0 / PHP 7.3+

## 安装参考

1. 将 `QiwiCommentMail` 目录复制到 Typecho 的 `usr/plugins/` 下
2. 在 Typecho 后台启用 `QiwiCommentMail`
3. 选择 `SMTP` 或 `Resend API` 并填写发信配置
4. 默认会在评论入队后自动处理邮件队列；低访问量站点也可以配置外部定时任务地址作为增强

## 队列设计

插件使用独立的 `qiwi_comment_mail_queue` 邮件任务表。一条队列记录只代表一封邮件:

- `owner`: 管理员通知，用于新评论或新回复
- `guest`: 用户通知，用于公开回复被回复者

这样管理员邮件和用户邮件可以分别限速、重试、记录错误，避免一类邮件失败导致另一类邮件重复发送。

## 触发规则

- 新评论完成后，按设置生成管理员通知任务
- 回复评论如果提交后已经公开，会生成用户通知任务
- 回复评论如果先进入待审核，不会通知用户；后台审核通过后再生成用户通知任务
- Time Machine 页面中，博主发布的顶层动态不会生成邮件任务
- Time Machine 页面中，访客回复动态可通知管理员；博主回复访客可通知用户

## Resend API

发信方式选择 `Resend API` 后，填写:

- `Resend API Key`: 在 Resend 后台创建的 API Key
- `Resend 发件邮箱`: 已验证域名下的邮箱地址，例如 `no-reply@example.com`
- `Resend API 地址`: 默认 `https://api.resend.com/emails` 即可
- `Resend CA 证书路径`: 可选。Windows/phpstudy 的 PHP 无法验证 HTTPS 证书时，填写 `cacert.pem` 的绝对路径；正常服务器留空即可

插件会识别 429 与 `Retry-After`，并对 5xx 或网络错误进行延迟重试。

## 邮件模板

插件设置页中可以直接编辑「管理员通知邮件模板」和「用户回复通知邮件模板」。支持变量包括:

`{{siteTitle}}`, `{{title}}`, `{{author}}`, `{{author_p}}`, `{{ip}}`, `{{mail}}`, `{{permalink}}`, `{{manage}}`, `{{text}}`, `{{text_p}}`, `{{contactme}}`, `{{time}}`, `{{status}}`

留空时会回退使用 `template/owner.html` 与 `template/guest.html`。

## 安全与可靠性

- 队列 action 使用 Key 或 Typecho 后台 token 校验
- 后台队列操作使用 POST
- 发送 worker 使用全局锁，避免并发 worker 同时发信
- 默认每次处理 2 封邮件，并按 2 req/s 限速
- 每封邮件独立记录状态、重试次数、下次重试时间和错误信息
- 队列 payload 使用 JSON，不再反序列化旧任务内容

## Copyright

QiwiCommentMail 随 Qiwi 主题维护，基于 CommentToMail 原版改造。

邮件服务采用 [PHPMailer](https://github.com/PHPMailer/PHPMailer)。

本项目采用 GNU GENERAL PUBLIC LICENSE 开源。
