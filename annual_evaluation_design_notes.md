# Annual Evaluation Design Notes

**Status:** Confirmed annual workflow implemented on October 18, 2026. Sections explicitly marked as proposed HR policies remain open for discussion.

## Goal

Design and implement the annual evaluation workflow for regular employees across departments. Template creation and finalization follow the immediate-head/direct-report relationships configured in Position Management.

## Current agreed direction

### Templates

- Annual templates are created for the positions that report directly to the template creator.
- Example for HRD:
  - President/CEO creates the Annual template targeting HR Manager.
  - HR Manager creates the Annual template targeting HR Supervisor.
  - HR Supervisor creates the Annual template targeting HR Staff.
- Each template contains the target position's KRAs and the same default set of eight Core Behaviors & Values.
- KRA items and weights may differ by position; the shared Core Behaviors & Values set is common.

### Self-rating

- Each employee completes the Annual template assigned to their position.
- Self-rating includes the position-specific KRA items and the shared Core Behaviors & Values.
- The current scoring model is 80% KRA and 20% Core Behaviors & Values.
- The KRA score is calculated using each KRA item's configured weight. The Core Behaviors & Values score is an average of its ratings.

### Department 360 ratings

- Submitted Annual self-ratings become available in the department's separate 360 rating page. Coworkers with submitted self-ratings for the same department period can rate them. This allows an initial batch to proceed while late participants join later.
- Department members rate the other eligible employees in their own department on Core Behaviors & Values only. They do not peer-rate KRAs.
- An employee does not submit a duplicate self-rating in the 360 form; their self-rating already exists in their Annual evaluation.
- For each employee, the combined Core Behaviors & Values result is calculated from that employee's self-rating plus the Core Behaviors & Values ratings received from the other department members.
- Each employee gets an individual combined score. Do not use one department-wide average as the Core Behaviors & Values score for everyone.
- Example for a three-person HRD team: each employee's combined Core Behaviors & Values result uses their self-rating plus ratings from the other two employees. This is an illustration, not a fixed team size.

### 360 submission, visibility, and consolidation

- Before submitting a 360 rating, show the rater a confirmation popup and a summary of their selected ratings so they can catch mistakes.
- Use numeric score-entry fields from 1.00 to 4.00 for the eight Core Behaviors & Values, without radio choices.
- After the rater confirms and submits, their submission is locked; they cannot edit it themselves.
- HR Manager and HR Supervisor can view the HRD 360 summary with named ratings. The employee being rated sees the ratings anonymously as `Employee 1`, `Employee 2`, etc.; names are hidden to reduce interpersonal conflict.
- HR Manager and HR Supervisor can view the HRD 360 summary. The employee's immediate head finalizes that employee's Annual evaluation: HR Supervisor finalizes HR Staff; HR Manager finalizes HR Supervisor; President/CEO finalizes HR Manager.
- The final score uses the 80/20 formula:

  `Final score = (consolidated KRA score x 80%) + (combined Core Behaviors & Values score x 20%)`

### Late 360 submissions after finalization

- A submission is considered late if it arrives after the first 360 batch has been finalized. This is the cutoff definition; it is not based on a fixed calendar date.
- A rater who missed the first batch may still submit after that batch is finalized.
- Accepting that late submission can change the 360 averages and final scores of the coworkers they rated. Recalculate affected results; do not pretend the already-finalized score is unchanged.
- Preserve the earlier finalized scores and mark the new values as revised due to a late 360 submission. Record the late submission time, affected evaluations, old/new scores, and resulting recalculation in the audit trail; notify the affected employees and authorized consolidators.
- The immediate head for each affected employee must explicitly review and re-finalize that employee's revised Annual result. Do not make the changed score current automatically; preserve the prior finalized result in the audit history.
- The late rater's own submission is locked immediately after they submit it, just like an on-time submission.

## Current HRD example

This is an illustration, not a fixed team size or a hardcoded HRD roster. The system should use the eligible employees in the department's annual cycle. For a department with `N` eligible employees, each employee rates the other `N - 1` employees; each employee's combined Core Behaviors & Values result uses their self-rating plus valid ratings they receive from eligible department members.

For example, a department with an HR Manager, HR Supervisor, and HR Staff:

- Each person self-rates their own KRAs and Core Behaviors & Values.
- Each person rates the other two HRD employees on Core Behaviors & Values.
- For each target employee, average their self Core Behaviors & Values rating with the two ratings they receive.
- Combine that individual Core Behaviors & Values result with the target employee's consolidated KRA score using the 80/20 formula.
- HR Manager and HR Supervisor can review the HRD 360 summary.
- Finalization follows the reporting line: HR Staff -> HR Supervisor; HR Supervisor -> HR Manager; HR Manager -> President/CEO.

## Proposed edge case: employee dies after self-rating but before rating peers

**Proposal for discussion, not a finalized HR policy:**

