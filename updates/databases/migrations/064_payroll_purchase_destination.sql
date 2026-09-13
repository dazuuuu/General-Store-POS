-- Employee payroll (employees do not require login accounts) and purchase transfer routing.

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='purchases' AND COLUMN_NAME='transfer_destination'),
  'SELECT 1',
  "ALTER TABLE purchases ADD COLUMN transfer_destination ENUM('store','shop') NOT NULL DEFAULT 'shop' AFTER staff_id"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS employees(
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  user_id INT NULL,
  name VARCHAR(160) NOT NULL,
  job_title VARCHAR(100) NOT NULL DEFAULT 'Employee',
  phone VARCHAR(40) NULL,
  salary_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  pay_day TINYINT NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_employee_tenant(tenant_id,is_active),
  KEY idx_employee_user(tenant_id,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payroll_payments(
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  employee_id INT NOT NULL,
  pay_period VARCHAR(80) NOT NULL,
  salary_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid_on DATE NOT NULL,
  payment_method VARCHAR(30) NOT NULL DEFAULT 'cash',
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_payroll_tenant(tenant_id,paid_on),
  KEY idx_payroll_employee(tenant_id,employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
