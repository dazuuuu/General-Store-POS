<?php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::REPORTS_VIEW);

$page_title = 'Data export';
ob_start();
?>
<div class="row g-4">
  <div class="col-12 col-xl-8">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-1">Excel exports</h2>
        <p class="text-muted small mb-4">Export products, sales, and product-level profit margins for Excel.</p>
        <form method="get" action="<?php echo public_url('super/data/export.php'); ?>" class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Export file</label>
            <select name="type" class="form-select">
              <option value="all">All data workbook</option>
              <option value="products">Products only</option>
              <option value="sales">Sales only</option>
              <option value="profit">Profit margins by product</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Period</label>
            <select name="period" class="form-select">
              <option value="all">All time</option>
              <option value="today">Today</option>
              <option value="week">Last 7 days</option>
              <option value="month">Last 30 days</option>
            </select>
          </div>
          <div class="col-12">
            <button class="btn btn-primary"><i class="fas fa-file-excel me-1"></i>Download Excel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-12 col-xl-4">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h3 class="h6 fw-bold mb-3">Quick exports</h3>
        <div class="d-grid gap-2">
          <a class="btn btn-outline-secondary" href="<?php echo public_url('super/data/export.php?type=products&period=all'); ?>">Products</a>
          <a class="btn btn-outline-secondary" href="<?php echo public_url('super/data/export.php?type=sales&period=all'); ?>">Sales</a>
          <a class="btn btn-outline-secondary" href="<?php echo public_url('super/data/export.php?type=profit&period=all'); ?>">Profit margins</a>
          <a class="btn btn-outline-primary" href="<?php echo public_url('super/data/export.php?type=all&period=all'); ?>">All workbook</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
