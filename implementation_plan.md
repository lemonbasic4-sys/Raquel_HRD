# Evaluation Historical Import Implementation Plan

## 1. Purpose

Import the organization's previous employee evaluations into the new HRIS without treating them as new active evaluation cycles.

Historical evaluations must:

- remain visible in employee and HR evaluation history;
- preserve the values shown on the previous evaluation form;
- remain printable using the existing performance evaluation form;
- be clearly identified as imported historical records; and
- remain outside the current self-rating, supervisor review, and approval workflow.

## 2. Source Of Truth

The existing printed evaluation form is the import reference. The import must support the information represented by that form:

- employee identity and position;
- department and branch;
- evaluation period;
- evaluation type;
- template/form code and revision information;
- KRA criteria, weights, ratings, and weighted results;
- behavior and values criteria and ratings;
- KRA subtotal;
- behavior average;
- total score;
- performance result;
- employee, supervisor, manager, and approval information;
- comments and remarks; and
- legacy control number or reference number, when available.

## 3. Import Modes

### 3.1 Summary Import

Use when the source contains only the final evaluation result.

Required columns:

```text
employee_code,evaluation_type,evaluation_period_start,evaluation_period_end,total_score
```

Optional columns:

```text
template_name,form_code,kra_subtotal,behavior_average,performance_level,employee_comments,supervisor_comments,manager_comments,legacy_reference
```

### 3.2 Detailed Import

Use when the source contains individual KRA and behavior ratings.

Evaluation summary file:

```text
employee_code,evaluation_type,evaluation_period_start,evaluation_period_end,total_score,kra_subtotal,behavior_average,performance_level,template_name,form_code,legacy_reference
```

Criterion score file:

```text
employee_code,evaluation_period_start,evaluation_period_end,criterion_name,criterion_section,criterion_weight,score_value,weighted_score
```

Allowed `criterion_section` values:

```text
KRA
Behavior
```

The system must map imported criteria to the selected system template. Criteria should be matched by a stable criterion code where possible. Name matching may be used only as a reviewable fallback and must never silently create an incorrect match.

## 4. Data Model Changes

Add historical metadata to `evaluations`:

```text
is_historical TINYINT(1) NOT NULL DEFAULT 0
record_source VARCHAR(40) NOT NULL DEFAULT 'System Workflow'
import_batch_id INT NULL
legacy_reference VARCHAR(150) NULL
historical_remarks TEXT NULL
```

Create `evaluation_import_batches`:

```text
import_batch_id INT AUTO_INCREMENT PRIMARY KEY
uploaded_filename VARCHAR(255) NOT NULL
import_mode ENUM('Summary','Detailed') NOT NULL
imported_by INT NOT NULL
imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
total_rows INT NOT NULL DEFAULT 0
successful_rows INT NOT NULL DEFAULT 0
failed_rows INT NOT NULL DEFAULT 0
status ENUM('Preview','Imported','Cancelled','Rolled Back') NOT NULL DEFAULT 'Preview'
error_log LONGTEXT NULL
```

Create `evaluation_import_errors` if row-level audit is needed:

```text
error_id INT AUTO_INCREMENT PRIMARY KEY
import_batch_id INT NOT NULL
row_number INT NOT NULL
employee_code VARCHAR(50) NULL
error_message TEXT NOT NULL
raw_data LONGTEXT NULL
```

Use foreign keys to the existing users and evaluations tables where practical. The import batch must remain auditable even after imported evaluation records are removed.

## 5. Employee Matching

Match employees in this order:

1. Exact `employee_code` match.
2. Reject the row if the code is missing or unmatched.
3. Do not automatically match by full name.
4. Show unmatched codes in the preview so HR can correct the source file.

Names may be displayed for confirmation, but they must not be the primary identity key.

## 6. Import Workflow

1. HR Manager opens Historical Evaluation Import.
2. HR downloads the summary or detailed CSV template.
3. HR uploads the completed file or files.
4. System checks file type, size, encoding, and headers.
5. System creates a preview batch.
6. System validates every row.
7. System displays valid rows, warnings, and rejected rows.
8. HR corrects the source file or confirms valid rows.
9. System starts a database transaction.
10. System creates the historical evaluation records.
11. System creates criterion score records when detailed scores are available.
12. System records the import batch and row results.
13. System commits only if the import completes successfully.
14. System displays an import summary and batch reference.

