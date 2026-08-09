-- Active: 1785849373366@@127.0.0.1@3306@2in1_db
-- 035_drop_branches_optional.sql
--
-- ============================================================================
-- OPTIONAL / DESTRUCTIVE — DO NOT RUN AUTOMATICALLY.
-- This app is now a single-shop POS; branch/multi-location support has been
-- fully removed from the code (no BranchModel, no branch UI, no branch
-- filtering anywhere). This migration finishes the job at the DB level by
-- dropping the now-unused branch_id columns and the branches table itself.
--
-- Back up your database before running this. It is irreversible: any data
-- that was stored only in these columns (branch assignment history) is lost.
-- Everything else (products, sales, orders, stock, staff) is unaffected —
-- only the branch_id column/table goes away.
-- ============================================================================

ALTER TABLE users          DROP INDEX idx_users_branch,  DROP COLUMN branch_id;
ALTER TABLE sales           DROP INDEX idx_sale_branch,   DROP COLUMN branch_id;
ALTER TABLE products        DROP INDEX idx_prod_branch,   DROP COLUMN branch_id;
ALTER TABLE stock_intakes   DROP INDEX idx_intake_branch, DROP COLUMN branch_id;
ALTER TABLE orders          DROP INDEX idx_order_branch,  DROP COLUMN branch_id;
ALTER TABLE held_orders     DROP INDEX idx_held_branch,   DROP COLUMN branch_id;

DROP TABLE IF EXISTS branches;
