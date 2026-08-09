<?php
// public/api/inventory/find_titles.php — product name type-ahead on Record Stock /
// Record product. Exact match → restock; otherwise create new.
require_once __DIR__ . '/../../../app/app.php';

header('Content-Type: application/json; charset=utf-8');

if (!TenantContext::check() || !TenantContext::can(Capabilities::STOCK_ENTER)) {
    http_response_code(403);
    echo json_encode(['items' => []]);
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
$rawType = $_GET['type'] ?? 'product';
$productType = in_array($rawType, ['product','book','stationery'], true) ? $rawType : 'product';
$pdo = Database::pdo();
$rows = (new Models\ProductModel($pdo))->searchByName($q, 8, $productType);

echo json_encode(['items' => array_map(function (array $r) {
    return [
        'id'             => (int) $r['id'],
        'name'           => $r['name'],
        'balance'        => (float) $r['quantity'],
        'buying_price'   => (float) $r['buying_price'],
        'image_path'     => $r['image_path'],
        'barcode'        => $r['barcode'] ?? null,
        'category_name'  => $r['category_name'] ?? $r['subject_name'] ?? null,
        'brand_name'     => $r['brand_name'] ?? $r['publisher_name'] ?? null,
        'unit'           => $r['unit'] ?? null,
        'colors'         => $r['colors'] ?? null,
        'faulty_quantity'=> isset($r['faulty_quantity']) ? (float) $r['faulty_quantity'] : null,
    ];
}, $rows)]);
