<?php
// public/staff/invoices/edit.php — add/remove/change items on an open credit
// invoice. Stock adjusts on save. Used by staff and owner (super wraps this).
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::SALES_RECORD);

$pdo = Database::pdo();
$O = new Models\OrderModel($pdo);
$productSearchUrl = public_url('api/inventory/sellable_search.php');
$isStaffViewer = TenantContext::role() === 'staff';
$editBase = $isStaffViewer ? public_url('staff/invoices/edit.php') : public_url('super/invoices/edit.php');
$backBase = $isStaffViewer ? public_url('staff/orders/') : public_url('super/invoices/');
$receiptBase = $isStaffViewer ? public_url('staff/orders/receipt.php') : public_url('super/orders/receipt.php');
$paymentsBase = $isStaffViewer ? public_url('staff/payments/') : public_url('super/payments/');

$id = (int) ($_GET['id'] ?? $_POST['order_id'] ?? 0);
$invoice = $id > 0 ? $O->find($id) : null;
if (!$invoice || ($invoice['status'] ?? '') === 'void') {
    $_SESSION['flash']['error'] = 'Invoice not found.';
    header('Location: ' . $backBase);
    exit;
}

$items = $O->items($id);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $existing = [];
    foreach ($_POST['existing_items'] ?? [] as $itemId => $row) {
        $existing[(int) $itemId] = [
            'quantity' => $row['remove'] ?? '' ? 0 : ($row['quantity'] ?? 0),
            'unit_price' => $row['unit_price'] ?? 0,
            'price_type' => (($row['price_type'] ?? 'retail') === 'wholesale') ? 'wholesale' : 'retail',
        ];
    }

    $newItems = [];
    foreach ($_POST['new_items'] ?? [] as $row) {
        $pid = (int) ($row['product_id'] ?? 0);
        $qty = (float) ($row['quantity'] ?? 0);
        if ($pid > 0 && $qty > 0) {
            $newItems[] = [
                'product_id' => $pid,
                'quantity' => $qty,
                'price_type' => (($row['price_type'] ?? 'retail') === 'wholesale') ? 'wholesale' : 'retail',
            ];
        }
    }

    $res = $O->updateInvoice($id, [
        'table_name' => $_POST['customer_name'] ?? '',
        'sale_type' => ($_POST['sale_type'] ?? 'retail') === 'wholesale' ? 'wholesale' : 'retail',
        'discount_amount' => $_POST['discount_amount'] ?? 0,
        'additional_charges' => $_POST['additional_charges'] ?? 0,
        'additional_charges_note' => $_POST['additional_charges_note'] ?? '',
        'customer_email' => $_POST['customer_email'] ?? '',
        'customer_phone' => $_POST['customer_phone'] ?? '',
        'credit_duration_days' => $_POST['credit_duration_days'] ?? 0,
        'existing_items' => $existing,
        'new_items' => $newItems,
    ], TenantContext::userId());

    if ($res['ok']) {
        $_SESSION['flash']['success'] = 'Invoice updated and stock adjusted.';
        header('Location: ' . $editBase . '?id=' . $id);
        exit;
    }
    $error = $res['errors']['_'] ?? (reset($res['errors']) ?: 'Could not update invoice.');
}

$invoice = $O->find($id);
$items = $O->items($id);
$paid = max(0, (float) ($invoice['amount_paid'] ?? 0));
$due = (float) ($invoice['amount_due'] ?? 0);
if (($invoice['status'] ?? '') === 'open' && $due <= 0.0001) {
    $due = max(0, (float) ($invoice['total'] ?? 0) - $paid);
}
$page_title = 'Edit invoice';
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <div>
    <h1 class="h5 fw-bold mb-1">Edit <?php echo htmlspecialchars($invoice['receipt_number'] ?? 'invoice'); ?></h1>
    <p class="text-muted small mb-0">Add products, remove lines, or change quantities. Stock is adjusted when you save. Balance due updates automatically.</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $backBase; ?>">Back</a>
    <?php if (($invoice['status'] ?? '') === 'open'): ?>
      <a class="btn btn-sm btn-success" href="<?php echo $paymentsBase . '?receipt=' . urlencode($invoice['receipt_number']) . '&deposit=1'; ?>"><i class="fas fa-hand-holding-dollar me-1"></i>Deposit</a>
    <?php endif; ?>
    <a class="btn btn-sm btn-outline-primary" href="<?php echo $receiptBase . '?id=' . $id; ?>">Print</a>
  </div>
