<?php
// public/super/profile/index.php — moved to Settings; keep old bookmarks working.
require_once __DIR__ . '/../../../app/helpers/PathConfig.php';
header('Location: ' . public_url('super/settings/'));
exit;
