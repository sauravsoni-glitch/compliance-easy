<div class="page-header">
    <div>
        <h1 class="page-title"><?= htmlspecialchars((string)($dashboardTitle ?? 'Dashboard')) ?></h1>
        <p class="page-subtitle"><?= ($user['role_slug'] ?? '') === 'admin' ? 'Compliance Management Overview' : 'Your assigned compliances and tasks' ?></p>
    </div>
</div>
<?php
$fe = $_SESSION['flash_error'] ?? null;
$fs = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);
?>
<?php if ($fe): ?><div class="alert alert-danger"><?= htmlspecialchars($fe) ?></div><?php endif; ?>
<?php if ($fs): ?><div class="alert alert-success"><?= htmlspecialchars($fs) ?></div><?php endif; ?>

<?php
$basePath = $basePath ?? '';
$dashboardView = (string)($dashboardView ?? 'dashboard');
$isDeepDiveView = $dashboardView === 'deep-dive';
$role = $user['role_slug'] ?? '';
$isAdmin = $role === 'admin';
$isMaker = $role === 'maker';
$isReviewer = $role === 'reviewer';
$isApprover = $role === 'approver';
$roleFocusCount = (int)($roleFocusCount ?? 0);
$roleFocusLabel = $roleFocusLabel ?? 'Action items';
?>

<div class="card mb-3">
    <div class="card-body py-2">
        <div class="rpt-tabs" style="margin:0;">
            <a href="<?= htmlspecialchars($basePath) ?>/dashboard" class="rpt-tab <?= !$isDeepDiveView ? 'active' : '' ?>"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="<?= htmlspecialchars($basePath) ?>/dashboard?view=deep-dive" class="rpt-tab <?= $isDeepDiveView ? 'active' : '' ?>"><i class="fas fa-magnifying-glass-chart"></i> Deep dive</a>
        </div>
    </div>
</div>

<?php if (!$isDeepDiveView): ?>
<div class="card mb-3" style="border-left: 4px solid var(--primary, #c41e3a);">
    <div class="card-body py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <strong class="d-block"><?= htmlspecialchars($roleFocusLabel) ?></strong>
            <span class="text-muted text-sm">Based on your role and assignments</span>
        </div>
        <a href="<?= htmlspecialchars($basePath) ?>/compliance" class="btn btn-primary"><?= (int)$roleFocusCount ?> item<?= $roleFocusCount === 1 ? '' : 's' ?> — Open list</a>
    </div>
</div>

