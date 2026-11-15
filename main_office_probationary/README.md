# Main Office probationary sample seed

This fictional test seed adds two probationary employees in each of the 12 departments at Main Office (branch 102), their immediate-head reporting chains and portal accounts, plus one shared Initial and one shared Final sample template per probationary position. It does not create evaluations or Quarterly forms.

## Fresh database import

Start MySQL in XAMPP. **The first command drops and recreates `raquel_hris`, deleting its existing data.** Back up anything you need before pasting this block. The Main Office seed itself does not reset the database.

Paste this in PowerShell from any directory:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source database/1st_schema_tables.sql"
if ($LASTEXITCODE -ne 0) { throw 'Schema import failed' }
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source database/2nd_seed_organization.sql"
if ($LASTEXITCODE -ne 0) { throw 'Organization import failed' }
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source database/3rd_seed_HR_accounts_.sql"
if ($LASTEXITCODE -ne 0) { throw 'HR account import failed' }
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source database/4th_seed_executive_portal_accounts.sql"
if ($LASTEXITCODE -ne 0) { throw 'Executive employee import failed' }
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source main_office_probationary/seed_main_office_probationary.sql"
if ($LASTEXITCODE -ne 0) { throw 'Main Office probationary import failed' }
```

The command's final output should list 24 probationary employees across all 12 departments, their immediate heads, and `2` templates per employee. The SQL rejects a repeated import or a conflicting `MO26-` Company ID instead of replacing an existing employee.

## Test accounts

For the new sample people's Employee Portal accounts, the username is the exact Company ID. Examples: `MO26-AP-P` and `MO26-AP-H`. The initial password is `password`, with a password change required at first login.

The existing HR immediate head uses `patricia.gomez` and her existing password. The seed creates an Employee Portal account for `AP-001` only when it does not already have one, using `AP-001` as the username. A CEO HRIS account, if created, uses `ceo.001` to leave `CEO-001` available for a separate Employee Portal account.

These templates use sample criteria for workflow testing. Confirm the actual role measures before using them for real evaluations.

## Start the probationary evaluation

Log in to the Employee Portal as a probationary employee, for example `MO26-AP-P` or `MO26-AP-P2`, then open **Self Rating** and choose the **Initial** form for AP Staff I. The employee submits the self-rating first; the immediate head (`MO26-AP-H`) receives the review afterward. The **Final** form is available only after the Initial evaluation is fully approved. Regular AP heads and the VP do not self-rate against these probationary forms.
