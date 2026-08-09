<?php
// public/super/products/index.php — edit a single product.
// This page no longer creates products: new stock is only added on
// super/stock/new.php. Browsing/toggling/deleting products lives on
// super/inventory/ (grouped by supplier); its Edit buttons land here.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::INVENTORY_EDIT);

$pdo = Database::pdo();
$C = new Models\CategoryModel($pdo);
$P = new Models\ProductModel($pdo);
$AL = new Models\AuditLogModel($pdo);
$SUP = new Models\SupplierModel($pdo);
$BA = new Models\BookAttributeModel($pdo);

$base = public_url('super/products/');
$inventoryUrl = public_url('super/inventory/');
$apiBase = public_url('api/inventory/');

// id => name maps so the activity log shows names, not raw ids.
$catName = []; foreach ($C->all([], 'name ASC') as $c) { $catName[(int)$c['id']] = $c['name']; }
$attrNames = ['grade' => [], 'publisher' => [], 'author' => [], 'edition' => []];
foreach (array_keys($attrNames) as $type) {
    foreach ($BA->all(['type' => $type]) as $a) { $attrNames[$type][(int) $a['id']] = $a['name']; }
}

$editId  = (int) ($_GET['edit'] ?? $_POST['id'] ?? 0);
$editRow = $editId > 0 ? $P->findWithMeta($editId) : null;

// This page only edits an existing product — nothing to do without one.
if (!$editRow) {
    header('Location: ' . $inventoryUrl);
    exit;
}

$errors = [];
$old = [];

/** Turn a product-shaped row into label-friendly values for the audit diff. */
function audit_product_view(array $r, array $catName, array $attrNames): array
{
    $cid = (int) ($r['category_id'] ?? 0);
    $idName = function (string $type, $id) use ($attrNames) {
        $id = (int) $id;
        return $id > 0 ? ($attrNames[$type][$id] ?? ('#' . $id)) : null;
    };
    return [
        'name'                => $r['name'] ?? null,
        'category_id'         => $cid > 0 ? ($catName[$cid] ?? ('#' . $cid)) : null,
        'grade_id'            => $idName('grade', $r['grade_id'] ?? 0),
        'publisher_id'        => $idName('publisher', $r['publisher_id'] ?? 0),
        'author_id'           => $idName('author', $r['author_id'] ?? 0),
        'edition_id'          => $idName('edition', $r['edition_id'] ?? 0),
        'barcode'             => ($r['barcode'] ?? '') !== '' ? $r['barcode'] : null,
        'description'         => ($r['description'] ?? '') !== '' ? $r['description'] : null,
        'quantity'            => $r['quantity'] ?? null,
        'unit'                => $r['unit'] ?? null,
        'buying_price'        => $r['buying_price'] ?? null,
        'wholesale_price'     => $r['wholesale_price'] ?? null,
        'retail_price'        => $r['retail_price'] ?? null,
        'offer_price'         => ($r['offer_price'] ?? '') !== '' && $r['offer_price'] !== null ? $r['offer_price'] : null,
        'offer_ends_at'       => $r['offer_ends_at'] ?? null,
        'low_stock_threshold' => $r['low_stock_threshold'] ?? null,
        'status'              => $r['status'] ?? null,
        'image_path'          => ($r['image_path'] ?? '') !== '' ? 'Photo' : null,
    ];
}

