# HR probationary evaluation test seeds

This batch creates a **fresh test database** with exactly nine HRD employees: the three regular starters (Elena Delgado, Patricia Gomez, and Miguel Torres), each with an HRIS login **and** an Employee portal login, plus six fictional probationary employees (two HR Staff, two HR Supervisors, and two HR Managers), each with an Employee portal login. It does **not** create evaluation forms or submissions; create those through the application to test eligibility and routing.

> **Destructive reset:** `database/1st_schema_tables.sql` starts with `DROP DATABASE IF EXISTS raquel_hris`. Export or back up anything you need first, and run this only against a disposable test database. Never run this batch against production.

With XAMPP MySQL running, paste these commands into PowerShell:

```powershell
cd C:\xampp\htdocs\raquel_hrd
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS raquel_hris CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run imports in chronological order
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/1st_schema_tables.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/2nd_seed_organization.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/3rd_seed_HR_accounts_.sql;"
# This supplies the three HRIS logins; the preceding file only supplies their employee records.
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/xPortal_accounts.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/test_hrd_portal_accounts.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/01_probationary_hr_employees.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/02_employee_portal_accounts.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/03_verify_hr_probationary.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/hr_personnel_templates.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/hr_final_probationary_evaluations.sql;"
Write-Host "Evaluation test database reset and seeded successfully!" -ForegroundColor Green

```

If your MySQL root account requires a password, add `-p` to each MySQL command; MySQL will prompt for it. Do not put the password in this file. The final file only reads the resulting rows; it should show three regular HRD employees with two accounts each, six probationary HRD employees with one portal account each, and nine HRD employees total. Import `xPortal_accounts.sql` **before** the HRD portal-account seed because it deletes existing Employee-role accounts for the three starters.

| Position | Portal usernames | Target rank |
| --- | --- | --- |
| HR Staff I / II | `EVAL-HR-S01`, `EVAL-HR-S02` | 5 |
| HR Supervisor I / II | `EVAL-HR-V01`, `EVAL-HR-V02` | 4 |
| HR Manager I / II | `EVAL-HR-M01`, `EVAL-HR-M02` | 3 |

All six test portal accounts use the password `password`. They are deliberately test-only accounts; change or remove them before using a non-test environment. Staff report to seeded HR Supervisor Patricia Gomez (employee 301), supervisors report to seeded HR Manager Elena Delgado (employee 101), and managers have no `reports_to` because the three starter files do not establish a President/CEO supervisor for them.

For the visibility test, create a probationary evaluation template targeting Human Resources and the intended rank, then check Self Rating with one account at the matching level and one at a different level. A form created before an employee was added should still become visible when its current targeting rules match that employee. These SQL files do not seed a President/CEO creator account; testing that creator path requires a separately configured authorized account.
