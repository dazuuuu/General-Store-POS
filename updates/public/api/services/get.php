<?php
// public/api/services/get.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../../app/config/db_connect.php';
require_once '../../../app/controllers/ServiceController.php';

$serviceController = new ServiceController($pdo);

if (isset($_GET['slug'])) {
    $service = $serviceController->getServiceDetails($_GET['slug']);
    
    if ($service) {
        // Update view count
        $serviceController->updateViewCount($service['id']);
        echo json_encode(['success' => true, 'data' => $service]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Service not found']);
    }
} elseif (isset($_GET['id'])) {
    $service = $serviceController->getServiceDetails($_GET['id']);
    echo json_encode(['success' => true, 'data' => $service]);
} else {
    echo json_encode(['success' => false, 'message' => 'Service identifier required']);
}
?>