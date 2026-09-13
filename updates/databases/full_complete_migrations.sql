-- Combined migration script generated on 2026-08-07 13:33:27
SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ===== 001_create_users_table.sql =====
-- Active: 1780050571987@@127.0.0.1@3306@archimedes_db
-- databases/migrations/001_create_users_table.sql

CREATE DATABASE IF NOT EXISTS archimedes_db;
USE archimedes_db;

-- Create roles table
CREATE TABLE IF NOT EXISTS roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert roles
INSERT INTO roles (role_name) VALUES 
('superadmin'),
('admin'),
('user')
ON DUPLICATE KEY UPDATE role_name = role_name;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_email (email),
    INDEX idx_username (username)
);

-- Create user_profiles table
CREATE TABLE IF NOT EXISTS user_profiles (
    user_id INT PRIMARY KEY,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create login_attempts table
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100),
    ip_address VARCHAR(45),
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_time (email, attempt_time)
);

-- Insert a default super admin (password: Admin123!)
INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified) 
VALUES ('superadmin', 'admin@ismano.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, 1)
ON DUPLICATE KEY UPDATE username = username;
-- ===== 002_create_projects_tables.sql =====
-- Active: 1780050571987@@127.0.0.1@3306@archimedes_db
-- databases/migrations/002_create_projects_tables.sql
USE archimedes_db;

-- Create project categories table
CREATE TABLE IF NOT EXISTS project_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    category_slug VARCHAR(100) NOT NULL UNIQUE,
    category_description TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_slug (category_slug)
);

-- Create projects table
CREATE TABLE IF NOT EXISTS projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    small_title VARCHAR(100) NOT NULL,
    major_title VARCHAR(200) NOT NULL,
    project_slug VARCHAR(200) NOT NULL UNIQUE,
    description TEXT,
    cover_image VARCHAR(255),
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    view_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES project_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category_id),
    INDEX idx_status (status),
    INDEX idx_slug (project_slug)
);

-- Create project gallery table (for multiple images)
CREATE TABLE IF NOT EXISTS project_gallery (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    image_title VARCHAR(100),
    image_description TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project (project_id),
    INDEX idx_sort (sort_order)
);

-- Create project videos table
CREATE TABLE IF NOT EXISTS project_videos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    video_title VARCHAR(200),
    video_url VARCHAR(500) NOT NULL,
    video_embed_code TEXT,
    video_type ENUM('youtube', 'vimeo', 'local', 'other') DEFAULT 'youtube',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project (project_id)
);

-- Create project tags table
CREATE TABLE IF NOT EXISTS project_tags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tag_name VARCHAR(50) NOT NULL UNIQUE,
    tag_slug VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create project-tag relationship table
CREATE TABLE IF NOT EXISTS project_tag_relations (
    project_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (project_id, tag_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES project_tags(id) ON DELETE CASCADE
);

-- Insert sample categories
INSERT INTO project_categories (category_name, category_slug, category_description) VALUES 
('Web Development', 'web-development', 'Web development projects including websites and web applications'),
('Mobile Apps', 'mobile-apps', 'Mobile application development projects'),
('UI/UX Design', 'ui-ux-design', 'User interface and experience design projects'),
('E-commerce', 'ecommerce', 'E-commerce platform and online store projects'),
('Custom Software', 'custom-software', 'Custom software development projects')
ON DUPLICATE KEY UPDATE category_name = category_name;
-- ===== 003_create_services_tables.sql =====
-- Active: 1780050571987@@127.0.0.1@3306@archimedes_db
USE archimedes_db;

-- Create services table
CREATE TABLE IF NOT EXISTS services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    short_description TEXT,
    cover_image VARCHAR(255),
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    view_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_slug (slug),
    INDEX idx_created_by (created_by)
);

-- Create service_sections table (for flexible content sections)
CREATE TABLE IF NOT EXISTS service_sections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_id INT NOT NULL,
    section_type ENUM('text_only', 'text_image_left', 'text_image_right', 'image_gallery', 'video') DEFAULT 'text_only',
    title VARCHAR(200),
    content TEXT,
    media_url VARCHAR(500),
    media_type ENUM('image', 'video', 'youtube', 'vimeo') DEFAULT 'image',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    INDEX idx_service (service_id),
    INDEX idx_sort (sort_order)
);

-- Create service_gallery table
CREATE TABLE IF NOT EXISTS service_gallery (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    image_title VARCHAR(100),
    image_description TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    INDEX idx_service (service_id),
    INDEX idx_sort (sort_order)
);

-- Create service_benefits table
CREATE TABLE IF NOT EXISTS service_benefits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_id INT NOT NULL,
    benefit_title VARCHAR(200) NOT NULL,
    benefit_description TEXT,
    icon_class VARCHAR(100),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    INDEX idx_service (service_id)
);

-- Create service_faqs table
CREATE TABLE IF NOT EXISTS service_faqs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_id INT NOT NULL,
    question VARCHAR(300) NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    INDEX idx_service (service_id)
);
-- ===== 004_create_blogs_tables.sql =====
-- Active: 1780050571987@@127.0.0.1@3306@archimedes_db
USE archimedes_db;

-- Create blog categories table (admin can create their own)
CREATE TABLE IF NOT EXISTS blog_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    color VARCHAR(20) DEFAULT '#667eea',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_slug (slug)
);

-- Create blogs table
CREATE TABLE IF NOT EXISTS blogs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    excerpt TEXT,
    content LONGTEXT,
    featured_image VARCHAR(255),
    category_id INT NULL,
    author_id INT NOT NULL,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    view_count INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords VARCHAR(255),
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_slug (slug),
    INDEX idx_author (author_id),
    INDEX idx_category (category_id),
    INDEX idx_published (published_at),
    INDEX idx_featured (is_featured)
);

-- Create blog sections table (for flexible content)
CREATE TABLE IF NOT EXISTS blog_sections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    blog_id INT NOT NULL,
    section_type ENUM('text_only', 'text_image_left', 'text_image_right', 'image_gallery', 'video', 'youtube', 'code_block', 'quote') DEFAULT 'text_only',
    title VARCHAR(255),
    content TEXT,
    media_url VARCHAR(500),
    media_type ENUM('image', 'video', 'youtube') DEFAULT 'image',
    video_id VARCHAR(100),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    INDEX idx_blog (blog_id),
    INDEX idx_sort (sort_order)
);

