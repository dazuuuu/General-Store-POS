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
$SER = new Models\ProductSerialModel($pdo);

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
    $packageRetail = max(0, (float) ($row['retail_pack_price'] ?? 0));
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
            'retail_pack_price' => $packageRetail > 0 ? $packageRetail : null,
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
        'retail_pack_price' => $packageRetail > 0 ? $packageRetail : null,
    ];
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $destination = in_array($_POST['destination'] ?? '', ['store', 'shop'], true) ? $_POST['destination'] : 'store';
    $supplierName = trim($_POST['supplier'] ?? '');
    $supplierId = $supplierName !== '' ? (int) $SUP->findOrCreate($supplierName) : 0;
    $rows = $_POST['items'] ?? [];

    $items = [];
    foreach ($rows as $i => $row) {
        $title = trim($row['title'] ?? '');
        $productChoice = trim($row['product_choice'] ?? '');
        $packageQty = max(0, (float) ($row['package_quantity'] ?? 0));
        $inside = max(0, (float) ($row['units_per_package'] ?? 0));
        $directQty = max(0, (float) ($row['quantity'] ?? 0));
        $packageCost = max(0, (float) ($row['buying_price'] ?? 0));
        $packageWholesale = max(0, (float) ($row['wholesale_price'] ?? 0));
        $packageRetail = max(0, (float) ($row['retail_pack_price'] ?? 0));
        $itemRetail = max(0, (float) ($row['selling_price'] ?? 0));
        $barcode = trim((string) ($row['barcode'] ?? ''));
        $serialText=trim((string)($row['serials']??''));
        $serialRequested=!empty($row['serial_tracking'])||$serialText!=='';
        $serialList=$serialRequested?Models\ProductSerialModel::parse($serialText):[];

        $hasContent = $title !== '' || $productChoice !== ''
            || $packageQty > 0 || $directQty > 0 || $packageCost > 0
            || $packageWholesale > 0 || $packageRetail > 0 || $itemRetail > 0
            || $barcode !== '' || $serialRequested;
        if (!$hasContent) {
            continue;
        }

        if ($productChoice === '' && $title === '') {
            $p = $itemRetail > 0 ? $itemRetail : ($packageRetail > 0 ? $packageRetail : ($packageCost > 0 ? $packageCost : 0));
            $title = $p > 0 ? ('Product KES ' . number_format($p, 0)) : ('Item ' . date('j M H:i'));
        }

        $effectiveInside = $inside > 0 ? $inside : 1.0;
        $qty = $serialRequested ? count($serialList) : ($directQty > 0 ? $directQty : ($packageQty > 0 ? round($packageQty * $effectiveInside, 2) : 0.0));
        $faulty = max(0, (float) ($row['faulty_quantity'] ?? 0));
        $unitBuying = ($packageCost > 0 && $effectiveInside > 0) ? round($packageCost / $effectiveInside, 2) : 0.0;
        $unitWholesale = ($packageWholesale > 0 && $effectiveInside > 0) ? round($packageWholesale / $effectiveInside, 2) : 0.0;
        $receiveUnit = trim((string) ($row['unit'] ?? '')) ?: 'carton';
        $innerUnit = in_array($row['inner_unit'] ?? '', $units, true) ? $row['inner_unit'] : 'piece';

        $remark = trim($row['remark'] ?? '');
        $batchNotes = trim((string) ($_POST['notes'] ?? ''));
        $lineNotes = $remark !== '' ? $remark : $batchNotes;

        if ($productChoice !== '') {
            $existing = $P->find((int) $productChoice);
            if ($existing && (int) $existing['tenant_id'] === (int) TenantContext::tenantId()) {
                $items[] = [
                    'product_id' => (int) $existing['id'],
                    'name' => $existing['name'],
                    'category_id' => (int) ($existing['category_id'] ?? 0),
                    'brand_id' => (int) ($existing['brand_id'] ?? 0),
                    'supplier_id' => $supplierId,
                    'barcode' => $existing['barcode'] ?? '',
                    'unit' => $innerUnit,
                    'package_unit' => $receiveUnit,
                    'package_quantity' => $packageQty > 0 ? $packageQty : null,
                    'units_per_package' => $effectiveInside,
                    'package_price' => $packageWholesale > 0 ? $packageWholesale : null,
                    'retail_pack_price' => $packageRetail > 0 ? $packageRetail : ($existing['retail_pack_price'] ?? null),
                    'package_buying_price' => $packageCost > 0 ? $packageCost : null,
                    'colors' => '',
                    'quantity' => $qty,
                    'faulty_quantity' => $faulty,
                    'buying_price' => $unitBuying > 0 ? $unitBuying : (float)($existing['buying_price'] ?? 0),
                    'retail_price' => $itemRetail > 0 ? $itemRetail : (float)($existing['retail_price'] ?? $existing['selling_price'] ?? 0),
                    'wholesale_price' => $unitWholesale > 0 ? $unitWholesale : (float)($existing['wholesale_price'] ?? 0),
                    'offer_price' => '',
                    'offer_starts_at' => '',
                    'offer_ends_at' => '',
                    'image_path' => '',
                    'notes' => $lineNotes,
                    'serials' => $serialText,
                    'serialized' => $serialRequested||!empty($existing['serial_tracking']),
                ];
                continue;
            }
        }

        $img = stock_handle_image(stock_row_image_file((int) $i));
        $imgPath = $img['ok'] ? ($img['path'] ?? '') : '';

        $items[] = [
            'product_id' => 0,
            'name' => $title,
            'category_id' => !empty($row['category']) ? (int) $C->findOrCreate($row['category'], 'product') : 0,
            'brand_id' => !empty($row['brand']) ? (int) $BA->findOrCreate('brand', $row['brand']) : 0,
            'supplier_id' => $supplierId,
            'barcode' => $barcode,
            'unit' => $innerUnit,
            'package_unit' => $receiveUnit,
            'package_quantity' => $packageQty > 0 ? $packageQty : null,
            'units_per_package' => $effectiveInside,
            'package_price' => $packageWholesale > 0 ? $packageWholesale : null,
            'retail_pack_price' => $packageRetail > 0 ? $packageRetail : null,
            'package_buying_price' => $packageCost > 0 ? $packageCost : null,
            'colors' => '',
            'quantity' => $qty,
            'faulty_quantity' => $faulty,
            'buying_price' => $unitBuying,
            'retail_price' => $itemRetail,
            'wholesale_price' => $unitWholesale,
            'offer_price' => $row['offer_price'] ?? '',
            'offer_starts_at' => $row['offer_starts_at'] ?? '',
            'offer_ends_at' => $row['offer_ends_at'] ?? '',
            'image_path' => $imgPath,
            'notes' => $lineNotes,
            'serials' => $serialText,
            'serialized' => $serialRequested,
        ];
    }

    if (!$items) {
        $error = 'Please fill in at least one product name or price to record.';
    } else {
        foreach($items as &$serialItem){if(!empty($serialItem['serialized'])){$serialItem['quantity']=count(Models\ProductSerialModel::parse((string)$serialItem['serials']));$serialItem['unit']='piece';if($serialItem['quantity']<1){$error='Enter one serial number or IMEI per serialized product unit.';break;}}}unset($serialItem);
        if(!$error&&$destination==='store'&&array_filter($items,fn($it)=>!empty($it['serialized'])))$error='Serialized products must be recorded directly to Shop Inventory so serials match sellable units.';
        if (!$error&&$destination === 'shop') {
            $pdo->beginTransaction();
            try{
            $savedCount = 0;
            foreach ($items as $it) {
                $targetProductId=0;
                if (!empty($it['product_id'])) {
                    $curr = $P->find((int) $it['product_id']);
                    if ($curr) {
                        if(!empty($curr['is_menu_item'])&&!empty($it['serialized']))throw new RuntimeException('Serial numbers are only for inventory products, not restaurant menu items.');
                        $newQty = (float) $curr['quantity'] + (!empty($it['serialized'])?0:(float)$it['quantity']);
                        $editRes=$P->edit((int) $curr['id'], array_merge($curr, [
                            'quantity' => $newQty,
                            'buying_price' => $it['buying_price'] > 0 ? $it['buying_price'] : ($curr['buying_price'] ?? 0),
                            'package_buying_price' => $it['package_buying_price'] ?: ($curr['package_buying_price'] ?? null),
                            'retail_price' => $it['retail_price'] > 0 ? $it['retail_price'] : ($curr['retail_price'] ?? 0),
                            'wholesale_price' => $it['wholesale_price'] > 0 ? $it['wholesale_price'] : ($curr['wholesale_price'] ?? 0),
                        ]));
                        if(!$editRes['ok'])throw new RuntimeException($editRes['errors']['_']??'Could not update product.');
                        $targetProductId=(int)$curr['id'];
                        $savedCount++;
                    }
                } else {
                    $pRes = $P->create([
                        'name' => $it['name'],
                        'category_id' => $it['category_id'] ?: null,
                        'brand_id' => $it['brand_id'] ?: null,
                        'supplier_id' => $it['supplier_id'] ?: null,
                        'barcode' => $it['barcode'] ?: null,
                        'unit' => $it['unit'] ?: 'piece',
                        'pack_unit' => $it['package_unit'] ?: 'carton',
                        'pack_price' => $it['package_price'] ?: null,
                        'retail_pack_price' => $it['retail_pack_price'] ?: null,
                        'package_buying_price' => $it['package_buying_price'] ?: null,
                        'units_per_pack' => $it['units_per_package'] ?: 1,
                        'quantity' => !empty($it['serialized'])?0:($it['quantity'] ?: 0),
                        'faulty_quantity' => $it['faulty_quantity'] ?: 0,
                        'buying_price' => $it['buying_price'] ?: 0,
                        'wholesale_price' => $it['wholesale_price'] ?: 0,
                        'retail_price' => $it['retail_price'] ?: 0,
                        'offer_price' => $it['offer_price'] ?: null,
                        'offer_starts_at' => $it['offer_starts_at'] ?: null,
                        'offer_ends_at' => $it['offer_ends_at'] ?: null,
                        'image_path' => $it['image_path'] ?: null,
                        'description' => $it['notes'] ?: null,
                    ]);
                    if ($pRes['ok']) {
                        $targetProductId=(int)$pRes['id'];
                        $savedCount++;
                    }else throw new RuntimeException($pRes['errors']['_']??'Could not create product.');
                }
                if(!empty($it['serialized'])){$serialRes=$SER->add($targetProductId,(string)$it['serials'],true);if(!$serialRes['ok'])throw new RuntimeException($serialRes['error']);}
            }
            $pdo->commit();
            $_SESSION['flash']['success'] = $savedCount . ' product' . ($savedCount === 1 ? '' : 's') . ' saved directly to Shop (Inventory) and ready to sell.';
            header('Location: ' . public_url('super/inventory/'));
            exit;
            }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}
        } elseif(!$error) {
            $res = $SP->createMany($items, TenantContext::userId());
            if ($res['ok']) {
                $_SESSION['flash']['success'] = $res['created'] . ' product' . ($res['created'] === 1 ? '' : 's') . ' saved to Store (warehouse). Generate an internal transfer invoice anytime to move them into shop Inventory.';
                header('Location: ' . public_url('super/store/'));
                exit;
            }
            $error = $res['error'] ?? 'Could not record this delivery to Store.';
        }
    }
}

