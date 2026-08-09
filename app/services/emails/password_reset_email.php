<?php
// app/services/emails/password_reset_email.php
// Builds the password-reset OTP email.

function build_password_reset_email(string $code, string $shopName = 'Modern POS'): array
{
    $safe = htmlspecialchars($code, ENT_QUOTES);
    $ctx  = htmlspecialchars($shopName, ENT_QUOTES);
    $year = date('Y');

    $subject = "Reset your password — {$shopName}";

    $html = <<<HTML
<div style="font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;background:#f1f5f9;padding:40px 0;min-height:100%">
  <div style="max-width:480px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,.1)">

    <!-- Header -->
    <div style="background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 60%,#1d4ed8 100%);padding:32px 36px;text-align:center">
      <div style="font-size:22px;font-weight:800;color:#fff;letter-spacing:-0.3px">{$ctx}</div>
      <div style="margin-top:4px;font-size:13px;color:rgba(255,255,255,.65)">Password Reset</div>
    </div>

    <!-- Body -->
    <div style="padding:32px 36px;text-align:center">
      <div style="width:56px;height:56px;border-radius:50%;background:#eff6ff;display:flex;align-items:center;justify-content:center;margin:0 auto 20px">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
      </div>
      <p style="margin:0 0 8px;color:#475569;font-size:15px;line-height:1.6">
        We received a request to reset the password for your account.
        Enter this code to continue:
      </p>
      <div style="font-size:40px;font-weight:800;letter-spacing:10px;color:#0f172a;margin:20px 0;font-variant-numeric:tabular-nums">{$safe}</div>
      <p style="margin:0;color:#94a3b8;font-size:13px;line-height:1.6">
        This code expires in <strong>10 minutes</strong>.<br>
        If you didn't request this, you can safely ignore this email — your password won't change.
      </p>
    </div>

    <!-- Footer -->
    <div style="padding:16px 36px;background:#f8fafc;border-top:1px solid #e2e8f0;text-align:center">
      <p style="margin:0;font-size:11px;color:#94a3b8">
        &copy; {$year} {$ctx} &mdash; Powered by <strong style="color:#64748b">Modern POS</strong>
      </p>
    </div>

  </div>
</div>
HTML;

    return ['subject' => $subject, 'html' => $html];
}
