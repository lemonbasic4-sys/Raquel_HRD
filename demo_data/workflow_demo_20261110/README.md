# Fictional workforce and evaluation workflow demo

Reference date: **10 November 2026**. Everything added by this fixture is fictional. The SQL is for a new disposable database, never the working `raquel_hris` database.

## Files

| File | Purpose |
| --- | --- |
| `01_fictional_workflow_demo.sql` | The single importable dataset, including the exact runtime cycle table definitions absent from the base schema |
| `02_verify_demo.sql` | SQL checks, counts, employee timelines, template counts and report summaries |
| `import_disposable.ps1` | PowerShell batch import into a new, separately named database |
| `manifest.json` | Every demo employee, username/code, ID, position, department and hire/regularization date |
| `SOURCE_REVIEW.md` | Schema/workflow decisions, inspection of all 12 department fixtures and source hashes |
| `VALIDATION.md` | Results and limits of the actual disposable-database validation |
| `validation/` | Raw import log, verification output, PHP workflow output and unchanged base-row snapshots |
| `build_demo.py` | Reproduces the SQL using the Python standard library; does not connect to MySQL |
| `validate_demo.py` | Imports rewritten in-memory copies of base seeds and this file into a new isolated validation DB |
| `validate_demo.php` | Calls the real application calculators, route builder, authority checks and analytics/history readers |

## What is included

| Dataset | Count |
| --- | ---: |
| New fictional employees | 55 |
| New employees with complete probation-to-regular evaluation histories | 54 |
| Reviewer-only CEO exception | 1 |
| Existing base HR employees preserved | 3 |
| Total active employees after import | 58 |
| Departments | 12 |
| Active branches represented | 7 |
| Position-specific templates | 214 |
| KRA and behavior criteria | 4,784 |
| Initial evaluations | 54 |
| Final evaluations | 54 |
| Quarterly evaluations | 171 |
| Annual evaluations | 57 |
| Completed Annual department cycles | 12 |
| Completed Quarterly department cycles | 36 |

Every applicable template has **13 KRAs**, or **18 for manager/executive positions**, and the application's exact **eight standard Core Behaviors & Values**. The KRA weights sum to 100 within the section; the master weights are 80% KRA and 20% behavior. Behavior criterion weights remain zero, as in the current template wizard; behavior ratings are averaged rather than multiplied by item weights.

The new employee cohort includes the actual seeded reporting chains, including Audit Manager II between Audit Manager I and the Audit Supervisor; the Operations VP above the IT Manager; and the GS Manager above Procurement. Multiple employees can share Purchasing Staff I or Business Development Staff I without inventing a new position.

The branch spread is Main Office (32 new employees), Calamba Branch 1 (5), Candelaria Branch 1 (4), Pagbilao (4), Lipa Branch 1 (4), Lucban Branch 1 (3), and Atimonan Branch 1 (3). The three original HR employees remain at Main Office. Branch and department differences affect the sample scores; all four supported performance categories occur in completed regular evaluations.

## Prerequisites and import order

Use XAMPP's **MariaDB 10.4 or later**, its MySQL command-line client, and a user allowed to create routines and tables. Validation used MariaDB 10.4.32 with the local XAMPP root account. This file uses MariaDB's `ADD COLUMN IF NOT EXISTS`; it is not presented as a portable MySQL 8 script.

The required order is:

1. `database/1st_schema_tables.sql`
2. `database/2nd_seed_organization.sql`
3. `database/3rd_seed_HR_accounts_.sql`
4. `01_fictional_workflow_demo.sql`
5. `02_verify_demo.sql`, then `validate_demo.php`

**Use rewritten copies of the first three files for a disposable database.** The original first schema contains `DROP DATABASE raquel_hris` and all three hardcode `USE raquel_hris`. The provided importer replaces that database token only in new copies and refuses an existing target database. It never edits the originals or selects `raquel_hris`.

Do not import the old department seeds, `analytics_demo_seed.sql`, or another fixture before this file. It intentionally requires the fresh base state: three HR employees, four base users, and no evaluations/templates. An attempted second import is rejected rather than replacing data.

## PowerShell batch import

Start Apache and MySQL in XAMPP, then run:

```powershell
cd C:\xampp\htdocs\Raquel_HRD

& .\demo_data\workflow_demo_20261110\import_disposable.ps1 `
    -DatabaseName 'raquel_demo_showcase_20261110'
```

Choose another `raquel_demo_...` name if that database already exists. No existing database is dropped. The script imports the three isolated base copies, imports the demo file, and runs the PHP workflow checks. It leaves the new disposable database available for inspection.

For a database that **already has only the three isolated base imports**, import just the new SQL:

```powershell
$demoDb = 'raquel_demo_showcase_20261110'
& 'C:\xampp\mysql\bin\mysql.exe' --default-character-set=utf8mb4 -u root $demoDb `
    -e 'source demo_data/workflow_demo_20261110/01_fictional_workflow_demo.sql;'
if ($LASTEXITCODE -ne 0) { throw 'Demo import failed' }

& 'C:\xampp\mysql\bin\mysql.exe' --default-character-set=utf8mb4 -u root $demoDb `
    -e 'source demo_data/workflow_demo_20261110/02_verify_demo.sql;'
if ($LASTEXITCODE -ne 0) { throw 'SQL verification failed' }

