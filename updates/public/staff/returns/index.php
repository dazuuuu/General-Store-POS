<?php
// public/staff/returns/index.php
// Returns desk: find the original receipt, choose the exact sold product, and
// record how much came back plus how much of it was used/not sellable.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::SALES_RECORD);

$pdo = Database::pdo();
$R = new Models\ReturnModel($pdo);
$isStaffViewer = TenantContext::role() === 'staff';
$returnsBase = $isStaffViewer ? public_url('staff/returns/') : public_url('super/returns/');
$receiptBase = $isStaffViewer ? public_url('staff/orders/receipt.php') : public_url('super/orders/receipt.php');
$saleReceiptBase = $isStaffViewer ? public_url('staff/sales/receipt.php') : public_url('super/sales/receipt.php');

$error = '';
$receiptQuery = trim($_GET['receipt'] ?? $_POST['receipt_number'] ?? '');
$source = $receiptQuery !== '' ? $R->findReceipt($receiptQuery) : null;

if ($receiptQuery !== '' && !$source) {
    $error = 'No sale found with that receipt number.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'return' && $source) {
    $res = $R->record([
        'source_type' => $_POST['source_type'] ?? '',
        'source_id' => $_POST['source_id'] ?? 0,
        'source_item_id' => $_POST['source_item_id'] ?? 0,
        'returned_quantity' => $_POST['returned_quantity'] ?? 0,
        'used_quantity' => $_POST['used_quantity'] ?? 0,
        'reason' => $_POST['reason'] ?? '',
        'note' => $_POST['note'] ?? '',
    ], TenantContext::userId());
    if ($res['ok']) {
        $_SESSION['flash']['success'] = 'Return recorded for ' . strtoupper($receiptQuery) . '. Stock and credit balance adjusted.';
        header('Location: ' . $returnsBase . '?receipt=' . urlencode($receiptQuery));
        exit;
    }
    $error = $res['error'] ?? 'Could not record the return.';
}

