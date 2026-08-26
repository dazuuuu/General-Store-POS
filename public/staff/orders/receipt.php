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
$payments = $O->payments($id);
$isWalkin = ($order['channel'] ?? 'tab') === 'walkin';
$tenant   = (new Models\TenantModel($pdo))->find(TenantContext::tenantId());
$shop     = $tenant['name'] ?? ReceiptFooter::SHOP_NAME;
$logo     = Branding::loginLogo();
$watermarkLogo = Branding::loginLogo();
$currency = $tenant['currency'] ?? 'KES';
$poBox    = trim((string) ($tenant['po_box'] ?? ReceiptFooter::SHOP_BOX));
$location = trim((string) ($tenant['address'] ?? ReceiptFooter::SHOP_LOCATION));
$email    = trim((string) ($tenant['business_email'] ?? ReceiptFooter::SHOP_EMAIL));
$phone    = trim((string) ($tenant['phone'] ?? ReceiptFooter::SHOP_PHONE));
$paymentCredentials = trim((string) ($tenant['payment_credentials'] ?? ''));
$identityBlock = trim(implode("\n", array_filter([$shop, $poBox, $location])));
$showPaymentCredentials = $paymentCredentials !== ''
    && preg_replace('/\s+/', '', strtolower($paymentCredentials)) !== preg_replace('/\s+/', '', strtolower($identityBlock));

$nameOf = function (?int $userId): string {
    if (!$userId) { return ''; }
    global $pdo;
    $s = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $s->execute([$userId]);
    return (string) ($s->fetchColumn() ?: '');
};
$openedBy = $nameOf((int) $order['opened_by']);
$customerName = trim((string) ($order['table_name'] ?? ''));
$amountPaid = max(0, (float) ($order['amount_paid'] ?? 0));
$amountDue = (float) ($order['amount_due'] ?? 0);
if ($order['status'] === 'open' && $amountDue <= 0.0001) {
    $amountDue = max(0, (float) $order['total'] - $amountPaid);
}

function money($n) { global $currency; return $currency . ' ' . number_format((float) $n, 2); }

