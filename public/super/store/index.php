<?php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::STOCK_ENTER);

$pdo = Database::pdo();
$SP = new Models\StoreProductModel($pdo);
$P = new Models\ProductModel($pdo);
$SUP = new Models\SupplierModel($pdo);
$C = new Models\CategoryModel($pdo);
$BA = new Models\BookAttributeModel($pdo);
$units = Models\ProductModel::UNITS;
$apiBase = public_url('api/inventory/');
$error = '';

function store_row_image_file(int $i): array
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

function store_handle_image(array $file): array
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
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $name = 'store_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return ['ok' => false, 'error' => 'Could not save the image. Check folder permissions.'];
    }
    return ['ok' => true, 'path' => public_url('assets/uploads/products/' . $name)];
}

function store_package_fields(array $row, array $units): array
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'store') {
        $supplierName = trim($_POST['supplier'] ?? '');
        $supplierId = $supplierName !== '' ? (int) $SUP->findOrCreate($supplierName) : 0;
        $batchNotes = trim((string) ($_POST['notes'] ?? ''));
        $items = [];
        foreach ($_POST['items'] ?? [] as $i => $row) {
            $nameProbe = trim((string) ($row['title'] ?? ''));
            $productChoiceProbe = (int) ($row['product_choice'] ?? 0);
            $packageQty = max(0, (float) ($row['package_quantity'] ?? 0));
            $inside = max(0, (float) ($row['units_per_package'] ?? 0));
            $directQty = max(0, (float) ($row['quantity'] ?? 0));
            $packageCost = max(0, (float) ($row['buying_price'] ?? 0));
            $packageWholesale = max(0, (float) ($row['wholesale_price'] ?? 0));
            $packageRetail = max(0, (float) ($row['retail_pack_price'] ?? 0));
            $itemRetail = max(0, (float) ($row['selling_price'] ?? 0));
            $barcode = trim((string) ($row['barcode'] ?? ''));

            $hasContent = $nameProbe !== '' || $productChoiceProbe > 0
                || $packageQty > 0 || $directQty > 0 || $packageCost > 0
                || $packageWholesale > 0 || $packageRetail > 0 || $itemRetail > 0
                || $barcode !== '';
            if (!$hasContent) {
                continue;
            }

            if ($nameProbe === '' && $productChoiceProbe <= 0) {
                $p = $itemRetail > 0 ? $itemRetail : ($packageRetail > 0 ? $packageRetail : ($packageCost > 0 ? $packageCost : 0));
                $nameProbe = $p > 0 ? ('Product KES ' . number_format($p, 0)) : ('Item ' . date('j M H:i'));
            }

            $effectiveInside = $inside > 0 ? $inside : 1.0;
            $qty = $directQty > 0 ? $directQty : ($packageQty > 0 ? round($packageQty * $effectiveInside, 2) : 0.0);
            $faulty = max(0, (float) ($row['faulty_quantity'] ?? 0));
            $unitBuying = ($packageCost > 0 && $effectiveInside > 0) ? round($packageCost / $effectiveInside, 2) : 0.0;
            $unitWholesale = ($packageWholesale > 0 && $effectiveInside > 0) ? round($packageWholesale / $effectiveInside, 2) : 0.0;
            $receiveUnit = trim((string) ($row['unit'] ?? '')) ?: 'carton';
            $innerUnit = in_array($row['inner_unit'] ?? '', $units, true) ? $row['inner_unit'] : 'piece';

            $productId = (int) ($row['product_choice'] ?? 0);
            $existing = $productId > 0 ? $P->find($productId) : null;
            if ($existing && (int) $existing['tenant_id'] !== (int) TenantContext::tenantId()) {
                $existing = null;
                $productId = 0;
            }
            $name = $nameProbe;
            if ($existing) {
                $name = $existing['name'];
            }

            $imgPath = '';
            if (!$existing) {
                $img = store_handle_image(store_row_image_file((int) $i));
                if (!$img['ok']) {
                    $error = $name . ': ' . $img['error'];
                    break;
                }
                $imgPath = $img['path'] ?? '';
            }

            $categoryId = $existing ? (int) ($existing['category_id'] ?? 0) : (int) $C->findOrCreate($row['category'] ?? '', 'product');
            $brandId = $existing ? (int) ($existing['brand_id'] ?? 0) : (int) $BA->findOrCreate('brand', $row['brand'] ?? '');
            $items[] = [
                'product_id' => $productId,
                'name' => $name,
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'supplier_id' => $supplierId,
                'barcode' => $existing ? ($existing['barcode'] ?? '') : ($row['barcode'] ?? ''),
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
                'offer_price' => $existing ? '' : ($row['offer_price'] ?? ''),
                'offer_starts_at' => $existing ? '' : ($row['offer_starts_at'] ?? ''),
                'offer_ends_at' => $existing ? '' : ($row['offer_ends_at'] ?? ''),
                'image_path' => $imgPath,
                'notes' => trim((string) ($row['remark'] ?? '')) ?: $batchNotes,
            ];
        }
        if (!$error && $items) {
            $res = $SP->createMany($items, TenantContext::userId());
            if ($res['ok']) {
                $_SESSION['flash']['success'] = $res['created'] . ' product' . ($res['created'] === 1 ? '' : 's') . ' saved in Store warehouse. Select them below and generate an internal transfer invoice to move stock into shop Inventory.';
                header('Location: ' . public_url('super/store/'));
                exit;
            }
            $error = $res['error'] ?? 'Could not store products.';
        } elseif (!$error && !$items) {
            $error = 'Please fill in at least one product name or price to record to Store.';
        }
    } elseif ($action === 'return_to_warehouse') {
        $returnItems = [];
        $rawItems = $_POST['return_items'] ?? [];
        foreach ($rawItems as $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            $qty = (float) ($row['quantity'] ?? 0);
            $pkgQty = isset($row['package_quantity']) && $row['package_quantity'] !== '' ? (float) $row['package_quantity'] : null;
            if ($pid > 0 && ($qty > 0 || ($pkgQty !== null && $pkgQty > 0))) {
                $returnItems[] = [
                    'product_id' => $pid,
                    'quantity' => $qty,
                    'package_quantity' => $pkgQty,
                ];
            }
        }
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $res = $SP->returnToWarehouse($returnItems, $notes, TenantContext::userId());
        if ($res['ok']) {
            $_SESSION['flash']['success'] = 'Warehouse Return Note ' . $res['invoice_number'] . ' generated. ' . count($returnItems) . ' product(s) returned to Store warehouse (Expected profit reduced by KES ' . number_format($res['profit_reduced'], 2) . ').';
            header('Location: ' . public_url('super/store/invoice.php?id=' . (int) $res['invoice_id']));
            exit;
        }
        $error = $res['error'] ?? 'Could not process return to warehouse.';
    } elseif ($action === 'invoice') {
        $ids = $_POST['store_ids'] ?? [];
        $ids = is_array($ids) ? $ids : [];
        $res = $SP->generateInvoice($ids, $_POST['invoice_to'] ?? '', $_POST['notes'] ?? '', TenantContext::userId(), $_POST['transfer_packages'] ?? [], $_POST['transfer_quantities'] ?? []);
        if ($res['ok']) {
            $_SESSION['flash']['success'] = 'Internal transfer invoice ' . $res['invoice_number'] . ' generated. Selected stock moved from Store into shop Inventory.';
            header('Location: ' . public_url('super/store/invoice.php?id=' . (int) $res['invoice_id']));
            exit;
        }
        $error = $res['error'] ?? 'Could not generate invoice.';
    } elseif ($action === 'edit_store_product') {
        $supplierName = trim($_POST['supplier'] ?? '');
        $supplierId = $supplierName !== '' ? (int) $SUP->findOrCreate($supplierName) : 0;
        $res = $SP->updatePending((int) ($_POST['id'] ?? 0), [
            'name' => $_POST['name'] ?? '',
            'category_id' => (int) $C->findOrCreate($_POST['category'] ?? '', 'product'),
            'brand_id' => (int) $BA->findOrCreate('brand', $_POST['brand'] ?? ''),
            'supplier_id' => $supplierId,
            'barcode' => $_POST['barcode'] ?? '',
            'unit' => in_array($_POST['unit'] ?? '', $units, true) ? $_POST['unit'] : 'piece',
            'colors' => '',
            'quantity' => $_POST['quantity'] ?? 0,
            'faulty_quantity' => $_POST['faulty_quantity'] ?? 0,
            'buying_price' => $_POST['buying_price'] ?? 0,
            'retail_price' => $_POST['retail_price'] ?? 0,
            'wholesale_price' => $_POST['wholesale_price'] ?? 0,
            'package_unit' => $_POST['package_unit'] ?? '',
            'package_quantity' => $_POST['package_quantity'] ?? '',
            'units_per_package' => $_POST['units_per_package'] ?? '',
            'package_price' => $_POST['package_price'] ?? '',
            'retail_pack_price' => $_POST['retail_pack_price'] ?? '',
            'notes' => $_POST['notes'] ?? '',
        ]);
        if ($res['ok']) {
            $_SESSION['flash']['success'] = 'Stored product updated.';
            header('Location: ' . public_url('super/store/'));
            exit;
        }
        $error = $res['error'] ?? 'Could not update stored product.';
    } elseif ($action === 'delete_store_product') {
        $res = $SP->deletePending((int) ($_POST['id'] ?? 0));
        if ($res['ok']) {
            $_SESSION['flash']['success'] = 'Stored product deleted.';
            header('Location: ' . public_url('super/store/'));
            exit;
        }
        $error = $res['error'] ?? 'Could not delete stored product.';
    } elseif ($action === 'delete_store_invoice') {
        $res = $SP->deleteInvoice((int) ($_POST['invoice_id'] ?? 0));
        if ($res['ok']) {
            $_SESSION['flash']['success'] = 'Invoice deleted and stock reversed.';
            header('Location: ' . public_url('super/store/'));
            exit;
        }
        $error = $res['error'] ?? 'Could not delete store invoice.';
    }
}

