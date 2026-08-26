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
$openWallet = isset($_GET['open']) || (($_POST['action'] ?? '') === 'settle_customer');
$order = null;
$searchMatches = [];
$customerInvoices = [];
$customerPayments = [];
$customerLabel = $customerQuery;
$customerCompany = '';

// Typed invoice numbers in the main search box still open a single invoice.
if ($receiptQuery === '' && $customerQuery !== '' && preg_match('/^ORD[\-\s]?\d+/i', $customerQuery)) {
    $receiptQuery = strtoupper(preg_replace('/\s+/', '', $customerQuery));
    if (stripos($receiptQuery, 'ORD') === 0 && $receiptQuery[3] !== '-') {
        $receiptQuery = 'ORD-' . substr($receiptQuery, 3);
    }
}
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
        } elseif (!$searchMatches && $customerId <= 0 && $customerQuery === '') {
            $error = 'No open invoice found for that search.';
        }
    }
    // Prefer the customer wallet view whenever an invoice belongs to a named customer.
    if ($order && ($order['status'] ?? '') === 'open') {
        $cid = (int) ($order['customer_id'] ?? 0);
        $cname = trim((string) ($order['table_name'] ?? ''));
        if ($cid > 0 || $cname !== '') {
            $qs = '?customer=' . urlencode($cname);
            if ($cid > 0) { $qs .= '&customer_id=' . $cid; }
            if ($preferDeposit) { $qs .= '&deposit=1'; }
            // Landing search shows summary only; open wallet when coming from an invoice.
            $qs .= '&open=1';
            header('Location: ' . $paymentsBase . $qs);
            exit;
        }
    }
}