-- Create blog FAQs table
CREATE TABLE IF NOT EXISTS blog_faqs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    blog_id INT NOT NULL,
    question VARCHAR(300) NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    INDEX idx_blog (blog_id)
);

-- Create blog tags table
CREATE TABLE IF NOT EXISTS blog_tags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    slug VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create blog-tag relationship table
CREATE TABLE IF NOT EXISTS blog_tag_relations (
    blog_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (blog_id, tag_id),
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
);

-- ===== 005_create_settings_tables.sql =====
-- =====================================================================
-- 005_create_settings_tables.sql
-- Site settings (logo + site name), homepage hero slides, and the
-- per-page header banners used by the public layout.
-- Safe to run more than once (IF NOT EXISTS + idempotent seeds).
-- =====================================================================

-- ---------------------------------------------------------------------
-- Key/value singletons: logo, site name, etc.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS site_settings (
    setting_key   VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_settings (setting_key, setting_value) VALUES
    ('site_name', 'Ismano'),
    ('logo_path', NULL),
    ('logo_alt',  'Ismano')
ON DUPLICATE KEY UPDATE setting_key = setting_key;   -- no-op if row exists

-- ---------------------------------------------------------------------
-- Homepage hero slideshow images (managed by admin).
-- image_path is stored relative to /public  (e.g. uploads/hero/x.png)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS hero_slides (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    image_path  VARCHAR(255) NOT NULL,
    caption     VARCHAR(255) NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hero_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Per-page header banners for public pages (services, projects, ...).
-- One row per page_key; image managed by admin.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS page_headers (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    page_key    VARCHAR(60)  NOT NULL UNIQUE,
    title       VARCHAR(150) NULL,
    subtitle    VARCHAR(255) NULL,
    image_path  VARCHAR(255) NULL,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO page_headers (page_key, title, subtitle) VALUES
    ('services', 'Our Services', 'Comprehensive digital solutions tailored to elevate your business.'),
    ('projects', 'Our Projects', 'A selection of the work we are proud of.'),
    ('blogs',    'Our Blog',     'Insights, ideas and updates from the team.'),
    ('contact',  'Get in Touch', 'We would love to hear about your project.')
ON DUPLICATE KEY UPDATE page_key = page_key;
-- ===== 006_create_gallery_tables.sql =====
-- Active: 1780050571987@@127.0.0.1@3306@archimedes_db
-- Gallery table for images and videos
CREATE TABLE IF NOT EXISTS gallery (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    media_type ENUM('image', 'video') DEFAULT 'image',
    file_path VARCHAR(500) NOT NULL,
    thumbnail_path VARCHAR(500),
    video_url VARCHAR(500), -- For external videos (YouTube/Vimeo)
    video_embed_code TEXT, -- For embedded videos
    category VARCHAR(100),
    tags VARCHAR(255),
    sort_order INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    view_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_media_type (media_type),
    INDEX idx_sort (sort_order)
);

-- Gallery categories (optional)
CREATE TABLE IF NOT EXISTS gallery_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- ===== 007_create_products_tables.sql =====
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

-- ===== 008_create_cart_tables.sql =====
-- Shopping cart sessions (for non-logged in users)
CREATE TABLE IF NOT EXISTS cart_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    user_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session (session_id),
    INDEX idx_user (user_id)
);

-- Cart items
CREATE TABLE IF NOT EXISTS cart_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cart_session_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10, 2) NOT NULL, -- Snapshot of price at add time
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_session_id) REFERENCES cart_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_product (cart_session_id, product_id),
    INDEX idx_cart (cart_session_id),
    INDEX idx_product (product_id)
);

-- Saved for later items
CREATE TABLE IF NOT EXISTS saved_for_later (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cart_session_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_session_id) REFERENCES cart_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_saved (cart_session_id, product_id)
);

-- 008_store_cart_saved_for_later.sql
-- Optional: enables the "Save for later" / "Move to cart" actions.
-- The consolidated cart API works without this; these two actions simply return
-- a friendly "not enabled" message until the column exists. Run once.
-- (MySQL has no ADD COLUMN IF NOT EXISTS before MariaDB 10.0 / MySQL 8.0.x — if
--  the column already exists you'll get a harmless "Duplicate column" error.)

ALTER TABLE store_cart
    ADD COLUMN saved_for_later TINYINT(1) NOT NULL DEFAULT 0;
-- ===== 009_create_store_tables.sql =====
-- Active: 1780050571987@@127.0.0.1@3306@archimedes_db
-- Store categories table
CREATE TABLE IF NOT EXISTS store_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    image_path VARCHAR(500),
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_slug (slug)
);

-- Store products table
CREATE TABLE IF NOT EXISTS store_products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    compare_price DECIMAL(10, 2),
    sku VARCHAR(100),
    stock_quantity INT DEFAULT 0,
    category_id INT,
    featured_image VARCHAR(500),
    gallery_images TEXT,
    status ENUM('active', 'inactive', 'draft') DEFAULT 'draft',
    is_featured BOOLEAN DEFAULT FALSE,
    meta_title VARCHAR(255),
    meta_description TEXT,
    sort_order INT DEFAULT 0,
    view_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES store_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_category (category_id),
    INDEX idx_price (price),
    FULLTEXT INDEX idx_search (name, description)
);

-- Shopping cart table (for logged-in users)
CREATE TABLE IF NOT EXISTS store_cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product (user_id, product_id),
    INDEX idx_user (user_id)
);

-- Saved for later items
CREATE TABLE IF NOT EXISTS store_saved_for_later (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product_saved (user_id, product_id),
    INDEX idx_user_saved (user_id)
);

-- Orders table (for future use)
CREATE TABLE IF NOT EXISTS store_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    shipping_address TEXT,
    billing_address TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_order_number (order_number)
);

-- Order items table
CREATE TABLE IF NOT EXISTS store_order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES store_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE CASCADE,
    INDEX idx_order (order_id)
);

-- Insert default categories
INSERT INTO store_categories (name, slug, description, sort_order) VALUES
('Electronics', 'electronics', 'Electronic devices and gadgets', 1),
('Clothing', 'clothing', 'Fashion and apparel', 2),
('Books', 'books', 'Books and publications', 3),
('Home & Living', 'home-living', 'Home decor and living essentials', 4),
('Sports', 'sports', 'Sports equipment and gear', 5)
ON DUPLICATE KEY UPDATE name = name;

