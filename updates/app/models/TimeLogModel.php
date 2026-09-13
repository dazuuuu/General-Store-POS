<?php
// app/models/TimeLogModel.php
// Clock in / clock out for staff. Runs before a full session exists (the
// staff terminal only has a PIN so far), so callers pass tenant/user ids
// explicitly rather than relying on TenantContext.
//
// Rule: one clock-in per calendar day, unless the owner authorizes another
// (staff_reclock_authorizations — a one-time slip, consumed on use). A
// clock-in left open from a previous day (forgotten clock-out) is
// auto-closed the next time this person clocks in, flagged `auto_closed`,
// so it never silently reads as "still active" and never blocks the new day.
namespace Models;

class TimeLogModel extends Model
{
    protected string $table = 'staff_time_logs';

    public function __construct(?\PDO $db = null)
    {
        parent::__construct($db);
        $this->ensureSchema();
    }

    public function clockIn(int $tenantId, int $userId): array
    {
        $today = date('Y-m-d');
        $last = $this->lastLog($tenantId, $userId);

        if ($last && $last['clock_out_at'] === null && $this->dateOf($last['clock_in_at']) !== $today) {
            $this->autoClose($last);
            $last = null; // yesterday's forgotten clock-out doesn't block today
        }

        if ($last && $this->dateOf($last['clock_in_at']) === $today) {
            if (!$this->consumeReclockAuthorization($tenantId, $userId)) {
                return ['ok' => false, 'error' => 'You already clocked in today. Ask the owner to authorize another clock-in.'];
            }
        }

        $stmt = $this->db->prepare(
            'INSERT INTO staff_time_logs (tenant_id, user_id, clock_in_at) VALUES (?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $userId]);
        return ['ok' => true, 'error' => null, 'at' => date('g:i a')];
    }

    public function clockOut(int $tenantId, int $userId): array
    {
        $open = $this->lastLog($tenantId, $userId);
        if (!$open || $open['clock_out_at'] !== null) {
            return ['ok' => false, 'error' => "You haven't clocked in, or you've already clocked out."];
        }

        $stmt = $this->db->prepare('UPDATE staff_time_logs SET clock_out_at = NOW() WHERE id = ?');
        $stmt->execute([$open['id']]);
        return ['ok' => true, 'error' => null, 'at' => date('g:i a')];
    }

    private function ensureSchema(): void
    {
        try {
            $this->db->query("SELECT 1 FROM `staff_time_logs` LIMIT 1");
        } catch (\PDOException $e) {
            $this->db->exec("CREATE TABLE IF NOT EXISTS `staff_time_logs` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `tenant_id` INT NOT NULL,
                `user_id` INT NOT NULL,
                `clock_in_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `clock_out_at` DATETIME NULL DEFAULT NULL,
                `auto_closed` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_stafflog_tenant_user` (`tenant_id`, `user_id`),
                KEY `idx_stafflog_clockin` (`clock_in_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        try {
            $this->db->query("SELECT 1 FROM `staff_reclock_authorizations` LIMIT 1");
        } catch (\PDOException $e) {
            $this->db->exec("CREATE TABLE IF NOT EXISTS `staff_reclock_authorizations` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `tenant_id` INT NOT NULL,
                `user_id` INT NOT NULL,
                `authorized_by` INT NOT NULL,
                `authorized_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `used_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_reclock_user` (`tenant_id`, `user_id`, `used_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    /** Has this staff member clocked in at all today? Gates Login on the PIN terminal. */
    public function hasClockedInToday(int $tenantId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM staff_time_logs WHERE tenant_id = ? AND user_id = ? AND DATE(clock_in_at) = CURDATE() LIMIT 1'
        );
        $stmt->execute([$tenantId, $userId]);
        return (bool) $stmt->fetchColumn();
    }

    /** Owner grants one more clock-in today for a staff member who's already used theirs. */
    public function authorizeReclock(int $tenantId, int $userId, int $ownerId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO staff_reclock_authorizations (tenant_id, user_id, authorized_by) VALUES (?, ?, ?)'
        );
        $stmt->execute([$tenantId, $userId, $ownerId]);
    }

    /** Does this staff member have an unused re-clock slip waiting? (for the attendance page) */
    public function hasUnusedAuthorization(int $tenantId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM staff_reclock_authorizations WHERE tenant_id = ? AND user_id = ? AND used_at IS NULL LIMIT 1'
        );
        $stmt->execute([$tenantId, $userId]);
        return (bool) $stmt->fetchColumn();
    }

    /** Staff currently clocked in (open, not auto-closed) — the attendance page's "In now" list. */
    public function currentlyIn(int $tenantId): array
    {
        $stmt = $this->db->prepare(
            "SELECT l.*, u.username FROM staff_time_logs l
               JOIN users u ON u.id = l.user_id
              WHERE l.tenant_id = ? AND l.clock_out_at IS NULL
           ORDER BY l.clock_in_at ASC"
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }

    /** Recent clock in/out history for every staff member, newest first. */
    public function recentForTenant(int $tenantId, int $limit = 100): array
    {
        $stmt = $this->db->prepare(
            "SELECT l.*, u.username FROM staff_time_logs l
               JOIN users u ON u.id = l.user_id
              WHERE l.tenant_id = ?
           ORDER BY l.clock_in_at DESC, l.id DESC
              LIMIT " . (int) $limit
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }

    // ---- internals ----

    private function autoClose(array $log): void
    {
        $closeAt = $this->dateOf($log['clock_in_at']) . ' 23:59:59';
        $stmt = $this->db->prepare('UPDATE staff_time_logs SET clock_out_at = ?, auto_closed = 1 WHERE id = ?');
        $stmt->execute([$closeAt, $log['id']]);
    }

    /** Reuses (consumes) the oldest unused re-clock slip, if any. */
    private function consumeReclockAuthorization(int $tenantId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM staff_reclock_authorizations
              WHERE tenant_id = ? AND user_id = ? AND used_at IS NULL
           ORDER BY authorized_at ASC LIMIT 1'
        );
        $stmt->execute([$tenantId, $userId]);
        $id = $stmt->fetchColumn();
        if (!$id) {
            return false;
        }
        $this->db->prepare('UPDATE staff_reclock_authorizations SET used_at = NOW() WHERE id = ?')->execute([(int) $id]);
        return true;
    }

    private function dateOf(string $datetime): string
    {
        return date('Y-m-d', strtotime($datetime));
    }

    private function lastLog(int $tenantId, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM staff_time_logs WHERE tenant_id = ? AND user_id = ? ORDER BY clock_in_at DESC, id DESC LIMIT 1'
        );
        $stmt->execute([$tenantId, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
