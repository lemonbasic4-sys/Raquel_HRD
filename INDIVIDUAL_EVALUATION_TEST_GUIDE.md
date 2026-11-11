# Testing individual Quarterly and Annual evaluations

Branch: `feature/employee-evaluation-periods`. Main folder only.

## Before testing

The application adds missing template-period columns, the individual-cycle marker, and required-rater tables through its existing runtime schema initialization. The base schema includes the new template columns for fresh setups. Existing rows default to legacy behavior; there is no data migration that rewrites submitted evaluations.

Use fictional employees. For Annual, HR must record the actual regularization effective date; the application does not invent it from the hire date.

## Quarterly dry run

1. Log in as an authorized immediate head and create a **new Quarterly template** for the appropriate department/positions.
2. Enter any valid Period From and Period To (for example, July 12 to November 20). Calendar quarters are not enforced. Complete the normal KRA/behavior wizard and publish.
3. Log in through an eligible employee's Employee Portal. Check the availability notification and Self-Rating count. Refresh: the same availability notification should not repeat.
4. Open the notification. Verify both period fields are already filled in and read-only. Submit self-rating.
5. In the department peers page, verify this employee appears without a department Start Cycle. Other employees do not need to submit their own self-ratings to rate this person.
6. As another active Regular employee in that department, submit the eight behavior ratings. A coworker outside the department must not be able to submit.
7. As the immediate head, save the KRA review, behavior rating, comments and plan. If another required rating is missing, the overall evaluation must remain pending.
8. After all required peers submit, the evaluation stays pending and the immediate head sees Review & approve with a pending indicator. Only explicit final approval moves it to history; the department manager does not approve the cycle separately.
9. On a phone, check the existing mobile roster, rating cards, development plan, review summary and buttons.

## Annual dry run

1. Publish a **new Annual template**, choosing the assessment year.
2. For an employee regularized November 15 of that year, verify Period From is November 15 and Period To is December 31. No minimum service duration applies.
3. For a later assessment year, verify January 1 through December 31.
4. Repeat the individual peer-rating and immediate-head approval sequence above.

## Compatibility checks

- Existing published templates are legacy until an unused form is deliberately edited/published with the new period controls, or a new form is created.
- Previously submitted evaluations and existing open department cycles keep their original period, raters and completion behavior.
- No completed record should become editable by publishing a new form.
- Rating submission remains locked after confirmation. Self-rating is counted once; a peer cannot rate themselves.
- A missing employee portal account does not reduce the number of required coworkers.

## Validation performed

An isolated database was built from the three base SQL files with the database name replaced in memory. The working database configuration and records were not used for test mutations.

The executable CLI integration check is `demo_data/workflow_demo_20261110/test_individual_workflow.php`. It is hardwired to `raquel_individual_workflow_test`, must run after a fresh base setup in that disposable database, and adds synthetic templates/users/evaluations there. Do not point it at the working database.

Checks passed for both types: deduplicated availability notifications; automatic individual registration; department rater snapshots; peers without their own self-rating; rejection of cross-department/self/duplicate peer ratings; no finalization with missing peers; early head review staying pending; independent head finalization and history; unchanged 80/20 computation; preserved legacy open cycles. Annual November/next-year dates and freely chosen Quarterly dates were also checked.

CLI page rendering checked self-rating date fields and immediate-head history without the retired setup panel. It does not prove browser interaction, CSS layout, notification click navigation, or physical-phone behavior. Those remain part of the manual dry run.

The temporary test database is removed after verification; test accounts are not inserted into the working database.
