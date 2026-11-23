# Phase 2 — Initial, Final, and Annual

This phase is complete and excludes Quarterly by explicit user direction because Quarterly is temporarily disabled.

Import order:

1. `05_probationary_kra_templates.sql`
2. `06_regular_annual_kra_templates.sql`
3. `07_evaluation_cycles.sql`
4. `08_self_evaluations.sql`
5. `09_peer_360_evaluations.sql`
6. `10_supervisor_evaluations.sql`
7. `11_evaluation_approvals.sql`
8. `12_evaluation_results.sql`

Run `../04-validation/validate_all_seeds.sql` afterward. All files are deterministic and passed a complete rerun in `raquel_hris_presentation_phase2_test`.
