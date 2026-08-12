<?php
// app/helpers/Branding.php
// Logo display rules (single-shop deployment):
//  - Everywhere (login page, dashboards, receipts) -> the shop's uploaded
//    logo from Settings, falling back to the default Duaqabe logo if none
//    has been uploaded yet.
// All paths are resolved through public_url() so they work regardless of
// whether the web server's document root points at the project root or
// straight at the public/ folder (see app/helpers/PathConfig.php).

class Branding
{
    // Default Duaqabe logo, relative to the public web root.
    const DEFAULT_LOGO_REL = 'assets/images/logo/duaqabe-general-logo-black.png';

    /** The default Modern logo (used when no tenant logo has been uploaded). */
    public static function loginLogo(): string
    {
        return public_url(self::DEFAULT_LOGO_REL);
    }

    /** Tenant's own logo for internal pages, receipts, and the login screen; else the default. */
    public static function tenantLogo(?array $tenant): string
    {
        if ($tenant && !empty($tenant['logo_path'])) {
            return public_url(self::normalizeStoredPath($tenant['logo_path']));
        }
        return self::loginLogo();
    }

    /**
     * Older rows (or a differently-configured server) may have a stored path
     * that's already absolute (leading '/', with or without a 'public/'
     * prefix). Strip that down to a path relative to the public root so it
     * can be safely passed through public_url().
     */
    private static function normalizeStoredPath(string $path): string
    {
        $path = ltrim($path, '/');
        if (strpos($path, 'public/') === 0) {
            $path = substr($path, strlen('public/'));
        }
        return $path;
    }
}
