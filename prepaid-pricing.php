<?php
/**
 * Plugin Name: Prepaid Pricing for WooCommerce
 * Plugin URI: 
 * Description: Quản lý giá prepaid với tỷ giá hối đoái trực tiếp
 * Version: 1.0.0
 * Author: 
 * Author URI: 
 * Text Domain: prepaid-pricing
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 * WC tested up to: 8.3
 * 
 * @package Prepaid_Pricing
 */

// Thoát nếu truy cập trực tiếp
if (!defined('ABSPATH')) {
    exit;
}

// Định nghĩa các hằng số plugin
define('PREPAID_PRICING_VERSION', '1.0.0');
define('PREPAID_PRICING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PREPAID_PRICING_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PREPAID_PRICING_PLUGIN_FILE', __FILE__);
define('PREPAID_PRICING_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Khai báo tương thích với HPOS
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Autoloader cho các class trong plugin
spl_autoload_register(function ($class_name) {
    // Chỉ xử lý các class trong namespace của plugin
    if (strpos($class_name, 'Prepaid_Pricing') !== 0) {
        return;
    }
    
    // Chuyển namespace thành đường dẫn file
    $class_file = str_replace('Prepaid_Pricing\\', '', $class_name);
    $class_file = str_replace('_', '-', $class_file);
    $class_file = strtolower($class_file);
    $class_file = 'includes/' . str_replace('\\', '/', $class_file) . '.php';
    
    // Kiểm tra và load file
    if (file_exists(PREPAID_PRICING_PLUGIN_DIR . $class_file)) {
        require_once PREPAID_PRICING_PLUGIN_DIR . $class_file;
    }
});

// Khởi tạo plugin
function prepaid_pricing_init() {
    // Kiểm tra WooCommerce
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            ?>
            <div class="error">
                <p><?php _e('Prepaid Pricing yêu cầu WooCommerce phải được cài đặt và kích hoạt.', 'prepaid-pricing'); ?></p>
            </div>
            <?php
        });
        return;
    }
    
    // Load ngôn ngữ
    load_plugin_textdomain('prepaid-pricing', false, basename(dirname(__FILE__)) . '/languages');
    
    // Khởi tạo plugin
    require_once PREPAID_PRICING_PLUGIN_DIR . 'includes/class-init.php';
    new Prepaid_Pricing\Init();
}
add_action('plugins_loaded', 'prepaid_pricing_init');

// Đăng ký hook kích hoạt
register_activation_hook(__FILE__, 'prepaid_pricing_activate');
function prepaid_pricing_activate() {
    // Lên lịch cho cron event
    if (!wp_next_scheduled('prepaid_pricing_update_rates')) {
        wp_schedule_event(time(), 'thirty_minutes', 'prepaid_pricing_update_rates');
    }
    
    // Tạo lịch cron tùy chỉnh
    add_filter('cron_schedules', 'prepaid_pricing_add_cron_interval');
}

// Thêm lịch cron tùy chỉnh
function prepaid_pricing_add_cron_interval($schedules) {
    $schedules['thirty_minutes'] = array(
        'interval' => 1800, // 30 phút tính bằng giây
        'display'  => __('Mỗi 30 Phút', 'prepaid-pricing')
    );
    return $schedules;
}

// Đăng ký hook hủy kích hoạt
register_deactivation_hook(__FILE__, 'prepaid_pricing_deactivate');
function prepaid_pricing_deactivate() {
    // Xóa sự kiện đã lên lịch
    wp_clear_scheduled_hook('prepaid_pricing_update_rates');
}