<?php
// Shared by the HR Manager and HR Supervisor import pages.
$page_title = 'Historical Evaluation Import';
require_once __DIR__ . '/session-check.php';
checkRole(['HR Manager', 'HR Supervisor']);
require_once __DIR__ . '/functions.php';
if (!ensureHistoricalImportSchema($conn)) {
    http_response_code(500);
    exit('Historical import storage is unavailable.');
}

$required_headers = ['employee_code', 'evaluation_type', 'evaluation_period_start', 'evaluation_period_end', 'total_score'];
$message_type = null;
$message = null;
$import_summary = null;
$preview_rows = [];
$preview_errors = [];
$historical_preview_ready = false;
$historical_preview_token = '';

function historicalImportDate($value): string
{
    $raw = trim((string)$value);
    foreach (['!Y-m-d', '!m/d/Y', '!n/j/Y'] as $format) {
        $date = DateTime::createFromFormat($format, $raw);
        $errors = DateTime::getLastErrors();
        if ($date && (!$errors || (!$errors['warning_count'] && !$errors['error_count']))) return $date->format('Y-m-d');
    }
    return '';
}

function historicalImportRatings(string $input, string $label, array &$errors): array
{
    if (trim($input) === '') return [];
    $values = explode(';', $input);
    $ratings = [];
    foreach ($values as $value) {
        $value = trim($value);
        if ($value === '' || !is_numeric($value) || (float)$value < 1 || (float)$value > 4) {
            $errors[] = "$label must contain ratings from 1 to 4, separated by semicolons.";
            return [];
        }
        $ratings[] = (float)$value;
    }
    return $ratings;
}

function historicalCriterionColumn(array $criterion): string
{
    $label = $criterion['section'] . ' ' . $criterion['sort_order'] . ' - ' . $criterion['criterion_name'];
    if ($criterion['section'] === 'KRA') {
        $label .= ' (' . $criterion['weight'] . '%)';
    } elseif ($criterion['kpi_description'] !== null && $criterion['kpi_description'] !== '') {
        $label .= ' - ' . $criterion['kpi_description'];
    }
    return $label;
}

// A named historical form can be reconstructed from the populated columns of its own row.
function historicalCsvCriteria(array $labels, array &$errors): array
{
    $criteria = [];
    $positions = [];
    foreach ($labels as $label) {
        $indicator = null;
        $weight = 0;
        if (preg_match('/^KRA ([1-9][0-9]*) - (.+) \(([0-9]{1,3}(?:\.[0-9]{1,2})?)%\)$/iu', $label, $m)) {
            $section = 'KRA';
            $weight = (float)$m[3];
        } elseif (preg_match('/^Behavior ([1-9][0-9]*) - (.+?)(?: - (.+))?$/iu', $label, $m)) {
            $section = 'Behavior';
            $indicator = $m[3] ?? null;
        } else {
            $errors[] = "Invalid criterion heading: $label.";
            continue;
        }
        $order = (int)$m[1];
        $name = trim($m[2]);
        if (isset($positions[$section][$order]) || mb_strlen($name) > 150 || $name === '' ||
            ($section === 'KRA' && ($weight <= 0 || $weight > 100))) {
            $errors[] = "Check the item number, name and weight in $label.";
        }
        $positions[$section][$order] = true;
        $criteria[] = ['criterion_id'=>count($criteria), 'csv_label'=>$label, 'section'=>$section,
            'sort_order'=>$order, 'criterion_name'=>$name, 'weight'=>$weight, 'kpi_description'=>$indicator];
    }
    return $criteria;
}

