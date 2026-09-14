-- Serial-number staging for purchases and product VAT through warehouse transfers.
SET @sql := IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='purchase_items' AND COLUMN_NAME='tax_rate'),
  'SELECT 1',
  'ALTER TABLE purchase_items ADD COLUMN tax_rate DECIMAL(5,2) NULL AFTER retail_pack_price'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='purchase_items' AND COLUMN_NAME='serial_numbers'),
  'SELECT 1',
  'ALTER TABLE purchase_items ADD COLUMN serial_numbers TEXT NULL AFTER tax_rate'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='store_products' AND COLUMN_NAME='tax_rate'),
  'SELECT 1',
  'ALTER TABLE store_products ADD COLUMN tax_rate DECIMAL(5,2) NULL AFTER wholesale_price'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
