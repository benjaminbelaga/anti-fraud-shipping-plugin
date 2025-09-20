<?php
/**
 * Plugin Name: WooCommerce Fraud Shield
 * Plugin URI: https://yoyaku.io
 * Description: Système de protection anti-fraude sophistiqué pour yoyaku.io avec détection honeypot et frais de sécurité automatiques
 * Version: 1.1.0
 * Author: YOYAKU Infrastructure Team
 * Author URI: https://yoyaku.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woocommerce-fraud-shield
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.7
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.4
 * Network: false
 *
 * @package WooCommerce_Fraud_Shield
 * @category Security
 * @author YOYAKU Infrastructure Team
 */

// Sécurité - Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

// Vérification de sécurité pour WooCommerce
if (!function_exists('is_plugin_active')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

// Constantes du plugin
define('WFS_PLUGIN_FILE', __FILE__);
define('WFS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WFS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WFS_VERSION', '1.1.0');
define('WFS_LOG_DIR', WP_CONTENT_DIR . '/fraud-shield-logs/');
define('WFS_HONEYPOT_PRODUCT_ID', 604098);
define('WFS_SECURITY_FEE', 9999.00);
define('WFS_MIN_PHP', '7.4');
define('WFS_MIN_WC', '8.0');

/**
 * Déclaration de compatibilité HPOS WooCommerce
 * Déclare officiellement la compatibilité avec High-Performance Order Storage
 */
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Classe principale WooCommerce Fraud Shield
 *
 * Système sophistiqué de protection anti-fraude pour yoyaku.io
 * - Détection honeypot produit 604098
 * - Application automatique de frais de sécurité 9999€
 * - Interface admin complète avec logs et statistiques
 * - Compatibilité HPOS officielle
 * - Standards WordPress/WooCommerce professionnels
 */
class WooCommerce_Fraud_Shield {

    /**
     * Instance singleton
     * @var WooCommerce_Fraud_Shield|null
     */
    private static $instance = null;

    /**
     * Configuration du plugin
     * @var array
     */
    private $config = [
        'enabled' => false,
        'alert_threshold' => 60,
        'auto_security_fee' => true,
        'email_alerts' => true,
        'log_all_attempts' => true,
        'hpos_enabled' => false
    ];

    /**
     * Statistiques en temps réel
     * @var array
     */
    private $stats = [
        'honeypot_detections' => 0,
        'security_fees_applied' => 0,
        'total_amount_protected' => 0,
        'alerts_sent' => 0
    ];

    /**
     * Obtenir l'instance singleton
     *
     * @return WooCommerce_Fraud_Shield
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructeur privé pour singleton
     */
    private function __construct() {
        add_action('init', [$this, 'init']);
        add_action('plugins_loaded', [$this, 'load_textdomain']);

        // Hooks WooCommerce
        add_action('woocommerce_before_calculate_totals', [$this, 'detect_honeypot_and_apply_security_fee'], 10, 1);
        add_action('woocommerce_checkout_order_processed', [$this, 'analyze_order_hpos'], 10, 3);
        add_action('woocommerce_checkout_process', [$this, 'analyze_order_legacy'], 5);

        // Interface admin
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        // AJAX handlers
        add_action('wp_ajax_wfs_get_live_stats', [$this, 'ajax_get_live_stats']);
        add_action('wp_ajax_wfs_clear_logs', [$this, 'ajax_clear_logs']);

        // Activation/Désactivation
        register_activation_hook(WFS_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(WFS_PLUGIN_FILE, [$this, 'deactivate']);

        // Charger la configuration
        $this->load_config();
    }

    /**
     * Initialisation du plugin
     */
    public function init() {
        // Vérifications de compatibilité
        if (!$this->check_requirements()) {
            return;
        }

        // Créer les dossiers nécessaires
        $this->create_directories();

        // Détecter HPOS
        $this->config['hpos_enabled'] = $this->is_hpos_enabled();

        // Log du démarrage
        $this->log_system_info();

        // Charger les statistiques
        $this->load_stats();
    }

    /**
     * Vérifier les prérequis
     *
     * @return bool
     */
    private function check_requirements() {
        $errors = [];

        // PHP version
        if (version_compare(PHP_VERSION, WFS_MIN_PHP, '<')) {
            $errors[] = sprintf(__('PHP %s or higher is required.', 'woocommerce-fraud-shield'), WFS_MIN_PHP);
        }

        // WooCommerce
        if (!class_exists('WooCommerce')) {
            $errors[] = __('WooCommerce must be installed and activated.', 'woocommerce-fraud-shield');
        } elseif (version_compare(WC_VERSION, WFS_MIN_WC, '<')) {
            $errors[] = sprintf(__('WooCommerce %s or higher is required.', 'woocommerce-fraud-shield'), WFS_MIN_WC);
        }

        if (!empty($errors)) {
            add_action('admin_notices', function() use ($errors) {
                foreach ($errors as $error) {
                    echo '<div class="notice notice-error"><p><strong>WooCommerce Fraud Shield:</strong> ' . esc_html($error) . '</p></div>';
                }
            });
            return false;
        }

        return true;
    }

    /**
     * Détecter si HPOS est activé
     *
     * @return bool
     */
    private function is_hpos_enabled() {
        if (!class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
            return false;
        }
        return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    /**
     * Créer les dossiers nécessaires
     */
    private function create_directories() {
        if (!file_exists(WFS_LOG_DIR)) {
            wp_mkdir_p(WFS_LOG_DIR);

            // Protection du dossier
            $htaccess_content = "Order Deny,Allow\nDeny from all";
            file_put_contents(WFS_LOG_DIR . '.htaccess', $htaccess_content);
            file_put_contents(WFS_LOG_DIR . 'index.php', '<?php // Silence is golden');
        }
    }

    /**
     * DÉTECTION HONEYPOT ET APPLICATION DES FRAIS DE SÉCURITÉ
     * Coeur du système anti-fraude sophistiqué
     */
    public function detect_honeypot_and_apply_security_fee($cart) {
        if (!$this->config['enabled'] || !$this->config['auto_security_fee']) {
            return;
        }

        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        if (did_action('woocommerce_before_calculate_totals') >= 2) {
            return;
        }

        $honeypot_detected = false;
        $honeypot_quantity = 0;

        // Analyser le panier pour détecter le produit honeypot
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];

            if ($product_id == WFS_HONEYPOT_PRODUCT_ID) {
                $honeypot_detected = true;
                $honeypot_quantity = $cart_item['quantity'];

                // Log de la détection
                $this->log_honeypot_detection($product_id, $honeypot_quantity);

                // Supprimer le produit honeypot du panier
                $cart->remove_cart_item($cart_item_key);
                break;
            }
        }

        // Si honeypot détecté, appliquer les frais de sécurité
        if ($honeypot_detected) {
            $this->apply_security_fee($cart, $honeypot_quantity);

            // Mettre à jour les statistiques
            $this->stats['honeypot_detections']++;
            $this->stats['security_fees_applied']++;
            $this->stats['total_amount_protected'] += WFS_SECURITY_FEE;
            $this->save_stats();

            // Alerte admin immédiate
            $this->send_fraud_alert([
                'type' => 'honeypot_detection',
                'product_id' => WFS_HONEYPOT_PRODUCT_ID,
                'quantity' => $honeypot_quantity,
                'security_fee' => WFS_SECURITY_FEE,
                'ip' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'timestamp' => current_time('mysql')
            ]);
        }
    }

    /**
     * Appliquer les frais de sécurité
     *
     * @param WC_Cart $cart
     * @param int $honeypot_quantity
     */
    private function apply_security_fee($cart, $honeypot_quantity = 1) {
        $security_fee = WFS_SECURITY_FEE * $honeypot_quantity;

        $cart->add_fee(
            __('Frais de sécurité anti-fraude', 'woocommerce-fraud-shield'),
            $security_fee,
            true // Taxable
        );

        // Ajouter une notice pour l'utilisateur
        if (!wc_has_notice(__('Des frais de sécurité ont été appliqués à votre commande.', 'woocommerce-fraud-shield'), 'notice')) {
            wc_add_notice(
                __('Des frais de sécurité ont été appliqués à votre commande pour des raisons de sécurité.', 'woocommerce-fraud-shield'),
                'notice'
            );
        }
    }

    /**
     * Logger la détection honeypot
     *
     * @param int $product_id
     * @param int $quantity
     */
    private function log_honeypot_detection($product_id, $quantity) {
        $log_data = [
            'timestamp' => current_time('mysql'),
            'type' => 'honeypot_detection',
            'product_id' => $product_id,
            'quantity' => $quantity,
            'security_fee_applied' => WFS_SECURITY_FEE,
            'ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'session_id' => WC()->session ? WC()->session->get_customer_id() : '',
            'cart_contents' => $this->get_cart_summary()
        ];

        $this->log_event($log_data, 'honeypot');
    }

    /**
     * Obtenir un résumé du panier
     *
     * @return array
     */
    private function get_cart_summary() {
        if (!WC()->cart) {
            return [];
        }

        $summary = [];
        foreach (WC()->cart->get_cart() as $cart_item) {
            $summary[] = [
                'product_id' => $cart_item['product_id'],
                'quantity' => $cart_item['quantity'],
                'price' => $cart_item['line_total']
            ];
        }

        return $summary;
    }

    /**
     * Analyser une commande (HPOS)
     *
     * @param int $order_id
     * @param array $posted_data
     * @param WC_Order $order
     */
    public function analyze_order_hpos($order_id, $posted_data, $order) {
        if (!$this->config['enabled'] || !$this->config['hpos_enabled']) {
            return;
        }

        try {
            $order_data = $this->extract_order_data_hpos($order, $posted_data);
            $risk_analysis = $this->calculate_comprehensive_risk_score($order_data);

            $log_data = [
                'timestamp' => current_time('mysql'),
                'order_id' => $order_id,
                'mode' => 'hpos',
                'ip' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'risk_analysis' => $risk_analysis,
                'order_data' => $order_data
            ];

            // Log toutes les analyses
            $this->log_event($log_data, 'order_analysis');

            // Alerte si score élevé
            if ($risk_analysis['score'] >= $this->config['alert_threshold']) {
                $this->log_event($log_data, 'high_risk_alert');
                $this->send_fraud_alert($log_data);
                $this->stats['alerts_sent']++;
                $this->save_stats();
            }

        } catch (Exception $e) {
            error_log('WooCommerce Fraud Shield HPOS Error: ' . $e->getMessage());
        }
    }

    /**
     * Analyser une commande (Legacy)
     *
     * @return void
     */
    public function analyze_order_legacy() {
        if (!$this->config['enabled'] || $this->config['hpos_enabled']) {
            return;
        }

        try {
            $order_data = $this->extract_order_data_legacy();
            $risk_analysis = $this->calculate_comprehensive_risk_score($order_data);

            $log_data = [
                'timestamp' => current_time('mysql'),
                'order_id' => 'pending_legacy',
                'mode' => 'legacy',
                'ip' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'risk_analysis' => $risk_analysis,
                'order_data' => $order_data
            ];

            // Log toutes les analyses
            $this->log_event($log_data, 'order_analysis');

            // Alerte si score élevé
            if ($risk_analysis['score'] >= $this->config['alert_threshold']) {
                $this->log_event($log_data, 'high_risk_alert');
                $this->send_fraud_alert($log_data);
                $this->stats['alerts_sent']++;
                $this->save_stats();
            }

        } catch (Exception $e) {
            error_log('WooCommerce Fraud Shield Legacy Error: ' . $e->getMessage());
        }
    }

    /**
     * Extraire les données de commande (HPOS)
     *
     * @param WC_Order $order
     * @param array $posted_data
     * @return array
     */
    private function extract_order_data_hpos($order, $posted_data) {
        return [
            'email' => $order->get_billing_email(),
            'country' => $order->get_billing_country(),
            'amount' => $order->get_total(),
            'items_count' => $order->get_item_count(),
            'customer_id' => $order->get_customer_id(),
            'billing_first_name' => $order->get_billing_first_name(),
            'billing_last_name' => $order->get_billing_last_name(),
            'billing_phone' => $order->get_billing_phone(),
            'shipping_country' => $order->get_shipping_country(),
            'payment_method' => $order->get_payment_method(),
            'currency' => $order->get_currency(),
            'order_items' => $this->extract_order_items($order),
            'has_security_fee' => $this->order_has_security_fee($order)
        ];
    }

    /**
     * Extraire les données de commande (Legacy)
     *
     * @return array
     */
    private function extract_order_data_legacy() {
        return [
            'email' => $_POST['billing_email'] ?? '',
            'country' => $_POST['billing_country'] ?? '',
            'amount' => WC()->cart ? WC()->cart->get_total('raw') : 0,
            'items_count' => WC()->cart ? WC()->cart->get_cart_contents_count() : 0,
            'customer_id' => get_current_user_id(),
            'billing_first_name' => $_POST['billing_first_name'] ?? '',
            'billing_last_name' => $_POST['billing_last_name'] ?? '',
            'billing_phone' => $_POST['billing_phone'] ?? '',
            'shipping_country' => $_POST['shipping_country'] ?? $_POST['billing_country'] ?? '',
            'payment_method' => $_POST['payment_method'] ?? '',
            'currency' => get_woocommerce_currency(),
            'order_items' => $this->get_cart_summary(),
            'has_security_fee' => $this->cart_has_security_fee()
        ];
    }

    /**
     * Extraire les articles de la commande
     *
     * @param WC_Order $order
     * @return array
     */
    private function extract_order_items($order) {
        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = [
                'product_id' => $item->get_product_id(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total()
            ];
        }
        return $items;
    }

    /**
     * Vérifier si la commande a des frais de sécurité
     *
     * @param WC_Order $order
     * @return bool
     */
    private function order_has_security_fee($order) {
        foreach ($order->get_fees() as $fee) {
            if (strpos($fee->get_name(), 'sécurité') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Vérifier si le panier a des frais de sécurité
     *
     * @return bool
     */
    private function cart_has_security_fee() {
        if (!WC()->cart) return false;

        foreach (WC()->cart->get_fees() as $fee) {
            if (strpos($fee->name, 'sécurité') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Calculer un score de risque complet et sophistiqué
     *
     * @param array $order_data
     * @return array
     */
    private function calculate_comprehensive_risk_score($order_data) {
        $score = 0;
        $factors = [];
        $details = [];

        // 1. Détection de frais de sécurité (honeypot confirmé)
        if ($order_data['has_security_fee']) {
            $score += 90; // Score très élevé pour honeypot
            $factors[] = 'security_fee_applied';
            $details['security_fee'] = 'Honeypot product detected - security fee applied';
        }

        // 2. Email analysis
        $email_risk = $this->analyze_email_risk($order_data['email']);
        $score += $email_risk['score'];
        if ($email_risk['score'] > 0) {
            $factors = array_merge($factors, $email_risk['factors']);
            $details['email'] = $email_risk['details'];
        }

        // 3. Geographic analysis
        $geo_risk = $this->analyze_geographic_risk($order_data['country'], $order_data['shipping_country']);
        $score += $geo_risk['score'];
        if ($geo_risk['score'] > 0) {
            $factors = array_merge($factors, $geo_risk['factors']);
            $details['geographic'] = $geo_risk['details'];
        }

        // 4. Order value analysis
        $value_risk = $this->analyze_order_value_risk($order_data['amount'], $order_data['items_count']);
        $score += $value_risk['score'];
        if ($value_risk['score'] > 0) {
            $factors = array_merge($factors, $value_risk['factors']);
            $details['value'] = $value_risk['details'];
        }

        // 5. Customer analysis
        $customer_risk = $this->analyze_customer_risk($order_data['customer_id'], $order_data['billing_first_name'], $order_data['billing_last_name']);
        $score += $customer_risk['score'];
        if ($customer_risk['score'] > 0) {
            $factors = array_merge($factors, $customer_risk['factors']);
            $details['customer'] = $customer_risk['details'];
        }

        // 6. Technical analysis (IP, User Agent)
        $technical_risk = $this->analyze_technical_risk();
        $score += $technical_risk['score'];
        if ($technical_risk['score'] > 0) {
            $factors = array_merge($factors, $technical_risk['factors']);
            $details['technical'] = $technical_risk['details'];
        }

        // 7. Behavioral analysis
        $behavioral_risk = $this->analyze_behavioral_risk($order_data);
        $score += $behavioral_risk['score'];
        if ($behavioral_risk['score'] > 0) {
            $factors = array_merge($factors, $behavioral_risk['factors']);
            $details['behavioral'] = $behavioral_risk['details'];
        }

        return [
            'score' => min($score, 100), // Plafonner à 100
            'factors' => array_unique($factors),
            'details' => $details,
            'risk_level' => $this->get_risk_level($score)
        ];
    }

    /**
     * Analyser le risque email
     *
     * @param string $email
     * @return array
     */
    private function analyze_email_risk($email) {
        $score = 0;
        $factors = [];
        $details = [];

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $score += 30;
            $factors[] = 'invalid_email';
            $details[] = 'Invalid or missing email address';
        }

        // Domaines temporaires
        $temp_domains = [
            '10minutemail.com', 'guerrillamail.com', 'mailinator.com',
            'tempmail.org', 'yopmail.com', 'throwaway.email',
            'temp-mail.org', '10minutemail.net', 'getnada.com',
            'sharklasers.com', 'mohmal.com', 'email-fake.com'
        ];

        $domain = substr(strrchr($email, "@"), 1);
        if (in_array(strtolower($domain), $temp_domains)) {
            $score += 40;
            $factors[] = 'temporary_email';
            $details[] = "Temporary email domain detected: {$domain}";
        }

        // Pattern suspects
        if (preg_match('/\d{6,}/', $email)) {
            $score += 15;
            $factors[] = 'suspicious_email_pattern';
            $details[] = 'Email contains long numeric sequence';
        }

        return ['score' => $score, 'factors' => $factors, 'details' => implode(', ', $details)];
    }

    /**
     * Analyser le risque géographique
     *
     * @param string $billing_country
     * @param string $shipping_country
     * @return array
     */
    private function analyze_geographic_risk($billing_country, $shipping_country) {
        $score = 0;
        $factors = [];
        $details = [];

        // Pays à haut risque
        $high_risk_countries = ['NG', 'GH', 'PK', 'BD', 'IN', 'RU', 'CN', 'IR', 'KP'];

        if (in_array($billing_country, $high_risk_countries)) {
            $score += 25;
            $factors[] = 'high_risk_country';
            $details[] = "High-risk billing country: {$billing_country}";
        }

        // Différence entre pays de facturation et livraison
        if (!empty($shipping_country) && $shipping_country !== $billing_country) {
            $score += 15;
            $factors[] = 'country_mismatch';
            $details[] = "Billing/shipping country mismatch: {$billing_country}/{$shipping_country}";
        }

        return ['score' => $score, 'factors' => $factors, 'details' => implode(', ', $details)];
    }

    /**
     * Analyser le risque de valeur de commande
     *
     * @param float $amount
     * @param int $items_count
     * @return array
     */
    private function analyze_order_value_risk($amount, $items_count) {
        $score = 0;
        $factors = [];
        $details = [];

        $amount = floatval($amount);
        $items_count = intval($items_count);

        // Montants élevés
        if ($amount > 1000) {
            $score += 20;
            $factors[] = 'high_amount';
            $details[] = "High order amount: €{$amount}";
        } elseif ($amount > 2000) {
            $score += 35;
            $factors[] = 'very_high_amount';
            $details[] = "Very high order amount: €{$amount}";
        }

        // Nombre d'articles
        if ($items_count > 20) {
            $score += 15;
            $factors[] = 'many_items';
            $details[] = "Many items in order: {$items_count}";
        } elseif ($items_count > 50) {
            $score += 25;
            $factors[] = 'excessive_items';
            $details[] = "Excessive items in order: {$items_count}";
        }

        // Ratio prix/article suspect
        if ($items_count > 0 && ($amount / $items_count) < 5) {
            $score += 10;
            $factors[] = 'low_average_price';
            $details[] = "Suspiciously low average item price";
        }

        return ['score' => $score, 'factors' => $factors, 'details' => implode(', ', $details)];
    }

    /**
     * Analyser le risque client
     *
     * @param int $customer_id
     * @param string $first_name
     * @param string $last_name
     * @return array
     */
    private function analyze_customer_risk($customer_id, $first_name, $last_name) {
        $score = 0;
        $factors = [];
        $details = [];

        // Client invité
        if (empty($customer_id) || $customer_id === 0) {
            $score += 10;
            $factors[] = 'guest_order';
            $details[] = 'Guest checkout';
        }

        // Noms suspects
        $full_name = trim($first_name . ' ' . $last_name);

        if (empty($full_name) || strlen($full_name) < 3) {
            $score += 15;
            $factors[] = 'invalid_name';
            $details[] = 'Invalid or missing name';
        }

        // Caractères répétés
        if (preg_match('/(.)\1{3,}/', $full_name)) {
            $score += 20;
            $factors[] = 'suspicious_name_pattern';
            $details[] = 'Name contains repeated characters';
        }

        // Nom trop long ou trop court
        if (strlen($full_name) > 100 || (strlen($full_name) > 0 && strlen($full_name) < 3)) {
            $score += 10;
            $factors[] = 'unusual_name_length';
            $details[] = 'Unusual name length';
        }

        return ['score' => $score, 'factors' => $factors, 'details' => implode(', ', $details)];
    }

    /**
     * Analyser le risque technique
     *
     * @return array
     */
    private function analyze_technical_risk() {
        $score = 0;
        $factors = [];
        $details = [];

        // Analyse IP
        $ip = $this->get_client_ip();
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $score += 20;
            $factors[] = 'suspicious_ip';
            $details[] = 'Invalid or suspicious IP address';
        }

        // Analyse User Agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (empty($user_agent) || strlen($user_agent) < 10) {
            $score += 15;
            $factors[] = 'missing_user_agent';
            $details[] = 'Missing or invalid user agent';
        }

        $suspicious_ua_patterns = [
            'bot', 'crawler', 'scraper', 'curl', 'wget', 'python', 'java',
            'automation', 'selenium', 'phantom', 'headless', 'spider'
        ];

        foreach ($suspicious_ua_patterns as $pattern) {
            if (stripos($user_agent, $pattern) !== false) {
                $score += 25;
                $factors[] = 'bot_user_agent';
                $details[] = "Bot-like user agent detected: {$pattern}";
                break;
            }
        }

        return ['score' => $score, 'factors' => $factors, 'details' => implode(', ', $details)];
    }

    /**
     * Analyser le risque comportemental
     *
     * @param array $order_data
     * @return array
     */
    private function analyze_behavioral_risk($order_data) {
        $score = 0;
        $factors = [];
        $details = [];

        // Commande très rapide (à implémenter avec tracking de session)
        // Pour l'instant, analyse basique

        // Méthode de paiement risquée
        $risky_payment_methods = ['cod', 'cheque'];
        if (in_array($order_data['payment_method'], $risky_payment_methods)) {
            $score += 10;
            $factors[] = 'risky_payment_method';
            $details[] = "Risky payment method: {$order_data['payment_method']}";
        }

        return ['score' => $score, 'factors' => $factors, 'details' => implode(', ', $details)];
    }

    /**
     * Obtenir le niveau de risque
     *
     * @param int $score
     * @return string
     */
    private function get_risk_level($score) {
        if ($score >= 80) return 'CRITICAL';
        if ($score >= 60) return 'HIGH';
        if ($score >= 40) return 'MEDIUM';
        if ($score >= 20) return 'LOW';
        return 'MINIMAL';
    }

    /**
     * Obtenir l'IP du client avec détection avancée
     *
     * @return string
     */
    private function get_client_ip() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',    // Cloudflare
            'HTTP_X_FORWARDED_FOR',     // Load balancer/proxy
            'HTTP_X_REAL_IP',           // Nginx
            'HTTP_CLIENT_IP',           // Proxy
            'HTTP_X_FORWARDED',         // Proxy
            'HTTP_FORWARDED'            // Standard
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Logger un événement
     *
     * @param array $data
     * @param string $type
     */
    private function log_event($data, $type = 'general') {
        if (!$this->config['log_all_attempts']) {
            return;
        }

        $log_file = WFS_LOG_DIR . $type . '-' . date('Y-m') . '.log';
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'data' => $data,
            'plugin_version' => WFS_VERSION
        ];

        $log_line = date('Y-m-d H:i:s') . ' - ' . json_encode($log_entry) . PHP_EOL;

        if (file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX) === false) {
            error_log('WooCommerce Fraud Shield: Failed to write log file ' . $log_file);
        }
    }

    /**
     * Logger les informations système
     */
    private function log_system_info() {
        $system_info = [
            'timestamp' => current_time('mysql'),
            'plugin_version' => WFS_VERSION,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'wc_version' => defined('WC_VERSION') ? WC_VERSION : 'unknown',
            'hpos_enabled' => $this->config['hpos_enabled'],
            'plugin_enabled' => $this->config['enabled'],
            'honeypot_product_id' => WFS_HONEYPOT_PRODUCT_ID,
            'security_fee' => WFS_SECURITY_FEE,
            'server_info' => [
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
                'php_sapi' => php_sapi_name(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            ]
        ];

        $this->log_event($system_info, 'system');
    }

    /**
     * Envoyer une alerte de fraude
     *
     * @param array $data
     */
    private function send_fraud_alert($data) {
        if (!$this->config['email_alerts']) {
            return;
        }

        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');

        $subject = "[{$site_name}] 🚨 WooCommerce Fraud Shield Alert";

        $message = "🚨 ALERTE SÉCURITÉ YOYAKU.IO\n\n";

        if (isset($data['type']) && $data['type'] === 'honeypot_detection') {
            $message .= "DÉTECTION HONEYPOT CONFIRMÉE !\n";
            $message .= "Produit piège détecté: {$data['product_id']}\n";
            $message .= "Quantité: {$data['quantity']}\n";
            $message .= "Frais de sécurité appliqués: {$data['security_fee']}€\n\n";
        } else {
            $message .= "Commande à haut risque détectée\n";
            $message .= "Score de risque: {$data['risk_analysis']['score']}/100\n";
            $message .= "Niveau: {$data['risk_analysis']['risk_level']}\n";
            $message .= "Facteurs: " . implode(', ', $data['risk_analysis']['factors']) . "\n\n";
        }

        $message .= "Détails techniques:\n";
        $message .= "IP: {$data['ip']}\n";
        $message .= "User Agent: " . substr($data['user_agent'], 0, 100) . "\n";
        $message .= "Timestamp: {$data['timestamp']}\n\n";

        $message .= "Action recommandée: Vérifiez manuellement cette activité dans l'admin WooCommerce.\n\n";
        $message .= "---\n";
        $message .= "WooCommerce Fraud Shield v" . WFS_VERSION . " - yoyaku.io";

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Charger la configuration
     */
    private function load_config() {
        $this->config = array_merge($this->config, [
            'enabled' => get_option('wfs_enabled', false),
            'alert_threshold' => get_option('wfs_alert_threshold', 60),
            'auto_security_fee' => get_option('wfs_auto_security_fee', true),
            'email_alerts' => get_option('wfs_email_alerts', true),
            'log_all_attempts' => get_option('wfs_log_all_attempts', true)
        ]);
    }

    /**
     * Sauvegarder la configuration
     */
    private function save_config() {
        update_option('wfs_enabled', $this->config['enabled']);
        update_option('wfs_alert_threshold', $this->config['alert_threshold']);
        update_option('wfs_auto_security_fee', $this->config['auto_security_fee']);
        update_option('wfs_email_alerts', $this->config['email_alerts']);
        update_option('wfs_log_all_attempts', $this->config['log_all_attempts']);
    }

    /**
     * Charger les statistiques
     */
    private function load_stats() {
        $this->stats = array_merge($this->stats, get_option('wfs_stats', []));
    }

    /**
     * Sauvegarder les statistiques
     */
    private function save_stats() {
        update_option('wfs_stats', $this->stats);
    }

    /**
     * Ajouter le menu admin
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Fraud Shield', 'woocommerce-fraud-shield'),
            __('Fraud Shield', 'woocommerce-fraud-shield'),
            'manage_woocommerce',
            'woocommerce-fraud-shield',
            [$this, 'admin_page']
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'woocommerce-fraud-shield') === false) {
            return;
        }

        wp_enqueue_script('wfs-admin', WFS_PLUGIN_URL . 'assets/admin.js', ['jquery'], WFS_VERSION, true);
        wp_enqueue_style('wfs-admin', WFS_PLUGIN_URL . 'assets/admin.css', [], WFS_VERSION);

        wp_localize_script('wfs-admin', 'wfs_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wfs_admin_nonce')
        ]);
    }

    /**
     * Page d'administration
     */
    public function admin_page() {
        // Traitement du formulaire
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'wfs_settings')) {
            $this->config['enabled'] = isset($_POST['enabled']);
            $this->config['alert_threshold'] = intval($_POST['alert_threshold']);
            $this->config['auto_security_fee'] = isset($_POST['auto_security_fee']);
            $this->config['email_alerts'] = isset($_POST['email_alerts']);
            $this->config['log_all_attempts'] = isset($_POST['log_all_attempts']);

            $this->save_config();

            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'woocommerce-fraud-shield') . '</p></div>';
        }

        // Recharger la config
        $this->load_config();
        $this->load_stats();

        include WFS_PLUGIN_PATH . 'templates/admin-page.php';
    }

    /**
     * AJAX: Obtenir les statistiques en temps réel
     */
    public function ajax_get_live_stats() {
        check_ajax_referer('wfs_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'woocommerce-fraud-shield'));
        }

        $this->load_stats();

        // Statistiques du mois en cours
        $monthly_stats = $this->get_monthly_stats();

        wp_send_json_success([
            'stats' => $this->stats,
            'monthly' => $monthly_stats,
            'timestamp' => current_time('mysql')
        ]);
    }

    /**
     * AJAX: Nettoyer les logs
     */
    public function ajax_clear_logs() {
        check_ajax_referer('wfs_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'woocommerce-fraud-shield'));
        }

        $log_type = sanitize_text_field($_POST['log_type'] ?? 'all');
        $cleared = $this->clear_logs($log_type);

        wp_send_json_success(['message' => "Cleared {$cleared} log files."]);
    }

    /**
     * Obtenir les statistiques mensuelles
     *
     * @return array
     */
    private function get_monthly_stats() {
        $log_files = glob(WFS_LOG_DIR . '*-' . date('Y-m') . '.log');
        $stats = [
            'total_events' => 0,
            'honeypot_detections' => 0,
            'high_risk_alerts' => 0,
            'order_analyses' => 0
        ];

        foreach ($log_files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            $basename = basename($file, '.log');
            $type = substr($basename, 0, strrpos($basename, '-'));

            switch ($type) {
                case 'honeypot':
                    $stats['honeypot_detections'] += count($lines);
                    break;
                case 'high_risk_alert':
                    $stats['high_risk_alerts'] += count($lines);
                    break;
                case 'order_analysis':
                    $stats['order_analyses'] += count($lines);
                    break;
            }

            $stats['total_events'] += count($lines);
        }

        return $stats;
    }

    /**
     * Nettoyer les logs
     *
     * @param string $type
     * @return int
     */
    private function clear_logs($type = 'all') {
        $pattern = ($type === 'all') ? WFS_LOG_DIR . '*.log' : WFS_LOG_DIR . $type . '-*.log';
        $files = glob($pattern);
        $cleared = 0;

        foreach ($files as $file) {
            if (unlink($file)) {
                $cleared++;
            }
        }

        return $cleared;
    }

    /**
     * Charger les traductions
     */
    public function load_textdomain() {
        load_plugin_textdomain('woocommerce-fraud-shield', false, dirname(plugin_basename(WFS_PLUGIN_FILE)) . '/languages');
    }

    /**
     * Activation du plugin
     */
    public function activate() {
        // Créer les tables/options nécessaires
        $this->create_directories();

        // Valeurs par défaut
        add_option('wfs_enabled', false);
        add_option('wfs_alert_threshold', 60);
        add_option('wfs_auto_security_fee', true);
        add_option('wfs_email_alerts', true);
        add_option('wfs_log_all_attempts', true);
        add_option('wfs_stats', [
            'honeypot_detections' => 0,
            'security_fees_applied' => 0,
            'total_amount_protected' => 0,
            'alerts_sent' => 0
        ]);

        // Log de l'activation
        $this->log_event([
            'event' => 'plugin_activated',
            'version' => WFS_VERSION,
            'timestamp' => current_time('mysql')
        ], 'system');
    }

    /**
     * Désactivation du plugin
     */
    public function deactivate() {
        // Log de la désactivation
        $this->log_event([
            'event' => 'plugin_deactivated',
            'version' => WFS_VERSION,
            'timestamp' => current_time('mysql')
        ], 'system');

        // Note: Ne pas supprimer les options/logs pour permettre la réactivation
    }
}

// Vérifier les prérequis et initialiser
add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce')) {
        WooCommerce_Fraud_Shield::get_instance();
    } else {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>' . __('WooCommerce Fraud Shield', 'woocommerce-fraud-shield') . ':</strong> ' . __('WooCommerce must be installed and activated.', 'woocommerce-fraud-shield') . '</p></div>';
        });
    }
});

// Hook d'installation pour les futures mises à jour
register_activation_hook(WFS_PLUGIN_FILE, [WooCommerce_Fraud_Shield::class, 'activate']);
register_deactivation_hook(WFS_PLUGIN_FILE, [WooCommerce_Fraud_Shield::class, 'deactivate']);