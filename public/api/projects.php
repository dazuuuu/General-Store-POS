<?php
// public/api/projects.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function sendJsonResponse($success, $message, $data = null) {
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit();
}

try {
    require_once __DIR__ . '/../../app/config/db_connect.php';
    
    $action = $_GET['action'] ?? '';
    
    // Public endpoints - no authentication required
    switch ($action) {
        case 'get_categories':
            // Get all project categories
            $stmt = $pdo->prepare("SELECT * FROM project_categories ORDER BY category_name ASC");
            $stmt->execute();
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse(true, 'Categories retrieved successfully', $categories);
            break;
            
        case 'get_projects':
            // Get projects with optional filters
            $category_id = $_GET['category_id'] ?? null;
            $status = $_GET['status'] ?? 'published';
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            
            $sql = "
                SELECT p.*, c.category_name 
                FROM projects p 
                LEFT JOIN project_categories c ON p.category_id = c.id 
                WHERE p.status = :status
            ";
            $params = [':status' => $status];
            
            if ($category_id) {
                $sql .= " AND p.category_id = :category_id";
                $params[':category_id'] = $category_id;
            }
            
            $sql .= " ORDER BY p.created_at DESC";
            
            if ($limit) {
                $sql .= " LIMIT :limit OFFSET :offset";
                $params[':limit'] = $limit;
                $params[':offset'] = $offset;
            }
            
            $stmt = $pdo->prepare($sql);
            
            foreach ($params as $key => $value) {
                if (is_int($value)) {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value);
                }
            }
            
            $stmt->execute();
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse(true, 'Projects retrieved successfully', $projects);
            break;
            
        case 'get_project':
            // Get single project by ID or slug
            $identifier = $_GET['id'] ?? $_GET['slug'] ?? null;
            
            if (!$identifier) {
                sendJsonResponse(false, 'Project ID or slug is required');
            }
            
            $field = is_numeric($identifier) ? 'id' : 'slug';
            $sql = "
                SELECT p.*, c.category_name 
                FROM projects p 
                LEFT JOIN project_categories c ON p.category_id = c.id 
                WHERE p.$field = :identifier AND p.status = 'published'
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':identifier' => $identifier]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($project) {
                // Update view count
                $updateStmt = $pdo->prepare("UPDATE projects SET view_count = view_count + 1 WHERE id = :id");
                $updateStmt->execute([':id' => $project['id']]);
                sendJsonResponse(true, 'Project retrieved successfully', $project);
            } else {
                sendJsonResponse(false, 'Project not found');
            }
            break;
            
        case 'search':
            // Search projects
            $keyword = trim($_GET['q'] ?? $_GET['keyword'] ?? '');
            
            if (empty($keyword)) {
                sendJsonResponse(false, 'Search keyword is required');
            }
            
            $sql = "
                SELECT p.*, c.category_name 
                FROM projects p 
                LEFT JOIN project_categories c ON p.category_id = c.id 
                WHERE p.status = 'published' 
                AND (p.small_title LIKE :keyword 
                     OR p.major_title LIKE :keyword 
                     OR p.description LIKE :keyword)
                ORDER BY p.created_at DESC
                LIMIT 20
            ";
            
            $stmt = $pdo->prepare($sql);
            $keywordParam = "%$keyword%";
            $stmt->bindParam(':keyword', $keywordParam);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse(true, 'Search completed', $results);
            break;
            
        default:
            sendJsonResponse(false, 'Invalid action. Available actions: get_categories, get_projects, get_project, search');
            break;
    }
    
} catch (PDOException $e) {
    sendJsonResponse(false, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendJsonResponse(false, 'Server error: ' . $e->getMessage());
}
?>