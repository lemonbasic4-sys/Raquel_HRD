# Probationary Evaluation — Tailored Implementation Checklist

## Scope and agreed rules

This plan implements Initial/Final evaluations for probationary employees only. Annual evaluations are out of scope.

- HR Manager or HR Supervisor adds the probationary employee record.
- After the record is successfully added, Admin creates the Employee Portal account.
- Employee self-rates KRA and Core Values / Behavior. KRA item weights total 100% within the KRA section; the overall calculation uses KRA 80% and Core Values / Behavior 20%.
- The immediate head is the final rater. They may adjust the employee's self-ratings based on observation and choose Recommend regularization or Continue training with a reason.
- Keep the employee's original self-ratings and the immediate head's final ratings separately.
- The employee signs the self-rating and then signs/acknowledges the same final version as the immediate head after head review.
- Month 3: Initial evaluation. If continued training is recommended and approved, Month 5: Final evaluation.
- HR-department evaluations go from HR Supervisor (immediate head) to HR Manager for approval or return.
- For other departments, the immediate head submits the signed evaluation to the Department Manager. If the Department Manager approves, the system records the final score and sends the approved evaluation to both HR Supervisor and HR Manager as an indicator/record; HR does not add another approval step.
- The employee becomes eligible for regularization only after the final approver approves: HR Manager for HR-department employees, Department Manager for other departments. Eligibility is shown in the profile; it does not automatically change employment status to Regular.
- If the Department Manager (or HR Manager on the HR route) needs changes, they return the evaluation to the immediate head with comments.

## Implementation checklist

### 1. Employee setup and account provisioning

- Keep employee-record creation with HR Manager/HR Supervisor.
- After successful creation, make the new probationary employee available to Admin for Employee Portal account creation/linking.
- Confirm the portal account is linked to the correct employee, department, position, and employment status.
- Limit probationary navigation to Dashboard, My Employment, Self Rating, Evaluation History, Notifications, and Change Password; restore the regular employee menu when status becomes Regular.

### 2. Initial/Final self-rating

- Ensure probationary templates are Initial or Final and contain the correct job-related KRA and Core Values / Behavior items.
- Keep the 80/20 overall weighting and require KRA item weights to total exactly 100.00%, using integer hundredths on both client and server.
- Employee completes ratings and required justifications, signs/submits, and sees a clear Pending Immediate Head Review status.
- Notify the immediate head that the evaluation is ready for review.

### 3. Immediate-head review and employee acknowledgment

- Give the immediate head access to the full self-rating and justifications.
- Allow the immediate head to enter final ratings, including changing an employee rating; preserve both original and final values.
- Require a reason for the recommendation and record the selected outcome: Recommend regularization or Continue training.
- Record the immediate head's signature and notify the employee that the head review is complete.
- Show the employee the head-final ratings and require employee sign/acknowledgment of that version before it proceeds. If later edits change the reviewed version, invalidate the old acknowledgment and require a new one.
- Maintain a change/audit trail with actor, timestamp, old value, new value, and explanation.

### 4. HR-department approval

- Route HR probationary evaluations to HR Supervisor as immediate head, then HR Manager.
- Build the HR Manager review summary to show:
  - Employee identity, department, position, evaluation type, and period.
  - Employee original KRA/Behavior ratings beside immediate-head final ratings, with any changes highlighted.
  - Final weighted score calculated from the immediate head's final ratings.
  - Recommend regularization or Continue training, with the immediate head's reason.
  - Employee and immediate-head signatures/acknowledgments and the review history.
- HR Manager can Approve or Return to HR Supervisor with required comments.
- A return notifies the HR Supervisor and employee, preserves the prior version/history, and routes the revised version through employee acknowledgment again when applicable.
- On approval, finalize the evaluation and apply the selected outcome:
  - Recommend regularization: mark profile Eligible for regularization; leave employment status unchanged until HR separately updates it.
  - Continue training: schedule month-five Final evaluation and notify the immediate head and employee when due.

### 5. Other-department approval and HR notification path

- For AP and other departments, the immediate head remains the final rater and signs with the employee.
- Route the immediate head's signed evaluation to the Department Manager (the earlier term "Branch Manager" meant Department Manager) for approval. The Department Manager may approve or return it to the immediate head with comments.
- Only after Department Manager approval, calculate/record the final score and set profile eligibility when the recommendation is Recommend regularization.
- Notify both HR Supervisor and HR Manager with a link to the approved evaluation, final score, recommendation, rationale, signatures, and decision details as an indicator/record.
- HR Supervisor and HR Manager do not rate or approve this non-HR evaluation. Do not place it in their approval queue.

### 6. Probationary notifications and due dates

- Send the immediate head an Initial evaluation due notification in month 3.
- Send the employee a receipt/status notification after self-rating submission.
- Notify the employee when immediate-head review completes and employee acknowledgment is required.
- Notify the employee when the evaluation is pending HR Manager review, returned, or approved.
- Notify HR Manager when an HR-department evaluation awaits their decision.
- Notify the Department Manager when a non-HR evaluation is submitted for approval.
- After Branch Manager approval, notify both HR Supervisor and HR Manager that the non-HR probationary evaluation was approved and recorded; this is informational, not an approval request.
- If Continue training is approved, create the month-five Final evaluation follow-up and remind the immediate head and employee.
- Make notifications event-specific, linked to the evaluation, and prevent duplicate notices. Define overdue reminders/escalation before enabling the scheduled job.

## Validation and acceptance tests

- HR can add a probationary employee; Admin can then create/link that employee's portal account.
- Probationary account sees only the intended portal menu and can complete its own self-rating.
- KRA weights near 100% are handled consistently on the client and server.
- Immediate head can review, change ratings, explain the recommendation, and sign without overwriting the employee's original scores.
- Employee is notified and must acknowledge the head-reviewed version; edits invalidate prior acknowledgment.
- HR Manager sees the comparison, rationale, score, signatures, and can approve or return with comments.
- Approved regularization sets eligibility without changing employment status.
- Approved Continue training creates the month-five Final follow-up.
- Other-department submission goes to Department Manager for approval; return with comments reopens the immediate-head review. Only after approval are both HR Supervisor and HR Manager notified, with no HR approval stage.
- Verify reminders are based on the chosen probation date and do not duplicate.

## Decisions required before building scheduled reminders/signatures

- Should overdue reminders repeat or escalate, and to whom?

## Explicitly out of scope

- Annual evaluations, peer/360 Core Values scoring, and Annual routing changes.
- Automatic conversion of employment status from Probationary to Regular.
- HR Supervisor/HR Manager approval of other-department evaluations after Branch Manager approval; they receive an informational notice only.
