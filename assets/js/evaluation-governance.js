document.addEventListener('DOMContentLoaded', function () {
    var roleSelect = document.getElementById('governance-type');
    var deptSelect = document.getElementById('governance-department');
    var userSelect = document.getElementById('governance-user');
    var deptHint   = document.getElementById('deptHint');
    var deptRequired = document.getElementById('deptRequired');
    var assignSection = document.getElementById('assignSection');

    if (!roleSelect || !deptSelect || !userSelect) return;

    // ─── Global quick assign trigger from cards ──────────────────────────────
    window.selectGovernanceRole = function (roleName, deptId) {
        // Switch to the Assign Routing Official tab if Bootstrap tabs are present
        var assignTabBtn = document.getElementById('tab-assign-btn');
        if (assignTabBtn) {
            if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                var tabInstance = bootstrap.Tab.getOrCreateInstance(assignTabBtn);
                tabInstance.show();
            } else {
                assignTabBtn.click();
            }
        }

        if (roleSelect) {
            roleSelect.value = roleName;
            onRoleChange();
        }
        if (deptId && deptSelect) {
            deptSelect.value = String(deptId);
        }

        if (assignSection) {
            assignSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            assignSection.classList.add('border-primary');
            setTimeout(function () {
                assignSection.classList.remove('border-primary');
            }, 1800);
        }

        setTimeout(function () {
            if (userSelect) userSelect.focus();
        }, 400);
    };

    // ─── Role → Department lock/unlock & Smart Suggestion ────────────────────
    function onRoleChange() {
        var role = roleSelect.value;
        var isDivisionVP = role === 'Division VP';

        // For corporate-level roles, lock department to 0 (All Departments)
        if (!isDivisionVP) {
            deptSelect.value = '0';
            deptSelect.disabled = true;
            if (deptHint) deptHint.textContent = 'Company-wide (applies to all department evaluations).';
            if (deptRequired) deptRequired.style.display = 'none';
        } else {
            deptSelect.disabled = false;
            if (deptHint) deptHint.textContent = 'Required: choose which department this Division VP signs off for.';
            if (deptRequired) deptRequired.style.display = '';
        }

        // Try to auto-select matching suggested candidate
        filterAndSuggestUsers(role);
    }

    // ─── Filter & Auto-Select matching official if found ─────────────────────
    function filterAndSuggestUsers(selectedRole) {
        var options = Array.prototype.slice.call(userSelect.options);
        var autoSelected = false;

        options.forEach(function (opt) {
            if (!opt.value) return;
            var suggestedRole = opt.getAttribute('data-suggested-role') || '';
            
            // If option matches the chosen governance role, highlight it and pre-select if empty
            if (selectedRole && suggestedRole && suggestedRole.toLowerCase() === selectedRole.toLowerCase()) {
                opt.style.fontWeight = 'bold';
                opt.style.color = '#0d6efd';
                if (!autoSelected && userSelect.value === '') {
                    userSelect.value = opt.value;
                    autoSelected = true;
                }
            } else {
                opt.style.fontWeight = 'normal';
                opt.style.color = '';
            }
        });
    }

    roleSelect.addEventListener('change', onRoleChange);
    if (deptSelect) {
        deptSelect.addEventListener('change', function () {
            // keep selection valid
        });
    }

    // Init on page load
    onRoleChange();
});
