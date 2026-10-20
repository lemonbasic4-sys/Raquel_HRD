## HRD, AP, Business Development, President/CEO, and Employee Portal test seeds

The base sequence below imports organization and employee test data only.
An optional HRD Annual template seed is provided in the Annual section below.
Keep the import order below because the probationary and
Business Development seeds reference employees and positions created earlier.


```powershell
cd C:\xampp\htdocs\raquel_hrd
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS raquel_hris CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run imports in chronological order
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/1st_schema_tables.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/2nd_seed_organization.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/3rd_seed_HR_accounts_.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/ap_complete_employee_profiles.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/president_ceo_complete_profile.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/probationary_hr_ap_complete_profiles.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/business_development_probationary_profiles.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/remaining_department_probationary_profiles.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/create_missing_employee_portal_accounts.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/hrd_annual_evaluation_templates.sql;"
```

The Business Development seed is optional. Import it after the organization
and President/CEO profile seeds, and before creating Employee Portal accounts.
It adds one regular Officer, one probationary Officer, one regular Staff member,
and one probationary Staff member. It follows the configured hierarchy:
Officer I reports to President/CEO; Staff I reports to Officer I. The seed uses
`REPLACE INTO` and relinks existing Employee accounts by employee-code username.
Because replacement can reset data linked to those employee rows, import this
seed before testing and do not rerun it if you need to preserve test history.

After the generic account-creation script runs, all four employees can sign in
to the Employee Portal using their employee code as the username and the test
password `password`.

The optional remaining-department seed adds Finance, General Services,
Information Technology, Marketing, and Operations test chains. It creates
regular reviewer accounts and probationary employees across each available
reporting level. Its usernames are the employee codes in
`remaining_department_probationary_profiles.sql`; the password is `password`.
Those accounts are created by the seed itself, and the generic account script
will skip them. Do not import the seed again after starting evaluations because
`REPLACE INTO` can reset data linked to the seeded employee rows.

The current creator/target guard may prevent VP Operations from selecting IT
Manager I as a probationary template target because IT Manager I is assigned to
the Information Technology department while VP Operations is in Operations.
That cross-department direct-report case needs to be resolved before the
`IT-P01` manager-level test can be completed; the IT Manager can still create
templates for its direct reports after regularization.

## Configure and create evaluation templates

After importing, review **Position Management** before testing templates:

- Make sure each active position has the correct immediate head selected in
  **Reports To**. This position relationship is the source for template targets.
- Confirm the test employees have the correct active positions and employee-level
  `reports_to` assignments for review routing.
- Confirm each immediate-head position has an active portal account to receive
  reviews.

The base sequence imports no evaluation templates. Create an **Initial** and a **Final**
probationary template through the portal for each required target position. The
creator selects a department and should only be offered active positions in that
department that report directly to the creator's position. The same rule applies
to HR; do not select targets merely because they share a rank.

Use these creator-to-target relationships when setting up the test templates:

- President/CEO → direct-report positions, such as HR Manager, VP General
  Services, VP Operations, Marketing Manager, Business Development Officer,
  VP Acquired Properties, and VP Finance.
- HR Manager → HR Supervisor positions that report to that HR Manager.
- HR Supervisor → HR Staff positions that report to that HR Supervisor.
- Each other department head → its own direct-report positions, as configured in
  Position Management (for example, AP VP → AP Manager → AP Supervisor → AP Staff).

Business Development template creators/targets are President/CEO to Officer I,
then the regular Officer to Staff I. The Employee Portal test accounts are:

- BD-T01: regular Business Development Officer (employee code is username)
- BD-P01: probationary Business Development Officer (employee code is username)
- BD-T02: regular Business Development Staff (employee code is username)
- BD-P02: probationary Business Development Staff (employee code is username)

## Expected evaluation results

### Annual workflow for regular employees

Use existing regular employees; no additional employee or template seed import is needed.

Optional shortcut: import `eval_test_seeds/hrd_annual_evaluation_templates.sql`
into the selected `raquel_hris` database after organization and Regular head
accounts are available. This creates three **test-only** Annual templates:
President/CEO → HR Manager, HR Manager → HR Supervisor, and HR Supervisor → HR Staff.
Targets come from current Position Management reporting lines. Each template has
four sample KRAs totaling 100%, eight standard Core Values, and an 80/20 split.
No employees, accounts, ratings, or submissions are created. Existing templates
are untouched and re-import skips already seeded templates. The file is not
automatically imported and is not part of the base/probationary seed sequence.

#### Import the optional HRD Annual templates

**After a fresh database import:** the base seed sequence does not create Annual
templates. HR Manager and HR Supervisor will have no available Annual self-rating
form until you import the optional template seed below or create forms manually.
Creating Employee Portal accounts alone does not create evaluation forms.

Back up any database you intend to restore first. Do not rerun the base schema or
employee replacement seeds simply to add these templates.

