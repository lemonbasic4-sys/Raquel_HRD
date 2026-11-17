# Compliance Evaluation Dry Run

For mock employee information and all four form types, use [README.md](README.md). Import base seeds 1-4 once, then this folder's import block. No user accounts are created or required for import. Create employee/reviewer accounts manually using Admin > Portal Accounts afterward.

## Probationary Initial and Final

The employee seed supplies `MO26-CO-P`, `MO26-CO-P2`, their reporting heads, and regular staff sample `MO26-CO-R`. Shared forms `MO26-403-INITIAL` and `MO26-403-FINAL` each have 8 KRAs and 8 Core Behaviors. Initial due dates are January 1 and January 15, 2027. Once accounts exist, start at Employee Portal > Self Rating > Initial. Final follows full Initial approval; regular Quarterly follows Final approval and regularization. No scores, approvals, or regularization are seeded.

## Quarterly

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source evaluation/Compliance/seed_compliance_quarterly_dry_run.sql"
```

The first cycle is **2026-11-15 to 2027-02-14** for all six Compliance positions. The same SQL adds a later **Staff I-III cycle, 2027-04-15 to 2027-07-14**, for employees who complete probation and become Regular.

## Annual

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source evaluation/Compliance/seed_compliance_annual_dry_run.sql"
```

The first Annual period is **2026-01-01 to 2026-12-31** for all six positions. The same SQL adds a **Staff I-III Annual 2027** form. The portal starts the actual Annual period on the later of January 1 or regularization date. Annual self-rating requires an **Approved Quarterly evaluation in the same year**.

Each SQL adds three role-specific templates (Supervisor I, Supervisor II-III, Staff I-III) and the later Staff form. Each has 8 KRAs totaling 100%, 8 Core Behaviors, and an 80% KRA / 20% Behavior overall split. No scores or approvals are seeded. Reimporting a template stops with an error.
