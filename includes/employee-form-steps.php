<?php
$e = $emp ?? [];
$v = function ($key, $default = '') use ($e) {
    return htmlspecialchars($e[$key] ?? $default, ENT_QUOTES, 'UTF-8');
};
$sel = function ($key, $val) use ($e) {
    return (($e[$key] ?? '') === $val) ? 'selected' : '';
};
$chk = function ($key) use ($e) {
    return !empty($e[$key]) ? 'checked' : '';
};
$isEdit = !empty($e);
$totalSteps = 12;
$currentStep = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$rankCategories = $rankCategories ?? [
    ['rank_category_id' => 1, 'rank_name' => 'Executives'],
    ['rank_category_id' => 2, 'rank_name' => 'Management Team'],
    ['rank_category_id' => 3, 'rank_name' => 'Manager'],
    ['rank_category_id' => 5, 'rank_name' => 'R&F'],
    ['rank_category_id' => 4, 'rank_name' => 'Supervisor'],
];
?>

<input type="hidden" name="current_step" id="currentStepInput" value="<?php echo $currentStep; ?>">

<!-- ====== STEP 1: Personal Information ====== -->
<div class="step-content" id="step1">
    <div class="form-section-title"><i class="fas fa-id-card"></i> Basic Identity</div>
    <div class="row">
        <div class="col-md-12 mb-3">
            <label class="form-label">Profile Picture <?php echo $isEdit ? '' : '(Optional)'; ?></label>
            <div class="d-flex align-items-start gap-4">
                <div id="profilePreviewContainer" class="text-center"
                    style="<?php echo !empty($e['profile_picture']) ? '' : 'display:none;'; ?>">
                    <img id="profilePreview"
                        src="<?php echo !empty($e['profile_picture']) ? getEmployeeAvatar($e['profile_picture']) : ''; ?>"
                        class="rounded-circle img-thumbnail shadow-sm"
                        style="width:100px;height:100px;object-fit:cover;">
                    <div class="small text-muted mt-1">Current/New</div>
                </div>
                <div class="flex-grow-1">
                    <?php if ($_SESSION['role'] === 'Admin' || $_SESSION['role'] === 'HR Manager' || $_SESSION['role'] === 'HR Supervisor'): ?>
                        <input type="file" class="form-control" name="profile_picture" accept="image/*"
                            onchange="previewImage(this)">
                        <small class="text-muted d-block mt-1">Recommended: Square image, max 2MB (JPG, PNG)</small>
                        <?php if (!empty($e['profile_picture'])): ?>
                            <small class="text-primary d-block mt-1 fw-bold"><i class="fas fa-check-circle me-1"></i>Filename:
                                <?php echo $v('profile_picture'); ?></small>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-light py-2 px-3 border border-dashed text-muted mb-0"
                            style="border-radius: 8px; font-size: 0.85rem;">
                            <i class="fas fa-lock me-2"></i>Avatar management is reserved for Administrators.
                        </div>
                        <?php if (!empty($e['profile_picture'])): ?>
                            <small class="text-muted d-block mt-1"><i class="fas fa-info-circle me-1"></i>This photo was
                                verified and added by the Admin.</small>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-3 mb-3">
            <label class="form-label">Surname <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="last_name" value="<?php echo $v('last_name'); ?>" required>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">First Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="first_name" value="<?php echo $v('first_name'); ?>" required>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Middle Name</label>
            <input type="text" class="form-control" name="middle_name" value="<?php echo $v('middle_name'); ?>">
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Name Extension</label>
            <select class="form-select" name="name_extension">
                <option value="">N/A</option>
                <?php foreach (['JR', 'SR', 'II', 'III', 'IV', 'V'] as $ext): ?>
                    <option value="<?php echo $ext; ?>" <?php echo $sel('name_extension', $ext); ?>><?php echo $ext; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-section-title mt-3"><i class="fas fa-birthday-cake"></i> Birth & Status</div>
    <div class="row">
        <div class="col-md-3 mb-3">
            <label class="form-label">Date of Birth</label>
            <input type="date" class="form-control" name="date_of_birth" value="<?php echo $v('date_of_birth'); ?>">
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Place of Birth</label>
            <input type="text" class="form-control" name="place_of_birth" value="<?php echo $v('place_of_birth'); ?>"
                placeholder="City/Province">
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Gender</label>
            <select class="form-select" name="gender">
                <option value="">Select</option>
                <option value="Male" <?php echo $sel('gender', 'Male'); ?>>Male</option>
                <option value="Female" <?php echo $sel('gender', 'Female'); ?>>Female</option>
                <option value="Other" <?php echo $sel('gender', 'Other'); ?>>Other</option>
                <option value="Preferred not to say" <?php echo $sel('gender', 'Preferred not to say'); ?>>Preferred not to say</option>
            </select>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Civil Status</label>
            <select class="form-select" name="civil_status">
                <option value="">Select</option>
                <?php foreach (['Single', 'Married', 'Widowed', 'Separated', 'Divorced'] as $cs): ?>
                    <option value="<?php echo $cs; ?>" <?php echo $sel('civil_status', $cs); ?>><?php echo $cs; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-section-title mt-3"><i class="fas fa-ruler-vertical"></i> Physical & Citizenship</div>
    <div class="row">
        <div class="col-md-2 mb-3">
            <label class="form-label">Height (m)</label>
            <input type="number" step="0.01" class="form-control" name="height_m" value="<?php echo $v('height_m'); ?>"
                placeholder="1.65">
        </div>
        <div class="col-md-2 mb-3">
            <label class="form-label">Weight (kg)</label>
            <input type="number" step="0.1" class="form-control" name="weight_kg" value="<?php echo $v('weight_kg'); ?>"
                placeholder="60">
        </div>
        <div class="col-md-2 mb-3">
            <label class="form-label">Blood Type</label>
            <select class="form-select" name="blood_type">
                <option value="">Select</option>
                <?php foreach (['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'] as $bt): ?>
                    <option value="<?php echo $bt; ?>" <?php echo $sel('blood_type', $bt); ?>><?php echo $bt; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Citizenship</label>
            <input type="text" class="form-control" name="citizenship"
                value="<?php echo $v('citizenship', 'Filipino'); ?>">
        </div>
    </div>

    <div class="form-section-title mt-3"><i class="fas fa-id-badge"></i> Government IDs</div>
    <div class="row">
        <div class="col-md-3 mb-3">
            <label class="form-label">SSS No.</label>
            <input type="text" class="form-control" name="sss_number" value="<?php echo $v('sss_number'); ?>" 
                placeholder="00-0000000-0" pattern="\d{2}-\d{7}-\d{1}" title="Format: 00-0000000-0 (10 digits)" inputmode="numeric">
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">PhilHealth No.</label>
            <input type="text" class="form-control" name="philhealth_number" value="<?php echo $v('philhealth_number'); ?>" 
                placeholder="00-000000000-0" pattern="\d{2}-\d{9}-\d{1}" title="Format: 00-000000000-0 (12 digits)" inputmode="numeric">
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Pag-IBIG No.</label>
            <input type="text" class="form-control" name="pagibig_number" value="<?php echo $v('pagibig_number'); ?>" 
                placeholder="0000-0000-0000" pattern="\d{4}-\d{4}-\d{4}" title="Format: 0000-0000-0000 (12 digits)" inputmode="numeric">
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">TIN No.</label>
            <input type="text" class="form-control" name="tin_number" value="<?php echo $v('tin_number'); ?>" 
                placeholder="000-000-000-000" pattern="\d{3}-\d{3}-\d{3}-\d{3}" title="Format: 000-000-000-000 (9 or 12 digits)" inputmode="numeric">
        </div>
    </div>

    <div class="form-section-title mt-3"><i class="fas fa-map-marker-alt"></i> Residential Address</div>
    <div class="row">
        <div class="col-md-3 mb-3">
            <label class="form-label">House/Block/Lot No.</label>
            <input type="text" class="form-control" name="res_house_no" id="res_house_no" value="<?php echo $v('res_house_no'); ?>">
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Street</label>
            <input type="text" class="form-control" name="res_street" id="res_street" value="<?php echo $v('res_street'); ?>">
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Subdivision/Village</label>
            <input type="text" class="form-control" name="res_subdivision" id="res_subdivision" value="<?php echo $v('res_subdivision'); ?>">
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Region</label>
            <select class="form-select ph-region-select" name="res_region" id="res_region" data-saved-value="<?php echo $v('res_region'); ?>">
                <option value="">Select Region...</option>
            </select>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Province</label>
            <select class="form-select ph-province-select" name="res_province" id="res_province" data-saved-value="<?php echo $v('res_province'); ?>" disabled>
                <option value="">Select Province...</option>
            </select>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">City/Municipality</label>
            <select class="form-select ph-city-select" name="res_city" id="res_city" data-saved-value="<?php echo $v('res_city'); ?>" disabled>
                <option value="">Select City/Municipality...</option>
            </select>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Barangay</label>
            <select class="form-select ph-barangay-select" name="res_barangay" id="res_barangay" data-saved-value="<?php echo $v('res_barangay'); ?>" disabled>
                <option value="">Select Barangay...</option>
            </select>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Zip Code</label>
            <input type="text" class="form-control" name="res_zip_code" id="res_zip_code" value="<?php echo $v('res_zip_code'); ?>" placeholder="Auto-filled / Editable">
        </div>
    </div>

    <div class="form-section-title mt-3">
        <i class="fas fa-home"></i> Permanent Address
        <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="copyResAddress()">
            <i class="fas fa-copy me-1"></i>Same as Residential
        </button>
    </div>
    <div class="row">
        <div class="col-md-3 mb-3">
            <label class="form-label">House/Block/Lot No.</label>
            <input type="text" class="form-control" name="perm_house_no" id="perm_house_no"
                value="<?php echo $v('perm_house_no'); ?>">
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Street</label>
            <input type="text" class="form-control" name="perm_street" id="perm_street"
                value="<?php echo $v('perm_street'); ?>">
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Subdivision/Village</label>
            <input type="text" class="form-control" name="perm_subdivision" id="perm_subdivision"
                value="<?php echo $v('perm_subdivision'); ?>">
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Region</label>
            <select class="form-select ph-region-select" name="perm_region" id="perm_region" data-saved-value="<?php echo $v('perm_region'); ?>">
                <option value="">Select Region...</option>
            </select>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Province</label>
            <select class="form-select ph-province-select" name="perm_province" id="perm_province" data-saved-value="<?php echo $v('perm_province'); ?>" disabled>
                <option value="">Select Province...</option>
            </select>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">City/Municipality</label>
            <select class="form-select ph-city-select" name="perm_city" id="perm_city" data-saved-value="<?php echo $v('perm_city'); ?>" disabled>
                <option value="">Select City/Municipality...</option>
            </select>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Barangay</label>
            <select class="form-select ph-barangay-select" name="perm_barangay" id="perm_barangay" data-saved-value="<?php echo $v('perm_barangay'); ?>" disabled>
                <option value="">Select Barangay...</option>
            </select>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Zip Code</label>
            <input type="text" class="form-control" name="perm_zip_code" id="perm_zip_code"
                value="<?php echo $v('perm_zip_code'); ?>" placeholder="Auto-filled / Editable">
        </div>
    </div>

    <div class="form-section-title mt-3"><i class="fas fa-phone-alt"></i> Contact Information</div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">Telephone No. <span class="text-muted small">(Optional)</span></label>
            <input type="text" class="form-control" name="telephone_number" value="<?php echo $v('telephone_number'); ?>" 
                placeholder="(042) 000-0000" title="Format: (000) 000-0000" inputmode="numeric">
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Mobile No.</label>
            <input type="text" class="form-control" name="contact_number" id="contact_number" value="<?php echo $v('contact_number'); ?>" 
                placeholder="09171234567" pattern="09\d{9}" title="Format: 11 digits starting with 09 (e.g. 09171234567)" inputmode="numeric" maxlength="11">
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" class="form-control" name="email" value="<?php echo $v('email'); ?>">
        </div>
    </div>

