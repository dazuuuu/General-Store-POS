<?php
// app/models/BookAttributeModel.php
// Tenant-scoped lookup values (brand; legacy grade/publisher/author/edition kept
// for older rows). Same dedupe idea as CategoryModel/SupplierModel.
namespace Models;

class BookAttributeModel extends Model
{
    protected string $table = 'book_attributes';

    public const TYPES = ['grade', 'publisher', 'author', 'edition', 'brand'];

    public function __construct(?\PDO $db = null)
    {
        parent::__construct($db);
        $this->ensureSchema();
    }

    /** Reuse the existing row for this type+name, or create one. Blank name -> null. */
    public function findOrCreate(string $type, string $name): ?int
    {
        if (!in_array($type, self::TYPES, true)) {
            return null;
        }
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $existing = $this->findId($type, $name);
        if ($existing !== null) {
            return $existing;
        }
        try {
            return $this->insert(['type' => $type, 'name' => $name]);
        } catch (\PDOException $e) {
            // Lost a create race (unique key) — fall back to the row that won it.
            if ($e->getCode() === '23000') {
                return $this->findId($type, $name);
            }
            throw $e;
        }
    }

    /** Explicit add from the management page — rejects a duplicate instead of silently reusing it. */
    public function create(string $type, string $name): array
    {
        if (!in_array($type, self::TYPES, true)) {
            return ['ok' => false, 'id' => null, 'error' => 'Invalid type.'];
        }
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'id' => null, 'error' => ucfirst($type) . ' name is required.'];
        }
        if (strlen($name) > 160) {
            return ['ok' => false, 'id' => null, 'error' => ucfirst($type) . ' name is too long.'];
        }
        if ($this->findId($type, $name) !== null) {
            return ['ok' => false, 'id' => null, 'error' => 'You already have a ' . $type . ' with that name.'];
        }
        try {
            $id = $this->insert(['type' => $type, 'name' => $name]);
            return ['ok' => true, 'id' => $id, 'error' => null];
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'id' => null, 'error' => 'You already have a ' . $type . ' with that name.'];
            }
            throw $e;
        }
    }

    /** Rows of this type with a count of products currently assigned to them. */
    public function listWithCounts(string $type): array
    {
        if (!in_array($type, self::TYPES, true)) {
            return [];
        }
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare('SELECT id, name FROM book_attributes WHERE tenant_id = ? AND type = ? ORDER BY name ASC');
        $stmt->execute([$tid, $type]);
        $rows = $stmt->fetchAll();
        if (!$rows) {
            return [];
        }
        $col = ($type === 'brand') ? 'brand_id' : ($type . '_id'); // grade_id / publisher_id / brand_id…
        $ids = array_column($rows, 'id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $cstmt = $this->db->prepare("SELECT {$col}, COUNT(*) c FROM products WHERE tenant_id = ? AND {$col} IN ($in) GROUP BY {$col}");
        $cstmt->execute(array_merge([$tid], $ids));
        $counts = [];
        foreach ($cstmt->fetchAll() as $r) { $counts[(int) $r[$col]] = (int) $r['c']; }
        foreach ($rows as &$r) { $r['book_count'] = $counts[(int) $r['id']] ?? 0; }
        return $rows;
    }

    private function ensureSchema(): void
    {
        try {
            $this->db->query("SELECT 1 FROM `book_attributes` LIMIT 1");
            $this->ensureBrandType();
            return;
        } catch (\PDOException $e) {
            // Ignore and create the table if it does not exist.
        }

        $sql = [];
        $sql[] = "CREATE TABLE IF NOT EXISTS `book_attributes` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `tenant_id` INT NOT NULL,
            `type` ENUM('grade','publisher','author','edition','brand') NOT NULL,
            `name` VARCHAR(160) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_attr_tenant_type_name` (`tenant_id`, `type`, `name`),
            KEY `idx_attr_tenant_type` (`tenant_id`, `type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $this->db->exec(implode("\n", $sql));

        $productColumns = [];
        try {
            $this->db->query("SELECT `grade_id` FROM `products` LIMIT 1");
        } catch (\PDOException $e) {
            $productColumns[] = "ALTER TABLE `products` ADD COLUMN `grade_id` INT NULL AFTER `subcategory_id`;";
            $productColumns[] = "ALTER TABLE `products` ADD COLUMN `publisher_id` INT NULL AFTER `grade_id`;";
            $productColumns[] = "ALTER TABLE `products` ADD COLUMN `author_id` INT NULL AFTER `publisher_id`;";
            $productColumns[] = "ALTER TABLE `products` ADD COLUMN `edition_id` INT NULL AFTER `author_id`;";
            $productColumns[] = "ALTER TABLE `products` ADD KEY `idx_prod_grade` (`grade_id`);";
            $productColumns[] = "ALTER TABLE `products` ADD KEY `idx_prod_publisher` (`publisher_id`);";
            $productColumns[] = "ALTER TABLE `products` ADD KEY `idx_prod_author` (`author_id`);";
            $productColumns[] = "ALTER TABLE `products` ADD KEY `idx_prod_edition` (`edition_id`);";
            foreach ($productColumns as $columnSql) {
                try {
                    $this->db->exec($columnSql);
                } catch (\PDOException $e) {
                    // Ignore if the column or index already exists.
                }
            }
        }
    }

    /** A `book_attributes` table created before Stationery existed has a
     *  `type` ENUM without 'brand' — widen it in place, once, cheaply. */
    private function ensureBrandType(): void
    {
        $stmt = $this->db->query("SHOW COLUMNS FROM `book_attributes` LIKE 'type'");
        $col = $stmt ? $stmt->fetch() : null;
        $definition = $col['Type'] ?? '';
        if ($definition !== '' && stripos($definition, "'brand'") === false) {
            try {
                $this->db->exec("ALTER TABLE `book_attributes` MODIFY COLUMN `type` ENUM('grade','publisher','author','edition','brand') NOT NULL");
            } catch (\PDOException $ignored) {
                // Best-effort — a concurrent request may have already widened it.
            }
        }
    }

    public function findId(string $type, string $name): ?int
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            'SELECT id FROM book_attributes WHERE tenant_id = ? AND type = ? AND name = ? LIMIT 1'
        );
        $stmt->execute([$tid, $type, trim($name)]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    public function nameFor(?int $id): ?string
    {
        if (!$id) {
            return null;
        }
        $row = $this->find($id);
        return $row ? $row['name'] : null;
    }

    /** Matching names for the type-ahead box, "starts with" ranked first. */
    public function suggestions(string $type, string $q, int $limit = 8): array
    {
        if (!in_array($type, self::TYPES, true)) {
            return [];
        }
        $tid = \TenantContext::tenantId();
        $q = trim($q);
        $stmt = $this->db->prepare(
            "SELECT id, name FROM book_attributes
              WHERE tenant_id = ? AND type = ? AND name LIKE ?
           ORDER BY (name LIKE ?) DESC, name ASC
              LIMIT " . (int) $limit
        );
        $stmt->execute([$tid, $type, '%' . $q . '%', $q . '%']);
        return $stmt->fetchAll();
    }

    public function belongsToTenant(int $id, string $type): bool
    {
        if ($id <= 0) {
            return false;
        }
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare('SELECT 1 FROM book_attributes WHERE id = ? AND type = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $type, $tid]);
        return (bool) $stmt->fetchColumn();
    }
}