-- 009_create_store_orders.sql
-- Orders + order items. Each order item IS a "parcel": it gets its own unique
-- parcel_id and its own fulfillment_status that the admin advances. The order
-- holds the customer/payment info; items hold the per-product parcels.

CREATE TABLE IF NOT EXISTS store_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(32) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    customer_name VARCHAR(150) NOT NULL,
    contact_phone VARCHAR(20) NOT NULL,
    fulfillment_method ENUM('walkin','delivery') NOT NULL DEFAULT 'walkin',
    pickup_location VARCHAR(255) NULL,         -- pickup point (walk-in) OR delivery address
    delivery_notes VARCHAR(500) NULL,
    currency VARCHAR(8) NOT NULL DEFAULT 'KES',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_status ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(20) NOT NULL DEFAULT 'mpesa',
    mpesa_merchant_request_id VARCHAR(64) NULL,
    mpesa_checkout_request_id VARCHAR(64) NULL,
    mpesa_receipt VARCHAR(32) NULL,
    mpesa_phone VARCHAR(20) NULL,
    mpesa_payer_name VARCHAR(150) NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_orders_user (user_id),
    INDEX idx_orders_pay (payment_status),
    INDEX idx_orders_checkout (mpesa_checkout_request_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS store_order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    parcel_id VARCHAR(32) UNIQUE NOT NULL,
    product_id INT NULL,                        -- snapshot link (nullable if product later deleted)
    product_name VARCHAR(255) NOT NULL,         -- snapshot so receipts stay correct
    unit_price DECIMAL(12,2) NOT NULL,
    quantity INT NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    fulfillment_status ENUM('processing','ready_for_pickup','out_for_delivery','picked_up','delivered','arrived','cancelled')
        NOT NULL DEFAULT 'processing',
    fulfilled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_items_order (order_id),
    INDEX idx_items_parcel (parcel_id),
    INDEX idx_items_status (fulfillment_status),
    FOREIGN KEY (order_id) REFERENCES store_orders(id) ON DELETE CASCADE
);
-- ===== 010_fix_store_orders_schema.sql =====
-- 010_fix_store_orders_schema.sql
--
-- WHY: an older store_orders table exists with a different structure, so inserts
-- fail with "Unknown column 'customer_name'". Migration 009 used
-- CREATE TABLE IF NOT EXISTS, so it skipped the existing table.
--
-- WHAT THIS DOES: renames the existing order tables to *_backup (your old rows
-- are preserved there), then creates store_orders / store_order_items with the
-- exact columns OrderModel expects. New foreign keys are explicitly named so
-- they can't clash with the backup tables' auto-named constraints.
--
-- Run this whole file once in phpMyAdmin / your DB tool. After confirming
-- checkout works, you can DROP the *_backup tables if you don't need the old data.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS store_orders_backup;
DROP TABLE IF EXISTS store_order_items_backup;

ALTER TABLE store_orders      RENAME TO store_orders_backup;
ALTER TABLE store_order_items RENAME TO store_order_items_backup;

CREATE TABLE store_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(32) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    customer_name VARCHAR(150) NOT NULL,
    contact_phone VARCHAR(20) NOT NULL,
    fulfillment_method ENUM('walkin','delivery') NOT NULL DEFAULT 'walkin',
    pickup_location VARCHAR(255) NULL,
    delivery_notes VARCHAR(500) NULL,
    currency VARCHAR(8) NOT NULL DEFAULT 'KES',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_status ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(20) NOT NULL DEFAULT 'mpesa',
    mpesa_merchant_request_id VARCHAR(64) NULL,
    mpesa_checkout_request_id VARCHAR(64) NULL,
    mpesa_receipt VARCHAR(32) NULL,
    mpesa_phone VARCHAR(20) NULL,
    mpesa_payer_name VARCHAR(150) NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_orders_user (user_id),
    INDEX idx_orders_pay (payment_status),
    INDEX idx_orders_checkout (mpesa_checkout_request_id),
    CONSTRAINT fk_orders_user_v2 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE store_order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    parcel_id VARCHAR(32) UNIQUE NOT NULL,
    product_id INT NULL,
    product_name VARCHAR(255) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    quantity INT NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    fulfillment_status ENUM('processing','ready_for_pickup','out_for_delivery','picked_up','delivered','arrived','cancelled')
        NOT NULL DEFAULT 'processing',
    fulfilled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_items_order (order_id),
    INDEX idx_items_parcel (parcel_id),
    INDEX idx_items_status (fulfillment_status),
    CONSTRAINT fk_items_order_v2 FOREIGN KEY (order_id) REFERENCES store_orders(id) ON DELETE CASCADE
);

SET FOREIGN_KEY_CHECKS = 1;
-- ===== 011_create_enquiries_table.sql =====
-- Enquiries table for managing contact form submissions
CREATE TABLE IF NOT EXISTS enquiries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    service VARCHAR(100),
    message TEXT,
    status ENUM('new', 'read', 'contacted', 'closed') DEFAULT 'new',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    notes TEXT,
    contacted_at TIMESTAMP NULL,
    closed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_email (email),
    INDEX idx_created (created_at),
    FULLTEXT INDEX idx_search (name, email, message)
);

-- Create replies table for admin responses
CREATE TABLE IF NOT EXISTS enquiry_replies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    enquiry_id INT NOT NULL,
    admin_id INT NOT NULL,
    reply TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (enquiry_id) REFERENCES enquiries(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_enquiry (enquiry_id)
);

-- Insert sample status labels for reference
INSERT INTO enquiries (name, email, phone, service, message, status) VALUES
('Test User', 'test@example.com', '0712345678', 'Commercial Kitchen', 'This is a test enquiry', 'closed')
ON DUPLICATE KEY UPDATE id = id;
-- ===== 012_create_testimonials_table.sql =====
-- Testimonials table
CREATE TABLE IF NOT EXISTS testimonials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(100),
    customer_phone VARCHAR(20),
    customer_initial VARCHAR(5),
    rating INT DEFAULT 5,
    testimonial_text TEXT NOT NULL,
    service_tag VARCHAR(100),
    role VARCHAR(100),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    is_featured BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_rating (rating),
    INDEX idx_featured (is_featured),
    INDEX idx_sort (sort_order)
);

