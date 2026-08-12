<?php
// public/staff/orders/view.php?id=N — tab detail: add rounds, void, and for
// a credit sale (open, unpaid tab with a customer email on file) email them
// an invoice or a delivery note. Settling payment happens on the Payments
// page (staff/payments/), keyed by invoice number — kept separate so
// servers and reception each have one job. Reached from both the staff till
// and the owner's side (super/orders/view.php thin-wraps this) — content
// adapts by role rather than duplicating the page.
require_once __DIR__ . '/../../../app/app.php';
require_once ROOT_PATH . '/app/services/emails/order_invoice_email.php';
require_once ROOT_PATH . '/app/services/emails/order_delivery_note_email.php';
require_once ROOT_PATH . '/app/services/emails/order_thank_you_email.php';
require_once ROOT_PATH . '/app/services/emails/order_remembrance_email.php';
PageGuard::capability(Capabilities::SALES_RECORD);

$pdo = Database::pdo();
$O = new Models\OrderModel($pdo);

$id = (int) ($_GET['id'] ?? 0);
$order = $id > 0 ? $O->find($id) : null;
if (!$order) {
    http_response_code(404);
    echo 'Tab not found.';
    exit;
}

$isStaffViewer = TenantContext::role() === 'staff';
$ordersBase  = $isStaffViewer ? public_url('staff/orders/') : public_url('super/orders/');
$viewUrl     = ($isStaffViewer ? public_url('staff/orders/view.php') : public_url('super/orders/view.php')) . '?id=' . $id;
$receiptUrl  = ($isStaffViewer ? public_url('staff/orders/receipt.php') : public_url('super/orders/receipt.php')) . '?id=' . $id;
$editUrl     = ($isStaffViewer ? public_url('staff/invoices/edit.php') : public_url('super/invoices/edit.php')) . '?id=' . $id;
$paymentsUrl = ($isStaffViewer ? public_url('staff/payments/') : public_url('super/payments/')) . '?receipt=' . urlencode($order['receipt_number'] ?? '') . '&deposit=1';

$P = new Models\ProductModel($pdo);
$products = $order['status'] === 'open' ? $P->sellable() : [];

