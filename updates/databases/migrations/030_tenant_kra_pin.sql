-- 030_tenant_kra_pin.sql
-- Optional KRA PIN for the receipt header — only shown when set.

ALTER TABLE tenants
    ADD COLUMN kra_pin VARCHAR(20) NULL AFTER receipt_footer;
