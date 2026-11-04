# Office of the President evaluation test

This folder provides a fictional President/CEO, five regular Executive Assistants, and two probationary Executive Assistants. The President/CEO owns the department's Quarterly and Annual cycles, creates the assistant forms, and reviews assistant KRAs. The President/CEO is excluded from their own evaluation roster because there is no immediate head configured for that position.

## Import on a fresh test database

Start Apache and MySQL. Open PowerShell in `C:\xampp\htdocs\Raquel_HRD`.

**The first command deletes the current `raquel_hris` database.** Back it up first. Run each file once, in order:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u root -e 'source database/1st_schema_tables.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source database/2nd_seed_organization.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/office_of_the_president/01_op_test_people.sql;'
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/office_of_the_president/02_verify_fixture.sql;'
```

Stop if a command reports an error. The verification file only reads data, so you may run it again.

## Test people

| Account | Person | Position | Status | Hire date |
| --- | --- | --- | --- | --- |
| `test.op.ceo` | Isabella Villanueva | President and CEO | Regular | 2007-04-02 |
| `test.op.assistant1` | Mara Santos | Executive Assistant I | Regular | 2012-06-04 |
| `test.op.assistant2` | Rico Reyes | Executive Assistant II | Regular | 2016-08-01 |
| `test.op.assistant3` | Dario Cruz | Executive Assistant III | Regular | 2020-03-02 |
| `test.op.assistant1b` | Rhea Garcia | Executive Assistant I | Regular | 2023-05-08 |
| `test.op.assistant2b` | Faye Mendoza | Executive Assistant II | Regular | 2021-08-02 |
| `test.op.ella` | Ella Navarro | Executive Assistant I | Probationary | 2026-09-01 |
| `test.op.leo` | Leo Dela Cruz | Executive Assistant I | Probationary | 2026-10-01 |

Local test password: `password`. The app may require a password change on first login. All personal details are fictional. Nonapplicable fields, such as separation dates for active employees, are empty.

The verification report should show **eight people, eight accounts, sixteen addresses, and eight rows for each other listed profile section**.

## Create the forms

Sign in as Isabella. In **Evaluation Templates**, create separate Active forms for Executive Assistant I, II, and III, targeting **Office of the President**. For each position, create Quarterly and Annual forms. Also create Initial and Final forms for Executive Assistant I. Use 11 job-relevant KRAs totaling 100%, the eight Core Behaviors & Values, and the 80% KRA / 20% behavior split.

Mara and Rhea share the Executive Assistant I Quarterly/Annual form. Rico and Faye share the Executive Assistant II form. Ella and Leo use the Executive Assistant I Initial/Final forms.

## Test order

1. **Probationary:** As Ella, submit an Initial self-rating. Isabella reviews and completes the approval route. Check Ella's history. Submit Final after Initial approval. Repeat for Leo.
2. **Quarterly:** As Isabella, confirm form readiness, choose From/To dates and a future self-rating deadline, then start the cycle. The five regular assistants should join; Isabella should remain outside the roster. Have the assistants submit self-ratings and rate their department peers. Isabella reviews their KRAs and approves the cycle when complete. Check Quarterly History.
3. **Annual:** Repeat with separate Annual forms. Confirm Annual and Quarterly results appear in their own histories.

Ella and Leo must stay outside regular cycles while probationary. For a failed step, record the account, page, expected result, actual result, and a screenshot.
