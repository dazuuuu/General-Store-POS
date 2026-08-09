<?php
// public/super/stock/new.php — record a delivery: a supplier brings a batch
// of books (new titles, or a restock of ones already on the shelf), in the
// same shape as the shop's paper stock ledger: Subject, Book Title,
// Grade/Class, Publisher, Author, Edition, Opening Stock, Unit Price, Total,
// Balance, Remark. Every text field below is free entry with live
// suggestions — picking a suggestion (or typing an exact match) reuses the
// existing Subject/Grade/Publisher/Author/Edition/Supplier; anything new is
// created automatically on save. No dropdowns.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::STOCK_ENTER);

$pdo = Database::pdo();
$SUP = new Models\SupplierModel($pdo);
$C   = new Models\CategoryModel($pdo);
$BA  = new Models\BookAttributeModel($pdo);
$SI  = new Models\StockIntakeModel($pdo);

$base = public_url('super/stock/new.php');
$apiBase = public_url('api/inventory/');

/** Validate + store a row's uploaded image. Same rules as the products page. */
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
    $name = 'book_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return ['ok' => false, 'error' => 'Could not save the image. Check folder permissions.'];
    }
    return ['ok' => true, 'path' => public_url('assets/uploads/products/' . $name)];
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
            if ($qty <= 0) { continue; } // skip rows the owner didn't fill in
            $remark = trim($row['remark'] ?? '');
            $productChoice = trim($row['product_choice'] ?? '');

            if ($productChoice !== '') {
                $items[] = [
                    'mode'         => 'restock',
                    'product_id'   => (int) $productChoice,
                    'quantity'     => $qty,
                    'buying_price' => (float) ($row['buying_price'] ?? 0),
                    'remark'       => $remark,
                ];
                continue;
            }

            $title = trim($row['title'] ?? '');
            if ($title === '') { continue; }

            $img = stock_handle_image(stock_row_image_file((int) $i));
            if (!$img['ok']) { $error = $title . ': ' . $img['error']; break; }

            $items[] = [
                'mode'            => 'new',
                'name'            => $title,
                'category_id'     => (int) $C->findOrCreate($row['subject'] ?? ''),
                'grade_id'        => (int) $BA->findOrCreate('grade', $row['grade'] ?? ''),
                'publisher_id'    => (int) $BA->findOrCreate('publisher', $row['publisher'] ?? ''),
                'author_id'       => (int) $BA->findOrCreate('author', $row['author'] ?? ''),
                'edition_id'      => (int) $BA->findOrCreate('edition', $row['edition'] ?? ''),
                'barcode'         => trim($row['barcode'] ?? ''),
                'quantity'        => $qty,
                'buying_price'    => (float) ($row['buying_price'] ?? 0),
                'selling_price'   => $row['selling_price'] ?? 0,
                'wholesale_price' => $row['wholesale_price'] ?? '',
                'offer_price'     => $row['offer_price'] ?? '',
                'offer_starts_at' => $row['offer_starts_at'] ?? '',
                'offer_ends_at'   => $row['offer_ends_at'] ?? '',
                'image_path'      => $img['path'] ?? '',
                'remark'          => $remark,
            ];
        }

        if (!$error && !$items) {
            $error = 'Add at least one book with a quantity.';
        }

        if (!$error) {
            $res = $SI->create([
                'supplier_id' => $supplierId,
                'staff_id'    => TenantContext::userId(),
                'notes'       => $_POST['notes'] ?? '',
            ], $items);
            if ($res['ok']) {
                $_SESSION['flash']['success'] = 'Stock recorded — ' . count($items) . ' book' . (count($items) === 1 ? '' : 's') . '.';
                header('Location: ' . public_url('super/inventory/'));
                exit;
            }
            $error = $res['errors']['_'] ?? (reset($res['errors']) ?: 'Could not record this delivery.');
        }
    }
}

