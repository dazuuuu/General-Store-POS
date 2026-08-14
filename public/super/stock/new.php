<?php
// public/super/stock/new.php — bulk product intake for a general shop:
// product name, category, brand, unit (kg/bale/carton…), good qty,
// faulty/broken qty, prices, barcode. Same UI card styles as before.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::STOCK_ENTER);

$pdo = Database::pdo();
$SUP = new Models\SupplierModel($pdo);
$C   = new Models\CategoryModel($pdo);
$BA  = new Models\BookAttributeModel($pdo);
$SP  = new Models\StoreProductModel($pdo);
$P   = new Models\ProductModel($pdo);

$base = public_url('super/stock/new.php');
$apiBase = public_url('api/inventory/');
$units = Models\ProductModel::UNITS;

function stock_row_image_file(int $i): array
{
    if (!isset($_FILES['items']['name'][$i]['image']) || $_FILES['items']['name'][$i]['image'] === '') {
        return ['error' => UPLOAD_ERR_NO_FILE];
    }
    return [
        'name'     => $_FILES['items']['name'][$i]['image'],
        'type'     => $_FILES['items']['type'][$i]['image'],
        'tmp_name' => $_FILES['items']['tmp_name'][$i]['image'],
        'error'    => $_FILES['items']['error'][$i]['image'],
        'size'     => $_FILES['items']['size'][$i]['image'],
    ];
}

function stock_handle_image(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Image upload failed. Try a smaller file.'];
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
        return ['ok' => false, 'error' => 'Could not save the image. Check folder permissions.'];
    }
    return ['ok' => true, 'path' => public_url('assets/uploads/products/' . $name)];
}

