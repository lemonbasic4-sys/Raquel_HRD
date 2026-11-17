# Operations Evaluation Dry Run

For mock employee information and all four form types, use [README.md](README.md). Import base seeds 1-4 once, then this folder's import block. No user accounts are created or required for import. Create employee/reviewer accounts manually using Admin > Portal Accounts afterward.

## Probationary Initial and Final

The employee seed supplies `MO26-OP-P`, `MO26-OP-P2`, their reporting heads, and regular staff sample `MO26-OP-R`. Shared forms `MO26-1007-INITIAL` and `MO26-1007-FINAL` each have 8 KRAs and 8 Core Behaviors. Initial due dates are January 1 and January 15, 2027. Once accounts exist, start at Employee Portal > Self Rating > Initial. Final follows full Initial approval; regular Quarterly follows Final approval and regularization. No scores, approvals, or regularization are seeded.

## Quarterly

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source evaluation/Operations/seed_operations_quarterly_dry_run.sql"
```

The base period is **2026-11-15 to 2027-02-14** for all 16 Operations positions. The same SQL adds a **2027-04-15 to 2027-07-14** Focal Person I-V cycle for Jules and Lorenzo after Final approval and regularization.

## Annual

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source evaluation/Operations/seed_operations_annual_dry_run.sql"
```

The base Annual period is **2026-01-01 to 2026-12-31**. The same SQL adds a **2027 Annual** Focal Person I-V form for employees after regularization. The portal starts an individual's Annual period on the later of January 1 or the regularization date. Annual self-rating requires an **Approved Quarterly evaluation in the same year**.

Each SQL creates six templates: VP for Operations, Regional Managers I-II, Area Coordinators I-III, Focal Persons I-V, Branch Staff I-V, and the later Focal Person cycle. Each template has 8 role-specific KRAs totaling 100%, 8 Core Behaviors, and an 80% KRA / 20% Behavior overall split. No scores, submissions, or approvals are seeded. A second import stops with an error.

Create all required Employee Portal accounts manually in Admin > Portal Accounts after import.
