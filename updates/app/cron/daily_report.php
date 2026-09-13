<?php
// app/cron/daily_report.php
// CLI script — run via Windows Task Scheduler at 18:00 daily.
// Usage: php "C:\Program Files\Ampps\www\Modern\app\cron\daily_report.php"
//
// What it does:
//   1. Loads all active tenants that have an owner user with an email.
//   2. For each tenant, fetches today's completed sales.
//   3. If any sales exist, emails a formatted HTML report to the owner.

if (php_sapi_name() !== 'cli' && empty($_GET['_cron_web'])) {
    http_response_code(403);
    exit('CLI only. Use the web endpoint for HTTP access.');
}

// Bootstrap — resolve ROOT_PATH relative to this file's location.
define('ROOT_PATH', dirname(__DIR__, 2));

require_once ROOT_PATH . '/vendor/autoload.php';

// Manual autoloader matching app/app.php
spl_autoload_register(function ($class) {
    $prefixes = ['Models\\' => '/app/models/', 'Controllers\\' => '/app/controllers/'];
    foreach ($prefixes as $prefix => $dir) {
        if (strpos($class, $prefix) === 0) {
            $file = ROOT_PATH . $dir . substr($class, strlen($prefix)) . '.php';
            if (is_file($file)) { require_once $file; return; }
        }
    }
    foreach (['/app/helpers/', '/app/services/'] as $dir) {
        $file = ROOT_PATH . $dir . $class . '.php';
        if (is_file($file)) { require_once $file; return; }
    }
});

require_once ROOT_PATH . '/app/helpers/Database.php';
require_once ROOT_PATH . '/app/helpers/TenantContext.php';
require_once ROOT_PATH . '/app/services/MailService.php';

$pdo  = Database::pdo();
$date = date('Y-m-d');
$year = date('Y');

// Load all active tenants with their owner's email
$stmt = $pdo->query(
    "SELECT t.id AS tenant_id, t.name AS shop_name, t.currency,
            u.email AS owner_email, u.username AS owner_name
       FROM tenants t
  LEFT JOIN users u ON u.id = t.owner_user_id
      WHERE t.status = 'active'
        AND u.email IS NOT NULL
        AND u.is_active = 1"
);
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$tenants) {
    clog("No active tenants with owner emails found. Exiting.");
    exit(0);
}

$SA     = new Models\SaleModel($pdo);
$mailer = new MailService();
$sent   = 0;
$skipped = 0;

foreach ($tenants as $t) {
    $sales    = $SA->forTenantId((int) $t['tenant_id'], $date);
    $currency = $t['currency'] ?? 'KES';

    if (!$sales) {
        clog("No sales for {$t['shop_name']} today — skipping.");
        $skipped++;
        continue;
    }

    $sum      = Models\SaleModel::summarize($sales);
    $staffBd  = Models\SaleModel::staffBreakdown($sales);

    $html = build_daily_report_email(
        $t['shop_name'],
        $t['owner_name'],
        $date,
        $sum,
        $staffBd,
        $sales,
        $currency,
        $year
    );

    $subject = "Daily Sales Report — {$t['shop_name']} — " . date('j M Y', strtotime($date));
    $result  = $mailer->send($t['owner_email'], $subject, $html);

    if ($result) {
        clog("✓ Report sent to {$t['owner_email']} for {$t['shop_name']} ({$sum['count']} sales, {$currency} " . number_format($sum['revenue'], 0) . ").");
        $sent++;
    } else {
        clog("✗ Failed to send to {$t['owner_email']}: " . MailService::lastError());
    }
}

clog("Done. Sent: {$sent}, Skipped (no sales): {$skipped}.");
exit(0);

// ---- helpers ----------------------------------------------------------------

function clog(string $msg): void
{
    $ts = date('[Y-m-d H:i:s]');
    $line = "{$ts} {$msg}";
    echo $line . PHP_EOL;

    // Also write to a rotating log file
    $logDir = ROOT_PATH . '/storage/logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
    @file_put_contents($logDir . '/daily_report_' . date('Y-m') . '.log', $line . PHP_EOL, FILE_APPEND);
}

