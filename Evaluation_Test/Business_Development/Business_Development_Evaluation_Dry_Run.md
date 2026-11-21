# Business Development Evaluation Dry Run

For mock employee information and all three form types, use [README.md](README.md). Import base seeds 1-4 once, then this folder's import block. No user accounts are created or required for import. Create employee/reviewer accounts manually using Admin > Portal Accounts afterward.

## Probationary Initial and Final

The employee seed supplies `MO26-BD-P`, `MO26-BD-P2`, their reporting heads, and regular staff sample `MO26-BD-R`. Shared forms `MO26-302-INITIAL` and `MO26-302-FINAL` each have 8 KRAs and 8 Core Behaviors. Initial due dates are January 1 and January 15, 2027. Once accounts exist, start at Employee Portal > Self Rating > Initial. Final follows full Initial approval; Annual eligibility follows regularization. No scores, approvals, or regularization are seeded.

## Annual

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Business_Development/seed_business_development_annual_dry_run.sql"
```

The first Annual period is **2026-01-01 to 2026-12-31** for all three BD positions. The same SQL adds a **Staff I-II Annual 2027** form for the post-probation path. The portal starts each employee's Annual period on the later of January 1 or their regularization date. Annual self-rating follows regular employee eligibility.

Each SQL creates three Active templates: two covering all **three Business Development positions** (Officer I and Staff I-II), plus the later Staff I-II form. The role-specific templates have 8 KRAs totaling 100%, 8 Core Behaviors, and an 80% KRA / 20% Behavior overall split. They create no scores or approvals, and reimporting them stops with an error.

Their Annual 2027 form follows regularization.
