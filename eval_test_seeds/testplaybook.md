## HRD, AP, Business Development, President/CEO, and Employee Portal test seeds

This imports organization and employee test data only. It does not seed
evaluation templates. Keep the import order below because the probationary and
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

No evaluation templates are imported. Create an **Initial** and a **Final**
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