- Preserve the employee's completed self-rating and any peer ratings already submitted.
- Cancel the employee's unfinished outgoing 360 rating tasks; do not attempt to collect ratings in their name or backfill them.
- Close the employee's own in-progress evaluation as `Incomplete - deceased` with no system-generated final score by default. Any different treatment would require an authorized, documented HR decision.
- Do not let the missing ratings from this employee block the remaining employees' evaluations. HR should be able to mark the employee as an exempt rater; remaining averages use only valid submitted ratings, subject to a minimum-response rule that HR still needs to define.
- Pending requests for other employees to rate the deceased employee should stop by default, while already-submitted ratings remain preserved in the audit history.

## Proposed edge case: employee does not submit assigned 360 ratings

The client indicated that if an employee does not participate despite being notified by their head, the head may change that employee's Core Behaviors & Values rating. The exact meaning and authority still need confirmation.

**Important distinction for the workflow:**

- A missing 360 submission means the employee did not provide ratings *about coworkers*. The system must not fabricate those missing ratings, treat blanks as zero, or use a score adjustment to fill in the missing rater data. Other employees' averages should use valid submitted ratings only, subject to a minimum-response rule.
- Any change by the head would affect the nonparticipant's *own* Core Behaviors & Values evaluation, not the ratings that person was assigned to give coworkers.
- If this is the intended policy, make it an explicit, authorized manual adjustment: require the head to select the affected Core Behaviors & Values item(s), enter a reason, and preserve the original score, adjusted score, author, and timestamp in the audit trail. Do not automatically deduct points merely because a peer-rating submission is missing.
- Keep the 360 task marked `Not submitted` or `Closed — non-participation` after the reminder/deadline process. Define whether the head must review/confirm non-participation before closing it.

**Open clarification:** Confirm whether the client means (a) the head manually adjusts the employee's own Core Behaviors & Values rating because of non-participation, or (b) the head supplies ratings for coworkers in place of the missing submission. These are different actions; the latter should not be inferred from the former.

## Proposed edge case: employee transfers during an active Annual evaluation

**Proposal for discussion, not a finalized HR policy:**

- Treat a transfer as a dated organizational change. Do not silently change the existing evaluation's department, position, template, or peer-rating cohort.
- Preserve the evaluation entry and its original assignment details. Mark it `Transfer review required` and record the transfer's effective date, old/new department, old/new position, and reporting heads.
- If no self-rating has started and the evaluation is still early, HR may cancel the old assignment with an audit record and assign the employee to the new department's appropriate Annual template, if eligible.
- If the employee has already started or submitted the self-rating, HR should decide whether the old department's head completes that evaluation for the time/role covered, or whether it is closed and a new evaluation is issued. Do not overwrite the original entry or mix old- and new-department peer ratings into one average.
- Keep the employee in the correct peer cohort for the evaluation period. Any removal from the old cohort or addition to a new cohort should be explicit and audited, not an automatic side effect of changing the employee's current department.
- Avoid issuing two full Annual evaluations for the same period unless HR explicitly defines a transfer-review policy for that case.

**Policy decision still needed:** Define when transfers are early enough to cancel/reassign, when the previous department should complete a review, and whether a transfer-date evaluation is required when the move happens mid-cycle.

## Implementation and testing

- Open **Annual & 360 Ratings** from the portal menu. HRD uses its HRIS accounts at `manager/annual-evaluations.php`, `supervisor/annual-evaluations.php`, and `staff/annual-evaluations.php`. Other department employees and President/CEO use `employee/annual-evaluations.php`. HRD Employee accounts can view their own anonymous results; their 360 rating tasks require HRIS access.
- Create Annual templates using the existing template pages. The eight standard Core Behaviors & Values and 80/20 split are supplied automatically. Framework identifiers are internal for Annual templates. Used Annual templates cannot have their criteria overwritten.
- Department cycles are keyed by department and the exact evaluation period dates. Use matching dates across the department's position-specific Annual templates. The roster includes active regular employees with applicable active Annual templates and supports late additions.
- New Annual self-ratings use the dedicated workflow; Initial/Final evaluations retain their existing package flow. No employee seeds or template seeds are imported by this feature.
- Schema is created on the first Annual workflow request or save. `database/zLAST_annual_peer_evaluations.sql` can also be imported manually and is safe to run repeatedly.
- KRA approval (October 19, 2026): the immediate head can adjust and approve KRAs independently while peer ratings are pending. Adjustments require remarks; original self-ratings are preserved. Each approval is recorded in `annual_kra_approvals` and the audit log. This is not final Annual approval.
- Finalization guard (updated October 19, 2026): all expected peers must submit before the immediate head can finalize or re-finalize. The button is disabled while responses are missing, and the server rejects bypass attempts even with remarks. Separated employees who never submitted self-rating are excluded from expected participation; submitted history is preserved. A one-person cohort uses its self-rating.
- Changed departments, positions, or non-Regular/inactive participants require HR review. The workflow preserves their existing entries and blocks further rating/finalization actions rather than automatically applying any proposed death, AWOL, or transfer policy.
- Automated integration test: `C:\xampp\php\php.exe tests/annual-evaluations.php`. It clones schema structures into a uniquely named test database, inserts fictional fixtures, tests controllers/scoring/privacy/locking/revisions, and deletes that isolated test database afterward. It does not import fixtures into the application database.

## Decisions still to document

Add further policy decisions and edge cases here. Proposed sections must be confirmed before their automatic handling is implemented.