function stock_package_fields(array $row, array $units): array
{
    $receiveUnit = in_array($row['unit'] ?? '', $units, true) ? $row['unit'] : 'carton';
    if ($receiveUnit === 'piece') {
        $receiveUnit = 'pack';
    }
    $packageQty = max(0, (float) ($row['package_quantity'] ?? 0));
    $inside = max(0, (float) ($row['units_per_package'] ?? 0));
    $packageCost = max(0, (float) ($row['buying_price'] ?? 0));
    $packageWholesale = max(0, (float) ($row['wholesale_price'] ?? 0));
    $innerUnit = in_array($row['inner_unit'] ?? '', $units, true) ? $row['inner_unit'] : 'piece';
    if ($packageQty <= 0 || $inside <= 0) {
        return [
            'quantity' => 0,
            'faulty_quantity' => max(0, (float) ($row['faulty_quantity'] ?? 0)),
            'unit' => $innerUnit,
            'buying_price' => 0,
            'package_buying_price' => $packageCost > 0 ? $packageCost : null,
            'wholesale_price' => '',
            'package_unit' => $receiveUnit,
            'package_quantity' => $packageQty > 0 ? $packageQty : null,
            'units_per_package' => $inside > 0 ? $inside : 1,
            'package_price' => $packageWholesale > 0 ? $packageWholesale : null,
        ];
    }

    return [
        'quantity' => round($packageQty * $inside, 2),
        'faulty_quantity' => round(max(0, (float) ($row['faulty_quantity'] ?? 0)) * $inside, 2),
        'unit' => $innerUnit,
        'buying_price' => round($packageCost / $inside, 2),
        'package_buying_price' => $packageCost,
        'wholesale_price' => round($packageWholesale / $inside, 2),
        'package_unit' => $receiveUnit,
        'package_quantity' => $packageQty,
        'units_per_package' => $inside,
        'package_price' => $packageWholesale,
    ];
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplierName = trim($_POST['supplier'] ?? '');
    $supplierId = $supplierName !== '' ? (int) $SUP->findOrCreate($supplierName) : 0;
    $rows = $_POST['items'] ?? [];

    if (!$error) {
        $items = [];
        foreach ($rows as $i => $row) {
            $pkg = stock_package_fields($row, $units);
            $title = trim($row['title'] ?? '');
            $productChoice = trim($row['product_choice'] ?? '');
            $hasContent = $title !== '' || $productChoice !== ''
                || (float) ($row['package_quantity'] ?? 0) > 0
                || (float) ($row['buying_price'] ?? 0) > 0;
            if (!$hasContent) {
                continue;
            }
            if ($productChoice === '' && $title === '') {
                $error = 'Enter a product name for each filled row.';
                break;
            }
            if ((float) ($row['package_quantity'] ?? 0) <= 0) {
                $error = ($title !== '' ? $title . ': ' : '') . 'Enter how many packages (cartons/bales) you received.';
                break;
            }
            if ((float) ($row['units_per_package'] ?? 0) <= 0) {
                $error = ($title !== '' ? $title . ': ' : '') . 'Enter how many items are inside each package.';
                break;
            }
            if (($pkg['package_buying_price'] ?? 0) <= 0) {
                $error = ($title !== '' ? $title . ': ' : '') . 'Enter the buying price of each package.';
                break;
            }
            if ($productChoice === '' && (float) ($row['selling_price'] ?? 0) <= 0) {
                $error = ($title !== '' ? $title . ': ' : '') . 'Enter the retail price of a single item inside.';
                break;
            }
            if ($productChoice === '' && ($pkg['package_price'] ?? 0) <= 0) {
                $error = ($title !== '' ? $title . ': ' : '') . 'Enter the wholesale selling price of each package.';
                break;
            }
            $qty = (float) $pkg['quantity'];
            $faulty = (float) $pkg['faulty_quantity'];
            if ($qty <= 0) {
                $error = ($title !== '' ? $title . ': ' : '') . 'Packages × items inside must be greater than zero.';
                break;
            }

            $remark = trim($row['remark'] ?? '');
            $unit = $pkg['unit'];
            $batchNotes = trim((string) ($_POST['notes'] ?? ''));
            $lineNotes = $remark !== '' ? $remark : $batchNotes;

            if ($productChoice !== '') {
                $existing = $P->find((int) $productChoice);
                if (!$existing || (int) $existing['tenant_id'] !== (int) TenantContext::tenantId()) {
                    $error = ($title !== '' ? $title . ': ' : '') . 'Matched inventory product was not found.';
                    break;
                }
                $items[] = [
                    'product_id' => (int) $existing['id'],
                    'name' => $existing['name'],
                    'category_id' => (int) ($existing['category_id'] ?? 0),
                    'brand_id' => (int) ($existing['brand_id'] ?? 0),
                    'supplier_id' => $supplierId,
                    'barcode' => $existing['barcode'] ?? '',
                    'unit' => $unit,
                    'package_unit' => $pkg['package_unit'],
                    'package_quantity' => $pkg['package_quantity'],
                    'units_per_package' => $pkg['units_per_package'],
                    'package_price' => $pkg['package_price'],
                    'package_buying_price' => $pkg['package_buying_price'],
                    'colors' => '',
                    'quantity' => $qty,
                    'faulty_quantity' => $faulty,
                    'buying_price' => $pkg['buying_price'],
                    'retail_price' => $existing['retail_price'] ?? $existing['selling_price'] ?? 0,
                    'wholesale_price' => $pkg['wholesale_price'] !== '' ? $pkg['wholesale_price'] : ($existing['wholesale_price'] ?? 0),
                    'offer_price' => '',
                    'offer_starts_at' => '',
                    'offer_ends_at' => '',
                    'image_path' => '',
                    'notes' => $lineNotes,
                ];
                continue;
            }

            $title = trim($row['title'] ?? '');
            if ($title === '') { continue; }

            $img = stock_handle_image(stock_row_image_file((int) $i));
            if (!$img['ok']) { $error = $title . ': ' . $img['error']; break; }

            $items[] = [
                'product_id' => 0,
                'name' => $title,
                'category_id' => (int) $C->findOrCreate($row['category'] ?? '', 'product'),
                'brand_id' => (int) $BA->findOrCreate('brand', $row['brand'] ?? ''),
                'supplier_id' => $supplierId,
                'barcode' => trim($row['barcode'] ?? ''),
                'unit' => $unit,
                'package_unit' => $pkg['package_unit'],
                'package_quantity' => $pkg['package_quantity'],
                'units_per_package' => $pkg['units_per_package'],
                'package_price' => $pkg['package_price'],
                'package_buying_price' => $pkg['package_buying_price'],
                'colors' => '',
                'quantity' => $qty,
                'faulty_quantity' => $faulty,
                'buying_price' => $pkg['buying_price'],
                'retail_price' => $row['selling_price'] ?? 0,
                'wholesale_price' => $pkg['wholesale_price'],
                'offer_price' => $row['offer_price'] ?? '',
                'offer_starts_at' => $row['offer_starts_at'] ?? '',
                'offer_ends_at' => $row['offer_ends_at'] ?? '',
                'image_path' => $img['path'] ?? '',
                'notes' => $lineNotes,
            ];
        }

        if (!$error && !$items) {
            $error = 'Add at least one product with a good (sellable) quantity.';
        }

        if (!$error) {
            $res = $SP->createMany($items, TenantContext::userId());
            if ($res['ok']) {
                $_SESSION['flash']['success'] = $res['created'] . ' product' . ($res['created'] === 1 ? '' : 's') . ' saved to Store (warehouse). Generate an internal transfer invoice to move them into shop Inventory.';
                header('Location: ' . public_url('super/store/'));
                exit;
            }
            $error = $res['error'] ?? 'Could not record this delivery to Store.';
        }
    }
}