$pending = $SP->pending();
$invoices = $SP->invoices(50);
$shopProducts = $P->all([], 'name ASC');
$shopProductsData = array_values(array_map(function($p) {
    $upp = max(1, (float)($p['units_per_pack'] ?? 1));
    $pkgBuy = (float)($p['package_buying_price'] ?? 0);
    $buy = (float)($p['buying_price'] ?? 0);
    if ($buy <= 0 && $pkgBuy > 0 && $upp > 0) {
        $buy = round($pkgBuy / $upp, 2);
    }
    $retail = (float)($p['retail_price'] ?? $p['selling_price'] ?? 0);
    return [
        'id' => (int) $p['id'],
        'name' => (string) $p['name'],
        'barcode' => (string) ($p['barcode'] ?? ''),
        'qty' => (float) ($p['quantity'] ?? 0),
        'unit' => (string) ($p['unit'] ?? 'piece'),
        'pack_unit' => (string) ($p['pack_unit'] ?? 'package'),
        'units_per_package' => $upp,
        'buying_price' => $buy,
        'retail_price' => $retail,
        'unit_profit' => max(0, $retail - $buy),
    ];
}, $shopProducts));
$page_title = 'Store warehouse';
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <div>
    <h1 class="h5 fw-bold mb-1"><i class="fas fa-box-archive text-primary me-2"></i>Store · Main Warehouse</h1>
    <p class="text-muted small mb-0">Receive stock here first, generate <strong>transfer invoices</strong> to move into shop Inventory, or <strong>return stock</strong> back from the shop to the warehouse.</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-sm btn-outline-primary" href="<?php echo public_url('super/stationery/new.php'); ?>"><i class="fas fa-box-open me-1"></i>Record one</a>
    <a class="btn btn-sm btn-outline-primary" href="<?php echo public_url('super/stock/new.php'); ?>"><i class="fas fa-boxes-stacked me-1"></i>Record in bulk</a>
    <a class="btn btn-sm btn-outline-warning text-dark" href="#returnWarehouseSection"><i class="fas fa-rotate-left me-1"></i>Return to Warehouse</a>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url('super/inventory/'); ?>"><i class="fas fa-store me-1"></i>Shop Inventory</a>
  </div>
</div>

<form method="post" enctype="multipart/form-data" id="storeForm" novalidate>
  <input type="hidden" name="action" value="store">
  <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
      <h2 class="h5 mb-3">Warehouse batch intake <span class="text-muted fw-normal small">(optional)</span></h2>
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
        <h2 class="h5 mb-0">Products received into warehouse</h2>
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

  <button class="btn btn-primary btn-lg mb-4"><i class="fas fa-box-archive me-1"></i>Save products to Store warehouse</button>
</form>

