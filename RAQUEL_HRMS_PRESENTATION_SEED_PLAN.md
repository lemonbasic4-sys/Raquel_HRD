# RAQUEL PAWNSHOP HRMS — PRESENTATION DATABASE TEST SEED PLAN

## 1. Objective and strict scope

Create realistic, interconnected **SQL database test seeds only** for the existing Raquel Pawnshop HRMS, for a capstone demonstration. The resulting records should support existing employee portals, HR portals, evaluation histories, career movement views, analytics, and reports **without changing application code or schema**.

Allowed deliverables: SQL seed files, SQL validation queries, Markdown documentation, and copy-paste-ready PowerShell/MySQL CLI import instructions. Do not modify UI, source code, authentication logic, scoring formulas, workflows, database schema, or existing records. Never use real personal information for new mock employees.

## 2. Mandatory preservation of existing HR employees

**These existing HR employees are protected records:**

| Existing employee | Existing position | Department |
|---|---|---|
| Elena Delgado | HR Manager | Human Resources |
| Patrica Gomez | HR Supervisor | Human Resources |
| Miguel Torres | HR Staff | Human Resources |

Before generating or importing seeds, locate and verify their actual employee IDs, account IDs, roles, assignments, and associated records. **Never delete, update, overwrite, reset, replace, or duplicate** their profiles, accounts, credentials, permissions, evaluations, employment histories, or career movements. Do not generate new history for them without explicit approval. They may be referenced as existing employees in new mock employees' reporting relationships or evaluations where the actual schema and rules permit, but their own existing data must remain unchanged.

Record pre-import checks and compare them after every phase. If preservation cannot be guaranteed, **stop and report the issue**. The three protected employees do not count as newly generated mock employees.

## 3. Inspect the existing database first

Review these existing initialization files **without modifying them**:

```text
database/1st_schema_tables.sql
database/2nd_seed_organization.sql
database/3rd_seed_HR_accounts_.sql
database/4th_seed_executive_portal_accounts.sql
```

Identify the actual database name, tables, required fields, unique keys, foreign keys, enums/statuses, and dependencies for departments, branches, employees, education, employment history, roles/accounts, evaluation templates and KRA/behavior items, cycles, assignments, self-ratings, peer ratings, head ratings, approvals, results, and career movements. Read existing application queries as needed to establish what data is required for current screens and analytics, **but do not edit application files**. Do not invent table or column names. If the schema cannot represent a requested record, document the limitation and ask before proceeding; do not create tables or alter columns.

## 4. Dedicated folder

Create the following folder in the project root. Names and order are provisional; adjust to actual schema dependencies.

```text
presentation-test-seeds/
├── README.md
├── DEVELOPMENT_PLAN.md
├── SEED_PROGRESS.md
├── 01-employee-seeds/
│   ├── 01_employee_profiles.sql
│   ├── 02_educational_background.sql
│   ├── 03_employment_history.sql
│   └── 04_employee_accounts.sql
├── 02-evaluation-seeds/
│   ├── 05_probationary_kra_templates.sql
│   ├── 06_regular_kra_templates.sql
│   ├── 07_evaluation_cycles.sql
│   ├── 08_self_evaluations.sql
│   ├── 09_peer_360_evaluations.sql
│   ├── 10_supervisor_evaluations.sql
│   ├── 11_evaluation_approvals.sql
│   └── 12_evaluation_results.sql
├── 03-career-movement-seeds/
│   ├── 13_regularization_history.sql
│   ├── 14_branch_transfers.sql
│   ├── 15_department_transfers.sql
│   └── 16_promotions.sql
└── 04-validation/
    ├── validate_employees.sql
    ├── validate_evaluations.sql
    ├── validate_career_movements.sql
    └── validate_all_seeds.sql
```

All seed data must reference the existing database and existing organizational records. Use deterministic synthetic identifiers or safe lookups to avoid duplicate imports. Preserve pre-existing data. Keep the SQL statements compatible with the project's actual MySQL/MariaDB version.

## 5. Phase 1 — Employee database seeds (START HERE)

**Work on Phase 1 only until explicitly approved.**

### Department and employee coverage

- Identify **all active departments and branches** from the database.
- Every active department must receive mock employees, including **at least two newly seeded probationary employees per department**.
- Add realistic regular employees, staff, supervisors, managers, and HR personnel as appropriate to existing roles and department structure. Do not assume all departments have identical staffing.
- Use department-appropriate positions and realistic reporting relationships.
- Preserve Elena Delgado, Patrica Gomez, and Miguel Torres exactly as they are; do not regenerate them.

### Complete employee profiles

