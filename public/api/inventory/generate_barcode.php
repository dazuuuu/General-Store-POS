<?php
// public/api/inventory/generate_barcode.php — assign a system barcode when
// the product has none printed to scan.
require_once __DIR__ . '/../../../app/app.php';

header('Content-Type: application/json; charset=utf-8');

if (!TenantContext::check() || !TenantContext::can(Capabilities::INVENTORY_EDIT)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not allowed.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Wrong method.']);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$pdo = Database::pdo();
$barcode = $id > 0 ? (new Models\ProductModel($pdo))->assignBarcode($id) : null;

if ($barcode === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Product not found.']);
    exit;
}

echo json_encode(['ok' => true, 'barcode' => $barcode]);
