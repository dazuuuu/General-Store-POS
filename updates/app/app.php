<?php
// app/app.php
// Single front bootstrap for the SaaS pages. Supersedes the duplicated
// init.php / bootstrap.php autoloaders. Every page does:
//     require_once __DIR__ . '/<relative>/app/app.php';

require_once __DIR__ . '/helpers/PathConfig.php';

// The shop is in Kenya — fix this explicitly rather than trusting the host's
// OS timezone (which PHP defaults to UTC from anyway when unset). Offers,
// attendance day-boundaries, and every other "is this timestamp now/today"
// check compare a PHP time() against a MySQL DATETIME that was entered in
// the browser as local wall-clock time, so PHP and MySQL must agree — see
// Database::pdo()'s matching `SET time_zone`.
date_default_timezone_set('Africa/Nairobi');

// Composer (PHPMailer, etc.)
if (is_file(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}

spl_autoload_register(function ($class) {
    // Namespaced: Models\X, Controllers\X
    foreach (['Models\\' => '/app/models/', 'Controllers\\' => '/app/controllers/'] as $prefix => $dir) {
        if (strpos($class, $prefix) === 0) {
            $file = ROOT_PATH . $dir . substr($class, strlen($prefix)) . '.php';
            if (is_file($file)) { require_once $file; return; }
        }
    }
    // Global infrastructure classes live in helpers/ or services/.
    foreach (['/app/helpers/', '/app/services/'] as $dir) {
        $file = ROOT_PATH . $dir . $class . '.php';
        if (is_file($file)) { require_once $file; return; }
    }
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Rehydrate the current tenant/user/capabilities for this request.
TenantContext::boot();