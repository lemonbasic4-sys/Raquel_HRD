# Evaluation-flow test seeds

Use this folder **instead of** the full department seeds (`AP_seed.sql`, `HR_seed.sql`, and the rest). Those files load too many employees for package testing: a department package waits until **every** active employee with a user account has submitted.

All Employee portal passwords below: `password`

HRIS:


| Login           | Password   | Use for                                              |
| --------------- | ---------- | ---------------------------------------------------- |
| `admin`         | `password` | Admin                                                |
| `elena.delgado` | `password` | HR Manager — templates, governance UI, Team Packages |


Patricia / Miguel also have HRIS accounts from `xPortal_accounts.sql`; for **self-rating** use their portal codes (`HRD-002`, `HRD-003`).

---



## Import order (fresh database)

Drop `raquel_hris`, recreate it, then import **in this order**:

1. `database/1st_schema_tables.sql`
2. `database/2nd_seed_organization.sql`
3. `database/3rd_seed_HR_accounts_.sql`
4. `sample_db_seeds/01_test_employees.sql` ← before portal accounts
5. `database/xPortal_accounts.sql`
6. `database/data/seed_templates.sql`
7. `sample_db_seeds/02_test_hrd_portal_accounts.sql`
8. `sample_db_seeds/03_test_governance_approvers.sql`

Do **not** import `testing_seed.sql` (it is not in the repo) and do **not** import the large `*_seed.sql` department files.

Smoke-check:

- HRIS: `http://localhost/Raquel_HRD/` → `elena.delgado` / `password`
- Portal: `http://localhost/Raquel_HRD/employee/` → `AP-T01` / `password`

---



## Who is in the roster

Each operational department has **2–4** people and a frozen `reports_to` chain.


| Dept                            | Portal logins (staff → … → head)          | Consolidator        | Annual template to pick        |
| ------------------------------- | ----------------------------------------- | ------------------- | ------------------------------ |
| **Acquired Properties (pilot)** | `AP-T01` → `AP-T02` → `AP-T03` → `AP-T04` | `AP-T02` Supervisor | Acquired Properties **Annual** |
| Human Resources                 | `HRD-003` → `HRD-002` → `HRD-001`         | `HRD-002` Patricia  | Human Resources **Annual**     |
| Audit                           | `AUD-T01` → `AUD-T02` → `AUD-T03`         | `AUD-T02`           | Audit Annual                   |
| Business Development            | `BD-T01`, `BD-T02` → `BD-T03`             | `BD-T03` Officer    | BD Annual                      |
| Compliance                      | `COM-T01` → `COM-T02` → `COM-T03`         | `COM-T02`           | Compliance Annual              |
| Finance                         | `FIN-T01` → `FIN-T02` → `FIN-T03`         | `FIN-T02`           | Finance Annual                 |
| General Services                | `GS-T01` → `GS-T02` → `GS-T03`            | `GS-T02`            | GS Annual                      |
| Information Technology          | `IT-T01` → `IT-T02` → `IT-T03`            | `IT-T02`            | IT Annual                      |
| Marketing                       | `MKT-T01` → `MKT-T02` → `MKT-T03`         | `MKT-T02`           | Marketing Annual               |
| Office of the President         | `OP-T01` → `OP-T02`                       | `OP-T02` President  | OP Annual                      |
| Operations                      | `OPS-T01` → `OPS-T02` → `OPS-T03`         | `OPS-T02`           | Operations Annual              |
| Purchasing                      | `PUR-T01`, `PUR-T02` → `PUR-T03`          | `PUR-T03`           | Purchasing Annual              |


### Executive & Governance Roster

All employee portal passwords: `password`

| Portal login | Name & Title | Assigned Role on Package |
| ------------ | ------------ | ------------------------- |
| `OPS-VP`     | Rodrigo Castillo (VP for Operations) | Division VP for HR, IT, OPS, BD, COM, MKT, PUR, AUD |
| `AP-T04`     | Eduardo Aquino (VP for Acquired Properties) | Division VP for Acquired Properties |
| `FIN-VP`     | Teresa Reyes (VP for Finance) | Division VP for Finance |
| `GS-VP`      | Ricardo Buenaventura (VP for General Services) | Division VP for General Services |
| `OP-T02`     | Gabriel Mendoza (President & CEO) | Corporate Executive Sign-off (President) |
| `GOV-AUD`    | Audit Approver (Audit Committee) | Corporate Compliance & Audit Check |
| `GOV-BOD`    | Board Approver (Board of Directors) | **Final Ratification, Lock & Apply** |

HRD dual login: Elena uses `elena.delgado` on HRIS (manager view) and `HRD-001` on the Employee portal (self-rating / package review).

---

## The Corporate Evaluation Routing Chain

