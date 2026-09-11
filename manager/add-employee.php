<?php
$page_title = 'Add Employee';
require_once '../includes/session-check.php';
checkRole(['HR Manager', 'HR Supervisor']);
require_once '../includes/functions.php';

// The same protected creation workflow is available from each authorized portal.
$employee_portal_base = BASE_URL . '/' . (($_SESSION['role'] ?? '') === 'HR Supervisor' ? 'supervisor' : 'manager');

// Check for saved form draft from previous failed attempt (Persistence)
$emp = $_SESSION['form_draft'] ?? [];
unset($_SESSION['form_draft']); // Clear it after retrieving to avoid stale data

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {
    require_once '../includes/functions.php';
    if (!isset($_FILES['employee_csv']) || $_FILES['employee_csv']['error'] !== UPLOAD_ERR_OK) {
        redirectWith($employee_portal_base . '/add-employee.php', 'danger', 'Please upload a valid CSV file.');
    }

    $uploadedName = $_FILES['employee_csv']['name'] ?? '';
    $uploadedExtension = strtolower(pathinfo($uploadedName, PATHINFO_EXTENSION));
    $uploadedHandle = fopen($_FILES['employee_csv']['tmp_name'], 'rb');
    $uploadedSignature = $uploadedHandle ? fread($uploadedHandle, 2) : false;
    if ($uploadedHandle) {
        fclose($uploadedHandle);
    }
    if ($uploadedExtension !== 'csv' || $uploadedSignature === "PK") {
        redirectWith($employee_portal_base . '/add-employee.php', 'danger', 'Excel files are not supported here. Save the workbook as CSV UTF-8, then upload the .csv file.');
    }

    $file = fopen($_FILES['employee_csv']['tmp_name'], 'r');
    if (!$file)
        redirectWith($employee_portal_base . '/add-employee.php', 'danger', 'Could not read the uploaded file.');

    // Get headers
    $headers = fgetcsv($file);
    if (!$headers) {
        fclose($file);
        redirectWith($employee_portal_base . '/add-employee.php', 'danger', 'CSV file is empty.');
    }

    // Map headers to indices
    $headerMap = array_flip(array_map('trim', $headers));

    $created = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];

    $parseCsvDate = static function ($value) {
        $value = trim((string) $value);
        if ($value === '' || strpos($value, "\0") !== false) {
            return null;
        }

        foreach (['m/d/Y', 'n/j/Y', 'Y-m-d'] as $format) {
            $date = DateTime::createFromFormat($format, $value);
            $dateErrors = DateTime::getLastErrors();
            if ($date && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    };

    $allowed_statuses = ['OJT', 'Probationary', 'Project Based', 'Project-Based', 'Regular', 'Separated', 'Trainee', 'AWOL', 'Retirement', 'Death', 'Permanent or Total Disability', 'Resignation', 'Failed in Training', 'Termination for Cause'];

    while (($row = fgetcsv($file)) !== false) {
        if (empty(array_filter($row)))
            continue;

        $getV = function ($key, $idx = null) use ($row, $headerMap) {
            if (isset($headerMap[$key])) {
                return trim($row[$headerMap[$key]] ?? '');
            }
            if ($idx !== null) {
                return trim($row[$idx] ?? '');
            }
            return '';
        };

        $first_name = $getV('First Name', 0);
        $last_name = $getV('Last Name', 1);
        $middle_name = $getV('Middle Name', 2);
        $name_extension = $getV('Extension', 3);
        $employee_code = $getV('Company ID', 39);

        $dobRaw = $getV('Birthday', 4);
        $dob = $parseCsvDate($dobRaw);

        $pob = $getV('Birthplace', 5);
        $gender = $getV('Gender', 6);
        $civil_status = $getV('Civil Status', 7);

        $hireDateRaw = $getV('Hire Date', 33);
        $hd = $parseCsvDate($hireDateRaw);

        $job_title_name = $getV('Job Title', 34);
        $rank_name_csv  = $getV('Rank');
        $dept_name = $getV('Department', 35);
        $branch_name = $getV('Branch', 36);
        $emp_status = $getV('Employment Status', 37) ?: 'Regular';
        $emp_type = $getV('Employment Type', 38) ?: 'Full-time';

        $csRaw = $getV('Contract Start Date');
        $contract_start_date = $parseCsvDate($csRaw);

        $ceRaw = $getV('Contract End Date');
        $contract_end_date = $parseCsvDate($ceRaw);

        // Validate Status against ENUM 
        $foundStatus = false;
        foreach ($allowed_statuses as $as) {
            if (strcasecmp($as, $emp_status) === 0) {
                $emp_status = $as;
                $foundStatus = true;
                break;
            }
        }
        if (!$foundStatus)
            $emp_status = 'Regular';

        if (empty($first_name) || empty($last_name) || empty($hd) || empty($job_title_name)) {
            $skipped++;
            $errors[] = "Row ($first_name $last_name): Missing required fields.";
            continue;
        }

        $existing_id = null;
        if (!empty($employee_code)) {
            $dc = $conn->prepare("SELECT employee_id FROM employees WHERE employee_code = ?");
            $dc->bind_param("s", $employee_code);
            $dc->execute();
            $dr = $dc->get_result();
            if ($d = $dr->fetch_assoc())
                $existing_id = $d['employee_id'];
            $dc->close();
        }
        if (!$existing_id) {
            $dc = $conn->prepare("SELECT employee_id FROM employees WHERE first_name = ? AND last_name = ?");
            $dc->bind_param("ss", $first_name, $last_name);
            $dc->execute();
            $dr = $dc->get_result();
            if ($d = $dr->fetch_assoc())
                $existing_id = $d['employee_id'];
            $dc->close();
        }

        $did = null;
        if (!empty($dept_name)) {
            $dc = $conn->prepare("SELECT department_id FROM departments WHERE department_name = ?");
            $dc->bind_param("s", $dept_name);
            $dc->execute();
            $dr = $dc->get_result();
            if ($d = $dr->fetch_assoc())
                $did = $d['department_id'];
            else {
                $di = $conn->prepare("INSERT INTO departments (department_name, description) VALUES (?, 'Imported via CSV')");
                $di->bind_param("s", $dept_name);
                $di->execute();
                $did = $di->insert_id;
                $di->close();
            }
            $dc->close();
        }

        $job_title_id = null;
        $jt_rank_category_id = null;
        if (!empty($job_title_name)) {
            $jc = $conn->prepare("SELECT job_title_id, rank_category_id FROM job_titles WHERE job_title = ? AND (department_id = ? OR department_id IS NULL)");
            $jc->bind_param("si", $job_title_name, $did);
            $jc->execute();
            $jr = $jc->get_result();
            if ($j = $jr->fetch_assoc()) {
                $job_title_id = $j['job_title_id'];
                $jt_rank_category_id = $j['rank_category_id'];
            }
            $jc->close();
        }

        $bid = null;
        if (!empty($branch_name)) {
            $bc = $conn->prepare("SELECT branch_id FROM branches WHERE branch_name = ?");
            $bc->bind_param("s", $branch_name);
            $bc->execute();
            $br = $bc->get_result();
            if ($b = $br->fetch_assoc())
                $bid = $b['branch_id'];
            else {
                $bi = $conn->prepare("INSERT INTO branches (branch_name, location) VALUES (?, 'TBD')");
                $bi->bind_param("s", $branch_name);
                $bi->execute();
                $bid = $bi->insert_id;
                $bi->close();
            }
            $bc->close();
        }

        // Robust Rank Category Resolution
        $rcid = null;
        if (!empty($rank_name_csv)) {
            // 1. Case-insensitive exact match from rank_categories
            $rq = $conn->prepare("SELECT rank_category_id FROM rank_categories WHERE LOWER(TRIM(rank_name)) = LOWER(TRIM(?)) LIMIT 1");
            $rq->bind_param("s", $rank_name_csv);
            $rq->execute();
            $rr = $rq->get_result();
            if ($rc_row = $rr->fetch_assoc()) {
                $rcid = (int) $rc_row['rank_category_id'];
            }
            $rq->close();

            // 2. Match common aliases / shorthand
            if (!$rcid) {
                $norm_rank = strtolower(trim($rank_name_csv));
                if (in_array($norm_rank, ['executive', 'executives', 'exec'], true)) {
                    $rcid = 1;
                } elseif (in_array($norm_rank, ['management team', 'management', 'mgmt'], true)) {
                    $rcid = 2;
                } elseif (in_array($norm_rank, ['manager', 'managers', 'mgr'], true)) {
                    $rcid = 3;
                } elseif (in_array($norm_rank, ['supervisor', 'supervisors', 'sup', 'supv'], true)) {
                    $rcid = 4;
                } elseif (in_array($norm_rank, ['r&f', 'rf', 'rank & file', 'rank and file', 'staff'], true)) {
                    $rcid = 5;
                }
            }
        }

        // 3. Fallback to rank_category_id from matched job_title table
        if (!$rcid && !empty($jt_rank_category_id)) {
            $rcid = (int) $jt_rank_category_id;
        }

        // 4. Fallback from job_title_name keywords if still unassigned
        if (!$rcid && !empty($job_title_name)) {
            $norm_jt = strtolower($job_title_name);
            if (str_contains($norm_jt, 'executive') || str_contains($norm_jt, 'president') || str_contains($norm_jt, 'vp') || str_contains($norm_jt, 'ceo')) {
                $rcid = 1;
            } elseif (str_contains($norm_jt, 'manager') || str_contains($norm_jt, 'head') || str_contains($norm_jt, 'chief')) {
                $rcid = 3;
            } elseif (str_contains($norm_jt, 'supervisor') || str_contains($norm_jt, 'lead')) {
                $rcid = 4;
            } else {
                $rcid = 5; // Default R&F / Staff
            }
        }

        $conn->begin_transaction();
        try {
            if ($existing_id) {
                $stmt = $conn->prepare("UPDATE employees SET employee_code=?, first_name=?, last_name=?, middle_name=?, name_extension=?, date_of_birth=?, place_of_birth=?, gender=?, civil_status=?, hire_date=?, job_title=?, job_title_id=?, department_id=?, rank_category_id=?, branch_id=?, employment_status=?, employment_type=?, contract_start_date=?, contract_end_date=? WHERE employee_id=?");
                $stmt->bind_param("sssssssssssiiiissssi", $employee_code, $first_name, $last_name, $middle_name, $name_extension, $dob, $pob, $gender, $civil_status, $hd, $job_title_name, $job_title_id, $did, $rcid, $bid, $emp_status, $emp_type, $contract_start_date, $contract_end_date, $existing_id);
                $stmt->execute();
                $eid = $existing_id;
                $stmt->close();
                $updated++;
            } else {
                $stmt = $conn->prepare("INSERT INTO employees (employee_code, first_name, last_name, middle_name, name_extension, date_of_birth, place_of_birth, gender, civil_status, hire_date, job_title, job_title_id, department_id, rank_category_id, branch_id, employment_status, employment_type, contract_start_date, contract_end_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param("sssssssssssiiiissss", $employee_code, $first_name, $last_name, $middle_name, $name_extension, $dob, $pob, $gender, $civil_status, $hd, $job_title_name, $job_title_id, $did, $rcid, $bid, $emp_status, $emp_type, $contract_start_date, $contract_end_date);
                $stmt->execute();
                $eid = $stmt->insert_id;
                $stmt->close();
                $created++;
            }

            // Employee Details
            $h_val = $getV('Height (m)', 8);
            $w_val = $getV('Weight (kg)', 9);
            $bt = $getV('Blood Type', 10);
            $cz = $getV('Citizenship', 11) ?: 'Filipino';
            $conn->query("DELETE FROM employee_details WHERE employee_id = $eid");
            $stmt = $conn->prepare("INSERT INTO employee_details (employee_id, height_m, weight_kg, blood_type, citizenship) VALUES (?,?,?,?,?)");
            $h_f = !empty($h_val) ? (float) $h_val : null;
            $w_f = !empty($w_val) ? (float) $w_val : null;
            $stmt->bind_param("iddss", $eid, $h_f, $w_f, $bt, $cz);
            $stmt->execute();
            $stmt->close();

            // Govt IDs
            $sss = $getV('SSS No', 12);
            $ph = $getV('PhilHealth No', 13);
            $pag = $getV('Pag-IBIG No', 14);
            $tin = $getV('TIN No', 15);
            $conn->query("DELETE FROM employee_government_ids WHERE employee_id = $eid");
            $stmt = $conn->prepare("INSERT INTO employee_government_ids (employee_id, sss_number, philhealth_number, pagibig_number, tin_number) VALUES (?,?,?,?,?)");
            $stmt->bind_param("issss", $eid, $sss, $ph, $pag, $tin);
            $stmt->execute();
            $stmt->close();

            // Contacts
            $tel = $getV('Telephone No', 30);
            $mob = formatPHMobileNumber($getV('Mobile No', 31));
            $eml = $getV('Email', 32);
            $conn->query("DELETE FROM employee_contacts WHERE employee_id = $eid");
            $stmt = $conn->prepare("INSERT INTO employee_contacts (employee_id, telephone_number, mobile_number, personal_email) VALUES (?,?,?,?)");
            $stmt->bind_param("isss", $eid, $tel, $mob, $eml);
            $stmt->execute();
            $stmt->close();

            // Addresses
            $conn->query("DELETE FROM employee_addresses WHERE employee_id = $eid");
            $res_reg = $getV('Residential Region') ?: 'Region IV-A (CALABARZON)';
            $res_h = $getV('Residential House No', 16);
            $res_s = $getV('Residential Street', 17);
            $res_v = $getV('Residential Subdivision', 18);
            $res_b = $getV('Residential Barangay', 19);
            $res_c = $getV('Residential City', 20);
            $res_p = $getV('Residential Province', 21);
            $res_z = $getV('Residential Zip Code', 22);
            if (!empty($res_s) || !empty($res_c) || !empty($res_p)) {
                $stmt = $conn->prepare("INSERT INTO employee_addresses (employee_id, address_type, region, house_no, street, subdivision, barangay, city, province, zip_code) VALUES (?, 'Residential', ?,?,?,?,?,?,?,?)");
                $stmt->bind_param("issssssss", $eid, $res_reg, $res_h, $res_s, $res_v, $res_b, $res_c, $res_p, $res_z);
                $stmt->execute();
                $stmt->close();
            }
            $per_reg = $getV('Permanent Region') ?: 'Region IV-A (CALABARZON)';
            $per_h = $getV('Permanent House No', 23);
            $per_s = $getV('Permanent Street', 24);
            $per_v = $getV('Permanent Subdivision', 25);
            $per_b = $getV('Permanent Barangay', 26);
            $per_c = $getV('Permanent City', 27);
            $per_p = $getV('Permanent Province', 28);
            $per_z = $getV('Permanent Zip Code', 29);
            if (!empty($per_s) || !empty($per_c) || !empty($per_p)) {
                $stmt = $conn->prepare("INSERT INTO employee_addresses (employee_id, address_type, region, house_no, street, subdivision, barangay, city, province, zip_code) VALUES (?, 'Permanent', ?,?,?,?,?,?,?,?)");
                $stmt->bind_param("issssssss", $eid, $per_reg, $per_h, $per_s, $per_v, $per_b, $per_c, $per_p, $per_z);
                $stmt->execute();
                $stmt->close();
            }

            // Emergency
            $ec_n = $getV('Emergency Contact Name', 40);
            $ec_r = $getV('Emergency Contact Relationship', 41);
            $ec_p = formatPHMobileNumber($getV('Emergency Contact Number', 42));
            $conn->query("DELETE FROM employee_emergency_contacts WHERE employee_id = $eid");
            if (!empty($ec_n)) {
                $stmt = $conn->prepare("INSERT INTO employee_emergency_contacts (employee_id, contact_name, relationship, contact_number) VALUES (?,?,?,?)");
                $stmt->bind_param("isss", $eid, $ec_n, $ec_r, $ec_p);
                $stmt->execute();
                $stmt->close();
            }

            // Family
            $spouse_n = $getV('Spouse Name', 43);
            $spouse_o = $getV('Spouse Occupation', 44);
            $has_detailed_family_columns = isset($headerMap['Father Surname']);
            $father_surname = $has_detailed_family_columns ? $getV('Father Surname') : '';
            $father_first_name = $has_detailed_family_columns ? $getV('Father First Name') : $getV('Father Name', 45);
            $father_middle_name = $has_detailed_family_columns ? $getV('Father Middle Name') : '';
            $father_extension = $has_detailed_family_columns ? $getV('Father Extension') : '';
            $father_o = $has_detailed_family_columns ? $getV('Father Occupation') : $getV('Father Occupation', 46);
            $mother_surname = $has_detailed_family_columns ? $getV('Mother Maiden Surname') : '';
            $mother_first_name = $has_detailed_family_columns ? $getV('Mother First Name') : $getV('Mother Maiden Name', 47);
            $mother_middle_name = $has_detailed_family_columns ? $getV('Mother Middle Name') : '';
            $mother_o = $has_detailed_family_columns ? $getV('Mother Occupation') : $getV('Mother Occupation', 48);
            $conn->query("DELETE FROM employee_family WHERE employee_id = $eid");
            if (!empty($spouse_n)) {
                $stmt = $conn->prepare("INSERT INTO employee_family (employee_id, member_type, first_name, occupation) VALUES (?, 'Spouse', ?, ?)");
                $stmt->bind_param("iss", $eid, $spouse_n, $spouse_o);
                $stmt->execute();
                $stmt->close();
            }
            if (!empty($father_surname) || !empty($father_first_name)) {
                $stmt = $conn->prepare("INSERT INTO employee_family (employee_id, member_type, surname, first_name, middle_name, name_extension, occupation) VALUES (?, 'Father', ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssss", $eid, $father_surname, $father_first_name, $father_middle_name, $father_extension, $father_o);
                $stmt->execute();
                $stmt->close();
            }
            if (!empty($mother_surname) || !empty($mother_first_name)) {
                $stmt = $conn->prepare("INSERT INTO employee_family (employee_id, member_type, surname, first_name, middle_name, occupation) VALUES (?, 'Mother', ?, ?, ?, ?)");
                $stmt->bind_param("issss", $eid, $mother_surname, $mother_first_name, $mother_middle_name, $mother_o);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();

            // ─── Extended PDS sections (CSV columns 54–98) ───────────────────

            // Children
            $child_surname = $has_detailed_family_columns ? $getV('Child 1 Surname') : '';
            $child_name = $has_detailed_family_columns ? $getV('Child 1 First Name') : $getV('Child 1 Name', 49);
            $child_middle_name = $has_detailed_family_columns ? $getV('Child 1 Middle Name') : '';
            $child_dob_raw = $has_detailed_family_columns ? $getV('Child 1 Birthday') : $getV('Child 1 Birthday', 50);
                        $child_dob = $parseCsvDate($child_dob_raw);
            $conn->query("DELETE FROM employee_children WHERE employee_id = $eid");
            if (!empty($child_surname) || !empty($child_name)) {
                $stmt = $conn->prepare("INSERT INTO employee_children (employee_id, surname, first_name, middle_name, date_of_birth) VALUES (?,?,?,?,?)");
                $stmt->bind_param("issss", $eid, $child_surname, $child_name, $child_middle_name, $child_dob);
                $stmt->execute(); $stmt->close();
            }

            // Siblings
            $sib_surname = $has_detailed_family_columns ? $getV('Sibling 1 Surname') : '';
            $sib_name = $has_detailed_family_columns ? $getV('Sibling 1 First Name') : $getV('Sibling 1 Name', 51);
            $sib_middle_name = $has_detailed_family_columns ? $getV('Sibling 1 Middle Name') : '';
            $sib_dob_raw = $has_detailed_family_columns ? $getV('Sibling 1 Birthday') : $getV('Sibling 1 Birthday', 52);
                        $sib_dob = $parseCsvDate($sib_dob_raw);
            $conn->query("DELETE FROM employee_siblings WHERE employee_id = $eid");
            if (!empty($sib_surname) || !empty($sib_name)) {
                $stmt = $conn->prepare("INSERT INTO employee_siblings (employee_id, surname, first_name, middle_name, date_of_birth) VALUES (?,?,?,?,?)");
                $stmt->bind_param("issss", $eid, $sib_surname, $sib_name, $sib_middle_name, $sib_dob);
                $stmt->execute(); $stmt->close();
            }

            // Education
            $conn->query("DELETE FROM employee_education WHERE employee_id = $eid");
            $elem_school = $getV('Elementary School', 62);
            $elem_grad   = $getV('Elementary Year Graduated', 63);
            if (!empty($elem_school)) {
                $stmt = $conn->prepare("INSERT INTO employee_education (employee_id, education_level, school_name, degree_course, period_from, period_to, highest_level_units, year_graduated, honors_received) VALUES (?, 'Elementary', ?, '', NULL, NULL, '', ?, '')");
                $stmt->bind_param("iss", $eid, $elem_school, $elem_grad);
                $stmt->execute(); $stmt->close();
            }
            $hs_school = $getV('High School', 64);
            $hs_grad   = $getV('High School Year Graduated', 65);
            if (!empty($hs_school)) {
                $stmt = $conn->prepare("INSERT INTO employee_education (employee_id, education_level, school_name, degree_course, period_from, period_to, highest_level_units, year_graduated, honors_received) VALUES (?, 'Secondary', ?, '', NULL, NULL, '', ?, '')");
                $stmt->bind_param("iss", $eid, $hs_school, $hs_grad);
                $stmt->execute(); $stmt->close();
            }
            $col_school = $getV('College School', 66);
            $col_course = $getV('College Degree/Course', 67);
            $col_grad   = $getV('College Year Graduated', 68);
            if (!empty($col_school)) {
                $stmt = $conn->prepare("INSERT INTO employee_education (employee_id, education_level, school_name, degree_course, period_from, period_to, highest_level_units, year_graduated, honors_received) VALUES (?, 'College', ?, ?, NULL, NULL, '', ?, '')");
                $stmt->bind_param("isss", $eid, $col_school, $col_course, $col_grad);
                $stmt->execute(); $stmt->close();
            }

            // Work Experience
            $prev_company = $getV('Previous Company 1', 69);
            $prev_pos     = $getV('Previous Position 1', 70);
            $prev_sal_raw = $getV('Previous Monthly Salary 1', 71);
            $prev_period  = $getV('Previous Employment Period 1', 72);
            $prev_reason  = $getV('Previous Reason for Leaving 1', 73);
            $conn->query("DELETE FROM employee_work_experience WHERE employee_id = $eid");
            if (!empty($prev_company)) {
                $wf = null; $wto = null;
                if (!empty($prev_period) && preg_match('/(\d{4})\s*[-\x{2013}]\s*(\d{4})/u', $prev_period, $wm)) {
                    $wf  = $wm[1] . '-01-01';
                    $wto = $wm[2] . '-12-31';
                }
                $prev_sal_f = !empty($prev_sal_raw) ? (float)$prev_sal_raw : null;
                $appt_status = '';
                $stmt = $conn->prepare("INSERT INTO employee_work_experience (employee_id, date_from, date_to, job_title, company_name, monthly_salary, appointment_status, reason_for_leaving) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->bind_param("issssdis", $eid, $wf, $wto, $prev_pos, $prev_company, $prev_sal_f, $appt_status, $prev_reason);
                $stmt->execute(); $stmt->close();
            }

            // Trainings
            $train_title = $getV('Training Title 1', 74);
            $train_by    = $getV('Training Conducted By 1', 75);
            $train_hrs   = $getV('Training Hours 1', 76);
            $conn->query("DELETE FROM employee_trainings WHERE employee_id = $eid");
            if (!empty($train_title)) {
                $train_hrs_f = !empty($train_hrs) ? (float)$train_hrs : null;
                $stmt = $conn->prepare("INSERT INTO employee_trainings (employee_id, date_from, date_to, training_title, training_type, no_of_hours, conducted_by) VALUES (?,NULL,NULL,?,'',?,?)");
                $stmt->bind_param("isds", $eid, $train_title, $train_hrs_f, $train_by);
                $stmt->execute(); $stmt->close();
            }

            // Eligibility
            $elig_title = $getV('Eligibility License Title 1', 77);
            $elig_no    = $getV('Eligibility License No 1', 78);
            $conn->query("DELETE FROM employee_eligibility WHERE employee_id = $eid");
            if (!empty($elig_title)) {
                $stmt = $conn->prepare("INSERT INTO employee_eligibility (employee_id, license_title, date_from, date_to, license_number, date_of_exam, place_of_exam) VALUES (?,?,NULL,NULL,?,NULL,'')");
                $stmt->bind_param("iss", $eid, $elig_title, $elig_no);
                $stmt->execute(); $stmt->close();
            }

            // Disclosures
            $is_related  = (strtolower($getV('Related to Company (Yes/No)', 79)) === 'yes') ? 1 : 0;
            $related_det = $getV('Related Details', 80) ?: '';
            $has_admin   = (strtolower($getV('Admin Offense (Yes/No)', 81)) === 'yes') ? 1 : 0;
            $admin_det   = $getV('Admin Offense Details', 82) ?: '';
            $has_crim    = (strtolower($getV('Criminal Charge (Yes/No)', 83)) === 'yes') ? 1 : 0;
            $crim_det    = $getV('Criminal Charge Details', 84) ?: '';
            $is_pwd_csv  = (strtolower($getV('PWD Status (Yes/No)', 85)) === 'yes') ? 1 : 0;
            $is_solo_csv = (strtolower($getV('Solo Parent Status (Yes/No)', 86)) === 'yes') ? 1 : 0;
            $conn->query("DELETE FROM employee_disclosures WHERE employee_id = $eid");
            $stmt = $conn->prepare("INSERT INTO employee_disclosures (employee_id, is_related_to_company, related_details, has_admin_offense, admin_offense_details, has_criminal_charge, criminal_charge_details, is_pwd, is_solo_parent) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("iisisisii", $eid, $is_related, $related_det, $has_admin, $admin_det, $has_crim, $crim_det, $is_pwd_csv, $is_solo_csv);
            $stmt->execute(); $stmt->close();

            // Real Properties
            $real_desc   = $getV('Real Property 1 Description', 87);
            $real_mval   = $getV('Real Property 1 Market Value', 88);
            $conn->query("DELETE FROM employee_real_properties WHERE employee_id = $eid");
            if (!empty($real_desc)) {
                $real_mval_f = !empty($real_mval) ? (float)$real_mval : null;
                $stmt = $conn->prepare("INSERT INTO employee_real_properties (employee_id, description, kind, exact_location, assessed_value, market_value, acquisition_year_mode, acquisition_cost) VALUES (?,?,'','',NULL,?,NULL,NULL)");
                $stmt->bind_param("isd", $eid, $real_desc, $real_mval_f);
                $stmt->execute(); $stmt->close();
            }

            // Personal Properties
            $pers_desc = $getV('Personal Property 1 Description', 89);
            $pers_cost = $getV('Personal Property 1 Cost', 90);
            $conn->query("DELETE FROM employee_personal_properties WHERE employee_id = $eid");
            if (!empty($pers_desc)) {
                $pers_cost_f = !empty($pers_cost) ? (float)$pers_cost : null;
                $stmt = $conn->prepare("INSERT INTO employee_personal_properties (employee_id, description, year_acquired, acquisition_cost) VALUES (?,?,'',?)");
                $stmt->bind_param("isd", $eid, $pers_desc, $pers_cost_f);
                $stmt->execute(); $stmt->close();
            }

            // Liabilities
            $liab_nat = $getV('Liability 1 Nature', 91);
            $liab_bal = $getV('Liability 1 Outstanding Balance', 92);
            $conn->query("DELETE FROM employee_liabilities WHERE employee_id = $eid");
            if (!empty($liab_nat)) {
                $liab_bal_f = !empty($liab_bal) ? (float)$liab_bal : null;
                $stmt = $conn->prepare("INSERT INTO employee_liabilities (employee_id, nature_of_liability, creditor_name, outstanding_balance) VALUES (?,?,'',?)");
                $stmt->bind_param("isd", $eid, $liab_nat, $liab_bal_f);
                $stmt->execute(); $stmt->close();
            }

            // References
            $ref1_name = $getV('Reference 1 Name', 93);
            $ref1_addr = $getV('Reference 1 Address', 94);
            $ref1_tel  = formatPHMobileNumber($getV('Reference 1 Contact Number', 95));
            $ref2_name = $getV('Reference 2 Name', 96);
            $ref2_addr = $getV('Reference 2 Address', 97);
            $ref2_tel  = formatPHMobileNumber($getV('Reference 2 Contact Number', 98));
            $ref3_name = $getV('Reference 3 Name');
            $ref3_addr = $getV('Reference 3 Address');
            $ref3_tel  = formatPHMobileNumber($getV('Reference 3 Contact Number'));
            $conn->query("DELETE FROM employee_references WHERE employee_id = $eid");
            if (!empty($ref1_name)) {
                $stmt = $conn->prepare("INSERT INTO employee_references (employee_id, reference_name, reference_address, reference_telephone) VALUES (?,?,?,?)");
                $stmt->bind_param("isss", $eid, $ref1_name, $ref1_addr, $ref1_tel);
                $stmt->execute(); $stmt->close();
            }
            if (!empty($ref2_name)) {
                $stmt = $conn->prepare("INSERT INTO employee_references (employee_id, reference_name, reference_address, reference_telephone) VALUES (?,?,?,?)");
                $stmt->bind_param("isss", $eid, $ref2_name, $ref2_addr, $ref2_tel);
                $stmt->execute(); $stmt->close();
            }
            if (!empty($ref3_name)) {
                $stmt = $conn->prepare("INSERT INTO employee_references (employee_id, reference_name, reference_address, reference_telephone) VALUES (?,?,?,?)");
                $stmt->bind_param("isss", $eid, $ref3_name, $ref3_addr, $ref3_tel);
                $stmt->execute(); $stmt->close();
            }

            // Additional PDS fields included in the reordered template.
            if (isset($headerMap['Skill/Hobby 1'])) {
                $skill = $getV('Skill/Hobby 1');
                $conn->query("DELETE FROM employee_skills WHERE employee_id = $eid");
                if (!empty($skill)) {
                    $stmt = $conn->prepare("INSERT INTO employee_skills (employee_id, skill_name) VALUES (?,?)");
                    $stmt->bind_param("is", $eid, $skill);
                    $stmt->execute(); $stmt->close();
                }
            }
            if (isset($headerMap['Recognition 1 Title'])) {
                $recognition = $getV('Recognition 1 Title');
                $conn->query("DELETE FROM employee_recognitions WHERE employee_id = $eid");
                if (!empty($recognition)) {
                    $stmt = $conn->prepare("INSERT INTO employee_recognitions (employee_id, recognition_title) VALUES (?,?)");
                    $stmt->bind_param("is", $eid, $recognition);
                    $stmt->execute(); $stmt->close();
                }
            }
            if (isset($headerMap['Membership 1 Organization'])) {
                $membership = $getV('Membership 1 Organization');
                $conn->query("DELETE FROM employee_memberships WHERE employee_id = $eid");
                if (!empty($membership)) {
                    $stmt = $conn->prepare("INSERT INTO employee_memberships (employee_id, organization_name) VALUES (?,?)");
                    $stmt->bind_param("is", $eid, $membership);
                    $stmt->execute(); $stmt->close();
                }
            }
            // ─────────────────────────────────────────────────────────────────

            // Auto-provision portal account and check for governance title linkage
            autoDetectAndLinkGovernanceApprover($conn, $eid);

            logAudit($conn, $_SESSION['user_id'], ($existing_id ? 'UPDATE' : 'CREATE'), 'Employee', $eid, "Imported/Updated via CSV: $first_name $last_name");
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Row ($first_name $last_name): " . $e->getMessage();
            $skipped++;
        }
    }
    fclose($file);

    if ($created > 0 || $updated > 0) {
        $msg = "Success! Created: $created, Updated: $updated.";
        if ($skipped > 0)
            $msg .= " Skipped $skipped rows.";
        redirectWith($employee_portal_base . '/employees.php', 'success', $msg);
    } else {
        $err = "No records were imported. ($skipped rows skipped) Latest error: " . end($errors);
        redirectWith($employee_portal_base . '/add-employee.php', 'danger', $err);
    }
}




if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['import_csv'])) {
    require_once '../includes/functions.php';

    // === SECTION 1: Personal Information ===
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $name_extension = trim($_POST['name_extension'] ?? '');
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $place_of_birth = trim($_POST['place_of_birth'] ?? '');
    $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
    $civil_status = !empty($_POST['civil_status']) ? $_POST['civil_status'] : null;
    $height_m = !empty($_POST['height_m']) ? (float) $_POST['height_m'] : null;
    $weight_kg = !empty($_POST['weight_kg']) ? (float) $_POST['weight_kg'] : null;
    $blood_type = !empty($_POST['blood_type']) ? $_POST['blood_type'] : null;
    $citizenship = trim($_POST['citizenship'] ?? 'Filipino');

    // Gov IDs
    $sss_number = trim($_POST['sss_number'] ?? '');
    $philhealth_number = trim($_POST['philhealth_number'] ?? '');
    $pagibig_number = trim($_POST['pagibig_number'] ?? '');
    $tin_number = trim($_POST['tin_number'] ?? '');

    // Addresses
    $res_region = trim($_POST['res_region'] ?? '');
    $res_house_no = trim($_POST['res_house_no'] ?? '');
    $res_street = trim($_POST['res_street'] ?? '');
    $res_subdivision = trim($_POST['res_subdivision'] ?? '');
    $res_barangay = trim($_POST['res_barangay'] ?? '');
    $res_city = trim($_POST['res_city'] ?? '');
    $res_province = trim($_POST['res_province'] ?? '');
    $res_zip_code = trim($_POST['res_zip_code'] ?? '');
    $perm_region = trim($_POST['perm_region'] ?? '');
    $perm_house_no = trim($_POST['perm_house_no'] ?? '');
    $perm_street = trim($_POST['perm_street'] ?? '');
    $perm_subdivision = trim($_POST['perm_subdivision'] ?? '');
    $perm_barangay = trim($_POST['perm_barangay'] ?? '');
    $perm_city = trim($_POST['perm_city'] ?? '');
    $perm_province = trim($_POST['perm_province'] ?? '');
    $perm_zip_code = trim($_POST['perm_zip_code'] ?? '');

    // Contact
    $telephone_number = trim($_POST['telephone_number'] ?? '');
    $contact_number = formatPHMobileNumber(trim($_POST['contact_number'] ?? ''));
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;

    // === SECTION 2: Family Background ===
    $spouse_surname = trim($_POST['spouse_surname'] ?? '');
    $spouse_first_name = trim($_POST['spouse_first_name'] ?? '');
    $spouse_middle_name = trim($_POST['spouse_middle_name'] ?? '');
    $spouse_name_ext = trim($_POST['spouse_name_ext'] ?? '');
    $spouse_occupation = trim($_POST['spouse_occupation'] ?? '');
    $father_surname = trim($_POST['father_surname'] ?? '');
    $father_first_name = trim($_POST['father_first_name'] ?? '');
    $father_middle_name = trim($_POST['father_middle_name'] ?? '');
    $father_name_ext = trim($_POST['father_name_ext'] ?? '');
    $father_occupation = trim($_POST['father_occupation'] ?? '');
    $mother_maiden_surname = trim($_POST['mother_maiden_surname'] ?? '');
    $mother_first_name = trim($_POST['mother_first_name'] ?? '');
    $mother_middle_name = trim($_POST['mother_middle_name'] ?? '');
    $mother_occupation = trim($_POST['mother_occupation'] ?? '');

    // === SECTION 10: Disclosures ===
    $is_related_to_company = isset($_POST['is_related_to_company']) ? 1 : 0;
    $related_details = trim($_POST['related_details'] ?? '');
    $has_admin_offense = isset($_POST['has_admin_offense']) ? 1 : 0;
    $admin_offense_details = trim($_POST['admin_offense_details'] ?? '');
    $has_criminal_charge = isset($_POST['has_criminal_charge']) ? 1 : 0;
    $criminal_charge_details = trim($_POST['criminal_charge_details'] ?? '');
    $has_criminal_conviction = isset($_POST['has_criminal_conviction']) ? 1 : 0;
    $criminal_conviction_details = trim($_POST['criminal_conviction_details'] ?? '');
    $has_been_separated = isset($_POST['has_been_separated']) ? 1 : 0;
    $separation_details = trim($_POST['separation_details'] ?? '');
    $is_pwd = isset($_POST['is_pwd']) ? 1 : 0;
    $pwd_details = trim($_POST['pwd_details'] ?? '');
    $is_solo_parent = isset($_POST['is_solo_parent']) ? 1 : 0;
    $solo_parent_details = trim($_POST['solo_parent_details'] ?? '');
    $has_recent_hospital = isset($_POST['has_recent_hospital']) ? 1 : 0;
    $hospital_details = trim($_POST['hospital_details'] ?? '');
    $has_current_treatment = isset($_POST['has_current_treatment']) ? 1 : 0;
    $treatment_details = trim($_POST['treatment_details'] ?? '');

    // === SECTION 12: Employment ===
    $hire_date = !empty($_POST['hire_date']) ? $_POST['hire_date'] : null;
    $job_title_id = !empty($_POST['job_title_id']) ? (int) $_POST['job_title_id'] : null;
    $job_title = trim($_POST['job_title'] ?? '');
    $department_id = !empty($_POST['department_id']) ? (int) $_POST['department_id'] : null;
    $rank_category_id = !empty($_POST['rank_category_id']) ? (int) $_POST['rank_category_id'] : null;
    $branch_id = !empty($_POST['branch_id']) ? (int) $_POST['branch_id'] : null;
    $employment_status = $_POST['employment_status'] ?? 'Regular';
    $employment_type = $_POST['employment_type'] ?? 'Full-time';
    $employee_code = strtoupper(trim($_POST['employee_code'] ?? ''));
    if ($employee_code === '')
        $employee_code = null;
    // emergency contact fields are now arrays — handled in the save block below
    $contract_start_date = !empty($_POST['contract_start_date']) ? $_POST['contract_start_date'] : null;
    $contract_end_date = !empty($_POST['contract_end_date']) ? $_POST['contract_end_date'] : null;

    // Profile picture
    $profile_picture = null;
    $upload_error = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['name'] !== '') {
        if ($_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../assets/img/employees/';
            if (!is_dir($upload_dir))
                mkdir($upload_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $new_filename = uniqid('emp_') . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $new_filename)) {
                    $profile_picture = $new_filename;
                } else {
                    $upload_error = "Could not save the uploaded image to the server.";
                }
            } else {
                $upload_error = "Invalid image format. Only JPG, PNG, and GIF are allowed.";
            }
        } else {
            $upload_error = "File upload error code: " . $_FILES['profile_picture']['error'];
        }
    }

    if ($upload_error) {
        redirectWith($employee_portal_base . '/add-employee.php', 'danger', "Image Upload Error: " . $upload_error);
    }

    if ($job_title_id !== null) {
        $jtStmt = $conn->prepare("SELECT job_title, department_id, is_active FROM job_titles WHERE job_title_id = ?");
        $jtStmt->bind_param("i", $job_title_id);
        $jtStmt->execute();
        $jtRow = $jtStmt->get_result()->fetch_assoc();
        $jtStmt->close();

        if (!$jtRow || (int) $jtRow['is_active'] !== 1) {
            redirectWith($employee_portal_base . '/add-employee.php', 'danger', 'Selected job title is invalid or inactive.');
        }

        if ($department_id !== null && (int) ($jtRow['department_id'] ?? 0) !== (int) $department_id) {
            redirectWith($employee_portal_base . '/add-employee.php', 'danger', 'Selected job title does not belong to the selected department.');
        }

        $job_title = (string) $jtRow['job_title'];
    }

    // Validate required
    if (empty($first_name) || empty($last_name) || empty($hire_date) || empty($department_id) || ($job_title_id === null && $job_title === '')) {
        redirectWith($employee_portal_base . '/add-employee.php', 'danger', 'Please fill in all required fields (Name, Hire Date, Job Title, Department).');
    }

    // Strictly no duplicate employee
    $dupCheck = $conn->prepare("SELECT employee_id FROM employees WHERE first_name = ? AND last_name = ?");
    $dupCheck->bind_param("ss", $first_name, $last_name);
    $dupCheck->execute();
    if ($dupCheck->get_result()->num_rows > 0) {
        $dupCheck->close();
        redirectWith($employee_portal_base . '/add-employee.php', 'danger', "An employee named '$first_name $last_name' already exists in the system.");
    }
    $dupCheck->close();

    if ($employee_code !== null) {
        $codeCheck = $conn->prepare("SELECT employee_id FROM employees WHERE employee_code = ?");
        $codeCheck->bind_param("s", $employee_code);
        $codeCheck->execute();
        if ($codeCheck->get_result()->num_rows > 0) {
            $codeCheck->close();
            redirectWith($employee_portal_base . '/add-employee.php', 'danger', "Employee ID '$employee_code' already exists in the system.");
        }
        $codeCheck->close();
    }

    // Build address string for legacy column
    $address = trim("$res_house_no $res_street $res_subdivision $res_barangay $res_city $res_province $res_zip_code");

    // Use Transaction for Normalized Tables
    $conn->begin_transaction();
    try {
        $sql = "INSERT INTO employees (
            employee_code, first_name, last_name, middle_name, name_extension,
            date_of_birth, place_of_birth, gender, civil_status,
            hire_date, job_title, job_title_id, department_id, rank_category_id, branch_id,
            employment_status, employment_type, contract_start_date, contract_end_date, profile_picture
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssssssssiiiisssss",
            $employee_code,
            $first_name,
            $last_name,
            $middle_name,
            $name_extension,
            $date_of_birth,
            $place_of_birth,
            $gender,
            $civil_status,
            $hire_date,
            $job_title,
            $job_title_id,
            $department_id,
            $rank_category_id,
            $branch_id,
            $employment_status,
            $employment_type,
            $contract_start_date,
            $contract_end_date,
            $profile_picture
        );
        $stmt->execute();
        $new_id = $stmt->insert_id;
        $stmt->close();

        // 1. Details
        $stmt = $conn->prepare("INSERT INTO employee_details (employee_id, height_m, weight_kg, blood_type, citizenship) VALUES (?,?,?,?,?)");
        $stmt->bind_param("iddss", $new_id, $height_m, $weight_kg, $blood_type, $citizenship);
        $stmt->execute();
        $stmt->close();

        // 2. Gov IDs
        $stmt = $conn->prepare("INSERT INTO employee_government_ids (employee_id, sss_number, philhealth_number, pagibig_number, tin_number) VALUES (?,?,?,?,?)");
        $stmt->bind_param("issss", $new_id, $sss_number, $philhealth_number, $pagibig_number, $tin_number);
        $stmt->execute();
        $stmt->close();

        // 3. Contacts
        $stmt = $conn->prepare("INSERT INTO employee_contacts (employee_id, telephone_number, mobile_number, personal_email) VALUES (?,?,?,?)");
        $stmt->bind_param("isss", $new_id, $telephone_number, $contact_number, $email);
        $stmt->execute();
        $stmt->close();

        // 4. Addresses (Residential)
        if (!empty($res_street) || !empty($res_city) || !empty($res_province)) {
            $stmt = $conn->prepare("INSERT INTO employee_addresses (employee_id, address_type, region, house_no, street, subdivision, barangay, city, province, zip_code) VALUES (?, 'Residential', ?,?,?,?,?,?,?,?)");
            $stmt->bind_param("issssssss", $new_id, $res_region, $res_house_no, $res_street, $res_subdivision, $res_barangay, $res_city, $res_province, $res_zip_code);
            $stmt->execute();
            $stmt->close();
        }

        // 5. Addresses (Permanent)
        if (!empty($perm_street) || !empty($perm_city) || !empty($perm_province)) {
            $stmt = $conn->prepare("INSERT INTO employee_addresses (employee_id, address_type, region, house_no, street, subdivision, barangay, city, province, zip_code) VALUES (?, 'Permanent', ?,?,?,?,?,?,?,?)");
            $stmt->bind_param("issssssss", $new_id, $perm_region, $perm_house_no, $perm_street, $perm_subdivision, $perm_barangay, $perm_city, $perm_province, $perm_zip_code);
            $stmt->execute();
            $stmt->close();
        }

        // 6. Emergency Contacts
        if (!empty($_POST['emergency_contact_name'])) {
            $primary_idx = isset($_POST['emergency_is_primary']) ? (int)$_POST['emergency_is_primary'] : 1;
            $idx = 0;
            $stmt = $conn->prepare("INSERT INTO employee_emergency_contacts (employee_id, contact_name, relationship, contact_number, is_primary) VALUES (?,?,?,?,?)");
            foreach ($_POST['emergency_contact_name'] as $i => $name) {
                $name = trim($name);
                if ($name === '') continue;
                $idx++;
                $rel = trim($_POST['emergency_contact_relationship'][$i] ?? '');
                $num = formatPHMobileNumber(trim($_POST['emergency_contact_number'][$i] ?? ''));
                $is_pri = ($idx === $primary_idx) ? 1 : 0;
                $stmt->bind_param("isssi", $new_id, $name, $rel, $num, $is_pri);
                $stmt->execute();
            }
            $stmt->close();
        }

        // 7. Disclosures
        $stmt = $conn->prepare("INSERT INTO employee_disclosures (
            employee_id, is_related_to_company, related_details, has_admin_offense, admin_offense_details,
            has_criminal_charge, criminal_charge_details, has_criminal_conviction, criminal_conviction_details,
            has_been_separated, separation_details, is_pwd, pwd_details, is_solo_parent, solo_parent_details,
            has_recent_hospital, hospital_details, has_current_treatment, treatment_details
        ) VALUES (?, ?,?,?,?, ?,?,?,?, ?,?,?,?, ?,?,?,?, ?,?)");
        $stmt->bind_param(
            "iisssssssssssssssss",
            $new_id,
            $is_related_to_company,
            $related_details,
            $has_admin_offense,
            $admin_offense_details,
            $has_criminal_charge,
            $criminal_charge_details,
            $has_criminal_conviction,
            $criminal_conviction_details,
            $has_been_separated,
            $separation_details,
            $is_pwd,
            $pwd_details,
            $is_solo_parent,
            $solo_parent_details,
            $has_recent_hospital,
            $hospital_details,
            $has_current_treatment,
            $treatment_details
        );
        $stmt->execute();
        $stmt->close();

        // 8. Family (Spouse)
        if (!empty($spouse_surname) || !empty($spouse_first_name)) {
            $stmt = $conn->prepare("INSERT INTO employee_family (employee_id, member_type, surname, first_name, middle_name, name_extension, occupation) VALUES (?, 'Spouse', ?,?,?,?,?)");
            $stmt->bind_param("isssss", $new_id, $spouse_surname, $spouse_first_name, $spouse_middle_name, $spouse_name_ext, $spouse_occupation);
            $stmt->execute();
            $stmt->close();
        }

        // 9. Family (Parents)
        if (!empty($father_surname) || !empty($father_first_name)) {
            $stmt = $conn->prepare("INSERT INTO employee_family (employee_id, member_type, surname, first_name, middle_name, name_extension, occupation) VALUES (?, 'Father', ?,?,?,?,?)");
            $stmt->bind_param("isssss", $new_id, $father_surname, $father_first_name, $father_middle_name, $father_name_ext, $father_occupation);
            $stmt->execute();
            $stmt->close();
        }
        if (!empty($mother_maiden_surname) || !empty($mother_first_name)) {
            $stmt = $conn->prepare("INSERT INTO employee_family (employee_id, member_type, surname, first_name, middle_name, occupation) VALUES (?, 'Mother', ?,?,?,?)");
            $stmt->bind_param("issss", $new_id, $mother_maiden_surname, $mother_first_name, $mother_middle_name, $mother_occupation);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();

        // Save child table data
        // Children
        if (!empty($_POST['child_first_name'])) {
            $cstmt = $conn->prepare("INSERT INTO employee_children (employee_id, surname, first_name, middle_name, date_of_birth) VALUES (?,?,?,?,?)");
            foreach ($_POST['child_first_name'] as $i => $cfn) {
                if (empty(trim($cfn)))
                    continue;
                $cs = trim($_POST['child_surname'][$i] ?? '');
                $cfn = trim($cfn);
                $cm = trim($_POST['child_middle_name'][$i] ?? '');
                $cd = !empty($_POST['child_dob'][$i]) ? $_POST['child_dob'][$i] : null;
                $cstmt->bind_param("issss", $new_id, $cs, $cfn, $cm, $cd);
                $cstmt->execute();
            }
            $cstmt->close();
        }

        // Siblings
        if (!empty($_POST['sibling_first_name'])) {
            $sstmt = $conn->prepare("INSERT INTO employee_siblings (employee_id, surname, first_name, middle_name, date_of_birth) VALUES (?,?,?,?,?)");
            foreach ($_POST['sibling_first_name'] as $i => $sfn) {
                if (empty(trim($sfn)))
                    continue;
                $ss = trim($_POST['sibling_surname'][$i] ?? '');
                $sfn = trim($sfn);
                $sm = trim($_POST['sibling_middle_name'][$i] ?? '');
                $sd = !empty($_POST['sibling_dob'][$i]) ? $_POST['sibling_dob'][$i] : null;
                $sstmt->bind_param("issss", $new_id, $ss, $sfn, $sm, $sd);
                $sstmt->execute();
            }
            $sstmt->close();
        }

        // Education
        if (!empty($_POST['edu_level'])) {
            $estmt = $conn->prepare("INSERT INTO employee_education (employee_id, education_level, school_name, degree_course, period_from, period_to, highest_level_units, year_graduated, honors_received) VALUES (?,?,?,?,?,?,?,?,?)");
            foreach ($_POST['edu_level'] as $i => $lvl) {
                if (empty(trim($_POST['edu_school'][$i] ?? '')))
                    continue;
                $school = trim($_POST['edu_school'][$i]);
                $degree = trim($_POST['edu_degree'][$i] ?? '');
                $pfrom = !empty($_POST['edu_from'][$i]) ? (strlen($_POST['edu_from'][$i]) == 4 ? $_POST['edu_from'][$i] . '-01-01' : $_POST['edu_from'][$i]) : null;
                $pto = !empty($_POST['edu_to'][$i]) ? (strlen($_POST['edu_to'][$i]) == 4 ? $_POST['edu_to'][$i] . '-01-01' : $_POST['edu_to'][$i]) : null;
                $units = trim($_POST['edu_units'][$i] ?? '');
                $ygrad = trim($_POST['edu_year_grad'][$i] ?? '');
                $honors = trim($_POST['edu_honors'][$i] ?? '');
                $estmt->bind_param("issssssss", $new_id, $lvl, $school, $degree, $pfrom, $pto, $units, $ygrad, $honors);
                $estmt->execute();
            }
            $estmt->close();
        }

        // Work Experience
        if (!empty($_POST['work_title'])) {
            $wstmt = $conn->prepare("INSERT INTO employee_work_experience (employee_id, date_from, date_to, job_title, company_name, monthly_salary, appointment_status, reason_for_leaving) VALUES (?,?,?,?,?,?,?,?)");
            foreach ($_POST['work_title'] as $i => $wt) {
                if (empty(trim($wt)))
                    continue;
                $wf = !empty($_POST['work_from'][$i]) ? (strlen($_POST['work_from'][$i]) == 4 ? $_POST['work_from'][$i] . '-01-01' : $_POST['work_from'][$i]) : null;
                $wto = !empty($_POST['work_to'][$i]) ? (strlen($_POST['work_to'][$i]) == 4 ? $_POST['work_to'][$i] . '-01-01' : $_POST['work_to'][$i]) : null;
                $wt = trim($wt);
                $wc = trim($_POST['work_company'][$i] ?? '');
                $ws = !empty($_POST['work_salary'][$i]) ? (float) $_POST['work_salary'][$i] : null;
                $wa = trim($_POST['work_status'][$i] ?? '');
                $wr = trim($_POST['work_reason'][$i] ?? '');
                $wstmt->bind_param("issssdss", $new_id, $wf, $wto, $wt, $wc, $ws, $wa, $wr);
                $wstmt->execute();
            }
            $wstmt->close();
        }

        // Trainings
        if (!empty($_POST['training_title'])) {
            $tstmt = $conn->prepare("INSERT INTO employee_trainings (employee_id, date_from, date_to, training_title, training_type, no_of_hours, conducted_by) VALUES (?,?,?,?,?,?,?)");
            foreach ($_POST['training_title'] as $i => $tt) {
                if (empty(trim($tt)))
                    continue;
                $tf = !empty($_POST['training_from'][$i]) ? (strlen($_POST['training_from'][$i]) == 4 ? $_POST['training_from'][$i] . '-01-01' : $_POST['training_from'][$i]) : null;
                $tto = !empty($_POST['training_to'][$i]) ? (strlen($_POST['training_to'][$i]) == 4 ? $_POST['training_to'][$i] . '-01-01' : $_POST['training_to'][$i]) : null;
                $tt = trim($tt);
                $ttype = trim($_POST['training_type'][$i] ?? '');
                $th = !empty($_POST['training_hours'][$i]) ? (float) $_POST['training_hours'][$i] : null;
                $tc = trim($_POST['training_conducted'][$i] ?? '');
                $tstmt->bind_param("issssds", $new_id, $tf, $tto, $tt, $ttype, $th, $tc);
                $tstmt->execute();
            }
            $tstmt->close();
        }

        // Voluntary Work
        if (!empty($_POST['vol_org'])) {
            $vstmt = $conn->prepare("INSERT INTO employee_voluntary_work (employee_id, date_from, date_to, organization_name, organization_address, no_of_hours, position_nature) VALUES (?,?,?,?,?,?,?)");
            foreach ($_POST['vol_org'] as $i => $vo) {
                if (empty(trim($vo)))
                    continue;
                $vf = !empty($_POST['vol_from'][$i]) ? (strlen($_POST['vol_from'][$i]) == 4 ? $_POST['vol_from'][$i] . '-01-01' : $_POST['vol_from'][$i]) : null;
                $vto = !empty($_POST['vol_to'][$i]) ? (strlen($_POST['vol_to'][$i]) == 4 ? $_POST['vol_to'][$i] . '-01-01' : $_POST['vol_to'][$i]) : null;
                $vo = trim($vo);
                $va = trim($_POST['vol_address'][$i] ?? '');
                $vh = !empty($_POST['vol_hours'][$i]) ? (float) $_POST['vol_hours'][$i] : null;
                $vp = trim($_POST['vol_position'][$i] ?? '');
                $vstmt->bind_param("issssds", $new_id, $vf, $vto, $vo, $va, $vh, $vp);
                $vstmt->execute();
            }
            $vstmt->close();
        }

        // Eligibility
        if (!empty($_POST['elig_title'])) {
            $elstmt = $conn->prepare("INSERT INTO employee_eligibility (employee_id, license_title, date_from, date_to, license_number, date_of_exam, place_of_exam) VALUES (?,?,?,?,?,?,?)");
            foreach ($_POST['elig_title'] as $i => $et) {
                if (empty(trim($et)))
                    continue;
                $ef = !empty($_POST['elig_from'][$i]) ? (strlen($_POST['elig_from'][$i]) == 4 ? $_POST['elig_from'][$i] . '-01-01' : $_POST['elig_from'][$i]) : null;
                $eto = !empty($_POST['elig_to'][$i]) ? (strlen($_POST['elig_to'][$i]) == 4 ? $_POST['elig_to'][$i] . '-01-01' : $_POST['elig_to'][$i]) : null;
                $en = trim($_POST['elig_number'][$i] ?? '');
                $ed = !empty($_POST['elig_exam_date'][$i]) ? $_POST['elig_exam_date'][$i] : null;
                $ep = trim($_POST['elig_exam_place'][$i] ?? '');
                $elstmt->bind_param("issssss", $new_id, $et, $ef, $eto, $en, $ed, $ep);
                $elstmt->execute();
            }
            $elstmt->close();
        }

        // Skills
        if (!empty($_POST['skill_name'])) {
            $skstmt = $conn->prepare("INSERT INTO employee_skills (employee_id, skill_name) VALUES (?,?)");
            foreach ($_POST['skill_name'] as $sk) {
                if (empty(trim($sk)))
                    continue;
                $sk = trim($sk);
                $skstmt->bind_param("is", $new_id, $sk);
                $skstmt->execute();
            }
            $skstmt->close();
        }

        // Recognitions
        if (!empty($_POST['recognition_title'])) {
            $rcstmt = $conn->prepare("INSERT INTO employee_recognitions (employee_id, recognition_title) VALUES (?,?)");
            foreach ($_POST['recognition_title'] as $i => $rc) {
                if (empty(trim($rc)))
                    continue;
                $rt = trim($rc);
                $rib = trim($_POST['recognition_issued_by'][$i] ?? '');
                $rd = !empty($_POST['recognition_date'][$i]) ? $_POST['recognition_date'][$i] : '';
                // Append issued_by and date to title (table only has recognition_title column)
                if ($rib || $rd) {
                    $rt .= ' - ' . $rib . ($rd ? ' (' . $rd . ')' : '');
                }
                $rcstmt->bind_param("is", $new_id, $rt);
                $rcstmt->execute();
            }
            $rcstmt->close();
        }

        // Memberships
        if (!empty($_POST['membership_org'])) {
            $mbstmt = $conn->prepare("INSERT INTO employee_memberships (employee_id, organization_name) VALUES (?,?)");
            foreach ($_POST['membership_org'] as $mb) {
                if (empty(trim($mb)))
                    continue;
                $mb = trim($mb);
                $mbstmt->bind_param("is", $new_id, $mb);
                $mbstmt->execute();
            }
            $mbstmt->close();
        }

        // Real Properties
        if (!empty($_POST['rprop_desc'])) {
            $rpstmt = $conn->prepare("INSERT INTO employee_real_properties (employee_id, description, kind, exact_location, assessed_value, market_value, acquisition_year_mode, acquisition_cost) VALUES (?,?,?,?,?,?,?,?)");
            foreach ($_POST['rprop_desc'] as $i => $rd) {
                if (empty(trim($rd)))
                    continue;
                $rk = trim($_POST['rprop_kind'][$i] ?? '');
                $rl = trim($_POST['rprop_location'][$i] ?? '');
                $rav = !empty($_POST['rprop_assessed'][$i]) ? (float) $_POST['rprop_assessed'][$i] : null;
                $rmv = !empty($_POST['rprop_market'][$i]) ? (float) $_POST['rprop_market'][$i] : null;
                $ram = trim($_POST['rprop_acq_mode'][$i] ?? '');
                $rac = !empty($_POST['rprop_acq_cost'][$i]) ? (float) $_POST['rprop_acq_cost'][$i] : null;
                $rd = trim($rd);
                $rpstmt->bind_param("isssddsd", $new_id, $rd, $rk, $rl, $rav, $rmv, $ram, $rac);
                $rpstmt->execute();
            }
            $rpstmt->close();
        }

        // Personal Properties
        if (!empty($_POST['pprop_desc'])) {
            $ppstmt = $conn->prepare("INSERT INTO employee_personal_properties (employee_id, description, year_acquired, acquisition_cost) VALUES (?,?,?,?)");
            foreach ($_POST['pprop_desc'] as $i => $pd) {
                if (empty(trim($pd)))
                    continue;
                $pd = trim($pd);
                $py = trim($_POST['pprop_year'][$i] ?? '');
                $pc = !empty($_POST['pprop_cost'][$i]) ? (float) $_POST['pprop_cost'][$i] : null;
                $ppstmt->bind_param("issd", $new_id, $pd, $py, $pc);
                $ppstmt->execute();
            }
            $ppstmt->close();
        }

        // Liabilities
        if (!empty($_POST['liab_nature'])) {
            $lstmt = $conn->prepare("INSERT INTO employee_liabilities (employee_id, nature_of_liability, creditor_name, outstanding_balance) VALUES (?,?,?,?)");
            foreach ($_POST['liab_nature'] as $i => $ln) {
                if (empty(trim($ln)))
                    continue;
                $ln = trim($ln);
                $lc = trim($_POST['liab_creditor'][$i] ?? '');
                $lb = !empty($_POST['liab_balance'][$i]) ? (float) $_POST['liab_balance'][$i] : null;
                $lstmt->bind_param("issd", $new_id, $ln, $lc, $lb);
                $lstmt->execute();
            }
            $lstmt->close();
        }

        // References
        if (!empty($_POST['ref_name'])) {
            $rfstmt = $conn->prepare("INSERT INTO employee_references (employee_id, reference_name, reference_address, reference_telephone) VALUES (?,?,?,?)");
            foreach ($_POST['ref_name'] as $i => $rn) {
                if (empty(trim($rn)))
                    continue;
                $rn = trim($rn);
                $ra = trim($_POST['ref_address'][$i] ?? '');
                $rt = formatPHMobileNumber(trim($_POST['ref_telephone'][$i] ?? ''));
                $rfstmt->bind_param("isss", $new_id, $rn, $ra, $rt);
                $rfstmt->execute();
            }
            $rfstmt->close();
        }

        // Check for governance title linkage (DO NOT auto-provision user accounts - that is Admin's job)
        autoDetectAndLinkGovernanceApprover($conn, $new_id);

        logAudit($conn, $_SESSION['user_id'], 'CREATE', 'Employee', $new_id, "Added employee: $first_name $last_name");
        redirectWith($employee_portal_base . '/employees.php', 'success', "Employee '$first_name $last_name' added successfully.");

    } catch (Exception $e) {
        $conn->rollback();
        // Save information to session to prevent starting over after redirect
        $_SESSION['form_draft'] = $_POST;
        redirectWith($employee_portal_base . '/add-employee.php', 'danger', "Failed to add employee: " . $e->getMessage());
    }
}

