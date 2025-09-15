<?php
/**
 * Plugin Name: WooCommerce Fraud Shield
 * Plugin URI: https://github.com/yoyaku-tech/woocommerce-fraud-shield
 * Description: Advanced anti-fraud shipping protection for WooCommerce. Detects suspicious product purchases and applies enhanced security measures to prevent credit card fraud attempts.
 * Version: 1.0.0
 * Author: YOYAKU Tech Team
 * Author URI: https://yoyaku.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woocommerce-fraud-shield
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.6
 * Requires PHP: 7.4
 * WC requires at least: 3.0
 * WC tested up to: 9.0
 *
 * @package WooCommerce_Fraud_Shield
 * @version 1.0.0
 * @author YOYAKU Tech Team
 * @since 2025-09-14
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class for WooCommerce Fraud Shield
 *
 * @since 1.0.0
 */
class WooCommerce_Fraud_Shield {

    private static $instance = null;
    private $suspicious_products = array(604098);
    private $debug_logs = array();

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks for 9999€ shipping replacement
     */
    private function init_hooks() {
        // Primary hook: Replace shipping rates with 9999€ security fee
        add_filter('woocommerce_package_rates', array($this, 'replace_with_security_fee'), 1000, 2);

        // Force cache invalidation to ensure hook fires
        add_filter('woocommerce_cart_shipping_packages', array($this, 'force_cache_invalidation'), 1);

        // Clear all shipping caches
        add_action('woocommerce_before_cart_table', array($this, 'clear_shipping_caches'));
        add_action('woocommerce_review_order_before_shipping', array($this, 'clear_shipping_caches'));

        // Red warning message for users
        add_action('woocommerce_before_cart', array($this, 'show_security_warning'));
        add_action('woocommerce_before_checkout_form', array($this, 'show_security_warning'));

        // Debug console for admins
        add_action('wp_footer', array($this, 'debug_console'));

        // Admin page
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    /**
     * MAIN FUNCTION: Replace all shipping with 9999€ security fee
     */
    public function replace_with_security_fee($rates, $package) {
        $this->log_debug("=== PROCESSING SHIPPING RATES ===");

        // Skip if admin or empty cart
        if (is_admin() || !WC()->cart || WC()->cart->is_empty()) {
            $this->log_debug("Skipping: Admin or empty cart");
            return $rates;
        }

        $cart_analysis = $this->analyze_cart();
        $this->log_debug("Cart analysis: " . json_encode($cart_analysis));

        // If cart contains ONLY suspicious products (604098)
        if ($cart_analysis['only_suspicious']) {
            $this->log_debug("🚫 SUSPICIOUS CART DETECTED - REPLACING WITH 9999€ SECURITY FEE");

            // Log the fraud attempt
            $this->log_fraud_attempt($cart_analysis, $rates);

            // CREATE THE 9999€ SECURITY RATE
            $security_rate = new WC_Shipping_Rate(
                'yoyaku_security_fee',                    // Rate ID
                '🚨 Security Verification Fee',           // Rate label
                9999,                                     // Cost: 9999€
                array(),                                  // Taxes
                'yoyaku_security'                         // Method ID
            );

            // REPLACE ALL RATES with our 9999€ security fee
            $new_rates = array(
                'yoyaku_security_fee' => $security_rate
            );

            $this->log_debug("✅ REPLACED " . count($rates) . " shipping methods with 9999€ security fee");

            // Store in session for tracking
            WC()->session->set('yoyaku_security_fee_applied', true);
            WC()->session->set('yoyaku_security_fee_time', time());

            return $new_rates; // Return ONLY the 9999€ rate
        }

        $this->log_debug("✅ Normal cart - allowing original shipping");

        // Clear security fee session for normal products
        WC()->session->set('yoyaku_security_fee_applied', false);

        return $rates; // Return original rates for normal products
    }

    /**
     * Force cache invalidation to ensure hooks fire
     */
    public function force_cache_invalidation($packages) {
        $this->log_debug("🔄 FORCING CACHE INVALIDATION");

        foreach ($packages as &$package) {
            // Force WooCommerce to recalculate by changing cache key
            $package['cache_key'] = 'yoyaku_antifraud_' . time() . '_' . wp_rand();
            $package['rate_cache'] = wp_rand();
        }
        unset($package);

        $this->log_debug("✅ Cache invalidation complete");
        return $packages;
    }

    /**
     * Clear all shipping caches
     */
    public function clear_shipping_caches() {
        $this->log_debug("🧹 CLEARING SHIPPING CACHES");

        // Clear WordPress transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_ship_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wc_ship_%'");

        // Clear WooCommerce session cache
        if (WC()->session) {
            WC()->session->set('shipping_for_package_0', null);
            WC()->session->set('chosen_shipping_methods', array());
        }

        // Clear object cache
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete('wc_shipping_zones', 'woocommerce');
            wp_cache_delete('wc_shipping_methods', 'woocommerce');
        }

        // Force recalculation
        if (WC()->cart && method_exists(WC()->cart, 'calculate_shipping')) {
            WC()->cart->calculate_shipping();
        }

        $this->log_debug("✅ Shipping caches cleared");
    }

