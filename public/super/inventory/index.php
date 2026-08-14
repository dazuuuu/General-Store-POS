<?php
// public/super/inventory/index.php — general shop stock overview
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::INVENTORY_VIEW);

$pdo = Database::pdo();
$P = new Models\ProductModel($pdo);
$R = new Models\ReturnModel($pdo);
$tenantModel = new Models\TenantModel($pdo);
$tenantModel->ensureShopSchema();
$tenant = $tenantModel->find(TenantContext::tenantId()) ?: [];
$canEdit = TenantContext::can(Capabilities::INVENTORY_EDIT);

if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'toggle') {
        $row = $P->find($id);
        if ($row) { $P->setStatus($id, $row['status'] === 'active' ? 'draft' : 'active'); }
        $_SESSION['flash']['success'] = 'Product status updated.';
    } elseif ($action === 'archive') {
        $P->setStatus($id, 'archived');
        $_SESSION['flash']['success'] = 'Product archived — still sellable from the Archive tab at the till.';
    } elseif ($action === 'unarchive') {
        $P->setStatus($id, 'active');
        $_SESSION['flash']['success'] = 'Product unarchived.';
    } elseif ($action === 'delete') {
        $P->deleteSafe($id);
        $_SESSION['flash']['success'] = 'Product deleted.';
    } elseif ($action === 'migrate_return') {
        $res = $R->migrateToInventory((int) ($_POST['return_id'] ?? 0), TenantContext::userId());
        $_SESSION['flash'][$res['ok'] ? 'success' : 'error'] = $res['ok'] ? 'Returned product migrated to inventory.' : ($res['error'] ?? 'Could not migrate return.');
    }
    header('Location: ' . public_url('super/inventory/') . '?group=' . urlencode($_GET['group'] ?? 'category'));
    exit;
}

$groupBy = in_array($_GET['group'] ?? '', ['category', 'brand', 'supplier', 'unit', 'archived'], true) ? $_GET['group'] : 'category';
$groupLabels = [
    'category' => 'category',
    'brand' => 'brand',
    'supplier' => 'supplier',
    'unit' => 'unit',
    'archived' => 'archived items',
];
$grouped = match ($groupBy) {
    'brand'     => $P->listGroupedByBrand(),
    'supplier'  => $P->listGroupedBySupplier(),
    'unit'      => $P->listGroupedByUnit(),
    'archived'  => ($archived = $P->listArchived()) ? ['Archived' => $archived] : [],
    default     => $P->listGroupedByCategory(),
};
$editBase = public_url('super/products/');
$stockUrl = public_url('super/stock/new.php');
$productUrl = public_url('super/stationery/new.php');
$storeUrl = public_url('super/store/');
$groupUrl = fn(string $g) => public_url('super/inventory/') . '?group=' . $g;
$pendingReturns = $R->pendingForInventory();

$totals = ['products' => 0, 'stock_value' => 0.0, 'retail_value' => 0.0, 'faulty' => 0.0];
$vatRate = (float) ($tenant['vat_rate'] ?? 0);
$vatMode = !empty($tenant['vat_inclusive']) ? 'inclusive' : 'exclusive';
foreach ($grouped as $items) {
    foreach ($items as $p) {
        $totals['products']++;
        $qty = (float)$p['quantity'];
        $totals['stock_value'] += Models\ProductModel::stockValue((float)$p['buying_price'], $qty);
        $totals['retail_value'] += $qty * (float)($p['retail_price'] ?? $p['selling_price']);
        $totals['faulty'] += (float)($p['faulty_quantity'] ?? 0);
    }
}

$page_title = 'Inventory';
ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h1 class="h5 fw-bold mb-1">Shop Inventory by <?php echo $groupLabels[$groupBy]; ?></h1>
    <p class="text-muted small mb-0">Sellable stock for the till. New deliveries go to <a href="<?php echo $storeUrl; ?>">Store warehouse</a> first — transfer here with an internal invoice so you never record twice.</p>
  </div>
  <?php if ($canEdit): ?>
    <a href="<?php echo $storeUrl; ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-box-archive me-1"></i>Store warehouse</a>
    <a href="<?php echo $productUrl; ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-box-open me-1"></i>Record to Store</a>
    <a href="<?php echo $stockUrl; ?>" class="btn btn-primary btn-sm"><i class="fas fa-boxes-stacked me-1"></i>Bulk to Store</a>
  <?php endif; ?>
