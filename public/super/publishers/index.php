<?php
// public/super/publishers/index.php
// Publishers, Subjects and Grade/Class are things you enter once here, then
// just assign to a book when recording stock — no retyping every delivery.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::INVENTORY_EDIT);

$pdo = Database::pdo();
$BA = new Models\BookAttributeModel($pdo);

$base = public_url('super/publishers/');
$error = '';
$old = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $old = trim($_POST['name'] ?? '');
    $res = $BA->create('publisher', $old);
    if ($res['ok']) {
        $_SESSION['flash']['success'] = 'Publisher "' . $old . '" added.';
        header('Location: ' . $base);
        exit;
    }
    $error = $res['error'];
}

$publishers = $BA->listWithCounts('publisher');
$page_title = 'Publishers';
ob_start();
?>
<div class="row g-4">
  <div class="col-12 col-lg-5">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-1">Add a publisher</h2>
        <p class="text-muted small mb-3">Pre-add publishers here, or just type a new one on the Record stock page — either way it's remembered.</p>
        <?php if ($error): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="post" novalidate>
          <input type="hidden" name="action" value="create">
          <div class="mb-3">
            <label class="form-label">Publisher name</label>
            <input name="name" class="form-control" placeholder="e.g. Longhorn Publishers" value="<?php echo htmlspecialchars($old); ?>" required autofocus>
          </div>
          <button class="btn btn-primary">Add publisher</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-7">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h2 class="h5 mb-0">Your publishers <span class="badge bg-light text-dark"><?php echo count($publishers); ?></span></h2>
          <a class="btn btn-sm btn-primary" href="<?php echo public_url('super/stock/new.php'); ?>"><i class="fas fa-truck-loading me-1"></i>Record stock</a>
        </div>
        <?php if (!$publishers): ?>
          <div class="text-muted">No publishers yet. Add your first one on the left.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead><tr class="text-muted small text-uppercase"><th>Name</th><th class="text-center">Books</th></tr></thead>
              <tbody>
                <?php foreach ($publishers as $p): ?>
                <tr>
                  <td class="fw-semibold"><?php echo htmlspecialchars($p['name']); ?></td>
                  <td class="text-center"><span class="badge bg-light text-dark"><?php echo (int) $p['book_count']; ?></span></td>
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
