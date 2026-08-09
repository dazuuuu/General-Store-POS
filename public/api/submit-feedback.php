<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

error_reporting(0);
ini_set('display_errors', 0);

try {
    require_once __DIR__ . '/../../app/bootstrap.php';
    require_once __DIR__ . '/../../app/config/db_connect.php';
    require_once __DIR__ . '/../../app/controllers/TestimonialController.php';
    
    $testimonialController = new TestimonialController($pdo);
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $result = $testimonialController->submit($input);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log('Feedback submit error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>