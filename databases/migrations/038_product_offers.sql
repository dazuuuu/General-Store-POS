-- 038_product_offers.sql
-- Time-boxed retail offers: set an offer price + end date (and an optional
-- start date) on a book. While "now" is inside that window the effective
-- retail price is the offer price everywhere (till, receipts); once it
-- passes, the price reverts to normal on its own — nothing to clean up.

-- 037_stock_intake_supplier_optional.sql
-- Supplier (who delivered a batch) is optional, not mandatory — a lot of
-- small deliveries don't have a formal distributor behind them.


ALTER TABLE products
    ADD COLUMN offer_price     DECIMAL(12,2) NULL AFTER retail_price,
    ADD COLUMN offer_starts_at DATETIME NULL AFTER offer_price,
    ADD COLUMN offer_ends_at   DATETIME NULL AFTER offer_starts_at;
