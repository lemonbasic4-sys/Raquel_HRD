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
$historyView=$view==='history';
if ($historyView) $view='peers';
$evaluationId = (int)($_GET['evaluation_id'] ?? 0);
if (!$evaluationId && $view==='list') $view='peers';
$cycleId = (int)($_GET['cycle_id'] ?? 0);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    try {
        if (($_POST['action'] ?? '') === 'rate' && ($_POST['confirmed'] ?? '') === '1') {
            $ratingTarget=annualMember($conn,$evaluationId);
            $withKra=$ratingTarget && annualCanFinalize($conn,$actor,$ratingTarget);
            if ($withKra) {
                annualReviewAndRate($conn,$actor,$evaluationId,(array)($_POST['kra'] ?? []),
                    (string)($_POST['review_token'] ?? ''),(array)($_POST['scores'] ?? []),trim($_POST['rater_signature_data'] ?? ''),(array)($_POST['plans'] ?? []),(string)($_POST['head_comments'] ?? ''));
            } else {
                if (!empty($_POST['kra'])) throw new DomainException('Only the immediate head may adjust KRA ratings.');
                annualSubmitPeers($conn,$actor,$evaluationId,(array)($_POST['scores'] ?? []));
            }
            redirectWith(annualUrl($actor,'?view=peers&cycle_id='.(int)$_POST['cycle_id']),'success',$withKra ? 'KRA review approved and your 360 ratings submitted. Peer ratings are locked.' : 'Your 360 ratings were submitted and locked.');
        } elseif (($_POST['action'] ?? '') === 'finalize') {
            annualFinalize($conn,$actor,$evaluationId,(array)($_POST['kra'] ?? []),trim($_POST['remarks'] ?? ''),(string)($_POST['review_token'] ?? ''),(array)($_POST['core_adjustments'] ?? []));
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
$cycleOptions=array_values(array_filter($cycles,static fn($c)=>!empty($c['completed_at'])===$historyView));
if (!$cycleId && $cycleOptions) $cycleId=(int)$cycleOptions[0]['cycle_id'];
$member=null; $named=false; $canFinalize=false;
if ($evaluationId) {
    $member=annualMember($conn,$evaluationId);
    $sameDepartmentPeerView=$member && (int)$member['employee_id']!==(int)$actor['employee_id']
        && (int)$member['department_id']===(int)$actor['department_id'] && annualCanRate($actor);
    if (!$member || ((int)$member['employee_id']!==(int)$actor['employee_id'] && !annualCanSeeNames($conn,$actor,$member)
        && $view!=='rate' && !(in_array($view,['review','results'],true) && $sameDepartmentPeerView))) { http_response_code(403); exit('This Annual evaluation is unavailable to your account.'); }
    $cycleId=(int)$member['cycle_id'];
    // The HRD workflow now displays rater names in 360 results. Keep
    // authorization for viewing the evaluation itself separate from rating/finalization.
    $named=(int)$actor['employee_id']!==(int)$member['employee_id'];
    $canFinalize=annualCanFinalize($conn,$actor,$member);
}
// Existing inbox links may still target the retired consolidation review.
if ($_SERVER['REQUEST_METHOD']==='GET' && $member && $canFinalize && $view==='review'
    && in_array($actor['role'],['HR Manager','HR Supervisor'],true)) {
    $hasHeadReview=(bool)annualQuery($conn,'SELECT 1 FROM annual_peer_submissions WHERE cycle_id=? AND target_employee_id=? AND rater_employee_id=? LIMIT 1',
        [(int)$member['cycle_id'],(int)$member['employee_id'],(int)$actor['employee_id']]);
    if ($hasHeadReview || !(int)$member['current_version']) {
        header('Location: '.annualUrl($actor,'?view='.($hasHeadReview ? 'head-review' : 'rate').'&evaluation_id='.$evaluationId)); exit;
    }
}
$headReviewSubmission=null;
$headReviewKra=[];
if ($view==='head-review') {
    if (!$member || !$canFinalize) { http_response_code(403); exit('Only this employee\'s immediate head can view the submitted head review.'); }
    $headReviewSubmission=annualQuery($conn,'SELECT scores_json,rater_signature_data,rater_signed_at,submitted_at FROM annual_peer_submissions WHERE cycle_id=? AND rater_employee_id=? AND target_employee_id=?',
        [$cycleId,(int)$actor['employee_id'],(int)$member['employee_id']])[0] ?? null;
    if (!$headReviewSubmission) { http_response_code(404); exit('The head review has not been submitted.'); }
    $kraApproval=annualQuery($conn,'SELECT ratings_json FROM annual_kra_approvals WHERE member_id=? AND approved_by=? AND approved_at<=? ORDER BY approved_at DESC,approval_id DESC LIMIT 1',
        [(int)$member['member_id'],(int)$actor['user_id'],$headReviewSubmission['submitted_at']])[0] ?? null;
    $headReviewKra=$kraApproval ? (json_decode($kraApproval['ratings_json'],true) ?: []) : annualKraRows($conn,$evaluationId);
}
$cycle=null;
foreach ($cycles as $c) if ((int)$c['cycle_id']===$cycleId) $cycle=$c;
if ($cycleId && !$cycle) { http_response_code(403); exit('This department cycle is unavailable.'); }
if ($cycle && !empty($cycle['completed_at'])) {
    $historyView=true;
    $cycleOptions=array_values(array_filter($cycles,static fn($c)=>!empty($c['completed_at'])));
}
if ($historyView && $cycle && empty($cycle['completed_at']) && !$evaluationId) {
    header('Location: '.annualUrl($actor,'?view=peers&cycle_id='.$cycleId)); exit;
}
if ($cycle && !empty($cycle['completed_at']) && $view==='rate') {
    header('Location: '.annualUrl($actor,'?view='.($canFinalize ? 'head-review' : 'review').'&evaluation_id='.$evaluationId)); exit;
}
$members=$cycle ? annualQuery($conn,"SELECT m.*,e.first_name,e.last_name,ev.total_score,
        (SELECT COUNT(*) FROM annual_peer_submissions s WHERE s.cycle_id=m.cycle_id AND s.target_employee_id=m.employee_id) AS received,
        (SELECT COUNT(*) FROM annual_peer_submissions s WHERE s.cycle_id=m.cycle_id AND s.target_employee_id=m.employee_id AND s.rater_employee_id=?) AS rated
    FROM annual_evaluation_members m JOIN employees e ON e.employee_id=m.employee_id
    LEFT JOIN evaluations ev ON ev.evaluation_id=m.evaluation_id WHERE m.cycle_id=? AND ".annualRosterEligibilitySql()." ORDER BY e.last_name,e.first_name",
    [(int)$actor['employee_id'],$cycleId]) : [];
$own=array_values(array_filter($members,static fn($m)=>(int)$m['employee_id']===(int)$actor['employee_id']));
$directReportCycle=(bool)array_filter($members,static fn($m)=>(int)$m['employee_id']!==(int)$actor['employee_id'] && (int)$m['reviewer_position_id']>0 && (int)$m['reviewer_position_id']===(int)$actor['job_title_id']);
$isDepartmentPeer=$own && (int)$cycle['department_id']===(int)$actor['department_id'];
if (($view==='rate' && !$isDepartmentPeer && !$canFinalize) || ($view==='peers' && !$isDepartmentPeer && !$directReportCycle && $cycle)) {
    http_response_code(403); exit('360 ratings are available only within your own department cycle.');
}
if ($view==='rate') {
    if (!$member || !annualCanRate($actor) || (empty($own[0]['evaluation_id']) && !annualCanRateBeforeSelf($conn,$actor,$member)) || (int)$member['employee_id']===(int)$actor['employee_id']) {
        http_response_code(403); exit('This peer-rating task is unavailable.');
    }
    $raterMember=!empty($own[0]['evaluation_id']) ? annualMember($conn,(int)$own[0]['evaluation_id']) : null;
    if (!$raterMember && !annualCanRateBeforeSelf($conn,$actor,$member)) {
        http_response_code(409);
        exit('Your own Annual evaluation record is unavailable, so you cannot submit department peer ratings yet. Contact HR to restore or review your Annual record.');
    }
    try { annualAssertSnapshotActive($member); if ($raterMember) annualAssertSnapshotActive($raterMember); }
    catch (DomainException $e) { http_response_code(403); exit(e($e->getMessage())); }
    $submitted=annualQuery($conn,'SELECT scores_json FROM annual_peer_submissions WHERE cycle_id=? AND rater_employee_id=? AND target_employee_id=?',
        [$cycleId,(int)$actor['employee_id'],(int)$member['employee_id']])[0] ?? null;
}
require_once __DIR__ . '/header.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/annual-evaluations.css?v=<?php echo filemtime(__DIR__.'/../assets/css/annual-evaluations.css'); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/annual-result-cards.css?v=<?php echo filemtime(__DIR__.'/../assets/css/annual-result-cards.css'); ?>">
<div class="annual-page evaluation-packages <?php echo $annual_in_performance_page ? 'annual-page--integrated' : 'container-fluid py-4'; ?>">
    <?php if ($annual_in_performance_page): require __DIR__.'/performance-evaluation-heading.php'; else: ?>
    <section class="annual-hero mb-4">
        <p class="small text-uppercase mb-1"><?php echo $annual_in_performance_page ? 'Performance Evaluation Process' : 'Performance evaluation · Regular employees'; ?></p>
        <h1 class="h3 fw-bold"><i class="fas fa-users me-2 annual-hero-icon" aria-hidden="true"></i><?php echo $annual_in_performance_page ? 'Performance Evaluation' : 'Annual Evaluations &amp; 360 Ratings'; ?></h1>
        <p class="mb-0">Position-specific KRAs and individual Core Behaviors &amp; Values results.</p>
    </section>
    <?php endif; ?>
    <?php if ($error): ?><div role="alert" class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
    <?php if ($cycles): ?>
    <nav class="annual-workflow-nav d-flex flex-wrap gap-2 mb-4" aria-label="Annual workflow">
        <a class="btn btn-outline-success <?php echo !$historyView ? 'active' : ''; ?>" <?php echo !$historyView ? 'aria-current="page"' : ''; ?> href="<?php echo annualUrl($actor,'?view=peers'); ?>"><i class="fas fa-users me-2" aria-hidden="true"></i>360 · Department peers</a>
        <a class="btn btn-outline-success <?php echo $historyView ? 'active' : ''; ?>" <?php echo $historyView ? 'aria-current="page"' : ''; ?> href="<?php echo annualUrl($actor,'?view=history'); ?>"><i class="fas fa-history me-2" aria-hidden="true"></i>Annual Evaluation History</a>
    </nav>
    <?php endif; ?>
    <?php if (!$cycles || (!$evaluationId && !$cycleOptions)): ?>
        <div class="annual-note-card" role="status">
            <i class="fas fa-layer-group annual-note-icon" aria-hidden="true"></i>
            <h2 class="h5 fw-bold"><?php echo $historyView ? 'No completed Annual cycles yet' : 'No active Annual evaluations'; ?></h2>
            <p class="mb-0"><?php echo $historyView ? 'Completed department cycles appear here after all required approvals and 360 ratings are submitted.' : 'Submitted Annual cycles appear here while reviews and ratings are in progress. Completed cycles are available in Annual Evaluation History.'; ?></p>
        </div>
    <?php elseif (!$evaluationId): ?>
        <form class="content-card annual-cycle-filter p-3 mb-4 d-flex gap-2 flex-wrap" method="get">
            <?php if ($annual_in_performance_page): ?><input type="hidden" name="category" value="regular"><?php endif; ?>
            <input type="hidden" name="view" value="<?php echo $historyView ? 'history' : e($view); ?>">
            <label for="annualCycle" class="align-self-center">Department / period</label>
            <select id="annualCycle" name="cycle_id" class="form-select annual-cycle">
            <?php foreach ($cycleOptions as $c): ?>
                <option value="<?php echo (int)$c['cycle_id']; ?>" <?php echo (int)$c['cycle_id']===$cycleId ? 'selected' : ''; ?>><?php echo e($c['department_name'].' · '.$c['period_start'].' to '.$c['period_end']); ?></option>
            <?php endforeach; ?>
            </select><button class="btn btn-success">Show cycle</button>
        </form>
        <?php if ($historyView): ?>
            <div class="alert alert-success" role="status"><i class="fas fa-check-circle me-2" aria-hidden="true"></i>All required Annual approvals and 360 ratings are complete. This cycle is archived for viewing. Completed <?php echo e($cycle['completed_at']); ?>.</div>
        <?php elseif ($view==='peers'): ?>
            <div class="alert alert-info">Rate coworkers on Core Behaviors &amp; Values. For your direct reports, review KRA and submit your Core Values rating on the same screen. Your self-rating is included once. Confirmed peer ratings are locked.</div>
            <?php if (!annualCanRate($actor)): ?><div class="alert alert-warning">HR department peer ratings are completed through your HRIS account. Your Employee Portal remains available for self-rating and your results.</div><?php endif; ?>
            <?php if ($own && !$own[0]['evaluation_id']): ?><div class="alert alert-warning"><?php echo $actor['role']==='HR Manager' ? 'Your own Annual self-rating is pending. You may rate submitted department evaluations now and complete your self-rating separately.' : 'Your own Annual self-rating is pending. Direct-report Rate & Review remains available; submit your self-rating before rating other coworkers.'; ?></div><?php endif; ?>
        <?php endif; ?>
        <div class="content-card p-3">
            <header class="annual-section-heading <?php echo $view==='peers' ? 'annual-peer-heading' : ''; ?>"><div><h2 class="h5 mb-1"><?php echo $historyView ? 'Annual Evaluation History' : ($view==='peers' ? '360 Rating — Department Peers' : 'Annual Evaluation Progress'); ?></h2><p class="text-muted small mb-0"><?php echo $historyView ? 'Completed department evaluations and their approved results. View only.' : ($view==='peers' ? 'Rate your coworkers on the eight Core Behaviors & Values items.' : 'Review submitted self-ratings, department 360 participation, and annual results.'); ?></p></div>
                <?php if ($view==='peers'): ?>
                <div class="annual-peer-size">
                    <div class="package-stat" aria-label="Department size: <?php echo count($members); ?> employees in this Annual cycle">
                        <div class="package-stat-icon" style="background:rgba(8,46,6,.08);color:var(--rp-forest-green,#082e06);"><i class="fas fa-users" aria-hidden="true"></i></div>
                        <div class="package-stat-content"><strong><?php echo count($members); ?> <?php echo count($members)===1 ? 'Employee' : 'Employees'; ?></strong><span>Department size</span></div>
                    </div>
                </div>
                <?php endif; ?>
            </header>
            <div class="row g-2 mb-3"><div class="col-md-7"><label for="annualSearch" class="form-label">Find employee</label><input id="annualSearch" class="form-control" type="search" placeholder="Employee name or position"></div>
                <div class="col-md-5"><label for="annualStatus" class="form-label">Status</label><select id="annualStatus" class="form-select"><option value="">All</option><option value="submitted">Self-rating submitted</option><option value="pending">For consolidation</option><option value="finalized">Finalized</option><option value="revision">Revision pending</option></select></div></div>
            <div class="table-responsive"><table class="table align-middle" id="annualRoster">
            <thead><tr><th>Employee</th><th>Position</th><th>Self-rating</th><th><?php echo $view==='peers' ? 'Your 360 rating' : '360 received'; ?></th><?php if ($view==='peers'): ?><th class="text-center">360 received</th><?php endif; ?><th>Annual result</th><th>Action</th></tr></thead>
            <tbody><?php foreach ($members as $m):
                $isOwnRow=(int)$m['employee_id']===(int)$actor['employee_id'];
                $target=$m['evaluation_id'] ? annualMember($conn,(int)$m['evaluation_id']) : null;
                $canRead=$target && ((int)$m['employee_id']===(int)$actor['employee_id'] || annualCanSeeNames($conn,$actor,$target)
                    || ((int)$cycle['department_id']===(int)$actor['department_id'] && annualCanRate($actor)));
                if ($view==='peers' && !$isDepartmentPeer && (!$target || !annualCanFinalize($conn,$actor,$target))) continue;
                if ($view!=='peers' && (!$target || !annualCanFinalize($conn,$actor,$target))) continue;
                $state=$m['current_version'] ? ((int)$m['received']>=annualExpectedPeers($conn,$cycleId,(int)$m['employee_id']) ? 'Approved · Score complete' : 'Approved · Score provisional') : 'Awaiting immediate-head approval';
                ?>
                <tr data-annual-state="<?php echo $m['revision_pending'] ? 'revision' : ($m['current_version'] ? 'finalized' : 'pending'); ?>" data-annual-submitted="<?php echo $m['evaluation_id'] ? '1' : '0'; ?>"><td><?php echo e($m['first_name'].' '.$m['last_name']); ?><?php if ($view==='peers' && $isOwnRow): ?> <span class="badge bg-secondary">You</span><?php endif; ?></td><td><?php echo e($m['position_name']); ?></td>
                    <td><span class="annual-status-pill <?php echo $m['evaluation_id'] ? 'is-complete' : 'is-waiting'; ?>"><?php echo $m['evaluation_id'] ? 'Submitted' : 'Awaiting self-rating'; ?></span></td>
                    <td><?php if ($view==='peers'): ?><span class="annual-status-pill <?php echo $isOwnRow ? ($m['evaluation_id'] ? 'is-self' : 'is-waiting') : ($m['rated'] ? 'is-complete' : 'is-waiting'); ?>"><?php echo $isOwnRow ? ($m['evaluation_id'] ? 'Self-rating included' : 'Awaiting self-rating') : ($m['rated'] ? 'Submitted · Locked' : 'Not submitted'); ?></span><?php else: echo (int)$m['received'].' / '.max(0,count($members)-1); endif; ?></td>
                    <?php if ($view==='peers'): $receivedCount=(int)$m['received']; $expectedCount=annualExpectedPeers($conn,$cycleId,(int)$m['employee_id']); ?>
                    <td class="text-center"><span class="annual-status-pill <?php echo $receivedCount>=$expectedCount && $m['evaluation_id'] ? 'is-complete' : 'is-pending'; ?>" title="<?php echo $receivedCount; ?> of <?php echo $expectedCount; ?> expected coworker ratings submitted; excludes self-rating"><i class="fas fa-users me-1" aria-hidden="true"></i><?php echo $receivedCount.' / '.$expectedCount; ?></span></td>
                    <?php endif; ?>
                    <td><span class="annual-status-pill <?php echo $m['revision_pending'] ? 'is-revision' : ($m['current_version'] ? 'is-complete' : 'is-pending'); ?>"><?php echo e($state); ?></span>
                        <?php if ($m['current_version']): ?><div class="small mt-1">Current final: <?php echo number_format((float)$m['total_score'],2); ?></div><?php endif; ?></td>
                    <td><?php if ($historyView && $target): ?>
                        <a class="btn btn-sm btn-outline-success" href="<?php echo annualUrl($actor,'?view='.(annualCanFinalize($conn,$actor,$target) && $m['rated'] ? 'head-review' : 'review').'&evaluation_id='.(int)$m['evaluation_id']); ?>">View completed evaluation</a>
                    <?php elseif ($view==='peers' && $isOwnRow): ?>
                        <span class="annual-roster-action-muted"><?php echo $m['evaluation_id'] ? 'Cannot rate yourself' : 'Your self-rating is not yet submitted'; ?></span>
                    <?php elseif (!$m['evaluation_id']): ?>
                        <span class="annual-roster-action-muted">Awaiting employee self-rating</span>
                    <?php elseif ($view==='peers' && ($isDepartmentPeer || ($target && annualCanFinalize($conn,$actor,$target))) && annualCanRate($actor) && $m['evaluation_id']): ?>
                        <?php if (empty($own[0]['evaluation_id']) && !annualCanRateBeforeSelf($conn,$actor,$target)): ?>
                            <button class="btn btn-sm btn-outline-secondary" type="button" disabled aria-disabled="true" title="Submit your Annual self-rating first to unlock peer ratings.">Self-rating required</button>
                        <?php elseif (annualCanFinalize($conn,$actor,$target) && $m['rated']): ?>
                            <?php if (!empty($m['revision_pending'])): ?>
                                <a class="btn btn-sm btn-outline-success" href="<?php echo annualUrl($actor,'?view=review&evaluation_id='.(int)$m['evaluation_id']); ?>">Review revised result</a>
                            <?php else: ?>
                                <a class="btn btn-sm btn-outline-success" href="<?php echo annualUrl($actor,'?view=head-review&evaluation_id='.(int)$m['evaluation_id']); ?>">View submitted review</a>
                            <?php endif; ?>
                        <?php elseif (annualCanFinalize($conn,$actor,$target)): ?>
                            <a class="btn btn-sm btn-outline-success" href="<?php echo annualUrl($actor,'?view=rate&evaluation_id='.(int)$m['evaluation_id']); ?>">Rate &amp; review</a>
                        <?php elseif ($m['rated']): ?>
                            <a class="btn btn-sm btn-outline-success" href="<?php echo annualUrl($actor,'?view=review&evaluation_id='.(int)$m['evaluation_id']); ?>">View behavior</a>
                        <?php else: ?>
                            <a class="btn btn-sm btn-outline-success" href="<?php echo annualUrl($actor,'?view=rate&evaluation_id='.(int)$m['evaluation_id']); ?>">Rate</a>
                        <?php endif; ?>
                    <?php elseif ($canRead): ?>
                        <a class="btn btn-sm btn-outline-success" href="<?php echo annualUrl($actor,'?view=review&evaluation_id='.(int)$m['evaluation_id']); ?>">View evaluation</a>
                    <?php else: ?>—<?php endif; ?></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div></div>
    <?php elseif ($view==='head-review'): $headReviewScores=json_decode($headReviewSubmission['scores_json'],true) ?: []; $headReviewSelf=json_decode($member['self_values_json'] ?? '[]',true) ?: []; $headReviewScore=annualCalculate($headReviewKra,$headReviewScores,[]); ?>
        <section class="content-card p-4 annual-head-review-view">
            <div class="annual-submission-heading">
                <div><p class="small text-uppercase mb-1">Immediate-head submission · View only</p><h2 class="h4 mb-1"><?php echo e($member['first_name'].' '.$member['last_name']); ?></h2>
                    <p class="mb-0"><?php echo e($member['position_name'].' · '.$member['period_start'].' to '.$member['period_end']); ?></p></div>
                <span class="annual-status-pill is-complete">Submitted · Locked</span>
            </div>
            <?php
                $annualScoresApproved=$member['evaluation_status']==='Approved';
                $liveSummary=annualCandidate($conn,$member);
                $annualDisplayKra=$annualScoresApproved ? (float)$member['kra_subtotal'] : $liveSummary['kra'];
                $annualDisplayCore=$annualScoresApproved ? (float)$member['behavior_average'] : $liveSummary['core'];
                $annualDisplayTotal=$annualScoresApproved ? (float)$member['official_score'] : $liveSummary['total'];
                $annualTier=$annualDisplayTotal>=3.6 ? 'outstanding' : ($annualDisplayTotal>=2.6 ? 'exceeds' : ($annualDisplayTotal>=2 ? 'meets' : 'needs'));
            ?>
            <div class="mb-4"><?php require __DIR__.'/annual-result-cards.php'; ?></div>

            <?php $headPlans=annualQuery($conn,'SELECT improvement_area,support_needed,time_frame FROM evaluation_dev_plans WHERE evaluation_id=? ORDER BY sort_order,plan_id',[$evaluationId]); ?>
            <section class="annual-submitted-details">
                <nav class="annual-review-tabs annual-head-review-tabs" role="tablist" aria-label="Submitted evaluation sections">
                    <button class="active" id="headKraTab" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#headKraPanel" aria-controls="headKraPanel" aria-selected="true"><i class="fas fa-chart-pie me-2" aria-hidden="true"></i>Key Result Areas <span><?php echo count($headReviewKra); ?></span></button>
                    <button id="headCoreTab" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#headCorePanel" aria-controls="headCorePanel" aria-selected="false"><i class="fas fa-users me-2" aria-hidden="true"></i>Core Behaviors &amp; Values <span>8</span></button>
                    <button id="headPlanTab" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#headPlanPanel" aria-controls="headPlanPanel" aria-selected="false"><i class="fas fa-seedling me-2" aria-hidden="true"></i>Developmental Plan <span><?php echo count($headPlans); ?></span></button>
                    <button id="headRemarksTab" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#headRemarksPanel" aria-controls="headRemarksPanel" aria-selected="false"><i class="fas fa-file-signature me-2" aria-hidden="true"></i>Remarks &amp; Signature</button>
                </nav>
                <div class="tab-content annual-submitted-tab-content">
                    <div class="tab-pane show active" id="headKraPanel" role="tabpanel" aria-labelledby="headKraTab" tabindex="0">
            <h3 class="h5 annual-review-section"><span>Key Result Areas · Immediate-head review</span><small>80% KRA</small></h3>
            <div class="table-responsive annual-score-table-wrap mb-4"><table class="table annual-score-table"><thead><tr><th>Criterion</th><th class="text-end">Weight</th><th class="text-end">Employee self-rating</th><th class="text-end">Reviewed / Adjusted</th><th class="text-center">Variance</th></tr></thead><tbody>
                <?php foreach ($headReviewKra as $row): ?><tr><td><strong><?php echo e($row['criterion_name']); ?></strong><?php if (!empty($row['kpi_description'])): ?><small><?php echo e($row['kpi_description']); ?></small><?php endif; ?></td><td class="text-end"><?php echo number_format((float)$row['weight'],2); ?>%</td><td class="text-end"><?php echo number_format((float)$row['self_rating'],2); ?></td><td class="text-end"><strong><?php echo number_format((float)$row['rating'],2); ?></strong></td><td class="text-center"><?php $difference=round((float)$row['rating']-(float)$row['self_rating'],2); ?><span class="annual-review-variance <?php echo $difference>0 ? 'is-positive' : ($difference<0 ? 'is-negative' : 'is-neutral'); ?>"><?php echo $difference==0 ? '—' : ($difference>0 ? '+' : '').number_format($difference,2); ?></span></td></tr><?php endforeach; ?>
            </tbody></table></div>

                    </div>
                    <div class="tab-pane" id="headCorePanel" role="tabpanel" aria-labelledby="headCoreTab" tabindex="0">
            <h3 class="h5 annual-review-section"><span>Core Behaviors &amp; Values · Head's 360 rating</span><small>20% Core Values</small></h3>
            <div class="table-responsive annual-score-table-wrap mb-4"><table class="table annual-score-table"><thead><tr><th>Behavior</th><th class="text-end">Employee self-rating</th><th class="text-end">Head's submitted rating</th><th class="text-center">Variance</th></tr></thead><tbody>
                <?php foreach (annualCoreValues() as $i=>$value): ?><tr><td><strong><?php echo e($value['name']); ?></strong><small><?php echo e($value['kpi']); ?></small></td><td class="text-end"><?php echo isset($headReviewSelf[$i]) ? number_format((float)$headReviewSelf[$i],2) : '—'; ?></td><td class="text-end"><strong><?php echo number_format((float)($headReviewScores[$i] ?? 0),2); ?></strong></td><td class="text-center"><?php $difference=round((float)($headReviewScores[$i] ?? 0)-(float)($headReviewSelf[$i] ?? 0),2); ?><span class="annual-review-variance <?php echo $difference>0 ? 'is-positive' : ($difference<0 ? 'is-negative' : 'is-neutral'); ?>"><?php echo $difference==0 ? '—' : ($difference>0 ? '+' : '').number_format($difference,2); ?></span></td></tr><?php endforeach; ?>
            </tbody></table></div>

                    </div>
                    <div class="tab-pane" id="headPlanPanel" role="tabpanel" aria-labelledby="headPlanTab" tabindex="0">
            <section class="mb-4"><h3 class="h5 annual-review-section"><span><i class="fas fa-seedling me-2" aria-hidden="true"></i>Developmental Plan</span></h3>
                <?php if (!$headPlans): ?><p class="small text-muted">No developmental plan items added.</p><?php endif; ?>
                <?php if ($headPlans): ?>
                <div class="table-responsive annual-submitted-plan-wrap">
                    <table class="table table-bordered align-middle mb-0 annual-submitted-plan-table">
                        <thead class="table-light"><tr><th>Area for Improvement / Development</th><th>Support Needed / Action Plan</th><th>Target Time Frame</th></tr></thead>
                        <tbody><?php foreach ($headPlans as $plan): ?><tr>
                            <td data-label="Area for Improvement / Development" class="fw-semibold text-dark"><?php echo e($plan['improvement_area'] ?: '—'); ?></td>
                            <td data-label="Support Needed / Action Plan"><?php echo e($plan['support_needed'] ?: '—'); ?></td>
                            <td data-label="Target Time Frame"><span class="badge bg-light text-dark border"><?php echo e($plan['time_frame'] ?: '—'); ?></span></td>
                        </tr><?php endforeach; ?></tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>

                    </div>
                    <div class="tab-pane" id="headRemarksPanel" role="tabpanel" aria-labelledby="headRemarksTab" tabindex="0">
            <section class="annual-rater-signature"><h3 class="h6 fw-bold">Rater signature</h3><?php if (!empty($headReviewSubmission['rater_signature_data'])): ?><img src="<?php echo e($headReviewSubmission['rater_signature_data']); ?>" alt="Submitted immediate-head rater signature" style="display:block;max-width:560px;width:100%;max-height:150px;object-fit:contain;background:#fff;border:1px solid #d5ddd2;border-radius:8px;padding:8px;"><p class="small text-muted mt-2 mb-0">Signed <?php echo e($headReviewSubmission['rater_signed_at'] ?? ''); ?></p><?php else: ?><p class="text-muted mb-0">No signature was saved on this submission.</p><?php endif; ?></section>
            <section class="mt-4"><h3 class="h6 fw-bold"><i class="fas fa-comment-alt me-2" aria-hidden="true"></i>Immediate Head Comments</h3><p class="annual-plan-summary" style="white-space:pre-wrap;"><?php echo e($member['head_comments'] ?: 'No comments added.'); ?></p></section>

                    </div>
                </div>
            </section>
            <a class="btn btn-outline-success mt-4" href="<?php echo annualUrl($actor,'?view=peers&amp;cycle_id='.$cycleId); ?>">Back to Department Peers</a>
        </section>
    <?php elseif ($view==='rate'): ?>
        <section class="content-card p-4 annual-rate-view <?php echo !$canFinalize ? 'annual-peer-rate-view' : ''; ?>">
            <header class="annual-rating-employee-header">
                <div><h2 class="h4"><?php echo e($member['first_name'].' '.$member['last_name']); ?></h2>
            <p><?php echo e($member['position_name']); ?> · <?php echo e($member['period_start'].' to '.$member['period_end']); ?></p></div>
                <span class="annual-status-pill <?php echo $submitted ? 'is-complete' : 'is-self'; ?>"><i class="fas fa-users me-1" aria-hidden="true"></i><?php echo $submitted ? 'Submitted · Locked' : ($canFinalize ? 'Immediate-head review' : 'Department peer rating'); ?></span>
            </header>
            <?php if ($canFinalize && !$submitted):
                // Reference only: exclude head overrides and department peer ratings.
                $selfSummaryKras=annualKraRows($conn,$evaluationId);
                foreach ($selfSummaryKras as &$selfSummaryRow) {
                    $selfSummaryRow['rating']=$selfSummaryRow['self_rating'];
                }
                unset($selfSummaryRow);
                $selfSummary=annualCalculate($selfSummaryKras,json_decode($member['self_values_json'] ?? '[]',true) ?: [],[]);
                $annualScoresApproved=false;
                $annualDisplayKra=$selfSummary['kra'];
                $annualDisplayCore=$selfSummary['core'];
                $annualDisplayTotal=$selfSummary['total'];
                $annualTier='meets';
            ?>
                <section class="mb-4" aria-label="Employee submitted self-rating summary">
                    <h3 class="h6 fw-bold mb-2">Employee self-rating</h3>
                    <p class="small text-muted mb-3">Submitted scores for reference. Your review changes and department peer ratings are not included here.</p>
                    <?php require __DIR__.'/annual-result-cards.php'; ?>
                </section>
            <?php endif; ?>
            <p id="annualRatingProgress" class="small fw-bold" aria-live="polite"></p>
            <?php if ($submitted): ?><div class="alert alert-success">Submitted and locked. These ratings cannot be edited.</div><?php endif; ?>
            <form method="post" id="annualRatingForm" data-draft-key="annual-rating-overrides-v3-<?php echo (int)$actor['user_id']; ?>-<?php echo $evaluationId; ?>-<?php echo substr(hash('sha256',json_encode([$member['employee_id'],$member['evaluation_created_at'],$member['evaluation_submitted_at'],$member['period_start'],$member['period_end']])),0,24); ?>" data-resume-tab="<?php echo $_SERVER['REQUEST_METHOD']==='POST' ? '1' : '0'; ?>" data-submitted="<?php echo $submitted ? '1' : '0'; ?>" data-override-only="<?php echo $canFinalize && !$submitted ? '1' : '0'; ?>">
                <p class="small text-muted">Use ratings from 1.00 to 4.00. Decimals are allowed, up to two decimal places (for example, 3.25).</p>
                <p id="annualRatingScaleNotice" class="alert alert-warning" role="status" hidden></p>
                <?php if ($canFinalize && !$submitted): ?><p id="annualHeadOverallPreview" class="alert alert-success" aria-live="polite"></p><?php endif; ?>
                <?php echo csrfField(); ?><input type="hidden" name="action" value="rate"><input type="hidden" name="cycle_id" value="<?php echo $cycleId; ?>"><input type="hidden" name="confirmed" id="annualConfirmed" value="0">
                <?php if ($canFinalize && !$submitted): $rateKras=annualKraRows($conn,$evaluationId); ?>
                    <input type="hidden" name="review_token" value="<?php echo annualReviewToken($conn,$member); ?>">
                    <div class="alert alert-info">You are this employee's immediate head. Leave KRA and behavior fields blank to retain the employee's submitted ratings. Enter a value only for an item you want to change. Both will save together after confirmation.</div>
                    <nav class="annual-review-tabs" role="tablist" aria-label="Rating sections">
                        <button class="active" id="annualRateKraTab" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#annualRateKraPanel" aria-controls="annualRateKraPanel" aria-selected="true"><i class="fas fa-chart-pie me-2" aria-hidden="true"></i>Key Result Areas · Immediate-head review <span><?php echo count($rateKras); ?></span></button>
                        <button id="annualRateCoreTab" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#annualRateCorePanel" aria-controls="annualRateCorePanel" aria-selected="false"><i class="fas fa-users me-2" aria-hidden="true"></i>Core Behaviors &amp; Values · Your 360 rating <span>8</span></button>
                        <button id="annualRatePlanTab" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#annualRatePlanPanel" aria-controls="annualRatePlanPanel" aria-selected="false"><i class="fas fa-seedling me-2" aria-hidden="true"></i>Developmental Plan</button>
                        <button id="annualRateReviewTab" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#annualRateReviewPanel" aria-controls="annualRateReviewPanel" aria-selected="false"><i class="fas fa-clipboard-check me-2" aria-hidden="true"></i>Review &amp; Submit</button>
                    </nav>
                    <div class="tab-content p-3">
                    <div class="tab-pane show active annual-probation-section" id="annualRateKraPanel" role="tabpanel" aria-labelledby="annualRateKraTab" tabindex="0">
                    <h3 class="h5 annual-review-section"><span><i class="fas fa-chart-pie me-2" aria-hidden="true"></i>Key Result Areas · Immediate-head review</span><small>KRA Weight: 80%</small></h3>
                    <div class="table-responsive annual-score-table-wrap mb-3"><table class="table annual-score-table"><thead><tr><th>Criterion</th><th class="text-end">Weight</th><th class="text-end">Employee Self-Rating</th><th>Evaluator Adjusted Rating</th></tr></thead><tbody>
                    <?php foreach ($rateKras as $r): ?><tr><td><strong><?php echo e($r['criterion_name']); ?></strong><?php if (!empty($r['kpi_description'])): ?><small><?php echo e($r['kpi_description']); ?></small><?php endif; ?></td><td class="text-end"><?php echo number_format((float)$r['weight'],2); ?>%</td><td class="text-end"><?php echo number_format((float)$r['self_rating'],2); ?></td><td><input class="form-control annual-number annual-rate-kra" type="number" inputmode="decimal" min="1" max="4" step="0.01" data-weight="<?php echo e($r['weight']); ?>" data-submitted-rating="<?php echo e($r['self_rating']); ?>" name="kra[<?php echo (int)$r['score_id']; ?>]" aria-label="Reviewed KRA: <?php echo e($r['criterion_name']); ?>" data-label="KRA · <?php echo e($r['criterion_name']); ?>" placeholder="Unchanged" value="<?php echo e($_POST['kra'][$r['score_id']] ?? ''); ?>"><span class="annual-rating-unit">/ 4.00</span></td></tr><?php endforeach; ?>
                    </tbody><tfoot><tr><th colspan="2">Key Result Areas (KRA) total</th><td class="text-end"><?php echo number_format(round(array_sum(array_map(static fn($r)=>(float)$r['weight']*(float)$r['self_rating']/100,$rateKras)),2),2); ?></td><td class="text-end"><strong id="annualRateKraTotal">—</strong><small>Weight: 80%</small></td></tr></tfoot></table></div>
                    <div class="annual-rating-step-actions"><span></span><button class="btn btn-success" type="button" id="annualRateKraNext" data-annual-tab="#annualRateCorePanel" aria-controls="annualRateCorePanel">Next: Core Behaviors <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i></button></div>
                    </div><div class="tab-pane annual-probation-section" id="annualRateCorePanel" role="tabpanel" aria-labelledby="annualRateCoreTab" tabindex="0">
                    <h3 class="h5 annual-review-section"><span><i class="fas fa-users me-2" aria-hidden="true"></i>Core Behaviors &amp; Values · Your 360 rating</span><small>Behavior Weight: 20%</small></h3>
                <?php elseif ($canFinalize): ?>
                    <a class="btn btn-outline-success mb-3" href="<?php echo annualUrl($actor,'?view=review&evaluation_id='.$evaluationId); ?>">Review KRA &amp; consolidate Annual result</a>
                <?php endif; ?>
                <?php if (!$canFinalize || $submitted): ?><section class="annual-probation-section annual-peer-rating-section" id="annualRateCorePanel"><h3 class="h5 annual-review-section"><span><i class="fas fa-users me-2" aria-hidden="true"></i>Core Behaviors &amp; Values · Your 360 rating</span><small>Behavior Weight: 20%</small></h3><?php endif; ?>
                <?php if ($canFinalize && !$submitted): ?><p class="small text-muted">Leave blank to retain the employee's self-rating. Enter a value only to override that item.</p><?php endif; ?>
                <?php if (!$canFinalize && !$submitted): ?><p class="small text-muted">Enter your rating for each behavior below. Use the employee’s self-rating as a reference. All eight ratings are required.</p><?php endif; ?>
                <div class="table-responsive annual-score-table-wrap"><table class="table align-middle annual-rating-table annual-score-table"><thead><tr><th>Criterion</th><th class="text-end">Employee self-rating</th><th class="text-end">Your 360 rating (1–4)</th></tr></thead><tbody>
                <?php $selfValues=json_decode($member['self_values_json'] ?? '[]',true) ?: [];
                $scores=$submitted ? json_decode($submitted['scores_json'],true) : ($_POST['scores'] ?? []);
                foreach (annualCoreValues() as $i=>$value): ?>
                    <tr><td><strong><?php echo e($value['name']); ?></strong><small><?php echo e($value['kpi']); ?></small></td><td class="text-end"><strong><?php echo isset($selfValues[$i]) ? number_format((float)$selfValues[$i],2) : '—'; ?></strong></td><td>
                        <input class="form-control annual-number" type="number" inputmode="decimal" min="1" max="4" step="0.01" <?php echo $canFinalize ? 'placeholder="Unchanged"' : 'placeholder="1.00–4.00" required'; ?>
                            name="scores[<?php echo $i; ?>]" aria-label="Rating for <?php echo e($value['name']); ?>" data-label="<?php echo e($value['name']); ?>" data-self-rating="<?php echo e($selfValues[$i] ?? ''); ?>"
                            value="<?php echo isset($scores[$i]) && is_numeric($scores[$i]) ? e(number_format((float)$scores[$i],2,'.','')) : e($scores[$i] ?? ''); ?>" <?php echo $submitted ? 'disabled' : ''; ?>><span class="annual-rating-unit">/ 4.00</span>
                    </td></tr>
                <?php endforeach; ?></tbody><tfoot><tr><th>Average</th><td class="text-end"><strong><?php echo count($selfValues) ? number_format(array_sum(array_map('floatval',$selfValues))/count($selfValues),2) : '—'; ?></strong><small>Self-rating</small></td><td class="text-end"><strong id="annualPeerRatingAverage">—</strong><small>Your 360</small></td></tr></tfoot></table></div>
                <?php if (!$canFinalize || $submitted): ?></section><?php endif; ?>
                <?php if ($canFinalize && !$submitted): ?><div class="annual-rating-step-actions"><button class="btn btn-outline-success" type="button" id="annualRateCoreBack" data-annual-tab="#annualRateKraPanel" aria-controls="annualRateKraPanel"><i class="fas fa-arrow-left me-2" aria-hidden="true"></i>Back to KRA</button><button class="btn btn-success" type="button" id="annualRateCoreNext" data-annual-tab="#annualRatePlanPanel" aria-controls="annualRatePlanPanel">Next: Developmental Plan <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i></button></div><?php endif; ?>
                <?php if ($canFinalize && !$submitted): ?></div>
                    <div class="tab-pane annual-probation-section" id="annualRatePlanPanel" role="tabpanel" aria-labelledby="annualRatePlanTab" tabindex="0">
                        <h3 class="h5 annual-review-section"><span><i class="fas fa-seedling me-2" aria-hidden="true"></i>Developmental Plan</span><small>Immediate-head recommendations</small></h3>
                        <p class="text-muted small mb-3">Specify growth areas, required support, and target completion timelines for this employee. Complete each added item or leave the section blank.</p>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle annual-development-table">
                                <thead class="table-light"><tr><th style="width:35%">Area for Improvement / Development</th><th style="width:35%">Support Needed / Action Plan</th><th style="width:20%">Target Time Frame</th><th style="width:10%" class="text-center">Action</th></tr></thead>
                                <tbody id="annualPlanRows" data-initial="<?php echo e(json_encode($_POST['plans'] ?? annualQuery($conn,'SELECT improvement_area,support_needed,time_frame FROM evaluation_dev_plans WHERE evaluation_id=? ORDER BY sort_order,plan_id',[$evaluationId]))); ?>"></tbody>
                            </table>
                        </div>
                        <p class="annual-plan-empty" id="annualPlanEmpty" hidden>No developmental items added.</p>
                        <button type="button" class="btn btn-sm btn-outline-success mt-2" id="annualAddPlan"><i class="fas fa-plus me-1" aria-hidden="true"></i>Add Development Item</button>
                        <div class="annual-rating-step-actions"><button type="button" class="btn btn-outline-success" data-annual-tab="#annualRateCorePanel">Back to Core Behaviors</button><button type="button" class="btn btn-success" data-annual-tab="#annualRateReviewPanel">Next: Review &amp; Submit <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i></button></div>
                    </div>
                    <div class="tab-pane" id="annualRateReviewPanel" role="tabpanel" aria-labelledby="annualRateReviewTab" tabindex="0">
                        <h3 class="h5 annual-review-section"><span><i class="fas fa-clipboard-check me-2" aria-hidden="true"></i>Review &amp; Submit Ratings</span></h3>
                        <p>Check the effective KRA and behavior ratings before approving. Blank overrides retain the employee's submitted ratings. Confirmation approves the employee's evaluation and locks your ratings. The overall score updates automatically as remaining department members submit their 360 ratings.</p>
                        <section class="annual-review-summary-group" aria-labelledby="annualReviewKraHeading">
                            <h4 class="h6" id="annualReviewKraHeading">Key Result Areas · Immediate-head review <span>80% of final score</span></h4>
                            <div class="table-responsive annual-score-table-wrap"><table class="table table-sm annual-score-table"><thead><tr><th>KRA item</th><th class="text-end">Weight</th><th class="text-end">Submitted</th><th class="text-end">Reviewed</th></tr></thead><tbody id="annualRatingPreviewKraRows"><tr><td colspan="4" class="text-muted">No KRA ratings available.</td></tr></tbody><tfoot><tr><th colspan="3">Weighted KRA subtotal</th><td class="text-end"><strong id="annualRatingPreviewKraTotal">—</strong></td></tr></tfoot></table></div>
                        </section>
                        <section class="annual-review-summary-group mt-4" aria-labelledby="annualReviewBehaviorHeading">
                            <h4 class="h6" id="annualReviewBehaviorHeading">Core Behaviors &amp; Values · 360 rating <span>20% of final score</span></h4>
                            <div class="table-responsive annual-score-table-wrap"><table class="table table-sm annual-score-table"><thead><tr><th>Behavior</th><th class="text-end">Employee self-rating</th><th class="text-end">Your 360 rating</th></tr></thead><tbody id="annualRatingPreviewBehaviorRows"><tr><td colspan="3" class="text-muted">Complete the behavior ratings in the previous tab to review them here.</td></tr></tbody><tfoot><tr><th colspan="2">Your 360 average</th><td class="text-end"><strong id="annualRatingPreviewBehaviorAverage">—</strong></td></tr></tfoot></table></div>
                        </section>
                        <section class="annual-review-summary-group mt-4"><h4 class="h6"><i class="fas fa-seedling me-2" aria-hidden="true"></i>Developmental Plan</h4><div id="annualPlanPreview"></div></section>
                        <div class="annual-rater-signature mt-4">
                            <label class="form-label fw-bold" for="annualRaterSignatureCanvas">Rater signature <span class="text-danger">*</span></label>
                            <p class="small text-muted mb-2">Sign below. Your signature will appear in the Rater field on the printed evaluation form.</p>
                            <canvas id="annualRaterSignatureCanvas" width="560" height="140" aria-label="Draw your rater signature" style="display:block;width:100%;max-width:560px;height:140px;touch-action:none;background:#fff;border:1px dashed #94a3b8;border-radius:8px;"></canvas>
                            <input type="hidden" name="rater_signature_data" id="annualRaterSignatureData" value="<?php echo e($_POST['rater_signature_data'] ?? ''); ?>">
                            <div id="annualRaterSignatureError" class="small text-danger mt-1" role="alert" hidden>Please sign before submitting.</div>
                            <button class="btn btn-sm btn-outline-secondary mt-2" type="button" id="annualClearRaterSignature">Clear signature</button>
                            <div class="mt-4">
                                <label class="form-label fw-bold" for="annualHeadComments"><i class="fas fa-comment-alt me-2" aria-hidden="true"></i>Immediate Head Comments</label>
                                <p class="small text-muted mb-2">Add feedback or recommendations for the employee. These comments will appear on the printed evaluation form.</p>
                                <textarea class="form-control" id="annualHeadComments" name="head_comments" rows="4" maxlength="4000" placeholder="Enter your feedback or recommendations…"><?php echo e($_POST['head_comments'] ?? $member['head_comments'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="annual-rating-step-actions"><button class="btn btn-outline-success" type="button" id="annualRateReviewBack" data-annual-tab="#annualRatePlanPanel" aria-controls="annualRatePlanPanel"><i class="fas fa-arrow-left me-2" aria-hidden="true"></i>Back to Developmental Plan</button><button class="btn btn-success" type="button" id="annualReviewRatings">Review &amp; submit ratings</button></div>
                    </div></div><?php endif; ?>
                <?php if (!$canFinalize): ?><div class="annual-rating-step-actions"><a class="btn btn-outline-success" href="<?php echo e(annualUrl($actor,'?view=peers&cycle_id='.$cycleId)); ?>"><i class="fas fa-arrow-left me-2" aria-hidden="true"></i>Back to Department Peers</a><?php if (!$submitted): ?><button class="btn btn-success" type="button" id="annualReviewRatings"><i class="fas fa-clipboard-check me-2" aria-hidden="true"></i>Review &amp; submit ratings</button><?php endif; ?></div><?php endif; ?>
            </form>
        </section>
        <div class="modal fade" id="annualRatingSummary" tabindex="-1" aria-labelledby="annualSummaryTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h2 id="annualSummaryTitle" class="modal-title h5">Are you sure you want to submit?</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><p>For <?php echo e($member['first_name'].' '.$member['last_name']); ?></p>
                <div id="annualSummaryKraSection"><h3 class="h6">Key Result Areas · 80%</h3><div class="table-responsive"><table class="table table-sm"><thead><tr><th>KRA item</th><th class="text-end">Weight</th><th class="text-end">Submitted</th><th class="text-end">Reviewed</th></tr></thead><tbody id="annualSummaryKraRows"></tbody><tfoot><tr><th colspan="3">Weighted KRA subtotal</th><td class="text-end"><strong id="annualSummaryKraTotal">—</strong></td></tr></tfoot></table></div></div>
                <h3 class="h6 mt-3">Core Behaviors &amp; Values · 360 rating · 20%</h3><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Behavior</th><th class="text-end">Employee self-rating</th><th class="text-end">Your 360 rating</th></tr></thead><tbody id="annualSummaryBehaviorRows"></tbody><tfoot><tr><th colspan="2">Your 360 average</th><td class="text-end"><strong id="annualSummaryBehaviorAverage">—</strong></td></tr></tfoot></table></div>
                <?php if ($canFinalize): ?><section class="mt-3"><h3 class="h6">Developmental Plan</h3><div id="annualPlanConfirmation"></div><h3 class="h6 mt-3">Immediate Head Comments</h3><p id="annualHeadCommentsConfirmation" style="white-space:pre-wrap;overflow-wrap:anywhere;"></p></section><?php endif; ?>
                <div class="alert alert-warning"><strong>This can’t be undone.</strong> After confirmation, your ratings and rater signature are locked and cannot be edited. Please verify the summary before continuing.</div></div>
            <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Back to edit</button><button class="btn btn-success" id="annualConfirmSubmit">Yes, submit and lock</button></div>
        </div></div></div>
    <?php elseif ($member):
        $peers=annualPeers($conn,$member,$named);
        $self=json_decode($member['self_values_json'],true);
        $candidate=annualCandidate($conn,$member);
        if ((int)$member['current_version']>0 && !$member['revision_pending']) {
            $currentSnapshot=annualQuery($conn,'SELECT snapshot_json FROM annual_evaluation_versions WHERE member_id=? AND version_number=?',[(int)$member['member_id'],(int)$member['current_version']])[0] ?? null;
            $currentSnapshot=$currentSnapshot ? json_decode($currentSnapshot['snapshot_json'],true) : [];
            if (!empty($currentSnapshot['core_adjustments'])) $candidate=annualApplyCoreAdjustments($candidate,$currentSnapshot['core_adjustments']);
        }
        $kra=annualKraRows($conn,$evaluationId);
        $submittedKraTotal=round(array_sum(array_map(static fn($row)=>(float)$row['weight']*(float)$row['self_rating']/100,$kra)),2);
        $versions=annualQuery($conn,'SELECT version_number,finalized_at,remarks,snapshot_json FROM annual_evaluation_versions WHERE member_id=? ORDER BY version_number DESC',[(int)$member['member_id']]);
        $editable=$view==='review' && $canFinalize && (!$member['current_version'] || $member['revision_pending']);
        $expected=annualExpectedPeers($conn,$cycleId,(int)$member['employee_id']);
        ?>
        <?php if (!$canFinalize && $sameDepartmentPeerView): ?>
        <section class="content-card p-4 mb-4" id="annual360">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h2 class="h4"><?php echo e($member['first_name'].' '.$member['last_name']); ?> · Core Behaviors &amp; Values</h2>
                    <p><?php echo e($member['position_name'].' · '.$member['period_start'].' to '.$member['period_end']); ?></p>
                    <p class="small text-muted mb-0">Behavior results include the names of employees who submitted each 360 rating.</p>
                </div>
            </div>
            <?php if (count($peers)>=$expected):
                $annualScoresApproved=$member['evaluation_status']==='Approved';
                $annualDisplayKra=$annualScoresApproved ? (float)$member['kra_subtotal'] : $candidate['kra'];
                $annualDisplayCore=$annualScoresApproved ? (float)$member['behavior_average'] : $candidate['core'];
                $annualDisplayTotal=$annualScoresApproved ? (float)$member['official_score'] : $candidate['total'];
                $annualTier=$annualDisplayTotal>=3.6 ? 'outstanding' : ($annualDisplayTotal>=2.6 ? 'exceeds' : ($annualDisplayTotal>=2 ? 'meets' : 'needs'));
            ?>
            <div class="mb-4"><?php require __DIR__.'/annual-result-cards.php'; ?></div>
            <?php endif; ?>
            <div class="table-responsive annual-score-table-wrap"><table class="table align-middle annual-score-table annual-core-score-table"><thead><tr><th>Criterion</th><th class="text-end">Weight</th><th class="text-end">Self-rating</th><?php foreach ($peers as $i=>$p): ?><th class="text-end"><?php echo e($p['rater_name']); ?></th><?php endforeach; ?><th class="text-end">Combined average</th></tr></thead><tbody>
            <?php foreach (annualCoreValues() as $i=>$value): ?><tr><td><strong><?php echo e($value['name']); ?></strong><small><?php echo e($value['kpi']); ?></small></td><td class="text-end">2.50%</td><td class="text-end"><?php echo number_format((float)$self[$i],2); ?></td><?php foreach ($peers as $p): $peerScores=json_decode($p['scores_json'],true); ?><td class="text-end"><?php echo number_format((float)$peerScores[$i],2); ?></td><?php endforeach; ?><td class="text-end"><?php echo number_format($candidate['item_averages'][$i],2); ?></td></tr><?php endforeach; ?>
            </tbody><tfoot><tr><th colspan="<?php echo 3+count($peers); ?>">Core Behaviors &amp; Values total</th><td class="text-end"><strong><?php echo number_format($candidate['core'],2); ?></strong><small>Weight: 20%</small></td></tr></tfoot></table></div>
        </section>
        <?php else: ?>
        <section class="content-card p-4 mb-4" id="annualOverview">
            <?php if ((int)$member['current_version']>0): $scoreComplete=count($peers)>=annualExpectedPeers($conn,$cycleId,(int)$member['employee_id']); ?>
            <div class="alert <?php echo $scoreComplete ? 'alert-success' : 'alert-info'; ?>" role="status"><strong>Approved · <?php echo $scoreComplete ? 'Score complete' : 'Score provisional'; ?></strong><p class="mb-0"><?php echo $scoreComplete ? 'All required department ratings have been received. Your score is complete.' : 'Your immediate head has approved your evaluation. Your overall score may change as remaining department members submit their 360 ratings. You will be notified when your score changes.'; ?></p></div>
            <?php endif; ?>
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
        <?php if ($editable): ?>
        <form method="post" class="content-card annual-review-card mb-4" id="annualConsolidationForm" data-core="<?php echo $candidate['core']; ?>">
            <?php echo csrfField(); ?><input type="hidden" name="action" value="finalize"><input type="hidden" name="review_token" value="<?php echo annualReviewToken($conn,$member); ?>">
        <?php else: ?>
        <section class="content-card annual-review-card mb-4" aria-label="Annual evaluation details">
        <?php endif; ?>
            <div class="annual-review-tabs" role="tablist" aria-label="Evaluation sections">
                <button class="active" id="annualKraTab" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#annualKraPanel" aria-controls="annualKraPanel" aria-selected="true"><i class="fas fa-chart-pie me-2"></i>Key Result Areas (KRA) <span><?php echo count($kra); ?></span></button>
                <button id="annualCoreTab" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#annualCorePanel" aria-controls="annualCorePanel" aria-selected="false"><i class="fas fa-users me-2"></i>Core Behaviors &amp; Values <span>8</span></button>
                <button id="annualDecisionTab" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#annualDecisionPanel" aria-controls="annualDecisionPanel" aria-selected="false"><i class="fas fa-file-signature me-2"></i>Consolidation &amp; Final Score</button>
            </div>
            <div class="tab-content p-4">
            <div class="tab-pane show active" id="annualKraPanel" role="tabpanel" aria-labelledby="annualKraTab" tabindex="0">
            <?php if ($editable): ?><div class="alert alert-info"><strong>How to Adjust Ratings:</strong> Enter the evaluator adjusted rating between 1.00 and 4.00. The proposed final score recalculates automatically.</div><?php endif; ?>
            <h3 class="h5 annual-review-section" id="annualKra"><span><i class="fas fa-chart-pie me-2"></i>Key Result Areas (KRA)</span><small>KRA Weight: 80%</small></h3>
            <div class="table-responsive annual-score-table-wrap"><table class="table align-middle annual-score-table"><thead><tr><th>Criterion</th><th class="text-end">Weight</th><th class="text-end">Submitted rating</th><th class="text-end"><?php echo $editable ? 'Reviewed rating' : 'Final rating'; ?></th></tr></thead><tbody>
            <?php foreach ($kra as $row): ?><tr><td><strong><?php echo e($row['criterion_name']); ?></strong><?php if (!empty($row['kpi_description'])): ?><small><?php echo e($row['kpi_description']); ?></small><?php endif; ?></td><td class="text-end"><?php echo number_format((float)$row['weight'],2); ?>%</td><td class="text-end"><?php echo number_format((float)$row['self_rating'],2); ?></td><td class="text-end">
                <?php if ($editable): ?><input class="form-control annual-number" type="number" inputmode="decimal" min="1" max="4" step="0.01" required data-weight="<?php echo e($row['weight']); ?>" name="kra[<?php echo (int)$row['score_id']; ?>]" aria-label="Reviewed rating for <?php echo e($row['criterion_name']); ?>" value="<?php echo e($_POST['kra'][$row['score_id']] ?? $row['rating']); ?>">
                <?php else: echo number_format((float)$row['rating'],2); endif; ?></td></tr><?php endforeach; ?></tbody>
                <tfoot><tr><th colspan="2">Key Result Areas (KRA) total</th><td class="text-end"><?php echo number_format($submittedKraTotal,2); ?></td><td class="text-end"><strong id="annualReviewedKraTotal"><?php echo number_format($candidate['kra'],2); ?></strong><small>Weight: 80%</small></td></tr></tfoot></table></div>
            </div>
            <div class="tab-pane" id="annualCorePanel" role="tabpanel" aria-labelledby="annualCoreTab" tabindex="0">
            <h3 class="h5 annual-review-section" id="annual360"><span><i class="fas fa-users me-2"></i>Core Behaviors &amp; Values · 360 Results</span><small>Behavior Weight: 20%</small></h3>
            <p class="small text-muted"><?php echo $named ? 'Rater names are visible to authorized consolidators only.' : 'Coworker identities are hidden. Employee labels identify anonymous responses only.'; ?></p>
            <div class="table-responsive annual-score-table-wrap"><table class="table annual-score-table annual-core-score-table"><thead><tr><th>Criterion</th><th class="text-end">Weight</th><th class="text-end">Self-rating</th>
                <?php foreach ($peers as $i=>$p): ?><th class="text-end"><?php echo e($named ? $p['rater_name'] : 'Employee '.($i+1)); ?></th><?php endforeach; ?><th class="text-end"><?php echo $editable ? 'Final reviewed rating' : 'Combined average'; ?></th></tr></thead><tbody>
            <?php foreach (annualCoreValues() as $i=>$value): ?><tr><td><strong><?php echo e($value['name']); ?></strong><small><?php echo e($value['kpi']); ?></small></td><td class="text-end">2.50%</td><td class="text-end"><?php echo number_format((float)$self[$i],2); ?></td>
                <?php foreach ($peers as $p): $pScores=json_decode($p['scores_json'],true); ?><td><?php echo number_format((float)$pScores[$i],2); ?></td><?php endforeach; ?><td>
                <?php if ($editable): ?><input form="annualConsolidationForm" class="form-control annual-number annual-core-adjustment" type="number" inputmode="decimal" min="1" max="4" step="0.01" required name="core_adjustments[<?php echo $i; ?>]" aria-label="Final reviewed rating for <?php echo e($value['name']); ?>" value="<?php echo e($_POST['core_adjustments'][$i] ?? number_format($candidate['item_averages'][$i],2,'.','')); ?>">
                <?php else: echo number_format($candidate['item_averages'][$i],2); endif; ?></td></tr><?php endforeach; ?>
            </tbody><tfoot><tr><th colspan="<?php echo 3+count($peers); ?>">Core Behaviors &amp; Values total</th><td class="text-end"><strong id="annualReviewedCoreTotal"><?php echo number_format($candidate['core'],2); ?></strong><small>Weight: 20%</small></td></tr></tfoot></table></div>
            </div>
            <div class="tab-pane" id="annualDecisionPanel" role="tabpanel" aria-labelledby="annualDecisionTab" tabindex="0">
            <h3 class="h5 annual-review-section"><?php echo (int)$member['current_version'] ? 'Approved Annual Result · View Only' : 'Annual Review Status'; ?></h3>
            <p><?php echo $view==='results' && !(int)$member['current_version']
                ? 'The immediate-head KRA review and submitted 360 ratings are locked. Annual finalization will be completed after department peer participation is complete.'
                : 'Approved KRA (80%) + combined Core Values result (20%). Later peer submissions update the overall score automatically. Previous score versions are preserved below.'; ?></p>
            <?php if ($editable): ?>
                <section class="annual-head-review-score mb-4 p-3 rounded" id="annualProposedResult" aria-label="Proposed weighted annual score" aria-live="polite">
                    <div class="annual-head-review-score-icon" aria-hidden="true"><i class="fas fa-chart-line"></i></div>
                    <div class="annual-head-review-score-details">
                        <div class="annual-head-review-score-kicker">Weighted result · 80/20</div>
                        <h4 class="h6 mb-2">Proposed overall score</h4>
                        <div class="annual-head-review-score-breakdown">
                            <div class="annual-head-review-score-part"><span>KRA <b>80%</b></span><strong id="annualProposedKra"><?php echo number_format($candidate['kra'],2); ?></strong></div>
                            <i class="fas fa-plus annual-head-review-score-operator" aria-hidden="true"></i>
                            <div class="annual-head-review-score-part annual-head-review-score-part--behavior"><span>Behavior <b>20%</b></span><strong id="annualProposedCore"><?php echo number_format($candidate['core'],2); ?></strong></div>
                        </div>
                    </div>
                    <div class="annual-head-review-score-total"><span>Overall score</span><strong id="annualProposedTotal"><?php echo number_format($candidate['total'],2); ?></strong><small>KRA × 80% + Behavior × 20%</small></div>
                </section>
                <p class="small text-muted">Adjust each behavior’s combined score from 1.00 to 4.00 if needed. These reviewed Core Values scores and the reviewed KRA scores will be saved together in the finalized version.</p>
                <label for="annualRemarks" class="form-label fw-bold">Review remarks</label>
                <textarea id="annualRemarks" class="form-control mb-3" name="remarks" rows="3" placeholder="Explain adjustments, missing participation, or late revisions."><?php echo e($_POST['remarks'] ?? ''); ?></textarea>
                <?php if (count($peers)<$expected): ?><p class="text-warning-emphasis" id="annualParticipationNotice">Some raters have not submitted. Finalization is unavailable until all <?php echo $expected; ?> expected raters have submitted their 360 ratings (<?php echo count($peers); ?> received). Refresh this review after they submit.</p><?php endif; ?>
                <button class="btn btn-success" type="submit" <?php echo count($peers)<$expected ? 'disabled aria-disabled="true" aria-describedby="annualParticipationNotice"' : ''; ?>><?php echo $member['current_version'] ? 'Approve &amp; finalize revised Annual result' : 'Approve &amp; finalize Annual evaluation'; ?></button>
            <?php endif; ?>
            </div></div>
        <?php if ($editable): ?></form><?php else: ?></section><?php endif; ?>
        <?php if ($versions): ?><section class="content-card p-4" id="annualHistory"><h3 class="h5">Approval &amp; Score History</h3>
            <?php foreach ($versions as $v): $snapshot=json_decode($v['snapshot_json'],true); ?>
                <div class="border rounded p-3 mb-2"><strong>Version <?php echo (int)$v['version_number']; ?> · <?php echo number_format($snapshot['total'],2); ?></strong>
                    <p class="small mb-1"><?php echo e($v['finalized_at']); ?> · KRA <?php echo number_format($snapshot['kra'],2); ?> · Core Values <?php echo number_format($snapshot['core'],2); ?> · <?php echo (int)$snapshot['peer_count']; ?> peer ratings</p>
                    <p class="mb-0"><?php echo e($v['remarks']); ?></p></div>
            <?php endforeach; ?>
        </section><?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
function annualEffectiveRating(input) {
    if (!input.checkValidity()) return null;
    const raw=input.value!=='' ? input.value : (input.closest('form')?.dataset.overrideOnly==='1' ? (input.dataset.submittedRating ?? input.dataset.selfRating ?? '') : '');
    return raw!=='' && Number.isFinite(Number(raw)) && Number(raw)>=1 && Number(raw)<=4 ? Number(raw) : null;
}
function updateAnnualProgress() {
    const inputs=[...document.querySelectorAll('#annualRatingForm input[name^="scores["]')];
    const progress=document.getElementById('annualRatingProgress');
    const rated=inputs.filter(input=>annualEffectiveRating(input)!==null);
    if (progress) {
        progress.textContent=rated.length+' of 8 values rated';
        if (rated.length===8) progress.textContent+=' · Your 360 average: '+(rated.reduce((sum,input)=>sum+annualEffectiveRating(input),0)/8).toFixed(2);
    }
    const coreAverage=document.getElementById('annualPeerRatingAverage');
    if (coreAverage) coreAverage.textContent=rated.length===8 ? (rated.reduce((sum,input)=>sum+annualEffectiveRating(input),0)/8).toFixed(2) : '—';
    const kraInputs=[...document.querySelectorAll('#annualRatingForm input.annual-rate-kra')];
    const kraTotal=document.getElementById('annualRateKraTotal');
    if (kraTotal) kraTotal.textContent=kraInputs.length && kraInputs.every(input=>annualEffectiveRating(input)!==null)
        ? kraInputs.reduce((sum,input)=>sum+Number(input.dataset.weight)/100*annualEffectiveRating(input),0).toFixed(2) : '—';
    const overallPreview=document.getElementById('annualHeadOverallPreview');
    if (overallPreview) {
        const valid=kraInputs.length>0 && inputs.length===8 && [...kraInputs,...inputs].every(input=>annualEffectiveRating(input)!==null);
        if (valid) {
            const kra=Math.round((kraInputs.reduce((sum,input)=>sum+Math.round(Number(input.dataset.weight)/100*annualEffectiveRating(input)*100)/100,0)+Number.EPSILON)*100)/100;
            const core=Math.round((inputs.reduce((sum,input)=>sum+annualEffectiveRating(input),0)/8+Number.EPSILON)*100)/100;
            const total=Math.round((kra*.8+core*.2+Number.EPSILON)*100)/100;
            const label=total>=3.6 ? 'Outstanding' : total>=2.6 ? 'Exceeds Expectations' : total>=2 ? 'Meets Expectations' : 'Needs Improvement';
            overallPreview.textContent='Proposed overall score: '+total.toFixed(2)+' / 4.00 · '+label+' — (KRA '+kra.toFixed(2)+' × 80%) + (Behavior '+core.toFixed(2)+' × 20%).';
        } else overallPreview.textContent='Complete all ratings from 1.00 to 4.00 to preview the overall score.';
    }
    renderAnnualRatingPreview();
    annualRenderPlanSummary();
}
const annualRatingForm=document.getElementById('annualRatingForm');
const annualDraftKey=annualRatingForm?.dataset.draftKey;
document.getElementById('annualHeadComments')?.addEventListener('input',()=>saveAnnualRatingDraft());
// Match the probationary form's separate section-total bars, retaining score IDs.
[['annualRateKraPanel','Key Result Areas (KRA) Total','annualRateKraTotal'],['annualRateCorePanel','Core Behaviors & Values Total','annualPeerRatingAverage']].forEach(([panelId,title,scoreId])=>{
    const panel=document.getElementById(panelId), score=document.getElementById(scoreId);
    const footer=score?.closest('tfoot'); if(!panel || !footer) return;
    const self=Number.parseFloat(footer.rows[0].cells[1].textContent);
    const bar=document.createElement('div'); bar.className='annual-section-total';
    const label=document.createElement('strong'); label.textContent=title+':';
    const values=document.createElement('div'); values.className='annual-section-total-values';
    const selfLabel=document.createElement('span'); selfLabel.textContent='Self-Rating: '+self.toFixed(2);
    const box=document.createElement('span'); box.className='annual-section-total-box';
    box.append(document.createTextNode('Total Score: '),score,document.createTextNode(' / 4.00'));
    values.append(selfLabel,box); bar.append(label,values);
    const wrap=footer.closest('.table-responsive'); footer.remove(); wrap.after(bar);
});
let annualPlanIndex=0;
function annualReadPlans() {
    return [...document.querySelectorAll('#annualPlanRows .annual-plan-row')].map(row=>Object.fromEntries(
        [...row.querySelectorAll('[data-annual-plan-field]')].map(input=>[input.dataset.annualPlanField,input.value])
    ));
}
function annualValidatePlanFields() {
    document.querySelectorAll('#annualPlanRows .annual-plan-row').forEach(row=>{
        const fields=[...row.querySelectorAll('[data-annual-plan-field]')];
        const hasContent=fields.some(field=>field.value.trim()!=='');
        fields.forEach(field=>{ field.required=hasContent; field.setCustomValidity(hasContent && field.value.trim()==='' ? 'Complete this field or remove this development goal.' : ''); });
    });
}
function annualUpdatePlanLayout() {
    const rows=[...document.querySelectorAll('#annualPlanRows .annual-plan-row')];
    rows.forEach((row,index)=>{ row.setAttribute('aria-label','Development item '+(index+1)); });
    const add=document.getElementById('annualAddPlan');
    if(add) add.disabled=rows.length>=20;
    const empty=document.getElementById('annualPlanEmpty');
    if(empty) empty.hidden=rows.length>0;
}
function annualAddPlanRow(plan={}) {
    const container=document.getElementById('annualPlanRows');
    if (!container || container.children.length>=20) return;
    const index=annualPlanIndex++;
    const row=document.createElement('tr'); row.className='annual-plan-row';
    [['improvement_area','Area for Improvement / Development',2000,'e.g. Advanced Excel / Financial Analysis'],['support_needed','Support Needed / Action Plan',4000,'e.g. External Training / Peer Mentoring'],['time_frame','Target Time Frame',100,'e.g. Q3 2026 / 3 Months']].forEach(([name,label,limit,placeholder])=>{
        const cell=document.createElement('td'); cell.dataset.label=label;
        const input=document.createElement('input'); input.type='text'; input.className='form-control';
        input.id='annualPlan-'+index+'-'+name; input.name='plans['+index+']['+name+']'; input.dataset.annualPlanField=name;
        input.setAttribute('aria-label',label); input.maxLength=limit; input.value=plan[name] || ''; input.placeholder=placeholder;
        input.addEventListener('input',()=>{annualValidatePlanFields(); annualRenderPlanSummary(); saveAnnualRatingDraft();});
        cell.appendChild(input); row.appendChild(cell);
    });
    const actions=document.createElement('td'); actions.className='text-center'; actions.dataset.label='Action';
    const remove=document.createElement('button'); remove.type='button'; remove.className='btn btn-sm btn-outline-danger';
    remove.title='Remove Row'; remove.setAttribute('aria-label','Remove developmental item');
    const trash=document.createElement('i'); trash.className='fas fa-trash-alt'; trash.setAttribute('aria-hidden','true'); remove.appendChild(trash);
    remove.addEventListener('click',()=>{row.remove(); annualUpdatePlanLayout(); annualRenderPlanSummary(); saveAnnualRatingDraft();});
    actions.appendChild(remove); row.appendChild(actions); container.appendChild(row); annualUpdatePlanLayout();
}
function annualRenderPlanSummary() {
    const plans=annualReadPlans().filter(plan=>Object.values(plan).some(value=>value.trim()!==''));
    ['annualPlanPreview','annualPlanConfirmation'].forEach(id=>{
        const target=document.getElementById(id); if(!target) return;
        target.replaceChildren();
        if(!plans.length) { const note=document.createElement('p'); note.className='small text-muted mb-0'; note.textContent='No developmental plan items added.'; target.appendChild(note); return; }
        plans.forEach((plan,index)=>{
            const block=document.createElement('div'); block.className='annual-plan-summary';
            [['Goal '+(index+1),plan.improvement_area],['Action / support',plan.support_needed],['Target time frame',plan.time_frame]].forEach(([label,value])=>{
                const line=document.createElement('p'); const title=document.createElement('strong'); title.textContent=label+': ';
                line.append(title,document.createTextNode(value || 'Not entered')); block.appendChild(line);
            }); target.appendChild(block);
        });
    });
}
const annualPlanRows=document.getElementById('annualPlanRows');
if (annualPlanRows) {
    try { const initial=Object.values(JSON.parse(annualPlanRows.dataset.initial || '[]')); (initial.length ? initial : [{}]).forEach(plan=>annualAddPlanRow(plan)); } catch (_) { annualAddPlanRow(); }
    document.getElementById('annualAddPlan')?.addEventListener('click',()=>{ annualAddPlanRow(); saveAnnualRatingDraft(); });
}
function saveAnnualRatingDraft(panelOverride=null) {
    if (!annualRatingForm || !annualDraftKey || annualRatingForm.dataset.submitted==='1') return;
    const values={};
    annualRatingForm.querySelectorAll('input[type="number"]').forEach(input=>{ values[input.name]=input.value; });
    const activePanel=panelOverride || document.querySelector('.annual-review-tabs button.active')?.dataset.bsTarget || '#annualRateKraPanel';
    try { sessionStorage.setItem(annualDraftKey,JSON.stringify({values,activePanel,behaviorDefaultsVersion:1,plans:annualReadPlans(),headComments:document.getElementById('annualHeadComments')?.value || '',signature:document.getElementById('annualRaterSignatureData')?.value || ''})); } catch (_) {}
}
function restoreAnnualRatingDraft() {
    if (!annualRatingForm || !annualDraftKey) return;
    try {
        if (annualRatingForm.dataset.submitted==='1') sessionStorage.removeItem(annualDraftKey);
        else {
            const draft=JSON.parse(sessionStorage.getItem(annualDraftKey) || 'null');
            if (draft && draft.values) annualRatingForm.querySelectorAll('input[type="number"]').forEach(input=>{
                if (Object.prototype.hasOwnProperty.call(draft.values,input.name)) {
                    input.value=draft.values[input.name];
                }
            });
            if (Array.isArray(draft?.plans) && document.getElementById('annualPlanRows')) {
                document.getElementById('annualPlanRows').replaceChildren();
                draft.plans.forEach(plan=>annualAddPlanRow(plan));
                annualUpdatePlanLayout();
            }
            const comments=document.getElementById('annualHeadComments');
            if(comments && typeof draft?.headComments==='string') comments.value=draft.headComments;
            const signature=document.getElementById('annualRaterSignatureData');
            if (signature && draft?.signature) signature.value=draft.signature;
            const navigationType=performance.getEntriesByType('navigation')[0]?.type;
            const resumeTab=annualRatingForm.dataset.resumeTab==='1' || navigationType==='reload' || navigationType==='back_forward';
            const activeTrigger=resumeTab && draft?.activePanel && document.querySelector('.annual-review-tabs [data-bs-target="'+draft.activePanel+'"]');
            if (activeTrigger && window.bootstrap?.Tab) bootstrap.Tab.getOrCreateInstance(activeTrigger).show();
            document.dispatchEvent(new Event('annualRatingDraftRestored'));
        }
    } catch (_) {}
    annualRatingForm.querySelectorAll('input[type="number"]').forEach(formatAnnualRatingDecimal);
    updateAnnualProgress();
}
function formatAnnualRatingDecimal(input) {
    if (input.value!=='' && input.checkValidity() && Number.isFinite(Number(input.value))) input.value=Number(input.value).toFixed(2);
}
if (annualRatingForm && annualDraftKey) {
    if (document.readyState==='loading') document.addEventListener('DOMContentLoaded',restoreAnnualRatingDraft,{once:true});
    else restoreAnnualRatingDraft();
}
document.querySelectorAll('#annualRatingForm input[type="number"]').forEach(input=>{
    const enforceRatingScale=()=>{
        const notice=document.getElementById('annualRatingScaleNotice');
        let message='';
        if(input.value!=='' && Number.isFinite(Number(input.value)) && Number(input.value)>4) {
            input.value='4.00';
            message='The maximum rating is 4.00. Your entry has been limited to 4.00.';
        } else if(input.value!=='' && !input.checkValidity()) {
            message='Enter a rating from 1.00 to 4.00 with no more than two decimal places.';
        }
        input.classList.toggle('is-invalid',input.value!=='' && !input.checkValidity());
        input.setAttribute('aria-invalid',String(input.value!=='' && !input.checkValidity()));
        if (notice) { notice.textContent=message; notice.hidden=!message; }
        updateAnnualProgress(); saveAnnualRatingDraft();
    };
    input.addEventListener('input',enforceRatingScale);
    input.addEventListener('change',enforceRatingScale);
    input.addEventListener('blur',()=>{ formatAnnualRatingDecimal(input); updateAnnualProgress(); saveAnnualRatingDraft(); });
});
updateAnnualProgress();
document.querySelectorAll('.annual-review-tabs [data-bs-toggle="tab"]').forEach(trigger=>{
    trigger.addEventListener('click',()=>saveAnnualRatingDraft(trigger.dataset.bsTarget));
    trigger.addEventListener('shown.bs.tab',()=>saveAnnualRatingDraft(trigger.dataset.bsTarget));
});
document.querySelectorAll('#annualRatingForm [data-annual-tab]').forEach(button=>button.addEventListener('click',()=>{
    const trigger=document.querySelector('.annual-review-tabs [data-bs-target="'+button.dataset.annualTab+'"]');
    if (trigger) { bootstrap.Tab.getOrCreateInstance(trigger).show(); trigger.focus(); saveAnnualRatingDraft(button.dataset.annualTab); }
}));
function renderAnnualRatingPreview() {
    const form=document.getElementById('annualRatingForm');
    if (!form || !document.getElementById('annualRatingPreviewKraRows')) return;
    renderAnnualRatingTables(form,'annualRatingPreviewKraRows','annualRatingPreviewKraTotal',
        'annualRatingPreviewBehaviorRows','annualRatingPreviewBehaviorAverage');
}
function renderAnnualRatingTables(form,kraBodyId,kraTotalId,behaviorBodyId,behaviorAverageId) {
    const kraBody=document.getElementById(kraBodyId), behaviorBody=document.getElementById(behaviorBodyId);
    const kraInputs=[...form.querySelectorAll('input.annual-rate-kra')];
    const behaviorInputs=[...form.querySelectorAll('input[name^="scores["]')];
    if (kraBody) {
        kraBody.replaceChildren();
        kraInputs.forEach(input=>{
            const row=document.createElement('tr');
            [input.dataset.label || 'KRA',Number(input.dataset.weight).toFixed(2)+'%',input.dataset.submittedRating || '—',annualEffectiveRating(input)!==null ? annualEffectiveRating(input).toFixed(2)+(form.dataset.overrideOnly==='1' ? (input.value==='' || Number(input.value)===Number(input.dataset.submittedRating ?? input.dataset.selfRating) ? ' · Retained' : ' · Modified') : '') : 'Invalid or missing'].forEach((text,index)=>{
                const cell=document.createElement('td'); cell.textContent=text; if(index) cell.className='text-end'; row.append(cell);
            });
            kraBody.append(row);
        });
        const subtotal=kraInputs.length && kraInputs.every(input=>annualEffectiveRating(input)!==null)
            ? kraInputs.reduce((sum,input)=>sum+Number(input.dataset.weight)/100*annualEffectiveRating(input),0) : null;
        const total=document.getElementById(kraTotalId); if(total) total.textContent=subtotal===null ? '—' : subtotal.toFixed(2);
    }
    if (behaviorBody) {
        behaviorBody.replaceChildren();
        behaviorInputs.forEach(input=>{
            const row=document.createElement('tr');
            [input.dataset.label || 'Behavior',input.dataset.selfRating || '—',annualEffectiveRating(input)!==null ? annualEffectiveRating(input).toFixed(2)+(form.dataset.overrideOnly==='1' ? (input.value==='' || Number(input.value)===Number(input.dataset.submittedRating ?? input.dataset.selfRating) ? ' · Retained' : ' · Modified') : '') : 'Invalid or missing'].forEach((text,index)=>{
                const cell=document.createElement('td'); cell.textContent=text; if(index) cell.className='text-end'; row.append(cell);
            });
            behaviorBody.append(row);
        });
        const average=behaviorInputs.length && behaviorInputs.every(input=>annualEffectiveRating(input)!==null)
            ? behaviorInputs.reduce((sum,input)=>sum+annualEffectiveRating(input),0)/behaviorInputs.length : null;
        const total=document.getElementById(behaviorAverageId); if(total) total.textContent=average===null ? '—' : average.toFixed(2);
    }
}
(() => {
    const canvas=document.getElementById('annualRaterSignatureCanvas');
    const input=document.getElementById('annualRaterSignatureData');
    if (!canvas || !input) return;
    const context=canvas.getContext('2d');
    context.strokeStyle='#14241a'; context.fillStyle='#14241a'; context.lineWidth=2.4; context.lineCap='round'; context.lineJoin='round';
    let drawing=false, hasInk=false;
    const point=event=>{ const rect=canvas.getBoundingClientRect(); return {x:(event.clientX-rect.left)*canvas.width/rect.width,y:(event.clientY-rect.top)*canvas.height/rect.height}; };
    const restoreSignature=()=>{ if(input.value) { const image=new Image(); image.onload=()=>{context.drawImage(image,0,0,canvas.width,canvas.height);hasInk=true;}; image.src=input.value; } };
    restoreSignature(); document.addEventListener('annualRatingDraftRestored',restoreSignature);
    canvas.addEventListener('pointerdown',event=>{ drawing=true; hasInk=true; canvas.setPointerCapture(event.pointerId); const p=point(event); context.beginPath(); context.arc(p.x,p.y,1.2,0,Math.PI*2); context.fill(); context.beginPath(); context.moveTo(p.x,p.y); event.preventDefault(); });
    canvas.addEventListener('pointermove',event=>{ if(!drawing) return; const p=point(event); context.lineTo(p.x,p.y); context.stroke(); event.preventDefault(); });
    const finish=()=>{ if(!drawing) return; drawing=false; input.value=hasInk ? canvas.toDataURL('image/png') : ''; document.getElementById('annualRaterSignatureError')?.setAttribute('hidden',''); saveAnnualRatingDraft(); };
    canvas.addEventListener('pointerup',finish); canvas.addEventListener('pointercancel',finish);
    document.getElementById('annualClearRaterSignature')?.addEventListener('click',()=>{ context.clearRect(0,0,canvas.width,canvas.height); hasInk=false; input.value=''; saveAnnualRatingDraft(); });
})();
function updateAnnualProposedResult() {
    const form=document.getElementById('annualConsolidationForm'), result=document.getElementById('annualProposedResult');
    if (!form || !result) return;
    const round2=value=>Math.round((value+Number.EPSILON)*100)/100;
    const inputs=[...form.querySelectorAll('input[data-weight]')];
    if (inputs.some(input=>input.value==='' || !input.checkValidity())) { result.textContent='Complete valid KRA ratings to preview the proposed score.'; return; }
    const kra=round2(inputs.reduce((sum,input)=>sum+round2(Number(input.dataset.weight)/100*annualEffectiveRating(input)),0));
    const kraTotal=document.getElementById('annualReviewedKraTotal');
    if (kraTotal) kraTotal.textContent=kra.toFixed(2);
    const coreInputs=[...form.querySelectorAll('.annual-core-adjustment')];
    if (coreInputs.some(input=>input.value==='' || !input.checkValidity())) { result.textContent='Complete valid Core Values ratings to preview the proposed score.'; return; }
    const core=round2(coreInputs.length ? coreInputs.reduce((sum,input)=>sum+Number(input.value),0)/8 : Number(form.dataset.core));
    const coreTotal=document.getElementById('annualReviewedCoreTotal');
    if (coreTotal) coreTotal.textContent=core.toFixed(2);
    const total=round2(kra*.8+core*.2);
    document.getElementById('annualProposedKra').textContent=kra.toFixed(2);
    document.getElementById('annualProposedCore').textContent=core.toFixed(2);
    document.getElementById('annualProposedTotal').textContent=total.toFixed(2);
    result.setAttribute('aria-label','Proposed overall score '+total.toFixed(2)+'. KRA '+kra.toFixed(2)+' times 80 percent plus Behavior '+core.toFixed(2)+' times 20 percent.');
}
document.querySelectorAll('#annualConsolidationForm input[data-weight]').forEach(input=>input.addEventListener('input',updateAnnualProposedResult));
document.querySelectorAll('#annualConsolidationForm .annual-core-adjustment').forEach(input=>input.addEventListener('input',updateAnnualProposedResult));
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
    const commentsPreview=document.getElementById('annualHeadCommentsConfirmation');
    if(commentsPreview) commentsPreview.textContent=document.getElementById('annualHeadComments')?.value.trim() || 'No comments added.';
    annualValidatePlanFields();
    const invalid=[...form.querySelectorAll('input,textarea')].find(input=>!input.checkValidity());
    if (invalid) {
        const panel=invalid.closest('.tab-pane');
        const trigger=panel && document.querySelector('[data-bs-target="#'+panel.id+'"]');
        if (trigger) bootstrap.Tab.getOrCreateInstance(trigger).show();
        invalid.reportValidity();
        return;
    }
    const signature=document.getElementById('annualRaterSignatureData');
    if (signature && !signature.value) {
        document.getElementById('annualRaterSignatureError')?.removeAttribute('hidden');
        document.querySelector('.annual-review-tabs [data-bs-target="#annualRateReviewPanel"]')?.click();
        document.getElementById('annualRaterSignatureCanvas')?.scrollIntoView({behavior:'smooth',block:'center'});
        return;
    }
    renderAnnualRatingTables(form,'annualSummaryKraRows','annualSummaryKraTotal',
        'annualSummaryBehaviorRows','annualSummaryBehaviorAverage');
    const kraSection=document.getElementById('annualSummaryKraSection');
    if (kraSection) kraSection.hidden=!form.querySelector('input.annual-rate-kra');
    // The signature is validated above and included in the POST. Keep it out of
    // the behavior ratings table: that table's body only contains rating rows.
    new bootstrap.Modal(document.getElementById('annualRatingSummary')).show();
});
document.getElementById('annualConfirmSubmit')?.addEventListener('click', function() {
    const form=document.getElementById('annualRatingForm');
    if (!form) return;
    document.getElementById('annualConfirmed').value='1';
    this.disabled=true;
    form.requestSubmit();
});
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
