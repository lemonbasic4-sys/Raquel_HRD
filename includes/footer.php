</main><!-- end .main-content -->



<?php 
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Employee'): 
    $curr_p = $current_page ?? basename($_SERVER['PHP_SELF']);
    $m_notif_count = $notif_count ?? 0;
?>
    <!-- Mobile Bottom Navigation (Strictly 4 Primary Shortcuts) -->
    <nav class="employee-bottom-nav d-md-none">
        <a href="<?php echo BASE_URL; ?>/employee/dashboard.php" class="nav-item <?php echo ($curr_p === 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-home nav-icon"></i>
            <span class="nav-label">Home</span>
        </a>
        <a href="<?php echo BASE_URL; ?>/employee/my-employment.php" class="nav-item <?php echo ($curr_p === 'my-employment.php') ? 'active' : ''; ?>">
            <i class="fas fa-briefcase nav-icon"></i>
            <span class="nav-label">Employment</span>
        </a>
        <a href="<?php echo BASE_URL; ?>/employee/self-rating.php" class="nav-item <?php echo ($curr_p === 'self-rating.php') ? 'active' : ''; ?>">
            <div class="position-relative">
                <i class="fas fa-star nav-icon"></i>
                <?php
                    $m_sr_count = $m_pending_template_count ?? 0;
                    if ($m_sr_count > 0):
                ?>
                    <span class="mobile-notif-badge"><?php echo $m_sr_count > 9 ? '9+' : $m_sr_count; ?></span>
                <?php endif; ?>
            </div>
            <span class="nav-label">Self Rating</span>
        </a>
        <a href="<?php echo BASE_URL; ?>/employee/evaluation-history.php" class="nav-item <?php echo ($curr_p === 'evaluation-history.php') ? 'active' : ''; ?>">
            <div class="position-relative">
                <i class="fas fa-history nav-icon"></i>
            </div>
            <span class="nav-label">History</span>
        </a>
    </nav>
<?php endif; ?>

<?php
/* ============================================================
   HR DEPARTMENT MOBILE BOTTOM NAVIGATION
   Rendered for: HR Manager, HR Supervisor, HR Staff, Admin
   Visible only on screens < 992px via d-lg-none.
   Active state is resolved client-side by hr-department-mobile.js.
   Live action badges use counts already computed in header.php.
============================================================ */
$_ft_role = $_SESSION['role'] ?? '';
$_ft_page = $current_page ?? basename($_SERVER['PHP_SELF']);
if (in_array($_ft_role, ['HR Manager', 'HR Supervisor', 'HR Staff', 'Admin'])):
?>

<!-- HR Department Mobile Bottom Navigation -->
<nav class="hr-bottom-nav d-lg-none" aria-label="HR Mobile Navigation">

