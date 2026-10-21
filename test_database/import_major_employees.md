# Complete department employee test database

A clean Regular-employee baseline for department evaluation testing: **41 employees across all 12 departments**, **41 Employee Portal accounts**, four additional HR/executive work accounts, and one Admin account.

This is an alternative to the employee imports in `eval_test_seeds/testplaybook.md`, not an additional layer on top of them. No application database was reimported when these files were created.

## Import into a disposable local database

**Back up first. `database/1st_schema_tables.sql` DROPS and recreates `raquel_hris`, including all current employees, evaluations, accounts, and history.** Its database name is hard-coded; merely selecting a different database in phpMyAdmin will not protect `raquel_hris`.

Use this sequence only when you intentionally want a fresh local test installation. Stop on any error. Do not use MySQL's `--force` option.

From PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD

# DESTRUCTIVE: intentionally resets raquel_hris. Back up before running.
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "source database/1st_schema_tables.sql;"
if ($LASTEXITCODE -ne 0) { throw "Schema import failed" }

& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/2nd_seed_organization.sql;"
if ($LASTEXITCODE -ne 0) { throw "Organization import failed" }

& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source test_database/major_employees_complete_profiles.sql;"
if ($LASTEXITCODE -ne 0) { throw "Employee import failed" }
```

Alternatively, import those same three files, in order, through phpMyAdmin after accepting the reset warning.

Do **not** mix in `3rd_seed_HR_accounts_.sql`, older department employee seeds, probationary-profile seeds, `brainrot_mockup_employees.sql`, or the old account-creation script for this baseline. They introduce different employee IDs/duplicate position holders and are unnecessary here.

The new employee SQL has no hard-coded database selection, does not delete existing employees, and can be rerun to add missing profile rows without resetting existing values, passwords, or evaluation history. However, combining it with a populated database is not a clean baseline: unrelated employees remain and affect department peer counts. Fixed ID/code collisions fail rather than replace existing people. If an import fails, disconnect/roll back the transaction before retrying; do not continue blindly.

Changing this seed does not rename employees already imported under the old `TDB-...` codes. Use the new codes on your next intentional fresh test import; do not rerun the updated seed over the old roster without a migration.

## Login

All accounts use the local test password: **`password`**. Never use this seed or password in production.

Every employee's **Employee Portal username equals their employee code**, shown below. Accounts are active, not held, and first-login setup is already completed. HR personnel and the CEO each have an Employee account as well as their separate work-role account.

| Work role | Username |
| --- | --- |
| Admin | `admin` |
| HR Manager | `elena.delgado` |
| HR Supervisor | `patricia.gomez` |
| HR Staff | `miguel.torres` |
| President and CEO | `isabella.cruz` |

Work-role accounts use `firstname.lastname`; Employee Portal accounts across every department retain their department codes (for example, `HRD-001` and `AP-001`). Other departments use their Employee Portal accounts for evaluation work; no unsupported HRIS roles are created. The standalone Admin username is `admin`.

The Admin account is standalone (`employee_id = NULL`): it has no employee profile and does not participate in department evaluations. Its password is also `password`.

These usernames apply to a fresh import. Rerunning the seed preserves existing accounts and will not rename previously imported `HRD-001-HRIS` or other work usernames.

### Employee Portal roster

| Department | Employee | Position | Portal username | Immediate head username |
| --- | --- | --- | --- | --- |
| Acquired Properties | Marisol Santos | VP for Acquired Properties | `AP-001` | `OP-001` |
| Acquired Properties | Adrian Reyes | AP Manager I | `AP-002` | `AP-001` |
| Acquired Properties | Nathan Cruz | AP Supervisor I | `AP-003` | `AP-002` |
| Acquired Properties | Bianca Garcia | AP Staff I | `AP-004` | `AP-003` |
| Audit | Rafael Mendoza | Audit Manager I | `AUD-001` | `OP-001` |
| Audit | Teresa Santos | Audit Manager II | `AUD-002` | `AUD-001` |
| Audit | Carlo Reyes | Audit Supervisor I | `AUD-003` | `AUD-002` |
| Audit | Diana Cruz | Auditor I | `AUD-004` | `AUD-003` |
| Business Development | Ernesto Garcia | Business Development Officer I | `BD-001` | `OP-001` |
| Business Development | Fiona Mendoza | Business Development Staff I | `BD-002` | `BD-001` |
| Compliance | Gabriel Santos | Compliance Supervisor I | `COM-001` | `OP-001` |
| Compliance | Helena Reyes | Compliance Supervisor II | `COM-002` | `COM-001` |
| Compliance | Ivan Cruz | Compliance Staff I | `COM-003` | `COM-002` |
| Finance | Julia Garcia | VP for Finance | `FIN-001` | `OP-001` |
| Finance | Kevin Mendoza | Accounting Manager I | `FIN-002` | `FIN-001` |
| Finance | Lara Santos | Accounting Supervisor I | `FIN-003` | `FIN-002` |
| Finance | Marco Reyes | Treasury Supervisor I | `FIN-004` | `FIN-001` |
| Finance | Nina Cruz | Accounting Staff I | `FIN-005` | `FIN-003` |
| Finance | Oscar Garcia | Treasury Staff I | `FIN-006` | `FIN-004` |
| General Services | Paula Mendoza | VP for General Services | `GS-001` | `OP-001` |
| General Services | Quentin Santos | GS Manager I | `GS-002` | `GS-001` |
| General Services | Rosa Reyes | GS Supervisor I | `GS-003` | `GS-002` |
| General Services | Samuel Cruz | Driver I | `GS-004` | `GS-003` |
| Human Resources | Elena Delgado | HR Manager I | `HRD-001` | `OP-001` |
| Human Resources | Patricia Gomez | HR Supervisor I | `HRD-002` | `HRD-001` |
| Human Resources | Miguel Torres | HR Staff I | `HRD-003` | `HRD-002` |
| Information Technology | Tristan Reyes | IT Manager I | `IT-001` | `OPS-001` |
| Information Technology | Una Cruz | IT Supervisor I | `IT-002` | `IT-001` |
| Information Technology | Victor Garcia | Technical Support Staff I | `IT-003` | `IT-002` |
| Marketing | Wendy Mendoza | Marketing Manager I | `MKT-001` | `OP-001` |
| Marketing | Xavier Santos | Marketing Supervisor I | `MKT-002` | `MKT-001` |
| Marketing | Yasmin Reyes | Marketing Staff I | `MKT-003` | `MKT-002` |
| Office of the President | Isabella Cruz | President and CEO | `OP-001` | None (top executive) |
| Office of the President | Zachary Garcia | Executive Assistant I | `OP-002` | `OP-001` |
| Operations | Andres Mendoza | VP for Operations | `OPS-001` | `OP-001` |
| Operations | Beatrice Santos | Regional Manager I | `OPS-002` | `OPS-001` |
| Operations | Cesar Reyes | Area Coordinator I | `OPS-003` | `OPS-002` |
| Operations | Danica Cruz | Focal Person I | `OPS-004` | `OPS-003` |
| Operations | Emilio Garcia | Branch Staff I | `OPS-005` | `OPS-004` |
| Purchasing | Flora Mendoza | Procurement Supervisor I | `PUR-001` | `GS-002` |
| Purchasing | Gino Santos | Purchasing Staff I | `PUR-002` | `PUR-001` |

Employee-facing IDs and portal usernames use department initials plus a three-digit sequence (for example, `HRD-001` and `AP-001`). Internal numeric database keys remain `20000 + job_title_id` to preserve relational links. All employees are active, Regular, Full-time, based at Main Office (branch 102).

Hire dates vary across **2014–2026**, with 11 hires spread across January–October 2026, including two in October, as of October 21, 2026. Senior reporting levels generally joined earlier than their staff. Mock previous employment ends the day before each hire date. The exact dates are listed in the SQL roster and stay fixed on reruns; they are synthetic dates for analytics, not actual employment histories. Recent employees are deliberately seeded directly as Regular for annual-workflow tests; this does not simulate their probation/regularization process.

### Update an already imported baseline without resetting it

Fresh imports already use the varied dates. If your existing 41 seeded employees still have the old January 6, 2020 hire date, run only:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source test_database/update_seeded_hire_dates.sql;"
```

