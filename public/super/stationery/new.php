<?php
// public/super/stationery/new.php — single-product intake (general shop).
// Kept at this path so existing menu links keep working; UI is product-only.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::STOCK_ENTER);

$pdo = Database::pdo();
$SUP = new Models\SupplierModel($pdo);
$C   = new Models\CategoryModel($pdo);
$BA  = new Models\BookAttributeModel($pdo);
$SP  = new Models\StoreProductModel($pdo);
$P   = new Models\ProductModel($pdo);
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

function single_product_package_fields(array $row, array $units): array
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
    $name = trim($_POST['name'] ?? '');
    $pkg = single_product_package_fields($_POST, $units);
    $qty = (float) $pkg['quantity'];
    $faulty = (float) $pkg['faulty_quantity'];
    $unit = $pkg['unit'];
    $supplierId = trim($_POST['supplier'] ?? '') !== '' ? (int) $SUP->findOrCreate($_POST['supplier']) : 0;

    if ($name === '') {
        $error = 'Product name is required.';
    } elseif ((float) ($_POST['package_quantity'] ?? 0) <= 0) {
        $error = 'Enter how many packages (cartons/bales/etc.) you received.';
    } elseif ((float) ($_POST['units_per_package'] ?? 0) <= 0) {
        $error = 'Enter how many items are inside each package.';
    } elseif (($pkg['package_buying_price'] ?? 0) <= 0 && (float) ($_POST['buying_price'] ?? 0) <= 0) {
        $error = 'Enter the buying price of each package (carton/bale).';
    } elseif (($pkg['package_price'] ?? 0) <= 0 && (float) ($_POST['wholesale_price'] ?? 0) <= 0) {
        $error = 'Enter the wholesale selling price of each package.';
    } elseif ((float) ($_POST['selling_price'] ?? 0) <= 0) {
        $error = 'Enter the retail price of a single item inside the package.';
    } elseif ($qty <= 0) {
        $error = 'Packages × items inside must be greater than zero.';
    } else {
        $img = single_product_handle_image($_FILES['image'] ?? []);
        if (!$img['ok']) {
            $error = $img['error'];
        } else {
            $productChoice = (int) ($_POST['product_choice'] ?? 0);
            $existing = $productChoice > 0 ? $P->find($productChoice) : null;
            if ($existing && (int) $existing['tenant_id'] !== (int) TenantContext::tenantId()) {
                $existing = null;
                $productChoice = 0;
            }
            $batchNotes = trim((string) ($_POST['notes'] ?? ''));
            $lineNotes = trim((string) ($_POST['remark'] ?? '')) ?: $batchNotes;
            if ($existing) {
                $items = [[
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
                    'retail_price' => ($existing['retail_price'] ?? $existing['selling_price'] ?? 0),
                    'wholesale_price' => $pkg['wholesale_price'] !== '' ? $pkg['wholesale_price'] : ($existing['wholesale_price'] ?? 0),
                    'offer_price' => '',
                    'offer_starts_at' => '',
                    'offer_ends_at' => '',
                    'image_path' => '',
                    'notes' => $lineNotes,
                ]];
            } else {
                $items = [[
                    'product_id' => 0,
                    'name' => $name,
                    'category_id' => (int) $C->findOrCreate($_POST['category'] ?? '', 'product'),
                    'brand_id' => (int) $BA->findOrCreate('brand', $_POST['brand'] ?? ''),
                    'supplier_id' => $supplierId,
                    'barcode' => trim($_POST['barcode'] ?? ''),
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
                    'retail_price' => $_POST['selling_price'] ?? 0,
                    'wholesale_price' => $pkg['wholesale_price'],
                    'offer_price' => $_POST['offer_price'] ?? '',
                    'offer_starts_at' => $_POST['offer_starts_at'] ?? '',
                    'offer_ends_at' => $_POST['offer_ends_at'] ?? '',
                    'image_path' => $img['path'] ?? '',
                    'notes' => $lineNotes,
                ]];
            }
            $res = $SP->createMany($items, TenantContext::userId());
            if ($res['ok']) {
                $_SESSION['flash']['success'] = 'Product saved to Store (warehouse). Generate an internal transfer invoice when you want it in shop Inventory.';
                header('Location: ' . public_url('super/store/'));
                exit;
            }
            $error = $res['error'] ?? 'Could not record this product to Store.';
        }
    }
}

