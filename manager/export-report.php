<?php
/**
 * Export Report - CSV & PDF Downloads
 * Handles both CSV streaming and FPDF-based PDF generation.
 */
require_once '../includes/session-check.php';
checkRole(['HR Manager']);

$report_type = $_GET['report_type'] ?? '';
$branch_id = intval($_GET['branch_id'] ?? 0);
$department_id = intval($_GET['department'] ?? 0);
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$export_type = strtolower(trim($_GET['export_type'] ?? 'csv'));

// Build SQL based on report type
$rows = [];
$headers = [];
$report_title = '';

switch ($report_type) {

    // ===========================
    // EMPLOYEE MASTERLIST
    // ===========================
    case 'employee_masterlist':
        $report_title = 'Employee Masterlist';
        $headers = ['#', 'Last Name', 'First Name', 'Middle Name', 'Position', 'Department', 'Branch', 'Hire Date', 'Status', 'Type', 'Mobile', 'Email'];

        $where = "WHERE e.is_active = 1 AND e.deleted_at IS NULL AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)";
        $params = [];
        $types = '';

        if ($branch_id > 0) {
            $where .= " AND e.branch_id = ?";
            $params[] = $branch_id;
            $types .= 'i';
        }
        if ($department_id > 0) {
            $where .= " AND e.department_id = ?";
            $params[] = $department_id;
            $types .= 'i';
        }
        if (!empty($date_from)) {
            $where .= " AND e.hire_date >= ?";
            $params[] = $date_from;
            $types .= 's';
        }
        if (!empty($date_to)) {
            $where .= " AND e.hire_date <= ?";
            $params[] = $date_to;
            $types .= 's';
        }

        $sql = "SELECT e.last_name, e.first_name, e.middle_name, e.job_title, d.department_name, e.hire_date,
                       e.employment_status, e.employment_type, b.branch_name, c.mobile_number, c.personal_email
                FROM employees e
                LEFT JOIN branches b ON e.branch_id = b.branch_id
                LEFT JOIN departments d ON e.department_id = d.department_id
                LEFT JOIN employee_contacts c ON e.employee_id = c.employee_id
                $where ORDER BY e.last_name, e.first_name";

        $stmt = $conn->prepare($sql);
        if (!empty($params))
            $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $i = 1;
        while ($r = $result->fetch_assoc()) {
            $rows[] = [
                $i++,
                $r['last_name'],
                $r['first_name'],
                $r['middle_name'] ?? '',
                $r['job_title'],
                $r['department_name'] ?? 'N/A',
                $r['branch_name'] ?? 'N/A',
                $r['hire_date'] ? date('M d, Y', strtotime($r['hire_date'])) : 'N/A',
                $r['employment_status'],
                $r['employment_type'],
                $r['mobile_number'] ?? 'N/A',
                $r['personal_email'] ?? 'N/A'
            ];
        }
        $stmt->close();
        break;

    // ===========================
    // PERFORMANCE SUMMARY
    // ===========================
    case 'performance_summary':
        $report_title = 'Performance Summary';
        $headers = ['#', 'Employee', 'Position', 'Department', 'Branch', 'Template', 'Eval Period', 'Score (%)', 'Performance Level', 'Approved Date'];

        $where = "WHERE ev.status = 'Approved' AND ev.deleted_at IS NULL AND ev.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)";
        $params = [];
        $types = '';

        if ($branch_id > 0) {
            $where .= " AND e.branch_id = ?";
            $params[] = $branch_id;
            $types .= 'i';
        }
        if ($department_id > 0) {
            $where .= " AND e.department_id = ?";
            $params[] = $department_id;
            $types .= 'i';
        }
        if (!empty($date_from)) {
            $where .= " AND ev.approved_date >= ?";
            $params[] = $date_from;
            $types .= 's';
        }
        if (!empty($date_to)) {
            $where .= " AND ev.approved_date <= ?";
            $params[] = $date_to . ' 23:59:59';
            $types .= 's';
        }

        $sql = "SELECT CONCAT(e.last_name, ', ', e.first_name) as employee_name, e.job_title, d.department_name,
                       b.branch_name, et.template_name, ev.total_score, ev.performance_level,
                       ev.evaluation_period_start, ev.evaluation_period_end, ev.approved_date
                FROM evaluations ev
                LEFT JOIN employees e ON ev.employee_id = e.employee_id
                LEFT JOIN branches b ON e.branch_id = b.branch_id
                LEFT JOIN departments d ON e.department_id = d.department_id
                LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
                $where ORDER BY ev.approved_date DESC, e.last_name";

        $stmt = $conn->prepare($sql);
        if (!empty($params))
            $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $i = 1;
        while ($r = $result->fetch_assoc()) {
            $period = '';
            if ($r['evaluation_period_start'] && $r['evaluation_period_end']) {
                $period = date('M Y', strtotime($r['evaluation_period_start'])) . ' - ' . date('M Y', strtotime($r['evaluation_period_end']));
            }
            $rows[] = [
                $i++,
                $r['employee_name'],
                $r['job_title'],
                $r['department_name'] ?? 'N/A',
                $r['branch_name'] ?? 'N/A',
                $r['template_name'] ?? '',
                $period,
                number_format($r['total_score'], 1),
                $r['performance_level'] ?? 'N/A',
                $r['approved_date'] ? date('M d, Y', strtotime($r['approved_date'])) : 'N/A'
            ];
        }
        $stmt->close();
        break;

    default:
        die('Invalid report type.');
}

