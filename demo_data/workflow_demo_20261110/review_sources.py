"""Summarize the existing seeds without importing or editing them."""
from pathlib import Path
import re, csv, hashlib
from build_demo import ROOT, HERE, positions

known={p['id']:p for p in positions.values()}
lines=['# Source inspection','', 'Reviewed against the current checkout on 10 November 2026. Existing files were read only.','',
'The older `Evaluation_test` directory referenced in the root README is absent. The available department fixtures are under `database/`. Their employee definitions were checked against the current organization seed rather than reused blindly.','',
'| Existing fixture | Employee tuples | Repeated IDs | Repeated codes | Position/department mismatches | Stored title mismatches |',
'| --- | ---: | ---: | ---: | ---: | ---: |']
files=['AP_seed.sql','Audit_seed.sql','BD_seed.sql','Compliance_seed.sql','Finance_seed.sql','GS_seed.sql','HR_seed.sql','IT_seed.sql','Marketing_seed.sql','OP_seed.sql','Operations_seed.sql','Purchasing_seed.sql']
for name in files:
    text=(ROOT/'database'/name).read_text(encoding='utf-8-sig')
    block=text.split('REPLACE INTO employees',1)[1].split('REPLACE INTO employee_contacts',1)[0]
    header=re.search(r'\(([^)]+)\)\s*VALUES',block,re.S)
    columns=[c.strip() for c in header.group(1).split(',')]
    rows=[]
    for line in block.splitlines():
        if not re.match(r'^\(\d+,',line.strip()): continue
        values=next(csv.reader([line.strip().rstrip(',;').strip()[1:-1]],quotechar="'",skipinitialspace=True))
        rows.append(dict(zip(columns,values)))
    ids=[r['employee_id'] for r in rows]; codes=[r['employee_code'] for r in rows]
    bad=labels=0
    for row in rows:
        pos=known.get(int(row['job_title_id']))
        if not pos or pos['dept']!=int(row['department_id']): bad+=1
        if pos and row['job_title']!=pos['title']: labels+=1
    lines.append(f'| `database/{name}` | {len(rows)} | {len(ids)-len(set(ids))} | {len(codes)-len(set(codes))} | {bad} | {labels} |')
lines+=['','These are inspection findings, not repairs. This demo imports none of those department fixtures. It builds a separate coherent roster using exact current position and department names.','',
'The only available SQL evaluation template fixture is `database/analytics_demo_seed.sql`. It uses broad department forms and does not establish the complete current probationary/package and peer-cycle state. It is not a prerequisite for this fixture.','',
'The base HR seed itself stores Miguel\'s title text as HR Staff I while position 711 is HR Staff II in the organization seed. This demo preserves his base row and targets his authoritative `job_title_id` for evaluation templates. New employee rows have matching title text, position, rank and department.','',
'## Workflow references','',
'- `database/1st_schema_tables.sql`: employee detail tables, enums, evaluation scores, probationary reviews, requests, packages, route steps and audit tables.',
'- `database/2nd_seed_organization.sql`: all branch, department, position, rank and position-parent references.',
'- `database/3rd_seed_HR_accounts_.sql`: preserved admin, Elena, Patricia and Miguel references.',
'- `database/zLAST_annual_peer_evaluations.sql` and `zLAST_quarterly_peer_evaluations.sql`: exact cycle, member, approval, peer and version definitions.',
'- `employee/self-rating.php`: Initial period ends at hire +3 months, Final at hire +5 months; separate managed Quarterly/Annual periods.',
'- `includes/functions.php`: `createOrganizationPackageRoute`, `recalculateEvaluationScores`, `applyOrganizationPackageResults`, `applyProbationaryEvaluationOutcome`, `applyDueStaffRegularizations`.',
'- `employee/team-evaluation-packages.php`: recommendation/signature checks and the final apply/lock step.',
'- `manager/pending-approvals.php`: legacy HR Staff regularization-request approval rules.',
'- `includes/annual-evaluations.php` and `quarterly-evaluations.php`: actual 80/20 calculators, roster eligibility, same-department peer authority, head review and cycle owner completion.',
'- `includes/employee-performance-analysis.php`: snapshot completeness and the shared finalized analytics SQL.',
'- `manager/analytics.php`, `manager/dashboard.php`, `includes/manager-hiring-analytics.php`, `manager/view-employee.php`: branch/department metrics, hire-year filters and employee history readers.','',
'## Important model decisions','',
'1. Initial/Final are individual reviews, not department 360 reviews. The current route builder generates one immediate-head step for these positions, with HR role-specific final review labels.',
'2. Same-rank non-HR parent positions fall back to CEO review for probationary evaluations in the actual route builder. The demo follows that fallback for Audit Manager II, Compliance Supervisor II and Focal Person I; their periodic KRA reviewers still follow their configured position parents.',
'3. Regular peer-cycle KRA is the sum of each item\'s individually rounded contribution. Core behavior is the eight-item average across self once plus submitted department raters. The final score is round(KRA × 0.8 + Core × 0.2, 2).',
'4. The CEO is excluded from the Office of the President evaluation roster by the application. That CEO can still review and rate direct-report assistants. The assistants therefore have two required roster peers plus a permitted same-department CEO head rating; snapshots preserve the actual expected/received counts.',
'5. The current code creates no regularization request (`INSERT` path absent). Two HR Staff rows demonstrate historical request compatibility, clearly labeled as synthetic history. All other new regularizations use the approved Final recommendation and employee effective-date fields, with no invented extra HR approval.',
'6. Peer submissions have no free-text feedback column. Narrative peer observations are stored in `audit_logs.details`; the existing peer tables and result views retain numeric item ratings.',
'7. No Board/Audit Committee stage is invented for a flow that does not use it. Initial/Final use their actual package routes; Quarterly/Annual use their separate department cycle engines.','',
'## Source hashes','', '| File | SHA-256 |','| --- | --- |']
for name in ['1st_schema_tables.sql','2nd_seed_organization.sql','3rd_seed_HR_accounts_.sql','zLAST_annual_peer_evaluations.sql','zLAST_quarterly_peer_evaluations.sql']+files+['analytics_demo_seed.sql']:
    path=ROOT/'database'/name
    lines.append(f'| `database/{name}` | `{hashlib.sha256(path.read_bytes()).hexdigest()}` |')
(HERE/'SOURCE_REVIEW.md').write_text('\n'.join(lines)+'\n',encoding='utf-8')
print('Inspected all 12 department fixtures and available SQL template fixture.')

