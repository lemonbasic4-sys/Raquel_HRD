-- Performance Indexes Migration
-- Run this once on the live raquel_hris database to speed up:
--   admin/members.php, admin/employee-accounts.php, admin/users.php
-- Compatible with MySQL 5.x / MariaDB

USE raquel_hris;

-- users.employee_id: used in every LEFT JOIN on listing pages
SET @index_exists := (
    SELECT COUNT(1)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'idx_user_employee_id'
);
SET @sql := IF(
    @index_exists = 0,
    'ALTER TABLE users ADD INDEX idx_user_employee_id (employee_id)',
    'SELECT ''idx_user_employee_id already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- employees.job_title: used for position filter in Portal Accounts
SET @index_exists := (
    SELECT COUNT(1)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'employees'
      AND INDEX_NAME = 'idx_employee_job_title'
);
SET @sql := IF(
    @index_exists = 0,
    'ALTER TABLE employees ADD INDEX idx_employee_job_title (job_title)',
    'SELECT ''idx_employee_job_title already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- departments.department_name: used for HR department lookup in User Management
SET @index_exists := (
    SELECT COUNT(1)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'departments'
      AND INDEX_NAME = 'idx_department_name'
);
SET @sql := IF(
    @index_exists = 0,
    'ALTER TABLE departments ADD INDEX idx_department_name (department_name)',
    'SELECT ''idx_department_name already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
