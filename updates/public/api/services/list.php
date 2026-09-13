<?php
// public/api/services/list.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../../app/config/db_connect.php';
require_once '../../../app/models/ServiceModel.php';

use Models\ServiceModel;

$serviceModel = new ServiceModel($pdo);
$status = $_GET['status'] ?? 'published';
$limit = $_GET['limit'] ?? 10;
$offset = $_GET['offset'] ?? 0;

$services = $serviceModel->getAllServices($status, $limit, $offset);
echo json_encode(['success' => true, 'data' => $services]);
?>