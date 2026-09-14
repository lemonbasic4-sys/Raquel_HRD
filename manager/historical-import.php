<?php
$page_title = 'Historical Evaluation Import';
require_once '../includes/session-check.php';
checkRole(['HR Manager', 'HR Supervisor']);
require_once '../includes/functions.php';
ensureHistoricalImportSchema($conn);

$allowed_eval_types = ['Initial', 'Final', 'Quarterly', 'Annual'];
$required_headers = ['employee_code', 'evaluation_type', 'evaluation_period_start', 'evaluation_period_end', 'total_score'];
$message_type = null;
$message = null;
$import_summary = null;
$preview_rows = [];
$preview_errors = [];

function normalizeEvaluationType($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $normalized = strtolower($value);
    $map = [
        'initial' => 'Initial',
        'final' => 'Final',
        'quarterly' => 'Quarterly',
        'annual' => 'Annual',
    ];

    return $map[$normalized] ?? ucfirst(strtolower($value));
}

function parseImportedDate($value)
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }

    $candidates = [
        'Y-m-d',
        'm/d/Y',
        'n/j/Y',
        'm-d-Y',
        'n-j-Y',
        'd/m/Y',
        'j/n/Y',
        'd-m-Y',
        'j-n-Y',
        'Y/m/d',
    ];

    foreach ($candidates as $format) {
        $date = DateTime::createFromFormat($format, $raw);
        $errors = DateTime::getLastErrors();
        if ($date && (!$errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            return $date->format('Y-m-d');
        }
    }

    return '';
}

function resolveHistoricalTemplateId($conn, $evaluation_type, $template_name = '')
{
    $evaluation_type = normalizeEvaluationType($evaluation_type);
    $template_name = trim((string) $template_name);

    if ($template_name !== '') {
        $stmt = $conn->prepare('SELECT template_id FROM evaluation_templates WHERE template_name = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->bind_param('s', $template_name);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return (int) $row['template_id'];
        }
    }

    $stmt = $conn->prepare('SELECT template_id FROM evaluation_templates WHERE evaluation_type = ? AND deleted_at IS NULL ORDER BY CASE WHEN status = "Active" THEN 0 ELSE 1 END, updated_at DESC, template_id DESC LIMIT 1');
    $stmt->bind_param('s', $evaluation_type);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int) $row['template_id'] : null;
}