$page_title = 'Record product to Store';
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <p class="text-muted small mb-0">Records into the <strong>Store warehouse</strong> (not shop Inventory). Transfer later from <a href="<?php echo public_url('super/store/'); ?>">Store</a>. Need many lines? <a href="<?php echo public_url('super/stock/new.php'); ?>">Record products in bulk</a>.</p>
</div>

<form method="post" enctype="multipart/form-data" class="card border-0 shadow-sm" style="border-radius:12px;">
  <div class="card-body p-4">
    <div class="row g-3">
      <div class="col-12 col-md-6">
        <label class="form-label fw-semibold">Product name</label>
        <div class="ta-wrap">
          <input type="text" name="name" id="prodName" class="form-control ta-input" data-field="title" required placeholder="e.g. Yellow beans, Cooking oil 5L" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" autocomplete="off">
          <div class="ta-menu"></div>
        </div>
        <input type="hidden" name="product_choice" id="productChoice" value="">
        <div id="matchNote" class="small text-primary mt-1" style="display:none;"></div>
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label fw-semibold"><i class="fas fa-barcode me-1"></i>Barcode</label>
        <input type="text" name="barcode" id="barcodeField" class="form-control" placeholder="Scan or type" value="<?php echo htmlspecialchars($_POST['barcode'] ?? ''); ?>" autocomplete="off">
        <div id="barcodeNote" class="small mt-1" style="display:none;"></div>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Category</label>
        <div class="ta-wrap">
          <input type="text" name="category" class="form-control ta-input" data-field="category" placeholder="e.g. Cereals" value="<?php echo htmlspecialchars($_POST['category'] ?? ''); ?>" autocomplete="off">
          <div class="ta-menu"></div>
        </div>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Brand</label>
        <div class="ta-wrap">
          <input type="text" name="brand" class="form-control ta-input" data-field="brand" placeholder="optional" value="<?php echo htmlspecialchars($_POST['brand'] ?? ''); ?>" autocomplete="off">
          <div class="ta-menu"></div>
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold">Received as <span class="text-danger">*</span></label>
        <select name="unit" id="unitSelect" class="form-select" required>
          <?php
            $packageUnits = array_values(array_filter($units, fn($u) => $u !== 'piece'));
            $selectedUnit = $_POST['unit'] ?? 'carton';
            if ($selectedUnit === 'piece') { $selectedUnit = 'carton'; }
            foreach ($packageUnits as $u):
          ?>
            <option value="<?php echo htmlspecialchars($u); ?>" <?php echo $selectedUnit === $u ? 'selected' : ''; ?>><?php echo htmlspecialchars($u); ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">Carton, bale, pack, dozen, box…</div>
      </div>
      <div class="col-12" id="packageFields">
        <div class="border rounded p-3" style="border-color:#e2e8f0!important;">
          <div class="small fw-semibold mb-2 text-danger">Package details (all required)</div>
          <div class="row g-2">
            <div class="col-md-3">
              <label class="form-label small mb-1" id="packageQtyLabel">Number of packages <span class="text-danger">*</span></label>
              <input type="number" step="0.01" min="0.01" name="package_quantity" id="packageQty" class="form-control form-control-sm" required value="<?php echo htmlspecialchars($_POST['package_quantity'] ?? ''); ?>" placeholder="e.g. 20">
            </div>
            <div class="col-md-3">
              <label class="form-label small mb-1" id="unitsPerPackageLabel">Items inside each package <span class="text-danger">*</span></label>
              <input type="number" step="0.01" min="0.01" name="units_per_package" id="unitsPerPackage" class="form-control form-control-sm" required value="<?php echo htmlspecialchars($_POST['units_per_package'] ?? ''); ?>" placeholder="e.g. 12">
            </div>
            <div class="col-md-3">
              <label class="form-label small mb-1">Inside item unit</label>
              <select name="inner_unit" class="form-select form-select-sm">
                <?php foreach ($units as $u): ?>
                  <option value="<?php echo htmlspecialchars($u); ?>" <?php echo (($_POST['inner_unit'] ?? 'piece') === $u) ? 'selected' : ''; ?>><?php echo htmlspecialchars($u); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small mb-1">Total sellable items</label>
              <div class="form-control form-control-sm bg-light" id="totalItems">0</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold" id="qtyLabel">Total sellable items</label>
        <input type="number" step="0.01" min="0" name="quantity" id="quantityInput" class="form-control" readonly value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold">Faulty / broken packages</label>
        <input type="number" step="0.01" min="0" name="faulty_quantity" class="form-control" value="<?php echo htmlspecialchars($_POST['faulty_quantity'] ?? '0'); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold">Supplier</label>
        <div class="ta-wrap">
          <input type="text" name="supplier" class="form-control ta-input" data-field="supplier" placeholder="optional" value="<?php echo htmlspecialchars($_POST['supplier'] ?? ''); ?>" autocomplete="off">
          <div class="ta-menu"></div>
        </div>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold" id="buyingLabel">Buying price per package (KES) <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0.01" name="buying_price" id="buyingPrice" class="form-control" required value="<?php echo htmlspecialchars($_POST['buying_price'] ?? ''); ?>">
        <div class="form-text" id="buyingHint">Cost of one carton/bale/package.</div>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold" id="wholesaleLabel">Wholesale price per package <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0.01" name="wholesale_price" id="wholesalePrice" class="form-control" required value="<?php echo htmlspecialchars($_POST['wholesale_price'] ?? ''); ?>">
        <div class="form-text" id="wholesaleHint">Selling price when selling a whole package (wholesale).</div>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold" id="retailLabel">Retail price (single item) <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0.01" name="selling_price" id="retailPrice" class="form-control" required value="<?php echo htmlspecialchars($_POST['selling_price'] ?? ''); ?>">
        <div class="form-text">Price when selling one item from inside the package.</div>
      </div>
      <div class="col-12">
        <div id="profitSummary" class="alert alert-light border small mb-0" style="display:none;"></div>
      </div>
      <div class="col-12">
        <div class="border rounded p-3" style="border-color:#e2e8f0!important;">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="offerToggle" <?php echo !empty($_POST['offer_price']) ? 'checked' : ''; ?>>
            <label class="form-check-label fw-semibold" for="offerToggle"><i class="fas fa-tag me-1 text-warning"></i>Put this product on offer</label>
          </div>
          <div class="row g-2 mt-1" id="offerFields" style="<?php echo !empty($_POST['offer_price']) ? '' : 'display:none;'; ?>">
            <div class="col-12 col-md-4">
              <label class="form-label small mb-1">Offer price (KES)</label>
              <input name="offer_price" type="number" step="0.01" min="0" class="form-control form-control-sm" value="<?php echo htmlspecialchars($_POST['offer_price'] ?? ''); ?>" placeholder="0">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small mb-1">Starts <span class="text-muted">(optional)</span></label>
              <input name="offer_starts_at" type="datetime-local" class="form-control form-control-sm" value="<?php echo htmlspecialchars($_POST['offer_starts_at'] ?? ''); ?>">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small mb-1">Ends</label>
              <input name="offer_ends_at" type="datetime-local" class="form-control form-control-sm" value="<?php echo htmlspecialchars($_POST['offer_ends_at'] ?? ''); ?>">
            </div>
          </div>
          <small class="text-muted d-block mt-1">After the end date, the till automatically returns to the normal selling price.</small>
        </div>
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
      <button class="btn btn-primary"><i class="fas fa-box-open me-1"></i>Save to Store warehouse</button>
    </div>
  </div>
