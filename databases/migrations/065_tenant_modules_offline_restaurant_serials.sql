-- Tenant modules, offline idempotency, restaurant menu markers, sales-agent portions and serialized stock.
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenants' AND COLUMN_NAME='enabled_modules'),'SELECT 1','ALTER TABLE tenants ADD COLUMN enabled_modules TEXT NULL');
PREPARE stmt FROM @sql;EXECUTE stmt;DEALLOCATE PREPARE stmt;
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenants' AND COLUMN_NAME='offline_enabled'),'SELECT 1','ALTER TABLE tenants ADD COLUMN offline_enabled TINYINT(1) NOT NULL DEFAULT 0');
PREPARE stmt FROM @sql;EXECUTE stmt;DEALLOCATE PREPARE stmt;
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='is_menu_item'),'SELECT 1','ALTER TABLE products ADD COLUMN is_menu_item TINYINT(1) NOT NULL DEFAULT 0 AFTER product_type');
PREPARE stmt FROM @sql;EXECUTE stmt;DEALLOCATE PREPARE stmt;
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='serial_tracking'),'SELECT 1','ALTER TABLE products ADD COLUMN serial_tracking TINYINT(1) NOT NULL DEFAULT 0 AFTER is_menu_item');
PREPARE stmt FROM @sql;EXECUTE stmt;DEALLOCATE PREPARE stmt;
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employees' AND COLUMN_NAME='commission_rate'),'SELECT 1','ALTER TABLE employees ADD COLUMN commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER pay_day');
PREPARE stmt FROM @sql;EXECUTE stmt;DEALLOCATE PREPARE stmt;
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='client_uuid'),'SELECT 1','ALTER TABLE orders ADD COLUMN client_uuid CHAR(36) NULL AFTER opened_by');
PREPARE stmt FROM @sql;EXECUTE stmt;DEALLOCATE PREPARE stmt;
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND INDEX_NAME='uq_orders_client_uuid'),'SELECT 1','CREATE UNIQUE INDEX uq_orders_client_uuid ON orders(tenant_id,client_uuid)');
PREPARE stmt FROM @sql;EXECUTE stmt;DEALLOCATE PREPARE stmt;
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='held_order_items' AND COLUMN_NAME='serials_json'),'SELECT 1','ALTER TABLE held_order_items ADD COLUMN serials_json TEXT NULL AFTER quantity');
PREPARE stmt FROM @sql;EXECUTE stmt;DEALLOCATE PREPARE stmt;
CREATE TABLE IF NOT EXISTS product_serials(
 id INT AUTO_INCREMENT PRIMARY KEY,tenant_id INT NOT NULL,product_id INT NOT NULL,
 serial_number VARCHAR(190) NOT NULL,status ENUM('in_stock','sold','returned') NOT NULL DEFAULT 'in_stock',
 order_item_id INT NULL,sold_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_tenant_serial(tenant_id,serial_number),KEY idx_serial_product(tenant_id,product_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
