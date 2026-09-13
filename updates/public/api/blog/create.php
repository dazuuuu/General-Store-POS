<?php
// public/api/blog/create.php
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
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, 'Invalid request method');
    }
    
    require_once __DIR__ . '/../../../app/config/db_connect.php';
    require_once __DIR__ . '/../../../app/controllers/BlogController.php';
    
    // Handle featured image upload
    $featured_image = null;
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === 0) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Modern/public/uploads/blogs/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $ext = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . uniqid() . '.' . $ext;
        $target_file = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $target_file)) {
            $featured_image = '/Modern/public/uploads/blogs/' . $filename;
        }
    }
    
    // Handle tags
    $tags = [];
    if (!empty($_POST['tags'])) {
        $tags = explode(',', $_POST['tags']);
        $tags = array_map('trim', $tags);
    }
    
    $blogController = new BlogController($pdo);
    $result = $blogController->create($_POST, $featured_image);
    
    sendJsonResponse($result['success'], $result['message'], ['blog_id' => $result['blog_id'] ?? null]);
    
} catch (Exception $e) {
    sendJsonResponse(false, 'Server error: ' . $e->getMessage());
}
?>