# Office of the President Evaluation Dry Run

Start MySQL in XAMPP. Import the organization, employee, and portal account seeds first.

## Probationary Initial and Final

Iris Morales (`MO26-EX-P`) and Nina Escobar (`MO26-EX-P2`) are probationary Executive Assistant I employees with portal accounts. Their Initial evaluations are due 2027-01-01 and 2027-01-15. Existing forms `MO26-1101-INITIAL` and `MO26-1101-FINAL` cover them; do not reimport the Main Office probationary seed into an existing database. They start at Employee Portal > Self Rating > Initial. Final follows Initial approval.

## Quarterly

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Office_of_the_President/seed_office_of_the_president_quarterly_dry_run.sql"
```

The base period is **2026-11-15 to 2027-02-14**. The same SQL adds a **2027-04-15 to 2027-07-14** cycle for Iris and Nina after Final approval and regularization.

## Annual

Copy and paste into PowerShell:

```powershell
cd C:\xampp\htdocs\Raquel_HRD
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source evaluation/Office_of_the_President/seed_office_of_the_president_annual_dry_run.sql"
```

The base period is **2026-01-01 to 2026-12-31**. The same SQL adds a **2027 Annual** cycle for the Executive Assistants after regularization. The portal starts an individual's Annual period on the later of January 1 or their regularization date. Annual self-rating requires an Approved Quarterly evaluation in that year.

Both scripts cover **Executive Assistant I, II, and III**. President and CEO is excluded because that position has no configured reporting head to review a self-rating. Each template has 8 executive-support KRAs totaling 100%, 8 Core Behaviors, and an 80% KRA / 20% Behavior overall split. The scripts add forms only: no scores, submissions, or approvals. A second import stops if the forms already exist.
