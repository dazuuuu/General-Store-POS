<?php
// public/staff/sales/receipt.php?id=N  — view / print / send a receipt
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth(Capabilities::SALES_VIEW);

$pdo = Database::pdo();
$SA  = new Models\SaleModel($pdo);

$id   = (int) ($_GET['id'] ?? 0);
$sale = $id > 0 ? $SA->find($id) : null;
if (!$sale) {
    http_response_code(404);
    echo 'Receipt not found.';
    exit;
}
$items = $SA->items($id);

/* Business header pulled live from Settings (super/settings/) — phones is a
 * list because receipt_header() joins multiple with " & "; only one number
 * is collected today. PIN is only shown when the owner has filled it in. */
$tenant = (new Models\TenantModel($pdo))->find(TenantContext::tenantId());
$shop   = $tenant['name'] ?? ReceiptFooter::SHOP_NAME;
$RECEIPT_BUSINESS = [
    'name'     => $shop,
    'logo'     => Branding::loginLogo(),
    'watermark_logo' => Branding::loginLogo(),
    'tagline'  => '',
    'phones'   => [trim((string) ($tenant['phone'] ?? ReceiptFooter::SHOP_PHONE))],
    'po_box'   => trim((string) ($tenant['po_box'] ?? ReceiptFooter::SHOP_BOX)),
    'location' => trim((string) ($tenant['address'] ?? ReceiptFooter::SHOP_LOCATION)),
    'email'    => trim((string) ($tenant['business_email'] ?? ReceiptFooter::SHOP_EMAIL)),
    'pin'      => $tenant['kra_pin'] ?? '',
    'payment_credentials' => $tenant['payment_credentials'] ?? '',
];

$st = $pdo->prepare('SELECT username FROM users WHERE id = ?');
$st->execute([$sale['staff_id']]);
$staff = (string) ($st->fetchColumn() ?: 'Staff');

function money($n) { return 'KES ' . number_format((float) $n, 2); }
function amt($n)   { return number_format((float) $n, 2); }

/* Centered business header block, matching the shared receipt layout:
   name, tagline, TEL (phones joined), P.O BOX, LOCATION, PIN,
   PAYMENT MODE (paybills), DATE, Served by. */
function receipt_header(array $biz, array $sale, string $staff): string
{
    $h = fn($s) => htmlspecialchars((string) $s);
    $rows = [];
    if (!empty($biz['logo'])) {
        $rows[] = '<img src="' . $h($biz['logo']) . '" alt="" style="max-height:104px;max-width:285px;object-fit:contain;margin-bottom:8px;">';
    }
    $rows[] = '<div style="font-size:24px;font-weight:900;">' . $h($biz['name']) . '</div>';
    if (!empty($biz['tagline']))  { $rows[] = '<div style="font-size:15px;font-weight:900;letter-spacing:.5px;">' . $h($biz['tagline']) . '</div>'; }
    if (!empty($biz['po_box']))   { $rows[] = '<div style="font-size:15px;">' . $h($biz['po_box']) . '</div>'; }
    if (!empty($biz['location'])) { $rows[] = '<div style="font-size:15px;">LOCATION: ' . $h($biz['location']) . '</div>'; }
    if (!empty($biz['phones']))   { $rows[] = '<div style="font-size:16px;color:#000;">TEL: ' . $h(implode(' & ', array_filter($biz['phones']))) . '</div>'; }
    if (!empty($biz['pin']))      { $rows[] = '<div style="font-size:15px;">PIN: ' . $h($biz['pin']) . '</div>'; }
    $rows[] = '<div style="font-size:15px;margin-top:4px;">' . $h(date('j M Y, g:i a', strtotime($sale['created_at']))) . '</div>';
    $rows[] = '<div style="font-size:15px;">Served by: ' . $h($staff) . '</div>';
    return '<div style="text-align:center;border-bottom:2px dashed #cbd5e1;padding-bottom:10px;margin-bottom:10px;">'
        . implode('', $rows) . '</div>';
}

