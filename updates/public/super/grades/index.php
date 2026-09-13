<?php
// public/super/grades/index.php — bookshop grades removed; redirect to categories.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::INVENTORY_EDIT);
header('Location: ' . public_url('super/categories/'));
exit;
