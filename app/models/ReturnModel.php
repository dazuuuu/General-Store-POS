<?php
namespace Models;

class ReturnModel extends Model
{
    protected string $table = 'product_returns';

    public function __construct(?\PDO $db = null)
    {
        parent::__construct($db);
        $this->ensureSchema();
    }

    public function findReceipt(string $receipt): ?array
    {
        $tid = \TenantContext::tenantId();
        $receipt = strtoupper(trim($receipt));
        if ($receipt === '' || $tid === null) { return null; }

        $st = $this->db->prepare(
            "SELECT 'order' AS source_type, o.id, o.receipt_number, o.table_name AS customer_name,
                    o.status, o.total, o.created_at, u.username AS staff_name
               FROM orders o
          LEFT JOIN users u ON u.id = o.opened_by
              WHERE o.tenant_id = ? AND o.receipt_number = ? AND o.status <> 'void'
              LIMIT 1"
        );
        $st->execute([$tid, $receipt]);
        $row = $st->fetch();
        if ($row) { return $row; }

        $st = $this->db->prepare(
            "SELECT 'sale' AS source_type, s.id, s.receipt_number, s.customer_name,
                    s.status, s.total, s.created_at, u.username AS staff_name
               FROM sales s
          LEFT JOIN users u ON u.id = s.staff_id
              WHERE s.tenant_id = ? AND s.receipt_number = ? AND s.status <> 'voided'
              LIMIT 1"
        );
        $st->execute([$tid, $receipt]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function receiptItems(string $sourceType, int $sourceId): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) { return []; }
        $isOrder = $sourceType === 'order';
        $table = $isOrder ? 'order_items' : 'sale_items';
        $sourceCol = $isOrder ? 'order_id' : 'sale_id';

        $st = $this->db->prepare(
            "SELECT i.id, i.product_id, i.product_name, i.unit_price, i.quantity, i.line_total,
                    COALESCE(SUM(r.returned_quantity),0) AS returned_quantity,
                    COALESCE(SUM(r.used_quantity),0) AS used_quantity,
                    COALESCE(SUM(r.restocked_quantity),0) AS restocked_quantity
               FROM {$table} i
          LEFT JOIN product_returns r
                 ON r.tenant_id = i.tenant_id
                AND r.source_type = ?
                AND r.source_item_id = i.id
              WHERE i.tenant_id = ? AND i.{$sourceCol} = ?
           GROUP BY i.id
           ORDER BY i.id ASC"
        );
        $st->execute([$sourceType, $tid, $sourceId]);
        return $st->fetchAll();
    }