</form>
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
  var nameInput = document.getElementById('prodName');
  var choice = document.getElementById('productChoice');
  var note = document.getElementById('matchNote');
  var barcode = document.getElementById('barcodeField');
  var barcodeNote = document.getElementById('barcodeNote');
  var lastMatchedName = null;
  var offerToggle = document.getElementById('offerToggle');
  var offerFields = document.getElementById('offerFields');
  var unitSelect = document.getElementById('unitSelect');
  var packageFields = document.getElementById('packageFields');
  var quantityInput = document.getElementById('quantityInput');
  var packageQty = document.getElementById('packageQty');
  var unitsPerPackage = document.getElementById('unitsPerPackage');
  function money(n) { return 'KES ' + (Math.round(n * 100) / 100).toLocaleString(); }
  function recalcPackage() {
    var unit = unitSelect ? unitSelect.value : 'carton';
    var pkgQty = parseFloat(packageQty ? packageQty.value : 0) || 0;
    var inside = parseFloat(unitsPerPackage ? unitsPerPackage.value : 0) || 0;
    var total = pkgQty > 0 && inside > 0 ? Math.round(pkgQty * inside * 100) / 100 : 0;
    var buy = parseFloat(document.getElementById('buyingPrice').value) || 0;
    var retail = parseFloat(document.getElementById('retailPrice').value) || 0;
    var wholesale = parseFloat(document.getElementById('wholesalePrice').value) || 0;
    if (packageFields) packageFields.style.display = 'block';
    var packageQtyLabel = document.getElementById('packageQtyLabel');
    var unitsPerPackageLabel = document.getElementById('unitsPerPackageLabel');
    if (packageQtyLabel) packageQtyLabel.innerHTML = 'Number of ' + unit + 's <span class="text-danger">*</span>';
    if (unitsPerPackageLabel) unitsPerPackageLabel.innerHTML = 'Items inside each ' + unit + ' <span class="text-danger">*</span>';
    if (document.getElementById('totalItems')) document.getElementById('totalItems').textContent = total;
    quantityInput.value = total;
    quantityInput.readOnly = true;
    document.getElementById('qtyLabel').textContent = 'Total sellable items';
    document.getElementById('buyingLabel').innerHTML = 'Buying price per ' + unit + ' (KES) <span class="text-danger">*</span>';
    document.getElementById('buyingHint').textContent = 'Cost of one ' + unit + '. Total cost = this × number of ' + unit + 's.';
    document.getElementById('retailLabel').innerHTML = 'Retail price (single item inside) <span class="text-danger">*</span>';
    document.getElementById('wholesaleLabel').innerHTML = 'Wholesale price per ' + unit + ' <span class="text-danger">*</span>';
    document.getElementById('wholesaleHint').textContent = 'Selling price when the customer buys a whole ' + unit + '.';
    var summary = document.getElementById('profitSummary');
    if (!summary) return;
    if (total <= 0 || buy <= 0 || wholesale <= 0 || retail <= 0) {
      summary.style.display = 'none';
      return;
    }
    var cost = pkgQty * buy;
    var wholesaleReturn = pkgQty * wholesale;
    var retailReturn = total * retail;
    var wholesaleProfit = wholesaleReturn - cost;
    var retailProfit = retailReturn - cost;
    var wholesaleMargin = wholesaleReturn > 0 ? (wholesaleProfit / wholesaleReturn * 100) : 0;
    var retailMargin = retailReturn > 0 ? (retailProfit / retailReturn * 100) : 0;
    summary.style.display = 'block';
    summary.innerHTML = '<strong>Expected profit for this delivery</strong>'
      + '<br>Cost: <strong>' + money(cost) + '</strong>'
      + ' <span class="text-muted">(' + pkgQty + ' × ' + money(buy) + ')</span>'
      + '<br>Wholesale profit (sell by ' + unit + '): <strong class="' + (wholesaleProfit < 0 ? 'text-danger' : 'text-success') + '">' + money(wholesaleProfit) + '</strong>'
      + ' · ' + wholesaleMargin.toFixed(1) + '%'
      + '<br>Retail profit (sell single items): <strong class="' + (retailProfit < 0 ? 'text-danger' : 'text-success') + '">' + money(retailProfit) + '</strong>'
      + ' · ' + retailMargin.toFixed(1) + '%';
  }
  [unitSelect, packageQty, unitsPerPackage, quantityInput,
    document.getElementById('buyingPrice'),
    document.getElementById('retailPrice'),
    document.getElementById('wholesalePrice')
  ].forEach(function (el) {
    if (el) {
      el.addEventListener('input', recalcPackage);
      el.addEventListener('change', recalcPackage);
    }
  });
  recalcPackage();
  if (offerToggle) {
    offerToggle.addEventListener('change', function () {
      offerFields.style.display = offerToggle.checked ? 'flex' : 'none';
      var price = offerFields.querySelector('[name="offer_price"]');
      var ends = offerFields.querySelector('[name="offer_ends_at"]');
      if (offerToggle.checked && !ends.value) {
        var d = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000);
        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        ends.value = d.toISOString().slice(0, 16);
      }
      if (!offerToggle.checked && price) { price.value = ''; }
    });
  }

  function attachTypeahead(input, field) {
    var wrap = input.closest('.ta-wrap');
    var menu = wrap && wrap.querySelector('.ta-menu');
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
        fetch(API + 'suggest.php?field=' + encodeURIComponent(field) + '&q=' + encodeURIComponent(q))
          .then(function (r) { return r.json(); })
          .then(function (data) { render(data.items || []); })
          .catch(function () {});
      }, 180);
    });
    input.addEventListener('blur', function () { setTimeout(function () { menu.classList.remove('show'); }, 150); });
  }

  function setRestock(item) {
    choice.value = item.id;
    lastMatchedName = item.name;
    nameInput.value = item.name;
    if (item.barcode) barcode.value = item.barcode;
    if (item.buying_price) document.getElementById('buyingPrice').value = item.buying_price;
    var bits = [item.category_name || item.subject_name, item.brand_name || item.publisher_name, item.unit].filter(Boolean);
    note.style.display = 'block';
    note.innerHTML = '<i class="fas fa-circle-check me-1"></i>Already in stock' +
      (bits.length ? ' — ' + bits.join(' · ') : '') +
      '. Current balance: <strong>' + item.balance + '</strong>. This adds to it.';
  }

  function clearRestock() {
    choice.value = '';
    lastMatchedName = null;
    note.style.display = 'none';
    note.innerHTML = '';
  }

  // Product name: suggest existing products and switch to restock when picked.
  (function wireTitle() {
    var wrap = nameInput.closest('.ta-wrap');
    var menu = wrap.querySelector('.ta-menu');
    var timer = null;
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
          menu.classList.remove('show');
          setRestock(item);
        });
        menu.appendChild(b);
      });
      menu.classList.add('show');
    }
    nameInput.addEventListener('input', function () {
      if (lastMatchedName !== null && nameInput.value !== lastMatchedName) clearRestock();
      clearTimeout(timer);
      var q = nameInput.value.trim();
      if (!q) { menu.classList.remove('show'); return; }
      timer = setTimeout(function () {
        fetch(API + 'find_titles.php?type=product&q=' + encodeURIComponent(q))
          .then(function (r) { return r.json(); })
          .then(function (data) { render(data.items || []); })
          .catch(function () {});
      }, 180);
    });
    nameInput.addEventListener('blur', function () { setTimeout(function () { menu.classList.remove('show'); }, 150); });
  })();

  function lookupBarcode() {
    var code = barcode.value.trim();
    if (!code) return;
    fetch(API + 'find_barcode.php?code=' + encodeURIComponent(code))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.item) {
          setRestock(data.item);
          barcodeNote.style.display = 'none';
        } else {
          barcodeNote.style.display = 'block';
          barcodeNote.className = 'small mt-1 text-muted';
          barcodeNote.innerHTML = '<i class="fas fa-circle-plus me-1"></i>New barcode — will be saved with this product.';
        }
      });
  }
  barcode.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); lookupBarcode(); }
  });
  barcode.addEventListener('blur', lookupBarcode);

  document.querySelectorAll('.ta-input[data-field="category"], .ta-input[data-field="brand"], .ta-input[data-field="supplier"]').forEach(function (input) {
    attachTypeahead(input, input.dataset.field);
  });
})();
</script>
<?php
$content = ob_get_clean();
$__layout = TenantContext::role() === 'staff' ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
