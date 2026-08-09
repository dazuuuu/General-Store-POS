<?php
// public/api/services/add_gallery.php
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
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, 'Invalid request method');
    }
    
    $service_id = $_POST['service_id'] ?? null;
    if (!$service_id) {
        sendJsonResponse(false, 'Service ID is required');
    }
    
    if (!isset($_FILES['gallery_image']) || $_FILES['gallery_image']['error'] !== 0) {
        sendJsonResponse(false, 'Image file is required');
    }
    
    require_once __DIR__ . '/../../../app/config/db_connect.php';
    
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Modern/public/uploads/services/gallery/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $ext = pathinfo($_FILES['gallery_image']['name'], PATHINFO_EXTENSION);
    $filename = time() . '_' . uniqid() . '.' . $ext;
    $target_file = $upload_dir . $filename;
    
    if (move_uploaded_file($_FILES['gallery_image']['tmp_name'], $target_file)) {
        $sql = "INSERT INTO service_gallery (service_id, image_path, image_title, image_description) 
                VALUES (:service_id, :image_path, :image_title, :image_description)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':service_id' => $service_id,
            ':image_path' => '/Modern/public/uploads/services/gallery/' . $filename,
            ':image_title' => $_POST['image_title'] ?? null,
            ':image_description' => $_POST['image_description'] ?? null
        ]);
        
        if ($result) {
            sendJsonResponse(true, 'Gallery image added successfully');
        } else {
            sendJsonResponse(false, 'Failed to save to database');
        }
    } else {
        sendJsonResponse(false, 'Failed to upload image');
    }
    
} catch (Exception $e) {
    sendJsonResponse(false, 'Server error: ' . $e->getMessage());
}
?>