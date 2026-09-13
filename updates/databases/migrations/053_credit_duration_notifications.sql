-- 053_credit_duration_notifications.sql
-- Credit-sale due dates plus dashboard notifications for owners and staff.

ALTER TABLE orders
    ADD COLUMN credit_duration_days INT NULL AFTER amount_due,
    ADD COLUMN credit_due_at DATETIME NULL AFTER credit_duration_days;

CREATE TABLE IF NOT EXISTS tenant_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    audience ENUM('owner','staff') NOT NULL,
    user_id INT NULL,
    type VARCHAR(40) NOT NULL,
    title VARCHAR(120) NOT NULL,
    message VARCHAR(255) NOT NULL,
    url VARCHAR(255) NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tenant_audience (tenant_id, audience, created_at),
    KEY idx_tenant_user (tenant_id, user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