-- Insert sample testimonials
INSERT INTO testimonials (customer_name, customer_initial, rating, testimonial_text, service_tag, role, status, is_featured) VALUES
('James Mwangi', 'J', 5, 'ISMAN designed and installed our 450 sqm hotel kitchen in under 8 weeks. The SS304 fabrication quality exceeded international standards, and their team worked around our operational hours without a single disruption to guests.', 'Commercial Kitchen', 'General Manager, Radisson Blu Nairobi', 'approved', 1),
('Aisha Noor', 'A', 5, 'The stainless balustrade work at Two Rivers was flawless. Precision welds, perfect alignment across three floors, and delivered ahead of schedule. We have used them on every project since.', 'Stainless Railing', 'Project Lead, Centum Investment', 'approved', 1),
('Dr. Peter Otieno', 'P', 5, 'Their hospital fit-out met every infection-control requirement we set. Documentation was thorough and the finish on the SS316 surfaces is exactly what a sterile environment needs.', 'Hospital Fit-out', 'Facilities Director, Kenyatta National Hospital', 'approved', 1),
('Grace Wambui', 'G', 5, 'We commissioned a full processing line and ISMAN handled design, fabrication and install end to end. HACCP-ready, on budget, and running at full throughput from day one.', 'Food Processing', 'Operations Manager, Brookside Dairy', 'approved', 1)
ON DUPLICATE KEY UPDATE id = id;
-- ===== 013_create_tenancy_tables.sql =====
-- 013_create_tenancy_tables.sql
-- Foundation tables for multi-tenant SaaS POS.
-- Created fresh (no prior tenancy tables existed in the live DB).
-- Run once, in order, after the e-commerce strip.

-- A tenant = one shop owner's workspace. All POS data is scoped to a tenant.
CREATE TABLE IF NOT EXISTS tenants (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,                 -- shop name
    slug            VARCHAR(150) NOT NULL,                 -- for subdomain / clean URLs
    owner_user_id   INT NULL,                              -- FK -> users.id (set after owner is created)
    status          ENUM('active','suspended','cancelled') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_slug (slug),
    KEY idx_tenant_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Global catalogue of plans (NOT tenant-scoped). Prices are per interval; a NULL
-- price means that interval is not offered for this plan.
CREATE TABLE IF NOT EXISTS subscription_plans (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    description     VARCHAR(255) NULL,
    price_weekly    DECIMAL(10,2) NULL,
    price_biweekly  DECIMAL(10,2) NULL,
    price_monthly   DECIMAL(10,2) NULL,
    max_staff       INT NULL,                              -- NULL = unlimited
    max_products    INT NULL,                              -- NULL = unlimited
    features        JSON NULL,                             -- flexible feature flags
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_plan_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per tenant: their current subscription state. Access is gated on
-- current_period_end (+ grace_until) and status.
CREATE TABLE IF NOT EXISTS subscriptions (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id            INT NOT NULL,
    plan_id              INT NOT NULL,
    billing_interval     ENUM('weekly','biweekly','monthly') NOT NULL DEFAULT 'monthly',
    amount               DECIMAL(10,2) NOT NULL,            -- price locked at subscribe time
    status               ENUM('trialing','active','past_due','suspended','cancelled') NOT NULL DEFAULT 'trialing',
    current_period_start DATETIME NULL,
    current_period_end   DATETIME NULL,
    grace_until          DATETIME NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_sub_tenant (tenant_id),
    KEY idx_sub_status (status),
    KEY idx_sub_period_end (current_period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- ===== 014_add_tenant_and_permissions.sql =====
-- 014_add_tenant_and_permissions.sql
-- Adds tenant scoping to users and replaces the magic-number role scheme with
-- named, scoped roles + a default capability set + per-user overrides.
--
-- ASSUMPTIONS (verify against your real tables):
--   users  has columns: id, username, email, password_hash, role_id, is_active, email_verified
--   roles  has columns: id, role_name   (existing rows likely: superadmin, admin, user)
-- If your column names differ, adjust the ALTER/UPDATE lines accordingly.

-- 1) Scope users to a tenant. NULL tenant_id = platform-level user (super admin).
ALTER TABLE users
    ADD COLUMN tenant_id INT NULL AFTER id,
    ADD KEY idx_users_tenant (tenant_id);

-- A staff/owner email only needs to be unique *within* a tenant.
-- (Platform admins have tenant_id NULL; they are few and managed by hand.)
ALTER TABLE users
    ADD UNIQUE KEY uq_users_tenant_email (tenant_id, email);

-- 2) Give roles a scope + a default capability set.
ALTER TABLE roles
    ADD COLUMN scope ENUM('platform','tenant') NOT NULL DEFAULT 'tenant' AFTER role_name,
    ADD COLUMN capabilities JSON NULL AFTER scope;

-- Canonical roles. We seed by name and ignore if they already exist.
INSERT INTO roles (role_name, scope, capabilities)
SELECT * FROM (SELECT 'platform_admin' AS role_name, 'platform' AS scope, JSON_ARRAY('*') AS capabilities) AS t
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE r.role_name = 'platform_admin');

INSERT INTO roles (role_name, scope, capabilities)
SELECT * FROM (SELECT 'tenant_owner', 'tenant',
    JSON_ARRAY('inventory.view','inventory.edit','stock.enter','sales.record','sales.view',
               'customers.manage','catalogue.send','reports.view','staff.manage','settings.manage','billing.manage')) AS t
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE r.role_name = 'tenant_owner');

INSERT INTO roles (role_name, scope, capabilities)
SELECT * FROM (SELECT 'staff', 'tenant',
    JSON_ARRAY('inventory.view','sales.record','sales.view')) AS t
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE r.role_name = 'staff');

-- Map any legacy rows onto the new model (safe no-ops if they don't exist).
UPDATE roles SET scope='platform', capabilities=JSON_ARRAY('*')
    WHERE role_name='superadmin';
UPDATE roles SET scope='tenant',
    capabilities=JSON_ARRAY('inventory.view','inventory.edit','stock.enter','sales.record','sales.view',
                            'customers.manage','catalogue.send','reports.view','staff.manage','settings.manage','billing.manage')
    WHERE role_name='admin';
UPDATE roles SET scope='tenant', capabilities=JSON_ARRAY('inventory.view','sales.record','sales.view')
    WHERE role_name='user';

-- 3) Per-user capability overrides (e.g. "give this cashier stock-entry rights").
-- effect='grant' adds a capability; effect='revoke' removes one the role grants.
CREATE TABLE IF NOT EXISTS user_permissions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT NOT NULL,
    user_id     INT NOT NULL,
    capability  VARCHAR(64) NOT NULL,
    effect      ENUM('grant','revoke') NOT NULL DEFAULT 'grant',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_cap (user_id, capability),
    KEY idx_perm_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- ===== 015_registration_activation.sql =====
