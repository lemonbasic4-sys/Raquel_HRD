# HRD personnel and President/CEO test seeds

This local-only batch creates three regular HRD employees (`HRD-001`, `HRD-002`, `HRD-003`) with both HRIS and Employee Portal accounts, plus the President/CEO (`OP-001`) with an Employee Portal account. It does **not** import AP employees, probationary employees, or AP templates.

> **Destructive reset:** `database/1st_schema_tables.sql` drops and recreates `raquel_hris`. Back up anything you need first. Never run this batch against production.

With XAMPP MySQL running, paste the complete block into PowerShell:

```powershell
cd C:\xampp\htdocs\raquel_hrd
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS raquel_hris CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run imports in chronological order
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/1st_schema_tables.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/2nd_seed_organization.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/3rd_seed_HR_accounts_.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/xPortal_accounts.sql;"


# Confirm that only the four intended employee records exist
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "SELECT employee_code, employment_status FROM employees ORDER BY employee_code;"
```

Expected employee codes: `HRD-001`, `HRD-002`, `HRD-003`, and `OP-001`. The AP seed files remain available in this folder for separate testing but are deliberately omitted here. The test portal password is `password`. If MySQL root requires a password, add `-p` to each command.
