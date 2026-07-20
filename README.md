# WordPress Gutenberg Multi-Block Template

A lightweight, clean boilerplate for creating custom WordPress plugins with multiple Gutenberg blocks. Developed with a pure JavaScript workflow (no compilation/NPM required) and designed to bypass common template-editor bugs.

## Included Structure
* **Block 1 (Input-Item):** A radically flat, single-line data entry block visible *only* in the WordPress backend.
* **Block 2 (Display-Block):** A dynamic PHP-rendered block utilizing `<ServerSideRender>` with a bulletproof synchronization fix for the Site Editor and Reusable Templates.
* **i18n Ready:** Fully configured with `wp.i18n` and localized script loading.
* **Compact Backend UI:** Pre-configured minimal inputs without bulky labels, keeping your post editor clean.

## Quick Start Setup (Search & Replace)

When using this template to start a new plugin, run a global **Search & Replace** across all files for the following three unique prefixes:

1. **Text Domain:** 
   * Find: `wp-my-plugin`
   * Replace with: `your-new-plugin-slug` (e.g., `wp-advanced-gallery`)
2. **Block Namespace:** 
   * Find: `wpm/`
   * Replace with: `your-prefix/` (e.g., `wpag/`)
3. **PHP Function Prefix:** 
   * Find: `wpm_`
   * Replace with: `your_prefix_` (e.g., `wpag_`)

## File Renaming
After creating a new repository from this template:
1. Rename `wp-my-plugin.php` to match your new plugin slug (e.g., `wp-advanced-gallery.php`).
2. Update the plugin header comments inside that file (Plugin Name, Description, Author).

## How to use as a GitHub Template
1. Go to this repository's **Settings** on GitHub.
2. Under the "General" tab, check the box **"Template repository"**.
3. Now, you can click the green **"Use this template"** button to instantly spin up a new, clean repository with its own separate Git history.

## License
This template is open-source and licensed under the GPLv2 or later.
