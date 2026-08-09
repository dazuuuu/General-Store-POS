<?php
$basePath = '/public';
if (!empty($_SERVER['REQUEST_URI'])) {
    $requestUri = strtok($_SERVER['REQUEST_URI'], '?');
    if (strpos($requestUri, '/public') !== false) {
        $basePath = substr($requestUri, 0, strpos($requestUri, '/public') + strlen('/public'));
    }
}
header('Location: ' . $basePath . '/');
exit();

