# Evaluation test templates

This folder contains **mock evaluation forms** for the employees in the department fixtures. It does not add employees. The form author is the target position's active immediate head. Each form has **12 KRAs totaling 100%** and the system's **eight Core Behaviors & Values**. The form uses the 80% KRA / 20% behavior split.

The seed creates separate **Initial** and **Final** forms for positions with probationary employees. It creates separate **Quarterly** and **Annual** forms for every position represented in the selected department fixture, including positions whose current employees are still probationary. The President/CEO has no self-evaluation form because that position has no immediate head in this test setup.

## Import order

Use a **fresh disposable `raquel_hris` database**. Follow one department's `Evaluation_test/<department>/README.md` through its employee import. Then run:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/templates/01_seed_templates.sql;'
if ($LASTEXITCODE -ne 0) { throw 'Template import failed' }
& 'C:\xampp\mysql\bin\mysql.exe' -u root raquel_hris -e 'source Evaluation_test/templates/02_verify_templates.sql;'
if ($LASTEXITCODE -ne 0) { throw 'Template verification failed' }
```

Run the template seed **once** after the employee fixture. The department README's manual **Create the forms** step is replaced by this import. Check that `invalid_forms` is `0`; the report lists the author and the 12/8 item counts for every form. You can rerun the read-only verification query.

These KRAs are test content for a workflow dry run. Their names follow each department's work, and the target position is included in every KRA name. Replace them with approved, measurable position requirements before any real evaluation.

`build_templates.py` regenerates `01_seed_templates.sql` when the test position list or KRA labels change:

```powershell
python Evaluation_test/templates/build_templates.py
```
