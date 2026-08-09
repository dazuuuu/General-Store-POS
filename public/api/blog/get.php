<?php
// public/api/blog/get.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../../app/config/db_connect.php';
    require_once __DIR__ . '/../../../app/controllers/BlogController.php';
    
    $slug = $_GET['slug'] ?? null;
    $id = $_GET['id'] ?? null;
    
    if (!$slug && !$id) {
        echo json_encode(['success' => false, 'message' => 'Blog identifier required']);
        exit();
    }
    
    $blogController = new BlogController($pdo);
    $identifier = $slug ?: $id;
    $blog = $blogController->getBlogDetails($identifier);
    
    if ($blog) {
        if (isset($_GET['increment_view']) && $_GET['increment_view'] == 1) {
            $blogController->incrementViews($blog['id']);
        }
        echo json_encode(['success' => true, 'data' => $blog]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Blog not found']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>