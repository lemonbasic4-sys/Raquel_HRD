# Purchasing evaluation test

This folder provides a fictional Procurement Supervisor I, three regular Purchasing Staff I employees, and two probationary Purchasing Staff I employees. A General Services Manager I account is included because that position is the supervisor's configured immediate head. The Procurement Supervisor starts and approves the Purchasing Quarterly and Annual cycles.

## Import on a fresh test database

Start Apache and MySQL. Open PowerShell in `C:\xampp\htdocs\Raquel_HRD`.

**The first command deletes the current `raquel_hris` database.** Back it up first. Run each file once, in order:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u root -e 'source database/1st_schema_tables.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source database/2nd_seed_organization.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/purchasing/01_purchasing_test_people.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/purchasing/02_verify_fixture.sql;'
```

Stop if a command reports an error. The verification file only reads data, so you may run it again.

## Test people

| Account | Person | Position | Status | Hire date |
| --- | --- | --- | --- | --- |
| `test.purchasing.gs_manager` | Isabella Villanueva | GS Manager I | Regular | 2007-04-02 |
| `test.purchasing.supervisor` | Mila Santos | Procurement Supervisor I | Regular | 2012-06-04 |
| `test.purchasing.staff1` | Vince Reyes | Purchasing Staff I | Regular | 2016-08-01 |
| `test.purchasing.staff2` | Dante Cruz | Purchasing Staff I | Regular | 2020-03-02 |
| `test.purchasing.staff3` | Rosalie Garcia | Purchasing Staff I | Regular | 2023-05-08 |
| `test.purchasing.cora` | Cora Navarro | Purchasing Staff I | Probationary | 2026-09-01 |
| `test.purchasing.gio` | Gio Dela Cruz | Purchasing Staff I | Probationary | 2026-10-01 |

Local test password: `password`. The app may require a password change on first login. All personal details are fictional. Nonapplicable fields, such as separation dates for active employees, are empty.

The verification report should show **seven people, seven accounts, fourteen addresses, and seven rows for each other listed profile section**.

## Create the forms

Create separate Active forms for each type below. Target **Purchasing**. Use 11 job-relevant KRAs totaling 100%, the eight Core Behaviors & Values, and the 80% KRA / 20% behavior split.

| Sign in as | Form target | Types |
| --- | --- | --- |
| Isabella | Procurement Supervisor I | Quarterly, Annual |
| Mila | Purchasing Staff I | Initial, Final, Quarterly, Annual |

Cora and Gio use the Initial/Final Purchasing Staff I forms. Vince, Dante, and Rosalie use its Quarterly/Annual forms.

## Test order

1. **Probationary:** As Cora, submit an Initial self-rating. Mila reviews it. Complete the approval route and check Cora's history. Submit Final after Initial approval. Repeat for Gio.
2. **Quarterly:** As Mila, confirm form readiness, choose From/To dates and a future self-rating deadline, then start the Purchasing cycle. Mila, Vince, Dante, and Rosalie should join. Submit self-ratings, perform Purchasing-only peer 360 ratings, and complete immediate-head KRA reviews. Isabella reviews Mila's KRA but is not a Purchasing peer 360 rater. Approve as Mila when ready and check Quarterly History.
3. **Annual:** Repeat with separate Annual forms. Confirm Annual and Quarterly results appear in their own histories.

Cora and Gio must stay outside regular cycles while probationary. For a failed step, record the account, page, expected result, actual result, and a screenshot.
