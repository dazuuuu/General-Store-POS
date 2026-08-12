<?php
namespace Models;

class NotificationModel extends Model
{
    protected string $table = 'tenant_notifications';

    public function __construct(?\PDO $db = null)
    {
        parent::__construct($db);
        $this->ensureSchema();
    }

    public function creditSaleCreated(array $order, int $staffId): void
    {
        $receipt = (string) ($order['receipt_number'] ?? 'Credit sale');
        $customer = (string) ($order['table_name'] ?? 'Customer');
        $amount = 'KES ' . number_format((float) ($order['total'] ?? 0), 0);
        $staff = (string) ($order['opened_by_name'] ?? ($_SESSION['username'] ?? 'Staff'));
        $message = "{$receipt}: {$customer} opened a credit sale for {$amount} by {$staff}.";
        $url = 'orders/view.php?id=' . (int) ($order['id'] ?? 0);

        $this->insert([
            'audience' => 'owner',
            'user_id' => null,
            'type' => 'credit_sale',
            'title' => 'New credit sale',
            'message' => $message,
            'url' => 'super/' . $url,
        ]);
        $this->insert([
            'audience' => 'staff',
            'user_id' => $staffId,
            'type' => 'credit_sale',
            'title' => 'Credit sale opened',
            'message' => $message,
            'url' => 'staff/' . $url,
        ]);
    }

    public function recentForCurrentUser(int $limit = 5): array
    {
        $tid = \TenantContext::tenantId();
        $role = \TenantContext::role() === 'staff' ? 'staff' : 'owner';
        $uid = \TenantContext::userId();
        try {
            $sql = "SELECT * FROM tenant_notifications
                     WHERE tenant_id = ? AND audience = ? AND (user_id IS NULL OR user_id = ?)
                       AND read_at IS NULL
                  ORDER BY created_at DESC, id DESC LIMIT " . (int) $limit;
            $st = $this->db->prepare($sql);
            $st->execute([$tid, $role, $uid]);
            return $st->fetchAll();
        } catch (\PDOException $e) {
            return [];
        }
    }

    public function clearCreditSaleAlerts(?int $orderId = null): int
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            return 0;
        }
        try {
            if ($orderId !== null && $orderId > 0) {
                $st = $this->db->prepare(
                    "UPDATE tenant_notifications
                        SET read_at = NOW()
                      WHERE tenant_id = ?
                        AND type = 'credit_sale'
                        AND url LIKE ?"
                );
                $st->execute([$tid, '%orders/view.php?id=' . (int) $orderId . '%']);
                return $st->rowCount();
            }
            $st = $this->db->prepare(
                "UPDATE tenant_notifications
                    SET read_at = NOW()
                  WHERE tenant_id = ?
                    AND type = 'credit_sale'
                    AND read_at IS NULL"
            );
            $st->execute([$tid]);
            return $st->rowCount();
        } catch (\PDOException $e) {
            return 0;
        }
    }

    public function creditSaleAlerts(int $limit = 100): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            return [];
        }
        try {
            $st = $this->db->prepare(
                "SELECT *
                   FROM tenant_notifications
                  WHERE tenant_id = ?
                    AND type = 'credit_sale'
                    AND read_at IS NULL
               ORDER BY created_at DESC, id DESC
                  LIMIT " . (int) $limit
            );
            $st->execute([$tid]);
            return $st->fetchAll();
        } catch (\PDOException $e) {
            return [];
        }
    }

    private function ensureSchema(): void
    {
        try {
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS tenant_notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    audience ENUM('owner','staff') NOT NULL,
                    user_id INT NULL,
                    type VARCHAR(40) NOT NULL,
                    title VARCHAR(120) NOT NULL,
                    message VARCHAR(255) NOT NULL,
                    url VARCHAR(255) NULL,
                    read_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_tenant_audience (tenant_id, audience, created_at),
                    KEY idx_tenant_user (tenant_id, user_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (\PDOException $ignored) {
        }
    }
}
