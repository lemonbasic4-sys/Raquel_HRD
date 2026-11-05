# Operations evaluation test

This folder provides a fictional Operations reporting chain and two probationary Branch Staff I employees. The President/CEO is included to create and review the VP's form, but is outside the Operations cycle. The VP for Operations starts and approves that cycle.

## Import on a fresh test database

Start Apache and MySQL. Open PowerShell in `C:\xampp\htdocs\Raquel_HRD`.

**The first command deletes the current `raquel_hris` database.** Back it up first. Run each file once, in order:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u root -e 'source database/1st_schema_tables.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source database/2nd_seed_organization.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/operations/01_ops_test_people.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/operations/02_verify_fixture.sql;'
```

Stop if a command reports an error. The verification file only reads data, so you may run it again.

## Test people

| Account | Person | Position | Status | Hire date |
| --- | --- | --- | --- | --- |
| `test.ops.ceo` | Isabella Villanueva | President and CEO | Regular | 2007-04-02 |
| `test.ops.vp` | Vera Santos | VP for Operations | Regular | 2012-06-04 |
| `test.ops.manager` | Ramon Reyes | Regional Manager I | Regular | 2016-08-01 |
| `test.ops.supervisor` | Arman Cruz | Area Coordinator I | Regular | 2020-03-02 |
| `test.ops.focal` | Fiona Garcia | Focal Person I | Regular | 2023-05-08 |
| `test.ops.branch` | Berto Mendoza | Branch Staff I | Regular | 2021-08-02 |
| `test.ops.nica` | Nica Navarro | Branch Staff I | Probationary | 2026-09-01 |
| `test.ops.enzo` | Enzo Dela Cruz | Branch Staff I | Probationary | 2026-10-01 |

Local test password: `password`. The app may require a password change on first login. All personal details are fictional. Fields that do not apply, such as separation dates for active employees, are empty.

The verification report should show **eight people, eight accounts, sixteen addresses, and eight rows for each other listed profile section**.

## Create the forms

For an automatic dry run, import the [template seed](../templates/README.md) after this department's employee SQL. It creates the forms listed below with 12 KRAs and the eight default behaviors. Skip manual form creation when using that seed.

Create separate Active forms for each type below. Target **Operations**. Use at least 12 job-relevant KRAs totaling 100%, the eight Core Behaviors & Values, and the 80% KRA / 20% behavior split.

| Sign in as | Form target | Types |
| --- | --- | --- |
| Isabella | VP for Operations | Quarterly, Annual |
| Vera | Regional Manager I | Quarterly, Annual |
| Ramon | Area Coordinator I | Quarterly, Annual |
| Arman | Focal Person I | Quarterly, Annual |
| Fiona | Branch Staff I | Initial, Final, Quarterly, Annual |

Nica and Enzo use the Branch Staff I Initial/Final forms. Berto uses its Quarterly/Annual forms.

## Test order

1. **Probationary:** As Nica, submit an Initial self-rating. Fiona reviews it. Complete the approval route and check Nica's history. Submit Final after Initial approval. Repeat for Enzo.
2. **Quarterly:** As Vera, confirm form readiness, choose From/To dates and a future self-rating deadline, then start the Operations cycle. Vera, Ramon, Arman, Fiona, and Berto should join. Submit self-ratings, perform Operations-only peer 360 ratings, and complete immediate-head KRA reviews. Isabella reviews Vera's KRA but is not an Operations peer 360 rater. Approve as Vera when ready and check Quarterly History.
3. **Annual:** Repeat with separate Annual forms. Confirm Annual and Quarterly results appear in their own histories.

Nica and Enzo must stay outside regular cycles while probationary. For a failed step, record the account, page, expected result, actual result, and a screenshot.
