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

Hire dates vary across 2014–2026, and the major-employee seed now includes Male and Female employees plus several education levels. Birth date and Single civil status are still uniform across all 41 records. These are synthetic test-data limitations, not conclusions about the real workforce. They leave age and civil-status chart/filter cases untested.

Evidence: [major employee seed](test_database/major_employees_complete_profiles.sql).

### W7. Initial setup and incremental updates are easy to confuse

The base schema explicitly drops and recreates `raquel_hris`. Importing it to perform a small update would erase existing records. The dedicated test import guide documents this, but setup documentation and migration paths should consistently distinguish a fresh installation from an update.

The README also references `sample_db_seeds/`, while the reviewed workspace uses `eval_test_seeds/` and `test_database/`.

Evidence: [base schema](database/1st_schema_tables.sql), [README](README.md), and [test import guide](test_database/import_major_employees.md).

### W8. Analytics query volume grows with historical data

The performance trend code loops over available years and months, running an aggregate query for each month. More historical years therefore mean more queries during a page request. This is a scalability concern to measure, not evidence of a current production slowdown.

Evidence: the per-year monthly score-trend loop in [analytics.php](manager/analytics.php).

### W9. CSV reimports still need broader data-protection checks

The CSV template and importer now support all six education levels. Blank education fields preserve existing qualifications, sample employee codes use department prefixes, and import instructions explain that Admin provisions login accounts separately. These changes passed PHP syntax checks; an end-to-end import test has not yet established their behavior against representative employee profiles.

Other profile sections still contain delete-and-reinsert operations. Reimporting a partial CSV can therefore remove information outside education. Education matching also needs tests for multiple degrees and renamed schools to establish when an import updates a record or adds another one.

Evidence: [CSV importer](manager/add-employee.php), [CSV preview](manager/ajax/preview-csv.php), [downloadable template](manager/download-sample.php), and [static sample](misc/sample_employees.csv).

### W10. Education analytics can count one employee in several categories

The Educational Attainment query counts distinct employees within each education level. An employee with Secondary, College, and Graduate Studies records can appear in all three categories. That does not represent one highest attainment per employee, and category totals can exceed the workforce count. The current single-education-record seed does not expose this case.

Evidence: the `employee_education` aggregation in [analytics.php](manager/analytics.php).

## Weakness remediation checklist

Unchecked tasks remain open. Checked tasks record completed changes, not proof that the whole weakness is resolved. P0 means before deployment with real employee data; P1 means before wider department rollout; P2 covers maintainability and measured performance improvements. Owners below are suggested roles, not assignments.

### P0: Protect records and recovery

#### W4. Deployment configuration | Open | Developer / system administrator

- [ ] Move credentials into environment-specific configuration and keep secrets out of version control.
- [ ] Replace the root connection with an application database account limited to required privileges.
- [ ] Review the runtime `SET GLOBAL max_allowed_packet` call so normal requests do not require server-administration privileges.
- [ ] Test login, employee updates, evaluations, and reporting with the restricted account. Use a separately controlled account for installation or migration work where required.

Completion evidence: the normal application runs without root privileges, and the tested deployment configuration contains no blank database password.

#### W5 / T1 / T3. Backup scheduling and restore | Open; deployment protections unverified | System administrator

- [ ] Verify whether an operating-system scheduler already exists. If absent, configure backups independently of Admin page visits.
- [ ] Define retry behavior for failed backups and test failure notifications.
- [ ] Verify that unauthenticated requests cannot download backup files; move storage outside the web root or enforce access restrictions.
- [ ] Define retention and keep a recovery copy separate from the application host.
- [ ] Restore a backup into an isolated database and check employee records, accounts, evaluations, and relevant uploaded files.

Completion evidence: a backup runs without an active Admin session; a simulated failure is reported; an isolated restore succeeds. Record the test date and recovery procedure.

#### W7. Safe installation and updates | Open | Developer

- [ ] Correct the README seed-directory reference.
- [ ] Separate fresh-install instructions from update instructions and label destructive schema imports clearly.
- [ ] Provide incremental migrations with preflight checks, backup instructions, and recovery steps.
- [ ] Test migrations against a populated test database and verify that unrelated employee and evaluation records survive.

Completion evidence: an existing installation can receive an update without importing the drop-and-recreate schema.

#### W9. Safe CSV reimports | Partially addressed | Developer / HR tester

