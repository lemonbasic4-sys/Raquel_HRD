# Annual evaluation rollout beyond Human Resources

Status: AP pilot implemented in the shared annual engine; other non-HRD departments retain their current workflow pending AP acceptance.

## Confirmed baseline

- Human Resources uses an HR Manager-started Annual cycle with a fixed department roster, department-only Core Behaviors & Values ratings, immediate-head KRA review, an 80% KRA / 20% Core Values score, and HR Manager approval before history.
- The President/CEO reviews the HR Manager's KRA but does not provide a cross-department Core Values rating.
- AP now uses an AP Manager-started and AP Manager-approved cycle. Other non-HRD departments still create a cycle when an employee submits an Annual self-rating.
- Position `reports_to` determines the immediate head. Employee Portal accounts, rather than HRIS accounts, are the normal access route outside HRD.
- The AP pilot records the effective regularization date on automatic and manual status transitions. The AP roster is refreshed before final approval and when the owner views a cycle; an in-period late regularization reopens an approved cycle and requires fresh AP Manager approval.

## Proposed AP pilot

| Participant | Annual form creator / KRA reviewer | Core Values raters | Cycle action |
| --- | --- | --- | --- |
| VP for Acquired Properties | Configured immediate executive | AP department members, including self | Participates in AP ratings and rates the AP Manager's KRA and Core Values as immediate head |
| AP Manager | VP for Acquired Properties | AP department members, including self | Starts and approves the AP cycle |
| AP Supervisor | AP Manager | AP department members, including self | Participates and reviews direct reports' KRA |
| AP Staff | AP Supervisor | AP department members, including self | Participates |

Use the actual position hierarchy and department membership from Position Management. The client confirms that the VP for AP is the AP Manager's immediate head and rates both KRA and Core Values. The VP's Core Values rating must be counted once in the AP Manager's department 360 result; do not create a second behavior vote when the head submits the combined review.

## AP cycle rules implemented for the pilot

1. **Department owner.** Configure one active cycle starter and one final cycle approver per department. Resolve them from an explicit assignment, not a job-title string, rank alone, or the global HR Manager role. A person may own only their assigned department cycle.
2. **Readiness preview.** Before Start, list active regular employees in that department and check their Employee Portal accounts, position, immediate-head account, assigned active Annual form, KRA weights totaling 100%, and eight standard Core Values. Show blocking reasons per employee.
3. **Dates and snapshot.** The owner selects Period From, Period To, and self-rating deadline. Start creates one department-period cycle, snapshots eligible members and reviewer positions, blocks overlapping cycles, and notifies those members.
4. **Ratings.** Each participant self-rates KRA and Core Values. Coworkers rate Core Values only within their own department. An immediate head may review a direct report's KRA even when the head belongs to another department; that does not grant a cross-department Core Values vote.
5. **Approval and score.** Immediate heads approve KRA. Core Values include the employee's self-rating exactly once plus each required department peer rating. Compute `(KRA × 0.80) + (Core Values × 0.20)`. Show provisional scores until required ratings arrive.
6. **Completion and history.** Recheck the current eligible roster, every self-rating, immediate-head approval, and all required peer ratings inside the final approval transaction. Only the configured department approver may approve that department cycle; then move it to read-only history and notify participants.
7. **Regularized or transferred employees.** Record the effective date of regularization. AP accepts a new participant when that date falls within Period From/To, including after approval. Refresh the roster before approval and reopen an approved cycle for required late ratings. Preserve earlier submissions and score versions. A transfer requires review of the original roster, form, and reviewer.

## Decisions to confirm with the client before implementation

1. Confirm the VP for Acquired Properties has AP department membership in the live organization, so the VP's Core Values vote follows the department-only 360 rule. The seeded AP roster places the VP in AP.
2. The AP pilot uses the effective regularization date and rejects an out-of-period late participant. The self-rating deadline is currently shown to employees but does not block a late participant; confirm whether it should block ordinary submissions after the deadline.
3. Confirm whether every later department follows the same 80/20 weights and eight Core Values, or whether approved templates may vary.

## Implementation order after decisions

1. Completed: AP owner mapping, regularization date, roster reconciliation, final approval checks, and AP Start action.
2. Completed: AP self-rating period assignment, department peer tasks, immediate-head KRA review, final approval, history, and late-cycle reopening.
3. AP pilot verification: test AP Manager, VP, Supervisor, and Staff accounts in the browser; cross-department KRA without a cross-department 360 vote; missing forms/accounts; overlapping periods; returned ratings; and late regularization before and after approval.
4. Compare calculated scores with a spreadsheet and verify history is read only. After AP acceptance, configure the next department through the same owner mapping and repeat the role/roster checks.

## Acceptance checks for the AP pilot

- The AP Manager sees AP only in Start/Approve controls; the HR Manager cannot approve AP merely because of their HRIS role.
- The VP for AP reviews the AP Manager's KRA and gives one Core Values rating that appears in the AP Manager's 360 result.
- No AP cycle starts when an eligible employee lacks a valid form, account, or immediate head.
- Every required AP participant appears before final approval, including someone regularized during the period who has not yet submitted.
- Every Core Values rater belongs to AP; an external executive can review KRA without entering the AP peer average.
- All required ratings and approvals produce the expected 80/20 score and an approved history record. Incomplete cycles stay active.
