<?php
/**
 * Rate Limiter Class
 *
 * @package AI_Auto_News_Poster
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AANP_Rate_Limiter {
    
    private $cache_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->cache_manager = new AANP_Cache_Manager();
    }
    
    /**
     * Check if action is rate limited
     *
     * @param string $action Action identifier
     * @param int $limit Number of attempts allowed
     * @param int $window Time window in seconds
     * @param string $identifier User identifier (IP, user ID, etc.)
     * @return bool True if rate limited
     */
    public function is_rate_limited($action, $limit = 5, $window = 3600, $identifier = null) {
        if ($identifier === null) {
            $identifier = $this->get_client_identifier();
        }
        
        $key = "rate_limit_{$action}_{$identifier}";
        $attempts = $this->cache_manager->get($key, 0);
        
        return $attempts >= $limit;
    }
    
    /**
     * Record an attempt
     *
     * @param string $action Action identifier
     * @param int $window Time window in seconds
     * @param string $identifier User identifier
     * @return int Current attempt count
     */
    public function record_attempt($action, $window = 3600, $identifier = null) {
        if ($identifier === null) {
            $identifier = $this->get_client_identifier();
        }
        
        $key = "rate_limit_{$action}_{$identifier}";
        $attempts = $this->cache_manager->get($key, 0) + 1;
        
        $this->cache_manager->set($key, $attempts, $window);
        
        return $attempts;
    }
    
    /**
     * Reset rate limit for action
     *
     * @param string $action Action identifier
     * @param string $identifier User identifier
     */
    public function reset_limit($action, $identifier = null) {
        if ($identifier === null) {
            $identifier = $this->get_client_identifier();
        }
        
        $key = "rate_limit_{$action}_{$identifier}";
        $this->cache_manager->delete($key);
    }
    
    /**
     * Get client identifier
     *
     * @return string Client identifier
     */
    private function get_client_identifier() {
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }
        
        return 'ip_' . $this->get_client_ip();
    }
    
    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return '127.0.0.1';
    }
}