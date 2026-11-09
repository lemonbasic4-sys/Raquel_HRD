<?php
/** Resolve temporary HR Supervisor I coverage without rewriting the organization tree. */
function hrSupervisorPositionSql(string $position, string $department): string
{
    $eligible = static function (string $p): string {
        return "EXISTS(SELECT 1 FROM employees succession_e JOIN users succession_u ON succession_u.employee_id=succession_e.employee_id
            WHERE succession_e.job_title_id=$p.job_title_id AND succession_e.department_id=$p.department_id
              AND succession_e.rank_category_id=4 AND succession_e.employment_status='Regular'
              AND succession_e.is_active=1 AND succession_e.deleted_at IS NULL
              AND succession_u.role='HR Supervisor' AND succession_u.is_active=1 AND succession_u.deleted_at IS NULL
              AND COALESCE(succession_u.account_hold,0)=0)";
    };
    $primaryEligible = $eligible('succession_primary');
    $successorEligible = $eligible('succession_next');
    return "COALESCE((SELECT CASE WHEN $primaryEligible THEN succession_primary.job_title_id
            ELSE (SELECT succession_next.job_title_id FROM job_titles succession_next
                WHERE succession_next.department_id=succession_primary.department_id
                  AND succession_next.job_title='HR Supervisor II' AND succession_next.rank_category_id=4
                  AND succession_next.is_active=1 AND $successorEligible LIMIT 1) END
        FROM job_titles succession_primary JOIN departments succession_d ON succession_d.department_id=succession_primary.department_id
        WHERE succession_primary.job_title_id=$position AND succession_primary.department_id=$department
          AND succession_primary.job_title='HR Supervisor I' AND succession_primary.rank_category_id=4
          AND succession_primary.is_active=1 AND succession_d.is_active=1 AND succession_d.deleted_at IS NULL
          AND succession_d.department_name='Human Resources' LIMIT 1), $position)";
}

function effectiveHRSupervisorPosition($conn, int $position, int $department): int
{
    $sql = hrSupervisorPositionSql((string)$position, (string)$department);
    return (int)(annualQuery($conn, "SELECT $sql AS position_id")[0]['position_id'] ?? $position);
}

/** Completed approvals retain their recorded reviewer; only unfinished reviews use acting coverage. */
function hrEvaluationReviewerSql(string $member, string $department): string
{
    $effective = hrSupervisorPositionSql("$member.reviewer_position_id", $department);
    return "CASE WHEN $member.current_version>0 AND $member.revision_pending=0
        THEN $member.reviewer_position_id ELSE $effective END";
}
