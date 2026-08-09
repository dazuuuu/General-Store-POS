<?php
// public/super/stationery/new.php — single-product intake (general shop).
// Kept at this path so existing menu links keep working; UI is product-only.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::STOCK_ENTER);

$pdo = Database::pdo();
$SUP = new Models\SupplierModel($pdo);
$C   = new Models\CategoryModel($pdo);
$BA  = new Models\BookAttributeModel($pdo);
$SI  = new Models\StockIntakeModel($pdo);
$units = Models\ProductModel::UNITS;
$apiBase = public_url('api/inventory/');

function single_product_handle_image(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || ($file['name'] ?? '') === '') {
        return ['ok' => true, 'path' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Image upload failed.'];
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Image must be under 3 MB.'];
    }
    $info = @getimagesize($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = $info['mime'] ?? '';
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'Use a JPG, PNG, WEBP or GIF image.'];
    }
    $dir = ROOT_PATH . '/public/assets/uploads/products';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $name = 'prod_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return ['ok' => false, 'error' => 'Could not save the image.'];
    }
    return ['ok' => true, 'path' => public_url('assets/uploads/products/' . $name)];
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $qty = (float) ($_POST['quantity'] ?? 0);
    $faulty = max(0, (float) ($_POST['faulty_quantity'] ?? 0));
    $unit = in_array($_POST['unit'] ?? '', $units, true) ? $_POST['unit'] : 'piece';
    $colors = array_values(array_filter(array_map('trim', explode(',', $_POST['colors'] ?? ''))));
    $supplierId = trim($_POST['supplier'] ?? '') !== '' ? (int) $SUP->findOrCreate($_POST['supplier']) : 0;

    if ($name === '') {
        $error = 'Product name is required.';
    } elseif ($qty <= 0) {
        $error = 'Enter the good (sellable) quantity received.';
    } else {
        $img = single_product_handle_image($_FILES['image'] ?? []);
        if (!$img['ok']) {
            $error = $img['error'];
        } else {
            $productChoice = (int) ($_POST['product_choice'] ?? 0);
            if ($productChoice > 0) {
                $items = [[
                    'mode' => 'restock',
                    'product_id' => $productChoice,
                    'quantity' => $qty,
                    'faulty_quantity' => $faulty,
                    'unit' => $unit,
                    'colors' => $colors,
                    'buying_price' => (float) ($_POST['buying_price'] ?? 0),
                    'remark' => trim($_POST['remark'] ?? ''),
                ]];
            } else {
                $items = [[
                    'mode' => 'new',
                    'product_type' => 'product',
                    'name' => $name,
                    'category_id' => (int) $C->findOrCreate($_POST['category'] ?? '', 'product'),
                    'brand_id' => (int) $BA->findOrCreate('brand', $_POST['brand'] ?? ''),
                    'barcode' => trim($_POST['barcode'] ?? ''),
                    'unit' => $unit,
                    'colors' => $colors,
                    'quantity' => $qty,
                    'faulty_quantity' => $faulty,
                    'buying_price' => (float) ($_POST['buying_price'] ?? 0),
                    'selling_price' => $_POST['selling_price'] ?? 0,
                    'wholesale_price' => $_POST['wholesale_price'] ?? '',
                    'image_path' => $img['path'] ?? '',
                    'remark' => trim($_POST['remark'] ?? ''),
                ]];
            }
            $res = $SI->create([
                'supplier_id' => $supplierId,
                'staff_id' => TenantContext::userId(),
                'notes' => $_POST['notes'] ?? '',
            ], $items);
            if ($res['ok']) {
                $_SESSION['flash']['success'] = 'Product recorded.';
                header('Location: ' . public_url('super/inventory/'));
                exit;
            }
            $error = $res['errors']['_'] ?? 'Could not record this product.';
        }
    }
}

$page_title = 'Record product';
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <p class="text-muted small mb-0">Add one product at a time. Need many lines? <a href="<?php echo public_url('super/stock/new.php'); ?>">Record products in bulk</a>.</p>
</div>

