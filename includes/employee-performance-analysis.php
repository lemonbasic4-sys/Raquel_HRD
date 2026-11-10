<?php
/** Read-only analysis of official results. Never recalculate or write approved scores. */
require_once __DIR__ . '/functions.php';

/** Shared eligibility for employee, manager and aggregate analytics queries. */
function finalizedPerformanceSql(string $alias = 'ev'): string
{
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $alias)) throw new InvalidArgumentException('Invalid evaluation alias.');
    $conditions = ["$alias.deleted_at IS NULL", "$alias.total_score IS NOT NULL"];
    foreach (['annual'=>'Annual', 'quarterly'=>'Quarterly'] as $prefix=>$type) {
        $conditions[] = "NOT EXISTS(SELECT 1 FROM {$prefix}_evaluation_members perf_m
            LEFT JOIN {$prefix}_evaluation_versions perf_v ON perf_v.member_id=perf_m.member_id AND perf_v.version_number=perf_m.current_version
            WHERE perf_m.evaluation_id=$alias.evaluation_id AND $alias.evaluation_type='$type'
              AND (perf_m.revision_pending=1 OR perf_v.version_id IS NULL OR
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(perf_v.snapshot_json,'$.score_complete')),
                    IF(COALESCE(JSON_EXTRACT(perf_v.snapshot_json,'$.peer_count'),0)>=COALESCE(JSON_EXTRACT(perf_v.snapshot_json,'$.expected_peers'),2147483647),'true','false'))<>'true'))";
        $conditions[] = "NOT EXISTS(SELECT 1 FROM {$prefix}_evaluation_members cancelled_member
            JOIN {$prefix}_evaluation_cycles cancelled_cycle ON cancelled_cycle.cycle_id=cancelled_member.cycle_id
            WHERE cancelled_member.evaluation_id=$alias.evaluation_id AND $alias.evaluation_type='$type'
              AND cancelled_cycle.cancelled_at IS NOT NULL)";
    }
    $conditions[] = "NOT EXISTS(SELECT 1 FROM evaluation_package_members perf_pm
        JOIN evaluation_packages perf_p ON perf_p.package_id=perf_pm.package_id
        WHERE perf_pm.evaluation_id=$alias.evaluation_id
          AND perf_pm.package_id=(SELECT MAX(perf_recent.package_id) FROM evaluation_package_members perf_recent WHERE perf_recent.evaluation_id=$alias.evaluation_id)
          AND (perf_p.status<>'Approved and Applied' OR
            ($alias.evaluation_type NOT IN ('Initial','Final') AND perf_p.shared_behavior_score IS NOT NULL
              AND NOT EXISTS(SELECT 1 FROM annual_evaluation_members perf_a WHERE perf_a.evaluation_id=$alias.evaluation_id)
              AND NOT EXISTS(SELECT 1 FROM quarterly_evaluation_members perf_q WHERE perf_q.evaluation_id=$alias.evaluation_id)
              AND EXISTS(SELECT 1 FROM evaluation_packages perf_open
                WHERE perf_open.department_id=perf_p.department_id AND perf_open.template_id=perf_p.template_id
                  AND perf_open.period_start=perf_p.period_start AND perf_open.period_end=perf_p.period_end
                  AND perf_open.package_id<>perf_p.package_id AND perf_open.status NOT IN ('Approved and Applied','Cancelled')
                  AND EXISTS(SELECT 1 FROM evaluation_package_members perf_opm WHERE perf_opm.package_id=perf_open.package_id)))))";
    return '(' . implode(' AND ', $conditions) . ')';
}

function employeePerformanceChange(?float $current, ?float $previous): array
{
    if ($current === null || $previous === null) return ['delta' => null, 'direction' => 'insufficient_data'];
    $delta = round($current - $previous, 2);
    return ['delta' => $delta, 'direction' => $delta > 0 ? 'improving' : ($delta < 0 ? 'declining' : 'stable')];
}

function employeePerformanceCriterionKey(array $row): string
{
    // Changed descriptions can mean changed responsibilities. Do not compare those as identical criteria.
    $normalize = static fn($s) => mb_strtolower(preg_replace('/\s+/u', ' ', trim((string)$s)), 'UTF-8');
    return $normalize($row['section']) . '|' . $normalize($row['criterion_name']) . '|'
        . $normalize($row['kpi_description'] ?? '') . '|' . $normalize($row['description'] ?? '');
}

