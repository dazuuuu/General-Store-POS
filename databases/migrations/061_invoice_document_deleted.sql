-- Allow admins to hide generated/sold invoice documents without cascading
-- to products, sales, stock, or payment rows.
ALTER TABLE orders
    ADD COLUMN invoice_deleted_at DATETIME NULL AFTER updated_at,
    ADD COLUMN invoice_deleted_by INT NULL AFTER invoice_deleted_at;

ALTER TABLE orders
    ADD KEY idx_order_invoice_deleted (tenant_id, invoice_deleted_at);
