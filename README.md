# Raquel Pawnshop HR Workforce Analytics &
Evaluation Platform

Raquel Pawnshop HR Workforce Analytics &
Evaluation Platform is a role-based Human Resource Information System for managing employees, organization records, performance evaluations, career movements, approvals, reports, notifications, audits, and backups.

## Requirements

- XAMPP or another Apache, PHP 8+, and MySQL/MariaDB environment
- A web browser

## Setup

1. Place the project in your web server directory. For XAMPP:

   ```text
   C:\xampp\htdocs\Raquel_HRD
   ```

2. Start Apache and MySQL from the XAMPP Control Panel.

3. Create a MySQL database named `raquel_hris`.

4. Import these files in order:

   ```text
   database/1st_schema_tables.sql
   database/2nd_seed_organization.sql
   database/3rd_seed_HR_accounts_.sql
   ```

5. If necessary, update the database credentials in `config/database.php`:

   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'raquel_hris');
   ```

6. Open the application:

   - HR portal: <http://localhost/Raquel_HRD/>
   - Employee portal: <http://localhost/Raquel_HRD/employee/>

## Test data

Additional demo and workflow data is available in `database/` and `sample_db_seeds/`. Only import the files needed for your test scenario.

Seeded accounts may use `password` as their initial password. Change all default credentials before using the system outside local development.

## Main directories

```text
admin/       System administration
manager/     HR Manager portal
supervisor/  HR Supervisor portal
staff/       HR Staff portal
employee/    Employee self-service portal
includes/    Shared application logic
config/      Database configuration
database/    Schema and seed files
assets/      CSS, JavaScript, and images
```

## Cloudflare tunnel installation / powershell

Invoke-WebRequest `
  -Uri "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe" `
  -OutFile ".\cloudflared.exe"

.\cloudflared.exe --version

```powershell
.\cloudflared.exe tunnel --url http://localhost
```
