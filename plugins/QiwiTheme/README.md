# Qiwi Theme

Qiwi Theme is the required companion plugin for advanced Qiwi theme admin features.

Typecho category descriptions are short fields, so Thread block JSON is stored in the plugin table instead of `typecho_metas.description`. The theme still keeps a compact category description for compatibility and renders `thread-*` category archives as full Thread pages.

Since Qiwi 2.2.0 this plugin also absorbs the former standalone companion plugins:

- **Sitemap / Feeds module** (from `QiwiSitemap`): `/sitemap.xml` and child sitemaps, `/robots.txt`, `/timemachine.xml`, RSS/Atom feed discovery links, feed shortcode compatibility and avatar enhancements.
- **Comment mail module** (from `QiwiCommentMail`, maintained from the original CommentToMail by xcsoft): queued async comment notifications via SMTP / Resend, with the `Qiwi 评论邮件` admin console for queue logs, retries and template editing.

All module settings live in the Qiwi theme settings page (tabs 「站点地图 / 订源」 and 「邮件通知」). The plugin itself has no config form; the theme config row survives plugin deactivation, so settings are never reset by toggling the plugin.

The comment mail module is maintained from the original CommentToMail by xcsoft and keeps its GPL-3.0 license (`LICENSE.CommentToMail.txt`). The vendored PHPMailer keeps its own LGPL license headers.

The mail worker endpoint keeps the original action name `/action/qiwi-comment-mail?do=deliverMail&key=...`, so existing external cron jobs keep working after the merge.

## Usage

1. Copy this plugin directory to `usr/plugins/QiwiTheme`, or run the Qiwi theme updater after it is released.
2. Enable `QiwiTheme` in the Typecho admin plugin page.
3. Edit a category whose slug starts with `thread-`.
4. Use the Thread editor to manage metadata, article blocks, text blocks, and Markdown blocks.

The editor can copy the complete JSON payload, but saving writes the canonical data into the plugin table.
When creating a new `thread-*` category, the first category save also carries the complete JSON payload so the plugin can persist it as soon as Typecho assigns the new category ID.

## Upgrading from QiwiSitemap / QiwiCommentMail

1. Update the theme files (the updater also removes the old plugin directories).
2. Disable `QiwiSitemap` and `QiwiCommentMail` in the admin plugin page if they are still listed.
3. Disable and re-enable `QiwiTheme` once. On activation it imports the old plugin settings into the theme config, deregisters the old plugins, and re-registers all sitemap routes and mail hooks under this plugin.

The mail queue table (`qiwi_comment_mail_queue`), queued tasks and the worker lock are preserved unchanged.
