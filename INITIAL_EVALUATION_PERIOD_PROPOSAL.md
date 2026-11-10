# Initial Evaluation Period Proposal

**Status:** For discussion. Documentation only; no application changes are included.

## Agreed requirements

Evaluation forms exclusively for the following employment statuses must show both dates:

| Employment status | Period From | Period To | Initial duration |
| --- | --- | --- | --- |
| OJT | Required | Required | To be decided |
| Trainee | Required | Required | To be decided |
| Probationary | Required | Required | Three calendar months |
| Project Based | Required | Required | To be decided |

**Period From** identifies the start of the period being assessed. **Period To** identifies its end. Neither field, by itself, sets a submission deadline or changes the employee's employment status.

## Probationary Initial example

A probationary employee starts on **February 1, 2026**.

| Field | Value |
| --- | --- |
| Evaluation type | Initial |
| Period From | February 1, 2026 |
| Period To | May 1, 2026 |

**Calculation: Period To = probationary start date + three calendar months.**

This proposal uses the three-month anniversary, May 1. It does not automatically regularize the employee. Final evaluation timing will be discussed separately.

## Proposed form workflow

1. The immediate head creates an evaluation template for the appropriate department, positions, employment statuses, and evaluation type.
2. The template defines the assessment criteria and explains the applicable date rule.
3. Each employee's evaluation receives its own **Period From** and **Period To**. A shared template must not force employees with different start dates to use the same period.
4. For a probationary Initial evaluation, the system fills in the employee's probationary start date and calculates the three-month end date.
5. The same saved period appears in employee self-rating, immediate-head review, and evaluation history.

Suggested wizard explanation for probationary Initial forms:

> Each employee's Initial evaluation covers three calendar months from the start of their probationary appointment. Dates are filled in separately for each employee.

Suggested employee display:

```text
Initial Evaluation
Period From: February 1, 2026
Period To:   May 1, 2026
```

The proposed approach is to make calculated dates read-only and enforce the calculation on the server. This is a recommendation, not an implemented feature.

## Recommended date safeguards

- Use the start of the relevant appointment. An employee moving from OJT to Probationary may have a probationary start date different from their original hire date.
- Confirm the correct source field before implementation. Use `hire_date` only when it represents that appointment's start; do not silently substitute another date.
- If the start date is missing, explain that HR must complete the employee record before the period can be generated.
- Add calendar months rather than a fixed number of days. If the destination month lacks the same day, use its last valid day: January 31, 2026 becomes April 30, 2026.
- Reject a period whose From date is later than its To date.
- Preserve saved periods in completed evaluations. Any correction to a pending evaluation's dates should be deliberate and recorded in the audit trail.

## Decisions still needed

| Decision | What needs confirmation |
| --- | --- |
| OJT dates | Which start date and duration define the evaluation period? |
| Trainee dates | Which start date and duration define the evaluation period? |
| Project Based dates | Should the period follow the project, contract, or another agreed range? |
| Date entry | Can the head choose dates for those three statuses, or should they be calculated? |
| Probationary start source | Is the existing hire date sufficient, or must a separate appointment effective date be recorded? |
| Availability | When can an employee begin and submit the Initial form? |
| Deadline | Is there a separate self-rating submission deadline? |
| Pending corrections | Who may regenerate a pending period after a start-date correction? |

The three-month rule applies only to the probationary Initial example. Do not extend it to OJT, Trainee, or Project Based without confirmation.

## Outside this proposal

- Final evaluation periods and timing.
- Changes to approval authority, reviewer claiming, or regularization decisions.
- Changes to KRA criteria, the eight standard behavior items, or scoring formulas.
- Annual and Quarterly cycle behavior.
- Rewriting completed evaluation history.

## Acceptance checklist for implementation

- [ ] All four employment statuses have Period From and Period To.
- [ ] February 1, 2026 produces May 1, 2026 for a probationary Initial evaluation.
- [ ] Different employee start dates produce separate periods under the same template.
- [ ] Month-end and leap-year calculations produce valid dates.
- [ ] Missing start dates receive a clear message instead of an invented date.
- [ ] Employees and reviewers see the same saved period.
- [ ] Browser-submitted values cannot override a server-calculated period.
- [ ] Pending date corrections are audited; completed periods remain intact.
- [ ] Final, Annual, and Quarterly workflows remain unchanged.
