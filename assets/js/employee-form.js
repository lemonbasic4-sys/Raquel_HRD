/**
 * Employee Form JS — 12-step wizard + dynamic repeater rows
 */

const TOTAL_STEPS = 12;

const PORTAL_MAP = [
  { id: 1, label: 'Core Identity',    steps: [1] },
  { id: 2, label: 'Background',       steps: [2, 3, 4, 5, 6] },
  { id: 3, label: 'Qualifications',   steps: [7, 8, 9, 10] },
  { id: 4, label: 'Final',            steps: [11, 12] }
];

function showPortal(portalId) {
    const currentStep = getCurrentStep();
    if (!validateStep(currentStep)) {
        return; // Validation failed, field highlighted and focused!
    }
    const activePortal = PORTAL_MAP.find(p => p.steps.includes(currentStep));
    const targetPortal = PORTAL_MAP.find(p => p.id === portalId);
    
    if (targetPortal) {
        let nextStepToShow = targetPortal.steps[0];
        
        // If they clicked the portal they are already on, go to the next step within that portal!
        if (activePortal && activePortal.id === portalId) {
            const currentIndex = activePortal.steps.indexOf(currentStep);
            if (currentIndex !== -1 && currentIndex < activePortal.steps.length - 1) {
                nextStepToShow = activePortal.steps[currentIndex + 1];
            } else {
                // If it's the last step of this portal, go to the first step of the next portal (if any)
                const nextPortal = PORTAL_MAP.find(p => p.id === portalId + 1);
                if (nextPortal) {
                    nextStepToShow = nextPortal.steps[0];
                } else {
                    return; // No next portal, do nothing
                }
            }
        }
        
        if (typeof autoSaveDraft === 'function') {
            autoSaveDraft(() => {
                showStep(nextStepToShow);
            });
        } else {
            showStep(nextStepToShow);
        }
    }
}

function getCurrentStep() {
    const input = document.getElementById('currentStepInput');
    const val = input ? parseInt(input.value, 10) : 1;
    return isNaN(val) ? 1 : val;
}

function nextStep() {
    const s = getCurrentStep();
    if (!validateStep(s)) {
        return; // Validation failed on current step, user taken directly to problem field
    }
    if (s < TOTAL_STEPS) {
        if (typeof autoSaveDraft === 'function') {
            autoSaveDraft(() => {
                showStep(s + 1);
            });
        } else {
            showStep(s + 1);
        }
    }
}

function prevStep() {
    const s = getCurrentStep();
    if (s > 1) {
        showStep(s - 1);
    }
}

function showStep(step, scrollToTop = true) {
    step = parseInt(step, 10) || 1;
    if (step < 1) step = 1;
    if (step > TOTAL_STEPS) step = TOTAL_STEPS;

    // Show/hide step content divs
    document.querySelectorAll('.step-content').forEach(el => el.style.display = 'none');
    const target = document.getElementById('step' + step);
    if (target) target.style.display = 'block';

    // Find active portal
    const activePortal = PORTAL_MAP.find(p => p.steps.includes(step));
    const activePortalId = activePortal ? activePortal.id : 1;

    // Update portal tab UI classes
    PORTAL_MAP.forEach(p => {
        const tabEl = document.getElementById('portal-tab-' + p.id);
        if (tabEl) {
            tabEl.classList.remove('active', 'completed');
            if (p.id === activePortalId) {
                tabEl.classList.add('active');
            } else if (step > p.steps[p.steps.length - 1]) {
                tabEl.classList.add('completed');
            }
        }
        
        // Render sub-step dots
        const dotsContainer = document.getElementById('portal-sub-' + p.id);
        if (dotsContainer) {
            dotsContainer.innerHTML = '';
            p.steps.forEach(s => {
                const dot = document.createElement('span');
                dot.className = 'portal-sub-dot';
                if (s === step) {
                    dot.classList.add('active');
                } else if (step > s) {
                    dot.classList.add('completed');
                }
                
                // Add tooltip title for easier hover understanding
                dot.title = `Step ${s}`;
                
                // Make the dot clickable
                dot.addEventListener('click', (e) => {
                    e.stopPropagation(); // Prevents triggering the outer portal-tab onclick
                    if (typeof autoSaveDraft === 'function') {
                        autoSaveDraft(() => {
                            showStep(s);
                        });
                    } else {
                        showStep(s);
                    }
                });
                
                dotsContainer.appendChild(dot);
            });
        }
    });

    // Sync with hidden input for form persistence
    const stepInput = document.getElementById('currentStepInput');
    if (stepInput) stepInput.value = step;

    // Update URL without refreshing to persist step on reload
    const url = new URL(window.location);
    url.searchParams.set('step', step);
    window.history.pushState({}, '', url);

    // Update progress bar
    const percent = (step / TOTAL_STEPS) * 100;
    const bar = document.getElementById('pdsProgressBar');
    if (bar) bar.style.width = percent + '%';
    const percentLabel = document.getElementById('pdsProgressPercent');
    if (percentLabel) percentLabel.textContent = Math.round(percent) + '%';

    // Update label in sticky footer
    const progressLabel = document.getElementById('wizardProgressLabel');
    if (progressLabel && activePortal) {
        const stepIndexInPortal = activePortal.steps.indexOf(step) + 1;
        const totalStepsInPortal = activePortal.steps.length;
        progressLabel.textContent = `${activePortal.label} · Step ${stepIndexInPortal} of ${totalStepsInPortal}`;
    }

    // Update navigation buttons
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');

    if (prevBtn) prevBtn.style.display = (step === 1) ? 'none' : 'inline-block';
    if (nextBtn) nextBtn.style.display = (step === TOTAL_STEPS) ? 'none' : 'inline-block';
    if (submitBtn) submitBtn.style.display = (step === TOTAL_STEPS) ? 'inline-block' : 'none';

    // Ensure has-wizard-footer class is added to body to prevent content overlapping
    document.body.classList.add('has-wizard-footer');

    if (step === 12 && typeof updatePDSSummary === 'function') {
        updatePDSSummary();
    }

    // Scroll page to top smoothly only if requested (don't scroll to top when focusing an invalid field)
    if (scrollToTop) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

// ── Validation & Error Navigation Helpers ─────────────────────────────────────

function getFieldLabel(el) {
    if (!el) return 'This field';
    if (el.id) {
        const lbl = document.querySelector(`label[for="${el.id}"]`);
        if (lbl) return lbl.textContent.replace('*', '').trim();
    }
    const formGroup = el.closest('.mb-3, .form-group, div');
    if (formGroup) {
        const lbl = formGroup.querySelector('label');
        if (lbl) return lbl.textContent.replace('*', '').trim();
    }
    return el.placeholder || el.name || 'This field';
}

function showValidationAlertBanner(stepNum, message) {
    let existing = document.getElementById('wizardValidationAlert');
    if (existing) existing.remove();

    const banner = document.createElement('div');
    banner.id = 'wizardValidationAlert';
    banner.className = 'alert alert-danger alert-dismissible fade show shadow-sm mb-3 d-flex align-items-center justify-content-between';
    banner.style.borderRadius = '10px';
    banner.style.borderLeft = '5px solid #dc3545';
    banner.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-circle fs-4 me-3 text-danger"></i>
            <div>
                <strong>Action Required:</strong> ${message}
                <div class="small text-muted">We shifted to <strong>Step ${stepNum}</strong> and placed your focus directly on the input below.</div>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;

    const cardBody = document.querySelector('.content-card .card-body') || document.querySelector('form');
    if (cardBody) {
        cardBody.insertBefore(banner, cardBody.firstChild);
    }
}

function highlightAndFocusInvalidField(inputElement, errorMessage) {
    if (!inputElement) return;

    // Find step containing this element
    const stepDiv = inputElement.closest('.step-content');
    let stepNum = 1;
    if (stepDiv && stepDiv.id && stepDiv.id.startsWith('step')) {
        stepNum = parseInt(stepDiv.id.replace('step', ''), 10) || 1;
    }

    // Switch to that step WITHOUT scrolling to top
    showStep(stepNum, false);

    // Apply error styling
    inputElement.classList.add('is-invalid');
    inputElement.classList.add('error-pulse');
    
    // Find or create invalid feedback container
    let parent = inputElement.parentElement;
    let feedback = parent.querySelector('.invalid-feedback-custom');
    if (!feedback) {
        feedback = document.createElement('div');
        feedback.className = 'invalid-feedback-custom text-danger fw-bold small mt-1';
        parent.appendChild(feedback);
    }
    feedback.innerHTML = `<i class="fas fa-exclamation-circle me-1"></i>${errorMessage}`;
    feedback.style.display = 'block';

    // Show floating alert banner
    showValidationAlertBanner(stepNum, errorMessage);

    // Center the element smoothly in viewport and focus
    setTimeout(() => {
        inputElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        inputElement.focus({ preventScroll: true });
    }, 150);

    // Remove error on edit
    const clearError = () => {
        inputElement.classList.remove('is-invalid');
        inputElement.classList.remove('error-pulse');
        if (feedback) {
            feedback.style.display = 'none';
        }
        inputElement.removeEventListener('input', clearError);
        inputElement.removeEventListener('change', clearError);
    };
    inputElement.addEventListener('input', clearError);
    inputElement.addEventListener('change', clearError);
}

function validateStep(stepNum) {
    const stepEl = document.getElementById('step' + stepNum);
    if (!stepEl) return true;

    // Remove old feedback in this step
    stepEl.querySelectorAll('.invalid-feedback-custom').forEach(el => el.remove());
    stepEl.querySelectorAll('.is-invalid, .error-pulse').forEach(el => el.classList.remove('is-invalid', 'error-pulse'));

    const inputs = Array.from(stepEl.querySelectorAll('input:not([type="hidden"]), select, textarea'));
    for (let input of inputs) {
        if (input.disabled) continue;

        const val = input.value ? input.value.trim() : '';
        const name = input.name || '';
        const label = getFieldLabel(input);

        // Required check
        if (input.hasAttribute('required') && val === '') {
            highlightAndFocusInvalidField(input, `${label} is required.`);
            return false;
        }

        // Mobile Number (contact_number)
        if (name === 'contact_number' && val !== '') {
            const digits = val.replace(/\D/g, '');
            if (digits.length === 10 && digits.startsWith('9')) {
                input.value = '0' + digits; // Auto-correct on the fly
            } else if (!/^09\d{9}$/.test(digits)) {
                highlightAndFocusInvalidField(input, `Mobile Number must be 11 digits starting with 09 (e.g., 09998988877). You entered: "${val}"`);
                return false;
            }
        }

        // Emergency Contact Number
        if (name === 'emergency_contact_number[]' && val !== '') {
            const digits = val.replace(/\D/g, '');
            if (digits.length === 10 && digits.startsWith('9')) {
                input.value = '0' + digits; // Auto-correct on the fly
            } else if (!/^09\d{9}$/.test(digits) && digits.length < 7) {
                highlightAndFocusInvalidField(input, `Emergency Contact Number must be a valid 11-digit mobile (09XXXXXXXXX) or landline number.`);
                return false;
            }
        }

        // Email
        if ((input.type === 'email' || name === 'email') && val !== '') {
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                highlightAndFocusInvalidField(input, `Please enter a valid email address.`);
                return false;
            }
        }

        // SSS Number
        if (name === 'sss_number' && val !== '') {
            const digits = val.replace(/\D/g, '');
            if (digits.length !== 10) {
                highlightAndFocusInvalidField(input, `SSS Number must be 10 digits (format: 00-0000000-0).`);
                return false;
            }
        }

        // PhilHealth Number
        if (name === 'philhealth_number' && val !== '') {
            const digits = val.replace(/\D/g, '');
            if (digits.length !== 12) {
                highlightAndFocusInvalidField(input, `PhilHealth Number must be 12 digits (format: 00-000000000-0).`);
                return false;
            }
        }

        // Pag-IBIG Number
        if (name === 'pagibig_number' && val !== '') {
            const digits = val.replace(/\D/g, '');
            if (digits.length !== 12) {
                highlightAndFocusInvalidField(input, `Pag-IBIG Number must be 12 digits (format: 0000-0000-0000).`);
                return false;
            }
        }

        // TIN Number
        if (name === 'tin_number' && val !== '') {
            const digits = val.replace(/\D/g, '');
            if (digits.length !== 9 && digits.length !== 12) {
                highlightAndFocusInvalidField(input, `TIN Number must be 9 or 12 digits (format: 000-000-000-000).`);
                return false;
            }
        }

        // HTML5 pattern check
        if (!input.checkValidity()) {
            const msg = input.title || input.validationMessage || `Invalid format for ${label}.`;
            highlightAndFocusInvalidField(input, msg);
            return false;
        }
    }

    return true;
}

