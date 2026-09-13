-- 018_create_subscription_stk.sql
-- Tracks each M-Pesa STK push for a subscription payment, from request through
-- callback. A successful callback activates the owner account + the subscription.

CREATE TABLE IF NOT EXISTS subscription_stk (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id           INT NOT NULL,
    user_id             INT NOT NULL,
    subscription_id     INT NULL,
    plan_id             INT NOT NULL,
    billing_interval    ENUM('weekly','biweekly','monthly') NOT NULL,
    amount              DECIMAL(10,2) NOT NULL,
    phone               VARCHAR(15) NOT NULL,
    checkout_request_id VARCHAR(64) NULL,
    merchant_request_id VARCHAR(64) NULL,
    status              ENUM('pending','success','failed','cancelled') NOT NULL DEFAULT 'pending',
    result_code         INT NULL,
    result_desc         VARCHAR(191) NULL,
    mpesa_receipt       VARCHAR(32) NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_stk_checkout (checkout_request_id),
    KEY idx_stk_tenant (tenant_id),
    KEY idx_stk_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;