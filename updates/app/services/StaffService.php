<?php
// app/services/StaffService.php
// Owner-driven staff management. Staff log in with a name + short PIN at a
// shared terminal (no email/password) — see PinLoginService for the login
// side of this.

class StaffService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @param int   $tenantId the owner's tenant
     * @param array $in       name (required), position (optional), pin (4-6 digits, required)
     * @return array ['ok'=>bool, 'user_id'=>?int, 'errors'=>array]
     */
    public function createWithPin(int $tenantId, array $in): array
    {
        $name     = trim($in['name'] ?? '');
        $position = trim($in['position'] ?? '');
        $pin      = trim($in['pin'] ?? '');
        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Enter the staff member\'s name.';
        }
        if (!preg_match('/^\d{4,6}$/', $pin)) {
            $errors['pin'] = 'PIN must be 4 to 6 digits.';
        }
        if (!$errors && $this->pinTaken($tenantId, $pin)) {
            $errors['pin'] = 'That PIN is already used by another staff member. Choose a different one.';
        }
        if ($errors) {
            return ['ok' => false, 'user_id' => null, 'errors' => $errors];
        }

        $staffRoleId = $this->roleId('staff');
        if ($staffRoleId === null) {
            return ['ok' => false, 'user_id' => null, 'errors' => ['_' => 'Staff role missing. Run migration 014.']];
        }

        // Password login stays impossible for staff — a random, never-shared
        // password fills the (NOT NULL) column. Email is likewise a hidden
        // placeholder; users.email is NOT NULL/unique but staff never see or
        // use it — they log in with name + PIN only.
        $placeholderEmail = 'pin.' . bin2hex(random_bytes(8)) . '@staff.local';
        $unusablePassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

        $stmt = $this->db->prepare(
            'INSERT INTO users (tenant_id, username, email, password_hash, pin_hash, position, must_reset_password, role_id, is_active, email_verified)
             VALUES (:t, :u, :e, :p, :pin, :pos, 0, :r, 1, 1)'
        );
        $stmt->execute([
            ':t' => $tenantId, ':u' => $name, ':e' => $placeholderEmail,
            ':p' => $unusablePassword, ':pin' => password_hash($pin, PASSWORD_DEFAULT),
            ':pos' => $position !== '' ? $position : null, ':r' => $staffRoleId,
        ]);

        return ['ok' => true, 'user_id' => (int) $this->db->lastInsertId(), 'errors' => []];
    }

    /** Staff for a tenant, with position. */
    public function listForTenant(int $tenantId): array
    {
        $sql = "SELECT u.id, u.username, u.position, u.is_active
                  FROM users u
                  JOIN roles r ON r.id = u.role_id
                 WHERE u.tenant_id = :t AND r.role_name = 'staff'
              ORDER BY u.username ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':t' => $tenantId]);
        return $stmt->fetchAll();
    }

    /** Block (or unblock) a staff member — a blocked account can't log in or clock in/out. */
    public function setActive(int $tenantId, int $userId, bool $active): array
    {
        if (!$this->belongsToTenant($tenantId, $userId)) {
            return ['ok' => false, 'error' => 'Staff not found.'];
        }
        $stmt = $this->db->prepare('UPDATE users SET is_active = ? WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$active ? 1 : 0, $userId, $tenantId]);
        return ['ok' => true, 'error' => null];
    }

    /**
     * Permanently remove a staff account. Their historical sales/orders/stock
     * entries are kept (referenced by id only) so reporting and audit trails
     * stay intact — every display already falls back gracefully (e.g. "—")
     * when the linked user no longer exists.
     */
    public function deleteStaff(int $tenantId, int $userId): array
    {
        if (!$this->belongsToTenant($tenantId, $userId)) {
            return ['ok' => false, 'error' => 'Staff not found.'];
        }
        $this->db->prepare('DELETE FROM user_permissions WHERE user_id = ?')->execute([$userId]);
        $this->db->prepare('DELETE FROM users WHERE id = ? AND tenant_id = ?')->execute([$userId, $tenantId]);
        return ['ok' => true, 'error' => null];
    }

    /** Owner resets a staff member's PIN (no current-PIN check — the owner is trusted). */
    public function setPin(int $tenantId, int $userId, string $newPin): array
    {
        if (!$this->belongsToTenant($tenantId, $userId)) {
            return ['ok' => false, 'error' => 'Staff not found.'];
        }
        if (!preg_match('/^\d{4,6}$/', $newPin)) {
            return ['ok' => false, 'error' => 'PIN must be 4 to 6 digits.'];
        }
        if ($this->pinTaken($tenantId, $newPin, $userId)) {
            return ['ok' => false, 'error' => 'That PIN is already used by another staff member. Choose a different one.'];
        }
        $this->db->prepare('UPDATE users SET pin_hash = ? WHERE id = ? AND tenant_id = ?')
            ->execute([password_hash($newPin, PASSWORD_DEFAULT), $userId, $tenantId]);
        return ['ok' => true, 'error' => null];
    }

    /** Staff member changes their own PIN — requires the current one to match first. */
    public function changeOwnPin(int $tenantId, int $userId, string $currentPin, string $newPin): array
    {
        $stmt = $this->db->prepare('SELECT pin_hash FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$userId, $tenantId]);
        $hash = $stmt->fetchColumn();
        if (!$hash || !password_verify($currentPin, $hash)) {
            return ['ok' => false, 'error' => 'Your current PIN is incorrect.'];
        }
        return $this->setPin($tenantId, $userId, $newPin);
    }

    private function belongsToTenant(int $tenantId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM users u JOIN roles r ON r.id = u.role_id
              WHERE u.id = ? AND u.tenant_id = ? AND r.role_name = 'staff' LIMIT 1"
        );
        $stmt->execute([$userId, $tenantId]);
        return (bool) $stmt->fetchColumn();
    }

    /** Is this PIN already in use by another active staff member of this tenant? */
    private function pinTaken(int $tenantId, string $pin, ?int $exceptUserId = null): bool
    {
        $sql = "SELECT u.pin_hash FROM users u JOIN roles r ON r.id = u.role_id
                 WHERE u.tenant_id = ? AND r.role_name = 'staff' AND u.is_active = 1 AND u.pin_hash IS NOT NULL";
        $params = [$tenantId];
        if ($exceptUserId !== null) { $sql .= ' AND u.id != ?'; $params[] = $exceptUserId; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $hash) {
            if ($hash && password_verify($pin, $hash)) {
                return true;
            }
        }
        return false;
    }

    private function roleId(string $name): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM roles WHERE role_name = ? LIMIT 1');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    // ===== per-staff capability management ==============================

    /** roles.id for the 'staff' role. */
    public function staffRoleId(): ?int
    {
        $id = $this->db->query("SELECT id FROM roles WHERE role_name = 'staff' LIMIT 1")->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    /** The capabilities a role grants by default (source of truth = roles table). */
    public function roleDefaultCaps(string $role = 'staff'): array
    {
        $stmt = $this->db->prepare("SELECT capabilities FROM roles WHERE role_name = ? LIMIT 1");
        $stmt->execute([$role]);
        $json = $stmt->fetchColumn();
        return $json ? (json_decode($json, true) ?: []) : [];
    }

    /** One staff member that belongs to this tenant (or null). */
    public function findStaff(int $tenantId, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT u.id, u.username, u.position, u.is_active
               FROM users u
               JOIN roles r ON r.id = u.role_id
              WHERE u.id = ? AND u.tenant_id = ? AND r.role_name = 'staff' LIMIT 1"
        );
        $stmt->execute([$userId, $tenantId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Effective capabilities for a staff member (role defaults + grants − revokes). */
    public function effectiveCaps(int $userId, int $roleId): array
    {
        return Capabilities::effective($this->db, $userId, $roleId);
    }

    /**
     * Persist desired capabilities for a staff member. Only capabilities in
     * $manageable are touched (never owner-only powers). Overrides are stored
     * only where the desired state differs from the role default, so the table
     * stays minimal and correct.
     */
    public function setCapabilities(int $tenantId, int $userId, array $desired, array $manageable, array $roleDefaults): void
    {
        $del = $this->db->prepare("DELETE FROM user_permissions WHERE user_id = ? AND capability = ?");
        $up  = $this->db->prepare(
            "INSERT INTO user_permissions (tenant_id, user_id, capability, effect)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE effect = VALUES(effect)"
        );
        $this->db->beginTransaction();
        try {
            foreach ($manageable as $cap) {
                $want = in_array($cap, $desired, true);
                $def  = in_array($cap, $roleDefaults, true);
                if ($want === $def) {
                    $del->execute([$userId, $cap]);          // back to default → no override
                } else {
                    $up->execute([$tenantId, $userId, $cap, $want ? 'grant' : 'revoke']);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $e;
        }
    }
}