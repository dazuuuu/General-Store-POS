<?php
// app/models/AuditLogModel.php
namespace Models;

/**
 * Tenant-scoped activity log. Extends the base Model so tenant stamping and
 * fail-closed scoping are inherited — an entry can never be written or read
 * across tenants.
 *
 * Records ONLY what passes through the app (this is an application-level trail,
 * not a database trigger): direct DB edits won't appear here.
 */
class AuditLogModel extends Model
{
    protected string $table = 'audit_log';

    /** Fields we diff on a product edit, with human labels + how to display them. */
    public const PRODUCT_FIELDS = [
        'name'                => 'Product name',
        'category_id'         => 'Category',
        'brand_id'            => 'Brand',
        'colors'              => 'Colors',
        'barcode'             => 'Barcode',
        'description'         => 'Description',
        'quantity'            => 'Good qty',
        'faulty_quantity'     => 'Faulty / broken',
        'unit'                => 'Unit',
        'buying_price'        => 'Unit price (cost)',
        'wholesale_price'     => 'Wholesale price',
        'retail_price'        => 'Selling price',
        'offer_price'         => 'Offer price',
        'offer_ends_at'       => 'Offer ends',
        'low_stock_threshold' => 'Restock alert',
        'status'              => 'Status',
        'image_path'          => 'Photo',
    ];

    /**
     * Write one entry. Actor (user/role) is taken from server-side context,
     * never from the caller, so attribution can't be forged from a form.
     */
    public function record(string $entityType, ?int $entityId, ?string $label, string $action, array $changes = []): void
    {
        $this->insert([
            'user_id'      => \TenantContext::userId(),
            'username'     => $_SESSION['username'] ?? null,
            'role'         => \TenantContext::role(),
            'entity_type'  => $entityType,
            'entity_id'    => $entityId,
            'entity_label' => $label !== null ? substr($label, 0, 200) : null,
            'action'       => $action,
            'changes'      => $changes ? json_encode(array_values($changes)) : null,
        ]);
    }

    /**
     * Compute a human-readable diff between two associative rows.
     * Returns [['field','label','from','to'], ...] for changed fields only.
     * Values are compared as trimmed strings so "10" vs "10.00" or null vs ''
     * don't register as spurious changes.
     */
    public static function diff(array $before, array $after, array $fields): array
    {
        $norm = static function ($v): string {
            if ($v === null) { return ''; }
            if (is_bool($v)) { return $v ? '1' : '0'; }
            // Normalise numeric strings so 120 vs 120.00 are equal.
            if (is_numeric($v)) {
                $f = (float) $v;
                return rtrim(rtrim(number_format($f, 4, '.', ''), '0'), '.');
            }
            return trim((string) $v);
        };
        $out = [];
        foreach ($fields as $key => $label) {
            $b = $before[$key] ?? null;
            $a = $after[$key]  ?? null;
            if ($norm($b) === $norm($a)) { continue; }
            $out[] = [
                'field' => $key,
                'label' => $label,
                'from'  => $b === null || $b === '' ? '—' : (string) $b,
                'to'    => $a === null || $a === '' ? '—' : (string) $a,
            ];
        }
        return $out;
    }

    /** Recent entries for the current tenant, newest first. */
    public function recent(string $entityType = '', int $limit = 60): array
    {
        $tid = $this->scopeTenantId();
        $sql = "SELECT * FROM {$this->table} WHERE tenant_id = :tid";
        $params = [':tid' => $tid];
        if ($entityType !== '') {
            $sql .= " AND entity_type = :et";
            $params[':et'] = $entityType;
        }
        $sql .= " ORDER BY created_at DESC, id DESC LIMIT :lim";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tid', $tid, \PDO::PARAM_INT);
        if ($entityType !== '') { $stmt->bindValue(':et', $entityType); }
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
