<?php
// public/super/purchases/transfer.php — move purchase items into Store warehouse
// with optional wholesale/retail prices for packages and inner items.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::STOCK_ENTER);

$pdo = Database::pdo();
$PUR = new Models\PurchaseModel($pdo);
$base = public_url('super/purchases/');
$storeUrl = public_url('super/store/');

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'purchase_id' => (int) ($_GET['purchase_id'] ?? 0),
];

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = $_POST['selected'] ?? [];
    $lines = $_POST['lines'] ?? [];
    $selections = [];
    foreach ((array) $selected as $id) {
        $id = (int) $id;
        if ($id <= 0) {
            continue;
        }
        $selections[$id] = is_array($lines[$id] ?? null) ? $lines[$id] : [];
    }
    $res = $PUR->transferToStore($selections, (int) TenantContext::userId());
    if ($res['ok']) {
        $_SESSION['flash']['success'] = $res['created'] . ' item' . ($res['created'] === 1 ? '' : 's')
            . ' moved to Store warehouse. Generate a Store transfer invoice when ready for shop Inventory.';
        header('Location: ' . $storeUrl);
        exit;
    }
    $error = $res['error'] ?? 'Transfer failed.';
}

$pending = $PUR->pendingItems($filters, 250);

$page_title = 'Transfer purchases';
ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h1 class="h5 fw-bold mb-1">Transfer purchases → Store</h1>
    <p class="text-muted small mb-0">Move all or part of a purchase into Store (e.g. 2,000 kg of 10,000 kg). Set optional sell prices and quantity discounts (buy ≥ 10 kg → KES 100 off, or cheaper unit price). Then invoice from Store into shop Inventory for POS.</p>
  </div>
  <a class="btn btn-sm btn-outline-secondary" href="<?php echo $base; ?>">Purchases</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<form method="get" class="card border-0 shadow-sm mb-3" style="border-radius:12px;">
  <div class="card-body p-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-7">
        <label class="form-label small mb-1">Advanced search</label>
        <div class="input-group">
          <span class="input-group-text bg-white"><i class="fas fa-magnifying-glass text-muted"></i></span>
          <input type="search" name="q" class="form-control" placeholder="Product, variant, supplier, receipt…" value="<?php echo htmlspecialchars($filters['q']); ?>">
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Purchase ID</label>
        <input type="number" name="purchase_id" class="form-control" min="0" value="<?php echo $filters['purchase_id'] ?: ''; ?>" placeholder="optional">
      </div>
      <div class="col-md-2">
        <button class="btn btn-outline-primary w-100">Filter</button>
      </div>
    </div>
  </div>
</form>

<?php if (!$pending): ?>
  <div class="card border-0 shadow-sm" style="border-radius:14px;">
    <div class="card-body p-5 text-center text-muted">
      No pending purchase items to transfer.
      <div class="mt-2"><a href="<?php echo $base; ?>new.php">Record a purchase</a></div>
    </div>
  </div>
