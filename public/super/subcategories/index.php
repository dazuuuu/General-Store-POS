<?php
// public/super/subcategories/index.php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();

$pdo = Database::pdo();
$C = new Models\CategoryModel($pdo);
$S = new Models\SubcategoryModel($pdo);

$categoryId = (int) ($_GET['category_id'] ?? ($_POST['category_id'] ?? 0));
$category = $categoryId > 0 ? $C->find($categoryId) : null;

// No valid category selected → send back to categories.
if (!$category) {
    header('Location: ' . public_url('super/categories/'));
    exit;
}

$error = '';
$old = '';
$editId = (int) ($_GET['edit'] ?? 0);
$editRow = $editId > 0 ? $S->find($editId) : null;
if ($editRow && (int) $editRow['category_id'] !== $categoryId) { $editRow = null; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $base = public_url('super/subcategories/?category_id=' . $categoryId);
    if ($action === 'create') {
        $old = trim($_POST['name'] ?? '');
        $res = $S->create($categoryId, $old);
        if ($res['ok']) { $_SESSION['flash']['success'] = 'Subcategory "' . $old . '" added.'; header("Location: $base"); exit; }
        $error = $res['error'];
    } elseif ($action === 'rename') {
        $res = $S->rename((int) ($_POST['id'] ?? 0), trim($_POST['name'] ?? ''));
        if ($res['ok']) { $_SESSION['flash']['success'] = 'Subcategory updated.'; header("Location: $base"); exit; }
        $error = $res['error']; $editRow = $S->find((int) ($_POST['id'] ?? 0));
    } elseif ($action === 'toggle') {
        $row = $S->find((int) ($_POST['id'] ?? 0));
        if ($row) { $S->setStatus((int) $row['id'], $row['status'] === 'active' ? 'draft' : 'active'); }
        $_SESSION['flash']['success'] = 'Subcategory status updated.'; header("Location: $base"); exit;
    } elseif ($action === 'delete') {
        $res = $S->deleteSafe((int) ($_POST['id'] ?? 0));
        $_SESSION['flash'][$res['ok'] ? 'success' : 'error'] = $res['ok'] ? 'Subcategory deleted.' : $res['error'];
        header("Location: $base"); exit;
    }
}

$subs = $S->listForCategory($categoryId);
$page_title = 'Subcategories';
ob_start();
?>
<div class="mb-3">
  <a href="<?php echo public_url('super/categories/'); ?>" class="text-decoration-none text-muted">&larr; Categories</a>
  <h2 class="h4 mt-1 mb-0"><?php echo htmlspecialchars($category['name']); ?> <span class="text-muted fs-6">subcategories</span></h2>
</div>
<div class="row g-4">
  <div class="col-12 col-lg-4">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-3"><?php echo $editRow ? 'Rename subcategory' : 'Add a subcategory'; ?></h2>
        <?php if ($error): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="post" novalidate>
          <input type="hidden" name="action" value="<?php echo $editRow ? 'rename' : 'create'; ?>">
          <input type="hidden" name="category_id" value="<?php echo $categoryId; ?>">
          <?php if ($editRow): ?><input type="hidden" name="id" value="<?php echo (int)$editRow['id']; ?>"><?php endif; ?>
          <div class="mb-3">
            <label class="form-label">Subcategory name</label>
            <input name="name" class="form-control" placeholder="e.g. Sodas"
                   value="<?php echo htmlspecialchars($editRow['name'] ?? $old); ?>" required autofocus>
          </div>
          <button class="btn btn-primary"><?php echo $editRow ? 'Save' : 'Add subcategory'; ?></button>
          <?php if ($editRow): ?><a class="btn btn-link" href="<?php echo public_url('super/subcategories/?category_id=' . $categoryId); ?>">Cancel</a><?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-8">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Subcategories <span class="badge bg-light text-dark"><?php echo count($subs); ?></span></h2>
        <?php if (!$subs): ?>
          <div class="text-muted">No subcategories yet in <?php echo htmlspecialchars($category['name']); ?>.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead><tr class="text-muted small text-uppercase"><th>Name</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($subs as $s): ?>
                <tr>
                  <td class="fw-semibold"><?php echo htmlspecialchars($s['name']); ?></td>
                  <td><?php echo $s['status'] === 'active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Draft</span>'; ?></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url('super/subcategories/?category_id=' . $categoryId . '&edit=' . (int)$s['id']); ?>">Edit</a>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="action" value="toggle"><input type="hidden" name="category_id" value="<?php echo $categoryId; ?>"><input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                      <button class="btn btn-sm btn-outline-secondary"><?php echo $s['status'] === 'active' ? 'Draft' : 'Activate'; ?></button>
                    </form>
                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this subcategory?');">
                      <input type="hidden" name="action" value="delete"><input type="hidden" name="category_id" value="<?php echo $categoryId; ?>"><input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                      <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                  </td>
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
include __DIR__ . '/../../templates/tenants/layout.php';