<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;overflow:hidden;">
  <div class="px-4 py-3 border-bottom bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h2 class="h6 fw-bold mb-0">Waiting in warehouse · select stock to transfer into shop Inventory</h2>
      <p class="text-muted small mb-0 mt-1">Tick products and enter how many <strong>packages</strong> (or <strong>kg/L</strong> for measured goods) to move — only that amount goes to Inventory; the rest stays in Store.</p>
    </div>
    <div>
      <input type="text" id="warehouseSearch" class="form-control form-control-sm" placeholder="Search warehouse products..." style="max-width:240px;">
    </div>
  </div>
  <?php if (!$pending): ?>
    <div class="p-4 text-muted small">No products in Store right now. Use Record product / Record in bulk, or the form above.</div>
  <?php else: ?>
  <form method="post" id="transferInvoiceForm" action="">
    <input type="hidden" name="action" value="invoice">
    <div class="table-responsive">
      <table class="table align-middle mb-0" id="warehouseTable">
        <thead><tr class="text-muted small text-uppercase"><th></th><th>Product</th><th>Supplier</th><th>Category</th><th>Brand</th><th class="text-end">In warehouse</th><th style="width:160px;">Qty to transfer</th><th class="text-end">Unit cost</th><th class="text-end">Line</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($pending as $p):
            $unitsPerPkg = max(0.01, (float) ($p['units_per_package'] ?? 1));
            $pkgUnit = trim((string) ($p['package_unit'] ?? '')) ?: 'package';
            $innerUnit = trim((string) ($p['unit'] ?? 'piece')) ?: 'piece';
            $isContinuous = Models\ProductModel::isContinuousUnit($innerUnit);
            $availPkgs = (float) ($p['package_quantity'] ?? 0);
            if ($availPkgs <= 0 && (float) $p['quantity'] > 0) {
                $availPkgs = round((float) $p['quantity'] / $unitsPerPkg, 2);
            }
            $availQty = (float) $p['quantity'];
            $pkgBuy = ($p['package_buying_price'] ?? '') !== '' && (float) $p['package_buying_price'] > 0
                ? (float) $p['package_buying_price']
                : round((float) $p['buying_price'] * $unitsPerPkg, 2);
            $unitBuy = (float) $p['buying_price'];
            $line = $isContinuous ? ($availQty * $unitBuy) : ($availPkgs * $pkgBuy);
            $availPkgsInt = (int) floor($availPkgs + 1e-9);
            $sid = (int) $p['id'];
          ?>
          <tr class="warehouse-row" data-search="<?php echo htmlspecialchars(strtolower($p['name'] . ' ' . ($p['barcode'] ?? '') . ' ' . ($p['category_name'] ?? '') . ' ' . ($p['brand_name'] ?? ''))); ?>" data-continuous="<?php echo $isContinuous ? '1' : '0'; ?>">
            <td><input class="form-check-input store-check" type="checkbox" name="store_ids[]" value="<?php echo $sid; ?>" form="transferInvoiceForm" data-line="<?php echo $line; ?>"></td>
            <td>
              <div class="fw-semibold"><?php echo htmlspecialchars($p['name']); ?></div>
              <div class="text-muted small"><?php echo htmlspecialchars($p['barcode'] ?: 'No barcode'); ?><?php if (!empty($p['product_id'])): ?> · matched inventory<?php endif; ?>
                <?php if ($isContinuous): ?>
                  · measured in <?php echo htmlspecialchars($innerUnit); ?>
                <?php else: ?>
                  · <?php echo rtrim(rtrim(number_format($unitsPerPkg, 2), '0'), '.'); ?> <?php echo htmlspecialchars($innerUnit); ?> / <?php echo htmlspecialchars($pkgUnit); ?>
                <?php endif; ?>
              </div>
            </td>
            <td class="small"><?php echo htmlspecialchars($p['supplier_name'] ?: '—'); ?></td>
            <td class="small"><?php echo htmlspecialchars($p['category_name'] ?: '—'); ?></td>
            <td class="small"><?php echo htmlspecialchars($p['brand_name'] ?: '—'); ?></td>
            <td class="text-end">
              <?php if ($isContinuous): ?>
                <div class="fw-semibold"><?php echo rtrim(rtrim(number_format($availQty, 2), '0'), '.'); ?> <?php echo htmlspecialchars($innerUnit); ?></div>
                <?php if ($availPkgs > 0): ?>
                  <div class="text-muted small"><?php echo rtrim(rtrim(number_format($availPkgs, 2), '0'), '.'); ?> <?php echo htmlspecialchars($pkgUnit); ?><?php echo abs($availPkgs - 1) < 0.001 ? '' : 's'; ?></div>
                <?php endif; ?>
              <?php else: ?>
                <div class="fw-semibold"><?php echo rtrim(rtrim(number_format($availPkgs, 2), '0'), '.'); ?> <?php echo htmlspecialchars($pkgUnit); ?><?php echo $availPkgs == 1 ? '' : 's'; ?></div>
                <div class="text-muted small"><?php echo rtrim(rtrim(number_format($availQty, 2), '0'), '.'); ?> <?php echo htmlspecialchars($innerUnit); ?> sealed</div>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($isContinuous): ?>
              <div class="input-group input-group-sm">
                <input type="number" step="0.01" min="0" max="<?php echo htmlspecialchars((string) $availQty); ?>" name="transfer_quantities[<?php echo $sid; ?>]" form="transferInvoiceForm" class="form-control form-control-sm transfer-qty" value="" placeholder="0" data-price="<?php echo htmlspecialchars((string) $unitBuy); ?>" data-id="<?php echo $sid; ?>" data-units="1" data-pkg-unit="<?php echo htmlspecialchars($innerUnit); ?>" data-continuous="1">
                <span class="input-group-text"><?php echo htmlspecialchars($innerUnit); ?></span>
              </div>
              <div class="text-muted" style="font-size:.68rem;">max <?php echo rtrim(rtrim(number_format($availQty, 2), '0'), '.'); ?></div>
              <?php else: ?>
              <div class="input-group input-group-sm">
                <input type="number" step="1" min="0" max="<?php echo (int) $availPkgsInt; ?>" name="transfer_packages[<?php echo $sid; ?>]" form="transferInvoiceForm" class="form-control form-control-sm transfer-qty" value="" placeholder="0" data-price="<?php echo htmlspecialchars((string) $pkgBuy); ?>" data-id="<?php echo $sid; ?>" data-units="<?php echo htmlspecialchars((string) $unitsPerPkg); ?>" data-pkg-unit="<?php echo htmlspecialchars($pkgUnit); ?>" data-continuous="0">
                <span class="input-group-text"><?php echo htmlspecialchars($pkgUnit); ?>s</span>
              </div>
              <div class="text-muted" style="font-size:.68rem;">max <?php echo (int) $availPkgsInt; ?></div>
              <?php endif; ?>
            </td>
            <td class="text-end">KES <?php echo number_format($isContinuous ? $unitBuy : $pkgBuy, 2); ?></td>
            <td class="text-end fw-semibold transfer-line" data-id="<?php echo $sid; ?>">KES 0.00</td>
            <td class="text-end store-actions">
              <button type="button" class="btn btn-sm btn-outline-secondary edit-store"
                data-id="<?php echo $sid; ?>"
                data-name="<?php echo htmlspecialchars($p['name']); ?>"
                data-category="<?php echo htmlspecialchars($p['category_name'] ?? ''); ?>"
                data-brand="<?php echo htmlspecialchars($p['brand_name'] ?? ''); ?>"
                data-supplier="<?php echo htmlspecialchars($p['supplier_name'] ?? ''); ?>"
                data-barcode="<?php echo htmlspecialchars($p['barcode'] ?? ''); ?>"
                data-unit="<?php echo htmlspecialchars($p['unit'] ?? 'piece'); ?>"
                data-package-unit="<?php echo htmlspecialchars($p['package_unit'] ?? ''); ?>"
                data-package-quantity="<?php echo htmlspecialchars((string) ($p['package_quantity'] ?? '')); ?>"
                data-units-per-package="<?php echo htmlspecialchars((string) ($p['units_per_package'] ?? '')); ?>"
                data-package-price="<?php echo htmlspecialchars((string) ($p['package_price'] ?? '')); ?>"
                data-retail-pack-price="<?php echo htmlspecialchars((string) ($p['retail_pack_price'] ?? '')); ?>"
                data-quantity="<?php echo htmlspecialchars((string) $p['quantity']); ?>"
                data-faulty="<?php echo htmlspecialchars((string) ($p['faulty_quantity'] ?? 0)); ?>"
                data-buying="<?php echo htmlspecialchars((string) $p['buying_price']); ?>"
                data-retail="<?php echo htmlspecialchars((string) $p['retail_price']); ?>"
                data-wholesale="<?php echo htmlspecialchars((string) $p['wholesale_price']); ?>"
                data-notes="<?php echo htmlspecialchars($p['notes'] ?? ''); ?>">Edit</button>
              <button type="submit" class="btn btn-sm btn-outline-danger" form="deleteStoreProductForm-<?php echo $sid; ?>" onclick="return confirm('Delete this stored product?');">Delete</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="p-3 border-top bg-light">
      <div class="row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label small mb-1">Invoice to (shop / note)</label><input name="invoice_to" class="form-control form-control-sm" placeholder="e.g. Main shop floor" value="Shop Inventory"></div>
        <div class="col-md-4"><label class="form-label small mb-1">Notes</label><input name="notes" class="form-control form-control-sm" placeholder="Optional"></div>
        <div class="col-md-2 fw-bold">Capital: <span id="selectedTotal">KES 0</span></div>
        <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100" id="invoiceBtn" disabled>Generate &amp; save invoice</button></div>
      </div>
      <p class="text-muted small mb-0 mt-2">This creates a saved transfer invoice (<code>STR-######</code>), moves only the quantity you entered into shop Inventory, and keeps the invoice on this page for printing and capital tracking.</p>
    </div>
  </form>
  <?php foreach ($pending as $p): $sid = (int) $p['id']; ?>
  <form method="post" id="deleteStoreProductForm-<?php echo $sid; ?>" class="d-none">
    <input type="hidden" name="action" value="delete_store_product">
    <input type="hidden" name="id" value="<?php echo $sid; ?>">
  </form>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- RETURN TO WAREHOUSE FEATURE -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;overflow:hidden;" id="returnWarehouseSection">
  <div class="px-4 py-3 border-bottom bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h2 class="h6 fw-bold mb-0 text-dark"><i class="fas fa-rotate-left text-warning me-2"></i>Return Products to Warehouse (Shop → Store)</h2>
      <p class="text-muted small mb-0 mt-1">Return sellable items from shop inventory back to the warehouse. Shop stock will decrease, warehouse stock will increase, and expected profit will reduce accordingly.</p>
    </div>
    <button type="button" class="btn btn-sm btn-outline-warning text-dark" id="addReturnRowBtn"><i class="fas fa-plus me-1"></i>Add item to return</button>
  </div>
  <form method="post" id="returnWarehouseForm" class="p-4 bg-light">
    <input type="hidden" name="action" value="return_to_warehouse">
    <div id="returnRowsWrap">
      <!-- Dynamic return rows -->
    </div>
    <div class="row g-2 align-items-end mt-2 pt-3 border-top">
      <div class="col-md-5">
        <label class="form-label small mb-1">Return Reason / Notes <span class="text-muted">(optional)</span></label>
        <input name="notes" class="form-control form-control-sm" placeholder="e.g. Returned excess stock to warehouse">
      </div>
      <div class="col-md-4">
        <div class="p-2 bg-white rounded border">
          <div class="small text-muted">Estimated Profit Reduction:</div>
          <div class="fw-bold text-danger" id="totalProfitReductionDisplay">KES 0.00</div>
        </div>
      </div>
      <div class="col-md-3">
        <button type="submit" class="btn btn-warning btn-sm w-100 fw-bold py-2" id="confirmReturnBtn" disabled><i class="fas fa-box-archive me-1"></i>Generate Return Note (WRN)</button>
      </div>
    </div>
  </form>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;overflow:hidden;">
  <div class="px-4 py-3 border-bottom bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h2 class="h6 fw-bold mb-0">Saved Invoices &amp; Warehouse Return Notes</h2>
      <p class="text-muted small mb-0">Transfer invoices (<strong>STR-######</strong>) and Return notes (<strong>WRN-######</strong>).</p>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr class="text-muted small text-uppercase">
          <th>Invoice / Note #</th>
          <th>Type</th>
          <th>From / To</th>
          <th>Items</th>
          <th>When</th>
          <th class="text-end">Total Value</th>
          <th class="text-end">Profit Impact</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$invoices): ?><tr><td colspan="8" class="text-center text-muted py-4">No saved transfer invoices or return notes yet.</td></tr><?php endif; ?>
        <?php foreach ($invoices as $inv):
          $isReturn = ($inv['invoice_type'] ?? '') === 'return';
          $profitImpact = (float) ($inv['profit_impact'] ?? 0);
        ?>
        <tr>
          <td class="fw-semibold"><a href="<?php echo public_url('super/store/invoice.php?id=' . (int) $inv['id']); ?>"><?php echo htmlspecialchars($inv['invoice_number']); ?></a></td>
          <td>
            <?php if ($isReturn): ?>
              <span class="badge bg-warning text-dark"><i class="fas fa-rotate-left me-1"></i>Return (Shop → Store)</span>
            <?php else: ?>
              <span class="badge bg-primary"><i class="fas fa-arrow-right me-1"></i>Transfer (Store → Shop)</span>
            <?php endif; ?>
          </td>
          <td><?php echo htmlspecialchars($inv['invoice_to'] ?: ($isReturn ? 'Store Warehouse' : 'Shop Inventory')); ?></td>
          <td><?php echo (int) $inv['item_count']; ?></td>
          <td class="small text-muted"><?php echo date('j M Y, g:i a', strtotime($inv['created_at'])); ?></td>
          <td class="text-end fw-semibold">KES <?php echo number_format((float) $inv['total'], 2); ?></td>
          <td class="text-end">
            <?php if ($isReturn && $profitImpact < 0): ?>
              <span class="badge bg-danger" title="Expected profit reduced">KES <?php echo number_format($profitImpact, 2); ?></span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="text-end store-actions">
            <a class="btn btn-sm btn-outline-primary" href="<?php echo public_url('super/store/invoice.php?id=' . (int) $inv['id']); ?>">Open / Print</a>
            <button type="submit" class="btn btn-sm btn-outline-danger" form="deleteStoreInvoiceForm-<?php echo (int) $inv['id']; ?>" onclick="return confirm('Delete this invoice and reverse its stock changes?');">Delete</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php foreach ($invoices as $inv): ?>
<form method="post" id="deleteStoreInvoiceForm-<?php echo (int) $inv['id']; ?>" class="d-none">
  <input type="hidden" name="action" value="delete_store_invoice">
  <input type="hidden" name="invoice_id" value="<?php echo (int) $inv['id']; ?>">
</form>
<?php endforeach; ?>

<div class="modal fade" id="editStoreModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="edit_store_product">
        <input type="hidden" name="id" id="editStoreId">
        <div class="modal-header">
          <h5 class="modal-title">Edit stored product</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-6"><label class="form-label small">Product name</label><input name="name" id="editName" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label small">Supplier</label><input name="supplier" id="editSupplier" class="form-control"></div>
            <div class="col-md-4"><label class="form-label small">Category</label><input name="category" id="editCategory" class="form-control"></div>
            <div class="col-md-4"><label class="form-label small">Brand</label><input name="brand" id="editBrand" class="form-control"></div>
            <div class="col-md-4"><label class="form-label small">Barcode</label><input name="barcode" id="editBarcode" class="form-control"></div>
            <div class="col-md-3"><label class="form-label small">Unit</label><select name="unit" id="editUnit" class="form-select"><?php foreach ($units as $u): ?><option value="<?php echo htmlspecialchars($u); ?>"><?php echo htmlspecialchars($u); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label small">Quantity</label><input type="number" step="0.01" min="0.01" name="quantity" id="editQuantity" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label small">Faulty</label><input type="number" step="0.01" min="0" name="faulty_quantity" id="editFaulty" class="form-control"></div>
            <div class="col-md-3"><label class="form-label small">Package unit</label><input name="package_unit" id="editPackageUnit" class="form-control" placeholder="carton / bale / pack"></div>
            <div class="col-md-3"><label class="form-label small">Packages</label><input type="number" step="0.01" min="0" name="package_quantity" id="editPackageQuantity" class="form-control"></div>
            <div class="col-md-3"><label class="form-label small">Items inside each package</label><input type="number" step="0.01" min="0.01" name="units_per_package" id="editUnitsPerPackage" class="form-control"></div>
            <div class="col-md-3"><label class="form-label small">Wholesale per package</label><input type="number" step="0.01" min="0" name="package_price" id="editPackagePrice" class="form-control"></div>
            <div class="col-md-3"><label class="form-label small">Retail per package</label><input type="number" step="0.01" min="0" name="retail_pack_price" id="editRetailPackPrice" class="form-control"></div>
            <div class="col-md-4"><label class="form-label small">Buying price</label><input type="number" step="0.01" min="0" name="buying_price" id="editBuying" class="form-control"></div>
            <div class="col-md-4"><label class="form-label small">Selling price</label><input type="number" step="0.01" min="0" name="retail_price" id="editRetail" class="form-control"></div>
            <div class="col-md-4"><label class="form-label small">Wholesale</label><input type="number" step="0.01" min="0" name="wholesale_price" id="editWholesale" class="form-control"></div>
            <div class="col-12"><label class="form-label small">Notes</label><input name="notes" id="editNotes" class="form-control"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary">Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<template id="rowTpl">
  <div class="store-row border rounded p-3 mb-3" style="border-color:#e2e8f0!important;">
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
          <?php
            $packageUnits = array_values(array_filter($units, fn($u) => $u !== 'piece'));
            foreach ($packageUnits as $u):
          ?>
            <option value="<?php echo htmlspecialchars($u); ?>" <?php echo $u === 'carton' ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($u)); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 mt-2 packageFields">
        <div class="border rounded p-2" style="border-color:#e2e8f0!important;background:#fafbfc;">
          <div class="small fw-semibold mb-2 text-secondary"><i class="fas fa-boxes-stacked me-1"></i>Package &amp; Unit details <span class="text-muted fw-normal">(optional)</span></div>
          <div class="row g-2">
            <div class="col-6 col-sm-3">
              <label class="form-label small mb-1 packageQtyLabel">Number of packages <span class="text-muted">(optional)</span></label>
              <input type="number" step="0.01" min="0" name="items[__I__][package_quantity]" class="form-control form-control-sm packageQty" placeholder="0">
            </div>
            <div class="col-6 col-sm-3">
              <label class="form-label small mb-1 unitsPerPackageLabel">Items inside each package <span class="text-muted">(optional)</span></label>
              <input type="number" step="0.01" min="0" name="items[__I__][units_per_package]" class="form-control form-control-sm unitsPerPackage" placeholder="0">
            </div>
            <div class="col-6 col-sm-3">
              <label class="form-label small mb-1">Inside unit <span class="text-muted">(optional)</span></label>
              <select name="items[__I__][inner_unit]" class="form-select form-select-sm">
                <?php foreach ($units as $u): ?>
                  <option value="<?php echo htmlspecialchars($u); ?>"><?php echo htmlspecialchars(ucfirst($u)); ?></option>
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
        <label class="form-label small mb-1 qtyLabel">Total sellable items <span class="text-muted">(optional)</span></label>
        <input type="number" step="0.01" min="0" name="items[__I__][quantity]" class="form-control form-control-sm qty" placeholder="Auto or enter directly">
      </div>
      <div class="col-6 col-sm-3 mt-2">
        <label class="form-label small mb-1">Faulty / broken items <span class="text-muted">(optional)</span></label>
        <input type="number" step="0.01" min="0" name="items[__I__][faulty_quantity]" class="form-control form-control-sm" placeholder="0">
      </div>
      <div class="col-6 col-sm-3 mt-2">
        <label class="form-label small mb-1 buyingLabel">Buying price per package <span class="text-muted">(optional)</span></label>
        <input type="number" step="0.01" min="0" name="items[__I__][buying_price]" class="form-control form-control-sm buyingPrice" placeholder="0">
      </div>
      <div class="col-6 col-sm-3 mt-2 newProductFields">
        <label class="form-label small mb-1 retailLabel">Retail price per single item <span class="text-muted">(optional)</span></label>
        <input type="number" step="0.01" min="0" name="items[__I__][selling_price]" class="form-control form-control-sm retailPrice" placeholder="0">
      </div>
      <div class="col-6 col-sm-3 mt-2 newProductFields">
        <label class="form-label small mb-1 wholesaleLabel">Wholesale price per package <span class="text-muted">(optional)</span></label>
        <input type="number" step="0.01" min="0" name="items[__I__][wholesale_price]" class="form-control form-control-sm wholesalePrice" placeholder="0">
      </div>
      <div class="col-6 col-sm-3 mt-2 newProductFields">
        <label class="form-label small mb-1 retailPackLabel">Retail price per package <span class="text-muted">(optional)</span></label>
        <input type="number" step="0.01" min="0" name="items[__I__][retail_pack_price]" class="form-control form-control-sm retailPackPrice" placeholder="0">
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
        <input type="text" name="items[__I__][remark]" class="form-control form-control-sm" placeholder="e.g. stored for invoice transfer">
      </div>
    </div>
  </div>
