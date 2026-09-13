<?php
require_once __DIR__ . '/../../../app/app.php';

header('Content-Type: application/json');

try {
    PageGuard::auth();
    $q = trim((string) ($_GET['q'] ?? ''));
    $limit = max(1, min(20, (int) ($_GET['limit'] ?? 10)));
    $rows = (new Models\CustomerModel(Database::pdo()))->search($q, $limit);
    $items = array_map(function ($c) {
        return [
            'id' => (int) $c['id'],
            'name' => (string) ($c['name'] ?? ''),
            'phone' => (string) ($c['phone'] ?? ''),
            'email' => (string) ($c['email'] ?? ''),
            'company_name' => (string) ($c['company_name'] ?? ''),
            'loyalty_points' => (float) ($c['loyalty_points'] ?? 0),
            'loyalty_tier' => (string) ($c['loyalty_tier'] ?? 'standard'),
            'credit_limit' => (float) ($c['credit_limit'] ?? 0),
            'credit_balance' => (float) ($c['credit_balance'] ?? 0),
        ];
    }, $rows);
    echo json_encode(['ok' => true, 'items' => $items]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'items' => []]);
}
