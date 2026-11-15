# AP Quarterly evaluation templates - batch import for testing

## 1. Rank and File (AP Staff I-V)

Start MySQL in XAMPP. Copy and paste this entire block into **PowerShell**:

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source evaluation/Acquired_Properties_Quarterly/seed_ap_rank_and_file_quarterly_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw 'AP Quarterly template import failed' }

Write-Host "AP QUARTERLY TEMPLATE IMPORTED" -ForegroundColor Green
```

This adds one Active **Quarterly** template for AP Staff I-V (R&F), with 8 KRA items, 8 standard Behavior items, and an 80%/20% overall split. Period From: **2026-11-15**. Period To: **2027-02-14**. The creator is the seeded AP Supervisor I account `MO26-AP-H`.

To check that the import worked, copy and paste this separate block:

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root raquel_hris -e "SELECT t.template_id, t.template_name, t.evaluation_type, t.assessment_from, t.assessment_to, t.status, (SELECT COUNT(*) FROM evaluation_template_positions p WHERE p.template_id=t.template_id) AS target_positions, (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA') AS kra_items, (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='Behavior') AS behavior_items FROM evaluation_templates t WHERE t.form_code='AP-RF-Q-DRYRUN-20261115';"
```

Expected: **5 target positions, 8 KRA items, 8 Behavior items**. The seed imports into the existing database and stops if the same form was already imported. It does not start an evaluation or reset the database.

## 2. Immediate Heads (AP Supervisor I-III)

After the R&F import, copy and paste this block into **PowerShell**:

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source evaluation/Acquired_Properties_Quarterly/seed_ap_immediate_heads_quarterly_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw 'AP immediate-head Quarterly template import failed' }

Write-Host "AP IMMEDIATE-HEAD QUARTERLY TEMPLATE IMPORTED" -ForegroundColor Green
```

This adds one Active **Quarterly** template for AP Supervisor I-III, created by AP Manager I account `MO26-AP-M`. Its 8 supervisory KRAs total **100%** within the KRA section; it also has the standard 8 Behavior items and the **80% KRA / 20% Behavior** overall split. Period From: **2026-11-15**. Period To: **2027-02-14**. It does not create evaluations or reset the database.

To verify the immediate-head form, copy and paste this separate block:

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root raquel_hris -e "SELECT t.template_id, t.template_name, t.evaluation_type, t.assessment_from, t.assessment_to, t.status, (SELECT COUNT(*) FROM evaluation_template_positions p WHERE p.template_id=t.template_id) AS target_positions, (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA') AS kra_items, (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='Behavior') AS behavior_items FROM evaluation_templates t WHERE t.form_code='AP-HEAD-Q-DRYRUN-20261115';"
```

Expected: **3 target positions, 8 KRA items, 8 Behavior items**. A repeated import stops with a duplicate-form error.
