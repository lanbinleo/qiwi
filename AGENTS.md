# AGENTS.md

This file helps coding agents work safely and efficiently in the `qiwi` Typecho theme.

## Project Snapshot

- Current line: Qiwi 2.0, using PHP templates, `assets/css/v2.css`, `assets/js/v2.js`, and template-owned inline JavaScript.
- No build step: files in this directory are loaded directly by Typecho.
- `header.php` currently loads `v2.css` directly. `assets/css/style.css` is a retained v1 stylesheet and is not part of the Qiwi 2.0 frontend runtime.
- Desktop layout uses a fixed `.v2-side` navigation column and a PJAX-updated `#qiwi-pjax` main column. At widths up to 860px, the desktop side column is replaced by the fixed mobile bar and mobile navigation.
- Primary design reference: the paper-journal visual language in `docs/new-design-v2.html`.
- Local runtime: phpstudy on Windows. Do not assume `php` is on `PATH`; use the bundled phpstudy binaries for linting.

## Important Files

- `header.php`: `<head>` output, v2 desktop/mobile navigation shell, fixed side column, viewport, and theme toggle entry points. It loads `assets/css/v2.css`; do not assume `style.css` is loaded.
- `footer.php`: v2 footer markup, closes `#qiwi-pjax` and `.v2-shell`, and retains shared interaction helpers used by templates.
- `assets/css/v2.css`: active Qiwi 2.0 frontend stylesheet and source of truth for layout, typography, components, transitions, and responsive behavior.
- `assets/js/v2.js`: active Qiwi 2.0 navigation and interaction layer, including PJAX, TOC generation, anchor transitions, code-block enhancement, likes, lightbox behavior, and comment profile setup.
- `assets/css/style.css`: legacy v1 stylesheet. It is not loaded by the current frontend. Do not add new v2 rules here; only edit it when a task explicitly concerns legacy cleanup or archival.
- `assets/js/admin-config.js`: theme settings admin UI adapter. Use this for structured option editors, tab placement, raw-data synchronization, import/export, and admin-only interactions.
- `assets/css/admin-config.css`: styles for the theme settings admin UI created by `admin-config.js`.
- `index.php`: home page layout, latest-moment display, and sticky-post aggregation logic. The v1 Home Hero has been removed and must not be reintroduced through old `homeHero*` options.
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
- `sidebar.php`: legacy v1 sidebar markup still referenced by some templates but hidden in the v2 frontend. Track its eventual removal in `TODO.md`.
- `comments.php`: comment list and form.
- `functions.php`: theme options and per-post custom fields.
- `version.php`: theme version string used in the footer.
- `index.php`: its file header also contains an `@version` value; keep it aligned with `version.php`, `update.json`, `CHANGELOG.md`, and all companion plugin `Plugin.php` headers when bumping releases.
- `components/home-jike.php`: Jike-style home feed component.
- `plugins/Geetest/`: Qiwi GTest companion plugin source for comment/login captcha integration.
- `plugins/QiwiCap/`: preferred self-hosted CAP proof-of-work captcha companion plugin for comment/login protection.
- `plugins/QiwiSitemap/`: Qiwi Sitemap plugin source maintained with this theme.
- `plugins/QiwiTheme/`: Qiwi Theme companion plugin source for `thread-*` admin editing and Thread data storage.
- `docs/new-design-v2.html`: primary Qiwi 2.0 visual and interaction reference. Treat as reference unless the task asks to edit it.
- `docs/design-doc.html`: older local visual/style reference. Prefer `new-design-v2.html` for 2.0 work.
- `docs/screenshot*.png`: theme screenshots used by project docs.
- `TODO.md`: deferred v1 cleanup work, including old sidebar options and the unloaded legacy stylesheet.
- `reference/`: legacy reference material. Read-only unless a task explicitly asks to sync it.

## Working Rules

