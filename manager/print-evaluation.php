<?php
require_once '../includes/session-check.php';
// Allow Manager, Supervisor, and Staff to print their own/relevant evaluations
checkRole(['HR Manager', 'HR Supervisor', 'HR Staff']);
require_once '../includes/functions.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id)
  die("Invalid Evaluation ID.");

// Fetch evaluation details with all joins
$query = "SELECT ev.*, 
    CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.job_title, e.department_id, d.department_name,
    u.full_name as submitted_by_name, u2.full_name as endorsed_by_name, u3.full_name as approved_by_name,
    et.template_name, et.form_code, et.revision_date, et.effective_date_form,
    et.kra_weight AS tpl_kra_weight, et.behavior_weight AS tpl_behavior_weight,
    ds.full_name AS dept_supervisor_confirmed_by_name,
    dm.full_name AS dept_manager_endorsed_by_name,
    CONCAT(ce.first_name, ' ', ce.last_name) AS consolidator_name,
    ce.job_title AS consolidator_job_title,
    ep.package_id,
    ep.updated_at AS consolidated_at
    FROM evaluations ev
    LEFT JOIN employees e ON ev.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN users u ON ev.submitted_by = u.user_id
    LEFT JOIN users u2 ON ev.endorsed_by = u2.user_id
    LEFT JOIN users u3 ON ev.approved_by = u3.user_id
    LEFT JOIN users ds ON ev.dept_supervisor_confirmed_by = ds.user_id
    LEFT JOIN users dm ON ev.dept_manager_endorsed_by = dm.user_id
    LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
    LEFT JOIN evaluation_package_members epm ON epm.evaluation_id = ev.evaluation_id
    LEFT JOIN evaluation_packages ep ON ep.package_id = epm.package_id
    LEFT JOIN employees ce ON ep.consolidator_employee_id = ce.employee_id
    WHERE ev.evaluation_id = $id";

$result = $conn->query($query);
if (!$result || $result->num_rows === 0)
  die("Evaluation not found.");
$row = $result->fetch_assoc();

// Staff check: Allowed as they have access to evaluation history in the staff portal
if (!in_array($_SESSION['role'], ['HR Manager', 'HR Supervisor', 'HR Staff'])) {
  die("Access denied.");
}

// Load all higher officials & route reviewers who participated in this evaluation
$package_id = (int)($row['package_id'] ?? 0);
$higher_officials = [];

