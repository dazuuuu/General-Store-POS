<?php
// public/staff/payments/index.php
// Customer-centric credit payments: group open invoices under one customer
// (Date / Invoice / Amount), take deposit or full payment with admin payment
// modes, then print a receipt. Also supports single-invoice lookup.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::PAYMENTS_PROCESS);

$pdo = Database::pdo();
$O = new Models\OrderModel($pdo);
$CM = new Models\CustomerModel($pdo);
$tenant = (new Models\TenantModel($pdo))->find(TenantContext::tenantId()) ?: [];
$cardTypes = PaymentOptions::cardTypes();
$banks = PaymentOptions::kenyaBanks();
$saccos = PaymentOptions::kenyaSaccos();
$settleMethods = PaymentOptions::enabledSettleMethods($tenant);
$depositMethods = PaymentOptions::depositMethods($tenant);
$isStaffViewer = TenantContext::role() === 'staff';
$paymentsBase = $isStaffViewer ? public_url('staff/payments/') : public_url('super/payments/');
$receiptBase = $isStaffViewer ? public_url('staff/orders/receipt.php') : public_url('super/orders/receipt.php');
$statementBase = $isStaffViewer ? public_url('staff/customers/statement.php') : public_url('super/customers/statement.php');
$invoiceSearchApi = public_url('api/orders/invoice_search.php');
$customerSearchApi = public_url('api/customers/search.php');

$error = '';
$receiptQuery = trim($_GET['receipt'] ?? $_POST['receipt_number'] ?? '');
$customerQuery = trim($_GET['customer'] ?? $_POST['customer_name'] ?? '');
$customerId = (int) ($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);
$preferDeposit = isset($_GET['deposit']) || (($_POST['pay_mode'] ?? '') === 'deposit');
$order = null;
$searchMatches = [];
$customerInvoices = [];
$customerPayments = [];
$customerLabel = $customerQuery;
$customerCompany = '';

if ($customerId > 0) {
    $cust = $CM->find($customerId);
    if ($cust) {
        $customerLabel = (string) ($cust['name'] ?? $customerLabel);
        $customerCompany = (string) ($cust['company_name'] ?? '');
        if ($customerQuery === '') {
            $customerQuery = $customerLabel;
        }
    }
}

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
        } elseif (!$searchMatches && $customerId <= 0 && $customerQuery === '') {
            $error = 'No open invoice found for that search.';
        }
    }
}

