<?php
// public/super/finances/index.php — money flow, expenses, product P&L, overall store P&L.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();

$pdo = Database::pdo();
$F = new Models\FinanceModel($pdo);
$SA = new Models\SaleModel($pdo);
$OR = new Models\OrderModel($pdo);
$P = new Models\ProductModel($pdo);
$SP = new Models\StoreProductModel($pdo);

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
            header('Location: ' . public_url('super/finances/?period=' . urlencode($period)));
            exit;
        }
        $error = $res['errors']['amount'] ?? ($res['errors']['_'] ?? 'Could not save expense.');
    } elseif ($action === 'delete_entry') {
        if ($F->deleteEntry((int) ($_POST['id'] ?? 0))) {
            $_SESSION['flash']['success'] = 'Entry removed.';
            header('Location: ' . public_url('super/finances/?period=' . urlencode($period)));
            exit;
        }
        $error = 'Could not delete that entry.';
    }
}

function fin_sales(Models\SaleModel $SA, Models\OrderModel $OR, string $period): array
{
    $sales = $SA->forTenant(1000, $period);
    foreach ($sales as &$s) { $s['source'] = 'sale'; }
    unset($s);
    $orders = $OR->forTenant(1000, $period);
    foreach ($orders as &$o) { $o['source'] = 'order'; }
    unset($o);
    return array_merge($sales, $orders);
}

function fin_period_sql(string $period, string $col): string
{
    if ($period === 'today') return "DATE({$col}) = CURDATE()";
    if ($period === 'week') return "{$col} >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    if ($period === 'month') return "{$col} >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    return '1=1';
}

$salesRows = fin_sales($SA, $OR, $period);
$sum = Models\SaleModel::summarize($salesRows);
$entrySum = $F->summarize($period);
$expenses = $F->forTenant('expense', $period, 300);
$revenues = $F->forTenant('revenue', $period, 100);

// Deposits / payments collected in period (cash movement into the shop).
$tid = (int) TenantContext::tenantId();
$paySql = 'SELECT COALESCE(SUM(amount),0) FROM order_payments WHERE tenant_id = ? AND ' . fin_period_sql($period, 'created_at');
$stPay = $pdo->prepare($paySql);
$stPay->execute([$tid]);
$depositsCollected = round((float) $stPay->fetchColumn(), 2);

$saleCharges = (float) ($sum['additional_charges'] ?? 0);
$otherRevenue = (float) ($entrySum['revenue'] ?? 0);
$expenseTotal = (float) ($entrySum['expense'] ?? 0);
$salesCollected = (float) ($sum['collected'] ?? 0);

// Product profit (COGS-based).
$costCol = 'buying_price';
try { $pdo->query('SELECT buying_price FROM products LIMIT 1'); } catch (Throwable $e) { $costCol = null; }
$productProfitRows = [];
$cogs = 0.0;
$productRevenue = 0.0;
if ($costCol) {
    $saleProfit = $SA->productProfit($period, $costCol);
    $orderProfit = $OR->productProfit($period, $costCol);
    $byId = [];
    foreach (array_merge($saleProfit, $orderProfit) as $row) {
        $pid = (int) ($row['product_id'] ?? 0);
        if ($pid <= 0) continue;
        if (!isset($byId[$pid])) {
            $byId[$pid] = [
                'product_id' => $pid,
                'product_name' => $row['product_name'] ?? 'Product',
                'qty' => 0.0,
                'revenue' => 0.0,
                'cost' => 0.0,
            ];
        }
        $byId[$pid]['qty'] += (float) ($row['qty'] ?? 0);
        $byId[$pid]['revenue'] += (float) ($row['revenue'] ?? 0);
        $byId[$pid]['cost'] += (float) ($row['cost'] ?? 0);
    }
    foreach ($byId as &$r) {
        $r['profit'] = round($r['revenue'] - $r['cost'], 2);
        $productRevenue += $r['revenue'];
        $cogs += $r['cost'];
    }
    unset($r);
    usort($byId, fn($a, $b) => $b['profit'] <=> $a['profit']);
    $productProfitRows = array_values($byId);
}
$grossProfit = round($productRevenue - $cogs, 2);

