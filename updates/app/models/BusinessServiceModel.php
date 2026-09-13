<?php
namespace Models;

/**
 * Tenant-owned sellable services and appointment/service invoices.
 * Kept separate from the legacy website/CMS `services` table.
 */
class BusinessServiceModel extends Model
{
    protected string $table = 'business_services';

    public function __construct(?\PDO $db = null)
    {
        parent::__construct($db);
        $this->ensureSchema();
    }

    public function allActive(): array
    {
        $st = $this->db->prepare(
            'SELECT * FROM business_services WHERE tenant_id = ? AND status = ? ORDER BY name'
        );
        $st->execute([\TenantContext::tenantId(), 'active']);
        return $st->fetchAll();
    }

    public function save(array $in, int $userId): array
    {
        $tid = \TenantContext::tenantId();
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'error' => 'Enter the service name.'];
        }
        $mergedIds = array_values(array_unique(array_filter(array_map('intval', (array) ($in['merged_service_ids'] ?? [])))));
        $price = max(0, (float) ($in['price'] ?? 0));
        $duration = max(1, (int) ($in['duration_minutes'] ?? 30));
        $commission = max(0, (float) ($in['commission_amount'] ?? 0));
        if ($mergedIds) {
            $inSql = implode(',', array_fill(0, count($mergedIds), '?'));
            $st = $this->db->prepare(
                "SELECT COALESCE(SUM(price),0) price, COALESCE(SUM(duration_minutes),0) duration,
                        COALESCE(SUM(commission_amount),0) commission
                   FROM business_services WHERE tenant_id = ? AND id IN ($inSql)"
            );
            $st->execute(array_merge([$tid], $mergedIds));
            $sum = $st->fetch() ?: [];
            if ($price <= 0) $price = (float) ($sum['price'] ?? 0);
            if (empty($in['duration_minutes'])) $duration = max(1, (int) ($sum['duration'] ?? 30));
            if (empty($in['commission_amount'])) $commission = (float) ($sum['commission'] ?? 0);
        }
        $id = $this->insert([
            'tenant_id' => $tid,
            'name' => $name,
            'description' => trim((string) ($in['description'] ?? '')) ?: null,
            'price' => round($price, 2),
            'commission_amount' => round($commission, 2),
            'duration_minutes' => $duration,
            'merged_service_ids' => $mergedIds ? json_encode($mergedIds) : null,
            'status' => 'active',
            'created_by' => $userId ?: null,
        ]);
        return ['ok' => true, 'id' => $id, 'error' => null];
    }

    /**
     * Create either a walk-in service invoice or a scheduled appointment invoice.
     * Price may be raised but never below the catalog price; all extra is commission.
     */
    public function createBooking(array $in, int $creatorId): array
    {
        // Ensure finance table exists before opening a transaction (MySQL DDL commits transactions).
        new FinanceModel($this->db);
        $tid = \TenantContext::tenantId();
        $serviceIds = array_values(array_unique(array_filter(array_map('intval', array_keys((array) ($in['services'] ?? []))))));
        if (!$serviceIds) return ['ok' => false, 'error' => 'Choose at least one service.'];
        $type = ($in['booking_type'] ?? '') === 'appointment' ? 'appointment' : 'walkin';
        $scheduled = $type === 'appointment' ? trim((string) ($in['scheduled_at'] ?? '')) : date('Y-m-d H:i:s');
        if ($type === 'appointment' && (!$scheduled || strtotime($scheduled) === false || strtotime($scheduled) < time())) {
            return ['ok' => false, 'error' => 'Choose a future appointment date and time.'];
        }
        $customer = trim((string) ($in['customer_name'] ?? '')) ?: 'Walk-in Customer';
        $staffId = (int) ($in['staff_id'] ?? 0) ?: $creatorId;

        try {
            $this->db->beginTransaction();
            $inSql = implode(',', array_fill(0, count($serviceIds), '?'));
            $st = $this->db->prepare(
                "SELECT * FROM business_services WHERE tenant_id = ? AND status = 'active' AND id IN ($inSql) FOR UPDATE"
            );
            $st->execute(array_merge([$tid], $serviceIds));
            $catalog = [];
            foreach ($st->fetchAll() as $service) $catalog[(int) $service['id']] = $service;
            if (count($catalog) !== count($serviceIds)) throw new \RuntimeException('One selected service is unavailable.');

            $items = [];
            $subtotal = 0.0;
            $commissionTotal = 0.0;
            foreach ($serviceIds as $sid) {
                $service = $catalog[$sid];
                $row = (array) ($in['services'][$sid] ?? []);
                $qty = max(1, (float) ($row['quantity'] ?? 1));
                $base = (float) $service['price'];
                $unitPrice = ($row['unit_price'] ?? '') !== '' ? (float) $row['unit_price'] : $base;
                if ($unitPrice + 0.0001 < $base) {
                    throw new \RuntimeException($service['name'] . ' cannot be sold below KES ' . number_format($base, 2) . '.');
                }
                $extraCommission = max(0, ($unitPrice - $base) * $qty);
                $commission = ((float) $service['commission_amount'] * $qty) + $extraCommission;
                $line = round($unitPrice * $qty, 2);
                $subtotal += $line;
                $commissionTotal += $commission;
                $items[] = compact('service', 'qty', 'base', 'unitPrice', 'commission', 'line');
            }

            $this->db->prepare(
                "INSERT INTO service_appointments
                    (tenant_id, invoice_number, booking_type, customer_name, customer_phone, customer_email,
                     scheduled_at, status, payment_status, subtotal, total, commission_total, staff_id, created_by, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $tid, 'PENDING', $type, $customer,
                trim((string) ($in['customer_phone'] ?? '')) ?: null,
                trim((string) ($in['customer_email'] ?? '')) ?: null,
                date('Y-m-d H:i:s', strtotime($scheduled)),
                $type === 'walkin' ? 'completed' : 'booked',
                ($in['payment_status'] ?? '') === 'paid' ? 'paid' : 'unpaid',
                round($subtotal, 2), round($subtotal, 2), round($commissionTotal, 2),
                $staffId, $creatorId ?: null, trim((string) ($in['notes'] ?? '')) ?: null,
            ]);
            $id = (int) $this->db->lastInsertId();
            $number = 'SVC-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
            $this->db->prepare('UPDATE service_appointments SET invoice_number = ? WHERE id = ? AND tenant_id = ?')
                ->execute([$number, $id, $tid]);
            $ins = $this->db->prepare(
                'INSERT INTO service_appointment_items
                    (tenant_id, appointment_id, service_id, service_name, quantity, base_price, unit_price, line_total, commission_amount)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );
            foreach ($items as $item) {
                $ins->execute([
                    $tid, $id, $item['service']['id'], $item['service']['name'], $item['qty'],
                    $item['base'], $item['unitPrice'], $item['line'], $item['commission'],
                ]);
            }
            if (($in['payment_status'] ?? '') === 'paid') {
                $this->recordServiceRevenue($number, $subtotal, $creatorId, date('Y-m-d', strtotime($scheduled)));
            }
            $this->db->commit();
            return ['ok' => true, 'id' => $id, 'invoice_number' => $number, 'error' => null];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function updateAppointment(int $id, string $action, int $userId): bool
    {
        new FinanceModel($this->db);
        $row = $this->invoice($id);
        if (!$row) return false;
        if ($action === 'cancel') {
            $st = $this->db->prepare("UPDATE service_appointments SET status='cancelled' WHERE id=? AND tenant_id=?");
            return $st->execute([$id, \TenantContext::tenantId()]);
        }
        if ($action === 'complete') {
            $this->db->prepare("UPDATE service_appointments SET status='completed' WHERE id=? AND tenant_id=?")
                ->execute([$id, \TenantContext::tenantId()]);
            return true;
        }
        if ($action === 'paid') {
            try {
                $this->db->beginTransaction();
                $this->db->prepare("UPDATE service_appointments SET payment_status='paid' WHERE id=? AND tenant_id=?")
                    ->execute([$id, \TenantContext::tenantId()]);
                $this->recordServiceRevenue($row['invoice_number'], (float) $row['total'], $userId, date('Y-m-d'));
                $this->db->commit();
                return true;
            } catch (\Throwable $e) {
                if ($this->db->inTransaction()) $this->db->rollBack();
                return false;
            }
        }
        return false;
    }

    public function appointments(array $filters = [], int $limit = 300): array
    {
        $where = ['a.tenant_id = ?'];
        $params = [\TenantContext::tenantId()];
        if (!empty($filters['status']) && in_array($filters['status'], ['booked','completed','cancelled'], true)) {
            $where[] = 'a.status = ?'; $params[] = $filters['status'];
        }
        $st = $this->db->prepare(
            "SELECT a.*, u.username AS staff_name
               FROM service_appointments a LEFT JOIN users u ON u.id = a.staff_id
              WHERE " . implode(' AND ', $where) . "
           ORDER BY a.scheduled_at DESC, a.id DESC LIMIT " . (int) $limit
        );
        $st->execute($params);
        return $st->fetchAll();
    }

    public function upcomingForUser(int $userId, int $minutes = 60): array
    {
        $st = $this->db->prepare(
            "SELECT * FROM service_appointments
              WHERE tenant_id = ? AND (staff_id = ? OR created_by = ?) AND status = 'booked'
                AND scheduled_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? MINUTE)
           ORDER BY scheduled_at ASC LIMIT 10"
        );
        $st->execute([\TenantContext::tenantId(), $userId, $userId, max(1, $minutes)]);
        return $st->fetchAll();
    }

    public function invoice(int $id): ?array
    {
        $st = $this->db->prepare(
            'SELECT a.*, u.username AS staff_name FROM service_appointments a
             LEFT JOIN users u ON u.id = a.staff_id WHERE a.id = ? AND a.tenant_id = ? LIMIT 1'
        );
        $st->execute([$id, \TenantContext::tenantId()]);
        return $st->fetch() ?: null;
    }

    public function invoiceItems(int $id): array
    {
        $st = $this->db->prepare(
            'SELECT * FROM service_appointment_items WHERE appointment_id = ? AND tenant_id = ? ORDER BY id'
        );
        $st->execute([$id, \TenantContext::tenantId()]);
        return $st->fetchAll();
    }

    private function recordServiceRevenue(string $invoiceNumber, float $amount, int $userId, string $date): void
    {
        $check = $this->db->prepare('SELECT 1 FROM finance_entries WHERE tenant_id=? AND reference=? LIMIT 1');
        $check->execute([\TenantContext::tenantId(), $invoiceNumber]);
        if ($check->fetchColumn()) return;
        $this->db->prepare(
            "INSERT INTO finance_entries
                (tenant_id,entry_type,category,description,amount,payment_method,reference,entry_date,created_by)
             VALUES (?,'revenue','Service sales','Paid service invoice',?,'other',?,?,?)"
        )->execute([\TenantContext::tenantId(), round($amount, 2), $invoiceNumber, $date, $userId ?: null]);
    }

    private function ensureSchema(): void
    {
        $tables = [
            'business_services' => "CREATE TABLE IF NOT EXISTS business_services (
                id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NOT NULL, name VARCHAR(160) NOT NULL,
                description VARCHAR(255) NULL, price DECIMAL(12,2) NOT NULL DEFAULT 0,
                commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0, duration_minutes INT NOT NULL DEFAULT 30,
                merged_service_ids TEXT NULL, status VARCHAR(20) NOT NULL DEFAULT 'active', created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_business_services (tenant_id,status,name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'service_appointments' => "CREATE TABLE IF NOT EXISTS service_appointments (
                id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NOT NULL, invoice_number VARCHAR(32) NOT NULL,
                booking_type VARCHAR(20) NOT NULL DEFAULT 'appointment', customer_name VARCHAR(160) NOT NULL,
                customer_phone VARCHAR(40) NULL, customer_email VARCHAR(190) NULL, scheduled_at DATETIME NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'booked', payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
                subtotal DECIMAL(12,2) NOT NULL DEFAULT 0, total DECIMAL(12,2) NOT NULL DEFAULT 0,
                commission_total DECIMAL(12,2) NOT NULL DEFAULT 0, staff_id INT NULL, created_by INT NULL,
                notes VARCHAR(255) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_service_invoice (tenant_id,invoice_number), KEY idx_service_due (tenant_id,staff_id,status,scheduled_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'service_appointment_items' => "CREATE TABLE IF NOT EXISTS service_appointment_items (
                id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NOT NULL, appointment_id INT NOT NULL,
                service_id INT NULL, service_name VARCHAR(160) NOT NULL, quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
                base_price DECIMAL(12,2) NOT NULL DEFAULT 0, unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
                line_total DECIMAL(12,2) NOT NULL DEFAULT 0, commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_service_items (tenant_id,appointment_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
        foreach ($tables as $table => $sql) {
            try { $this->db->query("SELECT 1 FROM `{$table}` LIMIT 1"); }
            catch (\PDOException $e) { try { $this->db->exec($sql); } catch (\PDOException $ignored) {} }
        }
    }
}
