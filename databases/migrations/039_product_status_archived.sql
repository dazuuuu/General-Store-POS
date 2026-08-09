-- 039_product_status_archived.sql
-- A third product status: old titles the shop still owns but doesn't want
-- cluttering normal browsing. Archived books stay sellable (staff can pull
-- one from the Archive tab at the till) but drop out of the default
-- inventory listing and the Subject/Grade/Publisher/Offers browsing tabs.
-- 038_product_offers.sql
-- Time-boxed retail offers: set an offer price + end date (and an optional
-- start date) on a book. While "now" is inside that window the effective
-- retail price is the offer price everywhere (till, receipts); once it
-- passes, the price reverts to normal on its own — nothing to clean up.

-- 037_stock_intake_supplier_optional.sql
-- Supplier (who delivered a batch) is optional, not mandatory — a lot of
-- small deliveries don't have a formal distributor behind them.

ALTER TABLE products MODIFY status ENUM('active','draft','archived') NOT NULL DEFAULT 'active';
