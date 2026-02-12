<?php

if (!defined('ABSPATH')) {
    exit;
}

class WIM_WebP_Migrator
{
    const VERSION = '1.2.0';
    const OPTION_KEY = 'wim_settings';
    const OPTION_BACKUP_DIR = 'wim_backup_dir';
    const OPTION_LAST_RUN = 'wim_last_run';

    private static $instance = null;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_wim_scan', array($this, 'ajax_scan'));
        add_action('wp_ajax_wim_convert_batch', array($this, 'ajax_convert_batch'));
        add_action('wp_ajax_wim_restore_batch', array($this, 'ajax_restore_batch'));
        add_action('wp_ajax_wim_reset_failed', array($this, 'ajax_reset_failed'));
        add_filter('wp_get_attachment_url', array($this, 'filter_attachment_url'), 10, 2);
        add_filter('wp_get_attachment_image_src', array($this, 'filter_attachment_image_src'), 10, 4);
        add_filter('wp_calculate_image_srcset', array($this, 'filter_attachment_srcset'), 10, 5);
    }

    public function register_menu()
    {
        add_menu_page('WebP Migrator', 'WebP Migrator', 'manage_options', 'wim-webp-migrator', array($this, 'render_admin_page'), 'dashicons-format-image');
    }

    public function register_settings()
    {
        register_setting(self::OPTION_KEY, self::OPTION_KEY, array($this, 'sanitize_settings'));
    }

    public function sanitize_settings($input)
    {
        $defaults = $this->get_settings();
        return array(
            'quality' => isset($input['quality']) ? max(1, min(100, absint($input['quality']))) : $defaults['quality'],
            'batch_size' => isset($input['batch_size']) ? max(1, min(200, absint($input['batch_size']))) : $defaults['batch_size'],
            'replace_content_urls' => isset($input['replace_content_urls']) ? 1 : 0,
        );
    }

    private function get_settings()
    {
        return wp_parse_args(get_option(self::OPTION_KEY, array()), array(
            'quality' => 82,
            'batch_size' => 20,
            'replace_content_urls' => 1,
        ));
    }

    public function enqueue_assets($hook_suffix)
    {
        if ('toplevel_page_wim-webp-migrator' !== $hook_suffix) {
            return;
        }
        wp_enqueue_style('wim-admin-style', WIM_PLUGIN_URL . 'assets/admin.css', array(), self::VERSION);
        wp_enqueue_script('wim-admin-script', WIM_PLUGIN_URL . 'assets/admin.js', array(), self::VERSION, true);
        wp_localize_script('wim-admin-script', 'wimData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wim_nonce'),
            'hasWebpSupport' => $this->has_webp_support() ? 1 : 0,
        ));
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $settings = $this->get_settings();
        $stats = $this->get_stats();
        $engine = $this->get_engine_status();
        $has_webp = $this->has_webp_support();
        ?>
        <div class="wrap wim-wrap">
            <h1>WebP Migrator</h1>
            <p>Back up originals, convert to WebP, and restore safely.</p>
            <?php if (!$has_webp) : ?><div class="notice notice-error inline"><p><strong>WebP support is missing.</strong> Install Imagick or GD WebP support first.</p></div><?php endif; ?>
            <div class="wim-grid">
                <div class="wim-card">
                    <h2>Library Status</h2>
                    <div class="wim-stats">
                        <div><strong id="wim-total"><?php echo esc_html($stats['total_convertible']); ?></strong><span>Convertible</span></div>
                        <div><strong id="wim-converted"><?php echo esc_html($stats['converted']); ?></strong><span>Converted</span></div>
                        <div><strong id="wim-pending"><?php echo esc_html($stats['pending']); ?></strong><span>Pending</span></div>
                        <div><strong id="wim-failed"><?php echo esc_html($stats['failed']); ?></strong><span>Failed</span></div>
                    </div>
                    <p><strong>Backup folder:</strong> <code id="wim-backup-dir"><?php echo esc_html($stats['backup_dir']); ?></code></p>
                    <p><strong>Last run:</strong> <span id="wim-last-run"><?php echo esc_html($stats['last_run']); ?></span></p>
                    <div class="wim-actions">
                        <button type="button" class="button" id="wim-scan-btn">Scan library</button>
                        <button type="button" class="button button-primary" id="wim-convert-btn" <?php disabled(!$has_webp); ?>>Convert pending images</button>
                        <button type="button" class="button" id="wim-restore-btn">Restore originals</button>
                        <button type="button" class="button" id="wim-retry-failed-btn">Retry failed images</button>
                    </div>
                    <div id="wim-log" class="wim-log"></div>
                </div>
                <div class="wim-card">
                    <h2>System Check</h2>
                    <div class="wim-system-grid">
                        <div><span>Imagick</span><strong><?php echo !empty($engine['imagick']) ? 'Available' : 'Missing'; ?></strong></div>
                        <div><span>Imagick WebP</span><strong><?php echo !empty($engine['imagick_webp']) ? 'Supported' : 'Unavailable'; ?></strong></div>
                        <div><span>GD</span><strong><?php echo !empty($engine['gd']) ? 'Available' : 'Missing'; ?></strong></div>
                        <div><span>GD WebP</span><strong><?php echo !empty($engine['gd_webp']) ? 'Supported' : 'Unavailable'; ?></strong></div>
                    </div>
                    <hr />
                    <h2>Settings</h2>
                    <form method="post" action="options.php"><?php settings_fields(self::OPTION_KEY); ?><table class="form-table"><tr><th scope="row"><label for="wim-quality">WebP quality</label></th><td><input id="wim-quality" name="<?php echo esc_attr(self::OPTION_KEY); ?>[quality]" type="number" min="1" max="100" value="<?php echo esc_attr($settings['quality']); ?>" /></td></tr><tr><th scope="row"><label for="wim-batch-size">Batch size</label></th><td><input id="wim-batch-size" name="<?php echo esc_attr(self::OPTION_KEY); ?>[batch_size]" type="number" min="1" max="200" value="<?php echo esc_attr($settings['batch_size']); ?>" /></td></tr><tr><th scope="row">Replace URLs</th><td><label><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[replace_content_urls]" type="checkbox" value="1" <?php checked(1, (int) $settings['replace_content_urls']); ?> /> Replace URLs in post content.</label></td></tr></table><?php submit_button('Save settings'); ?></form>
                    <hr />
                    <h2>Plugin and Website</h2>
                    <p>
                        <a class="button button-secondary" href="https://www.convertimagestowebp.com/" target="_blank" rel="noopener noreferrer">Visit convertimagestowebp.com</a>
                        <a class="button button-secondary" href="https://www.convertimagestowebp.com/guides/wordpress-webp-plugin.html?utm_source=wp-plugin&utm_medium=admin&utm_campaign=wp-webp-migrator" target="_blank" rel="noopener noreferrer">Open Plugin Guide</a>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_scan()
    {
        $this->assert_ajax_permission();
        wp_send_json_success($this->get_stats());
    }

    public function ajax_convert_batch()
    {
        $this->assert_ajax_permission();
        if (!$this->has_webp_support()) {
            wp_send_json_error(array('message' => 'WebP support not available on this server.'), 400);
        }
        $settings = $this->get_settings();
        $batch_size = max(1, min(200, (int) $settings['batch_size']));
        $attachment_ids = $this->get_pending_attachment_ids($batch_size);
        $converted = 0; $failed = 0;
        foreach ($attachment_ids as $attachment_id) {
            if ($this->convert_attachment((int) $attachment_id, $settings)) {
                $converted++;
            } else {
                update_post_meta((int) $attachment_id, '_wim_conversion_failed', current_time('mysql'));
                $failed++;
            }
        }
        $this->update_last_run('convert', $converted, $failed);
        wp_send_json_success(array('converted_in_batch' => $converted, 'failed_in_batch' => $failed, 'completed' => empty($attachment_ids), 'stats' => $this->get_stats()));
    }

    public function ajax_restore_batch()
    {
        $this->assert_ajax_permission();
        $settings = $this->get_settings();
        $batch_size = max(1, min(200, (int) $settings['batch_size']));
        $attachment_ids = $this->get_converted_attachment_ids($batch_size);
        $restored = 0; $failed = 0;
        foreach ($attachment_ids as $attachment_id) {
            if ($this->restore_attachment((int) $attachment_id, $settings)) {
                $restored++;
            } else {
                $failed++;
            }
        }
        $this->update_last_run('restore', $restored, $failed);
        wp_send_json_success(array('restored_in_batch' => $restored, 'failed_in_batch' => $failed, 'completed' => empty($attachment_ids), 'stats' => $this->get_stats()));
    }

    public function ajax_reset_failed()
    {
        $this->assert_ajax_permission();
        $reset = 0;
        foreach ($this->get_failed_attachment_ids() as $attachment_id) {
            delete_post_meta((int) $attachment_id, '_wim_conversion_failed');
            delete_post_meta((int) $attachment_id, '_wim_last_error');
            $reset++;
        }
        $this->update_last_run('retry_failed_reset', $reset, 0);
        wp_send_json_success(array('reset' => $reset, 'stats' => $this->get_stats()));
    }

    private function assert_ajax_permission()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }
        check_ajax_referer('wim_nonce', 'nonce');
    }

    private function get_stats()
    {
        $convertible = new WP_Query(array('post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => array('image/jpeg', 'image/png'), 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => false));
        $total = (int) $convertible->found_posts;
        $done = count($this->get_converted_attachment_ids());
        $bad = count($this->get_failed_attachment_ids());
        $pending = max(0, $total - $done - $bad);
        return array(
            'total_convertible' => $total,
            'converted' => $done,
            'pending' => $pending,
            'failed' => $bad,
            'backup_dir' => get_option(self::OPTION_BACKUP_DIR, '') ?: 'Not created yet',
            'last_run' => $this->format_last_run(get_option(self::OPTION_LAST_RUN, array())),
        );
    }

    private function update_last_run($action, $success_count, $failed_count)
    {
        update_option(self::OPTION_LAST_RUN, array(
            'action' => (string) $action,
            'success' => (int) $success_count,
            'failed' => (int) $failed_count,
            'timestamp' => current_time('mysql'),
        ), false);
    }

    private function format_last_run($last_run)
    {
        if (!is_array($last_run) || empty($last_run['timestamp'])) {
            return 'No run yet';
        }
        return sprintf(
            '%s | action: %s | success: %d | failed: %d',
            sanitize_text_field($last_run['timestamp']),
            sanitize_text_field(isset($last_run['action']) ? $last_run['action'] : 'unknown'),
            isset($last_run['success']) ? (int) $last_run['success'] : 0,
            isset($last_run['failed']) ? (int) $last_run['failed'] : 0
        );
    }

    private function has_webp_support()
    {
        $status = $this->get_engine_status();
        return !empty($status['imagick_webp']) || !empty($status['gd_webp']);
    }

    private function get_engine_status()
    {
        $status = array('imagick' => false, 'imagick_webp' => false, 'gd' => false, 'gd_webp' => false);
        if (class_exists('Imagick')) {
            $status['imagick'] = true;
            try {
                $probe = new Imagick();
                $formats = $probe->queryFormats('WEBP');
                $status['imagick_webp'] = !empty($formats);
            } catch (Exception $e) {
                $status['imagick_webp'] = false;
            }
        }
        if (function_exists('gd_info')) {
            $status['gd'] = true;
            $gd = gd_info();
            $status['gd_webp'] = !empty($gd['WebP Support']) || !empty($gd['WEBP Support']);
        }
        return $status;
    }

    private function convert_attachment($attachment_id, $settings)
    {
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return false;
        }
        $basedir = wp_normalize_path($uploads['basedir']);
        $baseurl = isset($uploads['baseurl']) ? untrailingslashit($uploads['baseurl']) : '';
        $quality = max(1, min(100, (int) $settings['quality']));
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!is_array($metadata) || empty($metadata['file'])) {
            update_post_meta($attachment_id, '_wim_last_error', 'Attachment metadata not found.');
            return false;
        }

        $original_path = get_attached_file($attachment_id);
        if (!$original_path || !file_exists($original_path)) {
            update_post_meta($attachment_id, '_wim_last_error', 'Source file missing.');
            return false;
        }
        $targets = $this->build_target_list($metadata, $original_path);
        $backup_root = $this->ensure_backup_root($basedir);
        if (!$backup_root || empty($targets)) {
            return false;
        }

        $backup_map = array(); $relative_map = array(); $url_map = array();
        foreach ($targets as $target) {
            $source_abs = $target['abs']; $source_rel = $target['rel'];
            if (!file_exists($source_abs) || !$this->is_supported_source($source_abs)) {
                continue;
            }
            $backup_rel = $this->backup_file($source_abs, $basedir, $backup_root);
            if (!$backup_rel) {
                continue;
            }
            $dest_abs = preg_replace('/\.(jpe?g|png)$/i', '.webp', $source_abs);
            $dest_rel = preg_replace('/\.(jpe?g|png)$/i', '.webp', $source_rel);
            if (!$dest_abs || !$dest_rel) {
                continue;
            }
            if (!$this->convert_file_to_webp($source_abs, $dest_abs, $quality) || !file_exists($dest_abs)) {
                continue;
            }
            $backup_map[] = array('source_rel' => $source_rel, 'backup_rel' => $backup_rel, 'webp_rel' => $dest_rel);
            $relative_map[$source_rel] = $dest_rel;
            if ($baseurl) {
                $url_map[$this->rel_to_upload_url($baseurl, $source_rel)] = $this->rel_to_upload_url($baseurl, $dest_rel);
            }
        }

        if (empty($relative_map)) {
            update_post_meta($attachment_id, '_wim_last_error', 'No files converted.');
            return false;
        }
        if (!empty($settings['replace_content_urls']) && !empty($url_map)) {
            $this->replace_url_map_in_posts($url_map);
        }

        update_post_meta($attachment_id, '_wim_converted', 1);
        delete_post_meta($attachment_id, '_wim_conversion_failed');
        delete_post_meta($attachment_id, '_wim_last_error');
        update_post_meta($attachment_id, '_wim_backup_map', $backup_map);
        update_post_meta($attachment_id, '_wim_relative_map', $relative_map);
        update_post_meta($attachment_id, '_wim_url_map', $url_map);
        return true;
    }

    private function restore_attachment($attachment_id, $settings)
    {
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return false;
        }

        $basedir = wp_normalize_path($uploads['basedir']);
        $baseurl = isset($uploads['baseurl']) ? untrailingslashit($uploads['baseurl']) : '';
        $backup_map = get_post_meta($attachment_id, '_wim_backup_map', true);
        $url_map = get_post_meta($attachment_id, '_wim_url_map', true);

        if (!is_array($backup_map) || empty($backup_map)) {
            return false;
        }

        foreach ($backup_map as $item) {
            if (empty($item['source_rel']) || empty($item['backup_rel'])) {
                continue;
            }
            $source_abs = $basedir . '/' . ltrim(wp_normalize_path($item['source_rel']), '/');
            $backup_abs = $basedir . '/' . ltrim(wp_normalize_path($item['backup_rel']), '/');
            if (!file_exists($backup_abs)) {
                continue;
            }
            wp_mkdir_p(dirname($source_abs));
            copy($backup_abs, $source_abs);
            if (!empty($item['webp_rel'])) {
                $webp_abs = $basedir . '/' . ltrim(wp_normalize_path($item['webp_rel']), '/');
                if (file_exists($webp_abs)) {
                    wp_delete_file($webp_abs);
                }
            }
        }

        if (!empty($settings['replace_content_urls'])) {
            if (!is_array($url_map) || empty($url_map)) {
                $url_map = $this->build_url_map_from_backup_map($backup_map, $baseurl);
            }
            if (is_array($url_map) && !empty($url_map)) {
                $this->replace_url_map_in_posts(array_flip($url_map));
            }
        }

        delete_post_meta($attachment_id, '_wim_converted');
        delete_post_meta($attachment_id, '_wim_backup_map');
        delete_post_meta($attachment_id, '_wim_relative_map');
        delete_post_meta($attachment_id, '_wim_url_map');
        delete_post_meta($attachment_id, '_wim_conversion_failed');
        delete_post_meta($attachment_id, '_wim_last_error');
        return true;
    }

    private function build_target_list($metadata, $original_path)
    {
        $targets = array();
        $main_rel = wp_normalize_path($metadata['file']);
        $main_abs = wp_normalize_path($original_path);
        $targets[] = array('rel' => ltrim($main_rel, '/'), 'abs' => $main_abs);
        if (empty($metadata['sizes']) || !is_array($metadata['sizes'])) {
            return $targets;
        }

        $main_dir_abs = wp_normalize_path(dirname($main_abs));
        $main_dir_rel = dirname($main_rel);
        $main_dir_rel = '.' === $main_dir_rel ? '' : trailingslashit($main_dir_rel);
        foreach ($metadata['sizes'] as $size) {
            if (empty($size['file'])) {
                continue;
            }
            $rel = wp_normalize_path($main_dir_rel . $size['file']);
            $abs = wp_normalize_path($main_dir_abs . '/' . $size['file']);
            $targets[] = array('rel' => ltrim($rel, '/'), 'abs' => $abs);
        }
        return $targets;
    }

    private function ensure_backup_root($basedir)
    {
        $existing = get_option(self::OPTION_BACKUP_DIR, '');
        if ($existing) {
            return wp_normalize_path($existing);
        }
        $backup_root = wp_normalize_path($basedir . '/wim-backups/' . gmdate('Ymd-His'));
        wp_mkdir_p($backup_root);
        update_option(self::OPTION_BACKUP_DIR, $backup_root, false);
        return $backup_root;
    }

    private function backup_file($source_abs, $basedir, $backup_root)
    {
        $source_abs = wp_normalize_path($source_abs);
        $basedir = wp_normalize_path($basedir);
        $backup_root = wp_normalize_path($backup_root);
        $source_rel = ltrim(str_replace($basedir, '', $source_abs), '/');
        if (!$source_rel) {
            return false;
        }
        $backup_abs = $backup_root . '/' . $source_rel;
        wp_mkdir_p(dirname($backup_abs));
        if (!file_exists($backup_abs) && !copy($source_abs, $backup_abs)) {
            return false;
        }
        return ltrim(str_replace($basedir, '', $backup_abs), '/');
    }

    private function is_supported_source($file_abs)
    {
        return (bool) preg_match('/\.(jpe?g|png)$/i', $file_abs);
    }

    private function convert_file_to_webp($source_abs, $dest_abs, $quality)
    {
        if (class_exists('Imagick')) {
            try {
                $image = new Imagick($source_abs);
                $image->setImageFormat('webp');
                $image->setImageCompressionQuality($quality);
                $ok = $image->writeImage($dest_abs);
                $image->clear();
                $image->destroy();
                if ($ok) {
                    return true;
                }
            } catch (Exception $e) {
            }
        }
        if (!function_exists('imagewebp')) {
            return false;
        }

        $type = function_exists('exif_imagetype') ? @exif_imagetype($source_abs) : false;
        if (!$type) {
            $ext = strtolower(pathinfo($source_abs, PATHINFO_EXTENSION));
            if (in_array($ext, array('jpg', 'jpeg'), true)) {
                $type = IMAGETYPE_JPEG;
            } elseif ('png' === $ext) {
                $type = IMAGETYPE_PNG;
            }
        }

        if (IMAGETYPE_JPEG === $type && function_exists('imagecreatefromjpeg')) {
            $resource = @imagecreatefromjpeg($source_abs);
        } elseif (IMAGETYPE_PNG === $type && function_exists('imagecreatefrompng')) {
            $resource = @imagecreatefrompng($source_abs);
            if ($resource) {
                if (function_exists('imagepalettetotruecolor')) {
                    imagepalettetotruecolor($resource);
                }
                imagealphablending($resource, true);
                imagesavealpha($resource, true);
            }
        } else {
            return false;
        }
        if (!$resource) {
            return false;
        }
        $ok = imagewebp($resource, $dest_abs, $quality);
        imagedestroy($resource);
        return (bool) $ok;
    }

    public function filter_attachment_url($url, $attachment_id)
    {
        if (!$url || !$this->is_attachment_converted($attachment_id)) {
            return $url;
        }
        return $this->map_url_to_webp((int) $attachment_id, $url);
    }

    public function filter_attachment_image_src($image, $attachment_id, $size, $icon)
    {
        if (!is_array($image) || empty($image[0]) || !$this->is_attachment_converted($attachment_id)) {
            return $image;
        }
        $image[0] = $this->map_url_to_webp((int) $attachment_id, $image[0]);
        return $image;
    }

    public function filter_attachment_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id)
    {
        if (!is_array($sources) || !$this->is_attachment_converted($attachment_id)) {
            return $sources;
        }
        foreach ($sources as $width => $source) {
            if (!empty($source['url'])) {
                $sources[$width]['url'] = $this->map_url_to_webp((int) $attachment_id, $source['url']);
            }
        }
        return $sources;
    }

    private function replace_urls_in_posts($old_url, $new_url)
    {
        if (!$old_url || !$new_url || $old_url === $new_url) {
            return;
        }

        $query = new WP_Query(array(
            'post_type' => 'any',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            's' => $old_url,
        ));

        foreach ($query->posts as $post_id) {
            $content = get_post_field('post_content', $post_id, 'raw');
            if (!is_string($content) || false === strpos($content, $old_url)) {
                continue;
            }

            $updated_content = str_replace($old_url, $new_url, $content);
            if ($updated_content === $content) {
                continue;
            }

            wp_update_post(array(
                'ID' => (int) $post_id,
                'post_content' => $updated_content,
            ));
        }
    }

    private function get_all_convertible_attachment_ids()
    {
        return get_posts(array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => array('image/jpeg', 'image/png'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
        ));
    }

    private function get_pending_attachment_ids($limit)
    {
        $limit = max(1, (int) $limit);
        $ids = array();
        foreach ($this->get_all_convertible_attachment_ids() as $attachment_id) {
            if (get_post_meta($attachment_id, '_wim_converted', true)) {
                continue;
            }
            if (get_post_meta($attachment_id, '_wim_conversion_failed', true)) {
                continue;
            }
            $ids[] = (int) $attachment_id;
            if (count($ids) >= $limit) {
                break;
            }
        }

        return $ids;
    }

    private function get_converted_attachment_ids($limit = 0)
    {
        $ids = array();
        $max = max(0, (int) $limit);
        foreach ($this->get_all_convertible_attachment_ids() as $attachment_id) {
            if (!get_post_meta($attachment_id, '_wim_converted', true)) {
                continue;
            }
            $ids[] = (int) $attachment_id;
            if ($max > 0 && count($ids) >= $max) {
                break;
            }
        }
        return $ids;
    }

    private function get_failed_attachment_ids($limit = 0)
    {
        $ids = array();
        $max = max(0, (int) $limit);
        foreach ($this->get_all_convertible_attachment_ids() as $attachment_id) {
            if (!get_post_meta($attachment_id, '_wim_conversion_failed', true)) {
                continue;
            }
            $ids[] = (int) $attachment_id;
            if ($max > 0 && count($ids) >= $max) {
                break;
            }
        }
        return $ids;
    }

    private function replace_url_map_in_posts($url_map)
    {
        if (!is_array($url_map) || empty($url_map)) {
            return;
        }

        $search = array();
        $replace = array();
        foreach ($url_map as $old_url => $new_url) {
            if (!is_string($old_url) || !is_string($new_url) || '' === $old_url || $old_url === $new_url) {
                continue;
            }
            $search[] = $old_url;
            $replace[] = $new_url;
        }
        if (empty($search)) {
            return;
        }

        $post_types = get_post_types(array('public' => true), 'names');
        if (empty($post_types)) {
            $post_types = array('post', 'page');
        }

        $paged = 1;
        do {
            $query = new WP_Query(array(
                'post_type' => array_values($post_types),
                'post_status' => 'any',
                'posts_per_page' => 100,
                'paged' => $paged,
                'fields' => 'ids',
                'no_found_rows' => false,
            ));

            foreach ($query->posts as $post_id) {
                $content = get_post_field('post_content', $post_id, 'raw');
                if (!is_string($content) || '' === $content) {
                    continue;
                }
                $updated_content = str_replace($search, $replace, $content);
                if ($updated_content === $content) {
                    continue;
                }
                wp_update_post(array(
                    'ID' => (int) $post_id,
                    'post_content' => $updated_content,
                ));
            }

            $paged++;
        } while ($paged <= (int) $query->max_num_pages);
    }

    private function build_url_map_from_backup_map($backup_map, $baseurl)
    {
        if (!is_array($backup_map) || empty($backup_map) || !$baseurl) {
            return array();
        }

        $map = array();
        foreach ($backup_map as $item) {
            if (empty($item['source_rel']) || empty($item['webp_rel'])) {
                continue;
            }
            $old_url = $this->rel_to_upload_url($baseurl, $item['source_rel']);
            $new_url = $this->rel_to_upload_url($baseurl, $item['webp_rel']);
            $map[$old_url] = $new_url;
        }
        return $map;
    }

    private function is_attachment_converted($attachment_id)
    {
        return (bool) get_post_meta((int) $attachment_id, '_wim_converted', true);
    }

    private function map_url_to_webp($attachment_id, $url)
    {
        if (!is_string($url) || '' === $url) {
            return $url;
        }
        $url_map = get_post_meta($attachment_id, '_wim_url_map', true);
        if (is_array($url_map) && isset($url_map[$url]) && $this->url_points_to_existing_file($url_map[$url])) {
            return $url_map[$url];
        }
        $candidate = preg_replace('/\.(jpe?g|png)(\?.*)?$/i', '.webp$2', $url);
        if (is_string($candidate) && $candidate !== $url && $this->url_points_to_existing_file($candidate)) {
            return $candidate;
        }
        return $url;
    }

    private function rel_to_upload_url($baseurl, $rel_path)
    {
        $baseurl = untrailingslashit((string) $baseurl);
        $rel_path = ltrim(str_replace('\\', '/', (string) $rel_path), '/');
        return $baseurl . '/' . $rel_path;
    }

    private function url_points_to_existing_file($url)
    {
        $uploads = wp_upload_dir();
        if (!empty($uploads['error']) || empty($uploads['baseurl']) || empty($uploads['basedir'])) {
            return false;
        }
        $baseurl = untrailingslashit($uploads['baseurl']);
        $basedir = wp_normalize_path($uploads['basedir']);
        $plain_url = strtok((string) $url, '?');
        if (!is_string($plain_url) || 0 !== strpos($plain_url, $baseurl . '/')) {
            return false;
        }
        $rel = ltrim(substr($plain_url, strlen($baseurl)), '/');
        $file = wp_normalize_path($basedir . '/' . $rel);
        return file_exists($file);
    }
}
