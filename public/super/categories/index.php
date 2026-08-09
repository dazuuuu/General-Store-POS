<?php
// public/super/categories/index.php — manage product categories for a general shop.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();

$pdo = Database::pdo();
$C = new Models\CategoryModel($pdo);

$type = 'product';
$noun = 'category';
$nounCap = 'Category';
$countLabel = 'Products';
$recordUrl = public_url('super/stock/new.php');
$recordLabel = 'Record products in bulk';

$error = ''; $old = '';

/** Validate + store an uploaded category image. Returns ['ok','path'|'error']. */
function category_handle_image(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || ($file['name'] ?? '') === '') {
        return ['ok' => true, 'path' => null]; // no new file — caller keeps the existing image
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Image upload failed. Try a smaller file.'];
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Image must be under 2 MB.'];
    }
    $info = @getimagesize($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = $info['mime'] ?? '';
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'Use a JPG, PNG, WEBP or GIF image.'];
    }
    $dir = ROOT_PATH . '/public/assets/uploads/categories';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $name = 'cat_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return ['ok' => false, 'error' => 'Could not save the image. Check folder permissions.'];
    }
    return ['ok' => true, 'path' => public_url('assets/uploads/categories/' . $name)];
}

$editId  = (int) ($_GET['edit'] ?? 0);
$editRow = $editId > 0 ? $C->find($editId) : null;
if (!$editRow || $editRow['type'] !== $type) { $editId = 0; $editRow = null; }

$base    = public_url('super/categories/');
$editUrl = public_url('super/categories/') . '?edit=';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $old = trim($_POST['name'] ?? '');
        $img = category_handle_image($_FILES['image'] ?? []);
        if (!$img['ok']) {
            $error = $img['error'];
        } else {
            $res = $C->create($old, $img['path'], $type);
            if ($res['ok']) { $_SESSION['flash']['success'] = $nounCap . ' "' . $old . '" added.'; header("Location: $base"); exit; }
            $error = $res['error'];
        }

    } elseif ($action === 'rename') {
        $id = (int) ($_POST['id'] ?? 0);
        $old = trim($_POST['name'] ?? '');
        $img = category_handle_image($_FILES['image'] ?? []);
        if (!$img['ok']) {
            $error = $img['error']; $editRow = $C->find($id); $editId = $id;
        } else {
            $res = $C->rename($id, $old, $img['path']);
            if ($res['ok']) { $_SESSION['flash']['success'] = $nounCap . ' updated.'; header("Location: {$editUrl}{$id}"); exit; }
            $error = $res['error']; $editRow = $C->find($id); $editId = $id;
        }

    } elseif ($action === 'toggle') {
        $row = $C->find((int) ($_POST['id'] ?? 0));
        if ($row) { $C->setStatus((int) $row['id'], $row['status'] === 'active' ? 'draft' : 'active'); }
        $_SESSION['flash']['success'] = $nounCap . ' status updated.'; header("Location: $base"); exit;

    } elseif ($action === 'delete') {
        $res = $C->deleteSafe((int) ($_POST['id'] ?? 0));
        $_SESSION['flash'][$res['ok'] ? 'success' : 'error'] = $res['ok'] ? "$nounCap deleted." : $res['error'];
        header("Location: $base"); exit;
    }
}

$categories = $C->listWithCounts($type);
$page_title = 'Categories';
ob_start();
?>
<div class="row g-4">
  <div class="col-12 col-lg-5">
    <?php if (!$editRow): ?>
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-1">Add a <?php echo $noun; ?></h2>
        <p class="text-muted small mb-3">
          Groups your products — Cereals, Drinks, Hardware… Shown as browsing cards on the till.
        </p>
        <?php if ($error): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="type" value="<?php echo $type; ?>">
          <div class="mb-3">
            <label class="form-label"><?php echo $nounCap; ?> name</label>
            <input name="name" class="form-control" placeholder="e.g. Cereals" value="<?php echo htmlspecialchars($old); ?>" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">Image <span class="text-muted">(optional — shown as a card on the selling screen)</span></label>
            <input type="file" name="image" accept="image/*" class="form-control">
          </div>
          <button class="btn btn-primary">Add <?php echo $noun; ?></button>
        </form>
      </div>
    </div>

    <?php else: ?>
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Rename <?php echo $noun; ?></h2>
        <?php if ($error): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="action" value="rename">
          <input type="hidden" name="type" value="<?php echo $type; ?>">
          <input type="hidden" name="id" value="<?php echo (int)$editRow['id']; ?>">
          <div class="mb-3">
            <input name="name" class="form-control" value="<?php echo htmlspecialchars($editRow['name']); ?>" required>
          </div>
          <div class="mb-3">
            <?php if (!empty($editRow['image_path'])): ?>
              <div class="mb-2"><img src="<?php echo htmlspecialchars($editRow['image_path']); ?>" alt="" style="height:54px;border-radius:8px;border:1px solid #e2e8f0;"></div>
            <?php endif; ?>
            <input type="file" name="image" accept="image/*" class="form-control">
            <small class="text-muted">Leave empty to keep the current image.</small>
          </div>
          <button class="btn btn-primary">Save</button>
          <a class="btn btn-link" href="<?php echo $base; ?>">Done</a>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- category list -->
  <div class="col-12 col-lg-7">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h2 class="h5 mb-0">Your categories <span class="badge bg-light text-dark"><?php echo count($categories); ?></span></h2>
          <a class="btn btn-sm btn-primary" href="<?php echo $recordUrl; ?>"><i class="fas fa-boxes-stacked me-1"></i><?php echo $recordLabel; ?></a>
        </div>
        <?php if (!$categories): ?>
          <div class="text-muted">No categories yet. Add your first one on the left.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead><tr class="text-muted small text-uppercase"><th></th><th>Name</th><th>Status</th><th class="text-center"><?php echo $countLabel; ?></th><th></th></tr></thead>
              <tbody>
                <?php foreach ($categories as $c): $on = (int)$c['id'] === $editId; ?>
                <tr class="<?php echo $on ? 'table-active' : ''; ?>">
                  <td style="width:44px;">
                    <?php if (!empty($c['image_path'])): ?>
                      <img src="<?php echo htmlspecialchars($c['image_path']); ?>" alt="" style="width:36px;height:36px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;">
                    <?php else: ?>
                      <span class="d-inline-flex align-items-center justify-content-center text-muted" style="width:36px;height:36px;border-radius:8px;background:#f1f5f9;"><i class="fas fa-tag"></i></span>
                    <?php endif; ?>
                  </td>
                  <td class="fw-semibold"><?php echo htmlspecialchars($c['name']); ?></td>
                  <td><?php echo $c['status'] === 'active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Draft</span>'; ?></td>
                  <td class="text-center"><span class="badge bg-light text-dark"><?php echo (int)$c['product_count']; ?></span></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $editUrl . (int)$c['id']; ?>">Edit</a>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="action" value="toggle"><input type="hidden" name="type" value="<?php echo $type; ?>"><input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                      <button class="btn btn-sm btn-outline-secondary"><?php echo $c['status'] === 'active' ? 'Draft' : 'Activate'; ?></button>
                    </form>
                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this <?php echo $noun; ?>?');">
                      <input type="hidden" name="action" value="delete"><input type="hidden" name="type" value="<?php echo $type; ?>"><input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
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
$__layout = TenantContext::role() === 'staff' ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