// Damaged stock loss (faulty_quantity × buying price) — same idea as Sales page.
$damagedLoss = 0.0;
try {
    $stD = $pdo->prepare(
        'SELECT COALESCE(SUM(COALESCE(faulty_quantity,0) * COALESCE(buying_price,0)),0)
           FROM products WHERE tenant_id = ?'
    );
    $stD->execute([$tid]);
    $damagedLoss = round((float) $stD->fetchColumn(), 2);
} catch (Throwable $ignored) {}

$moneyIn = round($salesCollected + $otherRevenue, 2);
$moneyOut = round($expenseTotal + $cogs + $damagedLoss, 2);
$netProfit = round($grossProfit + $saleCharges + $otherRevenue - $expenseTotal - $damagedLoss, 2);

$capital = $SP->capitalSummary();
$transferTotal = $SP->transferTotalForPeriod($period);
$transferInvoices = $SP->transfersForPeriod($period, 40);

// Combined flow rows for the ledger table.
$flow = [];
foreach ($salesRows as $s) {
    $amt = array_key_exists('_recognized_revenue', $s)
        ? (float) $s['_recognized_revenue']
        : (float) ($s['amount_paid'] ?? $s['total'] ?? 0);
    if ($amt <= 0.0001 && (float) ($s['amount_due'] ?? 0) > 0) {
        // Unpaid credit sale — still show as credit outflow of goods / receivable.
        $flow[] = [
            'when' => $s['created_at'] ?? '',
            'type' => 'Credit sale',
            'detail' => ($s['receipt_number'] ?? '') . ' · ' . ($s['customer_name'] ?? $s['table_name'] ?? 'Customer'),
            'in' => 0.0,
            'out' => 0.0,
            'note' => 'Owes KES ' . number_format((float) ($s['amount_due'] ?? 0), 0),
        ];
        continue;
    }
    if ($amt <= 0.0001) continue;
    $flow[] = [
        'when' => $s['created_at'] ?? '',
        'type' => ((float) ($s['amount_due'] ?? 0) > 0.0001) ? 'Deposit / part pay' : 'Sale',
        'detail' => ($s['receipt_number'] ?? '') . ' · ' . ($s['customer_name'] ?? $s['table_name'] ?? 'Walk-in'),
        'in' => $amt,
        'out' => 0.0,
        'note' => '',
    ];
}
foreach ($revenues as $e) {
    $flow[] = [
        'when' => ($e['entry_date'] ?? '') . ' 12:00:00',
        'type' => 'Revenue',
        'detail' => ($e['category'] ?? '') . ($e['description'] ? ' · ' . $e['description'] : ''),
        'in' => (float) $e['amount'],
        'out' => 0.0,
        'note' => '',
    ];
}
foreach ($expenses as $e) {
    $flow[] = [
        'when' => ($e['entry_date'] ?? '') . ' 12:00:00',
        'type' => 'Expense',
        'detail' => ($e['category'] ?? '') . ($e['description'] ? ' · ' . $e['description'] : ''),
        'in' => 0.0,
        'out' => (float) $e['amount'],
        'note' => '',
    ];
}
foreach ($transferInvoices as $inv) {
    $flow[] = [
        'when' => $inv['created_at'] ?? '',
        'type' => 'Store → Inventory',
        'detail' => ($inv['invoice_number'] ?? 'Transfer') . ($inv['invoice_to'] ? ' · ' . $inv['invoice_to'] : ''),
        'in' => 0.0,
        'out' => 0.0,
        'note' => 'Capital moved KES ' . number_format((float) ($inv['total'] ?? 0), 0) . ' · ' . (int) ($inv['item_count'] ?? 0) . ' lines',
    ];
}
usort($flow, fn($a, $b) => strtotime($b['when'] ?? 'now') <=> strtotime($a['when'] ?? 'now'));
$flow = array_slice($flow, 0, 80);

