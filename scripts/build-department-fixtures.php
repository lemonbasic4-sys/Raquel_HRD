<?php
// Regenerate standalone department fixtures from the legacy fictional roster.
// Run: C:\xampp\php\php.exe scripts/build-department-fixtures.php
$root = dirname(__DIR__);
$legacy = file_get_contents("$root/main_office_probationary/seed_main_office_probationary.sql");
preg_match_all("/^  \('MO26-[^\r\n]+/m", $legacy, $matches);
$people = [];
foreach ($matches[0] as $row) {
    $values = str_getcsv(trim($row, " \r\n,;()"), ',', "'", '\\');
    $people[$values[0]] = $values;
}
$departments = [
    'Acquired_Properties' => [1, 'AP', 'ap_department'],
    'Audit' => [2, 'AU', 'audit_department'],
    'Business_Development' => [3, 'BD', 'business_development'],
    'Compliance' => [4, 'CO', 'compliance'],
    'Finance' => [5, 'FI', 'finance'],
    'General_Services' => [6, 'GS', 'general_services'],
    'Human_Resources' => [7, 'HR', 'human_resources'],
    'Information_Technology' => [8, 'IT', 'information_technology'],
    'Marketing' => [9, 'MK', 'marketing'],
    'Office_of_the_President' => [10, 'EX', 'office_of_the_president'],
    'Operations' => [11, 'OP', 'operations'],
    'Purchasing' => [12, 'PU', 'purchasing'],
];
$insertStart = strpos($legacy, '  INSERT INTO employees (');
$insertEnd = strpos($legacy, '  INSERT INTO users (', $insertStart);
$insert = substr($legacy, $insertStart, $insertEnd - $insertStart);
$insert = str_replace('FROM mo26_people p JOIN job_titles j ON j.job_title_id=p.job_title_id',
    'FROM mo26_people p JOIN mo26_new n ON n.employee_code=p.employee_code JOIN job_titles j ON j.job_title_id=p.job_title_id', $insert);
$insert = str_replace('UPDATE employees e JOIN mo26_people p', 'UPDATE employees e JOIN mo26_new n ON n.employee_code=e.employee_code JOIN mo26_people p', $insert);
$insert = str_replace('FROM mo26_people p JOIN employees e ON e.employee_code=p.employee_code;',
    'FROM mo26_people p JOIN mo26_new n ON n.employee_code=p.employee_code JOIN employees e ON e.employee_code=p.employee_code;', $insert);
$templateStart = strpos($legacy, '  -- Two position-specific forms');
$templateEnd = strpos($legacy, '  IF (SELECT COUNT(*) FROM employees e JOIN mo26_people', $templateStart);
$template = substr($legacy, $templateStart, $templateEnd - $templateStart);
// All lookups are limited to this department, including criteria insertion.
$template = str_replace("WHERE form_code LIKE 'MO26-%'", "WHERE target_department_id=__DEPT__ AND form_code LIKE 'MO26-%'", $template);
$template = str_replace("WHERE t.form_code LIKE 'MO26-%'", "WHERE t.target_department_id=__DEPT__ AND t.form_code LIKE 'MO26-%'", $template);
$template = str_replace('JOIN users u ON u.employee_id=h.employee_id',
    "LEFT JOIN users u ON u.user_id=(SELECT MIN(u2.user_id) FROM users u2 WHERE u2.employee_id=h.employee_id AND u2.is_active=1 AND u2.deleted_at IS NULL AND u2.account_hold=0)", $template);
