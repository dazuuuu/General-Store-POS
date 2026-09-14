<?php

class SupportGuard
{
    public static function auth(): void
    {
        if(empty($_SESSION['logged_in'])||empty($_SESSION['otp_verified'])||!TenantContext::check()){
            header('Location: '.public_url('domain/support/'));exit;
        }
        PageGuard::platform();
        if(TenantContext::role()!=='platform_admin'){
            http_response_code(403);
            exit('Developer support account required.');
        }
    }
}
