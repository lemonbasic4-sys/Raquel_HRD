# HR probationary evaluation test seeds

This batch creates a **fresh test database** with one President/CEO account (also able to use the Employee portal), three regular HR personnel (Elena Delgado, Patricia Gomez, and Miguel Torres) with both HRIS and Employee portal accounts, and six fictional probationary HR employees with Employee portal accounts. It also seeds **six active probationary evaluation templates**: Initial and Final for HR Staff, HR Supervisors, and HR Managers. It does **not** create employee evaluation submissions; submit those through the application to test eligibility and routing.

> **Destructive reset:** `database/1st_schema_tables.sql` starts with `DROP DATABASE IF EXISTS raquel_hris`. Export or back up anything you need first, and run this only against a disposable test database. Never run this batch against production.

With XAMPP MySQL running, paste these commands into PowerShell:

```powershell
cd C:\xampp\htdocs\raquel_hrd
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS raquel_hris CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run imports in chronological order
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/1st_schema_tables.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/2nd_seed_organization.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/3rd_seed_HR_accounts_.sql;"
# This supplies the three HRIS logins; the preceding file only supplies their employee records.
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/xPortal_accounts.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/test_hrd_portal_accounts.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/president_ceo_account.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/01_probationary_hr_employees.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/02_employee_portal_accounts.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/hr_personnel_templates.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/hr_final_probationary_evaluations.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/president_ceo_probationary_templates.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/03_verify_hr_probationary.sql;"

```

If your MySQL root account requires a password, add `-p` to each MySQL command; MySQL will prompt for it. Do not put the password in this file. These plain commands do not stop the batch automatically when an import fails, so check the MySQL output for errors and review the final verification rows. They should show three regular HRD employees with two accounts each, six probationary HRD employees with one portal account each, and nine HRD employees total **plus one President/CEO outside HR**. Import `xPortal_accounts.sql` **before** the HRD portal-account seed because it deletes existing Employee-role accounts for the three starters.

| Template creator | Target probationary employees | Initial | Final |
| --- | --- | --- | --- |
| President/CEO (`OP-001`) | 2 HR Managers | `president_ceo_probationary_templates.sql` | `president_ceo_probationary_templates.sql` |
| HR Manager (Elena Delgado) | 2 HR Supervisors | `hr_personnel_templates.sql` | `hr_final_probationary_evaluations.sql` |
| HR Supervisor (Patricia Gomez) | 2 HR Staff | `hr_personnel_templates.sql` | `hr_final_probationary_evaluations.sql` |

| Position | Portal usernames | Target rank |
| --- | --- | --- |
| HR Staff I / II | `HRD-111`, `HRD-222` | 5 |
| HR Supervisor I / II | `HRD-333`, `HRD-444` | 4 |
| HR Manager I / II | `HRD-555`, `HRD-777` | 3 |

The President/CEO portal account is `OP-001`. All six probationary portal accounts and the President/CEO account use the test password `password`. These are deliberately test-only credentials; never use this seed against a non-test environment. Staff report to seeded HR Supervisor Patricia Gomez (employee 301), supervisors to seeded HR Manager Elena Delgado (employee 101), and the two probationary HR Managers to the seeded President/CEO. The President/CEO uses one `President and CEO` role account for both its authorized workflow and Employee portal access.

For the visibility test, sign in as one probationary employee at each level and confirm they see only the Initial/Final forms targeting their rank. Submit through the Employee portal and confirm the review reaches the assigned immediate head. The templates alone do not create submitted evaluations or approve results.

## HR Staff Final regularization approval check

1. Sign in as `HRD-111` in the Employee portal, submit the HR Staff Final evaluation, then sign in as HR Supervisor Patricia Gomez and record **Recommend regularization** with a justification and signature. Approve the Final evaluation.
2. Verify the Final score appears in evaluation history while `HRD-111` still has **Probationary** employment status.
3. Sign in as HR Manager Elena Delgado. Open **Pending Approvals → Regularization Requests**. The request should show the score, supervisor justification, and a link to the full evaluation. If two active HR Managers exist, both see this same request—not separate copies.
4. Approve with an effective date. A date of today changes the employee to **Regular** immediately; a future date keeps them **Probationary** until that date. The system applies due approvals on the first subsequent authenticated page request.
5. For `HRD-222`, repeat the Final submission but reject the recommendation with a reason. The employee remains **Probationary**. A second manager attempting to decide either completed request must be refused.

The Initial evaluation workflow and the HR Supervisor/HR Manager probationary routes are unchanged by this HR Staff approval check.
