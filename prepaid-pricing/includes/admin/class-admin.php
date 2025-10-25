<?php
namespace Prepaid_Pricing\Admin;

use Prepaid_Pricing\Core\Rates;

/**
 * Lớp quản lý admin
 */
class Admin {
    
    private $settings;

    public function __construct() {
        $this->settings = new Settings();
        
        // Thêm menu
        add_action('admin_menu', array($this, 'add_admin_menu'), 99);
        
        // Đăng ký scripts và styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Thêm AJAX action để cập nhật tỷ giá thủ công
        add_action('wp_ajax_prepaid_pricing_update_rates_manually', array($this, 'update_rates_manually'));
    }

    /**
     * Thêm mục menu vào menu WooCommerce
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Prepaid Pricing', 'prepaid-pricing'),
            __('Prepaid Pricing', 'prepaid-pricing'),
            'manage_woocommerce',
            'prepaid-pricing',
            array($this, 'display_admin_page')
        );
    }

    /**
     * Đăng ký scripts và styles cho admin
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook != 'woocommerce_page_prepaid-pricing') {
            return;
        }

        wp_enqueue_style(
            'prepaid-pricing-admin-css',
            PREPAID_PRICING_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            PREPAID_PRICING_VERSION
        );

        wp_enqueue_script(
            'prepaid-pricing-admin-js',
            PREPAID_PRICING_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            PREPAID_PRICING_VERSION,
            true
        );

        wp_localize_script('prepaid-pricing-admin-js', 'prepaid_pricing_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('prepaid-pricing-nonce'),
            'i18n'     => array(
                'updating' => __('Đang cập nhật tỷ giá...', 'prepaid-pricing'),
                'updated'  => __('Cập nhật tỷ giá thành công!', 'prepaid-pricing'),
                'error'    => __('Lỗi khi cập nhật tỷ giá.', 'prepaid-pricing'),
                'update_rates' => __('Cập nhật tỷ giá thủ công', 'prepaid-pricing')
            )
        ));
    }

    /**
     * Hiển thị trang admin dashboard
     */
    public function display_admin_page() {
        // Lấy tỷ giá
        $rates_manager = new Rates();
        $rates_data = $rates_manager->get_saved_rates();
        
        // Thêm tiêu đề
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Bảng điều khiển Prepaid Pricing', 'prepaid-pricing') . '</h1>';

        // 1. Phần cài đặt Y và Z toàn cục
        $this->settings->render_settings_form();
        
        // 2. Phần tỷ giá
        require_once PREPAID_PRICING_PLUGIN_DIR . 'includes/admin/views/rates-dashboard.php';
        
        echo '</div>';
    }

    /**
     * Xử lý AJAX cho việc cập nhật tỷ giá thủ công
     */
    public function update_rates_manually() {
        // Kiểm tra nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'prepaid-pricing-nonce')) {
            wp_send_json_error(array(
                'message' => __('Kiểm tra bảo mật thất bại.', 'prepaid-pricing')
            ));
        }

        // Kiểm tra quyền người dùng
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array(
                'message' => __('Bạn không có quyền thực hiện thao tác này.', 'prepaid-pricing')
            ));
        }

        // Cập nhật tỷ giá
        $rates_manager = new Rates();
        $result = $rates_manager->fetch_and_save_rates();

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Cập nhật tỷ giá thành công!', 'prepaid-pricing'),
                'rates_data' => $rates_manager->get_saved_rates()
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Không thể cập nhật tỷ giá. Vui lòng thử lại.', 'prepaid-pricing')
            ));
        }
    }
}