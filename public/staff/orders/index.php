<?php
// public/staff/orders/index.php — open tabs shop-wide (add rounds, void, or
// — for a credit sale — email the customer an invoice/delivery note from
// the detail page). Settling payment is a separate job — see staff/payments/.
// Reached from both the staff till and the owner's side (super/orders/
// thin-wraps this) — content adapts by role rather than duplicating the page.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::SALES_RECORD);

$pdo = Database::pdo();
$O = new Models\OrderModel($pdo);
$orders = $O->openOrders();

$isStaffViewer = TenantContext::role() === 'staff';
$viewBase = $isStaffViewer ? public_url('staff/orders/view.php') : public_url('super/orders/view.php');
$newBase = $isStaffViewer ? public_url('staff/orders/new.php') : public_url('super/orders/new.php');

$page_title = 'Credit sales';
ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h1 class="h5 mb-0 fw-bold"><i class="fas fa-receipt me-2 text-warning"></i>Credit sales</h1>
  <a href="<?php echo $newBase; ?>" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>New credit sale</a>
</div>

<?php if (!$orders): ?>
  <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-5 text-center text-muted">
    <i class="fas fa-inbox fa-2x mb-2 d-block" style="opacity:.3;"></i>
    No open tabs right now.
  </div></div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($orders as $o): ?>
    <?php
      $paid = max(0, (float) ($o['amount_paid'] ?? 0));
      $due = (float) ($o['amount_due'] ?? 0);
      if ($due <= 0.0001) { $due = max(0, (float) $o['total'] - $paid); }
    ?>
    <div class="col-12 col-sm-6 col-lg-4">
      <a class="card border-0 shadow-sm h-100 text-decoration-none text-reset" style="border-radius:14px;" href="<?php echo $viewBase . '?id=' . (int) $o['id']; ?>">
        <div class="card-body p-3">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="fw-semibold"><?php echo htmlspecialchars($o['table_name']); ?></div>
            <span class="badge bg-warning text-dark">Unpaid</span>
          </div>
          <div class="text-muted small mb-2">
            <?php echo (int) $o['item_count']; ?> item<?php echo (int) $o['item_count'] === 1 ? '' : 's'; ?>
            · opened by <?php echo htmlspecialchars($o['opened_by_name'] ?? '—'); ?>
            · <?php echo date('g:i a', strtotime($o['created_at'])); ?>
            <?php if (!empty($o['customer_email'])): ?> · <i class="fas fa-envelope"></i><?php endif; ?>
          </div>
          <div class="fw-bold fs-5">KES <?php echo number_format($due, 0); ?> owed</div>
          <?php if ($paid > 0): ?><div class="small text-success">KES <?php echo number_format($paid, 0); ?> paid so far</div><?php endif; ?>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
$__layout = $isStaffViewer ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
