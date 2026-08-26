-- 049_tenant_receipt_contact_fields.sql
-- Separate receipt/contact fields so business identity does not get duplicated
-- through payment credentials.

ALTER TABLE tenants
    ADD COLUMN po_box VARCHAR(120) NULL AFTER address,
    ADD COLUMN business_email VARCHAR(190) NULL AFTER po_box;
