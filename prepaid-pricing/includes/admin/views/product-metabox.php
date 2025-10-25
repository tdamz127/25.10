<?php
// Ngăn truy cập trực tiếp
if (!defined('ABSPATH')) {
    exit;
}

// Lấy giá trị đã lưu
$enabled = get_post_meta($post->ID, '_prepaid_pricing_enabled', true);
$regions = get_post_meta($post->ID, '_prepaid_pricing_regions', true);
// Lấy source URL đã lưu
$source_url = get_post_meta($post->ID, '_prepaid_pricing_source_url', true);

if (empty($regions)) {
    $regions = array();
}

// Thêm một region mặc định nếu chưa có
if (empty($regions)) {
    $regions[] = array(
        'name' => '',
        'currency' => '',
        'face_value_mode' => 'list',
        'face_values' => array(),
        'face_value_min' => 1,
        'face_value_max' => 100,
        'face_value_step' => 1,
        'factor_x' => 1,
        'rate_pair' => '',
        'factor_y' => '',
        'factor_z' => '',
        'stock_status' => 'instock'
    );
}
?>

<div class="prepaid-pricing-metabox">
    <div class="prepaid-pricing-enable">
        <label>
            <input type="checkbox" name="prepaid_pricing_enabled" value="1" <?php checked($enabled, 1); ?>>
            <?php _e('Kích hoạt tính năng Prepaid Pricing cho sản phẩm này', 'prepaid-pricing'); ?>
        </label>
    </div>

    <!-- Thêm trường source URL mới ở đây -->
    <div class="prepaid-pricing-source-url" style="margin-top: 15px; margin-bottom: 20px;">
        <label>
            <strong><?php _e('Nguồn sản phẩm:', 'prepaid-pricing'); ?></strong>
            <input type="url" name="prepaid_pricing_source_url" value="<?php echo esc_url($source_url); ?>" placeholder="<?php _e('Nhập URL nguồn sản phẩm', 'prepaid-pricing'); ?>" class="widefat" style="margin-top: 5px;">
        </label>
    </div>

    <div class="prepaid-pricing-regions-container">
        <h3 class="regions-heading"><?php _e('Quản lý Regions', 'prepaid-pricing'); ?></h3>
        
        <table class="widefat prepaid-pricing-regions-table">
            <thead>
                <tr>
                    <th width="14%"><?php _e('Region', 'prepaid-pricing'); ?></th>
                    <th width="7%"><?php _e('Tiền tệ', 'prepaid-pricing'); ?></th>
                    <th width="7%"><?php _e('Chế độ', 'prepaid-pricing'); ?></th>
                    <th width="20%"><?php _e('Mệnh giá', 'prepaid-pricing'); ?></th>
                    <th width="5%"><?php _e('X', 'prepaid-pricing'); ?></th>
                    <th width="10%"><?php _e('Cặp tỷ giá', 'prepaid-pricing'); ?></th>
                    <th width="7%"><?php _e('Y (%)', 'prepaid-pricing'); ?></th>
                    <th width="7%"><?php _e('Z', 'prepaid-pricing'); ?></th>
                    <th width="7%"><?php _e('Kho', 'prepaid-pricing'); ?></th>
                    <th width="8%"><?php _e('Thao tác', 'prepaid-pricing'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($regions as $index => $region): ?>
                <tr class="prepaid-pricing-region-row">
                    <td>
                        <input type="text" class="region-name" name="prepaid_pricing_region_name[]" value="<?php echo esc_attr($region['name']); ?>" required>
                    </td>
                    <td>
                        <input type="text" class="region-currency" name="prepaid_pricing_region_currency[]" value="<?php echo esc_attr($region['currency']); ?>" required>
                    </td>
                    <td>
                        <select name="prepaid_pricing_face_value_mode[]" class="face-value-mode-selector">
                            <option value="list" <?php selected(($region['face_value_mode'] ?? 'list'), 'list'); ?>><?php _e('Danh sách', 'prepaid-pricing'); ?></option>
                            <option value="custom" <?php selected(($region['face_value_mode'] ?? 'list'), 'custom'); ?>><?php _e('Tùy chỉnh', 'prepaid-pricing'); ?></option>
                        </select>
                    </td>
                    <td>
                        <div class="face-value-list-container" style="<?php echo (($region['face_value_mode'] ?? 'list') === 'custom') ? 'display:none;' : ''; ?>">
                            <input type="text" name="prepaid_pricing_region_face_values[]" value="<?php echo esc_attr(implode(',', $region['face_values'] ?? [])); ?>" placeholder="<?php _e('Ví dụ: 3,5,10,15', 'prepaid-pricing'); ?>">
                        </div>
                        <div class="face-value-custom-container" style="<?php echo (($region['face_value_mode'] ?? 'list') === 'list') ? 'display:none;' : ''; ?>">
                            <div class="face-value-custom-fields">
                                <input type="number" name="prepaid_pricing_face_value_min[]" placeholder="Min" value="<?php echo esc_attr($region['face_value_min'] ?? 1); ?>" step="0.1">
                                <input type="number" name="prepaid_pricing_face_value_max[]" placeholder="Max" value="<?php echo esc_attr($region['face_value_max'] ?? 100); ?>" step="0.1">
                                <input type="number" name="prepaid_pricing_face_value_step[]" placeholder="Step" value="<?php echo esc_attr($region['face_value_step'] ?? 1); ?>" step="0.1">
                            </div>
                        </div>
                    </td>
                    <td>
                        <input type="number" class="factor-x-input" name="prepaid_pricing_region_factor_x[]" value="<?php echo esc_attr($region['factor_x']); ?>" step="0.00001" required>
                    </td>
                    <td>
                        <select name="prepaid_pricing_region_rate_pair[]" class="rate-pair-select" required>
                            <option value=""><?php _e('Chọn cặp', 'prepaid-pricing'); ?></option>
                            <?php foreach ($rate_pairs as $rate_pair): ?>
                                <option value="<?php echo esc_attr($rate_pair); ?>" <?php selected($region['rate_pair'], $rate_pair); ?>>
                                    <?php echo esc_html($rate_pair); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <input type="number" class="factor-y-input" name="prepaid_pricing_region_factor_y[]" value="<?php echo esc_attr($region['factor_y']); ?>" step="0.01" placeholder="<?php echo $global_settings['global_factor_y']; ?>" title="<?php _e('Để trống để sử dụng giá trị toàn cục', 'prepaid-pricing'); ?>">
                    </td>
                    <td>
                        <input type="number" class="factor-z-input" name="prepaid_pricing_region_factor_z[]" value="<?php echo esc_attr($region['factor_z']); ?>" step="0.01" placeholder="<?php echo $global_settings['global_factor_z']; ?>" title="<?php _e('Để trống để sử dụng giá trị toàn cục', 'prepaid-pricing'); ?>">
                    </td>
                    <td>
                        <select name="prepaid_pricing_region_stock_status[]" class="stock-status-select">
                            <option value="instock" <?php selected($region['stock_status'], 'instock'); ?>><?php _e('Còn', 'prepaid-pricing'); ?></option>
                            <option value="outofstock" <?php selected($region['stock_status'], 'outofstock'); ?>><?php _e('Hết', 'prepaid-pricing'); ?></option>
                        </select>
                    </td>
                    <td class="actions-column">
                        <div class="action-buttons">
                            <button type="button" class="button button-small view-rates" title="<?php _e('Xem giá', 'prepaid-pricing'); ?>"><span class="dashicons dashicons-visibility"></span></button>
                            <button type="button" class="button button-small remove-region" title="<?php _e('Xóa', 'prepaid-pricing'); ?>"><span class="dashicons dashicons-trash"></span></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="button" class="button add-region"><?php _e('Thêm Region mới', 'prepaid-pricing'); ?></button>
    </div>
    
    <div class="prepaid-pricing-formula">
        <p><strong><?php _e('Công thức:', 'prepaid-pricing'); ?></strong> <?php _e('Mệnh giá × X × Tỷ giá × (100 + Y)% + Z', 'prepaid-pricing'); ?></p>
        <p><strong><?php printf(__('Cài đặt toàn cục:', 'prepaid-pricing')); ?></strong> Y = <?php echo $global_settings['global_factor_y']; ?>%, Z = <?php echo $global_settings['global_factor_z']; ?></p>
        <a href="<?php echo admin_url('admin.php?page=prepaid-pricing'); ?>" class="edit-global-link"><?php _e('Chỉnh sửa', 'prepaid-pricing'); ?></a></p>
    </div>

    <!-- Template cho việc thêm region mới -->
    <script type="text/template" id="prepaid-pricing-region-template">
        <tr class="prepaid-pricing-region-row">
            <td>
                <input type="text" class="region-name" name="prepaid_pricing_region_name[]" value="" required>
            </td>
            <td>
                <input type="text" class="region-currency" name="prepaid_pricing_region_currency[]" value="" required>
            </td>
            <td>
                <select name="prepaid_pricing_face_value_mode[]" class="face-value-mode-selector">
                    <option value="list"><?php _e('Danh sách', 'prepaid-pricing'); ?></option>
                    <option value="custom"><?php _e('Tùy chỉnh', 'prepaid-pricing'); ?></option>
                </select>
            </td>
            <td>
                <div class="face-value-list-container">
                    <input type="text" name="prepaid_pricing_region_face_values[]" value="" placeholder="<?php _e('Ví dụ: 3,5,10,15', 'prepaid-pricing'); ?>">
                </div>
                <div class="face-value-custom-container" style="display:none;">
                    <div class="face-value-custom-fields">
                        <input type="number" name="prepaid_pricing_face_value_min[]" placeholder="Min" value="1" step="0.1">
                        <input type="number" name="prepaid_pricing_face_value_max[]" placeholder="Max" value="100" step="0.1">
                        <input type="number" name="prepaid_pricing_face_value_step[]" placeholder="Step" value="1" step="0.1">
                    </div>
                </div>
            </td>
            <td>
                <input type="number" class="factor-x-input" name="prepaid_pricing_region_factor_x[]" value="1" step="0.00001" required>
            </td>
            <td>
                <select name="prepaid_pricing_region_rate_pair[]" class="rate-pair-select" required>
                    <option value=""><?php _e('Chọn cặp', 'prepaid-pricing'); ?></option>
                </select>
            </td>
            <td>
                <input type="number" class="factor-y-input" name="prepaid_pricing_region_factor_y[]" value="" step="0.01" placeholder="<?php echo $global_settings['global_factor_y']; ?>" title="<?php _e('Để trống để sử dụng giá trị toàn cục', 'prepaid-pricing'); ?>">
            </td>
            <td>
                <input type="number" class="factor-z-input" name="prepaid_pricing_region_factor_z[]" value="" step="0.01" placeholder="<?php echo $global_settings['global_factor_z']; ?>" title="<?php _e('Để trống để sử dụng giá trị toàn cục', 'prepaid-pricing'); ?>">
            </td>
            <td>
                <select name="prepaid_pricing_region_stock_status[]" class="stock-status-select">
                    <option value="instock"><?php _e('Còn', 'prepaid-pricing'); ?></option>
                    <option value="outofstock"><?php _e('Hết', 'prepaid-pricing'); ?></option>
                </select>
            </td>
            <td class="actions-column">
                <div class="action-buttons">
                    <button type="button" class="button button-small view-rates" title="<?php _e('Xem giá', 'prepaid-pricing'); ?>"><span class="dashicons dashicons-visibility"></span></button>
                    <button type="button" class="button button-small remove-region" title="<?php _e('Xóa', 'prepaid-pricing'); ?>"><span class="dashicons dashicons-trash"></span></button>
                </div>
            </td>
        </tr>
    </script>

    <!-- Modal hiển thị bảng giá -->
    <div class="prepaid-pricing-modal" id="prepaid-pricing-rates-modal">
        <div class="prepaid-pricing-modal-content">
            <span class="prepaid-pricing-modal-close">&times;</span>
            <h2><?php _e('Bảng tính giá', 'prepaid-pricing'); ?></h2>
            <div class="prepaid-pricing-modal-loading"><?php _e('Đang tải...', 'prepaid-pricing'); ?></div>
            <div class="prepaid-pricing-modal-body"></div>
        </div>
    </div>

    <!-- Rates data for JS - IMPORTANT: Make sure this exists -->
    <script type="text/javascript">
        // Đảm bảo các biến cần thiết được định nghĩa
        var prepaidPricingRates = <?php echo json_encode($rates_data['rates'] ?? array()); ?>;
        var prepaidPricingGlobalSettings = <?php echo json_encode($global_settings); ?>;
        var prepaidPricingRatePairs = <?php echo json_encode($rate_pairs); ?>;
        
        // Debug info
        console.log('Prepaid Pricing data loaded');
        console.log('Rate pairs:', prepaidPricingRatePairs);
        console.log('Global settings:', prepaidPricingGlobalSettings);
    </script>
</div>