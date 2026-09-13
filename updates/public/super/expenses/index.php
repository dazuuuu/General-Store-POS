<?php
// public/super/expenses/index.php — record and review shop expenses / money out (losses).
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
    if ($action === 'add_expense') {
        $res = $F->create([
            'entry_type' => 'expense',
            'category' => $_POST['category'] ?? 'General expense',
            'description' => $_POST['description'] ?? '',
            'amount' => $_POST['amount'] ?? 0,
            'payment_method' => $_POST['payment_method'] ?? '',
            'reference' => $_POST['reference'] ?? '',
            'entry_date' => $_POST['entry_date'] ?? date('Y-m-d'),
            'created_by' => TenantContext::userId(),
        ]);
        if ($res['ok']) {
            $_SESSION['flash']['success'] = 'Expense recorded.';
            header('Location: ' . public_url('super/expenses/?period=' . urlencode($period)));
            exit;
        }
        $error = $res['errors']['amount'] ?? ($res['errors']['_'] ?? 'Could not save expense.');
    } elseif ($action === 'delete_entry') {
        if ($F->deleteEntry((int) ($_POST['id'] ?? 0))) {
            $_SESSION['flash']['success'] = 'Expense entry removed.';
            header('Location: ' . public_url('super/expenses/?period=' . urlencode($period)));
            exit;
        }
        $error = 'Could not delete that entry.';
    }
}

function exp_sales(Models\SaleModel $SA, Models\OrderModel $OR, string $period): array
{
    return array_merge($SA->forTenant(1000, $period), $OR->forTenant(1000, $period));
}

function exp_period_sql(string $period, string $col): string
{
    if ($period === 'today') return "DATE({$col}) = CURDATE()";
    if ($period === 'week') return "{$col} >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    if ($period === 'month') return "{$col} >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    return '1=1';
}

$salesRows = exp_sales($SA, $OR, $period);
$salesSum = Models\SaleModel::summarize($salesRows);
$entrySum = $F->summarize($period);
$entries = $F->forTenant('expense', $period, 300);
$expenseTotal = (float) ($entrySum['expense'] ?? 0);

// Cost of goods sold for the period (product cost of recognized sales).
$tid = (int) TenantContext::tenantId();
$cogs = 0.0;
try {
    $saleProfit = $SA->productProfit($period, 'buying_price');
    $orderProfit = $OR->productProfit($period, 'buying_price');
    foreach (array_merge($saleProfit, $orderProfit) as $row) {
        $cogs += (float) ($row['cost'] ?? 0);
    }
    $cogs = round($cogs, 2);
} catch (Throwable $ignored) {
}

// Damaged / faulty stock loss (shop-wide).
$damagedLoss = 0.0;
try {
    $stD = $pdo->prepare(
        'SELECT COALESCE(SUM(COALESCE(faulty_quantity,0) * COALESCE(buying_price,0)),0)
           FROM products WHERE tenant_id = ?'
    );
    $stD->execute([$tid]);
    $damagedLoss = round((float) $stD->fetchColumn(), 2);
} catch (Throwable $ignored) {
}

$totalLoss = round($expenseTotal + $cogs + $damagedLoss, 2);
$discounts = (float) ($salesSum['discount'] ?? 0);

$periodLabel = ['today' => 'Today', 'week' => 'Last 7 days', 'month' => 'Last 30 days', 'all' => 'All time'][$period];
$page_title = 'Expenses';
ob_start();
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h1 class="h5 fw-bold mb-0"><i class="fas fa-money-bill-wave me-2 text-danger"></i>Expenses</h1>
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
      <div class="text-muted small text-uppercase fw-semibold">Recorded expenses</div>
      <div class="h5 mb-0 fw-bold text-danger">KES <?php echo number_format($expenseTotal, 0); ?></div>
      <div class="text-muted" style="font-size:.7rem;"><?php echo (int) ($entrySum['count_expense'] ?? 0); ?> entries · <?php echo htmlspecialchars($periodLabel); ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-3">
      <div class="text-muted small text-uppercase fw-semibold">Cost of goods</div>
      <div class="h5 mb-0 fw-bold text-danger">KES <?php echo number_format($cogs, 0); ?></div>
      <div class="text-muted" style="font-size:.7rem;">Buying cost of sold products</div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-3">
      <div class="text-muted small text-uppercase fw-semibold">Damaged stock</div>
      <div class="h5 mb-0 fw-bold text-danger">KES <?php echo number_format($damagedLoss, 0); ?></div>
      <div class="text-muted" style="font-size:.7rem;">Faulty × buying price</div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-3">
      <div class="text-muted small text-uppercase fw-semibold">Total money out</div>
      <div class="h5 mb-0 fw-bold text-danger">KES <?php echo number_format($totalLoss, 0); ?></div>
      <div class="text-muted" style="font-size:.7rem;">Expenses + COGS + damage<?php if ($discounts > 0): ?> · discounts KES <?php echo number_format($discounts, 0); ?><?php endif; ?></div>
    </div></div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-4">
    <form method="post" class="card border-0 shadow-sm" style="border-radius:14px;">
      <input type="hidden" name="action" value="add_expense">
      <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3">Record expense</h2>
        <div class="mb-3">
          <label class="form-label small">What caused the expense</label>
          <input list="expenseCats" name="category" class="form-control" placeholder="e.g. Rent, Transport, Salaries" required>
          <datalist id="expenseCats">
            <option value="Rent"><option value="Transport"><option value="Salaries">
            <option value="Utilities"><option value="Stock purchase"><option value="Repairs">
            <option value="Packaging"><option value="General expense">
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
          <label class="form-label small">Paid via</label>
          <select name="payment_method" class="form-select">
            <option value="cash">Cash</option>
            <option value="mpesa">M-Pesa</option>
            <option value="bank">Bank</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label small">Details / cause</label>
          <input type="text" name="description" class="form-control" placeholder="What this expense was for">
        </div>
        <div class="mb-3">
          <label class="form-label small">Reference</label>
          <input type="text" name="reference" class="form-control" placeholder="Optional receipt / M-Pesa code">
        </div>
        <button class="btn btn-danger w-100"><i class="fas fa-minus me-1"></i>Add expense</button>
        <div class="form-text mt-2 mb-0">Use this for money the shop spends (rent, transport, wages, repairs). Product cost and damaged stock are calculated automatically.</div>
      </div>
    </form>
  </div>
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
      <div class="px-4 py-3 border-bottom"><h2 class="h6 fw-bold mb-0">Expense entries · <?php echo htmlspecialchars($periodLabel); ?></h2></div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr class="text-muted small text-uppercase"><th>Date</th><th>Cause / category</th><th>Details</th><th class="text-end">Amount</th><th></th></tr></thead>
          <tbody>
            <?php if (!$entries): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">No expenses recorded in this period.</td></tr>
            <?php else: foreach ($entries as $e): ?>
              <tr>
                <td class="small"><?php echo htmlspecialchars(date('j M Y', strtotime($e['entry_date']))); ?></td>
                <td class="fw-semibold"><?php echo htmlspecialchars($e['category']); ?></td>
                <td class="small text-muted">
                  <?php echo htmlspecialchars($e['description'] ?: '—'); ?>
                  <?php if (!empty($e['payment_method'])): ?> · <?php echo htmlspecialchars($e['payment_method']); ?><?php endif; ?>
                </td>
                <td class="text-end fw-bold text-danger">KES <?php echo number_format((float) $e['amount'], 0); ?></td>
                <td class="text-end">
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete this expense entry?');">
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
