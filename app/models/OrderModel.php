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

    public function __construct(?\PDO $db = null)
    {
        parent::__construct($db);
        $this->ensurePaymentSchema();
    }

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

        $openedBy = (int) ($in['opened_by'] ?? 0);
        if ($openedBy <= 0) {
            return ['ok' => false, 'errors' => ['_' => 'No staff in context.']];
        }
        $channelIn = $in['channel'] ?? 'tab';
        $channel   = in_array($channelIn, ['walkin', 'tab'], true) ? $channelIn : 'tab';
        $tableName = trim((string) ($in['table_name'] ?? ''));
        if ($tableName === '' && $channel === 'tab') {
            return ['ok' => false, 'errors' => ['table_name' => 'Enter the customer name.']];
        }
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

            $saleType = $this->summarySaleType($items, $in['sale_type'] ?? 'retail');
            $creditOverride = max(0, round((float) ($in['credit_override_amount'] ?? 0), 2));
            $added = $this->insertItems($db, $tid, $orderId, $items, $openedBy, $saleType, $channel === 'tab', $creditOverride);
            if (!$added['ok']) {
                $db->rollBack();
                return $added;
            }

            $discountIn = round((float) ($in['discount_amount'] ?? 0), 2);
            $email = trim((string) ($in['customer_email'] ?? ''));
            $phone = trim((string) ($in['customer_phone'] ?? ''));
            $customerId = (int) ($in['customer_id'] ?? 0);
            $creditDays = max(0, (int) ($in['credit_duration_days'] ?? 0));
            $creditDueAt = $creditDays > 0 ? date('Y-m-d H:i:s', strtotime('+' . $creditDays . ' days')) : null;
            $vatRate = max(0, round((float) ($in['vat_rate'] ?? 0), 2));
            $vatInclusive = array_key_exists('vat_inclusive', $in) ? (bool) $in['vat_inclusive'] : true;

            $sub = $db->prepare('SELECT subtotal FROM orders WHERE id = ?');
            $sub->execute([$orderId]);
            $subtotal = (float) $sub->fetchColumn();
            $priced = \Pricing::totals($subtotal, $discountIn, $vatRate, $vatInclusive);

            $sets = ['discount_amount = ?', 'total = ?', 'amount_due = ?', 'customer_email = ?', 'customer_phone = ?'];
            $vals = [
                $priced['discount'],
                $priced['total'],
                $priced['total'],
                $email !== '' ? $email : null,
                $phone !== '' ? $phone : null,
            ];
            if ($creditDays > 0) {
                $sets[] = 'credit_duration_days = ?';
                $sets[] = 'credit_due_at = ?';
                $vals[] = $creditDays;
                $vals[] = $creditDueAt;
            }
            try {
                $db->query('SELECT vat_amount FROM orders LIMIT 1');
                $sets[] = 'vat_rate = ?';
                $sets[] = 'vat_amount = ?';
                $sets[] = 'sale_type = ?';
                $vals[] = $priced['vat_rate'];
                $vals[] = $priced['vat_amount'];
                $vals[] = $saleType;
                if ($customerId > 0) {
                    $sets[] = 'customer_id = ?';
                    $vals[] = $customerId;
                }
            } catch (\PDOException $ignored) {}
            $vals[] = $orderId;
            $db->prepare('UPDATE orders SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);

            $db->commit();
            return ['ok' => true, 'order_id' => $orderId, 'receipt_number' => $receipt, 'errors' => []];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            error_log('OrderModel::open failed: ' . $e->getMessage());
            return ['ok' => false, 'errors' => ['_' => 'Could not open this tab. Please try again.']];
        }
    }

    /** Add another round of drinks to an OPEN tab. */
    public function addItems(int $orderId, array $items, int $staffId, float $creditOverride = 0.0): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            return ['ok' => false, 'errors' => ['_' => 'No shop in context.']];
        }
        $items = array_values(array_filter($items, fn($i) => (int) ($i['product_id'] ?? 0) > 0 && (float) ($i['quantity'] ?? 0) > 0));
        if (!$items) {
            return ['ok' => false, 'errors' => ['_' => 'Add at least one item.']];
        }

        $db = $this->db;
        try {
            $db->beginTransaction();

            $sel = $db->prepare("SELECT id, status, sale_type FROM orders WHERE id = ? AND tenant_id = ? FOR UPDATE");
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

            $saleType = ($order['sale_type'] ?? 'retail') === 'wholesale' ? 'wholesale' : 'retail';
            $added = $this->insertItems($db, $tid, $orderId, $items, $staffId, $saleType, true, max(0, round($creditOverride, 2)));
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

    public function updateInvoice(int $orderId, array $in, int $staffId): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            return ['ok' => false, 'errors' => ['_' => 'No shop in context.']];
        }
        $tableName = trim((string) ($in['table_name'] ?? ''));
        if ($tableName === '') {
            return ['ok' => false, 'errors' => ['table_name' => 'Enter the customer name.']];
        }

        $db = $this->db;
        try {
            $db->beginTransaction();

            $st = $db->prepare('SELECT * FROM orders WHERE id = ? AND tenant_id = ? FOR UPDATE');
            $st->execute([$orderId, $tid]);
            $order = $st->fetch();
            if (!$order) {
                $db->rollBack();
                return ['ok' => false, 'errors' => ['_' => 'Invoice not found.']];
            }
            if (($order['status'] ?? '') !== 'open') {
                $db->rollBack();
                return ['ok' => false, 'errors' => ['_' => 'Only unpaid/open invoices can be edited.']];
            }

            $saleType = (($in['sale_type'] ?? $order['sale_type'] ?? 'retail') === 'wholesale') ? 'wholesale' : 'retail';
            $existingRows = [];
            $itemsStmt = $db->prepare('SELECT * FROM order_items WHERE order_id = ? AND tenant_id = ? FOR UPDATE');
            $itemsStmt->execute([$orderId, $tid]);
            foreach ($itemsStmt->fetchAll() as $row) {
                $existingRows[(int) $row['id']] = $row;
            }

            $returns = (new ReturnModel($db))->returnsForItems('order', array_keys($existingRows));
            $productSel = $db->prepare(
                "SELECT id, name, selling_price, wholesale_price, retail_price, offer_price, offer_starts_at, offer_ends_at,
                        quantity, unit, units_per_pack, pack_unit, pack_price
                   FROM products
                  WHERE id = ? AND tenant_id = ? AND status IN ('active','archived') FOR UPDATE"
            );
            $stockAdd = $db->prepare('UPDATE products SET quantity = quantity + ? WHERE id = ? AND tenant_id = ?');
            $stockDec = $db->prepare('UPDATE products SET quantity = quantity - ? WHERE id = ? AND tenant_id = ? AND quantity >= ?');
            $updateItem = $db->prepare('UPDATE order_items SET quantity = ?, unit_price = ?, price_type = ?, line_total = ? WHERE id = ? AND order_id = ? AND tenant_id = ?');
            $deleteItem = $db->prepare('DELETE FROM order_items WHERE id = ? AND order_id = ? AND tenant_id = ?');
            $insertItem = $db->prepare(
                'INSERT INTO order_items (tenant_id, order_id, product_id, product_name, unit_price, price_type, quantity, line_total, added_by)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );

            $seenExisting = [];
            foreach (($in['existing_items'] ?? []) as $itemId => $row) {
                $itemId = (int) $itemId;
                if (!isset($existingRows[$itemId])) {
                    continue;
                }
                $old = $existingRows[$itemId];
                $newQty = round(max(0, (float) ($row['quantity'] ?? 0)), 2);
                $oldQty = round((float) $old['quantity'], 2);
                $returned = round((float) ($returns[$itemId]['returned'] ?? 0), 2);
                if ($newQty + 0.0001 < $returned) {
                    $db->rollBack();
                    return ['ok' => false, 'errors' => ['_' => $old['product_name'] . ' cannot be below already-returned quantity.']];
                }
                $delta = round($newQty - $oldQty, 2);
                if ($delta > 0) {
                    $stockDec->execute([$delta, (int) $old['product_id'], $tid, $delta]);
                    if ($stockDec->rowCount() !== 1) {
                        $db->rollBack();
                        return ['ok' => false, 'errors' => ['_' => 'Not enough stock to increase ' . $old['product_name'] . '.']];
                    }
                } elseif ($delta < 0) {
                    $stockAdd->execute([abs($delta), (int) $old['product_id'], $tid]);
                }
                if ($newQty <= 0.0001 && $returned <= 0.0001) {
                    $deleteItem->execute([$itemId, $orderId, $tid]);
                } else {
                    $unitPrice = (float) ($row['unit_price'] ?? $old['unit_price']);
                    if ($unitPrice < 0) { $unitPrice = (float) $old['unit_price']; }
                    $lineSaleType = (($row['price_type'] ?? $old['price_type'] ?? $saleType) === 'wholesale') ? 'wholesale' : 'retail';
                    $updateItem->execute([$newQty, $unitPrice, $lineSaleType, round($newQty * $unitPrice, 2), $itemId, $orderId, $tid]);
                }
                $seenExisting[$itemId] = true;
            }

            foreach ($existingRows as $itemId => $old) {
                if (isset($seenExisting[$itemId])) {
                    continue;
                }
                $returned = round((float) ($returns[$itemId]['returned'] ?? 0), 2);
                $restore = max(0, round((float) $old['quantity'] - $returned, 2));
                if ($restore > 0 && !empty($old['product_id'])) {
                    $stockAdd->execute([$restore, (int) $old['product_id'], $tid]);
                }
                if ($returned <= 0.0001) {
                    $deleteItem->execute([$itemId, $orderId, $tid]);
                } else {
                    $lineSaleType = (($old['price_type'] ?? $saleType) === 'wholesale') ? 'wholesale' : 'retail';
                    $updateItem->execute([$returned, (float) $old['unit_price'], $lineSaleType, round($returned * (float) $old['unit_price'], 2), $itemId, $orderId, $tid]);
                }
            }

            foreach (($in['new_items'] ?? []) as $row) {
                $pid = (int) ($row['product_id'] ?? 0);
                $qty = round(max(0, (float) ($row['quantity'] ?? 0)), 2);
                if ($pid <= 0 || $qty <= 0) {
                    continue;
                }
                $productSel->execute([$pid, $tid]);
                $p = $productSel->fetch();
                if (!$p) {
                    $db->rollBack();
                    return ['ok' => false, 'errors' => ['_' => 'One selected product is no longer available.']];
                }
                $stockDec->execute([$qty, $pid, $tid, $qty]);
                if ($stockDec->rowCount() !== 1) {
                    $db->rollBack();
                    return ['ok' => false, 'errors' => ['_' => 'Not enough stock for ' . $p['name'] . '.']];
                }
                $lineSaleType = (($row['price_type'] ?? $saleType) === 'wholesale') ? 'wholesale' : 'retail';
                $unitPrice = \Pricing::unitPriceForQty($p, $qty, $lineSaleType);
                if ($unitPrice <= 0) {
                    $unitPrice = (float) (($p['retail_price'] ?? 0) ?: ($p['selling_price'] ?? 0));
                }
                $insertItem->execute([$tid, $orderId, $pid, $p['name'], $unitPrice, $lineSaleType, $qty, round($qty * $unitPrice, 2), $staffId]);
            }

            $sum = $db->prepare('SELECT COALESCE(SUM(line_total),0) FROM order_items WHERE order_id = ? AND tenant_id = ?');
            $sum->execute([$orderId, $tid]);
            $subtotal = round((float) $sum->fetchColumn(), 2);
            if ($subtotal <= 0.0001) {
                $db->rollBack();
                return ['ok' => false, 'errors' => ['_' => 'Invoice must keep at least one product.']];
            }
            $discount = max(0, round((float) ($in['discount_amount'] ?? 0), 2));
            $priced = \Pricing::totals($subtotal, $discount, (float) ($order['vat_rate'] ?? 0), true);
            $paid = max(0, (float) ($order['amount_paid'] ?? 0));
            $paid = min($paid, (float) $priced['total']);
            $due = max(0, round((float) $priced['total'] - $paid, 2));
            $creditDays = max(0, (int) ($in['credit_duration_days'] ?? 0));
            $creditDueAt = $creditDays > 0 ? date('Y-m-d H:i:s', strtotime('+' . $creditDays . ' days', strtotime($order['created_at'] ?? 'now'))) : null;

            $db->prepare(
                'UPDATE orders
                    SET table_name = ?, sale_type = ?, subtotal = ?, discount_amount = ?, total = ?,
                        amount_paid = ?, amount_due = ?, customer_email = ?, customer_phone = ?,
                        credit_duration_days = ?, credit_due_at = ?
                  WHERE id = ? AND tenant_id = ?'
            )->execute([
                $tableName,
                $saleType,
                $subtotal,
                $priced['discount'],
                $priced['total'],
                $paid,
                $due,
                trim((string) ($in['customer_email'] ?? '')) ?: null,
                trim((string) ($in['customer_phone'] ?? '')) ?: null,
                $creditDays > 0 ? $creditDays : null,
                $creditDueAt,
                $orderId,
                $tid,
            ]);

            $db->commit();
            return ['ok' => true, 'errors' => []];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            error_log('OrderModel::updateInvoice failed: ' . $e->getMessage());
            return ['ok' => false, 'errors' => ['_' => 'Could not update this invoice.']];
        }
    }

    /** Shared item-insert + stock-decrement + total-recalc, used by open() and addItems(). */
    private function insertItems(\PDO $db, int $tid, int $orderId, array $items, int $staffId, string $saleType = 'retail', bool $enforceCreditLimit = false, float $creditOverride = 0.0): array
    {
        $selSql = "SELECT id, name, selling_price, wholesale_price, retail_price, offer_price, offer_starts_at, offer_ends_at,
                          credit_limit, quantity, unit, units_per_pack, pack_unit, pack_price
                     FROM products WHERE id = ? AND tenant_id = ? AND status IN ('active','archived') FOR UPDATE";
        try {
            $sel = $db->prepare($selSql);
        } catch (\PDOException $e) {
            $sel = $db->prepare("SELECT id, name, selling_price, retail_price, quantity, unit FROM products WHERE id = ? AND tenant_id = ? AND status IN ('active','archived') FOR UPDATE");
        }
        $insItem = $db->prepare(
            'INSERT INTO order_items (tenant_id, order_id, product_id, product_name, unit_price, price_type, quantity, line_total, added_by)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $dec = $db->prepare('UPDATE products SET quantity = quantity - ? WHERE id = ? AND tenant_id = ? AND quantity >= ?');

        foreach ($items as $it) {
            $pid = (int) $it['product_id'];
            $qty = (float) $it['quantity'];
            $sel->execute([$pid, $tid]);
            $p = $sel->fetch();
            if (!$p) {
                return ['ok' => false, 'errors' => ['_' => 'One of the products is no longer available. Refresh and try again.']];
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
            $lineSaleType = (($it['price_type'] ?? $saleType) === 'wholesale') ? 'wholesale' : 'retail';
            $unitPrice = \Pricing::unitPriceForQty($offerRow + $p, $qty, $lineSaleType);
            if ($unitPrice <= 0) { $unitPrice = (float) ($p['retail_price'] ?: $p['selling_price']); }
            $lineTotal = round($unitPrice * $qty, 2);
            if ($enforceCreditLimit && isset($p['credit_limit']) && $p['credit_limit'] !== null && $p['credit_limit'] !== '') {
                $limit = max((float) $p['credit_limit'], $creditOverride);
                if ($limit > 0 && $lineTotal > $limit + 0.0001) {
                    return ['ok' => false, 'errors' => ['_' => "{$p['name']} exceeds its product credit limit of KES " . number_format($limit, 0) . '. Reduce the quantity or enter a loyal-customer override.']];
                }
            }

            $insItem->execute([$tid, $orderId, $pid, $p['name'], $unitPrice, $lineSaleType, $qty, $lineTotal, $staffId]);
            $dec->execute([$qty, $pid, $tid, $qty]);
            if ($dec->rowCount() !== 1) {
                return ['ok' => false, 'errors' => ['_' => "Stock changed for {$p['name']} while saving. Please try again."]];
            }
        }

        $sum = $db->prepare('SELECT COALESCE(SUM(line_total),0) FROM order_items WHERE order_id = ? AND tenant_id = ?');
        $sum->execute([$orderId, $tid]);
        $total = round((float) $sum->fetchColumn(), 2);
        $paid = 0.0;
        try {
            $paidSt = $db->prepare('SELECT COALESCE(amount_paid,0) FROM orders WHERE id = ? AND tenant_id = ?');
            $paidSt->execute([$orderId, $tid]);
            $paid = (float) $paidSt->fetchColumn();
        } catch (\PDOException $ignored) {}
        $due = max(0, round($total - $paid, 2));
        try {
            $db->prepare('UPDATE orders SET subtotal = ?, total = ?, amount_due = ? WHERE id = ?')->execute([$total, $total, $due, $orderId]);
        } catch (\PDOException $e) {
            $db->prepare('UPDATE orders SET subtotal = ?, total = ? WHERE id = ?')->execute([$total, $total, $orderId]);
        }

        return ['ok' => true, 'errors' => []];
    }

    private function summarySaleType(array $items, string $fallback = 'retail'): string
    {
        $hasRetail = false;
        $hasWholesale = false;
        foreach ($items as $item) {
            if (($item['price_type'] ?? $fallback) === 'wholesale') {
                $hasWholesale = true;
            } else {
                $hasRetail = true;
            }
        }
        return $hasWholesale && !$hasRetail ? 'wholesale' : 'retail';
    }

    /** Record a payment against a credit sale. Full payment closes it; partial payment keeps it open. */
    public function markPaid(int $orderId, array $payment, int $staffId): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            return ['ok' => false, 'error' => 'No shop in context.'];
        }
        $allowed = ['cash', 'mpesa', 'split', 'card', 'bank', 'sacco', 'credit'];
        $method = in_array($payment['method'] ?? '', $allowed, true) ? $payment['method'] : null;
        if (!$method) {
            return ['ok' => false, 'error' => 'Choose how the customer paid.'];
        }

        $this->ensurePaymentSchema();
        $db = $this->db;
        try {
            $db->beginTransaction();
            $sel = $db->prepare('SELECT id, status, total, amount_paid, amount_due, customer_id FROM orders WHERE id = ? AND tenant_id = ? FOR UPDATE');
            $sel->execute([$orderId, $tid]);
            $order = $sel->fetch();
            if (!$order) { $db->rollBack(); return ['ok' => false, 'error' => 'Sale not found.']; }
            if ($order['status'] !== 'open') { $db->rollBack(); return ['ok' => false, 'error' => 'This sale is not open.']; }

            $total = (float) $order['total'];
            $paidBefore = max(0, (float) ($order['amount_paid'] ?? 0));
            $balanceDue = (float) ($order['amount_due'] ?? 0);
            if ($balanceDue <= 0.0001) {
                $balanceDue = max(0, round($total - $paidBefore, 2));
            }
            if ($balanceDue <= 0.0001) {
                $db->rollBack();
                return ['ok' => false, 'error' => 'This sale is already fully paid.'];
            }
            $cash  = 0.0;
            $mpesa = 0.0;
            $tendered = null;
            $change = null;
            if ($method === 'cash') {
                $cash = $balanceDue;
                $tendered = max(0, round((float) ($payment['amount_tendered'] ?? 0), 2));
                if ($tendered + 0.0001 < $cash) {
                    $db->rollBack();
                    return ['ok' => false, 'error' => 'Cash given is less than the total (KES ' . number_format($cash, 0) . ').'];
                }
                $change = round($tendered - $cash, 2);
            } elseif ($method === 'mpesa') {
                $mpesa = $balanceDue;
            } elseif ($method === 'split') {
                $cash  = max(0, round((float) ($payment['cash_amount'] ?? 0), 2));
                $mpesa = max(0, round((float) ($payment['mpesa_amount'] ?? 0), 2));
                if (abs(($cash + $mpesa) - $balanceDue) > 0.01) {
                    $db->rollBack();
                    return ['ok' => false, 'error' => 'Cash and M-Pesa amounts must add up to the balance due (KES ' . number_format($balanceDue, 0) . ').'];
                }
                if ($cash > 0) {
                    $tendered = max(0, round((float) ($payment['amount_tendered'] ?? 0), 2));
                    if ($tendered + 0.0001 < $cash) {
                        $db->rollBack();
                        return ['ok' => false, 'error' => 'Cash given is less than the cash portion (KES ' . number_format($cash, 0) . ').'];
                    }
                    $change = round($tendered - $cash, 2);
                }
            } elseif ($method === 'credit') {
                $actualMethod = in_array($payment['deposit_method'] ?? '', ['cash', 'mpesa', 'card', 'bank', 'sacco'], true) ? $payment['deposit_method'] : 'cash';
                $received = max(0, round((float) ($payment['amount_received'] ?? 0), 2));
                if ($received <= 0) {
                    $db->rollBack();
                    return ['ok' => false, 'error' => 'Enter the amount the customer is paying now.'];
                }
                $received = min($received, $balanceDue);
                if ($actualMethod === 'cash') {
                    $cash = $received;
                    $tendered = max(0, round((float) ($payment['amount_tendered'] ?? $received), 2));
                    if ($tendered + 0.0001 < $cash) {
                        $db->rollBack();
                        return ['ok' => false, 'error' => 'Cash given is less than this payment (KES ' . number_format($cash, 0) . ').'];
                    }
                    $change = round($tendered - $cash, 2);
                } elseif ($actualMethod === 'mpesa') {
                    $mpesa = $received;
                }
                $payment['provider'] = $payment['provider'] ?: ucfirst($actualMethod);
            }
            // card / bank / sacco: paid in full, no cash split tracking.
            $paymentAmount = $method === 'credit' ? ($cash + $mpesa) : $balanceDue;
            if ($method === 'credit' && $paymentAmount <= 0.0001) {
                $paymentAmount = min(max(0, round((float) ($payment['amount_received'] ?? 0), 2)), $balanceDue);
            }
            $provider = trim((string) ($payment['provider'] ?? ''));
            $accountName = trim((string) ($payment['account_name'] ?? ''));
            $reference = trim((string) ($payment['reference'] ?? ''));

            if ($method === 'mpesa' && $provider === '') {
                $provider = 'M-Pesa';
            }
            if ($method === 'split' && $provider === '') {
                $provider = 'Cash + M-Pesa';
            }
            if ($method === 'credit' && $provider === '') {
                $provider = 'Credit payment';
            }

            $newPaid = round($paidBefore + $paymentAmount, 2);
            $newDue = max(0, round($total - $newPaid, 2));
            $newStatus = $newDue <= 0.0001 ? 'paid' : 'open';
            $paymentStatus = $newStatus === 'paid' ? 'paid' : 'part_paid';

            $db->prepare(
                'INSERT INTO order_payments
                    (tenant_id, order_id, staff_id, amount, method, cash_amount, mpesa_amount, amount_tendered, change_due, provider, account_name, reference)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $tid,
                $orderId,
                $staffId,
                $paymentAmount,
                $method,
                $cash > 0 ? $cash : null,
                $mpesa > 0 ? $mpesa : null,
                $tendered,
                $change,
                $provider !== '' ? $provider : null,
                $accountName !== '' ? $accountName : null,
                $reference !== '' ? $reference : null,
            ]);

            $cashSql = $cash > 0 ? 'cash_amount = COALESCE(cash_amount,0) + ?' : 'cash_amount = cash_amount';
            $mpesaSql = $mpesa > 0 ? 'mpesa_amount = COALESCE(mpesa_amount,0) + ?' : 'mpesa_amount = mpesa_amount';
            $payVals = [];
            if ($cash > 0) { $payVals[] = $cash; }
            if ($mpesa > 0) { $payVals[] = $mpesa; }

            $db->prepare(
                'UPDATE orders
                    SET status = ?, payment_status = ?, amount_paid = ?, amount_due = ?,
                        payment_method = ?, ' . $cashSql . ', ' . $mpesaSql . ',
                        amount_tendered = ?, change_due = ?, payment_provider = ?,
                        payment_account_name = ?, payment_reference = ?,
                        paid_by = ?, paid_at = NOW()
                  WHERE id = ?'
            )->execute(array_merge([
                $newStatus,
                $paymentStatus,
                $newPaid,
                $newDue,
                $method,
            ], $payVals, [
                $tendered,
                $change,
                $provider !== '' ? $provider : null,
                $accountName !== '' ? $accountName : null,
                $reference !== '' ? $reference : null,
                $staffId,
                $orderId,
            ]));

            $customerId = (int) ($order['customer_id'] ?? 0);
            if ($newStatus === 'paid' && $customerId > 0) {
                try {
                    $tenant = (new TenantModel($db))->find($tid);
                    $rate = (float) ($tenant['loyalty_points_per_kes'] ?? 1);
                    $earned = round($total * $rate, 2);
                    if ($earned > 0) {
                        (new CustomerModel($db))->adjustPoints($customerId, $earned, 'Sale #' . $orderId, $orderId, $staffId);
                        try {
                            $db->prepare('UPDATE orders SET loyalty_points_earned = ? WHERE id = ?')->execute([$earned, $orderId]);
                        } catch (\PDOException $ignored) {}
                    }
                } catch (\Throwable $ignored) {}
            }

            $db->commit();
            return ['ok' => true, 'status' => $newStatus, 'amount_due' => $newDue, 'error' => null];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            error_log('OrderModel::markPaid failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not record the payment. Please try again.'];
        }
    }

    private function ensurePaymentSchema(): void
    {
        $this->ensureColumn('orders', 'customer_id', "ALTER TABLE orders ADD COLUMN customer_id INT NULL AFTER customer_email");
        $this->ensureColumn('orders', 'sale_type', "ALTER TABLE orders ADD COLUMN sale_type ENUM('retail','wholesale') NOT NULL DEFAULT 'retail' AFTER channel");
        $this->ensureColumn('order_items', 'price_type', "ALTER TABLE order_items ADD COLUMN price_type ENUM('retail','wholesale') NOT NULL DEFAULT 'retail' AFTER unit_price");
        $this->ensureColumn('orders', 'vat_rate', "ALTER TABLE orders ADD COLUMN vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER discount_amount");
        $this->ensureColumn('orders', 'vat_amount', "ALTER TABLE orders ADD COLUMN vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER vat_rate");
        $this->ensureColumn('orders', 'payment_provider', "ALTER TABLE orders ADD COLUMN payment_provider VARCHAR(100) NULL AFTER payment_method");
        $this->ensureColumn('orders', 'payment_account_name', "ALTER TABLE orders ADD COLUMN payment_account_name VARCHAR(160) NULL AFTER payment_provider");
        $this->ensureColumn('orders', 'payment_reference', "ALTER TABLE orders ADD COLUMN payment_reference VARCHAR(120) NULL AFTER payment_account_name");
        $this->ensureColumn('orders', 'payment_status', "ALTER TABLE orders ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'credit' AFTER payment_method");
        $this->ensureColumn('orders', 'amount_paid', "ALTER TABLE orders ADD COLUMN amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER total");
        $this->ensureColumn('orders', 'amount_due', "ALTER TABLE orders ADD COLUMN amount_due DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER amount_paid");
        $this->ensureColumn('orders', 'loyalty_points_earned', "ALTER TABLE orders ADD COLUMN loyalty_points_earned DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER customer_id");
        $this->ensureColumn('orders', 'loyalty_points_redeemed', "ALTER TABLE orders ADD COLUMN loyalty_points_redeemed DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER loyalty_points_earned");
        $this->ensureColumn('orders', 'credit_duration_days', "ALTER TABLE orders ADD COLUMN credit_duration_days INT NULL AFTER amount_due");
        $this->ensureColumn('orders', 'credit_due_at', "ALTER TABLE orders ADD COLUMN credit_due_at DATETIME NULL AFTER credit_duration_days");
        $this->ensureColumn('orders', 'thank_you_sent_at', "ALTER TABLE orders ADD COLUMN thank_you_sent_at DATETIME NULL AFTER delivery_note_sent_at");
        $this->ensureColumn('orders', 'remembrance_sent_at', "ALTER TABLE orders ADD COLUMN remembrance_sent_at DATETIME NULL AFTER thank_you_sent_at");
        try {
            $this->db->exec("ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cash','mpesa','split','card','bank','sacco','credit') DEFAULT NULL");
        } catch (\PDOException $ignored) {}
        try {
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS order_payments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    order_id INT NOT NULL,
                    staff_id INT NULL,
                    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    method VARCHAR(20) NOT NULL,
                    cash_amount DECIMAL(12,2) NULL,
                    mpesa_amount DECIMAL(12,2) NULL,
                    amount_tendered DECIMAL(12,2) NULL,
                    change_due DECIMAL(12,2) NULL,
                    provider VARCHAR(100) NULL,
                    account_name VARCHAR(160) NULL,
                    reference VARCHAR(120) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_order_payment_order (tenant_id, order_id),
                    KEY idx_order_payment_staff (tenant_id, staff_id),
                    CONSTRAINT fk_order_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (\PDOException $ignored) {}
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

    /** Admin delete for any non-void invoice/order sale. Restores quantities not already returned. */
    public function deleteSale(int $orderId, int $staffId): array
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
            if (!$order) {
                $db->rollBack();
                return ['ok' => false, 'error' => 'Invoice not found.'];
            }
            if (($order['status'] ?? '') === 'void') {
                $db->rollBack();
                return ['ok' => false, 'error' => 'This invoice is already deleted.'];
            }

            $items = $db->prepare(
                "SELECT oi.id, oi.product_id, oi.quantity, COALESCE(ret.returned_quantity,0) AS returned_quantity
                   FROM order_items oi
              LEFT JOIN (
                        SELECT tenant_id, source_item_id, SUM(returned_quantity) AS returned_quantity
                          FROM product_returns
                         WHERE source_type = 'order'
                      GROUP BY tenant_id, source_item_id
                   ) ret ON ret.tenant_id = oi.tenant_id AND ret.source_item_id = oi.id
                  WHERE oi.order_id = ? AND oi.tenant_id = ?"
            );
            $items->execute([$orderId, $tid]);
            $restore = $db->prepare('UPDATE products SET quantity = quantity + ? WHERE id = ? AND tenant_id = ?');
            foreach ($items->fetchAll() as $it) {
                $qty = max(0, round((float) $it['quantity'] - (float) $it['returned_quantity'], 2));
                if ($qty > 0 && !empty($it['product_id'])) {
                    $restore->execute([$qty, (int) $it['product_id'], $tid]);
                }
            }

            $db->prepare(
                "UPDATE orders
                    SET status = 'void', payment_status = 'deleted', amount_paid = 0, amount_due = 0,
                        cash_amount = NULL, mpesa_amount = NULL, paid_by = ?, paid_at = NOW()
                  WHERE id = ? AND tenant_id = ?"
            )->execute([$staffId, $orderId, $tid]);

            $db->commit();
            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            error_log('OrderModel::deleteSale failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not delete this invoice sale.'];
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
                   AND GREATEST(COALESCE(o.total,0) - COALESCE(o.amount_paid,0), 0) > 0.0001
              ORDER BY o.created_at ASC, o.id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tid]);
        return $stmt->fetchAll();
    }

    /** Recent non-void orders for invoice/receipt/delivery/thank-you/reminder tools. */
    public function documentOrders(int $limit = 200): array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            "SELECT o.*, u.username AS opened_by_name,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
               FROM orders o
          LEFT JOIN users u ON u.id = o.opened_by
              WHERE o.tenant_id = ? AND o.status <> 'void'
           ORDER BY COALESCE(o.paid_at, o.created_at) DESC, o.id DESC
              LIMIT " . (int) $limit
        );
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

    public function payments(int $orderId): array
    {
        $tid = \TenantContext::tenantId();
        try {
            $stmt = $this->db->prepare(
                "SELECT op.*, u.username AS staff_name
                   FROM order_payments op
              LEFT JOIN users u ON u.id = op.staff_id
                  WHERE op.order_id = ? AND op.tenant_id = ?
               ORDER BY op.created_at ASC, op.id ASC"
            );
            $stmt->execute([$orderId, $tid]);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            return [];
        }
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
            "SELECT oi.id, oi.order_id, oi.product_name, oi.quantity, oi.line_total,
                    oi.product_id, p.quantity AS stock_left
               FROM order_items oi
          LEFT JOIN products p ON p.id = oi.product_id AND p.tenant_id = oi.tenant_id
              WHERE oi.tenant_id = ? AND oi.order_id IN ($in) ORDER BY oi.id ASC"
        );
        $stmt->execute(array_merge([$tid], $orderIds));
        $rows = $stmt->fetchAll();
        $returns = (new ReturnModel($this->db))->returnsForItems('order', array_column($rows, 'id'));
        $out = [];
        foreach ($rows as $r) {
            $ret = $returns[(int) $r['id']] ?? ['returned' => 0.0, 'used' => 0.0];
            $out[(int) $r['order_id']][] = [
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

    public function markThankYouSent(int $orderId): void
    {
        $tid = \TenantContext::tenantId();
        try {
            $this->db->prepare('UPDATE orders SET thank_you_sent_at = NOW() WHERE id = ? AND tenant_id = ?')->execute([$orderId, $tid]);
        } catch (\PDOException $e) {
            // Column may not exist yet on older installs.
        }
    }

    public function markRemembranceSent(int $orderId): void
    {
        $tid = \TenantContext::tenantId();
        try {
            $this->db->prepare('UPDATE orders SET remembrance_sent_at = NOW() WHERE id = ? AND tenant_id = ?')->execute([$orderId, $tid]);
        } catch (\PDOException $e) {
        }
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
            $r['sale_type']        = $r['sale_type'] ?? 'retail';
            $r['payment_status']   = 'paid';               // only paid tabs are reported as sales
            $r['discount_amount']  = (float) ($r['discount_amount'] ?? 0);
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
            $r['sale_type']       = $r['sale_type'] ?? 'retail';
            $r['payment_status']  = 'paid';
            $r['discount_amount'] = (float) ($r['discount_amount'] ?? 0);
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
            "SELECT oi.product_name, SUM(GREATEST(oi.quantity - COALESCE(ret.returned_quantity,0), 0)) AS qty, SUM(oi.line_total) AS revenue
               FROM order_items oi
               JOIN orders o ON o.id = oi.order_id AND o.tenant_id = oi.tenant_id
          LEFT JOIN (
                    SELECT tenant_id, source_item_id, SUM(returned_quantity) AS returned_quantity
                      FROM product_returns
                     WHERE source_type = 'order'
                  GROUP BY tenant_id, source_item_id
               ) ret ON ret.tenant_id = oi.tenant_id AND ret.source_item_id = oi.id
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
                       MAX(oi.product_name) AS product_name,
                       SUM(GREATEST(oi.quantity - COALESCE(ret.returned_quantity,0), 0)) AS qty,
                       SUM(oi.line_total) AS revenue,
                       SUM(GREATEST(oi.quantity - COALESCE(ret.returned_quantity,0), 0) * COALESCE(p.`{$costCol}`, 0)) AS cost
                  FROM order_items oi
                  JOIN orders o    ON o.id = oi.order_id AND o.tenant_id = oi.tenant_id
             LEFT JOIN products p  ON p.id = oi.product_id AND p.tenant_id = oi.tenant_id
             LEFT JOIN (
                    SELECT tenant_id, source_item_id, SUM(returned_quantity) AS returned_quantity
                      FROM product_returns
                     WHERE source_type = 'order'
                  GROUP BY tenant_id, source_item_id
             ) ret ON ret.tenant_id = oi.tenant_id AND ret.source_item_id = oi.id
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
