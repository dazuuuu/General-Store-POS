<?php
// public/api/services/update.php
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
    
    require_once __DIR__ . '/../../../app/config/db_connect.php';
    
    // Handle cover image upload
    $cover_image = null;
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === 0) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Modern/public/uploads/services/covers/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $ext = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . uniqid() . '.' . $ext;
        $target_file = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $target_file)) {
            $cover_image = '/Modern/public/uploads/services/covers/' . $filename;
            
            // Delete old cover image
            $stmt = $pdo->prepare("SELECT cover_image FROM services WHERE id = :id");
            $stmt->execute([':id' => $service_id]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($old && $old['cover_image']) {
                $old_file = $_SERVER['DOCUMENT_ROOT'] . $old['cover_image'];
                if (file_exists($old_file)) unlink($old_file);
            }
        }
    }
    
    // Build update query
    $fields = [];
    $params = [':id' => $service_id];
    
    if (!empty($_POST['title'])) {
        $fields[] = "title = :title";
        $params[':title'] = $_POST['title'];
        
        $slug = strtolower(trim(preg_replace('/[^a-z0-9-]/', '-', $_POST['title']), '-'));
        $fields[] = "slug = :slug";
        $params[':slug'] = $slug;
    }
    
    if (isset($_POST['short_description'])) {
        $fields[] = "short_description = :short_description";
        $params[':short_description'] = $_POST['short_description'];
    }
    
    if ($cover_image) {
        $fields[] = "cover_image = :cover_image";
        $params[':cover_image'] = $cover_image;
    }
    
    if (isset($_POST['status'])) {
        $fields[] = "status = :status";
        $params[':status'] = $_POST['status'];
    }
    
    if (empty($fields)) {
        sendJsonResponse(false, 'No data to update');
    }
    
    $sql = "UPDATE services SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute($params)) {
        sendJsonResponse(true, 'Service updated successfully');
    } else {
        sendJsonResponse(false, 'Failed to update service');
    }
    
} catch (Exception $e) {
    sendJsonResponse(false, 'Server error: ' . $e->getMessage());
}
?>