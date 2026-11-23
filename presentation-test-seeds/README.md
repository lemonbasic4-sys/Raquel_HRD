# Raquel HRMS Presentation Test Seeds

## Scope

This folder contains completed **Phase 1** employee/profile seeds, **Phase 2** Initial/Final/Annual evaluation seeds, and **Phase 3** career-movement seeds. Quarterly is intentionally excluded because it is temporarily disabled. No application code or schema is modified.

The completed seed set adds 45 fictional employees across all 12 active departments:

- 21 regular employees (12 department anchors plus 9 eligible Annual peers)
- 24 probationary employees (two per active department)
- 45 Employee Portal accounts
- 1 additional HR Staff account for the new regular HR employee
- Complete data for all 12 profile-completion sections used by the employee profile screen
- 9 additional Regular peer employees required for same-department Annual 360 eligibility
- Active reviewer accounts for the existing CEO and AP executive employees

The employee master records for Elena Delgado (`HRD-001`), Patricia Gomez (`HRD-002`), and Miguel Torres (`HRD-003`) are not overwritten. Phase 2 now adds evaluation-only presentation history for them so every seed-controlled employee except the President/CEO has an evaluation entry.

## Prerequisites

- XAMPP MariaDB is running.
- Project root is `C:\xampp\htdocs\Raquel_HRD`.
- Database is `raquel_hris`.
- The four existing initialization seeds have already been imported in their documented order.
- Take a database backup or use a disposable database first.
- Run commands from the project root so nested `SOURCE` paths resolve correctly.

Do **not** rerun `database/1st_schema_tables.sql` against a populated database. It drops and recreates `raquel_hris`.

## Development-only credentials

All new presentation accounts use this initial password:

```text
DemoHRMS!2026
```

All accounts have `must_change_password = 1`. Employee Portal usernames are derived from the employee code, for example:

```text
DEMO-IT-P01 -> demo.it-p01
DEMO-HR-REG -> demo.hr-reg
```

The additional HR Portal account for Tristan Dela Cruz is:

```text
Username: demo.hr.tristan
Role: HR Staff
```

These credentials are for local development and presentation use only.

## Pre-import validation and protected baseline

Run the employee validation first:

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/04-validation/validate_employees.sql"
if ($LASTEXITCODE -ne 0) { throw "Phase 1 preflight validation failed." }
```

Before importing:

1. Save the `PROTECTED_EMPLOYEE_BASELINE`, `PROTECTED_USER_BASELINE`, and `PROTECTED_RELATED_COUNTS` output.
2. Confirm `CODE_COLLISION_STOP` returns no data rows.
3. Confirm the reported database is the exact target supplied with `-D` (`raquel_hris` for the intended live import, or the named disposable database for testing).
4. If any presentation code belongs to another person, stop. Do not import.

The plan spells Patricia as “Patrica” in one place; the actual protected database record is **Patricia Gomez**, employee ID `301`, code `HRD-002`.

## Phase 1 import

Run each command separately. The checks halt the pasted block when MySQL returns an error.

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/01-employee-seeds/01_employee_profiles.sql"
if ($LASTEXITCODE -ne 0) { throw "01_employee_profiles.sql failed; stop and inspect the error." }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/01-employee-seeds/02_educational_background.sql"
if ($LASTEXITCODE -ne 0) { throw "02_educational_background.sql failed; stop and inspect the error." }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/01-employee-seeds/03_employment_history.sql"
if ($LASTEXITCODE -ne 0) { throw "03_employment_history.sql failed; stop and inspect the error." }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/01-employee-seeds/04_employee_accounts.sql"
if ($LASTEXITCODE -ne 0) { throw "04_employee_accounts.sql failed; stop and inspect the error." }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/01-employee-seeds/05_complete_profile_sections.sql"
if ($LASTEXITCODE -ne 0) { throw "05_complete_profile_sections.sql failed; stop and inspect the error." }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/01-employee-seeds/06_regular_peer_profiles.sql"
if ($LASTEXITCODE -ne 0) { throw "06_regular_peer_profiles.sql failed; stop and inspect the error." }
```

If files 01–04 were already imported and employee profiles show 33%, run only file 05, then rerun validation. It is additive and targets only `DEMO-*` employees.

## Post-import validation

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/04-validation/validate_all_seeds.sql"
if ($LASTEXITCODE -ne 0) { throw "Phase 1 validation failed." }
```

Expected headline results:

| Check | Expected |
|---|---:|
| Presentation employees | 45 |
| Regular presentation employees | 21 |
| Probationary presentation employees | 24 |
| New probationary employees per active department | at least 2 |
| Employee Portal accounts | 45 |
| New regular HR roles | Employee + HR Staff |
| Complete regularization histories | 21 |
| Employees with education | 45 |
| Employees without college | at least 1 |
| Employees with 12/12 profile sections | 45 |
| Presentation evaluations | 0 in Phase 1 |
| Presentation career movements | 0 in Phase 1 |

## Phase 2 import — Initial, Final, and Annual only

Quarterly seeds are deliberately omitted. Run in this order:

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/02-evaluation-seeds/05_probationary_kra_templates.sql"
if ($LASTEXITCODE -ne 0) { throw "05_probationary_kra_templates.sql failed." }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/02-evaluation-seeds/06_regular_annual_kra_templates.sql"
if ($LASTEXITCODE -ne 0) { throw "06_regular_annual_kra_templates.sql failed." }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/02-evaluation-seeds/07_evaluation_cycles.sql"
if ($LASTEXITCODE -ne 0) { throw "07_evaluation_cycles.sql failed." }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/02-evaluation-seeds/08_self_evaluations.sql"
if ($LASTEXITCODE -ne 0) { throw "08_self_evaluations.sql failed." }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/02-evaluation-seeds/09_peer_360_evaluations.sql"
if ($LASTEXITCODE -ne 0) { throw "09_peer_360_evaluations.sql failed." }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/02-evaluation-seeds/10_supervisor_evaluations.sql"
if ($LASTEXITCODE -ne 0) { throw "10_supervisor_evaluations.sql failed." }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/02-evaluation-seeds/11_evaluation_approvals.sql"
if ($LASTEXITCODE -ne 0) { throw "11_evaluation_approvals.sql failed." }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/02-evaluation-seeds/12_evaluation_results.sql"
if ($LASTEXITCODE -ne 0) { throw "12_evaluation_results.sql failed." }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/02-evaluation-seeds/13_annual_2026_demo_assignments.sql"
if ($LASTEXITCODE -ne 0) { throw "13_annual_2026_demo_assignments.sql failed." }
```