- Treat this as a production theme: prefer small, focused edits over large rewrites.
- Before any development change, check the current Git branch. If it is not a `dev/x.x.x` branch, for example if it is `main`, ask the user which version number should carry the work before making code changes.
- When committing changes, use Conventional Commits style with the correct type prefix, such as `feat:`, `fix:`, `docs:`, `style:`, `refactor:`, `test:`, `chore:`, or `perf:`.
- When preparing a theme release, keep every public version surface synchronized: `version.php`, `update.json`, `CHANGELOG.md`, the `@version` header in `index.php`, and the `@version` headers for every companion plugin under `plugins/*/Plugin.php`.
- Keep Typecho template calls intact. When changing markup, preserve PHP conditions and widget output.
- Reuse the `--v2-*` design tokens in `assets/css/v2.css` instead of hardcoding unrelated colors or spacing.
- Do not add v2 frontend behavior to `assets/css/style.css`; the file is not loaded by the current theme shell.
- Preserve the two-column v2 shell contract: `.v2-side` stays outside `#qiwi-pjax`, while page content and the footer stay inside `#qiwi-pjax`. PJAX replacements must not replace the desktop/mobile navigation shell.
- Same-page TOC links should use the v2 hide → position → reveal interaction. Cross-page PJAX navigation should keep content hidden until scroll restoration or anchor positioning is complete.
- When adding JavaScript, prefer progressive enhancement. The page should remain usable if the script does not run.
- Avoid browser-only assumptions that break no-JS or small-screen use cases.
- Page templates with inline JavaScript may be executed again after PJAX replacement. Use idempotent initialization, `AbortController`, or explicit cleanup where repeated listeners or requests are possible.
- Do not edit `reference/` files unless the task explicitly asks for it.
- Plugin development source lives under this theme, for example `plugins/QiwiSitemap/` or `plugins/Geetest/`. Do not confuse it with the local Typecho runtime plugin directory such as `D:\phpstudy_pro\WWW\localhost\usr\plugins\QiwiSitemap`, which is only a deployment/testing copy unless the user explicitly asks to sync or inspect it.
- `plugins/QiwiCap/` is the preferred captcha provider. Keep the theme captcha calls provider-neutral through `qiwiCanRenderCaptcha()` and `qiwiRenderCaptcha()`; Qiwi CAP and Qiwi GTest must not be active at the same time.
- `plugins/QiwiTheme/` is the Qiwi theme companion plugin. When a feature cannot be solved reliably inside theme templates because it needs routes, actions, storage tables, admin APIs, or Typecho lifecycle hooks, put that logic in `QiwiTheme` instead of unrelated plugins such as `QiwiSitemap`.
- When changing companion plugin code for local testing, sync `plugins/*` into the local Typecho test runtime at `D:\phpstudy_pro\WWW\localhost\usr\plugins` before reporting completion. Use `scripts/sync-local-plugins.ps1`; it also refreshes selected plugin registrations so newly added hooks/actions/routes are written to Typecho's `options.plugins` record without deleting existing plugin configuration.

## Release Process

When publishing a new Qiwi version, use a controlled release workflow:

1. Confirm the working branch and workspace status. Release work should happen from the matching `dev/x.x.x` branch, and any pre-existing local changes must be understood before editing.
2. Run the lightweight verification checks first, including PHP linting with the bundled phpstudy PHP runtimes and any targeted JavaScript or configuration checks required by the changed files.
3. Update all public version surfaces together: `version.php`, `update.json`, `CHANGELOG.md`, the `@version` header in `index.php`, and every companion plugin header under `plugins/*/Plugin.php`.
4. Write release notes in consistent language across `CHANGELOG.md`, `update.json`, and the `$releaseNotes` entry in `version.php`. The notes should describe user-visible fixes or behavior changes, not internal implementation trivia.
5. Run the verification checks again after the release metadata is updated.
6. Review the final diff, commit with a Conventional Commits message, push the release branch, and open a pull request into the target stable branch.
7. Merge the pull request only after the checks and diff are acceptable. After merging, create and publish the GitHub Release using the same version number and release notes.

## Theme Configuration Rules

