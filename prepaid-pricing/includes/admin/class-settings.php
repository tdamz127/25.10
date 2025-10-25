<?php
namespace Prepaid_Pricing\Admin;

/**
 * Lớp quản lý các cài đặt toàn cục của plugin
 */
class Settings {

    private $option_name = 'prepaid_pricing_global_settings';

    public function __construct() {
        // Đã bỏ phần đăng ký menu riêng
        
        // Đăng ký cài đặt
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Đăng ký các tùy chọn cài đặt
     */
    public function register_settings() {
        register_setting(
            'prepaid_pricing_settings',
            $this->option_name,
            array('sanitize_callback' => array($this, 'sanitize_settings'))
        );

        add_settings_section(
            'prepaid_pricing_global_settings',
            __('Cài đặt Y và Z toàn cục', 'prepaid-pricing'),
            array($this, 'settings_section_callback'),
            'prepaid_pricing_settings'
        );

        add_settings_field(
            'global_factor_y',
            __('Y toàn cục (%)', 'prepaid-pricing'),
            array($this, 'global_factor_y_callback'),
            'prepaid_pricing_settings',
            'prepaid_pricing_global_settings'
        );

        add_settings_field(
            'global_factor_z',
            __('Z toàn cục', 'prepaid-pricing'),
            array($this, 'global_factor_z_callback'),
            'prepaid_pricing_settings',
            'prepaid_pricing_global_settings'
        );
    }

    /**
     * Callback mô tả cho phần cài đặt
     */
    public function settings_section_callback() {
        echo '<p>' . __('Cấu hình giá trị Y và Z toàn cục được sử dụng khi không có giá trị cụ thể tại Region.', 'prepaid-pricing') . '</p>';
        echo '<p><strong>' . __('Công thức tính giá: Mệnh giá * X * Tỷ giá * (100 + Y)% + Z', 'prepaid-pricing') . '</strong></p>';
    }

    /**
     * Callback cho trường Y toàn cục
     */
    public function global_factor_y_callback() {
        $options = get_option($this->option_name);
        $global_factor_y = isset($options['global_factor_y']) ? $options['global_factor_y'] : 0;
        
        echo '<input type="number" step="0.01" name="' . $this->option_name . '[global_factor_y]" value="' . esc_attr($global_factor_y) . '" class="regular-text" />';
        echo '<p class="description">' . __('Giá trị Y (%) dùng cho công thức tính giá khi không có giá trị cụ thể ở Region.', 'prepaid-pricing') . '</p>';
    }

    /**
     * Callback cho trường Z toàn cục
     */
    public function global_factor_z_callback() {
        $options = get_option($this->option_name);
        $global_factor_z = isset($options['global_factor_z']) ? $options['global_factor_z'] : 0;
        
        echo '<input type="number" step="0.01" name="' . $this->option_name . '[global_factor_z]" value="' . esc_attr($global_factor_z) . '" class="regular-text" />';
        echo '<p class="description">' . __('Giá trị Z dùng cho công thức tính giá khi không có giá trị cụ thể ở Region.', 'prepaid-pricing') . '</p>';
    }

    /**
     * Xử lý và làm sạch dữ liệu cài đặt
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['global_factor_y'])) {
            $sanitized['global_factor_y'] = floatval($input['global_factor_y']);
        }
        
        if (isset($input['global_factor_z'])) {
            $sanitized['global_factor_z'] = floatval($input['global_factor_z']);
        }
        
        return $sanitized;
    }

    /**
     * Render form cài đặt toàn cục
     */
    public function render_settings_form() {
        ?>
        <div class="prepaid-pricing-container">
            <div class="prepaid-pricing-header">
                <h2><?php echo esc_html__('Cài đặt Y và Z toàn cục', 'prepaid-pricing'); ?></h2>
            </div>
            
            <div class="prepaid-pricing-content">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('prepaid_pricing_settings');
                    do_settings_sections('prepaid_pricing_settings');
                    submit_button(__('Lưu cài đặt', 'prepaid-pricing'));
                    ?>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Hàm static để lấy cài đặt toàn cục từ bất kỳ đâu trong plugin
     */
    public static function get_global_settings() {
        $default = array(
            'global_factor_y' => 0,
            'global_factor_z' => 0
        );
        
        $options = get_option('prepaid_pricing_global_settings', $default);
        
        return array(
            'global_factor_y' => isset($options['global_factor_y']) ? floatval($options['global_factor_y']) : 0,
            'global_factor_z' => isset($options['global_factor_z']) ? floatval($options['global_factor_z']) : 0
        );
    }
}