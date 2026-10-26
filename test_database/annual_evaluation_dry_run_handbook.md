# Annual evaluation dry-run handbook

Use this handbook to test the department Annual evaluation changes in the local HRIS. The forms and accounts described here are **test data**. The 11 KRAs in each sample form are generic prompts for checking the workflow and computation; they are not approved job requirements.

## Where the system stands now

Checked on **October 26, 2026**: the local `raquel_hris` database has **41 regular employees**, **40 active Annual test forms**, and **12 departments with zero form-readiness blockers**. Acquired Properties and Audit already have open cycles for **January 1 to December 31, 2026**. The other ten departments have their forms ready but no started cycle. No new cycle was started while preparing this handbook.

**You do not need to reset the database to start testing.** If you deliberately want a clean installation later, use [import_major_employees.md](import_major_employees.md) and back up the database before its destructive schema import. A backup made before the latest form import is at `C:\Users\justi\AppData\Local\Temp\raquel_hris_before_all_departments_20261026_212816.sql`.

## What changed

1. All departments now have an assigned Annual cycle owner. The owner sees the readiness table, selects **Period From**, **Period To**, and **Self-rating deadline**, and starts the cycle. An owner gets an action indicator when forms are ready to start and when a completed cycle is ready to approve.
2. Other department heads with direct reports can see the cycle setup **read only**. External immediate heads can see readiness for departments where they created the top employee's form. They cannot start or approve that department's cycle.
3. Before a department cycle starts, its Annual self-rating form and pending-template indicator stay hidden. Starting a cycle enrolls its regular roster and notifies Employee Portal accounts.
4. The immediate head reviews each employee's KRA. Department members rate one another's eight Core Behaviors & Values. A head outside the department reviews KRA only; that head does not give a cross-department 360 rating.
5. The cycle owner approves the cycle after all roster members have submitted, immediate-head reviews are approved, and expected department ratings are in. Approval moves the cycle to Annual Evaluation History and informs HR for non-HR departments.
6. An employee who becomes regular within an approved cycle's period can join it later. The cycle reopens for the new employee's self-rating, head review, and reciprocal department ratings. The owner must approve it again.

The employee's combined Core Values result includes their self-rating **once** and every received department rating. Final score uses **approved KRA × 80% + combined Core Values × 20%**. Scores before head approval or before all ratings arrive are provisional.

## Sign in as the cycle owner

On this local test dataset, Employee Portal usernames are the employee codes below. The seed's starting password is `password`; the first login may ask you to change it. For Human Resources, start and approve through the HR Manager **HRIS** account. Regular HR employees still use their Employee Portal accounts for self-rating.

| Department | Employees in cycle | Owner | Sign-in username | Starting state |
| --- | ---: | --- | --- | --- |
| Acquired Properties | 4 | AP Manager I | `AP-002` | Cycle already open |
| Audit | 4 | Audit Manager I | `AUD-001` | Cycle already open |
| Business Development | 2 | Business Development Officer I | `BD-001` | Ready to start |
| Compliance | 3 | Compliance Supervisor I | `COM-001` | Ready to start |
| Finance | 6 | VP for Finance | `FIN-001` | Ready to start |
| General Services | 4 | VP for General Services | `GS-001` | Ready to start |
| Human Resources | 3 | HR Manager I | `elena.delgado` (HRIS) | Ready to start |
| Information Technology | 3 | IT Manager I | `IT-001` | Ready to start |
| Marketing | 3 | Marketing Manager I | `MKT-001` | Ready to start |
| Office of the President | 1 | President and CEO | `OP-001` | Ready to start |
| Operations | 5 | VP for Operations | `OPS-001` | Ready to start |
| Purchasing | 2 | Procurement Supervisor I | `PUR-001` | Ready to start |

The CEO is **excluded from their own Annual evaluation** until an external KRA approver is decided. The Office of the President cycle contains Executive Assistant I (`OP-002`). The CEO starts that cycle and reviews the assistant.

## First cycle: follow these steps

Use Business Development as a short first example; it has two employees. Repeat the same steps for the other departments.