require_once '../includes/header.php';
$branches = $conn->query("SELECT * FROM branches ORDER BY branch_name");
$departments_result = $conn->query("SELECT department_id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name");
$departments = $departments_result ? $departments_result->fetch_all(MYSQLI_ASSOC) : [];
$job_titles_result = $conn->query("
    SELECT job_title_id, job_title, department_id, rank_category_id, is_head, reports_to
    FROM job_titles
    WHERE is_active = 1
    ORDER BY department_id, is_head DESC, job_title
");
$jobTitles = $job_titles_result ? $job_titles_result->fetch_all(MYSQLI_ASSOC) : [];

$stepLabels = [
    '1' => 'Personal Info',
    '2' => 'Family',
    '3' => 'Education',
    '4' => 'Work Exp.',
    '5' => 'Training',
    '6' => 'Voluntary',
    '7' => 'Eligibility',
    '8' => 'Skills',
    '9' => 'Assets',
    '10' => 'Disclosures',
    '11' => 'References',
    '12' => 'Employment',
];
?>

<div class="page-hero fadeup mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR
                Manager · Employees</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-user-plus me-2" style="color:#BD9414;"></i>Add New
                Employee</h4>
            <p class="text-white-50 small mb-0 mt-2">Create an employee record manually or import validated employee details from a CSV file.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal"
                style="background: linear-gradient(135deg, #28a745 0%, #218838 100%); border: none; padding: .5rem 1.25rem; border-radius: 8px; font-weight: 500; color: #fff;">
                <i class="fas fa-file-csv me-2"></i>Import Custom CSV
            </button>
        </div>
    </div>
</div>











<div class="content-card">
    <div class="card-header">
        <h5><i class="fas fa-user-plus me-2"></i>Add New Employee (Personal Data Sheet)</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="" id="addEmployeeForm" enctype="multipart/form-data" data-is-edit="false">
            <?php echo csrfField(); ?>
            <!-- Wizard Header / Progress (matches Employee PDS wizard UX) -->
            <div class="pds-progress-container mb-3">
                <div class="pds-progress-wrapper">
                    <div id="pdsProgressBar" class="pds-progress-bar" style="width: 8.33%;"></div>
                </div>
                <div class="pds-progress-percent" id="pdsProgressPercent">8%</div>
            </div>

            <!-- Portal Tabs -->
            <div class="portal-tabs">
                <div class="portal-tab active" id="portal-tab-1" onclick="showPortal(1)">
                    <div class="portal-num">1</div>
                    <div class="portal-label-wrapper">
                        <span class="portal-label">Core Identity</span>
                        <div class="portal-sub-steps" id="portal-sub-1">
                            <!-- Dots injected/updated by JS -->
                        </div>
                    </div>
                </div>
                <div class="portal-tab" id="portal-tab-2" onclick="showPortal(2)">
                    <div class="portal-num">2</div>
                    <div class="portal-label-wrapper">
                        <span class="portal-label">Background</span>
                        <div class="portal-sub-steps" id="portal-sub-2">
                            <!-- Dots injected/updated by JS -->
                        </div>
                    </div>
                </div>
                <div class="portal-tab" id="portal-tab-3" onclick="showPortal(3)">
                    <div class="portal-num">3</div>
                    <div class="portal-label-wrapper">
                        <span class="portal-label">Qualifications</span>
                        <div class="portal-sub-steps" id="portal-sub-3">
                            <!-- Dots injected/updated by JS -->
                        </div>
                    </div>
                </div>
                <div class="portal-tab" id="portal-tab-4" onclick="showPortal(4)">
                    <div class="portal-num">4</div>
                    <div class="portal-label-wrapper">
                        <span class="portal-label">Final</span>
                        <div class="portal-sub-steps" id="portal-sub-4">
                            <!-- Dots injected/updated by JS -->
                        </div>
                    </div>
                </div>
            </div>

            <?php include __DIR__ . '/../includes/employee-form-steps.php'; ?>

            <!-- Sticky Wizard Footer -->
            <div class="wizard-footer mt-4">
                <button type="button" id="prevBtn" onclick="prevStep()" class="btn btn-outline-secondary px-4 shadow-sm"
                    style="display:none;">
                    <i class="fas fa-arrow-left me-2"></i>Back
                </button>
                <div class="text-muted small d-none d-md-block" id="wizardProgressLabel">
                    Portal 1 of 4 · Step 1 of 12
                </div>
                <div class="d-flex gap-2">
                    <button type="button" id="nextBtn" onclick="nextStep()" class="btn btn-primary px-4 shadow-sm">
                        Next <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                    <button type="submit" id="submitBtn" class="btn btn-success px-4 shadow-sm" style="display:none;">
                        <i class="fas fa-save me-2"></i>Save Employee
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/employee-form.js?v=<?php echo time(); ?>"></script>



<?php require_once '../includes/footer.php'; ?>

<!-- Import CSV Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" enctype="multipart/form-data">
                <?php echo csrfField(); ?>
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="importModalLabel"><i class="fas fa-file-csv me-2"></i>Import Employees
                        from CSV</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Upload a CSV UTF-8 file to bulk import complete employee records. Excel workbooks (.xlsx) are not supported; save them as CSV first. Ensure your file matches the system's exact column format.</p>

                    <div class="mb-3">
                        <label for="employee_csv" class="form-label fw-bold">Select CSV File</label>
                        <input class="form-control" type="file" id="employee_csv" name="employee_csv" accept=".csv"
                            required>
                    </div>

                    <div class="alert alert-info py-2 small mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        Need the format? <a href="<?php echo BASE_URL; ?>/manager/download-sample.php"
                            class="alert-link">Download the sample template</a>.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="import_csv" class="btn btn-success"><i
                            class="fas fa-upload me-2"></i>Upload File</button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>
</div>
</div>
