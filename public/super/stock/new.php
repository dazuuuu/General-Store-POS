<?php
// public/super/stock/new.php — bulk product intake for a general shop:
// product name, category, brand, colors, unit (kg/bale/carton…), good qty,
// faulty/broken qty, prices, barcode. Same UI card styles as before.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::STOCK_ENTER);

$pdo = Database::pdo();
$SUP = new Models\SupplierModel($pdo);
$C   = new Models\CategoryModel($pdo);
$BA  = new Models\BookAttributeModel($pdo);
$SI  = new Models\StockIntakeModel($pdo);

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

function stock_split_colors(string $csv): array
{
    return array_values(array_filter(array_map('trim', explode(',', $csv))));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplierName = trim($_POST['supplier'] ?? '');
    $supplierId = $supplierName !== '' ? (int) $SUP->findOrCreate($supplierName) : 0;
    $rows = $_POST['items'] ?? [];

    if (!$error) {
        $items = [];
        foreach ($rows as $i => $row) {
            $qty = (float) ($row['quantity'] ?? 0);
            $faulty = max(0, (float) ($row['faulty_quantity'] ?? 0));
            if ($qty <= 0 && $faulty <= 0) { continue; }
            if ($qty <= 0 && $faulty > 0) { $qty = 0; } // allow recording only damaged goods on restock path via remark
            if ($qty <= 0) { continue; } // sellable qty still required for new/restock stock bump

            $remark = trim($row['remark'] ?? '');
            $productChoice = trim($row['product_choice'] ?? '');
            $unit = in_array($row['unit'] ?? '', $units, true) ? $row['unit'] : 'piece';
            $colors = stock_split_colors($row['colors'] ?? '');

            if ($productChoice !== '') {
                $items[] = [
                    'mode'            => 'restock',
                    'product_id'      => (int) $productChoice,
                    'quantity'        => $qty,
                    'faulty_quantity' => $faulty,
                    'unit'            => $unit,
                    'colors'          => $colors,
                    'buying_price'    => (float) ($row['buying_price'] ?? 0),
                    'remark'          => $remark,
                ];
                continue;
            }

            $title = trim($row['title'] ?? '');
            if ($title === '') { continue; }

            $img = stock_handle_image(stock_row_image_file((int) $i));
            if (!$img['ok']) { $error = $title . ': ' . $img['error']; break; }

            $items[] = [
                'mode'            => 'new',
                'product_type'    => 'product',
                'name'            => $title,
                'category_id'     => (int) $C->findOrCreate($row['category'] ?? '', 'product'),
                'brand_id'        => (int) $BA->findOrCreate('brand', $row['brand'] ?? ''),
                'barcode'         => trim($row['barcode'] ?? ''),
                'unit'            => $unit,
                'colors'          => $colors,
                'quantity'        => $qty,
                'faulty_quantity' => $faulty,
                'buying_price'    => (float) ($row['buying_price'] ?? 0),
                'selling_price'   => $row['selling_price'] ?? 0,
                'wholesale_price' => $row['wholesale_price'] ?? '',
                'image_path'      => $img['path'] ?? '',
                'remark'          => $remark,
            ];
        }

        if (!$error && !$items) {
            $error = 'Add at least one product with a good (sellable) quantity.';
        }

        if (!$error) {
            $res = $SI->create([
                'supplier_id' => $supplierId,
                'staff_id'    => TenantContext::userId(),
                'notes'       => $_POST['notes'] ?? '',
            ], $items);
            if ($res['ok']) {
                $_SESSION['flash']['success'] = 'Stock recorded — ' . count($items) . ' product' . (count($items) === 1 ? '' : 's') . '.';
                header('Location: ' . public_url('super/inventory/'));
                exit;
            }
            $error = $res['errors']['_'] ?? (reset($res['errors']) ?: 'Could not record this delivery.');
        }
    }
}

