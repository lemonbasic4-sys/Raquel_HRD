-- Annual workflow only. Safe to import repeatedly; existing evaluations are preserved.
CREATE TABLE IF NOT EXISTS annual_evaluation_cycles (
    cycle_id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    started_at DATETIME NULL,
    started_by INT NULL,
    self_rating_deadline DATE NULL,
    first_finalized_at DATETIME NULL,
    completed_at DATETIME NULL,
    first_completed_at DATETIME NULL,
    ready_for_approval_at DATETIME NULL,
    cycle_approved_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY annual_department_period (department_id, period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS annual_kra_approvals (
    approval_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    approved_by INT NOT NULL,
    approved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    remarks TEXT NOT NULL,
    ratings_json LONGTEXT NOT NULL,
    rater_signature_data LONGTEXT NULL,
    rater_signed_at DATETIME NULL,
    INDEX annual_kra_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS annual_evaluation_members (
    member_id INT AUTO_INCREMENT PRIMARY KEY,
    cycle_id INT NOT NULL,
    employee_id INT NOT NULL,
    evaluation_id INT NULL,
    position_id INT NOT NULL,
    reviewer_position_id INT NULL,
    position_name VARCHAR(150) NOT NULL,
    self_values_json TEXT NULL,
    current_version INT NOT NULL DEFAULT 0,
    revision_pending TINYINT NOT NULL DEFAULT 0,
    finalized_at DATETIME NULL,
    is_late_participant TINYINT NOT NULL DEFAULT 0,
    UNIQUE KEY annual_member (cycle_id, employee_id),
    UNIQUE KEY annual_evaluation (evaluation_id),
    CONSTRAINT annual_member_cycle FOREIGN KEY (cycle_id) REFERENCES annual_evaluation_cycles(cycle_id),
    INDEX annual_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS annual_peer_submissions (
    submission_id INT AUTO_INCREMENT PRIMARY KEY,
    cycle_id INT NOT NULL,
    rater_employee_id INT NOT NULL,
    target_employee_id INT NOT NULL,
    submitted_by INT NOT NULL,
    scores_json TEXT NOT NULL,
    rater_signature_data LONGTEXT NULL,
    rater_signed_at DATETIME NULL,
    is_late TINYINT NOT NULL DEFAULT 0,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY annual_peer_once (cycle_id, rater_employee_id, target_employee_id),
    CONSTRAINT annual_peer_cycle FOREIGN KEY (cycle_id) REFERENCES annual_evaluation_cycles(cycle_id),
    INDEX annual_peer_target (cycle_id, target_employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS annual_evaluation_versions (
    version_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    version_number INT NOT NULL,
    finalized_by INT NOT NULL,
    finalized_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    remarks TEXT NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    UNIQUE KEY annual_version (member_id, version_number),
    CONSTRAINT annual_version_member FOREIGN KEY (member_id) REFERENCES annual_evaluation_members(member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
