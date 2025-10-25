<?php
/**
 * Debug Fix for Prepaid Pricing
 * 
 * File này sẽ loại bỏ các hook trùng lặp và chỉ giữ lại một hook duy nhất
 * để tránh hiển thị form nhiều lần
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Loại bỏ các hook trùng lặp
 */
function prepaid_pricing_cleanup_duplicate_hooks() {
    global $wp_filter;
    
    // Debug
    error_log('Prepaid Pricing Debug Fix: Running cleanup hook function');
    
    $found_hooks = array();
    
    // Danh sách các hook cần kiểm tra
    $hook_list = array(
        'woocommerce_before_add_to_cart_form',
        'woocommerce_before_add_to_cart_button',
        'woocommerce_before_single_product',
        'woocommerce_after_single_product_summary'
    );
    
    // Lưu lại hook chính để sử dụng
    $main_hook = 'woocommerce_single_product_summary';
    
    foreach ($hook_list as $hook) {
        if (isset($wp_filter[$hook])) {
            // Tìm và xóa add_region_selection method khỏi tất cả các priority của hook này
            foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $idx => $callback) {
                    if (is_array($callback['function']) && is_object($callback['function'][0]) && 
                        get_class($callback['function'][0]) === 'Prepaid_Pricing\Frontend\Frontend' && 
                        $callback['function'][1] === 'add_region_selection') {
                        
                        error_log("Prepaid Pricing Debug Fix: Removing duplicate hook: {$hook}, priority: {$priority}");
                        unset($wp_filter[$hook]->callbacks[$priority][$idx]);
                        $found_hooks[] = $hook;
                    }
                }
            }
        }
    }
    
    error_log('Prepaid Pricing Debug Fix: Found and removed hooks: ' . implode(', ', $found_hooks));
    
    // Kiểm tra và đảm bảo hook chính vẫn tồn tại
    $main_hook_exists = false;
    if (isset($wp_filter[$main_hook])) {
        foreach ($wp_filter[$main_hook]->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $idx => $callback) {
                if (is_array($callback['function']) && is_object($callback['function'][0]) && 
                    get_class($callback['function'][0]) === 'Prepaid_Pricing\Frontend\Frontend' && 
                    $callback['function'][1] === 'add_region_selection') {
                    
                    $main_hook_exists = true;
                    break 2;
                }
            }
        }
    }
    
    if (!$main_hook_exists) {
        error_log('Prepaid Pricing Debug Fix: WARNING - Main hook not found, attempting to recreate');
        
        // Thử lại khởi tạo hook chính nếu nó không tồn tại
        $frontend_instance = null;
        
        // Tìm instance của Frontend class nếu có thể
        global $prepaid_pricing;
        if (isset($prepaid_pricing) && isset($prepaid_pricing->frontend) && 
            $prepaid_pricing->frontend instanceof \Prepaid_Pricing\Frontend\Frontend) {
            $frontend_instance = $prepaid_pricing->frontend;
        } else {
            // Tạo instance mới nếu không tìm thấy
            try {
                $frontend_instance = new \Prepaid_Pricing\Frontend\Frontend();
            } catch (\Exception $e) {
                error_log('Prepaid Pricing Debug Fix: Error creating Frontend instance: ' . $e->getMessage());
            }
        }
        
        if ($frontend_instance) {
            // Xóa hook cũ
            remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
            
            // Thêm hook mới
            add_action('woocommerce_single_product_summary', array($frontend_instance, 'add_region_selection'), 30);
            error_log('Prepaid Pricing Debug Fix: Successfully recreated the main hook');
        } else {
            error_log('Prepaid Pricing Debug Fix: Could not recreate the main hook - Frontend instance not available');
        }
    }
}

// Chạy cleanup hook sau khi tất cả các plugin đã được load
add_action('wp', 'prepaid_pricing_cleanup_duplicate_hooks', 999);

/**
 * Thêm CSS để ẩn form trùng lặp
 */
function prepaid_pricing_add_duplicate_form_css() {
    if (!is_product()) return;
    ?>
    <style type="text/css">
        /* Hide all but the first prepaid pricing form */
        .prepaid-pricing-region-selection:not(:first-of-type) {
            display: none !important;
        }
        
        /* Add styling to the first form to make it stand out */
        .prepaid-pricing-region-selection:first-of-type {
            border: 2px solid #4CAF50 !important;
            padding: 15px !important;
            margin: 15px 0 !important;
            background-color: #f9f9f9 !important;
        }
    </style>
    <?php
}

add_action('wp_head', 'prepaid_pricing_add_duplicate_form_css');

/**
 * Debug form information
 */
function prepaid_pricing_debug_form_info() {
    if (!is_product() || !current_user_can('manage_options')) return;
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Count forms
        var formCount = $('.prepaid-pricing-region-selection').length;
        
        if (formCount > 1) {
            console.log('Prepaid Pricing Debug Fix: Multiple forms detected - ' + formCount);
            console.warn('Multiple Prepaid Pricing forms detected - this can cause issues. CSS has been applied to hide duplicates.');
        } else if (formCount === 1) {
            console.log('Prepaid Pricing Debug Fix: Single form confirmed - OK');
        } else {
            console.error('Prepaid Pricing Debug Fix: No form found!');
        }
        
        // Log all events on the region and value selects
        $(document).on('change click focus blur', '#prepaid-pricing-region, #prepaid-pricing-face-value-list, #prepaid-pricing-face-value-custom', function(e) {
            console.log('Event: ' + e.type + ' on ' + this.id + ', value: ' + $(this).val());
        });
    });
    </script>
    <?php
}

add_action('wp_footer', 'prepaid_pricing_debug_form_info', 9999);