$page_title = 'Record products to Store';
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" id="stockForm" novalidate>
  <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
      <h2 class="h5 mb-3">Warehouse delivery (Store)</h2>
      <p class="text-muted small mb-3">Products land in the <strong>Store warehouse</strong> first. Use <a href="<?php echo public_url('super/store/'); ?>">Store → Generate invoice</a> to transfer into shop Inventory for selling — no double entry.</p>
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label">Supplier <span class="text-muted">(optional)</span></label>
          <div class="ta-wrap">
            <input type="text" name="supplier" class="form-control ta-input" data-field="supplier" placeholder="e.g. Nairobi Distributors" autocomplete="off">
            <div class="ta-menu"></div>
          </div>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Notes <span class="text-muted">(optional)</span></label>
          <input name="notes" class="form-control" placeholder="e.g. invoice #, delivery date">
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Products received into Store</h2>
        <button type="button" class="btn btn-sm btn-outline-primary" id="addRowBtn"><i class="fas fa-plus me-1"></i>Add another product</button>
      </div>
      <div id="rows"></div>
      <div class="d-flex justify-content-end pt-2 border-top mt-2">
        <div class="text-muted small">
          Grand totals:
          Cost <strong id="grandTotal">KES 0</strong>
          · Wholesale return <strong id="grandWholesaleTotal">KES 0</strong>
          (<strong id="grandWholesaleProfit">KES 0</strong>, <span id="grandWholesaleMargin">0.0%</span>)
          · Retail return <strong id="grandRetailTotal">KES 0</strong>
          (<strong id="grandRetailProfit">KES 0</strong>, <span id="grandRetailMargin">0.0%</span>)
        </div>
      </div>
    </div>
  </div>

  <button class="btn btn-primary btn-lg"><i class="fas fa-boxes-stacked me-1"></i>Save products to Store warehouse</button>
</form>

