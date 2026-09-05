<?php

// ============================================

// Helper Functions

// ============================================



// ============================================
// CSRF Protection
// ============================================

/**
 * Generate (or retrieve) the session-scoped CSRF token.
 * Call this on any page that renders a POST form.
 */
function generateCsrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF input field for use inside HTML forms.
 */
function csrfField(): string
{
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify the CSRF token submitted with a POST request.
 * Accepts token from POST body (forms) or X-CSRF-Token header (AJAX/fetch).
 * Immediately terminates the request with 403 if invalid.
 */
function verifyCsrfToken(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $submitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    if (empty($submitted) && function_exists('getallheaders')) {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        $submitted = $headers['x-csrf-token'] ?? '';
    }

    $expected = $_SESSION['csrf_token'] ?? '';

    if (empty($expected) || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                   || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
                   || str_contains($_SERVER['HTTP_CONTENT_TYPE'] ?? ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')
                   || str_contains($_SERVER['REQUEST_URI'] ?? '', '/ajax/')
                   || str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/ajax/');
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Invalid CSRF token. Please refresh the page and try again.',
            ]);
        } else {
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Security Error</title>'
               . '<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f8f9fa;}'
               . '.card{background:#fff;border-radius:12px;padding:2.5rem;max-width:420px;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.1);}'
               . '.icon{font-size:3rem;margin-bottom:1rem;}.btn{display:inline-block;margin-top:1.5rem;padding:.75rem 2rem;background:#d32f2f;color:#fff;'
               . 'border-radius:8px;text-decoration:none;font-weight:600;}</style></head>'
               . '<body><div class="card"><div class="icon">&#x26a0;&#xfe0f;</div>'
               . '<h2 style="color:#d32f2f;margin:0 0 .5rem">Security Error</h2>'
               . '<p style="color:#555;margin:0">Invalid or missing CSRF token.<br>Please go back and try again.</p>'
               . '<a class="btn" href="javascript:history.back()">&#8592; Go Back</a></div></body></html>';
        }
        exit;
    }
}



/**

 * Sanitize output to prevent XSS

 */

function e($string)
{

    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');

}



/**

 * Get a time-based greeting for the logged-in user

 * Returns HTML string with greeting text and icon

 */

function getGreeting($full_name = '')
{

    // Use Philippine Standard Time (UTC+8) explicitly to avoid server UTC offset issues

    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));

    $hour = (int) $now->format('G'); // 0-23



    if ($hour >= 5 && $hour < 12) {

        $greeting = 'Good morning';

        $icon = 'fas fa-sun';

        $color = '#F6C23E';

    } elseif ($hour >= 12 && $hour < 18) {

        $greeting = 'Good afternoon';

        $icon = 'fas fa-cloud-sun';

        $color = '#F6A623';

    } else {

        $greeting = 'Good evening';

        $icon = 'fas fa-moon';

        $color = '#a78bfa';

    }



    // Extract first name only for a friendly feel

    $first_name = trim(explode(' ', trim($full_name))[0]);

    $display = $first_name ? ", {$first_name}!" : '!';



    return '<span style="opacity:.85;font-size:.92rem;font-weight:500;">'

        . '<i class="' . $icon . ' me-2" style="color:' . $color . ';"></i>'

        . $greeting . e($display)

        . '</span>';

}



/**

 * Get employee profile picture URL with fallback

 */

function getEmployeeAvatar($profile_picture)
{

    $fallback = BASE_URL . '/assets/img/logo/logo.png';

    if (!empty($profile_picture)) {

        // Check standard employee folder

        $path = __DIR__ . '/../assets/img/employees/' . $profile_picture;

        if (file_exists($path)) {

            return BASE_URL . '/assets/img/employees/' . $profile_picture;

        }

        // Check sample images folder (for seed data support)

        $path_sample = __DIR__ . '/../assets/img/sample_images/' . $profile_picture;

        if (file_exists($path_sample)) {

            return BASE_URL . '/assets/img/sample_images/' . $profile_picture;

        }

    }

    return $fallback;

}

/**
 * Get a system account avatar, falling back to the linked employee avatar.
 */
function getUserAvatar($user_profile_picture, $employee_profile_picture = null)
{
    if (!empty($user_profile_picture)) {
        $path = __DIR__ . '/../assets/img/users/' . $user_profile_picture;
        if (file_exists($path)) {
            return BASE_URL . '/assets/img/users/' . $user_profile_picture;
        }
    }

    return getEmployeeAvatar($employee_profile_picture);
}



/**

 * Get the company-facing employee ID/code for display.

 */

function getEmployeeDisplayId($employee)
{

    if (is_array($employee) && !empty($employee['employee_code'])) {

        return (string) $employee['employee_code'];

    }



    if (is_array($employee) && !empty($employee['employee_id'])) {

        return (string) $employee['employee_id'];

    }



    return 'Not Assigned';

}



/**

 * Create a notification for a user

 */

function createNotification($conn, $user_id, $title, $message, $link = null)
{

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");

    $stmt->bind_param("isss", $user_id, $title, $message, $link);

    $stmt->execute();

    $stmt->close();

}



/**

 * Resolve the preferred linked user account for a specific portal context.

 * For the employee portal, only the explicit Employee account should be used.

 */

function getPreferredLinkedUserId($conn, $employee_id, $context = 'employee_portal')
{

    $employee_id = (int) $employee_id;

    if ($employee_id <= 0) {

        return null;

    }



    if ($context === 'employee_portal') {

        $stmt = $conn->prepare("

            SELECT user_id

            FROM users

            WHERE employee_id = ? AND role = 'Employee' AND is_active = 1

            ORDER BY user_id ASC

            LIMIT 1

        ");

    } else {

        $stmt = $conn->prepare("

            SELECT user_id

            FROM users

            WHERE employee_id = ? AND is_active = 1

            ORDER BY user_id ASC

            LIMIT 1

        ");

    }



    $stmt->bind_param("i", $employee_id);

    $stmt->execute();

    $result = $stmt->get_result()->fetch_assoc();

    $stmt->close();



    return $result ? (int) $result['user_id'] : null;

}



/**

 * Log an audit event

 */

function ensureAuditTrailSchema($conn): bool
{
    static $checked = false;
    if ($checked) {
        return true;
    }

    try {
        $columns = [
            'module_name' => "VARCHAR(100) NULL AFTER entity_type",
            'target_employee_id' => "INT NULL AFTER entity_id",
            'previous_value' => "TEXT NULL AFTER details",
            'new_value' => "TEXT NULL AFTER previous_value",
            'branch_id' => "INT NULL AFTER new_value",
            'department_id' => "INT NULL AFTER branch_id",
            'user_agent' => "VARCHAR(500) NULL AFTER ip_address",
            'action_status' => "ENUM('Successful','Failed','Cancelled') NOT NULL DEFAULT 'Successful' AFTER user_agent",
        ];

        foreach ($columns as $column => $definition) {
            $escaped = $conn->real_escape_string($column);
            $exists = $conn->query("SHOW COLUMNS FROM audit_logs LIKE '{$escaped}'")->num_rows > 0;
            if (!$exists) {
                $conn->query("ALTER TABLE audit_logs ADD COLUMN {$column} {$definition}");
            }
        }
        $checked = true;
        return true;
    } catch (Throwable $e) {
        error_log('Audit trail schema upgrade failed: ' . $e->getMessage());
        return false;
    }
}

function logAudit($conn, $user_id, $action_type, $entity_type, $entity_id = null, $details = null, array $context = [])
{
    ensureAuditTrailSchema($conn);

    // Ensure user_id actually exists to avoid Foreign Key constraint errors (e.g. after DB reset)

    $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");

    $stmt->bind_param("i", $user_id);

    $stmt->execute();

    $user_exists = $stmt->get_result()->num_rows > 0;

    $stmt->close();



    if (!$user_exists) {

        $user_id = null; // Record as system/unknown if user doesn't exist

    }



    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown device', 0, 500);
    $module = $context['module'] ?? auditModuleForEntity($entity_type);
    $target_employee_id = $context['target_employee_id'] ?? (strcasecmp($entity_type, 'Employee') === 0 ? $entity_id : null);
    $previous_value = $context['previous_value'] ?? null;
    $new_value = $context['new_value'] ?? null;
    $branch_id = $context['branch_id'] ?? null;
    $department_id = $context['department_id'] ?? null;
    $action_status = $context['status'] ?? 'Successful';
    if (!in_array($action_status, ['Successful', 'Failed', 'Cancelled'], true)) {
        $action_status = 'Successful';
    }

    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, module_name, entity_id, target_employee_id, details, previous_value, new_value, branch_id, department_id, ip_address, user_agent, action_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssiisssiisss", $user_id, $action_type, $entity_type, $module, $entity_id, $target_employee_id, $details, $previous_value, $new_value, $branch_id, $department_id, $ip, $user_agent, $action_status);

    $stmt->execute();

    $stmt->close();

}

function auditModuleForEntity($entity_type): string
{
    $entity = strtolower($entity_type);
    if (str_contains($entity, 'evaluation')) return 'Performance & Evaluation';
    if (str_contains($entity, 'career') || str_contains($entity, 'movement')) return 'Career Progression';
    if (str_contains($entity, 'user') || str_contains($entity, 'permission') || str_contains($entity, 'role')) return 'User & Access Management';
    if (str_contains($entity, 'employee') || str_contains($entity, 'pds')) return 'Employee Management';
    if (str_contains($entity, 'branch') || str_contains($entity, 'department') || str_contains($entity, 'setting') || str_contains($entity, 'config')) return 'System Administration';
    return 'System Activity';
}



/**

 * Get unread notification count for current user, filtered by portal context

 * @param string $context 'employee' or 'hr'

 */

function getUnreadNotificationCount($conn, $user_id, $context = null)
{

    $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";

    if ($context === 'employee') {

        $sql .= " AND (link LIKE '%/employee/%' OR link IS NULL OR link = '')";

    } elseif ($context === 'hr') {
        $sql .= " AND (link NOT LIKE '%/employee/%' OR link LIKE '%/employee/team-evaluation%' OR link LIKE '%/employee/package-member%' OR link IS NULL OR link = '')";
    }



    $stmt = $conn->prepare($sql);

    $stmt->bind_param("i", $user_id);

    $stmt->execute();

    $result = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return $result['count'];

}



/**

 * Get recent notifications for a user, filtered by portal context

 * @param string $context 'employee' or 'hr'

 */

function getRecentNotifications($conn, $user_id, $limit = 5, $context = null)
{

    $sql = "SELECT * FROM notifications WHERE user_id = ?";

    if ($context === 'employee') {

        $sql .= " AND (link LIKE '%/employee/%' OR link IS NULL OR link = '')";

    } elseif ($context === 'hr') {
        $sql .= " AND (link NOT LIKE '%/employee/%' OR link LIKE '%/employee/team-evaluation%' OR link LIKE '%/employee/package-member%' OR link IS NULL OR link = '')";
    }

    $sql .= " ORDER BY created_at DESC LIMIT ?";



    $stmt = $conn->prepare($sql);

    $stmt->bind_param("ii", $user_id, $limit);

    $stmt->execute();

    $result = $stmt->get_result();

    $notifications = [];

    while ($row = $result->fetch_assoc()) {

        $notifications[] = $row;

    }

    $stmt->close();

    return $notifications;

}

/**
 * Get category icon and CSS class for a notification title
 */
function getNotifIconInfo($title)
{
    $t = strtolower($title ?? '');
    if (str_contains($t, 'approved') || str_contains($t, 'approval') || str_contains($t, 'confirmed') || str_contains($t, 'endorsed')) {
        return ['icon' => 'fas fa-check-circle', 'class' => 'approve'];
    }
    if (str_contains($t, 'rejected') || str_contains($t, 'reject')) {
        return ['icon' => 'fas fa-times-circle', 'class' => 'reject'];
    }
    if (str_contains($t, 'returned') || str_contains($t, 'revision')) {
        return ['icon' => 'fas fa-undo-alt', 'class' => 'return'];
    }
    if (str_contains($t, 'evaluation') || str_contains($t, 'validation') || str_contains($t, 'rating') || str_contains($t, 'pending')) {
        return ['icon' => 'fas fa-clipboard-check', 'class' => 'eval'];
    }
    return ['icon' => 'fas fa-bell', 'class' => 'system'];
}

/**
 * Format relative time ago for notifications
 */
function timeAgoFormat($datetime)
{
    if (empty($datetime)) return '';
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M d, Y', $time);
}



/**

 * Format date for display

 */

function formatDate($date, $format = 'M d, Y')
{

    if (empty($date))

        return 'N/A';

    return date($format, strtotime($date));

}



/**

 * Format datetime for display

 */

function formatDateTime($datetime, $format = 'M d, Y h:i A')
{

    if (empty($datetime))

        return 'N/A';

    return date($format, strtotime($datetime));

}



/**

 * Get performance level badge class

 */

function getPerformanceBadgeClass($level)
{

    switch ($level) {

        case 'Outstanding':

            return 'bg-success';

        case 'Exceeds Expectations':

            return 'bg-info';

        case 'Meets Expectations':

            return 'bg-warning text-dark';

        case 'Needs Improvement':

            return 'bg-danger';

        // Legacy support

        case 'Excellent':

            return 'bg-success';

        case 'Above Average':

            return 'bg-info';

        case 'Average':

            return 'bg-warning text-dark';

        default:

            return 'bg-secondary';

    }

}



/**

 * Get status badge class

 */

function getStatusBadgeClass($status)
{

    switch ($status) {

        case 'Draft':

            return 'bg-secondary';

        case 'Pending Self-Rating':

            return 'bg-primary';

        case 'Pending Dept Supervisor':

            return 'bg-warning text-dark';

        case 'Pending Dept Manager':

            return 'bg-info text-dark';

        case 'Pending Supervisor':

            return 'bg-warning text-dark';

        case 'Pending HR Consolidation':

            return 'bg-warning text-dark';

        case 'Pending Manager':

            return 'bg-info';

        case 'Supervisor Confirmed':

            return 'bg-info text-dark';

        case 'Approved':

            return 'bg-success';

        case 'Rejected':

            return 'bg-danger';

        case 'Returned':

            return 'bg-purple';

        default:

            return 'bg-secondary';

    }

}



/**

 * Calculate performance level based on score

 */

function getPerformanceLevel($score)
{

    // HRD Form-013.01 rating scale (1.00-4.00)

    if ($score >= 3.60)

        return 'Outstanding';

    if ($score >= 2.60)

        return 'Exceeds Expectations';

    if ($score >= 2.00)

        return 'Meets Expectations';

    return 'Needs Improvement';

}

/**
 * Return the hex color for a given performance level.
 * Outstanding        → #198754 (Green)
 * Exceeds Expectations → #0DCAF0 (Cyan/Info)
 * Meets Expectations → #FFC107 (Yellow/Warning)
 * Needs Improvement  → #DC3545 (Red/Danger)
 */
function getPerformanceLevelColor(string $level): string
{
    $map = [
        'Outstanding'          => '#198754',
        'Exceeds Expectations' => '#0DCAF0',
        'Meets Expectations'   => '#FFC107',
        'Needs Improvement'    => '#DC3545',
    ];
    return $map[$level] ?? '#6c757d';
}

/**
 * Return a Bootstrap badge class for a performance level.
 */
function getPerformanceLevelBadgeClass(string $level): string
{
    $map = [
        'Outstanding'          => 'bg-success',
        'Exceeds Expectations' => 'bg-info text-dark',
        'Meets Expectations'   => 'bg-warning text-dark',
        'Needs Improvement'    => 'bg-danger',
    ];
    return $map[$level] ?? 'bg-secondary';
}



/**

 *  ================================================================================

 * Calculate evaluation total using: weight × rating × average

 *

 * Formula: total = (kra_subtotal × behavior_average) / 4.0

 *

 *   kra_subtotal    = Σ(criterion_weight/100 × rating)  ← encodes weight × rating

 *   behavior_average= avg of all behavior ratings        ← the "average" factor

 *   ÷ 4.0           = normalises the product to 1–4 scale

 *

 * Examples (with weights summing correctly):

 *   Perfect KRA (4.0) × Perfect behavior (4.0) / 4 = 4.00  → Outstanding

 *   Perfect KRA (4.0) × Avg behavior    (2.0) / 4 = 2.00  → Meets Expectations

 * ================================================================================

 */

function calculateEvalTotal($kra_subtotal, $behavior_average, $kra_weight = 80, $behavior_weight = 20)
{
    return round(($kra_subtotal * $kra_weight / 100) + ($behavior_average * $behavior_weight / 100), 2);
}



/**

 * Redirect with a flash message

 */

function redirectWith($url, $type, $message)
{

    $_SESSION['flash_type'] = $type;

    $_SESSION['flash_message'] = $message;

    header("Location: " . $url);

    exit();

}



function getCareerProgressionMovementColumns($conn)
{
    $columns = [];
    try {
        $result = $conn->query("SHOW COLUMNS FROM career_movements");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columns[$row['Field']] = true;
            }
            $result->free();
        }
    } catch (mysqli_sql_exception $e) {
        return [];
    }
    return $columns;
}



