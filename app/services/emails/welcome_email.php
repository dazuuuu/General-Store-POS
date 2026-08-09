<?php
// app/services/emails/welcome_email.php
// Built and sent the moment a subscription payment succeeds. Doubles as the
// payment receipt (plan, amount, M-Pesa code, active-until date).
//
// Usage:
//   $msg = build_welcome_email('Acme Beddings', [
//     'plan' => 'Standard', 'interval' => 'biweekly', 'amount' => 10.00,
//     'receipt' => 'QGH7XYZ123', 'period_end' => '2026-07-04', 'login_url' => 'https://.../login.php',
//   ]);
//   $mailer->send($toEmail, $msg['subject'], $msg['html']);

function build_welcome_email(string $businessName, array $ctx = []): array
{
    $logo = '../public/assets/images/logo/logo.png';
    $name = htmlspecialchars($businessName, ENT_QUOTES);

    $intervalLabels = ['weekly' => 'Weekly', 'biweekly' => 'Every 2 weeks', 'monthly' => 'Monthly'];
    $interval = $intervalLabels[$ctx['interval'] ?? ''] ?? htmlspecialchars((string)($ctx['interval'] ?? ''), ENT_QUOTES);
    $plan     = htmlspecialchars((string)($ctx['plan'] ?? ''), ENT_QUOTES);
    $amount   = isset($ctx['amount']) ? 'KES ' . number_format((float) $ctx['amount'], 0) : '';
    $receipt  = htmlspecialchars((string)($ctx['receipt'] ?? ''), ENT_QUOTES);
    $until    = !empty($ctx['period_end']) ? date('j M Y', strtotime((string)$ctx['period_end'])) : '';
    $login    = htmlspecialchars((string)($ctx['login_url'] ?? '#'), ENT_QUOTES);

    $rows = '';
    $row = function (string $k, string $v) {
        return $v === '' ? '' :
            "<tr><td style=\"padding:6px 0;color:#64748b;font-size:14px\">{$k}</td>" .
            "<td style=\"padding:6px 0;text-align:right;color:#0f172a;font-size:14px;font-weight:600\">{$v}</td></tr>";
    };
    $rows .= $row('Plan', $plan);
    $rows .= $row('Billing', $interval);
    $rows .= $row('Amount paid', $amount);
    $rows .= $row('M-Pesa code', $receipt);
    $rows .= $row('Active until', $until);

    $subject = 'Your shop is active — welcome to Modern POS';

    $html = <<<HTML
<div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f1f5f9;padding:32px 0">
  <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">
    <div style="padding:24px 28px;border-bottom:1px solid #f1f5f9">
      <img src="{$logo}" alt="Modern" style="height:32px">
    </div>
    <div style="padding:28px">
      <h1 style="margin:0 0 12px;font-size:20px;color:#0f172a">You're all set, {$name}</h1>
      <p style="margin:0 0 18px;color:#475569;font-size:15px;line-height:1.5">
        Your payment came through and your shop is now active. Here's your receipt:
      </p>
      <table style="width:100%;border-collapse:collapse;border-top:1px solid #f1f5f9;border-bottom:1px solid #f1f5f9;margin-bottom:20px">
        {$rows}
      </table>
      <a href="{$login}"
         style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;
                padding:12px 22px;border-radius:8px;font-size:15px;font-weight:600">
        Log in to your shop
      </a>
      <p style="margin:20px 0 0;color:#94a3b8;font-size:13px;line-height:1.5">
        You'll be asked for a one-time code at login to keep your shop secure.
      </p>
    </div>
  </div>
</div>
HTML;

    return ['subject' => $subject, 'html' => $html];
}