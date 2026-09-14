-- Developer support controls for tenant pages, synchronization and audit.
SET @sql := IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenants' AND COLUMN_NAME='enabled_pages'),
  'SELECT 1',
  'ALTER TABLE tenants ADD COLUMN enabled_pages TEXT NULL AFTER enabled_modules'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenants' AND COLUMN_NAME='settings_revision'),
  'SELECT 1',
  'ALTER TABLE tenants ADD COLUMN settings_revision INT UNSIGNED NOT NULL DEFAULT 1 AFTER offline_enabled'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS platform_audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  platform_user_id INT NULL,
  tenant_id INT NULL,
  action VARCHAR(80) NOT NULL,
  details TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_platform_audit_created (created_at),
  KEY idx_platform_audit_tenant (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