function validateAllSteps() {
    for (let s = 1; s <= TOTAL_STEPS; s++) {
        if (!validateStep(s)) {
            return false;
        }
    }
    return true;
}

// ── Mobile Accordion Repeater Helper ─────────────────────────────────────────
function updateRepeaterSummary(row) {
    let bar = row.querySelector('.repeater-summary-bar');
    if (!bar) {
        bar = document.createElement('div');
        bar.className = 'repeater-summary-bar';
        bar.addEventListener('click', (e) => {
            if (window.innerWidth <= 768) {
                const isExpanded = row.classList.contains('expanded');
                
                // Collapse all other rows in this container
                const container = row.closest('.repeater-accordion');
                if (container) {
                    container.querySelectorAll('.repeater-row').forEach(r => {
                        r.classList.remove('expanded');
                    });
                }
                
                if (!isExpanded) {
                    row.classList.add('expanded');
                }
            }
        });
        row.insertBefore(bar, row.firstChild);
    }
    
    const values = [];
    const inputs = Array.from(row.querySelectorAll('input:not([type="hidden"]), select'));
    inputs.forEach(input => {
        let val = input.value;
        if (val && val.trim() !== '') {
            if (input.tagName === 'SELECT') {
                const selectedOpt = input.options[input.selectedIndex];
                if (selectedOpt && selectedOpt.text) {
                    val = selectedOpt.text;
                }
            }
            if (!values.includes(val)) {
                values.push(val);
            }
        }
    });
    
    let summaryText = values.slice(0, 3).join(' - ');
    if (!summaryText || summaryText.trim() === '') {
        summaryText = 'New Entry (Tap to edit)';
    }
    bar.textContent = summaryText;
}

function initRepeaterAccordions() {
    const handleNewRow = (row) => {
        if (!row.classList.contains('repeater-row')) return;
        updateRepeaterSummary(row);
        row.addEventListener('input', () => updateRepeaterSummary(row));
        row.addEventListener('change', () => updateRepeaterSummary(row));
    };

    document.querySelectorAll('.repeater-accordion .repeater-row').forEach(row => {
        handleNewRow(row);
    });

    document.querySelectorAll('.repeater-accordion').forEach(container => {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach(mutation => {
                mutation.addedNodes.forEach(node => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        if (node.classList.contains('repeater-row')) {
                            handleNewRow(node);
                            if (window.innerWidth <= 768) {
                                container.querySelectorAll('.repeater-row').forEach(r => {
                                    r.classList.remove('expanded');
                                });
                                node.classList.add('expanded');
                            }
                        } else {
                            node.querySelectorAll('.repeater-row').forEach(subRow => {
                                handleNewRow(subRow);
                            });
                        }
                    }
                });
            });
        });
        observer.observe(container, { childList: true, subtree: true });
    });
}

function updatePDSSummary() {
    const getValue = (name, fallback = '<span class="text-muted small">Blank</span>') => {
        const el = document.querySelector(`[name="${name}"]`);
        return el && el.value ? el.value : fallback;
    };
    
    // Core Identity
    const firstName = getValue('first_name');
    const middleName = getValue('middle_name', '');
    const lastName = getValue('last_name');
    const nameExt = getValue('name_extension', '');
    const name = `${firstName} ${middleName ? middleName + ' ' : ''}${lastName}${nameExt ? ' ' + nameExt : ''}`;
    
    const sumNameEl = document.getElementById('sum-name');
    if (sumNameEl) sumNameEl.innerHTML = name;
    
    const sumDobEl = document.getElementById('sum-dob');
    if (sumDobEl) sumDobEl.innerHTML = getValue('date_of_birth');
    
    const sumGenderEl = document.getElementById('sum-gender');
    if (sumGenderEl) sumGenderEl.innerHTML = getValue('gender');
    
    const sumCivilEl = document.getElementById('sum-civil');
    if (sumCivilEl) sumCivilEl.innerHTML = getValue('civil_status');
    
    const sumMobileEl = document.getElementById('sum-mobile');
    if (sumMobileEl) sumMobileEl.innerHTML = getValue('contact_number');
    
    const sumEmailEl = document.getElementById('sum-email');
    if (sumEmailEl) sumEmailEl.innerHTML = getValue('email');
    
    // Government IDs
    const sumSssEl = document.getElementById('sum-sss');
    if (sumSssEl) sumSssEl.innerHTML = getValue('sss_number');
    
    const sumPhilhealthEl = document.getElementById('sum-philhealth');
    if (sumPhilhealthEl) sumPhilhealthEl.innerHTML = getValue('philhealth_number');
    
    const sumPagibigEl = document.getElementById('sum-pagibig');
    if (sumPagibigEl) sumPagibigEl.innerHTML = getValue('pagibig_number');
    
    const sumTinEl = document.getElementById('sum-tin');
    if (sumTinEl) sumTinEl.innerHTML = getValue('tin_number');
    
    // Background (counts)
    const countRepeaterEntries = (containerId) => {
        const container = document.getElementById(containerId);
        return container ? container.querySelectorAll('.repeater-row').length : 0;
    };
    
    const sumChildrenEl = document.getElementById('sum-children');
    if (sumChildrenEl) sumChildrenEl.innerHTML = countRepeaterEntries('childrenContainer') + ' child(ren)';
    
    const sumSiblingsEl = document.getElementById('sum-siblings');
    if (sumSiblingsEl) sumSiblingsEl.innerHTML = countRepeaterEntries('siblingsContainer') + ' sibling(s)';
    
    const sumEducationEl = document.getElementById('sum-education');
    if (sumEducationEl) sumEducationEl.innerHTML = countRepeaterEntries('educationContainer') + ' education entry(ies)';
    
    const sumWorkEl = document.getElementById('sum-work');
    if (sumWorkEl) sumWorkEl.innerHTML = countRepeaterEntries('workContainer') + ' job entry(ies)';
    
    // Qualifications (counts)
    const sumEligEl = document.getElementById('sum-eligibility');
    if (sumEligEl) sumEligEl.innerHTML = countRepeaterEntries('eligibilityContainer') + ' license(s)';
    
    const sumSkillsEl = document.getElementById('sum-skills');
    if (sumSkillsEl) sumSkillsEl.innerHTML = countRepeaterEntries('skillsContainer') + ' skill(s)';
    
    const sumRecEl = document.getElementById('sum-recognitions');
    if (sumRecEl) sumRecEl.innerHTML = countRepeaterEntries('recognitionsContainer') + ' recognition(s)';
    
    const sumPropEl = document.getElementById('sum-properties');
    if (sumPropEl) sumPropEl.innerHTML = (countRepeaterEntries('realPropContainer') + countRepeaterEntries('personalPropContainer')) + ' asset(s)';
    
    // Disclosures count
    let activeDisclosures = 0;
    document.querySelectorAll('#step10 input[type="checkbox"]').forEach(cb => {
        if (cb.checked) activeDisclosures++;
    });
    const sumDiscEl = document.getElementById('sum-disclosures');
    if (sumDiscEl) sumDiscEl.innerHTML = activeDisclosures + ' active declaration(s)';
}

