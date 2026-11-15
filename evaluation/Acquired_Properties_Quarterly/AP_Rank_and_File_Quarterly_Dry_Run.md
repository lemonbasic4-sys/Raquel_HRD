# AP Quarterly evaluation template - batch import for testing

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
