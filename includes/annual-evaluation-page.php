<?php
$annual_in_performance_page = $annual_in_performance_page ?? false;
$page_title = $annual_in_performance_page ? 'Performance Evaluation' : 'Annual Evaluations & 360 Ratings';
require_once __DIR__ . '/session-check.php';
ensureAnnualEvaluationSchema($conn);
$actor = annualActor($conn, (int)($_SESSION['user_id'] ?? 0));
if (!$actor || $actor['employment_status'] !== 'Regular') {
    http_response_code(403); exit('Annual evaluations are available to active regular employees.');
}
if ($_SERVER['REQUEST_METHOD']==='GET' && !$annual_in_performance_page
    && (in_array($actor['role'], ['HR Manager','HR Supervisor'], true)
        || basename(dirname($_SERVER['SCRIPT_NAME']))!==annualPortal($actor))) {
    header('Location: '.annualUrl($actor, $_GET ? '?'.http_build_query($_GET) : '')); exit;
}
$view = (string)($_GET['view'] ?? 'list');
$evaluationId = (int)($_GET['evaluation_id'] ?? 0);
$cycleId = (int)($_GET['cycle_id'] ?? 0);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    try {
        if (($_POST['action'] ?? '') === 'rate' && ($_POST['confirmed'] ?? '') === '1') {
            annualSubmitPeers($conn,$actor,$evaluationId,(array)($_POST['scores'] ?? []));
            redirectWith(annualUrl($actor,'?view=peers&cycle_id='.(int)$_POST['cycle_id']),'success','Your 360 ratings were submitted and locked.');
        } elseif (($_POST['action'] ?? '') === 'approve_kra') {
            annualApproveKra($conn,$actor,$evaluationId,(array)($_POST['kra'] ?? []),trim($_POST['kra_remarks'] ?? ''),(string)($_POST['review_token'] ?? ''));
            redirectWith(annualUrl($actor,'?view=review&evaluation_id='.$evaluationId),'success','KRA ratings approved. Annual finalization still requires all expected 360 submissions.');
        } elseif (($_POST['action'] ?? '') === 'finalize') {
            annualFinalize($conn,$actor,$evaluationId,(array)($_POST['kra'] ?? []),trim($_POST['remarks'] ?? ''),(string)($_POST['review_token'] ?? ''));
            redirectWith(annualUrl($actor,'?view=results&evaluation_id='.$evaluationId),'success','Annual evaluation finalized. The approved version was recorded.');
        } else {
            throw new DomainException('Review the rating summary and confirm before submitting.');
        }
    } catch (DomainException $e) { $error=$e->getMessage(); }
      catch (Throwable $e) { error_log('Annual workflow: '.$e->getMessage()); $error='The request could not be completed. Your previous finalized result has been preserved. Please try again.'; }
}
$cycles = annualQuery($conn,"SELECT DISTINCT c.*,d.department_name FROM annual_evaluation_cycles c
    JOIN departments d ON d.department_id=c.department_id JOIN annual_evaluation_members m ON m.cycle_id=c.cycle_id
    WHERE m.employee_id=? OR m.reviewer_position_id=?
    ORDER BY c.period_end DESC,c.cycle_id DESC",[(int)$actor['employee_id'],(int)$actor['job_title_id']]);
if (!$cycleId && $cycles) $cycleId=(int)$cycles[0]['cycle_id'];
$member=null; $named=false; $canFinalize=false;
if ($evaluationId) {
    $member=annualMember($conn,$evaluationId);
    if (!$member || ((int)$member['employee_id']!==(int)$actor['employee_id'] && !annualCanSeeNames($conn,$actor,$member)
        && $view!=='rate')) { http_response_code(403); exit('This Annual evaluation is unavailable to your account.'); }
    $cycleId=(int)$member['cycle_id'];
    $named=annualCanSeeNames($conn,$actor,$member);
    $canFinalize=annualCanFinalize($conn,$actor,$member);
}
$cycle=null;
foreach ($cycles as $c) if ((int)$c['cycle_id']===$cycleId) $cycle=$c;
if ($cycleId && !$cycle) { http_response_code(403); exit('This department cycle is unavailable.'); }
$members=$cycle ? annualQuery($conn,"SELECT m.*,e.first_name,e.last_name,ev.total_score,
        (SELECT COUNT(*) FROM annual_peer_submissions s WHERE s.cycle_id=m.cycle_id AND s.target_employee_id=m.employee_id) AS received,
        (SELECT COUNT(*) FROM annual_peer_submissions s WHERE s.cycle_id=m.cycle_id AND s.target_employee_id=m.employee_id AND s.rater_employee_id=?) AS rated
    FROM annual_evaluation_members m JOIN employees e ON e.employee_id=m.employee_id
    LEFT JOIN evaluations ev ON ev.evaluation_id=m.evaluation_id WHERE m.cycle_id=? AND ".annualRosterEligibilitySql()." ORDER BY e.last_name,e.first_name",
    [(int)$actor['employee_id'],$cycleId]) : [];
$own=array_values(array_filter($members,static fn($m)=>(int)$m['employee_id']===(int)$actor['employee_id']));
if (($view==='rate' || $view==='peers') && (!$own || (int)$cycle['department_id']!==(int)$actor['department_id'])) {
    http_response_code(403); exit('360 ratings are available only within your own department cycle.');
}
if ($view==='rate') {
    if (!$member || !annualCanRate($actor) || !$own[0]['evaluation_id'] || (int)$member['employee_id']===(int)$actor['employee_id']) {
        http_response_code(403); exit('This peer-rating task is unavailable.');
    }
    try { annualAssertSnapshotActive($member); annualAssertSnapshotActive(annualMember($conn,(int)$own[0]['evaluation_id'])); }
    catch (DomainException $e) { http_response_code(403); exit(e($e->getMessage())); }
    $submitted=annualQuery($conn,'SELECT scores_json FROM annual_peer_submissions WHERE cycle_id=? AND rater_employee_id=? AND target_employee_id=?',
        [$cycleId,(int)$actor['employee_id'],(int)$member['employee_id']])[0] ?? null;
}
require_once __DIR__ . '/header.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/annual-evaluations.css">
<div class="annual-page <?php echo $annual_in_performance_page ? 'evaluation-packages annual-page--integrated' : 'container-fluid py-4'; ?>">
    <?php if ($annual_in_performance_page): require __DIR__.'/performance-evaluation-heading.php'; else: ?>
    <section class="annual-hero mb-4">
        <p class="small text-uppercase mb-1"><?php echo $annual_in_performance_page ? 'Performance Evaluation Process' : 'Performance evaluation · Regular employees'; ?></p>
        <h1 class="h3 fw-bold"><i class="fas fa-users me-2 annual-hero-icon" aria-hidden="true"></i><?php echo $annual_in_performance_page ? 'Performance Evaluation' : 'Annual Evaluations &amp; 360 Ratings'; ?></h1>
        <p class="mb-0">Position-specific KRAs and individual Core Behaviors &amp; Values results.</p>
    </section>
    <?php endif; ?>
    <?php if ($error): ?><div role="alert" class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
    <?php if (!$annual_in_performance_page || $own): ?>
    <nav class="annual-workflow-nav d-flex flex-wrap gap-2 mb-4" aria-label="Annual workflow">
        <a class="btn btn-outline-success <?php echo !in_array($view,['peers','rate'],true) ? 'active' : ''; ?>" <?php echo !in_array($view,['peers','rate'],true) ? 'aria-current="page"' : ''; ?> href="<?php echo annualUrl($actor,'?cycle_id='.$cycleId); ?>"><i class="fas fa-file-alt me-2" aria-hidden="true"></i><?php echo $annual_in_performance_page ? 'Regular Employee · Annual' : 'Annual evaluations'; ?></a>
        <?php if ($own): ?><a class="btn btn-outline-success <?php echo in_array($view,['peers','rate'],true) ? 'active' : ''; ?>" <?php echo in_array($view,['peers','rate'],true) ? 'aria-current="page"' : ''; ?> href="<?php echo annualUrl($actor,'?view=peers&cycle_id='.$cycleId); ?>"><i class="fas fa-users me-2" aria-hidden="true"></i>360 · Department peers</a><?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php if (!$cycles): ?>
        <div class="content-card p-4"><h2 class="h5">No annual evaluations submitted yet</h2><p class="mb-0">Annual cycles appear when regular employees submit their Annual self-rating. Use the same period dates across the department's position-specific templates.</p></div>
    <?php elseif (!$evaluationId): ?>
        <form class="content-card annual-cycle-filter p-3 mb-4 d-flex gap-2 flex-wrap" method="get">
            <?php if ($annual_in_performance_page): ?><input type="hidden" name="category" value="regular"><?php endif; ?>
            <input type="hidden" name="view" value="<?php echo e($view); ?>">
            <label for="annualCycle" class="align-self-center">Department / period</label>
            <select id="annualCycle" name="cycle_id" class="form-select annual-cycle">
            <?php foreach ($cycles as $c): ?>
                <option value="<?php echo (int)$c['cycle_id']; ?>" <?php echo (int)$c['cycle_id']===$cycleId ? 'selected' : ''; ?>><?php echo e($c['department_name'].' · '.$c['period_start'].' to '.$c['period_end']); ?></option>
            <?php endforeach; ?>
            </select><button class="btn btn-success">Show cycle</button>
        </form>
        <?php if ($view==='peers'): ?>
            <div class="alert alert-info">Rate coworkers on Core Behaviors &amp; Values only. Your own self-rating is already included once. Confirmed submissions are locked.</div>
            <?php if (!annualCanRate($actor)): ?><div class="alert alert-warning">HR department peer ratings are completed through your HRIS account. Your Employee Portal remains available for self-rating and your results.</div><?php endif; ?>
            <?php if (!$own[0]['evaluation_id']): ?><div class="alert alert-warning">Submit your Annual self-rating for this period before rating coworkers.</div><?php endif; ?>
        <?php endif; ?>
        <div class="content-card p-3">
            <header class="annual-section-heading"><h2 class="h5 mb-1"><?php echo $view==='peers' ? '360 Rating — Department Peers' : 'Annual Evaluation Progress'; ?></h2><p class="text-muted small mb-0"><?php echo $view==='peers' ? 'Rate your coworkers on the eight Core Behaviors & Values items.' : 'Review submitted self-ratings, department 360 participation, and annual results.'; ?></p></header>
            <div class="row g-2 mb-3"><div class="col-md-7"><label for="annualSearch" class="form-label">Find employee</label><input id="annualSearch" class="form-control" type="search" placeholder="Employee name or position"></div>
                <div class="col-md-5"><label for="annualStatus" class="form-label">Status</label><select id="annualStatus" class="form-select"><option value="">All</option><option value="submitted">Self-rating submitted</option><option value="pending">For consolidation</option><option value="finalized">Finalized</option><option value="revision">Revision pending</option></select></div></div>
            <div class="table-responsive"><table class="table align-middle" id="annualRoster">
            <thead><tr><th>Employee</th><th>Position</th><th>Self-rating</th><th><?php echo $view==='peers' ? 'Your 360 rating' : '360 received'; ?></th><th>Annual result</th><th>Action</th></tr></thead>
            <tbody><?php foreach ($members as $m):
                $isOwnRow=(int)$m['employee_id']===(int)$actor['employee_id'];
                $target=$m['evaluation_id'] ? annualMember($conn,(int)$m['evaluation_id']) : null;
                $canRead=$target && ((int)$m['employee_id']===(int)$actor['employee_id'] || annualCanSeeNames($conn,$actor,$target));
                if ($view!=='peers' && (!$target || !annualCanFinalize($conn,$actor,$target))) continue;
                $state=$m['revision_pending'] ? 'Late 360 · Revision pending' : ($m['current_version'] ? 'Finalized · v'.$m['current_version'] : 'Pending consolidation');
                ?>
                <tr data-annual-state="<?php echo $m['revision_pending'] ? 'revision' : ($m['current_version'] ? 'finalized' : 'pending'); ?>" data-annual-submitted="<?php echo $m['evaluation_id'] ? '1' : '0'; ?>"><td><?php echo e($m['first_name'].' '.$m['last_name']); ?><?php if ($view==='peers' && $isOwnRow): ?> <span class="badge bg-secondary">You</span><?php endif; ?></td><td><?php echo e($m['position_name']); ?></td>
                    <td><?php echo $m['evaluation_id'] ? 'Submitted' : 'Awaiting self-rating'; ?></td>
                    <td><?php echo $view==='peers' ? ($isOwnRow ? 'Self-rating included' : ($m['rated'] ? 'Submitted · Locked' : 'Not submitted')) : (int)$m['received'].' / '.max(0,count($members)-1); ?></td>
                    <td><span class="badge bg-<?php echo $m['revision_pending'] ? 'warning text-dark' : ($m['current_version'] ? 'success' : 'secondary'); ?>"><?php echo e($state); ?></span>
                        <?php if ($m['current_version']): ?><div class="small mt-1">Current final: <?php echo number_format((float)$m['total_score'],2); ?></div><?php endif; ?></td>
                    <td><?php if ($view==='peers' && $isOwnRow): ?>
                        <span class="small text-muted">Cannot rate yourself</span>
                    <?php elseif ($view==='peers' && annualCanRate($actor) && $own[0]['evaluation_id'] && $m['evaluation_id']): ?>
                        <a class="btn btn-sm btn-outline-success" href="<?php echo annualUrl($actor,'?view=rate&evaluation_id='.(int)$m['evaluation_id']); ?>"><?php echo $m['rated'] ? 'View submission' : 'Rate'; ?></a>
                    <?php elseif ($canRead): ?>
                        <a class="btn btn-sm btn-outline-success" href="<?php echo annualUrl($actor,'?view=review&evaluation_id='.(int)$m['evaluation_id']); ?>">View evaluation</a>
                    <?php else: ?>—<?php endif; ?></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div></div>
    <?php elseif ($view==='rate'): ?>
        <section class="content-card p-4">
            <h2 class="h4"><?php echo e($member['first_name'].' '.$member['last_name']); ?></h2>
            <p><?php echo e($member['position_name']); ?> · <?php echo e($member['period_start'].' to '.$member['period_end']); ?></p>
            <p id="annualRatingProgress" class="small fw-bold" aria-live="polite"></p>
            <?php if ($submitted): ?><div class="alert alert-success">Submitted and locked. These ratings cannot be edited.</div><?php endif; ?>
            <form method="post" id="annualRatingForm">
                <?php echo csrfField(); ?><input type="hidden" name="action" value="rate"><input type="hidden" name="cycle_id" value="<?php echo $cycleId; ?>"><input type="hidden" name="confirmed" id="annualConfirmed" value="0">
                <div class="table-responsive"><table class="table align-middle annual-rating-table"><thead><tr><th>Core Behavior &amp; Value</th><th>Description</th><th>Rating (1–4)</th></tr></thead><tbody>
                <?php $scores=$submitted ? json_decode($submitted['scores_json'],true) : ($_POST['scores'] ?? []);
                foreach (annualCoreValues() as $i=>$value): ?>
                    <tr><td><?php echo e($value['name']); ?></td><td><?php echo e($value['kpi']); ?></td><td>
                        <input class="form-control annual-number" type="number" inputmode="decimal" min="1" max="4" step="0.01" required
                            name="scores[<?php echo $i; ?>]" aria-label="Rating for <?php echo e($value['name']); ?>" data-label="<?php echo e($value['name']); ?>"
                            value="<?php echo e($scores[$i] ?? ''); ?>" <?php echo $submitted ? 'disabled' : ''; ?>>
                    </td></tr>
                <?php endforeach; ?></tbody></table></div>
                <?php if (!$submitted): ?><button class="btn btn-success" type="button" id="annualReviewRatings">Review &amp; submit ratings</button><?php endif; ?>
            </form>
        </section>
        <div class="modal fade" id="annualRatingSummary" tabindex="-1" aria-labelledby="annualSummaryTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h2 id="annualSummaryTitle" class="modal-title h5">Review your ratings</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><p>For <?php echo e($member['first_name'].' '.$member['last_name']); ?></p><table class="table"><tbody id="annualSummaryRows"></tbody></table>
                <div class="alert alert-warning">After confirming, you cannot edit these ratings. Check every number before submitting.</div></div>
            <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Back to edit</button><button class="btn btn-success" id="annualConfirmSubmit">Confirm &amp; submit</button></div>
        </div></div></div>
    <?php elseif ($member):
        $peers=annualPeers($conn,$member,$named);
        $self=json_decode($member['self_values_json'],true);
        $candidate=annualCandidate($conn,$member);
        $kra=annualKraRows($conn,$evaluationId);
        $versions=annualQuery($conn,'SELECT version_number,finalized_at,remarks,snapshot_json FROM annual_evaluation_versions WHERE member_id=? ORDER BY version_number DESC',[(int)$member['member_id']]);
        $editable=$canFinalize && (!$member['current_version'] || $member['revision_pending']);
        $kraApproval=annualQuery($conn,'SELECT approved_at,remarks FROM annual_kra_approvals WHERE member_id=? ORDER BY approval_id DESC LIMIT 1',[(int)$member['member_id']])[0] ?? null;
        $expected=max(0,count($members)-1);
        ?>
        <section class="content-card p-4 mb-4" id="annualOverview">
            <h2 class="h4"><?php echo e($member['first_name'].' '.$member['last_name']); ?></h2>
            <p><?php echo e($member['position_name'].' · '.$member['template_name'].' · '.$member['period_start'].' to '.$member['period_end']); ?></p>
            <p class="small">Finalized by the immediate-head position: <strong><?php echo e($member['reviewer_position_name'] ?? 'Not configured'); ?></strong></p>
            <?php if (!$member['reviewer_position_id']): ?><div class="alert alert-warning">Configure this position's Reports To relationship in Position Management before finalizing this evaluation.</div><?php endif; ?>
            <?php if ($member['revision_pending']): ?><div class="alert alert-warning">Late 360 · Revision pending. The official score remains <?php echo number_format((float)$member['official_score'],2); ?> until the immediate head re-finalizes.</div><?php endif; ?>
            <div class="row g-3 annual-summary">
                <div class="col-sm-4"><div class="p-3 bg-light rounded"><span>Reviewed KRA · 80%</span><strong><?php echo number_format($candidate['kra'],2); ?></strong></div></div>
                <div class="col-sm-4"><div class="p-3 bg-light rounded"><span>Self + peer Core Values · 20%</span><strong><?php echo number_format($candidate['core'],2); ?></strong><small><?php echo count($peers); ?> of <?php echo $expected; ?> peer ratings received</small></div></div>
                <div class="col-sm-4"><div class="p-3 bg-light rounded"><span><?php echo $member['current_version'] ? 'Official final score' : 'Provisional combined score'; ?></span><strong><?php echo number_format((float)($member['current_version'] ? $member['official_score'] : $candidate['total']),2); ?></strong>
                    <?php if ($member['revision_pending']): ?><small>Pending candidate: <?php echo number_format($candidate['total'],2); ?></small><?php endif; ?></div></div>
            </div>
        </section>
        <form method="post" class="content-card annual-review-card mb-4" id="annualConsolidationForm" data-core="<?php echo $candidate['core']; ?>">
            <?php echo csrfField(); ?><input type="hidden" name="action" value="finalize"><input type="hidden" name="review_token" value="<?php echo annualReviewToken($conn,$member); ?>">
            <div class="annual-review-tabs" role="tablist" aria-label="Evaluation sections">
                <button class="active" id="annualKraTab" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#annualKraPanel" aria-controls="annualKraPanel" aria-selected="true"><i class="fas fa-chart-pie me-2"></i>Key Result Areas (KRA) <span><?php echo count($kra); ?></span></button>
                <button id="annualCoreTab" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#annualCorePanel" aria-controls="annualCorePanel" aria-selected="false"><i class="fas fa-users me-2"></i>Core Behaviors &amp; Values <span>8</span></button>
                <button id="annualDecisionTab" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#annualDecisionPanel" aria-controls="annualDecisionPanel" aria-selected="false"><i class="fas fa-file-signature me-2"></i>Consolidation &amp; Final Score</button>
            </div>
            <div class="tab-content p-4">
            <div class="tab-pane show active" id="annualKraPanel" role="tabpanel" aria-labelledby="annualKraTab" tabindex="0">
            <?php if ($editable): ?><div class="alert alert-info"><strong>How to Adjust Ratings:</strong> Enter the evaluator adjusted rating between 1.00 and 4.00. The proposed final score recalculates automatically.</div><?php endif; ?>
            <h3 class="h5 annual-review-section" id="annualKra"><span><i class="fas fa-chart-pie me-2"></i>Key Result Areas (KRA)</span><small>KRA Weight: 80%</small></h3>
            <?php if ($kraApproval): ?><div class="alert alert-success"><strong>KRA approved</strong> · <?php echo e($kraApproval['approved_at']); ?><?php if ($kraApproval['remarks']!==''): ?><div><?php echo e($kraApproval['remarks']); ?></div><?php endif; ?><small>Any new adjustments must be approved again or included in the final Annual review.</small></div><?php endif; ?>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>KRA</th><th>Weight</th><th>Self-rating</th><th>Reviewed rating</th></tr></thead><tbody>
            <?php foreach ($kra as $row): ?><tr><td><?php echo e($row['criterion_name']); ?></td><td><?php echo number_format((float)$row['weight'],2); ?>%</td><td><?php echo number_format((float)$row['self_rating'],2); ?></td><td>
                <?php if ($editable): ?><input class="form-control annual-number" type="number" inputmode="decimal" min="1" max="4" step="0.01" required data-weight="<?php echo e($row['weight']); ?>" name="kra[<?php echo (int)$row['score_id']; ?>]" aria-label="Reviewed rating for <?php echo e($row['criterion_name']); ?>" value="<?php echo e($_POST['kra'][$row['score_id']] ?? $row['rating']); ?>">
                <?php else: echo number_format((float)$row['rating'],2); endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
            <?php if ($editable): ?>
                <div class="mt-3"><label for="annualKraRemarks" class="form-label">KRA review remarks</label>
                <textarea class="form-control mb-3" id="annualKraRemarks" name="kra_remarks" rows="2" placeholder="Explain any adjusted KRA ratings."><?php echo e($_POST['kra_remarks'] ?? ''); ?></textarea>
                <button type="submit" class="btn btn-success" name="action" value="approve_kra"><i class="fas fa-check me-2" aria-hidden="true"></i>Approve KRA</button>
                <p class="small text-muted mt-2 mb-0">Saves your reviewed KRA ratings only. You can approve KRA while 360 ratings are still pending; this does not finalize the Annual evaluation.</p></div>
            <?php endif; ?>
            </div>
            <div class="tab-pane" id="annualCorePanel" role="tabpanel" aria-labelledby="annualCoreTab" tabindex="0">
            <h3 class="h5 annual-review-section" id="annual360"><span><i class="fas fa-users me-2"></i>Core Behaviors &amp; Values · 360 Results</span><small>Behavior Weight: 20%</small></h3>
            <p class="small text-muted"><?php echo $named ? 'Rater names are visible to authorized consolidators only.' : 'Coworker identities are hidden. Employee labels identify anonymous responses only.'; ?></p>
            <div class="table-responsive"><table class="table"><thead><tr><th>Behavior</th><th>Self</th>
                <?php foreach ($peers as $i=>$p): ?><th><?php echo e($named ? $p['rater_name'] : 'Employee '.($i+1)); ?></th><?php endforeach; ?><th>Combined average</th></tr></thead><tbody>
                <?php foreach (annualCoreValues() as $i=>$value): ?><tr><td><?php echo e($value['name']); ?></td><td><?php echo number_format((float)$self[$i],2); ?></td>
                    <?php foreach ($peers as $p): $pScores=json_decode($p['scores_json'],true); ?><td><?php echo number_format((float)$pScores[$i],2); ?></td><?php endforeach; ?><td><?php echo number_format($candidate['item_averages'][$i],2); ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
            </div>
            <div class="tab-pane" id="annualDecisionPanel" role="tabpanel" aria-labelledby="annualDecisionTab" tabindex="0">
            <h3 class="h5 annual-review-section">Consolidation &amp; Final Score</h3>
            <p>Reviewed KRA (80%) + individual Core Values result (20%). Finalized results are preserved in the version history below.</p>
            <?php if ($editable): ?>
                <div class="alert alert-info" id="annualProposedResult" aria-live="polite">Proposed score: <?php echo number_format($candidate['total'],2); ?></div>
                <label for="annualRemarks" class="form-label fw-bold">Review remarks</label>
                <textarea id="annualRemarks" class="form-control mb-3" name="remarks" rows="3" placeholder="Explain adjustments, missing participation, or late revisions."><?php echo e($_POST['remarks'] ?? ''); ?></textarea>
                <?php if (count($peers)<$expected): ?><p class="text-warning-emphasis" id="annualParticipationNotice">Some raters have not submitted. Finalization is unavailable until all <?php echo $expected; ?> expected raters have submitted their 360 ratings (<?php echo count($peers); ?> received). Refresh this review after they submit.</p><?php endif; ?>
                <button class="btn btn-success" type="submit" <?php echo count($peers)<$expected ? 'disabled aria-disabled="true" aria-describedby="annualParticipationNotice"' : ''; ?>><?php echo $member['current_version'] ? 'Re-finalize revised Annual result' : 'Finalize Annual evaluation'; ?></button>
            <?php endif; ?>
            </div></div>
        </form>
        <section class="content-card p-4" id="annualHistory"><h3 class="h5">Finalized versions</h3>
            <?php if (!$versions): ?><p>No finalized version yet.</p><?php endif; ?>
            <?php foreach ($versions as $v): $snapshot=json_decode($v['snapshot_json'],true); ?>
                <div class="border rounded p-3 mb-2"><strong>Version <?php echo (int)$v['version_number']; ?> · <?php echo number_format($snapshot['total'],2); ?></strong>
                    <p class="small mb-1"><?php echo e($v['finalized_at']); ?> · KRA <?php echo number_format($snapshot['kra'],2); ?> · Core Values <?php echo number_format($snapshot['core'],2); ?> · <?php echo (int)$snapshot['peer_count']; ?> peer ratings</p>
                    <p class="mb-0"><?php echo e($v['remarks']); ?></p></div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</div>
<script>
function updateAnnualProgress() {
    const inputs=[...document.querySelectorAll('#annualRatingForm input[type="number"]')];
    const progress=document.getElementById('annualRatingProgress');
    if (progress) progress.textContent=inputs.filter(input=>input.value!=='' && Number(input.value)>=1 && Number(input.value)<=4).length+' of 8 values rated';
}
document.querySelectorAll('#annualRatingForm input[type="number"]').forEach(input=>input.addEventListener('input',updateAnnualProgress));
updateAnnualProgress();
function updateAnnualProposedResult() {
    const form=document.getElementById('annualConsolidationForm'), result=document.getElementById('annualProposedResult');
    if (!form || !result) return;
    const round2=value=>Math.round((value+Number.EPSILON)*100)/100;
    const inputs=[...form.querySelectorAll('input[data-weight]')];
    if (inputs.some(input=>input.value==='' || !input.checkValidity())) { result.textContent='Complete valid KRA ratings to preview the proposed score.'; return; }
    const kra=round2(inputs.reduce((sum,input)=>sum+round2(Number(input.dataset.weight)/100*Number(input.value)),0));
    const core=Number(form.dataset.core), total=round2(kra*.8+core*.2);
    result.textContent='Proposed result: KRA '+kra.toFixed(2)+' × 80% + Core Values '+core.toFixed(2)+' × 20% = '+total.toFixed(2);
}
document.querySelectorAll('#annualConsolidationForm input[data-weight]').forEach(input=>input.addEventListener('input',updateAnnualProposedResult));
document.getElementById('annualConsolidationForm')?.addEventListener('invalid',event=>{
    const panel=event.target.closest('.tab-pane');
    const trigger=panel && document.querySelector('[data-bs-target="#'+panel.id+'"]');
    if (trigger) bootstrap.Tab.getOrCreateInstance(trigger).show();
},true);
updateAnnualProposedResult();
function filterAnnualRoster() {
    const text=(document.getElementById('annualSearch')?.value || '').toLowerCase();
    const state=document.getElementById('annualStatus')?.value || '';
    document.querySelectorAll('#annualRoster tbody tr').forEach(row=>{
        const matches=state==='submitted' ? row.dataset.annualSubmitted==='1' : !state || row.dataset.annualState===state;
        row.hidden=!matches || !row.textContent.toLowerCase().includes(text);
    });
}
document.getElementById('annualSearch')?.addEventListener('input',filterAnnualRoster);
document.getElementById('annualStatus')?.addEventListener('change',filterAnnualRoster);
document.getElementById('annualReviewRatings')?.addEventListener('click', () => {
    const form=document.getElementById('annualRatingForm');
    if (!form.reportValidity()) return;
    const body=document.getElementById('annualSummaryRows'); body.replaceChildren();
    form.querySelectorAll('input[type="number"]').forEach(input => {
        const row=document.createElement('tr'), label=document.createElement('td'), score=document.createElement('td');
        label.textContent=input.dataset.label; score.textContent=Number(input.value).toFixed(2);
        row.append(label,score); body.append(row);
    });
    new bootstrap.Modal(document.getElementById('annualRatingSummary')).show();
});
document.getElementById('annualConfirmSubmit')?.addEventListener('click', function() {
    this.disabled=true; document.getElementById('annualConfirmed').value='1'; document.getElementById('annualRatingForm').requestSubmit();
});
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