function receipt_inner(array $sale, array $items, string $shop, string $staff, array $biz, ?array $tenant = null): string
{
    $h = fn($s) => htmlspecialchars((string) $s);

    // Item table: ITEM | QTY | PRICE | AMT
    $rows = '';
    foreach ($items as $idx => $it) {
        $qty = rtrim(rtrim(number_format((float) $it['quantity'], 2), '0'), '.');
        $rows .= '<tr>'
            . '<td style="padding:4px 4px 4px 0;vertical-align:top;white-space:nowrap;">' . ((int) $idx + 1) . '</td>'
            . '<td style="padding:4px 4px 4px 0;vertical-align:top;">' . $h($it['product_name'])
            . (!empty($it['unit']) ? ' <span style="color:#000;">(' . $h($it['unit']) . ')</span>' : '') . '</td>'
            . '<td style="padding:4px;text-align:center;vertical-align:top;white-space:nowrap;">' . $h($qty) . '</td>'
            . '<td style="padding:4px;text-align:right;vertical-align:top;white-space:nowrap;">' . amt($it['unit_price']) . '</td>'
            . '<td style="padding:4px 0 4px 4px;text-align:right;vertical-align:top;white-space:nowrap;">' . amt($it['line_total']) . '</td>'
            . '</tr>';
    }
    $thead = '<tr style="border-bottom:1px solid #cbd5e1;">'
        . '<th style="text-align:left;padding:0 4px 5px 0;font-size:16px;font-weight:900;">NO.</th>'
        . '<th style="text-align:left;padding:0 4px 5px 0;font-size:16px;font-weight:900;">ITEM</th>'
        . '<th style="text-align:center;padding:0 4px 5px;font-size:16px;font-weight:900;">QTY</th>'
        . '<th style="text-align:right;padding:0 4px 5px;font-size:16px;font-weight:900;">PRICE</th>'
        . '<th style="text-align:right;padding:0 0 5px 4px;font-size:16px;font-weight:900;">AMT</th>'
        . '</tr>';

    // Payment lines (unchanged behaviour)
    $payLine = '';
    $method = $sale['payment_method'] ?? 'cash';
    if ($method === 'split') {
        if ((float)($sale['cash_amount'] ?? 0) > 0) {
            $payLine .= '<tr><td style="color:#64748b;">Cash</td><td style="text-align:right;">' . money($sale['cash_amount']) . '</td></tr>';
            if ((float)($sale['change_given'] ?? 0) > 0) {
                $payLine .= '<tr><td style="color:#64748b;">Change</td><td style="text-align:right;">' . money($sale['change_given']) . '</td></tr>';
            }
        }
        if ((float)($sale['mpesa_amount'] ?? 0) > 0) {
            $payLine .= '<tr><td style="color:#64748b;">M-Pesa</td><td style="text-align:right;">' . money($sale['mpesa_amount']) . '</td></tr>';
        }
    } elseif ($method === 'cash') {
        $payLine = '<tr><td style="color:#64748b;">Cash given</td><td style="text-align:right;">' . money($sale['amount_given']) . '</td></tr>'
          . '<tr><td style="color:#64748b;">Change</td><td style="text-align:right;">' . money($sale['change_given']) . '</td></tr>';
    } elseif ($method === 'card') {
        $payLine = '<tr><td style="color:#64748b;">Paid by</td><td style="text-align:right;">Card</td></tr>';
    } elseif ($method === 'bank') {
        $payLine = '<tr><td style="color:#64748b;">Paid by</td><td style="text-align:right;">Bank transfer</td></tr>';
    } elseif ($method === 'credit') {
        $payLine = '<tr><td style="color:#64748b;">Paid by</td><td style="text-align:right;">Credit</td></tr>';
    } else {
        $payLine = '<tr><td style="color:#64748b;">Paid by</td><td style="text-align:right;">M-Pesa</td></tr>';
    }

    $stype = ($sale['sale_type'] ?? 'retail') === 'wholesale' ? 'Wholesale' : 'Retail';
    $disc = (float)($sale['discount_amount'] ?? 0);
    $vat = (float)($sale['vat_amount'] ?? 0);
    $sub = (float)($sale['subtotal'] ?? $sale['total']);
    $totals = '';
    if ($disc > 0 || $vat > 0) {
        $totals .= '<tr><td style="color:#64748b;">Subtotal</td><td style="text-align:right;">' . money($sub) . '</td></tr>';
        if ($disc > 0) {
            $totals .= '<tr><td style="color:#64748b;">Discount</td><td style="text-align:right;">− ' . money($disc) . '</td></tr>';
        }
        if ($vat > 0) {
            $totals .= '<tr><td style="color:#64748b;">VAT (' . number_format((float)($sale['vat_rate'] ?? 0), 2) . '%)</td><td style="text-align:right;">' . money($vat) . '</td></tr>';
        }
    }
    $cust = '';
    $identityBlock = trim(implode("\n", array_filter([
        $biz['name'] ?? '',
        $biz['po_box'] ?? '',
        $biz['location'] ?? '',
    ])));
    $paymentCredentials = trim((string) ($biz['payment_credentials'] ?? ''));
    $showPaymentCredentials = $paymentCredentials !== ''
        && preg_replace('/\s+/', '', strtolower($paymentCredentials)) !== preg_replace('/\s+/', '', strtolower($identityBlock));
    if ($showPaymentCredentials) {
        $payLine = '<tr><td style="color:#64748b;vertical-align:top;">Payment mode</td><td style="text-align:right;white-space:pre-line;">' . $h($paymentCredentials) . '</td></tr>' . $payLine;
    }
    if (!empty($sale['customer_name']) || !empty($sale['customer_phone'])) {
        $cust = '<p style="margin:10px 0 0;font-size:12px;color:#64748b;">Customer: ' . $h($sale['customer_name'] ?: '—')
              . (!empty($sale['customer_phone']) ? ' · ' . $h($sale['customer_phone']) : '') . '</p>';
    }

    return '<div class="receipt-inner" style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:360px;margin:0 auto;color:#000;font-size:16px;font-weight:900;">'
        . receipt_header($biz, $sale, $staff)
        . '<div style="font-size:17px;color:#000;margin-bottom:6px;">Invoice / Receipt ' . $h($sale['receipt_number']) . ' · ' . $h($stype) . ' sale</div>'
        . '<table style="width:100%;border-collapse:collapse;font-size:16px;">' . $thead . $rows . '</table>'
        . '<table style="width:100%;border-collapse:collapse;font-size:17px;border-top:2px dashed #000;margin-top:8px;padding-top:8px;">'
        . $totals
        . '<tr><td style="font-weight:900;padding-top:8px;font-size:20px;">Total</td><td style="text-align:right;font-weight:900;padding-top:8px;font-size:20px;">' . money($sale['total']) . '</td></tr>'
        . $payLine . '</table>'
        . $cust
        . ReceiptFooter::html($tenant, $sale['receipt_number'] ?? null)
        . '</div>';
}

