-- 013_create_tenancy_tables.sql
-- Foundation tables for multi-tenant SaaS POS.
-- Created fresh (no prior tenancy tables existed in the live DB).
-- Run once, in order, after the e-commerce strip.

-- A tenant = one shop owner's workspace. All POS data is scoped to a tenant.
CREATE TABLE IF NOT EXISTS tenants (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,                 -- shop name
    slug            VARCHAR(150) NOT NULL,                 -- for subdomain / clean URLs
    owner_user_id   INT NULL,                              -- FK -> users.id (set after owner is created)
    status          ENUM('active','suspended','cancelled') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_slug (slug),
    KEY idx_tenant_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Global catalogue of plans (NOT tenant-scoped). Prices are per interval; a NULL
-- price means that interval is not offered for this plan.
CREATE TABLE IF NOT EXISTS subscription_plans (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    description     VARCHAR(255) NULL,
    price_weekly    DECIMAL(10,2) NULL,
    price_biweekly  DECIMAL(10,2) NULL,
    price_monthly   DECIMAL(10,2) NULL,
    max_staff       INT NULL,                              -- NULL = unlimited
    max_products    INT NULL,                              -- NULL = unlimited
    features        JSON NULL,                             -- flexible feature flags
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_plan_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per tenant: their current subscription state. Access is gated on
-- current_period_end (+ grace_until) and status.
CREATE TABLE IF NOT EXISTS subscriptions (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id            INT NOT NULL,
    plan_id              INT NOT NULL,
    billing_interval     ENUM('weekly','biweekly','monthly') NOT NULL DEFAULT 'monthly',
    amount               DECIMAL(10,2) NOT NULL,            -- price locked at subscribe time
    status               ENUM('trialing','active','past_due','suspended','cancelled') NOT NULL DEFAULT 'trialing',
    current_period_start DATETIME NULL,
    current_period_end   DATETIME NULL,
    grace_until          DATETIME NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_sub_tenant (tenant_id),
    KEY idx_sub_status (status),
    KEY idx_sub_period_end (current_period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;