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

require_once '../includes/header.php';
?>
<style>
    .historical-import-page .card {
        border: 1px solid #bdcba9 !important;
        background: #fff;
        box-shadow: 0 8px 24px rgba(8, 46, 6, .06) !important;
    }
    .historical-import-page .card-body {
        background: #f8faf5;
    }
    .historical-import-page .card h2 {
        color: #234d08;
    }
    .historical-import-page .form-control {
        border-color: #aab99a;
    }
    .historical-import-page .form-control:focus {
        border-color: #bd9414;
        box-shadow: 0 0 0 3px rgba(189, 148, 20, .18);
    }
    .historical-import-page .btn-primary {
        background: #bd9414;
        border-color: #bd9414;
        color: #17310d;
        font-weight: 700;
    }
    .historical-import-page .btn-primary:hover {
        background: #d2ad35;
        border-color: #d2ad35;
        color: #17310d;
    }
    .historical-import-page .table thead th {
        background: #234d08;
        color: #fff;
        border-color: #bdcba9;
    }
</style>
<div class="container-fluid py-4 historical-import-page">
    <div class="row justify-content-center">
        <div class="col-12 col-xxl-11">
            <div class="page-hero mb-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="text-uppercase small fw-bold" style="letter-spacing:1px; opacity:.72;">HR Supervisor • Historical Records</div>
                        <h1 class="h3 mb-0 text-white fw-bold"><i class="fas fa-file-import me-2"></i>Historical Evaluation Import</h1>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="historical-import.php?download=template" class="btn btn-light btn-sm fw-semibold"><i class="fas fa-download me-1"></i>Download CSV Template</a>
                        <a href="evaluation-history.php" class="btn btn-outline-light btn-sm fw-semibold"><i class="fas fa-arrow-left me-1"></i>Back to Evaluation History</a>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo e($message_type ?? 'info'); ?> rounded-4 border-0 shadow-sm">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : ($message_type === 'danger' ? 'times-circle' : 'info-circle')); ?> me-2"></i>
                    <?php echo nl2br(e($message)); ?>
                </div>
            <?php endif; ?>

            <?php if ($import_summary): ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small text-uppercase fw-bold">Total Rows</div>
                                <div class="display-6 fw-bold text-dark"><?php echo (int) $import_summary['total_rows']; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small text-uppercase fw-bold">Imported</div>
                                <div class="display-6 fw-bold text-success"><?php echo (int) $import_summary['successful_rows']; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small text-uppercase fw-bold">Failed</div>
                                <div class="display-6 fw-bold text-danger"><?php echo (int) $import_summary['failed_rows']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 fw-bold mb-3"><i class="fas fa-upload me-2"></i>Upload CSV file</h2>
                    <form method="post" enctype="multipart/form-data">
                        <?php echo csrfField(); ?>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Historical evaluation CSV</label>
                                <input type="file" name="historical_csv" class="form-control" accept=".csv,text/csv" required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100 fw-semibold"><i class="fas fa-file-import me-1"></i>Validate & Import</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($preview_rows)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-bold mb-3"><i class="fas fa-table me-2"></i>Validation results</h2>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Row</th>
                                        <th>Employee</th>
                                        <th>Type</th>
                                        <th>Period</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($preview_rows as $row): ?>
                                        <tr class="<?php echo $row['valid'] ? 'table-success-subtle' : 'table-danger-subtle'; ?>">
                                            <td><?php echo (int) $row['row_number']; ?></td>
                                            <td>
                                                <div class="fw-semibold"><?php echo e($row['employee_code'] ?: ''); ?></div>
                                                <?php if (!empty($row['employee_name'])): ?><div class="small text-muted"><?php echo e($row['employee_name']); ?></div><?php endif; ?>
                                            </td>
                                            <td><?php echo e($row['evaluation_type']); ?></td>
                                            <td><?php echo e($row['evaluation_period_start'] ?: '-') . ' to ' . e($row['evaluation_period_end'] ?: '-'); ?></td>
                                            <td><?php echo e($row['total_score'] ?: '-'); ?></td>
                                            <td>
                                                <?php if ($row['valid']): ?>
                                                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">Valid</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($row['errors'])): ?>
                                                    <ul class="mb-0 ps-3 small">
                                                        <?php foreach ($row['errors'] as $error): ?>
                                                            <li><?php echo e($error); ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else: ?>
                                                    <span class="small text-muted">Ready for historical import.</span>
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
        </div>
    </div>
</div>