</div>

<div class="btn-group btn-group-sm mb-4" role="group">
  <?php foreach (['category' => 'By category', 'brand' => 'By brand', 'supplier' => 'By supplier', 'unit' => 'By unit', 'archived' => 'Archived'] as $g => $lbl): ?>
    <a href="<?php echo $groupUrl($g); ?>" class="btn btn-outline-secondary <?php echo $groupBy === $g ? 'active' : ''; ?>"><?php echo $lbl; ?></a>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-3">
        <div class="text-muted small text-uppercase fw-semibold">Products</div>
        <div class="h4 mb-0 fw-bold"><?php echo $totals['products']; ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-3">
        <div class="text-muted small text-uppercase fw-semibold">Stock value (cost)</div>
        <div class="h5 mb-0 fw-bold">KES <?php echo number_format($totals['stock_value'], 0); ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-3">
        <div class="text-muted small text-uppercase fw-semibold">Selling value</div>
        <div class="h5 mb-0 fw-bold text-primary">KES <?php echo number_format($totals['retail_value'], 0); ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-3">
        <div class="text-muted small text-uppercase fw-semibold">Faulty / broken</div>
        <div class="h5 mb-0 fw-bold text-danger"><?php echo rtrim(rtrim(number_format($totals['faulty'], 2), '0'), '.'); ?></div>
      </div>
    </div>
  </div>
</div>

<div class="alert alert-light border small py-2 mb-4">
  VAT ratio: <strong><?php echo number_format($vatRate, 2); ?>%</strong>
  <span class="text-muted">(<?php echo htmlspecialchars($vatMode); ?> by default on sales; staff can turn it off at checkout)</span>
</div>

