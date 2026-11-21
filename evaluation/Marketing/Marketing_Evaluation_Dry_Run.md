# Marketing Evaluation Dry Run

For mock employee information and all three form types, use [README.md](README.md). Import base seeds 1-4 once, then this folder's import block. No user accounts are created or required for import. Create employee/reviewer accounts manually using Admin > Portal Accounts afterward.

## Probationary Initial and Final

The employee seed supplies `MO26-MK-P`, `MO26-MK-P2`, their reporting heads, and regular staff sample `MO26-MK-R`. Shared forms `MO26-904-INITIAL` and `MO26-904-FINAL` each have 8 KRAs and 8 Core Behaviors. Initial due dates are January 1 and January 15, 2027. Once accounts exist, start at Employee Portal > Self Rating > Initial. Final follows full Initial approval; Annual eligibility follows regularization. No scores, approvals, or regularization are seeded.

## Annual

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source evaluation/Marketing/seed_marketing_annual_dry_run.sql"
```

The first Annual period is **2026-01-01 to 2026-12-31** for all six positions. The same SQL adds a **Marketing Staff I-II Annual 2027** form. The portal starts the actual Annual period on the later of January 1 or regularization date. Annual self-rating follows regular employee eligibility.

Each SQL adds four role-specific templates (Marketing Manager I, Marketing Manager II, Marketing Supervisors I-II, Marketing Staff I-II) and the later Staff form. Each has 8 KRAs totaling 100%, 8 Core Behaviors, and an 80% KRA / 20% Behavior overall split. No scores or approvals are seeded. Reimporting a template stops with an error.

Only Manager I, Supervisor I, and the two probationary Staff I employees have portal accounts in the current Main Office sample. Other role templates are position-ready and need eligible employee accounts before use.
