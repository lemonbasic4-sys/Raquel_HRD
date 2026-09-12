<?php
/**
 * Employee Profile Edit History Card
 * Shared partial included at the bottom of view-employee.php pages.
 * Requires $eid (int) and $conn (mysqli) to be available in scope.
 */

if (!isset($conn) || !isset($eid) || (int)$eid <= 0) return;

$edit_history = getEmployeeEditHistory($conn, (int)$eid, 50);
?>

<div class="col-12 mt-2" data-profile-panel="timeline">
    <div class="content-card employee-section-card">
        <div class="employee-section-header">
            <div>
                <div class="employee-section-kicker">
                    <i class="fas fa-history" style="color: var(--primary-blue);"></i>
                    Profile Audit Trail
                </div>
                <h5 class="mb-0">Profile Edit History</h5>
            </div>
            <span class="badge px-3 py-2" style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; font-size:0.78rem; font-weight:700; border-radius:999px;">
                <i class="fas fa-list-check me-1"></i><?php echo count($edit_history); ?> Edit Record<?php echo count($edit_history) !== 1 ? 's' : ''; ?>
            </span>
        </div>

        <div class="card-body">
            <?php if (empty($edit_history)): ?>
                <div class="empty-state">
                    <i class="fas fa-shield-check d-block" style="color: var(--primary-blue); opacity: 0.35;"></i>
                    <p>No manual profile edits have been recorded for this employee.</p>
                </div>
            <?php else: ?>
                <div class="d-grid gap-2">
                    <?php foreach ($edit_history as $idx => $eh):
                        $edit_date = !empty($eh['created_at']) ? formatDateTime($eh['created_at']) : 'Unknown';
                        $has_details = !empty($eh['changes_data']) && is_array($eh['changes_data']);
                        $collapse_id = 'editHistoryRow_' . (int)$eh['edit_id'] . '_' . $idx;

                        $role_colors = match($eh['editor_role']) {
                            'HR Manager'    => ['bg' => '#fee2e2', 'color' => '#991b1b', 'border' => '#fca5a5', 'icon' => 'fa-user-tie'],
                            'HR Supervisor' => ['bg' => '#e0e7ff', 'color' => '#3730a3', 'border' => '#c7d2fe', 'icon' => 'fa-user-shield'],
                            'HR Staff'      => ['bg' => '#dbeafe', 'color' => '#1e40af', 'border' => '#bfdbfe', 'icon' => 'fa-user-edit'],
                            'Admin'         => ['bg' => '#111827', 'color' => '#f9fafb', 'border' => '#374151', 'icon' => 'fa-shield-halved'],
                            default         => ['bg' => '#f3f4f6', 'color' => '#374151', 'border' => '#d1d5db', 'icon' => 'fa-user'],
                        };

                        $icon_css  = "background:{$role_colors['bg']}; color:{$role_colors['color']}; border:1px solid {$role_colors['border']};";
                        $badge_css = "background:{$role_colors['bg']}; color:{$role_colors['color']}; border:1px solid {$role_colors['border']}; font-size:0.72rem; font-weight:700; padding:3px 9px; border-radius:12px; white-space:nowrap;";
                    ?>
                        <details class="employee-subsection audit-edit-row" style="padding:0; overflow: hidden;">
                            <summary style="list-style:none; cursor:pointer; padding: 14px 16px; display:grid; grid-template-columns: 40px 1fr auto; gap: 12px; align-items:center;" 
                                     onmouseover="this.parentElement.style.background='#f8fafc'" 
                                     onmouseout="this.parentElement.style.background=''">
                                <summary::-webkit-details-marker></summary::-webkit-details-marker>

                                <!-- Icon -->
                                <div class="d-flex align-items-center justify-content-center rounded-3" 
                                     style="width:40px; height:40px; flex-shrink:0; <?php echo $icon_css; ?>">
                                    <i class="fas <?php echo $role_colors['icon']; ?>" style="font-size:0.95rem;"></i>
                                </div>

                                <!-- Main content -->
                                <div style="min-width:0;">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <span style="font-weight:700; font-size:0.92rem; color: var(--text-dark);">
                                            <?php echo htmlspecialchars($eh['editor_name']); ?>
                                        </span>
                                        <span style="<?php echo $badge_css; ?>">
                                            <i class="fas <?php echo $role_colors['icon']; ?> me-1" style="font-size:0.68rem;"></i>
                                            <?php echo htmlspecialchars($eh['editor_role']); ?>
                                        </span>
                                        <?php if (!empty($eh['step_name'])): ?>
                                            <span class="detail-label mb-0" style="background:#f1f5f9; padding:2px 8px; border-radius:8px; text-transform:none; letter-spacing:0; font-size:0.72rem;">
                                                <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($eh['step_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex flex-wrap align-items-center gap-3">
                                        <span class="detail-label mb-0">
                                            <i class="far fa-clock me-1"></i><?php echo $edit_date; ?>
                                        </span>
                                        <span style="font-size:0.82rem; color: var(--text-dark); min-width:0; overflow-wrap:anywhere;">
                                            <?php echo htmlspecialchars($eh['change_summary'] ?: 'Profile information updated'); ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Expand indicator -->
                                <?php if ($has_details): ?>
                                    <span style="flex-shrink:0; font-size:0.75rem; color: var(--text-muted);">
                                        <i class="fas fa-chevron-down audit-edit-chevron" style="transition: transform 0.2s;"></i>
                                    </span>
                                <?php else: ?>
                                    <span></span>
                                <?php endif; ?>
                            </summary>

                            <?php if ($has_details): ?>
                                <div style="padding: 0 16px 16px 16px; border-top: 1px solid #edf2f7; margin-top: 0; background: #fbfcfe;">
                                    <div class="employee-subsection-title mb-2 mt-3">
                                        <i class="fas fa-code-compare me-1"></i>
                                        Field-Level Changes (<?php echo count($eh['changes_data']); ?> field<?php echo count($eh['changes_data']) !== 1 ? 's' : ''; ?> modified)
                                    </div>
                                    <div class="employee-table-wrap">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th style="width:30%;">Field</th>
                                                    <th style="width:35%;">Previous Value</th>
                                                    <th style="width:35%;">New Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($eh['changes_data'] as $f_key => $diff): ?>
                                                    <tr>
                                                        <td data-label="Field" class="fw-bold" style="font-size:0.82rem; color: var(--text-muted);">
                                                            <?php echo htmlspecialchars($diff['label'] ?? $f_key); ?>
                                                        </td>
                                                        <td data-label="Previous Value">
                                                            <span style="display:inline-block; padding:3px 8px; border-left:3px solid #fca5a5; background:#fff1f2; border-radius:0 6px 6px 0; font-size:0.81rem; color:#991b1b; font-family:monospace; word-break:break-all;">
                                                                <?php echo htmlspecialchars($diff['old'] !== '' ? $diff['old'] : '— Empty'); ?>
                                                            </span>
                                                        </td>
                                                        <td data-label="New Value">
                                                            <span style="display:inline-block; padding:3px 8px; border-left:3px solid #86efac; background:#f0fdf4; border-radius:0 6px 6px 0; font-size:0.81rem; color:#15803d; font-weight:600; font-family:monospace; word-break:break-all;">
                                                                <?php echo htmlspecialchars($diff['new'] !== '' ? $diff['new'] : '— Empty'); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
/* Rotate chevron arrow when details opens/closes */
document.querySelectorAll('.audit-edit-row').forEach(function(details) {
    details.addEventListener('toggle', function() {
        var chevron = details.querySelector('.audit-edit-chevron');
        if (chevron) {
            chevron.style.transform = details.open ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    });
});
</script>
