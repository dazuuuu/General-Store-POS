-- Active: 1785849373366@@127.0.0.1@3306@archimedes_db
-- 036_bookshop_attributes.sql
-- Converts the generic products catalogue over to a bookshop stock ledger:
-- Grade/Class, Publisher, Author and Edition are small tenant-scoped lookup
-- values (like categories/suppliers already are) that get reused when a book
-- with the same value is entered again, and created automatically otherwise.
-- Subject continues to use the existing `categories` table.

-- Product categories
CREATE TABLE IF NOT EXISTS product_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    image_path VARCHAR(500),
    parent_id INT DEFAULT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES product_categories(id) ON DELETE SET NULL,
    INDEX idx_active (is_active),
    INDEX idx_slug (slug)
);

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    compare_price DECIMAL(10, 2), -- Original price for sale display
    sku VARCHAR(100) UNIQUE,
    stock_quantity INT DEFAULT 0,
    category_id INT,
    featured_image VARCHAR(500),
    gallery_images TEXT, -- JSON array of additional images
    status ENUM('active', 'inactive', 'draft') DEFAULT 'draft',
    is_featured BOOLEAN DEFAULT FALSE,
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords VARCHAR(255),
    view_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_category (category_id),
    INDEX idx_price (price),
    FULLTEXT INDEX idx_search (name, description)
);

CREATE TABLE IF NOT EXISTS book_attributes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id  INT NOT NULL,
    type       ENUM('grade','publisher','author','edition') NOT NULL,
    name       VARCHAR(160) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attr_tenant_type_name (tenant_id, type, name),
    KEY idx_attr_tenant_type (tenant_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE products
    ADD COLUMN grade_id     INT NULL AFTER subcategory_id,
    ADD COLUMN publisher_id INT NULL AFTER grade_id,
    ADD COLUMN author_id    INT NULL AFTER publisher_id,
    ADD COLUMN edition_id   INT NULL AFTER author_id,
    ADD KEY idx_prod_grade (grade_id),
    ADD KEY idx_prod_publisher (publisher_id),
    ADD KEY idx_prod_author (author_id),
    ADD KEY idx_prod_edition (edition_id);

-- Per-delivery-line note (e.g. "torn cover", "damaged carton") — the ledger's
-- Remark column.
ALTER TABLE stock_intake_items
    ADD COLUMN remark VARCHAR(255) NULL AFTER buying_price;
