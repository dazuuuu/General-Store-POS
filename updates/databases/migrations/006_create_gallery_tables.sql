-- Active: 1780050571987@@127.0.0.1@3306@modern_db
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