if (!$order && ($customerId > 0 || $customerQuery !== '')) {
    $customerInvoices = $O->openInvoicesForCustomer($customerId > 0 ? $customerId : null, $customerQuery);
    $customerPayments = $O->paymentsForCustomer($customerId > 0 ? $customerId : null, $customerQuery, 50);
    if (!$customerInvoices && !$error) {
        if ($receiptQuery === '' && $customerQuery !== '') {
            $searchMatches = $O->searchInvoices($customerQuery, ['open_only' => true, 'limit' => 20]);
        }
        if (!$searchMatches) {
            // Still show wallet if they have payment history but no open balance.
            if ($customerPayments) {
                $customerLabel = $customerLabel ?: (string) ($customerPayments[0]['table_name'] ?? 'Customer');
            } else {
                $error = 'No open credit invoices for this customer.';
            }
        }
    } elseif ($customerInvoices && !$customerLabel) {
        $customerLabel = (string) ($customerInvoices[0]['table_name'] ?? 'Customer');
        $customerCompany = (string) ($customerInvoices[0]['customer_company'] ?? '');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'settle_customer') {
    // Always settle against the customer's full open wallet (all invoices combined).
    $openRows = $O->openInvoicesForCustomer($customerId > 0 ? $customerId : null, $customerQuery);
    $ids = array_map(fn($inv) => (int) $inv['id'], $openRows);
    $amount = round((float) ($_POST['amount_received'] ?? 0), 2);
    $mode = $_POST['payment_method'] ?? 'cash';
    $payMode = ($_POST['pay_mode'] ?? 'deposit') === 'full' ? 'full' : 'deposit';
    $extraCharge = round(max(0, (float) ($_POST['additional_charges'] ?? 0)), 2);
    $extraNote = trim((string) ($_POST['additional_charges_note'] ?? ''));
    $totalDue = 0.0;
    foreach ($openRows as $inv) {
        $due = (float) ($inv['balance_due'] ?? $inv['amount_due'] ?? 0);
        if ($due <= 0.0001) {
            $due = max(0, (float) $inv['total'] - max(0, (float) ($inv['amount_paid'] ?? 0)));
        }
        $totalDue += $due;
    }
    $totalDue = round($totalDue, 2);

    if ($extraCharge > 0 && $extraNote === '') {
        $error = 'Enter a reason for the extra charge (e.g. delivery, packing).';
    } elseif ($extraCharge > 0 && !$ids) {
        $error = 'No open invoice to attach the extra charge to.';
    } elseif ($extraCharge > 0) {
        $chargeRes = $O->addAdditionalCharge((int) $ids[0], $extraCharge, $extraNote);
        if (!$chargeRes['ok']) {
            $error = $chargeRes['error'] ?? 'Could not save the extra charge.';
        } else {
            $totalDue = round($totalDue + $extraCharge, 2);
        }
    }

    if (!$error) {
    if ($payMode === 'full') {
        $amount = $totalDue;
    }
    $payload = [
        'method' => $payMode === 'deposit' ? 'credit' : $mode,
        'deposit_method' => $mode,
        'amount_received' => $amount,
        'amount_tendered' => (isset($_POST['amount_tendered']) && $_POST['amount_tendered'] !== '')
            ? $_POST['amount_tendered']
            : $amount,
        'cash_amount' => $_POST['cash_amount'] ?? 0,
        'mpesa_amount' => $_POST['mpesa_amount'] ?? 0,
        'provider' => $_POST['payment_provider'] ?? '',
        'account_name' => $_POST['payment_account_name'] ?? '',
        'reference' => $_POST['payment_reference'] ?? '',
        'customer_id' => $customerId,
    ];
    if ($payMode === 'full') {
        $payload['method'] = $mode === 'split' ? 'split' : $mode;
        if ($mode === 'cash' && (!isset($_POST['amount_tendered']) || $_POST['amount_tendered'] === '')) {
            $payload['amount_tendered'] = $amount;
        }
    }
    $res = $O->applyCustomerPayment($ids, $amount, $payload, TenantContext::userId());
    if ($res['ok']) {
        if ($customerId > 0) {
            try { $CM->refreshCreditBalance($customerId); } catch (\Throwable $ignored) {}
        }
        $applied = (float) ($res['amount_applied'] ?? $amount);
        $newBalance = max(0, round($totalDue - $applied, 2));
        if ($customerId > 0) {
            $fresh = $CM->find($customerId);
            $newBalance = (float) ($fresh['credit_balance'] ?? $newBalance);
        }
        $msg = 'Payment of KES ' . number_format($applied, 0) . ' recorded. Wallet balance now KES ' . number_format($newBalance, 0) . '.';
        if ($extraCharge > 0) {
            $msg .= ' Extra charge KES ' . number_format($extraCharge, 0)
                . ($extraNote !== '' ? ' (' . $extraNote . ')' : '')
                . ' added as profit.';
        }
        $_SESSION['flash']['success'] = $msg;
        $firstId = (int) ($res['receipt_order_ids'][0] ?? 0);
        $returnQs = '?customer=' . urlencode($customerQuery !== '' ? $customerQuery : $customerLabel);
        if ($customerId > 0) { $returnQs .= '&customer_id=' . $customerId; }
        $returnQs .= '&open=1';
        if ($firstId > 0) {
            $_SESSION['flash']['receipt_url'] = $receiptBase . '?id=' . $firstId . '&print=1'
                . ((float) ($res['allocations'][0]['amount_due'] ?? 0) > 0.0001 ? '&deposit=1' : '');
        }
        header('Location: ' . $paymentsBase . $returnQs);
        exit;
    }
    $error = $res['error'] ?? 'Could not record the payment.';
    }
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
            'amount_tendered' => (isset($_POST['amount_tendered']) && $_POST['amount_tendered'] !== '')
                ? $_POST['amount_tendered']
                : (($_POST['payment_method'] ?? '') === 'credit'
                    ? ($_POST['amount_received'] ?? 0)
                    : null),
            'provider'        => $_POST['payment_provider'] ?? '',
            'account_name'    => $_POST['payment_account_name'] ?? '',
            'reference'       => $_POST['payment_reference'] ?? '',
            'amount_received' => $_POST['amount_received'] ?? 0,
            'deposit_method'  => $_POST['deposit_method'] ?? '',
            'customer_id'     => (int) ($order['customer_id'] ?? $customerId),
        ], TenantContext::userId());
        if ($res['ok']) {
            $cid = (int) ($order['customer_id'] ?? $customerId);
            if ($cid <= 0) {
                $byName = $CM->findByName((string) ($order['table_name'] ?? ''));
                $cid = $byName ? (int) $byName['id'] : 0;
            }
            if ($cid > 0) {
                try { $CM->refreshCreditBalance($cid); } catch (\Throwable $ignored) {}
            }
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
if ($customerId > 0) {
    try {
        $customerBalance = $CM->refreshCreditBalance($customerId);
    } catch (\Throwable $ignored) {}
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
        <div class="form-text">Type at least 2 letters — pick a customer to see their name and total balance, then open the wallet to pay.</div>
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
<?php if (!empty($_SESSION['flash']['success'])): ?>
  <div class="alert alert-success d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><?php echo htmlspecialchars($_SESSION['flash']['success']); unset($_SESSION['flash']['success']); ?></span>
    <?php if (!empty($_SESSION['flash']['receipt_url'])):
      $ru = $_SESSION['flash']['receipt_url']; unset($_SESSION['flash']['receipt_url']); ?>
      <a class="btn btn-sm btn-outline-success" href="<?php echo htmlspecialchars($ru); ?>" target="_blank" rel="noopener"><i class="fas fa-print me-1"></i>Print receipt</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($searchMatches && !$order && !$customerInvoices): ?>
<?php
  // Group invoice hits into customer summary rows (name + total balance), not a full invoice dump.
  $matchGroups = [];
  foreach ($searchMatches as $m) {
      $paid = max(0, (float) ($m['amount_paid'] ?? 0));
      $due = (float) ($m['balance_due'] ?? $m['amount_due'] ?? 0);
      if ($due <= 0.0001) { $due = max(0, (float) ($m['total'] ?? 0) - $paid); }
      $cust = trim((string) ($m['table_name'] ?? ''));
      $co = trim((string) ($m['customer_company'] ?? ''));
      $cid = (int) ($m['customer_id'] ?? 0);
      $key = $cid > 0 ? 'id:' . $cid : 'n:' . strtolower($cust !== '' ? $cust : ('inv-' . ($m['id'] ?? 0)));
      if (!isset($matchGroups[$key])) {
          $matchGroups[$key] = [
              'name' => $cust !== '' ? $cust : ('Invoice ' . ($m['receipt_number'] ?? '')),
              'company' => $co,
              'customer_id' => $cid,
              'balance' => 0.0,
              'count' => 0,
          ];
      }
      $matchGroups[$key]['balance'] += $due;
      $matchGroups[$key]['count']++;
      if ($matchGroups[$key]['company'] === '' && $co !== '') {
          $matchGroups[$key]['company'] = $co;
      }
  }
?>
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;overflow:hidden;">
  <div class="px-4 py-3 border-bottom"><strong><?php echo count($matchGroups); ?></strong> customer<?php echo count($matchGroups) === 1 ? '' : 's'; ?> matched</div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr class="text-muted small text-uppercase"><th>Customer / company</th><th class="text-end">Total balance</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($matchGroups as $g): ?>
        <tr>
          <td>
            <div class="fw-semibold"><?php echo htmlspecialchars($g['name']); ?></div>
            <?php if ($g['company'] !== ''): ?><div class="text-muted small"><?php echo htmlspecialchars($g['company']); ?></div><?php endif; ?>
          </td>
          <td class="text-end fw-bold text-danger">KES <?php echo number_format($g['balance'], 0); ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-success" href="<?php echo $paymentsBase . '?customer=' . urlencode($g['name']) . ($g['customer_id'] ? '&customer_id=' . (int) $g['customer_id'] : ''); ?>">View</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($customerInvoices || ($customerPayments && ($customerId > 0 || $customerQuery !== ''))): ?>
<?php
  $statementUrl = $statementBase . '?name=' . urlencode($customerLabel) . ($customerId > 0 ? '&customer_id=' . $customerId : '');
  $invoiceTotal = 0.0;
  foreach ($customerInvoices as $inv) {
      $due = (float) ($inv['balance_due'] ?? $inv['amount_due'] ?? 0);
      if ($due <= 0.0001) {
          $due = max(0, (float) $inv['total'] - max(0, (float) ($inv['amount_paid'] ?? 0)));
      }
      $invoiceTotal += $due;
  }
  if ($customerBalance <= 0 && $invoiceTotal > 0) {
      $customerBalance = $invoiceTotal;
  }
  $paidTotal = 0.0;
  foreach ($customerPayments as $pay) {
      $paidTotal += (float) ($pay['amount'] ?? 0);
  }
  $walletUrl = $paymentsBase . '?customer=' . urlencode($customerLabel)
      . ($customerId > 0 ? '&customer_id=' . $customerId : '')
      . '&open=1'
      . ($preferDeposit ? '&deposit=1' : '');
?>

<?php if (!$openWallet): ?>
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <div class="card-body p-4">
    <div class="text-muted small text-uppercase fw-semibold mb-1">Customer</div>
    <a href="<?php echo htmlspecialchars($walletUrl); ?>" class="text-decoration-none text-dark d-block">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
          <div class="fw-bold" style="font-size:1.5rem;line-height:1.2;"><?php echo htmlspecialchars($customerLabel); ?></div>
          <?php if ($customerCompany !== ''): ?><div class="text-muted"><?php echo htmlspecialchars($customerCompany); ?></div><?php endif; ?>
        </div>
        <div class="text-end">
          <div class="text-muted small text-uppercase fw-semibold mb-1">Total balance</div>
          <div class="fw-bold text-danger" style="font-size:1.75rem;">KES <?php echo number_format($customerBalance, 0); ?></div>
          <span class="btn btn-sm btn-success mt-2"><i class="fas fa-folder-open me-1"></i>Open wallet</span>
        </div>
      </div>
    </a>
    <div class="form-text mt-3 mb-0">Click the customer to see invoices and record a deposit or full payment.</div>
  </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
      <div>
        <div class="text-muted small text-uppercase fw-semibold mb-1">Customer</div>
        <div class="fw-bold" style="font-size:1.6rem;line-height:1.2;"><?php echo htmlspecialchars($customerLabel); ?></div>
        <?php if ($customerCompany !== ''): ?><div class="text-muted"><?php echo htmlspecialchars($customerCompany); ?></div><?php endif; ?>
        <div class="text-muted small mt-1"><?php echo count($customerInvoices); ?> open invoice<?php echo count($customerInvoices) === 1 ? '' : 's'; ?></div>
      </div>
      <div class="text-end">
        <div class="text-muted small text-uppercase fw-semibold mb-1">Wallet balance</div>
        <div class="fw-bold text-danger" style="font-size:1.75rem;line-height:1.1;">KES <?php echo number_format($customerBalance, 0); ?></div>
        <a class="btn btn-sm btn-outline-secondary mt-2" href="<?php echo $statementUrl; ?>"><i class="fas fa-download me-1"></i>Download report</a>
      </div>
    </div>

    <h2 class="h6 fw-bold mb-2">Open invoices</h2>
    <div class="table-responsive mb-4">
      <table class="table align-middle mb-0">
        <thead>
          <tr class="text-muted small text-uppercase">
            <th>Date</th>
            <th>Invoice no</th>
            <th class="text-end">Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$customerInvoices): ?>
            <tr><td colspan="3" class="text-muted text-center py-3">No open invoices — wallet is clear.</td></tr>
          <?php else: foreach ($customerInvoices as $inv):
            $due = (float) ($inv['balance_due'] ?? $inv['amount_due'] ?? 0);
            if ($due <= 0.0001) {
                $due = max(0, (float) $inv['total'] - max(0, (float) ($inv['amount_paid'] ?? 0)));
            }
          ?>
          <tr>
            <td><?php echo date('j M Y', strtotime($inv['created_at'])); ?></td>
            <td class="fw-semibold"><?php echo htmlspecialchars($inv['receipt_number'] ?? ''); ?></td>
            <td class="text-end fw-bold text-danger">KES <?php echo number_format($due, 0); ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
          <tr class="border-top">
            <th colspan="2" class="text-end">Total balance</th>
            <th class="text-end text-danger fs-5" id="walletBalanceOut">KES <?php echo number_format($customerBalance, 0); ?></th>
          </tr>
        </tfoot>
      </table>
    </div>

    <?php if ($customerInvoices): ?>
    <form method="post" id="customerPayForm" class="border rounded-3 p-3 mb-4" style="background:#f8fafc;">
      <input type="hidden" name="action" value="settle_customer">
      <input type="hidden" name="customer_id" value="<?php echo (int) $customerId; ?>">
      <input type="hidden" name="customer_name" value="<?php echo htmlspecialchars($customerLabel); ?>">
      <input type="hidden" name="payment_provider" id="custPaymentProvider" value="">
      <input type="hidden" name="payment_account_name" id="custPaymentAccountName" value="">
      <input type="hidden" name="payment_reference" id="custPaymentReference" value="">
      <?php foreach ($customerInvoices as $inv): ?>
        <input type="hidden" name="order_ids[]" value="<?php echo (int) $inv['id']; ?>">
      <?php endforeach; ?>

      <div class="fw-semibold mb-2">Pay against wallet total</div>
      <div class="text-muted small mb-3">Deposits reduce the customer’s overall balance (applied oldest invoice first). You do not pick one invoice.</div>

      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label small">Payment type</label>
          <select name="pay_mode" id="custPayMode" class="form-select">
            <option value="deposit" <?php echo $preferDeposit ? 'selected' : ''; ?>>Deposit / part pay</option>
            <option value="full">Pay full wallet balance</option>
          </select>
        </div>
        <div class="col-md-4" id="custAmountWrap">
          <label class="form-label small">Amount paid</label>
          <input type="number" step="0.01" min="0" name="amount_received" id="custAmount" class="form-control" value="" placeholder="0" autofocus>
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

      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label small">Extra charge <span class="text-muted">(optional profit)</span></label>
          <input type="number" step="0.01" min="0" name="additional_charges" id="custExtraCharge" class="form-control" value="" placeholder="0">
        </div>
        <div class="col-md-8">
          <label class="form-label small">Reason for extra charge</label>
          <input type="text" name="additional_charges_note" id="custExtraNote" class="form-control" maxlength="255" placeholder="e.g. Delivery, packing, late fee">
          <div class="form-text">Shown on the receipt and tracked in Finances / Dashboard as pure profit.</div>
        </div>
      </div>
      <div class="small mb-3" id="custCollectOut" style="display:none;">
        Collecting: wallet <strong id="custWalletDueLabel">KES 0</strong>
        + charge <strong id="custChargeLabel">KES 0</strong>
        = <strong id="custCollectTotalLabel">KES 0</strong>
      </div>

      <div id="custCashBox" class="row g-2 mb-2" style="display:none;">
        <div class="col-md-6">
          <label class="form-label small">Cash given</label>
          <input type="number" step="0.01" min="0" name="amount_tendered" id="custCashGiven" class="form-control" placeholder="Optional — defaults to amount paid">
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

      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <div class="small text-muted">After payment, balance becomes: <strong id="balanceAfterOut">KES <?php echo number_format($customerBalance, 0); ?></strong></div>
        <button type="submit" class="btn btn-success btn-lg" id="custPayBtn"><i class="fas fa-check me-1"></i>Record payment</button>
      </div>
    </form>
    <?php endif; ?>

    <h2 class="h6 fw-bold mb-2">Payments</h2>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr class="text-muted small text-uppercase">
            <th>Date</th>
            <th class="text-end">Paid amount</th>
            <th>Mode of payment</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$customerPayments): ?>
            <tr><td colspan="3" class="text-muted text-center py-3">No payments recorded yet.</td></tr>
          <?php else: foreach ($customerPayments as $pay): ?>
            <tr>
              <td class="small"><?php echo date('j M Y, g:i a', strtotime($pay['created_at'])); ?></td>
              <td class="text-end fw-semibold">KES <?php echo number_format((float) $pay['amount'], 0); ?></td>
              <td><?php echo htmlspecialchars(PaymentOptions::label($pay)); ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <?php if ($customerPayments): ?>
        <tfoot>
          <tr class="border-top">
            <th>Total paid</th>
            <th class="text-end">KES <?php echo number_format($paidTotal, 0); ?></th>
            <th></th>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>
