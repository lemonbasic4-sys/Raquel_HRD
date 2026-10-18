document.addEventListener('DOMContentLoaded', () => {
    const type = document.getElementById('inputEvalType');
    const toggle = document.getElementById('probationaryTemplateToggle');
    const container = document.getElementById('behaviorContainer');
    let wasAnnual = false;
    function syncAnnual() {
        const annual = type?.value === 'Annual' && !toggle?.checked;
        if (annual && !wasAnnual && container) {
            container.replaceChildren();
            window.annualTemplateCoreValues.forEach(value => addBehavior(value.name, value.kpi));
        }
        wasAnnual = annual;
        document.querySelectorAll('[name="behavior_framework_code"], [name="behavior_framework_version"]').forEach(input => {
            input.closest('.col-md-3')?.classList.toggle('d-none', annual);
            if (annual) { input.required = false; input.disabled = true; }
        });
        if (container) {
            container.querySelectorAll('input').forEach(input => { input.readOnly = annual; });
            container.querySelectorAll('button').forEach(button => { button.disabled = annual; });
            container.closest('.content-card')?.querySelectorAll('button[onclick*="addBehavior"]').forEach(button => { button.disabled = annual; });
        }
        if (annual) {
            const kra = document.getElementById('kraWeight'), core = document.getElementById('behaviorWeight');
            if (kra && core) { kra.value = '80'; core.value = '20'; kra.readOnly = true; core.readOnly = true; updateWeightSplit(); }
        }
    }
    type?.addEventListener('change', syncAnnual);
    toggle?.addEventListener('change', syncAnnual);
    syncAnnual();
});
