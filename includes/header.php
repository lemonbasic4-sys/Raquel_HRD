<?php
/**
 * Common Header - includes navbar, sidebar, and CDN links
 * Usage: include this file at the top of every dashboard page
 * Requires: $page_title (string), session must be active
 */

require_once __DIR__ . '/functions.php';

// Auto-backup scheduler: silently check & trigger if Admin session
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') {
    require_once __DIR__ . '/auto-backup-check.php';
}

// Get dynamic branding settings
$sys_pawnshop_name = getSetting($conn, 'company_name', 'Raquel Pawnshop');
$sys_logo = getSetting($conn, 'system_logo', 'assets/img/logo/logo.png');

// Determine current page for active nav highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

$effective_role = $_SESSION['role'] ?? '';

// Notifications are strictly account-based but now isolated by portal context.
$notif_context = ($current_dir === 'employee') ? 'employee' : 'hr';
$notif_count = getUnreadNotificationCount($conn, (int) $_SESSION['user_id'], $notif_context);
$notifications = getRecentNotifications($conn, (int) $_SESSION['user_id'], 5, $notif_context);

// 1. Get profile picture from the linked EMPLOYEE account
$stmt = $conn->prepare("
    SELECT u.profile_picture AS user_profile_picture, e.profile_picture AS employee_profile_picture
    FROM users u 
    LEFT JOIN employees e ON u.employee_id = e.employee_id 
    WHERE u.user_id = ? 
    LIMIT 1
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$res = $stmt->get_result();
$display_avatar = getEmployeeAvatar(''); // Default
if ($row = $res->fetch_assoc()) {
    $display_avatar = getUserAvatar($row['user_profile_picture'], $row['employee_profile_picture']);
}
$stmt->close();

// Define sidebar menus per role
$sidebar_menus = [];

switch ($effective_role) {
    case 'Admin':
        $sidebar_menus = [
            'MAIN' => [
                ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => BASE_URL . '/admin/dashboard.php', 'page' => 'dashboard.php'],
            ],
            'MANAGEMENT' => [
                ['icon' => 'fas fa-id-badge', 'label' => 'Member List', 'url' => BASE_URL . '/admin/members.php', 'page' => 'members.php'],
                ['icon' => 'fas fa-user-lock', 'label' => 'Portal Accounts', 'url' => BASE_URL . '/admin/employee-accounts.php', 'page' => 'employee-accounts.php'],
                ['icon' => 'fas fa-users', 'label' => 'User Management', 'url' => BASE_URL . '/admin/users.php', 'page' => 'users.php'],
                ['icon' => 'fas fa-clipboard-list', 'label' => 'Audit Trail', 'url' => BASE_URL . '/admin/audit-trail.php', 'page' => 'audit-trail.php'],
            ],
            'SYSTEM' => [
                ['icon' => 'fas fa-database', 'label' => 'System Backup', 'url' => BASE_URL . '/admin/backup.php', 'page' => 'backup.php'],
                ['icon' => 'fas fa-cogs', 'label' => 'System Config', 'url' => BASE_URL . '/admin/config.php', 'page' => 'config.php'],
            ],
        ];
        break;

    case 'HR Manager':
        // Count pending career movements for badge (HR Portal pending + Portal Requests at Pending_HR_Manager)
        $_mgr_career_pending = 0;
        try {
            $r = $conn->query("SELECT COUNT(*) AS c FROM career_movements
                WHERE (approval_status = 'Pending' AND portal_workflow_stage IS NULL)
                   OR (request_source = 'Employee Portal' AND portal_workflow_stage = 'Pending_HR_Manager')");
            if ($r) $_mgr_career_pending = (int)($r->fetch_assoc()['c'] ?? 0);
        } catch (Exception $e) {}
        $_mgr_pkg_pending = countPendingOrganizationPackagesForUser($conn, (int)($_SESSION['user_id'] ?? 0));

        $sidebar_menus = [
            'MAIN' => [
                ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => BASE_URL . '/manager/dashboard.php', 'page' => 'dashboard.php'],
            ],
            'EMPLOYEES' => [
                ['icon' => 'fas fa-users', 'label' => 'Employees', 'url' => BASE_URL . '/manager/employees.php', 'page' => 'employees.php'],
                ['icon' => 'fas fa-user-plus', 'label' => 'Add Employee', 'url' => BASE_URL . '/manager/add-employee.php', 'page' => 'add-employee.php'],
            ],
            'ORGANIZATION' => [
                ['icon' => 'fas fa-building', 'label' => 'Branches', 'url' => BASE_URL . '/manager/branches.php', 'page' => 'branches.php'],
                ['icon' => 'fas fa-sitemap', 'label' => 'Departments', 'url' => BASE_URL . '/manager/departments.php', 'page' => 'departments.php'],
                ['icon' => 'fas fa-briefcase', 'label' => 'Positions', 'url' => BASE_URL . '/manager/positions.php', 'page' => 'positions.php'],
                ['icon' => 'fas fa-landmark', 'label' => 'Evaluation Governance', 'url' => BASE_URL . '/manager/evaluation-governance.php', 'page' => 'evaluation-governance.php'],
                // ['icon' => 'fas fa-project-diagram', 'label' => 'Operation Management', 'url' => BASE_URL . '/manager/operation-management.php', 'page' => 'operation-management.php'],
            ],
            'EVALUATIONS' => [
                ['icon' => 'fas fa-file-alt', 'label' => 'Templates', 'url' => BASE_URL . '/manager/templates.php', 'page' => 'templates.php'],
                ['icon' => 'fas fa-layer-group', 'label' => 'Team Evaluation Packages', 'url' => BASE_URL . '/employee/team-evaluation-packages.php', 'page' => 'team-evaluation-packages.php',
                 'badge' => $_mgr_pkg_pending ?: null, 'badge_class' => 'bg-warning text-dark'],
                ['icon' => 'fas fa-check-double', 'label' => 'Pending Approvals', 'url' => BASE_URL . '/manager/pending-approvals.php', 'page' => 'pending-approvals.php',
                 'badge' => (function() use ($conn) {
                     try { $r = $conn->query("SELECT COUNT(*) as c FROM employee_change_requests WHERE status='Pending'"); return $r ? (int)($r->fetch_assoc()['c'] ?? 0) : 0; } catch(Exception $e){ return 0; }
                 })(), 'badge_class' => 'bg-warning text-dark'],
                ['icon' => 'fas fa-history', 'label' => 'Evaluation History', 'url' => BASE_URL . '/manager/evaluation-history.php', 'page' => 'evaluation-history.php'],
            ],

            'CAREER' => [
                ['icon' => 'fas fa-route', 'label' => 'Career Movements', 'url' => BASE_URL . '/manager/career-movements.php', 'page' => 'career-movements.php',
                 'badge' => $_mgr_career_pending ?: null, 'badge_class' => 'bg-warning text-dark'],
                ['icon' => 'fas fa-user-tie', 'label' => 'Succession Planning', 'url' => BASE_URL . '/manager/succession-planning.php', 'page' => 'succession-planning.php'],
            ],
            'ANALYTICS' => [
                ['icon' => 'fas fa-chart-bar', 'label' => 'Analytics', 'url' => BASE_URL . '/manager/analytics.php', 'page' => 'analytics.php'],
                ['icon' => 'fas fa-clipboard-list', 'label' => 'Audit Trail', 'url' => BASE_URL . '/manager/audit-trail.php', 'page' => 'audit-trail.php'],
            ],
        ];
        break;

    case 'HR Supervisor':
        // Count pending career movements for badge (HR Portal pending + Portal Requests at Pending_HR_Supervisor)
        $_sup_career_pending = 0;
        try {
            $r = $conn->query("SELECT COUNT(*) AS c FROM career_movements
                WHERE (approval_status = 'Pending' AND (portal_workflow_stage IS NULL OR request_source = 'HR Portal'))
                   OR (request_source = 'Employee Portal' AND portal_workflow_stage = 'Pending_HR_Supervisor')");
            if ($r) $_sup_career_pending = (int)($r->fetch_assoc()['c'] ?? 0);
        } catch (Exception $e) {}
        $_sup_pkg_pending = countPendingOrganizationPackagesForUser($conn, (int)($_SESSION['user_id'] ?? 0));

        $sidebar_menus = [
            'MAIN' => [
                ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => BASE_URL . '/supervisor/dashboard.php', 'page' => 'dashboard.php'],
            ],
            'EMPLOYEES' => [
                ['icon' => 'fas fa-address-book', 'label' => 'Employee Info', 'url' => BASE_URL . '/supervisor/employees.php', 'page' => 'employees.php'],
                ['icon' => 'fas fa-user-plus', 'label' => 'Add Employee', 'url' => BASE_URL . '/supervisor/add-employee.php', 'page' => 'add-employee.php'],
            ],
            'EVALUATIONS' => [
                ['icon' => 'fas fa-layer-group', 'label' => 'Team Evaluation Packages', 'url' => BASE_URL . '/employee/team-evaluation-packages.php', 'page' => 'team-evaluation-packages.php',
                 'badge' => $_sup_pkg_pending ?: null, 'badge_class' => 'bg-warning text-dark'],
                ['icon' => 'fas fa-clipboard-check', 'label' => 'Pending Validations', 'url' => BASE_URL . '/supervisor/pending-endorsements.php', 'page' => 'pending-endorsements.php'],
                ['icon' => 'fas fa-history', 'label' => 'Evaluation History', 'url' => BASE_URL . '/supervisor/evaluation-history.php', 'page' => 'evaluation-history.php'],
            ],
            'CAREER' => [
                ['icon' => 'fas fa-route', 'label' => 'Career Movements', 'url' => BASE_URL . '/supervisor/career-movements.php', 'page' => 'career-movements.php',
                 'badge' => $_sup_career_pending ?: null, 'badge_class' => 'bg-warning text-dark'],
                ['icon' => 'fas fa-chart-line', 'label' => 'Career Progression', 'url' => BASE_URL . '/supervisor/career-progression.php', 'page' => 'career-progression.php'],
            ],
            'ANALYTICS' => [
                ['icon' => 'fas fa-chart-bar', 'label' => 'Branch Analytics', 'url' => BASE_URL . '/supervisor/analytics.php', 'page' => 'analytics.php'],
                ['icon' => 'fas fa-clipboard-list', 'label' => 'My Audit Trail', 'url' => BASE_URL . '/supervisor/audit-trail.php', 'page' => 'audit-trail.php'],
            ],
        ];
        break;

    case 'HR Staff':
        // Count pending change requests for badge
        $staff_pending_ecr = 0;
        if ($conn) {
            try {
                $ecr_res = $conn->query("SELECT COUNT(*) as c FROM employee_change_requests WHERE status = 'Pending'");
                if ($ecr_res) $staff_pending_ecr = (int)($ecr_res->fetch_assoc()['c'] ?? 0);
            } catch (Exception $e) {}
        }
        $sidebar_menus = [
            'MAIN' => [
                ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => BASE_URL . '/staff/dashboard.php', 'page' => 'dashboard.php'],
            ],
            'EMPLOYEES' => [
                ['icon' => 'fas fa-users', 'label' => 'Employees', 'url' => BASE_URL . '/staff/employees.php', 'page' => 'employees.php',
                 'badge' => $staff_pending_ecr > 0 ? $staff_pending_ecr : null, 'badge_class' => 'bg-warning text-dark'],
                ['icon' => 'fas fa-building', 'label' => 'Branches & Roster', 'url' => BASE_URL . '/staff/branches.php', 'page' => 'branches.php'],
            ],
            'EVALUATIONS & MONITORING' => [
                ['icon' => 'fas fa-tasks', 'label' => 'Package Tracker', 'url' => BASE_URL . '/staff/package-tracker.php', 'page' => 'package-tracker.php'],
                ['icon' => 'fas fa-file-alt', 'label' => 'Templates', 'url' => BASE_URL . '/staff/templates.php', 'page' => 'templates.php'],
                ['icon' => 'fas fa-history', 'label' => 'Evaluation History', 'url' => BASE_URL . '/staff/evaluation-history.php', 'page' => 'evaluation-history.php'],
            ],
            'CAREER' => [
                ['icon' => 'fas fa-route', 'label' => 'Career Movements', 'url' => BASE_URL . '/staff/career-movements.php', 'page' => 'career-movements.php'],
            ],
            'SYSTEM' => [
                ['icon' => 'fas fa-clipboard-list', 'label' => 'My Audit Trail', 'url' => BASE_URL . '/staff/audit-trail.php', 'page' => 'audit-trail.php'],
            ],
        ];
        break;

    case 'Employee':

        // Count employee portal work items for sidebar and mobile bottom-nav badges.
        $m_pending_template_count = 0;
        $m_eval_status_count = 0;
        $m_dept_review_count = 0;
        $m_confirm_rating_count = 0;
        $m_hr_review_count = 0;
        $is_dept_manager_menu = false;
        $is_supervisor_menu  = false;
        $hdr_sup_dept_name   = '';
        $_hdr_viewer_hr_role = null;
        if (isset($_SESSION['employee_id']) && $conn) {
            $_hdr_emp_id = (int) $_SESSION['employee_id'];
            $_hdr_dept_stmt = $conn->prepare("
                SELECT d.department_name, e.branch_id, e.rank_category_id, e.employment_status
                FROM employees e 
                LEFT JOIN departments d ON e.department_id = d.department_id 
                WHERE e.employee_id = ? 
                LIMIT 1
            ");
            $_hdr_dept_stmt->bind_param("i", $_hdr_emp_id);
            $_hdr_dept_stmt->execute();
            $_hdr_dept_row = $_hdr_dept_stmt->get_result()->fetch_assoc();
            $_hdr_emp_dept = $_hdr_dept_row['department_name'] ?? '';
            $_hdr_emp_branch_id = $_hdr_dept_row ? (int)$_hdr_dept_row['branch_id'] : 0;
            $_hdr_emp_rank = $_hdr_dept_row ? (int)$_hdr_dept_row['rank_category_id'] : 0;
            $_hdr_emp_status = $_hdr_dept_row['employment_status'] ?? 'Regular';
            $_hdr_dept_stmt->close();

            $_hdr_is_non_regular = in_array($_hdr_emp_status, ['OJT', 'Probationary', 'Project Based', 'Project-Based', 'Trainee'], true);
            $_hdr_allowed_eval_types = $_hdr_is_non_regular ? ['Initial', 'Final'] : ['Annual', 'Quarterly', 'Final'];
            $_hdr_in_clause = "'" . implode("','", $_hdr_allowed_eval_types) . "'";

            $_hdr_pt_stmt = $conn->prepare("
                SELECT COUNT(*) AS total
                FROM evaluation_templates et
                WHERE et.status = 'Active'
                  AND et.deleted_at IS NULL
                  AND (et.target_department IS NULL OR et.target_department = '' OR et.target_department = 'All Departments' OR et.target_department = ?)
                  AND et.evaluation_type IN ($_hdr_in_clause)
                  AND NOT EXISTS (
                      SELECT 1
                      FROM evaluations ev
                      WHERE ev.employee_id = ?
                        AND ev.template_id = et.template_id
                        AND ev.deleted_at IS NULL
                        AND ev.status NOT IN ('Draft', 'Returned', 'Rejected', 'Pending Self-Rating')
                  )
            ");
            $_hdr_pt_stmt->bind_param("si", $_hdr_emp_dept, $_hdr_emp_id);
            $_hdr_pt_stmt->execute();
            $m_pending_template_count = (int) ($_hdr_pt_stmt->get_result()->fetch_assoc()['total'] ?? 0);
            $_hdr_pt_stmt->close();

            if (ensureEmployeeEvaluationStatusViewSchema($conn)) {
                $_hdr_status_stmt = $conn->prepare("
                    SELECT COUNT(*) AS total
                    FROM evaluations ev
                    LEFT JOIN employee_evaluation_status_views esv ON esv.employee_id = ev.employee_id
                    WHERE ev.employee_id = ?
                      AND ev.status IN ('Approved', 'Rejected', 'Returned')
                      AND ev.deleted_at IS NULL
                      AND (
                          esv.last_viewed_at IS NULL
                          OR COALESCE(ev.updated_at, ev.submitted_date, ev.created_at) > esv.last_viewed_at
                      )
                ");
            } else {
                $_hdr_status_stmt = $conn->prepare("
                    SELECT COUNT(*) AS total
                    FROM evaluations
                    WHERE employee_id = ?
                      AND status IN ('Approved', 'Rejected', 'Returned')
                      AND deleted_at IS NULL
                ");
            }
            $_hdr_status_stmt->bind_param("i", $_hdr_emp_id);
            $_hdr_status_stmt->execute();
            $m_eval_status_count = (int) ($_hdr_status_stmt->get_result()->fetch_assoc()['total'] ?? 0);
            $_hdr_status_stmt->close();

            $is_dept_manager_menu = isDeptManagerRole($conn, $_hdr_emp_id);
            if ($is_dept_manager_menu) {
                $_hdr_dept_pending_stmt = $conn->prepare("
                    SELECT ev.evaluation_id, ev.employee_id
                    FROM evaluations ev
                    JOIN employees emp ON ev.employee_id = emp.employee_id
                    WHERE ev.status = 'Pending Dept Manager'
                      AND ev.deleted_at IS NULL
                      AND emp.is_active = 1
                      AND emp.deleted_at IS NULL
                ");
                $_hdr_dept_pending_stmt->execute();
                $_hdr_dept_pending_rows = $_hdr_dept_pending_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $_hdr_dept_pending_stmt->close();
                foreach ($_hdr_dept_pending_rows as $_hdr_pending) {
                    if (isDeptManagerOfEmployee($conn, (int)$_SESSION['user_id'], (int)$_hdr_pending['employee_id'])) {
                        $m_dept_review_count++;
                    }
                }
            }

            $is_supervisor_menu = hasSupervisorPrivileges($conn, $_hdr_emp_id);
            if ($is_supervisor_menu) {
                $hdr_sup_dept_name = $_hdr_emp_dept;
                $_hdr_confirm_stmt = $conn->prepare("
                    SELECT ev.evaluation_id, ev.employee_id, emp.reports_to, emp.branch_id, emp.rank_category_id
                    FROM evaluations ev
                    JOIN employees emp ON ev.employee_id = emp.employee_id
                    WHERE emp.employee_id <> ?
                      AND ev.status IN ('Pending Dept Supervisor','Pending Supervisor')
                      AND ev.deleted_at IS NULL
                      AND emp.is_active = 1
                      AND emp.deleted_at IS NULL
                ");
                $_hdr_confirm_stmt->bind_param("i", $_hdr_emp_id);
                $_hdr_confirm_stmt->execute();
                $_hdr_confirm_rows = $_hdr_confirm_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $_hdr_confirm_stmt->close();

                $_hdr_active_reports_to_cache = [];
                foreach ($_hdr_confirm_rows as $_hdr_confirm_row) {
                    if (isSupervisorOfEmployee($conn, (int)$_SESSION['user_id'], (int)$_hdr_confirm_row['employee_id'])) {
                        $m_confirm_rating_count++;
                    }
                }
            }

            // Resolve HR role for use elsewhere in this block
            $_hdr_viewer_hr_role = getEmployeeHRRole($conn, $_hdr_emp_id);
        }

        // ── Section 1: My Profile ───────────────────────────────────────────
        $menu_my_profile = [
            ['icon' => 'fas fa-briefcase', 'label' => 'My Employment', 'url' => BASE_URL . '/employee/my-employment.php', 'page' => 'my-employment.php'],
        ];

        // ── Section 2: Evaluations ──────────────────────────────────────────
        $menu_evaluations = [
            ['icon' => 'fas fa-star',        'label' => 'Self Rating',        'url' => BASE_URL . '/employee/self-rating.php',      'page' => 'self-rating.php',      'badge' => $m_pending_template_count],
            ['icon' => 'fas fa-history',     'label' => 'Evaluation History', 'url' => BASE_URL . '/employee/evaluation-history.php', 'page' => 'evaluation-history.php'],
            ['icon' => 'fas fa-chart-line',  'label' => 'My Performance',     'url' => BASE_URL . '/employee/my-performance.php',   'page' => 'my-performance.php'],
        ];

        // ── Section 3: My Team (supervisors/managers & assigned package reviewers) ──
        $m_pending_pkg_count = countPendingOrganizationPackagesForUser($conn, (int)($_SESSION['user_id'] ?? 0));
        $menu_my_team = [];
        $is_hr_personnel = (strcasecmp($_hdr_emp_dept ?? '', 'Human Resources') === 0) 
                            || !empty($_hdr_viewer_hr_role) 
                            || in_array($_SESSION['role'] ?? '', ['HR Manager', 'HR Supervisor', 'HR Staff'], true);
        if (!$is_hr_personnel && ($is_supervisor_menu || $m_pending_pkg_count > 0)) {
            $menu_my_team[] = ['icon' => 'fas fa-users',       'label' => 'My Team',                  'url' => BASE_URL . '/employee/team-list.php',              'page' => 'team-list.php'];
            $menu_my_team[] = ['icon' => 'fas fa-layer-group', 'label' => 'Team Evaluation Packages', 'url' => BASE_URL . '/employee/team-evaluation-packages.php', 'page' => 'team-evaluation-packages.php', 'badge' => $m_pending_pkg_count ?: null, 'badge_class' => 'bg-warning text-dark'];
            $menu_my_team[] = ['icon' => 'fas fa-history',     'label' => 'Team Evaluation History',  'url' => BASE_URL . '/employee/team-evaluation-history.php', 'page' => 'team-evaluation-history.php'];
        }

        // ── Section 4: Career (rank-based) ─────────────────────────────────
        $menu_career = [];
        // Branch Supervisor (rank 4): can submit Transfer requests
        if ($_hdr_emp_rank === 4) {
            $menu_career[] = ['icon' => 'fas fa-route', 'label' => 'Career Movement Request', 'url' => BASE_URL . '/employee/career-movement-request.php', 'page' => 'career-movement-request.php'];
        }
        // Branch Manager (rank 3): can approve/reject Transfer requests from their branch
        if ($_hdr_emp_rank === 3) {
            // Count pending BM approvals for badge — guarded in case schema migration hasn't run yet
            $_hdr_bm_pending = 0;
            if ($_hdr_emp_branch_id > 0) {
                try {
                    $_hdr_bm_stmt = $conn->prepare(
                        "SELECT COUNT(*) AS cnt FROM career_movements
                         WHERE portal_workflow_stage = 'Pending_Branch_Manager'
                           AND previous_branch_id = ? AND request_source = 'Employee Portal'"
                    );
                    $_hdr_bm_stmt->bind_param("i", $_hdr_emp_branch_id);
                    $_hdr_bm_stmt->execute();
                    $_hdr_bm_pending = (int)($_hdr_bm_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
                    $_hdr_bm_stmt->close();
                } catch (mysqli_sql_exception $e) {
                    // Column not yet migrated — badge shows 0
                    $_hdr_bm_pending = 0;
                }
            }
            $menu_career[] = ['icon' => 'fas fa-clipboard-check', 'label' => 'Transfer Approvals', 'url' => BASE_URL . '/employee/branch-manager-approvals.php', 'page' => 'branch-manager-approvals.php', 'badge' => $_hdr_bm_pending];
        }

        // ── Build sidebar with grouped sections ─────────────────────────────
        $sidebar_menus = [
            'MAIN' => [
                ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => BASE_URL . '/employee/dashboard.php', 'page' => 'dashboard.php'],
            ],
            'MY PROFILE' => $menu_my_profile,
            'EVALUATIONS' => $menu_evaluations,
        ];

        // Only add MY TEAM section when there are items to show
        if (!empty($menu_my_team)) {
            $sidebar_menus['MY TEAM'] = $menu_my_team;
        }

        // Only add CAREER section when there are items to show
        if (!empty($menu_career)) {
            $sidebar_menus['CAREER'] = $menu_career;
        }

        $sidebar_menus['ACCOUNT'] = [
            ['icon' => 'fas fa-bell',     'label' => 'Notifications', 'url' => BASE_URL . '/employee/notifications.php',   'page' => 'notifications.php'],
            ['icon' => 'fas fa-user-cog', 'label' => 'Change Password', 'url' => BASE_URL . '/employee/profile-settings.php', 'page' => 'profile-settings.php'],
        ];
        break;

}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title ?? 'Dashboard'); ?> - Raquel Pawnshop HRIS</title>
    <meta name="description" content="Raquel Pawnshop Human Resource Information System">
    <meta name="csrf-token" content="<?php echo generateCsrfToken(); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">
    <?php if (in_array($effective_role, ['HR Manager', 'HR Supervisor', 'HR Staff', 'Admin'])): ?>
    <!-- HR Department Mobile View — exclusive CSS for HR roles on mobile -->
    <link href="<?php echo BASE_URL; ?>/assets/css/hr-department-mobile.css?v=<?php echo time(); ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if ($effective_role === 'Employee'): ?>
    <!-- Employee Portal UX Revamp CSS — loaded for Employee role only -->
    <!-- Critical CSS inlined for above-the-fold render speed (Task 24.4) -->
    <style>
    *,*::before,*::after{box-sizing:border-box;max-width:100%;}
    .skip-navigation{position:absolute;top:-100px;left:0;background:#082E06;color:#fff;padding:.75rem 1.5rem;text-decoration:none;font-weight:600;z-index:10000;border-radius:0 0 8px 0;}
    .skip-navigation:focus{top:0;}
    @media(min-width:768px){.main-content{margin-left:260px;padding:1.5rem;}.top-navbar{left:260px;}}
    @media(max-width:767px){.main-content{padding:1rem;}}
    </style>
    <link rel="preload" href="<?php echo BASE_URL; ?>/assets/css/employee-portal-variables.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="<?php echo BASE_URL; ?>/assets/css/employee-portal-variables.css" rel="stylesheet"></noscript>
    <link href="<?php echo BASE_URL; ?>/assets/css/employee-portal-variables.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/employee-portal-layout.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/employee-portal-navigation.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/employee-portal-cards.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/employee-portal-forms.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/employee-portal-buttons.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/employee-portal-ratings.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/employee-portal-progress.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/employee-portal-notifications.css?v=<?php echo time(); ?>" rel="stylesheet">
    <?php endif; ?>
    <!-- Feedback & Sound Effects System CSS — loaded for all roles -->
    <link href="<?php echo BASE_URL; ?>/assets/css/employee-portal-feedback.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/evaluation-packages.css?v=<?php echo time(); ?>" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/pjax.js?v=<?php echo time(); ?>" defer></script>
    <script>
        // Prevent FOUC for collapsed sidebar
        if (localStorage.getItem('sidebar_collapsed') === 'true') {
            document.documentElement.classList.add('sidebar-collapsed');
        }
        // Expose app base URL for shared JS utilities.
        window.APP_BASE_URL = <?php echo json_encode(BASE_URL); ?>;
        window.NOTIF_CONTEXT = <?php echo json_encode($notif_context === 'employee' ? 'employee' : 'hr'); ?>;
    </script>
</head>

<body class="<?php echo ($current_dir === 'admin' ? 'admin-area' : '') . ($effective_role === 'Employee' ? ' role-employee' : ''); ?>">

    <!-- ── Network Offline Banner ─────────────────────────────────────────────
         Shows when device loses connectivity (APK WebView + browser).
         Prevents white-screen freeze; auto-hides on reconnect.
    ──────────────────────────────────────────────────────────────────────── -->
    <div id="networkOfflineBanner" style="
        display:none;
        position:fixed;
        top:0;left:0;right:0;
        z-index:99999;
        background:#1a1a2e;
        color:#fff;
        padding:10px 16px;
        font-size:0.82rem;
        font-family:'Inter',sans-serif;
        font-weight:600;
        text-align:center;
        box-shadow:0 2px 12px rgba(0,0,0,.45);
        letter-spacing:.3px;
        align-items:center;
        justify-content:center;
        gap:10px;
        flex-wrap:wrap;
    ">
        <span id="networkOfflineIcon" style="font-size:1rem;">📵</span>
        <span id="networkOfflineMsg">No internet connection. Waiting to reconnect…</span>
        <button onclick="location.reload()" style="
            background:rgba(255,255,255,.15);
            border:1px solid rgba(255,255,255,.3);
            color:#fff;
            border-radius:20px;
            padding:4px 14px;
            font-size:0.78rem;
            font-weight:700;
            cursor:pointer;
        ">Retry</button>
    </div>
    <script>
    (function(){
        var banner = document.getElementById('networkOfflineBanner');
        var msg    = document.getElementById('networkOfflineMsg');
        var icon   = document.getElementById('networkOfflineIcon');
        function showOffline(){
            if(!banner) return;
            banner.style.display = 'flex';
            msg.textContent  = 'No internet connection. Waiting to reconnect…';
            icon.textContent = '📵';
        }
        function showOnline(){
            if(!banner) return;
            icon.textContent = '✅';
            msg.textContent  = 'Connection restored! Reloading…';
            setTimeout(function(){ banner.style.display='none'; }, 2000);
        }
        window.addEventListener('offline', showOffline);
        window.addEventListener('online',  showOnline);
        if(!navigator.onLine){ showOffline(); }
    })();
    </script>

    <?php if ($effective_role === 'Employee'): ?>
    <!-- Skip Navigation for accessibility (WCAG AAA) -->
    <a href="#main-content" class="skip-navigation">Skip to main content</a>
    <?php endif; ?>


    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="<?php echo BASE_URL . '/' . e($sys_logo); ?>" alt="Logo"
                style="width: 50px; height: 50px; border-radius: 12px; object-fit: cover; margin-bottom: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); background: white; border: 2px solid rgba(255,255,255,0.1);">
            <h2><?php echo e($sys_pawnshop_name); ?></h2>
            <?php if ($effective_role === 'Employee'): ?>
                <small>Your HRIS Employee Portal</small>
            <?php else: ?>
                <small>HRIS • <?php echo e($effective_role); ?></small>
            <?php endif; ?>
        </div>

        <nav class="sidebar-nav" id="sidebar-nav">
            <?php foreach ($sidebar_menus as $label => $items): ?>
                <div class="nav-label"><?php echo e($label); ?></div>
                <?php foreach ($items as $item): ?>
                    <?php
                    $classes = ($current_page === $item['page']) ? 'active' : '';
                    if (!empty($item['class']))
                        $classes .= ($classes ? ' ' : '') . $item['class'];
                    ?>
                    <a href="<?php echo $item['url']; ?>" class="<?php echo $classes; ?>"
                        title="<?php echo e($item['label']); ?>">
                        <i class="<?php echo $item['icon']; ?>"></i>
                        <span class="nav-text"><?php echo e($item['label']); ?></span>
                        <?php if (!empty($item['badge'])): ?>
                            <span class="badge rounded-pill <?php echo $item['badge_class'] ?? 'bg-danger'; ?> ms-auto"><?php echo (int)$item['badge'] > 9 ? '9+' : (int)$item['badge']; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <div class="nav-label">ACCOUNT</div>
            <a href="<?php echo BASE_URL; ?>/logout.php" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
                <span class="nav-text">Logout</span>
            </a>
        </nav>
    </aside>

    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Top Navbar -->
    <header class="top-navbar">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle <?php echo ($effective_role === 'Employee') ? 'd-none d-md-block' : (in_array($effective_role, ['HR Manager', 'HR Supervisor', 'HR Staff', 'Admin']) ? 'd-lg-block' : ''); ?>" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="navbar-logo d-flex align-items-center gap-2">
                <img src="<?php echo BASE_URL . '/' . e($sys_logo); ?>" alt="Logo"
                    style="width: 35px; height: 35px; border-radius: 8px; object-fit: cover;">
                <h1 class="page-title mb-0"><?php echo e($page_title ?? 'Dashboard'); ?></h1>
            </div>
        </div>

        <div class="nav-right">
            <!-- Notification Bell (visible on all screen sizes) -->
            <div class="dropdown">
                <button class="notification-btn" data-bs-toggle="dropdown" aria-expanded="false" id="notificationBtn">
                    <i class="fas fa-bell"></i>
                    <?php if ($notif_count > 0): ?>
                        <span class="notification-badge"><?php echo $notif_count > 9 ? '9+' : $notif_count; ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                    <div class="dropdown-header-bar">
                        <div class="dropdown-header-title">
                            <i class="fas fa-bell me-1" style="color:#CBA135;"></i> Notifications
                            <span class="notif-unread-pill" style="<?php echo $notif_count > 0 ? '' : 'display:none;'; ?>">
                                <?php echo $notif_count > 9 ? '9+ unread' : $notif_count . ' unread'; ?>
                            </span>
                        </div>
                        <a href="#" class="mark-all-btn" onclick="markAllRead(); return false;" title="Mark all as read">
                            <i class="fas fa-check-double me-1"></i>Mark all read
                        </a>
                    </div>
                    
                    <div class="notif-list-body">
                        <?php if (empty($notifications)): ?>
                            <div class="p-4 text-center text-muted" style="font-size:0.9rem;">
                                <i class="fas fa-bell-slash d-block mb-2" style="font-size:2rem;opacity:0.3;color:var(--primary-blue);"></i>
                                <div class="fw-semibold">You're all caught up!</div>
                                <div class="small opacity-75 mt-1">No notifications at the moment</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): 
                                $icon_info = getNotifIconInfo($notif['title']);
                            ?>
                                <a href="<?php echo e($notif['link'] ?? '#'); ?>"
                                    class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                                    <div class="notif-avatar <?php echo $icon_info['class']; ?>">
                                        <i class="<?php echo $icon_info['icon']; ?>"></i>
                                    </div>
                                    <div class="notif-content-area">
                                        <div class="notif-title"><?php echo e($notif['title']); ?></div>
                                        <div class="notif-message"><?php echo e($notif['message']); ?></div>
                                        <div class="notif-time"><i class="far fa-clock me-1"></i><?php echo timeAgoFormat($notif['created_at']); ?></div>
                                    </div>
                                    <?php if (!$notif['is_read']): ?>
                                        <div class="unread-dot" title="Unread"></div>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <?php
                    $current_portal = basename(dirname($_SERVER['SCRIPT_NAME']));
                    if (in_array($current_portal, ['employee', 'staff', 'manager', 'supervisor', 'admin'])) {
                        $notif_url = BASE_URL . '/' . $current_portal . '/notifications.php';
                    } else {
                        $role_map = [
                            'Admin' => 'admin',
                            'HR Manager' => 'manager',
                            'HR Supervisor' => 'supervisor',
                            'HR Staff' => 'staff',
                            'Employee' => 'employee'
                        ];
                        $portal_name = $role_map[$_SESSION['role'] ?? 'Employee'] ?? 'employee';
                        $notif_url = BASE_URL . '/' . $portal_name . '/notifications.php';
                    }
                    ?>
                    <div class="dropdown-footer-bar">
                        <a href="<?php echo $notif_url; ?>">
                            View All Notifications <i class="fas fa-arrow-right ms-1" style="font-size:0.75rem;"></i>
                        </a>
                    </div>
                </div>

            </div>

            <!-- User Dropdown -->
            <div class="dropdown user-dropdown <?php echo ($effective_role === 'Employee') ? 'd-none d-md-block' : ''; ?>">
                <button class="btn dropdown-toggle" data-bs-toggle="dropdown">
                    <div class="user-avatar">
                        <img src="<?php echo $display_avatar . '?v=' . time(); ?>" alt="Avatar">
                    </div>
                    <span class="d-none d-md-inline"><?php echo e($_SESSION['full_name']); ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text"><small
                                class="text-muted"><?php echo e($_SESSION['role']); ?></small></span></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <?php
                    $profile_settings_url = '';
                    if ($effective_role === 'HR Manager') {
                        $profile_settings_url = BASE_URL . '/manager/profile-settings.php';
                    } elseif ($effective_role === 'HR Supervisor') {
                        $profile_settings_url = BASE_URL . '/supervisor/profile-settings.php';
                    } elseif ($effective_role === 'HR Staff') {
                        $profile_settings_url = BASE_URL . '/staff/profile-settings.php';
                    } elseif ($effective_role === 'Employee') {
                        $profile_settings_url = BASE_URL . '/employee/profile-settings.php';
                    }
                    ?>
                    <?php if (!empty($profile_settings_url)): ?>
                        <li><a class="dropdown-item" href="<?php echo $profile_settings_url; ?>"><i
                                    class="fas fa-user-cog me-2"></i>Profile & Settings</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                    <?php endif; ?>
                    <button type="button" class="dropdown-item d-flex align-items-center justify-content-between w-100 border-0 bg-transparent sound-toggle-btn" id="soundToggleBtn" onclick="if(window.toggleUiSound) window.toggleUiSound();" style="font-size:0.82rem; padding: 6px 10px; cursor: pointer;">
                            <span><i class="fas fa-volume-up me-2 sound-icon text-success" style="width: 18px; text-align: center;"></i>Sound Effects</span>
                            <span class="badge bg-success sound-status-badge" style="font-size:0.7rem;">ON</span>
                    </button>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/logout.php"><i
                                class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>

            <?php if ($effective_role === 'Employee'): ?>
                <!-- Mobile Settings Gear Dropdown (shows on mobile only) -->
                <div class="dropdown d-md-none">
                    <button class="notification-btn" data-bs-toggle="dropdown" aria-expanded="false" id="mobileGearBtn" style="color: #074B02;">
                        <i class="fas fa-cog"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" id="mobileGearDropdown" style="border-radius: 14px; border: 1px solid rgba(0,0,0,0.08); min-width: 200px; max-width: calc(100vw - 24px); font-size: 0.84rem; padding: 6px;">
                        <li>
                            <span class="dropdown-item-text fw-bold d-flex align-items-center" style="font-size:0.8rem;color:var(--text-muted); padding: 6px 10px;">
                                <i class="fas fa-user-circle me-2"></i><?php echo e($_SESSION['full_name']); ?>
                            </span>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center" href="<?php echo BASE_URL; ?>/employee/dashboard.php" style="font-size:0.82rem; padding: 6px 10px;">
                                <i class="fas fa-tachometer-alt me-2" style="width: 18px; text-align: center;"></i>Dashboard
                            </a>
                        </li>
                        <?php
                        $_g_emp_id = (int)($_SESSION['employee_id'] ?? 0);
                        $_g_is_dept_mgr = false;
                        if ($_g_emp_id > 0 && $conn) {
                            $_g_is_dept_mgr = isDeptManagerRole($conn, $_g_emp_id);
                        }
                        if ($_g_is_dept_mgr):
                            $_g_dept_pending = $m_dept_review_count ?? 0;
                            if (!isset($m_dept_review_count) && $conn) {
                                $_g_dept_stmt = $conn->prepare("
                                    SELECT e.evaluation_id, e.employee_id
                                    FROM evaluations e
                                    JOIN employees emp ON e.employee_id = emp.employee_id
                                    WHERE e.status = 'Pending Dept Manager'
                                      AND e.deleted_at IS NULL
                                      AND emp.is_active = 1
                                      AND emp.deleted_at IS NULL
                                ");
                                if ($_g_dept_stmt) {
                                    $_g_dept_stmt->execute();
                                    $_g_pending_rows = $_g_dept_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                    $_g_dept_stmt->close();
                                    foreach ($_g_pending_rows as $_g_pending) {
                                        if (isDeptManagerOfEmployee($conn, (int)$_SESSION['user_id'], (int)$_g_pending['employee_id'])) {
                                            $_g_dept_pending++;
                                        }
                                    }
                                }
                            }
                        ?>
                        <?php endif; ?>
                        <?php
                        // Confirm Rating — for immediate heads outside HR department
                        $_g_is_sup = false;
                        $_g_dept_name = '';
                        if ($_g_emp_id > 0 && $conn) {
                            $_g_is_sup = hasSupervisorPrivileges($conn, $_g_emp_id);
                            if ($_g_is_sup) {
                                $_g_dep_r = $conn->query("SELECT d.department_name FROM employees e LEFT JOIN departments d ON e.department_id = d.department_id WHERE e.employee_id = $_g_emp_id LIMIT 1");
                                if ($_g_dep_r) {
                                    $_g_dept_name = $_g_dep_r->fetch_assoc()['department_name'] ?? '';
                                }
                            }
                        }
                        if ($_g_is_sup && !$_g_is_dept_mgr):
                            $_g_confirm_pending = $m_confirm_rating_count ?? 0;
                            if (!isset($m_confirm_rating_count) && $conn) {
                                $_g_c_stmt = $conn->prepare("
                                    SELECT ev.evaluation_id, ev.employee_id
                                    FROM evaluations ev
                                    JOIN employees e ON ev.employee_id = e.employee_id
                                    WHERE e.employee_id <> ?
                                      AND ev.status IN ('Pending Dept Supervisor','Pending Supervisor')
                                      AND ev.deleted_at IS NULL
                                      AND e.is_active = 1
                                      AND e.deleted_at IS NULL
                                ");
                                if ($_g_c_stmt) {
                                    $_g_c_stmt->bind_param('i', $_g_emp_id);
                                    $_g_c_stmt->execute();
                                    $_g_confirm_rows = $_g_c_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                    $_g_c_stmt->close();
                                    foreach ($_g_confirm_rows as $_g_confirm_row) {
                                        if (isSupervisorOfEmployee($conn, (int)$_SESSION['user_id'], (int)$_g_confirm_row['employee_id'])) {
                                            $_g_confirm_pending++;
                                        }
                                    }
                                }
                            }
                        ?>
                        <?php endif; ?>
                        <li>
                            <a class="dropdown-item d-flex align-items-center" href="<?php echo BASE_URL; ?>/employee/my-performance.php" style="font-size:0.82rem; padding: 6px 10px;">
                                <i class="fas fa-chart-line me-2" style="width: 18px; text-align: center;"></i>My Performance
                            </a>
                        </li>
                        <?php
                        // My Team & Movement Requests — for supervisors/managers
                        if ($_g_is_sup):
                        ?>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="<?php echo BASE_URL; ?>/employee/team-list.php" style="font-size:0.82rem; padding: 6px 10px;">
                                    <i class="fas fa-users me-2" style="width: 18px; text-align: center;"></i>My Team
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="<?php echo BASE_URL; ?>/employee/career-movement-request.php" style="font-size:0.82rem; padding: 6px 10px;">
                                    <i class="fas fa-route me-2" style="width: 18px; text-align: center;"></i>Movement Requests
                                </a>
                            </li>
                        <?php endif; ?>
                        <li>
                            <a class="dropdown-item d-flex align-items-center" href="<?php echo BASE_URL; ?>/employee/profile-settings.php" style="font-size:0.82rem; padding: 6px 10px;">
                                <i class="fas fa-user-cog me-2" style="width: 18px; text-align: center;"></i>Change Password
                            </a>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center justify-content-between w-100 border-0 bg-transparent sound-toggle-btn" id="soundToggleBtn" onclick="if(window.toggleUiSound) window.toggleUiSound();" style="font-size:0.82rem; padding: 6px 10px; cursor: pointer;">
                                <span><i class="fas fa-volume-up me-2 sound-icon text-success" style="width: 18px; text-align: center;"></i>Sound Effects</span>
                                <span class="badge bg-success sound-status-badge" style="font-size:0.7rem;">ON</span>
                            </button>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item text-danger d-flex align-items-center" href="<?php echo BASE_URL; ?>/logout.php" style="font-size:0.82rem; padding: 6px 10px;">
                                <i class="fas fa-sign-out-alt me-2" style="width: 18px; text-align: center;"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Content Wrapper -->
    <main class="main-content" id="main-content">
        <?php
        // Always consume the flash session to prevent it leaking to the next page.
        // Suppress the visual banner on pages that have their own inline feedback:
        //   - employee-accounts.php  → Portal Accounts (uses credential slip modal)
        //   - users.php              → User Management (has its own inline alerts)
        $suppress_flash_pages = ['employee-accounts.php', 'users.php'];
        if (isset($_SESSION['flash_message'])) {
            if (!in_array($current_page, $suppress_flash_pages, true)) {
                displayFlashMessage(); // renders and clears
            } else {
                unset($_SESSION['flash_type'], $_SESSION['flash_message']); // clear only
            }
        }

        // Auto-backup result toast
        if (!empty($_SESSION['auto_backup_toast'])) {
            $ab_toast = $_SESSION['auto_backup_toast'];
            unset($_SESSION['auto_backup_toast']);
            $ab_class = $ab_toast['type'] === 'success' ? 'success' : 'danger';
            $ab_icon  = $ab_toast['type'] === 'success' ? 'fas fa-check-circle' : 'fas fa-times-circle';
            echo "<div class='alert alert-{$ab_class} alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm' role='alert' style='border-radius:12px;margin-bottom:1rem;'>"
               . "<i class='{$ab_icon} fa-lg'></i>"
               . "<div><strong>Auto Backup:</strong> {$ab_toast['msg']}</div>"
               . "<button type='button' class='btn-close ms-auto' data-bs-dismiss='alert'></button>"
               . "</div>";
        }
        ?>
