<?php
// public/api/blog/create_category.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

function sendJsonResponse($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit();
}

try {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] > 2) {
        sendJsonResponse(false, 'Unauthorized access');
    }
    
    require_once __DIR__ . '/../../../app/config/db_connect.php';
    require_once __DIR__ . '/../../../app/controllers/BlogController.php';
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['name'])) {
        sendJsonResponse(false, 'Category name is required');
    }
    
    $blogController = new BlogController($pdo);
    $result = $blogController->createCategory(
        $input['name'],
        $input['description'] ?? null,
        $input['color'] ?? '#667eea'
    );
    
    sendJsonResponse($result['success'], $result['message'], ['category_id' => $result['category_id'] ?? null]);
    
} catch (Exception $e) {
    sendJsonResponse(false, 'Server error: ' . $e->getMessage());
}
?>