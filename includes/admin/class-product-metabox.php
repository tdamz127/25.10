<?php
namespace Prepaid_Pricing\Admin;

use Prepaid_Pricing\Core\Rates;
use Prepaid_Pricing\Core\Data;

/**
 * Lớp quản lý meta box cho sản phẩm
 */
class Product_Metabox {

    private $data;
    private $rates;

    public function __construct() {
        $this->data = new Data();
        $this->rates = new Rates();
        
        // Thêm meta box mới cho trang sản phẩm
        add_action('add_meta_boxes', array($this, 'add_prepaid_pricing_meta_box'));
        
        // Lưu dữ liệu sản phẩm khi lưu sản phẩm
        add_action('woocommerce_process_product_meta', array($this, 'save_prepaid_pricing_product_data'));
        
        // Đăng ký scripts và styles cho trang sản phẩm admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_product_scripts'));
    }

    /**
     * Thêm meta box Prepaid Pricing 
     */
    public function add_prepaid_pricing_meta_box($post_type) {
        if ($post_type == 'product') {
            add_meta_box(
                'prepaid_pricing_meta_box',
                __('Prepaid Pricing - Quản lý Regions', 'prepaid-pricing'),
                array($this, 'render_prepaid_pricing_meta_box'),
                'product',
                'normal',
                'default'
            );
        }
    }

    /**
     * Render nội dung meta box
     */
    public function render_prepaid_pricing_meta_box($post) {
        // Lấy dữ liệu đã lưu
        $product_id = $post->ID;
        $enabled = $this->data->product_has_prepaid_pricing($product_id);
        $regions = $this->data->get_product_regions($product_id);
        
        // Lấy danh sách cặp tỷ giá từ database
        $rates_data = $this->rates->get_saved_rates();
        $rate_pairs = array();
        
        // Kiểm tra rates_data có dữ liệu không và tạo danh sách các cặp tỷ giá
        if (isset($rates_data['rates']) && is_array($rates_data['rates'])) {
            $rate_pairs = array_keys($rates_data['rates']);
        }
        
        // Lấy cài đặt toàn cục cho Y và Z
        $global_settings = Settings::get_global_settings();

        // Thêm nonce field
        wp_nonce_field('prepaid_pricing_save_data', 'prepaid_pricing_nonce');
        
        // Load template
        include PREPAID_PRICING_PLUGIN_DIR . 'includes/admin/views/product-metabox.php';
    }

    /**
     * Lưu dữ liệu Prepaid Pricing
     */
    public function save_prepaid_pricing_product_data($post_id) {
        // Kiểm tra nonce
        if (!isset($_POST['prepaid_pricing_nonce']) || !wp_verify_nonce($_POST['prepaid_pricing_nonce'], 'prepaid_pricing_save_data')) {
            return;
        }

        // Kiểm tra autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Kiểm tra quyền
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Lưu trạng thái bật/tắt
        $enabled = isset($_POST['prepaid_pricing_enabled']) ? true : false;
        
        // Lưu source URL
        $source_url = '';
        if (isset($_POST['prepaid_pricing_source_url'])) {
            $source_url = esc_url_raw($_POST['prepaid_pricing_source_url']);
        }
        update_post_meta($post_id, '_prepaid_pricing_source_url', $source_url);
        
        // Xử lý dữ liệu regions
        $regions = array();
        if (isset($_POST['prepaid_pricing_region_name'])) {
            $region_names = $_POST['prepaid_pricing_region_name'];
            $region_currencies = $_POST['prepaid_pricing_region_currency'];
            $face_value_modes = $_POST['prepaid_pricing_face_value_mode'];
            $region_face_values = $_POST['prepaid_pricing_region_face_values'];
            $face_value_mins = $_POST['prepaid_pricing_face_value_min'];
            $face_value_maxs = $_POST['prepaid_pricing_face_value_max'];
            $face_value_steps = $_POST['prepaid_pricing_face_value_step'];
            $factor_xs = $_POST['prepaid_pricing_region_factor_x'];
            $rate_pairs = $_POST['prepaid_pricing_region_rate_pair'];
            $factor_ys = $_POST['prepaid_pricing_region_factor_y'];
            $factor_zs = $_POST['prepaid_pricing_region_factor_z'];
            $stock_statuses = $_POST['prepaid_pricing_region_stock_status'];
            
            for ($i = 0; $i < count($region_names); $i++) {
                if (empty($region_names[$i])) {
                    continue;
                }
                
                $face_value_mode = sanitize_text_field($face_value_modes[$i]);
                
                $region = array(
                    'name' => sanitize_text_field($region_names[$i]),
                    'currency' => sanitize_text_field($region_currencies[$i]),
                    'face_value_mode' => $face_value_mode,
                    // Lưu với độ chính xác 5 chữ số thập phân cho factor_x
                    'factor_x' => (float) number_format((float) $factor_xs[$i], 5, '.', ''),
                    'rate_pair' => sanitize_text_field($rate_pairs[$i]),
                    'stock_status' => sanitize_text_field($stock_statuses[$i])
                );
                
                // Xử lý Y và Z - cho phép giá trị rỗng để sử dụng giá trị toàn cục
                if (isset($factor_ys[$i]) && $factor_ys[$i] !== '') {
                    $region['factor_y'] = (float) $factor_ys[$i];
                } else {
                    $region['factor_y'] = ''; // Giá trị rỗng để sử dụng toàn cục
                }
                
                if (isset($factor_zs[$i]) && $factor_zs[$i] !== '') {
                    $region['factor_z'] = (float) $factor_zs[$i];
                } else {
                    $region['factor_z'] = ''; // Giá trị rỗng để sử dụng toàn cục
                }
                
                if ($face_value_mode === 'list') {
                    // Xử lý face values dưới dạng danh sách
                    $face_values = array_map('trim', explode(',', $region_face_values[$i]));
                    $face_values = array_map('floatval', $face_values);
                    $region['face_values'] = array_filter($face_values);
                } else {
                    // Xử lý face values dưới dạng custom range
                    $region['face_value_min'] = (float) $face_value_mins[$i];
                    $region['face_value_max'] = (float) $face_value_maxs[$i];
                    $region['face_value_step'] = (float) $face_value_steps[$i];
                }
                
                $regions[] = $region;
            }
        }
        
        // Lưu dữ liệu
        update_post_meta($post_id, '_prepaid_pricing_enabled', $enabled ? 1 : 0);
        update_post_meta($post_id, '_prepaid_pricing_regions', $regions);
    }

