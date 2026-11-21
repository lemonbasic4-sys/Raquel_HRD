# Compiled import: all 12 departments

Run the four base seeds in [batch_import_sql.md](../batch_import_sql.md) once first. This is the optional compiled version of every department README. Copy the single PowerShell block below to import **all departments' mock employee information and Initial, Final, and Annual evaluation forms**. No user accounts, ratings, approvals, or automatic regularization are created. Required external heads are reused. Template duplicates stop the import; each SQL file commits separately.

For one department at a time, use that department's README instead, starting with [Acquired Properties](Acquired_Properties/README.md). Do not run this entire compiled block after already importing some departments; skip their completed template files.

- [Acquired Properties](Acquired_Properties/README.md)
- [Audit](Audit/README.md)
- [Business Development](Business_Development/README.md)
- [Compliance](Compliance/README.md)
- [Finance](Finance/README.md)
- [General Services](General_Services/README.md)
- [Human Resources](Human_Resources/README.md)
- [Information Technology](Information_Technology/README.md)
- [Marketing](Marketing/README.md)
- [Office of the President](Office_of_the_President/README.md)
- [Operations](Operations/README.md)
- [Purchasing](Purchasing/README.md)

```powershell
cd C:\xampp\htdocs\raquel_hrd

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Acquired_Properties/seed_employees.sql"
if ($LASTEXITCODE -ne 0) { throw 'Acquired Properties import stopped: seed_employees.sql' }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Acquired_Properties/seed_initial_final.sql"
if ($LASTEXITCODE -ne 0) { throw 'Acquired Properties import stopped: seed_initial_final.sql' }


C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Acquired_Properties/seed_ap_department_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw 'Acquired Properties import stopped: seed_ap_department_annual_dry_run.sql' }

cd C:\xampp\htdocs\raquel_hrd

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Audit/seed_employees.sql"
if ($LASTEXITCODE -ne 0) { throw 'Audit import stopped: seed_employees.sql' }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Audit/seed_initial_final.sql"
if ($LASTEXITCODE -ne 0) { throw 'Audit import stopped: seed_initial_final.sql' }


C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Audit/seed_audit_department_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw 'Audit import stopped: seed_audit_department_annual_dry_run.sql' }

cd C:\xampp\htdocs\raquel_hrd

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Business_Development/seed_employees.sql"
if ($LASTEXITCODE -ne 0) { throw 'Business Development import stopped: seed_employees.sql' }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Business_Development/seed_initial_final.sql"
if ($LASTEXITCODE -ne 0) { throw 'Business Development import stopped: seed_initial_final.sql' }


C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Business_Development/seed_business_development_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw 'Business Development import stopped: seed_business_development_annual_dry_run.sql' }

cd C:\xampp\htdocs\raquel_hrd

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Compliance/seed_employees.sql"
if ($LASTEXITCODE -ne 0) { throw 'Compliance import stopped: seed_employees.sql' }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Compliance/seed_initial_final.sql"
if ($LASTEXITCODE -ne 0) { throw 'Compliance import stopped: seed_initial_final.sql' }


C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Compliance/seed_compliance_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw 'Compliance import stopped: seed_compliance_annual_dry_run.sql' }

cd C:\xampp\htdocs\raquel_hrd

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Finance/seed_employees.sql"
if ($LASTEXITCODE -ne 0) { throw 'Finance import stopped: seed_employees.sql' }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Finance/seed_initial_final.sql"
if ($LASTEXITCODE -ne 0) { throw 'Finance import stopped: seed_initial_final.sql' }


C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Finance/seed_finance_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw 'Finance import stopped: seed_finance_annual_dry_run.sql' }

cd C:\xampp\htdocs\raquel_hrd

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/General_Services/seed_employees.sql"
if ($LASTEXITCODE -ne 0) { throw 'General Services import stopped: seed_employees.sql' }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/General_Services/seed_initial_final.sql"
if ($LASTEXITCODE -ne 0) { throw 'General Services import stopped: seed_initial_final.sql' }


C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/General_Services/seed_general_services_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw 'General Services import stopped: seed_general_services_annual_dry_run.sql' }

cd C:\xampp\htdocs\raquel_hrd

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Human_Resources/seed_employees.sql"
if ($LASTEXITCODE -ne 0) { throw 'Human Resources import stopped: seed_employees.sql' }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Human_Resources/seed_initial_final.sql"
if ($LASTEXITCODE -ne 0) { throw 'Human Resources import stopped: seed_initial_final.sql' }


C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Human_Resources/seed_human_resources_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw 'Human Resources import stopped: seed_human_resources_annual_dry_run.sql' }

cd C:\xampp\htdocs\raquel_hrd

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Information_Technology/seed_employees.sql"
if ($LASTEXITCODE -ne 0) { throw 'Information Technology import stopped: seed_employees.sql' }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Information_Technology/seed_initial_final.sql"
if ($LASTEXITCODE -ne 0) { throw 'Information Technology import stopped: seed_initial_final.sql' }


C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Information_Technology/seed_information_technology_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw 'Information Technology import stopped: seed_information_technology_annual_dry_run.sql' }

cd C:\xampp\htdocs\raquel_hrd

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Marketing/seed_employees.sql"
if ($LASTEXITCODE -ne 0) { throw 'Marketing import stopped: seed_employees.sql' }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Marketing/seed_initial_final.sql"
if ($LASTEXITCODE -ne 0) { throw 'Marketing import stopped: seed_initial_final.sql' }


C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Marketing/seed_marketing_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw 'Marketing import stopped: seed_marketing_annual_dry_run.sql' }

cd C:\xampp\htdocs\raquel_hrd

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Office_of_the_President/seed_employees.sql"
if ($LASTEXITCODE -ne 0) { throw 'Office of the President import stopped: seed_employees.sql' }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Office_of_the_President/seed_initial_final.sql"
if ($LASTEXITCODE -ne 0) { throw 'Office of the President import stopped: seed_initial_final.sql' }


C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Office_of_the_President/seed_office_of_the_president_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw 'Office of the President import stopped: seed_office_of_the_president_annual_dry_run.sql' }

cd C:\xampp\htdocs\raquel_hrd

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Operations/seed_employees.sql"
if ($LASTEXITCODE -ne 0) { throw 'Operations import stopped: seed_employees.sql' }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Operations/seed_initial_final.sql"
if ($LASTEXITCODE -ne 0) { throw 'Operations import stopped: seed_initial_final.sql' }


C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Operations/seed_operations_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw 'Operations import stopped: seed_operations_annual_dry_run.sql' }

cd C:\xampp\htdocs\raquel_hrd

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Purchasing/seed_employees.sql"
if ($LASTEXITCODE -ne 0) { throw 'Purchasing import stopped: seed_employees.sql' }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Purchasing/seed_initial_final.sql"
if ($LASTEXITCODE -ne 0) { throw 'Purchasing import stopped: seed_initial_final.sql' }


C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source Evaluation_Test/Purchasing/seed_purchasing_annual_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw 'Purchasing import stopped: seed_purchasing_annual_dry_run.sql' }
```

After import, create the employee/reviewer portal accounts manually using Admin > Portal Accounts. Existing HRIS accounts remain unchanged.
