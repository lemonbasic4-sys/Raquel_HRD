# Validation results

Validated on **10 November 2026**, against **MariaDB 10.4.32** using the local XAMPP MySQL client and PHP executable. The final validation database is:

`raquel_demo_validation_20261110_185243`

Validated SQL SHA-256: `581a6bd8c730f284a692200a2d3d8116812636dd6f003365a74bacb71b57638b`.

The working `raquel_hris` database was not selected or imported into. No existing schema file, seed or application file was changed. The base SQL database token was rewritten only in memory for isolated validation; the PowerShell importer writes separate base copies instead.

## Actual results

| Check | Result |
| --- | --- |
| Three base seeds followed by the new SQL | Imported successfully |
| New employee count | 55; plus 3 original HR employees = 58 |
| Department coverage | 12 of 12 |
| Active branch coverage | 7 |
| Applicable profile-section coverage for new employees | 55 of 55 pass |
| Complete progression cohort | 54 of 54 have Initial, Final, regularization, Annual 2025 and Q1–Q3 2026 |
| Reviewer-only exception | CEO; no unsupported personal evaluation fabricated |
| Templates | 214; every one has 13 or 18 KRAs, KRA weights exactly 100, and 8 exact standard behaviors |
| Individual Initial/Final packages | 108 finalized packages with individual signed review and acknowledgement records |
| Real PHP probationary route builder | All 108 generated routes accept the saved reviewer; scratch route tests rolled back |
| Quarterly/Annual totals | All 228 match the actual PHP calculators and saved result snapshots |
| Immediate-head and peer authority | Actual PHP checks pass; SQL finds no invalid department or reporting relationship |
| Cycle-owner authority | All 48 pass actual owner checks |
| Actual cycle completion engine | All 48 stay complete without adding an unexpected late participant |
| Employee history reader | All 54 progression employees show 6 finalized evaluations and no pending result |
| Actual manager analytics filter | All 336 finalized evaluations are visible |
| SQL integrity checks | `TOTAL_INVALID_ROWS = 0` |
| PHP checks | **6,740 checks; zero failures** |
| Foreign keys | Enabled throughout the demo import and after validation |
| Original 4 user / 3 employee rows | Byte-for-byte unchanged before import, after import and after PHP checks |
| Second import | Correctly rejected by fresh-database/collision guard; 58 employees and 336 evaluations preserved |
| Original base seed files | SHA-256 unchanged after validation |

The final outputs are in `validation/import_log.txt`, `validation/verification_results.tsv`, `validation/php_workflow_results.txt`, `validation/source_hashes.json`, and the before/after base-row TSV snapshots. `validation/database_name.txt` identifies the final retained database.

## Report visibility and variation

| Evaluation type | Visible finalized records |
| --- | ---: |
| Initial | 54 |
| Final | 54 |
| Quarterly | 171 |
| Annual | 57 |
| Total | 336 |

| Supported category | Annual | Quarterly |
| --- | ---: | ---: |
| Outstanding | 6 | 18 |
| Exceeds Expectations | 42 | 124 |
| Meets Expectations | 5 | 17 |
| Needs Improvement | 4 | 12 |

Initial and Final examples all have Exceeds Expectations results: this is deliberately a successful regularization cohort. Subsequent regular results include weaker performance and development actions. All categories come from the existing `getPerformanceLevel()` thresholds.

The SQL verification output includes branch workforce counts, department workforce counts, each template's criterion counts, each new employee's dated evaluation timeline, the IT walkthrough, cycle counts, and branch/department score averages. The PHP validator uses the application's actual `finalizedPerformanceSql()` rather than treating `evaluations.status='Approved'` alone as proof of a completed result.

## Validation method

1. Refuse a preexisting validation database name; allocate a new `raquel_demo_validation_20261110_<time>` database.
2. Read the three required base files, rewrite their `raquel_hris` token in memory to that isolated name, and import them in order using UTF-8.
3. Save the original base employee/user rows and source hashes.
4. Import `01_fictional_workflow_demo.sql` without disabling foreign keys.
5. Run `02_verify_demo.sql` and the actual PHP calculators, authority checks, roster preview, cycle completion and employee/manager reporting readers.
6. Run the real probationary route builder against temporary validation packages within transactions and roll back those scratch records.
7. Compare base rows and source files again. Attempt a second import and confirm that its guard rejects it without changing fixture counts.

Earlier iteration databases were also isolated and are retained rather than dropped: `raquel_demo_validation_20261110_184109`, `raquel_demo_validation_20261110_184416`, and `raquel_demo_validation_20261110_184732`. Use **185243** for the final validated dataset. No database cleanup or deletion was performed.

## Limits

- These checks establish schema validity and compatibility with the current PHP data readers/calculators. They do not establish browser rendering, successful interactive clicks, PDF layout, email delivery or an actual person's consent/signature.
- The current application excludes the CEO from its own evaluation roster; a complete personal CEO workflow would require a business decision and application support.
- The current checkout has regularization-request decision code but no creation endpoint. The two HR Staff request rows are labeled synthetic historical compatibility examples; no end-to-end request submission is claimed.
- Peer narrative feedback is stored as labeled audit context because the peer submission tables contain no narrative column.
- The original HR seed's historic gaps and Miguel's title-text/position-ID inconsistency are preserved and documented in `SOURCE_REVIEW.md`; only newly added employees are asserted to have complete profiles and probationary histories.
- The fixture contains selected completed reporting periods, not every historic year. Hiring Analytics should be filtered to a represented hire year such as 2024; no fake 2026 hires or future-completed Annual evaluations were added merely to fill a current-year chart.

