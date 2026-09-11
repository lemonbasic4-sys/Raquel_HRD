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
    'Educational Background' => ['Elementary School', 'Elementary Year Graduated', 'High School', 'High School Year Graduated', 'College School', 'College Degree/Course', 'College Year Graduated'],
    'Service Eligibility / Licenses' => ['Eligibility License Title 1', 'Eligibility License No 1'],
    'Special Skills & Hobbies' => ['Skill/Hobby 1'],
    'Non-Academic Distinctions / Recognition' => ['Recognition 1 Title'],
    'Membership in Organizations' => ['Membership 1 Organization'],
    'Real Properties' => ['Real Property 1 Description', 'Real Property 1 Market Value'],
    'Personal Properties' => ['Personal Property 1 Description', 'Personal Property 1 Cost'],
    'Liabilities' => ['Liability 1 Nature', 'Liability 1 Outstanding Balance'],
    'Employment-Related Disclosures' => ['Related to Company (Yes/No)', 'Related Details', 'Admin Offense (Yes/No)', 'Admin Offense Details', 'Criminal Charge (Yes/No)', 'Criminal Charge Details', 'PWD Status (Yes/No)', 'Solo Parent Status (Yes/No)'],
    'Character References (3 persons not related)' => ['Reference 1 Name', 'Reference 1 Address', 'Reference 1 Contact Number', 'Reference 2 Name', 'Reference 2 Address', 'Reference 2 Contact Number', 'Reference 3 Name', 'Reference 3 Address', 'Reference 3 Contact Number'],
    'Employment Details' => ['Company ID', 'Hire Date', 'Job Title', 'Rank', 'Department', 'Branch', 'Employment Status', 'Employment Type', 'Contract Start Date', 'Contract End Date', 'Previous Company 1', 'Previous Position 1', 'Previous Monthly Salary 1', 'Previous Employment Period 1', 'Previous Reason for Leaving 1', 'Training Title 1', 'Training Conducted By 1', 'Training Hours 1'],
    'Emergency Contacts' => ['Emergency Contact Name', 'Emergency Contact Relationship', 'Emergency Contact Number'],
];
$headers = array_merge(...array_values($pds_sections));
fputcsv($output, $headers);

// ── LEGEND / HINT ROW ────────────────────────────────────────────────────────
// This row explains accepted values for key fields. DELETE THIS ROW before uploading.
$legend = array_fill_keys($headers, '');
$legend['First Name']                      = '[Required] Employee first name';
$legend['Last Name']                       = '[Required] Employee last name';
$legend['Birthday']                        = '[Required] Format: MM/DD/YYYY';
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
$legend['PWD Status (Yes/No)']             = 'Yes | No';
$legend['Solo Parent Status (Yes/No)']     = 'Yes | No';
fputcsv($output, array_map(fn($h) => $legend[$h] ?? '', $headers));

