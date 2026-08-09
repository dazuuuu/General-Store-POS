-- 037_stock_intake_supplier_optional.sql
-- Supplier (who delivered a batch) is optional, not mandatory — a lot of
-- small deliveries don't have a formal distributor behind them.

ALTER TABLE stock_intakes MODIFY supplier_id INT NULL;