if (!$order && ($customerId > 0 || $customerQuery !== '')) {
    $customerInvoices = $O->openInvoicesForCustomer($customerId > 0 ? $customerId : null, $customerQuery);
    $customerPayments = $O->paymentsForCustomer($customerId > 0 ? $customerId : null, $customerQuery, 50);
    if (!$customerInvoices && !$error) {
        // Fall back to invoice search by the typed name
        if ($receiptQuery === '' && $customerQuery !== '') {
            $searchMatches = $O->searchInvoices($customerQuery, ['open_only' => true, 'limit' => 20]);
        }
        if (!$searchMatches) {
            $error = 'No open credit invoices for this customer.';
        }
    } elseif ($customerInvoices && !$customerLabel) {
        $customerLabel = (string) ($customerInvoices[0]['table_name'] ?? 'Customer');
        $customerCompany = (string) ($customerInvoices[0]['customer_company'] ?? '');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'settle_customer') {
    $ids = array_map('intval', $_POST['order_ids'] ?? []);
    $amount = round((float) ($_POST['amount_received'] ?? 0), 2);
    $mode = $_POST['payment_method'] ?? 'cash';
    $payMode = ($_POST['pay_mode'] ?? 'deposit') === 'full' ? 'full' : 'deposit';
    if ($payMode === 'full') {
        // Pay full selected balances
        $totalDue = 0.0;
        foreach ($O->openInvoicesForCustomer($customerId > 0 ? $customerId : null, $customerQuery) as $inv) {
            if ($ids && !in_array((int) $inv['id'], $ids, true)) continue;
            $due = (float) ($inv['balance_due'] ?? $inv['amount_due'] ?? 0);
            if ($due <= 0.0001) {
                $due = max(0, (float) $inv['total'] - max(0, (float) ($inv['amount_paid'] ?? 0)));
            }
            $totalDue += $due;
            if (!$ids) { $ids[] = (int) $inv['id']; }
        }
        $amount = round($totalDue, 2);
    }
    $payload = [
        'method' => $payMode === 'deposit' ? 'credit' : $mode,
        'deposit_method' => $mode,
        'amount_received' => $amount,
        'amount_tendered' => $_POST['amount_tendered'] ?? $amount,
        'cash_amount' => $_POST['cash_amount'] ?? 0,
        'mpesa_amount' => $_POST['mpesa_amount'] ?? 0,
        'provider' => $_POST['payment_provider'] ?? '',
        'account_name' => $_POST['payment_account_name'] ?? '',
        'reference' => $_POST['payment_reference'] ?? '',
    ];
    if ($payMode === 'full') {
        $payload['method'] = $mode === 'split' ? 'split' : $mode;
    }
    $res = $O->applyCustomerPayment($ids, $amount, $payload, TenantContext::userId());
    if ($res['ok']) {
        $firstId = (int) ($res['receipt_order_ids'][0] ?? 0);
        $applied = (float) ($res['amount_applied'] ?? $amount);
        $_SESSION['flash']['success'] = 'Payment of KES ' . number_format($applied, 0) . ' recorded across ' . count($res['allocations']) . ' invoice(s).';
        if ($firstId > 0) {
            $isDeposit = false;
            foreach ($res['allocations'] as $a) {
                if (($a['status'] ?? '') === 'open') { $isDeposit = true; break; }
            }
            header('Location: ' . $receiptBase . '?id=' . $firstId . '&print=1' . ($isDeposit ? '&deposit=1' : ''));
            exit;
        }
        header('Location: ' . $paymentsBase . '?customer_id=' . $customerId . '&customer=' . urlencode($customerQuery));
        exit;
    }
    $error = $res['error'] ?? 'Could not record the payment.';
    $customerInvoices = $O->openInvoicesForCustomer($customerId > 0 ? $customerId : null, $customerQuery);
    $customerPayments = $O->paymentsForCustomer($customerId > 0 ? $customerId : null, $customerQuery, 50);
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
$customerBalance = 0.0;
foreach ($customerInvoices as $inv) {
    $due = (float) ($inv['balance_due'] ?? $inv['amount_due'] ?? 0);
    if ($due <= 0.0001) {
        $due = max(0, (float) $inv['total'] - max(0, (float) ($inv['amount_paid'] ?? 0)));
    }
    $customerBalance += $due;
}

$page_title = 'Payments';
ob_start();
$firstMethod = array_key_first($settleMethods) ?: 'cash';
$firstDeposit = array_key_first($depositMethods) ?: 'cash';
?>
<h1 class="h5 mb-4 fw-bold"><i class="fas fa-cash-register me-2 text-primary"></i>Payments</h1>

<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <div class="card-body p-4">
    <form method="get" class="row g-2 align-items-end" id="paymentLookupForm" autocomplete="off">
      <div class="col-12 col-lg-8 position-relative">
        <label class="form-label small mb-1">Find customer or invoice</label>
        <input type="text" name="customer" id="customerSearch" class="form-control form-control-lg"
               placeholder="Customer name, company, or invoice number…"
               value="<?php echo htmlspecialchars($customerQuery !== '' ? $customerQuery : $receiptQuery); ?>" autofocus>
        <input type="hidden" name="customer_id" id="customerIdField" value="<?php echo (int) $customerId; ?>">
        <div class="invoice-suggest-menu" id="paymentSuggestMenu"></div>
        <div class="form-text">Credit invoices for one customer are grouped with Date, Invoice no, and Amount.</div>
      </div>
      <div class="col-6 col-lg-2">
        <button class="btn btn-primary btn-lg w-100"><i class="fas fa-magnifying-glass me-1"></i>Find</button>
      </div>
      <div class="col-6 col-lg-2">
        <a class="btn btn-outline-secondary btn-lg w-100" href="<?php echo $paymentsBase; ?>">Clear</a>
      </div>
      <?php if ($preferDeposit): ?><input type="hidden" name="deposit" value="1"><?php endif; ?>
    </form>
  </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if ($searchMatches && !$order && !$customerInvoices): ?>
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;overflow:hidden;">
  <div class="px-4 py-3 border-bottom"><strong><?php echo count($searchMatches); ?></strong> open invoices matched</div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr class="text-muted small text-uppercase"><th>Date</th><th>Customer / company</th><th>Invoice no</th><th class="text-end">Amount</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($searchMatches as $m):
          $paid = max(0, (float) ($m['amount_paid'] ?? 0));
          $due = (float) ($m['balance_due'] ?? $m['amount_due'] ?? 0);
          if ($due <= 0.0001) { $due = max(0, (float) ($m['total'] ?? 0) - $paid); }
          $cust = trim((string) ($m['table_name'] ?? ''));
          $co = trim((string) ($m['customer_company'] ?? ''));
        ?>
        <tr>
          <td class="small"><?php echo date('j M Y', strtotime($m['created_at'])); ?></td>
          <td>
            <div class="fw-semibold"><?php echo htmlspecialchars($cust !== '' ? $cust : '—'); ?></div>
            <?php if ($co !== ''): ?><div class="text-muted small"><?php echo htmlspecialchars($co); ?></div><?php endif; ?>
          </td>
          <td class="fw-semibold"><?php echo htmlspecialchars($m['receipt_number'] ?? ''); ?></td>
          <td class="text-end fw-bold text-danger">KES <?php echo number_format($due, 0); ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-primary" href="<?php echo $paymentsBase . '?customer=' . urlencode($cust) . ($m['customer_id'] ? '&customer_id=' . (int) $m['customer_id'] : ''); ?>">Customer ledger</a>
            <a class="btn btn-sm btn-success" href="<?php echo $paymentsBase . '?receipt=' . urlencode($m['receipt_number']) . ($preferDeposit ? '&deposit=1' : ''); ?>">Select</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($customerInvoices): ?>
<?php
  $statementUrl = $statementBase . '?name=' . urlencode($customerLabel) . ($customerId > 0 ? '&customer_id=' . $customerId : '');
?>
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
      <div>
        <div class="fw-bold fs-5"><?php echo htmlspecialchars($customerLabel); ?></div>
        <?php if ($customerCompany !== ''): ?><div class="text-muted"><?php echo htmlspecialchars($customerCompany); ?></div><?php endif; ?>
        <div class="mt-1">Total credit owed: <strong class="text-danger fs-5">KES <?php echo number_format($customerBalance, 0); ?></strong></div>
      </div>
      <a class="btn btn-sm btn-outline-secondary" href="<?php echo $statementUrl; ?>"><i class="fas fa-download me-1"></i>Download customer report</a>
    </div>

    <form method="post" id="customerPayForm">
      <input type="hidden" name="action" value="settle_customer">
      <input type="hidden" name="customer_id" value="<?php echo (int) $customerId; ?>">
      <input type="hidden" name="customer_name" value="<?php echo htmlspecialchars($customerLabel); ?>">
      <input type="hidden" name="payment_provider" id="custPaymentProvider" value="">
      <input type="hidden" name="payment_account_name" id="custPaymentAccountName" value="">
      <input type="hidden" name="payment_reference" id="custPaymentReference" value="">

      <div class="table-responsive mb-3">
        <table class="table align-middle mb-0">
          <thead>
            <tr class="text-muted small text-uppercase">
              <th style="width:40px;"><input type="checkbox" id="checkAllInvoices" checked></th>
              <th>Date</th>
              <th>Invoice no</th>
              <th class="text-end">Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($customerInvoices as $inv):
              $due = (float) ($inv['balance_due'] ?? $inv['amount_due'] ?? 0);
              if ($due <= 0.0001) {
                  $due = max(0, (float) $inv['total'] - max(0, (float) ($inv['amount_paid'] ?? 0)));
              }
            ?>
            <tr>
              <td><input type="checkbox" class="inv-check" name="order_ids[]" value="<?php echo (int) $inv['id']; ?>" data-due="<?php echo htmlspecialchars((string) $due); ?>" checked></td>
              <td><?php echo date('j M Y', strtotime($inv['created_at'])); ?></td>
              <td class="fw-semibold"><?php echo htmlspecialchars($inv['receipt_number'] ?? ''); ?></td>
              <td class="text-end fw-bold text-danger">KES <?php echo number_format($due, 0); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="3" class="text-end">Selected balance</th>
              <th class="text-end text-danger" id="selectedBalanceOut">KES <?php echo number_format($customerBalance, 0); ?></th>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label small">Payment type</label>
          <select name="pay_mode" id="custPayMode" class="form-select">
            <option value="deposit" <?php echo $preferDeposit ? 'selected' : ''; ?>>Deposit / part pay</option>
            <option value="full">Pay full selected balance</option>
          </select>
        </div>
        <div class="col-md-4" id="custAmountWrap">
          <label class="form-label small">Amount paid</label>
          <input type="number" step="0.01" min="0" name="amount_received" id="custAmount" class="form-control" value="" placeholder="0">
        </div>
        <div class="col-md-4">
          <label class="form-label small">Mode of payment</label>
          <select name="payment_method" id="custPayMethod" class="form-select">
            <?php foreach ($depositMethods as $mid => $mlabel): ?>
              <option value="<?php echo htmlspecialchars($mid); ?>"><?php echo htmlspecialchars($mlabel); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div id="custCashBox" class="row g-2 mb-2" style="display:none;">
        <div class="col-md-6">
          <label class="form-label small">Cash given</label>
          <input type="number" step="0.01" min="0" name="amount_tendered" id="custCashGiven" class="form-control" placeholder="0">
        </div>
      </div>
      <div id="custDetailBox" class="row g-2 mb-3" style="display:none;">
        <div class="col-md-6">
          <label class="form-label small" id="custDetailLabel">Details</label>
          <input type="text" id="custDetailInput" class="form-control" placeholder="Optional" list="custDetailList">
          <datalist id="custDetailList"></datalist>
        </div>
        <div class="col-md-6">
          <label class="form-label small">Reference</label>
          <input type="text" id="custRefInput" class="form-control" placeholder="Optional">
        </div>
      </div>

      <button type="submit" class="btn btn-success btn-lg w-100" id="custPayBtn"><i class="fas fa-check me-1"></i>Record payment &amp; print receipt</button>
      <div class="form-text text-center mt-2">Payment date is recorded automatically. A receipt opens after saving.</div>
    </form>

    <?php if ($customerPayments): ?>
      <hr class="my-4">
      <h2 class="h6 fw-bold mb-2">Recent payments</h2>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr class="text-muted small text-uppercase"><th>Date</th><th>Invoice</th><th>Mode</th><th class="text-end">Amount paid</th></tr></thead>
          <tbody>
            <?php foreach ($customerPayments as $pay): ?>
            <tr>
              <td class="small"><?php echo date('j M Y, g:i a', strtotime($pay['created_at'])); ?></td>
              <td><?php echo htmlspecialchars($pay['receipt_number'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars(PaymentOptions::label($pay)); ?></td>
              <td class="text-end fw-semibold">KES <?php echo number_format((float) $pay['amount'], 0); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<script>
(function(){
  var checks = Array.prototype.slice.call(document.querySelectorAll('.inv-check'));
  var all = document.getElementById('checkAllInvoices');
  var amountInput = document.getElementById('custAmount');
  var modeSel = document.getElementById('custPayMode');
  var methodSel = document.getElementById('custPayMethod');
  var cashBox = document.getElementById('custCashBox');
  var detailBox = document.getElementById('custDetailBox');
  var detailInput = document.getElementById('custDetailInput');
  var detailLabel = document.getElementById('custDetailLabel');
  var detailList = document.getElementById('custDetailList');
  var refInput = document.getElementById('custRefInput');
  var cardTypes = <?php echo json_encode(array_values($cardTypes)); ?>;
  var banks = <?php echo json_encode(array_values($banks)); ?>;
  var saccos = <?php echo json_encode(array_values($saccos)); ?>;
  function selectedDue(){
    return checks.reduce(function(sum, c){ return sum + (c.checked ? (parseFloat(c.dataset.due)||0) : 0); }, 0);
  }
  function money(n){ return 'KES ' + (Math.round(n*100)/100).toLocaleString('en-KE', {maximumFractionDigits:0}); }
  function refreshSelected(){
    var due = selectedDue();
    document.getElementById('selectedBalanceOut').textContent = money(due);
    if (modeSel.value === 'full') amountInput.value = due.toFixed(2);
    syncDetails();
  }
  function fillList(items){
    detailList.innerHTML = '';
    (items||[]).forEach(function(v){
      var o = document.createElement('option');
      o.value = v;
      detailList.appendChild(o);
    });
  }
  function syncDetails(){
    var m = methodSel.value;
    cashBox.style.display = m === 'cash' ? 'flex' : 'none';
    document.getElementById('custAmountWrap').style.display = modeSel.value === 'deposit' ? '' : 'none';
    if (m === 'cash') {
      detailBox.style.display = 'none';
      return;
    }
    detailBox.style.display = 'flex';
    if (m === 'mpesa' || m === 'paybill') {
      detailLabel.textContent = m === 'paybill' ? 'Paybill / Till' : 'Name on M-Pesa';
      detailInput.placeholder = m === 'paybill' ? 'Paybill or Till number' : 'Optional name';
      fillList([]);
    } else if (m === 'card') {
      detailLabel.textContent = 'Card type';
      detailInput.placeholder = 'Choose card type';
      fillList(cardTypes);
    } else if (m === 'bank') {
      detailLabel.textContent = 'Bank';
      detailInput.placeholder = 'Choose or type bank';
      fillList(banks);
    } else if (m === 'sacco') {
      detailLabel.textContent = 'SACCO';
      detailInput.placeholder = 'Choose or type SACCO';
      fillList(saccos);
    } else {
      detailBox.style.display = 'none';
    }
  }
  function syncHidden(){
    var m = methodSel.value;
    var provider = '';
    var account = '';
    if (m === 'mpesa') { provider = 'M-Pesa'; account = detailInput.value || ''; }
    else if (m === 'paybill') { provider = detailInput.value || 'Paybill / Till'; }
    else if (m === 'card' || m === 'bank' || m === 'sacco') { provider = detailInput.value || ''; }
    document.getElementById('custPaymentProvider').value = provider;
    document.getElementById('custPaymentAccountName').value = account;
    document.getElementById('custPaymentReference').value = refInput.value || '';
    var due = selectedDue();
    var amt = modeSel.value === 'full' ? due : (parseFloat(amountInput.value)||0);
    document.getElementById('custPayBtn').innerHTML = '<i class="fas fa-check me-1"></i>Record ' + money(amt) + ' &amp; print receipt';
  }
  if (all) all.addEventListener('change', function(){ checks.forEach(function(c){ c.checked = all.checked; }); refreshSelected(); });
  checks.forEach(function(c){ c.addEventListener('change', refreshSelected); });
  modeSel.addEventListener('change', refreshSelected);
  methodSel.addEventListener('change', function(){ syncDetails(); syncHidden(); });
  [amountInput, detailInput, refInput, document.getElementById('custCashGiven')].forEach(function(el){
    if (el) { el.addEventListener('input', syncHidden); el.addEventListener('change', syncHidden); }
  });
  document.getElementById('customerPayForm').addEventListener('submit', function(e){
    syncHidden();
    if (!checks.some(function(c){ return c.checked; })) { e.preventDefault(); alert('Select at least one invoice.'); return; }
    if (modeSel.value === 'deposit' && (parseFloat(amountInput.value)||0) <= 0) { e.preventDefault(); alert('Enter the deposit amount.'); }
  });
  refreshSelected();
})();
</script>
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
    $ledgerUrl = $paymentsBase . '?customer=' . urlencode($order['table_name'] ?? '') . (!empty($order['customer_id']) ? '&customer_id=' . (int) $order['customer_id'] : '');
?>
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
      <div>
        <div class="fw-bold fs-5"><?php echo htmlspecialchars($order['table_name']); ?> <?php echo $statusBadge; ?></div>
        <div class="text-muted small">Invoice <?php echo htmlspecialchars($order['receipt_number']); ?> · opened by <?php echo htmlspecialchars($order['opened_by_name'] ?? '—'); ?> · <?php echo date('j M Y, g:i a', strtotime($order['created_at'])); ?></div>
        <?php if (!empty($order['credit_due_at']) || !empty($order['credit_duration_days'])): ?>
          <div class="small <?php echo !empty($order['credit_due_at']) && strtotime($order['credit_due_at']) < time() && $order['status'] === 'open' ? 'text-danger fw-semibold' : 'text-muted'; ?>">
            Credit:
            <?php if (!empty($order['credit_duration_days'])): ?><?php echo (int) $order['credit_duration_days']; ?> days<?php endif; ?>
            <?php if (!empty($order['credit_due_at'])): ?> · due <?php echo date('j M Y', strtotime($order['credit_due_at'])); ?><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-outline-primary" href="<?php echo $ledgerUrl; ?>">Customer ledger</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?php echo $receiptBase . '?id=' . (int) $order['id']; ?>"><i class="fas fa-receipt me-1"></i>Receipt</a>
      </div>
    </div>

    <?php foreach ($items as $it): ?>
      <div class="d-flex justify-content-between border-bottom py-2">
        <div>
          <div class="fw-semibold" style="font-size:.9rem;"><?php echo htmlspecialchars($it['product_name']); ?></div>
          <small class="text-muted">KES <?php echo number_format((float) $it['unit_price'], 0); ?> × <?php echo htmlspecialchars(QtyFormat::display((float) $it['quantity'])); ?></small>
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
          <?php $i = 0; foreach ($settleMethods as $mid => $mlabel): $i++; ?>
            <input type="radio" class="btn-check" name="payment_method" id="pay_<?php echo htmlspecialchars($mid); ?>" value="<?php echo htmlspecialchars($mid); ?>" <?php echo (!$preferDeposit && $i === 1) || ($preferDeposit && false) ? 'checked' : ''; ?>>
            <label class="btn btn-outline-primary" for="pay_<?php echo htmlspecialchars($mid); ?>"><?php echo htmlspecialchars($mlabel); ?></label>
          <?php endforeach; ?>
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
              <?php foreach ($depositMethods as $mid => $mlabel): ?>
                <option value="<?php echo htmlspecialchars($mid); ?>"><?php echo htmlspecialchars($mlabel); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
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
        <div id="paybillBox" style="display:none;" class="row g-2 mb-2">
          <div class="col-12">
            <label class="form-label small">Paybill / Till</label>
            <input type="text" id="paybillInput" class="form-control" placeholder="Paybill or Till number">
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
          var detailMethod = m === 'credit' ? depositMethod : m;
          cashBox.style.display = (m === 'cash' || m === 'split' || (m === 'credit' && depositMethod === 'cash')) ? 'flex' : 'none';
          splitBox.style.display = m === 'split' ? 'flex' : 'none';
          document.getElementById('mpesaBox').style.display = (detailMethod === 'mpesa' || m === 'split') ? 'flex' : 'none';
          document.getElementById('paybillBox').style.display = detailMethod === 'paybill' ? 'flex' : 'none';
          document.getElementById('cardBox').style.display = detailMethod === 'card' ? 'flex' : 'none';
          document.getElementById('bankBox').style.display = detailMethod === 'bank' ? 'flex' : 'none';
          document.getElementById('saccoBox').style.display = detailMethod === 'sacco' ? 'flex' : 'none';
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
          if (detailMethod === 'paybill') { provider = document.getElementById('paybillInput').value || 'Paybill / Till'; }
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
        ['cashGiven', 'cashPortion', 'mpesaPortion', 'mpesaNameInput', 'paybillInput', 'cardTypeInput', 'bankInput', 'saccoInput', 'referenceInput', 'creditAmount', 'depositMethod'].forEach(function (id) {
          var el = document.getElementById(id);
          if (!el) return;
          el.addEventListener('input', updateBalance);
          el.addEventListener('change', updateBalance);
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
  var input = document.getElementById('customerSearch');
  var menu = document.getElementById('paymentSuggestMenu');
  var hidden = document.getElementById('customerIdField');
  var api = <?php echo json_encode($invoiceSearchApi); ?>;
  var custApi = <?php echo json_encode($customerSearchApi); ?>;
  var preferDeposit = <?php echo $preferDeposit ? 'true' : 'false'; ?>;
  if (!input || !menu) return;
  var timer = null;
  function hide(){ menu.classList.remove('show'); }
  function money(n){ return 'KES ' + (Number(n)||0).toLocaleString('en-KE', {maximumFractionDigits:0}); }
  function goCustomer(name, id){
    var url = '?customer=' + encodeURIComponent(name || '');
    if (id) url += '&customer_id=' + encodeURIComponent(id);
    if (preferDeposit) url += '&deposit=1';
    window.location.href = url;
  }
  function goReceipt(receipt){
    var url = '?receipt=' + encodeURIComponent(receipt);
    if (preferDeposit) url += '&deposit=1';
    window.location.href = url;
  }
  function render(items, customers){
    menu.innerHTML = '';
    var any = false;
    (customers || []).forEach(function(c){
      any = true;
      var b = document.createElement('button');
      b.type = 'button';
      b.innerHTML = '<strong></strong><span class="meta"></span>';
      b.querySelector('strong').textContent = (c.name || 'Customer') + (c.company_name ? ' · ' + c.company_name : '');
      b.querySelector('.meta').textContent = 'Customer ledger' + (c.phone ? ' · ' + c.phone : '');
      b.addEventListener('mousedown', function(e){
        e.preventDefault();
        if (hidden) hidden.value = c.id || '';
        goCustomer(c.name || '', c.id || 0);
      });
      menu.appendChild(b);
    });
    (items || []).forEach(function(it){
      any = true;
      var b = document.createElement('button');
      b.type = 'button';
      var title = (it.customer_name || 'Customer') + (it.company_name ? ' · ' + it.company_name : '');
      b.innerHTML = '<span class="bal">' + money(it.balance) + '</span><strong></strong><span class="meta"></span>';
      b.querySelector('strong').textContent = title;
      b.querySelector('.meta').textContent = (it.receipt_number || '') + ' · open invoice';
      b.addEventListener('mousedown', function(e){
        e.preventDefault();
        goCustomer(it.customer_name || '', 0);
      });
      menu.appendChild(b);
    });
    if (any) menu.classList.add('show'); else hide();
  }
  input.addEventListener('input', function(){
    if (hidden) hidden.value = '';
    clearTimeout(timer);
    var q = input.value.trim();
    if (q.length < 1) { hide(); return; }
    timer = setTimeout(function(){
      Promise.all([
        fetch(api + '?q=' + encodeURIComponent(q) + '&limit=8&open_only=1').then(function(r){ return r.json(); }).catch(function(){ return {items:[]}; }),
        fetch(custApi + '?q=' + encodeURIComponent(q) + '&limit=6').then(function(r){ return r.json(); }).catch(function(){ return {items:[]}; })
      ]).then(function(res){
        render((res[0] && res[0].items) || [], (res[1] && res[1].items) || []);
      });
    }, 180);
  });
  input.addEventListener('blur', function(){ setTimeout(hide, 160); });
})();
</script>
<?php
$content = ob_get_clean();
$__layout = $isStaffViewer ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
