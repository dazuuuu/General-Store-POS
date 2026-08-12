-- 050_order_credit_payments.sql
-- Let credit-sale invoices receive one or more payments before they are fully
-- settled. The orders row keeps the current balance; order_payments keeps the
-- payment history for receipts and audit.

ALTER TABLE orders
    ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'credit' AFTER payment_method,
    ADD COLUMN amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER total,
    ADD COLUMN amount_due DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER amount_paid;

UPDATE orders
   SET amount_paid = CASE WHEN status = 'paid' THEN total ELSE COALESCE(amount_paid, 0) END,
       amount_due = CASE WHEN status = 'paid' THEN 0 ELSE GREATEST(total - COALESCE(amount_paid, 0), 0) END,
       payment_status = CASE WHEN status = 'paid' THEN 'paid' ELSE 'credit' END
 WHERE amount_due = 0;

CREATE TABLE IF NOT EXISTS order_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    order_id INT NOT NULL,
    staff_id INT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    method VARCHAR(20) NOT NULL,
    cash_amount DECIMAL(12,2) NULL,
    mpesa_amount DECIMAL(12,2) NULL,
    amount_tendered DECIMAL(12,2) NULL,
    change_due DECIMAL(12,2) NULL,
    provider VARCHAR(100) NULL,
    account_name VARCHAR(160) NULL,
    reference VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_order_payment_order (tenant_id, order_id),
    KEY idx_order_payment_staff (tenant_id, staff_id),
    CONSTRAINT fk_order_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
