# Purchasing Evaluation Dry Run

Start MySQL in XAMPP. Import the organization, employee, and portal account seeds first.

## Probationary Initial and Final

Rico Magsino (`MO26-PU-P`) and Hannah Yap (`MO26-PU-P2`) are probationary Purchasing Staff I employees with portal accounts. Their Initial evaluations are due **2027-01-01** and **2027-01-15**. Existing forms `MO26-1202-INITIAL` and `MO26-1202-FINAL` cover them. Start at Employee Portal > Self Rating > Initial. Final follows Initial approval. Do not reimport the Main Office probationary seed into an existing database.

## Quarterly

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Purchasing/seed_purchasing_quarterly_dry_run.sql"
```

The base period is **2026-11-15 to 2027-02-14** for both Purchasing positions. The same SQL adds a **2027-04-15 to 2027-07-14** Purchasing Staff I cycle for Rico and Hannah after Final approval and regularization.

## Annual

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Purchasing/seed_purchasing_annual_dry_run.sql"
```

The base Annual period is **2026-01-01 to 2026-12-31**. The same SQL adds a **2027 Annual** Purchasing Staff I form for employees after regularization. The portal starts an individual's Annual period on the later of January 1 or the regularization date. Annual self-rating requires an **Approved Quarterly evaluation in the same year**.

Each SQL creates three templates: Procurement Supervisor I, Purchasing Staff I, and the later Staff cycle. Each template has 8 role-specific KRAs totaling 100%, 8 Core Behaviors, and an 80% KRA / 20% Behavior overall split. No scores, submissions, or approvals are seeded. A second import stops with an error.

The current Main Office sample has portal accounts for Procurement Supervisor Angela Domingo and probationary staff Rico and Hannah. Angela reports to GS Manager I, who has an active portal account.