/** Validate + store an uploaded product image. Returns ['ok','path'|'error','skip']. */
function product_handle_image(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || ($file['name'] ?? '') === '') {
        return ['ok' => true, 'path' => null, 'skip' => true];
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplierName = trim($_POST['supplier'] ?? '');
    // This page's form only ever edits the book fields — product_type isn't
    // a form field, so keep whatever the row already was (never silently
    // flip a stationery item back to 'book' just by saving an edit).
    $productType = in_array($editRow['product_type'] ?? 'book', ['book', 'stationery'], true) ? $editRow['product_type'] : 'book';
    $in = [
        'product_type'        => $productType,
        'name'                => trim($_POST['name'] ?? ''),
        'category_id'         => (int) $C->findOrCreate($_POST['subject'] ?? '', $productType === 'stationery' ? 'stationery' : 'subject'),
        'grade_id'            => (int) $BA->findOrCreate('grade', $_POST['grade'] ?? ''),
        'publisher_id'        => (int) $BA->findOrCreate('publisher', $_POST['publisher'] ?? ''),
        'author_id'           => (int) $BA->findOrCreate('author', $_POST['author'] ?? ''),
        'edition_id'          => (int) $BA->findOrCreate('edition', $_POST['edition'] ?? ''),
        // This form has no Brand/Colors/Variants fields (those live on the
        // Stationery pages) — preserve them as-is rather than wiping a
        // stationery item's brand/colors/variants just by saving here.
        'brand_id'            => (int) ($editRow['brand_id'] ?? 0),
        'colors'              => $editRow['colors'] ? (json_decode($editRow['colors'], true) ?: []) : [],
        'sizes'               => $editRow['sizes'] ? (json_decode($editRow['sizes'], true) ?: []) : [],
        'barcode'             => trim($_POST['barcode'] ?? ''),
        'supplier_id'         => $supplierName !== '' ? (int) $SUP->findOrCreate($supplierName) : 0,
        'description'         => trim($_POST['description'] ?? ''),
        'quantity'            => $_POST['quantity'] ?? '',
        'unit'                => $_POST['unit'] ?? 'piece',
        'buying_price'        => $_POST['buying_price'] ?? '',
        'wholesale_price'     => $_POST['wholesale_price'] ?? '',
        'retail_price'        => $_POST['retail_price'] ?? '',
        'offer_price'         => $_POST['offer_price'] ?? '',
        'offer_starts_at'     => $_POST['offer_starts_at'] ?? '',
        'offer_ends_at'       => $_POST['offer_ends_at'] ?? '',
        'low_stock_threshold' => (int) ($_POST['low_stock_threshold'] ?? 10),
        // Draft always wins; otherwise keep an archived book archived instead
        // of silently reactivating it just because its price was edited.
        'status'              => ($_POST['action'] ?? '') === 'draft' ? 'draft' : ($editRow['status'] === 'archived' ? 'archived' : 'active'),
    ];
    $old = $in;
    $old['subject'] = $_POST['subject'] ?? ''; $old['grade'] = $_POST['grade'] ?? '';
    $old['publisher'] = $_POST['publisher'] ?? ''; $old['author'] = $_POST['author'] ?? '';
    $old['edition'] = $_POST['edition'] ?? ''; $old['supplier'] = $supplierName;

    // Image: keep existing unless a new one is uploaded.
    $img = product_handle_image($_FILES['image'] ?? []);
    if (!$img['ok']) {
        $errors['image'] = $img['error'];
    } else {
        $in['image_path'] = empty($img['skip']) ? $img['path'] : ($editRow['image_path'] ?? null);
    }

    if (!$errors) {
        $beforeRow = $editRow;
        $res = $P->edit($editId, $in);
        if ($res['ok']) {
            try {
                $before = audit_product_view($beforeRow, $catName, $attrNames);
                $after  = audit_product_view($in, $catName, $attrNames);
                $changes = Models\AuditLogModel::diff($before, $after, Models\AuditLogModel::PRODUCT_FIELDS);
                if ($changes) {
                    $AL->record('product', $editId, $in['name'], 'updated', $changes);
                }
            } catch (\Throwable $e) { /* logging must never block the save */ }

            $_SESSION['flash']['success'] = 'Product updated.';
            header('Location: ' . $inventoryUrl); exit;
        }
        $errors = $res['errors'];
    }
    $old['image_path'] = $editRow['image_path'] ?? null;
}

// Prefill values (edit row, or repopulated $old after a failed submit)
$val = function (string $k, $default = '') use ($editRow, $old) {
    if (!empty($old)) { return $old[$k] ?? $default; }
    return $editRow[$k] ?? $default;
};
// The free-text type-ahead fields prefill from the joined *_name columns
// (findWithMeta), or from what was typed on a failed resubmit.
$textVal = function (string $formKey, string $joinedNameKey) use ($editRow, $old) {
    if (!empty($old)) { return $old[$formKey] ?? ''; }
    return $editRow[$joinedNameKey] ?? '';
};
$curImage  = $editRow['image_path'] ?? ($old['image_path'] ?? null);
/** MySQL/`strtotime`-parseable datetime -> the `datetime-local` input format. */
$dtLocal = function ($v): string {
    if (!$v) { return ''; }
    $t = strtotime((string) $v);
    return $t ? date('Y-m-d\TH:i', $t) : '';
};
$onOffer = !empty($editRow['offer_price']) && !empty($editRow['offer_ends_at']) && strtotime($editRow['offer_ends_at']) > time();

