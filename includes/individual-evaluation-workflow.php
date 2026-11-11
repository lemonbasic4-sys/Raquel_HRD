<?php
/** Submission-led evaluations coexist with legacy department cycles. */
function ensureIndividualEvaluationSchema($conn, string $kind): void
{
    static $ready=[];
    $key=spl_object_id($conn).$kind;
    if(isset($ready[$key])) return;
    if(!in_array($kind,['annual','quarterly'],true)) throw new InvalidArgumentException('Invalid workflow');
    foreach(['individual_workflow'=>'TINYINT NOT NULL DEFAULT 0','assessment_from'=>'DATE NULL','assessment_to'=>'DATE NULL'] as $column=>$definition) {
        if(!$conn->query("SHOW COLUMNS FROM evaluation_templates LIKE '$column'")->num_rows) $conn->query("ALTER TABLE evaluation_templates ADD COLUMN $column $definition");
    }
    foreach(['head_approved_by'=>'INT NULL','head_approved_at'=>'DATETIME NULL'] as $column=>$definition) {
        if(!$conn->query("SHOW COLUMNS FROM {$kind}_evaluation_members LIKE '$column'")->num_rows) $conn->query("ALTER TABLE {$kind}_evaluation_members ADD COLUMN $column $definition");
    }
    $table=$kind.'_evaluation_cycles';
    if(!$conn->query("SHOW COLUMNS FROM $table LIKE 'individual_evaluation_id'")->num_rows) $conn->query("ALTER TABLE $table ADD COLUMN individual_evaluation_id INT NOT NULL DEFAULT 0");
    $index=$kind.'_department_active_period';
    $columns=[];
    foreach($conn->query("SHOW INDEX FROM $table WHERE Key_name='$index'") as $row) $columns[]=$row['Column_name'];
    if(!in_array('individual_evaluation_id',$columns,true)) {
        if($columns) $conn->query("ALTER TABLE $table DROP INDEX $index");
        $conn->query("ALTER TABLE $table ADD UNIQUE KEY $index(department_id,period_start,period_end,active_period_key,individual_evaluation_id)");
    }
    $conn->query("CREATE TABLE IF NOT EXISTS {$kind}_required_raters (
        cycle_id INT NOT NULL, target_employee_id INT NOT NULL, rater_employee_id INT NOT NULL,
        PRIMARY KEY(cycle_id,target_employee_id,rater_employee_id),
        FOREIGN KEY(cycle_id) REFERENCES {$kind}_evaluation_cycles(cycle_id),
        FOREIGN KEY(target_employee_id) REFERENCES employees(employee_id),
        FOREIGN KEY(rater_employee_id) REFERENCES employees(employee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ready[$key]=true;
}

function individualAssessmentDates(array $employee,array $template): array
{
    $from=$template['assessment_from']??''; $to=$template['assessment_to']??'';
    foreach([$from,$to] as $value) {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$value);
        if(!$date || $date->format('Y-m-d')!==$value) throw new DomainException('The immediate head must publish the form with valid Period From and Period To dates.');
    }
    if($from>$to) throw new DomainException('Period From must be on or before Period To.');
    if(($employee['employment_status']??'')!=='Regular') throw new DomainException('Only Regular employees can use this form.');
    if(($template['evaluation_type']??'')==='Annual') {
        $regular=$employee['regularization_effective_date']??'';
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$regular);
        if(!$date || $date->format('Y-m-d')!==$regular) throw new DomainException('Ask HR to record your actual regularization date before opening the Annual form.');
        $from=max(substr($from,0,4).'-01-01',$regular);
        $to=substr($to,0,4).'-12-31';
        if($from>$to) throw new DomainException('Your regularization date falls after this Annual period.');
    }
    return [$from,$to];
}

function individualTemplateDates(array $input,string $type): array
{
    if(!in_array($type,['Annual','Quarterly'],true)) return [null,null];
    if($type==='Annual') {
        $year=filter_var($input['assessment_year']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1900,'max_range'=>2200]]);
        if(!$year) throw new DomainException('Select a valid Annual evaluation year.');
        return ["$year-01-01","$year-12-31"];
    }
    $from=(string)($input['assessment_from']??'');$to=(string)($input['assessment_to']??'');
    foreach([$from,$to] as $value) {
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if(!$d || $d->format('Y-m-d')!==$value) throw new DomainException('Enter both Quarterly Period From and Period To.');
    }
    if($from>$to) throw new DomainException('Quarterly Period From must be on or before Period To.');
    return [$from,$to];
}

function individualIsCycle($conn,string $kind,int $cycleId): bool
{
    return (bool)annualQuery($conn,"SELECT 1 FROM {$kind}_evaluation_cycles WHERE cycle_id=? AND individual_evaluation_id>0",[$cycleId]);
}

