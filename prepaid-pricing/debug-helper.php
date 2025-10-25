<?php
/**
 * Debug Helper để xác định vấn đề với Prepaid Pricing
 */

// Thêm vào sau khi kích hoạt plugin
add_action('admin_footer', 'prepaid_pricing_debug_info');
add_action('wp_footer', 'prepaid_pricing_debug_info');

function prepaid_pricing_debug_info() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $post, $product;
    
    echo '<!-- PREPAID_PRICING DEBUG INFO START -->';
    echo '<div id="prepaid-pricing-debug" style="background:#f8f8f8; border:1px solid #ddd; padding:15px; margin:15px; position:relative; z-index:9999; display:none;">';
    echo '<h3>Prepaid Pricing Debug Info</h3>';
    
    // Thông tin chung
    echo '<p><strong>Plugin URL:</strong> ' . PREPAID_PRICING_PLUGIN_URL . '</p>';
    echo '<p><strong>Plugin DIR:</strong> ' . PREPAID_PRICING_PLUGIN_DIR . '</p>';
    
    // Kiểm tra xem là trang sản phẩm không
    echo '<p><strong>is_product():</strong> ' . (is_product() ? 'Yes' : 'No') . '</p>';
    
    // Thông tin về sản phẩm hiện tại
    if (is_product() && $product) {
        echo '<p><strong>Product ID:</strong> ' . $product->get_id() . '</p>';
        
        // Kiểm tra Prepaid Pricing có bật không
        require_once PREPAID_PRICING_PLUGIN_DIR . 'includes/core/class-data.php';
        $data = new Prepaid_Pricing\Core\Data();
        $enabled = $data->product_has_prepaid_pricing($product->get_id());
        echo '<p><strong>Prepaid Pricing Enabled:</strong> ' . ($enabled ? 'Yes' : 'No') . '</p>';
        
        if ($enabled) {
            $regions = $data->get_product_regions($product->get_id());
            echo '<p><strong>Number of regions:</strong> ' . count($regions) . '</p>';
            echo '<pre>' . print_r($regions, true) . '</pre>';
        }
    }
    
    // Hook callback information
    global $wp_filter;
    echo '<p><strong>woocommerce_before_add_to_cart_button Hooks:</strong></p>';
    if (isset($wp_filter['woocommerce_before_add_to_cart_button'])) {
        echo '<pre>' . print_r($wp_filter['woocommerce_before_add_to_cart_button'], true) . '</pre>';
    } else {
        echo '<p>No callbacks registered for woocommerce_before_add_to_cart_button</p>';
    }
    
    echo '</div>';
    echo '<script>
    jQuery(document).ready(function($) {
        $("body").append("<button id=\'toggle-debug\' style=\'position:fixed; bottom:10px; right:10px; z-index:99999; padding:5px 10px; background:#007cba; color:#fff;\'>Toggle Debug</button>");
        $("#toggle-debug").click(function() {
            $("#prepaid-pricing-debug").toggle();
        });
    });
    </script>';
    echo '<!-- PREPAID_PRICING DEBUG INFO END -->';
}