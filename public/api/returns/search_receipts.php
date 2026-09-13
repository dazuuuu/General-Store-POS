<?php
// public/api/returns/search_receipts.php — receipt type-ahead for Returns desk.
require_once __DIR__ . '/../../../app/app.php';

header('Content-Type: application/json; charset=utf-8');

$isAllowed = TenantContext::check() && (
    TenantContext::role() === 'tenant_owner' ||
    TenantContext::can(Capabilities::SALES_RECORD) ||
    TenantContext::can(Capabilities::SALES_VIEW) ||
    TenantContext::can(Capabilities::ALL)
);

if (!$isAllowed) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'items' => []]);
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
$pdo = Database::pdo();
$R = new Models\ReturnModel($pdo);
$rows = $R->searchReceipts($q, 8);

echo json_encode([
    'ok' => true,
    'items' => array_map(function (array $r) {
        $date = !empty($r['created_at']) ? date('j M Y, g:i a', strtotime($r['created_at'])) : '';
        return [
            'id'             => (int) $r['id'],
            'receipt_number' => $r['receipt_number'],
            'source_type'    => $r['source_type'],
            'customer_name'  => $r['customer_name'] ?: 'Walk-in Customer',
            'staff_name'     => $r['staff_name'] ?? '—',
            'total'          => (float) $r['total'],
            'created_at'     => $r['created_at'],
            'date_formatted' => $date,
            'items_summary'  => $r['items_summary'] ?: 'No products',
        ];
    }, $rows),
]);
