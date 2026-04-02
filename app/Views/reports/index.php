<?php
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$basePath = $basePath ?? '';
$activeTab = $activeTab ?? 'overview';
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
$frameworkChart = $frameworkChart ?? ['labels' => ['RBI', 'NHB', 'Internal Policy'], 'data' => [0, 0, 0]];
$sb = $statusBuckets ?? ['completed' => 0, 'pending' => 0, 'under_review' => 0, 'overdue' => 0];
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
        <a href="<?= htmlspecialchars($tabUrl('overview')) ?>" class="rpt-tab <?= $activeTab === 'overview' ? 'active' : '' ?>"><i class="fas fa-th-large"></i> Overview</a>
        <a href="<?= htmlspecialchars($tabUrl('recent')) ?>" class="rpt-tab <?= $activeTab === 'recent' ? 'active' : '' ?>"><i class="fas fa-folder-open"></i> Recent Documents</a>
        <a href="<?= htmlspecialchars($tabUrl('missing')) ?>" class="rpt-tab <?= $activeTab === 'missing' ? 'active' : '' ?>"><i class="fas fa-exclamation-circle"></i> Missing / Pending</a>
        <a href="<?= htmlspecialchars($tabUrl('upload')) ?>" class="rpt-tab <?= $activeTab === 'upload' ? 'active' : '' ?>"><i class="fas fa-cloud-upload-alt"></i> Quick Upload</a>
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
    <div class="rpt-charts-row">
        <div class="card rpt-chart-card">
            <h3 class="card-title">Compliance by Framework</h3>
            <div class="rpt-chart-wrap"><canvas id="rpt-bar-chart"></canvas></div>
        </div>
        <div class="card rpt-chart-card">
            <h3 class="card-title">Status Distribution</h3>
            <div class="rpt-chart-wrap rpt-donut-wrap"><canvas id="rpt-donut-chart"></canvas></div>
            <div class="rpt-donut-legend">
                <span><i class="rpt-lg rpt-lg-done"></i> Completed</span>
                <span><i class="rpt-lg rpt-lg-pend"></i> Pending</span>
                <span><i class="rpt-lg rpt-lg-rev"></i> Under Review</span>
                <span><i class="rpt-lg rpt-lg-od"></i> Overdue</span>
            </div>
        </div>
    </div>
    <div class="rpt-export-row">
        <a href="<?= htmlspecialchars($basePath) ?>/reports/export?format=csv" class="btn btn-secondary"><i class="fas fa-file-excel"></i> Export to Excel</a>
        <a href="<?= htmlspecialchars($basePath) ?>/reports/export?format=pdf" class="btn btn-secondary" target="_blank"><i class="fas fa-file-pdf"></i> Export to PDF</a>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
    (function(){
        var primary = 'rgb(185, 28, 28)';
        new Chart(document.getElementById('rpt-bar-chart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($frameworkChart['labels'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                datasets: [{ data: <?= json_encode($frameworkChart['data'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, backgroundColor: primary, borderRadius: 6 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { maxRotation: 45, minRotation: 0, autoSkip: true } },
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
        new Chart(document.getElementById('rpt-donut-chart'), {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Pending', 'Under Review', 'Overdue'],
                datasets: [{
                    data: [<?= (int)$sb['completed'] ?>, <?= (int)$sb['pending'] ?>, <?= (int)$sb['under_review'] ?>, <?= (int)$sb['overdue'] ?>],
                    backgroundColor: ['#059669', '#f59e0b', '#3b82f6', '#dc2626'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: { legend: { display: false } }
            }
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

    <?php else: /* upload */ ?>
    <div class="card rpt-upload-card">
        <h3 class="card-title">Quick Upload</h3>
        <p class="text-muted text-sm">Upload documents for compliance items</p>
        <form method="post" action="<?= htmlspecialchars($basePath) ?>/reports/quick-upload" enctype="multipart/form-data" class="rpt-upload-form">
            <div class="form-group">
                <label class="form-label">Select Compliance <span class="text-danger">*</span></label>
                <select name="compliance_id" class="form-control" required>
                    <option value="">Choose compliance item</option>
                    <?php foreach ($uploadComplianceOptions ?? [] as $opt): ?>
                    <option value="<?= (int)$opt['id'] ?>"><?= htmlspecialchars($opt['compliance_code'] . ' — ' . $opt['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Document Type <span class="text-danger">*</span></label>
                <select name="document_type" class="form-control" required>
                    <option value="">Select type</option>
                    <option value="Evidence">Evidence</option>
                    <option value="Supporting">Supporting</option>
                    <option value="Return">Return</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Upload File <span class="text-danger">*</span></label>
                <div class="rpt-dropzone" id="rpt-dropzone">
                    <input type="file" name="file" id="rpt-file" class="rpt-file-input" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                    <i class="fas fa-cloud-upload-alt rpt-drop-ico"></i>
                    <p class="mb-1"><strong>Click to upload</strong> or drag and drop</p>
                    <p class="text-muted text-sm mb-0">PDF, Word, Excel (XLS, XLSX), PNG, JPG — max 10MB</p>
                    <span class="rpt-file-name" id="rpt-file-name"></span>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Upload Comments</label>
                <textarea name="upload_comments" class="form-control" rows="3" placeholder="Add any comments about this upload..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block rpt-upload-submit"><i class="fas fa-upload"></i> Upload Document</button>
        </form>
    </div>
    <script>
    (function(){
        var dz = document.getElementById('rpt-dropzone');
        var fi = document.getElementById('rpt-file');
        var fn = document.getElementById('rpt-file-name');
        if (!dz || !fi) return;
        dz.addEventListener('click', function(e) { if (e.target !== fi) fi.click(); });
        fi.addEventListener('change', function() { fn.textContent = this.files[0] ? this.files[0].name : ''; });
        dz.addEventListener('dragover', function(e) { e.preventDefault(); dz.classList.add('dragover'); });
        dz.addEventListener('dragleave', function() { dz.classList.remove('dragover'); });
        dz.addEventListener('drop', function(e) {
            e.preventDefault();
            dz.classList.remove('dragover');
            if (e.dataTransfer.files.length) { fi.files = e.dataTransfer.files; fn.textContent = fi.files[0].name; }
        });
    })();
    </script>
    <?php endif; ?>
</div>
