=== WebP Migrator ===
Contributors: convertimagestowebp
Tags: webp, images, media, optimization, performance
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Back up uploads, convert JPG/PNG media to WebP, and restore originals from a dashboard.

== Description ==
WebP Migrator gives site owners a safe workflow to modernize image delivery:

1. Scan media library status (convertible, converted, pending, failed).
2. Back up source files under `wp-content/uploads/wim-backups/...`.
3. Convert JPG/PNG (including generated sizes) to WebP without deleting originals.
4. Optionally replace URLs in post content.
5. Restore original files when needed.
6. Retry failed conversion items from the dashboard.

The plugin is designed for production safety: source files remain available so legacy URLs do not break.

== Installation ==
1. Download `webp-migrator-1.2.0.zip`.
2. In WordPress admin, go to Plugins > Add New Plugin > Upload Plugin.
3. Select the zip file and click Install Now.
4. Click Activate Plugin.
5. Open WebP Migrator in the admin menu.
6. Run Scan library, then Convert pending images in small batches.

== Frequently Asked Questions ==
= Will this delete my original JPG/PNG files? =
No. The plugin keeps source files and generates WebP copies.

= What if conversion fails for some images? =
Failed images are tracked in the dashboard. Use Retry failed images after fixing server support/settings.

= Can I revert everything? =
Yes. Use Restore originals to revert from plugin backups.

= What server support is required? =
Imagick with WebP support or GD with WebP support.

== Changelog ==
= 1.2.0 =
* Added server capability checks (Imagick/GD WebP).
* Added failed count, retry failed action, and last-run status.
* Improved admin dashboard diagnostics and logging.
* Added WordPress.org compatibility files and language folder.

= 1.1.0 =
* Changed conversion flow to keep source files.
* Improved content URL replacement and restore behavior.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==
= 1.2.0 =
Recommended update. Adds stronger diagnostics, retry tools, and WordPress.org packaging compatibility.

== Notes ==
- The plugin requires Imagick or GD with WebP support.
- Conversion and restore process in batches to avoid timeouts.
- Always test on staging before production.

