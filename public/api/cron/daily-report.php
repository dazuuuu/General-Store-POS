<?php
// public/api/cron/daily-report.php
// Web-callable fallback for the daily report cron.
// Protected by a secret token: ?token=<cron_token from app/config/app.php>
// Usage: https://yourdomain.com/Modern/public/api/cron/daily-report.php?token=your-secret

header('Content-Type: text/plain; charset=utf-8');

define('ROOT_PATH', dirname(__DIR__, 3));

$appCfg = is_file(ROOT_PATH . '/app/config/app.php') ? require ROOT_PATH . '/app/config/app.php' : [];
$secret = $appCfg['cron_token'] ?? '';

if ($secret === '' || ($_GET['token'] ?? '') !== $secret) {
    http_response_code(403);
    exit('Forbidden.');
}

// Mark as web-triggered so the CLI-only guard passes
$_GET['_cron_web'] = true;

// Delegate entirely to the CLI script
require ROOT_PATH . '/app/cron/daily_report.php';
