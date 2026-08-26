<?php
// Old Revenues URL — shop now uses Expenses for money out / losses.
require_once __DIR__ . '/../../../app/app.php';
$period = trim((string) ($_GET['period'] ?? ''));
$qs = $period !== '' ? ('?period=' . urlencode($period)) : '';
header('Location: ' . public_url('super/expenses/' . $qs), true, 301);
exit;
