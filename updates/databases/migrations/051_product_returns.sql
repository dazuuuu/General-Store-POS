-- 051_product_returns.sql
-- Product returns linked to the exact sale/order line item. returned_quantity
-- is what came back; used_quantity is the returned portion that is no longer
-- sellable; restocked_quantity is returned minus used and goes back to stock.

CREATE TABLE IF NOT EXISTS product_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    source_type ENUM('sale','order') NOT NULL,
    source_id INT NOT NULL,
    source_item_id INT NOT NULL,
    product_id INT NULL,
    product_name VARCHAR(160) NOT NULL,
    receipt_number VARCHAR(32) NOT NULL,
    returned_quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    used_quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    restocked_quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    reason VARCHAR(120) NULL,
    note VARCHAR(255) NULL,
    processed_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_returns_source (tenant_id, source_type, source_id),
    KEY idx_returns_item (tenant_id, source_type, source_item_id),
    KEY idx_returns_product (tenant_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