</div>


<!-- ====== STEP 2: Family Background ====== -->
<div class="step-content" id="step2" style="display:none;">
    <div class="form-section-title"><i class="fas fa-heart"></i> Spouse Information</div>
    <div class="row">
        <div class="col-md-3 mb-3">
            <label class="form-label">Surname</label>
            <input type="text" class="form-control" name="spouse_surname" value="<?php echo $v('spouse_surname'); ?>">
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">First Name</label>
            <input type="text" class="form-control" name="spouse_first_name"
                value="<?php echo $v('spouse_first_name'); ?>">
        </div>
        <div class="col-md-2 mb-3">
            <label class="form-label">Middle Name</label>
            <input type="text" class="form-control" name="spouse_middle_name"
                value="<?php echo $v('spouse_middle_name'); ?>">
        </div>
        <div class="col-md-2 mb-3">
            <label class="form-label">Ext.</label>
            <input type="text" class="form-control" name="spouse_name_ext" value="<?php echo $v('spouse_name_ext'); ?>"
                placeholder="JR, SR">
        </div>
        <div class="col-md-2 mb-3">
            <label class="form-label">Occupation</label>
            <input type="text" class="form-control" name="spouse_occupation"
                value="<?php echo $v('spouse_occupation'); ?>">
        </div>
    </div>

    <div class="form-section-title mt-3"><i class="fas fa-male"></i> Father's Information</div>
    <div class="row">
        <div class="col-md-3 mb-3">
            <label class="form-label">Surname</label>
            <input type="text" class="form-control" name="father_surname" value="<?php echo $v('father_surname'); ?>">
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">First Name</label>
            <input type="text" class="form-control" name="father_first_name"
                value="<?php echo $v('father_first_name'); ?>">
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Middle Name</label>
            <input type="text" class="form-control" name="father_middle_name"
                value="<?php echo $v('father_middle_name'); ?>">
        </div>
        <div class="col-md-1 mb-3">
            <label class="form-label">Ext.</label>
            <input type="text" class="form-control" name="father_name_ext" value="<?php echo $v('father_name_ext'); ?>">
        </div>
        <div class="col-md-2 mb-3">
            <label class="form-label">Occupation</label>
            <input type="text" class="form-control" name="father_occupation"
                value="<?php echo $v('father_occupation'); ?>">
        </div>
    </div>

    <div class="form-section-title mt-3"><i class="fas fa-female"></i> Mother's Maiden Name</div>
    <div class="row">
        <div class="col-md-3 mb-3">
            <label class="form-label">Maiden Surname</label>
            <input type="text" class="form-control" name="mother_maiden_surname"
                value="<?php echo $v('mother_maiden_surname'); ?>">
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">First Name</label>
            <input type="text" class="form-control" name="mother_first_name"
                value="<?php echo $v('mother_first_name'); ?>">
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Middle Name</label>
            <input type="text" class="form-control" name="mother_middle_name"
                value="<?php echo $v('mother_middle_name'); ?>">
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Occupation</label>
            <input type="text" class="form-control" name="mother_occupation"
                value="<?php echo $v('mother_occupation'); ?>">
        </div>
    </div>

    <div class="form-section-title mt-3"><i class="fas fa-child"></i> Children</div>
    <div id="childrenContainer" class="repeater-accordion">
        <?php if ($isEdit && !empty($employeeChildren)): ?>
            <?php foreach ($employeeChildren as $i => $child): ?>
                <div class="repeater-row">
                    <button type="button" class="btn-remove-row" onclick="this.closest('.repeater-row').remove()"><i
                            class="fas fa-times"></i></button>
                    <div class="row">
                        <div class="col-md-3 mb-2"><input type="text" class="form-control form-control-sm"
                                name="child_surname[]" value="<?php echo e($child['surname']); ?>" placeholder="Surname"></div>
                        <div class="col-md-3 mb-2"><input type="text" class="form-control form-control-sm"
                                name="child_first_name[]" value="<?php echo e($child['first_name']); ?>"
                                placeholder="First Name"></div>
                        <div class="col-md-3 mb-2"><input type="text" class="form-control form-control-sm"
                                name="child_middle_name[]" value="<?php echo e($child['middle_name']); ?>"
                                placeholder="Middle Name"></div>
                        <div class="col-md-3 mb-2"><input type="date" class="form-control form-control-sm" name="child_dob[]"
                                value="<?php echo e($child['date_of_birth']); ?>"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <button type="button" class="btn-add-row mb-3" onclick="addRepeaterRow('childrenContainer','child')"><i
            class="fas fa-plus me-1"></i> Add Child</button>

    <div class="form-section-title mt-3"><i class="fas fa-users"></i> Siblings</div>
    <div id="siblingsContainer" class="repeater-accordion">
        <?php if ($isEdit && !empty($employeeSiblings)): ?>
            <?php foreach ($employeeSiblings as $i => $sib): ?>
                <div class="repeater-row">
                    <button type="button" class="btn-remove-row" onclick="this.closest('.repeater-row').remove()"><i
                            class="fas fa-times"></i></button>
                    <div class="row">
                        <div class="col-md-3 mb-2"><input type="text" class="form-control form-control-sm"
                                name="sibling_surname[]" value="<?php echo e($sib['surname']); ?>" placeholder="Surname"></div>
                        <div class="col-md-3 mb-2"><input type="text" class="form-control form-control-sm"
                                name="sibling_first_name[]" value="<?php echo e($sib['first_name']); ?>"
                                placeholder="First Name"></div>
                        <div class="col-md-3 mb-2"><input type="text" class="form-control form-control-sm"
                                name="sibling_middle_name[]" value="<?php echo e($sib['middle_name']); ?>"
                                placeholder="Middle Name"></div>
                        <div class="col-md-3 mb-2"><input type="date" class="form-control form-control-sm" name="sibling_dob[]"
                                value="<?php echo e($sib['date_of_birth']); ?>"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <button type="button" class="btn-add-row mb-3" onclick="addRepeaterRow('siblingsContainer','sibling')"><i
            class="fas fa-plus me-1"></i> Add Sibling</button>

