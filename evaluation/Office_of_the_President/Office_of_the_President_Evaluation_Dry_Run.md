# Office of the President Evaluation Dry Run

For mock employee information and all four form types, use [README.md](README.md). Import base seeds 1-4 once, then this folder's import block. No user accounts are created or required for import. Create employee/reviewer accounts manually using Admin > Portal Accounts afterward.

## Probationary Initial and Final

The employee seed supplies `MO26-EX-P`, `MO26-EX-P2`, their reporting heads, and regular staff sample `MO26-EX-R`. Shared forms `MO26-1101-INITIAL` and `MO26-1101-FINAL` each have 8 KRAs and 8 Core Behaviors. Initial due dates are January 1 and January 15, 2027. Once accounts exist, start at Employee Portal > Self Rating > Initial. Final follows full Initial approval; regular Quarterly follows Final approval and regularization. No scores, approvals, or regularization are seeded.

## Quarterly

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source evaluation/Office_of_the_President/seed_office_of_the_president_quarterly_dry_run.sql"
```

The base period is **2026-11-15 to 2027-02-14**. The same SQL adds a **2027-04-15 to 2027-07-14** cycle for Iris and Nina after Final approval and regularization.

## Annual

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source evaluation/Office_of_the_President/seed_office_of_the_president_annual_dry_run.sql"
```

The base period is **2026-01-01 to 2026-12-31**. The same SQL adds a **2027 Annual** cycle for the Executive Assistants after regularization. The portal starts an individual's Annual period on the later of January 1 or their regularization date. Annual self-rating requires an Approved Quarterly evaluation in that year.

Both scripts cover **Executive Assistant I, II, and III**. President and CEO is excluded because that position has no configured reporting head to review a self-rating. Each template has 8 executive-support KRAs totaling 100%, 8 Core Behaviors, and an 80% KRA / 20% Behavior overall split. The scripts add forms only: no scores, submissions, or approvals. A second import stops if the forms already exist.
