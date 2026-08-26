<?php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::SALES_RECORD);

$pdo = Database::pdo();
$O = new Models\OrderModel($pdo);
$customerSearchUrl = public_url('api/customers/search.php');
$productSearchUrl = public_url('api/inventory/sellable_search.php');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_invoice') {
        $items = [];
        foreach ($_POST['items'] ?? [] as $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            $qty = (float) ($row['quantity'] ?? 0);
            if ($pid > 0 && $qty > 0) {
                $items[] = [
                    'product_id' => $pid,
                    'quantity' => $qty,
                    'price_type' => (($row['price_type'] ?? 'retail') === 'wholesale') ? 'wholesale' : 'retail',
                ];
            }
        }
        $res = $O->open([
            'table_name' => $_POST['customer_name'] ?? '',
            'opened_by' => TenantContext::userId(),
            'items' => $items,
            'channel' => 'tab',
            'sale_type' => ($_POST['sale_type'] ?? 'retail') === 'wholesale' ? 'wholesale' : 'retail',
            'discount_amount' => $_POST['discount_amount'] ?? 0,
            'additional_charges' => $_POST['additional_charges'] ?? 0,
            'additional_charges_note' => $_POST['additional_charges_note'] ?? '',
            'customer_id' => $_POST['customer_id'] ?? 0,
            'customer_email' => $_POST['customer_email'] ?? '',
            'customer_phone' => $_POST['customer_phone'] ?? '',
            'credit_duration_days' => $_POST['credit_duration_days'] ?? 0,
        ]);
        if ($res['ok']) {
            $_SESSION['flash']['success'] = 'Invoice ' . $res['receipt_number'] . ' created. Products have been deducted from stock.';
            header('Location: ' . public_url('super/orders/receipt.php?id=' . (int) $res['order_id']));
            exit;
        }
        $error = $res['errors']['_'] ?? (reset($res['errors']) ?: 'Could not create invoice.');
    } elseif ($action === 'delete_invoice') {
        $res = $O->void((int) ($_POST['order_id'] ?? 0), TenantContext::userId());
        if ($res['ok']) {
            $_SESSION['flash']['success'] = 'Invoice deleted and products returned to stock.';
            header('Location: ' . public_url('super/invoices/'));
            exit;
        }
        $error = $res['error'] ?? 'Could not delete invoice.';
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$invoices = $q !== ''
    ? $O->searchInvoices($q, ['open_only' => false, 'limit' => 100])
    : $O->documentOrders(100);
$itemsByOrder = $O->itemsForMany(array_column($invoices, 'id'));
$invoiceSearchApi = public_url('api/orders/invoice_search.php');
$page_title = 'Invoices';
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <div>
    <h1 class="h5 fw-bold mb-1"><i class="fas fa-file-invoice me-2 text-primary"></i>Invoices</h1>
    <p class="text-muted small mb-0">Generate customer invoices directly from products. Stock is deducted immediately; unpaid balances are collected from Payments.</p>
  </div>
  <a class="btn btn-sm btn-outline-success" href="<?php echo public_url('super/payments/'); ?>"><i class="fas fa-cash-register me-1"></i>Payments</a>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <div class="card-body p-3">
    <form method="get" class="row g-2 align-items-end" id="invoiceListSearchForm" autocomplete="off">
      <div class="col-12 col-md-9 position-relative">
        <label class="form-label small mb-1">Search invoices</label>
        <input type="search" name="q" id="invoiceListSearch" class="form-control form-control-lg"
               placeholder="Customer name, company, or invoice number…"
               value="<?php echo htmlspecialchars($q); ?>">
        <div class="invoice-suggest-menu" id="invoiceListSuggestMenu"></div>
        <div class="form-text">Shows customer / company, invoice number, and balance owed.</div>
      </div>
      <div class="col-6 col-md-2">
        <button class="btn btn-primary btn-lg w-100"><i class="fas fa-magnifying-glass me-1"></i>Find</button>
      </div>
      <div class="col-6 col-md-1">
        <?php if ($q !== ''): ?><a class="btn btn-outline-secondary btn-lg w-100" href="<?php echo public_url('super/invoices/'); ?>">Clear</a><?php endif; ?>
      </div>
    </form>
  </div>
</div>

<form method="post" id="invoiceForm" class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <input type="hidden" name="action" value="create_invoice">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <h2 class="h6 fw-bold mb-0">New customer invoice</h2>
      <button type="button" class="btn btn-sm btn-outline-primary" id="addInvoiceRow"><i class="fas fa-plus me-1"></i>Add product</button>
    </div>
    <div class="row g-3 mb-3">
      <input type="hidden" name="customer_id" id="customerIdInput" value="">
      <div class="col-md-4 customer-lookup-wrap"><label class="form-label small">Customer name</label><input name="customer_name" id="customerName" class="form-control" required placeholder="Customer / company" autocomplete="off"><div class="customer-suggest-menu" id="customerSuggestMenu"></div></div>
      <div class="col-md-4"><label class="form-label small">Phone</label><input name="customer_phone" id="customerPhone" class="form-control" placeholder="Optional"></div>
      <div class="col-md-4"><label class="form-label small">Email</label><input type="email" name="customer_email" id="customerEmail" class="form-control" placeholder="Optional"></div>
      <div class="col-md-4"><label class="form-label small">Set invoice price</label><select name="sale_type" id="saleType" class="form-select"><option value="retail">Retail</option><option value="wholesale">Wholesale</option></select></div>
      <div class="col-md-4"><label class="form-label small">Credit duration</label><select name="credit_duration_days" class="form-select"><option value="0">No due date</option><?php foreach ([2 => '2 days', 7 => '1 week', 14 => '2 weeks', 30 => '1 month', 45 => '45 days', 60 => '2 months'] as $days => $label): ?><option value="<?php echo $days; ?>"><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-4"><label class="form-label small">Discount</label><input type="number" min="0" step="0.01" name="discount_amount" id="discountAmount" class="form-control" value="0"></div>
      <div class="col-md-4"><label class="form-label small">Extra charge</label><input type="number" min="0" step="0.01" name="additional_charges" id="extraChargeAmount" class="form-control" value="0"></div>
      <div class="col-md-4"><label class="form-label small">Charge note</label><input type="text" name="additional_charges_note" id="extraChargeNote" class="form-control" placeholder="Delivery, packing…"></div>
    </div>
    <div id="invoiceRows"></div>
    <div class="d-flex justify-content-between align-items-center border-top pt-3 mt-2">
      <div class="text-muted small">Invoice total: <strong id="invoiceTotal">KES 0</strong></div>
      <button class="btn btn-primary"><i class="fas fa-file-invoice me-1"></i>Generate invoice</button>
    </div>
  </div>
</form>

<div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
  <div class="px-4 py-3 border-bottom bg-white">
    <h2 class="h6 fw-bold mb-0"><?php echo $q !== '' ? 'Search results' : 'Customer invoices'; ?></h2>
  </div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr class="text-muted small text-uppercase"><th>Invoice</th><th>Customer / company</th><th>Products</th><th>Status</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th></th></tr></thead>
      <tbody>
        <?php if (!$invoices): ?><tr><td colspan="7" class="text-center text-muted py-4"><?php echo $q !== '' ? 'No invoices match that search.' : 'No invoices yet.'; ?></td></tr><?php endif; ?>
        <?php foreach ($invoices as $inv):
            $paid = max(0, (float) ($inv['amount_paid'] ?? 0));
            $due = (float) ($inv['balance_due'] ?? $inv['amount_due'] ?? 0);
            if (($inv['status'] ?? '') === 'open' && $due <= 0.0001) { $due = max(0, (float) $inv['total'] - $paid); }
            if (($inv['status'] ?? '') === 'paid') { $due = 0; }
            $orderItems = $itemsByOrder[(int) $inv['id']] ?? [];
            $company = trim((string) ($inv['customer_company'] ?? ''));
        ?>
        <tr>
          <td>
            <div class="fw-semibold"><?php echo htmlspecialchars($inv['receipt_number']); ?></div>
            <div class="text-muted small"><?php echo date('j M Y, g:i a', strtotime($inv['created_at'])); ?></div>
          </td>
          <td>
            <div><?php echo htmlspecialchars($inv['table_name']); ?></div>
            <?php if ($company !== ''): ?><div class="text-muted small"><?php echo htmlspecialchars($company); ?></div><?php endif; ?>
          </td>
          <td class="small">
            <?php echo htmlspecialchars(implode(', ', array_map(fn($it) => $it['name'] . ' x' . rtrim(rtrim(number_format((float) $it['qty'], 2), '0'), '.'), array_slice($orderItems, 0, 3)))); ?>
            <?php if (count($orderItems) > 3): ?> ...<?php endif; ?>
          </td>
          <td><?php echo $inv['status'] === 'paid' ? '<span class="badge bg-success">Paid</span>' : '<span class="badge bg-warning text-dark">Unpaid</span>'; ?></td>
          <td class="text-end">KES <?php echo number_format($paid, 0); ?></td>
          <td class="text-end fw-semibold <?php echo $due > 0 ? 'text-danger' : 'text-success'; ?>">KES <?php echo number_format($inv['status'] === 'paid' ? 0 : $due, 0); ?></td>
          <td class="text-end invoice-actions">
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url('super/orders/receipt.php?id=' . (int) $inv['id']); ?>">Print</a>
            <?php if ($inv['status'] === 'open'): ?>
              <a class="btn btn-sm btn-outline-primary" href="<?php echo public_url('super/invoices/edit.php?id=' . (int) $inv['id']); ?>">Edit</a>
              <a class="btn btn-sm btn-success" href="<?php echo public_url('super/payments/?receipt=' . urlencode($inv['receipt_number']) . '&deposit=1'); ?>">Deposit</a>
              <form method="post" class="d-inline" onsubmit="return confirm('Delete this invoice and return its products to stock?');">
                <input type="hidden" name="action" value="delete_invoice">
                <input type="hidden" name="order_id" value="<?php echo (int) $inv['id']; ?>">
                <button class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<template id="invoiceRowTpl">
  <div class="invoice-row row g-2 align-items-end mb-2">
    <div class="col-md-5">
      <label class="form-label small mb-1">Search product</label>
      <div class="product-search-wrap">
        <input type="text" class="form-control product-search" placeholder="Type at least 2 letters…" autocomplete="off">
        <input type="hidden" name="items[__I__][product_id]" class="product-id" value="">
        <div class="product-search-menu"></div>
      </div>
      <div class="selected-product small text-muted mt-1">No product selected</div>
    </div>
    <div class="col-md-2"><label class="form-label small mb-1 qty-label">Qty</label><input type="hidden" name="items[__I__][quantity]" class="stock-qty" value=""><input type="number" step="0.01" min="0" class="form-control invoice-qty" placeholder="0"></div>
    <div class="col-md-2"><label class="form-label small mb-1">Price type</label><select name="items[__I__][price_type]" class="form-select invoice-price-type"><option value="retail">Retail</option><option value="wholesale">Wholesale</option></select></div>
    <div class="col-md-2"><label class="form-label small mb-1">Line</label><div class="form-control bg-light line-total">KES 0</div></div>
    <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-invoice-row"><i class="fas fa-trash"></i></button></div>
  </div>
</template>

<style>
.customer-lookup-wrap{position:relative;}
.customer-suggest-menu{position:absolute;left:calc(var(--bs-gutter-x) * .5);right:calc(var(--bs-gutter-x) * .5);top:100%;z-index:70;background:#fff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 12px 28px rgba(15,23,42,.14);display:none;max-height:240px;overflow:auto;}
.customer-suggest-menu.show{display:block;}
.customer-suggest-menu button{display:block;width:100%;border:0;background:#fff;text-align:left;padding:.55rem .7rem;font-size:.85rem;}
.customer-suggest-menu button:hover{background:#f8fafc;}
.customer-suggest-menu .meta{display:block;color:#64748b;font-size:.75rem;margin-top:1px;}
.product-search-wrap{position:relative;}
.product-search-menu{position:absolute;left:0;right:0;top:100%;z-index:80;background:#fff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 12px 28px rgba(15,23,42,.14);display:none;max-height:260px;overflow:auto;}
.product-search-menu.show{display:block;}
.product-search-menu button{display:block;width:100%;border:0;background:#fff;text-align:left;padding:.55rem .7rem;font-size:.85rem;}
.product-search-menu button:hover{background:#f8fafc;}
.product-search-menu .meta{display:block;color:#64748b;font-size:.75rem;margin-top:1px;}
.invoice-actions{white-space:nowrap;}
.invoice-actions .btn{margin:.1rem;}
@media (max-width: 576px){
  #invoiceForm .card-body{padding:1rem!important;}
  .invoice-row{border:1px solid #e2e8f0;border-radius:10px;padding:.7rem;margin-bottom:.8rem!important;}
  .invoice-actions{display:grid;grid-template-columns:1fr;gap:.35rem;white-space:normal;}
  .invoice-actions .btn,.invoice-actions form,.invoice-actions button{width:100%;margin:0;}
}
</style>
<script>
(function(){
  var rows = document.getElementById('invoiceRows');
  var tpl = document.getElementById('invoiceRowTpl').innerHTML;
  var idx = 0;
  var customerUrl = <?php echo json_encode($customerSearchUrl); ?>;
  var productSearchUrl = <?php echo json_encode($productSearchUrl); ?>;
  function money(n){ return 'KES ' + (Math.round(n * 100) / 100).toLocaleString('en-KE', {maximumFractionDigits:2}); }
  function formatQty(n){ return (Math.round((parseFloat(n) || 0) * 100) / 100).toLocaleString('en-KE', {maximumFractionDigits:2}); }
  function attachCustomerLookup(){
    var input = document.getElementById('customerName');
    var menu = document.getElementById('customerSuggestMenu');
    var id = document.getElementById('customerIdInput');
    if (!input || !menu || !id) return;
    var timer = null, picked = '';
    function hide(){ menu.classList.remove('show'); }
    function render(items){
      menu.innerHTML = '';
      if (!items.length) { hide(); return; }
      items.forEach(function(c){
        var b = document.createElement('button');
        b.type = 'button';
        b.innerHTML = '<strong>' + (c.name || '') + '</strong><span class="meta">' + [c.phone, c.email, c.company_name].filter(Boolean).join(' · ') + '</span>';
        b.addEventListener('mousedown', function(e){
          e.preventDefault();
          picked = c.name || '';
          id.value = c.id || '';
          input.value = picked;
          document.getElementById('customerPhone').value = c.phone || '';
          document.getElementById('customerEmail').value = c.email || '';
          hide();
        });
        menu.appendChild(b);
      });
      menu.classList.add('show');
    }
    input.addEventListener('input', function(){
      if (picked && input.value !== picked) { id.value = ''; picked = ''; }
      clearTimeout(timer);
      var q = input.value.trim();
      if (q.length < 2) { hide(); return; }
      timer = setTimeout(function(){
        fetch(customerUrl + '?q=' + encodeURIComponent(q) + '&limit=8')
          .then(function(r){ return r.json(); })
          .then(function(data){ render(data.items || []); })
          .catch(function(){});
      }, 180);
    });
    input.addEventListener('blur', function(){ setTimeout(hide, 160); });
  }
  function addRow(){
    rows.insertAdjacentHTML('beforeend', tpl.replaceAll('__I__', idx++));
    var row = rows.lastElementChild;
    row.querySelector('.invoice-price-type').value = document.getElementById('saleType').value === 'wholesale' ? 'wholesale' : 'retail';
    wire(row);
    recalc();
  }
  function rowSaleType(row) {
    var el = row.querySelector('.invoice-price-type');
    return el && el.value === 'wholesale' ? 'wholesale' : 'retail';
  }
  function rowMeta(row){
    return {
      retail: parseFloat(row.dataset.retailPrice) || 0,
      wholesale: parseFloat(row.dataset.wholesalePrice) || 0,
      stock: parseFloat(row.dataset.stock) || 0,
      unitsPerPack: parseFloat(row.dataset.unitsPerPack) || 1,
      packUnit: row.dataset.packUnit || '',
      packPrice: parseFloat(row.dataset.packPrice) || 0
    };
  }
  function rowPrice(row){
    var m = rowMeta(row);
    var type = rowSaleType(row);
    if (type === 'wholesale' && m.packUnit && m.unitsPerPack > 1 && m.packPrice > 0) return m.packPrice;
    return type === 'wholesale' ? m.wholesale : m.retail;
  }
  function recalc(){
    var total = 0;
    rows.querySelectorAll('.invoice-row').forEach(function(row){
      var m = rowMeta(row);
      var qty = parseFloat(row.querySelector('.invoice-qty').value) || 0;
      var byPack = rowSaleType(row) === 'wholesale' && m.packUnit && m.unitsPerPack > 1 && m.packPrice > 0;
      var max = byPack ? Math.floor((m.stock / m.unitsPerPack) * 100) / 100 : m.stock;
      if (qty > max) { qty = max; row.querySelector('.invoice-qty').value = max || ''; }
      row.querySelector('.qty-label').textContent = byPack ? ('Qty (' + m.packUnit + ')') : 'Qty';
      row.querySelector('.stock-qty').value = (row.querySelector('.product-id').value && qty > 0)
        ? (qty * (byPack ? m.unitsPerPack : 1)).toFixed(2) : '';
      var line = qty * rowPrice(row);
      row.querySelector('.line-total').textContent = money(line);
      total += line;
    });
    total = Math.max(0, total - (parseFloat(document.getElementById('discountAmount').value) || 0));
    total += Math.max(0, parseFloat((document.getElementById('extraChargeAmount') || {}).value) || 0);
    document.getElementById('invoiceTotal').textContent = money(total);
  }
  function attachProductSearch(row){
    var input = row.querySelector('.product-search');
    var hidden = row.querySelector('.product-id');
    var menu = row.querySelector('.product-search-menu');
    var selected = row.querySelector('.selected-product');
    if (!input || !hidden || !menu) return;
    var timer = null;
    function hide(){ menu.classList.remove('show'); }
    function clearPick(){
      hidden.value = '';
      row.dataset.retailPrice = '0';
      row.dataset.wholesalePrice = '0';
      row.dataset.stock = '0';
      row.dataset.unitsPerPack = '1';
      row.dataset.packUnit = '';
      row.dataset.packPrice = '0';
      if (selected) selected.textContent = 'No product selected';
    }
    function render(items){
      menu.innerHTML = '';
      if (!items.length) { hide(); return; }
      items.forEach(function(p){
        var btn = document.createElement('button');
        btn.type = 'button';
        var meta = ['Stock ' + formatQty(p.stock) + ' ' + (p.unit || ''), p.barcode, p.category_name, p.brand_name].filter(Boolean).join(' · ');
        btn.innerHTML = '<strong></strong><span class="meta"></span>';
        btn.querySelector('strong').textContent = p.name || '';
        btn.querySelector('.meta').textContent = meta;
        btn.addEventListener('mousedown', function(e){
          e.preventDefault();
          hidden.value = p.id || '';
          input.value = p.name || '';
          row.dataset.retailPrice = p.retail_price || 0;
          row.dataset.wholesalePrice = p.wholesale_price || p.retail_price || 0;
          row.dataset.stock = p.stock || 0;
          row.dataset.unitsPerPack = p.units_per_pack || 1;
          row.dataset.packUnit = p.pack_unit || '';
          row.dataset.packPrice = p.pack_price || 0;
          if (selected) {
            selected.textContent = 'Selected: ' + (p.name || '') + ' · stock ' + formatQty(p.stock) + ' ' + (p.unit || '') + ' · ' + money(p.retail_price || 0);
          }
          hide();
          recalc();
        });
        menu.appendChild(btn);
      });
      menu.classList.add('show');
    }
    input.addEventListener('input', function(){
      clearPick();
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
  function wire(row){
    attachProductSearch(row);
    row.querySelector('.invoice-qty').addEventListener('input', recalc);
    row.querySelector('.invoice-price-type').addEventListener('change', recalc);
    row.querySelector('.remove-invoice-row').addEventListener('click', function(){ row.remove(); recalc(); });
  }
  document.getElementById('addInvoiceRow').addEventListener('click', addRow);
  document.getElementById('saleType').addEventListener('change', function(){
    var type = document.getElementById('saleType').value === 'wholesale' ? 'wholesale' : 'retail';
    rows.querySelectorAll('.invoice-price-type').forEach(function(select){ select.value = type; });
    recalc();
  });
  document.getElementById('discountAmount').addEventListener('input', recalc);
  var extraChargeAmount = document.getElementById('extraChargeAmount');
  if (extraChargeAmount) extraChargeAmount.addEventListener('input', recalc);
  document.getElementById('invoiceForm').addEventListener('submit', function(e){
    var ok = false;
    rows.querySelectorAll('.invoice-row').forEach(function(row){
      if ((row.querySelector('.product-id').value || '') && (parseFloat(row.querySelector('.invoice-qty').value) || 0) > 0) ok = true;
    });
    if (!ok) {
      e.preventDefault();
      alert('Search and select at least one product, then enter quantity.');
    }
  });
  attachCustomerLookup();
  addRow();
})();
</script>
<style>
.invoice-suggest-menu{position:absolute;left:0;right:0;top:100%;z-index:40;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 12px 28px rgba(15,23,42,.14);display:none;max-height:320px;overflow:auto;margin-top:4px;}
.invoice-suggest-menu.show{display:block;}
.invoice-suggest-menu button{display:block;width:100%;border:0;background:#fff;text-align:left;padding:.7rem .85rem;border-bottom:1px solid #f1f5f9;}
.invoice-suggest-menu button:last-child{border-bottom:0;}
.invoice-suggest-menu button:hover{background:#f8fafc;}
.invoice-suggest-menu .meta{display:block;color:#64748b;font-size:.78rem;margin-top:2px;}
.invoice-suggest-menu .bal{float:right;font-weight:700;color:#b91c1c;}
.invoice-suggest-menu .bal.paid{color:#15803d;}
</style>
<script>
(function(){
  var input = document.getElementById('invoiceListSearch');
  var menu = document.getElementById('invoiceListSuggestMenu');
  var api = <?php echo json_encode($invoiceSearchApi); ?>;
  if (!input || !menu) return;
  var timer = null;
  function hide(){ menu.classList.remove('show'); }
  function money(n){ return 'KES ' + (Number(n)||0).toLocaleString('en-KE', {maximumFractionDigits:0}); }
  function render(items){
    menu.innerHTML = '';
    if (!items.length) { hide(); return; }
    items.forEach(function(it){
      var b = document.createElement('button');
      b.type = 'button';
      var title = (it.customer_name || 'Customer') + (it.company_name ? ' · ' + it.company_name : '');
      var balClass = (Number(it.balance) || 0) > 0 ? 'bal' : 'bal paid';
      b.innerHTML = '<span class="' + balClass + '">' + money(it.balance) + '</span><strong></strong><span class="meta"></span>';
      b.querySelector('strong').textContent = title;
      b.querySelector('.meta').textContent = (it.receipt_number || '') + (it.status === 'paid' ? ' · Paid' : ' · Unpaid') + (it.phone ? ' · ' + it.phone : '');
      b.addEventListener('mousedown', function(e){
        e.preventDefault();
        window.location.href = '?q=' + encodeURIComponent(it.receipt_number || it.customer_name || '');
      });
      menu.appendChild(b);
    });
    menu.classList.add('show');
  }
  input.addEventListener('input', function(){
    clearTimeout(timer);
    var q = input.value.trim();
    if (q.length < 1) { hide(); return; }
    timer = setTimeout(function(){
      fetch(api + '?q=' + encodeURIComponent(q) + '&limit=12&open_only=0')
        .then(function(r){ return r.json(); })
        .then(function(data){ render(data.items || []); })
        .catch(function(){ hide(); });
    }, 180);
  });
  input.addEventListener('blur', function(){ setTimeout(hide, 160); });
  input.addEventListener('keydown', function(e){
    if (e.key === 'Escape') hide();
  });
})();
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
