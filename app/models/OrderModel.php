<?php
// app/models/OrderModel.php
// Bar tabs: a server opens a tab for a table/customer, adds drinks over one
// or more rounds, and someone with payment permission settles it later. This
// is now the only way staff record a sale (the old direct-sale flow was
// removed), so paid orders are "the sales" for owner reporting — see
// forTenant()/productProfit() below, which shape rows to match SaleModel's
// so the owner's Sales page can merge both sources. Legacy `sales` rows stay
// visible for history. Mirrors SaleModel's transactional style since
// multi-row atomic writes don't fit the plain base-Model CRUD helpers.
namespace Models;

class OrderModel extends Model
{
    protected string $table = 'orders';

    /**
     * Open a new order.
     * @param array $in table_name, opened_by, items[{product_id,quantity}],
     *                  channel: 'walkin' (Home — checkout pays immediately, no
     *                  invoice) or 'tab' (Orders — starts unpaid, this IS the
     *                  invoice). Defaults to 'tab' for backward compatibility.
     *                  Optional: discount_amount (negotiated off the subtotal,
     *                  clamped so the total never goes below zero),
     *                  customer_email / customer_phone (for emailing an
     *                  invoice/delivery note on a credit sale later).
     */
    public function open(array $in): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            return ['ok' => false, 'errors' => ['_' => 'No shop in context.']];
        }

        $tableName = trim($in['table_name'] ?? '');
        if ($tableName === '') {
            return ['ok' => false, 'errors' => ['table_name' => 'Enter a table or customer name.']];
        }
        $openedBy = (int) ($in['opened_by'] ?? 0);
        if ($openedBy <= 0) {
            return ['ok' => false, 'errors' => ['_' => 'No staff in context.']];
        }
        $channelIn = $in['channel'] ?? 'tab';
        $channel   = in_array($channelIn, ['walkin', 'tab'], true) ? $channelIn : 'tab';
        $items = array_values(array_filter($in['items'] ?? [], fn($i) => (int) ($i['product_id'] ?? 0) > 0 && (float) ($i['quantity'] ?? 0) > 0));
        if (!$items) {
            return ['ok' => false, 'errors' => ['_' => 'Add at least one item.']];
        }

        $db = $this->db;
        try {
            $db->beginTransaction();

            $ins = $db->prepare(
                "INSERT INTO orders (tenant_id, table_name, channel, opened_by, receipt_number, status, subtotal, total)
                 VALUES (?,?,?,?,'PENDING','open',0,0)"
            );
            $ins->execute([$tid, $tableName, $channel, $openedBy]);
            $orderId = (int) $db->lastInsertId();
            $prefix  = $channel === 'walkin' ? 'RCP-' : 'ORD-';
            $receipt = $prefix . str_pad((string) $orderId, 6, '0', STR_PAD_LEFT);
            $db->prepare('UPDATE orders SET receipt_number = ? WHERE id = ?')->execute([$receipt, $orderId]);

            $added = $this->insertItems($db, $tid, $orderId, $items, $openedBy);
            if (!$added['ok']) {
                $db->rollBack();
                return $added;
            }

            $discountIn = round((float) ($in['discount_amount'] ?? 0), 2);
            $email = trim((string) ($in['customer_email'] ?? ''));
            $phone = trim((string) ($in['customer_phone'] ?? ''));
            if ($discountIn > 0 || $email !== '' || $phone !== '') {
                $sub = $db->prepare('SELECT subtotal FROM orders WHERE id = ?');
                $sub->execute([$orderId]);
                $subtotal = (float) $sub->fetchColumn();
                $discount = min(max($discountIn, 0), $subtotal); // never negative, never more than the subtotal
                $newTotal = round($subtotal - $discount, 2);
                $db->prepare('UPDATE orders SET discount_amount = ?, total = ?, customer_email = ?, customer_phone = ? WHERE id = ?')
                    ->execute([$discount, $newTotal, $email !== '' ? $email : null, $phone !== '' ? $phone : null, $orderId]);
            }

            $db->commit();
            return ['ok' => true, 'order_id' => $orderId, 'receipt_number' => $receipt, 'errors' => []];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            error_log('OrderModel::open failed: ' . $e->getMessage());
            return ['ok' => false, 'errors' => ['_' => 'Could not open this tab. Please try again.']];
        }
    }

    /** Add another round of drinks to an OPEN tab. */
    public function addItems(int $orderId, array $items, int $staffId): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            return ['ok' => false, 'errors' => ['_' => 'No shop in context.']];
        }
        $items = array_values(array_filter($items, fn($i) => (int) ($i['product_id'] ?? 0) > 0 && (float) ($i['quantity'] ?? 0) > 0));
        if (!$items) {
            return ['ok' => false, 'errors' => ['_' => 'Add at least one drink.']];
        }

        $db = $this->db;
        try {
            $db->beginTransaction();

            $sel = $db->prepare("SELECT id, status FROM orders WHERE id = ? AND tenant_id = ? FOR UPDATE");
            $sel->execute([$orderId, $tid]);
            $order = $sel->fetch();
            if (!$order) {
                $db->rollBack();
                return ['ok' => false, 'errors' => ['_' => 'Tab not found.']];
            }
            if ($order['status'] !== 'open') {
                $db->rollBack();
                return ['ok' => false, 'errors' => ['_' => 'This tab is no longer open.']];
            }

            $added = $this->insertItems($db, $tid, $orderId, $items, $staffId);
            if (!$added['ok']) {
                $db->rollBack();
                return $added;
            }

            $db->commit();
            return ['ok' => true, 'errors' => []];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            error_log('OrderModel::addItems failed: ' . $e->getMessage());
            return ['ok' => false, 'errors' => ['_' => 'Could not add those drinks. Please try again.']];
        }
    }

    /** Shared item-insert + stock-decrement + total-recalc, used by open() and addItems(). */
    private function insertItems(\PDO $db, int $tid, int $orderId, array $items, int $staffId): array
    {
        $selSql = "SELECT id, name, selling_price, retail_price, quantity, unit
                     FROM products WHERE id = ? AND tenant_id = ? AND status IN ('active','archived') FOR UPDATE";
        try {
            $sel = $db->prepare($selSql);
        } catch (\PDOException $e) {
            $sel = $db->prepare("SELECT id, name, selling_price, retail_price, quantity, unit FROM products WHERE id = ? AND tenant_id = ? AND status IN ('active','archived') FOR UPDATE");
        }
        $insItem = $db->prepare(
            'INSERT INTO order_items (tenant_id, order_id, product_id, product_name, unit_price, quantity, line_total, added_by)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $dec = $db->prepare('UPDATE products SET quantity = quantity - ? WHERE id = ? AND tenant_id = ? AND quantity >= ?');

        foreach ($items as $it) {
            $pid = (int) $it['product_id'];
            $qty = (float) $it['quantity'];
            $sel->execute([$pid, $tid]);
            $p = $sel->fetch();
            if (!$p) {
                return ['ok' => false, 'errors' => ['_' => 'One of the drinks is no longer available. Refresh and try again.']];
            }
            if ($qty > (float) $p['quantity']) {
                return ['ok' => false, 'errors' => ['_' => "Not enough stock for {$p['name']} — only " . rtrim(rtrim(number_format((float) $p['quantity'], 2), '0'), '.') . ' left.']];
            }
            // Offer-aware: charges the live offer price when one is running,
            // the regular price otherwise — same rule everywhere (ProductModel::effectivePrice).
            $offerRow = $p;
            if (!array_key_exists('offer_price', $offerRow)) {
                $offerRow['offer_price'] = null;
                $offerRow['offer_starts_at'] = null;
                $offerRow['offer_ends_at'] = null;
            }
            $unitPrice = ProductModel::effectivePrice($offerRow)['price'];
            if ($unitPrice <= 0) { $unitPrice = (float) ($p['retail_price'] ?: $p['selling_price']); }
            $lineTotal = round($unitPrice * $qty, 2);

            $insItem->execute([$tid, $orderId, $pid, $p['name'], $unitPrice, $qty, $lineTotal, $staffId]);
            $dec->execute([$qty, $pid, $tid, $qty]);
            if ($dec->rowCount() !== 1) {
                return ['ok' => false, 'errors' => ['_' => "Stock changed for {$p['name']} while saving. Please try again."]];
            }
        }

        $sum = $db->prepare('SELECT COALESCE(SUM(line_total),0) FROM order_items WHERE order_id = ? AND tenant_id = ?');
        $sum->execute([$orderId, $tid]);
        $total = round((float) $sum->fetchColumn(), 2);
        $db->prepare('UPDATE orders SET subtotal = ?, total = ? WHERE id = ?')->execute([$total, $total, $orderId]);

        return ['ok' => true, 'errors' => []];
    }

    /** Settle a tab in full. $payment: method (cash/mpesa/split), cash_amount, mpesa_amount. */
    public function markPaid(int $orderId, array $payment, int $staffId): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            return ['ok' => false, 'error' => 'No shop in context.'];
        }
        $method = in_array($payment['method'] ?? '', ['cash', 'mpesa', 'split'], true) ? $payment['method'] : null;
        if (!$method) {
            return ['ok' => false, 'error' => 'Choose how the customer paid.'];
        }

        $db = $this->db;
        try {
            $db->beginTransaction();
            $sel = $db->prepare('SELECT id, status, total FROM orders WHERE id = ? AND tenant_id = ? FOR UPDATE');
            $sel->execute([$orderId, $tid]);
            $order = $sel->fetch();
            if (!$order) { $db->rollBack(); return ['ok' => false, 'error' => 'Tab not found.']; }
            if ($order['status'] !== 'open') { $db->rollBack(); return ['ok' => false, 'error' => 'This tab is not open.']; }

            $total = (float) $order['total'];
            $cash  = 0.0;
            $mpesa = 0.0;
            $tendered = null;
            $change = null;
            if ($method === 'cash') {
                $cash = $total;
                $tendered = max(0, round((float) ($payment['amount_tendered'] ?? 0), 2));
                if ($tendered + 0.0001 < $cash) {
                    $db->rollBack();
                    return ['ok' => false, 'error' => 'Cash given is less than the total (KES ' . number_format($cash, 0) . ').'];
                }
                $change = round($tendered - $cash, 2);
            } elseif ($method === 'mpesa') {
                $mpesa = $total;
            } else { // split
                $cash  = max(0, round((float) ($payment['cash_amount'] ?? 0), 2));
                $mpesa = max(0, round((float) ($payment['mpesa_amount'] ?? 0), 2));
                if (abs(($cash + $mpesa) - $total) > 0.01) {
                    $db->rollBack();
                    return ['ok' => false, 'error' => 'Cash and M-Pesa amounts must add up to the total (KES ' . number_format($total, 0) . ').'];
                }
                if ($cash > 0) {
                    $tendered = max(0, round((float) ($payment['amount_tendered'] ?? 0), 2));
                    if ($tendered + 0.0001 < $cash) {
                        $db->rollBack();
                        return ['ok' => false, 'error' => 'Cash given is less than the cash portion (KES ' . number_format($cash, 0) . ').'];
                    }
                    $change = round($tendered - $cash, 2);
                }
            }

            $db->prepare(
                'UPDATE orders SET status = ?, payment_method = ?, cash_amount = ?, mpesa_amount = ?, amount_tendered = ?, change_due = ?, paid_by = ?, paid_at = NOW() WHERE id = ?'
            )->execute(['paid', $method, $cash > 0 ? $cash : null, $mpesa > 0 ? $mpesa : null, $tendered, $change, $staffId, $orderId]);

            $db->commit();
            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            error_log('OrderModel::markPaid failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not record the payment. Please try again.'];
        }
    }

    /** Cancel an open tab, restoring its stock. */
    public function void(int $orderId, int $staffId): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            return ['ok' => false, 'error' => 'No shop in context.'];
        }
        $db = $this->db;
        try {
            $db->beginTransaction();
            $sel = $db->prepare('SELECT id, status FROM orders WHERE id = ? AND tenant_id = ? FOR UPDATE');
            $sel->execute([$orderId, $tid]);
            $order = $sel->fetch();
            if (!$order) { $db->rollBack(); return ['ok' => false, 'error' => 'Tab not found.']; }
            if ($order['status'] !== 'open') { $db->rollBack(); return ['ok' => false, 'error' => 'Only an open tab can be voided.']; }

            $items = $db->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? AND tenant_id = ?');
            $items->execute([$orderId, $tid]);
            $restore = $db->prepare('UPDATE products SET quantity = quantity + ? WHERE id = ? AND tenant_id = ?');
            foreach ($items->fetchAll() as $it) {
                if ($it['product_id']) {
                    $restore->execute([$it['quantity'], $it['product_id'], $tid]);
                }
            }

            $db->prepare("UPDATE orders SET status = 'void', paid_by = ?, paid_at = NOW() WHERE id = ?")->execute([$staffId, $orderId]);
            $db->commit();
            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            error_log('OrderModel::void failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not void this tab. Please try again.'];
        }
    }

    /** Find an order by its printed invoice/receipt number (e.g. ORD-000123). */
    public function findByReceipt(string $receiptNumber): ?array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            "SELECT o.*, u.username AS opened_by_name
               FROM orders o
          LEFT JOIN users u ON u.id = o.opened_by
              WHERE o.tenant_id = ? AND o.receipt_number = ? LIMIT 1"
        );
        $stmt->execute([$tid, strtoupper(trim($receiptNumber))]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** All open tabs for the tenant, newest first. */
    public function openOrders(): array
    {
        $tid = \TenantContext::tenantId();
        $sql = "SELECT o.*, u.username AS opened_by_name,
                       (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
                  FROM orders o
             LEFT JOIN users u ON u.id = o.opened_by
                 WHERE o.tenant_id = ? AND o.status = 'open'
              ORDER BY o.created_at ASC, o.id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tid]);
        return $stmt->fetchAll();
    }

    public function items(int $orderId): array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare('SELECT * FROM order_items WHERE order_id = ? AND tenant_id = ? ORDER BY id ASC');
        $stmt->execute([$orderId, $tid]);
        return $stmt->fetchAll();
    }

    /** Line items for many orders at once, keyed by order_id — one query
     *  instead of one per row, for the sales list "Products" column. */
    public function itemsForMany(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_map('intval', $orderIds)));
        if (!$orderIds) { return []; }
        $tid = \TenantContext::tenantId();
        $in = implode(',', array_fill(0, count($orderIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT order_id, product_name, quantity, line_total FROM order_items
              WHERE tenant_id = ? AND order_id IN ($in) ORDER BY id ASC"
        );
        $stmt->execute(array_merge([$tid], $orderIds));
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[(int) $r['order_id']][] = ['name' => $r['product_name'], 'qty' => (float) $r['quantity'], 'total' => (float) $r['line_total']];
        }
        return $out;
    }

    /** This customer's tabs (any status but void), newest first — matched on
     *  table_name, trimmed and case-insensitive since it's free text typed
     *  at checkout, not a real customer record. */
    public function forCustomer(string $name, int $limit = 200): array
    {
        $name = trim($name);
        if ($name === '') { return []; }
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            "SELECT o.*, u.username AS staff_name
               FROM orders o
          LEFT JOIN users u ON u.id = o.opened_by
              WHERE o.tenant_id = ? AND o.status <> 'void' AND LOWER(TRIM(o.table_name)) = LOWER(?)
           ORDER BY o.created_at DESC, o.id DESC
              LIMIT " . (int) $limit
        );
        $stmt->execute([$tid, $name]);
        return $stmt->fetchAll();
    }

    /** Add/update a customer's contact info on an existing tab (e.g. before emailing them). */
    public function updateCustomerContact(int $orderId, ?string $email, ?string $phone): array
    {
        $tid = \TenantContext::tenantId();
        $email = $email !== null ? trim($email) : null;
        $phone = $phone !== null ? trim($phone) : null;
        if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Enter a valid email address.'];
        }
        $stmt = $this->db->prepare('UPDATE orders SET customer_email = ?, customer_phone = ? WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$email !== '' ? $email : null, $phone !== '' ? $phone : null, $orderId, $tid]);
        return ['ok' => true, 'error' => null];
    }

    public function markInvoiceSent(int $orderId): void
    {
        $tid = \TenantContext::tenantId();
        $this->db->prepare('UPDATE orders SET invoice_sent_at = NOW() WHERE id = ? AND tenant_id = ?')->execute([$orderId, $tid]);
    }

    public function markDeliveryNoteSent(int $orderId): void
    {
        $tid = \TenantContext::tenantId();
        $this->db->prepare('UPDATE orders SET delivery_note_sent_at = NOW() WHERE id = ? AND tenant_id = ?')->execute([$orderId, $tid]);
    }

    // ===== owner reporting: paid orders, shaped like SaleModel's rows ======

    /**
     * Paid tabs for a period, shaped with the same keys SaleModel::forTenant()
     * rows have (total, payment_method, cash_amount, mpesa_amount,
     * staff_name, sale_type, payment_status, discount_amount,
     * amount_paid, amount_due) so the owner's Sales page can merge the two
     * lists and reuse SaleModel's summarize()/staffBreakdown().
     * Filtered by paid_at (when the money actually came in), not created_at
     * (when the tab was opened) — a late-night tab shouldn't count against
     * the wrong day.
     */
    public function forTenant(int $limit = 1000, string $period = 'all', ?int $staffId = null): array
    {
        $tid = \TenantContext::tenantId();
        $periodSql = match ($period) {
            'today' => "AND DATE(o.paid_at) = CURDATE()",
            'week'  => "AND o.paid_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'month' => "AND o.paid_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            default => '',
        };
        $staffSql = $staffId !== null ? "AND o.opened_by = :staff_id" : '';
        $stmt = $this->db->prepare(
            "SELECT o.*, u.username AS staff_name,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
               FROM orders o
          LEFT JOIN users u ON u.id = o.opened_by
              WHERE o.tenant_id = :tid AND o.status = 'paid' {$periodSql} {$staffSql}
           ORDER BY o.paid_at DESC, o.id DESC
              LIMIT :lim"
        );
        $stmt->bindValue(':tid', $tid, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        if ($staffId !== null) { $stmt->bindValue(':staff_id', $staffId, \PDO::PARAM_INT); }
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['created_at']       = $r['paid_at'];       // reporting date = when paid
            $r['customer_name']    = $r['table_name'];
            $r['sale_type']        = 'retail';             // tabs don't distinguish wholesale/retail
            $r['payment_status']   = 'paid';               // only paid tabs are reported as sales
            $r['discount_amount']  = 0;
            $r['amount_due']       = 0;
            $r['amount_paid']      = $r['total'];
            $r['receipt_url']      = 'staff/orders/receipt.php?id=' . (int) $r['id'];
            $r['source']           = 'order';
        }
        unset($r);

        return $rows;
    }

    /**
     * Paid orders for one tenant on one exact Y-m-d date — CLI-safe (explicit
     * tenant id, no TenantContext), mirrors SaleModel::forTenantId(). Used by
     * the daily sales report (page, PDF, email cron).
     */
    public function forTenantOnDate(int $tenantId, string $date): array
    {
        $date = preg_replace('/[^0-9-]/', '', $date);
        $stmt = $this->db->prepare(
            "SELECT o.*, u.username AS staff_name,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
               FROM orders o
          LEFT JOIN users u ON u.id = o.opened_by
              WHERE o.tenant_id = ? AND o.status = 'paid' AND DATE(o.paid_at) = ?
           ORDER BY o.paid_at ASC, o.id ASC"
        );
        $stmt->execute([$tenantId, $date]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['created_at']      = $r['paid_at'];
            $r['customer_name']   = $r['table_name'];
            $r['sale_type']       = 'retail';
            $r['payment_status']  = 'paid';
            $r['discount_amount'] = 0;
            $r['amount_due']      = 0;
            $r['amount_paid']     = $r['total'];
            $r['receipt_url']     = 'staff/orders/receipt.php?id=' . (int) $r['id'];
            $r['source']          = 'order';
        }
        unset($r);

        return $rows;
    }

    /** Per-product revenue/qty from paid tabs on one exact date — CLI-safe. */
    public function productBreakdownOnDate(int $tenantId, string $date): array
    {
        $date = preg_replace('/[^0-9-]/', '', $date);
        $stmt = $this->db->prepare(
            "SELECT oi.product_name, SUM(oi.quantity) AS qty, SUM(oi.line_total) AS revenue
               FROM order_items oi
               JOIN orders o ON o.id = oi.order_id AND o.tenant_id = oi.tenant_id
              WHERE oi.tenant_id = ? AND o.status = 'paid' AND DATE(o.paid_at) = ?
           GROUP BY oi.product_name
           ORDER BY revenue DESC"
        );
        $stmt->execute([$tenantId, $date]);
        return $stmt->fetchAll();
    }

    /** Per-product revenue/cost/profit from paid tabs — mirrors SaleModel::productProfit(). */
    public function productProfit(string $period = 'all', string $costCol = 'buying_price'): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) { return []; }

        $periodSql = match ($period) {
            'today' => "AND DATE(o.paid_at) = CURDATE()",
            'week'  => "AND o.paid_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'month' => "AND o.paid_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            default => '',
        };

        $sql = "SELECT oi.product_id,
                       MAX(oi.product_name)                           AS product_name,
                       SUM(oi.quantity)                                AS qty,
                       SUM(oi.line_total)                              AS revenue,
                       SUM(oi.quantity * COALESCE(p.`{$costCol}`, 0))  AS cost
                  FROM order_items oi
                  JOIN orders o    ON o.id = oi.order_id AND o.tenant_id = oi.tenant_id
             LEFT JOIN products p  ON p.id = oi.product_id AND p.tenant_id = oi.tenant_id
                 WHERE oi.tenant_id = ? AND o.status = 'paid' {$periodSql}
              GROUP BY oi.product_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tid]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $rev  = round((float) $r['revenue'], 2);
            $cost = round((float) $r['cost'], 2);
            $r['product_id'] = (int) $r['product_id'];
            $r['unit']       = 'piece';
            $r['qty']        = (float) $r['qty'];
            $r['revenue']    = $rev;
            $r['cost']       = $cost;
            $r['profit']     = round($rev - $cost, 2);
            $r['margin']     = $rev > 0 ? round(($rev - $cost) / $rev * 100, 1) : 0.0;
        }
        unset($r);

        return $rows;
    }
}
