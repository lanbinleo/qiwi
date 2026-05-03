# AGENTS.md

This file helps coding agents work safely and efficiently in the `qiwi` Typecho theme.

## Project Snapshot

- Stack: Typecho theme using PHP templates, one main stylesheet, and small inline/browser-side JavaScript.
- No build step: files in this directory are loaded directly by Typecho.
- Primary goal of changes: preserve the current visual language while improving maintainability, responsiveness, and content readability.
- Local runtime: phpstudy on Windows. Do not assume `php` is on `PATH`; use the bundled phpstudy binaries for linting.

## Important Files

- `header.php`: `<head>` output, global navbar, viewport, theme toggle entry point.
- `footer.php`: footer markup, theme toggle logic, code highlighting, post-list click behavior, and global interaction scripts.
- `assets/css/style.css`: main source of truth for layout, typography, page templates, components, and responsive behavior.
- `assets/js/admin-config.js`: theme settings admin UI adapter. Use this for structured option editors, tab placement, raw-data synchronization, import/export, and admin-only interactions.
- `assets/css/admin-config.css`: styles for the theme settings admin UI created by `admin-config.js`.
- `index.php`: home page layout and sticky-post aggregation logic.
- `archive.php`: archive/search/tag/category listing page.
- `404.php`: not-found page template.
- `post.php`: single post layout.
- `post-card.php`: shared post-card/list item partial.
- `page.php`: generic page layout.
- `page-about.php`: about page.
- `page-archives.php`: yearly archive page.
- `page-categories.php`: category index page.
- `page-friends.php`: friends page and apply form.
- `page-tags.php`: tag cloud page.
- `page-timemachine.php`: timemachine page, publisher UI, and modal logic.
- `sidebar.php`: sidebar blocks used on list pages.
- `comments.php`: comment list and form.
- `functions.php`: theme options and per-post custom fields.
- `version.php`: theme version string used in the footer.
- `index.php`: its file header also contains an `@version` value; keep it aligned with `version.php`, `update.json`, and `CHANGELOG.md` when bumping releases.
- `components/home-jike.php`: Jike-style home feed component.
- `plugins/QiwiSitemap/`: Qiwi Sitemap plugin source maintained with this theme.
- `plugins/QiwiTheme/`: Qiwi Theme companion plugin source for `thread-*` admin editing and Thread data storage.
- `docs/design-doc.html`: local visual/style references. Treat as reference unless the task asks to edit them.
- `docs/screenshot*.png`: theme screenshots used by project docs.
- `reference/`: legacy reference material. Read-only unless a task explicitly asks to sync it.

## Working Rules

- Treat this as a production theme: prefer small, focused edits over large rewrites.
- Before any development change, check the current Git branch. If it is not a `dev/x.x.x` branch, for example if it is `main`, ask the user which version number should carry the work before making code changes.
- When committing changes, use Conventional Commits style with the correct type prefix, such as `feat:`, `fix:`, `docs:`, `style:`, `refactor:`, `test:`, `chore:`, or `perf:`.
- Keep Typecho template calls intact. When changing markup, preserve PHP conditions and widget output.
- Reuse the existing design tokens in `assets/css/style.css` instead of hardcoding unrelated colors or spacing.
- When adding JavaScript, prefer progressive enhancement. The page should remain usable if the script does not run.
- Avoid browser-only assumptions that break no-JS or small-screen use cases.
- Do not edit `reference/` files unless the task explicitly asks for it.
- Plugin development source lives under this theme, for example `plugins/QiwiSitemap/`. Do not confuse it with the local Typecho runtime plugin directory such as `D:\phpstudy_pro\WWW\localhost\usr\plugins\QiwiSitemap`, which is only a deployment/testing copy unless the user explicitly asks to sync or inspect it.

## Theme Configuration Rules

- Any change that adds, moves, renames, imports, exports, or substantially changes a theme option must use the existing admin configuration system:
  - Define the underlying Typecho option in `functions.php`.
  - Place and enhance the visible admin UI through `assets/js/admin-config.js`.
  - Style admin-only controls in `assets/css/admin-config.css`.
