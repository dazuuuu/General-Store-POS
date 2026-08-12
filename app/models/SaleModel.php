<?php
// app/models/SaleModel.php
namespace Models;

class SaleModel extends Model
{
    protected string $table = 'sales';

    public function __construct(?\PDO $db = null)
    {
        parent::__construct($db);
        $this->ensureSchema();
    }

    /**
     * Record a sale atomically. Supports cash / mpesa / split (all fully paid)
     * and CREDIT (customer owes all or part; an optional deposit is taken now).
     */
    public function record(array $in): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            return ['ok' => false, 'errors' => ['_' => 'No shop in context.']];
        }

        $saleType = in_array($in['sale_type'] ?? '', ['retail', 'wholesale'], true) ? $in['sale_type'] : 'retail';
        $method = in_array($in['payment_method'] ?? '', ['cash', 'mpesa', 'split', 'credit'], true) ? $in['payment_method'] : null;
        if (!$method) {
            return ['ok' => false, 'errors' => ['payment_method' => 'Choose how the customer paid.']];
        }

        // Credit requires a named customer to attach the debt to.
        $customerName = trim($in['customer_name'] ?? '');
        if ($method === 'credit' && $customerName === '') {
            return ['ok' => false, 'errors' => ['customer_name' => 'Enter the customer name for a credit sale.']];
        }

        $items = array_values(array_filter($in['items'] ?? [], fn($i) => (int)($i['product_id'] ?? 0) > 0 && (float)($i['quantity'] ?? 0) > 0));
        if (!$items) {
            return ['ok' => false, 'errors' => ['_' => 'Add at least one product to the sale.']];
        }

        $discount = max(0, round((float) ($in['discount_amount'] ?? 0), 2));

        $db = $this->db;
        try {
            $db->beginTransaction();

            $selSql = "SELECT id, name, selling_price, wholesale_price, retail_price, offer_price, offer_starts_at, offer_ends_at,
                              quantity, unit, units_per_pack, pack_unit, pack_price
                         FROM products WHERE id = ? AND tenant_id = ? AND status = 'active' FOR UPDATE";
            $sel = $db->prepare($selSql);
            $subtotal = 0.0;
            $lines = [];
            foreach ($items as $it) {
                $pid = (int) $it['product_id'];
                $qty = (float) $it['quantity'];
                $sel->execute([$pid, $tid]);
                $p = $sel->fetch();
                if (!$p) {
                    $db->rollBack();
                    return ['ok' => false, 'errors' => ['_' => 'One of the products is no longer available. Refresh and try again.']];
                }
                if ($qty > (float) $p['quantity']) {
                    $db->rollBack();
                    return ['ok' => false, 'errors' => ['_' => "Not enough stock for {$p['name']} — only " . rtrim(rtrim(number_format((float)$p['quantity'], 2), '0'), '.') . " left."]];
                }
                $unitPrice = \Pricing::unitPriceForQty($p, $qty, $saleType);
                if ($unitPrice <= 0) {
                    $unitPrice = (float) ($p['selling_price'] ?? 0);
                }
                $lineTotal = round($unitPrice * $qty, 2);
                $subtotal += $lineTotal;
                $lines[] = [
                    'product_id'   => $pid,
                    'product_name' => $p['name'],
                    'unit'         => $p['unit'],
                    'unit_price'   => $unitPrice,
                    'price_type'   => $saleType,
                    'quantity'     => $qty,
                    'line_total'   => $lineTotal,
                ];
            }
            $subtotal = round($subtotal, 2);
            if ($discount > $subtotal) {
                $db->rollBack();
                return ['ok' => false, 'errors' => ['discount_amount' => 'Discount cannot exceed the subtotal.']];
            }
            $total = round($subtotal - $discount, 2);

            $cashAmount  = 0.0;
            $mpesaAmount = 0.0;
            $amountGiven = null;
            $change      = null;
            $amountPaid  = $total;   // default: fully paid
            $amountDue   = 0.0;
            $paymentStatus = 'paid';
            $depositMethod = null;
            $depositAmt    = 0.0;

            if ($method === 'cash') {
                $cashAmount = $total;
                $amountGiven = (float) ($in['amount_given'] ?? 0);
                if ($amountGiven + 0.0001 < $total) {
                    $db->rollBack();
                    return ['ok' => false, 'errors' => ['amount_given' => 'Cash given is less than the total.']];
                }
                $change = round($amountGiven - $total, 2);
            } elseif ($method === 'mpesa') {
                $mpesaAmount = $total;
                $amountGiven = $total;
                $change = 0.0;
            } elseif ($method === 'split') {
                $cashAmount  = max(0, round((float) ($in['cash_amount'] ?? 0), 2));
                $mpesaAmount = max(0, round((float) ($in['mpesa_amount'] ?? 0), 2));
                if (abs(($cashAmount + $mpesaAmount) - $total) > 0.01) {
                    $db->rollBack();
                    return ['ok' => false, 'errors' => ['_' => 'Cash and M-Pesa amounts must add up to the total (KES ' . number_format($total, 0) . ').']];
                }
                if ($cashAmount > 0) {
                    $amountGiven = (float) ($in['amount_given'] ?? $cashAmount);
                    if ($amountGiven + 0.0001 < $cashAmount) {
                        $db->rollBack();
                        return ['ok' => false, 'errors' => ['amount_given' => 'Cash given is less than the cash portion.']];
                    }
                    $change = round($amountGiven - $cashAmount, 2);
                } else {
                    $amountGiven = $mpesaAmount;
                    $change = 0.0;
                }
            } else { // credit
                $depositAmt = max(0, round((float) ($in['amount_paid'] ?? 0), 2));
                if ($depositAmt > $total) { $depositAmt = $total; }
                $depositMethod = in_array($in['deposit_method'] ?? '', ['cash', 'mpesa'], true) ? $in['deposit_method'] : 'cash';
                // Route the deposit into the right cash/mpesa bucket for reporting.
                if ($depositMethod === 'mpesa') { $mpesaAmount = $depositAmt; } else { $cashAmount = $depositAmt; }
                $amountPaid = $depositAmt;
                $amountDue  = round($total - $depositAmt, 2);
                $amountGiven = $depositAmt;
                $change = 0.0;
                $paymentStatus = $amountDue <= 0.0001 ? 'paid' : ($depositAmt > 0 ? 'part_paid' : 'credit');
            }

            $ins = $db->prepare(
                "INSERT INTO sales (tenant_id, staff_id, sale_type, receipt_number, payment_method, payment_status,
                    total, subtotal, discount_amount, amount_paid, amount_due, amount_given, change_given, cash_amount, mpesa_amount,
                    customer_name, customer_phone, customer_email, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'completed')"
            );
            $ins->execute([
                $tid,
                (int) $in['staff_id'],
                $saleType,
                'PENDING',
                $method,
                $paymentStatus,
                $total,
                $subtotal,
                $discount,
                $amountPaid,
                $amountDue,
                $amountGiven,
                $change,
                $cashAmount > 0 ? $cashAmount : null,
                $mpesaAmount > 0 ? $mpesaAmount : null,
                $customerName !== '' ? $customerName : null,
                ($in['customer_phone'] ?? '') !== '' ? trim($in['customer_phone']) : null,
                ($in['customer_email'] ?? '') !== '' ? trim($in['customer_email']) : null,
            ]);
            $saleId  = (int) $db->lastInsertId();
            $receipt = 'RCP-' . str_pad((string) $saleId, 6, '0', STR_PAD_LEFT);
            $db->prepare("UPDATE sales SET receipt_number = ? WHERE id = ?")->execute([$receipt, $saleId]);

            $insItem = $db->prepare(
                "INSERT INTO sale_items (tenant_id, sale_id, product_id, product_name, unit, unit_price, price_type, quantity, line_total)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            $dec = $db->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ? AND tenant_id = ? AND quantity >= ?");
            foreach ($lines as $l) {
                $insItem->execute([
                    $tid, $saleId, $l['product_id'], $l['product_name'], $l['unit'],
                    $l['unit_price'], $l['price_type'], $l['quantity'], $l['line_total'],
                ]);
                $dec->execute([$l['quantity'], $l['product_id'], $tid, $l['quantity']]);
                if ($dec->rowCount() !== 1) {
                    $db->rollBack();
                    return ['ok' => false, 'errors' => ['_' => "Stock changed for {$l['product_name']} while saving. Please redo the sale."]];
                }
            }

            // Record the credit deposit as the first repayment, so history is complete.
            if ($method === 'credit' && $depositAmt > 0) {
                $db->prepare(
                    "INSERT INTO sale_payments (tenant_id, sale_id, staff_id, amount, method, note)
                     VALUES (?,?,?,?,?,?)"
                )->execute([$tid, $saleId, (int) $in['staff_id'], $depositAmt, $depositMethod, 'Deposit at sale']);
            }

            $db->commit();
            return ['ok' => true, 'sale_id' => $saleId, 'receipt_number' => $receipt, 'amount_due' => $amountDue, 'errors' => []];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            error_log('SaleModel::record failed: ' . $e->getMessage());
            return ['ok' => false, 'errors' => ['_' => 'Could not complete the sale. Please try again.']];
        }
    }

    /**
     * Record a repayment against a credit sale. Atomic; clamps to what's owed.
     * When $onlyStaffId is given, the sale must belong to that staff (per-staff scope).
     */
    public function recordRepayment(int $saleId, float $amount, string $method, int $staffId, ?int $onlyStaffId = null): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) { return ['ok' => false, 'error' => 'No shop in context.']; }
        $method = in_array($method, ['cash', 'mpesa'], true) ? $method : 'cash';
        $amount = round((float) $amount, 2);
        if ($amount <= 0) { return ['ok' => false, 'error' => 'Enter an amount greater than zero.']; }

        $db = $this->db;
        try {
            $db->beginTransaction();
            $sql = "SELECT id, total, amount_paid, amount_due, staff_id FROM sales
                     WHERE id = ? AND tenant_id = ?" . ($onlyStaffId !== null ? " AND staff_id = ?" : "") . " FOR UPDATE";
            $params = [$saleId, $tid];
            if ($onlyStaffId !== null) { $params[] = $onlyStaffId; }
            $st = $db->prepare($sql);
            $st->execute($params);
            $sale = $st->fetch();
            if (!$sale) { $db->rollBack(); return ['ok' => false, 'error' => 'Credit sale not found.']; }

            $due = round((float) $sale['amount_due'], 2);
            if ($due <= 0) { $db->rollBack(); return ['ok' => false, 'error' => 'This sale is already fully paid.']; }
            $pay = min($amount, $due);

            $db->prepare(
                "INSERT INTO sale_payments (tenant_id, sale_id, staff_id, amount, method, note) VALUES (?,?,?,?,?,?)"
            )->execute([$tid, $saleId, $staffId, $pay, $method, 'Repayment']);

            $newPaid = round((float) $sale['amount_paid'] + $pay, 2);
            $newDue  = round($due - $pay, 2);
            $status  = $newDue <= 0.0001 ? 'paid' : 'part_paid';
            $db->prepare(
                "UPDATE sales SET amount_paid = ?, amount_due = ?, payment_status = ? WHERE id = ? AND tenant_id = ?"
            )->execute([$newPaid, $newDue, $status, $saleId, $tid]);

            $db->commit();
            return ['ok' => true, 'paid' => $pay, 'amount_due' => $newDue, 'status' => $status];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            return ['ok' => false, 'error' => 'Could not record the payment. Please try again.'];
        }
    }

    /** Outstanding credit sales for one staff member (amount_due > 0). */
    public function creditsForStaff(int $staffId): array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            "SELECT s.*, u.username AS staff_name
               FROM sales s LEFT JOIN users u ON u.id = s.staff_id
              WHERE s.tenant_id = ? AND s.staff_id = ? AND s.amount_due > 0
           ORDER BY s.created_at ASC, s.id ASC"
        );
        $stmt->execute([$tid, $staffId]);
        return $stmt->fetchAll();
    }

    /** All outstanding credit sales for the shop (owner view). */
    public function creditsAll(): array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            "SELECT s.*, u.username AS staff_name
               FROM sales s LEFT JOIN users u ON u.id = s.staff_id
              WHERE s.tenant_id = ? AND s.amount_due > 0
           ORDER BY s.created_at ASC, s.id ASC"
        );
        $stmt->execute([$tid]);
        return $stmt->fetchAll();
    }

    /** Total still owed — optionally restricted to one staff member. */
    public function creditsOwed(?int $staffId = null): float
    {
        $tid = \TenantContext::tenantId();
        $sql = "SELECT COALESCE(SUM(amount_due),0) FROM sales WHERE tenant_id = ? AND amount_due > 0";
        $params = [$tid];
        if ($staffId !== null) { $sql .= " AND staff_id = ?"; $params[] = $staffId; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return round((float) $stmt->fetchColumn(), 2);
    }

    /** Repayment history for a single sale. */
    public function repayments(int $saleId): array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            "SELECT sp.*, u.username AS staff_name
               FROM sale_payments sp LEFT JOIN users u ON u.id = sp.staff_id
              WHERE sp.sale_id = ? AND sp.tenant_id = ?
           ORDER BY sp.created_at ASC, sp.id ASC"
        );
        $stmt->execute([$saleId, $tid]);
        return $stmt->fetchAll();
    }

    public function findScoped(int $id): ?array
    {
        return $this->find($id);
    }

    public function items(int $saleId): array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare("SELECT * FROM sale_items WHERE sale_id = ? AND tenant_id = ? ORDER BY id ASC");
        $stmt->execute([$saleId, $tid]);
        return $stmt->fetchAll();
    }

    /** Line items for many sales at once, keyed by sale_id — one query
     *  instead of one per row, for the sales list "Products" column. */
    public function itemsForMany(array $saleIds): array
    {
        $saleIds = array_values(array_unique(array_map('intval', $saleIds)));
        if (!$saleIds) { return []; }
        $tid = \TenantContext::tenantId();
        $in = implode(',', array_fill(0, count($saleIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT si.id, si.sale_id, si.product_name, si.quantity, si.line_total,
                    si.product_id, p.quantity AS stock_left
               FROM sale_items si
          LEFT JOIN products p ON p.id = si.product_id AND p.tenant_id = si.tenant_id
              WHERE si.tenant_id = ? AND si.sale_id IN ($in) ORDER BY si.id ASC"
        );
        $stmt->execute(array_merge([$tid], $saleIds));
        $rows = $stmt->fetchAll();
        $returns = (new ReturnModel($this->db))->returnsForItems('sale', array_column($rows, 'id'));
        $out = [];
        foreach ($rows as $r) {
            $ret = $returns[(int) $r['id']] ?? ['returned' => 0.0, 'used' => 0.0];
            $out[(int) $r['sale_id']][] = [
                'name' => $r['product_name'],
                'qty' => (float) $r['quantity'],
                'total' => (float) $r['line_total'],
                'returned' => (float) $ret['returned'],
                'used' => (float) $ret['used'],
                'stock_left' => $r['stock_left'] !== null ? (float) $r['stock_left'] : null,
            ];
        }
        return $out;
    }

    /** Admin delete/void for a recorded direct sale. Restores unsold-returned stock and removes it from reports. */
    public function deleteSale(int $saleId, int $staffId): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) { return ['ok' => false, 'error' => 'No shop in context.']; }
        $db = $this->db;
        try {
            $db->beginTransaction();
            $st = $db->prepare("SELECT id, status FROM sales WHERE id = ? AND tenant_id = ? FOR UPDATE");
            $st->execute([$saleId, $tid]);
            $sale = $st->fetch();
            if (!$sale) {
                $db->rollBack();
                return ['ok' => false, 'error' => 'Sale not found.'];
            }
            if (($sale['status'] ?? '') === 'voided') {
                $db->rollBack();
                return ['ok' => false, 'error' => 'This sale is already deleted.'];
            }

            $items = $db->prepare(
                "SELECT si.id, si.product_id, si.quantity, COALESCE(ret.returned_quantity,0) AS returned_quantity
                   FROM sale_items si
              LEFT JOIN (
                        SELECT tenant_id, source_item_id, SUM(returned_quantity) AS returned_quantity
                          FROM product_returns
                         WHERE source_type = 'sale'
                      GROUP BY tenant_id, source_item_id
                   ) ret ON ret.tenant_id = si.tenant_id AND ret.source_item_id = si.id
                  WHERE si.sale_id = ? AND si.tenant_id = ?"
            );
            $items->execute([$saleId, $tid]);
            $restore = $db->prepare('UPDATE products SET quantity = quantity + ? WHERE id = ? AND tenant_id = ?');
            foreach ($items->fetchAll() as $it) {
                $qty = max(0, round((float) $it['quantity'] - (float) $it['returned_quantity'], 2));
                if ($qty > 0 && !empty($it['product_id'])) {
                    $restore->execute([$qty, (int) $it['product_id'], $tid]);
                }
            }

            $db->prepare(
                "UPDATE sales
                    SET status = 'voided', payment_status = 'deleted', amount_paid = 0, amount_due = 0,
                        cash_amount = NULL, mpesa_amount = NULL
                  WHERE id = ? AND tenant_id = ?"
            )->execute([$saleId, $tid]);

            $db->commit();
            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            error_log('SaleModel::deleteSale failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not delete this sale.'];
        }
    }

    /** This customer's completed sales (any status but voided), newest
     *  first — matched on customer_name, trimmed and case-insensitive since
     *  it's free text typed at checkout, not a real customer record. */
    public function forCustomer(string $name, int $limit = 200): array
    {
        $name = trim($name);
        if ($name === '') { return []; }
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            "SELECT s.*, u.username AS staff_name
               FROM sales s
          LEFT JOIN users u ON u.id = s.staff_id
              WHERE s.tenant_id = ? AND s.status <> 'voided' AND LOWER(TRIM(s.customer_name)) = LOWER(?)
           ORDER BY s.created_at DESC, s.id DESC
              LIMIT " . (int) $limit
        );
        $stmt->execute([$tid, $name]);
        return $stmt->fetchAll();
    }

    /** Compact "2× Pen, 1× Book +3 more" summary for a sale/order's line
     *  items, for the sales list "Products" column. Full list is in the
     *  title tooltip. Shared by both the staff and owner Sales pages. */
    public static function itemsSummaryHtml(array $items, int $max = 2): string
    {
        if (!$items) { return '<span class="text-muted">—</span>'; }
        $fmtQty = fn($q) => rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
        $parts = array_map(function ($i) use ($fmtQty) {
            $label = $fmtQty($i['qty']) . '× ' . $i['name'];
            if ((float) ($i['returned'] ?? 0) > 0) {
                $label .= ' (returned ' . $fmtQty($i['returned']);
                if ((float) ($i['used'] ?? 0) > 0) {
                    $label .= ', used ' . $fmtQty($i['used']);
                }
                $label .= ')';
            }
            if (array_key_exists('stock_left', $i) && $i['stock_left'] !== null) {
                $label .= ' - left ' . $fmtQty($i['stock_left']);
            }
            return $label;
        }, $items);
        $shown = array_slice($parts, 0, $max);
        $extra = count($parts) - count($shown);
        $html = htmlspecialchars(implode(', ', $shown));
        if ($extra > 0) { $html .= ' <span class="text-muted">+' . $extra . ' more</span>'; }
        return '<span title="' . htmlspecialchars(implode(', ', $parts)) . '">' . $html . '</span>';
    }

    private function ensureSchema(): void
    {
        $this->ensureTable('sales', "
            CREATE TABLE IF NOT EXISTS sales (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                staff_id INT NOT NULL,
                sale_type VARCHAR(20) NULL,
                receipt_number VARCHAR(32) NOT NULL,
                payment_method VARCHAR(20) NOT NULL,
                payment_status VARCHAR(20) NULL,
                total DECIMAL(12,2) NOT NULL DEFAULT 0,
                subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
                discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
                amount_due DECIMAL(12,2) NOT NULL DEFAULT 0,
                amount_given DECIMAL(12,2) NULL,
                change_given DECIMAL(12,2) NULL,
                cash_amount DECIMAL(12,2) NULL,
                mpesa_amount DECIMAL(12,2) NULL,
                customer_name VARCHAR(120) NULL,
                customer_phone VARCHAR(30) NULL,
                customer_email VARCHAR(255) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'completed',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_sale_receipt (tenant_id, receipt_number),
                KEY idx_sale_tenant (tenant_id),
                KEY idx_sale_staff (staff_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->ensureTable('sale_items', "
            CREATE TABLE IF NOT EXISTS sale_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                sale_id INT NOT NULL,
                product_id INT NULL,
                product_name VARCHAR(160) NOT NULL,
                unit VARCHAR(20) NOT NULL DEFAULT 'piece',
                unit_price DECIMAL(12,2) NOT NULL,
                price_type VARCHAR(20) NULL,
                quantity DECIMAL(12,2) NOT NULL,
                line_total DECIMAL(12,2) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_item_sale (sale_id),
                KEY idx_item_tenant (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->ensureTable('sale_payments', "
            CREATE TABLE IF NOT EXISTS sale_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                sale_id INT NOT NULL,
                staff_id INT NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                method VARCHAR(20) NOT NULL,
                note VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_payment_sale (sale_id),
                KEY idx_payment_tenant (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->ensureColumn('sales', 'sale_type', "ALTER TABLE sales ADD COLUMN sale_type VARCHAR(20) NULL AFTER staff_id");
        $this->ensureColumn('sales', 'payment_status', "ALTER TABLE sales ADD COLUMN payment_status VARCHAR(20) NULL AFTER payment_method");
        $this->ensureColumn('sales', 'subtotal', "ALTER TABLE sales ADD COLUMN subtotal DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total");
        $this->ensureColumn('sales', 'discount_amount', "ALTER TABLE sales ADD COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER subtotal");
        $this->ensureColumn('sales', 'amount_paid', "ALTER TABLE sales ADD COLUMN amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER discount_amount");
        $this->ensureColumn('sales', 'amount_due', "ALTER TABLE sales ADD COLUMN amount_due DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER amount_paid");
        $this->ensureColumn('sales', 'cash_amount', "ALTER TABLE sales ADD COLUMN cash_amount DECIMAL(12,2) NULL AFTER change_given");
        $this->ensureColumn('sales', 'mpesa_amount', "ALTER TABLE sales ADD COLUMN mpesa_amount DECIMAL(12,2) NULL AFTER cash_amount");
        $this->ensureColumn('sale_items', 'price_type', "ALTER TABLE sale_items ADD COLUMN price_type VARCHAR(20) NULL AFTER unit_price");
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
        if ($this->columnExists($table, $column)) {
            return;
        }
        try { $this->db->exec($sql); } catch (\PDOException $ignored) {}
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

    public function forStaff(int $staffId, int $limit = 500, ?string $date = null): array
    {
        $tid = \TenantContext::tenantId();
        $dateSql = $date ? "AND DATE(s.created_at) = '" . preg_replace('/[^0-9-]/', '', $date) . "'" : '';
        $stmt = $this->db->prepare(
            "SELECT s.*, (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS item_count
               FROM sales s
              WHERE s.tenant_id = ? AND s.staff_id = ? AND s.status <> 'voided' {$dateSql}
           ORDER BY s.created_at DESC, s.id DESC
              LIMIT ?"
        );
        $stmt->bindValue(1, $tid, \PDO::PARAM_INT);
        $stmt->bindValue(2, $staffId, \PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function forTenant(int $limit = 1000, string $period = 'all'): array
    {
        $tid = \TenantContext::tenantId();
        $periodSql = match ($period) {
            'today' => "AND DATE(s.created_at) = CURDATE()",
            'week'  => "AND s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'month' => "AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            default => '',
        };
        $stmt = $this->db->prepare(
            "SELECT s.*, u.username AS staff_name,
                    (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS item_count
               FROM sales s
          LEFT JOIN users u ON u.id = s.staff_id
              WHERE s.tenant_id = ? AND s.status <> 'voided' {$periodSql}
           ORDER BY s.created_at DESC, s.id DESC
              LIMIT ?"
        );
        $stmt->bindValue(1, $tid, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function forTenantId(int $tenantId, string $date): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, u.username AS staff_name
               FROM sales s
          LEFT JOIN users u ON u.id = s.staff_id
              WHERE s.tenant_id = ? AND s.status <> 'voided' AND DATE(s.created_at) = ?
           ORDER BY s.created_at ASC"
        );
        $stmt->execute([$tenantId, $date]);
        return $stmt->fetchAll();
    }

    /**
     * Column names present on the products table — used for cost-column
     * auto-detection and for the diagnostic list in the profit notice.
     * Names come from the DB schema itself, so they're safe to interpolate.
     */
    public function productColumns(): array
    {
        $stmt = $this->db->query('SHOW COLUMNS FROM products');
        return $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

    /** Caller's preferred cost column if it exists, else first known match, else null. */
    private function resolveCostColumn(?string $preferred): ?string
    {
        $cols = $this->productColumns();
        if ($preferred !== null && in_array($preferred, $cols, true)) {
            return $preferred;
        }
        foreach (['buying_price', 'cost_price', 'cost', 'purchase_price', 'buy_price', 'unit_cost'] as $cand) {
            if (in_array($cand, $cols, true)) { return $cand; }
        }
        return null;
    }

    /**
     * Per-product revenue, cost and profit for a period (owner reporting).
     *
     * Cost is read from the CURRENT products cost column (auto-detected unless
     * you pass one). It is NOT the cost at time of sale — changing a product's
     * cost later shifts its historical profit, and deleted products show 0 cost.
     * Revenue is SUM(line_total), i.e. BEFORE order-level discounts. For
     * accurate history, snapshot unit_cost onto sale_items in record().
     *
     * Throws \RuntimeException('NO_COST_COLUMN') when products has no cost column.
     *
     * @param string      $period  today|week|month|all
     * @param string|null $costCol preferred cost column, or null to auto-detect
     * @return array<int,array{product_id:int,product_name:string,unit:?string,qty:float,revenue:float,cost:float,profit:float,margin:float}>
     */
    public function productProfit(string $period = 'all', ?string $costCol = null): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) { return []; }

        $costCol = $this->resolveCostColumn($costCol);
        if ($costCol === null) {
            throw new \RuntimeException('NO_COST_COLUMN');
        }

        $periodSql = match ($period) {
            'today' => "AND DATE(s.created_at) = CURDATE()",
            'week'  => "AND s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'month' => "AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            default => '',
        };

        $sql = "SELECT si.product_id,
                       MAX(si.product_name) AS product_name,
                       MAX(si.unit) AS unit,
                       SUM(GREATEST(si.quantity - COALESCE(ret.returned_quantity,0), 0)) AS qty,
                       SUM(si.line_total) AS revenue,
                       SUM(GREATEST(si.quantity - COALESCE(ret.returned_quantity,0), 0) * COALESCE(p.`{$costCol}`, 0)) AS cost
                  FROM sale_items si
                  JOIN sales s     ON s.id = si.sale_id AND s.tenant_id = si.tenant_id
             LEFT JOIN products p  ON p.id = si.product_id AND p.tenant_id = si.tenant_id
             LEFT JOIN (
                    SELECT tenant_id, source_item_id, SUM(returned_quantity) AS returned_quantity
                      FROM product_returns
                     WHERE source_type = 'sale'
                  GROUP BY tenant_id, source_item_id
             ) ret ON ret.tenant_id = si.tenant_id AND ret.source_item_id = si.id
                 WHERE si.tenant_id = ? AND s.status <> 'voided' {$periodSql}
              GROUP BY si.product_id
              ORDER BY (SUM(si.line_total) - SUM(GREATEST(si.quantity - COALESCE(ret.returned_quantity,0), 0) * COALESCE(p.`{$costCol}`, 0))) DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tid]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $rev  = round((float) $r['revenue'], 2);
            $cost = round((float) $r['cost'], 2);
            $r['product_id'] = (int) $r['product_id'];
            $r['qty']        = (float) $r['qty'];
            $r['revenue']    = $rev;
            $r['cost']       = $cost;
            $r['profit']     = round($rev - $cost, 2);
            $r['margin']     = $rev > 0 ? round(($rev - $cost) / $rev * 100, 1) : 0.0;
        }
        unset($r);

        return $rows;
    }

    public static function staffBreakdown(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $name = $r['staff_name'] ?? 'Unknown';
            if (!isset($out[$name])) { $out[$name] = ['count' => 0, 'revenue' => 0.0]; }
            $out[$name]['count']++;
            $out[$name]['revenue'] += (float) $r['total'];
        }
        uasort($out, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
        return $out;
    }

    /** Totals for a set of sales rows (revenue, count, cash/mpesa collected, credit owed). */
    public static function summarize(array $rows): array
    {
        $sum = [
            'count' => 0, 'revenue' => 0.0, 'collected' => 0.0, 'cash' => 0.0, 'mpesa' => 0.0,
            'card' => 0.0, 'bank' => 0.0, 'sacco' => 0.0,
            'retail' => 0, 'wholesale' => 0, 'discount' => 0.0,
            'credit_due' => 0.0, 'credit_count' => 0,
        ];
        foreach ($rows as $r) {
            $sum['count']++;
            $sum['revenue']  += (float) $r['total'];
            $sum['collected'] += (float) ($r['amount_paid'] ?? $r['total']);
            $sum['discount'] += (float) ($r['discount_amount'] ?? 0);
            $sum['credit_due'] += (float) ($r['amount_due'] ?? 0);
            if (($r['payment_status'] ?? 'paid') !== 'paid') { $sum['credit_count']++; }

            $stype = $r['sale_type'] ?? 'retail';
            $sum[$stype] = ($sum[$stype] ?? 0) + 1;

            $method = $r['payment_method'] ?? 'cash';
            // For split/credit, use the actual cash/mpesa columns (deposit routed there).
            if ($method === 'split' || $method === 'credit') {
                $sum['cash']  += (float) ($r['cash_amount'] ?? 0);
                $sum['mpesa'] += (float) ($r['mpesa_amount'] ?? 0);
            } elseif ($method === 'cash') {
                $sum['cash']  += (float) $r['total'];
            } elseif ($method === 'mpesa') {
                $sum['mpesa'] += (float) $r['total'];
            } elseif (isset($sum[$method])) {
                $sum[$method] += (float) $r['total'];
            }
        }
        foreach (['revenue','collected','cash','mpesa','card','bank','sacco','discount','credit_due'] as $k) {
            $sum[$k] = round($sum[$k], 2);
        }
        return $sum;
    }

    /** Human-readable payment summary for a sale row. */
    public static function paymentLabel(array $sale): string
    {
        $status = $sale['payment_status'] ?? 'paid';
        if (($sale['payment_method'] ?? '') === 'credit' || $status !== 'paid') {
            $due = (float) ($sale['amount_due'] ?? 0);
            if ($status === 'part_paid') {
                return 'Part-paid · owes KES ' . number_format($due, 0);
            }
            if ($due > 0) {
                return 'Credit · owes KES ' . number_format($due, 0);
            }
        }
        $method = $sale['payment_method'] ?? 'cash';
        if ($method === 'split') {
            $parts = [];
            if ((float) ($sale['cash_amount'] ?? 0) > 0) { $parts[] = 'Cash KES ' . number_format((float) $sale['cash_amount'], 0); }
            if ((float) ($sale['mpesa_amount'] ?? 0) > 0) { $parts[] = 'M-Pesa KES ' . number_format((float) $sale['mpesa_amount'], 0); }
            return $parts ? implode(' + ', $parts) : 'Split';
        }
        return \PaymentOptions::label($sale);
    }

    /** Badge HTML for sale type. */
    public static function saleTypeBadge(array $sale): string
    {
        $t = $sale['sale_type'] ?? 'retail';
        if ($t === 'wholesale') {
            return '<span class="badge bg-info text-dark">Wholesale</span>';
        }
        return '<span class="badge bg-primary">Retail</span>';
    }

    /** Badge HTML for payment status (credit-aware). */
    public static function paymentStatusBadge(array $sale): string
    {
        $status = $sale['payment_status'] ?? 'paid';
        if ($status === 'credit')    { return '<span class="badge bg-danger">Credit</span>'; }
        if ($status === 'part_paid') { return '<span class="badge bg-warning text-dark">Part-paid</span>'; }
        return '<span class="badge bg-success">Paid</span>';
    }
}