$page_title = 'Record products in bulk';
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" id="stockForm" novalidate>
  <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
      <h2 class="h5 mb-3">This delivery</h2>
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
        <h2 class="h5 mb-0">Products received</h2>
        <button type="button" class="btn btn-sm btn-outline-primary" id="addRowBtn"><i class="fas fa-plus me-1"></i>Add another product</button>
      </div>
      <div id="rows"></div>
      <div class="d-flex justify-content-end pt-2 border-top mt-2">
        <div class="text-muted small">Grand total (good stock cost): <strong id="grandTotal">KES 0</strong></div>
      </div>
    </div>
  </div>

  <button class="btn btn-primary btn-lg"><i class="fas fa-boxes-stacked me-1"></i>Record products in bulk</button>
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
      <div class="col-6 col-sm-3 mt-2 newProductFields">
        <label class="form-label small mb-1">Colors <span class="text-muted">(comma-separated)</span></label>
        <input type="text" name="items[__I__][colors]" class="form-control form-control-sm" placeholder="e.g. Red, Blue, White">
      </div>
      <div class="col-6 col-sm-3 mt-2">
        <label class="form-label small mb-1">Unit</label>
        <select name="items[__I__][unit]" class="form-select form-select-sm unitSelect">
          <?php foreach ($units as $u): ?>
            <option value="<?php echo htmlspecialchars($u); ?>"><?php echo htmlspecialchars($u); ?></option>
          <?php endforeach; ?>
        </select>
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
        <label class="form-label small mb-1">Buying price (KES)</label>
        <input type="number" step="0.01" min="0" name="items[__I__][buying_price]" class="form-control form-control-sm buyingPrice" placeholder="0">
      </div>
      <div class="col-6 col-sm-3 mt-2 newProductFields">
        <label class="form-label small mb-1">Selling price</label>
        <input type="number" step="0.01" min="0" name="items[__I__][selling_price]" class="form-control form-control-sm" placeholder="0">
      </div>
      <div class="col-6 col-sm-3 mt-2 newProductFields">
        <label class="form-label small mb-1">Wholesale <span class="text-muted">(optional)</span></label>
        <input type="number" step="0.01" min="0" name="items[__I__][wholesale_price]" class="form-control form-control-sm" placeholder="0">
      </div>

      <div class="col-6 col-sm-4 mt-2">
        <label class="form-label small mb-1">Line total (good stock)</label>
        <div class="form-control form-control-sm bg-light rowTotal" data-value="0">KES 0</div>
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

  function attachTypeahead(input, field, onPick) {
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

  function setRestock(row, product) {
    var choice = row.querySelector('.productChoice');
    var note = row.querySelector('.matchNote');
    if (product && product.id) {
      choice.value = product.id;
      row.classList.add('is-restock');
      note.style.display = 'block';
      note.textContent = 'Restocking existing product (balance ' + product.balance + ').';
      row.querySelector('.qtyLabel').textContent = 'Qty to add';
    } else {
      choice.value = '';
      row.classList.remove('is-restock');
      note.style.display = 'none';
      row.querySelector('.qtyLabel').textContent = 'Good qty received';
    }
    recalc();
  }

  function wireRow(row) {
    row.querySelector('.removeRow').addEventListener('click', function () {
      row.remove(); recalc();
    });
    row.querySelectorAll('.qty, .buyingPrice').forEach(function (el) {
      el.addEventListener('input', recalc);
    });
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
    row.querySelectorAll('.ta-input').forEach(function (input) {
      var field = input.dataset.field;
      if (field === 'title') {
        attachTypeahead(input, 'title', function () {});
        var timer = null;
        input.addEventListener('input', function () {
          clearTimeout(timer);
          var q = input.value.trim();
          if (!q) { setRestock(row, null); return; }
          timer = setTimeout(function () {
            fetch(API + 'find_titles.php?type=product&q=' + encodeURIComponent(q))
              .then(function (r) { return r.json(); })
              .then(function (data) {
                var exact = (data.items || []).find(function (it) { return (it.name || '').toLowerCase() === q.toLowerCase(); });
                setRestock(row, exact || null);
              }).catch(function () {});
          }, 200);
        });
      } else {
        attachTypeahead(input, field);
      }
    });
    var barcode = row.querySelector('.barcodeInput');
    var barcodeNote = row.querySelector('.barcodeNote');
    barcode.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      var code = barcode.value.trim();
      if (!code) return;
      fetch(API + 'find_barcode.php?code=' + encodeURIComponent(code))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.item) {
            row.querySelector('.productTitle').value = data.item.name;
            setRestock(row, { id: data.item.id, balance: data.item.balance || data.item.quantity || 0 });
            barcodeNote.style.display = 'block';
            barcodeNote.style.color = '#15803d';
            barcodeNote.textContent = 'Matched existing product.';
          } else {
            barcodeNote.style.display = 'block';
            barcodeNote.style.color = '#64748b';
            barcodeNote.textContent = 'New barcode — will be saved with this product.';
          }
        });
    });
  }

  function recalc() {
    var grand = 0;
    document.querySelectorAll('.stock-row').forEach(function (row) {
      var qty = parseFloat(row.querySelector('.qty').value) || 0;
      var buy = parseFloat(row.querySelector('.buyingPrice').value) || 0;
      var total = qty * buy;
      var el = row.querySelector('.rowTotal');
      el.dataset.value = total;
      el.textContent = money(total);
      grand += total;
    });
    document.getElementById('grandTotal').textContent = money(grand);
  }

  document.getElementById('addRowBtn').addEventListener('click', addRow);
  addRow();
})();
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
