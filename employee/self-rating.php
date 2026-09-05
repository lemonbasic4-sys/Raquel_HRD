<?php
$page_title = 'Self Rating';
require_once '../includes/session-check.php';
checkRole(['Employee']);
require_once '../includes/functions.php';

ensureEvaluationWorkflowSchema($conn);

$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
$user_id = (int) ($_SESSION['user_id'] ?? 0);

// Validate user_id exists in users table to prevent FK constraint failure
$user_id_nullable = null;
if ($user_id > 0) {
    $uid_check = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? LIMIT 1");
    $uid_check->bind_param("i", $user_id);
    $uid_check->execute();
    $uid_exists = (bool) $uid_check->get_result()->fetch_assoc();
    $uid_check->close();
    if ($uid_exists) {
        $user_id_nullable = $user_id;
    }
}
// Keep the original variable as well to avoid breaking local checks that don't insert/update, but update inserts/updates to use the nullable version where necessary.
$user_id_safe = $user_id_nullable;


if (isset($_GET['discard']) && is_numeric($_GET['discard'])) {
    $discard_id = (int)$_GET['discard'];
    $stmt = $conn->prepare("
        UPDATE evaluations 
        SET deleted_at = NOW() 
        WHERE evaluation_id = ? 
          AND employee_id = ? 
          AND status IN ('Draft', 'Returned', 'Pending Self-Rating')
        LIMIT 1
    ");
    $stmt->bind_param("ii", $discard_id, $employee_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected > 0) {
        logAudit($conn, $user_id, 'DELETE', 'Evaluation', $discard_id, 'Discarded evaluation draft');
        redirectWith(BASE_URL . '/employee/self-rating.php', 'success', 'Draft evaluation was successfully discarded.');
    } else {
        redirectWith(BASE_URL . '/employee/self-rating.php', 'danger', 'Failed to discard draft, or draft not found.');
    }
}

$employee_stmt = $conn->prepare("
    SELECT e.employee_id, e.employee_code, e.first_name, e.last_name, e.job_title, e.department_id, e.branch_id,
           e.rank_category_id, e.hire_date, e.employment_status,
           d.department_name, b.branch_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    WHERE e.employee_id = ?
    LIMIT 1
");
$employee_stmt->bind_param("i", $employee_id);
$employee_stmt->execute();
$employee = $employee_stmt->get_result()->fetch_assoc();
$employee_stmt->close();

if (!$employee) {
    redirectWith(BASE_URL . '/employee/dashboard.php', 'danger', 'No employee record found for self-rating.');
}

$edit_eval = null;
$view_eval = null;
$edit_scores = [];
$view_scores = [];
$view_dev_plans = [];
$selected_template_id = isset($_GET['template']) ? (int) $_GET['template'] : 0;
$view_mode = false;
$assigned_evaluations = null;
$is_assigned_edit = false;

if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $view_id = (int) $_GET['view'];
    $stmt = $conn->prepare("
        SELECT ev.*, et.template_name, et.kra_weight, et.behavior_weight, et.form_code, et.revision_date, et.effective_date_form, au.full_name AS assigned_by_name
        FROM evaluations ev
        LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
        LEFT JOIN users au ON ev.assigned_by = au.user_id
        WHERE ev.evaluation_id = ?
          AND ev.employee_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $view_id, $employee_id);
    $stmt->execute();
    $view_eval = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($view_eval) {
        $view_mode = true;
        $selected_template_id = (int) $view_eval['template_id'];
        $score_rs = $conn->query("SELECT criterion_id, score_value, weighted_score, dept_manager_override_score, supervisor_override_score, manager_override_score FROM evaluation_scores WHERE evaluation_id = " . (int) $view_eval['evaluation_id']);
        while ($score = $score_rs->fetch_assoc()) {
            $view_scores[(int) $score['criterion_id']] = $score;
        }

        $dev_stmt = $conn->prepare("
            SELECT improvement_area, support_needed, time_frame
            FROM evaluation_dev_plans
            WHERE evaluation_id = ?
            ORDER BY sort_order, plan_id
        ");
        $dev_stmt->bind_param("i", $view_eval['evaluation_id']);
        $dev_stmt->execute();
        $dev_rs = $dev_stmt->get_result();
        while ($plan = $dev_rs->fetch_assoc()) {
            $view_dev_plans[] = $plan;
        }
        $dev_stmt->close();
    }
}

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];
    $stmt = $conn->prepare("
        SELECT ev.*, et.form_code, et.revision_date, et.effective_date_form, au.full_name AS assigned_by_name
        FROM evaluations ev
        LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
        LEFT JOIN users au ON ev.assigned_by = au.user_id
        WHERE ev.evaluation_id = ?
          AND ev.employee_id = ?
          AND (
            ev.status IN ('Draft', 'Returned', 'Pending Self-Rating')
          )
        LIMIT 1
    ");
    $stmt->bind_param("ii", $edit_id, $employee_id);
    $stmt->execute();
    $edit_eval = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($edit_eval) {
        $is_assigned_edit = !empty($edit_eval['assigned_by']);
        $selected_template_id = (int) $edit_eval['template_id'];
        $score_rs = $conn->query("SELECT criterion_id, score_value FROM evaluation_scores WHERE evaluation_id = " . (int) $edit_eval['evaluation_id']);
        while ($score = $score_rs->fetch_assoc()) {
            $edit_scores[(int) $score['criterion_id']] = $score['score_value'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $template_id = (int) ($_POST['template_id'] ?? 0);
    $period_start = $_POST['period_start'] ?: null;
    $period_end = $_POST['period_end'] ?: null;
    $self_comments = trim($_POST['self_comments'] ?? '');
    $action = $_POST['submit_action'] ?? 'draft';
    $kra_scores = $_POST['kra_scores'] ?? [];
    $beh_scores = $_POST['beh_scores'] ?? [];
    $editing_id = (int) ($_POST['edit_id'] ?? 0);
    $employee_consent_agreed = isset($_POST['employee_consent_agreed']) ? 1 : 0;
    $employee_signature_data = trim($_POST['employee_signature_data'] ?? '');
    $editable_eval = null;
    $is_assigned_submission = false;

    if ($editing_id <= 0 && $template_id > 0) {
        $check_stmt = $conn->prepare("
            SELECT evaluation_id 
            FROM evaluations 
            WHERE employee_id = ? 
              AND template_id = ? 
              AND status IN ('Draft', 'Returned', 'Pending Self-Rating')
              AND deleted_at IS NULL 
            LIMIT 1
        ");
        $check_stmt->bind_param("ii", $employee_id, $template_id);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        if ($check_res) {
            $editing_id = (int) $check_res['evaluation_id'];
        }
    }

    $errors = [];

    if ($editing_id > 0) {
        $stmt = $conn->prepare("
            SELECT evaluation_id, template_id, assigned_by, status, submitted_date
            FROM evaluations
            WHERE evaluation_id = ?
              AND employee_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("ii", $editing_id, $employee_id);
        $stmt->execute();
        $editable_eval = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$editable_eval) {
            $errors[] = 'The selected self-rating could not be found.';
        } else {
            $is_assigned_submission = !empty($editable_eval['assigned_by']);
            $allowed_statuses = ['Draft', 'Returned', 'Pending Self-Rating'];

            if (!in_array($editable_eval['status'], $allowed_statuses, true)) {
                $errors[] = 'This self-rating is no longer editable.';
            }

            if ($is_assigned_submission) {
                $template_id = (int) $editable_eval['template_id'];
            }
        }
    }

    if ($template_id <= 0) {
        $errors[] = 'Please select an evaluation template.';
    }

    if ($template_id > 0) {
        $existing_stmt = $conn->prepare("
            SELECT evaluation_id, status 
            FROM evaluations 
            WHERE employee_id = ? 
              AND template_id = ? 
              AND status NOT IN ('Draft', 'Returned', 'Rejected', 'Pending Self-Rating')
              AND deleted_at IS NULL 
            LIMIT 1
        ");
        $existing_stmt->bind_param("ii", $employee_id, $template_id);
        $existing_stmt->execute();
        $existing_eval_check = $existing_stmt->get_result()->fetch_assoc();
        $existing_stmt->close();

        if ($existing_eval_check && $editing_id !== (int)$existing_eval_check['evaluation_id']) {
            $errors[] = 'You have already submitted a self-rating for this template (Status: ' . $existing_eval_check['status'] . '). Duplicate submissions are not allowed.';
        }
    }

    if ($action === 'submit') {
        if (!$employee_consent_agreed) {
            $errors[] = 'You must read and agree to the declaration consent before submitting your self-rating.';
        }
        if (empty($employee_signature_data)) {
            $errors[] = 'Please provide your digital signature before submitting your self-rating.';
        }
    }

    $template_stmt = $conn->prepare("SELECT template_id, template_name, kra_weight, behavior_weight, evaluation_type, form_code, revision_date, effective_date_form FROM evaluation_templates WHERE template_id = ? AND status = 'Active' LIMIT 1");
    $template_stmt->bind_param("i", $template_id);
    $template_stmt->execute();
    $template = $template_stmt->get_result()->fetch_assoc();
    $template_stmt->close();

    if (!$template) {
        $errors[] = 'Selected template is not available.';
    }

    $evaluation_type = trim($template['evaluation_type'] ?? 'Annual');

    // Automatically calculate period_start and period_end if they are empty
    if (empty($period_start) || empty($period_end)) {
        if ($editing_id > 0) {
            $db_eval_stmt = $conn->prepare("SELECT evaluation_period_start, evaluation_period_end FROM evaluations WHERE evaluation_id = ? LIMIT 1");
            $db_eval_stmt->bind_param("i", $editing_id);
            $db_eval_stmt->execute();
            $db_eval = $db_eval_stmt->get_result()->fetch_assoc();
            $db_eval_stmt->close();
            
            if ($db_eval && !empty($db_eval['evaluation_period_start']) && !empty($db_eval['evaluation_period_end'])) {
                $period_start = $db_eval['evaluation_period_start'];
                $period_end = $db_eval['evaluation_period_end'];
            }
        }
        
        if (empty($period_start) || empty($period_end)) {
            $hire_date = $employee['hire_date'] ?? date('Y-m-d');
            
            if ($evaluation_type === 'Annual') {
                $period_start = date('Y') . '-01-01';
                $period_end = date('Y') . '-12-31';
            } elseif ($evaluation_type === 'Quarterly') {
                $current_month = (int)date('n');
                $current_year = date('Y');
                if ($current_month <= 3) {
                    $period_start = $current_year . '-01-01';
                    $period_end = $current_year . '-03-31';
                } elseif ($current_month <= 6) {
                    $period_start = $current_year . '-04-01';
                    $period_end = $current_year . '-06-30';
                } elseif ($current_month <= 9) {
                    $period_start = $current_year . '-07-01';
                    $period_end = $current_year . '-09-30';
                } else {
                    $period_start = $current_year . '-10-01';
                    $period_end = $current_year . '-12-31';
                }
            } elseif ($evaluation_type === 'Initial') {
                $period_start = $hire_date;
                $period_end = date('Y-m-d', strtotime('+3 months', strtotime($hire_date)));
            } elseif ($evaluation_type === 'Final') {
                $period_start = $hire_date;
                $period_end = date('Y-m-d', strtotime('+6 months', strtotime($hire_date)));
            } else {
                $period_start = date('Y') . '-01-01';
                $period_end = date('Y') . '-12-31';
            }
        }
    }

    if ($action === 'submit') {
        $criteria_count_rs = $conn->query("SELECT COUNT(*) AS total FROM evaluation_criteria WHERE template_id = $template_id");
        $criteria_total = (int) ($criteria_count_rs->fetch_assoc()['total'] ?? 0);
        if ($criteria_total <= 0) {
            $errors[] = 'This template has no criteria yet.';
        }

        // Validate evaluation period dates
        if (empty($period_start)) {
            $errors[] = 'Evaluation Period Start date is required before submitting.';
        }
        if (empty($period_end)) {
            $errors[] = 'Evaluation Period End date is required before submitting.';
        }
        if (!empty($period_start) && !empty($period_end) && $period_start > $period_end) {
            $errors[] = 'Evaluation Period Start must be before the End date.';
        }

        // Validate that EVERY criterion in the template has a rating selected (1 to 4)
        $req_criteria = $conn->query("SELECT criterion_id FROM evaluation_criteria WHERE template_id = $template_id");
        $missing_count = 0;
        while ($cr = $req_criteria->fetch_assoc()) {
            $cid = (int)$cr['criterion_id'];
            $val = (float)($kra_scores[$cid] ?? ($beh_scores[$cid] ?? 0));
            if ($val <= 0) {
                $missing_count++;
            }
        }
        if ($missing_count > 0) {
            $errors[] = 'All evaluation criteria must have a rating selected (1 to 4) before submitting. Please complete all questions.';
        }
    }



    if (!empty($errors)) {
        if (isset($_POST['auto_save']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
            exit;
        }
        redirectWith(BASE_URL . '/employee/self-rating.php' . ($editing_id ? '?edit=' . $editing_id : '?template=' . $template_id), 'danger', implode(' ', $errors));
    }

    $kra_weight_pct = (float) ($template['kra_weight'] ?? 80);
    $beh_weight_pct = (float) ($template['behavior_weight'] ?? 20);

    $kra_subtotal = 0;
    $kra_score_data = [];
    $kra_criteria = $conn->query("SELECT * FROM evaluation_criteria WHERE template_id = $template_id AND section='KRA' ORDER BY sort_order");
    while ($criterion = $kra_criteria->fetch_assoc()) {
        $criterion_id = (int) $criterion['criterion_id'];
        $rating = (float) ($kra_scores[$criterion_id] ?? 0);
        if ($rating > 4.00)
            $rating = 4.00;
        if ($rating < 0)
            $rating = 0;
        $weight = (float) $criterion['weight'];
        $weighted = round(($weight / 100) * $rating, 2);
        $kra_subtotal += $weighted;
        $kra_score_data[] = ['criterion_id' => $criterion_id, 'score_value' => $rating, 'weighted_score' => $weighted];
    }
    $kra_subtotal = round($kra_subtotal, 2);

    $beh_score_data = [];
    $behavior_total = 0;
    $behavior_count = 0;
    $beh_criteria = $conn->query("SELECT * FROM evaluation_criteria WHERE template_id = $template_id AND section='Behavior' ORDER BY sort_order");
    while ($criterion = $beh_criteria->fetch_assoc()) {
        $criterion_id = (int) $criterion['criterion_id'];
        $rating = (float) ($beh_scores[$criterion_id] ?? 0);
        if ($rating > 4.00)
            $rating = 4.00;
        if ($rating < 0)
            $rating = 0;
        $behavior_total += $rating;
        $behavior_count++;
        $beh_score_data[] = ['criterion_id' => $criterion_id, 'score_value' => $rating, 'weighted_score' => $rating];
    }
    $behavior_average = $behavior_count > 0 ? round($behavior_total / $behavior_count, 2) : 0;

    $total_score = calculateEvalTotal($kra_subtotal, $behavior_average, $kra_weight_pct, $beh_weight_pct);
    $performance_level = getPerformanceLevel($total_score);
    $supervisor = getEmployeeSupervisor($conn, $employee_id); // used for notification routing
    $genuine_supervisor = getDeptSupervisorOfEmployee($conn, $employee_id); // rank-4 only, no manager fallback
    $has_supervisor = ($genuine_supervisor !== null && !empty($genuine_supervisor['user_id']));
    $dept_manager = getDeptManagerOfEmployee($conn, $employee_id);
    $has_dept_manager = ($dept_manager !== null && !empty($dept_manager['user_id']));
    $is_supervisor_level_employee = isSupervisorLevelEmployee($employee);
    
    // Check if employee has an HR role
    $hr_role = getEmployeeHRRole($conn, $employee_id);
    $uses_hr_specific_flow = $hr_role !== null || isMainOfficeHumanResourcesEmployee($conn, $employee_id);
    
    if ($action === 'submit') {
        if ($uses_hr_specific_flow && ($hr_role === 'HR Staff' || $hr_role === null)) {
            $status = 'Pending Supervisor';
        } elseif ($hr_role === 'HR Supervisor') {
            $status = 'Pending Manager';
        } elseif ($hr_role === 'HR Manager') {
            $status = 'Pending Supervisor';
        } elseif ($is_supervisor_level_employee && $has_dept_manager) {
            $status = 'Pending Dept Manager';
        } elseif ($is_supervisor_level_employee) {
            $status = 'Pending HR Consolidation';
        } elseif (!$uses_hr_specific_flow && (int)($employee['rank_category_id'] ?? 0) === 3) {
            // Branch Manager self-rating: goes to Branch Supervisor (rank 4) ONLY if one exists in the branch.
            // Manager-only departments (no rank-4 supervisor) skip directly to HR Consolidation.
            $branch_has_real_supervisor = getDeptSupervisorOfEmployee($conn, $employee_id) !== null;
            $status = $branch_has_real_supervisor ? 'Pending Dept Supervisor' : 'Pending HR Consolidation';
        } else {
            // R&F employees, or others falling here
            if ($has_supervisor) {
                $status = 'Pending Dept Supervisor';
            } elseif ($has_dept_manager) {
                $status = 'Pending Dept Manager';
            } else {
                $status = 'Pending HR Consolidation';
            }
        }
    } else {
        $status = $is_assigned_submission ? 'Pending Self-Rating' : 'Draft';
    }

    // New organization-driven packages own the approval route. Legacy individual
    // confirmation pages must not receive newly submitted package evaluations.
    $uses_organization_package_flow = ($action === 'submit') && ensureOrganizationEvaluationPackageSchema($conn);
    if ($uses_organization_package_flow) {
        $status = 'Pending Team Consolidation';
    }
    $submitted_date = ($action === 'submit') ? date('Y-m-d H:i:s') : null;

        $employee_signed_at = !empty($employee_signature_data) ? date('Y-m-d H:i:s') : null;

        if ($editing_id > 0) {
            $stmt = $conn->prepare("
                UPDATE evaluations
                SET template_id=?, evaluation_type=?, evaluation_period_start=?, evaluation_period_end=?,
                    status=?, total_score=?, kra_subtotal=?, behavior_average=?, performance_level=?,
                    submitted_by=?, submitted_date=?, staff_comments=?, current_position=?, months_in_position=?,
                    desired_position=?, target_date=?, career_growth_suited=?, career_growth_details=?,
                    employee_consent_agreed=?, employee_signature_data=?, employee_signed_at=?
                WHERE evaluation_id=? AND employee_id=?
            ");
            $current_position = (string) ($employee['job_title'] ?? '');
            $months_in_position = 0;
            $desired_position = '';
            $target_date = null;
            $career_growth_suited = 0;
            $career_growth_details = '';
            $stmt->bind_param("issssdddsssssissisissii", $template_id, $evaluation_type, $period_start, $period_end, $status, $total_score, $kra_subtotal, $behavior_average, $performance_level, $user_id_safe, $submitted_date, $self_comments, $current_position, $months_in_position, $desired_position, $target_date, $career_growth_suited, $career_growth_details, $employee_consent_agreed, $employee_signature_data, $employee_signed_at, $editing_id, $employee_id);
            $stmt->execute();
            $stmt->close();

            $conn->query("DELETE FROM evaluation_scores WHERE evaluation_id = $editing_id");
            $eval_id = $editing_id;
        } else {
            $stmt = $conn->prepare("
                INSERT INTO evaluations (
                    employee_id, template_id, evaluation_type, evaluation_period_start, evaluation_period_end,
                    submitted_by, status, total_score, kra_subtotal, behavior_average, performance_level,
                    submitted_date, staff_comments, current_position, months_in_position,
                    desired_position, target_date, career_growth_suited, career_growth_details,
                    employee_consent_agreed, employee_signature_data, employee_signed_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $current_position = (string) ($employee['job_title'] ?? '');
            $months_in_position = 0;
            $desired_position = '';
            $target_date = null;
            $career_growth_suited = 0;
            $career_growth_details = '';
            $stmt->bind_param("iisssisdddssssissisiss", $employee_id, $template_id, $evaluation_type, $period_start, $period_end, $user_id_safe, $status, $total_score, $kra_subtotal, $behavior_average, $performance_level, $submitted_date, $self_comments, $current_position, $months_in_position, $desired_position, $target_date, $career_growth_suited, $career_growth_details, $employee_consent_agreed, $employee_signature_data, $employee_signed_at);
            $stmt->execute();
            $eval_id = (int) $stmt->insert_id;
            $stmt->close();
        }

    $score_stmt = $conn->prepare("INSERT INTO evaluation_scores (evaluation_id, criterion_id, score_value, weighted_score) VALUES (?, ?, ?, ?)");
    foreach (array_merge($kra_score_data, $beh_score_data) as $score_data) {
        $score_stmt->bind_param("iidd", $eval_id, $score_data['criterion_id'], $score_data['score_value'], $score_data['weighted_score']);
        $score_stmt->execute();
    }
    $score_stmt->close();

    if ($action === 'submit') {
        $package_id = syncEvaluationToOrganizationPackage($conn, $eval_id);
        if ($uses_organization_package_flow && $package_id) {
            logAudit($conn, $user_id, 'CREATE', 'Evaluation', $eval_id, 'Submitted self-rating to organization evaluation package');
            redirectWith(BASE_URL . '/employee/self-rating.php', 'success', 'Your self-rating was submitted to the team evaluation package. The package will open for consolidation after every required team member submits.');
        }
        $employee_name = trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));

        // Check if employee has an HR role
        $hr_role = getEmployeeHRRole($conn, $employee_id);
        $uses_hr_specific_flow = $hr_role !== null || isMainOfficeHumanResourcesEmployee($conn, $employee_id);
        if ($uses_hr_specific_flow) {
            $display_hr_role = $hr_role ?? 'Human Resources';

            // HRD self-rating notifications go ONLY to the admin portal (HRIS).
            // No employee-portal notifications are sent to HR Supervisors or HR Managers.

            if ($hr_role === 'HR Staff' || $hr_role === null) {
                // HR Staff → notify HR Supervisor HRIS (admin portal) only
                $hr_supervisors_stmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'HR Supervisor' AND is_active = 1");
                $hr_supervisors_stmt->execute();
                $hr_supervisors = $hr_supervisors_stmt->get_result();
                while ($hr_sup = $hr_supervisors->fetch_assoc()) {
                    createNotification(
                        $conn,
                        (int)$hr_sup['user_id'],
                        'HR Self-Rating Pending Your Review',
                        $employee_name . ' (' . $display_hr_role . ') submitted a self-rating for your review.',
                        BASE_URL . '/supervisor/pending-endorsements.php'
                    );
                }
                $hr_supervisors_stmt->close();

            } elseif ($hr_role === 'HR Supervisor') {
                // HR Supervisor → notify HR Manager HRIS (admin portal) only
                $hr_managers_stmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'HR Manager' AND is_active = 1");
                $hr_managers_stmt->execute();
                $hr_managers_res = $hr_managers_stmt->get_result();
                while ($hr_mgr = $hr_managers_res->fetch_assoc()) {
                    createNotification(
                        $conn,
                        (int)$hr_mgr['user_id'],
                        'HR Self-Rating Pending Your Review',
                        $employee_name . ' (HR Supervisor) submitted a self-rating for your review.',
                        BASE_URL . '/manager/pending-approvals.php'
                    );
                }
                $hr_managers_stmt->close();

            } elseif ($hr_role === 'HR Manager') {
                // HR Manager → notify HR Supervisor HRIS (admin portal) only
                $hr_supervisors_stmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'HR Supervisor' AND is_active = 1");
                $hr_supervisors_stmt->execute();
                $hr_supervisors = $hr_supervisors_stmt->get_result();
                while ($hr_sup = $hr_supervisors->fetch_assoc()) {
                    createNotification(
                        $conn,
                        (int)$hr_sup['user_id'],
                        'HR Self-Rating Pending Your Review',
                        $employee_name . ' (HR Manager) submitted a self-rating for your review.',
                        BASE_URL . '/supervisor/pending-endorsements.php'
                    );
                }
                $hr_supervisors_stmt->close();
            }
        } else {
            // Normal employee flow
            if ($status === 'Pending Dept Manager') {
                // Broadcast to all active branch/department managers
                $dept_managers = getDeptManagersOfEmployee($conn, $employee_id);
                foreach ($dept_managers as $dm) {
                    if (!empty($dm['user_id'])) {
                        createNotification(
                            $conn,
                            (int)$dm['user_id'],
                            'Evaluation Pending Endorsement',
                            $employee_name . ' submitted a self-rating requiring your Department Manager review.',
                            BASE_URL . '/employee/dept-manager-review.php?evaluation_id=' . $eval_id
                        );
                    }
                }
                $supervisor_notified = true;
            } elseif (!$uses_hr_specific_flow && (int)($employee['rank_category_id'] ?? 0) === 3) {
                // Branch Manager self-rating: specifically notify Branch Supervisors (rank 4) in same branch
                $branch_id_mgr = (int)($employee['branch_id'] ?? 0);
                $supervisor_notified = false;
                if ($branch_id_mgr > 0) {
                    $sup_notif_stmt = $conn->prepare("
                        SELECT DISTINCT u.user_id
                        FROM users u
                        JOIN employees s ON u.employee_id = s.employee_id
                        WHERE s.is_active = 1
                          AND s.deleted_at IS NULL
                          AND s.employee_id != ?
                          AND s.branch_id = ?
                          AND (s.rank_category_id = 4 OR (s.job_title LIKE '%Supervisor%' AND s.job_title NOT LIKE '%Manager%'))
                          AND u.role = 'Employee'
                          AND u.is_active = 1
                    ");
                    $sup_notif_stmt->bind_param("ii", $employee_id, $branch_id_mgr);
                    $sup_notif_stmt->execute();
                    $sup_notif_result = $sup_notif_stmt->get_result();
                    while ($sup_row = $sup_notif_result->fetch_assoc()) {
                        createNotification(
                            $conn,
                            (int)$sup_row['user_id'],
                            'Self-Rating Pending Confirmation',
                            $employee_name . ' (Branch Manager) submitted a self-rating awaiting your confirmation.',
                            BASE_URL . '/employee/team-evaluation-packages.php'
                        );
                        $supervisor_notified = true;
                    }
                    $sup_notif_stmt->close();
                }
                if (!$supervisor_notified) {
                    // Fallback: use general supervisor notification
                    $supervisor_notified = notifySupervisorOfSelfRating($conn, $employee_id, $eval_id);
                }
            } else {
                $supervisor_notified = notifySupervisorOfSelfRating($conn, $employee_id, $eval_id);
            }

            // If no supervisor found, notify HR Supervisor as fallback (filtered by employee's branch)
            if (!$supervisor_notified) {
                $branch_id = (int) ($employee['branch_id'] ?? 0);
                $hr_supervisors_stmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'HR Supervisor' AND branch_id = ? AND is_active = 1");
                $hr_supervisors_stmt->bind_param("i", $branch_id);
                $hr_supervisors_stmt->execute();
                $hr_supervisors = $hr_supervisors_stmt->get_result();
                $hr_notified = false;
                while ($hr_sup = $hr_supervisors->fetch_assoc()) {
                    createNotification(
                        $conn,
                        (int) $hr_sup['user_id'],
                        'Employee Self-Rating Submitted',
                        $employee_name . ' submitted a self-rating for review. (No supervisor assigned)',
                        BASE_URL . '/supervisor/pending-endorsements.php'
                    );
                    $hr_notified = true;
                }
                $hr_supervisors_stmt->close();

                // If no branch-specific HR Supervisor was notified, notify ALL active HR Supervisors
                if (!$hr_notified) {
                    $hr_all_stmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'HR Supervisor' AND is_active = 1");
                    $hr_all_stmt->execute();
                    $hr_all_res = $hr_all_stmt->get_result();
                    while ($hr_sup = $hr_all_res->fetch_assoc()) {
                        createNotification(
                            $conn,
                            (int) $hr_sup['user_id'],
                            'Employee Self-Rating Submitted',
                            $employee_name . ' submitted a self-rating for review. (No supervisor assigned)',
                            BASE_URL . '/supervisor/pending-endorsements.php'
                        );
                    }
                    $hr_all_stmt->close();
                }
            }
        }
        $success_msg = 'Your self-rating was submitted successfully. Awaiting supervisor confirmation.';
        if ($hr_role === 'HR Manager') {
            $success_msg = 'Your evaluation was submitted successfully. Awaiting HR supervisor confirmation.';
        } elseif ($hr_role === 'HR Supervisor') {
            $success_msg = 'Your evaluation was submitted successfully. Awaiting HR manager confirmation.';
        } elseif ((int)($employee['rank_category_id'] ?? 0) === 3) {
            $success_msg = 'Your evaluation was submitted successfully. Awaiting branch supervisor confirmation.';
        } elseif ($is_supervisor_level_employee) {
            $success_msg = 'Your evaluation was submitted successfully. Awaiting branch manager confirmation.';
        }

        logAudit($conn, $user_id, 'CREATE', 'Evaluation', $eval_id, 'Submitted employee self-rating');
        redirectWith(BASE_URL . '/employee/self-rating.php', 'success', $success_msg);
    }

    // Only log manual saves — auto-saves would spam the audit trail with dozens of identical entries
    $is_auto_save = isset($_POST['auto_save']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
    if (!$is_auto_save) {
        logAudit($conn, $user_id, 'CREATE', 'Evaluation', $eval_id, 'Saved employee self-rating draft');
    }
    if ($is_auto_save) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Your self-rating draft was saved.', 'evaluation_id' => $eval_id]);
        exit;
    }
    redirectWith(BASE_URL . '/employee/self-rating.php?edit=' . $eval_id, 'success', 'Your self-rating draft was saved.');
}

// Get employee's department name and employment status for template filtering
$employee_dept = $employee['department_name'] ?? '';
$emp_status = $employee['employment_status'] ?? 'Regular';

// Determine allowed evaluation types based on employment status
$is_non_regular = in_array($emp_status, ['OJT', 'Probationary', 'Project Based', 'Project-Based', 'Trainee'], true);
$allowed_eval_types = $is_non_regular ? ['Initial', 'Final'] : ['Annual', 'Quarterly', 'Final'];
$in_clause = "'" . implode("','", $allowed_eval_types) . "'";

// Filter templates: show if matches employee's department (with 'All' fallbacks) and matches allowed evaluation types
// Only exclude a template if the employee already has a submitted/in-review/approved evaluation for it
// (Do NOT exclude if the evaluation is still editable: Draft, Returned, Pending Self-Rating)
$templates_stmt = $conn->prepare("
    SELECT template_id, template_name, kra_weight, behavior_weight, evaluation_type, target_department
    FROM evaluation_templates et
    WHERE et.status = 'Active' 
      AND (target_department IS NULL OR target_department = '' OR target_department = 'All Departments' OR target_department = ?)
      AND et.evaluation_type IN ($in_clause)
      AND NOT EXISTS (
          SELECT 1
          FROM evaluations ev
          WHERE ev.employee_id = ?
            AND ev.template_id = et.template_id
            AND ev.deleted_at IS NULL
            AND ev.status NOT IN ('Draft', 'Returned', 'Rejected', 'Pending Self-Rating')
      )
    ORDER BY template_name
");
$templates_stmt->bind_param("si", $employee_dept, $employee_id);
$templates_stmt->execute();
$templates = $templates_stmt->get_result();
$available_template_count = (int) $templates->num_rows;
$templates_stmt->close();

// Fetch selected template details for evaluation type (verify it's accessible to this employee)
$selected_template = null;
if ($selected_template_id > 0) {
    if ($is_assigned_edit) {
        $sel_template_stmt = $conn->prepare("
            SELECT template_id, template_name, evaluation_type, target_department, form_code, revision_date, effective_date_form
            FROM evaluation_templates
            WHERE template_id = ?
            LIMIT 1
        ");
        $sel_template_stmt->bind_param("i", $selected_template_id);
    } else {
        $sel_template_stmt = $conn->prepare("
            SELECT template_id, template_name, evaluation_type, target_department, form_code, revision_date, effective_date_form
            FROM evaluation_templates 
            WHERE template_id = ? 
              AND (target_department IS NULL OR target_department = '' OR target_department = 'All Departments' OR target_department = ?)
              AND evaluation_type IN ($in_clause)
            LIMIT 1
        ");
        $sel_template_stmt->bind_param("is", $selected_template_id, $employee_dept);
    }
    $sel_template_stmt->execute();
    $selected_template = $sel_template_stmt->get_result()->fetch_assoc();
    $sel_template_stmt->close();

    // If template not accessible, clear the selection
    if (!$selected_template) {
        $selected_template_id = 0;
    }

    // Limit to 1 evaluation per template: Check if employee already has a non-draft evaluation for it
    if ($selected_template_id > 0 && !$edit_eval && !$view_mode) {
        $editable_existing_stmt = $conn->prepare("
            SELECT evaluation_id, status 
            FROM evaluations 
            WHERE employee_id = ? 
              AND template_id = ? 
              AND status IN ('Draft', 'Returned', 'Pending Self-Rating')
              AND deleted_at IS NULL 
            ORDER BY updated_at DESC, evaluation_id DESC
            LIMIT 1
        ");
        $editable_existing_stmt->bind_param("ii", $employee_id, $selected_template_id);
        $editable_existing_stmt->execute();
        $editable_existing_eval = $editable_existing_stmt->get_result()->fetch_assoc();
        $editable_existing_stmt->close();

        if ($editable_existing_eval) {
            redirectWith(BASE_URL . '/employee/self-rating.php?edit=' . $editable_existing_eval['evaluation_id'], 'info', 'Continuing your existing self-rating.');
        }

        $existing_stmt = $conn->prepare("
            SELECT evaluation_id, status 
            FROM evaluations 
            WHERE employee_id = ? 
              AND template_id = ? 
              AND status NOT IN ('Draft', 'Returned', 'Rejected', 'Pending Self-Rating')
              AND deleted_at IS NULL 
            LIMIT 1
        ");
        $existing_stmt->bind_param("ii", $employee_id, $selected_template_id);
        $existing_stmt->execute();
        $existing_eval_check = $existing_stmt->get_result()->fetch_assoc();
        $existing_stmt->close();

        if ($existing_eval_check) {
            redirectWith(BASE_URL . '/employee/self-rating.php', 'danger', 'You have already submitted a self-rating for this template (Status: ' . $existing_eval_check['status'] . '). Duplicate evaluations are not allowed.');
        }
    }
}

$assigned_evaluations = $conn->query("
    SELECT ev.evaluation_id, ev.evaluation_type, ev.status, ev.assigned_at, ev.updated_at,
           et.template_name, au.full_name AS assigned_by_name
    FROM evaluations ev
    LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
    LEFT JOIN users au ON ev.assigned_by = au.user_id
    WHERE ev.employee_id = $employee_id
      AND ev.assigned_by IS NOT NULL
      AND ev.status = 'Pending Self-Rating'
      AND ev.deleted_at IS NULL
    ORDER BY COALESCE(ev.assigned_at, ev.updated_at, ev.created_at) DESC, ev.evaluation_id DESC
");
$assigned_pending_count = $assigned_evaluations ? (int) $assigned_evaluations->num_rows : 0;
$pending_template_count = $available_template_count + $assigned_pending_count;

$criteria_kra = [];
$criteria_behavior = [];
if ($selected_template_id > 0) {
    $criteria_query = $conn->query("SELECT * FROM evaluation_criteria WHERE template_id = " . $selected_template_id . " ORDER BY section, sort_order");
    while ($criterion = $criteria_query->fetch_assoc()) {
        if (($criterion['section'] ?? '') === 'Behavior') {
            $criteria_behavior[] = $criterion;
        } else {
            $criteria_kra[] = $criterion;
        }
    }
}

$history = $conn->query("
    SELECT ev.evaluation_id, ev.evaluation_type, ev.status, ev.total_score, ev.performance_level, ev.submitted_date, ev.updated_at,
           et.template_name
    FROM evaluations ev
    LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
    WHERE ev.employee_id = $employee_id
      AND ev.deleted_at IS NULL
    ORDER BY COALESCE(ev.submitted_date, ev.updated_at) DESC, ev.evaluation_id DESC
    LIMIT 10
");

$in_progress_q = $conn->query("
    SELECT ev.evaluation_id, ev.evaluation_type, ev.status, ev.updated_at, ev.created_at,
           et.template_name
    FROM evaluations ev
    LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
    WHERE ev.employee_id = $employee_id
      AND ev.status IN ('Draft', 'Returned', 'Pending Self-Rating')
      AND ev.deleted_at IS NULL
    ORDER BY COALESCE(ev.updated_at, ev.created_at) DESC, ev.evaluation_id DESC
");
$in_progress_evals = $in_progress_q ? $in_progress_q->fetch_all(MYSQLI_ASSOC) : [];
$in_progress_count = count($in_progress_evals);

// Approval history: evaluations confirmed by the logged-in user acting as a supervisor/approver
$approvals_given = [];
$is_supervisor_user = hasSupervisorPrivileges($conn, $employee_id);
if ($is_supervisor_user && $user_id > 0) {
    $approvals_stmt = $conn->prepare("
        SELECT ev.evaluation_id, ev.status, ev.total_score, ev.performance_level,
               ev.supervisor_altered_scores,
               ev.evaluation_period_start, ev.evaluation_period_end,
               ev.dept_supervisor_confirmed_date, ev.supervisor_confirmed_date,
               emp.first_name, emp.last_name, emp.job_title, emp.employee_code,
               et.template_name
        FROM evaluations ev
        JOIN employees emp ON ev.employee_id = emp.employee_id
        LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
        WHERE (ev.dept_supervisor_confirmed_by = ? OR ev.supervisor_confirmed_by = ?)
        ORDER BY COALESCE(ev.dept_supervisor_confirmed_date, ev.supervisor_confirmed_date) DESC
        LIMIT 50
    ");
    $approvals_stmt->bind_param("ii", $user_id, $user_id);
    $approvals_stmt->execute();
    $approvals_given = $approvals_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $approvals_stmt->close();
}
$approvals_given_count = count($approvals_given);

require_once '../includes/header.php';
?>

<style>
#selfRatingRightTabs .nav-link {
    color: var(--color-text-muted) !important;
    border-bottom: 2px solid transparent !important;
    transition: all 0.2s ease-in-out;
}
#selfRatingRightTabs .nav-link:hover {
    color: var(--color-primary) !important;
    background-color: rgba(8, 46, 6, 0.05) !important;
}
#selfRatingRightTabs .nav-link.active {
    color: var(--color-primary) !important;
    border-bottom: 2px solid var(--color-primary-light) !important;
    background: transparent !important;
    font-weight: 700 !important;
}

@media (max-width: 767.98px) {
    .content-card .card-body {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .section-premium-label {
        align-items: flex-start;
        display: flex;
        font-size: 0.92rem;
        gap: 0.45rem;
        line-height: 1.25;
    }

    .self-rating-view-meta {
        row-gap: 0.85rem !important;
    }

    .self-rating-view-meta .form-label {
        font-size: 0.74rem;
        margin-bottom: 0.2rem;
    }

    .self-rating-view-meta .fw-semibold,
    .self-rating-view-meta .badge {
        font-size: 0.88rem;
        line-height: 1.25;
    }

    .self-rating-result-table-wrap {
        overflow: visible;
    }

    .self-rating-result-table {
        border: 0 !important;
        margin-bottom: 0;
    }

    .self-rating-result-table thead {
        display: none;
    }

    .self-rating-result-table tbody,
    .self-rating-result-table tr,
    .self-rating-result-table td {
        display: block;
        width: 100%;
    }

    .self-rating-result-table tr {
        background: #fff;
        border: 1px solid rgba(8, 46, 6, 0.12);
        border-radius: 10px;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
        margin-bottom: 0.85rem;
        overflow: hidden;
    }

    .self-rating-result-table td {
        border: 0;
        padding: 0.72rem 0.85rem !important;
    }

    .self-rating-result-table td + td {
        border-top: 1px solid rgba(8, 46, 6, 0.08);
    }

    .self-rating-result-table td[data-label]::before {
        color: var(--color-text-muted);
        content: attr(data-label);
        display: block;
        font-size: 0.68rem;
        font-weight: 800;
        line-height: 1.2;
        margin-bottom: 0.28rem;
        text-transform: uppercase;
    }

    .self-rating-result-table .badge {
        align-items: flex-start;
        max-width: 100%;
        white-space: normal;
    }

    .self-rating-score-summary .d-flex {
        align-items: stretch !important;
        flex-direction: column;
        gap: 0.85rem;
    }

    .self-rating-score-summary .text-end {
        text-align: left !important;
    }

    .self-rating-actions {
        align-items: stretch !important;
        flex-direction: column;
    }

    .self-rating-actions .btn,
    .self-rating-actions a.btn {
        margin-left: 0 !important;
        width: 100%;
    }

    .self-rating-actions .btn-outline-danger {
        order: 3;
    }
}

/* ── Unanswered rating item highlight ── */
@keyframes rating-shake {
    0%, 100% { transform: translateX(0); }
    20%       { transform: translateX(-6px); }
    40%       { transform: translateX(6px); }
    60%       { transform: translateX(-4px); }
    80%       { transform: translateX(4px); }
}

.rating-item.rating-missing-error {
    border: 2px solid #dc3545 !important;
    border-radius: 12px;
    animation: rating-shake 0.45s ease-in-out;
    position: relative;
}

.rating-item.rating-missing-error::after {
    content: '⚠ Rating Required';
    position: absolute;
    top: -1px;
    right: 10px;
    background: #dc3545;
    color: #fff;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 0 0 6px 6px;
    letter-spacing: 0.03em;
}
</style>

<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">
                Employee Portal · Evaluation</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-star-half-alt me-2"
                    style="color:var(--primary-light);"></i>360-Degree Self Rating</h4>
        </div>
        <div class="d-none d-md-block text-end">
            <div class="badge mb-2 px-3 py-2" style="background-color: #CBA135; color: #1C271B; font-weight: 700; border-radius: 8px;">
                <i class="fas fa-bell me-1"></i><?php echo (int) $pending_template_count; ?> pending · <?php echo (int) $in_progress_count; ?> ongoing
            </div><br>
            <a href="<?php echo BASE_URL; ?>/employee/dashboard.php"
                class="btn btn-outline-light btn-sm rounded-pill px-3 mb-1">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
    <p class="text-white-50 small mb-0 d-none d-md-block"><i class="fas fa-info-circle me-1"></i>Complete your
        self-rating to provide insights for your performance review.</p>
</div>

<?php if ($edit_eval || $view_mode): ?>
<!-- Progress indicator for evaluation workflow -->
<div class="progress-indicator" role="navigation" aria-label="Evaluation progress">
    <?php
    $prog_steps = [
        ['label' => 'Select Template', 'icon' => 'fas fa-list'],
        ['label' => 'KRA Ratings',     'icon' => 'fas fa-tasks'],
        ['label' => 'Behaviour',        'icon' => 'fas fa-user'],
        ['label' => 'Review & Submit',  'icon' => 'fas fa-paper-plane'],
    ];
    // Determine progress: if editing, count scored KRA criteria
    $prog_kra_done  = !empty($edit_scores) ? count(array_filter($edit_scores, fn($v) => $v > 0)) : 0;
    $prog_kra_total = count($criteria_kra);
    $prog_beh_done  = 0;
    foreach ($criteria_behavior as $bc) {
        if (isset($edit_scores[(int)$bc['criterion_id']]) && $edit_scores[(int)$bc['criterion_id']] > 0) $prog_beh_done++;
    }
    $prog_beh_total = count($criteria_behavior);

    if ($view_mode)           { $prog_active = 3; }
    elseif ($prog_beh_total > 0 && $prog_beh_done === $prog_beh_total) { $prog_active = 3; }
    elseif ($prog_kra_total > 0 && $prog_kra_done === $prog_kra_total) { $prog_active = 2; }
    elseif ($prog_kra_done > 0)                                         { $prog_active = 1; }
    else                                                                 { $prog_active = 1; }

    foreach ($prog_steps as $pi => $ps):
        $ps_state = $pi < $prog_active ? 'completed' : ($pi === $prog_active ? 'active' : '');
    ?>
    <div class="progress-step <?php echo $ps_state; ?>" aria-current="<?php echo $pi === $prog_active ? 'step' : 'false'; ?>">
        <div class="progress-step-number">
            <?php if ($pi < $prog_active): ?>
                <i class="fas fa-check" aria-hidden="true"></i>
            <?php else: ?>
                <?php echo $pi + 1; ?>
            <?php endif; ?>
        </div>
        <div class="progress-step-label"><?php echo e($ps['label']); ?></div>
    </div>
    <?php if ($pi < count($prog_steps) - 1): ?>
    <div class="progress-line <?php echo $pi < $prog_active ? 'completed' : ''; ?>"></div>
    <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Mobile-only section -->
<div class="d-md-none d-flex justify-content-between align-items-center mt-3 mb-4 flex-wrap gap-3 fadeup"
    style="animation-delay: 0.1s;">
    <a href="<?php echo BASE_URL; ?>/employee/dashboard.php" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
        <i class="fas fa-arrow-left me-2"></i>Back to My Dashboard
    </a>
    <div class="alert alert-light border-0 shadow-sm py-2 px-3 mb-0"
        style="border-radius: 10px; font-size: 0.85rem; background: #fff;">
        <i class="fas fa-info-circle me-2 text-primary"></i>
        <span class="text-muted fw-500"><?php echo (int) $pending_template_count; ?> pending · <?php echo (int) $in_progress_count; ?> ongoing</span>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <?php if ($assigned_evaluations && $assigned_evaluations->num_rows > 0 && !$view_mode): ?>
            <div class="content-card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-bell me-2"></i>Assigned Evaluations</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-3">
                        <?php while ($assigned_item = $assigned_evaluations->fetch_assoc()): ?>
                            <div class="border rounded p-3">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <div class="fw-semibold">
                                            <?php echo e($assigned_item['template_name'] ?? 'Assigned Template'); ?></div>
                                        <div class="small text-muted">
                                            Assigned by <?php echo e($assigned_item['assigned_by_name'] ?? 'your Head'); ?>
                                            <?php if (!empty($assigned_item['assigned_at'])): ?>
                                                on <?php echo formatDateTime($assigned_item['assigned_at']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small text-muted mt-1">
                                            <?php echo e($assigned_item['evaluation_type'] ?? 'Annual'); ?> evaluation</div>
                                    </div>
                                    <span
                                        class="badge <?php echo getStatusBadgeClass($assigned_item['status']); ?>"><?php echo e($assigned_item['status']); ?></span>
                                </div>
                                <div class="mt-3">
                                    <a href="<?php echo BASE_URL; ?>/employee/self-rating.php?edit=<?php echo (int) $assigned_item['evaluation_id']; ?>"
                                        class="btn btn-sm btn-primary">
                                        <i class="fas fa-play me-1"></i>Start Self Rating
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($view_mode && $view_eval): ?>
            <!-- View Mode -->
            <div class="content-card">
                <div class="card-header">
                    <h5><i class="fas fa-eye me-2"></i>View Self Rating</h5>
                </div>
                <div class="card-body">
                    <?php if ($view_eval && $view_eval['status'] === 'Returned'): ?>
                        <div class="alert alert-warning border-0 mb-4 shadow-sm" style="border-radius: 12px; border-left: 5px solid #dc3545 !important;">
                            <div class="fw-bold text-danger mb-1">
                                <i class="fas fa-undo me-2"></i>Revision Required (Evaluation Returned)
                            </div>
                            <div class="small">
                                This evaluation has been returned for revision.
                            </div>
                            <?php if (!empty($view_eval['supervisor_comments'])): ?>
                                <div class="mt-2 p-2 bg-white rounded border italic small text-dark">
                                    <strong>Supervisor Feedback:</strong> <?php echo nl2br(e($view_eval['supervisor_comments'])); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($view_eval['manager_comments'])): ?>
                                <div class="mt-2 p-2 bg-white rounded border italic small text-dark">
                                    <strong>HR Manager Feedback:</strong> <?php echo nl2br(e($view_eval['manager_comments'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3 mb-4 self-rating-view-meta">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Employee</label>
                            <div class="fw-semibold">
                                <?php echo e(trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''))); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Position</label>
                            <div class="fw-semibold"><?php echo e($employee['job_title'] ?? '—'); ?></div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-muted">Evaluation Type</label>
                            <div class="fw-semibold"><?php echo e($view_eval['evaluation_type'] ?? '—'); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Template</label>
                            <div class="fw-semibold"><?php echo e($view_eval['template_name'] ?? '—'); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Status</label>
                            <div><span
                                    class="badge <?php echo getStatusBadgeClass($view_eval['status']); ?>"><?php echo e($view_eval['status']); ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($view_eval['staff_comments'])): ?>
                        <div class="alert alert-light border mb-4">
                            <label class="form-label fw-semibold">Self Comments:</label>
                            <p class="mb-0"><?php echo nl2br(e($view_eval['staff_comments'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php
                    $view_criteria_kra = [];
                    $view_criteria_behavior = [];
                    if ($selected_template_id > 0) {
                        $view_criteria_query = $conn->query("SELECT * FROM evaluation_criteria WHERE template_id = " . $selected_template_id . " ORDER BY section, sort_order");
                        while ($criterion = $view_criteria_query->fetch_assoc()) {
                            if (($criterion['section'] ?? '') === 'Behavior') {
                                $view_criteria_behavior[] = $criterion;
                            } else {
                                $view_criteria_kra[] = $criterion;
                            }
                        }
                    }
                    ?>

                    <?php if (!empty($view_criteria_kra)): ?>
                        <div class="section-premium-label mb-3">
                            <i class="fas fa-bullseye"></i>KRA Ratings
                        </div>
                        <div class="table-responsive mb-4 self-rating-result-table-wrap">
                            <table class="table table-hover align-middle self-rating-result-table">
                                <thead>
                                    <tr>
                                        <th>Criterion</th>
                                        <th style="width:110px;">Weight</th>
                                        <th style="width:250px;">Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($view_criteria_kra as $criterion): ?>
                                        <?php
                                        $score_data = $view_scores[(int) $criterion['criterion_id']] ?? null;
                                        $original_score = $score_data ? (float)$score_data['score_value'] : 0.00;
                                        $is_eval_approved = (($view_eval['status'] ?? '') === 'Approved');
                                        $dept_mgr_override = ($is_eval_approved && $score_data && $score_data['dept_manager_override_score'] !== null) ? (float)$score_data['dept_manager_override_score'] : null;
                                        $supervisor_override = ($is_eval_approved && $score_data && $score_data['supervisor_override_score'] !== null) ? (float)$score_data['supervisor_override_score'] : null;
                                        $manager_override = ($is_eval_approved && $score_data && $score_data['manager_override_score'] !== null) ? (float)$score_data['manager_override_score'] : null;
                                        
                                        $effective_score = $original_score;
                                        $badge_html = '';
                                        if ($dept_mgr_override !== null) {
                                            $effective_score = $dept_mgr_override;
                                            $badge_html = ' <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 ms-2 rounded-pill small fw-semibold" style="font-size: 0.65rem;" title="Original Self-Rating: ' . number_format($original_score, 2) . '"><i class="fas fa-pen me-1"></i>Adjusted during review</span>';
                                        }
                                        if ($supervisor_override !== null) {
                                            $effective_score = $supervisor_override;
                                            $badge_html = ' <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 ms-2 rounded-pill small fw-semibold" style="font-size: 0.65rem;" title="Original Self-Rating: ' . number_format($original_score, 2) . '"><i class="fas fa-pen me-1"></i>Adjusted during review</span>';
                                        }
                                        if ($manager_override !== null) {
                                            $effective_score = $manager_override;
                                            $badge_html = ' <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-2 rounded-pill small fw-semibold" style="font-size: 0.65rem;" title="Original Self-Rating: ' . number_format($original_score, 2) . '"><i class="fas fa-pen me-1"></i>Adjusted during review</span>';
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo e($criterion['criterion_name']); ?></div>
                                                <?php if (!empty($criterion['description'])): ?>
                                                    <div class="small text-muted"><?php echo e($criterion['description']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Weight"><?php echo e($criterion['weight']); ?>%</td>
                                            <td data-label="Rating">
                                                <span class="badge bg-light text-dark fs-6 fw-bold">
                                                    <?php echo e(number_format($effective_score, 2)); ?>
                                                </span>
                                                <?php echo $badge_html; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($view_criteria_behavior)): ?>
                        <div class="section-premium-label mb-3">
                            <i class="fas fa-heart"></i>Behavior Ratings
                        </div>
                        <div class="table-responsive mb-4 self-rating-result-table-wrap">
                            <table class="table table-hover align-middle self-rating-result-table">
                                <thead>
                                    <tr>
                                        <th>Criterion</th>
                                        <th style="width:250px;">Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($view_criteria_behavior as $criterion): ?>
                                        <?php
                                        $score_data = $view_scores[(int) $criterion['criterion_id']] ?? null;
                                        $original_score = $score_data ? (float)$score_data['score_value'] : 0.00;
                                        $is_eval_approved = (($view_eval['status'] ?? '') === 'Approved');
                                        $dept_mgr_override = ($is_eval_approved && $score_data && $score_data['dept_manager_override_score'] !== null) ? (float)$score_data['dept_manager_override_score'] : null;
                                        $supervisor_override = ($is_eval_approved && $score_data && $score_data['supervisor_override_score'] !== null) ? (float)$score_data['supervisor_override_score'] : null;
                                        $manager_override = ($is_eval_approved && $score_data && $score_data['manager_override_score'] !== null) ? (float)$score_data['manager_override_score'] : null;
                                        
                                        $effective_score = $original_score;
                                        $badge_html = '';
                                        if ($dept_mgr_override !== null) {
                                            $effective_score = $dept_mgr_override;
                                            $badge_html = ' <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 ms-2 rounded-pill small fw-semibold" style="font-size: 0.65rem;" title="Original Self-Rating: ' . number_format($original_score, 2) . '"><i class="fas fa-pen me-1"></i>Adjusted during review</span>';
                                        }
                                        if ($supervisor_override !== null) {
                                            $effective_score = $supervisor_override;
                                            $badge_html = ' <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 ms-2 rounded-pill small fw-semibold" style="font-size: 0.65rem;" title="Original Self-Rating: ' . number_format($original_score, 2) . '"><i class="fas fa-pen me-1"></i>Adjusted during review</span>';
                                        }
                                        if ($manager_override !== null) {
                                            $effective_score = $manager_override;
                                            $badge_html = ' <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-2 rounded-pill small fw-semibold" style="font-size: 0.65rem;" title="Original Self-Rating: ' . number_format($original_score, 2) . '"><i class="fas fa-pen me-1"></i>Adjusted during review</span>';
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo e($criterion['criterion_name']); ?></div>
                                                <?php if (!empty($criterion['description'])): ?>
                                                    <div class="small text-muted"><?php echo e($criterion['description']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Rating">
                                                <span class="badge bg-light text-dark fs-6 fw-bold">
                                                    <?php echo e(number_format($effective_score, 2)); ?>
                                                </span>
                                                <?php echo $badge_html; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <div class="section-premium-label mb-3 mt-4">
                        <i class="fas fa-seedling"></i>Developmental Plan
                    </div>
                    <?php if (($view_eval['status'] ?? '') === 'Approved'): ?>
                        <div class="table-responsive mb-4 self-rating-result-table-wrap">
                            <table class="table table-sm table-hover align-middle border-start self-rating-result-table">
                                <thead class="small text-muted bg-light">
                                    <tr>
                                        <th class="ps-3">Area of Improvement</th>
                                        <th>Support Needed</th>
                                        <th>Time Frame</th>
                                    </tr>
                                </thead>
                                <tbody class="small">
                                    <?php if (!empty($view_dev_plans)): ?>
                                        <?php foreach ($view_dev_plans as $plan): ?>
                                            <tr>
                                                <td class="ps-3"><?php echo e($plan['improvement_area']); ?></td>
                                                <td data-label="Support Needed"><?php echo e($plan['support_needed']); ?></td>
                                                <td data-label="Time Frame"><?php echo e($plan['time_frame']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted small py-3">No developmental plan recorded.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info border d-flex align-items-center gap-3 p-3 mb-4 rounded-3 shadow-sm" style="background-color: #f0fdf4; border-color: #86efac !important; color: #166534;">
                            <i class="fas fa-lock fa-2x text-success"></i>
                            <div class="small">
                                <strong class="d-block mb-1" style="color: #14532d;">Developmental Plan Under Review:</strong>
                                Supervisor action plans and developmental recommendations are currently being formulated and will be published once the evaluation cycle receives final approval.
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (($view_eval['status'] ?? '') === 'Approved'): ?>
                        <?php if (!empty($view_eval['supervisor_comments']) || !empty($view_eval['dept_manager_comments']) || !empty($view_eval['evaluator_comments']) || !empty($view_eval['manager_comments'])): ?>
                            <div class="section-premium-label mb-3 mt-4">
                                <i class="fas fa-comments"></i>Management Remarks & Justifications
                            </div>
                            <div class="row g-3 mb-4">
                                <?php if (!empty($view_eval['supervisor_comments'])): ?>
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded-3 border-start border-warning border-4 shadow-sm">
                                            <div class="fw-bold text-warning small mb-1">
                                                <i class="fas fa-user-shield me-1"></i>Department Supervisor Feedback
                                            </div>
                                            <p class="mb-0 small text-dark" style="white-space: pre-wrap;"><?php echo e($view_eval['supervisor_comments']); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($view_eval['dept_manager_comments'])): ?>
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded-3 border-start border-info border-4 shadow-sm">
                                            <div class="fw-bold text-info small mb-1">
                                                <i class="fas fa-user-shield me-1"></i>Department Manager Remarks
                                            </div>
                                            <p class="mb-0 small text-dark" style="white-space: pre-wrap;"><?php echo e($view_eval['dept_manager_comments']); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($view_eval['evaluator_comments'])): ?>
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded-3 border-start border-primary border-4 shadow-sm">
                                               <div class="fw-bold text-primary small mb-1">
                                                <i class="fas fa-user-tie me-1"></i>HR Supervisor Remarks
                                            </div>
                                            <p class="mb-0 small text-dark" style="white-space: pre-wrap;"><?php echo e($view_eval['evaluator_comments']); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($view_eval['manager_comments'])): ?>
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded-3 border-start border-success border-4 shadow-sm">
                                            <div class="fw-bold text-success small mb-1">
                                                <i class="fas fa-user-check me-1"></i>HR Manager Remarks / Justification
                                            </div>
                                            <p class="mb-0 small text-dark" style="white-space: pre-wrap;"><?php echo e($view_eval['manager_comments']); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="alert alert-info mb-4 self-rating-score-summary">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="small text-muted">Total Score</div>
                                <div class="h4 mb-0"><?php echo (($view_eval['status'] ?? '') === 'Approved') ? e($view_eval['total_score'] ?? '0.00') : 'Pending Final Approval'; ?>
                                    <?php if (($view_eval['status'] ?? '') === 'Approved'): ?>
                                        <span class="badge bg-primary"><?php echo e($view_eval['performance_level'] ?? '—'); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Under Review</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="small text-muted">KRA: <?php echo e($view_eval['kra_subtotal'] ?? '0.00'); ?>
                                </div>
                                <div class="small text-muted">Behavior:
                                    <?php echo e($view_eval['behavior_average'] ?? '0.00'); ?></div>
                            </div>
                        </div>
                    </div>

                    <?php
                    $can_edit = in_array($view_eval['status'], ['Draft', 'Returned', 'Pending Self-Rating'], true);
                    ?>
                    <div class="d-flex flex-wrap justify-content-end gap-2">
                        <?php if ($can_edit): ?>
                            <a href="<?php echo BASE_URL; ?>/employee/self-rating.php?edit=<?php echo (int) $view_eval['evaluation_id']; ?>"
                                class="btn btn-primary">
                                <i class="fas fa-edit me-2"></i>Edit Rating
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded shadow-sm">
                                <div class="small text-muted mb-1 text-uppercase fw-bold" style="font-size: 0.65rem;">Form
                                    Code</div>
                                <div class="fw-bold"><?php echo e($view_eval['form_code'] ?? '—'); ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded shadow-sm">
                                <div class="small text-muted mb-1 text-uppercase fw-bold" style="font-size: 0.65rem;">
                                    Revision Date</div>
                                <div class="fw-bold">
                                    <?php echo !empty($view_eval['revision_date']) ? formatDate($view_eval['revision_date']) : '—'; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded shadow-sm">
                                <div class="small text-muted mb-1 text-uppercase fw-bold" style="font-size: 0.65rem;">
                                    Effective Date</div>
                                <div class="fw-bold">
                                    <?php echo !empty($view_eval['effective_date_form']) ? formatDate($view_eval['effective_date_form']) : '—'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Edit/New Mode -->
            <div class="content-card">
                <div class="card-header">
                    <h5><i
                            class="fas fa-star me-2"></i><?php echo $edit_eval ? 'Continue Self Rating' : 'New Self Rating'; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" data-autosave="self-rating-form" data-validate novalidate autocomplete="off">
                        <?php if ($edit_eval): ?>
                            <input type="hidden" name="edit_id" value="<?php echo (int) $edit_eval['evaluation_id']; ?>">
                        <?php endif; ?>

                        <?php if ($edit_eval && $edit_eval['status'] === 'Returned'): ?>
                            <div class="alert alert-warning border-0 mb-4 shadow-sm" style="border-radius: 12px; border-left: 5px solid #dc3545 !important;">
                                <div class="fw-bold text-danger mb-1">
                                    <i class="fas fa-undo me-2"></i>Revision Required (Evaluation Returned)
                                </div>
                                <div class="small mb-2">
                                    This evaluation has been returned to you for revision. Please review the feedback below and update your self-rating accordingly.
                                </div>
                                <?php if (!empty($edit_eval['supervisor_comments'])): ?>
                                    <div class="mt-2 p-2 bg-white rounded border italic small text-dark">
                                        <strong>Supervisor Feedback:</strong> <?php echo nl2br(e($edit_eval['supervisor_comments'])); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($edit_eval['dept_manager_comments'])): ?>
                                    <div class="mt-2 p-2 bg-white rounded border italic small text-dark">
                                        <strong>Department Manager Feedback:</strong> <?php echo nl2br(e($edit_eval['dept_manager_comments'])); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($edit_eval['evaluator_comments'])): ?>
                                    <div class="mt-2 p-2 bg-white rounded border italic small text-dark">
                                        <strong>HR Supervisor Feedback:</strong> <?php echo nl2br(e($edit_eval['evaluator_comments'])); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($edit_eval['manager_comments'])): ?>
                                    <div class="mt-2 p-2 bg-white rounded border italic small text-dark">
                                        <strong>HR Manager Feedback:</strong> <?php echo nl2br(e($edit_eval['manager_comments'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($is_assigned_edit && $edit_eval): ?>
                            <div class="alert alert-primary border-0 mb-4 shadow-sm">
                                <div class="fw-semibold mb-1"><i class="fas fa-user-check me-2"></i>Assigned by Head</div>
                                <div class="small">
                                    <?php echo e($edit_eval['assigned_by_name'] ?? 'Your Head'); ?> assigned this evaluation to
                                    you.
                                    Complete your ratings, then submit it for review.
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- 1. Primary Action: Template Selection at TOP -->
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <label class="form-label fw-bold text-primary" style="font-size: 0.95rem;">
                                    <i class="fas fa-file-signature me-2"></i>Select Evaluation Template
                                </label>
                                <?php if ($is_assigned_edit && $selected_template): ?>
                                    <input type="hidden" name="template_id"
                                        value="<?php echo (int) $selected_template['template_id']; ?>">
                                    <input type="text" class="form-control form-control-lg fw-semibold bg-light"
                                        value="<?php echo e($selected_template['template_name']); ?>" readonly>
                                    <small class="text-muted mt-1 d-block"><i class="fas fa-lock me-1"></i>This template was
                                        assigned by your Head and cannot be changed.</small>
                                <?php elseif ($edit_eval && $selected_template): ?>
                                    <input type="hidden" name="template_id" value="<?php echo (int) $selected_template['template_id']; ?>">
                                    <input type="text" class="form-control form-control-lg fw-semibold bg-light" value="<?php echo e($selected_template['template_name']); ?>" readonly>
                                    <small class="text-muted mt-1 d-block"><i class="fas fa-lock me-1"></i>Template is locked for this draft.</small>
                                <?php else: ?>
                                    <select class="form-select form-select-lg border-primary shadow-sm" name="template_id" id="templateSelect" autocomplete="off"
                                        onchange="if(this.value){ window.location='?template=' + this.value; } else { window.location='self-rating.php'; }"
                                        required>
                                        <option value="" disabled <?php echo $selected_template_id <= 0 ? 'selected' : ''; ?>>-- Choose an Active Template --</option>
                                        <?php while ($template = $templates->fetch_assoc()): ?>
                                            <?php
                                            $template_label = $template['template_name'] . ' (' . (float) $template['kra_weight'] . '% KRA / ' . (float) $template['behavior_weight'] . '% Behavior)';
                                            $opt_display = str_replace(['All Departments', 'Template'], ['All Depts', 'Temp'], $template['template_name']);
                                            $opt_display .= ' (' . (int)$template['kra_weight'] . '/' . (int)$template['behavior_weight'] . ')';
                                            ?>
                                            <option value="<?php echo (int) $template['template_id']; ?>" title="<?php echo e($template_label); ?>" data-title="<?php echo e($template_label); ?>" <?php echo $selected_template_id === (int) $template['template_id'] ? 'selected' : ''; ?>>
                                                <?php echo e($opt_display); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 2. Employee Info & Evaluation Type -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Employee</label>
                                <input type="text" class="form-control"
                                    value="<?php echo e(trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''))); ?>"
                                    readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Position</label>
                                <input type="text" class="form-control"
                                    value="<?php echo e($employee['job_title'] ?? '—'); ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Evaluation Type</label>
                                <?php
                                if ($edit_eval) {
                                    $display_eval_type = $edit_eval['evaluation_type'] ?? 'Annual';
                                } elseif ($selected_template) {
                                    $display_eval_type = $selected_template['evaluation_type'] ?? 'Annual';
                                } else {
                                    $display_eval_type = '—';
                                }
                                ?>
                                <input type="text" class="form-control fw-bold text-success" value="<?php echo e($display_eval_type); ?>"
                                    readonly>
                                <?php if ($edit_eval): ?>
                                    <input type="hidden" name="evaluation_type"
                                        value="<?php echo e($edit_eval['evaluation_type'] ?? 'Annual'); ?>">
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 3. Template Metadata Details -->
                        <?php
                        $disp_form_code = $edit_eval['form_code'] ?? ($selected_template['form_code'] ?? '—');
                        $disp_rev_date = $edit_eval['revision_date'] ?? ($selected_template['revision_date'] ?? '');
                        $disp_eff_date = $edit_eval['effective_date_form'] ?? ($selected_template['effective_date_form'] ?? '');
                        ?>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded border shadow-sm">
                                    <div class="small text-muted mb-1 text-uppercase fw-bold" style="font-size: 0.65rem;">
                                        Form Code</div>
                                    <div class="fw-bold text-primary"><?php echo e($disp_form_code); ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded border shadow-sm">
                                    <div class="small text-muted mb-1 text-uppercase fw-bold" style="font-size: 0.65rem;">
                                        Revision Date</div>
                                    <div class="fw-bold">
                                        <?php echo !empty($disp_rev_date) ? formatDate($disp_rev_date) : '—'; ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded border shadow-sm">
                                    <div class="small text-muted mb-1 text-uppercase fw-bold" style="font-size: 0.65rem;">
                                        Effective Date</div>
                                    <div class="fw-bold">
                                        <?php echo !empty($disp_eff_date) ? formatDate($disp_eff_date) : '—'; ?></div>
                                </div>
                            </div>
                        </div>

                        <?php if ($selected_template_id > 0 && (!empty($criteria_kra) || !empty($criteria_behavior))): ?>
                        <!-- Evaluation Period (start & end) - AUTOMATIC DISPLAY ONLY -->
                        <?php 
                        // Compute on the fly for display and hidden inputs
                        $display_start = $edit_eval['evaluation_period_start'] ?? '';
                        $display_end = $edit_eval['evaluation_period_end'] ?? '';
                        
                        if (empty($display_start) || empty($display_end)) {
                            $hire_date = $employee['hire_date'] ?? date('Y-m-d');
                            $eval_type = $edit_eval['evaluation_type'] ?? ($selected_template['evaluation_type'] ?? 'Annual');
                            
                            if ($eval_type === 'Annual') {
                                $display_start = date('Y') . '-01-01';
                                $display_end = date('Y') . '-12-31';
                            } elseif ($eval_type === 'Quarterly') {
                                $current_month = (int)date('n');
                                $current_year = date('Y');
                                if ($current_month <= 3) {
                                    $display_start = $current_year . '-01-01';
                                    $display_end = $current_year . '-03-31';
                                } elseif ($current_month <= 6) {
                                    $display_start = $current_year . '-04-01';
                                    $display_end = $current_year . '-06-30';
                                } elseif ($current_month <= 9) {
                                    $display_start = $current_year . '-07-01';
                                    $display_end = $current_year . '-09-30';
                                } else {
                                    $display_start = $current_year . '-10-01';
                                    $display_end = $current_year . '-12-31';
                                }
                            } elseif ($eval_type === 'Initial') {
                                $display_start = $hire_date;
                                $display_end = date('Y-m-d', strtotime('+3 months', strtotime($hire_date)));
                            } elseif ($eval_type === 'Final') {
                                $display_start = $hire_date;
                                $display_end = date('Y-m-d', strtotime('+6 months', strtotime($hire_date)));
                            } else {
                                $display_start = date('Y') . '-01-01';
                                $display_end = date('Y') . '-12-31';
                            }
                        }
                        ?>
                        <input type="hidden" name="period_start" value="<?php echo e($display_start); ?>">
                        <input type="hidden" name="period_end" value="<?php echo e($display_end); ?>">

                            <?php
                            // Rating scale definitions (1–4 matching the system's 0.00–4.00 range)
                            $rating_scale = [
                                1 => ['label' => '1 - Needs Improvement',    'text' => 'Needs Improvement'],
                                2 => ['label' => '2 - Exceeds Expectations', 'text' => 'Exceeds Expectations'],
                                3 => ['label' => '3 - Meets Expectations',   'text' => 'Meets Expectations'],
                                4 => ['label' => '4 - Outstanding',          'text' => 'Outstanding'],
                            ];
                            ?>

                            <!-- KRA Ratings Section -->
                            <div class="rating-section">
                                <h2 class="rating-section-title">
                                    <i class="fas fa-bullseye me-2" aria-hidden="true"></i>KRA Self Rating
                                </h2>

                                <?php foreach ($criteria_kra as $criterion): ?>
                                    <?php
                                    $cid      = (int) $criterion['criterion_id'];
                                    $saved    = isset($edit_scores[$cid]) && $edit_scores[$cid] > 0
                                                    ? (int) round((float) $edit_scores[$cid])
                                                    : 0;
                                    $field_id = 'kra_criterion_' . $cid;
                                    ?>
                                    <div class="rating-item">
                                        <div class="rating-header">
                                            <h3 class="rating-title">
                                                <?php echo e($criterion['criterion_name']); ?>
                                                <span class="badge bg-secondary ms-2" style="font-size:0.75rem;font-weight:600;">
                                                    Weight: <?php echo e($criterion['weight']); ?>%
                                                </span>
                                            </h3>
                                            <?php if (!empty($criterion['description'])): ?>
                                                <p class="rating-description"><?php echo e($criterion['description']); ?></p>
                                            <?php endif; ?>
                                        </div>

                                        <fieldset>
                                            <legend class="visually-hidden">Rating for <?php echo e($criterion['criterion_name']); ?></legend>
                                            <div class="rating-scale" role="radiogroup" aria-label="Rating scale for <?php echo e($criterion['criterion_name']); ?>">
                                                <?php foreach ($rating_scale as $val => $scale): ?>
                                                    <div class="rating-option">
                                                        <input
                                                            type="radio"
                                                            class="rating-input"
                                                            id="<?php echo $field_id . '_' . $val; ?>"
                                                            name="kra_scores[<?php echo $cid; ?>]"
                                                            value="<?php echo $val; ?>"
                                                            <?php echo $saved === $val ? 'checked' : ''; ?>
                                                            required
                                                            aria-label="<?php echo e($scale['label']); ?>"
                                                        >
                                                        <label class="rating-label" for="<?php echo $field_id . '_' . $val; ?>">
                                                            <span class="rating-number" aria-hidden="true"><?php echo $val; ?></span>
                                                            <span class="rating-text"><?php echo e($scale['text']); ?></span>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </fieldset>
                                    </div>
                                <?php endforeach; ?>
                            </div><!-- /.rating-section (KRA) -->

                            <!-- Behavior Ratings Section -->
                            <div class="rating-section">
                                <h2 class="rating-section-title">
                                    <i class="fas fa-heart me-2" aria-hidden="true"></i>Behavior Self Rating
                                </h2>

                                <?php foreach ($criteria_behavior as $criterion): ?>
                                    <?php
                                    $cid      = (int) $criterion['criterion_id'];
                                    $saved    = isset($edit_scores[$cid]) && $edit_scores[$cid] > 0
                                                    ? (int) round((float) $edit_scores[$cid])
                                                    : 0;
                                    $field_id = 'beh_criterion_' . $cid;
                                    ?>
                                    <div class="rating-item">
                                        <div class="rating-header">
                                            <h3 class="rating-title"><?php echo e($criterion['criterion_name']); ?></h3>
                                            <?php if (!empty($criterion['description'])): ?>
                                                <p class="rating-description"><?php echo e($criterion['description']); ?></p>
                                            <?php endif; ?>
                                        </div>

                                        <fieldset>
                                            <legend class="visually-hidden">Rating for <?php echo e($criterion['criterion_name']); ?></legend>
                                            <div class="rating-scale" role="radiogroup" aria-label="Rating scale for <?php echo e($criterion['criterion_name']); ?>">
                                                <?php foreach ($rating_scale as $val => $scale): ?>
                                                    <div class="rating-option">
                                                        <input
                                                            type="radio"
                                                            class="rating-input"
                                                            id="<?php echo $field_id . '_' . $val; ?>"
                                                            name="beh_scores[<?php echo $cid; ?>]"
                                                            value="<?php echo $val; ?>"
                                                            <?php echo $saved === $val ? 'checked' : ''; ?>
                                                            required
                                                            aria-label="<?php echo e($scale['label']); ?>"
                                                        >
                                                        <label class="rating-label" for="<?php echo $field_id . '_' . $val; ?>">
                                                            <span class="rating-number" aria-hidden="true"><?php echo $val; ?></span>
                                                            <span class="rating-text"><?php echo e($scale['text']); ?></span>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </fieldset>
                                    </div>
                                <?php endforeach; ?>
                            </div><!-- /.rating-section (Behavior) -->

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Self Comments</label>
                                <textarea class="form-control" name="self_comments" rows="4"
                                    placeholder="Share any notes about your self-rating..."><?php echo e($edit_eval['staff_comments'] ?? ''); ?></textarea>
                            </div>

                            <!-- Declaration Consent & HTML5 Digital Signature Pad -->
                            <div class="card border border-primary border-opacity-25 rounded-3 mb-4 shadow-sm" style="background: #f8faf6;">
                                <div class="card-header bg-primary bg-opacity-10 fw-bold text-primary py-2" style="font-size:0.9rem;">
                                    <i class="fas fa-file-contract me-2"></i>Declaration Consent & Digital Signature
                                </div>
                                <div class="card-body p-3">
                                    <!-- Consent Disclaimer Text -->
                                    <div class="p-3 bg-white rounded border mb-3 small text-secondary shadow-sm" style="line-height: 1.6;">
                                        <i class="fas fa-quote-left text-primary opacity-50 me-2"></i>
                                        I hereby declare and certify that the scores, self-assessment ratings, and comments provided in this form are accurate, complete, and submitted voluntarily. I understand that this submission forms an official component of my employee performance appraisal record.
                                    </div>
                                    
                                    <!-- Mandatory Consent Checkbox -->
                                    <div class="form-check mb-4">
                                        <input class="form-check-input" type="checkbox" name="employee_consent_agreed" id="employee_consent_agreed" value="1" <?php echo !empty($edit_eval['employee_consent_agreed']) ? 'checked' : ''; ?> required>
                                        <label class="form-check-label fw-bold text-dark small" for="employee_consent_agreed">
                                            I have read, understood, and agree to the declaration statement above.
                                        </label>
                                    </div>

                                    <!-- Digital Signature Canvas Pad -->
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label fw-semibold small text-dark mb-0">
                                                <i class="fas fa-pen-nib me-1 text-primary"></i>Employee Digital Signature
                                            </label>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:0.75rem;" onclick="clearSignatureCanvas()">
                                                <i class="fas fa-eraser me-1"></i>Clear Signature
                                            </button>
                                        </div>
                                        
                                        <div class="signature-canvas-wrapper border rounded bg-white shadow-sm position-relative text-center" style="touch-action: none;">
                                            <canvas id="signatureCanvas" width="500" height="150" style="width: 100%; height: 150px; cursor: crosshair; display: block;"></canvas>
                                            <input type="hidden" name="employee_signature_data" id="employee_signature_data" value="<?php echo e($edit_eval['employee_signature_data'] ?? ''); ?>">
                                        </div>
                                        <div class="form-text small text-muted"><i class="fas fa-info-circle me-1"></i>Use your mouse or finger (on touchscreens) to draw your signature inside the box above.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap justify-content-end gap-2 self-rating-actions">
                                <?php if ($edit_eval): ?>
                                    <a href="?discard=<?php echo (int) $edit_eval['evaluation_id']; ?>" class="btn btn-outline-danger me-auto" onclick="return confirm('Are you sure you want to discard this draft? This action cannot be undone.');">
                                        <i class="fas fa-trash me-2"></i>Discard Draft
                                    </a>
                                <?php endif; ?>
                                <button type="submit" name="submit_action" value="draft" class="btn btn-outline-secondary">
                                    <i class="fas fa-save me-2"></i>Save Draft
                                </button>
                                <button type="button" class="btn btn-primary" onclick="showReviewModal()">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Self Rating
                                </button>
                            </div>
                        <?php else: ?>
                            <?php if (!empty($in_progress_evals)): ?>
                                <div class="in-progress-section">
                                    <h5 class="fw-bold mb-3 text-primary"><i class="fas fa-clock me-2"></i>In-Progress Evaluations <span class="badge bg-primary ms-1"><?php echo (int) $in_progress_count; ?></span></h5>
                                    <div class="row g-3 mb-4">
                                        <?php foreach ($in_progress_evals as $ip_eval): ?>
                                            <div class="col-md-6">
                                                <div class="card border border-primary border-opacity-10 h-100 shadow-sm" style="border-radius: 12px; background: #fafcf8;">
                                                    <div class="card-body p-3 d-flex flex-column justify-content-between">
                                                        <div>
                                                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                                                <h6 class="fw-bold text-dark mb-0" style="font-size:0.9rem;"><?php echo e($ip_eval['template_name'] ?? 'Evaluation'); ?></h6>
                                                                <span class="badge <?php echo getStatusBadgeClass($ip_eval['status']); ?>"><?php echo e($ip_eval['status']); ?></span>
                                                            </div>
                                                            <div class="small text-muted mb-1" style="font-size:0.75rem;">Type: <?php echo e($ip_eval['evaluation_type']); ?></div>
                                                            <div class="small text-muted mb-3" style="font-size:0.75rem;"><i class="fas fa-edit me-1"></i>Last updated: <?php echo formatDateTime($ip_eval['updated_at'] ?? $ip_eval['created_at']); ?></div>
                                                        </div>
                                                        <div class="d-flex gap-2">
                                                            <a href="?edit=<?php echo (int) $ip_eval['evaluation_id']; ?>" class="btn btn-sm btn-primary w-100 rounded-pill" style="font-size:0.75rem;">
                                                                <i class="fas fa-play me-1"></i>Continue
                                                            </a>
                                                            <a href="?discard=<?php echo (int) $ip_eval['evaluation_id']; ?>" class="btn btn-sm btn-outline-danger rounded-pill px-2" style="font-size:0.75rem;" onclick="return confirm('Are you sure you want to discard this draft? This action cannot be undone.');">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="border-top my-4"></div>
                                    <p class="text-muted small"><i class="fas fa-info-circle me-1"></i>Or choose a template below to start a new self-rating.</p>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-file-signature d-block"></i>
                                    <p class="mb-0">Select an active template to start your self-rating.</p>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-xl-4">
        <div class="content-card mb-4">
            <div class="card-header p-0">
                <ul class="nav nav-tabs card-header-tabs m-0 border-bottom-0" id="selfRatingRightTabs" role="tablist">
                    <li class="nav-item" role="presentation" style="flex: 1;">
                        <button class="nav-link active w-100 text-center py-3 fw-bold border-0" id="help-tab" data-bs-toggle="tab" data-bs-target="#help-tab-pane" type="button" role="tab" aria-controls="help-tab-pane" aria-selected="true" style="border-radius: var(--radius-lg) 0 0 0; background: transparent; color: inherit;">
                            <i class="fas fa-question-circle me-1"></i>How It Works
                        </button>
                    </li>
                    <li class="nav-item" role="presentation" style="flex: 1;">
                        <button class="nav-link w-100 text-center py-3 fw-bold border-0" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-tab-pane" type="button" role="tab" aria-controls="history-tab-pane" aria-selected="false" style="border-radius: 0; background: transparent; color: inherit;">
                            <i class="fas fa-history me-1"></i>My Evaluations
                        </button>
                    </li>
                    <?php if ($is_supervisor_user): ?>
                    <li class="nav-item" role="presentation" style="flex: 1;">
                        <button class="nav-link w-100 text-center py-3 fw-bold border-0 position-relative" id="approvals-tab" data-bs-toggle="tab" data-bs-target="#approvals-tab-pane" type="button" role="tab" aria-controls="approvals-tab-pane" aria-selected="false" style="border-radius: 0 var(--radius-lg) 0 0; background: transparent; color: inherit;">
                            <i class="fas fa-check-circle me-1"></i>Approvals
                            <?php if ($approvals_given_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success" style="font-size:0.6rem; margin-top:8px; margin-left:-18px;"><?php echo $approvals_given_count; ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="card-body tab-content" id="selfRatingRightTabsContent">
                <div class="tab-pane fade show active" id="help-tab-pane" role="tabpanel" aria-labelledby="help-tab" tabindex="0">
                    <h5 class="mb-4 fw-bold text-primary"><i class="fas fa-route me-2"></i>How It Works</h5>
                    <div class="help-stepper">
                        <div class="help-step d-flex gap-3 mb-4">
                            <div class="help-step-icon bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center" style="width:36px; height:36px; flex-shrink:0;">
                                <i class="fas fa-list-ol"></i>
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold">1. Select Template</h6>
                                <p class="text-muted small mb-0">Choose an active evaluation template. If one was assigned to you by HRD, it will appear here automatically.</p>
                            </div>
                        </div>
                        <div class="help-step d-flex gap-3 mb-4">
                            <div class="help-step-icon bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width:36px; height:36px; flex-shrink:0;">
                                <i class="fas fa-edit"></i>
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold">2. Rate Yourself</h6>
                                <p class="text-muted small mb-0">Score each KRA and Behavior criterion from 1–4. You can save a draft anytime and return to finish later.</p>
                            </div>
                        </div>
                        <div class="help-step d-flex gap-3 mb-4">
                            <div class="help-step-icon bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center" style="width:36px; height:36px; flex-shrink:0;">
                                <i class="fas fa-paper-plane"></i>
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold">3. Submit to Immediate Head</h6>
                                <p class="text-muted small mb-0">Once submitted, your ratings are locked. Your Immediate Head (Branch Supervisor or Branch Manager) will be notified to review.</p>
                            </div>
                        </div>
                        <div class="help-step d-flex gap-3 mb-4">
                            <div class="help-step-icon bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center" style="width:36px; height:36px; flex-shrink:0;">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold">4. Immediate Head Confirms</h6>
                                <p class="text-muted small mb-0">Your Immediate Head reviews and may adjust your scores. They can confirm and forward it, or return it to you for revision.</p>
                            </div>
                        </div>
                        <div class="help-step d-flex gap-3 mb-4">
                            <div class="help-step-icon bg-secondary bg-opacity-10 text-secondary rounded-circle d-flex align-items-center justify-content-center" style="width:36px; height:36px; flex-shrink:0;">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold">5. Department Manager Endorses <span class="badge bg-secondary" style="font-size:.65rem;">if applicable</span></h6>
                                <p class="text-muted small mb-0">If your branch has a Department Manager, they will receive the evaluation for endorsement before it goes to HRD.</p>
                            </div>
                        </div>
                        <div class="help-step d-flex gap-3 mb-4">
                            <div class="help-step-icon bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center" style="width:36px; height:36px; flex-shrink:0;">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold">6. HR Consolidation</h6>
                                <p class="text-muted small mb-0">The HR Supervisor consolidates all submitted ratings for the period before forwarding to the HR Manager for final approval.</p>
                            </div>
                        </div>
                        <div class="help-step d-flex gap-3">
                            <div class="help-step-icon bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center" style="width:36px; height:36px; flex-shrink:0;">
                                <i class="fas fa-check-double"></i>
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold">7. HR Manager Approves</h6>
                                <p class="text-muted small mb-0">The HR Manager gives final approval. You will be notified once your evaluation is officially approved and recorded.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="history-tab-pane" role="tabpanel" aria-labelledby="history-tab" tabindex="0">
                    <h5 class="mb-4 fw-bold text-primary"><i class="fas fa-history me-2"></i>Recent Evaluations</h5>
                    <?php if ($history->num_rows === 0): ?>
                        <div class="empty-state py-4">
                            <i class="fas fa-inbox d-block"></i>
                            <p class="mb-0">No self-ratings yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="d-grid gap-3">
                            <?php while ($item = $history->fetch_assoc()): ?>
                                <div class="border rounded p-3">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <div class="fw-semibold"><?php echo e($item['template_name'] ?? 'Template'); ?></div>
                                            <div class="small text-muted"><?php echo e($item['evaluation_type'] ?? 'Evaluation'); ?>
                                            </div>
                                        </div>
                                        <span
                                            class="badge <?php echo getStatusBadgeClass($item['status']); ?>"><?php echo e($item['status']); ?></span>
                                    </div>
                                    <div class="small text-muted mt-2">
                                        Updated: <?php echo formatDateTime($item['updated_at'] ?? ''); ?>
                                    </div>
                                    <div class="small mt-1">
                                        Score: <strong><?php echo e($item['total_score'] ?? '0.00'); ?></strong>
                                        <?php if (!empty($item['performance_level'])): ?>
                                            <span class="text-muted">• <?php echo e($item['performance_level']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-3">
                                        <a href="<?php echo BASE_URL; ?>/employee/self-rating.php?view=<?php echo (int) $item['evaluation_id']; ?>"
                                            class="btn btn-sm btn-outline-info me-2">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                        <?php
                                        $can_edit_item = in_array($item['status'], ['Draft', 'Returned', 'Pending Self-Rating'], true);
                                        ?>
                                        <?php if ($can_edit_item): ?>
                                            <a href="<?php echo BASE_URL; ?>/employee/self-rating.php?edit=<?php echo (int) $item['evaluation_id']; ?>"
                                                class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($is_supervisor_user): ?>
                <div class="tab-pane fade" id="approvals-tab-pane" role="tabpanel" aria-labelledby="approvals-tab" tabindex="0">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-check-circle me-2"></i>Approvals Given</h5>
                        <?php if ($approvals_given_count > 0): ?>
                        <span class="badge bg-success rounded-pill"><?php echo $approvals_given_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-muted small mb-3">Self-rating evaluations you have reviewed and confirmed as an immediate head.</p>
                    <?php if (empty($approvals_given)): ?>
                        <div class="empty-state py-4">
                            <i class="fas fa-clipboard-check d-block"></i>
                            <p class="mb-0">No approvals recorded yet.</p>
                            <div class="small text-muted mt-1">Evaluations you confirm will appear here.</div>
                        </div>
                    <?php else: ?>
                        <div class="d-grid gap-3">
                            <?php foreach ($approvals_given as $appr):
                                $confirmed_on = $appr['dept_supervisor_confirmed_date'] ?? $appr['supervisor_confirmed_date'] ?? null;
                                $appr_status_classes = [
                                    'Pending Dept Manager'     => 'bg-warning text-dark',
                                    'Pending HR Consolidation' => 'bg-info text-dark',
                                    'Pending Manager'          => 'bg-info text-dark',
                                    'Approved'                 => 'bg-success',
                                    'Returned'                 => 'bg-danger',
                                ];
                                $appr_sc = $appr_status_classes[$appr['status']] ?? 'bg-secondary';
                                $appr_labels = [
                                    'Pending Dept Manager'     => 'Sent to Dept Mgr',
                                    'Pending HR Consolidation' => 'Sent to HR',
                                    'Pending Manager'          => 'Pending HR Mgr',
                                    'Approved'                 => 'Approved',
                                    'Returned'                 => 'Returned',
                                ];
                                $appr_sl = $appr_labels[$appr['status']] ?? e($appr['status']);
                            ?>
                            <div class="border rounded-3 p-3" style="border-left: 3px solid var(--color-primary-light) !important;">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                    <div>
                                        <div class="fw-semibold small"><?php echo e($appr['last_name'] . ', ' . $appr['first_name']); ?></div>
                                        <div class="text-muted" style="font-size:0.72rem;"><?php echo e($appr['job_title'] ?? '—'); ?></div>
                                    </div>
                                    <span class="badge <?php echo $appr_sc; ?> rounded-pill" style="font-size:0.65rem; white-space:nowrap;"><?php echo $appr_sl; ?></span>
                                </div>
                                <div class="small text-muted mb-1"><?php echo e($appr['template_name'] ?? '—'); ?></div>
                                <?php if (!empty($appr['evaluation_period_start'])): ?>
                                <div style="font-size:0.72rem;" class="text-muted mb-1">
                                    <?php echo formatDate($appr['evaluation_period_start']); ?> – <?php echo formatDate($appr['evaluation_period_end']); ?>
                                </div>
                                <?php endif; ?>
                                <div class="d-flex align-items-center justify-content-between mt-2">
                                    <div style="font-size:0.75rem;">
                                        Score: <strong class="text-primary"><?php echo number_format((float)($appr['total_score'] ?? 0), 2); ?></strong>
                                        <?php if (!empty($appr['supervisor_altered_scores'])): ?>
                                            <span class="badge bg-warning text-dark ms-1" style="font-size:0.6rem;" title="You adjusted the original scores"><i class="fas fa-pen me-1"></i>Altered</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($confirmed_on): ?>
                                    <div style="font-size:0.65rem;" class="text-muted text-end">
                                        <i class="fas fa-calendar-check me-1"></i><?php echo formatDate($confirmed_on); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2">
                                    <a href="<?php echo BASE_URL; ?>/employee/evaluation-history.php" class="btn btn-sm btn-outline-secondary rounded-pill w-100" style="font-size:0.72rem;">
                                        <i class="fas fa-history me-1"></i>View Evaluation History
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down review-modal-dialog">
        <div class="modal-content border-0 shadow-lg review-modal">
            <div class="modal-header bg-primary text-white border-0 review-modal-header">
                <div>
                    <div class="review-modal-eyebrow">Final check</div>
                    <h5 class="modal-title fw-bold"><i class="fas fa-search me-2"></i>Review Your Self-Rating</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body review-modal-body">
                <div class="review-warning">
                    <div class="review-warning-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <strong>Before you submit</strong>
                        <p>Once submitted, you can no longer edit your ratings. Please review carefully below.</p>
                    </div>
                </div>

                <div id="reviewContent">
                    <!-- Dynamic content will be injected here -->
                </div>
            </div>
            <div class="modal-footer bg-light border-0 review-modal-footer">
                <button type="button" class="btn btn-outline-secondary rounded-pill review-modal-action" data-bs-dismiss="modal">
                    <i class="fas fa-arrow-left me-2"></i>Go Back & Edit
                </button>
                <button type="button" class="btn btn-primary rounded-pill review-modal-action" id="btnConfirmSubmit" onclick="confirmFinalSubmit()">
                    <i class="fas fa-check-circle me-2"></i>Confirm & Submit
                </button>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Get the selected value for a radio group by name.
 * Returns null if nothing is selected.
 */
function getRadioValue(name) {
    const checked = document.querySelector(`input[name="${CSS.escape(name)}"]:checked`);
    return checked ? checked.value : null;
}

/**
 * Get the label text for a selected radio option.
 */
function getRadioLabel(name) {
    const checked = document.querySelector(`input[name="${CSS.escape(name)}"]:checked`);
    if (!checked) return null;
    const label = document.querySelector(`label[for="${CSS.escape(checked.id)}"]`);
    return label ? label.querySelector('.rating-text')?.innerText || checked.value : checked.value;
}

function escapeReviewHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function buildReviewSection(title, inputPrefix, badgeClass) {
    const names = new Set();
    document.querySelectorAll(`input[name^="${inputPrefix}"]`).forEach(input => names.add(input.name));

    let section = `
        <section class="review-section">
            <div class="review-section-heading">
                <span>${escapeReviewHtml(title)}</span>
                <span>${names.size} item${names.size === 1 ? '' : 's'}</span>
            </div>
            <div class="review-list">
    `;

    names.forEach(name => {
        const checkedInput = document.querySelector(`input[name="${CSS.escape(name)}"]:checked`);
        const ratingItem = document.querySelector(`input[name="${CSS.escape(name)}"]`)?.closest('.rating-item');
        const criterion = ratingItem?.querySelector('.rating-title')?.childNodes[0]?.textContent?.trim() || name;
        const value = checkedInput ? checkedInput.value : null;
        const labelText = checkedInput
            ? (document.querySelector(`label[for="${CSS.escape(checkedInput.id)}"] .rating-text`)?.innerText || value)
            : 'Not rated';
        const stateClass = checkedInput ? badgeClass : 'review-rating-missing';

        section += `
            <article class="review-rating-card">
                <div class="review-rating-criterion">${escapeReviewHtml(criterion)}</div>
                <div class="review-rating-value ${stateClass}">
                    <span>${value ? escapeReviewHtml(value) : '--'}</span>
                    <small>${escapeReviewHtml(labelText)}</small>
                </div>
            </article>
        `;
    });

    section += `
            </div>
        </section>
    `;

    return section;
}

const reviewPositionText = <?php echo json_encode($employee['job_title'] ?? ''); ?>;
const reviewTemplateText = <?php echo json_encode($selected_template['template_name'] ?? ''); ?>;

function validateAllRatings() {
    // Collect every unique radio group name for kra_scores and beh_scores
    const groupNames = new Set();
    document.querySelectorAll('input[name^="kra_scores["], input[name^="beh_scores["]').forEach(inp => {
        groupNames.add(inp.name);
    });

    const missing = [];
    groupNames.forEach(name => {
        const checked = document.querySelector(`input[name="${CSS.escape(name)}"]:checked`);
        if (!checked) missing.push(name);
    });

    // Clear previous error highlights
    document.querySelectorAll('.rating-item.rating-missing-error').forEach(el => {
        el.classList.remove('rating-missing-error');
    });

    if (missing.length === 0) return true;   // all good

    // Highlight every unanswered item
    let firstMissingEl = null;
    missing.forEach(name => {
        const anyInput = document.querySelector(`input[name="${CSS.escape(name)}"]`);
        if (anyInput) {
            const item = anyInput.closest('.rating-item');
            if (item) {
                item.classList.add('rating-missing-error');
                if (!firstMissingEl) firstMissingEl = item;
            }
        }
    });

    // Scroll to the first unanswered item
    if (firstMissingEl) {
        firstMissingEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // Show toast
    const msg = `Please rate all criteria before submitting. ${missing.length} item${missing.length > 1 ? 's' : ''} still need${missing.length === 1 ? 's' : ''} a rating.`;
    if (typeof showToast === 'function') {
        showToast(msg, 'error');
    } else {
        alert(msg);
    }

    return false;
}

// Auto-clear error highlight when user picks a rating on a previously missing item
document.addEventListener('change', function (e) {
    if (e.target && (e.target.name?.startsWith('kra_scores[') || e.target.name?.startsWith('beh_scores['))) {
        const item = e.target.closest('.rating-item');
        if (item) item.classList.remove('rating-missing-error');
    }
});

function validateConsentAndSignature() {
    const consent = document.getElementById('employee_consent_agreed');
    const signature = document.getElementById('employee_signature_data');
    if (consent && !consent.checked) {
        consent.scrollIntoView({ behavior: 'smooth', block: 'center' });
        consent.classList.add('is-invalid');
        const msg = 'Please read and check the declaration consent before submitting.';
        if (typeof showToast === 'function') showToast(msg, 'error'); else alert(msg);
        return false;
    }
    if (signature && (!signature.value || signature.value.trim() === '')) {
        const canvas = document.getElementById('signatureCanvas');
        if (canvas) canvas.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const msg = 'Please draw your digital signature before submitting.';
        if (typeof showToast === 'function') showToast(msg, 'error'); else alert(msg);
        return false;
    }
    return true;
}

function showReviewModal() {
    // Guard: do not open if any rating is missing or consent/signature is invalid
    if (!validateAllRatings() || !validateConsentAndSignature()) return;

    const modal = new bootstrap.Modal(document.getElementById('reviewModal'));
    const reviewContent = document.getElementById('reviewContent');
    let html = '';

    // Summary Info
    html += `
        <section class="review-summary">
            <div class="review-summary-item">
                <span>Position</span>
                <strong>${escapeReviewHtml(reviewPositionText)}</strong>
            </div>
            <div class="review-summary-item">
                <span>Template</span>
                <strong>${escapeReviewHtml(reviewTemplateText)}</strong>
            </div>
        </section>
    `;

    html += buildReviewSection('KRA Ratings', 'kra_scores', 'review-rating-kra');
    html += buildReviewSection('Behavior Ratings', 'beh_scores', 'review-rating-behavior');

    // Comments
    const comments = document.querySelector('textarea[name="self_comments"]').value;
    if (comments) {
        html += `
            <section class="review-comments">
                <div class="review-section-heading">
                    <span>Self Comments</span>
                </div>
                <div>${escapeReviewHtml(comments).replace(/\n/g, '<br>')}</div>
            </section>
        `;
    }

    reviewContent.innerHTML = html;
    modal.show();
}

function confirmFinalSubmit() {
    // Find the form and set submit_action to 'submit' via a hidden input,
    // then submit — this avoids browser quirks with clicking hidden submit buttons.
    const form = document.querySelector('form[data-autosave]');
    if (!form) return;

    // ── Cancel any pending autosave timers FIRST ──────────────────────────────
    // Without this, a 2-second debounce timer started by the user's last rating
    // click could fire during the 600 ms spinner delay, hit the server with an
    // AJAX draft-save request, get back success:false (evaluation already gone),
    // and show a spurious "Failed to save changes" toast even though the final
    // submission succeeded.
    if (typeof window.cancelAutoSave === 'function') {
        window.cancelAutoSave();
    }

    // Show loading state on the submit button
    const submitBtn = document.getElementById('btnConfirmSubmit');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Submitting...';
    }

    // Disable "Go Back & Edit" button
    const backBtn = document.querySelector('.review-modal-footer button[data-bs-dismiss="modal"]');
    if (backBtn) {
        backBtn.disabled = true;
    }

    // Remove any stale override input from a previous attempt
    const existing = form.querySelector('input[name="submit_action"][data-final]');
    if (existing) existing.remove();

    // Add a hidden input that guarantees submit_action = 'submit'
    const input = document.createElement('input');
    input.type  = 'hidden';
    input.name  = 'submit_action';
    input.value = 'submit';
    input.setAttribute('data-final', '1');
    form.appendChild(input);

    // Disable the draft submit button so it is not included in the POST
    const draftBtn = form.querySelector('button[name="submit_action"][value="draft"]');
    if (draftBtn) draftBtn.disabled = true;

    // Brief delay to allow the user to see the loading state/spinner
    setTimeout(() => {
        form.submit();
    }, 600);
}

// ── Auto-Scroll on Self-Rating Choice Selection ──────────────────────────────
document.addEventListener('change', function (e) {
    if (!e.target || !e.target.classList.contains('rating-input')) return;

    const currentItem = e.target.closest('.rating-item');
    if (!currentItem) return;

    // Find all rating items in order
    const allItems = Array.from(document.querySelectorAll('.rating-item'));
    const currentIndex = allItems.indexOf(currentItem);

    if (currentIndex !== -1 && currentIndex < allItems.length - 1) {
        const nextItem = allItems[currentIndex + 1];
        setTimeout(() => {
            nextItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
            // Add a brief subtle pulse outline highlight to signal active focus
            nextItem.style.transition = 'box-shadow 0.3s ease';
            nextItem.style.boxShadow = '0 0 0 3px rgba(4, 61, 7, 0.2)';
            setTimeout(() => { nextItem.style.boxShadow = ''; }, 1200);
        }, 150);
    } else {
        // Last question reached — scroll smoothly to the comments / submit section
        const actionsDiv = document.querySelector('.self-rating-actions') || document.querySelector('textarea[name="self_comments"]');
        if (actionsDiv) {
            setTimeout(() => {
                actionsDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 150);
        }
    }
});
</script>


<script>
(function() {
    const shouldClearTemplateUrl = <?php echo (!$edit_eval && !$view_mode && isset($_GET['template']) && $selected_template_id > 0) ? 'true' : 'false'; ?>;
    const shouldResetTemplateSelect = <?php echo (!$edit_eval && !$view_mode && !isset($_GET['template']) && $selected_template_id <= 0) ? 'true' : 'false'; ?>;

    if (shouldClearTemplateUrl && window.history && window.history.replaceState) {
        window.history.replaceState(null, document.title, 'self-rating.php');
    }

    if (shouldResetTemplateSelect) {
        const resetTemplateSelect = () => {
            const templateSelect = document.getElementById('templateSelect');
            if (templateSelect) {
                templateSelect.value = '';
                templateSelect.selectedIndex = 0;
            }
        };

        resetTemplateSelect();
        window.addEventListener('pageshow', resetTemplateSelect);
    }
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('signatureCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const hiddenInput = document.getElementById('employee_signature_data');
    let isDrawing = false;
    let hasSignature = false;

    // Load existing signature if present
    if (hiddenInput && hiddenInput.value) {
        const img = new Image();
        img.onload = function() {
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            hasSignature = true;
        };
        img.src = hiddenInput.value;
    }

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        return {
            x: (clientX - rect.left) * scaleX,
            y: (clientY - rect.top) * scaleY
        };
    }

    function startDrawing(e) {
        isDrawing = true;
        const pos = getPos(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
        ctx.strokeStyle = '#1C271B';
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        if (e.type === 'touchstart') e.preventDefault();
    }

    function draw(e) {
        if (!isDrawing) return;
        const pos = getPos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
        hasSignature = true;
        if (e.type === 'touchmove') e.preventDefault();
    }

    function stopDrawing() {
        if (isDrawing) {
            isDrawing = false;
            if (hasSignature && hiddenInput) {
                hiddenInput.value = canvas.toDataURL('image/png');
            }
        }
    }

    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseleave', stopDrawing);

    canvas.addEventListener('touchstart', startDrawing, {passive: false});
    canvas.addEventListener('touchmove', draw, {passive: false});
    canvas.addEventListener('touchend', stopDrawing);

    window.clearSignatureCanvas = function() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hasSignature = false;
        if (hiddenInput) hiddenInput.value = '';
    };
});
</script>
