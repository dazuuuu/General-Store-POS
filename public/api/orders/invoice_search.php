<?php
// Live search for credit invoices: receipt #, customer name, company, phone.
require_once __DIR__ . '/../../../app/app.php';

header('Content-Type: application/json');

try {
    PageGuard::auth();
    $q = trim((string) ($_GET['q'] ?? ''));
    $limit = max(1, min(40, (int) ($_GET['limit'] ?? 15)));
    $openOnly = !isset($_GET['open_only']) || $_GET['open_only'] !== '0';
    $rows = (new Models\OrderModel(Database::pdo()))->searchInvoices($q, [
        'open_only' => $openOnly,
        'limit' => $limit,
    ]);
    $items = array_map(function ($o) {
        $paid = max(0, (float) ($o['amount_paid'] ?? 0));
        $due = (float) ($o['balance_due'] ?? $o['amount_due'] ?? 0);
        if (($o['status'] ?? '') === 'open' && $due <= 0.0001) {
            $due = max(0, (float) ($o['total'] ?? 0) - $paid);
        }
        if (($o['status'] ?? '') === 'paid') {
            $due = 0.0;
        }
        $customer = trim((string) ($o['table_name'] ?? ''));
        $company = trim((string) ($o['customer_company'] ?? ''));
        if ($company === '' && !empty($o['customer_record_name']) && $customer === '') {
            $customer = (string) $o['customer_record_name'];
        }
        return [
            'id' => (int) $o['id'],
            'receipt_number' => (string) ($o['receipt_number'] ?? ''),
            'customer_name' => $customer,
            'company_name' => $company,
            'phone' => (string) ($o['customer_phone'] ?? ''),
            'status' => (string) ($o['status'] ?? ''),
            'total' => (float) ($o['total'] ?? 0),
            'amount_paid' => $paid,
            'balance' => round($due, 2),
            'item_count' => (int) ($o['item_count'] ?? 0),
            'created_at' => (string) ($o['created_at'] ?? ''),
        ];
    }, $rows);
    echo json_encode(['ok' => true, 'items' => $items]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'items' => []]);
}
