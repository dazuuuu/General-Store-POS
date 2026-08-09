-- Active: 1785849373366@@127.0.0.1@3306@2in1_db
-- 031_held_orders.sql
-- "Hold Order": a cart set aside before it becomes a real invoice/tab. Unlike
-- `orders`, holding does NOT touch stock — nothing is committed until the
-- held cart is resumed and checked out (which creates a real `orders` row
-- and discards the hold).

CREATE TABLE IF NOT EXISTS held_orders (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id     INT NOT NULL,
    branch_id     INT NULL,
    customer_name VARCHAR(120) NOT NULL,
    staff_id      INT NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_held_tenant (tenant_id),
    KEY idx_held_branch (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS held_order_items (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id      INT NOT NULL,
    held_order_id  INT NOT NULL,
    product_id     INT NULL,
    product_name   VARCHAR(160) NOT NULL,
    unit_price     DECIMAL(12,2) NOT NULL,
    quantity       DECIMAL(12,2) NOT NULL,
    KEY idx_helditem_held (held_order_id),
    KEY idx_helditem_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
