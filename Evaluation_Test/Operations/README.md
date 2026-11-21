# Operations: fictional employees and evaluation forms

Use this folder to import **this department's mock employee information and Initial, Final, and Annual evaluation forms**. Required external reporting heads are included automatically. **No user accounts are created.** You do not need the all-department `main_office_probationary` seed.

## Prerequisites (once per database)

Start MySQL in XAMPP. The existing database must already have base seeds **1-4**: schema, organization, HR demo accounts, and executive employee records. Follow [base setup](../README.md) only if they have not been imported. The department commands below do **not** reset the database. Do not rerun the schema on an existing database.

## Import this department

Paste this block into PowerShell. It imports employee information first, then Initial/Final and Annual templates, including their criteria. Employee Portal accounts are not required for import. Use `-p` if your MySQL root account has a password.

```powershell
cd C:\xampp\htdocs\raquel_hrd

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Operations/seed_employees.sql"
if ($LASTEXITCODE -ne 0) { throw 'Operations import stopped: seed_employees.sql' }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Operations/seed_initial_final.sql"
if ($LASTEXITCODE -ne 0) { throw 'Operations import stopped: seed_initial_final.sql' }


C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Operations/seed_operations_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw 'Operations import stopped: seed_operations_annual_dry_run.sql' }
```


## Mock employee information

| Company ID | Fictional employee | Status |
| --- | --- | --- |
| `MO26-OP-V` | Gabriel Mercado | Regular |
| `MO26-OP-M` | Monica Gonzales | Regular |
| `MO26-OP-H` | Edgar Valdez | Regular |
| `MO26-OP-P` | Jules Santiago | Probationary |
| `MO26-OP-P2` | Lorenzo Fajardo | Probationary |
| `MO26-OP-R` | Simon Solis | Regular |

Also uses the base CEO `CEO-001`.

Each department has two probationary employees, one regular staff sample, and its reporting heads; this does not populate every covered job position with an employee. Information includes names, employment status, position, department, branch, hire/regularization dates, contact details, and immediate-head reporting relationships.

After import, use **Admin > Portal Accounts** to create the employee and reviewer accounts yourself, including any executive Employee Portal accounts you need. You choose the credentials. The seeded forms remember their intended reviewer employee and are linked when that account is created through Admin. Forms can be imported before accounts; self-rating/review still requires the appropriate active accounts.

## Forms and workflow

- `seed_initial_final.sql`: two shared Active forms for the sample probationary position. Each has 8 KRAs (total 100%) and 8 Core Behaviors, with an 80% KRA / 20% Behavior split.
- Annual SQL files: role-specific templates and criteria for this department. See the [detailed department guide](Operations_Evaluation_Dry_Run.md) for role coverage and any later 2027 post-probation forms.
- No ratings, submissions, approvals, or automatic regularization are imported.
- Probationary employee: Employee Portal > Self Rating > Initial. Final follows full Initial approval. The sample Initial due dates are January 1 and January 15, 2027.
- Regular staff sample (`MO26-OP-R`) and eligible regular heads can use Annual. The Annual period begins on the later of January 1 or the employee's regularization date.

Base Annual period: **January 1-December 31, 2026**. Importing a form does not bypass employee eligibility or approval rules.

To import every department instead, use the separate [compiled all-department import](../Batch_Import_Department_Evaluation_Seeds.md).
