-- 046_payment_details.sql
-- Store payment metadata for completed sales: M-Pesa display name, card type,
-- bank/SACCO name, and optional transaction reference.

ALTER TABLE orders
    MODIFY COLUMN payment_method ENUM('cash','mpesa','split','card','bank','sacco','credit') DEFAULT NULL,
    ADD COLUMN payment_provider VARCHAR(100) NULL AFTER payment_method,
    ADD COLUMN payment_account_name VARCHAR(160) NULL AFTER payment_provider,
    ADD COLUMN payment_reference VARCHAR(120) NULL AFTER payment_account_name;

