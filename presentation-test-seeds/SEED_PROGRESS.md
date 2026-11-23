# Presentation Seed Progress

## Current phase

Phase 4 — final SQL validation and analytics-readiness checks completed

## Completed files

- `01-employee-seeds/01_employee_profiles.sql`
- `01-employee-seeds/02_educational_background.sql`
- `01-employee-seeds/03_employment_history.sql`
- `01-employee-seeds/04_employee_accounts.sql`
- `01-employee-seeds/05_complete_profile_sections.sql`
- `01-employee-seeds/06_regular_peer_profiles.sql`
- `02-evaluation-seeds/05_probationary_kra_templates.sql`
- `02-evaluation-seeds/06_regular_annual_kra_templates.sql`
- `02-evaluation-seeds/07_evaluation_cycles.sql`
- `02-evaluation-seeds/08_self_evaluations.sql`
- `02-evaluation-seeds/09_peer_360_evaluations.sql`
- `02-evaluation-seeds/10_supervisor_evaluations.sql`
- `02-evaluation-seeds/11_evaluation_approvals.sql`
- `02-evaluation-seeds/12_evaluation_results.sql`
- `03-career-movement-seeds/13_regularization_history.sql`
- `03-career-movement-seeds/14_branch_transfers.sql`
- `03-career-movement-seeds/15_department_transfers.sql`
- `03-career-movement-seeds/16_promotions.sql`
- `04-validation/validate_employees.sql`
- `04-validation/validate_evaluations.sql`
- `04-validation/validate_career_movements.sql`
- `04-validation/validate_analytics_readiness.sql`
- `04-validation/validate_all_seeds.sql`
- `README.md`
- `DEVELOPMENT_PLAN.md`

## Expected counts

- 45 fictional employees
- 24 probationary employees, exactly 2 per active department
- 21 regular employees
- 45 complete profile/detail/contact/emergency-contact sets
- 90 addresses
- Education coverage for all 45 employees
- At least 1 employee without a college degree
- 42 internal appointment-history rows for the 21 regular employees
- 45 Employee Portal accounts plus authorized HR/executive reviewer accounts
- 45 employees at 12/12 profile-completion sections (100%)

## Execution status

- Live `raquel_hris` core import (files 01–04): already present when the profile-completeness issue was reported; 36 `DEMO-*` employees were verified
- Live `raquel_hris` complete-profile import (file 05): **performed and passed** on 2026-11-23
- Disposable database import: **passed** in `raquel_hris_presentation_phase1_test` on 2026-11-23
- Idempotency rerun: **passed**; core counts remained 36 employees / 129 education / 24 regularization history / 37 accounts
- Complete-profile additive rerun: **passed**; support rows stayed at 36 per section and total work-history rows stayed at 48
- SQL validation: **passed** on MariaDB 10.4.32
- Live profile-completeness validation: **passed**; all 45 presentation employees have 12/12 sections (100%)
- Phase 2 disposable database: `raquel_hris_presentation_phase2_test`
- Phase 2 templates: **passed**; 12 Initial, 12 Final, 21 Annual
- Phase 2 evaluations: **passed**; 45 Initial, 21 Final, 63 Annual
- Annual 360: **passed**; 69 required and 69 submitted ratings
- Annual formula validation: **passed**; 0 stored-result mismatches
- Evaluator/timeline validation: **passed**; 0 invalid relationships and 0 invalid timelines
- Phase 2 idempotency: **passed**; counts remained 45 employees / 45 templates / 129 evaluations / 2,769 scores / 69 peer ratings / 63 versions
- Quarterly presentation records: **0**, per user instruction
- Live Phase 2 import: **performed and passed** in `raquel_hris` on 2026-11-23
- Phase 3 disposable database: **passed** in `raquel_hris_phase3_test_20261123`
- Phase 3 idempotency rerun: **passed**; counts remained 6 transfers and 4 promotion endorsements
- Live Phase 3 import: **performed and passed** in `raquel_hris` on 2026-11-23
- Phase 3 consistency validation: **passed**; 0 assignment, applied-status, chronology, protected-record, and duplicate-regularization failures
- Phase 4 initially detected 0 live evaluations; the live database had been reinitialized after the earlier Phase 2 import
- Approved Phase 2 seeds were restored to live `raquel_hris`: **passed**; 129 evaluations, 2,769 scores, 69/69 peer ratings
- Phase 4 analytics-readiness validation: **passed** on live `raquel_hris`; all 10 failure checks returned 0
- Analytics coverage: **passed**; 45 employees, 12 departments, 3 branches, Annual comparisons for 2023–2025, 6 transfers, and 4 promotion endorsements
- Protected HR own evaluations: **0 before / 0 after**; protected employee timestamps unchanged
- Browser login/UI test: not part of SQL validation and not performed

## Protected records

- Elena Delgado (`HRD-001`, employee 101): unchanged in disposable before/after comparison
- Patricia Gomez (`HRD-002`, employee 301): unchanged in disposable before/after comparison
- Miguel Torres (`HRD-003`, employee 302): unchanged in disposable before/after comparison

Protected employee and user `updated_at` values remained `2026-11-23 12:44:49`; Phase 1 related-row counts also remained unchanged.

## Known limitation

There is no general status-history table. Phase 1 uses `employee_work_experience` plus `employees.regularization_effective_date` for coherent probationary-to-regular history.

## Approval boundary

All four approved seed phases are complete. Browser/dashboard rendering remains a separate manual verification step; SQL readiness does not prove UI rendering.
