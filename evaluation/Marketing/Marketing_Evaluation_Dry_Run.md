# Marketing Evaluation Dry Run

Start MySQL in XAMPP. The organization, employee, and portal account seeds must already be imported.

## Probationary Initial and Final: already ready

Lea Fernandez (`MO26-MK-P`) and Joel Marquez (`MO26-MK-P2`) have active portal accounts. Both are currently Probationary, with **Initial** due **2027-01-01** and **2027-01-15**, respectively. The Main Office probationary seed already created active Marketing Staff I forms `MO26-904-INITIAL` and `MO26-904-FINAL`; do not reimport that seed into an existing setup.

They start in **Employee Portal → Self Rating → Initial**. Final becomes available after Initial approval. Regular Quarterly becomes available after the Final outcome and regularization.

## Quarterly

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Marketing/seed_marketing_quarterly_dry_run.sql"
```

The first cycle is **2026-11-15 to 2027-02-14** for all six Marketing positions. The same SQL adds a **Marketing Staff I-II cycle, 2027-04-15 to 2027-07-14**, for employees who complete probation and become Regular.

## Annual

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Marketing/seed_marketing_annual_dry_run.sql"
```

The first Annual period is **2026-01-01 to 2026-12-31** for all six positions. The same SQL adds a **Marketing Staff I-II Annual 2027** form. The portal starts the actual Annual period on the later of January 1 or regularization date. Annual self-rating requires an **Approved Quarterly evaluation in the same year**.

Each SQL adds four role-specific templates (Marketing Manager I, Marketing Manager II, Marketing Supervisors I-II, Marketing Staff I-II) and the later Staff form. Each has 8 KRAs totaling 100%, 8 Core Behaviors, and an 80% KRA / 20% Behavior overall split. No scores or approvals are seeded. Reimporting a template stops with an error.

Only Manager I, Supervisor I, and the two probationary Staff I employees have portal accounts in the current Main Office sample. Other role templates are position-ready and need eligible employee accounts before use.