</div>



<!-- ====== STEP 3: Educational Background ====== -->
<div class="step-content" id="step3" style="display:none;">
    <div class="form-section-title"><i class="fas fa-graduation-cap"></i> Educational Background</div>
    
    <?php
    $eduOrder = ['Elementary' => 1, 'Secondary' => 2, 'Senior High School' => 3, 'Vocational' => 4, 'College' => 5, 'Graduate Studies' => 6];
    $eduLabels = [
        'Elementary' => 'Elementary',
        'Secondary' => 'Secondary / Junior High',
        'Senior High School' => 'Senior High School',
        'Vocational' => 'Vocational / Trade Course',
        'College' => 'College',
        'Graduate Studies' => 'Graduate Studies'
    ];
    $eduIcons = [
        'Elementary' => 'fas fa-school',
        'Secondary' => 'fas fa-book',
        'Senior High School' => 'fas fa-book-open',
        'Vocational' => 'fas fa-tools',
        'College' => 'fas fa-graduation-cap',
        'Graduate Studies' => 'fas fa-user-graduate'
    ];
    $eduCss = [
        'Elementary' => 'level-elementary',
        'Secondary' => 'level-secondary',
        'Senior High School' => 'level-shs',
        'Vocational' => 'level-vocational',
        'College' => 'level-college',
        'Graduate Studies' => 'level-graduate'
    ];

    if ($isEdit && !empty($employeeEducation)) {
        usort($employeeEducation, function($a, $b) use ($eduOrder) {
            return ($eduOrder[$a['education_level']] ?? 99) <=> ($eduOrder[$b['education_level']] ?? 99);
        });
    }
    ?>

    <div id="educationContainer">
        <?php if ($isEdit && !empty($employeeEducation)): ?>
            <?php foreach ($employeeEducation as $edu): 
                $lvl = $edu['education_level'];
                $label = $eduLabels[$lvl] ?? $lvl;
                $icon = $eduIcons[$lvl] ?? 'fas fa-graduation-cap';
                $css = $eduCss[$lvl] ?? 'level-college';
            ?>
                <div class="pds-card">
                    <div class="pds-card-header">
                        <div class="pds-card-title">
                            <div class="pds-card-icon"><i class="<?php echo $icon; ?>"></i></div>
                            <div>
                                <div class="pds-card-label"><?php echo $label; ?></div>
                                <span class="edu-level-badge <?php echo $css; ?>"><i class="<?php echo $icon; ?>"></i> <?php echo $label; ?></span>
                            </div>
                        </div>
                        <div class="pds-card-actions">
                            <button type="button" class="pds-card-btn btn-move-up" onclick="moveCard(this,'up')" title="Move Up"><i class="fas fa-chevron-up"></i></button>
                            <button type="button" class="pds-card-btn btn-move-down" onclick="moveCard(this,'down')" title="Move Down"><i class="fas fa-chevron-down"></i></button>
                            <button type="button" class="pds-card-btn btn-delete" onclick="this.closest('.pds-card').remove(); document.querySelectorAll('#educationContainer select[name=\'edu_level[]\']').forEach(s=>{const c=s.closest('.pds-card');if(c)updateEduCardBadge(c)})" title="Remove"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="pds-form-grid">
                        <div class="full-width">
                            <label class="pds-field-label">Education Level</label>
                            <select class="form-select form-select-sm" name="edu_level[]" onchange="updateEduCardBadge(this.closest('.pds-card'))">
                                <?php foreach ($eduLabels as $val => $lbl): ?>
                                    <option value="<?php echo $val; ?>" <?php echo $lvl === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="edu-duplicate-warning"><i class="fas fa-exclamation-triangle"></i><span>This education level already exists. Are you sure you want to add another record for the same level?</span></div>
                        </div>
                        <div>
                            <label class="pds-field-label">School Name</label>
                            <input type="text" class="form-control form-control-sm" name="edu_school[]" value="<?php echo e($edu['school_name']); ?>" placeholder="School Name">
                        </div>
                        <div>
                            <label class="pds-field-label">Degree / Course / Strand</label>
                            <input type="text" class="form-control form-control-sm" name="edu_degree[]" value="<?php echo e($edu['degree_course']); ?>" placeholder="Degree/Course">
                        </div>
                        <div>
                            <label class="pds-field-label">Year From</label>
                            <input type="number" class="form-control form-control-sm" name="edu_from[]" value="<?php echo !empty($edu['period_from']) ? date('Y', strtotime($edu['period_from'])) : ''; ?>" min="1900" max="2099" placeholder="Year">
                        </div>
                        <div>
                            <label class="pds-field-label">Year To</label>
                            <input type="number" class="form-control form-control-sm" name="edu_to[]" value="<?php echo !empty($edu['period_to']) ? date('Y', strtotime($edu['period_to'])) : ''; ?>" min="1900" max="2099" placeholder="Year">
                        </div>
                        <div>
                            <label class="pds-field-label">Highest Level / Units Earned</label>
                            <input type="text" class="form-control form-control-sm" name="edu_units[]" value="<?php echo e($edu['highest_level_units']); ?>" placeholder="Highest Level/Units">
                        </div>
                        <div>
                            <label class="pds-field-label">Year Graduated</label>
                            <input type="text" class="form-control form-control-sm" name="edu_year_grad[]" value="<?php echo e($edu['year_graduated']); ?>" placeholder="Year Grad">
                        </div>
                        <div class="full-width">
                            <label class="pds-field-label">Honors / Awards / Distinctions Received</label>
                            <textarea class="form-control form-control-sm" name="edu_honors[]" rows="2" placeholder="List honors received (e.g., Cum Laude, Dean's List, etc.)"><?php echo e($edu['honors_received']); ?></textarea>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <button type="button" class="btn-add-pds-section mb-3" onclick="addEducationRow()"><i class="fas fa-plus me-1"></i> Add Education Entry</button>
</div>



