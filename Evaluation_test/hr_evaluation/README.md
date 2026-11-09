# HR evaluation test guide

Use this folder to test **probationary Initial and Final evaluations** and **regular Quarterly and Annual evaluations** in Human Resources. Two probationary employees let you test them separately. All people and personal details are mock data.

## Temporary HR Supervisor coverage

HR Supervisor II temporarily receives Supervisor I's direct reports when Supervisor I has no active regular holder with an enabled HR Supervisor account. The configured reporting positions stay unchanged. An eligible replacement Supervisor I automatically resumes those reports. Acting coverage also applies to form targets and unfinished Annual/Quarterly reviews; completed approvals and scores retain their recorded reviewer.

After loading the HR fixture, run the regression check from the project root:

```powershell
& 'C:\xampp\php\php.exe' Evaluation_test/hr_evaluation/verify_supervisor_succession.php
```

The check creates temporary records and simulates promotion/replacement inside a transaction, then rolls back. Use a test database; `HRIS_TEST_DATABASE`, `HRIS_TEST_HOST`, `HRIS_TEST_USER`, and `HRIS_TEST_PASSWORD` can override the local connection defaults. This check does not verify the browser workflow.

## Before you start

Start Apache and MySQL in XAMPP. Open PowerShell in `C:\xampp\htdocs\Raquel_HRD`.

**The first command deletes the current `raquel_hris` database.** Back it up first. Use these commands only when you want a fresh test database. Run each SQL file **once**, in this order:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u root -e 'source database/1st_schema_tables.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source database/2nd_seed_organization.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source database/3rd_seed_HR_accounts_.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/hr_evaluation/01_hr_test_people.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/hr_evaluation/02_verify_fixture.sql;'
```

Stop if a command shows an error. The last command only reads data; you can run it again at any time.

## People in this test

| Person | Position | Status | Hire date |
| --- | --- | --- | --- |
| Isabella Villanueva | President and CEO | Regular | 2007-04-02 |
| Elena Delgado | HR Manager I | Regular | 2020-01-15 |
| Patricia Gomez | HR Supervisor I | Regular | 2017-02-14 |
| Miguel Torres | HR Staff II | Regular | 2022-01-05 |
| Mara Reyes | HR Staff I | Probationary | 2026-09-01 |
| Luis Navarro | HR Staff I | Probationary | 2026-10-01 |

Isabella creates and reviews Elena's form. She is **not** part of the HR cycle. Elena, Patricia, and Miguel came from the base HR seed. `01_hr_test_people.sql` adds Isabella, Mara, Luis, and employee portal accounts for the HR team. Each person has the core profile sections needed for testing. Fields that do not apply, such as a separation date for an active employee, are intentionally empty.

The new test accounts use the local seed password `password`. The app may ask you to change it on first login. Do not use these accounts outside a local test database.

## Check the import

`02_verify_fixture.sql` prints three reports:

1. **People:** six rows. Mara and Luis are Probationary; the others are Regular.
2. **Accounts:** nine rows. Elena, Patricia, and Miguel each have an HRIS account and a separate Employee account.
3. **Profile sections:** at least six rows in each section. Addresses should have twelve rows.

If a count is lower, check the import command for an error before continuing.

## Create the forms in the portal

For an automatic dry run, import the [template seed](../templates/README.md) after this department's employee SQL. It creates the forms listed below with 12 KRAs and the eight default behaviors. Skip manual form creation when using that seed.

Create **separate** forms for each evaluation type. Set them to **Active**. Use at least 12 KRA items whose weights total 100%, the eight system Core Behaviors & Values, and the system's 80% KRA / 20% behavior split.

| Sign in as | Create forms for | Evaluation types |
| --- | --- | --- |
| Isabella | HR Manager I | Quarterly, Annual |
| Elena | HR Supervisor I | Quarterly, Annual |
| Patricia | HR Staff I | Initial, Final |
| Patricia | HR Staff II | Quarterly, Annual |

All forms target **Human Resources**. If a cycle says a form is missing, check the form's author, position, type, and Active status.

## Test the probationary employee first

1. Sign in as Mara in the employee portal. Open **Self Rating** and submit the **Initial** evaluation.
2. Sign in as Patricia. Review Mara's KRA and behavior ratings. Complete the remaining approval steps shown in the portal.
3. Check Mara's **Evaluation History**. The Initial result should appear there after approval.
4. Sign in as Mara again. Submit the **Final** evaluation. It should be available after the Initial evaluation is approved.
5. Complete the Final review and check that Initial and Final appear as separate history entries.
6. Repeat the Initial and Final test for Luis. His results should remain separate from Mara's.

Mara and Luis must not be in the regular Quarterly or Annual roster while they are still Probationary.

## Test Quarterly

1. Sign in as Elena. Open **Performance Evaluation**, then **Quarterly Employee**. Check that all three regular HR positions show **Ready**.
2. Choose **Period From**, **Period To**, and a future **Self-rating deadline**. The dates do not have to match a calendar quarter.
3. Start the HR cycle. Elena, Patricia, and Miguel should now see their Quarterly forms in **Self Rating**.
4. Submit their self-ratings. Complete HR-only 360 ratings and immediate-head KRA reviews. Isabella reviews Elena's KRA; she does not give an HR peer 360 rating.
5. Check each result. The final score uses `KRA score * 0.80 + behavior score * 0.20`.
6. When **Ready for HR Manager approval** appears, approve as Elena. Check **Quarterly History**.

## Test Annual

Repeat the Quarterly steps with the **Annual** forms and Annual cycle. Use a period you record in your notes. Check that Annual results appear in Annual History and remain separate from Quarterly results.

For any failed step, record the account, page, action, expected result, actual result, and a screenshot. Avoid changing evaluation status directly in SQL during the test.

## Why these hire dates?

Raquel Pawnshop's [2016 company publication](https://www.raquelpawnshop.com/insider/Insider_21st_2016.pdf) says it had operated for three decades. That suggests a start around 1986. The mock employees' hire dates are later than that, and Mara's recent hire supports the probationary scenario. None of these dates are real employee records.
