<?php
// app/services/emails/otp_email.php
// Builds the 2FA code email.

function build_otp_email(string $code, string $context = 'your login'): array
{
    $logo = '../public/assets/images/logo/logo.png';
    $safe = htmlspecialchars($code, ENT_QUOTES);
    $ctx  = htmlspecialchars($context, ENT_QUOTES);

    $subject = "Your verification code: {$code}";

    $html = <<<HTML
<div style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f1f5f9;padding:32px 0">
  <div style="max-width:440px;margin:0 auto;background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden">
    <div style="padding:22px 26px;border-bottom:1px solid #f1f5f9"><img src="{$logo}" alt="Modern" style="height:30px"></div>
    <div style="padding:26px;text-align:center">
      <p style="margin:0 0 8px;color:#475569;font-size:14px">Your verification code for {$ctx}:</p>
      <div style="font-size:34px;font-weight:800;letter-spacing:8px;color:#0f172a;margin:12px 0">{$safe}</div>
      <p style="margin:8px 0 0;color:#94a3b8;font-size:13px">This code expires in 10 minutes. If you didn't try to log in, you can ignore this email.</p>
    </div>
  </div>
</div>
HTML;

    return ['subject' => $subject, 'html' => $html];
}