function build_daily_report_email(
    string $shopName,
    string $ownerName,
    string $date,
    array  $sum,
    array  $staffBd,
    array  $sales,
    string $currency,
    int    $year
): string {
    $safeShop  = htmlspecialchars($shopName);
    $safeOwner = htmlspecialchars($ownerName);
    $dateLabel = date('l, j F Y', strtotime($date));
    $revenue   = number_format($sum['revenue'], 0);
    $cash      = number_format($sum['cash'], 0);
    $mpesa     = number_format($sum['mpesa'], 0);
    $count     = $sum['count'];

    // Staff breakdown rows
    $staffRows = '';
    foreach ($staffBd as $name => $d) {
        $staffRows .= '<tr>
          <td style="padding:8px 12px;border-bottom:1px solid #f1f5f9;">' . htmlspecialchars($name) . '</td>
          <td style="padding:8px 12px;border-bottom:1px solid #f1f5f9;text-align:center;">' . $d['count'] . '</td>
          <td style="padding:8px 12px;border-bottom:1px solid #f1f5f9;text-align:right;font-weight:700;color:#2563eb;">'
            . $currency . ' ' . number_format($d['revenue'], 0) . '</td>
        </tr>';
    }

    // Recent sales rows (last 10)
    $recentRows = '';
    $recent = array_slice(array_reverse($sales), 0, 10);
    foreach ($recent as $s) {
        $method = $s['payment_method'] === 'cash'
            ? '<span style="background:#f1f5f9;padding:2px 8px;border-radius:999px;font-size:11px;">Cash</span>'
            : '<span style="background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:999px;font-size:11px;">M-Pesa</span>';
        $recentRows .= '<tr>
          <td style="padding:8px 12px;border-bottom:1px solid #f8fafc;font-size:13px;">'
            . htmlspecialchars($s['receipt_number']) . '</td>
          <td style="padding:8px 12px;border-bottom:1px solid #f8fafc;font-size:13px;">'
            . htmlspecialchars($s['staff_name'] ?? '—') . '</td>
          <td style="padding:8px 12px;border-bottom:1px solid #f8fafc;text-align:center;">' . $method . '</td>
          <td style="padding:8px 12px;border-bottom:1px solid #f8fafc;text-align:right;font-weight:600;font-size:13px;">'
            . $currency . ' ' . number_format((float)$s['total'], 0) . '</td>
        </tr>';
    }

    return <<<HTML
<div style="font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;background:#f1f5f9;padding:40px 0">
  <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,.08)">

    <!-- Header -->
    <div style="background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 60%,#1d4ed8 100%);padding:32px 36px">
      <div style="font-size:22px;font-weight:800;color:#fff">{$safeShop}</div>
      <div style="font-size:13px;color:rgba(255,255,255,.65);margin-top:4px">Daily Sales Report &mdash; {$dateLabel}</div>
    </div>

    <!-- Greeting -->
    <div style="padding:28px 36px 0">
      <p style="margin:0;font-size:15px;color:#0f172a;line-height:1.6">Hi {$safeOwner},<br>
      Here is your sales summary for today.</p>
    </div>

    <!-- Stats -->
    <div style="padding:20px 36px;display:flex;gap:12px;flex-wrap:wrap">
      <div style="flex:1;min-width:140px;background:#eff6ff;border-radius:12px;padding:16px 20px;text-align:center">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:600">Total Revenue</div>
        <div style="font-size:26px;font-weight:800;color:#1d4ed8;margin-top:6px">{$currency} {$revenue}</div>
        <div style="font-size:12px;color:#64748b;margin-top:2px">{$count} sale(s)</div>
      </div>
      <div style="flex:1;min-width:120px;background:#fefce8;border-radius:12px;padding:16px 20px;text-align:center">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:600">Cash</div>
        <div style="font-size:22px;font-weight:700;color:#b45309;margin-top:6px">{$currency} {$cash}</div>
      </div>
      <div style="flex:1;min-width:120px;background:#f0fdf4;border-radius:12px;padding:16px 20px;text-align:center">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:600">M-Pesa</div>
        <div style="font-size:22px;font-weight:700;color:#065f46;margin-top:6px">{$currency} {$mpesa}</div>
      </div>
    </div>

    <!-- Staff breakdown -->
    <div style="padding:0 36px 20px">
      <div style="font-size:13px;font-weight:700;color:#0f172a;margin-bottom:10px;text-transform:uppercase;letter-spacing:.05em">By Staff Member</div>
      <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border-radius:8px;overflow:hidden;border:1px solid #e2e8f0">
        <thead>
          <tr style="background:#f8fafc">
            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;">Staff</th>
            <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;">Sales</th>
            <th style="padding:10px 12px;text-align:right;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;">Revenue</th>
          </tr>
        </thead>
        <tbody>{$staffRows}</tbody>
      </table>
    </div>

    <!-- Recent transactions -->
    <div style="padding:0 36px 28px">
      <div style="font-size:13px;font-weight:700;color:#0f172a;margin-bottom:10px;text-transform:uppercase;letter-spacing:.05em">Transactions</div>
      <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border-radius:8px;overflow:hidden;border:1px solid #e2e8f0">
        <thead>
          <tr style="background:#f8fafc">
            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;">Receipt</th>
            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;">Staff</th>
            <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;">Pay</th>
            <th style="padding:10px 12px;text-align:right;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;">Total</th>
          </tr>
        </thead>
        <tbody>{$recentRows}</tbody>
      </table>
    </div>

    <!-- Footer -->
    <div style="padding:16px 36px;background:#f8fafc;border-top:1px solid #e2e8f0;text-align:center">
      <p style="margin:0;font-size:11px;color:#94a3b8">
        &copy; {$year} {$safeShop} &mdash; Powered by <strong style="color:#64748b">Modern POS</strong><br>
        This report is automatically sent every evening at 6 pm.
      </p>
    </div>

  </div>
</div>
HTML;
}
