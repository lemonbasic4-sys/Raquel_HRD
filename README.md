# Raquel Pawnshop HR Workforce Analytics & Evaluation Platform

A role-based Human Resource Information System for employee records, performance evaluations, approvals, career movements, reports, notifications, audit trails, and backups.

## Requirements

- Apache, PHP 8+, and MySQL/MariaDB (XAMPP for local development)
- A web browser
- Python only if you need to regenerate the evaluation template test seed

## Local setup

1. Place the project at `C:\xampp\htdocs\Raquel_HRD`.
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Back up any existing database before importing the schema. **`1st_schema_tables.sql` drops and recreates `raquel_hris`, deleting its existing data.**
4. Import these files in order using phpMyAdmin or the MySQL command-line client:

   ```text
   database/1st_schema_tables.sql
   database/2nd_seed_organization.sql
   database/3rd_seed_HR_accounts_.sql
   ```

   The third file includes the standalone administrator and HR demo records/accounts. Review the seed before importing it into an existing installation.

5. Check the database settings in [config/database.php](config/database.php). Local defaults are host `127.0.0.1`, username `root`, an empty password, and database `raquel_hris`.
6. Open the application:

   - HRIS login: <http://localhost/Raquel_HRD/>
   - Employee Portal: <http://localhost/Raquel_HRD/employee/>

For Windows command-line imports, pass `--default-character-set=utf8mb4` to `mysql.exe` to preserve names containing characters such as `ñ`. The schema creates the database automatically. Change seeded passwords before using the system beyond local testing.

## Portals

| Portal | Purpose |
| --- | --- |
| System Admin | Account management, portal access, security monitoring, audit trails, configuration, and backups |
| HR Manager | Employee management, evaluation templates, reviews, approvals, and reporting |
| HR Supervisor | Authorized employee records, templates, reviews, and approvals |
| HR Staff | Authorized HR operations and evaluation access |
| Employee | Self-service records, self-ratings, department ratings, evaluation results, and account settings |

Access depends on the account role, department, reporting relationships, and workflow permissions.

## Evaluation workflows and test data

The system supports Initial and Final evaluations for probationary employees, plus separate Quarterly and Annual evaluations for regular employees. Department cycles coordinate form readiness, self-ratings, department 360 ratings, head reviews, cycle approval, and history.

Use the department fixture instructions under [Evaluation_test](Evaluation_test/) for workflow dry runs. These are mock records intended for a disposable test database.

Reusable test forms are documented in [Evaluation_test/templates/README.md](Evaluation_test/templates/README.md):

- Import the selected department's employee fixture first.
- Run `Evaluation_test/templates/01_seed_templates.sql` once after the fixture.
- Run `Evaluation_test/templates/02_verify_templates.sql` and check that `invalid_forms` is `0`.
- Seeded forms contain 12 KRAs totaling 100%, eight standard Core Behaviors & Values, and the 80% KRA / 20% behavior split.
- Separate Initial, Final, Quarterly, and Annual forms are generated for the applicable test positions. The President/CEO has no self-evaluation form in this test setup.

These sample KRAs support workflow testing; use client-approved criteria for real evaluations.

## Recent interface updates

### Sidebar visibility preference

Profile Settings provides a desktop sidebar toggle for Admin, HR Manager, HR Supervisor, HR Staff, and Employee Portal accounts:

- **Fully hidden:** collapsing hides the sidebar and its icons, expanding the content area.
- **Compact:** collapsing retains a narrow navigation rail with visible icons.

Choose a mode and save the preference. The sidebar and content use matching opening and closing transitions; mobile retains its existing slide-out navigation.

The current implementation stores this preference per account in `users.sidebar_collapse_mode`, defaulting to `hidden`. The schema includes this column, and existing installations have an automatic column check. Browser-only preference storage has not been implemented. The expanded/collapsed state itself uses the browser's `sidebar_collapsed` local-storage key.

### Template viewing

Template library cards include a **View** action that opens read-only criteria and template details, including templates already used in evaluations. Authorized users at the same level can view shared templates. Existing edit permissions and protections for used templates still apply.

The template details page displays scoring criteria, KRA count, behavior count, and weight allocation in individual `page-hero fadeup` summary cards using the system's green and gold styling.

## Main directories

```text
admin/            System administration
manager/          HR Manager portal
supervisor/       HR Supervisor portal
staff/            HR Staff portal and shared template viewer
employee/         Employee self-service portal
includes/         Shared application logic and layouts
config/           Database configuration
database/         Schema, organization, and account seeds
Evaluation_test/  Department fixtures and evaluation test templates
assets/           CSS, JavaScript, images, and local libraries
backups/          Database backups
scripts/          Supporting scripts
misc/             Additional project documentation
```

## Basic checks after setup

1. Confirm HRIS and Employee Portal login pages load.
2. Sign in with the intended role and verify its permitted modules.
3. Save each sidebar mode in Profile Settings, collapse and reopen the sidebar, then refresh and navigate to another module.
4. Open a template through **View** and check its criteria and summary cards.
5. Follow the selected department fixture's evaluation dry-run instructions.

If the application cannot connect to the database, confirm MySQL is running and the credentials in `config/database.php` match the local installation.

justine gaming