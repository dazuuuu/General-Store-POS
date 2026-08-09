<?php
// public/api/services/delete_gallery.php
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
    $id = $input['id'] ?? null;
    
    if (!$id) {
        sendJsonResponse(false, 'Gallery ID is required');
    }
    
    require_once __DIR__ . '/../../../app/config/db_connect.php';
    
    // Get image path to delete file
    $stmt = $pdo->prepare("SELECT image_path FROM service_gallery WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($image && $image['image_path']) {
        $file_path = $_SERVER['DOCUMENT_ROOT'] . $image['image_path'];
        if (file_exists($file_path)) unlink($file_path);
    }
    
    $stmt = $pdo->prepare("DELETE FROM service_gallery WHERE id = :id");
    $result = $stmt->execute([':id' => $id]);
    
    sendJsonResponse($result, $result ? 'Gallery image deleted successfully' : 'Failed to delete image');
    
} catch (Exception $e) {
    sendJsonResponse(false, 'Server error');
}
?>