    /**
     * Đăng ký scripts và styles cho trang sản phẩm admin
     */
    public function enqueue_admin_product_scripts($hook) {
        global $post_type;
        
        if (($hook == 'post.php' || $hook == 'post-new.php') && $post_type == 'product') {
            // CSS cho trang sản phẩm
            wp_enqueue_style(
                'prepaid-pricing-product-css',
                PREPAID_PRICING_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                PREPAID_PRICING_VERSION . '.' . time() // Thêm timestamp để tránh cache
            );

            // JavaScript cho trang sản phẩm
            wp_enqueue_script(
                'prepaid-pricing-product-js',
                PREPAID_PRICING_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery', 'wp-util'),
                PREPAID_PRICING_VERSION . '.' . time(), // Thêm timestamp để tránh cache
                true
            );
            
            // Lấy cài đặt toàn cục
            $global_settings = Settings::get_global_settings();
            
            // Lấy danh sách cặp tỷ giá từ database
            $rates_data = $this->rates->get_saved_rates();
            $rate_pairs = array();
            
            if (isset($rates_data['rates']) && is_array($rates_data['rates'])) {
                $rate_pairs = array_keys($rates_data['rates']);
            }

            // Truyền dữ liệu tỷ giá và cài đặt toàn cục cho JavaScript
            wp_localize_script('prepaid-pricing-product-js', 'prepaid_pricing_product', array(
                'i18n' => array(
                    'region' => __('Region', 'prepaid-pricing'),
                    'add_region' => __('Thêm Region mới', 'prepaid-pricing'),
                    'remove' => __('Xóa', 'prepaid-pricing'),
                    'choose_region' => __('Chọn Region', 'prepaid-pricing'),
                    'face_value' => __('Mệnh giá', 'prepaid-pricing'),
                    'calculated_price' => __('Giá dự tính', 'prepaid-pricing'),
                    'hide_prices' => __('Ẩn giá', 'prepaid-pricing'),
                    'show_prices' => __('Xem giá', 'prepaid-pricing'),
                    'custom_face_value' => __('Mệnh giá tùy chỉnh', 'prepaid-pricing'),
                    'min_value' => __('Giá trị tối thiểu', 'prepaid-pricing'),
                    'max_value' => __('Giá trị tối đa', 'prepaid-pricing'),
                    'step_value' => __('Bước nhảy', 'prepaid-pricing'),
                    'select_rate_pair' => __('Chọn cặp tỷ giá', 'prepaid-pricing')
                ),
                'rates' => $rates_data['rates'] ?? array(),
                'rate_pairs' => $rate_pairs,
                'global_settings' => $global_settings
            ));
        }
    }
}