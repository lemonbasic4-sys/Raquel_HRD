document.addEventListener('DOMContentLoaded', () => {
    for (const id of ['quarterlyRatingSummary', 'annualRatingSummary']) {
        const modal = document.getElementById(id);
        if (!modal) continue;
        const body = modal.querySelector('.modal-body');
        const submit = modal.querySelector('[id$="ConfirmSubmit"]');
        if (!body || !submit) continue;

        const mobile = window.matchMedia('(max-width: 650px)');
        const originalLabel = submit.innerHTML;
        const hint = document.createElement('div');
        hint.className = 'small text-dark fw-semibold w-100 mt-2';
        hint.setAttribute('role', 'status');
        hint.setAttribute('aria-live', 'polite');
        hint.hidden = true;
        submit.parentElement.appendChild(hint);
        let needsScroll = false;
        let hintAnimation;
        const update = () => {
            needsScroll = mobile.matches && body.scrollHeight > body.clientHeight + 2
                && body.scrollTop + body.clientHeight < body.scrollHeight - 4;
            submit.setAttribute('aria-disabled', String(needsScroll));
            submit.classList.toggle('opacity-75', needsScroll);
            submit.innerHTML = needsScroll ? 'Scroll to bottom to submit' : originalLabel;
            if (!needsScroll) hint.hidden = true;
        };
        submit.addEventListener('click', (event) => {
            if (!needsScroll) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            hint.textContent = 'Scroll to the bottom of the rating summary before you can submit and lock your ratings.';
            hint.hidden = false;
            if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && hint.animate) {
                hintAnimation?.cancel();
                hintAnimation = hint.animate([
                    { transform: 'translateX(0)' },
                    { transform: 'translateX(-5px)' },
                    { transform: 'translateX(5px)' },
                    { transform: 'translateX(-4px)' },
                    { transform: 'translateX(4px)' },
                    { transform: 'translateX(0)' }
                ], { duration: 350, easing: 'ease-in-out' });
            }
        }, true);
        modal.addEventListener('shown.bs.modal', () => {
            body.scrollTop = 0;
            update();
        });
        body.addEventListener('scroll', update, { passive: true });
        mobile.addEventListener('change', update);
    }
});
