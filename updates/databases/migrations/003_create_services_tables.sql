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