<?php
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';

function parseDelimitedList(?string $value, string $delimiter = '||'): array
{
    if ($value === null || $value === '') {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode($delimiter, $value)), static function ($item) {
        return $item !== '';
    }));
}

$conn->query("
    CREATE TABLE IF NOT EXISTS organization_structure_entries (
        entry_id INT AUTO_INCREMENT PRIMARY KEY,
        division_name VARCHAR(150) NOT NULL DEFAULT 'Operation Management',
        region_name VARCHAR(150) NOT NULL,
        area_name VARCHAR(150) NULL,
        branch_no VARCHAR(30) NULL,
        branch_id INT NOT NULL,
        area_supervisor_employee_id INT NULL,
        regional_manager_employee_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_org_structure_branch
            FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE CASCADE,
        CONSTRAINT fk_org_structure_area_supervisor
            FOREIGN KEY (area_supervisor_employee_id) REFERENCES employees(employee_id) ON DELETE SET NULL,
        CONSTRAINT fk_org_structure_regional_manager
            FOREIGN KEY (regional_manager_employee_id) REFERENCES employees(employee_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$entriesResult = $conn->query("
    SELECT ose.*,
           CONCAT(ae.first_name, ' ', ae.last_name) AS area_supervisor_name,
           CONCAT(re.first_name, ' ', re.last_name) AS regional_manager_name,
           GROUP_CONCAT(DISTINCT b.branch_name ORDER BY b.branch_name SEPARATOR '||') AS branch_names,
           GROUP_CONCAT(DISTINCT COALESCE(b.location, '') ORDER BY b.branch_name SEPARATOR '||') AS branch_locations
    FROM organization_structure_entries ose
    LEFT JOIN organization_structure_entry_branches osb ON ose.entry_id = osb.entry_id
    LEFT JOIN branches b ON osb.branch_id = b.branch_id
    LEFT JOIN employees ae ON ose.area_supervisor_employee_id = ae.employee_id
    LEFT JOIN employees re ON ose.regional_manager_employee_id = re.employee_id
    GROUP BY ose.entry_id
    ORDER BY ose.division_name, ose.region_name, ose.area_name
");

$entries = $entriesResult ? $entriesResult->fetch_all(MYSQLI_ASSOC) : [];
$grouped = [];
foreach ($entries as $entry) {
    $division = $entry['division_name'] ?: 'Operation Management';
    if (!isset($grouped[$division])) {
        $grouped[$division] = [];
    }
    $grouped[$division][] = $entry;
}
$sys_logo = getSetting($conn, 'system_logo', 'assets/img/logo/logo.png');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Organization Structure Form</title>
    <!-- Browser Tab Icon (Favicon) -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL . '/' . htmlspecialchars($sys_logo); ?>">
    <link rel="shortcut icon" href="<?php echo BASE_URL . '/' . htmlspecialchars($sys_logo); ?>">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            font-size: 11px;
            background: #e0e0e0;
            padding: 20px;
            color: #000;
        }

        .page {
            background: #fff;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 30px auto;
            padding: 12mm 15mm;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
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

        .header-box {
            border: 1px solid #000;
            display: flex;
            margin-bottom: 10px;
        }

        .header-logo {
            width: 155px;
            border-right: 1px solid #000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4px;
        }

        .header-main {
            flex: 1;
        }

        .header-main-title {
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            padding: 4px 0 2px;
        }

        .meta-table,
        .org-table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-table td,
        .org-table th,
        .org-table td {
            border: 1px solid #000;
            padding: 4px 6px;
        }

        .meta-table td {
            font-size: 10px;
        }

        .org-table th {
            background: #fff;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            text-align: center;
        }

        .org-table td {
            font-size: 10px;
            vertical-align: top;
        }

        .division-title {
            margin: 12px 0 6px;
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
            border: 1px solid #000;
            border-bottom: none;
            padding: 5px 8px;
        }

        .empty-box {
            border: 1px solid #000;
            padding: 20px;
            text-align: center;
            font-style: italic;
        }

        .notes-box {
            border: 1px solid #000;
            border-top: none;
            padding: 10px 8px;
            font-size: 10px;
        }

        @media print {
            body {
                background: none;
                padding: 0;
            }

            .page {
                box-shadow: none;
                margin: 0;
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

    <div class="page">
        <div class="header-box">
            <div class="header-logo">
                <img src="https://raquelpawnshop.com/wp-content/uploads/2023/05/png-logo.png"
                    style="max-width:140px; max-height:55px; object-fit:contain;" alt="Logo">
            </div>
            <div class="header-main">
                <div class="header-main-title">ORGANIZATION STRUCTURE FORM</div>
                <table class="meta-table">
                    <tr>
                        <td style="width:18%; font-weight:bold;">Document</td>
                        <td style="width:32%;">Organization Structure Listing</td>
                        <td style="width:18%; font-weight:bold;">Printed Date</td>
                        <td><?php echo date('F d, Y'); ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:bold;">Prepared By</td>
                        <td><?php echo e($_SESSION['full_name'] ?? 'HR Manager'); ?></td>
                        <td style="font-weight:bold;">Department</td>
                        <td>Human Resources</td>
                    </tr>
                </table>
            </div>
        </div>

        <?php if (empty($grouped)): ?>
            <div class="empty-box">No organization structure rows have been added yet.</div>
        <?php else: ?>
            <?php foreach ($grouped as $division => $rows): ?>
                <div class="division-title"><?php echo e($division); ?></div>
                <table class="org-table">
                    <thead>
                        <tr>
                            <th style="width:14%;">Region</th>
                            <th style="width:18%;">Area Supervisor</th>
                            <th style="width:18%;">Regional Manager</th>
                            <th style="width:14%;">Area</th>
                            <th style="width:36%;">Branches</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                                $rowBranchNames = parseDelimitedList($row['branch_names'] ?? '');
                                $rowBranchLocations = parseDelimitedList($row['branch_locations'] ?? '');
                                $rowBranchLabels = [];
                                foreach ($rowBranchNames as $index => $branchName) {
                                    $branchLabel = $branchName;
                                    if (!empty($rowBranchLocations[$index])) {
                                        $branchLabel .= ' - ' . $rowBranchLocations[$index];
                                    }
                                    $rowBranchLabels[] = $branchLabel;
                                }
                            ?>
                            <tr>
                                <td><?php echo e($row['region_name']); ?></td>
                                <td><?php echo e($row['area_supervisor_name'] ?: ''); ?></td>
                                <td><?php echo e($row['regional_manager_name'] ?: ''); ?></td>
                                <td><?php echo e($row['area_name'] ?: ''); ?></td>
                                <td>
                                    <?php if (!empty($rowBranchLabels)): ?>
                                        <?php foreach ($rowBranchLabels as $index => $branchLabel): ?>
                                            <div style="font-weight:bold;">
                                                <?php echo e(($index + 1) . '. ' . $branchLabel); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="notes-box">
                    Total Rows for <?php echo e($division); ?>: <strong><?php echo count($rows); ?></strong>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
