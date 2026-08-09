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
        $allowed = ['name', 'logo_path', 'currency', 'phone', 'address', 'receipt_footer', 'kra_pin'];
        $clean = array_intersect_key($data, array_flip($allowed));
        if (!$clean) {
            return false;
        }
        return $this->update($tenantId, $clean);
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