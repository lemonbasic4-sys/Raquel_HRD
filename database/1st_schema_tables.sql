-- ============================================
-- Raquel HRIS Database Schema
-- Includes:
-- setup database, branches, rank categories, departments, job titles,
-- employees, users, employee PDS submissions, employee details,
-- government IDs, addresses, contacts, emergency contacts,
-- disclosures, family, children, siblings, education, work experience,
-- evaluation templates, evaluation criteria, evaluations,
-- evaluation scores, evaluation development plans, career movements,
-- notifications, audit logs, system settings, additional PDS records,
-- login attempts, and performance indexes.
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;
DROP DATABASE IF EXISTS raquel_hris;
CREATE DATABASE IF NOT EXISTS raquel_hris;
USE raquel_hris;

-- ============================================
-- 1. Setup Database
-- ============================================

-- ============================================
-- 2. Branches
-- ============================================
DROP TABLE IF EXISTS branches;
CREATE TABLE branches (
    branch_id INT AUTO_INCREMENT PRIMARY KEY,
    branch_name VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_branch_status (is_active, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2. Rank Categories
-- ============================================
DROP TABLE IF EXISTS rank_categories;
CREATE TABLE rank_categories (
    rank_category_id INT AUTO_INCREMENT PRIMARY KEY,
    rank_name VARCHAR(50) NOT NULL,
    level_order INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rank_order (level_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 3. Departments
-- ============================================
DROP TABLE IF EXISTS departments;
CREATE TABLE departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_department_status (is_active, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 4. Job Titles
-- ============================================
DROP TABLE IF EXISTS job_titles;
CREATE TABLE job_titles (
    job_title_id INT AUTO_INCREMENT PRIMARY KEY,
    job_title VARCHAR(200) NOT NULL,
    rank_category_id INT,
    department_id INT,
    is_active TINYINT(1) DEFAULT 1,
    is_head TINYINT(1) DEFAULT 0,
    reports_to INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rank_category_id) REFERENCES rank_categories(rank_category_id),
    FOREIGN KEY (department_id) REFERENCES departments(department_id),
    FOREIGN KEY (reports_to) REFERENCES job_titles(job_title_id) ON DELETE SET NULL,
    INDEX idx_job_title (job_title),
    INDEX idx_rank_category (rank_category_id),
    INDEX idx_department (department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 5. Employees (Core Identity & Employment)
-- ============================================
DROP TABLE IF EXISTS employees;
CREATE TABLE employees (
    employee_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_code VARCHAR(30) NULL UNIQUE,
    
    -- Identity (Core)
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    name_extension VARCHAR(10) NULL,
    date_of_birth DATE NULL,
    place_of_birth VARCHAR(255) NULL,
    gender ENUM('Male', 'Female', 'Other', 'Preferred not to say') NULL,
    civil_status ENUM('Single', 'Married', 'Widowed', 'Separated', 'Other') NULL,
    
    -- Employment Metadata
    hire_date DATE NOT NULL,
    job_title VARCHAR(150) NULL,
    job_title_id INT NULL,
    department_id INT NULL,
    rank_category_id INT NULL,
    branch_id INT NULL,
    reports_to INT NULL,
    employment_status ENUM('OJT', 'Probationary', 'Project Based', 'Regular', 'Separated', 'Trainee', 'AWOL', 'Retirement', 'Death', 'Permanent of Total Disability', 'Resignation', 'Failed in Training', 'Termination for Cause') DEFAULT 'Regular',
    employment_type ENUM('Full-time', 'Part-time') DEFAULT 'Full-time',
    contract_start_date DATE NULL,
    contract_end_date DATE NULL,
    
    profile_picture VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    
    CONSTRAINT fk_employees_department FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
    CONSTRAINT fk_employees_branch FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE SET NULL,
    CONSTRAINT fk_employees_job_title FOREIGN KEY (job_title_id) REFERENCES job_titles(job_title_id) ON DELETE SET NULL,
    CONSTRAINT fk_employees_rank_category FOREIGN KEY (rank_category_id) REFERENCES rank_categories(rank_category_id) ON DELETE SET NULL,
    INDEX idx_employee_names (last_name, first_name),
    INDEX idx_employee_dept (department_id, branch_id),
    INDEX idx_employee_reports_to (reports_to),
    INDEX idx_employee_employment_status (employment_status, is_active, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 6. Users (System accounts)
-- ============================================
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    profile_picture VARCHAR(255) NULL,
    role ENUM('Admin', 'HR Manager', 'HR Supervisor', 'HR Staff', 'Employee') NOT NULL,
    branch_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    first_login_completed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE SET NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE SET NULL,
    INDEX idx_user_role (role),
    INDEX idx_user_role_status (is_active, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 7. Employee PDS Submissions (Self-Service)
-- ============================================
DROP TABLE IF EXISTS employee_pds_submissions;
CREATE TABLE employee_pds_submissions (
    submission_id   INT AUTO_INCREMENT PRIMARY KEY,
    employee_id     INT NOT NULL,
    submitted_by    INT NOT NULL,
    status          ENUM('Draft','Submitted','Under Review','Approved','Rejected','Changes Requested') DEFAULT 'Draft',
    hr_notes        TEXT NULL,
    reviewed_by     INT NULL,
    reviewed_at     DATETIME NULL,
    submitted_at    DATETIME NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pds_employee    FOREIGN KEY (employee_id)  REFERENCES employees(employee_id) ON DELETE CASCADE,
    CONSTRAINT fk_pds_submitter   FOREIGN KEY (submitted_by) REFERENCES users(user_id)         ON DELETE CASCADE,
    CONSTRAINT fk_pds_reviewer    FOREIGN KEY (reviewed_by)  REFERENCES users(user_id)         ON DELETE SET NULL,
    INDEX idx_pds_status (status, employee_id),
    INDEX idx_pds_submitted_at (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 8. Employee Details (Physical & Citizenship)
-- ============================================
DROP TABLE IF EXISTS employee_details;
CREATE TABLE employee_details (
    detail_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    height_m DECIMAL(4,2) NULL,
    weight_kg DECIMAL(5,2) NULL,
    blood_type VARCHAR(5) NULL,
    citizenship VARCHAR(100) DEFAULT 'Filipino',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_details_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_details_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 9. Employee Government IDs
-- ============================================
DROP TABLE IF EXISTS employee_government_ids;
CREATE TABLE employee_government_ids (
    id_entry_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    sss_number VARCHAR(50) NULL,
    philhealth_number VARCHAR(50) NULL,
    pagibig_number VARCHAR(50) NULL,
    tin_number VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ids_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_ids_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 10. Employee Addresses
-- ============================================
DROP TABLE IF EXISTS employee_addresses;
CREATE TABLE employee_addresses (
    address_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    address_type ENUM('Residential', 'Permanent') NOT NULL,
    region VARCHAR(150) NULL,
    house_no VARCHAR(100) NULL,
    street VARCHAR(150) NULL,
    subdivision VARCHAR(150) NULL,
    barangay VARCHAR(150) NULL,
    city VARCHAR(150) NULL,
    province VARCHAR(150) NULL,
    zip_code VARCHAR(10) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_addresses_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_employee_address_type (employee_id, address_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 11. Employee Contacts
-- ============================================
DROP TABLE IF EXISTS employee_contacts;
CREATE TABLE employee_contacts (
    contact_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    telephone_number VARCHAR(20) NULL,
    mobile_number VARCHAR(20) NULL,
    personal_email VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_contacts_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_contacts_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 12. Employee Emergency Contacts
-- ============================================
DROP TABLE IF EXISTS employee_emergency_contacts;
CREATE TABLE employee_emergency_contacts (
    emergency_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    contact_name VARCHAR(150) NOT NULL,
    relationship VARCHAR(50) NULL,
    contact_number VARCHAR(20) NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_emergency_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    INDEX idx_emergency_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 13. Employee Disclosures (Section 10)
-- ============================================
DROP TABLE IF EXISTS employee_disclosures;
CREATE TABLE employee_disclosures (
    disclosure_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    is_related_to_company TINYINT(1) DEFAULT 0,
    related_details TEXT NULL,
    has_admin_offense TINYINT(1) DEFAULT 0,
    admin_offense_details TEXT NULL,
    has_criminal_charge TINYINT(1) DEFAULT 0,
    criminal_charge_details TEXT NULL,
    has_criminal_conviction TINYINT(1) DEFAULT 0,
    criminal_conviction_details TEXT NULL,
    has_been_separated TINYINT(1) DEFAULT 0,
    separation_details TEXT NULL,
    is_pwd TINYINT(1) DEFAULT 0,
    pwd_details TEXT NULL,
    is_solo_parent TINYINT(1) DEFAULT 0,
    solo_parent_details TEXT NULL,
    has_recent_hospital TINYINT(1) DEFAULT 0,
    hospital_details TEXT NULL,
    has_current_treatment TINYINT(1) DEFAULT 0,
    treatment_details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_disclosures_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_disclosures_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 14. Employee Family (Section 2)
-- ============================================
DROP TABLE IF EXISTS employee_family;
CREATE TABLE employee_family (
    family_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    member_type ENUM('Spouse', 'Father', 'Mother') NOT NULL,
    surname VARCHAR(100) NULL,
    first_name VARCHAR(100) NULL,
    middle_name VARCHAR(100) NULL,
    name_extension VARCHAR(10) NULL,
    occupation VARCHAR(150) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_family_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_family_member_type (employee_id, member_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 15. Employee Children
-- ============================================
DROP TABLE IF EXISTS employee_children;
CREATE TABLE employee_children (
    child_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    surname VARCHAR(100) NULL,
    first_name VARCHAR(100) NULL,
    middle_name VARCHAR(100) NULL,
    date_of_birth DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_children_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 16. Employee Siblings
-- ============================================
DROP TABLE IF EXISTS employee_siblings;
CREATE TABLE employee_siblings (
    sibling_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    surname VARCHAR(100) NULL,
    first_name VARCHAR(100) NULL,
    middle_name VARCHAR(100) NULL,
    date_of_birth DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_siblings_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 17. Professional Background (Sub-tables)
-- ============================================
DROP TABLE IF EXISTS employee_education;
CREATE TABLE employee_education (
    education_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    education_level ENUM('Elementary', 'Secondary', 'Senior High School', 'Vocational', 'College', 'Graduate Studies') NOT NULL,
    school_name VARCHAR(255) NULL,
    degree_course VARCHAR(255) NULL,
    period_from DATE NULL,
    period_to DATE NULL,
    highest_level_units VARCHAR(100) NULL,
    year_graduated VARCHAR(10) NULL,
    honors_received VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_edu_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 18. Employee Work Experience
-- ============================================
DROP TABLE IF EXISTS employee_work_experience;
CREATE TABLE employee_work_experience (
    work_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date_from DATE NULL,
    date_to DATE NULL,
    job_title VARCHAR(150) NULL,
    company_name VARCHAR(255) NULL,
    monthly_salary DECIMAL(12,2) NULL,
    appointment_status VARCHAR(100) NULL,
    reason_for_leaving TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_work_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 19. Evaluation Templates
-- ============================================
DROP TABLE IF EXISTS evaluation_templates;
CREATE TABLE evaluation_templates (
    template_id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    target_department VARCHAR(100) NULL,
    evaluation_type ENUM('Initial','Final','Quarterly','Annual') DEFAULT 'Annual',
    kra_weight DECIMAL(5,2) DEFAULT 80.00,
    behavior_weight DECIMAL(5,2) DEFAULT 20.00,
    form_code VARCHAR(50) DEFAULT 'HRD Form-013.01',
    revision_date DATE NULL,
    effective_date_form DATE NULL,
    status ENUM('Draft', 'Active', 'Archived') DEFAULT 'Draft',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_template_creator FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_template_status (status, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 20. Evaluation Criteria
-- ============================================
DROP TABLE IF EXISTS evaluation_criteria;
CREATE TABLE evaluation_criteria (
    criterion_id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    section ENUM('KRA','Behavior') DEFAULT 'KRA',
    criterion_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    kpi_description TEXT NULL,
    weight DECIMAL(5,2) NOT NULL,
    scoring_method ENUM('Scale_1_5', 'Scale_1_10', 'Percentage', 'Scale_1_4') DEFAULT 'Scale_1_4',
    sort_order INT DEFAULT 0,
    is_custom TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_criteria_template FOREIGN KEY (template_id) REFERENCES evaluation_templates(template_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 21. Evaluations 
-- ============================================
DROP TABLE IF EXISTS evaluations;
CREATE TABLE evaluations (
    evaluation_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    template_id INT NOT NULL,
    evaluation_type ENUM('Initial','Final','Quarterly','Annual') DEFAULT 'Annual',
    evaluation_period_start DATE NULL,
    evaluation_period_end DATE NULL,
    submitted_by INT NULL,
    assigned_by INT NULL,
    assigned_at DATETIME NULL,
    endorsed_by INT NULL,
    approved_by INT NULL,
    supervisor_confirmed_by INT NULL,
    supervisor_confirmed_date DATETIME NULL,
    supervisor_altered_scores TINYINT(1) DEFAULT 0,
    sent_to_hr_date DATETIME NULL,
    sent_to_hr_by INT NULL,
    status ENUM('Draft', 'Pending Self-Rating', 'Pending Supervisor', 'Pending HR Consolidation', 'Pending Manager', 'Supervisor Confirmed', 'Approved', 'Rejected', 'Returned') DEFAULT 'Draft',
    total_score DECIMAL(5,2) NULL,
    kra_subtotal DECIMAL(5,2) NULL,
    behavior_average DECIMAL(5,2) NULL,
    performance_level VARCHAR(50) NULL,
    submitted_date DATETIME NULL,
    endorsed_date DATETIME NULL,
    approved_date DATETIME NULL,
    staff_comments TEXT NULL,
    employee_consent_agreed TINYINT(1) DEFAULT 0,
    employee_signature_data LONGTEXT NULL,
    employee_signed_at DATETIME NULL,
    supervisor_comments TEXT NULL,
    supervisor_rating DECIMAL(5,2) NULL,
    manager_comments TEXT NULL,
    manager_rating DECIMAL(5,2) NULL,
    evaluator_comments TEXT NULL,
    current_position VARCHAR(150) NULL,
    months_in_position INT NULL,
    desired_position VARCHAR(150) NULL,
    target_date DATE NULL,
    career_growth_suited TINYINT(1) DEFAULT 0,
    career_growth_details TEXT NULL,
    hr_received_date DATE NULL,
    hr_received_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_eval_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    CONSTRAINT fk_eval_template FOREIGN KEY (template_id) REFERENCES evaluation_templates(template_id) ON DELETE CASCADE,
    CONSTRAINT fk_eval_submitter FOREIGN KEY (submitted_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_eval_assigner FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_eval_endorser FOREIGN KEY (endorsed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_eval_approver FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_eval_status (status, deleted_at),
    INDEX idx_eval_date (approved_date, submitted_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 22. Evaluation Scores
-- ============================================
DROP TABLE IF EXISTS evaluation_scores;
CREATE TABLE evaluation_scores (
    score_id INT AUTO_INCREMENT PRIMARY KEY,
    evaluation_id INT NOT NULL,
    criterion_id INT NOT NULL,
    score_value DECIMAL(5,2) NOT NULL,
    weighted_score DECIMAL(5,2) NOT NULL,
    supervisor_override_score DECIMAL(5,2) NULL DEFAULT NULL,
    supervisor_override_by INT NULL DEFAULT NULL,
    supervisor_override_at DATETIME NULL DEFAULT NULL,
    manager_override_score DECIMAL(5,2) NULL DEFAULT NULL,
    manager_override_by INT NULL DEFAULT NULL,
    manager_override_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_score_eval FOREIGN KEY (evaluation_id) REFERENCES evaluations(evaluation_id) ON DELETE CASCADE,
    CONSTRAINT fk_score_criteria FOREIGN KEY (criterion_id) REFERENCES evaluation_criteria(criterion_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 23. Evaluation Dev Plans
-- ============================================
DROP TABLE IF EXISTS evaluation_dev_plans;
CREATE TABLE evaluation_dev_plans (
    plan_id INT AUTO_INCREMENT PRIMARY KEY,
    evaluation_id INT NOT NULL,
    improvement_area TEXT NULL,
    support_needed TEXT NULL,
    time_frame VARCHAR(100) NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_devplan_eval FOREIGN KEY (evaluation_id) REFERENCES evaluations(evaluation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 24. Career Progression Movements
-- ============================================
DROP TABLE IF EXISTS career_movements;
CREATE TABLE career_movements (
    movement_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    movement_type ENUM('Promotion', 'Transfer', 'Demotion', 'Role Change') NOT NULL,
    previous_position VARCHAR(100) NULL,
    new_position VARCHAR(100) NOT NULL,
    previous_branch_id INT NULL,
    new_branch_id INT NULL,
    effective_date DATE NOT NULL,
    reason TEXT NULL,
    logged_by INT NULL,
    approved_by INT NULL,
    decision_date DATETIME NULL,
    manager_comments TEXT NULL,
    approval_status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    is_applied TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_career_progression_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    CONSTRAINT fk_career_progression_logger FOREIGN KEY (logged_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_career_progression_approver FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_career_progression_prev_branch FOREIGN KEY (previous_branch_id) REFERENCES branches(branch_id) ON DELETE SET NULL,
    CONSTRAINT fk_career_progression_new_branch FOREIGN KEY (new_branch_id) REFERENCES branches(branch_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 25. Notifications
-- ============================================
DROP TABLE IF EXISTS notifications;
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 26. Audit Logs
-- ============================================
DROP TABLE IF EXISTS audit_logs;
CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    module_name VARCHAR(100) NULL,
    entity_id INT NULL,
    target_employee_id INT NULL,
    details TEXT NULL,
    previous_value TEXT NULL,
    new_value TEXT NULL,
    branch_id INT NULL,
    department_id INT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    action_status ENUM('Successful','Failed','Cancelled') NOT NULL DEFAULT 'Successful',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 27. System Settings
-- ============================================
DROP TABLE IF EXISTS system_settings;
CREATE TABLE system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 28. Employee Trainings
-- ============================================
DROP TABLE IF EXISTS employee_trainings;
CREATE TABLE employee_trainings (
    training_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date_from DATE NULL,
    date_to DATE NULL,
    training_title VARCHAR(255) NULL,
    training_type VARCHAR(100) NULL,
    no_of_hours DECIMAL(7,2) NULL,
    conducted_by VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_train_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 29. Employee Voluntary Work
-- ============================================
DROP TABLE IF EXISTS employee_voluntary_work;
CREATE TABLE employee_voluntary_work (
    voluntary_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date_from DATE NULL,
    date_to DATE NULL,
    organization_name VARCHAR(255) NULL,
    organization_address TEXT NULL,
    no_of_hours DECIMAL(7,2) NULL,
    position_nature VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_vol_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 30. System Settings
-- ============================================
DROP TABLE IF EXISTS system_settings;
CREATE TABLE system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 31. Employee Eligibility
-- ============================================
DROP TABLE IF EXISTS employee_eligibility;
CREATE TABLE employee_eligibility (
    eligibility_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    license_title VARCHAR(255) NULL,
    date_from DATE NULL,
    date_to DATE NULL,
    license_number VARCHAR(100) NULL,
    date_of_exam DATE NULL,
    place_of_exam VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_elig_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 32. Employee Skills
-- ============================================
DROP TABLE IF EXISTS employee_skills;
CREATE TABLE employee_skills (
    skill_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    skill_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_skill_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 33. Employee Recognitions
-- ============================================
DROP TABLE IF EXISTS employee_recognitions;
CREATE TABLE employee_recognitions (
    recognition_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    recognition_title VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_recog_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 34. Employee Memberships
-- ============================================
DROP TABLE IF EXISTS employee_memberships;
CREATE TABLE employee_memberships (
    membership_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    organization_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_member_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 35. Employee Real Properties
-- ============================================
DROP TABLE IF EXISTS employee_real_properties;
CREATE TABLE employee_real_properties (
    property_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    description VARCHAR(255) NULL,
    kind VARCHAR(100) NULL,
    exact_location TEXT NULL,
    assessed_value DECIMAL(14,2) NULL,
    market_value DECIMAL(14,2) NULL,
    acquisition_year_mode VARCHAR(100) NULL,
    acquisition_cost DECIMAL(14,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_real_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 36. Employee Personal Properties
-- ============================================
DROP TABLE IF EXISTS employee_personal_properties;
CREATE TABLE employee_personal_properties (
    property_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    description VARCHAR(255) NULL,
    year_acquired VARCHAR(10) NULL,
    acquisition_cost DECIMAL(14,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pers_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 37. Employee Liabilities
-- ============================================
DROP TABLE IF EXISTS employee_liabilities;
CREATE TABLE employee_liabilities (
    liability_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    nature_of_liability VARCHAR(255) NULL,
    creditor_name VARCHAR(255) NULL,
    outstanding_balance DECIMAL(14,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_liab_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 38. Employee References
-- ============================================
DROP TABLE IF EXISTS employee_references;
CREATE TABLE employee_references (
    reference_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    reference_name VARCHAR(200) NULL,
    reference_address TEXT NULL,
    reference_telephone VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ref_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 39. Login Attempts (Brute Force Protection)
-- ============================================
DROP TABLE IF EXISTS login_attempts;
CREATE TABLE login_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    identifier VARCHAR(100) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempt_time),
    INDEX idx_identifier_time (identifier, attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 40. Evaluation Governance Approvers
-- ============================================
DROP TABLE IF EXISTS evaluation_governance_approvers;
CREATE TABLE evaluation_governance_approvers (
    governance_approver_id INT AUTO_INCREMENT PRIMARY KEY,
    governance_type ENUM('Board of Directors','Audit Committee','President','Division VP') NOT NULL,
    department_id INT NULL,
    user_id INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_governance_user (governance_type, department_id, user_id),
    CONSTRAINT fk_governance_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_governance_department FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 41. Evaluation Packages
-- ============================================
DROP TABLE IF EXISTS evaluation_packages;
CREATE TABLE evaluation_packages (
    package_id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    template_id INT NOT NULL,
    evaluation_type ENUM('Initial','Final','Quarterly','Annual') NOT NULL DEFAULT 'Annual',
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    consolidator_employee_id INT NULL,
    shared_behavior_score DECIMAL(5,2) NULL,
    current_step_order INT NULL,
    status ENUM('Pending Self-Ratings','Pending Consolidation','Pending Review','Pending Audit Approval','Pending Board Approval','Approved and Applied','Returned','Cancelled') NOT NULL DEFAULT 'Pending Self-Ratings',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_evaluation_package (department_id, template_id, period_start, period_end),
    INDEX idx_package_status (status, current_step_order),
    CONSTRAINT fk_package_department FOREIGN KEY (department_id) REFERENCES departments(department_id),
    CONSTRAINT fk_package_template FOREIGN KEY (template_id) REFERENCES evaluation_templates(template_id),
    CONSTRAINT fk_package_consolidator FOREIGN KEY (consolidator_employee_id) REFERENCES employees(employee_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 42. Evaluation Package Members
-- ============================================
DROP TABLE IF EXISTS evaluation_package_members;
CREATE TABLE evaluation_package_members (
    package_id INT NOT NULL,
    evaluation_id INT NOT NULL,
    PRIMARY KEY (package_id, evaluation_id),
    CONSTRAINT fk_package_member_package FOREIGN KEY (package_id) REFERENCES evaluation_packages(package_id) ON DELETE CASCADE,
    CONSTRAINT fk_package_member_evaluation FOREIGN KEY (evaluation_id) REFERENCES evaluations(evaluation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 43. Evaluation Package Route Steps
-- ============================================
DROP TABLE IF EXISTS evaluation_package_route_steps;
CREATE TABLE evaluation_package_route_steps (
    package_route_step_id INT AUTO_INCREMENT PRIMARY KEY,
    package_id INT NOT NULL,
    step_order INT NOT NULL,
    reviewer_employee_id INT NULL,
    reviewer_user_id INT NULL,
    step_label VARCHAR(160) NOT NULL,
    step_type ENUM('Consolidation','Review','Governance') NOT NULL DEFAULT 'Review',
    action_status ENUM('Waiting','Pending','Approved','Returned','Skipped') NOT NULL DEFAULT 'Waiting',
    acted_at DATETIME NULL,
    comments TEXT NULL,
    UNIQUE KEY uq_package_route_step (package_id, step_order),
    INDEX idx_route_reviewer (reviewer_user_id, action_status),
    CONSTRAINT fk_route_package FOREIGN KEY (package_id) REFERENCES evaluation_packages(package_id) ON DELETE CASCADE,
    CONSTRAINT fk_route_employee FOREIGN KEY (reviewer_employee_id) REFERENCES employees(employee_id) ON DELETE SET NULL,
    CONSTRAINT fk_route_user FOREIGN KEY (reviewer_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 44. Evaluation Package Audit
-- ============================================
DROP TABLE IF EXISTS evaluation_package_audit;
CREATE TABLE evaluation_package_audit (
    package_audit_id INT AUTO_INCREMENT PRIMARY KEY,
    package_id INT NOT NULL,
    user_id INT NULL,
    action VARCHAR(80) NOT NULL,
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_package_audit (package_id, created_at),
    CONSTRAINT fk_pkg_audit_pkg FOREIGN KEY (package_id) REFERENCES evaluation_packages(package_id) ON DELETE CASCADE,
    CONSTRAINT fk_pkg_audit_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- Performance Indexes (run after initial schema)
-- ============================================
-- users.employee_id is used in every JOIN across listing pages but has no index
ALTER TABLE users ADD INDEX idx_user_employee_id (employee_id);
-- employees.job_title used for position filtering
ALTER TABLE employees ADD INDEX idx_employee_job_title (job_title);
-- departments.department_name used in HR filter lookups
ALTER TABLE departments ADD INDEX idx_department_name (department_name);
