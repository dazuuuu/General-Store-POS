<?php
// public/staff/orders/receipt.php?id=N — printable tab receipt (unpaid or paid)
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();

$pdo = Database::pdo();
$O = new Models\OrderModel($pdo);

$id = (int) ($_GET['id'] ?? 0);
$order = $id > 0 ? $O->find($id) : null;
if (!$order) {
    http_response_code(404);
    echo 'Receipt not found.';
    exit;
}
$items = $O->items($id);
$isWalkin = ($order['channel'] ?? 'tab') === 'walkin';
$tenant   = (new Models\TenantModel($pdo))->find(TenantContext::tenantId());
$shop     = $tenant['name'] ?? 'My Shop';
$logo     = Branding::tenantLogo($tenant);
$currency = $tenant['currency'] ?? 'KES';

$nameOf = function (?int $userId): string {
    if (!$userId) { return ''; }
    global $pdo;
    $s = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $s->execute([$userId]);
    return (string) ($s->fetchColumn() ?: '');
};
$openedBy = $nameOf((int) $order['opened_by']);
$paidBy   = $order['paid_by'] ? $nameOf((int) $order['paid_by']) : '';

function money($n) { global $currency; return $currency . ' ' . number_format((float) $n, 2); }

// Reached from both the staff till and (potentially) the owner's views —
// send each viewer back into their own section, not always into staff.
$isStaffViewer = TenantContext::role() === 'staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt <?php echo htmlspecialchars($order['receipt_number']); ?> — <?php echo htmlspecialchars($shop); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
  body{background:#f1f5f9;margin:0;padding:24px;font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;}
  .sheet{background:#fff;max-width:380px;margin:0 auto 18px;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.1);padding:24px;}
  .actions{max-width:380px;margin:0 auto;}
  @media print { body{background:#fff;padding:0;} .actions,.noprint{display:none !important;} .sheet{box-shadow:none;border-radius:0;margin:0;} }
</style>
</head>
<body>
  <div class="sheet" style="font-size:13px;color:#0f172a;">
    <div style="text-align:center;border-bottom:2px dashed #cbd5e1;padding-bottom:10px;margin-bottom:10px;">
      <?php if ($logo): ?><img src="<?php echo htmlspecialchars($logo); ?>" alt="" style="max-height:44px;max-width:160px;object-fit:contain;margin-bottom:6px;"><?php endif; ?>
      <div style="font-size:16px;font-weight:700;"><?php echo htmlspecialchars($shop); ?></div>
      <?php if (!empty($tenant['phone'])): ?><div style="font-size:12px;color:#475569;">TEL: <?php echo htmlspecialchars($tenant['phone']); ?></div><?php endif; ?>
      <?php if (!empty($tenant['address'])): ?><div style="font-size:12px;color:#475569;"><?php echo htmlspecialchars($tenant['address']); ?></div><?php endif; ?>
      <?php if (!empty($tenant['kra_pin'])): ?><div style="font-size:12px;color:#475569;">PIN: <?php echo htmlspecialchars($tenant['kra_pin']); ?></div><?php endif; ?>
      <div style="font-size:12px;margin-top:4px;"><?php echo $isWalkin ? 'Receipt' : 'Invoice'; ?> <?php echo htmlspecialchars($order['receipt_number']); ?></div>
      <div style="font-size:12px;">DATE: <?php echo htmlspecialchars(date('j M Y, g:i a', strtotime($order['created_at']))); ?></div>
      <?php if (!$isWalkin || $order['table_name'] !== 'Walk-in Customer'): ?>
      <div style="font-size:12px;"><?php echo $isWalkin ? 'Customer' : 'Customer'; ?>: <strong><?php echo htmlspecialchars($order['table_name']); ?></strong></div>
      <?php endif; ?>
      <div style="font-size:12px;"><?php echo $isWalkin ? 'Served by' : 'Opened by'; ?>: <?php echo htmlspecialchars($openedBy ?: '—'); ?></div>
    </div>

    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <tr style="border-bottom:1px solid #cbd5e1;">
        <th style="text-align:left;padding:0 4px 4px 0;font-size:12px;">ITEM</th>
        <th style="text-align:center;padding:0 4px 4px;font-size:12px;">QTY</th>
        <th style="text-align:right;padding:0 0 4px 4px;font-size:12px;">AMT</th>
      </tr>
      <?php foreach ($items as $it): ?>
      <tr>
        <td style="padding:4px 4px 4px 0;"><?php echo htmlspecialchars($it['product_name']); ?></td>
        <td style="padding:4px;text-align:center;"><?php echo rtrim(rtrim(number_format((float) $it['quantity'], 2), '0'), '.'); ?></td>
        <td style="padding:4px 0 4px 4px;text-align:right;"><?php echo number_format((float) $it['line_total'], 2); ?></td>
      </tr>
      <?php endforeach; ?>
    </table>

    <table style="width:100%;border-collapse:collapse;font-size:14px;border-top:2px dashed #cbd5e1;margin-top:8px;padding-top:8px;">
      <?php if ((float) ($order['discount_amount'] ?? 0) > 0): ?>
        <tr><td style="color:#64748b;">Subtotal</td><td style="text-align:right;"><?php echo money($order['subtotal']); ?></td></tr>
        <tr><td style="color:#64748b;">Discount</td><td style="text-align:right;">− <?php echo money($order['discount_amount']); ?></td></tr>
      <?php endif; ?>
      <tr><td style="font-weight:700;padding-top:8px;">Total</td><td style="text-align:right;font-weight:700;padding-top:8px;"><?php echo money($order['total']); ?></td></tr>
      <?php if ($order['status'] === 'paid'): ?>
        <tr><td style="color:#64748b;">Paid via</td><td style="text-align:right;"><?php echo htmlspecialchars(ucfirst($order['payment_method'] ?? '')); ?></td></tr>
        <?php if ($order['amount_tendered'] !== null): ?>
        <tr><td style="color:#64748b;">Cash given</td><td style="text-align:right;"><?php echo money($order['amount_tendered']); ?></td></tr>
        <tr><td style="color:#64748b;font-weight:700;">Balance</td><td style="text-align:right;font-weight:700;"><?php echo money($order['change_due']); ?></td></tr>
        <?php endif; ?>
        <tr><td style="color:#64748b;">Paid to</td><td style="text-align:right;"><?php echo htmlspecialchars($paidBy ?: '—'); ?></td></tr>
        <tr><td style="color:#64748b;">Paid at</td><td style="text-align:right;"><?php echo htmlspecialchars(date('j M Y, g:i a', strtotime($order['paid_at']))); ?></td></tr>
      <?php elseif ($order['status'] === 'open'): ?>
        <tr><td colspan="2" style="padding-top:8px;color:#b45309;font-size:12px;">
          <?php echo $isWalkin ? 'Payment wasn\'t completed — finish it from Payments.' : 'Pay at reception or with your server.'; ?>
        </td></tr>
      <?php endif; ?>
    </table>

    <p style="text-align:center;font-size:12px;color:#94a3b8;margin-top:14px;"><?php echo htmlspecialchars($tenant['receipt_footer'] ?? '') ?: 'Thank you for your business.'; ?></p>
  </div>

  <div class="actions">
    <div class="d-flex gap-2 mb-2">
      <button onclick="window.print()" class="btn btn-primary flex-fill"><i class="fas fa-print me-1"></i> Print</button>
      <?php if ($isStaffViewer && !$isWalkin && $order['status'] === 'open'): ?>
        <a href="<?php echo public_url('staff/orders/view.php?id=' . $id); ?>" class="btn btn-outline-secondary flex-fill">Back to tab</a>
      <?php endif; ?>
    </div>
    <?php if (!$isStaffViewer): ?>
    <div class="d-flex gap-2">
      <a href="<?php echo public_url('super/sales/'); ?>" class="btn btn-link flex-fill">Sales</a>
      <a href="<?php echo public_url('super/dashboard/'); ?>" class="btn btn-link flex-fill">Dashboard</a>
    </div>
    <?php elseif ($isWalkin): ?>
    <div class="d-flex gap-2">
      <a href="<?php echo public_url('staff/sales/'); ?>" class="btn btn-link flex-fill">Sales history</a>
      <a href="<?php echo public_url('staff/dashboard/'); ?>" class="btn btn-link flex-fill">New sale</a>
    </div>
    <?php else: ?>
    <div class="d-flex gap-2">
      <a href="<?php echo public_url('staff/orders/'); ?>" class="btn btn-link flex-fill">All tabs</a>
      <a href="<?php echo public_url('staff/orders/new.php'); ?>" class="btn btn-link flex-fill">New order</a>
    </div>
    <?php endif; ?>
  </div>
</body>
</html>