function historicalSaveCsvForm($conn, array $entry): array
{
    $stmt = $conn->prepare("INSERT INTO evaluation_templates
        (template_name,evaluation_type,target_department_id,target_job_title_id,status,form_code,kra_weight,behavior_weight)
        VALUES (?,?,?,?,'Archived','Historical CSV',80,20)");
    $stmt->bind_param('ssii', $entry['template_name'], $entry['evaluation_type'], $entry['department_id'], $entry['job_title_id']);
    $stmt->execute();
    $templateId = (int)$stmt->insert_id;
    $stmt->close();
    $stmt = $conn->prepare('INSERT INTO evaluation_criteria (template_id,section,sort_order,criterion_name,weight,kpi_description) VALUES (?,?,?,?,?,?)');
    $ids = [];
    foreach ($entry['csv_criteria'] as $item) {
        $stmt->bind_param('isisds', $templateId, $item['section'], $item['sort_order'], $item['criterion_name'], $item['weight'], $item['kpi_description']);
        $stmt->execute();
        $ids[$item['criterion_id']] = (int)$stmt->insert_id;
    }
    $stmt->close();
    foreach ($entry['criterion_scores'] as &$score) $score[0] = $ids[$score[0]];
    $entry['template_id'] = $templateId;
    return $entry;
}

function historicalImportValidate($conn, array $rawRows, ?int $selectedEmployeeId = null): array
{
    $preview = [];
    $valid = [];
    $seen = [];
    $seenReferences = [];
    foreach ($rawRows as $raw) {
        $originalLabels = array_keys($raw);
        $raw = array_change_key_case($raw, CASE_LOWER);
        $entry = array_intersect_key($raw, array_flip(['row_number','employee_comments','supervisor_comments','manager_comments']));
        $labels = array_combine(array_map('strtolower', $originalLabels), $originalLabels);
        $namedColumns = preg_grep('/^(kra|behavior) [0-9]+ - /i', array_keys($raw));
        $filledColumns = array_filter($namedColumns, static fn($key) => trim((string)$raw[$key]) !== '');
        $errors = [];
        $entry['employee_code'] = trim((string)($raw['employee_code'] ?? ''));
        $entry['evaluation_type'] = ucfirst(strtolower(trim((string)($raw['evaluation_type'] ?? ''))));
        $entry['evaluation_period_start'] = historicalImportDate($raw['evaluation_period_start'] ?? '');
        $entry['evaluation_period_end'] = historicalImportDate($raw['evaluation_period_end'] ?? '');
        $entry['template_name'] = trim((string)($raw['template_name'] ?? ''));
        $entry['legacy_reference'] = trim((string)($raw['legacy_reference'] ?? ''));
        $entry['employee_name'] = '';
        if ($entry['employee_code'] === '') {
            $errors[] = 'Employee code is required.';
        } else {
            $stmt = $conn->prepare('SELECT employee_id, department_id, job_title_id, CONCAT(first_name," ",last_name) AS employee_name FROM employees WHERE employee_code=? AND deleted_at IS NULL LIMIT 1');
            $stmt->bind_param('s', $entry['employee_code']);
            $stmt->execute();
            $employee = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$employee) $errors[] = 'Employee code does not match an employee record.';
            else {
                $entry['employee_id'] = (int)$employee['employee_id'];
                $entry['employee_name'] = $employee['employee_name'];
                if ($selectedEmployeeId !== null && $entry['employee_id'] !== $selectedEmployeeId) {
                    $errors[] = 'Employee code must match the employee selected for this upload.';
                }
                $entry['department_id'] = (int)$employee['department_id'];
                $entry['job_title_id'] = (int)$employee['job_title_id'];
            }
        }
        if (!in_array($entry['evaluation_type'], ['Initial','Final','Quarterly','Annual'], true)) $errors[] = 'Evaluation type must be Initial, Final, Quarterly, or Annual.';
        if ($entry['evaluation_period_start'] === '' || $entry['evaluation_period_end'] === '') $errors[] = 'Dates must use YYYY-MM-DD or MM/DD/YYYY.';
        elseif ($entry['evaluation_period_start'] > $entry['evaluation_period_end']) $errors[] = 'Period From cannot be after Period To.';
        foreach (['total_score','kra_subtotal','behavior_average'] as $field) {
            $value = trim((string)($raw[$field] ?? ''));
            $entry[$field] = $value;
            if ($field === 'total_score' || $value !== '') {
                if (!is_numeric($value) || (float)$value < 1 || (float)$value > 4) $errors[] = "$field must be a number from 1 to 4.";
            }
        }
        if (strlen($entry['legacy_reference']) > 150) $errors[] = 'Legacy reference is too long (150 characters maximum).';
        $entry['template_id'] = null;
        $criteria = [];
        if (isset($entry['department_id'], $entry['job_title_id']) && in_array($entry['evaluation_type'], ['Initial','Final','Quarterly','Annual'], true)) {
            $templateSql = 'SELECT t.template_id,t.template_name,t.kra_weight,t.behavior_weight FROM evaluation_templates t WHERE t.evaluation_type=? AND t.deleted_at IS NULL AND t.target_department_id=? AND (t.target_job_title_id=? OR EXISTS(SELECT 1 FROM evaluation_template_positions tp WHERE tp.template_id=t.template_id AND tp.job_title_id=?))';
            $templateSql .= " AND NOT (t.status='Archived' AND t.form_code <=> 'Historical CSV')";
            $types = 'siii';
            $params = [$entry['evaluation_type'],$entry['department_id'],$entry['job_title_id'],$entry['job_title_id']];
            if ($entry['template_name'] !== '') { $templateSql .= ' AND t.template_name=?'; $types .= 's'; $params[] = $entry['template_name']; }
            $templateSql .= ' ORDER BY t.template_id';
            $stmt = $conn->prepare($templateSql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $templates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            if (!$templates && $filledColumns && $entry['template_name'] !== '') {
                if (mb_strlen($entry['template_name']) > 150) $errors[] = 'Form name exceeds 150 characters.';
                $criteria = historicalCsvCriteria(array_map(static fn($key) => $labels[$key], $filledColumns), $errors);
                $entry['csv_criteria'] = $criteria;
                $template = ['kra_weight'=>80, 'behavior_weight'=>20];
            } elseif (count($templates) !== 1) $errors[] = count($templates) ? 'Multiple matching forms exist; specify template_name.' : 'No matching form for this employee, department, position, and evaluation type.';
            else {
                $template = $templates[0];
                $entry['template_id'] = (int)$template['template_id'];
                $entry['template_name'] = $template['template_name'];
                $stmt = $conn->prepare("SELECT criterion_id,section,weight,sort_order,criterion_name,kpi_description FROM evaluation_criteria WHERE template_id=? ORDER BY section='Behavior',sort_order,criterion_id");
                $stmt->bind_param('i', $entry['template_id']);
                $stmt->execute();
                $criteria = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
            }
        }
        $entry['kra_scores'] = trim((string)($raw['kra_scores'] ?? ''));
        $entry['behavior_scores'] = trim((string)($raw['behavior_scores'] ?? ''));
        if ($filledColumns) {
            if ($entry['kra_scores'] !== '' || $entry['behavior_scores'] !== '') {
                $errors[] = 'Use named rating columns or score lists, not both.';
            }
            $expectedColumns = [];
            $ratings = ['KRA' => [], 'Behavior' => []];
            foreach ($criteria as $criterion) {
                $label = $criterion['csv_label'] ?? historicalCriterionColumn($criterion);
                $key = strtolower(trim($label));
                $expectedColumns[] = $key;
                $rating = trim((string)($raw[$key] ?? ''));
                if ($rating === '' || !is_numeric($rating) || (float)$rating < 1 || (float)$rating > 4) {
                    $errors[] = "Enter a rating from 1 to 4 for $label.";
                }
                $ratings[$criterion['section']][] = $rating;
                $entry['named_ratings'][$label] = $rating;
            }
            if (array_diff($filledColumns, $expectedColumns)) $errors[] = 'Some rating columns do not belong to the matching form.';
            $entry['kra_scores'] = implode(';', $ratings['KRA']);
            $entry['behavior_scores'] = implode(';', $ratings['Behavior']);
        }
        $detailed = $entry['kra_scores'] !== '' || $entry['behavior_scores'] !== '';
        $entry['import_mode'] = $detailed ? 'Detailed' : 'Summary';
        if ($detailed) {
            if ($entry['kra_scores'] === '' || $entry['behavior_scores'] === '') $errors[] = 'Detailed rows require both kra_scores and behavior_scores.';
            $kraScores = historicalImportRatings($entry['kra_scores'], 'kra_scores', $errors);
            $behaviorScores = historicalImportRatings($entry['behavior_scores'], 'behavior_scores', $errors);
            $kraCriteria = array_values(array_filter($criteria, static fn($c) => $c['section'] === 'KRA'));
            $behaviorCriteria = array_values(array_filter($criteria, static fn($c) => $c['section'] === 'Behavior'));
            if ($criteria && (count($kraScores) !== count($kraCriteria) || count($behaviorScores) !== count($behaviorCriteria))) $errors[] = 'Detailed rating counts must match the selected form criteria.';
            if ($criteria && count($behaviorCriteria) !== 8) $errors[] = 'Detailed form must have eight behavior criteria.';
            $kraWeight = array_sum(array_column($kraCriteria, 'weight'));
            if ($criteria && abs($kraWeight - 100) > .001) $errors[] = 'Selected form KRA weights must total 100%.';
            if ($criteria && (abs((float)$template['kra_weight'] - 80) > .001 || abs((float)$template['behavior_weight'] - 20) > .001)) $errors[] = 'Detailed form must use 80% KRA and 20% behavior weights.';
            if (!$errors) {
                $kra = 0.0;
                foreach ($kraCriteria as $i => $criterion) $kra += (float)$criterion['weight'] * $kraScores[$i] / 100;
                $behavior = round(array_sum($behaviorScores) / 8, 2);
                $kra = round($kra, 2);
                $total = round($kra * .8 + $behavior * .2, 2);
                if (abs((float)$entry['total_score'] - $total) > .01) $errors[] = "Total score differs from calculated $total.";
                if ($entry['kra_subtotal'] !== '' && abs((float)$entry['kra_subtotal'] - $kra) > .01) $errors[] = "KRA subtotal differs from calculated $kra.";
                if ($entry['behavior_average'] !== '' && abs((float)$entry['behavior_average'] - $behavior) > .01) $errors[] = "Behavior average differs from calculated $behavior.";
                $entry['kra_subtotal'] = $kra;
                $entry['behavior_average'] = $behavior;
                $entry['criterion_scores'] = [];
                foreach ($kraCriteria as $i => $criterion) $entry['criterion_scores'][] = [$criterion['criterion_id'],$kraScores[$i],round((float)$criterion['weight'] * $kraScores[$i] / 100,2)];
                foreach ($behaviorCriteria as $i => $criterion) $entry['criterion_scores'][] = [$criterion['criterion_id'],$behaviorScores[$i],round($behaviorScores[$i] * .2 / 8,2)];
            }
        } elseif ($entry['kra_subtotal'] !== '' && $entry['behavior_average'] !== '') {
            $expected = round((float)$entry['kra_subtotal'] * .8 + (float)$entry['behavior_average'] * .2, 2);
            if (abs((float)$entry['total_score'] - $expected) > .01) $errors[] = "Total score differs from 80/20 calculation $expected.";
        }
        if ($entry['total_score'] !== '' && is_numeric($entry['total_score']) && (float)$entry['total_score'] >= 1 && (float)$entry['total_score'] <= 4) {
            $entry['performance_level'] = getPerformanceLevel((float)$entry['total_score']);
        }
        if (isset($entry['employee_id']) && $entry['evaluation_period_start'] !== '' && $entry['evaluation_period_end'] !== '' && in_array($entry['evaluation_type'], ['Initial','Final','Quarterly','Annual'], true)) {
            $key = implode('|', [$entry['employee_id'],$entry['evaluation_type'],$entry['evaluation_period_start'],$entry['evaluation_period_end']]);
            if (isset($seen[$key])) $errors[] = 'Duplicate employee, type, and period within this CSV.';
            $seen[$key] = true;
            $stmt = $conn->prepare('SELECT evaluation_id FROM evaluations WHERE employee_id=? AND evaluation_type=? AND evaluation_period_start=? AND evaluation_period_end=? AND deleted_at IS NULL LIMIT 1');
            $stmt->bind_param('isss', $entry['employee_id'],$entry['evaluation_type'],$entry['evaluation_period_start'],$entry['evaluation_period_end']);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) $errors[] = 'An evaluation already exists for this employee, type, and period.';
            $stmt->close();
        }
        if ($entry['legacy_reference'] !== '') {
            if (isset($seenReferences[$entry['legacy_reference']])) $errors[] = 'Duplicate legacy reference within this CSV.';
            $seenReferences[$entry['legacy_reference']] = true;
            $stmt = $conn->prepare('SELECT evaluation_id FROM evaluations WHERE legacy_reference=? AND deleted_at IS NULL LIMIT 1');
            $stmt->bind_param('s', $entry['legacy_reference']);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) $errors[] = 'Legacy reference already exists.';
            $stmt->close();
        }
        $entry['errors'] = $errors;
        $entry['valid'] = !$errors;
        $preview[] = $entry;
        if (!$errors) $valid[] = $entry;
    }
    return [$preview,$valid];
}