<?php if ($_ft_role === 'HR Manager'): ?>
    <?php
    // Resolve pending approvals badge for HR Manager
    $_ft_mgr_pending = 0;
    if ($conn) {
        try {
            $r = $conn->query("SELECT COUNT(*) as c FROM employee_change_requests WHERE status='Pending'");
            if ($r) $_ft_mgr_pending = (int)($r->fetch_assoc()['c'] ?? 0);
        } catch (Exception $e) {}
    }
    ?>
    <a href="<?php echo BASE_URL; ?>/manager/dashboard.php"
       class="hr-nav-item<?php echo ($_ft_page === 'dashboard.php') ? ' active' : ''; ?>"
       data-page="dashboard.php"
       aria-label="Dashboard">
        <i class="fas fa-tachometer-alt hr-nav-icon"></i>
        <span class="hr-nav-label">Home</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/manager/employees.php"
       class="hr-nav-item<?php echo ($_ft_page === 'employees.php') ? ' active' : ''; ?>"
       data-page="employees.php"
       aria-label="Employees">
        <i class="fas fa-users hr-nav-icon"></i>
        <span class="hr-nav-label">Employees</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/manager/pending-approvals.php"
       class="hr-nav-item<?php echo ($_ft_page === 'pending-approvals.php') ? ' active' : ''; ?>"
       data-page="pending-approvals.php"
       aria-label="Pending Approvals">
        <div class="position-relative d-flex justify-content-center">
            <i class="fas fa-check-double hr-nav-icon"></i>
            <?php if ($_ft_mgr_pending > 0): ?>
                <span class="hr-mobile-badge"><?php echo $_ft_mgr_pending > 9 ? '9+' : $_ft_mgr_pending; ?></span>
            <?php endif; ?>
        </div>
        <span class="hr-nav-label">Approvals</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/manager/analytics.php"
       class="hr-nav-item<?php echo ($_ft_page === 'analytics.php') ? ' active' : ''; ?>"
       data-page="analytics.php"
       aria-label="Analytics">
        <i class="fas fa-chart-bar hr-nav-icon"></i>
        <span class="hr-nav-label">Analytics</span>
    </a>
    <!-- More Menu -->
    <button type="button" class="hr-nav-item" id="hrNavMoreBtn" aria-label="More options">
        <i class="fas fa-ellipsis-h hr-nav-icon"></i>
        <span class="hr-nav-label">More</span>
        <div class="hr-nav-more-dropdown" id="hrNavMoreMenu" role="menu">
            <a href="<?php echo BASE_URL; ?>/manager/branches.php"><i class="fas fa-building"></i>Branches</a>
            <a href="<?php echo BASE_URL; ?>/manager/departments.php"><i class="fas fa-sitemap"></i>Departments</a>
            <a href="<?php echo BASE_URL; ?>/manager/positions.php"><i class="fas fa-briefcase"></i>Positions</a>
            <a href="<?php echo BASE_URL; ?>/manager/templates.php"><i class="fas fa-file-alt"></i>Templates</a>
            <a href="<?php echo BASE_URL; ?>/manager/career-movements.php"><i class="fas fa-route"></i>Career Movements</a>
            <a href="<?php echo BASE_URL; ?>/manager/evaluation-history.php"><i class="fas fa-history"></i>Eval History</a>
            <a href="<?php echo BASE_URL; ?>/manager/audit-trail.php"><i class="fas fa-clipboard-list"></i>Audit Trail</a>
            <a href="<?php echo BASE_URL; ?>/manager/add-employee.php"><i class="fas fa-user-plus"></i>Add Employee</a>
            <hr>
            <a href="<?php echo BASE_URL; ?>/logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i>Logout</a>
        </div>
    </button>

