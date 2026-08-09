<?php
// app/models/SupplierModel.php
namespace Models;

class SupplierModel extends Model
{
    protected string $table = 'suppliers';

    public function create(string $name, ?string $phone = null, ?string $notes = null): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'id' => null, 'error' => 'Supplier name is required.'];
        }
        if (strlen($name) > 160) {
            return ['ok' => false, 'id' => null, 'error' => 'Supplier name is too long.'];
        }
        if ($this->nameTaken($name)) {
            return ['ok' => false, 'id' => null, 'error' => 'You already have a supplier with that name.'];
        }
        try {
            $id = $this->insert([
                'name'  => $name,
                'phone' => ($phone !== null && trim($phone) !== '') ? trim($phone) : null,
                'notes' => ($notes !== null && trim($notes) !== '') ? trim($notes) : null,
            ]);
            return ['ok' => true, 'id' => $id, 'error' => null];
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'id' => null, 'error' => 'You already have a supplier with that name.'];
            }
            throw $e;
        }
    }

    public function nameTaken(string $name, ?int $exceptId = null): bool
    {
        foreach ($this->all(['name' => trim($name)]) as $row) {
            if ($exceptId === null || (int) $row['id'] !== $exceptId) {
                return true;
            }
        }
        return false;
    }

    /** Reuse the existing supplier for this name, or create one. Blank name -> null. */
    public function findOrCreate(string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $rows = $this->all(['name' => $name]);
        if ($rows) {
            return (int) $rows[0]['id'];
        }
        $res = $this->create($name);
        if ($res['ok']) {
            return (int) $res['id'];
        }
        // Lost a create race — the row that won it now exists.
        $rows = $this->all(['name' => $name]);
        return $rows ? (int) $rows[0]['id'] : null;
    }

    /** Matching names for the type-ahead box, "starts with" ranked first. */
    public function suggestions(string $q, int $limit = 8): array
    {
        $tid = \TenantContext::tenantId();
        $q = trim($q);
        $stmt = $this->db->prepare(
            "SELECT id, name FROM suppliers
              WHERE tenant_id = ? AND name LIKE ?
           ORDER BY (name LIKE ?) DESC, name ASC
              LIMIT " . (int) $limit
        );
        $stmt->execute([$tid, '%' . $q . '%', $q . '%']);
        return $stmt->fetchAll();
    }

    /** Suppliers with a count of products currently attributed to them. */
    public function listWithCounts(): array
    {
        $suppliers = $this->all([], 'name ASC');
        if (!$suppliers) {
            return [];
        }
        $tid = \TenantContext::tenantId();
        $ids = array_column($suppliers, 'id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT supplier_id, COUNT(*) c FROM products WHERE tenant_id = ? AND supplier_id IN ($in) GROUP BY supplier_id"
        );
        $stmt->execute(array_merge([$tid], $ids));
        $counts = [];
        foreach ($stmt->fetchAll() as $r) { $counts[(int) $r['supplier_id']] = (int) $r['c']; }
        foreach ($suppliers as &$s) { $s['product_count'] = $counts[(int) $s['id']] ?? 0; }
        return $suppliers;
    }
}
