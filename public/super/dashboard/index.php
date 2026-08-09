<?php
// public/super/dashboard/index.php — owner analytics dashboard
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();

$pdo = Database::pdo();
$__tenant = (new Models\TenantModel($pdo))->find(TenantContext::tenantId());
$currency = $__tenant['currency'] ?? 'KES';

/** Paid orders + legacy direct sales, merged newest-first (mirrors super/sales/index.php). */
function dash_sales(Models\SaleModel $SA, Models\OrderModel $OR, string $period): array
{
    $sales = $SA->forTenant(1000, $period);
    foreach ($sales as &$s) { $s['receipt_url'] = 'super/sales/receipt.php?id=' . (int) $s['id']; $s['source'] = 'sale'; }
    unset($s);
    $orders = $OR->forTenant(1000, $period);
    foreach ($orders as &$o) { $o['receipt_url'] = 'super/orders/receipt.php?id=' . (int) $o['id']; }
    unset($o);
    $merged = array_merge($sales, $orders);
    usort($merged, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
    return $merged;
}

$SA = new Models\SaleModel($pdo);
$OR = new Models\OrderModel($pdo);
$P  = new Models\ProductModel($pdo);
$svc = new StaffService($pdo);

$today = dash_sales($SA, $OR, 'today');
$week  = dash_sales($SA, $OR, 'week');
$todaySum = Models\SaleModel::summarize($today);
$weekSum  = Models\SaleModel::summarize($week);

$lowStock  = $P->lowStock();
$staffList = $svc->listForTenant((int) TenantContext::tenantId());
$openTabs  = $OR->openOrders();

// Last 7 days revenue for the chart (oldest first).
$chartLabels = [];
$chartValues = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $chartLabels[] = date('D', strtotime($date));
    $dayTotal = 0.0;
    foreach ($week as $r) {
        if (date('Y-m-d', strtotime($r['created_at'])) === $date) { $dayTotal += (float) $r['total']; }
    }
    $chartValues[] = round($dayTotal, 2);
}

$recent = array_slice($today ?: $week, 0, 8);
$recentSaleIds  = array_column(array_filter($recent, fn($s) => ($s['source'] ?? 'sale') === 'sale'), 'id');
$recentOrderIds = array_column(array_filter($recent, fn($s) => ($s['source'] ?? 'sale') === 'order'), 'id');
$recentItemsBySale  = $SA->itemsForMany($recentSaleIds);
$recentItemsByOrder = $OR->itemsForMany($recentOrderIds);
foreach ($recent as &$r) {
    $r['items'] = (($r['source'] ?? 'sale') === 'order' ? $recentItemsByOrder : $recentItemsBySale)[(int) $r['id']] ?? [];
}
unset($r);

