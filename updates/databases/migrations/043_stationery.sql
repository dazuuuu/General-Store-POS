-- 043_stationery.sql
-- Sell stationery (pens, geometrical sets, erasers…) through the same
-- catalogue, till and inventory as books. A product is now typed
-- ('book'/'stationery'); stationery gets its own category namespace
-- (Pens, Geometry sets…) kept separate from book Subjects even though both
-- live in the `categories` table, plus a reusable Brand attribute alongside
-- Grade/Publisher/Author/Edition. Colors and variants (e.g. "0.5mm, 0.7mm")
-- reuse the `colors`/`sizes` JSON columns already on products, unused until now.

ALTER TABLE products
    ADD COLUMN product_type ENUM('book','stationery') NOT NULL DEFAULT 'book' AFTER tenant_id;

ALTER TABLE products
    ADD COLUMN brand_id INT NULL AFTER edition_id,
    ADD KEY idx_prod_brand (brand_id);

ALTER TABLE categories
    ADD COLUMN type ENUM('subject','stationery') NOT NULL DEFAULT 'subject' AFTER name;

-- Was (tenant_id, name) — widen so a Subject and a Stationery category can
-- share a name without colliding (e.g. "Art" as both).
ALTER TABLE categories
    DROP INDEX uq_cat_tenant_name,
    ADD UNIQUE KEY uq_cat_tenant_type_name (tenant_id, type, name);

ALTER TABLE book_attributes
    MODIFY COLUMN type ENUM('grade','publisher','author','edition','brand') NOT NULL;