<div class="stats-grid dashboard-kpi">
    <div class="stat-card primary stat-card-clickable" data-modal="modal-all" title="Click to view all compliances">
        <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
        <div>
            <div class="stat-value"><?= (int)($totalCompliances ?? 0) ?></div>
            <div class="stat-label">Total Compliances</div>
        </div>
    </div>
    <div class="stat-card warning stat-card-clickable" data-modal="modal-pending" title="Click to view pending">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-value"><?= (int)($pendingSubmissions ?? 0) ?></div>
            <div class="stat-label">Pending Submissions</div>
        </div>
    </div>
    <div class="stat-card success stat-card-clickable" data-modal="modal-approved" title="Click to view approved">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-value"><?= (int)($approvedCount ?? 0) ?></div>
            <div class="stat-label">Approved</div>
        </div>
    </div>
    <div class="stat-card danger stat-card-clickable" data-modal="modal-rejected" title="Click to view rejected">
        <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        <div>
            <div class="stat-value"><?= (int)($rejectedCount ?? 0) ?></div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>
    <div class="stat-card primary stat-card-clickable" data-modal="modal-upcoming" title="Click to view upcoming due">
        <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        <div>
            <div class="stat-value"><?= (int)($upcomingDueCount ?? 0) ?></div>
            <div class="stat-label">Upcoming Due Dates</div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">Delivery Health</h3>
    </div>
    <div class="stats-grid mb-0">
        <div class="stat-card success stat-card-clickable" data-modal="modal-ontime-month" title="Click to view on-time completions this month">
            <div class="stat-icon"><i class="fas fa-bolt"></i></div>
            <div>
                <div class="stat-value"><?= (int)($onTimeCompletedMonth ?? 0) ?></div>
                <div class="stat-label">On-Time Completed (This Month)</div>
            </div>
        </div>
        <div class="stat-card success stat-card-clickable" data-modal="modal-ontime-6m" title="Click to view on-time completions in last 6 months">
            <div class="stat-icon"><i class="fas fa-history"></i></div>
            <div>
                <div class="stat-value"><?= (int)($onTimeCompleted6Months ?? 0) ?></div>
                <div class="stat-label">On-Time Completed (Last 6 Months)</div>
            </div>
        </div>
        <div class="stat-card danger stat-card-clickable" data-modal="modal-overdue-tasks" title="Click to view overdue tasks">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div>
                <div class="stat-value"><?= (int)($overdueCount ?? 0) ?></div>
                <div class="stat-label">Overdue Tasks</div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">Monthly Delay Signal</h3>
    </div>
    <p class="text-muted text-sm mb-2">Checks if the same department or compliance is delayed in each of the last 6 months.</p>
    <div class="d-flex flex-wrap gap-2">
        <?php if (!empty($persistentDelayDepartment)): ?>
        <div class="alert alert-danger mb-0" style="flex:1;min-width:260px;">
            <strong>Department delayed every month:</strong>
            <?= htmlspecialchars((string)$persistentDelayDepartment['department']) ?>
            <span class="text-sm">(<?= (int)$persistentDelayDepartment['delay_instances'] ?> delays)</span>
        </div>
        <?php else: ?>
        <div class="alert alert-success mb-0" style="flex:1;min-width:260px;">
            <strong>No department</strong> is delayed in all last 6 months.
        </div>
        <?php endif; ?>

        <?php if (!empty($persistentDelayCompliance)): ?>
        <div class="alert alert-danger mb-0" style="flex:1;min-width:260px;">
            <strong>Compliance delayed every month:</strong>
            <a href="<?= $basePath ?>/compliance/view/<?= (int)$persistentDelayCompliance['id'] ?>">
                <?= htmlspecialchars((string)$persistentDelayCompliance['compliance_code']) ?>
            </a>
            <span class="text-sm">(<?= (int)$persistentDelayCompliance['delay_instances'] ?> delays)</span>
        </div>
        <?php else: ?>
        <div class="alert alert-success mb-0" style="flex:1;min-width:260px;">
            <strong>No compliance</strong> is delayed in all last 6 months.
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">Activity Timeline</h3>
        <a href="<?= htmlspecialchars($basePath) ?>/compliance" class="btn btn-secondary btn-sm">View All</a>
    </div>
    <?php if (!empty($recentActivity)): ?>
    <ul class="activity-timeline">
        <?php foreach ($recentActivity as $a): ?>
        <li class="activity-item">
            <a href="<?= $basePath ?>/compliance/view/<?= (int)$a['id'] ?>" class="activity-link">
                <span class="activity-dot"></span>
                <div class="activity-body">
                    <strong>Created <?= htmlspecialchars((string)($a['compliance_code'] ?? '')) ?>:</strong>
                    <?= htmlspecialchars((string)($a['title'] ?? '')) ?>
                    <div class="text-muted activity-meta">
                        by <?= htmlspecialchars($user['full_name'] ?? 'User') ?> ·
                        <?= htmlspecialchars(\App\Core\MailIstTime::formatUiDateTime((string)($a['created_at'] ?? ''))) ?>
                    </div>
                </div>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p class="text-muted mb-0">No recent activity.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($isDeepDiveView && !empty($reportsPayload) && is_array($reportsPayload)): ?>
<?php extract($reportsPayload, EXTR_SKIP); ?>
<?php $reportsHostPage = 'dashboard'; ?>
<?php require __DIR__ . '/../reports/_unified_reports_tab.php'; ?>
<?php endif; ?>

