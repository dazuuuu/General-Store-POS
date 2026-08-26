<?php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::REPORTS_VIEW);

$pdo = Database::pdo();
$tenant = (new Models\TenantModel($pdo))->find(TenantContext::tenantId());
$currency = $tenant['currency'] ?? 'KES';

$type = $_GET['type'] ?? 'all';
$period = $_GET['period'] ?? 'all';

try {
    $workbook = (new TenantDataExportService($pdo, $currency))->workbook($type, $period);
} catch (Throwable $e) {
    error_log('Data export failed: ' . $e->getMessage());
    http_response_code(500);
    echo 'Could not export data.';
    exit;
}

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $workbook['filename']) . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo $workbook['content'];
