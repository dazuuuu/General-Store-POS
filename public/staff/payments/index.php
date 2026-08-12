<?php
// public/staff/payments/index.php
// Payments desk: look up a credit-sale invoice by its number and record full
// or partial payments. Reached from staff/payments and super/payments; links
// and layout adapt to the current role.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::PAYMENTS_PROCESS);

$pdo = Database::pdo();
$O = new Models\OrderModel($pdo);
$cardTypes = PaymentOptions::cardTypes();
$banks = PaymentOptions::kenyaBanks();
$saccos = PaymentOptions::kenyaSaccos();
$isStaffViewer = TenantContext::role() === 'staff';
$paymentsBase = $isStaffViewer ? public_url('staff/payments/') : public_url('super/payments/');
$receiptBase = $isStaffViewer ? public_url('staff/orders/receipt.php') : public_url('super/orders/receipt.php');

$error = '';
$receiptQuery = trim($_GET['receipt'] ?? $_POST['receipt_number'] ?? '');
$preferDeposit = isset($_GET['deposit']) || (($_POST['payment_method'] ?? '') === 'credit');
$invoiceSearchApi = public_url('api/orders/invoice_search.php');
$order = null;
$searchMatches = [];

if ($receiptQuery !== '') {
    $order = $O->findByReceipt($receiptQuery);
    if (!$order) {
        $searchMatches = $O->searchInvoices($receiptQuery, ['open_only' => true, 'limit' => 20]);
        if (count($searchMatches) === 1) {
            $order = $O->find((int) $searchMatches[0]['id']);
            $searchMatches = [];
            if ($order && !empty($order['receipt_number'])) {
                $qs = '?receipt=' . urlencode($order['receipt_number']);
                if ($preferDeposit) { $qs .= '&deposit=1'; }
                header('Location: ' . $paymentsBase . $qs);
                exit;
            }
        } elseif (!$searchMatches) {
            $error = 'No open invoice found for that search. Try the invoice number, customer name, or company.';
        }
    }
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
            'provider'        => $_POST['payment_provider'] ?? '',
            'account_name'    => $_POST['payment_account_name'] ?? '',
            'reference'       => $_POST['payment_reference'] ?? '',
            'amount_received' => $_POST['amount_received'] ?? 0,
            'deposit_method'  => $_POST['deposit_method'] ?? '',
        ], TenantContext::userId());
        if ($res['ok']) {
            if (($res['status'] ?? 'paid') === 'paid') {
                $_SESSION['flash']['success'] = 'Payment recorded for ' . $order['receipt_number'] . '.';
                header('Location: ' . $receiptBase . '?id=' . (int) $order['id'] . '&print=1');
            } else {
                $_SESSION['flash']['success'] = 'Deposit recorded. Balance remaining: KES ' . number_format((float) ($res['amount_due'] ?? 0), 0) . '.';
                header('Location: ' . $receiptBase . '?id=' . (int) $order['id'] . '&print=1&deposit=1');
            }
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
    <form method="get" class="row g-2 align-items-end" id="paymentLookupForm" autocomplete="off">
      <div class="col-12 col-sm-8 position-relative">
        <label class="form-label small mb-1">Find credit invoice</label>
        <input type="text" name="receipt" id="receiptSearch" class="form-control form-control-lg"
               placeholder="Invoice number, customer name, or company…"
               value="<?php echo htmlspecialchars($receiptQuery); ?>" autofocus>
        <div class="invoice-suggest-menu" id="paymentSuggestMenu"></div>
        <div class="form-text">Search by invoice #, customer name, or company. Results show balance owed.</div>
      </div>
      <div class="col-12 col-sm-4">
        <button class="btn btn-primary btn-lg w-100"><i class="fas fa-magnifying-glass me-1"></i>Find</button>
      </div>
      <?php if ($preferDeposit): ?><input type="hidden" name="deposit" value="1"><?php endif; ?>
    </form>
  </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if ($searchMatches && !$order): ?>
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;overflow:hidden;">
  <div class="px-4 py-3 border-bottom"><strong><?php echo count($searchMatches); ?></strong> open invoices match “<?php echo htmlspecialchars($receiptQuery); ?>”</div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr class="text-muted small text-uppercase"><th>Customer / company</th><th>Invoice</th><th class="text-end">Balance</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($searchMatches as $m):
          $paid = max(0, (float) ($m['amount_paid'] ?? 0));
          $due = (float) ($m['balance_due'] ?? $m['amount_due'] ?? 0);
          if ($due <= 0.0001) { $due = max(0, (float) ($m['total'] ?? 0) - $paid); }
          $cust = trim((string) ($m['table_name'] ?? ''));
          $co = trim((string) ($m['customer_company'] ?? ''));
        ?>
        <tr>
          <td>
            <div class="fw-semibold"><?php echo htmlspecialchars($cust !== '' ? $cust : '—'); ?></div>
            <?php if ($co !== ''): ?><div class="text-muted small"><?php echo htmlspecialchars($co); ?></div><?php endif; ?>
          </td>
          <td class="fw-semibold"><?php echo htmlspecialchars($m['receipt_number'] ?? ''); ?></td>
          <td class="text-end fw-bold text-danger">KES <?php echo number_format($due, 0); ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-success" href="<?php echo $paymentsBase . '?receipt=' . urlencode($m['receipt_number']) . ($preferDeposit ? '&deposit=1' : ''); ?>">Select</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($order):
    $amountPaid = max(0, (float) ($order['amount_paid'] ?? 0));
    $amountDue = (float) ($order['amount_due'] ?? 0);
    if ($order['status'] === 'open' && $amountDue <= 0.0001) {
        $amountDue = max(0, (float) $order['total'] - $amountPaid);
    }
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
      <a class="btn btn-sm btn-outline-secondary" href="<?php echo $receiptBase . '?id=' . (int) $order['id']; ?>"><i class="fas fa-receipt me-1"></i>Receipt</a>
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

    <div class="d-flex justify-content-between pt-3">
      <span class="fw-semibold">Invoice total</span>
      <span class="fw-bold fs-4">KES <?php echo number_format((float) $order['total'], 0); ?></span>
    </div>
    <?php if ($amountPaid > 0 || $order['status'] === 'open'): ?>
      <div class="d-flex justify-content-between small text-muted">
        <span>Paid so far</span>
        <span>KES <?php echo number_format($amountPaid, 0); ?></span>
      </div>
      <div class="d-flex justify-content-between mb-3">
        <span class="fw-semibold">Balance due</span>
        <span class="fw-bold fs-5 text-danger">KES <?php echo number_format($order['status'] === 'paid' ? 0 : $amountDue, 0); ?></span>
      </div>
    <?php else: ?>
      <div class="mb-3"></div>
    <?php endif; ?>

    <?php if ($order['status'] === 'open'): ?>
      <form method="post">
        <input type="hidden" name="action" value="settle">
        <input type="hidden" name="receipt_number" value="<?php echo htmlspecialchars($order['receipt_number']); ?>">
        <div class="btn-group payment-methods w-100 mb-3 flex-wrap" role="group">
          <input type="radio" class="btn-check" name="payment_method" id="payCash" value="cash"<?php echo $preferDeposit ? '' : ' checked'; ?>>
          <label class="btn btn-outline-primary" for="payCash"><i class="fas fa-money-bill-wave me-1"></i>Cash</label>
          <input type="radio" class="btn-check" name="payment_method" id="payMpesa" value="mpesa">
          <label class="btn btn-outline-success" for="payMpesa"><i class="fas fa-mobile-screen me-1"></i>M-Pesa</label>
          <input type="radio" class="btn-check" name="payment_method" id="payCard" value="card">
          <label class="btn btn-outline-dark" for="payCard"><i class="fas fa-credit-card me-1"></i>Card</label>
          <input type="radio" class="btn-check" name="payment_method" id="payBank" value="bank">
          <label class="btn btn-outline-secondary" for="payBank"><i class="fas fa-building-columns me-1"></i>Bank</label>
          <input type="radio" class="btn-check" name="payment_method" id="paySacco" value="sacco">
          <label class="btn btn-outline-secondary" for="paySacco"><i class="fas fa-landmark me-1"></i>SACCO</label>
          <input type="radio" class="btn-check" name="payment_method" id="paySplit" value="split">
          <label class="btn btn-outline-secondary" for="paySplit"><i class="fas fa-divide me-1"></i>Split (both)</label>
          <input type="radio" class="btn-check" name="payment_method" id="payCredit" value="credit"<?php echo $preferDeposit ? ' checked' : ''; ?>>
          <label class="btn btn-outline-warning" for="payCredit"><i class="fas fa-hand-holding-dollar me-1"></i>Deposit / part pay</label>
        </div>
        <div id="creditBox" style="display:<?php echo $preferDeposit ? 'flex' : 'none'; ?>;" class="row g-2 mb-2">
          <div class="col-12 col-sm-6">
            <label class="form-label small">Deposit amount now</label>
            <input type="number" step="0.01" min="0" max="<?php echo htmlspecialchars((string) $amountDue); ?>" name="amount_received" id="creditAmount" class="form-control" placeholder="0"<?php echo $preferDeposit ? ' autofocus' : ''; ?>>
          </div>
          <div class="col-12 col-sm-6">
            <label class="form-label small">Received through</label>
            <select name="deposit_method" id="depositMethod" class="form-select">
              <option value="cash">Cash</option>
              <option value="mpesa">M-Pesa</option>
              <option value="card">Card</option>
              <option value="bank">Bank</option>
              <option value="sacco">SACCO</option>
            </select>
          </div>
          <div class="col-12"><div class="form-text">Enter any amount up to the balance. A deposit receipt prints after saving.</div></div>
        </div>
        <div id="cashBox" class="row g-2 mb-2" style="display:<?php echo $preferDeposit ? 'none' : 'flex'; ?>;">
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
        <input type="hidden" name="payment_provider" id="paymentProvider" value="">
        <input type="hidden" name="payment_account_name" id="paymentAccountName" value="">
        <input type="hidden" name="payment_reference" id="paymentReference" value="">
        <div id="mpesaBox" style="display:none;" class="row g-2 mb-2">
          <div class="col-12">
            <label class="form-label small">Name shown on M-Pesa</label>
            <input type="text" id="mpesaNameInput" class="form-control" placeholder="Optional">
          </div>
        </div>
        <div id="cardBox" style="display:none;" class="row g-2 mb-2">
          <div class="col-12">
            <label class="form-label small">Card type</label>
            <select id="cardTypeInput" class="form-select">
              <option value="">Choose card type</option>
              <?php foreach ($cardTypes as $type): ?><option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div id="bankBox" style="display:none;" class="row g-2 mb-2">
          <div class="col-12">
            <label class="form-label small">Bank</label>
            <input type="text" id="bankInput" class="form-control" list="kenyaBanks" placeholder="Choose or type bank">
          </div>
        </div>
        <div id="saccoBox" style="display:none;" class="row g-2 mb-2">
          <div class="col-12">
            <label class="form-label small">SACCO</label>
            <input type="text" id="saccoInput" class="form-control" list="kenyaSaccos" placeholder="Choose or type SACCO">
          </div>
        </div>
        <div id="referenceBox" style="display:none;" class="row g-2 mb-2">
          <div class="col-12">
            <label class="form-label small">Transaction reference</label>
            <input type="text" id="referenceInput" class="form-control" placeholder="Optional">
          </div>
        </div>
        <div class="mb-3"></div>
        <button type="submit" class="btn btn-success btn-lg w-100" id="submitPayment"><i class="fas fa-check me-1"></i>Mark paid — KES <?php echo number_format($amountDue, 0); ?></button>
      </form>
      <datalist id="kenyaBanks">
        <?php foreach ($banks as $bank): ?><option value="<?php echo htmlspecialchars($bank); ?>"></option><?php endforeach; ?>
      </datalist>
      <datalist id="kenyaSaccos">
        <?php foreach ($saccos as $sacco): ?><option value="<?php echo htmlspecialchars($sacco); ?>"></option><?php endforeach; ?>
      </datalist>
      <script>
        var ORDER_TOTAL = <?php echo (float) $amountDue; ?>;
        var cashBox = document.getElementById('cashBox'), splitBox = document.getElementById('splitBox');
        function money(n) { return n.toLocaleString('en-KE', {maximumFractionDigits: 2}); }
        function syncMode() {
          var m = document.querySelector('input[name=payment_method]:checked').value;
          var depositMethod = document.getElementById('depositMethod').value;
          document.getElementById('creditBox').style.display = m === 'credit' ? 'flex' : 'none';
          cashBox.style.display = (m === 'cash' || m === 'split' || (m === 'credit' && depositMethod === 'cash')) ? 'flex' : 'none';
          splitBox.style.display = m === 'split' ? 'flex' : 'none';
          document.getElementById('mpesaBox').style.display = (m === 'mpesa' || m === 'split' || (m === 'credit' && depositMethod === 'mpesa')) ? 'flex' : 'none';
          document.getElementById('cardBox').style.display = (m === 'card' || (m === 'credit' && depositMethod === 'card')) ? 'flex' : 'none';
          document.getElementById('bankBox').style.display = (m === 'bank' || (m === 'credit' && depositMethod === 'bank')) ? 'flex' : 'none';
          document.getElementById('saccoBox').style.display = (m === 'sacco' || (m === 'credit' && depositMethod === 'sacco')) ? 'flex' : 'none';
          document.getElementById('referenceBox').style.display = (m === 'cash') ? 'none' : 'flex';
          updateBalance();
        }
        function updateBalance() {
          var m = document.querySelector('input[name=payment_method]:checked').value;
          var depositMethod = document.getElementById('depositMethod').value;
          var creditAmount = parseFloat(document.getElementById('creditAmount').value) || 0;
          var due = m === 'split' ? (parseFloat(document.getElementById('cashPortion').value) || 0) : (m === 'credit' ? creditAmount : ORDER_TOTAL);
          var given = parseFloat(document.getElementById('cashGiven').value) || 0;
          var bal = document.getElementById('cashBalance');
          if (m !== 'cash' && m !== 'split' && !(m === 'credit' && depositMethod === 'cash')) { bal.textContent = '—'; }
          else { bal.textContent = given >= due ? ('KES ' + money(given - due)) : 'short'; }
          var provider = '';
          var accountName = '';
          var detailMethod = m === 'credit' ? depositMethod : m;
          if (detailMethod === 'mpesa' || m === 'split') { accountName = document.getElementById('mpesaNameInput').value || ''; provider = m === 'split' ? 'Cash + M-Pesa' : 'M-Pesa'; }
          if (detailMethod === 'card') { provider = document.getElementById('cardTypeInput').value || ''; }
          if (detailMethod === 'bank') { provider = document.getElementById('bankInput').value || ''; }
          if (detailMethod === 'sacco') { provider = document.getElementById('saccoInput').value || ''; }
          document.getElementById('paymentProvider').value = provider;
          document.getElementById('paymentAccountName').value = accountName;
          document.getElementById('paymentReference').value = document.getElementById('referenceInput').value || '';
          var button = document.getElementById('submitPayment');
          if (m === 'credit') {
            var remaining = Math.max(ORDER_TOTAL - creditAmount, 0);
            button.innerHTML = '<i class="fas fa-check me-1"></i>Record deposit — KES ' + money(creditAmount) + (remaining > 0 ? ' (owes KES ' + money(remaining) + ')' : '');
          } else {
            button.innerHTML = '<i class="fas fa-check me-1"></i>Mark paid — KES ' + money(ORDER_TOTAL);
          }
        }
        document.querySelectorAll('input[name=payment_method]').forEach(function (r) { r.addEventListener('change', syncMode); });
        ['cashGiven', 'cashPortion', 'mpesaPortion', 'mpesaNameInput', 'cardTypeInput', 'bankInput', 'saccoInput', 'referenceInput', 'creditAmount', 'depositMethod'].forEach(function (id) {
          document.getElementById(id).addEventListener('input', updateBalance);
          document.getElementById(id).addEventListener('change', updateBalance);
        });
        document.getElementById('depositMethod').addEventListener('change', syncMode);
        syncMode();
      </script>
      <style>
        .payment-methods{gap:6px;}
        .payment-methods>.btn{border-radius:8px!important;margin:0!important;flex:1 1 130px;}
        @media (max-width:576px){
          .payment-methods{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));}
          .payment-methods>.btn{min-width:0;font-size:.85rem;}
        }
      </style>
    <?php elseif ($order['status'] === 'paid'): ?>
      <div class="alert alert-success mb-0">Already settled via <?php echo htmlspecialchars(ucfirst($order['payment_method'] ?? '')); ?> on <?php echo date('j M Y, g:i a', strtotime($order['paid_at'])); ?>.</div>
    <?php else: ?>
      <div class="alert alert-secondary mb-0">This tab was voided — nothing to collect.</div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<style>
