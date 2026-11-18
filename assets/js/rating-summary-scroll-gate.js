document.addEventListener('DOMContentLoaded', () => {
    for (const id of ['quarterlyRatingSummary', 'annualRatingSummary']) {
        const modal = document.getElementById(id);
        if (!modal) continue;
        const body = modal.querySelector('.modal-body');
        const submit = modal.querySelector('[id$="ConfirmSubmit"]');
        if (!body || !submit) continue;

        const mobile = window.matchMedia('(max-width: 650px)');
        const update = () => {
            submit.disabled = mobile.matches && body.scrollHeight > body.clientHeight + 2
                && body.scrollTop + body.clientHeight < body.scrollHeight - 4;
        };
        modal.addEventListener('shown.bs.modal', () => {
            body.scrollTop = 0;
            update();
        });
        body.addEventListener('scroll', update, { passive: true });
        mobile.addEventListener('change', update);
    }
});