function ensureCareerProgressionMovements($conn)
{
    static $ensured = false;
    if ($ensured) {
        return true;
    }

    try {

        $conn->query("

            CREATE TABLE IF NOT EXISTS career_movements (

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

            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4

        ");



        $columns = getCareerProgressionMovementColumns($conn);

        $optional_columns = [

            'decision_date' => "ALTER TABLE career_movements ADD COLUMN decision_date DATETIME NULL AFTER approved_by",

            'manager_comments' => "ALTER TABLE career_movements ADD COLUMN manager_comments TEXT NULL AFTER decision_date",

            'is_applied' => "ALTER TABLE career_movements ADD COLUMN is_applied TINYINT(1) DEFAULT 0 AFTER approval_status",

            'initiated_by_name' => "ALTER TABLE career_movements ADD COLUMN initiated_by_name VARCHAR(100) NULL AFTER reason",

            'initiated_by_role' => "ALTER TABLE career_movements ADD COLUMN initiated_by_role VARCHAR(50) NULL AFTER initiated_by_name",

            'initiated_via' => "ALTER TABLE career_movements ADD COLUMN initiated_via ENUM('Memo','Email','Verbal','Letter','Meeting','Employee Portal') NULL AFTER initiated_by_role",

            'request_source' => "ALTER TABLE career_movements ADD COLUMN request_source ENUM('HR Portal','Employee Portal') DEFAULT 'HR Portal' AFTER initiated_via",

        ];



        foreach ($optional_columns as $column => $sql) {

            if (!isset($columns[$column])) {

                $conn->query($sql);

            }

        }

        $new_portal_columns = [

            'portal_workflow_stage' =>
                "ALTER TABLE career_movements ADD COLUMN portal_workflow_stage
                 ENUM('Pending_Branch_Manager','Pending_HR_Supervisor','Pending_HR_Manager','Approved','Rejected')
                 NULL DEFAULT NULL AFTER request_source",

            'branch_manager_approved_by' =>
                "ALTER TABLE career_movements ADD COLUMN branch_manager_approved_by INT NULL AFTER portal_workflow_stage",

            'branch_manager_decision_date' =>
                "ALTER TABLE career_movements ADD COLUMN branch_manager_decision_date DATETIME NULL AFTER branch_manager_approved_by",

            'branch_manager_comments' =>
                "ALTER TABLE career_movements ADD COLUMN branch_manager_comments TEXT NULL AFTER branch_manager_decision_date",

            'hr_supervisor_approved_by' =>
                "ALTER TABLE career_movements ADD COLUMN hr_supervisor_approved_by INT NULL AFTER branch_manager_comments",

            'hr_supervisor_decision_date' =>
                "ALTER TABLE career_movements ADD COLUMN hr_supervisor_decision_date DATETIME NULL AFTER hr_supervisor_approved_by",

            'hr_supervisor_comments' =>
                "ALTER TABLE career_movements ADD COLUMN hr_supervisor_comments TEXT NULL AFTER hr_supervisor_decision_date",

        ];

        $columns = getCareerProgressionMovementColumns($conn);

        foreach ($new_portal_columns as $column => $sql) {

            if (!isset($columns[$column])) {

                $conn->query($sql);

            }

        }

    } catch (mysqli_sql_exception $e) {

        error_log('ensureCareerProgressionMovements error: ' . $e->getMessage());

        return false;

    }



    $ensured = true;

    return true;

}



/**
 * Ensures the employee_change_requests table exists (auto-migration).
 */
function ensureEmployeeChangeRequests($conn)
{
    static $ecr_ensured = false;
    if ($ecr_ensured) return true;
    try {
        $conn->query("
            CREATE TABLE IF NOT EXISTS employee_change_requests (
                request_id    INT AUTO_INCREMENT PRIMARY KEY,
                employee_id   INT NOT NULL,
                submitted_by  INT NOT NULL,
                changes_json  LONGTEXT NOT NULL,
                change_summary TEXT NULL,
                status        ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
                reviewed_by   INT NULL,
                reviewed_at   DATETIME NULL,
                manager_notes TEXT NULL,
                created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_ecr_employee  FOREIGN KEY (employee_id)  REFERENCES employees(employee_id) ON DELETE CASCADE,
                CONSTRAINT fk_ecr_submitter FOREIGN KEY (submitted_by) REFERENCES users(user_id) ON DELETE CASCADE,
                CONSTRAINT fk_ecr_reviewer  FOREIGN KEY (reviewed_by)  REFERENCES users(user_id) ON DELETE SET NULL,
                INDEX idx_ecr_status (status, employee_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $ecr_ensured = true;
        return true;
    } catch (mysqli_sql_exception $e) {
        return false;
    }
}

/**
 * Applies an approved employee change request JSON diff to the live employee record.
 * Handles the main employees table and all sub-tables.
 * Returns true on success, false on failure.
 */
function applyEmployeeChangeRequest($conn, $request_id)
{
    $stmt = $conn->prepare("SELECT * FROM employee_change_requests WHERE request_id = ? AND status = 'Approved' LIMIT 1");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$req) return false;

    $eid        = (int) $req['employee_id'];
    $changes    = json_decode($req['changes_json'], true);
    if (!$changes || !is_array($changes)) return false;

    // ── 1. Main employees table flat fields ──────────────────────────────────
    $emp_fields = [
        'employee_code','first_name','last_name','middle_name','name_extension',
        'date_of_birth','place_of_birth','gender','civil_status',
        'hire_date','job_title','job_title_id','department_id','rank_category_id','branch_id',
        'employment_status','employment_type','contract_start_date','contract_end_date','is_active',
    ];
    $set_parts = []; $set_vals = []; $set_types = '';
    foreach ($emp_fields as $f) {
        if (array_key_exists($f, $changes)) {
            $set_parts[] = "`$f` = ?";
            $set_vals[]  = $changes[$f]['new'];
            $set_types  .= 's';
        }
    }
    if ($set_parts) {
        $set_vals[] = $eid; $set_types .= 'i';
        $sql = "UPDATE employees SET " . implode(', ', $set_parts) . " WHERE employee_id = ?";
        $u = $conn->prepare($sql);
        $u->bind_param($set_types, ...$set_vals);
        $u->execute(); $u->close();
    }

    // ── 2. employee_details ──────────────────────────────────────────────────
    $det_fields = ['height_m','weight_kg','blood_type','citizenship'];
    $det_vals = [];
    foreach ($det_fields as $f) {
        $det_vals[$f] = isset($changes[$f]) ? $changes[$f]['new'] : null;
    }
    if (array_intersect_key($changes, array_flip($det_fields))) {
        // Fetch current to fill missing
        $cur = $conn->query("SELECT * FROM employee_details WHERE employee_id=$eid")->fetch_assoc() ?? [];
        $h = $det_vals['height_m'] ?? ($cur['height_m'] ?? null);
        $w = $det_vals['weight_kg'] ?? ($cur['weight_kg'] ?? null);
        $b = $det_vals['blood_type'] ?? ($cur['blood_type'] ?? null);
        $c = $det_vals['citizenship'] ?? ($cur['citizenship'] ?? 'Filipino');
        $u = $conn->prepare("REPLACE INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship) VALUES (?,?,?,?,?)");
        $u->bind_param("iddss", $eid, $h, $w, $b, $c); $u->execute(); $u->close();
    }

    // ── 3. employee_government_ids ───────────────────────────────────────────
    $gov_fields = ['sss_number','philhealth_number','pagibig_number','tin_number'];
    if (array_intersect_key($changes, array_flip($gov_fields))) {
        $cur = $conn->query("SELECT * FROM employee_government_ids WHERE employee_id=$eid")->fetch_assoc() ?? [];
        $s = isset($changes['sss_number']) ? $changes['sss_number']['new'] : ($cur['sss_number'] ?? '');
        $p = isset($changes['philhealth_number']) ? $changes['philhealth_number']['new'] : ($cur['philhealth_number'] ?? '');
        $g = isset($changes['pagibig_number']) ? $changes['pagibig_number']['new'] : ($cur['pagibig_number'] ?? '');
        $t = isset($changes['tin_number']) ? $changes['tin_number']['new'] : ($cur['tin_number'] ?? '');
        $u = $conn->prepare("REPLACE INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number) VALUES (?,?,?,?,?)");
        $u->bind_param("issss", $eid, $s, $p, $g, $t); $u->execute(); $u->close();
    }

    // ── 4. employee_contacts ─────────────────────────────────────────────────
    $con_fields = ['telephone_number','mobile_number','personal_email'];
    if (array_intersect_key($changes, array_flip($con_fields))) {
        $cur = $conn->query("SELECT * FROM employee_contacts WHERE employee_id=$eid")->fetch_assoc() ?? [];
        $tel = isset($changes['telephone_number']) ? $changes['telephone_number']['new'] : ($cur['telephone_number'] ?? '');
        $mob = isset($changes['mobile_number']) ? $changes['mobile_number']['new'] : ($cur['mobile_number'] ?? '');
        $eml = isset($changes['personal_email']) ? $changes['personal_email']['new'] : ($cur['personal_email'] ?? null);
        $u = $conn->prepare("REPLACE INTO employee_contacts (employee_id,telephone_number,mobile_number,personal_email) VALUES (?,?,?,?)");
        $u->bind_param("isss", $eid, $tel, $mob, $eml); $u->execute(); $u->close();
    }

    // ── 5. employee_addresses (residential + permanent) ──────────────────────
    $addr_pfxs = ['res_','perm_'];
    $addr_types = ['res_' => 'Residential', 'perm_' => 'Permanent'];
    $addr_subs  = ['region','house_no','street','subdivision','barangay','city','province','zip_code'];
    foreach ($addr_pfxs as $pfx) {
        $changed_addr = false;
        foreach ($addr_subs as $sub) { if (isset($changes[$pfx.$sub])) { $changed_addr = true; break; } }
        if ($changed_addr) {
            $cur = $conn->query("SELECT * FROM employee_addresses WHERE employee_id=$eid AND address_type='" . $addr_types[$pfx] . "'")->fetch_assoc() ?? [];
            $vals = [];
            foreach ($addr_subs as $sub) {
                $vals[$sub] = isset($changes[$pfx.$sub]) ? $changes[$pfx.$sub]['new'] : ($cur[$sub] ?? '');
            }
            $conn->query("DELETE FROM employee_addresses WHERE employee_id=$eid AND address_type='" . $addr_types[$pfx] . "'");
            if (!empty($vals['street']) || !empty($vals['city']) || !empty($vals['province'])) {
                $u = $conn->prepare("INSERT INTO employee_addresses (employee_id,address_type,region,house_no,street,subdivision,barangay,city,province,zip_code) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $t = $addr_types[$pfx];
                $u->bind_param("isssssssss", $eid, $t, $vals['region'], $vals['house_no'], $vals['street'], $vals['subdivision'], $vals['barangay'], $vals['city'], $vals['province'], $vals['zip_code']);
                $u->execute(); $u->close();
            }
        }
    }

    // ── 6. emergency contact ─────────────────────────────────────────────────
    $emg_fields = ['emergency_contact_name','emergency_contact_relationship','emergency_contact_number'];
    if (array_intersect_key($changes, array_flip($emg_fields))) {
        $cur = $conn->query("SELECT * FROM employee_emergency_contacts WHERE employee_id=$eid AND is_primary=1 LIMIT 1")->fetch_assoc() ?? [];
        $en = isset($changes['emergency_contact_name']) ? $changes['emergency_contact_name']['new'] : ($cur['contact_name'] ?? '');
        $er = isset($changes['emergency_contact_relationship']) ? $changes['emergency_contact_relationship']['new'] : ($cur['relationship'] ?? '');
        $ec = isset($changes['emergency_contact_number']) ? $changes['emergency_contact_number']['new'] : ($cur['contact_number'] ?? '');
        if (!empty($cur['emergency_id'])) {
            $u = $conn->prepare("UPDATE employee_emergency_contacts SET contact_name=?, relationship=?, contact_number=? WHERE emergency_id=?");
            $u->bind_param("sssi", $en, $er, $ec, $cur['emergency_id']);
            $u->execute();
            $u->close();
        } else {
            $u = $conn->prepare("INSERT INTO employee_emergency_contacts (employee_id,contact_name,relationship,contact_number,is_primary) VALUES (?,?,?,?,1)");
            $u->bind_param("isss", $eid, $en, $er, $ec);
            $u->execute();
            $u->close();
        }
    }

    // Record into employee_edit_history
    $reviewer_id = (int)($req['reviewed_by'] ?? $_SESSION['user_id'] ?? 0);
    $summary = $req['change_summary'] ?: 'Applied approved employee edit request';
    
    $formatted_changes = [];
    foreach ($changes as $k => $c) {
        if (is_array($c) && array_key_exists('old', $c) && array_key_exists('new', $c)) {
            $formatted_changes[$k] = [
                'label' => ucwords(str_replace('_', ' ', $k)),
                'old' => (string)($c['old'] ?? ''),
                'new' => (string)($c['new'] ?? '')
            ];
        }
    }
    
    ensureEmployeeEditHistorySchema($conn);
    $editor_name = 'HR Manager (Approved Request)';
    $editor_role = 'HR Manager';
    if ($reviewer_id > 0) {
        $u_stmt = $conn->prepare("SELECT u.role, CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,'')) as full_name, u.username FROM users u LEFT JOIN employees e ON u.employee_id = e.employee_id WHERE u.user_id = ?");
        if ($u_stmt) {
            $u_stmt->bind_param("i", $reviewer_id);
            $u_stmt->execute();
            $u_row = $u_stmt->get_result()->fetch_assoc();
            $u_stmt->close();
            if ($u_row) {
                $editor_role = $u_row['role'] ?: 'HR Manager';
                $editor_name = trim($u_row['full_name']) ?: ($u_row['username'] ?: 'HR Manager');
            }
        }
    }
    
    $changes_json = !empty($formatted_changes) ? json_encode($formatted_changes, JSON_UNESCAPED_UNICODE) : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $step_num = 12;
    $step_title = 'Staff Edit Request (Approved)';
    $stmt_hist = $conn->prepare("INSERT INTO employee_edit_history (employee_id, edited_by, editor_name, editor_role, step_number, step_name, change_summary, changes_json, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt_hist) {
        $stmt_hist->bind_param("iississss", $eid, $reviewer_id, $editor_name, $editor_role, $step_num, $step_title, $summary, $changes_json, $ip);
        $stmt_hist->execute();
        $stmt_hist->close();
    }

    return true;
}

/**
 * Resolve the HRIS system role (users.role) that corresponds to a given job title string.
 * Maps HR job titles to HR Supervisor / HR Manager / HR Staff;
 * everything else maps to 'Employee' for branch-level non-HR positions.
 *
 * @param string $job_title
 * @return string  One of 'HR Manager', 'HR Supervisor', 'HR Staff', 'Employee'
 */
function resolveRoleFromJobTitle(string $job_title): string
{
    $t = strtolower(trim($job_title));
    if (str_contains($t, 'hr manager') || str_contains($t, 'human resources manager')) {
        return 'HR Manager';
    }
    if (str_contains($t, 'hr supervisor') || str_contains($t, 'human resources supervisor')) {
        return 'HR Supervisor';
    }
    if (str_contains($t, 'hr staff') || str_contains($t, 'human resources staff') || str_contains($t, 'hr ')) {
        // Catches 'HR Staff I', 'HR Staff on Probation', etc.
        if (str_starts_with($t, 'hr ') || str_starts_with($t, 'human resources ')) {
            return 'HR Staff';
        }
    }
    return 'Employee';
}

/**
 * Execute the full application of a career movement:
 * 1. Updates employees.job_title, job_title_id, department_id, rank_category_id, branch_id
 * 2. Updates users.role and users.branch_id (RBAC)
 * 3. Logs a ROLE_CHANGE audit log if role changed
 * 4. Sends in-app notification to employee
 * 5. Marks career_movements.is_applied = 1
 */
function executeCareerMovementApplication($conn, array $movement, int $movement_id): void
{
    $eid          = (int)$movement['employee_id'];
    $new_position = trim((string)($movement['new_position'] ?? ''));
    $new_bid      = !empty($movement['new_branch_id']) ? (int)$movement['new_branch_id'] : null;

    // ── 1. Update employee position/branch ───────────────────────────────────
    // Only update job_title if a new position was actually specified
    $has_new_position = $new_position !== '';

    if ($has_new_position) {
        // Lookup job_titles metadata (job_title_id, department_id, rank_category_id)
        $jt_stmt = $conn->prepare("SELECT job_title_id, department_id, rank_category_id FROM job_titles WHERE job_title = ? AND is_active = 1 LIMIT 1");
        $jt_stmt->bind_param("s", $new_position);
        $jt_stmt->execute();
        $jt_info = $jt_stmt->get_result()->fetch_assoc();
        $jt_stmt->close();

        if ($jt_info) {
            $j_id = (int)$jt_info['job_title_id'];
            $d_id = (int)$jt_info['department_id'];
            $r_id = (int)$jt_info['rank_category_id'];

            if ($new_bid) {
                $eu = $conn->prepare("UPDATE employees SET job_title=?, job_title_id=?, department_id=?, rank_category_id=?, branch_id=? WHERE employee_id=?");
                $eu->bind_param("siiiii", $new_position, $j_id, $d_id, $r_id, $new_bid, $eid);
            } else {
                $eu = $conn->prepare("UPDATE employees SET job_title=?, job_title_id=?, department_id=?, rank_category_id=? WHERE employee_id=?");
                $eu->bind_param("siiii", $new_position, $j_id, $d_id, $r_id, $eid);
            }
            $eu->execute(); $eu->close();
        } else {
            if ($new_bid) {
                $eu = $conn->prepare("UPDATE employees SET job_title=?, branch_id=? WHERE employee_id=?");
                $eu->bind_param("sii", $new_position, $new_bid, $eid);
            } else {
                $eu = $conn->prepare("UPDATE employees SET job_title=? WHERE employee_id=?");
                $eu->bind_param("si", $new_position, $eid);
            }
            $eu->execute(); $eu->close();
        }
    } elseif ($new_bid) {
        // No new position but branch is changing — only update branch_id
        $eu = $conn->prepare("UPDATE employees SET branch_id=? WHERE employee_id=?");
        $eu->bind_param("ii", $new_bid, $eid);
        $eu->execute(); $eu->close();
    }

    // ── 2. RBAC: Update users.role and users.branch_id ─────────────────────
    $new_role = resolveRoleFromJobTitle($new_position);

    $usr_stmt = $conn->prepare("
        SELECT user_id, role, branch_id
        FROM users
        WHERE employee_id = ? AND is_active = 1
        LIMIT 1
    ");
    $usr_stmt->bind_param("i", $eid);
    $usr_stmt->execute();
    $linked_user = $usr_stmt->get_result()->fetch_assoc();
    $usr_stmt->close();

    if ($linked_user) {
        $old_role      = $linked_user['role'];
        $linked_uid    = (int) $linked_user['user_id'];
        // Keep NULL as NULL — casting NULL to int gives 0 which violates the FK constraint on branch_id
        $new_user_bid  = $new_bid ?? ($linked_user['branch_id'] !== null ? (int) $linked_user['branch_id'] : null);

        if ($new_user_bid !== null) {
            $upd_user = $conn->prepare("UPDATE users SET role = ?, branch_id = ? WHERE user_id = ?");
            $upd_user->bind_param("sii", $new_role, $new_user_bid, $linked_uid);
        } else {
            $upd_user = $conn->prepare("UPDATE users SET role = ?, branch_id = NULL WHERE user_id = ?");
            $upd_user->bind_param("si", $new_role, $linked_uid);
        }
        $upd_user->execute();
        $upd_user->close();

        // ── 3. Audit log for role change ───────────────────────────────────
        if ($old_role !== $new_role) {
            $audit_detail = "Role changed from '{$old_role}' to '{$new_role}' via Career Movement (ID: {$movement_id}) — {$movement['movement_type']} to '{$new_position}'";
            $al = $conn->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, details) VALUES (?, 'ROLE_CHANGE', 'User', ?, ?)");
            $al->bind_param("iis", $linked_uid, $linked_uid, $audit_detail);
            $al->execute(); $al->close();

            // ── 4. In-app notification to the employee ─────────────────────
            $notif_title = 'Your Position & System Access Has Been Updated';
            $notif_msg   = "Congratulations! Your career movement ({$movement['movement_type']}) to '{$new_position}' has taken effect. Your system role has been updated to '{$new_role}'. Please re-login to apply your new access permissions.";
            $notif_link  = BASE_URL . '/employee/my-employment.php';
            createNotification($conn, $linked_uid, $notif_title, $notif_msg, $notif_link);
        }
    }

    // ── 5. Mark movement as applied ────────────────────────────────────────
    $mark = $conn->prepare("UPDATE career_movements SET is_applied = 1 WHERE movement_id = ?");
    $mark->bind_param("i", $movement_id);
    $mark->execute();
    $mark->close();
}

/**
 * Apply all due (Approved, is_applied=0, effective_date<=today) career movements.
 */
function applyDueCareerProgressionMovements($conn)
{
    if (!ensureCareerProgressionMovements($conn)) {
        return;
    }

    $today = date('Y-m-d');

    $stmt = $conn->prepare("
        SELECT cm.movement_id, cm.employee_id, cm.new_position, cm.new_branch_id,
               cm.movement_type,
               CONCAT(e.first_name,' ',e.last_name) AS employee_name
        FROM career_movements cm
        JOIN employees e ON cm.employee_id = e.employee_id
        WHERE cm.approval_status = 'Approved' AND cm.is_applied = 0 AND cm.effective_date <= ?
    ");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($movement = $result->fetch_assoc()) {
        $movement_id = (int) $movement['movement_id'];
        executeCareerMovementApplication($conn, $movement, $movement_id);
    }

    $stmt->close();
}



/**

 * Display flash message if exists

 */

function renderFlashPopup($type, $message)
{

    $flash_icons = [

        'success' => 'fas fa-check-circle',

        'danger' => 'fas fa-exclamation-circle',

        'warning' => 'fas fa-exclamation-triangle',

        'info' => 'fas fa-info-circle',

        'primary' => 'fas fa-bell',

    ];

    $icon = $flash_icons[$type] ?? 'fas fa-bell';

    $flash_titles = [

        'success' => 'Success!',

        'danger' => 'Failed!',

        'warning' => 'Warning!',

        'info' => 'Notice!',

        'primary' => 'Notice!',

    ];

    $title = $flash_titles[$type] ?? 'Notice!';

    $allowed_types = ['success', 'danger', 'warning', 'info', 'primary'];
    $type_class = in_array($type, $allowed_types, true) ? $type : 'info';

    echo '<div class="flash-message-banner flash-message-' . e($type_class) . ' alert alert-dismissible fade show" role="alert">';
    echo '<div class="flash-message-icon"><i class="' . e($icon) . '"></i></div>';
    echo '<div class="flash-message-copy">';
    echo '<span class="flash-message-app">Raquel HRIS</span>';
    echo '<strong class="flash-message-title">' . e($title) . '</strong>';
    echo '<span class="flash-message-text">' . e($message) . '</span>';
    echo '</div>';
    echo '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';

}



function displayFlashMessage()
{

    if (isset($_SESSION['flash_message'])) {

        $type = $_SESSION['flash_type'] ?? 'info';

        $message = $_SESSION['flash_message'];

        unset($_SESSION['flash_type'], $_SESSION['flash_message']);

        renderFlashPopup($type, $message);

    }

}



/**

 * Get a single system setting by key

 */

function getSetting($conn, $key, $default = null)
{

    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");

    $stmt->bind_param("s", $key);

    $stmt->execute();

    $res = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return $res ? $res['setting_value'] : $default;

}



/**

 * Update a system setting

 */

function updateSetting($conn, $key, $value)
{

    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");

    $stmt->bind_param("sss", $key, $value, $value);

    $success = $stmt->execute();

    $stmt->close();

    return $success;

}

/**

 * Check if the login attempt should be blocked due to brute force

 */

function checkLoginBruteForce($conn, $identifier, $ip)
{
    $lockout_time = 30; // seconds
    $max_attempts = 5;
    $max_ip_attempts = 10;

    $remaining_identifier = 0;
    $remaining_ip = 0;

    // Check by Identifier (Username/Email)
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as attempt_count,
            TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(MAX(attempt_time), INTERVAL ? SECOND)) as remaining
        FROM login_attempts
        WHERE identifier = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmt->bind_param("isi", $lockout_time, $identifier, $lockout_time);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result && (int)$result['attempt_count'] >= $max_attempts) {
        $remaining_identifier = max(0, (int)$result['remaining']);
    }
    $stmt->close();

    // Check by IP
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as attempt_count,
            TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(MAX(attempt_time), INTERVAL ? SECOND)) as remaining
        FROM login_attempts
        WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmt->bind_param("isi", $lockout_time, $ip, $lockout_time);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result && (int)$result['attempt_count'] >= $max_ip_attempts) {
        $remaining_ip = max(0, (int)$result['remaining']);
    }
    $stmt->close();

    return max($remaining_identifier, $remaining_ip);
}



/**

 * Register a failed login attempt

 */

function registerLoginAttempt($conn, $identifier, $ip)
{

    $stmt = $conn->prepare("INSERT INTO login_attempts (identifier, ip_address) VALUES (?, ?)");

    $stmt->bind_param("ss", $identifier, $ip);

    $stmt->execute();

    $stmt->close();

}



/**

 * Clear login attempts for a successful login

 */

function clearLoginAttempts($conn, $identifier, $ip)
{

    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE identifier = ? OR ip_address = ?");

    $stmt->bind_param("ss", $identifier, $ip);

    $stmt->execute();

    $stmt->close();

}



/**

 * Ensure employees table has reports_to column for supervisor-subordinate relationships

 */

function ensureEmployeesReportsTo($conn)
{
    static $ensured = false;
    if ($ensured) {
        return true;
    }

    try {
        $result = $conn->query("SHOW COLUMNS FROM employees LIKE 'reports_to'");
        if ($result) {
            if ($result->num_rows === 0) {
                $conn->query("ALTER TABLE employees ADD COLUMN reports_to INT NULL AFTER branch_id, ADD CONSTRAINT fk_employees_reports_to FOREIGN KEY (reports_to) REFERENCES employees(employee_id) ON DELETE SET NULL");
            }
            $result->free();
        }
    } catch (mysqli_sql_exception $e) {
        return false;
    }
    $ensured = true;
    return true;
}



/**

 * Get all subordinates (employees who report to the given employee_id)

 */

function getEmployeeSubordinates($conn, $supervisor_employee_id)
{

    ensureEmployeesReportsTo($conn);

    $supervisor_employee_id = (int) $supervisor_employee_id;

    $subordinates = [];



    $stmt = $conn->prepare("

        SELECT employee_id, employee_code, first_name, last_name, job_title, branch_id

        FROM employees

        WHERE reports_to = ? AND is_active = 1

        ORDER BY last_name, first_name

    ");

    $stmt->bind_param("i", $supervisor_employee_id);

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $subordinates[] = $row;

    }

    $stmt->close();



    return $subordinates;

}



/**

 * Check if an employee has subordinates (is a supervisor)

 */

function hasEmployeeSubordinates($conn, $employee_id)
{

    ensureEmployeesReportsTo($conn);

    $employee_id = (int) $employee_id;



    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM employees WHERE reports_to = ? AND is_active = 1");

    $stmt->bind_param("i", $employee_id);

    $stmt->execute();

    $count = (int) ($stmt->get_result()->fetch_assoc()['count'] ?? 0);

    $stmt->close();



    return $count > 0;

}



/**
 * Check if a branch has no active Manager or Supervisor.
 * Used to determine whether HR Staff must initiate career movement
 * requests on behalf of that branch (leaderless branch scenario).
 *
 * @param  mysqli $conn
 * @param  int    $branch_id
 * @return bool   true = leaderless (no Manager/Supervisor found)
 */
function isBranchLeaderless($conn, $branch_id)
{
    $branch_id = (int) $branch_id;
    if ($branch_id <= 0) {
        return true; // no branch assigned → treat as leaderless
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM employees
        WHERE branch_id = ?
          AND is_active  = 1
          AND deleted_at IS NULL
          AND (
                job_title LIKE '%Manager%'
             OR job_title LIKE '%Supervisor%'
          )
    ");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $cnt = (int) ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt->close();

    return $cnt === 0; // true = leaderless
}



/**
 * Check if an employee has supervisor privileges.
 * Returns true if the employee:
 *   (1) has at least one active subordinate (reports_to rows), OR
 *   (2) holds a supervisor/manager job title or rank category (rank_category_id 3 or 4).
 * This allows Branch Supervisors and Branch Managers to access supervisor features
 * even when reports_to relationships have not been fully set up in the database.
 */
function hasSupervisorPrivileges($conn, $employee_id)
{
    $employee_id = (int) $employee_id;
    if (!$employee_id) {
        return false;
    }

    // Check 1: Has at least one direct subordinate
    if (hasEmployeeSubordinates($conn, $employee_id)) {
        return true;
    }

    // Check 2: Holds a supervisor or manager job title / rank category
    $stmt = $conn->prepare("
        SELECT employee_id
        FROM employees
        WHERE employee_id = ?
          AND is_active = 1
          AND (
              rank_category_id IN (3, 4)
              OR job_title LIKE '%Supervisor%'
              OR job_title LIKE '%Manager%'
          )
        LIMIT 1
    ");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $res !== null;
}



/**

 * Get employee name by ID

 */

function getEmployeeNameById($conn, $employee_id)
{

    $employee_id = (int) $employee_id;

    $stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM employees WHERE employee_id = ? LIMIT 1");

    $stmt->bind_param("i", $employee_id);

    $stmt->execute();

    $result = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return $result ? $result['full_name'] : 'Unknown';

}



/**

 * Get columns for evaluations table

 */

function getEvaluationsTableColumns($conn)
{
    $columns = [];
    try {
        $result = $conn->query("SHOW COLUMNS FROM evaluations");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columns[$row['Field']] = true;
            }
            $result->free();
        }
    } catch (mysqli_sql_exception $e) {
        return [];
    }
    return $columns;
}



function getEvaluationCriteriaTableColumns($conn)
{
    $columns = [];
    try {
        $result = $conn->query("SHOW COLUMNS FROM evaluation_criteria");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columns[$row['Field']] = true;
            }
            $result->free();
        }
    } catch (mysqli_sql_exception $e) {
        return [];
    }
    return $columns;
}



