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
            $price = (float) ($t['unit_price'] ?? 0);
            if ($min <= 0 || $price < 0) {
                continue;
            }
            $max = ($t['max_qty'] ?? '') !== '' ? (float) $t['max_qty'] : null;
            $clean[] = [
                'min_qty' => $min,
                'max_qty' => $max,
                'unit_price' => $price,
                'label' => trim((string) ($t['label'] ?? '')) ?: null,
            ];
        }

        $this->db->beginTransaction();
        try {
            $del = $this->db->prepare('DELETE FROM product_price_tiers WHERE tenant_id = ? AND product_id = ?');
            $del->execute([$tid, $productId]);
            $ins = $this->db->prepare(
                'INSERT INTO product_price_tiers (tenant_id, product_id, min_qty, max_qty, unit_price, label) VALUES (?,?,?,?,?,?)'
            );
            foreach ($clean as $row) {
                $ins->execute([$tid, $productId, $row['min_qty'], $row['max_qty'], $row['unit_price'], $row['label']]);
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
                        unit_price DECIMAL(12,2) NOT NULL,
                        label VARCHAR(80) NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        KEY idx_tier_product (tenant_id, product_id, min_qty)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
            } catch (\PDOException $ignored) {
            }
        }
    }
}
