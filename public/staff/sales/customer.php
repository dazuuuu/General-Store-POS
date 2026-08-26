<?php
// public/staff/sales/customer.php?name=... — everything about one customer:
// contact info, total paid / owed, every product they've bought, and a
// receipt link per visit. Reached from the "Customer" column on the Sales
// pages and the owner dashboard. A "customer" here is just the name typed
// at checkout (no customer accounts exist), matched case-insensitively —
// same person, however it was typed, but not a verified identity.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::SALES_VIEW);

$pdo = Database::pdo();
$SA  = new Models\SaleModel($pdo);
$OR  = new Models\OrderModel($pdo);

$isStaffViewer = TenantContext::role() === 'staff';
$prefix        = $isStaffViewer ? 'staff/' : 'super/';
$listUrl       = public_url($prefix . 'sales/');

$name = trim($_GET['name'] ?? '');
if ($name === '') {
    header('Location: ' . $listUrl);
    exit;
}

$sales = $SA->forCustomer($name, 300);
foreach ($sales as &$s) {
    $s['receipt_url'] = $prefix . 'sales/receipt.php?id=' . (int) $s['id'];
    $s['source']      = 'sale';
}
unset($s);

$orders = $OR->forCustomer($name, 300);
foreach ($orders as &$o) {
    $o['receipt_url']   = $prefix . 'orders/receipt.php?id=' . (int) $o['id'];
    $o['source']        = 'order';
    $o['customer_name'] = $o['table_name'];
}
unset($o);

$visits = array_merge($sales, $orders);
usort($visits, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));

// Contact info: newest non-empty phone/email across every visit.
$phone = null; $email = null;
foreach ($visits as $v) {
    if ($phone === null && !empty($v['customer_phone'])) { $phone = $v['customer_phone']; }
    if ($email === null && !empty($v['customer_email'])) { $email = $v['customer_email']; }
}

// Paid vs owed. Orders: 'paid' status is settled, 'open' is still owed (this
// is what the credit-sale/invoice flow leaves behind). Legacy sales: any
// payment_status other than 'paid' still has money outstanding.
$paidTotal = 0.0; $owedTotal = 0.0; $visitCount = 0;
foreach ($visits as $v) {
    $visitCount++;
    if ($v['source'] === 'order') {
        if ($v['status'] === 'paid') {
            $paidTotal += (float) $v['total'];
        } elseif ($v['status'] === 'open') {
            $paidTotal += max(0, (float) ($v['amount_paid'] ?? 0));
            $due = (float) ($v['amount_due'] ?? 0);
            if ($due <= 0.0001) { $due = max(0, (float) $v['total'] - (float) ($v['amount_paid'] ?? 0)); }
            $owedTotal += $due;
        }
    } else {
        if (($v['payment_status'] ?? 'paid') === 'paid') { $paidTotal += (float) $v['total']; }
        else { $owedTotal += (float) ($v['amount_due'] ?? $v['total']); }
    }
}
$lastVisit = $visits[0]['created_at'] ?? null;

// Batch-load every visit's line items, and roll them up into one "what have
// they bought, ever" product summary.
$itemsBySale  = $SA->itemsForMany(array_column($sales, 'id'));
$itemsByOrder = $OR->itemsForMany(array_column($orders, 'id'));
$productAgg = [];
foreach ($visits as &$v) {
    $items = ($v['source'] === 'order' ? $itemsByOrder : $itemsBySale)[(int) $v['id']] ?? [];
    $v['items'] = $items;
    foreach ($items as $it) {
        if (!isset($productAgg[$it['name']])) { $productAgg[$it['name']] = ['qty' => 0.0, 'total' => 0.0]; }
        $productAgg[$it['name']]['qty']   += $it['qty'];
        $productAgg[$it['name']]['total'] += $it['total'];
    }
}
unset($v);
uasort($productAgg, fn($a, $b) => $b['total'] <=> $a['total']);

$page_title = $name;
ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h1 class="h5 fw-bold mb-1"><i class="fas fa-user me-2 text-primary"></i><?php echo htmlspecialchars($name); ?></h1>
    <p class="text-muted small mb-0">
      <?php if ($phone): ?><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($phone); ?><?php endif; ?>
      <?php if ($phone && $email): ?> &nbsp;·&nbsp; <?php endif; ?>
      <?php if ($email): ?><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($email); ?><?php endif; ?>
      <?php if (!$phone && !$email): ?>No phone or email on file.<?php endif; ?>
    </p>
  </div>
  <a href="<?php echo $listUrl; ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to sales</a>
