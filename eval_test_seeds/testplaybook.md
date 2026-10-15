# Acquired Properties probationary test seeds

This batch creates a fresh local test database with one President/CEO account, three regular HR personnel with HRIS and Employee portal accounts, and **six probationary Acquired Properties (AP) employees** with Employee portal accounts: two Staff, two Supervisors, and two Managers. It does not create AP evaluation templates or submissions.

> **Destructive reset:** `database/1st_schema_tables.sql` drops and recreates `raquel_hris`. Back up anything you need first. Never run this batch against production.

With XAMPP MySQL running, paste these commands into PowerShell:

```powershell
cd C:\xampp\htdocs\raquel_hrd
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS raquel_hris CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run imports in chronological order
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/1st_schema_tables.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/2nd_seed_organization.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/3rd_seed_HR_accounts_.sql;"
# The next file supplies the three HRIS logins; the preceding file supplies their employee records.
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/xPortal_accounts.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/test_hrd_portal_accounts.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/president_ceo_account.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/01_probationary_hr_employees.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/02_employee_portal_accounts.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/03_verify_hr_probationary.sql;"
```

The `01_probationary_hr_employees.sql` filename is retained for compatibility, but its six records now belong to AP. The HR-only template seed files remain available separately and are intentionally not imported because they do not target AP positions. If MySQL root requires a password, add `-p` to each command. Check every import for errors: these plain commands do not stop automatically. Import `xPortal_accounts.sql` before the HRD portal-account seed because it deletes existing Employee-role accounts for the three HR starters.

| AP position | Employee portal username | Direct report |
| --- | --- | --- |
| AP Staff I | `AP-111` | `AP-333` |
| AP Staff II | `AP-222` | `AP-444` |
| AP Supervisor I | `AP-333` | `AP-555` |
| AP Supervisor II | `AP-444` | `AP-777` |
| AP Manager I | `AP-555` | Not assigned; AP Vice President is not seeded |
| AP Manager II | `AP-777` | Not assigned; AP Vice President is not seeded |

The President/CEO portal account is `OP-001`. All six AP probationary portal accounts and the President/CEO account use the test password `password`. These are test-only credentials. The three regular HR personnel remain in Human Resources; their HRIS and Employee accounts are not converted.

This batch verifies AP employee records, positions, Staff/Supervisor reporting links, and portal access. It is **not yet a complete AP evaluation workflow fixture**: AP templates and an authorized AP Vice President/reviewer account are not seeded. Create those as part of the agreed AP workflow before testing submissions, approvals, and regularization.
