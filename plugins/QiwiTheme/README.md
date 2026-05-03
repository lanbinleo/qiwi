# Qiwi Theme

Qiwi Theme is the required companion plugin for advanced Qiwi theme admin features.

Typecho category descriptions are short fields, so Thread block JSON is stored in the plugin table instead of `typecho_metas.description`. The theme still keeps a compact category description for compatibility and renders `thread-*` category archives as full Thread pages.

## Usage

1. Copy this plugin directory to `usr/plugins/QiwiTheme`, or run the Qiwi theme updater after it is released.
2. Enable `QiwiTheme` in the Typecho admin plugin page.
3. Edit a category whose slug starts with `thread-`.
4. Use the Thread editor to manage metadata, article blocks, text blocks, and Markdown blocks.

The editor can copy the complete JSON payload, but saving writes the canonical data into the plugin table.
When creating a new `thread-*` category, the first category save also carries the complete JSON payload so the plugin can persist it as soon as Typecho assigns the new category ID.
