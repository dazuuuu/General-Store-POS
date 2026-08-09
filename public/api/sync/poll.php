<?php
// public/api/sync/poll.php — lightweight real-time sync for tills / inventory
require_once __DIR__ . '/../../../app/app.php';
header('Content-Type: application/json');

PageGuard::auth();
$pdo = Database::pdo();
$tid = TenantContext::tenantId();
$since = (int) ($_GET['since'] ?? 0);

try {
    $pdo->query('SELECT id FROM sync_events LIMIT 1');
} catch (PDOException $e) {
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sync_events (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                entity_type VARCHAR(40) NOT NULL,
                entity_id INT NOT NULL,
                action VARCHAR(20) NOT NULL,
                payload JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_sync_tenant_time (tenant_id, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } catch (PDOException $ignored) {}
}

$events = [];
try {
    $st = $pdo->prepare('SELECT id, entity_type, entity_id, action, payload, created_at FROM sync_events WHERE tenant_id = ? AND id > ? ORDER BY id ASC LIMIT 200');
    $st->execute([$tid, $since]);
    $events = $st->fetchAll();
} catch (PDOException $e) {
    $events = [];
}

// Always include a live snapshot of low-stock count + latest sale id for clients.
$P = new Models\ProductModel($pdo);
$low = count($P->lowStock(500));
$latestSale = 0;
try {
    $st = $pdo->prepare("SELECT MAX(id) FROM orders WHERE tenant_id = ? AND status = 'paid'");
    $st->execute([$tid]);
    $latestSale = (int) $st->fetchColumn();
} catch (PDOException $e) {}

$maxId = $since;
foreach ($events as $ev) {
    $maxId = max($maxId, (int) $ev['id']);
}

echo json_encode([
    'ok' => true,
    'cursor' => $maxId,
    'server_time' => date('c'),
    'snapshot' => [
        'low_stock_count' => $low,
        'latest_paid_order_id' => $latestSale,
    ],
    'events' => $events,
]);
