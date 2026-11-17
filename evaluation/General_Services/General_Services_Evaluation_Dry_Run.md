# General Services Evaluation Dry Run

Start MySQL in XAMPP. The organization, employee, and portal account seeds must already be imported.

## Probationary Initial and Final: already ready

Paolo Ramos (`MO26-GS-P`) and Jerome Abad (`MO26-GS-P2`) have active portal accounts. Both are currently Probationary, with **Initial** due **2027-01-01** and **2027-01-15**, respectively. The Main Office probationary seed already created active Facilities Maintenance Staff I forms `MO26-618-INITIAL` and `MO26-618-FINAL`; do not reimport that seed into an existing setup.

They start in **Employee Portal → Self Rating → Initial**. Final becomes available after Initial approval. Regular Quarterly becomes available after the Final outcome and regularization.

## Quarterly

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/General_Services/seed_general_services_quarterly_dry_run.sql"
```

The first cycle is **2026-11-15 to 2027-02-14** for all 24 General Services positions. The same SQL adds a **Facilities Maintenance Staff I-III cycle, 2027-04-15 to 2027-07-14**, for employees who complete probation and become Regular.

## Annual

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/General_Services/seed_general_services_annual_dry_run.sql"
```

The first Annual period is **2026-01-01 to 2026-12-31** for all 24 positions. The same SQL adds a **Facilities Maintenance Staff I-III Annual 2027** form. The portal starts the actual Annual period on the later of January 1 or regularization date. Annual self-rating requires an **Approved Quarterly evaluation in the same year**.

Each SQL adds eight role-specific templates (VP, Managers, Supervisors, Drivers, Security Monitoring, Facilities Maintenance, Warehouse, Messengers) and the later Facilities form. Each has 8 KRAs totaling 100%, 8 Core Behaviors, and an 80% KRA / 20% Behavior overall split. No scores or approvals are seeded. Reimporting a template stops with an error.

Only the GS VP, Manager I, Supervisor I, and two probationary Facilities staff have Employee Portal accounts in the current Main Office sample. The other role templates are position-ready and need eligible employee accounts before use.
