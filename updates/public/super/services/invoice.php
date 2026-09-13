<?php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();
$S = new Models\BusinessServiceModel(Database::pdo());
$invoice = $S->invoice((int)($_GET['id'] ?? 0));
if (!$invoice) { http_response_code(404); exit('Service invoice not found.'); }
$items = $S->invoiceItems((int)$invoice['id']);
$tenant = (new Models\TenantModel(Database::pdo()))->find(TenantContext::tenantId()) ?: [];
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f1f5f9;padding:24px}.sheet{max-width:720px;margin:auto;background:#fff;padding:32px;border-radius:14px}.actions{max-width:720px;margin:16px auto}@media print{body{background:#fff;padding:0}.sheet{max-width:none}.actions{display:none}}</style>
</head><body><div class="sheet">
<div class="d-flex justify-content-between border-bottom pb-3 mb-3"><div><h2><?php echo htmlspecialchars($tenant['name'] ?? 'Service Invoice'); ?></h2><div><?php echo htmlspecialchars($invoice['booking_type']==='appointment'?'Appointment invoice':'Service sale invoice'); ?></div></div><div class="text-end"><h4><?php echo htmlspecialchars($invoice['invoice_number']); ?></h4><div><?php echo date('j M Y, g:i a',strtotime($invoice['created_at'])); ?></div></div></div>
<div class="row mb-4"><div class="col"><strong>Customer</strong><br><?php echo htmlspecialchars($invoice['customer_name']); ?><br><?php echo htmlspecialchars($invoice['customer_phone'] ?: ''); ?></div><div class="col text-end"><strong>Scheduled</strong><br><?php echo date('j M Y, g:i a',strtotime($invoice['scheduled_at'])); ?><br>Staff: <?php echo htmlspecialchars($invoice['staff_name'] ?: '—'); ?></div></div>
<table class="table"><thead><tr><th>Service</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead><tbody>
<?php foreach($items as $it): ?><tr><td><?php echo htmlspecialchars($it['service_name']); ?></td><td class="text-end"><?php echo rtrim(rtrim(number_format((float)$it['quantity'],2),'0'),'.'); ?></td><td class="text-end">KES <?php echo number_format((float)$it['unit_price'],2); ?></td><td class="text-end">KES <?php echo number_format((float)$it['line_total'],2); ?></td></tr><?php endforeach; ?>
</tbody><tfoot><tr><th colspan="3" class="text-end">Total</th><th class="text-end">KES <?php echo number_format((float)$invoice['total'],2); ?></th></tr></tfoot></table>
<div class="small text-muted">Payment: <?php echo htmlspecialchars(ucfirst($invoice['payment_status'])); ?> · Status: <?php echo htmlspecialchars(ucfirst($invoice['status'])); ?></div>
</div><div class="actions d-flex gap-2"><button class="btn btn-primary" onclick="print()">Print invoice</button><a class="btn btn-outline-secondary" href="<?php echo public_url('super/services/appointments.php'); ?>">Appointments</a></div>
</body></html>
