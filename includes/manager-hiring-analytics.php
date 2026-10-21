<?php
// Hiring is based on the employment start date, not account creation or evaluations.
// Include separated employees for historical accuracy; omit deleted/admin records.
$hiringScope = "e.deleted_at IS NULL AND e.hire_date <= CURDATE()
    AND NOT EXISTS (SELECT 1 FROM users u WHERE u.employee_id=e.employee_id AND u.role='Admin')";
$hiringCurrentYear = (int)date('Y');
$hiringYears = [$hiringCurrentYear];
$hiringYearRows = $conn->query("SELECT DISTINCT YEAR(e.hire_date) AS yr FROM employees e WHERE $hiringScope ORDER BY yr DESC");
while ($hiringRow = $hiringYearRows->fetch_assoc()) {
    if ((int)$hiringRow['yr'] > 0) $hiringYears[] = (int)$hiringRow['yr'];
}
$hiringYears = array_values(array_unique($hiringYears));
rsort($hiringYears);
$hiringYearInput = filter_var($_GET['hire_year'] ?? $hiringCurrentYear, FILTER_VALIDATE_INT);
$hiringYear = in_array($hiringYearInput, $hiringYears, true) ? $hiringYearInput : $hiringCurrentYear;
$hiringMonth = filter_var($_GET['hire_month'] ?? 0, FILTER_VALIDATE_INT);
$hiringMonth = ($hiringMonth >= 1 && $hiringMonth <= 12) ? $hiringMonth : 0;
$hiringPage = max(1, (int)filter_var($_GET['hire_page'] ?? 1, FILTER_VALIDATE_INT));
$hiringSummary = $conn->query("SELECT
    COALESCE(SUM(e.hire_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')),0) AS this_month,
    COALESCE(SUM(e.hire_date >= MAKEDATE(YEAR(CURDATE()),1)),0) AS this_year
    FROM employees e WHERE $hiringScope")->fetch_assoc();
$hiringMonthly = array_fill(1, 12, 0);
$hiringStmt = $conn->prepare("SELECT MONTH(e.hire_date) AS mo, COUNT(*) AS total FROM employees e
    WHERE $hiringScope AND e.hire_date >= ? AND e.hire_date < ? GROUP BY MONTH(e.hire_date)");
$hiringYearStart = sprintf('%04d-01-01', $hiringYear);
$hiringYearEnd = sprintf('%04d-01-01', $hiringYear + 1);
$hiringStmt->bind_param('ss', $hiringYearStart, $hiringYearEnd);
$hiringStmt->execute();
$hiringMonthlyRows = $hiringStmt->get_result();
while ($hiringRow = $hiringMonthlyRows->fetch_assoc()) $hiringMonthly[(int)$hiringRow['mo']] = (int)$hiringRow['total'];
$hiringStmt->close();
$hiringSelectedTotal = array_sum($hiringMonthly);
$hiringStart = $hiringMonth ? sprintf('%04d-%02d-01', $hiringYear, $hiringMonth) : $hiringYearStart;
$hiringEnd = $hiringMonth ? date('Y-m-d', strtotime($hiringStart . ' +1 month')) : $hiringYearEnd;
$hiringPeriod = $hiringMonth ? date('F Y', strtotime($hiringStart)) : (string)$hiringYear;
$hiringPeriodTotal = $hiringMonth ? $hiringMonthly[$hiringMonth] : $hiringSelectedTotal;
$hiringPages = max(1, (int)ceil($hiringPeriodTotal / 25));
$hiringPage = min($hiringPage, $hiringPages);
$hiringOffset = ($hiringPage - 1) * 25;
$hiringStmt = $conn->prepare("SELECT COALESCE(d.department_name,'Unassigned') AS department_name, COUNT(*) AS total
    FROM employees e LEFT JOIN departments d ON d.department_id=e.department_id
    WHERE $hiringScope AND e.hire_date >= ? AND e.hire_date < ?
    GROUP BY e.department_id,d.department_name ORDER BY total DESC,department_name");
$hiringStmt->bind_param('ss', $hiringStart, $hiringEnd);
$hiringStmt->execute();
$hiringDepartments = $hiringStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$hiringStmt->close();
$hiringStmt = $conn->prepare("SELECT e.employee_code, e.first_name, e.last_name, e.job_title,e.hire_date,
    e.employment_status,COALESCE(d.department_name,'Unassigned') AS department_name,
    COALESCE(b.branch_name,'Unassigned') AS branch_name
    FROM employees e LEFT JOIN departments d ON d.department_id=e.department_id
    LEFT JOIN branches b ON b.branch_id=e.branch_id
    WHERE $hiringScope AND e.hire_date >= ? AND e.hire_date < ?
    ORDER BY e.hire_date DESC,e.employee_id DESC LIMIT 25 OFFSET ?");
$hiringStmt->bind_param('ssi', $hiringStart, $hiringEnd, $hiringOffset);
$hiringStmt->execute();
$hiringEmployees = $hiringStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$hiringStmt->close();
$hiringLink = static function (int $page) use ($hiringYear, $hiringMonth): string {
    $query = array_intersect_key($_GET, array_flip(['cohort','branch','department','date_from','date_to']));
    $query = array_filter($query, 'is_scalar');
    return 'analytics.php?' . http_build_query(array_merge($query, ['tab'=>'hiring','hire_year'=>$hiringYear,'hire_month'=>$hiringMonth,'hire_page'=>$page])) . '#hiring-analytics';
};
?>
<link rel="stylesheet" href="../assets/css/manager-hiring-analytics.css?v=3">
<section id="hiring-analytics" class="hiring-workspace" aria-labelledby="hiring-title">
    <header class="hiring-heading">
        <div>
            <div class="hiring-eyebrow">WORKFORCE INSIGHTS</div>
            <h2 id="hiring-title">Hiring Analytics</h2>
            <p>Track new hires and explore how your workforce has grown.</p>
        </div>
        <span class="hiring-updated"><span aria-hidden="true"></span>Updated <?php echo e(date('M d, Y')); ?></span>
    </header>

    <div class="hiring-kpis">
        <?php foreach ([
            ['This month', (int)$hiringSummary['this_month'], date('F Y'), 'fa-calendar-plus', 'month'],
            ['This year', (int)$hiringSummary['this_year'], (string)$hiringCurrentYear . ' to date', 'fa-user-plus', 'year'],
            ['Selected year', $hiringSelectedTotal, (string)$hiringYear . ' total hires', 'fa-chart-line', 'selected']
        ] as [$hiringLabel,$hiringValue,$hiringCaption,$hiringIcon,$hiringTone]): ?>
        <article class="hiring-kpi hiring-kpi--<?php echo e($hiringTone); ?> page-hero fadeup">
            <div class="hiring-kpi-top"><span><?php echo e($hiringLabel); ?></span><span class="hiring-kpi-icon"><i class="fas <?php echo e($hiringIcon); ?>" aria-hidden="true"></i></span></div>
            <div class="hiring-kpi-value"><?php echo number_format($hiringValue); ?><span>new <?php echo $hiringValue === 1 ? 'hire' : 'hires'; ?></span></div>
            <div class="hiring-kpi-caption"><?php echo e($hiringCaption); ?></div>
        </article>
        <?php endforeach; ?>
    </div>

    <form method="get" action="analytics.php#hiring-analytics" class="hiring-toolbar">
        <input type="hidden" name="tab" value="hiring">
        <?php foreach (['cohort','branch','department','date_from','date_to'] as $hiringKey):
            if (!isset($_GET[$hiringKey]) || !is_scalar($_GET[$hiringKey])) continue; ?>
        <input type="hidden" name="<?php echo e($hiringKey); ?>" value="<?php echo e((string)$_GET[$hiringKey]); ?>">
        <?php endforeach; ?>
        <div class="hiring-toolbar-title"><span class="hiring-toolbar-icon"><i class="fas fa-sliders-h" aria-hidden="true"></i></span><div><strong>Explore hiring</strong><small>Company-wide &middot; By hire date</small></div></div>
        <div class="hiring-filter"><label for="hire-year">Year</label>
            <select name="hire_year" id="hire-year" class="form-select">
            <?php foreach ($hiringYears as $hiringOption): ?><option value="<?php echo $hiringOption; ?>" <?php echo $hiringYear === $hiringOption ? 'selected' : ''; ?>><?php echo $hiringOption; ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="hiring-filter"><label for="hire-month">Month</label>
            <select name="hire_month" id="hire-month" class="form-select">
                <option value="0">All months</option>
                <?php for ($hiringM=1;$hiringM<=12;$hiringM++): ?><option value="<?php echo $hiringM; ?>" <?php echo $hiringMonth === $hiringM ? 'selected' : ''; ?>><?php echo date('F',mktime(0,0,0,$hiringM,1)); ?></option><?php endfor; ?>
            </select>
        </div>
        <button type="submit" class="hiring-apply"><i class="fas fa-arrow-right" aria-hidden="true"></i>Update view</button>
        <a class="hiring-reset" href="analytics.php?tab=hiring#hiring-analytics">Reset</a>
    </form>

    <div class="hiring-insights">
        <article class="hiring-surface hiring-trend">
            <header class="hiring-card-heading"><div><h3>Hiring trend</h3><p>Monthly new hires in <?php echo $hiringYear; ?></p></div><span class="hiring-chip"><?php echo number_format($hiringSelectedTotal); ?> hires</span></header>
            <?php if ($hiringSelectedTotal > 0): ?>
            <div class="hiring-chart-scroll" tabindex="0" role="region" aria-label="Monthly hiring counts for <?php echo $hiringYear; ?>">
                <div class="hiring-bars">
                <?php $hiringMax = max(1,max($hiringMonthly)); foreach ($hiringMonthly as $hiringM=>$hiringCount): ?>
                    <div class="hiring-bar-column<?php echo $hiringMonth === $hiringM ? ' is-selected' : ''; ?>">
                        <div class="hiring-bar-track"><div class="hiring-bar-fill" style="--bar-height:<?php echo round($hiringCount/$hiringMax*100,2); ?>%"><span><?php echo $hiringCount; ?></span></div></div>
                        <div class="hiring-bar-label"><?php echo date('M',mktime(0,0,0,$hiringM,1)); ?></div>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
            <div class="hiring-chart-caption"><span><i aria-hidden="true"></i>New hires</span><span>January &ndash; December <?php echo $hiringYear; ?></span></div>
            <?php else: ?>
            <div class="hiring-empty hiring-empty--chart">
                <span class="hiring-empty-icon"><i class="fas fa-chart-bar" aria-hidden="true"></i></span>
                <strong>No hires recorded in <?php echo $hiringYear; ?></strong>
                <p>Choose another year to explore your hiring history.</p>
                <?php $hiringPastYears = array_values(array_filter($hiringYears, static fn($year) => $year !== $hiringCurrentYear));
                if ($hiringPastYears): ?>
                <a href="analytics.php?tab=hiring&amp;hire_year=<?php echo $hiringPastYears[0]; ?>#hiring-analytics" class="hiring-text-link">View <?php echo $hiringPastYears[0]; ?> hiring <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </article>

        <article class="hiring-surface hiring-departments">
            <header class="hiring-card-heading"><div><h3>Department breakdown</h3><p><?php echo e($hiringPeriod); ?> &middot; Share of new hires</p></div><span class="hiring-heading-icon"><i class="fas fa-sitemap" aria-hidden="true"></i></span></header>
            <?php if (!$hiringDepartments): ?>
            <div class="hiring-empty"><span class="hiring-empty-icon"><i class="fas fa-building" aria-hidden="true"></i></span><strong>No department data yet</strong><p>Department totals appear when hires are recorded for this period.</p></div>
            <?php else: ?>
            <div class="hiring-department-list" tabindex="0" role="region" aria-label="New hires by department">
            <?php foreach ($hiringDepartments as $hiringDepartment):
                $hiringShare = $hiringPeriodTotal ? round((int)$hiringDepartment['total']/$hiringPeriodTotal*100) : 0; ?>
                <div class="hiring-department">
                    <div><span><?php echo e($hiringDepartment['department_name']); ?></span><strong><?php echo (int)$hiringDepartment['total']; ?> <small><?php echo $hiringShare; ?>%</small></strong></div>
                    <div class="hiring-share-track"><span style="width:<?php echo $hiringShare; ?>%"></span></div>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </article>
    </div>

    <article class="hiring-surface hiring-directory">
        <header class="hiring-card-heading"><div><h3>New hires <span class="hiring-chip"><?php echo number_format($hiringPeriodTotal); ?></span></h3><p>Employees who joined in <?php echo e($hiringPeriod); ?></p></div><span class="hiring-list-note"><i class="fas fa-sort-amount-down" aria-hidden="true"></i>Latest first</span></header>
        <?php if (!$hiringEmployees): ?>
        <div class="hiring-empty hiring-empty--directory"><span class="hiring-empty-icon"><i class="fas fa-user-plus" aria-hidden="true"></i></span><strong>No employees joined in this period</strong><p>Try another month or year to see employee details.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table hiring-table">
                <caption class="visually-hidden">Employees hired in <?php echo e($hiringPeriod); ?></caption>
                <thead><tr><th scope="col">Employee</th><th scope="col">Department / Branch</th><th scope="col">Date hired</th><th scope="col">Current status</th></tr></thead>
                <tbody>
                <?php foreach ($hiringEmployees as $hiringEmployee): ?>
                <tr><td><div class="hiring-person"><span class="hiring-avatar" aria-hidden="true"><?php echo e(mb_substr($hiringEmployee['first_name'],0,1).mb_substr($hiringEmployee['last_name'],0,1)); ?></span><div><strong><?php echo e($hiringEmployee['first_name'].' '.$hiringEmployee['last_name']); ?></strong><small><?php echo e($hiringEmployee['employee_code'].' · '.$hiringEmployee['job_title']); ?></small></div></div></td>
                <td><?php echo e($hiringEmployee['department_name']); ?><small><?php echo e($hiringEmployee['branch_name']); ?></small></td>
                <td class="text-nowrap"><?php echo e(date('M d, Y',strtotime($hiringEmployee['hire_date']))); ?></td>
                <td><span class="hiring-status"><?php echo e($hiringEmployee['employment_status']); ?></span></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <nav class="hiring-pagination" aria-label="Hired employees pagination">
            <span>Showing <?php echo number_format($hiringOffset+1); ?>&ndash;<?php echo number_format($hiringOffset+count($hiringEmployees)); ?> of <?php echo number_format($hiringPeriodTotal); ?> employees</span>
            <div><span>Page <?php echo $hiringPage; ?> of <?php echo $hiringPages; ?></span>
            <?php if ($hiringPage>1): ?><a href="<?php echo e($hiringLink($hiringPage-1)); ?>" aria-label="Previous page"><i class="fas fa-chevron-left" aria-hidden="true"></i></a><?php endif; ?>
            <?php if ($hiringPage<$hiringPages): ?><a href="<?php echo e($hiringLink($hiringPage+1)); ?>" aria-label="Next page"><i class="fas fa-chevron-right" aria-hidden="true"></i></a><?php endif; ?>
            </div>
        </nav>
        <?php endif; ?>
    </article>
    <p class="hiring-footnote"><i class="fas fa-info-circle" aria-hidden="true"></i>Counts use employee hire dates, including separated employees. Deleted records, administrators, and future start dates are excluded.</p>
</section>

