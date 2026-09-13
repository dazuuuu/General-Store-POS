<?php
// public/super/purchases/new.php — record a purchase (supplier buy) with optional
// receipt. Items stay in Purchases until transferred to Store warehouse.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::STOCK_ENTER);

$pdo = Database::pdo();
$PUR = new Models\PurchaseModel($pdo);
$SUP = new Models\SupplierModel($pdo);
$C   = new Models\CategoryModel($pdo);
$BA  = new Models\BookAttributeModel($pdo);
$units = Models\ProductModel::UNITS;
$apiBase = public_url('api/inventory/');
$base = public_url('super/purchases/');

function purchase_handle_receipt(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || ($file['name'] ?? '') === '') {
        return ['ok' => true, 'path' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Receipt upload failed. Try a smaller file.'];
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Receipt image must be under 5 MB.'];
    }
    $info = @getimagesize($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = $info['mime'] ?? '';
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'Use a JPG, PNG, WEBP or GIF for the receipt.'];
    }
    $dir = ROOT_PATH . '/public/assets/uploads/receipts';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $name = 'rcpt_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return ['ok' => false, 'error' => 'Could not save the receipt image.'];
    }
    return ['ok' => true, 'path' => public_url('assets/uploads/receipts/' . $name)];
}

function purchase_row_image_file(int $i): array
{
    if (!isset($_FILES['items']['name'][$i]['image']) || $_FILES['items']['name'][$i]['image'] === '') {
        return ['error' => UPLOAD_ERR_NO_FILE];
    }
    return [
        'name' => $_FILES['items']['name'][$i]['image'],
        'type' => $_FILES['items']['type'][$i]['image'],
        'tmp_name' => $_FILES['items']['tmp_name'][$i]['image'],
        'error' => $_FILES['items']['error'][$i]['image'],
        'size' => $_FILES['items']['size'][$i]['image'],
    ];
}

function purchase_handle_product_image(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Product image upload failed.'];
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Product image must be under 3 MB.'];
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
        return ['ok' => false, 'error' => 'Could not save the product image.'];
    }
    return ['ok' => true, 'path' => public_url('assets/uploads/products/' . $name)];
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shopName = trim((string) ($_POST['shop_name'] ?? ''));
    $supplierName = trim((string) ($_POST['supplier'] ?? ''));
    $supplierId = $supplierName !== '' ? (int) $SUP->findOrCreate($supplierName) : 0;
    if ($shopName === '' && $supplierName !== '') {
        $shopName = $supplierName;
    }
    $receipt = purchase_handle_receipt($_FILES['receipt_image'] ?? []);
    if (!$receipt['ok']) {
        $error = $receipt['error'];
    } else {
        $items = [];
        foreach (($_POST['items'] ?? []) as $i => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $variant = trim((string) ($row['variant_label'] ?? ''));
            $packageQty = max(0, (float) ($row['package_quantity'] ?? 0));
            $inside = max(0, (float) ($row['units_per_package'] ?? 0));
            $directQty = max(0, (float) ($row['quantity'] ?? 0));
            $packageCost = max(0, (float) ($row['buying_price'] ?? 0));
            $barcode = trim((string) ($row['barcode'] ?? ''));
            $has = $name !== '' || $variant !== '' || $packageQty > 0 || $directQty > 0 || $packageCost > 0 || $barcode !== '';
            if (!$has) {
                continue;
            }
            $img = purchase_handle_product_image(purchase_row_image_file((int) $i));
            if (!$img['ok']) {
                $error = $img['error'];
                break;
            }
            $effectiveInside = $inside > 0 ? $inside : 1.0;
            $qty = $directQty > 0 ? $directQty : ($packageQty > 0 ? round($packageQty * $effectiveInside, 2) : 0.0);
            $categoryId = !empty($row['category']) ? (int) $C->findOrCreate($row['category'], 'product') : 0;
            $brandId = !empty($row['brand']) ? (int) $BA->findOrCreate('brand', $row['brand']) : 0;
            $items[] = [
                'name' => $name,
                'variant_label' => $variant,
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'barcode' => $barcode,
                'package_unit' => $row['package_unit'] ?? 'bale',
                'unit' => $row['inner_unit'] ?? 'piece',
                'package_quantity' => $packageQty > 0 ? $packageQty : null,
                'units_per_package' => $effectiveInside,
                'quantity' => $qty,
                'faulty_quantity' => max(0, (float) ($row['faulty_quantity'] ?? 0)),
                'buying_price' => $packageCost,
                'package_buying_price' => $packageCost > 0 ? $packageCost : null,
                'image_path' => $img['path'],
                'notes' => trim((string) ($row['remark'] ?? '')),
            ];
        }

        if ($error === '') {
            $res = $PUR->create([
                'shop_name' => $shopName,
                'supplier_id' => $supplierId,
                'receipt_number' => trim((string) ($_POST['receipt_number'] ?? '')),
                'receipt_image_path' => $receipt['path'],
                'purchase_date' => trim((string) ($_POST['purchase_date'] ?? '')),
                'notes' => trim((string) ($_POST['notes'] ?? '')),
                'staff_id' => TenantContext::userId(),
            ], $items);
            if ($res['ok']) {
                $_SESSION['flash']['success'] = 'Purchase saved. Transfer items to Store when you are ready to set sell prices.';
                header('Location: ' . $base . 'view.php?id=' . (int) $res['purchase_id']);
                exit;
            }
            $error = $res['errors']['_'] ?? 'Could not save this purchase.';
        }
    }
}