- [x] Add Senior High School, Vocational, and Graduate Studies columns and import mappings.
- [x] Preserve existing education when education fields are blank or absent.
- [x] Synchronize sample CSVs, use department-prefix sample codes, and explain separate Admin account provisioning.
- [x] Add UTF-8 BOM handling to preview and import; pass PHP syntax checks.
- [ ] Define preserve, replace, and explicit-clear behavior for every related profile section before changing the remaining delete-and-reinsert operations.
- [ ] Test old and new templates, blank fields, duplicate Company IDs, multiple degrees, renamed schools, and repeated imports in an isolated database.
- [ ] Verify preview and final-import validation agree on required fields, accepted values, and rejected rows.
- [ ] Verify failed rows roll back all their changes and that success counts reflect committed records.
- [ ] Add a regression test that compares an existing complete profile before and after a partial reimport.

Completion evidence: partial imports preserve unrelated data, repeated identical imports do not create duplicate qualifications, and invalid rows leave no partial changes. These checks do not authorize automatic creation of privileged accounts.

### P1: Correct reports and verify workflows

#### W1. Support and Operations grouping | Confirmed query issue | HR process owner / developer

- [ ] Agree on the classification of Main Office departments, branches, and exceptional positions.
- [ ] Replace branch-ID-presence checks with the agreed organizational mapping across summary and breakdown queries.
- [ ] Test Main Office support employees, branch Operations employees, and unassigned records.

Completion evidence: each eligible employee belongs to one defined group, and group counts reconcile with the eligible workforce total.

#### W10. Educational attainment totals | Confirmed query issue | HR process owner / developer

- [ ] Decide whether the chart means highest completed attainment or all recorded education; document treatment of unfinished studies and missing data.
- [ ] If highest attainment is intended, select one qualifying level per employee using an explicit ordering.
- [ ] Add fixtures with several education records per employee and compare chart totals with the underlying employee list.

Completion evidence: chart labels match the calculation, with one employee counted once if the chart reports highest attainment.

#### W2 / T5. Role and department journeys | Regression coverage needed | HR testers / developer

- [ ] Test annual and probationary journeys for HR Manager, HR Supervisor, HR Staff, employee, and President/CEO where authorized.
- [ ] Verify notification and dashboard links open the correct category, employee, and review step.
- [ ] Cover cross-department heads, changed reporting lines, and multiple holders of a position.
- [ ] Verify blank head-rating overrides use employee defaults and submitted reviews remain locked.
- [ ] Test cycle completion, history placement, and a newly regularized participant joining after finalization.
- [ ] Check provisional notices, rater counts, final-score updates, and print-form names/comments against saved records.
- [ ] Verify unauthorized users cannot view or submit another employee's restricted review through a direct URL.
- [ ] Record results by role and department, including expected outcome, actual outcome, and any unresolved defect.

Completion evidence: the agreed role/department matrix passes with no unresolved blocking routing, authorization, or scoring defects. Earlier fixes alone do not establish this coverage.

#### W6. Representative demographic fixtures | Partially addressed | Developer / HR tester

- [x] Vary seeded hire dates, genders, and education levels.
- [ ] Add varied birth dates and civil statuses to synthetic profiles, keeping age and employment dates plausible.
- [ ] Add missing-data cases and multiple education records without changing real employee profiles for demonstration purposes.
- [ ] Verify chart counts and percentages against the fixture's expected results.

Completion evidence: fixtures exercise the intended age and civil-status categories, missing data, and highest-education rules with documented expected counts.

### P2: Reduce maintenance cost and measure performance

#### W3. Shared components and large PHP files | Open | Developer

- [ ] Identify duplicated review panels, employee views, navigation, and notification-link logic.
- [ ] Extract one shared component at a time, preserving role authorization and existing behavior.
- [ ] Separate analytics queries and evaluation business rules from page rendering where practical.
- [ ] Run role-specific regression checks after each extraction, including narrow-screen layouts.

Completion evidence: targeted pages share the same implementation and pass the same behavior checks; file-size reduction alone is insufficient.

#### W8. Analytics query volume | Measurement needed | Developer

- [ ] Measure query count and page response time with several years of representative test data.
- [ ] Replace per-month queries with grouped aggregates where the measurements justify it.
- [ ] Inspect query plans before adding indexes and compare old/new results for identical filters.
- [ ] Record before-and-after timings and an agreed response-time target.

Completion evidence: reported values remain unchanged and measurements demonstrate whether the target is met. No production slowdown has been established by this assessment.

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
