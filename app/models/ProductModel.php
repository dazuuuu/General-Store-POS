<?php
// app/models/ProductModel.php
namespace Models;

class ProductModel extends Model
{
    protected string $table = 'products';

    public function __construct(?\PDO $db = null)
    {
        parent::__construct($db);
        $this->ensureSchema();
    }

    public const UNITS = ['piece', 'kg', 'g', 'bale', 'carton', 'pack', 'dozen', 'box', 'ml', 'litre', 'tonne'];
    public const SIZE_UNITS = ['ml', 'l'];
    public const PRODUCT_TYPES = ['product', 'book', 'stationery']; // legacy book/stationery kept for old rows

    /**
     * @param array $in name, category_id, subcategory_id, supplier_id, description,
     *                  quantity, unit, size_value, size_unit, buying_price, wholesale_price,
     *                  retail_price, colors[], sizes[], image_path, low_stock_threshold, status
     */
    public function create(array $in): array
    {
        $errors = $this->validate($in);
        if ($errors) {
            return ['ok' => false, 'id' => null, 'errors' => $errors];
        }
        $id = $this->insert($this->columns($in));
        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    public function edit(int $id, array $in): array
    {
        if (!$this->find($id)) {
            return ['ok' => false, 'errors' => ['_' => 'Product not found.']];
        }
        $errors = $this->validate($in + ['id' => $id]);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }
        $this->update($id, $this->columns($in));
        return ['ok' => true, 'errors' => []];
    }

    /** Assign a system-generated barcode when the product has none yet. */
    public function assignBarcode(int $id): ?string
    {
        $tid = \TenantContext::tenantId();
        $row = $this->find($id);
        if (!$row || (int) $row['tenant_id'] !== (int) $tid) {
            return null;
        }
        if (!empty($row['barcode'])) {
            return $row['barcode'];
        }
        // "ARC" + tenant + zero-padded id keeps it short, unique, and stable —
        // safe to print on a sticker and re-scan later.
        $code = 'ARC' . $tid . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        $this->update($id, ['barcode' => $code]);
        return $code;
    }

