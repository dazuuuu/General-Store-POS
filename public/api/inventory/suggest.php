<?php
// public/api/inventory/suggest.php — type-ahead suggestions for the free-text
// Subject / Grade / Publisher / Author / Supplier boxes on Record Stock and
// Edit Product. Read-only, tenant-scoped (Model base class enforces that).
require_once __DIR__ . '/../../../app/app.php';

header('Content-Type: application/json; charset=utf-8');

if (
    !TenantContext::check()
    || (!TenantContext::can(Capabilities::STOCK_ENTER)
        && !TenantContext::can(Capabilities::INVENTORY_EDIT)
        && !TenantContext::can(Capabilities::INVENTORY_VIEW))
) {
    http_response_code(403);
    echo json_encode(['items' => []]);
    exit;
}

$field = $_GET['field'] ?? '';
$q = trim((string) ($_GET['q'] ?? ''));

$pdo = Database::pdo();
$items = [];

if ($field === 'subject') {
    $items = (new Models\CategoryModel($pdo))->suggestions($q, 8, 'subject');
} elseif ($field === 'stationery_category') {
    $items = (new Models\CategoryModel($pdo))->suggestions($q, 8, 'stationery');
} elseif ($field === 'supplier') {
    $items = (new Models\SupplierModel($pdo))->suggestions($q);
} elseif (in_array($field, Models\BookAttributeModel::TYPES, true)) {
    $items = (new Models\BookAttributeModel($pdo))->suggestions($field, $q);
}

echo json_encode(['items' => array_map(
    fn($r) => ['id' => (int) $r['id'], 'name' => $r['name']],
    $items
)]);