<?php if (!$isDeepDiveView): ?>
<div class="dashboard-grid">
    <div class="dashboard-main">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Delayed Departments (Last 6 Months)</h3>
            </div>
            <p class="text-muted text-sm mb-2">Departments with recurring monthly late completions.</p>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Department</th><th>Delayed Months</th><th>Delay Instances</th></tr></thead>
                    <tbody>
                        <?php foreach (($departmentDelayHotspots ?? []) as $d): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($d['department'] ?? 'Unspecified')) ?></td>
                            <td><span class="badge badge-warning"><?= (int)($d['delayed_months'] ?? 0) ?></span></td>
                            <td><?= (int)($d['delay_instances'] ?? 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($departmentDelayHotspots ?? [])): ?><tr><td colspan="3" class="text-muted">No recurring department delay pattern detected.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Delayed Compliances (Last 6 Months)</h3>
            </div>
            <p class="text-muted text-sm mb-2">Compliance items repeatedly delayed across months.</p>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Compliance</th><th>Delayed Months</th><th>Delay Instances</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach (($complianceDelayHotspots ?? []) as $cHot): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($cHot['compliance_code'] ?? '')) ?> — <?= htmlspecialchars((string)($cHot['title'] ?? '')) ?></td>
                            <td><span class="badge badge-warning"><?= (int)($cHot['delayed_months'] ?? 0) ?></span></td>
                            <td><?= (int)($cHot['delay_instances'] ?? 0) ?></td>
                            <td><a href="<?= $basePath ?>/compliance/view/<?= (int)($cHot['id'] ?? 0) ?>" class="btn btn-sm btn-secondary">Open</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($complianceDelayHotspots ?? [])): ?><tr><td colspan="4" class="text-muted">No recurring compliance delay pattern detected.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="dashboard-side">
        <div class="card alerts-card">
            <div class="card-header">
                <h3 class="card-title">Alerts</h3>
                <?php $alertCount = count($overdueList ?? []) + count($highRiskList ?? []) + count($reworkList ?? []); ?>
                <?php if ($alertCount > 0): ?><span class="badge badge-danger"><?= $alertCount ?></span><?php endif; ?>
            </div>
            <?php
            $alerts = [];
            foreach ($reworkList ?? [] as $r) { $alerts[] = ['type' => 'Rework Required', 'item' => $r, 'class' => 'alert-rework']; }
            foreach ($highRiskList ?? [] as $h) { $alerts[] = ['type' => 'High Risk Pending', 'item' => $h, 'class' => 'alert-highrisk']; }
            foreach ($overdueList ?? [] as $o) { $alerts[] = ['type' => 'Overdue', 'item' => $o, 'class' => 'alert-overdue']; }
            $alerts = array_slice($alerts, 0, 8);
            ?>
            <?php if (!empty($alerts)): ?>
            <ul class="alerts-list">
                <?php foreach ($alerts as $a): ?>
                <li class="alert-item <?= $a['class'] ?>">
                    <a href="<?= $basePath ?>/compliance/view/<?= (int)$a['item']['id'] ?>">
                        <span class="alert-icon"><i class="fas fa-<?= $a['type'] === 'Overdue' ? 'clock' : 'exclamation-triangle' ?>"></i></span>
                        <span><?= htmlspecialchars($a['type']) ?> · <?= htmlspecialchars($a['item']['compliance_code']) ?>: <?= htmlspecialchars(mb_substr($a['item']['title'], 0, 40)) ?><?= mb_strlen($a['item']['title']) > 40 ? '…' : '' ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-muted mb-0">No alerts.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-calendar-span">
        <div class="card compliance-calendar-card compliance-calendar-ref" data-today-ist="<?= htmlspecialchars(\App\Core\MailIstTime::todayYmd()) ?>">
            <div class="card-header compliance-cal-header-ref">
                <h3 class="card-title">Compliance Calendar</h3>
                <div class="cal-month-nav-ref">
                    <?php
                    $calMonth = $calendarMonth ?? \App\Core\MailIstTime::yearMonthNow();
                    $prevMonth = \App\Core\MailIstTime::shiftYearMonth($calMonth, -1);
                    $nextMonth = \App\Core\MailIstTime::shiftYearMonth($calMonth, 1);
                    $monthLabel = \App\Core\MailIstTime::formatDateOnly($calMonth . '-01', null, 'F Y');
                    ?>
                    <a href="?cal_month=<?= htmlspecialchars($prevMonth) ?>" class="cal-nav-arrow" aria-label="Previous month">&lt;</a>
                    <span class="cal-month-title-ref"><?= htmlspecialchars($monthLabel) ?></span>
                    <a href="?cal_month=<?= htmlspecialchars($nextMonth) ?>" class="cal-nav-arrow" aria-label="Next month">&gt;</a>
                </div>
            </div>
            <div class="compliance-calendar-grid cal-grid-ref">
                <?php
                foreach (['S', 'M', 'T', 'W', 'T', 'F', 'S'] as $dh) {
                    echo '<span class="cal-head cal-head-ref">' . $dh . '</span>';
                }
                $calYm = $calendarMonth ?? \App\Core\MailIstTime::yearMonthNow();
                $daysInMonth = \App\Core\MailIstTime::daysInMonthYm($calYm);
                $firstDow = \App\Core\MailIstTime::firstWeekdayOfYm($calYm);
                $today = \App\Core\MailIstTime::todayYmd();
                for ($i = 0; $i < $firstDow; $i++) {
                    echo '<span class="cal-cell cal-empty cal-cell-ref-empty"></span>';
                }
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = $calYm . '-' . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
                    $events = $calendarEvents[$date] ?? [];
                    $hasEvent = count($events) > 0;
                    echo '<div class="cal-cell cal-cell-ref" data-date="' . htmlspecialchars($date) . '" data-events="' . htmlspecialchars(json_encode($events)) . '" role="button" tabindex="0">';
                    echo '<span class="cal-num-ref">' . $day . '</span>';
                    if ($hasEvent) {
                        echo '<span class="cal-event-square" aria-hidden="true"></span>';
                    }
                    echo '</div>';
                }
                ?>
            </div>
        </div>

        <div class="card calendar-day-events-card">
            <div class="card-header calendar-day-events-header">
                <h3 class="card-title" id="cal-selected-title-ref">—</h3>
            </div>
            <div class="calendar-day-events-body" id="cal-selected-body-ref">
                <div class="cal-selected-empty">
                    <i class="far fa-calendar-alt cal-selected-empty-icon"></i>
                    <p class="text-muted mb-0">No events on this date</p>
                </div>
            </div>
        </div>

        <div class="card upcoming-events-panel upcoming-events-ref">
            <div class="card-header">
                <h3 class="card-title">Upcoming Events</h3>
                <a href="<?= $basePath ?>/compliance?from=<?= htmlspecialchars(\App\Core\MailIstTime::todayYmd()) ?>&to=<?= htmlspecialchars(\App\Core\MailIstTime::shiftCalendarDays(\App\Core\MailIstTime::todayYmd(), 30)) ?>" class="btn btn-secondary btn-sm">View All</a>
            </div>
            <?php if (!empty($upcomingDue)): ?>
            <ul class="upcoming-events-list-ref">
                <?php foreach (array_slice($upcomingDue, 0, 10) as $u): ?>
                <?php
                $due = $u['due_date'];
                $st = $u['status'] ?? '';
                if (in_array($st, ['approved', 'completed'], true)) {
                    $pillClass = 'upcoming-pill-approved';
                    $pillText = 'approved';
                } elseif (in_array($st, ['submitted', 'under_review'], true)) {
                    $pillClass = 'upcoming-pill-review';
                    $pillText = 'submitted for review';
                } else {
                    $pillClass = 'upcoming-pill-pending';
                    $pillText = 'pending';
                }
                $dueY = substr((string) $due, 0, 10);
                $rangeStart = !empty($u['start_date']) ? substr((string) $u['start_date'], 0, 10) : (!empty($u['expected_date']) ? substr((string) $u['expected_date'], 0, 10) : \App\Core\MailIstTime::shiftCalendarDays($dueY, -6));
                if ($rangeStart > $dueY) {
                    $rangeStart = $dueY;
                }
                $rangeLabel = \App\Core\MailIstTime::formatDateOnly($rangeStart, null, 'M j') . ' - ' . \App\Core\MailIstTime::formatDateOnly($dueY, null, 'M j');
                $todayY = \App\Core\MailIstTime::todayYmd();
                $daysLeft = (int) floor((strtotime($dueY . ' 00:00:00') - strtotime($todayY . ' 00:00:00')) / 86400);
                if ($daysLeft < 0) {
                    $dueMetaText = 'Overdue by ' . abs($daysLeft) . ' day' . (abs($daysLeft) === 1 ? '' : 's');
                    $dueMetaClass = 'upcoming-due-overdue';
                } elseif ($daysLeft === 0) {
                    $dueMetaText = 'Due today';
                    $dueMetaClass = 'upcoming-due-today';
                } else {
                    $dueMetaText = 'Due in ' . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's');
                    $dueMetaClass = 'upcoming-due-soon';
                }
                ?>
                <li class="upcoming-event-row-ref">
                    <a href="<?= $basePath ?>/compliance/view/<?= (int)$u['id'] ?>" class="upcoming-event-link-ref">
                        <span class="upcoming-event-main-ref">
                            <span class="upcoming-event-title-ref"><?= htmlspecialchars($u['title']) ?></span>
                            <span class="upcoming-event-submeta-ref">
                                <span class="upcoming-code-ref"><?= htmlspecialchars((string)($u['compliance_code'] ?? '')) ?></span>
                                <span class="upcoming-dept-ref"><?= htmlspecialchars((string)($u['department'] ?? 'General')) ?></span>
                            </span>
                            <span class="upcoming-event-dates-ref"><?= htmlspecialchars($rangeLabel) ?></span>
                            <span class="upcoming-due-meta-ref <?= $dueMetaClass ?>"><?= htmlspecialchars($dueMetaText) ?></span>
                        </span>
                        <span class="upcoming-pill <?= $pillClass ?>"><?= htmlspecialchars($pillText) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-muted mb-0 px-card-pad">No upcoming events.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
