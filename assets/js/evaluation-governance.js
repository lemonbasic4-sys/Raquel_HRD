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
        onRoleChange();
        if (deptId && deptSelect) deptSelect.value = String(deptId);

        if (assignSection) {
            assignSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            assignSection.classList.add('border-primary');
            setTimeout(function () { assignSection.classList.remove('border-primary'); }, 1800);
        }
        setTimeout(function () { tomEmp.focus(); }, 450);
    };

    // ─── Role change ─────────────────────────────────────────────────────────
    function onRoleChange() {
        var role         = roleSelect.value;
        var isDivisionVP = role === 'Division VP';
        var isCorporate  = ['President', 'Audit Committee', 'Board of Directors'].indexOf(role) !== -1;

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

        // Reset employee selection
        tomEmp.clear();
        tomEmp.clearOptions();

        // Rebuild options filtered by role
        var options = Array.prototype.slice.call(empSelect.querySelectorAll('option'));
        options.forEach(function (opt) {
            if (!opt.value) return;
            var assignedRole = opt.getAttribute('data-assigned-role') || '';
            // For corporate roles: skip employees already in a DIFFERENT corporate role
            if (isCorporate && assignedRole && assignedRole.toLowerCase() !== role.toLowerCase()) {
                return;
            }
            tomEmp.addOption({ value: opt.value, text: opt.textContent.trim(), $option: opt });
        });
        tomEmp.refreshOptions(false);

        // Auto-suggest matching candidate
        options.forEach(function (opt) {
            if (!opt.value) return;
            var suggestedRole = opt.getAttribute('data-suggested-role') || '';
            if (role && suggestedRole && suggestedRole.toLowerCase() === role.toLowerCase()) {
                tomEmp.setValue(opt.value, true);
                return;
            }
        });
    }

    roleSelect.addEventListener('change', onRoleChange);

    // Initialise
    onRoleChange();
});
