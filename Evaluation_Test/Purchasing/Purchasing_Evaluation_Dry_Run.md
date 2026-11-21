# Purchasing Evaluation Dry Run

For mock employee information and all three form types, use [README.md](README.md). Import base seeds 1-4 once, then this folder's import block. No user accounts are created or required for import. Create employee/reviewer accounts manually using Admin > Portal Accounts afterward.

## Probationary Initial and Final

The employee seed supplies `MO26-PU-P`, `MO26-PU-P2`, their reporting heads, and regular staff sample `MO26-PU-R`. Shared forms `MO26-1202-INITIAL` and `MO26-1202-FINAL` each have 8 KRAs and 8 Core Behaviors. Initial due dates are January 1 and January 15, 2027. Once accounts exist, start at Employee Portal > Self Rating > Initial. Final follows full Initial approval; Annual eligibility follows regularization. No scores, approvals, or regularization are seeded.

## Annual

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Purchasing/seed_purchasing_annual_dry_run.sql"
```

The base Annual period is **2026-01-01 to 2026-12-31**. The same SQL adds a **2027 Annual** Purchasing Staff I form for employees after regularization. The portal starts an individual's Annual period on the later of January 1 or the regularization date. Annual self-rating follows regular employee eligibility.

Each SQL creates three templates: Procurement Supervisor I, Purchasing Staff I, and the later Staff cycle. Each template has 8 role-specific KRAs totaling 100%, 8 Core Behaviors, and an 80% KRA / 20% Behavior overall split. No scores, submissions, or approvals are seeded. A second import stops with an error.

The current Main Office sample has portal accounts for Procurement Supervisor Angela Domingo and probationary staff Rico and Hannah. Angela reports to GS Manager I, who has an active portal account.
