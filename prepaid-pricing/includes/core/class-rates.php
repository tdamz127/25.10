<?php
namespace Prepaid_Pricing\Core;

/**
 * Lớp xử lý tỷ giá hối đoái
 */
class Rates {

    private $api_url = 'https://code-master-hadez3027.replit.app/rate';
    private $option_name = 'prepaid_pricing_exchange_rates';

    public function __construct() {
        // Hook vào cron event để cập nhật tỷ giá
        add_action('prepaid_pricing_update_rates', array($this, 'fetch_and_save_rates'));
    }

    /**
     * Lấy tỷ giá từ API và lưu vào database
     */
    public function fetch_and_save_rates() {
        $response = wp_remote_get($this->api_url, array(
            'timeout' => 15,
            'sslverify' => true,
        ));

        if (is_wp_error($response)) {
            error_log('Prepaid Pricing: Lỗi khi lấy tỷ giá - ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log('Prepaid Pricing: API trả về mã trạng thái ' . $status_code);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data) || !isset($data['rates']) || !is_array($data['rates'])) {
            error_log('Prepaid Pricing: Định dạng dữ liệu không hợp lệ từ API');
            return false;
        }

        // Lưu tỷ giá vào database
        update_option($this->option_name, $data);
        
        return true;
    }

    /**
     * Lấy tỷ giá đã lưu từ database
     */
    public function get_saved_rates() {
        $rates = get_option($this->option_name, array());
        
        // Nếu chưa có tỷ giá nào được lưu, thử lấy tỷ giá mới
        if (empty($rates)) {
            $this->fetch_and_save_rates();
            $rates = get_option($this->option_name, array());
        }
        
        return $rates;
    }

    /**
     * Lấy tỷ giá cụ thể
     */
    public function get_exchange_rate($currency_pair) {
        $rates = $this->get_saved_rates();
        
        if (isset($rates['rates'][$currency_pair])) {
            return $rates['rates'][$currency_pair];
        }
        
        return false;
    }
    
    /**
     * Tính giá dựa trên công thức và tỷ giá
     * Sử dụng Y và Z global nếu các giá trị này không được cung cấp
     */
    public function calculate_price($face_value, $x, $rate_pair, $y = null, $z = null) {
        $rate = $this->get_exchange_rate($rate_pair);
        
        if (!$rate) {
            return 0;
        }
        
        // Kiểm tra xem có cần sử dụng giá trị toàn cục không
        if ($y === null || $y === '' || $z === null || $z === '') {
            // Load các giá trị toàn cục
            $global_settings = \Prepaid_Pricing\Admin\Settings::get_global_settings();
            
            // Chỉ sử dụng giá trị toàn cục nếu giá trị cụ thể không có
            if ($y === null || $y === '') {
                $y = $global_settings['global_factor_y'];
            }
            
            if ($z === null || $z === '') {
                $z = $global_settings['global_factor_z'];
            }
        }
        
        // Đảm bảo các giá trị là số
        $face_value = floatval($face_value);
        $x = floatval($x);
        $rate = floatval($rate);
        $y = floatval($y);
        $z = floatval($z);
        
        // Công thức: Mệnh giá * X * rate * (100 + Y)% + Z
        return ($face_value * $x * $rate * (100 + $y) / 100) + $z;
    }
}