<?php
// Loyalty + B2B customer records for counter sales and wholesale invoicing.
namespace Models;

class CustomerModel extends Model
{
    protected string $table = 'customers';

    private static bool $balancesSynced = false;

    public function __construct(?\PDO $db = null)
    {
        parent::__construct($db);
        $this->ensureSchema();
        $this->syncAllCreditBalancesOnce();
    }

    public function create(array $in): array
    {
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'id' => null, 'errors' => ['name' => 'Customer name is required.']];
        }
        $id = $this->insert([
            'name'           => $name,
            'phone'          => trim((string) ($in['phone'] ?? '')) ?: null,
            'email'          => trim((string) ($in['email'] ?? '')) ?: null,
            'company_name'   => trim((string) ($in['company_name'] ?? '')) ?: null,
            'is_b2b'         => !empty($in['is_b2b']) ? 1 : 0,
            'credit_limit'   => max(0, (float) ($in['credit_limit'] ?? 0)),
            'loyalty_tier'   => in_array($in['loyalty_tier'] ?? '', ['standard', 'silver', 'gold', 'platinum'], true)
                ? $in['loyalty_tier'] : 'standard',
            'notes'          => trim((string) ($in['notes'] ?? '')) ?: null,
            'status'         => 'active',
        ]);
        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    public function edit(int $id, array $in): array
    {
        if (!$this->find($id)) {
            return ['ok' => false, 'errors' => ['_' => 'Customer not found.']];
        }
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'errors' => ['name' => 'Customer name is required.']];
        }
        $this->update($id, [
            'name'           => $name,
            'phone'          => trim((string) ($in['phone'] ?? '')) ?: null,
            'email'          => trim((string) ($in['email'] ?? '')) ?: null,
            'company_name'   => trim((string) ($in['company_name'] ?? '')) ?: null,
            'is_b2b'         => !empty($in['is_b2b']) ? 1 : 0,
            'credit_limit'   => max(0, (float) ($in['credit_limit'] ?? 0)),
            'loyalty_tier'   => in_array($in['loyalty_tier'] ?? '', ['standard', 'silver', 'gold', 'platinum'], true)
                ? $in['loyalty_tier'] : 'standard',
            'notes'          => trim((string) ($in['notes'] ?? '')) ?: null,
            'status'         => ($in['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        ]);
        return ['ok' => true, 'errors' => []];
    }

    public function findByPhone(string $phone): ?array
    {
        $phone = preg_replace('/\D+/', '', $phone);
        if ($phone === '') {
            return null;
        }
        $tid = \TenantContext::tenantId();
        $st = $this->db->prepare(
            "SELECT * FROM customers WHERE tenant_id = ? AND REPLACE(REPLACE(REPLACE(phone,'+',''),' ',''),'-','') LIKE ? AND status = 'active' LIMIT 1"
        );
        $st->execute([$tid, '%' . $phone]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function search(string $q, int $limit = 20): array
    {
        $tid = \TenantContext::tenantId();
        $q = trim($q);
        if ($q === '') {
            $st = $this->db->prepare('SELECT * FROM customers WHERE tenant_id = ? AND status = ? ORDER BY name ASC LIMIT ?');
            $st->bindValue(1, $tid, \PDO::PARAM_INT);
            $st->bindValue(2, 'active');
            $st->bindValue(3, $limit, \PDO::PARAM_INT);
            $st->execute();
            return $st->fetchAll();
        }
        $like = '%' . $q . '%';
        $st = $this->db->prepare(
            'SELECT * FROM customers WHERE tenant_id = ? AND status = ? AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR company_name LIKE ?)
             ORDER BY name ASC LIMIT ?'
        );
        $st->bindValue(1, $tid, \PDO::PARAM_INT);
        $st->bindValue(2, 'active');
        $st->bindValue(3, $like);
        $st->bindValue(4, $like);
        $st->bindValue(5, $like);
        $st->bindValue(6, $like);
        $st->bindValue(7, $limit, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    public function adjustPoints(int $customerId, float $points, string $reason, ?int $orderId = null, ?int $createdBy = null): bool
    {
        $tid = \TenantContext::tenantId();
        $cust = $this->find($customerId);
        if (!$cust || (int) $cust['tenant_id'] !== (int) $tid) {
            return false;
        }
        $new = round((float) $cust['loyalty_points'] + $points, 2);
        if ($new < 0) {
            return false;
        }
        // Do not nest transactions — markPaid may already be inside one.
        $started = false;
        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $started = true;
            }
            $this->update($customerId, ['loyalty_points' => $new]);
            $st = $this->db->prepare(
                'INSERT INTO loyalty_transactions (tenant_id, customer_id, order_id, points, reason, created_by) VALUES (?,?,?,?,?,?)'
            );
            $st->execute([$tid, $customerId, $orderId, $points, $reason, $createdBy]);
            if ($started) {
                $this->db->commit();
            }
            return true;
        } catch (\Throwable $e) {
            if ($started && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    /**
     * Recompute customers.credit_balance from open invoice balances.
     * Matches by customer_id and/or exact customer name on the invoice.
     */
    public function refreshCreditBalance(int $customerId): float
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null || $customerId <= 0) {
            return 0.0;
        }
        $cust = $this->find($customerId);
        if (!$cust || (int) $cust['tenant_id'] !== (int) $tid) {
            return 0.0;
        }
        $name = trim((string) ($cust['name'] ?? ''));
        try {
            $sql = "SELECT COALESCE(SUM(GREATEST(COALESCE(o.total,0) - COALESCE(o.amount_paid,0), 0)), 0)
                      FROM orders o
                     WHERE o.tenant_id = ?
                       AND o.status = 'open'
                       AND o.invoice_deleted_at IS NULL
                       AND GREATEST(COALESCE(o.total,0) - COALESCE(o.amount_paid,0), 0) > 0.0001
                       AND (o.customer_id = ?";
            $params = [$tid, $customerId];
            if ($name !== '') {
                $sql .= ' OR LOWER(TRIM(o.table_name)) = LOWER(?)';
                $params[] = $name;
            }
            $sql .= ')';
            $st = $this->db->prepare($sql);
            $st->execute($params);
            $balance = round((float) $st->fetchColumn(), 2);
            $this->db->prepare('UPDATE customers SET credit_balance = ? WHERE id = ? AND tenant_id = ?')
                ->execute([$balance, $customerId, $tid]);
            return $balance;
        } catch (\Throwable $e) {
            error_log('CustomerModel::refreshCreditBalance failed: ' . $e->getMessage());
            return (float) ($cust['credit_balance'] ?? 0);
        }
    }

    /** Find an active customer by exact name (case-insensitive). */
    public function findByName(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $tid = \TenantContext::tenantId();
        $st = $this->db->prepare(
            'SELECT * FROM customers WHERE tenant_id = ? AND status = ? AND LOWER(TRIM(name)) = LOWER(?) LIMIT 1'
        );
        $st->execute([$tid, 'active', $name]);
        $row = $st->fetch();
        return $row ?: null;
    }

    private function ensureSchema(): void
    {
        try {
            $this->db->query('SELECT id FROM customers LIMIT 1');
        } catch (\PDOException $e) {
            try {
                $this->db->exec(
                    "CREATE TABLE IF NOT EXISTS customers (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        tenant_id INT NOT NULL,
                        name VARCHAR(160) NOT NULL,
                        phone VARCHAR(30) NULL,
                        email VARCHAR(255) NULL,
                        company_name VARCHAR(160) NULL,
                        is_b2b TINYINT(1) NOT NULL DEFAULT 0,
                        credit_limit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                        credit_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                        loyalty_points DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                        loyalty_tier ENUM('standard','silver','gold','platinum') NOT NULL DEFAULT 'standard',
                        notes TEXT NULL,
                        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        KEY idx_cust_tenant (tenant_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
            } catch (\PDOException $ignored) {
            }
        }
        try {
            $st = $this->db->prepare(
                "SELECT COUNT(*) FROM information_schema.columns
                  WHERE table_schema = DATABASE() AND table_name = 'customers' AND column_name = 'credit_balance'"
            );
            $st->execute();
            if ((int) $st->fetchColumn() === 0) {
                $this->db->exec('ALTER TABLE customers ADD COLUMN credit_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER credit_limit');
            }
        } catch (\PDOException $ignored) {
        }
        try {
            $st = $this->db->prepare(
                "SELECT COUNT(*) FROM information_schema.columns
                  WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'invoice_deleted_at'"
            );
            $st->execute();
            if ((int) $st->fetchColumn() === 0) {
                $this->db->exec('ALTER TABLE orders ADD COLUMN invoice_deleted_at DATETIME NULL');
            }
        } catch (\PDOException $ignored) {
        }
    }

    /** One-time per request: recompute every customer's credit_balance from open invoices. */
    private function syncAllCreditBalancesOnce(): void
    {
        if (self::$balancesSynced) {
            return;
        }
        self::$balancesSynced = true;
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            return;
        }
        try {
            $st = $this->db->prepare(
                "UPDATE customers c
                    SET c.credit_balance = (
                        SELECT COALESCE(SUM(GREATEST(COALESCE(o.total,0) - COALESCE(o.amount_paid,0), 0)), 0)
                          FROM orders o
                         WHERE o.tenant_id = c.tenant_id
                           AND o.status = 'open'
                           AND o.invoice_deleted_at IS NULL
                           AND GREATEST(COALESCE(o.total,0) - COALESCE(o.amount_paid,0), 0) > 0.0001
                           AND (
                                o.customer_id = c.id
                             OR LOWER(TRIM(o.table_name)) = LOWER(TRIM(c.name))
                           )
                    )
                  WHERE c.tenant_id = ?"
            );
            $st->execute([$tid]);
        } catch (\PDOException $ignored) {
        }
    }
}
