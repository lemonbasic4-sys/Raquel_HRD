# Batch Import Jan Paul Gaming

```
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/1st_schema_tables.sql"
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/2nd_seed_organization.sql"
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/3rd_seed_HR_accounts_.sql"

Write-Host "SUCCESS YARN?" -ForegroundColor Green
```
