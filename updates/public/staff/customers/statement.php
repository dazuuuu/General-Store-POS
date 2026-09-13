<?php
// public/staff/customers/statement.php — single-customer credit report
// (invoices + payments). Supports print and CSV download.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::PAYMENTS_PROCESS);

$pdo = Database::pdo();
$O = new Models\OrderModel($pdo);
$CM = new Models\CustomerModel($pdo);
$isStaffViewer = TenantContext::role() === 'staff';
$paymentsBase = $isStaffViewer ? public_url('staff/payments/') : public_url('super/payments/');
$receiptBase = $isStaffViewer ? public_url('staff/orders/receipt.php') : public_url('super/orders/receipt.php');

$customerId = (int) ($_GET['customer_id'] ?? 0);
$name = trim((string) ($_GET['name'] ?? ''));
$format = strtolower(trim((string) ($_GET['format'] ?? 'html')));

if ($customerId > 0) {
    $cust = $CM->find($customerId);
    if ($cust) {
        $name = (string) ($cust['name'] ?? $name);
        $company = (string) ($cust['company_name'] ?? '');
        $phone = (string) ($cust['phone'] ?? '');
        $email = (string) ($cust['email'] ?? '');
    }
}
if ($name === '' && $customerId <= 0) {
    header('Location: ' . $paymentsBase);
    exit;
}

$company = $company ?? '';
$phone = $phone ?? '';
$email = $email ?? '';

$open = $O->openInvoicesForCustomer($customerId > 0 ? $customerId : null, $name);
$allOrders = $O->forCustomer($name, 300);
if ($customerId > 0) {
    // Also include orders linked by customer_id that may use a different typed name
    $extra = $O->openInvoicesForCustomer($customerId, '');
    $seen = array_column($open, 'id');
    foreach ($extra as $row) {
        if (!in_array($row['id'], $seen, true)) {
            $open[] = $row;
        }
    }
}
$payments = $O->paymentsForCustomer($customerId > 0 ? $customerId : null, $name, 500);

$openBalance = 0.0;
foreach ($open as &$inv) {
    $due = (float) ($inv['balance_due'] ?? $inv['amount_due'] ?? 0);
    if ($due <= 0.0001) {
        $due = max(0, (float) $inv['total'] - max(0, (float) ($inv['amount_paid'] ?? 0)));
    }
    $inv['_due'] = $due;
    $openBalance += $due;
}
unset($inv);

$totalPaid = 0.0;
foreach ($payments as $pay) {
    $totalPaid += (float) ($pay['amount'] ?? 0);
}

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="customer-report-' . preg_replace('/[^a-z0-9]+/i', '-', $name) . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Customer', $name]);
    if ($company !== '') fputcsv($out, ['Company', $company]);
    fputcsv($out, ['Open balance', number_format($openBalance, 2, '.', '')]);
    fputcsv($out, ['Total paid (recorded)', number_format($totalPaid, 2, '.', '')]);
    fputcsv($out, []);
    fputcsv($out, ['OPEN INVOICES']);
    fputcsv($out, ['Date', 'Invoice no', 'Amount']);
    foreach ($open as $inv) {
        fputcsv($out, [
            date('Y-m-d', strtotime($inv['created_at'])),
            $inv['receipt_number'] ?? '',
            number_format($inv['_due'], 2, '.', ''),
        ]);
    }
    fputcsv($out, []);
    fputcsv($out, ['PAYMENTS']);
    fputcsv($out, ['Date', 'Invoice no', 'Mode of payment', 'Amount paid']);
    foreach ($payments as $pay) {
        fputcsv($out, [
            date('Y-m-d H:i', strtotime($pay['created_at'])),
            $pay['receipt_number'] ?? '',
            PaymentOptions::label($pay),
            number_format((float) $pay['amount'], 2, '.', ''),
        ]);
    }
    fclose($out);
    exit;
}