.invoice-suggest-menu{position:absolute;left:0;right:0;top:100%;z-index:40;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 12px 28px rgba(15,23,42,.14);display:none;max-height:320px;overflow:auto;margin-top:4px;}
.invoice-suggest-menu.show{display:block;}
.invoice-suggest-menu button{display:block;width:100%;border:0;background:#fff;text-align:left;padding:.7rem .85rem;border-bottom:1px solid #f1f5f9;}
.invoice-suggest-menu button:last-child{border-bottom:0;}
.invoice-suggest-menu button:hover{background:#f8fafc;}
.invoice-suggest-menu .meta{display:block;color:#64748b;font-size:.78rem;margin-top:2px;}
.invoice-suggest-menu .bal{float:right;font-weight:700;color:#b91c1c;}
</style>
<script>
(function(){
  var input = document.getElementById('receiptSearch');
  var menu = document.getElementById('paymentSuggestMenu');
  var api = <?php echo json_encode($invoiceSearchApi); ?>;
  var preferDeposit = <?php echo $preferDeposit ? 'true' : 'false'; ?>;
  if (!input || !menu) return;
  var timer = null;
  function hide(){ menu.classList.remove('show'); }
  function money(n){ return 'KES ' + (Number(n)||0).toLocaleString('en-KE', {maximumFractionDigits:0}); }
  function go(receipt){
    var url = '?receipt=' + encodeURIComponent(receipt);
    if (preferDeposit) url += '&deposit=1';
    window.location.href = url;
  }
  function render(items){
    menu.innerHTML = '';
    if (!items.length) { hide(); return; }
    items.forEach(function(it){
      var b = document.createElement('button');
      b.type = 'button';
      var title = (it.customer_name || 'Customer') + (it.company_name ? ' · ' + it.company_name : '');
      b.innerHTML = '<span class="bal">' + money(it.balance) + '</span><strong></strong><span class="meta"></span>';
      b.querySelector('strong').textContent = title;
      b.querySelector('.meta').textContent = (it.receipt_number || '') + (it.phone ? ' · ' + it.phone : '');
      b.addEventListener('mousedown', function(e){
        e.preventDefault();
        go(it.receipt_number);
      });
      menu.appendChild(b);
    });
    menu.classList.add('show');
  }
  input.addEventListener('input', function(){
    clearTimeout(timer);
    var q = input.value.trim();
    // Exact ORD- style numbers can still use Find; suggestions help name/company search.
    if (q.length < 1) { hide(); return; }
    timer = setTimeout(function(){
      fetch(api + '?q=' + encodeURIComponent(q) + '&limit=12&open_only=1')
        .then(function(r){ return r.json(); })
        .then(function(data){ render(data.items || []); })
        .catch(function(){ hide(); });
    }, 180);
  });
  input.addEventListener('blur', function(){ setTimeout(hide, 160); });
})();
</script>
<?php
$content = ob_get_clean();
$__layout = $isStaffViewer ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
