# Phase 3 - Career Movement Seeds

Import in numeric order. The files are idempotent and only target `DEMO-` employees.

1. `13_regularization_history.sql` validates Phase 1 history without duplicating regularization requests.
2. `14_branch_transfers.sql` adds completed and pending branch transfers.
3. `15_department_transfers.sql` adds completed and rejected department transfers.
4. `16_promotions.sql` uses `succession_endorsements`, the actual promotion workflow.

Completed records match the current assignment. Pending and rejected records are not applied. Protected HR employees are not targeted.
