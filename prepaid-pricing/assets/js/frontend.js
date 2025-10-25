jQuery(document).ready(function($) {
    console.log('Prepaid Pricing: Frontend script loaded');
    
    // Kiểm tra xem có element regions data không
    var regionsDataElement = document.getElementById('prepaid-pricing-regions-data');
    console.log('Prepaid Pricing: Region data element exists:', regionsDataElement !== null);
    
    if (!regionsDataElement) {
        return;
    }
    
    // Parse dữ liệu regions
    var regionsData = JSON.parse(regionsDataElement.textContent);
    console.log('Prepaid Pricing: Regions data parsed successfully:', regionsData);
    
    // Các element cần thiết
    var $regionSelect = $('#prepaid-pricing-region');
    var $faceValueListSelect = $('#prepaid-pricing-face-value-list');
    var $faceValueCustomInput = $('#prepaid-pricing-face-value-custom');
    var $faceValueHidden = $('#prepaid-pricing-face-value-hidden');
    var $priceDisplay = $('.price-amount');
    var $addToCartButton = $('.single_add_to_cart_button');
    var $buyNowButton = $('.buy-now-button');
    var $faceValueCurrencySymbol = $('.face-value-currency-symbol');
    
    // Kiểm tra các element
    console.log('Prepaid Pricing: DOM elements loaded:', {
        'regionSelect exists': $regionSelect.length > 0,
        'faceValueListSelect exists': $faceValueListSelect.length > 0,
        'faceValueCustomInput exists': $faceValueCustomInput.length > 0,
        'priceDisplay exists': $priceDisplay.length > 0,
        'addToCartButton exists': $addToCartButton.length > 0,
        'buyNowButton exists': $buyNowButton.length > 0
    });
    
    // Vô hiệu hóa button Add to Cart khi chưa chọn đủ thông tin
    function disableAddToCartButton() {
        $addToCartButton.prop('disabled', true);
        $buyNowButton.prop('disabled', true);
    }
    
    // Kích hoạt button Add to Cart khi đã chọn đủ thông tin
    function enableAddToCartButton() {
        $addToCartButton.prop('disabled', false);
        $buyNowButton.prop('disabled', false);
    }
    
    // Khi chọn region
    $regionSelect.on('change', function() {
        console.log('Prepaid Pricing: Region selection changed');
        
        var selectedRegionIndex = $(this).val();
        
        if (selectedRegionIndex === '') {
            $faceValueListSelect.prop('disabled', true).val('');
            $faceValueCustomInput.prop('disabled', true).val('');
            $('.face-value-list-select, .face-value-custom-input').hide();
            $faceValueHidden.val('');
            disableAddToCartButton();
            return;
        }
        
        var selectedRegion = regionsData[selectedRegionIndex];
        console.log('Prepaid Pricing: Selected region:', selectedRegion);
        
        // Reset face value
        $faceValueListSelect.empty().append('<option value="">' + prepaid_pricing_frontend.i18n.select_value + '</option>');
        $faceValueCustomInput.val('');
        $faceValueHidden.val('');
        
        // Cập nhật symbol tiền tệ
        $faceValueCurrencySymbol.text(selectedRegion.currency);
        
        // Hiển thị giao diện phù hợp với mode
        var faceValueMode = selectedRegion.face_value_mode || 'list';
        
        if (faceValueMode === 'custom') {
            // Chế độ custom range
            $('#prepaid-pricing-face-value-list').hide();
            $('.face-value-custom-input').show();
            
            // Cập nhật thuộc tính cho input
            $faceValueCustomInput.prop('disabled', false)
                .attr('min', selectedRegion.face_value_min || 1)
                .attr('max', selectedRegion.face_value_max || 100)
                .attr('step', selectedRegion.face_value_step || 1)
                .val(selectedRegion.face_value_min || 1);
            
            // Cập nhật text hướng dẫn
            $('.face-value-custom-guide').text('Min: ' + (selectedRegion.face_value_min || 1) + ' Max: ' + (selectedRegion.face_value_max || 100) + ' Step: ' + (selectedRegion.face_value_step || 1));
            
            // Trigger change để cập nhật giá
            $faceValueCustomInput.trigger('change');
        } else {
            // Chế độ danh sách
            $('#prepaid-pricing-face-value-list').show();
            $('.face-value-custom-input').hide();
            
            // Cập nhật danh sách face values
            if (selectedRegion.face_values && selectedRegion.face_values.length) {
                $.each(selectedRegion.face_values, function(i, face_value) {
                    var price = calculatePrice(face_value, selectedRegion);
                    // ĐÃ SỬA: Đổi thứ tự - số tiền trước, currency sau
                    var optionText = face_value + ' ' + selectedRegion.currency;
                    $('<option>').val(face_value).text(optionText).appendTo($faceValueListSelect);
                });
            }
            
            $faceValueListSelect.prop('disabled', false);
        }
    });
    
    // Xử lý khi chọn face value từ danh sách
    $faceValueListSelect.on('change', function() {
        console.log('Prepaid Pricing: Face value selection changed');
        
        var selectedRegionIndex = $regionSelect.val();
        
        if (selectedRegionIndex === '' || $(this).val() === '') {
            disableAddToCartButton();
            $faceValueHidden.val('');
            return;
        }
        
        var selectedRegion = regionsData[selectedRegionIndex];
        var face_value = parseFloat($(this).val());
        
        // Cập nhật hidden input
        $faceValueHidden.val(face_value);
        
        // Tính và hiển thị giá
        var price = calculatePrice(face_value, selectedRegion);
        $priceDisplay.text(formatPrice(price));
        
        // Kích hoạt button
        enableAddToCartButton();
    });
    
    // Xử lý khi nhập custom face value
    $faceValueCustomInput.on('change', function() {
        console.log('Prepaid Pricing: Custom face value changed');
        
        var selectedRegionIndex = $regionSelect.val();
        
        if (selectedRegionIndex === '' || $(this).val() === '') {
            disableAddToCartButton();
            $faceValueHidden.val('');
            return;
        }
        
        var selectedRegion = regionsData[selectedRegionIndex];
        var face_value = parseFloat($(this).val());
        
        // Kiểm tra giới hạn min-max
        var min = parseFloat(selectedRegion.face_value_min || 1);
        var max = parseFloat(selectedRegion.face_value_max || 100);
        
        if (face_value < min) {
            $(this).val(min);
            face_value = min;
        }
        
        if (face_value > max) {
            $(this).val(max);
            face_value = max;
        }
        
        // Cập nhật hidden input
        $faceValueHidden.val(face_value);
        
        // Tính và hiển thị giá
        var price = calculatePrice(face_value, selectedRegion);
        $priceDisplay.text(formatPrice(price));
        
        // Kích hoạt button
        enableAddToCartButton();
    });
    
    // Xử lý button +/- số lượng
    $('.minus').on('click', function() {
        var $input = $(this).next('input.qty');
        var val = parseInt($input.val());
        if (val > 1) {
            $input.val(val - 1).change();
        }
    });
    
    $('.plus').on('click', function() {
        var $input = $(this).prev('input.qty');
        var val = parseInt($input.val());
        if (val < 100) {
            $input.val(val + 1).change();
        }
    });
    
    // Helper function để tính giá
    function calculatePrice(faceValue, region) {
        console.log('Prepaid Pricing: Calculating price with data:', {
            faceValue: faceValue, 
            factor_x: region.factor_x, 
            rate_pair: region.rate_pair,
            factor_y: region.factor_y || prepaid_pricing_frontend.global_settings.global_factor_y,
            factor_z: region.factor_z || prepaid_pricing_frontend.global_settings.global_factor_z
        });
        
        var rate = prepaid_pricing_frontend.rates[region.rate_pair] || 1;
        console.log('Prepaid Pricing: Using exchange rate:', rate);
        
        // Sử dụng giá trị Y và Z từ region, nếu không có thì dùng global
        var factorY = (region.factor_y !== undefined && region.factor_y !== '') 
            ? parseFloat(region.factor_y) 
            : parseFloat(prepaid_pricing_frontend.global_settings.global_factor_y);
        
        var factorZ = (region.factor_z !== undefined && region.factor_z !== '') 
            ? parseFloat(region.factor_z) 
            : parseFloat(prepaid_pricing_frontend.global_settings.global_factor_z);
        
        var price = (faceValue * region.factor_x * rate * (100 + factorY) / 100) + factorZ;
        console.log('Prepaid Pricing: Calculated price:', price);
        
        return price;
    }
    
    // Helper function để định dạng giá
    function formatPrice(price) {
        return accounting.formatMoney(
            price,
            prepaid_pricing_frontend.currency_symbol,
            prepaid_pricing_frontend.decimals,
            prepaid_pricing_frontend.thousand_sep,
            prepaid_pricing_frontend.decimal_sep,
            prepaid_pricing_frontend.currency_format
        );
    }
    
    // Xử lý form submit
    $('.prepaid-pricing-region-selection form').on('submit', function() {
        // Đảm bảo face value được submit từ input ẩn
        var selectedRegionIndex = $regionSelect.val();
        if (selectedRegionIndex !== '') {
            var selectedRegion = regionsData[selectedRegionIndex];
            var faceValueMode = selectedRegion.face_value_mode || 'list';
            
            if (faceValueMode === 'custom') {
                $faceValueHidden.val($faceValueCustomInput.val());
            } else {
                $faceValueHidden.val($faceValueListSelect.val());
            }
        }
    });
});