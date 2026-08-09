<?php
// public/super/suppliers/index.php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::INVENTORY_EDIT);

$pdo = Database::pdo();
$SUP = new Models\SupplierModel($pdo);

$base = public_url('super/suppliers/');
$error = '';
$old = ['name' => '', 'phone' => '', 'notes' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $old = [
        'name'  => trim($_POST['name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
    ];
    $res = $SUP->create($old['name'], $old['phone'], $old['notes']);
    if ($res['ok']) {
        $_SESSION['flash']['success'] = 'Supplier "' . $old['name'] . '" added.';
        header('Location: ' . $base);
        exit;
    }
    $error = $res['error'];
}

$suppliers = $SUP->listWithCounts();
$page_title = 'Suppliers';
ob_start();
?>
<div class="row g-4">
  <div class="col-12 col-lg-5">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-1">Add a supplier</h2>
        <p class="text-muted small mb-3">Suppliers bring the stock you record on the "Record stock" page.</p>
        <?php if ($error): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="post" novalidate>
          <input type="hidden" name="action" value="create">
          <div class="mb-3">
            <label class="form-label">Supplier name</label>
            <input name="name" class="form-control" placeholder="e.g. Highland Distributors" value="<?php echo htmlspecialchars($old['name']); ?>" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">Phone <span class="text-muted">(optional)</span></label>
            <input name="phone" class="form-control" placeholder="07xx xxx xxx" value="<?php echo htmlspecialchars($old['phone']); ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Notes <span class="text-muted">(optional)</span></label>
            <input name="notes" class="form-control" placeholder="e.g. delivers every Tuesday" value="<?php echo htmlspecialchars($old['notes']); ?>">
          </div>
          <button class="btn btn-primary">Add supplier</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-7">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h2 class="h5 mb-0">Your suppliers <span class="badge bg-light text-dark"><?php echo count($suppliers); ?></span></h2>
          <a class="btn btn-sm btn-primary" href="<?php echo public_url('super/stock/new.php'); ?>"><i class="fas fa-truck-loading me-1"></i>Record stock</a>
        </div>
        <?php if (!$suppliers): ?>
          <div class="text-muted">No suppliers yet. Add your first one on the left.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead><tr class="text-muted small text-uppercase"><th>Name</th><th>Phone</th><th>Notes</th><th class="text-center">Products</th></tr></thead>
              <tbody>
                <?php foreach ($suppliers as $s): ?>
                <tr>
                  <td class="fw-semibold"><?php echo htmlspecialchars($s['name']); ?></td>
                  <td class="text-muted"><?php echo htmlspecialchars($s['phone'] ?? '—'); ?></td>
                  <td class="text-muted small"><?php echo htmlspecialchars($s['notes'] ?? ''); ?></td>
                  <td class="text-center"><span class="badge bg-light text-dark"><?php echo (int) $s['product_count']; ?></span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
$__layout = TenantContext::role() === 'staff' ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
