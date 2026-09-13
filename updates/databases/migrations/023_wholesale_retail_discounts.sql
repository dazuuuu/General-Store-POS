-- 023_wholesale_retail_discounts.sql
-- Wholesale/retail pricing, sale-level discounts, and split cash+M-Pesa payments.

ALTER TABLE products
    ADD COLUMN wholesale_price DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER selling_price,
    ADD COLUMN retail_price    DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER wholesale_price;

UPDATE products
   SET retail_price = selling_price,
       wholesale_price = selling_price
 WHERE retail_price = 0;

ALTER TABLE sales
    ADD COLUMN sale_type ENUM('retail','wholesale') NOT NULL DEFAULT 'retail' AFTER staff_id,
    ADD COLUMN subtotal DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total,
    ADD COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER subtotal,
    ADD COLUMN cash_amount DECIMAL(12,2) NULL AFTER change_given,
    ADD COLUMN mpesa_amount DECIMAL(12,2) NULL AFTER cash_amount;

UPDATE sales SET subtotal = total WHERE subtotal = 0;

ALTER TABLE sales
    MODIFY payment_method ENUM('cash','mpesa','split') NOT NULL DEFAULT 'cash';

ALTER TABLE sale_items
    ADD COLUMN price_type ENUM('retail','wholesale') NOT NULL DEFAULT 'retail' AFTER unit_price;
