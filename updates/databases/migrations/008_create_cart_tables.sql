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