</div>

<?php if (($invoice['status'] ?? '') === 'open'): ?>
  <div class="alert alert-light border mb-3 d-flex justify-content-between flex-wrap gap-2">
    <div><span class="text-muted">Paid so far</span> <strong>KES <?php echo number_format($paid, 0); ?></strong></div>
    <div><span class="text-muted">Balance due</span> <strong class="text-danger">KES <?php echo number_format($due, 0); ?></strong></div>
  </div>
<?php endif; ?>

<?php if (($invoice['status'] ?? '') !== 'open'): ?>
  <div class="alert alert-info">This invoice is already paid, so it is view-only.</div>
<?php endif; ?>

<form method="post" id="editInvoiceForm" class="card border-0 shadow-sm" style="border-radius:14px;">
  <input type="hidden" name="order_id" value="<?php echo $id; ?>">
  <div class="card-body p-4">
    <div class="row g-3 mb-4">
      <div class="col-md-4"><label class="form-label small">Customer name</label><input name="customer_name" class="form-control" required value="<?php echo htmlspecialchars($invoice['table_name'] ?? ''); ?>" <?php echo ($invoice['status'] ?? '') !== 'open' ? 'disabled' : ''; ?>></div>
      <div class="col-md-4"><label class="form-label small">Phone</label><input name="customer_phone" class="form-control" value="<?php echo htmlspecialchars($invoice['customer_phone'] ?? ''); ?>" <?php echo ($invoice['status'] ?? '') !== 'open' ? 'disabled' : ''; ?>></div>
      <div class="col-md-4"><label class="form-label small">Email</label><input type="email" name="customer_email" class="form-control" value="<?php echo htmlspecialchars($invoice['customer_email'] ?? ''); ?>" <?php echo ($invoice['status'] ?? '') !== 'open' ? 'disabled' : ''; ?>></div>
      <div class="col-md-4"><label class="form-label small">Set invoice price</label><select name="sale_type" class="form-select" <?php echo ($invoice['status'] ?? '') !== 'open' ? 'disabled' : ''; ?>><option value="retail" <?php echo ($invoice['sale_type'] ?? 'retail') !== 'wholesale' ? 'selected' : ''; ?>>Retail</option><option value="wholesale" <?php echo ($invoice['sale_type'] ?? '') === 'wholesale' ? 'selected' : ''; ?>>Wholesale</option></select></div>
      <div class="col-md-4"><label class="form-label small">Credit duration</label><select name="credit_duration_days" class="form-select" <?php echo ($invoice['status'] ?? '') !== 'open' ? 'disabled' : ''; ?>><option value="0">No due date</option><?php foreach ([2 => '2 days', 7 => '1 week', 14 => '2 weeks', 30 => '1 month', 45 => '45 days', 60 => '2 months'] as $days => $label): ?><option value="<?php echo $days; ?>" <?php echo (int) ($invoice['credit_duration_days'] ?? 0) === $days ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-4"><label class="form-label small">Discount</label><input type="number" min="0" step="0.01" name="discount_amount" class="form-control" value="<?php echo htmlspecialchars((string) ($invoice['discount_amount'] ?? 0)); ?>" <?php echo ($invoice['status'] ?? '') !== 'open' ? 'disabled' : ''; ?>></div>
      <div class="col-md-4"><label class="form-label small">Extra charge</label><input type="number" min="0" step="0.01" name="additional_charges" class="form-control" value="<?php echo htmlspecialchars((string) ($invoice['additional_charges'] ?? 0)); ?>" <?php echo ($invoice['status'] ?? '') !== 'open' ? 'disabled' : ''; ?>></div>
      <div class="col-md-4"><label class="form-label small">Charge note</label><input type="text" name="additional_charges_note" class="form-control" value="<?php echo htmlspecialchars((string) ($invoice['additional_charges_note'] ?? '')); ?>" placeholder="Delivery, packing…" <?php echo ($invoice['status'] ?? '') !== 'open' ? 'disabled' : ''; ?>></div>
    </div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
      <h2 class="h6 fw-bold mb-0">Invoice products</h2>
      <?php if (($invoice['status'] ?? '') === 'open'): ?><button type="button" class="btn btn-sm btn-outline-primary" id="addNewItem">Add product</button><?php endif; ?>
    </div>

    <div class="invoice-items">
      <?php foreach ($items as $it): ?>
        <div class="invoice-edit-row" data-row>
          <div>
            <div class="fw-semibold"><?php echo htmlspecialchars($it['product_name']); ?></div>
            <div class="text-muted small">Current line: KES <?php echo number_format((float) $it['line_total'], 2); ?></div>
          </div>
          <div><label class="form-label small mb-1">Qty</label><input type="number" step="0.01" min="0" name="existing_items[<?php echo (int) $it['id']; ?>][quantity]" class="form-control qty-input" value="<?php echo htmlspecialchars(rtrim(rtrim(number_format((float) $it['quantity'], 2, '.', ''), '0'), '.')); ?>" <?php echo ($invoice['status'] ?? '') !== 'open' ? 'disabled' : ''; ?>></div>
          <div><label class="form-label small mb-1">Price</label><input type="number" step="0.01" min="0" name="existing_items[<?php echo (int) $it['id']; ?>][unit_price]" class="form-control price-input" value="<?php echo htmlspecialchars((string) $it['unit_price']); ?>" <?php echo ($invoice['status'] ?? '') !== 'open' ? 'disabled' : ''; ?>></div>
          <div><label class="form-label small mb-1">Type</label><select name="existing_items[<?php echo (int) $it['id']; ?>][price_type]" class="form-select" <?php echo ($invoice['status'] ?? '') !== 'open' ? 'disabled' : ''; ?>><option value="retail" <?php echo ($it['price_type'] ?? 'retail') !== 'wholesale' ? 'selected' : ''; ?>>Retail</option><option value="wholesale" <?php echo ($it['price_type'] ?? '') === 'wholesale' ? 'selected' : ''; ?>>Wholesale</option></select></div>
          <div class="fw-bold text-end line-output">KES 0</div>
          <?php if (($invoice['status'] ?? '') === 'open'): ?>
            <div><button type="button" class="btn btn-outline-danger w-100 remove-existing">Remove</button><input type="hidden" name="existing_items[<?php echo (int) $it['id']; ?>][remove]" value=""></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div id="newItems"></div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 border-top pt-3 mt-3">
      <div class="fw-bold">New invoice total: <span id="invoiceEditTotal">KES 0</span></div>
      <?php if (($invoice['status'] ?? '') === 'open'): ?><button class="btn btn-primary">Save invoice changes</button><?php endif; ?>
    </div>
  </div>
