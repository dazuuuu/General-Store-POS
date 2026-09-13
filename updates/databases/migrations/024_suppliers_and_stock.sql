-- Active: 1785849373366@@127.0.0.1@3306@2in1_db
-- 024_suppliers_and_stock.sql
-- Suppliers + stock intake for a liquor-store style workflow: a supplier
-- brings a batch of products (new or restocks) in one delivery. Products gain
-- a branch (stock is per-branch), a supplier reference, and a bottle size.

CREATE TABLE IF NOT EXISTS suppliers (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id  INT NOT NULL,
    name       VARCHAR(160) NOT NULL,
    phone      VARCHAR(30) NULL,
    notes      VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_supplier_tenant_name (tenant_id, name),
    KEY idx_supplier_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE products
    ADD COLUMN supplier_id INT NULL AFTER subcategory_id,
    ADD COLUMN branch_id   INT NULL AFTER supplier_id,
    ADD COLUMN size_value  DECIMAL(10,2) NULL AFTER unit,
    ADD COLUMN size_unit   ENUM('ml','l') NULL AFTER size_value,
    ADD KEY idx_prod_supplier (supplier_id),
    ADD KEY idx_prod_branch (branch_id);

-- One row per delivery: who brought what, to which branch, entered by whom.
CREATE TABLE IF NOT EXISTS stock_intakes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT NOT NULL,
    supplier_id INT NOT NULL,
    branch_id   INT NULL,
    staff_id    INT NOT NULL,
    notes       VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_intake_tenant (tenant_id),
    KEY idx_intake_supplier (supplier_id),
    KEY idx_intake_branch (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Line items of a delivery (new product or restock) — kept for history even
-- if the product itself is edited/deleted later.
CREATE TABLE IF NOT EXISTS stock_intake_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT NOT NULL,
    stock_intake_id INT NOT NULL,
    product_id      INT NULL,
    product_name    VARCHAR(160) NOT NULL,
    quantity        DECIMAL(12,2) NOT NULL,
    buying_price    DECIMAL(12,2) NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_intakeitem_intake (stock_intake_id),
    KEY idx_intakeitem_tenant (tenant_id),
    KEY idx_intakeitem_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