1. **Before starting:** sign in as `BD-002` and open **Self Rating**. Its Annual form should not be offered yet. Sign in as `BD-001`, open **Annual & 360 Ratings**, and find **Business Development Annual cycle**. The readiness table should show **Business Development Officer I** and **Business Development Staff I**, both **Ready**.
2. **Start:** as `BD-001`, select the period and self-rating deadline, use **Preview period**, then select **Start Business Development cycle and notify employees**. Confirm in the popup. Choose a deadline that is today or later. You cannot start an overlapping period or a second open cycle.
3. **Self-rating:** sign in as `BD-001` and `BD-002` in their Employee Portal accounts. Both should now see the Annual form in **Self Rating**. Submit all 11 KRAs and eight Core Values. If an employee receives a first-login password prompt, complete that step first.
4. **Head review:** sign in as the immediate head of each person. The President/CEO (`OP-001`, or the CEO work account where applicable) reviews `BD-001`'s KRA. `BD-001` reviews `BD-002`'s KRA and Core Values on the review screen. Check the saved head review and signature.
5. **Department 360:** each Business Development employee rates the other on the eight Core Values in **360 Rating — Department Peers**. A person cannot rate themselves. A confirmed rating locks. The CEO must not appear as a Business Development 360 rater.
6. **Check results:** open each employee's **View my score** or Evaluation History. Compare the KRA card, combined Core Values card, criterion ratings, and overall score. For two department members, each employee should receive one coworker rating; the self-rating is included once.
7. **Approve the cycle:** after every required task is complete, `BD-001` should see **Ready for Business Development Officer I approval** and a sidebar indicator. Select **Approve cycle**. The cycle should move to **Annual Evaluation History** as view only, and HR should receive an informational notification.

For Acquired Properties and Audit, **begin at step 3** because their cycles are already open. Do not try to start another 2026 cycle for either department.

## Reporting lines to check

| Person being evaluated | KRA reviewer | Core Values raters |
| --- | --- | --- |
| AP VP, Audit Manager I, Business Development Officer I, Compliance Supervisor I, Finance VP, General Services VP, Marketing Manager I, Operations VP, HR Manager I | President/CEO | People in the employee's own department |
| IT Manager I (`IT-001`) | VP for Operations (`OPS-001`) | IT employees only |
| Procurement Supervisor I (`PUR-001`) | GS Manager I (`GS-002`) | Purchasing employees only |
| Executive Assistant I (`OP-002`) | President/CEO (`OP-001`) | No other department peers; the CEO can provide the in-department head rating |
| Every other seeded position | The immediate head shown in the [roster table](import_major_employees.md#employee-portal-roster) | People in that employee's department only |

HR Manager and HR Supervisor perform their department review and 360 work in the HRIS portal. HR Staff use their HRIS **Annual & 360 Ratings** entry. Their Employee Portal accounts are used for self-rating and viewing personal results.

## Expected 360 counts

For a department with **N** enrolled employees, a typical employee should receive **N − 1** coworker ratings after everyone submits. The self-rating is included once in the combined Core Values result. Examples: Finance has six participants, so each expects five department ratings; Business Development has two, so each expects one. The Office of the President has one enrolled participant, so it has no other enrolled department peer. The CEO's head rating is handled separately from that peer count.

## Check late regularization after approval

Use a disposable test employee whose effective regularization date falls **inside** the approved cycle period. Do this after you have recorded the first approved scores.

1. Regularize the employee in their department and confirm their Employee Portal account and matching Annual form are available.
2. Open the department Annual page. The new employee should join the existing period, and the approved cycle should reopen for outstanding work. A date outside the period should not enroll that employee into the old cycle.
3. Submit the new employee's self-rating and have their immediate head review it. Existing employees rate the newcomer; the newcomer rates existing department members.
4. Confirm earlier submitted ratings remain saved, scores update from the new ratings, and the owner must approve the expanded cycle again before it returns to history.

Keep this test in the local fixture: changing a real employee's regularization date solely for a dry run would affect their records.

## Quick checks and issue notes

From PowerShell in `C:\xampp\htdocs\Raquel_HRD`, this command prints every department's roster size, owner, and readiness blockers:

```powershell
& "C:\xampp\php\php.exe" scripts/check-annual-readiness.php
```

This test checks all 12 departments in a disposable database. It does **not** start cycles in your working database:

```powershell
& "C:\xampp\php\php.exe" tests/annual-all-departments.php
```

When you find a problem, keep the list and send it together. For each item, record:

| Detail | What to write down |
| --- | --- |
| Account and department | Username, role, and department |
| Page | Full URL and the button or tab used |
| Cycle | Period From/To and whether it was open or approved |
| Expected | What you thought should happen |
| Actual | What happened, including the exact message or score |
| Evidence | Screenshot and, for scoring, the KRA and individual Core Values ratings |

Avoid another schema reset while collecting issues. The current database already has the forms and open AP/Audit cycles you can inspect.
