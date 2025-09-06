# Qiwi Theme Changelog

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