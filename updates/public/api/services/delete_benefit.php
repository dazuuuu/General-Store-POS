<?php
// public/api/services/delete_benefit.php
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
        sendJsonResponse(false, 'Benefit ID is required');
    }
    
    require_once __DIR__ . '/../../../app/config/db_connect.php';
    
    $stmt = $pdo->prepare("DELETE FROM service_benefits WHERE id = :id");
    $result = $stmt->execute([':id' => $id]);
    
    sendJsonResponse($result, $result ? 'Benefit deleted successfully' : 'Failed to delete benefit');
    
} catch (Exception $e) {
    sendJsonResponse(false, 'Server error');
}
?>