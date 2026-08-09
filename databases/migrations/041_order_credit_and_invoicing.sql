-- 041_order_credit_and_invoicing.sql
-- Discount at the point of sale (customers negotiate), a customer contact on
-- a tab so a credit sale (an open, unpaid order) can be followed up by
-- email, and flags for whether an invoice / delivery note has been sent.

ALTER TABLE orders
    ADD COLUMN customer_phone        VARCHAR(30) NULL AFTER table_name,
    ADD COLUMN customer_email        VARCHAR(255) NULL AFTER customer_phone,
    ADD COLUMN discount_amount       DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER subtotal,
    ADD COLUMN invoice_sent_at       DATETIME NULL,
    ADD COLUMN delivery_note_sent_at DATETIME NULL;
