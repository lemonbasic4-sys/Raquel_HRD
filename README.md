 database/1st_schema_tables.sql
 database/2nd_seed_organization.sql
 database/3rd_seed_HR_accounts_.sql
 sample_db_seeds/01_test_employees.sql
 database/xPortal_accounts.sql
 database/data/seed_templates.sql
 sample_db_seeds/02_test_hrd_portal_accounts.sql
 sample_db_seeds/03_test_governance_approvers.sql
 (All portal accounts use default password: password)
 
 AP and HRD

----

System tech stack

- Backend: PHP (plain PHP pages and controllers). Uses mysqli for database access.
- Database: MySQL / MariaDB (database name: raquel_hris in config/database.php). Charset: utf8mb4.
- Frontend: HTML, CSS, Bootstrap 5 (CDN), Font Awesome (CDN), custom CSS in assets/css.
- JavaScript: Custom JS under assets/js (includes pjax.js), Chart.js (CDN) for charts, client-side features (offline banner, sound effects).
- Session & Auth: PHP sessions (session_start), role-based access (Admin, HR Manager, HR Supervisor, HR Staff, Employee), CSRF token generation used in header.
- Deployment / Hosting: Repository includes Web deployment considerations and a homepage set to https://raquel-hrd-three.vercel.app (Vercel). Default branch: apk-test.
- Mobile / APK support: APK conversion guide (complete_apk_conversion_guide.md), WebView-friendly features (offline handling, mobile CSS).
- Utilities & Features: Auto-backup, audit trail, notifications, PJAX partial page loads, sample DB seeds in database/ and sample_db_seeds/.
- Environment: Timezone set to Asia/Manila in config/database.php.

Security & setup notes

- config/database.php currently contains example local credentials (DB_HOST=localhost, DB_USER=root, DB_PASS=''). Update credentials and secure this file before production.
- No license file detected in the repository metadata — consider adding a LICENSE.

Files referenced

- config/database.php — database connection and timezone
- includes/header.php, includes/functions.php — UI components, notifications, role-based menus
- assets/css/, assets/js/ — frontend assets
- database/ and sample_db_seeds/ — schema and seed SQL files