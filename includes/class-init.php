<?php
namespace Prepaid_Pricing;

/**
 * Lớp khởi tạo plugin
 */
class Init {

    /**
     * Khởi tạo plugin
     */
    public function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load các file phụ thuộc
     */
    private function load_dependencies() {
        // Core
        require_once PREPAID_PRICING_PLUGIN_DIR . 'includes/core/class-rates.php';
        require_once PREPAID_PRICING_PLUGIN_DIR . 'includes/core/class-notices.php';
        require_once PREPAID_PRICING_PLUGIN_DIR . 'includes/core/class-data.php';
        
        // Load Settings class ở cả frontend và admin
        require_once PREPAID_PRICING_PLUGIN_DIR . 'includes/admin/class-settings.php';
        
        // Admin
        if (is_admin()) {
            require_once PREPAID_PRICING_PLUGIN_DIR . 'includes/admin/class-admin.php';
            require_once PREPAID_PRICING_PLUGIN_DIR . 'includes/admin/class-product-metabox.php';
        }
        
        // Frontend
        if (!is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            require_once PREPAID_PRICING_PLUGIN_DIR . 'includes/frontend/class-frontend.php';
        }
    }

    /**
     * Khởi tạo các hooks
     */
    private function init_hooks() {
        // Khởi tạo các lớp
        new Core\Rates();
        new Core\Notices();
        new Core\Data();
        
        // Admin
        if (is_admin()) {
            new Admin\Admin();
            new Admin\Product_Metabox();
            new Admin\Settings(); // Chỉ khởi tạo Settings trong admin
        }
        
        // Frontend
        if (!is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            new Frontend\Frontend();
        }
    }
}