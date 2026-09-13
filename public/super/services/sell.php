<?php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();
$pdo = Database::pdo();
$S = new Models\BusinessServiceModel($pdo);
$services = $S->allActive();
$staffSt = $pdo->prepare(
    "SELECT u.id, u.username FROM users u JOIN roles r ON r.id = u.role_id
      WHERE u.tenant_id = ? AND u.is_active = 1 AND r.name IN ('staff','tenant_owner') ORDER BY u.username"
);
$staffSt->execute([TenantContext::tenantId()]);
$staff = $staffSt->fetchAll();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = $S->createBooking($_POST, (int) TenantContext::userId());
    if ($res['ok']) {
        header('Location: ' . public_url('super/services/invoice.php?id=' . (int) $res['id'])); exit;
    }
    $error = $res['error'];
}
$page_title = 'Sell services';
ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div><h1 class="h5 fw-bold mb-1">Sell services / make appointment</h1><p class="text-muted small mb-0">Every booking automatically receives an SVC invoice.</p></div>
  <a class="btn btn-outline-secondary btn-sm" href="<?php echo public_url('super/services/appointments.php'); ?>">View appointments</a>
</div>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if (!$services): ?>
  <div class="alert alert-warning">Create a service before selling it. <?php if (TenantContext::role() === 'tenant_owner'): ?><a href="<?php echo public_url('super/services/new.php'); ?>">Create service</a><?php endif; ?></div>
<?php else: ?>
<form method="post" class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-4">
  <div class="row g-3 mb-4">
    <div class="col-md-4"><label class="form-label">Customer</label><input name="customer_name" class="form-control" placeholder="Walk-in Customer"></div>
    <div class="col-md-4"><label class="form-label">Phone</label><input name="customer_phone" class="form-control"></div>
    <div class="col-md-4"><label class="form-label">Email</label><input name="customer_email" type="email" class="form-control"></div>
    <div class="col-md-3"><label class="form-label">Type</label><select name="booking_type" id="bookingType" class="form-select"><option value="walkin">Walking customer</option><option value="appointment">Appointment</option></select></div>
    <div class="col-md-3" id="scheduleBox" style="display:none;"><label class="form-label">Appointment time</label><input name="scheduled_at" type="datetime-local" class="form-control" min="<?php echo date('Y-m-d\TH:i'); ?>"></div>
    <div class="col-md-3"><label class="form-label">Staff earning commission</label><select name="staff_id" class="form-select">
      <?php foreach ($staff as $person): ?><option value="<?php echo (int) $person['id']; ?>" <?php echo (int)$person['id'] === (int)TenantContext::userId() ? 'selected' : ''; ?>><?php echo htmlspecialchars($person['username']); ?></option><?php endforeach; ?>
    </select></div>
    <div class="col-md-3"><label class="form-label">Payment</label><select name="payment_status" class="form-select"><option value="paid">Paid</option><option value="unpaid">Unpaid</option></select></div>
  </div>
  <h2 class="h6 fw-bold">Select services</h2>
  <div class="table-responsive"><table class="table align-middle">
    <thead><tr class="small text-muted text-uppercase"><th></th><th>Service</th><th class="text-end">Base price</th><th style="width:120px;">Qty</th><th style="width:170px;">Selling price</th><th>Commission</th></tr></thead>
    <tbody><?php foreach ($services as $s): ?>
      <tr>
        <td><input class="form-check-input service-check" type="checkbox" data-id="<?php echo (int)$s['id']; ?>"></td>
        <td><div class="fw-semibold"><?php echo htmlspecialchars($s['name']); ?></div><div class="small text-muted"><?php echo (int)$s['duration_minutes']; ?> min</div></td>
        <td class="text-end">KES <?php echo number_format((float)$s['price'], 2); ?></td>
        <td><input disabled name="services[<?php echo (int)$s['id']; ?>][quantity]" type="number" min="1" step="1" value="1" class="form-control service-field" data-service="<?php echo (int)$s['id']; ?>"></td>
        <td><input disabled name="services[<?php echo (int)$s['id']; ?>][unit_price]" type="number" min="<?php echo (float)$s['price']; ?>" step="0.01" value="<?php echo (float)$s['price']; ?>" class="form-control service-field" data-service="<?php echo (int)$s['id']; ?>"></td>
        <td class="small text-success">KES <?php echo number_format((float)$s['commission_amount'], 2); ?> + price above base</td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
  <button class="btn btn-primary btn-lg"><i class="fas fa-file-invoice me-1"></i>Create service invoice</button>
</div></form>
<script>
(function(){
  var type=document.getElementById('bookingType'),box=document.getElementById('scheduleBox');
  function schedule(){box.style.display=type.value==='appointment'?'':'none';}
  type.addEventListener('change',schedule); schedule();
  document.querySelectorAll('.service-check').forEach(function(check){
    check.addEventListener('change',function(){
      document.querySelectorAll('.service-field[data-service="'+check.dataset.id+'"]').forEach(function(input){input.disabled=!check.checked;});
    });
  });
})();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
$layout = TenantContext::role() === 'staff' ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $layout . '/layout.php';