$ownerTable = <<<'SQL'
-- Fixture ownership is recorded without creating any user accounts.
CREATE TABLE IF NOT EXISTS evaluation_seed_template_owners (
  form_code VARCHAR(50) PRIMARY KEY,
  employee_id INT NOT NULL,
  preferred_role VARCHAR(30) NOT NULL,
  FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
$batch = [];
foreach ($departments as $folder => [$id, $prefix, $stem]) {
    $label = str_replace('_', ' ', $folder);
    $selected = array_filter($people, fn($p) => str_starts_with($p[0], "MO26-$prefix-"));
    $primary = $people["MO26-$prefix-P"];
    $regular = $primary;
    $regular[0] = "MO26-$prefix-R";
    $regular[1] = ['AP'=>'Luis','AU'=>'Mabel','BD'=>'Enzo','CO'=>'Rhea','FI'=>'Diego','GS'=>'Noel','HR'=>'Tessa','IT'=>'Ivan','MK'=>'Hazel','EX'=>'Clara','OP'=>'Simon','PU'=>'Maya'][$prefix];
    $regular[2] = 'Solis';
    $regular[5] = 'Regular';
    $regular[6] = '2020-01-06';
    $selected[$regular[0]] = $regular;
    do {
        $added = false;
        foreach ($selected as $person) {
            if (isset($people[$person[4]]) && !isset($selected[$person[4]])) {
                $selected[$person[4]] = $people[$person[4]];
                $added = true;
            }
        }
    } while ($added);
    $rows = implode(",\n", array_map(function ($p) {
        return '  (' . implode(',', array_map(fn($v, $i) => $i === 3 ? $v : "'" . str_replace("'", "''", $v) . "'", $p, array_keys($p))) . ')';
    }, array_values($selected)));
    $proc = 'seed_' . strtolower($folder) . '_employees';
    $sql = <<<SQL
-- $label fictional Main Office employees and required reporting heads. No user accounts.
-- Prerequisites: database seeds 1-4. No templates, ratings or approvals.
-- Matching existing fixture people are reused; conflicts abort. Existing users are untouched.
SET NAMES utf8mb4;
USE raquel_hris;
$ownerTable
DROP PROCEDURE IF EXISTS $proc;
DELIMITER //
CREATE PROCEDURE $proc()
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    DROP TEMPORARY TABLE IF EXISTS mo26_people,mo26_new;
    RESIGNAL;
  END;
  IF NOT EXISTS (SELECT 1 FROM branches WHERE branch_id=102 AND is_active=1)
     OR NOT EXISTS (SELECT 1 FROM employees WHERE employee_code='CEO-001' AND job_title_id=1100 AND branch_id=102 AND is_active=1 AND deleted_at IS NULL)
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Import base database seeds 1-4 first'; END IF;
  CREATE TEMPORARY TABLE mo26_people (
    employee_code VARCHAR(30) PRIMARY KEY,first_name VARCHAR(100),last_name VARCHAR(100),
    job_title_id INT,head_code VARCHAR(30),status_name VARCHAR(20),hire_date DATE
  );
  INSERT INTO mo26_people VALUES
$rows;
  IF EXISTS (SELECT 1 FROM mo26_people p LEFT JOIN job_titles j ON j.job_title_id=p.job_title_id AND j.is_active=1 WHERE j.job_title_id IS NULL)
     OR EXISTS (SELECT 1 FROM mo26_people p JOIN job_titles j ON j.job_title_id=p.job_title_id LEFT JOIN mo26_people h ON h.employee_code=p.head_code LEFT JOIN employees e ON e.employee_code=p.head_code AND e.is_active=1 AND e.deleted_at IS NULL WHERE NOT (j.reports_to <=> COALESCE(h.job_title_id,e.job_title_id)))
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Missing position or required reporting head'; END IF;
  START TRANSACTION;
  IF EXISTS (SELECT 1 FROM employees e JOIN mo26_people p ON p.employee_code=e.employee_code JOIN job_titles j ON j.job_title_id=p.job_title_id
    WHERE NOT (e.job_title_id <=> p.job_title_id) OR e.first_name<>p.first_name OR e.last_name<>p.last_name
      OR NOT (e.department_id <=> j.department_id) OR NOT (e.rank_category_id <=> j.rank_category_id)
      OR NOT (e.branch_id <=> 102) OR e.employment_status<>p.status_name OR e.hire_date<>p.hire_date OR NOT (e.is_active <=> 1) OR e.deleted_at IS NOT NULL)
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Existing MO26 employee conflicts with fictional fixture; nothing replaced'; END IF;
  CREATE TEMPORARY TABLE mo26_new AS SELECT p.employee_code FROM mo26_people p
    WHERE NOT EXISTS (SELECT 1 FROM employees e WHERE e.employee_code=p.employee_code);
$insert
  IF EXISTS (SELECT 1 FROM employees e JOIN mo26_people p ON p.employee_code=e.employee_code
    JOIN employees h ON h.employee_code=p.head_code WHERE NOT (e.reports_to <=> h.employee_id))
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Existing fixture reporting line differs; nothing overwritten'; END IF;
SQL;
    if ($id === 7) {
        $sql .= <<<'SQL'

  -- Only supply missing regularization dates for the three documented demo HR employees.
  UPDATE employees SET regularization_effective_date=DATE_ADD(hire_date,INTERVAL 6 MONTH)
    WHERE employee_code IN ('HRD-001','HRD-002','HRD-003') AND employment_status='Regular' AND regularization_effective_date IS NULL;
  UPDATE employees e JOIN employees ceo ON ceo.employee_code='CEO-001'
    SET e.reports_to=ceo.employee_id WHERE e.employee_code='HRD-001' AND e.reports_to IS NULL;
SQL;
    }
    $sql .= <<<SQL

  COMMIT;
  SELECT e.employee_code,e.first_name,e.last_name,e.employment_status,j.job_title,h.employee_code AS immediate_head,
    IF(j.department_id=$id,'Selected department','Required external head') AS fixture_scope
  FROM mo26_people p JOIN employees e ON e.employee_code=p.employee_code JOIN job_titles j ON j.job_title_id=e.job_title_id
    LEFT JOIN employees h ON h.employee_id=e.reports_to ORDER BY fixture_scope,e.employee_code;
  DROP TEMPORARY TABLE mo26_people,mo26_new;
END //
DELIMITER ;
CALL $proc();
DROP PROCEDURE $proc;
SQL;
    file_put_contents("$root/evaluation/$folder/seed_employees.sql", $sql . "\n");
    $probRows = implode(",\n", array_map(function ($p) {
        return "  ('$p[0]','$p[4]',$p[3])";
    }, array_values(array_filter($selected, fn($p) => $p[5] === 'Probationary'))));
    $probTemplate = str_replace('__DEPT__', (string)$id, $template);
    // Template SQL uses the same scoped three-column roster as the legacy joins.
    $probTemplate = str_replace("WHERE p.status_name='Probationary'", '', $probTemplate);
    $proc = 'seed_' . strtolower($folder) . '_initial_final';
    $prob = <<<SQL
-- $label: one shared Initial and one Final form for the two sample probationary employees.
-- Run seed_employees.sql first. Templates only; repeat import deliberately fails.
SET NAMES utf8mb4;
USE raquel_hris;
DROP PROCEDURE IF EXISTS $proc;
DELIMITER //
CREATE PROCEDURE $proc()
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN
    ROLLBACK; DROP TEMPORARY TABLE IF EXISTS mo26_people; RESIGNAL;
  END;
  IF EXISTS (SELECT 1 FROM evaluation_templates WHERE target_department_id=$id AND form_code IN ('MO26-$primary[3]-INITIAL','MO26-$primary[3]-FINAL'))
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Department Initial/Final forms already exist; do not import twice'; END IF;
  CREATE TEMPORARY TABLE mo26_people (employee_code VARCHAR(30),head_code VARCHAR(30),job_title_id INT);
  INSERT INTO mo26_people VALUES
$probRows;
  IF (SELECT COUNT(*) FROM mo26_people p JOIN employees e ON e.employee_code=p.employee_code
       JOIN employees h ON h.employee_code=p.head_code AND e.reports_to=h.employee_id
       WHERE e.job_title_id=p.job_title_id AND e.employment_status='Probationary' AND e.is_active=1 AND e.deleted_at IS NULL
         AND h.is_active=1 AND h.deleted_at IS NULL)<>2
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Import department fictional employees first'; END IF;
  START TRANSACTION;
$probTemplate
  IF (SELECT COUNT(*) FROM evaluation_templates WHERE target_department_id=$id AND form_code IN ('MO26-$primary[3]-INITIAL','MO26-$primary[3]-FINAL'))<>2
     OR EXISTS (SELECT 1 FROM evaluation_templates t WHERE t.target_department_id=$id AND t.form_code IN ('MO26-$primary[3]-INITIAL','MO26-$primary[3]-FINAL')
       AND ((SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>8
         OR (SELECT SUM(c.weight) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>100
         OR (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='Behavior')<>8))
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Initial/Final criteria validation failed'; END IF;
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT DISTINCT t.form_code,h.employee_id,IF(h.employee_code='CEO-001','President and CEO','Employee')
  FROM evaluation_templates t JOIN mo26_people p ON p.job_title_id=t.target_job_title_id
    JOIN employees h ON h.employee_code=p.head_code
  WHERE t.target_department_id=$id AND t.form_code IN ('MO26-$primary[3]-INITIAL','MO26-$primary[3]-FINAL')
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  COMMIT;
  DROP TEMPORARY TABLE mo26_people;
END //
DELIMITER ;
CALL $proc();
DROP PROCEDURE $proc;
SQL;
    file_put_contents("$root/evaluation/$folder/seed_initial_final.sql", $prob . "\n");
    foreach (['quarterly','annual'] as $kind) {
        $path = "$root/evaluation/$folder/seed_{$stem}_{$kind}_dry_run.sql";
        $source = file_get_contents($path);
        $source = preg_replace('/\s*-- BEGIN SEEDED OWNERS.*?-- END SEEDED OWNERS\s*/s', "\n", $source);
        $events = [];
        preg_match_all('/SELECT\s+MIN\(u\.user_id\)\s+INTO\s+(v_\w+)\s+FROM\s+users u JOIN employees e.*?;/si', $source, $queries, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($queries as $q) {
            if (!preg_match("/(?:e\\.employee_code|u\\.username)\\s*=\\s*'([^']+)'/", $q[0][0], $code)) {
                throw new RuntimeException("Cannot determine fixture owner: $path / {$q[1][0]}");
            }
            preg_match("/u\\.role\\s*=\\s*'([^']+)'/", $q[0][0], $role);
            $events[] = [$q[0][1], 'owner', $q[1][0], $code[1], $role[1] ?? 'Employee'];
        }
        preg_match_all("/'([^']+)'\\s*,\\s*'\\d{4}-\\d{2}-\\d{2}'\\s*,\\s*'\\d{4}-\\d{2}-\\d{2}'\\s*,\\s*'Active'\\s*,\\s*(v_\\w+)/", $source, $forms, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($forms as $f) { $events[] = [$f[0][1], 'form', $f[2][0], $f[1][0]]; }
        usort($events, fn($a,$b) => $a[0] <=> $b[0]);
        $owners = [];
        $registration = '';
        foreach ($events as $event) {
            if ($event[1] === 'owner') { $owners[$event[2]] = [$event[3],$event[4]]; continue; }
            if (!isset($owners[$event[2]])) { throw new RuntimeException("Missing owner for $path / $event[3]"); }
            [$ownerCode,$ownerRole] = $owners[$event[2]];
            $registration .= "  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)\n  SELECT '$event[3]',employee_id,'$ownerRole' FROM employees WHERE employee_code='$ownerCode'\n  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);\n";
        }
        if (!$registration) { throw new RuntimeException("No templates found: $path"); }
        // Account existence is a runtime requirement, never an import prerequisite.
        $source = preg_replace("/\\bIF\\s+v_\\w+\\s+IS NULL(?:\\s+OR\\s+v_\\w+\\s+IS NULL)*\\s+THEN\\s+SIGNAL\\s+SQLSTATE\\s+'45000'\\s+SET\\s+MESSAGE_TEXT\\s*=\\s*'[^']*';\\s*END IF;/i", '', $source);
        $source = preg_replace("/\\bIF NOT EXISTS \\(SELECT 1 FROM employees e JOIN users u.*?THEN\\s+SIGNAL SQLSTATE '45000'.*?END IF;/s", '', $source);
        $source = preg_replace("/AND u\\.username\\s*=\\s*'[^']+'\\s*/", '', $source);
        $bind = <<<'SQL'
  UPDATE evaluation_templates t JOIN evaluation_seed_template_owners o ON o.form_code=t.form_code
  SET t.created_by=(SELECT MIN(u.user_id) FROM users u WHERE u.employee_id=o.employee_id
    AND (u.role=o.preferred_role OR (o.preferred_role='Employee' AND u.role IN ('HR Manager','HR Supervisor','HR Staff')))
    AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0)
  WHERE t.created_by IS NULL;
SQL;
        $source = str_replace('COMMIT;', "\n  -- BEGIN SEEDED OWNERS\n$registration$bind\n  -- END SEEDED OWNERS\n  COMMIT;", $source);
        $source = str_replace('employees, and portal accounts are seeded.', 'employees are seeded. Portal accounts are created manually later.', $source);
        $source = str_replace('Run after organization, employee, and portal-account seeds.', 'Run after organization and employee seeds; no portal accounts needed for import.', $source);
        file_put_contents($path, preg_replace('/[ \t]+(?=\r?$)/m', '', $source));
    }
    $files = ['seed_employees.sql','seed_initial_final.sql',"seed_{$stem}_quarterly_dry_run.sql","seed_{$stem}_annual_dry_run.sql"];
    $commands = "cd C:\\xampp\\htdocs\\raquel_hrd\n";
    foreach ($files as $file) {
        $commands .= "\nC:\\xampp\\mysql\\bin\\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e \"source evaluation/$folder/$file\"\n";
        $commands .= "if (\$LASTEXITCODE -ne 0) { throw '$label import stopped: $file' }\n";
    }
    $accounts = "| Company ID | Fictional employee | Status |\n| --- | --- | --- |\n";
    foreach ($selected as $p) {
        $accounts .= "| `$p[0]` | $p[1] $p[2] | $p[5]" . (!str_starts_with($p[0], "MO26-$prefix-") ? ' (required external head)' : '') . " |\n";
    }
    $extra = match ($id) {
        1 => 'Also uses the base AP VP `AP-001` and CEO `CEO-001` employee records.',
        7 => 'Uses the base HR demo employee records. Missing regularization dates are filled using hire date + six months; the base HR Manager is linked to the CEO if no head is set. Existing HRIS accounts remain unchanged.',
        default => 'Also uses the base CEO `CEO-001`.',
    };
    $readme = <<<MD
# $label: fictional employees and evaluation forms

Use this folder to import **this department's mock employee information and Initial, Final, Quarterly, and Annual evaluation forms**. Required external reporting heads are included automatically. **No user accounts are created.** You do not need the all-department `main_office_probationary` seed.

## Prerequisites (once per database)

Start MySQL in XAMPP. The existing database must already have base seeds **1-4**: schema, organization, HR demo accounts, and executive employee records. Follow [base setup](../README.md) only if they have not been imported. The department commands below do **not** reset the database. Do not rerun the schema on an existing database.

## Import this department

Paste this block into PowerShell. It imports employee information first, then Initial/Final, Quarterly, and Annual templates, including their criteria. Employee Portal accounts are not required for import. Use `-p` if your MySQL root account has a password.

```powershell
$commands```

Run template imports **once**. If the old all-department seed already supplied this department's Initial/Final forms, skip `seed_initial_final.sql`. Likewise, skip an already imported Quarterly or Annual file. Template duplicates stop with an error; they are not overwritten. Matching employee records and required heads are reused; conflicting identities/reporting lines abort. Existing accounts/passwords are untouched. Each SQL file commits separately; a later failure does not undo earlier files.

## Mock employee information

$accounts
$extra

Each department has two probationary employees, one regular staff sample, and its reporting heads; this does not populate every covered job position with an employee. Information includes names, employment status, position, department, branch, hire/regularization dates, contact details, and immediate-head reporting relationships.

After import, use **Admin > Portal Accounts** to create the employee and reviewer accounts yourself, including any executive Employee Portal accounts you need. You choose the credentials. The seeded forms remember their intended reviewer employee and are linked when that account is created through Admin. Forms can be imported before accounts; self-rating/review still requires the appropriate active accounts.

## Forms and workflow

- `seed_initial_final.sql`: two shared Active forms for the sample probationary position. Each has 8 KRAs (total 100%) and 8 Core Behaviors, with an 80% KRA / 20% Behavior split.
- Quarterly and Annual SQL files: role-specific templates and criteria for this department. See the [detailed department guide]() for role coverage and any later 2027 post-probation forms.
- No ratings, submissions, approvals, or automatic regularization are imported.
- Probationary employee: Employee Portal > Self Rating > Initial. Final follows full Initial approval. The sample Initial due dates are January 1 and January 15, 2027.
- Regular staff sample (`MO26-$prefix-R`) and eligible regular heads can use Quarterly. Annual requires an Approved Quarterly evaluation in the same year. The Annual period begins on the later of January 1 or the employee's regularization date.

Base Quarterly period: **November 15, 2026-February 14, 2027**. Base Annual period: **January 1-December 31, 2026**. Importing a form does not bypass employee eligibility or approval rules.

To import every department instead, use the separate [compiled all-department import](../Batch_Import_Department_Evaluation_Seeds.md).
MD;
    $guide = basename(glob("$root/evaluation/$folder/*_Evaluation_Dry_Run.md")[0]);
    $guidePath = "$root/evaluation/$folder/$guide";
    $oldGuide = file_get_contents($guidePath);
    $quarterlyAt = strpos($oldGuide, '## Quarterly');
    $details = substr($oldGuide, $quarterlyAt);
    $details = str_replace('It also assigns Adrian Reyes one blank **Pending Self-Rating** evaluation. The current live database already has the Quarterly forms; this seed stops if they already exist.',
        'This imports templates only. To optionally assign Adrian Reyes one blank **Pending Self-Rating** evaluation afterward, import `seed_ap_supervisor_quarterly_evaluation.sql` separately. The Quarterly seed stops if its forms already exist.', $details);
    $details = preg_replace('/^.*(?:has Employee Portal accounts|have Employee Portal accounts|It has Employee Portal accounts).*$/m', 'Create all required Employee Portal accounts manually in Admin > Portal Accounts after import.', $details);
    $details = str_replace('--default-character-set=utf8mb4 -u root', '--default-character-set=utf8mb4 --abort-source-on-error -u root', $details);
    $guideIntro = "# $label Evaluation Dry Run\n\nFor mock employee information and all four form types, use [README.md](README.md). Import base seeds 1-4 once, then this folder's import block. No user accounts are created or required for import. Create employee/reviewer accounts manually using Admin > Portal Accounts afterward.\n\n## Probationary Initial and Final\n\nThe employee seed supplies `MO26-$prefix-P`, `MO26-$prefix-P2`, their reporting heads, and regular staff sample `MO26-$prefix-R`. Shared forms `MO26-$primary[3]-INITIAL` and `MO26-$primary[3]-FINAL` each have 8 KRAs and 8 Core Behaviors. Initial due dates are January 1 and January 15, 2027. Once accounts exist, start at Employee Portal > Self Rating > Initial. Final follows full Initial approval; regular Quarterly follows Final approval and regularization. No scores, approvals, or regularization are seeded.\n\n";
    file_put_contents($guidePath, $guideIntro . $details);
    $readme = str_replace('[detailed department guide]()', "[detailed department guide]($guide)", $readme);
    file_put_contents("$root/evaluation/$folder/README.md", $readme . "\n");
    $batch[] = "## $label\n\n```powershell\n$commands```\n";
}
$compiled = "# Compiled import: all 12 departments\n\nRun the four base seeds in [batch_import_sql.md](../batch_import_sql.md) once first. This is the optional compiled version of every department README. Copy the single PowerShell block below to import **all departments' mock employee information and Initial, Final, Quarterly, and Annual evaluation forms**. No user accounts, ratings, approvals, or automatic regularization are created. Required external heads are reused. Template duplicates stop the import; each SQL file commits separately.\n\nFor one department at a time, use that department's README instead, starting with [Acquired Properties](Acquired_Properties/README.md). Do not run this entire compiled block after already importing some departments; skip their completed template files.\n\n";
foreach ($departments as $folder => $meta) { $compiled .= '- [' . str_replace('_',' ',$folder) . "]($folder/README.md)\n"; }
$compiled .= "\n```powershell\n" . implode("\n", array_map(function ($block) { preg_match('/```powershell\n(.*?)```/s', $block, $m); return $m[1]; }, $batch)) . "```\n\nAfter import, create the employee/reviewer portal accounts manually using Admin > Portal Accounts. Existing HRIS accounts remain unchanged.\n";
file_put_contents("$root/evaluation/Batch_Import_Department_Evaluation_Seeds.md", $compiled);
file_put_contents("$root/evaluation/README.md", $compiled);
echo "Generated 12 department employee seeds, Initial/Final seeds, READMEs, and batch guide.\n";