$page_title = 'Dashboard';
$shop = $__tenant['name'] ?? 'your shop';
ob_start();
?>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100" style="border-radius:16px;">
      <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <span class="text-muted small text-uppercase fw-semibold">Today's Revenue</span>
          <span class="dash-ic" style="background:#f0fdf4;color:var(--pos-green);"><i class="fas fa-sack-dollar"></i></span>
        </div>
        <div class="h4 mb-0 fw-bold"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($todaySum['revenue'], 0); ?></div>
        <div class="text-muted small"><?php echo $todaySum['count']; ?> sale<?php echo $todaySum['count'] !== 1 ? 's' : ''; ?> today</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100" style="border-radius:16px;">
      <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <span class="text-muted small text-uppercase fw-semibold">7-day Revenue</span>
          <span class="dash-ic" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-chart-line"></i></span>
        </div>
        <div class="h4 mb-0 fw-bold"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($weekSum['revenue'], 0); ?></div>
        <div class="text-muted small"><?php echo $weekSum['count']; ?> sale<?php echo $weekSum['count'] !== 1 ? 's' : ''; ?> this week</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100" style="border-radius:16px;">
      <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <span class="text-muted small text-uppercase fw-semibold">Open Tabs</span>
          <span class="dash-ic" style="background:#fffbeb;color:#d97706;"><i class="fas fa-receipt"></i></span>
        </div>
        <div class="h4 mb-0 fw-bold"><?php echo count($openTabs); ?></div>
        <div class="text-muted small">unpaid right now</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100" style="border-radius:16px;">
      <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <span class="text-muted small text-uppercase fw-semibold">Low Stock</span>
          <span class="dash-ic" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-box"></i></span>
        </div>
        <div class="h4 mb-0 fw-bold <?php echo $lowStock ? 'text-danger' : ''; ?>"><?php echo count($lowStock); ?></div>
        <div class="text-muted small"><?php echo count($staffList); ?> staff on the team</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-12 col-lg-8">
    <div class="card border-0 shadow-sm h-100" style="border-radius:16px;">
      <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3">Sales dynamics <span class="text-muted fw-normal small">last 7 days</span></h2>
        <canvas id="salesChart" height="90"></canvas>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="card border-0 shadow-sm h-100" style="border-radius:16px;">
      <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3"><i class="fas fa-triangle-exclamation me-2 text-warning"></i>Low stock</h2>
        <?php if (!$lowStock): ?>
          <div class="text-muted small">Nothing running low. Nice.</div>
        <?php else: ?>
          <?php foreach (array_slice($lowStock, 0, 6) as $p): ?>
            <div class="d-flex justify-content-between border-bottom py-2">
              <span class="small fw-semibold"><?php echo htmlspecialchars($p['name']); ?></span>
              <span class="small text-danger fw-semibold"><?php echo rtrim(rtrim(number_format((float) $p['quantity'], 2), '0'), '.'); ?> left</span>
            </div>
          <?php endforeach; ?>
          <a class="small d-block mt-2" href="<?php echo public_url('super/inventory/'); ?>">View inventory →</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius:16px;">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="h6 fw-bold mb-0">Recent sales</h2>
      <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url('super/sales/'); ?>">View all</a>
    </div>
    <?php if (!$recent): ?>
      <div class="text-muted small text-center py-4">No sales recorded yet.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr class="text-muted small text-uppercase"><th>Customer</th><th>Products</th><th>When</th><th>Staff</th><th>Pay</th><th class="text-end">Amount</th></tr></thead>
          <tbody>
            <?php foreach ($recent as $r): ?>
            <tr>
              <td class="small fw-semibold">
                <?php if ($r['customer_name'] && $r['customer_name'] !== 'Walk-in Customer'): ?>
                  <a href="<?php echo public_url('super/sales/customer.php?name=' . urlencode($r['customer_name'])); ?>"><?php echo htmlspecialchars($r['customer_name']); ?></a>
                <?php else: ?>
                  <?php echo htmlspecialchars($r['customer_name'] ?: '—'); ?>
                <?php endif; ?>
              </td>
              <td class="small"><?php echo Models\SaleModel::itemsSummaryHtml($r['items']); ?></td>
              <td class="small text-muted"><?php echo date('j M, g:i a', strtotime($r['created_at'])); ?></td>
              <td class="small"><?php echo htmlspecialchars($r['staff_name'] ?: '—'); ?></td>
              <td class="small"><span class="badge bg-light text-dark"><?php echo htmlspecialchars(ucfirst($r['payment_method'] ?? 'cash')); ?></span></td>
              <td class="text-end small fw-semibold"><?php echo htmlspecialchars($currency); ?> <?php echo number_format((float) $r['total'], 0); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<style>.dash-ic{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.9rem;}</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('salesChart'), {
  type: 'bar',
  data: {
    labels: <?php echo json_encode($chartLabels); ?>,
    datasets: [{ data: <?php echo json_encode($chartValues); ?>, backgroundColor: '#16a34a', borderRadius: 6, maxBarThickness: 40 }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { callback: function (v) { return (v / 1000) + 'k'; } } } }
  }
});
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