Phase 2 expected results:

| Record group | Expected |
|---|---:|
| Initial templates | 12 with 12 KRAs each |
| Final templates | 12 with 12 KRAs each |
| Annual templates | 21 with 15 KRAs each |
| Initial evaluations | 49: 37 approved, 12 pending |
| Final evaluations | 25 approved |
| Historical Annual evaluations/cycles/versions | 130 approved |
| 2026 Annual demonstration assignments | 21 Pending Self-Rating |
| Required/submitted historical Annual 360 ratings | 107 / 107 |
| Quarterly presentation records | 0 |

Compare the protected employee-master result sets with the saved pre-import output. Evaluation-only rows are expected for `AP-001` and `HRD-001/002/003`; changes to their employee master fields remain a stop condition.

## Phase 3 import - chronological order

File 13 validates existing Phase 1 regularization history and intentionally creates no duplicate request. Transfers use `career_movements`; promotions use the system's `succession_endorsements` workflow.

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/03-career-movement-seeds/13_regularization_history.sql"
if ($LASTEXITCODE -ne 0) { throw "13_regularization_history.sql failed." }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/03-career-movement-seeds/14_branch_transfers.sql"
if ($LASTEXITCODE -ne 0) { throw "14_branch_transfers.sql failed." }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/03-career-movement-seeds/15_department_transfers.sql"
if ($LASTEXITCODE -ne 0) { throw "15_department_transfers.sql failed." }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/03-career-movement-seeds/16_promotions.sql"
if ($LASTEXITCODE -ne 0) { throw "16_promotions.sql failed." }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/04-validation/validate_career_movements.sql"
if ($LASTEXITCODE -ne 0) { throw "Phase 3 validation failed." }
```

Expected Phase 3 totals: 6 transfers (4 approved/applied, 1 pending, 1 rejected) and 4 promotion endorsements (2 approved/applied, 1 pending HR review, 1 rejected). Every validation `failures` value must be `0`.

## Phase 4 final analytics-readiness validation

Phase 4 is read-only. It derives workforce, evaluation, regularization, transfer, and promotion summaries from the underlying seeded records and checks duplicates, dates, assignments, and broken references.

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/04-validation/validate_analytics_readiness.sql"
if ($LASTEXITCODE -ne 0) { throw "Phase 4 analytics-readiness validation failed." }
```

To execute every completed validation in dependency order:

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -D raquel_hris -e "source presentation-test-seeds/04-validation/validate_all_seeds.sql"
if ($LASTEXITCODE -ne 0) { throw "Complete presentation-seed validation failed." }
```

Every Phase 4 result labeled `failures` must be `0`. Evaluation coverage includes the 45 `DEMO-*` employees plus evaluation-only history for `AP-001` and the three HR portal personnel. `CEO-001` remains excluded. Expected evaluation totals are 229 records: 204 completed or probationary-workflow records plus 25 open 2026 Annual assignments.

## Safe re-import behavior

The seed files use deterministic `DEMO-*` identities and `NOT EXISTS` guards. They do not use `REPLACE`, `DELETE`, or `TRUNCATE`. Re-running a completed phase should add zero duplicates.

Always rerun preflight validation first. A matching presentation code with a different first or last name is a collision and must be investigated; the seed must not be used to overwrite it.

## Troubleshooting

- `Unknown database`: verify that the existing initialization process created `raquel_hris`.
- Foreign-key error: verify the organization seed and executive employee seed are already present, especially `CEO-001` and `AP-001`.
- Missing manager: verify the expected protected/executive employee codes and run files in order.
- Duplicate username/email: run `validate_employees.sql` and resolve the collision without changing existing accounts.
- PowerShell redirection error: use the documented `-e "source ..."` form; do not use `<` in PowerShell 5.1.
- A successful SQL import does not prove the browser login flow. Login and UI testing must be reported separately.
- Empty performance aggregates usually mean Phase 2 evaluations are absent from the selected database; run the Phase 2 validation before assuming an analytics-query defect.

## Schema limitation

The current schema has no dedicated general employment-status-history table. For each new regular employee, the seed uses two `employee_work_experience` rows at Raquel Pawnshop—`Probationary` followed by `Regular`—and keeps `employees.regularization_effective_date` consistent. No evaluation or `regularization_requests` rows are created in Phase 1.