</form>

<template id="newItemTpl">
  <div class="invoice-edit-row new-row" data-row>
    <div>
      <label class="form-label small mb-1">Search product</label>
      <div class="product-search-wrap">
        <input type="text" class="form-control product-search" placeholder="Type at least 2 letters…" autocomplete="off">
        <input type="hidden" name="new_items[__I__][product_id]" class="product-id" value="">
        <div class="product-search-menu"></div>
      </div>
      <div class="selected-product small text-muted mt-1">No product selected</div>
    </div>
    <div><label class="form-label small mb-1">Qty</label><input type="number" step="0.01" min="0" name="new_items[__I__][quantity]" class="form-control qty-input" placeholder="0"></div>
    <div><label class="form-label small mb-1">Type</label><select name="new_items[__I__][price_type]" class="form-select new-price-type"><option value="retail">Retail</option><option value="wholesale">Wholesale</option></select></div>
    <div><label class="form-label small mb-1">Price</label><div class="form-control bg-light new-price">KES 0</div></div>
    <div class="fw-bold text-end line-output">KES 0</div>
    <div><button type="button" class="btn btn-outline-danger w-100 remove-new">Remove</button></div>
  </div>
</template>

<style>
.invoice-edit-row{display:grid;grid-template-columns:minmax(220px,1fr) 110px 120px 120px 120px 110px;gap:.75rem;align-items:end;border-bottom:1px solid #eef0f4;padding:.8rem 0;}
.invoice-edit-row:last-child{border-bottom:0;}
.invoice-edit-row.is-removed{opacity:.55;background:#fff5f5;}
.product-search-wrap{position:relative;}
.product-search-menu{position:absolute;left:0;right:0;top:100%;z-index:80;background:#fff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 12px 28px rgba(15,23,42,.14);display:none;max-height:260px;overflow:auto;}
.product-search-menu.show{display:block;}
.product-search-menu button{display:block;width:100%;border:0;background:#fff;text-align:left;padding:.55rem .7rem;font-size:.85rem;}
.product-search-menu button:hover{background:#f8fafc;}
.product-search-menu .meta{display:block;color:#64748b;font-size:.75rem;margin-top:1px;}
@media (max-width: 768px){
  .card-body{padding:1rem!important;}
  .invoice-edit-row{grid-template-columns:1fr;border:1px solid #e2e8f0;border-radius:10px;padding:.8rem;margin-bottom:.8rem;}
  .line-output{text-align:left!important;}
}
</style>
<script>
(function(){
  var idx = 0;
  var newWrap = document.getElementById('newItems');
  var tpl = document.getElementById('newItemTpl');
  var saleType = document.querySelector('[name="sale_type"]');
  var discount = document.querySelector('[name="discount_amount"]');
  var extraCharge = document.querySelector('[name="additional_charges"]');
  var productSearchUrl = <?php echo json_encode($productSearchUrl); ?>;
  function money(n){ return 'KES ' + (Math.round(n * 100) / 100).toLocaleString('en-KE', {maximumFractionDigits:2}); }
  function rowSaleType(row){
    var select = row.querySelector('.new-price-type');
    return select ? (select.value === 'wholesale' ? 'wholesale' : 'retail') : (saleType && saleType.value === 'wholesale' ? 'wholesale' : 'retail');
  }
  function priceForRow(row){
    return parseFloat(rowSaleType(row) === 'wholesale' ? row.dataset.wholesalePrice : row.dataset.retailPrice) || 0;
  }
  function recalc(){
    var total = 0;
    document.querySelectorAll('[data-row]').forEach(function(row){
      var qty = parseFloat((row.querySelector('.qty-input') || {}).value) || 0;
      var priceInput = row.querySelector('.price-input');
      var price = priceInput ? (parseFloat(priceInput.value) || 0) : priceForRow(row);
      var line = Math.max(0, qty * price);
      var newPrice = row.querySelector('.new-price');
      if (newPrice) newPrice.textContent = money(price);
      var output = row.querySelector('.line-output');
      if (output) output.textContent = money(line);
      if (!row.classList.contains('is-removed')) total += line;
    });
    total = Math.max(0, total - (parseFloat(discount ? discount.value : 0) || 0));
    total += Math.max(0, parseFloat(extraCharge ? extraCharge.value : 0) || 0);
    document.getElementById('invoiceEditTotal').textContent = money(total);
  }
  function wire(row){
    row.querySelectorAll('input,select').forEach(function(el){ el.addEventListener('input', recalc); el.addEventListener('change', recalc); });
    var newType = row.querySelector('.new-price-type');
    if (newType && saleType) {
      newType.value = saleType.value === 'wholesale' ? 'wholesale' : 'retail';
    }
    attachProductSearch(row);
    var removeExisting = row.querySelector('.remove-existing');
    if (removeExisting) {
      removeExisting.addEventListener('click', function(){
        row.classList.toggle('is-removed');
        row.querySelector('input[type="hidden"]').value = row.classList.contains('is-removed') ? '1' : '';
        removeExisting.textContent = row.classList.contains('is-removed') ? 'Undo' : 'Remove';
        recalc();
      });
    }
    var removeNew = row.querySelector('.remove-new');
    if (removeNew) removeNew.addEventListener('click', function(){ row.remove(); recalc(); });
  }
  function attachProductSearch(row){
    var input = row.querySelector('.product-search');
    var hidden = row.querySelector('.product-id');
    var menu = row.querySelector('.product-search-menu');
    var selected = row.querySelector('.selected-product');
    if (!input || !hidden || !menu) return;
    var timer = null;
    function hide(){ menu.classList.remove('show'); }
    function formatQty(n){
      return (Math.round((parseFloat(n) || 0) * 100) / 100).toLocaleString('en-KE', {maximumFractionDigits:2});
    }
    function render(items){
      menu.innerHTML = '';
      if (!items.length) { hide(); return; }
      items.forEach(function(p){
        var btn = document.createElement('button');
        btn.type = 'button';
        var meta = ['Stock ' + formatQty(p.stock) + ' ' + (p.unit || ''), p.barcode, p.category_name, p.brand_name].filter(Boolean).join(' · ');
        var name = document.createElement('strong');
        name.textContent = p.name || '';
        var sub = document.createElement('span');
        sub.className = 'meta';
        sub.textContent = meta;
        btn.appendChild(name);
        btn.appendChild(sub);
        btn.addEventListener('mousedown', function(e){
          e.preventDefault();
          hidden.value = p.id || '';
          input.value = p.name || '';
          row.dataset.retailPrice = p.retail_price || 0;
          row.dataset.wholesalePrice = p.wholesale_price || p.retail_price || 0;
          if (selected) {
            selected.textContent = 'Selected: ' + (p.name || '') + ' · stock ' + formatQty(p.stock) + ' ' + (p.unit || '') + ' · retail ' + money(p.retail_price || 0);
          }
          hide();
          recalc();
        });
        menu.appendChild(btn);
      });
      menu.classList.add('show');
    }
    input.addEventListener('input', function(){
      hidden.value = '';
      row.dataset.retailPrice = '0';
      row.dataset.wholesalePrice = '0';
      if (selected) selected.textContent = 'No product selected';
      recalc();
      clearTimeout(timer);
      var q = input.value.trim();
      if (q.length < 2) { hide(); return; }
      timer = setTimeout(function(){
        fetch(productSearchUrl + '?q=' + encodeURIComponent(q) + '&limit=12')
          .then(function(r){ return r.json(); })
          .then(function(data){ render(data.items || []); })
          .catch(function(){ hide(); });
      }, 180);
    });
    input.addEventListener('blur', function(){ setTimeout(hide, 160); });
  }
  document.querySelectorAll('[data-row]').forEach(wire);
  if (saleType) saleType.addEventListener('change', function(){
    var type = saleType.value === 'wholesale' ? 'wholesale' : 'retail';
    document.querySelectorAll('[name$="[price_type]"]').forEach(function(select){ select.value = type; });
    recalc();
  });
  if (discount) discount.addEventListener('input', recalc);
  if (extraCharge) extraCharge.addEventListener('input', recalc);
  var addBtn = document.getElementById('addNewItem');
  if (addBtn && tpl) {
    addBtn.addEventListener('click', function(){
      newWrap.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__I__', idx++));
      wire(newWrap.lastElementChild);
      recalc();
    });
  }
  recalc();
})();
</script>
<?php
$content = ob_get_clean();
$__layout = $isStaffViewer ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
