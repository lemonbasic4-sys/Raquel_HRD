# Source inspection

Reviewed against the current checkout on 10 November 2026. Existing files were read only.

The older `Evaluation_test` directory referenced in the root README is absent. The available department fixtures are under `database/`. Their employee definitions were checked against the current organization seed rather than reused blindly.

| Existing fixture | Employee tuples | Repeated IDs | Repeated codes | Position/department mismatches | Stored title mismatches |
| --- | ---: | ---: | ---: | ---: | ---: |
| `database/AP_seed.sql` | 21 | 0 | 0 | 0 | 0 |
| `database/Audit_seed.sql` | 17 | 0 | 0 | 0 | 0 |
| `database/BD_seed.sql` | 11 | 0 | 0 | 0 | 0 |
| `database/Compliance_seed.sql` | 14 | 0 | 0 | 0 | 0 |
| `database/Finance_seed.sql` | 23 | 1 | 1 | 0 | 0 |
| `database/GS_seed.sql` | 32 | 0 | 0 | 0 | 0 |
| `database/HR_seed.sql` | 18 | 0 | 0 | 0 | 0 |
| `database/IT_seed.sql` | 24 | 2 | 2 | 0 | 0 |
| `database/Marketing_seed.sql` | 12 | 0 | 0 | 0 | 0 |
| `database/OP_seed.sql` | 13 | 0 | 0 | 0 | 0 |
| `database/Operations_seed.sql` | 22 | 0 | 0 | 0 | 0 |
| `database/Purchasing_seed.sql` | 10 | 0 | 0 | 0 | 0 |

These are inspection findings, not repairs. This demo imports none of those department fixtures. It builds a separate coherent roster using exact current position and department names.

The only available SQL evaluation template fixture is `database/analytics_demo_seed.sql`. It uses broad department forms and does not establish the complete current probationary/package and peer-cycle state. It is not a prerequisite for this fixture.

The base HR seed itself stores Miguel's title text as HR Staff I while position 711 is HR Staff II in the organization seed. This demo preserves his base row and targets his authoritative `job_title_id` for evaluation templates. New employee rows have matching title text, position, rank and department.

## Workflow references

- `database/1st_schema_tables.sql`: employee detail tables, enums, evaluation scores, probationary reviews, requests, packages, route steps and audit tables.
- `database/2nd_seed_organization.sql`: all branch, department, position, rank and position-parent references.
- `database/3rd_seed_HR_accounts_.sql`: preserved admin, Elena, Patricia and Miguel references.
- `database/zLAST_annual_peer_evaluations.sql` and `zLAST_quarterly_peer_evaluations.sql`: exact cycle, member, approval, peer and version definitions.
- `employee/self-rating.php`: Initial period ends at hire +3 months, Final at hire +5 months; separate managed Quarterly/Annual periods.
- `includes/functions.php`: `createOrganizationPackageRoute`, `recalculateEvaluationScores`, `applyOrganizationPackageResults`, `applyProbationaryEvaluationOutcome`, `applyDueStaffRegularizations`.
- `employee/team-evaluation-packages.php`: recommendation/signature checks and the final apply/lock step.
- `manager/pending-approvals.php`: legacy HR Staff regularization-request approval rules.
- `includes/annual-evaluations.php` and `quarterly-evaluations.php`: actual 80/20 calculators, roster eligibility, same-department peer authority, head review and cycle owner completion.
- `includes/employee-performance-analysis.php`: snapshot completeness and the shared finalized analytics SQL.
- `manager/analytics.php`, `manager/dashboard.php`, `includes/manager-hiring-analytics.php`, `manager/view-employee.php`: branch/department metrics, hire-year filters and employee history readers.

## Important model decisions