<template id="rowTpl">
  <div class="stock-row border rounded p-3 mb-3" style="border-color:#e2e8f0!important;">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <span class="fw-semibold small text-muted">Product __N__</span>
      <button type="button" class="btn btn-sm btn-link text-danger p-0 removeRow">Remove</button>
    </div>
    <div class="row g-2">
      <div class="col-12 col-sm-6">
        <label class="form-label small mb-1">Product name</label>
        <div class="ta-wrap">
          <input type="text" name="items[__I__][title]" class="form-control form-control-sm ta-input productTitle" data-field="title" placeholder="e.g. Yellow beans, Soft drink 500ml" autocomplete="off">
          <div class="ta-menu"></div>
        </div>
        <input type="hidden" name="items[__I__][product_choice]" class="productChoice" value="">
        <div class="matchNote small mt-1" style="display:none;"></div>
      </div>
      <div class="col-12 col-sm-6">
        <label class="form-label small mb-1"><i class="fas fa-barcode me-1"></i>Barcode <span class="text-muted">(optional — scan it)</span></label>
        <input type="text" name="items[__I__][barcode]" class="form-control form-control-sm barcodeInput" placeholder="Scan or type a barcode" autocomplete="off">
        <div class="barcodeNote small mt-1" style="display:none;"></div>
      </div>
      <div class="col-12 col-sm-6 photoCol newProductFields">
        <label class="form-label small mb-1">Photo <span class="text-muted">(optional)</span></label>
        <div class="d-flex align-items-center gap-2">
          <input type="file" name="items[__I__][image]" accept="image/*" class="form-control form-control-sm photoInput">
          <img class="photoPreview" style="display:none;width:36px;height:36px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;">
        </div>
      </div>

      <div class="col-6 col-sm-3 mt-2 newProductFields">
        <label class="form-label small mb-1">Category</label>
        <div class="ta-wrap">
          <input type="text" name="items[__I__][category]" class="form-control form-control-sm ta-input" data-field="category" placeholder="e.g. Cereals, Drinks" autocomplete="off">
          <div class="ta-menu"></div>
        </div>
      </div>
      <div class="col-6 col-sm-3 mt-2 newProductFields">
        <label class="form-label small mb-1">Brand <span class="text-muted">(optional)</span></label>
        <div class="ta-wrap">
          <input type="text" name="items[__I__][brand]" class="form-control form-control-sm ta-input" data-field="brand" placeholder="e.g. Coca-Cola" autocomplete="off">
          <div class="ta-menu"></div>
        </div>
      </div>
      <div class="col-6 col-sm-3 mt-2">
        <label class="form-label small mb-1">Received as <span class="text-danger">*</span></label>
        <select name="items[__I__][unit]" class="form-select form-select-sm unitSelect" required>
          <?php foreach (array_values(array_filter($units, fn($u) => $u !== 'piece')) as $u): ?>
            <option value="<?php echo htmlspecialchars($u); ?>" <?php echo $u === 'carton' ? 'selected' : ''; ?>><?php echo htmlspecialchars($u); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 mt-2 packageFields">
        <div class="border rounded p-2" style="border-color:#e2e8f0!important;">
          <div class="small fw-semibold mb-2 text-danger">Package details (all required)</div>
          <div class="row g-2">
            <div class="col-6 col-sm-3">
              <label class="form-label small mb-1 packageQtyLabel">Number of packages <span class="text-danger">*</span></label>
              <input type="number" step="0.01" min="0.01" name="items[__I__][package_quantity]" class="form-control form-control-sm packageQty" required placeholder="e.g. 20">
            </div>
            <div class="col-6 col-sm-3">
              <label class="form-label small mb-1 unitsPerPackageLabel">Items inside each package <span class="text-danger">*</span></label>
              <input type="number" step="0.01" min="0.01" name="items[__I__][units_per_package]" class="form-control form-control-sm unitsPerPackage" required placeholder="e.g. 12">
            </div>
            <div class="col-6 col-sm-3">
              <label class="form-label small mb-1">Inside unit</label>
              <select name="items[__I__][inner_unit]" class="form-select form-select-sm">
                <?php foreach ($units as $u): ?>
                  <option value="<?php echo htmlspecialchars($u); ?>"><?php echo htmlspecialchars($u); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6 col-sm-3">
              <label class="form-label small mb-1">Total items</label>
              <div class="form-control form-control-sm bg-light totalItems">0</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-6 col-sm-3 mt-2">
        <label class="form-label small mb-1 qtyLabel">Total sellable items</label>
        <input type="number" step="0.01" min="0" name="items[__I__][quantity]" class="form-control form-control-sm qty" readonly placeholder="0">
      </div>
      <div class="col-6 col-sm-3 mt-2">
        <label class="form-label small mb-1">Faulty / broken packages</label>
        <input type="number" step="0.01" min="0" name="items[__I__][faulty_quantity]" class="form-control form-control-sm" placeholder="0">
      </div>
      <div class="col-6 col-sm-3 mt-2">
        <label class="form-label small mb-1 buyingLabel">Buying price of the package <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0.01" name="items[__I__][buying_price]" class="form-control form-control-sm buyingPrice" required placeholder="0">
      </div>
      <div class="col-6 col-sm-3 mt-2 newProductFields">
        <label class="form-label small mb-1 wholesaleLabel">Selling price of the package (wholesale) <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0.01" name="items[__I__][wholesale_price]" class="form-control form-control-sm wholesalePrice" required placeholder="0">
      </div>
      <div class="col-6 col-sm-3 mt-2 newProductFields">
        <label class="form-label small mb-1 retailLabel">Selling price of items inside (retail) <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0.01" name="items[__I__][selling_price]" class="form-control form-control-sm retailPrice" required placeholder="0">
      </div>
      <div class="col-12 mt-2 newProductFields">
        <div class="border rounded p-2" style="border-color:#e2e8f0!important;">
          <div class="form-check">
            <input class="form-check-input offerToggle" type="checkbox" id="offerToggle__I__">
            <label class="form-check-label small fw-semibold" for="offerToggle__I__"><i class="fas fa-tag me-1 text-warning"></i>Offer for this product</label>
          </div>
          <div class="row g-2 mt-1 offerFields" style="display:none;">
            <div class="col-12 col-sm-4">
              <label class="form-label small mb-1">Offer price</label>
              <input type="number" step="0.01" min="0" name="items[__I__][offer_price]" class="form-control form-control-sm" placeholder="0">
            </div>
            <div class="col-12 col-sm-4">
              <label class="form-label small mb-1">Starts</label>
              <input type="datetime-local" name="items[__I__][offer_starts_at]" class="form-control form-control-sm">
            </div>
            <div class="col-12 col-sm-4">
              <label class="form-label small mb-1">Ends</label>
              <input type="datetime-local" name="items[__I__][offer_ends_at]" class="form-control form-control-sm">
            </div>
          </div>
        </div>
      </div>

      <div class="col-6 col-sm-4 mt-2">
        <label class="form-label small mb-1">Expected return</label>
        <div class="form-control form-control-sm bg-light rowTotal" data-value="0" data-wholesale-value="0" data-retail-value="0" style="height:auto;min-height:31px;">Cost: KES 0</div>
      </div>
      <div class="col-6 col-sm-8 mt-2">
        <label class="form-label small mb-1">Remark <span class="text-muted">(optional)</span></label>
        <input type="text" name="items[__I__][remark]" class="form-control form-control-sm" placeholder="e.g. torn packaging, wet carton">
      </div>
    </div>
  </div>
