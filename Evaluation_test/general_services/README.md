# General Services evaluation test

This folder provides a small mock General Services team with **two probationary Warehouse Staff I employees**. The regular team includes the VP, manager, supervisor, warehouse staff, and driver needed to test the Annual and Quarterly reporting flow.

## Import on a fresh test database

Start Apache and MySQL. Open PowerShell in `C:\xampp\htdocs\Raquel_HRD`.

**The first command deletes the current `raquel_hris` database.** Back it up first. Run each file once, in this order:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u root -e 'source database/1st_schema_tables.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source database/2nd_seed_organization.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/general_services/01_gs_test_people.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/general_services/02_verify_fixture.sql;'
```

Stop if a command reports an error. The verification file only reads data, so you may run it again.

## Test people

| Account | Person | Position | Status | Hire date |
| --- | --- | --- | --- | --- |
| `test.gs.ceo` | Isabella Villanueva | President and CEO | Regular | 2007-04-02 |
| `test.gs.vp` | Nadine Santos | VP for General Services | Regular | 2012-06-04 |
| `test.gs.manager` | Rafael Reyes | GS Manager I | Regular | 2016-08-01 |
| `test.gs.supervisor` | Jonas Cruz | GS Supervisor I | Regular | 2020-03-02 |
| `test.gs.warehouse` | Tessa Garcia | Warehouse Staff I | Regular | 2023-05-08 |
| `test.gs.driver` | Gabriel Mendoza | Driver I | Regular | 2021-08-02 |
| `test.gs.iris` | Iris Navarro | Warehouse Staff I | Probationary | 2026-09-01 |
| `test.gs.paolo` | Paolo Dela Cruz | Warehouse Staff I | Probationary | 2026-10-01 |

Local test password: `password`. The app may ask you to change it at first login. Isabella creates and reviews Nadine's form but is outside the General Services cycle. Nadine starts and approves the cycle. Jonas reviews the Warehouse Staff and Driver KRAs.

The verification report should show **eight people, eight accounts, sixteen addresses, and eight rows for each other listed profile section**. These details are fictional. Fields that do not apply, such as separation dates for active employees, are empty.

## Create the forms

In **Evaluation Templates**, create separate Active forms for every type below. Target **General Services**. Use 11 job-relevant KRA items totaling 100%, the system's eight Core Behaviors & Values, and the 80% KRA / 20% behavior split.

| Sign in as | Form target | Types |
| --- | --- | --- |
| Isabella | VP for General Services | Quarterly, Annual |
| Nadine | GS Manager I | Quarterly, Annual |
| Rafael | GS Supervisor I | Quarterly, Annual |
| Jonas | Warehouse Staff I | Initial, Final, Quarterly, Annual |
| Jonas | Driver I | Quarterly, Annual |

Iris and Paolo use the Initial/Final Warehouse Staff I forms. Tessa uses the Quarterly/Annual Warehouse Staff I forms while the other two remain probationary.

## Test order

1. **Probationary:** As Iris, submit Initial self-rating. Jonas reviews it. Complete the approval route and check Iris's history. Submit Final only after Initial approval. Repeat for Paolo. Their results should stay separate.
2. **Quarterly:** As Nadine, check form readiness, choose From/To dates and a future self-rating deadline, then start the GS cycle. Nadine, Rafael, Jonas, Tessa, and Gabriel should join. Submit self-ratings, perform GS-only peer 360 ratings, and complete immediate-head KRA reviews. Isabella reviews Nadine's KRA but is not a GS peer 360 rater. Approve as Nadine when ready and check Quarterly History.
3. **Annual:** Repeat with separate Annual forms. Confirm Annual and Quarterly results appear in their own histories.

Iris and Paolo must stay outside regular cycles while probationary. For a failed step, record the account, page, expected result, actual result, and a screenshot.

Raquel Pawnshop's [2016 company publication](https://www.raquelpawnshop.com/insider/Insider_21st_2016.pdf) describes three decades of operation. The mock hire dates are later than its approximate 1986 start and are **not** real employee records.
