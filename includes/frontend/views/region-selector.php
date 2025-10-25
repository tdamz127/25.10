<?php
// Debug log - Không hiển thị trên giao diện người dùng
error_log('Prepaid Pricing: Rendering region selector. Regions data: ' . print_r($regions, true));

// Force hiển thị lỗi nếu có để debug
ini_set('display_errors', 1);
error_reporting(E_ALL);
?>

<div class="prepaid-pricing-region-selection" style="border: 1px solid #eee; padding: 15px; margin: 15px 0; clear: both;">
    <h3><?php _e('Select Region and Value', 'prepaid-pricing'); ?></h3>
    
    <form class="cart" method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('woocommerce-add-to-cart'); ?>
        
        <!-- Region Selector -->
        <div class="prepaid-pricing-field">
            <h3 class="field-heading"><?php _e('Region', 'prepaid-pricing'); ?></h3>
            
            <div class="field-input">
                <select name="prepaid_pricing_region" id="prepaid-pricing-region" required>
                    <option value=""><?php _e('Select Region', 'prepaid-pricing'); ?></option>
                    <?php foreach ($regions as $region_index => $region): ?>
                        <?php if ($region['stock_status'] == 'instock'): ?>
                            <option value="<?php echo esc_attr($region_index); ?>"
                                    data-currency="<?php echo esc_attr($region['currency']); ?>"
                                    data-face-value-mode="<?php echo esc_attr($region['face_value_mode'] ?? 'list'); ?>"
                                    data-face-value-min="<?php echo esc_attr($region['face_value_min'] ?? 1); ?>"
                                    data-face-value-max="<?php echo esc_attr($region['face_value_max'] ?? 100); ?>"
                                    data-face-value-step="<?php echo esc_attr($region['face_value_step'] ?? 1); ?>">
                                <?php echo esc_html($region['name']); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <!-- Value Selector -->
        <div class="prepaid-pricing-field">
            <h3 class="field-heading"><?php _e('Value', 'prepaid-pricing'); ?></h3>
            
            <div class="field-input">
                <!-- Hidden input to always submit the value -->
                <input type="hidden" name="prepaid_pricing_face_value" id="prepaid-pricing-face-value-hidden" value="">
                
                <!-- Dropdown for list mode (default) -->
                <select name="prepaid_pricing_face_value_list" id="prepaid-pricing-face-value-list" class="face-value-list-select" required disabled>
                    <option value=""><?php _e('Select Value', 'prepaid-pricing'); ?></option>
                </select>
                
                <!-- Number input for custom mode -->
                <div class="face-value-custom-input" style="display: none;">
                    <div class="input-currency-container">
                        <input type="number" name="prepaid_pricing_face_value_custom" id="prepaid-pricing-face-value-custom" 
                               step="1" min="1" max="100" value="" required disabled>
                        <span class="face-value-currency-symbol"></span>
                    </div>
                    <small class="face-value-custom-guide">Min: 1 Max: 100 Step: 1</small>
                </div>
            </div>
        </div>

        <!-- Price Display -->
        <div class="prepaid-pricing-price">
            <h3 class="price-label"><?php _e('Price:', 'prepaid-pricing'); ?> <span class="price-amount">$0.00</span></h3>
        </div>

        <!-- Add to Cart Section -->
        <div class="prepaid-pricing-cart-buttons">
            <div class="quantity-wrapper">
                <div class="quantity">
                    <button type="button" class="minus">-</button>
                    <input type="number" name="quantity" value="1" min="1" max="100" step="1" id="prepaid-quantity" class="qty" />
                    <button type="button" class="plus">+</button>
                </div>
            </div>
            
            <div class="button-group">
                <!-- QUAN TRỌNG: Thêm input hidden với tên "add-to-cart" -->
                <input type="hidden" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" />
                
                <!-- Thêm các field debug -->
                <input type="hidden" name="pp_debug_timestamp" value="<?php echo time(); ?>" />
                <input type="hidden" name="pp_form_id" value="prepaid_pricing_form" />
                
                <button type="submit" class="single_add_to_cart_button button alt" disabled>
                    <?php _e('ADD TO CART', 'prepaid-pricing'); ?>
                </button>
                
                <button type="submit" name="buy_now" value="1" class="buy-now-button button alt" disabled>
                    <?php _e('BUY NOW', 'prepaid-pricing'); ?>
                </button>
            </div>
        </div>
        
        <!-- Hidden JSON data cho frontend -->
        <script type="text/template" id="prepaid-pricing-regions-data">
            <?php echo wp_json_encode($regions); ?>
        </script>
    </form>
</div>