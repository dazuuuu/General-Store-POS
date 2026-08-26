<?php
// public/super/dashboard/index.php — owner analytics dashboard
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();

$pdo = Database::pdo();
$__tenant = (new Models\TenantModel($pdo))->find(TenantContext::tenantId());
$currency = $__tenant['currency'] ?? 'KES';

/** Paid orders + legacy direct sales, merged newest-first (mirrors super/sales/index.php). */
function dash_sales(Models\SaleModel $SA, Models\OrderModel $OR, string $period): array
{
    $sales = $SA->forTenant(1000, $period);
    foreach ($sales as &$s) { $s['receipt_url'] = 'super/sales/receipt.php?id=' . (int) $s['id']; $s['source'] = 'sale'; }
    unset($s);
    $orders = $OR->forTenant(1000, $period);
    foreach ($orders as &$o) { $o['receipt_url'] = 'super/orders/receipt.php?id=' . (int) $o['id']; }
    unset($o);
    $merged = array_merge($sales, $orders);
    usort($merged, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
    return $merged;
}

$SA = new Models\SaleModel($pdo);
$OR = new Models\OrderModel($pdo);
$P  = new Models\ProductModel($pdo);
$svc = new StaffService($pdo);

$today = dash_sales($SA, $OR, 'today');
$week  = dash_sales($SA, $OR, 'week');
$todaySum = Models\SaleModel::summarize($today);
$weekSum  = Models\SaleModel::summarize($week);

$lowStock  = $P->lowStock();
$staffList = $svc->listForTenant((int) TenantContext::tenantId());
$openTabs  = $OR->openOrders();
$dashCreditOwed = 0.0;
foreach ($openTabs as $tab) {
    $dashCreditOwed += max(0, (float) ($tab['total'] ?? 0) - max(0, (float) ($tab['amount_paid'] ?? 0)));
}

$weekProfitRows = [];
$profitAvailable = true;
try {
    $byProduct = [];
    foreach ($SA->productProfit('week') as $pp) {
        $byProduct[(int) $pp['product_id']] = $pp;
    }
    foreach ($OR->productProfit('week') as $op) {
        $pid = (int) $op['product_id'];
        if (!isset($byProduct[$pid])) {
            $byProduct[$pid] = $op;
            continue;
        }
        foreach (['qty', 'revenue', 'cost', 'profit'] as $k) {
            $byProduct[$pid][$k] = (float) ($byProduct[$pid][$k] ?? 0) + (float) ($op[$k] ?? 0);
        }
    }
    $weekProfitRows = array_values($byProduct);
} catch (Throwable $e) {
    $profitAvailable = false;
}
$weekCogs = 0.0;
$salesLoss = 0.0;
foreach ($weekProfitRows as $pp) {
    $weekCogs += (float) ($pp['cost'] ?? 0);
    if ((float) ($pp['profit'] ?? 0) < 0) {
        $salesLoss += abs((float) $pp['profit']);
    }
}
$weekNetProfit = round(($weekSum['revenue'] ?? 0) - $weekCogs, 2);
$weekExtraCharges = round((float) ($weekSum['additional_charges'] ?? 0), 2);
$todayExtraCharges = round((float) ($todaySum['additional_charges'] ?? 0), 2);
$damagedLoss = 0.0;
try {
    $damagedStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(COALESCE(faulty_quantity,0) * COALESCE(buying_price,0)),0)
           FROM products
          WHERE tenant_id = ?"
    );
    $damagedStmt->execute([(int) TenantContext::tenantId()]);
    $damagedLoss = round((float) $damagedStmt->fetchColumn(), 2);
} catch (Throwable $e) {
    $damagedLoss = 0.0;
}
$totalLoss = round($salesLoss + $damagedLoss, 2);
$profitAfterLoss = round($weekNetProfit - $damagedLoss, 2);

// Last 7 days revenue for the chart (oldest first).
$chartLabels = [];
$chartValues = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $chartLabels[] = date('D', strtotime($date));
    $dayTotal = 0.0;
    foreach ($week as $r) {
        if (date('Y-m-d', strtotime($r['created_at'])) === $date) {
            $dayTotal += array_key_exists('_recognized_revenue', $r)
                ? (float) $r['_recognized_revenue']
                : (float) $r['total'];
        }
    }
    $chartValues[] = round($dayTotal, 2);
}

