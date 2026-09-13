<?php
// public/super/reports/index.php — daily sales report (view / print / PDF)
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();

$pdo  = Database::pdo();
$date = preg_replace('/[^0-9-]/', '', $_GET['date'] ?? '') ?: date('Y-m-d');
$data = SalesReport::data($pdo, TenantContext::tenantId(), $date);

$cur = $data['shop']['currency'] ?: 'KES';
$sum = $data['sum'];
$money = fn($v) => $cur . ' ' . number_format((float) $v, 0);
$isToday = ($date === date('Y-m-d'));

$page_title = 'Sales report';
ob_start();
?>
<style>
@media print {
  .no-print, .sidebar, nav, header, footer { display:none !important; }
  .print-area { box-shadow:none !important; border:0 !important; }
  body { background:#fff !important; }
}
</style>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2 no-print">
  <h1 class="h5 mb-0 fw-bold">Daily Sales Report</h1>
  <form method="get" class="d-flex align-items-center gap-2">
    <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>" max="<?php echo date('Y-m-d'); ?>" class="form-control form-control-sm" style="width:auto;">
    <button class="btn btn-sm btn-primary" type="submit">View</button>
    <a class="btn btn-sm btn-outline-secondary" href="download.php?date=<?php echo urlencode($date); ?>"><i class="fas fa-file-pdf me-1"></i>PDF</a>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
  </form>
</div>

<div class="card border-0 shadow-sm print-area" style="border-radius:14px;">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
      <div>
        <div class="h5 fw-bold mb-0"><?php echo htmlspecialchars($data['shop']['name'] ?: 'Shop'); ?></div>
        <div class="text-muted"><?php echo date('l, j F Y', strtotime($date)); ?><?php echo $isToday ? ' · today' : ''; ?></div>
      </div>
      <div class="text-end small text-muted">
        <?php if ($data['shop']['phone']): ?><div><?php echo htmlspecialchars($data['shop']['phone']); ?></div><?php endif; ?>
        <?php if ($data['shop']['address']): ?><div><?php echo htmlspecialchars($data['shop']['address']); ?></div><?php endif; ?>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <?php foreach ([['Sales',$sum['count']],['Revenue',$money($sum['revenue'])],['Cash',$money($sum['cash'])],['M-Pesa',$money($sum['mpesa'])],['Card',$money($sum['card'] ?? 0)],['Bank',$money($sum['bank'] ?? 0)],['SACCO',$money($sum['sacco'] ?? 0)]] as $b): ?>
      <div class="col-6 col-md-3 col-xl">
        <div class="p-3" style="background:#f7f8fa;border:1px solid #e6e8ec;border-radius:10px;">
          <div class="text-muted small text-uppercase fw-semibold"><?php echo $b[0]; ?></div>
          <div class="h5 mb-0 fw-bold"><?php echo is_string($b[1]) ? htmlspecialchars($b[1]) : $b[1]; ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <h2 class="h6 fw-bold mb-2">Sales <span class="text-muted">(<?php echo $sum['count']; ?>)</span></h2>
    <?php if (!$data['sales']): ?>
      <p class="text-muted">No sales recorded on this day.</p>
    <?php else: ?>
    <div class="table-responsive mb-4">
      <table class="table table-sm align-middle mb-0">
        <thead><tr class="text-muted small text-uppercase"><th>Receipt</th><th>Time</th><th>Staff</th><th>Customer</th><th>Pay</th><th class="text-end">Total</th></tr></thead>
        <tbody>
          <?php foreach ($data['sales'] as $s): ?>
          <tr>
            <td class="small fw-semibold"><?php echo htmlspecialchars($s['receipt_number']); ?></td>
            <td class="small text-nowrap"><?php echo date('g:i a', strtotime($s['created_at'])); ?></td>
            <td class="small"><?php echo htmlspecialchars($s['staff_name'] ?: '—'); ?></td>
            <td class="small"><?php echo htmlspecialchars($s['customer_name'] ?: '—'); ?></td>
            <td><?php
              $pm = $s['payment_method'] ?? '';
              $badge = match ($pm) {
                  'cash' => '<span class="badge bg-light text-dark">Cash</span>',
                  'mpesa' => '<span class="badge bg-success text-white">M-Pesa</span>',
                  'split' => '<span class="badge bg-secondary">Split</span>',
                  'card' => '<span class="badge bg-dark">Card</span>',
                  'bank' => '<span class="badge bg-info text-dark">Bank</span>',
                  'sacco' => '<span class="badge bg-primary">SACCO</span>',
                  'credit' => '<span class="badge bg-warning text-dark">Credit</span>',
                  default => '<span class="badge bg-light text-dark">'.htmlspecialchars(ucfirst($pm ?: '—')).'</span>',
              };
              echo $badge;
              $detail = PaymentOptions::label($s);
              if ($detail !== ucfirst($pm ?: '')) {
                  echo '<div class="text-muted" style="font-size:.7rem;">' . htmlspecialchars($detail) . '</div>';
              }
            ?></td>
            <td class="text-end fw-semibold"><?php echo $money($s['total']); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if ($data['products']): ?>
    <h2 class="h6 fw-bold mb-2">Products sold</h2>
    <div class="table-responsive mb-4">
      <table class="table table-sm align-middle mb-0">
        <thead><tr class="text-muted small text-uppercase"><th>Product</th><th class="text-end">Qty</th><th class="text-end">Revenue</th></tr></thead>
        <tbody>
          <?php foreach ($data['products'] as $p): ?>
          <tr>
            <td class="small"><?php echo htmlspecialchars($p['product_name']); ?></td>
            <td class="text-end small"><?php echo rtrim(rtrim(number_format((float)$p['qty'],2),'0'),'.'); ?></td>
            <td class="text-end fw-semibold"><?php echo $money($p['revenue']); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if ($data['staff']): ?>
    <h2 class="h6 fw-bold mb-2">By staff member</h2>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr class="text-muted small text-uppercase"><th>Staff</th><th class="text-end">Sales</th><th class="text-end">Revenue</th></tr></thead>
        <tbody>
          <?php foreach ($data['staff'] as $name => $d): ?>
          <tr><td class="small"><?php echo htmlspecialchars($name); ?></td><td class="text-end small"><?php echo $d['count']; ?></td><td class="text-end fw-semibold"><?php echo $money($d['revenue']); ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
