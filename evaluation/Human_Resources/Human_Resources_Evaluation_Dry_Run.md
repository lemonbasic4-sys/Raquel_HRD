# Human Resources Evaluation Dry Run

Start MySQL in XAMPP. The organization, employee, and portal account seeds must already be imported.

## Probationary Initial and Final: already ready

Sofia Villanueva (`MO26-HR-P`) and Celine Mata (`MO26-HR-P2`) have active portal accounts. Both are currently Probationary, with **Initial** due **2027-01-01** and **2027-01-15**, respectively. The Main Office probationary seed already created active HR Staff I forms `MO26-710-INITIAL` and `MO26-710-FINAL`; do not reimport that seed into an existing setup.

They start in **Employee Portal → Self Rating → Initial**. Final becomes available after Initial approval. Regular Quarterly becomes available after the Final outcome and regularization.

## Quarterly

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Human_Resources/seed_human_resources_quarterly_dry_run.sql"
```

The first cycle is **2026-11-15 to 2027-02-14** for all 15 HR positions. The same SQL adds an **HR Staff I-V cycle, 2027-04-15 to 2027-07-14**, for employees who complete probation and become Regular.

## Annual

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Human_Resources/seed_human_resources_annual_dry_run.sql"
```

The first Annual period is **2026-01-01 to 2026-12-31** for all 15 positions. The same SQL adds an **HR Staff I-V Annual 2027** form. Annual self-rating requires an **Approved Quarterly evaluation in the same year** and the employee's actual **regularization effective date**. The portal starts the Annual period on the later of January 1 or that date.

Each SQL adds three role-specific templates (HR Manager I-V, HR Supervisor I-V, HR Staff I-V) and the later HR Staff form. Each has 8 KRAs totaling 100%, 8 Core Behaviors, and an 80% KRA / 20% Behavior overall split. No scores or approvals are seeded. Reimporting a template stops with an error.

The current Regular HR employee records (`HRD-001`, `HRD-002`, `HRD-003`) have **no regularization effective dates recorded**. Record their true dates in HR before testing their Annual self-ratings. This seed does not invent or overwrite those dates.