function evaluationsStatusSupports($conn, $required_statuses)
{
    try {
        $result = $conn->query("SHOW COLUMNS FROM evaluations LIKE 'status'");
        $status_column = $result ? $result->fetch_assoc() : null;
        if ($result) {
            $result->free();
        }
        $column_type = (string) ($status_column['Type'] ?? '');

        if ($column_type === '') {
            return false;
        }

        foreach ($required_statuses as $status) {
            if (strpos($column_type, "'" . $status . "'") === false) {
                return false;
            }
        }

        return true;
    } catch (mysqli_sql_exception $e) {
        return false;
    }
}



function ensureEvaluationWorkflowSchema($conn)
{
    static $ensured = false;
    if ($ensured) {
        return true;
    }

    try {
        $columns = getEvaluationsTableColumns($conn);

        $optional_columns = [

            'assigned_by' => "ALTER TABLE evaluations ADD COLUMN assigned_by INT NULL AFTER submitted_by",

            'assigned_at' => "ALTER TABLE evaluations ADD COLUMN assigned_at DATETIME NULL AFTER assigned_by",

            'supervisor_confirmed_by' => "ALTER TABLE evaluations ADD COLUMN supervisor_confirmed_by INT NULL AFTER approved_by",

            'supervisor_confirmed_date' => "ALTER TABLE evaluations ADD COLUMN supervisor_confirmed_date DATETIME NULL AFTER supervisor_confirmed_by",

            'supervisor_altered_scores' => "ALTER TABLE evaluations ADD COLUMN supervisor_altered_scores TINYINT(1) DEFAULT 0 AFTER supervisor_confirmed_date",

            'sent_to_hr_date' => "ALTER TABLE evaluations ADD COLUMN sent_to_hr_date DATETIME NULL AFTER supervisor_altered_scores",

            'sent_to_hr_by' => "ALTER TABLE evaluations ADD COLUMN sent_to_hr_by INT NULL AFTER sent_to_hr_date",

            'supervisor_rating' => "ALTER TABLE evaluations ADD COLUMN supervisor_rating DECIMAL(5,2) NULL AFTER supervisor_comments",

            'manager_rating' => "ALTER TABLE evaluations ADD COLUMN manager_rating DECIMAL(5,2) NULL AFTER manager_comments",

            'dept_supervisor_confirmed_by' => "ALTER TABLE evaluations ADD COLUMN dept_supervisor_confirmed_by INT NULL AFTER supervisor_confirmed_date",

            'dept_supervisor_confirmed_date' => "ALTER TABLE evaluations ADD COLUMN dept_supervisor_confirmed_date DATETIME NULL AFTER dept_supervisor_confirmed_by",

            'dept_manager_endorsed_by' => "ALTER TABLE evaluations ADD COLUMN dept_manager_endorsed_by INT NULL AFTER dept_supervisor_confirmed_date",

            'dept_manager_endorsed_date' => "ALTER TABLE evaluations ADD COLUMN dept_manager_endorsed_date DATETIME NULL AFTER dept_manager_endorsed_by",

            'dept_manager_comments' => "ALTER TABLE evaluations ADD COLUMN dept_manager_comments TEXT NULL AFTER supervisor_comments",

            'supervisor_lock_user_id' => "ALTER TABLE evaluations ADD COLUMN supervisor_lock_user_id INT NULL AFTER dept_manager_endorsed_date",

            'supervisor_lock_expires' => "ALTER TABLE evaluations ADD COLUMN supervisor_lock_expires DATETIME NULL AFTER supervisor_lock_user_id",

            'manager_lock_user_id' => "ALTER TABLE evaluations ADD COLUMN manager_lock_user_id INT NULL AFTER supervisor_lock_expires",

            'manager_lock_expires' => "ALTER TABLE evaluations ADD COLUMN manager_lock_expires DATETIME NULL AFTER manager_lock_user_id",

            'employee_consent_agreed' => "ALTER TABLE evaluations ADD COLUMN employee_consent_agreed TINYINT(1) DEFAULT 0 AFTER staff_comments",

            'employee_signature_data' => "ALTER TABLE evaluations ADD COLUMN employee_signature_data LONGTEXT NULL AFTER employee_consent_agreed",

            'employee_signed_at' => "ALTER TABLE evaluations ADD COLUMN employee_signed_at DATETIME NULL AFTER employee_signature_data",

        ];



        foreach ($optional_columns as $column => $sql) {

            if (!isset($columns[$column])) {

                $conn->query($sql);

            }

        }



        $required_statuses = [
            'Draft',
            'Pending Self-Rating',
            'Pending Dept Supervisor',
            'Pending Dept Manager',
            'Pending Supervisor',
            'Pending HR Consolidation',
            'Pending Team Consolidation',
            'Pending Manager',
            'Supervisor Confirmed',
            'Approved',
            'Rejected',
            'Returned'
        ];

        if (!evaluationsStatusSupports($conn, $required_statuses)) {
            $conn->query("
                ALTER TABLE evaluations
                MODIFY COLUMN status ENUM(
                    'Draft',
                    'Pending Self-Rating',
                    'Pending Dept Supervisor',
                    'Pending Dept Manager',
                    'Pending Supervisor',
                    'Pending HR Consolidation',
                    'Pending Team Consolidation',
                    'Pending Manager',
                    'Supervisor Confirmed',
                    'Approved',
                    'Rejected',
                    'Returned'
                ) DEFAULT 'Draft'
            ");
        }

        $criteria_columns = getEvaluationCriteriaTableColumns($conn);

        if (!isset($criteria_columns['is_custom'])) {
            $conn->query("ALTER TABLE evaluation_criteria ADD COLUMN is_custom TINYINT(1) DEFAULT 0 AFTER sort_order");
        }

        $score_columns = [];
        $score_cols_result = $conn->query('SHOW COLUMNS FROM evaluation_scores');
        if ($score_cols_result) {
            while ($score_col = $score_cols_result->fetch_assoc()) {
                $score_columns[$score_col['Field']] = true;
            }
            $score_cols_result->free();
        }

        $score_optional_columns = [
            'supervisor_override_score' => "ALTER TABLE evaluation_scores ADD COLUMN supervisor_override_score DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'Score overridden by HR Supervisor' AFTER weighted_score",
            'supervisor_override_by' => "ALTER TABLE evaluation_scores ADD COLUMN supervisor_override_by INT NULL DEFAULT NULL COMMENT 'User ID of HR Supervisor who overrode' AFTER supervisor_override_score",
            'supervisor_override_at' => "ALTER TABLE evaluation_scores ADD COLUMN supervisor_override_at DATETIME NULL DEFAULT NULL COMMENT 'When override was made' AFTER supervisor_override_by",
            'manager_override_score' => "ALTER TABLE evaluation_scores ADD COLUMN manager_override_score DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'Score overridden by HR Manager' AFTER supervisor_override_at",
            'manager_override_by' => "ALTER TABLE evaluation_scores ADD COLUMN manager_override_by INT NULL DEFAULT NULL COMMENT 'User ID of HR Manager who overrode' AFTER manager_override_score",
            'manager_override_at' => "ALTER TABLE evaluation_scores ADD COLUMN manager_override_at DATETIME NULL DEFAULT NULL COMMENT 'When manager override was made' AFTER manager_override_by",
            // Dept Manager override columns (recorded before HR Supervisor/Manager steps)
            'dept_manager_override_score' => "ALTER TABLE evaluation_scores ADD COLUMN dept_manager_override_score DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'Score adjusted by Department Manager' AFTER manager_override_at",
            'dept_manager_override_by' => "ALTER TABLE evaluation_scores ADD COLUMN dept_manager_override_by INT NULL DEFAULT NULL COMMENT 'User ID of Department Manager who adjusted' AFTER dept_manager_override_score",
            'dept_manager_override_at' => "ALTER TABLE evaluation_scores ADD COLUMN dept_manager_override_at DATETIME NULL DEFAULT NULL COMMENT 'When Department Manager adjustment was made' AFTER dept_manager_override_by",
        ];

        foreach ($score_optional_columns as $column => $sql) {
            if (!isset($score_columns[$column])) {
                $conn->query($sql);
            }
        }

        $conn->query("
            UPDATE notifications
            SET link = REPLACE(link, '/staff/my-submissions.php', '/staff/evaluation-history.php')
            WHERE link LIKE '%/staff/my-submissions.php%'
        ");

        return true;

    } catch (mysqli_sql_exception $e) {

        return false;

    }

}



/**

 * Ensure evaluations table has 360-degree workflow columns

 */

function ensure360DegreeEvaluationColumns($conn)
{

    return ensureEvaluationWorkflowSchema($conn);

}

/**
 * Schema and helpers for the organization-driven, team-consolidated workflow.
 * This is deliberately separate from the legacy individual workflow so existing
 * evaluations remain readable while departments are migrated progressively.
 */
function ensureOrganizationEvaluationPackageSchema($conn)
{
    static $ensured = false;
    if ($ensured) return true;

    ensureEvaluationWorkflowSchema($conn);

    try {
        $conn->query("CREATE TABLE IF NOT EXISTS evaluation_governance_approvers (
            governance_approver_id INT AUTO_INCREMENT PRIMARY KEY,
            governance_type ENUM('Board of Directors','Audit Committee','President','Division VP') NOT NULL,
            department_id INT NULL,
            user_id INT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_governance_user (governance_type, department_id, user_id),
            CONSTRAINT fk_governance_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            CONSTRAINT fk_governance_department FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Migrate existing installs: add department_id column and expand governance_type enum.
        try {
            $conn->query("ALTER TABLE evaluation_governance_approvers
                ADD COLUMN IF NOT EXISTS department_id INT NULL AFTER governance_type,
                MODIFY governance_type ENUM('Board of Directors','Audit Committee','President','Division VP') NOT NULL");
        } catch (mysqli_sql_exception $e) { /* Already migrated or unsupported — safe to ignore. */ }
        try {
            $conn->query("ALTER TABLE evaluation_governance_approvers DROP KEY uq_governance_user");
            $conn->query("ALTER TABLE evaluation_governance_approvers ADD UNIQUE KEY uq_governance_user (governance_type, department_id, user_id)");
        } catch (mysqli_sql_exception $e) { /* Key already updated. */ }
        $conn->query("CREATE TABLE IF NOT EXISTS evaluation_packages (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Existing installs may predate Pending Audit Approval.
        try {
            $conn->query("ALTER TABLE evaluation_packages MODIFY status ENUM('Pending Self-Ratings','Pending Consolidation','Pending Review','Pending Audit Approval','Pending Board Approval','Approved and Applied','Returned','Cancelled') NOT NULL DEFAULT 'Pending Self-Ratings'");
        } catch (mysqli_sql_exception $e) {
            // Ignore when the column is already aligned or the table is unavailable.
        }
        $conn->query("CREATE TABLE IF NOT EXISTS evaluation_package_members (
            package_id INT NOT NULL,
            evaluation_id INT NOT NULL,
            PRIMARY KEY (package_id, evaluation_id),
            CONSTRAINT fk_package_member_package FOREIGN KEY (package_id) REFERENCES evaluation_packages(package_id) ON DELETE CASCADE,
            CONSTRAINT fk_package_member_evaluation FOREIGN KEY (evaluation_id) REFERENCES evaluations(evaluation_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $conn->query("CREATE TABLE IF NOT EXISTS evaluation_package_route_steps (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $conn->query("CREATE TABLE IF NOT EXISTS evaluation_package_audit (
            package_audit_id INT AUTO_INCREMENT PRIMARY KEY,
            package_id INT NOT NULL,
            user_id INT NULL,
            action VARCHAR(80) NOT NULL,
            remarks TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_package_audit (package_id, created_at),
            CONSTRAINT fk_package_audit_package FOREIGN KEY (package_id) REFERENCES evaluation_packages(package_id) ON DELETE CASCADE,
            CONSTRAINT fk_package_audit_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $ensured = true;
        return true;
    } catch (mysqli_sql_exception $e) {
        return false;
    }
}

function positionReportsToWouldCreateCycle($conn, $position_id, $parent_position_id)
{
    $position_id = (int) $position_id;
    $parent_position_id = (int) $parent_position_id;
    if ($position_id <= 0 || $parent_position_id <= 0) return false;
    if ($position_id === $parent_position_id) return true;
    $seen = [];
    while ($parent_position_id > 0 && !isset($seen[$parent_position_id])) {
        if ($parent_position_id === $position_id) return true;
        $seen[$parent_position_id] = true;
        $stmt = $conn->prepare('SELECT reports_to FROM job_titles WHERE job_title_id = ? LIMIT 1');
        $stmt->bind_param('i', $parent_position_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $parent_position_id = !empty($row['reports_to']) ? (int) $row['reports_to'] : 0;
    }
    return false;
}

function getOrganizationPackageConsolidator($conn, $department_id)
{
    $stmt = $conn->prepare("SELECT e.employee_id, u.user_id
        FROM employees e JOIN users u ON u.employee_id = e.employee_id AND u.is_active = 1
        WHERE e.department_id = ? AND e.is_active = 1 AND e.deleted_at IS NULL
        ORDER BY CASE WHEN e.rank_category_id = 4 THEN 0 WHEN e.rank_category_id = 3 THEN 1 ELSE 2 END, e.employee_id
        LIMIT 1");
    $stmt->bind_param('i', $department_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Append one governance step to a package route.
 * Supports 'Board of Directors', 'Audit Committee', 'President', and 'Division VP' (department-specific).
 * Returns the next step_order after a successful insert, or the same order if no approver is found.
 */
function appendOrganizationGovernanceRouteStep($conn, $package_id, $order, $governance_type, $step_label, $department_id = null)
{
    $package_id = (int) $package_id;
    $order = (int) $order;
    $department_id = $department_id ? (int) $department_id : null;

    // For Division VP: match by governance_type + department_id.
    // For all others (Board, Audit, President): department_id is NULL (company-wide).
    if ($department_id !== null) {
        $stmt = $conn->prepare("SELECT ega.user_id, u.employee_id
            FROM evaluation_governance_approvers ega
            JOIN users u ON u.user_id = ega.user_id AND u.is_active = 1
            WHERE ega.governance_type = ? AND ega.department_id = ? AND ega.is_active = 1
            ORDER BY ega.governance_approver_id LIMIT 1");
        $stmt->bind_param('si', $governance_type, $department_id);
    } else {
        $stmt = $conn->prepare("SELECT ega.user_id, u.employee_id
            FROM evaluation_governance_approvers ega
            JOIN users u ON u.user_id = ega.user_id AND u.is_active = 1
            WHERE ega.governance_type = ? AND ega.department_id IS NULL AND ega.is_active = 1
            ORDER BY ega.governance_approver_id LIMIT 1");
        $stmt->bind_param('s', $governance_type);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return $order;
    }

    $route = $conn->prepare("INSERT INTO evaluation_package_route_steps
        (package_id, step_order, reviewer_employee_id, reviewer_user_id, step_label, step_type, action_status)
        VALUES (?, ?, ?, ?, ?, 'Governance', 'Waiting')");
    $reviewer_employee_id = !empty($row['employee_id']) ? (int) $row['employee_id'] : null;
    $reviewer_user_id = (int) $row['user_id'];
    $route->bind_param('iiiis', $package_id, $order, $reviewer_employee_id, $reviewer_user_id, $step_label);
    $route->execute();
    $route->close();
    return $order + 1;
}

/**
 * Fetch the designated official for a given governance role and department.
 * Returns ['user_id' => int, 'employee_id' => int|null, 'job_title' => string, 'full_name' => string] or null.
 */
function getDepartmentDesignatedOfficial($conn, $governance_type, $department_id = null)
{
    $department_id = $department_id ? (int) $department_id : null;
    if ($department_id !== null) {
        $stmt = $conn->prepare("SELECT ega.user_id, u.employee_id, u.full_name, COALESCE(e.job_title, u.role) AS job_title
            FROM evaluation_governance_approvers ega
            JOIN users u ON u.user_id = ega.user_id AND u.is_active = 1
            LEFT JOIN employees e ON e.employee_id = u.employee_id AND e.is_active = 1
            WHERE ega.governance_type = ? AND ega.department_id = ? AND ega.is_active = 1
            ORDER BY ega.governance_approver_id LIMIT 1");
        $stmt->bind_param('si', $governance_type, $department_id);
    } else {
        $stmt = $conn->prepare("SELECT ega.user_id, u.employee_id, u.full_name, COALESCE(e.job_title, u.role) AS job_title
            FROM evaluation_governance_approvers ega
            JOIN users u ON u.user_id = ega.user_id AND u.is_active = 1
            LEFT JOIN employees e ON e.employee_id = u.employee_id AND e.is_active = 1
            WHERE ega.governance_type = ? AND ega.department_id IS NULL AND ega.is_active = 1
            ORDER BY ega.governance_approver_id LIMIT 1");
        $stmt->bind_param('s', $governance_type);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Map a route step to the package status shown while that step is active.
 */
function getOrganizationPackageStatusForStep(array $step)
{
    $type = $step['step_type'] ?? '';
    $label = $step['step_label'] ?? '';
    if ($type === 'Consolidation') {
        return 'Pending Consolidation';
    }
    if ($type === 'Governance') {
        if (stripos($label, 'Audit') !== false) {
            return 'Pending Audit Approval';
        }
        // Division VP and President steps are shown as Pending Review
        if (stripos($label, 'Board') !== false) {
            return 'Pending Board Approval';
        }
        return 'Pending Review';
    }
    return 'Pending Review';
}

function isOrganizationPackageLocked($conn, $package_id)
{
    $package_id = (int) $package_id;
    if ($package_id <= 0) {
        return false;
    }
    $stmt = $conn->prepare("SELECT status FROM evaluation_packages WHERE package_id = ? LIMIT 1");
    $stmt->bind_param('i', $package_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ($row['status'] ?? '') === 'Approved and Applied';
}

/** SQL fragment: current user owns a route step via user_id or shared employee_id (HRIS + portal dual login). */
function organizationPackageReviewerMatchSql($alias = 'rs')
{
    return "({$alias}.reviewer_user_id = ? OR ({$alias}.reviewer_employee_id IS NOT NULL AND {$alias}.reviewer_employee_id = (SELECT employee_id FROM users WHERE user_id = ? LIMIT 1)))";
}

function getOrganizationPackageReviewerDisplayName($conn, $user_id = 0, $employee_id = 0)
{
    $user_id = (int) $user_id;
    $employee_id = (int) $employee_id;
    if ($employee_id > 0) {
        $stmt = $conn->prepare("SELECT TRIM(CONCAT_WS(' ', first_name, last_name)) AS full_name, job_title FROM employees WHERE employee_id = ? LIMIT 1");
        $stmt->bind_param('i', $employee_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && trim((string) $row['full_name']) !== '') {
            $title = trim((string) ($row['job_title'] ?? ''));
            return $title !== '' ? $row['full_name'] . ' (' . $title . ')' : $row['full_name'];
        }
    }
    if ($user_id > 0) {
        $stmt = $conn->prepare('SELECT full_name FROM users WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!empty($row['full_name'])) {
            return $row['full_name'];
        }
    }
    return 'the next reviewer';
}

function notifyUsersForEmployee($conn, $employee_id, $title, $message, $link)
{
    $employee_id = (int) $employee_id;
    if ($employee_id <= 0) {
        return 0;
    }
    $stmt = $conn->prepare('SELECT user_id FROM users WHERE employee_id = ? AND is_active = 1');
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($users as $user) {
        createNotification($conn, (int) $user['user_id'], $title, $message, $link);
    }
    return count($users);
}

function notifyOrganizationPackageStepAssignees($conn, $package_id, $step_order, $title, $message)
{
    $package_id = (int) $package_id;
    $step_order = (int) $step_order;
    $stmt = $conn->prepare('SELECT reviewer_user_id, reviewer_employee_id FROM evaluation_package_route_steps WHERE package_id = ? AND step_order = ? LIMIT 1');
    $stmt->bind_param('ii', $package_id, $step_order);
    $stmt->execute();
    $step = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$step) {
        return 0;
    }
    $link = BASE_URL . '/employee/team-evaluation-packages.php';
    $sent = 0;
    if (!empty($step['reviewer_employee_id'])) {
        $sent = notifyUsersForEmployee($conn, (int) $step['reviewer_employee_id'], $title, $message, $link);
    }
    if ($sent === 0 && !empty($step['reviewer_user_id'])) {
        createNotification($conn, (int) $step['reviewer_user_id'], $title, $message, $link);
        $sent = 1;
    }
    return $sent;
}

function getOrganizationPackagePipelineLabel($conn, $package_id)
{
    $package_id = (int) $package_id;
    $stmt = $conn->prepare("SELECT ep.status, ep.current_step_order, rs.step_label, rs.step_type, rs.action_status, rs.reviewer_user_id, rs.reviewer_employee_id
        FROM evaluation_packages ep
        LEFT JOIN evaluation_package_route_steps rs ON rs.package_id = ep.package_id
            AND rs.step_order = ep.current_step_order
        WHERE ep.package_id = ?
        LIMIT 1");
    $stmt->bind_param('i', $package_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return 'Unknown';
    }
    if (($row['status'] ?? '') === 'Approved and Applied') {
        return 'Approved & Locked';
    }
    if (($row['status'] ?? '') === 'Pending Self-Ratings' || empty($row['current_step_order'])) {
        $s1_stmt = $conn->prepare("SELECT reviewer_user_id, reviewer_employee_id, step_label FROM evaluation_package_route_steps WHERE package_id = ? AND step_order = 1 LIMIT 1");
        $s1_stmt->bind_param('i', $package_id);
        $s1_stmt->execute();
        $s1 = $s1_stmt->get_result()->fetch_assoc();
        $s1_stmt->close();
        $s1_name = $s1 ? getOrganizationPackageReviewerDisplayName($conn, (int)($s1['reviewer_user_id'] ?? 0), (int)($s1['reviewer_employee_id'] ?? 0)) : '';
        return 'Waiting for team self-ratings' . ($s1_name ? ' (Consolidator: ' . $s1_name . ')' : '');
    }
    if (($row['status'] ?? '') === 'Returned') {
        $name = getOrganizationPackageReviewerDisplayName($conn, (int) ($row['reviewer_user_id'] ?? 0), (int) ($row['reviewer_employee_id'] ?? 0));
        return 'Returned for revision — ' . $name;
    }
    $name = getOrganizationPackageReviewerDisplayName($conn, (int) ($row['reviewer_user_id'] ?? 0), (int) ($row['reviewer_employee_id'] ?? 0));
    $label = trim((string) ($row['step_label'] ?? 'Review'));
    return 'Pending ' . $label . ' — ' . $name;
}

function renderOrganizationPipelineBadge($conn, $package_id, $extra_classes = '')
{
    $package_id = (int) $package_id;
    if ($package_id <= 0) return '';
    $stmt = $conn->prepare("SELECT ep.status, ep.current_step_order, rs.step_label, rs.step_type, rs.action_status, rs.reviewer_user_id, rs.reviewer_employee_id
        FROM evaluation_packages ep
        LEFT JOIN evaluation_package_route_steps rs ON rs.package_id = ep.package_id
            AND rs.step_order = ep.current_step_order
        WHERE ep.package_id = ?
        LIMIT 1");
    $stmt->bind_param('i', $package_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return '';
    
    $status = $row['status'] ?? '';
    if ($status === 'Approved and Applied') {
        return '<span class="pipeline-badge pipeline-badge--approved ' . e($extra_classes) . '"><i class="fas fa-lock me-1"></i>Approved &amp; Locked</span>';
    }
    if ($status === 'Pending Self-Ratings' || empty($row['current_step_order'])) {
        $s1_stmt = $conn->prepare("SELECT reviewer_user_id, reviewer_employee_id, step_label FROM evaluation_package_route_steps WHERE package_id = ? AND step_order = 1 LIMIT 1");
        $s1_stmt->bind_param('i', $package_id);
        $s1_stmt->execute();
        $s1 = $s1_stmt->get_result()->fetch_assoc();
        $s1_stmt->close();
        $s1_name = $s1 ? getOrganizationPackageReviewerDisplayName($conn, (int)($s1['reviewer_user_id'] ?? 0), (int)($s1['reviewer_employee_id'] ?? 0)) : '';
        return '<span class="pipeline-badge pipeline-badge--waiting ' . e($extra_classes) . '"><i class="fas fa-user-clock me-1"></i>Waiting for team self-ratings' . ($s1_name ? ' <span class="pipeline-reviewer">(' . e($s1_name) . ')</span>' : '') . '</span>';
    }
    if ($status === 'Returned') {
        $name = getOrganizationPackageReviewerDisplayName($conn, (int) ($row['reviewer_user_id'] ?? 0), (int) ($row['reviewer_employee_id'] ?? 0));
        return '<span class="pipeline-badge pipeline-badge--returned ' . e($extra_classes) . '"><i class="fas fa-undo me-1"></i>Returned for Revision &mdash; ' . e($name) . '</span>';
    }
    $name = getOrganizationPackageReviewerDisplayName($conn, (int) ($row['reviewer_user_id'] ?? 0), (int) ($row['reviewer_employee_id'] ?? 0));
    $label = trim((string) ($row['step_label'] ?? 'Review'));
    return '<span class="pipeline-badge pipeline-badge--pending ' . e($extra_classes) . '"><i class="fas fa-hourglass-half me-1"></i>Pending ' . e($label) . ' &mdash; <strong>' . e($name) . '</strong></span>';
}

function renderOrganizationPackageAuditTrail($conn, $package_id)
{
    $package_id = (int) $package_id;
    if ($package_id <= 0) return '';

    $audit_stmt = $conn->prepare("SELECT pa.*,
            u.full_name, u.role, u.username,
            emp.first_name, emp.last_name, emp.job_title
        FROM evaluation_package_audit pa
        LEFT JOIN users u ON u.user_id = pa.user_id
        LEFT JOIN employees emp ON emp.employee_id = u.employee_id
        WHERE pa.package_id = ?
        ORDER BY pa.created_at ASC, pa.package_audit_id ASC");
    $audit_stmt->bind_param('i', $package_id);
    $audit_stmt->execute();
    $audits = $audit_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $audit_stmt->close();

    if (empty($audits)) {
        return '<div class="package-audit-section mt-4 pt-3 border-top">
            <h3 class="h6 fw-bold text-uppercase text-muted mb-2" style="letter-spacing:0.5px;">
                <i class="fas fa-history text-primary me-1"></i> Package Audit Trail &amp; Revision Log
            </h3>
            <p class="text-muted small mb-0"><i class="fas fa-info-circle me-1"></i>No audit records or score modifications logged for this package yet.</p>
        </div>';
    }

    $count_label = count($audits) . ' event' . (count($audits) === 1 ? '' : 's');
    $html = '<div class="package-audit-section mt-4 pt-3 border-top">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="h6 fw-bold text-uppercase text-muted mb-0" style="letter-spacing:0.5px;">
                <i class="fas fa-history text-primary me-1"></i> Package Audit Trail &amp; Revision History
            </h3>
            <span class="badge bg-light text-secondary border">
                ' . $count_label . '
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle table-bordered mb-0 bg-white" style="font-size:0.88rem; border-color:#E2E8F0;">
                <thead style="background:#F8FAFC; color:#475569;">
                    <tr>
                        <th style="width: 170px;">Date &amp; Time</th>
                        <th style="width: 220px;">Official / Actor</th>
                        <th style="width: 180px;">Action</th>
                        <th>Details &amp; Alterations</th>
                    </tr>
                </thead>
                <tbody>';

    foreach ($audits as $a) {
        $time_formatted = date('M d, Y h:i A', strtotime($a['created_at']));
        $actor_name = !empty($a['full_name']) ? $a['full_name'] : (trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?: ($a['username'] ?? 'System / Automated'));
        $actor_role = !empty($a['job_title']) ? $a['job_title'] : ($a['role'] ?? 'Official');

        $action_badge = '';
        switch ($a['action']) {
            case 'MEMBER_SCORES_ADJUSTED':
                $action_badge = '<span class="badge" style="background:#FEF3C7; color:#92400E; border:1px solid #F59E0B;"><i class="fas fa-sliders-h me-1"></i>Score Adjusted</span>';
                break;
            case 'DEV_PLAN_UPDATED':
                $action_badge = '<span class="badge" style="background:#E0E7FF; color:#3730A3; border:1px solid #C7D2FE;"><i class="fas fa-clipboard-list me-1"></i>Dev Plan Updated</span>';
                break;
            case 'APPROVED':
                $action_badge = '<span class="badge" style="background:#DCFCE7; color:#065F46; border:1px solid #86EFAC;"><i class="fas fa-check-circle me-1"></i>Step Approved</span>';
                break;
            case 'RETURNED':
                $action_badge = '<span class="badge" style="background:#FEE2E2; color:#991B1B; border:1px solid #FCA5A5;"><i class="fas fa-undo me-1"></i>Returned for Revision</span>';
                break;
            case 'MEMBER_RETURNED':
                $action_badge = '<span class="badge" style="background:#FEE2E2; color:#991B1B; border:1px solid #FCA5A5;"><i class="fas fa-user-times me-1"></i>Member Returned</span>';
                break;
            case 'LOCKED_AND_APPLIED':
                $action_badge = '<span class="badge" style="background:#DBEAFE; color:#1E40AF; border:1px solid #93C5FD;"><i class="fas fa-lock me-1"></i>Board Approved &amp; Locked</span>';
                break;
            case 'CANCELLED':
                $action_badge = '<span class="badge bg-secondary"><i class="fas fa-ban me-1"></i>Package Dropped</span>';
                break;
            case 'CREATED':
                $action_badge = '<span class="badge bg-light text-muted border"><i class="fas fa-plus-circle me-1"></i>Package Created</span>';
                break;
            default:
                $action_badge = '<span class="badge bg-light text-dark border">' . e($a['action']) . '</span>';
                break;
        }

        $remark_text = (string)($a['remarks'] ?? '');
        $details_html = '';

        if ($a['action'] === 'MEMBER_SCORES_ADJUSTED' && strpos($remark_text, '→') !== false) {
            $prefix = '';
            $changes_str = $remark_text;
            if (strpos($remark_text, ' — ') !== false) {
                list($prefix, $changes_str) = explode(' — ', $remark_text, 2);
            } elseif (strpos($remark_text, 'Adjusted ') === 0) {
                $changes_str = preg_replace('/^Adjusted\s+/', '', $remark_text);
            }
            
            $items = explode(';', $changes_str);
            $chips = [];
            foreach ($items as $item) {
                $item = trim($item);
                if (!$item) continue;
                if (strpos($item, '→') !== false && strpos($item, ':') !== false) {
                    list($crit_name, $vals) = explode(':', $item, 2);
                    list($old_v, $new_v) = explode('→', $vals, 2);
                    $chips[] = '<div class="d-inline-flex align-items-center me-2 mb-1 p-1 px-2 rounded border bg-light" style="font-size:0.83rem;">'
                        . '<strong class="me-1 text-dark">' . e(trim($crit_name)) . ':</strong> '
                        . '<span class="text-muted text-decoration-line-through me-1">' . e(trim($old_v)) . '</span>'
                        . '<i class="fas fa-arrow-right text-warning mx-1" style="font-size:0.75rem;"></i>'
                        . '<span class="fw-bold text-success">' . e(trim($new_v)) . '</span>'
                        . '</div>';
                } else {
                    $chips[] = '<span class="badge bg-light text-dark border me-1 mb-1">' . e($item) . '</span>';
                }
            }
            if ($prefix) {
                $details_html .= '<div class="fw-semibold text-primary mb-1"><i class="fas fa-user-edit me-1"></i>' . e($prefix) . '</div>';
            }
            $details_html .= '<div class="d-flex flex-wrap align-items-center">' . implode('', $chips) . '</div>';
        } else {
            $details_html = '<div class="text-dark">' . nl2br(e($remark_text)) . '</div>';
        }

        $html .= '<tr>
            <td class="text-muted small align-top tabular-nums">
                <i class="fas fa-clock text-secondary me-1"></i>' . $time_formatted . '
            </td>
            <td class="align-top">
                <div class="fw-bold text-dark"><i class="fas fa-user-tie text-secondary me-1"></i>' . e($actor_name) . '</div>
                <div class="text-muted" style="font-size:0.78rem;">' . e($actor_role) . '</div>
            </td>
            <td class="align-top">' . $action_badge . '</td>
            <td class="align-top">' . $details_html . '</td>
        </tr>';
    }

    $html .= '</tbody></table></div></div>';
    return $html;
}

function countPendingOrganizationPackagesForUser($conn, $user_id)
{
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !ensureOrganizationEvaluationPackageSchema($conn)) {
        return 0;
    }
    $match = organizationPackageReviewerMatchSql('rs');
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT ep.package_id) AS total
        FROM evaluation_packages ep
        JOIN evaluation_package_route_steps rs ON rs.package_id = ep.package_id
        WHERE $match
          AND (
              (rs.step_order = 1 AND rs.action_status IN ('Pending', 'Waiting'))
              OR (rs.step_order > 1 AND rs.action_status = 'Pending')
          )
          AND ep.status <> 'Approved and Applied'");
    $stmt->bind_param('ii', $user_id, $user_id);
    $stmt->execute();
    $total = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $total;
}

function isEvaluationInLockedOrganizationPackage($conn, $evaluation_id)
{
    $evaluation_id = (int) $evaluation_id;
    if ($evaluation_id <= 0 || !ensureOrganizationEvaluationPackageSchema($conn)) {
        return false;
    }
    $stmt = $conn->prepare("SELECT ep.status
        FROM evaluation_package_members pm
        JOIN evaluation_packages ep ON ep.package_id = pm.package_id
        WHERE pm.evaluation_id = ? AND ep.status = 'Approved and Applied'
        LIMIT 1");
    $stmt->bind_param('i', $evaluation_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return !empty($row);
}

function createOrganizationPackageRoute($conn, $package_id, $consolidator_employee_id)
{
    $package_id = (int) $package_id;
    $employee_id = (int) $consolidator_employee_id;
    $order = 1;
    $seen = [];
    $package_department_id = null;

    // Fetch the package department_id so we can pull the right Division VP.
    $pkg_stmt = $conn->prepare('SELECT department_id FROM evaluation_packages WHERE package_id = ? LIMIT 1');
    $pkg_stmt->bind_param('i', $package_id);
    $pkg_stmt->execute();
    $pkg_row = $pkg_stmt->get_result()->fetch_assoc();
    $pkg_stmt->close();
    if ($pkg_row) $package_department_id = (int) $pkg_row['department_id'];

    // Track which employee_ids already appear in the internal chain so we don't
    // duplicate them if the designated VP also sits in the reports_to chain.
    $chain_employee_ids = [];

    while ($employee_id > 0 && !isset($seen[$employee_id]) && $order <= 12) {
        $seen[$employee_id] = true;
        $stmt = $conn->prepare("SELECT e.employee_id, e.reports_to, e.job_title, e.rank_category_id, e.department_id, u.user_id
            FROM employees e LEFT JOIN users u ON u.employee_id = e.employee_id AND u.is_active = 1
            WHERE e.employee_id = ? AND e.is_active = 1 AND e.deleted_at IS NULL LIMIT 1");
        $stmt->bind_param('i', $employee_id);
        $stmt->execute();
        $person = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$person) break;

        $user_id = !empty($person['user_id']) ? (int) $person['user_id'] : 0;
        if ($user_id <= 0) {
            $u_stmt = $conn->prepare("SELECT user_id FROM users WHERE employee_id = ? AND is_active = 1 LIMIT 1");
            $u_stmt->bind_param('i', $employee_id);
            $u_stmt->execute();
            $u_row = $u_stmt->get_result()->fetch_assoc();
            $u_stmt->close();
            if ($u_row) $user_id = (int)$u_row['user_id'];
        }

        $type = $order === 1 ? 'Consolidation' : 'Review';
        $state = 'Waiting';
        $route = $conn->prepare('INSERT INTO evaluation_package_route_steps (package_id, step_order, reviewer_employee_id, reviewer_user_id, step_label, step_type, action_status) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $label = $order === 1 ? 'Team consolidation — ' . $person['job_title'] : 'Review — ' . $person['job_title'];
        $route->bind_param('iiiisss', $package_id, $order, $employee_id, $user_id, $label, $type, $state);
        $route->execute();
        $route->close();
        $chain_employee_ids[] = $employee_id;

        $next_employee_id = !empty($person['reports_to']) ? (int) $person['reports_to'] : 0;
        if ($next_employee_id <= 0 && (int)$person['rank_category_id'] === 4) {
            $mgr = $conn->prepare("SELECT employee_id FROM employees WHERE department_id = ? AND rank_category_id = 3 AND is_active = 1 AND deleted_at IS NULL LIMIT 1");
            $mgr->bind_param('i', $person['department_id']);
            $mgr->execute();
            $mgr_row = $mgr->get_result()->fetch_assoc();
            $mgr->close();
            if ($mgr_row && (int)$mgr_row['employee_id'] !== $employee_id) {
                $next_employee_id = (int)$mgr_row['employee_id'];
            }
        }
        $employee_id = $next_employee_id;
        $order++;
    }

    // --- Division VP step (department-specific, from Evaluation Routing & Governance) ---
    // Only appended if: a Division VP is configured for this department AND
    // the VP is not already a step in the internal chain (avoids duplicates).
    if ($package_department_id) {
        $div_vp = getDepartmentDesignatedOfficial($conn, 'Division VP', $package_department_id);
        if ($div_vp) {
            $vp_employee_id = !empty($div_vp['employee_id']) ? (int) $div_vp['employee_id'] : null;
            $already_in_chain = $vp_employee_id && in_array($vp_employee_id, $chain_employee_ids, true);
            if (!$already_in_chain) {
                $order = appendOrganizationGovernanceRouteStep($conn, $package_id, $order, 'Division VP', 'Division VP Review — ' . $div_vp['job_title'], $package_department_id);
            }
        }
    }

    // --- President step (company-wide) ---
    $order = appendOrganizationGovernanceRouteStep($conn, $package_id, $order, 'President', 'Executive Approval — President & CEO');

    // --- Audit Committee (optional, before Board) ---
    $order = appendOrganizationGovernanceRouteStep($conn, $package_id, $order, 'Audit Committee', 'Audit Committee approval');

    // --- Board of Directors (Final lock & apply) ---
    appendOrganizationGovernanceRouteStep($conn, $package_id, $order, 'Board of Directors', 'Board of Directors approval');
}

/**
 * A governance approver must remain independent from the employee-portal
 * reporting hierarchy. This prevents a President, CEO, VP, Manager, or other
 * reviewer from approving the same package again as the Board.
 */
function isEmployeeInOrganizationReviewHierarchy($conn, $employee_id)
{
    $employee_id = (int) $employee_id;
    if ($employee_id <= 0) return false;

    $staff_stmt = $conn->prepare('SELECT employee_id, reports_to FROM employees WHERE is_active = 1 AND deleted_at IS NULL');
    $staff_stmt->execute();
    $staff = $staff_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $staff_stmt->close();

    foreach ($staff as $person) {
        $next = !empty($person['reports_to']) ? (int) $person['reports_to'] : 0;
        $seen = [];
        while ($next > 0 && !isset($seen[$next])) {
            if ($next === $employee_id) return true;
            $seen[$next] = true;
            $parent_stmt = $conn->prepare('SELECT reports_to FROM employees WHERE employee_id = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1');
            $parent_stmt->bind_param('i', $next); $parent_stmt->execute();
            $parent = $parent_stmt->get_result()->fetch_assoc(); $parent_stmt->close();
            $next = !empty($parent['reports_to']) ? (int) $parent['reports_to'] : 0;
        }
    }
    return false;
}

function syncEvaluationToOrganizationPackage($conn, $evaluation_id)
{
    if (!ensureOrganizationEvaluationPackageSchema($conn)) return null;
    $stmt = $conn->prepare("SELECT ev.evaluation_id, ev.template_id, ev.evaluation_type, ev.evaluation_period_start, ev.evaluation_period_end,
            e.department_id, e.employee_id, e.first_name, e.last_name, e.job_title, et.template_name, d.department_name
        FROM evaluations ev
        JOIN employees e ON e.employee_id = ev.employee_id
        JOIN evaluation_templates et ON et.template_id = ev.template_id
        LEFT JOIN departments d ON d.department_id = e.department_id
        WHERE ev.evaluation_id = ? LIMIT 1");
    $stmt->bind_param('i', $evaluation_id);
    $stmt->execute();
    $evaluation = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$evaluation || empty($evaluation['department_id']) || empty($evaluation['evaluation_period_start']) || empty($evaluation['evaluation_period_end'])) return null;
    $department_id = (int) $evaluation['department_id'];
    $template_id = (int) $evaluation['template_id'];
    $type = $evaluation['evaluation_type']; $start = $evaluation['evaluation_period_start']; $end = $evaluation['evaluation_period_end'];
    // Look for an existing active (non-cancelled) package first
    $find = $conn->prepare("SELECT package_id, consolidator_employee_id FROM evaluation_packages WHERE department_id = ? AND template_id = ? AND period_start = ? AND period_end = ? AND status <> 'Cancelled' LIMIT 1");
    $find->bind_param('iiss', $department_id, $template_id, $start, $end); $find->execute();
    $package = $find->get_result()->fetch_assoc(); $find->close();
    if (!$package) {
        // Check if a cancelled package exists for the same period — reactivate it instead of inserting (avoids UNIQUE KEY duplicate error)
        $find_cancelled = $conn->prepare("SELECT package_id, consolidator_employee_id FROM evaluation_packages WHERE department_id = ? AND template_id = ? AND period_start = ? AND period_end = ? AND status = 'Cancelled' LIMIT 1");
        $find_cancelled->bind_param('iiss', $department_id, $template_id, $start, $end); $find_cancelled->execute();
        $cancelled_pkg = $find_cancelled->get_result()->fetch_assoc(); $find_cancelled->close();

        if ($cancelled_pkg) {
            // Reactivate the cancelled package
            $package_id = (int) $cancelled_pkg['package_id'];
            $consolidator_id = !empty($cancelled_pkg['consolidator_employee_id']) ? (int)$cancelled_pkg['consolidator_employee_id'] : 0;
            $conn->query("UPDATE evaluation_packages SET status = 'Pending Self-Ratings', current_step_order = NULL WHERE package_id = $package_id");
            // Reset route steps — handle blank/empty action_status as well as 'Cancelled'
            $conn->query("UPDATE evaluation_package_route_steps SET action_status = 'Waiting', acted_at = NULL, comments = NULL WHERE package_id = $package_id");
            // If no route steps exist at all, rebuild them from consolidator
            $step_count = (int)($conn->query("SELECT COUNT(*) AS c FROM evaluation_package_route_steps WHERE package_id = $package_id")->fetch_assoc()['c'] ?? 0);
            if ($step_count === 0 && $consolidator_id > 0) {
                createOrganizationPackageRoute($conn, $package_id, $consolidator_id);
            }
            $audit = $conn->prepare("INSERT INTO evaluation_package_audit (package_id, action, remarks) VALUES (?, 'REACTIVATED', 'Package reactivated after cancellation — new self-rating submission received.')");
            $audit->bind_param('i', $package_id); $audit->execute(); $audit->close();
        } else {
            // No package at all — create a fresh one
            $consolidator = getOrganizationPackageConsolidator($conn, $department_id);
            $consolidator_id = $consolidator ? (int) $consolidator['employee_id'] : 0;
            $insert = $conn->prepare('INSERT INTO evaluation_packages (department_id, template_id, evaluation_type, period_start, period_end, consolidator_employee_id) VALUES (?, ?, ?, ?, ?, NULLIF(?, 0))');
            $insert->bind_param('iisssi', $department_id, $template_id, $type, $start, $end, $consolidator_id); $insert->execute();
            $package_id = (int) $insert->insert_id; $insert->close();
            if ($consolidator_id) createOrganizationPackageRoute($conn, $package_id, $consolidator_id);
            $audit = $conn->prepare("INSERT INTO evaluation_package_audit (package_id, action, remarks) VALUES (?, 'CREATED', 'Created from first submitted self-rating.')");
            $audit->bind_param('i', $package_id); $audit->execute(); $audit->close();
        }
    } else {
        $package_id = (int) $package['package_id'];
        $consolidator_id = !empty($package['consolidator_employee_id']) ? (int)$package['consolidator_employee_id'] : 0;
    }
    $member = $conn->prepare('INSERT IGNORE INTO evaluation_package_members (package_id, evaluation_id) VALUES (?, ?)');
    $member->bind_param('ii', $package_id, $evaluation_id); $member->execute(); $member->close();
    recalculateOrganizationPackageBehaviorScore($conn, $package_id);

    // Trigger notification bell to the package consolidator upon member self-rating completion
    $emp_fullname = trim(($evaluation['first_name'] ?? '') . ' ' . ($evaluation['last_name'] ?? ''));
    $emp_job_title = $evaluation['job_title'] ?? '';
    $tmpl_name = $evaluation['template_name'] ?? 'Evaluation';
    $notif_title = 'Team Member Self-Rating Submitted';
    $notif_body = $emp_fullname . ($emp_job_title ? ' (' . $emp_job_title . ')' : '') . ' submitted their self-evaluation for ' . $tmpl_name . '.';
    $notif_link = BASE_URL . '/employee/team-evaluation-packages.php';

    if ($consolidator_id > 0) {
        notifyUsersForEmployee($conn, $consolidator_id, $notif_title, $notif_body, $notif_link);
    }

    // Open the consolidation step only after every active department member has
    // submitted the same template and period.
    $expected = $conn->prepare('SELECT COUNT(DISTINCT e.employee_id) AS total FROM employees e JOIN users u ON u.employee_id = e.employee_id AND u.is_active = 1 WHERE e.department_id = ? AND e.is_active = 1 AND e.deleted_at IS NULL');
    $expected->bind_param('i', $department_id); $expected->execute(); $expected_count = (int) $expected->get_result()->fetch_assoc()['total']; $expected->close();
    $submitted = $conn->prepare("SELECT COUNT(DISTINCT ev.employee_id) AS total FROM evaluations ev JOIN employees e ON e.employee_id = ev.employee_id WHERE e.department_id = ? AND ev.template_id = ? AND ev.evaluation_period_start = ? AND ev.evaluation_period_end = ? AND ev.deleted_at IS NULL AND ev.status NOT IN ('Draft','Pending Self-Rating','Returned','Rejected')");
    $submitted->bind_param('iiss', $department_id, $template_id, $start, $end); $submitted->execute(); $submitted_count = (int) $submitted->get_result()->fetch_assoc()['total']; $submitted->close();
    if ($expected_count > 0 && $submitted_count >= $expected_count) {
        $open = $conn->prepare("UPDATE evaluation_package_route_steps SET action_status = 'Pending' WHERE package_id = ? AND step_order = 1 AND action_status = 'Waiting'");
        $open->bind_param('i', $package_id); $open->execute(); $open->close();
        $status = $conn->prepare("UPDATE evaluation_packages SET status = 'Pending Consolidation', current_step_order = 1 WHERE package_id = ? AND status = 'Pending Self-Ratings'");
        $status->bind_param('i', $package_id); $status->execute(); $status->close();

        // Notify consolidator that the complete team package is unlocked and ready for consolidation
        $dept_name = $evaluation['department_name'] ?? 'Department';
        notifyOrganizationPackageStepAssignees($conn, $package_id, 1, 'Team Package Ready for Consolidation', "All required members of $dept_name have submitted their self-ratings. The evaluation package is now ready for your consolidation.");
    }
    return $package_id;
}

function getOrganizationPackageSubmissionSummary($conn, array $package)
{
    $department_id = (int) ($package['department_id'] ?? 0);
    $template_id = (int) ($package['template_id'] ?? 0);
    $start = $package['period_start'] ?? '';
    $end = $package['period_end'] ?? '';
    $stmt = $conn->prepare("SELECT DISTINCT e.employee_id, CONCAT(e.first_name, ' ', e.last_name) AS employee_name, e.job_title,
        EXISTS(SELECT 1 FROM evaluations ev WHERE ev.employee_id = e.employee_id AND ev.template_id = ? AND ev.evaluation_period_start = ? AND ev.evaluation_period_end = ? AND ev.deleted_at IS NULL AND ev.status NOT IN ('Draft','Pending Self-Rating','Returned','Rejected')) AS is_submitted
        FROM employees e JOIN users u ON u.employee_id = e.employee_id AND u.is_active = 1
        WHERE e.department_id = ? AND e.is_active = 1 AND e.deleted_at IS NULL ORDER BY e.last_name, e.first_name");
    $stmt->bind_param('issi', $template_id, $start, $end, $department_id);
    $stmt->execute();
    $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $submitted = count(array_filter($members, static fn($member) => (int) $member['is_submitted'] === 1));
    return ['members' => $members, 'submitted' => $submitted, 'required' => count($members)];
}

function recalculateOrganizationPackageBehaviorScore($conn, $package_id)
{
    $stmt = $conn->prepare('SELECT AVG(ev.behavior_average) AS shared_behavior_score FROM evaluation_package_members pm JOIN evaluations ev ON ev.evaluation_id = pm.evaluation_id WHERE pm.package_id = ?');
    $stmt->bind_param('i', $package_id); $stmt->execute();
    $score = (float) ($stmt->get_result()->fetch_assoc()['shared_behavior_score'] ?? 0); $stmt->close();
    $score = round($score, 2);
    $update = $conn->prepare('UPDATE evaluation_packages SET shared_behavior_score = ? WHERE package_id = ?');
    $update->bind_param('di', $score, $package_id); $update->execute(); $update->close();
    return $score;
}

function applyOrganizationPackageResults($conn, $package_id)
{
    $package_id = (int) $package_id;
    $stmt = $conn->prepare("SELECT ep.status, ep.shared_behavior_score, et.kra_weight, et.behavior_weight
        FROM evaluation_packages ep JOIN evaluation_templates et ON et.template_id = ep.template_id
        WHERE ep.package_id = ? LIMIT 1");
    $stmt->bind_param('i', $package_id); $stmt->execute(); $package = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$package || $package['shared_behavior_score'] === null) return false;
    // Idempotent lock: never re-apply or mutate an already finalized package.
    if (($package['status'] ?? '') === 'Approved and Applied') {
        return true;
    }
    $score = (float) $package['shared_behavior_score'];
    $kra_weight = (float) $package['kra_weight']; $behavior_weight = (float) $package['behavior_weight'];
    $members = $conn->prepare('SELECT e.evaluation_id, e.kra_subtotal FROM evaluation_package_members pm JOIN evaluations e ON e.evaluation_id = pm.evaluation_id WHERE pm.package_id = ?');
    $members->bind_param('i', $package_id); $members->execute(); $result = $members->get_result();
    while ($evaluation = $result->fetch_assoc()) {
        $total = calculateEvalTotal((float) $evaluation['kra_subtotal'], $score, $kra_weight, $behavior_weight);
        $level = getPerformanceLevel($total); $evaluation_id = (int) $evaluation['evaluation_id'];
        $update = $conn->prepare("UPDATE evaluations SET behavior_average = ?, total_score = ?, performance_level = ?, status = 'Approved', approved_date = NOW() WHERE evaluation_id = ?");
        $update->bind_param('ddsi', $score, $total, $level, $evaluation_id); $update->execute(); $update->close();
    }
    $members->close();
    return true;
}

/**
 * Keep unacted governance steps aligned with the currently active
 * Board / Audit authorized users. Already acted steps are left unchanged.
 */
function ensureOrganizationPackageGovernanceSteps($conn, $package_id)
{
    $package_id = (int) $package_id;
    if ($package_id <= 0) return false;

    // Fetch package department for Division VP lookup.
    $pkg = $conn->query("SELECT department_id, current_step_order, status FROM evaluation_packages WHERE package_id = $package_id")->fetch_assoc();
    $package_department_id = $pkg ? (int)($pkg['department_id'] ?? 0) : 0;

    // 0. Clean up any invalid steps placed AFTER Board of Directors (Board is always the final step).
    $board_step = $conn->query("SELECT step_order, action_status FROM evaluation_package_route_steps WHERE package_id = $package_id AND step_label LIKE '%Board%' LIMIT 1")->fetch_assoc();
    if ($board_step) {
        $board_order = (int) $board_step['step_order'];
        $conn->query("DELETE FROM evaluation_package_route_steps WHERE package_id = $package_id AND step_order > $board_order");

        // If Board of Directors is already approved, finalize the package immediately.
        if (($board_step['action_status'] ?? '') === 'Approved') {
            applyOrganizationPackageResults($conn, $package_id);
            $conn->query("UPDATE evaluation_packages SET status = 'Approved and Applied', current_step_order = NULL WHERE package_id = $package_id");
            return true;
        }
    }

    // Check if the department's designated Division VP is already an internal hierarchy reviewer in the route.
    if ($package_department_id > 0) {
        $div_vp = getDepartmentDesignatedOfficial($conn, 'Division VP', $package_department_id);
        if ($div_vp) {
            $vp_user_id = (int)($div_vp['user_id'] ?? 0);
            $vp_emp_id  = !empty($div_vp['employee_id']) ? (int)$div_vp['employee_id'] : 0;

            $internal_vp_count = (int)($conn->query("SELECT COUNT(*) AS c FROM evaluation_package_route_steps 
                WHERE package_id = $package_id 
                  AND step_type = 'Review'
                  AND (($vp_user_id > 0 AND reviewer_user_id = $vp_user_id) OR ($vp_emp_id > 0 AND reviewer_employee_id = $vp_emp_id))")->fetch_assoc()['c'] ?? 0);

            if ($internal_vp_count > 0) {
                // Already in reporting chain — purge any extraneous duplicate Governance Division VP step
                $conn->query("DELETE FROM evaluation_package_route_steps WHERE package_id = $package_id AND step_label LIKE 'Division VP Review%'");
            }
        }
    }

    // 1. Get current max step_order
    $res = $conn->query("SELECT MAX(step_order) AS max_order FROM evaluation_package_route_steps WHERE package_id = $package_id");
    $max_order = (int)($res->fetch_assoc()['max_order'] ?? 0);

    // 2. Append Division VP step only if configured for this department, not already in chain,
    // and only if global governance steps (President, Audit, Board) have not been appended yet.
    $has_gov_pres = (int)($conn->query("SELECT COUNT(*) AS c FROM evaluation_package_route_steps WHERE package_id = $package_id AND step_label LIKE '%President%'")->fetch_assoc()['c'] ?? 0);
    if ($package_department_id > 0 && !$has_gov_pres) {
        $div_vp = getDepartmentDesignatedOfficial($conn, 'Division VP', $package_department_id);
        if ($div_vp) {
            $vp_user_id = (int)($div_vp['user_id'] ?? 0);
            $vp_emp_id  = !empty($div_vp['employee_id']) ? (int)$div_vp['employee_id'] : 0;
            $has_vp_step = (int)($conn->query("SELECT COUNT(*) AS c FROM evaluation_package_route_steps 
                WHERE package_id = $package_id 
                  AND (step_label LIKE 'Division VP%' OR ($vp_user_id > 0 AND reviewer_user_id = $vp_user_id) OR ($vp_emp_id > 0 AND reviewer_employee_id = $vp_emp_id))")->fetch_assoc()['c'] ?? 0);

            if (!$has_vp_step) {
                $max_order++;
                $new_order = appendOrganizationGovernanceRouteStep($conn, $package_id, $max_order, 'Division VP', 'Division VP Review — ' . $div_vp['job_title'], $package_department_id);
                if ($new_order > $max_order) $max_order = $new_order - 1;
            }
        }
    }

    // 3. Append President step if missing.
    $has_president = (int)($conn->query("SELECT COUNT(*) AS c FROM evaluation_package_route_steps WHERE package_id = $package_id AND step_label LIKE '%President%'")->fetch_assoc()['c'] ?? 0);
    if (!$has_president) {
        $max_order++;
        $new_order = appendOrganizationGovernanceRouteStep($conn, $package_id, $max_order, 'President', 'Executive Approval — President & CEO');
        if ($new_order > $max_order) $max_order = $new_order - 1;
    }

    // 4. Append Audit Committee step if missing.
    $has_audit = (int)($conn->query("SELECT COUNT(*) AS c FROM evaluation_package_route_steps WHERE package_id = $package_id AND step_label LIKE '%Audit%'")->fetch_assoc()['c'] ?? 0);
    if (!$has_audit) {
        $max_order++;
        $new_order = appendOrganizationGovernanceRouteStep($conn, $package_id, $max_order, 'Audit Committee', 'Audit Committee approval');
        if ($new_order > $max_order) $max_order = $new_order - 1;
    }

    // 5. Append Board of Directors step if missing.
    $has_board = (int)($conn->query("SELECT COUNT(*) AS c FROM evaluation_package_route_steps WHERE package_id = $package_id AND step_label LIKE '%Board%'")->fetch_assoc()['c'] ?? 0);
    if (!$has_board) {
        $max_order++;
        appendOrganizationGovernanceRouteStep($conn, $package_id, $max_order, 'Board of Directors', 'Board of Directors approval');
    }

    // 6. Advance step if previous step was approved but next step was stuck in Waiting.
    if ($pkg) {
        $curr_order = (int)($pkg['current_step_order'] ?? 0);
        if ($curr_order > 0) {
            $curr_step = $conn->query("SELECT action_status FROM evaluation_package_route_steps WHERE package_id = $package_id AND step_order = $curr_order")->fetch_assoc();
            if ($curr_step && $curr_step['action_status'] === 'Approved') {
                $next_order = $curr_order + 1;
                $next_step = $conn->query("SELECT package_route_step_id, step_label, step_type, action_status FROM evaluation_package_route_steps WHERE package_id = $package_id AND step_order = $next_order")->fetch_assoc();
                if ($next_step && $next_step['action_status'] === 'Waiting') {
                    $conn->query("UPDATE evaluation_package_route_steps SET action_status = 'Pending' WHERE package_route_step_id = " . (int)$next_step['package_route_step_id']);
                    $new_status = getOrganizationPackageStatusForStep($next_step);
                    $conn->query("UPDATE evaluation_packages SET current_step_order = $next_order, status = '$new_status' WHERE package_id = $package_id");
                }
            }
        }
    }
    return true;
}

function syncPendingOrganizationPackageGovernanceApprovers($conn)
{
    if (!ensureOrganizationEvaluationPackageSchema($conn)) return 0;

    // Automatically append missing governance route steps for all active packages
    $active_pkgs = $conn->query("SELECT package_id FROM evaluation_packages WHERE status NOT IN ('Approved and Applied', 'Cancelled')");
    if ($active_pkgs) {
        while ($pkg = $active_pkgs->fetch_assoc()) {
            ensureOrganizationPackageGovernanceSteps($conn, (int)$pkg['package_id']);
        }
    }

    $updated = 0;

    // Sync company-wide governance roles (Board, Audit, President) — department_id IS NULL.
    $global_groups = [
        'Board of Directors' => [
            'label_match' => 'Board of Directors approval',
            'notify_title' => 'Board approval assignment updated',
            'notify_body'  => 'You are now the Board of Directors approver for a team evaluation package.',
        ],
        'Audit Committee' => [
            'label_match' => 'Audit Committee approval',
            'notify_title' => 'Audit approval assignment updated',
            'notify_body'  => 'You are now the Audit Committee approver for a team evaluation package.',
        ],
        'President' => [
            'label_match' => 'Executive Approval — President & CEO',
            'notify_title' => 'Executive approval assignment updated',
            'notify_body'  => 'You are now the President & CEO approver for a team evaluation package.',
        ],
    ];

    foreach ($global_groups as $governance_type => $meta) {
        $approver_stmt = $conn->prepare("SELECT ega.user_id, u.employee_id
            FROM evaluation_governance_approvers ega
            JOIN users u ON u.user_id = ega.user_id AND u.is_active = 1
            WHERE ega.governance_type = ? AND ega.department_id IS NULL AND ega.is_active = 1
            ORDER BY ega.governance_approver_id LIMIT 1");
        $approver_stmt->bind_param('s', $governance_type);
        $approver_stmt->execute();
        $approver = $approver_stmt->get_result()->fetch_assoc();
        $approver_stmt->close();
        if (!$approver) continue;

        $user_id    = (int) $approver['user_id'];
        $employee_id = !empty($approver['employee_id']) ? (int) $approver['employee_id'] : null;
        $label = $meta['label_match'];
        $steps_stmt = $conn->prepare("SELECT rs.package_route_step_id, rs.package_id
            FROM evaluation_package_route_steps rs
            JOIN evaluation_packages ep ON ep.package_id = rs.package_id
            WHERE rs.step_type = 'Governance'
              AND rs.step_label = ?
              AND rs.action_status IN ('Waiting', 'Pending')
              AND ep.status <> 'Approved and Applied'
              AND (rs.reviewer_user_id IS NULL OR rs.reviewer_user_id <> ?)");
        $steps_stmt->bind_param('si', $label, $user_id);
        $steps_stmt->execute();
        $steps = $steps_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $steps_stmt->close();
        if (!$steps) continue;

        $update = $conn->prepare('UPDATE evaluation_package_route_steps SET reviewer_user_id = ?, reviewer_employee_id = ? WHERE package_route_step_id = ?');
        foreach ($steps as $step) {
            $step_id = (int) $step['package_route_step_id'];
            $update->bind_param('iii', $user_id, $employee_id, $step_id);
            $update->execute();
            if ($update->affected_rows > 0) {
                $updated++;
                createNotification($conn, $user_id, $meta['notify_title'], $meta['notify_body'], BASE_URL . '/employee/team-evaluation-packages.php');
            }
        }
        $update->close();
    }

    // Sync Division VP steps — one approver per department.
    $vp_stmt = $conn->query("SELECT DISTINCT ega.department_id FROM evaluation_governance_approvers ega WHERE ega.governance_type = 'Division VP' AND ega.is_active = 1 AND ega.department_id IS NOT NULL");
    if ($vp_stmt) {
        while ($vp_dept = $vp_stmt->fetch_assoc()) {
            $dept_id = (int) $vp_dept['department_id'];
            $official = getDepartmentDesignatedOfficial($conn, 'Division VP', $dept_id);
            if (!$official) continue;
            $user_id    = (int) $official['user_id'];
            $employee_id = !empty($official['employee_id']) ? (int) $official['employee_id'] : null;

            $steps_stmt = $conn->prepare("SELECT rs.package_route_step_id
                FROM evaluation_package_route_steps rs
                JOIN evaluation_packages ep ON ep.package_id = rs.package_id
                WHERE rs.step_type = 'Governance'
                  AND rs.step_label LIKE 'Division VP Review%'
                  AND ep.department_id = ?
                  AND rs.action_status IN ('Waiting', 'Pending')
                  AND ep.status <> 'Approved and Applied'
                  AND (rs.reviewer_user_id IS NULL OR rs.reviewer_user_id <> ?)");
            $steps_stmt->bind_param('ii', $dept_id, $user_id);
            $steps_stmt->execute();
            $steps = $steps_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $steps_stmt->close();
            if (!$steps) continue;

            $update = $conn->prepare('UPDATE evaluation_package_route_steps SET reviewer_user_id = ?, reviewer_employee_id = ? WHERE package_route_step_id = ?');
            foreach ($steps as $step) {
                $step_id = (int) $step['package_route_step_id'];
                $update->bind_param('iii', $user_id, $employee_id, $step_id);
                $update->execute();
                if ($update->affected_rows > 0) {
                    $updated++;
                    createNotification($conn, $user_id, 'Division VP approval assignment updated', 'You are now the Division VP approver for a department evaluation package.', BASE_URL . '/employee/team-evaluation-packages.php');
                }
            }
            $update->close();
        }
    }

    return $updated;
}

/** @deprecated Use syncPendingOrganizationPackageGovernanceApprovers() */
function syncPendingOrganizationPackageBoardApprover($conn)
{
    return syncPendingOrganizationPackageGovernanceApprovers($conn);
}



/**

 * Get employee's supervisor (immediate head) based on reports_to

 */

function getEmployeeSupervisor($conn, $employee_id)
{
    $employee_id = (int)$employee_id;
    if ($employee_id <= 0) {
        return null;
    }

    static $supervisor_cache = [];
    if (array_key_exists($employee_id, $supervisor_cache)) {
        return $supervisor_cache[$employee_id];
    }

    ensureEmployeesReportsTo($conn);

    // Get the employee's direct reports_to, branch_id, and department_id
    $emp_stmt = $conn->prepare("SELECT reports_to, branch_id, department_id FROM employees WHERE employee_id = ? LIMIT 1");
    $emp_stmt->bind_param("i", $employee_id);
    $emp_stmt->execute();
    $emp_info = $emp_stmt->get_result()->fetch_assoc();
    $emp_stmt->close();

    if (!$emp_info) {
        return null;
    }

    $reports_to = $emp_info['reports_to'] ? (int) $emp_info['reports_to'] : 0;
    $branch_id = $emp_info['branch_id'] ? (int) $emp_info['branch_id'] : 0;
    $department_id = $emp_info['department_id'] ? (int) $emp_info['department_id'] : 0;
    $supervisor_employee_id = 0;

    if ($reports_to > 0) {
        // Direct supervisor reports_to exists
        // Check if this supervisor is active
        $check_active_stmt = $conn->prepare("SELECT employee_id FROM employees WHERE employee_id = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1");
        $check_active_stmt->bind_param("i", $reports_to);
        $check_active_stmt->execute();
        $is_active_res = $check_active_stmt->get_result()->fetch_assoc();
        $check_active_stmt->close();

        if ($is_active_res) {
            $supervisor_employee_id = $reports_to;
        }
    }

    if ($supervisor_employee_id <= 0 && $branch_id > 0) {
        // Step 2: Query the same branch for any active employee with Supervisor rank (rank_category_id = 4)
        // or job title containing "Supervisor"
        $sup_stmt = $conn->prepare("
            SELECT employee_id 
            FROM employees 
            WHERE branch_id = ? 
              AND is_active = 1 
              AND deleted_at IS NULL
              AND employee_id != ?
              AND (? = 0 OR department_id = ?)
              AND (rank_category_id = 4 OR job_title LIKE '%Supervisor%')
            LIMIT 1
        ");
        $sup_stmt->bind_param("iiii", $branch_id, $employee_id, $department_id, $department_id);
        $sup_stmt->execute();
        $sup_res = $sup_stmt->get_result()->fetch_assoc();
        $sup_stmt->close();

        if ($sup_res) {
            $supervisor_employee_id = (int)$sup_res['employee_id'];
        }
    }

    if ($supervisor_employee_id <= 0 && $branch_id > 0) {
        // Step 3: Query the same branch for any active employee with Manager rank (rank_category_id = 3)
        // or job title containing "Manager"
        $mgr_stmt = $conn->prepare("
            SELECT employee_id 
            FROM employees 
            WHERE branch_id = ? 
              AND is_active = 1 
              AND deleted_at IS NULL
              AND employee_id != ?
              AND (? = 0 OR department_id = ?)
              AND (rank_category_id = 3 OR job_title LIKE '%Manager%')
            LIMIT 1
        ");
        $mgr_stmt->bind_param("iiii", $branch_id, $employee_id, $department_id, $department_id);
        $mgr_stmt->execute();
        $mgr_res = $mgr_stmt->get_result()->fetch_assoc();
        $mgr_stmt->close();

        if ($mgr_res) {
            $supervisor_employee_id = (int)$mgr_res['employee_id'];
        }
    }

    if ($supervisor_employee_id > 0) {
        // Fetch full supervisor info, including user account if exists
        $stmt = $conn->prepare("
            SELECT ? as reports_to, s.employee_id as supervisor_employee_id, 
                   s.first_name, s.last_name, s.job_title,
                   u.user_id, u.full_name, u.email
            FROM employees s
            LEFT JOIN users u ON s.employee_id = u.employee_id
            WHERE s.employee_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("ii", $reports_to, $supervisor_employee_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $supervisor_cache[$employee_id] = $result ?: null;
        return $supervisor_cache[$employee_id];
    }

    $supervisor_cache[$employee_id] = null;
    return null;
}



/**
 * Returns a GENUINE branch supervisor (rank 4 / job_title LIKE '%Supervisor%') for the employee.
 * Unlike getEmployeeSupervisor(), this function does NOT fall back to a Branch Manager (rank 3).
 * Returns null when no rank-4 supervisor exists in the employee's branch + department.
 *
 * Use this for workflow STATUS decisions (Pending Dept Supervisor vs Pending HR Consolidation).
 * Use getEmployeeSupervisor() for notification routing.
 */
function getDeptSupervisorOfEmployee($conn, $employee_id)
{
    $employee_id = (int)$employee_id;
    if ($employee_id <= 0) {
        return null;
    }

    $stmt = $conn->prepare("SELECT reports_to, branch_id, department_id FROM employees WHERE employee_id = ? LIMIT 1");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $emp_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$emp_info) {
        return null;
    }

    $reports_to    = $emp_info['reports_to']    ? (int)$emp_info['reports_to']    : 0;
    $branch_id     = $emp_info['branch_id']     ? (int)$emp_info['branch_id']     : 0;
    $department_id = $emp_info['department_id'] ? (int)$emp_info['department_id'] : 0;
    $supervisor_employee_id = 0;

    // Step 1: reports_to exists AND the target is genuinely a supervisor (rank 4), not a manager
    if ($reports_to > 0) {
        $chk = $conn->prepare("
            SELECT employee_id FROM employees
            WHERE employee_id = ?
              AND is_active = 1
              AND deleted_at IS NULL
              AND (rank_category_id = 4 OR (job_title LIKE '%Supervisor%' AND job_title NOT LIKE '%Manager%'))
            LIMIT 1
        ");
        $chk->bind_param("i", $reports_to);
        $chk->execute();
        if ($res = $chk->get_result()->fetch_assoc()) {
            $supervisor_employee_id = (int)$res['employee_id'];
        }
        $chk->close();
    }

    // Step 2: Fallback — any active rank-4 in same branch + department
    if ($supervisor_employee_id <= 0 && $branch_id > 0) {
        $sup_stmt = $conn->prepare("
            SELECT employee_id FROM employees
            WHERE branch_id = ?
              AND is_active = 1
              AND deleted_at IS NULL
              AND employee_id != ?
              AND (? = 0 OR department_id = ?)
              AND (rank_category_id = 4 OR (job_title LIKE '%Supervisor%' AND job_title NOT LIKE '%Manager%'))
            LIMIT 1
        ");
        $sup_stmt->bind_param("iiii", $branch_id, $employee_id, $department_id, $department_id);
        $sup_stmt->execute();
        if ($res = $sup_stmt->get_result()->fetch_assoc()) {
            $supervisor_employee_id = (int)$res['employee_id'];
        }
        $sup_stmt->close();
    }

    // No genuine supervisor found — do NOT fall back to a manager
    if ($supervisor_employee_id <= 0) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT s.employee_id as supervisor_employee_id,
               s.first_name, s.last_name, s.job_title,
               u.user_id, u.full_name, u.email
        FROM employees s
        LEFT JOIN users u ON s.employee_id = u.employee_id
        WHERE s.employee_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $supervisor_employee_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $result ?: null;
}



/**

 * Notify supervisor that subordinate submitted self-rating

 */

function notifySupervisorOfSelfRating($conn, $employee_id, $evaluation_id)
{
    $employee_id = (int)$employee_id;
    $evaluation_id = (int)$evaluation_id;

    // Fetch evaluation status
    $status_stmt = $conn->prepare("SELECT status FROM evaluations WHERE evaluation_id = ? LIMIT 1");
    $status_stmt->bind_param("i", $evaluation_id);
    $status_stmt->execute();
    $status_row = $status_stmt->get_result()->fetch_assoc();
    $status_stmt->close();

    if (!$status_row) {
        return false;
    }
    $status = $status_row['status'];

    // If the evaluation status is NOT pending supervisor approval, do not notify local department supervisors
    if (!in_array($status, ['Pending Dept Supervisor', 'Pending Supervisor'])) {
        return false;
    }

    // Get employee details
    $emp_stmt = $conn->prepare("SELECT reports_to, branch_id, department_id, rank_category_id FROM employees WHERE employee_id = ? LIMIT 1");
    $emp_stmt->bind_param("i", $employee_id);
    $emp_stmt->execute();
    $emp_info = $emp_stmt->get_result()->fetch_assoc();
    $emp_stmt->close();

    if (!$emp_info) {
        return false;
    }

    $reports_to = $emp_info['reports_to'] ? (int) $emp_info['reports_to'] : 0;
    $branch_id = $emp_info['branch_id'] ? (int) $emp_info['branch_id'] : 0;
    $department_id = $emp_info['department_id'] ? (int) $emp_info['department_id'] : 0;
    $rank_category_id = $emp_info['rank_category_id'] ? (int) $emp_info['rank_category_id'] : 0;

    // Check if the direct supervisor (reports_to) is active
    $is_reports_to_active = false;
    if ($reports_to > 0) {
        $check_stmt = $conn->prepare("SELECT employee_id FROM employees WHERE employee_id = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1");
        $check_stmt->bind_param("i", $reports_to);
        $check_stmt->execute();
        $is_reports_to_active = (bool)$check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
    }

    // For Rank & File (rank 5), we always broadcast to all active supervisors in the same branch and department.
    if ($rank_category_id === 5) {
        $query = "
            SELECT DISTINCT u.user_id
            FROM users u
            JOIN employees s ON u.employee_id = s.employee_id
            WHERE s.is_active = 1 
              AND s.deleted_at IS NULL
              AND s.branch_id = ? 
              AND s.department_id = ? 
              AND s.employee_id != ?
              AND (s.rank_category_id = 4 OR (s.job_title LIKE '%Supervisor%' AND s.job_title NOT LIKE '%Manager%'))
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $branch_id, $department_id, $employee_id);
    } else {
        // For non-R&F roles, notify direct active supervisor if they exist, else broadcast.
        if ($is_reports_to_active) {
            $query = "
                SELECT DISTINCT u.user_id
                FROM users u
                JOIN employees s ON u.employee_id = s.employee_id
                WHERE s.is_active = 1 
                  AND s.deleted_at IS NULL
                  AND s.employee_id = ?
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $reports_to);
        } else {
            $query = "
                SELECT DISTINCT u.user_id
                FROM users u
                JOIN employees s ON u.employee_id = s.employee_id
                WHERE s.is_active = 1 
                  AND s.deleted_at IS NULL
                  AND s.branch_id = ? 
                  AND s.department_id = ? 
                  AND s.employee_id != ?
                  AND (s.rank_category_id IN (3, 4) OR s.job_title LIKE '%Supervisor%' OR s.job_title LIKE '%Manager%')
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iii", $branch_id, $department_id, $employee_id);
        }
    }

    $stmt->execute();
    $result = $stmt->get_result();
    
    $notified = false;
    $employee_name = getEmployeeNameById($conn, $employee_id);

    while ($row = $result->fetch_assoc()) {
        $supervisor_user_id = (int)$row['user_id'];
        
        $role_stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ? LIMIT 1");
        $role_stmt->bind_param("i", $supervisor_user_id);
        $role_stmt->execute();
        $user_role_row = $role_stmt->get_result()->fetch_assoc();
        $role_stmt->close();
        $user_role = $user_role_row['role'] ?? 'Employee';

        if ($user_role === 'HR Supervisor') {
            $title = 'Employee Self-Rating Submitted';
            $msg = $employee_name . ' submitted a self-rating for review.';
            $link = BASE_URL . '/supervisor/pending-endorsements.php';
        } elseif ($user_role === 'HR Manager') {
            $title = 'Employee Self-Rating Submitted';
            $msg = $employee_name . ' submitted a self-rating for review.';
            $link = BASE_URL . '/manager/pending-approvals.php';
        } else {
            $title = 'Self-Rating Pending Confirmation';
            $msg = $employee_name . ' submitted a self-rating awaiting your confirmation.';
            $link = BASE_URL . '/employee/team-evaluation-packages.php';
        }

        createNotification(
            $conn,
            $supervisor_user_id,
            $title,
            $msg,
            $link
        );
        $notified = true;
    }
    $stmt->close();

    return $notified;
}



/**

 * Check if user is supervisor of specific employee

 */

function isSupervisorOfEmployee($conn, $supervisor_user_id, $employee_id)
{
    $supervisor_user_id = (int) $supervisor_user_id;
    $employee_id = (int) $employee_id;

    // Get the supervisor details
    $stmt = $conn->prepare("
        SELECT e.employee_id, e.branch_id, e.department_id, e.rank_category_id, e.job_title
        FROM users u
        JOIN employees e ON u.employee_id = e.employee_id
        WHERE u.user_id = ? AND e.is_active = 1 AND e.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->bind_param("i", $supervisor_user_id);
    $stmt->execute();
    $supervisor = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$supervisor) {
        return false;
    }

    $supervisor_employee_id = (int)$supervisor['employee_id'];

    // Get employee details
    $stmt = $conn->prepare("SELECT reports_to, branch_id, department_id, rank_category_id FROM employees WHERE employee_id = ? LIMIT 1");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $employee = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$employee) {
        return false;
    }

    // Special rule for Branch Manager self-rating: reviewed by a Branch Supervisor (rank 4) in the same branch
    if ((int)$employee['rank_category_id'] === 3 && (int)$supervisor['rank_category_id'] === 4 && (int)$supervisor['branch_id'] === (int)$employee['branch_id']) {
        return true;
    }

    $reports_to = $employee['reports_to'] ? (int)$employee['reports_to'] : 0;
    if ($reports_to > 0 && $reports_to === $supervisor_employee_id) {
        return true;
    }

    // Check same branch/department supervisor or manager
    if ((int)$supervisor['branch_id'] === (int)$employee['branch_id'] && 
        (int)$supervisor['department_id'] === (int)$employee['department_id'] &&
        $supervisor_employee_id !== $employee_id) {
        
        $rank_cat = (int)$supervisor['rank_category_id'];
        $job_title = $supervisor['job_title'];
        if ($rank_cat === 3 || $rank_cat === 4 || 
            stripos($job_title, 'Supervisor') !== false || 
            stripos($job_title, 'Manager') !== false) {
            return true;
        }
    }

    // Fallback to the default getEmployeeSupervisor matching logic
    $fallback_sup = getEmployeeSupervisor($conn, $employee_id);
    if ($fallback_sup && (int)$fallback_sup['supervisor_employee_id'] === $supervisor_employee_id) {
        return true;
    }

    return false;
}

/**
 * Recalculate evaluation scores (KRA subtotal, Behavior average, total score, performance level)
 * after a supervisor or manager override.
 */
function recalculateEvaluationScores($conn, $evaluation_id)
{
    $evaluation_id = (int)$evaluation_id;

    // Fetch the evaluation details (template weight splits)
    $eval_q = $conn->query("SELECT ev.*, et.kra_weight, et.behavior_weight 
                            FROM evaluations ev 
                            LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id 
                            WHERE ev.evaluation_id = $evaluation_id");
    
    if (!$eval_q || $eval_q->num_rows === 0) {
        return false;
    }
    
    $eval = $eval_q->fetch_assoc();
    $kra_weight_pct = (float)($eval['kra_weight'] ?? 80);
    $beh_weight_pct = (float)($eval['behavior_weight'] ?? 20);

    // Fetch all scores for this evaluation
    $scores_q = $conn->query("SELECT es.*, ec.section, ec.weight 
                              FROM evaluation_scores es 
                              JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id 
                              WHERE es.evaluation_id = $evaluation_id");

    $kra_subtotal = 0;
    $beh_total = 0;
    $beh_count = 0;

    while ($row = $scores_q->fetch_assoc()) {
        $score_id = (int)$row['score_id'];
        
        // Hierarchy of scores: HR Manager Override > HR Supervisor Override > Dept Manager Override > Original Score
        $effective_score = $row['score_value'];
        if ($row['dept_manager_override_score'] !== null) {
            $effective_score = (float)$row['dept_manager_override_score'];
        }
        if ($row['supervisor_override_score'] !== null) {
            $effective_score = (float)$row['supervisor_override_score'];
        }
        if ($row['manager_override_score'] !== null) {
            $effective_score = (float)$row['manager_override_score'];
        }

        // Keep database up-to-date with active weighted score
        if ($row['section'] === 'KRA') {
            $weight = (float)$row['weight'];
            $weighted = round(($weight / 100) * $effective_score, 2);
            $kra_subtotal += $weighted;
            
            $conn->query("UPDATE evaluation_scores SET weighted_score = $weighted WHERE score_id = $score_id");
        } else {
            $beh_total += $effective_score;
            $beh_count++;
            
            $conn->query("UPDATE evaluation_scores SET weighted_score = $effective_score WHERE score_id = $score_id");
        }
    }

    $kra_subtotal = round($kra_subtotal, 2);
    $behavior_average = $beh_count > 0 ? round($beh_total / $beh_count, 2) : 0;

    // Recalculate overall score
    $total_score = calculateEvalTotal($kra_subtotal, $behavior_average, $kra_weight_pct, $beh_weight_pct);
    $performance_level = getPerformanceLevel($total_score);

    // Update main evaluations table
    $stmt = $conn->prepare("UPDATE evaluations SET kra_subtotal = ?, behavior_average = ?, total_score = ?, performance_level = ?, updated_at = NOW() WHERE evaluation_id = ?");
    $stmt->bind_param("dddsi", $kra_subtotal, $behavior_average, $total_score, $performance_level, $evaluation_id);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

/**
 * Get employee's department manager (supervisor of supervisor) based on reports_to
 */
function getDeptManagerOfEmployee($conn, $employee_id)
{
    $employee_id = (int)$employee_id;
    if ($employee_id <= 0) {
        return null;
    }

    // Get immediate supervisor (reports_to), branch_id, and rank/job title
    $stmt = $conn->prepare("SELECT reports_to, branch_id, department_id, job_title, rank_category_id FROM employees WHERE employee_id = ? LIMIT 1");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $emp_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$emp_info) {
        return null;
    }

    $reports_to = $emp_info['reports_to'] ? (int)$emp_info['reports_to'] : 0;
    $branch_id = $emp_info['branch_id'] ? (int)$emp_info['branch_id'] : 0;
    $department_id = $emp_info['department_id'] ? (int)$emp_info['department_id'] : 0;
    $job_title = $emp_info['job_title'] ?? '';
    $rank_category_id = (int)($emp_info['rank_category_id'] ?? 0);

    if (($rank_category_id === 4 || stripos($job_title, 'Supervisor') !== false) && $reports_to > 0) {
        $stmt = $conn->prepare("
            SELECT m.reports_to, m.employee_id as supervisor_employee_id,
                   m.first_name, m.last_name, m.job_title, m.rank_category_id,
                   u.user_id, u.full_name, u.email
            FROM employees m
            LEFT JOIN users u ON m.employee_id = u.employee_id AND u.is_active = 1
            WHERE m.employee_id = ?
              AND m.is_active = 1
              AND m.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->bind_param("i", $reports_to);
        $stmt->execute();
        $manager = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($manager && !empty($manager['user_id'])) {
            $m_rank = (int)($manager['rank_category_id'] ?? 0);
            $m_title = $manager['job_title'] ?? '';
            if ($m_rank === 3 || stripos($m_title, 'Manager') !== false) {
                return $manager;
            }
        }
    }

    // 1. Try reports_to chain: employee -> supervisor -> manager
    if ($reports_to > 0) {
        $stmt = $conn->prepare("
            SELECT m.reports_to, m.employee_id as supervisor_employee_id,
                   m.first_name, m.last_name, m.job_title, m.rank_category_id,
                   u.user_id, u.full_name, u.email
            FROM employees s
            JOIN employees m ON s.reports_to = m.employee_id
            LEFT JOIN users u ON m.employee_id = u.employee_id AND u.is_active = 1
            WHERE s.employee_id = ?
              AND s.is_active = 1
              AND s.deleted_at IS NULL
              AND m.is_active = 1
              AND m.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->bind_param("i", $reports_to);
        $stmt->execute();
        $manager = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($manager && !empty($manager['user_id'])) {
            $m_rank = (int)($manager['rank_category_id'] ?? 0);
            $m_title = $manager['job_title'] ?? '';
            if ($m_rank === 3 || stripos($m_title, 'Manager') !== false) {
                return $manager;
            }
        }
    }

    // 2. Fallback to branch-based hierarchy if reports_to chain is not set or doesn't yield a manager
    if ($branch_id > 0) {
        // If the employee is a Manager themselves, they don't have a department manager above them.
        if ($rank_category_id === 3 || stripos($job_title, 'Manager') !== false) {
            return null;
        }

        // Check if there is an active Manager in the same branch
        $mgr_stmt = $conn->prepare("
            SELECT employee_id 
            FROM employees 
            WHERE branch_id = ? 
              AND is_active = 1 
              AND deleted_at IS NULL
              AND employee_id != ?
              AND (? = 0 OR department_id = ?)
              AND (rank_category_id = 3 OR job_title LIKE '%Manager%')
            ORDER BY employee_id ASC
            LIMIT 1
        ");
        $mgr_stmt->bind_param("iiii", $branch_id, $employee_id, $department_id, $department_id);
        $mgr_stmt->execute();
        $mgr_res = $mgr_stmt->get_result()->fetch_assoc();
        $mgr_stmt->close();

        if ($mgr_res) {
            $manager_employee_id = (int)$mgr_res['employee_id'];

            // Return the manager info as the department manager for the employee
            $stmt = $conn->prepare("
                SELECT s.reports_to, s.employee_id as supervisor_employee_id, 
                       s.first_name, s.last_name, s.job_title,
                       u.user_id, u.full_name, u.email
                FROM employees s
                LEFT JOIN users u ON s.employee_id = u.employee_id
                WHERE s.employee_id = ? AND s.is_active = 1 AND s.deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->bind_param("i", $manager_employee_id);
            $stmt->execute();
            $mgr_info = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $mgr_info;
        }
    }

    return null;
}
/**
 * Get all active department managers of employee (either via reports_to chain or same branch & department fallback)
 */
function getDeptManagersOfEmployee($conn, $employee_id)
{
    $employee_id = (int)$employee_id;
    if ($employee_id <= 0) {
        return [];
    }

    // Get immediate supervisor (reports_to), branch_id, and rank/job title
    $stmt = $conn->prepare("SELECT reports_to, branch_id, department_id, job_title, rank_category_id FROM employees WHERE employee_id = ? LIMIT 1");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $emp_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$emp_info) {
        return [];
    }

    $reports_to = $emp_info['reports_to'] ? (int)$emp_info['reports_to'] : 0;
    $branch_id = $emp_info['branch_id'] ? (int)$emp_info['branch_id'] : 0;
    $department_id = $emp_info['department_id'] ? (int)$emp_info['department_id'] : 0;
    $job_title = $emp_info['job_title'] ?? '';
    $rank_category_id = (int)($emp_info['rank_category_id'] ?? 0);

    $managers = [];

    // 1. If employee is a supervisor and reports_to is set, get that manager
    if (($rank_category_id === 4 || stripos($job_title, 'Supervisor') !== false) && $reports_to > 0) {
        $stmt = $conn->prepare("
            SELECT m.reports_to, m.employee_id as supervisor_employee_id,
                   m.first_name, m.last_name, m.job_title,
                   u.user_id, u.full_name, u.email
            FROM employees m
            LEFT JOIN users u ON m.employee_id = u.employee_id AND u.is_active = 1
            WHERE m.employee_id = ?
              AND m.is_active = 1
              AND m.deleted_at IS NULL
        ");
        $stmt->bind_param("i", $reports_to);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (!empty($row['user_id'])) {
                $managers[$row['user_id']] = $row;
            }
        }
        $stmt->close();
    }

    // 2. Try reports_to chain: employee -> supervisor -> manager
    if ($reports_to > 0) {
        $stmt = $conn->prepare("
            SELECT m.reports_to, m.employee_id as supervisor_employee_id,
                   m.first_name, m.last_name, m.job_title,
                   u.user_id, u.full_name, u.email
            FROM employees s
            JOIN employees m ON s.reports_to = m.employee_id
            LEFT JOIN users u ON m.employee_id = u.employee_id AND u.is_active = 1
            WHERE s.employee_id = ?
              AND s.is_active = 1
              AND s.deleted_at IS NULL
              AND m.is_active = 1
              AND m.deleted_at IS NULL
        ");
        $stmt->bind_param("i", $reports_to);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (!empty($row['user_id'])) {
                $managers[$row['user_id']] = $row;
            }
        }
        $stmt->close();
    }

    // 3. Fallback to branch-based hierarchy if reports_to chain is not set or doesn't yield a manager
    if (empty($managers) && $branch_id > 0) {
        // If the employee is a Manager themselves, they don't have a department manager above them.
        if ($rank_category_id === 3 || stripos($job_title, 'Manager') !== false) {
            return [];
        }

        // Get all active managers in the same branch/department
        $mgr_stmt = $conn->prepare("
            SELECT m.employee_id as supervisor_employee_id, m.reports_to,
                   m.first_name, m.last_name, m.job_title,
                   u.user_id, u.full_name, u.email
            FROM employees m
            LEFT JOIN users u ON m.employee_id = u.employee_id AND u.is_active = 1
            WHERE m.branch_id = ? 
              AND m.is_active = 1 
              AND m.deleted_at IS NULL
              AND m.employee_id != ?
              AND (? = 0 OR m.department_id = ?)
              AND (m.rank_category_id = 3 OR m.job_title LIKE '%Manager%')
        ");
        $mgr_stmt->bind_param("iiii", $branch_id, $employee_id, $department_id, $department_id);
        $mgr_stmt->execute();
        $res = $mgr_stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (!empty($row['user_id'])) {
                $managers[$row['user_id']] = $row;
            }
        }
        $mgr_stmt->close();
    }

    return array_values($managers);
}

/**
 * Check if user is department manager of specific employee (supervisor's supervisor)
 */
function isDeptManagerOfEmployee($conn, $manager_user_id, $employee_id)
{
    $manager_user_id = (int)$manager_user_id;
    $employee_id = (int)$employee_id;

    // Get manager's employee_id
    $stmt = $conn->prepare("SELECT employee_id FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $manager_user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $manager_employee_id = (int)($res['employee_id'] ?? 0);
    $stmt->close();

    if (!$manager_employee_id) {
        return false;
    }

    // 1. Try direct reports_to check first
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM employees e
        JOIN employees s ON e.reports_to = s.employee_id
        WHERE e.employee_id = ? AND s.reports_to = ? AND e.is_active = 1 AND s.is_active = 1
    ");
    $stmt->bind_param("ii", $employee_id, $manager_employee_id);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
    $stmt->close();

    if ($count > 0) {
        return true;
    }

    // 2. Fallback: Resolve employee's department managers using new logic
    $dept_managers = getDeptManagersOfEmployee($conn, $employee_id);
    foreach ($dept_managers as $mgr) {
        if ((int)$mgr['supervisor_employee_id'] === $manager_employee_id) {
            return true;
        }
    }

    return false;
}

/**
 * Notify all Branch Supervisors of an employee when department manager returns/rejects the evaluation
 */
function notifySupervisorOfReturnedEvaluation($conn, $employee_id, $evaluation_id, $manager_name)
{
    $employee_id = (int)$employee_id;
    $evaluation_id = (int)$evaluation_id;

    // Get employee details
    $emp_stmt = $conn->prepare("SELECT reports_to, branch_id, department_id FROM employees WHERE employee_id = ? LIMIT 1");
    $emp_stmt->bind_param("i", $employee_id);
    $emp_stmt->execute();
    $emp_info = $emp_stmt->get_result()->fetch_assoc();
    $emp_stmt->close();

    if (!$emp_info) {
        return false;
    }

    $reports_to = $emp_info['reports_to'] ? (int)$emp_info['reports_to'] : 0;
    $branch_id = $emp_info['branch_id'] ? (int)$emp_info['branch_id'] : 0;
    $department_id = $emp_info['department_id'] ? (int)$emp_info['department_id'] : 0;

    // Find all supervisors/managers who can confirm the rating (excluding the returning manager)
    $query = "
        SELECT DISTINCT u.user_id
        FROM users u
        JOIN employees s ON u.employee_id = s.employee_id
        WHERE s.is_active = 1 
          AND s.deleted_at IS NULL
          AND (
            (s.employee_id = ? AND ? > 0)
            OR (
              s.branch_id = ? 
              AND s.department_id = ? 
              AND s.employee_id != ?
              AND (s.rank_category_id = 4 OR s.job_title LIKE '%Supervisor%')
            )
          )
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiiii", $reports_to, $reports_to, $branch_id, $department_id, $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notified = false;
    $employee_name = getEmployeeNameById($conn, $employee_id);

    while ($row = $result->fetch_assoc()) {
        $supervisor_user_id = (int)$row['user_id'];
        
        $role_stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ? LIMIT 1");
        $role_stmt->bind_param("i", $supervisor_user_id);
        $role_stmt->execute();
        $user_role_row = $role_stmt->get_result()->fetch_assoc();
        $role_stmt->close();
        $user_role = $user_role_row['role'] ?? 'Employee';

        if ($user_role === 'HR Supervisor') {
            $title = 'Evaluation Returned by Department Manager';
            $msg = $manager_name . ' returned the evaluation for ' . $employee_name . ' to your level for re-evaluation.';
            $link = BASE_URL . '/supervisor/pending-endorsements.php';
        } elseif ($user_role === 'HR Manager') {
            $title = 'Evaluation Returned by Department Manager';
            $msg = $manager_name . ' returned the evaluation for ' . $employee_name . ' to your level for re-evaluation.';
            $link = BASE_URL . '/manager/pending-approvals.php';
        } else {
            $title = 'Evaluation Returned by Department Manager';
            $msg = $manager_name . ' returned the evaluation for ' . $employee_name . ' to your level for re-evaluation.';
            $link = BASE_URL . '/employee/team-evaluation-packages.php';
        }

        createNotification(
            $conn,
            $supervisor_user_id,
            $title,
            $msg,
            $link
        );
        $notified = true;
    }
    $stmt->close();

    // Fallback: Notify HR Supervisor if no branch supervisors found
    if (!$notified) {
        $hr_supervisors_stmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'HR Supervisor' AND branch_id = ? AND is_active = 1");
        $hr_supervisors_stmt->bind_param("i", $branch_id);
        $hr_supervisors_stmt->execute();
        $hr_supervisors = $hr_supervisors_stmt->get_result();
        $hr_notified = false;
        while ($hr_sup = $hr_supervisors->fetch_assoc()) {
            createNotification(
                $conn,
                (int)$hr_sup['user_id'],
                'Evaluation Returned by Department Manager',
                $manager_name . ' returned the evaluation for ' . $employee_name . ' for re-evaluation. (No supervisor assigned)',
                BASE_URL . '/supervisor/pending-endorsements.php'
            );
            $hr_notified = true;
            $notified = true;
        }
        $hr_supervisors_stmt->close();

        if (!$hr_notified) {
            $hr_all_stmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'HR Supervisor' AND is_active = 1");
            $hr_all_stmt->execute();
            $hr_all_res = $hr_all_stmt->get_result();
            while ($hr_sup = $hr_all_res->fetch_assoc()) {
                createNotification(
                    $conn,
                    (int)$hr_sup['user_id'],
                    'Evaluation Returned by Department Manager',
                    $manager_name . ' returned the evaluation for ' . $employee_name . ' for re-evaluation. (No supervisor assigned)',
                    BASE_URL . '/supervisor/pending-endorsements.php'
                );
                $notified = true;
            }
            $hr_all_stmt->close();
        }
    }

    return $notified;
}

/**
 * Check if employee is a department manager (has subordinate supervisors who themselves have subordinates)
 */
function isDeptManagerRole($conn, $employee_id)
{
    $employee_id = (int)$employee_id;
    if (!$employee_id) {
        return false;
    }

    if (getEmployeeHRRole($conn, $employee_id) !== null || isMainOfficeHumanResourcesEmployee($conn, $employee_id)) {
        return false;
    }

    // 1. Direct reports_to check: Check if there is any active employee whose supervisor reports to this employee
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM employees e
        JOIN employees s ON e.reports_to = s.employee_id
        WHERE s.reports_to = ? AND e.is_active = 1 AND s.is_active = 1
    ");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
    $stmt->close();

    if ($count > 0) {
        return true;
    }

    // 2. Fallback check: is the employee themselves a Manager (rank_category_id = 3 or job_title containing Manager)?
    $stmt = $conn->prepare("
        SELECT rank_category_id, job_title 
        FROM employees 
        WHERE employee_id = ? AND is_active = 1 AND deleted_at IS NULL 
        LIMIT 1
    ");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $emp = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($emp) {
        $rank_category_id = (int)($emp['rank_category_id'] ?? 0);
        $job_title = $emp['job_title'] ?? '';
        if ($rank_category_id === 3 || stripos($job_title, 'Manager') !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Get employee's HR role from active user accounts
 */
function getEmployeeHRRole($conn, $employee_id)
{
    $employee_id = (int)$employee_id;
    if (!$employee_id || !$conn) {
        return null;
    }
    $stmt = $conn->prepare("SELECT role FROM users WHERE employee_id = ? AND role IN ('HR Staff', 'HR Supervisor', 'HR Manager') AND is_active = 1 LIMIT 1");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res ? $res['role'] : null;
}

function ensureEmployeeEvaluationStatusViewSchema($conn): bool
{
    static $ensured = false;
    if ($ensured) {
        return true;
    }

    try {
        $conn->query("
            CREATE TABLE IF NOT EXISTS employee_evaluation_status_views (
                employee_id INT NOT NULL PRIMARY KEY,
                last_viewed_at DATETIME NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (mysqli_sql_exception $e) {
        return false;
    }

    $ensured = true;
    return true;
}

function markEmployeeEvaluationStatusViewed($conn, $employee_id): bool
{
    $employee_id = (int)$employee_id;
    if ($employee_id <= 0 || !ensureEmployeeEvaluationStatusViewSchema($conn)) {
        return false;
    }

    $stmt = $conn->prepare("
        INSERT INTO employee_evaluation_status_views (employee_id, last_viewed_at)
        VALUES (?, NOW())
        ON DUPLICATE KEY UPDATE last_viewed_at = NOW()
    ");
    $stmt->bind_param("i", $employee_id);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

function isSupervisorLevelEmployee($employee): bool
{
    if (!is_array($employee)) {
        return false;
    }

    $rank_category_id = (int)($employee['rank_category_id'] ?? 0);
    $job_title = (string)($employee['job_title'] ?? '');

    return $rank_category_id === 4 || stripos($job_title, 'Supervisor') !== false;
}

function isMainOfficeHumanResourcesEmployee($conn, $employee_id): bool
{
    $employee_id = (int)$employee_id;
    if ($employee_id <= 0) {
        return false;
    }

    $stmt = $conn->prepare("
        SELECT d.department_name, b.branch_name
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.department_id
        LEFT JOIN branches b ON e.branch_id = b.branch_id
        WHERE e.employee_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return false;
    }

    $department = strtolower(trim((string)($row['department_name'] ?? '')));
    $branch = strtolower(trim((string)($row['branch_name'] ?? '')));

    $is_hr_department = in_array($department, ['human resources', 'human resource', 'hr'], true)
        || strpos($department, 'human resources') !== false;
    $is_main_office = strpos($branch, 'main') !== false || strpos($branch, 'head office') !== false;

    return $is_hr_department && $is_main_office;
}

/**
 * Calculate the original self-rating total score from the employee's initial submissions
 */
function getOriginalSelfRatingScore($conn, $evaluation_id)
{
    $evaluation_id = (int)$evaluation_id;
    if ($evaluation_id <= 0) {
        return null;
    }

    // Fetch the evaluation details (template weight splits)
    $eval_q = $conn->query("SELECT ev.*, et.kra_weight, et.behavior_weight 
                            FROM evaluations ev 
                            LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id 
                            WHERE ev.evaluation_id = $evaluation_id");
    
    if (!$eval_q || $eval_q->num_rows === 0) {
        return null;
    }
    
    $eval = $eval_q->fetch_assoc();
    $kra_weight_pct = (float)($eval['kra_weight'] ?? 80);
    $beh_weight_pct = (float)($eval['behavior_weight'] ?? 20);

    // Fetch original scores (score_value)
    $scores_q = $conn->query("SELECT es.score_value, ec.section, ec.weight 
                              FROM evaluation_scores es 
                              JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id 
                              WHERE es.evaluation_id = $evaluation_id");
    if (!$scores_q) {
        return null;
    }

    $kra_subtotal = 0;
    $beh_total = 0;
    $beh_count = 0;

    while ($row = $scores_q->fetch_assoc()) {
        $score = (float)$row['score_value'];
        if ($row['section'] === 'KRA') {
            $weight = (float)$row['weight'];
            $weighted = round(($weight / 100) * $score, 2);
            $kra_subtotal += $weighted;
        } else {
            $beh_total += $score;
            $beh_count++;
        }
    }

    $kra_subtotal = round($kra_subtotal, 2);
    $behavior_average = $beh_count > 0 ? round($beh_total / $beh_count, 2) : 0;
    return calculateEvalTotal($kra_subtotal, $behavior_average, $kra_weight_pct, $beh_weight_pct);
}

// ============================================
// Employee Portal — Career Movement Helpers
// ============================================

/**
 * Return the movement types a user is allowed to submit through the Employee Portal.
 *
 * Only Branch Supervisors (rank_category_id === 4) who have at least one subordinate
 * in their branch may submit Transfer requests.
 *
 * @param  int  $rank_category_id   The submitting employee's rank category (4 = Branch Supervisor).
 * @param  bool $has_subordinates   Whether the employee has at least one active branch employee.
 * @return array                    ['Transfer'] when eligible; [] otherwise.
 *
 * Requirements: 1.1, 1.2
 */
function buildAllowedMovementTypes(int $rank_category_id, bool $has_subordinates): array
{
    if ($rank_category_id === 4 && $has_subordinates === true) {
        return ['Transfer'];
    }
    return [];
}

/**
 * Get all active employees in a branch (and optionally department) for the Transfer request dropdown,
 * excluding the submitting supervisor themselves.
 *
 * @param mysqli $conn
 * @param int    $sup_employee_id  The supervisor's employee_id (excluded from results)
 * @param int    $branch_id        The branch to query
 * @param int    $department_id    Optional — when > 0, further restricts to same department
 * @return array  Rows with keys: employee_id, first_name, last_name, job_title
 */
function getBranchEmployeesForDropdown($conn, $sup_employee_id, $branch_id, int $department_id = 0): array
{
    $sup_employee_id = (int) $sup_employee_id;
    $branch_id       = (int) $branch_id;

    if ($branch_id <= 0) {
        return [];
    }

    if ($department_id > 0) {
        $stmt = $conn->prepare(
            "SELECT e.employee_id, e.employee_code, e.first_name, e.last_name, e.job_title, e.branch_id, e.department_id,
                    b.branch_name, d.department_name, rc.rank_name, rc.level_order
             FROM employees e
             LEFT JOIN branches b ON e.branch_id = b.branch_id
             LEFT JOIN departments d ON e.department_id = d.department_id
             LEFT JOIN rank_categories rc ON e.rank_category_id = rc.rank_category_id
             WHERE e.branch_id = ? AND e.department_id = ? AND e.is_active = 1 AND e.employee_id != ?
             ORDER BY e.last_name, e.first_name"
        );
        $stmt->bind_param("iii", $branch_id, $department_id, $sup_employee_id);
    } else {
        $stmt = $conn->prepare(
            "SELECT e.employee_id, e.employee_code, e.first_name, e.last_name, e.job_title, e.branch_id, e.department_id,
                    b.branch_name, d.department_name, rc.rank_name, rc.level_order
             FROM employees e
             LEFT JOIN branches b ON e.branch_id = b.branch_id
             LEFT JOIN departments d ON e.department_id = d.department_id
             LEFT JOIN rank_categories rc ON e.rank_category_id = rc.rank_category_id
             WHERE e.branch_id = ? AND e.is_active = 1 AND e.employee_id != ?
             ORDER BY e.last_name, e.first_name"
        );
        $stmt->bind_param("ii", $branch_id, $sup_employee_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
    $stmt->close();

    return $employees;
}

/**
 * Return a human-readable label for a portal_workflow_stage ENUM value.
 *
 * Valid ENUM values and their labels:
 *   Pending_Branch_Manager  → "Pending Branch Manager"
 *   Pending_HR_Supervisor   → "Pending HR Supervisor"
 *   Pending_HR_Manager      → "Pending HR Manager"
 *   Approved                → "Approved"
 *   Rejected                → "Rejected"
 *
 * @param  string|null $stage  A portal_workflow_stage ENUM value.
 * @return string              Human-readable label, or '' for unknown/invalid values.
 *
 * Requirements: 8.2, 8.3
 */
function getPortalStageLabel($stage): string
{
    $labels = [
        'Pending_Branch_Manager' => 'Pending Branch Manager',
        'Pending_HR_Supervisor'  => 'Pending HR Supervisor',
        'Pending_HR_Manager'     => 'Pending HR Manager',
        'Approved'               => 'Approved',
        'Rejected'               => 'Rejected',
    ];

    return $labels[$stage] ?? '';
}

/**
 * Apply a stage transition for a Portal_Request approval workflow.
 *
 * Encodes the six valid transitions across the three-approver chain:
 *   Pending_Branch_Manager  + approve → Pending_HR_Supervisor
 *   Pending_Branch_Manager  + reject  → Rejected
 *   Pending_HR_Supervisor   + approve → Pending_HR_Manager
 *   Pending_HR_Supervisor   + reject  → Rejected
 *   Pending_HR_Manager      + approve → Approved
 *   Pending_HR_Manager      + reject  → Rejected
 *
 * Terminal states (Approved, Rejected) and any unrecognised stage/action combination
 * return null — callers must treat null as an authorization/logic error and abort.
 *
 * @param  string $current_stage  The current portal_workflow_stage value.
 * @param  string $action         The action being taken: 'approve' or 'reject'.
 * @return string|null            The next stage string, or null for invalid input.
 *
 * Requirements: 5.2, 5.3, 6.2, 6.3, 7.2, 7.3
 */
function applyStageTransition(string $current_stage, string $action): ?string
{
    $transitions = [
        'Pending_Branch_Manager' => [
            'approve' => 'Pending_HR_Supervisor',
            'reject'  => 'Rejected',
        ],
        'Pending_HR_Supervisor' => [
            'approve' => 'Pending_HR_Manager',
            'reject'  => 'Rejected',
        ],
        'Pending_HR_Manager' => [
            'approve' => 'Approved',
            'reject'  => 'Rejected',
        ],
    ];

    return $transitions[$current_stage][$action] ?? null;
}

/**
 * Validate a Transfer submission from the Employee Portal before inserting a career_movements record.
 *
 * Checks (in order):
 *  1. The target employee belongs to the submitter's branch (active, same branch_id).
 *  2. $new_branch_id is provided and is different from the employee's current branch.
 *  3. No duplicate pending Portal_Request already exists for the same employee.
 *
 * @param mysqli $conn
 * @param int    $submitter_branch_id  The branch_id of the submitting Branch Supervisor.
 * @param int    $submitter_emp_id     The employee_id of the submitting Branch Supervisor (unused
 *                                     in DB checks here but kept for future audit use).
 * @param int    $employee_id          The target employee being transferred.
 * @param mixed  $new_branch_id        The destination branch_id selected by the submitter.
 * @return string|null  Validation error message string, or null when input is valid.
 *
 * Requirements: 1.2, 1.3, 2.2, 4.1
 */
function validateTransferSubmission($conn, $submitter_branch_id, $submitter_emp_id, $employee_id, $new_branch_id): ?string
{
    $submitter_branch_id = (int) $submitter_branch_id;
    $submitter_emp_id    = (int) $submitter_emp_id;
    $employee_id         = (int) $employee_id;

    // --- Check 1: Employee must exist, be active, and belong to the submitter's branch ---
    if ($employee_id <= 0 || $submitter_branch_id <= 0) {
        return 'The selected employee is not eligible.';
    }

    $stmt = $conn->prepare(
        "SELECT branch_id FROM employees WHERE employee_id = ? AND branch_id = ? AND is_active = 1 LIMIT 1"
    );
    $stmt->bind_param("ii", $employee_id, $submitter_branch_id);
    $stmt->execute();
    $emp_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$emp_row) {
        return 'The selected employee is not eligible.';
    }

    $current_branch_id = (int) $emp_row['branch_id'];

    // --- Check 2: new_branch_id must be present and differ from the employee's current branch ---
    if (empty($new_branch_id) || (int) $new_branch_id <= 0) {
        return 'A different destination branch must be selected.';
    }

    if ((int) $new_branch_id === $current_branch_id) {
        return 'A different destination branch must be selected.';
    }

    // --- Check 3: No duplicate pending Portal_Request for this employee ---
    $stmt = $conn->prepare(
        "SELECT movement_id FROM career_movements
         WHERE employee_id = ? AND request_source = 'Employee Portal' AND approval_status = 'Pending'
         LIMIT 1"
    );
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $dup_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($dup_row) {
        return 'A pending Transfer request already exists for this employee.';
    }

    return null;
}

/**
 * Determine whether an actor is authorised to take an approval action on a Portal_Request.
 *
 * All three guards must pass for the function to return true:
 *   1. Stage match  — the movement's current portal_workflow_stage equals $required_stage.
 *   2. Branch match — when the required stage is 'Pending_Branch_Manager', the movement's
 *                     previous_branch_id must equal the actor's own branch_id.
 *   3. Self-approval — the actor must NOT be the user who originally logged the request
 *                      (logged_by field).
 *
 * @param  array  $movement        Associative array of the career_movements row (must include
 *                                 'portal_workflow_stage', 'previous_branch_id', 'logged_by').
 * @param  int    $actor_user_id   The user_id of the approver attempting the action.
 * @param  int    $actor_branch_id The branch_id of the approver (from their employee record).
 * @param  string $actor_role      The role string of the approver (not currently used in checks
 *                                 but kept for callers that pass it for logging/context).
 * @param  string $required_stage  The portal_workflow_stage the movement must be at for this
 *                                 action to be valid.
 * @return bool   true if all guards pass; false otherwise.
 *
 * Requirements: 5.4, 11.1, 11.2, 11.3, 11.4, 11.5
 */
function checkApprovalAuthorization(
    array  $movement,
    int    $actor_user_id,
    int    $actor_branch_id,
    string $actor_role,
    string $required_stage
): bool {
    // Guard 1: Stage must match the required stage
    if (($movement['portal_workflow_stage'] ?? null) !== $required_stage) {
        return false;
    }

    // Guard 2: Branch Manager actions are scoped to the movement's originating branch
    if ($required_stage === 'Pending_Branch_Manager') {
        if ((int)($movement['previous_branch_id'] ?? -1) !== $actor_branch_id) {
            return false;
        }
    }

    // Guard 3: Prevent self-approval — the actor must not be the original submitter
    if ($actor_user_id === (int)($movement['logged_by'] ?? 0)) {
        return false;
    }

    return true;
}

/**
 * Return HTML for either a single score circle or side-by-side original & adjusted circles
 */
function getEvaluationScoreCirclesHtml($conn, $evaluation_id, $current_score)
{
    $current_score = (float)$current_score;
    $original_score = getOriginalSelfRatingScore($conn, $evaluation_id);
    if ($original_score !== null && abs($current_score - $original_score) > 0.01) {
        return '
        <div class="d-flex align-items-center gap-3">
            <div class="score-circle" style="border-color:#6c757d; min-width:80px;" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Original Self-Rating</strong><br>Score: ' . number_format($original_score, 2) . '">
                <div class="val text-secondary" style="font-size:1.15rem; color:#6c757d !important;">' . number_format($original_score, 2) . '</div>
                <div class="lbl text-secondary" style="font-size:0.55rem; font-weight:700;">Original</div>
            </div>
            <div class="score-circle" style="border-color:#198754; min-width:80px;" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Adjusted Score</strong><br>Score: ' . number_format($current_score, 2) . '">
                <div class="val text-success total-score-val" style="font-size:1.15rem; color:#198754 !important;">' . number_format($current_score, 2) . '</div>
                <div class="lbl text-success" style="font-size:0.55rem; font-weight:700;">Adjusted</div>
            </div>
        </div>';
    } else {
        return '
        <div class="score-circle">
            <div class="val total-score-val">' . number_format($current_score, 2) . '/4</div>
            <div class="lbl">Score</div>
        </div>';
    }
}
/**
 * Returns every active non-regular employee (OJT, Trainee, Probationary,
 * Project Based), regardless of when their contract or probation ends.
 * Each row includes a calculated `days_remaining` (negative = overdue) and `urgency`
 * (overdue | critical | warning | upcoming).
 */
function getExpiringNonRegularEmployees($conn, $daysThreshold = 60, $departmentId = 0)
{
    $nonRegularStatuses = "('OJT','Trainee','Probationary','Project Based','Project-Based')";
    $departmentId = (int) $departmentId;
    $departmentClause = $departmentId > 0 ? " AND e.department_id = {$departmentId}" : '';
    $result = $conn->query("
        SELECT e.employee_id, e.department_id, e.first_name, e.last_name, e.profile_picture,
               e.job_title, e.employment_status, e.hire_date,
               e.contract_start_date, e.contract_end_date,
               b.branch_name, d.department_name,
               CASE
                   WHEN e.contract_end_date IS NOT NULL
                       THEN DATEDIFF(e.contract_end_date, CURRENT_DATE())
                   WHEN e.employment_status = 'Probationary'
                       THEN DATEDIFF(DATE_ADD(e.hire_date, INTERVAL 6 MONTH), CURRENT_DATE())
                   WHEN e.employment_status IN ('OJT','Trainee')
                       THEN DATEDIFF(DATE_ADD(e.hire_date, INTERVAL 60 DAY), CURRENT_DATE())
                   ELSE NULL
               END AS days_remaining
        FROM employees e
        LEFT JOIN branches b ON e.branch_id = b.branch_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE e.is_active = 1
          AND e.employment_status IN {$nonRegularStatuses}
          AND e.employee_id NOT IN (
              SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL
          )
          {$departmentClause}
        ORDER BY
            CASE WHEN days_remaining IS NULL THEN 1 ELSE 0 END,
            days_remaining ASC,
            e.last_name ASC,
            e.first_name ASC
    ");

    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $d = $row['days_remaining'] !== null ? (int)$row['days_remaining'] : null;
            if ($d === null)    $row['urgency'] = 'unscheduled';
            elseif ($d < 0)     $row['urgency'] = 'overdue';
            elseif ($d <= 14)   $row['urgency'] = 'critical';
            elseif ($d <= 30)   $row['urgency'] = 'warning';
            else                $row['urgency'] = 'upcoming';
            $rows[] = $row;
        }
    }
    return $rows;
}
/**
 * Returns distinct color styling (bg, text color, border) for each employment status.
 */
function getEmploymentStatusBadgeStyle(string $status): array
{
    $statusMap = [
        'Regular'                        => ['bg' => '#d1fae5', 'color' => '#065f46', 'border' => '#a7f3d0'], // Emerald Green
        'Probationary'                   => ['bg' => '#e0e7ff', 'color' => '#3730a3', 'border' => '#c7d2fe'], // Royal Indigo
        'OJT'                            => ['bg' => '#f3e8ff', 'color' => '#6b21a8', 'border' => '#e9d5ff'], // Vibrant Purple
        'Trainee'                        => ['bg' => '#ccfbf1', 'color' => '#115e59', 'border' => '#99f6e4'], // Bright Teal
        'Project Based'                  => ['bg' => '#fef3c7', 'color' => '#92400e', 'border' => '#fde68a'], // Warm Amber
        'Project-Based'                  => ['bg' => '#fef3c7', 'color' => '#92400e', 'border' => '#fde68a'], // Warm Amber
        'Resignation'                    => ['bg' => '#ffedd5', 'color' => '#c2410c', 'border' => '#fed7aa'], // Warm Orange
        'Separated'                      => ['bg' => '#f3f4f6', 'color' => '#4b5563', 'border' => '#e5e7eb'], // Cool Slate
        'AWOL'                           => ['bg' => '#ffe4e6', 'color' => '#9f1239', 'border' => '#fecdd3'], // Crimson Red
        'Failed in Training'             => ['bg' => '#fee2e2', 'color' => '#991b1b', 'border' => '#fca5a5'], // Rose Red
        'Termination for Cause'          => ['bg' => '#7f1d1d', 'color' => '#ffffff', 'border' => '#991b1b'], // Dark Burgundy
        'Retirement'                     => ['bg' => '#e0f2fe', 'color' => '#075985', 'border' => '#bae6fd'], // Sky Blue
        'Death'                          => ['bg' => '#111827', 'color' => '#f9fafb', 'border' => '#374151'], // Dark Slate
        'Permanent of Total Disability'  => ['bg' => '#f5f3ff', 'color' => '#5b21b6', 'border' => '#ddd6fe'], // Deep Lavender
    ];

    return $statusMap[$status] ?? ['bg' => '#f3f4f6', 'color' => '#374151', 'border' => '#d1d5db'];
}

/**
 * Renders a distinct HTML badge for any employment status.
 */
function renderEmploymentStatusBadge(string $status, string $extraClass = ''): string
{
    $st = getEmploymentStatusBadgeStyle($status);
    $style = "background-color: {$st['bg']}; color: {$st['color']}; border: 1px solid {$st['border']}; font-weight: 600; font-size: 0.72rem; padding: 3px 9px; border-radius: 12px; display: inline-block; white-space: nowrap;";
    return sprintf('<span class="status-badge %s" style="%s">%s</span>', htmlspecialchars($extraClass), $style, htmlspecialchars($status));
}

/**
 * Ensures the employee_edit_history table exists in the database.
 */
function ensureEmployeeEditHistorySchema($conn): bool
{
    static $history_ensured = false;
    if ($history_ensured) return true;
    try {
        $conn->query("
            CREATE TABLE IF NOT EXISTS employee_edit_history (
                edit_id        INT AUTO_INCREMENT PRIMARY KEY,
                employee_id    INT NOT NULL,
                edited_by      INT NOT NULL,
                editor_name    VARCHAR(150) NOT NULL,
                editor_role    VARCHAR(50) NOT NULL,
                step_number    INT NULL,
                step_name      VARCHAR(100) NULL,
                change_summary TEXT NULL,
                changes_json   LONGTEXT NULL,
                ip_address     VARCHAR(45) NULL,
                created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_eeh_emp (employee_id),
                INDEX idx_eeh_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $history_ensured = true;
        return true;
    } catch (Throwable $e) {
        error_log('ensureEmployeeEditHistorySchema error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Computes the differences between original and newly submitted employee data,
 * records the exact changes to employee_edit_history, and creates an audit log entry.
 */
function logEmployeeProfileEdit($conn, int $employee_id, int $editor_user_id, ?int $step_number, ?string $step_name, array $old_data, array $new_data, ?string $custom_summary = null): bool
{
    ensureEmployeeEditHistorySchema($conn);

    // Fetch editor user information
    $editor_name = 'System / HR Personnel';
    $editor_role = 'HR Personnel';
    $u_stmt = $conn->prepare("SELECT u.role, CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,'')) as full_name, u.username FROM users u LEFT JOIN employees e ON u.employee_id = e.employee_id WHERE u.user_id = ?");
    if ($u_stmt) {
        $u_stmt->bind_param("i", $editor_user_id);
        $u_stmt->execute();
        $u_row = $u_stmt->get_result()->fetch_assoc();
        $u_stmt->close();
        if ($u_row) {
            $editor_role = $u_row['role'] ?: 'HR Personnel';
            $editor_name = trim($u_row['full_name']) ?: ($u_row['username'] ?: 'HR Personnel');
        }
    }

    // Comprehensive field label dictionary
    $field_labels = [
        'employee_code'        => 'Employee Code',
        'first_name'           => 'First Name',
        'last_name'            => 'Last Name',
        'middle_name'          => 'Middle Name',
        'name_extension'       => 'Name Extension',
        'date_of_birth'        => 'Date of Birth',
        'place_of_birth'       => 'Place of Birth',
        'gender'               => 'Gender',
        'civil_status'         => 'Civil Status',
        'height_m'             => 'Height (m)',
        'weight_kg'            => 'Weight (kg)',
        'blood_type'           => 'Blood Type',
        'citizenship'          => 'Citizenship',
        'sss_number'           => 'SSS No.',
        'philhealth_number'    => 'PhilHealth No.',
        'pagibig_number'       => 'Pag-IBIG No.',
        'tin_number'           => 'TIN No.',
        'telephone_number'     => 'Telephone No.',
        'mobile_number'        => 'Mobile No.',
        'personal_email'       => 'Personal Email',
        'email'                => 'Email Address',
        'contact_number'       => 'Contact Number',
        'hire_date'            => 'Engagement / Hire Date',
        'job_title'            => 'Job Title',
        'department_id'        => 'Department',
        'branch_id'            => 'Branch',
        'rank_category_id'     => 'Rank Category',
        'employment_status'    => 'Employment Status',
        'employment_type'      => 'Employment Type',
        'contract_start_date'  => 'Contract Start Date',
        'contract_end_date'    => 'Contract End Date',
        'profile_picture'      => 'Profile Picture',
        'res_street'           => 'Residential Street',
        'res_barangay'         => 'Residential Barangay',
        'res_city'             => 'Residential City',
        'res_province'         => 'Residential Province',
        'res_zip_code'         => 'Residential Zip Code',
        'perm_street'          => 'Permanent Street',
        'perm_barangay'        => 'Permanent Barangay',
        'perm_city'            => 'Permanent City',
        'perm_province'        => 'Permanent Province',
        'perm_zip_code'        => 'Permanent Zip Code',
        'spouse_first_name'    => 'Spouse First Name',
        'spouse_surname'       => 'Spouse Surname',
        'father_first_name'    => 'Father First Name',
        'father_surname'       => 'Father Surname',
        'mother_first_name'    => 'Mother First Name',
        'mother_maiden_surname'=> 'Mother Maiden Surname',
        'emergency_contact_name'         => 'Emergency Contact Name',
        'emergency_contact_relationship' => 'Emergency Contact Relationship',
        'emergency_contact_number'       => 'Emergency Contact Number',
        'is_related_to_company'          => 'Company Relationship Disclosure',
        'related_details'                => 'Company Relationship Details',
        'has_admin_offense'              => 'Admin Offense Disclosure',
        'admin_offense_details'          => 'Admin Offense Details',
        'has_criminal_charge'            => 'Criminal Charge Disclosure',
        'criminal_charge_details'        => 'Criminal Charge Details',
        'has_criminal_conviction'        => 'Criminal Conviction Disclosure',
        'criminal_conviction_details'    => 'Criminal Conviction Details',
        'has_been_separated'             => 'Previous Separation Disclosure',
        'separation_details'             => 'Previous Separation Details',
        'is_pwd'                         => 'PWD Status',
        'pwd_details'                    => 'PWD Details',
        'is_solo_parent'                 => 'Solo Parent Status',
        'solo_parent_details'            => 'Solo Parent Details',
        'has_recent_hospital'            => 'Recent Hospitalization Disclosure',
        'hospital_details'               => 'Hospitalization Details',
        'has_current_treatment'          => 'Current Medical Treatment',
        'treatment_details'              => 'Medical Treatment Details',
    ];

    // Branch & Department name resolver caches
    static $dept_map = null;
    static $branch_map = null;
    if ($dept_map === null) {
        $dept_map = [];
        $dres = $conn->query("SELECT department_id, department_name FROM departments");
        if ($dres) while ($dr = $dres->fetch_assoc()) $dept_map[(int)$dr['department_id']] = $dr['department_name'];
    }
    if ($branch_map === null) {
        $branch_map = [];
        $bres = $conn->query("SELECT branch_id, branch_name FROM branches");
        if ($bres) while ($br = $bres->fetch_assoc()) $branch_map[(int)$br['branch_id']] = $br['branch_name'];
    }

    $changes = [];
    $summary_parts = [];

    foreach ($field_labels as $f_key => $label) {
        if (!array_key_exists($f_key, $new_data)) continue;

        $old_val = isset($old_data[$f_key]) ? trim((string)$old_data[$f_key]) : '';
        $new_val = isset($new_data[$f_key]) ? trim((string)$new_data[$f_key]) : '';

        // Normalize nulls and booleans
        if ($old_val !== $new_val) {
            $display_old = $old_val;
            $display_new = $new_val;

            // Resolve branch & department names
            if ($f_key === 'department_id') {
                $display_old = $dept_map[(int)$old_val] ?? ($old_val ?: 'None');
                $display_new = $dept_map[(int)$new_val] ?? ($new_val ?: 'None');
            } elseif ($f_key === 'branch_id') {
                $display_old = $branch_map[(int)$old_val] ?? ($old_val ?: 'None');
                $display_new = $branch_map[(int)$new_val] ?? ($new_val ?: 'None');
            } elseif (in_array($f_key, ['is_related_to_company', 'has_admin_offense', 'has_criminal_charge', 'has_criminal_conviction', 'has_been_separated', 'is_pwd', 'is_solo_parent', 'has_recent_hospital', 'has_current_treatment'])) {
                $display_old = ((int)$old_val === 1) ? 'Yes' : 'No';
                $display_new = ((int)$new_val === 1) ? 'Yes' : 'No';
                if ($display_old === $display_new) continue;
            }

            $changes[$f_key] = [
                'label' => $label,
                'old'   => $display_old,
                'new'   => $display_new
            ];
            $summary_parts[] = $label;
        }
    }

    if (empty($changes) && empty($custom_summary)) {
        return false; // No changes to log
    }

    $summary = $custom_summary;
    if (empty($summary)) {
        if (!empty($summary_parts)) {
            $count = count($summary_parts);
            if ($count <= 3) {
                $summary = "Updated " . implode(', ', $summary_parts);
            } else {
                $summary = "Updated $count fields: " . implode(', ', array_slice($summary_parts, 0, 3)) . " and " . ($count - 3) . " more";
            }
        } else {
            $summary = "Updated profile details";
        }
    }

    $changes_json = !empty($changes) ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $stmt = $conn->prepare("INSERT INTO employee_edit_history (employee_id, edited_by, editor_name, editor_role, step_number, step_name, change_summary, changes_json, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iississss", $employee_id, $editor_user_id, $editor_name, $editor_role, $step_number, $step_name, $summary, $changes_json, $ip);
        $stmt->execute();
        $stmt->close();
    }

    // Also link with master logAudit
    $emp_first = $new_data['first_name'] ?? ($old_data['first_name'] ?? '');
    $emp_last  = $new_data['last_name'] ?? ($old_data['last_name'] ?? '');
    logAudit($conn, $editor_user_id, 'UPDATE', 'Employee', $employee_id, "Profile edited by {$editor_role} ({$editor_name}) for employee {$emp_first} {$emp_last}. {$summary}");

    return true;
}

/**
 * Retrieves the profile edit history for a specific employee.
 */
function getEmployeeEditHistory($conn, int $employee_id, int $limit = 50): array
{
    ensureEmployeeEditHistorySchema($conn);
    $records = [];
    $stmt = $conn->prepare("SELECT * FROM employee_edit_history WHERE employee_id = ? ORDER BY created_at DESC LIMIT ?");
    if ($stmt) {
        $stmt->bind_param("ii", $employee_id, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (!empty($row['changes_json'])) {
                $row['changes_data'] = json_decode($row['changes_json'], true) ?: [];
            } else {
                $row['changes_data'] = [];
            }
            $records[] = $row;
        }
        $stmt->close();
    }
    return $records;
}

/**
 * Normalizes and formats Philippine mobile numbers to standard 11-digit format starting with '0' (e.g., 09998988877).
 * Handles:
 *   - '09998988877' (standard format, preserves leading 0)
 *   - '9998988877'  (10 digits where Excel dropped the leading 0 -> prepends '0')
 *   - '+639998988877' or '639998988877' (international format -> '09998988877')
 *   - '0999-898-8877', '(0999) 898 8877', '0999.898.8877' (formats with spaces/dashes -> '09998988877')
 *
 * @param  string|int|null $number
 * @return string
 */
function formatPHMobileNumber($number): string
{
    if ($number === null || $number === '') {
        return '';
    }

    $str = trim((string)$number);
    if ($str === '') {
        return '';
    }

    // Strip non-digits
    $digits = preg_replace('/[^\d]/', '', $str);

    if ($digits === '') {
        return $str;
    }

    // 12 digits starting with 639 (e.g., 639998988877 from +639998988877) -> 09998988877
    if (strlen($digits) === 12 && str_starts_with($digits, '63') && substr($digits, 2, 1) === '9') {
        return '0' . substr($digits, 2);
    }

    // 10 digits starting with 9 (Excel dropped leading 0 e.g. 9998988877) -> 09998988877
    if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
        return '0' . $digits;
    }

    // 11 digits starting with 0 (standard e.g. 09998988877)
    if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
        return $digits;
    }

    // Otherwise return cleaned digits or string
    return $digits;
}
?>

