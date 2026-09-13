<?php
// app/services/OtpService.php
// Email OTP for 2FA. Codes are hashed at rest, single-use, time-limited, and
// rate-limited on both verification attempts and resends.

class OtpService
{
    const TTL_MINUTES        = 10;
    const RESEND_COOLDOWN    = 60;   // seconds between sends
    const CODE_LENGTH        = 6;
    const MAX_ATTEMPTS       = 5;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Issue a fresh code. Invalidates any prior unconsumed code for this
     * user+purpose. Returns the PLAIN code (to email) or a cooldown signal.
     *
     * @return array ['ok'=>bool, 'code'=>?string, 'reason'=>?string, 'retry_after'=>?int]
     */
    public function issue(int $userId, ?int $tenantId, string $purpose = 'login_2fa', ?string $ip = null): array
    {
        // Resend cooldown: look at the most recent code for this user+purpose.
        $stmt = $this->db->prepare(
            'SELECT created_at FROM login_otps WHERE user_id = ? AND purpose = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$userId, $purpose]);
        $last = $stmt->fetchColumn();
        if ($last) {
            $elapsed = time() - strtotime($last);
            if ($elapsed < self::RESEND_COOLDOWN) {
                return ['ok' => false, 'code' => null, 'reason' => 'cooldown', 'retry_after' => self::RESEND_COOLDOWN - $elapsed];
            }
        }

        // Invalidate older unconsumed codes.
        $this->db->prepare('UPDATE login_otps SET consumed_at = NOW() WHERE user_id = ? AND purpose = ? AND consumed_at IS NULL')
            ->execute([$userId, $purpose]);

        $code = str_pad((string) random_int(0, (10 ** self::CODE_LENGTH) - 1), self::CODE_LENGTH, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + self::TTL_MINUTES * 60);

        $ins = $this->db->prepare(
            'INSERT INTO login_otps (user_id, tenant_id, code_hash, purpose, max_attempts, expires_at, ip)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$userId, $tenantId, password_hash($code, PASSWORD_DEFAULT), $purpose, self::MAX_ATTEMPTS, $expires, $ip]);

        return ['ok' => true, 'code' => $code, 'reason' => null, 'retry_after' => null];
    }

    /**
     * Verify a submitted code against the active OTP for user+purpose.
     * @return array ['ok'=>bool, 'reason'=>?string]
     */
    public function verify(int $userId, string $purpose, string $code): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM login_otps
             WHERE user_id = ? AND purpose = ? AND consumed_at IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$userId, $purpose]);
        $otp = $stmt->fetch();

        if (!$otp) {
            return ['ok' => false, 'reason' => 'no_active_code'];
        }
        if (strtotime($otp['expires_at']) < time()) {
            return ['ok' => false, 'reason' => 'expired'];
        }
        if ((int) $otp['attempts'] >= (int) $otp['max_attempts']) {
            return ['ok' => false, 'reason' => 'too_many_attempts'];
        }

        // Count this attempt regardless of outcome.
        $this->db->prepare('UPDATE login_otps SET attempts = attempts + 1 WHERE id = ?')->execute([$otp['id']]);

        if (!password_verify($code, $otp['code_hash'])) {
            $remaining = (int) $otp['max_attempts'] - ((int) $otp['attempts'] + 1);
            return ['ok' => false, 'reason' => 'mismatch', 'remaining' => max(0, $remaining)];
        }

        // Success — consume it.
        $this->db->prepare('UPDATE login_otps SET consumed_at = NOW() WHERE id = ?')->execute([$otp['id']]);
        return ['ok' => true, 'reason' => null];
    }

    public static function message(string $reason): string
    {
        return [
            'no_active_code'    => 'Your code has expired. Request a new one.',
            'expired'           => 'Your code has expired. Request a new one.',
            'too_many_attempts' => 'Too many incorrect attempts. Request a new code.',
            'mismatch'          => 'That code is incorrect. Please try again.',
            'cooldown'          => 'Please wait a moment before requesting another code.',
        ][$reason] ?? 'Verification failed. Please try again.';
    }
}