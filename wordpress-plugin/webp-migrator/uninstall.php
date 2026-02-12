<?php
/**
 * Uninstall WebP Migrator.
 *
 * @package WP_WebP_Migrator
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('wim_settings');
delete_option('wim_backup_dir');
delete_option('wim_last_run');
