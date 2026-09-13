<?php
// app/helpers/Capabilities.php
// The single source of truth for what actions exist in the system, and the logic
// that resolves a user's *effective* capabilities (role defaults + per-user grants
// - per-user revokes).

class Capabilities
{
    // Tenant-scoped capabilities (what a shop owner / staff can do inside a shop).
    const INVENTORY_VIEW   = 'inventory.view';
    const INVENTORY_EDIT   = 'inventory.edit';
    const STOCK_ENTER      = 'stock.enter';
    const SALES_RECORD     = 'sales.record';
    const SALES_VIEW       = 'sales.view';
    const PAYMENTS_PROCESS = 'payments.process';
    const CUSTOMERS_MANAGE = 'customers.manage';
    const CATALOGUE_SEND   = 'catalogue.send';
    const REPORTS_VIEW     = 'reports.view';
    const STAFF_MANAGE     = 'staff.manage';
    const SETTINGS_MANAGE  = 'settings.manage';
    const BILLING_MANAGE   = 'billing.manage';

    // Platform-scoped capabilities (what you, the SaaS owner, can do).
    const PLATFORM_TENANTS = 'platform.tenants.manage';
    const PLATFORM_PLANS   = 'platform.plans.manage';
    const PLATFORM_BILLING = 'platform.billing.view';

    /** Wildcard held by platform_admin → passes every can() check. */
    const ALL = '*';

    public static function allTenantCaps(): array
    {
        return [
            self::INVENTORY_VIEW, self::INVENTORY_EDIT, self::STOCK_ENTER,
            self::SALES_RECORD, self::SALES_VIEW, self::PAYMENTS_PROCESS, self::CUSTOMERS_MANAGE,
            self::CATALOGUE_SEND, self::REPORTS_VIEW, self::STAFF_MANAGE,
            self::SETTINGS_MANAGE, self::BILLING_MANAGE,
        ];
    }

    /**
     * Resolve a user's effective capability list.
     * Starts from the role's default set (from roles.capabilities JSON), then
     * applies per-user grants/revokes from user_permissions.
     *
     * Returns ['*'] for platform admins (all access).
     */
    public static function effective(PDO $db, int $userId, int $roleId): array
    {
        // Role defaults
        $stmt = $db->prepare('SELECT capabilities FROM roles WHERE id = ?');
        $stmt->execute([$roleId]);
        $json = $stmt->fetchColumn();
        $caps = $json ? (json_decode($json, true) ?: []) : [];

        if (in_array(self::ALL, $caps, true)) {
            return [self::ALL]; // platform admin short-circuit
        }

        $caps = array_fill_keys($caps, true);

        // Per-user overrides
        $stmt = $db->prepare('SELECT capability, effect FROM user_permissions WHERE user_id = ?');
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $row) {
            if ($row['effect'] === 'grant') {
                $caps[$row['capability']] = true;
            } else { // revoke
                unset($caps[$row['capability']]);
            }
        }

        return array_keys($caps);
    }
}