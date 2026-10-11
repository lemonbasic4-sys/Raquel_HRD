<?php
/**
 * Profile & Settings - Shared module for Manager, Supervisor, Staff
 * This file is included by each role's profile-settings.php
 * Required: $page_title, session, $conn must be available
 */

require_once __DIR__ . '/functions.php';

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Fetch current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Try to find linked employee record
$linked_employee = null;
if (!empty($_SESSION['employee_id'])) {
    $emp_stmt = $conn->prepare("SELECT e.*, b.branch_name, d.department_name as department, ec.personal_email, ec.mobile_number, ec.telephone_number 
        FROM employees e 
        LEFT JOIN branches b ON e.branch_id = b.branch_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        LEFT JOIN employee_contacts ec ON e.employee_id = ec.employee_id
        WHERE e.employee_id = ? AND e.is_active = 1 LIMIT 1");
    $emp_stmt->bind_param("i", $_SESSION['employee_id']);
    $emp_stmt->execute();
    $linked_employee = $emp_stmt->get_result()->fetch_assoc();
    $emp_stmt->close();
} elseif (!empty($_SESSION['email'])) {
    // Fallback to email matching if employee_id is not in session (e.g. for Admin who might not have linked employee_id)
    $emp_stmt = $conn->prepare("SELECT e.*, b.branch_name, d.department_name as department, ec.personal_email, ec.mobile_number, ec.telephone_number 
        FROM employees e 
        LEFT JOIN branches b ON e.branch_id = b.branch_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        LEFT JOIN employee_contacts ec ON e.employee_id = ec.employee_id
        WHERE ec.personal_email = ? AND e.is_active = 1 LIMIT 1");
    $emp_stmt->bind_param("s", $_SESSION['email']);
    $emp_stmt->execute();
    $linked_employee = $emp_stmt->get_result()->fetch_assoc();
    $emp_stmt->close();
}

// Verify CSRF token on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// Handle Profile Picture Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $file = $_FILES['profile_photo'];
        
        if (!in_array($file['type'], $allowed)) {
            $error_msg = 'Only JPG, PNG, and WebP images are allowed.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $error_msg = 'Image must be under 2MB.';
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'emp_' . ($linked_employee ? $linked_employee['employee_id'] : $user_id) . '_' . time() . '.' . $ext;
            $upload_dir = __DIR__ . '/../assets/img/employees/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $dest = $upload_dir . $filename;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                // Delete old upload if exists
                if ($linked_employee && !empty($linked_employee['profile_picture'])) {
                    $old_path = $upload_dir . $linked_employee['profile_picture'];
                    if (file_exists($old_path)) unlink($old_path);
                }
                
                if ($linked_employee) {
                    $upd = $conn->prepare("UPDATE employees SET profile_picture = ? WHERE employee_id = ?");
                    $upd->bind_param("si", $filename, $linked_employee['employee_id']);
                    $upd->execute();
                    $upd->close();
                    $linked_employee['profile_picture'] = $filename;
                }
                
                logAudit($conn, $user_id, 'UPDATE', 'Employee Profile', $linked_employee ? $linked_employee['employee_id'] : 0, 'Updated profile picture');
                $success_msg = 'Profile picture updated successfully!';
            } else {
                $error_msg = 'Failed to upload image. Please try again.';
            }
        }
    } else {
        $error_msg = 'Please select an image file.';
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (empty($current) || empty($new) || empty($confirm)) {
        $error_msg = 'All password fields are required.';
    } elseif (!password_verify($current, $user['password_hash'])) {
        $error_msg = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error_msg = 'New password must be at least 8 characters long.';
    } elseif ($new !== $confirm) {
        $error_msg = 'New passwords do not match.';
    } elseif ($current === $new) {
        $error_msg = 'New password must be different from the current password.';
    } else {
        $new_hash = password_hash($new, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $upd->bind_param("si", $new_hash, $user_id);
        $upd->execute();
        $upd->close();
        
        logAudit($conn, $user_id, 'UPDATE', 'User Password', $user_id, 'Password changed successfully');
        $success_msg = 'Password changed successfully!';
    }
}

// Handle Profile Info Update (full_name, email)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $new_name = trim($_POST['full_name'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    
    if (empty($new_name) || empty($new_email)) {
        $error_msg = 'Full name and email are required.';
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'Please enter a valid email address.';
    } else {
        // Check email uniqueness (excluding self)
        $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $chk->bind_param("si", $new_email, $user_id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error_msg = 'This email is already in use by another account.';
        } else {
            $upd = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
            $upd->bind_param("ssi", $new_name, $new_email, $user_id);
            $upd->execute();
            $upd->close();
            
            $_SESSION['full_name'] = $new_name;
            $_SESSION['email'] = $new_email;
            $user['full_name'] = $new_name;
            $user['email'] = $new_email;
            
            logAudit($conn, $user_id, 'UPDATE', 'User Profile', $user_id, 'Updated profile information');
            $success_msg = 'Profile updated successfully!';
        }
        $chk->close();
    }
}

