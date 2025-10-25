<?php
namespace Prepaid_Pricing\Core;

/**
 * Lớp xử lý dữ liệu và meta cho plugin
 */
class Data {

    /**
     * Kiểm tra xem sản phẩm có sử dụng Prepaid Pricing hay không
     */
    public function product_has_prepaid_pricing($product_id) {
        // Debug giá trị của meta _prepaid_pricing_enabled
        $enabled_value = get_post_meta($product_id, '_prepaid_pricing_enabled', true);
        error_log('Prepaid Pricing: Checking if product ' . $product_id . ' has prepaid pricing enabled. Value: ' . 
                 (is_bool($enabled_value) ? ($enabled_value ? 'true (bool)' : 'false (bool)') : 
                 (is_numeric($enabled_value) ? $enabled_value . ' (numeric)' : 
                 (empty($enabled_value) ? 'empty' : 'non-empty string: ' . $enabled_value))));
        
        // SỬA LỖI CHÍNH: Kiểm tra tất cả các giá trị có thể đại diện cho "true"
        if ($enabled_value === '1' || $enabled_value === 1 || $enabled_value === true || $enabled_value === 'yes' || $enabled_value === 'on') {
            error_log('Prepaid Pricing: Product ' . $product_id . ' has prepaid pricing enabled');
            return true;
        }
        
        // Kiểm tra xem có region nào đã được cấu hình hay không
        $regions = $this->get_product_regions($product_id);
        if (!empty($regions)) {
            error_log('Prepaid Pricing: Product ' . $product_id . ' has regions configured, considering prepaid pricing enabled');
            return true;
        }
        
        error_log('Prepaid Pricing: Product ' . $product_id . ' does not have prepaid pricing enabled');
        return false;
    }

    /**
     * Lấy danh sách region cho sản phẩm
     */
    public function get_product_regions($product_id) {
        $regions = get_post_meta($product_id, '_prepaid_pricing_regions', true);
        
        // Debug
        error_log('Prepaid Pricing: Getting regions for product ' . $product_id . '. Count: ' . 
                 (is_array($regions) ? count($regions) : 'not an array'));
        
        if (!is_array($regions)) {
            return array();
        }
        
        return $regions;
    }

    /**
     * Lưu cấu hình Prepaid Pricing cho sản phẩm
     */
    public function save_product_prepaid_pricing($product_id, $enabled, $regions) {
        // Debug
        error_log('Prepaid Pricing: Saving prepaid pricing for product ' . $product_id . '. Enabled: ' . 
                 ($enabled ? 'true' : 'false') . ', Regions count: ' . count($regions));
        
        update_post_meta($product_id, '_prepaid_pricing_enabled', $enabled ? 1 : 0);
        update_post_meta($product_id, '_prepaid_pricing_regions', $regions);
    }
    
    /**
     * Lấy danh sách tất cả các sản phẩm có sử dụng Prepaid Pricing
     */
    public function get_all_prepaid_pricing_products() {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_prepaid_pricing_enabled',
                    'value' => '1',
                    'compare' => '='
                )
            )
        );
        
        $products = get_posts($args);
        
        return $products;
    }
    
    /**
     * Lấy danh sách tất cả các cặp tỷ giá được sử dụng trong tất cả các sản phẩm
     */
    public function get_all_used_rate_pairs() {
        $products = $this->get_all_prepaid_pricing_products();
        
        $rate_pairs = array();
        
        foreach ($products as $product) {
            $regions = $this->get_product_regions($product->ID);
            
            foreach ($regions as $region) {
                if (isset($region['rate_pair']) && !empty($region['rate_pair']) && !in_array($region['rate_pair'], $rate_pairs)) {
                    $rate_pairs[] = $region['rate_pair'];
                }
            }
        }
        
        return $rate_pairs;
    }
    
    /**
     * Kiểm tra xem một cặp tỷ giá có đang được sử dụng bởi bất kỳ sản phẩm nào không
     */
    public function is_rate_pair_in_use($rate_pair) {
        $used_pairs = $this->get_all_used_rate_pairs();
        return in_array($rate_pair, $used_pairs);
    }
    
    /**
     * Lấy URL nguồn của sản phẩm
     */
    public function get_product_source_url($product_id) {
        $source_url = get_post_meta($product_id, '_prepaid_pricing_source_url', true);
        return $source_url;
    }
    
    /**
     * Lưu URL nguồn của sản phẩm
     */
    public function save_product_source_url($product_id, $source_url) {
        update_post_meta($product_id, '_prepaid_pricing_source_url', esc_url_raw($source_url));
    }
}