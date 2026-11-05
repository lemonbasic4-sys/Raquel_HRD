# Business Development evaluation test

This folder provides a small mock Business Development team with **two probationary Staff I employees**. Use it to test Initial and Final evaluations, then separate Quarterly and Annual cycles for regular employees.

## Import on a fresh test database

Start Apache and MySQL. Open PowerShell in `C:\xampp\htdocs\Raquel_HRD`.

**The first command deletes the current `raquel_hris` database.** Back it up first. Run each file once, in this order:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u root -e 'source database/1st_schema_tables.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source database/2nd_seed_organization.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/business_development/01_bd_test_people.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/business_development/02_verify_fixture.sql;'
```

Stop if a command reports an error. The verification file only reads data, so you may run it again.

## Test people

| Account | Person | Position | Status | Hire date |
| --- | --- | --- | --- | --- |
| `test.bd.ceo` | Isabella Villanueva | President and CEO | Regular | 2007-04-02 |
| `test.bd.officer` | Antonio Dela Cruz | Business Development Officer I | Regular | 2017-05-02 |
| `test.bd.staff1` | Emilio Cruz | Business Development Staff I | Regular | 2021-11-25 |
| `test.bd.staff2` | Rina Torres | Business Development Staff II | Regular | 2023-07-03 |
| `test.bd.celia` | Celia Navarro | Business Development Staff I | Probationary | 2026-09-01 |
| `test.bd.paolo` | Paolo Castillo | Business Development Staff I | Probationary | 2026-10-01 |

Local test password: `password`. The app may ask you to change it at first login. Isabella only creates and reviews Antonio's form; she is outside the BD cycle. Antonio starts and approves BD cycles and reviews both Staff positions.

The verification report should show **six people, six accounts, twelve addresses, and six rows for each other listed profile section**. All personal details are fictional. Fields that do not apply, such as separation dates for active employees, are empty.

## Create the forms

For an automatic dry run, import the [template seed](../templates/README.md) after this department's employee SQL. It creates the forms listed below with 12 KRAs and the eight default behaviors. Skip manual form creation when using that seed.

In **Evaluation Templates**, create separate Active forms for every type below. Target **Business Development**. Use at least 12 job-relevant KRA items totaling 100%, the system's eight Core Behaviors & Values, and the 80% KRA / 20% behavior split.

| Sign in as | Form target | Types |
| --- | --- | --- |
| Isabella | Business Development Officer I | Quarterly, Annual |
| Antonio | Business Development Staff I | Initial, Final, Quarterly, Annual |
| Antonio | Business Development Staff II | Quarterly, Annual |

Celia and Paolo use the Initial/Final Staff I forms. Emilio uses the Quarterly/Annual Staff I forms while the other two remain probationary.

## Test order

1. **Probationary:** As Celia, submit Initial self-rating. Antonio reviews it. Complete the approval route and check Celia's history. Submit Final only after Initial approval. Repeat for Paolo. Their histories should stay separate.
2. **Quarterly:** As Antonio, check form readiness, choose From/To dates and a future self-rating deadline, then start the BD cycle. Antonio, Emilio, and Rina should join. Submit self-ratings, perform BD-only peer 360 ratings, and complete immediate-head KRA reviews. Isabella reviews Antonio's KRA but is not a BD peer 360 rater. Approve as Antonio when ready and check Quarterly History.
3. **Annual:** Repeat with separate Annual forms. Confirm Annual and Quarterly results appear in their own histories.

Celia and Paolo must stay outside regular cycles while they are probationary. For a failed step, record the account, page, expected result, actual result, and a screenshot.

Raquel Pawnshop's [2016 company publication](https://www.raquelpawnshop.com/insider/Insider_21st_2016.pdf) describes three decades of operation. The mock hire dates are later than its approximate 1986 start and are **not** real employee records.
