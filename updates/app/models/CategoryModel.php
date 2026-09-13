<?php
// app/models/CategoryModel.php
namespace Models;

class CategoryModel extends Model
{
    protected string $table = 'categories';

    public const TYPES = ['subject', 'stationery', 'product'];

    public function __construct(?\PDO $db = null)
    {
        parent::__construct($db);
        $this->ensureSchema();
    }

    /** Self-heals a `categories` table created before Stationery existed —
     *  same idea as ProductModel's ensureSchema(): re-uploading the PHP is
     *  enough, no manual SQL needed on the live database. */
    private function ensureSchema(): void
    {
        try {
            $this->db->query("SELECT `type` FROM `categories` LIMIT 1");
            try {
                $this->db->exec("ALTER TABLE `categories` MODIFY COLUMN `type` ENUM('subject','stationery','product') NOT NULL DEFAULT 'product'");
            } catch (\PDOException $ignored) {}
        } catch (\PDOException $e) {
            try {
                $this->db->exec("ALTER TABLE `categories` ADD COLUMN `type` ENUM('subject','stationery','product') NOT NULL DEFAULT 'product' AFTER `name`");
            } catch (\PDOException $ignored) {
                return;
            }
            try {
                $this->db->exec('ALTER TABLE `categories` DROP INDEX `uq_cat_tenant_name`');
            } catch (\PDOException $ignored) {
            }
            try {
                $this->db->exec('ALTER TABLE `categories` ADD UNIQUE KEY `uq_cat_tenant_type_name` (`tenant_id`,`type`,`name`)');
            } catch (\PDOException $ignored) {
            }
        }
    }

    public function create(string $name, ?string $imagePath = null, string $type = 'product'): array
    {
        $type = in_array($type, self::TYPES, true) ? $type : 'product';
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'id' => null, 'error' => 'Category name is required.'];
        }
        if (strlen($name) > 120) {
            return ['ok' => false, 'id' => null, 'error' => 'Category name is too long.'];
        }
        if ($this->nameTaken($name, null, $type)) {
            return ['ok' => false, 'id' => null, 'error' => 'You already have a category with that name.'];
        }
        try {
            $id = $this->insert(['name' => $name, 'type' => $type, 'image_path' => $imagePath, 'status' => 'active']);
            return ['ok' => true, 'id' => $id, 'error' => null];
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'id' => null, 'error' => 'You already have a category with that name.'];
            }
            throw $e;
        }
    }

    /** Rename and, optionally, replace the image (pass null to leave it as-is). */
    public function rename(int $id, string $name, ?string $imagePath = null): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'error' => 'Category name is required.'];
        }
        $row = $this->find($id);
        $type = $row['type'] ?? 'subject';
        if ($this->nameTaken($name, $id, $type)) {
            return ['ok' => false, 'error' => 'You already have a category with that name.'];
        }
        $data = ['name' => $name];
        if ($imagePath !== null) { $data['image_path'] = $imagePath; }
        $this->update($id, $data);
        return ['ok' => true, 'error' => null];
    }

    public function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['active', 'draft'], true)) {
            return false;
        }
        return $this->update($id, ['status' => $status]);
    }

    /** Delete only when empty — never orphan subcategories or products. */
    public function deleteSafe(int $id): array
    {
        if ($this->childCount('subcategories', 'category_id', $id) > 0) {
            return ['ok' => false, 'error' => 'Remove or move its subcategories first.'];
        }
        if ($this->childCount('products', 'category_id', $id) > 0) {
            return ['ok' => false, 'error' => 'This category still has products. Move or delete them first.'];
        }
        $this->delete($id);
        return ['ok' => true, 'error' => null];
    }

    public function nameTaken(string $name, ?int $exceptId = null, string $type = 'subject'): bool
    {
        foreach ($this->all(['name' => trim($name), 'type' => $type]) as $row) {
            if ($exceptId === null || (int) $row['id'] !== $exceptId) {
                return true;
            }
        }
        return false;
    }

    /** Reuse the existing category for this name (within this type), or create one. Blank name -> null. */
    public function findOrCreate(string $name, string $type = 'product'): ?int
    {
        $type = in_array($type, self::TYPES, true) ? $type : 'product';
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $rows = $this->all(['name' => $name, 'type' => $type]);
        if ($rows) {
            return (int) $rows[0]['id'];
        }
        $res = $this->create($name, null, $type);
        if ($res['ok']) {
            return (int) $res['id'];
        }
        // Lost a create race — the row that won it now exists.
        $rows = $this->all(['name' => $name, 'type' => $type]);
        return $rows ? (int) $rows[0]['id'] : null;
    }

    /** Matching names for the type-ahead box, "starts with" ranked first. */
    public function suggestions(string $q, int $limit = 8, string $type = 'product'): array
    {
        $type = in_array($type, self::TYPES, true) ? $type : 'product';
        $tid = \TenantContext::tenantId();
        $q = trim($q);
        $stmt = $this->db->prepare(
            "SELECT id, name FROM categories
              WHERE tenant_id = ? AND type = ? AND name LIKE ?
           ORDER BY (name LIKE ?) DESC, name ASC
              LIMIT " . (int) $limit
        );
        $stmt->execute([$tid, $type, '%' . $q . '%', $q . '%']);
        return $stmt->fetchAll();
    }

    /** Categories with their subcategory + product counts. */
    public function listWithCounts(string $type = 'product'): array
    {
        $type = in_array($type, self::TYPES, true) ? $type : 'product';
        // Fall back to legacy 'subject' rows when no product categories exist yet.
        $cats = $this->all(['type' => $type], 'name ASC');
        if (!$cats && $type === 'product') {
            $cats = $this->all(['type' => 'subject'], 'name ASC');
        }
        if (!$cats) {
            return [];
        }
        $ids = array_column($cats, 'id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $sub = $this->db->prepare("SELECT category_id, COUNT(*) c FROM subcategories WHERE category_id IN ($in) GROUP BY category_id");
        $sub->execute($ids);
        $subCounts = [];
        foreach ($sub->fetchAll() as $r) { $subCounts[(int) $r['category_id']] = (int) $r['c']; }
        $prd = $this->db->prepare("SELECT category_id, COUNT(*) c FROM products WHERE category_id IN ($in) GROUP BY category_id");
        $prd->execute($ids);
        $prdCounts = [];
        foreach ($prd->fetchAll() as $r) { $prdCounts[(int) $r['category_id']] = (int) $r['c']; }
        foreach ($cats as &$c) {
            $c['subcategory_count'] = $subCounts[(int) $c['id']] ?? 0;
            $c['product_count']     = $prdCounts[(int) $c['id']] ?? 0;
        }
        return $cats;
    }

    private function childCount(string $table, string $col, int $id): int
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$col} = ? AND tenant_id = ?");
        $stmt->execute([$id, $tid]);
        return (int) $stmt->fetchColumn();
    }
}