$page_title = 'Record purchase';
ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h1 class="h5 fw-bold mb-1">Record purchase</h1>
    <p class="text-muted small mb-0">Log what you bought from a supplier/shop. Attach a receipt photo or number. Items stay here until you transfer them to Store (then Inventory).</p>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $base; ?>">View purchases</a>
    <a class="btn btn-sm btn-outline-primary" href="<?php echo $base; ?>transfer.php">Transfer purchases</a>
  </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" id="purchaseForm" novalidate>
  <div class="card border-0 shadow-sm mb-3" style="border-radius:12px;">
    <div class="card-body p-4">
      <h2 class="h6 fw-bold mb-3">Purchase details <span class="text-muted fw-normal small">(all optional)</span></h2>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label fw-semibold">Supplier / shop name</label>
          <div class="ta-wrap">
            <input type="text" name="supplier" class="form-control ta-input" data-field="supplier" placeholder="e.g. Bidco depot, Naivas" value="<?php echo htmlspecialchars($_POST['supplier'] ?? ''); ?>" autocomplete="off">
            <div class="ta-menu"></div>
          </div>
          <input type="hidden" name="shop_name" id="shopNameField" value="<?php echo htmlspecialchars($_POST['shop_name'] ?? ''); ?>">
          <div class="form-text">Where you bought from.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Receipt number</label>
          <input type="text" name="receipt_number" class="form-control" placeholder="e.g. INV-8821" value="<?php echo htmlspecialchars($_POST['receipt_number'] ?? ''); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Purchase date</label>
          <input type="date" name="purchase_date" class="form-control" value="<?php echo htmlspecialchars($_POST['purchase_date'] ?? date('Y-m-d')); ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold">Receipt photo</label>
          <input type="file" name="receipt_image" accept="image/*" class="form-control">
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Notes</label>
          <input type="text" name="notes" class="form-control" placeholder="optional batch note" value="<?php echo htmlspecialchars($_POST['notes'] ?? ''); ?>">
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex align-items-center justify-content-between mb-2">
    <h2 class="h6 fw-bold mb-0">Items bought <span class="text-muted fw-normal small">(all optional)</span></h2>
    <button type="button" class="btn btn-sm btn-outline-primary" id="addItemBtn"><i class="fas fa-plus me-1"></i>Add item</button>
  </div>

  <div id="purchaseItems"></div>

  <div class="mt-3 mb-5 d-flex gap-2 flex-wrap">
    <button class="btn btn-primary btn-lg"><i class="fas fa-cart-shopping me-1"></i>Save purchase</button>
    <a class="btn btn-outline-secondary btn-lg" href="<?php echo $base; ?>">Cancel</a>
  </div>
</form>

