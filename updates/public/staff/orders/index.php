<?php
// public/staff/orders/index.php — open credit invoices with advanced search
// (customer / company / invoice # / balance). Deposit & edit from here.
// Reached from staff and owner (super/orders/ wraps this).
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::SALES_RECORD);

$pdo = Database::pdo();
$O = new Models\OrderModel($pdo);

$isStaffViewer = TenantContext::role() === 'staff';
$viewBase = $isStaffViewer ? public_url('staff/orders/view.php') : public_url('super/orders/view.php');
$newBase = $isStaffViewer ? public_url('staff/orders/new.php') : public_url('super/orders/new.php');
$paymentsBase = $isStaffViewer ? public_url('staff/payments/') : public_url('super/payments/');
$editBase = $isStaffViewer ? public_url('staff/invoices/edit.php') : public_url('super/invoices/edit.php');
$searchApi = public_url('api/orders/invoice_search.php');

$q = trim((string) ($_GET['q'] ?? ''));
$orders = $q !== ''
    ? $O->searchInvoices($q, ['open_only' => true, 'limit' => 80])
    : $O->openOrders();

$page_title = 'Credit sales';
ob_start();

$displayCustomer = static function (array $o): array {
    $name = trim((string) ($o['table_name'] ?? ''));
    $company = trim((string) ($o['customer_company'] ?? ''));
    if ($name === '' && !empty($o['customer_record_name'])) {
        $name = trim((string) $o['customer_record_name']);
    }
    return [$name !== '' ? $name : '—', $company];
};
$balanceOf = static function (array $o): float {
    $paid = max(0, (float) ($o['amount_paid'] ?? 0));
    $due = (float) ($o['balance_due'] ?? $o['amount_due'] ?? 0);
    if ($due <= 0.0001) {
        $due = max(0, (float) ($o['total'] ?? 0) - $paid);
    }
    return $due;
};
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h1 class="h5 mb-0 fw-bold"><i class="fas fa-receipt me-2 text-warning"></i>Credit sales</h1>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?php echo $paymentsBase; ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-hand-holding-dollar me-1"></i>Deposits / Payments</a>
    <a href="<?php echo $newBase; ?>" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>New credit sale</a>
  </div>
</div>

<div class="card border-0 shadow-sm mb-3" style="border-radius:14px;">
  <div class="card-body p-3">
    <form method="get" class="row g-2 align-items-end" id="invoiceSearchForm" autocomplete="off">
      <div class="col-12 col-md-9 position-relative">
        <label class="form-label small mb-1">Search invoices</label>
        <input type="search" name="q" id="invoiceSearch" class="form-control form-control-lg"
               placeholder="Customer name, company, or invoice number…"
               value="<?php echo htmlspecialchars($q); ?>" autofocus>
        <div class="invoice-suggest-menu" id="invoiceSuggestMenu"></div>
        <div class="form-text">Shows customer / company, invoice number, and balance owed.</div>
      </div>
      <div class="col-6 col-md-2">
        <button class="btn btn-primary btn-lg w-100"><i class="fas fa-magnifying-glass me-1"></i>Find</button>
      </div>
      <div class="col-6 col-md-1">
        <?php if ($q !== ''): ?><a class="btn btn-outline-secondary btn-lg w-100" href="<?php echo $isStaffViewer ? public_url('staff/orders/') : public_url('super/orders/'); ?>">Clear</a><?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php if (!$orders): ?>
  <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-5 text-center text-muted">
    <i class="fas fa-inbox fa-2x mb-2 d-block" style="opacity:.3;"></i>
    <?php echo $q !== '' ? 'No open invoices match that search.' : 'No open credit invoices right now.'; ?>
  </div></div>