$page_title = 'Record stock';
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" id="stockForm" novalidate>
  <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
      <h2 class="h5 mb-3">This delivery</h2>
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label">Supplier <span class="text-muted">(optional — who delivered it)</span></label>
          <div class="ta-wrap">
            <input type="text" name="supplier" class="form-control ta-input" data-field="supplier" placeholder="e.g. Longhorn Distributors" autocomplete="off">
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
        <h2 class="h5 mb-0">Books received</h2>
        <button type="button" class="btn btn-sm btn-outline-primary" id="addRowBtn"><i class="fas fa-plus me-1"></i>Add another book</button>
      </div>
      <div id="rows"></div>
      <div class="d-flex justify-content-end pt-2 border-top mt-2">
        <div class="text-muted small">Grand total: <strong id="grandTotal">KES 0</strong></div>
      </div>
    </div>
  </div>

  <button class="btn btn-primary btn-lg"><i class="fas fa-truck-loading me-1"></i>Record stock</button>
</form>

<template id="rowTpl">
  <div class="stock-row border rounded p-3 mb-3" style="border-color:#e2e8f0!important;">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <span class="fw-semibold small text-muted">Book __N__</span>
      <button type="button" class="btn btn-sm btn-link text-danger p-0 removeRow">Remove</button>
    </div>
    <div class="row g-2">
      <div class="col-12 col-sm-6">
        <label class="form-label small mb-1">Book title</label>
        <div class="ta-wrap">
          <input type="text" name="items[__I__][title]" class="form-control form-control-sm ta-input bookTitle" data-field="title" placeholder="e.g. Let's do Mathematics" autocomplete="off">
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
        <label class="form-label small mb-1">Cover photo <span class="text-muted">(optional)</span></label>
        <div class="d-flex align-items-center gap-2">
          <input type="file" name="items[__I__][image]" accept="image/*" class="form-control form-control-sm photoInput">
          <img class="photoPreview" style="display:none;width:36px;height:36px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;">
        </div>
      </div>

      <div class="col-6 col-sm-3 mt-2 newProductFields">
        <label class="form-label small mb-1">Subject</label>
        <div class="ta-wrap">
          <input type="text" name="items[__I__][subject]" class="form-control form-control-sm ta-input" data-field="subject" placeholder="e.g. Mathematics" autocomplete="off">
          <div class="ta-menu"></div>
        </div>
      </div>
      <div class="col-6 col-sm-3 mt-2 newProductFields">
        <label class="form-label small mb-1">Grade/Class</label>
        <div class="ta-wrap">
          <input type="text" name="items[__I__][grade]" class="form-control form-control-sm ta-input" data-field="grade" placeholder="e.g. Grade Three" autocomplete="off">
          <div class="ta-menu"></div>
        </div>
      </div>
      <div class="col-6 col-sm-3 mt-2 newProductFields">
        <label class="form-label small mb-1">Publisher</label>
        <div class="ta-wrap">
          <input type="text" name="items[__I__][publisher]" class="form-control form-control-sm ta-input" data-field="publisher" placeholder="e.g. Longhorn" autocomplete="off">
          <div class="ta-menu"></div>
        </div>
      </div>
      <div class="col-6 col-sm-3 mt-2 newProductFields">
        <label class="form-label small mb-1">Author</label>
        <div class="ta-wrap">
          <input type="text" name="items[__I__][author]" class="form-control form-control-sm ta-input" data-field="author" placeholder="e.g. Kefa Masita" autocomplete="off">
          <div class="ta-menu"></div>
        </div>
      </div>
      <div class="col-6 col-sm-3 mt-2 newProductFields">
        <label class="form-label small mb-1">Edition</label>
        <div class="ta-wrap">
          <input type="text" name="items[__I__][edition]" class="form-control form-control-sm ta-input" data-field="edition" placeholder="e.g. First" autocomplete="off">
          <div class="ta-menu"></div>
        </div>
      </div>

      <div class="col-6 col-sm-3 mt-2">
        <label class="form-label small mb-1 qtyLabel">Opening stock</label>
        <input type="number" step="0.01" min="0" name="items[__I__][quantity]" class="form-control form-control-sm qty" placeholder="0">
      </div>
      <div class="col-6 col-sm-3 mt-2">
        <label class="form-label small mb-1">Unit price (KES)</label>
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
        <label class="form-label small mb-1">Total</label>
        <div class="form-control form-control-sm bg-light rowTotal" data-value="0">KES 0</div>
      </div>
      <div class="col-6 col-sm-8 mt-2">
        <label class="form-label small mb-1">Remark <span class="text-muted">(optional)</span></label>
        <input type="text" name="items[__I__][remark]" class="form-control form-control-sm" placeholder="e.g. torn cover, invoice #2201">
      </div>

      <div class="col-12 mt-2 newProductFields">
        <div class="form-check">
          <input class="form-check-input offerToggle" type="checkbox" id="offerToggle__I__">
          <label class="form-check-label small" for="offerToggle__I__"><i class="fas fa-tag me-1"></i>Put this book on offer</label>
        </div>
        <div class="row g-2 mt-1 offerFields" style="display:none;">
          <div class="col-6 col-sm-4">
            <label class="form-label small mb-1">Offer price (KES)</label>
            <input type="number" step="0.01" min="0" name="items[__I__][offer_price]" class="form-control form-control-sm" placeholder="0">
          </div>
          <div class="col-6 col-sm-4">
            <label class="form-label small mb-1">Starts <span class="text-muted">(optional)</span></label>
            <input type="datetime-local" name="items[__I__][offer_starts_at]" class="form-control form-control-sm">
          </div>
          <div class="col-12 col-sm-4">
            <label class="form-label small mb-1">Ends</label>
            <input type="datetime-local" name="items[__I__][offer_ends_at]" class="form-control form-control-sm offerEnds">
          </div>
        </div>
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
  .ta-menu .ta-empty { padding: .4rem .65rem; font-size: .8rem; color: #94a3b8; }
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

  // --- generic type-ahead: free text, suggestions only, no forced choice ---
  function attachTypeahead(input, field, onPick) {
    var wrap = input.closest('.ta-wrap');
    var menu = wrap.querySelector('.ta-menu');
    var timer = null;

    function render(items) {
      menu.innerHTML = '';
      if (!items.length) {
        menu.classList.remove('show');
        return;
      }
      items.forEach(function (item) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = item.name;
        b.addEventListener('mousedown', function (e) {
          e.preventDefault(); // fire before input blur closes the menu
          input.value = item.name;
          menu.classList.remove('show');
          if (onPick) onPick(item);
        });
        menu.appendChild(b);
      });
      menu.classList.add('show');
    }

    input.addEventListener('input', function () {
      if (onPick) onPick(null); // typing invalidates any earlier pick (e.g. restock match)
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

  // --- Shared: a matched product (by title or by barcode) switches the row to restock ---
  function makeRestockControls(row) {
    var titleInput = row.querySelector('.bookTitle');
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
      row.querySelector('.buyingPrice').value = item.buying_price || '';
      row.querySelector('.qtyLabel').textContent = 'Additional stock';
      var bits = [item.subject_name, item.grade_name, item.publisher_name, item.author_name, item.edition_name].filter(Boolean);
      note.style.display = 'block';
      note.innerHTML = '<i class="fas fa-circle-check me-1"></i>Already on the shelf' + (bits.length ? ' — ' + bits.join(' · ') : '') +
        '. Current balance: <strong>' + item.balance + '</strong>. This adds to it.';
    }

    function clearRestock() {
      productChoice.value = '';
      lastMatchedId = null;
      row.classList.remove('is-restock');
      row.querySelector('.qtyLabel').textContent = 'Opening stock';
      note.style.display = 'none';
      note.innerHTML = '';
    }

    return { setRestock: setRestock, clearRestock: clearRestock, isMatched: function () { return lastMatchedId !== null; } };
  }

  // --- Book Title: matches an existing product -> switches the row to restock ---
  function wireTitleField(row, restock) {
    var input = row.querySelector('.bookTitle');
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
        var bits = [item.grade_name, item.publisher_name].filter(Boolean);
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
      if (lastMatchedName !== null && input.value !== lastMatchedName) { lastMatchedName = null; restock.clearRestock(); }
      clearTimeout(timer);
      var q = input.value.trim();
      if (!q) { menu.classList.remove('show'); return; }
      timer = setTimeout(function () {
        fetch(API + 'find_titles.php?q=' + encodeURIComponent(q))
          .then(function (r) { return r.json(); })
          .then(function (data) { render(data.items || []); })
          .catch(function () {});
      }, 180);
    });
    input.addEventListener('blur', function () { setTimeout(function () { menu.classList.remove('show'); }, 150); });
  }

  // --- Barcode: an exact match (scanner types fast + hits Enter) -> restock;
  //     no match just keeps the code to save on the new book. ---
  function wireBarcodeField(row, restock) {
    var input = row.querySelector('.barcodeInput');
    var note = row.querySelector('.barcodeNote');
    var lastChecked = null;

    function lookup() {
      var code = input.value.trim();
      if (!code || code === lastChecked) { return; }
      lastChecked = code;
      fetch(API + 'find_barcode.php?code=' + encodeURIComponent(code))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (input.value.trim() !== code) { return; } // stale response, input changed since
          if (data.item) {
            restock.setRestock(data.item);
            note.style.display = 'none';
          } else {
            note.style.display = 'block';
            note.className = 'barcodeNote small mt-1 text-muted';
            note.innerHTML = '<i class="fas fa-circle-plus me-1"></i>New barcode — will be saved on this book.';
          }
        })
        .catch(function () {});
    }

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); lookup(); }
    });
    input.addEventListener('blur', lookup);
    input.addEventListener('input', function () {
      if (restock.isMatched() && input.value.trim() === '') { /* leave restock state alone; title field owns it */ }
      note.style.display = 'none';
    });
  }

  function recalcRow(row) {
    var qty = parseFloat(row.querySelector('.qty').value) || 0;
    var price = parseFloat(row.querySelector('.buyingPrice').value) || 0;
    var total = qty * price;
    var box = row.querySelector('.rowTotal');
    box.textContent = money(total);
    box.dataset.value = total;
    recalcGrandTotal();
  }

  function recalcGrandTotal() {
    var sum = 0;
    document.querySelectorAll('.rowTotal').forEach(function (b) { sum += parseFloat(b.dataset.value) || 0; });
    document.getElementById('grandTotal').textContent = money(sum);
  }

  function wireRow(row) {
    row.querySelector('.removeRow').addEventListener('click', function () { row.remove(); recalcGrandTotal(); });

    var restock = makeRestockControls(row);
    wireTitleField(row, restock);
    wireBarcodeField(row, restock);
    ['subject', 'grade', 'publisher', 'author', 'edition'].forEach(function (field) {
      var el = row.querySelector('[data-field="' + field + '"]');
      if (el) attachTypeahead(el, field);
    });

    row.querySelector('.qty').addEventListener('input', function () { recalcRow(row); });
    row.querySelector('.buyingPrice').addEventListener('input', function () { recalcRow(row); });

    var photoInput = row.querySelector('.photoInput');
    var preview = row.querySelector('.photoPreview');
    if (photoInput) {
      photoInput.addEventListener('change', function () {
        var file = photoInput.files && photoInput.files[0];
        if (!file) { preview.style.display = 'none'; return; }
        preview.src = URL.createObjectURL(file);
        preview.style.display = 'block';
      });
    }

    var offerToggle = row.querySelector('.offerToggle');
    var offerFields = row.querySelector('.offerFields');
    var offerEnds = row.querySelector('.offerEnds');
    if (offerToggle) {
      offerToggle.addEventListener('change', function () {
        offerFields.style.display = offerToggle.checked ? 'flex' : 'none';
        if (offerToggle.checked && !offerEnds.value) {
          var d = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000);
          d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
          offerEnds.value = d.toISOString().slice(0, 16);
        }
      });
    }
  }

  document.getElementById('addRowBtn').addEventListener('click', addRow);
  addRow(); // start with one row

  var supplierInput = document.querySelector('.ta-input[data-field="supplier"]');
  attachTypeahead(supplierInput, 'supplier');

  document.getElementById('stockForm').addEventListener('submit', function (e) {
    if (!rowsWrap.children.length) { e.preventDefault(); alert('Add at least one book.'); }
  });
})();
</script>

<?php
$content = ob_get_clean();
$__layout = TenantContext::role() === 'staff' ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
