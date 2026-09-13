-- 019_create_branches_and_staff.sql
-- Branches belong to a tenant and are uniquely named within that tenant.
-- Staff (users with the 'staff' role) are pinned to one branch and must reset
-- their auto-generated password on first login.

CREATE TABLE IF NOT EXISTS branches (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id  INT NOT NULL,
    title      VARCHAR(120) NOT NULL,
    location   VARCHAR(255) NULL,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_branch_tenant_title (tenant_id, title),
    KEY idx_branch_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pin users to a branch (NULL = owner/platform, operates across branches) and
-- flag accounts that must change a temporary password on next login.
ALTER TABLE users
    ADD COLUMN branch_id INT NULL AFTER tenant_id,
    ADD COLUMN must_reset_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash,
    ADD KEY idx_users_branch (branch_id);

-- Let owners manage branches. Re-set the tenant_owner default capability list to
-- include branches.manage (owners must log in again to pick it up).
UPDATE roles
   SET capabilities = JSON_ARRAY(
        'inventory.view','inventory.edit','stock.enter','sales.record','sales.view',
        'customers.manage','catalogue.send','reports.view',
        'branches.manage','staff.manage','settings.manage','billing.manage')
 WHERE role_name = 'tenant_owner';