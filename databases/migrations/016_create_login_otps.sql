-- 016_create_login_otps.sql
-- Email OTP codes for mandatory 2FA on every login (owners now, staff later).
-- Codes are stored HASHED, are single-use, expire, and cap failed attempts.

CREATE TABLE IF NOT EXISTS login_otps (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    tenant_id    INT NULL,
    code_hash    VARCHAR(255) NOT NULL,
    purpose      VARCHAR(32)  NOT NULL DEFAULT 'login_2fa',
    attempts     TINYINT      NOT NULL DEFAULT 0,
    max_attempts TINYINT      NOT NULL DEFAULT 5,
    expires_at   DATETIME     NOT NULL,
    consumed_at  DATETIME     NULL,
    ip           VARCHAR(45)  NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_otp_user_purpose (user_id, purpose),
    KEY idx_otp_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;