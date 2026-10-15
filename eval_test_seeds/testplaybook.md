## HRD personnel and President/CEO test seeds


```powershell
cd C:\xampp\htdocs\raquel_hrd
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS raquel_hris CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run imports in chronological order
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/1st_schema_tables.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/2nd_seed_organization.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/3rd_seed_HR_accounts_.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source database/xPortal_accounts.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/ap_complete_employee_profiles.sql;"
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "source eval_test_seeds/president_ceo_complete_profile.sql;"


# Confirm that only the four intended employee records exist
& "C:\xampp\mysql\bin\mysql.exe" -u root raquel_hris -e "SELECT employee_code, employment_status FROM employees ORDER BY employee_code;"
```

