<?php
namespace Prepaid_Pricing\Frontend;

use Prepaid_Pricing\Core\Rates;
use Prepaid_Pricing\Core\Data;
use Prepaid_Pricing\Admin\Settings;

/**
 * Lớp quản lý frontend
 */
class Frontend {

    private $data;
    private $rates;

    public function __construct() {
        $this->data = new Data();
        $this->rates = new Rates();
        
        // Đăng ký styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // FIX: Đảm bảo sản phẩm có thể mua được
        add_filter('woocommerce_is_purchasable', array($this, 'make_product_purchasable'), 10, 2);
        add_filter('woocommerce_product_supports', array($this, 'product_supports_ajax_add_to_cart'), 10, 3);
        
        // Xóa giá mặc định cho sản phẩm có Prepaid Pricing - PRIORITY THẤP ĐỂ CÓ THỂ BỊ GHI ĐÈ
        add_filter('woocommerce_product_get_price', array($this, 'hide_default_price'), 5, 2);
        add_filter('woocommerce_product_get_regular_price', array($this, 'hide_default_price'), 5, 2);
        
        // SỬA LỖI CHÍNH: Chỉ đăng ký ở một vị trí duy nhất
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        add_action('woocommerce_single_product_summary', array($this, 'add_region_selection'), 30);
        
        // QUAN TRỌNG: Loại bỏ các hook gây duplicate - đã comment để không gây hiểu lầm
        // add_action('woocommerce_before_add_to_cart_form', array($this, 'add_region_selection'), 5);
        // add_action('woocommerce_before_add_to_cart_button', array($this, 'add_region_selection'), 5);
        // add_action('woocommerce_before_single_product', array($this, 'add_region_selection'), 30);
        
        // Debug form
        add_action('woocommerce_after_single_product_summary', array($this, 'force_debug_form'), 5);
        
        // Loại bỏ thông báo "Add price"
        add_filter('woocommerce_get_price_html', array($this, 'maybe_show_region_price'), 10, 2);
        
        // Debug thông báo
        add_action('woocommerce_before_single_product', array($this, 'debug_add_to_cart_messages'), 10);
        
        // Thêm debug hook để hiển thị $_POST, $_GET và $_REQUEST
        add_action('wp_footer', array($this, 'debug_form_submissions'));
        
        // Xử lý thêm sản phẩm vào giỏ hàng
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_region_selection'), 10, 3);
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_region_data_to_cart'), 10, 3);
        
        // Hiển thị thông tin region trong giỏ hàng
        add_filter('woocommerce_get_item_data', array($this, 'display_region_in_cart'), 10, 2);
        
        // IMPORTANT: Apply prices at HIGHEST PRIORITY to ensure they're not overridden
        add_filter('woocommerce_product_get_price', array($this, 'set_cart_item_price'), 999, 2);
        add_filter('woocommerce_product_get_regular_price', array($this, 'set_cart_item_price'), 999, 2);
        
        // Cart price calculation with backup methods
        add_action('woocommerce_before_calculate_totals', array($this, 'calculate_region_price'), 999, 1);
        add_filter('woocommerce_cart_item_price', array($this, 'cart_item_price'), 999, 3);
        add_filter('woocommerce_cart_item_subtotal', array($this, 'cart_item_subtotal'), 999, 3);
        
        // Lưu thông tin region vào đơn hàng
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_region_to_order_item'), 10, 4);
        
        // Xử lý chức năng "Buy Now" - chuyển thẳng đến trang checkout
        add_filter('woocommerce_add_to_cart_redirect', array($this, 'redirect_to_checkout'));
        
        // Force prices in checkout and order
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'update_order_item_price'), 20, 4);
        add_filter('woocommerce_order_item_product', array($this, 'update_order_item_product_price'), 10, 2);
        
        // Thêm JavaScript debug
        add_action('wp_footer', array($this, 'add_debug_javascript'), 99);
    }

    /**
     * FIX: Đảm bảo sản phẩm có thể mua được
     */
    public function make_product_purchasable($purchasable, $product) {
        // Nếu sản phẩm là prepaid pricing, luôn cho phép mua
        if ($this->data->product_has_prepaid_pricing($product->get_id())) {
            error_log('Prepaid Pricing: Making product purchasable: ' . $product->get_id());
            return true;
        }
        return $purchasable;
    }
    
    /**
     * FIX: Đảm bảo sản phẩm hỗ trợ thêm vào giỏ hàng qua AJAX
     */
    public function product_supports_ajax_add_to_cart($support, $feature, $product) {
        if ($feature === 'ajax_add_to_cart' && $this->data->product_has_prepaid_pricing($product->get_id())) {
            return true;
        }
        return $support;
    }

    /**
     * Đăng ký scripts và styles cho frontend
     */
    public function enqueue_frontend_scripts() {
        // Đăng ký CSS
        wp_enqueue_style(
            'prepaid-pricing-frontend',
            PREPAID_PRICING_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            PREPAID_PRICING_VERSION
        );

        // Đăng ký JS
        wp_enqueue_script(
            'accounting',
            PREPAID_PRICING_PLUGIN_URL . 'assets/js/accounting.min.js',
            array('jquery'),
            '0.4.2',
            true
        );

        wp_enqueue_script(
            'prepaid-pricing-frontend',
            PREPAID_PRICING_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery', 'accounting'),
            PREPAID_PRICING_VERSION,
            true
        );

        // Lấy các tỷ giá
        $rates_data = $this->rates->get_saved_rates();
        $rates = isset($rates_data['rates']) ? $rates_data['rates'] : array();
        
        // Lấy cài đặt toàn cục
        $global_settings = Settings::get_global_settings();

        // Truyền dữ liệu cho JS
        wp_localize_script('prepaid-pricing-frontend', 'prepaid_pricing_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'rates' => $rates,
            'global_settings' => $global_settings,
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'decimals' => wc_get_price_decimals(),
            'thousand_sep' => wc_get_price_thousand_separator(),
            'decimal_sep' => wc_get_price_decimal_separator(),
            'currency_format' => get_option('woocommerce_currency_pos'),
            'i18n' => array(
                'select_region' => __('Select region', 'prepaid-pricing'),
                'select_value' => __('Select value', 'prepaid-pricing') // Đã thay đổi từ 'Chọn mệnh giá' sang 'Select value'
            )
        ));
        
        // Thêm một đoạn script inline để debug
        wp_add_inline_script('prepaid-pricing-frontend-js', '
            console.log("Prepaid Pricing: Scripts loaded");
            jQuery(document).ready(function($) {
                console.log("Prepaid Pricing: DOM ready");
                console.log("Prepaid Pricing: Region selector exists:", $("#prepaid-pricing-region").length > 0);
                
                // Check how many forms we have
                const forms = $(".prepaid-pricing-region-selection form");
                console.log("Prepaid Pricing: Found " + forms.length + " forms on the page");
                
                // Add special class to the first form
                if (forms.length > 1) {
                    forms.each(function(index) {
                        $(this).addClass("pp-form-" + (index + 1));
                        $(this).prepend("<div style=\"color: red; font-weight: bold;\">Form #" + (index + 1) + "</div>");
                    });
                    
                    // Disable the second form to avoid confusion
                    $(".pp-form-2").find("select, input, button").prop("disabled", true);
                    
                    // Add warning message
                    $("body").prepend("<div style=\"background: #ff0000; color: white; padding: 10px; text-align: center;\">WARNING: Multiple Prepaid Pricing forms detected. Only the first form is active.</div>");
                }
            });
        ');
    }

    /**
     * Ẩn giá mặc định cho sản phẩm có Prepaid Pricing
     */
    public function hide_default_price($price, $product) {
        // FIX: Nếu sản phẩm có Prepaid Pricing, cho giá mặc định là 0 thay vì rỗng
        if ($this->data->product_has_prepaid_pricing($product->get_id())) {
            return 0;
        }
        return $price;
    }
    
    /**
     * NEW: Set cart item price at HIGHEST priority to override all others
     */
    public function set_cart_item_price($price, $product) {
        global $woocommerce;
        
        if (!$woocommerce || !$woocommerce->cart || !$product) {
            return $price;
        }
        
        if ($cart = $woocommerce->cart) {
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                if ($cart_item['data'] === $product || $cart_item['product_id'] === $product->get_id()) {
                    if (isset($cart_item['prepaid_pricing_region'])) {
                        $region = $cart_item['prepaid_pricing_region'];
                        $face_value = $region['face_value'];
                        
                        // Lấy Y và Z - nếu rỗng thì dùng giá trị toàn cục
                        $factor_y = $region['factor_y'];
                        $factor_z = $region['factor_z'];
                        
                        if ($factor_y === '' || $factor_y === null) {
                            $global_settings = Settings::get_global_settings();
                            $factor_y = $global_settings['global_factor_y'];
                        }
                        
                        if ($factor_z === '' || $factor_z === null) {
                            $global_settings = Settings::get_global_settings();
                            $factor_z = $global_settings['global_factor_z'];
                        }
                        
                        $rate = $this->rates->get_exchange_rate($region['rate_pair']);
                        
                        if ($rate) {
                            $calculated_price = ($face_value * $region['factor_x'] * $rate * (100 + (float)$factor_y) / 100) + (float)$factor_z;
                            error_log("Prepaid Pricing: Setting cart item price to {$calculated_price} (highest priority)");
                            return $calculated_price;
                        }
                    }
                }
            }
        }
        
        return $price;
    }

    /**
     * Hiển thị giá dựa trên region
     */
    public function maybe_show_region_price($price_html, $product) {
        if ($this->data->product_has_prepaid_pricing($product->get_id())) {
            $regions = $this->data->get_product_regions($product->get_id());
            
            if (!empty($regions)) {
                // Lấy giá thấp nhất và cao nhất từ tất cả region và mệnh giá
                $min_price = PHP_INT_MAX;
                $max_price = 0;
                
                foreach ($regions as $region) {
                    if ($region['stock_status'] === 'instock') {
                        // Xử lý khác nhau cho chế độ list và custom
                        $face_value_mode = isset($region['face_value_mode']) ? $region['face_value_mode'] : 'list';
                        
                        if ($face_value_mode === 'list' && !empty($region['face_values'])) {
                            // Chế độ list - lấy min/max từ danh sách giá trị
                            foreach ($region['face_values'] as $face_value) {
                                $region_price = $this->calculate_region_price_from_data($region, $face_value);
                                $min_price = min($min_price, $region_price);
                                $max_price = max($max_price, $region_price);
                            }
                        } elseif ($face_value_mode === 'custom') {
                            // Chế độ custom - lấy min/max từ min_value và max_value
                            $min_face_value = isset($region['face_value_min']) ? $region['face_value_min'] : 1;
                            $max_face_value = isset($region['face_value_max']) ? $region['face_value_max'] : 100;
                            
                            $min_price_for_region = $this->calculate_region_price_from_data($region, $min_face_value);
                            $max_price_for_region = $this->calculate_region_price_from_data($region, $max_face_value);
                            
                            $min_price = min($min_price, $min_price_for_region);
                            $max_price = max($max_price, $max_price_for_region);
                        }
                    }
                }
                
                if ($min_price !== PHP_INT_MAX) {
                    if ($min_price === $max_price) {
                        return wc_price($min_price);
                    } else {
                        return wc_price($min_price) . ' - ' . wc_price($max_price);
                    }
                }
            }
            
            // Nếu không có region nào, hiển thị thông báo chọn region
            return '<span class="select-region-notice">' . __('Select Value to see price', 'prepaid-pricing') . '</span>';
        }
        
        return $price_html;
    }

    /**
     * Hiển thị dropdown chọn region trên trang sản phẩm
     */
    public function add_region_selection() {
        global $product;
        
        if (!$product) {
            error_log('Prepaid Pricing: $product không tồn tại');
            return;
        }
        
        // Thêm debug log
        error_log('Prepaid Pricing: add_region_selection được gọi cho sản phẩm ID=' . $product->get_id());
        
        if ($this->data->product_has_prepaid_pricing($product->get_id())) {
            // Ngăn chặn form tiêu chuẩn của WooCommerce hiển thị - LƯU Ý: ĐẶT SAU ĐIỀU KIỆN
            remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
            
            $regions = $this->data->get_product_regions($product->get_id());
            
            // Thêm debug log
            error_log('Prepaid Pricing: Sản phẩm sử dụng Prepaid Pricing. Số lượng regions: ' . count($regions));
            
            if (!empty($regions)) {
                // Thêm debug log
                error_log('Prepaid Pricing: Bắt đầu render region selector');
                
                // Đảm bảo đường dẫn đến file template chính xác
                $template_path = PREPAID_PRICING_PLUGIN_DIR . 'includes/frontend/views/region-selector.php';
                error_log('Prepaid Pricing: Loading template from path: ' . $template_path);
                
                if (file_exists($template_path)) {
                    include $template_path;
                    error_log('Prepaid Pricing: Template loaded successfully');
                } else {
                    error_log('Prepaid Pricing: ERROR - Template file not found at: ' . $template_path);
                }
                
                // Thêm debug log
                error_log('Prepaid Pricing: Đã render xong region selector');
            } else {
                error_log('Prepaid Pricing: No regions found for product');
            }
        } else {
            error_log('Prepaid Pricing: Product does not have prepaid pricing enabled');
        }
    }
    
    /**
     * Debug thông báo khi thêm vào giỏ hàng
     */
    public function debug_add_to_cart_messages() {
        if (is_admin()) return;
        
        // Thông báo chỉ hiển thị cho admin
        if (current_user_can('manage_options')) {
            $notices = wc_get_notices();
            
            if (!empty($notices)) {
                error_log('WooCommerce Notices: ' . print_r($notices, true));
            }
            
            // Thêm notice debug
            if (isset($_REQUEST['add-to-cart'])) {
                echo '<div class="prepaid-pricing-debug" style="background: #f5f5f5; padding: 10px; margin: 10px 0; border: 1px dashed #ddd;">';
                echo '<h4>Debug Add to Cart:</h4>';
                echo '<pre>';
                echo 'Product ID: ' . esc_html($_REQUEST['add-to-cart']) . "\n";
                echo 'Region: ' . (isset($_REQUEST['prepaid_pricing_region']) ? esc_html($_REQUEST['prepaid_pricing_region']) : 'Not set') . "\n";
                echo 'Face Value: ' . (isset($_REQUEST['prepaid_pricing_face_value']) ? esc_html($_REQUEST['prepaid_pricing_face_value']) : 'Not set') . "\n";
                echo 'Quantity: ' . (isset($_REQUEST['quantity']) ? esc_html($_REQUEST['quantity']) : '1') . "\n";
                echo '</pre>';
                echo '</div>';
            }
        }
    }
    
    /**
     * Debug form force render để tìm ra vấn đề
     */
    public function force_debug_form() {
        if (!is_product()) {
            return;
        }
        
        global $product;
        
        if (!$product) {
            return;
        }
        
        echo '<div style="background: #f5f5f5; padding: 15px; margin: 15px 0; border: 1px solid #ddd;">';
        echo '<h3>Debug Form Check</h3>';
        echo '<p>Product ID: ' . $product->get_id() . '</p>';
        
        // Debug chi tiết về giá trị meta
        $enabled_value = get_post_meta($product->get_id(), '_prepaid_pricing_enabled', true);
        echo '<p>_prepaid_pricing_enabled raw value: ' . (is_bool($enabled_value) ? ($enabled_value ? 'true (bool)' : 'false (bool)') : 
             (is_numeric($enabled_value) ? $enabled_value . ' (numeric)' : 
             (empty($enabled_value) ? 'empty' : 'non-empty string: ' . $enabled_value))) . '</p>';
        
        echo '<p>Has Prepaid Pricing: ' . ($this->data->product_has_prepaid_pricing($product->get_id()) ? 'Yes' : 'No') . '</p>';
        
        $regions = $this->data->get_product_regions($product->get_id());
        echo '<p>Number of regions: ' . count($regions) . '</p>';
        
        // Thêm hack tạm thời để force hiển thị form nếu có regions
        if (!empty($regions) && !$this->data->product_has_prepaid_pricing($product->get_id())) {
            echo '<p style="color:red">WARNING: Regions exist but prepaid pricing is not enabled - trying to force display form</p>';
            
            // Force render form
            echo '<div style="border: 2px solid red; padding: 15px; margin: 15px 0;">';
            echo '<h3>FORCE RENDERED FORM:</h3>';
            $this->render_region_form($product, $regions);
            echo '</div>';
        }
        
        echo '<pre>';
        print_r(array_keys($regions));
        echo '</pre>';
        
        echo '</div>';
    }

    /**
     * Force render form để giải quyết tạm thời
     */
    private function render_region_form($product, $regions) {
        // Tạo một phiên bản đơn giản của region selector
        ?>
        <div class="prepaid-pricing-region-selection" style="border: 1px solid #eee; padding: 15px; margin: 15px 0;">
            <h3>Select Region and Value</h3>
            
            <form class="cart" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('woocommerce-add-to-cart'); ?>
                
                <!-- Region Selector -->
                <div class="prepaid-pricing-field">
                    <h3 class="field-heading"><?php _e('Region', 'prepaid-pricing'); ?></h3>
                    
                    <div class="field-input">
                        <select name="prepaid_pricing_region" id="prepaid-pricing-region" required>
                            <option value=""><?php _e('Select region', 'prepaid-pricing'); ?></option>
                            <?php foreach ($regions as $region_index => $region): ?>
                                <?php if ($region['stock_status'] == 'instock'): ?>
                                    <option value="<?php echo esc_attr($region_index); ?>"><?php echo esc_html($region['name']); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Value Selector -->
                <div class="prepaid-pricing-field">
                    <h3 class="field-heading"><?php _e('Value', 'prepaid-pricing'); ?></h3>
                    
                    <div class="field-input">
                        <input type="hidden" name="prepaid_pricing_face_value" id="prepaid-pricing-face-value-hidden" value="">
                        <select name="prepaid_pricing_face_value_list" id="prepaid-pricing-face-value-list" required disabled>
                            <option value=""><?php _e('Select value', 'prepaid-pricing'); ?></option>
                        </select>
                    </div>
                </div>

                <!-- Add to Cart Section -->
                <div class="prepaid-pricing-cart-buttons">
                    <input type="hidden" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" />
                    <button type="submit" class="single_add_to_cart_button button alt" disabled>
                        <?php _e('ADD TO CART', 'prepaid-pricing'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Thêm JavaScript để debug
     */
    public function add_debug_javascript() {
        ?>
        <script>
        console.log('Prepaid Pricing Debug Initialized');
        
        // Check all forms on the page
        const forms = document.querySelectorAll('form');
        for (let i = 0; i < forms.length; i++) {
            console.log('Form #' + i, {
                id: forms[i].id,
                class: forms[i].className,
                action: forms[i].action,
                method: forms[i].method
            });
        }
        
        // Check if the region selector exists
        const regionSelect = document.getElementById('prepaid-pricing-region');
        console.log('Region select:', {
            exists: regionSelect !== null,
            value: regionSelect ? regionSelect.value : undefined
        });
        
        // Check if the face value selector exists
        const faceValueSelect = document.getElementById('prepaid-pricing-face-value-list');
        console.log('Face value select:', {
            exists: faceValueSelect !== null,
            value: faceValueSelect ? faceValueSelect.value : undefined
        });
        
        // Try to find regions data
        const regionsData = document.getElementById('prepaid-pricing-regions-data');
        if (regionsData) {
            console.log('Regions data found:', regionsData.textContent);
        } else {
            console.log('Regions data not found');
        }
        </script>
        <?php
    }
    
    /**
     * Debug thông tin form
     */
    public function debug_form_submissions() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (is_product()) {
            echo '<div style="display: none;" id="pp-form-debug">';
            echo '<h3>Form Submit Debug</h3>';
            echo '<pre>REQUEST URI: ' . $_SERVER['REQUEST_URI'] . '</pre>';
            echo '<pre>REQUEST METHOD: ' . $_SERVER['REQUEST_METHOD'] . '</pre>';
            echo '<pre>POST: ' . print_r($_POST, true) . '</pre>';
            echo '<pre>GET: ' . print_r($_GET, true) . '</pre>';
            echo '<pre>REQUEST: ' . print_r($_REQUEST, true) . '</pre>';
            echo '</div>';
            
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('form.cart').on('submit', function() {
                    console.log('Form submitted', {
                        region: $('#prepaid-pricing-region').val(),
                        face_value: $('#prepaid-pricing-face-value-hidden').val(),
                        form_data: $(this).serialize()
                    });
                });
            });
            </script>
            <?php
        }
    }

    /**
     * Kiểm tra xem có chọn region khi thêm vào giỏ hàng không
     */
    public function validate_region_selection($valid, $product_id, $quantity) {
        if ($this->data->product_has_prepaid_pricing($product_id)) {
            // Debug thông tin POST
            error_log('Prepaid Pricing: Validate region selection - POST data: ' . print_r($_POST, true));
            error_log('Prepaid Pricing: Validate region selection - REQUEST data: ' . print_r($_REQUEST, true));
            
            // Fix cho một số theme hoặc plugins có thể làm thay đổi cách dữ liệu POST được xử lý
            $region = isset($_POST['prepaid_pricing_region']) ? $_POST['prepaid_pricing_region'] : 
                     (isset($_REQUEST['prepaid_pricing_region']) ? $_REQUEST['prepaid_pricing_region'] : '');
                     
            // Lấy face value từ nhiều nguồn khác nhau
            $face_value = '';
            
            // Kiểm tra hidden field chính
            if (isset($_POST['prepaid_pricing_face_value']) && $_POST['prepaid_pricing_face_value'] !== '') {
                $face_value = $_POST['prepaid_pricing_face_value'];
            } elseif (isset($_REQUEST['prepaid_pricing_face_value']) && $_REQUEST['prepaid_pricing_face_value'] !== '') {
                $face_value = $_REQUEST['prepaid_pricing_face_value'];
            }
            
            // Kiểm tra face value từ danh sách nếu chưa có
            if ($face_value === '' && isset($_POST['prepaid_pricing_face_value_list']) && $_POST['prepaid_pricing_face_value_list'] !== '') {
                $face_value = $_POST['prepaid_pricing_face_value_list'];
            } elseif ($face_value === '' && isset($_REQUEST['prepaid_pricing_face_value_list']) && $_REQUEST['prepaid_pricing_face_value_list'] !== '') {
                $face_value = $_REQUEST['prepaid_pricing_face_value_list'];
            }
            
            // Kiểm tra face value từ custom input nếu chưa có
            if ($face_value === '' && isset($_POST['prepaid_pricing_face_value_custom']) && $_POST['prepaid_pricing_face_value_custom'] !== '') {
                $face_value = $_POST['prepaid_pricing_face_value_custom'];
            } elseif ($face_value === '' && isset($_REQUEST['prepaid_pricing_face_value_custom']) && $_REQUEST['prepaid_pricing_face_value_custom'] !== '') {
                $face_value = $_REQUEST['prepaid_pricing_face_value_custom'];
            }
            
            // Debug thông tin sau khi xử lý
            error_log("Prepaid Pricing: Extracted region: {$region}, face_value: {$face_value}");
            
            // SỬA LỖI CHÍNH: Kiểm tra region với isset và $region === '' thay vì empty($region)
            if (!isset($region) || $region === '') {  // $region có thể là '0' nhưng vẫn hợp lệ
                wc_add_notice(__('Please select a Region before adding to cart.', 'prepaid-pricing'), 'error');
                return false;
            }
            
            // SỬA LỖI CHÍNH: Kiểm tra face_value với isset và $face_value === '' 
            if (!isset($face_value) || $face_value === '') {
                wc_add_notice(__('Please select a Value before adding to cart.', 'prepaid-pricing'), 'error');
                return false;
            }
            
            // Kiểm tra xem region được chọn có tồn tại và còn hàng không
            $regions = $this->data->get_product_regions($product_id);
            $region_index = (int) $region;
            
            if (!isset($regions[$region_index])) {
                wc_add_notice(__('Invalid region.', 'prepaid-pricing'), 'error');
                error_log('Prepaid Pricing: Invalid region index: ' . $region_index);
                return false;
            }
            
            $region_data = $regions[$region_index];
            
            if ($region_data['stock_status'] !== 'instock') {
                wc_add_notice(__('Region is out of stock.', 'prepaid-pricing'), 'error');
                error_log('Prepaid Pricing: Region out of stock: ' . $region_index);
                return false;
            }
            
            // Kiểm tra face value dựa trên chế độ
            $face_value = (float) $face_value;
            $face_value_mode = isset($region_data['face_value_mode']) ? $region_data['face_value_mode'] : 'list';
            
            if ($face_value_mode === 'custom') {
                // Chế độ custom - kiểm tra min/max/step
                $min = (float) ($region_data['face_value_min'] ?? 1);
                $max = (float) ($region_data['face_value_max'] ?? 100);
                $step = (float) ($region_data['face_value_step'] ?? 1);
                
                if ($face_value < $min || $face_value > $max) {
                    wc_add_notice(sprintf(__('Value must be between %s and %s.', 'prepaid-pricing'), 
                                          $region_data['currency'] . ' ' . $min, 
                                          $region_data['currency'] . ' ' . $max), 'error');
                    error_log('Prepaid Pricing: Face value out of range: ' . $face_value . ', min: ' . $min . ', max: ' . $max);
                    return false;
                }
                
                // Kiểm tra step (optional) - có thể bỏ qua nếu không cần kiểm tra chặt chẽ
                if ($step > 0) {
                    $remainder = fmod(($face_value - $min), $step);
                    // Xử lý sai số của phép tính floating point
                    if (abs($remainder) > 0.0001 && abs($remainder - $step) > 0.0001) {
                        wc_add_notice(sprintf(__('Value must be in increments of %s from the minimum value.', 'prepaid-pricing'), 
                                             $region_data['currency'] . ' ' . $step), 'error');
                        error_log('Prepaid Pricing: Face value not a valid step: ' . $face_value . ', step: ' . $step . ', remainder: ' . $remainder);
                        return false;
                    }
                }
            } else {
                // Chế độ danh sách - kiểm tra face value có trong danh sách không
                $face_values = $region_data['face_values'] ?? array();
                
                if (!in_array($face_value, $face_values)) {
                    wc_add_notice(__('Invalid value.', 'prepaid-pricing'), 'error');
                    error_log('Prepaid Pricing: Invalid face value: ' . $face_value . ' for region: ' . $region_index);
                    return false;
                }
            }
            
            // Debug - Thông báo kiểm tra thành công
            error_log('Prepaid Pricing: Validation successful - adding to cart');
        }
        
        return $valid;
    }

    /**
     * Thêm thông tin region vào dữ liệu giỏ hàng
     */
    public function add_region_data_to_cart($cart_item_data, $product_id, $variation_id) {
        error_log('Prepaid Pricing: Adding to cart - Product ID: ' . $product_id);
        
        // Fix cho một số theme hoặc plugins có thể làm thay đổi cách dữ liệu POST được xử lý
        $region = isset($_POST['prepaid_pricing_region']) ? $_POST['prepaid_pricing_region'] : 
                 (isset($_REQUEST['prepaid_pricing_region']) ? $_REQUEST['prepaid_pricing_region'] : '');
                 
        // Lấy face value từ nhiều nguồn có thể có
        $face_value = '';
        
        // Kiểm tra hidden field chính
        if (isset($_POST['prepaid_pricing_face_value']) && $_POST['prepaid_pricing_face_value'] !== '') {
            $face_value = $_POST['prepaid_pricing_face_value'];
        } elseif (isset($_REQUEST['prepaid_pricing_face_value']) && $_REQUEST['prepaid_pricing_face_value'] !== '') {
            $face_value = $_REQUEST['prepaid_pricing_face_value'];
        }
        
        // Kiểm tra face value từ danh sách nếu chưa có
        if ($face_value === '' && isset($_POST['prepaid_pricing_face_value_list']) && $_POST['prepaid_pricing_face_value_list'] !== '') {
            $face_value = $_POST['prepaid_pricing_face_value_list'];
        } elseif ($face_value === '' && isset($_REQUEST['prepaid_pricing_face_value_list']) && $_REQUEST['prepaid_pricing_face_value_list'] !== '') {
            $face_value = $_REQUEST['prepaid_pricing_face_value_list'];
        }
        
        // Kiểm tra face value từ custom input nếu chưa có
        if ($face_value === '' && isset($_POST['prepaid_pricing_face_value_custom']) && $_POST['prepaid_pricing_face_value_custom'] !== '') {
            $face_value = $_POST['prepaid_pricing_face_value_custom'];
        } elseif ($face_value === '' && isset($_REQUEST['prepaid_pricing_face_value_custom']) && $_REQUEST['prepaid_pricing_face_value_custom'] !== '') {
            $face_value = $_REQUEST['prepaid_pricing_face_value_custom'];
        }
        
        if ($region !== '' && $face_value !== '') {
            $regions = $this->data->get_product_regions($product_id);
            $region_index = (int) $region;
            $face_value = (float) $face_value;
            
            error_log('Prepaid Pricing: Region index: ' . $region_index . ', Face value: ' . $face_value);
            
            if (isset($regions[$region_index])) {
                $region_data = $regions[$region_index];
                
                // Tính giá ngay khi thêm vào giỏ hàng
                $rate = $this->rates->get_exchange_rate($region_data['rate_pair']);
                $calculated_price = 0;
                
                // Lấy Y và Z - nếu rỗng thì dùng giá trị toàn cục
                $factor_y = $region_data['factor_y'];
                $factor_z = $region_data['factor_z'];
                
                if ($factor_y === '' || $factor_y === null) {
                    $global_settings = Settings::get_global_settings();
                    $factor_y = $global_settings['global_factor_y'];
                }
                
                if ($factor_z === '' || $factor_z === null) {
                    $global_settings = Settings::get_global_settings();
                    $factor_z = $global_settings['global_factor_z'];
                }
                
                if ($rate) {
                    $calculated_price = ($face_value * $region_data['factor_x'] * $rate * (100 + (float)$factor_y) / 100) + (float)$factor_z;
                    error_log('Prepaid Pricing: Calculated price on add to cart: ' . $calculated_price);
                }
                
                $cart_item_data['prepaid_pricing_region'] = array(
                    'index' => $region_index,
                    'name' => $region_data['name'],
                    'currency' => $region_data['currency'],
                    'face_value' => $face_value,
                    'factor_x' => $region_data['factor_x'],
                    'rate_pair' => $region_data['rate_pair'],
                    'factor_y' => $factor_y,
                    'factor_z' => $factor_z,
                    'face_value_mode' => $region_data['face_value_mode'] ?? 'list',
                    'calculated_price' => $calculated_price // Lưu giá đã tính
                );
                
                // Tạo key riêng để mỗi region+mệnh giá là một item riêng biệt trong giỏ hàng
                $cart_item_data['unique_key'] = md5($region_index . '-' . $face_value . '-' . microtime());
                
                error_log('Prepaid Pricing: Added to cart data: ' . print_r($cart_item_data, true));
            } else {
                error_log('Prepaid Pricing: Region not found with index: ' . $region_index);
            }
        } else {
            error_log('Prepaid Pricing: Missing region or face value parameters');
            error_log('Prepaid Pricing: POST data: ' . print_r($_POST, true));
            error_log('Prepaid Pricing: REQUEST data: ' . print_r($_REQUEST, true));
        }
        
        return $cart_item_data;
    }

    /**
     * Hiển thị thông tin region trong giỏ hàng
     */
    public function display_region_in_cart($item_data, $cart_item) {
        if (isset($cart_item['prepaid_pricing_region'])) {
            $region = $cart_item['prepaid_pricing_region'];
            
            $item_data[] = array(
                'key' => __('Region', 'prepaid-pricing'),
                'value' => $region['name']
            );
            
            $item_data[] = array(
                'key' => __('Value', 'prepaid-pricing'),
                'value' => $region['currency'] . ' ' . $region['face_value']
            );
        }
        
        return $item_data;
    }

    /**
     * Tính giá sản phẩm trong giỏ hàng dựa trên region và mệnh giá
     */
    public function calculate_region_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        // Debug - kiểm tra nếu hook được gọi
        error_log('Prepaid Pricing: calculate_region_price hook called');
        
        if (!$cart || !is_object($cart) || !method_exists($cart, 'get_cart')) {
            error_log('Prepaid Pricing: Invalid cart object');
            return;
        }
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['prepaid_pricing_region'])) {
                $region = $cart_item['prepaid_pricing_region'];
                $product = $cart_item['data'];
                
                error_log('Prepaid Pricing: Calculating price for cart item: ' . $cart_item_key);
                error_log('Prepaid Pricing: Region data: ' . print_r($region, true));
                
                if (isset($region['calculated_price']) && $region['calculated_price'] > 0) {
                    $price = $region['calculated_price'];
                    error_log('Prepaid Pricing: Using pre-calculated price: ' . $price);
                } else {
                    // Lấy tỷ giá hiện tại
                    $rate = $this->rates->get_exchange_rate($region['rate_pair']);
                    error_log('Prepaid Pricing: Exchange rate: ' . $rate . ' for pair: ' . $region['rate_pair']);
                    
                    // Lấy Y và Z - nếu rỗng thì dùng giá trị toàn cục
                    $factor_y = $region['factor_y'];
                    $factor_z = $region['factor_z'];
                    
                    if ($factor_y === '' || $factor_y === null) {
                        $global_settings = Settings::get_global_settings();
                        $factor_y = $global_settings['global_factor_y'];
                    }
                    
                    if ($factor_z === '' || $factor_z === null) {
                        $global_settings = Settings::get_global_settings();
                        $factor_z = $global_settings['global_factor_z'];
                    }
                    
                    if ($rate) {
                        // Công thức: Mệnh giá * X * rate * (100 + Y)% + Z
                        $price = ($region['face_value'] * $region['factor_x'] * $rate * (100 + (float)$factor_y) / 100) + (float)$factor_z;
                        error_log('Prepaid Pricing: Calculated price: ' . $price);
                    } else {
                        error_log('Prepaid Pricing: Rate not found for pair: ' . $region['rate_pair']);
                        $price = 0;
                    }
                }
                
                // CRITICAL FIX: Set the price directly to the cart item's data
                if ($price > 0) {
                    if (method_exists($product, 'set_price')) {
                        $product->set_price($price);
                        error_log('Prepaid Pricing: Set price using set_price(): ' . $price);
                    }
                    
                    // Update price data directly for maximum compatibility
                    $product->price = $price;
                    $product->regular_price = $price;
                    
                    // Compatibility with WooCommerce extensions 
                    $cart_item['data']->price = $price;
                    $cart_item['data']->regular_price = $price;
                    
                    // Direct cart data manipulation
                    $cart->cart_contents[$cart_item_key]['data']->price = $price;
                    $cart->cart_contents[$cart_item_key]['data']->regular_price = $price;
                    
                    // Save the calculated price in cart_item
                    $cart->cart_contents[$cart_item_key]['prepaid_pricing_region']['calculated_price'] = $price;
                    
                    error_log('Prepaid Pricing: Updated price in cart directly: ' . $price);
                }
            } else {
                error_log('Prepaid Pricing: No prepaid_pricing_region in cart item');
            }
        }
    }

    /**
     * Thay đổi giá hiển thị trong giỏ hàng
     */
    public function cart_item_price($price, $cart_item, $cart_item_key) {
        if (isset($cart_item['prepaid_pricing_region'])) {
            $region = $cart_item['prepaid_pricing_region'];
            
            if (isset($region['calculated_price']) && $region['calculated_price'] > 0) {
                error_log('Prepaid Pricing: Override cart display price with pre-calculated: ' . $region['calculated_price']);
                return wc_price($region['calculated_price']);
            }
            
            $face_value = $region['face_value'];
            $rate = $this->rates->get_exchange_rate($region['rate_pair']);
            
            // Lấy Y và Z - nếu rỗng thì dùng giá trị toàn cục
            $factor_y = $region['factor_y'];
            $factor_z = $region['factor_z'];
            
            if ($factor_y === '' || $factor_y === null) {
                $global_settings = Settings::get_global_settings();
                $factor_y = $global_settings['global_factor_y'];
            }
            
            if ($factor_z === '' || $factor_z === null) {
                $global_settings = Settings::get_global_settings();
                $factor_z = $global_settings['global_factor_z'];
            }
            
            if ($rate) {
                $calculated_price = ($face_value * $region['factor_x'] * $rate * (100 + (float)$factor_y) / 100) + (float)$factor_z;
                error_log('Prepaid Pricing: Override cart display price: ' . $calculated_price);
                return wc_price($calculated_price);
            }
        }
        
        return $price;
    }

    /**
     * Xử lý trực tiếp giá hiển thị trong giỏ hàng
     */
    public function cart_item_subtotal($subtotal, $cart_item, $cart_item_key) {
        if (isset($cart_item['prepaid_pricing_region'])) {
            $region = $cart_item['prepaid_pricing_region'];
            $quantity = $cart_item['quantity'];
            
            if (isset($region['calculated_price']) && $region['calculated_price'] > 0) {
                $total = $region['calculated_price'] * $quantity;
                error_log('Prepaid Pricing: Override cart subtotal with pre-calculated: ' . $total);
                return wc_price($total);
            }
            
            $face_value = $region['face_value'];
            $rate = $this->rates->get_exchange_rate($region['rate_pair']);
            
            // Lấy Y và Z - nếu rỗng thì dùng giá trị toàn cục
            $factor_y = $region['factor_y'];
            $factor_z = $region['factor_z'];
            
            if ($factor_y === '' || $factor_y === null) {
                $global_settings = Settings::get_global_settings();
                $factor_y = $global_settings['global_factor_y'];
            }
            
            if ($factor_z === '' || $factor_z === null) {
                $global_settings = Settings::get_global_settings();
                $factor_z = $global_settings['global_factor_z'];
            }
            
            if ($rate) {
                $calculated_price = ($face_value * $region['factor_x'] * $rate * (100 + (float)$factor_y) / 100) + (float)$factor_z;
                $total = $calculated_price * $quantity;
                error_log('Prepaid Pricing: Override cart subtotal: ' . $total);
                return wc_price($total);
            }
        }
        
        return $subtotal;
    }

    /**
     * IMPORTANT: Update order item prices
     */
    public function update_order_item_price($item, $cart_item_key, $values, $order) {
        if (isset($values['prepaid_pricing_region'])) {
            $region = $values['prepaid_pricing_region'];
            
            if (isset($region['calculated_price']) && $region['calculated_price'] > 0) {
                error_log('Prepaid Pricing: Setting order item price to: ' . $region['calculated_price']);
                $item->set_subtotal($region['calculated_price'] * $values['quantity']);
                $item->set_total($region['calculated_price'] * $values['quantity']);
            } else {
                $face_value = $region['face_value'];
                $rate = $this->rates->get_exchange_rate($region['rate_pair']);
                
                // Lấy Y và Z - nếu rỗng thì dùng giá trị toàn cục
                $factor_y = $region['factor_y'];
                $factor_z = $region['factor_z'];
                
                if ($factor_y === '' || $factor_y === null) {
                    $global_settings = Settings::get_global_settings();
                    $factor_y = $global_settings['global_factor_y'];
                }
                
                if ($factor_z === '' || $factor_z === null) {
                    $global_settings = Settings::get_global_settings();
                    $factor_z = $global_settings['global_factor_z'];
                }
                
                if ($rate) {
                    $calculated_price = ($face_value * $region['factor_x'] * $rate * (100 + (float)$factor_y) / 100) + (float)$factor_z;
                    error_log('Prepaid Pricing: Setting order item price to calculated: ' . $calculated_price);
                    $item->set_subtotal($calculated_price * $values['quantity']);
                    $item->set_total($calculated_price * $values['quantity']);
                }
            }
        }
    }
    
    /**
     * IMPORTANT: Update product price in order items
     */
    public function update_order_item_product_price($product, $item) {
        // Check if the item has our region data
        $region_data = $item->get_meta('_prepaid_pricing_region_data');
        
        if ($product && !empty($region_data)) {
            if (isset($region_data['calculated_price']) && $region_data['calculated_price'] > 0) {
                $price = $region_data['calculated_price'];
                error_log('Prepaid Pricing: Setting order product price to: ' . $price);
                $product->set_price($price);
                $product->price = $price;
                $product->regular_price = $price;
            } else {
                $face_value = $region_data['face_value'];
                $rate = $this->rates->get_exchange_rate($region_data['rate_pair']);
                
                // Lấy Y và Z - nếu rỗng thì dùng giá trị toàn cục
                $factor_y = $region_data['factor_y'];
                $factor_z = $region_data['factor_z'];
                
                if ($factor_y === '' || $factor_y === null) {
                    $global_settings = Settings::get_global_settings();
                    $factor_y = $global_settings['global_factor_y'];
                }
                
                if ($factor_z === '' || $factor_z === null) {
                    $global_settings = Settings::get_global_settings();
                    $factor_z = $global_settings['global_factor_z'];
                }
                
                if ($rate) {
                    $price = ($face_value * $region_data['factor_x'] * $rate * (100 + (float)$factor_y) / 100) + (float)$factor_z;
                    error_log('Prepaid Pricing: Setting order product price to calculated: ' . $price);
                    $product->set_price($price);
                    $product->price = $price;
                    $product->regular_price = $price;
                }
            }
        }
        
        return $product;
    }

    /**
     * Lưu thông tin region vào đơn hàng
     */
    public function save_region_to_order_item($item, $cart_item_key, $values, $order) {
        if (isset($values['prepaid_pricing_region'])) {
            $region = $values['prepaid_pricing_region'];
            
            $item->add_meta_data(__('Region', 'prepaid-pricing'), $region['name']);
            $item->add_meta_data(__('Value', 'prepaid-pricing'), $region['currency'] . ' ' . $region['face_value']);
            
            // Lưu thêm các thông số để có thể truy vấn sau này nếu cần
            $item->add_meta_data('_prepaid_pricing_region_data', $region, true);
        }
    }

    /**
     * Tính giá dựa trên dữ liệu region và mệnh giá
     */
    private function calculate_region_price_from_data($region, $face_value) {
        if (empty($region['rate_pair'])) {
            return 0;
        }
        
        $rate = $this->rates->get_exchange_rate($region['rate_pair']);
        
        if (!$rate) {
            return 0;
        }
        
        // Lấy Y và Z - nếu rỗng thì dùng giá trị toàn cục
        $factor_y = $region['factor_y'];
        $factor_z = $region['factor_z'];
        
        if ($factor_y === '' || $factor_y === null) {
            $global_settings = Settings::get_global_settings();
            $factor_y = $global_settings['global_factor_y'];
        }
        
        if ($factor_z === '' || $factor_z === null) {
            $global_settings = Settings::get_global_settings();
            $factor_z = $global_settings['global_factor_z'];
        }
        
        return ($face_value * $region['factor_x'] * $rate * (100 + (float)$factor_y) / 100) + (float)$factor_z;
    }
    
    /**
     * Chuyển hướng đến trang thanh toán nếu nhấn "Buy Now"
     */
    public function redirect_to_checkout($redirect_url) {
        if (isset($_REQUEST['buy_now']) && $_REQUEST['buy_now']) {
            $redirect_url = wc_get_checkout_url();
        }
        return $redirect_url;
    }
}