<?php
// public/clean_migrations.php
// Admin cleanup/repair tool for stale credit invoices/orders left open after payment.
require_once __DIR__ . '/../app/app.php';
PageGuard::tenant();

$pdo = Database::pdo();
$tenantId = (int) TenantContext::tenantId();
$userId = (int) TenantContext::userId();
$O = new Models\OrderModel($pdo);
$N = new Models\NotificationModel($pdo);
$message = '';
$error = '';

function clean_money(float $n): string
{
    return 'KES ' . number_format($n, 2);
}

function clean_fetch_orders(PDO $pdo, int $tenantId): array
{
    $stmt = $pdo->prepare(
        "SELECT id, receipt_number, table_name, status, payment_status, total,
                COALESCE(amount_paid,0) AS amount_paid,
                COALESCE(amount_due,0) AS amount_due,
                GREATEST(COALESCE(total,0) - COALESCE(amount_paid,0), 0) AS calculated_due,
                created_at
           FROM orders
          WHERE tenant_id = ?
            AND status = 'open'
          ORDER BY created_at DESC, id DESC
          LIMIT 200"
    );
    $stmt->execute([$tenantId]);
    return $stmt->fetchAll();
}

function clean_fetch_sales(PDO $pdo, int $tenantId): array
{
    try {
        $stmt = $pdo->prepare(
            "SELECT id, receipt_number, customer_name, status, payment_status, total,
                    COALESCE(amount_paid,0) AS amount_paid,
                    COALESCE(amount_due,0) AS amount_due,
                    GREATEST(COALESCE(total,0) - COALESCE(amount_paid,0), 0) AS calculated_due,
                    created_at
               FROM sales
              WHERE tenant_id = ?
                AND COALESCE(payment_status,'') <> 'paid'
                AND status NOT IN ('voided','deleted')
              ORDER BY created_at DESC, id DESC
              LIMIT 200"
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'repair_paid') {
        try {
            $pdo->beginTransaction();
            $repairedOrderIds = [];
            $ids = $pdo->prepare(
                "SELECT id
                   FROM orders
                  WHERE tenant_id = ?
                    AND status = 'open'
                    AND GREATEST(COALESCE(total,0) - COALESCE(amount_paid,0), 0) <= 0.0001"
            );
            $ids->execute([$tenantId]);
            $repairedOrderIds = array_map('intval', array_column($ids->fetchAll(), 'id'));

            $orders = $pdo->prepare(
                "UPDATE orders
                    SET status = 'paid',
                        payment_status = 'paid',
                        amount_due = 0,
                        paid_by = COALESCE(paid_by, ?),
                        paid_at = COALESCE(paid_at, NOW())
                  WHERE tenant_id = ?
                    AND status = 'open'
                    AND GREATEST(COALESCE(total,0) - COALESCE(amount_paid,0), 0) <= 0.0001"
            );
            $orders->execute([$userId, $tenantId]);
            $orderCount = $orders->rowCount();

            $saleCount = 0;
            try {
                $sales = $pdo->prepare(
                    "UPDATE sales
                        SET payment_status = 'paid',
                            amount_due = 0
                      WHERE tenant_id = ?
                        AND status NOT IN ('voided','deleted')
                        AND COALESCE(payment_status,'') <> 'paid'
                        AND GREATEST(COALESCE(total,0) - COALESCE(amount_paid,0), 0) <= 0.0001"
                );
                $sales->execute([$tenantId]);
                $saleCount = $sales->rowCount();
            } catch (Throwable $ignored) {}

            $pdo->commit();
            $alertCount = 0;
            foreach ($repairedOrderIds as $oid) {
                $alertCount += $N->clearCreditSaleAlerts($oid);
            }
            $message = "Repaired {$orderCount} paid credit order(s), {$saleCount} paid sale record(s), and cleared {$alertCount} matching alert(s).";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = 'Cleanup failed: ' . $e->getMessage();
        }
    } elseif ($action === 'void_order') {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $res = $O->void($orderId, $userId);
        if ($res['ok']) {
            $cleared = $N->clearCreditSaleAlerts($orderId);
            $message = 'Credit invoice/order voided, stock restored, and ' . $cleared . ' matching alert(s) cleared.';
        } else {
            $error = $res['error'] ?? 'Could not void that order.';
        }
    } elseif ($action === 'clear_credit_alerts') {
        $cleared = $N->clearCreditSaleAlerts();
        $message = "Cleared {$cleared} credit-sale alert(s).";
    }
}

$orders = clean_fetch_orders($pdo, $tenantId);
$sales = clean_fetch_sales($pdo, $tenantId);
$creditAlerts = $N->creditSaleAlerts(100);
$staleOrders = array_values(array_filter($orders, fn($r) => (float) $r['calculated_due'] <= 0.0001));
$trueOpenOrders = array_values(array_filter($orders, fn($r) => (float) $r['calculated_due'] > 0.0001));
$staleSales = array_values(array_filter($sales, fn($r) => (float) $r['calculated_due'] <= 0.0001));
$openSales = array_values(array_filter($sales, fn($r) => (float) $r['calculated_due'] > 0.0001));

$page_title = 'Clean credit records';
ob_start();
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <div>
    <h1 class="h5 fw-bold mb-1">Clean credit records</h1>
    <p class="text-muted small mb-0">Repair paid invoices/orders that are still marked pending, or void unwanted credit orders and return stock.</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <form method="post" onsubmit="return confirm('Clear all credit-sale banner alerts? This only hides alerts, not invoices.');">
      <input type="hidden" name="action" value="clear_credit_alerts">
      <button class="btn btn-sm btn-outline-warning">Clear banner alerts</button>
    </form>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url('super/dashboard/'); ?>">Dashboard</a>
  </div>
</div>

<?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <div class="card-body p-4">
    <h2 class="h6 fw-bold mb-2">Quick repair</h2>
    <p class="text-muted small mb-3">Marks zero-balance open credit invoices as paid, so they stop showing as pending.</p>
    <form method="post" onsubmit="return confirm('Repair zero-balance paid records now?');">
      <input type="hidden" name="action" value="repair_paid">
      <button class="btn btn-primary">Repair paid records</button>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;overflow:hidden;">
  <div class="px-4 py-3 border-bottom bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h2 class="h6 fw-bold mb-0">Credit-sale banner alerts</h2>
      <div class="text-muted small">These are the yellow banners at the top of the super page.</div>
    </div>
    <form method="post" onsubmit="return confirm('Clear all credit-sale banner alerts?');">
      <input type="hidden" name="action" value="clear_credit_alerts">
      <button class="btn btn-sm btn-outline-warning">Clear all alerts</button>
    </form>
  </div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Alert</th><th>When</th><th>Link</th></tr></thead>
      <tbody>
        <?php if (!$creditAlerts): ?><tr><td colspan="3" class="text-center text-muted py-4">No credit-sale banner alerts found.</td></tr><?php endif; ?>
        <?php foreach ($creditAlerts as $n): ?>
          <tr>
            <td><div class="fw-semibold"><?php echo htmlspecialchars($n['title']); ?></div><div class="small text-muted"><?php echo htmlspecialchars($n['message']); ?></div></td>
            <td class="small text-muted"><?php echo date('j M Y, g:i a', strtotime($n['created_at'])); ?></td>
            <td><?php if (!empty($n['url'])): ?><a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url($n['url']); ?>">Open</a><?php else: ?>—<?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="row g-4">
  <div class="col-12 col-xl-6">
    <div class="card border-0 shadow-sm h-100" style="border-radius:14px;overflow:hidden;">
      <div class="px-4 py-3 border-bottom bg-white">
        <h2 class="h6 fw-bold mb-0">Stale paid credit invoices</h2>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Invoice</th><th>Customer</th><th class="text-end">Paid</th><th class="text-end">Due</th></tr></thead>
          <tbody>
            <?php if (!$staleOrders): ?><tr><td colspan="4" class="text-center text-muted py-4">No stale paid credit invoices found.</td></tr><?php endif; ?>
            <?php foreach ($staleOrders as $r): ?>
              <tr>
                <td><div class="fw-semibold"><?php echo htmlspecialchars($r['receipt_number']); ?></div><div class="text-muted small"><?php echo date('j M Y', strtotime($r['created_at'])); ?></div></td>
                <td><?php echo htmlspecialchars($r['table_name'] ?: '—'); ?></td>
                <td class="text-end"><?php echo clean_money((float) $r['amount_paid']); ?></td>
                <td class="text-end text-success fw-semibold"><?php echo clean_money((float) $r['calculated_due']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-6">
    <div class="card border-0 shadow-sm h-100" style="border-radius:14px;overflow:hidden;">
      <div class="px-4 py-3 border-bottom bg-white">
        <h2 class="h6 fw-bold mb-0">Still unpaid credit invoices</h2>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Invoice</th><th>Customer</th><th class="text-end">Due</th><th></th></tr></thead>
          <tbody>
            <?php if (!$trueOpenOrders): ?><tr><td colspan="4" class="text-center text-muted py-4">No unpaid credit invoices found.</td></tr><?php endif; ?>
            <?php foreach ($trueOpenOrders as $r): ?>
              <tr>
                <td><div class="fw-semibold"><?php echo htmlspecialchars($r['receipt_number']); ?></div><div class="text-muted small"><?php echo date('j M Y', strtotime($r['created_at'])); ?></div></td>
                <td><?php echo htmlspecialchars($r['table_name'] ?: '—'); ?></td>
                <td class="text-end text-danger fw-semibold"><?php echo clean_money((float) $r['calculated_due']); ?></td>
                <td class="text-end clean-actions">
                  <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url('super/orders/view.php?id=' . (int) $r['id']); ?>">Open</a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Void this credit invoice/order and restore its products to stock?');">
                    <input type="hidden" name="action" value="void_order">
                    <input type="hidden" name="order_id" value="<?php echo (int) $r['id']; ?>">
                    <button class="btn btn-sm btn-outline-danger">Void</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php if ($staleSales || $openSales): ?>
<div class="card border-0 shadow-sm mt-4" style="border-radius:14px;overflow:hidden;">
  <div class="px-4 py-3 border-bottom bg-white"><h2 class="h6 fw-bold mb-0">Legacy direct credit sales</h2></div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Receipt</th><th>Customer</th><th>Status</th><th class="text-end">Paid</th><th class="text-end">Due</th></tr></thead>
      <tbody>
        <?php foreach (array_merge($staleSales, $openSales) as $r): ?>
          <tr>
            <td><?php echo htmlspecialchars($r['receipt_number']); ?></td>
            <td><?php echo htmlspecialchars($r['customer_name'] ?: '—'); ?></td>
            <td><?php echo (float) $r['calculated_due'] <= 0.0001 ? '<span class="badge bg-success">Ready to repair</span>' : '<span class="badge bg-warning text-dark">Still unpaid</span>'; ?></td>
            <td class="text-end"><?php echo clean_money((float) $r['amount_paid']); ?></td>
            <td class="text-end"><?php echo clean_money((float) $r['calculated_due']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<style>
.clean-actions{white-space:nowrap;}
.clean-actions .btn{margin:.1rem;}
@media (max-width: 576px){
  .card-body{padding:1rem!important;}
  .clean-actions{display:grid;grid-template-columns:1fr;gap:.35rem;white-space:normal;}
  .clean-actions .btn,.clean-actions form,.clean-actions button{width:100%;margin:0;}
}
</style>
<?php
$content = ob_get_clean();
include __DIR__ . '/templates/tenants/layout.php';
