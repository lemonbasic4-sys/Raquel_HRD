<?php
/** Link imported mock forms when the Admin creates their intended reviewer's account. */
function bindSeededEvaluationFormsToAccount(mysqli $conn, int $userId): int
{
    $table = $conn->query("SELECT 1 FROM information_schema.tables
        WHERE table_schema=DATABASE() AND table_name='evaluation_seed_template_owners' LIMIT 1");
    if (!$table || !$table->num_rows) {
        return 0; // Installations without the optional department fixtures.
    }
    $stmt = $conn->prepare("UPDATE evaluation_templates t
        JOIN evaluation_seed_template_owners owner ON owner.form_code=t.form_code
        JOIN users u ON u.employee_id=owner.employee_id AND u.user_id=?
        JOIN employees e ON e.employee_id=u.employee_id
        SET t.created_by=u.user_id
        WHERE t.created_by IS NULL AND t.deleted_at IS NULL
          AND (u.role=owner.preferred_role OR (owner.preferred_role='Employee'
               AND u.role IN ('HR Manager','HR Supervisor','HR Staff')))
          AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0
          AND e.is_active=1 AND e.deleted_at IS NULL");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}