function renderKpiModal(string $id, string $title, array $list, string $viewAllUrl, string $basePath): void {
?>
<div id="<?= $id ?>" class="modal-overlay dashboard-modal" style="display: none;" aria-hidden="true">
    <div class="modal modal-dashboard">
        <div class="modal-header">
            <h3 class="modal-title"><?= htmlspecialchars($title) ?></h3>
            <a href="<?= htmlspecialchars($viewAllUrl) ?>" class="btn btn-primary btn-sm">View All</a>
            <button type="button" class="modal-close" data-close="<?= $id ?>">&times;</button>
        </div>
        <div class="modal-body">
            <div class="kpi-modal-meta text-muted text-sm mb-2"><?= count($list) ?> item<?= count($list) === 1 ? '' : 's' ?> shown</div>
            <div class="table-wrap kpi-modal-table-wrap">
            <table class="data-table kpi-modal-table">
                <thead><tr><th>ID</th><th>Title</th><th>Framework</th><th>Status</th><th>Risk</th><th>Due Date</th><th>Owner</th></tr></thead>
                <tbody>
                    <?php foreach ($list as $r): ?>
                    <tr>
                        <td class="kpi-code-cell"><?= htmlspecialchars((string)($r['compliance_code'] ?? '')) ?></td>
                        <td class="kpi-title-cell"><a href="<?= $basePath ?>/compliance/view/<?= (int)($r['id'] ?? 0) ?>"><?= htmlspecialchars((string)($r['title'] ?? '')) ?></a></td>
                        <td><?= htmlspecialchars((string)($r['framework'] ?? '—')) ?></td>
                        <?php
                        $statusRaw = (string)($r['status'] ?? '');
                        $statusBadgeClass = in_array($statusRaw, ['approved', 'completed'], true) ? 'badge-success' : ($statusRaw === 'rejected' ? 'badge-danger' : 'badge-warning');
                        $statusLabel = str_replace('_', ' ', $statusRaw);
                        $riskRaw = (string)($r['risk_level'] ?? '');
                        $riskBadgeClass = in_array($riskRaw, ['high', 'critical'], true) ? 'badge-danger' : 'badge-secondary';
                        ?>
                        <td><span class="badge <?= $statusBadgeClass ?>"><?= htmlspecialchars(ucfirst($statusLabel)) ?></span></td>
                        <td><span class="badge <?= $riskBadgeClass ?>"><?= htmlspecialchars($riskRaw !== '' ? ucfirst($riskRaw) : '—') ?></span></td>
                        <td><?= !empty($r['due_date']) ? \App\Core\MailIstTime::formatUiDate((string) $r['due_date']) : '—' ?></td>
                        <td><?= htmlspecialchars((string)($r['owner_name'] ?? '—')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($list)): ?><tr><td colspan="7">No items.</td></tr><?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>
<?php }
renderKpiModal('modal-all', 'All Compliances', $allList ?? [], $basePath . '/compliance', $basePath);
renderKpiModal('modal-pending', 'Pending Submissions', $pendingList ?? [], $basePath . '/compliance?filter=pending', $basePath);
renderKpiModal('modal-approved', 'Approved Compliances', $approvedList ?? [], $basePath . '/compliance?filter=approved', $basePath);
renderKpiModal('modal-rejected', 'Rejected Compliances', $rejectedList ?? [], $basePath . '/compliance?filter=rejected', $basePath);
renderKpiModal('modal-upcoming', 'Upcoming Due Dates', $upcomingDueList ?? [], $basePath . '/compliance?from=' . \App\Core\MailIstTime::todayYmd() . '&to=' . \App\Core\MailIstTime::shiftCalendarDays(\App\Core\MailIstTime::todayYmd(), 7), $basePath);
[$dashboardMonthFirst, $dashboardMonthLast] = \App\Core\MailIstTime::monthBoundsYmd();
$dashboardSixMonthFrom = \App\Core\MailIstTime::firstDayOfMonthOffsetFromToday(-5);
renderKpiModal('modal-ontime-month', 'On-Time Completed (This Month)', $onTimeMonthList ?? [], $basePath . '/compliance?filter=approved&from=' . $dashboardMonthFirst . '&to=' . $dashboardMonthLast, $basePath);
renderKpiModal('modal-ontime-6m', 'On-Time Completed (Last 6 Months)', $onTime6MonthsList ?? [], $basePath . '/compliance?filter=approved&from=' . $dashboardSixMonthFrom . '&to=' . $dashboardMonthLast, $basePath);
renderKpiModal('modal-overdue-tasks', 'Overdue Tasks', $overdueTasksList ?? [], $basePath . '/compliance?filter=overdue', $basePath);
?>

