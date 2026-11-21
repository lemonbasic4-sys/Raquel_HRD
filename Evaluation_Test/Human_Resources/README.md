# Human Resources: fictional employees and evaluation forms

Use this folder to import **this department's mock employee information and Initial, Final, and Annual evaluation forms**. Required external reporting heads are included automatically. **No user accounts are created.** You do not need the all-department `main_office_probationary` seed.

## Prerequisites (once per database)

Start MySQL in XAMPP. The existing database must already have base seeds **1-4**: schema, organization, HR demo accounts, and executive employee records. Follow [base setup](../README.md) only if they have not been imported. The department commands below do **not** reset the database. Do not rerun the schema on an existing database.

## Import this department

Paste this block into PowerShell. It imports employee information first, then Initial/Final and Annual templates, including their criteria. Employee Portal accounts are not required for import. Use `-p` if your MySQL root account has a password.

```powershell
cd C:\xampp\htdocs\raquel_hrd

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Human_Resources/seed_employees.sql"
if ($LASTEXITCODE -ne 0) { throw 'Human Resources import stopped: seed_employees.sql' }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Human_Resources/seed_initial_final.sql"
if ($LASTEXITCODE -ne 0) { throw 'Human Resources import stopped: seed_initial_final.sql' }


C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Human_Resources/seed_human_resources_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw 'Human Resources import stopped: seed_human_resources_annual_dry_run.sql' }
```


## Mock employee information

| Company ID | Fictional employee | Status |
| --- | --- | --- |
| `MO26-HR-P` | Sofia Villanueva | Probationary |
| `MO26-HR-P2` | Celine Mata | Probationary |
| `MO26-HR-R` | Tessa Solis | Regular |

Uses the base HR demo employee records. Missing regularization dates are filled using hire date + six months; the base HR Manager is linked to the CEO if no head is set. Existing HRIS accounts remain unchanged.

Each department has two probationary employees, one regular staff sample, and its reporting heads; this does not populate every covered job position with an employee. Information includes names, employment status, position, department, branch, hire/regularization dates, contact details, and immediate-head reporting relationships.

After import, use **Admin > Portal Accounts** to create the employee and reviewer accounts yourself, including any executive Employee Portal accounts you need. You choose the credentials. The seeded forms remember their intended reviewer employee and are linked when that account is created through Admin. Forms can be imported before accounts; self-rating/review still requires the appropriate active accounts.

## Forms and workflow

- `seed_initial_final.sql`: two shared Active forms for the sample probationary position. Each has 8 KRAs (total 100%) and 8 Core Behaviors, with an 80% KRA / 20% Behavior split.
- Annual SQL files: role-specific templates and criteria for this department.
- No ratings, submissions, approvals, or automatic regularization are imported.
- Probationary employee: Employee Portal > Self Rating > Initial. Final follows full Initial approval. The sample Initial due dates are January 1 and January 15, 2027.
- Regular staff sample (`MO26-HR-R`) and eligible regular heads can use Annual. The Annual period begins on the later of January 1 or the employee's regularization date.

Base Annual period: **January 1-December 31, 2026**. Importing a form does not bypass employee eligibility or approval rules.

To import every department instead, use the separate [compiled all-department import](../README.md).

## Annual form coverage

The first Annual period is **2026-01-01 to 2026-12-31** for all 15 positions. The same SQL adds an **HR Staff I-V Annual 2027** form. Annual self-rating requires the employee's **regularization effective date**. The portal starts the Annual period on the later of January 1 or that date.

Each SQL adds three role-specific templates (HR Manager I-V, HR Supervisor I-V, HR Staff I-V) and the later HR Staff form. Each has 8 KRAs totaling 100%, 8 Core Behaviors, and an 80% KRA / 20% Behavior overall split. No scores or approvals are seeded. Reimporting a template stops with an error.

The current Regular HR employee records (`HRD-001`, `HRD-002`, `HRD-003`) have **no regularization effective dates recorded**. Record their true dates in HR before testing their Annual self-ratings. This seed does not invent or overwrite those dates.