& 'C:\xampp\php\php.exe' .\demo_data\workflow_demo_20261110\validate_demo.php $demoDb
if ($LASTEXITCODE -ne 0) { throw 'Workflow verification failed' }
```

For SQL verification, every `invalid_rows` value and `TOTAL_INVALID_ROWS` must be zero. The PHP checker fails with a nonzero exit code if an application calculation, reporting reader, approval relationship or route check disagrees.

## Reading the demonstration in the application

Importing a separate database does **not** change which database your normal application uses. No `config/database.php` or application file was changed for this task. To browse the fixture, use a separate demo application instance connected to the disposable database. The task's validation directly called the PHP readers against the isolated DB; it did not switch the existing site to that DB.

All newly added demo accounts use the password **`password`**. Original admin and HR account passwords remain unchanged.

| Example | Username | Portal |
| --- | --- | --- |
| Flora Alcantara, HR Manager II | `D26-07-031.hris` | HRIS login; Manager Analytics and employee profiles |
| Flora's employee account | `D26-07-031` | Employee Portal; her own evaluation history |
| Sofia Balagtas, Technical Support Staff I | `D26-08-038` | Employee Portal; complete IT example |
| Isadora Velasco, President and CEO | `D26-10-001` | Employee Portal; reviewer/cycle-owner access |

The manifest lists all other usernames. Non-HR employees use their demo Company ID as their portal username. New HR personnel have separate employee and HRIS accounts, and HRIS access dates begin only after regularization. Additional `D26-REF-HRD-...` accounts provide historically dated demo actors for the existing three HR personas. Their original employee and user rows are preserved byte for byte.

Useful pages in that separate demo instance:

- HRIS Manager: `manager/analytics.php`; select the performance tabs to see finalized results. Hiring Analytics needs the **2024** hire-year filter because this complete-progression cohort was hired before 2025. A zero 2026 hiring count is intentional.
- HRIS Manager: `manager/view-employee.php?id=910038` for Sofia's profile, regularization audit entry and performance history.
- Sofia's own portal: `employee/evaluation-history.php`; the latest Quarterly detail is `employee/evaluation-history-view.php?evaluation_id=940319`.
- Department cycle histories: the existing Annual and Quarterly history links. All seeded cycles are completed, so the fixture does not create unfinished rating tasks.

## IT Staff walkthrough

**Sofia Balagtas — D26-08-038 — Technical Support Staff I** reports to Elisa Agoncillo, IT Supervisor I. That supervisor reports to Victor Manansala, IT Manager I; the IT Manager reports to the Operations VP, as configured in the organization seed.

| Event | Date/period | Result |
| --- | --- | --- |
| Probationary hire | 7 March 2023 | Complete fictional profile and employee account |
| Initial evaluation | Hire through 7 June 2023; approved 12 June | 3.01 / 4.00; individual package and signed head review |
| Final evaluation | Hire through 7 August 2023; approved 12 August | 3.22 / 4.00; Recommend regularization |
| Regularization effective | 12 August 2023 | Employee field, linked Final evaluation and profile audit record |
| Annual 2025 | 1 January–31 December 2025; head approved 8 January 2026 | 3.19 / 4.00; cycle owner approved 10 January |
| Quarterly Q1 2026 | 1 January–31 March; head approved 8 April | 3.17 / 4.00 |
| Quarterly Q2 2026 | 1 April–30 June; head approved 8 July | 3.19 / 4.00 |
| Quarterly Q3 2026 | 1 July–30 September; head approved 8 October | 3.35 / 4.00; cycle owner approved 10 October |

Initial/Final contain individual behavior reviews. Regular cycles contain self-rating once, all department coworker ratings, immediate-head KRA checks, development plans, and a complete finalized snapshot. The snapshot and stored totals were compared with the actual PHP calculators.

## Scope and honest limitations

- This is a synthetic reconstruction of completed workflow state, not evidence that a person logged in, signed, consented or approved anything. Every signature PNG reads **DEMO ONLY**. Audit text identifies the records as demo history.
- The **CEO has no supported immediate head or self-evaluation route** in the current application. The CEO is the one reviewer-only exception, with no invented Initial/Final, regularization or personal Quarterly/Annual record. The three executive assistants participate in Office of the President cycles.
- The current source can decide an existing `regularization_requests` row but has **no request-creation path**. The two new HR Staff request rows are clearly labeled historical compatibility examples. They do not establish that an end-to-end request submission was performed. For other positions, regularization is represented by the approved Final recommendation, linked Final result, employee effective date and audit history; an extra HR Manager decision is not fabricated.
- The three base HR employees are references from the required seed, not newly added progression cases. Their preexisting incomplete or inconsistent profile details and unknown historic regularization dates are not rewritten. Their added periodic results use their current authoritative position IDs; all **new** profiles pass coverage and position checks.
- No real personal photographs are supplied. Photograph fields are NULL so the existing logo fallback works. Optional name suffix, absent spouse/children for single employees, unowned real property and absent debt remain absent rather than being filled with invented placeholders. Mandatory/applicable profile sections are populated.
- Peer tables have no narrative-feedback field. Varying peer observations appear in the existing audit log; numeric peer ratings remain in their normal tables and snapshots.
- Only selected completed periods are shown: Annual **2025** and Quarterly **Q1–Q3 2026**. This is not an exhaustive evaluation archive for every year since hire. There is no future-completed 2026 Annual or Q4 cycle.
- Browser rendering, PDF exports, interactive submissions and actual notification delivery were not exercised. Database imports, the actual PHP route builder/calculators, current roster readiness, cycle completion, employee history and analytics filtering were exercised in the disposable database.

