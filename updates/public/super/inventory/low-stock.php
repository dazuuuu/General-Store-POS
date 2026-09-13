<?php
// public/super/inventory/low-stock.php — unified low stock alerts
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::INVENTORY_VIEW);

$pdo = Database::pdo();
$P = new Models\ProductModel($pdo);
$tenant = (new Models\TenantModel($pdo))->find(TenantContext::tenantId());
$enabled = !isset($tenant['low_stock_alert_enabled']) || !empty($tenant['low_stock_alert_enabled']);
$rows = $enabled ? $P->lowStock(200) : [];
$page_title = 'Low stock alerts';
ob_start();
?>
<div class="card border-0 shadow-sm" style="border-radius:12px;">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div>
        <h2 class="h5 mb-1">Low stock alerts</h2>
        <p class="text-muted small mb-0">Products at or below their alert threshold.</p>
      </div>
      <a href="<?php echo public_url('super/stock/new.php'); ?>" class="btn btn-sm btn-primary"><i class="fas fa-boxes-stacked me-1"></i>Record products in bulk</a>
    </div>
    <?php if (!$enabled): ?>
      <div class="alert alert-warning mb-0">Low stock alerts are turned off in <a href="<?php echo public_url('super/settings/'); ?>">Settings</a>.</div>
    <?php elseif (!$rows): ?>
      <p class="text-muted mb-0">All products are above their thresholds. Nice work.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr class="text-muted small text-uppercase"><th>Product</th><th>Category</th><th class="text-end">In stock</th><th class="text-end">Alert at</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td class="fw-semibold"><?php echo htmlspecialchars($r['name']); ?></td>
            <td class="small text-muted"><?php echo htmlspecialchars($r['category_name'] ?: '—'); ?></td>
            <td class="text-end text-danger fw-bold"><?php echo rtrim(rtrim(number_format((float) $r['quantity'], 2), '0'), '.'); ?></td>
            <td class="text-end"><?php echo (int) $r['low_stock_threshold']; ?></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?php echo public_url('super/products/?id=' . (int) $r['id']); ?>">Edit</a></td>
          </tr>
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
