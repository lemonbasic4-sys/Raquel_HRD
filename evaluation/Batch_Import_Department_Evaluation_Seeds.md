# Department Evaluation Seeds: Batch Import

Start MySQL in XAMPP. Run this from the Raquel_HRD project folder. The department commands import **templates only** into `raquel_hris`; they do not create evaluations, ratings, or approvals. The separate prerequisite command also creates sample employees and portal accounts.

## Prerequisite: probationary Initial and Final

The shared Main Office probationary seed creates two probationary employees per department, their portal accounts, and their Initial/Final forms. **Run it only on a fresh setup** after the organization, HR account, and executive account seeds. If those `MO26-` employees or forms already exist, skip this command. It rejects duplicates.

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source main_office_probationary/seed_main_office_probationary.sql"
```

## Import all 12 departments

Copy and paste this block into PowerShell **after the prerequisite exists**. Each line imports one SQL file. If a command reports an error, PowerShell stops this block; check the message before continuing. Do not rerun successfully imported seeds; each has a duplicate guard.

```powershell
cd C:\xampp\htdocs\Raquel_HRD

# Acquired Properties
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Acquired_Properties/seed_ap_department_quarterly_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Acquired_Properties/seed_ap_department_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }

# Audit
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Audit/seed_audit_department_quarterly_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Audit/seed_audit_department_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }

# Business Development
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Business_Development/seed_business_development_quarterly_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Business_Development/seed_business_development_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }

# Compliance
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Compliance/seed_compliance_quarterly_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Compliance/seed_compliance_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }

# Finance
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Finance/seed_finance_quarterly_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Finance/seed_finance_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }

# General Services
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/General_Services/seed_general_services_quarterly_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/General_Services/seed_general_services_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }

# Human Resources
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Human_Resources/seed_human_resources_quarterly_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Human_Resources/seed_human_resources_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }

# Information Technology
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Information_Technology/seed_information_technology_quarterly_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Information_Technology/seed_information_technology_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }

# Marketing
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Marketing/seed_marketing_quarterly_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Marketing/seed_marketing_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }

# Office of the President
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Office_of_the_President/seed_office_of_the_president_quarterly_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Office_of_the_President/seed_office_of_the_president_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }

# Operations
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Operations/seed_operations_quarterly_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Operations/seed_operations_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }

# Purchasing
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Purchasing/seed_purchasing_quarterly_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Purchasing/seed_purchasing_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw "Import stopped. Check the MySQL error above." }
```

Quarterly base period: **2026-11-15 to 2027-02-14**. Annual base period: **2026-01-01 to 2026-12-31**. Department guides explain role coverage and later 2027 post-probation forms. This guide does not import into the live database.
