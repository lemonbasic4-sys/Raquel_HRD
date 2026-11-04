# Acquired Properties evaluation test

This folder has a small, fictional AP team for testing probationary Initial/Final evaluations and regular Quarterly/Annual cycles. It has **two probationary AP Staff I employees**. All details are mock data.

## Import on a fresh test database

Start Apache and MySQL. Open PowerShell in `C:\xampp\htdocs\Raquel_HRD`.

**The first command deletes the current `raquel_hris` database.** Back it up first. Run these files once, in order:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u root -e 'source database/1st_schema_tables.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source database/2nd_seed_organization.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/acquired_properties/01_ap_test_people.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/acquired_properties/02_verify_fixture.sql;'
```

Stop if a command reports an error. The last file only reads data and can be run again.

## Test people

| Account | Person | Position | Status | Hire date |
| --- | --- | --- | --- | --- |
| `test.ap.ceo` | Isabella Villanueva | President and CEO | Regular | 2007-04-02 |
| `test.ap.vp` | Marisol Santos | VP for Acquired Properties | Regular | 2012-06-04 |
| `test.ap.manager` | Adrian Reyes | AP Manager I | Regular | 2016-08-01 |
| `test.ap.supervisor` | Nathan Cruz | AP Supervisor I | Regular | 2020-03-02 |
| `test.ap.staff` | Bianca Garcia | AP Staff I | Regular | 2023-05-08 |
| `test.ap.lea` | Lea Navarro | AP Staff I | Probationary | 2026-09-01 |
| `test.ap.marco` | Marco Dela Cruz | AP Staff I | Probationary | 2026-10-01 |

Local test password: `password`. The app may require a password change on first login. Isabella is present to create/review the VP form; she is outside the AP cycle. The AP Manager starts and approves AP cycles. The VP reviews the AP Manager's KRA. Within-department 360 ratings stay within AP.

`02_verify_fixture.sql` should show **seven people, seven accounts, fourteen addresses, and seven rows for each other listed profile section**. Fields that do not apply, such as separation dates for active employees, are empty.

## Create the forms

Use the portal's **Evaluation Templates** screen. Create separate Active forms for each type. Use the system's eight Core Behaviors & Values, 11 job-relevant KRA items totaling 100%, and the 80% KRA / 20% behavior split.

| Sign in as | Form target | Types |
| --- | --- | --- |
| Isabella | VP for Acquired Properties | Quarterly, Annual |
| Marisol | AP Manager I | Quarterly, Annual |
| Adrian | AP Supervisor I | Quarterly, Annual |
| Nathan | AP Staff I | Initial, Final, Quarterly, Annual |

All forms target **Acquired Properties**. The Initial/Final forms are for Lea and Marco. The Quarterly/Annual AP Staff I forms are for Bianca while Lea and Marco remain probationary.

## Test order

1. **Probationary:** Sign in as Lea. Submit Initial self-rating. Nathan reviews it; complete the portal's approval route. Confirm the result in Lea's history. Then submit Final after Initial approval. Repeat for Marco and check that their histories stay separate.
2. **Quarterly:** As Adrian, check AP form readiness. Choose From/To dates and a future self-rating deadline, then start the AP cycle. Only Marisol, Adrian, Nathan, and Bianca should join the regular roster. Submit their self-ratings, complete AP peer 360 and immediate-head KRA reviews, and approve as Adrian when ready. Check Quarterly History.
3. **Annual:** Repeat with separate Annual forms and the Annual cycle. Check that Annual and Quarterly results remain separate.

The CEO may review Marisol's KRA but should not be counted as an AP peer 360 rater. Lea and Marco must stay outside the regular cycle until actually regularized. Record any failed step with the account, page, expected result, actual result, and screenshot.

Raquel Pawnshop's [2016 company publication](https://www.raquelpawnshop.com/insider/Insider_21st_2016.pdf) describes three decades of operation. The mock hire dates are later than its approximate 1986 start and are **not** actual employee records.