A failed import must roll back its database changes and preserve the error report.

## 7. Historical Evaluation Rules

Imported records should use:

```text
is_historical = 1
record_source = 'Historical Import'
status = 'Approved'
```

Historical records must:

- appear in HR evaluation history;
- appear in employee evaluation history;
- be available to the existing print evaluation page;
- show an Imported Historical badge;
- retain the original evaluation period and final result;
- be excluded from active package creation and current rating actions;
- not request employee consent again; and
- not generate current-workflow notifications.

Existing workflow queries and actions must explicitly protect records where `is_historical = 1`.

## 8. Template And Criteria Mapping

The system must allow HR to select the target evaluation template during preview when the source does not contain a valid form code.

Mapping rules:

- Prefer exact form code and revision match.
- Otherwise allow HR to select a template manually.
- For detailed imports, map each criterion to a target criterion.
- Require manual confirmation for unmatched criteria.
- Do not create duplicate criteria automatically.
- Preserve the original criterion name in the import audit data when a mapping is made.

If only summary values exist, the system may create the historical evaluation without score rows. The print page must still show the summary values and indicate that criterion-level scores were not supplied.

## 9. Validation Rules

Validate:

- required headers;
- UTF-8 CSV encoding;
- valid employee code;
- valid evaluation type;
- valid date format;
- start date is not after end date;
- numeric score values;
- score range expected by the form, normally 1.00 to 4.00;
- total score consistency where enough component data exists;
- KRA and behavior weight totals;
- valid performance-level labels;
- duplicate employee and evaluation-period combinations; and
- duplicate legacy references.

Warnings may be confirmed by HR. Identity errors, malformed dates, invalid scores, and missing required fields must block the affected row.

## 10. Security And Permissions

Only authorized HR roles should access the import feature. Recommended initial permission:

```text
HR Manager
System Administrator
```

The feature must use:

- CSRF protection;
- upload size limits;
- extension and MIME validation;
- server-side CSV parsing;
- prepared statements;
- escaped preview output;
- audit logging; and
- no direct execution of uploaded content.

Government IDs and other confidential values should not be imported through the evaluation import unless explicitly required by the printed form.

## 11. UI Components

Add a Historical Evaluation Import page with:

- import mode selector;
- template download buttons;
- file upload controls;
- template/form mapping controls;
- preview table;
- valid, warning, and rejected row counts;
- row-level error details;
- confirm import button;
- cancel button; and
- import batch history with rollback access.

Update evaluation history and print views to show:

```text
Historical Import
```

when `is_historical = 1`.

## 12. Rollback

Every import must be grouped by `import_batch_id`. HR should be able to roll back a completed batch after confirmation.

Rollback must:

- delete only evaluations created by that batch;
- delete their score and development-plan children through existing foreign-key behavior or explicit cleanup;
- mark the batch as `Rolled Back`; and
- preserve the batch audit and error information.

Do not allow rollback of records that were later modified through a normal workflow without an additional confirmation and audit policy.

## 13. Implementation Sequence

1. Confirm the client's source format and obtain representative historical forms.
2. Compare the source form fields with the current print page.
3. Add the historical metadata and import-batch schema migration.
4. Build summary CSV template generation.
5. Build upload, parsing, validation, and preview.
6. Build historical evaluation creation in a transaction.
7. Add detailed criterion-score import and template mapping.
8. Update history and print displays.
9. Add rollback and audit reporting.
10. Test with clean, incomplete, duplicate, unmatched, and mixed-format data.
11. Import a small pilot batch.
12. Reconcile pilot records against the original printed forms.
13. Import the remaining historical evaluations.

## 14. Acceptance Criteria

The feature is ready when:

- HR can download the correct import template;
- valid historical records import without manual database edits;
- invalid rows are clearly explained before import;
- employee matching uses employee code;
- duplicate imports are blocked or clearly warned;
- imported records appear in evaluation history;
- imported records can be printed using the existing form;
- imported records do not enter active evaluation workflows;
- imported records are visibly marked as historical;
- every import has an auditable batch record; and
- a completed batch can be rolled back safely.

## 15. Open Decisions

- Are the source records Excel files, CSV files, PDFs, scans, or printed forms?
- Do old records contain criterion-level ratings or only final scores?
- Do old forms use the same KRA and behavior criteria as the current templates?
- Is there a reliable employee code on every old form?
- Should historical forms display original signatures, names only, or approval text?
- Which HR role may import and roll back batches?
- Should the system allow historical records with no matching template, using a generic historical template?

