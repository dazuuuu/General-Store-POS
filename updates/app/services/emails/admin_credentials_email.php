<?php
// app/services/emails/admin_credentials_email.php
// Sent by public/admin/register.php the moment an owner/admin account is
// created manually — their login credentials for the shop.
//
// Usage:
//   $msg = build_admin_credentials_email('Jane Doe', 'jane@example.com', 'S3cret!23', 'Archimedes Elite Bookshop', $loginUrl);
//   $mailer->send($toEmail, $msg['subject'], $msg['html'], $msg['text']);

function build_admin_credentials_email(string $name, string $email, string $password, string $shopName, string $loginUrl): array
{
    $n     = htmlspecialchars($name, ENT_QUOTES);
    $e     = htmlspecialchars($email, ENT_QUOTES);
    $pw    = htmlspecialchars($password, ENT_QUOTES);
    $shop  = htmlspecialchars($shopName, ENT_QUOTES);
    $url   = htmlspecialchars($loginUrl, ENT_QUOTES);

    $subject = "Your {$shopName} admin account is ready";

    $html = <<<HTML
<div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f1f5f9;padding:32px 0">
  <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">
    <div style="padding:24px 28px;border-bottom:1px solid #f1f5f9">
      <p style="margin:0;font-size:1.05rem;font-weight:700;color:#0f172a">{$shop}</p>
      <p style="margin:4px 0 0;font-size:.85rem;color:#64748b">Admin account credentials</p>
    </div>
    <div style="padding:28px">
      <h1 style="margin:0 0 12px;font-size:20px;color:#0f172a">Welcome, {$n}</h1>
      <p style="margin:0 0 18px;color:#475569;font-size:15px;line-height:1.5">
        Your owner/admin account for <strong>{$shop}</strong> has been created. Here are your credentials — keep them safe.
      </p>
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:22px">
        <tr>
          <td style="padding:14px 18px;border-bottom:1px solid #e2e8f0">
            <p style="margin:0;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b">Email</p>
            <p style="margin:4px 0 0;font-size:.95rem;font-weight:600;color:#0f172a">{$e}</p>
          </td>
        </tr>
        <tr>
          <td style="padding:14px 18px">
            <p style="margin:0;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b">Password</p>
            <p style="margin:4px 0 0;font-size:.95rem;font-weight:600;color:#0f172a;font-family:monospace">{$pw}</p>
          </td>
        </tr>
      </table>
      <a href="{$url}" style="display:inline-block;background:#16a34a;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-size:15px;font-weight:600">
        Log in to your dashboard
      </a>
      <p style="margin:20px 0 0;color:#94a3b8;font-size:13px;line-height:1.5">
        We recommend changing your password after you log in — use "Forgot password" on the login page any time; it'll email you a one-time code to confirm it's you.<br>
        If you weren't expecting this account, you can ignore this email.
      </p>
    </div>
  </div>
</div>
HTML;

    $text = "Welcome, {$name}\n\n"
        . "Your {$shopName} admin account has been created.\n\n"
        . "Email:    {$email}\n"
        . "Password: {$password}\n\n"
        . "Log in: {$loginUrl}\n\n"
        . "We recommend changing your password after you log in (use \"Forgot password\" on the login page — it emails a one-time code).";

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}