// ===========================
// EXPORT: CSV
// ===========================
if ($export_type === 'csv') {
    $filename = strtolower(str_replace(' ', '_', $report_title)) . '_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");

    // Report title row
    fputcsv($output, [$report_title . ' - Generated ' . date('M d, Y h:i A')]);
    fputcsv($output, []); // blank row

    // Headers
    fputcsv($output, $headers);

    // Data
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }

    // Summary
    fputcsv($output, []);
    fputcsv($output, ['Total Records: ' . count($rows)]);

    fclose($output);
    exit;
}

// ===========================
// EXPORT: PDF (FPDF)
// ===========================
if ($export_type === 'pdf') {
    require_once '../includes/plugins/fpdf/fpdf.php';

    // Custom PDF class with header/footer
    class ReportPDF extends FPDF
    {
        public $reportTitle = '';
        public $generatedDate = '';

        function Header()
        {
            // Logo
            $logoPath = dirname(__DIR__) . '/assets/img/logo/logo.png';
            if (file_exists($logoPath)) {
                $this->Image($logoPath, 10, 6, 15);
            }
            // Company
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 7, 'Raquel Pawnshop', 0, 1, 'C');
            $this->SetFont('Arial', '', 9);
            $this->Cell(0, 5, 'Human Resource Information System', 0, 1, 'C');
            $this->Ln(2);
            // Report title
            $this->SetFont('Arial', 'B', 12);
            $this->SetTextColor(41, 67, 6);
            $this->Cell(0, 7, $this->reportTitle, 0, 1, 'C');
            $this->SetTextColor(0);
            $this->SetFont('Arial', '', 8);
            $this->Cell(0, 5, 'Generated: ' . $this->generatedDate, 0, 1, 'C');
            $this->Ln(3);
            // Line
            $this->SetDrawColor(189, 148, 20);
            $this->SetLineWidth(0.5);
            $this->Line(10, $this->GetY(), $this->GetPageWidth() - 10, $this->GetY());
            $this->Ln(4);
        }

        function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(128);
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    // Determine orientation based on column count
    $orientation = count($headers) > 7 ? 'L' : 'P';
    $pdf = new ReportPDF($orientation, 'mm', 'A4');
    $pdf->reportTitle = $report_title;
    $pdf->generatedDate = date('M d, Y h:i A');
    $pdf->AliasNbPages();
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    // Calculate column widths
    $pageWidth = $pdf->GetPageWidth() - 20; // margins
    $colCount = count($headers);
    $colWidth = $pageWidth / $colCount;

    // Flexible widths: give # column less, name columns more
    $colWidths = array_fill(0, $colCount, $colWidth);
    if ($colCount >= 3) {
        $colWidths[0] = 8; // # column
        $remaining = $pageWidth - 8;
        $perCol = $remaining / ($colCount - 1);
        for ($c = 1; $c < $colCount; $c++) {
            $colWidths[$c] = $perCol;
        }
    }

    // Table header
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(41, 67, 6);
    $pdf->SetTextColor(255);
    for ($c = 0; $c < $colCount; $c++) {
        $pdf->Cell($colWidths[$c], 7, $headers[$c], 1, 0, 'C', true);
    }
    $pdf->Ln();

    // Table data
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(0);
    $fill = false;
    foreach ($rows as $row) {
        if ($fill) {
            $pdf->SetFillColor(245, 245, 245);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }

        // Check if we need a new page
        if ($pdf->GetY() + 6 > $pdf->GetPageHeight() - 25) {
            $pdf->AddPage();
            // Re-draw header row
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->SetFillColor(41, 67, 6);
            $pdf->SetTextColor(255);
            for ($c = 0; $c < $colCount; $c++) {
                $pdf->Cell($colWidths[$c], 7, $headers[$c], 1, 0, 'C', true);
            }
            $pdf->Ln();
            $pdf->SetFont('Arial', '', 7);
            $pdf->SetTextColor(0);
        }

        for ($c = 0; $c < $colCount; $c++) {
            $value = isset($row[$c]) ? $row[$c] : '';
            // Truncate long text
            if (mb_strlen($value, 'UTF-8') > 25 && $c !== 0) {
                $value = mb_substr($value, 0, 23, 'UTF-8') . '..';
            }
            $pdf->Cell($colWidths[$c], 6, utf8_decode($value), 1, 0, ($c === 0 ? 'C' : 'L'), true);
        }
        $pdf->Ln();
        $fill = !$fill;
    }

    // Summary
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(41, 67, 6);
    $pdf->Cell(0, 7, 'Total Records: ' . count($rows), 0, 1);

    // Output PDF
    $filename = strtolower(str_replace(' ', '_', $report_title)) . '_' . date('Y-m-d_His') . '.pdf';
    $pdf->Output('D', $filename);
    exit;
}

die('Invalid export type.');
?>