- Any change that adds, moves, renames, imports, exports, or substantially changes a theme option must use the existing admin configuration system:
  - Define the underlying Typecho option in `functions.php`.
  - Place and enhance the visible admin UI through `assets/js/admin-config.js`.
  - Style admin-only controls in `assets/css/admin-config.css`.
- Prefer structured admin UI over large free-form configuration textareas. Keep raw serialized fields, such as `navItems`, `friendsData`, `bookReference`, or compatibility fields, in the `原始数据` tab as collapsible fallback data.
- When a structured editor mirrors raw data, keep both directions synchronized in `admin-config.js`: UI edits update raw data, and raw data edits refresh the structured UI.
- If adding import/export for settings, include a clear schema identifier and integer version, for example `schema: "qiwi-theme-config"` and `version: 1`. Import logic must tolerate older versions where practical, ignore unknown keys safely, and never auto-submit or save; it should only update the current form until the user manually saves.
- When adding new config keys, update all relevant config surfaces together: field creation in `functions.php`, tab placement in `admin-config.js`, import/export field lists when applicable, frontend reads with sensible defaults/fallbacks, and verification notes.
- Removed v1 Home Hero keys (`homeHeroEyebrow`, `homeHeroLines`, `homeHeroQuote`, `homeHeroSwitchInterval`, `homeHeroTypingSpeed`, `homeHeroDeletingSpeed`, `homeHeroTypingPause`, `homeHeroAnimation`, and `homeHeroHitokotoMode`) are legacy database data. Do not recreate their fields or export them. Old imports should simply ignore them as unknown keys.
- The admin configuration opens on the navigation tab; do not add empty tabs for removed feature groups.

## Mobile-First Expectations

- Every page template should remain usable at narrow widths, including:
  - global navigation
  - list pages after the desktop `.v2-side` is replaced by mobile navigation
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

3. For responsive/CSS work, inspect the active v2 stylesheet for the relevant layout primitives:

```powershell
rg -n "@media|overflow|grid-template-columns|display:\s*none|details|summary|position:\s*fixed" assets/css/v2.css
```

4. If JavaScript changed, review for null-safe DOM access, keyboard accessibility, progressive enhancement behavior, and repeated PJAX initialization.

5. If `assets/js/admin-config.js` changed, run a syntax check:

```powershell
node --check assets/js/admin-config.js
```

If `assets/js/v2.js` changed, run:

```powershell
node --check assets/js/v2.js
```

6. If theme configuration changed, verify the option flow at code level:

- the field exists in `functions.php`
- the admin UI placement/enhancement exists in `assets/js/admin-config.js`
- admin-only styles, if any, live in `assets/css/admin-config.css`
- frontend code reads the same key with defaults/fallbacks
- raw-data editors and structured editors stay synchronized when both exist
- import/export payloads include schema/version and do not auto-save

For responsive work, code-level proof should include:

- explicit mobile breakpoints in `assets/css/v2.css`
- markup hooks that enable collapsed or stacked layouts on small screens
- no obvious fixed-width containers that exceed viewport width
- preserved access to mobile navigation, comments, pagination, modal controls, and footer actions

Do not use browser automation for routine verification unless Leo explicitly asks for it. Prefer linting, syntax checks, targeted source inspection, and other non-browser evidence.

## Common Change Patterns

- New global UI behavior usually touches `header.php`, `footer.php`, `assets/css/v2.css`, and `assets/js/v2.js`.
- V2 navigation or page-transition changes belong in `assets/js/v2.js`; avoid duplicating them in template scripts.
- Template-specific layout changes should stay close to the template that owns the markup.
- If a component is reused in multiple pages, fix it once at the shared layer instead of duplicating styles.

## Definition of Done

- PHP templates lint cleanly.
- Desktop structure is preserved.
- Mobile layout is stacked, readable, and free from known horizontal overflow paths.
- The fixed desktop side column does not move when the main document reaches its bottom.
- PJAX navigation and same-page TOC navigation restore the intended position before revealing content.
- Secondary sections are collapsible where that meaningfully reduces clutter.
- Final notes should mention what was changed and how it was verified without relying on manual browser testing.
