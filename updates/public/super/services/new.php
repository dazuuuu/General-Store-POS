<?php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::tenant();
$S = new Models\BusinessServiceModel(Database::pdo());
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = $S->save($_POST, (int) TenantContext::userId());
    if ($res['ok']) {
        $_SESSION['flash']['success'] = 'Service created.';
        header('Location: ' . public_url('super/services/')); exit;
    }
    $error = $res['error'];
}
$services = $S->allActive();
$page_title = 'Create service';
ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h1 class="h5 fw-bold mb-1">Create service</h1><p class="text-muted small mb-0">Create one service or merge existing services into a package.</p></div>
  <a class="btn btn-outline-secondary btn-sm" href="<?php echo public_url('super/services/'); ?>">View services</a>
</div>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<form method="post" class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-4">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Service name</label><input name="name" class="form-control" required placeholder="e.g. Haircut + beard trim"></div>
    <div class="col-md-3"><label class="form-label">Price (KES)</label><input name="price" type="number" step="0.01" min="0" class="form-control" placeholder="Auto-sum if merged"></div>
    <div class="col-md-3"><label class="form-label">Duration (minutes)</label><input name="duration_minutes" type="number" min="1" class="form-control" value="30"></div>
    <div class="col-md-3"><label class="form-label">Commission per service</label><input name="commission_amount" type="number" step="0.01" min="0" class="form-control" value="0"></div>
    <div class="col-md-9"><label class="form-label">Description</label><input name="description" class="form-control" placeholder="What is included?"></div>
    <?php if ($services): ?>
    <div class="col-12">
      <label class="form-label fw-semibold">Merge existing services <span class="text-muted fw-normal">(optional)</span></label>
      <div class="row g-2">
        <?php foreach ($services as $s): ?>
        <div class="col-md-4"><label class="border rounded p-2 d-block">
          <input class="form-check-input me-1" type="checkbox" name="merged_service_ids[]" value="<?php echo (int) $s['id']; ?>">
          <?php echo htmlspecialchars($s['name']); ?> · KES <?php echo number_format((float) $s['price'], 0); ?>
        </label></div>
        <?php endforeach; ?>
      </div>
      <div class="form-text">Leave price/commission blank or zero to total the selected services automatically.</div>
    </div>
    <?php endif; ?>
  </div>
  <button class="btn btn-primary mt-4"><i class="fas fa-save me-1"></i>Save service</button>
</div></form>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
