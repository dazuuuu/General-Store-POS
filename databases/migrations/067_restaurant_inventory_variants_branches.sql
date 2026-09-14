CREATE TABLE IF NOT EXISTS restaurant_ingredients (
 id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NOT NULL, name VARCHAR(160) NOT NULL,
 package_unit VARCHAR(40) NOT NULL DEFAULT 'packet', units_per_package DECIMAL(12,4) NOT NULL DEFAULT 1,
 base_unit VARCHAR(40) NOT NULL DEFAULT 'piece', quantity DECIMAL(14,4) NOT NULL DEFAULT 0,
 avg_unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_restaurant_ingredient(tenant_id,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS restaurant_stock_intakes (
 id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NOT NULL, ingredient_id INT NOT NULL,
 package_quantity DECIMAL(12,4) NOT NULL, units_per_package DECIMAL(12,4) NOT NULL,
 quantity_received DECIMAL(14,4) NOT NULL, total_cost DECIMAL(14,2) NOT NULL,
 unit_cost DECIMAL(14,4) NOT NULL, received_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_restaurant_intakes(tenant_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS menu_variants (
 id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NOT NULL, menu_product_id INT NOT NULL,
 label VARCHAR(100) NOT NULL, retail_price DECIMAL(12,2) NOT NULL, sort_order INT NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_menu_variant(tenant_id,menu_product_id,label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS menu_recipe_items (
 id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NOT NULL, variant_id INT NOT NULL,
 ingredient_id INT NOT NULL, quantity DECIMAL(12,4) NOT NULL,
 UNIQUE KEY uq_menu_recipe(tenant_id,variant_id,ingredient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS restaurant_ingredient_movements (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NOT NULL, ingredient_id INT NOT NULL,
 order_item_id INT NOT NULL, delta_quantity DECIMAL(14,4) NOT NULL, unit_cost DECIMAL(14,4) NOT NULL,
 movement_type VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_restaurant_movement(tenant_id,order_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS branches (
 id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NOT NULL, title VARCHAR(160) NOT NULL, location VARCHAR(255) NULL,
 inventory_mode ENUM('shared','independent') NOT NULL DEFAULT 'shared', is_default TINYINT(1) NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_branch_title(tenant_id,title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS branch_stock (
 tenant_id INT NOT NULL, branch_id INT NOT NULL, product_id INT NOT NULL,
 quantity DECIMAL(14,4) NOT NULL DEFAULT 0, faulty_quantity DECIMAL(14,4) NOT NULL DEFAULT 0,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(tenant_id,branch_id,product_id), KEY idx_branch_stock_product(tenant_id,product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @s=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_items' AND COLUMN_NAME='menu_variant_id')=0,'ALTER TABLE order_items ADD menu_variant_id INT NULL AFTER product_id','SELECT 1');PREPARE x FROM @s;EXECUTE x;DEALLOCATE PREPARE x;
SET @s=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_items' AND COLUMN_NAME='unit_cogs')=0,'ALTER TABLE order_items ADD unit_cogs DECIMAL(14,4) NULL AFTER line_total','SELECT 1');PREPARE x FROM @s;EXECUTE x;DEALLOCATE PREPARE x;
SET @s=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_items' AND COLUMN_NAME='cogs_total')=0,'ALTER TABLE order_items ADD cogs_total DECIMAL(14,2) NULL AFTER unit_cogs','SELECT 1');PREPARE x FROM @s;EXECUTE x;DEALLOCATE PREPARE x;
SET @s=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='held_order_items' AND COLUMN_NAME='menu_variant_id')=0,'ALTER TABLE held_order_items ADD menu_variant_id INT NULL AFTER product_id','SELECT 1');PREPARE x FROM @s;EXECUTE x;DEALLOCATE PREPARE x;
SET @s=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='branch_id')=0,'ALTER TABLE users ADD branch_id INT NULL AFTER tenant_id','SELECT 1');PREPARE x FROM @s;EXECUTE x;DEALLOCATE PREPARE x;
SET @s=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='branch_id')=0,'ALTER TABLE orders ADD branch_id INT NULL AFTER tenant_id','SELECT 1');PREPARE x FROM @s;EXECUTE x;DEALLOCATE PREPARE x;
SET @s=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='held_orders' AND COLUMN_NAME='branch_id')=0,'ALTER TABLE held_orders ADD branch_id INT NULL AFTER tenant_id','SELECT 1');PREPARE x FROM @s;EXECUTE x;DEALLOCATE PREPARE x;
SET @s=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='stock_intakes' AND COLUMN_NAME='branch_id')=0,'ALTER TABLE stock_intakes ADD branch_id INT NULL AFTER tenant_id','SELECT 1');PREPARE x FROM @s;EXECUTE x;DEALLOCATE PREPARE x;
SET @s=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sales' AND COLUMN_NAME='branch_id')=0,'ALTER TABLE sales ADD branch_id INT NULL AFTER tenant_id','SELECT 1');PREPARE x FROM @s;EXECUTE x;DEALLOCATE PREPARE x;
SET @s=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='branches' AND COLUMN_NAME='inventory_mode')=0,'ALTER TABLE branches ADD inventory_mode ENUM(''shared'',''independent'') NOT NULL DEFAULT ''shared'' AFTER location','SELECT 1');PREPARE x FROM @s;EXECUTE x;DEALLOCATE PREPARE x;
SET @s=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='branches' AND COLUMN_NAME='is_default')=0,'ALTER TABLE branches ADD is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER inventory_mode','SELECT 1');PREPARE x FROM @s;EXECUTE x;DEALLOCATE PREPARE x;