- Prefer structured admin UI over large free-form configuration textareas. Keep raw serialized fields, such as `navItems`, `friendsData`, `bookReference`, or compatibility fields, in the `原始数据` tab as collapsible fallback data.
- When a structured editor mirrors raw data, keep both directions synchronized in `admin-config.js`: UI edits update raw data, and raw data edits refresh the structured UI.
- If adding import/export for settings, include a clear schema identifier and integer version, for example `schema: "qiwi-theme-config"` and `version: 1`. Import logic must tolerate older versions where practical, ignore unknown keys safely, and never auto-submit or save; it should only update the current form until the user manually saves.
- When adding new config keys, update all relevant config surfaces together: field creation in `functions.php`, tab placement in `admin-config.js`, import/export field lists when applicable, frontend reads with sensible defaults/fallbacks, and verification notes.

## Mobile-First Expectations

- Every page template should remain usable at narrow widths, including:
  - global navigation
  - list pages with sidebar
  - single article/page content
  - comments
  - about/friends/timemachine pages
  - footer and pagination
- Prefer collapsible sections on mobile when secondary information would otherwise create long vertical clutter.
- Guard against horizontal overflow from:
  - long nav items
  - code blocks
  - tables
  - images
  - pagination
  - footer metadata rows
  - modal dialogs

## Safe Verification

There is no formal test suite in this repo. Use lightweight checks after edits:

1. Lint all theme PHP files with both bundled phpstudy runtimes. Use `-n` so PHP 8 is not blocked by older phpstudy ini directives such as `track_errors`.

```powershell
$phpFiles = rg --files -g '*.php' -g '!reference/**'
$phpBins = @(
    'D:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe',
    'D:\phpstudy_pro\Extensions\php\php8.2.9nts\php.exe'
)
foreach ($php in $phpBins) {
    Write-Output "== $php -n =="
    foreach ($file in $phpFiles) {
        & $php -n -l $file
        if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    }
}
```

2. For targeted quick checks, lint only touched files with both binaries, for example:

```powershell
& 'D:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe' -n -l sidebar.php
& 'D:\phpstudy_pro\Extensions\php\php8.2.9nts\php.exe' -n -l sidebar.php
```

3. For responsive/CSS work, inspect the main stylesheet for the relevant layout primitives:

```powershell
rg -n "@media|overflow|grid-template-columns|display:\s*none|details|summary" assets/css/style.css
```

4. If JavaScript changed, review for null-safe DOM access, keyboard accessibility, and progressive enhancement behavior.

5. If `assets/js/admin-config.js` changed, run a syntax check:

```powershell
node --check assets/js/admin-config.js
```

6. If theme configuration changed, verify the option flow at code level:

- the field exists in `functions.php`
- the admin UI placement/enhancement exists in `assets/js/admin-config.js`
- admin-only styles, if any, live in `assets/css/admin-config.css`
- frontend code reads the same key with defaults/fallbacks
- raw-data editors and structured editors stay synchronized when both exist
- import/export payloads include schema/version and do not auto-save

For responsive work, code-level proof should include:

- explicit mobile breakpoints in `assets/css/style.css`
- markup hooks that enable collapsed or stacked layouts on small screens
- no obvious fixed-width containers that exceed viewport width
- preserved access to navigation, sidebar content, comments, and footer actions

## Common Change Patterns

- New global UI behavior usually touches `header.php`, `footer.php`, and `assets/css/style.css`.
- Template-specific layout changes should stay close to the template that owns the markup.
- If a component is reused in multiple pages, fix it once at the shared layer instead of duplicating styles.

## Definition of Done

- PHP templates lint cleanly.
- Desktop structure is preserved.
- Mobile layout is stacked, readable, and free from known horizontal overflow paths.
- Secondary sections are collapsible where that meaningfully reduces clutter.
- Final notes should mention what was changed and how it was verified without relying on manual browser testing.
