-- Sellable services, appointment invoices, product commissions and product taxes.

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenants' AND COLUMN_NAME='product_commission_enabled'),
    'SELECT 1',
    'ALTER TABLE tenants ADD COLUMN product_commission_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER low_stock_alert_enabled'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='tax_rate'),
    'SELECT 1',
    'ALTER TABLE products ADD COLUMN tax_rate DECIMAL(5,2) NULL AFTER retail_price'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_items' AND COLUMN_NAME='base_unit_price'),
    'SELECT 1',
    'ALTER TABLE order_items ADD COLUMN base_unit_price DECIMAL(12,2) NULL AFTER unit_price'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_items' AND COLUMN_NAME='commission_amount'),
    'SELECT 1',
    'ALTER TABLE order_items ADD COLUMN commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER base_unit_price'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS business_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(160) NOT NULL,
    description VARCHAR(255) NULL,
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    duration_minutes INT NOT NULL DEFAULT 30,
    merged_service_ids TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_business_services (tenant_id,status,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    invoice_number VARCHAR(32) NOT NULL,
    booking_type VARCHAR(20) NOT NULL DEFAULT 'appointment',
    customer_name VARCHAR(160) NOT NULL,
    customer_phone VARCHAR(40) NULL,
    customer_email VARCHAR(190) NULL,
    scheduled_at DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'booked',
    payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    commission_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    staff_id INT NULL,
    created_by INT NULL,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_service_invoice (tenant_id,invoice_number),
    KEY idx_service_due (tenant_id,staff_id,status,scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_appointment_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    appointment_id INT NOT NULL,
    service_id INT NULL,
    service_name VARCHAR(160) NOT NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
    base_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_service_items (tenant_id,appointment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
