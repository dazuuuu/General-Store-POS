<?php
// Thank-you note after a completed sale / paid invoice.

function build_order_thank_you_email(array $order, array $items, array $shop): array
{
    $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);
    $shopName = $shop['name'] ?? 'the shop';
    $money = fn($n) => 'KES ' . number_format((float) $n, 2);
    $customer = $order['table_name'] ?? 'valued customer';

    $subject = 'Thank you for shopping with ' . $shopName . ' — ' . ($order['receipt_number'] ?? '');

    $html = <<<HTML
<div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f1f5f9;padding:32px 0">
  <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">
    <div style="padding:24px 28px;border-bottom:1px solid #f1f5f9">
      <p style="margin:0;font-size:1.05rem;font-weight:700;color:#0f172a">{$h($shopName)}</p>
      <p style="margin:4px 0 0;font-size:.85rem;color:#64748b">Invoice {$h($order['receipt_number'] ?? '')}</p>
    </div>
    <div style="padding:28px">
      <h1 style="margin:0 0 12px;font-size:20px;color:#0f172a">Hi {$h($customer)},</h1>
      <p style="margin:0 0 18px;color:#475569;font-size:15px;line-height:1.5">
        Thank you for visiting our business! We appreciate your purchase of {$money($order['total'] ?? 0)}.
      </p>
      <p style="margin:0 0 8px;color:#475569;font-size:14px;line-height:1.5">
        GOODS ONCE SOLD ARE NOT REACCEPTED
      </p>
      <p style="margin:18px 0 0;color:#94a3b8;font-size:13px;line-height:1.5">
        !!WELCOME!! We look forward to serving you again.
      </p>
    </div>
  </div>
</div>
HTML;

    $text = "Thank you for shopping with {$shopName}.\nInvoice: {$order['receipt_number']}\nTotal: " . $money($order['total'] ?? 0)
        . "\n\nGOODS ONCE SOLD ARE NOT REACCEPTED\n!THANK YOU FOR VISITING OUR BUSINESS!\n!!WELCOME!!";

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}
