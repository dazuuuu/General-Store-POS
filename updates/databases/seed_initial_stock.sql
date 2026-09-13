-- databases/seed_initial_stock.sql
-- Opening stock transcribed from the shop's paper stock ledger (Playgroup,
-- Grade Three, Grade Six pages). Run this once, in phpMyAdmin's SQL tab,
-- against the shop's live database, AFTER the schema is installed
-- (public/migrations/) and the admin account exists.
--
-- Selling price: the ledger only records the buying/unit price, not a
-- markup, so selling_price/retail_price are seeded equal to buying_price
-- (i.e. zero margin) below. Update real selling prices afterwards from
-- Inventory > Edit before selling anything — don't sell at these prices.
--
-- Safe to run more than once: every INSERT is guarded so it only adds a
-- row that isn't already there (matched by tenant + book title).

SET @tid = (SELECT id FROM tenants ORDER BY id ASC LIMIT 1);

-- ── Subjects ─────────────────────────────────────────────────────────
INSERT IGNORE INTO categories (tenant_id, name, status) VALUES
(@tid, 'Mathematics', 'active'),
(@tid, 'English', 'active'),
(@tid, 'Environmental', 'active'),
(@tid, 'C.R.E', 'active'),
(@tid, 'Kiswahili', 'active'),
(@tid, 'Handwriting', 'active'),
(@tid, 'Creative', 'active'),
(@tid, 'Science', 'active'),
(@tid, 'Agriculture', 'active'),
(@tid, 'Social Studies', 'active');

-- ── Grades ───────────────────────────────────────────────────────────
INSERT IGNORE INTO book_attributes (tenant_id, type, name) VALUES
(@tid, 'grade', 'Playgroup'),
(@tid, 'grade', 'Grade Three'),
(@tid, 'grade', 'Grade Six');

-- ── Publishers ───────────────────────────────────────────────────────
INSERT IGNORE INTO book_attributes (tenant_id, type, name) VALUES
(@tid, 'publisher', 'Smartbrain'),
(@tid, 'publisher', 'Queenex'),
(@tid, 'publisher', 'Moran'),
(@tid, 'publisher', 'Longhorn'),
(@tid, 'publisher', 'Spotlight'),
(@tid, 'publisher', 'Oxford'),
(@tid, 'publisher', 'Mentor'),
(@tid, 'publisher', 'Storymoja');

-- ── Authors ──────────────────────────────────────────────────────────
INSERT IGNORE INTO book_attributes (tenant_id, type, name) VALUES
(@tid, 'author', 'E.C.D.E Panel'),
(@tid, 'author', 'Mercy Kirui'),
(@tid, 'author', 'Tony Njoroge'),
(@tid, 'author', 'Ekari'),
(@tid, 'author', 'Rosemary Wambugu'),
(@tid, 'author', 'Kefa Masita'),
(@tid, 'author', 'Nsomi Kanyiri'),
(@tid, 'author', 'Julie Buholo'),
(@tid, 'author', 'Lucy Muiru'),
(@tid, 'author', 'Alice Njeru'),
(@tid, 'author', 'Stephen Adoch'),
(@tid, 'author', 'Awuondo Matei'),
(@tid, 'author', 'Benta Achieng'),
(@tid, 'author', 'Jane Omare'),
(@tid, 'author', 'Catherine Kiyiapi'),
(@tid, 'author', 'Lech Kanoki'),
(@tid, 'author', 'Jackline Ndege'),
(@tid, 'author', 'Ann Njoroge'),
(@tid, 'author', 'Hazron Onyango'),
(@tid, 'author', 'Jacob Odhiambo'),
(@tid, 'author', 'Steward Anyona'),
(@tid, 'author', 'Cephas Kamau'),
(@tid, 'author', 'Hezron Onyango'),
(@tid, 'author', 'G. Karanja'),
(@tid, 'author', 'A. Weronga'),
(@tid, 'author', 'Dickson Njoe'),
(@tid, 'author', 'James Njehia'),
(@tid, 'author', 'James Gachagua'),
(@tid, 'author', 'Tom Nyambeka'),
(@tid, 'author', 'Joy Kelemba'),
(@tid, 'author', 'Foster Kanr'),
(@tid, 'author', 'Evaline Chesiyot');

-- ── Editions ─────────────────────────────────────────────────────────
INSERT IGNORE INTO book_attributes (tenant_id, type, name) VALUES
(@tid, 'edition', 'First'),
(@tid, 'edition', 'Second'),
(@tid, 'edition', 'Third'),
(@tid, 'edition', 'Fifth');

