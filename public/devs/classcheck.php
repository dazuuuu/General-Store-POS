<?php
// public/devs/classcheck.php
// DEV-ONLY. Shows the REAL file each guard class is loaded from, and the actual
// source of PageGuard's methods that are running — so we can catch a stale or
// duplicate copy being autoloaded. Key-guarded. Delete before prod.
//   http://localhost/Modern/public/devs/classcheck.php?key=modern-dev

require_once __DIR__ . '/../../app/app.php';

const DEV_KEY = 'modern-dev';
if (!hash_equals(DEV_KEY, (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden — append ?key=modern-dev');
}

header('Content-Type: text/plain; charset=utf-8');

function methodSource(string $class, string $method): string
{
    if (!method_exists($class, $method)) return "  (no method {$class}::{$method})\n";
    $rm = new ReflectionMethod($class, $method);
    $file = $rm->getFileName();
    $lines = file($file);
    $src = implode('', array_slice($lines, $rm->getStartLine() - 1, $rm->getEndLine() - $rm->getStartLine() + 1));
    return $src;
}

echo "=== Loaded class files (the code actually running) ===\n";
foreach (['PageGuard', 'TenantContext', 'AccountGuard', 'Capabilities'] as $c) {
    if (class_exists($c)) {
        echo str_pad($c, 16) . " <- " . (new ReflectionClass($c))->getFileName() . "\n";
    } else {
        echo str_pad($c, 16) . " <- NOT LOADED\n";
    }
}
if (class_exists('Models\\SubscriptionModel')) {
    echo str_pad('SubscriptionModel', 16) . " <- " . (new ReflectionClass('Models\\SubscriptionModel'))->getFileName() . "\n";
}

echo "\n=== PageGuard::tenant() (running source) ===\n";
echo methodSource('PageGuard', 'tenant');

echo "\n=== PageGuard::requireActiveSubscription() (running source) ===\n";
echo methodSource('PageGuard', 'requireActiveSubscription');

echo "\n=== PageGuard::deny() (running source) ===\n";
echo methodSource('PageGuard', 'deny');

echo "\n=== Top of public/super/dashboard/index.php (deployed) ===\n";
$dash = ROOT_PATH . '/public/super/dashboard/index.php';
if (is_file($dash)) {
    echo implode('', array_slice(file($dash), 0, 12));
} else {
    echo "  MISSING: $dash\n";
}

echo "\n=== Duplicate guard files anywhere under the project (excluding vendor/.git) ===\n";
$found = [];
try {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(ROOT_PATH, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $f) {
        $path = $f->getPathname();
        if (preg_match('#[\\\\/](vendor|node_modules|\.git)[\\\\/]#', $path)) continue;
        $name = $f->getFilename();
        if ($name === 'PageGuard.php' || $name === 'TenantContext.php') {
            $found[] = $path;
        }
    }
} catch (\Throwable $e) {
    echo "  scan error: " . $e->getMessage() . "\n";
}
if ($found) { foreach ($found as $p) echo "  $p\n"; }
else echo "  (none found by scan)\n";
echo "\nIf more than one PageGuard.php is listed, an old copy may be shadowing the new one.\n";