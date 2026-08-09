-- 034_drop_branches_manage_capability.sql
-- This is now a single-shop POS (no branch/multi-location support), so the
-- 'branches.manage' capability no longer exists in code. Strip it from the
-- tenant_owner role's default capability list. Same full-replace pattern
-- 026_payments_capability.sql used.

UPDATE roles
   SET capabilities = JSON_ARRAY(
        'inventory.view','inventory.edit','stock.enter','sales.record','sales.view',
        'payments.process','customers.manage','catalogue.send','reports.view',
        'staff.manage','settings.manage','billing.manage')
 WHERE role_name = 'tenant_owner';

-- Also remove any leftover per-user grants/revokes referencing the capability.
DELETE FROM user_permissions WHERE capability = 'branches.manage';
