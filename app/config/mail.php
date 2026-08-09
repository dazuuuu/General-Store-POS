<?php
// app/config/mail.php — the ONE place outgoing email is configured.
// Gmail requires an App Password (NOT your normal password):
//   https://support.google.com/accounts/answer/185833

return [
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'encryption' => 'tls',                     // 'tls' for port 587, 'ssl' for 465

    'username'   => 'vickiekaran254@gmail.com',
    'password'   => 'main ngmz sanw ijak',     // Gmail App Password — regenerate after testing

    // Gmail blocks/rewrites a From it can't verify, so the From matches the login:
    'from_email' => 'vickiekaran254@gmail.com',
    'from_name'  => '2in1 POS',

    // Local Windows/AMPPS often can't verify Gmail's TLS certificate. This is the
    // same toggle the mail-test page uses. Keep true locally; set false in
    // production once a proper CA bundle (openssl.cafile in php.ini) is set.
    'skip_verify' => true,
];