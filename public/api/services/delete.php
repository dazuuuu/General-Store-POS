<?php
// public/api/services/delete.php
header('Content-Type: application/json');

// Start session and check admin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] > 2) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get the JSON input
$input = json_decode(file_get_contents('php://input'), true);
$service_id = $input['id'] ?? null;

if (!$service_id) {
    echo json_encode(['success' => false, 'message' => 'Service ID is required']);
    exit();
}

require_once __DIR__ . '/../../../app/config/db_connect.php';

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Get cover image path to delete file
    $sql = "SELECT cover_image FROM services WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $service_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($service && $service['cover_image']) {
        $file_path = $_SERVER['DOCUMENT_ROOT'] . $service['cover_image'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // Delete service (cascading will delete related records)
    $sql = "DELETE FROM services WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $service_id]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Service deleted successfully']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Failed to delete service: ' . $e->getMessage()]);
}
?>