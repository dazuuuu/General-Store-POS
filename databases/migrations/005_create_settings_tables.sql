-- =====================================================================
-- 005_create_settings_tables.sql
-- Site settings (logo + site name), homepage hero slides, and the
-- per-page header banners used by the public layout.
-- Safe to run more than once (IF NOT EXISTS + idempotent seeds).
-- =====================================================================

-- ---------------------------------------------------------------------
-- Key/value singletons: logo, site name, etc.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS site_settings (
    setting_key   VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_settings (setting_key, setting_value) VALUES
    ('site_name', 'Ismano'),
    ('logo_path', NULL),
    ('logo_alt',  'Ismano')
ON DUPLICATE KEY UPDATE setting_key = setting_key;   -- no-op if row exists

-- ---------------------------------------------------------------------
-- Homepage hero slideshow images (managed by admin).
-- image_path is stored relative to /public  (e.g. uploads/hero/x.png)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS hero_slides (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    image_path  VARCHAR(255) NOT NULL,
    caption     VARCHAR(255) NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hero_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Per-page header banners for public pages (services, projects, ...).
-- One row per page_key; image managed by admin.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS page_headers (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    page_key    VARCHAR(60)  NOT NULL UNIQUE,
    title       VARCHAR(150) NULL,
    subtitle    VARCHAR(255) NULL,
    image_path  VARCHAR(255) NULL,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO page_headers (page_key, title, subtitle) VALUES
    ('services', 'Our Services', 'Comprehensive digital solutions tailored to elevate your business.'),
    ('projects', 'Our Projects', 'A selection of the work we are proud of.'),
    ('blogs',    'Our Blog',     'Insights, ideas and updates from the team.'),
    ('contact',  'Get in Touch', 'We would love to hear about your project.')
ON DUPLICATE KEY UPDATE page_key = page_key;