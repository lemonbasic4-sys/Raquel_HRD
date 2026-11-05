# Finance evaluation test

This folder provides a small mock Finance team with **two probationary Accounting Staff I employees**. The regular team includes both Accounting and Treasury so you can test both reporting branches in Quarterly and Annual cycles.

## Import on a fresh test database

Start Apache and MySQL. Open PowerShell in `C:\xampp\htdocs\Raquel_HRD`.

**The first command deletes the current `raquel_hris` database.** Back it up first. Run each file once, in this order:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u root -e 'source database/1st_schema_tables.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source database/2nd_seed_organization.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/finance/01_finance_test_people.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/finance/02_verify_fixture.sql;'
```

Stop if a command reports an error. The verification file only reads data, so you may run it again.

## Test people

| Account | Person | Position | Status | Hire date |
| --- | --- | --- | --- | --- |
| `test.finance.ceo` | Isabella Villanueva | President and CEO | Regular | 2007-04-02 |
| `test.finance.vp` | Veronica Santos | VP for Finance | Regular | 2012-06-04 |
| `test.finance.accounting.manager` | Mateo Reyes | Accounting Manager I | Regular | 2016-08-01 |
| `test.finance.accounting.supervisor` | Julian Cruz | Accounting Supervisor I | Regular | 2020-03-02 |
| `test.finance.accounting.staff` | Diana Garcia | Accounting Staff I | Regular | 2023-05-08 |
| `test.finance.treasury.supervisor` | Grace Valdez | Treasury Supervisor I | Regular | 2018-02-05 |
| `test.finance.treasury.staff` | Nico Flores | Treasury Staff I | Regular | 2022-08-01 |
| `test.finance.alma` | Alma Navarro | Accounting Staff I | Probationary | 2026-09-01 |
| `test.finance.leo` | Leo Dela Cruz | Accounting Staff I | Probationary | 2026-10-01 |

Local test password: `password`. The app may require a password change on first login. Isabella creates and reviews Veronica's form, but Isabella is outside the Finance cycle. Veronica starts and approves Finance cycles.

The verification report should show **nine people, nine accounts, eighteen addresses, and nine rows for each other listed profile section**. All personal details are fictional. Fields that do not apply, such as separation dates for active employees, are empty.

## Create the forms

For an automatic dry run, import the [template seed](../templates/README.md) after this department's employee SQL. It creates the forms listed below with 12 KRAs and the eight default behaviors. Skip manual form creation when using that seed.

In **Evaluation Templates**, create separate Active forms for every type below. Target **Finance**. Use at least 12 job-relevant KRA items totaling 100%, the system's eight Core Behaviors & Values, and the 80% KRA / 20% behavior split.

| Sign in as | Form target | Types |
| --- | --- | --- |
| Isabella | VP for Finance | Quarterly, Annual |
| Veronica | Accounting Manager I | Quarterly, Annual |
| Veronica | Treasury Supervisor I | Quarterly, Annual |
| Mateo | Accounting Supervisor I | Quarterly, Annual |
| Julian | Accounting Staff I | Initial, Final, Quarterly, Annual |
| Grace | Treasury Staff I | Quarterly, Annual |

Alma and Leo use the Initial/Final Accounting Staff I forms. Diana uses the Quarterly/Annual Accounting Staff I forms while they remain probationary.

## Test order

1. **Probationary:** As Alma, submit Initial self-rating. Julian reviews it. Complete the approval route and check Alma's history. Submit Final only after Initial approval. Repeat for Leo. Their results should stay separate.
2. **Quarterly:** As Veronica, check form readiness, choose From/To dates and a future self-rating deadline, then start the Finance cycle. Veronica, Mateo, Julian, Diana, Grace, and Nico should join. Submit self-ratings, perform Finance-only peer 360 ratings, and complete each immediate-head KRA review. Isabella reviews Veronica's KRA but is not a Finance peer 360 rater. Approve as Veronica when ready and check Quarterly History.
3. **Annual:** Repeat with separate Annual forms. Confirm Annual and Quarterly results appear in their own histories.

Alma and Leo must stay outside regular cycles while probationary. For a failed step, record the account, page, expected result, actual result, and a screenshot.

Raquel Pawnshop's [2016 company publication](https://www.raquelpawnshop.com/insider/Insider_21st_2016.pdf) describes three decades of operation. The mock hire dates are later than its approximate 1986 start and are **not** real employee records.
