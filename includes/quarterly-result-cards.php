<?php // Shared read-only Quarterly score summary; caller supplies display scores and status. ?>
<div class="quarterly-result-cards" aria-label="Quarterly evaluation scores">
                <section class="quarterly-result-card page-hero">
                    <div class="quarterly-result-card-head"><span class="quarterly-result-icon"><i class="fas fa-chart-pie" aria-hidden="true"></i></span><span class="quarterly-result-weight">80% weight</span></div>
                    <h3>Key Result Areas</h3>
                    <div class="quarterly-result-number"><?php echo number_format($quarterlyDisplayKra,2); ?><span>/ 4.00</span></div>
                    <p><?php echo $quarterlyScoresApproved ? 'Approved KRA score' : 'Submitted KRA score'; ?></p>
                </section>
                <section class="quarterly-result-card quarterly-result-card--behavior page-hero">
                    <div class="quarterly-result-card-head"><span class="quarterly-result-icon"><i class="fas fa-handshake" aria-hidden="true"></i></span><span class="quarterly-result-weight">20% weight</span></div>
                    <h3>Core Behaviors &amp; Values</h3>
                    <div class="quarterly-result-number"><?php echo number_format($quarterlyDisplayCore,2); ?><span>/ 4.00</span></div>
                    <p><?php echo $quarterlyScoresApproved || !empty($quarterlyShowLive360) ? 'Combined 360 average' : 'Submitted behavior score'; ?></p>
                </section>
                <section class="quarterly-result-card quarterly-result-card--total page-hero">
                    <div class="quarterly-result-card-head"><span class="quarterly-result-icon"><i class="fas fa-chart-line" aria-hidden="true"></i></span><span class="quarterly-result-weight">Weighted overall</span></div>
                    <h3><?php echo !empty($quarterlyResultProvisional) ? 'Provisional score' : 'Final Score'; ?></h3>
                    <div class="quarterly-result-number"><?php echo number_format($quarterlyDisplayTotal,2); ?><span>/ 4.00</span></div>
                    <span class="quarterly-result-level is-<?php echo $quarterlyTier; ?>"><i class="fas fa-<?php echo $quarterlyTier==='needs' ? 'exclamation-circle' : 'check-circle'; ?>" aria-hidden="true"></i> <?php echo e(!empty($quarterlyResultProvisional) ? ($quarterlyScoresApproved ? 'More 360 ratings pending' : 'Awaiting head approval') : ($quarterlyScoresApproved ? getPerformanceLevel($quarterlyDisplayTotal) : 'Awaiting approval')); ?></span>
                    <p>KRA × 80% + Behavior × 20%</p>
                </section>
            </div>
