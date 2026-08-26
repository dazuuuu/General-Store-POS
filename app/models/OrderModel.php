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

    /** Run expensive one-time schema/balance sync at most once per request. */
    private static bool $paymentSchemaSynced = false;

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
            $additionalCharges = round(max(0, (float) ($in['additional_charges'] ?? 0)), 2);
            $additionalNote = trim((string) ($in['additional_charges_note'] ?? ''));
            $email = trim((string) ($in['customer_email'] ?? ''));
            $phone = trim((string) ($in['customer_phone'] ?? ''));
            $customerId = (int) ($in['customer_id'] ?? 0);
            if ($customerId <= 0 && $tableName !== '') {
                $byName = (new CustomerModel($db))->findByName($tableName);
                if ($byName) {
                    $customerId = (int) $byName['id'];
                }
            }
            $creditDays = max(0, (int) ($in['credit_duration_days'] ?? 0));
            $creditDueAt = $creditDays > 0 ? date('Y-m-d H:i:s', strtotime('+' . $creditDays . ' days')) : null;
            $vatRate = max(0, round((float) ($in['vat_rate'] ?? 0), 2));
            $vatInclusive = array_key_exists('vat_inclusive', $in) ? (bool) $in['vat_inclusive'] : true;

            $sub = $db->prepare('SELECT subtotal FROM orders WHERE id = ?');
            $sub->execute([$orderId]);
            $subtotal = (float) $sub->fetchColumn();
            $priced = \Pricing::totals($subtotal, $discountIn, $vatRate, $vatInclusive, $additionalCharges);

            // Credit tabs (channel=tab) start unpaid — stamp method/status so Sales
            // and Payments show them immediately without waiting on migrations.
            $sets = ['discount_amount = ?', 'total = ?', 'amount_paid = ?', 'amount_due = ?', 'customer_email = ?', 'customer_phone = ?'];
            $vals = [
                $priced['discount'],
                $priced['total'],
                0,
                $priced['total'],
                $email !== '' ? $email : null,
                $phone !== '' ? $phone : null,
            ];
            try {
                $db->query('SELECT additional_charges FROM orders LIMIT 1');
                $sets[] = 'additional_charges = ?';
                $sets[] = 'additional_charges_note = ?';
                $vals[] = $priced['additional_charges'];
                $vals[] = $additionalNote !== '' ? $additionalNote : null;
            } catch (\PDOException $ignored) {}
            if ($channel === 'tab') {
                $sets[] = 'payment_method = ?';
                $sets[] = 'payment_status = ?';
                $vals[] = 'credit';
                $vals[] = 'credit';
            }
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
            } catch (\PDOException $ignored) {}
            if ($customerId > 0) {
                $sets[] = 'customer_id = ?';
                $vals[] = $customerId;
            }
            $vals[] = $orderId;
            $db->prepare('UPDATE orders SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);

            $db->commit();
            if ($channel === 'tab' && $customerId > 0) {
                try {
                    (new CustomerModel($db))->refreshCreditBalance($customerId);
                } catch (\Throwable $ignored) {}
            }
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
                        quantity, unit, units_per_pack, pack_unit, pack_price, retail_pack_price
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
                $lineTotal = \Pricing::lineTotal($p, $qty, $lineSaleType);
                $unitPrice = $qty > 0 ? round($lineTotal / $qty, 2) : 0.0;
                if ($unitPrice <= 0) {
                    $unitPrice = (float) (($p['retail_price'] ?? 0) ?: ($p['selling_price'] ?? 0));
                    $lineTotal = round($qty * $unitPrice, 2);
                }
                $insertItem->execute([$tid, $orderId, $pid, $p['name'], $unitPrice, $lineSaleType, $qty, $lineTotal, $staffId]);
            }

            $sum = $db->prepare('SELECT COALESCE(SUM(line_total),0) FROM order_items WHERE order_id = ? AND tenant_id = ?');
            $sum->execute([$orderId, $tid]);
            $subtotal = round((float) $sum->fetchColumn(), 2);
            if ($subtotal <= 0.0001) {
                $db->rollBack();
                return ['ok' => false, 'errors' => ['_' => 'Invoice must keep at least one product.']];
            }
            $discount = max(0, round((float) ($in['discount_amount'] ?? 0), 2));
            $additionalCharges = round(max(0, (float) ($in['additional_charges'] ?? $order['additional_charges'] ?? 0)), 2);
            $additionalNote = trim((string) ($in['additional_charges_note'] ?? $order['additional_charges_note'] ?? ''));
            $priced = \Pricing::totals($subtotal, $discount, (float) ($order['vat_rate'] ?? 0), true, $additionalCharges);
            $paid = max(0, (float) ($order['amount_paid'] ?? 0));
            $paid = min($paid, (float) $priced['total']);
            $due = max(0, round((float) $priced['total'] - $paid, 2));
            $creditDays = max(0, (int) ($in['credit_duration_days'] ?? 0));
            $creditDueAt = $creditDays > 0 ? date('Y-m-d H:i:s', strtotime('+' . $creditDays . ' days', strtotime($order['created_at'] ?? 'now'))) : null;

            $db->prepare(
                'UPDATE orders
                    SET table_name = ?, sale_type = ?, subtotal = ?, discount_amount = ?, additional_charges = ?,
                        additional_charges_note = ?, total = ?,
                        amount_paid = ?, amount_due = ?, customer_email = ?, customer_phone = ?,
                        credit_duration_days = ?, credit_due_at = ?
                  WHERE id = ? AND tenant_id = ?'
            )->execute([
                $tableName,
                $saleType,
                $subtotal,
                $priced['discount'],
                $priced['additional_charges'],
                $additionalNote !== '' ? $additionalNote : null,
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
                          credit_limit, quantity, unit, units_per_pack, pack_unit, pack_price, retail_pack_price
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
            $lineTotal = \Pricing::lineTotal($offerRow + $p, $qty, $lineSaleType);
            $unitPrice = $qty > 0 ? round($lineTotal / $qty, 2) : 0.0;
            if ($unitPrice <= 0) { $unitPrice = (float) ($p['retail_price'] ?: $p['selling_price']); $lineTotal = round($unitPrice * $qty, 2); }
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
        $allowed = ['cash', 'mpesa', 'split', 'card', 'bank', 'sacco', 'paybill', 'credit'];
        $method = in_array($payment['method'] ?? '', $allowed, true) ? $payment['method'] : null;
        if (!$method) {
            return ['ok' => false, 'error' => 'Choose how the customer paid.'];
        }

        $this->ensurePaymentSchema();
        $db = $this->db;
        try {
            $db->beginTransaction();
            $sel = $db->prepare('SELECT id, status, total, amount_paid, amount_due, customer_id, table_name FROM orders WHERE id = ? AND tenant_id = ? FOR UPDATE');
            $sel->execute([$orderId, $tid]);
            $order = $sel->fetch();
            if (!$order) { $db->rollBack(); return ['ok' => false, 'error' => 'Sale not found.']; }
            if ($order['status'] !== 'open') { $db->rollBack(); return ['ok' => false, 'error' => 'This sale is not open.']; }

            // Attach customer from the payment form when the invoice was opened by name only.
            $linkCustomerId = (int) ($payment['customer_id'] ?? 0);
            $customerId = (int) ($order['customer_id'] ?? 0);
            if ($customerId <= 0 && $linkCustomerId > 0) {
                $customerId = $linkCustomerId;
                try {
                    $db->prepare('UPDATE orders SET customer_id = ? WHERE id = ? AND tenant_id = ?')
                        ->execute([$customerId, $orderId, $tid]);
                } catch (\PDOException $ignored) {}
            }
            if ($customerId <= 0) {
                $byName = (new CustomerModel($db))->findByName((string) ($order['table_name'] ?? ''));
                if ($byName) {
                    $customerId = (int) $byName['id'];
                    try {
                        $db->prepare('UPDATE orders SET customer_id = ? WHERE id = ? AND tenant_id = ?')
                            ->execute([$customerId, $orderId, $tid]);
                    } catch (\PDOException $ignored) {}
                }
            }

            $total = (float) $order['total'];
            $paidBefore = max(0, (float) ($order['amount_paid'] ?? 0));
            // Always trust total - paid so a stale amount_due=0 cannot block deposits.
            $balanceDue = max(0, round($total - $paidBefore, 2));
            if ($balanceDue <= 0.0001) {
                $db->rollBack();
                return ['ok' => false, 'error' => 'This sale is already fully paid.'];
            }
            $cash  = 0.0;
            $mpesa = 0.0;
            $tendered = null;
            $change = null;
            $recordMethod = $method;
            if ($method === 'cash') {
                $cash = $balanceDue;
                $tendered = $this->parseMoney($payment['amount_tendered'] ?? null, $cash);
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
                    $tendered = $this->parseMoney($payment['amount_tendered'] ?? null, $cash);
                    if ($tendered + 0.0001 < $cash) {
                        $db->rollBack();
                        return ['ok' => false, 'error' => 'Cash given is less than the cash portion (KES ' . number_format($cash, 0) . ').'];
                    }
                    $change = round($tendered - $cash, 2);
                }
            } elseif ($method === 'credit') {
                $depositAllowed = ['cash', 'mpesa', 'paybill', 'card', 'bank', 'sacco'];
                $actualMethod = in_array($payment['deposit_method'] ?? '', $depositAllowed, true) ? $payment['deposit_method'] : 'cash';
                $recordMethod = $actualMethod;
                $received = $this->parseMoney($payment['amount_received'] ?? null, 0.0);
                if ($received <= 0) {
                    $db->rollBack();
                    return ['ok' => false, 'error' => 'Enter the amount the customer is paying now.'];
                }
                $received = min($received, $balanceDue);
                if ($actualMethod === 'cash') {
                    $cash = $received;
                    // Empty "cash given" must default to the deposit amount — otherwise
                    // deposits silently fail and balances never move.
                    $tendered = $this->parseMoney($payment['amount_tendered'] ?? null, $received);
                    if ($tendered + 0.0001 < $cash) {
                        $db->rollBack();
                        return ['ok' => false, 'error' => 'Cash given is less than this payment (KES ' . number_format($cash, 0) . ').'];
                    }
                    $change = round($tendered - $cash, 2);
                } elseif ($actualMethod === 'mpesa') {
                    $mpesa = $received;
                }
                $payment['provider'] = $payment['provider'] ?: ($actualMethod === 'paybill' ? 'Paybill / Till' : ucfirst($actualMethod));
            } elseif ($method === 'paybill') {
                if (trim((string) ($payment['provider'] ?? '')) === '') {
                    $payment['provider'] = 'Paybill / Till';
                }
            }
            if ($method === 'credit') {
                $paymentAmount = min($this->parseMoney($payment['amount_received'] ?? null, 0.0), $balanceDue);
                if ($paymentAmount <= 0.0001) {
                    $paymentAmount = round($cash + $mpesa, 2);
                }
            } else {
                $paymentAmount = $balanceDue;
            }
            $provider = trim((string) ($payment['provider'] ?? ''));
            $accountName = trim((string) ($payment['account_name'] ?? ''));
            $reference = trim((string) ($payment['reference'] ?? ''));

            if ($method === 'mpesa' && $provider === '') {
                $provider = 'M-Pesa';
            }
            if ($method === 'paybill' && $provider === '') {
                $provider = 'Paybill / Till';
            }
            if ($method === 'split' && $provider === '') {
                $provider = 'Cash + M-Pesa';
            }
            if ($method === 'credit' && $provider === '') {
                $provider = 'Deposit';
            }

            if ($paymentAmount <= 0.0001) {
                $db->rollBack();
                return ['ok' => false, 'error' => 'Enter the amount the customer is paying now.'];
            }

            $db->prepare(
                'INSERT INTO order_payments
                    (tenant_id, order_id, staff_id, amount, method, cash_amount, mpesa_amount, amount_tendered, change_due, provider, account_name, reference)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $tid,
                $orderId,
                $staffId,
                $paymentAmount,
                $recordMethod,
                $cash > 0 ? $cash : null,
                $mpesa > 0 ? $mpesa : null,
                $tendered,
                $change,
                $provider !== '' ? $provider : null,
                $accountName !== '' ? $accountName : null,
                $reference !== '' ? $reference : null,
            ]);

            // Always recompute live balance from the payments ledger.
            $sumPaid = $db->prepare('SELECT COALESCE(SUM(amount),0) FROM order_payments WHERE order_id = ? AND tenant_id = ?');
            $sumPaid->execute([$orderId, $tid]);
            $newPaid = round((float) $sumPaid->fetchColumn(), 2);
            $newDue = max(0, round($total - $newPaid, 2));
            $newStatus = $newDue <= 0.0001 ? 'paid' : 'open';
            $paymentStatus = $newStatus === 'paid' ? 'paid' : ($newPaid > 0.0001 ? 'part_paid' : 'credit');
            $orderPayMethod = $newStatus === 'paid' ? $recordMethod : 'credit';

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
                        paid_by = ?, paid_at = CASE WHEN ? = \'paid\' THEN NOW() ELSE paid_at END
                  WHERE id = ?'
            )->execute(array_merge([
                $newStatus,
                $paymentStatus,
                $newPaid,
                $newDue,
                $orderPayMethod,
            ], $payVals, [
                $tendered,
                $change,
                $provider !== '' ? $provider : null,
                $accountName !== '' ? $accountName : null,
                $reference !== '' ? $reference : null,
                $staffId,
                $newStatus,
                $orderId,
            ]));

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

            if ($customerId > 0) {
                try {
                    (new CustomerModel($db))->refreshCreditBalance($customerId);
                } catch (\Throwable $ignored) {}
            }

            return [
                'ok' => true,
                'status' => $newStatus,
                'amount_due' => $newDue,
                'amount_paid' => $newPaid,
                'amount_paid_now' => $paymentAmount,
                'order_id' => $orderId,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            error_log('OrderModel::markPaid failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not record the payment: ' . $e->getMessage()];
        }
    }

    /** Parse a posted money field; empty string / null uses $fallback (not 0). */
    private function parseMoney($value, float $fallback = 0.0): float
    {
        if ($value === null || $value === '') {
            return round($fallback, 2);
        }
        return max(0, round((float) $value, 2));
    }

    /**
     * Open unpaid invoices for a customer (by customer_id and/or checkout name).
     * Returns Date / Invoice / Amount (balance) ready for the payments ledger.
     */
    public function openInvoicesForCustomer(?int $customerId, string $customerName = '', int $limit = 100): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            return [];
        }
        $customerName = trim($customerName);
        if (($customerId === null || $customerId <= 0) && $customerName === '') {
            return [];
        }

        $sql = "SELECT o.*, u.username AS opened_by_name,
                       c.name AS customer_record_name,
                       c.company_name AS customer_company,
                       (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
                       GREATEST(COALESCE(o.total,0) - COALESCE(o.amount_paid,0), 0) AS balance_due
                  FROM orders o
             LEFT JOIN users u ON u.id = o.opened_by
             LEFT JOIN customers c ON c.id = o.customer_id AND c.tenant_id = o.tenant_id
                 WHERE o.tenant_id = ? AND o.status = 'open'
                   AND GREATEST(COALESCE(o.total,0) - COALESCE(o.amount_paid,0), 0) > 0.0001";
        $params = [$tid];
        $parts = [];
        if ($customerId !== null && $customerId > 0) {
            $parts[] = 'o.customer_id = ?';
            $params[] = $customerId;
            // Also pull invoices opened under this customer's exact name
            // when customer_id was never linked at sale time.
            $nameSt = $this->db->prepare('SELECT name FROM customers WHERE id = ? AND tenant_id = ? LIMIT 1');
            $nameSt->execute([$customerId, $tid]);
            $custName = trim((string) ($nameSt->fetchColumn() ?: ''));
            if ($custName !== '') {
                $parts[] = 'LOWER(TRIM(o.table_name)) = LOWER(?)';
                $params[] = $custName;
            }
        }
        if ($customerName !== '') {
            $parts[] = 'LOWER(TRIM(o.table_name)) = LOWER(?)';
            $params[] = $customerName;
        }
        $sql .= ' AND (' . implode(' OR ', $parts) . ')';
        $sql .= ' ORDER BY o.created_at ASC, o.id ASC LIMIT ' . (int) $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        // Deduplicate when both customer_id and name match the same invoice.
        $seen = [];
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $r['amount_due'] = (float) $r['balance_due'];
            $out[] = $r;
        }
        return $out;
    }

    /** Payment history across all invoices for a customer. */
    public function paymentsForCustomer(?int $customerId, string $customerName = '', int $limit = 200): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            return [];
        }
        $customerName = trim($customerName);
        if (($customerId === null || $customerId <= 0) && $customerName === '') {
            return [];
        }
        try {
            $sql = "SELECT op.*, o.receipt_number, o.table_name, o.created_at AS invoice_date,
                           u.username AS staff_name
                      FROM order_payments op
                 INNER JOIN orders o ON o.id = op.order_id AND o.tenant_id = op.tenant_id
                 LEFT JOIN users u ON u.id = op.staff_id
                     WHERE op.tenant_id = ? AND o.status <> 'void'";
            $params = [$tid];
            $parts = [];
            if ($customerId !== null && $customerId > 0) {
                $parts[] = 'o.customer_id = ?';
                $params[] = $customerId;
            }
            if ($customerName !== '') {
                $parts[] = 'LOWER(TRIM(o.table_name)) = LOWER(?)';
                $params[] = $customerName;
            }
            $sql .= ' AND (' . implode(' OR ', $parts) . ')';
            $sql .= ' ORDER BY op.created_at DESC, op.id DESC LIMIT ' . (int) $limit;
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Apply a customer payment across one or more open invoices (FIFO / selected).
     * Reuses markPaid so each invoice still gets its own payment row + receipt.
     *
     * @param int[] $orderIds
     * @return array{ok:bool,error:?string,allocations:array,receipt_order_ids:array,amount_remaining:float}
     */
    /**
     * Add an optional pure-profit extra charge (delivery, packing, etc.) onto an open invoice.
     * Recalculates total + amount_due so wallet payments can collect the charge with the balance.
     */
    public function addAdditionalCharge(int $orderId, float $amount, string $note = ''): array
    {
        $amount = round(max(0, $amount), 2);
        if ($amount <= 0) {
            return ['ok' => true, 'order_id' => $orderId, 'added' => 0.0, 'error' => null];
        }
        $this->ensurePaymentSchema();
        $tid = \TenantContext::tenantId();
        $order = $this->find($orderId);
        if (!$order || (int) ($order['tenant_id'] ?? 0) !== (int) $tid) {
            return ['ok' => false, 'order_id' => $orderId, 'added' => 0.0, 'error' => 'Invoice not found.'];
        }
        if (($order['status'] ?? '') !== 'open') {
            return ['ok' => false, 'order_id' => $orderId, 'added' => 0.0, 'error' => 'Extra charges can only be added on an open invoice.'];
        }

        $newCharges = round((float) ($order['additional_charges'] ?? 0) + $amount, 2);
        $existingNote = trim((string) ($order['additional_charges_note'] ?? ''));
        $note = trim($note);
        if (strlen($note) > 255) {
            $note = substr($note, 0, 255);
        }
        if ($note !== '') {
            $merged = $existingNote === '' ? $note : ($existingNote . '; ' . $note);
            if (strlen($merged) > 255) {
                $merged = substr($merged, 0, 255);
            }
        } else {
            $merged = $existingNote !== '' ? $existingNote : null;
        }

        $subtotal = (float) ($order['subtotal'] ?? 0);
        $discount = (float) ($order['discount_amount'] ?? 0);
        $vatRate = max(0, (float) ($order['vat_rate'] ?? 0));
        $priced = \Pricing::totals($subtotal, $discount, $vatRate, true, $newCharges);
        $paid = max(0, (float) ($order['amount_paid'] ?? 0));
        $due = max(0, round($priced['total'] - $paid, 2));
        $payStatus = $due <= 0.0001 ? 'paid' : ($paid > 0.0001 ? 'part_paid' : 'credit');

        $stmt = $this->db->prepare(
            "UPDATE orders
                SET additional_charges = ?, additional_charges_note = ?, total = ?, vat_amount = ?,
                    amount_due = ?, payment_status = ?
              WHERE id = ? AND tenant_id = ? AND status = 'open'"
        );
        $stmt->execute([
            $newCharges,
            $merged,
            $priced['total'],
            $priced['vat_amount'],
            $due,
            $payStatus,
            $orderId,
            $tid,
        ]);
        if ($stmt->rowCount() < 1) {
            return ['ok' => false, 'order_id' => $orderId, 'added' => 0.0, 'error' => 'Could not save the extra charge.'];
        }

        return [
            'ok' => true,
            'order_id' => $orderId,
            'added' => $amount,
            'additional_charges' => $newCharges,
            'total' => $priced['total'],
            'amount_due' => $due,
            'error' => null,
        ];
    }

    /**
     * Open a charge-only credit invoice (no stock, no deposit) so extra charges
     * can increase a customer's wallet balance even when they have no open tab.
     */
    public function createWalletCharge(string $customerName, int $customerId, float $amount, string $note, int $staffId): array
    {
        $amount = round(max(0, $amount), 2);
        $customerName = trim($customerName);
        $note = trim($note);
        if ($amount <= 0) {
            return ['ok' => false, 'order_id' => null, 'error' => 'Enter the extra charge amount.'];
        }
        if ($note === '') {
            return ['ok' => false, 'order_id' => null, 'error' => 'Enter a reason for the extra charge (e.g. delivery, packing).'];
        }
        if ($customerName === '' && $customerId <= 0) {
            return ['ok' => false, 'order_id' => null, 'error' => 'Choose a customer first.'];
        }

        $this->ensurePaymentSchema();
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            return ['ok' => false, 'order_id' => null, 'error' => 'No shop in context.'];
        }
        if ($staffId <= 0) {
            return ['ok' => false, 'order_id' => null, 'error' => 'No staff in context.'];
        }

        $cm = new CustomerModel($this->db);
        if ($customerId <= 0) {
            $byName = $cm->findByName($customerName);
            if ($byName) {
                $customerId = (int) $byName['id'];
                $customerName = (string) ($byName['name'] ?? $customerName);
            } else {
                $created = $cm->create(['name' => $customerName]);
                if (!empty($created['ok'])) {
                    $customerId = (int) $created['id'];
                }
            }
        } else {
            $cust = $cm->find($customerId);
            if ($cust) {
                $customerName = $customerName !== '' ? $customerName : (string) ($cust['name'] ?? '');
            }
        }
        if ($customerName === '') {
            return ['ok' => false, 'order_id' => null, 'error' => 'Choose a customer first.'];
        }
        if (strlen($note) > 255) {
            $note = substr($note, 0, 255);
        }

        $db = $this->db;
        try {
            $db->beginTransaction();
            $ins = $db->prepare(
                "INSERT INTO orders (tenant_id, table_name, channel, opened_by, receipt_number, status, subtotal, total)
                 VALUES (?,?,'tab',?,'PENDING','open',0,0)"
            );
            $ins->execute([$tid, $customerName, $staffId]);
            $orderId = (int) $db->lastInsertId();
            $receipt = 'ORD-' . str_pad((string) $orderId, 6, '0', STR_PAD_LEFT);
            $priced = \Pricing::totals(0, 0, 0, true, $amount);
            $sets = [
                'receipt_number = ?',
                'discount_amount = ?',
                'additional_charges = ?',
                'additional_charges_note = ?',
                'total = ?',
                'amount_paid = ?',
                'amount_due = ?',
                'payment_method = ?',
                'payment_status = ?',
            ];
            $vals = [
                $receipt,
                0,
                $priced['additional_charges'],
                $note,
                $priced['total'],
                0,
                $priced['total'],
                'credit',
                'credit',
            ];
            if ($customerId > 0) {
                $sets[] = 'customer_id = ?';
                $vals[] = $customerId;
            }
            $vals[] = $orderId;
            $db->prepare('UPDATE orders SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
            $db->commit();
            if ($customerId > 0) {
                try { $cm->refreshCreditBalance($customerId); } catch (\Throwable $ignored) {}
            }
            return [
                'ok' => true,
                'order_id' => $orderId,
                'receipt_number' => $receipt,
                'added' => $amount,
                'amount_due' => $priced['total'],
                'error' => null,
            ];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            error_log('OrderModel::createWalletCharge failed: ' . $e->getMessage());
            return ['ok' => false, 'order_id' => null, 'error' => 'Could not add the extra charge.'];
        }
    }

    public function applyCustomerPayment(array $orderIds, float $amount, array $payment, int $staffId): array
    {
        $amount = max(0, round($amount, 2));
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'Enter the amount being paid.', 'allocations' => [], 'receipt_order_ids' => [], 'amount_remaining' => 0.0];
        }
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if (!$orderIds) {
            return ['ok' => false, 'error' => 'Select at least one invoice.', 'allocations' => [], 'receipt_order_ids' => [], 'amount_remaining' => 0.0];
        }

        $remaining = $amount;
        $allocations = [];
        $receiptIds = [];
        $depositChannel = $payment['deposit_method'] ?? $payment['method'] ?? 'cash';
        if (($payment['method'] ?? '') !== 'credit' && in_array($payment['method'] ?? '', ['cash', 'mpesa', 'paybill', 'card', 'bank', 'sacco'], true)) {
            $depositChannel = $payment['method'];
        }

        foreach ($orderIds as $oid) {
            if ($remaining <= 0.0001) {
                break;
            }
            $order = $this->find($oid);
            if (!$order || ($order['status'] ?? '') !== 'open') {
                continue;
            }
            // Always derive due from total - paid so deposits never skip invoices
            // that still have a stale amount_due of 0.
            $due = max(0, round((float) ($order['total'] ?? 0) - max(0, (float) ($order['amount_paid'] ?? 0)), 2));
            if ($due <= 0.0001) {
                continue;
            }
            $slice = min($remaining, $due);
            $isFull = $slice + 0.0001 >= $due;
            $payload = $payment;
            if (!empty($payment['customer_id'])) {
                $payload['customer_id'] = (int) $payment['customer_id'];
            }
            if ($isFull && ($payment['method'] ?? '') !== 'credit' && ($payment['method'] ?? '') !== 'split') {
                // Full settle of this invoice with the chosen mode.
                $payload['method'] = $depositChannel === 'split' ? 'cash' : $depositChannel;
                if ($payload['method'] === 'cash') {
                    $rawTender = $payload['amount_tendered'] ?? null;
                    if ($rawTender === null || $rawTender === '') {
                        $payload['amount_tendered'] = $slice;
                    }
                }
            } else {
                $payload['method'] = 'credit';
                $payload['deposit_method'] = in_array($depositChannel, ['cash', 'mpesa', 'paybill', 'card', 'bank', 'sacco'], true) ? $depositChannel : 'cash';
                $payload['amount_received'] = $slice;
                if ($payload['deposit_method'] === 'cash') {
                    $rawTender = $payload['amount_tendered'] ?? null;
                    if ($rawTender === null || $rawTender === '') {
                        $payload['amount_tendered'] = $slice;
                    }
                }
            }
            $res = $this->markPaid($oid, $payload, $staffId);
            if (!$res['ok']) {
                if ($allocations) {
                    return [
                        'ok' => true,
                        'error' => null,
                        'partial_error' => $res['error'] ?? 'Stopped early.',
                        'allocations' => $allocations,
                        'receipt_order_ids' => $receiptIds,
                        'amount_remaining' => $remaining,
                    ];
                }
                return ['ok' => false, 'error' => $res['error'] ?? 'Could not record payment.', 'allocations' => [], 'receipt_order_ids' => [], 'amount_remaining' => $amount];
            }
            $paidNow = (float) ($res['amount_paid_now'] ?? $slice);
            $allocations[] = [
                'order_id' => $oid,
                'receipt_number' => $order['receipt_number'] ?? '',
                'amount' => $paidNow,
                'status' => $res['status'] ?? 'open',
                'amount_due' => (float) ($res['amount_due'] ?? 0),
            ];
            $receiptIds[] = $oid;
            $remaining = round($remaining - $paidNow, 2);
        }

        if (!$allocations) {
            return ['ok' => false, 'error' => 'No open balance found on the selected invoices.', 'allocations' => [], 'receipt_order_ids' => [], 'amount_remaining' => $amount];
        }
        return [
            'ok' => true,
            'error' => null,
            'allocations' => $allocations,
            'receipt_order_ids' => $receiptIds,
            'amount_remaining' => max(0, $remaining),
            'amount_applied' => round($amount - max(0, $remaining), 2),
        ];
    }

    private function ensurePaymentSchema(): void
    {
        $this->ensureColumn('orders', 'customer_id', "ALTER TABLE orders ADD COLUMN customer_id INT NULL AFTER customer_email");
        $this->ensureColumn('orders', 'sale_type', "ALTER TABLE orders ADD COLUMN sale_type ENUM('retail','wholesale') NOT NULL DEFAULT 'retail' AFTER channel");
        $this->ensureColumn('order_items', 'price_type', "ALTER TABLE order_items ADD COLUMN price_type ENUM('retail','wholesale') NOT NULL DEFAULT 'retail' AFTER unit_price");
        $this->ensureColumn('orders', 'vat_rate', "ALTER TABLE orders ADD COLUMN vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER discount_amount");
        $this->ensureColumn('orders', 'additional_charges', "ALTER TABLE orders ADD COLUMN additional_charges DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER discount_amount");
        $this->ensureColumn('orders', 'additional_charges_note', "ALTER TABLE orders ADD COLUMN additional_charges_note VARCHAR(255) NULL AFTER additional_charges");
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
        $this->ensureColumn('products', 'retail_pack_price', "ALTER TABLE `products` ADD COLUMN `retail_pack_price` DECIMAL(12,2) NULL AFTER `pack_price`");
        $this->ensureColumn('orders', 'thank_you_sent_at', "ALTER TABLE orders ADD COLUMN thank_you_sent_at DATETIME NULL AFTER delivery_note_sent_at");
        $this->ensureColumn('orders', 'remembrance_sent_at', "ALTER TABLE orders ADD COLUMN remembrance_sent_at DATETIME NULL AFTER thank_you_sent_at");
        // Widen payment_method so deposits (incl. paybill) never fail on a
        // stale ENUM — schema self-heals at runtime, no manual migration.
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
        if (!self::$paymentSchemaSynced) {
            self::$paymentSchemaSynced = true;
            try {
                $this->db->exec("ALTER TABLE orders MODIFY COLUMN payment_method VARCHAR(20) DEFAULT NULL");
            } catch (\PDOException $ignored) {}
            try {
                // Recompute open-invoice balances from the payments ledger so
                // deposits always show the real remaining amount automatically.
                $this->db->exec(
                    "UPDATE orders o
                        LEFT JOIN (
                            SELECT order_id, tenant_id, COALESCE(SUM(amount),0) AS paid
                              FROM order_payments
                          GROUP BY order_id, tenant_id
                        ) p ON p.order_id = o.id AND p.tenant_id = o.tenant_id
                        SET o.amount_paid = COALESCE(p.paid, o.amount_paid, 0),
                            o.amount_due = GREATEST(COALESCE(o.total,0) - COALESCE(p.paid, o.amount_paid, 0), 0),
                            o.payment_method = CASE
                                WHEN GREATEST(COALESCE(o.total,0) - COALESCE(p.paid, o.amount_paid, 0), 0) > 0.0001
                                    THEN COALESCE(NULLIF(o.payment_method,''), 'credit')
                                ELSE o.payment_method
                            END,
                            o.payment_status = CASE
                                WHEN GREATEST(COALESCE(o.total,0) - COALESCE(p.paid, o.amount_paid, 0), 0) <= 0.0001 THEN 'paid'
                                WHEN COALESCE(p.paid, o.amount_paid, 0) > 0.0001 THEN 'part_paid'
                                ELSE COALESCE(NULLIF(o.payment_status,''), 'credit')
                            END
                      WHERE o.status = 'open' AND COALESCE(o.total,0) > 0"
                );
            } catch (\PDOException $ignored) {}
            try {
                $this->db->exec(
                    "UPDATE orders
                        SET amount_paid = COALESCE(amount_paid, 0),
                            amount_due = GREATEST(COALESCE(total,0) - COALESCE(amount_paid,0), 0),
                            payment_method = COALESCE(NULLIF(payment_method,''), 'credit'),
                            payment_status = CASE
                                WHEN GREATEST(COALESCE(total,0) - COALESCE(amount_paid,0), 0) <= 0.0001 THEN 'paid'
                                WHEN COALESCE(amount_paid,0) > 0.0001 THEN 'part_paid'
                                ELSE COALESCE(NULLIF(payment_status,''), 'credit')
                            END
                      WHERE status = 'open' AND COALESCE(total,0) > 0
                        AND (amount_due IS NULL OR amount_due = 0)
                        AND COALESCE(amount_paid,0) < COALESCE(total,0)"
                );
            } catch (\PDOException $ignored) {}
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

    /** All open tabs for the tenant, oldest first (FIFO credit queue). */
    public function openOrders(): array
    {
        $tid = \TenantContext::tenantId();
        $sql = "SELECT o.*, u.username AS opened_by_name,
                       c.name AS customer_record_name,
                       c.company_name AS customer_company,
                       (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
                       GREATEST(COALESCE(o.amount_due, GREATEST(COALESCE(o.total,0) - COALESCE(o.amount_paid,0), 0)), 0) AS balance_due
                  FROM orders o
             LEFT JOIN users u ON u.id = o.opened_by
             LEFT JOIN customers c ON c.id = o.customer_id AND c.tenant_id = o.tenant_id
                 WHERE o.tenant_id = ? AND o.status = 'open'
                   AND GREATEST(COALESCE(o.total,0) - COALESCE(o.amount_paid,0), 0) > 0.0001
              ORDER BY o.created_at ASC, o.id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tid]);
        return $stmt->fetchAll();
    }

    /**
     * Search invoices by receipt #, customer name, company name, or phone.
     * Defaults to open unpaid credit invoices (balance > 0).
     *
     * @param array{open_only?:bool,limit?:int} $opts
     */
    public function searchInvoices(string $q, array $opts = []): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            return [];
        }
        $q = trim($q);
        $openOnly = !array_key_exists('open_only', $opts) || (bool) $opts['open_only'];
        $limit = max(1, min(100, (int) ($opts['limit'] ?? 40)));

        $sql = "SELECT o.*, u.username AS opened_by_name,
                       c.name AS customer_record_name,
                       c.company_name AS customer_company,
                       (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
                       GREATEST(COALESCE(o.amount_due, GREATEST(COALESCE(o.total,0) - COALESCE(o.amount_paid,0), 0)), 0) AS balance_due
                  FROM orders o
             LEFT JOIN users u ON u.id = o.opened_by
             LEFT JOIN customers c ON c.id = o.customer_id AND c.tenant_id = o.tenant_id
                 WHERE o.tenant_id = ? AND o.status <> 'void'";
        $params = [$tid];

        if ($openOnly) {
            $sql .= " AND o.status = 'open'
                      AND GREATEST(COALESCE(o.total,0) - COALESCE(o.amount_paid,0), 0) > 0.0001";
        }

        if ($q !== '') {
            $like = '%' . $q . '%';
            $sql .= " AND (
                        o.receipt_number LIKE ?
                        OR o.table_name LIKE ?
                        OR COALESCE(o.customer_phone,'') LIKE ?
                        OR COALESCE(c.name,'') LIKE ?
                        OR COALESCE(c.company_name,'') LIKE ?
                        OR COALESCE(c.phone,'') LIKE ?
                      )";
            $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
        }

        $sql .= " ORDER BY o.created_at DESC, o.id DESC LIMIT " . (int) $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Recent non-void orders for invoice/receipt/delivery/thank-you/reminder tools. */
    public function documentOrders(int $limit = 200): array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            "SELECT o.*, u.username AS opened_by_name,
                    c.name AS customer_record_name,
                    c.company_name AS customer_company,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
                    GREATEST(COALESCE(o.amount_due, GREATEST(COALESCE(o.total,0) - COALESCE(o.amount_paid,0), 0)), 0) AS balance_due
               FROM orders o
          LEFT JOIN users u ON u.id = o.opened_by
          LEFT JOIN customers c ON c.id = o.customer_id AND c.tenant_id = o.tenant_id
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

    /** SQL fragments used to recognize credit repayments in the period received. */
    private function paymentPeriodSql(string $period, string $alias = 'op'): string
    {
        return match ($period) {
            'today' => "DATE({$alias}.created_at) = CURDATE()",
            'week'  => "{$alias}.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'month' => "{$alias}.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            default => '1=1',
        };
    }

    private function orderPeriodSql(string $period, string $alias = 'o'): string
    {
        return match ($period) {
            'today' => "DATE({$alias}.created_at) = CURDATE()",
            'week'  => "{$alias}.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'month' => "{$alias}.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            default => '1=1',
        };
    }

    /**
     * Sales for a period (paid + open credit / part-paid), shaped with the same
     * keys SaleModel::forTenant() rows have so the owner's Sales page can merge
     * the two lists and reuse SaleModel's summarize()/staffBreakdown().
     * Credit revenue is recognized from order_payments in the period the money
     * was received. The invoice total and live balance remain unchanged.
     */
    public function forTenant(int $limit = 1000, string $period = 'all', ?int $staffId = null): array
    {
        $tid = \TenantContext::tenantId();
        $payPeriod = $this->paymentPeriodSql($period);
        $orderPeriod = $this->orderPeriodSql($period);
        $activitySql = $period === 'all'
            ? ''
            : "AND (({$orderPeriod}) OR COALESCE(pa.period_paid, 0) > 0)";
        $staffSql = $staffId !== null ? "AND o.opened_by = :staff_id" : '';
        $stmt = $this->db->prepare(
            "SELECT o.*, u.username AS staff_name,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
                    CASE
                        WHEN COALESCE(pa.all_paid, 0) = 0 AND o.status = 'paid' AND ({$orderPeriod}) THEN o.total
                        ELSE COALESCE(pa.period_paid, 0)
                    END AS recognized_revenue,
                    CASE WHEN COALESCE(pa.all_paid, 0) = 0 AND o.status = 'paid' AND ({$orderPeriod})
                         THEN COALESCE(o.cash_amount, CASE WHEN o.payment_method = 'cash' THEN o.total ELSE 0 END)
                         ELSE COALESCE(pa.period_cash, 0) END AS recognized_cash,
                    CASE WHEN COALESCE(pa.all_paid, 0) = 0 AND o.status = 'paid' AND ({$orderPeriod})
                         THEN COALESCE(o.mpesa_amount, CASE WHEN o.payment_method = 'mpesa' THEN o.total ELSE 0 END)
                         ELSE COALESCE(pa.period_mpesa, 0) END AS recognized_mpesa,
                    CASE WHEN COALESCE(pa.all_paid, 0) = 0 AND o.status = 'paid' AND ({$orderPeriod})
                         THEN CASE WHEN o.payment_method IN ('card','bank','sacco','paybill') THEN o.total ELSE 0 END
                         ELSE COALESCE(pa.period_other, 0) END AS recognized_other,
                    pa.last_period_payment_at
               FROM orders o
          LEFT JOIN users u ON u.id = o.opened_by
          LEFT JOIN (
                    SELECT op.tenant_id, op.order_id,
                           SUM(op.amount) AS all_paid,
                           SUM(CASE WHEN {$payPeriod} THEN op.amount ELSE 0 END) AS period_paid,
                           SUM(CASE WHEN {$payPeriod} AND op.method = 'cash' THEN op.amount ELSE 0 END) AS period_cash,
                           SUM(CASE WHEN {$payPeriod} AND op.method = 'mpesa' THEN op.amount ELSE 0 END) AS period_mpesa,
                           SUM(CASE WHEN {$payPeriod} AND op.method NOT IN ('cash','mpesa') THEN op.amount ELSE 0 END) AS period_other,
                           MAX(CASE WHEN {$payPeriod} THEN op.created_at ELSE NULL END) AS last_period_payment_at
                      FROM order_payments op
                  GROUP BY op.tenant_id, op.order_id
               ) pa ON pa.order_id = o.id AND pa.tenant_id = o.tenant_id
              WHERE o.tenant_id = :tid
                AND o.status IN ('paid', 'open')
                AND COALESCE(o.total, 0) > 0
                AND (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) > 0
                {$activitySql} {$staffSql}
           ORDER BY COALESCE(pa.last_period_payment_at, o.created_at) DESC, o.id DESC
              LIMIT :lim"
        );
        $stmt->bindValue(':tid', $tid, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        if ($staffId !== null) { $stmt->bindValue(':staff_id', $staffId, \PDO::PARAM_INT); }
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $paid = max(0, (float) ($r['amount_paid'] ?? 0));
            $due = (float) ($r['amount_due'] ?? 0);
            if ($due <= 0.0001 && ($r['status'] ?? '') === 'open') {
                $due = max(0, round((float) ($r['total'] ?? 0) - $paid, 2));
            }
            if (($r['status'] ?? '') === 'paid') {
                $due = 0;
                if ($paid <= 0.0001) {
                    $paid = (float) ($r['total'] ?? 0);
                }
            }
            $status = (string) ($r['payment_status'] ?? '');
            if ($status === '' || $status === 'credit') {
                if ($due <= 0.0001) {
                    $status = 'paid';
                } elseif ($paid > 0.0001) {
                    $status = 'part_paid';
                } else {
                    $status = 'credit';
                }
            }
            $r['created_at']       = $r['last_period_payment_at'] ?: $r['created_at'];
            $r['customer_name']    = $r['table_name'];
            $r['sale_type']        = $r['sale_type'] ?? 'retail';
            $r['payment_status']   = $status;
            $r['payment_method']   = $r['payment_method'] ?: ($due > 0.0001 ? 'credit' : 'cash');
            $r['discount_amount']  = (float) ($r['discount_amount'] ?? 0);
            $r['additional_charges'] = (float) ($r['additional_charges'] ?? 0);
            $r['amount_due']       = $due;
            $r['amount_paid']      = $paid;
            $r['_recognized_revenue'] = round((float) ($r['recognized_revenue'] ?? 0), 2);
            $r['_recognized_cash'] = round((float) ($r['recognized_cash'] ?? 0), 2);
            $r['_recognized_mpesa'] = round((float) ($r['recognized_mpesa'] ?? 0), 2);
            $r['_recognized_other'] = round((float) ($r['recognized_other'] ?? 0), 2);
            $r['receipt_url']      = 'staff/orders/receipt.php?id=' . (int) $r['id'];
            $r['source']           = 'order';
        }
        unset($r);

        return $rows;
    }

    /**
     * Paid + credit orders for one tenant on one exact Y-m-d date — CLI-safe (explicit
     * tenant id, no TenantContext), mirrors SaleModel::forTenantId(). Used by
     * the daily sales report (page, PDF, email cron).
     */
    public function forTenantOnDate(int $tenantId, string $date): array
    {
        $date = preg_replace('/[^0-9-]/', '', $date);
        $stmt = $this->db->prepare(
            "SELECT o.*, u.username AS staff_name,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
                    CASE
                        WHEN COALESCE(pa.all_paid, 0) = 0 AND o.status = 'paid' AND DATE(o.created_at) = ? THEN o.total
                        ELSE COALESCE(pa.period_paid, 0)
                    END AS recognized_revenue,
                    CASE WHEN COALESCE(pa.all_paid, 0) = 0 AND o.status = 'paid' AND DATE(o.created_at) = ?
                         THEN COALESCE(o.cash_amount, CASE WHEN o.payment_method = 'cash' THEN o.total ELSE 0 END)
                         ELSE COALESCE(pa.period_cash, 0) END AS recognized_cash,
                    CASE WHEN COALESCE(pa.all_paid, 0) = 0 AND o.status = 'paid' AND DATE(o.created_at) = ?
                         THEN COALESCE(o.mpesa_amount, CASE WHEN o.payment_method = 'mpesa' THEN o.total ELSE 0 END)
                         ELSE COALESCE(pa.period_mpesa, 0) END AS recognized_mpesa,
                    CASE WHEN COALESCE(pa.all_paid, 0) = 0 AND o.status = 'paid' AND DATE(o.created_at) = ?
                         THEN CASE WHEN o.payment_method IN ('card','bank','sacco','paybill') THEN o.total ELSE 0 END
                         ELSE COALESCE(pa.period_other, 0) END AS recognized_other,
                    pa.last_period_payment_at
               FROM orders o
          LEFT JOIN users u ON u.id = o.opened_by
          LEFT JOIN (
                    SELECT op.tenant_id, op.order_id,
                           SUM(op.amount) AS all_paid,
                           SUM(CASE WHEN DATE(op.created_at) = ? THEN op.amount ELSE 0 END) AS period_paid,
                           SUM(CASE WHEN DATE(op.created_at) = ? AND op.method = 'cash' THEN op.amount ELSE 0 END) AS period_cash,
                           SUM(CASE WHEN DATE(op.created_at) = ? AND op.method = 'mpesa' THEN op.amount ELSE 0 END) AS period_mpesa,
                           SUM(CASE WHEN DATE(op.created_at) = ? AND op.method NOT IN ('cash','mpesa') THEN op.amount ELSE 0 END) AS period_other,
                           MAX(CASE WHEN DATE(op.created_at) = ? THEN op.created_at ELSE NULL END) AS last_period_payment_at
                      FROM order_payments op
                  GROUP BY op.tenant_id, op.order_id
               ) pa ON pa.order_id = o.id AND pa.tenant_id = o.tenant_id
              WHERE o.tenant_id = ?
                AND o.status IN ('paid', 'open')
                AND COALESCE(o.total, 0) > 0
                AND (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) > 0
                AND (DATE(o.created_at) = ? OR COALESCE(pa.period_paid, 0) > 0)
           ORDER BY COALESCE(pa.last_period_payment_at, o.created_at) ASC, o.id ASC"
        );
        $stmt->execute([$date, $date, $date, $date, $date, $date, $date, $date, $date, $tenantId, $date]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $paid = max(0, (float) ($r['amount_paid'] ?? 0));
            $due = (float) ($r['amount_due'] ?? 0);
            if ($due <= 0.0001 && ($r['status'] ?? '') === 'open') {
                $due = max(0, round((float) ($r['total'] ?? 0) - $paid, 2));
            }
            if (($r['status'] ?? '') === 'paid') {
                $due = 0;
                if ($paid <= 0.0001) {
                    $paid = (float) ($r['total'] ?? 0);
                }
            }
            $status = (string) ($r['payment_status'] ?? '');
            if ($status === '' || $status === 'credit') {
                if ($due <= 0.0001) {
                    $status = 'paid';
                } elseif ($paid > 0.0001) {
                    $status = 'part_paid';
                } else {
                    $status = 'credit';
                }
            }
            $r['created_at']      = $r['last_period_payment_at'] ?: $r['created_at'];
            $r['customer_name']   = $r['table_name'];
            $r['sale_type']       = $r['sale_type'] ?? 'retail';
            $r['payment_status']  = $status;
            $r['payment_method']  = $r['payment_method'] ?: ($due > 0.0001 ? 'credit' : 'cash');
            $r['discount_amount'] = (float) ($r['discount_amount'] ?? 0);
            $r['additional_charges'] = (float) ($r['additional_charges'] ?? 0);
            $r['amount_due']      = $due;
            $r['amount_paid']     = $paid;
            $r['_recognized_revenue'] = round((float) ($r['recognized_revenue'] ?? 0), 2);
            $r['_recognized_cash'] = round((float) ($r['recognized_cash'] ?? 0), 2);
            $r['_recognized_mpesa'] = round((float) ($r['recognized_mpesa'] ?? 0), 2);
            $r['_recognized_other'] = round((float) ($r['recognized_other'] ?? 0), 2);
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
            "SELECT oi.product_name,
                    SUM(GREATEST(oi.quantity - COALESCE(ret.returned_quantity,0), 0)
                        * LEAST(1, COALESCE(pa.period_paid, CASE WHEN COALESCE(pa.all_paid,0) = 0 AND o.status = 'paid' AND DATE(o.created_at) = ? THEN o.total ELSE 0 END) / NULLIF(o.total,0))) AS qty,
                    SUM(oi.line_total
                        * LEAST(1, COALESCE(pa.period_paid, CASE WHEN COALESCE(pa.all_paid,0) = 0 AND o.status = 'paid' AND DATE(o.created_at) = ? THEN o.total ELSE 0 END) / NULLIF(o.total,0))) AS revenue
               FROM order_items oi
               JOIN orders o ON o.id = oi.order_id AND o.tenant_id = oi.tenant_id
          LEFT JOIN (
                    SELECT op.tenant_id, op.order_id, SUM(op.amount) AS all_paid,
                           SUM(CASE WHEN DATE(op.created_at) = ? THEN op.amount ELSE 0 END) AS period_paid
                      FROM order_payments op
                  GROUP BY op.tenant_id, op.order_id
               ) pa ON pa.order_id = o.id AND pa.tenant_id = o.tenant_id
          LEFT JOIN (
                    SELECT tenant_id, source_item_id, SUM(returned_quantity) AS returned_quantity
                      FROM product_returns
                     WHERE source_type = 'order'
                  GROUP BY tenant_id, source_item_id
               ) ret ON ret.tenant_id = oi.tenant_id AND ret.source_item_id = oi.id
              WHERE oi.tenant_id = ? AND o.status IN ('paid','open') AND COALESCE(o.total,0) > 0
                AND (COALESCE(pa.period_paid,0) > 0 OR (COALESCE(pa.all_paid,0) = 0 AND o.status = 'paid' AND DATE(o.created_at) = ?))
           GROUP BY oi.product_name
           ORDER BY revenue DESC"
        );
        $stmt->execute([$date, $date, $date, $tenantId, $date]);
        return $stmt->fetchAll();
    }

    /** Per-product revenue/cost/profit from paid tabs — mirrors SaleModel::productProfit(). */
    public function productProfit(string $period = 'all', string $costCol = 'buying_price'): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) { return []; }

        $this->ensureColumn('products', 'package_buying_price', "ALTER TABLE `products` ADD COLUMN `package_buying_price` DECIMAL(12,2) NULL AFTER `pack_price`");
        $payPeriod = $this->paymentPeriodSql($period);
        $orderPeriod = $this->orderPeriodSql($period);
        $recognizedPay = "(CASE
            WHEN COALESCE(pa.all_paid, 0) = 0 AND o.status = 'paid' AND ({$orderPeriod}) THEN o.total
            ELSE COALESCE(pa.period_paid, 0)
        END)";
        $paidRatio = "LEAST(1, {$recognizedPay} / NULLIF(o.total, 0))";

        $sql = "SELECT oi.product_id,
                       MAX(oi.product_name) AS product_name,
                       SUM(GREATEST(oi.quantity - COALESCE(ret.returned_quantity,0), 0) * {$paidRatio}) AS qty,
                       SUM(oi.line_total * {$paidRatio}) AS revenue,
                       SUM(
                           CASE
                               WHEN oi.price_type = 'wholesale'
                                    AND COALESCE(p.units_per_pack, 1) > 1
                                    AND COALESCE(p.pack_unit, '') <> ''
                                    AND p.package_buying_price IS NOT NULL
                                   THEN (GREATEST(oi.quantity - COALESCE(ret.returned_quantity,0), 0) / p.units_per_pack) * p.package_buying_price * {$paidRatio}
                               ELSE GREATEST(oi.quantity - COALESCE(ret.returned_quantity,0), 0) * COALESCE(p.`{$costCol}`, 0) * {$paidRatio}
                           END
                       ) AS cost,
                       SUM(CASE WHEN oi.price_type = 'retail' THEN oi.line_total * {$paidRatio} ELSE 0 END)
                       - SUM(CASE WHEN oi.price_type = 'retail' THEN GREATEST(oi.quantity - COALESCE(ret.returned_quantity,0), 0) * COALESCE(p.`{$costCol}`, 0) * {$paidRatio} ELSE 0 END) AS retail_profit,
                       SUM(CASE WHEN oi.price_type = 'wholesale' THEN oi.line_total * {$paidRatio} ELSE 0 END)
                       - SUM(CASE WHEN oi.price_type = 'wholesale' THEN
                           CASE
                               WHEN COALESCE(p.units_per_pack, 1) > 1
                                    AND COALESCE(p.pack_unit, '') <> ''
                                    AND p.package_buying_price IS NOT NULL
                                   THEN (GREATEST(oi.quantity - COALESCE(ret.returned_quantity,0), 0) / p.units_per_pack) * p.package_buying_price * {$paidRatio}
                               ELSE GREATEST(oi.quantity - COALESCE(ret.returned_quantity,0), 0) * COALESCE(p.`{$costCol}`, 0) * {$paidRatio}
                           END
                         ELSE 0 END) AS wholesale_profit
                  FROM order_items oi
                  JOIN orders o    ON o.id = oi.order_id AND o.tenant_id = oi.tenant_id
             LEFT JOIN (
                    SELECT op.tenant_id, op.order_id,
                           SUM(op.amount) AS all_paid,
                           SUM(CASE WHEN {$payPeriod} THEN op.amount ELSE 0 END) AS period_paid
                      FROM order_payments op
                  GROUP BY op.tenant_id, op.order_id
             ) pa ON pa.order_id = o.id AND pa.tenant_id = o.tenant_id
             LEFT JOIN products p  ON p.id = oi.product_id AND p.tenant_id = oi.tenant_id
             LEFT JOIN (
                    SELECT tenant_id, source_item_id, SUM(returned_quantity) AS returned_quantity
                      FROM product_returns
                     WHERE source_type = 'order'
                  GROUP BY tenant_id, source_item_id
             ) ret ON ret.tenant_id = oi.tenant_id AND ret.source_item_id = oi.id
                 WHERE oi.tenant_id = ? AND o.status IN ('paid','open') AND COALESCE(o.total,0) > 0
                   AND {$recognizedPay} > 0
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
            $r['retail_profit'] = round((float) ($r['retail_profit'] ?? 0), 2);
            $r['wholesale_profit'] = round((float) ($r['wholesale_profit'] ?? 0), 2);
            $r['profit']     = round($rev - $cost, 2);
            $r['margin']     = $rev > 0 ? round(($rev - $cost) / $rev * 100, 1) : 0.0;
        }
        unset($r);

        return $rows;
    }
}