</template>

<template id="returnRowTpl">
  <div class="return-row card border p-3 mb-2 bg-white rounded-3 shadow-sm position-relative">
    <button type="button" class="btn btn-outline-danger btn-sm position-absolute top-0 end-0 m-2 remove-return-row" title="Remove" style="padding:0.15rem 0.45rem;"><i class="fas fa-times"></i></button>
    <div class="row g-2 align-items-center">
      <div class="col-12 col-md-5">
        <label class="form-label small mb-1 fw-semibold"><i class="fas fa-magnifying-glass me-1 text-warning"></i>Product to Return</label>
        <div class="position-relative">
          <input type="text" class="form-control form-control-sm return-prod-search" placeholder="Type product name or barcode..." autocomplete="off">
          <input type="hidden" name="return_items[__I__][product_id]" class="return-prod-id" value="">
          <div class="return-prod-menu dropdown-menu shadow-lg border-0 w-100 mt-1 py-0" style="max-height:280px;overflow-y:auto;display:none;z-index:1060;border-radius:10px;"></div>
        </div>
        <div class="d-flex align-items-center justify-content-between mt-1">
          <div class="text-muted small return-stock-info" style="font-size:0.75rem;">Type name or scan barcode to autofill</div>
          <button type="button" class="btn btn-link btn-sm text-secondary p-0 return-clear-prod" style="font-size:0.72rem;display:none;text-decoration:none;"><i class="fas fa-rotate-left me-1"></i>Change</button>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1 return-pkg-label">Packages</label>
        <div class="input-group input-group-sm">
          <input type="number" step="0.01" min="0" name="return_items[__I__][package_quantity]" class="form-control form-control-sm return-pkg-qty" placeholder="0">
          <span class="input-group-text return-pkg-unit" style="font-size:0.75rem;">pkgs</span>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1 return-item-label">Total items to return</label>
        <div class="input-group input-group-sm">
          <input type="number" step="0.01" min="0" name="return_items[__I__][quantity]" class="form-control form-control-sm return-item-qty" placeholder="0">
          <span class="input-group-text return-inner-unit" style="font-size:0.75rem;">pcs</span>
        </div>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label small mb-1">Expected profit reduction</label>
        <div class="border rounded px-2 py-1 bg-light text-danger fw-bold small return-profit-display" style="min-height:31px;display:flex;align-items:center;">
          -KES 0.00
        </div>
      </div>
    </div>
  </div>
