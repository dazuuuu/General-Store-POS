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