Populate all applicable existing profile fields, such as unique employee ID, name, birth date, contact information, fictional address, emergency contact, hire date, branch, department, position, classification, employment status, supervisor, and other required columns. Do not leave required fields blank or fill them with meaningless placeholders. Optional fields can remain null when genuinely inapplicable and permitted by the schema. All newly created identities must be fictional.

### Educational background

Provide each employee's education history from elementary through their actual highest attainment: junior high, senior high where applicable to the person's timeline, and college **if attended**. Include schools, programs, dates, and completion statuses where supported. Include **at least one employee without a college degree** (e.g., high-school graduate or college undergraduate); do not fabricate college completion. Keep dates and job qualifications realistic.

### Probationary-to-regular history

For **every newly seeded regular employee**, include prior probationary employment and subsequent regularization in the existing employment-history structures, with consistent hire/start/completion/effective dates and status changes. Their current status must remain Regular. **Do not create evaluation records yet**; Phase 2 will add historical evaluations linked to these periods. Do not add or alter histories of the three protected HR employees.

### Accounts and portals

Every new mock employee must have valid database account and role associations for the existing Employee Portal. New mock HR personnel must have both Employee Portal and authorized HR Portal access under the existing model; supervisors/managers must have correct existing permissions. Use existing account/password-hash conventions, not a new authentication system. Provide development-only test credentials in README, without exposing or changing the three protected employees' credentials. Database rows should support login, but do not claim working login unless actually tested.

### Phase 1 validation

Provide SQL checks for department coverage, >=2 **new** probationary employees per active department, required profile fields, education, at least one non-college-degree employee, regularization history, unique employee/account IDs, valid references, portal role mappings, and unchanged protected HR records. Report expected and actual row counts separately; only claim actual imports/tests if performed.

**STOP AFTER PHASE 1. Report files, counts, validation results, issues, and wait for explicit approval.**

## 6. Phase 2 — Performance evaluation database seeds

**Begin only after Phase 1 approval.**

### Department-specific templates

Every department must have distinct role-appropriate templates for probationary and regular employees:

| Classification | Minimum KRA items per applicable template |
|---|---:|
| Probationary | 12 |
| Regular | 15 |

These are minimums, not fixed totals. Include all existing required fields, criteria, weights, statuses, and behavior sections. Use the existing rating scale and scoring formulas. Do not change computation logic.

### Historical evaluation records

Generate coherent cycles (e.g., 2023–2026 when consistent with employment dates), assignments, KRA/behavior responses, self-evaluations, 360-degree peer ratings, supervisor/immediate-head evaluations, comments, submission/approval statuses, and final results. Every newly seeded regular employee must have historical evaluations from probationary employment through later regular cycles. Newly seeded probationary employees must have evaluation records appropriate to their tenure and existing workflow.

Use the real reporting hierarchy where supported (CEO/president to department managers, managers to supervisors, supervisors to staff). Peer evaluators must be eligible existing or newly seeded employees, not the rated employee unless permitted by the current workflow. Respect actual required participant counts and approval sequences. Avoid inserting only aggregate scores without underlying responses. Final values must agree with the existing formulas.

Create varied, realistic trends: strong, improving, declining, inconsistent, high KRA/low behavior, and low KRA/high behavior. Do not overwrite any existing evaluation records, especially those belonging to protected HR employees. Any new records referencing protected HR personnel must not mutate their records and must follow the existing workflow.

Validate template item counts, all expected evaluation response records, evaluator relationships, approvals, calculations, statuses, and timelines. Ensure records can be consumed by existing evaluation history, comparisons, performance trends, analytics, and reports. **STOP and await approval.**

## 7. Phase 3 — Career movement database seeds

**Begin only after Phase 2 approval.**

For selected **new mock employees**, create realistic chronological histories of branch transfers, department transfers, promotions, and regularization where the existing schema supports them. Include previous/new branch, department and position, request and approval actors, request/approval/effective dates, reason, status, and remarks where applicable. Use only existing valid branches/departments/positions. Some employees should have multiple movements, with a consistent timeline. The latest **completed** movement must agree with their current employee assignment; pending/rejected movements must not change it. Avoid duplicating regularization events already represented by Phase 1. Do not change or add history to the three protected HR employees without approval.

Validate movement references, chronological order, approvals, statuses, and consistency with current assignments. **STOP and await approval.**

## 8. Phase 4 — SQL validation and analytics readiness

**Begin only after Phase 3 approval.** Create SQL checks for employee counts by department/branch/classification, completed evaluation cycles, self/peer/head ratings, department performance, KRA/behavior breakdowns, historical comparisons, regularization, transfers, promotions, missing/duplicate records, invalid dates, and broken relationships.

