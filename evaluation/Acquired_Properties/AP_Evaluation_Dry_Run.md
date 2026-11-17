# Acquired Properties Evaluation Dry Run

Start MySQL in XAMPP. The organization, employee, and portal account seeds must already be imported.

## Quarterly

For a **fresh setup only**, copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Acquired_Properties/seed_ap_department_quarterly_dry_run.sql"
```

Period: **2026-11-15 to 2027-02-14**. This creates four templates for AP Staff I-V, AP Supervisor I-III, AP Manager I-IV, and VP for Acquired Properties. It also assigns Adrian Reyes one blank **Pending Self-Rating** evaluation. The current live database already has the Quarterly forms; this seed stops if they already exist.

## Annual

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Acquired_Properties/seed_ap_department_annual_dry_run.sql"
```

Period: **2026-01-01 to 2026-12-31**. This creates five templates covering every AP position, including Sales Associate I-II. The portal starts each employee's Annual period on the later of January 1 or their regularization date. Annual self-rating requires an **Approved 2026 Quarterly evaluation**.

Each template has 8 KRAs totaling 100%, 8 Core Behaviors, and an overall score split of 80% KRA / 20% Behavior. The seeds do not add scores or approvals. Reimporting a template stops with an error.
