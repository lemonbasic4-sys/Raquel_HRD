# Marketing evaluation test

This folder provides a small mock Marketing team with **two probationary Marketing Staff I employees**. The regular team includes Marketing Manager I and II, Marketing Supervisor I, a Marketing Staff I, and Marketing Supervisor II. A President and CEO account is included because that position reviews Marketing Manager I's KRA.

## Import on a fresh test database

Start Apache and MySQL. Open PowerShell in `C:\xampp\htdocs\Raquel_HRD`.

**The first command deletes the current `raquel_hris` database.** Back it up first. Run each file once, in this order:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u root -e 'source database/1st_schema_tables.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source database/2nd_seed_organization.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/marketing/01_marketing_test_people.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/marketing/02_verify_fixture.sql;'
```

Stop if a command reports an error. The verification file only reads data, so you may run it again.

## Test people

| Account | Person | Position | Status | Hire date |
| --- | --- | --- | --- | --- |
| `test.marketing.ceo` | Isabella Villanueva | President and CEO | Regular | 2007-04-02 |
| `test.marketing.manager1` | Celia Santos | Marketing Manager I | Regular | 2012-06-04 |
| `test.marketing.manager2` | Marco Reyes | Marketing Manager II | Regular | 2016-08-01 |
| `test.marketing.supervisor` | Dennis Cruz | Marketing Supervisor I | Regular | 2020-03-02 |
| `test.marketing.staff1` | Rina Garcia | Marketing Staff I | Regular | 2023-05-08 |
| `test.marketing.supervisor2` | Felix Mendoza | Marketing Supervisor II | Regular | 2021-08-02 |
| `test.marketing.erika` | Erika Navarro | Marketing Staff I | Probationary | 2026-09-01 |
| `test.marketing.luis` | Luis Dela Cruz | Marketing Staff I | Probationary | 2026-10-01 |

Local test password: `password`. The app may require a password change on first login. Isabella creates and reviews Celia's form but is **outside Marketing**. Celia starts and approves the Marketing cycle. Marketing 360 ratings remain within Marketing.

The verification report should show **eight people, eight accounts, sixteen addresses, and eight rows for each other listed profile section**. All personal details are fictional. Fields that do not apply, such as separation dates for active employees, are empty.

## Create the forms

In **Evaluation Templates**, create separate Active forms for every type below. Target **Marketing**. Use 11 job-relevant KRA items totaling 100%, the system's eight Core Behaviors & Values, and the 80% KRA / 20% behavior split.

| Sign in as | Form target | Types |
| --- | --- | --- |
| Isabella | Marketing Manager I | Quarterly, Annual |
| Celia | Marketing Manager II | Quarterly, Annual |
| Celia | Marketing Supervisor I | Quarterly, Annual |
| Dennis | Marketing Staff I | Initial, Final, Quarterly, Annual |
| Celia | Marketing Supervisor II | Quarterly, Annual |

Erika and Luis use the Initial/Final Marketing Staff I forms. Rina uses the Quarterly/Annual Marketing Staff I forms while the other two remain probationary.

## Test order

1. **Probationary:** As Erika, submit Initial self-rating. Dennis reviews it. Complete the approval route and check Erika's history. Submit Final only after Initial approval. Repeat for Luis. Their results should stay separate.
2. **Quarterly:** As Celia, check form readiness, choose From/To dates and a future self-rating deadline, then start the Marketing cycle. Celia, Marco, Dennis, Rina, and Felix should join. Submit self-ratings, perform Marketing-only peer 360 ratings, and complete immediate-head KRA reviews. Isabella reviews Celia's KRA but is not a Marketing peer 360 rater. Approve as Celia when ready and check Quarterly History.
3. **Annual:** Repeat with separate Annual forms. Confirm Annual and Quarterly results appear in their own histories.

Erika and Luis must stay outside regular cycles while probationary. For a failed step, record the account, page, expected result, actual result, and a screenshot.

Raquel Pawnshop's [2016 company publication](https://www.raquelpawnshop.com/insider/Insider_21st_2016.pdf) describes three decades of operation. The mock hire dates are later than its approximate 1986 start and are **not** real employee records.
