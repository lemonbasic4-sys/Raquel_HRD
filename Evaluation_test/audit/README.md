# Audit evaluation test

This folder provides a small mock Audit team with **two probationary Auditor I employees**. Use it to test Initial and Final evaluations, then separate Quarterly and Annual cycles for regular Audit employees.

## Import on a fresh test database

Start Apache and MySQL. Open PowerShell in `C:\xampp\htdocs\Raquel_HRD`.

**The first command deletes the current `raquel_hris` database.** Back it up first. Run each file once, in this order:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u root -e 'source database/1st_schema_tables.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source database/2nd_seed_organization.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/audit/01_audit_test_people.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/audit/02_verify_fixture.sql;'
```

Stop if a command reports an error. The verification file only reads data, so you may run it again.

## Test people

| Account | Person | Position | Status | Hire date |
| --- | --- | --- | --- | --- |
| `test.audit.ceo` | Isabella Villanueva | President and CEO | Regular | 2007-04-02 |
| `test.audit.manager1` | Nadine Santos | Audit Manager I | Regular | 2012-06-04 |
| `test.audit.manager2` | Rafael Reyes | Audit Manager II | Regular | 2016-08-01 |
| `test.audit.supervisor` | Jonas Cruz | Audit Supervisor I | Regular | 2020-03-02 |
| `test.audit.auditor` | Tessa Garcia | Auditor I | Regular | 2023-05-08 |
| `test.audit.iris` | Iris Navarro | Auditor I | Probationary | 2026-09-01 |
| `test.audit.paolo` | Paolo Dela Cruz | Auditor I | Probationary | 2026-10-01 |

Local test password: `password`. The app may ask you to change it at first login. Isabella only creates and reviews Nadine's form; she is outside the Audit cycle. Nadine starts and approves Audit cycles. The reporting chain is Isabella → Nadine → Rafael → Jonas → Auditor I.

The verification report should show **seven people, seven accounts, fourteen addresses, and seven rows for each other listed profile section**. These profiles are fictional. Fields that do not apply, such as a separation date for an active employee, are empty.

## Create the forms

In **Evaluation Templates**, create separate Active forms for every type below. Target **Audit**. Use 11 job-relevant KRA items totaling 100%, the system's eight Core Behaviors & Values, and the 80% KRA / 20% behavior split.

| Sign in as | Form target | Types |
| --- | --- | --- |
| Isabella | Audit Manager I | Quarterly, Annual |
| Nadine | Audit Manager II | Quarterly, Annual |
| Rafael | Audit Supervisor I | Quarterly, Annual |
| Jonas | Auditor I | Initial, Final, Quarterly, Annual |

Iris and Paolo use the Initial/Final Auditor I forms. Tessa uses the Quarterly/Annual Auditor I forms while the other two remain probationary.

## Test order

1. **Probationary:** As Iris, submit Initial self-rating. Jonas reviews it. Complete the approval route and check Iris's history. Submit Final only after Initial approval. Repeat for Paolo. Their results should remain separate.
2. **Quarterly:** As Nadine, check form readiness, choose From/To dates and a future self-rating deadline, then start the Audit cycle. Nadine, Rafael, Jonas, and Tessa should join. Submit self-ratings, perform Audit-only peer 360 ratings, and complete immediate-head KRA reviews. Isabella reviews Nadine's KRA but is not an Audit peer 360 rater. Approve as Nadine when ready and check Quarterly History.
3. **Annual:** Repeat with separate Annual forms. Confirm Annual and Quarterly results appear in their own histories.

Iris and Paolo must stay outside regular cycles while they are probationary. For a failed step, record the account, page, expected result, actual result, and a screenshot.

Raquel Pawnshop's [2016 company publication](https://www.raquelpawnshop.com/insider/Insider_21st_2016.pdf) describes three decades of operation. The mock hire dates are later than its approximate 1986 start and are **not** real employee records.
