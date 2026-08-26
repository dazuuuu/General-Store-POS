-- Preserve retail package/carton sales as their own line-item mode, so one
-- sale can mix retail items, retail packages, and wholesale packages.

ALTER TABLE sale_items
    MODIFY price_type ENUM('retail','retail_pack','wholesale') NOT NULL DEFAULT 'retail';

ALTER TABLE order_items
    MODIFY price_type ENUM('retail','retail_pack','wholesale') NOT NULL DEFAULT 'retail';

ALTER TABLE held_order_items
    MODIFY price_type ENUM('retail','retail_pack','wholesale') NOT NULL DEFAULT 'retail';
