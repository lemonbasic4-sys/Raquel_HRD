# Phase 2 — Initial, Final, and Annual

This phase excludes Quarterly by explicit user direction because Quarterly is temporarily disabled. Every seed-controlled Regular employee except the President/CEO has completed Initial and Final histories, completed Annual records from the calendar year after regularization through 2025, and one open 2026 Annual assignment for the live demonstration. This includes `AP-001`, `HRD-001`, `HRD-002`, and `HRD-003` without overwriting their employee master records.

Import order:

1. `05_probationary_kra_templates.sql`
2. `06_regular_annual_kra_templates.sql`
3. `07_evaluation_cycles.sql`
4. `08_self_evaluations.sql`
5. `09_peer_360_evaluations.sql`
6. `10_supervisor_evaluations.sql`
7. `11_evaluation_approvals.sql`
8. `12_evaluation_results.sql`
9. `13_annual_2026_demo_assignments.sql`

Run `../04-validation/validate_all_seeds.sql` afterward. All files are deterministic and passed a complete rerun in `raquel_hris_presentation_phase2_test`.
