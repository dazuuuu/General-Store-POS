-- Active: 1785849373366@@127.0.0.1@3306@2in1_db
-- 027_create_orders.sql
-- Bar tabs: a server opens a tab for a table/customer, adds drinks over one
-- or more rounds, and someone with payment permission settles it later.
-- Separate from `sales` (which stays for direct/immediate sales).

CREATE TABLE IF NOT EXISTS orders (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id      INT NOT NULL,
    branch_id      INT NULL,
    table_name     VARCHAR(120) NOT NULL,
    opened_by      INT NOT NULL,
    receipt_number VARCHAR(32) NOT NULL,
    status         ENUM('open','paid','void') NOT NULL DEFAULT 'open',
    subtotal       DECIMAL(12,2) NOT NULL DEFAULT 0,
    total          DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_method ENUM('cash','mpesa','split') NULL,
    cash_amount    DECIMAL(12,2) NULL,
    mpesa_amount   DECIMAL(12,2) NULL,
    paid_by        INT NULL,
    paid_at        DATETIME NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_order_receipt (tenant_id, receipt_number),
    KEY idx_order_tenant (tenant_id),
    KEY idx_order_status (tenant_id, status),
    KEY idx_order_branch (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id    INT NOT NULL,
    order_id     INT NOT NULL,
    product_id   INT NULL,
    product_name VARCHAR(160) NOT NULL,
    unit_price   DECIMAL(12,2) NOT NULL,
    quantity     DECIMAL(12,2) NOT NULL,
    line_total   DECIMAL(12,2) NOT NULL,
    added_by     INT NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_orderitem_order (order_id),
    KEY idx_orderitem_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
