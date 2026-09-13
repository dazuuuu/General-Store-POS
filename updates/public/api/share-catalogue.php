<?php
// public/api/share-catalogue.php
// POST endpoint — sends the catalogue link by email using MailService.
// Expects JSON body: { "email": "...", "catalogue_url": "...", "shop_name": "..." }
// Returns JSON: { "ok": true } or { "ok": false, "error": "..." }

header('Content-Type: application/json');

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}
if (is_file(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}
spl_autoload_register(function ($class) {
    foreach (['/app/helpers/', '/app/services/'] as $dir) {
        $file = ROOT_PATH . $dir . $class . '.php';
        if (is_file($file)) { require_once $file; return; }
    }
});
if (session_status() === PHP_SESSION_NONE) { session_start(); }
TenantContext::boot();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

// Parse JSON body
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    // Fall back to form POST
    $body = $_POST;
}

$recipientEmail = trim($body['email'] ?? '');
$catalogueUrl   = trim($body['catalogue_url'] ?? '');
$shopName       = trim($body['shop_name'] ?? 'Our Shop');

// Basic validation
if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}
if ($catalogueUrl === '') {
    echo json_encode(['ok' => false, 'error' => 'Catalogue URL is missing.']);
    exit;
}

// Build a premium HTML email
$safeShop = htmlspecialchars($shopName);
$safeUrl  = htmlspecialchars($catalogueUrl);
$year     = date('Y');

$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Product Catalogue — {$safeShop}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
      <td align="center" style="padding:40px 16px;">
        <table width="560" cellpadding="0" cellspacing="0" role="presentation" style="border-radius:16px;overflow:hidden;box-shadow:0 4px 32px rgba(0,0,0,.1);">

          <!-- Header -->
          <tr>
            <td style="background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 50%,#1d4ed8 100%);padding:36px 40px;text-align:center;">
              <div style="font-size:28px;font-weight:800;color:#fff;letter-spacing:-0.5px;">{$safeShop}</div>
              <div style="margin-top:6px;font-size:13px;color:rgba(255,255,255,.7);">Product Catalogue</div>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="background:#fff;padding:36px 40px;">
              <p style="margin:0 0 12px;font-size:15px;color:#0f172a;line-height:1.6;">Hi there 👋,</p>
              <p style="margin:0 0 24px;font-size:15px;color:#334155;line-height:1.7;">
                You've been shared a product catalogue from <strong style="color:#0f172a;">{$safeShop}</strong>.
                Click the button below to browse all available products, view prices, and explore what's in stock.
              </p>

              <!-- CTA Button -->
              <table cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto 28px;">
                <tr>
                  <td style="background:#2563eb;border-radius:12px;">
                    <a href="{$safeUrl}" style="display:inline-block;padding:16px 36px;font-size:15px;font-weight:700;color:#fff;text-decoration:none;letter-spacing:0.2px;">
                      Browse Catalogue &rarr;
                    </a>
                  </td>
                </tr>
              </table>

              <p style="margin:0;font-size:12px;color:#94a3b8;text-align:center;">
                Or copy this link into your browser:<br>
                <a href="{$safeUrl}" style="color:#2563eb;word-break:break-all;">{$safeUrl}</a>
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background:#f8fafc;padding:20px 40px;text-align:center;border-top:1px solid #e2e8f0;">
              <p style="margin:0;font-size:11px;color:#94a3b8;">
                &copy; {$year} {$safeShop} &mdash; Powered by <strong style="color:#64748b;">Modern POS</strong>
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

$subject = "Product Catalogue — {$shopName}";
$altBody = "Browse the product catalogue for {$shopName}: {$catalogueUrl}";

$mailer = new MailService();
$sent   = $mailer->send($recipientEmail, $subject, $html, $altBody);

if ($sent) {
    echo json_encode(['ok' => true]);
} else {
    $err = MailService::lastError();
    echo json_encode(['ok' => false, 'error' => $err ?: 'Could not send the email. Please try again.']);
}