// Copy residential address to permanent with cascading dropdown support
function copyResAddress() {
    // Copy plain text fields first
    var fields = ['house_no', 'street', 'subdivision', 'zip_code'];
    fields.forEach(function (f) {
        var src = document.getElementById('res_' + f) || document.querySelector('[name="res_' + f + '"]');
        var dst = document.getElementById('perm_' + f);
        if (src && dst) dst.value = src.value;
    });

    var resRegion = document.getElementById('res_region');
    var permRegion = document.getElementById('perm_region');
    if (!resRegion || !permRegion || !resRegion.value) return;

    // Step 1: Copy & fire Region → this repopulates Province options
    selectOrAddOption(permRegion, resRegion.value);
    permRegion.dispatchEvent(new Event('change'));

    // Step 2: After Province options are populated, select Province & fire → repopulates City options
    setTimeout(function () {
        var resProv = document.getElementById('res_province');
        var permProv = document.getElementById('perm_province');
        if (!resProv || !permProv || !resProv.value) return;

        selectOrAddOption(permProv, resProv.value);
        permProv.dispatchEvent(new Event('change'));

        // Step 3: After City options are populated, select City & fire → repopulates Barangay + autofills zip
        setTimeout(function () {
            var resCity = document.getElementById('res_city');
            var permCity = document.getElementById('perm_city');
            if (!resCity || !permCity || !resCity.value) return;

            selectOrAddOption(permCity, resCity.value);
            permCity.dispatchEvent(new Event('change'));

            // Step 4: After Barangay options are populated, select Barangay
            setTimeout(function () {
                var resBrgy = document.getElementById('res_barangay');
                var permBrgy = document.getElementById('perm_barangay');
                if (resBrgy && permBrgy && resBrgy.value) {
                    selectOrAddOption(permBrgy, resBrgy.value);
                }

                // Also sync zip from residential (in case city didn't match zip lookup)
                var resZip = document.getElementById('res_zip_code');
                var permZip = document.getElementById('perm_zip_code');
                if (resZip && permZip && resZip.value) {
                    permZip.value = resZip.value;
                }
            }, 100);
        }, 100);
    }, 100);
}


// Toggle disclosure detail areas
function toggleDetails(checkbox, detailsDivId) {
    const div = document.getElementById(detailsDivId);
    if (div) {
        div.classList.toggle('show', checkbox.checked);
    }
}

// Generic repeater: child/sibling (4-column: surname, first, middle, dob)
function addRepeaterRow(containerId, prefix) {
    const c = document.getElementById(containerId);
    const div = document.createElement('div');
    div.className = 'repeater-row';
    div.innerHTML = `
        <button type="button" class="btn-remove-row" onclick="this.closest('.repeater-row').remove()"><i class="fas fa-times"></i></button>
        <div class="row">
            <div class="col-md-3 mb-2"><input type="text" class="form-control form-control-sm" name="${prefix}_surname[]" placeholder="Surname"></div>
            <div class="col-md-3 mb-2"><input type="text" class="form-control form-control-sm" name="${prefix}_first_name[]" placeholder="First Name"></div>
            <div class="col-md-3 mb-2"><input type="text" class="form-control form-control-sm" name="${prefix}_middle_name[]" placeholder="Middle Name"></div>
            <div class="col-md-3 mb-2"><input type="date" class="form-control form-control-sm" name="${prefix}_dob[]"></div>
        </div>`;
    c.appendChild(div);
}

// ── Education helpers ──────────────────────────────────────────────────────
const EDU_LEVELS = ['Elementary','Secondary','Senior High School','Vocational','College','Graduate Studies'];
const EDU_LEVEL_LABELS = {
    'Elementary':        'Elementary',
    'Secondary':         'Secondary / Junior High',
    'Senior High School':'Senior High School',
    'Vocational':        'Vocational / Trade Course',
    'College':           'College',
    'Graduate Studies':  'Graduate Studies'
};
const EDU_LEVEL_ICONS = {
    'Elementary':        'fas fa-school',
    'Secondary':         'fas fa-book',
    'Senior High School':'fas fa-book-open',
    'Vocational':        'fas fa-tools',
    'College':           'fas fa-graduation-cap',
    'Graduate Studies':  'fas fa-user-graduate'
};
const EDU_LEVEL_CSS = {
    'Elementary':        'level-elementary',
    'Secondary':         'level-secondary',
    'Senior High School':'level-shs',
    'Vocational':        'level-vocational',
    'College':           'level-college',
    'Graduate Studies':  'level-graduate'
};

function getExistingEduLevels() {
    return Array.from(document.querySelectorAll('#educationContainer select[name="edu_level[]"]'))
        .map(s => s.value);
}

function getSuggestedEduLevel() {
    const existing = getExistingEduLevels();
    for (const lvl of EDU_LEVELS) {
        if (!existing.includes(lvl)) return lvl;
    }
    return 'College'; // all taken, default to College
}

function updateEduCardBadge(card) {
    const sel = card.querySelector('select[name="edu_level[]"]');
    const badge = card.querySelector('.edu-level-badge');
    const label = card.querySelector('.pds-card-label');
    const lvl = sel ? sel.value : '';
    if (badge && lvl) {
        badge.className = 'edu-level-badge ' + (EDU_LEVEL_CSS[lvl] || '');
        badge.innerHTML = `<i class="${EDU_LEVEL_ICONS[lvl] || 'fas fa-graduation-cap'}"></i> ${EDU_LEVEL_LABELS[lvl] || lvl}`;
    }
    if (label && lvl) label.textContent = EDU_LEVEL_LABELS[lvl] || lvl;
    // Check duplicates
    const existing = getExistingEduLevels();
    const dupWarning = card.querySelector('.edu-duplicate-warning');
    if (dupWarning) {
        const isDup = existing.filter(v => v === lvl).length > 1;
        dupWarning.classList.toggle('show', isDup);
    }
}

function moveCard(btn, direction) {
    const card = btn.closest('.pds-card');
    const container = card.parentElement;
    if (direction === 'up' && card.previousElementSibling) {
        container.insertBefore(card, card.previousElementSibling);
    } else if (direction === 'down' && card.nextElementSibling) {
        container.insertBefore(card.nextElementSibling, card);
    }
}

function buildEduLevelOptions(selected) {
    return EDU_LEVELS.map(lvl =>
        `<option value="${lvl}" ${lvl === selected ? 'selected' : ''}>${EDU_LEVEL_LABELS[lvl]}</option>`
    ).join('');
}

function buildEduCard(selectedLevel) {
    const lvl = selectedLevel || getSuggestedEduLevel();
    const cssClass = EDU_LEVEL_CSS[lvl] || 'level-college';
    const icon = EDU_LEVEL_ICONS[lvl] || 'fas fa-graduation-cap';
    const label = EDU_LEVEL_LABELS[lvl] || lvl;
    const div = document.createElement('div');
    div.className = 'pds-card';
    div.innerHTML = `
        <div class="pds-card-header">
            <div class="pds-card-title">
                <div class="pds-card-icon"><i class="${icon}"></i></div>
                <div>
                    <div class="pds-card-label">${label}</div>
                    <span class="edu-level-badge ${cssClass}"><i class="${icon}"></i> ${label}</span>
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
                    ${buildEduLevelOptions(lvl)}
                </select>
                <div class="edu-duplicate-warning"><i class="fas fa-exclamation-triangle"></i><span>This education level already exists. Are you sure you want to add another record for the same level?</span></div>
            </div>
            <div>
                <label class="pds-field-label">School Name</label>
                <input type="text" class="form-control form-control-sm" name="edu_school[]" placeholder="e.g. University of the Philippines">
            </div>
            <div>
                <label class="pds-field-label">Degree / Course / Strand</label>
                <input type="text" class="form-control form-control-sm" name="edu_degree[]" placeholder="e.g. Bachelor of Science in IT">
            </div>
            <div>
                <label class="pds-field-label">Year From</label>
                <input type="number" class="form-control form-control-sm" name="edu_from[]" min="1900" max="2099" placeholder="e.g. 2015">
            </div>
            <div>
                <label class="pds-field-label">Year To</label>
                <input type="number" class="form-control form-control-sm" name="edu_to[]" min="1900" max="2099" placeholder="e.g. 2019">
            </div>
            <div>
                <label class="pds-field-label">Highest Level / Units Earned</label>
                <input type="text" class="form-control form-control-sm" name="edu_units[]" placeholder="e.g. Completed / 120 units">
            </div>
            <div>
                <label class="pds-field-label">Year Graduated</label>
                <input type="text" class="form-control form-control-sm" name="edu_year_grad[]" placeholder="e.g. 2019 or N/A">
            </div>
            <div class="full-width">
                <label class="pds-field-label">Honors / Awards / Distinctions Received</label>
                <textarea class="form-control form-control-sm" name="edu_honors[]" rows="2" placeholder="e.g. Cum Laude, Dean's List, With Honors"></textarea>
            </div>
        </div>`;
    return div;
}

// Education row
function addEducationRow(selectedLevel) {
    const c = document.getElementById('educationContainer');
    const card = buildEduCard(selectedLevel);
    c.appendChild(card);
    updateEduCardBadge(card);
}

// Work experience row
function addWorkRow() {
    const c = document.getElementById('workContainer');
    const div = document.createElement('div');
    div.className = 'pds-card';
    div.innerHTML = `
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
                <input type="number" class="form-control form-control-sm" name="work_from[]" min="1900" max="2099" placeholder="e.g. 2018">
            </div>
            <div>
                <label class="pds-field-label">Date To (Year or Present)</label>
                <input type="text" class="form-control form-control-sm" name="work_to[]" placeholder="e.g. 2022 or Present">
            </div>
            <div>
                <label class="pds-field-label">Job Title / Position</label>
                <input type="text" class="form-control form-control-sm" name="work_title[]" placeholder="e.g. Software Engineer">
            </div>
            <div>
                <label class="pds-field-label">Company / Employer Name</label>
                <input type="text" class="form-control form-control-sm" name="work_company[]" placeholder="e.g. Raquel Pawnshop">
            </div>
            <div>
                <label class="pds-field-label">Monthly Salary (₱)</label>
                <input type="number" step="0.01" class="form-control form-control-sm" name="work_salary[]" placeholder="e.g. 18000.00">
            </div>
            <div>
                <label class="pds-field-label">Appointment / Employment Status</label>
                <input type="text" class="form-control form-control-sm" name="work_status[]" placeholder="e.g. Regular, Contractual">
            </div>
            <div class="full-width">
                <label class="pds-field-label">Reason for Leaving</label>
                <input type="text" class="form-control form-control-sm" name="work_reason[]" placeholder="e.g. Career growth, Resigned">
            </div>
        </div>`;
    c.appendChild(div);
}

