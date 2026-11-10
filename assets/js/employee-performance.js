(() => {
    'use strict';
    const BASE = document.currentScript.dataset.base;
    const byId = id => document.getElementById(id);
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c]));
    const num = (value, digits = 2) => value == null ? '\u2014' : Number(value).toFixed(digits);
    const delta = (current, previous) => current == null || previous == null ? null : Math.round((current - previous) * 100) / 100;
    const signed = value => value == null ? '\u2014' : `${value > 0 ? '+' : ''}${num(value)}`;
    const direction = value => value == null ? 'No comparison yet' : value > 0 ? 'Improved' : value < 0 ? 'Declined' : 'Unchanged';
    const changeClass = value => value > 0 ? 'mp-up' : value < 0 ? 'mp-down' : 'mp-same';
    const level = score => score >= 3.6 ? 'Outstanding' : score >= 2.6 ? 'Exceeds Expectations' : score >= 2 ? 'Meets Expectations' : 'Needs Improvement';
    const changeHtml = value => value == null ? '<span class="mp-muted">Not comparable</span>' : `<span class="mp-change ${changeClass(value)}">${signed(value)} ${value > 0 ? '&#8593;' : value < 0 ? '&#8595;' : '&#8212;'}</span>`;
    let evaluations = [];
    let selectedId;
    let comparisonId;
    let chart;

    function previousCycle(ev) {
        return evaluations[evaluations.indexOf(ev) + 1] ?? null;
    }

    function criterionComparison(row, ev, previous) {
        if (!previous) return {previous:null, change:null, note:'Select another cycle to compare.'};
        if (!ev.details_match || !previous.details_match) return {previous:null, change:null, note:'Detailed ratings need HR verification.'};
        const matches = previous.criteria.filter(r => r.key === row.key && r.basis === row.basis);
        if (matches.length !== 1 || ev.criteria.filter(r => r.key === row.key && r.basis === row.basis).length !== 1) {
            return {previous:null, change:null, note:'Criterion or rating basis changed, is missing, or is duplicated.'};
        }
        const match = matches[0];
        return {previous:match.rating, change:delta(row.rating, match.rating),
            note: Math.abs(match.item_weight - row.item_weight) > .005 || match.section_weight !== row.section_weight
                ? 'Weight changed; this compares ratings only.' : ''};
    }

    function extremes(ev) {
        const rated = ev.details_match ? ev.criteria.filter(r => r.rating != null) : [];
        const high = rated.length ? Math.max(...rated.map(r => r.rating)) : null;
        const low = rated.length ? Math.min(...rated.map(r => r.rating)) : null;
        return {rated, high, low, tied:high === low};
    }

    function renderSummary() {
        const latest = evaluations[0];
        const previous = evaluations[1] ?? null;
        byId('latest-score').textContent = num(latest.total_score);
        byId('latest-level').textContent = latest.performance_level || level(latest.total_score);
        byId('latest-cycle').textContent = latest.cycle_label;
        byId('previous-score').textContent = num(previous?.total_score);
        byId('previous-scale').hidden = !previous;
        byId('previous-level').textContent = previous ? (previous.performance_level || level(previous.total_score)) : 'No previous evaluation';
        byId('previous-cycle').textContent = previous?.cycle_label ?? '';
        const d = delta(latest.total_score, previous?.total_score);
        byId('latest-change').textContent = signed(d);
        byId('latest-change').className = `mp-score ${changeClass(d)}`;
        byId('latest-direction').textContent = direction(d);
        byId('finalized-count').textContent = `${evaluations.length} cycle${evaluations.length === 1 ? '' : 's'}`;
        renderInsights(latest,previous);
    }

    function renderInsights(ev, previous) {
        const {rated,high,low,tied} = extremes(ev);
        if (!rated.length) {
            byId('latest-insights').innerHTML = '<p class="mp-muted">Detailed ratings are unavailable or need HR verification. Open the evaluation details or contact HR.</p>';
            return;
        }
        if (tied) {
            const below = low < 2.6;
            byId('latest-insights').innerHTML = `<div class="mp-highlight"><div class="mp-highlight-label">Consistent ratings</div><p>All rated criteria are ${num(high)} / 4.</p><span class="mp-muted">There is no distinct highest or lowest criterion.</span></div><div class="mp-highlight"><div class="mp-highlight-label mp-focus-label">Next step</div><p>${below ? 'Agree on a development action and review date with your head.' : 'Keep your current strengths and agree on one development goal with your head.'}</p></div>`;
            return;
        }
        const highest = rated.filter(r => r.rating === high);
        const lowest = rated.filter(r => r.rating === low);
        const names = rows => `${rows.slice(0,2).map(r => esc(r.name)).join(', ')}${rows.length > 2 ? ` +${rows.length - 2} more` : ''}`;
        const declining = rated.map(row => ({row,comparison:criterionComparison(row,ev,previous)}))
            .filter(r => r.comparison.change !== null && r.comparison.change < 0).sort((a,b) => a.comparison.change - b.comparison.change);
        byId('latest-insights').innerHTML = `<div class="mp-highlight"><div class="mp-highlight-label">Highest rated</div><p><strong>${names(highest)}</strong> <span class="mp-muted">${num(high)} / 4</span></p></div><div class="mp-highlight"><div class="mp-highlight-label mp-focus-label">Lowest rated</div><p><strong>${names(lowest)}</strong> <span class="mp-muted">${num(low)} / 4</span></p><span class="mp-muted">${low < 2.6 ? 'Agree on a specific improvement action with your head.' : 'A development opportunity; a relative low rating does not mean poor performance.'}</span></div>${declining.length ? `<div class="mp-highlight"><p><strong>${esc(declining[0].row.name)}</strong> declined ${num(-declining[0].comparison.change)} points. Review what changed.</p></div>` : ''}`;
    }

    function updateSelectors() {
        const option = (ev, selected) => `<option value="${ev.evaluation_id}" ${ev.evaluation_id === selected ? 'selected' : ''}>${esc(ev.cycle_label)}</option>`;
        byId('review-cycle').innerHTML = evaluations.map(ev => option(ev,selectedId)).join('');
        byId('compare-cycle').innerHTML = '<option value="">No comparison</option>' + evaluations.filter(ev => ev.evaluation_id !== selectedId).map(ev => option(ev,comparisonId)).join('');
        byId('compare-cycle').value = comparisonId == null ? '' : String(comparisonId);
        byId('compare-cycle').disabled = evaluations.length < 2;
    }

    function renderCriteria(ev, comparison) {
        const column = (cycle, baseline = null) => {
            if (!cycle) return '<section class="mp-evaluation-column"><p>Choose another evaluation on the right.</p></section>';
            let html = `<section class="mp-evaluation-column"><div class="mp-cycle-title"><strong>${esc(cycle.evaluation_type)} evaluation</strong><span>${esc(cycle.cycle_label)}</span><b>Total score: ${num(cycle.total_score)} / 4</b></div>`;
            for (const section of ['KRA', 'Behavior']) {
                const rows = cycle.criteria.filter(row => section === 'KRA' ? row.section === 'KRA' : row.section !== 'KRA');
                html += `<h3 class="mt-4">${section === 'KRA' ? 'Key Result Areas' : 'Core Behaviors & Values'}</h3><table class="mp-simple-ratings"><thead><tr><th>Item</th><th>Self</th><th>${cycle.peer_summary && section !== 'KRA' ? '360 final' : 'Reviewed'}</th></tr></thead><tbody>`;
                html += rows.map((row,index) => {
                    const altered = row.rating != null && row.self_rating != null && Math.abs(row.rating - row.self_rating) > .005;
                    const compared = baseline ? criterionComparison(row, cycle, baseline) : null;
                    const progress = !baseline ? '' : compared.change == null
                        ? '<span class="mp-item-progress mp-same">Not comparable</span>'
                        : `<span class="mp-item-progress ${changeClass(compared.change)}">${compared.change > 0 ? '&#8593; Improved' : compared.change < 0 ? '&#8595; Declined' : '&#8212; No change'} (${signed(compared.change)})</span>`;
                    return `<tr class="${row.rating != null && row.rating < 2.6 ? 'mp-needs-focus' : ''}"><td><span class="mp-item-number">${section} ${index + 1}</span><strong>${esc(row.name)}</strong>${progress}</td><td>${num(row.self_rating)}</td><td><strong>${num(row.rating)}</strong>${altered ? `<small class="${row.rating < row.self_rating ? 'mp-down' : 'mp-up'}">${signed(delta(row.rating,row.self_rating))} adjusted</small>` : (row.rating == null || row.self_rating == null ? '' : '<small class="mp-muted">No review adjustment</small>')}</td></tr>`;
                }).join('');
                html += '</tbody></table>';
            }
            return html + '</section>';
        };
        byId('paired-criteria').innerHTML = column(ev) + column(comparison, ev);
        requestAnimationFrame(alignComparisonRows);
    }

    function alignComparisonRows() {
        const columns = byId('paired-criteria').querySelectorAll('.mp-evaluation-column');
        if (columns.length !== 2) return;
        const pairs = [];
        for (const selector of ['.mp-cycle-title', 'h3', '.mp-simple-ratings']) {
            const left = columns[0].querySelectorAll(selector);
            const right = columns[1].querySelectorAll(selector);
            left.forEach((element, index) => {
                if (!right[index]) return;
                if (selector === '.mp-simple-ratings') {
                    const leftRows = element.querySelectorAll('tr');
                    const rightRows = right[index].querySelectorAll('tr');
                    leftRows.forEach((row, i) => { if (rightRows[i]) pairs.push([row, rightRows[i]]); });
                } else pairs.push([element, right[index]]);
            });
        }
        pairs.forEach(pair => pair.forEach(element => { element.style.height = ''; }));
        const heights = pairs.map(pair => Math.ceil(Math.max(...pair.map(element => element.getBoundingClientRect().height))));
        pairs.forEach((pair, i) => pair.forEach(element => { element.style.height = `${heights[i]}px`; }));
    }
    let comparisonWidth = 0;
    new ResizeObserver(entries => {
        const width = entries[0].contentRect.width;
        if (width !== comparisonWidth) {
            comparisonWidth = width;
            requestAnimationFrame(alignComparisonRows);
        }
    }).observe(byId('paired-criteria'));
    document.fonts.ready.then(alignComparisonRows);

    function renderComparison() {
        const ev = evaluations.find(r => r.evaluation_id === selectedId);
        const previous = evaluations.find(r => r.evaluation_id === comparisonId) ?? null;
        const d = previous ? delta(previous.total_score,ev.total_score) : null;
        byId('comparison-summary').innerHTML = `<span>Reviewed cycle: <strong>${num(ev.total_score)} / 4</strong></span>${previous ? `<span>Comparison: <strong>${num(previous.total_score)} / 4</strong></span><span>${changeHtml(d)} <span class="mp-muted">${direction(d)}</span></span>` : '<span class="mp-muted">Choose another cycle to see changes.</span>'}`;
        const notices = [];
        if (!ev.formula_matches || !ev.details_match || (previous && !previous.details_match)) notices.push('Some detailed ratings need HR verification. Official overall scores are preserved.');
        if (previous && ev.evaluation_type !== previous.evaluation_type) notices.push('Different cycle types: interpret the overall change alongside matching criteria.');
        if (ev.behavior_basis === 'department_shared') notices.push('Behavior ratings below are individual; the overall score uses department shared behavior.');
        if (previous && !ev.criteria.some(r => criterionComparison(r,ev,previous).change !== null)) notices.push('No equivalent criteria are available for this pair.');
        byId('comparison-notice').textContent = notices.join(' ');
        renderCriteria(ev,previous);
    }

    function selectCycle(id) {
        const ev = evaluations.find(r => r.evaluation_id === id);
        if (!ev) return;
        selectedId = id;
        comparisonId = previousCycle(ev)?.evaluation_id ?? null;
        updateSelectors(); renderComparison();
    }

    function activateTab(panelId, focus = false) {
        document.querySelectorAll('.mp-tabs [role="tab"]').forEach(tab => {
            const active = tab.dataset.panel === panelId;
            tab.setAttribute('aria-selected',String(active));
            tab.tabIndex = active ? 0 : -1;
            byId(tab.dataset.panel).hidden = !active;
            if (active && focus) tab.focus();
        });
        if (panelId === 'performance-panel' && chart) chart.resize();
    }

    document.querySelectorAll('.mp-tabs [role="tab"]').forEach((tab,index,tabs) => {
        tab.addEventListener('click',() => activateTab(tab.dataset.panel));
        tab.addEventListener('keydown',event => {
            if (!['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
            activateTab(tabs[next].dataset.panel,true);
        });
    });
    byId('review-cycle').addEventListener('change',event => selectCycle(Number(event.target.value)));
    byId('compare-cycle').addEventListener('change',event => {
        comparisonId = event.target.value ? Number(event.target.value) : null;
        renderComparison();
    });

    function renderChart(data) {
        const chronological = [...evaluations].reverse();
        chart = new Chart(byId('perfTrendChart'), {
            type:'line',data:{labels:data.chart_labels,datasets:[{label:'Finalized score',data:data.chart_scores,
                borderColor:'#244b15',backgroundColor:'rgba(203,161,53,.1)',pointBackgroundColor:'#CBA135',pointBorderColor:'#244b15',pointRadius:5,pointHoverRadius:7,tension:0,fill:true}]},
            options:{responsive:true,maintainAspectRatio:false,
                onClick:(_,points) => {if (!points.length) return;selectCycle(chronological[points[0].index].evaluation_id);byId('comparison-heading').scrollIntoView({behavior:'smooth',block:'start'});},
                plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx => `${num(ctx.raw)} / 4`}}},
                scales:{y:{min:1,max:4,ticks:{stepSize:1},grid:{color:'#edf0e9'}},x:{offset:true,ticks:{autoSkip:true,maxTicksLimit:5,maxRotation:0,font:{size:10},
                    callback:value => {const ev = chronological[Number(value)];return ev ? [ev.evaluation_type,(ev.approved_date || ev.evaluation_period_end).slice(0,10)] : '';}},grid:{display:false}}}}
        });
        byId('perfTrendChart').setAttribute('aria-label',`Finalized evaluation scores: ${data.chart_labels.map((label,i) => `${label}: ${num(data.chart_scores[i])} out of 4`).join('; ')}`);
    }

    function renderDevelopment(data) {
        const pct = data.readiness;
        byId('readinessPct').textContent = `${pct}%`;
        byId('readinessBar').style.width = `${pct}%`;
        byId('readinessLabel').textContent = pct >= 80 ? 'Ready for Advancement' : pct >= 55 ? 'Approaching Readiness' : pct >= 35 ? 'Progressing' : 'Developing';
        const b = data.readiness_breakdown;
        byId('readinessBreakdown').innerHTML = `<p>Evaluation score: ${num(b.evaluation)} / 60<br>Years of service: ${num(b.service)} / 25<br>Evaluation history: ${num(b.history)} / 15</p><p>A planning indicator, not an official promotion decision.</p>`;
        const s = data.latest_score;
        const next = data.next_level;
        byId('goalTrackingContent').innerHTML = `<div class="mp-goal"><div class="d-flex justify-content-between small mb-2"><span>Latest finalized score</span><strong>${num(s)} / 4</strong></div><div class="progress"><div class="progress-bar bg-success" style="width:${Math.max(0,Math.min(100,(s - 1)/3*100))}%"></div></div><p>${data.points_needed > 0 ? `${num(data.points_needed)} more points would reach ${esc(next)} in a future cycle.` : 'Your latest finalized score is Outstanding. Keep building on your strengths.'}</p></div>`;
        const latest = evaluations[0];
        byId('development-plan').innerHTML = latest.development_plans.length ? `<ul>${latest.development_plans.map(p => `<li><strong>${esc(p.improvement_area)}</strong><br>${esc(p.support_needed)} &middot; ${esc(p.time_frame)}</li>`).join('')}</ul>` : '<p class="mp-muted">No development plan was recorded for your latest evaluation. Agree on a specific action and review date with your head.</p>';
    }

    fetch(`${BASE}/employee/ajax/get-my-performance.php`)
        .then(response => { if (!response.ok) throw new Error('Unable to load');return response.json(); })
        .then(data => {
            if (!data.success) throw new Error('Unable to load');
            byId('perfLoadingState').hidden = true;
            if (data.pending_evaluations.length) {
                byId('pendingPerformanceNotice').hidden = false;
                byId('pendingPerformanceNotice').innerHTML = `${data.pending_evaluations.length} approved result(s) are awaiting score completion and are excluded from this view.<details><summary>View pending results</summary><ul>${data.pending_evaluations.map(p => `<li><a href="${BASE}/employee/evaluation-history-view.php?evaluation_id=${p.evaluation_id}">${esc(p.cycle_label)}</a>: ${esc(p.reason)}</li>`).join('')}</ul></details>`;
            }
            if (!data.total_count) {byId('perfEmptyState').hidden = false;return;}
            evaluations = data.evaluations;
            byId('perfContent').hidden = false;
            renderSummary();selectCycle(evaluations[0].evaluation_id);renderDevelopment(data);renderChart(data);
        })
        .catch(() => {
            byId('perfLoadingState').hidden = true;
            byId('perfContent').hidden = true;
            byId('perfEmptyState').hidden = false;
            byId('perfEmptyState').querySelector('h2').textContent = 'Performance data could not be loaded';
            byId('perfEmptyState').querySelector('p').textContent = 'Reload the page to try again. If the problem continues, contact HR.';
        });
})();