-- 015_registration_activation.sql
-- Adds email-activation fields to users, business-settings fields to tenants,
-- and seeds a couple of subscription plans so registration has something to offer.

-- Email activation / verification on the owner (and later staff) account.
ALTER TABLE users
    ADD COLUMN activation_token   VARCHAR(64) NULL AFTER email_verified,
    ADD COLUMN activation_expires DATETIME    NULL AFTER activation_token,
    ADD COLUMN activated_at       DATETIME    NULL AFTER activation_expires,
    ADD KEY idx_users_activation (activation_token);

-- Per-tenant business settings (set on the profile page after first login).
ALTER TABLE tenants
    ADD COLUMN logo_path      VARCHAR(255) NULL AFTER status,
    ADD COLUMN currency       VARCHAR(8)   NOT NULL DEFAULT 'KES' AFTER logo_path,
    ADD COLUMN phone          VARCHAR(30)  NULL AFTER currency,
    ADD COLUMN address        VARCHAR(255) NULL AFTER phone,
    ADD COLUMN receipt_footer VARCHAR(255) NULL AFTER address;

-- Seed plans (prices in KES; NULL interval price = not offered).
INSERT INTO subscription_plans (name, description, price_weekly, price_biweekly, price_monthly, max_staff, max_products, is_active)
VALUES
 ('Starter', 'For a single small shop',        300.00,  550.00, 1000.00,  3,  200, 1),
 ('Pro',     'Growing shops, more staff/stock', 700.00, 1300.00, 2500.00, 15, NULL, 1);
-- ===== 016_create_login_otps.sql =====
-- 016_create_login_otps.sql
-- Email OTP codes for mandatory 2FA on every login (owners now, staff later).
-- Codes are stored HASHED, are single-use, expire, and cap failed attempts.

CREATE TABLE IF NOT EXISTS login_otps (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    tenant_id    INT NULL,
    code_hash    VARCHAR(255) NOT NULL,
    purpose      VARCHAR(32)  NOT NULL DEFAULT 'login_2fa',
    attempts     TINYINT      NOT NULL DEFAULT 0,
    max_attempts TINYINT      NOT NULL DEFAULT 5,
    expires_at   DATETIME     NOT NULL,
    consumed_at  DATETIME     NULL,
    ip           VARCHAR(45)  NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_otp_user_purpose (user_id, purpose),
    KEY idx_otp_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- ===== 017_pricing_and_test_plan.sql =====
-- 017_pricing_and_test_plan.sql
-- 1) One production plan at the real prices: 1000/month, 500/2-weeks, 250/week.
-- 2) Retire the second seeded tier (kept, not deleted).
-- 3) Add a TEMPORARY KSh 10 / 2-week test plan, hidden from the public landing
--    page (is_public = 0) but selectable at registration so you can activate a
--    real account and exercise the subscription-gated features.
--
-- >>> TO REMOVE THE TEST PLAN BEFORE LAUNCH:
--     DELETE FROM subscription_plans WHERE name = 'Test (2 weeks)';

-- Separate "listed on the marketing page" from "selectable internally".
ALTER TABLE subscription_plans
    ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active;

-- Production plan: single plan, real prices.
UPDATE subscription_plans
   SET name           = 'Standard',
       description    = 'Everything you need to run your shop',
       price_weekly   = 250.00,
       price_biweekly = 500.00,
       price_monthly  = 1000.00,
       is_active      = 1,
       is_public      = 1
 WHERE name = 'Starter';

-- Retire the extra tier from the single-plan offering.
UPDATE subscription_plans SET is_active = 0, is_public = 0 WHERE name = 'Pro';

-- Temporary test plan: KSh 10 for 2 weeks. Hidden from the public page.
INSERT INTO subscription_plans
    (name, description, price_weekly, price_biweekly, price_monthly, max_staff, max_products, is_active, is_public)
SELECT * FROM (
    SELECT 'Test (2 weeks)' AS name,
           'Temporary test plan — remove before launch' AS description,
           NULL  AS price_weekly,
           10.00 AS price_biweekly,
           NULL  AS price_monthly,
           NULL  AS max_staff,
           NULL  AS max_products,
           1     AS is_active,
           0     AS is_public
) AS t
WHERE NOT EXISTS (SELECT 1 FROM subscription_plans WHERE name = 'Test (2 weeks)');
-- ===== 018_create_subscription_stk.sql =====
-- 018_create_subscription_stk.sql
-- Tracks each M-Pesa STK push for a subscription payment, from request through
-- callback. A successful callback activates the owner account + the subscription.