<?php else: ?>
  <div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
    <div class="table-responsive">
      <table class="table align-middle mb-0 credit-invoice-table">
        <thead>
          <tr class="text-muted small text-uppercase">
            <th>Customer / company</th>
            <th>Invoice</th>
            <th class="text-end">Balance</th>
            <th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o):
            [$custName, $company] = $displayCustomer($o);
            $paid = max(0, (float) ($o['amount_paid'] ?? 0));
            $due = $balanceOf($o);
          ?>
          <tr>
            <td>
              <div class="fw-semibold"><?php echo htmlspecialchars($custName); ?></div>
              <?php if ($company !== ''): ?><div class="text-muted small"><?php echo htmlspecialchars($company); ?></div><?php endif; ?>
              <?php if (!empty($o['customer_phone'])): ?><div class="text-muted small"><?php echo htmlspecialchars($o['customer_phone']); ?></div><?php endif; ?>
            </td>
            <td>
              <div class="fw-semibold"><?php echo htmlspecialchars($o['receipt_number'] ?? ''); ?></div>
              <div class="text-muted small">
                <?php echo (int) ($o['item_count'] ?? 0); ?> item<?php echo (int) ($o['item_count'] ?? 0) === 1 ? '' : 's'; ?>
                · <?php echo date('j M, g:i a', strtotime($o['created_at'])); ?>
              </div>
              <?php if (!empty($o['credit_due_at'])): ?>
                <div class="small <?php echo strtotime($o['credit_due_at']) < time() ? 'text-danger' : 'text-muted'; ?>">
                  Due <?php echo date('j M Y', strtotime($o['credit_due_at'])); ?>
                  <?php if (!empty($o['credit_duration_days'])): ?> · <?php echo (int) $o['credit_duration_days']; ?> days<?php endif; ?>
                </div>
              <?php elseif (!empty($o['credit_duration_days'])): ?>
                <div class="small text-muted">Credit <?php echo (int) $o['credit_duration_days']; ?> days</div>
              <?php endif; ?>
              <?php if ($paid > 0): ?><div class="small text-success">KES <?php echo number_format($paid, 0); ?> paid so far</div><?php endif; ?>
            </td>
            <td class="text-end">
              <div class="fw-bold fs-5 text-danger">KES <?php echo number_format($due, 0); ?></div>
            </td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-success" href="<?php echo $paymentsBase . '?receipt=' . urlencode($o['receipt_number']) . '&deposit=1'; ?>" title="Record a deposit"><i class="fas fa-hand-holding-dollar me-1"></i>Deposit</a>
              <a class="btn btn-sm btn-outline-primary" href="<?php echo $editBase . '?id=' . (int) $o['id']; ?>" title="Add or remove products"><i class="fas fa-pen me-1"></i>Edit</a>
              <a class="btn btn-sm btn-outline-secondary" href="<?php echo $viewBase . '?id=' . (int) $o['id']; ?>">Open</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<style>
.invoice-suggest-menu{position:absolute;left:0;right:0;top:100%;z-index:40;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 12px 28px rgba(15,23,42,.14);display:none;max-height:320px;overflow:auto;margin-top:4px;}
.invoice-suggest-menu.show{display:block;}
.invoice-suggest-menu button{display:block;width:100%;border:0;background:#fff;text-align:left;padding:.7rem .85rem;border-bottom:1px solid #f1f5f9;}
.invoice-suggest-menu button:last-child{border-bottom:0;}
.invoice-suggest-menu button:hover{background:#f8fafc;}
.invoice-suggest-menu .meta{display:block;color:#64748b;font-size:.78rem;margin-top:2px;}
.invoice-suggest-menu .bal{float:right;font-weight:700;color:#b91c1c;}
.credit-invoice-table th,.credit-invoice-table td{padding:.85rem 1rem;vertical-align:middle;}
@media (max-width:768px){
  .credit-invoice-table thead{display:none;}
  .credit-invoice-table tr{display:block;border-bottom:1px solid #eef0f4;padding:.5rem 0;}
  .credit-invoice-table td{display:block;text-align:left!important;padding:.25rem 1rem;}
  .credit-invoice-table td.text-end{text-align:left!important;}
}
</style>
<script>
(function(){
  var input = document.getElementById('invoiceSearch');
  var menu = document.getElementById('invoiceSuggestMenu');
  var api = <?php echo json_encode($searchApi); ?>;
  var paymentsBase = <?php echo json_encode($paymentsBase); ?>;
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
      b.innerHTML = '<span class="bal">' + money(it.balance) + '</span><strong></strong><span class="meta"></span>';
      b.querySelector('strong').textContent = title;
      b.querySelector('.meta').textContent = (it.receipt_number || '') + (it.phone ? ' · ' + it.phone : '');
      b.addEventListener('mousedown', function(e){
        e.preventDefault();
        window.location.href = paymentsBase + '?receipt=' + encodeURIComponent(it.receipt_number) + '&deposit=1';
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
      fetch(api + '?q=' + encodeURIComponent(q) + '&limit=12&open_only=1')
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
$__layout = $isStaffViewer ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
