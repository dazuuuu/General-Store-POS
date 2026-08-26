-- 047_tenant_payment_credentials.sql
-- Optional payment instructions shown on emailed bulk invoices.

ALTER TABLE tenants
    ADD COLUMN payment_credentials TEXT NULL AFTER kra_pin;
