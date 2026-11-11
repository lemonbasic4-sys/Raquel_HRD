# Fresh testing database import

**Warning:** the first SQL resets `raquel_hris` and deletes existing data. Start MySQL in XAMPP first.

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/1st_schema_tables.sql"
if ($LASTEXITCODE -ne 0) { throw "Schema import failed" }
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/2nd_seed_organization.sql"
if ($LASTEXITCODE -ne 0) { throw "Organization import failed" }
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/3rd_seed_HR_accounts_.sql"
if ($LASTEXITCODE -ne 0) { throw "HR accounts import failed" }
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/4th_seed_executive_portal_accounts.sql"
if ($LASTEXITCODE -ne 0) { throw "Executive accounts import failed" }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/5th_seed_sample_evaluation_templates.sql"
if ($LASTEXITCODE -ne 0) { throw "Sample template import failed" }

Write-Host "SUCCESS YARN?" -ForegroundColor Green
```

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