-- ── Books ─────────────────────────────────────────────────────────────
INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Mathematics'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Smartbrain'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'E.C.D.E Panel'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Mastering numberwork', 4, 'piece', 400, 400, 400, 400, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Mastering numberwork'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Smartbrain')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'E.C.D.E Panel')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'English'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Smartbrain'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'E.C.D.E Panel'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'Second'),
  'Mastering a,b,c with pictures', 5, 'piece', 400, 400, 400, 400, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Mastering a,b,c with pictures'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Smartbrain')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'E.C.D.E Panel')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Environmental'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Smartbrain'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'E.C.D.E Panel'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Mastering environmental activities', 4, 'piece', 400, 400, 400, 400, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Mastering environmental activities'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Smartbrain')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'E.C.D.E Panel')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'C.R.E'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Smartbrain'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'E.C.D.E Panel'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Mastering Religious activities', 2, 'piece', 400, 400, 400, 400, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Mastering Religious activities'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Smartbrain')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'E.C.D.E Panel')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Mathematics'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Queenex'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Mercy Kirui'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Mathematical activities practice book', 5, 'piece', 450, 450, 450, 450, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Mathematical activities practice book'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Queenex')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Mercy Kirui')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'English'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Queenex'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Mercy Kirui'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Language activities practice book', 4, 'piece', 500, 500, 500, 500, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Language activities practice book'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Queenex')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Mercy Kirui')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Environmental'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Queenex'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Mercy Kirui'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Environmental activities practice book', 4, 'piece', 470, 470, 470, 470, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Environmental activities practice book'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Queenex')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Mercy Kirui')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Mathematics'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Queenex'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Tony Njoroge'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'Third'),
  'Premier mathematics workbook', 2, 'piece', 470, 470, 470, 470, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Premier mathematics workbook'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Queenex')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Tony Njoroge')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'English'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Queenex'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Tony Njoroge'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'Third'),
  'Premier sound workbook', 3, 'piece', 470, 470, 470, 470, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Premier sound workbook'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Queenex')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Tony Njoroge')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Kiswahili'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Queenex'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Tony Njoroge'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'Second'),
  'Kitabu changu cha Kiswahili', 5, 'piece', 350, 350, 350, 350, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Kitabu changu cha Kiswahili'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Queenex')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Tony Njoroge')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Handwriting'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Queenex'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Ekari'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Ekari handwriting activities', 2, 'piece', 400, 400, 400, 400, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Ekari handwriting activities'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Queenex')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Ekari')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'English'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Moran'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Rosemary Wambugu'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'Second'),
  'Sound activities level 1', 3, 'piece', 410, 410, 410, 410, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Sound activities level 1'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Moran')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Rosemary Wambugu')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Mathematics'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Moran'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Rosemary Wambugu'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'Second'),
  'Numberwork level 1', 3, 'piece', 430, 430, 430, 430, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Numberwork level 1'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Moran')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Rosemary Wambugu')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Handwriting'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Moran'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Rosemary Wambugu'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'Fifth'),
  'Handwriting level 1', 1, 'piece', 300, 300, 300, 300, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Handwriting level 1'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Playgroup')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Moran')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Rosemary Wambugu')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Mathematics'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Longhorn'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Kefa Masita'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Mathematical activities', 1, 'piece', 670, 670, 670, 670, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Mathematical activities'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Longhorn')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Kefa Masita')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'English'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Longhorn'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Nsomi Kanyiri'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'English activities', 2, 'piece', 750, 750, 750, 750, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'English activities'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Longhorn')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Nsomi Kanyiri')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Environmental'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Longhorn'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Julie Buholo'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Environmental activities', 2, 'piece', 720, 720, 720, 720, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Environmental activities'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Longhorn')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Julie Buholo')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'C.R.E'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Longhorn'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Lucy Muiru'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'C.R.E activities', 4, 'piece', 760, 760, 760, 760, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'C.R.E activities'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Longhorn')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Lucy Muiru')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Mathematics'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Spotlight'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Alice Njeru'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'Second'),
  'Mathematical activities', 2, 'piece', 760, 760, 760, 760, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Mathematical activities'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Spotlight')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Alice Njeru')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'English'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Spotlight'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Stephen Adoch'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'Second'),
  'English activities', 1, 'piece', 760, 760, 760, 760, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'English activities'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Spotlight')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Stephen Adoch')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Kiswahili'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Spotlight'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Awuondo Matei'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'Second'),
  'Kurunzi kiswahili', 2, 'piece', 760, 760, 760, 760, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Kurunzi kiswahili'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Spotlight')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Awuondo Matei')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Environmental'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Spotlight'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Benta Achieng'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'Second'),
  'Environmental activities', 1, 'piece', 700, 700, 700, 700, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Environmental activities'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Spotlight')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Benta Achieng')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Creative'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Spotlight'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Jane Omare'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Creative activities', 1, 'piece', 640, 640, 640, 640, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Creative activities'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Spotlight')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Jane Omare')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Mathematics'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Oxford'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Catherine Kiyiapi'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Let''s do mathematics', 1, 'piece', 620, 620, 620, 620, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Let''s do mathematics'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Oxford')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Catherine Kiyiapi')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'English'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Oxford'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Lech Kanoki'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'Third'),
  'New progressive primary english', 3, 'piece', 720, 720, 720, 720, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'New progressive primary english'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Oxford')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Lech Kanoki')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Kiswahili'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Oxford'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Jackline Ndege'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'Third'),
  'Kiswahili dadisi', 2, 'piece', 720, 720, 720, 720, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Kiswahili dadisi'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Oxford')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Jackline Ndege')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Environmental'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Oxford'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Ann Njoroge'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'Second'),
  'Our lives today environmental', 3, 'piece', 720, 720, 720, 720, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Our lives today environmental'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Oxford')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Ann Njoroge')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'C.R.E'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Oxford'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Hazron Onyango'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'Second'),
  'Growing in christ', 2, 'piece', 620, 620, 620, 620, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Growing in christ'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Three')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Oxford')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Hazron Onyango')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Science'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Oxford'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Jacob Odhiambo'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Everyday science', 2, 'piece', 750, 750, 750, 750, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Everyday science'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Oxford')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Jacob Odhiambo')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Agriculture'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Oxford'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Steward Anyona'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Modern Agriculture', 2, 'piece', 720, 720, 720, 720, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Modern Agriculture'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Oxford')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Steward Anyona')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Kiswahili'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Oxford'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Jackline Ndege'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Kiswahili dadisi', 3, 'piece', 750, 750, 750, 750, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Kiswahili dadisi'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Oxford')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Jackline Ndege')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Social Studies'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Oxford'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Cephas Kamau'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Our lives today social', 4, 'piece', 720, 720, 720, 720, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Our lives today social'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Oxford')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Cephas Kamau')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'C.R.E'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Oxford'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Hezron Onyango'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Growing in christ C.R.E', 4, 'piece', 720, 720, 720, 720, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Growing in christ C.R.E'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Oxford')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Hezron Onyango')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Science'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Mentor'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'G. Karanja'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Science', 2, 'piece', 720, 720, 720, 720, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Science'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Mentor')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'G. Karanja')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Agriculture'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Mentor'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'A. Weronga'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Agriculture', 2, 'piece', 630, 630, 630, 630, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Agriculture'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Mentor')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'A. Weronga')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Social Studies'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Mentor'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Dickson Njoe'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Social studies', 2, 'piece', 760, 760, 760, 760, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Social studies'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Mentor')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Dickson Njoe')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'C.R.E'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Mentor'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'James Njehia'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Christian Religious education', 2, 'piece', 720, 720, 720, 720, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Christian Religious education'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Mentor')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'James Njehia')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'English'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Storymoja'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'James Gachagua'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Spark composition', 2, 'piece', 670, 670, 670, 670, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Spark composition'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Storymoja')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'James Gachagua')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Kiswahili'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Storymoja'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Tom Nyambeka'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Chechefu za kiswahili', 2, 'piece', 620, 620, 620, 620, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Chechefu za kiswahili'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Storymoja')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Tom Nyambeka')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Science'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Storymoja'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Joy Kelemba'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Smart beginner science', 3, 'piece', 620, 620, 620, 620, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Smart beginner science'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Storymoja')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Joy Kelemba')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'Agriculture'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Storymoja'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Foster Kanr'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Smart beginner Agriculture', 2, 'piece', 720, 720, 720, 720, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Smart beginner Agriculture'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Storymoja')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Foster Kanr')
);

INSERT INTO products
  (tenant_id, category_id, grade_id, publisher_id, author_id, edition_id,
   name, quantity, unit, buying_price, selling_price, wholesale_price, retail_price,
   low_stock_threshold, status)
SELECT @tid,
  (SELECT id FROM categories WHERE tenant_id = @tid AND name = 'C.R.E'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Storymoja'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Evaline Chesiyot'),
  (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'edition' AND name = 'First'),
  'Smart beginner C.R.E', 2, 'piece', 620, 620, 620, 620, 2, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM products WHERE tenant_id = @tid AND name = 'Smart beginner C.R.E'
  AND grade_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'grade' AND name = 'Grade Six')
  AND publisher_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'publisher' AND name = 'Storymoja')
  AND author_id = (SELECT id FROM book_attributes WHERE tenant_id = @tid AND type = 'author' AND name = 'Evaline Chesiyot')
);

