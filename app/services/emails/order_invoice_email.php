<?php
// app/services/emails/order_invoice_email.php
// Sent for a credit sale (an open, unpaid tab) — asks the customer for
// payment. Itemized, shows the discount if one was given and the amount due.
//
// Usage:
//   $msg = build_order_invoice_email($order, $items, ['name'=>.., 'phone'=>.., 'address'=>.., 'logo'=>..]);
//   $mailer->send($order['customer_email'], $msg['subject'], $msg['html'], $msg['text']);

function build_order_invoice_email(array $order, array $items, array $shop): array
{
    $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);
    $shopName = $shop['name'] ?? 'the shop';
    $money = fn($n) => 'KES ' . number_format((float) $n, 2);
    $logo = class_exists('Branding') ? Branding::loginLogo() : trim((string) ($shop['logo'] ?? ''));
    if ($logo !== '' && !preg_match('#^https?://#i', $logo)) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $logo = $scheme . '://' . $host . '/' . ltrim($logo, '/');
    }
    $logoHtml = $logo !== '' ? '<img src="' . $h($logo) . '" alt="' . $h($shopName) . '" style="max-height:104px;max-width:285px;object-fit:contain;margin:0 0 8px;">' : '';
    $phone = trim((string) ($shop['phone'] ?? ''));
    $poBox = trim((string) ($shop['po_box'] ?? ''));
    $address = trim((string) ($shop['address'] ?? ''));
    $kraPin = trim((string) ($shop['kra_pin'] ?? ''));
    $contactBits = array_filter([$phone ? 'TEL: ' . $phone : '', $poBox, $address, $kraPin ? 'PIN: ' . $kraPin : '']);
    $contactHtml = '';
    foreach ($contactBits as $bit) {
        $contactHtml .= '<div style="margin:0;font-size:15px;line-height:1.35;color:#000;font-weight:900;">' . $h($bit) . '</div>';
    }
    $paymentCredentials = trim((string) ($shop['payment_credentials'] ?? ''));
    $paymentHtml = $paymentCredentials !== ''
        ? '<tr><td style="padding:4px 0;color:#000;font-weight:900;vertical-align:top;">Payment mode</td><td style="padding:4px 0;text-align:right;color:#000;font-weight:900;white-space:pre-line;">' . nl2br($h($paymentCredentials)) . '</td></tr>'
        : '';
    $dueText = '';
    if (!empty($order['credit_due_at'])) {
        $dueText = ' Payment is due by ' . date('j M Y', strtotime($order['credit_due_at'])) . '.';
    } elseif (!empty($order['credit_duration_days'])) {
        $dueText = ' Payment is due within ' . (int) $order['credit_duration_days'] . ' day(s).';
    }

    $rows = '';
    foreach ($items as $idx => $it) {
        $qty = rtrim(rtrim(number_format((float) $it['quantity'], 2), '0'), '.');
        $rows .= '<tr>'
            . '<td style="padding:4px 4px 4px 0;color:#000;font-size:15px;font-weight:900;vertical-align:top;">' . ((int) $idx + 1) . '</td>'
            . '<td style="padding:4px 4px 4px 0;color:#000;font-size:15px;font-weight:900;vertical-align:top;">' . $h($it['product_name']) . '</td>'
            . '<td style="padding:4px;color:#000;font-size:15px;font-weight:900;text-align:center;vertical-align:top;">' . $h($qty) . '</td>'
            . '<td style="padding:4px;color:#000;font-size:15px;font-weight:900;text-align:right;vertical-align:top;">' . $money($it['unit_price']) . '</td>'
            . '<td style="padding:4px 0 4px 4px;color:#000;font-size:15px;font-weight:900;text-align:right;vertical-align:top;">' . $money($it['line_total']) . '</td>'
            . '</tr>';
    }

    $discount = (float) ($order['discount_amount'] ?? 0);
    $amountPaid = max(0, (float) ($order['amount_paid'] ?? 0));
    $amountDue = (float) ($order['amount_due'] ?? 0);
    if ($amountDue <= 0.0001) {
        $amountDue = max(0, (float) ($order['total'] ?? 0) - $amountPaid);
    }
    $totalsRows = '';
    if ($discount > 0) {
        $totalsRows .= '<tr><td style="padding:4px 0;color:#000;font-weight:900;">Subtotal</td><td style="padding:4px 0;text-align:right;color:#000;font-weight:900;">' . $money($order['subtotal']) . '</td></tr>';
        $totalsRows .= '<tr><td style="padding:4px 0;color:#000;font-weight:900;">Discount</td><td style="padding:4px 0;text-align:right;color:#000;font-weight:900;">- ' . $money($discount) . '</td></tr>';
    }
    if ((float) ($order['vat_amount'] ?? 0) > 0) {
        $totalsRows .= '<tr><td style="padding:4px 0;color:#000;font-weight:900;">VAT (' . number_format((float) ($order['vat_rate'] ?? 0), 2) . '%)</td><td style="padding:4px 0;text-align:right;color:#000;font-weight:900;">' . $money($order['vat_amount']) . '</td></tr>';
    }
    $totalsRows .= '<tr><td style="padding:8px 0 4px;color:#000;font-size:19px;font-weight:900;border-top:2px dashed #000;">Total</td><td style="padding:8px 0 4px;text-align:right;color:#000;font-size:19px;font-weight:900;border-top:2px dashed #000;">' . $money($order['total']) . '</td></tr>';
    if ($amountPaid > 0) {
        $totalsRows .= '<tr><td style="padding:4px 0;color:#000;font-weight:900;">Paid so far</td><td style="padding:4px 0;text-align:right;color:#000;font-weight:900;">' . $money($amountPaid) . '</td></tr>';
    }
    $totalsRows .= '<tr><td style="padding:4px 0;color:#000;font-size:20px;font-weight:900;">Balance</td><td style="padding:4px 0;text-align:right;color:#000;font-size:20px;font-weight:900;">' . $money($amountDue) . '</td></tr>';
    $totalsRows .= $paymentHtml;

    $subject = 'Invoice ' . $order['receipt_number'] . ' from ' . $shopName;
    $invoiceDate = date('j M Y, g:i a', strtotime($order['created_at']));

    $html = <<<HTML
