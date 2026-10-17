## HRD, AP, President/CEO, and Employee Portal test seeds

This imports organization and employee test data only. It does not seed
evaluation templates. Keep the import order below because the probationary seed
refers to employees created by the earlier HR, AP, and CEO seeds.


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
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/create_missing_employee_portal_accounts.sql;"
```

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

The configured immediate head should receive and accept/claim the evaluation,
then consolidate and sign it. Once the Initial is approved, the employee can
submit the Final evaluation for the same immediate-head review and final
probationary recommendation. Verify the actual reviewer against the reporting
lines in Position Management; if it differs, record the employee position,
configured Reports To position, and reviewer who received the evaluation.

