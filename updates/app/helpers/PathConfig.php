<?php
// app/helpers/PathConfig.php
// Single source of truth for project root and public web base URL.
// This removes the need to hard-code folder names like /Kitale or /2in1.

if (!function_exists('path_config_normalize')) {
    function path_config_normalize(string $path): string
    {
        return str_replace('\\', '/', rtrim($path, '/'));
    }
}

if (!defined('ROOT_PATH')) {
    $projectRoot = path_config_normalize(dirname(__DIR__, 2));
    define('ROOT_PATH', $projectRoot);
}

if (!defined('APP_ROOT')) {
    define('APP_ROOT', ROOT_PATH . '/app');
}

if (!defined('PUBLIC_ROOT')) {
    define('PUBLIC_ROOT', ROOT_PATH . '/public');
}

if (!defined('BASE_URL')) {
    $baseUrl = '';
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? path_config_normalize($_SERVER['DOCUMENT_ROOT']) : '';
    $publicRoot = path_config_normalize(PUBLIC_ROOT);
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
    $requestUri = isset($_SERVER['REQUEST_URI']) ? str_replace('\\', '/', $_SERVER['REQUEST_URI']) : '';

    if ($documentRoot !== '' && $documentRoot === $publicRoot) {
        $baseUrl = '';
        foreach ([$scriptName, $requestUri] as $value) {
            if ($value === '') {
                continue;
            }
            $path = parse_url($value, PHP_URL_PATH) ?: $value;
            $publicMarker = '/public';
            $pos = strpos($path, $publicMarker);
            if ($pos !== false) {
                $prefix = substr($path, 0, $pos);
                $candidate = $prefix . $publicMarker;
                if ($candidate !== '/public') {
                    $baseUrl = $candidate;
                }
                break;
            }
        }
    } elseif ($documentRoot !== '' && strpos($publicRoot, $documentRoot . '/') === 0) {
        $relative = substr($publicRoot, strlen($documentRoot));
        $baseUrl = '/' . ltrim($relative, '/');
    } else {
        $candidate = '';
        foreach ([$scriptName, $requestUri] as $value) {
            if ($value === '') {
                continue;
            }
            $path = parse_url($value, PHP_URL_PATH) ?: $value;
            $publicMarker = '/public';
            $pos = strpos($path, $publicMarker);
            if ($pos !== false) {
                $candidate = substr($path, 0, $pos + strlen($publicMarker));
                break;
            }
            if (strpos($path, '/public') === strlen($path) - strlen('/public')) {
                $candidate = $path;
                break;
            }
        }

        if ($candidate !== '') {
            $baseUrl = $candidate;
        } elseif ($scriptName !== '') {
            $baseUrl = '/public';
        } else {
            $baseUrl = '/public';
        }
    }

    define('BASE_URL', $baseUrl);
}

if (!function_exists('public_url')) {
    function public_url(string $path = ''): string
    {
        $base = defined('BASE_URL') ? (string) BASE_URL : '/public';
        $base = rtrim($base, '/');
        $path = ltrim($path, '/');
        return $base === '' ? '/' . ($path !== '' ? $path : '') : $base . '/' . $path;
    }
}

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . public_url($path);
    }
}

if (!function_exists('path_config_rewrite_output')) {
    function path_config_rewrite_output(string $buffer): string
    {
        $base = defined('BASE_URL') ? (string) BASE_URL : '/public';
        $base = rtrim($base, '/');
        $oldPrefix = '/Kitale/public';
        if ($base !== '' && $base !== $oldPrefix) {
            $buffer = str_replace($oldPrefix, $base, $buffer);
        }
        return $buffer;
    }
}

if (!defined('PATH_CONFIG_OUTPUT_BUFFERING')) {
    define('PATH_CONFIG_OUTPUT_BUFFERING', true);
    if (!headers_sent()) {
        ob_start(function (string $buffer): string {
            return path_config_rewrite_output($buffer);
        });
    }
}
