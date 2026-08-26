<?php
namespace Models;

class StoreProductModel extends Model
{
    protected string $table = 'store_products';

    public function __construct(?\PDO $db = null)
    {
        parent::__construct($db);
        $this->ensureSchema();
    }

    public function createMany(array $items, int $staffId): array
    {
        $created = 0;
        foreach ($items as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            $qty = (float) ($item['quantity'] ?? 0);
            if ($name === '' || $qty <= 0) {
                continue;
            }
            $this->insert([
                'product_id' => (int) ($item['product_id'] ?? 0) ?: null,
                'name' => $name,
                'category_id' => (int) ($item['category_id'] ?? 0) ?: null,
                'brand_id' => (int) ($item['brand_id'] ?? 0) ?: null,
                'supplier_id' => (int) ($item['supplier_id'] ?? 0) ?: null,
                'barcode' => trim((string) ($item['barcode'] ?? '')) ?: null,
                'unit' => $item['unit'] ?? 'piece',
                'package_unit' => trim((string) ($item['package_unit'] ?? '')) ?: null,
                'package_quantity' => ($item['package_quantity'] ?? '') !== '' ? max(0, (float) $item['package_quantity']) : null,
                'units_per_package' => ($item['units_per_package'] ?? '') !== '' ? max(0.01, (float) $item['units_per_package']) : null,
                'package_price' => ($item['package_price'] ?? '') !== '' ? max(0, (float) $item['package_price']) : null,
                'colors' => trim((string) ($item['colors'] ?? '')) ?: null,
                'quantity' => $qty,
                'faulty_quantity' => max(0, (float) ($item['faulty_quantity'] ?? 0)),
                'buying_price' => max(0, (float) ($item['buying_price'] ?? 0)),
                'package_buying_price' => ($item['package_buying_price'] ?? '') !== '' ? max(0, (float) $item['package_buying_price']) : null,
                'retail_price' => max(0, (float) ($item['retail_price'] ?? 0)),
                'wholesale_price' => max(0, (float) ($item['wholesale_price'] ?? 0)),
                'offer_price' => ($item['offer_price'] ?? '') !== '' ? max(0, (float) $item['offer_price']) : null,
                'offer_starts_at' => $this->dateOrNull($item['offer_starts_at'] ?? null),
                'offer_ends_at' => $this->dateOrNull($item['offer_ends_at'] ?? null),
                'image_path' => trim((string) ($item['image_path'] ?? '')) ?: null,
                'notes' => trim((string) ($item['notes'] ?? '')) ?: null,
                'created_by' => $staffId,
                'status' => 'stored',
            ]);
            $created++;
        }
        return ['ok' => $created > 0, 'created' => $created, 'error' => $created > 0 ? null : 'Add at least one stored product.'];
    }