if (($_GET['download'] ?? '') === 'sample') {
    $samplePath = __DIR__ . '/../database/samples/patricia_annual_2020.csv';
    if (!is_readable($samplePath)) {
        http_response_code(404);
        exit('The sample CSV is unavailable.');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="annual_2019_batch_sample.csv"');
    echo "\xEF\xBB\xBF";
    readfile($samplePath);
    exit;
}

if (($_GET['download'] ?? '') === 'template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=historical_evaluations_template.csv');
    $output = fopen('php://output','w');
    fwrite($output, "\xEF\xBB\xBF");
    if (!empty($batchDownload)) {
        $columns = array_merge($required_headers, ['template_name','kra_subtotal','behavior_average','legacy_reference','employee_comments','supervisor_comments','manager_comments']);
        $items = $conn->query("SELECT c.section,c.sort_order,c.criterion_name,c.kpi_description,c.weight
            FROM evaluation_criteria c JOIN evaluation_templates t USING(template_id)
            WHERE t.deleted_at IS NULL ORDER BY t.template_id,c.section='Behavior',c.sort_order,c.criterion_id");
        $ratingColumns = [];
        foreach ($items as $item) {
            $label = historicalCriterionColumn($item);
            $ratingColumns[strtolower($label)] = $label;
        }
        fputcsv($output, array_merge($columns, array_values($ratingColumns)));
    } else {
        fputcsv($output, ['employee_code','evaluation_type','evaluation_period_start','evaluation_period_end','total_score','kra_subtotal','behavior_average','template_name','legacy_reference','employee_comments','supervisor_comments','manager_comments','kra_scores','behavior_scores']);
        fputcsv($output, ['HRD-001','Annual','2024-01-01','2024-12-31','3.60','3.70','3.20','', 'LEG-2024-001','Historical comments','','','','']);
    }
    fclose($output);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $action = (string)($_POST['import_action'] ?? 'preview');
    if ($action === 'preview') {
        unset($_SESSION['historical_import_preview']);
        if (!isset($_FILES['historical_csv']) || $_FILES['historical_csv']['error'] !== UPLOAD_ERR_OK || $_FILES['historical_csv']['size'] > 10 * 1024 * 1024 || strtolower(pathinfo($_FILES['historical_csv']['name'],PATHINFO_EXTENSION)) !== 'csv') {
            $message = 'Choose a CSV file no larger than 10 MB.';
            $message_type = 'danger';
        } else {
            $handle = fopen($_FILES['historical_csv']['tmp_name'],'rb');
            $headers = $handle ? fgetcsv($handle) : false;
            if (isset($headers[0])) $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            $headers = $headers ? array_map(static fn($v) => trim((string)$v), $headers) : [];
            $missing = array_diff($required_headers,array_map('strtolower',$headers));
            if (!$headers || $missing || count($headers) !== count(array_unique(array_map('strtolower',$headers)))) {
                $message = $missing ? 'Missing required columns: ' . implode(', ',$missing) : 'CSV headers are empty or duplicated.';
                $message_type = 'danger';
            } else {
                $rawRows = [];
                $line = 1;
                while (($values = fgetcsv($handle)) !== false) {
                    $line++;
                    if ($values === [null] || !array_filter($values, static fn($v) => trim((string)$v) !== '')) continue;
                    if (count($values) !== count($headers)) { $message = "Row $line has a different number of columns."; $message_type = 'danger'; break; }
                    $entry = array_combine($headers,$values);
                    $entry['row_number'] = $line;
                    $rawRows[] = $entry;
                    if (count($rawRows) > 1000) { $message = 'Upload at most 1,000 evaluations per file.'; $message_type = 'danger'; break; }
                }
                if (!$message && !$rawRows) { $message = 'The CSV contains no records.'; $message_type = 'warning'; }
                if (!$message) {
                    [$preview_rows,$valid] = historicalImportValidate($conn,$rawRows);
                    $historical_preview_ready = count($valid) === count($rawRows);
                    $historical_preview_token = bin2hex(random_bytes(16));
                    $_SESSION['historical_import_preview'] = ['token'=>$historical_preview_token,'user_id'=>(int)$_SESSION['user_id'],'filename'=>basename($_FILES['historical_csv']['name']),'rows'=>$rawRows,'created'=>time()];
                    $message = $historical_preview_ready ? 'Validation passed. Review the rows, then confirm the import.' : 'Validation found errors. Correct the CSV and upload it again; nothing was imported.';
                    $message_type = $historical_preview_ready ? 'info' : 'warning';
                }
            }
            if ($handle) fclose($handle);
        }
    } elseif ($action === 'confirm') {
        $pending = $_SESSION['historical_import_preview'] ?? null;
        unset($_SESSION['historical_import_preview']);
        if (!$pending || !hash_equals((string)$pending['token'],(string)($_POST['preview_token'] ?? '')) || (int)$pending['user_id'] !== (int)$_SESSION['user_id'] || time() - (int)$pending['created'] > 1800) {
            $message = 'Preview expired. Upload the CSV again.';
            $message_type = 'danger';
        } else {
            try {
                $conn->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
                $conn->begin_transaction();
                [$preview_rows,$valid] = historicalImportValidate($conn,$pending['rows'],$pending['employee_id'] ?? null);
                if (count($valid) !== count($pending['rows'])) throw new DomainException('Records changed since preview. Correct the listed rows and upload again. Nothing was imported.');
                $employeeIds = array_unique(array_column($valid, 'employee_id'));
                sort($employeeIds, SORT_NUMERIC);
                foreach ($employeeIds as $employeeId) {
                    $stmt = $conn->prepare('SELECT employee_id FROM employees WHERE employee_id=? FOR UPDATE');
                    $stmt->bind_param('i',$employeeId);
                    $stmt->execute();
                    $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                }
                [$preview_rows,$valid] = historicalImportValidate($conn,$pending['rows'],$pending['employee_id'] ?? null);
                if (count($valid) !== count($pending['rows'])) throw new DomainException('A matching evaluation was added since preview. Nothing was imported.');
                $mode = count(array_filter($valid,static fn($row) => $row['import_mode'] === 'Detailed')) ? 'Detailed' : 'Summary';
                $batch = $conn->prepare("INSERT INTO evaluation_import_batches (uploaded_filename,import_mode,imported_by,total_rows,successful_rows,failed_rows,status) VALUES (?,?,?,?,?,0,'Imported')");
                $count = count($valid);
                $userId = (int)$_SESSION['user_id'];
                $batch->bind_param('ssiii',$pending['filename'],$mode,$userId,$count,$count);
                $batch->execute();
                $batchId = (int)$conn->insert_id;
                $batch->close();
                $insert = $conn->prepare("INSERT INTO evaluations (employee_id,template_id,evaluation_type,evaluation_period_start,evaluation_period_end,status,total_score,kra_subtotal,behavior_average,performance_level,staff_comments,supervisor_comments,manager_comments,is_historical,record_source,import_batch_id,legacy_reference,historical_remarks,employee_consent_agreed) VALUES (?,?,?,?,?,'Imported',?,?,?,?,?,?,?,1,'Historical Import',?,?,?,0)");
                $scoreInsert = $conn->prepare('INSERT INTO evaluation_scores (evaluation_id,criterion_id,score_value,weighted_score) VALUES (?,?,?,?)');
                foreach ($valid as $entry) {
                    if (isset($entry['csv_criteria'])) $entry = historicalSaveCsvForm($conn, $entry);
                    $kra = $entry['kra_subtotal'] === '' ? null : (float)$entry['kra_subtotal'];
                    $behavior = $entry['behavior_average'] === '' ? null : (float)$entry['behavior_average'];
                    $staff = trim((string)($entry['employee_comments'] ?? '')) ?: null;
                    $supervisor = trim((string)($entry['supervisor_comments'] ?? '')) ?: null;
                    $manager = trim((string)($entry['manager_comments'] ?? '')) ?: null;
                    $legacy = $entry['legacy_reference'] ?: null;
                    $remarks = 'Imported from historical CSV by user #' . $userId . '. No new employee consent or approval was recorded.';
                    $total = (float)$entry['total_score'];
                    $insert->bind_param('iisssdddssssiss', $entry['employee_id'],$entry['template_id'],$entry['evaluation_type'],$entry['evaluation_period_start'],$entry['evaluation_period_end'],$total,$kra,$behavior,$entry['performance_level'],$staff,$supervisor,$manager,$batchId,$legacy,$remarks);
                    $insert->execute();
                    $evaluationId = (int)$conn->insert_id;
                    foreach ($entry['criterion_scores'] ?? [] as [$criterionId,$rating,$weighted]) {
                        $scoreInsert->bind_param('iidd',$evaluationId,$criterionId,$rating,$weighted);
                        $scoreInsert->execute();
                    }
                }
                $scoreInsert->close();
                $insert->close();
                $conn->commit();
                logAudit($conn,$userId,'IMPORT','Evaluation',$batchId,"Historical evaluation import: $count records imported.",['module'=>'Performance & Evaluation']);
                $import_summary = ['total_rows'=>$count,'successful_rows'=>$count,'failed_rows'=>0,'batch_id'=>$batchId];
                $message = "$count historical evaluations imported.";
                $message_type = 'success';
            } catch (Throwable $e) {
                $conn->rollback();
                $message = 'Import stopped: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}

if ($historical_preview_ready && isset($_SESSION['historical_import_preview'])) {
    $historical_preview_token = $_SESSION['historical_import_preview']['token'];
}
