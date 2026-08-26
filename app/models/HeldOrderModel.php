<?php
// app/models/HeldOrderModel.php
// "Hold Order": set a cart aside before it's a real invoice. Doesn't touch
// stock — a held cart only becomes real (and stock moves) when it's resumed
// and checked out via OrderModel::open(), which discards the hold.
namespace Models;

class HeldOrderModel extends Model
{
    protected string $table = 'held_orders';

    public function __construct(?\PDO $db = null)
    {
        parent::__construct($db);
        $this->ensureSchema();
    }

    /** @param array $in customer_name, staff_id, items[{product_id,quantity}] */
    public function hold(array $in): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            return ['ok' => false, 'errors' => ['_' => 'No shop in context.']];
        }
        $customerName = trim($in['customer_name'] ?? '');
        $items = array_values(array_filter($in['items'] ?? [], fn($i) => (int) ($i['product_id'] ?? 0) > 0 && (float) ($i['quantity'] ?? 0) > 0));
        if (!$items) {
            return ['ok' => false, 'errors' => ['_' => 'Add at least one item to hold this order.']];
        }
        $staffId = (int) ($in['staff_id'] ?? 0);
        if ($staffId <= 0) {
            return ['ok' => false, 'errors' => ['_' => 'No staff in context.']];
        }

        $db = $this->db;
        try {
            $db->beginTransaction();
            $ins = $db->prepare('INSERT INTO held_orders (tenant_id, customer_name, staff_id) VALUES (?,?,?)');
            $ins->execute([$tid, $customerName, $staffId]);
            $heldId = (int) $db->lastInsertId();

            $sel = $db->prepare('SELECT id, name, selling_price, wholesale_price, retail_price FROM products WHERE id = ? AND tenant_id = ?');
            $insItem = $db->prepare(
                'INSERT INTO held_order_items (tenant_id, held_order_id, product_id, product_name, unit_price, price_type, quantity) VALUES (?,?,?,?,?,?,?)'
            );
            foreach ($items as $it) {
                $pid = (int) $it['product_id'];
                $sel->execute([$pid, $tid]);
                $p = $sel->fetch();
                if (!$p) { continue; } // product removed since — skip it, don't fail the whole hold
                $priceType = (($it['price_type'] ?? 'retail') === 'wholesale') ? 'wholesale' : 'retail';
                $price = $priceType === 'wholesale'
                    ? (float) (($p['wholesale_price'] ?? 0) ?: ($p['retail_price'] ?: $p['selling_price']))
                    : (float) ($p['retail_price'] ?: $p['selling_price']);
                $insItem->execute([$tid, $heldId, $pid, $p['name'], $price, $priceType, (float) $it['quantity']]);
            }

            $db->commit();
            return ['ok' => true, 'held_order_id' => $heldId, 'errors' => []];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            return ['ok' => false, 'errors' => ['_' => 'Could not hold this order. Please try again.']];
        }
    }

    /** Held orders for the tenant, newest first. */
    public function listForTenant(): array
    {
        $tid = \TenantContext::tenantId();
        $sql = "SELECT h.*, u.username AS staff_name,
                       (SELECT COUNT(*) FROM held_order_items hi WHERE hi.held_order_id = h.id) AS item_count,
                       (SELECT COALESCE(SUM(hi.unit_price * hi.quantity),0) FROM held_order_items hi WHERE hi.held_order_id = h.id) AS total
                  FROM held_orders h
             LEFT JOIN users u ON u.id = h.staff_id
                 WHERE h.tenant_id = ?
              ORDER BY h.created_at DESC, h.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tid]);
        return $stmt->fetchAll();
    }

    public function items(int $heldOrderId): array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare('SELECT * FROM held_order_items WHERE held_order_id = ? AND tenant_id = ? ORDER BY id ASC');
        $stmt->execute([$heldOrderId, $tid]);
        return $stmt->fetchAll();
    }

    private function ensureSchema(): void
    {
        $this->ensureTable('held_orders', "
            CREATE TABLE IF NOT EXISTS held_orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                customer_name VARCHAR(160) NOT NULL,
                staff_id INT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_held_tenant (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->ensureTable('held_order_items', "
            CREATE TABLE IF NOT EXISTS held_order_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                held_order_id INT NOT NULL,
                product_id INT NULL,
                product_name VARCHAR(160) NOT NULL,
                unit_price DECIMAL(12,2) NOT NULL,
                price_type VARCHAR(20) NOT NULL DEFAULT 'retail',
                quantity DECIMAL(12,2) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_held_item_order (held_order_id),
                KEY idx_held_item_tenant (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $this->ensureColumn('held_order_items', 'price_type', "ALTER TABLE held_order_items ADD COLUMN price_type VARCHAR(20) NOT NULL DEFAULT 'retail' AFTER unit_price");
    }

    private function ensureTable(string $table, string $sql): void
    {
        try {
            $this->db->query("SELECT 1 FROM `{$table}` LIMIT 1");
        } catch (\PDOException $e) {
            try { $this->db->exec($sql); } catch (\PDOException $ignored) {}
        }
    }

    private function ensureColumn(string $table, string $column, string $sql): void
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
            $stmt->execute([$table, $column]);
            if ((int) $stmt->fetchColumn() > 0) {
                return;
            }
            $this->db->exec($sql);
        } catch (\PDOException $ignored) {}
    }

    /** Delete a held order and its items — used both for "Discard" and after a successful resume→checkout. */
    public function discard(int $heldOrderId): bool
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) { return false; }
        $this->db->prepare('DELETE FROM held_order_items WHERE held_order_id = ? AND tenant_id = ?')->execute([$heldOrderId, $tid]);
        $stmt = $this->db->prepare('DELETE FROM held_orders WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$heldOrderId, $tid]);
        return $stmt->rowCount() > 0;
    }
}
