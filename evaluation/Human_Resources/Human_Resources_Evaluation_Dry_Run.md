# Human Resources Evaluation Dry Run

For mock employee information and all four form types, use [README.md](README.md). Import base seeds 1-4 once, then this folder's import block. No user accounts are created or required for import. Create employee/reviewer accounts manually using Admin > Portal Accounts afterward.

## Probationary Initial and Final

The employee seed supplies `MO26-HR-P`, `MO26-HR-P2`, their reporting heads, and regular staff sample `MO26-HR-R`. Shared forms `MO26-710-INITIAL` and `MO26-710-FINAL` each have 8 KRAs and 8 Core Behaviors. Initial due dates are January 1 and January 15, 2027. Once accounts exist, start at Employee Portal > Self Rating > Initial. Final follows full Initial approval; regular Quarterly follows Final approval and regularization. No scores, approvals, or regularization are seeded.

## Quarterly

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source evaluation/Human_Resources/seed_human_resources_quarterly_dry_run.sql"
```

The first cycle is **2026-11-15 to 2027-02-14** for all 15 HR positions. The same SQL adds an **HR Staff I-V cycle, 2027-04-15 to 2027-07-14**, for employees who complete probation and become Regular.

## Annual

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source evaluation/Human_Resources/seed_human_resources_annual_dry_run.sql"
```

The first Annual period is **2026-01-01 to 2026-12-31** for all 15 positions. The same SQL adds an **HR Staff I-V Annual 2027** form. Annual self-rating requires an **Approved Quarterly evaluation in the same year** and the employee's actual **regularization effective date**. The portal starts the Annual period on the later of January 1 or that date.

Each SQL adds three role-specific templates (HR Manager I-V, HR Supervisor I-V, HR Staff I-V) and the later HR Staff form. Each has 8 KRAs totaling 100%, 8 Core Behaviors, and an 80% KRA / 20% Behavior overall split. No scores or approvals are seeded. Reimporting a template stops with an error.

The current Regular HR employee records (`HRD-001`, `HRD-002`, `HRD-003`) have **no regularization effective dates recorded**. Record their true dates in HR before testing their Annual self-ratings. This seed does not invent or overwrite those dates.
