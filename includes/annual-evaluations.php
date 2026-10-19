<?php
/** Annual ratings are isolated from the probationary/package approval pipeline. */
function annualCoreValues(): array
{
    return [
        ['name' => 'Positive Attitude', 'kpi' => 'Displays positive attitude at work.'],
        ['name' => 'Respect', 'kpi' => 'Shows respect to all people in the organization.'],
        ['name' => 'Accountability', 'kpi' => 'Takes full responsibility of the job including special task or assignment.'],
        ['name' => 'Commitment', 'kpi' => 'Demonstrates strong commitment to the job.'],
        ['name' => 'Teamwork', 'kpi' => 'Works cooperatively with others in achieving the goals.'],
        ['name' => 'Integrity', 'kpi' => 'Exhibits honesty and strong moral uprightness.'],
        ['name' => 'Continuous Improvement', 'kpi' => 'Provides diligent effort to continuously focus on getting better.'],
        ['name' => 'Excellent Client Experience', 'kpi' => 'Delivers the service beyond the expectations of the internal and external clients.'],
    ];
}

function annualQuery($conn, string $sql, array $params = [])
{
    $stmt = $conn->prepare($sql);
    if ($params) {
        $types = '';
        foreach ($params as $p) $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function annualValidateTemplateKras(array $names, array $weights): void
{
    if (!$names || count($names)!==count($weights)) throw new DomainException('Add at least one KRA with an item weight.');
    $total=0;
    foreach ($names as $i=>$name) {
        $w=$weights[$i] ?? null;
        if (!is_string($name) || trim($name)==='' || mb_strlen(trim($name))>150
            || !is_scalar($w) || !is_numeric($w) || !is_finite((float)$w) || $w<=0 || $w>100
            || abs(round((float)$w,2)-(float)$w)>.00001) {
            throw new DomainException('Each KRA needs a name and a positive weight with at most two decimal places.');
        }
        $total+=(int)round((float)$w*100);
    }
    if ($total!==10000) throw new DomainException('KRA item weights must total exactly 100%.');
}

function ensureAnnualEvaluationSchema($conn): void
{
    static $ready = [];
    $key = spl_object_id($conn);
    if (isset($ready[$key])) return;
    $sql = file_get_contents(__DIR__ . '/../database/zLAST_annual_peer_evaluations.sql');
    $sql = preg_replace('/^--.*$/m', '', $sql);
    foreach (explode(';', $sql) as $statement) {
        if (trim($statement) !== '') $conn->query($statement);
    }
    foreach ([
        'rater_signature_data' => 'LONGTEXT NULL',
        'rater_signed_at' => 'DATETIME NULL',
    ] as $column => $definition) {
        if (!$conn->query("SHOW COLUMNS FROM annual_peer_submissions LIKE '{$column}'")->num_rows) {
            $conn->query("ALTER TABLE annual_peer_submissions ADD COLUMN {$column} {$definition}");
        }
    }
    $ready[$key] = true;
}

function annualUsesPeerWorkflow($conn, int $evaluationId): bool
{
    if (!$conn->query("SHOW TABLES LIKE 'annual_evaluation_members'")->num_rows) return false;
    return (bool)annualQuery($conn,'SELECT 1 FROM annual_evaluation_members WHERE evaluation_id=?',[$evaluationId]);
}

function annualActor($conn, int $userId): ?array
{
    return annualQuery($conn, "SELECT u.user_id, u.role, e.employee_id, e.department_id, e.job_title_id,
            e.employment_status, d.department_name, j.department_id AS position_department_id
        FROM users u JOIN employees e ON e.employee_id=u.employee_id
        LEFT JOIN departments d ON d.department_id=e.department_id
        LEFT JOIN job_titles j ON j.job_title_id=e.job_title_id
        WHERE u.user_id=? AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0
          AND e.is_active=1 AND e.deleted_at IS NULL", [$userId])[0] ?? null;
}

function annualIsHrDepartment(array $actor): bool
{
    return (bool)preg_match('/^human resources?( department)?$|^hrd$/i', trim($actor['department_name'] ?? ''));
}

function annualPortal(array $actor): string
{
    return ['HR Manager' => 'manager', 'HR Supervisor' => 'supervisor', 'HR Staff' => 'staff'][$actor['role']] ?? 'employee';
}

function annualUrl(array $actor, string $query = ''): string
{
    if (in_array($actor['role'], ['HR Manager', 'HR Supervisor'], true)) {
        return BASE_URL . '/employee/team-evaluation-packages.php?category=regular'
            . ($query !== '' ? '&' . ltrim($query, '?&') : '');
    }
    return BASE_URL . '/' . annualPortal($actor) . '/annual-evaluations.php' . $query;
}

function annualCanRate(array $actor): bool
{
    return $actor['employment_status'] === 'Regular'
        && (!annualIsHrDepartment($actor) || in_array($actor['role'], ['HR Manager', 'HR Supervisor', 'HR Staff'], true));
}

function annualMember($conn, int $evaluationId): ?array
{
    return annualQuery($conn, "SELECT m.*, c.department_id, c.period_start, c.period_end, c.first_finalized_at,
            e.first_name, e.last_name, e.department_id AS current_department_id, e.job_title_id AS current_position_id,
            e.is_active AS employee_active, e.employment_status, e.deleted_at AS employee_deleted_at,
            ev.status AS evaluation_status, ev.total_score AS official_score, et.template_name,
            reviewer_position.job_title AS reviewer_position_name
        FROM annual_evaluation_members m JOIN annual_evaluation_cycles c ON c.cycle_id=m.cycle_id
        JOIN employees e ON e.employee_id=m.employee_id JOIN evaluations ev ON ev.evaluation_id=m.evaluation_id
        JOIN evaluation_templates et ON et.template_id=ev.template_id
        LEFT JOIN job_titles reviewer_position ON reviewer_position.job_title_id=m.reviewer_position_id
        WHERE m.evaluation_id=? AND ev.deleted_at IS NULL", [$evaluationId])[0] ?? null;
}

/** Keep submitted history, but exclude departed employees who never self-rated. */
function annualRosterEligibilitySql(string $memberAlias = 'm', string $employeeAlias = 'e'): string
{
    return "($memberAlias.evaluation_id IS NOT NULL OR ($employeeAlias.is_active=1
        AND $employeeAlias.deleted_at IS NULL AND $employeeAlias.employment_status='Regular'))";
}

function annualExpectedPeers($conn, int $cycleId, int $employeeId): int
{
    return (int)annualQuery($conn,'SELECT COUNT(*) AS n FROM annual_evaluation_members m
        JOIN employees e ON e.employee_id=m.employee_id WHERE m.cycle_id=? AND m.employee_id<>? AND '
        .annualRosterEligibilitySql(),[$cycleId,$employeeId])[0]['n'];
}

function annualAssertSnapshotActive(array $member): void
{
    if (!$member['employee_active'] || $member['employee_deleted_at'] || $member['employment_status'] !== 'Regular') {
        throw new DomainException('This participant requires HR review before the annual workflow can continue.');
    }
    if ((int)$member['department_id'] !== (int)$member['current_department_id']
        || (int)$member['position_id'] !== (int)$member['current_position_id']) {
        throw new DomainException('Transfer review required. The original department and position are preserved. Contact HR.');
    }
}

function annualCanFinalize($conn, array $actor, array $member): bool
{
    if ($actor['employment_status'] !== 'Regular' || (int)$actor['employee_id'] === (int)$member['employee_id']) return false;
    // Position Management is the authority, including department-wide parent positions.
    // HR department consolidation is performed through its HRIS accounts.
    if (annualIsHrDepartment($actor) && !in_array($actor['role'], ['HR Manager', 'HR Supervisor'], true)) return false;
    return (int)$member['reviewer_position_id'] > 0
        && (int)$actor['job_title_id'] === (int)$member['reviewer_position_id'];
}

function annualCanSeeNames($conn, array $actor, array $member): bool
{
    if ((int)$actor['employee_id'] === (int)$member['employee_id']) return false;
    return annualCanFinalize($conn, $actor, $member);
}

function annualValidateScores(array $scores): array
{
    if (count($scores) !== 8 || array_keys($scores) !== range(0, 7)) throw new DomainException('Rate all eight Core Behaviors & Values.');
    foreach ($scores as &$score) {
        if (!is_scalar($score) || !is_numeric($score) || !is_finite((float)$score)
            || (float)$score < 1 || (float)$score > 4 || abs(round((float)$score, 2) - (float)$score) > 0.00001) {
            throw new DomainException('Each rating must be a number from 1.00 to 4.00, with at most two decimal places.');
        }
        $score = (float)$score;
    }
    unset($score);
    return $scores;
}

/** Pure calculator used by preview, finalization and tests. Self counts exactly once. */
function annualCalculate(array $kraRows, array $self, array $peers): array
{
    $self = annualValidateScores($self);
    $kra = 0.0;
    $weight = 0;
    foreach ($kraRows as $row) {
        $rating = (float)$row['rating'];
        $w = (float)$row['weight'];
        if (!is_finite($rating) || $rating < 1 || $rating > 4 || !is_finite($w) || $w <= 0) {
            throw new DomainException('Every KRA must have a valid rating and a positive item weight.');
        }
        $weight += (int)round($w * 100);
        $kra += round($w / 100 * $rating, 2);
    }
    if ($weight !== 10000) throw new DomainException('KRA item weights must total exactly 100%.');
    $sums = $self;
    foreach ($peers as $scores) {
        $scores = annualValidateScores($scores);
        foreach ($scores as $i => $score) $sums[$i] += $score;
    }
    $averages = array_map(static fn($sum) => $sum / (count($peers) + 1), $sums);
    $core = round(array_sum($averages) / 8, 2);
    $kra = round($kra, 2);
    return ['kra' => $kra, 'core' => $core, 'item_averages' => $averages,
        'peer_count' => count($peers), 'total' => round($kra * .8 + $core * .2, 2)];
}

function annualKraRows($conn, int $evaluationId): array
{
    return annualQuery($conn, "SELECT es.score_id, ec.criterion_name, ec.kpi_description, ec.weight, es.score_value AS self_rating,
            COALESCE(es.supervisor_override_score,es.score_value) AS rating
        FROM evaluation_scores es JOIN evaluation_criteria ec ON ec.criterion_id=es.criterion_id
        WHERE es.evaluation_id=? AND ec.section='KRA' ORDER BY ec.sort_order, ec.criterion_id", [$evaluationId]);
}

function annualPeers($conn, array $member, bool $named = false): array
{
    // Names are not fetched at all in the employee's result response.
    $names = $named ? ", CONCAT(e.first_name,' ',e.last_name) AS rater_name" : '';
    $join = $named ? ' JOIN employees e ON e.employee_id=s.rater_employee_id' : '';
    return annualQuery($conn, "SELECT s.submission_id, s.scores_json, s.submitted_at, s.is_late $names
        FROM annual_peer_submissions s $join WHERE s.cycle_id=? AND s.target_employee_id=? ORDER BY s.submission_id",
        [(int)$member['cycle_id'], (int)$member['employee_id']]);
}

function annualCandidate($conn, array $member): array
{
    $peers = annualPeers($conn, $member);
    return annualCalculate(annualKraRows($conn, (int)$member['evaluation_id']),
        json_decode($member['self_values_json'], true), array_map(static fn($p) => json_decode($p['scores_json'], true), $peers));
}

function annualApplyCoreAdjustments(array $candidate, array $adjustments): array
{
    if (!$adjustments) { $candidate['core_adjustments_changed']=false; return $candidate; }
    $adjustments=annualValidateScores($adjustments);
    $changed=false;
    foreach ($adjustments as $i=>$score) {
        if (abs((float)$candidate['item_averages'][$i]-(float)$score)>.00001) $changed=true;
    }
    $candidate['item_averages']=$adjustments;
    $candidate['core']=round(array_sum($adjustments)/8,2);
    $candidate['total']=round($candidate['kra']*.8+$candidate['core']*.2,2);
    $candidate['core_adjustments_changed']=$changed;
    return $candidate;
}

function annualReviewToken($conn, array $member): string
{
    return hash('sha256', json_encode([$member['current_version'], $member['self_values_json'],
        annualKraRows($conn, (int)$member['evaluation_id']), annualPeers($conn, $member),
        annualQuery($conn, 'SELECT member_id,employee_id,evaluation_id FROM annual_evaluation_members WHERE cycle_id=? ORDER BY member_id', [(int)$member['cycle_id']])]));
}

/** Snapshot a department roster using active regular employees with applicable Annual forms. */
function annualRegisterSubmission($conn, int $evaluationId): void
{
    $ev = annualQuery($conn, "SELECT ev.*, e.department_id, e.job_title_id, e.employment_status, e.job_title, jt.reports_to
        FROM evaluations ev JOIN employees e ON e.employee_id=ev.employee_id
        LEFT JOIN job_titles jt ON jt.job_title_id=e.job_title_id
        WHERE ev.evaluation_id=? AND ev.deleted_at IS NULL AND ev.evaluation_type='Annual'", [$evaluationId])[0] ?? null;
    if (!$ev || $ev['employment_status'] !== 'Regular') throw new DomainException('Annual evaluation requires a regular employee.');
    $assigned=annualQuery($conn,"SELECT 1 FROM evaluation_templates t JOIN evaluation_template_positions tp ON tp.template_id=t.template_id
        WHERE t.template_id=? AND t.target_department_id=? AND tp.job_title_id=? AND t.deleted_at IS NULL",
        [(int)$ev['template_id'],(int)$ev['department_id'],(int)$ev['job_title_id']]);
    if (!$assigned) throw new DomainException('Transfer review required: this Annual template does not match the current department and position.');
    $start = $ev['evaluation_period_start']; $end = $ev['evaluation_period_end'];
    if (!$start || !$end || $start > $end) throw new DomainException('A valid annual evaluation period is required.');
    $criteria = annualQuery($conn, "SELECT ec.criterion_name, es.score_value FROM evaluation_criteria ec
        JOIN evaluation_scores es ON es.criterion_id=ec.criterion_id AND es.evaluation_id=?
        WHERE ec.section='Behavior' ORDER BY ec.sort_order, ec.criterion_id", [$evaluationId]);
    if (array_column($criteria, 'criterion_name') !== array_column(annualCoreValues(), 'name')) {
        throw new DomainException('This Annual template must use the eight standard Core Behaviors & Values. Ask its creator to publish an updated template.');
    }
    $values = json_encode(annualValidateScores(array_column($criteria, 'score_value')));
    annualQuery($conn, "INSERT INTO annual_evaluation_cycles (department_id,period_start,period_end) VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE cycle_id=cycle_id", [(int)$ev['department_id'], $start, $end]);
    $cycle = annualQuery($conn, 'SELECT * FROM annual_evaluation_cycles WHERE department_id=? AND period_start=? AND period_end=? FOR UPDATE',
        [(int)$ev['department_id'], $start, $end])[0];
    $cycleId = (int)$cycle['cycle_id'];
    $roster = annualQuery($conn, "SELECT DISTINCT e.employee_id,e.job_title_id,e.job_title,j.reports_to
        FROM employees e JOIN job_titles j ON j.job_title_id=e.job_title_id
        WHERE e.department_id=? AND e.is_active=1 AND e.deleted_at IS NULL AND e.employment_status='Regular'
          AND EXISTS (SELECT 1 FROM evaluation_templates t JOIN evaluation_template_positions tp ON tp.template_id=t.template_id
             WHERE tp.job_title_id=e.job_title_id AND t.target_department_id=e.department_id
               AND t.evaluation_type='Annual' AND t.status='Active' AND t.deleted_at IS NULL)", [(int)$ev['department_id']]);
    foreach ($roster as $r) {
        annualQuery($conn, "INSERT INTO annual_evaluation_members (cycle_id,employee_id,position_id,reviewer_position_id,position_name)
            VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE member_id=member_id", [$cycleId,(int)$r['employee_id'],(int)$r['job_title_id'], $r['reports_to'], $r['job_title']]);
    }
    $member = annualQuery($conn, 'SELECT * FROM annual_evaluation_members WHERE cycle_id=? AND employee_id=?', [$cycleId,(int)$ev['employee_id']])[0] ?? null;
    if (!$member) throw new DomainException('No eligible department Annual template is assigned to this position.');
    if ($member['evaluation_id'] && (int)$member['evaluation_id'] !== $evaluationId) throw new DomainException('An Annual evaluation already exists for this department period.');
    if ((int)$member['position_id'] !== (int)$ev['job_title_id']) throw new DomainException('Transfer review required before submitting this evaluation.');
    if ($member['self_values_json']) throw new DomainException('This Annual self-rating has already been submitted.');
    annualQuery($conn, 'UPDATE annual_evaluation_members SET evaluation_id=?,self_values_json=? WHERE member_id=?', [$evaluationId,$values,(int)$member['member_id']]);
    annualCandidate($conn,annualMember($conn,$evaluationId));
    $submittedMember=annualMember($conn,$evaluationId);
    annualNotifyHeads($conn,$submittedMember,'Annual self-rating ready for KRA review',
        $submittedMember['first_name'].' '.$submittedMember['last_name'].' submitted an Annual self-rating. Review and approve the KRA in Performance Evaluation. Final Annual approval waits for all expected 360 ratings.');
    annualNotifyEmployee($conn,(int)$ev['employee_id'],'Annual self-rating submitted','Your department 360 rating tasks are available.');
    annualNotifyCycleRaters($conn,$cycleId,$evaluationId,(int)$ev['employee_id'],!empty($cycle['first_finalized_at']));
}

function annualNotifyCycleRaters($conn, int $cycleId, int $evaluationId, int $joiningEmployeeId, bool $late): void
{
    $members=annualQuery($conn,'SELECT employee_id FROM annual_evaluation_members WHERE cycle_id=? AND evaluation_id IS NOT NULL',[$cycleId]);
    if (count($members)<2) return;
    foreach ($members as $member) {
        $users=annualQuery($conn,'SELECT user_id FROM users WHERE employee_id=?',[(int)$member['employee_id']]);
        foreach ($users as $user) {
            $actor=annualActor($conn,(int)$user['user_id']);
            if (!$actor || !annualCanRate($actor)) continue;
            $newTarget=$late && (int)$member['employee_id']!==$joiningEmployeeId;
            $title=$newTarget ? 'Late participant: annual peer rating available' : 'Department 360 ratings available';
            $link=annualUrl($actor,$newTarget ? '?view=rate&evaluation_id='.$evaluationId : '?view=peers&cycle_id='.$cycleId);
            if (!annualQuery($conn,'SELECT 1 FROM notifications WHERE user_id=? AND title=? AND link=?',[(int)$user['user_id'],$title,$link])) {
                createNotification($conn,(int)$user['user_id'],$title,'Review your department peer-rating tasks. Rate Core Behaviors & Values, then confirm your submission.',$link,'evaluations',true);
            }
        }
    }
}

function annualNotifyEmployee($conn, int $employeeId, string $title, string $message, int $evaluationId = 0): void
{
    $users = annualQuery($conn, "SELECT user_id FROM users WHERE employee_id=? AND is_active=1 AND deleted_at IS NULL AND COALESCE(account_hold,0)=0", [$employeeId]);
    foreach ($users as $u) {
        $actor = annualActor($conn,(int)$u['user_id']);
        if (!$actor) continue;
        createNotification($conn,(int)$u['user_id'],$title,$message,
            annualUrl($actor,$evaluationId ? '?view=results&evaluation_id='.$evaluationId : '?view=peers'), 'evaluations', false);
    }
}

function annualNotifyHeads($conn, array $member, string $title, string $message): void
{
    $users = annualQuery($conn, "SELECT u.user_id FROM users u JOIN employees e ON e.employee_id=u.employee_id
        WHERE e.job_title_id=? AND e.employee_id<>? AND e.is_active=1 AND e.deleted_at IS NULL
          AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0", [(int)$member['reviewer_position_id'],(int)$member['employee_id']]);
    foreach ($users as $u) {
        $actor=annualActor($conn,(int)$u['user_id']);
        if ($actor && annualCanFinalize($conn,$actor,$member)) createNotification($conn,(int)$u['user_id'],$title,$message,
            annualUrl($actor,'?view=review&evaluation_id='.$member['evaluation_id']), 'evaluations', true);
    }
}

function annualSubmitPeers($conn, array $actor, int $evaluationId, array $scores, bool $manageTransaction = true, string $signature = ''): void
{
    ensureAuditTrailSchema($conn);
    $scores = annualValidateScores($scores);
    if ($signature !== '' && (!preg_match('/^data:image\/png;base64,/', $signature) || strlen($signature) > 100000)) {
        throw new DomainException('The rater signature is invalid. Please sign again.');
    }
    if ($signature !== '') {
        $signatureBytes=base64_decode(substr($signature,strpos($signature,',')+1),true);
        $signatureInfo=$signatureBytes===false ? false : @getimagesizefromstring($signatureBytes);
        if (!$signatureInfo || ($signatureInfo[2] ?? null)!==IMAGETYPE_PNG) throw new DomainException('The rater signature image is invalid. Please sign again.');
    }
    if (!annualCanRate($actor)) throw new DomainException('Use your authorized department portal to submit 360 ratings.');
    if ($manageTransaction) $conn->begin_transaction();
    try {
        $member = annualMember($conn,$evaluationId);
        if (!$member) throw new DomainException('This annual evaluation is unavailable.');
        annualQuery($conn,'SELECT cycle_id FROM annual_evaluation_cycles WHERE cycle_id=? FOR UPDATE',[(int)$member['cycle_id']]);
        $member=annualMember($conn,$evaluationId);
        annualAssertSnapshotActive($member);
        $rater=annualQuery($conn,'SELECT evaluation_id FROM annual_evaluation_members WHERE cycle_id=? AND employee_id=?',[(int)$member['cycle_id'],(int)$actor['employee_id']])[0] ?? null;
        if (!$rater || !$rater['evaluation_id']) throw new DomainException('Submit your Annual self-rating for this department period first.');
        annualAssertSnapshotActive(annualMember($conn,(int)$rater['evaluation_id']));
        if ((int)$actor['employee_id']===(int)$member['employee_id'] || (int)$actor['department_id']!==(int)$member['department_id']) {
            throw new DomainException('Only coworkers in this department cycle can rate this employee.');
        }
        $exists=annualQuery($conn,'SELECT submission_id FROM annual_peer_submissions WHERE cycle_id=? AND rater_employee_id=? AND target_employee_id=?',
            [(int)$member['cycle_id'],(int)$actor['employee_id'],(int)$member['employee_id']]);
        if ($exists) throw new DomainException('Your submitted rating is locked and cannot be edited.');
        $late=$member['first_finalized_at'] ? 1 : 0;
        annualQuery($conn,'INSERT INTO annual_peer_submissions (cycle_id,rater_employee_id,target_employee_id,submitted_by,scores_json,rater_signature_data,rater_signed_at,is_late) VALUES (?,?,?,?,?,?,?,?)',
            [(int)$member['cycle_id'],(int)$actor['employee_id'],(int)$member['employee_id'],(int)$actor['user_id'],json_encode($scores),$signature !== '' ? $signature : null,$signature !== '' ? date('Y-m-d H:i:s') : null,$late]);
        annualQuery($conn,'UPDATE notifications SET needs_action=0 WHERE user_id=? AND link=?',
            [(int)$actor['user_id'],annualUrl($actor,'?view=rate&evaluation_id='.$evaluationId)]);
        $remaining=(int)annualQuery($conn,"SELECT COUNT(*) AS n FROM annual_evaluation_members m
            WHERE m.cycle_id=? AND m.employee_id<>? AND m.evaluation_id IS NOT NULL
              AND NOT EXISTS(SELECT 1 FROM annual_peer_submissions s WHERE s.cycle_id=m.cycle_id
                  AND s.target_employee_id=m.employee_id AND s.rater_employee_id=?)",
            [(int)$member['cycle_id'],(int)$actor['employee_id'],(int)$actor['employee_id']])[0]['n'];
        if ($remaining===0) annualQuery($conn,'UPDATE notifications SET needs_action=0 WHERE user_id=? AND link=?',
            [(int)$actor['user_id'],annualUrl($actor,'?view=peers&cycle_id='.(int)$member['cycle_id'])]);
        $revisionNotice=(int)$member['current_version']>0 && !$member['revision_pending'];
        if ((int)$member['current_version']>0) {
            annualQuery($conn,'UPDATE annual_evaluation_members SET revision_pending=1 WHERE member_id=?',[(int)$member['member_id']]);
            if ($revisionNotice) annualNotifyEmployee($conn,(int)$member['employee_id'],'Annual rating revision pending','A late 360 submission has arrived. Your previous final score remains current until your immediate head re-finalizes.', $evaluationId);
        }
        logAudit($conn,(int)$actor['user_id'],'CREATE','Annual 360',$evaluationId, $late ? 'Submitted locked late peer rating; any final score revision requires immediate-head approval.' : 'Submitted locked peer rating.');
        $received=(int)annualQuery($conn,'SELECT COUNT(*) AS n FROM annual_peer_submissions WHERE cycle_id=? AND target_employee_id=?',[(int)$member['cycle_id'],(int)$member['employee_id']])[0]['n'];
        $expected=annualExpectedPeers($conn,(int)$member['cycle_id'],(int)$member['employee_id']);
        if ($revisionNotice || $received===$expected) annualNotifyHeads($conn,$member,$revisionNotice ? 'Late annual 360 rating received' : 'Annual 360 summary ready','Review the updated 360 summary for '.$member['first_name'].' '.$member['last_name'].'.');
        if ($manageTransaction) $conn->commit();
    } catch (Throwable $e) { if ($manageTransaction) $conn->rollback(); throw $e; }
}

/** Approve KRAs independently of the pending department peer-rating phase. */
function annualApproveKra($conn, array $actor, int $evaluationId, array $ratings, string $remarks, string $token, bool $manageTransaction = true, bool $requireRemarks = true): void
{
    ensureAuditTrailSchema($conn);
    if ($manageTransaction) $conn->begin_transaction();
    try {
        $member=annualMember($conn,$evaluationId);
        if (!$member) throw new DomainException('This Annual evaluation is unavailable.');
        annualQuery($conn,'SELECT cycle_id FROM annual_evaluation_cycles WHERE cycle_id=? FOR UPDATE',[(int)$member['cycle_id']]);
        $member=annualMember($conn,$evaluationId);
        if (!annualCanFinalize($conn,$actor,$member)) throw new DomainException('Only this employee\'s immediate head can approve the KRA ratings.');
        annualAssertSnapshotActive($member);
        if ((int)$member['current_version']>0 && !$member['revision_pending']) throw new DomainException('This result is already finalized.');
        if (!$token || !hash_equals(annualReviewToken($conn,$member),$token)) throw new DomainException('Ratings changed while you were reviewing. Reload before approving KRA.');
        $changes=annualApplyKraRatings($conn,$actor,$evaluationId,$ratings);
        if ($requireRemarks && $changes && $remarks==='') throw new DomainException('Explain your KRA adjustments before approving.');
        annualQuery($conn,'INSERT INTO annual_kra_approvals (member_id,approved_by,remarks,ratings_json) VALUES (?,?,?,?)',
            [(int)$member['member_id'],(int)$actor['user_id'],$remarks,json_encode(annualKraRows($conn,$evaluationId))]);
        logAudit($conn,(int)$actor['user_id'],'APPROVE','Annual KRA',$evaluationId,'Approved KRA ratings independently of Annual finalization. '.$remarks);
        if ($manageTransaction) $conn->commit();
    } catch (Throwable $e) { if ($manageTransaction) $conn->rollback(); throw $e; }
}

/** Save the head's KRA review and their own peer rating together, or neither. */
function annualReviewAndRate($conn, array $actor, int $evaluationId, array $ratings, string $token, array $scores, string $signature): void
{
    if ($signature==='') throw new DomainException('Sign in the rater signature box before submitting.');
    ensureAuditTrailSchema($conn);
    $conn->begin_transaction();
    try {
        annualApproveKra($conn,$actor,$evaluationId,$ratings,'',$token,false,false);
        annualSubmitPeers($conn,$actor,$evaluationId,$scores,false,$signature);
        $conn->commit();
    } catch (Throwable $e) { $conn->rollback(); throw $e; }
}

function annualApplyKraRatings($conn, array $actor, int $evaluationId, array $ratings): array
{
    $rows=annualKraRows($conn,$evaluationId);
    if (!$rows || count($ratings)!==count($rows)) throw new DomainException('Review every KRA rating before approving.');
    $changes=[];
    foreach ($rows as $row) {
        $id=(int)$row['score_id'];
        $value=$ratings[$id] ?? null;
        if (!is_scalar($value) || !is_numeric($value) || !is_finite((float)$value) || $value<1 || $value>4
            || abs(round((float)$value,2)-(float)$value)>.00001) throw new DomainException('KRA ratings must be from 1.00 to 4.00 with at most two decimals.');
        if (abs((float)$row['rating']-(float)$value)>.00001) $changes[]=$row['criterion_name'];
        annualQuery($conn,'UPDATE evaluation_scores SET supervisor_override_score=?,supervisor_override_by=?,supervisor_override_at=NOW() WHERE score_id=? AND evaluation_id=?',
            [(float)$value,(int)$actor['user_id'],$id,$evaluationId]);
    }
    return $changes;
}

function annualFinalize($conn, array $actor, int $evaluationId, array $ratings, string $remarks, string $token, array $coreAdjustments = []): void
{
    ensureAuditTrailSchema($conn);
    $conn->begin_transaction();
    try {
        $member=annualMember($conn,$evaluationId);
        if (!$member) throw new DomainException('This Annual evaluation is unavailable.');
        annualQuery($conn,'SELECT cycle_id FROM annual_evaluation_cycles WHERE cycle_id=? FOR UPDATE',[(int)$member['cycle_id']]);
        $member=annualMember($conn,$evaluationId);
        if (!annualCanFinalize($conn,$actor,$member)) throw new DomainException('Only this employee\'s immediate head can finalize their Annual evaluation.');
        annualAssertSnapshotActive($member);
        if ((int)$member['current_version']>0 && !$member['revision_pending']) throw new DomainException('This result is already finalized.');
        if (!$token || !hash_equals(annualReviewToken($conn,$member),$token)) throw new DomainException('Ratings changed while you were reviewing. Reload the summary before finalizing.');
        $changes=annualApplyKraRatings($conn,$actor,$evaluationId,$ratings);
        $candidate=annualApplyCoreAdjustments(annualCandidate($conn,$member),$coreAdjustments);
        $expected=annualExpectedPeers($conn,(int)$member['cycle_id'],(int)$member['employee_id']);
        if ($candidate['peer_count']<$expected) {
            throw new DomainException('Finalization is unavailable until all expected raters have submitted their 360 ratings.');
        }
        if (($changes || $candidate['core_adjustments_changed'] || $member['revision_pending']) && trim($remarks)==='') {
            throw new DomainException('Explain KRA or Core Values adjustments, or late-score revisions, in the review remarks.');
        }
        if ($expected>0 && $candidate['peer_count']===0) throw new DomainException('At least one submitted peer rating is needed before finalization.');
        $version=(int)$member['current_version']+1;
        $snapshot=$candidate+['core_adjustments'=>$coreAdjustments ? array_map(static fn($score)=>round((float)$score,2),$coreAdjustments) : null,'kra_rows'=>annualKraRows($conn,$evaluationId),'self_values'=>json_decode($member['self_values_json'],true),
            'peers'=>annualPeers($conn,$member), 'expected_peers'=>$expected,'previous_score'=>$member['official_score']];
        annualQuery($conn,'INSERT INTO annual_evaluation_versions (member_id,version_number,finalized_by,remarks,snapshot_json) VALUES (?,?,?,?,?)',
            [(int)$member['member_id'],$version,(int)$actor['user_id'],$remarks,json_encode($snapshot)]);
        annualQuery($conn,'UPDATE annual_evaluation_members SET current_version=?,revision_pending=0,finalized_at=NOW() WHERE member_id=?',[$version,(int)$member['member_id']]);
        annualQuery($conn,'UPDATE annual_evaluation_cycles SET first_finalized_at=COALESCE(first_finalized_at,NOW()) WHERE cycle_id=?',[(int)$member['cycle_id']]);
        annualQuery($conn,"UPDATE evaluations SET kra_subtotal=?,behavior_average=?,total_score=?,performance_level=?,status='Approved',approved_by=?,approved_date=NOW() WHERE evaluation_id=?",
            [$candidate['kra'],$candidate['core'],$candidate['total'],getPerformanceLevel($candidate['total']),(int)$actor['user_id'],$evaluationId]);
        annualQuery($conn,'UPDATE notifications SET needs_action=0 WHERE link LIKE ?', ['%/annual-evaluations.php?view=review&evaluation_id='.$evaluationId]);
        logAudit($conn,(int)$actor['user_id'],'APPROVE','Annual Evaluation',$evaluationId,'Finalized version '.$version.'. Score: '.$candidate['total'].'. '.$remarks);
        annualNotifyEmployee($conn,(int)$member['employee_id'],'Annual evaluation finalized','Your immediate head finalized Annual result version '.$version.'. Final score: '.number_format($candidate['total'],2).'.', $evaluationId);
        $conn->commit();
    } catch (Throwable $e) { $conn->rollback(); throw $e; }
}

/** Counts work only; rendering navigation never creates notifications or cycles. */
function annualPendingReviewCount($conn, array $actor): int
{
    if (!$conn->query("SHOW TABLES LIKE 'annual_evaluation_members'")->num_rows) return 0;
    $reviews=annualQuery($conn,"SELECT m.evaluation_id FROM annual_evaluation_members m
        WHERE m.reviewer_position_id=? AND m.employee_id<>? AND m.evaluation_id IS NOT NULL
          AND (m.current_version=0 OR m.revision_pending=1)",[(int)$actor['job_title_id'],(int)$actor['employee_id']]);
    $count=0;
    foreach ($reviews as $review) {
        $member=annualMember($conn,(int)$review['evaluation_id']);
        if ($member && annualCanFinalize($conn,$actor,$member)) $count++;
    }
    return $count;
}

function annualPendingCount($conn, array $actor): int
{
    if (!$conn->query("SHOW TABLES LIKE 'annual_evaluation_members'")->num_rows) return 0;
    $count=annualPendingReviewCount($conn,$actor);
    if (annualCanRate($actor)) {
        $row=annualQuery($conn,"SELECT COUNT(*) AS n FROM annual_evaluation_members target
            JOIN annual_evaluation_members own ON own.cycle_id=target.cycle_id AND own.employee_id=? AND own.evaluation_id IS NOT NULL
            JOIN annual_evaluation_cycles c ON c.cycle_id=target.cycle_id AND c.department_id=?
            WHERE target.employee_id<>own.employee_id AND target.evaluation_id IS NOT NULL
              AND NOT EXISTS(SELECT 1 FROM annual_peer_submissions s WHERE s.cycle_id=target.cycle_id
                  AND s.target_employee_id=target.employee_id AND s.rater_employee_id=own.employee_id)",[(int)$actor['employee_id'],(int)$actor['department_id']])[0];
        $count+=(int)$row['n'];
    }
    return $count;
}