// --- email delivery ---
$flash = '';
$flashOk = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'email') {
    $to = trim($_POST['email'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $flash = 'Enter a valid email address.';
    } else {
        $html = '<div style="background:#f8fafc;padding:20px;">' . receipt_inner($sale, $items, $shop, $staff, $RECEIPT_BUSINESS, $tenant) . '</div>';
        $sent = (new MailService())->send($to, 'Invoice / Receipt ' . $sale['receipt_number'] . ' — ' . $RECEIPT_BUSINESS['name'], $html, 'Invoice ' . $sale['receipt_number'] . ' from ' . $RECEIPT_BUSINESS['name']);
        if ($sent) { $flash = 'Receipt sent to ' . $to . '.'; $flashOk = true; }
        else { $flash = 'Could not send the email. Check the mail settings and try again.'; }
    }
}

// WhatsApp number (Kenya-friendly)
$waNum = '';
if (!empty($sale['customer_phone'])) {
    $d = preg_replace('/\D+/', '', $sale['customer_phone']);
    if ($d !== '') {
        if (strpos($d, '0') === 0) { $d = '254' . substr($d, 1); }
        elseif (strpos($d, '254') !== 0) { $d = '254' . $d; }
        $waNum = $d;
    }
}
$waText = rawurlencode("Receipt {$sale['receipt_number']} from {$RECEIPT_BUSINESS['name']}\nTotal: " . money($sale['total']) . "\nThank you!");
$waLink = 'https://wa.me/' . $waNum . '?text=' . $waText;

$defaultEmail = htmlspecialchars($sale['customer_email'] ?? '');

// This one page is reached from both the staff till and the owner's Sales/
// Dashboard views — send each viewer back into their own section instead of
// always dropping an owner onto the staff selling screen.
$isStaffViewer = TenantContext::role() === 'staff';
$backLinks = $isStaffViewer
    ? ['New sale' => public_url('staff/dashboard/'), 'My sales' => public_url('staff/sales/')]
    : ['Sales' => public_url('super/sales/'), 'Dashboard' => public_url('super/dashboard/')];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt <?php echo htmlspecialchars($sale['receipt_number']); ?> — <?php echo htmlspecialchars($RECEIPT_BUSINESS['name']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
  body{background:#f1f5f9;margin:0;padding:24px;font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;}
  .sheet{background:#fff;max-width:420px;margin:0 auto 18px;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.1);padding:24px;font-weight:700;position:relative;overflow:hidden;}
  .sheet::before{content:"";position:absolute;inset:42px 18px;background:url('<?php echo htmlspecialchars($RECEIPT_BUSINESS['watermark_logo'], ENT_QUOTES); ?>') center 48%/82% auto no-repeat;opacity:.38;pointer-events:none;}
  .sheet > *{position:relative;z-index:1;}
  .sheet, .sheet *{font-weight:900 !important;color:#000 !important;}
  .sheet table, .sheet th, .sheet td{font-weight:900 !important;color:#000 !important;}
  .actions{max-width:420px;margin:0 auto;}
  @page{margin:8mm;}
  @media print {
    body{background:#fff;padding:0;margin:0;}
    .actions,.noprint{display:none !important;}
    .sheet{box-shadow:none;border-radius:0;margin:0 auto !important;width:80mm;max-width:80mm;padding:10px 12px;}
    .receipt-inner{max-width:100% !important;font-size:16px !important;}
  }
</style>
</head>
<body>
  <?php if ($flash): ?>
    <div class="actions"><div class="alert <?php echo $flashOk ? 'alert-success' : 'alert-danger'; ?> py-2"><?php echo htmlspecialchars($flash); ?></div></div>
  <?php endif; ?>

  <div class="sheet"><?php echo receipt_inner($sale, $items, $shop, $staff, $RECEIPT_BUSINESS, $tenant); ?></div>

  <div class="actions">
    <div class="d-flex gap-2 mb-2">
      <button onclick="window.print()" class="btn btn-primary flex-fill"><i class="fas fa-print me-1"></i> Print / Save PDF</button>
      <a href="<?php echo htmlspecialchars($waLink); ?>" target="_blank" rel="noopener" class="btn btn-success flex-fill"><i class="fab fa-whatsapp me-1"></i> WhatsApp</a>
    </div>
    <form method="post" class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-3">
        <label class="form-label small mb-1">Email the receipt</label>
        <div class="input-group">
          <input type="email" name="email" class="form-control" placeholder="customer@email.com" value="<?php echo $defaultEmail; ?>" required>
          <input type="hidden" name="action" value="email">
          <button class="btn btn-outline-primary"><i class="fas fa-paper-plane me-1"></i> Send</button>
        </div>
      </div>
    </form>
    <div class="d-flex gap-2 mt-3">
      <?php foreach ($backLinks as $label => $url): ?>
        <a href="<?php echo htmlspecialchars($url); ?>" class="btn btn-link flex-fill"><?php echo htmlspecialchars($label); ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</body>
</html>
