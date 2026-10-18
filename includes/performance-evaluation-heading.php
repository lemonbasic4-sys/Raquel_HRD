<?php
// Shared HRIS heading for both evaluation categories.
$regular_category = !empty($annual_in_performance_page);
?>
<section class="package-hero">
    <?php if ($regular_category && !empty($member) && in_array($view ?? '', ['review','results'],true)): ?>
    <a class="history-back-link" href="<?php echo annualUrl($actor,'?cycle_id='.$cycleId); ?>"><i class="fas fa-arrow-left me-1"></i>Back to evaluation review</a>
    <?php endif; ?>
    <p class="mb-1 text-uppercase fw-bold" style="letter-spacing:1px;color:var(--rp-primary-gold-light);font-size:.85rem;">Performance Evaluation Process</p>
    <?php if ($regular_category && !empty($member) && in_array($view ?? '', ['review','results'],true)): ?>
    <h1 class="h3 mb-2 fw-bold">Review &amp; Adjust: <?php echo e($member['first_name'].' '.$member['last_name']); ?></h1>
    <p class="mb-3"><strong><?php echo e($member['position_name']); ?></strong> &bull; Template: <?php echo e($member['template_name']); ?> &bull; Period: <?php echo e($member['period_start'].' to '.$member['period_end']); ?></p>
    <?php else: ?>
    <h1 class="h3 mb-2 fw-bold">Performance Evaluation</h1>
    <p class="mb-3">Review submitted employee ratings and follow their approval progress.</p>
    <?php endif; ?>
    <div class="d-flex flex-wrap gap-2 pt-2 border-top border-white-10">
        <span class="badge" style="background:rgba(40,167,69,.2);border:1px solid rgba(40,167,69,.4);color:#96e0a8;"><i class="fas fa-star me-1"></i>Outstanding (3.60 – 4.00)</span>
        <span class="badge" style="background:rgba(23,162,184,.2);border:1px solid rgba(23,162,184,.4);color:#9de0ec;"><i class="fas fa-thumbs-up me-1"></i>Exceeds Expectations (2.60 – 3.59)</span>
        <span class="badge" style="background:rgba(255,193,7,.2);border:1px solid rgba(255,193,7,.4);color:#ffe699;"><i class="fas fa-check-circle me-1"></i>Meets Expectations (2.00 – 2.59)</span>
        <span class="badge" style="background:rgba(220,53,69,.2);border:1px solid rgba(220,53,69,.4);color:#f5a3ab;"><i class="fas fa-exclamation-triangle me-1"></i>Needs Improvement (1.00 – 1.99)</span>
    </div>
</section>
<style>
.performance-category-tabs{display:flex;gap:.75rem;align-items:stretch;margin:1rem 0 1.25rem;border-bottom:1px solid #dce5dc}
.performance-category-tab{display:flex;align-items:center;gap:.75rem;min-width:240px;padding:.8rem 1rem;background:#f3f4f6;border:0;border-bottom:3px solid transparent;border-radius:.5rem .5rem 0 0;color:#374151;text-align:left;text-decoration:none}
.performance-category-tab:hover{background:#e9f2e9;color:#082e06}
.performance-category-tab.active{background:#eef7ed;border-bottom-color:#d4a900;color:#082e06}
.performance-category-icon{display:grid;place-items:center;width:38px;height:38px;border-radius:50%;background:rgba(8,46,6,.08);font-size:1.1rem;flex-shrink:0}
.performance-category-copy{display:flex;flex-direction:column;gap:.15rem}
.performance-category-copy strong{font-size:.9rem}.performance-category-copy small{color:#6b7280;font-size:.72rem}
@media(max-width:575.98px){.performance-category-tabs{gap:.4rem}.performance-category-tab{flex:1;min-width:0;padding:.65rem .5rem;gap:.45rem}.performance-category-icon{width:30px;height:30px}.performance-category-copy strong{font-size:.76rem}.performance-category-copy small{font-size:.65rem}}
</style>
<nav class="performance-category-tabs" aria-label="Evaluation review type">
    <a class="performance-category-tab <?php echo !$regular_category ? 'active' : ''; ?>" <?php echo !$regular_category ? 'aria-current="page"' : ''; ?> href="<?php echo BASE_URL; ?>/employee/team-evaluation-packages.php?category=probationary">
        <span class="performance-category-icon"><i class="fas fa-users" aria-hidden="true"></i></span><span class="performance-category-copy"><strong>Probationary Employee</strong><small>Initial and Final evaluations</small></span>
    </a>
    <a class="performance-category-tab <?php echo $regular_category ? 'active' : ''; ?>" <?php echo $regular_category ? 'aria-current="page"' : ''; ?> href="<?php echo BASE_URL; ?>/employee/team-evaluation-packages.php?category=regular">
        <span class="performance-category-icon"><i class="fas fa-user" aria-hidden="true"></i></span><span class="performance-category-copy"><strong>Regular Employee</strong><small>Annual evaluations &amp; 360 ratings</small></span>
    </a>
</nav>
