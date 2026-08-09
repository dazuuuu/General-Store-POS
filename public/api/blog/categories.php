<?php
// public/api/blog/categories.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../../app/config/db_connect.php';
    require_once __DIR__ . '/../../../app/models/BlogModel.php';
    
    $blogModel = new BlogModel($pdo);
    $categories = $blogModel->getAllCategories();
    
    echo json_encode(['success' => true, 'data' => $categories]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>