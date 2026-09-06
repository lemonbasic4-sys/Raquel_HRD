# Evaluation-flow test seeds (HRD & Acquired Properties Only)

Use this folder **instead of** the full department seeds (`AP_seed.sql`, `HR_seed.sql`, and the rest). Those files load too many employees for package testing: a department package waits until **every** active employee with a user account has submitted.

All Employee portal passwords below: `password`

### HRIS Management Accounts:

| Login           | Password   | Use for                                              |
| --------------- | ---------- | ---------------------------------------------------- |
| `admin`         | `password` | Admin                                                |
| `elena.delgado` | `password` | HR Manager — templates, governance UI, Team Packages |

Patricia / Miguel also have HRIS accounts (`patricia.gomez`, `miguel.torres`); for **self-rating** use their portal codes (`HRD-002`, `HRD-003`).

---

## Import order (fresh database)

Drop `raquel_hris_test_db`, recreate it, then import **in this order**:

1. `database/1st_schema_tables.sql`
2. `database/2nd_seed_organization.sql`
3. `database/3rd_seed_HR_accounts_.sql`
4. `sample_db_seeds/01_test_employees.sql` ← Lean test roster (HRD & AP only)
5. `database/xPortal_accounts.sql`
6. `database/data/seed_templates.sql`
7. `sample_db_seeds/02_test_hrd_portal_accounts.sql`
8. `sample_db_seeds/03_test_governance_approvers.sql`

Do **not** import `testing_seed.sql` and do **not** import the large `*_seed.sql` department files.

---

## Active Test Roster

Each department in this lean test setup has a frozen `reports_to` chain:

| Dept | Portal logins (staff → … → head) | Consolidator | Annual template to pick |
| --- | --- | --- | --- |
| **Acquired Properties (Pilot)** | `AP-T01` → `AP-T02` → `AP-T03` → `AP-T04` | `AP-T02` (Supervisor) | Acquired Properties **Annual** |
| **Human Resources (HRD)** | `HRD-003` → `HRD-002` → `HRD-001` | `HRD-002` (Patricia) | Human Resources **Annual** |

### Executive & Governance Roster

All employee portal passwords: `password`

| Portal login | Name & Title | Assigned Role on Package |
| ------------ | ------------ | ------------------------- |
| `AP-T04`     | Eduardo Aquino (VP for Acquired Properties) | Step 4: Division VP for Acquired Properties |
| `OP-T02`     | Gabriel Mendoza (President & CEO) | Step 5: Corporate Executive Sign-off (President) |
| `GOV-AUD`    | Manuel Ramos (Audit Committee Chair) | Step 6: Corporate Compliance & Audit Check |
| `GOV-BOD`    | Antonio Raquel (Chairman of the Board) | Step 7: **Final Ratification, Lock & Apply** |

*Note: HRD reports directly to the President & CEO and automatically bypasses Step 4 (Division VP).*

---

## The Corporate Evaluation Routing Chain

When an evaluation package is generated, it automatically follows the company hierarchy:

$$\text{Supervisor (Consolidator)} \longrightarrow \text{Manager (Review)} \longrightarrow \mathbf{Division\ VP\ (AP\ only)} \longrightarrow \mathbf{President} \longrightarrow \mathbf{Audit\ Committee} \longrightarrow \mathbf{Board\ of\ Directors\ [Final\ Lock]}$$

---

## Tests in Order

### Test 1 — Verify Routing Matrix in HRIS

1. Login to HRIS as `elena.delgado` / `password`.
2. Go to **Performance & Appraisal** &rarr; **Evaluation Routing & Governance** (`manager/evaluation-governance.php`).
3. Confirm the **Department Division VP Matrix**:
   - **Acquired Properties** &rarr; `Eduardo Villanueva Aquino (VP for Acquired Properties)`
   - **Human Resources** &rarr; Direct to President & CEO (No Division VP needed)
4. Confirm **Corporate Governance Officials**:
   - **President** &rarr; `Gabriel Santos Mendoza (President and CEO)`
   - **Audit Committee** &rarr; `Manuel Rivera Ramos (Audit Committee Chair)`
   - **Board of Directors** &rarr; `Antonio Velasco Raquel (Chairman of the Board)`

### Test 2 — Full Human Resources Flow (Direct to President)

1. Portal self-rate HR Annual as all 3 HR members:
   - Login `HRD-003` (Miguel / Staff) &rarr; Self-Rating &rarr; Human Resources Annual &rarr; Submit.
   - Login `HRD-002` (Patricia / Supervisor) &rarr; Self-Rating &rarr; Human Resources Annual &rarr; Submit.
   - Login `HRD-001` (Elena / Manager) &rarr; Self-Rating &rarr; Human Resources Annual &rarr; Submit.
2. Step 1 (Consolidation): Login `HRD-002` &rarr; Team Packages &rarr; Approve and advance package.
3. Step 2 (Department Review): Login `HRD-001` &rarr; Team Packages &rarr; Approve.
4. Step 5 (President Sign-off): Login `OP-T02` (President) &rarr; Team Packages &rarr; Notice package from HRD &rarr; Approve.
5. Step 6 (Audit Check): Login `GOV-AUD` &rarr; Team Packages &rarr; Approve.
6. Step 7 (Final Lock): Login `GOV-BOD` &rarr; Team Packages &rarr; **Approve, lock, and apply results**.
7. **Pass:** Status changes to **Approved and Applied**; final results locked and applied to employee records.

### Test 3 — Full Acquired Properties Flow (With Division VP Step)

1. Portal self-rate AP Annual as:
   - `AP-T01` (Leonora Gomez / Staff)
   - `AP-T02` (Ronald Lopez / Supervisor)
   - `AP-T03` (Christopher Tolentino / Manager)
   - `AP-T04` (Eduardo Aquino / VP for Acquired Properties)
2. Step 1 (Consolidation): `AP-T02` &rarr; Consolidate & Approve.
3. Step 2 (Manager Review): `AP-T03` &rarr; Approve.
4. Step 4 (Division VP): `AP-T04` &rarr; Approve.
5. Step 5 (President): `OP-T02` &rarr; Approve.
6. Step 6 (Audit Committee): `GOV-AUD` &rarr; Approve.
7. Step 7 (Board of Directors): `GOV-BOD` &rarr; Approve, lock, and apply results.
8. **Pass:** Successfully completes full route through designated VP for Acquired Properties.
