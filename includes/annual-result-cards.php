<?php // Shared read-only Annual score summary; caller supplies display scores and status. ?>
<div class="annual-result-cards" aria-label="Annual evaluation scores">
                <section class="annual-result-card page-hero fadeup">
                    <div class="annual-result-card-head"><span class="annual-result-icon"><i class="fas fa-chart-pie" aria-hidden="true"></i></span><span class="annual-result-weight">80% weight</span></div>
                    <h3>Key Result Areas</h3>
                    <div class="annual-result-number"><?php echo number_format($annualDisplayKra,2); ?><span>/ 4.00</span></div>
                    <p><?php echo $annualScoresApproved ? 'Approved KRA score' : 'Submitted KRA score'; ?></p>
                </section>
                <section class="annual-result-card annual-result-card--behavior page-hero fadeup">
                    <div class="annual-result-card-head"><span class="annual-result-icon"><i class="fas fa-handshake" aria-hidden="true"></i></span><span class="annual-result-weight">20% weight</span></div>
                    <h3>Core Behaviors &amp; Values</h3>
                    <div class="annual-result-number"><?php echo number_format($annualDisplayCore,2); ?><span>/ 4.00</span></div>
                    <p><?php echo $annualScoresApproved || !empty($annualShowLive360) ? 'Combined 360 average' : 'Submitted behavior score'; ?></p>
                </section>
                <section class="annual-result-card annual-result-card--total page-hero fadeup">
                    <div class="annual-result-card-head"><span class="annual-result-icon"><i class="fas fa-chart-line" aria-hidden="true"></i></span><span class="annual-result-weight">Weighted overall</span></div>
                    <h3><?php echo !empty($annualResultProvisional) ? 'Provisional score' : 'Final Score'; ?></h3>
                    <div class="annual-result-number"><?php echo number_format($annualDisplayTotal,2); ?><span>/ 4.00</span></div>
                    <span class="annual-result-level is-<?php echo $annualTier; ?>"><i class="fas fa-<?php echo $annualTier==='needs' ? 'exclamation-circle' : 'check-circle'; ?>" aria-hidden="true"></i> <?php echo e(!empty($annualResultProvisional) ? ($annualScoresApproved ? 'More 360 ratings pending' : 'Awaiting head approval') : ($annualScoresApproved ? getPerformanceLevel($annualDisplayTotal) : 'Awaiting approval')); ?></span>
                    <p>KRA × 80% + Behavior × 20%</p>
                </section>
            </div>
