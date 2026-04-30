<?php
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$basePath = $basePath ?? '';
$activeTab = $activeTab ?? 'reports';
$searchQ = $searchQ ?? '';
$tabUrl = function (string $t) use ($basePath, $searchQ) {
    $u = $basePath . '/reports?tab=' . urlencode($t);
    if ($searchQ !== '') {
        $u .= '&q=' . urlencode($searchQ);
    }
    return $u;
};
function rpt_fw_badge(string $auth): string {
    $a = trim($auth);
    if (stripos($a, 'RBI') !== false) {
        return 'RBI';
    }
    if (stripos($a, 'NHB') !== false) {
        return 'NHB';
    }
    if (stripos($a, 'Internal') !== false) {
        return 'Internal Policy';
    }
    return strlen($a) > 24 ? substr($a, 0, 22) . '…' : $a;
}
function rpt_priority_class(string $p): string {
    if ($p === 'critical') {
        return 'rpt-pill rpt-pill-critical';
    }
    if ($p === 'high') {
        return 'rpt-pill rpt-pill-high';
    }
    if ($p === 'medium') {
        return 'rpt-pill rpt-pill-medium';
    }
    return 'rpt-pill rpt-pill-low';
}
function rpt_risk_class(string $r): string {
    if ($r === 'critical') {
        return 'rpt-pill rpt-pill-critical';
    }
    if ($r === 'high') {
        return 'rpt-pill rpt-pill-high';
    }
    if ($r === 'medium') {
        return 'rpt-pill rpt-pill-medium';
    }
    return 'rpt-pill rpt-pill-low';
}
function rpt_file_icon(string $fileName): string {
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (in_array($ext, ['xlsx', 'xls'], true)) {
        return '<i class="far fa-file-excel text-success rpt-doc-ico"></i> ';
    }
    if ($ext === 'pdf') {
        return '<i class="far fa-file-pdf text-danger rpt-doc-ico"></i> ';
    }
    if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
        return '<i class="far fa-file-image text-primary rpt-doc-ico"></i> ';
    }
    if (in_array($ext, ['doc', 'docx'], true)) {
        return '<i class="far fa-file-word text-info rpt-doc-ico"></i> ';
    }

    return '<i class="far fa-file-alt text-secondary rpt-doc-ico"></i> ';
}
function rpt_doc_status(string $st, int $id): string {
    if ($st === 'approved') {
        return '<span class="rpt-pill rpt-pill-approved">Approved</span>';
    }
    if ($st === 'rejected') {
        return '<span class="rpt-pill rpt-pill-review">Rejected</span>';
    }
    return ($id % 2 === 0)
        ? '<span class="rpt-pill rpt-pill-uploaded">Uploaded</span>'
        : '<span class="rpt-pill rpt-pill-review">Under Review</span>';
}
$fw  = $frameworkCounts ?? ['RBI' => 0, 'NHB' => 0, 'SEBI' => 0, 'Internal' => 0];
$sb  = $statusBuckets ?? ['completed' => 0, 'pending' => 0, 'under_review' => 0, 'overdue' => 0];
$sba = $statusByAuthority ?? [
    'RBI'      => ['completed' => 0, 'pending' => 0, 'under_review' => 0, 'overdue' => 0],
    'NHB'      => ['completed' => 0, 'pending' => 0, 'under_review' => 0, 'overdue' => 0],
    'SEBI'     => ['completed' => 0, 'pending' => 0, 'under_review' => 0, 'overdue' => 0],
    'IRDAI'    => ['completed' => 0, 'pending' => 0, 'under_review' => 0, 'overdue' => 0],
    'Internal' => ['completed' => 0, 'pending' => 0, 'under_review' => 0, 'overdue' => 0],
];
?>
<div class="rpt-page">
    <div class="rpt-header-row">
        <div>
            <h1 class="page-title">Reports &amp; Analytics</h1>
            <p class="page-subtitle mb-0">View compliance reports and analytics</p>
        </div>
        <form method="get" action="<?= htmlspecialchars($basePath) ?>/reports" class="rpt-search-form">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
            <input type="search" name="q" class="form-control rpt-search-input" placeholder="Search compliance items..." value="<?= htmlspecialchars($searchQ) ?>">
        </form>
    </div>

    <nav class="rpt-tabs">
        <a href="<?= htmlspecialchars($tabUrl('reports')) ?>" class="rpt-tab <?= $activeTab === 'reports' ? 'active' : '' ?>"><i class="fas fa-table"></i> Reports Dashboard</a>
        <a href="<?= htmlspecialchars($tabUrl('overview')) ?>" class="rpt-tab <?= $activeTab === 'overview' ? 'active' : '' ?>"><i class="fas fa-th-large"></i> Overview</a>
        <a href="<?= htmlspecialchars($tabUrl('recent')) ?>" class="rpt-tab <?= $activeTab === 'recent' ? 'active' : '' ?>"><i class="fas fa-folder-open"></i> Recent Documents</a>
        <a href="<?= htmlspecialchars($tabUrl('missing')) ?>" class="rpt-tab <?= $activeTab === 'missing' ? 'active' : '' ?>"><i class="fas fa-exclamation-circle"></i> Missing / Pending</a>
    </nav>

    <?php if ($flashSuccess): ?><div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

    <?php if ($activeTab === 'overview'): ?>
    <div class="rpt-kpi-row">
        <div class="rpt-kpi rpt-kpi-green">
            <div class="rpt-kpi-icon"><i class="fas fa-chart-line"></i></div>
            <div>
                <div class="rpt-kpi-val"><?= (int)($kpiCompletion ?? 0) ?>%</div>
                <div class="rpt-kpi-lbl">Completion Rate</div>
            </div>
        </div>
        <div class="rpt-kpi rpt-kpi-red">
            <div class="rpt-kpi-icon"><i class="fas fa-clock"></i></div>
            <div>
                <div class="rpt-kpi-val"><?= (int)($kpiOverdue ?? 0) ?></div>
                <div class="rpt-kpi-lbl">Overdue Items</div>
            </div>
        </div>
        <div class="rpt-kpi rpt-kpi-orange">
            <div class="rpt-kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div>
                <div class="rpt-kpi-val"><?= (int)($kpiHighRisk ?? 0) ?></div>
                <div class="rpt-kpi-lbl">High Risk Items</div>
            </div>
        </div>
        <div class="rpt-kpi rpt-kpi-doc">
            <div class="rpt-kpi-icon"><i class="fas fa-file-alt"></i></div>
            <div>
                <div class="rpt-kpi-val"><?= (int)($kpiDocuments ?? 0) ?></div>
                <div class="rpt-kpi-lbl">Total Documents</div>
            </div>
        </div>
    </div>
    <?php
    $authMeta = [
        'RBI'      => ['label' => 'RBI',             'icon' => 'fa-landmark',      'color' => '#dc2626', 'gradient' => 'linear-gradient(135deg,#fef2f2,#fff)'],
        'NHB'      => ['label' => 'NHB',             'icon' => 'fa-home',          'color' => '#2563eb', 'gradient' => 'linear-gradient(135deg,#eff6ff,#fff)'],
        'SEBI'     => ['label' => 'SEBI',            'icon' => 'fa-chart-bar',     'color' => '#d97706', 'gradient' => 'linear-gradient(135deg,#fffbeb,#fff)'],
        'IRDAI'    => ['label' => 'IRDAI',           'icon' => 'fa-shield-alt',    'color' => '#7c3aed', 'gradient' => 'linear-gradient(135deg,#f5f3ff,#fff)'],
        'Internal' => ['label' => 'Internal Policy', 'icon' => 'fa-file-contract', 'color' => '#059669', 'gradient' => 'linear-gradient(135deg,#ecfdf5,#fff)'],
    ];
    $authKeys  = array_keys($authMeta);
    $totalAuth = count($authKeys);
    ?>
    <div class="rpt-authority-pies-grid">
        <?php foreach ($authMeta as $authKey => $meta):
            $idx        = array_search($authKey, $authKeys);
            $isLast     = ($idx === $totalAuth - 1);
            $isOddLast  = $isLast && ($totalAuth % 2 !== 0);
            $d          = $sba[$authKey] ?? ['completed' => 0, 'pending' => 0, 'under_review' => 0, 'overdue' => 0];
            $total_auth = (int)($fw[$authKey] ?? array_sum($d));
            $canvasId   = 'rpt-pie-' . strtolower(str_replace(' ', '-', $authKey));
        ?>
        <div class="rpt-auth-pie-block<?= $isOddLast ? ' rpt-auth-pie-block-center' : '' ?>">
            <div class="rpt-authority-pie-card">
                <!-- Top: icon + label + total -->
                <div class="rpt-pie-top">
                    <div class="rpt-pie-top-icon" style="background:<?= $meta['color'] ?>18;color:<?= $meta['color'] ?>;">
                        <i class="fas <?= $meta['icon'] ?>"></i>
                    </div>
                    <div class="rpt-pie-top-info">
                        <div class="rpt-pie-top-name"><?= htmlspecialchars($meta['label']) ?></div>
                        <div class="rpt-pie-top-total"><span style="color:<?= $meta['color'] ?>;font-weight:800;"><?= $total_auth ?></span> Total</div>
                    </div>
                </div>
                <!-- Donut -->
                <div class="rpt-pie-canvas-wrap">
                    <canvas id="<?= $canvasId ?>"></canvas>
                    <div class="rpt-pie-center-text">
                        <span class="rpt-pie-center-num" style="color:<?= $meta['color'] ?>;"><?= $total_auth ?></span>
                        <span class="rpt-pie-center-lbl">Total</span>
                    </div>
                </div>
                <!-- Legend -->
                <div class="rpt-pie-legend-v2">
                    <div class="rpt-pie-leg-item"><span class="rpt-pie-leg-dot" style="background:#059669;"></span><span class="rpt-pie-leg-label">Completed</span><span class="rpt-pie-leg-val"><?= $d['completed'] ?></span></div>
                    <div class="rpt-pie-leg-item"><span class="rpt-pie-leg-dot" style="background:#f59e0b;"></span><span class="rpt-pie-leg-label">Pending</span><span class="rpt-pie-leg-val"><?= $d['pending'] ?></span></div>
                    <div class="rpt-pie-leg-item"><span class="rpt-pie-leg-dot" style="background:#3b82f6;"></span><span class="rpt-pie-leg-label">Under Review</span><span class="rpt-pie-leg-val"><?= $d['under_review'] ?></span></div>
                    <div class="rpt-pie-leg-item"><span class="rpt-pie-leg-dot" style="background:#ef4444;"></span><span class="rpt-pie-leg-label">Overdue</span><span class="rpt-pie-leg-val"><?= $d['overdue'] ?></span></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="rpt-export-row">
        <a href="<?= htmlspecialchars($basePath) ?>/reports/export?format=csv" class="btn btn-secondary"><i class="fas fa-file-excel"></i> Export to Excel</a>
        <a href="<?= htmlspecialchars($basePath) ?>/reports/export?format=pdf" class="btn btn-secondary" target="_blank"><i class="fas fa-file-pdf"></i> Export to PDF</a>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
    (function(){
        var pieColors  = ['#059669','#f59e0b','#3b82f6','#dc2626'];
        var pieHovers  = ['#047857','#d97706','#2563eb','#b91c1c'];
        var pieLabels  = ['Completed','Pending','Under Review','Overdue'];
        var charts     = [
            <?php foreach ($authMeta as $authKey => $meta):
                $d = $sba[$authKey] ?? ['completed'=>0,'pending'=>0,'under_review'=>0,'overdue'=>0];
                $canvasId = 'rpt-pie-' . strtolower(str_replace(' ','-',$authKey));
            ?>
            { id:'<?= $canvasId ?>', data:[<?= (int)$d['completed'] ?>,<?= (int)$d['pending'] ?>,<?= (int)$d['under_review'] ?>,<?= (int)$d['overdue'] ?>], color:'<?= $meta['color'] ?>' },
            <?php endforeach; ?>
        ];

        charts.forEach(function(c){
            var canvas = document.getElementById(c.id);
            if (!canvas) return;
            var total = c.data.reduce(function(a,b){ return a+b; }, 0);
            var isEmpty = total === 0;
            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: pieLabels,
                    datasets: [{
                        data: isEmpty ? [1] : c.data,
                        backgroundColor: isEmpty ? ['#f1f5f9'] : pieColors,
                        hoverBackgroundColor: isEmpty ? ['#e2e8f0'] : pieHovers,
                        borderWidth: 3,
                        borderColor: '#fff',
                        hoverOffset: isEmpty ? 0 : 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    animation: { animateRotate: true, duration: 800 },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            enabled: !isEmpty,
                            callbacks: {
                                label: function(ctx){
                                    var val = ctx.raw;
                                    var pct = total > 0 ? Math.round(val/total*100) : 0;
                                    return ' ' + ctx.label + ': ' + val + ' (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
        });
    })();
    </script>

    <?php elseif ($activeTab === 'recent'): ?>
    <div class="card">
        <h3 class="card-title">Recent Documents</h3>
        <p class="text-muted text-sm">All uploaded compliance documents</p>
        <div class="table-wrap mt-3">
            <table class="data-table rpt-table">
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>Framework</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Risk</th>
                        <th>Due Date</th>
                        <th>Uploaded On</th>
                        <th>Owner</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentDocs ?? [] as $d):
                        $fwl = rpt_fw_badge($d['authority_name'] ?? '');
                        $type = trim($d['doc_kind'] ?? '') ?: (trim($d['evidence_type'] ?? '') ?: 'Supporting');
                        if (stripos($type, 'evidence') !== false) {
                            $type = 'Evidence';
                        } elseif (stripos($type, 'return') !== false) {
                            $type = 'Return';
                        } elseif ($type === '' || $type === 'Supporting') {
                            $type = 'Supporting';
                        }
                    ?>
                    <tr>
                        <td><?= rpt_file_icon($d['file_name'] ?? '') ?><?= htmlspecialchars($d['file_name']) ?></td>
                        <td><span class="rpt-fw-pill"><?= htmlspecialchars($fwl) ?></span></td>
                        <td><?= htmlspecialchars($type) ?></td>
                        <td><?= rpt_doc_status($d['doc_status'] ?? 'pending', (int)$d['id']) ?></td>
                        <td><span class="<?= rpt_risk_class($d['risk_level'] ?? 'medium') ?>"><?= htmlspecialchars(ucfirst($d['risk_level'] ?? 'medium')) ?></span></td>
                        <td><?= !empty($d['due_date']) ? date('M j, Y', strtotime($d['due_date'])) : '—' ?></td>
                        <td><?= !empty($d['uploaded_at']) ? date('M j, Y', strtotime($d['uploaded_at'])) : '—' ?></td>
                        <td><?= htmlspecialchars($d['owner_name'] ?? '') ?></td>
                        <td class="rpt-actions">
                            <a href="<?= htmlspecialchars($basePath) ?>/compliance/view/<?= (int)$d['compliance_id'] ?>" class="rpt-icon-btn" title="View"><i class="fas fa-eye"></i></a>
                            <a href="<?= htmlspecialchars($basePath) ?>/reports/document/<?= (int)$d['id'] ?>" class="rpt-icon-btn" title="Download"><i class="fas fa-download"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentDocs)): ?>
                    <tr><td colspan="9" class="text-muted">No documents uploaded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($activeTab === 'missing'): ?>
    <div class="card">
        <h3 class="card-title">Missing / Pending Documents</h3>
        <p class="text-muted text-sm">Compliance items that still require document uploads</p>
        <div class="table-wrap mt-3">
            <table class="data-table rpt-table">
                <thead>
                    <tr>
                        <th>Compliance Name</th>
                        <th>Required Document</th>
                        <th>Framework</th>
                        <th>Due Date</th>
                        <th>Delay Days</th>
                        <th>Priority</th>
                        <th>Owner</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($missingRows ?? [] as $m):
                        $req = trim($m['evidence_type'] ?? '') ?: 'Supporting Documentation';
                        $due = $m['due_date'] ?? null;
                        $ts = $due ? strtotime($due) : null;
                        $late = $ts && $ts < strtotime('today');
                        $days = $late ? (int) floor((strtotime('today') - $ts) / 86400) : 0;
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($m['title']) ?></strong></td>
                        <td><?= htmlspecialchars($req) ?></td>
                        <td><span class="rpt-fw-pill"><?= htmlspecialchars(rpt_fw_badge($m['authority_name'] ?? '')) ?></span></td>
                        <td><?= $due ? date('M j, Y', strtotime($due)) : '—' ?></td>
                        <td><?php if ($late): ?><span class="rpt-delay-late"><?= $days ?> days</span><?php else: ?><span class="rpt-delay-ok">On track</span><?php endif; ?></td>
                        <td><span class="<?= rpt_priority_class($m['priority'] ?? 'medium') ?>"><?= htmlspecialchars(ucfirst($m['priority'] ?? 'medium')) ?></span></td>
                        <td><?= htmlspecialchars($m['owner_name'] ?? '') ?></td>
                        <td><a href="<?= htmlspecialchars($basePath) ?>/compliance/view/<?= (int)$m['id'] ?>?tab=checklist" class="btn btn-sm btn-primary"><i class="fas fa-upload"></i> Upload</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($missingRows)): ?>
                    <tr><td colspan="8" class="text-muted">No missing documents — all required items have uploads, or adjust filters.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($activeTab === 'reports'): ?>
    <div class="card mt-3">
        <h3 class="card-title">Date-wise Report Run Summary</h3>
        <p class="text-muted text-sm">Use each row's date range filter to download only that specific report for the selected period.</p>
        <div class="table-wrap mt-3">
            <table class="data-table rpt-table">
                <thead>
                    <tr>
                        <th>Report Name</th>
                        <th>Description</th>
                        <th>Date Range</th>
                        <th>Records</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($runtimeRows ?? []) as $rr): ?>
                    <?php
                    $reportKey = (string)($rr['key'] ?? 'main_report');
                    $rowFormId = 'report-range-form-' . preg_replace('/[^a-z0-9\-_]/i', '-', $reportKey);
                    $defaultRecords = (int)($rr['records'] ?? 0);
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars((string)($rr['name'] ?? 'Report')) ?></strong></td>
                        <td><?= htmlspecialchars((string)($rr['description'] ?? '')) ?></td>
                        <td>
                            <form id="<?= htmlspecialchars($rowFormId) ?>" method="get" action="<?= htmlspecialchars($basePath) ?>/reports/dashboard-export" class="rpt-row-range-form">
                                <input type="hidden" name="tab" value="reports">
                                <input type="hidden" name="report_focus" value="<?= htmlspecialchars($reportKey) ?>">
                                <input type="hidden" name="report" value="<?= htmlspecialchars($reportKey) ?>">
                                <input type="date" name="from" class="form-control rpt-row-date-input" value="<?= htmlspecialchars((string)($effectiveFrom ?? '')) ?>" max="<?= htmlspecialchars(\App\Core\MailIstTime::todayYmd()) ?>">
                                <span class="text-muted rpt-row-to-text">to</span>
                                <input type="date" name="to" class="form-control rpt-row-date-input" value="<?= htmlspecialchars((string)($effectiveTo ?? '')) ?>" max="<?= htmlspecialchars(\App\Core\MailIstTime::todayYmd()) ?>">
                            </form>
                        </td>
                        <td data-record-cell="<?= htmlspecialchars($rowFormId) ?>" data-default-records="<?= $defaultRecords ?>"><?= $defaultRecords ?></td>
                        <td class="rpt-row-actions-cell">
                            <div class="rpt-row-actions">
                                <button type="button" class="btn btn-sm btn-primary js-report-apply" data-form-id="<?= htmlspecialchars($rowFormId) ?>">Apply Filter</button>
                                <button
                                    type="submit"
                                    form="<?= htmlspecialchars($rowFormId) ?>"
                                    formaction="<?= htmlspecialchars($basePath) ?>/reports/dashboard-export"
                                    formmethod="get"
                                    name="format"
                                    value="csv"
                                    class="btn btn-sm btn-secondary rpt-row-download-btn">Download CSV</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary js-report-reset" data-form-id="<?= htmlspecialchars($rowFormId) ?>">Reset</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($runtimeRows ?? [])): ?>
                    <tr><td colspan="5" class="text-muted">No report summary rows available.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    (function() {
        function getRecordCell(formId) {
            return document.querySelector('[data-record-cell="' + formId + '"]');
        }
        document.querySelectorAll('.js-report-apply').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var formId = this.getAttribute('data-form-id');
                var form = document.getElementById(formId);
                if (!form) return;
                var params = new URLSearchParams(new FormData(form));
                params.set('format', 'count');
                this.disabled = true;
                this.textContent = 'Applying...';
                fetch(form.getAttribute('action') + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        var cell = getRecordCell(formId);
                        if (cell && data && data.ok) {
                            cell.textContent = String(data.count || 0);
                        }
                    })
                    .catch(function() {})
                    .finally(() => {
                        btn.disabled = false;
                        btn.textContent = 'Apply Filter';
                    });
            });
        });
        document.querySelectorAll('.js-report-reset').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var formId = this.getAttribute('data-form-id');
                var form = document.getElementById(formId);
                if (!form) return;
                var from = form.querySelector('input[name="from"]');
                var to = form.querySelector('input[name="to"]');
                if (from) from.value = '';
                if (to) to.value = '';
                var cell = getRecordCell(formId);
                if (cell) {
                    cell.textContent = cell.getAttribute('data-default-records') || '0';
                }
            });
        });
    })();
    </script>

    <?php endif; ?>
</div>
