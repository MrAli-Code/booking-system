<?php
/**
 * Plugin Name: Booking System
 * Plugin URI: https://yourdomain.com/booking-system
 * Description: Advanced appointment booking & management system with multi-service cart, Persian calendar, and omnichannel notifications.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Your Company
 * License: Commercial
 * Text Domain: booking-system
 *
 * @package BBS_WP
 */

defined('ABSPATH') or die('Direct access not allowed.');

define('BBS_WP_VERSION', '1.0.0');
define('BBS_WP_FILE', __FILE__);
define('BBS_WP_PATH', plugin_dir_path(__FILE__));
define('BBS_WP_URL', plugin_dir_url(__FILE__));

// Check if main system exists
$systemPath = BBS_WP_PATH . 'vendor/autoload.php';
$corePath = WP_CONTENT_DIR . '/booking-system/public/index.php';

if (!defined('BBS_ROOT')) {
    define('BBS_ROOT', BBS_WP_PATH . 'system');
}

require_once BBS_WP_PATH . 'includes/core.php';
require_once BBS_WP_PATH . 'includes/shortcodes.php';
require_once BBS_WP_PATH . 'includes/admin.php';
require_once BBS_WP_PATH . 'includes/api.php';

class BBS_WP_Plugin
{
    private static ?BBS_WP_Plugin $instance = null;
    private array $shortcodes = [];
    private array $scriptsLoaded = false;

