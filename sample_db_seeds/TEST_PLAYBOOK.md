# Evaluation-Flow Test Seeds & Playbook

This seed setup is optimized for testing the complete end-to-end evaluation lifecycle with a lean, clean employee roster without waiting on dozens of extraneous department accounts.

All Employee portal passwords: `password`

---

## HRIS Administrative Logins:

| Login | Password | Role | Usage |
|---|---|---|---|
| `admin` | `password` | Admin | Full System Administration |
| `elena.delgado` | `password` | HR Manager | Templates, Routing & Governance UI, HRIS Packages |

---

## Active Employee & Portal Roster:

| Department | Portal Login | Name | Job Title | Reports To |
|---|---|---|---|---|
| **Human Resources** | `HRD-003` | Miguel Torres | HR Staff I | `HRD-002` (Patricia Gomez) |
| **Human Resources** | `HRD-002` | Patricia Gomez | HR Supervisor I | `HRD-001` (Elena Delgado) |
| **Human Resources** | `HRD-001` | Elena Delgado | HR Manager I | *(Direct to President)* |
| **Acquired Properties** | `AP-T01` | Leonora Gomez | AP Staff I | `AP-T02` (Ronald Lopez) |
| **Acquired Properties** | `AP-T02` | Ronald Lopez | AP Supervisor I | `AP-T03` (Christopher Tolentino) |
| **Acquired Properties** | `AP-T03` | Christopher Tolentino | AP Manager I | `AP-T04` (Eduardo Aquino) |
| **Acquired Properties** | `AP-T04` | Eduardo Aquino | VP for Acquired Properties | *(Executive)* |
| **Audit** | `AUD-S01` | Isabel Mendoza | Audit Supervisor I | `AUD-M01` (Manuel Ramos) |
| **Audit** | `AUD-M01` | Manuel Ramos | Audit Manager I | *(Department Head)* |
| **Office of President** | `OP-T02` | Gabriel Mendoza | President and CEO | *(Company Head)* |

---

## Configured Corporate Governance Approvers:

| Step | Governance Level | Assigned Official | Portal Login | Scope |
|---|---|---|---|---|
| **Step 4** | **Division VP** | Eduardo Villanueva Aquino (VP for Acquired Properties) | `AP-T04` | Acquired Properties (Dept 1) |
| **Step 5** | **President & CEO** | Gabriel Santos Mendoza (President & CEO) | `OP-T02` | Company-Wide |
| **Step 6** | **Audit Committee** | Isabel Reyes Mendoza (Audit Supervisor I) | `AUD-S01` | Company-Wide |
| **Step 7** | **Board of Directors** | Manuel Rivera Ramos (Audit Manager I) | `AUD-M01` | **Company-Wide (Final Lock & Apply)** |

*Note: Human Resources routes directly to President (Step 5) and automatically bypasses Step 4 (Division VP).*

---

## Complete Reset & Seed Batch Script:

Run this directly in PowerShell to reset and load the entire setup in one go:

```powershell
# Set working directory to project root
cd C:\xampp\htdocs\Raquel_HRD_Test

# 1. Drop and recreate the database
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "DROP DATABASE IF EXISTS raquel_hris_test_db; CREATE DATABASE raquel_hris_test_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Run imports in chronological order
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris_test_db -e "source database/1st_schema_tables.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris_test_db -e "source database/2nd_seed_organization.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris_test_db -e "source database/3rd_seed_HR_accounts_.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris_test_db -e "source sample_db_seeds/01_test_employees.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris_test_db -e "source database/xPortal_accounts.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris_test_db -e "source database/data/seed_templates.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris_test_db -e "source sample_db_seeds/02_test_hrd_portal_accounts.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris_test_db -e "source sample_db_seeds/03_test_governance_approvers.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris_test_db -e "source database/zLAST_performance_indexes.sql;"

Write-Host "Database reset and seeded successfully!" -ForegroundColor Green
```