---

# Progressive Department Shared Behavior Implementation Plan

> Planning only. No application code or database records have been changed. This section documents the proposed fix for progressive Shared Behavior across submitted employee evaluations in the same department cohort.

## 1. Purpose

Calculate Shared Behavior from the Individual Behavior scores of every participating employee in the same department, evaluation template, and evaluation period. Participation begins when an employee's self-rating is submitted and an Individual Behavior score is available. Package workflow status and package ownership must not remove an otherwise eligible employee from this population.

The calculation must work when employees are in separate packages because a prior package has already advanced through consolidation. For the inspected HR 2026 scenario, the expected score is:

```text
(2.63 + 2.75 + 3.13) / 3 = 2.836666... = 2.84
```

## 2. Inspected Current State

The live database contains these HR evaluations in department 7, template 1, period 2026-01-01 through 2026-12-31:

| Employee | Evaluation | Package | Workflow state | Individual Behavior |
| --- | ---: | ---: | --- | ---: |
| Elena Delgado, HR Manager | 2 | 1 | Package review pending | 2.63 |
| Patricia Gomez, HR Supervisor | 1 | 1 | Consolidation approved; manager review pending | 2.75 |
| Miguel Torres, HR Staff | 3 | 2 | Team consolidation pending | 3.13 |

All three submitted evaluations have eight Behavior score rows. Package 1 currently stores Shared Behavior 2.69; package 2 stores 3.13. Department team size is 3.

The existing `recalculateOrganizationPackageBehaviorScore()` in `includes/functions.php` averages eligible members from the package being recalculated plus members of matching prior packages whose status is `Approved and Applied`. Since package 1 is `Pending Review`, recalculating package 2 excludes Elena and Patricia. Package 2 then averages only Miguel's eight Behavior items: 3.125, rounded to 3.13.

The current calculation is scoped using package department, template, period, and membership. The consolidator is not an aggregation filter, though a package that has passed consolidation can cause a later submission to be placed in a separate package. Department membership is not currently the direct participant query for Shared Behavior.

## 3. Proposed Participation Rules

An employee participates when all of the following are true:

1. Their employee record belongs to the cohort department.
2. Their evaluation matches the cohort template and period.
3. Their self-rating has been submitted (not Draft, Pending Self-Rating, Returned, or Rejected).
4. Their Individual Behavior score can be computed from the Behavior items.

Do not require team consolidation, manager review, governance approval, or final application. Do not infer department membership from a package owner or consolidator. Do not require that the employee's evaluation share a package with the other participants.

Use the existing score override precedence when selecting an item's effective rating:

```text
manager override, otherwise supervisor override, otherwise department-manager override, otherwise submitted score
```

Compute each participating employee's Individual Behavior independently. Count each employee at most once for the cohort. If duplicate evaluations exist for the same employee and cohort, define a deterministic eligible evaluation selection and surface duplicates for review rather than counting both.

## 4. Proposed Calculation and Rounding

To match the provided workbook, use each participant's Individual Behavior score at two decimal places, average those employee values, then round the resulting department score to two decimal places:

```text
individual_behavior(employee) = round(average(effective Behavior item scores), 2)
shared_behavior = round(average(individual_behavior for unique participants), 2)
```

For the current HR cohort:

```text
round(average(2.63, 2.75, 3.13), 2)
= round(2.836666..., 2)
= 2.84
```

Do not use a prior Shared Behavior value as an employee input. Recompute from the Individual Behavior values of the participating employees. This avoids incorrectly treating the prior 2-person value of 2.69 as one person's score and avoids dependence on package arrival order.

## 5. Score Boundaries to Preserve

- **Individual KRA:** unchanged.
- **Individual Behavior:** remains each employee's own average across their Behavior items.
- **Individual Score:** remains `(Individual KRA × 80%) + (Individual Behavior × 20%)` for the configured weights.
- **Final Score:** remains `(Individual KRA × 80%) + (department Shared Behavior × 20%)` for the configured weights.
- **Department Team Size:** remains the active department employee count; it is not the participating employee count or package member count.
- **Workflow and approvals:** unchanged. Participating in Shared Behavior does not finalize an evaluation or add it to final analytics.

## 6. Proposed Update Flow