// Determine the current avatar
$current_avatar = '';
if ($linked_employee && !empty($linked_employee['profile_picture'])) {
    $current_avatar = getEmployeeAvatar($linked_employee['profile_picture']);
} else {
    $role_img = 'staff.png';
    if ($_SESSION['role'] === 'HR Manager') $role_img = 'manager.png';
    elseif ($_SESSION['role'] === 'HR Supervisor') $role_img = 'supervisor.png';
    $current_avatar = BASE_URL . '/assets/img/avatars/' . $role_img;
}

require_once __DIR__ . '/header.php';
?>

<div class="profile-settings-page">

<?php if ($success_msg): ?>
    <?php renderFlashPopup('success', $success_msg); ?>
<?php endif; ?>
<?php if ($error_msg): ?>
    <?php renderFlashPopup('danger', $error_msg); ?>
<?php endif; ?>

<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);"><?php echo e($_SESSION['role']); ?> · Account</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-user-cog me-2" style="color:#BD9414;"></i>Profile &amp; Settings</h4>
        </div>
        <div style="color:rgba(255,255,255,.6);font-size:.8rem;">
            <i class="fas fa-sync-alt me-1"></i>Data as of <?php echo date('F d, Y'); ?>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value" style="font-size:1rem;"><?php echo e($user['role']); ?></div>
                        <div class="stat-label">Access Role</div>
                    </div>
                    <i class="fas fa-id-badge stat-icon text-white-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value" style="font-size:1rem;"><?php echo e($user['username']); ?></div>
                        <div class="stat-label">Username</div>
                    </div>
                    <i class="fas fa-user stat-icon" style="color:#BD9414;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value" style="font-size:1rem;"><?php echo $linked_employee ? 'Linked' : 'None'; ?></div>
                        <div class="stat-label">Employee Record</div>
                    </div>
                    <i class="fas fa-link stat-icon" style="color:#28a745;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value" style="font-size:1rem;"><?php echo formatDate($user['created_at']); ?></div>
                        <div class="stat-label">Account Created</div>
                    </div>
                    <i class="fas fa-calendar stat-icon" style="color:#17a2b8;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row fadeup-1">
    <!-- Profile Card -->
    <div class="col-lg-4 mb-4">
        <div class="content-card h-100 text-center">
            <div class="card-body py-4 px-4">
                <div class="position-relative d-inline-block mb-3">
                    <img src="<?php echo $current_avatar; ?>" class="rounded-circle img-thumbnail shadow-sm" id="avatarPreview"
                        style="width:130px;height:130px;object-fit:cover;border:3px solid #e0e7ff;">
                    <button type="button" class="btn btn-sm btn-primary rounded-circle shadow position-absolute" 
                        style="bottom:5px;right:5px;width:36px;height:36px;" 
                        data-bs-toggle="modal" data-bs-target="#photoModal" title="Change Photo">
                        <i class="fas fa-camera" style="font-size:0.85rem;"></i>
                    </button>
                </div>
                <h5 class="fw-bold mb-1"><?php echo e($user['full_name']); ?></h5>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 mb-2"><?php echo e($user['role']); ?></span>
                <p class="text-muted small mb-0"><?php echo e($user['email']); ?></p>
                
                <hr class="my-3 opacity-10">
                
                <div class="text-start">
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-user text-muted me-3" style="width:18px;"></i>
                        <div class="small">
                            <div class="text-muted" style="font-size:0.7rem;">Username</div>
                            <div class="fw-semibold"><?php echo e($user['username']); ?></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-building text-muted me-3" style="width:18px;"></i>
                        <div class="small">
                            <div class="text-muted" style="font-size:0.7rem;">Branch</div>
                            <div class="fw-semibold">
                                <?php 
                                if ($user['branch_id']) {
                                    $br = $conn->query("SELECT branch_name FROM branches WHERE branch_id = " . (int)$user['branch_id'])->fetch_assoc();
                                    echo e($br['branch_name'] ?? 'N/A');
                                } else {
                                    echo 'Not assigned';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-calendar text-muted me-3" style="width:18px;"></i>
                        <div class="small">
                            <div class="text-muted" style="font-size:0.7rem;">Account Created</div>
                            <div class="fw-semibold"><?php echo formatDate($user['created_at']); ?></div>
                        </div>
                    </div>
                    <?php if ($linked_employee): ?>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-id-badge text-muted me-3" style="width:18px;"></i>
                            <div class="small">
                                <div class="text-muted" style="font-size:0.7rem;">Linked Employee Record</div>
                                <div class="fw-semibold"><?php echo e($linked_employee['first_name'] . ' ' . $linked_employee['last_name']); ?> <span class="company-id-text">(Company ID: <span class="company-id-value"><?php echo e(getEmployeeDisplayId($linked_employee)); ?></span>)</span></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Tabs -->
    <div class="col-lg-8 mb-4">
        <div class="content-card h-100">
            <div class="card-body p-4">
                <ul class="nav nav-tabs mb-4" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active fw-bold small" data-bs-toggle="tab" data-bs-target="#tab-profile" type="button">
                            <i class="fas fa-user-edit me-1"></i>Edit Profile
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link fw-bold small" data-bs-toggle="tab" data-bs-target="#tab-security" type="button">
                            <i class="fas fa-shield-alt me-1"></i>Security
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link fw-bold small" data-bs-toggle="tab" data-bs-target="#tab-account" type="button">
                            <i class="fas fa-info-circle me-1"></i>Account Info
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Edit Profile Tab -->
                    <div class="tab-pane fade show active" id="tab-profile">
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark border-start border-4 border-primary ps-2 mb-1">Personal Information</h6>
                            <p class="text-muted small">Update your display name and email address.</p>
                        </div>
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="update_profile">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Full Name</label>
                                    <input type="text" name="full_name" class="form-control" 
                                        value="<?php echo e($user['full_name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Email Address</label>
                                    <input type="email" name="email" class="form-control" 
                                        value="<?php echo e($user['email']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Username</label>
                                    <input type="text" class="form-control bg-light" 
                                        value="<?php echo e($user['username']); ?>" disabled>
                                    <small class="text-muted">Username cannot be changed.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Role</label>
                                    <input type="text" class="form-control bg-light" 
                                        value="<?php echo e($user['role']); ?>" disabled>
                                    <small class="text-muted">Role is assigned by administrator.</small>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-top">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Security Tab -->
                    <div class="tab-pane fade" id="tab-security">
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark border-start border-4 border-primary ps-2 mb-1">Change Password</h6>
                            <p class="text-muted small">Ensure your account uses a strong password for security.</p>
                        </div>
                        <form method="POST" id="passwordForm">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="change_password">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label small fw-semibold">Current Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fas fa-lock text-muted"></i></span>
                                        <input type="password" name="current_password" class="form-control" required placeholder="Enter current password">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">New Password</label>
                                    <input type="password" name="new_password" class="form-control" id="newPassword" 
                                        required placeholder="Minimum 8 characters" minlength="8">
                                    <div class="password-strength mt-2" id="strengthBar" style="display:none;">
                                        <div class="progress" style="height:4px;">
                                            <div class="progress-bar" id="strengthProgress" style="width:0%"></div>
                                        </div>
                                        <small class="text-muted" id="strengthText"></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control" id="confirmPassword"
                                        required placeholder="Re-enter new password" minlength="8">
                                    <small class="text-danger d-none" id="matchError">Passwords do not match</small>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-top">
                                <button type="submit" class="btn btn-warning px-4" id="changePasswordBtn">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Account Info Tab -->
                    <div class="tab-pane fade" id="tab-account">
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark border-start border-4 border-primary ps-2 mb-1">Account Details</h6>
                            <p class="text-muted small">Your account information and system activity.</p>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-3 rounded" style="background:#f8fafe;">
                                    <div class="small text-muted mb-1">User ID</div>
                                    <div class="fw-bold">#<?php echo $user['user_id']; ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 rounded" style="background:#f8fafe;">
                                    <div class="small text-muted mb-1">Account Status</div>
                                    <div class="fw-bold">
                                        <?php if ($user['is_active']): ?>
                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i>Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="fas fa-times me-1"></i>Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 rounded" style="background:#f8fafe;">
                                    <div class="small text-muted mb-1">Created</div>
                                    <div class="fw-bold"><?php echo formatDateTime($user['created_at']); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 rounded" style="background:#f8fafe;">
                                    <div class="small text-muted mb-1">Last Updated</div>
                                    <div class="fw-bold"><?php echo formatDateTime($user['updated_at']); ?></div>
                                </div>
                            </div>
                        </div>

                        <?php if ($linked_employee): ?>
                            <div class="mt-4">
                                <h6 class="fw-bold text-dark border-start border-4 border-info ps-2 mb-3">Linked Employee Record</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="small text-muted">Employee Name</div>
                                        <div class="fw-semibold"><?php echo e($linked_employee['first_name'] . ' ' . ($linked_employee['middle_name'] ? $linked_employee['middle_name'] . ' ' : '') . $linked_employee['last_name']); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="small text-muted">Job Title</div>
                                        <div class="fw-semibold"><?php echo e($linked_employee['job_title']); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="small text-muted">Department</div>
                                        <div class="fw-semibold"><?php echo e($linked_employee['department']); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="small text-muted">Branch</div>
                                        <div class="fw-semibold"><?php echo e($linked_employee['branch_name'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="small text-muted">Employment Status</div>
                                        <div class="fw-semibold"><span class="badge bg-success-subtle text-success border border-success-subtle"><?php echo e($linked_employee['employment_status']); ?></span></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="small text-muted">Date Hired</div>
                                        <div class="fw-semibold"><?php echo formatDate($linked_employee['hire_date']); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Photo Upload Modal -->
<div class="modal fade" id="photoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold"><i class="fas fa-camera me-2"></i>Update Profile Photo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="photoForm">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="upload_photo">
                    <div class="text-center mb-3">
                        <img src="<?php echo $current_avatar; ?>" class="rounded-circle shadow-sm mb-3" id="photoPreview"
                            style="width:120px;height:120px;object-fit:cover;border:3px solid #e0e7ff;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Choose Image</label>
                        <input type="file" name="profile_photo" class="form-control" id="photoInput"
                            accept="image/jpeg,image/png,image/webp" required>
                        <small class="text-muted">JPG, PNG, or WebP &bull; Max 2MB</small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-upload me-2"></i>Upload Photo
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Move modal to body to prevent z-index backdrop issues (the "black shadow" bug)
    const photoModal = document.getElementById('photoModal');
    if (photoModal) {
        document.body.appendChild(photoModal);
    }

    // Photo preview
    const photoInput = document.getElementById('photoInput');
    const photoPreview = document.getElementById('photoPreview');
    if (photoInput) {
        photoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    photoPreview.src = ev.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Password strength meter
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    const strengthBar = document.getElementById('strengthBar');
    const strengthProgress = document.getElementById('strengthProgress');
    const strengthText = document.getElementById('strengthText');
    const matchError = document.getElementById('matchError');

    if (newPassword) {
        newPassword.addEventListener('input', function() {
            const val = this.value;
            strengthBar.style.display = val.length > 0 ? 'block' : 'none';
            
            let strength = 0;
            if (val.length >= 8) strength += 25;
            if (val.match(/[a-z]/) && val.match(/[A-Z]/)) strength += 25;
            if (val.match(/\d/)) strength += 25;
            if (val.match(/[^a-zA-Z0-9]/)) strength += 25;

            strengthProgress.style.width = strength + '%';
            strengthProgress.className = 'progress-bar';
            if (strength <= 25) { strengthProgress.classList.add('bg-danger'); strengthText.textContent = 'Weak'; }
            else if (strength <= 50) { strengthProgress.classList.add('bg-warning'); strengthText.textContent = 'Fair'; }
            else if (strength <= 75) { strengthProgress.classList.add('bg-info'); strengthText.textContent = 'Good'; }
            else { strengthProgress.classList.add('bg-success'); strengthText.textContent = 'Strong'; }
        });
    }

    if (confirmPassword) {
        confirmPassword.addEventListener('input', function() {
            if (this.value && newPassword.value !== this.value) {
                matchError.classList.remove('d-none');
            } else {
                matchError.classList.add('d-none');
            }
        });
    }
});
</script>

<style>
.nav-tabs .nav-link { color: var(--text-muted); border: none; border-bottom: 2px solid transparent; }
.nav-tabs .nav-link.active { color: var(--primary-blue); background: transparent; border-bottom: 2px solid var(--primary-blue); }
.bg-primary-subtle { background-color: #e7f1ff; }
.bg-success-subtle { background-color: #e8f5e9; }
.form-control, .form-select { border-radius: 8px; border: 1.5px solid #eee; padding: 0.6rem 0.8rem; }
.form-control:focus { box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.05); border-color: #0d6efd; }
.password-strength { margin-top: 4px; }
</style>

</div>
<?php require_once __DIR__ . '/footer.php'; ?>