function individualRegisterSubmission($conn,string $kind,array $ev,string $values): void
{
    $existing=annualQuery($conn,"SELECT member_id,cycle_id FROM {$kind}_evaluation_members WHERE evaluation_id=?",[(int)$ev['evaluation_id']])[0]??null;
    if($existing) {
        $cycleId=(int)$existing['cycle_id'];
        $cycle=annualQuery($conn,"SELECT * FROM {$kind}_evaluation_cycles WHERE cycle_id=? FOR UPDATE",[$cycleId])[0];
        if(!$cycle['individual_evaluation_id']) throw new DomainException('This evaluation belongs to a legacy department cycle.');
        if($cycle['completed_at'] || $cycle['cancelled_at']) throw new DomainException('A completed or cancelled evaluation cannot be resubmitted.');
        annualQuery($conn,"DELETE FROM {$kind}_peer_submissions WHERE cycle_id=?",[$cycleId]);
        annualQuery($conn,"DELETE FROM {$kind}_kra_approvals WHERE member_id=?",[(int)$existing['member_id']]);
        annualQuery($conn,"UPDATE {$kind}_evaluation_members SET self_values_json=?,revision_pending=0,head_approved_by=NULL,head_approved_at=NULL WHERE member_id=?",[$values,(int)$existing['member_id']]);
        ($kind.'NotifyCycleRaters')($conn,$cycleId,(int)$ev['evaluation_id'],(int)$ev['employee_id'],false);
        return;
    }
    annualQuery($conn,'SELECT template_id FROM evaluation_templates WHERE template_id=? FOR UPDATE',[(int)$ev['template_id']]);
    $duplicate=annualQuery($conn,"SELECT evaluation_id FROM evaluations WHERE template_id=? AND employee_id=? AND evaluation_id<>? AND deleted_at IS NULL AND status NOT IN ('Draft','Pending Self-Rating','Returned','Rejected') LIMIT 1 FOR UPDATE",[(int)$ev['template_id'],(int)$ev['employee_id'],(int)$ev['evaluation_id']]);
    if($duplicate) throw new DomainException('This employee already submitted an evaluation for this form.');
    $reviewer=(int)($ev['reports_to']??0);
    if(!$reviewer) throw new DomainException('This position has no immediate head configured. Ask HR to update its reporting line.');
    annualQuery($conn,"INSERT INTO {$kind}_evaluation_cycles(department_id,period_start,period_end,started_at,individual_evaluation_id) VALUES(?,?,?,NOW(),?)",[(int)$ev['department_id'],$ev['evaluation_period_start'],$ev['evaluation_period_end'],(int)$ev['evaluation_id']]);
    $cycleId=(int)$conn->insert_id;
    annualQuery($conn,"INSERT INTO {$kind}_evaluation_members(cycle_id,employee_id,evaluation_id,position_id,reviewer_position_id,position_name,self_values_json) VALUES(?,?,?,?,?,?,?)",[$cycleId,(int)$ev['employee_id'],(int)$ev['evaluation_id'],(int)$ev['job_title_id'],$reviewer,$ev['job_title'],$values]);
    annualQuery($conn,"INSERT INTO {$kind}_required_raters(cycle_id,target_employee_id,rater_employee_id) SELECT ?,?,e.employee_id FROM employees e WHERE e.department_id=? AND e.employee_id<>? AND e.is_active=1 AND e.deleted_at IS NULL AND e.employment_status='Regular'",[$cycleId,(int)$ev['employee_id'],(int)$ev['department_id'],(int)$ev['employee_id']]);
    $member=($kind.'Member')($conn,(int)$ev['evaluation_id']);
    foreach(annualQuery($conn,"SELECT u.user_id FROM {$kind}_required_raters rr JOIN users u ON u.employee_id=rr.rater_employee_id WHERE rr.cycle_id=? AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0 AND u.role IN ('Employee','HR Manager','HR Supervisor','HR Staff')",[$cycleId]) as $user) {
        $actor=($kind.'Actor')($conn,(int)$user['user_id']);
        if(!$actor || !($kind.'CanRate')($actor)) continue;
        createNotification($conn,(int)$user['user_id'],ucfirst($kind).' 360 rating available','A coworker submitted an individual evaluation. Rate their eight Core Behaviors & Values.',($kind.'Url')($actor,'?view=rate&evaluation_id='.(int)$ev['evaluation_id']),'evaluations',true);
    }
    ($kind.'NotifyHeads')($conn,$member,ucfirst($kind).' self-rating submitted','KRA review is available. Final approval requires all assigned department peer ratings.');
    logAudit($conn,(int)$ev['submitted_by'],'CREATE',ucfirst($kind).' Individual Evaluation',(int)$ev['evaluation_id'],'Submitted with a snapshot of active Regular department peers.');
}

function individualRequiredRater($conn,string $kind,array $member,int $employeeId): bool
{
    return (bool)annualQuery($conn,"SELECT 1 FROM {$kind}_required_raters WHERE cycle_id=? AND target_employee_id=? AND rater_employee_id=?",[(int)$member['cycle_id'],(int)$member['employee_id'],$employeeId]);
}