// ── SAMPLE ROWS (one per key Employment Status) ───────────────────────────────
// Shared fields reused across all sample rows to reduce duplication.
$shared = [
    'Extension' => '', 'Birthplace' => 'Manila, Metro Manila',
    'Height (m)' => '1.63', 'Weight (kg)' => '57', 'Citizenship' => 'Filipino',
    'Permanent Region' => 'Region III (Central Luzon)', 'Permanent House No' => '45',
    'Permanent Street' => 'Mabini Street', 'Permanent Subdivision' => 'Sunshine Village',
    'Permanent Barangay' => 'Barangay 5', 'Permanent City' => 'Angeles City',
    'Permanent Province' => 'Pampanga', 'Permanent Zip Code' => '2009',
    'Residential Region' => 'Region III (Central Luzon)', 'Residential House No' => '45',
    'Residential Street' => 'Mabini Street', 'Residential Subdivision' => 'Sunshine Village',
    'Residential Barangay' => 'Barangay 5', 'Residential City' => 'Angeles City',
    'Residential Province' => 'Pampanga', 'Residential Zip Code' => '2009',
    'Telephone No' => '(045) 987-6543',
    'Spouse Name' => '', 'Spouse Occupation' => '',
    'Father Surname' => 'Reyes', 'Father First Name' => 'Roberto', 'Father Middle Name' => 'Cruz',
    'Father Extension' => '', 'Father Occupation' => 'Accountant',
    'Mother Maiden Surname' => 'Garcia', 'Mother First Name' => 'Lourdes',
    'Mother Middle Name' => 'Bautista', 'Mother Occupation' => 'Nurse',
    'Child 1 Surname' => '', 'Child 1 First Name' => '', 'Child 1 Middle Name' => '', 'Child 1 Birthday' => '',
    'Sibling 1 Surname' => 'Reyes', 'Sibling 1 First Name' => 'Mark',
    'Sibling 1 Middle Name' => 'Santos', 'Sibling 1 Birthday' => '04/10/1998',
    'Elementary School' => 'Holy Rosary Elementary School', 'Elementary Year Graduated' => '2006',
    'High School' => 'Angeles City National High School', 'High School Year Graduated' => '2010',
    'College School' => 'Holy Angel University', 'College Degree/Course' => 'BS Business Administration',
    'College Year Graduated' => '2014',
    'Eligibility License Title 1' => '', 'Eligibility License No 1' => '',
    'Skill/Hobby 1' => 'Microsoft Office', 'Recognition 1 Title' => '',
    'Membership 1 Organization' => '',
    'Real Property 1 Description' => '', 'Real Property 1 Market Value' => '',
    'Personal Property 1 Description' => 'Laptop', 'Personal Property 1 Cost' => '45000',
    'Liability 1 Nature' => '', 'Liability 1 Outstanding Balance' => '',
    'Related to Company (Yes/No)' => 'No', 'Related Details' => '',
    'Admin Offense (Yes/No)' => 'No', 'Admin Offense Details' => '',
    'Criminal Charge (Yes/No)' => 'No', 'Criminal Charge Details' => '',
    'PWD Status (Yes/No)' => 'No', 'Solo Parent Status (Yes/No)' => 'No',
    'Reference 1 Name' => 'Elena Santos', 'Reference 1 Address' => 'Angeles City, Pampanga',
    'Reference 1 Contact Number' => '09201234567',
    'Reference 2 Name' => 'Jose Bautista', 'Reference 2 Address' => 'Mabalacat, Pampanga',
    'Reference 2 Contact Number' => '09202345678',
    'Reference 3 Name' => 'Carla Mendoza', 'Reference 3 Address' => 'San Fernando, Pampanga',
    'Reference 3 Contact Number' => '09203456789',
    'Previous Company 1' => '', 'Previous Position 1' => '', 'Previous Monthly Salary 1' => '',
    'Previous Employment Period 1' => '', 'Previous Reason for Leaving 1' => '',
    'Training Title 1' => '', 'Training Conducted By 1' => '', 'Training Hours 1' => '',
    'Branch' => 'Raquel Pawnshop Main Office', 'Employment Type' => 'Full-time',
    'Emergency Contact Name' => 'Roberto Reyes', 'Emergency Contact Relationship' => 'Father',
    'Emergency Contact Number' => '09207654321',
];

