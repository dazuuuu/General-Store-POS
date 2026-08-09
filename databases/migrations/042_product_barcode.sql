-- 042_product_barcode.sql
-- Per-book barcode: scan a printed barcode while recording stock to reuse
-- the right book automatically, and scan at the till to add it to the cart
-- straight away. NULL is fine (and stays fine for many rows — this is
-- optional per book), the unique key only kicks in once a barcode is set.

ALTER TABLE products
    ADD COLUMN barcode VARCHAR(64) NULL AFTER edition_id;

ALTER TABLE products
    ADD UNIQUE KEY uq_prod_tenant_barcode (tenant_id, barcode);