**Do not seed fabricated dashboard totals or modify analytics code.** Existing dashboards/reports must derive their numbers from the underlying seeded records. If required records are missing, add compatible seed data only; document any schema/query limitations rather than altering application logic. Do not claim the UI was verified solely from SQL validation.

## 9. Existing XAMPP PowerShell import convention

The user's current commands are:

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/1st_schema_tables.sql"
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/2nd_seed_organization.sql"
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/3rd_seed_HR_accounts_.sql"
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/4th_seed_executive_portal_accounts.sql"
```

**Follow this MySQL CLI approach.** Do not introduce Laravel/Prisma/migration frameworks or require a new application-side import system. These existing initialization commands are documented as context; **do not automatically rerun them against an already populated database**, especially schema initialization. Preserve their files unchanged.

For Phase 1, provide README commands in this format, adjusting file paths and order after inspecting actual dependencies:

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source presentation-test-seeds/01-employee-seeds/01_employee_profiles.sql"
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source presentation-test-seeds/01-employee-seeds/02_educational_background.sql"
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source presentation-test-seeds/01-employee-seeds/03_employment_history.sql"
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source presentation-test-seeds/01-employee-seeds/04_employee_accounts.sql"
```

These are **illustrative commands, not yet validated**. SQL must select the correct existing database (`USE ...`) or the CLI must explicitly specify it. README must give actual verified commands for each approved phase, all completed phases, and validation. Include visible error handling and halt on failures. Use safe rerun/idempotency checks, transactions where appropriate, and explicit confirmation before any potentially destructive action. Never truncate/reset the database or change existing records. Prefer testing in a dedicated development copy/backup first. No hardcoded real passwords.

## 10. Documentation requirements

**README.md:** overview, prerequisites, actual database selection, file/import order, exact copy-paste-ready PowerShell commands for individual and completed phases, development-only test credentials, validation, troubleshooting, safe re-import guidance, and preservation checks.

**DEVELOPMENT_PLAN.md:** discovered tables/columns/relationships, dependencies, seed strategy, expected records, phase-by-phase milestones, and validation plan.

**SEED_PROGRESS.md:** current phase, completed/pending files, expected and actually imported counts, checks performed, protected HR preservation results, blockers, and next steps. Update after each phase.

## 11. Global rules

1. **DATABASE SQL SEEDS ONLY**; Markdown docs and import/validation commands are permitted.
2. No application source, UI, feature, workflow, authentication, formula, or schema modifications.
3. Preserve all existing records, with **special immutable protection** for Elena Delgado, Patrica Gomez, and Miguel Torres.
4. Never generate duplicate versions of protected HR employees.
5. Inspect real tables and relationships; do not invent schema names.
6. Use fictional employee identities and coherent dates/positions.
7. Respect foreign keys, unique keys, constraints, and existing role rules.
8. Make imports repeatable without duplicates or destructive changes.
9. Match the user's XAMPP PowerShell/MySQL CLI workflow.
10. Generate one phase at a time and require approval before continuing.
11. Do not claim execution, import success, UI functionality, or validation without evidence.
12. Stop and report any incompatibility rather than silently changing the system.

## 12. Expected final presentation dataset

When all phases have been approved and imported, the existing database should contain complete fictional employee profiles across departments, at least two new probationary employees per department, education and probationary-to-regular histories, valid accounts/roles, department-specific KRA templates, self/360-peer/head evaluations, approval and result histories, realistic performance variation, transfers, promotions, and coherent analytics-ready underlying records. The existing HR employee records for **Elena Delgado, Patrica Gomez, and Miguel Torres must remain unchanged**.

## 13. Immediate AI instruction — START WITH PHASE 1 ONLY

1. Inspect the four existing database SQL files and relevant schema/application reads without editing source files.
2. Verify and record the protected HR employee records and their associated IDs/roles/history.
3. Identify all active departments, branches, employee fields, education/history tables, accounts, and role relationships.
4. Create `presentation-test-seeds/`, `README.md`, `DEVELOPMENT_PLAN.md`, and `SEED_PROGRESS.md`.
5. Generate **only Phase 1 employee SQL seeds**: profiles, education, employment/regularization history, accounts/roles.
6. Generate Phase 1 SQL validation queries, including before/after protected-record preservation checks.
7. Document actual PowerShell MySQL CLI import commands in README.
8. Validate against the real schema; report expected vs actually executed/imported counts honestly.
9. Summarize completed files, counts, preservation checks, issues, and next actions.
10. **STOP. Do not generate Phase 2 evaluation seeds or Phase 3 career movement seeds until I explicitly approve.**
