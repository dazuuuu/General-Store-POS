<?php
// cron/daily_sales_email.php
// Emails each shop owner a PDF of the day's sales. Schedule daily at 18:00.
//   Linux cron:        0 18 * * * php /path/to/cron/daily_sales_email.php >> /var/log/kitale-sales.log 2>&1
//   Windows Scheduler: program  php   args  C:\...\cron\daily_sales_email.php   (daily 6:00 PM)
// Optional arg: a Y-m-d date to (re)send a specific day, e.g. `php daily_sales_email.php 2026-06-21`.

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require_once __DIR__ . '/../app/app.php';

$date = isset($argv[1]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $argv[1]) ? $argv[1] : date('Y-m-d');
$pdo  = Database::pdo();
$log  = fn($m) => print('[' . date('Y-m-d H:i:s') . "] $m\n");

$tenants = $pdo->query(
    "SELECT t.id, t.name, u.email AS owner_email
       FROM tenants t
       JOIN users u ON u.id = t.owner_user_id
      WHERE u.email IS NOT NULL AND u.email <> ''"
)->fetchAll();

if (!$tenants) { $log('No tenants with an owner email — nothing to send.'); exit; }

$mailer = new MailService();
$sent = 0; $failed = 0;

foreach ($tenants as $t) {
    $tid = (int) $t['id'];
    try {
        $data = SalesReport::data($pdo, $tid, $date);
        $cur  = $data['shop']['currency'] ?: 'KES';
        $subject = 'Daily Sales — ' . ($data['shop']['name'] ?: 'Shop') . ' — ' . date('j M Y', strtotime($date))
                 . ' (' . $cur . ' ' . number_format((float) $data['sum']['revenue'], 0) . ')';
        $html = SalesReport::emailHtml($data);
        $pdf  = SalesReport::pdf($data);
        $ok = $mailer->send(
            $t['owner_email'], $subject, $html, '',
            [['data' => $pdf, 'name' => 'sales-report-' . $date . '.pdf', 'type' => 'application/pdf']]
        );
        if ($ok) { $sent++; $log("Sent to {$t['owner_email']} — {$data['sum']['count']} sale(s), {$cur} " . number_format((float)$data['sum']['revenue'],0)); }
        else     { $failed++; $log("FAILED to {$t['owner_email']}: " . MailService::lastError()); }
    } catch (\Throwable $e) {
        $failed++; $log("ERROR tenant {$tid}: " . $e->getMessage());
    }
}
$log("Done. sent={$sent} failed={$failed} date={$date}");