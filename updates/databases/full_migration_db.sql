-- =============================================================================
-- full_migration_db.sql
-- ONE-SHOT, FRESH-INSTALL SCHEMA for going live.
-- Generated from the current app/models/*.php contract and the verified
-- databases/full_schema.sql snapshot. This replaces
-- databases/full_complete_migrations.sql for fresh installs because the
-- numbered migration history contains legacy storefront tables that are not
-- part of the current POS app.
--
-- Specifically:
--
--   * The early storefront migrations are excluded on purpose. They build a
--     legacy online-shop schema that is not wired into the current routes.
--     Worse, one of them creates a `products` table BEFORE the real POS
--     `products` table, so a fresh replay silently keeps the wrong shape and
--     later POS inserts fail with "Unknown column".
--   * 034 and 035 (drop the 'branches.manage' capability, drop branch_id /
--     the branches table) are folded in as the final state: BranchModel,
--     BranchController and every branch_id reference have already been
--     removed from app/ — this is a single-shop POS now.
--   * Columns/tables added straight to production after their migration file
--     was written are included in their final form so this file matches what
--     app/models actually query today: product attributes, stationery,
--     product barcodes, offer pricing, archived products, nullable stock
--     suppliers, stock remarks, staff auto-close/reclock authorization, and
--     order credit/invoice fields.
--   * The temporary "Test (2 weeks)" KSh 10 subscription plan (017) is left
--     out, per that migration's own "remove before launch" note.
--
-- Safe to run once on an EMPTY database. Every statement is idempotent
-- (CREATE TABLE IF NOT EXISTS / INSERT ... ON DUPLICATE KEY UPDATE), so
-- re-running it is harmless, but it is NOT meant to be replayed against your
-- current dev/production DB that already has data in a different shape —
-- this is for a fresh host.
--
-- Before running: create the database and point your MySQL client at it,
-- e.g.
--   CREATE DATABASE IF NOT EXISTS your_db_name
--     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   USE your_db_name;
-- then update app/config/database.php with that db name + credentials.
-- =============================================================================

SET NAMES utf8mb4;

-- =============================================================================
-- SECTION 1 — Accounts: roles, users, profiles, login security
-- =============================================================================

CREATE TABLE IF NOT EXISTS roles (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    role_name    VARCHAR(50) UNIQUE NOT NULL,
    scope        ENUM('platform','tenant') NOT NULL DEFAULT 'tenant',
    capabilities JSON NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Upgrade an older `roles` table created before SaaS/POS permissions.
ALTER TABLE roles ADD COLUMN scope ENUM('platform','tenant') NOT NULL DEFAULT 'tenant' AFTER role_name;
ALTER TABLE roles ADD COLUMN capabilities JSON NULL AFTER scope;

-- Two role families share this table: the legacy CMS roles (superadmin/admin/
-- user — gate the marketing-site admin panel by name in app/helpers/middleware.php)
-- and the POS/SaaS roles (platform_admin/tenant_owner/staff — gate everything
-- under public/super and public/staff via app/helpers/Capabilities.php).
INSERT INTO roles (role_name, scope, capabilities) VALUES
 ('superadmin', 'platform', JSON_ARRAY('*')),
 ('admin', 'tenant', JSON_ARRAY('inventory.view','inventory.edit','stock.enter','sales.record','sales.view','customers.manage','catalogue.send','reports.view','staff.manage','settings.manage','billing.manage')),
 ('user', 'tenant', JSON_ARRAY('inventory.view','sales.record','sales.view')),
 ('platform_admin', 'platform', JSON_ARRAY('*')),
 ('tenant_owner', 'tenant', JSON_ARRAY('inventory.view','inventory.edit','stock.enter','sales.record','sales.view','payments.process','customers.manage','catalogue.send','reports.view','staff.manage','settings.manage','billing.manage')),
 ('staff', 'tenant', JSON_ARRAY('inventory.view','sales.record','sales.view'))
ON DUPLICATE KEY UPDATE scope = VALUES(scope), capabilities = VALUES(capabilities);

CREATE TABLE IF NOT EXISTS users (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id           INT NULL,                 -- NULL = platform-level user (super admin)
    username            VARCHAR(50) NOT NULL,
    email               VARCHAR(100) NOT NULL,
    password_hash       VARCHAR(255) NOT NULL,
    pin_hash            VARCHAR(255) NULL,         -- staff shared-terminal PIN login
    position            VARCHAR(100) NULL,
    must_reset_password TINYINT(1) NOT NULL DEFAULT 0,
    role_id             INT NOT NULL,
    is_active           BOOLEAN DEFAULT TRUE,
    email_verified      BOOLEAN DEFAULT FALSE,
    activation_token    VARCHAR(64) NULL,
    activation_expires  DATETIME NULL,
    activated_at        DATETIME NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY username (username),
    UNIQUE KEY email (email),
    UNIQUE KEY uq_users_tenant_email (tenant_id, email),
    KEY idx_email (email),
    KEY idx_username (username),
    KEY idx_users_tenant (tenant_id),
    KEY idx_users_activation (activation_token),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Upgrade an older `users` table created before tenants/staff PIN login.
ALTER TABLE users ADD COLUMN tenant_id INT NULL AFTER id;
ALTER TABLE users ADD COLUMN pin_hash VARCHAR(255) NULL AFTER password_hash;
ALTER TABLE users ADD COLUMN position VARCHAR(100) NULL AFTER pin_hash;
ALTER TABLE users ADD COLUMN must_reset_password TINYINT(1) NOT NULL DEFAULT 0 AFTER position;
ALTER TABLE users ADD COLUMN activation_token VARCHAR(64) NULL AFTER email_verified;
ALTER TABLE users ADD COLUMN activation_expires DATETIME NULL AFTER activation_token;
ALTER TABLE users ADD COLUMN activated_at DATETIME NULL AFTER activation_expires;
ALTER TABLE users ADD UNIQUE KEY uq_users_tenant_email (tenant_id, email);
ALTER TABLE users ADD KEY idx_users_tenant (tenant_id);
ALTER TABLE users ADD KEY idx_users_activation (activation_token);

-- Default platform super admin for the CMS admin panel (email: admin@ismano.com,
-- password: Admin123!). Change this password immediately after first login.
INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified)
VALUES ('superadmin', 'admin@ismano.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, 1)
ON DUPLICATE KEY UPDATE username = username;

CREATE TABLE IF NOT EXISTS user_profiles (
    user_id    INT PRIMARY KEY,
    first_name VARCHAR(100),
    last_name  VARCHAR(100),
    phone      VARCHAR(20),
    address    TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    email        VARCHAR(100),
    ip_address   VARCHAR(45),
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_time (email, attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email OTP codes for mandatory 2FA on every login.
CREATE TABLE IF NOT EXISTS login_otps (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    tenant_id    INT NULL,
    code_hash    VARCHAR(255) NOT NULL,
    purpose      VARCHAR(32)  NOT NULL DEFAULT 'login_2fa',
    attempts     TINYINT      NOT NULL DEFAULT 0,
    max_attempts TINYINT      NOT NULL DEFAULT 5,
    expires_at   DATETIME     NOT NULL,
    consumed_at  DATETIME     NULL,
    ip           VARCHAR(45)  NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_otp_user_purpose (user_id, purpose),
    KEY idx_otp_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Per-user capability overrides on top of the role defaults above.
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

-- =============================================================================
-- SECTION 2 — Multi-tenant SaaS: tenants (shops) & subscriptions
-- =============================================================================

CREATE TABLE IF NOT EXISTS tenants (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(150) NOT NULL,
    slug           VARCHAR(150) NOT NULL,
    owner_user_id  INT NULL,
    status         ENUM('active','suspended','cancelled') NOT NULL DEFAULT 'active',
    logo_path      VARCHAR(255) NULL,
    currency       VARCHAR(8)   NOT NULL DEFAULT 'KES',
    phone          VARCHAR(30)  NULL,
    address        VARCHAR(255) NULL,
    po_box         VARCHAR(120) NULL,
    business_email VARCHAR(190) NULL,
    receipt_footer VARCHAR(255) NULL,
    kra_pin        VARCHAR(20)  NULL,
    payment_credentials TEXT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_slug (slug),
    KEY idx_tenant_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subscription_plans (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    description    VARCHAR(255) NULL,
    price_weekly   DECIMAL(10,2) NULL,
    price_biweekly DECIMAL(10,2) NULL,
    price_monthly  DECIMAL(10,2) NULL,
    max_staff      INT NULL,                 -- NULL = unlimited
    max_products   INT NULL,                 -- NULL = unlimited
    features       JSON NULL,
    is_active      TINYINT(1) NOT NULL DEFAULT 1,
    is_public      TINYINT(1) NOT NULL DEFAULT 1,   -- shown on the marketing/pricing page
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_plan_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Single production plan at the real prices. Add more rows here if you want
-- tiered plans; the registration flow lists every is_public=1 row.
INSERT INTO subscription_plans (name, description, price_weekly, price_biweekly, price_monthly, max_staff, max_products, is_active, is_public)
SELECT * FROM (
    SELECT 'Standard' AS name,
           'Everything you need to run your shop' AS description,
           250.00 AS price_weekly, 500.00 AS price_biweekly, 1000.00 AS price_monthly,
           3 AS max_staff, 200 AS max_products, 1 AS is_active, 1 AS is_public
) AS t
WHERE NOT EXISTS (SELECT 1 FROM subscription_plans WHERE name = 'Standard');

CREATE TABLE IF NOT EXISTS subscriptions (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id            INT NOT NULL,
    plan_id              INT NOT NULL,
    billing_interval     ENUM('weekly','biweekly','monthly') NOT NULL DEFAULT 'monthly',
    amount               DECIMAL(10,2) NOT NULL,
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

-- Tracks each M-Pesa STK push for a subscription payment.
CREATE TABLE IF NOT EXISTS subscription_stk (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id           INT NOT NULL,
    user_id             INT NOT NULL,
    subscription_id     INT NULL,
    plan_id             INT NOT NULL,
    billing_interval    ENUM('weekly','biweekly','monthly') NOT NULL,
    amount              DECIMAL(10,2) NOT NULL,
    phone               VARCHAR(15) NOT NULL,
    checkout_request_id VARCHAR(64) NULL,
    merchant_request_id VARCHAR(64) NULL,
    status              ENUM('pending','success','failed','cancelled') NOT NULL DEFAULT 'pending',
    result_code         INT NULL,
    result_desc         VARCHAR(191) NULL,
    mpesa_receipt       VARCHAR(32) NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_stk_checkout (checkout_request_id),
    KEY idx_stk_tenant (tenant_id),
    KEY idx_stk_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- SECTION 3 — Marketing / CMS site: projects, services, blog, gallery,
-- site settings, enquiries, testimonials
-- =============================================================================

CREATE TABLE IF NOT EXISTS project_categories (
    id                    INT PRIMARY KEY AUTO_INCREMENT,
    category_name         VARCHAR(100) NOT NULL,
    category_slug         VARCHAR(100) NOT NULL UNIQUE,
    category_description  TEXT,
    created_by            INT,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_projcat_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_slug (category_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO project_categories (category_name, category_slug, category_description) VALUES
('Web Development', 'web-development', 'Web development projects including websites and web applications'),
('Mobile Apps', 'mobile-apps', 'Mobile application development projects'),
('UI/UX Design', 'ui-ux-design', 'User interface and experience design projects'),
('E-commerce', 'ecommerce', 'E-commerce platform and online store projects'),
('Custom Software', 'custom-software', 'Custom software development projects')
ON DUPLICATE KEY UPDATE category_name = category_name;

CREATE TABLE IF NOT EXISTS projects (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    category_id    INT NOT NULL,
    small_title    VARCHAR(100) NOT NULL,
    major_title    VARCHAR(200) NOT NULL,
    project_slug   VARCHAR(200) NOT NULL UNIQUE,
    description    TEXT,
    cover_image    VARCHAR(255),
    status         ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    view_count     INT DEFAULT 0,
    created_by     INT,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_proj_cat  FOREIGN KEY (category_id) REFERENCES project_categories(id) ON DELETE CASCADE,
    CONSTRAINT fk_proj_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category_id),
    INDEX idx_status (status),
    INDEX idx_slug (project_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_gallery (
    id                 INT PRIMARY KEY AUTO_INCREMENT,
    project_id         INT NOT NULL,
    image_path         VARCHAR(255) NOT NULL,
    image_title        VARCHAR(100),
    image_description  TEXT,
    sort_order         INT DEFAULT 0,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_projgal_proj FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project (project_id),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_videos (
    id                INT PRIMARY KEY AUTO_INCREMENT,
    project_id        INT NOT NULL,
    video_title       VARCHAR(200),
    video_url         VARCHAR(500) NOT NULL,
    video_embed_code  TEXT,
    video_type        ENUM('youtube', 'vimeo', 'local', 'other') DEFAULT 'youtube',
    sort_order        INT DEFAULT 0,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_projvid_proj FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_tags (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    tag_name   VARCHAR(50) NOT NULL UNIQUE,
    tag_slug   VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_tag_relations (
    project_id INT NOT NULL,
    tag_id     INT NOT NULL,
    PRIMARY KEY (project_id, tag_id),
    CONSTRAINT fk_ptr_proj FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_ptr_tag  FOREIGN KEY (tag_id) REFERENCES project_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS services (
    id                 INT PRIMARY KEY AUTO_INCREMENT,
    title              VARCHAR(200) NOT NULL,
    slug               VARCHAR(200) NOT NULL UNIQUE,
    short_description  TEXT,
    cover_image        VARCHAR(255),
    status             ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    view_count         INT DEFAULT 0,
    created_by         INT,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_service_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_slug (slug),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_sections (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    service_id   INT NOT NULL,
    section_type ENUM('text_only', 'text_image_left', 'text_image_right', 'image_gallery', 'video') DEFAULT 'text_only',
    title        VARCHAR(200),
    content      TEXT,
    media_url    VARCHAR(500),
    media_type   ENUM('image', 'video', 'youtube', 'vimeo') DEFAULT 'image',
    sort_order   INT DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_svcsec_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    INDEX idx_service (service_id),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_gallery (
    id                 INT PRIMARY KEY AUTO_INCREMENT,
    service_id         INT NOT NULL,
    image_path         VARCHAR(255) NOT NULL,
    image_title        VARCHAR(100),
    image_description  TEXT,
    sort_order         INT DEFAULT 0,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_svcgal_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    INDEX idx_service (service_id),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_benefits (
    id                    INT PRIMARY KEY AUTO_INCREMENT,
    service_id            INT NOT NULL,
    benefit_title         VARCHAR(200) NOT NULL,
    benefit_description   TEXT,
    icon_class            VARCHAR(100),
    sort_order            INT DEFAULT 0,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_svcben_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    INDEX idx_service (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_faqs (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    service_id  INT NOT NULL,
    question    VARCHAR(300) NOT NULL,
    answer      TEXT NOT NULL,
    sort_order  INT DEFAULT 0,
    is_active   BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_svcfaq_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    INDEX idx_service (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blog_categories (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    color       VARCHAR(20) DEFAULT '#667eea',
    created_by  INT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_blogcat_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blogs (
    id                INT PRIMARY KEY AUTO_INCREMENT,
    title             VARCHAR(255) NOT NULL,
    slug              VARCHAR(255) NOT NULL UNIQUE,
    excerpt           TEXT,
    content           LONGTEXT,
    featured_image    VARCHAR(255),
    category_id       INT NULL,
    author_id         INT NOT NULL,
    status            ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    view_count        INT DEFAULT 0,
    is_featured       BOOLEAN DEFAULT FALSE,
    meta_title        VARCHAR(255),
    meta_description  TEXT,
    meta_keywords     VARCHAR(255),
    published_at      TIMESTAMP NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_blog_cat  FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_blog_user FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_slug (slug),
    INDEX idx_author (author_id),
    INDEX idx_category (category_id),
    INDEX idx_published (published_at),
    INDEX idx_featured (is_featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blog_sections (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    blog_id      INT NOT NULL,
    section_type ENUM('text_only', 'text_image_left', 'text_image_right', 'image_gallery', 'video', 'youtube', 'code_block', 'quote') DEFAULT 'text_only',
    title        VARCHAR(255),
    content      TEXT,
    media_url    VARCHAR(500),
    media_type   ENUM('image', 'video', 'youtube') DEFAULT 'image',
    video_id     VARCHAR(100),
    sort_order   INT DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_blogsec_blog FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    INDEX idx_blog (blog_id),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blog_faqs (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    blog_id    INT NOT NULL,
    question   VARCHAR(300) NOT NULL,
    answer     TEXT NOT NULL,
    sort_order INT DEFAULT 0,
    is_active  BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_blogfaq_blog FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    INDEX idx_blog (blog_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blog_tags (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    name       VARCHAR(50) NOT NULL UNIQUE,
    slug       VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blog_tag_relations (
    blog_id INT NOT NULL,
    tag_id  INT NOT NULL,
    PRIMARY KEY (blog_id, tag_id),
    CONSTRAINT fk_btr_blog FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    CONSTRAINT fk_btr_tag  FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS site_settings (
    setting_key   VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_settings (setting_key, setting_value) VALUES
    ('site_name', 'Ismano'),
    ('logo_path', NULL),
    ('logo_alt',  'Ismano')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

CREATE TABLE IF NOT EXISTS hero_slides (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    image_path  VARCHAR(255) NOT NULL,
    caption     VARCHAR(255) NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hero_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS gallery (
    id                INT PRIMARY KEY AUTO_INCREMENT,
    title             VARCHAR(255) NOT NULL,
    description       TEXT,
    media_type        ENUM('image', 'video') DEFAULT 'image',
    file_path         VARCHAR(500) NOT NULL,
    thumbnail_path    VARCHAR(500),
    video_url         VARCHAR(500),
    video_embed_code  TEXT,
    category          VARCHAR(100),
    tags              VARCHAR(255),
    sort_order        INT DEFAULT 0,
    is_featured       BOOLEAN DEFAULT FALSE,
    status            ENUM('active', 'inactive') DEFAULT 'active',
    view_count        INT DEFAULT 0,
    created_by        INT,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_gallery_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_media_type (media_type),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gallery_categories (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS enquiries (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(100) NOT NULL,
    phone         VARCHAR(20) NOT NULL,
    service       VARCHAR(100),
    message       TEXT,
    status        ENUM('new', 'read', 'contacted', 'closed') DEFAULT 'new',
    priority      ENUM('low', 'medium', 'high') DEFAULT 'medium',
    notes         TEXT,
    contacted_at  TIMESTAMP NULL,
    closed_at     TIMESTAMP NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_email (email),
    INDEX idx_created (created_at),
    FULLTEXT INDEX idx_search (name, email, message)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS enquiry_replies (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    enquiry_id  INT NOT NULL,
    admin_id    INT NOT NULL,
    reply       TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_enqreply_enq   FOREIGN KEY (enquiry_id) REFERENCES enquiries(id) ON DELETE CASCADE,
    CONSTRAINT fk_enqreply_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_enquiry (enquiry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS testimonials (
    id                 INT PRIMARY KEY AUTO_INCREMENT,
    customer_name      VARCHAR(100) NOT NULL,
    customer_email     VARCHAR(100),
    customer_phone     VARCHAR(20),
    customer_initial   VARCHAR(5),
    rating             INT DEFAULT 5,
    testimonial_text   TEXT NOT NULL,
    service_tag        VARCHAR(100),
    role               VARCHAR(100),
    status             ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    is_featured        BOOLEAN DEFAULT FALSE,
    sort_order         INT DEFAULT 0,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    approved_at        TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_rating (rating),
    INDEX idx_featured (is_featured),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- testimonials has no natural unique key (it's free-text customer quotes), so
-- each seed row is guarded individually by customer_name to stay idempotent
-- on a re-run instead of relying on ON DUPLICATE KEY UPDATE (which does
-- nothing useful against an autoincrement PK with no other unique key).
INSERT INTO testimonials (customer_name, customer_initial, rating, testimonial_text, service_tag, role, status, is_featured)
SELECT 'James Mwangi', 'J', 5, 'ISMAN designed and installed our 450 sqm hotel kitchen in under 8 weeks. The SS304 fabrication quality exceeded international standards, and their team worked around our operational hours without a single disruption to guests.', 'Commercial Kitchen', 'General Manager, Radisson Blu Nairobi', 'approved', 1
WHERE NOT EXISTS (SELECT 1 FROM testimonials WHERE customer_name = 'James Mwangi');

INSERT INTO testimonials (customer_name, customer_initial, rating, testimonial_text, service_tag, role, status, is_featured)
SELECT 'Aisha Noor', 'A', 5, 'The stainless balustrade work at Two Rivers was flawless. Precision welds, perfect alignment across three floors, and delivered ahead of schedule. We have used them on every project since.', 'Stainless Railing', 'Project Lead, Centum Investment', 'approved', 1
WHERE NOT EXISTS (SELECT 1 FROM testimonials WHERE customer_name = 'Aisha Noor');

INSERT INTO testimonials (customer_name, customer_initial, rating, testimonial_text, service_tag, role, status, is_featured)
SELECT 'Dr. Peter Otieno', 'P', 5, 'Their hospital fit-out met every infection-control requirement we set. Documentation was thorough and the finish on the SS316 surfaces is exactly what a sterile environment needs.', 'Hospital Fit-out', 'Facilities Director, Kenyatta National Hospital', 'approved', 1
WHERE NOT EXISTS (SELECT 1 FROM testimonials WHERE customer_name = 'Dr. Peter Otieno');

INSERT INTO testimonials (customer_name, customer_initial, rating, testimonial_text, service_tag, role, status, is_featured)
SELECT 'Grace Wambui', 'G', 5, 'We commissioned a full processing line and ISMAN handled design, fabrication and install end to end. HACCP-ready, on budget, and running at full throughput from day one.', 'Food Processing', 'Operations Manager, Brookside Dairy', 'approved', 1
WHERE NOT EXISTS (SELECT 1 FROM testimonials WHERE customer_name = 'Grace Wambui');

-- =============================================================================
-- SECTION 4 — POS: suppliers, catalogue (categories/subcategories/products),
-- stock intake
-- =============================================================================

CREATE TABLE IF NOT EXISTS suppliers (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id  INT NOT NULL,
    name       VARCHAR(160) NOT NULL,
    phone      VARCHAR(30) NULL,
    notes      VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_supplier_tenant_name (tenant_id, name),
    KEY idx_supplier_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id  INT NOT NULL,
    name       VARCHAR(120) NOT NULL,
    type       ENUM('subject','stationery') NOT NULL DEFAULT 'subject',
    image_path VARCHAR(255) NULL,
    status     ENUM('active','draft') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cat_tenant_type_name (tenant_id, type, name),
    KEY idx_cat_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Upgrade categories created before stationery categories existed.
ALTER TABLE categories ADD COLUMN type ENUM('subject','stationery') NOT NULL DEFAULT 'subject' AFTER name;
ALTER TABLE categories ADD COLUMN image_path VARCHAR(255) NULL AFTER type;
ALTER TABLE categories DROP INDEX uq_cat_tenant_name;
ALTER TABLE categories ADD UNIQUE KEY uq_cat_tenant_type_name (tenant_id, type, name);

CREATE TABLE IF NOT EXISTS subcategories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT NOT NULL,
    category_id INT NOT NULL,
    name        VARCHAR(120) NOT NULL,
    status      ENUM('active','draft') NOT NULL DEFAULT 'active',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subcat_tenant_cat_name (tenant_id, category_id, name),
    KEY idx_subcat_tenant (tenant_id),
    KEY idx_subcat_cat (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Small tenant-scoped lookup values shared across books/stationery:
-- Grade/Class, Publisher, Author, Edition, Brand.
CREATE TABLE IF NOT EXISTS book_attributes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id  INT NOT NULL,
    type       ENUM('grade','publisher','author','edition','brand') NOT NULL,
    name       VARCHAR(160) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attr_tenant_type_name (tenant_id, type, name),
    KEY idx_attr_tenant_type (tenant_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
    id                     INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id              INT NOT NULL,
    product_type           ENUM('book','stationery') NOT NULL DEFAULT 'book',
    category_id            INT NULL,
    subcategory_id         INT NULL,
    grade_id               INT NULL,
    publisher_id           INT NULL,
    author_id              INT NULL,
    edition_id             INT NULL,
    brand_id               INT NULL,
    barcode                VARCHAR(64) NULL,
    supplier_id            INT NULL,
    name                   VARCHAR(160) NOT NULL,
    description            TEXT NULL,
    quantity               DECIMAL(12,2) NOT NULL DEFAULT 0,
    unit                   VARCHAR(20) NOT NULL DEFAULT 'piece',   -- piece,g,kg,tonne,ml,litre
    size_value             DECIMAL(10,2) NULL,
    size_unit              ENUM('ml','l') NULL,
    buying_price           DECIMAL(12,2) NOT NULL DEFAULT 0,
    selling_price          DECIMAL(12,2) NOT NULL DEFAULT 0,
    wholesale_price        DECIMAL(12,2) NOT NULL DEFAULT 0,
    retail_price           DECIMAL(12,2) NOT NULL DEFAULT 0,
    offer_price            DECIMAL(12,2) NULL,
    offer_starts_at        DATETIME NULL,
    offer_ends_at          DATETIME NULL,
    colors                 JSON NULL,                              -- ["Blue","Red"]
    sizes                  JSON NULL,                              -- ["S","M","L"] or ["500ml","1L"]
    image_path             VARCHAR(255) NULL,
    low_stock_threshold    INT NOT NULL DEFAULT 10,
    credit_limit           DECIMAL(12,2) NULL,
    low_stock_notified_at  DATETIME NULL,
    status                 ENUM('active','draft','archived') NOT NULL DEFAULT 'active',
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_prod_tenant (tenant_id),
    KEY idx_prod_cat (category_id),
    KEY idx_prod_subcat (subcategory_id),
    KEY idx_prod_supplier (supplier_id),
    KEY idx_prod_status (status),
    KEY idx_prod_lowstock (tenant_id, quantity),
    KEY idx_prod_grade (grade_id),
    KEY idx_prod_publisher (publisher_id),
    KEY idx_prod_author (author_id),
    KEY idx_prod_edition (edition_id),
    KEY idx_prod_brand (brand_id),
    UNIQUE KEY uq_prod_tenant_barcode (tenant_id, barcode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Upgrade a pre-POS or early-POS `products` table to the current model shape.
ALTER TABLE products DROP FOREIGN KEY products_ibfk_1;
ALTER TABLE products DROP FOREIGN KEY products_ibfk_2;
ALTER TABLE products MODIFY slug VARCHAR(255) NULL;
ALTER TABLE products MODIFY price DECIMAL(10,2) NULL;
ALTER TABLE products ADD COLUMN tenant_id INT NOT NULL DEFAULT 0 AFTER id;
ALTER TABLE products ADD COLUMN product_type ENUM('book','stationery') NOT NULL DEFAULT 'book' AFTER tenant_id;
ALTER TABLE products ADD COLUMN subcategory_id INT NULL AFTER category_id;
ALTER TABLE products ADD COLUMN grade_id INT NULL AFTER subcategory_id;
ALTER TABLE products ADD COLUMN publisher_id INT NULL AFTER grade_id;
ALTER TABLE products ADD COLUMN author_id INT NULL AFTER publisher_id;
ALTER TABLE products ADD COLUMN edition_id INT NULL AFTER author_id;
ALTER TABLE products ADD COLUMN brand_id INT NULL AFTER edition_id;
ALTER TABLE products ADD COLUMN barcode VARCHAR(64) NULL AFTER brand_id;
ALTER TABLE products ADD COLUMN supplier_id INT NULL AFTER barcode;
ALTER TABLE products ADD COLUMN quantity DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER description;
ALTER TABLE products ADD COLUMN unit VARCHAR(20) NOT NULL DEFAULT 'piece' AFTER quantity;
ALTER TABLE products ADD COLUMN size_value DECIMAL(10,2) NULL AFTER unit;
ALTER TABLE products ADD COLUMN size_unit ENUM('ml','l') NULL AFTER size_value;
ALTER TABLE products ADD COLUMN buying_price DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER size_unit;
ALTER TABLE products ADD COLUMN selling_price DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER buying_price;
ALTER TABLE products ADD COLUMN wholesale_price DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER selling_price;
ALTER TABLE products ADD COLUMN retail_price DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER wholesale_price;
ALTER TABLE products ADD COLUMN offer_price DECIMAL(12,2) NULL AFTER retail_price;
ALTER TABLE products ADD COLUMN offer_starts_at DATETIME NULL AFTER offer_price;
ALTER TABLE products ADD COLUMN offer_ends_at DATETIME NULL AFTER offer_starts_at;
ALTER TABLE products ADD COLUMN colors JSON NULL AFTER offer_ends_at;
ALTER TABLE products ADD COLUMN sizes JSON NULL AFTER colors;
ALTER TABLE products ADD COLUMN image_path VARCHAR(255) NULL AFTER sizes;
ALTER TABLE products ADD COLUMN low_stock_threshold INT NOT NULL DEFAULT 10 AFTER image_path;
ALTER TABLE products ADD COLUMN credit_limit DECIMAL(12,2) NULL AFTER low_stock_threshold;
ALTER TABLE products ADD COLUMN low_stock_notified_at DATETIME NULL AFTER low_stock_threshold;
UPDATE products SET status = 'draft' WHERE status = 'inactive';
ALTER TABLE products MODIFY status ENUM('active','draft','archived') NOT NULL DEFAULT 'active';
ALTER TABLE products ADD KEY idx_prod_tenant (tenant_id);
ALTER TABLE products ADD KEY idx_prod_subcat (subcategory_id);
ALTER TABLE products ADD KEY idx_prod_supplier (supplier_id);
ALTER TABLE products ADD KEY idx_prod_lowstock (tenant_id, quantity);
ALTER TABLE products ADD KEY idx_prod_grade (grade_id);
ALTER TABLE products ADD KEY idx_prod_publisher (publisher_id);
ALTER TABLE products ADD KEY idx_prod_author (author_id);
ALTER TABLE products ADD KEY idx_prod_edition (edition_id);
ALTER TABLE products ADD KEY idx_prod_brand (brand_id);
ALTER TABLE products ADD UNIQUE KEY uq_prod_tenant_barcode (tenant_id, barcode);

-- One row per delivery: who brought what, entered by whom.
CREATE TABLE IF NOT EXISTS stock_intakes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT NOT NULL,
    supplier_id INT NULL,
    staff_id    INT NOT NULL,
    notes       VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_intake_tenant (tenant_id),
    KEY idx_intake_supplier (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE stock_intakes MODIFY supplier_id INT NULL;
ALTER TABLE stock_intakes ADD COLUMN notes VARCHAR(255) NULL AFTER staff_id;

-- Line items of a delivery — kept for history even if the product is later
-- edited/deleted.
CREATE TABLE IF NOT EXISTS stock_intake_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT NOT NULL,
    stock_intake_id INT NOT NULL,
    product_id      INT NULL,
    product_name    VARCHAR(160) NOT NULL,
    quantity        DECIMAL(12,2) NOT NULL,
    buying_price    DECIMAL(12,2) NOT NULL,
    remark          VARCHAR(255) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_intakeitem_intake (stock_intake_id),
    KEY idx_intakeitem_tenant (tenant_id),
    KEY idx_intakeitem_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE stock_intake_items ADD COLUMN remark VARCHAR(255) NULL AFTER buying_price;

-- =============================================================================
-- SECTION 5 — POS: direct sales (with credit/split payments), bar-tab style
-- orders, held carts, staff time clock, audit trail
-- =============================================================================

CREATE TABLE IF NOT EXISTS sales (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id        INT NOT NULL,
    staff_id         INT NOT NULL,                      -- user who recorded the sale
    sale_type        ENUM('retail','wholesale') NOT NULL DEFAULT 'retail',
    receipt_number   VARCHAR(32) NOT NULL,
    payment_method   ENUM('cash','mpesa','split','credit') NOT NULL DEFAULT 'cash',
    mpesa_channel    VARCHAR(10) NULL,
    total            DECIMAL(12,2) NOT NULL DEFAULT 0,
    subtotal         DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_amount  DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount_paid      DECIMAL(12,2) NOT NULL DEFAULT 0,   -- running total actually collected
    amount_due       DECIMAL(12,2) NOT NULL DEFAULT 0,   -- outstanding balance (credit sales)
    amount_given     DECIMAL(12,2) NULL,                 -- cash tendered
    change_given     DECIMAL(12,2) NULL,
    cash_amount      DECIMAL(12,2) NULL,                 -- cash leg of a split payment
    mpesa_amount     DECIMAL(12,2) NULL,                 -- mpesa leg of a split payment
    customer_name    VARCHAR(120) NULL,
    customer_phone   VARCHAR(30) NULL,
    customer_email   VARCHAR(255) NULL,
    status           ENUM('completed','voided') NOT NULL DEFAULT 'completed',
    payment_status   ENUM('pending','paid','part_paid','credit','failed') NOT NULL DEFAULT 'paid',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sale_receipt (tenant_id, receipt_number),
    KEY idx_sale_tenant (tenant_id),
    KEY idx_sale_staff (staff_id),
    KEY idx_sale_created (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE sales ADD COLUMN sale_type ENUM('retail','wholesale') NOT NULL DEFAULT 'retail' AFTER staff_id;
ALTER TABLE sales ADD COLUMN mpesa_channel VARCHAR(10) NULL AFTER payment_method;
ALTER TABLE sales ADD COLUMN subtotal DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total;
ALTER TABLE sales ADD COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER subtotal;
ALTER TABLE sales ADD COLUMN amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER discount_amount;
ALTER TABLE sales ADD COLUMN amount_due DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER amount_paid;
ALTER TABLE sales ADD COLUMN cash_amount DECIMAL(12,2) NULL AFTER change_given;
ALTER TABLE sales ADD COLUMN mpesa_amount DECIMAL(12,2) NULL AFTER cash_amount;
ALTER TABLE sales ADD COLUMN customer_phone VARCHAR(30) NULL AFTER customer_name;
ALTER TABLE sales ADD COLUMN customer_email VARCHAR(255) NULL AFTER customer_phone;
ALTER TABLE sales ADD COLUMN payment_status ENUM('pending','paid','part_paid','credit','failed') NOT NULL DEFAULT 'paid' AFTER status;
ALTER TABLE sales ADD KEY idx_sale_created (tenant_id, created_at);

CREATE TABLE IF NOT EXISTS sale_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id    INT NOT NULL,
    sale_id      INT NOT NULL,
    product_id   INT NULL,                             -- may be null if product later deleted
    product_name VARCHAR(160) NOT NULL,                -- snapshot at sale time
    unit         VARCHAR(20) NOT NULL DEFAULT 'piece',
    unit_price   DECIMAL(12,2) NOT NULL,                -- snapshot of the price charged
    price_type   ENUM('retail','wholesale') NOT NULL DEFAULT 'retail',
    unit_cost    DECIMAL(12,2) NOT NULL DEFAULT 0,       -- snapshot of buying_price, for margin reports
    quantity     DECIMAL(12,2) NOT NULL,
    line_total   DECIMAL(12,2) NOT NULL,
    KEY idx_item_sale (sale_id),
    KEY idx_item_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE sale_items ADD COLUMN price_type ENUM('retail','wholesale') NOT NULL DEFAULT 'retail' AFTER unit_price;
ALTER TABLE sale_items ADD COLUMN unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER price_type;

-- Installment payments against a credit/part-paid sale.
CREATE TABLE IF NOT EXISTS sale_payments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id  INT NOT NULL,
    sale_id    INT NOT NULL,
    staff_id   INT NOT NULL,
    amount     DECIMAL(12,2) NOT NULL,
    method     VARCHAR(20) NOT NULL DEFAULT 'cash',
    note       VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sale (sale_id),
    KEY idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bar/club tabs: a server opens a tab for a table/customer, adds items over
-- one or more rounds, and someone with payments.process settles it later.
-- `channel` distinguishes a walk-in sale (paid immediately) from a tab.
CREATE TABLE IF NOT EXISTS orders (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id        INT NOT NULL,
    table_name       VARCHAR(120) NOT NULL,
    customer_phone   VARCHAR(30) NULL,
    customer_email   VARCHAR(255) NULL,
    channel          ENUM('walkin','tab') NOT NULL DEFAULT 'tab',
    opened_by        INT NOT NULL,
    receipt_number   VARCHAR(32) NOT NULL,
    status           ENUM('open','paid','void') NOT NULL DEFAULT 'open',
    subtotal         DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_amount  DECIMAL(12,2) NOT NULL DEFAULT 0,
    total            DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount_paid      DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount_due       DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_method   ENUM('cash','mpesa','split','card','bank','sacco','credit') NULL,
    payment_status   VARCHAR(20) NOT NULL DEFAULT 'credit',
    payment_provider VARCHAR(100) NULL,
    payment_account_name VARCHAR(160) NULL,
    payment_reference VARCHAR(120) NULL,
    cash_amount      DECIMAL(12,2) NULL,
    mpesa_amount     DECIMAL(12,2) NULL,
    amount_tendered  DECIMAL(12,2) NULL,
    change_due       DECIMAL(12,2) NULL,
    paid_by          INT NULL,
    paid_at          DATETIME NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    invoice_sent_at  DATETIME NULL,
    delivery_note_sent_at DATETIME NULL,
    UNIQUE KEY uq_order_receipt (tenant_id, receipt_number),
    KEY idx_order_tenant (tenant_id),
    KEY idx_order_status (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE orders ADD COLUMN customer_phone VARCHAR(30) NULL AFTER table_name;
ALTER TABLE orders ADD COLUMN customer_email VARCHAR(255) NULL AFTER customer_phone;
ALTER TABLE orders ADD COLUMN channel ENUM('walkin','tab') NOT NULL DEFAULT 'tab' AFTER customer_email;
ALTER TABLE orders ADD COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER subtotal;
ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cash','mpesa','split','card','bank','sacco','credit') DEFAULT NULL;
ALTER TABLE orders ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'credit' AFTER payment_method;
ALTER TABLE orders ADD COLUMN amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER total;
ALTER TABLE orders ADD COLUMN amount_due DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER amount_paid;
ALTER TABLE orders ADD COLUMN payment_provider VARCHAR(100) NULL AFTER payment_method;
ALTER TABLE orders ADD COLUMN payment_account_name VARCHAR(160) NULL AFTER payment_provider;
ALTER TABLE orders ADD COLUMN payment_reference VARCHAR(120) NULL AFTER payment_account_name;
ALTER TABLE orders ADD COLUMN invoice_sent_at DATETIME NULL AFTER updated_at;
ALTER TABLE orders ADD COLUMN delivery_note_sent_at DATETIME NULL AFTER invoice_sent_at;

UPDATE orders
   SET amount_paid = CASE WHEN status = 'paid' THEN total ELSE COALESCE(amount_paid, 0) END,
       amount_due = CASE WHEN status = 'paid' THEN 0 ELSE GREATEST(total - COALESCE(amount_paid, 0), 0) END,
       payment_status = CASE WHEN status = 'paid' THEN 'paid' ELSE 'credit' END
 WHERE amount_due = 0;

CREATE TABLE IF NOT EXISTS order_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    order_id INT NOT NULL,
    staff_id INT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    method VARCHAR(20) NOT NULL,
    cash_amount DECIMAL(12,2) NULL,
    mpesa_amount DECIMAL(12,2) NULL,
    amount_tendered DECIMAL(12,2) NULL,
    change_due DECIMAL(12,2) NULL,
    provider VARCHAR(100) NULL,
    account_name VARCHAR(160) NULL,
    reference VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_order_payment_order (tenant_id, order_id),
    KEY idx_order_payment_staff (tenant_id, staff_id),
    CONSTRAINT fk_order_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id    INT NOT NULL,
    order_id     INT NOT NULL,
    product_id   INT NULL,
    product_name VARCHAR(160) NOT NULL,
    unit_price   DECIMAL(12,2) NOT NULL,
    quantity     DECIMAL(12,2) NOT NULL,
    line_total   DECIMAL(12,2) NOT NULL,
    added_by     INT NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_orderitem_order (order_id),
    KEY idx_orderitem_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    source_type ENUM('sale','order') NOT NULL,
    source_id INT NOT NULL,
    source_item_id INT NOT NULL,
    product_id INT NULL,
    product_name VARCHAR(160) NOT NULL,
    receipt_number VARCHAR(32) NOT NULL,
    returned_quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    used_quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    restocked_quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    reason VARCHAR(120) NULL,
    note VARCHAR(255) NULL,
    processed_by INT NULL,
    migrated_at DATETIME NULL,
    migrated_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_returns_source (tenant_id, source_type, source_id),
    KEY idx_returns_item (tenant_id, source_type, source_item_id),
    KEY idx_returns_product (tenant_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE product_returns ADD COLUMN migrated_at DATETIME NULL AFTER processed_by;
ALTER TABLE product_returns ADD COLUMN migrated_by INT NULL AFTER migrated_at;

-- "Hold Order": a cart set aside before it becomes a real sale/tab. Holding
-- does NOT touch stock — nothing is committed until it's resumed.
CREATE TABLE IF NOT EXISTS held_orders (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id     INT NOT NULL,
    customer_name VARCHAR(120) NOT NULL,
    staff_id      INT NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_held_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS held_order_items (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id      INT NOT NULL,
    held_order_id  INT NOT NULL,
    product_id     INT NULL,
    product_name   VARCHAR(160) NOT NULL,
    unit_price     DECIMAL(12,2) NOT NULL,
    quantity       DECIMAL(12,2) NOT NULL,
    KEY idx_helditem_held (held_order_id),
    KEY idx_helditem_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clock in / clock out records for staff. One open (clock_out_at IS NULL)
-- row per staff member at a time.
CREATE TABLE IF NOT EXISTS staff_time_logs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id    INT NOT NULL,
    user_id      INT NOT NULL,
    clock_in_at  DATETIME NOT NULL,
    clock_out_at DATETIME NULL,
    auto_closed  TINYINT(1) NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_timelog_tenant (tenant_id),
    KEY idx_timelog_user_time (user_id, clock_in_at),
    KEY idx_timelog_open (user_id, clock_out_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE staff_time_logs ADD COLUMN auto_closed TINYINT(1) NOT NULL DEFAULT 0 AFTER clock_out_at;

CREATE TABLE IF NOT EXISTS staff_reclock_authorizations (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id     INT NOT NULL,
    user_id       INT NOT NULL,
    authorized_by INT NOT NULL,
    authorized_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used_at       DATETIME NULL,
    KEY idx_reclock_user (tenant_id, user_id, used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Application-level activity trail (product/staff/settings edits, etc).
CREATE TABLE IF NOT EXISTS audit_log (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id    INT NOT NULL,
    user_id      INT NULL,
    username     VARCHAR(150) NULL,
    role         VARCHAR(60) NULL,
    entity_type  VARCHAR(60) NOT NULL,
    entity_id    INT NULL,
    entity_label VARCHAR(200) NULL,
    action       VARCHAR(30) NOT NULL,
    changes      TEXT NULL,
    created_at   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_tenant_time (tenant_id, created_at),
    KEY idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- Done. Next steps:
--   1. Log in as admin@ismano.com (password Admin123!) and change the password.
--   2. Register your first shop/tenant through the app's registration flow —
--      this creates the tenant + owner user + subscription rows for you.
--   3. If you actually need the old online storefront revived, wire its
--      routes/models intentionally instead of replaying the historical
--      migration files.
-- =============================================================================
