-- 052_return_inventory_migration.sql
-- Returned products now wait for an inventory admin to migrate sellable
-- returned stock back into products.quantity.

ALTER TABLE product_returns
    ADD COLUMN migrated_at DATETIME NULL AFTER processed_by,
    ADD COLUMN migrated_by INT NULL AFTER migrated_at;
