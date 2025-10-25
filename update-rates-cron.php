<?php
// Tìm đường dẫn đến wp-load.php
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {
    die('Không thể tìm thấy WordPress.');
}

// Bảo mật với khóa bí mật
$secret_key = 'cnoiwencwoi28374';

// Kiểm tra xác thực
if (empty($_GET['key']) || $_GET['key'] !== $secret_key) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Access Forbidden';
    exit;
}

// Tải file class-rates.php
$rates_class_path = dirname(__FILE__) . '/includes/core/class-rates.php';
if (!file_exists($rates_class_path)) {
    header('HTTP/1.0 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'Không tìm thấy file class-rates.php'
    ]);
    exit;
}

// Tải autoloader nếu cần
$autoloader_path = dirname(__FILE__) . '/vendor/autoload.php';
if (file_exists($autoloader_path)) {
    require_once($autoloader_path);
}

// Tải class Rates
require_once($rates_class_path);

// Khởi tạo class Rates và cập nhật tỷ giá
$rates = new Prepaid_Pricing\Core\Rates();
$result = $rates->fetch_and_save_rates();

// Ghi log
error_log('Prepaid Pricing: Rates updated by cron at ' . date('Y-m-d H:i:s'));

// Trả về kết quả dưới dạng JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => $result,
    'message' => $result ? 'Tỷ giá đã được cập nhật thành công' : 'Có lỗi khi cập nhật tỷ giá',
    'time' => current_time('mysql'),
    'data' => $rates->get_saved_rates()
]);