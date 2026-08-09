-- Active: 1785849373366@@127.0.0.1@3306@2in1_db
-- 026_payments_capability.sql
-- New capability: process payments (view all open tabs shop-wide, settle
-- them). Owners get it by default, same full-replace pattern used in
-- 019_create_branches_and_staff.sql for branches.manage.

UPDATE roles
   SET capabilities = JSON_ARRAY(
        'inventory.view','inventory.edit','stock.enter','sales.record','sales.view',
        'customers.manage','catalogue.send','reports.view',
        'branches.manage','staff.manage','settings.manage','billing.manage','payments.process')
 WHERE role_name = 'tenant_owner';
