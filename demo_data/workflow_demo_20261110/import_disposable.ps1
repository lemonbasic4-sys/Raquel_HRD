param([string]$DatabaseName = 'raquel_demo_showcase_20261110')
$ErrorActionPreference = 'Stop'
if ($DatabaseName -notmatch '^raquel_demo_[A-Za-z0-9_]+$') {
    throw 'Choose a disposable database name starting with raquel_demo_. Never use raquel_hris.'
}
$mysql = 'C:\xampp\mysql\bin\mysql.exe'
$php = 'C:\xampp\php\php.exe'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$exists = & $mysql --default-character-set=utf8mb4 -u root --batch --skip-column-names -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='$DatabaseName';"
if ($LASTEXITCODE -ne 0) { throw 'Cannot connect to MySQL. Start MySQL in XAMPP first.' }
if ([int]$exists -ne 0) { throw "Database $DatabaseName already exists. Choose a new disposable name; nothing was imported." }
$copyDirectory = Join-Path $PSScriptRoot "validation\base_copies_$DatabaseName"
[IO.Directory]::CreateDirectory($copyDirectory) | Out-Null
$utf8 = New-Object System.Text.UTF8Encoding($false)
$files = @('1st_schema_tables.sql','2nd_seed_organization.sql','3rd_seed_HR_accounts_.sql')
foreach ($file in $files) {
    $original = Join-Path $projectRoot "database\$file"
    $isolated = [IO.File]::ReadAllText($original).Replace('raquel_hris', $DatabaseName)
    if ($isolated.Contains('raquel_hris')) { throw 'Unsafe database reference remained in validation copy.' }
    $copy = Join-Path $copyDirectory $file
    [IO.File]::WriteAllText($copy, $isolated, $utf8)
    $sqlPath = $copy.Replace('\','/')
    & $mysql --default-character-set=utf8mb4 -u root -e "source $sqlPath;"
    if ($LASTEXITCODE -ne 0) { throw "Import failed: $file. Original seeds and raquel_hris were not changed." }
}
$demoPath = (Join-Path $PSScriptRoot '01_fictional_workflow_demo.sql').Replace('\','/')
& $mysql --default-character-set=utf8mb4 -u root $DatabaseName -e "source $demoPath;"
if ($LASTEXITCODE -ne 0) { throw 'Demo import failed. Review the MySQL error; do not use --force.' }
& $php (Join-Path $PSScriptRoot 'validate_demo.php') $DatabaseName
if ($LASTEXITCODE -ne 0) { throw 'Workflow verification failed.' }
Write-Host "Imported and verified disposable database: $DatabaseName" -ForegroundColor Green

