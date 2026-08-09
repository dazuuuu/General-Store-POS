-- 032_order_channel.sql
-- Distinguish a walk-in sale (Home — always paid immediately, no invoice
-- concept) from a club/dine-in tab (Orders — starts unpaid, generates an
-- invoice). Same `orders` table; this just controls receipt wording and
-- keeps the two flows honest about what they are.

ALTER TABLE orders
    ADD COLUMN channel ENUM('walkin','tab') NOT NULL DEFAULT 'tab' AFTER table_name;
