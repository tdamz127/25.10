<div class="wrap prepaid-pricing-wrap">
    <h1><?php echo esc_html__('Bảng điều khiển Prepaid Pricing', 'prepaid-pricing'); ?></h1>
    
    <div class="prepaid-pricing-container">
        <div class="prepaid-pricing-header">
            <h2><?php echo esc_html__('Tỷ giá hối đoái hiện tại', 'prepaid-pricing'); ?></h2>
            <button id="update-rates-manually" class="button button-primary">
                <?php echo esc_html__('Cập nhật tỷ giá thủ công', 'prepaid-pricing'); ?>
            </button>
        </div>
        
        <div class="prepaid-pricing-content">
            <div id="rates-update-message" class="notice inline hidden"></div>
            
            <div class="rates-info">
                <?php if ($rates_data && isset($rates_data['fetched_at_utc'])): ?>
                    <p class="last-updated">
                        <?php printf(
                            __('Cập nhật lần cuối: %s UTC', 'prepaid-pricing'),
                            esc_html($rates_data['fetched_at_utc'])
                        ); ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <div class="rates-table-container">
                <table class="widefat" id="rates-table">
                    <thead>
                        <tr>
                            <th><?php _e('Cặp tiền tệ', 'prepaid-pricing'); ?></th>
                            <th><?php _e('Tỷ giá', 'prepaid-pricing'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($rates_data && isset($rates_data['rates']) && !empty($rates_data['rates'])): ?>
                        <?php foreach ($rates_data['rates'] as $pair => $rate): ?>
                        <tr>
                            <td><?php echo esc_html($pair); ?></td>
                            <td><?php echo esc_html($rate); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2"><?php _e('Không có tỷ giá hối đoái. Vui lòng cập nhật tỷ giá.', 'prepaid-pricing'); ?></td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($rates_data && isset($rates_data['calculated']) && !empty($rates_data['calculated'])): ?>
            <div class="calculated-info">
                <h3><?php _e('Thông tin tỷ giá được tính toán', 'prepaid-pricing'); ?></h3>
                <ul>
                    <?php foreach ($rates_data['calculated'] as $info): ?>
                        <li><?php echo esc_html($info); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if ($rates_data && isset($rates_data['sources']) && !empty($rates_data['sources'])): ?>
            <div class="sources-info">
                <h3><?php _e('Nguồn dữ liệu', 'prepaid-pricing'); ?></h3>
                <ul>
                    <?php foreach ($rates_data['sources'] as $source): ?>
                        <li><a href="<?php echo esc_url($source); ?>" target="_blank"><?php echo esc_html($source); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>