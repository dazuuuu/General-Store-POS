<?php
// public/super/purchases/index.php — purchases hub / view list
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::STOCK_ENTER);

$pdo = Database::pdo();
$PUR = new Models\PurchaseModel($pdo);
$summary = $PUR->summary();
$rows = $PUR->listWithMeta([], 80);
$base = public_url('super/purchases/');

$page_title = 'Purchases';
ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h1 class="h5 fw-bold mb-1">Purchases</h1>
    <p class="text-muted small mb-0">Record supplier buys with receipts, track them, then transfer into Store warehouse with wholesale/retail prices before shop Inventory.</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $base; ?>track.php"><i class="fas fa-magnifying-glass me-1"></i>Track / search</a>
    <a class="btn btn-sm btn-outline-primary" href="<?php echo $base; ?>transfer.php"><i class="fas fa-right-left me-1"></i>Transfer</a>
    <a class="btn btn-sm btn-primary" href="<?php echo $base; ?>new.php"><i class="fas fa-plus me-1"></i>Record purchase</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:12px;"><div class="card-body p-3">
      <div class="text-muted small text-uppercase fw-semibold">Purchases</div>
      <div class="h4 mb-0 fw-bold"><?php echo (int) $summary['purchase_count']; ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:12px;"><div class="card-body p-3">
      <div class="text-muted small text-uppercase fw-semibold">Pending items</div>
      <div class="h4 mb-0 fw-bold text-warning"><?php echo (int) $summary['pending_items']; ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:12px;"><div class="card-body p-3">
      <div class="text-muted small text-uppercase fw-semibold">Pending cost</div>
      <div class="h5 mb-0 fw-bold">KES <?php echo number_format((float) $summary['pending_cost'], 0); ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:12px;"><div class="card-body p-3">
      <div class="text-muted small text-uppercase fw-semibold">Transferred</div>
      <div class="h4 mb-0 fw-bold text-success"><?php echo (int) $summary['transferred_items']; ?></div>
    </div></div>
  </div>
</div>

<?php if (!$rows): ?>
  <div class="card border-0 shadow-sm" style="border-radius:14px;">
    <div class="card-body p-5 text-center text-muted">
      <i class="fas fa-cart-shopping fa-2x mb-3 d-block" style="opacity:.25;"></i>
      No purchases yet. <a href="<?php echo $base; ?>new.php">Record your first purchase</a>.
    </div>
  </div>
<?php else: ?>
  <div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr class="text-muted small text-uppercase">
            <th>Date</th>
            <th>Products</th>
            <th>Supplier / shop</th>
            <th>Receipt</th>
            <th class="text-end">Items</th>
            <th class="text-end">Cost</th>
            <th>Status</th>
            <th>By</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
              $date = $r['purchase_date'] ?: date('Y-m-d', strtotime($r['created_at']));
              $shop = $r['shop_name'] ?: ($r['supplier_name'] ?: '—');
              $names = trim((string) ($r['product_names'] ?? ''));
              $statusClass = match ($r['status']) {
                  'transferred' => 'success',
                  'partial' => 'warning',
                  default => 'secondary',
              };
          ?>
          <tr>
            <td class="small"><?php echo htmlspecialchars(date('j M Y', strtotime($date))); ?></td>
            <td>
              <div class="fw-semibold small"><?php echo $names !== '' ? htmlspecialchars($names) : '<span class="text-muted">Untitled items</span>'; ?></div>
            </td>
            <td class="fw-semibold small"><?php echo htmlspecialchars($shop); ?></td>
            <td class="small">
              <?php if (!empty($r['receipt_number'])): ?>
                <span class="fw-semibold"><?php echo htmlspecialchars($r['receipt_number']); ?></span>
              <?php else: ?><span class="text-muted">—</span><?php endif; ?>
              <?php if (!empty($r['receipt_image_path'])): ?>
                <a class="ms-1" href="<?php echo htmlspecialchars($r['receipt_image_path']); ?>" target="_blank" title="View receipt"><i class="fas fa-image"></i></a>
              <?php endif; ?>
            </td>
            <td class="text-end small">
              <?php echo (int) $r['item_count']; ?>
              <?php if ((int) $r['pending_count'] > 0): ?>
                <div class="text-warning" style="font-size:.7rem;"><?php echo (int) $r['pending_count']; ?> pending</div>
              <?php endif; ?>
            </td>
            <td class="text-end small">KES <?php echo number_format((float) $r['cost_total'], 0); ?></td>
            <td><span class="badge bg-<?php echo $statusClass; ?>"><?php echo htmlspecialchars(ucfirst($r['status'])); ?></span></td>
            <td class="small text-muted"><?php echo htmlspecialchars($r['staff_name'] ?? '—'); ?></td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-primary" href="<?php echo $base; ?>view.php?id=<?php echo (int) $r['id']; ?>">View</a>
              <?php if ((int) $r['pending_count'] > 0): ?>
                <a class="btn btn-sm btn-outline-success" href="<?php echo $base; ?>transfer.php?purchase_id=<?php echo (int) $r['id']; ?>">Transfer</a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
$__layout = TenantContext::role() === 'staff' ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
