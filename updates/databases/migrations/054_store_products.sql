-- 054_store_products.sql
-- Holding-store stock: products wait here until a store invoice transfers them
-- into the normal sellable inventory.

CREATE TABLE IF NOT EXISTS store_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    product_id INT NULL,
    transferred_invoice_id INT NULL,
    name VARCHAR(160) NOT NULL,
    category_id INT NULL,
    brand_id INT NULL,
    supplier_id INT NULL,
    barcode VARCHAR(64) NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'piece',
    colors VARCHAR(255) NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    faulty_quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    buying_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    retail_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    wholesale_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    offer_price DECIMAL(12,2) NULL,
    offer_starts_at DATETIME NULL,
    offer_ends_at DATETIME NULL,
    image_path VARCHAR(255) NULL,
    notes VARCHAR(255) NULL,
    status ENUM('stored','transferred') NOT NULL DEFAULT 'stored',
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    transferred_at DATETIME NULL,
    KEY idx_store_products_tenant_status (tenant_id, status),
    KEY idx_store_products_invoice (tenant_id, transferred_invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS store_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    invoice_number VARCHAR(32) NOT NULL,
    invoice_to VARCHAR(160) NULL,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes VARCHAR(255) NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_store_invoice (tenant_id, invoice_number),
    KEY idx_store_invoice_tenant (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS store_invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    invoice_id INT NOT NULL,
    store_product_id INT NOT NULL,
    product_id INT NULL,
    product_name VARCHAR(160) NOT NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    unit VARCHAR(20) NULL,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    KEY idx_store_invoice_items_invoice (tenant_id, invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
