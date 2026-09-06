document.addEventListener('DOMContentLoaded', function () {
    var roleSelect        = document.getElementById('governance-type');
    var deptSelect        = document.getElementById('governance-department');
    var empSelect         = document.getElementById('governance-user');
    var departmentCol     = document.getElementById('departmentCol');
    var employeeStepBadge = document.getElementById('employeeStepBadge');
    var assignSection     = document.getElementById('assignSection');

    if (!roleSelect || !empSelect) return;

    // ─── Initialise Tom Select on the employee dropdown ──────────────────────
    var tomEmp = new TomSelect('#governance-user', {
        placeholder:    'Search by name or job title…',
        dropdownParent: 'body',            // Appends dropdown to body to escape container-fluid and package-card clipping
        searchField:    ['text'],          // searches the option text
        maxOptions:     null,              // show all matches
        allowEmptyOption: true,
        render: {
            option: function (data, escape) {
                // data.text is the raw option text
                var alreadyAttr = data.$option ? data.$option.getAttribute('data-already') : null;
                var alreadyHtml = alreadyAttr
                    ? '<span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1" style="font-size:.68rem;">Already assigned</span>'
                    : '';
                return '<div class="d-flex align-items-center gap-2 py-1">'
                    + '<span style="width:28px;height:28px;border-radius:50%;background:#e0f2fe;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">'
                    + '<i class="fas fa-user text-primary" style="font-size:.7rem;"></i></span>'
                    + '<span class="text-truncate">' + escape(data.text) + alreadyHtml + '</span>'
                    + '</div>';
            },
            item: function (data, escape) {
                return '<div>' + escape(data.text) + '</div>';
            },
            no_results: function () {
                return '<div class="no-results px-3 py-2 text-muted small">No employees found.</div>';
            }
        },
        onInitialize: function () {
            // Style the Tom Select wrapper to match Bootstrap form-select height
            var wrapper = this.wrapper;
            if (wrapper) {
                wrapper.style.minHeight = '38px';
            }
        }
    });

    // ─── Quick-assign from corp/VP cards ────────────────────────────────────
    window.selectGovernanceRole = function (roleName, deptId) {
        var assignTabBtn = document.getElementById('tab-assign-btn');
        if (assignTabBtn) {
            if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                bootstrap.Tab.getOrCreateInstance(assignTabBtn).show();
            } else {
                assignTabBtn.click();
            }
        }
        roleSelect.value = roleName;
        if (deptId && deptSelect) deptSelect.value = String(deptId);
        onRoleChange();

        if (assignSection) {
            assignSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            assignSection.classList.add('border-primary');
            setTimeout(function () { assignSection.classList.remove('border-primary'); }, 1800);
        }
        setTimeout(function () { tomEmp.focus(); }, 450);
    };

    // ─── Filter & populate Employee options based on Role & Department ──────
    function filterEmployeeOptions() {
        var role         = roleSelect.value;
        var selectedDept = parseInt(deptSelect.value, 10) || 0;
        var isDivisionVP = role === 'Division VP';
        var isCorporate  = ['President', 'Audit Committee', 'Board of Directors'].indexOf(role) !== -1;

        // Reset employee selection
        tomEmp.clear();
        tomEmp.clearOptions();

        var rawOptions = Array.prototype.slice.call(empSelect.querySelectorAll('option'));
        var validOptions = [];

        rawOptions.forEach(function (opt) {
            if (!opt.value) return;

            var assignedRole   = (opt.getAttribute('data-assigned-role') || '').toLowerCase();
            var suggestedRole  = (opt.getAttribute('data-suggested-role') || '').toLowerCase();
            var deptId         = parseInt(opt.getAttribute('data-department-id'), 10) || 0;
            var deptName       = (opt.getAttribute('data-department-name') || '').toLowerCase();
            var jobTitle       = (opt.getAttribute('data-job-title') || '').toLowerCase();
            var rankName       = (opt.getAttribute('data-rank-name') || '').toLowerCase();
            var rankCategoryId = parseInt(opt.getAttribute('data-rank-category-id'), 10) || 0;
            var currentRoleLow = role.toLowerCase();

            // Rank & File (R&F) check
            var isRankAndFile = (rankCategoryId === 5)
                || (rankName.indexOf('rank and file') !== -1)
                || (rankName.indexOf('rank & file') !== -1)
                || (jobTitle.indexOf('staff') !== -1 && jobTitle.indexOf('chief of staff') === -1);

            // 1. Corporate single-person conflict check:
            // Skip employees already in a DIFFERENT corporate role
            if (isCorporate && assignedRole && assignedRole !== currentRoleLow) {
                return;
            }

            // 2. Role-specific department & candidate filtering
            var isMatch = false;

            if (!role) {
                // No role selected yet: show all
                isMatch = true;
            } else if (role === 'President') {
                // Step 5: Filter to Office of the President department, President/CEO title, or detected President role
                isMatch = (suggestedRole === 'president')
                    || deptName.indexOf('president') !== -1
                    || deptId === 10
                    || jobTitle.indexOf('president') !== -1
                    || jobTitle.indexOf('chief executive') !== -1
                    || jobTitle.indexOf('ceo') !== -1;
            } else if (role === 'Division VP') {
                // Step 4: Division VP
                if (selectedDept > 0) {
                    // Specific department selected: match that department OR corporate VPs
                    isMatch = (deptId === selectedDept)
                        || (suggestedRole === 'division vp')
                        || (jobTitle.indexOf('vp') !== -1)
                        || (jobTitle.indexOf('vice president') !== -1);
                } else {
                    // All departments / corporate VP
                    isMatch = (suggestedRole === 'division vp')
                        || (jobTitle.indexOf('vp') !== -1)
                        || (jobTitle.indexOf('vice president') !== -1)
                        || (rankCategoryId === 1)
                        || (rankCategoryId === 3);
                }
            } else if (role === 'Audit Committee') {
                // Step 6: Exclusively Audit Department leadership / members (excluding R&F)
                isMatch = !isRankAndFile && (
                    (deptId === 2)
                    || (deptName.indexOf('audit') !== -1)
                    || (suggestedRole === 'audit committee')
                );
            } else if (role === 'Board of Directors') {
                // Step 7: All available leadership personnel (Supervisors, Managers, VPs, Executives, Board Members) EXCEPT Rank & File (R&F)
                isMatch = !isRankAndFile;
            }

            if (isMatch) {
                validOptions.push(opt);
            }
        });

        // Fallback: Only for roles without strict exclusivity when no candidates are found
        if (validOptions.length === 0 && role && role !== 'Audit Committee' && role !== 'President' && role !== 'Board of Directors') {
            rawOptions.forEach(function (opt) {
                if (!opt.value) return;
                var assignedRole = (opt.getAttribute('data-assigned-role') || '').toLowerCase();
                var rankCategoryId = parseInt(opt.getAttribute('data-rank-category-id'), 10) || 0;
                var rankName = (opt.getAttribute('data-rank-name') || '').toLowerCase();
                var isRF = (rankCategoryId === 5) || (rankName.indexOf('rank and file') !== -1) || (rankName.indexOf('rank & file') !== -1);
                if (isCorporate && isRF) return;
                if (isCorporate && assignedRole && assignedRole !== role.toLowerCase()) return;
                validOptions.push(opt);
            });
        }

        // Add filtered options to Tom Select
        validOptions.forEach(function (opt) {
            tomEmp.addOption({ value: opt.value, text: opt.textContent.trim(), $option: opt });
        });
        tomEmp.refreshOptions(false);

        // Auto-select ONLY when an explicit matching title exists
        var bestCandidateValue = null;
        validOptions.forEach(function (opt) {
            if (bestCandidateValue) return;
            var suggestedRole = (opt.getAttribute('data-suggested-role') || '').toLowerCase();
            var deptId        = parseInt(opt.getAttribute('data-department-id'), 10) || 0;
            var jobTitle      = (opt.getAttribute('data-job-title') || '').toLowerCase();

            if (role === 'President') {
                if (suggestedRole === 'president' || jobTitle.indexOf('president') !== -1 || deptId === 10) {
                    bestCandidateValue = opt.value;
                }
            } else if (role === 'Division VP' && selectedDept > 0) {
                if (deptId === selectedDept && (suggestedRole === 'division vp' || jobTitle.indexOf('vp') !== -1)) {
                    bestCandidateValue = opt.value;
                }
            } else if (role === 'Audit Committee') {
                if (suggestedRole === 'audit committee' || jobTitle.indexOf('audit chair') !== -1 || jobTitle.indexOf('audit manager') !== -1 || jobTitle.indexOf('audit supervisor') !== -1) {
                    bestCandidateValue = opt.value;
                }
            } else if (role === 'Board of Directors') {
                // Only auto-select if explicitly a Board of Directors / Chairman title
                if (suggestedRole === 'board of directors' || jobTitle.indexOf('board of director') !== -1 || jobTitle.indexOf('chairman') !== -1 || jobTitle.indexOf('board chair') !== -1) {
                    bestCandidateValue = opt.value;
                }
            }
        });

        if (bestCandidateValue) {
            tomEmp.setValue(bestCandidateValue, true);
        }
    }

    // ─── Role change ─────────────────────────────────────────────────────────
    function onRoleChange() {
        var role         = roleSelect.value;
        var isDivisionVP = role === 'Division VP';

        // Show / hide Department column
        if (isDivisionVP) {
            departmentCol.classList.remove('d-none');
            deptSelect.disabled = false;
            if (employeeStepBadge) employeeStepBadge.textContent = '3';
        } else {
            departmentCol.classList.add('d-none');
            deptSelect.value    = '0';
            deptSelect.disabled = true;
            if (employeeStepBadge) employeeStepBadge.textContent = '2';
        }

        filterEmployeeOptions();
    }

    roleSelect.addEventListener('change', onRoleChange);
    if (deptSelect) {
        deptSelect.addEventListener('change', filterEmployeeOptions);
    }

    // Initialise
    onRoleChange();
});