function employeePerformanceHistory($conn, int $employeeId): array
{
    ensureAnnualEvaluationSchema($conn);
    ensureQuarterlyEvaluationSchema($conn);
    $finalizedSql = finalizedPerformanceSql('ev');
    $rows = annualQuery($conn, "SELECT ev.evaluation_id, ev.evaluation_type, ev.template_id,
            ev.evaluation_period_start, ev.evaluation_period_end, ev.total_score, ev.kra_subtotal,
            ev.behavior_average, ev.performance_level, ev.approved_date, ev.supervisor_comments,
            et.template_name, et.kra_weight, et.behavior_weight,
            COALESCE(ev.approved_date, ev.evaluation_period_end) AS display_date,
            ep.status AS package_status, ep.shared_behavior_score, $finalizedSql AS score_finalized,
            EXISTS(SELECT 1 FROM evaluation_packages open_pkg
                WHERE open_pkg.department_id=ep.department_id AND open_pkg.template_id=ep.template_id
                  AND open_pkg.period_start=ep.period_start AND open_pkg.period_end=ep.period_end
                  AND open_pkg.package_id<>ep.package_id AND open_pkg.status NOT IN ('Approved and Applied','Cancelled')
                  AND EXISTS(SELECT 1 FROM evaluation_package_members opm WHERE opm.package_id=open_pkg.package_id)) AS open_cohort
        FROM evaluations ev JOIN evaluation_templates et ON et.template_id=ev.template_id
        LEFT JOIN evaluation_package_members pm ON pm.evaluation_id=ev.evaluation_id
            AND pm.package_id=(SELECT MAX(recent_pm.package_id) FROM evaluation_package_members recent_pm WHERE recent_pm.evaluation_id=ev.evaluation_id)
        LEFT JOIN evaluation_packages ep ON ep.package_id=pm.package_id
        WHERE ev.employee_id=? AND ev.status='Approved' AND ev.deleted_at IS NULL AND ev.total_score IS NOT NULL
        ORDER BY COALESCE(ev.approved_date, ev.evaluation_period_end), ev.evaluation_id", [$employeeId]);
    $scoreRows = annualQuery($conn, "SELECT es.*, ec.section, ec.criterion_name, ec.description,
            ec.kpi_description, ec.weight, ec.sort_order
        FROM evaluation_scores es JOIN evaluation_criteria ec ON ec.criterion_id=es.criterion_id
        JOIN evaluations ev ON ev.evaluation_id=es.evaluation_id
        WHERE ev.employee_id=? AND ev.status='Approved' AND ev.deleted_at IS NULL
        ORDER BY ec.section, ec.sort_order, ec.criterion_id", [$employeeId]);
    $scores = [];
    foreach ($scoreRows as $row) $scores[(int)$row['evaluation_id']][] = $row;
    $finalized = []; $pending = [];
    foreach ($rows as $ev) {
        $id = (int)$ev['evaluation_id'];
        $type = $ev['evaluation_type'];
        $peer = null; $snapshot = null;
        $reason = null;
        if (in_array($type, ['Annual', 'Quarterly'], true)) {
            $prefix = $type === 'Annual' ? 'annual' : 'quarterly';
            $member = ($prefix . 'Member')($conn, $id);
            if ($member) {
                $version = annualQuery($conn, "SELECT snapshot_json FROM {$prefix}_evaluation_versions
                    WHERE member_id=? AND version_number=? LIMIT 1",
                    [(int)$member['member_id'], (int)$member['current_version']])[0] ?? [];
                $snapshot = json_decode($version['snapshot_json'] ?? 'null', true);
                if (!$snapshot || !empty($member['revision_pending']) ||
                    !($snapshot['score_complete'] ?? ((int)($snapshot['peer_count'] ?? 0) >= (int)($snapshot['expected_peers'] ?? PHP_INT_MAX)))) {
                    $reason = 'Approved by the head; complete 360 ratings or a finalized score version is still pending.';
                }
                $peer = ['received' => (int)($snapshot['peer_count'] ?? 0),
                    'expected' => (int)($snapshot['expected_peers'] ?? 0), 'version' => (int)$member['current_version']];
            }
        }
        $shared = !$peer && !in_array($type, ['Initial','Final'], true) && $ev['shared_behavior_score'] !== null;
        if (!$peer && $ev['package_status'] !== null && $ev['package_status'] !== 'Approved and Applied') {
            $reason = 'The approval package has not been finalized.';
        } elseif ($shared && !empty($ev['open_cohort'])) {
            $reason = 'The department shared behavior score is provisional while related evaluations remain open.';
        }
        $ev['evaluation_id'] = $id;
        $ev['total_score'] = (float)$ev['total_score'];
        $ev['kra_subtotal'] = $ev['kra_subtotal'] === null ? null : (float)$ev['kra_subtotal'];
        $ev['behavior_average'] = $ev['behavior_average'] === null ? null : (float)$ev['behavior_average'];
        $ev['score_percentage'] = round($ev['total_score'] / 4 * 100, 2);
        $ev['cycle_label'] = date('M d, Y', strtotime($ev['display_date'])) . ' · ' . $type . ' #' . $id;
        if (!(int)$ev['score_finalized']) {
            $reason = $reason ?? 'The official score is awaiting completion of its approval workflow.';
            $pending[] = ['evaluation_id'=>$id, 'cycle_label'=>$ev['cycle_label'], 'reason'=>$reason];
            continue;
        }
        $ev['kra_weight'] = $peer || in_array($type, ['Initial','Final'], true) ? 80.0 : (float)$ev['kra_weight'];
        $ev['behavior_weight'] = $peer || in_array($type, ['Initial','Final'], true) ? 20.0 : (float)$ev['behavior_weight'];
        $ev['behavior_basis'] = $peer ? 'self_peer' : ($shared ? 'department_shared' : 'individual_review');
        $ev['peer_summary'] = $peer;
        $ev['criteria'] = [];
        $behaviorRows = array_values(array_filter($scores[$id] ?? [], static fn($r) => $r['section'] !== 'KRA'));
        $coreNames = array_column(annualCoreValues(), 'name');
        $snapshotKra = $snapshot['kra_rows'] ?? [];
        foreach ($scores[$id] ?? [] as $row) {
            $kra = $row['section'] === 'KRA';
            $rating = $row['manager_override_score'] ?? $row['supervisor_override_score']
                ?? $row['dept_manager_override_score'] ?? $row['score_value'];
            $self = $row['score_value'] === null ? null : (float)$row['score_value'];
            $peerAverage = null;
            if ($peer) {
                if ($kra) {
                    // Snapshot contains the exact head-reviewed KRA rows used in this official version.
                    $saved = array_values(array_filter($snapshotKra, static fn($r) => (int)($r['score_id'] ?? 0) === (int)$row['score_id']));
                    $rating = $saved[0]['rating'] ?? null;
                } else {
                    $index = array_search($row['criterion_name'], $coreNames, true);
                    $rating = $index === false ? null : ($snapshot['item_averages'][$index] ?? null);
                    $self = $index === false ? null : ($snapshot['self_values'][$index] ?? $self);
                    $values = [];
                    foreach ($snapshot['peers'] ?? [] as $p) {
                        $v = json_decode($p['scores_json'] ?? '[]', true);
                        if ($index !== false && isset($v[$index])) $values[] = (float)$v[$index];
                    }
                    if ($values) $peerAverage = array_sum($values) / count($values);
                }
            }
            $rating = $rating === null ? null : (float)$rating;
            $itemWeight = $kra ? (float)$row['weight'] : (count($behaviorRows) ? 100 / count($behaviorRows) : 0);
            $sectionWeight = $kra ? $ev['kra_weight'] : $ev['behavior_weight'];
            $weighted = $rating === null ? null : ($kra ? round($rating * $itemWeight / 100, 2) : $rating * $itemWeight / 100);
            $ev['criteria'][] = [
                'key'=>employeePerformanceCriterionKey($row), 'section'=>$row['section'],
                'name'=>$row['criterion_name'], 'description'=>$row['kpi_description'] ?: $row['description'],
                'self_rating'=>$self, 'rating'=>$rating, 'peer_average'=>$peerAverage,
                'item_weight'=>$itemWeight, 'section_weight'=>$sectionWeight,
                'weighted_score'=>$weighted,
                // Shared department behavior cannot be attributed to this employee's individual criteria.
                'contribution'=>$shared && !$kra ? null : ($weighted === null ? null : $weighted * $sectionWeight / 100),
                'basis'=>$kra ? 'head_review' : $ev['behavior_basis']
            ];
        }
        $computed = $ev['kra_subtotal'] !== null && $ev['behavior_average'] !== null
            ? calculateEvalTotal($ev['kra_subtotal'], $ev['behavior_average'], $ev['kra_weight'], $ev['behavior_weight']) : null;
        $ev['formula_matches'] = $computed !== null && abs($computed - $ev['total_score']) < .005;
        $ev['computed_total'] = $computed;
        $kraDetails = array_values(array_filter($ev['criteria'], static fn($r) => $r['section'] === 'KRA' && $r['rating'] !== null));
        $behaviorDetails = array_values(array_filter($ev['criteria'], static fn($r) => $r['section'] !== 'KRA' && $r['rating'] !== null));
        $detailKra = $kraDetails ? round(array_sum(array_column($kraDetails, 'weighted_score')), 2) : null;
        $detailBehavior = $behaviorDetails ? round(array_sum(array_column($behaviorDetails, 'rating')) / count($behaviorDetails), 2) : null;
        $ev['details_match'] = $detailKra !== null && $ev['kra_subtotal'] !== null
            && abs($detailKra - $ev['kra_subtotal']) < .005
            && ($shared || ($detailBehavior !== null && $ev['behavior_average'] !== null && abs($detailBehavior - $ev['behavior_average']) < .005));
        $ev['development_plans'] = annualQuery($conn, 'SELECT improvement_area,support_needed,time_frame FROM evaluation_dev_plans WHERE evaluation_id=? ORDER BY sort_order,plan_id', [$id]);
        unset($ev['package_status'], $ev['shared_behavior_score'], $ev['open_cohort'], $ev['score_finalized']);
        $finalized[] = $ev;
    }
    return ['evaluations'=>$finalized, 'pending_evaluations'=>$pending];
}
