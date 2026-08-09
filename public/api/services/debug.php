<?php
// public/api/services/debug.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Step 1: Script started<br>";

// Check if files exist
$files = [
    __DIR__ . '/../../../app/config/db_connect.php',
    __DIR__ . '/../../../app/models/Session.php',
    __DIR__ . '/../../../app/helpers/middleware.php',
    __DIR__ . '/../../../app/controllers/ServiceController.php',
    __DIR__ . '/../../../app/models/ServiceModel.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✓ " . basename($file) . " exists<br>";
    } else {
        echo "✗ " . basename($file) . " NOT FOUND at: " . $file . "<br>";
    }
}

echo "Step 2: Attempting to include files...<br>";

require_once __DIR__ . '/../../../app/config/db_connect.php';
echo "✓ db_connect.php loaded<br>";

require_once __DIR__ . '/../../../app/models/Session.php';
echo "✓ Session.php loaded<br>";

require_once __DIR__ . '/../../../app/helpers/middleware.php';
echo "✓ middleware.php loaded<br>";

require_once __DIR__ . '/../../../app/controllers/ServiceController.php';
echo "✓ ServiceController.php loaded<br>";

echo "Step 3: All files loaded successfully!";
?>