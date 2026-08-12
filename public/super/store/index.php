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
    $receiveUnit = in_array($row['unit'] ?? '', $units, true) ? $row['unit'] : 'piece';
    $packageQty = max(0, (float) ($row['package_quantity'] ?? 0));
    $inside = max(0, (float) ($row['units_per_package'] ?? 0));
    if ($packageQty <= 0 || $inside <= 0) {
        return [
            'quantity' => (float) ($row['quantity'] ?? 0),
            'faulty_quantity' => max(0, (float) ($row['faulty_quantity'] ?? 0)),
            'unit' => $receiveUnit,
            'buying_price' => (float) ($row['buying_price'] ?? 0),
            'package_buying_price' => null,
            'wholesale_price' => $row['wholesale_price'] ?? '',
            'package_unit' => null,
            'package_quantity' => null,
            'units_per_package' => 1,
            'package_price' => null,
        ];
    }
    $packageCost = max(0, (float) ($row['buying_price'] ?? 0));
    $packageWholesale = ($row['wholesale_price'] ?? '') !== '' ? max(0, (float) $row['wholesale_price']) : null;
    $innerUnit = in_array($row['inner_unit'] ?? '', $units, true) ? $row['inner_unit'] : 'piece';

    return [
        'quantity' => round($packageQty * $inside, 2),
        'faulty_quantity' => round(max(0, (float) ($row['faulty_quantity'] ?? 0)) * $inside, 2),
        'unit' => $innerUnit,
        'buying_price' => round($packageCost / $inside, 2),
        'package_buying_price' => $packageCost,
        'wholesale_price' => $packageWholesale !== null ? round($packageWholesale / $inside, 2) : '',
        'package_unit' => $receiveUnit,
        'package_quantity' => $packageQty,
        'units_per_package' => $inside > 0 ? $inside : 1,
        'package_price' => $packageWholesale,
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
            $pkg = store_package_fields($row, $units);
            if ($pkg['package_unit'] !== null) {
                if (($pkg['package_buying_price'] ?? 0) <= 0) {
                    $error = 'Enter the package buying price for packaged products.';
                    break;
                }
                if (($pkg['package_price'] ?? 0) <= 0) {
                    $error = 'Enter the package wholesale price for packaged products.';
                    break;
                }
            }
            $qty = (float) $pkg['quantity'];
            if ($qty <= 0) {
                continue;
            }
            $productId = (int) ($row['product_choice'] ?? 0);
            $existing = $productId > 0 ? $P->find($productId) : null;
            if ($existing && (int) $existing['tenant_id'] !== (int) TenantContext::tenantId()) {
                $existing = null;
                $productId = 0;
            }
            $name = trim((string) ($row['title'] ?? ''));
            if ($existing) {
                $name = $existing['name'];
            }
            if ($name === '') {
                continue;
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
                'unit' => $pkg['unit'],
                'package_unit' => $pkg['package_unit'],
                'package_quantity' => $pkg['package_quantity'],
                'units_per_package' => $pkg['units_per_package'],
                'package_price' => $pkg['package_price'],
                'colors' => '',
                'quantity' => $qty,
                'faulty_quantity' => $pkg['faulty_quantity'],
                'buying_price' => ($row['buying_price'] ?? '') !== '' ? $pkg['buying_price'] : ($existing['buying_price'] ?? 0),
                'package_buying_price' => ($row['buying_price'] ?? '') !== '' ? $pkg['package_buying_price'] : ($existing['package_buying_price'] ?? null),
                'retail_price' => ($row['selling_price'] ?? '') !== '' ? $row['selling_price'] : ($existing['retail_price'] ?? $existing['selling_price'] ?? 0),
                'wholesale_price' => ($row['wholesale_price'] ?? '') !== '' ? $pkg['wholesale_price'] : ($existing['wholesale_price'] ?? $existing['selling_price'] ?? 0),
                'offer_price' => $existing ? '' : ($row['offer_price'] ?? ''),
                'offer_starts_at' => $existing ? '' : ($row['offer_starts_at'] ?? ''),
                'offer_ends_at' => $existing ? '' : ($row['offer_ends_at'] ?? ''),
                'image_path' => $imgPath,
                'notes' => trim((string) ($row['remark'] ?? '')) ?: $batchNotes,
            ];
        }
        if (!$error) {
            $res = $SP->createMany($items, TenantContext::userId());
            if ($res['ok']) {
                $_SESSION['flash']['success'] = $res['created'] . ' product' . ($res['created'] === 1 ? '' : 's') . ' stored. Generate an invoice when you want to move them into inventory.';
                header('Location: ' . public_url('super/store/'));
                exit;
            }
            $error = $res['error'] ?? 'Could not store products.';
        }
    } elseif ($action === 'invoice') {
        $ids = $_POST['store_ids'] ?? [];
        $ids = is_array($ids) ? $ids : [];
        $res = $SP->generateInvoice($ids, $_POST['invoice_to'] ?? '', $_POST['notes'] ?? '', TenantContext::userId(), $_POST['transfer_qty'] ?? []);
        if ($res['ok']) {
            $_SESSION['flash']['success'] = 'Store invoice ' . $res['invoice_number'] . ' generated. Products moved to normal inventory.';
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
            $_SESSION['flash']['success'] = 'Store invoice deleted and products returned to store.';
            header('Location: ' . public_url('super/store/'));
            exit;
        }
        $error = $res['error'] ?? 'Could not delete store invoice.';
    }
}

