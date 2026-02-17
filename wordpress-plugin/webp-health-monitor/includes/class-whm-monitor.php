<?php

if (!defined('ABSPATH')) {
    exit;
}

class WHM_Monitor
{
    const VERSION = '0.1.0';
    const OPTION_REPORT = 'whm_last_report';
    const OPTION_SETTINGS = 'whm_settings';
    const OPTION_METRICS = 'whm_frontend_metrics';
    const CRON_HOOK = 'whm_weekly_scan';
    const CRON_SCHEDULE = 'whm_weekly';
    const MAX_METRICS = 100;

    private static $instance = null;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate()
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK);
        }
    }

    public static function deactivate()
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    private function __construct()
    {
        add_filter('cron_schedules', array($this, 'register_cron_schedule'));
        add_action('wp_dashboard_setup', array($this, 'register_dashboard_widget'));
        add_action('admin_menu', array($this, 'register_tools_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_whm_run_scan', array($this, 'ajax_run_scan'));
        add_action('wp_ajax_whm_track_frontend_metric', array($this, 'ajax_track_frontend_metric'));
        add_action(self::CRON_HOOK, array($this, 'run_scheduled_scan'));
        add_action('wp_footer', array($this, 'inject_frontend_metric_script'), 100);
    }

    public function register_cron_schedule($schedules)
    {
        if (!isset($schedules[self::CRON_SCHEDULE])) {
            $schedules[self::CRON_SCHEDULE] = array(
                'interval' => WEEK_IN_SECONDS,
                'display' => __('Once Weekly', 'webp-health-monitor'),
            );
        }
        return $schedules;
    }

    public function register_dashboard_widget()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'whm_dashboard_widget',
            __('WebP Health Monitor', 'webp-health-monitor'),
            array($this, 'render_dashboard_widget')
        );
    }

    public function register_tools_page()
    {
        add_management_page(
            __('WebP Health Monitor', 'webp-health-monitor'),
            __('WebP Health Monitor', 'webp-health-monitor'),
            'manage_options',
            'whm-webp-health-monitor',
            array($this, 'render_tools_page')
        );
    }

    public function enqueue_admin_assets($hook_suffix)
    {
        $allowed = array('index.php', 'tools_page_whm-webp-health-monitor');
        if (!in_array($hook_suffix, $allowed, true)) {
            return;
        }

        wp_enqueue_style('whm-admin-style', WHM_PLUGIN_URL . 'assets/admin.css', array(), self::VERSION);
        wp_enqueue_script('whm-admin-script', WHM_PLUGIN_URL . 'assets/admin.js', array(), self::VERSION, true);
        wp_localize_script('whm-admin-script', 'whmData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('whm_admin_nonce'),
            'strings' => array(
                'running' => __('Running scan...', 'webp-health-monitor'),
                'completed' => __('Scan completed.', 'webp-health-monitor'),
                'failed' => __('Scan failed.', 'webp-health-monitor'),
            ),
        ));
    }

    public function render_dashboard_widget()
    {
        $report = $this->get_last_report();
        $metrics = $this->get_metrics_summary();
        ?>
        <div class="whm-widget">
            <?php $this->render_summary_cards($report, $metrics); ?>
            <p>
                <button type="button" class="button button-primary whm-run-scan"><?php esc_html_e('Run Scan Now', 'webp-health-monitor'); ?></button>
                <a class="button" href="<?php echo esc_url(admin_url('tools.php?page=whm-webp-health-monitor')); ?>"><?php esc_html_e('Open Full Report', 'webp-health-monitor'); ?></a>
            </p>
            <div class="whm-ajax-result" aria-live="polite"></div>
        </div>
        <?php
    }

    public function render_tools_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $report = $this->get_last_report();
        $metrics = $this->get_metrics_summary();
        ?>
        <div class="wrap whm-wrap">
            <h1><?php esc_html_e('WebP Health Monitor', 'webp-health-monitor'); ?></h1>
            <p><?php esc_html_e('Audit WooCommerce product pages for image format and size mismatches.', 'webp-health-monitor'); ?></p>

            <p>
                <button type="button" class="button button-primary whm-run-scan"><?php esc_html_e('Run Scan Now', 'webp-health-monitor'); ?></button>
            </p>
            <div class="whm-ajax-result" aria-live="polite"></div>

            <?php $this->render_summary_cards($report, $metrics); ?>
            <?php $this->render_issues_table($report); ?>
        </div>
        <?php
    }

    private function render_summary_cards($report, $metrics)
    {
        $scan_time = !empty($report['scanned_at']) ? $report['scanned_at'] : __('Not scanned yet', 'webp-health-monitor');
        $products = isset($report['scanned_products']) ? (int) $report['scanned_products'] : 0;
        $issues = isset($report['issues_count']) ? (int) $report['issues_count'] : 0;
        $avg_ttfb = isset($report['avg_ttfb_ms']) ? (int) $report['avg_ttfb_ms'] : 0;
        $avg_lcp = isset($metrics['avg_lcp_ms']) ? (int) $metrics['avg_lcp_ms'] : 0;
        $correlation = $this->build_correlation_message($avg_ttfb, $avg_lcp);
        ?>
        <div class="whm-cards">
            <div class="whm-card"><span><?php esc_html_e('Last Scan', 'webp-health-monitor'); ?></span><strong><?php echo esc_html($scan_time); ?></strong></div>
            <div class="whm-card"><span><?php esc_html_e('Products Scanned', 'webp-health-monitor'); ?></span><strong><?php echo esc_html($products); ?></strong></div>
            <div class="whm-card"><span><?php esc_html_e('Ghost Issues', 'webp-health-monitor'); ?></span><strong><?php echo esc_html($issues); ?></strong></div>
            <div class="whm-card"><span><?php esc_html_e('Avg TTFB', 'webp-health-monitor'); ?></span><strong><?php echo esc_html($avg_ttfb); ?>ms</strong></div>
            <div class="whm-card"><span><?php esc_html_e('Avg LCP', 'webp-health-monitor'); ?></span><strong><?php echo esc_html($avg_lcp); ?>ms</strong></div>
        </div>
        <p class="whm-correlation"><strong><?php esc_html_e('TTFB/LCP diagnosis:', 'webp-health-monitor'); ?></strong> <?php echo esc_html($correlation); ?></p>
        <?php
    }

    private function render_issues_table($report)
    {
        $issues = (!empty($report['issues']) && is_array($report['issues'])) ? $report['issues'] : array();
        ?>
        <h2><?php esc_html_e('Detected Issues', 'webp-health-monitor'); ?></h2>
        <?php if (empty($issues)) : ?>
            <p><?php esc_html_e('No issues found in the last scan.', 'webp-health-monitor'); ?></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Type', 'webp-health-monitor'); ?></th>
                        <th><?php esc_html_e('Product', 'webp-health-monitor'); ?></th>
                        <th><?php esc_html_e('Image', 'webp-health-monitor'); ?></th>
                        <th><?php esc_html_e('Details', 'webp-health-monitor'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($issues as $issue) : ?>
                        <tr>
                            <td><?php echo esc_html(isset($issue['type']) ? $issue['type'] : 'unknown'); ?></td>
                            <td>
                                <?php
                                $product_id = isset($issue['product_id']) ? (int) $issue['product_id'] : 0;
                                if ($product_id > 0) {
                                    $edit_url = get_edit_post_link($product_id);
                                    $title = get_the_title($product_id);
                                    if ($edit_url) {
                                        echo '<a href="' . esc_url($edit_url) . '">' . esc_html($title) . '</a>';
                                    } else {
                                        echo esc_html($title);
                                    }
                                } else {
                                    esc_html_e('N/A', 'webp-health-monitor');
                                }
                                ?>
                            </td>
                            <td><code><?php echo esc_html(isset($issue['image_src']) ? $issue['image_src'] : ''); ?></code></td>
                            <td><?php echo esc_html(isset($issue['message']) ? $issue['message'] : ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    }

    public function ajax_run_scan()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'webp-health-monitor')), 403);
        }
        check_ajax_referer('whm_admin_nonce', 'nonce');

        $report = $this->run_scan();
        wp_send_json_success($report);
    }

    public function ajax_track_frontend_metric()
    {
        check_ajax_referer('whm_frontend_metric', 'nonce');
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'webp-health-monitor')), 403);
        }

        $ttfb = isset($_POST['ttfb']) ? (float) wp_unslash($_POST['ttfb']) : 0;
        $lcp = isset($_POST['lcp']) ? (float) wp_unslash($_POST['lcp']) : 0;
        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';

        if ($ttfb <= 0 && $lcp <= 0) {
            wp_send_json_error(array('message' => __('No metric provided.', 'webp-health-monitor')), 400);
        }

        $metrics = get_option(self::OPTION_METRICS, array());
        if (!is_array($metrics)) {
            $metrics = array();
        }
        $metrics[] = array(
            'ttfb' => max(0, (int) round($ttfb)),
            'lcp' => max(0, (int) round($lcp)),
            'url' => $url,
            'timestamp' => current_time('mysql'),
        );
        if (count($metrics) > self::MAX_METRICS) {
            $metrics = array_slice($metrics, -1 * self::MAX_METRICS);
        }
        update_option(self::OPTION_METRICS, $metrics, false);

        wp_send_json_success();
    }

    public function run_scheduled_scan()
    {
        $this->run_scan();
    }

    private function run_scan()
    {
        $settings = $this->get_settings();
        $limit = max(1, (int) $settings['scan_limit']);
        $product_ids = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        ));

        $report = array(
            'scanned_at' => current_time('mysql'),
            'scanned_products' => 0,
            'issues_count' => 0,
            'issues' => array(),
            'avg_ttfb_ms' => 0,
            'notes' => array(),
        );

        if (!post_type_exists('product')) {
            $report['notes'][] = __('WooCommerce product post type not found.', 'webp-health-monitor');
            update_option(self::OPTION_REPORT, $report, false);
            return $report;
        }

        $issues = array();
        $ttfb_values = array();

        foreach ($product_ids as $product_id) {
            $url = get_permalink($product_id);
            if (!$url) {
                continue;
            }

            $request = $this->fetch_page_with_timing($url);
            if (!empty($request['ttfb'])) {
                $ttfb_values[] = (int) $request['ttfb'];
            }
            if (!empty($request['error'])) {
                $issues[] = array(
                    'type' => 'page_fetch_error',
                    'product_id' => (int) $product_id,
                    'image_src' => '',
                    'message' => $request['error'],
                );
                continue;
            }

            $images = $this->extract_image_candidates($request['html']);
            foreach ($images as $candidate) {
                $candidate_issues = $this->inspect_candidate($candidate, (int) $product_id);
                if (!empty($candidate_issues)) {
                    $issues = array_merge($issues, $candidate_issues);
                }
            }
        }

        $issues = $this->dedupe_issues($issues);
        $report['scanned_products'] = count($product_ids);
        $report['issues_count'] = count($issues);
        $report['issues'] = array_slice($issues, 0, 200);
        $report['avg_ttfb_ms'] = !empty($ttfb_values) ? (int) round(array_sum($ttfb_values) / count($ttfb_values)) : 0;

        update_option(self::OPTION_REPORT, $report, false);
        return $report;
    }

    private function fetch_page_with_timing($url)
    {
        $start = microtime(true);
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'redirection' => 2,
            'headers' => array(
                'Cache-Control' => 'no-cache',
            ),
        ));
        $elapsed_ms = (int) round((microtime(true) - $start) * 1000);

        if (is_wp_error($response)) {
            return array(
                'ttfb' => $elapsed_ms,
                'error' => $response->get_error_message(),
                'html' => '',
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 400) {
            return array(
                'ttfb' => $elapsed_ms,
                'error' => sprintf(__('HTTP error %d', 'webp-health-monitor'), (int) $code),
                'html' => '',
            );
        }

        return array(
            'ttfb' => $elapsed_ms,
            'error' => '',
            'html' => wp_remote_retrieve_body($response),
        );
    }

    private function extract_image_candidates($html)
    {
        if (!is_string($html) || '' === trim($html)) {
            return array();
        }

        $images = array();
        if (!class_exists('DOMDocument')) {
            return $images;
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $nodes = $dom->getElementsByTagName('img');
        foreach ($nodes as $node) {
            $src = trim((string) $node->getAttribute('src'));
            if ('' === $src) {
                continue;
            }
            $images[] = array(
                'src' => $src,
                'srcset' => trim((string) $node->getAttribute('srcset')),
                'width' => (int) $node->getAttribute('width'),
            );
        }
        libxml_clear_errors();

        return $images;
    }

    private function inspect_candidate($candidate, $product_id)
    {
        $src = isset($candidate['src']) ? (string) $candidate['src'] : '';
        if (!$src || preg_match('/^data:/i', $src)) {
            return array();
        }

        $issues = array();
        $src_path = $this->local_path_from_upload_url($src);
        if (!$src_path) {
            return array();
        }

        $src_ext = strtolower((string) pathinfo($src_path, PATHINFO_EXTENSION));
        $width_hint = isset($candidate['width']) ? (int) $candidate['width'] : 0;
        $requested_widths = $this->extract_srcset_widths(isset($candidate['srcset']) ? $candidate['srcset'] : '');
        if ($width_hint > 0) {
            $requested_widths[] = $width_hint;
        }
        $requested_width = !empty($requested_widths) ? min($requested_widths) : $this->extract_width_from_filename($src_path);

        if (in_array($src_ext, array('jpg', 'jpeg', 'png'), true) && $this->has_direct_webp_variant($src_path)) {
            $issues[] = array(
                'type' => 'legacy_format_webp_exists',
                'product_id' => (int) $product_id,
                'image_src' => $src,
                'message' => __('JPG/PNG is used while a WebP variant exists.', 'webp-health-monitor'),
            );
        }

        $webp_widths = $this->available_webp_widths($src_path);
        if (!empty($webp_widths) && $requested_width > 0 && in_array($src_ext, array('jpg', 'jpeg', 'png'), true)) {
            $matching = array_filter($webp_widths, function ($w) use ($requested_width) {
                return (int) $w >= (int) $requested_width;
            });
            if (!empty($matching)) {
                $issues[] = array(
                    'type' => 'format_size_mismatch',
                    'product_id' => (int) $product_id,
                    'image_src' => $src,
                    'message' => sprintf(
                        __('Requested width ~%dpx is served as %s while suitable WebP sizes are available.', 'webp-health-monitor'),
                        (int) $requested_width,
                        strtoupper($src_ext)
                    ),
                );
            }
        }

        return $issues;
    }

    private function local_path_from_upload_url($url)
    {
        $uploads = wp_upload_dir();
        if (!empty($uploads['error']) || empty($uploads['baseurl']) || empty($uploads['basedir'])) {
            return '';
        }

        $plain_url = strtok((string) $url, '?');
        $baseurl = untrailingslashit((string) $uploads['baseurl']);
        $basedir = wp_normalize_path((string) $uploads['basedir']);

        if (0 !== strpos($plain_url, $baseurl . '/')) {
            return '';
        }

        $relative = ltrim(substr($plain_url, strlen($baseurl)), '/');
        return wp_normalize_path($basedir . '/' . $relative);
    }

    private function has_direct_webp_variant($path)
    {
        $candidate = preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);
        return is_string($candidate) && $candidate !== $path && file_exists($candidate);
    }

    private function available_webp_widths($path)
    {
        $path = wp_normalize_path($path);
        $dir = dirname($path);
        $name = pathinfo($path, PATHINFO_FILENAME);

        $candidates = glob($dir . '/' . $name . '*.webp');
        if (!is_array($candidates) || empty($candidates)) {
            return array();
        }

        $widths = array();
        foreach ($candidates as $candidate) {
            $width = $this->extract_width_from_filename($candidate);
            if ($width > 0) {
                $widths[] = $width;
            }
        }

        $widths = array_unique(array_map('intval', $widths));
        sort($widths);
        return $widths;
    }

    private function extract_width_from_filename($path)
    {
        if (preg_match('/-(\d+)x(\d+)\./', (string) basename($path), $matches)) {
            return (int) $matches[1];
        }
        return 0;
    }

    private function extract_srcset_widths($srcset)
    {
        if (!is_string($srcset) || '' === trim($srcset)) {
            return array();
        }

        $widths = array();
        $parts = explode(',', $srcset);
        foreach ($parts as $part) {
            if (preg_match('/\s(\d+)w\s*$/', trim($part), $matches)) {
                $widths[] = (int) $matches[1];
            }
        }

        return $widths;
    }

    private function dedupe_issues($issues)
    {
        $unique = array();
        $seen = array();
        foreach ($issues as $issue) {
            $key = implode('|', array(
                isset($issue['type']) ? $issue['type'] : '',
                isset($issue['product_id']) ? (int) $issue['product_id'] : 0,
                isset($issue['image_src']) ? (string) $issue['image_src'] : '',
            ));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $issue;
        }
        return $unique;
    }

    private function get_last_report()
    {
        $report = get_option(self::OPTION_REPORT, array());
        return is_array($report) ? $report : array();
    }

    private function get_settings()
    {
        $saved = get_option(self::OPTION_SETTINGS, array());
        return wp_parse_args(is_array($saved) ? $saved : array(), array(
            'scan_limit' => 40,
        ));
    }

    private function get_metrics_summary()
    {
        $metrics = get_option(self::OPTION_METRICS, array());
        if (!is_array($metrics) || empty($metrics)) {
            return array(
                'count' => 0,
                'avg_ttfb_ms' => 0,
                'avg_lcp_ms' => 0,
            );
        }

        $ttfb = array();
        $lcp = array();
        foreach ($metrics as $entry) {
            $entry_ttfb = isset($entry['ttfb']) ? (int) $entry['ttfb'] : 0;
            $entry_lcp = isset($entry['lcp']) ? (int) $entry['lcp'] : 0;
            if ($entry_ttfb > 0) {
                $ttfb[] = $entry_ttfb;
            }
            if ($entry_lcp > 0) {
                $lcp[] = $entry_lcp;
            }
        }

        return array(
            'count' => count($metrics),
            'avg_ttfb_ms' => !empty($ttfb) ? (int) round(array_sum($ttfb) / count($ttfb)) : 0,
            'avg_lcp_ms' => !empty($lcp) ? (int) round(array_sum($lcp) / count($lcp)) : 0,
        );
    }

    private function build_correlation_message($avg_ttfb_ms, $avg_lcp_ms)
    {
        if ($avg_ttfb_ms <= 0 && $avg_lcp_ms <= 0) {
            return __('No frontend field data yet. Visit product pages while logged in to collect metrics.', 'webp-health-monitor');
        }
        if ($avg_ttfb_ms >= 800 && $avg_lcp_ms >= 2500) {
            return __('Both TTFB and LCP are high. Server response delays likely contribute heavily.', 'webp-health-monitor');
        }
        if ($avg_ttfb_ms < 600 && $avg_lcp_ms >= 2500) {
            return __('TTFB is acceptable but LCP is high. Image payload/format issues are likely contributors.', 'webp-health-monitor');
        }
        if ($avg_ttfb_ms >= 800 && $avg_lcp_ms < 2500) {
            return __('Server response is slow but LCP is still acceptable. Backend latency is visible.', 'webp-health-monitor');
        }
        return __('TTFB and LCP are in a reasonable range based on captured samples.', 'webp-health-monitor');
    }

    public function inject_frontend_metric_script()
    {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return;
        }
        ?>
        <script>
        (function () {
            if (!window.performance || !window.fetch) {
                return;
            }
            var nav = performance.getEntriesByType('navigation');
            var ttfb = nav && nav.length ? Math.round(nav[0].responseStart - nav[0].requestStart) : 0;
            var lcp = 0;

            try {
                new PerformanceObserver(function (entryList) {
                    var entries = entryList.getEntries();
                    if (entries.length) {
                        lcp = Math.round(entries[entries.length - 1].startTime);
                    }
                }).observe({ type: 'largest-contentful-paint', buffered: true });
            } catch (e) {
                return;
            }

            function sendMetrics() {
                var data = new URLSearchParams();
                data.append('action', 'whm_track_frontend_metric');
                data.append('nonce', '<?php echo esc_js(wp_create_nonce('whm_frontend_metric')); ?>');
                data.append('ttfb', String(ttfb));
                data.append('lcp', String(lcp));
                data.append('url', window.location.href);

                fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    keepalive: true,
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: data.toString()
                }).catch(function () {});
            }

            window.addEventListener('pagehide', sendMetrics, { once: true });
        })();
        </script>
        <?php
    }
}
