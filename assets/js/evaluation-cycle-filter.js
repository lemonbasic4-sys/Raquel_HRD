// Update the cycle roster in place without reloading the page or executing fetched scripts.
(() => {
    if (window.evaluationCycleFilterReady) return;
    window.evaluationCycleFilterReady = true;
    document.addEventListener('change', async event => {
        const select = event.target.closest('[data-cycle-filter]');
        if (!select || select.disabled) return;
        const kind = select.dataset.cycleFilter;
        const region = select.closest('[data-cycle-results]');
        if (!region) return;
        const form = select.form;
        const url = new URL(form.action || location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        const scrollX = window.scrollX, scrollY = window.scrollY;
        const previousValue = new URL(location.href).searchParams.get('cycle_id') || '0';
        let status = region.querySelector('[data-cycle-filter-status]');
        if (!status) {
            status = document.createElement('p');
            status.dataset.cycleFilterStatus = '';
            status.className = 'small text-muted';
            status.setAttribute('role', 'status');
            form.after(status);
        }
        status.textContent = 'Loading evaluations...';
        select.disabled = true;
        region.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {credentials: 'same-origin'});
            if (!response.ok) throw new Error('Request failed');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const updated = page.querySelector('[data-cycle-results="' + kind + '"]');
            if (!updated) throw new Error('Evaluation results unavailable');
            region.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => window.bootstrap?.Tooltip?.getInstance(el)?.dispose());
            region.replaceWith(updated);
            history.replaceState(history.state, '', url);
            updated.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => window.bootstrap?.Tooltip?.getOrCreateInstance(el));
            requestAnimationFrame(() => window.scrollTo({left: scrollX, top: scrollY, behavior: 'instant'}));
        } catch (error) {
            select.value = previousValue;
            status.className = 'small text-danger';
            status.textContent = 'Could not load evaluations. Please choose the period again to retry.';
        } finally {
            select.disabled = false;
            region.removeAttribute('aria-busy');
        }
    });
    // Delegation keeps the roster search/status working after replacing its HTML.
    const filterRoster = event => {
        const region = event.target.closest('[data-cycle-results]');
        if (!region) return;
        const kind = region.dataset.cycleResults;
        if (![kind + 'Search', kind + 'Status'].includes(event.target.id)) return;
        const text = (region.querySelector('#' + kind + 'Search')?.value || '').toLowerCase();
        const state = region.querySelector('#' + kind + 'Status')?.value || '';
        region.querySelectorAll('#' + kind + 'Roster tbody tr').forEach(row => {
            const matches = state === 'submitted' ? row.dataset[kind + 'Submitted'] === '1' : !state || row.dataset[kind + 'State'] === state;
            row.hidden = !matches || !row.textContent.toLowerCase().includes(text);
        });
    };
    document.addEventListener('input', filterRoster);
    document.addEventListener('change', filterRoster);
})();
