// Shared, progressive enhancement for read-only HR history detail modals.
(() => {
    const enhance = modal => {
        if (!modal?.matches('.history-detail-modal') || modal.dataset.historyDesignReady) return;
        const body = modal.querySelector('.modal-body');
        const headings = body && [...body.children].filter(el => el.matches('.section-premium-label'));
        if (!headings?.length) return;
        modal.dataset.historyDesignReady = '1';
        modal.classList.add('hr-history-details');
        const tabs = document.createElement('div');
        tabs.className = 'hr-detail-tabs';
        tabs.setAttribute('role', 'tablist');
        tabs.setAttribute('aria-label', 'Evaluation details');
        const groups = [
            {key:'kra', label:'KRA', icon:'fa-bullseye', title:'Key Result Areas (KRA)', copy:'Performance targets and deliverables for the evaluation period.'},
            {key:'behavior', label:'Behavior', icon:'fa-users', title:'Core Behaviors & Values', copy:'Recorded behavior ratings for this evaluation.'},
            {key:'plan', label:'Development Plan', icon:'fa-seedling', title:'Development Plan', copy:'Recorded development goals, support, and timelines.'},
            {key:'comments', label:'Comments & Decisions', icon:'fa-comment-dots', title:'Comments & Decisions', copy:'Recorded feedback and evaluation decisions.'}
        ];
        const panels = new Map();
        groups.forEach(group => {
            const panel = document.createElement('section');
            panel.className = 'hr-detail-panel';
            panel.id = modal.id + '-detail-' + group.key;
            panel.setAttribute('role','tabpanel');
            const heading = document.createElement('div');
            heading.className = 'hr-detail-panel-heading';
            const title = document.createElement('h3');
            title.textContent = group.title;
            const copy = document.createElement('p');
            copy.textContent = group.copy;
            heading.append(title, copy);
            panel.append(heading);
            panels.set(group.key, panel);
        });
        body.insertBefore(tabs, headings[0]);
        headings.forEach(heading => {
            const text = heading.textContent.toLowerCase();
            const key = /strategic|job requirements|key result/.test(text) ? 'kra' : /behavior|values/.test(text) ? 'behavior' : /development|career growth/.test(text) ? 'plan' : 'comments';
            const panel = panels.get(key);
            const nodes = [heading];
            let next = heading.nextSibling;
            while (next && !(next.nodeType === 1 && next.matches('.section-premium-label'))) {
                nodes.push(next);
                next = next.nextSibling;
            }
            nodes.forEach(node => panel.append(node));
            heading.hidden = true;
            // Preserve the optional Career Growth heading within the plan tab.
            if (/career growth/.test(text)) heading.hidden = false;
        });
        groups.forEach((group, index) => {
            const panel = panels.get(group.key);
            const button = document.createElement('button');
            button.type = 'button';
            button.id = panel.id + '-tab';
            button.setAttribute('role','tab');
            button.setAttribute('aria-controls',panel.id);
            panel.setAttribute('aria-labelledby',button.id);
            const icon = document.createElement('i');
            icon.className = 'fas ' + group.icon;
            icon.setAttribute('aria-hidden','true');
            const label = document.createElement('span');
            label.textContent = group.label;
            button.append(icon,label);
            if (['kra','behavior'].includes(group.key)) {
                const rows = [...panel.querySelectorAll('table:first-of-type tbody tr')].filter(row => !row.matches('.total-row') && row.cells.length > 1 && !row.querySelector('[colspan]'));
                label.textContent += ' (' + rows.length + ')';
                const totalRow = panel.querySelector('tr.total-row');
                if (totalRow) {
                    const score = totalRow.cells[totalRow.cells.length - 1]?.textContent.trim();
                    if (score) {
                        const metric = document.createElement('div');
                        metric.className = 'hr-detail-section-metric';
                        metric.textContent = (group.key === 'kra' ? 'KRA subtotal: ' : 'Behavior score: ') + score + ' / 4.00';
                        panel.querySelector('.hr-detail-panel-heading').append(metric);
                    }
                }
                rows.forEach((row, i) => {
                    const cell = row.cells[0];
                    const title = cell.querySelector('.fw-bold') || cell;
                    const number = document.createElement('span');
                    number.className = 'hr-criterion-number';
                    number.textContent = i + 1;
                    title.prepend(number);
                    const description = cell.querySelector('.text-muted');
                    if (description) {
                        const details = document.createElement('details');
                        details.className = 'hr-criterion-description';
                        const summary = document.createElement('summary');
                        summary.textContent = 'View description';
                        details.append(summary,description);
                        cell.append(details);
                    }
                });
            }
            button.addEventListener('click', () => activate(index));
            button.addEventListener('keydown', event => {
                let target = index;
                if (event.key === 'ArrowRight') target = (index + 1) % groups.length;
                else if (event.key === 'ArrowLeft') target = (index + groups.length - 1) % groups.length;
                else if (event.key === 'Home') target = 0;
                else if (event.key === 'End') target = groups.length - 1;
                else return;
                event.preventDefault();
                activate(target);
                tabs.children[target].focus();
            });
            tabs.append(button);
            if (panel.children.length === 1) {
                const empty = document.createElement('p');
                empty.className = 'text-muted small mb-0';
                empty.textContent = 'No ' + group.label.toLowerCase() + ' recorded for this evaluation.';
                panel.append(empty);
            }
            body.append(panel);
        });
        function activate(index) {
            groups.forEach((group,i) => {
                const button = tabs.children[i];
                button.classList.toggle('active',i === index);
                button.setAttribute('aria-selected',String(i === index));
                button.tabIndex = i === index ? 0 : -1;
                panels.get(group.key).hidden = i !== index;
            });
        }
        activate(0);
    };
    document.addEventListener('show.bs.modal', event => enhance(event.target));
})();
