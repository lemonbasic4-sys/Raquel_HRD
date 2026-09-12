document.addEventListener('DOMContentLoaded', function () {
    const tabs = Array.from(document.querySelectorAll('.employee-information-tab'));
    if (!tabs.length) return;

    const cards = Array.from(document.querySelectorAll('.employee-section-card'));
    cards.forEach(function (card) {
        if (card.hasAttribute('data-profile-panel')) return;

        const label = (card.querySelector('.employee-section-kicker')?.textContent || card.textContent).toLowerCase();
        let panel = 'documents';
        if (label.includes('performance') || label.includes('insights')) panel = 'performance';
        else if (label.includes('personal information')) panel = 'personal';
        else if (label.includes('contact')) panel = 'contact';
        else if (label.includes('education')) panel = 'education';
        else if (label.includes('family')) panel = 'family';
        else if (label.includes('training') || label.includes('work experience') || label.includes('skills')) panel = 'training';
        else if (label.includes('employment') || label.includes('government ids')) panel = 'employment';
        else if (label.includes('audit trail') || label.includes('edit history')) panel = 'timeline';
        card.setAttribute('data-profile-panel', panel);
    });

    const panels = Array.from(document.querySelectorAll('[data-profile-panel]'));

    function getPanelTarget(panel) {
        const parent = panel.parentElement;
        const nestedPanels = parent ? parent.querySelectorAll('[data-profile-panel]') : [];
        return parent && parent.matches('.col-xl-6, .col-12') && nestedPanels.length === 1 ? parent : panel;
    }

    function activate(tab, moveFocus) {
        const selected = tab.getAttribute('data-profile-tab');
        tabs.forEach(function (item) {
            const active = item === tab;
            item.setAttribute('aria-selected', active ? 'true' : 'false');
            item.tabIndex = active ? 0 : -1;
        });
        panels.forEach(function (panel) {
            const target = getPanelTarget(panel);
            const visible = selected === 'all' || panel.getAttribute('data-profile-panel') === selected;
            target.hidden = !visible;
            target.classList.toggle('profile-panel-active', selected !== 'all' && visible);
        });
        if (moveFocus) tab.focus({ preventScroll: true });
    }

    tabs.forEach(function (tab, index) {
        tab.addEventListener('click', function () { activate(tab, false); });
        tab.addEventListener('keydown', function (event) {
            let next = null;
            if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = tabs.length - 1;
            if (next !== null) {
                event.preventDefault();
                tabs[next].click();
                tabs[next].focus({ preventScroll: true });
            }
        });
    });

    const initial = tabs.find(function (tab) {
        return tab.getAttribute('aria-selected') === 'true';
    }) || tabs[0];
    activate(initial, false);
});
