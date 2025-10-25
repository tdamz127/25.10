<?php
namespace Prepaid_Pricing\Core;

/**
 * Lớp xử lý thông báo
 */
class Notices {

    public function __construct() {
        // Lọc và loại bỏ thông báo không mong muốn
        add_filter('woocommerce_add_error', array($this, 'filter_woocommerce_notices'), 10, 1);
        add_filter('woocommerce_add_notice', array($this, 'filter_woocommerce_notices'), 10, 1);
        
        // Loại bỏ các thông báo đã được lưu trong session
        add_action('template_redirect', array($this, 'remove_specific_notices'));
    }
    
    /**
     * Lọc các thông báo WooCommerce
     */
    public function filter_woocommerce_notices($message) {
        // Kiểm tra nếu thông báo chứa văn bản cụ thể
        if (strpos($message, 'gift card has been removed from your cart because it can no longer be purchased') !== false) {
            // Trả về chuỗi rỗng để loại bỏ thông báo
            return '';
        }
        
        // Trả về thông báo gốc nếu không phải loại bỏ
        return $message;
    }
    
    /**
     * Loại bỏ các thông báo cụ thể đã được lưu trong session
     */
    public function remove_specific_notices() {
        // Nếu không có WooCommerce hoặc không có session, thoát
        if (!function_exists('wc') || !isset(WC()->session)) {
            return;
        }
        
        // Lấy tất cả các thông báo từ session
        $all_notices = WC()->session->get('wc_notices', array());
        
        // Duyệt qua các loại thông báo
        foreach ($all_notices as $notice_type => $notices) {
            if (empty($notices)) continue;
            
            // Lọc các thông báo từng loại
            $filtered_notices = array();
            foreach ($notices as $notice) {
                // Nếu là thông báo dạng chuỗi
                if (is_string($notice)) {
                    if (strpos($notice, 'gift card has been removed from your cart') === false) {
                        $filtered_notices[] = $notice;
                    }
                } 
                // Nếu là thông báo dạng mảng có key 'notice'
                elseif (isset($notice['notice'])) {
                    if (strpos($notice['notice'], 'gift card has been removed from your cart') === false) {
                        $filtered_notices[] = $notice;
                    }
                } 
                // Các loại thông báo khác giữ nguyên
                else {
                    $filtered_notices[] = $notice;
                }
            }
            
            // Cập nhật lại thông báo đã lọc
            $all_notices[$notice_type] = $filtered_notices;
        }
        
        // Cập nhật lại session với danh sách thông báo đã lọc
        WC()->session->set('wc_notices', $all_notices);
    }
}