$tenant = (new Models\TenantModel($pdo))->find(TenantContext::tenantId()) ?: [];
$shop = $tenant['name'] ?? 'Shop';
$page_title = 'Customer report — ' . $name;
ob_start();
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 noprint">
  <div>
    <h1 class="h5 fw-bold mb-1"><i class="fas fa-file-lines me-2 text-primary"></i>Customer report</h1>
    <div class="text-muted small"><?php echo htmlspecialchars($name); ?><?php if ($company !== ''): ?> · <?php echo htmlspecialchars($company); ?><?php endif; ?></div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $paymentsBase . '?customer=' . urlencode($name) . ($customerId ? '&customer_id=' . $customerId : ''); ?>">Back to payments</a>
    <a class="btn btn-sm btn-outline-primary" href="?name=<?php echo urlencode($name); ?>&customer_id=<?php echo (int) $customerId; ?>&format=csv"><i class="fas fa-download me-1"></i>CSV</a>
    <button class="btn btn-sm btn-primary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
  </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <div class="card-body p-4">
    <div class="fw-bold fs-5 mb-1"><?php echo htmlspecialchars($shop); ?></div>
    <div class="fw-semibold"><?php echo htmlspecialchars($name); ?></div>
    <?php if ($company !== ''): ?><div class="text-muted"><?php echo htmlspecialchars($company); ?></div><?php endif; ?>
    <?php if ($phone !== '' || $email !== ''): ?>
      <div class="text-muted small"><?php echo htmlspecialchars(trim($phone . ($phone && $email ? ' · ' : '') . $email)); ?></div>
    <?php endif; ?>
    <div class="row g-3 mt-3">
      <div class="col-md-4"><div class="text-muted small">Open credit</div><div class="fw-bold text-danger fs-5">KES <?php echo number_format($openBalance, 0); ?></div></div>
      <div class="col-md-4"><div class="text-muted small">Payments recorded</div><div class="fw-bold text-success fs-5">KES <?php echo number_format($totalPaid, 0); ?></div></div>
      <div class="col-md-4"><div class="text-muted small">Open invoices</div><div class="fw-bold fs-5"><?php echo count($open); ?></div></div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;overflow:hidden;">
  <div class="px-4 py-3 border-bottom fw-bold">Open invoices</div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr class="text-muted small text-uppercase"><th>Date</th><th>Invoice no</th><th class="text-end">Amount</th></tr></thead>
      <tbody>
        <?php if (!$open): ?><tr><td colspan="3" class="text-center text-muted py-3">No open invoices.</td></tr><?php endif; ?>
        <?php foreach ($open as $inv): ?>
        <tr>
          <td><?php echo date('j M Y', strtotime($inv['created_at'])); ?></td>
          <td>
            <a href="<?php echo $receiptBase . '?id=' . (int) $inv['id']; ?>"><?php echo htmlspecialchars($inv['receipt_number'] ?? ''); ?></a>
          </td>
          <td class="text-end fw-semibold text-danger">KES <?php echo number_format($inv['_due'], 0); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
  <div class="px-4 py-3 border-bottom fw-bold">Payments</div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr class="text-muted small text-uppercase"><th>Date</th><th>Invoice no</th><th>Mode of payment</th><th class="text-end">Amount paid</th></tr></thead>
      <tbody>
        <?php if (!$payments): ?><tr><td colspan="4" class="text-center text-muted py-3">No payments recorded yet.</td></tr><?php endif; ?>
        <?php foreach ($payments as $pay): ?>
        <tr>
          <td><?php echo date('j M Y, g:i a', strtotime($pay['created_at'])); ?></td>
          <td><?php echo htmlspecialchars($pay['receipt_number'] ?? ''); ?></td>
          <td><?php echo htmlspecialchars(PaymentOptions::label($pay)); ?></td>
          <td class="text-end fw-semibold">KES <?php echo number_format((float) $pay['amount'], 0); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
@media print {
  .noprint { display:none !important; }
  .card { box-shadow:none !important; border:1px solid #ddd !important; }
}
</style>
<?php
$content = ob_get_clean();
$__layout = $isStaffViewer ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
