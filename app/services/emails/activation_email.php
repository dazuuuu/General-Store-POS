<?php
// app/services/emails/activation_email.php
// Builds the activation email (subject + HTML). Self-contained so it doesn't
// depend on your Mailer's internals — your Mailer just sends the returned body.
//
// Usage:
//   $msg = build_activation_email('Acme Beddings', 'https://.../activate.php?token=...', [
//       'plan' => 'Starter', 'interval' => 'monthly', 'amount' => 1000.00,
//   ]);
//   $mailer->send($toEmail, $msg['subject'], $msg['html']);

function build_activation_email(string $businessName, string $activationLink, array $ctx = []): array
{
    $logo     = '../public/assets/images/logo/logo.png'; // default Modern logo on emails
    $plan     = htmlspecialchars($ctx['plan'] ?? '', ENT_QUOTES);
    $interval = htmlspecialchars($ctx['interval'] ?? '', ENT_QUOTES);
    $amount   = isset($ctx['amount']) ? 'KES ' . number_format((float) $ctx['amount'], 2) : '';
    $name     = htmlspecialchars($businessName, ENT_QUOTES);
    $link     = htmlspecialchars($activationLink, ENT_QUOTES);

    $planLine = $plan ? "<p style=\"margin:0 0 4px;color:#475569;font-size:14px\">Plan: <strong>{$plan}</strong> &middot; {$interval} &middot; {$amount}</p>" : '';

    $subject = "Activate your {$name} account";

    $html = <<<HTML
<div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f1f5f9;padding:32px 0">
  <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">
    <div style="padding:24px 28px;border-bottom:1px solid #f1f5f9">
      <img src="{$logo}" alt="Modern" style="height:32px">
    </div>
    <div style="padding:28px">
      <h1 style="margin:0 0 12px;font-size:20px;color:#0f172a">Welcome, {$name}</h1>
      <p style="margin:0 0 16px;color:#475569;font-size:15px;line-height:1.5">
        Your account has been created. Activate it to start managing your shop.
      </p>
      {$planLine}
      <a href="{$link}"
         style="display:inline-block;margin:20px 0 8px;background:#2563eb;color:#ffffff;
                text-decoration:none;padding:12px 22px;border-radius:8px;font-size:15px;font-weight:600">
        Activate my account
      </a>
      <p style="margin:16px 0 0;color:#94a3b8;font-size:13px;line-height:1.5">
        This link expires in 48 hours. If you didn't sign up, you can ignore this email.
      </p>
    </div>
  </div>
</div>
HTML;

    return ['subject' => $subject, 'html' => $html];
}