</div>

<?php if (!$visits): ?>
  <div class="card border-0 shadow-sm" style="border-radius:14px;">
    <div class="card-body p-5 text-center text-muted">
      <i class="fas fa-user-slash fa-2x mb-3 d-block" style="opacity:.25;"></i>
      No purchases found for "<?php echo htmlspecialchars($name); ?>".
    </div>
  </div>
<?php else: ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;">
      <div class="card-body p-3">
        <div class="text-muted small text-uppercase fw-semibold">Total paid</div>
        <div class="h5 mb-0 mt-1 fw-bold text-success">KES <?php echo number_format($paidTotal, 0); ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;">
      <div class="card-body p-3">
        <div class="text-muted small text-uppercase fw-semibold">Still owed</div>
        <div class="h5 mb-0 mt-1 fw-bold <?php echo $owedTotal > 0 ? 'text-danger' : ''; ?>">KES <?php echo number_format($owedTotal, 0); ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;">
      <div class="card-body p-3">
        <div class="text-muted small text-uppercase fw-semibold">Visits</div>
        <div class="h5 mb-0 mt-1 fw-bold"><?php echo $visitCount; ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;">
      <div class="card-body p-3">
        <div class="text-muted small text-uppercase fw-semibold">Last visit</div>
        <div class="h6 mb-0 mt-1 fw-bold"><?php echo $lastVisit ? date('j M Y', strtotime($lastVisit)) : '—'; ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-12 col-lg-5">
    <div class="card border-0 shadow-sm" style="border-radius:14px;">
      <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3"><i class="fas fa-box me-2 text-primary"></i>Products bought</h2>
        <?php if (!$productAgg): ?>
          <div class="text-muted small">No line items recorded.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr class="text-muted small text-uppercase"><th>Product</th><th class="text-end">Qty</th><th class="text-end">Spent</th></tr></thead>
              <tbody>
                <?php foreach ($productAgg as $pname => $agg):
                    $qtyLabel = rtrim(rtrim(number_format($agg['qty'], 2), '0'), '.');
                ?>
                <tr>
                  <td class="small fw-semibold"><?php echo htmlspecialchars($pname); ?></td>
                  <td class="text-end small"><?php echo $qtyLabel; ?></td>
                  <td class="text-end small">KES <?php echo number_format($agg['total'], 0); ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-7">
    <div class="card border-0 shadow-sm" style="border-radius:14px;">
      <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3"><i class="fas fa-receipt me-2 text-primary"></i>Purchase history</h2>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr class="text-muted small text-uppercase"><th>Receipt</th><th>When</th><th>Products</th><th>Status</th><th class="text-end">Total</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($visits as $v):
                  $isOrder = $v['source'] === 'order';
                  $status = $isOrder ? $v['status'] : ($v['payment_status'] ?? 'paid');
              ?>
              <tr>
                <td class="fw-semibold small"><?php echo htmlspecialchars($v['receipt_number']); ?></td>
                <td class="small text-nowrap"><?php echo date('j M, g:i a', strtotime($v['created_at'])); ?></td>
                <td class="small"><?php echo Models\SaleModel::itemsSummaryHtml($v['items']); ?></td>
                <td class="small">
                  <?php if ($status === 'paid'): ?><span class="badge bg-success">Paid</span>
                  <?php elseif ($status === 'open'): ?><span class="badge bg-warning text-dark">Owes</span>
                  <?php else: ?><span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst($status)); ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-end fw-semibold small">
                  KES <?php echo number_format((float) $v['total'], 0); ?>
                  <?php if ($isOrder && $v['status'] === 'open' && (float) ($v['amount_paid'] ?? 0) > 0): ?>
                    <div class="text-muted" style="font-size:.75rem;">paid KES <?php echo number_format((float) $v['amount_paid'], 0); ?></div>
                  <?php endif; ?>
                </td>
                <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url($v['receipt_url']); ?>">Receipt</a></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
$__layout = TenantContext::role() === 'staff' ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