<template id="itemTemplate">
  <div class="card border-0 shadow-sm mb-3 purchase-item" style="border-radius:12px;">
    <div class="card-body p-3 p-md-4">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="fw-semibold small text-secondary">Item <span class="item-num">1</span></div>
        <button type="button" class="btn btn-sm btn-outline-danger remove-item"><i class="fas fa-trash"></i></button>
      </div>
      <div class="row g-2">
        <div class="col-md-5">
          <label class="form-label small mb-1">Product name</label>
          <input type="text" name="items[__i__][name]" class="form-control form-control-sm" placeholder="e.g. Maize flour" autocomplete="off">
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">Size / variant</label>
          <input type="text" name="items[__i__][variant_label]" class="form-control form-control-sm" placeholder="e.g. 2kg or 1kg">
        </div>
        <div class="col-md-4">
          <label class="form-label small mb-1">Barcode</label>
          <input type="text" name="items[__i__][barcode]" class="form-control form-control-sm" placeholder="optional">
        </div>
        <div class="col-md-4">
          <label class="form-label small mb-1">Category</label>
          <div class="ta-wrap">
            <input type="text" name="items[__i__][category]" class="form-control form-control-sm ta-input" data-field="category" placeholder="e.g. Cereals" autocomplete="off">
            <div class="ta-menu"></div>
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label small mb-1">Brand</label>
          <div class="ta-wrap">
            <input type="text" name="items[__i__][brand]" class="form-control form-control-sm ta-input" data-field="brand" placeholder="optional" autocomplete="off">
            <div class="ta-menu"></div>
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label small mb-1">Packaging unit</label>
          <select name="items[__i__][package_unit]" class="form-select form-select-sm pkg-unit">
            <?php foreach (array_filter($units, fn($u) => $u !== 'piece') as $u): ?>
              <option value="<?php echo htmlspecialchars($u); ?>" <?php echo $u === 'bale' ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($u)); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1 pkg-qty-label">Number of packages</label>
          <input type="number" step="0.01" min="0" name="items[__i__][package_quantity]" class="form-control form-control-sm pkg-qty" placeholder="e.g. 10">
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1 inside-label">Items inside each package</label>
          <input type="number" step="0.01" min="0" name="items[__i__][units_per_package]" class="form-control form-control-sm inside-qty" placeholder="e.g. 25">
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">Inside item unit</label>
          <select name="items[__i__][inner_unit]" class="form-select form-select-sm">
            <?php foreach ($units as $u): ?>
              <option value="<?php echo htmlspecialchars($u); ?>" <?php echo $u === 'piece' ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($u)); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">Total items (auto)</label>
          <div class="form-control form-control-sm bg-light total-items">0</div>
          <input type="hidden" name="items[__i__][quantity]" class="qty-hidden" value="">
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1 buy-label">Buying price per package</label>
          <input type="number" step="0.01" min="0" name="items[__i__][buying_price]" class="form-control form-control-sm buy-price" placeholder="0">
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">Faulty items</label>
          <input type="number" step="0.01" min="0" name="items[__i__][faulty_quantity]" class="form-control form-control-sm" value="0">
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">Photo</label>
          <input type="file" name="items[__i__][image]" accept="image/*" class="form-control form-control-sm">
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">Remark</label>
          <input type="text" name="items[__i__][remark]" class="form-control form-control-sm" placeholder="optional">
        </div>
        <div class="col-12">
          <div class="line-cost small text-muted"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<style>
  .ta-wrap { position: relative; }
  .ta-menu {
    position: absolute; left: 0; right: 0; top: 100%; z-index: 40;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
    box-shadow: 0 8px 20px rgba(15,23,42,.08); margin-top: 2px; max-height: 220px; overflow-y: auto; display: none;
  }
  .ta-menu.show { display: block; }
  .ta-menu button {
    display: block; width: 100%; text-align: left; background: none; border: 0;
    padding: .45rem .7rem; font-size: .9rem; cursor: pointer;
  }
  .ta-menu button:hover { background: #f1f5f9; }
</style>
<script>
(function () {
  var API = <?php echo json_encode($apiBase); ?>;
  var wrap = document.getElementById('purchaseItems');
  var tpl = document.getElementById('itemTemplate');
  var idx = 0;

  function money(n) { return 'KES ' + (Math.round(n * 100) / 100).toLocaleString(); }

  function bindTypeahead(root) {
    (root || document).querySelectorAll('.ta-input').forEach(function (input) {
      if (input.dataset.bound) return;
      input.dataset.bound = '1';
      var menu = input.parentElement.querySelector('.ta-menu');
      var timer = null;
      function render(items) {
        menu.innerHTML = '';
        if (!items.length) { menu.classList.remove('show'); return; }
        items.forEach(function (it) {
          var b = document.createElement('button');
          b.type = 'button';
          b.textContent = it.name;
          b.addEventListener('mousedown', function (e) {
            e.preventDefault();
            input.value = it.name;
            menu.classList.remove('show');
          });
          menu.appendChild(b);
        });
        menu.classList.add('show');
      }
      input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (!q) { menu.classList.remove('show'); return; }
        timer = setTimeout(function () {
          fetch(API + 'suggest.php?field=' + encodeURIComponent(input.dataset.field) + '&q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (d) { render(d.items || []); })
            .catch(function () { menu.classList.remove('show'); });
        }, 180);
      });
      input.addEventListener('blur', function () { setTimeout(function () { menu.classList.remove('show'); }, 150); });
    });
  }

  function recalc(card) {
    var unit = (card.querySelector('.pkg-unit') || {}).value || 'package';
    var pkgQty = parseFloat((card.querySelector('.pkg-qty') || {}).value) || 0;
    var inside = parseFloat((card.querySelector('.inside-qty') || {}).value) || 0;
    var buy = parseFloat((card.querySelector('.buy-price') || {}).value) || 0;
    var total = (pkgQty > 0 && inside > 0) ? Math.round(pkgQty * inside * 100) / 100 : 0;
    var totalEl = card.querySelector('.total-items');
    var qtyHidden = card.querySelector('.qty-hidden');
    if (totalEl) totalEl.textContent = String(total);
    if (qtyHidden) qtyHidden.value = total > 0 ? String(total) : '';
    var pkgLabel = card.querySelector('.pkg-qty-label');
    var insideLabel = card.querySelector('.inside-label');
    var buyLabel = card.querySelector('.buy-label');
    if (pkgLabel) pkgLabel.textContent = 'Number of ' + unit + 's';
    if (insideLabel) insideLabel.textContent = 'Items inside each ' + unit;
    if (buyLabel) buyLabel.textContent = 'Buying price per ' + unit;
    var cost = pkgQty > 0 ? pkgQty * buy : 0;
    var line = card.querySelector('.line-cost');
    if (line) {
      line.textContent = cost > 0
        ? ('Line cost: ' + money(cost) + (total > 0 ? (' · unit cost ≈ ' + money(buy / (inside || 1))) : ''))
        : '';
    }
  }

  function addItem() {
    var html = tpl.innerHTML.replace(/__i__/g, String(idx++));
    var div = document.createElement('div');
    div.innerHTML = html;
    var card = div.firstElementChild;
    wrap.appendChild(card);
    renumber();
    bindTypeahead(card);
    card.querySelectorAll('.pkg-unit, .pkg-qty, .inside-qty, .buy-price').forEach(function (el) {
      el.addEventListener('input', function () { recalc(card); });
      el.addEventListener('change', function () { recalc(card); });
    });
    card.querySelector('.remove-item').addEventListener('click', function () {
      if (wrap.children.length <= 1) return;
      card.remove();
      renumber();
    });
    recalc(card);
  }

  function renumber() {
    Array.prototype.forEach.call(wrap.querySelectorAll('.purchase-item'), function (card, i) {
      var n = card.querySelector('.item-num');
      if (n) n.textContent = String(i + 1);
    });
  }

  document.getElementById('addItemBtn').addEventListener('click', addItem);
  var supplierInput = document.querySelector('input[name="supplier"]');
  if (supplierInput) {
    supplierInput.addEventListener('input', function () {
      document.getElementById('shopNameField').value = supplierInput.value;
    });
  }
  bindTypeahead(document);
  addItem();
})();
</script>
<?php
$content = ob_get_clean();
$__layout = TenantContext::role() === 'staff' ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
