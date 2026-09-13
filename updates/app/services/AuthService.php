<?php
// app/services/AuthService.php
// Credential checking and the data the login flow needs. Kept thin and testable;
// the 2FA state machine lives in the login/otp-verify pages on top of this.

use Models\SubscriptionModel;

class AuthService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** Look up a user by email with their role name. (Owners use unique emails.) */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.*, r.role_name
             FROM users u JOIN roles r ON u.role_id = r.id
             WHERE u.email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function verifyPassword(array $user, string $password): bool
    {
        return !empty($user['password_hash']) && password_verify($password, $user['password_hash']);
    }

    public function subscriptionFor(?int $tenantId): ?array
    {
        if ($tenantId === null) {
            return null;
        }
        return (new SubscriptionModel($this->db))->forTenant($tenantId);
    }

    public function logAttempt(string $email, ?string $ip): void
    {
        try {
            $this->db->prepare('INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)')
                ->execute([$email, $ip]);
        } catch (\Throwable $e) {
            // login_attempts is best-effort; never block login on logging failure.
        }
    }
}