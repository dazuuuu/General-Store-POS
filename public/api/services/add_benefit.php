<?php
// public/api/services/add_benefit.php
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
    
    if (empty($_POST['benefit_title'])) {
        sendJsonResponse(false, 'Benefit title is required');
    }
    
    require_once __DIR__ . '/../../../app/config/db_connect.php';
    
    $sql = "INSERT INTO service_benefits (service_id, benefit_title, benefit_description, icon_class) 
            VALUES (:service_id, :benefit_title, :benefit_description, :icon_class)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':service_id' => $service_id,
        ':benefit_title' => $_POST['benefit_title'],
        ':benefit_description' => $_POST['benefit_description'] ?? null,
        ':icon_class' => $_POST['icon_class'] ?? null
    ]);
    
    if ($result) {
        sendJsonResponse(true, 'Benefit added successfully');
    } else {
        sendJsonResponse(false, 'Failed to add benefit');
    }
    
} catch (Exception $e) {
    sendJsonResponse(false, 'Server error: ' . $e->getMessage());
}
?>