<!-- ====== STEP 4: Work Experience ====== -->
<div class="step-content" id="step4" style="display:none;">
    <div class="form-section-title"><i class="fas fa-briefcase"></i> Work Experience</div>
    <div class="work-timeline">
        <div id="workContainer">
            <?php if ($isEdit && !empty($employeeWork)): ?>
                <?php foreach ($employeeWork as $w): ?>
                    <div class="pds-card">
                        <div class="pds-card-header">
                            <div class="pds-card-title">
                                <div class="pds-card-icon"><i class="fas fa-briefcase"></i></div>
                                <div>
                                    <div class="pds-card-label">Work Experience</div>
                                    <div class="pds-card-subtitle">Fill in the details below</div>
                                </div>
                            </div>
                            <div class="pds-card-actions">
                                <button type="button" class="pds-card-btn btn-delete" onclick="this.closest('.pds-card').remove()" title="Remove"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                        <div class="pds-form-grid">
                            <div>
                                <label class="pds-field-label">Date From (Year)</label>
                                <input type="number" class="form-control form-control-sm" name="work_from[]"
                                    value="<?php echo !empty($w['date_from']) ? date('Y', strtotime($w['date_from'])) : ''; ?>" 
                                    min="1900" max="2099" placeholder="e.g. 2018">
                            </div>
                            <div>
                                <label class="pds-field-label">Date To (Year or Present)</label>
                                <input type="text" class="form-control form-control-sm" name="work_to[]"
                                    value="<?php echo !empty($w['date_to']) && $w['date_to'] !== '0000-00-00' ? (strtotime($w['date_to']) ? date('Y', strtotime($w['date_to'])) : $w['date_to']) : 'Present'; ?>" 
                                    placeholder="e.g. 2022 or Present">
                            </div>
                            <div>
                                <label class="pds-field-label">Job Title / Position</label>
                                <input type="text" class="form-control form-control-sm" name="work_title[]"
                                    value="<?php echo e($w['job_title']); ?>" placeholder="e.g. Software Engineer">
                            </div>
                            <div>
                                <label class="pds-field-label">Company / Employer Name</label>
                                <input type="text" class="form-control form-control-sm" name="work_company[]"
                                    value="<?php echo e($w['company_name']); ?>" placeholder="e.g. Raquel Pawnshop">
                            </div>
                            <div>
                                <label class="pds-field-label">Monthly Salary (₱)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm"
                                    name="work_salary[]" value="<?php echo e($w['monthly_salary']); ?>" placeholder="e.g. 18000.00">
                            </div>
                            <div>
                                <label class="pds-field-label">Appointment / Employment Status</label>
                                <input type="text" class="form-control form-control-sm" name="work_status[]"
                                    value="<?php echo e($w['appointment_status']); ?>" placeholder="e.g. Regular, Contractual">
                            </div>
                            <div class="full-width">
                                <label class="pds-field-label">Reason for Leaving</label>
                                <input type="text" class="form-control form-control-sm" name="work_reason[]"
                                    value="<?php echo e($w['reason_for_leaving']); ?>" placeholder="e.g. Career growth, Resigned">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <button type="button" class="btn-add-pds-section mb-3" onclick="addWorkRow()"><i class="fas fa-plus me-1"></i> Add Work Entry</button>
</div>


<!-- ====== STEP 5: Training Programs ====== -->
<div class="step-content" id="step5" style="display:none;">
    <div class="form-section-title"><i class="fas fa-chalkboard-teacher"></i> Training Programs Attended</div>
    <div id="trainingContainer">
        <?php if ($isEdit && !empty($employeeTrainings)): ?>
            <?php foreach ($employeeTrainings as $t): ?>
                <div class="pds-card training-card">
                    <div class="pds-card-header">
                        <div class="pds-card-title">
                            <div class="pds-card-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                            <div>
                                <div class="pds-card-label">Training Program</div>
                                <div class="pds-card-subtitle">Seminar, workshop, or training attended</div>
                            </div>
                        </div>
                        <div class="pds-card-actions">
                            <button type="button" class="pds-card-btn btn-delete" onclick="this.closest('.pds-card').remove()" title="Remove"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="pds-form-grid cols-3">
                        <div class="full-width">
                            <label class="pds-field-label">Training Title / Program Name</label>
                            <input type="text" class="form-control form-control-sm" name="training_title[]" value="<?php echo e($t['training_title']); ?>" placeholder="e.g. Basic Fire Safety Training">
                        </div>
                        <div>
                            <label class="pds-field-label">Date From</label>
                            <input type="number" class="form-control form-control-sm" name="training_from[]" value="<?php echo !empty($t['date_from']) ? date('Y', strtotime($t['date_from'])) : ''; ?>" min="1900" max="2099" placeholder="Year">
                        </div>
                        <div>
                            <label class="pds-field-label">Date To</label>
                            <input type="number" class="form-control form-control-sm" name="training_to[]" value="<?php echo !empty($t['date_to']) ? date('Y', strtotime($t['date_to'])) : ''; ?>" min="1900" max="2099" placeholder="Year">
                        </div>
                        <div>
                            <label class="pds-field-label">No. of Hours</label>
                            <input type="number" class="form-control form-control-sm" name="training_hours[]" value="<?php echo e($t['no_of_hours']); ?>" placeholder="e.g. 8">
                        </div>
                        <div>
                            <label class="pds-field-label">Type (L/D/ET/Other)</label>
                            <input type="text" class="form-control form-control-sm" name="training_type[]" value="<?php echo e($t['training_type']); ?>" placeholder="e.g. Leadership, Technical">
                        </div>
                        <div class="full-width">
                            <label class="pds-field-label">Conducted / Sponsored By</label>
                            <input type="text" class="form-control form-control-sm" name="training_conducted[]" value="<?php echo e($t['conducted_by']); ?>" placeholder="e.g. DOLE, Company HR Dept.">
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <button type="button" class="btn-add-pds-section mb-3" onclick="addTrainingRow()"><i class="fas fa-plus me-1"></i> Add Training</button>
</div>


<!-- ====== STEP 6: Voluntary Work ====== -->
<div class="step-content" id="step6" style="display:none;">
    <div class="form-section-title"><i class="fas fa-hands-helping"></i> Voluntary Work / Civic Involvement</div>
    <div id="voluntaryContainer">
        <?php if ($isEdit && !empty($employeeVoluntary)): ?>
            <?php foreach ($employeeVoluntary as $vol): ?>
                <div class="pds-card voluntary-card">
                    <div class="pds-card-header">
                        <div class="pds-card-title">
                            <div class="pds-card-icon"><i class="fas fa-hands-helping"></i></div>
                            <div>
                                <div class="pds-card-label">Voluntary / Civic Work</div>
                                <div class="pds-card-subtitle">Organization involvement or community service</div>
                            </div>
                        </div>
                        <div class="pds-card-actions">
                            <button type="button" class="pds-card-btn btn-delete" onclick="this.closest('.pds-card').remove()" title="Remove"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="pds-form-grid">
                        <div>
                            <label class="pds-field-label">Organization Name</label>
                            <input type="text" class="form-control form-control-sm" name="vol_org[]" value="<?php echo e($vol['organization_name']); ?>" placeholder="e.g. Red Cross, Barangay Council">
                        </div>
                        <div>
                            <label class="pds-field-label">Organization Address</label>
                            <input type="text" class="form-control form-control-sm" name="vol_address[]" value="<?php echo e($vol['organization_address']); ?>" placeholder="City/Province">
                        </div>
                        <div>
                            <label class="pds-field-label">Date From</label>
                            <input type="number" class="form-control form-control-sm" name="vol_from[]" value="<?php echo !empty($vol['date_from']) ? date('Y', strtotime($vol['date_from'])) : ''; ?>" min="1900" max="2099" placeholder="Year">
                        </div>
                        <div>
                            <label class="pds-field-label">Date To</label>
                            <input type="number" class="form-control form-control-sm" name="vol_to[]" value="<?php echo !empty($vol['date_to']) ? date('Y', strtotime($vol['date_to'])) : ''; ?>" min="1900" max="2099" placeholder="Year">
                        </div>
                        <div>
                            <label class="pds-field-label">No. of Hours</label>
                            <input type="number" class="form-control form-control-sm" name="vol_hours[]" value="<?php echo e($vol['no_of_hours']); ?>" placeholder="e.g. 40">
                        </div>
                        <div>
                            <label class="pds-field-label">Position / Nature of Work</label>
                            <input type="text" class="form-control form-control-sm" name="vol_position[]" value="<?php echo e($vol['position_nature']); ?>" placeholder="e.g. Volunteer, Secretary">
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <button type="button" class="btn-add-pds-section mb-3" onclick="addVoluntaryRow()"><i class="fas fa-plus me-1"></i> Add Voluntary Work</button>
</div>