$error = '';
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_items') {
        $cart = json_decode($_POST['cart'] ?? '[]', true);
        $items = [];
        if (is_array($cart)) {
            foreach ($cart as $c) {
                $items[] = ['product_id' => (int) ($c['product_id'] ?? 0), 'quantity' => (float) ($c['quantity'] ?? 0)];
            }
        }
        $res = $O->addItems($id, $items, TenantContext::userId(), (float) ($_POST['credit_override_amount'] ?? 0));
        if ($res['ok']) {
            $_SESSION['flash']['success'] = 'Added to the tab.';
            header('Location: ' . $viewUrl);
            exit;
        }
        $error = $res['errors']['_'] ?? 'Could not add those products.';

    } elseif ($action === 'void') {
        $res = $O->void($id, TenantContext::userId());
        $_SESSION['flash'][$res['ok'] ? 'success' : 'error'] = $res['ok'] ? 'Tab voided; stock restored.' : ($res['error'] ?? 'Could not void this tab.');
        header('Location: ' . $ordersBase);
        exit;

    } elseif ($action === 'update_contact') {
        $res = $O->updateCustomerContact($id, trim($_POST['customer_email'] ?? ''), trim($_POST['customer_phone'] ?? ''));
        if ($res['ok']) { $notice = "Customer contact saved."; }
        else { $error = $res['error']; }

    } elseif (in_array($action, ['send_invoice', 'send_delivery_note', 'send_thank_you', 'send_remembrance'], true)) {
        if (empty($order['customer_email'])) {
            $error = 'Add a customer email first.';
        } else {
            $tenant = (new Models\TenantModel($pdo))->find(TenantContext::tenantId());
            $shop = [
                'name' => $tenant['name'] ?? 'the shop',
                'phone' => $tenant['phone'] ?? '',
                'po_box' => $tenant['po_box'] ?? '',
                'email' => $tenant['business_email'] ?? '',
                'address' => $tenant['address'] ?? '',
                'kra_pin' => $tenant['kra_pin'] ?? '',
                'logo' => Branding::tenantLogo($tenant ?: []),
                'payment_credentials' => $tenant['payment_credentials'] ?? '',
            ];
            $items = $O->items($id);
            if ($action === 'send_invoice') {
                $msg = build_order_invoice_email($order, $items, $shop);
            } elseif ($action === 'send_delivery_note') {
                $msg = build_order_delivery_note_email($order, $items, $shop);
            } elseif ($action === 'send_thank_you') {
                $msg = build_order_thank_you_email($order, $items, $shop);
            } else {
                $msg = build_order_remembrance_email($order, $items, $shop);
            }
            if ((new MailService())->send($order['customer_email'], $msg['subject'], $msg['html'], $msg['text'])) {
                if ($action === 'send_invoice') { $O->markInvoiceSent($id); }
                elseif ($action === 'send_delivery_note') { $O->markDeliveryNoteSent($id); }
                elseif ($action === 'send_thank_you') { $O->markThankYouSent($id); }
                else { $O->markRemembranceSent($id); }
                $labels = [
                    'send_invoice' => 'Invoice',
                    'send_delivery_note' => 'Delivery note',
                    'send_thank_you' => 'Thank-you note',
                    'send_remembrance' => 'Remembrance note',
                ];
                $notice = ($labels[$action] ?? 'Message') . ' emailed to ' . $order['customer_email'] . '.';
            } else {
                $error = 'Could not send the email: ' . (MailService::lastError() ?: 'unknown error');
            }
        }
    }

    $order = $O->find($id); // re-fetch in case totals/contact changed
}

$items = $O->items($id);
$amountPaid = max(0, (float) ($order['amount_paid'] ?? 0));
$amountDue = (float) ($order['amount_due'] ?? 0);
if ($order['status'] === 'open' && $amountDue <= 0.0001) {
    $amountDue = max(0, (float) $order['total'] - $amountPaid);
}
$page_title = 'Tab — ' . $order['table_name'];
$statusBadge = [
    'open' => '<span class="badge bg-warning text-dark">Unpaid</span>',
    'paid' => '<span class="badge bg-success">Paid</span>',
    'void' => '<span class="badge bg-secondary">Voided</span>',
][$order['status']] ?? '';
ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h1 class="h5 mb-1 fw-bold"><?php echo htmlspecialchars($order['table_name']); ?> <?php echo $statusBadge; ?></h1>
    <div class="small text-muted">Invoice <?php echo htmlspecialchars($order['receipt_number']); ?> · opened <?php echo date('j M Y, g:i a', strtotime($order['created_at'])); ?></div>
    <?php if (!empty($order['credit_due_at']) || !empty($order['credit_duration_days'])): ?>
      <div class="small <?php echo !empty($order['credit_due_at']) && strtotime($order['credit_due_at']) < time() && $order['status'] === 'open' ? 'text-danger' : 'text-muted'; ?>">
        Credit duration:
        <?php if (!empty($order['credit_duration_days'])): ?>
          <?php echo (int) $order['credit_duration_days']; ?> day<?php echo (int) $order['credit_duration_days'] === 1 ? '' : 's'; ?>
        <?php endif; ?>
        <?php if (!empty($order['credit_due_at'])): ?>
          · due <?php echo date('j M Y', strtotime($order['credit_due_at'])); ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $ordersBase; ?>"><i class="fas fa-arrow-left me-1"></i>Credit sales</a>
    <?php if ($order['status'] === 'open'): ?>
      <a class="btn btn-sm btn-outline-primary" href="<?php echo $editUrl; ?>"><i class="fas fa-pen me-1"></i>Edit items</a>
      <a class="btn btn-sm btn-success" href="<?php echo $paymentsUrl; ?>"><i class="fas fa-hand-holding-dollar me-1"></i>Deposit</a>
    <?php endif; ?>
    <a class="btn btn-sm btn-outline-primary" href="<?php echo $receiptUrl; ?>"><i class="fas fa-receipt me-1"></i>Receipt</a>
  </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($notice): ?><div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>

