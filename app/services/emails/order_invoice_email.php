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

    $rows = '';
    foreach ($items as $it) {
        $qty = rtrim(rtrim(number_format((float) $it['quantity'], 2), '0'), '.');
        $rows .= '<tr>'
            . '<td style="padding:6px 4px;border-bottom:1px solid #f1f5f9;">' . $h($it['product_name']) . '</td>'
            . '<td style="padding:6px 4px;border-bottom:1px solid #f1f5f9;text-align:center;">' . $h($qty) . '</td>'
            . '<td style="padding:6px 4px;border-bottom:1px solid #f1f5f9;text-align:right;">' . $money($it['unit_price']) . '</td>'
            . '<td style="padding:6px 4px;border-bottom:1px solid #f1f5f9;text-align:right;">' . $money($it['line_total']) . '</td>'
            . '</tr>';
    }

    $discount = (float) ($order['discount_amount'] ?? 0);
    $totalsRows = '';
    if ($discount > 0) {
        $totalsRows .= '<tr><td colspan="3" style="padding:4px 4px;text-align:right;color:#64748b;">Subtotal</td><td style="padding:4px 4px;text-align:right;">' . $money($order['subtotal']) . '</td></tr>';
        $totalsRows .= '<tr><td colspan="3" style="padding:4px 4px;text-align:right;color:#64748b;">Discount</td><td style="padding:4px 4px;text-align:right;">− ' . $money($discount) . '</td></tr>';
    }
    $totalsRows .= '<tr><td colspan="3" style="padding:8px 4px;text-align:right;font-weight:700;border-top:2px solid #e2e8f0;">Amount due</td><td style="padding:8px 4px;text-align:right;font-weight:700;border-top:2px solid #e2e8f0;">' . $money($order['total']) . '</td></tr>';

    $subject = 'Invoice ' . $order['receipt_number'] . ' from ' . $shopName;

    $html = <<<HTML
<div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f1f5f9;padding:32px 0">
  <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">
    <div style="padding:24px 28px;border-bottom:1px solid #f1f5f9">
      <p style="margin:0;font-size:1.05rem;font-weight:700;color:#0f172a">{$h($shopName)}</p>
      <p style="margin:4px 0 0;font-size:.85rem;color:#64748b">Invoice {$h($order['receipt_number'])}</p>
    </div>
    <div style="padding:28px">
      <h1 style="margin:0 0 12px;font-size:20px;color:#0f172a">Hi {$h($order['table_name'])},</h1>
      <p style="margin:0 0 18px;color:#475569;font-size:15px;line-height:1.5">
        Here's an invoice for your order on {$h(date('j M Y', strtotime($order['created_at'])))} — please arrange payment when you're able to.
      </p>
      <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;margin-bottom:8px">
        <tr style="color:#64748b;font-size:12px;text-transform:uppercase;">
          <td style="padding:4px;">Item</td><td style="padding:4px;text-align:center;">Qty</td><td style="padding:4px;text-align:right;">Price</td><td style="padding:4px;text-align:right;">Amount</td>
        </tr>
        {$rows}
        {$totalsRows}
      </table>
      <p style="margin:20px 0 0;color:#94a3b8;font-size:13px;line-height:1.5">
        Questions about this invoice? Just reply to this email.
      </p>
    </div>
  </div>
</div>
HTML;

    $textLines = ["Invoice {$order['receipt_number']} from {$shopName}", ''];
    foreach ($items as $it) {
        $textLines[] = $it['product_name'] . ' x' . rtrim(rtrim(number_format((float) $it['quantity'], 2), '0'), '.') . ' — ' . $money($it['line_total']);
    }
    if ($discount > 0) {
        $textLines[] = '';
        $textLines[] = 'Subtotal: ' . $money($order['subtotal']);
        $textLines[] = 'Discount: -' . $money($discount);
    }
    $textLines[] = '';
    $textLines[] = 'Amount due: ' . $money($order['total']);
    $text = implode("\n", $textLines);

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}