$pending = $SP->pending();
$invoices = $SP->invoices(30);
$page_title = 'Store';
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <div>
    <h1 class="h5 fw-bold mb-1">Store products</h1>
    <p class="text-muted small mb-0">Record bulk products into store first. Store invoices later transfer them to normal inventory for selling.</p>
  </div>
  <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url('super/inventory/'); ?>"><i class="fas fa-warehouse me-1"></i>Inventory</a>
</div>

<form method="post" enctype="multipart/form-data" id="storeForm" novalidate>
  <input type="hidden" name="action" value="store">
  <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
      <h2 class="h5 mb-3">This store batch</h2>
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
        <h2 class="h5 mb-0">Products received into store</h2>
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

  <button class="btn btn-primary btn-lg mb-4"><i class="fas fa-box-archive me-1"></i>Save products to store</button>
</form>

<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;overflow:hidden;">
  <div class="px-4 py-3 border-bottom bg-white">
    <h2 class="h6 fw-bold mb-0">Stored products waiting for invoice</h2>
  </div>
  <?php if (!$pending): ?>
    <div class="p-4 text-muted small">No products in store right now.</div>
  <?php else: ?>
  <form method="post">
    <input type="hidden" name="action" value="invoice">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr class="text-muted small text-uppercase"><th></th><th>Product</th><th>Supplier</th><th>Category</th><th>Brand</th><th class="text-end">Available</th><th style="width:120px;">Invoice qty</th><th class="text-end">Cost</th><th class="text-end">Line</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($pending as $p): $line = (float) $p['quantity'] * (float) $p['buying_price']; ?>
          <tr>
            <td><input class="form-check-input store-check" type="checkbox" name="store_ids[]" value="<?php echo (int) $p['id']; ?>" data-line="<?php echo $line; ?>"></td>
            <td>
              <div class="fw-semibold"><?php echo htmlspecialchars($p['name']); ?></div>
              <div class="text-muted small"><?php echo htmlspecialchars($p['barcode'] ?: 'No barcode'); ?><?php if (!empty($p['product_id'])): ?> · matched inventory<?php endif; ?><?php if (!empty($p['package_unit'])): ?> · <?php echo rtrim(rtrim(number_format((float) ($p['package_quantity'] ?? 0), 2), '0'), '.'); ?> <?php echo htmlspecialchars($p['package_unit']); ?><?php endif; ?></div>
            </td>
            <td class="small"><?php echo htmlspecialchars($p['supplier_name'] ?: '—'); ?></td>
            <td class="small"><?php echo htmlspecialchars($p['category_name'] ?: '—'); ?></td>
            <td class="small"><?php echo htmlspecialchars($p['brand_name'] ?: '—'); ?></td>
            <td class="text-end"><?php echo rtrim(rtrim(number_format((float) $p['quantity'], 2), '0'), '.'); ?> <?php echo htmlspecialchars($p['unit']); ?></td>
            <td><input type="number" step="0.01" min="0" max="<?php echo htmlspecialchars((string) $p['quantity']); ?>" name="transfer_qty[<?php echo (int) $p['id']; ?>]" class="form-control form-control-sm transfer-qty" value="<?php echo htmlspecialchars((string) rtrim(rtrim(number_format((float) $p['quantity'], 2, '.', ''), '0'), '.')); ?>" data-price="<?php echo (float) $p['buying_price']; ?>" data-id="<?php echo (int) $p['id']; ?>"></td>
            <td class="text-end">KES <?php echo number_format((float) $p['buying_price'], 2); ?></td>
            <td class="text-end fw-semibold">KES <?php echo number_format($line, 2); ?></td>
            <td class="text-end store-actions">
              <button type="button" class="btn btn-sm btn-outline-secondary edit-store"
                data-id="<?php echo (int) $p['id']; ?>"
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
                data-quantity="<?php echo htmlspecialchars((string) $p['quantity']); ?>"
                data-faulty="<?php echo htmlspecialchars((string) ($p['faulty_quantity'] ?? 0)); ?>"
                data-buying="<?php echo htmlspecialchars((string) $p['buying_price']); ?>"
                data-retail="<?php echo htmlspecialchars((string) $p['retail_price']); ?>"
                data-wholesale="<?php echo htmlspecialchars((string) $p['wholesale_price']); ?>"
                data-notes="<?php echo htmlspecialchars($p['notes'] ?? ''); ?>">Edit</button>
              <form method="post" class="d-inline" onsubmit="return confirm('Delete this stored product?');">
                <input type="hidden" name="action" value="delete_store_product">
                <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                <button class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="p-3 border-top bg-light">
      <div class="row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label small mb-1">Invoice to</label><input name="invoice_to" class="form-control form-control-sm" placeholder="Supplier / store name"></div>
        <div class="col-md-4"><label class="form-label small mb-1">Notes</label><input name="notes" class="form-control form-control-sm" placeholder="Optional"></div>
        <div class="col-md-2 fw-bold">Total: <span id="selectedTotal">KES 0</span></div>
        <div class="col-md-2"><button class="btn btn-primary btn-sm w-100" id="invoiceBtn" disabled>Generate invoice</button></div>
      </div>
    </div>
  </form>
  <?php endif; ?>
