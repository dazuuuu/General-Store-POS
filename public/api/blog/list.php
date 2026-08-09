<?php
// public/api/blog/list.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../../app/config/db_connect.php';
    require_once __DIR__ . '/../../../app/models/BlogModel.php';
    
    $blogModel = new BlogModel($pdo);
    $status = $_GET['status'] ?? 'published';
    $limit = $_GET['limit'] ?? 12;
    $offset = $_GET['offset'] ?? 0;
    
    $blogs = $blogModel->getAll($status, $limit, $offset);
    
    echo json_encode(['success' => true, 'data' => $blogs]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>