    public function record(array $in, int $staffId): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) { return ['ok' => false, 'error' => 'No shop in context.']; }

        $sourceType = ($in['source_type'] ?? '') === 'sale' ? 'sale' : 'order';
        $sourceId = (int) ($in['source_id'] ?? 0);
        $itemId = (int) ($in['source_item_id'] ?? 0);
        $returned = round((float) ($in['returned_quantity'] ?? 0), 2);
        $used = round((float) ($in['used_quantity'] ?? 0), 2);
        $reason = trim((string) ($in['reason'] ?? ''));
        $note = trim((string) ($in['note'] ?? ''));

        if ($sourceId <= 0 || $itemId <= 0) { return ['ok' => false, 'error' => 'Choose the product being returned.']; }
        if ($returned <= 0) { return ['ok' => false, 'error' => 'Enter a returned quantity greater than zero.']; }
        if ($used < 0) { return ['ok' => false, 'error' => 'Used quantity cannot be negative.']; }
        if ($used > $returned + 0.0001) { return ['ok' => false, 'error' => 'Used quantity cannot be more than the returned quantity.']; }

        $isOrder = $sourceType === 'order';
        $itemTable = $isOrder ? 'order_items' : 'sale_items';
        $sourceCol = $isOrder ? 'order_id' : 'sale_id';
        $sourceTable = $isOrder ? 'orders' : 'sales';
        $voidStatus = $isOrder ? 'void' : 'voided';
        $restocked = round($returned - $used, 2);

        try {
            $this->db->beginTransaction();

            $st = $this->db->prepare(
                "SELECT i.*, s.receipt_number
                   FROM {$itemTable} i
                   JOIN {$sourceTable} s ON s.id = i.{$sourceCol} AND s.tenant_id = i.tenant_id
                  WHERE i.id = ? AND i.{$sourceCol} = ? AND i.tenant_id = ? AND s.status <> ?
                  FOR UPDATE"
            );
            $st->execute([$itemId, $sourceId, $tid, $voidStatus]);
            $item = $st->fetch();
            if (!$item) {
                $this->db->rollBack();
                return ['ok' => false, 'error' => 'That sold product was not found on this receipt.'];
            }

            $ret = $this->db->prepare(
                "SELECT COALESCE(SUM(returned_quantity),0)
                   FROM product_returns
                  WHERE tenant_id = ? AND source_type = ? AND source_item_id = ?"
            );
            $ret->execute([$tid, $sourceType, $itemId]);
            $alreadyReturned = round((float) $ret->fetchColumn(), 2);
            $remaining = round((float) $item['quantity'] - $alreadyReturned, 2);
            if ($returned > $remaining + 0.0001) {
                $this->db->rollBack();
                return ['ok' => false, 'error' => 'Only ' . rtrim(rtrim(number_format($remaining, 2), '0'), '.') . ' can still be returned for this product.'];
            }

            $this->db->prepare(
                "INSERT INTO product_returns
                    (tenant_id, source_type, source_id, source_item_id, product_id, product_name,
                     receipt_number, returned_quantity, used_quantity, restocked_quantity, reason, note, processed_by, migrated_at, migrated_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?)"
            )->execute([
                $tid,
                $sourceType,
                $sourceId,
                $itemId,
                $item['product_id'] ? (int) $item['product_id'] : null,
                $item['product_name'],
                $item['receipt_number'],
                $returned,
                $used,
                $restocked,
                $reason !== '' ? $reason : null,
                $note !== '' ? $note : null,
                $staffId,
                $staffId,
            ]);

            if ($restocked > 0 && !empty($item['product_id'])) {
                $this->db->prepare('UPDATE products SET quantity = quantity + ? WHERE id = ? AND tenant_id = ?')
                    ->execute([$restocked, (int) $item['product_id'], $tid]);
            }
            if ($used > 0 && !empty($item['product_id'])) {
                try {
                    $this->db->prepare('UPDATE products SET faulty_quantity = COALESCE(faulty_quantity,0) + ? WHERE id = ? AND tenant_id = ?')
                        ->execute([$used, (int) $item['product_id'], $tid]);
                } catch (\PDOException $ignored) {}
            }

            $this->applyFinancialReturn($sourceType, $sourceId, $itemId, $returned, (float) $item['unit_price'], $tid);

            $this->db->commit();
            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            error_log('ReturnModel::record failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not record the return. Please try again.'];
        }
    }

    private function applyFinancialReturn(string $sourceType, int $sourceId, int $itemId, float $returned, float $unitPrice, int $tid): void
    {
        $isOrder = $sourceType === 'order';
        $sourceTable = $isOrder ? 'orders' : 'sales';
        $itemTable = $isOrder ? 'order_items' : 'sale_items';
        $sourceCol = $isOrder ? 'order_id' : 'sale_id';
        $zeroStatus = $isOrder ? 'void' : 'voided';
        $refundValue = round(max(0, $returned) * max(0, $unitPrice), 2);
        if ($refundValue <= 0) {
            return;
        }

        $src = $this->db->prepare("SELECT * FROM {$sourceTable} WHERE id = ? AND tenant_id = ? FOR UPDATE");
        $src->execute([$sourceId, $tid]);
        $source = $src->fetch();
        if (!$source) {
            return;
        }

        $this->db->prepare(
            "UPDATE {$itemTable}
                SET line_total = GREATEST(line_total - ?, 0)
              WHERE id = ? AND {$sourceCol} = ? AND tenant_id = ?"
        )->execute([$refundValue, $itemId, $sourceId, $tid]);

        $subtotal = max(0, round((float) ($source['subtotal'] ?? $source['total'] ?? 0) - $refundValue, 2));
        $total = max(0, round((float) ($source['total'] ?? 0) - $refundValue, 2));
        $paidBefore = max(0, (float) ($source['amount_paid'] ?? 0));
        $paid = min($paidBefore, $total);
        $due = max(0, round($total - $paid, 2));

        if ($isOrder) {
            $status = $total <= 0.0001 ? $zeroStatus : (($source['status'] ?? 'open') === 'paid' && $due <= 0.0001 ? 'paid' : 'open');
            $paymentStatus = $status === 'paid' ? 'paid' : ($paid > 0 ? 'part_paid' : 'credit');
            if ($status === $zeroStatus) {
                $paymentStatus = 'returned';
            }
            $this->db->prepare(
                'UPDATE orders
                    SET subtotal = ?, total = ?, amount_paid = ?, amount_due = ?, status = ?, payment_status = ?
                  WHERE id = ? AND tenant_id = ?'
            )->execute([$subtotal, $total, $paid, $due, $status, $paymentStatus, $sourceId, $tid]);
            return;
        }

        $status = $total <= 0.0001 ? $zeroStatus : ($due <= 0.0001 ? 'completed' : ($source['status'] ?? 'completed'));
        $paymentStatus = $total <= 0.0001 ? 'returned' : ($due <= 0.0001 ? 'paid' : ($paid > 0 ? 'part_paid' : 'credit'));
        $cash = isset($source['cash_amount']) && $source['cash_amount'] !== null ? min((float) $source['cash_amount'], $paid) : null;
        $mpesa = isset($source['mpesa_amount']) && $source['mpesa_amount'] !== null ? min((float) $source['mpesa_amount'], max(0, $paid - (float) ($cash ?? 0))) : null;
        $this->db->prepare(
            'UPDATE sales
                SET subtotal = ?, total = ?, amount_paid = ?, amount_due = ?, payment_status = ?,
                    cash_amount = ?, mpesa_amount = ?, status = ?
              WHERE id = ? AND tenant_id = ?'
        )->execute([$subtotal, $total, $paid, $due, $paymentStatus, $cash, $mpesa, $status, $sourceId, $tid]);
    }

    public function recent(int $limit = 100): array
    {
        $tid = \TenantContext::tenantId();
        $st = $this->db->prepare(
            "SELECT r.*, u.username AS processed_by_name
               FROM product_returns r
          LEFT JOIN users u ON u.id = r.processed_by
              WHERE r.tenant_id = ?
           ORDER BY r.created_at DESC, r.id DESC
              LIMIT " . (int) $limit
        );
        $st->execute([$tid]);
        return $st->fetchAll();
    }

    public function pendingForInventory(): array
    {
        $tid = \TenantContext::tenantId();
        $st = $this->db->prepare(
            "SELECT r.*, p.quantity AS current_quantity, p.faulty_quantity, u.username AS processed_by_name
               FROM product_returns r
          LEFT JOIN products p ON p.id = r.product_id AND p.tenant_id = r.tenant_id
          LEFT JOIN users u ON u.id = r.processed_by
              WHERE r.tenant_id = ? AND r.migrated_at IS NULL
           ORDER BY r.created_at ASC, r.id ASC"
        );
        $st->execute([$tid]);
        return $st->fetchAll();
    }

    public function migrateToInventory(int $returnId, int $userId): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) { return ['ok' => false, 'error' => 'No shop in context.']; }

        try {
            $this->db->beginTransaction();
            $st = $this->db->prepare('SELECT * FROM product_returns WHERE id = ? AND tenant_id = ? FOR UPDATE');
            $st->execute([$returnId, $tid]);
            $row = $st->fetch();
            if (!$row) {
                $this->db->rollBack();
                return ['ok' => false, 'error' => 'Return not found.'];
            }
            if (!empty($row['migrated_at'])) {
                $this->db->rollBack();
                return ['ok' => false, 'error' => 'This return is already migrated.'];
            }
            if (empty($row['product_id'])) {
                $this->db->rollBack();
                return ['ok' => false, 'error' => 'This return is not linked to an inventory product.'];
            }

            $restocked = max(0, (float) $row['restocked_quantity']);
            $used = max(0, (float) $row['used_quantity']);
            if ($restocked > 0) {
                $this->db->prepare('UPDATE products SET quantity = quantity + ? WHERE id = ? AND tenant_id = ?')
                    ->execute([$restocked, (int) $row['product_id'], $tid]);
            }
            if ($used > 0) {
                try {
                    $this->db->prepare('UPDATE products SET faulty_quantity = COALESCE(faulty_quantity,0) + ? WHERE id = ? AND tenant_id = ?')
                        ->execute([$used, (int) $row['product_id'], $tid]);
                } catch (\PDOException $ignored) {}
            }

            $this->db->prepare('UPDATE product_returns SET migrated_at = NOW(), migrated_by = ? WHERE id = ? AND tenant_id = ?')
                ->execute([$userId, $returnId, $tid]);
            $this->db->commit();
            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            error_log('ReturnModel::migrateToInventory failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not migrate this return to inventory.'];
        }
    }

    public function returnsForItems(string $sourceType, array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
        if (!$itemIds) { return []; }
        $tid = \TenantContext::tenantId();
        $in = implode(',', array_fill(0, count($itemIds), '?'));
        $st = $this->db->prepare(
            "SELECT source_item_id, SUM(returned_quantity) AS returned_quantity, SUM(used_quantity) AS used_quantity
               FROM product_returns
              WHERE tenant_id = ? AND source_type = ? AND source_item_id IN ($in)
           GROUP BY source_item_id"
        );
        $st->execute(array_merge([$tid, $sourceType], $itemIds));
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[(int) $r['source_item_id']] = [
                'returned' => (float) $r['returned_quantity'],
                'used' => (float) $r['used_quantity'],
            ];
        }
        return $out;
    }

    private function ensureSchema(): void
    {
        try {
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS product_returns (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    source_type ENUM('sale','order') NOT NULL,
                    source_id INT NOT NULL,
                    source_item_id INT NOT NULL,
                    product_id INT NULL,
                    product_name VARCHAR(160) NOT NULL,
                    receipt_number VARCHAR(32) NOT NULL,
                    returned_quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    used_quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    restocked_quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    reason VARCHAR(120) NULL,
                    note VARCHAR(255) NULL,
                    processed_by INT NULL,
                    migrated_at DATETIME NULL,
                    migrated_by INT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_returns_source (tenant_id, source_type, source_id),
                    KEY idx_returns_item (tenant_id, source_type, source_item_id),
                    KEY idx_returns_product (tenant_id, product_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (\PDOException $ignored) {}
        $this->ensureColumn('product_returns', 'migrated_at', "ALTER TABLE product_returns ADD COLUMN migrated_at DATETIME NULL AFTER processed_by");
        $this->ensureColumn('product_returns', 'migrated_by', "ALTER TABLE product_returns ADD COLUMN migrated_by INT NULL AFTER migrated_at");
    }

    private function ensureColumn(string $table, string $column, string $sql): void
    {
        try {
            $st = $this->db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
            $st->execute([$table, $column]);
            if ((int) $st->fetchColumn() === 0) {
                $this->db->exec($sql);
            }
        } catch (\PDOException $ignored) {}
    }
}