1. Initial/Final are individual reviews, not department 360 reviews. The current route builder generates one immediate-head step for these positions, with HR role-specific final review labels.
2. Same-rank non-HR parent positions fall back to CEO review for probationary evaluations in the actual route builder. The demo follows that fallback for Audit Manager II, Compliance Supervisor II and Focal Person I; their periodic KRA reviewers still follow their configured position parents.
3. Regular peer-cycle KRA is the sum of each item's individually rounded contribution. Core behavior is the eight-item average across self once plus submitted department raters. The final score is round(KRA × 0.8 + Core × 0.2, 2).
4. The CEO is excluded from the Office of the President evaluation roster by the application. That CEO can still review and rate direct-report assistants. The assistants therefore have two required roster peers plus a permitted same-department CEO head rating; snapshots preserve the actual expected/received counts.
5. The current code creates no regularization request (`INSERT` path absent). Two HR Staff rows demonstrate historical request compatibility, clearly labeled as synthetic history. All other new regularizations use the approved Final recommendation and employee effective-date fields, with no invented extra HR approval.
6. Peer submissions have no free-text feedback column. Narrative peer observations are stored in `audit_logs.details`; the existing peer tables and result views retain numeric item ratings.
7. No Board/Audit Committee stage is invented for a flow that does not use it. Initial/Final use their actual package routes; Quarterly/Annual use their separate department cycle engines.

## Source hashes

| File | SHA-256 |
| --- | --- |
| `database/1st_schema_tables.sql` | `0c9c02282ba324c320b8df5988784d69201bebfebc40863a8129b29843fc9988` |
| `database/2nd_seed_organization.sql` | `09d37cbb53ba66a272c73722bf0d5ba6393eeb0104a7dae232fd6546a435d476` |
| `database/3rd_seed_HR_accounts_.sql` | `37f79792f65cf692ed47c09607a94db18b9d96ff7ebee39c072931cd9d3cc6e0` |
| `database/zLAST_annual_peer_evaluations.sql` | `7a5817c4f3fcf9fa36d8c09c3a09e9bb71e99ce5ce5df6849326e6668d5cf5d9` |
| `database/zLAST_quarterly_peer_evaluations.sql` | `e383b3e98c562be7e144557547861363310bc0851ee4ab9b4c576903f9241dc1` |
| `database/AP_seed.sql` | `fb2e80e0ab5e8455c2db9e8af90df83dd2af5b68af59598d04dd13412f534b64` |
| `database/Audit_seed.sql` | `e78c519e3c1d5238299700fe1d2352e7da7a085347ccaa6e31c0efb395ce8edf` |
| `database/BD_seed.sql` | `4ff74e1558808838a562f8df68873fe37837f42cde94728bf9f2e151204f3bc1` |
| `database/Compliance_seed.sql` | `59620db6b4ef980ee5a140ed11271de1beb897b24e5558e32247f81745d7a91c` |
| `database/Finance_seed.sql` | `14b84e2378585a3aa81a7bdd9f21197e5b0bd55fdbeadad6b79d62193dd3691f` |
| `database/GS_seed.sql` | `2486229f3ff52d68948e29843afcfde0cf85708c0f990ff6bd8c93c4874d965f` |
| `database/HR_seed.sql` | `f3e6436611e85635838ffe8915c4e061f08102ef1074a18389a2adb4b41756d8` |
| `database/IT_seed.sql` | `5f72c6a789cac86b893d75fecc1c304e2d79c5cd9a6035570c532599ec727388` |
| `database/Marketing_seed.sql` | `13b9ba5dbfd7796c392170b76622fe3ffd187b0631f8b96e7312bdd1b2b21b62` |
| `database/OP_seed.sql` | `964ba20ab0cb42bad2f730af2bcc22f2b97dc9424df8429b37ed81ebff37af3a` |
| `database/Operations_seed.sql` | `dfad6dd8e2d79a82b8f545beb2250efd652c9ded2a3fce02fd71a44f6016b3c7` |
| `database/Purchasing_seed.sql` | `eeee9148b611dfac69d48f5b09559b68c4bdb0763ca91f2ee9e86211665f6ddb` |
| `database/analytics_demo_seed.sql` | `aed8ee27d2c6ad6272c0708eb664230506170128c2cbe65348b6ee4c52f06797` |