$page_title = 'Record Stock';
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" id="stockForm" novalidate>
  <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
      <h2 class="h5 mb-2">Record Destination</h2>
      <p class="text-muted small mb-3">Choose where these products will be saved:</p>
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="d-flex align-items-start p-3 border rounded cursor-pointer destination-card h-100" style="cursor:pointer;border-radius:10px;">
            <input type="radio" name="destination" value="store" class="form-check-input me-3 mt-1 dest-radio" checked id="destStore">
            <div>
              <div class="fw-bold text-dark"><i class="fas fa-box-archive text-primary me-2"></i>Store (Warehouse)</div>
              <div class="small text-muted mt-1">Products land in the Store warehouse awaiting transfer to shop via invoice.</div>
            </div>
          </label>
        </div>
        <div class="col-12 col-md-6">
          <label class="d-flex align-items-start p-3 border rounded cursor-pointer destination-card h-100" style="cursor:pointer;border-radius:10px;">
            <input type="radio" name="destination" value="shop" class="form-check-input me-3 mt-1 dest-radio" id="destShop">
            <div>
              <div class="fw-bold text-dark"><i class="fas fa-store text-success me-2"></i>Shop (Active Inventory)</div>
              <div class="small text-muted mt-1">Products appear directly in shop Inventory, available immediately for cashier counter sales.</div>
            </div>
          </label>
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
      <h2 class="h5 mb-3">Batch &amp; Supplier info <span class="text-muted fw-normal small">(optional)</span></h2>
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
        <h2 class="h5 mb-0" id="productsHeading">Products to record</h2>
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

  <button class="btn btn-primary btn-lg" id="submitBtn"><i class="fas fa-boxes-stacked me-1"></i><span id="submitBtnText">Save products to Store warehouse</span></button>
