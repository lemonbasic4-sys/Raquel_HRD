# Finance Evaluation Dry Run

Start MySQL in XAMPP. The organization, employee, and portal account seeds must already be imported.

## Probationary Initial and Final: already ready

Carlo Dela Cruz (`MO26-FI-P`) and Alyssa Baring (`MO26-FI-P2`) have active portal accounts. Both are currently Probationary, with **Initial** due **2027-01-01** and **2027-01-15**, respectively. The Main Office probationary seed already created active Accounting Staff I forms `MO26-511-INITIAL` and `MO26-511-FINAL`; do not reimport that seed into an existing setup.

They start in **Employee Portal → Self Rating → Initial**. Final becomes available after the Initial evaluation is approved. Regular Quarterly becomes available after the Final outcome and regularization.

## Quarterly

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Finance/seed_finance_quarterly_dry_run.sql"
```

The first cycle is **2026-11-15 to 2027-02-14** for all 18 Finance positions. The same SQL adds an **Accounting Staff I-V cycle, 2027-04-15 to 2027-07-14**, for employees who complete probation and become Regular.

## Annual

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Finance/seed_finance_annual_dry_run.sql"
```

The first Annual period is **2026-01-01 to 2026-12-31** for all 18 positions. The same SQL adds an **Accounting Staff I-V Annual 2027** form. The portal starts the actual Annual period on the later of January 1 or regularization date. Annual self-rating requires an **Approved Quarterly evaluation in the same year**.

Each SQL adds six role-specific templates (Finance VP, Accounting Manager, Accounting Supervisors, Treasury Supervisors, Accounting Staff, Treasury Staff) and the later Accounting Staff form. Each has 8 KRAs totaling 100%, 8 Core Behaviors, and an 80% KRA / 20% Behavior overall split. No scores or approvals are seeded. Reimporting a template stops with an error.

No Treasury Supervisor or Treasury Staff employee account is present in the current Main Office sample. Their templates are position-ready; actual self-rating and review require eligible employee and reporting-head accounts.
