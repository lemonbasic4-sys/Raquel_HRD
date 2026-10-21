# System SWOT Analysis

**System:** Raquel Pawnshop HR Workforce Analytics & Evaluation Platform  
**Assessment date:** October 21, 2026  
**Basis:** Source-code review and observed development/testing workflows.

The platform's main strength is its detailed HR and evaluation workflow. Its main weakness is consistency: reporting definitions, behavior across roles, and deployment configuration need further refinement before wider rollout.

This is a code-based assessment, not a complete penetration test, load test, production-readiness certification, or validation of every department. Identified controls show that protections exist; their presence does not establish that every endpoint applies them correctly. Earlier UI and routing problems are treated as regression risks, not as proof that resolved defects still exist.

## Strengths

### S1. Centralized HR management

Employee profiles, organization records, performance evaluations, career movements, approvals, notifications, reports, audit records, and backups are available within one platform. This creates an opportunity to reduce repeated entry and fragmented records.

Evidence: [Project overview](README.md), and the `admin/`, `manager/`, `supervisor/`, `staff/`, and `employee/` modules.

### S2. Structured performance evaluation

Annual evaluation supports self-rating, immediate-head review, department peer ratings, developmental plans, signatures, and an 80% KRA / 20% behavior calculation. Rating validation enforces values from 1.00 to 4.00 and checks KRA weights. Peer submissions are locked after submission; provisional overall scores can update as additional ratings arrive.

The system also tracks department-cycle completion and reopening for additional participants.

Evidence: `annualValidateScores`, `annualCalculate`, `annualReviewAndRate`, `annualSubmitPeers`, and `annualUpdateCycleCompletion` in [annual-evaluations.php](includes/annual-evaluations.php).

### S3. Accountability and access-control foundation

The reviewed code includes role checks, account-status checks, password verification, login-attempt controls, CSRF-token verification, and audit logging. Immediate-head authorization is checked when reviews are submitted.

Evidence: [session-check.php](includes/session-check.php), [login handler](index.php), and [shared functions](includes/functions.php).

### S4. Reporting and recovery capabilities

Hiring trends, demographics, performance analytics, employee reports, and exports support HR administration. Database backup helpers support full, schema-only, and data-only snapshots, with success/failure notifications for scheduled backup attempts.

Evidence: [analytics.php](manager/analytics.php), [hiring analytics](includes/manager-hiring-analytics.php), [export-report.php](manager/export-report.php), and [backup-engine.php](includes/backup-engine.php).

### S5. Transaction safeguards and focused automated tests

Annual review operations use transactions, row locking, and review tokens to reduce partial saves and conflicts when ratings change during review. Existing tests cover annual calculations, review overrides, approved score updates, and autosave security behavior.

Evidence: [annual-evaluations.php](includes/annual-evaluations.php) and [tests directory](tests/).

## Weaknesses

### W1. Incorrect head-office versus Operations classification

The demographics query treats employees with a nonzero branch ID as Operations. Main Office also has a branch ID, so its employees are included in Operations even when their departments are HR, Finance, IT, or other support departments. This explains the observed **0 Support / 41 Operations** result.

The grouping needs an explicit organizational definition instead of relying on the presence of a branch ID.

Evidence: the workforce grouping, Support Department Distribution, and Operations Branch Distribution queries in [analytics.php](manager/analytics.php).

### W2. Inconsistent behavior across related pages

During development, review routing, notification destinations, tab alignment, signature presentation, and score labels required repeated corrections. This history indicates a need for regression checks across roles and evaluation types. It does not mean every previously reported issue remains unresolved.

### W3. Large files and repeated role-specific pages

Large PHP files combine queries, business rules, presentation, styles, and scripts. Similar employee-view pages exist for HR Manager, HR Supervisor, and HR Staff. This increases maintenance effort and the risk that equivalent pages receive different fixes.

Evidence: [functions.php](includes/functions.php), [analytics.php](manager/analytics.php), [manager employee view](manager/view-employee.php), [supervisor employee view](supervisor/view-employee.php), and [staff employee view](staff/view-employee.php).

### W4. Development database configuration

The current configuration uses the MySQL root user with an empty password. That is an unsuitable default for a deployment containing real employee data. Environment-specific credentials and a database account limited to required privileges would improve deployment readiness.

Evidence: [database.php](config/database.php). External exposure and actual server permissions were not tested in this review.

### W5. Backup timing depends on application activity in the reviewed path

The automatic backup check is invoked for Admin requests. This path alone does not guarantee that a backup runs at its configured time when no Admin is using the system. The reviewed failure path also advances the next scheduled run after a failed attempt; recovery behavior should be deliberately defined and tested.

Evidence: [header.php](includes/header.php) and [auto-backup-check.php](includes/auto-backup-check.php). An external operating-system scheduler, if separately configured, was not verified.

### W6. Test profiles have limited demographic variety