<?php if ($order['status'] === 'open'): ?>
  <div class="alert alert-info py-2 small">
    <i class="fas fa-circle-info me-1"></i>
    Use <strong>Edit items</strong> to add or remove products, or <strong>Deposit</strong> when the customer pays part of the balance (a receipt prints automatically).
  </div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-12 col-lg-6">
    <div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
      <div class="card-body p-4">
        <h2 class="h6 mb-3">Items so far</h2>
        <?php if (!$items): ?>
          <div class="text-muted small">No items yet.</div>
        <?php else: ?>
          <?php foreach ($items as $it): ?>
            <div class="d-flex justify-content-between border-bottom py-2">
              <div>
                <div class="fw-semibold" style="font-size:.9rem;"><?php echo htmlspecialchars($it['product_name']); ?></div>
                <small class="text-muted">KES <?php echo number_format((float) $it['unit_price'], 0); ?> × <?php echo rtrim(rtrim(number_format((float) $it['quantity'], 2), '0'), '.'); ?></small>
              </div>
              <div class="fw-bold">KES <?php echo number_format((float) $it['line_total'], 0); ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
        <?php if ((float) $order['discount_amount'] > 0): ?>
          <div class="d-flex justify-content-between pt-2 text-muted small">
            <span>Subtotal</span><span>KES <?php echo number_format((float) $order['subtotal'], 0); ?></span>
          </div>
          <div class="d-flex justify-content-between text-muted small">
            <span>Discount</span><span>− KES <?php echo number_format((float) $order['discount_amount'], 0); ?></span>
          </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between pt-2">
          <span class="fw-semibold">Total</span>
          <span class="fw-bold fs-5">KES <?php echo number_format((float) $order['total'], 0); ?></span>
        </div>
        <?php if ($amountPaid > 0 || $order['status'] === 'open'): ?>
          <div class="d-flex justify-content-between text-muted small">
            <span>Paid so far</span>
            <span>KES <?php echo number_format($amountPaid, 0); ?></span>
          </div>
          <div class="d-flex justify-content-between">
            <span class="fw-semibold">Still owed</span>
            <span class="fw-bold text-danger">KES <?php echo number_format($order['status'] === 'paid' ? 0 : $amountDue, 0); ?></span>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($order['status'] === 'open'): ?>
    <form method="post" class="d-inline" onsubmit="return confirm('Void this credit sale? Stock will be restored.');">
      <input type="hidden" name="action" value="void">
      <button class="btn btn-outline-danger btn-sm"><i class="fas fa-ban me-1"></i>Void sale</button>
    </form>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4 mt-4" style="border-radius:14px;">
      <div class="card-body p-4">
        <h2 class="h6 mb-1">Credit sale contact</h2>
        <p class="text-muted small mb-3">Add their email to send an invoice or a delivery note by email — either staff or the owner can do this.</p>
        <form method="post" class="row g-2 mb-3">
          <input type="hidden" name="action" value="update_contact">
          <div class="col-12">
            <label class="form-label small mb-1">Email</label>
            <input type="email" name="customer_email" class="form-control form-control-sm" value="<?php echo htmlspecialchars($order['customer_email'] ?? ''); ?>" placeholder="customer@email.com">
          </div>
          <div class="col-12">
            <label class="form-label small mb-1">Phone <span class="text-muted">(optional)</span></label>
            <input type="text" name="customer_phone" class="form-control form-control-sm" value="<?php echo htmlspecialchars($order['customer_phone'] ?? ''); ?>">
          </div>
          <div class="col-12">
            <button class="btn btn-sm btn-outline-secondary">Save contact</button>
          </div>
        </form>
        <div class="d-flex flex-wrap gap-2">
          <form method="post" class="d-inline">
            <input type="hidden" name="action" value="send_invoice">
            <button class="btn btn-sm btn-primary" <?php echo empty($order['customer_email']) ? 'disabled' : ''; ?>><i class="fas fa-file-invoice me-1"></i>Email invoice</button>
          </form>
          <form method="post" class="d-inline">
            <input type="hidden" name="action" value="send_delivery_note">
            <button class="btn btn-sm btn-outline-primary" <?php echo empty($order['customer_email']) ? 'disabled' : ''; ?>><i class="fas fa-truck-ramp-box me-1"></i>Email delivery note</button>
          </form>
          <form method="post" class="d-inline">
            <input type="hidden" name="action" value="send_thank_you">
            <button class="btn btn-sm btn-outline-success" <?php echo empty($order['customer_email']) ? 'disabled' : ''; ?>><i class="fas fa-heart me-1"></i>Thank-you note</button>
          </form>
          <form method="post" class="d-inline">
            <input type="hidden" name="action" value="send_remembrance">
            <button class="btn btn-sm btn-outline-secondary" <?php echo empty($order['customer_email']) ? 'disabled' : ''; ?>><i class="fas fa-envelope-open-text me-1"></i>Remembrance note</button>
          </form>
        </div>
        <?php if (!empty($order['invoice_sent_at']) || !empty($order['delivery_note_sent_at'])): ?>
          <div class="text-muted small mt-2">
            <?php if (!empty($order['invoice_sent_at'])): ?>Invoice sent <?php echo date('j M, g:i a', strtotime($order['invoice_sent_at'])); ?>.<?php endif; ?>
            <?php if (!empty($order['delivery_note_sent_at'])): ?> Delivery note sent <?php echo date('j M, g:i a', strtotime($order['delivery_note_sent_at'])); ?>.<?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <?php if ($order['status'] === 'open'): ?>
    <div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
      <div class="card-body p-4">
        <h2 class="h6 mb-3">Add more items</h2>
        <?php if (!$products): ?>
          <div class="text-muted small">No products in stock.</div>
        <?php else: ?>
        <form method="post" id="addForm">
          <input type="hidden" name="action" value="add_items">
          <input type="hidden" name="cart" id="cartInput" value="">
          <div class="position-relative mb-2">
            <input type="text" id="search" class="form-control" placeholder="Search products…" autocomplete="off">
          </div>
          <div id="productList" style="max-height:280px;overflow-y:auto;">
            <?php foreach ($products as $p):
                $price = (float) ($p['retail_price'] ?: $p['selling_price']);
                $sz = Models\ProductModel::sizeLabel($p);
            ?>
              <button type="button" class="prod btn w-100 text-start border rounded mb-2 p-2 d-flex justify-content-between align-items-center"
                      data-id="<?php echo (int) $p['id']; ?>"
                      data-name="<?php echo htmlspecialchars($p['name'] . ($sz ? " ({$sz})" : ''), ENT_QUOTES); ?>"
                      data-price="<?php echo $price; ?>" data-stock="<?php echo (float) $p['quantity']; ?>">
                <span><?php echo htmlspecialchars($p['name']); ?><?php echo $sz ? ' <small class="text-muted">(' . htmlspecialchars($sz) . ')</small>' : ''; ?></span>
                <span class="fw-bold text-primary">KES <?php echo number_format($price, 0); ?></span>
              </button>
            <?php endforeach; ?>
          </div>
          <div id="cartRows" class="mt-3"></div>
          <div class="d-flex justify-content-between fw-semibold mt-2"><span>Round total</span><span>KES <span id="roundTotal">0</span></span></div>
          <div class="mt-2">
            <label class="form-label small mb-1">Loyal customer credit override</label>
            <input type="number" step="0.01" min="0" name="credit_override_amount" class="form-control form-control-sm" placeholder="Optional higher product limit">
          </div>
          <button type="submit" class="btn btn-primary w-100 mt-3" id="addBtn" disabled>Add to tab</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($order['status'] === 'open' && $products): ?>