$recent = array_slice($today ?: $week, 0, 8);
$recentSaleIds  = array_column(array_filter($recent, fn($s) => ($s['source'] ?? 'sale') === 'sale'), 'id');
$recentOrderIds = array_column(array_filter($recent, fn($s) => ($s['source'] ?? 'sale') === 'order'), 'id');
$recentItemsBySale  = $SA->itemsForMany($recentSaleIds);
$recentItemsByOrder = $OR->itemsForMany($recentOrderIds);
foreach ($recent as &$r) {
    $r['items'] = (($r['source'] ?? 'sale') === 'order' ? $recentItemsByOrder : $recentItemsBySale)[(int) $r['id']] ?? [];
}
unset($r);

$page_title = 'Dashboard';
$shop = $__tenant['name'] ?? 'your shop';
$userName = $_SESSION['username'] ?? 'Admin';
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$chartMax = max(1, max($chartValues));
$lossBars = array_map(fn($v) => round(max(0, $v * 0.38), 2), $chartValues);
$limitTarget = max(1, $weekSum['revenue'] + max($weekCogs, $damagedLoss, 1));
$limitPct = min(100, round(($weekSum['revenue'] / $limitTarget) * 100));
ob_start();
$icon = fn(string $n, int $s = 18) => NavIcons::svg($n, $s);
?>
<div class="fin-shell">
  <div class="fin-rail">
    <a class="rail-mark" href="<?php echo public_url('super/dashboard/'); ?>" title="Dashboard"><?php echo $icon('chart', 18); ?></a>
    <a class="rail-pill active" href="<?php echo public_url('super/dashboard/'); ?>" title="Overview"><?php echo $icon('overview'); ?></a>
    <a class="rail-pill" href="<?php echo public_url('super/shop/'); ?>" title="Shop"><?php echo $icon('shop'); ?></a>
    <a class="rail-pill" href="<?php echo public_url('super/sales/'); ?>" title="Sales"><?php echo $icon('sales'); ?></a>
    <a class="rail-pill" href="<?php echo public_url('super/invoices/'); ?>" title="Invoices"><?php echo $icon('invoices'); ?></a>
    <a class="rail-pill" href="<?php echo public_url('super/payments/'); ?>" title="Payments"><?php echo $icon('payments'); ?></a>
    <a class="rail-pill" href="<?php echo public_url('super/inventory/'); ?>" title="Inventory"><?php echo $icon('inventory'); ?></a>
    <a class="rail-pill" href="<?php echo public_url('super/store/'); ?>" title="Store"><?php echo $icon('store'); ?></a>
    <a class="rail-pill" href="<?php echo public_url('super/expenses/'); ?>" title="Expenses"><?php echo $icon('expenses'); ?></a>
    <a class="rail-pill" href="<?php echo public_url('super/finances/'); ?>" title="Finances"><?php echo $icon('finances'); ?></a>
    <a class="rail-pill" href="<?php echo public_url('super/stock/new.php'); ?>" title="Record stock"><?php echo $icon('stock'); ?></a>
    <a class="rail-pill" href="<?php echo public_url('super/settings/'); ?>" title="Settings"><?php echo $icon('settings'); ?></a>
    <span class="rail-spacer"></span>
    <a class="rail-pill" href="<?php echo public_url('auth/logout.php'); ?>" title="Logout"><?php echo $icon('logout'); ?></a>
  </div>

  <section class="fin-board">
    <header class="fin-head">
      <div class="fin-tabs">
        <a class="tab active" href="<?php echo public_url('super/dashboard/'); ?>">Overview</a>
        <a class="tab" href="<?php echo public_url('super/sales/'); ?>">Activity</a>
        <a class="tab" href="<?php echo public_url('super/inventory/'); ?>">Manage</a>
        <a class="tab" href="<?php echo public_url('super/stock/new.php'); ?>">Program</a>
        <a class="tab" href="<?php echo public_url('super/settings/'); ?>">Account</a>
        <a class="tab" href="<?php echo public_url('super/reports/'); ?>">Reports</a>
      </div>
      <div class="head-actions">
        <a class="circle-btn" href="<?php echo public_url('super/sales/'); ?>" title="Search sales"><?php echo $icon('search', 16); ?></a>
        <a class="circle-btn" href="<?php echo public_url('super/inventory/low-stock.php'); ?>" title="Alerts"><?php echo $icon('bell', 16); ?></a>
        <div class="profile-chip">
          <span class="avatar"><?php echo strtoupper(substr($userName, 0, 1)); ?></span>
          <span><strong><?php echo htmlspecialchars($userName); ?></strong><small><?php echo htmlspecialchars($shop); ?></small></span>
          <?php echo $icon('chevron', 14); ?>
        </div>
      </div>
    </header>

    <div class="fin-greeting">
      <h1><?php echo htmlspecialchars($greeting . ', ' . $userName); ?></h1>
      <p>Stay on top of sales, stock movement, credit invoices, and team activity.</p>
    </div>

    <div class="fin-grid">
      <section class="panel balance-panel">
        <div class="panel-label">Total Balance</div>
        <div class="money-xl"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($weekSum['revenue'], 2); ?></div>
        <div class="delta good"><?php echo $icon('arrow-up', 12); ?><?php echo $todaySum['count']; ?> sale<?php echo $todaySum['count'] === 1 ? '' : 's'; ?> today</div>
        <div class="balance-actions">
          <a class="dark-action" href="<?php echo public_url('super/shop/'); ?>"><?php echo $icon('transfer', 14); ?> Sell</a>
          <a class="light-action" href="<?php echo public_url('super/stock/new.php'); ?>"><?php echo $icon('plus', 14); ?> Stock</a>
        </div>
        <div class="wallets">
          <div class="wallet"><span>Today</span><strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($todaySum['revenue'], 0); ?></strong><small>Active</small></div>
          <div class="wallet"><span>Credit owed</span><strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($dashCreditOwed, 0); ?></strong><small><?php echo count($openTabs); ?> invoice<?php echo count($openTabs) === 1 ? '' : 's'; ?></small></div>
          <div class="wallet"><span>Low stock</span><strong><?php echo count($lowStock); ?></strong><small><?php echo $lowStock ? 'Review' : 'Clear'; ?></small></div>
        </div>
      </section>

      <section class="metric-grid">
        <article class="metric hot"><span>Total Earnings</span><strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($todaySum['revenue'], 0); ?></strong><small><?php echo $icon('arrow-up', 11); ?> Today</small></article>
        <article class="metric"><span>Credit sales owed</span><strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($dashCreditOwed, 0); ?></strong><small><?php echo $icon('invoice-dollar', 12); ?> <?php echo count($openTabs); ?> open</small></article>
        <article class="metric"><span>Sales Revenue</span><strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($weekSum['revenue'], 0); ?></strong><small><?php echo $icon('arrow-up', 11); ?> This week</small></article>
        <article class="metric"><span>Extra charges today</span><strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($todayExtraCharges, 0); ?></strong><small><?php echo $icon('arrow-up', 11); ?> Pure profit</small></article>
        <article class="metric <?php echo $profitAfterLoss < 0 ? 'danger' : ''; ?>"><span>Net Profit</span><strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($profitAvailable ? $profitAfterLoss : 0, 0); ?></strong><small><?php echo $icon('chart', 12); ?> After losses</small></article>
      </section>

      <section class="panel chart-panel">
        <div class="panel-title-row"><div><h2>Profit and Loss</h2><p>Weekly revenue against stock cost and losses</p></div></div>
        <div class="chart-card">
          <div class="chart-legend"><strong>Profit and Loss</strong><span><i class="dot violet"></i>Profit</span><span><i class="dot ink"></i>Loss</span></div>
          <div class="bar-chart">
            <?php foreach ($chartLabels as $i => $label):
              $profitHeight = max(8, round(($chartValues[$i] / $chartMax) * 120));
              $lossHeight = max(8, round(($lossBars[$i] / $chartMax) * 120));
            ?>
              <div class="bar-col">
                <div class="bar-stack" style="height:132px;">
                  <span class="bar loss" style="height:<?php echo $lossHeight; ?>px;"></span>
                  <span class="bar profit" style="height:<?php echo $profitHeight; ?>px;"></span>
                </div>
                <small><?php echo htmlspecialchars($label); ?></small>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <section class="panel pnl-panel">
        <div class="panel-title-row">
          <div>
            <h2>Profit and Loss Summary</h2>
            <p>This week</p>
          </div>
          <a href="<?php echo public_url('super/sales/'); ?>">Full report</a>
        </div>
        <?php if (!$profitAvailable): ?>
          <div class="empty-state">Profit and loss is unavailable until product buying prices are recorded.</div>
        <?php else: ?>
          <div class="pnl-grid">
            <div class="pnl-card strong">
              <span>Sales Revenue</span>
              <strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($weekSum['revenue'], 0); ?></strong>
              <small>Paid sales and paid orders</small>
            </div>
            <div class="pnl-card">
              <span>Cost of Goods</span>
              <strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($weekCogs, 0); ?></strong>
              <small>Buying price x sold quantity</small>
            </div>
            <div class="pnl-card <?php echo $weekNetProfit < 0 ? 'loss' : 'profit'; ?>">
              <span>Gross Profit</span>
              <strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($weekNetProfit, 0); ?></strong>
              <small>Revenue minus cost</small>
            </div>
            <div class="pnl-card profit">
              <span>Extra charges (pure profit)</span>
              <strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($weekExtraCharges, 0); ?></strong>
              <small>Delivery, packing, wallet extras · today <?php echo htmlspecialchars($currency); ?> <?php echo number_format($todayExtraCharges, 0); ?></small>
            </div>
            <div class="pnl-card loss">
              <span>Sales Loss</span>
              <strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($salesLoss, 0); ?></strong>
              <small>Products sold below cost</small>
            </div>
            <div class="pnl-card loss">
              <span>Damaged Stock Loss</span>
              <strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($damagedLoss, 0); ?></strong>
              <small>Faulty stock at buying price</small>
            </div>
            <div class="pnl-card loss">
              <span>Total Loss</span>
              <strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($totalLoss, 0); ?></strong>
              <small>Sales loss plus damaged stock</small>
            </div>
            <div class="pnl-card final <?php echo $profitAfterLoss < 0 ? 'loss' : 'profit'; ?>">
              <span>Profit After Loss</span>
              <strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($profitAfterLoss, 0); ?></strong>
              <small>Gross profit minus damaged stock</small>
            </div>
          </div>
        <?php endif; ?>
      </section>

      <section class="panel limit-panel">
        <h2>Monthly Spending Limit</h2>
        <div class="limit-track"><span style="width:<?php echo $limitPct; ?>%;"></span></div>
        <div class="limit-row"><span><?php echo htmlspecialchars($currency); ?> <?php echo number_format($weekCogs, 0); ?> spent out of</span><strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($limitTarget, 0); ?></strong></div>
      </section>

      <section class="panel card-panel">
        <div class="panel-title-row"><h2>My Cards</h2><a href="<?php echo public_url('super/payments/'); ?>">+ Add new</a></div>
        <div class="card-strip">
          <div class="pay-card dark"><span>Active</span><strong><?php echo htmlspecialchars($shop); ?></strong><small>**** **** <?php echo str_pad((string) TenantContext::tenantId(), 4, '0', STR_PAD_LEFT); ?></small></div>
          <div class="pay-card violet"><span>Active</span><strong><?php echo htmlspecialchars($currency); ?></strong><small>**** **** <?php echo date('md'); ?></small></div>
        </div>
      </section>

      <section class="panel activity-panel">
        <div class="panel-title-row">
          <h2>Recent Activities</h2>
          <div class="activity-tools"><span><?php echo $icon('search', 12); ?> Search</span><span>Filter <?php echo $icon('filter', 12); ?></span></div>
        </div>
        <?php if (!$recent): ?>
          <div class="empty-state">No sales recorded yet.</div>
        <?php else: ?>
        <div class="activity-table">
          <div class="activity-head"><span></span><span>Order ID</span><span>Activity</span><span>Price</span><span>Status</span><span>Date</span><span></span></div>
          <?php foreach (array_slice($recent, 0, 6) as $idx => $r):
            $paid = ($r['status'] ?? '') === 'paid' || ($r['payment_status'] ?? '') === 'paid';
            $pending = !$paid && (float) ($r['amount_due'] ?? 0) > 0;
          ?>
          <div class="activity-row">
            <span class="check <?php echo $idx === 3 ? 'on' : ''; ?>"><?php echo $icon('check', 10); ?></span>
            <span class="mono"><?php echo htmlspecialchars($r['receipt_number'] ?? ('INV_' . str_pad((string) $r['id'], 6, '0', STR_PAD_LEFT))); ?></span>
            <span><span class="act-icon"><?php echo $icon('cart', 11); ?></span><?php echo htmlspecialchars(($r['items'][0]['name'] ?? $r['customer_name'] ?? 'Sale')); ?></span>
            <span><?php echo htmlspecialchars($currency); ?> <?php echo number_format((float) $r['total'], 0); ?></span>
            <span class="status <?php echo $pending ? 'pending' : 'done'; ?>"><i></i><?php echo $pending ? 'Pending' : 'Completed'; ?></span>
            <span><?php echo date('j M Y, h:i A', strtotime($r['created_at'])); ?></span>
            <span class="dots">•••</span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </section>
    </div>
  </section>
