<?php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();
$S = new Models\BusinessServiceModel(Database::pdo());
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($S->updateAppointment((int)($_POST['id'] ?? 0), (string)($_POST['action'] ?? ''), (int)TenantContext::userId())) {
        $_SESSION['flash']['success'] = 'Appointment updated.';
        header('Location: ' . public_url('super/services/appointments.php')); exit;
    }
}
$status = in_array($_GET['status'] ?? '', ['booked','completed','cancelled'], true) ? $_GET['status'] : '';
$rows = $S->appointments(['status' => $status]);
$page_title = 'Appointments';
ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div><h1 class="h5 fw-bold mb-1">Service appointments</h1><p class="text-muted small mb-0">Upcoming appointments and automatically generated invoices.</p></div>
  <a class="btn btn-primary btn-sm" href="<?php echo public_url('super/services/sell.php'); ?>">New appointment / sale</a>
</div>
<div class="btn-group mb-3">
  <?php foreach ([''=>'All','booked'=>'Upcoming','completed'=>'Completed','cancelled'=>'Cancelled'] as $key=>$label): ?>
    <a class="btn btn-sm <?php echo $status===$key?'btn-primary':'btn-outline-secondary'; ?>" href="?status=<?php echo urlencode($key); ?>"><?php echo $label; ?></a>
  <?php endforeach; ?>
</div>
<div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;"><div class="table-responsive">
<table class="table align-middle mb-0"><thead><tr class="small text-muted text-uppercase"><th>When</th><th>Invoice</th><th>Customer</th><th>Assigned staff</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Commission</th><th></th></tr></thead>
<tbody>
<?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-5">No service appointments yet.</td></tr>
<?php else: foreach ($rows as $row):
  $soon = $row['status']==='booked' && strtotime($row['scheduled_at']) >= time() && strtotime($row['scheduled_at']) <= time()+3600;
?>
<tr class="<?php echo $soon?'table-warning':''; ?>">
  <td class="small text-nowrap"><?php echo htmlspecialchars(date('j M Y, g:i a', strtotime($row['scheduled_at']))); ?><?php if ($soon): ?><div class="text-danger fw-bold">Due within 1 hour</div><?php endif; ?></td>
  <td><a class="fw-semibold" href="<?php echo public_url('super/services/invoice.php?id='.(int)$row['id']); ?>"><?php echo htmlspecialchars($row['invoice_number']); ?></a></td>
  <td><div class="fw-semibold"><?php echo htmlspecialchars($row['customer_name']); ?></div><div class="small text-muted"><?php echo htmlspecialchars($row['customer_phone'] ?: ''); ?></div></td>
  <td class="small"><?php echo htmlspecialchars($row['staff_name'] ?: '—'); ?></td>
  <td><span class="badge bg-<?php echo $row['status']==='completed'?'success':($row['status']==='cancelled'?'secondary':'primary'); ?>"><?php echo htmlspecialchars(ucfirst($row['status'])); ?></span></td>
  <td class="text-end fw-semibold">KES <?php echo number_format((float)$row['total'],2); ?></td>
  <td class="text-end text-success">KES <?php echo number_format((float)$row['commission_total'],2); ?></td>
  <td class="text-end"><form method="post" class="d-flex gap-1 justify-content-end"><input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
    <?php if($row['payment_status']!=='paid' && $row['status']!=='cancelled'): ?><button name="action" value="paid" class="btn btn-sm btn-outline-success">Mark paid</button><?php endif; ?>
    <?php if($row['status']==='booked'): ?><button name="action" value="complete" class="btn btn-sm btn-outline-primary">Complete</button><button name="action" value="cancel" class="btn btn-sm btn-outline-danger">Cancel</button><?php endif; ?>
  </form></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div>
<?php
$content=ob_get_clean();
$layout=TenantContext::role()==='staff'?'staff':'tenants';
include __DIR__.'/../../templates/'.$layout.'/layout.php';