<?php else: ?>
<form method="post" id="transferForm">
  <div class="card border-0 shadow-sm mb-3" style="border-radius:14px;overflow:hidden;">
    <div class="px-3 py-2 d-flex justify-content-between align-items-center" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
      <div class="form-check mb-0">
        <input class="form-check-input" type="checkbox" id="selectAll">
        <label class="form-check-label small fw-semibold" for="selectAll">Select all (<?php echo count($pending); ?>)</label>
      </div>
      <div class="small text-muted">All sell-price fields are optional</div>
    </div>
    <div class="p-3">
      <?php foreach ($pending as $it):
          $id = (int) $it['id'];
          $pkgUnit = $it['package_unit'] ?: 'package';
          $innerUnit = $it['unit'] ?: 'piece';
          $inside = (float) ($it['units_per_package'] ?? 1);
          $pkgQty = (float) ($it['package_quantity'] ?? 0);
          $pkgBuy = (float) ($it['package_buying_price'] ?? 0);
          $unitBuy = (float) ($it['buying_price'] ?? 0);
          $availQty = (float) ($it['quantity'] ?? 0);
          $shop = $it['shop_name'] ?: ($it['supplier_name'] ?: '—');
          $isContinuous = Models\ProductModel::isContinuousUnit($innerUnit);
      ?>
      <div class="border rounded mb-3 p-3 transfer-row" style="border-color:#e2e8f0!important;" data-id="<?php echo $id; ?>"
           data-pkg-buy="<?php echo htmlspecialchars((string) $pkgBuy); ?>"
           data-unit-buy="<?php echo htmlspecialchars((string) $unitBuy); ?>"
           data-pkg-qty="<?php echo htmlspecialchars((string) $pkgQty); ?>"
           data-qty="<?php echo htmlspecialchars((string) $availQty); ?>"
           data-inside="<?php echo htmlspecialchars((string) $inside); ?>"
           data-continuous="<?php echo $isContinuous ? '1' : '0'; ?>">
        <div class="d-flex gap-2 align-items-start mb-2">
          <input class="form-check-input mt-1 row-check" type="checkbox" name="selected[]" value="<?php echo $id; ?>" id="sel<?php echo $id; ?>">
          <div class="flex-grow-1">
            <label for="sel<?php echo $id; ?>" class="fw-semibold mb-0" style="cursor:pointer;">
              <?php echo htmlspecialchars($it['name'] ?: 'Untitled'); ?>
              <?php if (!empty($it['variant_label'])): ?>
                <span class="text-muted fw-normal">(<?php echo htmlspecialchars($it['variant_label']); ?>)</span>
              <?php endif; ?>
            </label>
            <div class="text-muted small">
              Purchase #<?php echo (int) $it['purchase_id']; ?> · <?php echo htmlspecialchars($shop); ?>
              <?php if (!empty($it['receipt_number'])): ?> · Rcpt <?php echo htmlspecialchars($it['receipt_number']); ?><?php endif; ?>
              · Available:
              <?php echo $pkgQty > 0
                ? htmlspecialchars(rtrim(rtrim(number_format($pkgQty, 2), '0'), '.') . ' ' . $pkgUnit . ' × ' . rtrim(rtrim(number_format($inside, 2), '0'), '.') . ' ' . $innerUnit)
                : htmlspecialchars(rtrim(rtrim(number_format($availQty, 2), '0'), '.') . ' ' . $innerUnit); ?>
              · Cost <?php echo $pkgBuy > 0 ? ('KES ' . number_format($pkgBuy, 0) . '/' . $pkgUnit) : ('KES ' . number_format($unitBuy, 2) . '/' . $innerUnit); ?>
            </div>
          </div>
        </div>
        <div class="row g-2 transfer-fields" style="opacity:.55;">
          <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Transfer qty (<?php echo htmlspecialchars($innerUnit); ?>)</label>
            <input type="number" step="0.01" min="0" max="<?php echo htmlspecialchars((string) $availQty); ?>" name="lines[<?php echo $id; ?>][transfer_quantity]" class="form-control form-control-sm f-xfer-qty" placeholder="<?php echo htmlspecialchars(rtrim(rtrim(number_format($availQty, 2), '0'), '.') ?: '0'); ?>" value="">
            <div class="form-text">Leave blank to move all <?php echo htmlspecialchars(rtrim(rtrim(number_format($availQty, 2), '0'), '.')); ?> <?php echo htmlspecialchars($innerUnit); ?>.</div>
          </div>
          <?php if ($pkgQty > 0): ?>
          <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Or transfer # of <?php echo htmlspecialchars($pkgUnit); ?>s</label>
            <input type="number" step="0.01" min="0" max="<?php echo htmlspecialchars((string) $pkgQty); ?>" name="lines[<?php echo $id; ?>][transfer_packages]" class="form-control form-control-sm f-xfer-pkg" placeholder="<?php echo htmlspecialchars(rtrim(rtrim(number_format($pkgQty, 2), '0'), '.')); ?>" value="">
          </div>
          <?php endif; ?>
          <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Wholesale / <?php echo htmlspecialchars($pkgUnit); ?></label>
            <input type="number" step="0.01" min="0" name="lines[<?php echo $id; ?>][package_price]" class="form-control form-control-sm f-ws-pack" placeholder="optional" value="<?php echo htmlspecialchars((string) ($it['package_price'] ?? '')); ?>">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Retail / <?php echo htmlspecialchars($pkgUnit); ?></label>
            <input type="number" step="0.01" min="0" name="lines[<?php echo $id; ?>][retail_pack_price]" class="form-control form-control-sm f-rt-pack" placeholder="optional" value="<?php echo htmlspecialchars((string) ($it['retail_pack_price'] ?? '')); ?>">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Retail / <?php echo htmlspecialchars($innerUnit); ?></label>
            <input type="number" step="0.01" min="0" name="lines[<?php echo $id; ?>][retail_price]" class="form-control form-control-sm f-rt-item" placeholder="optional" value="<?php echo htmlspecialchars((string) ($it['retail_price'] ?? '')); ?>">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Wholesale / <?php echo htmlspecialchars($innerUnit); ?></label>
            <input type="number" step="0.01" min="0" name="lines[<?php echo $id; ?>][wholesale_price]" class="form-control form-control-sm f-ws-item" placeholder="optional" value="<?php echo htmlspecialchars((string) ($it['wholesale_price'] ?? '')); ?>">
          </div>
          <div class="col-12">
            <div class="border rounded p-2" style="background:#fafbfc;border-color:#e2e8f0!important;">
              <div class="small fw-semibold mb-2">Quantity discounts <span class="text-muted fw-normal">(optional — admin sets these)</span></div>
              <div class="text-muted mb-2" style="font-size:.75rem;">Example: buy ≥ 10 <?php echo htmlspecialchars($innerUnit); ?> → KES 100 off, or set a cheaper unit price for bulk.</div>
              <?php for ($ti = 0; $ti < 2; $ti++): ?>
              <div class="row g-2 mb-1">
                <div class="col-6 col-md-2"><input type="number" step="0.01" min="0" name="lines[<?php echo $id; ?>][tiers][<?php echo $ti; ?>][min_qty]" class="form-control form-control-sm" placeholder="Min qty"></div>
                <div class="col-6 col-md-2"><input type="number" step="0.01" min="0" name="lines[<?php echo $id; ?>][tiers][<?php echo $ti; ?>][max_qty]" class="form-control form-control-sm" placeholder="Max (opt)"></div>
                <div class="col-6 col-md-2"><input type="number" step="0.01" min="0" name="lines[<?php echo $id; ?>][tiers][<?php echo $ti; ?>][unit_price]" class="form-control form-control-sm" placeholder="Unit price"></div>
                <div class="col-6 col-md-3"><input type="number" step="0.01" min="0" name="lines[<?php echo $id; ?>][tiers][<?php echo $ti; ?>][discount_amount]" class="form-control form-control-sm" placeholder="KES off total"></div>
                <div class="col-12 col-md-3"><input type="text" name="lines[<?php echo $id; ?>][tiers][<?php echo $ti; ?>][label]" class="form-control form-control-sm" placeholder="Label e.g. Bulk 10kg+"></div>
              </div>
              <?php endfor; ?>
            </div>
          </div>
          <div class="col-12">
            <div class="profit-box alert alert-light border small py-2 mb-0" style="display:none;"></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-3">
      <div id="transferProfitSummary" class="small text-muted mb-2">Select items to preview expected profit margins.</div>
      <button class="btn btn-primary btn-lg" id="transferBtn" disabled>
        <i class="fas fa-right-left me-1"></i>Transfer selected to Store warehouse
      </button>
    </div>
  </div>
