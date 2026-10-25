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
    if (!$conn->query("SHOW COLUMNS FROM annual_evaluation_cycles LIKE 'completed_at'")->num_rows) {
        $conn->query('ALTER TABLE annual_evaluation_cycles ADD COLUMN completed_at DATETIME NULL');
    }
    foreach (['rater_signature_data'=>'LONGTEXT NULL','rater_signed_at'=>'DATETIME NULL'] as $column=>$definition) {
        if (!$conn->query("SHOW COLUMNS FROM annual_kra_approvals LIKE '{$column}'")->num_rows) {
            $conn->query("ALTER TABLE annual_kra_approvals ADD COLUMN {$column} {$definition}");
        }
    }
    if (!$conn->query("SHOW COLUMNS FROM annual_evaluation_cycles LIKE 'first_completed_at'")->num_rows) {
        $conn->query('ALTER TABLE annual_evaluation_cycles ADD COLUMN first_completed_at DATETIME NULL');
        $conn->query('UPDATE annual_evaluation_cycles SET first_completed_at=completed_at WHERE completed_at IS NOT NULL');
    }
    if (!$conn->query("SHOW COLUMNS FROM annual_evaluation_members LIKE 'is_late_participant'")->num_rows) {
        $conn->query('ALTER TABLE annual_evaluation_members ADD COLUMN is_late_participant TINYINT NOT NULL DEFAULT 0');
    }
    foreach (['ready_for_approval_at'=>'DATETIME NULL','cycle_approved_by'=>'INT NULL'] as $column=>$definition) {
        if (!$conn->query("SHOW COLUMNS FROM annual_evaluation_cycles LIKE '{$column}'")->num_rows) $conn->query("ALTER TABLE annual_evaluation_cycles ADD COLUMN {$column} {$definition}");
    }
    foreach (['started_at'=>'DATETIME NULL','started_by'=>'INT NULL','self_rating_deadline'=>'DATE NULL'] as $column=>$definition) {
        if (!$conn->query("SHOW COLUMNS FROM annual_evaluation_cycles LIKE '{$column}'")->num_rows) $conn->query("ALTER TABLE annual_evaluation_cycles ADD COLUMN {$column} {$definition}");
    }
    if (!$conn->query("SHOW COLUMNS FROM employees LIKE 'regularization_effective_date'")->num_rows) {
        $conn->query('ALTER TABLE employees ADD COLUMN regularization_effective_date DATE NULL');
    }
    $conn->query('CREATE TABLE IF NOT EXISTS annual_cycle_department_owners (
        department_id INT NOT NULL PRIMARY KEY,
        owner_position_id INT NOT NULL,
        CONSTRAINT fk_annual_cycle_owner_department FOREIGN KEY (department_id) REFERENCES departments(department_id),
        CONSTRAINT fk_annual_cycle_owner_position FOREIGN KEY (owner_position_id) REFERENCES job_titles(job_title_id)
    )');
    // AP is the pilot. The mapping is explicit and can be changed without changing authorization code.
    $conn->query("INSERT IGNORE INTO annual_cycle_department_owners (department_id,owner_position_id)
        SELECT d.department_id,j.job_title_id FROM departments d JOIN job_titles j ON j.department_id=d.department_id
        WHERE d.department_name='Acquired Properties' AND j.job_title='AP Manager I' AND j.is_active=1");
    $ready[$key] = true;
    // Cycles created before the explicit-start workflow are already in progress.
    $conn->query('UPDATE annual_evaluation_cycles c SET c.started_at=c.created_at
        WHERE c.started_at IS NULL AND EXISTS (SELECT 1 FROM annual_evaluation_members m WHERE m.cycle_id=c.cycle_id)');
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

function annualIsApDepartment(array $actor): bool
{
    return strcasecmp(trim((string)($actor['department_name'] ?? '')), 'Acquired Properties') === 0;
}

function annualUsesManagedCycle(array $actor): bool
{
    return annualIsHrDepartment($actor) || annualIsApDepartment($actor);
}

function annualCanOwnCycle($conn, array $actor, int $departmentId): bool
{
    if ($actor['employment_status'] !== 'Regular' || (int)$actor['department_id'] !== $departmentId) return false;
    if (annualIsHrDepartment($actor)) return $actor['role'] === 'HR Manager';
    if (!annualIsApDepartment($actor) || $actor['role'] !== 'Employee') return false;
    return (bool)annualQuery($conn, 'SELECT 1 FROM annual_cycle_department_owners
        WHERE department_id=? AND owner_position_id=?', [$departmentId,(int)$actor['job_title_id']]);
}

function annualCanViewCycleSetup($conn, array $actor, int $departmentId): bool
{
    if (annualCanOwnCycle($conn,$actor,$departmentId)) return true;
    if ($actor['employment_status'] !== 'Regular' || $actor['role'] !== 'Employee'
        || (int)$actor['department_id'] !== $departmentId || !annualIsApDepartment($actor)) return false;
    return (bool)annualQuery($conn,"SELECT 1 FROM job_titles
        WHERE job_title_id=? AND department_id=?
          AND (job_title LIKE 'AP Supervisor%' OR job_title='VP for Acquired Properties')",
        [(int)$actor['job_title_id'],$departmentId]);
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
            ev.created_at AS evaluation_created_at, ev.submitted_date AS evaluation_submitted_at,
            ev.supervisor_comments AS head_comments,
            ev.kra_subtotal, ev.behavior_average,
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
    $count=(int)annualQuery($conn,'SELECT COUNT(*) AS n FROM annual_evaluation_members m
        JOIN employees e ON e.employee_id=m.employee_id WHERE m.cycle_id=? AND m.employee_id<>? AND '
        .annualRosterEligibilitySql(),[$cycleId,$employeeId])[0]['n'];
    return $count;
}

/** Preview regular department employees in position hierarchy order. */
function annualCycleRosterPreview($conn, int $departmentId): array
{
    $employees = annualQuery($conn, "SELECT e.employee_id,e.first_name,e.last_name,e.job_title_id,
            j.job_title,j.reports_to,h.job_title AS reviewer_position_name
        FROM employees e LEFT JOIN job_titles j ON j.job_title_id=e.job_title_id
        LEFT JOIN job_titles h ON h.job_title_id=j.reports_to
        WHERE e.department_id=? AND e.is_active=1 AND e.deleted_at IS NULL
          AND e.employment_status='Regular'
        ORDER BY j.rank_category_id,j.job_title_id,e.last_name,e.first_name", [$departmentId]);
    foreach ($employees as &$employee) {
        $issues = [];
        $positionId = (int)$employee['job_title_id'];
        if (!$positionId || !$employee['job_title']) $issues[] = 'Active position missing';
        if (!$employee['reports_to'] || !annualQuery($conn, "SELECT 1 FROM employees h
                JOIN users u ON u.employee_id=h.employee_id
                WHERE h.job_title_id=? AND h.employee_id<>? AND h.is_active=1 AND h.deleted_at IS NULL
                  AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0 LIMIT 1",
                [(int)$employee['reports_to'],(int)$employee['employee_id']])) $issues[] = 'Immediate head account missing';
        if (!annualQuery($conn, "SELECT 1 FROM users WHERE employee_id=? AND role='Employee'
                AND is_active=1 AND deleted_at IS NULL AND COALESCE(account_hold,0)=0 LIMIT 1",
                [(int)$employee['employee_id']])) $issues[] = 'Employee Portal account missing';
        $templates = annualQuery($conn, "SELECT t.template_id,t.kra_weight,t.behavior_weight FROM evaluation_templates t
            JOIN evaluation_template_positions p ON p.template_id=t.template_id
            JOIN users creator_account ON creator_account.user_id=t.created_by
            JOIN employees creator ON creator.employee_id=creator_account.employee_id
            WHERE t.target_department_id=? AND p.job_title_id=? AND t.evaluation_type='Annual'
              AND t.status='Active' AND t.deleted_at IS NULL
              AND creator.job_title_id=? AND creator.is_active=1 AND creator.deleted_at IS NULL
              AND creator_account.is_active=1 AND creator_account.deleted_at IS NULL
              AND COALESCE(creator_account.account_hold,0)=0",
            [$departmentId,$positionId,(int)$employee['reports_to']]);
        $validTemplate = false;
        foreach ($templates as $template) {
            $criteria = annualQuery($conn, "SELECT section,criterion_name,weight FROM evaluation_criteria
                WHERE template_id=? ORDER BY section,sort_order,criterion_id", [(int)$template['template_id']]);
            $kra = array_values(array_filter($criteria,static fn($row)=>$row['section']==='KRA'));
            $behavior = array_values(array_filter($criteria,static fn($row)=>$row['section']==='Behavior'));
            if ($kra && (int)round(array_sum(array_column($kra,'weight'))*100)===10000
                && array_column($behavior,'criterion_name')===array_column(annualCoreValues(),'name')
                && (float)$template['kra_weight']===80.0 && (float)$template['behavior_weight']===20.0) {
                $validTemplate = true; break;
            }
        }
        if (!$validTemplate) $issues[] = 'Annual form from immediate head missing or invalid (80/20, eight Core Values, KRA 100%)';
        $employee['issues'] = $issues;
    }
    unset($employee);
    return $employees;
}

/** Collapse employee readiness into one row per position without hiding individual blockers. */
function annualCyclePositionReadinessRows(array $employees): array
{
    $positions=[];
    foreach ($employees as $employee) {
        $positionId=(int)($employee['job_title_id'] ?? 0);
        $key=$positionId ? 'position-'.$positionId : 'employee-'.(int)$employee['employee_id'];
        if (!isset($positions[$key])) {
            $positions[$key]=[
                'job_title'=>$employee['job_title'] ?: 'Position missing',
                'reviewer_position_name'=>$employee['reviewer_position_name'] ?? null,
                'employee_count'=>0,
                'blocked_count'=>0,
                'issues'=>[],
            ];
        }
        $positions[$key]['employee_count']++;
        if (!empty($employee['issues'])) {
            $positions[$key]['blocked_count']++;
            foreach ($employee['issues'] as $issue) $positions[$key]['issues'][$issue]=true;
        }
    }
    foreach ($positions as &$position) $position['issues']=array_keys($position['issues']);
    unset($position);
    return array_values($positions);
}

function annualApCycleReadyToStart($conn, array $actor): bool
{
    if (!annualIsApDepartment($actor) || !annualCanOwnCycle($conn,$actor,(int)$actor['department_id'])) return false;
    ensureAnnualEvaluationSchema($conn);
    $year=date('Y');
    $from=$year.'-01-01';
    $to=$year.'-12-31';
    if (annualQuery($conn,'SELECT 1 FROM annual_evaluation_cycles WHERE department_id=?
            AND ((started_at IS NOT NULL AND completed_at IS NULL) OR (period_start<=? AND period_end>=?)) LIMIT 1',
            [(int)$actor['department_id'],$to,$from])) return false;
    $roster=annualCycleRosterPreview($conn,(int)$actor['department_id']);
    return (bool)$roster && !array_filter($roster,static fn($person)=>!empty($person['issues']));
}

/** Keep one actionable notification while the AP Manager can start this year's cycle. */
function annualSyncApCycleReadinessNotification($conn, array $actor): bool
{
    if (!annualIsApDepartment($actor) || !annualCanOwnCycle($conn,$actor,(int)$actor['department_id'])) return false;
    $ready=annualApCycleReadyToStart($conn,$actor);
    $title='AP Annual cycle ready to start ('.date('Y').')';
    $existing=annualQuery($conn,'SELECT notification_id FROM notifications WHERE user_id=? AND title=? LIMIT 1',
        [(int)$actor['user_id'],$title]);
    if ($ready && !$existing) {
        createNotification($conn,(int)$actor['user_id'],$title,
            'All Acquired Properties Annual forms and accounts are ready. Start the department evaluation cycle.',
            annualUrl($actor,'?view=peers'),'evaluations',true);
    } elseif (!$ready && $existing) {
        annualQuery($conn,'UPDATE notifications SET needs_action=0 WHERE user_id=? AND title=? AND needs_action=1',
            [(int)$actor['user_id'],$title]);
    }
    return $ready;
}

function annualStartCycle($conn, array $actor, int $departmentId, string $from, string $to, string $deadline): int
{
    if (!annualCanOwnCycle($conn,$actor,$departmentId)) {
        throw new DomainException('Only the assigned department cycle owner can start this Annual cycle.');
    }
    foreach ([$from,$to,$deadline] as $date) {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if (!$parsed || $parsed->format('Y-m-d')!==$date) throw new DomainException('Enter valid period and deadline dates.');
    }
    if ($from>$to) throw new DomainException('Period From must be on or before Period To.');
    if ($deadline < date('Y-m-d')) throw new DomainException('The self-rating deadline cannot be in the past.');
    $department = annualQuery($conn,'SELECT department_name FROM departments WHERE department_id=? AND is_active=1 AND deleted_at IS NULL',[$departmentId])[0] ?? null;
    if (!$department) throw new DomainException('Select an active department.');
    $roster = annualCycleRosterPreview($conn,$departmentId);
    if (!$roster) throw new DomainException('This department has no active regular employees.');
    foreach ($roster as $employee) {
        if ($employee['issues']) throw new DomainException($employee['first_name'].' '.$employee['last_name'].': '.implode('; ',$employee['issues']));
    }
    $conn->begin_transaction();
    try {
        $existing = annualQuery($conn,'SELECT cycle_id FROM annual_evaluation_cycles
            WHERE department_id=? AND period_start<=? AND period_end>=? LIMIT 1 FOR UPDATE',[$departmentId,$to,$from]);
        if ($existing) throw new DomainException('An Annual cycle already covers some or all of this period for the department.');
        if (annualQuery($conn,'SELECT cycle_id FROM annual_evaluation_cycles
                WHERE department_id=? AND started_at IS NOT NULL AND completed_at IS NULL LIMIT 1 FOR UPDATE',[$departmentId])) {
            throw new DomainException('Complete the department’s current Annual cycle before starting a new period.');
        }
        annualQuery($conn,'INSERT INTO annual_evaluation_cycles
            (department_id,period_start,period_end,started_at,started_by,self_rating_deadline)
            VALUES (?,?,?,NOW(),?,?)',[$departmentId,$from,$to,(int)$actor['user_id'],$deadline]);
        $cycleId=(int)$conn->insert_id;
        foreach ($roster as $employee) {
            annualQuery($conn,'INSERT INTO annual_evaluation_members
                (cycle_id,employee_id,position_id,reviewer_position_id,position_name) VALUES (?,?,?,?,?)',
                [$cycleId,(int)$employee['employee_id'],(int)$employee['job_title_id'],(int)$employee['reports_to'],$employee['job_title']]);
            foreach (annualQuery($conn,"SELECT user_id FROM users WHERE employee_id=? AND role='Employee'
                    AND is_active=1 AND deleted_at IS NULL AND COALESCE(account_hold,0)=0",[(int)$employee['employee_id']]) as $user) {
                createNotification($conn,(int)$user['user_id'],'Annual self-rating is open',
                    $department['department_name'].' Annual evaluation covers '.$from.' to '.$to.'. Submit your self-rating by '.$deadline.'.',
                    BASE_URL.'/employee/self-rating.php','evaluations',true);
            }
        }
        logAudit($conn,(int)$actor['user_id'],'CREATE','Annual Cycle',$cycleId,
            $department['department_name'].' '.$from.' to '.$to.'; self-rating deadline '.$deadline.'; '.count($roster).' members.');
        if (annualIsApDepartment($department)) {
            annualQuery($conn,'UPDATE notifications SET needs_action=0 WHERE user_id=? AND title=? AND needs_action=1',
                [(int)$actor['user_id'],'AP Annual cycle ready to start ('.date('Y').')']);
        }
        $conn->commit();
        return $cycleId;
    } catch (Throwable $error) { $conn->rollback(); throw $error; }
}

function annualCycleForSelfRating($conn, int $departmentId, int $employeeId): ?array
{
    if (!$conn->query("SHOW TABLES LIKE 'annual_evaluation_cycles'")->num_rows) return null;
    $cycles=annualQuery($conn,'SELECT c.cycle_id,c.period_start,c.period_end,c.self_rating_deadline,c.completed_at,
            c.started_at,m.member_id,e.hire_date,e.regularization_effective_date,e.probationary_eligible_at
        FROM annual_evaluation_cycles c
        JOIN employees e ON e.employee_id=?
        LEFT JOIN annual_evaluation_members m ON m.cycle_id=c.cycle_id AND m.employee_id=?
        WHERE c.department_id=? AND c.started_at IS NOT NULL
          AND (m.member_id IS NULL OR m.evaluation_id IS NULL OR m.revision_pending=1)
        ORDER BY c.period_end DESC,c.cycle_id DESC',[$employeeId,$employeeId,$departmentId]);
    $department=annualQuery($conn,'SELECT department_name FROM departments WHERE department_id=?',[$departmentId])[0] ?? [];
    foreach ($cycles as $cycle) {
        if (annualIsApDepartment($department) && empty($cycle['member_id'])) {
            $eligibleDate=$cycle['regularization_effective_date'] ?: (!empty($cycle['probationary_eligible_at']) ? substr($cycle['probationary_eligible_at'],0,10) : $cycle['hire_date']);
            if (!$eligibleDate || $eligibleDate < $cycle['period_start'] || $eligibleDate > $cycle['period_end']) continue;
        }
        return $cycle;
    }
    return null;
}

/** Caller holds the cycle lock. Enroll AP employees regularized within the cycle period. */
function annualSyncApCycleRoster($conn, array $cycle): int
{
    if (!annualIsApDepartment($cycle) || empty($cycle['started_at'])) return 0;
    $newMembers=annualQuery($conn,"SELECT e.employee_id,e.job_title_id,j.job_title,j.reports_to
        FROM employees e JOIN job_titles j ON j.job_title_id=e.job_title_id
        LEFT JOIN annual_evaluation_members m ON m.cycle_id=? AND m.employee_id=e.employee_id
        WHERE e.department_id=? AND e.is_active=1 AND e.deleted_at IS NULL
          AND e.employment_status='Regular' AND m.member_id IS NULL
          AND COALESCE(e.regularization_effective_date,DATE(e.probationary_eligible_at),e.hire_date) BETWEEN ? AND ?",
        [(int)$cycle['cycle_id'],(int)$cycle['department_id'],$cycle['period_start'],$cycle['period_end']]);
    foreach ($newMembers as $person) {
        annualQuery($conn,'INSERT INTO annual_evaluation_members
            (cycle_id,employee_id,position_id,reviewer_position_id,position_name,is_late_participant)
            VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE member_id=member_id',
            [(int)$cycle['cycle_id'],(int)$person['employee_id'],(int)$person['job_title_id'],
                (int)$person['reports_to'],$person['job_title'],!empty($cycle['first_completed_at']) ? 1 : 0]);
        foreach (annualQuery($conn,"SELECT user_id FROM users WHERE employee_id=? AND role='Employee'
                AND is_active=1 AND deleted_at IS NULL AND COALESCE(account_hold,0)=0",
                [(int)$person['employee_id']]) as $user) {
            createNotification($conn,(int)$user['user_id'],'Annual self-rating available',
                'You joined the Acquired Properties Annual cycle for '.$cycle['period_start'].' to '.$cycle['period_end'].'. Submit your self-rating to begin department 360 ratings.',
                BASE_URL.'/employee/self-rating.php','evaluations',true);
        }
    }
    return count($newMembers);
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

function annualCanRateBeforeSelf($conn, array $actor, array $member): bool
{
    return annualCanRate($actor) && ($actor['role']==='HR Manager' || annualCanFinalize($conn,$actor,$member));
}

function annualCanSeeNames($conn, array $actor, array $member): bool
{
    if ((int)$actor['employee_id'] === (int)$member['employee_id']) return false;
    return annualCanFinalize($conn, $actor, $member);
}

function annualCanSeeOwnPeerNames($conn, array $actor, array $member): bool
{
    if ((int)$actor['employee_id'] !== (int)$member['employee_id']) return false;
    if (annualIsHrDepartment($actor) && $actor['role'] === 'HR Manager') return true;
    // A head is identified by the review assignments saved in this department cycle.
    return (bool)annualQuery($conn, 'SELECT 1 FROM annual_evaluation_members
        WHERE cycle_id=? AND reviewer_position_id=? AND employee_id<>? LIMIT 1',
        [(int)$member['cycle_id'], (int)$member['position_id'], (int)$member['employee_id']]);
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
    $join = ' JOIN employees e ON e.employee_id=s.rater_employee_id JOIN annual_evaluation_cycles c ON c.cycle_id=s.cycle_id';
    return annualQuery($conn, "SELECT s.submission_id, s.scores_json, s.submitted_at, s.is_late $names
        FROM annual_peer_submissions s $join WHERE s.cycle_id=? AND s.target_employee_id=? AND e.department_id=c.department_id ORDER BY s.submission_id",
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
    $departmentName = annualQuery($conn,'SELECT department_name FROM departments WHERE department_id=?',[(int)$ev['department_id']])[0]['department_name'] ?? '';
    $isManagedCycle = annualUsesManagedCycle(['department_name'=>$departmentName]);
    if (!$isManagedCycle) {
        // Other departments retain their existing submission-led workflow until
        // their own managers receive an explicit Start Annual Cycle action.
        annualQuery($conn,"INSERT INTO annual_evaluation_cycles (department_id,period_start,period_end) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE cycle_id=cycle_id",[(int)$ev['department_id'],$start,$end]);
    }
    $cycle = annualQuery($conn, 'SELECT * FROM annual_evaluation_cycles WHERE department_id=? AND period_start=? AND period_end=? FOR UPDATE',
        [(int)$ev['department_id'], $start, $end])[0] ?? null;
    if (!$cycle || ($isManagedCycle && empty($cycle['started_at']))) throw new DomainException('The department cycle owner must start the Annual cycle before self-ratings can be submitted.');
    $cycleId = (int)$cycle['cycle_id'];
    if (!$isManagedCycle) {
        $roster = annualQuery($conn, "SELECT DISTINCT e.employee_id,e.job_title_id,e.job_title,j.reports_to
            FROM employees e JOIN job_titles j ON j.job_title_id=e.job_title_id
            WHERE e.department_id=? AND e.is_active=1 AND e.deleted_at IS NULL AND e.employment_status='Regular'
              AND EXISTS (SELECT 1 FROM evaluation_templates t JOIN evaluation_template_positions tp ON tp.template_id=t.template_id
                 WHERE tp.job_title_id=e.job_title_id AND t.target_department_id=e.department_id
                   AND t.evaluation_type='Annual' AND t.status='Active' AND t.deleted_at IS NULL)",[(int)$ev['department_id']]);
        foreach ($roster as $r) {
            annualQuery($conn,"INSERT INTO annual_evaluation_members (cycle_id,employee_id,position_id,reviewer_position_id,position_name)
                VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE member_id=member_id",
                [$cycleId,(int)$r['employee_id'],(int)$r['job_title_id'],$r['reports_to'],$r['job_title']]);
        }
    }
    // A newly regularized employee may join an existing cycle without changing
    // the original roster or its submitted evaluations.
    $member = annualQuery($conn, 'SELECT * FROM annual_evaluation_members WHERE cycle_id=? AND employee_id=?',
        [$cycleId,(int)$ev['employee_id']])[0] ?? null;
    if (!$member) {
        if (annualIsApDepartment(['department_name'=>$departmentName])) {
            $personDate=annualQuery($conn,'SELECT COALESCE(regularization_effective_date,DATE(probationary_eligible_at),hire_date) AS effective_date FROM employees WHERE employee_id=?',[(int)$ev['employee_id']])[0]['effective_date'] ?? null;
            if (!$personDate || $personDate<$cycle['period_start'] || $personDate>$cycle['period_end']) {
                throw new DomainException('Your effective regularization or hire date is outside this Annual cycle period.');
            }
        }
        $roster = annualQuery($conn, "SELECT e.employee_id,e.job_title_id,e.job_title,j.reports_to
            FROM employees e JOIN job_titles j ON j.job_title_id=e.job_title_id
            WHERE e.employee_id=? AND e.department_id=? AND e.is_active=1 AND e.deleted_at IS NULL
              AND e.employment_status='Regular'", [(int)$ev['employee_id'],(int)$ev['department_id']]);
        if (!$roster) throw new DomainException('This employee is not eligible to join the department Annual cycle.');
        $r=$roster[0];
        annualQuery($conn, "INSERT INTO annual_evaluation_members (cycle_id,employee_id,position_id,reviewer_position_id,position_name)
            VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE member_id=member_id", [$cycleId,(int)$r['employee_id'],(int)$r['job_title_id'], $r['reports_to'], $r['job_title']]);
    }
    $member = annualQuery($conn, 'SELECT * FROM annual_evaluation_members WHERE cycle_id=? AND employee_id=?', [$cycleId,(int)$ev['employee_id']])[0] ?? null;
    if (!$member) throw new DomainException('No eligible department Annual template is assigned to this position.');
    if ($member['evaluation_id'] && (int)$member['evaluation_id'] !== $evaluationId) throw new DomainException('An Annual evaluation already exists for this department period.');
    if ((int)$member['position_id'] !== (int)$ev['job_title_id']) throw new DomainException('Transfer review required before submitting this evaluation.');
    if ($member['self_values_json']) throw new DomainException('This Annual self-rating has already been submitted.');
    if (!empty($member['revision_pending'])) {
        $previous=annualPeers($conn,annualMember($conn,$evaluationId));
        logAudit($conn,(int)($_SESSION['user_id'] ?? $ev['submitted_by'] ?? 0),'UPDATE','Annual Evaluation',$evaluationId,'Resubmission reset earlier 360 ratings: '.json_encode($previous));
        annualQuery($conn,'DELETE FROM annual_peer_submissions WHERE cycle_id=? AND target_employee_id=?',[$cycleId,(int)$ev['employee_id']]);
        foreach (annualQuery($conn,'SELECT u.user_id FROM users u JOIN annual_evaluation_members m ON m.employee_id=u.employee_id WHERE m.cycle_id=? AND m.employee_id<>?',[$cycleId,(int)$ev['employee_id']]) as $user) {
            $raterActor=annualActor($conn,(int)$user['user_id']);
            if ($raterActor && annualCanRate($raterActor)) createNotification($conn,(int)$user['user_id'],'Annual evaluation resubmitted: rate again','Earlier 360 ratings for this employee were cleared. Submit a new rating.',annualUrl($raterActor,'?view=rate&evaluation_id='.$evaluationId),'evaluations',true);
        }
    }
    $isLateParticipant=!empty($cycle['first_completed_at']) || !empty($member['is_late_participant']);
    annualQuery($conn, 'UPDATE annual_evaluation_members SET evaluation_id=?,self_values_json=?,is_late_participant=? WHERE member_id=?', [$evaluationId,$values,$isLateParticipant ? 1 : 0,(int)$member['member_id']]);
    annualCandidate($conn,annualMember($conn,$evaluationId));
    $submittedMember=annualMember($conn,$evaluationId);
    annualNotifyHeads($conn,$submittedMember,'Annual self-rating ready for KRA review',
        $submittedMember['first_name'].' '.$submittedMember['last_name'].' submitted an Annual self-rating. Open Rate & Review to review KRA, behavior, and the developmental plan, then sign and approve. The overall score updates as remaining peer ratings arrive.');
    annualNotifyEmployee($conn,(int)$ev['employee_id'],'Annual self-rating submitted','Your department 360 rating tasks are available.');
    annualNotifyCycleRaters($conn,$cycleId,$evaluationId,(int)$ev['employee_id'],$isLateParticipant);
    annualUpdateCycleCompletion($conn,$cycleId);
}

function annualReturnEvaluation($conn, array $actor, int $evaluationId, string $reason): void
{
    $reason=trim($reason);
    if ($reason==='') throw new DomainException('Enter a reason for returning the evaluation.');
    $conn->begin_transaction();
    try {
        $member=annualMember($conn,$evaluationId);
        if (!$member || !annualCanFinalize($conn,$actor,$member)) throw new DomainException('Only the immediate head can return this evaluation.');
        $returnCycle=annualQuery($conn,'SELECT cycle_id,completed_at FROM annual_evaluation_cycles WHERE cycle_id=? FOR UPDATE',[(int)$member['cycle_id']])[0] ?? null;
        if (!$returnCycle || !empty($returnCycle['completed_at'])) throw new DomainException('Archived annual evaluations are view only and cannot be returned.');
        $member=annualMember($conn,$evaluationId);
        if (!$member['self_values_json']) throw new DomainException('This evaluation is already awaiting resubmission.');
        annualQuery($conn,"UPDATE evaluations SET status='Returned',supervisor_comments=? WHERE evaluation_id=?",[$reason,$evaluationId]);
        annualQuery($conn,'UPDATE annual_evaluation_members SET self_values_json=NULL,revision_pending=1 WHERE member_id=?',[(int)$member['member_id']]);
        annualQuery($conn,'UPDATE annual_evaluation_cycles SET completed_at=NULL WHERE cycle_id=?',[(int)$member['cycle_id']]);
        logAudit($conn,(int)$actor['user_id'],'UPDATE','Annual Evaluation',$evaluationId,'Returned for self-rating revision: '.$reason);
        foreach (annualQuery($conn,'SELECT user_id FROM users WHERE employee_id=? AND is_active=1',[(int)$member['employee_id']]) as $user) {
            createNotification($conn,(int)$user['user_id'],'Annual evaluation returned',$reason.' Revise and resubmit your self-rating. Earlier 360 ratings will be cleared on resubmission.',BASE_URL.'/employee/self-rating.php','evaluations',true);
        }
        $conn->commit();
    } catch (Throwable $error) { $conn->rollback(); throw $error; }
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
            annualUrl($actor,'?view=rate&evaluation_id='.$member['evaluation_id']), 'evaluations', true);
    }
}

function annualSubmitPeers($conn, array $actor, int $evaluationId, array $scores, bool $manageTransaction = true, string $signature = ''): void
{
    ensureAuditTrailSchema($conn);
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
        if (!$member['self_values_json']) throw new DomainException('Wait for the employee to resubmit their self-rating.');
        $rater=annualQuery($conn,'SELECT evaluation_id,is_late_participant FROM annual_evaluation_members WHERE cycle_id=? AND employee_id=?',[(int)$member['cycle_id'],(int)$actor['employee_id']])[0] ?? null;
        if (!$rater && !annualCanFinalize($conn,$actor,$member)) throw new DomainException('Only assigned department participants and the immediate head may rate this employee.');
        if ((!$rater || !$rater['evaluation_id']) && !annualCanRateBeforeSelf($conn,$actor,$member)) throw new DomainException('Submit your Annual self-rating for this department period first.');
        if (!empty($rater['evaluation_id'])) {
            $raterMember=annualMember($conn,(int)$rater['evaluation_id']);
            if (!$raterMember) throw new DomainException('Your Annual evaluation record is unavailable. Contact HR.');
            annualAssertSnapshotActive($raterMember);
        }
        if ((int)$actor['employee_id']===(int)$member['employee_id'] || (int)$actor['department_id']!==(int)$member['department_id']) {
            throw new DomainException('Only coworkers in this department cycle can rate this employee.');
        }
        $exists=annualQuery($conn,'SELECT submission_id FROM annual_peer_submissions WHERE cycle_id=? AND rater_employee_id=? AND target_employee_id=?',
            [(int)$member['cycle_id'],(int)$actor['employee_id'],(int)$member['employee_id']]);
        if ($exists) throw new DomainException('Your submitted rating is locked and cannot be edited.');
        // Resolve omitted peer ratings from the employee submission, never from browser defaults.
        $scores=annualValidateScores(annualResolveReviewOverrides(
            annualValidateScores(json_decode($member['self_values_json'] ?? '[]',true) ?: []),$scores));
        $late=!empty($member['is_late_participant']) || !empty($rater['is_late_participant']) ? 1 : 0;
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
        $revisionNotice=false;
        if ((int)$member['current_version']>0 && $member['evaluation_status']==='Approved') annualRefreshApprovedResult($conn,$member,(int)$actor['user_id']);
        logAudit($conn,(int)$actor['user_id'],'CREATE','Annual 360',$evaluationId,'Submitted locked peer rating. Approved Annual scores update automatically.');
        $received=(int)annualQuery($conn,'SELECT COUNT(*) AS n FROM annual_peer_submissions s JOIN employees r ON r.employee_id=s.rater_employee_id JOIN annual_evaluation_cycles c ON c.cycle_id=s.cycle_id WHERE s.cycle_id=? AND s.target_employee_id=? AND r.department_id=c.department_id',[(int)$member['cycle_id'],(int)$member['employee_id']])[0]['n'];
        $expected=annualExpectedPeers($conn,(int)$member['cycle_id'],(int)$member['employee_id']);
        if ($received===$expected && $member['evaluation_status']!=='Approved') annualNotifyHeads($conn,$member,'Annual 360 summary ready','Review the updated 360 summary for '.$member['first_name'].' '.$member['last_name'].'.');
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

/** Resolve optional head overrides against original employee submissions. */
function annualResolveReviewOverrides(array $defaults, array $overrides): array
{
    if (array_diff_key($overrides,$defaults)) throw new DomainException('An unknown rating item was submitted. Reload the evaluation.');
    foreach ($defaults as $key => &$value) {
        $override=$overrides[$key] ?? '';
        if ($override !== '') {
            if (!is_scalar($override) || !is_numeric($override) || !is_finite((float)$override)
                || (float)$override<1 || (float)$override>4 || abs(round((float)$override,2)-(float)$override)>.00001) {
                throw new DomainException('Rating overrides must be from 1.00 to 4.00 with at most two decimal places.');
            }
            $value=(float)$override;
        }
    }
    unset($value);
    return $defaults;
}

/** Save the head's KRA review and their own peer rating together, or neither. */
function annualReviewAndRate($conn, array $actor, int $evaluationId, array $ratings, string $token, array $scores, string $signature, ?array $plans = null, string $headComments = ''): void
{
    if ($signature==='') throw new DomainException('Sign in the rater signature box before submitting.');
    $headComments=trim($headComments);
    if (mb_strlen($headComments)>4000) throw new DomainException('Immediate head comments must not exceed 4,000 characters.');
    ensureAuditTrailSchema($conn);
    $conn->begin_transaction();
    try {
        $member=annualMember($conn,$evaluationId);
        if (!$member || !annualCanFinalize($conn,$actor,$member)) throw new DomainException('Only this employee\'s immediate head can review these ratings.');
        annualQuery($conn,'SELECT cycle_id FROM annual_evaluation_cycles WHERE cycle_id=? FOR UPDATE',[(int)$member['cycle_id']]);
        $member=annualMember($conn,$evaluationId);
        $kraDefaults=[];
        foreach (annualKraRows($conn,$evaluationId) as $row) $kraDefaults[(int)$row['score_id']]=$row['self_rating'];
        $ratings=annualResolveReviewOverrides($kraDefaults,$ratings);
        $departmentHead=(int)$actor['department_id']===(int)$member['department_id'];
        if (!$departmentHead && $scores) throw new DomainException('360 ratings are restricted to the employee department.');
        if ($departmentHead) $scores=annualResolveReviewOverrides(json_decode($member['self_values_json'],true),$scores);
        annualApproveKra($conn,$actor,$evaluationId,$ratings,'',$token,false,false);
        if ($departmentHead) annualSubmitPeers($conn,$actor,$evaluationId,$scores,false,$signature);
        else {
            $bytes=base64_decode(substr($signature,strpos($signature,',')+1),true);
            $info=$bytes===false ? false : @getimagesizefromstring($bytes);
            if (!str_starts_with($signature,'data:image/png;base64,') || strlen($signature)>100000 || !$info || $info[2]!==IMAGETYPE_PNG) throw new DomainException('Please provide a valid signature.');
            annualQuery($conn,'UPDATE annual_kra_approvals SET rater_signature_data=?,rater_signed_at=NOW() WHERE member_id=? AND approved_by=? ORDER BY approval_id DESC LIMIT 1',[$signature,(int)$member['member_id'],(int)$actor['user_id']]);
            $plans=null;
        }
        annualQuery($conn,'UPDATE evaluations SET supervisor_comments=? WHERE evaluation_id=?',[$headComments,$evaluationId]);
        if ($plans!==null) {
            $plans=annualValidateDevelopmentPlans($plans);
            annualQuery($conn,'DELETE FROM evaluation_dev_plans WHERE evaluation_id=?',[$evaluationId]);
            foreach ($plans as $index=>$plan) annualQuery($conn,'INSERT INTO evaluation_dev_plans (evaluation_id,improvement_area,support_needed,time_frame,sort_order) VALUES (?,?,?,?,?)',
                [$evaluationId,$plan['improvement_area'],$plan['support_needed'],$plan['time_frame'],$index]);
        }
        annualRefreshApprovedResult($conn,annualMember($conn,$evaluationId),(int)$actor['user_id'],true);
        $conn->commit();
    } catch (Throwable $e) { $conn->rollback(); throw $e; }
}

function annualValidateDevelopmentPlans(array $plans): array
{
    if (count($plans)>20) throw new DomainException('Use no more than 20 developmental plan items.');
    $validated=[];
    foreach ($plans as $plan) {
        if (!is_array($plan)) throw new DomainException('Invalid developmental plan item.');
        $row=[];
        foreach (['improvement_area'=>2000,'support_needed'=>4000,'time_frame'=>100] as $field=>$limit) {
            $value=$plan[$field] ?? '';
            if (!is_string($value) || mb_strlen($value)>$limit) throw new DomainException('Check the developmental plan field lengths.');
            $row[$field]=trim($value);
        }
        if (!array_filter($row)) continue;
        if (in_array('',$row,true)) throw new DomainException('Complete the development area, action/support, and time frame for each plan item, or remove the row.');
        $validated[]=$row;
    }
    return $validated;
}

/** Caller holds the cycle lock. Approval stays locked; peer arrivals revise the score. */
function annualRefreshApprovedResult($conn, array $member, int $actorUserId, bool $approve = false): void
{
    $candidate=annualCandidate($conn,$member);
    $expected=annualExpectedPeers($conn,(int)$member['cycle_id'],(int)$member['employee_id']);
    $complete=$candidate['peer_count']>=$expected;
    $approval=annualQuery($conn,'SELECT approved_by FROM evaluations WHERE evaluation_id=?',[(int)$member['evaluation_id']])[0] ?? [];
    $previous=annualQuery($conn,'SELECT snapshot_json FROM annual_evaluation_versions WHERE member_id=? ORDER BY version_number DESC LIMIT 1',[(int)$member['member_id']])[0] ?? [];
    $previousSnapshot=json_decode($previous['snapshot_json'] ?? '{}',true) ?: [];
    $scoreChanged=$member['official_score']===null || abs((float)$member['official_score']-$candidate['total'])>.00001;
    $completeChanged=($previousSnapshot['score_complete'] ?? false)!==$complete;
    $version=(int)$member['current_version']+1;
    $snapshot=$candidate+['score_complete'=>$complete,'expected_peers'=>$expected,
        'previous_score'=>$member['official_score'],'kra_rows'=>annualKraRows($conn,(int)$member['evaluation_id']),
        'self_values'=>json_decode($member['self_values_json'],true),'peers'=>annualPeers($conn,$member),
        'head_comments'=>$member['head_comments'] ?? '',
        'development_plans'=>annualQuery($conn,'SELECT improvement_area,support_needed,time_frame FROM evaluation_dev_plans WHERE evaluation_id=? ORDER BY sort_order,plan_id',[(int)$member['evaluation_id']])];
    annualQuery($conn,'INSERT INTO annual_evaluation_versions (member_id,version_number,finalized_by,remarks,snapshot_json) VALUES (?,?,?,?,?)',
        [(int)$member['member_id'],$version,$approve ? $actorUserId : (int)$approval['approved_by'],
            $approve ? 'Approved by immediate head.' : 'Score updated automatically after a department peer submission.',json_encode($snapshot)]);
    annualQuery($conn,'UPDATE annual_evaluation_members SET current_version=?,revision_pending=0,finalized_at=COALESCE(finalized_at,NOW()) WHERE member_id=?',[$version,(int)$member['member_id']]);
    annualQuery($conn,"UPDATE evaluations SET kra_subtotal=?,behavior_average=?,total_score=?,performance_level=?,status='Approved',approved_by=COALESCE(approved_by,?),approved_date=COALESCE(approved_date,NOW()) WHERE evaluation_id=?",
        [$candidate['kra'],$candidate['core'],$candidate['total'],getPerformanceLevel($candidate['total']),$actorUserId,(int)$member['evaluation_id']]);
    annualQuery($conn,'UPDATE annual_evaluation_cycles SET first_finalized_at=COALESCE(first_finalized_at,NOW()) WHERE cycle_id=?',[(int)$member['cycle_id']]);
    $notice=$complete ? 'All required department ratings have been received. Your score is complete.' : 'Your immediate head has approved your evaluation. Your overall score may change as remaining department members submit their 360 ratings.';
    if ($approve || $scoreChanged || $completeChanged) annualNotifyEmployee($conn,(int)$member['employee_id'],
        $approve ? 'Annual evaluation approved' : ($complete ? 'Annual score complete' : 'Annual score updated'),
        ($approve ? 'Current overall score: '.number_format($candidate['total'],2).'. ' : 'Overall score: '.number_format((float)$member['official_score'],2).' → '.number_format($candidate['total'],2).'. ').$notice,(int)$member['evaluation_id']);
    if ($approve) {
        $head=annualActor($conn,$actorUserId);
        annualQuery($conn,'UPDATE notifications SET needs_action=0 WHERE user_id=? AND link IN (?,?)',[$actorUserId,annualUrl($head,'?view=review&evaluation_id='.(int)$member['evaluation_id']),annualUrl($head,'?view=rate&evaluation_id='.(int)$member['evaluation_id'])]);
    }
    logAudit($conn,$actorUserId,$approve ? 'APPROVE' : 'UPDATE','Annual Evaluation',(int)$member['evaluation_id'],'Version '.$version.'. Score '.$candidate['total'].'. '.($complete ? 'Score complete.' : 'Score provisional.'));
    annualUpdateCycleCompletion($conn,(int)$member['cycle_id']);
}

/** Caller holds the cycle lock; archive only fully approved and fully rated cycles. */
function annualUpdateCycleCompletion($conn, int $cycleId, int $approvalUserId = 0): bool
{
    $cycle=annualQuery($conn,'SELECT c.*,d.department_name FROM annual_evaluation_cycles c JOIN departments d ON d.department_id=c.department_id WHERE c.cycle_id=?',[$cycleId])[0] ?? null;
    if (!$cycle) {
        if ($approvalUserId) throw new DomainException('This annual cycle is unavailable.');
        return false;
    }
    annualSyncApCycleRoster($conn,$cycle);
    $roster=annualQuery($conn,'SELECT m.employee_id,m.evaluation_id,m.current_version,ev.status FROM annual_evaluation_members m JOIN employees e ON e.employee_id=m.employee_id LEFT JOIN evaluations ev ON ev.evaluation_id=m.evaluation_id WHERE m.cycle_id=? AND '.annualRosterEligibilitySql(),[$cycleId]);
    $complete=(bool)$roster;
    foreach ($roster as $row) {
        if (!$row['evaluation_id'] || !(int)$row['current_version'] || $row['status']!=='Approved') { $complete=false; break; }
        $received=(int)annualQuery($conn,'SELECT COUNT(*) AS n FROM annual_peer_submissions s JOIN employees r ON r.employee_id=s.rater_employee_id JOIN annual_evaluation_cycles c ON c.cycle_id=s.cycle_id WHERE s.cycle_id=? AND s.target_employee_id=? AND r.department_id=c.department_id',[$cycleId,(int)$row['employee_id']])[0]['n'];
        if ($received<annualExpectedPeers($conn,$cycleId,(int)$row['employee_id'])) { $complete=false; break; }
    }
    if ($approvalUserId) {
        $approver=annualActor($conn,$approvalUserId);
        if (!$approver || !annualCanOwnCycle($conn,$approver,(int)$cycle['department_id'])) {
            throw new DomainException('Only the assigned department cycle owner can approve this Annual cycle.');
        }
        if (!$complete) throw new DomainException('All self-ratings, individual approvals, and department 360 ratings must be complete first.');
        if (!empty($cycle['completed_at'])) throw new DomainException('This cycle is already approved.');
        annualQuery($conn,'UPDATE annual_evaluation_cycles SET cycle_approved_by=? WHERE cycle_id=?',[$approvalUserId,$cycleId]);
        logAudit($conn,$approvalUserId,'APPROVE','Annual Cycle',$cycleId,'Approved completed department annual cycle for history.');
    } elseif ($complete && empty($cycle['completed_at'])) {
        if (empty($cycle['ready_for_approval_at'])) {
            annualQuery($conn,'UPDATE annual_evaluation_cycles SET ready_for_approval_at=NOW() WHERE cycle_id=?',[$cycleId]);
            $ownerUsers=annualIsHrDepartment($cycle)
                ? annualQuery($conn,"SELECT u.user_id FROM users u JOIN employees e ON e.employee_id=u.employee_id
                    WHERE u.role='HR Manager' AND e.department_id=? AND u.is_active=1 AND u.deleted_at IS NULL",[(int)$cycle['department_id']])
                : annualQuery($conn,"SELECT u.user_id FROM users u JOIN employees e ON e.employee_id=u.employee_id
                    JOIN annual_cycle_department_owners o ON o.department_id=e.department_id AND o.owner_position_id=e.job_title_id
                    WHERE e.department_id=? AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL",[(int)$cycle['department_id']]);
            foreach ($ownerUsers as $manager) {
                $managerActor=annualActor($conn,(int)$manager['user_id']);
                if ($managerActor) createNotification($conn,(int)$manager['user_id'],'Annual cycle ready for approval',$cycle['department_name'].' has completed its annual requirements. Review and approve the cycle to move it to history.',annualUrl($managerActor,'?view=peers&cycle_id='.$cycleId),'evaluations',true);
            }
        }
        return false;
    }
    if (!$complete) annualQuery($conn,'UPDATE annual_evaluation_cycles SET ready_for_approval_at=NULL,cycle_approved_by=NULL WHERE cycle_id=?',[$cycleId]);
    if ($complete===!empty($cycle['completed_at'])) return $complete;
    annualQuery($conn,'UPDATE annual_evaluation_cycles SET '.($complete ? 'completed_at=NOW(),first_completed_at=COALESCE(first_completed_at,NOW())' : 'completed_at=NULL').' WHERE cycle_id=?',[$cycleId]);
    if ($complete && $approvalUserId && !annualIsHrDepartment($cycle)) {
        $hrRecipients=annualQuery($conn,"SELECT u.user_id,u.role,d.department_name FROM users u
            JOIN employees e ON e.employee_id=u.employee_id
            JOIN departments d ON d.department_id=e.department_id
            WHERE u.role IN ('HR Manager','HR Supervisor','HR Staff')
              AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0
              AND e.is_active=1 AND e.deleted_at IS NULL");
        foreach ($hrRecipients as $hrRecipient) {
            if (!annualIsHrDepartment($hrRecipient)) continue;
            $historyPortal=['HR Manager'=>'manager','HR Supervisor'=>'supervisor','HR Staff'=>'staff'][$hrRecipient['role']];
            createNotification($conn,(int)$hrRecipient['user_id'],
                $cycle['department_name'].' Annual cycle approved',
                $cycle['department_name'].' approved its Annual evaluation cycle for '.$cycle['period_start'].' to '.$cycle['period_end'].'. Completed results are available in Evaluation History. This is for HR visibility; no further HR approval is required.',
                BASE_URL.'/'.$historyPortal.'/evaluation-history.php','evaluations',false);
        }
    }
    $recipients=annualQuery($conn,'SELECT DISTINCT u.user_id FROM users u JOIN employees e ON e.employee_id=u.employee_id WHERE u.is_active=1 AND u.deleted_at IS NULL AND e.is_active=1 AND e.deleted_at IS NULL AND (EXISTS(SELECT 1 FROM annual_evaluation_members m WHERE m.cycle_id=? AND m.employee_id=e.employee_id) OR EXISTS(SELECT 1 FROM annual_evaluation_members m WHERE m.cycle_id=? AND m.reviewer_position_id=e.job_title_id))',[$cycleId,$cycleId]);
    foreach ($recipients as $recipient) {
        $actor=annualActor($conn,(int)$recipient['user_id']);
        if (!$actor) continue;
        createNotification($conn,(int)$recipient['user_id'],$complete ? 'Department Annual evaluation complete' : 'Department Annual evaluation reopened',
            $cycle['department_name'].' · '.$cycle['period_start'].' to '.$cycle['period_end'].'. '.($complete ? 'All employees have completed their Annual evaluations and all required 360 ratings are submitted. The completed cycle is now available in Annual Evaluation History.' : 'An additional participant requires ratings. The cycle is active again; previous approved ratings remain locked.'),
            annualUrl($actor,'?view='.($complete ? 'history' : 'peers').'&cycle_id='.$cycleId),'evaluations',false);
    }
    return $complete;
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
    if (annualApCycleReadyToStart($conn,$actor)) $count++;
    if (annualCanOwnCycle($conn,$actor,(int)$actor['department_id'])) {
        $ready=annualQuery($conn,'SELECT COUNT(*) AS n FROM annual_evaluation_cycles
            WHERE department_id=? AND ready_for_approval_at IS NOT NULL AND completed_at IS NULL',
            [(int)$actor['department_id']])[0];
        $count+=(int)$ready['n'];
    }
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
