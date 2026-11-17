param([Parameter(Mandatory=$true)][string]$TestDataDirectory, [int]$Port=13379)
$ErrorActionPreference = 'Stop'
$mysql = 'C:\xampp\mysql\bin\mysql.exe'
$connection = @('--no-defaults','--host=127.0.0.1',"--port=$Port",'-u','root','--default-character-set=utf8mb4','--abort-source-on-error','--raw','-N','-B')
function Query([string]$Sql) {
    if ($Sql -notmatch '^(source |SELECT @@datadir)') { $Sql = "USE raquel_hris; $Sql" }
    $output = & $mysql @connection -e $Sql 2>&1
    if ($LASTEXITCODE -ne 0) { throw ($output -join "`n") }
    return ($output -join "`n")
}
$expected = [IO.Path]::GetFullPath($TestDataDirectory).TrimEnd('\','/').Replace('\','/')
$actual = (Query 'SELECT @@datadir').Trim().TrimEnd('\','/').Replace('\','/')
if ($Port -eq 3306 -or $actual -ne $expected -or $actual -notlike '*/.tmp-department-tests/data') { throw 'Refusing tests outside isolated .tmp-department-tests/data on a non-production port.' }
function Import([string]$Path) { [void](Query "source $Path") }
function Reset-Fixture {
    foreach ($file in @('1st_schema_tables.sql','2nd_seed_organization.sql','3rd_seed_HR_accounts_.sql','4th_seed_executive_portal_accounts.sql')) { Import "database/$file" }
}
function Assert-Equal($Expected,$Actual,[string]$Message) { if ("$Expected" -ne "$Actual") { throw "$Message : expected $Expected, got $Actual" } }
function Assert-NoNewAccounts {
    Assert-Equal 4 (Query 'SELECT COUNT(*) FROM users') 'Department imports must not create ANY accounts'
    Assert-Equal 0 (Query "SELECT COUNT(*) FROM users WHERE role='Employee'") 'No Employee Portal accounts by default'
}
function Import-Department($folder) {
    $path = "evaluation/$($folder.Name)"
    Import "$path/seed_employees.sql"
    Import "$path/seed_initial_final.sql"
    foreach ($kind in @('quarterly','annual')) {
        $file = Get-ChildItem $folder.FullName -Filter "*_${kind}_dry_run.sql"
        Import "$path/$($file.Name)"
    }
}
function Assert-Forms {
    Assert-Equal 0 (Query 'SELECT COUNT(*) FROM evaluations') 'No evaluation assignments'
    Assert-Equal 0 (Query "SELECT COUNT(*) FROM evaluation_templates t WHERE (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND section='KRA')<>8 OR (SELECT SUM(weight) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND section='KRA')<>100 OR (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND section='Behavior')<>8") 'Complete criteria'
    Assert-Equal 0 (Query 'SELECT COUNT(*) FROM evaluation_templates t LEFT JOIN evaluation_seed_template_owners o ON o.form_code=t.form_code WHERE o.form_code IS NULL') 'Every form remembers its intended reviewer'
}
$folders = @(Get-ChildItem evaluation -Directory | Sort-Object Name)
foreach ($folder in $folders) {
    Reset-Fixture
    Import-Department $folder
    $before = Query 'SELECT COUNT(*) FROM employees'
    Import "evaluation/$($folder.Name)/seed_employees.sql"
    Assert-Equal $before (Query 'SELECT COUNT(*) FROM employees') 'Matching employee reuse'
    Assert-NoNewAccounts
    Assert-Equal 4 (Query 'SELECT COUNT(DISTINCT evaluation_type) FROM evaluation_templates') 'Four evaluation types'
    Assert-Equal 1 (Query 'SELECT COUNT(DISTINCT target_department_id) FROM evaluation_templates') 'Only selected department forms'
    Assert-Equal 2 (Query "SELECT COUNT(*) FROM employees WHERE employment_status='Probationary'") 'Two probationary samples'
    Assert-Forms
    $ErrorActionPreference = 'Continue'
    $duplicate = & $mysql @connection -e "source evaluation/$($folder.Name)/seed_initial_final.sql" 2>&1
    $duplicateExit = $LASTEXITCODE
    $ErrorActionPreference = 'Stop'
    if ($duplicateExit -eq 0) { throw 'Duplicate Initial/Final import should fail' }
    Write-Host "PASS account-free independent import: $($folder.Name)"
}
Reset-Fixture
$folders = @($folders | Sort-Object @{Expression={if ($_.Name -eq 'Purchasing') {0} elseif ($_.Name -eq 'Information_Technology') {1} else {2}}},Name)
foreach ($folder in $folders) { Import-Department $folder }
Assert-NoNewAccounts
Assert-Forms
Assert-Equal 12 (Query 'SELECT COUNT(DISTINCT target_department_id) FROM evaluation_templates') 'All departments coexist'
Assert-Equal 24 (Query "SELECT COUNT(*) FROM employees WHERE employment_status='Probationary'") 'All probationary samples'
Write-Host 'PASS combined account-free import with dependency reuse'
& C:\xampp\php\php.exe scripts/test-seeded-account-binding.php $Port $TestDataDirectory
if ($LASTEXITCODE -ne 0) { throw 'Admin account binding test failed' }
$credentialsBefore = Query "SELECT GROUP_CONCAT(CONCAT(user_id,':',password_hash) ORDER BY user_id SEPARATOR '|') FROM users"
foreach ($folder in $folders) { Import "evaluation/$($folder.Name)/seed_employees.sql" }
Assert-Equal $credentialsBefore (Query "SELECT GROUP_CONCAT(CONCAT(user_id,':',password_hash) ORDER BY user_id SEPARATOR '|') FROM users") 'Employee reimport leaves all accounts/passwords untouched'
Write-Host 'PASS existing Admin-created accounts preserved'
Reset-Fixture
[void](Query "INSERT INTO employees (employee_code,first_name,last_name,hire_date) VALUES ('MO26-AP-H','Conflicting','Person','2020-01-01')")
$before = Query 'SELECT COUNT(*) FROM employees'
$ErrorActionPreference = 'Continue'
$failure = & $mysql @connection -e 'source evaluation/Acquired_Properties/seed_employees.sql' 2>&1
$conflictExit = $LASTEXITCODE
$ErrorActionPreference = 'Stop'
if ($conflictExit -eq 0) { throw 'Conflicting employee must fail' }
Assert-Equal $before (Query 'SELECT COUNT(*) FROM employees') 'Conflicting employee import leaves existing people untouched'
Assert-NoNewAccounts
Write-Host 'PASS conflicting employee import aborts safely'
