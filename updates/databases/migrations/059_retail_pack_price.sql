-- Retail selling price of a whole package (carton/bale/pack), alongside
-- pack_price which remains the wholesale package price.

ALTER TABLE products
    ADD COLUMN retail_pack_price DECIMAL(12,2) NULL AFTER pack_price;

ALTER TABLE store_products
    ADD COLUMN retail_pack_price DECIMAL(12,2) NULL AFTER package_price;

ALTER TABLE stock_intake_items
    ADD COLUMN retail_pack_price DECIMAL(12,2) NULL AFTER package_price;