Run from the project root after importing organization and employee/account seeds:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
# Annual tables, including independent KRA approval history (safe to rerun)
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/zLAST_annual_peer_evaluations.sql;"
# Templates only — does not submit or rate any evaluation
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/hrd_annual_evaluation_templates.sql;"
```

Alternatively, select `raquel_hris` in phpMyAdmin and import those two files in
that order. The Annual schema also initializes when an Annual page first opens.

Expected template output:

| Template | Creator | Target positions |
| --- | --- | --- |
| `[TEST HRD ANNUAL] HR Manager` | President/CEO | HR Manager direct reports |
| `[TEST HRD ANNUAL] HR Supervisor` | HR Manager | HR Supervisor direct reports |
| `[TEST HRD ANNUAL] HR Staff` | HR Supervisor | HR Staff direct reports |

**Creator is not the person being evaluated.** President/CEO creates the HR
Manager's form, HR Manager creates HR Supervisor's form, and HR Supervisor creates
HR Staff's form. The seed does **not** create the President/CEO's own Annual
evaluation. That employee's immediate head/evaluator must be confirmed separately;
do not configure the President/CEO to approve their own evaluation.

The seed resolves IDs from the database and uses one active Regular head account
per creator role. Confirm the accounts have roles `President and CEO`,
`HR Manager`, and `HR Supervisor`, and the target positions are active and have
the correct **Reports To**. If the output has fewer than three templates, check
those prerequisites. The seed does not repair reporting lines or create accounts.
The KRAs are synthetic test content, not approved production job requirements.
Re-import leaves existing seeded templates and their criterion IDs unchanged.

#### Verify forms are available after import

1. Check that the seed's verification output lists all three template names above.
2. Sign in to the **Employee Portal** account of each Regular HR Manager,
   HR Supervisor, and HR Staff member. Open **Self Rating** and confirm the
   matching `[TEST HRD ANNUAL]` form appears. Use Employee Portal accounts for
   self-rating, not the HRIS reviewer screen.
3. Use HR Manager/HR Supervisor **HRIS** accounts for template management,
   direct-report consolidation, and department peer rating.
   HR Staff uses its HRIS Annual & 360 Ratings entry for peer rating.
4. If no form appears, verify the optional seed was imported into the intended
   database, the template is Active, the employee is active and Regular, and the
   employee's position is included in the template targets. Confirm the creator's
   position is the target position's configured **Reports To**.
5. A template is an available form, not a submitted evaluation. Annual cycles and
   reviewer progress appear only after employees submit their self-ratings. Do not
   import the seed expecting it to generate scores or completed evaluations.

#### Manual Annual workflow checks

1. Use the seeded templates above **or** create Annual templates manually for the configured direct-report positions. Do not create duplicate templates for the same test. Confirm eight standard Core Values, KRA item weights totaling 100%, and the fixed 80/20 split. No framework code/version entry is required.
2. Submit self-ratings from each Regular employee's Employee Portal using **identical start/end dates** for the department cycle. Employee count is dynamic, not fixed at three. Creating/importing templates alone does not create a cycle.
3. As HR Manager or HR Supervisor, open **Performance Evaluation → Regular Employee**. The Regular Employee section opens the department peer list. All eligible employees appear; only a direct report's **Consolidation & result** action allows KRA/Core Values review. The President/CEO reviews and finalizes the HR Manager. Your own result remains available separately through Evaluation History; its **Audit Details** opens the traditional view.
4. Open **360 — Department peers**. HR Manager/Supervisor access it inside Performance Evaluation; HR Staff uses the dedicated **Annual & 360 Ratings** HRIS entry. Non-HRD departments use the Employee Portal. All eligible department participants appear, including your row marked **You / Cannot rate yourself**. Coworkers cannot be rated until both employees submit self-rating for that cycle.
5. Rate the eight Core Values with numeric inputs from **1.00 to 4.00**. Immediate heads proceed through the **KRA → Core Behaviors → Review & Submit** tabs, compare each behavior with the employee's self-rating, draw a rater signature, and review the confirmation popup. Confirm submitted peer ratings are locked and self-rating/cross-department requests are rejected. On the printed evaluation form, verify the immediate head's signature appears in the **Rater** field.
6. As the immediate head, open **Consolidation & result** for a direct report. Review and, if needed, adjust both the KRA ratings and the eight combined Core Values scores in the same consolidation form. Add review remarks for adjustments, then use the single **Approve & finalize Annual evaluation** action after all expected peer ratings arrive. The reviewed values are saved in the finalized version and affect the 80/20 final score; original self-ratings and individual peer submissions remain preserved. Verify the employee receives an Annual finalization notification.
7. For an HR Supervisor viewing the HR Manager's evaluation, verify only the anonymous Core Behaviors & Values results appear. The supervisor cannot edit that review or finalize it; the President/CEO owns the HR Manager's final approval. The supervisor's submitted peer rating contributes to the manager's combined behavior scores.
8. Check the individual Core Values result: the employee's self-rating is included once with ratings received from coworkers. This is not a department-wide shared score. Named raters are visible only to the employee's immediate-head consolidator; department peers see anonymous labels.
9. While any expected peer response is missing, verify **Finalize Annual evaluation** is disabled and the server rejects direct submission even with remarks. After **all expected raters** submit, refresh and finalize as the immediate head. Final score = reviewed KRA × 80% + reviewed individual Core Values result × 20%. Finalized scores and versions must be preserved.
10. For a late-participant test, first complete all expected responses for the original roster and finalize a result. Then add a new eligible Regular department participant and have them submit the same-period Annual self-rating. Coworkers can rate the newcomer, and the newcomer rates coworkers. A late rating on a finalized result must flag **Revision pending**, keeping the old official score until the immediate head reviews and re-finalizes. All newly expected peer responses must be present before re-finalization. Do not bypass this guard by leaving an original rater outstanding.
11. For each **Reason for Separation** (AWOL, Retirement, Death, Permanent or Total Disability, Resignation, Failed in Training, Termination for Cause, Others), save separation on an employee who has **not** self-rated. Refresh: they must disappear from active peers and expected-rater counts, without deleting their roster record or generating zero scores. Existing submitted evaluations/ratings remain preserved for HR review; separation after submission is not automatically discarded.
12. Check desktop and mobile navigation, category tabs, KRA/Core Values adjustment, the single final approval action, confirmation dialogs, and locked/finalized states. No duplicate empty-state messages should appear.

Run the isolated automated checks without importing any test data into your working database:

```powershell
& "C:\xampp\php\php.exe" tests/annual-evaluations.php
```

See `annual_evaluation_design_notes.md` for confirmed rules and proposed HR exception policies.

### Notification clearing regression check

The notification inbox uses the same compact list/detail layout for Admin,
HR Manager, HR Supervisor, HR Staff, and Employee Portal accounts. Categories
adapt to the account role through the category dropdown.

1. Open Notifications in each role. Search, category, date, and read-status
   filters should work together and retain their values when opening details.
2. With many notifications, the inbox list and detail pane scroll independently
   on desktop. The page should not grow into a long list of reminders.
3. Repeated probation reminders with the same title are collapsed into a group
   for the current page. Expand it to see individual employee notifications.
   Pagination still counts individual notifications, not groups.
4. Select a notification and verify its full message, timestamp, related-page
   action, and read state. The unread count should update without reloading.
5. Select several rows or use **Select page**; check **Mark read** and **Delete**.
   Only the signed-in account's selected notifications should be affected.
6. On a narrow screen, selecting an item opens its detail view. **Back to
   notifications** should return to the list with filters and selections intact.
7. Check widths of 320, 375, 768, 1024, 1366, and 1920 pixels, including
   landscape phones, browser zoom, and the desktop sidebar expanded/collapsed.
   The inbox switches panels based on its available content width. Confirm no
   horizontal page overflow, clipped filters, overlapping actions, or inaccessible
   pagination. Long titles and messages must wrap; desktop panels should scroll
   independently and narrow layouts should offer Back to notifications.

Probationary due/overdue reminders keep a delivery record in
`notification_reminder_deliveries`, separate from the `notifications` inbox.
The table initializes automatically when reminders run or notifications are deleted.
After a reminder appears, delete it and refresh/reopen the staff, supervisor, or
employee portal. The same reminder must not reappear. A new milestone (for example,
Due Soon becoming Overdue) can still generate a new notification. Existing inbox
rows are not automatically removed by this fix. Do not clear the delivery-history
table when clearing the inbox, because that would reset reminder deduplication.

### Probationary workflow

After creating the templates, sign in to each probationary employee account and
confirm the self-rating page offers the Initial evaluation for that employee's
position. The Final evaluation should become available after the Initial has
been approved.

Then sign in to each probationary employee account and confirm the self-rating page offers the appropriate Initial evaluation. The reporting head should see that evaluation in Performance Evaluation and be able to accept/claim it for consolidation.

Check that each review reaches the correct immediate-head level:

- HRD-P01 (probationary HR Manager): President/CEO
- HRD-P02 (probationary HR Supervisor): active HR Manager
- HRD-P03 (probationary HR Staff): active HR Supervisor
- AP-P01 (probationary AP Manager): active AP VP
- AP-P02 (probationary AP Supervisor): active AP Manager
- AP-P03 (probationary AP Staff): active AP Supervisor
- BD-P01 (probationary Business Development Officer): President/CEO
- BD-P02 (probationary Business Development Staff): regular Business Development Officer

The configured immediate head should receive and accept/claim the evaluation,
then consolidate and sign it. Once the Initial is approved, the employee can
submit the Final evaluation for the same immediate-head review and final
probationary recommendation. Verify the actual reviewer against the reporting
lines in Position Management; if it differs, record the employee position,
configured Reports To position, and reviewer who received the evaluation.

