<?php
/**
 * Cache Manager Class
 *
 * @package AI_Auto_News_Poster
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AANP_Cache_Manager {
    
    private $cache_group = 'aanp_cache';
    private $cache_expiry = 3600; // 1 hour default
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init_cache_hooks'));
    }
    
    /**
     * Initialize cache hooks
     */
    public function init_cache_hooks() {
        // Purge cache when posts are created/updated
        add_action('aanp_post_created', array($this, 'purge_post_cache'));
        add_action('aanp_settings_updated', array($this, 'purge_settings_cache'));
    }
    
    /**
     * Get cached data
     *
     * @param string $key Cache key
     * @param mixed $default Default value
     * @return mixed Cached data or default
     */
    public function get($key, $default = false) {
        $cache_key = $this->get_cache_key($key);
        
        // Try WordPress object cache first
        $data = wp_cache_get($cache_key, $this->cache_group);
        
        if ($data !== false) {
            return $data;
        }
        
        // Try transient cache
        $data = get_transient($cache_key);
        
        return $data !== false ? $data : $default;
    }
    
    /**
     * Set cached data
     *
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int $expiry Cache expiry in seconds
     * @return bool Success status
     */
    public function set($key, $data, $expiry = null) {
        if ($expiry === null) {
            $expiry = $this->cache_expiry;
        }
        
        $cache_key = $this->get_cache_key($key);
        
        // Set in WordPress object cache
        wp_cache_set($cache_key, $data, $this->cache_group, $expiry);
        
        // Set in transient cache as fallback
        return set_transient($cache_key, $data, $expiry);
    }
    
    /**
     * Delete cached data
     *
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete($key) {
        $cache_key = $this->get_cache_key($key);
        
        // Delete from object cache
        wp_cache_delete($cache_key, $this->cache_group);
        
        // Delete from transient cache
        return delete_transient($cache_key);
    }
    
    /**
     * Purge all plugin cache
     */
    public function purge_all() {
        // Clear WordPress object cache
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group($this->cache_group);
        }
        
        // Clear transients
        $this->clear_transients();
        
        // Purge external caches
        $this->purge_external_cache();
    }
    
    /**
     * Purge post-related cache
     */
    public function purge_post_cache() {
        $this->delete('recent_posts');
        $this->delete('post_stats');
        $this->purge_external_cache();
    }
    
    /**
     * Purge settings cache
     */
    public function purge_settings_cache() {
        $this->delete('plugin_settings');
        $this->delete('rss_feeds');
    }
    
    /**
     * Get cache key with prefix
     *
     * @param string $key Original key
     * @return string Prefixed cache key
     */
    private function get_cache_key($key) {
        return 'aanp_' . md5($key);
    }
    
    /**
     * Clear all plugin transients
     */
    private function clear_transients() {
        global $wpdb;
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->options}'") === $wpdb->options) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $wpdb->esc_like('_transient_aanp_') . '%'
                )
            );
            
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $wpdb->esc_like('_transient_timeout_aanp_') . '%'
                )
            );
        }
    }
    
    /**
     * Purge external cache systems
     */
    private function purge_external_cache() {
        // OpenLiteSpeed Cache
        if (function_exists('litespeed_purge_all')) {
            litespeed_purge_all();
        }
        
        // W3 Total Cache
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        
        // WP Super Cache
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        
        // WP Rocket
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        
        // Cloudflare
        $this->purge_cloudflare_cache();
    }
    
    /**
     * Purge Cloudflare cache if configured
     */
    private function purge_cloudflare_cache() {
        $options = get_option('aanp_settings', array());
        
        if (empty($options['cloudflare_zone_id']) || empty($options['cloudflare_api_key'])) {
            return;
        }
        
        // Validate zone ID format
        if (!preg_match('/^[a-f0-9]{32}$/i', $options['cloudflare_zone_id'])) {
            return;
        }
        
        $url = 'https://api.cloudflare.com/client/v4/zones/' . sanitize_text_field($options['cloudflare_zone_id']) . '/purge_cache';
        
        wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . sanitize_text_field($options['cloudflare_api_key']),
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode(array('purge_everything' => true)),
            'timeout' => 30
        ));
    }
    
    /**
     * Get cache statistics
     *
     * @return array Cache stats
     */
    public function get_cache_stats() {
        global $wpdb;
        
        $transient_count = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->options}'") === $wpdb->options) {
            $transient_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $wpdb->esc_like('_transient_aanp_') . '%'
                )
            );
        }
        
        return array(
            'transients' => (int) $transient_count,
            'object_cache_enabled' => wp_using_ext_object_cache(),
            'cache_plugins' => $this->detect_cache_plugins()
        );
    }
    
    /**
     * Detect active cache plugins
     *
     * @return array Active cache plugins
     */
    private function detect_cache_plugins() {
        $plugins = array();
        
        if (defined('LSCWP_V')) {
            $plugins[] = 'LiteSpeed Cache';
        }
        
        if (defined('W3TC')) {
            $plugins[] = 'W3 Total Cache';
        }
        
        if (defined('WP_CACHE') && WP_CACHE) {
            $plugins[] = 'WP Super Cache';
        }
        
        if (defined('WP_ROCKET_VERSION')) {
            $plugins[] = 'WP Rocket';
        }
        
        return $plugins;
    }
}