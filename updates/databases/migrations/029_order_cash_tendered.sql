-- 029_order_cash_tendered.sql
-- Track cash handed over and change owed on a settled tab, so the payments
-- page and receipt can show the balance given back to the customer.

ALTER TABLE orders
    ADD COLUMN amount_tendered DECIMAL(12,2) NULL AFTER mpesa_amount,
    ADD COLUMN change_due      DECIMAL(12,2) NULL AFTER amount_tendered;