<?php if ($pendingReturns): ?>
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;overflow:hidden;">
  <div class="px-4 py-3 d-flex align-items-center justify-content-between" style="background:#fff7ed;border-bottom:1px solid #fed7aa;">
    <div>
      <h2 class="h6 fw-bold mb-0"><i class="fas fa-rotate-left me-2 text-warning"></i>Returned products waiting for inventory</h2>
      <span class="text-muted small"><?php echo count($pendingReturns); ?> return<?php echo count($pendingReturns) !== 1 ? 's' : ''; ?> pending migration</span>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr class="text-muted small text-uppercase">
          <th>Receipt</th>
          <th>Product</th>
          <th class="text-end">Returned</th>
          <th class="text-end">Used</th>
          <th class="text-end">Sellable</th>
          <th>Reason</th>
          <th>By</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pendingReturns as $ret):
            $fmt = fn($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
            $canMigrate = !empty($ret['product_id']);
        ?>
        <tr>
          <td class="fw-semibold small"><?php echo htmlspecialchars($ret['receipt_number']); ?></td>
          <td>
            <div class="fw-semibold small"><?php echo htmlspecialchars($ret['product_name']); ?></div>
            <div class="text-muted" style="font-size:.75rem;">Current stock: <?php echo $ret['current_quantity'] !== null ? $fmt($ret['current_quantity']) : 'product missing'; ?></div>
          </td>
          <td class="text-end small"><?php echo $fmt($ret['returned_quantity']); ?></td>
          <td class="text-end small"><?php echo $fmt($ret['used_quantity']); ?></td>
          <td class="text-end fw-semibold small text-success"><?php echo $fmt($ret['restocked_quantity']); ?></td>
          <td class="small"><?php echo htmlspecialchars($ret['reason'] ?: '—'); ?></td>
          <td class="small"><?php echo htmlspecialchars($ret['processed_by_name'] ?? '—'); ?></td>
          <td class="text-end">
            <?php if ($canEdit && $canMigrate): ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="action" value="migrate_return">
                <input type="hidden" name="return_id" value="<?php echo (int) $ret['id']; ?>">
                <button class="btn btn-sm btn-outline-success"><i class="fas fa-arrow-up-from-bracket me-1"></i>Migrate to stock</button>
              </form>
            <?php elseif (!$canMigrate): ?>
              <span class="badge bg-secondary">No product link</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if (!$grouped): ?>
  <div class="card border-0 shadow-sm" style="border-radius:14px;">
    <div class="card-body p-5 text-center text-muted">
      <i class="fas fa-box fa-2x mb-3 d-block" style="opacity:.25;"></i>
      <?php if ($groupBy === 'archived'): ?>
        No archived products.
      <?php else: ?>
        No products yet. <?php echo $canEdit ? '<a href="' . $stockUrl . '">Record your first delivery</a>.' : 'Ask the owner to record stock.'; ?>
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <?php foreach ($grouped as $groupName => $items): ?>
  <div class="card border-0 shadow-sm mb-4" style="border-radius:14px;overflow:hidden;">
    <div class="px-4 py-3 d-flex align-items-center justify-content-between" style="background:linear-gradient(135deg,#f8fafc,#f1f5f9);border-bottom:1px solid #e2e8f0;">
      <div>
        <h2 class="h6 fw-bold mb-0"><i class="fas fa-box me-2 text-primary"></i><?php echo htmlspecialchars($groupName); ?></h2>
        <span class="text-muted small"><?php echo count($items); ?> product<?php echo count($items) !== 1 ? 's' : ''; ?></span>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr class="text-muted small text-uppercase">
            <th style="width:48px;"></th>
            <th>Product</th>
            <th>Category</th>
            <th>Brand</th>
            <th>Unit</th>
            <th class="text-end">Good qty</th>
            <th class="text-end">Faulty</th>
            <th class="text-end">Buying Prices</th>
            <th class="text-end">Selling Prices</th>
            <th class="text-end">Profit Totals</th>
            <th class="text-end">VAT</th>
            <th class="text-end">Credit limit</th>
            <th>Status</th>
            <?php if ($canEdit): ?><th></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $p):
              $buy = (float)$p['buying_price'];
              $qty = (float)$p['quantity'];
              $faulty = (float)($p['faulty_quantity'] ?? 0);
              $retail = (float)($p['retail_price'] ?? $p['selling_price']);
              $unitsPerPack = max(1, (float)($p['units_per_pack'] ?? 1));
              $packUnit = trim((string)($p['pack_unit'] ?? ''));
              $packBuy = ($p['package_buying_price'] ?? '') !== '' && $p['package_buying_price'] !== null ? (float)$p['package_buying_price'] : 0.0;
              $packSell = ($p['pack_price'] ?? '') !== '' && $p['pack_price'] !== null ? (float)$p['pack_price'] : 0.0;
              $hasPack = $packUnit !== '' && $unitsPerPack > 1;
              $packageCount = $hasPack ? floor(($qty / $unitsPerPack) * 100) / 100 : $qty;
              $retailProfitTotal = ($retail - $buy) * $qty;
              $wholesaleProfitTotal = ($hasPack && $packBuy > 0 && $packSell > 0)
                  ? (($packSell - $packBuy) * $packageCount)
                  : (((float)($p['wholesale_price'] ?? 0) - $buy) * $qty);
              $low = $qty <= (int)$p['low_stock_threshold'];
              $colors = $p['colors'] ? (is_array($p['colors']) ? $p['colors'] : (json_decode($p['colors'], true) ?: [])) : [];
              if (is_string($p['colors'] ?? null) && !$colors && $p['colors'] !== '') {
                  $colors = array_filter(array_map('trim', explode(',', $p['colors'])));
              }
              $eff = Models\ProductModel::effectivePrice($p);
          ?>
          <tr>
            <td>
              <?php if (!empty($p['image_path'])): ?>
                <img src="<?php echo htmlspecialchars($p['image_path']); ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;">
              <?php else: ?>
                <span class="d-inline-flex align-items-center justify-content-center text-muted" style="width:40px;height:40px;border-radius:8px;background:#f1f5f9;"><i class="fas fa-box"></i></span>
              <?php endif; ?>
            </td>
            <td>
              <div class="fw-semibold"><?php echo htmlspecialchars($p['name']); ?></div>
              <?php if ($colors): ?><div class="text-muted small"><?php echo htmlspecialchars(implode(' · ', $colors)); ?></div><?php endif; ?>
            </td>
            <td class="text-muted small"><?php echo htmlspecialchars($p['category_name'] ?: '—'); ?></td>
            <td class="text-muted small"><?php echo htmlspecialchars(($p['brand_name'] ?? null) ?: ($p['publisher_name'] ?? '—')); ?></td>
            <td class="small"><?php echo htmlspecialchars($p['unit'] ?? 'piece'); ?></td>
            <td class="text-end <?php echo $low ? 'text-danger fw-semibold' : ''; ?>">
              <?php echo rtrim(rtrim(number_format($qty, 2), '0'), '.'); ?>
            </td>
            <td class="text-end <?php echo $faulty > 0 ? 'text-warning fw-semibold' : 'text-muted'; ?>">
              <?php echo rtrim(rtrim(number_format($faulty, 2), '0'), '.'); ?>
            </td>
            <td class="text-end">
              <div class="text-muted small">Content: KES <?php echo number_format($buy, 0); ?></div>
              <?php if ($hasPack): ?>
                <div class="small">Package: <?php echo $packBuy > 0 ? 'KES ' . number_format($packBuy, 0) : '<span class="text-danger">missing</span>'; ?></div>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <?php if ($eff['on_offer']): ?>
                <div><span class="text-decoration-line-through text-muted small">KES <?php echo number_format($retail, 0); ?></span>
                <span class="fw-semibold" style="color:#b45309;">KES <?php echo number_format($eff['price'], 0); ?></span></div>
              <?php else: ?>
                <div>Retail: KES <?php echo number_format($retail, 0); ?></div>
              <?php endif; ?>
              <?php if ($hasPack): ?>
                <div class="text-muted small">Wholesale/<?php echo htmlspecialchars($packUnit); ?>: <?php echo $packSell > 0 ? 'KES ' . number_format($packSell, 0) : '<span class="text-danger">missing</span>'; ?></div>
              <?php endif; ?>
            </td>
            <td class="text-end small">
              <div>Retail: <span class="<?php echo $retailProfitTotal < 0 ? 'text-danger' : 'text-success'; ?>">KES <?php echo number_format($retailProfitTotal, 0); ?></span></div>
              <div>Wholesale: <span class="<?php echo $wholesaleProfitTotal < 0 ? 'text-danger' : 'text-success'; ?>">KES <?php echo number_format($wholesaleProfitTotal, 0); ?></span></div>
            </td>
            <td class="text-end text-muted"><?php echo number_format($vatRate, 2); ?>%</td>
            <td class="text-end text-muted">
              <?php echo ($p['credit_limit'] ?? null) !== null && $p['credit_limit'] !== '' ? 'KES ' . number_format((float) $p['credit_limit'], 0) : '—'; ?>
            </td>
            <td>
              <?php if ($p['status'] === 'active'): ?><span class="badge bg-success">Active</span>
              <?php elseif ($p['status'] === 'archived'): ?><span class="badge bg-secondary">Archived</span>
              <?php else: ?><span class="badge bg-light text-dark">Draft</span><?php endif; ?>
            </td>
            <?php if ($canEdit): ?>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-primary" href="<?php echo $editBase; ?>?edit=<?php echo (int)$p['id']; ?>">Edit</a>
              <?php if ($p['status'] === 'archived'): ?>
                <form method="post" class="d-inline">
                  <input type="hidden" name="action" value="unarchive"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                  <button class="btn btn-sm btn-outline-success">Unarchive</button>
                </form>
              <?php else: ?>
                <form method="post" class="d-inline">
                  <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                  <button class="btn btn-sm btn-outline-secondary"><?php echo $p['status'] === 'active' ? 'Draft' : 'Activate'; ?></button>
                </form>
                <form method="post" class="d-inline" onsubmit="return confirm('Archive this product? Staff can still sell it from the Archive tab.');">
                  <input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                  <button class="btn btn-sm btn-outline-dark">Archive</button>
                </form>
              <?php endif; ?>
              <form method="post" class="d-inline" onsubmit="return confirm('Delete this product?');">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                <button class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
$__layout = TenantContext::role() === 'staff' ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
