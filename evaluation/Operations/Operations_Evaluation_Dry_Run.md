# Operations Evaluation Dry Run

Start MySQL in XAMPP. Import the organization, employee, and portal account seeds first.

## Probationary Initial and Final

Jules Santiago (`MO26-OP-P`) and Lorenzo Fajardo (`MO26-OP-P2`) are probationary Focal Person I employees with portal accounts. Their Initial evaluations are due **2027-01-01** and **2027-01-15**. The existing Main Office probationary seed already created active forms `MO26-1007-INITIAL` and `MO26-1007-FINAL`. Start at Employee Portal > Self Rating > Initial. Final follows Initial approval. Do not reimport the Main Office probationary seed into an existing database.

## Quarterly

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Operations/seed_operations_quarterly_dry_run.sql"
```

The base period is **2026-11-15 to 2027-02-14** for all 16 Operations positions. The same SQL adds a **2027-04-15 to 2027-07-14** Focal Person I-V cycle for Jules and Lorenzo after Final approval and regularization.

## Annual

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Operations/seed_operations_annual_dry_run.sql"
```

The base Annual period is **2026-01-01 to 2026-12-31**. The same SQL adds a **2027 Annual** Focal Person I-V form for employees after regularization. The portal starts an individual's Annual period on the later of January 1 or the regularization date. Annual self-rating requires an **Approved Quarterly evaluation in the same year**.

Each SQL creates six templates: VP for Operations, Regional Managers I-II, Area Coordinators I-III, Focal Persons I-V, Branch Staff I-V, and the later Focal Person cycle. Each template has 8 role-specific KRAs totaling 100%, 8 Core Behaviors, and an 80% KRA / 20% Behavior overall split. No scores, submissions, or approvals are seeded. A second import stops with an error.

The current Main Office sample has Employee Portal accounts for the VP, Regional Manager I, Area Coordinator I, and two probationary Focal Person I employees. Other position forms are ready for eligible accounts.