<script>
(function(){
    var basePath = <?= json_encode($basePath ?? '') ?>;
    /* Reference-style calendar: selected date + inline day panel */
    function initCalRef(){
        var calCard = document.querySelector('.compliance-calendar-ref');
        var todayIst = calCard && calCard.getAttribute('data-today-ist') ? calCard.getAttribute('data-today-ist') : null;
        var cells = document.querySelectorAll('.cal-grid-ref .cal-cell-ref[data-date]');
        var titleEl = document.getElementById('cal-selected-title-ref');
        var bodyEl = document.getElementById('cal-selected-body-ref');
        if (!cells.length || !titleEl || !bodyEl) return;
        function dedupeEvents(events) {
            var map = {};
            (events || []).forEach(function(ev) {
                var id = ev.compliance_id;
                if (!map[id]) map[id] = ev;
            });
            return Object.keys(map).map(function(k) { return map[k]; });
        }
        function renderPanel(dateStr, events) {
            var d = dateStr ? new Date(dateStr + 'T12:00:00') : null;
            titleEl.textContent = d ? d.toLocaleDateString('en-IN', { timeZone: 'Asia/Kolkata', month: 'short', day: 'numeric', year: 'numeric' }) : '—';
            var list = dedupeEvents(events);
            if (!list.length) {
                bodyEl.innerHTML = '<div class="cal-selected-empty"><i class="far fa-calendar-alt cal-selected-empty-icon"></i><p class="text-muted mb-0">No events on this date</p></div>';
                return;
            }
            var html = '<ul class="cal-day-events-list-ref">';
            list.forEach(function(ev) {
                var typeLabel = { due: 'Due', overdue: 'Overdue', submitted: 'Submitted', review_pending: 'Review pending', approval_pending: 'Approval pending', completed: 'Completed', escalated: 'Escalated' }[ev.type] || (ev.type || '');
                var typeClass = {
                    due: 'ev-chip-due',
                    overdue: 'ev-chip-overdue',
                    submitted: 'ev-chip-submitted',
                    review_pending: 'ev-chip-review',
                    approval_pending: 'ev-chip-approval',
                    completed: 'ev-chip-completed',
                    escalated: 'ev-chip-escalated'
                }[ev.type] || 'ev-chip-due';
                html += '<li><a href="' + basePath + '/compliance/view/' + ev.compliance_id + '" class="cal-day-event-link-ref">';
                html += '<span class="cal-day-event-name-ref">' + (ev.title || ev.compliance_code || '') + '</span>';
                html += '<span class="cal-day-event-meta-ref">';
                html += '<span class="ev-code">' + (ev.compliance_code || '') + '</span>';
                html += '<span class="ev-sep">•</span>';
                html += '<span>' + (ev.department || 'Unspecified') + '</span>';
                html += '</span>';
                html += '<span class="ev-chip ' + typeClass + '">' + typeLabel + '</span>';
                html += '</a></li>';
            });
            html += '</ul>';
            bodyEl.innerHTML = html;
        }
        function selectCell(cell) {
            cells.forEach(function(c) { c.classList.remove('cal-selected-ref'); });
            if (cell) cell.classList.add('cal-selected-ref');
        }
        var calYm = cells[0].getAttribute('data-date').slice(0, 7);
        var todayStr = todayIst;
        if (!todayStr) {
            var today = new Date();
            var y = today.getFullYear(), m = String(today.getMonth() + 1).padStart(2, '0'), da = String(today.getDate()).padStart(2, '0');
            todayStr = y + '-' + m + '-' + da;
        }
        var initial = null;
        if (todayStr.slice(0, 7) === calYm) {
            cells.forEach(function(c) { if (c.getAttribute('data-date') === todayStr) initial = c; });
        }
        if (!initial) {
            cells.forEach(function(c) {
                try {
                    var ev = JSON.parse(c.getAttribute('data-events') || '[]');
                    if (ev.length && !initial) initial = c;
                } catch (e) {}
            });
        }
        if (!initial) initial = cells[0];
        selectCell(initial);
        renderPanel(initial.getAttribute('data-date'), JSON.parse(initial.getAttribute('data-events') || '[]'));
        cells.forEach(function(cell) {
            cell.addEventListener('click', function() {
                selectCell(this);
                var ds = this.getAttribute('data-date');
                var ev = [];
                try { ev = JSON.parse(this.getAttribute('data-events') || '[]'); } catch (e) {}
                renderPanel(ds, ev);
            });
            cell.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.click(); }
            });
        });
    }

    function bindCalendarMonthNavigation() {
        var calCard = document.querySelector('.compliance-calendar-ref');
        if (!calCard) return;
        calCard.querySelectorAll('.cal-nav-arrow').forEach(function(link){
            if (link.dataset.ajaxBound === '1') return;
            link.dataset.ajaxBound = '1';
            link.addEventListener('click', function(e){
                e.preventDefault();
                var href = this.getAttribute('href');
                if (!href) return;
                var grid = calCard.querySelector('.cal-grid-ref');
                var dir = (this.getAttribute('aria-label') || '').toLowerCase().indexOf('next') >= 0 ? 'next' : 'prev';
                if (grid) {
                    grid.classList.remove('cal-slide-out-left', 'cal-slide-out-right');
                    grid.classList.add(dir === 'next' ? 'cal-slide-out-left' : 'cal-slide-out-right');
                }
                fetch(href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(r){ return r.text(); })
                    .then(function(html){
                        var doc = new DOMParser().parseFromString(html, 'text/html');
                        var newCard = doc.querySelector('.compliance-calendar-ref');
                        if (!newCard) return;
                        calCard.innerHTML = newCard.innerHTML;
                        calCard.setAttribute('data-today-ist', newCard.getAttribute('data-today-ist') || '');
                        var newGrid = calCard.querySelector('.cal-grid-ref');
                        if (newGrid) {
                            newGrid.classList.add(dir === 'next' ? 'cal-slide-in-left' : 'cal-slide-in-right');
                        }
                        initCalRef();
                        bindCalendarMonthNavigation();
                        if (window.history && window.history.pushState) {
                            var u = new URL(window.location.href);
                            var h = new URL(href, window.location.origin);
                            u.searchParams.set('cal_month', h.searchParams.get('cal_month') || '');
                            window.history.pushState({}, '', u.toString());
                        }
                    })
                    .catch(function(){ window.location.href = href; });
            });
        });
    }

    initCalRef();
    bindCalendarMonthNavigation();

    document.querySelectorAll('.stat-card-clickable[data-modal]').forEach(function(el){
        el.style.cursor = 'pointer';
        el.addEventListener('click', function(){
            var id = this.getAttribute('data-modal');
            var modal = document.getElementById(id);
            if (modal) { modal.style.display = 'flex'; modal.setAttribute('aria-hidden', 'false'); }
        });
    });
    document.querySelectorAll('.modal-overlay.dashboard-modal .modal-close').forEach(function(btn){
        btn.addEventListener('click', function(){
            var id = this.getAttribute('data-close');
            var modal = document.getElementById(id);
            if (modal) { modal.style.display = 'none'; modal.setAttribute('aria-hidden', 'true'); }
        });
    });
    document.querySelectorAll('.modal-overlay.dashboard-modal').forEach(function(overlay){
        overlay.addEventListener('click', function(e){
            if (e.target === this) { this.style.display = 'none'; this.setAttribute('aria-hidden', 'true'); }
        });
    });
})();
</script>
