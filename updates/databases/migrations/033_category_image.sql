-- 033_category_image.sql
-- Categories get an optional image, shown as a navigation card on the
-- staff selling screens (Home / New order).

ALTER TABLE categories
    ADD COLUMN image_path VARCHAR(255) NULL AFTER name;
