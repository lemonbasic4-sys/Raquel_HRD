# Fresh testing database import

**Warning:** the first SQL resets `raquel_hris` and deletes existing data. Start MySQL in XAMPP first.

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/1st_schema_tables.sql"
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/2nd_seed_organization.sql"
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/3rd_seed_HR_accounts_.sql"
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/4th_seed_executive_portal_accounts.sql"
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/5th_seed_sample_evaluation_templates.sql"

Write-Host "SUCCESS YARN?" -ForegroundColor Green
```

## Additional Employee Portal accounts

| Position | Department | Username | Temporary password |
| --- | --- | --- | --- |
| President and CEO | Office of the President | `CEO-001` | `password` |
| VP for Acquired Properties | Acquired Properties | `AP-001` | `password` |

Use **Employee Portal sign-in**, not HRIS sign-in. Both accounts require a password change on first login.

The fourth seed adds fictional employee records and portal accounts only. It does not create evaluation templates or evaluations. Existing Admin and HR accounts remain supplied by the third seed.

Import the fourth seed once per fresh setup. It deliberately rejects duplicate Company IDs and usernames instead of replacing existing employees.

## Sample evaluation forms

The fifth seed adds Active Quarterly and Annual forms, each with 10 KRA items (10% each), 8 standard behavior items, and overall weights of 80% KRA / 20% Behavior.

| Creator | Target positions |
| --- | --- |
| President/CEO (`CEO-001`) | HR Manager I-V and VP for Acquired Properties |
| Elena Delgado (`elena.delgado`) | HR Supervisor I-V |
| Patricia Gomez (`patricia.gomez`) | HR Staff I-V |
| AP VP (`AP-001`) | AP Manager I-IV |

Import once after the fourth seed. This creates templates only. AP Manager positions have forms ready, but this base setup does not yet include AP Manager employees/accounts. No form is created for the CEO because the CEO has no configured immediate head.

Revision Date and Effective Date are template metadata; set evaluation periods through the evaluation workflow.

## Sample form periods (individual workflow)

Quarterly sample: November 11, 2026 to February 11, 2027. Annual sample: January 1 to December 31, 2026, adjusted to each employee's recorded regularization date when applicable. These are editable sample dates, not hardcoded application rules. No department Start Cycle action is needed.

The fifth seed supplies explicit fictional regularization dates for the three mock HR employees. It does not calculate regularization dates for real employees.

If the old sample seed was already imported, do not reset the database or reimport the fifth seed. Apply the scoped repair once:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/repair_sample_individual_templates.sql"
```

The repair skips forms with submitted evaluations and leaves existing regularization dates unchanged.
