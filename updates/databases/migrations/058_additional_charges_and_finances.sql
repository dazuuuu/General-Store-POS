-- Additional charges on sales/invoices + standalone revenue/expense ledger for Finances.

ALTER TABLE orders
  ADD COLUMN additional_charges DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER discount_amount,
  ADD COLUMN additional_charges_note VARCHAR(255) NULL AFTER additional_charges;

CREATE TABLE IF NOT EXISTS finance_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  entry_type ENUM('revenue','expense') NOT NULL,
  category VARCHAR(120) NOT NULL DEFAULT '',
  description VARCHAR(255) NOT NULL DEFAULT '',
  amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  payment_method VARCHAR(40) NULL,
  reference VARCHAR(120) NULL,
  entry_date DATE NOT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_finance_tenant_date (tenant_id, entry_date),
  KEY idx_finance_tenant_type (tenant_id, entry_type),
  CONSTRAINT fk_finance_entries_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
