<?php
// app/init.php (Universal initializer)
// Auto-load all required classes

require_once __DIR__ . '/helpers/PathConfig.php';

// Auto-loader function
spl_autoload_register(function ($class) {
    $prefix = 'Controllers\\';
    $base_dir = ROOT_PATH . '/app/controllers/';
    if (strpos($class, $prefix) === 0) {
        $class_name = substr($class, strlen($prefix));
        $file = $base_dir . $class_name . '.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    
    $prefix = 'Models\\';
    $base_dir = ROOT_PATH . '/app/models/';
    if (strpos($class, $prefix) === 0) {
        $class_name = substr($class, strlen($prefix));
        $file = $base_dir . $class_name . '.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    
    return false;
});

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>