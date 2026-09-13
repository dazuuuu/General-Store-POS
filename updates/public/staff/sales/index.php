<?php
// public/staff/sales/index.php — this staff member's own sales.
// Defaults to TODAY only (resets every day); pass ?period=all for history.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::SALES_VIEW);

$pdo = Database::pdo();
$SA  = new Models\SaleModel($pdo);
$OR  = new Models\OrderModel($pdo);
$myId = TenantContext::userId();

/** This staff member's legacy sales + paid orders, merged newest-first. */
function my_sales(Models\SaleModel $SA, Models\OrderModel $OR, int $staffId, string $period): array
{
    $sales = $SA->forStaff($staffId, 500, $period === 'today' ? date('Y-m-d') : null);
    if ($period !== 'today' && $period !== 'all') {
        // forStaff() only supports "today" or "all" — filter week/month here.
        $cut = $period === 'week' ? strtotime('-7 days') : strtotime('-30 days');
        $sales = array_values(array_filter($sales, fn($s) => strtotime($s['created_at']) >= $cut));
    }
    foreach ($sales as &$s) { $s['receipt_url'] = 'staff/sales/receipt.php?id=' . (int) $s['id']; $s['source'] = 'sale'; }
    unset($s);
    $merged = array_merge($sales, $OR->forTenant(500, $period, $staffId));
    usort($merged, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
    return $merged;
}

$allowed = ['today', 'week', 'month', 'all'];
$period  = in_array($_GET['period'] ?? '', $allowed, true) ? $_GET['period'] : 'today';

$todaySales = my_sales($SA, $OR, $myId, 'today');
$todaySum   = Models\SaleModel::summarize($todaySales);

$sales = $period === 'today' ? $todaySales : my_sales($SA, $OR, $myId, $period);
$sum   = $period === 'today' ? $todaySum : Models\SaleModel::summarize($sales);

// Batch-load line items for the products column — one query per source,
// not one per row.
$saleIds  = array_column(array_filter($sales, fn($s) => ($s['source'] ?? 'sale') === 'sale'), 'id');
$orderIds = array_column(array_filter($sales, fn($s) => ($s['source'] ?? 'sale') === 'order'), 'id');
$itemsBySale  = $SA->itemsForMany($saleIds);
$itemsByOrder = $OR->itemsForMany($orderIds);
foreach ($sales as &$s) {
    $s['items'] = (($s['source'] ?? 'sale') === 'order' ? $itemsByOrder : $itemsBySale)[(int) $s['id']] ?? [];
}
unset($s);

$__tenant     = (new Models\TenantModel($pdo))->find(TenantContext::tenantId());
$tenantSlug   = $__tenant['slug'] ?? '';
$shopName     = $__tenant['name'] ?? 'Our Shop';
$catalogueUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
              . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
              . public_url('catalogue.php?shop=' . urlencode($tenantSlug));

$periodLabel = ['today' => "Today — " . date('j M Y'), 'week' => 'Last 7 days', 'month' => 'Last 30 days', 'all' => 'All time'][$period];

$page_title = 'My sales';
ob_start();
?>
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;">
      <div class="card-body p-3">
        <div class="text-muted small text-uppercase fw-semibold">Today</div>
        <div class="h5 mb-0 mt-1 fw-bold">KES <?php echo number_format($todaySum['revenue'], 0); ?></div>
        <div class="text-muted small"><?php echo $todaySum['count']; ?> sale<?php echo $todaySum['count'] !== 1 ? 's' : ''; ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;">
      <div class="card-body p-3">
        <div class="text-muted small text-uppercase fw-semibold"><?php echo $period === 'today' ? 'Shown' : htmlspecialchars($periodLabel); ?></div>
        <div class="h5 mb-0 mt-1 fw-bold">KES <?php echo number_format($sum['revenue'], 0); ?></div>
        <div class="text-muted small"><?php echo $sum['count']; ?> sale<?php echo $sum['count'] !== 1 ? 's' : ''; ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;">
      <div class="card-body p-3">
        <div class="text-muted small text-uppercase fw-semibold">Credit owed</div>
        <div class="h5 mb-0 mt-1 fw-bold text-danger">KES <?php echo number_format($sum['credit_due'] ?? 0, 0); ?></div>
        <div class="text-muted small"><?php echo (int) ($sum['credit_count'] ?? 0); ?> credit sale<?php echo (int) ($sum['credit_count'] ?? 0) !== 1 ? 's' : ''; ?></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-3 d-flex align-items-center gap-2 flex-wrap">
    <a href="<?php echo public_url('staff/dashboard/'); ?>" class="btn btn-primary"><i class="fas fa-cash-register me-1"></i>Home</a>
    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#shareCatalogueModal">
      <i class="fas fa-share-nodes me-1"></i>Share Catalogue
    </button>
  </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius:14px;">
  <div class="card-body p-4">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <h2 class="h6 fw-bold mb-0">
        <?php echo htmlspecialchars($periodLabel); ?>
        <span class="badge bg-light text-dark ms-1"><?php echo count($sales); ?></span>
      </h2>
      <div class="btn-group">
        <?php foreach (['today' => 'Today', 'week' => '7 days', 'month' => '30 days', 'all' => 'History'] as $p => $lbl): ?>
          <a href="?period=<?php echo $p; ?>" class="btn btn-sm <?php echo $period === $p ? 'btn-primary' : 'btn-outline-secondary'; ?>"><?php echo $lbl; ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (!$sales): ?>
      <div class="text-muted py-4 text-center">
        <i class="fas fa-receipt fa-2x d-block mb-2" style="opacity:.25;"></i>
        <?php echo $period === 'today' ? 'No sales recorded today yet.' : 'No sales in this period.'; ?>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr class="text-muted small text-uppercase"><th>Receipt</th><th>When</th><th>Customer</th><th>Products</th><th>Pay</th><th class="text-end">Total</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($sales as $s): ?>
            <tr>
              <td class="fw-semibold small"><?php echo htmlspecialchars($s['receipt_number']); ?></td>
              <td class="small text-nowrap"><?php echo date('j M, g:i a', strtotime($s['created_at'])); ?></td>
              <td class="small">
                <?php if ($s['customer_name'] && $s['customer_name'] !== 'Walk-in Customer'): ?>
                  <a href="<?php echo public_url('staff/sales/customer.php?name=' . urlencode($s['customer_name'])); ?>"><?php echo htmlspecialchars($s['customer_name']); ?></a>
                <?php else: ?>
                  <?php echo htmlspecialchars($s['customer_name'] ?: '—'); ?>
                <?php endif; ?>
              </td>
              <td class="small"><?php echo Models\SaleModel::itemsSummaryHtml($s['items']); ?></td>
              <td class="small"><?php
                echo Models\SaleModel::paymentStatusBadge($s);
                echo ' <span class="text-muted">' . htmlspecialchars(Models\SaleModel::paymentLabel($s)) . '</span>';
                if ((float)($s['amount_due'] ?? 0) > 0.0001) {
                    echo '<div class="text-danger" style="font-size:.7rem;">Owes KES ' . number_format((float)$s['amount_due'], 0) . '</div>';
                }
              ?></td>
              <td class="text-end fw-semibold small">KES <?php echo number_format((float) $s['total'], 0); ?></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <a class="btn btn-outline-secondary" href="<?php echo public_url($s['receipt_url']); ?>">Receipt</a>
                  <?php if (TenantContext::can(Capabilities::SALES_RECORD)): ?>
                    <a class="btn btn-outline-primary" href="<?php echo public_url('staff/returns/?receipt=' . urlencode($s['receipt_number'])); ?>">Return</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../../components/tenants/share_modal.php'; ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/staff/layout.php';
