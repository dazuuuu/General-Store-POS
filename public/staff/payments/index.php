<?php
// public/staff/payments/index.php
// Reception/payments desk: look up an invoice by its number and record the
// payment (cash, M-Pesa, or split) once the customer pays.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::PAYMENTS_PROCESS);

$pdo = Database::pdo();
$O = new Models\OrderModel($pdo);

$error = '';
$receiptQuery = trim($_GET['receipt'] ?? $_POST['receipt_number'] ?? '');
$order = $receiptQuery !== '' ? $O->findByReceipt($receiptQuery) : null;

if ($receiptQuery !== '' && !$order) {
    $error = 'No invoice found with that number. Check it and try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'settle' && $order) {
    if ($order['status'] !== 'open') {
        $error = 'This invoice is not open for payment.';
    } else {
        $res = $O->markPaid((int) $order['id'], [
            'method'          => $_POST['payment_method'] ?? '',
            'cash_amount'     => $_POST['cash_amount'] ?? 0,
            'mpesa_amount'    => $_POST['mpesa_amount'] ?? 0,
            'amount_tendered' => $_POST['amount_tendered'] ?? 0,
        ], TenantContext::userId());
        if ($res['ok']) {
            $_SESSION['flash']['success'] = 'Payment recorded for ' . $order['receipt_number'] . '.';
            header('Location: ' . public_url('staff/orders/receipt.php?id=' . (int) $order['id']));
            exit;
        }
        $error = $res['error'] ?? 'Could not record the payment.';
    }
}

$items = $order ? $O->items((int) $order['id']) : [];
$page_title = 'Payments';
ob_start();
?>
<h1 class="h5 mb-4 fw-bold"><i class="fas fa-cash-register me-2 text-primary"></i>Payments</h1>

<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <div class="card-body p-4">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-12 col-sm-8">
        <label class="form-label small mb-1">Invoice number</label>
        <input type="text" name="receipt" class="form-control form-control-lg text-uppercase" placeholder="e.g. ORD-000123"
               value="<?php echo htmlspecialchars($receiptQuery); ?>" autofocus>
      </div>
      <div class="col-12 col-sm-4">
        <button class="btn btn-primary btn-lg w-100"><i class="fas fa-magnifying-glass me-1"></i>Find</button>
      </div>
    </form>
  </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if ($order):
    $statusBadge = [
        'open' => '<span class="badge bg-warning text-dark">Unpaid</span>',
        'paid' => '<span class="badge bg-success">Paid</span>',
        'void' => '<span class="badge bg-secondary">Voided</span>',
    ][$order['status']] ?? '';
