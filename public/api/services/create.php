<?php
// public/api/services/create.php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors in output
header('Content-Type: application/json');

try {
    // Include database connection
    require_once __DIR__ . '/../../../app/config/db_connect.php';
    
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in and is admin
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] > 2) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    }
    
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit();
    }
    
    // Include controller
    require_once __DIR__ . '/../../../app/controllers/ServiceController.php';
    
    $serviceController = new ServiceController($pdo);
    
    // Handle cover image upload
    $cover_image = null;
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === 0) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Modern/public/uploads/services/covers/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_info = pathinfo($_FILES['cover_image']['name']);
        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $file_info['filename']) . '.' . $file_info['extension'];
        $target_file = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $target_file)) {
            $cover_image = '/Modern/public/uploads/services/covers/' . $filename;
        }
    }
    
    $result = $serviceController->create($_POST, $cover_image);
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>