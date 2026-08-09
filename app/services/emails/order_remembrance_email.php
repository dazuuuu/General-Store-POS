<?php
// Remembrance / follow-up note — gentle reminder for open credit or past visit.

function build_order_remembrance_email(array $order, array $items, array $shop): array
{
    $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);
    $shopName = $shop['name'] ?? 'the shop';
    $money = fn($n) => 'KES ' . number_format((float) $n, 2);
    $customer = $order['table_name'] ?? 'valued customer';
    $isOpen = ($order['status'] ?? '') === 'open';

    $subject = ($isOpen ? 'Friendly reminder — invoice ' : 'We remember you — ')
        . ($order['receipt_number'] ?? '') . ' from ' . $shopName;

    $body = $isOpen
        ? "This is a friendly remembrance note about invoice {$h($order['receipt_number'])} "
          . "for {$money($order['total'] ?? 0)}. Please arrange payment when you can — we're happy to help."
        : "Just a short note to say we remember your visit on {$h(date('j M Y', strtotime($order['created_at'] ?? 'now')))}. "
          . "We would love to welcome you back anytime.";

    $html = <<<HTML
<div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f1f5f9;padding:32px 0">
  <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">
    <div style="padding:24px 28px;border-bottom:1px solid #f1f5f9">
      <p style="margin:0;font-size:1.05rem;font-weight:700;color:#0f172a">{$h($shopName)}</p>
      <p style="margin:4px 0 0;font-size:.85rem;color:#64748b">Remembrance note · {$h($order['receipt_number'] ?? '')}</p>
    </div>
    <div style="padding:28px">
      <h1 style="margin:0 0 12px;font-size:20px;color:#0f172a">Hi {$h($customer)},</h1>
      <p style="margin:0 0 18px;color:#475569;font-size:15px;line-height:1.5">{$body}</p>
      <p style="margin:20px 0 0;color:#94a3b8;font-size:13px;line-height:1.5">
        !THANK YOU FOR VISITING OUR BUSINESS! · !!WELCOME!!
      </p>
    </div>
  </div>
</div>
HTML;

    $text = "Remembrance note from {$shopName}\nInvoice: {$order['receipt_number']}\n\n"
        . strip_tags($body) . "\n\n!THANK YOU FOR VISITING OUR BUSINESS!\n!!WELCOME!!";

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}