When an evaluation package is generated, it automatically follows the company hierarchy:

$$\text{Supervisor (Consolidator)} \longrightarrow \text{Manager (Review)} \longrightarrow \mathbf{Division\ VP} \longrightarrow \mathbf{President} \longrightarrow \mathbf{Audit\ (if\ active)} \longrightarrow \mathbf{Board\ of\ Directors\ [Final\ Lock]}$$

---

## Tests in Order

Use **one department at a time**. A package only waits on **that** department’s active members.
Always use the **same Annual template** and the **same period** (Jan 1–Dec 31 of the current year). Submit — do not leave drafts.

### Test 1 — Verify Routing Matrix in HRIS

1. Login to HRIS as `elena.delgado` / `password`.
2. Go to **Performance & Appraisal** &rarr; **Evaluation Routing & Governance** (`manager/evaluation-governance.php`).
3. Confirm the **Department Governance Matrix** displays all departments with their assigned Division VPs:
   - **Human Resources** &rarr; `Rodrigo Lim Castillo (VP for Operations)`
   - **Acquired Properties** &rarr; `Eduardo Villanueva Aquino (VP for Acquired Properties)`
   - **Finance** &rarr; `Teresa Santos Reyes (VP for Finance)`
   - **General Services** &rarr; `Ricardo Cruz Buenaventura (VP for General Services)`
   - **President** &rarr; `Gabriel Santos Mendoza (President and CEO)`
   - **Board of Directors** &rarr; `Board Test Approver`
   - **Audit Committee** &rarr; `Audit Test Approver`
4. Test editing or adding an official if needed using the smart dropdowns.

### Test 2 — Full Human Resources Flow (HR Supervisor → Manager → VP Ops → President → Board Lock)

1. Portal self-rate HR Annual as all 3 HR members:
   - Login `HRD-003` (Miguel / Staff) &rarr; Self-Rating &rarr; Human Resources Annual &rarr; Submit.
   - Login `HRD-002` (Patricia / Supervisor) &rarr; Self-Rating &rarr; Human Resources Annual &rarr; Submit.
   - Login `HRD-001` (Elena / Manager) &rarr; Self-Rating &rarr; Human Resources Annual &rarr; Submit.
2. Step 1 (Consolidation): Login `HRD-002` &rarr; Team Packages &rarr; Approve and advance package.
3. Step 2 (Department Review): Login `HRD-001` &rarr; Team Packages &rarr; Approve.
4. Step 3 (Division VP): Login `OPS-VP` &rarr; Team Packages &rarr; Notice package from Human Resources &rarr; Approve.
5. Step 4 (Executive Sign-off): Login `OP-T02` (President) &rarr; Team Packages &rarr; Approve.
6. Step 5 (Audit Check): Login `GOV-AUD` &rarr; Team Packages &rarr; Approve.
7. Step 6 (Final Lock): Login `GOV-BOD` &rarr; Team Packages &rarr; **Approve, lock, and apply results**.
8. **Pass:** Status changes to **Approved and Applied**; final results locked and applied to employee records.

### Test 3 — Full Acquired Properties Flow (AP Supervisor → AP Manager → VP AP → President → Board Lock)

1. Portal self-rate AP Annual as:
   - `AP-T01` (Staff)
   - `AP-T02` (Supervisor)
   - `AP-T03` (Manager)
   - `AP-T04` (VP for Acquired Properties)
2. `AP-T02` &rarr; Consolidate & Approve.
3. `AP-T03` (Manager) &rarr; Approve.
4. `AP-T04` (VP for Acquired Properties) &rarr; Approve.
5. `OP-T02` (President) &rarr; Approve.
6. `GOV-AUD` &rarr; Approve.
7. `GOV-BOD` &rarr; Approve, lock, and apply results.
8. **Pass:** Successfully completes full route through designated VP for Acquired Properties.

### Test 4 — Return for Revision at Executive Level

1. When package reaches `OPS-VP` or `OP-T02` (President), click **Return for revision** with a note (e.g., "Please adjust scoring justification").
2. **Pass:** Package returns to Consolidator (`HRD-002` or `AP-T02`) with status marked returned and comments visible.
3. Consolidator adjusts, re-approves, and package moves forward along the route again.

---

## Common Blockers & Tips

| Symptom | Cause & Solution |
| --- | --- |
| Waiting forever for submissions | Someone in that dept still active did not submit, or used a different template/period. |
| Division VP or President missing on route | Department official was configured *after* package was generated. Re-create package or sync approvers. |
| Inability to assign VP in governance UI | Ensure the user is an active employee or user account; use the updated "Evaluation Routing & Governance" page. |
| Elena cannot open Self Rating on HRIS | Use Employee portal `HRD-001`, not `elena.delgado`. |


