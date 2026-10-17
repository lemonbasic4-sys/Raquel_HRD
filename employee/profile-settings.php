<?php
/**
 * Employee Portal — Profile Settings (Change Password)
 *
 * Tasks applied:
 *   14.1 — Logical sections, .content-card layout, .form-group wrapping, 1.5rem headings
 *   14.2 — .form-error containers after inputs; is-invalid on server-side errors
 *   14.3 — form-label above inputs, required-indicator * + aria-required="true"
 *   14.4 — input type="password", class="form-control", min-height 48px, placeholder text
 *   14.5 — Visual section completion indicator at the top
 */
$page_title = 'Profile Settings';
require_once '../includes/session-check.php';
checkRole(['Employee']);
require_once '../includes/functions.php';

$user_id = (int)$_SESSION['user_id'];
$success = '';

// Per-field validation errors
$field_errors = [
    'current_password' => '',
    'new_password'     => '',
    'confirm_password' => '',
];
// General (non-field-specific) error
$general_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current  = $_POST['current_password']  ?? '';
    $new      = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';

    // Field-level validation
    if ($current === '') {
        $field_errors['current_password'] = 'Current password is required.';
    }

    if ($new === '') {
        $field_errors['new_password'] = 'New password is required.';
    } elseif (strlen($new) < 6) {
        $field_errors['new_password'] = 'New password must be at least 6 characters.';
    }

    if ($confirm === '') {
        $field_errors['confirm_password'] = 'Please confirm your new password.';
    } elseif ($new !== '' && $new !== $confirm) {
        $field_errors['confirm_password'] = 'New passwords do not match.';
    }

    // Only hit the DB if field-level checks pass
    if ($field_errors['current_password'] === '' && $field_errors['new_password'] === '' && $field_errors['confirm_password'] === '') {
        // Fetch current hash
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!password_verify($current, $row['password_hash'])) {
            $field_errors['current_password'] = 'Current password is incorrect.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $upd  = $conn->prepare("UPDATE users SET password_hash=?, must_change_password=0, first_login_completed=1 WHERE user_id=?");
            $upd->bind_param("si", $hash, $user_id);
            if ($upd->execute()) {
                $_SESSION['must_change_password'] = false;
                $_SESSION['first_login_completed'] = true;
                logAudit($conn, $user_id, 'CHANGE_PASSWORD', 'User', $user_id, 'Employee changed their password.');
                $employee_name = trim((string) ($_SESSION['full_name'] ?? 'An employee'));
                notifyOtherAdmins(
                    $conn,
                    $user_id,
                    'Employee Password Changed',
                    $employee_name . ' changed their Employee Portal password.',
                    BASE_URL . '/admin/audit-trail.php',
                    'security'
                );
                $success = 'Password changed successfully!';
            } else {
                $general_error = 'Failed to update password. Please try again.';
            }
            $upd->close();
        }
    }
}

// Helper: returns 'is-invalid' if a field has an error (after POST)
function fieldInvalidClass(string $field, array $errors): string {
    return ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors[$field] !== '') ? ' is-invalid' : '';
}

// ── 14.5 Section completion logic ──────────────────────────────────────────
// "Account Info" section — always complete (read-only display data)
$section_account_complete = true;

// "Change Password" section — complete only when the form was submitted
// successfully (no pending errors) and a password was just changed.
$section_password_complete = ($success !== '');

// Determine overall form errors exist
$has_any_field_error = array_filter($field_errors, fn($e) => $e !== '');
// ───────────────────────────────────────────────────────────────────────────

require_once '../includes/header.php';
?>

<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-0 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">Employee Portal · Settings</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-user-cog me-2" style="color:var(--primary-light);"></i>Profile Settings</h4>
            <p class="text-white-50 small mb-0 mt-2 d-none d-sm-block">Manage your account security and credentials</p>
        </div>
        <div class="d-none d-md-block">
            <a href="<?php echo BASE_URL; ?>/employee/dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<div class="d-md-none mb-4 fadeup" style="animation-delay: 0.1s;">
    <a href="<?php echo BASE_URL; ?>/employee/dashboard.php" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
        <i class="fas fa-arrow-left me-2"></i>Back to My Dashboard
    </a>
</div>


<!-- ══════════════════════════════════════════════════════════════════
     TASK 14.5 — Section Completion Indicator
     Shows which form sections have data (green ✓ = complete, amber ⚠ = incomplete)
     ══════════════════════════════════════════════════════════════════ -->