if (isset($_GET['download']) && $_GET['download'] === 'template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=historical_evaluations_template.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'employee_code',
        'evaluation_type',
        'evaluation_period_start',
        'evaluation_period_end',
        'total_score',
        'kra_subtotal',
        'behavior_average',
        'performance_level',
        'template_name',
        'legacy_reference',
        'employee_comments',
        'supervisor_comments',
        'manager_comments',
    ]);
    fputcsv($output, [
        'EMP-001',
        'Annual',
        '1/1/2024',
        '12/31/2024',
        '3.60',
        '2.88',
        '3.20',
        'Meets Expectations',
        'Annual Performance Review',
        'LEG-2024-001',
        'Sample employee comments',
        '',
        '',
    ]);
    fclose($output);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    if (!isset($_FILES['historical_csv']) || $_FILES['historical_csv']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Please upload a valid CSV file.';
        $message_type = 'danger';
    } else {
        $tmp_name = $_FILES['historical_csv']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['historical_csv']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            $message = 'Only .csv uploads are allowed.';
            $message_type = 'danger';
        } else {
            $handle = fopen($tmp_name, 'rb');
            if ($handle === false) {
                $message = 'The uploaded file could not be opened.';
                $message_type = 'danger';
            } else {
                $headers = fgetcsv($handle);
                if ($headers === false || $headers === [null]) {
                    $message = 'The CSV file is empty or missing a header row.';
                    $message_type = 'danger';
                } else {
                    $normalized_headers = [];
                    foreach ($headers as $index => $header) {
                        $normalized_headers[strtolower(trim((string) $header))] = $index;
                    }

                    $missing_headers = [];
                    foreach ($required_headers as $required_header) {
                        if (!isset($normalized_headers[$required_header])) {
                            $missing_headers[] = $required_header;
                        }
                    }

                    if (!empty($missing_headers)) {
                        $message = 'Missing required CSV columns: ' . implode(', ', $missing_headers) . '.';
                        $message_type = 'danger';
                    } else {
                        $row_number = 1;
                        $valid_rows = [];
                        $invalid_rows = [];
                        $all_rows = 0;

                        while (($row = fgetcsv($handle)) !== false) {
                            $row_number++;
                            if ($row === [null] || count(array_filter($row, fn($value) => trim((string) $value) !== '')) === 0) {
                                continue;
                            }

                            $all_rows++;
                            $row_map = [];
                            foreach ($headers as $col_index => $header) {
                                $key = strtolower(trim((string) $header));
                                $row_map[$key] = $row[$col_index] ?? '';
                            }

                            $entry = [
                                'row_number' => $row_number,
                                'employee_code' => trim((string) ($row_map['employee_code'] ?? '')),
                                'evaluation_type' => normalizeEvaluationType($row_map['evaluation_type'] ?? ''),
                                'evaluation_period_start' => parseImportedDate($row_map['evaluation_period_start'] ?? ''),
                                'evaluation_period_end' => parseImportedDate($row_map['evaluation_period_end'] ?? ''),
                                'total_score' => trim((string) ($row_map['total_score'] ?? '')),
                                'kra_subtotal' => trim((string) ($row_map['kra_subtotal'] ?? '')),
                                'behavior_average' => trim((string) ($row_map['behavior_average'] ?? '')),
                                'performance_level' => trim((string) ($row_map['performance_level'] ?? '')),
                                'template_name' => trim((string) ($row_map['template_name'] ?? '')),
                                'legacy_reference' => trim((string) ($row_map['legacy_reference'] ?? '')),
                                'employee_comments' => trim((string) ($row_map['employee_comments'] ?? '')),
                                'supervisor_comments' => trim((string) ($row_map['supervisor_comments'] ?? '')),
                                'manager_comments' => trim((string) ($row_map['manager_comments'] ?? '')),
                            ];

                            $errors = [];
                            if ($entry['employee_code'] === '') {
                                $errors[] = 'Employee code is required.';
                            } else {
                                $emp_stmt = $conn->prepare('SELECT employee_id, CONCAT(first_name, " ", last_name) AS employee_name FROM employees WHERE employee_code = ? AND deleted_at IS NULL LIMIT 1');
                                $emp_stmt->bind_param('s', $entry['employee_code']);
                                $emp_stmt->execute();
                                $emp = $emp_stmt->get_result()->fetch_assoc();
                                $emp_stmt->close();
                                if (!$emp) {
                                    $errors[] = 'Employee code does not match an active employee record.';
                                } else {
                                    $entry['employee_id'] = (int) $emp['employee_id'];
                                    $entry['employee_name'] = $emp['employee_name'];
                                }
                            }

                            if ($entry['evaluation_type'] === '') {
                                $errors[] = 'Evaluation type is required.';
                            } elseif (!in_array($entry['evaluation_type'], $allowed_eval_types, true)) {
                                $errors[] = 'Evaluation type must be one of: ' . implode(', ', $allowed_eval_types) . '.';
                            }

                            if ($entry['evaluation_period_start'] === '') {
                                $errors[] = 'Evaluation period start is required.';
                            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entry['evaluation_period_start']) || !strtotime($entry['evaluation_period_start'])) {
                                $errors[] = 'Evaluation period start must use YYYY-MM-DD or a common US/European date format.';
                            }

                            if ($entry['evaluation_period_end'] === '') {
                                $errors[] = 'Evaluation period end is required.';
                            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entry['evaluation_period_end']) || !strtotime($entry['evaluation_period_end'])) {
                                $errors[] = 'Evaluation period end must use YYYY-MM-DD or a common US/European date format.';
                            }

                            if (!empty($entry['evaluation_period_start']) && !empty($entry['evaluation_period_end']) && strtotime($entry['evaluation_period_start']) > strtotime($entry['evaluation_period_end'])) {
                                $errors[] = 'Evaluation period start cannot be after end date.';
                            }

                            if ($entry['total_score'] === '') {
                                $errors[] = 'Total score is required.';
                            } elseif (!is_numeric($entry['total_score']) || (float) $entry['total_score'] < 0) {
                                $errors[] = 'Total score must be a numeric value.';
                            }

                            if ($entry['kra_subtotal'] !== '' && (!is_numeric($entry['kra_subtotal']) || (float) $entry['kra_subtotal'] < 0)) {
                                $errors[] = 'KRA subtotal must be numeric if provided.';
                            }

                            if ($entry['behavior_average'] !== '' && (!is_numeric($entry['behavior_average']) || (float) $entry['behavior_average'] < 0)) {
                                $errors[] = 'Behavior average must be numeric if provided.';
                            }

                            $template_id = resolveHistoricalTemplateId($conn, $entry['evaluation_type'], $entry['template_name']);

                            if ($template_id === null) {
                                $errors[] = 'No evaluation template was found for this evaluation type. Create or activate a template before importing historical records.';
                            } else {
                                $entry['template_id'] = $template_id;
                            }

                            if (!empty($entry['legacy_reference'])) {
                                $dup_stmt = $conn->prepare('SELECT evaluation_id FROM evaluations WHERE legacy_reference = ? AND deleted_at IS NULL LIMIT 1');
                                $dup_stmt->bind_param('s', $entry['legacy_reference']);
                                $dup_stmt->execute();
                                $dup_row = $dup_stmt->get_result()->fetch_assoc();
                                $dup_stmt->close();
                                if ($dup_row) {
                                    $errors[] = 'Duplicate legacy reference found.';
                                }
                            }

                            $entry['valid'] = empty($errors);
                            $entry['errors'] = $errors;
                            $preview_rows[] = $entry;

                            if (empty($errors)) {
                                $valid_rows[] = $entry;
                            } else {
                                $invalid_rows[] = [
                                    'row_number' => $row_number,
                                    'employee_code' => $entry['employee_code'],
                                    'message' => implode('; ', $errors),
                                    'raw_data' => json_encode($entry),
                                ];
                            }
                        }

                        fclose($handle);

                        if (!empty($valid_rows)) {
                            $imported_by = (int) ($_SESSION['user_id'] ?? 0);
                            $file_name = $_FILES['historical_csv']['name'];

                            $conn->begin_transaction();
                            try {
                                $batch_stmt = $conn->prepare('INSERT INTO evaluation_import_batches (uploaded_filename, import_mode, imported_by, total_rows, successful_rows, failed_rows, status, error_log) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                                $import_mode = 'Summary';
                                $batch_file_name = $_FILES['historical_csv']['name'];
                                $batch_status = 'Imported';
                                $batch_error_log = '';
                                $batch_total_rows = (int) $all_rows;
                                $batch_success_rows = count($valid_rows);
                                $batch_failed_rows = count($invalid_rows);
                                $batch_stmt->bind_param('ssiiisss', $batch_file_name, $import_mode, $imported_by, $batch_total_rows, $batch_success_rows, $batch_failed_rows, $batch_status, $batch_error_log);
                                $batch_stmt->execute();
                                $batch_id = $conn->insert_id;
                                $batch_stmt->close();

                                foreach ($invalid_rows as $error_row) {
                                    $err_stmt = $conn->prepare('INSERT INTO evaluation_import_errors (import_batch_id, row_number, employee_code, error_message, raw_data) VALUES (?, ?, ?, ?, ?)');
                                    $error_row_number = (int) $error_row['row_number'];
                                    $error_employee_code = (string) $error_row['employee_code'];
                                    $error_message_text = (string) $error_row['message'];
                                    $error_raw_data = (string) $error_row['raw_data'];
                                    $err_stmt->bind_param('iisss', $batch_id, $error_row_number, $error_employee_code, $error_message_text, $error_raw_data);
                                    $err_stmt->execute();
                                    $err_stmt->close();
                                }

                                foreach ($valid_rows as $item) {
                                    $status = 'Approved';
                                    $approved_date = date('Y-m-d H:i:s');
                                    $kra_value = $item['kra_subtotal'] !== '' ? (float) $item['kra_subtotal'] : null;
                                    $behavior_value = $item['behavior_average'] !== '' ? (float) $item['behavior_average'] : null;
                                    $performance_value = $item['performance_level'] !== '' ? $item['performance_level'] : null;
                                    $employee_comments_value = $item['employee_comments'] !== '' ? $item['employee_comments'] : null;
                                    $supervisor_comments_value = $item['supervisor_comments'] !== '' ? $item['supervisor_comments'] : null;
                                    $manager_comments_value = $item['manager_comments'] !== '' ? $item['manager_comments'] : null;
                                    $legacy_value = $item['legacy_reference'] !== '' ? $item['legacy_reference'] : null;

                                    $insert_stmt = $conn->prepare('INSERT INTO evaluations (
                                        employee_id, template_id, evaluation_type, evaluation_period_start, evaluation_period_end,
                                        submitted_by, approved_by, approved_date, status, total_score, kra_subtotal, behavior_average,
                                        performance_level, staff_comments, supervisor_comments, manager_comments,
                                        is_historical, record_source, import_batch_id, legacy_reference, historical_remarks,
                                        employee_consent_agreed
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

                                    $employee_id = (int) $item['employee_id'];
                                    $template_id = (int) $item['template_id'];
                                    $evaluation_type = (string) $item['evaluation_type'];
                                    $period_start = (string) $item['evaluation_period_start'];
                                    $period_end = (string) $item['evaluation_period_end'];
                                    $submitted_by = (int) $imported_by;
                                    $approved_by = (int) $imported_by;
                                    $total_score_value = (float) $item['total_score'];
                                    $is_historical_value = 1;
                                    $record_source_value = 'Historical Import';
                                    $import_batch_id_value = (int) $batch_id;
                                    $remarks_value = 'Imported historical evaluation record.';
                                    $consent_value = 1;

                                    $bind_values = [
                                        $employee_id,
                                        $template_id,
                                        $evaluation_type,
                                        $period_start,
                                        $period_end,
                                        $submitted_by,
                                        $approved_by,
                                        $approved_date,
                                        $status,
                                        $total_score_value,
                                        $kra_value,
                                        $behavior_value,
                                        $performance_value,
                                        $employee_comments_value,
                                        $supervisor_comments_value,
                                        $manager_comments_value,
                                        $is_historical_value,
                                        $record_source_value,
                                        $import_batch_id_value,
                                        $legacy_value,
                                        $remarks_value,
                                        $consent_value,
                                    ];

                                    $bind_types = '';
                                    foreach ($bind_values as $bind_value) {
                                        if (is_int($bind_value) || is_bool($bind_value)) {
                                            $bind_types .= 'i';
                                        } elseif (is_float($bind_value)) {
                                            $bind_types .= 'd';
                                        } else {
                                            $bind_types .= 's';
                                        }
                                    }

                                    $refs = [];
                                    foreach ($bind_values as $index => $value) {
                                        $refs[$index] = &$bind_values[$index];
                                    }

                                    call_user_func_array([$insert_stmt, 'bind_param'], array_merge([$bind_types], $refs));
                                    $insert_stmt->execute();
                                    $insert_stmt->close();
                                }

                                $conn->commit();
                                $message = 'Historical import succeeded. ' . count($valid_rows) . ' validation row(s) were recorded as historical evaluations.';
                                $message_type = 'success';
                                $import_summary = [
                                    'total_rows' => $all_rows,
                                    'successful_rows' => count($valid_rows),
                                    'failed_rows' => count($invalid_rows),
                                    'batch_id' => $batch_id,
                                ];
                            } catch (Exception $e) {
                                $conn->rollback();
                                $message = 'Import failed and was rolled back: ' . $e->getMessage();
                                $message_type = 'danger';
                            }
                        } else {
                            $message = 'No valid rows were found to import. Review the validation results below.';
                            $message_type = 'warning';
                        }
                    }
                }
            }
        }
    }
}

