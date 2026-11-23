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
.succession-endorsement-card[hidden], .succession-status-empty[hidden] { display:none !important; }
.succession-endorsement-entry { overflow:hidden; border:1px solid #dce6d6; border-radius:14px; background:#fff; box-shadow:0 8px 22px rgba(8,46,6,.06); }
.succession-endorsement-head { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; padding:1rem 1.2rem; background:#f7faf4; border-bottom:1px solid #e3e9dd; }
.succession-endorsement-head strong { display:block; color:#173b20; font-size:1.05rem; }
.succession-endorsement-route { margin-top:.25rem; color:#52614f; font-size:.85rem; }
.succession-endorsement-layout { display:grid; grid-template-columns:minmax(0,1fr) minmax(320px,.9fr); }
.succession-endorsement-layout.no-action { grid-template-columns:minmax(0,1fr); }
.succession-endorsement-details { min-width:0; padding:1.1rem 1.2rem; }
.succession-endorsement-action { min-width:0; margin:0; padding:1.1rem 1.2rem; border-left:1px solid #e3e9dd; background:#fbfcfa; }
.succession-endorsement-action .form-control { min-height:104px; }
.succession-endorsement-action label.d-block { margin:.35rem 0; line-height:1.4; }
.succession-endorsement-action .d-flex > .btn { min-height:38px; }
.succession-approved-note { margin-top:1rem; padding:.8rem .9rem; border:1px solid #b9dec3; border-radius:10px; background:#edf8ef; color:#17472a; font-size:.82rem; line-height:1.5; }
@media(max-width:991.98px) { .succession-endorsement-layout { grid-template-columns:minmax(0,1fr); } .succession-endorsement-action { border-left:0; border-top:1px solid #e3e9dd; } }
@media(max-width:575.98px) { .succession-endorsement-head { flex-wrap:wrap; } .succession-endorsement-details,.succession-endorsement-action { padding:.9rem; } .succession-endorsement-action .d-flex > .btn { flex:1 1 100%; } }
</style>
<?php
$successionStatusCounts = ['all'=>count($successionEndorsements),'ongoing'=>0,'approved'=>0,'rejected'=>0,'returned'=>0];
$successionSelectedStatus = $_SESSION['succession_selected_status'] ?? null;
unset($_SESSION['succession_selected_status']);
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
?>
<section class="succession-panel succession-endorsement-list mb-4" id="endorsements" aria-labelledby="endorsementsTitle">
    <div class="succession-panel-header">
        <h6 class="succession-panel-title" id="endorsementsTitle"><i class="fas fa-file-signature"></i>Promotion Endorsements</h6>
        <span class="badge succession-count-badge"><?php echo count($successionEndorsements); ?> recorded</span>
    </div>
    <div class="p-3 p-xl-4">
        <p class="text-muted small mb-3">Supervisor recommendation → HR review → President/CEO final decision. An approved endorsement does not automatically update the position or pay.</p>
        <?php if (($_SESSION['role'] ?? '') === 'HR Manager' && empty($successionCeoAvailable)): ?><div class="alert alert-warning small">No active Employee Portal account is linked to the President and CEO. Create or activate the linked account through Admin before forwarding an endorsement for final approval.</div><?php endif; ?>
        <?php if (!$successionEndorsements): ?>
            <p class="text-muted mb-0">No endorsements yet.</p>
        <?php else: ?>
            <div class="succession-status-tabs" role="tablist" aria-label="Filter promotion endorsements by status">
                <?php foreach (['ongoing'=>'Ongoing','approved'=>'Approved','rejected'=>'Rejected','returned'=>'Returned','all'=>'All'] as $key=>$label): ?>
                <button type="button" class="succession-status-tab" role="tab" data-succession-status="<?php echo $key; ?>" aria-selected="false" aria-controls="successionEndorsementCards"><?php echo $label; ?> <span class="badge rounded-pill"><?php echo $successionStatusCounts[$key]; ?></span></button>
                <?php endforeach; ?>
            </div>
            <p class="succession-status-empty text-muted mb-0" hidden>No endorsements in this status.</p>
            <div class="row g-3" id="successionEndorsementCards" role="tabpanel" aria-label="Promotion endorsements">
            <?php foreach ($successionEndorsements as $endorsement):
                $status = $endorsement['status'];
                $statusGroup = match ($status) { 'Pending HR Review', 'Pending CEO Approval'=>'ongoing', 'Approved'=>'approved', 'Rejected'=>'rejected', 'Returned'=>'returned', default=>'all' };
                $isHrAction = ($_SESSION['role'] ?? '') === 'HR Manager' && $status === 'Pending HR Review';
                $isCeoAction = in_array(($_SESSION['role'] ?? ''), ['Employee', 'President and CEO'], true)
                    && successionIsCeoUser($conn, (int)($_SESSION['user_id'] ?? 0)) && $status === 'Pending CEO Approval';
            ?>
                <div class="col-12 succession-endorsement-card" data-status-group="<?php echo e($statusGroup); ?>">
                    <article class="succession-endorsement-entry">
                        <div class="succession-endorsement-head">
                            <strong><?php echo e($endorsement['employee_name']); ?></strong>
                            <span class="badge <?php echo $status === 'Approved' ? 'bg-success' : ($status === 'Rejected' ? 'bg-danger' : 'bg-secondary'); ?>"><?php echo e($status); ?></span>
                        </div>
                        <div class="succession-endorsement-layout<?php echo ($isHrAction || $isCeoAction) ? '' : ' no-action'; ?>">
                        <div class="succession-endorsement-details">
                        <div class="small text-muted mb-2"><?php echo e($endorsement['current_title']); ?> → <strong><?php echo e($endorsement['target_title']); ?></strong></div>
                        <div class="small"><strong>Supervisor's basis:</strong> <?php echo nl2br(e($endorsement['recommendation_reason'])); ?></div>
                        <div class="small text-muted mt-2">Endorsed by <?php echo e($endorsement['recommender_name']); ?> · <?php echo e($endorsement['created_at']); ?></div>
                        <?php if ($endorsement['hr_reviewed_at']): ?><div class="small mt-2"><strong>HR review:</strong> <?php echo nl2br(e($endorsement['hr_review_notes'] ?: 'Forwarded for management decision.')); ?> <span class="text-muted">— <?php echo e($endorsement['hr_reviewer_name']); ?></span></div><?php endif; ?>
                        <?php if ($endorsement['qualifications_verified']): ?><div class="small text-success mt-1"><i class="fas fa-check-circle me-1"></i>HR verified qualifications, position opportunity, and supporting records.</div><?php endif; ?>
                        <?php if ($endorsement['ceo_decided_at']): ?><div class="small mt-2"><strong>Final decision:</strong> <?php echo nl2br(e($endorsement['ceo_decision_notes'] ?: $status)); ?> <span class="text-muted">— <?php echo e($endorsement['ceo_name']); ?></span></div><?php endif; ?>
                        <?php if ($status === 'Approved'): ?>
                        <div class="succession-approved-note">
                            <strong>Endorsement approved.</strong> <?php echo $endorsement['applied_at'] ? 'The approved position was applied to the employee record. Pay is unchanged.' : 'This earlier approval was recorded before automatic position updates were introduced.'; ?>
                        </div>
                        <?php endif; ?>
                        <a class="btn btn-outline-success btn-sm mt-3" href="<?php echo BASE_URL . '/' . (in_array(($_SESSION['role'] ?? ''), ['Employee', 'President and CEO'], true) ? 'employee' : (($_SESSION['role'] ?? '') === 'HR Supervisor' ? 'supervisor' : 'manager')) . '/succession-candidate.php?employee_id=' . (int)$endorsement['employee_id']; ?>#tab-overview"><i class="fas fa-folder-open me-1"></i>Review Candidate Evidence</a>
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
    const savedStatusKey = 'successionEndorsementStatus:' + window.location.pathname;
    if (!tabs.length) return;
    function selectStatus(status) {
        let visible = 0;
        tabs.forEach(tab => tab.setAttribute('aria-selected', tab.dataset.successionStatus === status ? 'true' : 'false'));
        cards.forEach(card => {
            card.hidden = status !== 'all' && card.dataset.statusGroup !== status;
            if (!card.hidden) visible++;
        });
        empty.hidden = visible > 0;
        try { sessionStorage.setItem(savedStatusKey, status); } catch (_) {}
    }
    tabs.forEach(tab => tab.addEventListener('click', () => selectStatus(tab.dataset.successionStatus)));
    const firstAvailable = ['ongoing', 'approved', 'rejected', 'returned'].find(status => cards.some(card => card.dataset.statusGroup === status)) || 'all';
    const afterDecision = <?php echo json_encode($successionSelectedStatus); ?>;
    let savedStatus = null;
    try { savedStatus = sessionStorage.getItem(savedStatusKey); } catch (_) {}
    const initialStatus = afterDecision || savedStatus || firstAvailable;
    selectStatus(tabs.some(tab => tab.dataset.successionStatus === initialStatus) ? initialStatus : firstAvailable);
});
</script>
