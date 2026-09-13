<?php
require_once __DIR__ . '/app/helpers/PathConfig.php';

// app/bootstrap.php (Custom autoloader)
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    
    // Define base directories
    $baseDir = __DIR__ . '/';
    
    // Possible file paths
    $paths = [
        $baseDir . $class . '.php',
        $baseDir . '../' . $class . '.php'
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }
    
    return false;
});

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>