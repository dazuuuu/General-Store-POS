<?php
// public/super/purchases/view.php — single purchase detail
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::STOCK_ENTER);

$pdo = Database::pdo();
$PUR = new Models\PurchaseModel($pdo);
$id = (int) ($_GET['id'] ?? 0);
$purchase = $id > 0 ? $PUR->findWithMeta($id) : null;
if (!$purchase) {
    $_SESSION['flash']['error'] = 'Purchase not found.';
    header('Location: ' . public_url('super/purchases/'));
    exit;
}
$items = $PUR->itemsFor($id);
$base = public_url('super/purchases/');
$pending = array_filter($items, fn($i) => ($i['status'] ?? '') === 'pending');
$date = $purchase['purchase_date'] ?: date('Y-m-d', strtotime($purchase['created_at']));
$shop = $purchase['shop_name'] ?: ($purchase['supplier_name'] ?: '—');

$page_title = 'Purchase #' . $id;
ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h1 class="h5 fw-bold mb-1">Purchase #<?php echo (int) $id; ?></h1>
    <p class="text-muted small mb-0"><?php echo htmlspecialchars(date('j M Y', strtotime($date))); ?> · <?php echo htmlspecialchars($shop); ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $base; ?>">All purchases</a>
    <?php if ($pending): ?>
      <a class="btn btn-sm btn-primary" href="<?php echo $base; ?>transfer.php?purchase_id=<?php echo (int) $id; ?>"><i class="fas fa-right-left me-1"></i>Transfer pending</a>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-8">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <div class="row g-3 small">
          <div class="col-sm-6"><div class="text-muted">Supplier / shop</div><div class="fw-semibold"><?php echo htmlspecialchars($shop); ?></div></div>
          <div class="col-sm-6"><div class="text-muted">Receipt number</div><div class="fw-semibold"><?php echo htmlspecialchars($purchase['receipt_number'] ?: '—'); ?></div></div>
          <div class="col-sm-6"><div class="text-muted">Status</div><div><span class="badge bg-<?php echo $purchase['status'] === 'transferred' ? 'success' : ($purchase['status'] === 'partial' ? 'warning' : 'secondary'); ?>"><?php echo htmlspecialchars(ucfirst($purchase['status'])); ?></span></div></div>
          <div class="col-sm-6"><div class="text-muted">Recorded by</div><div class="fw-semibold"><?php echo htmlspecialchars($purchase['staff_name'] ?? '—'); ?></div></div>
          <?php if (!empty($purchase['notes'])): ?>
            <div class="col-12"><div class="text-muted">Notes</div><div><?php echo htmlspecialchars($purchase['notes']); ?></div></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
      <div class="card-body p-4">
        <div class="text-muted small text-uppercase fw-semibold mb-2">Receipt</div>
        <?php if (!empty($purchase['receipt_image_path'])): ?>
          <a href="<?php echo htmlspecialchars($purchase['receipt_image_path']); ?>" target="_blank">
            <img src="<?php echo htmlspecialchars($purchase['receipt_image_path']); ?>" alt="Receipt" style="width:100%;max-height:180px;object-fit:contain;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;">
          </a>
        <?php else: ?>
          <div class="text-muted small">No receipt photo uploaded.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
  <div class="px-4 py-3" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
    <h2 class="h6 fw-bold mb-0">Items</h2>
  </div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr class="text-muted small text-uppercase">
          <th>Product</th>
          <th>Package</th>
          <th class="text-end">Qty</th>
          <th class="text-end">Buying</th>
          <th class="text-end">Sell prices</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it):
            $pkgUnit = $it['package_unit'] ?: '';
            $inside = (float) ($it['units_per_package'] ?? 1);
            $pkgQty = (float) ($it['package_quantity'] ?? 0);
            $pkgBuy = (float) ($it['package_buying_price'] ?? 0);
            $unitBuy = (float) ($it['buying_price'] ?? 0);
        ?>
        <tr>
          <td>
            <div class="fw-semibold small"><?php echo htmlspecialchars($it['name'] ?: '—'); ?></div>
            <?php if (!empty($it['variant_label'])): ?><div class="text-muted" style="font-size:.75rem;"><?php echo htmlspecialchars($it['variant_label']); ?></div><?php endif; ?>
            <div class="text-muted" style="font-size:.75rem;">
              <?php echo htmlspecialchars($it['category_name'] ?: 'No category'); ?>
              <?php if (!empty($it['brand_name'])): ?> · <?php echo htmlspecialchars($it['brand_name']); ?><?php endif; ?>
            </div>
          </td>
          <td class="small">
            <?php if ($pkgUnit): ?>
              <?php echo htmlspecialchars(rtrim(rtrim(number_format($pkgQty, 2), '0'), '.') . ' ' . $pkgUnit); ?>
              × <?php echo htmlspecialchars(rtrim(rtrim(number_format($inside, 2), '0'), '.')); ?> <?php echo htmlspecialchars($it['unit'] ?: 'piece'); ?>
            <?php else: ?>
              <?php echo htmlspecialchars($it['unit'] ?: 'piece'); ?>
            <?php endif; ?>
          </td>
          <td class="text-end small"><?php echo rtrim(rtrim(number_format((float) $it['quantity'], 2), '0'), '.'); ?></td>
          <td class="text-end small">
            <?php if ($pkgBuy > 0): ?><div>Pkg: KES <?php echo number_format($pkgBuy, 0); ?></div><?php endif; ?>
            <div class="text-muted">Unit: KES <?php echo number_format($unitBuy, 2); ?></div>
          </td>
          <td class="text-end small">
            <?php if ((float) ($it['package_price'] ?? 0) > 0): ?><div>WS pkg: KES <?php echo number_format((float) $it['package_price'], 0); ?></div><?php endif; ?>
            <?php if ((float) ($it['retail_pack_price'] ?? 0) > 0): ?><div>RT pkg: KES <?php echo number_format((float) $it['retail_pack_price'], 0); ?></div><?php endif; ?>
            <?php if ((float) ($it['retail_price'] ?? 0) > 0): ?><div>Retail: KES <?php echo number_format((float) $it['retail_price'], 0); ?></div><?php endif; ?>
            <?php if ((float) ($it['wholesale_price'] ?? 0) > 0): ?><div>WS item: KES <?php echo number_format((float) $it['wholesale_price'], 0); ?></div><?php endif; ?>
            <?php if (!(float) ($it['package_price'] ?? 0) && !(float) ($it['retail_pack_price'] ?? 0) && !(float) ($it['retail_price'] ?? 0) && !(float) ($it['wholesale_price'] ?? 0)): ?>
              <span class="text-muted">Set on transfer</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge bg-<?php echo $it['status'] === 'transferred' ? 'success' : 'warning'; ?>">
              <?php echo htmlspecialchars(ucfirst($it['status'])); ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$content = ob_get_clean();
$__layout = TenantContext::role() === 'staff' ? 'staff' : 'tenants';
include __DIR__ . '/../../templates/' . $__layout . '/layout.php';
