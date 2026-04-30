<?php
$basePath = $basePath ?? '';
$filters = $filters ?? [];
$summary = $summary ?? [];
$mainRows = $mainRows ?? [];
$departmentPerf = $departmentPerf ?? [];
$userPerf = $userPerf ?? [];
$overdueRows = $overdueRows ?? [];
$trendLabels = $trendLabels ?? [];
$trendValues = $trendValues ?? [];
$overdueVsCompleted = $overdueVsCompleted ?? ['overdue' => 0, 'completed' => 0];
$riskDist = $riskDist ?? ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];

$currentUrl = $basePath . '/reports/dashboard';
$queryWithoutDrill = $_GET;
unset($queryWithoutDrill['drill']);
$baseQuery = http_build_query($queryWithoutDrill);
$detailBase = $currentUrl . ($baseQuery !== '' ? ('?' . $baseQuery . '&') : '?');

$badgeClass = static function (string $status): string {
    if (in_array($status, ['approved', 'completed'], true)) {
        return 'u-badge-green';
    }
    if ($status === 'overdue') {
        return 'u-badge-red';
    }
    return 'u-badge-yellow';
};
?>
<style>
.u-wrap{padding:1rem}.u-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1rem;margin-bottom:1rem}
.u-filter{position:sticky;top:0;z-index:30;background:#fff}
.u-grid{display:grid;gap:.75rem}.u-grid-7{grid-template-columns:repeat(7,minmax(110px,1fr))}.u-grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}
.u-grid-6{grid-template-columns:repeat(6,minmax(0,1fr))}.u-scroller{overflow:auto}.u-table{width:100%;border-collapse:collapse}.u-table th,.u-table td{padding:.55rem;border-bottom:1px solid #f1f5f9;font-size:.9rem}
.u-kpi{cursor:pointer;text-decoration:none;color:inherit}.u-kpi h2{margin:0;font-size:1.4rem}.u-kpi p{margin:0;color:#6b7280;font-size:.85rem}
.u-badge{padding:.2rem .5rem;border-radius:999px;font-size:.75rem}.u-badge-green{background:#dcfce7;color:#166534}.u-badge-yellow{background:#fef3c7;color:#92400e}.u-badge-red{background:#fee2e2;color:#991b1b}
.u-row-red{background:#fff1f2}.u-actions{display:flex;gap:.35rem}
@media(max-width:1200px){.u-grid-7,.u-grid-6,.u-grid-3{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>

<div class="u-wrap">
    <h1 class="page-title">Unified Compliance Reports Dashboard</h1>
    <p class="page-subtitle">One screen with filters, analytics, drill-down and exports.</p>
    <div style="margin-bottom:.75rem;">
        <a href="<?= htmlspecialchars($basePath) ?>/reports?tab=overview" class="btn btn-secondary">Open Existing Overview Tab</a>
    </div>

    <form method="get" action="<?= htmlspecialchars($basePath) ?>/reports/dashboard" class="u-card u-filter">
        <div class="u-grid u-grid-7">
            <input type="date" name="from" class="form-control" value="<?= htmlspecialchars((string)($filters['from'] ?? '')) ?>" placeholder="From Date" max="<?= htmlspecialchars(\App\Core\MailIstTime::todayYmd()) ?>">
            <input type="date" name="to" class="form-control" value="<?= htmlspecialchars((string)($filters['to'] ?? '')) ?>" placeholder="To Date" max="<?= htmlspecialchars(\App\Core\MailIstTime::todayYmd()) ?>">
            <select name="department" class="form-control">
                <option value="">All Departments</option>
                <?php foreach (($departmentOptions ?? []) as $d): ?>
                    <option value="<?= htmlspecialchars($d) ?>" <?= (($filters['department'] ?? '') === $d) ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="user_id" class="form-control">
                <option value="">All Users</option>
                <?php foreach (($userOptions ?? []) as $u): ?>
                    <option value="<?= (int)$u['id'] ?>" <?= ((int)($filters['userId'] ?? 0) === (int)$u['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string)$u['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-control">
                <option value="">All Status</option><option value="completed" <?= (($filters['status'] ?? '') === 'completed') ? 'selected' : '' ?>>Completed</option><option value="pending" <?= (($filters['status'] ?? '') === 'pending') ? 'selected' : '' ?>>Pending</option><option value="under_review" <?= (($filters['status'] ?? '') === 'under_review') ? 'selected' : '' ?>>Under Review</option><option value="overdue" <?= (($filters['status'] ?? '') === 'overdue') ? 'selected' : '' ?>>Overdue</option>
            </select>
            <select name="risk_level" class="form-control">
                <option value="">All Risk</option><option value="low" <?= (($filters['risk'] ?? '') === 'low') ? 'selected' : '' ?>>Low</option><option value="medium" <?= (($filters['risk'] ?? '') === 'medium') ? 'selected' : '' ?>>Medium</option><option value="high" <?= (($filters['risk'] ?? '') === 'high') ? 'selected' : '' ?>>High</option><option value="critical" <?= (($filters['risk'] ?? '') === 'critical') ? 'selected' : '' ?>>Critical</option>
            </select>
            <select name="priority" class="form-control">
                <option value="">All Priority</option><option value="low" <?= (($filters['priority'] ?? '') === 'low') ? 'selected' : '' ?>>Low</option><option value="medium" <?= (($filters['priority'] ?? '') === 'medium') ? 'selected' : '' ?>>Medium</option><option value="high" <?= (($filters['priority'] ?? '') === 'high') ? 'selected' : '' ?>>High</option><option value="critical" <?= (($filters['priority'] ?? '') === 'critical') ? 'selected' : '' ?>>Critical</option>
            </select>
        </div>
        <div style="margin-top:.75rem;display:flex;gap:.5rem;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">Apply Filter</button>
            <a href="<?= htmlspecialchars($basePath) ?>/reports/dashboard" class="btn btn-secondary">Reset</a>
            <a href="<?= htmlspecialchars($basePath) ?>/reports/dashboard-export?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['format' => 'csv']))) ?>" class="btn btn-secondary">Export CSV</a>
            <a href="<?= htmlspecialchars($basePath) ?>/reports/dashboard-export?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['format' => 'excel']))) ?>" class="btn btn-secondary">Export Excel</a>
            <a href="<?= htmlspecialchars($basePath) ?>/reports/dashboard-export?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['format' => 'print']))) ?>" target="_blank" class="btn btn-secondary">Print</a>
        </div>
    </form>

    <div class="u-grid u-grid-6">
        <a class="u-card u-kpi" href="<?= htmlspecialchars($detailBase . 'drill=total#main-report') ?>"><h2><?= (int)($summary['total'] ?? 0) ?></h2><p>Total Compliances</p></a>
        <a class="u-card u-kpi" href="<?= htmlspecialchars($detailBase . 'drill=completed#main-report') ?>"><h2><?= (int)($summary['completed'] ?? 0) ?></h2><p>Completed</p></a>
        <a class="u-card u-kpi" href="<?= htmlspecialchars($detailBase . 'drill=pending#main-report') ?>"><h2><?= (int)($summary['pending'] ?? 0) ?></h2><p>Pending</p></a>
        <a class="u-card u-kpi" href="<?= htmlspecialchars($detailBase . 'drill=overdue#main-report') ?>"><h2 style="color:#b91c1c"><?= (int)($summary['overdue'] ?? 0) ?></h2><p>Overdue</p></a>
        <a class="u-card u-kpi" href="<?= htmlspecialchars($detailBase . 'drill=high_risk#main-report') ?>"><h2><?= (int)($summary['high_risk'] ?? 0) ?></h2><p>High Risk</p></a>
        <a class="u-card u-kpi" href="<?= htmlspecialchars($detailBase . 'drill=escalated#main-report') ?>"><h2><?= (int)($summary['escalated'] ?? 0) ?></h2><p>Escalated</p></a>
    </div>

    <div class="u-grid u-grid-3">
        <div class="u-card"><h4>Compliance Trend</h4><canvas id="trendChart" height="120"></canvas></div>
        <div class="u-card"><h4>Overdue vs Completed</h4><canvas id="ovcChart" height="120"></canvas></div>
        <div class="u-card"><h4>Risk Distribution</h4><canvas id="riskChart" height="120"></canvas></div>
    </div>

    <div id="main-report" class="u-card">
        <h3>Main Report Table</h3>
        <div class="u-scroller">
            <table class="u-table">
                <thead><tr><th>Compliance Title</th><th>Department</th><th>Assigned User</th><th>Due Date</th><th>Completion Date</th><th>Status</th><th>Delay (Days)</th><th>Risk Level</th><th>Escalation Level</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($mainRows as $r): $st = (string)($r['status'] ?? 'pending'); $normalized = in_array($st, ['approved', 'completed'], true) ? 'completed' : (((!empty($r['due_date']) && $r['due_date'] < date('Y-m-d') && !in_array($st, ['approved','completed','rejected'], true)) || $st === 'overdue') ? 'overdue' : 'pending'); ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$r['title']) ?></td>
                        <td><?= htmlspecialchars((string)($r['department'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars((string)($r['owner_name'] ?? 'Unassigned')) ?></td>
                        <td><?= htmlspecialchars((string)($r['due_date'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars((string)($r['completion_date'] ?? '—')) ?></td>
                        <td><span class="u-badge <?= $badgeClass($normalized) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $normalized))) ?></span></td>
                        <td><?= ($r['delay_days'] === null) ? '—' : (int)$r['delay_days'] ?></td>
                        <td><?= htmlspecialchars(ucfirst((string)($r['risk_level'] ?? 'low'))) ?></td>
                        <td><?= htmlspecialchars((string)($r['escalation_level'] ?? '—')) ?></td>
                        <td class="u-actions"><a href="<?= htmlspecialchars($basePath) ?>/compliance/view/<?= (int)$r['id'] ?>" class="btn btn-sm btn-primary">View Compliance</a><a href="<?= htmlspecialchars($basePath) ?>/reports/dashboard-export?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['format' => 'csv']))) ?>" class="btn btn-sm btn-secondary">Download Data</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($mainRows)): ?><tr><td colspan="10">No records found for selected filters.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="u-grid u-grid-3">
        <div class="u-card" style="grid-column:span 2;">
            <h3>Department Performance Panel</h3>
            <div class="u-scroller"><table class="u-table"><thead><tr><th>Department</th><th>Total Tasks</th><th>Completed</th><th>Pending</th><th>Overdue</th><th>Compliance %</th><th>Avg Delay</th></tr></thead><tbody>
                <?php foreach ($departmentPerf as $d): ?>
                <tr>
                    <td><a href="<?= htmlspecialchars($currentUrl . '?' . http_build_query(array_merge($_GET, ['department' => $d['department']])) . '#main-report') ?>"><?= htmlspecialchars((string)$d['department']) ?></a></td>
                    <td><?= (int)$d['total'] ?></td><td><?= (int)$d['completed'] ?></td><td><?= (int)$d['pending'] ?></td><td><?= (int)$d['overdue'] ?></td><td><?= (int)$d['compliance_pct'] ?>%</td><td><?= htmlspecialchars((string)$d['avg_delay']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody></table></div>
        </div>
        <div class="u-card">
            <h3>User Performance Panel</h3>
            <div class="u-scroller"><table class="u-table"><thead><tr><th>User</th><th>Role</th><th>Total</th><th>Completed</th><th>Pending</th><th>Overdue</th><th>Performance %</th></tr></thead><tbody>
                <?php foreach ($userPerf as $u): ?>
                <tr>
                    <td><a href="<?= htmlspecialchars($currentUrl . '?' . http_build_query(array_merge($_GET, ['user_id' => (int)$u['user_id']])) . '#main-report') ?>"><?= htmlspecialchars((string)$u['user_name']) ?></a></td>
                    <td><?= htmlspecialchars((string)$u['role']) ?></td><td><?= (int)$u['total'] ?></td><td><?= (int)$u['completed'] ?></td><td><?= (int)$u['pending'] ?></td><td><?= (int)$u['overdue'] ?></td><td><?= (int)$u['performance_pct'] ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody></table></div>
        </div>
    </div>

    <div class="u-card">
        <h3>Overdue &amp; Penalty Tracker</h3>
        <div class="u-scroller">
            <table class="u-table">
                <thead><tr><th>Compliance Title</th><th>Department</th><th>User</th><th>Due Date</th><th>Days Overdue</th><th>Risk Level</th><th>Escalation Level</th><th>Penalty</th></tr></thead>
                <tbody>
                    <?php foreach ($overdueRows as $o): $critical = in_array((string)($o['risk_level'] ?? ''), ['high','critical'], true) || (int)($o['days_overdue'] ?? 0) > 7; ?>
                    <tr class="<?= $critical ? 'u-row-red' : '' ?>">
                        <td><?= htmlspecialchars((string)$o['title']) ?></td><td><?= htmlspecialchars((string)($o['department'] ?? '—')) ?></td><td><?= htmlspecialchars((string)($o['owner_name'] ?? 'Unassigned')) ?></td><td><?= htmlspecialchars((string)($o['due_date'] ?? '—')) ?></td><td><?= (int)($o['days_overdue'] ?? 0) ?></td><td><?= htmlspecialchars(ucfirst((string)($o['risk_level'] ?? 'low'))) ?></td><td><?= htmlspecialchars((string)($o['escalation_level'] ?? '—')) ?></td><td><?= htmlspecialchars((string)($o['penalty_impact'] ?? '—')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($overdueRows)): ?><tr><td colspan="8">No overdue rows for current filters.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var trendLabels = <?= json_encode(array_values($trendLabels), JSON_UNESCAPED_SLASHES) ?>;
    var trendValues = <?= json_encode(array_values($trendValues), JSON_UNESCAPED_SLASHES) ?>;
    new Chart(document.getElementById('trendChart'), {type: 'line', data: {labels: trendLabels, datasets: [{label: 'Compliances', data: trendValues, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.15)', tension: .3, fill: true}]}, options: {responsive: true, plugins: {legend: {display: false}}}});
    new Chart(document.getElementById('ovcChart'), {type: 'bar', data: {labels: ['Overdue', 'Completed'], datasets: [{data: [<?= (int)$overdueVsCompleted['overdue'] ?>, <?= (int)$overdueVsCompleted['completed'] ?>], backgroundColor: ['#ef4444', '#22c55e']}]}, options: {responsive: true, plugins: {legend: {display: false}}}});
    new Chart(document.getElementById('riskChart'), {type: 'doughnut', data: {labels: ['Low', 'Medium', 'High', 'Critical'], datasets: [{data: [<?= (int)$riskDist['low'] ?>, <?= (int)$riskDist['medium'] ?>, <?= (int)$riskDist['high'] ?>, <?= (int)$riskDist['critical'] ?>], backgroundColor: ['#22c55e', '#f59e0b', '#fb7185', '#dc2626']}]}, options: {responsive: true}});
})();
</script>
