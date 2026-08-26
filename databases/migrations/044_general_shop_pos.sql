-- Active: 1785849373366@@127.0.0.1@3306@duaqabe_db
-- 044_general_shop_pos.sql
-- Evolve the bookshop POS into a general shop: VAT, credit limits, tiered
-- pricing, bulk units, loyalty customers, extra payment methods, and longer
-- receipt footers. Existing book/stationery rows stay valid.

-- Tenant shop settings
ALTER TABLE tenants
    MODIFY COLUMN receipt_footer TEXT NULL,
    ADD COLUMN payment_credentials TEXT NULL AFTER kra_pin,
    ADD COLUMN vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER kra_pin,
    ADD COLUMN vat_inclusive TINYINT(1) NOT NULL DEFAULT 1 AFTER vat_rate,
    ADD COLUMN loyalty_points_per_kes DECIMAL(8,2) NOT NULL DEFAULT 1.00 AFTER vat_inclusive,
    ADD COLUMN loyalty_kes_per_point DECIMAL(8,4) NOT NULL DEFAULT 0.0100 AFTER loyalty_points_per_kes,
    ADD COLUMN low_stock_alert_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER loyalty_kes_per_point;

-- Products: credit limit, bulk packs, generic type
ALTER TABLE products
    MODIFY COLUMN product_type ENUM('book','stationery','product') NOT NULL DEFAULT 'product',
    ADD COLUMN credit_limit DECIMAL(12,2) NULL AFTER low_stock_threshold,
    ADD COLUMN units_per_pack DECIMAL(12,2) NOT NULL DEFAULT 1.00 AFTER unit,
    ADD COLUMN pack_unit VARCHAR(20) NULL AFTER units_per_pack,
    ADD COLUMN pack_price DECIMAL(12,2) NULL AFTER pack_unit;

UPDATE products SET product_type = 'product' WHERE product_type = 'book';

-- Categories: subject → product (keep stationery for legacy)
ALTER TABLE categories
    MODIFY COLUMN type ENUM('subject','stationery','product') NOT NULL DEFAULT 'product';

UPDATE categories SET type = 'product' WHERE type = 'subject';

-- Orders: VAT + payment options + loyalty + B2B sale type
ALTER TABLE orders
    ADD COLUMN sale_type ENUM('retail','wholesale') NOT NULL DEFAULT 'retail' AFTER channel,
    ADD COLUMN vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER discount_amount,
    ADD COLUMN vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER vat_rate,
    ADD COLUMN customer_id INT NULL AFTER customer_email,
    ADD COLUMN loyalty_points_earned DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER customer_id,
    ADD COLUMN loyalty_points_redeemed DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER loyalty_points_earned,
    ADD COLUMN thank_you_sent_at DATETIME NULL AFTER delivery_note_sent_at,
    ADD COLUMN remembrance_sent_at DATETIME NULL AFTER thank_you_sent_at,
    MODIFY COLUMN payment_method ENUM('cash','mpesa','split','card','bank','sacco','credit') DEFAULT NULL,
    ADD COLUMN payment_provider VARCHAR(100) NULL AFTER payment_method,
    ADD COLUMN payment_account_name VARCHAR(160) NULL AFTER payment_provider,
    ADD COLUMN payment_reference VARCHAR(120) NULL AFTER payment_account_name;

-- Sales (legacy table): VAT columns for unified reports
ALTER TABLE sales
    ADD COLUMN vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER discount_amount,
    ADD COLUMN vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER vat_rate,
    ADD COLUMN customer_id INT NULL AFTER customer_email,
    MODIFY COLUMN payment_method ENUM('cash','mpesa','split','credit','card','bank') NOT NULL DEFAULT 'cash';

-- Loyalty / B2B customers
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(160) NOT NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(255) NULL,
    company_name VARCHAR(160) NULL,
    is_b2b TINYINT(1) NOT NULL DEFAULT 0,
    credit_limit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    credit_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    loyalty_points DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    loyalty_tier ENUM('standard','silver','gold','platinum') NOT NULL DEFAULT 'standard',
    notes TEXT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_cust_tenant (tenant_id),
    KEY idx_cust_phone (tenant_id, phone),
    KEY idx_cust_email (tenant_id, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tiered pricing (qty breaks per product)
CREATE TABLE IF NOT EXISTS product_price_tiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    product_id INT NOT NULL,
    min_qty DECIMAL(12,2) NOT NULL DEFAULT 1.00,
    max_qty DECIMAL(12,2) NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    label VARCHAR(80) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_tier_product (tenant_id, product_id, min_qty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Loyalty ledger
CREATE TABLE IF NOT EXISTS loyalty_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    customer_id INT NOT NULL,
    order_id INT NULL,
    points DECIMAL(12,2) NOT NULL,
    reason VARCHAR(160) NOT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_loy_customer (tenant_id, customer_id),
    KEY idx_loy_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Real-time sync cursors (clients poll since last_seen)
CREATE TABLE IF NOT EXISTS sync_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT NOT NULL,
    action VARCHAR(20) NOT NULL,
    payload JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sync_tenant_time (tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
