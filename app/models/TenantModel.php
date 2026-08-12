<?php
// app/models/TenantModel.php
namespace Models;

/**
 * The tenant record itself. NOT tenant-scoped (it predates/defines the scope),
 * so $tenantScoped is false and queries are addressed by tenant id explicitly.
 */
class TenantModel extends Model
{
    protected string $table = 'tenants';
    protected bool $tenantScoped = false;

    public function create(string $name, string $slug): int
    {
        return $this->insert(['name' => $name, 'slug' => $slug, 'status' => 'active']);
    }

    public function setOwner(int $tenantId, int $userId): bool
    {
        return $this->update($tenantId, ['owner_user_id' => $userId]);
    }

    /** Whitelisted business-settings update. Caller passes their own tenant id. */
    public function updateSettings(int $tenantId, array $data): bool
    {
        $allowed = [
            'name', 'logo_path', 'currency', 'phone', 'address', 'po_box', 'business_email', 'receipt_footer', 'kra_pin',
            'payment_credentials', 'payment_methods_json',
            'vat_rate', 'vat_inclusive', 'loyalty_points_per_kes', 'loyalty_kes_per_point',
            'low_stock_alert_enabled',
        ];
        $clean = array_intersect_key($data, array_flip($allowed));
        if (!$clean) {
            return false;
        }
        return $this->update($tenantId, $clean);
    }

    /** Ensure general-shop columns exist (safe on older installs). */
    public function ensureShopSchema(): void
    {
        $checks = [
            'vat_rate' => "ALTER TABLE `tenants` ADD COLUMN `vat_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `kra_pin`",
            'vat_inclusive' => "ALTER TABLE `tenants` ADD COLUMN `vat_inclusive` TINYINT(1) NOT NULL DEFAULT 1 AFTER `vat_rate`",
            'loyalty_points_per_kes' => "ALTER TABLE `tenants` ADD COLUMN `loyalty_points_per_kes` DECIMAL(8,2) NOT NULL DEFAULT 1.00 AFTER `vat_inclusive`",
            'loyalty_kes_per_point' => "ALTER TABLE `tenants` ADD COLUMN `loyalty_kes_per_point` DECIMAL(8,4) NOT NULL DEFAULT 0.0100 AFTER `loyalty_points_per_kes`",
            'low_stock_alert_enabled' => "ALTER TABLE `tenants` ADD COLUMN `low_stock_alert_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `loyalty_kes_per_point`",
            'payment_credentials' => "ALTER TABLE `tenants` ADD COLUMN `payment_credentials` TEXT NULL AFTER `kra_pin`",
            'payment_methods_json' => "ALTER TABLE `tenants` ADD COLUMN `payment_methods_json` TEXT NULL AFTER `payment_credentials`",
            'po_box' => "ALTER TABLE `tenants` ADD COLUMN `po_box` VARCHAR(120) NULL AFTER `address`",
            'business_email' => "ALTER TABLE `tenants` ADD COLUMN `business_email` VARCHAR(190) NULL AFTER `po_box`",
        ];
        foreach ($checks as $column => $sql) {
            try {
                $this->db->query("SELECT `{$column}` FROM `tenants` LIMIT 1");
            } catch (\PDOException $e) {
                try { $this->db->exec($sql); } catch (\PDOException $ignored) {}
            }
        }
        try {
            $this->db->exec('ALTER TABLE `tenants` MODIFY COLUMN `receipt_footer` TEXT NULL');
        } catch (\PDOException $ignored) {}
    }

    /** Unique slug from a business name. */
    public function uniqueSlug(string $name): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        if ($base === '') {
            $base = 'shop';
        }
        $slug = $base;
        $i = 1;
        while ($this->slugExists($slug)) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }

    private function slugExists(string $slug): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM tenants WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * The one real business for this single-client deployment — used to show
     * the shop's name/logo on pages that run before anyone is logged in
     * (the PIN screen, the admin login). Resolved by slug (stable even if
     * old test/duplicate tenant rows around it get renumbered or cleaned up),
     * falling back to the oldest active tenant if that slug is ever gone.
     */
    public function primary(): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM tenants WHERE slug = 'lucsela-pos' AND status = 'active' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
        $stmt = $this->db->query("SELECT * FROM tenants WHERE status = 'active' ORDER BY id ASC LIMIT 1");
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
