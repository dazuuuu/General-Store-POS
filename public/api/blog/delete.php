<?php
// public/api/blog/delete.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

function sendJsonResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

try {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] > 2) {
        sendJsonResponse(false, 'Unauthorized access');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $blog_id = $input['id'] ?? null;
    
    if (!$blog_id) {
        sendJsonResponse(false, 'Blog ID is required');
    }
    
    require_once __DIR__ . '/../../../app/config/db_connect.php';
    require_once __DIR__ . '/../../../app/controllers/BlogController.php';
    
    $blogController = new BlogController($pdo);
    $result = $blogController->delete($blog_id);
    
    sendJsonResponse($result['success'], $result['message']);
    
} catch (Exception $e) {
    sendJsonResponse(false, 'Server error: ' . $e->getMessage());
}
?>