# Floodlight Alt Text Monitor

**Contributors:** floodlight  
**Requires at least:** 6.0  
**Tested up to:** 6.6  
**Requires PHP:** 7.4  
**Stable tag:** 0.1.0  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

Multisite-ready WordPress plugin for scanning and reporting missing or poor image alt text.

---

## Description

Floodlight Alt Text Monitor is a multisite-ready WordPress plugin that scans for images with missing or poor alt text.  
It provides a simple admin UI with settings, scan controls, and results reporting.

Current features include:

- Media Library scan for image attachments
- Content scan support for ACF Image/Gallery fields (including nested fields)
- Content scan support for ACF WYSIWYG embedded images (`<img>` alt evaluation)
- Results table with severity filtering, source filtering, and search
- Quick Content Scan (dashboard widget) for the last 5 modified posts

This plugin is intended as a foundation for a more full-featured accessibility auditing tool.

---

## Installation

1. Upload the plugin folder to `/wp-content/plugins/floodlight-alt-text-monitor/`
2. Activate the plugin through the Plugins menu
3. (Multisite) Network-activate the plugin to enable Network Settings and network-wide defaults

---

## Usage

1. Navigate to:
   - Site Admin: **Alt Text Monitor**
   - Network Admin: **Alt Text Monitor**

2. Configure rules:
   - Minimum alt length
   - Generic alt word list
   - Missing alt severity
   - Filename detection

3. Run scans:
   - **Media Scan:** scans image attachments in the Media Library
   - **Content Scan:** scans enabled post types and ACF fields for image usage
   - **Quick Content Scan:** runs a content scan against the last 5 modified posts (WordPress Dashboard widget)

4. Review findings:
   - **Alt Text Monitor → Results**
   - Use filters and search to narrow results

---

## Settings

### Network Settings

- Default scan post types
- Default scanning rules

### Site Settings

- Use network defaults (inherit network settings)
- Override scan post types and rules (if network defaults are disabled)

---

## Scanning Notes

### Media Scan

- Evaluates attachment alt text stored in `_wp_attachment_image_alt`.

### Content Scan

- Evaluates image usage within content using ACF field objects.
- For WYSIWYG fields, evaluates inline `<img alt="...">` (front-end truth).
- Attempts to resolve embedded image attachment ID via:
  - `wp-image-{ID}` class
  - `data-id="{ID}"`
  - URL lookup (`attachment_url_to_postid()`)

### Inline alt vs Attachment alt

- WYSIWYG embedded images may have inline alt text that differs from the Media Library attachment alt.
- Results display may show both values when available.

---

## Development

Plugin structure (high level):

- `src/Plugin.php` bootstraps core functionality
- `src/Admin/` admin menu pages, dashboard widget, and actions
- `src/Settings/` network + site settings
- `src/Jobs/` scan job state
- `src/Findings/` scan findings storage
- `src/Scan/` scanning logic (media + content + evaluation)
- `src/Cron/` scheduled tasks (optional / if enabled)

---

## Changelog

### 0.1.0

- Initial multisite-ready boilerplate
- Network/Site Settings pages
- Media scan MVP + results
- Content scan MVP:
  - ACF nested image/gallery scanning
  - ACF WYSIWYG embedded image alt scanning
- Results filtering improvements (severity/source/search)
- WordPress dashboard widget with Quick Content Scan (last 5 modified posts)

---

## License

This plugin is licensed under the GPLv2 (or later).