<div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f1f5f9;padding:28px 0">
  <div style="max-width:380px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;position:relative;">
    <div style="background-image:url('{$h($logo)}');background-position:center 52%;background-repeat:no-repeat;background-size:82% auto;">
    <div style="background:rgba(255,255,255,.82);padding:24px;color:#000;font-weight:900;">
    <div style="text-align:center;border-bottom:2px dashed #000;padding-bottom:10px;margin-bottom:10px;">
      {$logoHtml}
      <div style="margin:0;font-size:24px;font-weight:900;color:#000;line-height:1.15">{$h($shopName)}</div>
      {$contactHtml}
      <div style="font-size:16px;margin-top:5px;color:#000;font-weight:900;">Invoice {$h($order['receipt_number'])}</div>
      <div style="font-size:15px;color:#000;font-weight:900;">{$h($invoiceDate)}</div>
      <div style="font-size:15px;color:#000;font-weight:900;">Customer: {$h($order['table_name'])}</div>
    </div>

      <table width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;margin-bottom:8px;border-collapse:collapse;color:#000;font-weight:900;">
        <tr style="border-bottom:1px solid #000;text-transform:uppercase;">
          <td style="padding:0 4px 5px 0;font-size:16px;font-weight:900;">No.</td>
          <td style="padding:0 4px 5px 0;font-size:16px;font-weight:900;">Item</td>
          <td style="padding:0 4px 5px;text-align:center;font-size:16px;font-weight:900;">Qty</td>
          <td style="padding:0 4px 5px;text-align:right;font-size:16px;font-weight:900;">Price</td>
          <td style="padding:0 0 5px 4px;text-align:right;font-size:16px;font-weight:900;">Amount</td>
        </tr>
        {$rows}
      </table>
      <table width="100%" cellpadding="0" cellspacing="0" style="font-size:17px;border-collapse:collapse;color:#000;font-weight:900;">
        {$totalsRows}
      </table>
      <div style="border-top:2px dashed #000;margin-top:10px;padding-top:8px;text-align:center;color:#000;font-size:14px;font-weight:900;line-height:1.35;">
        {$h(trim($dueText))}
      </div>
    </div>
    </div>
  </div>
</div>
HTML;

    $textLines = ["Invoice {$order['receipt_number']} from {$shopName}", ''];
    if ($contactBits) {
        $textLines[] = implode(' · ', $contactBits);
        $textLines[] = '';
    }
    foreach ($items as $idx => $it) {
        $textLines[] = ((int) $idx + 1) . '. ' . $it['product_name'] . ' x' . rtrim(rtrim(number_format((float) $it['quantity'], 2), '0'), '.') . ' — ' . $money($it['line_total']);
    }
    if ($discount > 0) {
        $textLines[] = '';
        $textLines[] = 'Subtotal: ' . $money($order['subtotal']);
        $textLines[] = 'Discount: -' . $money($discount);
    }
    $textLines[] = '';
    $textLines[] = 'Total: ' . $money($order['total']);
    if ($amountPaid > 0) {
        $textLines[] = 'Paid so far: ' . $money($amountPaid);
    }
    $textLines[] = 'Balance: ' . $money($amountDue);
    if ($dueText !== '') {
        $textLines[] = trim($dueText);
    }
    if ($paymentCredentials !== '') {
        $textLines[] = '';
        $textLines[] = 'Payment details:';
        $textLines[] = $paymentCredentials;
    }
    $text = implode("\n", $textLines);

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}
