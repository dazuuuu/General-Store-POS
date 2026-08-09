<?php
// public/api/inventory/find_barcode.php — "scan to restock" on Record
// Stock: a scanned barcode that matches an existing book behaves exactly
// like picking it from the Book Title type-ahead (same row shape).
require_once __DIR__ . '/../../../app/app.php';

header('Content-Type: application/json; charset=utf-8');

if (!TenantContext::check() || !TenantContext::can(Capabilities::STOCK_ENTER)) {
    http_response_code(403);
    echo json_encode(['item' => null]);
    exit;
}

$code = trim((string) ($_GET['code'] ?? ''));
$pdo = Database::pdo();
$row = $code !== '' ? (new Models\ProductModel($pdo))->findByBarcode($code) : null;

if (!$row) {
    echo json_encode(['item' => null]);
    exit;
}

echo json_encode(['item' => [
    'id'             => (int) $row['id'],
    'name'           => $row['name'],
    'product_type'   => $row['product_type'] ?? 'book',
    'balance'        => (float) $row['quantity'],
    'buying_price'   => (float) $row['buying_price'],
    'image_path'     => $row['image_path'],
    'barcode'        => $row['barcode'],
    'subject_name'   => $row['subject_name'],
    'grade_name'     => $row['grade_name'],
    'publisher_name' => $row['publisher_name'],
    'author_name'    => $row['author_name'],
    'edition_name'   => $row['edition_name'],
    'brand_name'     => $row['brand_name'] ?? null,
]]);
