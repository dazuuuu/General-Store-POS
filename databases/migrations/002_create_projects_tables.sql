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