-- 045_general_product_units.sql
-- Faulty/broken stock tracking and expanded receive units for a general shop.

ALTER TABLE products
    ADD COLUMN faulty_quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER quantity;

ALTER TABLE stock_intake_items
    ADD COLUMN faulty_quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER quantity,
    ADD COLUMN unit VARCHAR(20) NULL AFTER faulty_quantity,
    ADD COLUMN colors VARCHAR(255) NULL AFTER unit;
