# Compliance evaluation test

This folder provides a small mock Compliance team with **two probationary Compliance Staff I employees**. Use it to test Initial and Final evaluations, then separate Quarterly and Annual cycles for regular employees.

## Import on a fresh test database

Start Apache and MySQL. Open PowerShell in `C:\xampp\htdocs\Raquel_HRD`.

**The first command deletes the current `raquel_hris` database.** Back it up first. Run each file once, in this order:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u root -e 'source database/1st_schema_tables.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source database/2nd_seed_organization.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/compliance/01_compliance_test_people.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/compliance/02_verify_fixture.sql;'
```

Stop if a command reports an error. The verification file only reads data, so you may run it again.

## Test people

| Account | Person | Position | Status | Hire date |
| --- | --- | --- | --- | --- |
| `test.compliance.ceo` | Isabella Villanueva | President and CEO | Regular | 2007-04-02 |
| `test.compliance.supervisor1` | Michelle Santos | Compliance Supervisor I | Regular | 2012-06-04 |
| `test.compliance.supervisor2` | Victor Reyes | Compliance Supervisor II | Regular | 2016-08-01 |
| `test.compliance.staff1` | Daniel Cruz | Compliance Staff I | Regular | 2020-03-02 |
| `test.compliance.staff2` | Rosario Garcia | Compliance Staff II | Regular | 2023-05-08 |
| `test.compliance.carla` | Carla Navarro | Compliance Staff I | Probationary | 2026-09-01 |
| `test.compliance.george` | George Dela Cruz | Compliance Staff I | Probationary | 2026-10-01 |

Local test password: `password`. The app may ask you to change it at first login. Isabella creates and reviews Michelle's form but is outside the Compliance cycle. Michelle starts and approves Compliance cycles. The reporting chain is Isabella → Michelle → Victor → Compliance Staff I and II.

The verification report should show **seven people, seven accounts, fourteen addresses, and seven rows for each other listed profile section**. These profiles are fictional. Fields that do not apply, such as separation dates for active employees, are empty.

## Create the forms

In **Evaluation Templates**, create separate Active forms for every type below. Target **Compliance**. Use 11 job-relevant KRA items totaling 100%, the system's eight Core Behaviors & Values, and the 80% KRA / 20% behavior split.

| Sign in as | Form target | Types |
| --- | --- | --- |
| Isabella | Compliance Supervisor I | Quarterly, Annual |
| Michelle | Compliance Supervisor II | Quarterly, Annual |
| Victor | Compliance Staff I | Initial, Final, Quarterly, Annual |
| Victor | Compliance Staff II | Quarterly, Annual |

Carla and George use the Initial/Final Staff I forms. Daniel uses the Quarterly/Annual Staff I forms while the other two remain probationary.

## Test order

1. **Probationary:** As Carla, submit Initial self-rating. Victor reviews it. Complete the approval route and check Carla's history. Submit Final only after Initial approval. Repeat for George. Their results should stay separate.
2. **Quarterly:** As Michelle, check form readiness, choose From/To dates and a future self-rating deadline, then start the Compliance cycle. Michelle, Victor, Daniel, and Rosario should join. Submit self-ratings, perform Compliance-only peer 360 ratings, and complete immediate-head KRA reviews. Isabella reviews Michelle's KRA but is not a Compliance peer 360 rater. Approve as Michelle when ready and check Quarterly History.
3. **Annual:** Repeat with separate Annual forms. Confirm Annual and Quarterly results appear in their own histories.

Carla and George must stay outside regular cycles while they are probationary. For a failed step, record the account, page, expected result, actual result, and a screenshot.

Raquel Pawnshop's [2016 company publication](https://www.raquelpawnshop.com/insider/Insider_21st_2016.pdf) describes three decades of operation. The mock hire dates are later than its approximate 1986 start and are **not** real employee records.