</template>

<style>
  .store-row .newProductFields { display: block; }
  .store-row.is-restock .newProductFields { display: none !important; }
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
  .return-prod-menu {
    position: absolute; left: 0; right: 0; top: 100%; z-index: 1060;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
    box-shadow: 0 10px 25px rgba(15,23,42,.12);
  }
  .return-prod-menu .dropdown-item:hover, .return-prod-menu .dropdown-item.active {
    background-color: #f8fafc;
  }
  .matchNote { color: #0d6efd; }
  .store-actions{white-space:nowrap;}
  .store-actions .btn{margin:.1rem;}
  @media (max-width: 768px) {
    #storeForm .card-body{padding:1rem!important;}
    #storeForm .d-flex.justify-content-between{align-items:flex-start!important;gap:.75rem;flex-wrap:wrap;}
    #addRowBtn,#storeForm > .btn{width:100%;}
    .store-actions{display:grid;grid-template-columns:1fr;gap:.35rem;white-space:normal;}
    .store-actions .btn,.store-actions form,.store-actions button{width:100%;margin:0;}
    .transfer-qty{min-width:96px;}
    .modal-dialog{margin:.5rem;}
  }
</style>
<script>
(function () {
  var API = <?php echo json_encode($apiBase); ?>;
  var SHOP_PRODUCTS = <?php echo json_encode($shopProductsData); ?>;
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
      if (item.retail_pack_price && row.querySelector('.retailPackPrice')) { row.querySelector('.retailPackPrice').value = item.retail_pack_price; }
      row.querySelector('.qtyLabel').textContent = 'Qty to store';
      var bits = [item.category_name || item.subject_name, item.brand_name || item.publisher_name, item.unit].filter(Boolean);
      note.style.display = 'block';
      note.innerHTML = '<i class="fas fa-circle-check me-1"></i>Already in shop catalogue' +
        (bits.length ? ' — ' + bits.join(' · ') : '') +
        '. Shop balance: <strong>' + (item.balance != null ? item.balance : '') + '</strong>. Saving here keeps it in Store until you generate a transfer invoice.';
      recalc();
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
            note.innerHTML = '<i class="fas fa-circle-plus me-1"></i>New barcode — will be saved with this stored product.';
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
    document.querySelectorAll('.store-row').forEach(function (row) {
      var unit = row.querySelector('.unitSelect').value;
      var isPackageUnit = unit !== 'piece';
      var pkgFields = row.querySelector('.packageFields');
      var qtyInput = row.querySelector('.qty');
      var pkgQty = parseFloat((row.querySelector('.packageQty') || {}).value) || 0;
      var perPkg = parseFloat((row.querySelector('.unitsPerPackage') || {}).value) || 0;
      var hasPackage = isPackageUnit && pkgQty > 0 && perPkg > 0;
      var qty = hasPackage ? (pkgQty * perPkg) : (parseFloat(qtyInput.value) || 0);
      var buy = parseFloat(row.querySelector('.buyingPrice').value) || 0;
      var retail = parseFloat(row.querySelector('.retailPrice').value) || 0;
      var wholesale = parseFloat(row.querySelector('.wholesalePrice').value) || 0;
      var retailPack = parseFloat((row.querySelector('.retailPackPrice') || {}).value) || 0;
      if (pkgFields) pkgFields.style.display = isPackageUnit ? 'block' : 'none';
      var packageQtyLabel = row.querySelector('.packageQtyLabel');
      var unitsPerPackageLabel = row.querySelector('.unitsPerPackageLabel');
      if (packageQtyLabel) packageQtyLabel.textContent = 'Number of ' + unit + 's';
      if (unitsPerPackageLabel) unitsPerPackageLabel.textContent = 'Items inside each ' + unit;
      if (hasPackage) {
        qtyInput.value = qty > 0 ? (Math.round(qty * 100) / 100) : '';
        qtyInput.readOnly = true;
        row.querySelector('.qtyLabel').textContent = 'Total items received';
        row.querySelector('.buyingLabel').textContent = 'Buying price of the package';
        row.querySelector('.wholesaleLabel').textContent = 'Selling price of the package (wholesale price)';
        var retailPackLabel = row.querySelector('.retailPackLabel');
        if (retailPackLabel) retailPackLabel.textContent = 'Selling price of the package (retail price)';
        row.querySelector('.retailLabel').textContent = 'Selling price of items inside (retail price)';
        var totalItems = row.querySelector('.totalItems');
        if (totalItems) totalItems.textContent = Math.round(qty * 100) / 100;
      } else {
        qtyInput.readOnly = false;
        row.querySelector('.qtyLabel').textContent = isPackageUnit ? ('Good ' + unit + ' qty received') : (row.classList.contains('is-restock') ? 'Qty to store' : 'Good qty received');
        row.querySelector('.buyingLabel').textContent = isPackageUnit ? 'Buying price of the package' : 'Buying price per item';
        row.querySelector('.wholesaleLabel').innerHTML = isPackageUnit ? 'Selling price of the package (wholesale price)' : 'Selling price wholesale <span class="text-muted">(optional)</span>';
        row.querySelector('.retailLabel').textContent = 'Selling price of items inside (retail price)';
      }
      var total = hasPackage ? (pkgQty * buy) : (qty * buy);
      var wholesaleReturn = hasPackage ? (pkgQty * wholesale) : (qty * wholesale);
      var retailPackReturn = hasPackage ? (pkgQty * retailPack) : 0;
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

  function transferAmount(qtyInput) {
    if (!qtyInput) return 0;
    var val = parseFloat(qtyInput.value) || 0;
    if (qtyInput.dataset.continuous === '1') {
      return Math.round(val * 100) / 100;
    }
    return Math.floor(val);
  }

  function refreshSelectedTotal(){
    var total = 0, ready = 0;
    document.querySelectorAll('.store-check').forEach(function(c){
      var qtyInput = document.querySelector('.transfer-qty[data-id="' + c.value + '"]');
      var amt = transferAmount(qtyInput);
      var price = qtyInput ? (parseFloat(qtyInput.dataset.price) || 0) : 0;
      var line = amt * price;
      var lineEl = document.querySelector('.transfer-line[data-id="' + c.value + '"]');
      if (lineEl) lineEl.textContent = money(line);
      if (c.checked) {
        if (amt > 0) {
          ready++;
          total += line;
        }
      }
    });
    var out = document.getElementById('selectedTotal'); if(out) out.textContent = money(total);
    var btn = document.getElementById('invoiceBtn'); if(btn) btn.disabled = ready === 0;
  }

  document.getElementById('addRowBtn').addEventListener('click', addRow);
  addRow();
  var supplierInput = document.querySelector('.ta-input[data-field="supplier"]');
  if (supplierInput) attachTypeahead(supplierInput, 'supplier');
  document.querySelectorAll('.store-check').forEach(function(c){ c.addEventListener('change', refreshSelectedTotal); });
  document.querySelectorAll('.transfer-qty').forEach(function(q){
    q.addEventListener('input', function(){
      var id = q.dataset.id;
      var check = document.querySelector('.store-check[value="' + id + '"]');
      var amt = transferAmount(q);
      if (check && amt > 0) check.checked = true;
      refreshSelectedTotal();
    });
    q.addEventListener('keydown', function(e){
      if (e.key === 'Enter') e.preventDefault();
    });
  });
  var transferForm = document.getElementById('transferInvoiceForm');
  if (transferForm) {
    transferForm.addEventListener('submit', function(e){
      var ready = 0;
      document.querySelectorAll('.store-check').forEach(function(c){
        if (!c.checked) return;
        var qtyInput = document.querySelector('.transfer-qty[data-id="' + c.value + '"]');
        if (transferAmount(qtyInput) > 0) ready++;
      });
      if (ready === 0) {
        e.preventDefault();
        alert('Select products and enter how much to transfer (packages or kg/L).');
        return;
      }
      if (!confirm('Generate and save transfer invoice for ' + ready + ' product line(s)? Stock will move to shop Inventory and the invoice will be stored as STR-######.')) {
        e.preventDefault();
      }
    });
  }
  document.querySelectorAll('.edit-store').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.getElementById('editStoreId').value = btn.dataset.id || '';
      document.getElementById('editName').value = btn.dataset.name || '';
      document.getElementById('editSupplier').value = btn.dataset.supplier || '';
      document.getElementById('editCategory').value = btn.dataset.category || '';
      document.getElementById('editBrand').value = btn.dataset.brand || '';
      document.getElementById('editBarcode').value = btn.dataset.barcode || '';
      document.getElementById('editUnit').value = btn.dataset.unit || 'piece';
      document.getElementById('editPackageUnit').value = btn.dataset.packageUnit || '';
      document.getElementById('editPackageQuantity').value = btn.dataset.packageQuantity || '';
      document.getElementById('editUnitsPerPackage').value = btn.dataset.unitsPerPackage || '';
      document.getElementById('editPackagePrice').value = btn.dataset.packagePrice || '';
      document.getElementById('editRetailPackPrice').value = btn.dataset.retailPackPrice || '';
      document.getElementById('editQuantity').value = btn.dataset.quantity || '0';
      document.getElementById('editFaulty').value = btn.dataset.faulty || '0';
      document.getElementById('editBuying').value = btn.dataset.buying || '0';
      document.getElementById('editRetail').value = btn.dataset.retail || '0';
      document.getElementById('editWholesale').value = btn.dataset.wholesale || '0';
      document.getElementById('editNotes').value = btn.dataset.notes || '';
      new bootstrap.Modal(document.getElementById('editStoreModal')).show();
    });
  });
  // Warehouse live search
  var wSearch = document.getElementById('warehouseSearch');
  if (wSearch) {
    wSearch.addEventListener('input', function() {
      var q = this.value.toLowerCase().trim();
      document.querySelectorAll('.warehouse-row').forEach(function(row) {
        var txt = (row.getAttribute('data-search') || '').toLowerCase();
        row.style.display = (!q || txt.indexOf(q) !== -1) ? '' : 'none';
      });
    });
  }

  // Return products to warehouse logic
  var returnWrap = document.getElementById('returnRowsWrap');
  var returnTpl = document.getElementById('returnRowTpl');
  var addReturnRowBtn = document.getElementById('addReturnRowBtn');
  var confirmReturnBtn = document.getElementById('confirmReturnBtn');
  var totalProfitReductionDisplay = document.getElementById('totalProfitReductionDisplay');
  var returnIndex = 0;

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
  }

  function recalcReturns() {
    var totalProfitLoss = 0;
    var ready = 0;
    if (!returnWrap) return;
    returnWrap.querySelectorAll('.return-row').forEach(function(row) {
      var prod = row._selectedProduct;
      var prodIdInput = row.querySelector('.return-prod-id');
      var profitDisp = row.querySelector('.return-profit-display');
      var itemQtyInput = row.querySelector('.return-item-qty');
      var qty = itemQtyInput ? (parseFloat(itemQtyInput.value) || 0) : 0;
      if (prod && prodIdInput && prodIdInput.value && qty > 0) {
        var retail = parseFloat(prod.retail_price) || 0;
        var buying = parseFloat(prod.buying_price) || 0;
        var unitProfit = Math.max(0, retail - buying);
        var lineLoss = Math.round(unitProfit * qty * 100) / 100;
        totalProfitLoss += lineLoss;
        if (profitDisp) profitDisp.textContent = '-KES ' + money(lineLoss);
        ready++;
      } else {
        if (profitDisp) profitDisp.textContent = '-KES 0.00';
      }
    });
    if (totalProfitReductionDisplay) {
      totalProfitReductionDisplay.textContent = '-KES ' + money(totalProfitLoss);
    }
    if (confirmReturnBtn) {
      confirmReturnBtn.disabled = ready === 0;
    }
  }

  function addReturnRow() {
    if (!returnWrap || !returnTpl) return;
    var html = returnTpl.innerHTML.replace(/__I__/g, returnIndex++);
    var div = document.createElement('div');
    div.innerHTML = html.trim();
    var row = div.firstElementChild;
    returnWrap.appendChild(row);

    var searchInput = row.querySelector('.return-prod-search');
    var prodIdInput = row.querySelector('.return-prod-id');
    var menu = row.querySelector('.return-prod-menu');
    var clearBtn = row.querySelector('.return-clear-prod');
    var pkgQtyInput = row.querySelector('.return-pkg-qty');
    var itemQtyInput = row.querySelector('.return-item-qty');
    var pkgUnitLabel = row.querySelector('.return-pkg-unit');
    var innerUnitLabel = row.querySelector('.return-inner-unit');
    var stockInfo = row.querySelector('.return-stock-info');
    var removeBtn = row.querySelector('.remove-return-row');

    var searchTimer = null;
    var activeIdx = -1;
    var currentMatches = [];

    row._selectedProduct = null;

    if (removeBtn) {
      removeBtn.addEventListener('click', function() {
        row.remove();
        recalcReturns();
      });
    }

    function selectProduct(p) {
      row._selectedProduct = p;
      if (!p) {
        prodIdInput.value = '';
        searchInput.value = '';
        if (stockInfo) stockInfo.textContent = 'Type name or scan barcode to autofill';
        if (clearBtn) clearBtn.style.display = 'none';
        if (pkgUnitLabel) pkgUnitLabel.textContent = 'pkgs';
        if (innerUnitLabel) innerUnitLabel.textContent = 'pcs';
        if (itemQtyInput) itemQtyInput.removeAttribute('max');
        recalcReturns();
        return;
      }

      prodIdInput.value = p.id;
      searchInput.value = p.name;
      menu.style.display = 'none';
      if (clearBtn) clearBtn.style.display = 'inline-block';

      var avail = parseFloat(p.qty) || 0;
      var unit = p.unit || 'piece';
      var packUnit = p.pack_unit || 'package';
      var upp = parseFloat(p.units_per_package) || 1;
      var buying = parseFloat(p.buying_price) || 0;
      var retail = parseFloat(p.retail_price) || 0;
      var margin = Math.max(0, retail - buying);

      if (pkgUnitLabel) pkgUnitLabel.textContent = packUnit + 's';
      if (innerUnitLabel) innerUnitLabel.textContent = unit;
      if (itemQtyInput) itemQtyInput.max = avail;
      if (stockInfo) {
        var packText = upp > 1 ? ' (' + (Math.round((avail / upp) * 10) / 10) + ' ' + packUnit + 's)' : '';
        stockInfo.innerHTML = '<span class="text-primary fw-semibold">In shop: ' + avail + ' ' + escapeHtml(unit) + packText + '</span> · Buy: KES ' + money(buying) + ' · Sell: KES ' + money(retail) + ' · Margin: KES ' + money(margin) + '/' + escapeHtml(unit);
      }

      recalcReturns();

      // Automatically focus quantity input
      if (upp > 1 && pkgQtyInput) {
        pkgQtyInput.focus();
        pkgQtyInput.select();
      } else if (itemQtyInput) {
        itemQtyInput.focus();
        itemQtyInput.select();
      }
    }

    if (clearBtn) {
      clearBtn.addEventListener('click', function(e) {
        e.preventDefault();
        selectProduct(null);
        searchInput.focus();
      });
    }

    function renderProductMenu(matches) {
      currentMatches = matches || [];
      activeIdx = -1;
      menu.innerHTML = '';
      if (!currentMatches.length) {
        menu.innerHTML = '<div class="px-3 py-2 text-muted small text-center"><i class="fas fa-circle-exclamation me-1"></i>No shop products matching that search</div>';
        menu.style.display = 'block';
        return;
      }

      var hdr = document.createElement('div');
      hdr.className = 'px-3 py-1 bg-light border-bottom text-muted small fw-semibold text-uppercase';
      hdr.style.fontSize = '0.68rem';
      hdr.textContent = 'Shop Products (' + currentMatches.length + ')';
      menu.appendChild(hdr);

      currentMatches.forEach(function(p, i) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'dropdown-item px-3 py-2 border-bottom d-flex justify-content-between align-items-center gap-2 text-wrap';
        btn.style.cursor = 'pointer';

        var upp = parseFloat(p.units_per_package) || 1;
        var packUnit = p.pack_unit || 'pkg';
        var packText = upp > 1 ? ' (' + (Math.round((p.qty / upp) * 10) / 10) + ' ' + packUnit + 's)' : '';

        btn.innerHTML =
          '<div class="text-start flex-grow-1">' +
            '<div class="fw-semibold text-dark">' + escapeHtml(p.name) + 
              (p.barcode ? ' <span class="badge bg-light text-secondary border font-monospace ms-1" style="font-size:0.7rem;">' + escapeHtml(p.barcode) + '</span>' : '') +
            '</div>' +
            '<div class="text-muted small" style="font-size:0.75rem;">' +
              'Shop stock: <strong class="' + (p.qty > 0 ? 'text-primary' : 'text-danger') + '">' + p.qty + ' ' + escapeHtml(p.unit) + packText + '</strong>' +
              ' · Sell: KES ' + money(p.retail_price) + ' · Buy: KES ' + money(p.buying_price) +
            '</div>' +
          '</div>' +
          '<span class="badge bg-warning-subtle text-dark border border-warning-subtle ms-2" style="font-size:0.68rem;"><i class="fas fa-check me-1"></i>Autofill</span>';

        btn.addEventListener('mousedown', function(e) {
          e.preventDefault();
          selectProduct(p);
        });
        menu.appendChild(btn);
      });
      menu.style.display = 'block';
    }

    function searchProducts(q) {
      q = (q || '').toLowerCase().trim();
      var products = window.SHOP_PRODUCTS || [];
      if (!q) {
        renderProductMenu(products.slice(0, 8));
        return;
      }
      var matches = products.filter(function(p) {
        var name = (p.name || '').toLowerCase();
        var barcode = (p.barcode || '').toLowerCase();
        return name.indexOf(q) !== -1 || barcode.indexOf(q) !== -1;
      }).slice(0, 10);
      renderProductMenu(matches);
    }

    searchInput.addEventListener('input', function() {
      if (row._selectedProduct && searchInput.value !== row._selectedProduct.name) {
        row._selectedProduct = null;
        prodIdInput.value = '';
        if (clearBtn) clearBtn.style.display = 'none';
        if (stockInfo) stockInfo.textContent = 'Type name or scan barcode to autofill';
        recalcReturns();
      }
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function() {
        searchProducts(searchInput.value);
      }, 100);
    });

    searchInput.addEventListener('focus', function() {
      if (!row._selectedProduct) {
        searchProducts(searchInput.value);
      }
    });

    searchInput.addEventListener('keydown', function(e) {
      var items = menu.querySelectorAll('.dropdown-item');
      if (menu.style.display !== 'block' || !items.length) {
        if (e.key === 'Enter') {
          e.preventDefault();
        }
        return;
      }
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIdx = (activeIdx + 1) % items.length;
        items.forEach(function(it, idx) {
          it.classList.toggle('active', idx === activeIdx);
          if (idx === activeIdx) it.scrollIntoView({ block: 'nearest' });
        });
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIdx = (activeIdx - 1 + items.length) % items.length;
        items.forEach(function(it, idx) {
          it.classList.toggle('active', idx === activeIdx);
          if (idx === activeIdx) it.scrollIntoView({ block: 'nearest' });
        });
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (activeIdx >= 0 && currentMatches[activeIdx]) {
          selectProduct(currentMatches[activeIdx]);
        } else if (currentMatches.length === 1) {
          selectProduct(currentMatches[0]);
        }
      } else if (e.key === 'Escape') {
        menu.style.display = 'none';
      }
    });

    searchInput.addEventListener('blur', function() {
      setTimeout(function() {
        menu.style.display = 'none';
      }, 200);
    });

    if (pkgQtyInput) {
      pkgQtyInput.addEventListener('input', function() {
        var p = row._selectedProduct;
        var upp = p ? (parseFloat(p.units_per_package) || 1) : 1;
        var pkgs = parseFloat(pkgQtyInput.value) || 0;
        if (itemQtyInput) {
          itemQtyInput.value = Math.round(pkgs * upp * 100) / 100;
        }
        recalcReturns();
      });
    }

    if (itemQtyInput) {
      itemQtyInput.addEventListener('input', function() {
        var p = row._selectedProduct;
        var upp = p ? (parseFloat(p.units_per_package) || 1) : 1;
        var items = parseFloat(itemQtyInput.value) || 0;
        if (pkgQtyInput && upp > 1) {
          pkgQtyInput.value = Math.round((items / upp) * 100) / 100;
        }
        recalcReturns();
      });
    }

    recalcReturns();
  }

  if (addReturnRowBtn) {
    addReturnRowBtn.addEventListener('click', addReturnRow);
  }
  if (returnWrap && returnWrap.children.length === 0) {
    addReturnRow();
  }

  var returnWarehouseForm = document.getElementById('returnWarehouseForm');
  if (returnWarehouseForm) {
    returnWarehouseForm.addEventListener('submit', function(e) {
      var ready = 0;
      returnWrap.querySelectorAll('.return-row').forEach(function(row) {
        var prodIdInput = row.querySelector('.return-prod-id');
        var itemQtyInput = row.querySelector('.return-item-qty');
        var qty = itemQtyInput ? (parseFloat(itemQtyInput.value) || 0) : 0;
        if (prodIdInput && prodIdInput.value && qty > 0) ready++;
      });
      if (ready === 0) {
        e.preventDefault();
        alert('Please search and select at least one shop product, and enter the return quantity.');
        return;
      }
      if (!confirm('Generate Warehouse Return Note (WRN) for ' + ready + ' product line(s)? Items will be removed from shop inventory and returned to Store warehouse. Shop profit will be reduced.')) {
        e.preventDefault();
      }
    });
  }

  refreshSelectedTotal();
})();
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