function individualApprovalReady($conn,string $kind,array $member,int $headEmployeeId=0): bool
{
    $missing=annualQuery($conn,"SELECT COUNT(*) n FROM {$kind}_required_raters rr WHERE rr.cycle_id=? AND rr.target_employee_id=? AND rr.rater_employee_id<>? AND NOT EXISTS(SELECT 1 FROM {$kind}_peer_submissions ps WHERE ps.cycle_id=rr.cycle_id AND ps.target_employee_id=rr.target_employee_id AND ps.rater_employee_id=rr.rater_employee_id)",[(int)$member['cycle_id'],(int)$member['employee_id'],$headEmployeeId])[0]['n'];
    return (int)$missing===0;
}

function individualNotifyTemplateAvailability($conn,int $templateId=0,int $employeeId=0): int
{
    $rows=annualQuery($conn,"SELECT DISTINCT e.*,t.template_id,t.template_name,t.evaluation_type,t.assessment_from,t.assessment_to,u.user_id FROM evaluation_templates t JOIN evaluation_template_positions tp ON tp.template_id=t.template_id JOIN employees e ON e.job_title_id=tp.job_title_id AND e.department_id=t.target_department_id JOIN users u ON u.employee_id=e.employee_id AND u.role='Employee' WHERE t.individual_workflow=1 AND t.status='Active' AND t.deleted_at IS NULL AND e.is_active=1 AND e.deleted_at IS NULL AND e.employment_status='Regular' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0 AND (?=0 OR t.template_id=?) AND (?=0 OR e.employee_id=?) AND NOT EXISTS(SELECT 1 FROM evaluations ev WHERE ev.template_id=t.template_id AND ev.employee_id=e.employee_id AND ev.deleted_at IS NULL AND ev.status NOT IN ('Draft','Pending Self-Rating','Returned','Rejected'))",[$templateId,$templateId,$employeeId,$employeeId]);
    $count=0;
    foreach($rows as $row) {
        try {[$from,$to]=individualAssessmentDates($row,$row);} catch(DomainException $e){continue;}
        $count++;
        $link=BASE_URL.'/employee/self-rating.php?template='.(int)$row['template_id'];
        $title=$row['evaluation_type'].' self-rating available';
        if(!annualQuery($conn,'SELECT 1 FROM notifications WHERE user_id=? AND title=? AND link=?',[(int)$row['user_id'],$title,$link])) createNotification($conn,(int)$row['user_id'],$title,$row['template_name'].' is available. Period: '.$from.' to '.$to.'.',$link,'evaluations',true);
    }
    return $count;
}

/** Reused by template lists/counts; never manufacture a missing regularization date. */
function individualTemplateEligibilitySql(string $alias,int $employeeId): string
{
    if(!preg_match('/^[a-z_]+$/i',$alias)) throw new InvalidArgumentException('Invalid template alias');
    return "($alias.individual_workflow=0 OR ($alias.assessment_from IS NOT NULL AND $alias.assessment_to IS NOT NULL AND $alias.assessment_from<=$alias.assessment_to AND EXISTS(SELECT 1 FROM employees ie WHERE ie.employee_id=$employeeId AND ie.is_active=1 AND ie.deleted_at IS NULL AND ie.employment_status='Regular' AND ie.department_id=$alias.target_department_id AND ($alias.evaluation_type<>'Annual' OR (ie.regularization_effective_date IS NOT NULL AND ie.regularization_effective_date<=$alias.assessment_to)))))";
}

/** Count a combined head-review/peer-rating task only once per evaluation. */
function individualPendingPeerCount($conn,string $kind,array $actor): int
{
    $rows=annualQuery($conn,"SELECT DISTINCT m.evaluation_id FROM {$kind}_required_raters rr JOIN {$kind}_evaluation_cycles c ON c.cycle_id=rr.cycle_id JOIN {$kind}_evaluation_members m ON m.cycle_id=rr.cycle_id AND m.employee_id=rr.target_employee_id WHERE rr.rater_employee_id=? AND c.completed_at IS NULL AND c.cancelled_at IS NULL AND c.department_id=? AND m.self_values_json IS NOT NULL AND NOT EXISTS(SELECT 1 FROM {$kind}_peer_submissions ps WHERE ps.cycle_id=rr.cycle_id AND ps.target_employee_id=rr.target_employee_id AND ps.rater_employee_id=rr.rater_employee_id)",[(int)$actor['employee_id'],(int)$actor['department_id']]);
    $count=0;
    foreach($rows as $row) {
        $member=($kind.'Member')($conn,(int)$row['evaluation_id']);
        // PendingReviewCount already includes this evaluation for its head.
        if($member && !($kind.'CanFinalize')($conn,$actor,$member)) $count++;
    }
    return $count;
}
