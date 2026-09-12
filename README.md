# Raquel Pawnshop HRIS

A full-featured Human Resource Information System for Raquel Pawnshop, built with PHP and MySQL. The project includes a dedicated HRIS portal for HR/admin users and a separate Employee Self-Service Portal for staff and employee accounts.

## Overview

This system manages:

- employee master data and profile setup
- employment and branch assignments
- HR role-based access and user accounts
- performance evaluation and self-rating workflows
- governance approval chains and review packages
- notifications, audit trails, and backup utilities
- employee portal features for profile and evaluation-related tasks

## Current system structure

- HRIS portal login: `index.php`
- Employee portal login: `employee/index.php`
- Shared configuration: `config/database.php`
- Core helpers and business logic: `includes/functions.php`
- Shared UI components: `includes/header.php`, `includes/footer.php`
- Admin/HR portals: `admin/`, `manager/`, `supervisor/`, `staff/`
- Employee portal modules: `employee/`
- Assets: `assets/css/`, `assets/js/`, `assets/img/`
- Database schema and seed files: `database/`, `sample_db_seeds/`

## Tech stack

- Backend: PHP 8+ with native `mysqli`
- Database: MySQL / MariaDB
- Frontend: HTML, CSS, Bootstrap 5, Font Awesome, custom JavaScript
- Auth: PHP sessions, role-based access, CSRF validation, fail-safe login lockouts
- Timezone: `Asia/Manila`
- Default database name: `raquel_hris`

## Default environment configuration

The local database connection is configured in `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'raquel_hris');

date_default_timezone_set('Asia/Manila');
```

> Update the credentials before deploying to a production environment. Keep this file secure and do not expose live credentials in public repositories.

## Login portals

### HRIS Portal

- URL: `http://localhost/Raquel_HRD/index.php`
- Purpose: administrative HR and system-management functions
- Roles supported: Admin, HR Manager, HR Supervisor, HR Staff

### Employee Self-Service Portal

- URL: `http://localhost/Raquel_HRD/employee/index.php`
- Purpose: employee-only access for self-service features
- Roles supported: Employee

## Default login credentials

The seeded portal and employee accounts typically use:

- Username: varies by account
- Password: `password`

This is commonly used across the sample database seeds for local testing.

## Database reset and seed process

The project includes modular SQL setup files in chronological order:

1. `database/1st_schema_tables.sql`
2. `database/2nd_seed_organization.sql`
3. `database/3rd_seed_HR_accounts_.sql`
4. `sample_db_seeds/01_test_employees.sql`
5. `database/xPortal_accounts.sql`
6. `sample_db_seeds/02_test_hrd_portal_accounts.sql`
7. `sample_db_seeds/03_test_governance_approvers.sql`
8. `database/zLAST_performance_indexes.sql`

Example reset script:

```powershell
cd C:\xampp\htdocs\Raquel_HRD

& "C:\xampp\mysql\bin\mysql.exe" -u root -e "DROP DATABASE IF EXISTS raquel_hris; CREATE DATABASE raquel_hris CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/1st_schema_tables.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/2nd_seed_organization.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/3rd_seed_HR_accounts_.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source sample_db_seeds/01_test_employees.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/xPortal_accounts.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source sample_db_seeds/02_test_hrd_portal_accounts.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source sample_db_seeds/03_test_governance_approvers.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/zLAST_performance_indexes.sql;"
```

## Key features

- role-based HRIS dashboards for Admin, Manager, Supervisor, and Staff
- employee portal with dedicated login and employee self-service access
- performance evaluation and rating workflows
- governance-based approval routing
- employee movement and career progression tracking
- notifications and audit logs for system activity
- backup support and data restore utilities
- branded UI for Raquel Pawnshop operations

## Project folders

- `admin/` — admin and HR management pages
- `manager/` — HR manager modules
- `supervisor/` — supervisor workflows
- `staff/` — staff-specific features
- `employee/` — employee portal modules
- `includes/` — shared session, utility, and UI logic
- `assets/` — CSS, JS, images, and frontend assets
- `database/` — schema and core seed data
- `sample_db_seeds/` — testing and demo data
- `misc/` — conversion and operational documentation

## Security notes

- The app uses PHP sessions and CSRF validation for form submissions.
- Login brute-force protection is implemented through helper functions in `includes/functions.php`.
- Notifications and audit trails are used to monitor suspicious or important system events.
- Ensure the database and config files are not exposed in production.

## Notes

- This repository is intended for local development and testing with XAMPP / MySQL.
- The system is branded as `Raquel Pawnshop HRIS` and includes separate HRIS and Employee Portal flows.
- Additional documentation and setup notes may be found under `misc/` and the SQL seed folders.

## License

No explicit license file is currently included in the repository. If this project is being prepared for distribution or deployment, add a proper `LICENSE` file before release.