<!-- ====== STEP 7: Service Eligibility ====== -->
<div class="step-content" id="step7" style="display:none;">
    <div class="form-section-title"><i class="fas fa-certificate"></i> Service Eligibility / Licenses</div>
    <div id="eligibilityContainer">
        <?php if ($isEdit && !empty($employeeEligibility)): ?>
            <?php foreach ($employeeEligibility as $el): ?>
                <div class="pds-card eligibility-card">
                    <div class="pds-card-header">
                        <div class="pds-card-title">
                            <div class="pds-card-icon"><i class="fas fa-certificate"></i></div>
                            <div>
                                <div class="pds-card-label">License / Eligibility</div>
                                <div class="pds-card-subtitle">PRC, CSE, or other professional license</div>
                            </div>
                        </div>
                        <div class="pds-card-actions">
                            <button type="button" class="pds-card-btn btn-delete" onclick="this.closest('.pds-card').remove()" title="Remove"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="pds-form-grid">
                        <div class="full-width">
                            <label class="pds-field-label">License / Eligibility Title</label>
                            <input type="text" class="form-control form-control-sm" name="elig_title[]" value="<?php echo e($el['license_title']); ?>" placeholder="e.g. PRC Nurse License, CS Professional">
                        </div>
                        <div>
                            <label class="pds-field-label">License Number</label>
                            <input type="text" class="form-control form-control-sm" name="elig_number[]" value="<?php echo e($el['license_number']); ?>" placeholder="e.g. 0012345">
                        </div>
                        <div>
                            <label class="pds-field-label">Date of Exam</label>
                            <input type="date" class="form-control form-control-sm" name="elig_exam_date[]" value="<?php echo e($el['date_of_exam']); ?>">
                        </div>
                        <div>
                            <label class="pds-field-label">Place of Exam</label>
                            <input type="text" class="form-control form-control-sm" name="elig_exam_place[]" value="<?php echo e($el['place_of_exam']); ?>" placeholder="e.g. Manila, Cebu City">
                        </div>
                        <div>
                            <label class="pds-field-label">Valid From (Year)</label>
                            <input type="number" class="form-control form-control-sm" name="elig_from[]" value="<?php echo !empty($el['date_from']) ? date('Y', strtotime($el['date_from'])) : ''; ?>" min="1900" max="2099" placeholder="Year">
                        </div>
                        <div>
                            <label class="pds-field-label">Valid To (Year)</label>
                            <input type="number" class="form-control form-control-sm" name="elig_to[]" value="<?php echo !empty($el['date_to']) ? date('Y', strtotime($el['date_to'])) : ''; ?>" min="1900" max="2099" placeholder="Year or leave blank if lifetime">
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <button type="button" class="btn-add-pds-section mb-3" onclick="addEligibilityRow()"><i class="fas fa-plus me-1"></i> Add License/Eligibility</button>
</div>


<!-- ====== STEP 8: Skills, Recognition & Membership ====== -->
<div class="step-content" id="step8" style="display:none;">
    <div class="form-section-title"><i class="fas fa-star"></i> Special Skills & Hobbies</div>
    <div id="skillsContainer" class="repeater-accordion">
        <?php if ($isEdit && !empty($employeeSkills)): ?>
            <?php foreach ($employeeSkills as $sk): ?>
                <div class="repeater-row"><button type="button" class="btn-remove-row"
                        onclick="this.closest('.repeater-row').remove()"><i class="fas fa-times"></i></button><input type="text"
                        class="form-control form-control-sm" name="skill_name[]" value="<?php echo e($sk['skill_name']); ?>">
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <button type="button" class="btn-add-row mb-3"
        onclick="addSimpleRow('skillsContainer','skill_name','Skill or Hobby')"><i class="fas fa-plus me-1"></i> Add
        Skill</button>

    <div class="form-section-title mt-3"><i class="fas fa-award"></i> Non-Academic Distinctions / Recognition</div>
    <div id="recognitionsContainer" class="repeater-accordion">
        <?php if ($isEdit && !empty($employeeRecognitions)): ?>
            <?php foreach ($employeeRecognitions as $rc): ?>
                <div class="repeater-row">
                    <button type="button" class="btn-remove-row" onclick="this.closest('.repeater-row').remove()"><i class="fas fa-times"></i></button>
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="small text-muted d-block">Award/Recognition Title</label>
                            <input type="text" class="form-control form-control-sm" name="recognition_title[]" value="<?php echo e($rc['recognition_title']); ?>" placeholder="Title">
                        </div>
                        <div class="col-md-5 mb-2">
                            <label class="small text-muted d-block">Issued By / Organization</label>
                            <input type="text" class="form-control form-control-sm" name="recognition_issued_by[]" value="<?php echo e($rc['issued_by'] ?? ''); ?>" placeholder="Organization">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="small text-muted d-block">Date Received</label>
                            <input type="date" class="form-control form-control-sm" name="recognition_date[]" value="<?php echo e($rc['date_awarded'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <button type="button" class="btn-add-row mb-3" onclick="addRecognitionRow()">
        <i class="fas fa-plus me-1"></i> Add Award/Recognition
    </button>

    <div class="form-section-title mt-3"><i class="fas fa-users-cog"></i> Membership in Organizations</div>
    <div id="membershipsContainer" class="repeater-accordion">
        <?php if ($isEdit && !empty($employeeMemberships)): ?>
            <?php foreach ($employeeMemberships as $mb): ?>
                <div class="repeater-row"><button type="button" class="btn-remove-row"
                        onclick="this.closest('.repeater-row').remove()"><i class="fas fa-times"></i></button><input type="text"
                        class="form-control form-control-sm" name="membership_org[]"
                        value="<?php echo e($mb['organization_name']); ?>"></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <button type="button" class="btn-add-row mb-3"
        onclick="addSimpleRow('membershipsContainer','membership_org','Organization Name')"><i
            class="fas fa-plus me-1"></i> Add Membership</button>

</div>


<!-- ====== STEP 9: Assets & Liabilities ====== -->
<div class="step-content" id="step9" style="display:none;">
    <div class="form-section-title"><i class="fas fa-building"></i> Real Properties</div>
    <div id="realPropContainer">
        <?php if ($isEdit && !empty($employeeRealProps)): ?>
            <?php foreach ($employeeRealProps as $rp): ?>
                <div class="pds-card property-card">
                    <div class="pds-card-header">
                        <div class="pds-card-title">
                            <div class="pds-card-icon"><i class="fas fa-building"></i></div>
                            <div>
                                <div class="pds-card-label">Real Property</div>
                                <div class="pds-card-subtitle">Land, house, or commercial property</div>
                            </div>
                        </div>
                        <div class="pds-card-actions">
                            <button type="button" class="pds-card-btn btn-delete" onclick="this.closest('.pds-card').remove()" title="Remove"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="pds-form-grid cols-3">
                        <div>
                            <label class="pds-field-label">Description</label>
                            <input type="text" class="form-control form-control-sm" name="rprop_desc[]" value="<?php echo e($rp['description']); ?>" placeholder="e.g. Residential House and Lot">
                        </div>
                        <div>
                            <label class="pds-field-label">Kind</label>
                            <input type="text" class="form-control form-control-sm" name="rprop_kind[]" value="<?php echo e($rp['kind']); ?>" placeholder="e.g. Land, House, Condo">
                        </div>
                        <div>
                            <label class="pds-field-label">Exact Location</label>
                            <input type="text" class="form-control form-control-sm" name="rprop_location[]" value="<?php echo e($rp['exact_location']); ?>" placeholder="City / Province">
                        </div>
                        <div>
                            <label class="pds-field-label">Assessed Value (₱)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" name="rprop_assessed[]" value="<?php echo e($rp['assessed_value']); ?>" placeholder="0.00">
                        </div>
                        <div>
                            <label class="pds-field-label">Current Market Value (₱)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" name="rprop_market[]" value="<?php echo e($rp['market_value']); ?>" placeholder="0.00">
                        </div>
                        <div>
                            <label class="pds-field-label">Year &amp; Mode of Acquisition</label>
                            <input type="text" class="form-control form-control-sm" name="rprop_acq_mode[]" value="<?php echo e($rp['acquisition_year_mode'] ?? ''); ?>" placeholder="e.g. 2018-Bought, 2020-Inherited">
                        </div>
                        <div>
                            <label class="pds-field-label">Acquisition Cost (₱)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" name="rprop_acq_cost[]" value="<?php echo e($rp['acquisition_cost'] ?? ''); ?>" placeholder="0.00">
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <button type="button" class="btn-add-pds-section mb-3" onclick="addRealPropertyRow()"><i class="fas fa-plus me-1"></i> Add Real Property</button>

    <div class="form-section-title mt-3"><i class="fas fa-car"></i> Personal Properties</div>
    <div id="personalPropContainer">
        <?php if ($isEdit && !empty($employeePersonalProps)): ?>
            <?php foreach ($employeePersonalProps as $pp): ?>
                <div class="pds-card property-card">
                    <div class="pds-card-header">
                        <div class="pds-card-title">
                            <div class="pds-card-icon"><i class="fas fa-car"></i></div>
                            <div>
                                <div class="pds-card-label">Personal Property</div>
                                <div class="pds-card-subtitle">Vehicle, jewelry, equipment, etc.</div>
                            </div>
                        </div>
                        <div class="pds-card-actions">
                            <button type="button" class="pds-card-btn btn-delete" onclick="this.closest('.pds-card').remove()" title="Remove"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="pds-form-grid cols-3">
                        <div class="full-width">
                            <label class="pds-field-label">Description</label>
                            <input type="text" class="form-control form-control-sm" name="pprop_desc[]" value="<?php echo e($pp['description']); ?>" placeholder="e.g. Toyota Vios 2019, Gold Necklace">
                        </div>
                        <div>
                            <label class="pds-field-label">Year Acquired</label>
                            <input type="text" class="form-control form-control-sm" name="pprop_year[]" value="<?php echo e($pp['year_acquired']); ?>" placeholder="e.g. 2020">
                        </div>
                        <div>
                            <label class="pds-field-label">Acquisition Cost (₱)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" name="pprop_cost[]" value="<?php echo e($pp['acquisition_cost']); ?>" placeholder="0.00">
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <button type="button" class="btn-add-pds-section mb-3" onclick="addPersonalPropertyRow()"><i class="fas fa-plus me-1"></i> Add Personal Property</button>

    <div class="form-section-title mt-3"><i class="fas fa-file-invoice-dollar"></i> Liabilities</div>
    <div id="liabilitiesContainer">
        <?php if ($isEdit && !empty($employeeLiabilities)): ?>
            <?php foreach ($employeeLiabilities as $lb): ?>
                <div class="pds-card">
                    <div class="pds-card-header">
                        <div class="pds-card-title">
                            <div class="pds-card-icon" style="background:linear-gradient(135deg,#dc2626,#ef4444)"><i class="fas fa-file-invoice-dollar"></i></div>
                            <div>
                                <div class="pds-card-label">Liability</div>
                                <div class="pds-card-subtitle">Outstanding loans or debts</div>
                            </div>
                        </div>
                        <div class="pds-card-actions">
                            <button type="button" class="pds-card-btn btn-delete" onclick="this.closest('.pds-card').remove()" title="Remove"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="pds-form-grid cols-3">
                        <div>
                            <label class="pds-field-label">Nature of Liability</label>
                            <input type="text" class="form-control form-control-sm" name="liab_nature[]" value="<?php echo e($lb['nature_of_liability']); ?>" placeholder="e.g. Housing Loan, Car Loan">
                        </div>
                        <div>
                            <label class="pds-field-label">Name of Creditor</label>
                            <input type="text" class="form-control form-control-sm" name="liab_creditor[]" value="<?php echo e($lb['creditor_name']); ?>" placeholder="e.g. PNB, SSS">
                        </div>
                        <div>
                            <label class="pds-field-label">Outstanding Balance (₱)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" name="liab_balance[]" value="<?php echo e($lb['outstanding_balance']); ?>" placeholder="0.00">
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <button type="button" class="btn-add-pds-section mb-3" onclick="addLiabilityRow()"><i class="fas fa-plus me-1"></i> Add Liability</button>

