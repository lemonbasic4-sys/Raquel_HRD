# Compliance Evaluation Dry Run

Start MySQL in XAMPP. The organization, employee, and portal account seeds must already be imported.

## Probationary Initial and Final: already ready

Mira Lopez (`MO26-CO-P`) and Jonas Herrera (`MO26-CO-P2`) have active portal accounts. Both are currently Probationary, with **Initial** due **2027-01-01** and **2027-01-15**, respectively. The Main Office probationary seed already created active Compliance Staff I forms `MO26-403-INITIAL` and `MO26-403-FINAL`; do not reimport that seed into an existing setup.

They start in **Employee Portal → Self Rating → Initial**. Final becomes available after the Initial evaluation is approved. Regular Quarterly becomes available after the Final outcome and regularization.

## Quarterly

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Compliance/seed_compliance_quarterly_dry_run.sql"
```

The first cycle is **2026-11-15 to 2027-02-14** for all six Compliance positions. The same SQL adds a later **Staff I-III cycle, 2027-04-15 to 2027-07-14**, for employees who complete probation and become Regular.

## Annual

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Compliance/seed_compliance_annual_dry_run.sql"
```

The first Annual period is **2026-01-01 to 2026-12-31** for all six positions. The same SQL adds a **Staff I-III Annual 2027** form. The portal starts the actual Annual period on the later of January 1 or regularization date. Annual self-rating requires an **Approved Quarterly evaluation in the same year**.

Each SQL adds three role-specific templates (Supervisor I, Supervisor II-III, Staff I-III) and the later Staff form. Each has 8 KRAs totaling 100%, 8 Core Behaviors, and an 80% KRA / 20% Behavior overall split. No scores or approvals are seeded. Reimporting a template stops with an error.
