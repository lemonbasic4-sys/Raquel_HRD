<?php
require_once '../includes/session-check.php';
checkRole(['HR Manager', 'HR Supervisor']);

// Clear any previous output buffers
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=sample_employees.csv');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");

// PDS column order. Section names are kept here for maintenance; the CSV still has one header row for importing.
$pds_sections = [
    'Basic Identity' => ['First Name', 'Last Name', 'Middle Name', 'Extension'],
    'Birth & Status' => ['Birthday', 'Birthplace', 'Gender', 'Civil Status'],
    'Physical & Citizenship' => ['Height (m)', 'Weight (kg)', 'Blood Type', 'Citizenship'],
    'Government IDs' => ['SSS No', 'PhilHealth No', 'Pag-IBIG No', 'TIN No'],
    'Permanent Address' => ['Permanent Region', 'Permanent House No', 'Permanent Street', 'Permanent Subdivision', 'Permanent Barangay', 'Permanent City', 'Permanent Province', 'Permanent Zip Code', 'Residential Region', 'Residential House No', 'Residential Street', 'Residential Subdivision', 'Residential Barangay', 'Residential City', 'Residential Province', 'Residential Zip Code'],
    'Contact Information' => ['Telephone No', 'Mobile No', 'Email'],
    'Spouse Information' => ['Spouse Name', 'Spouse Occupation'],
    "Father's Information" => ['Father Surname', 'Father First Name', 'Father Middle Name', 'Father Extension', 'Father Occupation'],
    "Mother's Maiden Name" => ['Mother Maiden Surname', 'Mother First Name', 'Mother Middle Name', 'Mother Occupation'],
    'Children' => ['Child 1 Surname', 'Child 1 First Name', 'Child 1 Middle Name', 'Child 1 Birthday'],
    'Siblings' => ['Sibling 1 Surname', 'Sibling 1 First Name', 'Sibling 1 Middle Name', 'Sibling 1 Birthday'],
    'Educational Background' => ['Elementary School', 'Elementary Year Graduated', 'High School', 'High School Year Graduated', 'Senior High School', 'Senior High School Degree/Course', 'Senior High School Year Graduated', 'Vocational School', 'Vocational Degree/Course', 'Vocational Year Graduated', 'College School', 'College Degree/Course', 'College Year Graduated', 'Graduate Studies School', 'Graduate Studies Degree/Course', 'Graduate Studies Year Graduated'],
    'Service Eligibility / Licenses' => ['Eligibility License Title 1', 'Eligibility License No 1'],
    'Special Skills & Hobbies' => ['Skill/Hobby 1'],
    'Non-Academic Distinctions / Recognition' => ['Recognition 1 Title'],
    'Membership in Organizations' => ['Membership 1 Organization'],
    'Real Properties' => ['Real Property 1 Description', 'Real Property 1 Market Value'],
    'Personal Properties' => ['Personal Property 1 Description', 'Personal Property 1 Cost'],
    'Liabilities' => ['Liability 1 Nature', 'Liability 1 Outstanding Balance'],
    'Employment-Related Disclosures' => ['Related to Company (Yes/No)', 'Related Details', 'Admin Offense (Yes/No)', 'Admin Offense Details', 'Criminal Charge (Yes/No)', 'Criminal Charge Details', 'Criminal Conviction (Yes/No)', 'Criminal Conviction Details', 'Previously Separated (Yes/No)', 'Separation Details', 'PWD Status (Yes/No)', 'PWD Details', 'Solo Parent Status (Yes/No)', 'Solo Parent Details', 'Recent Hospitalization (Yes/No)', 'Hospital Details', 'Current Treatment (Yes/No)', 'Treatment Details'],
    'Character References (3 persons not related)' => ['Reference 1 Name', 'Reference 1 Address', 'Reference 1 Contact Number', 'Reference 2 Name', 'Reference 2 Address', 'Reference 2 Contact Number', 'Reference 3 Name', 'Reference 3 Address', 'Reference 3 Contact Number'],
    'Employment Details' => ['Company ID', 'Hire Date', 'Job Title', 'Rank', 'Department', 'Branch', 'Employment Status', 'Employment Type', 'Contract Start Date', 'Contract End Date', 'Previous Company 1', 'Previous Position 1', 'Previous Monthly Salary 1', 'Previous Employment Period 1', 'Previous Reason for Leaving 1', 'Training Title 1', 'Training Conducted By 1', 'Training Hours 1'],
    'Emergency Contacts' => ['Emergency Contact Name', 'Emergency Contact Relationship', 'Emergency Contact Number'],
];
$headers = array_merge(...array_values($pds_sections));
fputcsv($output, $headers);

