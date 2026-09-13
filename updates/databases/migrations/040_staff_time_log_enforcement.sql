-- 040_staff_time_log_enforcement.sql
-- Clock-in/out becomes calendar-day based instead of a rolling cooldown:
-- one clock-in per day unless the owner authorizes another, and a
-- forgotten clock-out gets auto-closed (flagged) instead of silently
-- reading as "still active" the next day.
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

ALTER TABLE stock_intakes MODIFY supplier_id INT NULL;

ALTER TABLE products
    ADD COLUMN offer_price     DECIMAL(12,2) NULL AFTER retail_price,
    ADD COLUMN offer_starts_at DATETIME NULL AFTER offer_price,
    ADD COLUMN offer_ends_at   DATETIME NULL AFTER offer_starts_at;


ALTER TABLE products MODIFY status ENUM('active','draft','archived') NOT NULL DEFAULT 'active';

ALTER TABLE staff_time_logs
    ADD COLUMN auto_closed TINYINT(1) NOT NULL DEFAULT 0 AFTER clock_out_at;

-- One-time permission slip from the owner letting a staff member clock in
-- again today, after they've already completed (or are mid-way through) a
-- clock-in for today.
CREATE TABLE IF NOT EXISTS staff_reclock_authorizations (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id     INT NOT NULL,
    user_id       INT NOT NULL,
    authorized_by INT NOT NULL,
    authorized_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used_at       DATETIME NULL,
    KEY idx_reclock_user (tenant_id, user_id, used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
