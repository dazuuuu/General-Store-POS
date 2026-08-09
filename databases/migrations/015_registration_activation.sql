-- 015_registration_activation.sql
-- Adds email-activation fields to users, business-settings fields to tenants,
-- and seeds a couple of subscription plans so registration has something to offer.

-- Email activation / verification on the owner (and later staff) account.
ALTER TABLE users
    ADD COLUMN activation_token   VARCHAR(64) NULL AFTER email_verified,
    ADD COLUMN activation_expires DATETIME    NULL AFTER activation_token,
    ADD COLUMN activated_at       DATETIME    NULL AFTER activation_expires,
    ADD KEY idx_users_activation (activation_token);

-- Per-tenant business settings (set on the profile page after first login).
ALTER TABLE tenants
    ADD COLUMN logo_path      VARCHAR(255) NULL AFTER status,
    ADD COLUMN currency       VARCHAR(8)   NOT NULL DEFAULT 'KES' AFTER logo_path,
    ADD COLUMN phone          VARCHAR(30)  NULL AFTER currency,
    ADD COLUMN address        VARCHAR(255) NULL AFTER phone,
    ADD COLUMN receipt_footer VARCHAR(255) NULL AFTER address;

-- Seed plans (prices in KES; NULL interval price = not offered).
INSERT INTO subscription_plans (name, description, price_weekly, price_biweekly, price_monthly, max_staff, max_products, is_active)
VALUES
 ('Starter', 'For a single small shop',        300.00,  550.00, 1000.00,  3,  200, 1),
 ('Pro',     'Growing shops, more staff/stock', 700.00, 1300.00, 2500.00, 15, NULL, 1);