<?php
// public/api/inventory/sellable_search.php — sale/invoice product search.
require_once __DIR__ . '/../../../app/app.php';

header('Content-Type: application/json; charset=utf-8');

$isOwner = TenantContext::role() === 'tenant_owner';
if (!TenantContext::check() || (!$isOwner && !TenantContext::can(Capabilities::SALES_RECORD))) {
    http_response_code(403);
    echo json_encode(['items' => []]);
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
$limit = min(20, max(5, (int) ($_GET['limit'] ?? 12)));
// Advanced search: wait until at least 2 characters so suggestions stay relevant.
if (mb_strlen($q) < 2) {
    echo json_encode(['items' => []]);
    exit;
}

$pdo = Database::pdo();
$tid = (int) TenantContext::tenantId();
$like = '%' . $q . '%';
$prefix = $q . '%';

$stmt = $pdo->prepare(
    "SELECT p.id, p.name, p.selling_price, p.wholesale_price, p.retail_price,
            p.offer_price, p.offer_starts_at, p.offer_ends_at,
            p.quantity, p.unit, p.barcode, p.status,
            p.units_per_pack, p.pack_unit, p.pack_price,
            c.name AS category_name,
            br.name AS brand_name
       FROM products p
  LEFT JOIN categories c ON c.id = p.category_id
  LEFT JOIN book_attributes br ON br.id = p.brand_id
      WHERE p.tenant_id = ?
        AND p.status IN ('active','archived')
        AND p.quantity > 0
        AND (p.name LIKE ? OR p.barcode LIKE ?)
   ORDER BY (p.name LIKE ?) DESC, p.name ASC
      LIMIT " . (int) $limit
);
$stmt->execute([$tid, $like, $like, $prefix]);

$items = [];
foreach ($stmt->fetchAll() as $row) {
    $eff = Models\ProductModel::effectivePrice($row);
    $retail = (float) $eff['price'];
    if ($retail <= 0) {
        $retail = (float) (($row['retail_price'] ?? 0) ?: ($row['selling_price'] ?? 0));
    }
    $wholesale = (float) ($row['wholesale_price'] ?? 0);
    if ($wholesale <= 0) {
        $wholesale = $retail;
    }
    $items[] = [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'barcode' => $row['barcode'] ?? '',
        'category_name' => $row['category_name'] ?? '',
        'brand_name' => $row['brand_name'] ?? '',
        'unit' => $row['unit'] ?? '',
        'stock' => (float) $row['quantity'],
        'retail_price' => $retail,
        'wholesale_price' => $wholesale,
        'units_per_pack' => max(1, (float) ($row['units_per_pack'] ?? 1)),
        'pack_unit' => (string) ($row['pack_unit'] ?? ''),
        'pack_price' => ($row['pack_price'] ?? '') !== '' && $row['pack_price'] !== null ? (float) $row['pack_price'] : 0.0,
    ];
}

echo json_encode(['items' => $items]);