<form method="post" enctype="multipart/form-data" class="card border-0 shadow-sm" style="border-radius:12px;">
  <div class="card-body p-4">
    <div class="row g-3">
      <div class="col-12 col-md-6">
        <label class="form-label fw-semibold">Product name</label>
        <input type="text" name="name" id="prodName" class="form-control" required placeholder="e.g. Yellow beans, Cooking oil 5L" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
        <input type="hidden" name="product_choice" id="productChoice" value="">
        <div id="matchNote" class="small text-primary mt-1" style="display:none;"></div>
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label fw-semibold"><i class="fas fa-barcode me-1"></i>Barcode</label>
        <input type="text" name="barcode" class="form-control" placeholder="Scan or type" value="<?php echo htmlspecialchars($_POST['barcode'] ?? ''); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Category</label>
        <input type="text" name="category" class="form-control" placeholder="e.g. Cereals" value="<?php echo htmlspecialchars($_POST['category'] ?? ''); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Brand</label>
        <input type="text" name="brand" class="form-control" placeholder="optional" value="<?php echo htmlspecialchars($_POST['brand'] ?? ''); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Colors</label>
        <input type="text" name="colors" class="form-control" placeholder="e.g. Red, Blue" value="<?php echo htmlspecialchars($_POST['colors'] ?? ''); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold">Unit</label>
        <select name="unit" class="form-select">
          <?php foreach ($units as $u): ?>
            <option value="<?php echo htmlspecialchars($u); ?>" <?php echo (($_POST['unit'] ?? 'piece') === $u) ? 'selected' : ''; ?>><?php echo htmlspecialchars($u); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold">Good qty received</label>
        <input type="number" step="0.01" min="0" name="quantity" class="form-control" required value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold">Faulty / broken</label>
        <input type="number" step="0.01" min="0" name="faulty_quantity" class="form-control" value="<?php echo htmlspecialchars($_POST['faulty_quantity'] ?? '0'); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold">Supplier</label>
        <input type="text" name="supplier" class="form-control" placeholder="optional" value="<?php echo htmlspecialchars($_POST['supplier'] ?? ''); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Buying price (KES)</label>
        <input type="number" step="0.01" min="0" name="buying_price" class="form-control" value="<?php echo htmlspecialchars($_POST['buying_price'] ?? ''); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Selling price (KES)</label>
        <input type="number" step="0.01" min="0" name="selling_price" class="form-control" value="<?php echo htmlspecialchars($_POST['selling_price'] ?? ''); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Wholesale <span class="text-muted fw-normal">(optional)</span></label>
        <input type="number" step="0.01" min="0" name="wholesale_price" class="form-control" value="<?php echo htmlspecialchars($_POST['wholesale_price'] ?? ''); ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-semibold">Photo</label>
        <input type="file" name="image" accept="image/*" class="form-control">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-semibold">Remark</label>
        <input type="text" name="remark" class="form-control" placeholder="e.g. damaged carton" value="<?php echo htmlspecialchars($_POST['remark'] ?? ''); ?>">
      </div>
    </div>
    <div class="mt-4">
      <button class="btn btn-primary"><i class="fas fa-box-open me-1"></i>Record product</button>
    </div>
  </div>
</form>
<script>
(function () {
  var API = <?php echo json_encode($apiBase); ?>;
  var nameInput = document.getElementById('prodName');
  var choice = document.getElementById('productChoice');
  var note = document.getElementById('matchNote');
  var timer = null;
  nameInput.addEventListener('input', function () {
    clearTimeout(timer);
    choice.value = '';
    note.style.display = 'none';
    var q = nameInput.value.trim();
    if (!q) return;
    timer = setTimeout(function () {
      fetch(API + 'find_titles.php?type=product&q=' + encodeURIComponent(q))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          var exact = (data.items || []).find(function (it) { return (it.name || '').toLowerCase() === q.toLowerCase(); });
          if (exact) {
            choice.value = exact.id;
            note.style.display = 'block';
            note.textContent = 'Restocking existing product (balance ' + exact.balance + ').';
          }
        });
    }, 200);
  });
})();
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