Hire dates now vary across 2014–2026, but the major-employee seed still uses the same birth date, Male gender, and Single civil status for all 41 employees. These are synthetic test-data limitations, not conclusions about the real workforce. They limit the usefulness of demographic demonstrations and leave some chart/filter cases untested.

Evidence: [major employee seed](test_database/major_employees_complete_profiles.sql).

### W7. Initial setup and incremental updates are easy to confuse

The base schema explicitly drops and recreates `raquel_hris`. Importing it to perform a small update would erase existing records. The dedicated test import guide documents this, but setup documentation and migration paths should consistently distinguish a fresh installation from an update.

The README also references `sample_db_seeds/`, while the reviewed workspace uses `eval_test_seeds/` and `test_database/`.

Evidence: [base schema](database/1st_schema_tables.sql), [README](README.md), and [test import guide](test_database/import_major_employees.md).

### W8. Analytics query volume grows with historical data

The performance trend code loops over available years and months, running an aggregate query for each month. More historical years therefore mean more queries during a page request. This is a scalability concern to measure, not evidence of a current production slowdown.

Evidence: the per-year monthly score-trend loop in [analytics.php](manager/analytics.php).

## Opportunities

### O1. Validate and extend workflows across departments

Use the department fixtures to validate reporting chains, cross-department immediate heads, probationary and annual routes, and late participants. A shared workflow can reduce department-specific exceptions once these cases are verified.

### O2. Turn existing records into actionable workforce information

Combine hiring trends with separations, regularization, staffing levels, and developmental-plan progress. Begin with agreed definitions for headcount, hires, Support, Operations, provisional scores, and completed evaluations so reports answer consistent questions.

### O3. Standardize common components

Reusable navigation, employee tables, review panels, filters, score displays, and notification links would reduce inconsistent behavior and make responsive improvements easier to maintain.

### O4. Broaden validation beyond individual calculations

Expand testing to cover complete role journeys: notification to review, self-rating to approval, final result to print form, and desktop to mobile layouts. Add varied demographic fixtures and multi-department scenarios.

### O5. Improve operational reliability

Use explicit database migrations, scheduled background jobs for backups and reminders, monitored failures, and tested restoration procedures. Aggregate monthly analytics in fewer queries and measure performance with a larger fixture before optimizing further.

## Threats

### T1. Unauthorized disclosure of employee information

The database stores government identifiers, contact information, disclosures, and financial details. Unauthorized access or exposed backups could cause significant harm. The backup engine writes inside the project directory; actual web-server protection of that directory was not verified.

Evidence: [employee profile schema](database/1st_schema_tables.sql) and [backup-engine.php](includes/backup-engine.php). This review does not establish that a breach or publicly accessible backup exists.

### T2. Rating disputes and reduced candor

Named peer ratings may discourage honest feedback or create interpersonal pressure. Overall scores that change after head approval may cause disputes unless provisional status, expected raters, score changes, and historical versions remain understandable. These are organizational risks, not confirmed user misconduct.

### T3. Service interruption or loss of recoverable data

A locally hosted installation depends on the host computer, network, database service, and backup storage. Hardware failure, downtime, or an unsuccessful restore could interrupt HR operations. Backup creation should be complemented by restoration testing and separate storage appropriate to the deployment.

### T4. Reduced adoption and trust

Incorrect charts, notification overload, or inconsistent review links can lead users to rely on spreadsheets and manual follow-ups. Wider department rollout increases the number of people affected by these issues.

### T5. Increasing workflow complexity

Transfers, multiple employees holding the same position, new regularized employees, changed reporting lines, and late ratings can create ambiguous routing or completion expectations. These scenarios need explicit business rules and representative tests before broad use.

## Recommended priorities

| Priority | Action | Expected result |
| --- | --- | --- |
| 1 | Correct the Support/Operations classification and document metric definitions. | HR reports reflect the actual organizational structure. |
| 2 | Review deployment credentials, backup access, scheduling, and restoration. | Better protection and recovery of employee records. |
| 3 | Validate annual and probationary journeys across departments and roles. | Fewer routing, authorization, and completion surprises. |
| 4 | Consolidate shared UI and workflow components. | Consistent behavior and easier maintenance. |
| 5 | Expand demographic fixtures and measure analytics with larger datasets. | More representative demonstrations and better evidence for performance changes. |
| 6 | Reconcile installation documentation and provide explicit update migrations. | Lower risk of accidentally resetting an existing database. |

## Review limits

- No full security audit, load test, backup restoration, or complete department acceptance test was performed for this SWOT.
- The planning documents shown in IDE tabs (`department_evaluation_testing_todo.md`, `annual_evaluation_design_notes.md`, and `anomaly_on_department.txt`) were not present at their root paths during inspection; their contents were not used as evidence.
- Existing test files were inspected, but the complete test suite was not rerun for this document.
- Strengths describe observed capabilities. Opportunities are proposed improvements, and threats describe plausible risks rather than confirmed incidents.
- This document records findings; it does not implement the recommended application changes.