if ($package_id > 0) {
    $steps_stmt = $conn->prepare("SELECT rs.step_order, rs.step_label, rs.step_type, rs.action_status, rs.comments, rs.acted_at,
            u.full_name as user_name, u.role as user_role,
            emp.first_name, emp.last_name, emp.middle_name, emp.job_title
        FROM evaluation_package_route_steps rs
        LEFT JOIN users u ON u.user_id = rs.reviewer_user_id
        LEFT JOIN employees emp ON emp.employee_id = rs.reviewer_employee_id
        WHERE rs.package_id = ?
        ORDER BY rs.step_order ASC");
    $steps_stmt->bind_param('i', $package_id);
    $steps_stmt->execute();
    $route_steps = $steps_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $steps_stmt->close();

    foreach ($route_steps as $rs) {
        $name = !empty($rs['user_name']) ? $rs['user_name'] : trim(($rs['first_name'] ?? '') . ' ' . ($rs['last_name'] ?? ''));
        $title = !empty($rs['job_title']) ? $rs['job_title'] : ($rs['user_role'] ?? '');

        // Friendly role label for print
        $label = $rs['step_label'];
        if (stripos($label, 'consolidation') !== false || $rs['step_order'] == 1) {
            $role_label = 'Immediate Head / Consolidator';
        } elseif (stripos($label, 'Board') !== false) {
            $role_label = 'Board of Directors';
        } elseif (stripos($label, 'Audit') !== false) {
            $role_label = 'Audit Committee';
        } elseif (stripos($label, 'President') !== false) {
            $role_label = 'President & CEO';
        } elseif (stripos($label, 'VP') !== false || stripos($label, 'Vice President') !== false) {
            $role_label = 'Division Vice President';
        } elseif (stripos($label, 'Manager') !== false) {
            $role_label = 'Department Manager';
        } else {
            $role_label = $rs['step_label'];
        }

        $higher_officials[] = [
            'role_label' => $role_label,
            'name'       => $name ?: 'Authorized Official',
            'title'      => $title ?: $rs['step_label'],
            'status'     => $rs['action_status'],
            'is_signed'  => ($rs['action_status'] === 'Approved'),
            'acted_at'   => $rs['acted_at'],
            'comments'   => $rs['comments'],
            'step_order' => $rs['step_order']
        ];
    }
}

// Fallback for non-package evaluations
if (empty($higher_officials)) {
    $dept_id = (int)($row['department_id'] ?? 0);
    
    // 1. Immediate Supervisor
    $sup_name = $row['dept_supervisor_confirmed_by_name'] ?: ($row['consolidator_name'] ?: 'Immediate Supervisor');
    $sup_title = $row['consolidator_job_title'] ?: 'Department Supervisor';
    $higher_officials[] = [
        'role_label' => 'Immediate Head / Supervisor',
        'name'       => $sup_name,
        'title'      => $sup_title,
        'status'     => !empty($row['dept_supervisor_confirmed_by']) ? 'Approved' : 'Pending',
        'is_signed'  => !empty($row['dept_supervisor_confirmed_by']),
        'acted_at'   => $row['dept_supervisor_confirmed_at'] ?? null,
        'comments'   => $row['supervisor_comments'] ?? '',
        'step_order' => 1
    ];

    // 2. Department Manager
    $mgr_name = $row['dept_manager_endorsed_by_name'] ?: ($row['approved_by_name'] ?: 'Department Manager');
    $higher_officials[] = [
        'role_label' => 'Department Manager',
        'name'       => $mgr_name,
        'title'      => 'Department Manager',
        'status'     => !empty($row['dept_manager_endorsed_by']) ? 'Approved' : 'Pending',
        'is_signed'  => !empty($row['dept_manager_endorsed_by']),
        'acted_at'   => $row['dept_manager_endorsed_at'] ?? null,
        'comments'   => $row['dept_manager_comments'] ?? '',
        'step_order' => 2
    ];

    // 3. Division VP
    $vp = getDepartmentDesignatedOfficial($conn, 'Division VP', $dept_id);
    if ($vp) {
        $higher_officials[] = [
            'role_label' => 'Division Vice President',
            'name'       => $vp['full_name'],
            'title'      => $vp['job_title'] ?: 'Vice President',
            'status'     => 'Approved',
            'is_signed'  => true,
            'acted_at'   => $row['approved_date'] ?? null,
            'comments'   => '',
            'step_order' => 3
        ];
    }

    // 4. President & CEO
    $pres = getDepartmentDesignatedOfficial($conn, 'President');
    if ($pres) {
        $higher_officials[] = [
            'role_label' => 'President & CEO',
            'name'       => $pres['full_name'],
            'title'      => $pres['job_title'] ?: 'President and CEO',
            'status'     => 'Approved',
            'is_signed'  => true,
            'acted_at'   => $row['approved_date'] ?? null,
            'comments'   => '',
            'step_order' => 4
        ];
    }

    // 5. Audit Committee
    $audit_official = getDepartmentDesignatedOfficial($conn, 'Audit Committee');
    if ($audit_official) {
        $higher_officials[] = [
            'role_label' => 'Audit Committee',
            'name'       => $audit_official['full_name'],
            'title'      => $audit_official['job_title'] ?: 'Audit Committee Chair',
            'status'     => 'Approved',
            'is_signed'  => true,
            'acted_at'   => $row['approved_date'] ?? null,
            'comments'   => '',
            'step_order' => 5
        ];
    }

    // 6. Board of Directors
    $board = getDepartmentDesignatedOfficial($conn, 'Board of Directors');
    if ($board) {
        $higher_officials[] = [
            'role_label' => 'Board of Directors',
            'name'       => $board['full_name'],
            'title'      => $board['job_title'] ?: 'Board Member',
            'status'     => 'Approved',
            'is_signed'  => true,
            'acted_at'   => $row['approved_date'] ?? null,
            'comments'   => '',
            'step_order' => 6
        ];
    }
}

// Extract specific official roles for the classic form layout:
$supervisor_name = ''; $supervisor_title = 'Immediate Head / Consolidator'; $supervisor_comments = $row['supervisor_comments'] ?? '';
$dept_manager_name = ''; $dept_manager_title = 'Department Manager'; $dept_manager_comments = $row['dept_manager_comments'] ?? '';
$hr_manager_name = ''; $hr_manager_title = 'HR Manager'; $hr_manager_comments = $row['manager_comments'] ?? '';
$vp_name = ''; $vp_title = 'Vice President';
$pres_name = ''; $pres_title = 'President and CEO';
$board_name = ''; $board_title = 'Board of Directors';

foreach ($higher_officials as $ho) {
    $lbl = strtolower($ho['role_label'] . ' ' . $ho['title']);
    if (stripos($lbl, 'board') !== false) {
        $board_name = $ho['name'];
        $board_title = $ho['title'];
    } elseif (stripos($lbl, 'president') !== false) {
        $pres_name = $ho['name'];
        $pres_title = $ho['title'];
    } elseif (stripos($lbl, 'vp') !== false || stripos($lbl, 'vice president') !== false) {
        $vp_name = $ho['name'];
        $vp_title = $ho['title'];
    } elseif (stripos($lbl, 'hr manager') !== false) {
        $hr_manager_name = $ho['name'];
        $hr_manager_title = $ho['title'];
        if (!empty($ho['comments']) && empty($hr_manager_comments)) $hr_manager_comments = $ho['comments'];
    } elseif (stripos($lbl, 'manager') !== false && empty($dept_manager_name)) {
        $dept_manager_name = $ho['name'];
        $dept_manager_title = $ho['title'];
        if (!empty($ho['comments']) && empty($dept_manager_comments)) $dept_manager_comments = $ho['comments'];
    } elseif ($ho['step_order'] == 1 || stripos($lbl, 'consolidat') !== false || stripos($lbl, 'supervisor') !== false) {
        if (empty($supervisor_name)) {
            $supervisor_name = $ho['name'];
            $supervisor_title = $ho['title'];
            if (!empty($ho['comments']) && empty($supervisor_comments)) $supervisor_comments = $ho['comments'];
        }
    }
}

// Ensure defaults from DB row & designated officials if not matched in route
if (empty($supervisor_name)) {
    $supervisor_name = $row['dept_supervisor_confirmed_by_name'] ?: ($row['consolidator_name'] ?: 'IMMEDIATE HEAD');
    $supervisor_title = $row['consolidator_job_title'] ?: 'Department Supervisor';
}
if (empty($dept_manager_name)) {
    $dept_manager_name = $row['dept_manager_endorsed_by_name'] ?: '';
    $dept_manager_title = 'Department Manager';
}
if (empty($hr_manager_name)) {
    $hr_manager_name = $row['approved_by_name'] ?: 'ELENA DELGADO';
    $hr_manager_title = 'HR Manager I';
}
if (empty($vp_name)) {
    $vp_obj = getDepartmentDesignatedOfficial($conn, 'Division VP', (int)($row['department_id'] ?? 0));
    $vp_name = $vp_obj ? $vp_obj['full_name'] : 'EDUARDO VILLANUEVA AQUINO';
    $vp_title = $vp_obj ? $vp_obj['job_title'] : 'Vice President';
}
if (empty($pres_name)) {
    $pres_obj = getDepartmentDesignatedOfficial($conn, 'President');
    $pres_name = $pres_obj ? $pres_obj['full_name'] : 'GABRIEL SANTOS MENDOZA';
    $pres_title = $pres_obj ? $pres_obj['job_title'] : 'President and CEO';
}
if (empty($board_name)) {
    $board_obj = getDepartmentDesignatedOfficial($conn, 'Board of Directors');
    $board_name = $board_obj ? $board_obj['full_name'] : 'MANUEL RIVERA RAMOS';
    $board_title = $board_obj ? $board_obj['job_title'] : 'Board of Directors';
}

// Resolve template metadata with fallbacks
$tpl_form_code      = !empty($row['form_code'])           ? $row['form_code']           : 'HRD Form-013.01';
$tpl_revision_date  = !empty($row['revision_date'])       ? date('j F Y', strtotime($row['revision_date'])) : '3 January 2022';
$tpl_effective_date = !empty($row['effective_date_form']) ? date('j F Y', strtotime($row['effective_date_form'])) : '14 January 2022';
$tpl_kra_weight     = !empty($row['tpl_kra_weight'])      ? (int)$row['tpl_kra_weight']      : 80;
$tpl_beh_weight     = !empty($row['tpl_behavior_weight']) ? (int)$row['tpl_behavior_weight'] : 20;
$tpl_name           = !empty($row['template_name'])       ? $row['template_name']       : 'Performance Evaluation Form';

// Resolve performance level (handle stale '0')
$pl = $row['performance_level'] ?? '';
if ((float)($row['total_score'] ?? 0) > 0 && (empty($pl) || $pl === '0')) {
    $pl = getPerformanceLevel((float)$row['total_score']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?php echo e($tpl_form_code); ?> - <?php echo e($row['employee_name']); ?></title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: Arial, sans-serif;
      font-size: 10px;
      background: #e0e0e0;
      padding: 20px;
      color: #000;
    }

    .page {
      background: #fff;
      width: 210mm;
      height: 297mm;
      margin: 0 auto 20px auto;
      padding: 8mm 12mm;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
      position: relative;
      overflow: hidden;
    }

    .no-print {
      text-align: center;
      padding-bottom: 20px;
    }

    .btn {
      padding: 8px 20px;
      cursor: pointer;
      border-radius: 4px;
      border: 1px solid #ccc;
      background: #f8f9fa;
      font-weight: bold;
      margin: 0 5px;
      text-decoration: none;
      color: #333;
      display: inline-block;
    }

    .btn-primary {
      background: #007bff;
      color: #fff;
      border-color: #007bff;
    }

    /* HEADER */
    .rating-table {
      width: 100%;
      border-collapse: collapse;
      border: 1px solid #000;
      margin-bottom: 0;
    }

    .rating-table th {
      background: #fff;
      border: 1px solid #000;
      padding: 2px 4px;
      font-size: 9px;
      font-weight: bold;
      text-align: left;
    }

    .rating-table td {
      border: 1px solid #000;
      padding: 2px 4px;
      font-size: 9px;
    }

    .kra-table {
      width: 100%;
      border-collapse: collapse;
      border: 1px solid #000;
    }

    .kra-table th {
      border: 1px solid #000;
      padding: 3px 4px;
      font-size: 9px;
      font-weight: bold;
      text-align: center;
      background: #fff;
      vertical-align: middle;
    }

    .kra-table th.left {
      text-align: left;
    }

    .kra-table td {
      border: 1px solid #000;
      padding: 2px 4px;
      font-size: 9px;
      height: auto;
    }

    .kra2-table {
      width: 100%;
      border-collapse: collapse;
      border: 1px solid #000;
      margin-bottom: 4px;
    }

    .kra2-table th {
      border: 1px solid #000;
      padding: 3px 4px;
      font-size: 9px;
      font-weight: bold;
      background: #fff;
      text-align: left;
    }

    .kra2-table td {
      border: 1px solid #000;
      padding: 2px 4px;
      font-size: 9px;
      height: auto;
    }

    .dev-table {
      width: 100%;
      border-collapse: collapse;
      border: 1px solid #000;
      margin-bottom: 4px;
    }

    .dev-table th {
      border: 1px solid #000;
      padding: 3px 4px;
      font-size: 9px;
      font-weight: bold;
      text-align: center;
      background: #fff;
    }

    .dev-table td {
      border: 1px solid #000;
      padding: 2px 4px;
      font-size: 9px;
      height: auto;
    }

    .comment-box {
      border: 1px solid #000;
      margin-bottom: 4px;
    }

    .comment-box .label {
      font-weight: bold;
      font-style: italic;
      padding: 2px 4px 1px;
      font-size: 9px;
    }

    .comment-box .content {
      min-height: 24px;
      padding: 2px 6px;
      font-size: 9px;
    }

    @media print {
      body {
        background: none;
        padding: 0;
        margin: 0;
      }

      @page {
        margin: 0;
        size: A4;
      }

      .page {
        box-shadow: none;
        margin: 0;
        height: 297mm;
        page-break-after: always;
        overflow: hidden;
      }

      /* Prevent blank trailing page after the last .page div */
      .page:last-of-type {
        page-break-after: avoid;
      }

      .no-print {
        display: none;
      }
    }
  </style>
</head>

<body>

  <div class="no-print">
    <button onclick="window.print()" class="btn btn-primary">Print Now</button>
    <button onclick="window.close()" class="btn">Close</button>
  </div>

  <!-- PAGE 1 -->
  <div class="page">

    <!-- Header -->
    <div style="border:1px solid #000; display:flex; margin-bottom:0;">
      <div
        style="width:155px; border-right:1px solid #000; display:flex; align-items:center; justify-content:center; padding:4px;">
        <img src="https://raquelpawnshop.com/wp-content/uploads/2023/05/png-logo.png"
          style="max-width:140px; max-height:55px; object-fit:contain;" alt="Logo">
      </div>
      <div style="flex:1; border-right:1px solid #000;">
        <div style="font-size:16px; font-weight:bold; text-align:center; padding:4px 0 2px;">PERFORMANCE EVALUATION FORM
        </div>
        <table style="width:100%; border-collapse:collapse; border-top:1px solid #000;">
          <tr>
            <td
              style="border-right:1px solid #000; border-bottom:1px solid #000; padding:2px 6px; font-size:10px; font-weight:bold; width:110px;">
              Revision Date</td>
            <td style="border-right:1px solid #000; border-bottom:1px solid #000; padding:2px 6px; font-size:10px;"><?php echo e($tpl_revision_date); ?></td>
            <td
              style="border-right:1px solid #000; border-bottom:1px solid #000; padding:2px 6px; font-size:10px; font-weight:bold;">
              Code</td>
            <td style="border-bottom:1px solid #000; padding:2px 6px; font-size:10px;"><?php echo e($tpl_form_code); ?></td>
          </tr>
          <tr>
            <td style="border-right:1px solid #000; padding:2px 6px; font-size:10px; font-weight:bold;">Effective Date
            </td>
            <td style="border-right:1px solid #000; padding:2px 6px; font-size:10px;"><?php echo e($tpl_effective_date); ?></td>
            <td style="border-right:1px solid #000; padding:2px 6px; font-size:10px; font-weight:bold;">Control No.</td>
            <td style="padding:2px 6px; font-size:10px;"></td>
          </tr>
        </table>
      </div>
    </div>

    <!-- Employee Info -->
    <table style="width:100%; border-collapse:collapse; border:1px solid #000; border-top:none;">
      <tr>
        <td style="border-right:1px solid #000; border-bottom:1px solid #000; padding:4px 6px; font-size:10px; width:50%;">
          Name of Employee: <span style="font-weight:bold; text-decoration:underline; color:#2d6a04;"><?php echo e($row['employee_name']); ?></span>
        </td>
        <td style="border-right:1px solid #000; border-bottom:1px solid #000; padding:4px 6px; font-size:10px; width:30%;">
          Position: <span style="font-weight:bold; color:#2d6a04;"><?php echo e($row['job_title']); ?></span>
        </td>
        <td style="border-bottom:1px solid #000; padding:4px 6px; font-size:10px;">
          Date: <span style="font-style:italic; color:#2d6a04;"><?php echo date('m/d/Y'); ?></span>
        </td>
      </tr>
      <tr>
        <td style="border-right:1px solid #000; padding:4px 6px; font-size:11px; vertical-align:top;">
          Evaluation Period:<br>
          <table style="width:100%; border-collapse:collapse; margin-top:2px;">
            <tr>
              <td style="padding:0; border:none; width:auto; font-size:11px;">From: <span style="font-weight:bold; text-decoration:underline; color:#2d6a04;"><?php echo date('m/d/Y', strtotime($row['evaluation_period_start'])); ?></span></td>
              <td style="padding:0 0 0 10px; border:none; width:auto; font-size:11px;">To: <span style="font-weight:bold; text-decoration:underline; color:#2d6a04;"><?php echo date('m/d/Y', strtotime($row['evaluation_period_end'])); ?></span></td>
            </tr>
            <tr>
              <td style="padding:0 0 0 35px; border:none; font-size:8px; color:#555;">(mm/dd/yyyy)</td>
              <td style="padding:0 0 0 30px; border:none; font-size:8px; color:#555;">(mm/dd/yyyy)</td>
            </tr>
          </table>
        </td>
        <td style="border-right:1px solid #000; padding:4px 6px; font-size:10px; vertical-align:top;">
          Please check one (1):<br>
          <div style="padding-left: 45px; margin-top: 4px; line-height: 1.4;">
            <?php $et = $row['evaluation_type'] ?? 'Annual'; ?>
            <span style="<?php echo ($et === 'Initial') ? 'color:#2d6a04;font-weight:bold;' : ''; ?>"><?php echo ($et === 'Initial') ? '&#9745;' : '&#9744;'; ?></span> Initial &nbsp;&nbsp;
            <span style="<?php echo ($et === 'Final') ? 'color:#2d6a04;font-weight:bold;' : ''; ?>"><?php echo ($et === 'Final') ? '&#9745;' : '&#9744;'; ?></span> Final<br>
            <span style="<?php echo ($et === 'Quarterly') ? 'color:#2d6a04;font-weight:bold;' : ''; ?>"><?php echo ($et === 'Quarterly') ? '&#9745;' : '&#9744;'; ?></span> Quarterly &nbsp;&nbsp;
            <span style="<?php echo ($et === 'Annual') ? 'color:#2d6a04;font-weight:bold;' : ''; ?>"><?php echo ($et === 'Annual') ? '&#9745;' : '&#9744;'; ?></span> Annual
          </div>
        </td>
        <td style="padding:4px 6px; font-size:10px; vertical-align:top;">
          Department/Branch:<br>
          <span style="font-weight:bold; color:#2d6a04;"><?php echo e($row['department_name'] ?? 'N/A'); ?></span>
        </td>
      </tr>
    </table>

    <!-- Performance Rating Scale -->
    <div
      style="font-weight:bold; font-size:11px; border:1px solid #000; border-bottom:none; padding:3px 6px; margin-top:6px;">
      Performance Rating Scale</div>
    <table class="rating-table">
      <thead>
        <tr>
          <th style="width:110px;">RATING SCALE</th>
          <th style="width:150px;">DESCRIPTION</th>
          <th>DEFINITION</th>
        </tr>
      </thead>
      <tbody>
        <?php /* $pl already resolved above with fallback */ ?>
        <tr <?php echo ($pl === 'Outstanding') ? 'style="background-color:#d4edda !important; -webkit-print-color-adjust: exact; print-color-adjust: exact;"' : ''; ?>>
          <td>3.60 – 4.00</td>
          <td>Outstanding</td>
          <td>Performance significantly exceeds standards and expectations</td>
        </tr>
        <tr <?php echo ($pl === 'Exceeds Expectations') ? 'style="background-color:#cce5ff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact;"' : ''; ?>>
          <td>2.60 – 3.59</td>
          <td>Exceeds Expectations</td>
          <td>Performance exceeds standards and expectations</td>
        </tr>
        <tr <?php echo ($pl === 'Meets Expectations') ? 'style="background-color:#fff3cd !important; -webkit-print-color-adjust: exact; print-color-adjust: exact;"' : ''; ?>>
          <td>2.00 – 2.59</td>
          <td>Meets Expectations</td>
          <td>Performance meets standards and expectations</td>
        </tr>
        <tr <?php echo ($pl === 'Needs Improvement') ? 'style="background-color:#f8d7da !important; -webkit-print-color-adjust: exact; print-color-adjust: exact;"' : ''; ?>>
          <td>1.00 – 1.99</td>
          <td>Needs Improvement</td>
          <td>Performance did not meet standards and expectations</td>
        </tr>
      </tbody>
    </table>

    <!-- Performance Evaluation Summary -->
    <div style="margin-top:6px;">
      <table style="width:100%; border-collapse:collapse; border:1px solid #000;">
        <thead>
          <tr>
            <th
              style="border:1px solid #000; padding:4px 6px; font-size:11px; font-weight:bold; text-align:left; background:#fff;">
              Performance Evaluation Summary</th>
            <th
              style="border:1px solid #000; padding:4px 12px; font-size:10px; font-weight:bold; text-align:center; background:#fff; width:70px;">
              Weight</th>
            <th
              style="border:1px solid #000; padding:4px 12px; font-size:10px; font-weight:bold; text-align:center; background:#fff; width:70px;">
              Rating</th>
            <th
              style="border:1px solid #000; padding:4px 12px; font-size:10px; font-weight:bold; text-align:center; background:#fff; width:90px;">
              Signature</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="border:1px solid #000; padding:3px 6px; font-size:10px;">I. Key Result Areas based on Strategic
              Programs and<br>&nbsp;&nbsp;&nbsp;Regular Job Requirements</td>
            <td style="border:1px solid #000; padding:3px 6px; font-size:10px; text-align:center;">
              <?php echo $tpl_kra_weight; ?>%
            </td>
            <td style="border:1px solid #000; padding:3px 6px; text-align:center; font-weight:bold;">
              <?php echo $row['kra_subtotal']; ?>
            </td>
            <td rowspan="3"
              style="border:1px solid #000; padding:4px 6px; text-align:center; font-size:9px; vertical-align:middle; width:110px;">
              <div style="margin:0 auto 2px; text-align:center; min-height:28px; display:flex; align-items:flex-end; justify-content:center;">
                <?php if (!empty($row['employee_signature_data'])): ?>
                  <img src="<?php echo e($row['employee_signature_data']); ?>" alt="Employee Signature" style="max-height:30px; max-width:95px; object-fit:contain; display:block; margin:0 auto;">
                <?php endif; ?>
              </div>
              <div style="border-bottom:1px solid #000; margin:0 4px 2px;"></div>
              <div style="font-style:italic; margin-bottom:12px;">Employee</div>
              <div style="border-bottom:1px solid #000; margin:0 4px 2px; height:12px;"></div>
              <div style="font-style:italic;">Rater</div>
            </td>
          </tr>
          <tr>
            <td style="border:1px solid #000; padding:3px 6px; font-size:10px;">II. Key Result Areas based on Behavior
              and Values</td>
            <td style="border:1px solid #000; padding:3px 6px; font-size:10px; text-align:center;">
              <?php echo $tpl_beh_weight; ?>%
            </td>
            <td style="border:1px solid #000; padding:3px 6px; text-align:center; font-weight:bold;">
              <?php echo $row['behavior_average']; ?>
            </td>
          </tr>
          <tr>
            <td style="border:1px solid #000; padding:3px 6px; font-size:10px; font-weight:bold; text-align:center;">
              TOTAL</td>
            <td style="border:1px solid #000; padding:3px 6px; font-size:10px; text-align:center; font-weight:bold;">
              100%</td>
            <td style="border:1px solid #000; padding:3px 6px; text-align:center; font-weight:bold; color:blue;">
              <?php echo $row['total_score']; ?>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Performance Result -->
    <div style="font-weight:bold; font-size:12px; text-align:center; padding:6px 0 2px;">Performance Result</div>
    <table class="kra-table">
      <thead>
        <tr>
          <th class="left" colspan="2" style="text-align:left; width:70%;">I. Key Result Areas based on Strategic Programs and Job Requirements (<?php echo $tpl_kra_weight; ?>%)</th>
          <th style="width:10%;">Weight</th>
          <th style="width:10%;">Rating</th>
          <th style="width:10%;">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $kra_q = $conn->query("SELECT es.*, ec.criterion_name, ec.weight FROM evaluation_scores es JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id WHERE es.evaluation_id = $id AND ec.section = 'KRA' ORDER BY ec.sort_order");
        $kra_count = 0;
        while ($k = $kra_q->fetch_assoc()):
          $kra_count++;
          $effective_score = $k['score_value'];
          if ($k['supervisor_override_score'] !== null) {
              $effective_score = $k['supervisor_override_score'];
          }
          if ($k['manager_override_score'] !== null) {
              $effective_score = $k['manager_override_score'];
          }
          $weighted_score = round(($k['weight'] / 100) * $effective_score, 2);
          ?>
          <tr>
            <td style="width:1%; white-space:nowrap; border:1px solid #000; padding:3px 6px;">KRA <?php echo $kra_count; ?></td>
            <td style="border:1px solid #000;"><?php echo e($k['criterion_name']); ?></td>
            <td style="border:1px solid #000; text-align:center;"><?php echo $k['weight']; ?>%</td>
            <td style="border:1px solid #000; text-align:center;"><?php echo number_format($effective_score, 2); ?></td>
            <td style="border:1px solid #000; text-align:center;"><?php echo number_format($weighted_score, 2); ?></td>
          </tr>
        <?php endwhile; ?>
        <tr>
          <td colspan="2"
            style="border:1px solid #000; text-align:right; font-weight:bold; padding:3px 8px; font-size:10px;">SUB
            TOTAL</td>
          <td style="border:1px solid #000; text-align:center; font-weight:bold; padding:3px 6px; font-size:10px;">100%
          </td>
          <td style="border:1px solid #000;"></td>
          <td style="border:1px solid #000; text-align:center; font-weight:bold; padding:3px 6px; font-size:10px;">
            <?php echo $row['kra_subtotal']; ?>
          </td>
        </tr>
      </tbody>
    </table>

  </div>

  <!-- PAGE 2 -->
  <div class="page">

    <!-- Header (same as page 1) -->
    <div style="border:1px solid #000; display:flex; margin-bottom:6px;">
      <div
        style="width:155px; border-right:1px solid #000; display:flex; align-items:center; justify-content:center; padding:4px;">
        <img src="https://raquelpawnshop.com/wp-content/uploads/2023/05/png-logo.png"
          style="max-width:140px; max-height:55px; object-fit:contain;" alt="Logo">
      </div>
      <div style="flex:1; border-right:1px solid #000;">
        <div style="font-size:16px; font-weight:bold; text-align:center; padding:4px 0 2px;">PERFORMANCE EVALUATION FORM
        </div>
        <table style="width:100%; border-collapse:collapse; border-top:1px solid #000;">
          <tr>
            <td style="border-right:1px solid #000; border-bottom:1px solid #000; padding:2px 6px; font-size:10px; font-weight:bold; width:110px;">Revision Date</td>
            <td style="border-right:1px solid #000; border-bottom:1px solid #000; padding:2px 6px; font-size:10px;"><?php echo e($tpl_revision_date); ?></td>
            <td style="border-right:1px solid #000; border-bottom:1px solid #000; padding:2px 6px; font-size:10px; font-weight:bold;">Code</td>
            <td style="border-bottom:1px solid #000; padding:2px 6px; font-size:10px;"><?php echo e($tpl_form_code); ?></td>
          </tr>
          <tr>
            <td style="border-right:1px solid #000; padding:2px 6px; font-size:10px; font-weight:bold;">Effective Date</td>
            <td style="border-right:1px solid #000; padding:2px 6px; font-size:10px;"><?php echo e($tpl_effective_date); ?></td>
            <td style="border-right:1px solid #000; padding:2px 6px; font-size:10px; font-weight:bold;">Control No.</td>
            <td style="padding:2px 6px; font-size:10px;"></td>
          </tr>
        </table>
      </div>
    </div>

    <!-- KRA II -->
    <div style="font-weight:bold; font-size:11px; border:1px solid #000; border-bottom:none; padding:3px 6px;">II. Key Result Areas based on Behavior and Values (<?php echo $tpl_beh_weight; ?>%)</div>
    <table class="kra2-table">
      <thead>
        <tr>
          <th style="width:30%;">Key Result Area</th>
          <th>Key Performance Indicator</th>
          <th style="width:80px; text-align:center;">Rating</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $beh_q = $conn->query("SELECT es.*, ec.criterion_name, ec.kpi_description FROM evaluation_scores es JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id WHERE es.evaluation_id = $id AND ec.section = 'Behavior' ORDER BY ec.sort_order");
        $idx = 1;
        while ($b = $beh_q->fetch_assoc()):
          $effective_score = $b['score_value'];
          if ($b['supervisor_override_score'] !== null) {
              $effective_score = $b['supervisor_override_score'];
          }
          if ($b['manager_override_score'] !== null) {
              $effective_score = $b['manager_override_score'];
          }
          ?>
          <tr>
            <td><?php echo $idx++; ?>. <?php echo e($b['criterion_name']); ?></td>
            <td><?php echo e($b['kpi_description']); ?></td>
            <td style="border:1px solid #000; text-align:center; font-weight:bold;"><?php echo number_format($effective_score, 2); ?></td>
          </tr>
        <?php endwhile; ?>
        <tr style="font-weight:bold;">
          <td colspan="2" style="text-align:right; padding:3px 8px; border:1px solid #000;">Average</td>
          <td style="border:1px solid #000; text-align:center; padding:3px 6px;"><?php echo $row['behavior_average']; ?>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Developmental Plan -->
    <div style="font-weight:bold; font-size:12px; text-align:center; padding:6px 0 2px;">DEVELOPMENTAL PLAN</div>
    <table class="dev-table">
      <thead>
        <tr>
          <th style="width:45%;">Areas of improvement that the employee should<br>concentrate on (if any)</th>
          <th style="width:30%;">Support Needed</th>
          <th style="width:25%;">Time Frame</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $dev_q = $conn->query("SELECT * FROM evaluation_dev_plans WHERE evaluation_id = $id ORDER BY sort_order");
        $dev_count = 0;
        while ($dp = $dev_q->fetch_assoc()):
          $dev_count++;
          ?>
          <tr>
            <td><?php echo e($dp['improvement_area']); ?></td>
            <td><?php echo e($dp['support_needed']); ?></td>
            <td style="text-align:center;"><?php echo e($dp['time_frame']); ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

    <!-- Career Growth -->
    <div style="font-weight:bold; font-size:11px; text-align:center; padding:3px 0 2px;">CAREER GROWTH</div>
    <div style="border:1px solid #000; padding:4px 6px; font-size:9px; margin-bottom:5px;">
      Is the employee better suited for another job within the company? &nbsp;
      <?php $suited = !empty($row['career_growth_suited']) ? 1 : (!empty($row['desired_position']) ? 1 : 0); ?>
      <?php echo ($suited == 1) ? '&#9745;' : '&#9744;'; ?> Yes &nbsp;&nbsp;
      <?php echo ($suited == 0) ? '&#9745;' : '&#9744;'; ?> No<br>
      If yes, specify the job function / department: <span
        style="display:inline-block; border-bottom:1px solid #000; min-width:300px; margin-left:4px;"><?php echo ($suited == 1) ? e($row['desired_position'] ?? '') : ''; ?></span>
    </div>

    <!-- Employee's Comments -->
    <div class="comment-box">
      <div class="label">Employee's Comments:</div>
      <div class="content"><?php echo !empty($row['staff_comments']) ? nl2br(e($row['staff_comments'])) : '&nbsp;'; ?></div>
      <div style="text-align:center; padding-bottom:3px; margin-top:8px;">
        <div style="font-weight:bold; font-size:11px;"><?php echo strtoupper(e($row['employee_name'] ?? '')); ?></div>
      </div>
    </div>

    <!-- Immediate Supervisor / Package Consolidator Comments -->
    <div class="comment-box">
      <div class="label">Immediate Supervisor / Package Consolidator Comments:</div>
      <div class="content"><?php echo !empty($supervisor_comments) ? nl2br(e($supervisor_comments)) : '&nbsp;'; ?></div>
      <div style="text-align:center; padding-bottom:3px; margin-top:8px;">
        <div style="font-weight:bold; font-size:11px;"><?php echo strtoupper(e($supervisor_name)); ?></div>
        <div style="font-size:9px; color:#555;"><?php echo e($supervisor_title); ?></div>
      </div>
    </div>

    <!-- Department Manager's Comments -->
    <?php if (!empty($dept_manager_name) && strcasecmp(trim($dept_manager_name), trim($supervisor_name)) !== 0): ?>
    <div class="comment-box">
      <div class="label">Department Manager's Comments:</div>
      <div class="content"><?php echo !empty($dept_manager_comments) ? nl2br(e($dept_manager_comments)) : '&nbsp;'; ?></div>
      <div style="text-align:center; padding-bottom:3px; margin-top:8px;">
        <div style="font-weight:bold; font-size:11px;"><?php echo strtoupper(e($dept_manager_name)); ?></div>
        <div style="font-size:9px; color:#555;"><?php echo e($dept_manager_title); ?></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- HR Manager's Comments -->
    <div class="comment-box">
      <div class="label">HR Manager's Comments:</div>
      <div class="content"><?php echo !empty($hr_manager_comments) ? nl2br(e($hr_manager_comments)) : '&nbsp;'; ?></div>
      <div style="text-align:center; padding-bottom:3px; margin-top:8px;">
        <div style="font-weight:bold; font-size:11px;"><?php echo strtoupper(e($hr_manager_name)); ?></div>
        <div style="font-size:9px; color:#555;"><?php echo e($hr_manager_title); ?></div>
      </div>
    </div>

    <!-- Executives' Signature -->
    <div style="border:1px solid #000; padding:6px 12px 8px; margin-bottom:5px;">
      <div style="font-weight:bold; font-style:italic; font-size:9px; margin-bottom:14px;">Executives' Signature</div>
      <div style="display:flex; justify-content:space-around; align-items:flex-end; gap:16px; padding:0 10px;">
        <div style="text-align:center; min-width:130px; flex:1;">
          <div style="border-top:1px solid #000; width:85%; max-width:160px; margin:0 auto 2px;"></div>
          <div style="font-weight:bold; font-size:10px;"><?php echo strtoupper(e($vp_name)); ?></div>
          <div style="font-size:8.5px; color:#333;"><?php echo e($vp_title); ?></div>
        </div>
        <div style="text-align:center; min-width:130px; flex:1;">
          <div style="border-top:1px solid #000; width:85%; max-width:160px; margin:0 auto 2px;"></div>
          <div style="font-weight:bold; font-size:10px;"><?php echo strtoupper(e($pres_name)); ?></div>
          <div style="font-size:8.5px; color:#333;"><?php echo e($pres_title); ?></div>
        </div>
        <?php if (!empty($board_name)): ?>
        <div style="text-align:center; min-width:130px; flex:1;">
          <div style="border-top:1px solid #000; width:85%; max-width:160px; margin:0 auto 2px;"></div>
          <div style="font-weight:bold; font-size:10px;"><?php echo strtoupper(e($board_name)); ?></div>
          <div style="font-size:8.5px; color:#333;"><?php echo e($board_title); ?></div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- HR Use Only -->
    <div style="border:1px solid #000; padding:4px 6px;">
      <div style="font-style:italic; font-weight:bold; text-align:center; font-size:9px; margin-bottom:4px;">For Human Resources Use Only</div>
      <div style="font-size:9px; display:flex; justify-content:space-between; align-items:center;">
        <div>PMS Form received on: <span style="display:inline-block; border-bottom:1px solid #000; min-width:120px; margin:0 4px; text-align:center;"><?php echo !empty($row['consolidated_at']) ? date('M d, Y', strtotime($row['consolidated_at'])) : date('M d, Y'); ?></span></div>
        <div>Received by: <span style="display:inline-block; border-bottom:1px solid #000; min-width:160px; margin:0 4px; text-align:center;">Human Resources Department</span></div>
      </div>
    </div>

  </div>

</body>

</html>