-- 021_product_category_optional.sql
-- Category (and subcategory) are optional on a product. Run this if you have
-- already applied 020; fresh installs get the nullable column from 020 directly.
ALTER TABLE products MODIFY category_id INT NULL;