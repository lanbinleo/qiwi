# AGENTS.md

This file helps coding agents work safely and efficiently in the `qiwi` Typecho theme.

## Project Snapshot

- Stack: Typecho theme using PHP templates, one main stylesheet, and small inline/browser-side JavaScript.
- No build step: files in this directory are loaded directly by Typecho.
- Primary goal of changes: preserve the current visual language while improving maintainability, responsiveness, and content readability.

## Important Files

- `header.php`: `<head>` output, global navbar, viewport, theme toggle entry point.
- `footer.php`: footer markup, theme toggle logic, code highlighting, post-list click behavior, and global interaction scripts.
- `assets/css/style.css`: main source of truth for layout, typography, page templates, components, and responsive behavior.
- `index.php`: home page layout and sticky-post aggregation logic.
- `archive.php`: archive/search/tag/category listing page.
- `post.php`: single post layout.
- `page.php`: generic page layout.
- `page-about.php`: about page.
- `page-friends.php`: friends page and apply form.
- `page-timemachine.php`: timemachine page, publisher UI, and modal logic.
- `sidebar.php`: sidebar blocks used on list pages.
- `comments.php`: comment list and form.
- `functions.php`: theme options and per-post custom fields.
- `version.php`: theme version string used in the footer.
- `reference/`: legacy reference material. Read-only unless a task explicitly asks to sync it.

## Working Rules

- Treat this as a production theme: prefer small, focused edits over large rewrites.
- Keep Typecho template calls intact. When changing markup, preserve PHP conditions and widget output.
- Reuse the existing design tokens in `assets/css/style.css` instead of hardcoding unrelated colors or spacing.
- When adding JavaScript, prefer progressive enhancement. The page should remain usable if the script does not run.
- Avoid browser-only assumptions that break no-JS or small-screen use cases.
- Do not edit `reference/` files unless the task explicitly asks for it.

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

1. `php -l header.php footer.php index.php archive.php post.php page.php page-about.php page-friends.php page-timemachine.php comments.php sidebar.php functions.php`
2. `rg -n "@media|overflow|grid-template-columns|display:\\s*none|details|summary" assets/css/style.css`
3. If JavaScript changed, review for null-safe DOM access and keyboard accessibility.

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