// Training row
function addTrainingRow() {
    const c = document.getElementById('trainingContainer');
    const div = document.createElement('div');
    div.className = 'pds-card training-card';
    div.innerHTML = `
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
                <input type="text" class="form-control form-control-sm" name="training_title[]" placeholder="e.g. Basic Fire Safety Training">
            </div>
            <div>
                <label class="pds-field-label">Date From</label>
                <input type="number" class="form-control form-control-sm" name="training_from[]" min="1900" max="2099" placeholder="Year">
            </div>
            <div>
                <label class="pds-field-label">Date To</label>
                <input type="number" class="form-control form-control-sm" name="training_to[]" min="1900" max="2099" placeholder="Year">
            </div>
            <div>
                <label class="pds-field-label">No. of Hours</label>
                <input type="number" class="form-control form-control-sm" name="training_hours[]" placeholder="e.g. 8">
            </div>
            <div>
                <label class="pds-field-label">Type (L/D/ET/Other)</label>
                <input type="text" class="form-control form-control-sm" name="training_type[]" placeholder="e.g. Leadership, Technical">
            </div>
            <div class="full-width">
                <label class="pds-field-label">Conducted / Sponsored By</label>
                <input type="text" class="form-control form-control-sm" name="training_conducted[]" placeholder="e.g. DOLE, Company HR Dept.">
            </div>
        </div>`;
    c.appendChild(div);
}

// Voluntary work row
function addVoluntaryRow() {
    const c = document.getElementById('voluntaryContainer');
    const div = document.createElement('div');
    div.className = 'pds-card voluntary-card';
    div.innerHTML = `
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
                <input type="text" class="form-control form-control-sm" name="vol_org[]" placeholder="e.g. Red Cross, Barangay Council">
            </div>
            <div>
                <label class="pds-field-label">Organization Address</label>
                <input type="text" class="form-control form-control-sm" name="vol_address[]" placeholder="City/Province">
            </div>
            <div>
                <label class="pds-field-label">Date From</label>
                <input type="number" class="form-control form-control-sm" name="vol_from[]" min="1900" max="2099" placeholder="Year">
            </div>
            <div>
                <label class="pds-field-label">Date To</label>
                <input type="number" class="form-control form-control-sm" name="vol_to[]" min="1900" max="2099" placeholder="Year">
            </div>
            <div>
                <label class="pds-field-label">No. of Hours</label>
                <input type="number" class="form-control form-control-sm" name="vol_hours[]" placeholder="e.g. 40">
            </div>
            <div>
                <label class="pds-field-label">Position / Nature of Work</label>
                <input type="text" class="form-control form-control-sm" name="vol_position[]" placeholder="e.g. Volunteer, Secretary">
            </div>
        </div>`;
    c.appendChild(div);
}

// Eligibility row
function addEligibilityRow() {
    const c = document.getElementById('eligibilityContainer');
    const div = document.createElement('div');
    div.className = 'pds-card eligibility-card';
    div.innerHTML = `
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
                <input type="text" class="form-control form-control-sm" name="elig_title[]" placeholder="e.g. PRC Nurse License, CS Professional">
            </div>
            <div>
                <label class="pds-field-label">License Number</label>
                <input type="text" class="form-control form-control-sm" name="elig_number[]" placeholder="e.g. 0012345">
            </div>
            <div>
                <label class="pds-field-label">Date of Exam</label>
                <input type="date" class="form-control form-control-sm" name="elig_exam_date[]">
            </div>
            <div>
                <label class="pds-field-label">Place of Exam</label>
                <input type="text" class="form-control form-control-sm" name="elig_exam_place[]" placeholder="e.g. Manila, Cebu City">
            </div>
            <div>
                <label class="pds-field-label">Valid From (Year)</label>
                <input type="number" class="form-control form-control-sm" name="elig_from[]" min="1900" max="2099" placeholder="Year">
            </div>
            <div>
                <label class="pds-field-label">Valid To (Year)</label>
                <input type="number" class="form-control form-control-sm" name="elig_to[]" min="1900" max="2099" placeholder="Year or leave blank if lifetime">
            </div>
        </div>`;
    c.appendChild(div);
}

// Recognition row
function addRecognitionRow() {
    const c = document.getElementById('recognitionsContainer');
    const div = document.createElement('div');
    div.className = 'repeater-row';
    div.innerHTML = `
        <button type="button" class="btn-remove-row" onclick="this.closest('.repeater-row').remove()"><i class="fas fa-times"></i></button>
        <div class="row">
            <div class="col-md-4 mb-2"><label class="small text-muted d-block">Award/Recognition Title</label><input type="text" class="form-control form-control-sm" name="recognition_title[]" placeholder="Title"></div>
            <div class="col-md-5 mb-2"><label class="small text-muted d-block">Issued By / Organization</label><input type="text" class="form-control form-control-sm" name="recognition_issued_by[]" placeholder="Organization"></div>
            <div class="col-md-3 mb-2"><label class="small text-muted d-block">Date Received</label><input type="date" class="form-control form-control-sm" name="recognition_date[]"></div>
        </div>`;
    c.appendChild(div);
}

// Simple single-field row (skills, memberships)
function addSimpleRow(containerId, fieldName, placeholder) {
    const c = document.getElementById(containerId);
    const div = document.createElement('div');
    div.className = 'repeater-row';
    div.innerHTML = `
        <button type="button" class="btn-remove-row" onclick="this.closest('.repeater-row').remove()"><i class="fas fa-times"></i></button>
        <input type="text" class="form-control form-control-sm" name="${fieldName}[]" placeholder="${placeholder}">`;
    c.appendChild(div);
}

// Real property row
function addRealPropertyRow() {
    const c = document.getElementById('realPropContainer');
    const div = document.createElement('div');
    div.className = 'pds-card property-card';
    div.innerHTML = `
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
                <input type="text" class="form-control form-control-sm" name="rprop_desc[]" placeholder="e.g. Residential House and Lot">
            </div>
            <div>
                <label class="pds-field-label">Kind</label>
                <input type="text" class="form-control form-control-sm" name="rprop_kind[]" placeholder="e.g. Land, House, Condo">
            </div>
            <div>
                <label class="pds-field-label">Exact Location</label>
                <input type="text" class="form-control form-control-sm" name="rprop_location[]" placeholder="City / Province">
            </div>
            <div>
                <label class="pds-field-label">Assessed Value (₱)</label>
                <input type="number" step="0.01" class="form-control form-control-sm" name="rprop_assessed[]" placeholder="0.00">
            </div>
            <div>
                <label class="pds-field-label">Current Market Value (₱)</label>
                <input type="number" step="0.01" class="form-control form-control-sm" name="rprop_market[]" placeholder="0.00">
            </div>
            <div>
                <label class="pds-field-label">Year &amp; Mode of Acquisition</label>
                <input type="text" class="form-control form-control-sm" name="rprop_acq_mode[]" placeholder="e.g. 2018-Bought, 2020-Inherited">
            </div>
            <div>
                <label class="pds-field-label">Acquisition Cost (₱)</label>
                <input type="number" step="0.01" class="form-control form-control-sm" name="rprop_acq_cost[]" placeholder="0.00">
            </div>
        </div>`;
    c.appendChild(div);
}

// Personal property row
function addPersonalPropertyRow() {
    const c = document.getElementById('personalPropContainer');
    const div = document.createElement('div');
    div.className = 'pds-card property-card';
    div.innerHTML = `
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
                <input type="text" class="form-control form-control-sm" name="pprop_desc[]" placeholder="e.g. Toyota Vios 2019, Gold Necklace">
            </div>
            <div>
                <label class="pds-field-label">Year Acquired</label>
                <input type="text" class="form-control form-control-sm" name="pprop_year[]" placeholder="e.g. 2020">
            </div>
            <div>
                <label class="pds-field-label">Acquisition Cost (₱)</label>
                <input type="number" step="0.01" class="form-control form-control-sm" name="pprop_cost[]" placeholder="0.00">
            </div>
        </div>`;
    c.appendChild(div);
}

// Liability row
function addLiabilityRow() {
    const c = document.getElementById('liabilitiesContainer');
    const div = document.createElement('div');
    div.className = 'pds-card';
    div.innerHTML = `
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
                <input type="text" class="form-control form-control-sm" name="liab_nature[]" placeholder="e.g. Housing Loan, Car Loan">
            </div>
            <div>
                <label class="pds-field-label">Name of Creditor</label>
                <input type="text" class="form-control form-control-sm" name="liab_creditor[]" placeholder="e.g. PNB, SSS">
            </div>
            <div>
                <label class="pds-field-label">Outstanding Balance (₱)</label>
                <input type="number" step="0.01" class="form-control form-control-sm" name="liab_balance[]" placeholder="0.00">
            </div>
        </div>`;
    c.appendChild(div);
}

