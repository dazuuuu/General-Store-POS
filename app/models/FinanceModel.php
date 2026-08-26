<?php
// Standalone expenses (and optional revenue entries) for Finances + Expenses pages.
namespace Models;

class FinanceModel extends Model
{
    protected string $table = 'finance_entries';

    private static bool $schemaReady = false;

    public function __construct(?\PDO $db = null)
    {
        parent::__construct($db);
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        self::$schemaReady = true;
        try {
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS finance_entries (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    entry_type ENUM('revenue','expense') NOT NULL,
                    category VARCHAR(120) NOT NULL DEFAULT '',
                    description VARCHAR(255) NOT NULL DEFAULT '',
                    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    payment_method VARCHAR(40) NULL,
                    reference VARCHAR(120) NULL,
                    entry_date DATE NOT NULL,
                    created_by INT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_finance_tenant_date (tenant_id, entry_date),
                    KEY idx_finance_tenant_type (tenant_id, entry_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (\PDOException $ignored) {
        }
    }

    /** @return array{ok:bool,id:?int,errors:array} */
    public function create(array $in): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            return ['ok' => false, 'id' => null, 'errors' => ['_' => 'No shop in context.']];
        }
        $type = ($in['entry_type'] ?? '') === 'expense' ? 'expense' : 'revenue';
        $amount = round(max(0, (float) ($in['amount'] ?? 0)), 2);
        $category = trim((string) ($in['category'] ?? ''));
        $description = trim((string) ($in['description'] ?? ''));
        $date = trim((string) ($in['entry_date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        if ($amount <= 0) {
            return ['ok' => false, 'id' => null, 'errors' => ['amount' => 'Enter an amount greater than zero.']];
        }
        if ($category === '') {
            $category = $type === 'expense' ? 'General expense' : 'Other revenue';
        }
        $id = $this->insert([
            'tenant_id' => $tid,
            'entry_type' => $type,
            'category' => $category,
            'description' => $description,
            'amount' => $amount,
            'payment_method' => trim((string) ($in['payment_method'] ?? '')) ?: null,
            'reference' => trim((string) ($in['reference'] ?? '')) ?: null,
            'entry_date' => $date,
            'created_by' => (int) ($in['created_by'] ?? 0) ?: null,
        ]);
        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    public function deleteEntry(int $id): bool
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null || $id <= 0) {
            return false;
        }
        $st = $this->db->prepare('DELETE FROM finance_entries WHERE id = ? AND tenant_id = ?');
        $st->execute([$id, $tid]);
        return $st->rowCount() > 0;
    }

    /** @return list<array> */
    public function forTenant(string $type = 'all', string $period = 'all', int $limit = 500): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            return [];
        }
        $sql = 'SELECT f.*, u.username AS created_by_name
                  FROM finance_entries f
             LEFT JOIN users u ON u.id = f.created_by
                 WHERE f.tenant_id = ?';
        $params = [$tid];
        if ($type === 'revenue' || $type === 'expense') {
            $sql .= ' AND f.entry_type = ?';
            $params[] = $type;
        }
        $periodSql = $this->periodSql($period, 'f.entry_date');
        if ($periodSql !== '') {
            $sql .= ' AND ' . $periodSql;
        }
        $sql .= ' ORDER BY f.entry_date DESC, f.id DESC LIMIT ' . max(1, min(1000, $limit));
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll() ?: [];
    }

    /** @return array{revenue:float,expense:float,count_revenue:int,count_expense:int} */
    public function summarize(string $period = 'all'): array
    {
        $rows = $this->forTenant('all', $period, 1000);
        $out = ['revenue' => 0.0, 'expense' => 0.0, 'count_revenue' => 0, 'count_expense' => 0];
        foreach ($rows as $r) {
            $amt = (float) ($r['amount'] ?? 0);
            if (($r['entry_type'] ?? '') === 'expense') {
                $out['expense'] += $amt;
                $out['count_expense']++;
            } else {
                $out['revenue'] += $amt;
                $out['count_revenue']++;
            }
        }
        $out['revenue'] = round($out['revenue'], 2);
        $out['expense'] = round($out['expense'], 2);
        return $out;
    }

    private function periodSql(string $period, string $col): string
    {
        if ($period === 'today') {
            return "DATE({$col}) = CURDATE()";
        }
        if ($period === 'week') {
            return "{$col} >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        }
        if ($period === 'month') {
            return "{$col} >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        }
        return '';
    }
}