$samples = [

    // ── Regular ──────────────────────────────────────────────────────────────
    // Hire Date = regularization date. Contract dates not needed.
    array_merge($shared, [
        'First Name' => 'Maria', 'Last Name' => 'Reyes', 'Middle Name' => 'Santos',
        'Birthday' => '03/15/1992', 'Gender' => 'Female', 'Civil Status' => 'Single',
        'Blood Type' => 'B+',
        'SSS No' => '01-2345678-9', 'PhilHealth No' => '12-345678901-2',
        'Pag-IBIG No' => '1234-5678-9012', 'TIN No' => '123-456-789-000',
        'Mobile No' => '09171000001', 'Email' => 'maria.reyes@example.com',
        'Company ID' => 'EMP-2026-0001', 'Hire Date' => '01/15/2020',
        'Job Title' => 'HR Specialist', 'Rank' => 'R&F', 'Department' => 'Human Resources',
        'Employment Status' => 'Regular',
        'Contract Start Date' => '', 'Contract End Date' => '',
        'Previous Company 1' => 'Santos & Co. Enterprises', 'Previous Position 1' => 'HR Assistant',
        'Previous Monthly Salary 1' => '18000', 'Previous Employment Period 1' => '2016-2019',
        'Previous Reason for Leaving 1' => 'Career growth',
    ]),

    // ── OJT ─────────────────────────────────────────────────────────────────
    // Hire Date = start of OJT. Contract Start Date = same as Hire Date.
    // Contract End Date = expected last day of OJT.
    array_merge($shared, [
        'First Name' => 'Carlo', 'Last Name' => 'Santos', 'Middle Name' => 'Dela Cruz',
        'Birthday' => '06/22/2003', 'Gender' => 'Male', 'Civil Status' => 'Single',
        'Blood Type' => 'O+',
        'SSS No' => '01-3456789-0', 'PhilHealth No' => '12-456789012-3',
        'Pag-IBIG No' => '2345-6789-0123', 'TIN No' => '',
        'Mobile No' => '09171000002', 'Email' => 'carlo.santos@example.com',
        'Company ID' => 'EMP-2026-0002', 'Hire Date' => '06/01/2026',
        'Job Title' => 'OJT - Business Administration', 'Rank' => 'R&F',
        'Department' => 'Human Resources',
        'Employment Status' => 'OJT',
        'Contract Start Date' => '06/01/2026', 'Contract End Date' => '08/31/2026',
        'Training Title 1' => 'On-the-Job Training Orientation',
        'Training Conducted By 1' => 'Raquel Pawnshop HRD', 'Training Hours 1' => '400',
    ]),

    // ── Trainee ──────────────────────────────────────────────────────────────
    // Hire Date = first day of training. Contract Start Date = same as Hire Date.
    // Contract End Date = expected last day of training period.
    array_merge($shared, [
        'First Name' => 'Ana', 'Last Name' => 'Bautista', 'Middle Name' => 'Lim',
        'Birthday' => '09/10/2001', 'Gender' => 'Female', 'Civil Status' => 'Single',
        'Blood Type' => 'A+',
        'SSS No' => '01-4567890-1', 'PhilHealth No' => '12-567890123-4',
        'Pag-IBIG No' => '3456-7890-1234', 'TIN No' => '234-567-890-000',
        'Mobile No' => '09171000003', 'Email' => 'ana.bautista@example.com',
        'Company ID' => 'EMP-2026-0003', 'Hire Date' => '05/01/2026',
        'Job Title' => 'Operations Trainee', 'Rank' => 'R&F',
        'Department' => 'Operations',
        'Employment Status' => 'Trainee',
        'Contract Start Date' => '05/01/2026', 'Contract End Date' => '10/31/2026',
        'Training Title 1' => 'Operations Fundamentals Training',
        'Training Conducted By 1' => 'Operations Department', 'Training Hours 1' => '240',
    ]),

    // ── Probationary ─────────────────────────────────────────────────────────
    // Hire Date = first day of work. Contract Start Date = start of probation period.
    // Contract End Date = scheduled regularization / end of probation.
    array_merge($shared, [
        'First Name' => 'Ramon', 'Last Name' => 'Cruz', 'Middle Name' => 'Torres',
        'Birthday' => '11/30/1995', 'Gender' => 'Male', 'Civil Status' => 'Married',
        'Blood Type' => 'AB+',
        'SSS No' => '01-5678901-2', 'PhilHealth No' => '12-678901234-5',
        'Pag-IBIG No' => '4567-8901-2345', 'TIN No' => '345-678-901-000',
        'Mobile No' => '09171000004', 'Email' => 'ramon.cruz@example.com',
        'Company ID' => 'EMP-2026-0004', 'Hire Date' => '03/01/2026',
        'Job Title' => 'Accounting Clerk', 'Rank' => 'R&F',
        'Department' => 'Finance',
        'Employment Status' => 'Probationary',
        'Contract Start Date' => '03/01/2026', 'Contract End Date' => '08/31/2026',
        'Previous Company 1' => 'XYZ Lending Corp.', 'Previous Position 1' => 'Accounting Assistant',
        'Previous Monthly Salary 1' => '18000', 'Previous Employment Period 1' => '2023-2025',
        'Previous Reason for Leaving 1' => 'Career growth',
    ]),

    // ── Project Based ─────────────────────────────────────────────────────────
    // Hire Date = project engagement start. Contract Start Date = project start.
    // Contract End Date = agreed project completion date.
    array_merge($shared, [
        'First Name' => 'Liza', 'Last Name' => 'Mendoza', 'Middle Name' => 'Ocampo',
        'Birthday' => '07/04/1990', 'Gender' => 'Female', 'Civil Status' => 'Single',
        'Blood Type' => 'O-',
        'SSS No' => '01-6789012-3', 'PhilHealth No' => '12-789012345-6',
        'Pag-IBIG No' => '5678-9012-3456', 'TIN No' => '456-789-012-000',
        'Mobile No' => '09171000005', 'Email' => 'liza.mendoza@example.com',
        'Company ID' => 'EMP-2026-0005', 'Hire Date' => '07/01/2026',
        'Job Title' => 'Project Coordinator', 'Rank' => 'Supervisor',
        'Department' => 'Acquired Properties',
        'Employment Status' => 'Project Based',
        'Contract Start Date' => '07/01/2026', 'Contract End Date' => '12/31/2026',
        'Previous Company 1' => 'ABC Realty Inc.', 'Previous Position 1' => 'Property Coordinator',
        'Previous Monthly Salary 1' => '28000', 'Previous Employment Period 1' => '2020-2025',
        'Previous Reason for Leaving 1' => 'Contract ended',
    ]),

];

foreach ($samples as $sample) {
    fputcsv($output, array_map(fn($h) => $sample[$h] ?? '', $headers));
}

fclose($output);
exit();
