# Annual employee period rule

Status: agreed business rule; not yet implemented in the Annual workflow.

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

The immediate head selects eligible same-department peer raters. Their own evaluations do not need to be due for them to rate the employee. Rater selection is an agreed requirement, not an implemented change in this branch.

Still to decide: Quarterly scheduling, form release timing, manager cycle controls, individual completion/approval behavior, and how employees joining after a cycle is finalized should be handled. Preserve completed records during implementation.

This branch currently implements Initial date calculations and displays read-only Period From / Period To on self-rating forms. Annual employee-specific periods and selected peer raters require a separate workflow implementation and validation.