// Emergency contact row
let _emergencyContactIndex = 0;
function addEmergencyContactRow(isFirst) {
    const c = document.getElementById('emergencyContactsContainer');
    if (!c) return;
    _emergencyContactIndex++;
    const idx = _emergencyContactIndex;
    const isPrimary = isFirst || c.querySelectorAll('.emergency-contact-card').length === 0;
    const div = document.createElement('div');
    div.className = 'emergency-contact-card' + (isPrimary ? ' is-primary-contact' : '');
    div.innerHTML = `
        <div class="emergency-contact-header">
            <label class="emergency-contact-radio">
                <input type="radio" name="emergency_is_primary" value="${idx}" ${isPrimary ? 'checked' : ''}
                    onchange="document.querySelectorAll('.emergency-contact-card').forEach(el=>el.classList.remove('is-primary-contact')); this.closest('.emergency-contact-card').classList.add('is-primary-contact');">
                Set as Primary Contact
                ${isPrimary ? '<span class="emergency-primary-badge ms-2"><i class="fas fa-star"></i> Primary</span>' : ''}
            </label>
            <button type="button" class="pds-card-btn btn-delete" onclick="removeEmergencyContact(this)" title="Remove"><i class="fas fa-trash"></i></button>
        </div>
        <div class="pds-form-grid cols-3">
            <div>
                <label class="pds-field-label">Contact Name</label>
                <input type="text" class="form-control form-control-sm" name="emergency_contact_name[]" placeholder="Full Name">
            </div>
            <div>
                <label class="pds-field-label">Relationship</label>
                <input type="text" class="form-control form-control-sm" name="emergency_contact_relationship[]" placeholder="e.g. Spouse, Parent, Sibling">
            </div>
            <div>
                <label class="pds-field-label">Contact Number</label>
                <input type="text" class="form-control form-control-sm" name="emergency_contact_number[]" placeholder="09XXXXXXXXX" maxlength="11" inputmode="numeric">
            </div>
        </div>`;
    c.appendChild(div);
    // Bind radio change to update primary badge dynamically
    div.querySelector('input[type=radio]').addEventListener('change', function() {
        document.querySelectorAll('.emergency-contact-card').forEach(card => {
            const badge = card.querySelector('.emergency-primary-badge');
            if (badge) badge.remove();
            card.classList.remove('is-primary-contact');
        });
        this.closest('.emergency-contact-card').classList.add('is-primary-contact');
        const label = this.closest('label');
        if (label && !label.querySelector('.emergency-primary-badge')) {
            const b = document.createElement('span');
            b.className = 'emergency-primary-badge ms-2';
            b.innerHTML = '<i class="fas fa-star"></i> Primary';
            label.appendChild(b);
        }
    });
}

function removeEmergencyContact(btn) {
    const card = btn.closest('.emergency-contact-card');
    const wasPrimary = card.classList.contains('is-primary-contact');
    card.remove();
    if (wasPrimary) {
        const first = document.querySelector('.emergency-contact-card');
        if (first) {
            first.classList.add('is-primary-contact');
            const radio = first.querySelector('input[type=radio]');
            if (radio) radio.checked = true;
        }
    }
}

// Profile image preview
function previewImage(input) {
    const preview = document.getElementById('profilePreview');
    const container = document.getElementById('profilePreviewContainer');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            container.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    } else {
        // Only hide if it's completely empty (not even an existing image)
        if (!preview.getAttribute('src')) {
            container.style.display = 'none';
        }
    }
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function normalizeChangeFieldName(name) {
    return (name || '').replace(/\[\]$/, '');
}

function formatFallbackLabel(name) {
    return normalizeChangeFieldName(name)
        .replace(/_/g, ' ')
        .replace(/\b\w/g, char => char.toUpperCase());
}

function getChangeFieldLabel(name) {
    const normalized = normalizeChangeFieldName(name);
    const directLabels = {
        first_name: 'First Name',
        last_name: 'Surname',
        middle_name: 'Middle Name',
        name_extension: 'Name Extension',
        date_of_birth: 'Date of Birth',
        place_of_birth: 'Place of Birth',
        gender: 'Gender',
        civil_status: 'Civil Status',
        height_m: 'Height',
        weight_kg: 'Weight',
        blood_type: 'Blood Type',
        citizenship: 'Citizenship',
        sss_number: 'SSS No.',
        philhealth_number: 'PhilHealth No.',
        pagibig_number: 'Pag-IBIG No.',
        tin_number: 'TIN No.',
        res_house_no: 'Residential House/Block/Lot No.',
        res_street: 'Residential Street',
        res_subdivision: 'Residential Subdivision/Village',
        res_barangay: 'Residential Barangay',
        res_city: 'Residential City/Municipality',
        res_province: 'Residential Province',
        res_zip_code: 'Residential Zip Code',
        perm_house_no: 'Permanent House/Block/Lot No.',
        perm_street: 'Permanent Street',
        perm_subdivision: 'Permanent Subdivision/Village',
        perm_barangay: 'Permanent Barangay',
        perm_city: 'Permanent City/Municipality',
        perm_province: 'Permanent Province',
        perm_zip_code: 'Permanent Zip Code',
        telephone_number: 'Telephone No.',
        contact_number: 'Contact Number',
        email: 'Email Address',
        spouse_surname: 'Spouse Surname',
        spouse_first_name: 'Spouse First Name',
        spouse_middle_name: 'Spouse Middle Name',
        spouse_name_ext: 'Spouse Name Extension',
        spouse_occupation: 'Spouse Occupation',
        father_surname: 'Father Surname',
        father_first_name: 'Father First Name',
        father_middle_name: 'Father Middle Name',
        father_name_ext: 'Father Name Extension',
        father_occupation: 'Father Occupation',
        mother_maiden_surname: 'Mother Maiden Surname',
        mother_first_name: 'Mother First Name',
        mother_middle_name: 'Mother Middle Name',
        mother_occupation: 'Mother Occupation',
        is_related_to_company: 'Related to Company',
        related_details: 'Related to Company Details',
        has_admin_offense: 'Administrative Offense',
        admin_offense_details: 'Administrative Offense Details',
        has_criminal_charge: 'Criminal Charge',
        criminal_charge_details: 'Criminal Charge Details',
        has_criminal_conviction: 'Criminal Conviction',
        criminal_conviction_details: 'Criminal Conviction Details',
        has_been_separated: 'Separated From Service',
        separation_details: 'Separation Details',
        is_pwd: 'PWD Status',
        pwd_details: 'PWD Details',
        is_solo_parent: 'Solo Parent Status',
        solo_parent_details: 'Solo Parent Details',
        has_recent_hospital: 'Recent Hospitalization',
        hospital_details: 'Hospitalization Details',
        has_current_treatment: 'Current Treatment',
        treatment_details: 'Treatment Details',
        hire_date: 'Hire Date',
        job_title: 'Job Title',
        department_id: 'Department',
        rank_category_id: 'Rank',
        branch_id: 'Branch',
        employment_status: 'Employment Status',
        employment_type: 'Employment Type',
        employee_code: 'Company ID',
        is_active: 'Employee Record Status',
        emergency_contact_name: 'Emergency Contact Name',
        emergency_contact_relationship: 'Emergency Contact Relationship',
        emergency_contact_number: 'Emergency Contact Number',
        contract_start_date: 'Contract Start Date',
        contract_end_date: 'Contract End Date',
        profile_picture: 'Profile Picture'
    };

    if (directLabels[normalized]) {
        return directLabels[normalized];
    }

    if (normalized.startsWith('child_')) return 'Children';
    if (normalized.startsWith('sibling_')) return 'Siblings';
    if (normalized.startsWith('edu_')) return 'Education';
    if (normalized.startsWith('work_')) return 'Work Experience';
    if (normalized.startsWith('training_')) return 'Training';
    if (normalized.startsWith('vol_')) return 'Voluntary Work';
    if (normalized.startsWith('elig_')) return 'Eligibility';
    if (normalized.startsWith('skill_')) return 'Skills';
    if (normalized.startsWith('recognition_')) return 'Recognition';
    if (normalized.startsWith('membership_')) return 'Membership';
    if (normalized.startsWith('rprop_')) return 'Real Properties';
    if (normalized.startsWith('pprop_')) return 'Personal Properties';
    if (normalized.startsWith('liab_')) return 'Liabilities';
    if (normalized.startsWith('ref_')) return 'References';

    return formatFallbackLabel(normalized);
}

function shouldIgnoreComparisonField(element) {
    const name = element.name || '';
    const type = (element.type || '').toLowerCase();

    if (!name || element.disabled) return true;
    if (['submit', 'button', 'image', 'reset'].includes(type)) return true;
    if (['current_step', 'return_to', 'quick_save'].includes(name)) return true;

    return false;
}

function getElementComparisonValue(element) {
    const tagName = element.tagName.toLowerCase();
    const type = (element.type || '').toLowerCase();

    if (type === 'checkbox') {
        return {
            raw: element.checked ? '1' : '0',
            display: element.checked ? 'Yes' : 'No'
        };
    }

    if (type === 'radio') {
        return {
            raw: element.checked ? String(element.value || '').trim() : '',
            display: element.checked ? String(element.value || '').trim() : ''
        };
    }

    if (type === 'file') {
        const files = Array.from(element.files || []);
        if (files.length === 0) {
            return { raw: '', display: '' };
        }

        const raw = files
            .map((file) => `${file.name}:${file.size}:${file.lastModified}`)
            .join('|');
        const display = files.map((file) => file.name).join(', ');

        return { raw, display };
    }

    if (tagName === 'select') {
        const selectedOption = element.options[element.selectedIndex];
        const raw = String(element.value || '').trim();
        const display = raw && selectedOption ? String(selectedOption.textContent || '').trim() : '';
        return { raw, display };
    }

    const raw = String(element.value || '').trim();
    return { raw, display: raw };
}

