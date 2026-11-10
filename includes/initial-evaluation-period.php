<?php
/** Employee-specific Initial dates; existing evaluation periods are preserved by callers. */
function initialEvaluationPeriod(array $employee): array
{
    $status = $employee['employment_status'] ?? '';
    $validDate = static function ($value): bool {
        if (!is_string($value)) return false;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    };
    if ($status === 'Probationary') {
        $from = $employee['hire_date'] ?? null;
        if (!$validDate($from)) throw new DomainException('Ask HR to record a valid probationary employment start date before opening the Initial evaluation.');
        $start = new DateTimeImmutable($from);
        $target = $start->modify('first day of this month')->modify('+3 months');
        $to = $target->setDate((int)$target->format('Y'), (int)$target->format('m'), min((int)$start->format('d'), (int)$target->format('t')))->format('Y-m-d');
    } elseif (in_array($status, ['OJT', 'Trainee', 'Project Based', 'Project-Based'], true)) {
        $from = $employee['contract_start_date'] ?? null;
        $to = $employee['contract_end_date'] ?? null;
        if (!$validDate($from) || !$validDate($to)) throw new DomainException('Ask HR to complete valid contract start and end dates before opening the Initial evaluation.');
    } else {
        throw new DomainException('Initial evaluation dates apply only to non-regular employees.');
    }
    if ($from > $to) throw new DomainException('The employment start date must be on or before the end date. Ask HR to correct the employee record.');
    return [$from, $to];
}
