/**
 * JavaScript cho trang chỉnh sửa sản phẩm
 */
jQuery(document).ready(function($) {
    
    // Khởi tạo số lượng region hiện tại
    let regionCount = $('#prepaid-pricing-regions .region-row').length;
    
    // Hiển thị/ẩn phần region khi bật/tắt tính năng
    $('#prepaid_pricing_enabled').on('change', function() {
        if ($(this).is(':checked')) {
            $('#prepaid-pricing-regions-wrapper').show();
        } else {
            $('#prepaid-pricing-regions-wrapper').hide();
        }
    });
    
    // Thêm region mới
    $('.add-region').on('click', function(e) {
        e.preventDefault();
        
        // Xóa thông báo không có region nếu có
        $('.no-regions-row').remove();
        
        // Sử dụng template cho region mới
        const template = wp.template('prepaid-pricing-region-row');
        const newRow = template({ index: regionCount });
        
        // Thêm vào bảng
        $('#prepaid-pricing-regions').append(newRow);
        
        // Tăng biến đếm
        regionCount++;
        
        // Kích hoạt các sự kiện trên phần tử mới
        initRegionEvents();
    });
    
    // Xóa region
    $(document).on('click', '.remove-region', function(e) {
        e.preventDefault();
        
        // Xóa dòng region hiện tại và dòng giá tương ứng
        const $row = $(this).closest('.region-row');
        const index = $row.index();
        $row.next('.region-prices-row').remove();
        $row.remove();
        
        // Nếu không còn region nào, hiển thị thông báo
        if ($('#prepaid-pricing-regions .region-row').length === 0) {
            $('#prepaid-pricing-regions').append('<tr class="no-regions-row"><td colspan="9">Chưa có Region nào. Hãy thêm Region mới bằng nút phía trên.</td></tr>');
        }
    });

    // Xử lý nút xem giá
    $(document).on('click', '.preview-prices', function(e) {
        e.preventDefault();
        
        const $row = $(this).closest('.region-row');
        const $pricesRow = $row.next('.region-prices-row');
        const $pricesTable = $pricesRow.find('.region-prices-table');
        
        // Nếu đang hiển thị thì ẩn đi
        if ($pricesRow.is(':visible')) {
            $pricesRow.hide();
            $(this).text(prepaid_pricing_product.i18n.show_prices);
            return;
        }
        
        // Hiện bảng và đổi tên nút
        $pricesRow.show();
        $(this).text(prepaid_pricing_product.i18n.hide_prices);
        
        // Lấy dữ liệu để tính giá
        const face_values_str = $row.find('input[name*="[face_values]"]').val();
        const x = parseFloat($row.find('input[name*="[factor_x]"]').val()) || 0;
        const y = parseFloat($row.find('input[name*="[factor_y]"]').val()) || 0;
        const z = parseFloat($row.find('input[name*="[factor_z]"]').val()) || 0;
        const ratePair = $row.find('select[name*="[rate_pair]"]').val();
        
        // Xóa nội dung cũ
        $pricesTable.empty();
        
        // Kiểm tra dữ liệu hợp lệ
        if (!face_values_str || !ratePair || !prepaid_pricing_product.rates[ratePair]) {
            $pricesTable.html('<p>Vui lòng điền đầy đủ thông tin để xem giá dự tính.</p>');
            return;
        }
        
        // Phân tích danh sách mệnh giá
        const face_values = face_values_str.split(',').map(v => parseFloat(v.trim())).filter(v => !isNaN(v));
        
        if (face_values.length === 0) {
            $pricesTable.html('<p>Vui lòng nhập danh sách mệnh giá hợp lệ.</p>');
            return;
        }
        
        // Lấy tỷ giá
        const rate = parseFloat(prepaid_pricing_product.rates[ratePair]);
        
        // Tạo bảng giá
        let tableHTML = '<table class="widefat striped">';
        tableHTML += '<thead><tr><th>' + prepaid_pricing_product.i18n.face_value + '</th><th>' + prepaid_pricing_product.i18n.calculated_price + '</th></tr></thead>';
        tableHTML += '<tbody>';
        
        for (let i = 0; i < face_values.length; i++) {
            const face_value = face_values[i];
            // Công thức: Mệnh giá * X * rate * (100 + Y)% + Z
            const price = (face_value * x * rate * (100 + y) / 100) + z;
            
            tableHTML += '<tr>';
            tableHTML += '<td>' + face_value + '</td>';
            tableHTML += '<td>' + formatCurrency(price) + '</td>';
            tableHTML += '</tr>';
        }
        
        tableHTML += '</tbody></table>';
        
        // Hiển thị bảng
        $pricesTable.html(tableHTML);
    });
    
    // Tính toán giá khi thay đổi input
    function initRegionEvents() {
        $('.region-row input, .region-row select').on('change', function() {
            // Khi thay đổi dữ liệu, ẩn bảng giá nếu đang hiển thị
            const $row = $(this).closest('.region-row');
            const $pricesRow = $row.next('.region-prices-row');
            
            if ($pricesRow.is(':visible')) {
                $pricesRow.hide();
                $row.find('.preview-prices').text(prepaid_pricing_product.i18n.show_prices);
            }
        });
    }
    
    // Định dạng giá tiền
    function formatCurrency(price) {
        if (typeof woocommerce_admin_meta_boxes !== 'undefined' && typeof accounting !== 'undefined') {
            return accounting.formatMoney(price, {
                symbol: woocommerce_admin_meta_boxes.currency_format_symbol,
                decimal: woocommerce_admin_meta_boxes.currency_format_decimal_sep,
                thousand: woocommerce_admin_meta_boxes.currency_format_thousand_sep,
                precision: woocommerce_admin_meta_boxes.currency_format_num_decimals,
                format: woocommerce_admin_meta_boxes.currency_format
            });
        } else {
            return price.toFixed(2);
        }
    }
    
    // Khởi tạo các sự kiện cho regions có sẵn
    initRegionEvents();
});