    /**
     * Analyze cart contents for suspicious products
     */
    private function analyze_cart() {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return array(
                'total_items' => 0,
                'suspicious_items' => 0,
                'suspicious_products' => array(),
                'only_suspicious' => false,
                'cart_total' => 0
            );
        }

        $cart_items = WC()->cart->get_cart();
        $total_items = count($cart_items);
        $suspicious_items = 0;
        $suspicious_product_ids = array();

        foreach ($cart_items as $cart_item) {
            $product_id = $cart_item['product_id'];
            if (in_array($product_id, $this->suspicious_products)) {
                $suspicious_items++;
                $suspicious_product_ids[] = $product_id;
            }
        }

        return array(
            'total_items' => $total_items,
            'suspicious_items' => $suspicious_items,
            'suspicious_products' => $suspicious_product_ids,
            'only_suspicious' => ($total_items > 0 && $suspicious_items === $total_items),
            'cart_total' => WC()->cart->get_total('raw')
        );
    }

    /**
     * Log fraud attempts with 9999€ fee application
     */
    private function log_fraud_attempt($cart_analysis, $original_rates) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'user_id' => get_current_user_id(),
            'cart_analysis' => $cart_analysis,
            'original_rates_count' => count($original_rates),
            'original_rate_ids' => array_keys($original_rates),
            'action_taken' => 'REPLACED_WITH_9999_SECURITY_FEE',
            'security_fee_applied' => 9999,
            'referer' => wp_get_referer()
        );

        // Save to options
        $fraud_logs = get_option('yoyaku_fraud_9999_logs', array());
        $fraud_logs[] = $log_entry;

        // Keep only last 200 entries
        if (count($fraud_logs) > 200) {
            $fraud_logs = array_slice($fraud_logs, -200);
        }

        update_option('yoyaku_fraud_9999_logs', $fraud_logs);

        $this->log_debug("🚨 FRAUD LOGGED: IP " . $log_entry['ip_address'] . ", Products: " . implode(',', $cart_analysis['suspicious_products']));

        // Log to WordPress error log
        error_log('[YOYAKU Anti-Fraud] 9999€ security fee applied - IP: ' . $log_entry['ip_address'] . ', Products: ' . implode(',', $cart_analysis['suspicious_products']));
    }

    /**
     * Debug console for administrators
     */
    public function debug_console() {
        if (!current_user_can('manage_woocommerce') || (!is_cart() && !is_checkout())) {
            return;
        }

        $cart_analysis = $this->analyze_cart();
        $security_fee_applied = WC()->session ? WC()->session->get('yoyaku_security_fee_applied') : false;

        echo '<div id="yoyaku-9999-debug" style="
            position: fixed;
            top: 10px;
            right: 10px;
            background: linear-gradient(45deg, #1a1a1a, #2d2d2d);
            color: #00ff00;
            padding: 20px;
            max-width: 400px;
            max-height: 500px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
            z-index: 999999;
            border: 2px solid #ff6600;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(255,102,0,0.3);
        ">';

        echo '<div style="color: #ff6600; font-weight: bold; text-align: center; margin-bottom: 15px;">
            💰 YOYAKU 9999€ SECURITY DEBUG
        </div>';

        echo '<div style="color: #00ffff; margin-bottom: 15px;">
            <strong>CART STATUS:</strong><br>
            Only Suspicious: ' . ($cart_analysis['only_suspicious'] ? '<span style="color: #ff0000;">YES - 9999€ FEE SHOULD APPLY</span>' : '<span style="color: #00ff00;">NO - NORMAL SHIPPING</span>') . '<br>
            Security Fee Applied: ' . ($security_fee_applied ? '<span style="color: #ff0000;">YES (9999€)</span>' : '<span style="color: #00ff00;">NO</span>') . '<br>
            Products: ' . implode(', ', $cart_analysis['suspicious_products']) . '<br>
            Total Items: ' . $cart_analysis['total_items'] . '<br>
            Suspicious Items: ' . $cart_analysis['suspicious_items'] . '
        </div>';

        echo '<div style="color: #ffff00; margin-bottom: 15px;">
            <strong>SYSTEM STATUS:</strong><br>
            Hook Priority: 1000 (High)<br>
            Cache Cleared: ✅<br>
            Rate Replacement: ✅<br>
            Logging: ✅
        </div>';

        echo '<div style="color: #00ff00; font-size: 10px; border-top: 1px solid #666; padding-top: 10px;">
            <strong>RECENT DEBUG LOGS:</strong><br>';
            $recent_logs = array_slice($this->debug_logs, -10);
            foreach ($recent_logs as $log) {
                echo date('H:i:s', $log['time']) . ': ' . htmlspecialchars($log['message']) . '<br>';
            }
        echo '</div>';

        echo '</div>';
    }

    /**
     * Show red security warning message to users
     */
    public function show_security_warning() {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        $cart_analysis = $this->analyze_cart();

        // Only show warning when security fee is applied (only suspicious products)
        if ($cart_analysis['only_suspicious']) {
            echo '<div style="
                background: #dc3545;
                color: #ffffff;
                padding: 15px 20px;
                margin: 20px 0;
                border: 2px solid #b02a37;
                border-radius: 8px;
                font-weight: bold;
                font-size: 16px;
                text-align: center;
                box-shadow: 0 2px 10px rgba(220, 53, 69, 0.3);
            ">
                🚨 <strong>SUSPECT ACTIVITY DETECTED - SECURITY DEPLOYED</strong> 🚨
                <div style="font-size: 14px; margin-top: 8px; font-weight: normal;">
                    Enhanced security measures have been activated for this transaction.
                </div>
            </div>';
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Anti-Fraud 9999€ Fee',
            'Anti-Fraud 9999€',
            'manage_woocommerce',
            'yoyaku-fraud-9999-fee',
            array($this, 'admin_page')
        );
    }

    /**
     * Admin page
     */
    public function admin_page() {
        $fraud_logs = get_option('yoyaku_fraud_9999_logs', array());
        $recent_logs = array_slice(array_reverse($fraud_logs), 0, 30);

        echo '<div class="wrap">';
        echo '<h1>💰 YOYAKU Anti-Fraud 9999€ Security Fee</h1>';

        echo '<div class="notice notice-warning">';
        echo '<p><strong>⚡ ACTIVE PROTECTION:</strong> When product 604098 is alone in cart, ALL shipping methods are replaced with a single 9999€ Security Verification Fee.</p>';
        echo '</div>';

        echo '<div style="background: #f0f8ff; border: 1px solid #0073aa; padding: 15px; margin: 20px 0;">';
        echo '<h3>🎯 How It Works:</h3>';
        echo '<ul>';
        echo '<li>✅ <strong>Normal products:</strong> Regular shipping rates apply</li>';
        echo '<li>🚫 <strong>Product 604098 alone:</strong> All shipping replaced with 9999€ Security Fee</li>';
        echo '<li>📊 <strong>Mixed cart:</strong> 604098 + normal products = regular shipping</li>';
        echo '<li>🔍 <strong>Purpose:</strong> Deters fraud attempts while allowing legitimate mixed orders</li>';
        echo '</ul>';
        echo '</div>';

        echo '<h2>📋 Recent 9999€ Security Fee Applications</h2>';
        echo '<p>Showing last 30 instances where the 9999€ security fee was applied:</p>';

        echo '<table class="widefat">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Date/Time</th>';
        echo '<th>IP Address</th>';
        echo '<th>Products</th>';
        echo '<th>Original Rates</th>';
        echo '<th>Fee Applied</th>';
        echo '<th>User Agent</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        if (empty($recent_logs)) {
            echo '<tr><td colspan="6" style="text-align: center; color: #666;">No 9999€ security fees applied yet</td></tr>';
        } else {
            foreach ($recent_logs as $log) {
                echo '<tr>';
                echo '<td>' . esc_html($log['timestamp']) . '</td>';
                echo '<td><code>' . esc_html($log['ip_address']) . '</code></td>';
                echo '<td><strong>' . esc_html(implode(', ', $log['cart_analysis']['suspicious_products'])) . '</strong></td>';
                echo '<td>' . esc_html($log['original_rates_count']) . ' methods</td>';
                echo '<td style="color: red; font-weight: bold;">9999€</td>';
                echo '<td title="' . esc_attr($log['user_agent']) . '">' . esc_html(substr($log['user_agent'], 0, 50)) . '...</td>';
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '</table>';

        echo '<div style="margin-top: 30px; padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7;">';
        echo '<h3>🧪 Testing Instructions:</h3>';
        echo '<ol>';
        echo '<li><strong>Test Case 1:</strong> Add ONLY product 604098 to cart → Go to checkout → Should see "🚨 Security Verification Fee - 9999€"</li>';
        echo '<li><strong>Test Case 2:</strong> Add product 604098 + any normal product → Should see regular shipping options</li>';
        echo '<li><strong>Test Case 3:</strong> Add only normal products → Should see regular shipping options</li>';
        echo '<li><strong>Debug:</strong> Admins see debug console in top-right corner of cart/checkout pages</li>';
        echo '</ol>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Debug logging
     */
    private function log_debug($message) {
        $this->debug_logs[] = array(
            'time' => time(),
            'message' => $message
        );

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[YOYAKU 9999€ DEBUG] ' . $message);
        }
    }

    /**
     * Get client IP
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'REMOTE_ADDR');

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

// Initialize the corrected 9999€ rate system
// Initialize the plugin
add_action('plugins_loaded', 'woocommerce_fraud_shield_init');

/**
 * Initialize WooCommerce Fraud Shield
 *
 * @since 1.0.0
 */
function woocommerce_fraud_shield_init() {
    if (class_exists('WooCommerce')) {
        WooCommerce_Fraud_Shield::getInstance();
    } else {
        add_action('admin_notices', 'woocommerce_fraud_shield_woocommerce_missing_notice');
    }
}

/**
 * Display admin notice if WooCommerce is not active
 *
 * @since 1.0.0
 */
function woocommerce_fraud_shield_woocommerce_missing_notice() {
    echo '<div class="notice notice-error"><p>';
    echo '<strong>WooCommerce Fraud Shield</strong> requires WooCommerce to be installed and active.';
    echo '</p></div>';
}