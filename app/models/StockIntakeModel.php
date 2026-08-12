<?php
// app/models/StockIntakeModel.php
// The write path for "record stock": a supplier brings a batch of products
// (new ones, or a restock of ones already in the catalogue) in one delivery.
namespace Models;

class StockIntakeModel extends Model
{
    protected string $table = 'stock_intakes';

    public function __construct(?\PDO $db = null)
    {
        parent::__construct($db);
        $this->ensureSchema();
    }

    /**
     * @param array $header supplier_id (optional), staff_id, notes
     * @param array $items  list of either:
     *   new:     ['mode'=>'new', 'name', 'category_id', 'brand_id', 'unit', 'colors',
     *             'quantity', 'faulty_quantity', 'buying_price', 'selling_price', ...]
     *   restock: ['mode'=>'restock', 'product_id', 'quantity', 'faulty_quantity', 'buying_price', 'remark']
     * @return array ['ok'=>bool, 'intake_id'=>?int, 'errors'=>array]
     */
    public function create(array $header, array $items): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            return ['ok' => false, 'intake_id' => null, 'errors' => ['_' => 'No shop in context.']];
        }

        // Supplier is optional — not every delivery has a formal distributor behind it.
        $supplierId = (int) ($header['supplier_id'] ?? 0);
        if ($supplierId > 0 && !$this->supplierBelongsToTenant($supplierId)) {
            return ['ok' => false, 'intake_id' => null, 'errors' => ['supplier_id' => 'Choose a valid supplier.']];
        }
        $staffId = (int) ($header['staff_id'] ?? 0);
        if ($staffId <= 0) {
            return ['ok' => false, 'intake_id' => null, 'errors' => ['_' => 'No staff in context.']];
        }

        $items = array_values(array_filter($items, fn($i) => in_array($i['mode'] ?? '', ['new', 'restock'], true)));
        if (!$items) {
            return ['ok' => false, 'intake_id' => null, 'errors' => ['_' => 'Add at least one product to this delivery.']];
        }

        $db = $this->db;
        $productModel = new ProductModel($db);

        try {
            $db->beginTransaction();

            $insIntake = $db->prepare(
                'INSERT INTO stock_intakes (tenant_id, supplier_id, staff_id, notes) VALUES (?,?,?,?)'
            );
            $insIntake->execute([
                $tid, $supplierId > 0 ? $supplierId : null, $staffId,
                trim((string) ($header['notes'] ?? '')) !== '' ? trim($header['notes']) : null,
            ]);
            $intakeId = (int) $db->lastInsertId();

            $bump = $db->prepare(
                'UPDATE products SET quantity = quantity + ?, buying_price = ?, package_buying_price = ?, unit = ?, units_per_pack = ?, pack_unit = ?, pack_price = ? WHERE id = ? AND tenant_id = ?'
            );

            foreach ($items as $i) {
                $qty = (float) ($i['quantity'] ?? 0);
                if ($qty <= 0) {
                    $db->rollBack();
                    return ['ok' => false, 'intake_id' => null, 'errors' => ['_' => 'Every product needs a quantity greater than zero.']];
                }
                $buying = (float) ($i['buying_price'] ?? 0);
                $unit = $i['unit'] ?? 'piece';
                $unitsPerPackage = max(0.01, (float) ($i['units_per_package'] ?? $i['units_per_pack'] ?? 1));
                $packageUnit = trim((string) ($i['package_unit'] ?? '')) ?: null;
                $packagePrice = ($i['package_price'] ?? '') !== '' ? max(0, (float) $i['package_price']) : null;

                if (($i['mode'] ?? '') === 'restock') {
                    $productId = (int) ($i['product_id'] ?? 0);
                    $prod = $productModel->find($productId);
                    if (!$prod) {
                        $db->rollBack();
                        return ['ok' => false, 'intake_id' => null, 'errors' => ['_' => 'One of the selected products was not found.']];
                    }
                    $packageBuying = ($i['package_buying_price'] ?? '') !== '' ? (float) $i['package_buying_price'] : null;
                    $bump->execute([$qty, $buying, $packageBuying, $unit, $unitsPerPackage, $packageUnit, $packagePrice, $productId, $tid]);
                    $productName = $prod['name'];
                    $faulty = max(0, (float) ($i['faulty_quantity'] ?? 0));
                    if ($faulty > 0) {
                        try {
                            $db->prepare('UPDATE products SET faulty_quantity = faulty_quantity + ? WHERE id = ? AND tenant_id = ?')
                               ->execute([$faulty, $productId, $tid]);
                        } catch (\PDOException $ignored) {}
                    }
                } else {
                    $name = trim((string) ($i['name'] ?? ''));
                    if ($name === '') {
                        $db->rollBack();
                        return ['ok' => false, 'intake_id' => null, 'errors' => ['_' => 'Every new product needs a name.']];
                    }
                    $res = $productModel->create([
                        'product_type'    => $i['product_type'] ?? 'product',
                        'name'            => $name,
                        'category_id'     => (int) ($i['category_id'] ?? 0),
                        'brand_id'        => (int) ($i['brand_id'] ?? 0),
                        'barcode'         => $i['barcode'] ?? '',
                        'supplier_id'     => $supplierId,
                        'unit'            => $i['unit'] ?? 'piece',
                        'units_per_pack'  => $unitsPerPackage,
                        'pack_unit'       => $packageUnit,
                        'pack_price'      => $packagePrice,
                        'size_value'      => $i['size_value'] ?? '',
                        'size_unit'       => $i['size_unit'] ?? '',
                        'colors'          => $i['colors'] ?? [],
                        'sizes'           => $i['sizes'] ?? [],
                        'quantity'        => $qty,
                        'faulty_quantity' => (float) ($i['faulty_quantity'] ?? 0),
                        'buying_price'    => $buying,
                        'package_buying_price' => $i['package_buying_price'] ?? '',
                        'retail_price'    => $i['selling_price'] ?? 0,
                        'wholesale_price' => $i['wholesale_price'] ?? '',
                        'offer_price'     => $i['offer_price'] ?? '',
                        'offer_starts_at' => $i['offer_starts_at'] ?? '',
                        'offer_ends_at'   => $i['offer_ends_at'] ?? '',
                        'image_path'      => $i['image_path'] ?? '',
                        'status'          => 'active',
                    ]);
                    if (!$res['ok']) {
                        $db->rollBack();
                        return ['ok' => false, 'intake_id' => null, 'errors' => $res['errors'] ?: ['_' => "Could not save \"{$name}\"."]];
                    }
                    $productId = (int) $res['id'];
                    $productName = $name;
                }

                $remark = trim((string) ($i['remark'] ?? ''));
                try {
                    $insItem = $db->prepare(
                        'INSERT INTO stock_intake_items (tenant_id, stock_intake_id, product_id, product_name, quantity, faulty_quantity, unit, colors, package_unit, package_quantity, units_per_package, package_price, buying_price, remark)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    );
                    $insItem->execute([
                        $tid, $intakeId, $productId, $productName, $qty,
                        max(0, (float) ($i['faulty_quantity'] ?? 0)),
                        $i['unit'] ?? null,
                        is_array($i['colors'] ?? null) ? implode(', ', $i['colors']) : ($i['colors'] ?? null),
                        $packageUnit,
                        ($i['package_quantity'] ?? '') !== '' ? max(0, (float) $i['package_quantity']) : null,
                        $unitsPerPackage,
                        $packagePrice,
                        $buying,
                        $remark !== '' ? $remark : null,
                    ]);
                } catch (\PDOException $e) {
                    $insItem = $db->prepare(
                        'INSERT INTO stock_intake_items (tenant_id, stock_intake_id, product_id, product_name, quantity, buying_price, remark)
                         VALUES (?,?,?,?,?,?,?)'
                    );
                    $insItem->execute([$tid, $intakeId, $productId, $productName, $qty, $buying, $remark !== '' ? $remark : null]);
                }
            }

            $db->commit();
            return ['ok' => true, 'intake_id' => $intakeId, 'errors' => []];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            error_log('StockIntakeModel create failed: ' . $e->getMessage());
            return ['ok' => false, 'intake_id' => null, 'errors' => ['_' => 'Could not record this delivery. Please try again.']];
        }
    }

    /** Recent deliveries with supplier names, for the activity list. */
    public function recentWithMeta(int $limit = 30): array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            "SELECT si.*, sup.name AS supplier_name, u.username AS staff_name,
                    (SELECT COUNT(*) FROM stock_intake_items sii WHERE sii.stock_intake_id = si.id) AS item_count
               FROM stock_intakes si
          LEFT JOIN suppliers sup ON sup.id = si.supplier_id
          LEFT JOIN users u ON u.id = si.staff_id
              WHERE si.tenant_id = ?
           ORDER BY si.created_at DESC, si.id DESC
              LIMIT " . (int) $limit
        );
        $stmt->execute([$tid]);
        return $stmt->fetchAll();
    }

    public function itemsFor(int $intakeId): array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare('SELECT * FROM stock_intake_items WHERE stock_intake_id = ? AND tenant_id = ? ORDER BY id ASC');
        $stmt->execute([$intakeId, $tid]);
        return $stmt->fetchAll();
    }

    private function ensureSchema(): void
    {
        $this->ensureTable('stock_intakes', "
            CREATE TABLE IF NOT EXISTS stock_intakes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                supplier_id INT NULL,
                staff_id INT NOT NULL,
                notes VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_intake_tenant (tenant_id),
                KEY idx_intake_supplier (supplier_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->ensureTable('stock_intake_items', "
            CREATE TABLE IF NOT EXISTS stock_intake_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                stock_intake_id INT NOT NULL,
                product_id INT NULL,
                product_name VARCHAR(160) NULL,
                quantity DECIMAL(12,2) NULL,
                buying_price DECIMAL(12,2) NULL,
                remark VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_intakeitem_intake (stock_intake_id),
                KEY idx_intakeitem_tenant (tenant_id),
                KEY idx_intakeitem_product (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->ensureColumn('stock_intakes', 'supplier_id', "ALTER TABLE stock_intakes MODIFY supplier_id INT NULL");
        $this->ensureColumn('stock_intakes', 'notes', "ALTER TABLE stock_intakes ADD COLUMN notes VARCHAR(255) NULL AFTER staff_id");
        $this->ensureColumn('stock_intake_items', 'remark', "ALTER TABLE stock_intake_items ADD COLUMN remark VARCHAR(255) NULL AFTER buying_price");
        $this->ensureColumn('stock_intake_items', 'faulty_quantity', "ALTER TABLE stock_intake_items ADD COLUMN faulty_quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER quantity");
        $this->ensureColumn('stock_intake_items', 'unit', "ALTER TABLE stock_intake_items ADD COLUMN unit VARCHAR(20) NULL AFTER faulty_quantity");
        $this->ensureColumn('stock_intake_items', 'colors', "ALTER TABLE stock_intake_items ADD COLUMN colors VARCHAR(255) NULL AFTER unit");
        $this->ensureColumn('stock_intake_items', 'package_unit', "ALTER TABLE stock_intake_items ADD COLUMN package_unit VARCHAR(20) NULL AFTER colors");
        $this->ensureColumn('stock_intake_items', 'package_quantity', "ALTER TABLE stock_intake_items ADD COLUMN package_quantity DECIMAL(12,2) NULL AFTER package_unit");
        $this->ensureColumn('stock_intake_items', 'units_per_package', "ALTER TABLE stock_intake_items ADD COLUMN units_per_package DECIMAL(12,2) NULL AFTER package_quantity");
        $this->ensureColumn('stock_intake_items', 'package_price', "ALTER TABLE stock_intake_items ADD COLUMN package_price DECIMAL(12,2) NULL AFTER units_per_package");
    }

    private function ensureTable(string $table, string $sql): void
    {
        try {
            $this->db->query("SELECT 1 FROM `{$table}` LIMIT 1");
        } catch (\PDOException $e) {
            try {
                $this->db->exec($sql);
            } catch (\PDOException $ignored) {
                // Ignore if the table already exists or the schema is partially present.
            }
        }
    }

    private function ensureColumn(string $table, string $column, string $sql): void
    {
        if ($this->columnExists($table, $column)) {
            return;
        }
        try {
            $this->db->exec($sql);
        } catch (\PDOException $ignored) {
            // Ignore if the column already exists or the table is missing.
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
            $stmt->execute([$table, $column]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (\PDOException $ignored) {
            return false;
        }
    }

    private function supplierBelongsToTenant(int $id): bool
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare('SELECT 1 FROM suppliers WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tid]);
        return (bool) $stmt->fetchColumn();
    }
}