$stat_records_stmt = $conn->query("SELECT COUNT(*) AS total FROM evaluations WHERE is_historical = 1 AND deleted_at IS NULL");
$historical_total_count = $stat_records_stmt ? (int)($stat_records_stmt->fetch_assoc()['total'] ?? 0) : 0;

$stat_emps_stmt = $conn->query("SELECT COUNT(DISTINCT employee_id) AS total FROM evaluations WHERE is_historical = 1 AND deleted_at IS NULL");
$historical_employees_count = $stat_emps_stmt ? (int)($stat_emps_stmt->fetch_assoc()['total'] ?? 0) : 0;

$stat_batches_stmt = $conn->query("SELECT COUNT(*) AS total FROM evaluation_import_batches WHERE status = 'Imported'");
$historical_batches_count = $stat_batches_stmt ? (int)($stat_batches_stmt->fetch_assoc()['total'] ?? 0) : 0;

$stat_templates_stmt = $conn->query("SELECT COUNT(*) AS total FROM evaluation_templates WHERE status = 'Active' AND deleted_at IS NULL");
$active_templates_count = $stat_templates_stmt ? (int)($stat_templates_stmt->fetch_assoc()['total'] ?? 0) : 0;

// Fetch recent import batches
$recent_batches = [];
$rb_stmt = $conn->query("
    SELECT b.*, u.full_name AS importer_name 
    FROM evaluation_import_batches b
    LEFT JOIN users u ON b.imported_by = u.user_id
    ORDER BY b.imported_at DESC 
    LIMIT 5
");
if ($rb_stmt) {
    while ($r = $rb_stmt->fetch_assoc()) {
        $recent_batches[] = $r;
    }
}

require_once '../includes/header.php';
?>

<style>
    /* Raquel HRIS Design System Enhancements for Historical Import */
    .import-dropzone {
        border: 2px dashed #b8c8b4;
        background: #fbfdfa;
        border-radius: 16px;
        padding: 38px 24px;
        text-align: center;
        transition: all 0.25s ease;
        cursor: pointer;
        position: relative;
    }
    .import-dropzone:hover,
    .import-dropzone.drag-over {
        border-color: #082E06;
        background: #f0f7ee;
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(8, 46, 6, 0.08);
    }
    .import-dropzone-icon {
        width: 64px;
        height: 64px;
        line-height: 64px;
        margin: 0 auto 16px;
        border-radius: 50%;
        background: rgba(8, 46, 6, 0.06);
        color: #082E06;
        font-size: 28px;
        transition: all 0.25s ease;
    }
    .import-dropzone:hover .import-dropzone-icon {
        background: #082E06;
        color: #BD9414;
        transform: scale(1.08);
    }
    .selected-file-card {
        display: none;
        background: #ffffff;
        border: 1px solid #d7e4d3;
        border-radius: 12px;
        padding: 14px 18px;
        margin-top: 16px;
        animation: fadeIn 0.25s ease-in-out;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .spec-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.76rem;
        font-weight: 600;
        margin-bottom: 6px;
        margin-right: 6px;
    }
    .spec-pill.required {
        background: #fdf2e9;
        color: #b75508;
        border: 1px solid #fed7aa;
    }
    .spec-pill.optional {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
    }
    .instruction-step {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 18px;
    }
    .instruction-step:last-child {
        margin-bottom: 0;
    }
    .step-num {
        width: 28px;
        height: 28px;
        line-height: 28px;
        text-align: center;
        border-radius: 50%;
        background: #082E06;
        color: #BD9414;
        font-weight: 700;
        font-size: 0.8rem;
        flex-shrink: 0;
    }
    .step-content h6 {
        font-size: 0.88rem;
        font-weight: 700;
        color: #1a2e06;
        margin-bottom: 2px;
    }
    .step-content p {
        font-size: 0.8rem;
        color: #64748b;
        margin-bottom: 0;
        line-height: 1.45;
    }
</style>

<!-- Standard Page Hero Header -->
<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Manager · Historical Evaluations</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-file-import me-2" style="color:#BD9414;"></i>Historical Evaluation Import</h4>
            <p class="text-white-50 small mb-0 mt-2">Bulk import legacy ratings, past performance records, and historical review scores into the evaluation repository.</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="historical-import.php?download=template" class="btn btn-outline-light btn-sm fw-semibold rounded-pill px-3">
                <i class="fas fa-download me-1 text-warning"></i>Download CSV Template
            </a>
            <a href="evaluation-history.php" class="btn btn-light btn-sm fw-semibold rounded-pill px-3">
                <i class="fas fa-arrow-left me-1" style="color:#082E06;"></i>Back to Evaluation History
            </a>
        </div>
    </div>

    <!-- Glass Stat Cards in Hero -->
    <div class="row g-3 mt-2">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo number_format($historical_total_count); ?></div>
                        <div class="stat-label">Historical Records</div>
                    </div>
                    <i class="fas fa-database stat-icon text-white-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo number_format($historical_employees_count); ?></div>
                        <div class="stat-label">Employees Covered</div>
                    </div>
                    <i class="fas fa-user-check stat-icon" style="color:#28a745;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo number_format($historical_batches_count); ?></div>
                        <div class="stat-label">Batches Processed</div>
                    </div>
                    <i class="fas fa-layer-group stat-icon" style="color:#BD9414;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo number_format($active_templates_count); ?></div>
                        <div class="stat-label">Active Templates</div>
                    </div>
                    <i class="fas fa-clipboard-check stat-icon" style="color:#17a2b8;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo e($message_type ?? 'info'); ?> alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
        <div class="d-flex align-items-center">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : ($message_type === 'danger' ? 'times-circle' : 'info-circle')); ?> fs-4 me-3"></i>
            <div>
                <strong class="d-block mb-1"><?php echo $message_type === 'success' ? 'Operation Successful' : ($message_type === 'danger' ? 'Action Failed' : 'Notice'); ?></strong>
                <span class="small"><?php echo nl2br(e($message)); ?></span>
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php endif; ?>

