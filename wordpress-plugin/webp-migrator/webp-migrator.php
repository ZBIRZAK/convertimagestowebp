<?php
/**
 * Plugin Name: WebP Migrator
 * Description: Back up uploads, convert JPG/PNG media to WebP, and update references from an admin dashboard.
 * Version: 1.2.0
 * Author: ConvertImagesToWebP
 * License: GPL-2.0-or-later
 * Text Domain: webp-migrator
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WIM_PLUGIN_FILE', __FILE__);
define('WIM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WIM_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once WIM_PLUGIN_DIR . 'includes/class-wim-webp-migrator.php';

WIM_WebP_Migrator::instance();
