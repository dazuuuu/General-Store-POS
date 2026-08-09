-- 028_staff_time_logs.sql
-- Clock in / clock out records for staff. One open (clock_out_at IS NULL)
-- row per staff member at a time.

CREATE TABLE IF NOT EXISTS staff_time_logs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id    INT NOT NULL,
    user_id      INT NOT NULL,
    clock_in_at  DATETIME NOT NULL,
    clock_out_at DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_timelog_tenant (tenant_id),
    KEY idx_timelog_user_time (user_id, clock_in_at),
    KEY idx_timelog_open (user_id, clock_out_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