$activity = $AL->recent('product', 20);
$page_title = 'Edit product';

ob_start();
$unitLabels = ['piece' => 'Piece(s)', 'g' => 'Grams (g)', 'kg' => 'Kilograms (kg)', 'tonne' => 'Tonnes', 'ml' => 'Millilitres (ml)', 'litre' => 'Litres'];

$actionBadge = function (string $a): string {
    $map = [
        'created'   => ['Created', 'bg-success'],
        'updated'   => ['Updated', 'bg-primary'],
        'activated' => ['Activated', 'bg-success'],
        'drafted'   => ['Set to draft', 'bg-secondary'],
        'deleted'   => ['Deleted', 'bg-danger'],
    ];
    [$label, $cls] = $map[$a] ?? [ucfirst($a), 'bg-secondary'];
    return '<span class="badge ' . $cls . '">' . $label . '</span>';
};
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h1 class="h5 mb-0 fw-bold">Edit product</h1>
  <a class="btn btn-sm btn-outline-secondary" href="<?php echo $inventoryUrl; ?>"><i class="fas fa-arrow-left me-1"></i>Back to inventory</a>
</div>

<div class="row g-4">
  <div class="col-12 col-lg-6">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <p class="text-muted small mb-3">Stock value = buying price &times; quantity. To add new stock or a new product, use <a href="<?php echo public_url('super/stock/new.php'); ?>">Record stock</a>.</p>

        <?php if (!empty($errors['_'])): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($errors['_']); ?></div><?php endif; ?>
        <?php if (!empty($errors['image'])): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($errors['image']); ?></div><?php endif; ?>

        <form method="post" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="id" value="<?php echo (int)$editRow['id']; ?>">

          <div class="mb-3">
            <label class="form-label">Book title</label>
            <input name="name" class="form-control" value="<?php echo htmlspecialchars($val('name')); ?>" placeholder="e.g. Growing in Christ" required>
            <?php if (!empty($errors['name'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['name']); ?></small><?php endif; ?>
          </div>

          <div class="row g-2">
            <div class="col-6 mb-3">
              <label class="form-label">Subject <span class="text-muted">(optional)</span></label>
              <div class="ta-wrap">
                <input type="text" name="subject" class="form-control ta-input" data-field="subject" value="<?php echo htmlspecialchars($textVal('subject', 'category_name')); ?>" placeholder="e.g. Mathematics" autocomplete="off">
                <div class="ta-menu"></div>
              </div>
              <?php if (!empty($errors['category_id'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['category_id']); ?></small><?php endif; ?>
            </div>
            <div class="col-6 mb-3">
              <label class="form-label">Grade/Class <span class="text-muted">(optional)</span></label>
              <div class="ta-wrap">
                <input type="text" name="grade" class="form-control ta-input" data-field="grade" value="<?php echo htmlspecialchars($textVal('grade', 'grade_name')); ?>" placeholder="e.g. Grade Three" autocomplete="off">
                <div class="ta-menu"></div>
              </div>
            </div>
          </div>

          <div class="row g-2">
            <div class="col-6 mb-3">
              <label class="form-label">Publisher <span class="text-muted">(optional)</span></label>
              <div class="ta-wrap">
                <input type="text" name="publisher" class="form-control ta-input" data-field="publisher" value="<?php echo htmlspecialchars($textVal('publisher', 'publisher_name')); ?>" placeholder="e.g. Longhorn" autocomplete="off">
                <div class="ta-menu"></div>
              </div>
            </div>
            <div class="col-6 mb-3">
              <label class="form-label">Author <span class="text-muted">(optional)</span></label>
              <div class="ta-wrap">
                <input type="text" name="author" class="form-control ta-input" data-field="author" value="<?php echo htmlspecialchars($textVal('author', 'author_name')); ?>" placeholder="e.g. Kefa Masita" autocomplete="off">
                <div class="ta-menu"></div>
              </div>
            </div>
          </div>

          <div class="row g-2">
            <div class="col-6 mb-3">
              <label class="form-label">Edition <span class="text-muted">(optional)</span></label>
              <div class="ta-wrap">
                <input type="text" name="edition" class="form-control ta-input" data-field="edition" value="<?php echo htmlspecialchars($textVal('edition', 'edition_name')); ?>" placeholder="e.g. First" autocomplete="off">
                <div class="ta-menu"></div>
              </div>
            </div>
            <div class="col-6 mb-3">
              <label class="form-label">Supplier <span class="text-muted">(optional)</span></label>
              <div class="ta-wrap">
                <input type="text" name="supplier" class="form-control ta-input" data-field="supplier" value="<?php echo htmlspecialchars($textVal('supplier', 'supplier_name')); ?>" placeholder="e.g. Longhorn Distributors" autocomplete="off">
                <div class="ta-menu"></div>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label"><i class="fas fa-barcode me-1"></i>Barcode <span class="text-muted">(optional — scan it, or generate one)</span></label>
            <div class="d-flex gap-2">
              <input name="barcode" id="barcodeField" class="form-control" value="<?php echo htmlspecialchars($val('barcode')); ?>" placeholder="Scan or type a barcode" autocomplete="off">
              <button type="button" class="btn btn-outline-secondary text-nowrap" id="genBarcodeBtn">Generate</button>
            </div>
            <?php if (!empty($errors['barcode'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['barcode']); ?></small><?php endif; ?>
          </div>

          <div class="mb-3">
            <label class="form-label">Description <span class="text-muted">(optional)</span></label>
            <textarea name="description" class="form-control" rows="2" placeholder="Short note about the book"><?php echo htmlspecialchars($val('description')); ?></textarea>
          </div>

          <div class="row g-2">
            <div class="col-6 mb-3">
              <label class="form-label">Balance (current stock)</label>
              <input name="quantity" id="qtyP" type="number" step="0.01" min="0" class="form-control" value="<?php echo htmlspecialchars($val('quantity')); ?>" placeholder="0">
              <?php if (!empty($errors['quantity'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['quantity']); ?></small><?php endif; ?>
            </div>
            <div class="col-6 mb-3">
              <label class="form-label">Unit</label>
              <select name="unit" class="form-select">
                <?php foreach ($unitLabels as $u => $lbl): ?>
                  <option value="<?php echo $u; ?>" <?php echo ((string)$val('unit', 'piece') === $u) ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="row g-2">
            <div class="col-4 mb-3">
              <label class="form-label">Unit price — cost (KES)</label>
              <input name="buying_price" id="buyP" type="number" step="0.01" min="0" class="form-control" value="<?php echo htmlspecialchars($val('buying_price')); ?>" placeholder="0">
              <?php if (!empty($errors['buying_price'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['buying_price']); ?></small><?php endif; ?>
            </div>
            <div class="col-4 mb-3">
              <label class="form-label">Wholesale (KES)</label>
              <input name="wholesale_price" id="wholeP" type="number" step="0.01" min="0" class="form-control" value="<?php echo htmlspecialchars($val('wholesale_price', $val('selling_price'))); ?>" placeholder="0">
              <?php if (!empty($errors['wholesale_price'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['wholesale_price']); ?></small><?php endif; ?>
            </div>
            <div class="col-4 mb-3">
              <label class="form-label">Selling price (KES)</label>
              <input name="retail_price" id="retailP" type="number" step="0.01" min="0" class="form-control" value="<?php echo htmlspecialchars($val('retail_price', $val('selling_price'))); ?>" placeholder="0">
              <?php if (!empty($errors['retail_price'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['retail_price']); ?></small><?php endif; ?>
            </div>
          </div>

          <div class="border rounded p-3 mb-3" style="border-color:#e2e8f0!important;">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="offerToggle" <?php echo ($val('offer_price') !== '' && $val('offer_price') !== null) ? 'checked' : ''; ?>>
              <label class="form-check-label fw-semibold" for="offerToggle"><i class="fas fa-tag me-1 text-warning"></i>On offer<?php echo $onOffer ? ' <span class="badge bg-warning text-dark">live now</span>' : ''; ?></label>
            </div>
            <div class="row g-2 mt-1" id="offerFields" style="<?php echo ($val('offer_price') !== '' && $val('offer_price') !== null) ? '' : 'display:none;'; ?>">
              <div class="col-4">
                <label class="form-label small mb-1">Offer price (KES)</label>
                <input name="offer_price" type="number" step="0.01" min="0" class="form-control form-control-sm" value="<?php echo htmlspecialchars($val('offer_price')); ?>" placeholder="0">
                <?php if (!empty($errors['offer_price'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['offer_price']); ?></small><?php endif; ?>
              </div>
              <div class="col-4">
                <label class="form-label small mb-1">Starts <span class="text-muted">(optional)</span></label>
                <input name="offer_starts_at" type="datetime-local" class="form-control form-control-sm" value="<?php echo htmlspecialchars($dtLocal($val('offer_starts_at'))); ?>">
                <?php if (!empty($errors['offer_starts_at'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['offer_starts_at']); ?></small><?php endif; ?>
              </div>
              <div class="col-4">
                <label class="form-label small mb-1">Ends</label>
                <input name="offer_ends_at" type="datetime-local" class="form-control form-control-sm" value="<?php echo htmlspecialchars($dtLocal($val('offer_ends_at'))); ?>">
                <?php if (!empty($errors['offer_ends_at'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['offer_ends_at']); ?></small><?php endif; ?>
              </div>
            </div>
            <small class="text-muted d-block mt-1">Leave the offer price empty to end/clear the offer. The price reverts on its own once "Ends" passes.</small>
          </div>

          <div id="stockValueBox" class="alert alert-light border py-2 small mb-2" style="display:none;"></div>
          <div id="profitBox" class="alert alert-secondary py-2 small mb-3" style="display:none;"></div>

          <div class="mb-3">
            <label class="form-label">Restock alert when stock reaches</label>
            <input name="low_stock_threshold" type="number" min="0" class="form-control" value="<?php echo htmlspecialchars($val('low_stock_threshold', 10)); ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Cover photo <span class="text-muted">(optional)</span></label>
            <?php if ($curImage): ?>
              <div class="mb-2"><img src="<?php echo htmlspecialchars($curImage); ?>" alt="" style="height:54px;border-radius:8px;border:1px solid #e2e8f0;"></div>
            <?php endif; ?>
            <input name="image" type="file" accept="image/*" class="form-control">
            <small class="text-muted">JPG, PNG, WEBP or GIF, under 3 MB.<?php echo $curImage ? ' Leave empty to keep the current image.' : ''; ?></small>
          </div>

          <button class="btn btn-primary" name="action" value="save">Save book</button>
          <button class="btn btn-outline-secondary" name="action" value="draft">Save as draft</button>
          <a class="btn btn-link" href="<?php echo $inventoryUrl; ?>">Cancel</a>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h6 mb-3"><i class="fas fa-clock-rotate-left me-2 text-primary"></i>Recent activity</h2>
        <?php if (!$activity): ?>
          <div class="text-muted small">No changes recorded yet.</div>
        <?php else: ?>
          <div class="timeline">
            <?php foreach ($activity as $a):
                $when = date('j M Y, g:i a', strtotime($a['created_at']));
                $who  = $a['username'] ?: 'Unknown';
                $ch   = $a['changes'] ? json_decode($a['changes'], true) : [];
                if (!is_array($ch)) { $ch = []; }
            ?>
            <div class="d-flex gap-3 py-2" style="border-top:1px solid #f1f5f9;">
              <div class="text-muted small text-nowrap" style="min-width:110px;"><?php echo htmlspecialchars($when); ?></div>
              <div class="flex-grow-1">
                <div class="mb-1">
                  <?php echo $actionBadge($a['action']); ?>
                  <span class="fw-semibold ms-1"><?php echo htmlspecialchars($a['entity_label'] ?: ('#' . (int)$a['entity_id'])); ?></span>
                  <span class="text-muted small">by <?php echo htmlspecialchars($who); ?></span>
                </div>
                <?php if ($ch): ?>
                  <div class="small">
                    <?php foreach ($ch as $c): ?>
                      <span class="d-inline-block me-2 mb-1" style="background:#f8fafc;border:1px solid #eef2f7;border-radius:6px;padding:2px 8px;">
                        <span class="text-muted"><?php echo htmlspecialchars($c['label'] ?? $c['field'] ?? ''); ?>:</span>
                        <span><?php echo htmlspecialchars((string)($c['from'] ?? '—')); ?></span>
                        <i class="fas fa-arrow-right-long text-muted mx-1" style="font-size:.7rem;"></i>
                        <span class="fw-semibold"><?php echo htmlspecialchars((string)($c['to'] ?? '—')); ?></span>
                      </span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

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
    padding: .4rem .65rem; font-size: .85rem; cursor: pointer;
  }
  .ta-menu button:hover, .ta-menu button.active { background: #f1f5f9; }
</style>
<script>
  // Free-text type-ahead for Subject/Grade/Publisher/Author/Edition/Supplier —
  // suggests existing values as you type; an exact match on save reuses it,
  // anything new is created automatically (no dropdown pickers).
  (function () {
    var API = <?php echo json_encode($apiBase); ?>;
    document.querySelectorAll('.ta-input').forEach(function (input) {
      var field = input.dataset.field;
      var wrap = input.closest('.ta-wrap');
      var menu = wrap.querySelector('.ta-menu');
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
    });
  })();

  // Live stock value + profit readout
  var buyP = document.getElementById('buyP'), wholeP = document.getElementById('wholeP'),
      retailP = document.getElementById('retailP'), qtyP = document.getElementById('qtyP'),
      box = document.getElementById('profitBox'), stockBox = document.getElementById('stockValueBox');
  function calcProfit() {
    var b = parseFloat(buyP.value), w = parseFloat(wholeP.value), r = parseFloat(retailP.value), q = parseFloat(qtyP.value) || 0;
    if (isNaN(b)) { box.style.display = 'none'; stockBox.style.display = 'none'; return; }
    if (!isNaN(q) && q >= 0) {
      stockBox.style.display = 'block';
      stockBox.innerHTML = 'Stock value at cost: <strong>KES ' + (b * q).toFixed(0) + '</strong> (buying &times; quantity)';
    } else { stockBox.style.display = 'none'; }
    if (isNaN(w) && isNaN(r)) { box.style.display = 'none'; return; }
    var wProfit = isNaN(w) ? null : w - b;
    var rProfit = isNaN(r) ? null : r - b;
    box.style.display = 'block';
    var html = '';
    if (wProfit !== null) {
      var wMargin = w > 0 ? (wProfit / w * 100) : 0;
      html += '<div><strong>Wholesale</strong> profit/unit: KES ' + wProfit.toFixed(0) + ' &middot; margin ' + wMargin.toFixed(1) + '%';
      if (q > 0) html += ' &middot; total profit if all sold: KES ' + (wProfit * q).toFixed(0);
      html += '</div>';
    }
    if (rProfit !== null) {
      var rMargin = r > 0 ? (rProfit / r * 100) : 0;
      html += '<div class="mt-1"><strong>Retail</strong> profit/unit: KES ' + rProfit.toFixed(0) + ' &middot; margin ' + rMargin.toFixed(1) + '%';
      if (q > 0) html += ' &middot; total profit if all sold: KES ' + (rProfit * q).toFixed(0);
      html += '</div>';
    }
    box.className = 'alert py-2 small mb-3 ' + ((wProfit !== null && wProfit < 0) || (rProfit !== null && rProfit < 0) ? 'alert-danger' : 'alert-success');
    box.innerHTML = html;
  }
  [buyP, wholeP, retailP, qtyP].forEach(function(el){ if(el) el.addEventListener('input', calcProfit); });
  calcProfit();

  var offerToggle = document.getElementById('offerToggle'), offerFields = document.getElementById('offerFields');
  if (offerToggle) {
    offerToggle.addEventListener('change', function () {
      offerFields.style.display = offerToggle.checked ? 'flex' : 'none';
      var endsInput = offerFields.querySelector('[name="offer_ends_at"]');
      if (offerToggle.checked && !endsInput.value) {
        var d = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000);
        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        endsInput.value = d.toISOString().slice(0, 16);
      }
      if (!offerToggle.checked) { offerFields.querySelector('[name="offer_price"]').value = ''; }
    });
  }

  var genBtn = document.getElementById('genBarcodeBtn'), barcodeField = document.getElementById('barcodeField');
  if (genBtn) {
    genBtn.addEventListener('click', function () {
      if (barcodeField.value.trim()) { return; } // already has one — nothing to generate
      genBtn.disabled = true;
      var fd = new FormData();
      fd.append('id', <?php echo (int) $editRow['id']; ?>);
      fetch(API + 'generate_barcode.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          genBtn.disabled = false;
          if (data.ok) { barcodeField.value = data.barcode; }
          else { alert(data.error || 'Could not generate a barcode.'); }
        })
        .catch(function () { genBtn.disabled = false; });
    });
  }
</script>
<?php
$content = ob_get_clean();
$__layout = TenantContext::role() === 'staff' ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