    public function pending(): array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            "SELECT sp.*, c.name AS category_name, br.name AS brand_name, s.name AS supplier_name
               FROM store_products sp
          LEFT JOIN categories c ON c.id = sp.category_id
          LEFT JOIN book_attributes br ON br.id = sp.brand_id
          LEFT JOIN suppliers s ON s.id = sp.supplier_id
              WHERE sp.tenant_id = ? AND sp.status = 'stored'
           ORDER BY sp.created_at DESC, sp.id DESC"
        );
        $stmt->execute([$tid]);
        return $stmt->fetchAll();
    }

    public function invoices(int $limit = 100): array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            "SELECT si.*, u.username AS created_by_name,
                    (SELECT COUNT(*) FROM store_invoice_items sii WHERE sii.invoice_id = si.id) AS item_count
               FROM store_invoices si
          LEFT JOIN users u ON u.id = si.created_by
              WHERE si.tenant_id = ?
           ORDER BY si.created_at DESC, si.id DESC
              LIMIT " . (int) $limit
        );
        $stmt->execute([$tid]);
        return $stmt->fetchAll();
    }

    /** Warehouse capital still in Store + shop Inventory capital (buying cost). */
    public function capitalSummary(): array
    {
        $tid = \TenantContext::tenantId();
        $warehouse = ['qty' => 0.0, 'capital' => 0.0, 'lines' => 0];
        $shop = ['qty' => 0.0, 'capital' => 0.0, 'lines' => 0];
        try {
            $st = $this->db->prepare(
                "SELECT COUNT(*) AS lines,
                        COALESCE(SUM(quantity),0) AS qty,
                        COALESCE(SUM(quantity * COALESCE(buying_price,0)),0) AS capital
                   FROM store_products
                  WHERE tenant_id = ? AND status = 'stored'"
            );
            $st->execute([$tid]);
            $row = $st->fetch() ?: [];
            $warehouse = [
                'qty' => round((float) ($row['qty'] ?? 0), 2),
                'capital' => round((float) ($row['capital'] ?? 0), 2),
                'lines' => (int) ($row['lines'] ?? 0),
            ];
        } catch (\Throwable $ignored) {
        }
        try {
            $st = $this->db->prepare(
                "SELECT COUNT(*) AS lines,
                        COALESCE(SUM(quantity),0) AS qty,
                        COALESCE(SUM(quantity * COALESCE(buying_price,0)),0) AS capital
                   FROM products
                  WHERE tenant_id = ? AND status IN ('active','draft')"
            );
            $st->execute([$tid]);
            $row = $st->fetch() ?: [];
            $shop = [
                'qty' => round((float) ($row['qty'] ?? 0), 2),
                'capital' => round((float) ($row['capital'] ?? 0), 2),
                'lines' => (int) ($row['lines'] ?? 0),
            ];
        } catch (\Throwable $ignored) {
        }
        return [
            'warehouse' => $warehouse,
            'shop' => $shop,
            'total_capital' => round($warehouse['capital'] + $shop['capital'], 2),
        ];
    }

    /** Internal transfer invoices in a period (Store → Inventory). */
    public function transfersForPeriod(string $period = 'all', int $limit = 100): array
    {
        $tid = \TenantContext::tenantId();
        $where = $this->periodSql($period, 'si.created_at');
        $stmt = $this->db->prepare(
            "SELECT si.*, u.username AS created_by_name,
                    (SELECT COUNT(*) FROM store_invoice_items sii WHERE sii.invoice_id = si.id) AS item_count
               FROM store_invoices si
          LEFT JOIN users u ON u.id = si.created_by
              WHERE si.tenant_id = ? AND {$where}
           ORDER BY si.created_at DESC, si.id DESC
              LIMIT " . (int) $limit
        );
        $stmt->execute([$tid]);
        return $stmt->fetchAll();
    }

    public function transferTotalForPeriod(string $period = 'all'): float
    {
        $tid = \TenantContext::tenantId();
        $where = $this->periodSql($period, 'created_at');
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(total),0) FROM store_invoices WHERE tenant_id = ? AND {$where}"
        );
        $stmt->execute([$tid]);
        return round((float) $stmt->fetchColumn(), 2);
    }

    private function periodSql(string $period, string $col): string
    {
        if ($period === 'today') {
            return "DATE({$col}) = CURDATE()";
        }
        if ($period === 'week') {
            return "{$col} >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        }
        if ($period === 'month') {
            return "{$col} >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        }
        return '1=1';
    }

    public function invoice(int $id): ?array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            "SELECT si.*, u.username AS created_by_name
               FROM store_invoices si
          LEFT JOIN users u ON u.id = si.created_by
              WHERE si.id = ? AND si.tenant_id = ? LIMIT 1"
        );
        $stmt->execute([$id, $tid]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function invoiceItems(int $invoiceId): array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare('SELECT * FROM store_invoice_items WHERE invoice_id = ? AND tenant_id = ? ORDER BY id ASC');
        $stmt->execute([$invoiceId, $tid]);
        return $stmt->fetchAll();
    }

    public function updatePending(int $id, array $item): array
    {
        $tid = \TenantContext::tenantId();
        $name = trim((string) ($item['name'] ?? ''));
        $qty = (float) ($item['quantity'] ?? 0);
        if ($name === '' || $qty <= 0) {
            return ['ok' => false, 'error' => 'Enter product name and quantity.'];
        }
        $stmt = $this->db->prepare("SELECT id FROM store_products WHERE id = ? AND tenant_id = ? AND status = 'stored' LIMIT 1");
        $stmt->execute([$id, $tid]);
        if (!$stmt->fetch()) {
            return ['ok' => false, 'error' => 'Stored product not found or already transferred.'];
        }
        $this->db->prepare(
            'UPDATE store_products
                SET name = ?, category_id = ?, brand_id = ?, supplier_id = ?, barcode = ?, unit = ?, colors = ?,
                    quantity = ?, faulty_quantity = ?, buying_price = ?, package_buying_price = ?, retail_price = ?, wholesale_price = ?,
                    package_unit = ?, package_quantity = ?, units_per_package = ?, package_price = ?, notes = ?
              WHERE id = ? AND tenant_id = ? AND status = \'stored\''
        )->execute([
            $name,
            (int) ($item['category_id'] ?? 0) ?: null,
            (int) ($item['brand_id'] ?? 0) ?: null,
            (int) ($item['supplier_id'] ?? 0) ?: null,
            trim((string) ($item['barcode'] ?? '')) ?: null,
            $item['unit'] ?? 'piece',
            trim((string) ($item['colors'] ?? '')) ?: null,
            $qty,
            max(0, (float) ($item['faulty_quantity'] ?? 0)),
            max(0, (float) ($item['buying_price'] ?? 0)),
            ($item['package_buying_price'] ?? '') !== '' ? max(0, (float) $item['package_buying_price']) : null,
            max(0, (float) ($item['retail_price'] ?? 0)),
            max(0, (float) ($item['wholesale_price'] ?? 0)),
            trim((string) ($item['package_unit'] ?? '')) ?: null,
            ($item['package_quantity'] ?? '') !== '' ? max(0, (float) $item['package_quantity']) : null,
            ($item['units_per_package'] ?? '') !== '' ? max(0.01, (float) $item['units_per_package']) : null,
            ($item['package_price'] ?? '') !== '' ? max(0, (float) $item['package_price']) : null,
            trim((string) ($item['notes'] ?? '')) ?: null,
            $id,
            $tid,
        ]);
        return ['ok' => true, 'error' => null];
    }

    public function deletePending(int $id): array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare("DELETE FROM store_products WHERE id = ? AND tenant_id = ? AND status = 'stored'");
        $stmt->execute([$id, $tid]);
        return $stmt->rowCount() > 0
            ? ['ok' => true, 'error' => null]
            : ['ok' => false, 'error' => 'Stored product not found or already transferred.'];
    }

    /**
     * Internal transfer Store → Inventory.
     * $packageQuantities[store_product_id] = how many sealed packages (cartons/bales) to move.
     * Never opens a package — only whole packages. Does not default to transferring everything.
     */
    public function generateInvoice(array $ids, string $invoiceTo, string $notes, int $staffId, array $packageQuantities = []): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return ['ok' => false, 'invoice_id' => null, 'error' => 'Choose at least one stored product.'];
        }
        $tid = \TenantContext::tenantId();
        $db = $this->db;
        $productModel = new ProductModel($db);
        try {
            $db->beginTransaction();
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("SELECT * FROM store_products WHERE tenant_id = ? AND status = 'stored' AND id IN ($in) FOR UPDATE");
            $stmt->execute(array_merge([$tid], $ids));
            $items = $stmt->fetchAll();
            if (!$items) {
                $db->rollBack();
                return ['ok' => false, 'invoice_id' => null, 'error' => 'No stored products are available for that invoice.'];
            }

            $subtotal = 0.0;
            $invoiceItems = [];
            foreach ($items as $it) {
                $unitsPerPkg = max(0.01, (float) ($it['units_per_package'] ?? 1));
                $availableItems = (float) $it['quantity'];
                $availablePkgs = !empty($it['package_unit'])
                    ? (float) ($it['package_quantity'] ?? 0)
                    : 0.0;
                if ($availablePkgs <= 0 && $availableItems > 0 && $unitsPerPkg > 0) {
                    $availablePkgs = round($availableItems / $unitsPerPkg, 2);
                }
                if ($availablePkgs <= 0) {
                    $db->rollBack();
                    return ['ok' => false, 'invoice_id' => null, 'error' => ($it['name'] ?? 'Product') . ': no sealed packages left to transfer. Transfers are by package only.'];
                }

                $wantedPkgs = isset($packageQuantities[(int) $it['id']])
                    ? (float) $packageQuantities[(int) $it['id']]
                    : 0.0;
                // Whole packages only — no opening cartons in the warehouse transfer.
                $wantedPkgs = floor(max(0, $wantedPkgs) + 1e-9);
                $pkgs = min($availablePkgs, $wantedPkgs);
                if ($pkgs <= 0) {
                    continue;
                }
                $qty = round($pkgs * $unitsPerPkg, 2);
                if ($qty > $availableItems + 0.0001) {
                    $qty = $availableItems;
                    $pkgs = round($qty / $unitsPerPkg, 2);
                }
                if ($qty <= 0) {
                    continue;
                }
                $copy = $it;
                $copy['quantity'] = $qty;
                $copy['transfer_packages'] = $pkgs;
                $copy['package_quantity'] = $pkgs;
                // Sealed packages only — do not move opened/faulty units from the warehouse.
                $copy['faulty_quantity'] = 0;
                $invoiceItems[] = $copy;
                $pkgBuy = ($it['package_buying_price'] ?? '') !== '' && (float) $it['package_buying_price'] > 0
                    ? (float) $it['package_buying_price']
                    : ((float) $it['buying_price'] * $unitsPerPkg);
                $subtotal += $pkgs * $pkgBuy;
            }
            if (!$invoiceItems) {
                $db->rollBack();
                return ['ok' => false, 'invoice_id' => null, 'error' => 'Enter how many packages (cartons/bales) to transfer for at least one selected product.'];
            }

            $db->prepare('INSERT INTO store_invoices (tenant_id, invoice_number, invoice_to, total, notes, created_by) VALUES (?,?,?,?,?,?)')
               ->execute([$tid, 'PENDING', trim($invoiceTo) ?: 'Shop Inventory', round($subtotal, 2), trim($notes) ?: null, $staffId]);
            $invoiceId = (int) $db->lastInsertId();
            if ($invoiceId <= 0) {
                $db->rollBack();
                return ['ok' => false, 'invoice_id' => null, 'error' => 'Could not save the transfer invoice record.'];
            }
            $number = 'STR-' . str_pad((string) $invoiceId, 6, '0', STR_PAD_LEFT);
            $db->prepare('UPDATE store_invoices SET invoice_number = ? WHERE id = ? AND tenant_id = ?')->execute([$number, $invoiceId, $tid]);

            foreach ($invoiceItems as $it) {
                $productId = $this->transferOneToInventory($productModel, $it);
                if ($productId <= 0) {
                    throw new \RuntimeException('Transfer did not create/update an inventory product for ' . ($it['name'] ?? 'item'));
                }
                $qty = (float) $it['quantity'];
                $unitPrice = (float) $it['buying_price'];
                $unitPackageQty = max(0.01, (float) ($it['units_per_package'] ?? 1));
                $packageQty = (float) ($it['transfer_packages'] ?? $it['package_quantity'] ?? round($qty / $unitPackageQty, 2));
                $lineTotal = round($qty * $unitPrice, 2);
                $db->prepare(
                    'INSERT INTO store_invoice_items
                        (tenant_id, invoice_id, store_product_id, product_id, product_name, quantity, unit, package_unit, package_quantity, units_per_package, package_price, unit_price, line_total)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $tid, $invoiceId, (int) $it['id'], $productId, $it['name'], $qty, $it['unit'],
                    $it['package_unit'] ?? null, $packageQty, ($it['units_per_package'] ?? null), ($it['package_price'] ?? null),
                    $unitPrice, $lineTotal,
                ]);
                $originalQty = 0.0;
                $originalPkgs = 0.0;
                foreach ($items as $raw) {
                    if ((int) $raw['id'] === (int) $it['id']) {
                        $originalQty = (float) $raw['quantity'];
                        $units = max(0.01, (float) ($raw['units_per_package'] ?? 1));
                        $originalPkgs = (float) ($raw['package_quantity'] ?? 0);
                        if ($originalPkgs <= 0 && $originalQty > 0) {
                            $originalPkgs = round($originalQty / $units, 2);
                        }
                        break;
                    }
                }
                $remaining = max(0, round($originalQty - $qty, 2));
                $remainingPackageQty = max(0, round($originalPkgs - $packageQty, 2));
                if ($remaining > 0.0001) {
                    $db->prepare('UPDATE store_products SET quantity = ?, package_quantity = ?, product_id = ? WHERE id = ? AND tenant_id = ?')
                       ->execute([$remaining, $remainingPackageQty, $productId, (int) $it['id'], $tid]);
                } else {
                    $db->prepare("UPDATE store_products SET status = 'transferred', quantity = ?, package_quantity = ?, product_id = ?, transferred_invoice_id = ?, transferred_at = NOW() WHERE id = ? AND tenant_id = ?")
                       ->execute([$qty, $packageQty, $productId, $invoiceId, (int) $it['id'], $tid]);
                }
            }

            // Confirm the invoice row really exists before committing the stock move.
            $check = $db->prepare('SELECT id, invoice_number FROM store_invoices WHERE id = ? AND tenant_id = ? LIMIT 1');
            $check->execute([$invoiceId, $tid]);
            $saved = $check->fetch();
            if (!$saved) {
                throw new \RuntimeException('Transfer invoice was not persisted.');
            }

            $db->commit();
            return [
                'ok' => true,
                'invoice_id' => $invoiceId,
                'invoice_number' => (string) ($saved['invoice_number'] ?: $number),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('StoreProductModel::generateInvoice failed: ' . $e->getMessage());
            return ['ok' => false, 'invoice_id' => null, 'error' => 'Could not generate and save the transfer invoice. ' . $e->getMessage()];
        }
    }

    public function deleteInvoice(int $invoiceId): array
    {
        $tid = \TenantContext::tenantId();
        $db = $this->db;
        try {
            $db->beginTransaction();
            $stmt = $db->prepare('SELECT * FROM store_invoices WHERE id = ? AND tenant_id = ? FOR UPDATE');
            $stmt->execute([$invoiceId, $tid]);
            $invoice = $stmt->fetch();
            if (!$invoice) {
                $db->rollBack();
                return ['ok' => false, 'error' => 'Store invoice not found.'];
            }
            $itemsStmt = $db->prepare('SELECT * FROM store_invoice_items WHERE invoice_id = ? AND tenant_id = ? ORDER BY id ASC');
            $itemsStmt->execute([$invoiceId, $tid]);
            $items = $itemsStmt->fetchAll();
            $dec = $db->prepare('UPDATE products SET quantity = quantity - ? WHERE id = ? AND tenant_id = ? AND quantity >= ?');
            foreach ($items as $it) {
                $qty = (float) $it['quantity'];
                if ((int) ($it['product_id'] ?? 0) > 0) {
                    $dec->execute([$qty, (int) $it['product_id'], $tid, $qty]);
                    if ($dec->rowCount() !== 1) {
                        $db->rollBack();
                        return ['ok' => false, 'error' => 'Cannot delete this store invoice because some transferred stock has already been sold.'];
                    }
                }
                $sp = $db->prepare('SELECT id, status FROM store_products WHERE id = ? AND tenant_id = ? FOR UPDATE');
                $sp->execute([(int) $it['store_product_id'], $tid]);
                $stored = $sp->fetch();
                if ($stored) {
                    if (($stored['status'] ?? '') === 'transferred') {
                        $packageQty = !empty($it['package_unit']) && (float) ($it['units_per_package'] ?? 0) > 0
                            ? round($qty / (float) $it['units_per_package'], 2)
                            : null;
                        $db->prepare("UPDATE store_products SET status = 'stored', quantity = ?, package_quantity = ?, transferred_invoice_id = NULL, transferred_at = NULL WHERE id = ? AND tenant_id = ?")
                           ->execute([$qty, $packageQty, (int) $it['store_product_id'], $tid]);
                    } else {
                        $packageQty = !empty($it['package_unit']) && (float) ($it['units_per_package'] ?? 0) > 0
                            ? round($qty / (float) $it['units_per_package'], 2)
                            : 0;
                        $db->prepare('UPDATE store_products SET quantity = quantity + ?, package_quantity = COALESCE(package_quantity, 0) + ? WHERE id = ? AND tenant_id = ?')
                           ->execute([$qty, $packageQty, (int) $it['store_product_id'], $tid]);
                    }
                }
            }
            $db->prepare('DELETE FROM store_invoice_items WHERE invoice_id = ? AND tenant_id = ?')->execute([$invoiceId, $tid]);
            $db->prepare('DELETE FROM store_invoices WHERE id = ? AND tenant_id = ?')->execute([$invoiceId, $tid]);
            $db->commit();
            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('StoreProductModel::deleteInvoice failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not delete the store invoice.'];
        }
    }

    private function transferOneToInventory(ProductModel $productModel, array $it): int
    {
        $tid = \TenantContext::tenantId();
        $barcode = trim((string) ($it['barcode'] ?? ''));
        $existing = null;
        $storedProductId = (int) ($it['product_id'] ?? 0);
        if ($storedProductId > 0) {
            $existing = $productModel->find($storedProductId);
            if (!$existing || (int) $existing['tenant_id'] !== (int) $tid) {
                $existing = null;
            }
        }
        if ($barcode !== '') {
            $existing = $existing ?: $productModel->findByBarcode($barcode);
        }
        $existing = $existing ?: $this->findExistingInventoryProduct($it);
        if ($existing) {
            $this->restockExistingInventoryProduct((int) $existing['id'], $it);
            return (int) $existing['id'];
        }
        $res = $productModel->create([
            'product_type' => 'product',
            'name' => $it['name'],
            'category_id' => (int) ($it['category_id'] ?? 0),
            'brand_id' => (int) ($it['brand_id'] ?? 0),
            'supplier_id' => (int) ($it['supplier_id'] ?? 0),
            'barcode' => $barcode,
            'unit' => $it['unit'] ?: 'piece',
            'units_per_pack' => max(0.01, (float) ($it['units_per_package'] ?? 1)),
            'pack_unit' => $it['package_unit'] ?? null,
            'pack_price' => ($it['package_price'] ?? '') !== '' ? (float) $it['package_price'] : null,
            'colors' => $it['colors'] ? array_map('trim', explode(',', $it['colors'])) : [],
            'quantity' => (float) $it['quantity'],
            'faulty_quantity' => (float) ($it['faulty_quantity'] ?? 0),
            'buying_price' => (float) $it['buying_price'],
            'package_buying_price' => $it['package_buying_price'] ?? null,
            'retail_price' => (float) $it['retail_price'],
            'wholesale_price' => (float) ($it['wholesale_price'] ?: $it['retail_price']),
            'offer_price' => $it['offer_price'] ?? '',
            'offer_starts_at' => $it['offer_starts_at'] ?? '',
            'offer_ends_at' => $it['offer_ends_at'] ?? '',
            'image_path' => $it['image_path'] ?? '',
            'status' => 'active',
        ]);
        if (!$res['ok']) {
            throw new \RuntimeException('Could not create inventory product: ' . json_encode($res['errors']));
        }
        return (int) $res['id'];
    }

    private function restockExistingInventoryProduct(int $productId, array $it): void
    {
        $tid = \TenantContext::tenantId();
        $sets = [
            'quantity = quantity + ?',
            'faulty_quantity = faulty_quantity + ?',
            'buying_price = ?',
            'package_buying_price = ?',
            'retail_price = ?',
            'selling_price = ?',
            'wholesale_price = ?',
            'unit = ?',
            'units_per_pack = ?',
            'pack_unit = ?',
            'pack_price = ?',
        ];
        $params = [
            (float) $it['quantity'],
            max(0, (float) ($it['faulty_quantity'] ?? 0)),
            (float) $it['buying_price'],
            ($it['package_buying_price'] ?? '') !== '' ? (float) $it['package_buying_price'] : null,
            (float) $it['retail_price'],
            (float) $it['retail_price'],
            (float) ($it['wholesale_price'] ?: $it['retail_price']),
            $it['unit'] ?: 'piece',
            max(0.01, (float) ($it['units_per_package'] ?? 1)),
            $it['package_unit'] ?? null,
            ($it['package_price'] ?? '') !== '' ? (float) $it['package_price'] : null,
        ];
        foreach (['category_id', 'brand_id', 'supplier_id'] as $column) {
            if ((int) ($it[$column] ?? 0) > 0) {
                $sets[] = $column . ' = ?';
                $params[] = (int) $it[$column];
            }
        }
        $colors = $it['colors'] ? array_values(array_filter(array_map('trim', explode(',', $it['colors'])))) : [];
        if ($colors) {
            $sets[] = 'colors = ?';
            $params[] = json_encode($colors);
        }
        if (($it['image_path'] ?? '') !== '') {
            $sets[] = 'image_path = ?';
            $params[] = $it['image_path'];
        }
        if (($it['offer_price'] ?? '') !== '') {
            $sets[] = 'offer_price = ?';
            $sets[] = 'offer_starts_at = ?';
            $sets[] = 'offer_ends_at = ?';
            $params[] = (float) $it['offer_price'];
            $params[] = $it['offer_starts_at'] ?: null;
            $params[] = $it['offer_ends_at'] ?: null;
        }
        $params[] = $productId;
        $params[] = $tid;
        $this->db->prepare('UPDATE products SET ' . implode(', ', $sets) . ' WHERE id = ? AND tenant_id = ?')->execute($params);
    }

    private function findExistingInventoryProduct(array $it): ?array
    {
        $tid = \TenantContext::tenantId();
        $name = trim((string) ($it['name'] ?? ''));
        if ($name === '') {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT * FROM products
              WHERE tenant_id = ? AND status IN ('active','draft','archived') AND LOWER(name) = LOWER(?)
                AND COALESCE(category_id, 0) = ? AND COALESCE(brand_id, 0) = ?
              ORDER BY id ASC LIMIT 1"
        );
        $stmt->execute([
            $tid,
            $name,
            (int) ($it['category_id'] ?? 0),
            (int) ($it['brand_id'] ?? 0),
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function dateOrNull($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || strtotime($value) === false) {
            return null;
        }
        return date('Y-m-d H:i:s', strtotime($value));
    }

    private function ensureSchema(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS store_products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                product_id INT NULL,
                transferred_invoice_id INT NULL,
                name VARCHAR(160) NOT NULL,
                category_id INT NULL,
                brand_id INT NULL,
                supplier_id INT NULL,
                barcode VARCHAR(64) NULL,
                unit VARCHAR(20) NOT NULL DEFAULT 'piece',
                package_unit VARCHAR(20) NULL,
                package_quantity DECIMAL(12,2) NULL,
                units_per_package DECIMAL(12,2) NULL,
                package_price DECIMAL(12,2) NULL,
                colors VARCHAR(255) NULL,
                quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                faulty_quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                buying_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                package_buying_price DECIMAL(12,2) NULL,
                retail_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                wholesale_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                offer_price DECIMAL(12,2) NULL,
                offer_starts_at DATETIME NULL,
                offer_ends_at DATETIME NULL,
                image_path VARCHAR(255) NULL,
                notes VARCHAR(255) NULL,
                status ENUM('stored','transferred') NOT NULL DEFAULT 'stored',
                created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                transferred_at DATETIME NULL,
                KEY idx_store_products_tenant_status (tenant_id, status),
                KEY idx_store_products_invoice (tenant_id, transferred_invoice_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $this->ensureColumn('store_products', 'supplier_id', "ALTER TABLE `store_products` ADD COLUMN `supplier_id` INT NULL AFTER `brand_id`");
        $this->ensureColumn('store_products', 'package_unit', "ALTER TABLE `store_products` ADD COLUMN `package_unit` VARCHAR(20) NULL AFTER `unit`");
        $this->ensureColumn('store_products', 'package_quantity', "ALTER TABLE `store_products` ADD COLUMN `package_quantity` DECIMAL(12,2) NULL AFTER `package_unit`");
        $this->ensureColumn('store_products', 'units_per_package', "ALTER TABLE `store_products` ADD COLUMN `units_per_package` DECIMAL(12,2) NULL AFTER `package_quantity`");
        $this->ensureColumn('store_products', 'package_price', "ALTER TABLE `store_products` ADD COLUMN `package_price` DECIMAL(12,2) NULL AFTER `units_per_package`");
        $this->ensureColumn('store_products', 'package_buying_price', "ALTER TABLE `store_products` ADD COLUMN `package_buying_price` DECIMAL(12,2) NULL AFTER `buying_price`");
        $this->ensureColumn('store_products', 'offer_price', "ALTER TABLE `store_products` ADD COLUMN `offer_price` DECIMAL(12,2) NULL AFTER `wholesale_price`");
        $this->ensureColumn('store_products', 'offer_starts_at', "ALTER TABLE `store_products` ADD COLUMN `offer_starts_at` DATETIME NULL AFTER `offer_price`");
        $this->ensureColumn('store_products', 'offer_ends_at', "ALTER TABLE `store_products` ADD COLUMN `offer_ends_at` DATETIME NULL AFTER `offer_starts_at`");
        $this->ensureColumn('store_products', 'image_path', "ALTER TABLE `store_products` ADD COLUMN `image_path` VARCHAR(255) NULL AFTER `offer_ends_at`");
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS store_invoices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                invoice_number VARCHAR(32) NOT NULL,
                invoice_to VARCHAR(160) NULL,
                total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                notes VARCHAR(255) NULL,
                created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_store_invoice (tenant_id, invoice_number),
                KEY idx_store_invoice_tenant (tenant_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS store_invoice_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                invoice_id INT NOT NULL,
                store_product_id INT NOT NULL,
                product_id INT NULL,
                product_name VARCHAR(160) NOT NULL,
                quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                unit VARCHAR(20) NULL,
                package_unit VARCHAR(20) NULL,
                package_quantity DECIMAL(12,2) NULL,
                units_per_package DECIMAL(12,2) NULL,
                package_price DECIMAL(12,2) NULL,
                unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                KEY idx_store_invoice_items_invoice (tenant_id, invoice_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $this->ensureColumn('store_invoice_items', 'package_unit', "ALTER TABLE `store_invoice_items` ADD COLUMN `package_unit` VARCHAR(20) NULL AFTER `unit`");
        $this->ensureColumn('store_invoice_items', 'package_quantity', "ALTER TABLE `store_invoice_items` ADD COLUMN `package_quantity` DECIMAL(12,2) NULL AFTER `package_unit`");
        $this->ensureColumn('store_invoice_items', 'units_per_package', "ALTER TABLE `store_invoice_items` ADD COLUMN `units_per_package` DECIMAL(12,2) NULL AFTER `package_quantity`");
        $this->ensureColumn('store_invoice_items', 'package_price', "ALTER TABLE `store_invoice_items` ADD COLUMN `package_price` DECIMAL(12,2) NULL AFTER `units_per_package`");
    }

    private function ensureColumn(string $table, string $column, string $sql): void
    {
        try {
            $this->db->query("SELECT `{$column}` FROM `{$table}` LIMIT 1");
        } catch (\PDOException $e) {
            try {
                $this->db->exec($sql);
            } catch (\PDOException $ignored) {
            }
        }
    }
}
