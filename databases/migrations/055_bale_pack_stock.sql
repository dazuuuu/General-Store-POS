-- 055_bale_pack_stock.sql
-- Package-aware stock intake. For wholesale package deliveries (bales,
-- cartons, packs, boxes, dozens, litres, etc.), products keep sellable
-- quantity as the inner item total, while package metadata preserves package
-- count and package wholesale/cost pricing.

ALTER TABLE stock_intake_items
    ADD COLUMN package_unit VARCHAR(20) NULL AFTER colors,
    ADD COLUMN package_quantity DECIMAL(12,2) NULL AFTER package_unit,
    ADD COLUMN units_per_package DECIMAL(12,2) NULL AFTER package_quantity,
    ADD COLUMN package_price DECIMAL(12,2) NULL AFTER units_per_package;

ALTER TABLE store_products
    ADD COLUMN package_unit VARCHAR(20) NULL AFTER unit,
    ADD COLUMN package_quantity DECIMAL(12,2) NULL AFTER package_unit,
    ADD COLUMN units_per_package DECIMAL(12,2) NULL AFTER package_quantity,
    ADD COLUMN package_price DECIMAL(12,2) NULL AFTER units_per_package;

ALTER TABLE store_invoice_items
    ADD COLUMN package_unit VARCHAR(20) NULL AFTER unit,
    ADD COLUMN package_quantity DECIMAL(12,2) NULL AFTER package_unit,
    ADD COLUMN units_per_package DECIMAL(12,2) NULL AFTER package_quantity,
    ADD COLUMN package_price DECIMAL(12,2) NULL AFTER units_per_package;
