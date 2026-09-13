<?php
// public/api/blog/add_faq.php
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
    
    require_once __DIR__ . '/../../../app/config/db_connect.php';
    require_once __DIR__ . '/../../../app/controllers/BlogController.php';
    
    $blogController = new BlogController($pdo);
    
    $result = $blogController->addFaq($_POST['blog_id'], $_POST, $_POST['sort_order'] ?? 0);
    
    sendJsonResponse($result ? true : false, $result ? 'FAQ added successfully' : 'Failed to add FAQ');
    
} catch (Exception $e) {
    sendJsonResponse(false, 'Server error: ' . $e->getMessage());
}
?>