1. On a submitted self-rating or an adjustment that changes an Individual Behavior item, identify the cohort from department, template, and period.
2. Query all eligible submitted evaluations for that cohort across packages, independent of package workflow status.
3. Compute per-employee Individual Behavior and the unique participant count.
4. Compute the department Shared Behavior using the rounding policy above.
5. Persist the provisional Shared Behavior consistently for relevant open packages in that cohort so separate package cards do not show separate department populations.
6. Keep final score application and analytics governed by their existing finalization flow. Do not broaden analytics changes as part of this fix.

The existing finalization path that applies package results and propagates progressive values should be reviewed for compatibility with the cohort calculation. Change it only if required to ensure final scores use the same department Shared Behavior; do not change unrelated approval or analytics behavior.

## 7. Likely Code Locations

### Primary calculation

- `includes/functions.php`
- `recalculateOrganizationPackageBehaviorScore()` (currently around line 3684): replace package-local plus approved-package aggregation with cohort-wide eligible Individual Behavior aggregation and consistent persistence to the relevant open cohort packages.

### Recalculation triggers to verify

- `syncEvaluationToOrganizationPackage()` in `includes/functions.php`: called as evaluations are added to packages, including standalone package creation for late submissions.
- `syncWaitingOrganizationPackages()` in `includes/functions.php`: recalculates when a waiting package is unlocked.
- Package member score adjustment and return paths in `employee/package-member-review.php` and `employee/team-evaluation-packages.php`: ensure a changed or removed participant triggers cohort recalculation.
- Consolidation-time recalculation in `employee/team-evaluation-packages.php`: keep if needed for score adjustments, but it must call the same cohort calculation.

### Display and score consumers to verify

- `employee/team-evaluation-packages.php`: displays the package Shared Behavior and calculates estimated final scores from it.
- `employee/package-member-review.php`: reads package Shared Behavior for the estimated final score.
- `employee/team-evaluation-history.php` and `employee/evaluation-history.php`: inspect only to confirm they display consistent package values; avoid unrelated history or analytics changes.

The implementation should centralize calculation logic rather than implement different formulas in each caller or view. Views should continue to consume the persisted cohort score.

## 8. Validation Scenarios

Before release, verify at minimum:

1. **Current split-package case:** scores 2.63, 2.75, and 3.13 across two in-progress packages produce 2.84 in both relevant package views; participation count is 3; department team size is 3.
2. **Before staff submission:** manager and supervisor scores 2.63 and 2.75 produce 2.69; participant count is 2; team size remains 3.
3. **Workflow independence:** a submitted employee in pending consolidation or review participates; a draft, returned, or rejected evaluation does not.
4. **Package independence:** different consolidators or package IDs in the same department/template/period do not split the population.
5. **Cohort boundaries:** another department, template, or period is excluded.
6. **No duplicate counting:** an employee appearing through duplicate membership/query joins contributes once.
7. **Missing Behavior scores:** an employee without an available Individual Behavior score is not counted until the score becomes available.
8. **Score separation:** Individual Score still uses personal Individual Behavior; Final Score uses department Shared Behavior.
9. **Precision:** participant Individual Behavior values are rounded to two decimals, then the cohort average is rounded to two decimals, matching the supplied workbook.

## 9. Acceptance Criteria

- HR Manager, HR Supervisor, and HR Staff participate in the same HR department cohort once their self-ratings and Individual Behavior scores are available.
- The current three-person example produces Shared Behavior **2.84**, not **3.13**.
- A package's workflow status and consolidator do not determine department participation.
- Team size remains 3 independently of the 2- or 3-person participation count.
- Individual Behavior, Individual Score, KRA, finalization, approval workflow, and analytics retain their defined responsibilities.
- The calculation is cohort-wide and consistent across open packages in the same department/template/period.

## 10. Not in Scope

- KRA formula or criteria changes.
- Behavior item formula or score override precedence changes.
- Individual Score formula changes.
- Consolidation, review, governance, or approval workflow redesign.
- Changing department assignments or package ownership.
- General analytics, reports, or historical evaluation changes.
- Backfilling or rewriting existing finalized records unless separately reviewed and approved.

## 11. Approval Boundary

This document is a proposal only. No PHP, SQL schema, seed data, live evaluation data, or workflow settings have been modified. Implementation should begin only after this plan and the rounding rule are approved.