This updates only exact seeded employee ID/code matches with the old hire date, plus their untouched mock previous-employment dates. Existing custom dates, accounts, statuses, evaluation records, and reporting lines are preserved. Rerunning this update changes zero already-updated rows. Do not reimport the base schema for this change.

`restore_seeded_hire_dates_20261021.sql` is the recovery script for the original dates, captured before this update. It is **not** part of the normal import sequence.

### Refresh demographics on an already imported baseline

Fresh imports have a mix of **21 Male and 20 Female** employees. Educational attainment is **26 College, 8 Graduate Studies, 5 Vocational, and 2 Secondary**. These values are synthetic and assigned explicitly to the mock roster; they do not represent real employee records.

To update the existing 41 seeded employees without resetting evaluations or accounts:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source test_database/update_seeded_demographics.sql;"
```

The update requires exact employee IDs and codes, and changes only untouched seed values. It can be rerun safely. `restore_seeded_demographics_20261021.sql` contains the original values and is for recovery only.

The seed follows the existing position hierarchy, including cross-department heads: IT Manager reports to VP Operations, and Procurement Supervisor reports to GS Manager. Audit Manager II and Compliance Supervisor II are included because existing staff/supervisor chains require them. Departments without a manager position use their configured officer/supervisor; no nonexistent positions are invented. This seeds representative major levels, not every job grade or every branch.

## Complete employee profiles

Each employee has identity/birth/employment details, contact information, physical details and citizenship, residential **and** permanent addresses, an emergency contact, family and sibling information, an educational attainment record, previous employment, training, voluntary work, eligibility, skills, recognition, membership, government-ID placeholders, personal property, liabilities, references, and disclosure answers.

All employees are marked Single, so spouse and children are not applicable; no fictitious dependent is required to complete those profiles. Property and financial information is synthetic. Names, phone numbers, eligibility, IDs, and `.invalid` email addresses are mock data, not valid government registrations or deliverable email addresses. Avatar placeholders are used; uploaded legal documents and signatures are not fabricated.

The 12 profile-completion areas are populated. This does **not** mean evaluations, self-ratings, signatures, or approvals have been submitted.

## Start evaluation testing

1. Sign in using each required Employee Portal account.
2. Confirm the department and immediate head match the roster.
3. Create the appropriate evaluation templates for the positions you want to test. This baseline deliberately contains no templates, evaluations, ratings, approvals, or notifications.
4. Perform self-rating, immediate-head review, and department peer ratings through the application.
5. Use the department's actual roster and configured external immediate head when checking expected 360 counts.

Do not import the older HRD annual template seed without reviewing its hard-coded creator/employee IDs: this baseline uses different IDs. Existing notification logic may generate legitimate reminders after login, but this seed does not create overdue probationary employees or seed notification rows.

## Verify without changing the application database

```powershell
& "C:\xampp\php\php.exe" test_database/verify_seed.php
```

The verifier creates a randomly named `raquel_seed_test_...` database, rewrites the base schema's database target in memory, imports the organization and new seed there, and drops only that generated database afterward. It uses the standard local XAMPP MySQL root account with no password; adjust its connection if your local setup differs.

Checks cover 41 employees, 12 departments, 46 accounts, valid test passwords, all configured heads present, profile coverage for every employee, both address types, no seeded evaluations/notifications, and nonduplicating reruns that preserve existing edits.

