<?php
$page_title = 'Succession Planning';
require_once '../includes/session-check.php';
checkRole(['Employee', 'President and CEO']);
require_once '../includes/succession-endorsements.php';
if (!successionIsCeoUser($conn, (int)$_SESSION['user_id'])) {
    http_response_code(403);
    exit('Succession Planning is available only to the President and CEO.');
}
successionHandlePost($conn);
$successionTableReady = successionEndorsementTableReady($conn);
$successionEndorsements = $successionTableReady ? successionListEndorsements($conn) : [];
require_once '../includes/header.php';
?>
<div class="page-hero fadeup mb-4">
    <div class="small text-white-50 text-uppercase">President and CEO · Career</div>
    <h1 class="h3 text-white fw-bold mb-1"><i class="fas fa-user-tie me-2"></i>Succession Planning</h1>
    <p class="text-white-50 mb-0">Make the final decision on promotion endorsements reviewed by HR.</p>
</div>
<?php if (!empty($_SESSION['succession_flash'])): $successionFlash = $_SESSION['succession_flash']; unset($_SESSION['succession_flash']); ?>
<div class="alert alert-<?php echo e($successionFlash[0]); ?>" role="status"><?php echo e($successionFlash[1]); ?></div>
<?php endif; ?>
<?php if (!$successionTableReady): ?>
<div class="alert alert-warning">Succession endorsements need the database migration: <code>database/succession_endorsements.sql</code>.</div>
<?php else: ?>
<?php require '../includes/succession-endorsements-view.php'; ?>
<?php endif; ?>
<?php require_once '../includes/footer.php'; ?>
