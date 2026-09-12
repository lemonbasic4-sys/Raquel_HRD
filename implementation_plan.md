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
