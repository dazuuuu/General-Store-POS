<?php
// public/auth/staff-login.php — retired. Staff PIN entry now lives at the
// site root; this just catches old bookmarks/links.
require_once __DIR__ . '/../../app/helpers/PathConfig.php';
header('Location: ' . public_url(''));
exit;
