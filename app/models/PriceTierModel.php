<?php
namespace Models;

class PriceTierModel extends Model
{
    protected string $table = 'product_price_tiers';

    public function __construct(?\PDO $db = null)
    {
        parent::__construct($db);
        $this->ensureSchema();
    }

    public function forProduct(int $productId): array
    {
        $tid = \TenantContext::tenantId();
        $st = $this->db->prepare(
            'SELECT * FROM product_price_tiers WHERE tenant_id = ? AND product_id = ? ORDER BY min_qty ASC'
        );
        $st->execute([$tid, $productId]);
        return $st->fetchAll();
    }

    /** @param int[] $productIds @return array<int, array> */
    public function forProducts(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (!$productIds) {
            return [];
        }
        $tid = \TenantContext::tenantId();
        $in = implode(',', array_fill(0, count($productIds), '?'));
        $st = $this->db->prepare(
            "SELECT * FROM product_price_tiers WHERE tenant_id = ? AND product_id IN ($in) ORDER BY product_id ASC, min_qty ASC"
        );
        $st->execute(array_merge([$tid], $productIds));
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $pid = (int) $row['product_id'];
            if (!isset($out[$pid])) {
                $out[$pid] = [];
            }
            $out[$pid][] = $row;
        }
        return $out;
    }

    /** Replace all tiers for a product with the given list. */
    public function replaceForProduct(int $productId, array $tiers): array
    {
        $tid = \TenantContext::tenantId();
        $prod = (new ProductModel($this->db))->find($productId);
        if (!$prod || (int) $prod['tenant_id'] !== (int) $tid) {
            return ['ok' => false, 'errors' => ['_' => 'Product not found.']];
        }

        $clean = [];
        foreach ($tiers as $t) {
            $min = (float) ($t['min_qty'] ?? 0);
            if ($min <= 0) {
                continue;
            }
            $hasUnit = ($t['unit_price'] ?? '') !== '' && (float) $t['unit_price'] >= 0;
            $hasDiscount = ($t['discount_amount'] ?? '') !== '' && (float) $t['discount_amount'] > 0;
            if (!$hasUnit && !$hasDiscount) {
                continue;
            }
            $max = ($t['max_qty'] ?? '') !== '' ? (float) $t['max_qty'] : null;
            $clean[] = [
                'min_qty' => $min,
                'max_qty' => $max,
                'unit_price' => $hasUnit ? (float) $t['unit_price'] : 0.0,
                'discount_amount' => $hasDiscount ? (float) $t['discount_amount'] : null,
                'label' => trim((string) ($t['label'] ?? '')) ?: null,
            ];
        }

        $this->db->beginTransaction();
        try {
            $del = $this->db->prepare('DELETE FROM product_price_tiers WHERE tenant_id = ? AND product_id = ?');
            $del->execute([$tid, $productId]);
            $ins = $this->db->prepare(
                'INSERT INTO product_price_tiers (tenant_id, product_id, min_qty, max_qty, unit_price, discount_amount, label) VALUES (?,?,?,?,?,?,?)'
            );
            foreach ($clean as $row) {
                $ins->execute([
                    $tid, $productId, $row['min_qty'], $row['max_qty'], $row['unit_price'],
                    $row['discount_amount'], $row['label'],
                ]);
            }
            $this->db->commit();
            return ['ok' => true, 'errors' => []];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['ok' => false, 'errors' => ['_' => 'Could not save price tiers.']];
        }
    }

    private function ensureSchema(): void
    {
        try {
            $this->db->query('SELECT id FROM product_price_tiers LIMIT 1');
        } catch (\PDOException $e) {
            try {
                $this->db->exec(
                    "CREATE TABLE IF NOT EXISTS product_price_tiers (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        tenant_id INT NOT NULL,
                        product_id INT NOT NULL,
                        min_qty DECIMAL(12,2) NOT NULL DEFAULT 1.00,
                        max_qty DECIMAL(12,2) NULL,
                        unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                        discount_amount DECIMAL(12,2) NULL,
                        label VARCHAR(80) NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        KEY idx_tier_product (tenant_id, product_id, min_qty)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
            } catch (\PDOException $ignored) {
            }
        }
        try {
            $this->db->query('SELECT discount_amount FROM product_price_tiers LIMIT 1');
        } catch (\PDOException $e) {
            try {
                $this->db->exec('ALTER TABLE product_price_tiers ADD COLUMN discount_amount DECIMAL(12,2) NULL AFTER unit_price');
            } catch (\PDOException $ignored) {
            }
        }
    }
}
