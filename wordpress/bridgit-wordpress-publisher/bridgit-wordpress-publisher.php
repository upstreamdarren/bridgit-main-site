<?php
/**
 * Plugin Name: Bridgit Page Loader
 * Plugin URI: https://bridgit.care/
 * Description: Loads approved Bridgit user-site builds into WordPress while leaving wp-admin, login, REST and existing WordPress content untouched.
 * Version: 1.5.0
 * Author: Bridgit Care
 * Author URI: https://bridgit.care/
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Update URI: https://bridgit.care/bridgit-page-loader/
 * Text Domain: bridgit-wordpress-publisher
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Bridgit_Page_Loader {
    const VERSION = '1.5.0';
    const OPTION_PROFILE = 'bridgit_loader_profile';
    const OPTION_SOURCE = 'bridgit_loader_source';
    const OPTION_TTL = 'bridgit_loader_cache_ttl';
    const CACHE_GROUP = 'bridgit_page_loader_';

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('template_redirect', array($this, 'render_remote_page'), 0);
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_bridgit_loader_clear_cache', array($this, 'clear_cache_action'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'settings_link'));
    }

    public static function profiles() {
        return array(
            'auto' => array('label' => 'Auto-detect from this site domain', 'hosts' => array(), 'routes' => array()),
            'carers' => array('label' => 'Adult carers', 'hosts' => array('carers.bridgit.care'), 'routes' => array('/' => '/user-sites/carers/')),
            'young' => array('label' => 'Young carers', 'hosts' => array('young.bridgit.care'), 'routes' => array('/' => '/user-sites/young/')),
            'employers' => array('label' => 'Employers', 'hosts' => array('employers.bridgit.care'), 'routes' => array('/' => '/user-sites/employers/')),
            'local' => array('label' => 'Local support', 'hosts' => array('local.bridgit.care'), 'routes' => array('/' => '/user-sites/local/')),
            'brum' => array('label' => 'Brum Chat', 'hosts' => array('brum.chat', 'www.brum.chat'), 'routes' => array('/' => '/user-sites/brum/')),
            'next' => array('label' => 'Next / care leavers', 'hosts' => array('next.bridgit.care'), 'routes' => array('/' => '/user-sites/next/')),
            'agewell' => array('label' => 'Age Well', 'hosts' => array('agewell.bridgit.care'), 'routes' => array('/' => '/user-sites/agewell/')),
            'myuklife' => array(
                'label' => 'My UK Life (three pages)',
                'hosts' => array('ai.myuk.life', 'www.ai.myuk.life'),
                'routes' => array(
                    '/' => '/user-sites/myuklife/',
                    '/se/' => '/user-sites/myuklife/se/',
                    '/wm/' => '/user-sites/myuklife/wm/',
                ),
            ),
        );
    }

    public function settings_link($links) {
        array_unshift($links, '<a href="' . esc_url(admin_url('options-general.php?page=bridgit-page-loader')) . '">Settings</a>');
        return $links;
    }

    public function add_settings_page() {
        add_options_page('Bridgit Page Loader', 'Bridgit Page Loader', 'manage_options', 'bridgit-page-loader', array($this, 'settings_page'));
    }

    public function register_settings() {
        register_setting('bridgit_page_loader', self::OPTION_PROFILE, array('sanitize_callback' => array($this, 'sanitize_profile'), 'default' => 'auto'));
        register_setting('bridgit_page_loader', self::OPTION_SOURCE, array('sanitize_callback' => array($this, 'sanitize_source'), 'default' => 'https://bridgit-main-site.pages.dev'));
        register_setting('bridgit_page_loader', self::OPTION_TTL, array('sanitize_callback' => array($this, 'sanitize_ttl'), 'default' => 300));
    }

    public function sanitize_profile($value) {
        return array_key_exists($value, self::profiles()) ? $value : 'auto';
    }

    public function sanitize_source($value) {
        $url = untrailingslashit(esc_url_raw($value));
        return wp_http_validate_url($url) ? $url : 'https://bridgit-main-site.pages.dev';
    }

    public function sanitize_ttl($value) {
        return max(60, min(86400, absint($value)));
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $profiles = self::profiles();
        $selected = get_option(self::OPTION_PROFILE, 'auto');
        $source = get_option(self::OPTION_SOURCE, 'https://bridgit-main-site.pages.dev');
        $ttl = get_option(self::OPTION_TTL, 300);
        $resolved = $this->resolve_profile_key();
        ?>
        <div class="wrap">
            <h1>Bridgit Page Loader</h1>
            <p>Version <?php echo esc_html(self::VERSION); ?> loads only the configured public routes. WordPress administration, login, REST, feeds and every other path continue to use WordPress normally.</p>
            <form method="post" action="options.php">
                <?php settings_fields('bridgit_page_loader'); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><label for="bridgit-loader-profile">Site profile</label></th><td>
                        <select id="bridgit-loader-profile" name="<?php echo esc_attr(self::OPTION_PROFILE); ?>">
                            <?php foreach ($profiles as $key => $profile) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($selected, $key); ?>><?php echo esc_html($profile['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Detected profile: <strong><?php echo esc_html(isset($profiles[$resolved]) ? $profiles[$resolved]['label'] : 'None'); ?></strong></p>
                    </td></tr>
                    <tr><th scope="row"><label for="bridgit-loader-source">Build source</label></th><td><input class="regular-text" type="url" id="bridgit-loader-source" name="<?php echo esc_attr(self::OPTION_SOURCE); ?>" value="<?php echo esc_attr($source); ?>" /></td></tr>
                    <tr><th scope="row"><label for="bridgit-loader-ttl">Cache duration</label></th><td><input type="number" min="60" max="86400" id="bridgit-loader-ttl" name="<?php echo esc_attr(self::OPTION_TTL); ?>" value="<?php echo esc_attr($ttl); ?>" /> seconds</td></tr>
                </table>
                <?php submit_button('Save settings'); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="bridgit_loader_clear_cache" />
                <?php wp_nonce_field('bridgit_loader_clear_cache'); ?>
                <?php submit_button('Clear page cache', 'secondary'); ?>
            </form>
        </div>
        <?php
    }

    public function clear_cache_action() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to clear this cache.');
        }
        check_admin_referer('bridgit_loader_clear_cache');
        $this->clear_known_transients();
        wp_safe_redirect(add_query_arg('bridgit_cache_cleared', '1', admin_url('options-general.php?page=bridgit-page-loader')));
        exit;
    }

    private function clear_known_transients() {
        foreach (self::profiles() as $profile) {
            foreach ($profile['routes'] as $remote_path) {
                delete_transient(self::CACHE_GROUP . md5($remote_path));
                delete_transient(self::CACHE_GROUP . 'stale_' . md5($remote_path));
            }
        }
    }

    private function resolve_profile_key() {
        $selected = get_option(self::OPTION_PROFILE, 'auto');
        if ('auto' !== $selected && isset(self::profiles()[$selected])) {
            return $selected;
        }
        $host = strtolower(preg_replace('/:\d+$/', '', isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : ''));
        foreach (self::profiles() as $key => $profile) {
            if (in_array($host, $profile['hosts'], true)) {
                return $key;
            }
        }
        return '';
    }

    private function request_path() {
        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $path = wp_parse_url($uri, PHP_URL_PATH);
        $path = '/' . ltrim((string) $path, '/');
        return '/' === $path ? '/' : trailingslashit($path);
    }

    private function should_skip() {
        return is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_trackback() || is_robots() || is_favicon() || (defined('REST_REQUEST') && REST_REQUEST);
    }

    public function render_remote_page() {
        if ($this->should_skip()) {
            return;
        }
        $profile_key = $this->resolve_profile_key();
        $profiles = self::profiles();
        if (!$profile_key || empty($profiles[$profile_key]['routes'])) {
            return;
        }
        $routes = $profiles[$profile_key]['routes'];
        $request_path = $this->request_path();
        if (!isset($routes[$request_path])) {
            return;
        }
        $html = $this->get_remote_html($routes[$request_path]);
        if (is_wp_error($html) || '' === trim($html)) {
            status_header(503);
            nocache_headers();
            echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Support is temporarily unavailable</title></head><body><main style="max-width:700px;margin:12vh auto;padding:24px;font:18px/1.6 Arial,sans-serif"><h1>Support is temporarily unavailable</h1><p>Please refresh in a moment. If you need urgent help, contact your local service directly.</p></main></body></html>';
            exit;
        }
        status_header(200);
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
        header('X-Bridgit-Loader: ' . self::VERSION);
        echo $this->rewrite_html($html, $request_path); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    private function get_remote_html($remote_path) {
        $source = untrailingslashit(get_option(self::OPTION_SOURCE, 'https://bridgit-main-site.pages.dev'));
        $url = $source . '/' . ltrim($remote_path, '/');
        $key = self::CACHE_GROUP . md5($remote_path);
        $stale_key = self::CACHE_GROUP . 'stale_' . md5($remote_path);
        $refresh = current_user_can('manage_options') && isset($_GET['bridgit_refresh']) && '1' === sanitize_text_field(wp_unslash($_GET['bridgit_refresh']));
        if (!$refresh) {
            $cached = get_transient($key);
            if (false !== $cached) {
                return $cached;
            }
        }
        $response = wp_safe_remote_get($url, array('timeout' => 15, 'redirection' => 3, 'headers' => array('Accept' => 'text/html', 'User-Agent' => 'BridgitPageLoader/' . self::VERSION)));
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            $stale = get_transient($stale_key);
            return false !== $stale ? $stale : new WP_Error('bridgit_loader_unavailable', 'The published page could not be loaded.');
        }
        $body = wp_remote_retrieve_body($response);
        $ttl = $this->sanitize_ttl(get_option(self::OPTION_TTL, 300));
        set_transient($key, $body, $ttl);
        set_transient($stale_key, $body, 7 * DAY_IN_SECONDS);
        return $body;
    }

    private function rewrite_html($html, $request_path) {
        $source = untrailingslashit(get_option(self::OPTION_SOURCE, 'https://bridgit-main-site.pages.dev'));
        $asset_paths = array('/_astro/', '/images/', '/fonts/');
        foreach ($asset_paths as $asset_path) {
            $html = str_replace(array('src="' . $asset_path, "src='" . $asset_path, 'href="' . $asset_path, "href='" . $asset_path), array('src="' . $source . $asset_path, "src='" . $source . $asset_path, 'href="' . $source . $asset_path, "href='" . $source . $asset_path), $html);
        }
        $current = home_url($request_path);
        $html = preg_replace('#<link rel="canonical" href="[^"]+"\s*/?>#i', '<link rel="canonical" href="' . esc_url($current) . '">', $html);
        $html = preg_replace('#(<meta property="og:url" content=")[^"]+("\s*/?>)#i', '$1' . esc_url($current) . '$2', $html);
        return $html;
    }
}

Bridgit_Page_Loader::instance();

