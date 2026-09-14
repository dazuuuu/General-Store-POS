ALTER TABLE categories MODIFY COLUMN type ENUM('subject','stationery','product','menu') NOT NULL DEFAULT 'product';
ALTER TABLE orders MODIFY COLUMN channel ENUM('walkin','tab','restaurant') NOT NULL DEFAULT 'tab';
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='held_orders' AND COLUMN_NAME='context'),'SELECT 1','ALTER TABLE held_orders ADD COLUMN context VARCHAR(20) NOT NULL DEFAULT ''shop'' AFTER staff_id');
PREPARE stmt FROM @sql;EXECUTE stmt;DEALLOCATE PREPARE stmt;
