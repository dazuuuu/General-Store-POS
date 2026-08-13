<?php
// public/super/revenues/index.php — record and review non-sale revenues + sale charge income.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();

$pdo = Database::pdo();
$F = new Models\FinanceModel($pdo);
$SA = new Models\SaleModel($pdo);
$OR = new Models\OrderModel($pdo);

$allowed = ['today', 'week', 'month', 'all'];
$period = in_array($_GET['period'] ?? '', $allowed, true) ? $_GET['period'] : 'month';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_revenue') {
        $res = $F->create([
            'entry_type' => 'revenue',
            'category' => $_POST['category'] ?? 'Other revenue',
            'description' => $_POST['description'] ?? '',
            'amount' => $_POST['amount'] ?? 0,
            'payment_method' => $_POST['payment_method'] ?? '',
            'reference' => $_POST['reference'] ?? '',
            'entry_date' => $_POST['entry_date'] ?? date('Y-m-d'),
            'created_by' => TenantContext::userId(),
        ]);
        if ($res['ok']) {
            $_SESSION['flash']['success'] = 'Revenue recorded.';
            header('Location: ' . public_url('super/revenues/?period=' . urlencode($period)));
            exit;
        }
        $error = $res['errors']['amount'] ?? ($res['errors']['_'] ?? 'Could not save revenue.');
    } elseif ($action === 'delete_entry') {
        if ($F->deleteEntry((int) ($_POST['id'] ?? 0))) {
            $_SESSION['flash']['success'] = 'Revenue entry removed.';
            header('Location: ' . public_url('super/revenues/?period=' . urlencode($period)));
            exit;
        }
        $error = 'Could not delete that entry.';
    }
}

function rev_sales(Models\SaleModel $SA, Models\OrderModel $OR, string $period): array
{
    $sales = $SA->forTenant(1000, $period);
    $orders = $OR->forTenant(1000, $period);
    return array_merge($sales, $orders);
}

$salesRows = rev_sales($SA, $OR, $period);
$salesSum = Models\SaleModel::summarize($salesRows);
$entrySum = $F->summarize($period);
$entries = $F->forTenant('revenue', $period, 300);
$saleCharges = (float) ($salesSum['additional_charges'] ?? 0);
$otherRevenue = (float) ($entrySum['revenue'] ?? 0);
$totalRevenue = round((float) ($salesSum['collected'] ?? 0) + $otherRevenue, 2);

$periodLabel = ['today' => 'Today', 'week' => 'Last 7 days', 'month' => 'Last 30 days', 'all' => 'All time'][$period];
$page_title = 'Revenues';
ob_start();
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h1 class="h5 fw-bold mb-0"><i class="fas fa-sack-dollar me-2 text-success"></i>Revenues</h1>
  <div class="btn-group">
    <?php foreach (['today' => 'Today', 'week' => '7 days', 'month' => '30 days', 'all' => 'All'] as $p => $lbl): ?>
      <a href="?period=<?php echo $p; ?>" class="btn btn-sm <?php echo $period === $p ? 'btn-primary' : 'btn-outline-secondary'; ?>"><?php echo $lbl; ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if (!empty($_SESSION['flash']['success'])): ?>
  <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['flash']['success']); unset($_SESSION['flash']['success']); ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-3">
      <div class="text-muted small text-uppercase fw-semibold">Sales collected</div>
      <div class="h5 mb-0 fw-bold">KES <?php echo number_format((float) ($salesSum['collected'] ?? 0), 0); ?></div>
      <div class="text-muted" style="font-size:.7rem;"><?php echo $periodLabel; ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-3">
      <div class="text-muted small text-uppercase fw-semibold">Extra charges</div>
      <div class="h5 mb-0 fw-bold text-success">KES <?php echo number_format($saleCharges, 0); ?></div>
      <div class="text-muted" style="font-size:.7rem;">From sales / invoices</div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-3">
      <div class="text-muted small text-uppercase fw-semibold">Other revenues</div>
      <div class="h5 mb-0 fw-bold">KES <?php echo number_format($otherRevenue, 0); ?></div>
      <div class="text-muted" style="font-size:.7rem;"><?php echo (int) ($entrySum['count_revenue'] ?? 0); ?> entries</div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-3">
      <div class="text-muted small text-uppercase fw-semibold">Total money in</div>
      <div class="h5 mb-0 fw-bold text-primary">KES <?php echo number_format($totalRevenue, 0); ?></div>
      <div class="text-muted" style="font-size:.7rem;">Sales + other revenues</div>
    </div></div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-4">
    <form method="post" class="card border-0 shadow-sm" style="border-radius:14px;">
      <input type="hidden" name="action" value="add_revenue">
      <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3">Record other revenue</h2>
        <div class="mb-3">
          <label class="form-label small">Category</label>
          <input list="revenueCats" name="category" class="form-control" placeholder="e.g. Service fee, Rent income" required>
          <datalist id="revenueCats">
            <option value="Service fee"><option value="Delivery income"><option value="Rent income">
            <option value="Commission"><option value="Other revenue">
          </datalist>
        </div>
        <div class="mb-3">
          <label class="form-label small">Amount (KES)</label>
          <input type="number" step="0.01" min="0" name="amount" class="form-control" required placeholder="0">
        </div>
        <div class="mb-3">
          <label class="form-label small">Date</label>
          <input type="date" name="entry_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label small">Payment mode</label>
          <select name="payment_method" class="form-select">
            <option value="cash">Cash</option>
            <option value="mpesa">M-Pesa</option>
            <option value="bank">Bank</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label small">Description</label>
          <input type="text" name="description" class="form-control" placeholder="Optional note">
        </div>
        <div class="mb-3">
          <label class="form-label small">Reference</label>
          <input type="text" name="reference" class="form-control" placeholder="Optional">
        </div>
        <button class="btn btn-success w-100"><i class="fas fa-plus me-1"></i>Add revenue</button>
        <div class="form-text mt-2 mb-0">Sale and invoice money is tracked automatically. Use this for other income only.</div>
      </div>
    </form>
  </div>
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
      <div class="px-4 py-3 border-bottom"><h2 class="h6 fw-bold mb-0">Other revenue entries · <?php echo htmlspecialchars($periodLabel); ?></h2></div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr class="text-muted small text-uppercase"><th>Date</th><th>Category</th><th>Details</th><th class="text-end">Amount</th><th></th></tr></thead>
          <tbody>
            <?php if (!$entries): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">No other revenues recorded in this period.</td></tr>
            <?php else: foreach ($entries as $e): ?>
              <tr>
                <td class="small"><?php echo htmlspecialchars(date('j M Y', strtotime($e['entry_date']))); ?></td>
                <td class="fw-semibold"><?php echo htmlspecialchars($e['category']); ?></td>
                <td class="small text-muted">
                  <?php echo htmlspecialchars($e['description'] ?: '—'); ?>
                  <?php if (!empty($e['payment_method'])): ?> · <?php echo htmlspecialchars($e['payment_method']); ?><?php endif; ?>
                </td>
                <td class="text-end fw-bold text-success">KES <?php echo number_format((float) $e['amount'], 0); ?></td>
                <td class="text-end">
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete this revenue entry?');">
                    <input type="hidden" name="action" value="delete_entry">
                    <input type="hidden" name="id" value="<?php echo (int) $e['id']; ?>">
                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