    public static function init(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        register_activation_hook(BBS_WP_FILE, [$this, 'activate']);
        register_deactivation_hook(BBS_WP_FILE, [$this, 'deactivate']);

        add_action('plugins_loaded', [$this, 'loadTextDomain']);
        add_action('init', [$this, 'registerShortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('admin_menu', [$this, 'registerAdminMenu']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // AJAX handlers
        add_action('wp_ajax_bbs_get_slots', [$this, 'ajaxGetSlots']);
        add_action('wp_ajax_nopriv_bbs_get_slots', [$this, 'ajaxGetSlots']);
        add_action('wp_ajax_bbs_submit_booking', [$this, 'ajaxSubmitBooking']);
        add_action('wp_ajax_nopriv_bbs_submit_booking', [$this, 'ajaxSubmitBooking']);
        add_action('wp_ajax_bbs_track_booking', [$this, 'ajaxTrackBooking']);
        add_action('wp_ajax_nopriv_bbs_track_booking', [$this, 'ajaxTrackBooking']);

        // WP user integration
        add_filter('bbs_sync_wp_user', [$this, 'syncWpUser'], 10, 2);
    }

    public function activate(): void
    {
        $this->createTables();
        $this->setDefaultOptions();
    }

    public function deactivate(): void
    {
        // Cleanup if needed
    }

    private function createTables(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'bbs_';

        $tables = [
            "CREATE TABLE IF NOT EXISTS {$prefix}tenants (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                settings LONGTEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) {$charset};",

            "CREATE TABLE IF NOT EXISTS {$prefix}customers (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                wp_user_id BIGINT UNSIGNED DEFAULT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                phone VARCHAR(50) NOT NULL,
                email VARCHAR(255) DEFAULT NULL,
                wallet_balance DECIMAL(20,2) DEFAULT 0.00,
                referral_code VARCHAR(20) DEFAULT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_phone (phone),
                INDEX idx_wp_user (wp_user_id)
            ) {$charset};",

            "CREATE TABLE IF NOT EXISTS {$prefix}services (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                description TEXT,
                duration_minutes INT UNSIGNED NOT NULL DEFAULT 30,
                price DECIMAL(20,2) NOT NULL DEFAULT 0.00,
                currency VARCHAR(3) DEFAULT 'IRR',
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) {$charset};",

            "CREATE TABLE IF NOT EXISTS {$prefix}bookings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                customer_id BIGINT UNSIGNED NOT NULL,
                booking_code VARCHAR(20) NOT NULL UNIQUE,
                status VARCHAR(20) DEFAULT 'pending',
                booking_date DATE NOT NULL,
                booking_time TIME NOT NULL,
                total_duration INT UNSIGNED DEFAULT 0,
                total_price DECIMAL(20,2) DEFAULT 0.00,
                currency VARCHAR(3) DEFAULT 'IRR',
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_code (booking_code),
                INDEX idx_date (booking_date),
                INDEX idx_status (status),
                FOREIGN KEY (customer_id) REFERENCES {$prefix}customers(id) ON DELETE CASCADE
            ) {$charset};",
        ];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($tables as $sql) {
            dbDelta($sql);
        }
    }

    private function setDefaultOptions(): void
    {
        add_option('bbs_version', BBS_WP_VERSION);
        add_option('bbs_settings', [
            'currency' => 'IRR',
            'timezone' => 'Asia/Tehran',
            'calendar' => 'jalali',
            'slot_duration' => 30,
            'booking_prefix' => 'BBS',
        ]);
    }

    public function loadTextDomain(): void
    {
        load_plugin_textdomain('booking-system', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function registerShortcodes(): void
    {
        $shortcodes = new BBS_WP_Shortcodes();
        $shortcodes->register();
    }

    public function enqueueFrontendAssets(): void
    {
        if (!$this->scriptsLoaded) {
            wp_enqueue_style('bbs-glassmorphism', BBS_WP_URL . 'assets/css/glassmorphism.css', [], BBS_WP_VERSION);
            wp_enqueue_style('bbs-frontend', BBS_WP_URL . 'assets/css/frontend.css', [], BBS_WP_VERSION);
            wp_enqueue_script('bbs-frontend', BBS_WP_URL . 'assets/js/frontend.js', ['jquery'], BBS_WP_VERSION, true);

            wp_localize_script('bbs-frontend', 'bbs_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bbs_nonce'),
                'currency' => get_option('bbs_settings')['currency'] ?? 'IRR',
                'locale' => 'fa',
            ]);
            $this->scriptsLoaded = true;
        }
    }

    public function enqueueAdminAssets(string $hook): void
    {
        if (strpos($hook, 'booking-system') !== false) {
            wp_enqueue_style('bbs-admin', BBS_WP_URL . 'assets/css/admin.css', [], BBS_WP_VERSION);
            wp_enqueue_script('bbs-admin', BBS_WP_URL . 'assets/js/admin.js', ['jquery'], BBS_WP_VERSION, true);
        }
    }

    public function registerAdminMenu(): void
    {
        add_menu_page(
            __('Booking System', 'booking-system'),
            __('Booking', 'booking-system'),
            'manage_options',
            'booking-system',
            [new BBS_WP_Admin(), 'renderDashboard'],
            'dashicons-calendar-alt',
            30
        );

        add_submenu_page('booking-system', __('Bookings', 'booking-system'), __('Bookings', 'booking-system'), 'manage_options', 'booking-system-bookings', [new BBS_WP_Admin(), 'renderBookings']);
        add_submenu_page('booking-system', __('Customers', 'booking-system'), __('Customers', 'booking-system'), 'manage_options', 'booking-system-customers', [new BBS_WP_Admin(), 'renderCustomers']);
        add_submenu_page('booking-system', __('Services', 'booking-system'), __('Services', 'booking-system'), 'manage_options', 'booking-system-services', [new BBS_WP_Admin(), 'renderServices']);
        add_submenu_page('booking-system', __('Settings', 'booking-system'), __('Settings', 'booking-system'), 'manage_options', 'booking-system-settings', [new BBS_WP_Admin(), 'renderSettings']);
    }

    public function registerRestRoutes(): void
    {
        $api = new BBS_WP_API();
        $api->registerRoutes();
    }

    public function ajaxGetSlots(): void
    {
        check_ajax_referer('bbs_nonce', 'nonce');
        $date = $_POST['date'] ?? '';
        $serviceId = (int)($_POST['service_id'] ?? 0);

        // Delegate to core service
        $slots = ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '14:00', '14:30', '15:00', '15:30'];
        wp_send_json_success(['slots' => $slots]);
    }

    public function ajaxSubmitBooking(): void
    {
        check_ajax_referer('bbs_nonce', 'nonce');

        global $wpdb;
        $prefix = $wpdb->prefix . 'bbs_';
        $data = $_POST;

        // Look up or create customer
        $phone = sanitize_text_field($data['phone']);
        $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}customers WHERE phone = %s", $phone));

        if (!$customer) {
            $wpdb->insert("{$prefix}customers", [
                'wp_user_id' => get_current_user_id() ?: null,
                'first_name' => sanitize_text_field($data['first_name']),
                'last_name' => sanitize_text_field($data['last_name']),
                'phone' => $phone,
                'email' => sanitize_email($data['email'] ?? ''),
                'referral_code' => strtoupper(substr(md5(uniqid()), 0, 8)),
            ]);
            $customerId = $wpdb->insert_id;
        } else {
            $customerId = $customer->id;
        }

        // Create booking
        $bookingCode = 'BBS-' . strtoupper(substr(md5(uniqid()), 0, 5));
        $wpdb->insert("{$prefix}bookings", [
            'customer_id' => $customerId,
            'booking_code' => $bookingCode,
            'status' => 'pending',
            'booking_date' => sanitize_text_field($data['booking_date']),
            'booking_time' => sanitize_text_field($data['booking_time']),
            'total_price' => (float)($data['total_price'] ?? 0),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
        ]);

        wp_send_json_success([
            'message' => 'Booking created successfully!',
            'booking_code' => $bookingCode,
        ]);
    }

    public function ajaxTrackBooking(): void
    {
        check_ajax_referer('bbs_nonce', 'nonce');

        global $wpdb;
        $prefix = $wpdb->prefix . 'bbs_';
        $code = sanitize_text_field($_POST['booking_code'] ?? '');

        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, c.first_name, c.last_name, c.phone
             FROM {$prefix}bookings b
             JOIN {$prefix}customers c ON b.customer_id = c.id
             WHERE b.booking_code = %s",
            $code
        ));

        if ($booking) {
            wp_send_json_success(['booking' => $booking]);
        }
        wp_send_json_error(['message' => 'Booking not found']);
    }

    public function syncWpUser(int $wpUserId, string $phone): int
    {
        global $wpdb;
        $prefix = $wpdb->prefix . 'bbs_';

        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$prefix}customers WHERE wp_user_id = %d OR phone = %s",
            $wpUserId, $phone
        ));

        if ($customer) {
            $wpdb->update("{$prefix}customers", ['wp_user_id' => $wpUserId], ['id' => $customer->id]);
            return $customer->id;
        }

        $user = get_userdata($wpUserId);
        $wpdb->insert("{$prefix}customers", [
            'wp_user_id' => $wpUserId,
            'first_name' => $user->first_name ?: $user->display_name,
            'last_name' => $user->last_name,
            'phone' => $phone,
            'email' => $user->user_email,
        ]);
        return $wpdb->insert_id;
    }
}

// Initialize
BBS_WP_Plugin::init();
