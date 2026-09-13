<?php
// app/services/RegistrationService.php
//
// Orchestrates first-time registration and email activation.
//   register(): creates tenant + owner (inactive) + pending subscription, then
//               hands an activation link to a notifier callback (so it's testable
//               and not coupled to a specific mailer).
//   activate(): verifies the token, activates the owner, and starts the first
//               subscription period.

use Models\TenantModel;
use Models\SubscriptionModel;

class RegistrationService
{
    private PDO $db;
    private TenantModel $tenants;
    private SubscriptionModel $subs;

    const TOKEN_TTL_HOURS = 48;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->tenants = new TenantModel($db);
        $this->subs = new SubscriptionModel($db);
    }

    /**
     * @param array    $in       ['business_name','email','password','plan_id','interval']
     * @param callable $notify   fn(string $email, string $activationLink, array $ctx): void
     * @return array ['ok'=>bool, 'errors'=>[], 'tenant_id'=>?, 'user_id'=>?, 'token'=>?]
     */
    public function register(array $in, callable $notify): array
    {
        $errors = $this->validate($in);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $plan = $this->plan((int) $in['plan_id']);
        if (!$plan) {
            return ['ok' => false, 'errors' => ['plan_id' => 'Unknown plan']];
        }
        $amount = Billing::planAmount($plan, $in['interval']);
        if ($amount === null) {
            return ['ok' => false, 'errors' => ['interval' => 'This plan does not offer that interval']];
        }

        $roleId = $this->tenantOwnerRoleId();
        if (!$roleId) {
            return ['ok' => false, 'errors' => ['_' => 'tenant_owner role missing — run migration 014']];
        }

        $token = bin2hex(random_bytes(24));
        $expires = date('Y-m-d H:i:s', strtotime('+' . self::TOKEN_TTL_HOURS . ' hours'));

        $this->db->beginTransaction();
        try {
            $slug = $this->tenants->uniqueSlug($in['business_name']);
            $tenantId = $this->tenants->create($in['business_name'], $slug);

            $username = $this->uniqueUsername($in['email']);
            $stmt = $this->db->prepare(
                'INSERT INTO users (tenant_id, username, email, password_hash, role_id,
                                    is_active, email_verified, activation_token, activation_expires)
                 VALUES (:tenant_id, :username, :email, :hash, :role_id, 0, 0, :token, :expires)'
            );
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':username'  => $username,
                ':email'     => $in['email'],
                ':hash'      => password_hash($in['password'], PASSWORD_DEFAULT),
                ':role_id'   => $roleId,
                ':token'     => $token,
                ':expires'   => $expires,
            ]);
            $userId = (int) $this->db->lastInsertId();

            $this->tenants->setOwner($tenantId, $userId);
            $subscriptionId = $this->subs->createForTenant($tenantId, (int) $plan['id'], $in['interval'], $amount);

            // Store the owner's phone (used for M-Pesa subscription payment).
            if (!empty($in['phone'])) {
                $this->db->prepare(
                    'INSERT INTO user_profiles (user_id, phone) VALUES (:u, :ph)
                     ON DUPLICATE KEY UPDATE phone = VALUES(phone)'
                )->execute([':u' => $userId, ':ph' => $in['phone']]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return ['ok' => false, 'errors' => ['_' => 'Registration failed: ' . $e->getMessage()]];
        }

        $link = $this->activationLink($token);
        $notify($in['email'], $link, [
            'business_name' => $in['business_name'],
            'plan'          => $plan['name'],
            'interval'      => $in['interval'],
            'amount'        => $amount,
        ]);

        return ['ok' => true, 'errors' => [], 'tenant_id' => $tenantId, 'user_id' => $userId, 'subscription_id' => $subscriptionId, 'token' => $token];
    }

    /**
     * Verify token, activate the owner, start the first billing period.
     * @return array ['ok'=>bool, 'reason'=>?string, 'tenant_id'=>?]
     */
    public function activate(string $token): array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE activation_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['ok' => false, 'reason' => 'invalid'];
        }
        if (!empty($user['activated_at'])) {
            return ['ok' => false, 'reason' => 'already_activated'];
        }
        if (!empty($user['activation_expires']) && strtotime($user['activation_expires']) < time()) {
            return ['ok' => false, 'reason' => 'expired'];
        }

        $now = date('Y-m-d H:i:s');

        $this->db->beginTransaction();
        try {
            $upd = $this->db->prepare(
                'UPDATE users SET is_active = 1, email_verified = 1, activated_at = :now,
                                  activation_token = NULL, activation_expires = NULL
                 WHERE id = :id'
            );
            $upd->execute([':now' => $now, ':id' => $user['id']]);

            $sub = $this->subs->forTenant((int) $user['tenant_id']);
            if ($sub) {
                $end = Billing::periodEnd($sub['billing_interval'], $now);
                $this->subs->activatePeriod((int) $sub['id'], $now, $end);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return ['ok' => false, 'reason' => 'error'];
        }

        return ['ok' => true, 'reason' => null, 'tenant_id' => (int) $user['tenant_id']];
    }

    // --- internals -----------------------------------------------------------

    private function validate(array $in): array
    {
        $e = [];
        if (empty($in['business_name']) || strlen(trim($in['business_name'])) < 2) {
            $e['business_name'] = 'Business name is required';
        }
        if (empty($in['email']) || !filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
            $e['email'] = 'A valid email is required';
        } elseif ($this->emailTaken($in['email'])) {
            $e['email'] = 'That email is already registered';
        }
        if (empty($in['password']) || strlen($in['password']) < 8) {
            $e['password'] = 'Password must be at least 8 characters';
        }
        if (empty($in['interval']) || !Billing::isValidInterval($in['interval'])) {
            $e['interval'] = 'Choose a billing interval';
        }
        if (empty($in['plan_id'])) {
            $e['plan_id'] = 'Choose a plan';
        }
        return $e;
    }

    private function emailTaken(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return (bool) $stmt->fetchColumn();
    }

    private function uniqueUsername(string $email): string
    {
        $base = preg_replace('/[^a-z0-9_]+/', '', strtolower(explode('@', $email)[0])) ?: 'owner';
        $name = $base;
        $i = 0;
        while (true) {
            $stmt = $this->db->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$name]);
            if (!$stmt->fetchColumn()) {
                return $name;
            }
            $name = $base . (++$i);
        }
    }

    private function plan(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM subscription_plans WHERE id = ? AND is_active = 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function tenantOwnerRoleId(): ?int
    {
        $id = $this->db->query("SELECT id FROM roles WHERE role_name = 'tenant_owner' LIMIT 1")->fetchColumn();
        return $id ? (int) $id : null;
    }

    private function activationLink(string $token): string
    {
        // Adjust base path to match your deployment.
        return '/Modern/public/auth/activate.php?token=' . urlencode($token);
    }
}