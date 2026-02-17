<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('whm_last_report');
delete_option('whm_settings');
delete_option('whm_frontend_metrics');

wp_clear_scheduled_hook('whm_weekly_scan');
