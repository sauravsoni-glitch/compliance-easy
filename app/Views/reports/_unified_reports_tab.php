<?php
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
$selectedDept = $selectedDept ?? '';
$selectedUserName = $selectedUserName ?? '';
$deptPerfLabels = $deptPerfLabels ?? [];
$deptPerfValues = $deptPerfValues ?? [];
$userPerfLabels = $userPerfLabels ?? [];
$userPerfValues = $userPerfValues ?? [];
$deptTrendSets = $deptTrendSets ?? [];
$userTrendSets = $userTrendSets ?? [];
$singleDeptMode = $singleDeptMode ?? false;
$singleUserMode = $singleUserMode ?? false;
$penaltyLabels = $penaltyLabels ?? [];
$penaltyValues = $penaltyValues ?? [];
$runtimeRows = $runtimeRows ?? [];
$effectiveFrom = $effectiveFrom ?? '';
$effectiveTo = $effectiveTo ?? '';
$queryWithoutDrill = $_GET;
unset($queryWithoutDrill['drill']);
$baseQuery = http_build_query($queryWithoutDrill);
$detailBase = ($basePath ?? '') . '/reports' . ($baseQuery !== '' ? ('?' . $baseQuery . '&') : '?');
?>
<style>
.u-grid-4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.u-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}
.u-kpi-link{text-decoration:none;color:inherit}.u-kpi-title{font-size:.8rem;color:#64748b}.u-kpi-value{font-size:1.5rem;font-weight:700}
.u-dashboard-glass{background:linear-gradient(180deg,#ffffff,#f8fbff);border:1px solid #e7eef7;box-shadow:0 8px 24px rgba(16,24,40,.06)}
.u-soft-card{border-radius:14px;transition:transform .24s ease, box-shadow .24s ease, border-color .24s ease}
.u-soft-card:hover{transform:translateY(-3px);box-shadow:0 14px 28px rgba(2,132,199,.12);border-color:#bfdbfe}
.u-accent-bar{position:relative;overflow:hidden}
.u-accent-bar:before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:linear-gradient(180deg,#3b82f6,#06b6d4)}
.u-title-glow{background:linear-gradient(90deg,#0f172a,#1d4ed8);-webkit-background-clip:text;background-clip:text;color:transparent}
.u-anim{opacity:0;transform:translateY(12px);transition:opacity .45s ease, transform .45s ease}
.u-anim.in{opacity:1;transform:translateY(0)}
.u-table-polish thead th{background:#f8fafc;position:sticky;top:0;z-index:1}
.u-table-polish tbody tr:hover{background:#f8fbff}
.u-pill{display:inline-block;padding:.15rem .5rem;border-radius:999px;font-size:.75rem;background:#e0f2fe;color:#075985}
@media(max-width:1200px){.u-grid-4,.u-grid-3{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
<div class="card u-dashboard-glass u-soft-card u-anim">
    <h3 class="card-title u-title-glow">Reports Dashboard</h3>
    <form method="get" action="<?= htmlspecialchars($basePath) ?>/reports" class="rpt-search-form" style="display:grid;gap:.5rem;grid-template-columns:repeat(4,minmax(0,1fr));margin-top:.75rem;position:sticky;top:8px;background:#fff;z-index:5;padding:.5rem;border-radius:10px;">
        <input type="hidden" name="tab" value="reports">
        <input type="date" name="from" class="form-control" value="<?= htmlspecialchars((string)($filters['from'] ?? '')) ?>" max="<?= htmlspecialchars(date('Y-m-d')) ?>">
        <input type="date" name="to" class="form-control" value="<?= htmlspecialchars((string)($filters['to'] ?? '')) ?>">
        <select name="period" class="form-control"><option value="">Custom Range</option><option value="6m" <?= (($filters['period'] ?? '') === '6m') ? 'selected' : '' ?>>Last 6 Months</option><option value="1y" <?= (($filters['period'] ?? '') === '1y') ? 'selected' : '' ?>>Last 1 Year</option></select>
        <div style="display:flex;gap:.5rem;">
            <button type="submit" class="btn btn-primary">Apply Filter</button>
            <a href="<?= htmlspecialchars($basePath) ?>/reports?tab=reports" class="btn btn-secondary">Reset</a>
            <a href="<?= htmlspecialchars($basePath) ?>/reports/dashboard-export?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['tab' => 'reports', 'format' => 'csv']))) ?>" class="btn btn-secondary">Export Selected Range</a>
        </div>
    </form>
</div>
<div class="card u-dashboard-glass u-soft-card u-anim">
    <h3 class="card-title">Date-wise Report Run Summary</h3>
    <p class="text-muted text-sm">Showing reports for <strong><?= htmlspecialchars($effectiveFrom !== '' ? $effectiveFrom : 'all dates') ?></strong> to <strong><?= htmlspecialchars($effectiveTo !== '' ? $effectiveTo : 'all dates') ?></strong>.</p>
    <div class="table-wrap mt-3">
        <table class="data-table rpt-table u-table-polish">
            <thead>
                <tr>
                    <th>Report Name</th>
                    <th>From Date</th>
                    <th>To Date</th>
                    <th>Records</th>
                    <th>Description</th>
                    <th>Download</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($runtimeRows as $rr): ?>
                <?php
                    $downloadQuery = array_merge($_GET, [
                        'tab' => 'reports',
                        'format' => 'csv',
                        'report' => (string)($rr['key'] ?? 'main_report'),
                    ]);
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars((string)$rr['name']) ?></strong></td>
                    <td><?= htmlspecialchars($effectiveFrom !== '' ? $effectiveFrom : 'All') ?></td>
                    <td><?= htmlspecialchars($effectiveTo !== '' ? $effectiveTo : 'All') ?></td>
                    <td><?= (int)($rr['records'] ?? 0) ?></td>
                    <td><?= htmlspecialchars((string)($rr['description'] ?? '')) ?></td>
                    <td><a class="btn btn-sm btn-secondary" href="<?= htmlspecialchars($basePath) ?>/reports/dashboard-export?<?= htmlspecialchars(http_build_query($downloadQuery)) ?>">Download CSV</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($runtimeRows)): ?>
                <tr><td colspan="6" class="text-muted">No report summary rows available.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="u-grid-4">
    <a href="<?= htmlspecialchars($detailBase . 'drill=total#main-report') ?>" class="card u-kpi-link u-soft-card u-accent-bar u-anim"><div class="u-kpi-title">Total Compliances</div><div class="u-kpi-value"><?= (int)($summary['total'] ?? 0) ?></div></a>
    <a href="<?= htmlspecialchars($detailBase . 'drill=completed#main-report') ?>" class="card u-kpi-link u-soft-card u-accent-bar u-anim"><div class="u-kpi-title">Completed</div><div class="u-kpi-value" style="color:#15803d;"><?= (int)($summary['completed'] ?? 0) ?></div></a>
    <a href="<?= htmlspecialchars($detailBase . 'drill=pending#main-report') ?>" class="card u-kpi-link u-soft-card u-accent-bar u-anim"><div class="u-kpi-title">Pending</div><div class="u-kpi-value" style="color:#b45309;"><?= (int)($summary['pending'] ?? 0) ?></div></a>
    <a href="<?= htmlspecialchars($detailBase . 'drill=overdue#main-report') ?>" class="card u-kpi-link u-soft-card u-accent-bar u-anim"><div class="u-kpi-title">Overdue</div><div class="u-kpi-value" style="color:#b91c1c;"><?= (int)($summary['overdue'] ?? 0) ?></div></a>
    <a href="<?= htmlspecialchars($detailBase . 'drill=high_risk#main-report') ?>" class="card u-kpi-link u-soft-card u-accent-bar u-anim"><div class="u-kpi-title">High Risk</div><div class="u-kpi-value"><?= (int)($summary['high_risk'] ?? 0) ?></div></a>
    <a href="<?= htmlspecialchars($detailBase . 'drill=escalated#main-report') ?>" class="card u-kpi-link u-soft-card u-accent-bar u-anim"><div class="u-kpi-title">Escalated</div><div class="u-kpi-value"><?= (int)($summary['escalated'] ?? 0) ?></div></a>
    <div class="card u-soft-card u-accent-bar u-anim"><div class="u-kpi-title">Penalty Cases</div><div class="u-kpi-value"><?= array_sum(array_map('intval', $penaltyValues)) ?></div></div>
    <div class="card u-soft-card u-accent-bar u-anim"><div class="u-kpi-title">Selected Period</div><div class="u-kpi-value" style="font-size:1rem;"><?= htmlspecialchars(($filters['period'] ?? '') === '1y' ? 'Last 1 Year' : (($filters['period'] ?? '') === '6m' ? 'Last 6 Months' : 'Custom')) ?></div></div>
</div>
<div class="u-grid-3">
    <div class="card u-soft-card u-anim"><h4>Compliance Trend (Date-wise)</h4><canvas id="tabTrendChart" height="100"></canvas></div>
    <div class="card u-soft-card u-anim"><h4>Overdue vs Completed</h4><canvas id="tabStatusChart" height="100"></canvas></div>
    <div class="card u-soft-card u-anim"><h4>Risk Distribution</h4><canvas id="tabRiskChart" height="100"></canvas></div>
    <div class="card u-soft-card u-anim"><h4><?= $singleDeptMode ? ('Department Performance Trend: ' . htmlspecialchars($selectedDept ?: 'N/A')) : 'Department Performance Trend (All Departments)' ?></h4><canvas id="tabDeptPerfChart" height="100"></canvas></div>
    <div class="card u-soft-card u-anim"><h4><?= $singleUserMode ? ('User Performance Trend: ' . htmlspecialchars($selectedUserName ?: 'N/A')) : 'User Performance Trend (All Users)' ?></h4><canvas id="tabUserPerfChart" height="100"></canvas></div>
    <div class="card u-soft-card u-anim"><h4>Penalties by Month</h4><canvas id="tabPenaltyChart" height="100"></canvas></div>
</div>
<div id="main-report" class="card u-soft-card u-anim">
    <h3 class="card-title">Main Report</h3>
    <div class="table-wrap mt-3">
        <table class="data-table rpt-table u-table-polish"><thead><tr><th>Compliance Title</th><th>Department</th><th>Assigned User</th><th>Due Date</th><th>Completion Date</th><th>Status</th><th>Delay (Days)</th><th>Risk</th><th>Escalation</th><th>Action</th></tr></thead><tbody>
        <?php foreach ($mainRows as $r): ?><tr><td><?= htmlspecialchars((string)$r['title']) ?></td><td><?= htmlspecialchars((string)($r['department'] ?? '—')) ?></td><td><?= htmlspecialchars((string)($r['owner_name'] ?? 'Unassigned')) ?></td><td><?= htmlspecialchars((string)($r['due_date'] ?? '—')) ?></td><td><?= htmlspecialchars((string)($r['completion_date'] ?? '—')) ?></td><td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string)($r['status'] ?? 'pending')))) ?></td><td><?= ($r['delay_days'] === null) ? '—' : (int)$r['delay_days'] ?></td><td><?= htmlspecialchars(ucfirst((string)($r['risk_level'] ?? 'low'))) ?></td><td><?= htmlspecialchars((string)($r['escalation_level'] ?? '—')) ?></td><td><a href="<?= htmlspecialchars($basePath) ?>/compliance/view/<?= (int)$r['id'] ?>" class="btn btn-sm btn-primary">View</a></td></tr><?php endforeach; ?>
        <?php if (empty($mainRows)): ?><tr><td colspan="10" class="text-muted">No rows found.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</div>
<div class="u-grid-3">
<div class="card u-soft-card u-anim" style="grid-column:span 2;">
    <h3 class="card-title">Department Performance Panel</h3>
    <div class="table-wrap mt-3">
        <table class="data-table rpt-table"><thead><tr><th>Department</th><th>Total Tasks</th><th>Completed</th><th>Pending</th><th>Overdue</th><th>Compliance %</th><th>Avg Delay</th></tr></thead><tbody>
            <?php foreach ($departmentPerf as $d): ?>
                <tr><td><a href="<?= htmlspecialchars($basePath) ?>/reports?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['tab' => 'reports', 'department' => $d['department']]))) ?>#main-report"><?= htmlspecialchars((string)$d['department']) ?></a></td><td><?= (int)$d['total'] ?></td><td><?= (int)$d['completed'] ?></td><td><?= (int)$d['pending'] ?></td><td><?= (int)$d['overdue'] ?></td><td><?= (int)$d['compliance_pct'] ?>%</td><td><?= htmlspecialchars((string)$d['avg_delay']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($departmentPerf)): ?><tr><td colspan="7" class="text-muted">No department rows.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</div>
<div class="card u-soft-card u-anim">
    <h3 class="card-title">User Performance Panel</h3>
    <div class="table-wrap mt-3">
        <table class="data-table rpt-table"><thead><tr><th>User</th><th>Role</th><th>Total</th><th>Completed</th><th>Pending</th><th>Overdue</th><th>Performance %</th></tr></thead><tbody>
            <?php foreach ($userPerf as $u): ?>
                <tr><td><a href="<?= htmlspecialchars($basePath) ?>/reports?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['tab' => 'reports', 'user_id' => (int)$u['user_id']]))) ?>#main-report"><?= htmlspecialchars((string)$u['user_name']) ?></a></td><td><?= htmlspecialchars((string)$u['role']) ?></td><td><?= (int)$u['total'] ?></td><td><?= (int)$u['completed'] ?></td><td><?= (int)$u['pending'] ?></td><td><?= (int)$u['overdue'] ?></td><td><?= (int)$u['performance_pct'] ?>%</td></tr>
            <?php endforeach; ?>
            <?php if (empty($userPerf)): ?><tr><td colspan="7" class="text-muted">No user rows.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</div>
</div>
<div class="card u-soft-card u-anim">
    <h3 class="card-title">Overdue & Penalty Tracker</h3>
    <div class="table-wrap mt-3">
        <table class="data-table rpt-table"><thead><tr><th>Compliance</th><th>Department</th><th>User</th><th>Due Date</th><th>Days Overdue</th><th>Risk</th><th>Escalation</th><th>Penalty</th></tr></thead><tbody>
        <?php foreach ($overdueRows as $o): ?><tr><td><?= htmlspecialchars((string)$o['title']) ?></td><td><?= htmlspecialchars((string)($o['department'] ?? '—')) ?></td><td><?= htmlspecialchars((string)($o['owner_name'] ?? 'Unassigned')) ?></td><td><?= htmlspecialchars((string)($o['due_date'] ?? '—')) ?></td><td><?= (int)($o['days_overdue'] ?? 0) ?></td><td><?= htmlspecialchars(ucfirst((string)($o['risk_level'] ?? 'low'))) ?></td><td><?= htmlspecialchars((string)($o['escalation_level'] ?? '—')) ?></td><td><?= htmlspecialchars((string)($o['penalty_impact'] ?? '—')) ?></td></tr><?php endforeach; ?>
        <?php if (empty($overdueRows)): ?><tr><td colspan="8" class="text-muted">No overdue rows.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('tabTrendChart'), {type:'line', data:{labels:<?= json_encode(array_values($trendLabels), JSON_UNESCAPED_SLASHES) ?>, datasets:[{label:'Compliances', data:<?= json_encode(array_values($trendValues), JSON_UNESCAPED_SLASHES) ?>, borderColor:'#2563eb', backgroundColor:'rgba(37,99,235,.1)', tension:.3, fill:true}]}, options:{responsive:true, animation:{duration:1100,easing:'easeOutCubic'}, plugins:{legend:{display:false}}}});
new Chart(document.getElementById('tabStatusChart'), {type:'bar', data:{labels:['Overdue','Completed'], datasets:[{data:[<?= (int)$overdueVsCompleted['overdue'] ?>,<?= (int)$overdueVsCompleted['completed'] ?>], backgroundColor:['#ef4444','#22c55e'], borderRadius:8}]}, options:{responsive:true, animation:{duration:900,easing:'easeOutQuart'}, plugins:{legend:{display:false}}}});
new Chart(document.getElementById('tabRiskChart'), {type:'doughnut', data:{labels:['Low','Medium','High','Critical'], datasets:[{data:[<?= (int)$riskDist['low'] ?>,<?= (int)$riskDist['medium'] ?>,<?= (int)$riskDist['high'] ?>,<?= (int)$riskDist['critical'] ?>], backgroundColor:['#22c55e','#f59e0b','#fb7185','#dc2626'], hoverOffset:10}]}, options:{responsive:true, animation:{duration:1100,easing:'easeOutCubic'}}});
const deptLabels = <?= json_encode(array_values($deptPerfLabels), JSON_UNESCAPED_SLASHES) ?>;
const deptSets = <?= json_encode($deptTrendSets, JSON_UNESCAPED_SLASHES) ?>;
const deptPalette = ['#0ea5e9','#ef4444','#10b981','#f59e0b','#8b5cf6','#ec4899','#14b8a6','#6366f1','#84cc16','#f97316','#22c55e','#e11d48'];
new Chart(document.getElementById('tabDeptPerfChart'), {type:'line', data:{labels:deptLabels, datasets:deptSets.map((s, i) => ({label:s.label, data:s.values, borderColor:deptPalette[i % deptPalette.length], backgroundColor:'transparent', tension:.3, fill:false, pointRadius:3}))}, options:{responsive:true, animation:{duration:1200,easing:'easeOutCubic'}, scales:{y:{min:0,max:100}}, plugins:{legend:{display:true, position:'bottom'}}}});
const userLabels = <?= json_encode(array_values($userPerfLabels), JSON_UNESCAPED_SLASHES) ?>;
const userSets = <?= json_encode($userTrendSets, JSON_UNESCAPED_SLASHES) ?>;
const userPalette = ['#7c3aed','#0ea5e9','#ef4444','#10b981','#f59e0b','#ec4899','#14b8a6','#6366f1','#84cc16','#f97316','#22c55e','#e11d48'];
new Chart(document.getElementById('tabUserPerfChart'), {type:'line', data:{labels:userLabels, datasets:userSets.map((s, i) => ({label:s.label, data:s.values, borderColor:userPalette[i % userPalette.length], backgroundColor:'transparent', tension:.3, fill:false, pointRadius:3}))}, options:{responsive:true, animation:{duration:1200,easing:'easeOutCubic'}, scales:{y:{min:0,max:100}}, plugins:{legend:{display:true, position:'bottom'}}}});
new Chart(document.getElementById('tabPenaltyChart'), {type:'bar', data:{labels:<?= json_encode(array_values($penaltyLabels), JSON_UNESCAPED_SLASHES) ?>, datasets:[{label:'Penalties', data:<?= json_encode(array_values($penaltyValues), JSON_UNESCAPED_SLASHES) ?>, backgroundColor:'#f97316', borderRadius:8}]}, options:{responsive:true, animation:{duration:1000,easing:'easeOutQuart'}, plugins:{legend:{display:false}}}});
const animatedBlocks = document.querySelectorAll('.u-anim');
const io = new IntersectionObserver((entries) => {
  entries.forEach((entry, idx) => {
    if (entry.isIntersecting) {
      setTimeout(() => entry.target.classList.add('in'), idx * 40);
      io.unobserve(entry.target);
    }
  });
}, { threshold: 0.1 });
animatedBlocks.forEach(el => io.observe(el));
</script>