<?php if ($import_summary): ?>
    <div class="content-card mb-4 fadeup">
        <div class="card-header">
            <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-chart-pie me-2 text-primary"></i>Import Results Summary</h6>
            <span class="badge bg-dark rounded-pill px-3">Batch #<?php echo (int) $import_summary['batch_id']; ?></span>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="p-3 rounded-3 text-center border" style="background:#f8fafc; border-color:#e2e8f0 !important;">
                        <div class="text-muted small text-uppercase fw-bold mb-1">Total Rows Scanned</div>
                        <div class="fs-2 fw-bold text-dark"><?php echo number_format((int) $import_summary['total_rows']); ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded-3 text-center border" style="background:#f0fdf4; border-color:#bbf7d0 !important;">
                        <div class="text-success small text-uppercase fw-bold mb-1">Successfully Imported</div>
                        <div class="fs-2 fw-bold text-success"><?php echo number_format((int) $import_summary['successful_rows']); ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded-3 text-center border" style="background:#fef2f2; border-color:#fecaca !important;">
                        <div class="text-danger small text-uppercase fw-bold mb-1">Failed / Rejected</div>
                        <div class="fs-2 fw-bold text-danger"><?php echo number_format((int) $import_summary['failed_rows']); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <!-- Left Column: Upload Dropzone Card -->
    <div class="col-12 col-lg-7">
        <div class="content-card h-100 fadeup">
            <div class="card-header">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-cloud-upload-alt me-2 text-success"></i>Upload Historical CSV File</h6>
                <span class="badge bg-light text-muted border">UTF-8 Encoded</span>
            </div>
            <div class="card-body p-4">
                <form method="post" enctype="multipart/form-data" id="historicalUploadForm">
                    <?php echo csrfField(); ?>

                    <div class="import-dropzone" id="dropzoneContainer" onclick="document.getElementById('historicalCsvInput').click();">
                        <div class="import-dropzone-icon">
                            <i class="fas fa-file-csv"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Choose CSV File or Drag & Drop Here</h6>
                        <p class="text-muted small mb-0">Standardized CSV template with historical scores and evaluation periods.</p>
                        <div class="text-secondary small mt-2" style="font-size:0.75rem;">
                            <i class="fas fa-info-circle me-1"></i>Supported format: <strong>.csv</strong> (Maximum file size: 10MB)
                        </div>
                        <input type="file" name="historical_csv" id="historicalCsvInput" class="d-none" accept=".csv,text/csv" required>
                    </div>

                    <!-- Selected File Info Banner -->
                    <div class="selected-file-card" id="selectedFileCard">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-2 rounded-3 bg-success bg-opacity-10 text-success">
                                    <i class="fas fa-file-excel fs-4"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark text-truncate" style="max-width: 280px;" id="selectedFileName">filename.csv</div>
                                    <div class="text-muted small" id="selectedFileSize">0 KB</div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3" id="clearFileBtn" title="Remove selected file">
                                <i class="fas fa-times me-1"></i>Remove
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 d-flex justify-content-end gap-2">
                        <a href="evaluation-history.php" class="btn btn-light rounded-pill px-4 fw-semibold border">Cancel</a>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold" id="submitImportBtn">
                            <i class="fas fa-file-import me-2"></i>Validate & Import Records
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Step Guide & Schema Quick Reference -->
    <div class="col-12 col-lg-5">
        <div class="content-card h-100 fadeup">
            <div class="card-header">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-book-reader me-2 text-primary"></i>Import Instructions & Format Guide</h6>
            </div>
            <div class="card-body p-4">
                <!-- 3 Step Guide -->
                <div class="instruction-step">
                    <div class="step-num">1</div>
                    <div class="step-content">
                        <h6>Download Template</h6>
                        <p>Click <strong>Download CSV Template</strong> to get the verified header columns. Do not rename the column titles.</p>
                    </div>
                </div>

                <div class="instruction-step">
                    <div class="step-num">2</div>
                    <div class="step-content">
                        <h6>Fill Evaluation Data</h6>
                        <p>Ensure <code>employee_code</code> matches registered employees and dates follow standard <code>YYYY-MM-DD</code> format.</p>
                    </div>
                </div>

                <div class="instruction-step">
                    <div class="step-num">3</div>
                    <div class="step-content">
                        <h6>Upload & Review</h6>
                        <p>Upload the CSV. Valid records will be recorded in evaluation histories, and any errors will be highlighted in the preview table.</p>
                    </div>
                </div>

                <hr class="my-4" style="border-color: var(--glass-border);">

                <!-- Required & Optional Columns -->
                <h6 class="fw-bold text-dark small text-uppercase mb-2" style="letter-spacing:0.5px;">Required Columns</h6>
                <div class="mb-3">
                    <span class="spec-pill required"><i class="fas fa-asterisk me-1" style="font-size:0.6rem;"></i>employee_code</span>
                    <span class="spec-pill required"><i class="fas fa-asterisk me-1" style="font-size:0.6rem;"></i>evaluation_type</span>
                    <span class="spec-pill required"><i class="fas fa-asterisk me-1" style="font-size:0.6rem;"></i>evaluation_period_start</span>
                    <span class="spec-pill required"><i class="fas fa-asterisk me-1" style="font-size:0.6rem;"></i>evaluation_period_end</span>
                    <span class="spec-pill required"><i class="fas fa-asterisk me-1" style="font-size:0.6rem;"></i>total_score</span>
                </div>

                <h6 class="fw-bold text-dark small text-uppercase mb-2" style="letter-spacing:0.5px;">Optional Columns</h6>
                <div>
                    <span class="spec-pill optional">kra_subtotal</span>
                    <span class="spec-pill optional">behavior_average</span>
                    <span class="spec-pill optional">performance_level</span>
                    <span class="spec-pill optional">template_name</span>
                    <span class="spec-pill optional">legacy_reference</span>
                    <span class="spec-pill optional">employee_comments</span>
                    <span class="spec-pill optional">supervisor_comments</span>
                    <span class="spec-pill optional">manager_comments</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($preview_rows)): ?>
    <!-- Validation Results Table -->
    <div class="content-card mb-4 fadeup">
        <div class="card-header">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-clipboard-check text-primary fs-5"></i>
                <h6 class="mb-0 fw-bold text-dark">Validation & Processing Results</h6>
            </div>
            <span class="badge bg-secondary rounded-pill px-3"><?php echo count($preview_rows); ?> Rows Processed</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead style="background:#f8fafc; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">
                        <tr>
                            <th class="ps-4" style="width:70px;">Row</th>
                            <th>Employee</th>
                            <th>Evaluation Type</th>
                            <th>Evaluation Period</th>
                            <th>Score</th>
                            <th>Validation Status</th>
                            <th class="pe-4">Validation Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview_rows as $row): ?>
                            <tr style="<?php echo $row['valid'] ? '' : 'background-color:#fffdfd;'; ?>">
                                <td class="ps-4 fw-bold text-secondary">#<?php echo (int) $row['row_number']; ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo e($row['employee_code'] ?: '—'); ?></div>
                                    <?php if (!empty($row['employee_name'])): ?>
                                        <div class="small text-muted"><?php echo e($row['employee_name']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $type_badges = [
                                        'Annual' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                        'Quarterly' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                                        'Initial' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                                        'Final' => 'bg-success-subtle text-success-emphasis border border-success-subtle',
                                    ];
                                    $badge_cls = $type_badges[$row['evaluation_type']] ?? 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                                    ?>
                                    <span class="badge rounded-pill <?php echo $badge_cls; ?> px-2 py-1"><?php echo e($row['evaluation_type'] ?: 'Unknown'); ?></span>
                                </td>
                                <td class="small text-secondary">
                                    <i class="far fa-calendar-alt me-1 text-muted"></i>
                                    <?php echo e($row['evaluation_period_start'] ?: '—') . ' &rarr; ' . e($row['evaluation_period_end'] ?: '—'); ?>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark"><?php echo e($row['total_score'] ?: '—'); ?></span>
                                    <?php if (!empty($row['performance_level'])): ?>
                                        <div class="small text-muted" style="font-size:0.72rem;"><?php echo e($row['performance_level']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['valid']): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1">
                                            <i class="fas fa-check-circle me-1"></i>Valid
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-1">
                                            <i class="fas fa-times-circle me-1"></i>Rejected
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4">
                                    <?php if (!empty($row['errors'])): ?>
                                        <ul class="mb-0 ps-3 small text-danger" style="list-style-type:circle;">
                                            <?php foreach ($row['errors'] as $error): ?>
                                                <li><?php echo e($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <span class="small text-success"><i class="fas fa-check me-1"></i>Imported to evaluation records.</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Recent Import Batches Audit History -->
<div class="content-card fadeup">
    <div class="card-header">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-history text-secondary"></i>
            <h6 class="mb-0 fw-bold text-dark">Recent Import Batches</h6>
        </div>
        <span class="text-muted small">Latest historical imports</span>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($recent_batches)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead style="background:#f8fafc; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">
                        <tr>
                            <th class="ps-4">Batch ID</th>
                            <th>Uploaded File</th>
                            <th>Imported By</th>
                            <th>Date & Time</th>
                            <th>Total Rows</th>
                            <th>Success</th>
                            <th>Failed</th>
                            <th class="pe-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_batches as $batch): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary">#<?php echo (int) $batch['import_batch_id']; ?></td>
                                <td>
                                    <i class="fas fa-file-csv text-muted me-1"></i>
                                    <span class="fw-semibold text-dark"><?php echo e($batch['uploaded_filename']); ?></span>
                                </td>
                                <td>
                                    <i class="fas fa-user-circle text-muted me-1"></i>
                                    <span><?php echo e($batch['importer_name'] ?? 'System User'); ?></span>
                                </td>
                                <td class="small text-secondary">
                                    <?php echo date('M d, Y h:i A', strtotime($batch['imported_at'])); ?>
                                </td>
                                <td><span class="fw-semibold"><?php echo number_format((int) $batch['total_rows']); ?></span></td>
                                <td><span class="badge bg-success-subtle text-success rounded-pill px-2"><?php echo number_format((int) $batch['successful_rows']); ?></span></td>
                                <td>
                                    <?php if ((int)$batch['failed_rows'] > 0): ?>
                                        <span class="badge bg-danger-subtle text-danger rounded-pill px-2"><?php echo number_format((int) $batch['failed_rows']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4">
                                    <span class="badge bg-success rounded-pill px-3 py-1"><?php echo e($batch['status']); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox text-muted opacity-25" style="font-size:3rem;"></i>
                <h6 class="fw-bold text-secondary mt-3 mb-1">No Historical Batches Recorded Yet</h6>
                <p class="text-muted small mb-0">When you import historical evaluations using a CSV file, the batch history will appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropzone = document.getElementById('dropzoneContainer');
    const fileInput = document.getElementById('historicalCsvInput');
    const selectedFileCard = document.getElementById('selectedFileCard');
    const selectedFileName = document.getElementById('selectedFileName');
    const selectedFileSize = document.getElementById('selectedFileSize');
    const clearFileBtn = document.getElementById('clearFileBtn');
    const submitBtn = document.getElementById('submitImportBtn');

    function formatBytes(bytes, decimals = 1) {
        if (!+bytes) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
    }

    function handleFile(file) {
        if (!file) return;
        if (!file.name.toLowerCase().endsWith('.csv')) {
            alert('Please select a valid CSV file (.csv).');
            fileInput.value = '';
            selectedFileCard.style.display = 'none';
            return;
        }

        selectedFileName.textContent = file.name;
        selectedFileSize.textContent = formatBytes(file.size);
        selectedFileCard.style.display = 'block';
    }

    fileInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            handleFile(this.files[0]);
        }
    });

    clearFileBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        fileInput.value = '';
        selectedFileCard.style.display = 'none';
    });

    // Drag and drop event handlers
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('drag-over');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('drag-over');
        }, false);
    });

    dropzone.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files && files.length > 0) {
            fileInput.files = files;
            handleFile(files[0]);
        }
    }, false);
});
</script>