</form>

<script>
(function () {
  function money(n) { return 'KES ' + (Math.round((n || 0) * 100) / 100).toLocaleString(); }
  function num(el) { return parseFloat(el && el.value ? el.value : 0) || 0; }

  function rowProfit(row) {
    var pkgBuy = parseFloat(row.dataset.pkgBuy) || 0;
    var unitBuy = parseFloat(row.dataset.unitBuy) || 0;
    var availPkg = parseFloat(row.dataset.pkgQty) || 0;
    var availQty = parseFloat(row.dataset.qty) || 0;
    var inside = parseFloat(row.dataset.inside) || 1;
    var xferQtyEl = row.querySelector('.f-xfer-qty');
    var xferPkgEl = row.querySelector('.f-xfer-pkg');
    var xferQty = num(xferQtyEl);
    var xferPkg = num(xferPkgEl);
    var qty = availQty;
    var pkgQty = availPkg;
    if (xferPkg > 0) {
      pkgQty = Math.min(availPkg || xferPkg, xferPkg);
      qty = Math.min(availQty, Math.round(pkgQty * inside * 100) / 100);
    } else if (xferQty > 0) {
      qty = Math.min(availQty, xferQty);
      pkgQty = availPkg > 0 && inside > 0 ? Math.round((qty / inside) * 100) / 100 : 0;
    }
    var wsPack = num(row.querySelector('.f-ws-pack'));
    var rtPack = num(row.querySelector('.f-rt-pack'));
    var rtItem = num(row.querySelector('.f-rt-item'));
    var wsItem = num(row.querySelector('.f-ws-item'));
    var cost = pkgQty > 0 && pkgBuy > 0 ? pkgQty * pkgBuy : qty * unitBuy;
    var wholesaleReturn = pkgQty > 0 && wsPack > 0 ? pkgQty * wsPack : (qty * wsItem);
    var retailReturn = pkgQty > 0 && rtPack > 0 ? pkgQty * rtPack : (qty * rtItem);
    return { cost: cost, wholesaleReturn: wholesaleReturn, retailReturn: retailReturn, qty: qty };
  }

  function refreshRow(row) {
    var checked = row.querySelector('.row-check').checked;
    var fields = row.querySelector('.transfer-fields');
    fields.style.opacity = checked ? '1' : '.55';
    var box = row.querySelector('.profit-box');
    var p = rowProfit(row);
    if (!checked || (p.cost <= 0 && p.wholesaleReturn <= 0 && p.retailReturn <= 0)) {
      box.style.display = 'none';
      return;
    }
    var wp = p.wholesaleReturn - p.cost;
    var rp = p.retailReturn - p.cost;
    var wm = p.wholesaleReturn > 0 ? (wp / p.wholesaleReturn * 100) : 0;
    var rm = p.retailReturn > 0 ? (rp / p.retailReturn * 100) : 0;
    box.style.display = 'block';
    box.innerHTML = '<strong>Profit preview</strong> for ' + (Math.round(p.qty * 100) / 100) + ' units · Cost ' + money(p.cost)
      + ' · Wholesale ' + money(p.wholesaleReturn) + ' (<span class="' + (wp < 0 ? 'text-danger' : 'text-success') + '">' + money(wp) + ' / ' + wm.toFixed(1) + '%</span>)'
      + ' · Retail ' + money(p.retailReturn) + ' (<span class="' + (rp < 0 ? 'text-danger' : 'text-success') + '">' + money(rp) + ' / ' + rm.toFixed(1) + '%</span>)';
  }

  function refreshAll() {
    var rows = document.querySelectorAll('.transfer-row');
    var selected = 0, cost = 0, ws = 0, rt = 0;
    rows.forEach(function (row) {
      refreshRow(row);
      if (row.querySelector('.row-check').checked) {
        selected++;
        var p = rowProfit(row);
        cost += p.cost; ws += p.wholesaleReturn; rt += p.retailReturn;
      }
    });
    document.getElementById('transferBtn').disabled = selected === 0;
    var summary = document.getElementById('transferProfitSummary');
    if (selected === 0) {
      summary.textContent = 'Select items to preview expected profit margins.';
      summary.className = 'small text-muted mb-2';
      return;
    }
    var wp = ws - cost, rp = rt - cost;
    summary.className = 'small mb-2';
    summary.innerHTML = '<strong>' + selected + ' selected</strong> · Cost <strong>' + money(cost) + '</strong>'
      + ' · Wholesale return <strong>' + money(ws) + '</strong> <span class="' + (wp < 0 ? 'text-danger' : 'text-success') + '">(profit ' + money(wp) + ')</span>'
      + ' · Retail return <strong>' + money(rt) + '</strong> <span class="' + (rp < 0 ? 'text-danger' : 'text-success') + '">(profit ' + money(rp) + ')</span>';
  }

  document.getElementById('selectAll').addEventListener('change', function () {
    document.querySelectorAll('.row-check').forEach(function (c) { c.checked = document.getElementById('selectAll').checked; });
    refreshAll();
  });
  document.querySelectorAll('.row-check, .f-ws-pack, .f-rt-pack, .f-rt-item, .f-ws-item, .f-xfer-qty, .f-xfer-pkg').forEach(function (el) {
    el.addEventListener('change', refreshAll);
    el.addEventListener('input', refreshAll);
  });
  document.getElementById('transferForm').addEventListener('submit', function (e) {
    var n = document.querySelectorAll('.row-check:checked').length;
    if (!n) { e.preventDefault(); return; }
    if (!confirm('Transfer ' + n + ' purchase item(s) into Store warehouse? Partial amounts leave the rest pending.')) e.preventDefault();
  });
  refreshAll();
})();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
$__layout = TenantContext::role() === 'staff' ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
