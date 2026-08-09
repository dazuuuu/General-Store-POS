-- 017_pricing_and_test_plan.sql
-- 1) One production plan at the real prices: 1000/month, 500/2-weeks, 250/week.
-- 2) Retire the second seeded tier (kept, not deleted).
-- 3) Add a TEMPORARY KSh 10 / 2-week test plan, hidden from the public landing
--    page (is_public = 0) but selectable at registration so you can activate a
--    real account and exercise the subscription-gated features.
--
-- >>> TO REMOVE THE TEST PLAN BEFORE LAUNCH:
--     DELETE FROM subscription_plans WHERE name = 'Test (2 weeks)';

-- Separate "listed on the marketing page" from "selectable internally".
ALTER TABLE subscription_plans
    ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active;

-- Production plan: single plan, real prices.
UPDATE subscription_plans
   SET name           = 'Standard',
       description    = 'Everything you need to run your shop',
       price_weekly   = 250.00,
       price_biweekly = 500.00,
       price_monthly  = 1000.00,
       is_active      = 1,
       is_public      = 1
 WHERE name = 'Starter';

-- Retire the extra tier from the single-plan offering.
UPDATE subscription_plans SET is_active = 0, is_public = 0 WHERE name = 'Pro';

-- Temporary test plan: KSh 10 for 2 weeks. Hidden from the public page.
INSERT INTO subscription_plans
    (name, description, price_weekly, price_biweekly, price_monthly, max_staff, max_products, is_active, is_public)
SELECT * FROM (
    SELECT 'Test (2 weeks)' AS name,
           'Temporary test plan — remove before launch' AS description,
           NULL  AS price_weekly,
           10.00 AS price_biweekly,
           NULL  AS price_monthly,
           NULL  AS max_staff,
           NULL  AS max_products,
           1     AS is_active,
           0     AS is_public
) AS t
WHERE NOT EXISTS (SELECT 1 FROM subscription_plans WHERE name = 'Test (2 weeks)');