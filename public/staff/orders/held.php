<?php
// public/staff/orders/held.php — carts set aside with "Hold Sale" (no stock
// touched yet). Resume loads one back into the selling screen; Discard drops it.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::SALES_RECORD);

$pdo = Database::pdo();
$HO = new Models\HeldOrderModel($pdo);
$isStaffViewer = TenantContext::role() === 'staff';
$heldUrl = $isStaffViewer ? public_url('staff/orders/held.php') : public_url('super/orders/held.php');
$shopUrl = $isStaffViewer ? public_url('staff/dashboard/') : public_url('super/shop/');
$creditUrl = $isStaffViewer ? public_url('staff/orders/new.php') : public_url('super/orders/new.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'discard') {
    $id = (int) ($_POST['id'] ?? 0);
    $ok = $HO->discard($id);
    $_SESSION['flash'][$ok ? 'success' : 'error'] = $ok ? 'Held order discarded.' : 'Could not discard that order.';
    header('Location: ' . $heldUrl);
    exit;
}

$held = $HO->listWithItemsForTenant();
$page_title = 'Held sales';
ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h1 class="h5 mb-1 fw-bold"><i class="fas fa-pause-circle me-2 text-warning"></i>Held Sales</h1>
    <div class="small text-muted">Paused sales waiting to be resumed or checked out.</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?php echo $shopUrl; ?>" class="btn btn-sm btn-primary"><i class="fas fa-cash-register me-1"></i>Shop POS</a>
    <a href="<?php echo $creditUrl; ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-invoice-dollar me-1"></i>New credit sale</a>
  </div>
</div>

<?php if (!$held): ?>
  <div class="card border-0 shadow-sm" style="border-radius:14px;">
    <div class="card-body p-5 text-center text-muted">
      <i class="fas fa-pause fa-2x mb-3 d-block text-warning" style="opacity:.4;"></i>
      <h2 class="h6 fw-bold mb-1">No sales on hold right now</h2>
      <p class="small text-muted mb-3">You can pause any active sale in the Shop POS and resume it whenever the customer is ready.</p>
      <a href="<?php echo $shopUrl; ?>" class="btn btn-sm btn-primary">Go to Shop POS</a>
    </div>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($held as $h): ?>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="card border-0 shadow-sm h-100" style="border-radius:14px; border: 1px solid #eef0f4;">
        <div class="card-body p-3 d-flex flex-column">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <span class="badge bg-warning text-dark mb-1">Hold #<?php echo (int) $h['id']; ?></span>
              <h2 class="h6 fw-bold mb-0 text-dark"><?php echo htmlspecialchars($h['customer_name'] ?: 'Walk-in customer'); ?></h2>
            </div>
            <div class="text-end">
              <div class="fw-bold text-success fs-5">KES <?php echo number_format((float) $h['total'], 0); ?></div>
              <div class="small text-muted"><?php echo (int) $h['item_count']; ?> item<?php echo (int) $h['item_count'] === 1 ? '' : 's'; ?></div>
            </div>
          </div>

          <div class="text-muted small mb-2">
            <i class="far fa-clock me-1"></i><?php echo date('j M Y, g:i a', strtotime($h['created_at'])); ?>
            <?php if (!empty($h['staff_name'])): ?>
              · <i class="far fa-user me-1"></i><?php echo htmlspecialchars($h['staff_name']); ?>
            <?php endif; ?>
          </div>

          <?php if (!empty($h['items'])): ?>
            <div class="bg-light rounded p-2 mb-3 small flex-grow-1" style="max-height:100px; overflow-y:auto;">
              <ul class="list-unstyled mb-0 text-secondary">
                <?php foreach ($h['items'] as $it): ?>
                  <li class="d-flex justify-content-between py-1 border-bottom border-light">
                    <span class="text-truncate me-2">
                      <strong><?php echo (float) $it['quantity']; ?>×</strong> <?php echo htmlspecialchars($it['product_name']); ?>
                    </span>
                    <span class="text-nowrap fw-semibold">KES <?php echo number_format((float) ($it['unit_price'] * $it['quantity']), 0); ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php else: ?>
            <div class="flex-grow-1"></div>
          <?php endif; ?>

          <div class="d-flex gap-2 pt-2 border-top">
            <a class="btn btn-sm btn-success flex-fill fw-bold" href="<?php echo $shopUrl . '?resume=' . (int) $h['id']; ?>">
              <i class="fas fa-play me-1"></i>Resume in Shop
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo $creditUrl . '?resume=' . (int) $h['id']; ?>" title="Resume as Credit Sale">
              <i class="fas fa-file-invoice"></i>
            </a>
            <form method="post" class="d-inline" onsubmit="return confirm('Discard this held sale permanently?');">
              <input type="hidden" name="action" value="discard">
              <input type="hidden" name="id" value="<?php echo (int) $h['id']; ?>">
              <button class="btn btn-sm btn-outline-danger" title="Discard this hold">
                <i class="fas fa-trash"></i>
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/' . ($isStaffViewer ? 'staff' : 'tenants') . '/layout.php';