</div>

<style>
.t-sidebar,.t-sidebar-toggle,.t-sidebar-overlay{display:none!important;}
.t-main{margin-left:0!important;width:100%!important;max-width:none!important;padding:0!important;background:#e9e9ea;min-height:100vh;}
.t-topbar{display:none;}
.fin-shell{display:grid;grid-template-columns:72px minmax(0,1fr);gap:0;width:100%;max-width:none;min-height:100vh;margin:0;background:#f3f3f4;border:0;border-radius:0;padding:0;box-shadow:none;}
.fin-rail{background:#fff;border-radius:0;border-right:1px solid #ece8ef;padding:16px 10px;display:flex;flex-direction:column;align-items:center;gap:10px;min-height:100vh;position:sticky;top:0;}
.rail-mark,.rail-pill{width:42px;height:42px;border-radius:14px;display:flex;align-items:center;justify-content:center;color:#625b69;text-decoration:none;}
.rail-mark{background:var(--pos-violet);color:#fff;}
.rail-pill.active{background:#17151d;color:#fff;}
.rail-pill:hover{background:var(--pos-violet-light);color:var(--pos-violet);}
.rail-spacer{flex:1;}
.nav-svg{display:block;flex-shrink:0;}
.fin-board{min-width:0;padding:20px 24px 28px;width:100%;}
.fin-head{height:56px;display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:12px;}
.fin-tabs{background:#fff;border-radius:20px;padding:7px;display:flex;gap:8px;align-items:center;}
.tab{border-radius:16px;padding:9px 18px;color:#4f4856;text-decoration:none;font-size:.84rem;}
.tab.active{background:#17151d;color:#fff;}
.head-actions{display:flex;gap:10px;align-items:center;}
.circle-btn{width:40px;height:40px;border:0;border-radius:50%;background:#fff;color:#17151d;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;}
.profile-chip{height:46px;border-radius:19px;background:#fff;padding:5px 10px 5px 6px;display:flex;align-items:center;gap:9px;min-width:190px;}
.profile-chip .avatar{width:34px;height:34px;border-radius:50%;background:var(--pos-violet-light);color:var(--pos-violet);display:flex;align-items:center;justify-content:center;font-weight:800;}
.profile-chip strong{display:block;font-size:.78rem;line-height:1.05;}
.profile-chip small{display:block;color:#8b8491;font-size:.68rem;max-width:112px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.fin-greeting{margin:8px 0 20px;}
.fin-greeting h1{font-size:2rem;line-height:1.12;margin:0 0 6px;font-weight:800;letter-spacing:0;}
.fin-greeting p{color:#686170;margin:0;font-size:.9rem;}
.fin-grid{display:grid;grid-template-columns:1.05fr 1.05fr 1fr;gap:16px;width:100%;}
.panel,.metric{background:#fff;border-radius:18px;border:1px solid #eeeeef;box-shadow:0 8px 20px rgba(23,21,29,.035);}
.balance-panel{padding:18px;min-height:274px;}
.panel-label{color:#746d7a;font-size:.84rem;margin-bottom:6px;}
.money-xl{font-size:1.6rem;font-weight:800;margin-bottom:6px;}
.delta{display:inline-flex;align-items:center;gap:4px;border-radius:999px;padding:3px 8px;font-size:.72rem;background:#f7f1ff;color:var(--pos-violet);}
.balance-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:18px 0;}
.dark-action,.light-action{border-radius:18px;padding:10px 12px;text-decoration:none;text-align:center;font-size:.86rem;display:inline-flex;align-items:center;justify-content:center;gap:6px;}
.dark-action{background:#17151d;color:#fff;}
.light-action{background:#f1f0f2;color:#17151d;}
.wallets{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;background:#f6f5f6;border-radius:16px;padding:10px;}
.wallet{background:#fff;border-radius:13px;padding:10px;min-width:0;}
.wallet span,.wallet small{display:block;color:#827b88;font-size:.68rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.wallet strong{display:block;font-size:.82rem;margin:4px 0;color:#17151d;}
.metric-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.metric{padding:18px;min-height:116px;}
.metric.hot{background:linear-gradient(145deg,var(--pos-violet),#6b1397);color:#fff;}
.metric.danger{border-color:#f3c9c9;background:#fff7f7;}
.metric span{display:block;font-size:.82rem;color:inherit;opacity:.78;margin-bottom:22px;}
.metric strong{font-size:1.55rem;display:block;}
.metric small{font-size:.72rem;opacity:.72;display:inline-flex;align-items:center;gap:4px;}
.chart-panel{padding:16px;}
.panel-title-row{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px;}
.panel-title-row h2,.limit-panel h2{font-size:1rem;font-weight:800;margin:0;}
.panel-title-row p{font-size:.78rem;color:#746d7a;margin:3px 0 0;}
.chart-card{background:#f6f5f6;border-radius:14px;padding:12px;}
.chart-legend{display:flex;align-items:center;gap:14px;font-size:.7rem;margin-bottom:10px;}
.chart-legend strong{margin-right:auto;}
.dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:4px;}.dot.violet{background:var(--pos-violet);}.dot.ink{background:#17151d;}
.bar-chart{height:170px;display:flex;align-items:end;justify-content:space-between;gap:8px;}
.bar-col{height:100%;display:flex;flex-direction:column;align-items:center;justify-content:end;gap:7px;flex:1;}
.bar-stack{display:flex;align-items:end;gap:3px;}
.bar{width:13px;border-radius:8px 8px 3px 3px;display:block;}.bar.profit{background:repeating-linear-gradient(135deg,#6b1397 0,#6b1397 3px,#7d23aa 3px,#7d23aa 6px);}.bar.loss{background:#17151d;}
.bar-col small{font-size:.68rem;color:#817a87;}
.pnl-panel{grid-column:1 / 4;padding:16px;}
.pnl-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:10px;}
.pnl-card{background:#f7f6f8;border:1px solid #ece8ef;border-radius:14px;padding:13px;min-height:112px;}
.pnl-card span{display:block;color:#716979;font-size:.72rem;text-transform:uppercase;font-weight:800;margin-bottom:11px;}
.pnl-card strong{display:block;color:#17151d;font-size:1.12rem;margin-bottom:8px;white-space:nowrap;}
.pnl-card small{display:block;color:#7c7482;font-size:.7rem;line-height:1.25;}
.pnl-card.strong{background:#17151d;border-color:#17151d;}
.pnl-card.strong span,.pnl-card.strong strong,.pnl-card.strong small{color:#fff;}
.pnl-card.profit{background:#f5ecff;border-color:#ddc3ef;}
.pnl-card.profit strong{color:var(--pos-violet);}
.pnl-card.loss{background:#fff5f5;border-color:#f1c7c7;}
.pnl-card.loss strong{color:#b42318;}
.pnl-card.final{box-shadow:inset 0 0 0 1px rgba(75,0,110,.14);}
.limit-panel{grid-column:1 / 2;padding:16px;}
.limit-track{height:10px;border-radius:999px;background:repeating-linear-gradient(135deg,#eeecef 0,#eeecef 5px,#f8f7f8 5px,#f8f7f8 10px);overflow:hidden;margin:28px 0 10px;}
.limit-track span{display:block;height:100%;background:var(--pos-violet);border-radius:999px;}
.limit-row{display:flex;justify-content:space-between;font-size:.72rem;color:#746d7a;}
.card-panel{grid-column:1 / 2;padding:16px;}
.panel-title-row a{color:#17151d;text-decoration:none;background:#f3f2f4;border-radius:13px;padding:7px 10px;font-size:.75rem;}
.card-strip{display:flex;gap:10px;overflow:hidden;}
.pay-card{min-width:190px;height:122px;border-radius:15px;color:#fff;padding:16px;display:flex;flex-direction:column;justify-content:space-between;}
.pay-card.dark{background:linear-gradient(135deg,#17151d,#2a2631);}.pay-card.violet{background:linear-gradient(135deg,var(--pos-violet),#7d23aa);}
.pay-card span{align-self:flex-start;background:#fff;color:#17151d;border-radius:999px;padding:4px 10px;font-size:.68rem;}
.pay-card strong{font-size:.9rem;}.pay-card small{font-size:.72rem;opacity:.78;}
.activity-panel{grid-column:2 / 4;padding:16px;min-height:276px;}
.activity-tools{display:flex;gap:8px;color:#17151d;font-size:.75rem;}
.activity-tools span{border:1px solid #eee;border-radius:14px;padding:8px 14px;background:#fff;display:inline-flex;align-items:center;gap:6px;}
.activity-table{border:1px solid #eeecef;border-radius:15px;overflow:hidden;}
.activity-head,.activity-row{display:grid;grid-template-columns:28px 92px minmax(150px,1fr) 90px 98px 142px 34px;gap:12px;align-items:center;padding:10px 12px;font-size:.76rem;}
.activity-head{background:#f7f6f7;color:#7d7582;}
.activity-row{border-top:1px solid #eeecef;color:#2b2731;}
.check{width:15px;height:15px;border:1px solid #ddd;border-radius:4px;display:flex;align-items:center;justify-content:center;color:transparent;}
.check.on{background:#17151d;color:#fff;border-color:#17151d;}
.mono{font-variant-numeric:tabular-nums;}
.act-icon{width:20px;height:20px;border-radius:50%;display:inline-flex!important;align-items:center;justify-content:center;background:var(--pos-violet-light);color:var(--pos-violet);margin-right:8px;vertical-align:middle;}
.status{display:flex;align-items:center;gap:5px;}.status i{width:6px;height:6px;border-radius:50%;background:#36a67c;display:inline-block;}.status.pending i{background:#d8bd2d;}.dots{color:#8c8491;letter-spacing:2px;}
.empty-state{text-align:center;color:#746d7a;padding:40px 0;}
@media (max-width:1180px){.fin-grid{grid-template-columns:1fr 1fr;}.chart-panel,.activity-panel,.pnl-panel{grid-column:1 / -1;}.pnl-grid{grid-template-columns:repeat(3,minmax(0,1fr));}.activity-panel{grid-row:auto;}.limit-panel,.card-panel{grid-column:auto;}}
@media (max-width:760px){.t-main{padding:0!important;}.fin-shell{grid-template-columns:1fr;}.fin-rail{display:none;}.fin-board{padding:12px;}.fin-head{height:auto;align-items:flex-start;}.fin-tabs{overflow:auto;max-width:100%;}.head-actions{display:none;}.fin-grid,.metric-grid,.pnl-grid{grid-template-columns:1fr;}.limit-panel,.card-panel,.activity-panel,.chart-panel,.pnl-panel{grid-column:auto;}.activity-head{display:none;}.activity-row{grid-template-columns:22px 1fr;}.activity-row span:nth-child(n+4){display:none;}.wallets{grid-template-columns:1fr;}.pnl-card strong{white-space:normal;}}
</style>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
