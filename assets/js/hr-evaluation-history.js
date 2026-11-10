(() => {
    'use strict';
    const state = window.hrHistoryState;
    if (!state) return;
    const manager = state.role === 'HR Manager';
    const ids = manager
        ? {branch:'histBranchFilter', department:'histDeptFilter', template:'histTemplateFilter', evaluation_type:'histEvalTypeFilter', q:'histSearchInput'}
        : {branch:'supervisorBranchFilter', department:'supervisorDeptFilter', template:'supervisorTemplateFilter', evaluation_type:'supervisorEvalTypeFilter', q:'customSearchEval'};
    function navigate(changes, reset = false) {
        const url = new URL(location.href);
        url.search = '';
        const filters = reset ? {category: state.filters.category} : {...state.filters};
        Object.assign(filters, changes);
        for (const [key, value] of Object.entries(filters)) {
            if (value && value !== 'All') url.searchParams.set(key, value);
        }
        location.assign(url.toString());
    }
    function setType(type) { navigate({category:type}, true); }
    function setStatus(status) { navigate({status}); }
    window.histSetType = setType;
    window.setSupervisorHistoryType = setType;
    window.histFilter = setStatus;
    window.filterByStatus = setStatus;

    document.addEventListener('DOMContentLoaded', () => {
        const labels = {branch:'All Branches', department:'All Departments', template:'All Templates', evaluation_type:'All Types'};
        for (const [key, id] of Object.entries(ids)) {
            const control = document.getElementById(id);
            if (!control) continue;
            if (key !== 'q') {
                control.replaceChildren(new Option(labels[key], 'All'), ...state.options[key].map(value => new Option(value, value)));
                control.value = state.filters[key];
                control.addEventListener('change', () => navigate({[key]:control.value}));
            } else {
                control.value = state.filters.q;
                control.addEventListener('keydown', event => {
                    if (event.key === 'Enter') { event.preventDefault(); navigate({q:control.value.trim()}); }
                });
                control.addEventListener('search', () => navigate({q:control.value.trim()}));
                const apply = document.createElement('button');
                apply.type = 'button';
                apply.className = 'btn btn-sm btn-success';
                apply.textContent = 'Search';
                apply.addEventListener('click', () => navigate({q:control.value.trim()}));
                control.parentElement.appendChild(apply);
            }
        }
        document.querySelectorAll('.history-review-tab').forEach(tab => {
            const active = tab.classList.contains(state.filters.category);
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', String(active));
        });
        const headings = manager ? ['historyTypeHeading','historyTypePeriod'] : ['supervisorHistoryHeading','supervisorHistorySubheading'];
        const heading = document.getElementById(headings[0]);
        const subheading = document.getElementById(headings[1]);
        if (heading) heading.textContent = state.filters.category === 'probationary' ? 'Probationary Evaluation' : 'Regular Employee';
        if (subheading) subheading.textContent = state.filters.category === 'probationary' ? 'Initial and Final employee evaluations' : 'Quarterly and Annual team evaluations';
        for (const type of ['probationary','regular']) {
            const counter = document.getElementById((manager ? 'hist' : 'supervisor') + (type === 'probationary' ? 'Probationary' : 'Regular') + 'Count');
            if (counter) counter.textContent = state.counts[type];
        }
        document.querySelectorAll('[data-status-count], [data-history-status-count]').forEach(counter => {
            const status = counter.dataset.statusCount || counter.dataset.historyStatusCount;
            counter.textContent = `(${state.statusCounts[status] || 0})`;
        });
        document.querySelectorAll('.hist-status-pill').forEach(button => {
            button.className = 'hist-status-pill';
            const status = button.querySelector('[data-status-count], [data-history-status-count]');
            if ((status?.dataset.statusCount || status?.dataset.historyStatusCount) === state.filters.status) button.classList.add('active-' + state.filters.status.toLowerCase());
        });
    });

    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-history-detail]');
        if (!button) return;
        const id = button.dataset.historyDetail;
        let modal = document.getElementById('reviewModal' + id);
        if (modal) { bootstrap.Modal.getOrCreateInstance(modal).show(); return; }
        const original = button.innerHTML;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Loading';
        document.getElementById('historyDetailError')?.remove();
        try {
            const url = new URL(location.href);
            url.search = '';
            url.searchParams.set('detail_id', id);
            const response = await fetch(url, {credentials:'same-origin', cache:'no-store', headers:{Accept:'text/html'}});
            if (!response.ok) throw new Error('Details unavailable');
            const html = new DOMParser().parseFromString(await response.text(), 'text/html');
            modal = html.getElementById('reviewModal' + id);
            if (!modal?.classList.contains('history-detail-modal')) throw new Error('Details unavailable');
            document.body.appendChild(modal);
            modal.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element => bootstrap.Tooltip.getOrCreateInstance(element));
            modal.querySelectorAll('.score-input').forEach(input => input.addEventListener('input', () => { if (+input.value > 4) input.value='4.00'; }));
            bootstrap.Modal.getOrCreateInstance(modal).show();
        } catch (error) {
            const alert = document.createElement('div');
            alert.id = 'historyDetailError';
            alert.className = 'alert alert-danger mt-3';
            alert.setAttribute('role', 'alert');
            alert.textContent = 'The evaluation details could not be loaded. Please try View again, or sign in again if your session expired.';
            document.querySelector('.history-table-wrap')?.before(alert);
        } finally {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            button.innerHTML = original;
        }
    });
})();