function serializeFormForComparison(form) {
    const result = {};
    const order = [];

    Array.from(form.elements).forEach((element) => {
        if (shouldIgnoreComparisonField(element)) return;

        const name = element.name;
        const label = getChangeFieldLabel(name);
        const isArrayField = name.endsWith('[]');
        const value = getElementComparisonValue(element);

        if (isArrayField) {
            if (!result[name]) {
                result[name] = { label, rawValues: [], displayValues: [] };
                order.push(name);
            }

            if (value.raw !== '') {
                result[name].rawValues.push(value.raw);
                result[name].displayValues.push(value.display || value.raw);
            }
            return;
        }

        if (!Object.prototype.hasOwnProperty.call(result, name)) {
            order.push(name);
        }

        result[name] = {
            label,
            raw: value.raw,
            display: value.display
        };
    });

    Object.keys(result).forEach((name) => {
        if (name.endsWith('[]')) {
            const rawValues = result[name].rawValues || [];
            const displayValues = result[name].displayValues || [];
            result[name] = {
                label: result[name].label,
                raw: rawValues.join(' | '),
                display: displayValues.join(' | ')
            };
        }
    });

    return { values: result, order };
}

function buildChangedFieldList(initialSnapshot, currentSnapshot) {
    const allNames = [...initialSnapshot.order];
    currentSnapshot.order.forEach((name) => {
        if (!allNames.includes(name)) allNames.push(name);
    });

    return allNames.reduce((changes, name) => {
        const before = initialSnapshot.values[name] || { label: getChangeFieldLabel(name), raw: '', display: '' };
        const after = currentSnapshot.values[name] || { label: getChangeFieldLabel(name), raw: '', display: '' };

        if ((before.raw || '') === (after.raw || '')) {
            return changes;
        }

        changes.push({
            label: after.label || before.label || formatFallbackLabel(name),
            from: before.display || 'Blank',
            to: after.display || 'Blank'
        });

        return changes;
    }, []);
}