// ── LEGEND / HINT ROW ────────────────────────────────────────────────────────
// This row explains accepted values for key fields. DELETE THIS ROW before uploading.
$legend = array_fill_keys($headers, '');
$legend['Company ID'] = 'Use a unique Company ID in your organization\'s format. HRD-001 and AP-001 are examples only. An existing ID updates only the matching employee; conflicting or repeated IDs are skipped for review. Login accounts are provisioned separately by Admin.';
$legend['Job Title'] = '[Required] Use the configured job title';
$legend['Graduate Studies School'] = 'Optional. Blank education fields preserve existing records on reimport.';
$legend['First Name']                      = '[Required] Employee first name';
$legend['Last Name']                       = '[Required] Employee last name';
$legend['Birthday']                        = '[Optional] Format: MM/DD/YYYY';
$legend['Gender']                          = 'Male | Female | Other';
$legend['Civil Status']                    = 'Single | Married | Widowed | Separated | Other';
$legend['Blood Type']                      = 'A | B | AB | O  (optionally add + or -, e.g. O+)';
$legend['SSS No']                          = 'Format: 01-2345678-9';
$legend['PhilHealth No']                   = 'Format: 12-345678901-2';
$legend['Pag-IBIG No']                     = 'Format: 1234-5678-9012';
$legend['TIN No']                          = 'Format: 123-456-789-000';
$legend['Hire Date']                       = '[Required] MM/DD/YYYY — For OJT/Trainee/Probationary/Project Based: use first engagement date, not regularization date';
$legend['Employment Status']               = '[Required] Regular | OJT | Trainee | Probationary | Project Based | Separated | AWOL | Retirement | Death | Resignation | Termination for Cause | Failed in Training | Permanent or Total Disability';
$legend['Employment Type']                 = 'Full-time | Part-time';
$legend['Contract Start Date']             = '[Required for OJT / Trainee / Probationary / Project Based] MM/DD/YYYY — Start of the arrangement period. Leave blank for Regular.';
$legend['Contract End Date']               = '[Optional] MM/DD/YYYY — Expected end of the arrangement period. Leave blank if ongoing or if Regular.';
$legend['Rank']                            = 'Executives | Management Team | Manager | Supervisor | R&F';
$legend['Related to Company (Yes/No)']     = 'Yes | No';
$legend['Admin Offense (Yes/No)']          = 'Yes | No';
$legend['Criminal Charge (Yes/No)']        = 'Yes | No';
$legend['Criminal Conviction (Yes/No)']    = 'Yes | No';
$legend['Previously Separated (Yes/No)']  = 'Yes | No';
$legend['PWD Status (Yes/No)']             = 'Yes | No';
$legend['Solo Parent Status (Yes/No)']     = 'Yes | No';
$legend['Recent Hospitalization (Yes/No)'] = 'Yes | No';
$legend['Current Treatment (Yes/No)']     = 'Yes | No';
fputcsv($output, array_map(fn($h) => $legend[$h] ?? '', $headers));

// Three complete, fictional HR employee examples. Optional inapplicable fields remain blank.
require __DIR__ . '/sample-hr-employees.php';
foreach ($samples as $sample) {
    fputcsv($output, array_map(fn($h) => $sample[$h] ?? '', $headers));
}

fclose($output);
exit();
