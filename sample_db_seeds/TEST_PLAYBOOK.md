# Evaluation-Flow Test Seeds & Playbook

### Option B — Chronological Modular Reset & Seed Batch Script:
Run this directly in PowerShell to reset and load from individual schema and seed modules:

```powershell
# Set working directory to project root
cd C:\xampp\htdocs\raquel_hrd

# WARNING: This drops the local raquel_hris database and recreates it.
# Back up anything you need before running the reset.
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "DROP DATABASE IF EXISTS raquel_hris; CREATE DATABASE raquel_hris CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Run imports in chronological order
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/1st_schema_tables.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/2nd_seed_organization.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/3rd_seed_HR_accounts_.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source sample_db_seeds/01_test_employees.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/xPortal_accounts.sql;"
#& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/data/seed_templates.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source sample_db_seeds/02_test_hrd_portal_accounts.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source sample_db_seeds/04_test_probationary_employees.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/zLAST_performance_indexes.sql;"

# Run the isolated route/scoring regression test (creates and drops only a uniquely named test DB)
& "C:\xampp\php\php.exe" sample_db_seeds/test_probationary_workflow.php

Write-Host "Database reset and seeded successfully!" -ForegroundColor Green
```
