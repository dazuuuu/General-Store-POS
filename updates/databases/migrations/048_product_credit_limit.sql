-- 048_product_credit_limit.sql
-- Per-product ceiling for unpaid credit lines. Paid counter sales are not
-- blocked by this limit; credit/bulk invoices are.

ALTER TABLE products
    ADD COLUMN credit_limit DECIMAL(12,2) NULL AFTER low_stock_threshold;
