<?php
/**
 * Plugin Name: WebP Health Monitor
 * Description: Audit product image delivery, detect ghost image format issues, and correlate TTFB with LCP.
 * Version: 0.1.0
 * Author: ConvertImagesToWebP
 * License: GPL-2.0-or-later
 * Text Domain: webp-health-monitor
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WHM_PLUGIN_FILE', __FILE__);
define('WHM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WHM_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once WHM_PLUGIN_DIR . 'includes/class-whm-monitor.php';

register_activation_hook(WHM_PLUGIN_FILE, array('WHM_Monitor', 'activate'));
register_deactivation_hook(WHM_PLUGIN_FILE, array('WHM_Monitor', 'deactivate'));

WHM_Monitor::instance();
