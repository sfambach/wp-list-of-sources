# WP List of Sources

A WordPress Gutenberg block that automatically scans the current post and displays a list of **links**, **images**, **tables**, or **files** found in the content.

Pure JavaScript in the editor (no build step, no NPM). Server-side rendering in PHP.

**Author:** Stefan Fambach  
**Version:** 1.3.0  
**License:** GPLv2 or later

## Features

- **One block, one source type** — choose Links, Images, Tables, or Files in the block sidebar
- **Table or bullet list** output
- **Optional URL cleanup** — remove `http(s)://` and `www.` from link labels
- **Block styles** — Default and Stripes table style
- **Smart titles** — uses link text, alt text, captions, or filenames where available
- **Table anchors** — table entries link to anchors in the post content
- **Caching** — output is cached and invalidated on save
- **i18n ready** — English strings with German translation included

## Installation

1. Download or clone this repository into `wp-content/plugins/wp-list-of-sources/`
2. Activate **WP List of Sources** in the WordPress admin under Plugins
3. In the block editor, insert the **List of Sources** block

## Usage

1. Add the **List of Sources** block to your post or page
2. In the sidebar under **Source Settings**, pick the source type:
   - **Links** — external and internal hyperlinks (`<a href>`), excluding file downloads
   - **Images** — embedded images (`<img src>`)
   - **Tables** — tables in the post, with jump links to their position
   - **Files** — download links (PDF, DOC, DOCX, XLS, ZIP, etc.)
3. Under **Display**, choose table or bullet list and URL label options

To show more than one source type, add **multiple blocks** — one per type.

### Editor preview

The block preview updates when you:

- first insert the block
- change block settings (source type, format, etc.)
- **save** the post (manual save, not autosave)

It does not refresh on every keystroke while editing content.

### How content is categorized

| Content in post | Shown in |
|-----------------|----------|
| Image block (Insert from URL) | **Images** |
| Text link to a URL | **Links** |
| Link to PDF / DOC / ZIP | **Files** |
| Table block | **Tables** |
| Linked image (`<a><img></a>`) | **Images** and possibly **Links** |

## File structure

```
wp-list-of-sources/
├── wp-list-of-sources.php   # Block registration, data collectors, rendering
├── blocks.js                # Gutenberg block editor UI
├── languages/
│   ├── wp-list-of-sources.pot
│   ├── wp-list-of-sources-de_DE.po
│   └── wp-list-of-sources-de_DE-*.json
└── README.md
```

## Development

No build step required. Edit `blocks.js` and `wp-list-of-sources.php` directly.

After changing translatable strings, regenerate the `.pot` file and update the `.po` / `.json` files in `languages/`.

## License

This plugin is open-source software licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).
