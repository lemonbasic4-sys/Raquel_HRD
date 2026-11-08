(() => {
    'use strict';
    const BASE = document.currentScript.dataset.base;
    const byId = id => document.getElementById(id);
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c]));
    const num = (v, digits = 2) => v === null || v === undefined ? '—' : Number(v).toFixed(digits);
    const signed = v => v === null ? 'No comparison' : `${v > 0 ? '+' : ''}${num(v)}`;
    const date = v => v ? new Date(v.replace(' ', 'T')).toLocaleDateString('en-PH', {year:'numeric', month:'short', day:'numeric'}) : 'Not recorded';
    const change = (a, b) => a == null || b == null ? null : Math.round((a - b) * 100) / 100;
    const changeHtml = delta => `<span class="perf-change ${delta > 0 ? 'text-success' : delta < 0 ? 'text-danger' : 'text-muted'}">${signed(delta)}${delta === null ? '' : delta > 0 ? ' · Improved' : delta < 0 ? ' · Declined' : ' · Unchanged'}</span>`;
    const level = s => s >= 3.6 ? 'Outstanding' : s >= 2.6 ? 'Exceeds Expectations' : s >= 2 ? 'Meets Expectations' : 'Needs Improvement';
    const compatibleCycle = (a, b) => a.evaluation_type === b.evaluation_type ||
        (['Initial','Final'].includes(a.evaluation_type) && ['Initial','Final'].includes(b.evaluation_type));
    let evaluations = [];
    let chart;

    function baselineFor(ev) {
        const index = evaluations.indexOf(ev);
        return evaluations.slice(index + 1).find(previous => compatibleCycle(ev, previous)) ?? null;
    }

    function criterionComparison(row, ev, previous) {
        if (!previous) return {previous:null, delta:null, note:'No earlier comparable cycle'};
        if (!ev.details_match || !previous.details_match) return {previous:null, delta:null, note:'Criterion details need HR verification'};
        const matches = previous.criteria.filter(r => r.key === row.key && r.basis === row.basis);
        const currentMatches = ev.criteria.filter(r => r.key === row.key && r.basis === row.basis);
        if (matches.length !== 1 || currentMatches.length !== 1) {
            return {previous:null, delta:null, note:'Criterion changed, missing, or duplicated'};
        }
        const match = matches[0];
        return {previous:match.rating, delta:change(row.rating, match.rating),
            note: Math.abs(match.item_weight - row.item_weight) > .005 || match.section_weight !== row.section_weight
                ? 'Weight changed; comparison uses the rating only' : ''};
    }

    function renderBreakdown(ev, previous) {
        const target = byId(`cycle-body-${ev.evaluation_id}`);
        const delta = previous ? change(ev.total_score, previous.total_score) : null;
        const components = `<div class="row g-2 mb-3">
            <div class="col-sm-4"><div class="bg-light rounded p-3 small">KRA (${num(ev.kra_weight,0)}%)<strong class="d-block fs-5">${num(ev.kra_subtotal)} / 4</strong>Contribution: ${num(ev.kra_subtotal === null ? null : ev.kra_subtotal * ev.kra_weight / 100, 3)}</div></div>
            <div class="col-sm-4"><div class="bg-light rounded p-3 small">${ev.behavior_basis === 'department_shared' ? 'Department Shared Behavior' : 'Core Behaviors & Values'} (${num(ev.behavior_weight,0)}%)<strong class="d-block fs-5">${num(ev.behavior_average)} / 4</strong>Contribution: ${num(ev.behavior_average === null ? null : ev.behavior_average * ev.behavior_weight / 100, 3)}</div></div>
            <div class="col-sm-4"><div class="bg-light rounded p-3 small">Official overall score<strong class="d-block fs-5">${num(ev.total_score)} / 4</strong>${num(ev.score_percentage)}% of scale maximum</div></div>
        </div>`;
        const formula = ev.formula_matches
            ? `<p class="small text-muted">Official formula: (${num(ev.kra_subtotal)} × ${num(ev.kra_weight,0)}%) + (${num(ev.behavior_average)} × ${num(ev.behavior_weight,0)}%) = <strong>${num(ev.total_score)}</strong>. Contributions are rounded for display; the overall score follows the official rounding rules.</p>`
            : '<div class="alert alert-warning small">The saved official score is shown. Its component totals do not reconcile with the available weights, or component details are missing. Ask HR to verify this record; a replacement score has not been calculated.</div>';
        const detailNotice = ev.details_match ? '' : '<div class="alert alert-warning small">The available criterion ratings do not fully reconcile with the saved component totals. The official overall score remains the saved approved result. Ask HR to check the missing or changed criterion details before using them for development comparisons.</div>';
        const peer = ev.peer_summary ? `<div class="alert alert-success small">Finalized 360 result · ${ev.peer_summary.received}/${ev.peer_summary.expected} required peer submissions · official version ${ev.peer_summary.version}. Behavior uses the approved item averages from that version. Self and peer averages are shown separately; any approved behavior adjustments are already included in the final rating.</div>` : '';
        const shared = ev.behavior_basis === 'department_shared' ? '<div class="alert alert-info small">This cycle applies a department shared behavior score. Individual behavior ratings below describe your review; they are not the department score and do not have individual contributions to the overall result.</div>' : '';
        const options = evaluations.slice(evaluations.indexOf(ev) + 1).filter(p => compatibleCycle(ev,p));
        const comparison = `<label class="small fw-semibold" for="baseline-${ev.evaluation_id}">Compare with an earlier cycle</label>
            <select class="form-select form-select-sm mt-1 mb-2 perf-baseline" id="baseline-${ev.evaluation_id}" data-id="${ev.evaluation_id}">
                <option value="">No comparison</option>${options.map(p => `<option value="${p.evaluation_id}" ${previous?.evaluation_id === p.evaluation_id ? 'selected' : ''}>${esc(p.cycle_label)}</option>`).join('')}
            </select><p class="small">Overall change: ${changeHtml(delta)}${previous ? ` versus ${esc(previous.cycle_label)}. ${previous.evaluation_type !== ev.evaluation_type ? 'Initial and Final are different stages; interpret the overall change alongside the matching criteria.' : ''}` : '. Another comparable finalized cycle is needed.'}</p>`;
        const rated = ev.criteria.filter(r => r.rating !== null);
        const high = rated.length ? Math.max(...rated.map(r => r.rating)) : null;
        const low = rated.length ? Math.min(...rated.map(r => r.rating)) : null;
        const sameRatings = high === low;
        const comparisons = ev.criteria.map(row => ({row, comparison:criterionComparison(row,ev,previous)}));
        const sectionTable = section => {
            const items = comparisons.filter(({row}) => section === 'KRA' ? row.section === 'KRA' : row.section !== 'KRA');
            if (!items.length) return `<p class="small text-muted">No ${section === 'KRA' ? 'KRA' : 'behavior'} criterion ratings were supplied for this record.</p>`;
            return `<div class="table-responsive mb-3" tabindex="0" aria-label="${section} criterion ratings"><table class="table perf-criteria"><thead><tr><th>Criterion</th><th>Item weight</th><th>Self</th>${ev.peer_summary && section !== 'KRA' ? '<th>Peer average</th>' : ''}<th>Final rating / 4</th><th>Weighted score</th><th>Overall contribution</th><th>Previous / 4</th><th>Change</th></tr></thead><tbody>${items.map(({row:r,comparison:c}) => `<tr>
                <td><strong>${esc(r.name)}</strong>${!sameRatings && r.rating === high ? ' <span class="badge bg-success perf-extreme">Highest rating</span>' : ''}${!sameRatings && r.rating === low ? ' <span class="badge bg-warning text-dark perf-extreme">Lowest rating</span>' : ''}<div class="small text-muted criterion-description">${esc(r.description)}</div></td>
                <td>${num(r.item_weight)}%${r.section !== 'KRA' ? '<div class="text-muted small">Equal share</div>' : ''}</td><td>${num(r.self_rating)}</td>
                ${ev.peer_summary && section !== 'KRA' ? `<td>${num(r.peer_average)}</td>` : ''}<td class="fw-bold">${num(r.rating)}</td>
                <td>${num(r.weighted_score,3)}</td><td>${num(r.contribution,3)}</td><td>${num(c.previous)}</td><td>${changeHtml(c.delta)}${c.note ? `<div class="small text-muted">${esc(c.note)}</div>` : ''}</td></tr>`).join('')}</tbody></table></div>`;
        };
        const strengths = ev.details_match && !sameRatings ? rated.filter(r => r.rating === high) : [];
        const focus = comparisons.filter(({row,comparison}) => ev.details_match && row.rating !== null &&
            (row.rating < 2.6 || comparison.delta < 0 || (!sameRatings && row.rating === low)));
        const insights = !ev.details_match ? '<p class="small text-muted">Criterion insights will be reliable once HR verifies the detailed scores for this record.</p>' : `<div class="insight-card p-3 mb-3"><h6 class="fw-bold">Performance insights</h6>
            ${strengths.length ? `<p class="small mb-2"><strong>Highest rated:</strong> ${strengths.map(r => `${esc(r.name)} (${num(r.rating)})`).join(', ')}. Maintain these responsibilities and discuss how to build on them with your head.</p>` : `<p class="small mb-2">${rated.length ? 'All available criteria have the same rating; there is no distinct highest or lowest criterion.' : 'Criterion-level insights are unavailable because this record has no detailed ratings.'}</p>`}
            ${focus.length ? `<ul class="small mb-0">${focus.map(({row:r,comparison:c}) => `<li><strong>${esc(r.name)}:</strong> ${num(r.rating)}/4${c.delta < 0 ? `, down ${num(-c.delta)} points` : ''}. ${r.rating < 2.6 ? 'Agree on a specific improvement action, support, and review date with your head.' : c.delta < 0 ? 'Review what changed since the previous cycle and agree on an action to recover performance.' : 'This is a relative low rating, not necessarily underperformance. Discuss a development goal for the next cycle.'}</li>`).join('')}</ul>` : rated.length ? '<p class="small mb-0">No lower or declining matching criteria were identified in this comparison. Continue the practices behind these ratings.</p>' : ''}
        </div>`;
        const plans = ev.development_plans.length ? `<h6 class="fw-bold">Approved development plan</h6><ul class="small">${ev.development_plans.map(p => `<li><strong>${esc(p.improvement_area)}</strong> · Action/support: ${esc(p.support_needed)} · Time frame: ${esc(p.time_frame)}</li>`).join('')}</ul>` : '';
        target.innerHTML = `<p class="small text-muted">${esc(ev.template_name)}<br>Evaluation period: ${esc(ev.evaluation_period_start)} to ${esc(ev.evaluation_period_end)} · Approved ${esc(date(ev.approved_date))}</p>${components}${formula}${detailNotice}${peer}${shared}${comparison}${insights}<h6 class="fw-bold">Key Result Areas</h6>${sectionTable('KRA')}<h6 class="fw-bold">Behavioral competencies${ev.peer_summary ? ' & 360 ratings' : ''}</h6>${sectionTable('Behavior')}${plans}${ev.supervisor_comments ? `<p class="small"><strong>Head's comments:</strong> ${esc(ev.supervisor_comments)}</p>` : ''}<a class="btn btn-outline-primary btn-sm" href="${BASE}/employee/evaluation-history-view.php?evaluation_id=${ev.evaluation_id}">Open evaluation &amp; audit details</a>`;
    }

    function renderHistory() {
        byId('evalHistoryContainer').innerHTML = evaluations.map((ev,i) => `<details class="perf-cycle" id="cycle-${ev.evaluation_id}" ${i === 0 ? 'open' : ''}><summary><span class="fw-bold">${esc(ev.cycle_label)}</span><span class="d-block small mt-1">${num(ev.total_score)} / 4 · ${esc(ev.performance_level || level(ev.total_score))} · ${num(ev.score_percentage)}% of scale maximum</span></summary><div class="perf-cycle-body" id="cycle-body-${ev.evaluation_id}"></div></details>`).join('');
        evaluations.forEach(ev => renderBreakdown(ev,baselineFor(ev)));
        byId('evalHistoryContainer').addEventListener('change', event => {
            if (!event.target.matches('.perf-baseline')) return;
            const ev = evaluations.find(r => r.evaluation_id === Number(event.target.dataset.id));
            const previous = evaluations.find(r => r.evaluation_id === Number(event.target.value)) ?? null;
            renderBreakdown(ev,previous);
            byId(`baseline-${ev.evaluation_id}`).focus();
        });
    }

    function renderChart(data) {
        chart = new Chart(byId('perfTrendChart'), {
            type:'line', data:{labels:data.chart_labels, datasets:[{label:'Official finalized score',data:data.chart_scores,
                borderColor:'#082E06',backgroundColor:'rgba(203,161,53,.12)',pointBackgroundColor:'#CBA135',pointBorderColor:'#082E06',
                pointRadius:6,pointHoverRadius:8,tension:0,fill:true}]},
            options:{responsive:true,maintainAspectRatio:false,
                onClick:(_,elements) => { if (!elements.length) return; const ev = [...evaluations].reverse()[elements[0].index]; const detail = byId(`cycle-${ev.evaluation_id}`); detail.open = true; detail.scrollIntoView({behavior:'smooth',block:'start'}); },
                plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx => `${num(ctx.raw)} / 4 · ${level(ctx.raw)}`}}},
                scales:{y:{min:1,max:4,ticks:{stepSize:.5}},x:{offset:true,ticks:{maxRotation:45,autoSkip:true},grid:{display:false}}}}
        });
    }

    function renderReadiness(data) {
        const pct = data.readiness;
        byId('readinessPct').textContent = `${pct}%`;
        byId('readinessBar').style.width = `${pct}%`;
        byId('readinessLabel').textContent = pct >= 80 ? 'Ready for Advancement' : pct >= 55 ? 'Approaching Readiness' : pct >= 35 ? 'Progressing' : 'Developing';
        const b = data.readiness_breakdown;
        byId('readinessBreakdown').innerHTML = `<div class="d-flex justify-content-between"><span>Evaluation Score (60%)</span><span>${num(b.evaluation)} / 60</span></div><div class="d-flex justify-content-between"><span>Years of Service (25%)</span><span>${num(b.service)} / 25</span></div><div class="d-flex justify-content-between"><span>Evaluation History (15%)</span><span>${num(b.history)} / 15</span></div><p class="mt-2 mb-0">A planning indicator, not an official promotion decision or evaluation percentage.</p>`;
    }

    function renderGoal(data) {
        const s = data.latest_score;
        const next = s < 2 ? 2 : s < 2.6 ? 2.6 : s < 3.6 ? 3.6 : null;
        byId('goalTrackingContent').innerHTML = `<div class="d-flex justify-content-between small mb-2"><span>Latest finalized score</span><strong>${num(s)} / 4</strong></div><div class="progress goal-progress"><div class="progress-bar bg-success" style="width:${Math.max(0,Math.min(100,(s - 1)/3*100))}%"></div></div><p class="small mt-3 mb-0">${next ? `An increase of ${num(next - s)} points in a future cycle would reach ${esc(level(next))}.` : 'The latest finalized score is Outstanding. Maintain your strengths and review your development goals.'}</p>`;
    }

    fetch(`${BASE}/employee/ajax/get-my-performance.php`)
        .then(response => { if (!response.ok) throw new Error('Unable to load performance data'); return response.json(); })
        .then(data => {
            if (!data.success) throw new Error(data.error || 'Unable to load performance data');
            byId('perfLoadingState').style.display = 'none';
            if (data.pending_evaluations.length) {
                const notice = byId('pendingPerformanceNotice');
                notice.style.display = 'block';
                notice.innerHTML = `<strong>${data.pending_evaluations.length} approved result(s) awaiting score completion</strong><p class="small mb-2">These are excluded from finalized averages, charts, and comparisons.</p><ul class="small mb-0">${data.pending_evaluations.map(p => `<li><a href="${BASE}/employee/evaluation-history-view.php?evaluation_id=${p.evaluation_id}">${esc(p.cycle_label)}</a>: ${esc(p.reason)}</li>`).join('')}</ul>`;
            }
            if (!data.total_count) {
                byId('perfEmptyState').style.display = 'block';
                byId('hero-trend-badge').textContent = 'No finalized data';
                return;
            }
            evaluations = data.evaluations;
            byId('perfContent').style.display = 'block';
            byId('hero-avg-score').textContent = num(data.avg_score);
            const labels = {improving:'Improving',declining:'Declining',stable:'Unchanged',insufficient_data:'One finalized cycle'};
            const label = labels[data.trend];
            ['hero-trend-badge','trendClassBadge'].forEach(id => {
                const badge = byId(id); badge.textContent = label;
                badge.style.cssText = `background:${data.trend === 'declining' ? '#fee2e2' : data.trend === 'improving' ? '#dcfce7' : '#e2e3e5'};color:#1c271b;padding:.3rem .75rem;border-radius:.5rem;`;
            });
            const note = document.createElement('p'); note.className = 'small text-muted mt-3 mb-0';
            note.textContent = data.change.delta === null ? 'A second finalized cycle is needed to measure a change.' : `Latest versus preceding finalized cycle: ${signed(data.change.delta)} points. Click a chart point to open its breakdown. Cycle types and responsibilities can differ; use matching criteria below for development comparisons.`;
            byId('perfTrendChart').parentElement.after(note);
            renderChart(data); renderHistory(); renderReadiness(data); renderGoal(data);
        })
        .catch(() => {
            byId('perfLoadingState').style.display = 'none';
            byId('perfContent').style.display = 'none';
            byId('hero-trend-badge').textContent = 'Unavailable';
            byId('perfEmptyState').style.display = 'block';
            byId('perfEmptyState').querySelector('h5').textContent = 'Performance data could not be loaded';
            byId('perfEmptyState').querySelector('p').textContent = 'Reload the page to try again. If the problem continues, contact HR.';
        });
})();
