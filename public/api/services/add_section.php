<?php
// public/api/services/add_section.php
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
    
    if (empty($_POST['section_type'])) {
        sendJsonResponse(false, 'Section type is required');
    }
    
    require_once __DIR__ . '/../../../app/config/db_connect.php';
    
    // Handle media file upload
    $media_url = $_POST['media_url'] ?? null;
    $media_type = 'image';
    
    if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === 0) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Modern/public/uploads/services/sections/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $ext = pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . uniqid() . '.' . $ext;
        $target_file = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['media_file']['tmp_name'], $target_file)) {
            $media_url = '/Modern/public/uploads/services/sections/' . $filename;
            $media_type = in_array($ext, ['mp4', 'webm', 'ogg']) ? 'video' : 'image';
        }
    }
    
    // Get max sort order
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 as next_sort FROM service_sections WHERE service_id = :service_id");
    $stmt->execute([':service_id' => $service_id]);
    $sort_order = $stmt->fetch(PDO::FETCH_ASSOC)['next_sort'] ?? 0;
    
    $sql = "INSERT INTO service_sections (service_id, section_type, title, content, media_url, media_type, sort_order) 
            VALUES (:service_id, :section_type, :title, :content, :media_url, :media_type, :sort_order)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':service_id' => $service_id,
        ':section_type' => $_POST['section_type'],
        ':title' => $_POST['title'] ?? null,
        ':content' => $_POST['content'] ?? null,
        ':media_url' => $media_url,
        ':media_type' => $media_type,
        ':sort_order' => $sort_order
    ]);
    
    if ($result) {
        sendJsonResponse(true, 'Section added successfully');
    } else {
        sendJsonResponse(false, 'Failed to add section');
    }
    
} catch (Exception $e) {
    sendJsonResponse(false, 'Server error: ' . $e->getMessage());
}
?>