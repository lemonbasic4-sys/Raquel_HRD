# Base database import

Start MySQL in XAMPP. **The first SQL drops and recreates `raquel_hris`, deleting existing data. Use this block for a fresh setup only.**

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/1st_schema_tables.sql"
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/2nd_seed_organization.sql"
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/3rd_seed_HR_accounts_.sql"
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/4th_seed_executive_portal_accounts.sql"
```

After all four imports succeed, open the selected department's `README.md`, starting with [Acquired Properties](evaluation/Acquired_Properties/README.md). Its commands import mock employee information and Initial, Final, Quarterly, and Annual evaluation forms. They do not create user accounts; you create Employee Portal accounts yourself through Admin > Portal Accounts after import.

For the optional compiled import of **all departments at once**, use [evaluation/README.md](evaluation/README.md). That compiled block is separate from the department-by-department workflow.

Run the base setup once per fresh database. To add another department afterward, use that department's README directly.
