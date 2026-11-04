# Information Technology evaluation test

This folder provides a small mock IT team with **two probationary Programmer I employees**. The regular team includes IT Manager I and II, IT Supervisor I, a Programmer, and Technical Support Staff. A VP for Operations account is included because that position reviews IT Manager I's KRA.

## Import on a fresh test database

Start Apache and MySQL. Open PowerShell in `C:\xampp\htdocs\Raquel_HRD`.

**The first command deletes the current `raquel_hris` database.** Back it up first. Run each file once, in this order:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u root -e 'source database/1st_schema_tables.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source database/2nd_seed_organization.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/information_technology/01_it_test_people.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/information_technology/02_verify_fixture.sql;'
```

Stop if a command reports an error. The verification file only reads data, so you may run it again.

## Test people

| Account | Person | Position | Status | Hire date |
| --- | --- | --- | --- | --- |
| `test.it.ops_vp` | Olivia Villanueva | VP for Operations | Regular | 2007-04-02 |
| `test.it.manager1` | Nadine Santos | IT Manager I | Regular | 2012-06-04 |
| `test.it.manager2` | Rafael Reyes | IT Manager II | Regular | 2016-08-01 |
| `test.it.supervisor` | Jonas Cruz | IT Supervisor I | Regular | 2020-03-02 |
| `test.it.programmer` | Tessa Garcia | Programmer I | Regular | 2023-05-08 |
| `test.it.techsupport` | Gabriel Mendoza | Technical Support Staff I | Regular | 2021-08-02 |
| `test.it.iris` | Iris Navarro | Programmer I | Probationary | 2026-09-01 |
| `test.it.paolo` | Paolo Dela Cruz | Programmer I | Probationary | 2026-10-01 |

Local test password: `password`. The app may require a password change on first login. Olivia creates and reviews Nadine's form but is **outside IT**. Nadine starts and approves the IT cycle. IT 360 ratings remain within IT.

The verification report should show **eight people, eight accounts, sixteen addresses, and eight rows for each other listed profile section**. All personal details are fictional. Fields that do not apply, such as separation dates for active employees, are empty.

## Create the forms

In **Evaluation Templates**, create separate Active forms for every type below. Target **Information Technology**. Use 11 job-relevant KRA items totaling 100%, the system's eight Core Behaviors & Values, and the 80% KRA / 20% behavior split.

| Sign in as | Form target | Types |
| --- | --- | --- |
| Olivia | IT Manager I | Quarterly, Annual |
| Nadine | IT Manager II | Quarterly, Annual |
| Nadine | IT Supervisor I | Quarterly, Annual |
| Jonas | Programmer I | Initial, Final, Quarterly, Annual |
| Jonas | Technical Support Staff I | Quarterly, Annual |

Iris and Paolo use the Initial/Final Programmer I forms. Tessa uses the Quarterly/Annual Programmer I forms while the other two remain probationary.

## Test order

1. **Probationary:** As Iris, submit Initial self-rating. Jonas reviews it. Complete the approval route and check Iris's history. Submit Final only after Initial approval. Repeat for Paolo. Their results should stay separate.
2. **Quarterly:** As Nadine, check form readiness, choose From/To dates and a future self-rating deadline, then start the IT cycle. Nadine, Rafael, Jonas, Tessa, and Gabriel should join. Submit self-ratings, perform IT-only peer 360 ratings, and complete immediate-head KRA reviews. Olivia reviews Nadine's KRA but is not an IT peer 360 rater. Approve as Nadine when ready and check Quarterly History.
3. **Annual:** Repeat with separate Annual forms. Confirm Annual and Quarterly results appear in their own histories.

Iris and Paolo must stay outside regular cycles while probationary. For a failed step, record the account, page, expected result, actual result, and a screenshot.

Raquel Pawnshop's [2016 company publication](https://www.raquelpawnshop.com/insider/Insider_21st_2016.pdf) describes three decades of operation. The mock hire dates are later than its approximate 1986 start and are **not** real employee records.
