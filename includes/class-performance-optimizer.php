<?php
/**
 * Performance Optimizer Class
 *
 * @package AI_Auto_News_Poster
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AANP_Performance_Optimizer {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init_optimizations'));
    }
    
    /**
     * Initialize performance optimizations
     */
    public function init_optimizations() {
        // Optimize database queries
        add_filter('posts_clauses', array($this, 'optimize_post_queries'), 10, 2);
        
        // Add async loading for admin scripts
        add_filter('script_loader_tag', array($this, 'add_async_attribute'), 10, 3);
        
        // Optimize images if needed
        add_filter('wp_generate_attachment_metadata', array($this, 'optimize_images'));
    }
    
    /**
     * Optimize post queries
     *
     * @param array $clauses Query clauses
     * @param WP_Query $query Query object
     * @return array Modified clauses
     */
    public function optimize_post_queries($clauses, $query) {
        if (!is_admin() || !$query->is_main_query()) {
            return $clauses;
        }
        
        // Add index hints for better performance
        if (isset($clauses['where']) && strpos($clauses['where'], 'aanp_') !== false) {
            if (isset($clauses['join'])) {
                $clauses['join'] .= " USE INDEX (PRIMARY)";
            }
        }
        
        return $clauses;
    }
    
    /**
     * Add async attribute to scripts
     *
     * @param string $tag Script tag
     * @param string $handle Script handle
     * @param string $src Script source
     * @return string Modified tag
     */
    public function add_async_attribute($tag, $handle, $src) {
        if ('aanp-admin-js' === $handle) {
            return str_replace(' src', ' async src', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Optimize images
     *
     * @param array $metadata Image metadata
     * @return array Modified metadata
     */
    public function optimize_images($metadata) {
        // Basic image optimization placeholder
        return $metadata;
    }
    
    /**
     * Get performance metrics
     *
     * @return array Performance data
     */
    public function get_performance_metrics() {
        return array(
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'query_count' => get_num_queries(),
            'load_time' => timer_stop()
        );
    }
}