# Fresh testing database import

Run this block in PowerShell. Start MySQL in XAMPP first.

**This resets `raquel_hris` and deletes its existing data. Use it only when you intend to start fresh.**

```powershell
cd C:\xampp\htdocs\Raquel_HRD

& "C:\xampp\mysql\bin\mysql.exe" --default-character-set=utf8mb4 -u root -e "source database/1st_schema_tables.sql"
if ($LASTEXITCODE -ne 0) { throw "Schema import failed" }

& "C:\xampp\mysql\bin\mysql.exe" --default-character-set=utf8mb4 -u root -e "source database/2nd_seed_organization.sql"
if ($LASTEXITCODE -ne 0) { throw "Organization seed failed" }

& "C:\xampp\mysql\bin\mysql.exe" --default-character-set=utf8mb4 -u root -e "source database/3rd_seed_HR_accounts_.sql"
if ($LASTEXITCODE -ne 0) { throw "HR account seed failed" }

& "C:\xampp\mysql\bin\mysql.exe" --default-character-set=utf8mb4 -u root -e "source database/4th_seed_executive_portal_accounts.sql"
if ($LASTEXITCODE -ne 0) { throw "Executive portal seed failed" }

Write-Host "SUCCESS YARN! All four SQL files imported." -ForegroundColor Green
```

## Additional Employee Portal accounts

| Position | Department | Username | Temporary password |
| --- | --- | --- | --- |
| President and CEO | Office of the President | `CEO-001` | `password` |
| VP for Acquired Properties | Acquired Properties | `AP-001` | `password` |

Use **Employee Portal sign-in**, not HRIS sign-in. Both accounts require a password change on first login.

The fourth seed adds fictional employee records and portal accounts only. It does not create evaluation templates or evaluations. Existing Admin and HR accounts remain supplied by the third seed.

Import the fourth seed once per fresh setup. It deliberately rejects duplicate Company IDs and usernames instead of replacing existing employees.
