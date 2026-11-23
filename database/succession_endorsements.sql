-- Run once on an existing database before using Succession Planning endorsements.
CREATE TABLE IF NOT EXISTS succession_endorsements (
    endorsement_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    target_job_title_id INT NOT NULL,
    recommended_by INT NOT NULL,
    recommendation_reason TEXT NOT NULL,
    status ENUM('Pending HR Review','Pending CEO Approval','Approved','Returned','Rejected') NOT NULL DEFAULT 'Pending HR Review',
    hr_reviewed_by INT NULL,
    hr_reviewed_at DATETIME NULL,
    hr_review_notes TEXT NULL,
    qualifications_verified TINYINT(1) NOT NULL DEFAULT 0,
    opportunity_verified TINYINT(1) NOT NULL DEFAULT 0,
    records_verified TINYINT(1) NOT NULL DEFAULT 0,
    ceo_decided_by INT NULL,
    ceo_decided_at DATETIME NULL,
    ceo_decision_notes TEXT NULL,
    previous_job_title VARCHAR(150) NULL,
    applied_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_succession_employee_status (employee_id, status),
    INDEX idx_succession_status_created (status, created_at),
    CONSTRAINT fk_succession_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    CONSTRAINT fk_succession_target FOREIGN KEY (target_job_title_id) REFERENCES job_titles(job_title_id),
    CONSTRAINT fk_succession_recommender FOREIGN KEY (recommended_by) REFERENCES users(user_id),
    CONSTRAINT fk_succession_hr_reviewer FOREIGN KEY (hr_reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_succession_ceo_decider FOREIGN KEY (ceo_decided_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Existing installations: run these once after checking that the columns are absent.
-- ALTER TABLE succession_endorsements ADD COLUMN previous_job_title VARCHAR(150) NULL;
-- ALTER TABLE succession_endorsements ADD COLUMN applied_at DATETIME NULL;