</div>


<!-- ====== STEP 10: Other Information (Disclosures) ====== -->
<div class="step-content" id="step10" style="display:none;">
    <div class="form-section-title"><i class="fas fa-clipboard-list"></i> Employment-Related Disclosures</div>

    <?php
    $disclosures = [
        ['is_related_to_company', 'related_details', 'Are you related by consanguinity or affinity to any Raquel Pawnshop employee within the third degree?'],
        ['has_admin_offense', 'admin_offense_details', 'Have you ever been found guilty of any administrative offense?'],
        ['has_criminal_charge', 'criminal_charge_details', 'Have you been criminally charged before any court?'],
        ['has_criminal_conviction', 'criminal_conviction_details', 'Have you ever been convicted of any crime or violation of law?'],
        ['has_been_separated', 'separation_details', 'Have you ever been separated from service (resignation, retirement, termination)?'],
    ];
    foreach ($disclosures as $d):
        ?>
        <div class="disclosure-item">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="<?php echo $d[0]; ?>" id="<?php echo $d[0]; ?>" <?php echo $chk($d[0]); ?> onchange="toggleDetails(this,'<?php echo $d[1]; ?>_div')">
                <label class="form-check-label" for="<?php echo $d[0]; ?>"><?php echo $d[2]; ?></label>
            </div>
            <div class="disclosure-details <?php echo !empty($e[$d[0]]) ? 'show' : ''; ?>" id="<?php echo $d[1]; ?>_div">
                <textarea class="form-control form-control-sm" name="<?php echo $d[1]; ?>" rows="2"
                    placeholder="Provide details..."><?php echo $v($d[1]); ?></textarea>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="form-section-title mt-3"><i class="fas fa-hand-holding-heart"></i> Special Considerations</div>
    <?php
    $specials = [
        ['is_pwd', 'pwd_details', 'Are you a person with disability (PWD)?'],
        ['is_solo_parent', 'solo_parent_details', 'Are you a solo parent?'],
    ];
    foreach ($specials as $d):
        ?>
        <div class="disclosure-item">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="<?php echo $d[0]; ?>" id="<?php echo $d[0]; ?>" <?php echo $chk($d[0]); ?> onchange="toggleDetails(this,'<?php echo $d[1]; ?>_div')">
                <label class="form-check-label" for="<?php echo $d[0]; ?>"><?php echo $d[2]; ?></label>
            </div>
            <div class="disclosure-details <?php echo !empty($e[$d[0]]) ? 'show' : ''; ?>" id="<?php echo $d[1]; ?>_div">
                <textarea class="form-control form-control-sm" name="<?php echo $d[1]; ?>" rows="2"
                    placeholder="Provide details..."><?php echo $v($d[1]); ?></textarea>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="form-section-title mt-3"><i class="fas fa-heartbeat"></i> Health Information</div>
    <?php
    $health = [
        ['has_recent_hospital', 'hospital_details', 'Have you been hospitalized in the last 6 months?'],
        ['has_current_treatment', 'treatment_details', 'Are you currently undergoing medication or treatment?'],
    ];
    foreach ($health as $d):
        ?>
        <div class="disclosure-item">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="<?php echo $d[0]; ?>" id="<?php echo $d[0]; ?>" <?php echo $chk($d[0]); ?> onchange="toggleDetails(this,'<?php echo $d[1]; ?>_div')">
                <label class="form-check-label" for="<?php echo $d[0]; ?>"><?php echo $d[2]; ?></label>
            </div>
            <div class="disclosure-details <?php echo !empty($e[$d[0]]) ? 'show' : ''; ?>" id="<?php echo $d[1]; ?>_div">
                <textarea class="form-control form-control-sm" name="<?php echo $d[1]; ?>" rows="2"
                    placeholder="Provide details..."><?php echo $v($d[1]); ?></textarea>
            </div>
        </div>
    <?php endforeach; ?>

</div>


<!-- ====== STEP 11: References ====== -->
<div class="step-content" id="step11" style="display:none;">
    <div class="form-section-title"><i class="fas fa-address-book"></i> Character References (3 persons not related)
    </div>
    <?php for ($r = 0; $r < 3; $r++): ?>
        <div class="repeater-row">
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control form-control-sm" name="ref_name[]"
                        value="<?php echo isset($employeeRefs[$r]) ? e($employeeRefs[$r]['reference_name']) : ''; ?>">
                </div>
                <div class="col-md-5 mb-2">
                    <label class="form-label">Address</label>
                    <input type="text" class="form-control form-control-sm" name="ref_address[]"
                        value="<?php echo isset($employeeRefs[$r]) ? e($employeeRefs[$r]['reference_address']) : ''; ?>">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Mobile Number</label>
                    <input type="text" class="form-control form-control-sm" name="ref_telephone[]" maxlength="11"
                        value="<?php echo isset($employeeRefs[$r]) ? e($employeeRefs[$r]['reference_telephone']) : ''; ?>" inputmode="numeric">
                </div>
            </div>
        </div>
    <?php endfor; ?>

</div>


