# Raquel HRD

Raquel HRD is a PHP-based Human Resource Information System built for a Raquel Pawnshop operational environment. It centralizes employee records, HR administration, evaluation management, employee self-service functions, and analytics in one multi-role platform.

This project is designed for local deployment in XAMPP/WAMP-style environments and uses MySQL as the data source. The system is organized into separate portals for different user types, with permissions enforced by session role checks and access-control functions.

## Overview

The application supports:

- Admin system management
- HR Manager operations and governance
- HR Supervisor oversight and validations
- HR Staff support workflows
- Employee self-service access
- Performance evaluation templates and approval routing
- Career movements, succession planning, and branch/department administration
- Notifications, audit trails, backups, and reporting

## Core technology stack

- Backend: PHP 8+ with mysqli database access
- Database: MySQL / MariaDB
- Frontend: HTML, CSS, JavaScript, Bootstrap 5, Font Awesome, custom CSS and JS assets
- Security: PHP sessions, CSRF validation, role checks, audit logging, secure credential handling
- Timezone: Asia/Manila
- Local environment: XAMPP / Apache + MySQL

## System architecture

The project is not a single monolithic app; it is a multi-portal HRIS with role-separated dashboards:

- `index.php` handles the main HRIS login screen and redirects based on role
- `employee/index.php` handles the separate Employee Self-Service portal login
- `admin/` contains system administration tools
- `manager/` contains HR Manager workflows
- `supervisor/` contains HR Supervisor workflows
- `staff/` contains HR Staff workflows
- `employee/` contains employee-facing modules and self-service features
- `includes/` contains shared logic, session checks, header/sidebar rendering, notifications, and helper functions
- `config/` contains the database connection configuration
- `database/` contains schema files, seed data, and performance optimization scripts
- `assets/` contains stylesheets, JavaScript, images, and frontend resources

## Main features

### HR and administration

- employee profile and account management
- branches, departments, positions, and organizational setup
- HR portal account creation and maintenance
- user management and role-based access control
- audit-trail monitoring and system oversight
- backup and restore operations
- admin configuration for system settings

### Performance evaluation system

- evaluation templates and criteria management
- KRA and behavior-based evaluation scoring
- performance package routing and approvals
- evaluation history for employees and HR users
- print-friendly evaluation output
- historical import support concepts and governance workflows

### Employee portal

- employee dashboard and profile views
- self-rating and evaluation participation
- PDS and employment-related information
- notifications and portal updates
- career movement requests and related flows

### Operational workflows

- career movements and progression tracking
- succession planning support
- pending approvals and validation queues
- analytics and dashboard reporting
- notifications for approvals, workflow updates, and system actions

## Role access overview

### Admin

- manages portal users and system configuration
- reviews audit trail and system security actions
- performs backups and maintenance
- manages core system setup and access settings

### HR Manager

- oversees evaluation governance
- manages templates and reports
- approves and monitors larger HR workflows
- handles employee and departmental administration

### HR Supervisor

- manages branch or department-level workflow oversight
- validates and endorses pending items
- supports employee administration within scope

### HR Staff

- supports daily employee administration and monitoring
- tracks evaluation packages and employee records
- handles workflow support tasks

### Employee

- logs into the Employee Self-Service portal
- updates profile-related information
- completes self-ratings and views evaluation results
- monitors pending tasks and notifications

## Project structure

```text
Raquel_HRD/
├── admin/                    # Admin portal screens
├── assets/                  # CSS, JS, images, fonts, frontend assets
├── config/                  # Database config and environment connection files
├── database/                # Schema and seed SQL files
├── employee/                # Employee portal screens and workflows
├── includes/                # Shared helpers, auth, headers, notifications, session logic
├── manager/                 # HR Manager portal screens
├── misc/                   # Miscellaneous files and utilities
├── staff/                   # HR Staff portal screens
├── supervisor/              # HR Supervisor portal screens
├── index.php                # Main HRIS login screen
├── employee/index.php       # Employee portal login screen
├── logout.php               # Session termination
├── README.md                # Project overview and setup guide
├── implementation_plan.md   # System implementation planning notes
└── ...
```

## Database and environment setup

### 1. Install dependencies

Use a local PHP + MySQL stack such as XAMPP or WAMP.

Required components:

- Apache / PHP
- MySQL / MariaDB
- PHP extensions used by the app
- Local project directory mapped to your web server document root

### 2. Configure the database connection

Open `config/database.php` and update the database connection settings to match your local environment:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'raquel_hris');

date_default_timezone_set('Asia/Manila');
```

The project is currently configured for a local database named `raquel_hris`. If your database name is different, update `DB_NAME` accordingly.

### 3. Import the SQL files

The repository includes a database folder with schema and seed scripts. For a fresh setup, import SQL in the appropriate order, usually beginning with the schema file and then the seed data.

Typical sequence:

```text
database/1st_schema_tables.sql
database/2nd_seed_organization.sql
database/3rd_seed_HR_accounts_.sql
```

Additional SQL files in `database/` and `sample_db_seeds/` may be used for template data, demo employees, governance approvers, and test accounts.

### 4. Start the application

Once the database has been imported and credentials are valid:

```text
http://localhost/Raquel_HRD/
```

or the equivalent URL matching your local document root.

For employee access:

```text
http://localhost/Raquel_HRD/employee/index.php
```

## Default credentials and seed data

The seed scripts in the repository are configured to create sample user accounts and portal data. The project notes indicate that the default portal password is:

```text
password
```

Use this only for local testing or development. Change passwords and tighten security before production use.

## Security considerations

This application contains sensitive HR and employee data. Before production use, review and harden the following:

- set strong database credentials in `config/database.php`
- disable or change default seeded passwords
- use HTTPS in production
- validate and sanitize all incoming form requests
- ensure CSRF protection stays enabled on all POST actions
- restrict admin-level access to trusted accounts
- review audit logs and notifications regularly

## Important notes about this project

- This system is custom-built for HR workflows and is not a generic open-source starter kit.
- It is tailored around a Raquel Pawnshop HRIS process modeled with employee, evaluation, department, branch, and approval flows.
- The repository contains both production-ready modules and sample seed data used for testing and demo development.
- Some files may still include implementation notes and draft planning artifacts, such as `implementation_plan.md`.

## Recommended development workflow

1. Start Apache and MySQL in XAMPP.
2. Ensure the database exists and import the schema/seed files.
3. Verify the credentials in `config/database.php`.
4. Access the main login page and test each portal role.
5. Validate employee, template, and evaluation workflows in a seeded development environment.
6. Back up the database before major changes.

## Repository notes

This repository currently includes:

- schema and seed scripts for employee, branch, and HR portal data
- custom admin and HR dashboards
- employee portal flows and evaluation workflows
- backup and audit functions
- analytics and reporting views
- sample data for testing different HR scenarios

## License

No explicit license file was found in the repository at the time of review. If this project is intended for broader reuse or distribution, a license should be added before publication.

## Summary

Raquel HRD is a full-featured HRIS for managing organization structure, employee records, portal access, evaluation processes, career movements, and reporting. It is built for a role-based enterprise workflow and is intended to run in a local PHP/MySQL environment during development and testing.
