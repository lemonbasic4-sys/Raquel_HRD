# Audit Department Evaluation Dry Run

Start MySQL in XAMPP. Import the organization, employee, and portal account seeds first. The separate `main_office_probationary/seed_main_office_probationary.sql` seed already supplies Audit probationary employees and their Initial/Final templates; do not import that seed again into an existing setup.

## Probationary Initial and Final: already ready

Nico Bautista (`MO26-AU-P`, hired 2026-10-01) and Katrina Vergara (`MO26-AU-P2`, hired 2026-10-15) have active portal accounts. Both are currently **Probationary** with **Initial** as their next evaluation: due **2027-01-01** and **2027-01-15**, respectively. The active Auditor I templates are `MO26-206-INITIAL` and `MO26-206-FINAL`. They have 8 KRAs and 8 Core Behaviors each. Neither employee has submitted an evaluation yet.

They start at **Employee Portal → Self Rating → Initial**. Final becomes available after the Initial evaluation is approved. Regularization follows the approved Final outcome; these templates do not regularize anyone by themselves.

## Quarterly

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Audit/seed_audit_department_quarterly_dry_run.sql"
```

The first cycle is **2026-11-15 to 2027-02-14** for all 11 Audit positions. The same SQL also creates a later **Auditor I-V Quarterly cycle, 2027-04-15 to 2027-07-14**, for employees who have completed probation and become Regular.

## Annual

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Audit/seed_audit_department_annual_dry_run.sql"
```

The first Annual period is **2026-01-01 to 2026-12-31** for all 11 Audit positions. The same SQL also creates an **Auditor I-V Annual 2027** form for the post-probation path. The portal starts the employee's actual Annual period on the later of January 1 or their regularization date. Annual self-rating requires an **Approved Quarterly evaluation in the same year**.

Each SQL creates five templates: four covering all **11 Audit positions** (Audit Manager I; Audit Manager II-III; Audit Supervisor I-III; Auditor I-V), plus the later Auditor I-V form. Each template has 8 KRAs totaling 100%, 8 Core Behaviors, and an 80% KRA / 20% Behavior overall split. The imports create no scores or approvals. Reimporting a template stops with an error.

The two current Auditor I employees become eligible for Regular Quarterly only after regularization. Their Annual 2027 form unlocks after an Approved 2027 Quarterly evaluation.