<div class="content-card fadeup-1 mb-4" aria-label="Form section completion status">
    <div class="content-card-header">
        <h2 class="content-card-title" style="font-size:1.5rem;">
            <i class="fas fa-tasks me-2" aria-hidden="true"></i>Settings Overview
        </h2>
    </div>
    <div class="content-card-body">
        <p class="text-muted mb-3" style="font-size:1rem;">
            Sections marked <strong style="color:var(--color-success);">complete</strong> have data saved.
            Sections marked <strong style="color:var(--color-warning);">incomplete</strong> need your attention.
        </p>
        <div class="d-flex flex-wrap gap-3" role="list" aria-label="Section completion status">

            <!-- Section 1: Account Information (always complete — read-only) -->
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3"
                 role="listitem"
                 aria-label="Account Information section: complete"
                 style="background:rgba(15,107,46,0.08); border:1.5px solid var(--color-success); min-height:48px;">
                <i class="fas fa-check-circle" aria-hidden="true" style="color:var(--color-success); font-size:1.25rem;"></i>
                <span style="font-weight:600; color:var(--color-success); font-size:1rem;">Account Information</span>
            </div>

            <!-- Section 2: Change Password — complete after successful submit, else incomplete -->
            <?php if ($section_password_complete): ?>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3"
                 role="listitem"
                 aria-label="Change Password section: complete"
                 style="background:rgba(15,107,46,0.08); border:1.5px solid var(--color-success); min-height:48px;">
                <i class="fas fa-check-circle" aria-hidden="true" style="color:var(--color-success); font-size:1.25rem;"></i>
                <span style="font-weight:600; color:var(--color-success); font-size:1rem;">Change Password</span>
            </div>
            <?php else: ?>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3"
                 role="listitem"
                 aria-label="Change Password section: incomplete"
                 style="background:rgba(127,92,0,0.08); border:1.5px solid var(--color-warning); min-height:48px;">
                <i class="fas fa-exclamation-triangle" aria-hidden="true" style="color:var(--color-warning); font-size:1.25rem;"></i>
                <span style="font-weight:600; color:var(--color-warning); font-size:1rem;">Change Password</span>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<div class="row g-4">

    <!-- ══════════════════════════════════════════════════════════════
         TASK 14.1 — Section 1: Account Information
         Read-only overview, .content-card layout, 1.5rem heading
         ══════════════════════════════════════════════════════════════ -->
    <div class="col-12 col-md-5">
        <div class="content-card fadeup-2">
            <div class="content-card-header">
                <!-- 14.1: Section heading at 1.5rem with clear visual separation -->
                <h2 class="content-card-title" style="font-size:1.5rem;">
                    <i class="fas fa-user me-2" aria-hidden="true"></i>Account Information
                </h2>
            </div>
            <div class="content-card-body">

                <!-- Fields (≤10 per section — this section has 3 read-only fields) -->

                <!-- 14.1 .form-group wrapping; 14.3 label above input -->
                <div class="form-group">
                    <!-- 14.3: form-label class, positioned above -->
                    <label for="display_name" class="form-label">Full Name</label>
                    <!-- 14.4: form-control class, min-height 48px -->
                    <input type="text"
                           id="display_name"
                           class="form-control"
                           value="<?php echo e($_SESSION['full_name'] ?? ''); ?>"
                           readonly
                           aria-readonly="true"
                           style="min-height:48px; background:var(--color-bg-secondary); cursor:default;"
                           placeholder="Your registered full name">
                </div>

                <div class="form-group">
                    <label for="display_role" class="form-label">Role</label>
                    <input type="text"
                           id="display_role"
                           class="form-control"
                           value="<?php echo e($_SESSION['role'] ?? ''); ?>"
                           readonly
                           aria-readonly="true"
                           style="min-height:48px; background:var(--color-bg-secondary); cursor:default;"
                           placeholder="Your system role">
                </div>

                <div class="form-group mb-0">
                    <label for="display_username" class="form-label">Username / Login</label>
                    <?php
                    // Fetch username for display
                    $ustmt = $conn->prepare("SELECT username FROM users WHERE user_id=? LIMIT 1");
                    $ustmt->bind_param("i", $user_id);
                    $ustmt->execute();
                    $urow = $ustmt->get_result()->fetch_assoc();
                    $ustmt->close();
                    $display_username = $urow['username'] ?? '';
                    ?>
                    <input type="text"
                           id="display_username"
                           class="form-control"
                           value="<?php echo e($display_username); ?>"
                           readonly
                           aria-readonly="true"
                           style="min-height:48px; background:var(--color-bg-secondary); cursor:default;"
                           placeholder="Your login username">
                    <small class="form-helper">Contact HR to update your username or account details.</small>
                </div>

            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         TASK 14.1 — Section 2: Change Password
         .content-card layout, .form-group wrapping, 1.5rem heading
         ══════════════════════════════════════════════════════════════ -->
    <div class="col-12 col-md-7">
        <div class="content-card fadeup-3">
            <div class="content-card-header">
                <!-- 14.1: Section heading at 1.5rem with clear visual separation -->
                <h2 class="content-card-title" style="font-size:1.5rem;">
                    <i class="fas fa-shield-alt me-2" aria-hidden="true"></i>Change Password
                </h2>
            </div>
            <div class="content-card-body">

                <!-- Success alert (ARIA live region — req 20.8) -->
                <?php if ($success): ?>
                <div class="alert alert-success border-0 shadow-sm mb-4" role="alert" aria-live="polite">
                    <i class="fas fa-check-circle me-2" aria-hidden="true"></i><?php echo e($success); ?>
                </div>
                <?php endif; ?>

                <!-- General error alert -->
                <?php if ($general_error): ?>
                <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert" aria-live="assertive">
                    <i class="fas fa-exclamation-circle me-2" aria-hidden="true"></i><?php echo e($general_error); ?>
                </div>
                <?php endif; ?>

                <form method="POST"
                      data-validate
                      aria-label="Change password form"
                      novalidate>

                    <!-- Fields (≤10 per section — this section has 3 password fields) -->

                    <!-- ── Field 1: Current Password ── -->
                    <!-- 14.1: .form-group wrapping -->
                    <div class="form-group">
                        <!-- 14.3: label with form-label class, positioned ABOVE input -->
                        <label for="current_password" class="form-label">
                            Current Password
                            <!-- 14.3: required-indicator asterisk -->
                            <span class="required-indicator" aria-label="required">*</span>
                        </label>
                        <!-- 14.4: type="password", class="form-control", min-height 48px, placeholder
                             14.2: is-invalid applied when server-side error exists -->
                        <input type="password"
                               name="current_password"
                               id="current_password"
                               class="form-control<?php echo fieldInvalidClass('current_password', $field_errors); ?>"
                               placeholder="Enter your current password"
                               required
                               aria-required="true"
                               autocomplete="current-password"
                               style="min-height:48px;"
                               aria-describedby="current_password_error">
                        <!-- 14.2: form-error container after input, ARIA live for screen readers -->
                        <div class="form-error<?php echo ($field_errors['current_password'] !== '') ? ' is-visible' : ''; ?>"
                             id="current_password_error"
                             role="alert"
                             aria-live="polite">
                            <?php echo e($field_errors['current_password']); ?>
                        </div>
                    </div>

                    <hr class="my-4 opacity-25">

                    <!-- ── Field 2: New Password ── -->
                    <div class="form-group">
                        <label for="new_password" class="form-label">
                            New Password
                            <span class="required-indicator" aria-label="required">*</span>
                        </label>
                        <input type="password"
                               name="new_password"
                               id="new_password"
                               class="form-control<?php echo fieldInvalidClass('new_password', $field_errors); ?>"
                               placeholder="Minimum 6 characters"
                               required
                               aria-required="true"
                               minlength="6"
                               autocomplete="new-password"
                               style="min-height:48px;"
                               aria-describedby="new_password_help new_password_error">
                        <!-- 14.4: helper text with instructions/examples -->
                        <small id="new_password_help" class="form-helper">Use a mix of letters, numbers, and symbols for a stronger password.</small>
                        <!-- 14.2: form-error container -->
                        <div class="form-error<?php echo ($field_errors['new_password'] !== '') ? ' is-visible' : ''; ?>"
                             id="new_password_error"
                             role="alert"
                             aria-live="polite">
                            <?php echo e($field_errors['new_password']); ?>
                        </div>
                    </div>

                    <!-- ── Field 3: Confirm New Password ── -->
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">
                            Confirm New Password
                            <span class="required-indicator" aria-label="required">*</span>
                        </label>
                        <input type="password"
                               name="confirm_password"
                               id="confirm_password"
                               class="form-control<?php echo fieldInvalidClass('confirm_password', $field_errors); ?>"
                               placeholder="Repeat your new password"
                               required
                               aria-required="true"
                               minlength="6"
                               autocomplete="new-password"
                               style="min-height:48px;"
                               aria-describedby="confirm_password_error">
                        <!-- 14.2: form-error container -->
                        <div class="form-error<?php echo ($field_errors['confirm_password'] !== '') ? ' is-visible' : ''; ?>"
                             id="confirm_password_error"
                             role="alert"
                             aria-live="polite">
                            <?php echo e($field_errors['confirm_password']); ?>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button type="submit"
                            class="btn btn-primary w-100 fw-bold"
                            style="min-height:48px;">
                        <i class="fas fa-save me-2" aria-hidden="true"></i>Update Password
                    </button>

                </form>
            </div>
        </div>
    </div>

</div><!-- /.row -->

<?php require_once '../includes/footer.php'; ?>