<?php if ($customerInvoices): ?>
<script>
(function(){
  var WALLET_DUE = <?php echo json_encode((float) $customerBalance); ?>;
  var amountInput = document.getElementById('custAmount');
  var modeSel = document.getElementById('custPayMode');
  var methodSel = document.getElementById('custPayMethod');
  var cashBox = document.getElementById('custCashBox');
  var detailBox = document.getElementById('custDetailBox');
  var detailInput = document.getElementById('custDetailInput');
  var detailLabel = document.getElementById('custDetailLabel');
  var detailList = document.getElementById('custDetailList');
  var refInput = document.getElementById('custRefInput');
  var chargeInput = document.getElementById('custExtraCharge');
  var chargeNote = document.getElementById('custExtraNote');
  var cardTypes = <?php echo json_encode(array_values($cardTypes)); ?>;
  var banks = <?php echo json_encode(array_values($banks)); ?>;
  var saccos = <?php echo json_encode(array_values($saccos)); ?>;
  function money(n){ return 'KES ' + (Math.round(n*100)/100).toLocaleString('en-KE', {maximumFractionDigits:0}); }
  function chargeAmt(){ return Math.max(0, Math.round((parseFloat(chargeInput && chargeInput.value)||0) * 100) / 100); }
  function dueWithCharge(){ return Math.round((WALLET_DUE + chargeAmt()) * 100) / 100; }
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
    if (modeSel.value === 'full') amountInput.value = dueWithCharge().toFixed(2);
    if (m === 'cash') { detailBox.style.display = 'none'; syncHidden(); return; }
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
    syncHidden();
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
    var due = dueWithCharge();
    var ch = chargeAmt();
    if (modeSel.value === 'full') amountInput.value = due.toFixed(2);
    var amt = modeSel.value === 'full' ? due : (parseFloat(amountInput.value)||0);
    if (amt > due) amt = due;
    var after = Math.max(0, Math.round((due - amt) * 100) / 100);
    document.getElementById('balanceAfterOut').textContent = money(after);
    document.getElementById('custPayBtn').innerHTML = '<i class="fas fa-check me-1"></i>Record ' + money(amt);
    var collectOut = document.getElementById('custCollectOut');
    if (collectOut) {
      if (ch > 0) {
        collectOut.style.display = '';
        document.getElementById('custWalletDueLabel').textContent = money(WALLET_DUE);
        document.getElementById('custChargeLabel').textContent = money(ch);
        document.getElementById('custCollectTotalLabel').textContent = money(due);
      } else {
        collectOut.style.display = 'none';
      }
    }
  }
  modeSel.addEventListener('change', syncDetails);
  methodSel.addEventListener('change', syncDetails);
  [amountInput, detailInput, refInput, document.getElementById('custCashGiven'), chargeInput, chargeNote].forEach(function(el){
    if (el) { el.addEventListener('input', syncHidden); el.addEventListener('change', syncHidden); }
  });
  document.getElementById('customerPayForm').addEventListener('submit', function(e){
    syncHidden();
    var ch = chargeAmt();
    if (ch > 0 && !(chargeNote.value || '').trim()) {
      e.preventDefault();
      alert('Enter a reason for the extra charge.');
      chargeNote.focus();
      return;
    }
    if (modeSel.value === 'deposit' && (parseFloat(amountInput.value)||0) <= 0) {
      e.preventDefault();
      alert('Enter the deposit amount.');
    }
  });
  syncDetails();
})();
</script>
<?php endif; ?>
<?php endif; /* openWallet */ ?>
<?php endif; /* customer found */ ?>

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
    $ledgerUrl = $paymentsBase . '?customer=' . urlencode($order['table_name'] ?? '') . (!empty($order['customer_id']) ? '&customer_id=' . (int) $order['customer_id'] : '') . '&open=1';
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
        <a class="btn btn-sm btn-outline-primary" href="<?php echo $ledgerUrl; ?>">Customer wallet</a>
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
.invoice-suggest-menu .bal.paid{color:#15803d;}
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
    // Summary first (name + total balance). Wallet opens only after click with &open=1.
    var url = '?customer=' + encodeURIComponent(name || '');
    if (id) url += '&customer_id=' + encodeURIComponent(id);
    if (preferDeposit) url += '&deposit=1';
    window.location.href = url;
  }
  function render(items, customers){
    menu.innerHTML = '';
    var any = false;
    var seen = {};
    (customers || []).forEach(function(c){
      any = true;
      var idKey = c.id ? String(c.id) : '';
      var nameKey = c.name ? ('n:' + String(c.name).toLowerCase()) : '';
      if (idKey) seen[idKey] = true;
      if (nameKey) seen[nameKey] = true;
      var bal = Number(c.credit_balance) || 0;
      var b = document.createElement('button');
      b.type = 'button';
      b.innerHTML = '<span class="bal"></span><strong></strong><span class="meta"></span>';
      b.querySelector('.bal').textContent = money(bal);
      if (bal <= 0) b.querySelector('.bal').classList.add('paid');
      b.querySelector('strong').textContent = (c.name || 'Customer') + (c.company_name ? ' · ' + c.company_name : '');
      b.querySelector('.meta').textContent = (bal > 0 ? 'Total balance' : 'No balance') + (c.phone ? ' · ' + c.phone : '');
      b.addEventListener('mousedown', function(e){
        e.preventDefault();
        if (hidden) hidden.value = c.id || '';
        goCustomer(c.name || '', c.id || 0);
      });
      menu.appendChild(b);
    });
    // Aggregate open invoices by customer — suggestions show total balance, not each invoice.
    var byCust = {};
    (items || []).forEach(function(it){
      var name = (it.customer_name || '').trim();
      if (!name) return;
      var id = it.customer_id || 0;
      var key = id ? String(id) : ('n:' + name.toLowerCase());
      if (seen[key] || seen['n:' + name.toLowerCase()]) return;
      if (!byCust[key]) {
        byCust[key] = { name: name, company_name: it.company_name || '', customer_id: id, balance: 0 };
      }
      byCust[key].balance += Number(it.balance) || 0;
      if (!byCust[key].company_name && it.company_name) byCust[key].company_name = it.company_name;
    });
    Object.keys(byCust).forEach(function(key){
      var it = byCust[key];
      any = true;
      var bal = Number(it.balance) || 0;
      var b = document.createElement('button');
      b.type = 'button';
      var title = it.name || 'Customer';
      if (it.company_name) title += ' · ' + it.company_name;
      b.innerHTML = '<span class="bal"></span><strong></strong><span class="meta"></span>';
      b.querySelector('.bal').textContent = money(bal);
      if (bal <= 0) b.querySelector('.bal').classList.add('paid');
      b.querySelector('strong').textContent = title;
      b.querySelector('.meta').textContent = 'Total balance';
      b.addEventListener('mousedown', function(e){
        e.preventDefault();
        goCustomer(it.name, it.customer_id || 0);
      });
      menu.appendChild(b);
    });
    if (any) menu.classList.add('show'); else hide();
  }
  input.addEventListener('input', function(){
    if (hidden) hidden.value = '';
    clearTimeout(timer);
    var q = input.value.trim();
    if (q.length < 2) { hide(); return; }
    timer = setTimeout(function(){
      Promise.all([
        fetch(custApi + '?q=' + encodeURIComponent(q) + '&limit=8').then(function(r){ return r.json(); }).catch(function(){ return {items:[]}; }),
        fetch(api + '?q=' + encodeURIComponent(q) + '&limit=20&open_only=1').then(function(r){ return r.json(); }).catch(function(){ return {items:[]}; })
      ]).then(function(res){
        render((res[1] && res[1].items) || [], (res[0] && res[0].items) || []);
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
