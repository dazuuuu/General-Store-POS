-- 022_create_sales.sql
-- Recorded sales (no payment gateway) + line items. Tenant-scoped.

CREATE TABLE IF NOT EXISTS sales (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id      INT NOT NULL,
    branch_id      INT NULL,
    staff_id       INT NOT NULL,                       -- user who recorded the sale
    receipt_number VARCHAR(32) NOT NULL,
    payment_method ENUM('cash','mpesa') NOT NULL,
    total          DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount_given   DECIMAL(12,2) NULL,                 -- cash tendered (= total for mpesa)
    change_given   DECIMAL(12,2) NULL,
    customer_name  VARCHAR(120) NULL,
    customer_phone VARCHAR(30) NULL,
    customer_email VARCHAR(255) NULL,
    status         ENUM('completed','voided') NOT NULL DEFAULT 'completed',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sale_receipt (tenant_id, receipt_number),
    KEY idx_sale_tenant (tenant_id),
    KEY idx_sale_staff (staff_id),
    KEY idx_sale_branch (branch_id),
    KEY idx_sale_created (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sale_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id    INT NOT NULL,
    sale_id      INT NOT NULL,
    product_id   INT NULL,                             -- may be null if product later deleted
    product_name VARCHAR(160) NOT NULL,                -- snapshot at sale time
    unit         VARCHAR(20) NOT NULL DEFAULT 'piece',
    unit_price   DECIMAL(12,2) NOT NULL,               -- snapshot of selling price
    quantity     DECIMAL(12,2) NOT NULL,
    line_total   DECIMAL(12,2) NOT NULL,
    KEY idx_item_sale (sale_id),
    KEY idx_item_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;