<script>
var PRODUCTS = {};
document.querySelectorAll('.prod').forEach(function (b) {
    PRODUCTS[b.dataset.id] = { name: b.dataset.name, price: parseFloat(b.dataset.price), stock: parseFloat(b.dataset.stock) };
});
var cart = {};
function money(n) { return n.toLocaleString('en-KE', {maximumFractionDigits:0}); }
function setQty(id, val) {
    var p = PRODUCTS[id]; val = Math.round((parseFloat(val) || 0) * 100) / 100;
    if (val <= 0) { delete cart[id]; render(); return; }
    if (val > p.stock) { val = p.stock; }
    cart[id] = val; render();
}
function syncCart() {
    var ids = Object.keys(cart), total = 0;
    ids.forEach(function (id) { total += PRODUCTS[id].price * cart[id]; });
    document.getElementById('roundTotal').textContent = money(total);
    document.getElementById('addBtn').disabled = ids.length === 0;
    document.getElementById('cartInput').value = JSON.stringify(ids.map(function (id) { return { product_id: parseInt(id, 10), quantity: cart[id] }; }));
}
function render() {
    var wrap = document.getElementById('cartRows'), ids = Object.keys(cart), total = 0;
    wrap.innerHTML = '';
    ids.forEach(function (id) {
        var p = PRODUCTS[id], qty = cart[id]; total += p.price * qty;
        var row = document.createElement('div');
        row.className = 'd-flex justify-content-between align-items-center border-bottom py-1';
        row.innerHTML = '<span style="font-size:.85rem;">' + p.name + '</span>'
          + '<span class="d-flex align-items-center gap-1"><button type="button" class="btn btn-sm btn-outline-secondary" data-dec="' + id + '">−</button>'
          + '<input type="number" step="0.01" min="0" max="' + p.stock + '" data-qty="' + id + '" class="form-control form-control-sm text-center" style="width:76px;" value="' + qty + '">'
          + '<button type="button" class="btn btn-sm btn-outline-secondary" data-inc="' + id + '">+</button></span>';
        wrap.appendChild(row);
    });
    syncCart();
}
document.querySelectorAll('.prod').forEach(function (b) { b.addEventListener('click', function () { setQty(b.dataset.id, (cart[b.dataset.id] || 0) + 1); }); });
document.getElementById('cartRows').addEventListener('click', function (e) {
    var t = e.target.closest('button'); if (!t) return;
    if (t.dataset.inc) setQty(t.dataset.inc, (cart[t.dataset.inc] || 0) + 1);
    else if (t.dataset.dec) setQty(t.dataset.dec, (cart[t.dataset.dec] || 0) - 1);
});
document.getElementById('cartRows').addEventListener('input', function (e) {
    var input = e.target.closest('[data-qty]');
    if (!input) return;
    var id = input.dataset.qty;
    var p = PRODUCTS[id]; if (!p) return;
    var val = Math.round((parseFloat(input.value) || 0) * 100) / 100;
    if (val > p.stock) { val = p.stock; input.value = val; }
    if (val > 0) { cart[id] = val; } else { delete cart[id]; }
    syncCart();
});
document.getElementById('cartRows').addEventListener('change', function (e) {
    var input = e.target.closest('[data-qty]');
    if (input) setQty(input.dataset.qty, input.value);
});
var search = document.getElementById('search');
search.addEventListener('input', function () {
    var q = search.value.toLowerCase().trim();
    document.querySelectorAll('.prod').forEach(function (b) {
        b.style.display = (q === '' || b.dataset.name.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
    });
});
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
$__layout = $isStaffViewer ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