<!-- ====== STEP 12: Employment & Submit ====== -->
<div class="step-content" id="step12" style="display:none;">
    <div class="form-section-title"><i class="fas fa-building"></i> Employment Details</div>
    <div class="row">
        <div class="col-md-3 mb-3">
            <label class="form-label">Employee ID (Company ID)</label>
            <input type="text" class="form-control" name="employee_code" value="<?php echo $v('employee_code'); ?>" placeholder="e.g. 026-001">
            <small class="text-muted">Optional: Official company issued ID.</small>
        </div>
        <div class="col-md-3 mb-3">
            <?php
            $nonRegularStatuses = ['OJT', 'Probationary', 'Project Based', 'Project-Based', 'Trainee'];
            $currentStatus = $e['employment_status'] ?? 'Regular';
            $hireDateLabels = [
                'OJT'          => 'OJT Start Date',
                'Probationary' => 'Probation Start Date',
                'Project Based'=> 'Project Start Date',
                'Project-Based'=> 'Project Start Date',
                'Trainee'      => 'Training Start Date',
            ];
            $hireDateLabel = $hireDateLabels[$currentStatus] ?? 'Date Hired';
            ?>
            <label class="form-label" id="hireDateLabel"><?php echo $hireDateLabel; ?> <span class="text-danger">*</span></label>
            <input type="date" class="form-control" name="hire_date" id="hire_date" value="<?php echo $v('hire_date'); ?>" required>
            <small class="text-muted" id="hireDateHint">
                <?php if (in_array($currentStatus, $nonRegularStatuses, true)): ?>
                    The date this person first engaged with the company. Not the same as regularization.
                <?php else: ?>
                    The official date this employee was hired / regularized.
                <?php endif; ?>
            </small>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Department <span class="text-danger">*</span></label>
            <?php if (!empty($departments) && is_array($departments)): ?>
                <select class="form-select" name="department_id" id="department_id" required>
                    <option value="" data-name="">-- Select Department --</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['department_id']; ?>" data-name="<?php echo e($dept['department_name']); ?>" <?php echo (($e['department_id'] ?? '') == $dept['department_id']) ? 'selected' : ''; ?>>
                            <?php echo e($dept['department_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="text" class="form-control" name="department_id" value="<?php echo $v('department_id'); ?>" required>
                <small class="text-muted">No departments defined yet. <a
                        href="<?php echo BASE_URL; ?>/manager/departments.php" target="_blank">Add departments</a></small>
            <?php endif; ?>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Job Title <span class="text-danger">*</span></label>
            <?php if (!empty($jobTitles) && is_array($jobTitles)): ?>
                <select class="form-select" name="job_title_id" id="job_title_id" required>
                    <option value="">-- Select Job Title --</option>
                    <?php foreach ($jobTitles as $jt): ?>
                        <?php
                        $jt_id = (int) ($jt['job_title_id'] ?? 0);
                        $selected = false;
                        if (!empty($e['job_title_id'])) {
                            $selected = (int) $e['job_title_id'] === $jt_id;
                        } else {
                            $selected = (($e['job_title'] ?? '') === ($jt['job_title'] ?? ''));
                        }
                        ?>
                        <option value="<?php echo $jt_id; ?>"
                            data-title="<?php echo e($jt['job_title']); ?>"
                            data-dept-id="<?php echo (int) ($jt['department_id'] ?? 0); ?>"
                            <?php
                            $group = 'other';
                            if ((int)($jt['is_head'] ?? 0) === 1) {
                                $group = 'head';
                            } elseif ((int)($jt['rank_category_id'] ?? 5) < 5) {
                                $group = 'direct';
                            }
                            ?>
                            data-position-group="<?php echo $group; ?>"
                            data-rank-id="<?php echo (int)($jt['rank_category_id'] ?? 0); ?>"
                            <?php echo $selected ? 'selected' : ''; ?>>
                            <?php echo e($jt['job_title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'HR Manager'): ?>
                    <small class="text-muted">Missing a title? <a href="<?php echo BASE_URL; ?>/manager/positions.php" target="_blank">Manage positions</a></small>
                <?php endif; ?>
            <?php else: ?>
                <input type="text" class="form-control" name="job_title" value="<?php echo $v('job_title'); ?>" required>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'HR Manager'): ?>
                    <small class="text-muted">No positions defined yet. <a href="<?php echo BASE_URL; ?>/manager/positions.php" target="_blank">Add positions</a></small>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">RANK</label>
            <select class="form-select" name="rank_category_id" id="rank_category_id">
                <option value="">Select Rank</option>
                <?php foreach ($rankCategories as $rank): ?>
                    <option value="<?php echo (int) $rank['rank_category_id']; ?>" <?php echo (($e['rank_category_id'] ?? '') == $rank['rank_category_id']) ? 'selected' : ''; ?>>
                        <?php echo e($rank['rank_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">Branch</label>
            <select class="form-select" name="branch_id">
                <option value="">Select Branch</option>
                <?php
                if ($branches) {
                    $branches->data_seek(0);
                }
                while ($branches && $branch = $branches->fetch_assoc()): ?>
                    <option value="<?php echo $branch['branch_id']; ?>" <?php echo (($e['branch_id'] ?? '') == $branch['branch_id']) ? 'selected' : ''; ?>>
                        <?php echo e($branch['branch_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Employment Status <span class="text-danger">*</span></label>
            <?php
            $employmentStatuses = ['OJT', 'Probationary', 'Project Based', 'Regular', 'Separated', 'Trainee', 'AWOL', 'Retirement', 'Death', 'Permanent or Total Disability', 'Resignation', 'Failed in Training', 'Termination for Cause'];
            $employmentStatusValue = $e['employment_status'] ?? 'Regular';
            ?>
            <select class="form-select" name="employment_status" id="employment_status_select" required>
                <?php foreach ($employmentStatuses as $status): ?>
                    <option value="<?php echo e($status); ?>" <?php echo $employmentStatusValue === $status ? 'selected' : ''; ?>>
                        <?php echo e($status); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">OJT / Trainee / Probationary / Project Based require contract dates below.</small>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Employment Type</label>
            <select class="form-select" name="employment_type">
                <option value="Full-time" <?php echo $sel('employment_type', 'Full-time'); ?>>Full-time</option>
                <option value="Part-time" <?php echo $sel('employment_type', 'Part-time'); ?>>Part-time</option>
            </select>
            <small class="text-muted">
                <?php if (in_array($currentStatus, $nonRegularStatuses, true)): ?>
                    Refers to their daily schedule commitment, not official employment classification.
                <?php else: ?>
                    Indicates whether this employee works full or reduced hours.
                <?php endif; ?>
            </small>
        </div>
    </div>

    <!-- Contract / Arrangement Dates (Visible for non-regular employment statuses) -->
    <?php
    $isNonRegular = in_array(($e['employment_status'] ?? 'Regular'), ['OJT', 'Probationary', 'Project Based', 'Project-Based', 'Trainee'], true);
    $contractStartLabels = [
        'OJT'          => 'OJT Period — Start Date',
        'Probationary' => 'Probation Period — Start Date',
        'Project Based'=> 'Project Period — Start Date',
        'Project-Based'=> 'Project Period — Start Date',
        'Trainee'      => 'Training Period — Start Date',
    ];
    $contractEndLabels = [
        'OJT'          => 'OJT Period — End Date',
        'Probationary' => 'Probation Period — End Date',
        'Project Based'=> 'Project Period — End Date',
        'Project-Based'=> 'Project Period — End Date',
        'Trainee'      => 'Training Period — End Date',
    ];
    $contractStartLabel = $contractStartLabels[$currentStatus] ?? 'Contract Start Date';
    $contractEndLabel   = $contractEndLabels[$currentStatus]   ?? 'Contract End Date';
    ?>
    <div class="row" id="contractDatesRow" style="display: <?php echo $isNonRegular ? 'flex' : 'none'; ?>;">
        <div class="col-12 mb-2">
            <div class="alert alert-info border-0 py-2 px-3 mb-0" style="font-size:0.85rem; border-radius:8px;">
                <i class="fas fa-info-circle me-1"></i>
                <strong>Note:</strong> The <em>engagement date</em> above records when this person first joined the company.
                The <em>arrangement period</em> below tracks the duration of their specific <?php echo strtolower(in_array($currentStatus, ['OJT','Trainee'], true) ? $currentStatus : ($currentStatus === 'Project Based' || $currentStatus === 'Project-Based' ? 'project' : 'probation')); ?> arrangement.
                If they are later regularized, this record will be updated via a <strong>Career Movement</strong>.
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label" id="contractStartLabel"><?php echo $contractStartLabel; ?> <span class="text-danger">*</span></label>
            <input type="date" class="form-control" name="contract_start_date" id="contract_start_date"
                value="<?php echo $v('contract_start_date'); ?>"
                <?php echo $isNonRegular ? 'required' : ''; ?>>
            <small class="text-muted">Start of the <?php echo strtolower($currentStatus); ?> arrangement.</small>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label" id="contractEndLabel"><?php echo $contractEndLabel; ?></label>
            <input type="date" class="form-control" name="contract_end_date" id="contract_end_date"
                value="<?php echo $v('contract_end_date'); ?>">
            <small class="text-muted">Expected end date. Leave blank if ongoing.</small>
        </div>
    </div>

    <?php if ($isEdit): ?>
        <div class="row">
            <?php if ($_SESSION['role'] === 'HR Supervisor'): ?>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Employee Record Status</label>
                    <div>
                        <span class="badge <?php echo !empty($e['is_active']) ? 'bg-success' : 'bg-danger'; ?> px-3 py-2">
                            <?php echo !empty($e['is_active']) ? 'Active Employee' : 'Inactive Employee'; ?>
                        </span>
                    </div>
                    <small class="text-muted">Activation changes are handled by HR Manager.</small>
                </div>
            <?php else: ?>
                <div class="col-md-4 mb-3">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?php echo $chk('is_active'); ?>>
                        <label class="form-check-label" for="isActive">Active Employee</label>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="form-section-title mt-4"><i class="fas fa-heartbeat"></i> Emergency Contacts</div>
    
    <?php
    $emergencies = [];
    if ($isEdit && !empty($eid)) {
        $emergencies = $conn->query("SELECT * FROM employee_emergency_contacts WHERE employee_id=$eid ORDER BY is_primary DESC, emergency_id ASC")->fetch_all(MYSQLI_ASSOC);
    }
    if (empty($emergencies) && !empty($v('emergency_contact_name'))) {
        $emergencies[] = [
            'emergency_id' => 0,
            'contact_name' => $v('emergency_contact_name'),
            'relationship' => $v('emergency_contact_relationship'),
            'contact_number' => $v('emergency_contact_number'),
            'is_primary' => 1
        ];
    }
    ?>

    <div id="emergencyContactsContainer">
        <?php 
        $contactIdx = 0;
        if (!empty($emergencies)): 
            foreach ($emergencies as $emerg): 
                $contactIdx++;
                $isPrimary = (int)$emerg['is_primary'] === 1;
        ?>
            <div class="emergency-contact-card <?php echo $isPrimary ? 'is-primary-contact' : ''; ?>">
                <div class="emergency-contact-header">
                    <label class="emergency-contact-radio">
                        <input type="radio" name="emergency_is_primary" value="<?php echo $contactIdx; ?>" <?php echo $isPrimary ? 'checked' : ''; ?>
                            onchange="document.querySelectorAll('.emergency-contact-card').forEach(el=>el.classList.remove('is-primary-contact')); this.closest('.emergency-contact-card').classList.add('is-primary-contact');">
                        Set as Primary Contact
                        <?php if ($isPrimary): ?>
                            <span class="emergency-primary-badge ms-2"><i class="fas fa-star"></i> Primary</span>
                        <?php endif; ?>
                    </label>
                    <button type="button" class="pds-card-btn btn-delete" onclick="removeEmergencyContact(this)" title="Remove"><i class="fas fa-trash"></i></button>
                </div>
                <div class="pds-form-grid cols-3">
                    <div>
                        <label class="pds-field-label">Contact Name</label>
                        <input type="text" class="form-control form-control-sm" name="emergency_contact_name[]" value="<?php echo e($emerg['contact_name']); ?>" placeholder="Full Name">
                    </div>
                    <div>
                        <label class="pds-field-label">Relationship</label>
                        <input type="text" class="form-control form-control-sm" name="emergency_contact_relationship[]" value="<?php echo e($emerg['relationship']); ?>" placeholder="e.g. Spouse, Parent, Sibling">
                    </div>
                    <div>
                        <label class="pds-field-label">Contact Number</label>
                        <input type="text" class="form-control form-control-sm" name="emergency_contact_number[]" value="<?php echo e($emerg['contact_number']); ?>" placeholder="09XXXXXXXXX" maxlength="11" inputmode="numeric">
                    </div>
                </div>
            </div>
        <?php 
            endforeach; 
        endif; 
        ?>
    </div>
    <button type="button" class="btn-add-pds-section mb-4" onclick="addEmergencyContactRow(false)"><i class="fas fa-plus me-1"></i> Add Emergency Contact</button>

    <script>
    // Make sure we have at least one emergency contact if blank
    document.addEventListener("DOMContentLoaded", function() {
        const container = document.getElementById('emergencyContactsContainer');
        if (container && container.querySelectorAll('.emergency-contact-card').length === 0) {
            addEmergencyContactRow(true);
        }
    });
    </script>

    </div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const deptSelect    = document.getElementById("department_id");
    const jobTitleSelect = document.getElementById("job_title_id");

    if (!deptSelect || !jobTitleSelect || jobTitleSelect.tagName !== 'SELECT') return;

    // Snapshot all job-title options on page load (before any filtering).
    // Each entry stores value, display text, and the department_id from the DB.
    const allJobTitleOptions = Array.from(jobTitleSelect.options)
        .filter(opt => opt.value !== "")
        .map(opt => ({
            value:  opt.value,
            text:   opt.text.trim(),
            title:  (opt.getAttribute("data-title") || opt.textContent || "").trim(),
            deptId: parseInt(opt.getAttribute("data-dept-id") || "0", 10),
            group:  opt.getAttribute("data-position-group") || "other",
            rankId: opt.getAttribute("data-rank-id") || "0"
        }));

    // Remember the job title that was pre-selected (edit mode).
    const initialJobTitle = "<?php echo (string) ($e['job_title_id'] ?? ''); ?>";

    function appendJobTitleOption(jobTitle, currentJobTitle) {
        const opt = document.createElement("option");
        opt.value = jobTitle.value;
        opt.textContent = jobTitle.text;
        opt.setAttribute("data-title", jobTitle.title || jobTitle.text);
        opt.setAttribute("data-dept-id", jobTitle.deptId);
        opt.setAttribute("data-position-group", jobTitle.group);
        opt.setAttribute("data-rank-id", jobTitle.rankId || "0");
        if (jobTitle.value === currentJobTitle) {
            opt.selected = true;
        }
        jobTitleSelect.appendChild(opt);
    }

    function appendGroupLabel(label) {
        const opt = document.createElement("option");
        opt.value = "";
        opt.textContent = label;
        opt.disabled = true;
        opt.className = "position-group-label";
        jobTitleSelect.appendChild(opt);
    }

    function updateJobTitles() {
        const selectedDeptId = parseInt(deptSelect.value || "0", 10);
        const currentJobTitle = jobTitleSelect.value || initialJobTitle;

        // Rebuild the list
        jobTitleSelect.innerHTML = "";

        if (!selectedDeptId) {
            jobTitleSelect.innerHTML = '<option value="">-- Select Department First --</option>';
            jobTitleSelect.disabled = true;
            return;
        }

        jobTitleSelect.disabled = false;
        jobTitleSelect.innerHTML = '<option value="">-- Select Job Title --</option>';

        const matchingTitles = allJobTitleOptions.filter(jobTitle => jobTitle.deptId === selectedDeptId);
        const headTitles = matchingTitles.filter(jobTitle => jobTitle.group === "head");
        const directTitles = matchingTitles.filter(jobTitle => jobTitle.group === "direct");
        const otherTitles = matchingTitles.filter(jobTitle => jobTitle.group === "other");

        if (headTitles.length) {
            appendGroupLabel("-- HEAD --");
            headTitles.forEach(jobTitle => {
                appendJobTitleOption(jobTitle, currentJobTitle);
            });
        }

        if (directTitles.length) {
            appendGroupLabel("-- DIRECT REPORT --");
            directTitles.forEach(jobTitle => {
                appendJobTitleOption(jobTitle, currentJobTitle);
            });
        }

        if (otherTitles.length) {
            appendGroupLabel("-- OTHERS --");
            otherTitles.forEach(jobTitle => {
                appendJobTitleOption(jobTitle, currentJobTitle);
            });
        }

        if (!matchingTitles.length) {
            const opt = document.createElement("option");
            opt.value = "";
            opt.textContent = "No positions found for this department";
            opt.disabled = true;
            jobTitleSelect.appendChild(opt);
        }

        // If the previously-selected title is no longer visible, clear the field.
        if (jobTitleSelect.value !== currentJobTitle) {
            jobTitleSelect.value = "";
        }

        // Trigger change event to sync rank autofill listener
        jobTitleSelect.dispatchEvent(new Event('change'));
    }

    deptSelect.addEventListener("change", updateJobTitles);

    // Run once on load so the list is correct in edit mode.
    updateJobTitles();
});
</script>