?>
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
      <div>
        <div class="fw-bold fs-5"><?php echo htmlspecialchars($order['table_name']); ?> <?php echo $statusBadge; ?></div>
        <div class="text-muted small">Invoice <?php echo htmlspecialchars($order['receipt_number']); ?> · opened by <?php echo htmlspecialchars($order['opened_by_name'] ?? '—'); ?> · <?php echo date('j M Y, g:i a', strtotime($order['created_at'])); ?></div>
      </div>
      <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url('staff/orders/receipt.php?id=' . (int) $order['id']); ?>"><i class="fas fa-receipt me-1"></i>Receipt</a>
    </div>

    <?php foreach ($items as $it): ?>
      <div class="d-flex justify-content-between border-bottom py-2">
        <div>
          <div class="fw-semibold" style="font-size:.9rem;"><?php echo htmlspecialchars($it['product_name']); ?></div>
          <small class="text-muted">KES <?php echo number_format((float) $it['unit_price'], 0); ?> × <?php echo rtrim(rtrim(number_format((float) $it['quantity'], 2), '0'), '.'); ?></small>
        </div>
        <div class="fw-bold">KES <?php echo number_format((float) $it['line_total'], 0); ?></div>
      </div>
    <?php endforeach; ?>

    <div class="d-flex justify-content-between pt-3 mb-3">
      <span class="fw-semibold">Total due</span>
      <span class="fw-bold fs-4">KES <?php echo number_format((float) $order['total'], 0); ?></span>
    </div>

    <?php if ($order['status'] === 'open'): ?>
      <form method="post">
        <input type="hidden" name="action" value="settle">
        <input type="hidden" name="receipt_number" value="<?php echo htmlspecialchars($order['receipt_number']); ?>">
        <div class="btn-group w-100 mb-3" role="group">
          <input type="radio" class="btn-check" name="payment_method" id="payCash" value="cash" checked>
          <label class="btn btn-outline-primary" for="payCash"><i class="fas fa-money-bill-wave me-1"></i>Cash</label>
          <input type="radio" class="btn-check" name="payment_method" id="payMpesa" value="mpesa">
          <label class="btn btn-outline-success" for="payMpesa"><i class="fas fa-mobile-screen me-1"></i>M-Pesa</label>
          <input type="radio" class="btn-check" name="payment_method" id="paySplit" value="split">
          <label class="btn btn-outline-secondary" for="paySplit"><i class="fas fa-divide me-1"></i>Split (both)</label>
        </div>
        <div id="cashBox" class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small">Cash given</label>
            <input type="number" step="0.01" min="0" name="amount_tendered" id="cashGiven" class="form-control" placeholder="0">
          </div>
          <div class="col-6">
            <label class="form-label small">Balance to give back</label>
            <div class="form-control bg-light fw-semibold" id="cashBalance">KES 0</div>
          </div>
        </div>
        <div id="splitBox" style="display:none;" class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small">Cash portion</label>
            <input type="number" step="0.01" min="0" name="cash_amount" id="cashPortion" class="form-control">
          </div>
          <div class="col-6">
            <label class="form-label small">M-Pesa portion</label>
            <input type="number" step="0.01" min="0" name="mpesa_amount" id="mpesaPortion" class="form-control">
          </div>
        </div>
        <div class="mb-3"></div>
        <button type="submit" class="btn btn-success btn-lg w-100"><i class="fas fa-check me-1"></i>Mark paid — KES <?php echo number_format((float) $order['total'], 0); ?></button>
      </form>
      <script>
        var ORDER_TOTAL = <?php echo (float) $order['total']; ?>;
        var cashBox = document.getElementById('cashBox'), splitBox = document.getElementById('splitBox');
        function money(n) { return n.toLocaleString('en-KE', {maximumFractionDigits: 2}); }
        function syncMode() {
          var m = document.querySelector('input[name=payment_method]:checked').value;
          cashBox.style.display = m === 'mpesa' ? 'none' : 'flex';
          splitBox.style.display = m === 'split' ? 'flex' : 'none';
          updateBalance();
        }
        function updateBalance() {
          var m = document.querySelector('input[name=payment_method]:checked').value;
          var due = m === 'split' ? (parseFloat(document.getElementById('cashPortion').value) || 0) : ORDER_TOTAL;
          var given = parseFloat(document.getElementById('cashGiven').value) || 0;
          var bal = document.getElementById('cashBalance');
          if (m === 'mpesa') { bal.textContent = '—'; return; }
          bal.textContent = given >= due ? ('KES ' + money(given - due)) : 'short';
        }
        document.querySelectorAll('input[name=payment_method]').forEach(function (r) { r.addEventListener('change', syncMode); });
        ['cashGiven', 'cashPortion'].forEach(function (id) {
          document.getElementById(id).addEventListener('input', updateBalance);
        });
        syncMode();
      </script>
    <?php elseif ($order['status'] === 'paid'): ?>
      <div class="alert alert-success mb-0">Already settled via <?php echo htmlspecialchars(ucfirst($order['payment_method'] ?? '')); ?> on <?php echo date('j M Y, g:i a', strtotime($order['paid_at'])); ?>.</div>
    <?php else: ?>
      <div class="alert alert-secondary mb-0">This tab was voided — nothing to collect.</div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/staff/layout.php';
