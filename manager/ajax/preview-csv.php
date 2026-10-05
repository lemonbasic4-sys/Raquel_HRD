<?php
/**
 * AJAX Endpoint: Preview CSV Employee Import
 * Parses the uploaded CSV and returns a JSON summary + preview rows.
 * Does NOT write anything to the database.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/session-check.php';
checkRole(['HR Manager', 'HR Supervisor', 'HR Staff']);
require_once __DIR__ . '/../../includes/functions.php';

verifyCsrfToken();

if (!isset($_FILES['employee_csv']) || $_FILES['employee_csv']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Please upload a valid CSV file.']);
    exit;
}

$uploadedName      = $_FILES['employee_csv']['name'] ?? '';
$uploadedExtension = strtolower(pathinfo($uploadedName, PATHINFO_EXTENSION));
$uploadedHandle    = fopen($_FILES['employee_csv']['tmp_name'], 'rb');
$uploadedSignature = $uploadedHandle ? fread($uploadedHandle, 2) : false;
if ($uploadedHandle) fclose($uploadedHandle);

if ($uploadedExtension !== 'csv' || $uploadedSignature === "PK") {
    echo json_encode(['success' => false, 'message' => 'Excel files (.xlsx) are not supported. Please save as CSV UTF-8 first.']);
    exit;
}

$file = fopen($_FILES['employee_csv']['tmp_name'], 'r');
if (!$file) {
    echo json_encode(['success' => false, 'message' => 'Could not read the uploaded file.']);
    exit;
}

// Read headers
$headers = fgetcsv($file);
if (!$headers) {
    fclose($file);
    echo json_encode(['success' => false, 'message' => 'CSV file is empty or has no headers.']);
    exit;
}

$headerMap = array_flip(array_map('trim', $headers));

// ── Date parser ───────────────────────────────────────────────────────────────
$parseCsvDate = static function ($value) {
    $value = trim((string) $value);
    if ($value === '' || strpos($value, "\0") !== false) return null;
    foreach (['m/d/Y', 'n/j/Y', 'Y-m-d'] as $format) {
        $date   = DateTime::createFromFormat($format, $value);
        $errors = DateTime::getLastErrors();
        if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            return $date->format('Y-m-d');
        }
    }
    return null;
};

// ── Pre-load lookup data from DB ──────────────────────────────────────────────
$all_depts   = [];
$depts_res   = $conn->query("SELECT department_id, department_name FROM departments");
if ($depts_res) {
    while ($drow = $depts_res->fetch_assoc()) {
        $all_depts[(int)$drow['department_id']] = $drow['department_name'];
    }
}

$all_branches = [];
$branches_res = $conn->query("SELECT branch_id, branch_name FROM branches WHERE is_active = 1");
if ($branches_res) {
    while ($brow = $branches_res->fetch_assoc()) {
        $all_branches[(int)$brow['branch_id']] = $brow['branch_name'];
    }
}

$dept_aliases = [
    'it' => 'Information Technology', 'i.t.' => 'Information Technology',
    'info tech' => 'Information Technology', 'information tech' => 'Information Technology',
    'it department' => 'Information Technology', 'it dept' => 'Information Technology',
    'hr' => 'Human Resources', 'h.r.' => 'Human Resources', 'hrd' => 'Human Resources',
    'human resource' => 'Human Resources', 'hr department' => 'Human Resources',
    'hr dept' => 'Human Resources',
    'acct' => 'Finance', 'accounting' => 'Finance', 'fin' => 'Finance',
    'finance & accounting' => 'Finance',
    'audit' => 'Audit', 'internal audit' => 'Audit',
    'ops' => 'Operations', 'operation' => 'Operations', 'operations dept' => 'Operations',
    'procurement' => 'Purchasing', 'purchasing dept' => 'Purchasing',
    'mktg' => 'Marketing', 'marketing & sales' => 'Marketing',
    'gsd' => 'General Services', 'general service' => 'General Services',
    'general services dept' => 'General Services',
    'bizdev' => 'Business Development', 'business dev' => 'Business Development',
    'legal & compliance' => 'Compliance', 'compliance dept' => 'Compliance',
    'president' => 'Office of the President', 'presidents office' => 'Office of the President',
    'op' => 'Office of the President',
];

$allowed_statuses = [
    'OJT', 'Probationary', 'Project Based', 'Project-Based', 'Regular', 'Separated',
    'Trainee', 'AWOL', 'Retirement', 'Death', 'Permanent or Total Disability',
    'Resignation', 'Failed in Training', 'Termination for Cause',
];

// ── Parse rows ────────────────────────────────────────────────────────────────
$preview_rows = [];
$summary = [
    'total'    => 0,
    'new'      => 0,
    'update'   => 0,
    'skipped'  => 0,
    'warnings' => [],
];

$MAX_PREVIEW = 200; // cap for safety
$row_count   = 0;

while (($row = fgetcsv($file)) !== false) {
    if (empty(array_filter($row))) continue;

    // Helper closure (needs to see current $row and $headerMap)
    $getV = function ($key, $idx = null) use ($row, $headerMap) {
        $val = '';
        if (isset($headerMap[$key])) {
            $val = trim($row[$headerMap[$key]] ?? '');
        } elseif ($idx !== null) {
            $val = trim($row[$idx] ?? '');
        }
        if ($val !== '') {
            $val = mb_convert_encoding($val, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }
        return $val;
    };

    $row_count++;
    if ($row_count > $MAX_PREVIEW) {
        $summary['warnings'][] = "Only the first {$MAX_PREVIEW} rows are shown in the preview. All rows will be processed on confirm.";
        // Still count skipped/new/update for rows beyond limit via a quick check
        break;
    }

    $first_name    = $getV('First Name', 0);
    $last_name     = $getV('Last Name', 1);
    $middle_name   = $getV('Middle Name', 2);
    $employee_code = $getV('Company ID', 39);

    // Skip instruction / legend row if present in uploaded CSV
    if (str_starts_with($first_name, '[') || str_starts_with($last_name, '[') || stripos($first_name, '[Required]') !== false) {
        continue;
    }

    $hireDateRaw   = $getV('Hire Date', 33);
    $hd            = $parseCsvDate($hireDateRaw);
    $dob_raw       = $getV('Birthday', 4);
    $dob           = $parseCsvDate($dob_raw);

    $job_title_name = $getV('Job Title', 34);
    $dept_name      = $getV('Department', 35);
    $branch_name    = $getV('Branch', 36);
    $emp_status     = $getV('Employment Status', 37) ?: 'Regular';
    $emp_type       = $getV('Employment Type', 38) ?: 'Full-time';
    $gender         = $getV('Gender', 6);
    $mobile         = $getV('Mobile No', 31);
    $email          = $getV('Email', 32);

    // Validate required fields
    $row_issues = [];
    if (empty($first_name) || empty($last_name)) {
        $row_issues[] = 'Missing name';
    }
    if (empty($hd)) {
        $row_issues[] = 'Invalid/missing hire date' . ($hireDateRaw ? " ($hireDateRaw)" : '');
    }
    if (empty($job_title_name)) {
        $row_issues[] = 'Missing job title';
    }

    $is_fatal = !empty($row_issues);

    // ── Department resolution (preview only) ─────────────────────────────────
    $resolved_dept = '';
    $dept_warning  = false;
    if (!empty($dept_name)) {
        $norm = strtolower(trim($dept_name));
        foreach ($all_depts as $dname) {
            if (strtolower(trim($dname)) === $norm) { $resolved_dept = $dname; break; }
        }
        if (!$resolved_dept && isset($dept_aliases[$norm])) {
            $target = strtolower($dept_aliases[$norm]);
            foreach ($all_depts as $dname) {
                if (strtolower(trim($dname)) === $target) { $resolved_dept = $dname; break; }
            }
        }
        if (!$resolved_dept) {
            foreach ($all_depts as $dname) {
                $dlower = strtolower(trim($dname));
                $dist = levenshtein($norm, $dlower);
                similar_text($norm, $dlower, $sim);
                if ($dist <= 2 || $sim >= 82) { $resolved_dept = $dname . ' ⚠'; $dept_warning = true; break; }
            }
        }
        if (!$resolved_dept) {
            $resolved_dept = $dept_name . ' (not found)';
            $dept_warning  = true;
            if (!$is_fatal) $row_issues[] = 'Department not recognized';
        }
    } else {
        $resolved_dept = '—';
    }

    // ── Branch resolution (preview only) ─────────────────────────────────────
    $resolved_branch = '';
    $branch_warning  = false;
    if (!empty($branch_name)) {
        foreach ($all_branches as $bname) {
            if (strtolower(trim($bname)) === strtolower(trim($branch_name))) {
                $resolved_branch = $bname; break;
            }
        }
        if (!$resolved_branch) {
            $resolved_branch = $branch_name . ' → fallback';
            $branch_warning  = true;
        }
    } else {
        $resolved_branch = '(your branch)';
        $branch_warning  = true;
    }

    // ── Existing employee check ───────────────────────────────────────────────
    $is_update = false;
    if (!empty($employee_code)) {
        $ec = $conn->prepare("SELECT employee_id FROM employees WHERE employee_code = ? LIMIT 1");
        $ec->bind_param("s", $employee_code);
        $ec->execute();
        if ($ec->get_result()->num_rows > 0) $is_update = true;
        $ec->close();
    }
    if (!$is_update && !empty($first_name) && !empty($last_name)) {
        $nc = $conn->prepare("SELECT employee_id FROM employees WHERE first_name = ? AND last_name = ? LIMIT 1");
        $nc->bind_param("ss", $first_name, $last_name);
        $nc->execute();
        if ($nc->get_result()->num_rows > 0) $is_update = true;
        $nc->close();
    }

    // Normalize employment status
    foreach ($allowed_statuses as $as) {
        if (strcasecmp($as, $emp_status) === 0) { $emp_status = $as; break; }
    }

    // ── Tally ─────────────────────────────────────────────────────────────────
    $summary['total']++;
    if ($is_fatal)      $summary['skipped']++;
    elseif ($is_update) $summary['update']++;
    else                $summary['new']++;

    $preview_rows[] = [
        'row_num'        => $row_count,
        'employee_code'  => $employee_code ?: '—',
        'name'           => trim($last_name . ', ' . $first_name . ($middle_name ? ' ' . $middle_name : '')),
        'job_title'      => $job_title_name ?: '—',
        'department'     => $resolved_dept ?: '—',
        'branch'         => $resolved_branch ?: '—',
        'hire_date'      => $hd ?: ($hireDateRaw ? "⚠ $hireDateRaw" : '—'),
        'dob'            => $dob ?: ($dob_raw ? "⚠ $dob_raw" : '—'),
        'gender'         => $gender ?: '—',
        'emp_status'     => $emp_status,
        'emp_type'       => $emp_type,
        'mobile'         => $mobile ?: '—',
        'email'          => $email ?: '—',
        'action'         => $is_fatal ? 'skip' : ($is_update ? 'update' : 'new'),
        'issues'         => $row_issues,
        'dept_warning'   => $dept_warning,
        'branch_warning' => $branch_warning,
    ];
}
fclose($file);

echo json_encode([
    'success'  => true,
    'summary'  => $summary,
    'rows'     => $preview_rows,
    'filename' => htmlspecialchars($uploadedName),
]);
