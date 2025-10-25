/**
 * Admin JavaScript for Prepaid Pricing Plugin
 */
jQuery(document).ready(function($) {
    
    /**
     * Xử lý cập nhật tỷ giá thủ công
     */
    $('#update-rates-manually').on('click', function() {
        var $button = $(this);
        var $message = $('#rates-update-message');
        
        $button.addClass('updating-message').prop('disabled', true);
        $button.text(prepaid_pricing_params.i18n.updating);
        
        $message.removeClass('notice-success notice-error').addClass('notice').html('').hide();
        
        $.ajax({
            url: prepaid_pricing_params.ajax_url,
            type: 'POST',
            data: {
                action: 'prepaid_pricing_update_rates_manually',
                nonce: prepaid_pricing_params.nonce
            },
            success: function(response) {
                $button.removeClass('updating-message').prop('disabled', false);
                $button.text(prepaid_pricing_params.i18n.update_rates);
                
                if (response.success) {
                    $message.addClass('notice-success').html('<p>' + response.data.message + '</p>').show();
                    
                    // Cập nhật lại bảng tỷ giá
                    if (response.data.rates_data) {
                        var rates = response.data.rates_data.rates || {};
                        var html = '';
                        
                        $.each(rates, function(pair, rate) {
                            html += '<tr><td>' + pair + '</td><td>' + rate + '</td></tr>';
                        });
                        
                        if (html === '') {
                            html = '<tr><td colspan="2">Không có tỷ giá hối đoái. Vui lòng cập nhật tỷ giá.</td></tr>';
                        }
                        
                        $('#rates-table tbody').html(html);
                        $('.last-updated').text('Cập nhật lần cuối: ' + response.data.rates_data.fetched_at_utc + ' UTC');
                    }
                } else {
                    $message.addClass('notice-error').html('<p>' + response.data.message + '</p>').show();
                }
                
                // Tự động ẩn thông báo sau 3 giây
                setTimeout(function() {
                    $message.slideUp();
                }, 3000);
            },
            error: function() {
                $button.removeClass('updating-message').prop('disabled', false);
                $button.text(prepaid_pricing_params.i18n.update_rates);
                $message.addClass('notice-error').html('<p>' + prepaid_pricing_params.i18n.error + '</p>').show();
            }
        });
    });
    
    /**
     * Product Metabox
     */
    // Xử lý khi thêm region mới
    $('.prepaid-pricing-metabox .add-region').on('click', function() {
        var template = $('#prepaid-pricing-region-template').html();
        
        // Thay thế template với danh sách rate pairs thực tế
        if (typeof prepaidPricingRatePairs !== 'undefined') {
            var rateOptions = '<option value="">' + prepaid_pricing_product.i18n.select_rate_pair + '</option>';
            $.each(prepaidPricingRatePairs, function(index, pair) {
                rateOptions += '<option value="' + pair + '">' + pair + '</option>';
            });
            
            // Tạo DOM element từ template để thay thế
            var $template = $(template);
            $template.find('select[name="prepaid_pricing_region_rate_pair[]"]').html(rateOptions);
            template = $template.prop('outerHTML');
        }
        
        $('.prepaid-pricing-regions-table tbody').append(template);
        
        // Thêm hiệu ứng highlight cho hàng mới
        var $newRow = $('.prepaid-pricing-regions-table tbody tr:last');
        $newRow.css('background-color', '#fffbcc');
        setTimeout(function() {
            $newRow.css('transition', 'background-color 1s');
            $newRow.css('background-color', '');
        }, 100);
    });
    
    // Xử lý khi xóa region
    $('.prepaid-pricing-metabox').on('click', '.remove-region', function() {
        if (confirm('Bạn có chắc chắn muốn xóa region này?')) {
            $(this).closest('tr').fadeOut(300, function() {
                $(this).remove();
            });
        }
    });
    
    // Xử lý khi thay đổi chế độ mệnh giá
    $('.prepaid-pricing-metabox').on('change', '.face-value-mode-selector', function() {
        var mode = $(this).val();
        var row = $(this).closest('.prepaid-pricing-region-row');
        
        if (mode === 'list') {
            row.find('.face-value-list-container').slideDown(200);
            row.find('.face-value-custom-container').slideUp(200);
        } else {
            row.find('.face-value-list-container').slideUp(200);
            row.find('.face-value-custom-container').slideDown(200);
        }
    });
    
    /**
     * Xử lý hiển thị bảng tính giá - FIX XEM GIÁ VÀ HIỂN THỊ 5 CHỮ SỐ THẬP PHÂN
     */
    $('.prepaid-pricing-metabox').on('click', '.view-rates', function(e) {
        e.preventDefault();
        console.log('View rates button clicked'); // Debug
        
        var row = $(this).closest('tr');
        var faceModeSelect = row.find('select[name="prepaid_pricing_face_value_mode[]"]');
        var faceValuesInput = row.find('input[name="prepaid_pricing_region_face_values[]"]');
        var faceValueMin = row.find('input[name="prepaid_pricing_face_value_min[]"]');
        var faceValueMax = row.find('input[name="prepaid_pricing_face_value_max[]"]');
        var faceValueStep = row.find('input[name="prepaid_pricing_face_value_step[]"]');
        var factorX = row.find('input[name="prepaid_pricing_region_factor_x[]"]').val();
        var ratePair = row.find('select[name="prepaid_pricing_region_rate_pair[]"]').val();
        
        // Hiện thông báo debug
        console.log('Rate pair:', ratePair);
        console.log('Face value mode:', faceModeSelect.val());
        console.log('Factor X:', factorX);
        
        // Kiểm tra xem có dữ liệu cần thiết không
        if (!ratePair || ratePair === '') {
            alert('Vui lòng chọn cặp tỷ giá trước khi xem giá');
            return;
        }
        
        // Lấy giá trị Y, Z - nếu rỗng thì sử dụng giá trị toàn cục
        var factorY = row.find('input[name="prepaid_pricing_region_factor_y[]"]').val();
        var factorZ = row.find('input[name="prepaid_pricing_region_factor_z[]"]').val();
        
        // Sử dụng giá trị toàn cục nếu Y hoặc Z rỗng
        if (factorY === '') {
            factorY = typeof prepaidPricingGlobalSettings !== 'undefined' ? 
                      prepaidPricingGlobalSettings.global_factor_y : 0;
        }
        
        if (factorZ === '') {
            factorZ = typeof prepaidPricingGlobalSettings !== 'undefined' ? 
                      prepaidPricingGlobalSettings.global_factor_z : 0;
        }
        
        // Kiểm tra biến global
        console.log('Global settings available:', typeof prepaidPricingGlobalSettings !== 'undefined');
        console.log('Rates available:', typeof prepaidPricingRates !== 'undefined');
        
        var currency = row.find('input[name="prepaid_pricing_region_currency[]"]').val();
        var faceValueMode = faceModeSelect.val();
        
        var faceValues = [];
        
        // Lấy danh sách face values tùy theo mode
        if (faceValueMode === 'list') {
            // Lấy face values từ danh sách
            faceValues = faceValuesInput.val().split(',').map(function(item) {
                return parseFloat(item.trim());
            }).filter(function(item) {
                return !isNaN(item);
            });
        } else {
            // Tạo danh sách từ min, max, step
            var min = parseFloat(faceValueMin.val()) || 1;
            var max = parseFloat(faceValueMax.val()) || 100;
            var step = parseFloat(faceValueStep.val()) || 1;
            
            for (var i = min; i <= max; i += step) {
                faceValues.push(parseFloat(i.toFixed(2)));
            }
            
            // Giới hạn số lượng hiển thị để không quá lớn
            if (faceValues.length > 10) {
                faceValues = faceValues.slice(0, 10);
            }
        }
        
        // Kiểm tra có mệnh giá không
        if (faceValues.length === 0) {
            if (faceValueMode === 'list') {
                alert('Vui lòng nhập các mệnh giá (phân cách bằng dấu phẩy)');
            } else {
                alert('Vui lòng điền giá trị Min, Max, Step');
            }
            return;
        }
        
        // Lấy rate từ cấu hình hoặc default = 1
        var rates = typeof prepaidPricingRates !== 'undefined' ? prepaidPricingRates : {};
        var rate = rates[ratePair] || 1;
        
        console.log('Rate for pair ' + ratePair + ':', rate);
        
        // Tạo HTML cho bảng giá
        var tableHtml = '<table class="widefat">';
        tableHtml += '<thead><tr><th>Mệnh giá</th><th>Công thức</th><th>Giá</th></tr></thead>';
        tableHtml += '<tbody>';
        
        for (var i = 0; i < faceValues.length; i++) {
            var faceValue = faceValues[i];
            
            // Hiển thị X với tối đa 5 chữ số thập phân, nhưng bỏ các số 0 không cần thiết
            var formattedX = parseFloat(parseFloat(factorX).toFixed(5));
            if (formattedX.toString().indexOf('.') !== -1) {
                formattedX = formattedX.toString().replace(/\.?0+$/, '');
            }
            
            var price = (faceValue * parseFloat(factorX) * rate * (100 + parseFloat(factorY)) / 100) + parseFloat(factorZ);
            var formula = faceValue + ' × ' + formattedX + ' × ' + rate + ' × (100 + ' + factorY + ')% + ' + factorZ;
            
            tableHtml += '<tr>';
            tableHtml += '<td>' + currency + ' ' + faceValue + '</td>';
            tableHtml += '<td>' + formula + '</td>';
            tableHtml += '<td>' + price.toFixed(2) + '</td>';
            tableHtml += '</tr>';
        }
        
        // Thêm dòng mô tả
        if (faceValueMode === 'custom' && faceValues.length === 10) {
            tableHtml += '<tr><td colspan="3">Hiển thị 10 mức đầu tiên. Giá trị khác sẽ được tính theo công thức tương tự.</td></tr>';
        }
        
        // Thêm thông tin về giá trị toàn cục nếu đang sử dụng
        var usingGlobalY = row.find('input[name="prepaid_pricing_region_factor_y[]"]').val() === '';
        var usingGlobalZ = row.find('input[name="prepaid_pricing_region_factor_z[]"]').val() === '';
        
        if (usingGlobalY || usingGlobalZ) {
            tableHtml += '<tr><td colspan="3" style="background-color: #f9f9f9;">';
            tableHtml += '<strong>Lưu ý:</strong> Đang sử dụng ';
            if (usingGlobalY) tableHtml += 'Y toàn cục (' + factorY + '%)';
            if (usingGlobalY && usingGlobalZ) tableHtml += ' và ';
            if (usingGlobalZ) tableHtml += 'Z toàn cục (' + factorZ + ')';
            tableHtml += '</td></tr>';
        }
        
        tableHtml += '</tbody></table>';
        
        // Hiển thị modal
        var $modal = $('#prepaid-pricing-rates-modal');
        $modal.css('display', 'block');
        $('.prepaid-pricing-modal-loading').hide();
        $('.prepaid-pricing-modal-body').html(tableHtml);
        
        console.log('Modal should be displayed now');
    });
    
    // Đóng modal khi nhấn nút đóng
    $('.prepaid-pricing-modal-close').on('click', function() {
        $('#prepaid-pricing-rates-modal').css('display', 'none');
    });
    
    // Đóng modal khi nhấn bên ngoài
    $(window).on('click', function(event) {
        var $modal = $('#prepaid-pricing-rates-modal');
        if ($(event.target).is($modal)) {
            $modal.css('display', 'none');
        }
    });
    
    // Fix độ rộng select trên load
    $(window).on('load', function() {
        $('.rate-pair-select').each(function() {
            $(this).css('width', '100%');
        });
    });
    
    // Tooltip cho các trường input
    $('.prepaid-pricing-metabox input[placeholder], .prepaid-pricing-metabox select[title]').tooltip({
        position: {
            my: "center bottom-20",
            at: "center top",
            using: function(position, feedback) {
                $(this).css(position);
                $("<div>")
                    .addClass("arrow")
                    .addClass(feedback.vertical)
                    .addClass(feedback.horizontal)
                    .appendTo(this);
            }
        }
    });
    
    // Khởi động vùng tab cho form cài đặt
    if ($('.prepaid-pricing-tabs').length) {
        $('.prepaid-pricing-tabs').tabs();
    }
});