// Reached from both the staff till and (potentially) the owner's views —
// send each viewer back into their own section, not always into staff.
$isStaffViewer = TenantContext::role() === 'staff';
$autoPrint = ($_GET['print'] ?? '') === '1';
$isDepositReceipt = ($_GET['deposit'] ?? '') === '1';
$returnToShop = ($_GET['return'] ?? '') === 'shop';
$shopReturnUrl = $isStaffViewer ? public_url('staff/dashboard/') : public_url('super/shop/');
$latestPayment = $payments ? $payments[count($payments) - 1] : null;
if ($isDepositReceipt && $latestPayment) {
    $depositJustPaid = (float) ($latestPayment['amount'] ?? 0);
} else {
    $depositJustPaid = 0.0;
}
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
  .sheet{background:#fff;max-width:380px;margin:0 auto 18px;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.1);padding:24px;font-weight:700;position:relative;overflow:hidden;}
  .sheet::before{content:"";position:absolute;inset:42px 18px;background:url('<?php echo htmlspecialchars($watermarkLogo, ENT_QUOTES); ?>') center 48%/82% auto no-repeat;opacity:.38;pointer-events:none;}
  .sheet > *{position:relative;z-index:1;}
  .sheet, .sheet *{font-weight:900 !important;color:#000 !important;}
  .sheet table, .sheet th, .sheet td{font-weight:900 !important;color:#000 !important;}
  .actions{max-width:380px;margin:0 auto;}
  @page{margin:8mm;}
  @media print {
    body{background:#fff;padding:0;margin:0;}
    .actions,.noprint{display:none !important;}
    .sheet{box-shadow:none;border-radius:0;margin:0 auto !important;width:80mm;max-width:80mm;padding:10px 12px;font-size:15px !important;}
  }
</style>
</head>
<body>
  <div class="sheet" style="font-size:16px;color:#000;">
    <div style="text-align:center;border-bottom:2px dashed #cbd5e1;padding-bottom:10px;margin-bottom:10px;">
      <?php if ($logo): ?><img src="<?php echo htmlspecialchars($logo); ?>" alt="" style="max-height:104px;max-width:285px;object-fit:contain;margin-bottom:8px;"><?php endif; ?>
      <div style="font-size:24px;font-weight:900;"><?php echo htmlspecialchars($shop); ?></div>
      <?php if ($poBox !== ''): ?><div style="font-size:15px;color:#000;"><?php echo htmlspecialchars($poBox); ?></div><?php endif; ?>
      <?php if ($location !== ''): ?><div style="font-size:15px;color:#000;"><?php echo htmlspecialchars($location); ?></div><?php endif; ?>
      <?php if ($phone !== ''): ?><div style="font-size:16px;color:#000;">TEL: <?php echo htmlspecialchars($phone); ?></div><?php endif; ?>
      <?php if (!empty($tenant['kra_pin'])): ?><div style="font-size:15px;color:#000;">PIN: <?php echo htmlspecialchars($tenant['kra_pin']); ?></div><?php endif; ?>
      <div style="font-size:16px;margin-top:5px;"><?php
        if ($isDepositReceipt && $order['status'] === 'open') {
            echo 'Deposit receipt';
        } else {
            echo $isWalkin ? 'Invoice / Receipt' : 'Invoice';
        }
      ?> <?php echo htmlspecialchars($order['receipt_number']); ?></div>
      <div style="font-size:15px;"><?php echo htmlspecialchars(date('j M Y, g:i a', strtotime($order['created_at']))); ?></div>
      <?php if ($customerName !== ''): ?>
      <div style="font-size:15px;">Customer: <strong><?php echo htmlspecialchars($customerName); ?></strong></div>
      <?php endif; ?>
      <?php if (!$isWalkin && (!empty($order['credit_due_at']) || !empty($order['credit_duration_days']))): ?>
      <div style="font-size:15px;">
        Credit:
        <?php if (!empty($order['credit_due_at'])): ?>
          due <strong><?php echo htmlspecialchars(date('j M Y', strtotime($order['credit_due_at']))); ?></strong>
          <?php if (!empty($order['credit_duration_days'])): ?>
            (<?php echo (int) $order['credit_duration_days']; ?> day<?php echo (int) $order['credit_duration_days'] === 1 ? '' : 's'; ?>)
          <?php endif; ?>
        <?php else: ?>
          <strong><?php echo (int) $order['credit_duration_days']; ?> day<?php echo (int) $order['credit_duration_days'] === 1 ? '' : 's'; ?></strong>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <div style="font-size:15px;"><?php echo $isWalkin ? 'Served by' : 'Opened by'; ?>: <?php echo htmlspecialchars($openedBy ?: '—'); ?></div>
    </div>

    <table style="width:100%;border-collapse:collapse;font-size:16px;">
      <tr style="border-bottom:1px solid #cbd5e1;">
        <th style="text-align:left;padding:0 4px 5px 0;font-size:16px;font-weight:900;">NO.</th>
        <th style="text-align:left;padding:0 4px 5px 0;font-size:16px;font-weight:900;">ITEM</th>
        <th style="text-align:center;padding:0 4px 5px;font-size:16px;font-weight:900;">QTY</th>
        <th style="text-align:right;padding:0 0 5px 4px;font-size:16px;font-weight:900;">AMT</th>
      </tr>
      <?php foreach ($items as $idx => $it): ?>
      <tr>
        <td style="padding:4px 4px 4px 0;vertical-align:top;"><?php echo (int) $idx + 1; ?></td>
        <td style="padding:4px 4px 4px 0;"><?php echo htmlspecialchars($it['product_name']); ?></td>
        <td style="padding:4px;text-align:center;"><?php
          $qty = (float) $it['quantity'];
          echo htmlspecialchars(QtyFormat::display($qty));
          $halfNote = QtyFormat::halfNote($qty);
          if ($halfNote !== '') {
              echo '<div style="font-size:12px;color:#64748b;">' . htmlspecialchars($halfNote) . '</div>';
          }
        ?></td>
        <td style="padding:4px 0 4px 4px;text-align:right;"><?php echo number_format((float) $it['line_total'], 2); ?></td>
      </tr>
      <?php endforeach; ?>
    </table>

    <table style="width:100%;border-collapse:collapse;font-size:17px;border-top:2px dashed #000;margin-top:8px;padding-top:8px;">
      <?php if ((float) ($order['discount_amount'] ?? 0) > 0 || (float) ($order['vat_amount'] ?? 0) > 0 || (float) ($order['additional_charges'] ?? 0) > 0): ?>
        <tr><td style="color:#64748b;">Subtotal</td><td style="text-align:right;"><?php echo money($order['subtotal']); ?></td></tr>
        <?php if ((float) ($order['discount_amount'] ?? 0) > 0): ?>
        <tr><td style="color:#64748b;">Discount</td><td style="text-align:right;">− <?php echo money($order['discount_amount']); ?></td></tr>
        <?php endif; ?>
        <?php if ((float) ($order['vat_amount'] ?? 0) > 0): ?>
        <tr><td style="color:#64748b;">VAT (<?php echo number_format((float) ($order['vat_rate'] ?? 0), 2); ?>%)</td><td style="text-align:right;"><?php echo money($order['vat_amount']); ?></td></tr>
        <?php endif; ?>
        <?php if ((float) ($order['additional_charges'] ?? 0) > 0): ?>
        <tr>
          <td style="color:#64748b;">Extra charge<?php
            $chargeNote = trim((string) ($order['additional_charges_note'] ?? ''));
            if ($chargeNote !== '') echo ' (' . htmlspecialchars($chargeNote) . ')';
          ?></td>
          <td style="text-align:right;"><?php echo money($order['additional_charges']); ?></td>
        </tr>
        <?php endif; ?>
      <?php endif; ?>
      <tr><td style="font-weight:900;padding-top:8px;font-size:19px;">Total</td><td style="text-align:right;font-weight:900;padding-top:8px;font-size:19px;"><?php echo money($order['total']); ?></td></tr>
      <?php if ($amountPaid > 0): ?>
        <?php if ($isDepositReceipt && $depositJustPaid > 0): ?>
        <tr><td style="color:#64748b;font-weight:700;">Deposit received</td><td style="text-align:right;font-weight:700;"><?php echo money($depositJustPaid); ?></td></tr>
        <?php endif; ?>
        <tr><td style="color:#64748b;">Paid so far</td><td style="text-align:right;"><?php echo money($amountPaid); ?></td></tr>
        <tr><td style="color:#64748b;font-weight:700;">Still owed</td><td style="text-align:right;font-weight:700;"><?php echo money($order['status'] === 'paid' ? 0 : $amountDue); ?></td></tr>
      <?php endif; ?>
      <?php if ($showPaymentCredentials): ?>
        <tr><td style="color:#64748b;vertical-align:top;">Payment mode</td><td style="text-align:right;white-space:pre-line;"><?php echo htmlspecialchars($paymentCredentials); ?></td></tr>
      <?php endif; ?>
      <?php if ($order['status'] === 'paid'): ?>
        <tr><td style="color:#64748b;">Paid via</td><td style="text-align:right;"><?php echo htmlspecialchars(PaymentOptions::label($order)); ?></td></tr>
        <?php if (!empty($order['payment_account_name'])): ?>
        <tr><td style="color:#64748b;">Account name</td><td style="text-align:right;"><?php echo htmlspecialchars($order['payment_account_name']); ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($order['payment_reference'])): ?>
        <tr><td style="color:#64748b;">Reference</td><td style="text-align:right;"><?php echo htmlspecialchars($order['payment_reference']); ?></td></tr>
        <?php endif; ?>
        <?php if ($order['amount_tendered'] !== null): ?>
        <tr><td style="color:#64748b;">Cash given</td><td style="text-align:right;"><?php echo money($order['amount_tendered']); ?></td></tr>
        <tr><td style="color:#64748b;font-weight:700;">Balance</td><td style="text-align:right;font-weight:700;"><?php echo money($order['change_due']); ?></td></tr>
        <?php endif; ?>
        <tr><td style="color:#64748b;">Paid at</td><td style="text-align:right;"><?php echo htmlspecialchars(date('j M Y, g:i a', strtotime($order['paid_at']))); ?></td></tr>
      <?php elseif ($order['status'] === 'open'): ?>
        <tr><td colspan="2" style="padding-top:8px;color:#b45309;font-size:12px;">
          <?php echo $amountPaid > 0 ? 'Partial payment received — balance remains on credit.' : ($isWalkin ? 'Payment wasn\'t completed — finish it from Payments.' : 'Pay at reception or with your server.'); ?>
        </td></tr>
      <?php endif; ?>
    </table>

    <?php if ($payments): ?>
      <div style="border-top:2px dashed #cbd5e1;margin-top:10px;padding-top:8px;">
        <div style="font-weight:700;font-size:12px;margin-bottom:4px;">PAYMENTS</div>
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
          <?php foreach ($payments as $pay): ?>
          <tr>
            <td style="padding:2px 4px 2px 0;color:#64748b;"><?php echo htmlspecialchars(date('j M, g:i a', strtotime($pay['created_at']))); ?></td>
            <td style="padding:2px 4px;text-align:center;"><?php echo htmlspecialchars(PaymentOptions::label(['payment_method' => $pay['method'], 'payment_provider' => $pay['provider'], 'payment_account_name' => $pay['account_name']])); ?></td>
            <td style="padding:2px 0 2px 4px;text-align:right;font-weight:700;"><?php echo money($pay['amount']); ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
    <?php endif; ?>

    <?php echo ReceiptFooter::html($tenant, $order['receipt_number'] ?? null); ?>
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
      <?php if ($returnToShop): ?><a href="<?php echo $shopReturnUrl; ?>" class="btn btn-link flex-fill">Shop</a><?php endif; ?>
      <a href="<?php echo public_url('super/sales/'); ?>" class="btn btn-link flex-fill">Sales</a>
      <a href="<?php echo public_url('super/dashboard/'); ?>" class="btn btn-link flex-fill">Dashboard</a>
    </div>
    <?php elseif ($isWalkin): ?>
    <div class="d-flex gap-2">
      <a href="<?php echo public_url('staff/sales/'); ?>" class="btn btn-link flex-fill">Sales history</a>
      <a href="<?php echo $shopReturnUrl; ?>" class="btn btn-link flex-fill">New sale</a>
    </div>
    <?php else: ?>
    <div class="d-flex gap-2">
      <a href="<?php echo public_url('staff/orders/'); ?>" class="btn btn-link flex-fill">Credit sales</a>
      <a href="<?php echo public_url('staff/orders/new.php'); ?>" class="btn btn-link flex-fill">New credit sale</a>
    </div>
    <?php endif; ?>
  </div>
  <?php if ($autoPrint): ?>
  <script>
    window.addEventListener('load', function () {
      setTimeout(function () {
        window.print();
      }, 250);
    });
  </script>
  <?php endif; ?>
</body>
</html>
