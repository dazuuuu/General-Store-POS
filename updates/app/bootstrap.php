<?php
// app/bootstrap.php

// Autoloader for classes
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $prefix = 'Controllers\\';
    $base_dir = __DIR__ . '/controllers/';
    
    if (strpos($class, $prefix) === 0) {
        $relative_class = substr($class, strlen($prefix));
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
    
    $prefix = 'Models\\';
    $base_dir = __DIR__ . '/models/';
    
    if (strpos($class, $prefix) === 0) {
        $relative_class = substr($class, strlen($prefix));
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
    
    // For classes without namespace (like SessionModel)
    $directories = [__DIR__ . '/models/', __DIR__ . '/controllers/', __DIR__ . '/helpers/'];
    foreach ($directories as $directory) {
        $file = $directory . $class . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});