</div>

<div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
  <div class="px-4 py-3 border-bottom bg-white"><h2 class="h6 fw-bold mb-0">Store invoices</h2></div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr class="text-muted small text-uppercase"><th>Invoice</th><th>To</th><th>Items</th><th>When</th><th class="text-end">Total</th><th></th></tr></thead>
      <tbody>
        <?php if (!$invoices): ?><tr><td colspan="6" class="text-center text-muted py-4">No store invoices yet.</td></tr><?php endif; ?>
        <?php foreach ($invoices as $inv): ?>
        <tr>
          <td class="fw-semibold"><?php echo htmlspecialchars($inv['invoice_number']); ?></td>
          <td><?php echo htmlspecialchars($inv['invoice_to'] ?: '—'); ?></td>
          <td><?php echo (int) $inv['item_count']; ?></td>
          <td class="small text-muted"><?php echo date('j M Y, g:i a', strtotime($inv['created_at'])); ?></td>
          <td class="text-end fw-semibold">KES <?php echo number_format((float) $inv['total'], 2); ?></td>
          <td class="text-end store-actions">
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url('super/store/invoice.php?id=' . (int) $inv['id']); ?>">Print</a>
            <form method="post" class="d-inline" onsubmit="return confirm('Delete this store invoice and reverse its stock transfer?');">
              <input type="hidden" name="action" value="delete_store_invoice">
              <input type="hidden" name="invoice_id" value="<?php echo (int) $inv['id']; ?>">
              <button class="btn btn-sm btn-outline-danger">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

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
            <div class="col-md-3"><label class="form-label small">Package price</label><input type="number" step="0.01" min="0" name="package_price" id="editPackagePrice" class="form-control"></div>
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
        <label class="form-label small mb-1">Received as</label>
        <select name="items[__I__][unit]" class="form-select form-select-sm unitSelect">
          <?php foreach ($units as $u): ?>
            <option value="<?php echo htmlspecialchars($u); ?>"><?php echo htmlspecialchars($u); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 mt-2 packageFields" style="display:none;">
        <div class="border rounded p-2" style="border-color:#e2e8f0!important;">
          <div class="small fw-semibold mb-2"><i class="fas fa-boxes-stacked me-1 text-primary"></i>Package contents</div>
          <div class="row g-2">
            <div class="col-6 col-sm-3">
              <label class="form-label small mb-1 packageQtyLabel">Number of packages</label>
              <input type="number" step="0.01" min="0" name="items[__I__][package_quantity]" class="form-control form-control-sm packageQty" placeholder="0">
            </div>
            <div class="col-6 col-sm-3">
              <label class="form-label small mb-1 unitsPerPackageLabel">Items inside each package</label>
              <input type="number" step="0.01" min="0" name="items[__I__][units_per_package]" class="form-control form-control-sm unitsPerPackage" placeholder="0">
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
        <label class="form-label small mb-1 qtyLabel">Good qty received</label>
        <input type="number" step="0.01" min="0" name="items[__I__][quantity]" class="form-control form-control-sm qty" placeholder="0">
      </div>
      <div class="col-6 col-sm-3 mt-2">
        <label class="form-label small mb-1">Faulty / broken</label>
        <input type="number" step="0.01" min="0" name="items[__I__][faulty_quantity]" class="form-control form-control-sm" placeholder="0">
      </div>
      <div class="col-6 col-sm-3 mt-2">
        <label class="form-label small mb-1 buyingLabel">Buying price of the package</label>
        <input type="number" step="0.01" min="0" name="items[__I__][buying_price]" class="form-control form-control-sm buyingPrice" placeholder="0">
      </div>
      <div class="col-6 col-sm-3 mt-2 newProductFields">
        <label class="form-label small mb-1 retailLabel">Selling price of items inside (retail price)</label>
        <input type="number" step="0.01" min="0" name="items[__I__][selling_price]" class="form-control form-control-sm retailPrice" placeholder="0">
      </div>
      <div class="col-6 col-sm-3 mt-2 newProductFields">
        <label class="form-label small mb-1 wholesaleLabel">Selling price of the package (wholesale price)</label>
        <input type="number" step="0.01" min="0" name="items[__I__][wholesale_price]" class="form-control form-control-sm wholesalePrice" placeholder="0">
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
      row.querySelector('.qtyLabel').textContent = 'Qty to store';
      var bits = [item.category_name || item.subject_name, item.brand_name || item.publisher_name, item.unit].filter(Boolean);
      note.style.display = 'block';
      note.innerHTML = '<i class="fas fa-circle-check me-1"></i>Already in inventory' +
        (bits.length ? ' — ' + bits.join(' · ') : '') +
        '. Current balance: <strong>' + (item.balance != null ? item.balance : '') + '</strong>. It will transfer after invoice.';
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

  function refreshSelectedTotal(){
    var total = 0, checked = 0;
    document.querySelectorAll('.store-check').forEach(function(c){
      if(c.checked){
        checked++;
        var qty = document.querySelector('.transfer-qty[data-id="' + c.value + '"]');
        var q = qty ? (parseFloat(qty.value) || 0) : 0;
        var price = qty ? (parseFloat(qty.dataset.price) || 0) : 0;
        total += q * price;
      }
    });
    var out = document.getElementById('selectedTotal'); if(out) out.textContent = money(total);
    var btn = document.getElementById('invoiceBtn'); if(btn) btn.disabled = checked === 0;
  }

  document.getElementById('addRowBtn').addEventListener('click', addRow);
  addRow();
  var supplierInput = document.querySelector('.ta-input[data-field="supplier"]');
  if (supplierInput) attachTypeahead(supplierInput, 'supplier');
  document.querySelectorAll('.store-check').forEach(function(c){ c.addEventListener('change', refreshSelectedTotal); });
  document.querySelectorAll('.transfer-qty').forEach(function(q){ q.addEventListener('input', refreshSelectedTotal); });
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
      document.getElementById('editQuantity').value = btn.dataset.quantity || '0';
      document.getElementById('editFaulty').value = btn.dataset.faulty || '0';
      document.getElementById('editBuying').value = btn.dataset.buying || '0';
      document.getElementById('editRetail').value = btn.dataset.retail || '0';
      document.getElementById('editWholesale').value = btn.dataset.wholesale || '0';
      document.getElementById('editNotes').value = btn.dataset.notes || '';
      new bootstrap.Modal(document.getElementById('editStoreModal')).show();
    });
  });
  refreshSelectedTotal();
})();
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
