<?php
// app/models/Model.php
namespace Models;

use PDO;
use RuntimeException;

/**
 * Base model for all tenant-owned data.
 *
 * The whole point: a model that extends this CANNOT accidentally read or write
 * across tenants. Every query is automatically constrained to the current
 * tenant, and if there is no tenant in context (and the user isn't a platform
 * admin) the query FAILS CLOSED rather than returning everyone's data.
 *
 * A non-tenant table (e.g. subscription_plans) sets $tenantScoped = false.
 */
abstract class Model
{
    protected PDO $db;
    protected string $table;
    protected bool $tenantScoped = true;
    protected string $tenantColumn = 'tenant_id';

    public function __construct(?PDO $db = null)
    {
        // Defaulting to the shared PDO fixes the old `new UserModel()` crash,
        // while still allowing a PDO to be injected for tests.
        $this->db = $db ?? \Database::pdo();
    }

    /**
     * Returns the active tenant id for scoping, or null for an unscoped query.
     * Throws if a tenant-scoped query is attempted with no tenant context and
     * the caller is not a platform admin.
     */
    protected function scopeTenantId(): ?int
    {
        if (!$this->tenantScoped) {
            return null;
        }
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            if (\TenantContext::isPlatformAdmin()) {
                return null; // platform admin may operate across tenants
            }
            throw new RuntimeException(
                "Refusing to run a tenant-scoped query on `{$this->table}` with no tenant context."
            );
        }
        return $tid;
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $params = [':id' => $id];

        if (($tid = $this->scopeTenantId()) !== null) {
            $sql .= " AND {$this->tenantColumn} = :tid";
            $params[':tid'] = $tid;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array $where  column => value equality filters (optional)
     */
    public function all(array $where = [], string $orderBy = 'id DESC'): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $clauses = [];
        $params = [];

        if (($tid = $this->scopeTenantId()) !== null) {
            $clauses[] = "{$this->tenantColumn} = :tid";
            $params[':tid'] = $tid;
        }
        foreach ($where as $col => $val) {
            $ph = ':w_' . $col;
            $clauses[] = "{$col} = {$ph}";
            $params[$ph] = $val;
        }
        if ($clauses) {
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }
        $sql .= ' ORDER BY ' . $orderBy;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function insert(array $data): int
    {
        if ($this->tenantScoped) {
            $tid = \TenantContext::tenantId();
            if ($tid === null && !\TenantContext::isPlatformAdmin()) {
                throw new RuntimeException(
                    "Refusing to insert into `{$this->table}` with no tenant context."
                );
            }
            if ($tid !== null) {
                $data[$this->tenantColumn] = $tid; // force-stamp the tenant
            }
        }

        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $cols),
            implode(', ', $placeholders)
        );
        $stmt = $this->db->prepare($sql);
        foreach ($data as $c => $v) {
            $stmt->bindValue(':' . $c, $v);
        }
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        unset($data['id'], $data[$this->tenantColumn]); // never let these be overwritten

        $sets = [];
        $params = [':id' => $id];
        foreach ($data as $col => $val) {
            $sets[] = "{$col} = :s_{$col}";
            $params[":s_{$col}"] = $val;
        }
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE id = :id";

        if (($tid = $this->scopeTenantId()) !== null) {
            $sql .= " AND {$this->tenantColumn} = :tid";
            $params[':tid'] = $tid;
        }
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $params = [':id' => $id];

        if (($tid = $this->scopeTenantId()) !== null) {
            $sql .= " AND {$this->tenantColumn} = :tid";
            $params[':tid'] = $tid;
        }
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}