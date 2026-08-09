<?php
// app/config/app.php
// Application-level settings only. All mail/SMTP settings live in app/config/mail.php.

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = defined('BASE_URL') && BASE_URL !== '' ? BASE_URL : '/public';

return [
    'app_name'     => '2in1',
    'app_url'      => $scheme . '://' . $host . $basePath,   // change to your domain in production
    'debug'        => false,                         // set false in production
    'timezone'     => 'UTC',
    'session_name' => '2in1_session',

    // Security
    'hash_cost'    => 12,

    // OTP testing aid: when true, the login screen shows the 6-digit code and, if
    // sending failed, the exact reason. TESTING ONLY — set to false in production.
    'otp_debug'    => false,

    // Secret token for the web-callable daily report endpoint.
    // Change this to a long random string before going live.
    'cron_token'   => 'change-this-to-a-long-random-secret',
];