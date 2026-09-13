<?php
// public/super/purchases/track.php — advanced search / track purchases
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::STOCK_ENTER);

$pdo = Database::pdo();
$PUR = new Models\PurchaseModel($pdo);
$SUP = new Models\SupplierModel($pdo);

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'supplier_id' => (int) ($_GET['supplier_id'] ?? 0),
    'shop_name' => trim((string) ($_GET['shop_name'] ?? '')),
    'receipt_number' => trim((string) ($_GET['receipt_number'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
];
$rows = $PUR->listWithMeta($filters, 200);
$suppliers = $SUP->all([], 'name ASC');
$base = public_url('super/purchases/');
$hasFilters = $filters['q'] !== '' || $filters['status'] !== '' || $filters['supplier_id'] > 0
    || $filters['shop_name'] !== '' || $filters['receipt_number'] !== ''
    || $filters['date_from'] !== '' || $filters['date_to'] !== '';

$page_title = 'Track purchases';
ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h1 class="h5 fw-bold mb-1">Track purchases</h1>
    <p class="text-muted small mb-0">Advanced search across supplier, receipt, product name, dates and transfer status.</p>
  </div>
  <a class="btn btn-sm btn-outline-secondary" href="<?php echo $base; ?>">All purchases</a>
</div>

<form method="get" class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
  <div class="card-body p-3 p-md-4">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-lg-4">
        <label class="form-label small mb-1">Search everything</label>
        <div class="input-group">
          <span class="input-group-text bg-white"><i class="fas fa-magnifying-glass text-muted"></i></span>
          <input type="search" name="q" class="form-control" placeholder="Product, supplier, shop, receipt, notes…" value="<?php echo htmlspecialchars($filters['q']); ?>">
        </div>
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select">
          <option value="">Any</option>
          <?php foreach (['recorded' => 'Recorded', 'partial' => 'Partial', 'transferred' => 'Transferred'] as $k => $lbl): ?>
            <option value="<?php echo $k; ?>" <?php echo $filters['status'] === $k ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <label class="form-label small mb-1">Supplier</label>
        <select name="supplier_id" class="form-select">
          <option value="">Any</option>
          <?php foreach ($suppliers as $s): ?>
            <option value="<?php echo (int) $s['id']; ?>" <?php echo $filters['supplier_id'] === (int) $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <label class="form-label small mb-1">From</label>
        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <label class="form-label small mb-1">To</label>
        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
      </div>
      <div class="col-md-4 col-lg-3">
        <label class="form-label small mb-1">Shop name contains</label>
        <input type="text" name="shop_name" class="form-control" value="<?php echo htmlspecialchars($filters['shop_name']); ?>" placeholder="optional">
      </div>
      <div class="col-md-4 col-lg-3">
        <label class="form-label small mb-1">Receipt number</label>
        <input type="text" name="receipt_number" class="form-control" value="<?php echo htmlspecialchars($filters['receipt_number']); ?>" placeholder="optional">
      </div>
      <div class="col-md-4 col-lg-3 d-flex gap-2">
        <button class="btn btn-primary flex-grow-1"><i class="fas fa-filter me-1"></i>Search</button>
        <?php if ($hasFilters): ?>
          <a class="btn btn-outline-secondary" href="<?php echo $base; ?>track.php">Clear</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</form>

<div class="d-flex justify-content-between align-items-center mb-2">
  <div class="text-muted small"><?php echo count($rows); ?> purchase<?php echo count($rows) === 1 ? '' : 's'; ?> found</div>
</div>

<?php if (!$rows): ?>
  <div class="card border-0 shadow-sm" style="border-radius:14px;">
    <div class="card-body p-5 text-center text-muted">No purchases match these filters.</div>
  </div>
<?php else: ?>
  <div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr class="text-muted small text-uppercase">
            <th>Date</th>
            <th>Products</th>
            <th>Supplier / shop</th>
            <th>Receipt</th>
            <th class="text-end">Items</th>
            <th class="text-end">Pending</th>
            <th class="text-end">Cost</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
              $date = $r['purchase_date'] ?: date('Y-m-d', strtotime($r['created_at']));
              $shop = $r['shop_name'] ?: ($r['supplier_name'] ?: '—');
              $names = trim((string) ($r['product_names'] ?? ''));
          ?>
          <tr>
            <td class="small"><?php echo htmlspecialchars(date('j M Y', strtotime($date))); ?></td>
            <td class="fw-semibold small"><?php echo $names !== '' ? htmlspecialchars($names) : '<span class="text-muted">—</span>'; ?></td>
            <td class="fw-semibold small"><?php echo htmlspecialchars($shop); ?></td>
            <td class="small">
              <?php echo htmlspecialchars($r['receipt_number'] ?: '—'); ?>
              <?php if (!empty($r['receipt_image_path'])): ?>
                <a class="ms-1" href="<?php echo htmlspecialchars($r['receipt_image_path']); ?>" target="_blank"><i class="fas fa-image"></i></a>
              <?php endif; ?>
            </td>
            <td class="text-end small"><?php echo (int) $r['item_count']; ?></td>
            <td class="text-end small <?php echo (int) $r['pending_count'] > 0 ? 'text-warning fw-semibold' : 'text-muted'; ?>"><?php echo (int) $r['pending_count']; ?></td>
            <td class="text-end small">KES <?php echo number_format((float) $r['cost_total'], 0); ?></td>
            <td><span class="badge bg-<?php echo $r['status'] === 'transferred' ? 'success' : ($r['status'] === 'partial' ? 'warning' : 'secondary'); ?>"><?php echo htmlspecialchars(ucfirst($r['status'])); ?></span></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" href="<?php echo $base; ?>view.php?id=<?php echo (int) $r['id']; ?>">Open</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
$__layout = TenantContext::role() === 'staff' ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
