<?php
/**
 * Security Manager Class
 *
 * @package AI_Auto_News_Poster
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AANP_Security_Manager {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init_security'));
    }
    
    /**
     * Initialize security measures
     */
    public function init_security() {
        // Add security headers
        add_action('send_headers', array($this, 'add_security_headers'));
        
        // Sanitize all inputs
        add_action('admin_init', array($this, 'sanitize_inputs'));
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers() {
        if (!is_admin()) {
            return;
        }
        
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
    
    /**
     * Sanitize all inputs
     */
    public function sanitize_inputs() {
        if (isset($_POST['aanp_settings'])) {
            $_POST['aanp_settings'] = $this->deep_sanitize($_POST['aanp_settings']);
        }
    }
    
    /**
     * Deep sanitize array
     *
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    private function deep_sanitize($data) {
        if (is_array($data)) {
            return array_map(array($this, 'deep_sanitize'), $data);
        }
        
        return sanitize_text_field($data);
    }
    
    /**
     * Validate API response
     *
     * @param string $response API response
     * @return bool True if valid
     */
    public function validate_api_response($response) {
        // Validate input type
        if (!is_string($response)) {
            return false;
        }
        
        // Check for suspicious content
        $suspicious_patterns = array(
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/<object[^>]*>.*?<\/object>/is',
            '/<embed[^>]*>/is'
        );
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $response)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Hash sensitive data
     *
     * @param string $data Data to hash
     * @return string Hashed data
     */
    public function hash_data($data) {
        return hash('sha256', $data . wp_salt('auth'));
    }
    
    /**
     * Generate secure token
     *
     * @return string Secure token
     */
    public function generate_token() {
        return wp_generate_password(32, false);
    }
}