<?php elseif ($_ft_role === 'HR Supervisor'): ?>
    <?php
    // Resolve pending endorsements badge for HR Supervisor
    $_ft_sup_pending = 0;
    if ($conn) {
        try {
            $r = $conn->query("
                SELECT COUNT(*) as c
                FROM evaluations ev
                INNER JOIN employees e ON ev.employee_id = e.employee_id
                WHERE ev.status IN ('Pending Supervisor', 'Pending HR Consolidation')
                  AND ev.deleted_at IS NULL
                  AND e.is_active = 1
                  AND e.employee_id NOT IN (
                      SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL
                  )
            ");
            if ($r) $_ft_sup_pending = (int)($r->fetch_assoc()['c'] ?? 0);
        } catch (Exception $e) {}
    }
    ?>
    <a href="<?php echo BASE_URL; ?>/supervisor/dashboard.php"
       class="hr-nav-item<?php echo ($_ft_page === 'dashboard.php') ? ' active' : ''; ?>"
       data-page="dashboard.php"
       aria-label="Dashboard">
        <i class="fas fa-tachometer-alt hr-nav-icon"></i>
        <span class="hr-nav-label">Home</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/supervisor/employees.php"
       class="hr-nav-item<?php echo ($_ft_page === 'employees.php') ? ' active' : ''; ?>"
       data-page="employees.php"
       aria-label="Employee Info">
        <i class="fas fa-address-book hr-nav-icon"></i>
        <span class="hr-nav-label">Employees</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/supervisor/pending-endorsements.php"
       class="hr-nav-item<?php echo ($_ft_page === 'pending-endorsements.php') ? ' active' : ''; ?>"
       data-page="pending-endorsements.php"
       aria-label="Pending Validations">
        <div class="position-relative d-flex justify-content-center">
            <i class="fas fa-clipboard-check hr-nav-icon"></i>
            <?php if ($_ft_sup_pending > 0): ?>
                <span class="hr-mobile-badge"><?php echo $_ft_sup_pending > 9 ? '9+' : $_ft_sup_pending; ?></span>
            <?php endif; ?>
        </div>
        <span class="hr-nav-label">Validations</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/supervisor/career-movements.php"
       class="hr-nav-item<?php echo ($_ft_page === 'career-movements.php') ? ' active' : ''; ?>"
       data-page="career-movements.php"
       aria-label="Career Movements">
        <i class="fas fa-route hr-nav-icon"></i>
        <span class="hr-nav-label">Movements</span>
    </a>
    <!-- More Menu -->
    <button type="button" class="hr-nav-item" id="hrNavMoreBtn" aria-label="More options">
        <i class="fas fa-ellipsis-h hr-nav-icon"></i>
        <span class="hr-nav-label">More</span>
        <div class="hr-nav-more-dropdown" id="hrNavMoreMenu" role="menu">
            <a href="<?php echo BASE_URL; ?>/supervisor/evaluation-history.php"><i class="fas fa-history"></i>Eval History</a>
            <a href="<?php echo BASE_URL; ?>/supervisor/analytics.php"><i class="fas fa-chart-bar"></i>Branch Analytics</a>
            <a href="<?php echo BASE_URL; ?>/supervisor/audit-trail.php"><i class="fas fa-clipboard-list"></i>My Audit Trail</a>
            <a href="<?php echo BASE_URL; ?>/supervisor/career-progression.php"><i class="fas fa-chart-line"></i>Career Progression</a>
            <hr>
            <a href="<?php echo BASE_URL; ?>/logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i>Logout</a>
        </div>
    </button>

<?php elseif ($_ft_role === 'HR Staff'): ?>
    <?php
    // Resolve pending Employee Change Requests badge for HR Staff
    $_ft_staff_ecr = 0;
    if ($conn) {
        try {
            $r = $conn->query("SELECT COUNT(*) as c FROM employee_change_requests WHERE status='Pending'");
            if ($r) $_ft_staff_ecr = (int)($r->fetch_assoc()['c'] ?? 0);
        } catch (Exception $e) {}
    }
    ?>
    <a href="<?php echo BASE_URL; ?>/staff/dashboard.php"
       class="hr-nav-item<?php echo ($_ft_page === 'dashboard.php') ? ' active' : ''; ?>"
       data-page="dashboard.php"
       aria-label="Dashboard">
        <i class="fas fa-tachometer-alt hr-nav-icon"></i>
        <span class="hr-nav-label">Home</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/staff/employees.php"
       class="hr-nav-item<?php echo ($_ft_page === 'employees.php') ? ' active' : ''; ?>"
       data-page="employees.php"
       aria-label="Employees">
        <div class="position-relative d-flex justify-content-center">
            <i class="fas fa-users hr-nav-icon"></i>
            <?php if ($_ft_staff_ecr > 0): ?>
                <span class="hr-mobile-badge"><?php echo $_ft_staff_ecr > 9 ? '9+' : $_ft_staff_ecr; ?></span>
            <?php endif; ?>
        </div>
        <span class="hr-nav-label">Employees</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/staff/evaluation-history.php"
       class="hr-nav-item<?php echo ($_ft_page === 'evaluation-history.php') ? ' active' : ''; ?>"
       data-page="evaluation-history.php"
       aria-label="Evaluation History">
        <i class="fas fa-history hr-nav-icon"></i>
        <span class="hr-nav-label">Evaluations</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/staff/career-movements.php"
       class="hr-nav-item<?php echo ($_ft_page === 'career-movements.php') ? ' active' : ''; ?>"
       data-page="career-movements.php"
       aria-label="Career Movements">
        <i class="fas fa-route hr-nav-icon"></i>
        <span class="hr-nav-label">Movements</span>
    </a>
    <!-- More Menu -->
    <button type="button" class="hr-nav-item" id="hrNavMoreBtn" aria-label="More options">
        <i class="fas fa-ellipsis-h hr-nav-icon"></i>
        <span class="hr-nav-label">More</span>
        <div class="hr-nav-more-dropdown" id="hrNavMoreMenu" role="menu">
            <a href="<?php echo BASE_URL; ?>/staff/package-tracker.php"><i class="fas fa-tasks"></i>Package Tracker</a>
            <a href="<?php echo BASE_URL; ?>/staff/templates.php"><i class="fas fa-file-alt"></i>Templates</a>
            <a href="<?php echo BASE_URL; ?>/staff/branches.php"><i class="fas fa-building"></i>Branches &amp; Roster</a>
            <a href="<?php echo BASE_URL; ?>/staff/add-employee.php"><i class="fas fa-user-plus"></i>Add Employee</a>
            <a href="<?php echo BASE_URL; ?>/staff/audit-trail.php"><i class="fas fa-clipboard-list"></i>My Audit Trail</a>
            <hr>
            <a href="<?php echo BASE_URL; ?>/logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i>Logout</a>
        </div>
    </button>

<?php elseif ($_ft_role === 'Admin'): ?>
    <a href="<?php echo BASE_URL; ?>/admin/dashboard.php"
       class="hr-nav-item<?php echo ($_ft_page === 'dashboard.php') ? ' active' : ''; ?>"
       data-page="dashboard.php"
       aria-label="Dashboard">
        <i class="fas fa-tachometer-alt hr-nav-icon"></i>
        <span class="hr-nav-label">Dashboard</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/members.php"
       class="hr-nav-item<?php echo ($_ft_page === 'members.php') ? ' active' : ''; ?>"
       data-page="members.php"
       aria-label="Members">
        <i class="fas fa-id-badge hr-nav-icon"></i>
        <span class="hr-nav-label">Members</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/employee-accounts.php"
       class="hr-nav-item<?php echo ($_ft_page === 'employee-accounts.php') ? ' active' : ''; ?>"
       data-page="employee-accounts.php"
       aria-label="Portal Accounts">
        <i class="fas fa-user-lock hr-nav-icon"></i>
        <span class="hr-nav-label">Accounts</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/users.php"
       class="hr-nav-item<?php echo ($_ft_page === 'users.php') ? ' active' : ''; ?>"
       data-page="users.php"
       aria-label="User Management">
        <i class="fas fa-users hr-nav-icon"></i>
        <span class="hr-nav-label">Users</span>
    </a>
    <!-- More Menu -->
    <button type="button" class="hr-nav-item" id="hrNavMoreBtn" aria-label="More options">
        <i class="fas fa-ellipsis-h hr-nav-icon"></i>
        <span class="hr-nav-label">System</span>
        <div class="hr-nav-more-dropdown" id="hrNavMoreMenu" role="menu">
            <a href="<?php echo BASE_URL; ?>/admin/audit-trail.php"><i class="fas fa-clipboard-list"></i>Audit Trail</a>
            <a href="<?php echo BASE_URL; ?>/admin/backup.php"><i class="fas fa-database"></i>System Backup</a>
            <a href="<?php echo BASE_URL; ?>/admin/config.php"><i class="fas fa-cogs"></i>System Config</a>
            <hr>
            <a href="<?php echo BASE_URL; ?>/logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i>Logout</a>
        </div>
    </button>
<?php endif; ?>

</nav><!-- end .hr-bottom-nav -->

<?php endif; /* end HR roles mobile nav */ ?>


<!-- Shared Image View Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true" style="background-color: rgba(0,0,0,0.85);">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0">
            <button type="button" class="btn-close btn-close-white p-2 rounded-circle shadow position-absolute" 
                style="top: 15px; right: 15px; z-index: 1100; background-color: rgba(0,0,0,0.6); border: none;"
                data-bs-dismiss="modal" aria-label="Close"></button>
            <div class="modal-body p-0 text-center">
                <img id="fullImage" src="" class="img-fluid rounded shadow" style="max-height: 85vh; border: 3px solid rgba(255,255,255,0.1); background-color: #ffffff;">
                <h6 id="fullImageName" class="text-white mt-3 fw-bold" style="text-shadow: 0 2px 4px rgba(0,0,0,0.5);"></h6>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Sidebar tooltip activation (collapsed sidebar icon labels for elderly users) ──
(function initSidebarTooltips() {
    function activateTooltips() {
        // Activate all elements with title="" in the sidebar nav (works even on collapsed mode)
        const sidebarLinks = document.querySelectorAll('.sidebar-nav a[title], .sidebar-nav [data-bs-toggle="tooltip"]');
        sidebarLinks.forEach(function(el) {
            // Only show tooltip when sidebar is collapsed (icon-only mode)
            var tooltip = new bootstrap.Tooltip(el, {
                placement: 'right',
                trigger: 'hover',
                container: 'body',
                customClass: 'sidebar-tooltip'
            });
            // Destroy tooltip when sidebar is expanded (label already visible)
            var observer = new MutationObserver(function() {
                var collapsed = document.documentElement.classList.contains('sidebar-collapsed');
                if (collapsed) {
                    tooltip.enable();
                } else {
                    tooltip.disable();
                }
            });
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
            // Set initial state
            var collapsed = document.documentElement.classList.contains('sidebar-collapsed');
            if (!collapsed) { tooltip.disable(); }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', activateTooltips);
    } else {
        activateTooltips();
    }
})();
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/zebra-stripe.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/main.js?v=<?php echo time(); ?>"></script>
<?php if (isset($_SESSION['role'])): ?>
<!-- Global Sound Effects & UX Feedback System — loaded for all HRIS & Admin roles -->
<script src="<?php echo BASE_URL; ?>/assets/js/employee-portal-feedback.js?v=<?php echo time(); ?>" defer></script>
<?php endif; ?>
<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Employee'): ?>
<!-- Employee Portal UX JS utilities — deferred for performance -->
<script src="<?php echo BASE_URL; ?>/assets/js/auto-save.js?v=<?php echo time(); ?>" defer></script>
<script src="<?php echo BASE_URL; ?>/assets/js/form-validation.js?v=<?php echo time(); ?>" defer></script>
<?php endif; ?>
<?php if (in_array($_SESSION['role'] ?? '', ['HR Manager', 'HR Supervisor', 'HR Staff', 'Admin'])): ?>
<!-- HR Department Mobile View JS — exclusive to HR roles -->
<script src="<?php echo BASE_URL; ?>/assets/js/hr-department-mobile.js?v=<?php echo time(); ?>" defer></script>
<?php endif; ?>
</body>
<!-- Global CSRF token injection for fetch() and jQuery AJAX -->
<script>
(function () {
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (!csrfMeta) return;
    var csrfToken = csrfMeta.getAttribute('content');
    if (!csrfToken) return;

    // Patch native fetch — auto-add header on non-GET requests
    var _origFetch = window.fetch;
    window.fetch = function (url, opts) {
        opts = opts || {};
        var method = (opts.method || 'GET').toUpperCase();
        if (method !== 'GET' && method !== 'HEAD') {
            opts.headers = Object.assign({}, opts.headers || {}, {
                'X-CSRF-Token': csrfToken
            });
        }
        return _origFetch.call(this, url, opts);
    };

    // Patch jQuery AJAX if loaded
    if (typeof $ !== 'undefined' && typeof $.ajaxSetup === 'function') {
        $.ajaxSetup({
            beforeSend: function (xhr, settings) {
                if (settings.type && settings.type.toUpperCase() !== 'GET') {
                    xhr.setRequestHeader('X-CSRF-Token', csrfToken);
                }
            }
        });
    }
})();
</script>
</html>
