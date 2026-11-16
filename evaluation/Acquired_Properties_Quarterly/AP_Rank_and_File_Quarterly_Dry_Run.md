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

## 3. Assign a Quarterly self-rating to AP Supervisor I (Adrian Reyes)

Run Section 2 first if the immediate-head template has not been imported. Then copy and paste this block into **PowerShell**:

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source evaluation/Acquired_Properties_Quarterly/seed_ap_supervisor_quarterly_evaluation.sql"
if ($LASTEXITCODE -ne 0) { throw 'AP Supervisor Quarterly evaluation assignment failed' }

Write-Host "AP SUPERVISOR QUARTERLY EVALUATION ASSIGNED" -ForegroundColor Green
```

This creates **one Pending Self-Rating** entry for Adrian Reyes (`MO26-AP-H`), assigned by AP Manager I (`MO26-AP-M`). It uses the immediate-head template and the **2026-11-15 to 2027-02-14** period. Adrian can open **Employee Portal → Self Rating** to fill it in. Scores and approvals remain empty until the normal evaluation workflow runs. Reimporting the same assignment stops with an error.

To verify the assignment:

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root raquel_hris -e "SELECT ev.evaluation_id, e.employee_code, t.form_code, ev.evaluation_period_start, ev.evaluation_period_end, ev.status, u.username AS assigned_by FROM evaluations ev JOIN employees e ON e.employee_id=ev.employee_id JOIN evaluation_templates t ON t.template_id=ev.template_id LEFT JOIN users u ON u.user_id=ev.assigned_by WHERE e.employee_code='MO26-AP-H' AND t.form_code='AP-HEAD-Q-DRYRUN-20261115' AND ev.deleted_at IS NULL;"
```

## 4. VP for Acquired Properties Quarterly template

For VP account `AP-001`, copy and paste this block into **PowerShell** after starting MySQL in XAMPP:

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source evaluation/Acquired_Properties_Quarterly/seed_ap_vp_quarterly_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw 'AP VP Quarterly template import failed' }

Write-Host "AP VP QUARTERLY TEMPLATE IMPORTED" -ForegroundColor Green
```

This creates one Active **Quarterly** template for the VP for Acquired Properties, with 8 portfolio leadership KRAs totaling 100%, 8 standard Behavior items, and an **80% KRA / 20% Behavior** overall split. Period From: **2026-11-15**. Period To: **2027-02-14**. The active `CEO-001` President and CEO account is recorded as creator. The seed does not create an evaluation entry, scores, or approval; the VP opens **Employee Portal → Self Rating** to start. A repeated import stops with a duplicate-form error.

To verify the VP form:

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root raquel_hris -e "SELECT t.template_id, t.template_name, t.evaluation_type, t.assessment_from, t.assessment_to, t.status, (SELECT COUNT(*) FROM evaluation_template_positions p WHERE p.template_id=t.template_id) AS target_positions, (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA') AS kra_items, (SELECT COALESCE(SUM(c.weight),0) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA') AS kra_weight_total, (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='Behavior') AS behavior_items FROM evaluation_templates t WHERE t.form_code='AP-VP-Q-DRYRUN-20261115';"
```

Expected: **1 target position, 8 KRA items, 100 KRA weight total, 8 Behavior items**.

## 5. AP Manager I-IV Quarterly template

For AP Manager I-IV, copy and paste this block into **PowerShell** after starting MySQL in XAMPP:

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 --abort-source-on-error -u root -e "source evaluation/Acquired_Properties_Quarterly/seed_ap_managers_quarterly_dry_run.sql"
if ($LASTEXITCODE -ne 0) { throw 'AP Manager Quarterly template import failed' }

Write-Host "AP MANAGER QUARTERLY TEMPLATE IMPORTED" -ForegroundColor Green
```

This creates one Active **Quarterly** template for AP Manager I-IV, including Marina Alvarez (`MO26-AP-M`). It has 8 manager KRAs totaling 100%, 8 standard Behavior items, and an **80% KRA / 20% Behavior** overall split. Period From: **2026-11-15**. Period To: **2027-02-14**. VP for Acquired Properties account `AP-001` is recorded as creator. This imports only the template: no evaluation entry, ratings, or approval. A repeated import stops with a duplicate-form error.

To verify the AP Manager form:

```powershell
cd C:\xampp\htdocs\Raquel_HRD

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root raquel_hris -e "SELECT t.template_id, t.template_name, t.evaluation_type, t.assessment_from, t.assessment_to, t.status, (SELECT COUNT(*) FROM evaluation_template_positions p WHERE p.template_id=t.template_id) AS target_positions, (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA') AS kra_items, (SELECT COALESCE(SUM(c.weight),0) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA') AS kra_weight_total, (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='Behavior') AS behavior_items FROM evaluation_templates t WHERE t.form_code='AP-MGR-Q-DRYRUN-20261115';"
```

Expected: **4 target positions, 8 KRA items, 100 KRA weight total, 8 Behavior items**. Marina can open **Employee Portal → Self Rating** to start after import.