</form>

<template id="rowTpl">
  <div class="stock-row border rounded p-3 mb-3" style="border-color:#e2e8f0!important;">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <span class="fw-semibold small text-muted">Product __N__</span>
      <button type="button" class="btn btn-sm btn-link text-danger p-0 removeRow">Remove</button>
    </div>
    <div class="row g-2">
      <div class="col-12 col-sm-6">
        <label class="form-label small mb-1">Product name <span class="text-muted">(optional)</span></label>
        <div class="ta-wrap">
          <input type="text" name="items[__I__][title]" class="form-control form-control-sm ta-input productTitle" data-field="title" placeholder="e.g. Yellow beans, Soft drink 500ml" autocomplete="off">
          <div class="ta-menu"></div>
        </div>
        <input type="hidden" name="items[__I__][product_choice]" class="productChoice" value="">
        <div class="matchNote small mt-1" style="display:none;"></div>
      </div>
      <div class="col-12 col-sm-6">
        <label class="form-label small mb-1"><i class="fas fa-barcode me-1"></i>Barcode <span class="text-muted">(optional)</span></label>
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
        <label class="form-label small mb-1">Category <span class="text-muted">(optional)</span></label>
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
        <label class="form-label small mb-1">Packaging unit <span class="text-muted">(optional)</span></label>
        <select name="items[__I__][unit]" class="form-select form-select-sm unitSelect">
          <?php foreach (array_values(array_filter($units, fn($u) => $u !== 'piece')) as $u): ?>
            <option value="<?php echo htmlspecialchars($u); ?>" <?php echo $u === 'carton' ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($u)); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 mt-2 packageFields">
        <div class="border rounded p-2" style="border-color:#e2e8f0!important;background:#fafbfc;">
          <div class="small fw-semibold mb-2 text-secondary"><i class="fas fa-boxes-stacked me-1"></i>Package &amp; Unit details <span class="fw-normal text-muted">(optional)</span></div>
          <div class="row g-2">
            <div class="col-6 col-sm-3">
              <label class="form-label small mb-1 packageQtyLabel">Number of cartons <span class="text-muted">(optional)</span></label>
              <input type="number" step="0.01" min="0" name="items[__I__][package_quantity]" class="form-control form-control-sm packageQty" placeholder="e.g. 20">
            </div>
            <div class="col-6 col-sm-3">
              <label class="form-label small mb-1 unitsPerPackageLabel">Items inside each carton <span class="text-muted">(optional)</span></label>
              <input type="number" step="0.01" min="0" name="items[__I__][units_per_package]" class="form-control form-control-sm unitsPerPackage" placeholder="e.g. 12">
            </div>
            <div class="col-6 col-sm-3">
              <label class="form-label small mb-1">Inside item unit <span class="text-muted">(optional)</span></label>
              <select name="items[__I__][inner_unit]" class="form-select form-select-sm">
                <?php foreach ($units as $u): ?>
                  <option value="<?php echo htmlspecialchars($u); ?>"><?php echo htmlspecialchars(ucfirst($u)); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6 col-sm-3">
              <label class="form-label small mb-1">Calculated pack items</label>
              <div class="form-control form-control-sm bg-light totalItems">0</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-6 col-sm-3 mt-2">
        <label class="form-label small mb-1 qtyLabel">Total sellable items <span class="text-muted">(optional)</span></label>
        <input type="number" step="0.01" min="0" name="items[__I__][quantity]" class="form-control form-control-sm qty" placeholder="Auto or enter directly">
      </div>
      <div class="col-6 col-sm-3 mt-2">
        <label class="form-label small mb-1">Faulty / broken items <span class="text-muted">(optional)</span></label>
        <input type="number" step="0.01" min="0" name="items[__I__][faulty_quantity]" class="form-control form-control-sm" placeholder="0">
      </div>
      <?php if(TenantFeatures::enabled('serials')):?>
      <div class="col-12 mt-2 serialStockBox">
        <div class="border rounded p-2 bg-light">
          <label class="form-check mb-0"><input class="form-check-input serialTracking" type="checkbox" name="items[__I__][serial_tracking]" value="1"> <span class="fw-semibold small">Track serial numbers / IMEI for this inventory product</span></label>
          <div class="serialFields mt-2" style="display:none"><textarea name="items[__I__][serials]" class="form-control form-control-sm font-monospace serialNumbers" rows="4" placeholder="One serial per unit&#10;IMEI-001&#10;IMEI-002"></textarea><div class="form-text"><span class="serialCount">0</span> units. Quantity is calculated from serials. Direct-to-Shop only.</div></div>
        </div>
      </div>
      <?php endif;?>
      <div class="col-6 col-sm-3 mt-2">
        <label class="form-label small mb-1 buyingLabel">Buying price per package <span class="text-muted">(optional)</span></label>
        <input type="number" step="0.01" min="0" name="items[__I__][buying_price]" class="form-control form-control-sm buyingPrice" placeholder="0">
      </div>
      <div class="col-6 col-sm-3 mt-2 newProductFields">
        <label class="form-label small mb-1 wholesaleLabel">Wholesale price per package <span class="text-muted">(optional)</span></label>
        <input type="number" step="0.01" min="0" name="items[__I__][wholesale_price]" class="form-control form-control-sm wholesalePrice" placeholder="0">
      </div>
      <div class="col-6 col-sm-3 mt-2 newProductFields">
        <label class="form-label small mb-1 retailPackLabel">Retail price per package <span class="text-muted">(optional)</span></label>
        <input type="number" step="0.01" min="0" name="items[__I__][retail_pack_price]" class="form-control form-control-sm retailPackPrice" placeholder="0">
      </div>
      <div class="col-6 col-sm-3 mt-2 newProductFields">
        <label class="form-label small mb-1 retailLabel">Retail price per single item <span class="text-muted">(optional)</span></label>
        <input type="number" step="0.01" min="0" name="items[__I__][selling_price]" class="form-control form-control-sm retailPrice" placeholder="0">
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
      if (item.retail_pack_price) { row.querySelector('.retailPackPrice').value = item.retail_pack_price; }
      if(item.serial_tracking&&row.querySelector('.serialTracking')){document.getElementById('destShop').checked=true;updateDestUI();var serialToggle=row.querySelector('.serialTracking');serialToggle.checked=true;row.querySelector('.serialFields').style.display='block';row.querySelector('.qty').readOnly=true;}
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
    var serialToggle=row.querySelector('.serialTracking'),serialText=row.querySelector('.serialNumbers');
    function updateSerialRow(){
      if(!serialToggle||!serialText)return;
      var values=Array.from(new Set(serialText.value.split(/[\r\n,]+/).map(function(v){return v.trim();}).filter(Boolean)));
      row.querySelector('.serialCount').textContent=values.length;
      row.querySelector('.serialFields').style.display=serialToggle.checked?'block':'none';
      var qty=row.querySelector('.qty');qty.readOnly=serialToggle.checked;if(serialToggle.checked)qty.value=values.length;
      recalc();
    }
    if(serialToggle){serialToggle.addEventListener('change',updateSerialRow);serialText.addEventListener('input',updateSerialRow);}

    ['category', 'brand'].forEach(function (field) {
      var el = row.querySelector('[data-field="' + field + '"]');
      if (el) attachTypeahead(el, field);
    });

    row.querySelectorAll('.qty, .buyingPrice, .retailPrice, .wholesalePrice, .retailPackPrice, .unitSelect, .packageQty, .unitsPerPackage').forEach(function (el) {
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
      var unit = row.querySelector('.unitSelect').value || 'package';
      var pkgFields = row.querySelector('.packageFields');
      var qtyInput = row.querySelector('.qty');
      var pkgQty = parseFloat((row.querySelector('.packageQty') || {}).value) || 0;
      var perPkg = parseFloat((row.querySelector('.unitsPerPackage') || {}).value) || 0;
      var qty = 0;
      if (pkgQty > 0 && perPkg > 0) {
        qty = pkgQty * perPkg;
        qtyInput.value = Math.round(qty * 100) / 100;
      } else {
        qty = parseFloat(qtyInput.value) || 0;
      }
      var serialToggle=row.querySelector('.serialTracking'),serialText=row.querySelector('.serialNumbers');
      if(serialToggle&&serialToggle.checked){qty=Array.from(new Set(serialText.value.split(/[\r\n,]+/).map(function(v){return v.trim();}).filter(Boolean))).length;qtyInput.value=qty;qtyInput.readOnly=true;}
      var buy = parseFloat(row.querySelector('.buyingPrice').value) || 0;
      var retail = parseFloat(row.querySelector('.retailPrice').value) || 0;
      var wholesale = parseFloat(row.querySelector('.wholesalePrice').value) || 0;
      var retailPack = parseFloat((row.querySelector('.retailPackPrice') || {}).value) || 0;
      if (pkgFields) pkgFields.style.display = 'block';
      var packageQtyLabel = row.querySelector('.packageQtyLabel');
      var unitsPerPackageLabel = row.querySelector('.unitsPerPackageLabel');
      if (packageQtyLabel) packageQtyLabel.innerHTML = 'Number of ' + unit + 's <span class="text-muted">(optional)</span>';
      if (unitsPerPackageLabel) unitsPerPackageLabel.innerHTML = 'Items inside each ' + unit + ' <span class="text-muted">(optional)</span>';
      row.querySelector('.buyingLabel').innerHTML = 'Buying price per ' + unit + ' <span class="text-muted">(optional)</span>';
      row.querySelector('.wholesaleLabel').innerHTML = 'Wholesale price per ' + unit + ' <span class="text-muted">(optional)</span>';
      var retailPackLabel = row.querySelector('.retailPackLabel');
      if (retailPackLabel) retailPackLabel.innerHTML = 'Retail price per ' + unit + ' <span class="text-muted">(optional)</span>';
      row.querySelector('.retailLabel').innerHTML = 'Retail price per single item <span class="text-muted">(optional)</span>';
      var totalItems = row.querySelector('.totalItems');
      if (totalItems) totalItems.textContent = Math.round((pkgQty > 0 && perPkg > 0 ? qty : 0) * 100) / 100;
      var total = pkgQty > 0 ? (pkgQty * buy) : (qty * buy);
      var wholesaleReturn = pkgQty > 0 && wholesale > 0 ? (pkgQty * wholesale) : (qty * wholesale);
      var retailPackReturn = pkgQty > 0 && retailPack > 0 ? (pkgQty * retailPack) : 0;
      var retailReturn = retailPackReturn > 0 ? retailPackReturn : (qty * retail);
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

  function updateDestUI() {
    var isShop = document.getElementById('destShop').checked;
    var btnText = document.getElementById('submitBtnText');
    var heading = document.getElementById('productsHeading');
    if (isShop) {
      if (btnText) btnText.textContent = 'Save products to Shop Inventory (Sell immediately)';
      if (heading) heading.textContent = 'Products for Shop Inventory (Direct Selling)';
    } else {
      if (btnText) btnText.textContent = 'Save products to Store warehouse';
      if (heading) heading.textContent = 'Products for Store Warehouse (Awaiting Transfer)';
    }
    document.querySelectorAll('.stock-row').forEach(function(row){var toggle=row.querySelector('.serialTracking'),text=row.querySelector('.serialNumbers');if(!toggle)return;toggle.disabled=!isShop;text.disabled=!isShop;if(!isShop){toggle.checked=false;row.querySelector('.serialFields').style.display='none';row.querySelector('.qty').readOnly=false;}});
  }
  document.querySelectorAll('.dest-radio').forEach(function (r) {
    r.addEventListener('change', updateDestUI);
  });
  updateDestUI();
})();
</script>
<?php
$content = ob_get_clean();
$__layout = TenantContext::role() === 'staff' ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