$items = $source ? $R->receiptItems($source['source_type'], (int) $source['id']) : [];
$recent = $R->recent(80);
$page_title = 'Returns';
ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h1 class="h5 mb-0 fw-bold"><i class="fas fa-rotate-left me-2 text-primary"></i>Returns</h1>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <div class="card-body p-4">
    <form method="get" class="row g-2 align-items-end" id="receiptSearchForm">
      <div class="col-12 col-sm-8">
        <label class="form-label small mb-1 fw-semibold"><i class="fas fa-magnifying-glass me-1 text-primary"></i>Original receipt or customer / product</label>
        <div class="position-relative">
          <input type="text" name="receipt" id="receiptSearchInput" class="form-control form-control-lg text-uppercase" placeholder="Type receipt #, customer name, or product..."
                 value="<?php echo htmlspecialchars($receiptQuery); ?>" autocomplete="off" autofocus>
          <div id="receiptSuggestMenu" class="dropdown-menu shadow-lg border-0 w-100 mt-1 py-0" style="max-height:360px;overflow-y:auto;display:none;z-index:1060;border-radius:10px;"></div>
        </div>
        <div class="text-muted small mt-1" style="font-size:0.75rem;">Type receipt #, customer, or product to see live suggestions, then click or press Enter to autofill</div>
      </div>
      <div class="col-12 col-sm-4">
        <button class="btn btn-primary btn-lg w-100" id="receiptSearchBtn"><i class="fas fa-magnifying-glass me-1"></i>Find sale</button>
      </div>
    </form>
  </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if ($source): ?>
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
      <div>
        <div class="fw-bold fs-5"><?php echo htmlspecialchars($source['customer_name'] ?: 'Walk-in Customer'); ?></div>
        <div class="text-muted small">
          Receipt <?php echo htmlspecialchars($source['receipt_number']); ?> · <?php echo htmlspecialchars(ucfirst($source['source_type'])); ?>
          · served by <?php echo htmlspecialchars($source['staff_name'] ?? '—'); ?>
          · <?php echo date('j M Y, g:i a', strtotime($source['created_at'])); ?>
        </div>
      </div>
      <?php $receiptUrl = $source['source_type'] === 'order' ? ($receiptBase . '?id=' . (int) $source['id']) : ($saleReceiptBase . '?id=' . (int) $source['id']); ?>
      <a class="btn btn-sm btn-outline-secondary" href="<?php echo $receiptUrl; ?>"><i class="fas fa-receipt me-1"></i>Receipt</a>
    </div>

    <?php if (!$items): ?>
      <div class="text-muted small">No products found on this sale.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr class="text-muted small text-uppercase">
              <th>Product</th><th class="text-end">Sold</th><th class="text-end">Returned</th><th class="text-end">Available</th><th style="min-width:280px;">Record return</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $it):
                $sold = (float) $it['quantity'];
                $returned = (float) $it['returned_quantity'];
                $used = (float) $it['used_quantity'];
                $available = max(0, $sold - $returned);
                $fmt = fn($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
            ?>
            <tr>
              <td>
                <div class="fw-semibold small"><?php echo htmlspecialchars($it['product_name']); ?></div>
                <div class="text-muted" style="font-size:.75rem;">KES <?php echo number_format((float) $it['unit_price'], 0); ?> each</div>
              </td>
              <td class="text-end small"><?php echo $fmt($sold); ?></td>
              <td class="text-end small">
                <?php echo $fmt($returned); ?>
                <?php if ($used > 0): ?><div class="text-muted" style="font-size:.75rem;">used <?php echo $fmt($used); ?></div><?php endif; ?>
              </td>
              <td class="text-end small fw-semibold"><?php echo $fmt($available); ?></td>
              <td>
                <?php if ($available <= 0): ?>
                  <span class="badge bg-secondary">Fully returned</span>
                <?php else: ?>
                <form method="post" class="return-form">
                  <input type="hidden" name="action" value="return">
                  <input type="hidden" name="receipt_number" value="<?php echo htmlspecialchars($source['receipt_number']); ?>">
                  <input type="hidden" name="source_type" value="<?php echo htmlspecialchars($source['source_type']); ?>">
                  <input type="hidden" name="source_id" value="<?php echo (int) $source['id']; ?>">
                  <input type="hidden" name="source_item_id" value="<?php echo (int) $it['id']; ?>">
                  <div class="row g-2">
                    <div class="col-6">
                      <label class="form-label small mb-1">Returned</label>
                      <input type="number" step="0.01" min="0.01" max="<?php echo htmlspecialchars((string) $available); ?>" name="returned_quantity" class="form-control form-control-sm returned-input" required>
                    </div>
                    <div class="col-6">
                      <label class="form-label small mb-1">Used</label>
                      <input type="number" step="0.01" min="0" name="used_quantity" class="form-control form-control-sm used-input" value="0">
                    </div>
                    <div class="col-12">
                      <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason, e.g. wrong item, damaged, expired">
                    </div>
                    <div class="col-12">
                      <textarea name="note" class="form-control form-control-sm" rows="1" placeholder="Note (optional)"></textarea>
                    </div>
                    <div class="col-12">
                      <button class="btn btn-sm btn-outline-primary w-100"><i class="fas fa-check me-1"></i>Record return</button>
                    </div>
                  </div>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm" style="border-radius:14px;">
  <div class="card-body p-4">
    <h2 class="h6 fw-bold mb-3"><i class="fas fa-clock-rotate-left me-2 text-primary"></i>Recent returns</h2>
    <?php if (!$recent): ?>
      <div class="text-muted small">No returns recorded yet.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr class="text-muted small text-uppercase"><th>Receipt</th><th>Product</th><th>Returned</th><th>Used</th><th>Restocked</th><th>By</th><th>When</th></tr></thead>
          <tbody>
            <?php foreach ($recent as $r): ?>
            <tr>
              <td class="fw-semibold small"><?php echo htmlspecialchars($r['receipt_number']); ?></td>
              <td class="small"><?php echo htmlspecialchars($r['product_name']); ?></td>
              <td class="small"><?php echo rtrim(rtrim(number_format((float) $r['returned_quantity'], 2), '0'), '.'); ?></td>
              <td class="small"><?php echo rtrim(rtrim(number_format((float) $r['used_quantity'], 2), '0'), '.'); ?></td>
              <td class="small"><?php echo rtrim(rtrim(number_format((float) $r['restocked_quantity'], 2), '0'), '.'); ?></td>
              <td class="small"><?php echo htmlspecialchars($r['processed_by_name'] ?? '—'); ?></td>
              <td class="small text-nowrap"><?php echo date('j M, g:i a', strtotime($r['created_at'])); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
document.querySelectorAll('.return-form').forEach(function (form) {
  form.addEventListener('submit', function (e) {
    var returned = parseFloat(form.querySelector('.returned-input').value) || 0;
    var used = parseFloat(form.querySelector('.used-input').value) || 0;
    if (used > returned) {
      e.preventDefault();
      alert('Used quantity cannot be more than the returned quantity.');
    }
  });
});

(function() {
  var input = document.getElementById('receiptSearchInput');
  var menu = document.getElementById('receiptSuggestMenu');
  var form = document.getElementById('receiptSearchForm');
  if (!input || !menu || !form) return;

  var apiUrl = <?php echo json_encode(public_url('api/returns/search_receipts.php')); ?>;
  var timer = null;
  var activeIndex = -1;
  var currentItems = [];

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
  }

  function renderMenu(items) {
    currentItems = items || [];
    activeIndex = -1;
    menu.innerHTML = '';
    if (!currentItems.length) {
      menu.innerHTML = '<div class="px-3 py-3 text-muted small text-center"><i class="fas fa-circle-exclamation me-1"></i>No matching sales or orders found</div>';
      menu.style.display = 'block';
      return;
    }

    var header = document.createElement('div');
    header.className = 'px-3 py-1 bg-light border-bottom text-muted small fw-semibold text-uppercase';
    header.style.fontSize = '0.68rem';
    header.textContent = 'Matching sales & credit tabs (' + currentItems.length + ')';
    menu.appendChild(header);

    currentItems.forEach(function(item, idx) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'dropdown-item px-3 py-2 border-bottom d-flex justify-content-between align-items-start gap-2 text-wrap';
      btn.style.cursor = 'pointer';
      btn.dataset.index = idx;

      var isOrder = item.source_type === 'order';
      var badgeHtml = isOrder 
        ? '<span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size:0.68rem;">Credit Tab</span>'
        : '<span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:0.68rem;">Sale</span>';

      btn.innerHTML = 
        '<div class="flex-grow-1 text-start">' +
          '<div class="d-flex align-items-center gap-2 flex-wrap mb-1">' +
            '<span class="fw-bold text-primary font-monospace">' + escapeHtml(item.receipt_number) + '</span>' +
            badgeHtml +
            '<span class="fw-semibold text-dark">' + escapeHtml(item.customer_name) + '</span>' +
          '</div>' +
          '<div class="text-muted small" style="font-size:0.75rem;">' +
            '<i class="fas fa-boxes-stacked me-1 text-secondary"></i>' + escapeHtml(item.items_summary) +
          '</div>' +
          '<div class="text-muted" style="font-size:0.7rem;margin-top:2px;">' +
            escapeHtml(item.date_formatted) + (item.staff_name && item.staff_name !== '—' ? ' · by ' + escapeHtml(item.staff_name) : '') +
          '</div>' +
        '</div>' +
        '<div class="text-end text-nowrap ms-2">' +
          '<div class="fw-bold text-dark" style="font-size:0.85rem;">KES ' + (item.total || 0).toLocaleString() + '</div>' +
          '<span class="badge bg-primary-subtle text-primary border border-primary-subtle mt-1" style="font-size:0.68rem;"><i class="fas fa-check me-1"></i>Autofill</span>' +
        '</div>';

      btn.addEventListener('mousedown', function(e) {
        e.preventDefault();
        chooseItem(item);
      });
      menu.appendChild(btn);
    });
    menu.style.display = 'block';
  }

  function chooseItem(item) {
    if (!item || !item.receipt_number) return;
    input.value = item.receipt_number;
    menu.style.display = 'none';
    form.submit();
  }

  function updateActive() {
    var buttons = menu.querySelectorAll('.dropdown-item');
    buttons.forEach(function(b, idx) {
      if (idx === activeIndex) {
        b.classList.add('active');
        b.scrollIntoView({ block: 'nearest' });
      } else {
        b.classList.remove('active');
      }
    });
  }

  function queryApi(val) {
    clearTimeout(timer);
    timer = setTimeout(function() {
      fetch(apiUrl + '?q=' + encodeURIComponent(val))
        .then(function(r) { return r.json(); })
        .then(function(data) {
          renderMenu(data.items || []);
        })
        .catch(function() {});
    }, 160);
  }

  input.addEventListener('input', function() {
    var q = input.value.trim();
    if (!q) {
      menu.style.display = 'none';
      menu.innerHTML = '';
      return;
    }
    queryApi(q);
  });

  input.addEventListener('focus', function() {
    var q = input.value.trim();
    if (q) {
      queryApi(q);
    }
  });

  input.addEventListener('keydown', function(e) {
    var buttons = menu.querySelectorAll('.dropdown-item');
    if (menu.style.display !== 'block' || !buttons.length) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      activeIndex = (activeIndex + 1) % buttons.length;
      updateActive();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      activeIndex = (activeIndex - 1 + buttons.length) % buttons.length;
      updateActive();
    } else if (e.key === 'Enter') {
      if (activeIndex >= 0 && currentItems[activeIndex]) {
        e.preventDefault();
        chooseItem(currentItems[activeIndex]);
      }
    } else if (e.key === 'Escape') {
      menu.style.display = 'none';
    }
  });

  input.addEventListener('blur', function() {
    setTimeout(function() {
      menu.style.display = 'none';
    }, 200);
  });
})();
</script>
<?php
$content = ob_get_clean();
$__layout = $isStaffViewer ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
