-- 014_add_tenant_and_permissions.sql
-- Adds tenant scoping to users and replaces the magic-number role scheme with
-- named, scoped roles + a default capability set + per-user overrides.
--
-- ASSUMPTIONS (verify against your real tables):
--   users  has columns: id, username, email, password_hash, role_id, is_active, email_verified
--   roles  has columns: id, role_name   (existing rows likely: superadmin, admin, user)
-- If your column names differ, adjust the ALTER/UPDATE lines accordingly.

-- 1) Scope users to a tenant. NULL tenant_id = platform-level user (super admin).
ALTER TABLE users
    ADD COLUMN tenant_id INT NULL AFTER id,
    ADD KEY idx_users_tenant (tenant_id);

-- A staff/owner email only needs to be unique *within* a tenant.
-- (Platform admins have tenant_id NULL; they are few and managed by hand.)
ALTER TABLE users
    ADD UNIQUE KEY uq_users_tenant_email (tenant_id, email);

-- 2) Give roles a scope + a default capability set.
ALTER TABLE roles
    ADD COLUMN scope ENUM('platform','tenant') NOT NULL DEFAULT 'tenant' AFTER role_name,
    ADD COLUMN capabilities JSON NULL AFTER scope;

-- Canonical roles. We seed by name and ignore if they already exist.
INSERT INTO roles (role_name, scope, capabilities)
SELECT * FROM (SELECT 'platform_admin' AS role_name, 'platform' AS scope, JSON_ARRAY('*') AS capabilities) AS t
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE r.role_name = 'platform_admin');

INSERT INTO roles (role_name, scope, capabilities)
SELECT * FROM (SELECT 'tenant_owner', 'tenant',
    JSON_ARRAY('inventory.view','inventory.edit','stock.enter','sales.record','sales.view',
               'customers.manage','catalogue.send','reports.view','staff.manage','settings.manage','billing.manage')) AS t
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE r.role_name = 'tenant_owner');

INSERT INTO roles (role_name, scope, capabilities)
SELECT * FROM (SELECT 'staff', 'tenant',
    JSON_ARRAY('inventory.view','sales.record','sales.view')) AS t
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE r.role_name = 'staff');

-- Map any legacy rows onto the new model (safe no-ops if they don't exist).
UPDATE roles SET scope='platform', capabilities=JSON_ARRAY('*')
    WHERE role_name='superadmin';
UPDATE roles SET scope='tenant',
    capabilities=JSON_ARRAY('inventory.view','inventory.edit','stock.enter','sales.record','sales.view',
                            'customers.manage','catalogue.send','reports.view','staff.manage','settings.manage','billing.manage')
    WHERE role_name='admin';
UPDATE roles SET scope='tenant', capabilities=JSON_ARRAY('inventory.view','sales.record','sales.view')
    WHERE role_name='user';

-- 3) Per-user capability overrides (e.g. "give this cashier stock-entry rights").
-- effect='grant' adds a capability; effect='revoke' removes one the role grants.
CREATE TABLE IF NOT EXISTS user_permissions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT NOT NULL,
    user_id     INT NOT NULL,
    capability  VARCHAR(64) NOT NULL,
    effect      ENUM('grant','revoke') NOT NULL DEFAULT 'grant',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_cap (user_id, capability),
    KEY idx_perm_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;