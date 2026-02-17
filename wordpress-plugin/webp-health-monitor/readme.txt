=== WebP Health Monitor ===
Contributors: convertimagestowebp
Tags: webp, performance, lcp, ttfb, woocommerce
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WebP Health Monitor audits WooCommerce product image delivery and highlights ghost image issues.

== Description ==

This plugin focuses on quality assurance after image optimization:

1. Scans product pages and flags legacy JPG/PNG image delivery where WebP variants exist.
2. Flags size/format mismatches where a suitable WebP size exists but JPG/PNG is still requested.
3. Tracks TTFB and LCP samples from logged-in admin browsing sessions.
4. Runs a weekly cron-based scan automatically.

== Installation ==

1. Upload the `webp-health-monitor` folder to `/wp-content/plugins/`.
2. Activate the plugin from the WordPress admin Plugins page.
3. Open Dashboard or Tools > WebP Health Monitor.

== Changelog ==

= 0.1.0 =
* Initial release.
