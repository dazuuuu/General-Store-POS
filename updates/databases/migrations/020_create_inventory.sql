-- 020_create_inventory.sql
-- Inventory: categories -> subcategories -> products. All tenant-scoped.

CREATE TABLE IF NOT EXISTS categories (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id  INT NOT NULL,
    name       VARCHAR(120) NOT NULL,
    status     ENUM('active','draft') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cat_tenant_name (tenant_id, name),
    KEY idx_cat_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subcategories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT NOT NULL,
    category_id INT NOT NULL,
    name        VARCHAR(120) NOT NULL,
    status      ENUM('active','draft') NOT NULL DEFAULT 'active',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subcat_tenant_cat_name (tenant_id, category_id, name),
    KEY idx_subcat_tenant (tenant_id),
    KEY idx_subcat_cat (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id             INT NOT NULL,
    category_id           INT NOT NULL,
    subcategory_id        INT NULL,
    name                  VARCHAR(160) NOT NULL,
    description           TEXT NULL,
    quantity              DECIMAL(12,2) NOT NULL DEFAULT 0,
    unit                  VARCHAR(20) NOT NULL DEFAULT 'piece',   -- piece,g,kg,tonne,ml,litre
    buying_price          DECIMAL(12,2) NOT NULL DEFAULT 0,
    selling_price         DECIMAL(12,2) NOT NULL DEFAULT 0,
    colors                JSON NULL,                              -- ["Blue","Red"]
    sizes                 JSON NULL,                              -- ["S","M","L"] or ["500ml","1L"]
    image_path            VARCHAR(255) NULL,
    low_stock_threshold   INT NOT NULL DEFAULT 10,
    low_stock_notified_at DATETIME NULL,                          -- last restock email sent
    status                ENUM('active','draft') NOT NULL DEFAULT 'active',
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_prod_tenant (tenant_id),
    KEY idx_prod_cat (category_id),
    KEY idx_prod_subcat (subcategory_id),
    KEY idx_prod_status (status),
    KEY idx_prod_lowstock (tenant_id, quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;