</template>

<style>
  .stock-row .newProductFields { display: block; }
  .stock-row.is-restock .newProductFields { display: none !important; }
  .ta-wrap { position: relative; }
  .ta-menu {
    position: absolute; left: 0; right: 0; top: 100%; z-index: 40;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
    box-shadow: 0 8px 20px rgba(15,23,42,.08); margin-top: 2px; max-height: 220px; overflow-y: auto; display: none;
  }
  .ta-menu.show { display: block; }
  .ta-menu button {
    display: block; width: 100%; text-align: left; background: none; border: 0;
    padding: .4rem .65rem; font-size: .85rem; cursor: pointer;
  }
  .ta-menu button:hover, .ta-menu button.active { background: #f1f5f9; }
  .matchNote { color: #0d6efd; }
</style>
<script>
(function () {
  var API = <?php echo json_encode($apiBase); ?>;
  var tplHtml = document.getElementById('rowTpl').innerHTML;
  var rowsWrap = document.getElementById('rows');
  var idx = 0;

  function money(n) { return 'KES ' + (Math.round(n * 100) / 100).toLocaleString(); }

  function addRow() {
    var html = tplHtml.replace(/__I__/g, idx).replace(/__N__/g, idx + 1);
    var wrap = document.createElement('div');
    wrap.innerHTML = html;
    var row = wrap.firstElementChild;
    rowsWrap.appendChild(row);
    wireRow(row);
    idx++;
  }

  // Free-text typeahead: suggests stored values; typing a new name still works (no forced dropdown).
  function attachTypeahead(input, field, onPick) {
    var wrap = input.closest('.ta-wrap');
    if (!wrap) return;
    var menu = wrap.querySelector('.ta-menu');
    if (!menu) return;
    var timer = null;
    function render(items) {
      menu.innerHTML = '';
      if (!items.length) { menu.classList.remove('show'); return; }
      items.forEach(function (item) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = item.name;
        b.addEventListener('mousedown', function (e) {
          e.preventDefault();
          input.value = item.name;
          menu.classList.remove('show');
          if (onPick) onPick(item);
        });
        menu.appendChild(b);
      });
      menu.classList.add('show');
    }
    input.addEventListener('input', function () {
      if (onPick) onPick(null);
      clearTimeout(timer);
      var q = input.value.trim();
      if (!q) { menu.classList.remove('show'); return; }
      timer = setTimeout(function () {
        fetch(API + 'suggest.php?field=' + encodeURIComponent(field) + '&q=' + encodeURIComponent(q))
          .then(function (r) { return r.json(); })
          .then(function (data) { render(data.items || []); })
          .catch(function () {});
      }, 180);
    });
    input.addEventListener('blur', function () { setTimeout(function () { menu.classList.remove('show'); }, 150); });
  }

  function makeRestockControls(row) {
    var titleInput = row.querySelector('.productTitle');
    var barcodeInput = row.querySelector('.barcodeInput');
    var productChoice = row.querySelector('.productChoice');
    var note = row.querySelector('.matchNote');
    var lastMatchedId = null;

    function setRestock(item) {
      productChoice.value = item.id;
      lastMatchedId = item.id;
      row.classList.add('is-restock');
      titleInput.value = item.name;
      if (item.barcode) { barcodeInput.value = item.barcode; }
      if (item.buying_price) { row.querySelector('.buyingPrice').value = item.buying_price; }
      if (item.retail_price) { row.querySelector('.retailPrice').value = item.retail_price; }
      if (item.wholesale_price) { row.querySelector('.wholesalePrice').value = item.pack_price && item.pack_price > 0 ? item.pack_price : item.wholesale_price; }
      row.querySelector('.qtyLabel').textContent = 'Qty to add';
      var bits = [item.category_name || item.subject_name, item.brand_name || item.publisher_name, item.unit].filter(Boolean);
      note.style.display = 'block';
      note.innerHTML = '<i class="fas fa-circle-check me-1"></i>Already in stock' +
        (bits.length ? ' — ' + bits.join(' · ') : '') +
        '. Current balance: <strong>' + (item.balance != null ? item.balance : '') + '</strong>. This adds to it.';
    }

    function clearRestock() {
      productChoice.value = '';
      lastMatchedId = null;
      row.classList.remove('is-restock');
      row.querySelector('.qtyLabel').textContent = 'Good qty received';
      note.style.display = 'none';
      note.innerHTML = '';
    }

    return { setRestock: setRestock, clearRestock: clearRestock, isMatched: function () { return lastMatchedId !== null; } };
  }

  function wireTitleField(row, restock) {
    var input = row.querySelector('.productTitle');
    var wrap = input.closest('.ta-wrap');
    var menu = wrap.querySelector('.ta-menu');
    var timer = null;
    var lastMatchedName = null;

    function render(items) {
      menu.innerHTML = '';
      if (!items.length) { menu.classList.remove('show'); return; }
      items.forEach(function (item) {
        var b = document.createElement('button');
        b.type = 'button';
        var bits = [item.category_name || item.subject_name, item.brand_name || item.publisher_name, item.unit].filter(Boolean);
        b.innerHTML = '<span class="fw-semibold">' + item.name + '</span>' +
          (bits.length ? ' <span class="text-muted">— ' + bits.join(' · ') + '</span>' : '') +
          ' <span class="text-muted">(balance ' + item.balance + ')</span>';
        b.addEventListener('mousedown', function (e) {
          e.preventDefault();
          lastMatchedName = item.name;
          menu.classList.remove('show');
          restock.setRestock(item);
        });
        menu.appendChild(b);
      });
      menu.classList.add('show');
    }

    input.addEventListener('input', function () {
      if (lastMatchedName !== null && input.value !== lastMatchedName) {
        lastMatchedName = null;
        restock.clearRestock();
      }
      clearTimeout(timer);
      var q = input.value.trim();
      if (!q) { menu.classList.remove('show'); return; }
      timer = setTimeout(function () {
        fetch(API + 'find_titles.php?type=product&q=' + encodeURIComponent(q))
          .then(function (r) { return r.json(); })
          .then(function (data) { render(data.items || []); })
          .catch(function () {});
      }, 180);
    });
    input.addEventListener('blur', function () { setTimeout(function () { menu.classList.remove('show'); }, 150); });
  }

  function wireBarcodeField(row, restock) {
    var input = row.querySelector('.barcodeInput');
    var note = row.querySelector('.barcodeNote');
    var lastChecked = null;

    function lookup() {
      var code = input.value.trim();
      if (!code || code === lastChecked) return;
      lastChecked = code;
      fetch(API + 'find_barcode.php?code=' + encodeURIComponent(code))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (input.value.trim() !== code) return;
          if (data.item) {
            restock.setRestock(data.item);
            note.style.display = 'none';
          } else {
            note.style.display = 'block';
            note.style.color = '#64748b';
            note.innerHTML = '<i class="fas fa-circle-plus me-1"></i>New barcode — will be saved with this product.';
          }
        })
        .catch(function () {});
    }

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); lookup(); }
    });
    input.addEventListener('blur', lookup);
    input.addEventListener('input', function () { note.style.display = 'none'; });
  }

  function wireRow(row) {
    row.querySelector('.removeRow').addEventListener('click', function () {
      row.remove();
      recalc();
    });

    var restock = makeRestockControls(row);
    wireTitleField(row, restock);
    wireBarcodeField(row, restock);

    ['category', 'brand'].forEach(function (field) {
      var el = row.querySelector('[data-field="' + field + '"]');
      if (el) attachTypeahead(el, field);
    });

    row.querySelectorAll('.qty, .buyingPrice, .retailPrice, .wholesalePrice, .unitSelect, .packageQty, .unitsPerPackage').forEach(function (el) {
      el.addEventListener('input', recalc);
      el.addEventListener('change', recalc);
    });

    var offerToggle = row.querySelector('.offerToggle');
    if (offerToggle) {
      offerToggle.addEventListener('change', function () {
        var fields = row.querySelector('.offerFields');
        fields.style.display = offerToggle.checked ? 'flex' : 'none';
        var price = fields.querySelector('[name$="[offer_price]"]');
        var ends = fields.querySelector('[name$="[offer_ends_at]"]');
        if (offerToggle.checked && !ends.value) {
          var d = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000);
          d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
          ends.value = d.toISOString().slice(0, 16);
        }
        if (!offerToggle.checked && price) { price.value = ''; }
      });
    }

    var photo = row.querySelector('.photoInput');
    if (photo) {
      photo.addEventListener('change', function () {
        var prev = row.querySelector('.photoPreview');
        if (photo.files && photo.files[0]) {
          prev.src = URL.createObjectURL(photo.files[0]);
          prev.style.display = 'block';
        }
      });
    }
  }

  function recalc() {
    var grand = 0, grandWholesale = 0, grandRetail = 0;
    document.querySelectorAll('.stock-row').forEach(function (row) {
      var unit = row.querySelector('.unitSelect').value;
      var pkgFields = row.querySelector('.packageFields');
      var qtyInput = row.querySelector('.qty');
      var pkgQty = parseFloat((row.querySelector('.packageQty') || {}).value) || 0;
      var perPkg = parseFloat((row.querySelector('.unitsPerPackage') || {}).value) || 0;
      var qty = (pkgQty > 0 && perPkg > 0) ? (pkgQty * perPkg) : 0;
      var buy = parseFloat(row.querySelector('.buyingPrice').value) || 0;
      var retail = parseFloat(row.querySelector('.retailPrice').value) || 0;
      var wholesale = parseFloat(row.querySelector('.wholesalePrice').value) || 0;
      if (pkgFields) pkgFields.style.display = 'block';
      var packageQtyLabel = row.querySelector('.packageQtyLabel');
      var unitsPerPackageLabel = row.querySelector('.unitsPerPackageLabel');
      if (packageQtyLabel) packageQtyLabel.innerHTML = 'Number of ' + unit + 's <span class="text-danger">*</span>';
      if (unitsPerPackageLabel) unitsPerPackageLabel.innerHTML = 'Items inside each ' + unit + ' <span class="text-danger">*</span>';
      qtyInput.value = qty > 0 ? (Math.round(qty * 100) / 100) : '';
      qtyInput.readOnly = true;
      row.querySelector('.qtyLabel').textContent = 'Total sellable items';
      row.querySelector('.buyingLabel').innerHTML = 'Buying price of the package <span class="text-danger">*</span>';
      row.querySelector('.wholesaleLabel').innerHTML = 'Selling price of the package (wholesale) <span class="text-danger">*</span>';
      row.querySelector('.retailLabel').innerHTML = 'Selling price of items inside (retail) <span class="text-danger">*</span>';
      var totalItems = row.querySelector('.totalItems');
      if (totalItems) totalItems.textContent = Math.round(qty * 100) / 100;
      var total = pkgQty * buy;
      var wholesaleReturn = pkgQty * wholesale;
      var retailReturn = qty * retail;
      var wholesaleProfit = wholesaleReturn - total;
      var retailProfit = retailReturn - total;
      var wholesaleMargin = wholesaleReturn > 0 ? (wholesaleProfit / wholesaleReturn * 100) : 0;
      var retailMargin = retailReturn > 0 ? (retailProfit / retailReturn * 100) : 0;
      var el = row.querySelector('.rowTotal');
      el.dataset.value = total;
      el.dataset.wholesaleValue = wholesaleReturn;
      el.dataset.retailValue = retailReturn;
      el.innerHTML = 'Cost: <strong>' + money(total) + '</strong>'
        + '<br><span class="text-muted">Package wholesale return:</span> <strong>' + money(wholesaleReturn) + '</strong>'
        + ' <span class="' + (wholesaleProfit < 0 ? 'text-danger' : 'text-success') + '">Profit ' + money(wholesaleProfit) + ' · ' + wholesaleMargin.toFixed(1) + '%</span>'
        + '<br><span class="text-muted">Retail return:</span> <strong>' + money(retailReturn) + '</strong>'
        + ' <span class="' + (retailProfit < 0 ? 'text-danger' : 'text-success') + '">Profit ' + money(retailProfit) + ' · ' + retailMargin.toFixed(1) + '%</span>';
      grand += total;
      grandWholesale += wholesaleReturn;
      grandRetail += retailReturn;
    });
    document.getElementById('grandTotal').textContent = money(grand);
    document.getElementById('grandWholesaleTotal').textContent = money(grandWholesale);
    document.getElementById('grandRetailTotal').textContent = money(grandRetail);
    var grandWholesaleProfit = grandWholesale - grand;
    var grandRetailProfit = grandRetail - grand;
    document.getElementById('grandWholesaleProfit').textContent = money(grandWholesaleProfit);
    document.getElementById('grandRetailProfit').textContent = money(grandRetailProfit);
    document.getElementById('grandWholesaleMargin').textContent = (grandWholesale > 0 ? (grandWholesaleProfit / grandWholesale * 100) : 0).toFixed(1) + '%';
    document.getElementById('grandRetailMargin').textContent = (grandRetail > 0 ? (grandRetailProfit / grandRetail * 100) : 0).toFixed(1) + '%';
  }

  document.getElementById('addRowBtn').addEventListener('click', addRow);
  addRow();

  var supplierInput = document.querySelector('.ta-input[data-field="supplier"]');
  if (supplierInput) attachTypeahead(supplierInput, 'supplier');
})();
</script>
<?php
$content = ob_get_clean();
$__layout = TenantContext::role() === 'staff' ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
