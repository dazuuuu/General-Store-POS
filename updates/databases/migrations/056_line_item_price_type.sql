-- 056_line_item_price_type.sql
-- Allows one sale/invoice to contain both retail-priced and wholesale-priced
-- line items.

ALTER TABLE order_items
    ADD COLUMN price_type ENUM('retail','wholesale') NOT NULL DEFAULT 'retail' AFTER unit_price;

ALTER TABLE held_order_items
    ADD COLUMN price_type ENUM('retail','wholesale') NOT NULL DEFAULT 'retail' AFTER unit_price;
