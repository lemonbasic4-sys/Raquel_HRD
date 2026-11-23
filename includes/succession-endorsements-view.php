<style>
.succession-endorsement-list { background:#fff; border:1px solid #e3e9dd; border-radius:16px; box-shadow:0 12px 30px rgba(8,46,6,.07); overflow:hidden; }
.succession-endorsement-list .succession-panel-header { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1rem 1.1rem; background:#f7faf4; border-bottom:1px solid #e3e9dd; }
.succession-endorsement-list .succession-panel-title { margin:0; color:#1c271b; font-size:1rem; font-weight:800; }
.succession-endorsement-list .succession-panel-title i { color:#bd9414; margin-right:.5rem; }
.succession-status-tabs { display:flex; gap:.45rem; overflow-x:auto; padding:.35rem; margin-bottom:1rem; background:#f4f8f1; border:1px solid #e3e9dd; border-radius:12px; scrollbar-width:thin; }
.succession-status-tab { display:inline-flex; align-items:center; justify-content:center; gap:.4rem; flex:1 0 auto; min-height:42px; padding:.55rem .8rem; border:0; border-radius:9px; background:transparent; color:#395039; font-size:.82rem; font-weight:700; white-space:nowrap; }
.succession-status-tab[aria-selected="true"] { background:#0a3b19; color:#fff; box-shadow:0 3px 8px rgba(8,46,6,.16); }
.succession-status-tab .badge { background:rgba(15,61,30,.1); color:inherit; }
.succession-status-tab[aria-selected="true"] .badge { background:rgba(255,255,255,.2); }
.succession-endorsement-toolbar { display:grid; grid-template-columns:minmax(0,1fr) minmax(210px,280px); gap:.75rem; align-items:end; margin-bottom:1rem; }
.succession-endorsement-toolbar .succession-status-tabs { margin-bottom:0; }
.succession-department-filter label { display:block; margin:0 0 .3rem; color:#4b5d49; font-size:.7rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
.succession-department-filter .form-select { min-height:44px; border-color:#dce6d6; border-radius:10px; color:#29442c; font-size:.8rem; font-weight:650; background-color:#fff; }
.succession-endorsement-card[hidden], .succession-status-empty[hidden] { display:none !important; }
.succession-endorsement-entry { overflow:hidden; border:1px solid #dce6d6; border-radius:16px; background:#fff; box-shadow:0 8px 22px rgba(8,46,6,.06); transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease; }
.succession-endorsement-entry:hover { border-color:#bfd0b8; box-shadow:0 14px 30px rgba(8,46,6,.1); transform:translateY(-1px); }
.succession-endorsement-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1rem 1.2rem; background:linear-gradient(135deg,#f8fbf6,#f2f7ee); border-bottom:1px solid #e3e9dd; }
.succession-candidate-identity { display:flex; align-items:center; gap:.75rem; min-width:0; }
.succession-candidate-avatar { display:grid; place-items:center; flex:0 0 44px; width:44px; height:44px; overflow:hidden; border:2px solid #fff; border-radius:13px; background:#174d19; box-shadow:0 3px 10px rgba(8,46,6,.16); color:#fff; font-size:.82rem; font-weight:800; }
.succession-candidate-avatar img { width:100%; height:100%; object-fit:cover; }
.succession-candidate-name { display:block; overflow:hidden; color:#173b20; font-size:1.02rem; font-weight:800; text-overflow:ellipsis; white-space:nowrap; }
.succession-candidate-meta { display:flex; flex-wrap:wrap; gap:.3rem .65rem; margin-top:.2rem; color:#63705f; font-size:.73rem; }
.succession-candidate-meta span { display:inline-flex; align-items:center; gap:.3rem; }
.succession-status-pill { display:inline-flex; align-items:center; gap:.38rem; flex:0 0 auto; padding:.42rem .65rem; border-radius:999px; font-size:.72rem; font-weight:800; }
.succession-status-pill.approved { background:#e5f5e9; color:#16703b; }
.succession-status-pill.rejected { background:#fde9e9; color:#b52c36; }
.succession-status-pill.returned { background:#fff2d5; color:#87620b; }
.succession-status-pill.ongoing { background:#e8f1fb; color:#245b8f; }
.succession-endorsement-layout { display:grid; grid-template-columns:minmax(0,1fr) minmax(320px,.9fr); }
.succession-endorsement-layout.no-action { grid-template-columns:minmax(0,1fr); }
.succession-endorsement-details { min-width:0; padding:1.1rem 1.2rem; }
.succession-endorsement-overview { display:grid; grid-template-columns:minmax(240px,.8fr) minmax(0,1.2fr); gap:1rem; }
.succession-position-route { display:grid; grid-template-columns:minmax(0,1fr) 32px minmax(0,1fr); align-items:stretch; padding:.8rem; border:1px solid #dfe8da; border-radius:12px; background:#f8fbf6; }
.succession-position-block { min-width:0; }
.succession-position-block small { display:block; margin-bottom:.25rem; color:#778274; font-size:.65rem; font-weight:800; letter-spacing:.055em; text-transform:uppercase; }
.succession-position-block strong { display:block; color:#183f21; font-size:.84rem; line-height:1.35; }
.succession-position-block.target { padding-left:.7rem; border-left:1px dashed #bdcdb6; }
.succession-position-arrow { display:grid; place-items:center; color:#bd9414; }
.succession-reason-card { margin-top:.75rem; padding:.8rem .9rem; border-left:4px solid #bd9414; border-radius:0 10px 10px 0; background:#fffbef; }
.succession-reason-card .reason-label { display:block; margin-bottom:.25rem; color:#78621d; font-size:.67rem; font-weight:800; letter-spacing:.05em; text-transform:uppercase; }
.succession-reason-card p { margin:0; color:#364134; font-size:.8rem; line-height:1.5; }
.succession-workflow { position:relative; display:grid; gap:.72rem; }
.succession-workflow-step { position:relative; display:grid; grid-template-columns:32px minmax(0,1fr); gap:.65rem; }
.succession-workflow-step:not(:last-child)::after { content:""; position:absolute; top:31px; bottom:-12px; left:15px; width:2px; background:#dfe7db; }
.succession-step-icon { position:relative; z-index:1; display:grid; place-items:center; width:32px; height:32px; border-radius:50%; background:#eaf4e8; color:#24612d; font-size:.72rem; }
.succession-workflow-step.pending .succession-step-icon { background:#edf1f4; color:#78827a; }
.succession-workflow-step.rejected .succession-step-icon { background:#fde9e9; color:#bb3038; }
.succession-step-body { min-width:0; padding-top:.05rem; }
.succession-step-title { display:flex; flex-wrap:wrap; justify-content:space-between; gap:.25rem .65rem; color:#173b20; font-size:.77rem; font-weight:800; }
.succession-step-time { color:#7b8578; font-size:.65rem; font-weight:600; }
.succession-step-body p { margin:.15rem 0 0; color:#596655; font-size:.75rem; line-height:1.42; }
.succession-verification-chips { display:flex; flex-wrap:wrap; gap:.38rem; margin-top:.75rem; }
.succession-verification-chip { display:inline-flex; align-items:center; gap:.32rem; padding:.3rem .48rem; border-radius:999px; background:#edf7ef; color:#26723e; font-size:.65rem; font-weight:700; }
.succession-endorsement-footer { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.75rem; margin-top:.85rem; padding-top:.85rem; border-top:1px solid #e8ede5; }
.succession-endorsement-id { color:#879083; font-size:.66rem; }
.succession-endorsement-pagination { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-top:1rem; padding:.75rem .85rem; border:1px solid #e2e9de; border-radius:11px; background:#f8faf6; }
.succession-page-summary { color:#667262; font-size:.72rem; font-weight:650; }
.succession-page-controls { display:flex; align-items:center; gap:.45rem; }
.succession-page-controls .btn { min-width:38px; min-height:34px; border-radius:8px; }
.succession-page-indicator { min-width:84px; color:#2f472f; font-size:.72rem; font-weight:800; text-align:center; }
.succession-endorsement-action { min-width:0; margin:0; padding:1.1rem 1.2rem; border-left:1px solid #e3e9dd; background:#fbfcfa; }
.succession-endorsement-action .form-control { min-height:104px; }
.succession-endorsement-action label.d-block { margin:.35rem 0; line-height:1.4; }
.succession-endorsement-action .d-flex > .btn { min-height:38px; }
.succession-approved-note { margin-top:.8rem; padding:.7rem .8rem; border:1px solid #b9dec3; border-radius:10px; background:#edf8ef; color:#17472a; font-size:.75rem; line-height:1.45; }
@media(max-width:1199.98px) { .succession-endorsement-overview { grid-template-columns:minmax(0,1fr); } }
@media(max-width:991.98px) { .succession-endorsement-layout { grid-template-columns:minmax(0,1fr); } .succession-endorsement-action { border-left:0; border-top:1px solid #e3e9dd; } }
@media(max-width:767.98px) { .succession-endorsement-toolbar { grid-template-columns:minmax(0,1fr); } }
@media(max-width:575.98px) { .succession-endorsement-head { align-items:flex-start; } .succession-candidate-meta { display:block; } .succession-candidate-meta span { display:flex; margin-top:.15rem; } .succession-position-route { grid-template-columns:minmax(0,1fr); gap:.55rem; } .succession-position-arrow { justify-content:start; transform:rotate(90deg); } .succession-position-block.target { padding:.55rem 0 0; border-top:1px dashed #bdcdb6; border-left:0; } .succession-endorsement-details,.succession-endorsement-action { padding:.9rem; } .succession-endorsement-action .d-flex > .btn { flex:1 1 100%; } .succession-endorsement-pagination { align-items:stretch; flex-direction:column; } .succession-page-controls { justify-content:space-between; } }
@media(prefers-reduced-motion:reduce) { .succession-endorsement-entry { transition:none; } }
</style>
<?php
$successionStatusCounts = ['all'=>count($successionEndorsements),'ongoing'=>0,'approved'=>0,'rejected'=>0,'returned'=>0];
$successionSelectedStatus = $_SESSION['succession_selected_status'] ?? null;
unset($_SESSION['succession_selected_status']);
$formatEndorsementDate = static function (?string $value): string {
    if (!$value || !($timestamp = strtotime($value))) return 'Not recorded';
    return date('M j, Y · g:i A', $timestamp);
};
foreach ($successionEndorsements as $endorsement) {
    $group = match ($endorsement['status']) {
        'Pending HR Review', 'Pending CEO Approval' => 'ongoing',
        'Approved' => 'approved',
        'Rejected' => 'rejected',
        'Returned' => 'returned',
        default => null,
    };
    if ($group !== null) $successionStatusCounts[$group]++;
}
$successionEndorsementDepartments = [];
foreach ($successionEndorsements as $endorsement) {
    $departmentId = (int)($endorsement['department_id'] ?? 0);
    if ($departmentId > 0 && !empty($endorsement['department_name'])) {
        $successionEndorsementDepartments[$departmentId] = $endorsement['department_name'];
    }
}
asort($successionEndorsementDepartments, SORT_NATURAL | SORT_FLAG_CASE);
?>
<section class="succession-panel succession-endorsement-list mb-4" id="endorsements" aria-labelledby="endorsementsTitle">
    <div class="succession-panel-header">
        <h6 class="succession-panel-title" id="endorsementsTitle"><i class="fas fa-file-signature"></i>Promotion Endorsements</h6>
        <span class="badge succession-count-badge"><?php echo count($successionEndorsements); ?> recorded</span>
    </div>
    <div class="p-3 p-xl-4">
        <p class="text-muted small mb-3">Follow each promotion from supervisor recommendation through HR verification and the President/CEO's final decision. Approved positions are applied to the employee record; pay remains unchanged.</p>
        <?php if (($_SESSION['role'] ?? '') === 'HR Manager' && empty($successionCeoAvailable)): ?><div class="alert alert-warning small">No active Employee Portal account is linked to the President and CEO. Create or activate the linked account through Admin before forwarding an endorsement for final approval.</div><?php endif; ?>
        <?php if (!$successionEndorsements): ?>
            <p class="text-muted mb-0">No endorsements yet.</p>
        <?php else: ?>
            <div class="succession-endorsement-toolbar">
                <div class="succession-status-tabs" role="tablist" aria-label="Filter promotion endorsements by status">
                    <?php foreach (['ongoing'=>'Ongoing','approved'=>'Approved','rejected'=>'Rejected','returned'=>'Returned','all'=>'All'] as $key=>$label): ?>
                    <button type="button" class="succession-status-tab" role="tab" data-succession-status="<?php echo $key; ?>" aria-selected="false" aria-controls="successionEndorsementCards"><?php echo $label; ?> <span class="badge rounded-pill"><?php echo $successionStatusCounts[$key]; ?></span></button>
                    <?php endforeach; ?>
                </div>
                <div class="succession-department-filter">
                    <label for="successionDepartmentFilter"><i class="fas fa-sitemap me-1" aria-hidden="true"></i>Department</label>
                    <select class="form-select" id="successionDepartmentFilter">
                        <option value="all">All departments</option>
                        <?php foreach ($successionEndorsementDepartments as $departmentId=>$departmentName): ?>
                        <option value="<?php echo (int)$departmentId; ?>"><?php echo e($departmentName); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <p class="succession-status-empty text-muted mb-0" hidden>No endorsements in this status.</p>
            <div class="row g-3" id="successionEndorsementCards" role="tabpanel" aria-label="Promotion endorsements">
            <?php foreach ($successionEndorsements as $endorsement):
                $status = $endorsement['status'];
                $statusGroup = match ($status) { 'Pending HR Review', 'Pending CEO Approval'=>'ongoing', 'Approved'=>'approved', 'Rejected'=>'rejected', 'Returned'=>'returned', default=>'all' };
                $isHrAction = ($_SESSION['role'] ?? '') === 'HR Manager' && $status === 'Pending HR Review';
                $isCeoAction = in_array(($_SESSION['role'] ?? ''), ['Employee', 'President and CEO'], true)
                    && successionIsCeoUser($conn, (int)($_SESSION['user_id'] ?? 0)) && $status === 'Pending CEO Approval';
                $statusClass = match ($status) { 'Approved'=>'approved','Rejected'=>'rejected','Returned'=>'returned',default=>'ongoing' };
                $statusIcon = match ($status) { 'Approved'=>'fa-check','Rejected'=>'fa-times','Returned'=>'fa-undo',default=>'fa-clock' };
                $nameParts = preg_split('/\s+/', trim((string)$endorsement['employee_name'])) ?: [];
                $initials = strtoupper(substr($nameParts[0] ?? 'E',0,1).substr($nameParts[count($nameParts)-1] ?? '',0,1));
                $avatar = !empty($endorsement['profile_picture']) ? getEmployeeAvatar($endorsement['profile_picture']) : '';
                $displayRecommendation = preg_replace('/^\[PRESENTATION-SEED:[^\]]+\]\s*/', '', (string)$endorsement['recommendation_reason']);
            ?>
                <div class="col-12 succession-endorsement-card" data-status-group="<?php echo e($statusGroup); ?>" data-department-id="<?php echo (int)($endorsement['department_id'] ?? 0); ?>">
                    <article class="succession-endorsement-entry">
                        <div class="succession-endorsement-head">
                            <div class="succession-candidate-identity">
                                <span class="succession-candidate-avatar">
                                    <?php if ($avatar && !str_contains($avatar,'/logo/logo.png')): ?><img src="<?php echo e($avatar); ?>" alt=""><?php else: ?><?php echo e($initials); ?><?php endif; ?>
                                </span>
                                <span class="min-w-0">
                                    <span class="succession-candidate-name"><?php echo e($endorsement['employee_name']); ?></span>
                                    <span class="succession-candidate-meta">
                                        <span><i class="fas fa-sitemap" aria-hidden="true"></i><?php echo e($endorsement['department_name'] ?: 'Department unavailable'); ?></span>
                                        <span><i class="fas fa-map-marker-alt" aria-hidden="true"></i><?php echo e($endorsement['branch_name'] ?: 'Branch unavailable'); ?></span>
                                    </span>
                                </span>
                            </div>
                            <span class="succession-status-pill <?php echo $statusClass; ?>"><i class="fas <?php echo $statusIcon; ?>" aria-hidden="true"></i><?php echo e($status); ?></span>
                        </div>
                        <div class="succession-endorsement-layout<?php echo ($isHrAction || $isCeoAction) ? '' : ' no-action'; ?>">
                        <div class="succession-endorsement-details">
                        <div class="succession-endorsement-overview">
                            <div>
                                <div class="succession-position-route" aria-label="Position change">
                                    <div class="succession-position-block"><small>Current position</small><strong><?php echo e($endorsement['current_title']); ?></strong></div>
                                    <div class="succession-position-arrow"><i class="fas fa-arrow-right" aria-hidden="true"></i></div>
                                    <div class="succession-position-block target"><small>Proposed position</small><strong><?php echo e($endorsement['target_title']); ?></strong></div>
                                </div>
                                <div class="succession-reason-card"><span class="reason-label"><i class="fas fa-quote-left me-1" aria-hidden="true"></i>Recommendation basis</span><p><?php echo nl2br(e($displayRecommendation)); ?></p></div>
                                <?php if ($endorsement['hr_reviewed_at']): ?>
                                <div class="succession-verification-chips" aria-label="HR verification checks">
                                    <?php if ($endorsement['qualifications_verified']): ?><span class="succession-verification-chip"><i class="fas fa-check-circle"></i>Qualifications</span><?php endif; ?>
                                    <?php if ($endorsement['opportunity_verified']): ?><span class="succession-verification-chip"><i class="fas fa-check-circle"></i>Position need</span><?php endif; ?>
                                    <?php if ($endorsement['records_verified']): ?><span class="succession-verification-chip"><i class="fas fa-check-circle"></i>Records</span><?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="succession-workflow" aria-label="Endorsement workflow">
                                <div class="succession-workflow-step">
                                    <span class="succession-step-icon"><i class="fas fa-user-check"></i></span>
                                    <div class="succession-step-body"><div class="succession-step-title"><span>Supervisor endorsement</span><span class="succession-step-time"><?php echo e($formatEndorsementDate($endorsement['created_at'])); ?></span></div><p>Submitted by <?php echo e($endorsement['recommender_name']); ?></p></div>
                                </div>
                                <div class="succession-workflow-step <?php echo $endorsement['hr_reviewed_at'] ? '' : 'pending'; ?>">
                                    <span class="succession-step-icon"><i class="fas <?php echo $endorsement['hr_reviewed_at'] ? 'fa-clipboard-check' : 'fa-hourglass-half'; ?>"></i></span>
                                    <div class="succession-step-body"><div class="succession-step-title"><span>HR verification</span><span class="succession-step-time"><?php echo e($formatEndorsementDate($endorsement['hr_reviewed_at'])); ?></span></div><p><?php echo $endorsement['hr_reviewed_at'] ? e($endorsement['hr_review_notes'] ?: 'Reviewed and forwarded for final decision.').' — '.e($endorsement['hr_reviewer_name']) : 'Waiting for HR Manager review'; ?></p></div>
                                </div>
                                <div class="succession-workflow-step <?php echo $endorsement['ceo_decided_at'] ? ($status === 'Rejected' ? 'rejected' : '') : 'pending'; ?>">
                                    <span class="succession-step-icon"><i class="fas <?php echo $endorsement['ceo_decided_at'] ? ($status === 'Rejected' ? 'fa-times' : 'fa-gavel') : 'fa-hourglass-half'; ?>"></i></span>
                                    <div class="succession-step-body"><div class="succession-step-title"><span>President/CEO decision</span><span class="succession-step-time"><?php echo e($formatEndorsementDate($endorsement['ceo_decided_at'])); ?></span></div><p><?php echo $endorsement['ceo_decided_at'] ? e($endorsement['ceo_decision_notes'] ?: $status).' — '.e($endorsement['ceo_name']) : 'Waiting for final decision'; ?></p></div>
                                </div>
                            </div>
                        </div>
                        <?php if ($status === 'Approved'): ?>
                        <div class="succession-approved-note">
                            <strong>Endorsement approved.</strong> <?php echo $endorsement['applied_at'] ? 'The approved position was applied to the employee record. Pay is unchanged.' : 'This earlier approval was recorded before automatic position updates were introduced.'; ?>
                        </div>
                        <?php endif; ?>
                        <div class="succession-endorsement-footer">
                            <span class="succession-endorsement-id">Endorsement #<?php echo (int)$endorsement['endorsement_id']; ?></span>
                            <a class="btn btn-outline-success btn-sm" href="<?php echo BASE_URL . '/' . (in_array(($_SESSION['role'] ?? ''), ['Employee', 'President and CEO'], true) ? 'employee' : (($_SESSION['role'] ?? '') === 'HR Supervisor' ? 'supervisor' : 'manager')) . '/succession-candidate.php?employee_id=' . (int)$endorsement['employee_id']; ?>#tab-overview"><i class="fas fa-folder-open me-1"></i>View Candidate Evidence</a>
                        </div>
                        </div>
                        <?php if ($isHrAction || $isCeoAction): ?>
                        <form method="post" class="succession-endorsement-action">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="endorsement_id" value="<?php echo (int)$endorsement['endorsement_id']; ?>">
                            <label class="form-label small fw-bold" for="reviewReason<?php echo (int)$endorsement['endorsement_id']; ?>"><?php echo $isHrAction ? 'HR review basis and recommendation' : 'Decision reason'; ?></label>
                            <textarea class="form-control mb-2" name="reason" id="reviewReason<?php echo (int)$endorsement['endorsement_id']; ?>" rows="3" placeholder="<?php echo $isHrAction ? 'Explain the evidence, qualifications, and position need before forwarding.' : 'Explain the final decision.'; ?>"></textarea>
                            <?php if ($isHrAction): ?>
                            <div class="small mb-2">
                                <label class="d-block"><input type="checkbox" name="qualifications_verified" value="1"> Qualifications and competencies reviewed</label>
                                <label class="d-block"><input type="checkbox" name="opportunity_verified" value="1"> Position opportunity or organizational need confirmed</label>
                                <label class="d-block"><input type="checkbox" name="records_verified" value="1"> Supporting records and applicable policy reviewed</label>
                            </div>
                            <?php endif; ?>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if ($isHrAction): ?>
                                    <button class="btn btn-success btn-sm" name="succession_action" value="forward" <?php echo empty($successionCeoAvailable) ? 'disabled title="Active President/CEO account required"' : ''; ?>>Forward to President/CEO</button>
                                    <button class="btn btn-outline-warning btn-sm" name="succession_action" value="return_hr">Return to Supervisor</button>
                                    <button class="btn btn-outline-danger btn-sm" name="succession_action" value="reject_hr">Reject</button>
                                <?php else: ?>
                                    <button class="btn btn-success btn-sm" name="succession_action" value="approve_ceo">Approve Endorsement</button>
                                    <button class="btn btn-outline-danger btn-sm" name="succession_action" value="reject_ceo">Reject</button>
                                <?php endif; ?>
                            </div>
                        </form>
                        <?php endif; ?>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
            </div>
            <nav class="succession-endorsement-pagination" id="successionEndorsementPagination" aria-label="Promotion endorsement pages">
                <span class="succession-page-summary" id="successionPageSummary"></span>
                <span class="succession-page-controls">
                    <button type="button" class="btn btn-outline-success btn-sm" id="successionPrevPage" aria-label="Previous endorsement page"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                    <span class="succession-page-indicator" id="successionPageIndicator" aria-live="polite"></span>
                    <button type="button" class="btn btn-outline-success btn-sm" id="successionNextPage" aria-label="Next endorsement page"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                </span>
            </nav>
        <?php endif; ?>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const section = document.getElementById('endorsements');
    if (!section) return;
    const tabs = [...section.querySelectorAll('[data-succession-status]')];
    const cards = [...section.querySelectorAll('.succession-endorsement-card')];
    const empty = section.querySelector('.succession-status-empty');
    const departmentFilter = section.querySelector('#successionDepartmentFilter');
    const pagination = section.querySelector('#successionEndorsementPagination');
    const pageSummary = section.querySelector('#successionPageSummary');
    const pageIndicator = section.querySelector('#successionPageIndicator');
    const previousPage = section.querySelector('#successionPrevPage');
    const nextPage = section.querySelector('#successionNextPage');
    const savedStatusKey = 'successionEndorsementStatus:' + window.location.pathname;
    const savedDepartmentKey = 'successionEndorsementDepartment:' + window.location.pathname;
    const pageSize = 5;
    let activeStatus = 'all';
    let activeDepartment = 'all';
    let activePage = 1;
    if (!tabs.length) return;
    function renderEndorsements() {
        const matches = cards.filter(card =>
            (activeStatus === 'all' || card.dataset.statusGroup === activeStatus) &&
            (activeDepartment === 'all' || card.dataset.departmentId === activeDepartment)
        );
        const totalPages = Math.max(1, Math.ceil(matches.length / pageSize));
        activePage = Math.min(Math.max(1, activePage), totalPages);
        const pageStart = (activePage - 1) * pageSize;
        const visibleCards = new Set(matches.slice(pageStart, pageStart + pageSize));
        cards.forEach(card => { card.hidden = !visibleCards.has(card); });
        tabs.forEach(tab => tab.setAttribute('aria-selected', tab.dataset.successionStatus === activeStatus ? 'true' : 'false'));
        empty.hidden = matches.length > 0;
        pagination.hidden = matches.length === 0;
        pageIndicator.textContent = `Page ${activePage} of ${totalPages}`;
        const firstResult = matches.length ? pageStart + 1 : 0;
        const lastResult = Math.min(pageStart + pageSize, matches.length);
        pageSummary.textContent = `Showing ${firstResult}–${lastResult} of ${matches.length} endorsement${matches.length === 1 ? '' : 's'}`;
        previousPage.disabled = activePage <= 1;
        nextPage.disabled = activePage >= totalPages;
        try {
            sessionStorage.setItem(savedStatusKey, activeStatus);
            sessionStorage.setItem(savedDepartmentKey, activeDepartment);
        } catch (_) {}
    }
    function selectStatus(status) { activeStatus = status; activePage = 1; renderEndorsements(); }
    tabs.forEach(tab => tab.addEventListener('click', () => selectStatus(tab.dataset.successionStatus)));
    departmentFilter?.addEventListener('change', () => { activeDepartment = departmentFilter.value; activePage = 1; renderEndorsements(); });
    previousPage?.addEventListener('click', () => { if (activePage > 1) { activePage--; renderEndorsements(); section.querySelector('#successionEndorsementCards')?.scrollIntoView({behavior:'smooth',block:'start'}); } });
    nextPage?.addEventListener('click', () => { activePage++; renderEndorsements(); section.querySelector('#successionEndorsementCards')?.scrollIntoView({behavior:'smooth',block:'start'}); });
    const firstAvailable = ['ongoing', 'approved', 'rejected', 'returned'].find(status => cards.some(card => card.dataset.statusGroup === status)) || 'all';
    const afterDecision = <?php echo json_encode($successionSelectedStatus); ?>;
    let savedStatus = null;
    let savedDepartment = null;
    try { savedStatus = sessionStorage.getItem(savedStatusKey); savedDepartment = sessionStorage.getItem(savedDepartmentKey); } catch (_) {}
    const initialStatus = afterDecision || savedStatus || firstAvailable;
    activeStatus = tabs.some(tab => tab.dataset.successionStatus === initialStatus) ? initialStatus : firstAvailable;
    if (departmentFilter && [...departmentFilter.options].some(option => option.value === savedDepartment)) {
        activeDepartment = savedDepartment;
        departmentFilter.value = savedDepartment;
    }
    renderEndorsements();
});
</script>
