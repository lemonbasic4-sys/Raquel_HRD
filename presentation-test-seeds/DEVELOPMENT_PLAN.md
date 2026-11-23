# Phase 1 Development Plan

## Discovered environment

- Database: `raquel_hris`
- Tested server target: MariaDB 10.4.32
- Active departments: 12
- Active branches: 151
- Main office branch: ID 102
- Employee identity and current assignment: `employees`
- Organization: `branches`, `departments`, `rank_categories`, `job_titles`
- Profile children: `employee_details`, `employee_contacts`, `employee_addresses`, `employee_emergency_contacts`
- Education: `employee_education`
- Available employment history: `employee_work_experience`
- Accounts and roles: `users`

Active department inventory discovered from the database:

1. Acquired Properties
2. Audit
3. Business Development
4. Compliance
5. Finance
6. General Services
7. Human Resources
8. Information Technology
9. Marketing
10. Office of the President
11. Operations
12. Purchasing

The validation script emits the complete ordered inventory of all 151 active branches with IDs, names, and locations rather than duplicating that long machine-readable list in this plan.

The `users.role` enum contains Admin, HR Manager, HR Supervisor, HR Staff, Employee, and President and CEO. Non-HR managerial routing is derived from employee rank, position, branch, department, and `reports_to`; therefore seeded non-HR managers and supervisors receive Employee Portal accounts. The new regular HR employee receives Employee and HR Staff accounts. Probationary HR employees receive Employee accounts only, matching `admin/add-user.php` eligibility.

## Protected records

| Employee ID | Code | Actual name | Role account |
|---:|---|---|---|
| 101 | HRD-001 | Elena Delgado | HR Manager |
| 301 | HRD-002 | Patricia Gomez | HR Supervisor |
| 302 | HRD-003 | Miguel Torres | HR Staff |

The seed statements select only deterministic `DEMO-*` codes. They never update, replace, or delete existing employees or their child records.

## Seed strategy

1. Insert 12 regular department anchors using existing job titles and reporting employees.
2. Insert 24 probationary employees after their reporting anchors exist.
3. Add required profile children using collision-safe employee-code and name joins.
4. Add education from elementary through the declared highest attainment.
5. Add two internal work-history periods for every new regular employee.
6. Add one Employee Portal account per new employee and one additional HR Staff account for the new regular HR employee.
7. Add family, training, eligibility, skills, government IDs, disclosures, personal property, references, and probationary work experience so every mock employee satisfies the existing 12-section profile-completion formula.
8. Validate counts, references, chronology, portal mappings, 12/12 profile completeness, Phase 1 boundaries, and protected baselines.

## Expected Phase 1 records

| Record group | Expected new rows |
|---|---:|
| Employees | 45 (36 core + 9 Annual peers) |
| Employee details | 45 |
| Employee contacts | 45 |
| Employee addresses | 90 |
| Primary emergency contacts | 45 |
| Education | all 45 covered |
| Work experience/status history | 42 (2 for each of 21 regular employees) |
| Employee Portal users | 45, plus authorized role/reviewer accounts |
| Family/training/eligibility/skills/government IDs/assets/references | 45 employees covered in each group |
| Additional probationary work experience | 24 rows |

## Validation plan

- Run preflight against the target and stop on identity collisions.
- Capture the protected employee/user rows and associated Phase 1 child-table counts.
- Import into disposable clone `raquel_hris_presentation_phase1_test` first. Completed successfully on 2026-11-23.
- Run all validation queries. Completed successfully on 2026-11-23.
- Re-run Phase 1 and the approved peer extension and verify counts do not increase. Completed; final validation stayed at 45 employees and 45 Employee Portal accounts.
- Compare protected outputs before and after. Completed; protected employee/user rows, timestamps, and Phase 1 child counts were unchanged.
- Do not import into the live database automatically.

## Phase 2 implementation

- Quarterly excluded by user direction because it is temporarily disabled.
- 24 probationary templates: Initial and Final for all 12 departments, 12 KRAs each.
- 21 Annual templates for all presentation Regular positions, 15 KRAs each.
- Every template has eight Core Behaviors, 100% KRA item weights, and an 80%/20% final split.
- 21 Regular employees have Initial and Final histories.
- 24 current probationary employees have Initial records: 12 approved and 12 pending review.
- 130 completed Annual histories cover every eligible calendar year after regularization through 2025 for all seed-controlled Regular employees except the President/CEO, with self, required same-department Regular raters, peer submissions, head review, approval, version snapshots, results, and development plans.
- Twenty-one 2026 Annual assignments remain Pending Self-Rating for the live presentation workflow.
- Stored Annual totals are recalculated by validation from underlying KRA and 360 records.

## Phase 3 implementation

- Regularization history is reused from Phase 1; no duplicate evaluation-linked request is inserted.
- Six transfer rows cover branch and department moves: four approved/applied, one pending, and one rejected.
- Four succession endorsements cover promotions: two approved/applied, one pending HR review, and one rejected.
- Completed destinations match current assignments; incomplete decisions do not mutate assignments.
- Disposable import, rerun idempotency, live import, chronology, and protected-record checks passed on 2026-11-23.

## Phase 4 implementation

- Added read-only workforce summaries by department, branch, and classification.
- Added finalized evaluation summaries by approval year, evaluation year, department, KRA, behavior, and performance level.
- Added regularization, transfer, and succession-promotion readiness summaries.
- Added explicit zero-failure checks for duplicate identities/evaluations, missing organization references, job-title/department mismatches, incomplete approved metrics, invalid dates, broken movement/promotion references, and accidental President/CEO evaluation data.
- Live validation passed on 2026-11-23 after restoring the previously approved Phase 2 dataset, which had been absent from the reinitialized live database.

## Remaining manual verification

- Browser rendering and role-specific dashboard navigation have not been tested by these SQL checks.
