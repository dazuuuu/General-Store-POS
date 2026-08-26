<?php
// public/staff/orders/held.php — carts set aside with "Hold Sale" (no stock
// touched yet). Resume loads one back into the selling screen; Discard drops it.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::SALES_RECORD);

$pdo = Database::pdo();
$HO = new Models\HeldOrderModel($pdo);
$isStaffViewer = TenantContext::role() === 'staff';
$heldUrl = $isStaffViewer ? public_url('staff/orders/held.php') : public_url('super/orders/held.php');
$newUrl = $isStaffViewer ? public_url('staff/orders/new.php') : public_url('super/orders/new.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'discard') {
    $id = (int) ($_POST['id'] ?? 0);
    $ok = $HO->discard($id);
    $_SESSION['flash'][$ok ? 'success' : 'error'] = $ok ? 'Held order discarded.' : 'Could not discard that order.';
    header('Location: ' . $heldUrl);
    exit;
}

$held = $HO->listForTenant();
$page_title = 'Held sales';
ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h1 class="h5 mb-0 fw-bold"><i class="fas fa-pause me-2 text-primary"></i>Held sales</h1>
  <a href="<?php echo $newUrl; ?>" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>New credit sale</a>
</div>

<?php if (!$held): ?>
  <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-5 text-center text-muted">
    <i class="fas fa-pause fa-2x mb-2 d-block" style="opacity:.3;"></i>
    No orders on hold right now.
  </div></div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($held as $h): ?>
    <div class="col-12 col-sm-6 col-lg-4">
      <div class="card border-0 shadow-sm h-100" style="border-radius:14px;">
        <div class="card-body p-3">
          <div class="fw-semibold mb-1"><?php echo htmlspecialchars($h['customer_name']); ?></div>
          <div class="text-muted small mb-2">
            <?php echo (int) $h['item_count']; ?> item<?php echo (int) $h['item_count'] === 1 ? '' : 's'; ?>
            · by <?php echo htmlspecialchars($h['staff_name'] ?? '—'); ?>
            · <?php echo date('g:i a', strtotime($h['created_at'])); ?>
          </div>
          <div class="fw-bold fs-5 mb-3">KES <?php echo number_format((float) $h['total'], 0); ?></div>
          <div class="d-flex gap-2">
            <a class="btn btn-sm btn-primary flex-fill" href="<?php echo $newUrl . '?resume=' . (int) $h['id']; ?>">Resume</a>
            <form method="post" class="d-inline" onsubmit="return confirm('Discard this held order?');">
              <input type="hidden" name="action" value="discard">
              <input type="hidden" name="id" value="<?php echo (int) $h['id']; ?>">
              <button class="btn btn-sm btn-outline-danger">Discard</button>
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