function getChangeConfirmationModal() {
    let modalElement = document.getElementById('employeeChangeSummaryModal');
    if (!modalElement) {
        modalElement = document.createElement('div');
        modalElement.className = 'modal fade';
        modalElement.id = 'employeeChangeSummaryModal';
        modalElement.tabIndex = -1;
        modalElement.setAttribute('aria-hidden', 'true');
        modalElement.innerHTML = `
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-clipboard-check me-2"></i>Review Changes</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-3">Please review the modified information before applying the update.</p>
                        <div id="employeeChangeSummaryList" class="list-group"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel Changes</button>
                        <button type="button" class="btn btn-primary" id="confirmEmployeeUpdateBtn">Confirm Update</button>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(modalElement);
    }

    return modalElement;
}

// Automatically navigate to the step containing an invalid required field
document.addEventListener("DOMContentLoaded", function () {
    // Initialize repeater accordions for mobile view
    initRepeaterAccordions();

    // Handle URL-based step navigation
    const urlParams = new URLSearchParams(window.location.search);
    const employeeForm = document.getElementById('addEmployeeForm') || document.getElementById('editEmployeeForm') || document.getElementById('pdsWizardForm');
    const urlStep = urlParams.get('step');
    if (urlStep) {
        showStep(parseInt(urlStep));
    } else {
        const stepInput = document.getElementById('currentStepInput');
        const defaultStep = stepInput ? parseInt(stepInput.value, 10) : 1;
        showStep(isNaN(defaultStep) ? 1 : defaultStep);
    }

    if (employeeForm) {
        employeeForm.addEventListener('invalid', function (e) {
            const stepContent = e.target.closest('.step-content');
            if (stepContent) {
                const stepId = stepContent.id;
                const stepNum = parseInt(stepId.replace('step', ''), 10);
                if (!isNaN(stepNum)) {
                    showStep(stepNum);
                }
            }
        }, true); // Use capture phase
    }

    // Toggle contract dates visibility + update labels dynamically based on employment status
    const statusSelect = document.querySelector('select[name="employment_status"]');
    const contractDatesRow = document.getElementById('contractDatesRow');
    if (statusSelect && contractDatesRow) {
        const statusesWithDates = ['OJT', 'Probationary', 'Project Based', 'Project-Based', 'Trainee'];

        // Label maps
        const hireDateLabels = {
            'OJT':           'OJT Start Date',
            'Probationary':  'Probation Start Date',
            'Project Based': 'Project Start Date',
            'Project-Based': 'Project Start Date',
            'Trainee':       'Training Start Date',
        };
        const hireDateHints = {
            'OJT':           'The date this person first joined as an OJT. Not the same as regularization.',
            'Probationary':  'The date this person started their probationary period.',
            'Project Based': 'The date this person started on this project contract.',
            'Project-Based': 'The date this person started on this project contract.',
            'Trainee':       'The date this person began their training engagement.',
        };
        const contractStartLabels = {
            'OJT':           'OJT Period \u2014 Start Date',
            'Probationary':  'Probation Period \u2014 Start Date',
            'Project Based': 'Project Period \u2014 Start Date',
            'Project-Based': 'Project Period \u2014 Start Date',
            'Trainee':       'Training Period \u2014 Start Date',
        };
        const contractEndLabels = {
            'OJT':           'OJT Period \u2014 End Date',
            'Probationary':  'Probation Period \u2014 End Date',
            'Project Based': 'Project Period \u2014 End Date',
            'Project-Based': 'Project Period \u2014 End Date',
            'Trainee':       'Training Period \u2014 End Date',
        };
        const arrangementWords = {
            'OJT':           'ojt',
            'Probationary':  'probation',
            'Project Based': 'project',
            'Project-Based': 'project',
            'Trainee':       'trainee',
        };

        const hireDateLabel      = document.getElementById('hireDateLabel');
        const hireDateHint       = document.getElementById('hireDateHint');
        const contractStartLabel = document.getElementById('contractStartLabel');
        const contractEndLabel   = document.getElementById('contractEndLabel');
        const contractStartInput = document.getElementById('contract_start_date');
        const contractInfoNote   = contractDatesRow.querySelector('.alert-info');

        const checkStatus = () => {
            const status = statusSelect.value;
            const isNonRegular = statusesWithDates.includes(status);

            // Show/hide contract dates row
            contractDatesRow.style.display = isNonRegular ? 'flex' : 'none';

            // Update hire_date label
            if (hireDateLabel) {
                const labelText = hireDateLabels[status] || 'Date Hired';
                hireDateLabel.innerHTML = labelText + ' <span class="text-danger">*</span>';
            }

            // Update hire_date hint
            if (hireDateHint) {
                hireDateHint.textContent = isNonRegular
                    ? (hireDateHints[status] || 'The date this person first engaged with the company.')
                    : 'The official date this employee was hired / regularized.';
            }

            // Update contract date labels
            if (contractStartLabel) {
                const labelText = contractStartLabels[status] || 'Contract Start Date';
                contractStartLabel.innerHTML = labelText + ' <span class="text-danger">*</span>';
            }
            if (contractEndLabel) {
                contractEndLabel.textContent = contractEndLabels[status] || 'Contract End Date';
            }

            // Toggle required on contract start date
            if (contractStartInput) {
                contractStartInput.required = isNonRegular;
            }

            // Update the info note arrangement word
            if (contractInfoNote && isNonRegular) {
                const word = arrangementWords[status] || status.toLowerCase();
                contractInfoNote.innerHTML =
                    '<i class="fas fa-info-circle me-1"></i>' +
                    '<strong>Note:</strong> The <em>engagement date</em> above records when this person first joined the company. ' +
                    'The <em>arrangement period</em> below tracks the duration of their specific <strong>' + word + '</strong> arrangement. ' +
                    'If they are later regularized, this record will be updated via a <strong>Career Movement</strong>.';
            }
        };

        statusSelect.addEventListener('change', checkStatus);
        // Run once on load for edit mode
        checkStatus();
    }

    // Auto-format Government IDs
    const idFormatters = {
        'sss_number': (val) => {
            val = val.replace(/\D/g, '');
            if (val.length > 10) val = val.substring(0, 10);
            if (val.length > 9) return `${val.substring(0, 2)}-${val.substring(2, 9)}-${val.substring(9)}`;
            if (val.length > 2) return `${val.substring(0, 2)}-${val.substring(2)}`;
            return val;
        },
        'philhealth_number': (val) => {
            val = val.replace(/\D/g, '');
            if (val.length > 12) val = val.substring(0, 12);
            if (val.length > 11) return `${val.substring(0, 2)}-${val.substring(2, 11)}-${val.substring(11)}`;
            if (val.length > 2) return `${val.substring(0, 2)}-${val.substring(2)}`;
            return val;
        },
        'pagibig_number': (val) => {
            val = val.replace(/\D/g, '');
            if (val.length > 12) val = val.substring(0, 12);
            let formatted = '';
            if (val.length > 8) formatted = `${val.substring(0, 4)}-${val.substring(4, 8)}-${val.substring(8)}`;
            else if (val.length > 4) formatted = `${val.substring(0, 4)}-${val.substring(4)}`;
            else formatted = val;
            return formatted;
        },
        'tin_number': (val) => {
            val = val.replace(/\D/g, '');
            if (val.length > 12) val = val.substring(0, 12);
            let parts = [];
            for (let i = 0; i < val.length; i += 3) {
                parts.push(val.substring(i, i + 3));
            }
            return parts.join('-');
        },
        'telephone_number': (val) => {
            val = val.replace(/\D/g, '');
            if (val.length > 10) val = val.substring(0, 10);
            if (val.length > 3) {
                return `(${val.substring(0, 3)}) ${val.substring(3, 6)}${val.length > 6 ? '-' + val.substring(6) : ''}`;
            }
            return val;
        },
        'contact_number': (val) => {
            val = val.replace(/\D/g, '');
            if (val.length > 11) val = val.substring(0, 11);
            return val;
        },
        'emergency_contact_number': (val) => {
            val = val.replace(/\D/g, '');
            if (val.length > 11) val = val.substring(0, 11);
            return val;
        }
    };

    Object.keys(idFormatters).forEach(name => {
        const input = document.querySelector(`[name="${name}"]`);
        if (input) {
            input.addEventListener('input', (e) => {
                const cursor = e.target.selectionStart;
                const oldLen = e.target.value.length;
                e.target.value = idFormatters[name](e.target.value);
                const newLen = e.target.value.length;
                let offset = newLen - oldLen;
                e.target.setSelectionRange(cursor + offset, cursor + offset);
            });
        }
    });

    // Auto-normalize Philippine Mobile numbers on blur (e.g. 9998988877 -> 09998988877)
    const normalizeMobileInput = (inputEl) => {
        if (!inputEl) return;
        let val = inputEl.value.trim().replace(/\D/g, '');
        if (val.length === 10 && val.startsWith('9')) {
            inputEl.value = '0' + val;
            inputEl.classList.remove('is-invalid', 'error-pulse');
            const feedback = inputEl.parentElement.querySelector('.invalid-feedback-custom');
            if (feedback) feedback.style.display = 'none';
        } else if (val.length === 12 && val.startsWith('639')) {
            inputEl.value = '0' + val.substring(2);
            inputEl.classList.remove('is-invalid', 'error-pulse');
            const feedback = inputEl.parentElement.querySelector('.invalid-feedback-custom');
            if (feedback) feedback.style.display = 'none';
        }
    };

    const mainContactInput = document.querySelector('[name="contact_number"]');
    if (mainContactInput) {
        mainContactInput.addEventListener('blur', (e) => normalizeMobileInput(e.target));
    }

    document.addEventListener('blur', (e) => {
        if (e.target && e.target.name === 'emergency_contact_number[]') {
            normalizeMobileInput(e.target);
        }
    }, true);

    // Inject validation helper CSS styles
    const validationStyles = document.createElement('style');
    validationStyles.textContent = `
        @keyframes fieldErrorPulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }
        .form-control.is-invalid, .form-select.is-invalid {
            border-color: #dc3545 !important;
            background-color: #fff8f8 !important;
        }
        .form-control.error-pulse, .form-select.error-pulse {
            animation: fieldErrorPulse 1.6s infinite !important;
        }
        .invalid-feedback-custom {
            display: block;
            color: #dc3545;
            background: #fff0f1;
            border: 1px solid #ffccd0;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 0.82rem;
            margin-top: 6px;
            box-shadow: 0 2px 4px rgba(220,53,69,0.08);
        }
    `;
    document.head.appendChild(validationStyles);

    // === AUTO-SAVE DRAFT FEATURE ===
    const isEdit = employeeForm ? employeeForm.dataset.isEdit === 'true' : false;
    const employeeId = isEdit ? (new URLSearchParams(window.location.search)).get('id') : 'new';
    const DRAFT_KEY = `hris_employee_draft_${employeeId}`;
    const initialComparisonSnapshot = (employeeForm && isEdit) ? serializeFormForComparison(employeeForm) : null;
    let allowEditSubmit = false;

    // Intercept native browser invalid events to smoothly focus & scroll to the exact faulty element
    if (employeeForm) {
        employeeForm.addEventListener('invalid', (e) => {
            e.preventDefault();
            const el = e.target;
            const label = getFieldLabel(el);
            let msg = el.validationMessage || `Please check ${label}.`;
            if (el.name === 'contact_number' || el.name === 'emergency_contact_number[]') {
                msg = `Mobile Number must be 11 digits starting with 09 (e.g., 09998988877).`;
            }
            highlightAndFocusInvalidField(el, msg);
        }, true);
    }

    // Run for both Add and Edit pages (exclude PDS Wizard as it uses server-side draft)
    const isPdsWizard = !!document.getElementById('pdsWizardForm');
    if (employeeForm && !isPdsWizard) {
        let isSaving = false;
        let isSubmitting = false;

        const saveDraft = () => {
            const formData = new FormData(employeeForm);
            const data = {};
            formData.forEach((value, key) => {
                // Don't save file inputs
                if (key === 'profile_picture') return;
                
                if (key.endsWith('[]')) {
                    if (!data[key]) data[key] = [];
                    data[key].push(value);
                } else {
                    data[key] = value;
                }
            });

            // Also save current step
            const activeStep = document.querySelector('.step.active');
            data['_active_step'] = activeStep ? activeStep.id.replace('step', '').replace('Label', '') : '1';
            
            localStorage.setItem(DRAFT_KEY, JSON.stringify(data));
        };

        if (isEdit && initialComparisonSnapshot) {
            const modalElement = getChangeConfirmationModal();
            const modalInstance = new bootstrap.Modal(modalElement);
            const summaryList = modalElement.querySelector('#employeeChangeSummaryList');
            const confirmButton = modalElement.querySelector('#confirmEmployeeUpdateBtn');

            employeeForm.addEventListener('submit', (event) => {
                if (allowEditSubmit) return;

                event.preventDefault();

                // Validate all steps before opening modal
                if (!validateAllSteps()) {
                    event.stopPropagation();
                    return;
                }

                const currentSnapshot = serializeFormForComparison(employeeForm);
                const changes = buildChangedFieldList(initialComparisonSnapshot, currentSnapshot);

                if (changes.length === 0) {
                    window.alert('No changes detected.');
                    return;
                }

                summaryList.innerHTML = changes.map(change => `
                    <div class="list-group-item">
                        <div class="fw-bold mb-1">${escapeHtml(change.label)}</div>
                        <div class="small text-muted">${escapeHtml(change.from)} &rarr; ${escapeHtml(change.to)}</div>
                    </div>
                `).join('');

                modalInstance.show();
            });

            confirmButton.addEventListener('click', () => {
                allowEditSubmit = true;
                isSubmitting = true;
                modalInstance.hide();
                HTMLFormElement.prototype.submit.call(employeeForm);
            });
        } else {
            // For Add mode or any submission that doesn't require a review modal
            employeeForm.addEventListener('submit', (event) => {
                if (!validateAllSteps()) {
                    event.preventDefault();
                    event.stopPropagation();
                    return;
                }
                isSubmitting = true;
            });
        }

        // Debounced save
        const debounceSave = () => {
            if (isSaving) return;
            isSaving = true;
            setTimeout(() => {
                saveDraft();
                isSaving = false;
            }, 1000);
        };

        employeeForm.addEventListener('input', debounceSave);
        employeeForm.addEventListener('change', debounceSave);

        // Clear draft if success message is present in URL
        if (urlParams.get('msg') && urlParams.get('msg').toLowerCase().includes('success')) {
            localStorage.removeItem(DRAFT_KEY);
        }

        // Resume draft logic
        const savedDraft = localStorage.getItem(DRAFT_KEY);
        if (savedDraft) {
            const draftData = JSON.parse(savedDraft);
            
            // Show a non-intrusive toast or banner to restore
            const restoreBanner = document.createElement('div');
            restoreBanner.className = 'alert alert-info d-flex justify-content-between align-items-center mb-4 shadow-sm fadeup';
            restoreBanner.style.borderRadius = '12px';
            restoreBanner.innerHTML = `
                <div>
                    <i class="fas fa-magic me-2"></i>
                    <strong>Draft Found!</strong> You have an unsaved session from ${new Date().toLocaleTimeString()}.
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-primary me-2" id="btnRestoreDraft">Restore Draft</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearDraft">Discard</button>
                </div>
            `;
            employeeForm.parentNode.insertBefore(restoreBanner, employeeForm);

            document.getElementById('btnRestoreDraft').addEventListener('click', () => {
                // 1. Create dynamic rows first
                const containers = {
                    'child_first_name[]': ['childrenContainer', 'child'],
                    'sibling_first_name[]': ['siblingsContainer', 'sibling'],
                    'edu_level[]': 'addEducationRow',
                    'work_title[]': 'addWorkRow',
                    'training_title[]': 'addTrainingRow',
                    'vol_org[]': 'addVoluntaryRow',
                    'elig_title[]': 'addEligibilityRow',
                    'skill_name[]': ['skillsContainer', 'skill_name', 'Skill or Hobby'],
                    'recognition_title[]': ['recognitionsContainer', 'recognition_title', 'Award/Recognition'],
                    'membership_org[]': ['membershipsContainer', 'membership_org', 'Organization Name'],
                    'rprop_desc[]': 'addRealPropertyRow',
                    'pprop_desc[]': 'addPersonalPropertyRow',
                    'liab_nature[]': 'addLiabilityRow'
                };

                // Add rows
                Object.entries(containers).forEach(([key, action]) => {
                    if (draftData[key] && Array.isArray(draftData[key])) {
                        // Skip first if it's not a repeater that needs creation? 
                        // Actually all repeaters here start empty in Add mode.
                        for (let i = 0; i < draftData[key].length; i++) {
                            if (typeof action === 'string') window[action]();
                            else if (Array.isArray(action)) addRepeaterRow(action[0], action[1]);
                            // Simple rows use addSimpleRow but I'll skip for now or fix later
                        }
                    }
                });

                // 2. Fill values
                setTimeout(() => {
                    Object.entries(draftData).forEach(([name, value]) => {
                        if (name === '_active_step') return;
                        if (Array.isArray(value)) {
                            const inputs = document.querySelectorAll(`[name="${name}"]`);
                            value.forEach((v, idx) => {
                                if (inputs[idx]) inputs[idx].value = v;
                            });
                        } else {
                            const input = document.querySelector(`[name="${name}"]`);
                            if (input) {
                                if (input.type === 'checkbox') input.checked = !!value;
                                else input.value = value;
                                // Trigger any change events (like for toggleDetails)
                                input.dispatchEvent(new Event('change'));
                            }
                        }
                    });

                    // 3. Go to saved step
                    if (draftData['_active_step']) showStep(parseInt(draftData['_active_step']));
                    
                    restoreBanner.remove();
                }, 100);
            });

            document.getElementById('btnClearDraft').addEventListener('click', () => {
                localStorage.removeItem(DRAFT_KEY);
                restoreBanner.remove();
            });
        }

        // Removed clear draft on submit to prevent data loss on server-side failure
        // The draft will now be cleared only when a success message is detected on the next page load.

        // Unsaved changes warning
        window.onbeforeunload = (e) => {
            if (isSubmitting) return;
            const draft = localStorage.getItem(DRAFT_KEY);
            if (draft) {
                e.preventDefault();
                e.returnValue = '';
            }
        };
    }
});

// ── Job Title → Rank auto-fill ───────────────────────────────────────────────
/**
 * Infer rank_category_id from job title name when data-rank-id is not set.
 * Matches against DB rank_categories defaults:
 *   1 = Executives, 2 = Management Team, 3 = Manager, 4 = Supervisor, 5 = R&F
 */
function inferRankFromTitle(titleText) {
    if (!titleText) return null;
    var t = titleText.toLowerCase();
    if (t.includes('executive') || t.includes('president') || t.includes('ceo') || t.includes('coo') || t.includes('cfo') || t.includes('chief')) {
        return '1'; // Executives
    }
    if (t.includes('director') || t.includes('vp') || t.includes('vice president') || t.includes('general manager')) {
        return '2'; // Management Team
    }
    if (t.includes('manager') || t.includes('head')) {
        return '3'; // Manager
    }
    if (t.includes('supervisor') || t.includes('team lead') || t.includes('team leader') || t.includes('lead')) {
        return '4'; // Supervisor
    }
    return '5'; // Default: Rank & File
}

function applyJobTitleRankAutofill(jobTitleSelect, rankSelect) {
    if (!jobTitleSelect || !rankSelect) return;
    var opt    = jobTitleSelect.options[jobTitleSelect.selectedIndex];
    if (!opt || !opt.value) {
        rankSelect.value = "";
        return;
    }
    // 1. Prefer the data-rank-id from the DB
    var rankId = opt.getAttribute('data-rank-id');
    // 2. Fallback: infer from job title text
    if (!rankId || rankId === '0') {
        var titleText = opt.getAttribute('data-title') || opt.textContent || '';
        rankId = inferRankFromTitle(titleText.trim());
    }
    if (rankId && rankId !== '0') {
        rankSelect.value = rankId;
        // Visual hint: briefly highlight the rank field
        rankSelect.classList.add('border-success');
        setTimeout(function () { rankSelect.classList.remove('border-success'); }, 1500);
    }
}

function bindJobTitleRankAutofill() {
    var jobTitleSelect = document.getElementById('job_title_id');
    var rankSelect     = document.getElementById('rank_category_id');

    if (!jobTitleSelect || !rankSelect) return;

    // Auto-fill on change
    jobTitleSelect.addEventListener('change', function () {
        applyJobTitleRankAutofill(jobTitleSelect, rankSelect);
    });

    // Also fire immediately if a job title is already selected (edit mode / URL step=12)
    if (jobTitleSelect.value) {
        applyJobTitleRankAutofill(jobTitleSelect, rankSelect);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    bindJobTitleRankAutofill();
    initPhAddresses();
});

/**
 * Modern Cascading Address Dropdowns for Philippines (Region -> Province -> City -> Barangay -> Zip)
 */
function initPhAddresses() {
    var regSelects = document.querySelectorAll('.ph-region-select');
    if (!regSelects.length) return;

    var basePath = '';
    if (typeof window.APP_BASE_URL !== 'undefined' && window.APP_BASE_URL) {
        basePath = window.APP_BASE_URL;
    } else if (typeof window.BASE_URL !== 'undefined' && window.BASE_URL) {
        basePath = window.BASE_URL;
    } else if (typeof BASE_URL !== 'undefined' && BASE_URL) {
        basePath = BASE_URL;
    } else {
        var parts = window.location.pathname.split('/').filter(Boolean);
        if (parts.length > 0 && ['manager', 'supervisor', 'admin', 'employee', 'staff'].includes(parts[0])) {
            basePath = '';
        } else if (parts.length > 1 && ['manager', 'supervisor', 'admin', 'employee', 'staff'].includes(parts[1])) {
            basePath = '/' + parts[0];
        }
    }

    var locUrl = basePath + '/assets/data/ph_locations.json';
    var zipUrl = basePath + '/assets/data/zip_codes.json';

    Promise.all([
        fetch(locUrl).then(function (r) { return r.json(); }),
        fetch(zipUrl).then(function (r) { return r.json(); }).catch(function () { return {}; })
    ]).then(function (results) {
        var phData = results[0];
        var zipData = results[1] || {};

        window.PH_LOCATIONS = phData;
        window.ZIP_CODES = zipData;

        ['res_', 'perm_'].forEach(function (prefix) {
            setupAddressBlock(prefix, phData, zipData);
        });
    }).catch(function (err) {
        console.error('Failed to load PH address dataset:', err);
    });
}

function setupAddressBlock(prefix, phData, zipData) {
    var regEl  = document.getElementById(prefix + 'region');
    var provEl = document.getElementById(prefix + 'province');
    var cityEl = document.getElementById(prefix + 'city');
    var brgyEl = document.getElementById(prefix + 'barangay');
    var zipEl  = document.getElementById(prefix + 'zip_code');

    if (!regEl || !provEl || !cityEl || !brgyEl) return;

    var savedReg  = regEl.getAttribute('data-saved-value') || '';
    var savedProv = provEl.getAttribute('data-saved-value') || '';
    var savedCity = cityEl.getAttribute('data-saved-value') || '';
    var savedBrgy = brgyEl.getAttribute('data-saved-value') || '';

    // 1. Populate Regions
    regEl.innerHTML = '<option value="">Select Region...</option>';
    phData.regions.forEach(function (r) {
        var opt = document.createElement('option');
        opt.value = r.name;
        opt.textContent = r.name + (r.regionName && r.regionName !== r.name ? ' (' + r.regionName + ')' : '');
        opt.setAttribute('data-code', r.code);
        regEl.appendChild(opt);
    });

    // Region change listener
    regEl.addEventListener('change', function () {
        var selOpt = regEl.options[regEl.selectedIndex];
        var regCode = selOpt ? selOpt.getAttribute('data-code') : '';

        provEl.innerHTML = '<option value="">Select Province...</option>';
        cityEl.innerHTML = '<option value="">Select City/Municipality...</option>';
        brgyEl.innerHTML = '<option value="">Select Barangay...</option>';

        provEl.disabled = true;
        cityEl.disabled = true;
        brgyEl.disabled = true;

        if (!regCode) return;

        // Populate Provinces for region
        var filteredProvs = phData.provinces.filter(function (p) {
            return p.regionCode === regCode;
        });

        if (filteredProvs.length === 0 && regCode === '130000000') {
            filteredProvs = [{ code: '130000000', name: 'Metro Manila (NCR)', regionCode: '130000000' }];
        }

        filteredProvs.forEach(function (p) {
            var opt = document.createElement('option');
            opt.value = p.name;
            opt.textContent = p.name;
            opt.setAttribute('data-code', p.code);
            provEl.appendChild(opt);
        });

        provEl.disabled = false;
    });

    // Province change listener
    provEl.addEventListener('change', function () {
        var selOpt = provEl.options[provEl.selectedIndex];
        var provCode = selOpt ? selOpt.getAttribute('data-code') : '';
        var selRegOpt = regEl.options[regEl.selectedIndex];
        var regCode = selRegOpt ? selRegOpt.getAttribute('data-code') : '';

        cityEl.innerHTML = '<option value="">Select City/Municipality...</option>';
        brgyEl.innerHTML = '<option value="">Select Barangay...</option>';

        cityEl.disabled = true;
        brgyEl.disabled = true;

        if (!provCode && !regCode) return;

        var filteredCities = phData.cities.filter(function (c) {
            if (provCode) return c.provinceCode === provCode;
            if (regCode) return c.regionCode === regCode;
            return false;
        });

        // Sort cities alphabetically
        filteredCities.sort(function (a, b) { return a.name.localeCompare(b.name); });

        filteredCities.forEach(function (c) {
            var opt = document.createElement('option');
            opt.value = c.name;
            opt.textContent = c.name;
            opt.setAttribute('data-code', c.code);
            cityEl.appendChild(opt);
        });

        cityEl.disabled = false;
    });

    // City change listener
    cityEl.addEventListener('change', function () {
        var selOpt = cityEl.options[cityEl.selectedIndex];
        var cityCode = selOpt ? selOpt.getAttribute('data-code') : '';
        var cityName = selOpt ? selOpt.value : '';

        brgyEl.innerHTML = '<option value="">Select Barangay...</option>';
        brgyEl.disabled = true;

        if (cityName && zipEl && zipData[cityName]) {
            zipEl.value = zipData[cityName];
        }

        if (!cityCode) return;

        var brgyList = phData.barangays[cityCode] || [];
        brgyList.forEach(function (bName) {
            var opt = document.createElement('option');
            opt.value = bName;
            opt.textContent = bName;
            brgyEl.appendChild(opt);
        });

        brgyEl.disabled = false;
    });

    // 2. Edit Mode Pre-fill Execution
    if (savedReg || savedProv || savedCity || savedBrgy) {
        selectOrAddOption(regEl, savedReg);
        if (regEl.value) {
            regEl.dispatchEvent(new Event('change'));

            if (savedProv) {
                selectOrAddOption(provEl, savedProv);
                if (provEl.value) {
                    provEl.dispatchEvent(new Event('change'));

                    if (savedCity) {
                        selectOrAddOption(cityEl, savedCity);
                        if (cityEl.value) {
                            cityEl.dispatchEvent(new Event('change'));

                            if (savedBrgy) {
                                selectOrAddOption(brgyEl, savedBrgy);
                            }
                        }
                    }
                }
            }
        }
    }
}

function selectOrAddOption(selectEl, val) {
    if (!val || !selectEl) return;
    var cleanVal = val.trim();

    // Check exact or case-insensitive match
    for (var i = 0; i < selectEl.options.length; i++) {
        var optVal = selectEl.options[i].value.trim();
        if (optVal.toLowerCase() === cleanVal.toLowerCase()) {
            selectEl.selectedIndex = i;
            return;
        }
    }

    // Fallback for custom legacy values: append option as selected
    var newOpt = document.createElement('option');
    newOpt.value = cleanVal;
    newOpt.textContent = cleanVal + ' (Legacy)';
    newOpt.selected = true;
    selectEl.appendChild(newOpt);
    selectEl.value = cleanVal;
}

