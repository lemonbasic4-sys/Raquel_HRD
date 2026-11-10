# Annual employee period rule

Status: implemented for new published Annual/Quarterly forms on this feature branch. Existing templates and open department cycles retain their legacy flow. Backend integration and CLI page rendering checked; interactive browser/mobile testing remains pending.

The department Annual cycle remains January 1 through December 31. An employee who becomes Regular during that year belongs to that same year's Annual cycle. There is no minimum number of months of regular service required.

For each eligible employee:

- Period From = the later of January 1 of the cycle year or the employee's actual regularization effective date.
- Period To = December 31 of the cycle year.
- An employee regularized after that cycle's December 31 does not belong to that cycle.
- In following years, the period is January 1 through December 31, subject to continuing eligibility.

| Department | Employee / position | Regularization date | Annual cycle | Period From | Period To |
| --- | --- | --- | --- | --- | --- |
| Human Resources | James / HR Staff | March 15, 2027 | 2027 | March 15, 2027 | December 31, 2027 |
| Human Resources | James / HR Staff | November 15, 2027 (alternative example) | 2027 | November 15, 2027 | December 31, 2027 |
| Human Resources | James / HR Staff | November 15, 2027 | 2028 | January 1, 2028 | December 31, 2028 |
| Human Resources | Patricia / HR Supervisor | Before 2027 | 2027 | January 1, 2027 | December 31, 2027 |

These are assessment periods, not form availability dates or submission deadlines. Missing regularization dates must be resolved rather than invented.

## Quarterly and Annual submission flow

These requirements supersede the earlier selected-rater and department-wide start/approval proposals. New forms use the individual flow.

1. Remove the department cycle setup/readiness panel from both Quarterly and Annual tabs, including Period preview, Start Cycle, and department-wide Approve Cycle controls in the replacement flow. This applies to the highlighted Human Resources panels and the equivalent workflow in other departments.
2. The immediate head supplies the Quarterly form's Period From and Period To. These are freely chosen dates, not hardcoded calendar quarters. Employees only see the dates and answer the form.
3. Annual dates follow the individual regularization rules above.
4. Notify each eligible employee when their assigned form is available; link directly to it and update the Self-Rating indicator. Do not send a duplicate notification on every refresh. Publication as an Active form is the release trigger; eligible employees can open it immediately. Revision Date and Effective Date remain document metadata.
5. On submission, the employee's individual evaluation appears in the appropriate Quarterly or Annual Department Peers list automatically. No department manager needs to start a cycle first.
6. Notify the other active, Regular employees in the same department to provide their 360 ratings. The immediate head does not choose raters. The employee's self-rating is counted separately, once. A peer's own evaluation need not be due or submitted to rate this employee.
7. The immediate head reviews the KRA and supplies a behavior rating if eligible as a same-department rater. A cross-department immediate head does not become an additional peer merely through the reporting relationship.
8. Once all required peer ratings are submitted, the individual evaluation becomes ready for immediate-head approval. Enforce this server-side as well as in the UI; do not allow final approval with missing required ratings.
9. Notify the immediate head that the employee's evaluation is ready for approval. On approval, finalize the individual's result and place it in the appropriate history. Other employees' unfinished evaluations do not block this result.
10. Preserve the KRA 80% / behavior 20% calculation and existing completed history. Historical department cycles must remain readable; removing setup controls does not mean deleting their records.

### Human Resources example

Patricia Gomez (HR Supervisor) creates a Quarterly form for James (HR Staff), with dates Patricia chooses. James receives an availability notification and submits his answers. Other active, Regular HR employees receive a task to rate James even if their own forms are not due. Patricia can give final approval after the required peer ratings and KRA review are complete. James's evaluation then moves to Quarterly History independently.

The same submission/rating/approval sequence applies to Annual, with James's individually calculated Annual dates.

## Operational rules

- Publish a new form for each assessment period. Templates already used by evaluations cannot be edited into a different period.
- Publishing an Active form releases it immediately to eligible employees. There is no additional Start Cycle or scheduled release gate.
- Annual forms require a real `regularization_effective_date`. Missing dates do not fall back to the hire date; HR must correct the employee record.
- Required peer raters are fixed from the other active, Regular employees in the department when the employee submits. Coworkers with no portal account still count; access must be provided rather than silently excluding them.
- New joiners are not automatically added to an already submitted evaluation's rater snapshot. There is no automated waiver if a required rater later separates or becomes inactive; administrative resolution is still needed in that case.
- The immediate head can save the KRA review and submit their own peer rating before other peers finish. This does not approve the overall evaluation. Once peers finish, open Review & approve to finalize.
- With no other eligible department peers, the behavior component is the employee's self-rating; immediate-head approval is still required.
- Existing templates and existing open/complete cycles are not migrated. Their records and old completion behavior are preserved. The obsolete start/setup panel is no longer shown; existing open cycles keep their legacy completion controls.

See `INDIVIDUAL_EVALUATION_TEST_GUIDE.md` for testing steps and validation limits.
