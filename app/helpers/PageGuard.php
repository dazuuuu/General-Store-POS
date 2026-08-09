<?php
// app/helpers/PageGuard.php
require_once __DIR__ . '/PathConfig.php';
// Per-request gate for protected pages.
// SINGLE-TENANT BUILD: subscription gating is disabled (no plans to pay for).
// Authentication (password + OTP) and role/capability checks still apply.

class PageGuard
{
    const LOGIN_URL = '/auth/login.php';
    const STAFF_RESET_URL = '/staff/reset-password.php';

    /** Any fully-authenticated user (owner or staff). No role/subscription gate. */
    public static function auth(): void
    {
        self::requireFullAuth();
        if (TenantContext::role() === 'staff' && !empty($_SESSION['must_reset'])) {
            header('Location: ' . self::STAFF_RESET_URL);
            exit;
        }
    }

    /** Require a fully-authenticated tenant OWNER. */
    public static function tenant(): void
    {
        self::requireFullAuth();
        if (TenantContext::role() !== 'tenant_owner') {
            self::deny();
        }
    }

    /**
     * Require the tenant's PRIMARY owner — the one account that can create
     * other admin accounts. Any owner created later via the in-app "Admins"
     * page is a secondary admin (tenant_owner role, but not
     * tenants.owner_user_id) and is denied here, so an admin can't create
     * further admins.
     */
    public static function primaryOwner(): void
    {
        self::tenant();
        $tid = TenantContext::tenantId();
        $uid = TenantContext::userId();
        $stmt = Database::pdo()->prepare('SELECT owner_user_id FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$tid]);
        if ((int) $stmt->fetchColumn() !== $uid) {
            self::deny();
        }
    }

    /** Require a fully-authenticated STAFF member. */
    public static function staff(): void
    {
        self::requireFullAuth();
        if (TenantContext::role() !== 'staff') {
            self::deny();
        }
        if (!empty($_SESSION['must_reset'])) {
            header('Location: ' . self::STAFF_RESET_URL);
            exit;
        }
    }

    /** Require a fully-authenticated user (owner or staff) who holds a capability. */
    public static function capability(string $cap): void
    {
        self::requireFullAuth();
        if (!TenantContext::can($cap)) {
            self::deny();
        }
        if (TenantContext::role() === 'staff' && !empty($_SESSION['must_reset'])) {
            header('Location: ' . self::STAFF_RESET_URL);
            exit;
        }
    }

    private static function requireFullAuth(): void
    {
        $authed = !empty($_SESSION['logged_in']) && !empty($_SESSION['otp_verified']) && TenantContext::check();
        if (!$authed) {
            header('Location: ' . public_url(ltrim(self::LOGIN_URL, '/')));
            exit;
        }
    }

    /** Kept as a no-op so any remaining callers are harmless in the single-tenant build. */
    private static function requireActiveSubscription(): void
    {
        return;
    }

    private static function deny(): void
    {
        header('Location: ' . self::LOGIN_URL . '?denied=1');
        exit;
    }
}