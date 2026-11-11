<?php
/** Ties retain displayed tab order; explicit links never use this default selector. */
function performanceEvaluationPriorityCategory(array $counts): string
{
    $selected=array_key_first($counts);
    foreach ($counts as $category=>$count) {
        if ($count>$counts[$selected]) $selected=$category;
    }
    return $selected;
}

function performanceEvaluationDefaultCategory($conn,int $userId,bool $includeProbationary): string
{
    ensureAnnualEvaluationSchema($conn);
    ensureQuarterlyEvaluationSchema($conn);
    $actor=annualActor($conn,$userId);
    $counts=$includeProbationary ? ['probationary'=>countPendingOrganizationPackagesForUser($conn,$userId,true)] : [];
    $counts['quarterly']=$actor ? quarterlyPendingCount($conn,$actor) : 0;
    $counts['regular']=$actor ? annualPendingCount($conn,$actor) : 0;
    if (max($counts)===0 && $actor) {
        foreach (['quarterly','annual'] as $kind) {
            $reviewerSql=hrEvaluationReviewerSql('m','c.department_id');
            $row=annualQuery($conn,"SELECT COUNT(DISTINCT m.evaluation_id) n FROM {$kind}_evaluation_cycles c
                JOIN {$kind}_evaluation_members m ON m.cycle_id=c.cycle_id
                WHERE c.completed_at IS NULL AND c.cancelled_at IS NULL AND m.evaluation_id IS NOT NULL
                  AND (m.employee_id=? OR ($reviewerSql)=? OR (c.individual_evaluation_id>0 AND c.department_id=?
                    AND EXISTS(SELECT 1 FROM {$kind}_required_raters rr WHERE rr.cycle_id=c.cycle_id AND rr.rater_employee_id=?)))",
                [(int)$actor['employee_id'],(int)$actor['job_title_id'],(int)$actor['department_id'],(int)$actor['employee_id']])[0];
            $counts[$kind==='annual' ? 'regular' : 'quarterly']=(int)$row['n'];
        }
    }
    return performanceEvaluationPriorityCategory($counts);
}
