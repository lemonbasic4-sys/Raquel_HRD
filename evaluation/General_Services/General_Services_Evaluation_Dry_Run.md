# General Services Evaluation Dry Run

For mock employee information and all three form types, use [README.md](README.md). Import base seeds 1-4 once, then this folder's import block. No user accounts are created or required for import. Create employee/reviewer accounts manually using Admin > Portal Accounts afterward.

## Probationary Initial and Final

The employee seed supplies `MO26-GS-P`, `MO26-GS-P2`, their reporting heads, and regular staff sample `MO26-GS-R`. Shared forms `MO26-618-INITIAL` and `MO26-618-FINAL` each have 8 KRAs and 8 Core Behaviors. Initial due dates are January 1 and January 15, 2027. Once accounts exist, start at Employee Portal > Self Rating > Initial. Final follows full Initial approval; Annual eligibility follows regularization. No scores, approvals, or regularization are seeded.

## Annual

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source evaluation/General_Services/seed_general_services_annual_dry_run.sql"
```

The first Annual period is **2026-01-01 to 2026-12-31** for all 24 positions. The same SQL adds a **Facilities Maintenance Staff I-III Annual 2027** form. The portal starts the actual Annual period on the later of January 1 or regularization date. Annual self-rating follows regular employee eligibility.

Each SQL adds eight role-specific templates (VP, Managers, Supervisors, Drivers, Security Monitoring, Facilities Maintenance, Warehouse, Messengers) and the later Facilities form. Each has 8 KRAs totaling 100%, 8 Core Behaviors, and an 80% KRA / 20% Behavior overall split. No scores or approvals are seeded. Reimporting a template stops with an error.

Create all required Employee Portal accounts manually in Admin > Portal Accounts after import.
