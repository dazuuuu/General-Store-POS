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