    public function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['active', 'draft', 'archived'], true)) {
            return false;
        }
        return $this->update($id, ['status' => $status]);
    }

    public function deleteSafe(int $id): array
    {
        $this->delete($id);
        return ['ok' => true, 'error' => null];
    }

    /** Per-unit profit and margins. */
    public static function profit(float $buying, float $selling): array
    {
        $unit = $selling - $buying;
        return [
            'unit_profit' => round($unit, 2),
            'margin_pct'  => $selling > 0 ? round($unit / $selling * 100, 1) : null,
            'markup_pct'  => $buying > 0 ? round($unit / $buying * 100, 1) : null,
        ];
    }

    /** Stock value at cost (buying price × quantity). */
    public static function stockValue(float $buying, float $quantity): float
    {
        return round($buying * $quantity, 2);
    }

    /**
     * The price to actually charge right now for a retail sale, accounting
     * for a live offer. Single source of truth — every price the customer
     * sees or pays reads through this, so an offer maturing just means the
     * next read of this function naturally falls back to the regular price.
     */
    public static function effectivePrice(array $row): array
    {
        $regular = (float) ($row['retail_price'] ?? $row['selling_price'] ?? 0);
        $offerPrice = $row['offer_price'] ?? null;
        $startsAt = $row['offer_starts_at'] ?? null;
        $endsAt = $row['offer_ends_at'] ?? null;
        $now = time();
        $onOffer = $offerPrice !== null && $offerPrice !== ''
            && !empty($endsAt) && strtotime($endsAt) > $now
            && (empty($startsAt) || strtotime($startsAt) <= $now);
        return [
            'price'         => $onOffer ? (float) $offerPrice : $regular,
            'on_offer'      => $onOffer,
            'regular_price' => $regular,
            'ends_at'       => $onOffer ? $endsAt : null,
        ];
    }

    /** Products grouped by category for the inventory overview. */
    public function listGroupedByCategory(): array
    {
        $rows = $this->listWithMeta();
        $grouped = [];
        foreach ($rows as $p) {
            $key = $p['category_name'] ?: 'Uncategorized';
            $grouped[$key][] = $p;
        }
        ksort($grouped);
        return $grouped;
    }

    /** Products grouped by brand. */
    public function listGroupedByBrand(): array
    {
        $rows = $this->listWithMeta();
        $grouped = [];
        foreach ($rows as $p) {
            $key = $p['brand_name'] ?: ($p['publisher_name'] ?: 'No brand');
            $grouped[$key][] = $p;
        }
        ksort($grouped);
        return $grouped;
    }

    /** Products grouped by receive/sell unit (kg, carton, bale…). */
    public function listGroupedByUnit(): array
    {
        $rows = $this->listWithMeta();
        $grouped = [];
        foreach ($rows as $p) {
            $key = strtoupper((string) ($p['unit'] ?? 'piece'));
            $grouped[$key][] = $p;
        }
        ksort($grouped);
        return $grouped;
    }

    /** Legacy alias → category grouping. */
    public function listGroupedByGrade(): array
    {
        return $this->listGroupedByCategory();
    }

    /** Legacy alias → brand grouping. */
    public function listGroupedByPublisher(): array
    {
        return $this->listGroupedByBrand();
    }

    /** Products grouped by supplier for the inventory overview. */
    public function listGroupedBySupplier(): array
    {
        $rows = $this->listWithMeta();
        $grouped = [];
        foreach ($rows as $p) {
            $key = $p['supplier_name'] ?: 'No supplier';
            $grouped[$key][] = $p;
        }
        ksort($grouped);
        return $grouped;
    }

    /** Legacy stationery grouping → category grouping. */
    public function listGroupedByStationeryCategory(): array
    {
        return $this->listGroupedByCategory();
    }

    /** Active products at or below their restock threshold (for alerts). */
    public function lowStock(int $limit = 100): array
    {
        $tid = \TenantContext::tenantId();
        $st = $this->db->prepare(
            "SELECT p.*, c.name AS category_name
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
              WHERE p.tenant_id = ? AND p.status IN ('active','archived')
                AND p.quantity <= p.low_stock_threshold
              ORDER BY p.quantity ASC, p.name ASC
              LIMIT ?"
        );
        $st->bindValue(1, $tid, \PDO::PARAM_INT);
        $st->bindValue(2, max(1, $limit), \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    /** Sellable stock for the till — category/brand, colors, unit, faulty qty,
     *  and offer/archive flags. Archived items stay sellable from the Archive tab. */
    public function sellable(): array
    {
        $tid = \TenantContext::tenantId();
        $sql = "SELECT p.id, p.name, p.product_type, p.selling_price, p.wholesale_price, p.retail_price,
                       p.offer_price, p.offer_starts_at, p.offer_ends_at,
                       p.quantity, p.faulty_quantity, p.unit, p.units_per_pack, p.pack_unit, p.pack_price,
                       p.credit_limit, p.status, p.barcode, p.colors, p.sizes,
                       p.image_path, p.size_value, p.size_unit,
                       p.category_id, c.name AS category_name,
                       p.publisher_id, pu.name AS publisher_name,
                       p.brand_id, br.name AS brand_name
                  FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN book_attributes pu ON pu.id = p.publisher_id
             LEFT JOIN book_attributes br ON br.id = p.brand_id
                 WHERE p.tenant_id = ? AND p.status IN ('active','archived') AND p.quantity > 0
              ORDER BY p.name ASC";
        $stmt = $this->db->prepare($sql);
        try {
            $stmt->execute([$tid]);
        } catch (\PDOException $e) {
            $stmt = $this->db->prepare(
                "SELECT p.id, p.name, p.product_type, p.selling_price, p.wholesale_price, p.retail_price,
                        p.offer_price, p.offer_starts_at, p.offer_ends_at,
                        p.quantity, p.unit, p.status, p.barcode, p.colors, p.sizes,
                        p.image_path, p.size_value, p.size_unit,
                        p.category_id, c.name AS category_name,
                        p.publisher_id, pu.name AS publisher_name,
                        p.brand_id, br.name AS brand_name
                   FROM products p
              LEFT JOIN categories c ON c.id = p.category_id
              LEFT JOIN book_attributes pu ON pu.id = p.publisher_id
              LEFT JOIN book_attributes br ON br.id = p.brand_id
                  WHERE p.tenant_id = ? AND p.status IN ('active','archived') AND p.quantity > 0
               ORDER BY p.name ASC"
            );
            $stmt->execute([$tid]);
        }
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $eff = self::effectivePrice($r);
            $r['regular_price']   = $eff['regular_price'];
            $r['retail_price']    = $eff['price'];
            $r['on_offer']        = $eff['on_offer'];
            $r['offer_ends_at']   = $eff['ends_at'];
            $r['wholesale_price'] = (float) ($r['wholesale_price'] ?? $r['selling_price'] ?? 0);
            $r['is_archived']     = $r['status'] === 'archived';
            $r['colors']          = $r['colors'] ? (json_decode($r['colors'], true) ?: []) : [];
            $r['sizes']           = $r['sizes'] ? (json_decode($r['sizes'], true) ?: []) : [];
        }
        return $rows;
    }

    /** Product name type-ahead / restock lookup. */
    public function searchByName(string $q, int $limit = 8, string $productType = 'product'): array
    {
        $tid = \TenantContext::tenantId();
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $types = [$productType];
        if ($productType === 'product') {
            $types[] = 'book';
            $types[] = 'stationery';
        }
        $productType = in_array($productType, self::PRODUCT_TYPES, true) ? $productType : 'product';
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $stmt = $this->db->prepare(
            "SELECT p.id, p.name, p.quantity, p.faulty_quantity, p.unit, p.colors, p.buying_price,
                    p.retail_price, p.wholesale_price, p.units_per_pack, p.pack_unit, p.pack_price,
                    p.package_buying_price, p.image_path, p.barcode,
                    c.name AS category_name, c.name AS subject_name,
                    pu.name AS publisher_name, br.name AS brand_name
               FROM products p
          LEFT JOIN categories c ON c.id = p.category_id
          LEFT JOIN book_attributes pu ON pu.id = p.publisher_id
          LEFT JOIN book_attributes br ON br.id = p.brand_id
              WHERE p.tenant_id = ? AND p.product_type IN ($placeholders) AND p.status = ? AND p.name LIKE ?
           ORDER BY (p.name LIKE ?) DESC, p.name ASC
              LIMIT " . (int) $limit
        );
        $params = array_merge([$tid], $types, ['active', '%' . $q . '%', $q . '%']);
        try {
            $stmt->execute($params);
        } catch (\PDOException $e) {
            $stmt = $this->db->prepare(
                "SELECT p.id, p.name, p.quantity, p.unit, p.buying_price, p.image_path, p.barcode,
                        c.name AS category_name, c.name AS subject_name,
                        pu.name AS publisher_name, br.name AS brand_name
                   FROM products p
              LEFT JOIN categories c ON c.id = p.category_id
              LEFT JOIN book_attributes pu ON pu.id = p.publisher_id
              LEFT JOIN book_attributes br ON br.id = p.brand_id
                  WHERE p.tenant_id = ? AND p.product_type IN ($placeholders) AND p.status = ? AND p.name LIKE ?
               ORDER BY (p.name LIKE ?) DESC, p.name ASC
                  LIMIT " . (int) $limit
            );
            $stmt->execute($params);
        }
        return $stmt->fetchAll();
    }

    /** Exact barcode match for scan-to-restock. */
    public function findByBarcode(string $barcode): ?array
    {
        $tid = \TenantContext::tenantId();
        $barcode = trim($barcode);
        if ($barcode === '') {
            return null;
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT p.id, p.name, p.product_type, p.quantity, p.faulty_quantity, p.unit, p.buying_price, p.image_path, p.barcode,
                        c.name AS category_name, c.name AS subject_name,
                        pu.name AS publisher_name, br.name AS brand_name
                   FROM products p
              LEFT JOIN categories c ON c.id = p.category_id
              LEFT JOIN book_attributes pu ON pu.id = p.publisher_id
              LEFT JOIN book_attributes br ON br.id = p.brand_id
                  WHERE p.tenant_id = ? AND p.barcode = ? AND p.status <> ?
                  LIMIT 1'
            );
            $stmt->execute([$tid, $barcode, 'archived']);
        } catch (\PDOException $e) {
            $stmt = $this->db->prepare(
                'SELECT p.id, p.name, p.product_type, p.quantity, p.unit, p.buying_price, p.image_path, p.barcode,
                        c.name AS category_name, c.name AS subject_name,
                        pu.name AS publisher_name, br.name AS brand_name
                   FROM products p
              LEFT JOIN categories c ON c.id = p.category_id
              LEFT JOIN book_attributes pu ON pu.id = p.publisher_id
              LEFT JOIN book_attributes br ON br.id = p.brand_id
                  WHERE p.tenant_id = ? AND p.barcode = ? AND p.status <> ?
                  LIMIT 1'
            );
            $stmt->execute([$tid, $barcode, 'archived']);
        }
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** All active products with category names — public catalogue uses retail price. */
    public function catalogueForTenant(int $tenantId): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.id, p.name,
                    COALESCE(NULLIF(p.retail_price, 0), p.selling_price) AS selling_price,
                    p.image_path, p.description, p.unit, p.size_value, p.size_unit,
                    c.name AS category_name, s.name AS subcategory_name
               FROM products p
          LEFT JOIN categories c  ON c.id = p.category_id
          LEFT JOIN subcategories s ON s.id = p.subcategory_id
              WHERE p.tenant_id = ? AND p.status = 'active'
           ORDER BY p.name ASC"
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }

    /** Shared JOINs for category / brand / supplier (legacy publisher kept for old rows). */
    private const META_JOIN_SQL = "
               FROM products p
          LEFT JOIN categories c ON c.id = p.category_id
          LEFT JOIN subcategories s ON s.id = p.subcategory_id
          LEFT JOIN suppliers sup ON sup.id = p.supplier_id
          LEFT JOIN book_attributes g  ON g.id  = p.grade_id
          LEFT JOIN book_attributes pu ON pu.id = p.publisher_id
          LEFT JOIN book_attributes au ON au.id = p.author_id
          LEFT JOIN book_attributes ed ON ed.id = p.edition_id
          LEFT JOIN book_attributes br ON br.id = p.brand_id";

    private const META_SELECT_SQL = "p.*, c.name AS category_name, s.name AS subcategory_name,
                    sup.name AS supplier_name, g.name AS grade_name, pu.name AS publisher_name,
                    au.name AS author_name, ed.name AS edition_name, br.name AS brand_name";

    /** Products with category/brand/supplier names for inventory listing. */
    public function listWithMeta(bool $includeArchived = false, ?string $productType = null): array
    {
        $tid = \TenantContext::tenantId();
        $params = [$tid];
        $sql = 'SELECT ' . self::META_SELECT_SQL . self::META_JOIN_SQL . '
              WHERE p.tenant_id = ?' . ($includeArchived ? '' : " AND p.status <> 'archived'");
        if ($productType !== null) {
            $sql .= ' AND p.product_type = ?';
            $params[] = $productType;
        }
        $sql .= ' ORDER BY p.name ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['retail_price'] = (float) ($r['retail_price'] ?? $r['selling_price'] ?? 0);
            $r['wholesale_price'] = (float) ($r['wholesale_price'] ?? $r['selling_price'] ?? 0);
        }
        return $rows;
    }

    /** Archived titles only — the Inventory page's "Archived" view. */
    public function listArchived(): array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            'SELECT ' . self::META_SELECT_SQL . self::META_JOIN_SQL . "
              WHERE p.tenant_id = ? AND p.status = 'archived'
           ORDER BY p.name ASC"
        );
        $stmt->execute([$tid]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['retail_price'] = (float) ($r['retail_price'] ?? $r['selling_price'] ?? 0);
            $r['wholesale_price'] = (float) ($r['wholesale_price'] ?? $r['selling_price'] ?? 0);
        }
        return $rows;
    }

    /** One product with the same names joined in, for prefilling the edit form. */
    public function findWithMeta(int $id): ?array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            'SELECT ' . self::META_SELECT_SQL . self::META_JOIN_SQL . '
              WHERE p.id = ? AND p.tenant_id = ?
              LIMIT 1'
        );
        $stmt->execute([$id, $tid]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ---- internals ----

    private function ensureSchema(): void
    {
        $checks = [
            'offer_price' => "ALTER TABLE `products` ADD COLUMN `offer_price` DECIMAL(12,2) NULL AFTER `retail_price`",
            'offer_starts_at' => "ALTER TABLE `products` ADD COLUMN `offer_starts_at` DATETIME NULL AFTER `offer_price`",
            'offer_ends_at' => "ALTER TABLE `products` ADD COLUMN `offer_ends_at` DATETIME NULL AFTER `offer_starts_at`",
            'barcode' => "ALTER TABLE `products` ADD COLUMN `barcode` VARCHAR(64) NULL AFTER `edition_id`",
            'grade_id' => "ALTER TABLE `products` ADD COLUMN `grade_id` INT NULL AFTER `subcategory_id`",
            'publisher_id' => "ALTER TABLE `products` ADD COLUMN `publisher_id` INT NULL AFTER `grade_id`",
            'author_id' => "ALTER TABLE `products` ADD COLUMN `author_id` INT NULL AFTER `publisher_id`",
            'edition_id' => "ALTER TABLE `products` ADD COLUMN `edition_id` INT NULL AFTER `author_id`",
            'product_type' => "ALTER TABLE `products` ADD COLUMN `product_type` ENUM('book','stationery','product') NOT NULL DEFAULT 'product' AFTER `tenant_id`",
            'brand_id' => "ALTER TABLE `products` ADD COLUMN `brand_id` INT NULL AFTER `edition_id`",
            'credit_limit' => "ALTER TABLE `products` ADD COLUMN `credit_limit` DECIMAL(12,2) NULL AFTER `low_stock_threshold`",
            'units_per_pack' => "ALTER TABLE `products` ADD COLUMN `units_per_pack` DECIMAL(12,2) NOT NULL DEFAULT 1.00 AFTER `unit`",
            'pack_unit' => "ALTER TABLE `products` ADD COLUMN `pack_unit` VARCHAR(20) NULL AFTER `units_per_pack`",
            'pack_price' => "ALTER TABLE `products` ADD COLUMN `pack_price` DECIMAL(12,2) NULL AFTER `pack_unit`",
            'package_buying_price' => "ALTER TABLE `products` ADD COLUMN `package_buying_price` DECIMAL(12,2) NULL AFTER `pack_price`",
            'faulty_quantity' => "ALTER TABLE `products` ADD COLUMN `faulty_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `quantity`",
        ];

        foreach ($checks as $column => $sql) {
            try {
                $this->db->query("SELECT `{$column}` FROM `products` LIMIT 1");
            } catch (\PDOException $e) {
                try {
                    $this->db->exec($sql);
                } catch (\PDOException $ignored) {
                    // Ignore if the column already exists or the table is missing.
                }
            }
        }
        try {
            $this->db->exec("ALTER TABLE `products` MODIFY COLUMN `product_type` ENUM('book','stationery','product') NOT NULL DEFAULT 'product'");
        } catch (\PDOException $ignored) {}
    }

    private function validate(array $in): array
    {
        $errors = [];
        if (trim($in['name'] ?? '') === '') {
            $errors['name'] = 'Product name is required.';
        }
        $barcode = trim((string) ($in['barcode'] ?? ''));
        if ($barcode !== '') {
            if (strlen($barcode) > 64) {
                $errors['barcode'] = 'That barcode is too long.';
            } elseif ($this->barcodeTakenByAnother($barcode, (int) ($in['id'] ?? 0))) {
                $errors['barcode'] = 'Another product already has this barcode.';
            }
        }
        $catId = (int) ($in['category_id'] ?? 0);
        if ($catId > 0 && !$this->categoryBelongsToTenant($catId)) {
            $errors['category_id'] = 'Choose a valid category.';
        }
        $subId = (int) ($in['subcategory_id'] ?? 0);
        if ($subId > 0) {
            if (!$this->subcategoryBelongsToTenant($subId)) {
                $errors['subcategory_id'] = 'Choose a valid subcategory.';
            } elseif ($catId > 0 && !$this->subcategoryBelongsToCategory($subId, $catId)) {
                $errors['subcategory_id'] = 'That subcategory is not in the chosen category.';
            }
        }
        $unit = $in['unit'] ?? 'piece';
        if (!in_array($unit, self::UNITS, true)) {
            $errors['unit'] = 'Choose a valid unit.';
        }
        $supplierId = (int) ($in['supplier_id'] ?? 0);
        if ($supplierId > 0 && !$this->supplierBelongsToTenant($supplierId)) {
            $errors['supplier_id'] = 'Choose a valid supplier.';
        }
        $attrModel = new BookAttributeModel($this->db);
        foreach (['grade_id' => 'grade', 'publisher_id' => 'publisher', 'author_id' => 'author', 'edition_id' => 'edition', 'brand_id' => 'brand'] as $field => $type) {
            $val = (int) ($in[$field] ?? 0);
            if ($val > 0 && !$attrModel->belongsToTenant($val, $type)) {
                $errors[$field] = 'Choose a valid ' . $type . '.';
            }
        }
        $productType = $in['product_type'] ?? 'product';
        if (!in_array($productType, self::PRODUCT_TYPES, true)) {
            $errors['product_type'] = 'Invalid product type.';
        }
        if (isset($in['credit_limit']) && $in['credit_limit'] !== '' && (!is_numeric($in['credit_limit']) || (float) $in['credit_limit'] < 0)) {
            $errors['credit_limit'] = 'Enter a valid credit limit.';
        }
        if (isset($in['units_per_pack']) && $in['units_per_pack'] !== '' && (!is_numeric($in['units_per_pack']) || (float) $in['units_per_pack'] <= 0)) {
            $errors['units_per_pack'] = 'Enter a valid pack size.';
        }
        if (isset($in['faulty_quantity']) && $in['faulty_quantity'] !== '' && (!is_numeric($in['faulty_quantity']) || (float) $in['faulty_quantity'] < 0)) {
            $errors['faulty_quantity'] = 'Enter a valid faulty quantity.';
        }
        $sizeValue = $in['size_value'] ?? '';
        if ($sizeValue !== '' && (!is_numeric($sizeValue) || (float) $sizeValue <= 0)) {
            $errors['size_value'] = 'Enter a valid size.';
        }
        $sizeUnit = $in['size_unit'] ?? '';
        if ($sizeValue !== '' && !in_array($sizeUnit, self::SIZE_UNITS, true)) {
            $errors['size_unit'] = 'Choose ML or L.';
        }
        $hasBuying = ($in['buying_price'] ?? '') !== '';
        $hasPackageBuying = ($in['package_buying_price'] ?? '') !== '';
        $unitsPerPackIn = (float) ($in['units_per_pack'] ?? 1);
        $hasPack = trim((string) ($in['pack_unit'] ?? '')) !== '' && $unitsPerPackIn > 1;
        if ((!$hasBuying && !$hasPackageBuying) || ($hasBuying && (!is_numeric($in['buying_price']) || (float) $in['buying_price'] < 0))) {
            $errors['buying_price'] = 'Enter a valid buying price.';
        }
        if ($hasPackageBuying && (!is_numeric($in['package_buying_price']) || (float) $in['package_buying_price'] < 0)) {
            $errors['package_buying_price'] = 'Enter a valid package buying price.';
        }
        $wholesaleIn = $in['wholesale_price'] ?? '';
        if ($wholesaleIn !== '' && (!is_numeric($wholesaleIn) || (float) $wholesaleIn < 0)) {
            $errors['wholesale_price'] = 'Enter a valid wholesale price.';
        }
        if ($hasPack) {
            if (!$hasPackageBuying || (float) ($in['package_buying_price'] ?? 0) <= 0) {
                $errors['package_buying_price'] = 'Buying price of the package is required.';
            }
            if (($in['pack_price'] ?? '') === '' || !is_numeric($in['pack_price']) || (float) $in['pack_price'] <= 0) {
                $errors['pack_price'] = 'Selling price of the package (wholesale price) is required.';
            }
            if (($in['units_per_pack'] ?? '') === '' || !is_numeric($in['units_per_pack']) || (float) $in['units_per_pack'] <= 1) {
                $errors['units_per_pack'] = 'Enter how many items are inside each package.';
            }
        } elseif ((int) ($in['id'] ?? 0) <= 0) {
            // New products must be recorded as packages (carton/bale/pack…).
            $errors['pack_unit'] = 'Choose a package type (carton, bale, pack, dozen, box…).';
            $errors['units_per_pack'] = 'Enter how many items are inside each package.';
            $errors['package_buying_price'] = 'Buying price of the package is required.';
            $errors['pack_price'] = 'Wholesale package selling price is required.';
        }
        $isNew = (int) ($in['id'] ?? 0) <= 0;
        if (!is_numeric($in['retail_price'] ?? null) || (float) ($in['retail_price'] ?? -1) < 0
            || ($isNew && (float) ($in['retail_price'] ?? 0) <= 0)) {
            $errors['retail_price'] = $isNew
                ? 'Enter the retail price of a single item inside the package.'
                : 'Enter a valid retail price.';
        }
        if (!is_numeric($in['quantity'] ?? null) || (float) $in['quantity'] < 0) {
            $errors['quantity'] = 'Enter a valid quantity.';
        }
        $offerPriceIn = $in['offer_price'] ?? '';
        if ($offerPriceIn !== '') {
            if (!is_numeric($offerPriceIn) || (float) $offerPriceIn < 0) {
                $errors['offer_price'] = 'Enter a valid offer price.';
            }
            if (empty($in['offer_ends_at'])) {
                $errors['offer_ends_at'] = 'Set when the offer ends.';
            } elseif (strtotime($in['offer_ends_at']) === false) {
                $errors['offer_ends_at'] = 'Enter a valid end date/time.';
            }
            if (!empty($in['offer_starts_at']) && strtotime($in['offer_starts_at']) === false) {
                $errors['offer_starts_at'] = 'Enter a valid start date/time.';
            }
            if (!empty($in['offer_ends_at']) && !empty($in['offer_starts_at'])
                && strtotime($in['offer_ends_at']) !== false && strtotime($in['offer_starts_at']) !== false
                && strtotime($in['offer_ends_at']) <= strtotime($in['offer_starts_at'])) {
                $errors['offer_ends_at'] = 'Offer end must be after its start.';
            }
        }
        return $errors;
    }

    private function columns(array $in): array
    {
        $subId = (int) ($in['subcategory_id'] ?? 0);
        $catId = (int) ($in['category_id'] ?? 0);
        if ($subId > 0 && $catId <= 0) {
            $catId = $this->subcategoryParent($subId);
        }
        $colors = array_values(array_filter(array_map('trim', (array) ($in['colors'] ?? []))));
        $sizes  = array_values(array_filter(array_map('trim', (array) ($in['sizes'] ?? []))));
        $status = $in['status'] ?? 'active';
        $status = in_array($status, ['active', 'draft', 'archived'], true) ? $status : 'active';
        $retail = (float) ($in['retail_price'] ?? $in['selling_price'] ?? 0);
        $sizeValue = ($in['size_value'] ?? '') !== '' ? (float) $in['size_value'] : null;
        $sizeUnit  = $sizeValue !== null ? ($in['size_unit'] ?? null) : null;
        // Clearing the offer price clears the whole offer (start/end go with it).
        $offerPrice  = ($in['offer_price'] ?? '') !== '' ? (float) $in['offer_price'] : null;
        $offerStarts = $offerPrice !== null && !empty($in['offer_starts_at']) ? date('Y-m-d H:i:s', strtotime($in['offer_starts_at'])) : null;
        $offerEnds   = $offerPrice !== null && !empty($in['offer_ends_at']) ? date('Y-m-d H:i:s', strtotime($in['offer_ends_at'])) : null;
        $productTypeIn = $in['product_type'] ?? 'product';
        $productType = in_array($productTypeIn, self::PRODUCT_TYPES, true) ? $productTypeIn : 'product';
        $creditLimit = ($in['credit_limit'] ?? '') !== '' ? (float) $in['credit_limit'] : null;
        $unitsPerPack = max(0.01, (float) ($in['units_per_pack'] ?? 1));
        $packUnit = trim((string) ($in['pack_unit'] ?? '')) ?: null;
        $packPrice = ($in['pack_price'] ?? '') !== '' ? (float) $in['pack_price'] : null;
        // For packaged products the wholesale selling price is the package
        // price; this per-content value is derived for legacy line-item math.
        $wholesale = ($packUnit !== null && $packPrice !== null && $unitsPerPack > 1)
            ? round($packPrice / $unitsPerPack, 2)
            : (($in['wholesale_price'] ?? '') !== '' ? (float) $in['wholesale_price'] : $retail);
        $packageBuying = ($in['package_buying_price'] ?? '') !== '' ? (float) $in['package_buying_price'] : null;
        $buyingPrice = (float) ($in['buying_price'] ?? 0);
        if ($packageBuying !== null && $packUnit !== null && $unitsPerPack > 0) {
            $buyingPrice = round($packageBuying / $unitsPerPack, 2);
        }
        return [
            'product_type'        => $productType,
            'category_id'         => $catId > 0 ? $catId : null,
            'subcategory_id'      => $subId > 0 ? $subId : null,
            'supplier_id'         => ((int) ($in['supplier_id'] ?? 0)) > 0 ? (int) $in['supplier_id'] : null,
            'grade_id'            => ((int) ($in['grade_id'] ?? 0)) > 0 ? (int) $in['grade_id'] : null,
            'publisher_id'        => ((int) ($in['publisher_id'] ?? 0)) > 0 ? (int) $in['publisher_id'] : null,
            'author_id'           => ((int) ($in['author_id'] ?? 0)) > 0 ? (int) $in['author_id'] : null,
            'edition_id'          => ((int) ($in['edition_id'] ?? 0)) > 0 ? (int) $in['edition_id'] : null,
            'brand_id'            => ((int) ($in['brand_id'] ?? 0)) > 0 ? (int) $in['brand_id'] : null,
            'barcode'             => trim((string) ($in['barcode'] ?? '')) !== '' ? trim((string) $in['barcode']) : null,
            'name'                => trim($in['name']),
            'description'         => ($in['description'] ?? '') !== '' ? trim($in['description']) : null,
            'quantity'            => (float) ($in['quantity'] ?? 0),
            'faulty_quantity'     => max(0, (float) ($in['faulty_quantity'] ?? 0)),
            'unit'                => $in['unit'] ?? 'piece',
            'units_per_pack'      => $unitsPerPack,
            'pack_unit'           => $packUnit,
            'pack_price'          => $packPrice,
            'size_value'          => $sizeValue,
            'size_unit'           => $sizeUnit,
            'buying_price'        => $buyingPrice,
            'package_buying_price' => $packageBuying,
            'selling_price'       => $retail,
            'wholesale_price'     => $wholesale,
            'retail_price'        => $retail,
            'offer_price'         => $offerPrice,
            'offer_starts_at'     => $offerStarts,
            'offer_ends_at'       => $offerEnds,
            'colors'              => $colors ? json_encode($colors) : null,
            'sizes'               => $sizes ? json_encode($sizes) : null,
            'image_path'          => ($in['image_path'] ?? '') !== '' ? $in['image_path'] : null,
            'low_stock_threshold' => (int) ($in['low_stock_threshold'] ?? 10),
            'credit_limit'        => $creditLimit,
            'status'              => $status,
        ];
    }

    /** Human-friendly "500ml" / "1L" label, or '' if no size is set. */
    public static function sizeLabel(array $row): string
    {
        if (empty($row['size_value'])) {
            return '';
        }
        $v = rtrim(rtrim(number_format((float) $row['size_value'], 2), '0'), '.');
        return $v . strtoupper((string) ($row['size_unit'] ?? ''));
    }

    private function categoryBelongsToTenant(int $categoryId): bool
    {
        if ($categoryId <= 0) { return false; }
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare('SELECT 1 FROM categories WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$categoryId, $tid]);
        return (bool) $stmt->fetchColumn();
    }

    private function subcategoryBelongsToCategory(int $subId, int $categoryId): bool
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare('SELECT 1 FROM subcategories WHERE id = ? AND category_id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$subId, $categoryId, $tid]);
        return (bool) $stmt->fetchColumn();
    }

    private function subcategoryBelongsToTenant(int $subId): bool
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare('SELECT 1 FROM subcategories WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$subId, $tid]);
        return (bool) $stmt->fetchColumn();
    }

    private function subcategoryParent(int $subId): int
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare('SELECT category_id FROM subcategories WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$subId, $tid]);
        return (int) $stmt->fetchColumn();
    }

    private function barcodeTakenByAnother(string $barcode, int $excludeId): bool
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare('SELECT 1 FROM products WHERE tenant_id = ? AND barcode = ? AND id <> ? LIMIT 1');
        $stmt->execute([$tid, $barcode, $excludeId]);
        return (bool) $stmt->fetchColumn();
    }

    private function supplierBelongsToTenant(int $supplierId): bool
    {
        if ($supplierId <= 0) { return false; }
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare('SELECT 1 FROM suppliers WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$supplierId, $tid]);
        return (bool) $stmt->fetchColumn();
    }
}