$periodLabel = ['today' => 'Today', 'week' => 'Last 7 days', 'month' => 'Last 30 days', 'all' => 'All time'][$period];
$page_title = 'Finances';
ob_start();
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h1 class="h5 fw-bold mb-0"><i class="fas fa-chart-pie me-2 text-primary"></i>Finances</h1>
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
      <div class="text-muted small text-uppercase fw-semibold">Money in</div>
      <div class="h5 mb-0 fw-bold text-success">KES <?php echo number_format($moneyIn, 0); ?></div>
      <div class="text-muted" style="font-size:.7rem;">Sales collected</div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-3">
      <div class="text-muted small text-uppercase fw-semibold">Payments / deposits</div>
      <div class="h5 mb-0 fw-bold">KES <?php echo number_format($depositsCollected, 0); ?></div>
      <div class="text-muted" style="font-size:.7rem;">Recorded in period</div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-3">
      <div class="text-muted small text-uppercase fw-semibold">Expenses</div>
      <div class="h5 mb-0 fw-bold text-danger">KES <?php echo number_format($expenseTotal, 0); ?></div>
      <div class="text-muted" style="font-size:.7rem;"><a href="<?php echo public_url('super/expenses/?period=' . urlencode($period)); ?>">Manage expenses</a> · <?php echo (int) ($entrySum['count_expense'] ?? 0); ?> entries</div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-3">
      <div class="text-muted small text-uppercase fw-semibold">Overall net profit</div>
      <div class="h5 mb-0 fw-bold <?php echo $netProfit < 0 ? 'text-danger' : 'text-success'; ?>">KES <?php echo number_format($netProfit, 0); ?></div>
      <div class="text-muted" style="font-size:.7rem;"><?php echo htmlspecialchars($periodLabel); ?></div>
    </div></div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-3">
      <div class="text-muted small text-uppercase fw-semibold">Product gross profit</div>
      <div class="h5 mb-0 fw-bold">KES <?php echo number_format($grossProfit, 0); ?></div>
      <div class="text-muted" style="font-size:.7rem;">Revenue − cost of goods</div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-3">
      <div class="text-muted small text-uppercase fw-semibold">Extra sale charges</div>
      <div class="h5 mb-0 fw-bold text-success">KES <?php echo number_format($saleCharges, 0); ?></div>
      <div class="text-muted" style="font-size:.7rem;">Pure profit on sales</div>
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
      <div class="text-muted small text-uppercase fw-semibold">Damaged stock loss</div>
      <div class="h5 mb-0 fw-bold text-danger">KES <?php echo number_format($damagedLoss, 0); ?></div>
      <div class="text-muted" style="font-size:.7rem;">Faulty × buying price</div>
    </div></div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-3">
      <div class="text-muted small text-uppercase fw-semibold">Store warehouse capital</div>
      <div class="h5 mb-0 fw-bold">KES <?php echo number_format((float) ($capital['warehouse']['capital'] ?? 0), 0); ?></div>
      <div class="text-muted" style="font-size:.7rem;"><a href="<?php echo public_url('super/store/'); ?>">Store</a> · <?php echo (int) ($capital['warehouse']['lines'] ?? 0); ?> lines waiting</div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-3">
      <div class="text-muted small text-uppercase fw-semibold">Shop inventory capital</div>
      <div class="h5 mb-0 fw-bold">KES <?php echo number_format((float) ($capital['shop']['capital'] ?? 0), 0); ?></div>
      <div class="text-muted" style="font-size:.7rem;"><a href="<?php echo public_url('super/inventory/'); ?>">Inventory</a> · sellable stock</div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-3">
      <div class="text-muted small text-uppercase fw-semibold">Transfers this period</div>
      <div class="h5 mb-0 fw-bold">KES <?php echo number_format($transferTotal, 0); ?></div>
      <div class="text-muted" style="font-size:.7rem;">Store → Inventory capital moved</div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-3">
      <div class="text-muted small text-uppercase fw-semibold">Total stock capital</div>
      <div class="h5 mb-0 fw-bold">KES <?php echo number_format((float) ($capital['total_capital'] ?? 0), 0); ?></div>
      <div class="text-muted" style="font-size:.7rem;">Warehouse + shop buying cost</div>
    </div></div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-3">
      <div class="text-muted small">Internal transfer invoices move capital from Store warehouse into shop Inventory without recording products twice. Cash P&amp;L still comes from sales, expenses, and deposits.</div>
    </div></div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-4">
    <form method="post" class="card border-0 shadow-sm" style="border-radius:14px;">
      <input type="hidden" name="action" value="add_expense">
      <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3">Record expense</h2>
        <div class="mb-3">
          <label class="form-label small">Category</label>
          <input list="expenseCats" name="category" class="form-control" placeholder="e.g. Rent, Transport, Salaries" required>
          <datalist id="expenseCats">
            <option value="Rent"><option value="Transport"><option value="Salaries">
            <option value="Utilities"><option value="Stock purchase"><option value="General expense">
          </datalist>
        </div>
        <div class="mb-3">
          <label class="form-label small">Amount (KES)</label>
          <input type="number" step="0.01" min="0" name="amount" class="form-control" required>
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
          <label class="form-label small">Description</label>
          <input type="text" name="description" class="form-control" placeholder="Optional">
        </div>
        <div class="mb-3">
          <label class="form-label small">Reference</label>
          <input type="text" name="reference" class="form-control" placeholder="Optional">
        </div>
        <button class="btn btn-danger w-100"><i class="fas fa-minus me-1"></i>Add expense</button>
      </div>
    </form>

    <div class="card border-0 shadow-sm mt-4" style="border-radius:14px;overflow:hidden;">
      <div class="px-4 py-3 border-bottom"><h2 class="h6 fw-bold mb-0">Recent expenses</h2></div>
      <div class="table-responsive" style="max-height:360px;overflow:auto;">
        <table class="table table-sm align-middle mb-0">
          <tbody>
            <?php if (!$expenses): ?>
              <tr><td class="text-muted text-center py-3">No expenses yet.</td></tr>
            <?php else: foreach ($expenses as $e): ?>
              <tr>
                <td class="small">
                  <div class="fw-semibold"><?php echo htmlspecialchars($e['category']); ?></div>
                  <div class="text-muted"><?php echo htmlspecialchars(date('j M Y', strtotime($e['entry_date']))); ?></div>
                </td>
                <td class="text-end fw-bold text-danger">KES <?php echo number_format((float) $e['amount'], 0); ?>
                  <form method="post" class="d-inline ms-1" onsubmit="return confirm('Delete expense?');">
                    <input type="hidden" name="action" value="delete_entry">
                    <input type="hidden" name="id" value="<?php echo (int) $e['id']; ?>">
                    <button class="btn btn-link btn-sm text-danger p-0">×</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card border-0 shadow-sm mb-4" style="border-radius:14px;overflow:hidden;">
      <div class="px-4 py-3 border-bottom"><h2 class="h6 fw-bold mb-0">Money flow · <?php echo htmlspecialchars($periodLabel); ?></h2></div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr class="text-muted small text-uppercase"><th>When</th><th>Type</th><th>Detail</th><th class="text-end">In</th><th class="text-end">Out</th></tr></thead>
          <tbody>
            <?php if (!$flow): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">No money movement in this period.</td></tr>
            <?php else: foreach ($flow as $row): ?>
              <tr>
                <td class="small text-nowrap"><?php echo $row['when'] ? htmlspecialchars(date('j M, g:i a', strtotime($row['when']))) : '—'; ?></td>
                <td class="small fw-semibold"><?php echo htmlspecialchars($row['type']); ?></td>
                <td class="small"><?php echo htmlspecialchars($row['detail']); ?><?php if ($row['note']): ?><div class="text-muted"><?php echo htmlspecialchars($row['note']); ?></div><?php endif; ?></td>
                <td class="text-end text-success small"><?php echo $row['in'] > 0 ? 'KES ' . number_format($row['in'], 0) : '—'; ?></td>
                <td class="text-end text-danger small"><?php echo $row['out'] > 0 ? 'KES ' . number_format($row['out'], 0) : '—'; ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card border-0 shadow-sm mb-4" style="border-radius:14px;overflow:hidden;">
      <div class="px-4 py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="h6 fw-bold mb-0">Internal transfers · Store → Inventory</h2>
        <a class="small" href="<?php echo public_url('super/store/'); ?>">Open Store</a>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr class="text-muted small text-uppercase"><th>Invoice</th><th>To</th><th>When</th><th class="text-end">Lines</th><th class="text-end">Capital</th></tr></thead>
          <tbody>
            <?php if (!$transferInvoices): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">No warehouse transfers in this period.</td></tr>
            <?php else: foreach ($transferInvoices as $inv): ?>
              <tr>
                <td class="fw-semibold small"><a href="<?php echo public_url('super/store/invoice.php?id=' . (int) $inv['id']); ?>"><?php echo htmlspecialchars($inv['invoice_number']); ?></a></td>
                <td class="small"><?php echo htmlspecialchars($inv['invoice_to'] ?: '—'); ?></td>
                <td class="small text-muted"><?php echo htmlspecialchars(date('j M Y, g:i a', strtotime($inv['created_at']))); ?></td>
                <td class="text-end small"><?php echo (int) ($inv['item_count'] ?? 0); ?></td>
                <td class="text-end fw-semibold small">KES <?php echo number_format((float) $inv['total'], 0); ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
          <?php if ($transferInvoices): ?>
          <tfoot>
            <tr class="border-top">
              <th colspan="4">Capital moved this period</th>
              <th class="text-end">KES <?php echo number_format($transferTotal, 0); ?></th>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
      <div class="px-4 py-3 border-bottom"><h2 class="h6 fw-bold mb-0">Product profit / loss</h2></div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr class="text-muted small text-uppercase"><th>Product</th><th class="text-end">Qty</th><th class="text-end">Revenue</th><th class="text-end">Cost</th><th class="text-end">Profit</th></tr></thead>
          <tbody>
            <?php if (!$productProfitRows): ?>
              <tr><td colspan="5" class="text-center text-muted py-4"><?php echo $costCol ? 'No product sales in this period.' : 'Buying price not available for profit calc.'; ?></td></tr>
            <?php else: foreach (array_slice($productProfitRows, 0, 40) as $pr): ?>
              <tr>
                <td class="fw-semibold small"><?php echo htmlspecialchars($pr['product_name']); ?></td>
                <td class="text-end small"><?php echo rtrim(rtrim(number_format($pr['qty'], 2), '0'), '.'); ?></td>
                <td class="text-end small">KES <?php echo number_format($pr['revenue'], 0); ?></td>
                <td class="text-end small">KES <?php echo number_format($pr['cost'], 0); ?></td>
                <td class="text-end fw-bold small <?php echo $pr['profit'] < 0 ? 'text-danger' : 'text-success'; ?>">KES <?php echo number_format($pr['profit'], 0); ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
          <?php if ($productProfitRows): ?>
          <tfoot>
            <tr class="border-top">
              <th colspan="2">Totals</th>
              <th class="text-end">KES <?php echo number_format($productRevenue, 0); ?></th>
              <th class="text-end">KES <?php echo number_format($cogs, 0); ?></th>
              <th class="text-end">KES <?php echo number_format($grossProfit, 0); ?></th>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
