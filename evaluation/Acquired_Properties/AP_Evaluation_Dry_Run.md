# Acquired Properties Evaluation Dry Run

For mock employee information and all four form types, use [README.md](README.md). Import base seeds 1-4 once, then this folder's import block. No user accounts are created or required for import. Create employee/reviewer accounts manually using Admin > Portal Accounts afterward.

## Probationary Initial and Final

The employee seed supplies `MO26-AP-P`, `MO26-AP-P2`, their reporting heads, and regular staff sample `MO26-AP-R`. Shared forms `MO26-108-INITIAL` and `MO26-108-FINAL` each have 8 KRAs and 8 Core Behaviors. Initial due dates are January 1 and January 15, 2027. Once accounts exist, start at Employee Portal > Self Rating > Initial. Final follows full Initial approval; regular Quarterly follows Final approval and regularization. No scores, approvals, or regularization are seeded.

## Quarterly

For a **fresh setup only**, copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source evaluation/Acquired_Properties/seed_ap_department_quarterly_dry_run.sql"
```

Period: **2026-11-15 to 2027-02-14**. This creates four templates for AP Staff I-V, AP Supervisor I-III, AP Manager I-IV, and VP for Acquired Properties. This imports templates only. To optionally assign Adrian Reyes one blank **Pending Self-Rating** evaluation afterward, import `seed_ap_supervisor_quarterly_evaluation.sql` separately. The Quarterly seed stops if its forms already exist.

## Annual

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source evaluation/Acquired_Properties/seed_ap_department_annual_dry_run.sql"
```

Period: **2026-01-01 to 2026-12-31**. This creates five templates covering every AP position, including Sales Associate I-II. The portal starts each employee's Annual period on the later of January 1 or their regularization date. Annual self-rating requires an **Approved 2026 Quarterly evaluation**.

Each template has 8 KRAs totaling 100%, 8 Core Behaviors, and an overall score split of 80% KRA / 20% Behavior. The seeds do not add scores or approvals. Reimporting a template stops with an error.
