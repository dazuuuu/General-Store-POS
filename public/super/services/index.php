<?php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();
$S = new Models\BusinessServiceModel(Database::pdo());
$services = $S->allActive();
$page_title = 'Services';
ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div><h1 class="h5 fw-bold mb-1">Services</h1><p class="text-muted small mb-0">Services offered, prices, duration and staff commission.</p></div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-primary btn-sm" href="<?php echo public_url('super/services/appointments.php'); ?>">Appointments</a>
    <a class="btn btn-primary btn-sm" href="<?php echo public_url('super/services/new.php'); ?>"><i class="fas fa-plus me-1"></i>Create service</a>
  </div>
</div>
<div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
  <div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr class="text-muted small text-uppercase"><th>Service</th><th>Description</th><th class="text-end">Duration</th><th class="text-end">Price</th><th class="text-end">Commission</th></tr></thead>
    <tbody>
      <?php if (!$services): ?><tr><td colspan="5" class="text-center text-muted py-5">No services created yet.</td></tr>
      <?php else: foreach ($services as $s): ?>
      <tr>
        <td class="fw-semibold"><?php echo htmlspecialchars($s['name']); ?><?php if (!empty($s['merged_service_ids'])): ?><div class="small text-primary">Merged package</div><?php endif; ?></td>
        <td class="small text-muted"><?php echo htmlspecialchars($s['description'] ?: '—'); ?></td>
        <td class="text-end small"><?php echo (int) $s['duration_minutes']; ?> min</td>
        <td class="text-end fw-semibold">KES <?php echo number_format((float) $s['price'], 2); ?></td>
        <td class="text-end text-success">KES <?php echo number_format((float) $s['commission_amount'], 2); ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table></div>
</div>
<?php
$content = ob_get_clean();
$layout = TenantContext::role() === 'staff' ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $layout . '/layout.php';
