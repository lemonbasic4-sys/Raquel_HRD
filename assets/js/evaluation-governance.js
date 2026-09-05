document.addEventListener('DOMContentLoaded', function () {
    var roleSelect = document.getElementById('governance-type');
    var deptSelect = document.getElementById('governance-department');
    var userSelect = document.getElementById('governance-user');
    var deptHint   = document.getElementById('deptHint');
    var deptRequired = document.getElementById('deptRequired');

    if (!roleSelect || !deptSelect || !userSelect) return;

    // ─── Role → Department lock/unlock ───────────────────────────────────────
    function onRoleChange() {
        var role = roleSelect.value;
        var isDivisionVP = role === 'Division VP';

        // For corporate-level roles, lock department to 0 (All Departments)
        if (!isDivisionVP) {
            deptSelect.value = '0';
            deptSelect.disabled = true;
            if (deptHint)   deptHint.textContent   = 'Company-wide \u2014 no department filter.';
            if (deptRequired) deptRequired.style.display = 'none';
        } else {
            deptSelect.disabled = false;
            if (deptHint)   deptHint.textContent   = 'Required for Division VP roles.';
            if (deptRequired) deptRequired.style.display = '';
        }

        // Reset user select and re-filter
        userSelect.value = '';
        filterUsers();
    }

    // ─── Department + Role → User filter ────────────────────────────────────
    function filterUsers() {
        var selectedDept = deptSelect.value;
        var role         = roleSelect.value;
        var isDivisionVP = role === 'Division VP';

        userSelect.value = '';
        var options = Array.prototype.slice.call(userSelect.options);
        var currentHeader = null;
        var currentHeaderHasVisible = false;

        options.forEach(function (option) {
            if (!option.value && !option.disabled) return; // placeholder

            if (option.disabled) {
                // Rank group header
                if (currentHeader) currentHeader.hidden = !currentHeaderHasVisible;
                currentHeader = option;
                currentHeaderHasVisible = false;
            } else {
                var deptMatch = true;
                if (isDivisionVP && selectedDept && selectedDept !== '0') {
                    // For Division VP: optionally filter by matching department
                    // but allow all so HR can cross-assign (e.g. OPS-VP to HR dept)
                    // — no filter here, just show all active users
                    deptMatch = true;
                }
                option.hidden = !deptMatch;
                if (deptMatch) currentHeaderHasVisible = true;
            }
        });

        // Finalize last group
        if (currentHeader) currentHeader.hidden = !currentHeaderHasVisible;
    }

    roleSelect.addEventListener('change', onRoleChange);
    deptSelect.addEventListener('change', filterUsers);

    // Init on page load
    onRoleChange();
});
