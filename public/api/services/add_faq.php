<?php
// public/api/services/add_faq.php
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
    
    if (empty($_POST['question']) || empty($_POST['answer'])) {
        sendJsonResponse(false, 'Question and answer are required');
    }
    
    require_once __DIR__ . '/../../../app/config/db_connect.php';
    
    $sql = "INSERT INTO service_faqs (service_id, question, answer, is_active) 
            VALUES (:service_id, :question, :answer, 1)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':service_id' => $service_id,
        ':question' => $_POST['question'],
        ':answer' => $_POST['answer']
    ]);
    
    if ($result) {
        sendJsonResponse(true, 'FAQ added successfully');
    } else {
        sendJsonResponse(false, 'Failed to add FAQ');
    }
    
} catch (Exception $e) {
    sendJsonResponse(false, 'Server error: ' . $e->getMessage());
}
?>