CREATE TABLE IF NOT EXISTS subscription_stk (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id           INT NOT NULL,
    user_id             INT NOT NULL,
    subscription_id     INT NULL,
    plan_id             INT NOT NULL,
    billing_interval    ENUM('weekly','biweekly','monthly') NOT NULL,
    amount              DECIMAL(10,2) NOT NULL,
    phone               VARCHAR(15) NOT NULL,
    checkout_request_id VARCHAR(64) NULL,
    merchant_request_id VARCHAR(64) NULL,
    status              ENUM('pending','success','failed','cancelled') NOT NULL DEFAULT 'pending',
    result_code         INT NULL,
    result_desc         VARCHAR(191) NULL,
    mpesa_receipt       VARCHAR(32) NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_stk_checkout (checkout_request_id),
    KEY idx_stk_tenant (tenant_id),
    KEY idx_stk_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- ===== 019_create_branches_and_staff.sql =====
-- 019_create_branches_and_staff.sql
-- Branches belong to a tenant and are uniquely named within that tenant.
-- Staff (users with the 'staff' role) are pinned to one branch and must reset
-- their auto-generated password on first login.

CREATE TABLE IF NOT EXISTS branches (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id  INT NOT NULL,
    title      VARCHAR(120) NOT NULL,
    location   VARCHAR(255) NULL,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_branch_tenant_title (tenant_id, title),
    KEY idx_branch_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pin users to a branch (NULL = owner/platform, operates across branches) and
-- flag accounts that must change a temporary password on next login.
ALTER TABLE users
    ADD COLUMN branch_id INT NULL AFTER tenant_id,
    ADD COLUMN must_reset_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash,
    ADD KEY idx_users_branch (branch_id);

-- Let owners manage branches. Re-set the tenant_owner default capability list to
-- include branches.manage (owners must log in again to pick it up).
UPDATE roles
   SET capabilities = JSON_ARRAY(
        'inventory.view','inventory.edit','stock.enter','sales.record','sales.view',
        'customers.manage','catalogue.send','reports.view',
        'branches.manage','staff.manage','settings.manage','billing.manage')
 WHERE role_name = 'tenant_owner';
-- ===== 020_create_inventory.sql =====
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
-- ===== 021_product_category_optional.sql =====
-- 021_product_category_optional.sql
-- Category (and subcategory) are optional on a product. Run this if you have
-- already applied 020; fresh installs get the nullable column from 020 directly.
ALTER TABLE products MODIFY category_id INT NULL;
-- ===== 022_create_sales.sql =====
-- 022_create_sales.sql
-- Recorded sales (no payment gateway) + line items. Tenant-scoped.

CREATE TABLE IF NOT EXISTS sales (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id      INT NOT NULL,
    branch_id      INT NULL,
    staff_id       INT NOT NULL,                       -- user who recorded the sale
    receipt_number VARCHAR(32) NOT NULL,
    payment_method ENUM('cash','mpesa') NOT NULL,
    total          DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount_given   DECIMAL(12,2) NULL,                 -- cash tendered (= total for mpesa)
    change_given   DECIMAL(12,2) NULL,
    customer_name  VARCHAR(120) NULL,
    customer_phone VARCHAR(30) NULL,
    customer_email VARCHAR(255) NULL,
    status         ENUM('completed','voided') NOT NULL DEFAULT 'completed',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sale_receipt (tenant_id, receipt_number),
    KEY idx_sale_tenant (tenant_id),
    KEY idx_sale_staff (staff_id),
    KEY idx_sale_branch (branch_id),
    KEY idx_sale_created (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sale_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id    INT NOT NULL,
    sale_id      INT NOT NULL,
    product_id   INT NULL,                             -- may be null if product later deleted
    product_name VARCHAR(160) NOT NULL,                -- snapshot at sale time
    unit         VARCHAR(20) NOT NULL DEFAULT 'piece',
    unit_price   DECIMAL(12,2) NOT NULL,               -- snapshot of selling price
    quantity     DECIMAL(12,2) NOT NULL,
    line_total   DECIMAL(12,2) NOT NULL,
    KEY idx_item_sale (sale_id),
    KEY idx_item_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- ===== 023_wholesale_retail_discounts.sql =====
-- 023_wholesale_retail_discounts.sql
-- Wholesale/retail pricing, sale-level discounts, and split cash+M-Pesa payments.

ALTER TABLE products
    ADD COLUMN wholesale_price DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER selling_price,
    ADD COLUMN retail_price    DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER wholesale_price;

UPDATE products
   SET retail_price = selling_price,
       wholesale_price = selling_price
 WHERE retail_price = 0;

ALTER TABLE sales
    ADD COLUMN sale_type ENUM('retail','wholesale') NOT NULL DEFAULT 'retail' AFTER staff_id,
    ADD COLUMN subtotal DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total,
    ADD COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER subtotal,
    ADD COLUMN cash_amount DECIMAL(12,2) NULL AFTER change_given,
    ADD COLUMN mpesa_amount DECIMAL(12,2) NULL AFTER cash_amount;

UPDATE sales SET subtotal = total WHERE subtotal = 0;

ALTER TABLE sales
    MODIFY payment_method ENUM('cash','mpesa','split') NOT NULL DEFAULT 'cash';

ALTER TABLE sale_items
    ADD COLUMN price_type ENUM('retail','wholesale') NOT NULL DEFAULT 'retail' AFTER unit_price;

-- ===== 024_suppliers_and_stock.sql =====
-- Active: 1785849373366@@127.0.0.1@3306@2in1_db
-- 024_suppliers_and_stock.sql
-- Suppliers + stock intake for a liquor-store style workflow: a supplier
-- brings a batch of products (new or restocks) in one delivery. Products gain
-- a branch (stock is per-branch), a supplier reference, and a bottle size.

CREATE TABLE IF NOT EXISTS suppliers (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id  INT NOT NULL,
    name       VARCHAR(160) NOT NULL,
    phone      VARCHAR(30) NULL,
    notes      VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_supplier_tenant_name (tenant_id, name),
    KEY idx_supplier_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE products
    ADD COLUMN supplier_id INT NULL AFTER subcategory_id,
    ADD COLUMN branch_id   INT NULL AFTER supplier_id,
    ADD COLUMN size_value  DECIMAL(10,2) NULL AFTER unit,
    ADD COLUMN size_unit   ENUM('ml','l') NULL AFTER size_value,
    ADD KEY idx_prod_supplier (supplier_id),
    ADD KEY idx_prod_branch (branch_id);

-- One row per delivery: who brought what, to which branch, entered by whom.
CREATE TABLE IF NOT EXISTS stock_intakes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT NOT NULL,
    supplier_id INT NOT NULL,
    branch_id   INT NULL,
    staff_id    INT NOT NULL,
    notes       VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_intake_tenant (tenant_id),
    KEY idx_intake_supplier (supplier_id),
    KEY idx_intake_branch (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Line items of a delivery (new product or restock) — kept for history even
-- if the product itself is edited/deleted later.
CREATE TABLE IF NOT EXISTS stock_intake_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT NOT NULL,
    stock_intake_id INT NOT NULL,
    product_id      INT NULL,
    product_name    VARCHAR(160) NOT NULL,
    quantity        DECIMAL(12,2) NOT NULL,
    buying_price    DECIMAL(12,2) NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_intakeitem_intake (stock_intake_id),
    KEY idx_intakeitem_tenant (tenant_id),
    KEY idx_intakeitem_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== 025_staff_pin_login.sql =====
-- Active: 1785849373366@@127.0.0.1@3306@2in1_db
-- 025_staff_pin_login.sql
-- Staff log in with a short PIN at a shared terminal instead of email +
-- password. Owners are unaffected (still email + password).

ALTER TABLE users
    ADD COLUMN pin_hash VARCHAR(255) NULL AFTER password_hash,
    ADD COLUMN position VARCHAR(100) NULL AFTER pin_hash;

-- ===== 026_payments_capability.sql =====
-- Active: 1785849373366@@127.0.0.1@3306@2in1_db
-- 026_payments_capability.sql
-- New capability: process payments (view all open tabs shop-wide, settle
-- them). Owners get it by default, same full-replace pattern used in
-- 019_create_branches_and_staff.sql for branches.manage.

UPDATE roles
   SET capabilities = JSON_ARRAY(
        'inventory.view','inventory.edit','stock.enter','sales.record','sales.view',
        'customers.manage','catalogue.send','reports.view',
        'branches.manage','staff.manage','settings.manage','billing.manage','payments.process')
 WHERE role_name = 'tenant_owner';

-- ===== 027_create_orders.sql =====
-- Active: 1785849373366@@127.0.0.1@3306@2in1_db
-- 027_create_orders.sql
-- Bar tabs: a server opens a tab for a table/customer, adds drinks over one
-- or more rounds, and someone with payment permission settles it later.
-- Separate from `sales` (which stays for direct/immediate sales).

CREATE TABLE IF NOT EXISTS orders (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id      INT NOT NULL,
    branch_id      INT NULL,
    table_name     VARCHAR(120) NOT NULL,
    opened_by      INT NOT NULL,
    receipt_number VARCHAR(32) NOT NULL,
    status         ENUM('open','paid','void') NOT NULL DEFAULT 'open',
    subtotal       DECIMAL(12,2) NOT NULL DEFAULT 0,
    total          DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_method ENUM('cash','mpesa','split') NULL,
    cash_amount    DECIMAL(12,2) NULL,
    mpesa_amount   DECIMAL(12,2) NULL,
    paid_by        INT NULL,
    paid_at        DATETIME NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_order_receipt (tenant_id, receipt_number),
    KEY idx_order_tenant (tenant_id),
    KEY idx_order_status (tenant_id, status),
    KEY idx_order_branch (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id    INT NOT NULL,
    order_id     INT NOT NULL,
    product_id   INT NULL,
    product_name VARCHAR(160) NOT NULL,
    unit_price   DECIMAL(12,2) NOT NULL,
    quantity     DECIMAL(12,2) NOT NULL,
    line_total   DECIMAL(12,2) NOT NULL,
    added_by     INT NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_orderitem_order (order_id),
    KEY idx_orderitem_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== 028_staff_time_logs.sql =====
-- 028_staff_time_logs.sql
-- Clock in / clock out records for staff. One open (clock_out_at IS NULL)
-- row per staff member at a time.

CREATE TABLE IF NOT EXISTS staff_time_logs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id    INT NOT NULL,
    user_id      INT NOT NULL,
    clock_in_at  DATETIME NOT NULL,
    clock_out_at DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_timelog_tenant (tenant_id),
    KEY idx_timelog_user_time (user_id, clock_in_at),
    KEY idx_timelog_open (user_id, clock_out_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== 029_order_cash_tendered.sql =====
-- 029_order_cash_tendered.sql
-- Track cash handed over and change owed on a settled tab, so the payments
-- page and receipt can show the balance given back to the customer.

ALTER TABLE orders
    ADD COLUMN amount_tendered DECIMAL(12,2) NULL AFTER mpesa_amount,
    ADD COLUMN change_due      DECIMAL(12,2) NULL AFTER amount_tendered;

-- ===== 030_tenant_kra_pin.sql =====
-- 030_tenant_kra_pin.sql
-- Optional KRA PIN for the receipt header — only shown when set.

ALTER TABLE tenants
    ADD COLUMN kra_pin VARCHAR(20) NULL AFTER receipt_footer;

-- ===== 031_held_orders.sql =====
-- Active: 1785849373366@@127.0.0.1@3306@2in1_db
-- 031_held_orders.sql
-- "Hold Order": a cart set aside before it becomes a real invoice/tab. Unlike
-- `orders`, holding does NOT touch stock — nothing is committed until the
-- held cart is resumed and checked out (which creates a real `orders` row
-- and discards the hold).

CREATE TABLE IF NOT EXISTS held_orders (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id     INT NOT NULL,
    branch_id     INT NULL,
    customer_name VARCHAR(120) NOT NULL,
    staff_id      INT NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_held_tenant (tenant_id),
    KEY idx_held_branch (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS held_order_items (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id      INT NOT NULL,
    held_order_id  INT NOT NULL,
    product_id     INT NULL,
    product_name   VARCHAR(160) NOT NULL,
    unit_price     DECIMAL(12,2) NOT NULL,
    quantity       DECIMAL(12,2) NOT NULL,
    KEY idx_helditem_held (held_order_id),
    KEY idx_helditem_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== 032_order_channel.sql =====
-- 032_order_channel.sql
-- Distinguish a walk-in sale (Home — always paid immediately, no invoice
-- concept) from a club/dine-in tab (Orders — starts unpaid, generates an
-- invoice). Same `orders` table; this just controls receipt wording and
-- keeps the two flows honest about what they are.

ALTER TABLE orders
    ADD COLUMN channel ENUM('walkin','tab') NOT NULL DEFAULT 'tab' AFTER table_name;

-- ===== 033_category_image.sql =====
-- 033_category_image.sql
-- Categories get an optional image, shown as a navigation card on the
-- staff selling screens (Home / New order).

ALTER TABLE categories
    ADD COLUMN image_path VARCHAR(255) NULL AFTER name;

-- ===== 034_drop_branches_manage_capability.sql =====
-- 034_drop_branches_manage_capability.sql
-- This is now a single-shop POS (no branch/multi-location support), so the
-- 'branches.manage' capability no longer exists in code. Strip it from the
-- tenant_owner role's default capability list. Same full-replace pattern
-- 026_payments_capability.sql used.

UPDATE roles
   SET capabilities = JSON_ARRAY(
        'inventory.view','inventory.edit','stock.enter','sales.record','sales.view',
        'payments.process','customers.manage','catalogue.send','reports.view',
        'staff.manage','settings.manage','billing.manage')
 WHERE role_name = 'tenant_owner';

-- Also remove any leftover per-user grants/revokes referencing the capability.
DELETE FROM user_permissions WHERE capability = 'branches.manage';

-- ===== 035_drop_branches_optional.sql =====
-- Active: 1785849373366@@127.0.0.1@3306@2in1_db
-- 035_drop_branches_optional.sql
--
-- ============================================================================
-- OPTIONAL / DESTRUCTIVE — DO NOT RUN AUTOMATICALLY.
-- This app is now a single-shop POS; branch/multi-location support has been
-- fully removed from the code (no BranchModel, no branch UI, no branch
-- filtering anywhere). This migration finishes the job at the DB level by
-- dropping the now-unused branch_id columns and the branches table itself.
--
-- Back up your database before running this. It is irreversible: any data
-- that was stored only in these columns (branch assignment history) is lost.
-- Everything else (products, sales, orders, stock, staff) is unaffected —
-- only the branch_id column/table goes away.
-- ============================================================================

ALTER TABLE users          DROP INDEX idx_users_branch,  DROP COLUMN branch_id;
ALTER TABLE sales           DROP INDEX idx_sale_branch,   DROP COLUMN branch_id;
ALTER TABLE products        DROP INDEX idx_prod_branch,   DROP COLUMN branch_id;
ALTER TABLE stock_intakes   DROP INDEX idx_intake_branch, DROP COLUMN branch_id;
ALTER TABLE orders          DROP INDEX idx_order_branch,  DROP COLUMN branch_id;
ALTER TABLE held_orders     DROP INDEX idx_held_branch,   DROP COLUMN branch_id;

DROP TABLE IF EXISTS branches;

-- ===== 036_bookshop_attributes.sql =====
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

-- ===== 037_stock_intake_supplier_optional.sql =====
-- 037_stock_intake_supplier_optional.sql
-- Supplier (who delivered a batch) is optional, not mandatory — a lot of
-- small deliveries don't have a formal distributor behind them.

ALTER TABLE stock_intakes MODIFY supplier_id INT NULL;

-- ===== 038_product_offers.sql =====
-- 038_product_offers.sql
-- Time-boxed retail offers: set an offer price + end date (and an optional
-- start date) on a book. While "now" is inside that window the effective
-- retail price is the offer price everywhere (till, receipts); once it
-- passes, the price reverts to normal on its own — nothing to clean up.

-- 037_stock_intake_supplier_optional.sql
-- Supplier (who delivered a batch) is optional, not mandatory — a lot of
-- small deliveries don't have a formal distributor behind them.


ALTER TABLE products
    ADD COLUMN offer_price     DECIMAL(12,2) NULL AFTER retail_price,
    ADD COLUMN offer_starts_at DATETIME NULL AFTER offer_price,
    ADD COLUMN offer_ends_at   DATETIME NULL AFTER offer_starts_at;

-- ===== 039_product_status_archived.sql =====
-- 039_product_status_archived.sql
-- A third product status: old titles the shop still owns but doesn't want
-- cluttering normal browsing. Archived books stay sellable (staff can pull
-- one from the Archive tab at the till) but drop out of the default
-- inventory listing and the Subject/Grade/Publisher/Offers browsing tabs.
-- 038_product_offers.sql
-- Time-boxed retail offers: set an offer price + end date (and an optional
-- start date) on a book. While "now" is inside that window the effective
-- retail price is the offer price everywhere (till, receipts); once it
-- passes, the price reverts to normal on its own — nothing to clean up.

-- 037_stock_intake_supplier_optional.sql
-- Supplier (who delivered a batch) is optional, not mandatory — a lot of
-- small deliveries don't have a formal distributor behind them.

ALTER TABLE products MODIFY status ENUM('active','draft','archived') NOT NULL DEFAULT 'active';

-- ===== 040_staff_time_log_enforcement.sql =====
-- 040_staff_time_log_enforcement.sql
-- Clock-in/out becomes calendar-day based instead of a rolling cooldown:
-- one clock-in per day unless the owner authorizes another, and a
-- forgotten clock-out gets auto-closed (flagged) instead of silently
-- reading as "still active" the next day.
-- 039_product_status_archived.sql
-- A third product status: old titles the shop still owns but doesn't want
-- cluttering normal browsing. Archived books stay sellable (staff can pull
-- one from the Archive tab at the till) but drop out of the default
-- inventory listing and the Subject/Grade/Publisher/Offers browsing tabs.
-- 038_product_offers.sql
-- Time-boxed retail offers: set an offer price + end date (and an optional
-- start date) on a book. While "now" is inside that window the effective
-- retail price is the offer price everywhere (till, receipts); once it
-- passes, the price reverts to normal on its own — nothing to clean up.

-- 037_stock_intake_supplier_optional.sql
-- Supplier (who delivered a batch) is optional, not mandatory — a lot of
-- small deliveries don't have a formal distributor behind them.

ALTER TABLE stock_intakes MODIFY supplier_id INT NULL;

ALTER TABLE products
    ADD COLUMN offer_price     DECIMAL(12,2) NULL AFTER retail_price,
    ADD COLUMN offer_starts_at DATETIME NULL AFTER offer_price,
    ADD COLUMN offer_ends_at   DATETIME NULL AFTER offer_starts_at;


ALTER TABLE products MODIFY status ENUM('active','draft','archived') NOT NULL DEFAULT 'active';

ALTER TABLE staff_time_logs
    ADD COLUMN auto_closed TINYINT(1) NOT NULL DEFAULT 0 AFTER clock_out_at;

-- One-time permission slip from the owner letting a staff member clock in
-- again today, after they've already completed (or are mid-way through) a
-- clock-in for today.
CREATE TABLE IF NOT EXISTS staff_reclock_authorizations (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id     INT NOT NULL,
    user_id       INT NOT NULL,
    authorized_by INT NOT NULL,
    authorized_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used_at       DATETIME NULL,
    KEY idx_reclock_user (tenant_id, user_id, used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== 041_order_credit_and_invoicing.sql =====
-- 041_order_credit_and_invoicing.sql
-- Discount at the point of sale (customers negotiate), a customer contact on
-- a tab so a credit sale (an open, unpaid order) can be followed up by
-- email, and flags for whether an invoice / delivery note has been sent.

ALTER TABLE orders
    ADD COLUMN customer_phone        VARCHAR(30) NULL AFTER table_name,
    ADD COLUMN customer_email        VARCHAR(255) NULL AFTER customer_phone,
    ADD COLUMN discount_amount       DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER subtotal,
    ADD COLUMN invoice_sent_at       DATETIME NULL,
    ADD COLUMN delivery_note_sent_at DATETIME NULL;
SET foreign_key_checks = 1;
