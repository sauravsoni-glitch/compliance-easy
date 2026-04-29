<?php
$c = $cards ?? ['total' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'open' => 0];
$items = $items ?? [];
$u = $users ?? [];
$filters = $filters ?? ['q' => '', 'category' => '', 'severity' => '', 'status' => ''];
$activeTab = $activeTab ?? 'identification';
$assessment = $assessment ?? ['inherent_score' => 0, 'residual_score' => 0, 'risk_appetite' => 0];
$controls = $controls ?? [];
$catOpts = $categoryOptions ?? [];
$sevOpts = $severityOptions ?? [];
$statusOpts = $statusOptions ?? [];
$controlCatOpts = $controlCategoryOptions ?? [];
$controlTypeOpts = $controlTypeOptions ?? [];
$controlFreqOpts = $controlFrequencyOptions ?? [];
$kris = $kris ?? [];
$kriFreqOpts = $kriFrequencyOptions ?? [];
$kriStatusOpts = $kriStatusOptions ?? [];
$itDashboard = $itDashboard ?? [];
$modalEnabledTabs = ['identification', 'assessment', 'controls', 'kris'];
$canOpenRiskModal = in_array($activeTab, $modalEnabledTabs, true);
$canOpenIncidentModal = $activeTab === 'incidents';
$canOpenAnomalyModal = $activeTab === 'anomalies';
$canOpenComplianceModal = $activeTab === 'compliance';
$canOpenResilienceModal = $activeTab === 'resilience';
$canOpenLessonsModal = $activeTab === 'lessons';
$incidentRows = $incidentRows ?? [];
$complianceTrackRows = $complianceTrackRows ?? [];
$resilienceRows = $resilienceRows ?? [];
$lessonRows = $lessonRows ?? [];
$uploadHistoryRows = $uploadHistoryRows ?? [
    ['id' => 'UP001', 'file_name' => 'risk_data_oct2023.csv', 'type' => 'Risk Data', 'uploaded_at' => '2023-10-15 10:30', 'records' => '42', 'status' => 'Completed', 'uploaded_by' => 'John Smith'],
    ['id' => 'UP002', 'file_name' => 'control_mapping_q3.xlsx', 'type' => 'Control Data', 'uploaded_at' => '2023-09-28 15:45', 'records' => '87', 'status' => 'Completed', 'uploaded_by' => 'Mary Johnson'],
    ['id' => 'UP003', 'file_name' => 'incident_log_nov.xlsx', 'type' => 'Incident Data', 'uploaded_at' => '2023-11-05 09:15', 'records' => '12', 'status' => 'Completed', 'uploaded_by' => 'Robert Davis'],
    ['id' => 'UP004', 'file_name' => 'kri_metrics_q4.csv', 'type' => 'KRI Data', 'uploaded_at' => '2023-11-10 16:20', 'records' => '35', 'status' => 'Failed', 'uploaded_by' => 'Sarah Wilson'],
    ['id' => 'UP005', 'file_name' => 'compliance_updates_nov.xlsx', 'type' => 'Compliance Data', 'uploaded_at' => '2023-11-15 11:05', 'records' => '28', 'status' => 'Completed', 'uploaded_by' => 'James Brown'],
];
$anomalyRows = $anomalyRows ?? [
    ['id' => 'ANO-001', 'type' => 'Transaction', 'description' => 'Unusual pattern of high-value transaction attempts', 'severity' => 'Critical', 'status' => 'Open', 'confidence' => 'High', 'detected_on' => '2023-11-15 10:30'],
    ['id' => 'ANO-002', 'type' => 'System Access', 'description' => 'Multiple failed login attempts from unusual locations', 'severity' => 'Critical', 'status' => 'Investigating', 'confidence' => 'High', 'detected_on' => '2023-11-14 23:15'],
    ['id' => 'ANO-003', 'type' => 'Customer Behavior', 'description' => 'Sudden increase in transaction volume for flagged segment', 'severity' => 'Medium', 'status' => 'Resolved', 'confidence' => 'Medium', 'detected_on' => '2023-11-13 14:45'],
    ['id' => 'ANO-004', 'type' => 'Transaction', 'description' => 'Pattern of small transactions followed by large withdrawal', 'severity' => 'High', 'status' => 'Open', 'confidence' => 'Medium', 'detected_on' => '2023-11-12 08:20'],
    ['id' => 'ANO-005', 'type' => 'Process', 'description' => 'KYC documentation completion rate significantly dropped', 'severity' => 'Medium', 'status' => 'Investigating', 'confidence' => 'High', 'detected_on' => '2023-11-11 16:10'],
];
$compliances = $compliances ?? [];
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<div class="page-header" style="margin-bottom:0.5rem;">
    <div>
        <h1 class="page-title"><?= $activeTab === 'it-dashboard' ? 'IT Dashboard' : ($activeTab === 'incidents' ? 'Incident Management' : ($activeTab === 'anomalies' ? 'Anomaly Detection' : ($activeTab === 'compliance' ? 'Compliance Tracking' : ($activeTab === 'resilience' ? 'Resilience Management' : ($activeTab === 'lessons' ? 'Lessons Learned' : ($activeTab === 'upload' ? 'Data Upload' : 'IT Risk')))))) ?></h1>
        <p class="page-subtitle"><?= $activeTab === 'it-dashboard' ? 'Monitor IT compliance, risk posture, controls, and KRIs from one place.' : ($activeTab === 'incidents' ? 'Track, manage, and report operational incidents.' : ($activeTab === 'anomalies' ? 'AI-powered detection of unusual patterns that might indicate risks' : ($activeTab === 'compliance' ? 'Monitor and manage compliance with regulatory requirements' : ($activeTab === 'resilience' ? 'Build and monitor organizational resilience to operational risks' : ($activeTab === 'lessons' ? 'Capture key findings to improve controls and prevent recurrence' : ($activeTab === 'upload' ? 'Upload and manage operational risk data in bulk' : 'Identify and classify IT/InfoSec risks across the organization.')))))) ?></p>
    </div>
    <?php if ($canOpenLessonsModal): ?>
    <button type="button" class="btn btn-primary" id="open-lesson-modal"><i class="fas fa-lightbulb"></i> Add Lesson</button>
    <?php elseif ($activeTab === 'upload'): ?>
    <button type="button" class="btn btn-primary js-itrisk-upload-trigger"><i class="fas fa-upload"></i> Upload Data</button>
    <?php elseif ($canOpenComplianceModal): ?>
    <button type="button" class="btn btn-primary" id="open-compliance-modal"><i class="fas fa-clipboard-check"></i> Add Requirement</button>
    <?php elseif ($canOpenResilienceModal): ?>
    <button type="button" class="btn btn-primary" id="open-resilience-modal"><i class="fas fa-shield-alt"></i> Add Plan</button>
    <?php elseif ($canOpenIncidentModal): ?>
    <button type="button" class="btn btn-primary" id="open-incident-modal"><i class="fas fa-flag"></i> Report Incident</button>
    <?php elseif ($canOpenAnomalyModal): ?>
    <button type="button" class="btn btn-primary" id="open-anomaly-modal"><i class="fas fa-plus"></i> Add Anomaly</button>
    <?php endif; ?>
</div>
<?php if ($flashSuccess): ?><div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>
<div class="stats-grid dashboard-kpi" style="margin-bottom:1rem;">
    <div class="stat-card primary">
        <div class="stat-icon"><i class="fas fa-shield-alt"></i></div>
        <div><div class="stat-value"><?= (int)($c['total'] ?? 0) ?></div><div class="stat-label">Total Risks</div></div>
    </div>
    <div class="stat-card danger">
        <div class="stat-icon"><i class="fas fa-radiation"></i></div>
        <div><div class="stat-value"><?= (int)($c['high'] ?? 0) ?></div><div class="stat-label">High Risk</div></div>
    </div>
    <div class="stat-card warning">
        <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div><div class="stat-value"><?= (int)($c['medium'] ?? 0) ?></div><div class="stat-label">Medium Risk</div></div>
    </div>
    <div class="stat-card success">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div><div class="stat-value"><?= (int)($c['low'] ?? 0) ?></div><div class="stat-label">Low Risk</div></div>
    </div>
    <div class="stat-card primary">
        <div class="stat-icon"><i class="fas fa-folder-open"></i></div>
        <div><div class="stat-value"><?= (int)($c['open'] ?? 0) ?></div><div class="stat-label">Open Risks</div></div>
    </div>
    <div class="stat-card success">
        <div class="stat-icon"><i class="fas fa-link"></i></div>
        <div><div class="stat-value"><?= (int)($c['linked'] ?? 0) ?></div><div class="stat-label">Linked Compliances</div></div>
    </div>
    <div class="stat-card primary">
        <div class="stat-icon"><i class="fas fa-network-wired"></i></div>
        <div><div class="stat-value"><?= (int)($c['it_compliances'] ?? 0) ?></div><div class="stat-label">IT Compliance Items</div></div>
    </div>
</div>
<div class="card" style="margin-top:1rem;">
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;justify-content:space-between;">
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
            <?php
            $tabMap = [
                'it-dashboard' => ['label' => 'IT Dashboard', 'icon' => 'fa-th-large'],
                'identification' => ['label' => 'Identification', 'icon' => 'fa-search'],
                'assessment' => ['label' => 'Assessment', 'icon' => 'fa-exclamation-triangle'],
                'controls' => ['label' => 'Controls', 'icon' => 'fa-shield-alt'],
                'kris' => ['label' => 'KRIs', 'icon' => 'fa-wave-square'],
                'incidents' => ['label' => 'Incidents', 'icon' => 'fa-exclamation-circle'],
                'anomalies' => ['label' => 'Anomalies', 'icon' => 'fa-bug'],
                'compliance' => ['label' => 'Compliance', 'icon' => 'fa-file-alt'],
                'resilience' => ['label' => 'Resilience', 'icon' => 'fa-life-ring'],
                'lessons' => ['label' => 'Lessons', 'icon' => 'fa-lightbulb'],
                'upload' => ['label' => 'Upload', 'icon' => 'fa-upload'],
            ];
            ?>
            <?php foreach ($tabMap as $key => $meta): ?>
            <a
                href="<?= htmlspecialchars($basePath) ?>/itrisk/dashboard?tab=<?= urlencode($key) ?>"
                class="btn btn-sm <?= $activeTab === $key ? 'btn-secondary' : 'btn-outline' ?>"
                aria-current="<?= $activeTab === $key ? 'page' : 'false' ?>"
                style="<?= $activeTab === $key
                    ? 'background:#0f172a;color:#fff;border-color:#0f172a;font-weight:600;box-shadow:0 0 0 1px rgba(15,23,42,0.12) inset;'
                    : 'background:#fff;color:#334155;border-color:#cbd5e1;' ?>"
            >
                <i class="fas <?= htmlspecialchars($meta['icon']) ?>"></i> <?= htmlspecialchars($meta['label']) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php if ($activeTab === 'it-dashboard'): ?>
        <a href="<?= htmlspecialchars($basePath) ?>/itrisk/dashboard?tab=identification" class="btn btn-primary btn-sm"><i class="fas fa-project-diagram"></i> Open Risks</a>
        <?php elseif ($canOpenLessonsModal): ?>
        <button type="button" class="btn btn-primary btn-sm" id="open-lesson-modal-2"><i class="fas fa-lightbulb"></i> Add Lesson</button>
        <?php elseif ($activeTab === 'upload'): ?>
        <button type="button" class="btn btn-primary btn-sm js-itrisk-upload-trigger"><i class="fas fa-upload"></i> Upload Data</button>
        <?php elseif ($canOpenComplianceModal): ?>
        <button type="button" class="btn btn-primary btn-sm" id="open-compliance-modal-2"><i class="fas fa-clipboard-check"></i> Track Compliance</button>
        <?php elseif ($canOpenResilienceModal): ?>
        <button type="button" class="btn btn-primary btn-sm" id="open-resilience-modal-2"><i class="fas fa-shield-alt"></i> Add Plan</button>
        <?php elseif ($canOpenIncidentModal): ?>
        <button type="button" class="btn btn-primary btn-sm" id="open-incident-modal-2"><i class="fas fa-flag"></i> Report Incident</button>
        <?php elseif ($canOpenAnomalyModal): ?>
        <button type="button" class="btn btn-primary btn-sm" id="open-anomaly-modal-2"><i class="fas fa-plus"></i> Add Anomaly</button>
        <?php elseif ($canOpenRiskModal): ?>
        <button type="button" class="btn btn-primary btn-sm" id="open-risk-modal-2"><i class="fas fa-shield-alt"></i> <?= $activeTab === 'controls' ? 'Add Control' : ($activeTab === 'kris' ? 'Add KRI' : 'Assess Risk') ?></button>
        <?php endif; ?>
    </div>
</div>
<div class="card mt-3">
    <div class="page-header" style="margin-bottom:0.5rem;">
        <div>
            <h3 class="card-title" style="margin-bottom:0.15rem;">
                <?= $activeTab === 'it-dashboard' ? 'IT Compliance Overview' : ($activeTab === 'incidents' ? 'Incident Management' : ($activeTab === 'anomalies' ? 'Anomaly Detection' : ($activeTab === 'compliance' ? 'Compliance Tracking' : ($activeTab === 'resilience' ? 'Resilience Management' : ($activeTab === 'lessons' ? 'Lessons Learned' : ($activeTab === 'upload' ? 'Data Upload' : ($activeTab === 'assessment' ? 'Risk Assessment' : ($activeTab === 'controls' ? 'Control Management' : ($activeTab === 'kris' ? 'Key Risk Indicators' : 'Risk Identification'))))))))) ?>
            </h3>
            <p class="text-muted text-sm mb-0">
                <?php if ($activeTab === 'it-dashboard'): ?>
                    Track the overall health of IT compliance and operational risk controls.
                <?php elseif ($activeTab === 'incidents'): ?>
                    Track, manage, and report operational incidents
                <?php elseif ($activeTab === 'anomalies'): ?>
                    AI-powered detection of unusual patterns that might indicate risks
                <?php elseif ($activeTab === 'compliance'): ?>
                    Monitor and manage compliance with regulatory requirements
                <?php elseif ($activeTab === 'resilience'): ?>
                    Build and monitor organizational resilience to operational risks
                <?php elseif ($activeTab === 'lessons'): ?>
                    Capture key learnings from incidents and operational events
                <?php elseif ($activeTab === 'upload'): ?>
                    Upload and manage operational risk data in bulk
                <?php elseif ($activeTab === 'assessment'): ?>
                    Analyze and evaluate identified risks across the organization
                <?php elseif ($activeTab === 'controls'): ?>
                    Define and monitor controls to mitigate operational risks
                <?php elseif ($activeTab === 'kris'): ?>
                    Monitor critical operational risk indicators across departments
                <?php else: ?>
                    Identify and classify operational risks across the organization
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:0.5rem;">
            <?php if ($activeTab === 'it-dashboard'): ?>
            <a href="<?= htmlspecialchars($basePath) ?>/itrisk/dashboard?tab=controls" class="btn btn-secondary btn-sm"><i class="fas fa-shield-alt"></i> Controls</a>
            <a href="<?= htmlspecialchars($basePath) ?>/itrisk/dashboard?tab=kris" class="btn btn-primary btn-sm"><i class="fas fa-chart-line"></i> KRIs</a>
            <?php elseif ($canOpenLessonsModal): ?>
            <button type="button" class="btn btn-secondary btn-sm"><i class="fas fa-filter"></i> Show Filters</button>
            <button type="button" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Export Lessons</button>
            <button type="button" class="btn btn-primary btn-sm" id="open-lesson-modal-3"><i class="fas fa-plus"></i> Add Lesson</button>
            <?php elseif ($activeTab === 'upload'): ?>
            <button type="button" class="btn btn-secondary btn-sm"><i class="fas fa-filter"></i> Filters</button>
            <button type="button" class="btn btn-primary btn-sm js-itrisk-upload-trigger"><i class="fas fa-upload"></i> Upload Data</button>
            <?php elseif ($canOpenComplianceModal): ?>
            <button type="button" class="btn btn-secondary btn-sm"><i class="fas fa-filter"></i> Show Filters</button>
            <button type="button" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Export</button>
            <button type="button" class="btn btn-primary btn-sm" id="open-compliance-modal-3"><i class="fas fa-plus"></i> Add Requirement</button>
            <?php elseif ($canOpenResilienceModal): ?>
            <button type="button" class="btn btn-secondary btn-sm"><i class="fas fa-filter"></i> Show Filters</button>
            <button type="button" class="btn btn-secondary btn-sm"><i class="fas fa-file-alt"></i> Generate Report</button>
            <button type="button" class="btn btn-primary btn-sm" id="open-resilience-modal-3"><i class="fas fa-plus"></i> Add Plan</button>
            <?php elseif ($canOpenIncidentModal): ?>
            <button type="button" class="btn btn-secondary btn-sm"><i class="fas fa-filter"></i> Show Filters</button>
            <button type="button" class="btn btn-secondary btn-sm"><i class="fas fa-file-import"></i> Import Incidents</button>
            <button type="button" class="btn btn-primary btn-sm" id="open-incident-modal-3"><i class="fas fa-flag"></i> Report Incident</button>
            <?php elseif ($canOpenAnomalyModal): ?>
            <button type="button" class="btn btn-secondary btn-sm"><i class="fas fa-filter"></i> Show Filters</button>
            <button type="button" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Export Data</button>
            <button type="button" class="btn btn-primary btn-sm" id="open-anomaly-modal-3"><i class="fas fa-plus"></i> Add Anomaly</button>
            <button type="button" class="btn btn-primary btn-sm" id="run-anomaly-detection-btn"><i class="fas fa-sync-alt"></i> Run Detection</button>
            <?php elseif ($canOpenRiskModal): ?>
            <button type="button" class="btn btn-secondary btn-sm"><i class="far fa-file"></i> <?= $activeTab === 'controls' ? 'Import Controls' : ($activeTab === 'kris' ? 'Export Data' : 'Import') ?></button>
            <button type="button" class="btn btn-primary btn-sm" id="open-risk-modal-3"><i class="fas fa-plus"></i> <?= $activeTab === 'assessment' ? 'Add Assessment' : ($activeTab === 'controls' ? 'Add Control' : ($activeTab === 'kris' ? 'Add KRI' : 'Add Risk')) ?></button>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($activeTab === 'it-dashboard'): ?>
    <div class="stats-grid dashboard-kpi" style="margin-bottom:0.75rem;">
        <div class="stat-card primary"><div><div class="stat-value"><?= (int)($itDashboard['it_total'] ?? 0) ?></div><div class="stat-label">IT Compliances</div></div></div>
        <div class="stat-card warning"><div><div class="stat-value"><?= (int)($itDashboard['it_open'] ?? 0) ?></div><div class="stat-label">Open IT Compliances</div></div></div>
        <div class="stat-card danger"><div><div class="stat-value"><?= (int)($itDashboard['it_overdue'] ?? 0) ?></div><div class="stat-label">Overdue</div></div></div>
        <div class="stat-card success"><div><div class="stat-value"><?= (int)($itDashboard['it_due_7'] ?? 0) ?></div><div class="stat-label">Due In 7 Days</div></div></div>
        <div class="stat-card primary"><div><div class="stat-value"><?= (int)($itDashboard['risk_total'] ?? 0) ?></div><div class="stat-label">Total Risks</div></div></div>
        <div class="stat-card success"><div><div class="stat-value"><?= (int)($itDashboard['control_total'] ?? 0) ?></div><div class="stat-label">Controls</div></div></div>
        <div class="stat-card primary"><div><div class="stat-value"><?= (int)($itDashboard['kri_total'] ?? 0) ?></div><div class="stat-label">KRIs</div></div></div>
    </div>
    <div class="form-row-2">
        <div class="card" style="padding:0.75rem;">
            <h4 style="margin:0 0 0.5rem 0;">Recent IT Compliances</h4>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Code</th><th>Title</th><th>Status</th><th>Due Date</th></tr></thead>
                    <tbody>
                        <?php foreach (($itDashboard['recent_compliances'] ?? []) as $cp): ?>
                        <tr>
                            <td><?= htmlspecialchars($cp['compliance_code'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($cp['title'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($cp['status'] ?? '—') ?></td>
                            <td><?= !empty($cp['due_date']) ? htmlspecialchars((string)$cp['due_date']) : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($itDashboard['recent_compliances'])): ?><tr><td colspan="4" class="text-muted text-center">No IT compliances found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card" style="padding:0.75rem;">
            <h4 style="margin:0 0 0.5rem 0;">Recent Risk Activity</h4>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Risk ID</th><th>Title</th><th>Severity</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach (($itDashboard['recent_risks'] ?? []) as $rr): ?>
                        <tr>
                            <td><?= htmlspecialchars($rr['risk_id'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($rr['title'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($rr['severity'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($rr['status'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($itDashboard['recent_risks'])): ?><tr><td colspan="4" class="text-muted text-center">No risk activity found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <?php if ($activeTab === 'upload'): ?>
    <div class="form-row-2" style="margin-bottom:0.75rem;">
        <div class="card" style="padding:1rem;">
            <h4 style="margin:0 0 0.75rem 0;"><i class="fas fa-file-upload"></i> Upload Files</h4>
            <div style="border:1px dashed #cbd5e1;border-radius:8px;padding:1.5rem;text-align:center;background:#fafcff;">
                <div style="font-size:2rem;color:#64748b;"><i class="far fa-file-alt"></i></div>
                <div style="font-weight:600;margin-top:0.35rem;">Drag and drop files here</div>
                <div class="text-muted text-sm" style="margin-bottom:0.35rem;">Supported formats: CSV, PDF, JPEG/JPG, XLSX (max 10MB)</div>
                <div class="text-muted text-sm" style="margin-bottom:0.6rem;">Accepted extensions: .csv, .pdf, .jpeg, .jpg, .xlsx</div>
                <input type="file" name="upload_file" id="itrisk-upload-file" class="d-none" accept=".csv,.pdf,.jpeg,.jpg,.xlsx">
                <button type="button" class="btn btn-secondary btn-sm js-itrisk-upload-trigger" id="itrisk-upload-browse">Browse Files</button>
            </div>
        </div>
        <div class="card" style="padding:1rem;">
            <h4 style="margin:0 0 0.75rem 0;"><i class="fas fa-download"></i> Download Templates</h4>
            <div class="text-muted text-sm" style="margin-bottom:0.6rem;">Download standard templates for data uploads</div>
            <div style="display:grid;gap:0.45rem;">
                <button type="button" class="btn btn-outline btn-sm"><i class="far fa-file"></i> Risk Data Template</button>
                <button type="button" class="btn btn-outline btn-sm"><i class="far fa-file"></i> Control Data Template</button>
                <button type="button" class="btn btn-outline btn-sm"><i class="far fa-file"></i> Incident Data Template</button>
                <button type="button" class="btn btn-outline btn-sm"><i class="far fa-file"></i> KRI Data Template</button>
            </div>
        </div>
    </div>
    <div class="card" style="padding:0.9rem;">
        <h3 style="margin:0 0 0.75rem 0;text-align:center;">Upload History</h3>
        <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;margin-bottom:0.6rem;">
            <input type="search" class="form-control" placeholder="Search uploads..." style="max-width:280px;">
            <span class="text-muted text-sm"><i class="fas fa-filter"></i> Filters:</span>
            <select class="form-control" style="max-width:130px;"><option>All Types</option></select>
            <select class="form-control" style="max-width:130px;"><option>All Status</option></select>
            <button type="button" class="btn btn-outline btn-sm">Clear Filters</button>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>ID</th><th>File Name</th><th>Type</th><th>Upload Date</th><th>Records</th><th>Status</th><th>Uploaded By</th><th>Actions</th></tr></thead>
                <tbody id="upload-history-tbody">
                    <?php foreach ($uploadHistoryRows as $up): ?>
                    <tr>
                    <?php $upDetail = htmlspecialchars(json_encode([
                        'title' => (string)($up['file_name'] ?? 'Upload details'),
                        'subtitle' => 'Upload ID: ' . (string)($up['id'] ?? '—'),
                        'sections' => [
                            ['title' => 'Upload Information', 'fields' => [
                                ['label' => 'Type', 'value' => (string)($up['type'] ?? '—')],
                                ['label' => 'Status', 'value' => (string)($up['status'] ?? '—')],
                                ['label' => 'Upload Date', 'value' => (string)($up['uploaded_at'] ?? '—')],
                                ['label' => 'Uploaded By', 'value' => (string)($up['uploaded_by'] ?? '—')],
                                ['label' => 'Records', 'value' => (string)($up['records'] ?? '—')],
                            ]],
                        ],
                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>
                        <td><?= htmlspecialchars($up['id']) ?></td>
                        <td><?= htmlspecialchars($up['file_name']) ?></td>
                        <td><?= htmlspecialchars($up['type']) ?></td>
                        <td><?= htmlspecialchars($up['uploaded_at']) ?></td>
                        <td><?= htmlspecialchars($up['records']) ?></td>
                        <td><span class="badge <?= ($up['status'] === 'Completed') ? 'badge-success' : 'badge-danger' ?>"><?= htmlspecialchars($up['status']) ?></span></td>
                        <td><?= htmlspecialchars($up['uploaded_by']) ?></td>
                        <td><div style="display:flex;gap:0.35rem;align-items:center;white-space:nowrap;"><a href="javascript:void(0)" class="btn btn-sm btn-outline-secondary js-open-tab-detail" data-detail="<?= $upDetail ?>" title="View"><i class="fas fa-eye"></i></a><a href="javascript:void(0)" class="btn btn-sm btn-secondary" title="Download"><i class="fas fa-download"></i></a></div></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif ($activeTab === 'lessons'): ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Lesson Title</th><th>Category</th><th>Impact</th><th>Status</th><th>Documented By</th><th>Actions</th></tr></thead>
            <tbody id="lessons-tbody">
                <?php foreach ($lessonRows as $ls): ?>
                <tr>
                    <?php $lsDetail = htmlspecialchars(json_encode([
                        'title' => (string)($ls['title'] ?? 'Lesson details'),
                        'subtitle' => 'Category: ' . (string)($ls['category'] ?? '—'),
                        'sections' => [
                            ['title' => 'Lesson Information', 'fields' => [
                                ['label' => 'Impact', 'value' => (string)($ls['impact'] ?? 'Medium')],
                                ['label' => 'Status', 'value' => (string)($ls['status'] ?? 'Pending')],
                                ['label' => 'Documented By', 'value' => (string)($ls['documented_by'] ?? '—')],
                            ]],
                        ],
                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>
                    <td><?= htmlspecialchars($ls['title'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($ls['category'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($ls['impact'] ?? 'Medium') ?></td>
                    <td><?= htmlspecialchars($ls['status'] ?? 'Pending') ?></td>
                    <td><?= htmlspecialchars($ls['documented_by'] ?? '—') ?></td>
                    <td><div style="display:flex;gap:0.35rem;align-items:center;white-space:nowrap;"><a href="javascript:void(0)" class="btn btn-sm btn-outline-secondary js-open-tab-detail" data-detail="<?= $lsDetail ?>" title="View"><i class="fas fa-eye"></i></a><a href="javascript:void(0)" class="btn btn-sm btn-secondary" title="Edit"><i class="fas fa-pen"></i></a><button type="button" class="btn btn-sm btn-danger" title="Delete" disabled><i class="fas fa-trash"></i></button></div></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($lessonRows)): ?><tr><td colspan="6" class="text-muted text-center">No lessons found. Add a lesson to get started.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php elseif ($activeTab === 'compliance'): ?>
    <div class="stats-grid dashboard-kpi" style="margin-bottom:0.75rem;">
        <div class="stat-card success"><div><div class="stat-value"><?= count(array_filter($complianceTrackRows, function($x){ return ($x['status'] ?? '') === 'Compliant'; })) ?></div><div class="stat-label">Compliant</div></div></div>
        <div class="stat-card danger"><div><div class="stat-value"><?= count(array_filter($complianceTrackRows, function($x){ return ($x['status'] ?? '') === 'Non-Compliant'; })) ?></div><div class="stat-label">Non-Compliant</div></div></div>
        <div class="stat-card primary"><div><div class="stat-value"><?= count(array_filter($complianceTrackRows, function($x){ return ($x['status'] ?? '') === 'In Progress'; })) ?></div><div class="stat-label">In Progress</div></div></div>
        <div class="stat-card warning"><div><div class="stat-value"><?= count(array_filter($complianceTrackRows, function($x){ return !empty($x['due_date']) && strtotime((string)$x['due_date']) <= strtotime('last day of this month'); })) ?></div><div class="stat-label">Due This Month</div></div></div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Regulation</th><th>Description</th><th>Department</th><th>Due Date</th><th>Status</th><th>Notes</th><th>Actions</th></tr></thead>
            <tbody id="compliance-track-tbody">
                <?php foreach ($complianceTrackRows as $cr): ?>
                <tr>
                    <?php $crDetail = htmlspecialchars(json_encode([
                        'title' => (string)($cr['regulation'] ?? 'Compliance requirement'),
                        'subtitle' => (string)($cr['department'] ?? '—'),
                        'sections' => [
                            ['title' => 'Requirement Details', 'fields' => [
                                ['label' => 'Description', 'value' => (string)($cr['description'] ?? '—')],
                                ['label' => 'Due Date', 'value' => (string)($cr['due_date'] ?? '—')],
                                ['label' => 'Status', 'value' => (string)($cr['status'] ?? 'In Progress')],
                                ['label' => 'Notes', 'value' => (string)($cr['notes'] ?? '—')],
                            ]],
                        ],
                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>
                    <td><?= htmlspecialchars($cr['regulation'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($cr['description'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($cr['department'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($cr['due_date'] ?? '—') ?></td>
                    <td><span class="badge <?= ($cr['status'] ?? '') === 'Compliant' ? 'badge-success' : (($cr['status'] ?? '') === 'Non-Compliant' ? 'badge-danger' : 'badge-primary') ?>"><?= htmlspecialchars($cr['status'] ?? 'In Progress') ?></span></td>
                    <td><?= htmlspecialchars($cr['notes'] ?? '—') ?></td>
                    <td><div style="display:flex;gap:0.35rem;align-items:center;white-space:nowrap;"><a href="javascript:void(0)" class="btn btn-sm btn-outline-secondary js-open-tab-detail" data-detail="<?= $crDetail ?>" title="View"><i class="fas fa-eye"></i></a><a href="javascript:void(0)" class="btn btn-sm btn-secondary" title="Edit"><i class="fas fa-pen"></i></a><button type="button" class="btn btn-sm btn-danger" title="Delete" disabled><i class="fas fa-trash"></i></button></div></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($complianceTrackRows)): ?><tr><td colspan="7" class="text-muted text-center">No compliance requirements found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php elseif ($activeTab === 'resilience'): ?>
    <div class="stats-grid dashboard-kpi" style="margin-bottom:0.75rem;">
        <div class="stat-card primary"><div><div class="stat-value"><?= count($resilienceRows) > 0 ? round((count(array_filter($resilienceRows, function($r){ return ($r['status'] ?? '') === 'Approved'; })) / max(1,count($resilienceRows))) * 100) : 0 ?>%</div><div class="stat-label">Resilience Score</div><div class="text-muted text-sm"><?= count(array_filter($resilienceRows, function($r){ return ($r['status'] ?? '') === 'Approved'; })) ?>/<?= count($resilienceRows) ?> plans approved</div></div></div>
        <div class="stat-card success"><div><div class="stat-value"><?= count($resilienceRows) > 0 ? round((count(array_filter($resilienceRows, function($r){ return !empty($r['last_tested']); })) / max(1,count($resilienceRows))) * 100) : 0 ?>%</div><div class="stat-label">Recovery Readiness</div><div class="text-muted text-sm"><?= count(array_filter($resilienceRows, function($r){ return !empty($r['last_tested']); })) ?>/<?= count($resilienceRows) ?> plans tested</div></div></div>
        <div class="stat-card warning"><div><div class="stat-value">0.0 hrs</div><div class="stat-label">Avg Recovery Time</div><div class="text-muted text-sm">Based on <?= count($resilienceRows) ?> plans</div></div></div>
        <div class="stat-card danger"><div><div class="stat-value"><?= count(array_filter($resilienceRows, function($r){ return ($r['status'] ?? '') !== 'Approved'; })) ?></div><div class="stat-label">Resilience Gaps</div><div class="text-muted text-sm">Plans needing attention</div></div></div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>ID</th><th>Plan Name</th><th>Type</th><th>Owner</th><th>Last Tested</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody id="resilience-tbody">
                <?php foreach ($resilienceRows as $rr): ?>
                <tr>
                    <?php $rrDetail = htmlspecialchars(json_encode([
                        'title' => (string)($rr['plan_name'] ?? 'Resilience plan'),
                        'subtitle' => (string)($rr['plan_id'] ?? '—'),
                        'sections' => [
                            ['title' => 'Plan Details', 'fields' => [
                                ['label' => 'Type', 'value' => (string)($rr['plan_type'] ?? '—')],
                                ['label' => 'Owner', 'value' => (string)($rr['owner'] ?? '—')],
                                ['label' => 'Last Tested', 'value' => (string)($rr['last_tested'] ?? '—')],
                                ['label' => 'Status', 'value' => (string)($rr['status'] ?? 'Draft')],
                            ]],
                        ],
                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>
                    <td><?= htmlspecialchars($rr['plan_id'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($rr['plan_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($rr['plan_type'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($rr['owner'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($rr['last_tested'] ?? '—') ?></td>
                    <td><span class="badge <?= ($rr['status'] ?? '') === 'Approved' ? 'badge-success' : (($rr['status'] ?? '') === 'Under Review' ? 'badge-info' : 'badge-warning') ?>"><?= htmlspecialchars($rr['status'] ?? 'Draft') ?></span></td>
                    <td><div style="display:flex;gap:0.35rem;align-items:center;white-space:nowrap;"><a href="javascript:void(0)" class="btn btn-sm btn-outline-secondary js-open-tab-detail" data-detail="<?= $rrDetail ?>" title="View"><i class="fas fa-eye"></i></a><a href="javascript:void(0)" class="btn btn-sm btn-secondary" title="Edit"><i class="fas fa-pen"></i></a><button type="button" class="btn btn-sm btn-danger" title="Delete" disabled><i class="fas fa-trash"></i></button></div></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($resilienceRows)): ?><tr><td colspan="7" class="text-muted text-center">No resilience plans found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php elseif ($activeTab === 'incidents'): ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Severity</th><th>Status</th><th>Reported By</th><th>Reported Date</th><th>Actions</th></tr></thead>
            <tbody id="incidents-tbody">
                <?php foreach ($incidentRows as $inc): ?>
                <tr>
                    <?php $incDetail = htmlspecialchars(json_encode([
                        'title' => (string)($inc['title'] ?? 'Incident details'),
                        'subtitle' => (string)($inc['incident_id'] ?? '—'),
                        'sections' => [
                            ['title' => 'Incident Information', 'fields' => [
                                ['label' => 'Category', 'value' => (string)($inc['category'] ?? '—')],
                                ['label' => 'Severity', 'value' => (string)($inc['severity'] ?? '—')],
                                ['label' => 'Status', 'value' => (string)($inc['status'] ?? 'Open')],
                                ['label' => 'Reported By', 'value' => (string)($inc['reported_by'] ?? '—')],
                                ['label' => 'Reported Date', 'value' => (string)($inc['reported_at'] ?? '—')],
                            ]],
                        ],
                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>
                    <td><?= htmlspecialchars($inc['incident_id'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($inc['title'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($inc['category'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($inc['severity'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($inc['status'] ?? 'Open') ?></td>
                    <td><?= htmlspecialchars($inc['reported_by'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($inc['reported_at'] ?? '—') ?></td>
                    <td><div style="display:flex;gap:0.35rem;align-items:center;white-space:nowrap;"><a href="javascript:void(0)" class="btn btn-sm btn-outline-secondary js-open-tab-detail" data-detail="<?= $incDetail ?>" title="View"><i class="fas fa-eye"></i></a><a href="javascript:void(0)" class="btn btn-sm btn-secondary" title="Edit"><i class="fas fa-pen"></i></a><button type="button" class="btn btn-sm btn-danger" title="Delete" disabled><i class="fas fa-trash"></i></button></div></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($incidentRows)): ?><tr><td colspan="8" class="text-muted text-center">No incidents found. Report a new incident to get started.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php elseif ($activeTab === 'anomalies'): ?>
    <div class="stats-grid dashboard-kpi" style="margin-bottom:0.75rem;">
        <div class="stat-card danger"><div><div class="stat-value" id="anomaly-kpi-transaction-total">0</div><div class="stat-label">Transaction Anomalies</div><div class="text-danger text-sm"><span id="anomaly-kpi-transaction-critical">0</span> Critical</div></div></div>
        <div class="stat-card warning"><div><div class="stat-value" id="anomaly-kpi-system-total">0</div><div class="stat-label">System Access</div><div class="text-warning text-sm"><span id="anomaly-kpi-system-critical">0</span> Critical</div></div></div>
        <div class="stat-card primary"><div><div class="stat-value" id="anomaly-kpi-customer-total">0</div><div class="stat-label">Customer Behavior</div><div class="text-primary text-sm"><span id="anomaly-kpi-customer-critical">0</span> Critical</div></div></div>
        <div class="stat-card success"><div><div class="stat-value" id="anomaly-kpi-process-total">0</div><div class="stat-label">Process Deviations</div><div class="text-success text-sm"><span id="anomaly-kpi-process-critical">0</span> Critical</div></div></div>
    </div>
    <div class="card" style="padding:1rem;margin-bottom:0.8rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;"><div style="font-weight:600;">Anomaly Trend</div><span class="badge badge-secondary">Last 30 days</span></div>
        <div id="anomaly-trend-chart" style="height:180px;border:1px dashed #dbe2ea;border-radius:8px;background:#fff;position:relative;"></div>
        <div style="display:flex;justify-content:center;gap:1rem;margin-top:0.5rem;font-size:0.92rem;">
            <span style="color:#ef4444;"><i class="fas fa-square"></i> Transaction Anomalies</span>
            <span style="color:#f59e0b;"><i class="fas fa-square"></i> System Access Anomalies</span>
            <span style="color:#3b82f6;"><i class="fas fa-square"></i> Customer Behavior Anomalies</span>
            <span style="color:#10b981;"><i class="fas fa-square"></i> Process Deviations</span>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>ID</th><th>Type</th><th>Description</th><th>Severity</th><th>Status</th><th>Confidence</th><th>Detected On</th><th>Actions</th></tr></thead>
            <tbody id="anomalies-tbody">
                <?php foreach ($anomalyRows as $a): ?>
                <tr>
                    <?php $anDetail = htmlspecialchars(json_encode([
                        'title' => (string)($a['id'] ?? 'Anomaly details'),
                        'subtitle' => (string)($a['type'] ?? '—'),
                        'sections' => [
                            ['title' => 'Anomaly Information', 'fields' => [
                                ['label' => 'Description', 'value' => (string)($a['description'] ?? '—')],
                                ['label' => 'Severity', 'value' => (string)($a['severity'] ?? '—')],
                                ['label' => 'Status', 'value' => (string)($a['status'] ?? '—')],
                                ['label' => 'Confidence', 'value' => (string)($a['confidence'] ?? '—')],
                                ['label' => 'Detected On', 'value' => (string)($a['detected_on'] ?? '—')],
                            ]],
                        ],
                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>
                    <td><?= htmlspecialchars($a['id']) ?></td>
                    <td><?= htmlspecialchars($a['type']) ?></td>
                    <td><?= htmlspecialchars($a['description']) ?></td>
                    <td><span class="badge <?= ($a['severity'] === 'Critical') ? 'badge-danger' : (($a['severity'] === 'High') ? 'badge-warning' : 'badge-success') ?>"><?= htmlspecialchars($a['severity']) ?></span></td>
                    <td><span class="badge <?= ($a['status'] === 'Resolved') ? 'badge-success' : (($a['status'] === 'Investigating') ? 'badge-info' : 'badge-primary') ?>"><?= htmlspecialchars($a['status']) ?></span></td>
                    <td><?= htmlspecialchars($a['confidence']) ?></td>
                    <td><?= htmlspecialchars($a['detected_on']) ?></td>
                    <td><div style="display:flex;gap:0.35rem;align-items:center;white-space:nowrap;"><a href="javascript:void(0)" class="btn btn-sm btn-outline-secondary js-open-tab-detail" data-detail="<?= $anDetail ?>" title="View"><i class="fas fa-eye"></i></a><a href="javascript:void(0)" class="btn btn-sm btn-secondary" title="Edit"><i class="fas fa-pen"></i></a><button type="button" class="btn btn-sm btn-danger" title="Delete" disabled><i class="fas fa-trash"></i></button></div></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <form method="get" id="risk-filter-form" action="<?= htmlspecialchars($basePath) ?>/itrisk/dashboard" style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;margin-bottom:0.75rem;">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
        <div style="flex:1;min-width:300px;position:relative;">
            <i class="fas fa-search text-muted" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:0.82rem;"></i>
            <input type="search" class="form-control" name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>" placeholder="Search risks by name, owner, or category..." style="padding-left:2rem;">
        </div>
        <?php if ($activeTab !== 'kris'): ?>
        <select name="category" class="form-control auto-submit-filter" style="max-width:150px;">
            <option value="">All...</option>
            <?php if ($activeTab === 'controls'): ?>
                <?php foreach ($controlCatOpts as $x): ?><option value="<?= htmlspecialchars($x) ?>" <?= ($filters['category'] ?? '') === $x ? 'selected' : '' ?>><?= htmlspecialchars($x) ?></option><?php endforeach; ?>
            <?php else: ?>
                <?php foreach ($catOpts as $x): ?><option value="<?= htmlspecialchars($x) ?>" <?= ($filters['category'] ?? '') === $x ? 'selected' : '' ?>><?= htmlspecialchars($x) ?></option><?php endforeach; ?>
            <?php endif; ?>
        </select>
        <?php endif; ?>
        <?php if ($activeTab === 'assessment'): ?>
        <select name="inherent" class="form-control auto-submit-filter" style="max-width:160px;">
            <option value="">All Severity</option>
            <?php foreach ($sevOpts as $x): ?><option value="<?= htmlspecialchars($x) ?>" <?= ($filters['inherent'] ?? '') === $x ? 'selected' : '' ?>><?= htmlspecialchars($x) ?></option><?php endforeach; ?>
        </select>
        <select name="residual" class="form-control auto-submit-filter" style="max-width:140px;">
            <option value="">All Status</option>
            <option value="Critical" <?= ($filters['residual'] ?? '') === 'Critical' ? 'selected' : '' ?>>Critical</option>
            <option value="High" <?= ($filters['residual'] ?? '') === 'High' ? 'selected' : '' ?>>High</option>
            <option value="Medium" <?= ($filters['residual'] ?? '') === 'Medium' ? 'selected' : '' ?>>Medium</option>
            <option value="Low" <?= ($filters['residual'] ?? '') === 'Low' ? 'selected' : '' ?>>Low</option>
        </select>
        <?php elseif ($activeTab === 'controls'): ?>
        <select name="control_type" class="form-control auto-submit-filter" style="max-width:140px;">
            <option value="">All Type</option>
            <?php foreach ($controlTypeOpts as $x): ?><option value="<?= htmlspecialchars($x) ?>" <?= ($filters['controlType'] ?? '') === $x ? 'selected' : '' ?>><?= htmlspecialchars($x) ?></option><?php endforeach; ?>
        </select>
        <select name="frequency" class="form-control auto-submit-filter" style="max-width:140px;">
            <option value="">All Status</option>
            <?php foreach ($controlFreqOpts as $x): ?><option value="<?= htmlspecialchars($x) ?>" <?= ($filters['controlFrequency'] ?? '') === $x ? 'selected' : '' ?>><?= htmlspecialchars($x) ?></option><?php endforeach; ?>
        </select>
        <?php elseif ($activeTab === 'kris'): ?>
        <select name="kri_status" class="form-control auto-submit-filter" style="max-width:140px;">
            <option value="">All Status</option>
            <?php foreach ($kriStatusOpts as $x): ?><option value="<?= htmlspecialchars($x) ?>" <?= ($filters['kriStatus'] ?? '') === $x ? 'selected' : '' ?>><?= htmlspecialchars($x) ?></option><?php endforeach; ?>
        </select>
        <select name="kri_frequency" class="form-control auto-submit-filter" style="max-width:140px;">
            <option value="">All Frequency</option>
            <?php foreach ($kriFreqOpts as $x): ?><option value="<?= htmlspecialchars($x) ?>" <?= ($filters['kriFrequency'] ?? '') === $x ? 'selected' : '' ?>><?= htmlspecialchars($x) ?></option><?php endforeach; ?>
        </select>
        <?php else: ?>
        <select name="severity" class="form-control auto-submit-filter" style="max-width:140px;">
            <option value="">All Severity</option>
            <?php foreach ($sevOpts as $x): ?><option value="<?= htmlspecialchars($x) ?>" <?= ($filters['severity'] ?? '') === $x ? 'selected' : '' ?>><?= htmlspecialchars($x) ?></option><?php endforeach; ?>
        </select>
        <select name="status" class="form-control auto-submit-filter" style="max-width:140px;">
            <option value="">All Status</option>
            <?php foreach ($statusOpts as $x): ?><option value="<?= htmlspecialchars($x) ?>" <?= ($filters['status'] ?? '') === $x ? 'selected' : '' ?>><?= htmlspecialchars($x) ?></option><?php endforeach; ?>
        </select>
        <?php endif; ?>
    </form>
    <?php if ($activeTab === 'assessment'): ?>
    <div class="stats-grid dashboard-kpi" style="margin-bottom:0.75rem;">
        <div class="stat-card warning">
            <div><div class="stat-value"><?= (int)($assessment['inherent_score'] ?? 0) ?></div><div class="stat-label">Inherent Risk Score</div></div>
        </div>
        <div class="stat-card success">
            <div><div class="stat-value"><?= (int)($assessment['residual_score'] ?? 0) ?></div><div class="stat-label">Residual Risk Score</div></div>
        </div>
        <div class="stat-card primary">
            <div><div class="stat-value"><?= (int)($assessment['risk_appetite'] ?? 0) ?></div><div class="stat-label">Risk Appetite</div></div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($activeTab === 'kris'): ?>
    <div class="text-muted text-center" style="padding:1rem 0 1.4rem 0;">
        <?php if (empty($kris)): ?>
            No KRIs found. Add some KRIs to get started.
        <?php else: ?>
            <?= count($kris) ?> KRI(s) available.
        <?php endif; ?>
    </div>
    <div class="page-header" style="margin-bottom:0.75rem;">
        <div><h4 style="margin:0;">KRI Performance</h4></div>
        <div style="display:flex;gap:0.5rem;">
            <a href="<?= htmlspecialchars($basePath) ?>/itrisk/dashboard?tab=kris&kri_view=charts" class="btn btn-sm <?= ($filters['kriViewMode'] ?? 'charts') === 'charts' ? 'btn-secondary' : 'btn-outline' ?>"><i class="fas fa-chart-bar"></i> Charts</a>
            <a href="<?= htmlspecialchars($basePath) ?>/itrisk/dashboard?tab=kris&kri_view=table" class="btn btn-sm <?= ($filters['kriViewMode'] ?? 'charts') === 'table' ? 'btn-secondary' : 'btn-outline' ?>"><i class="fas fa-table"></i> Data Table</a>
        </div>
    </div>
    <?php if (($filters['kriViewMode'] ?? 'charts') === 'charts'): ?>
    <div class="card" style="padding:1rem;margin-bottom:0.75rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
            <div style="font-weight:600;">System Performance Metrics</div>
            <span class="badge badge-secondary">Last 30 days</span>
        </div>
        <div id="kri-chart-system" class="kri-chart-box" style="height:230px;position:relative;"></div>
        <div style="display:flex;justify-content:center;gap:1rem;margin-top:0.35rem;font-size:0.92rem;">
            <span style="color:#2563eb;">&#8212; Actual</span>
            <span style="color:#ef4444;">&#8212; Threshold</span>
        </div>
    </div>
    <div class="card" style="padding:1rem;margin-bottom:0.75rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
            <div style="font-weight:600;">Transaction Failure Rate</div>
            <span class="badge badge-secondary">Last 30 days</span>
        </div>
        <div id="kri-chart-failure" class="kri-chart-box" style="height:230px;position:relative;"></div>
        <div style="display:flex;justify-content:center;gap:1rem;margin-top:0.35rem;font-size:0.92rem;">
            <span style="color:#2563eb;">&#8212; Actual</span>
            <span style="color:#ef4444;">&#8212; Threshold</span>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <?php if (!($activeTab === 'kris' && ($filters['kriViewMode'] ?? 'charts') === 'charts')): ?>
    <div class="table-wrap">
        <table class="data-table">
            <?php if ($activeTab === 'assessment'): ?>
            <thead><tr><th>ID</th><th>Risk Name</th><th>Category</th><th>Inherent Risk</th><th>Residual Risk</th><th>Owner</th><th>Last Assessment</th><th>Actions</th></tr></thead>
            <?php elseif ($activeTab === 'controls'): ?>
            <thead><tr><th>ID</th><th>Control Name</th><th>Risk Category</th><th>Type</th><th>Frequency</th><th>Effectiveness</th><th>Status</th><th>Actions</th></tr></thead>
            <?php elseif ($activeTab === 'kris'): ?>
            <thead><tr><th>KRI Name</th><th>Description</th><th>Current Value</th><th>Threshold</th><th>Unit</th><th>Frequency</th><th>Owner</th><th>Status</th><th>Actions</th></tr></thead>
            <?php else: ?>
            <thead><tr><th>Risk Name</th><th>Category</th><th>Severity</th><th>Status</th><th>Owner</th><th>Date Identified</th><th>Actions</th></tr></thead>
            <?php endif; ?>
            <tbody>
                <?php if ($activeTab === 'controls'): ?>
                <?php foreach ($controls as $ctl): ?>
                <tr>
                    <?php $ctlDetail = htmlspecialchars(json_encode([
                        'title' => (string)($ctl['control_name'] ?? 'Control details'),
                        'subtitle' => (string)($ctl['control_id'] ?? '—'),
                        'sections' => [
                            ['title' => 'Control Information', 'fields' => [
                                ['label' => 'Risk Category', 'value' => (string)($ctl['risk_category'] ?? '—')],
                                ['label' => 'Control Type', 'value' => (string)($ctl['control_type'] ?? '—')],
                                ['label' => 'Frequency', 'value' => (string)($ctl['frequency'] ?? '—')],
                                ['label' => 'Effectiveness', 'value' => (string)($ctl['effectiveness'] ?? '—')],
                                ['label' => 'Status', 'value' => (string)($ctl['status'] ?? '—')],
                                ['label' => 'Owner', 'value' => (string)($ctl['control_owner'] ?? '—')],
                                ['label' => 'Description', 'value' => (string)($ctl['description'] ?? '—')],
                            ]],
                        ],
                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>
                    <td><?= htmlspecialchars($ctl['control_id']) ?></td>
                    <td><?= htmlspecialchars($ctl['control_name']) ?></td>
                    <td><?= htmlspecialchars($ctl['risk_category']) ?></td>
                    <td><?= htmlspecialchars($ctl['control_type']) ?></td>
                    <td><?= htmlspecialchars($ctl['frequency']) ?></td>
                    <td>
                        <?php $eff = (string)($ctl['effectiveness'] ?? 'Effective'); ?>
                        <span class="badge <?= $eff === 'Effective' ? 'badge-success' : ($eff === 'Partially Effective' ? 'badge-warning' : 'badge-danger') ?>"><?= htmlspecialchars($eff) ?></span>
                    </td>
                    <td>
                        <?php $stCtl = (string)($ctl['status'] ?? 'Active'); ?>
                        <span class="badge <?= $stCtl === 'Active' ? 'badge-success' : ($stCtl === 'Under Review' ? 'badge-info' : 'badge-danger') ?>"><?= htmlspecialchars($stCtl) ?></span>
                    </td>
                    <td>
                        <div style="display:flex;gap:0.35rem;align-items:center;white-space:nowrap;">
                            <a href="javascript:void(0)" class="btn btn-sm btn-outline-secondary js-open-tab-detail" data-detail="<?= $ctlDetail ?>" title="View"><i class="fas fa-eye"></i></a>
                            <a href="javascript:void(0)" class="btn btn-sm btn-secondary" title="Edit"><i class="fas fa-pen"></i></a>
                            <?php if (($user['role_slug'] ?? '') === 'admin'): ?><button type="button" class="btn btn-sm btn-danger" title="Delete" disabled><i class="fas fa-trash"></i></button><?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php elseif ($activeTab === 'kris'): ?>
                <?php foreach ($kris as $k): ?>
                <tr>
                    <?php $kriDetail = htmlspecialchars(json_encode([
                        'title' => (string)($k['kri_name'] ?? 'KRI details'),
                        'subtitle' => (string)($k['kri_id'] ?? '—'),
                        'sections' => [
                            ['title' => 'KRI Information', 'fields' => [
                                ['label' => 'Description', 'value' => (string)($k['description'] ?? '—')],
                                ['label' => 'Current Value', 'value' => $k['current_value'] !== null ? (string)$k['current_value'] : '—'],
                                ['label' => 'Threshold', 'value' => $k['threshold_value'] !== null ? (string)$k['threshold_value'] : '—'],
                                ['label' => 'Measurement Unit', 'value' => (string)($k['measurement_unit'] ?? '—')],
                                ['label' => 'Frequency', 'value' => (string)($k['frequency'] ?? '—')],
                                ['label' => 'Owner', 'value' => (string)($k['owner_label'] ?? '—')],
                                ['label' => 'Status', 'value' => (string)($k['status'] ?? '—')],
                            ]],
                        ],
                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>
                    <td><?= htmlspecialchars($k['kri_name']) ?></td>
                    <td><?= htmlspecialchars($k['description'] ?? '—') ?></td>
                    <td><?= $k['current_value'] !== null ? htmlspecialchars((string) $k['current_value']) : '—' ?></td>
                    <td><?= $k['threshold_value'] !== null ? htmlspecialchars((string) $k['threshold_value']) : '—' ?></td>
                    <td><?= htmlspecialchars($k['measurement_unit'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($k['frequency']) ?></td>
                    <td><?= htmlspecialchars($k['owner_label'] ?? '—') ?></td>
                    <td><span class="badge <?= ($k['status'] ?? '') === 'Active' ? 'badge-success' : (($k['status'] ?? '') === 'Under Review' ? 'badge-info' : 'badge-danger') ?>"><?= htmlspecialchars($k['status']) ?></span></td>
                    <td>
                        <div style="display:flex;gap:0.35rem;align-items:center;white-space:nowrap;">
                            <a href="javascript:void(0)" class="btn btn-sm btn-outline-secondary js-open-tab-detail" data-detail="<?= $kriDetail ?>" title="View"><i class="fas fa-eye"></i></a>
                            <a href="javascript:void(0)" class="btn btn-sm btn-secondary" title="Edit"><i class="fas fa-pen"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <?php foreach ($items as $r): ?>
                <tr>
                    <?php $riskDetail = htmlspecialchars(json_encode([
                        'title' => (string)($r['title'] ?? 'Risk details'),
                        'subtitle' => 'Risk ID: ' . (string)($r['risk_id'] ?? '—'),
                        'sections' => [
                            ['title' => 'Assessment Details', 'fields' => [
                                ['label' => 'Description', 'value' => (string)($r['description'] ?? '—')],
                                ['label' => 'Category', 'value' => (string)($r['category'] ?? '—')],
                                ['label' => 'Severity', 'value' => (string)($r['severity'] ?? '—')],
                                ['label' => 'Inherent Risk', 'value' => (string)($r['inherent_risk'] ?? '—')],
                                ['label' => 'Residual Risk', 'value' => (string)($r['residual_risk'] ?? '—')],
                                ['label' => 'Status', 'value' => (string)($r['status'] ?? '—')],
                                ['label' => 'Owner', 'value' => (string)($r['owner_name'] ?? '—')],
                                ['label' => 'Department', 'value' => (string)($r['department'] ?? '—')],
                                ['label' => 'Sources', 'value' => (string)($r['sources'] ?? '—')],
                                ['label' => 'Last Assessment', 'value' => !empty($r['last_assessed_at']) ? date('Y-m-d', strtotime((string)$r['last_assessed_at'])) : (!empty($r['created_at']) ? date('Y-m-d', strtotime((string)$r['created_at'])) : '—')],
                            ]],
                        ],
                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($activeTab === 'assessment'): ?>
                    <td><?= htmlspecialchars($r['risk_id']) ?></td>
                    <?php endif; ?>
                    <td><?= htmlspecialchars($r['title']) ?></td>
                    <td><?= htmlspecialchars($r['category'] ?? '—') ?></td>
                    <?php if ($activeTab === 'assessment'): ?>
                    <td><span class="badge badge-warning"><?= htmlspecialchars($r['inherent_risk'] ?? ($r['severity'] ?? 'Medium')) ?></span></td>
                    <td><span class="badge badge-success"><?= htmlspecialchars($r['residual_risk'] ?? 'Low') ?></span></td>
                    <?php else: ?>
                    <td><?= htmlspecialchars($r['severity'] ?? ($r['impact'] ?? 'Medium')) ?></td>
                    <td><?= htmlspecialchars($r['status']) ?></td>
                    <?php endif; ?>
                    <td><?= htmlspecialchars($r['owner_name'] ?? '—') ?></td>
                    <td><?= !empty($r['last_assessed_at']) ? date('Y-m-d', strtotime($r['last_assessed_at'])) : (!empty($r['created_at']) ? date('Y-m-d', strtotime($r['created_at'])) : '—') ?></td>
                    <td>
                        <div style="display:flex;gap:0.35rem;align-items:center;white-space:nowrap;">
                            <a href="javascript:void(0)" class="btn btn-sm btn-outline-secondary js-open-tab-detail" data-detail="<?= $riskDetail ?>" title="View"><i class="fas fa-eye"></i></a>
                            <a href="<?= htmlspecialchars($basePath) ?>/itrisk/edit/<?= (int) $r['id'] ?>" class="btn btn-sm btn-secondary" title="Edit"><i class="fas fa-pen"></i></a>
                            <?php if (($user['role_slug'] ?? '') === 'admin'): ?>
                            <form method="post" action="<?= htmlspecialchars($basePath) ?>/itrisk/delete/<?= (int) $r['id'] ?>" class="d-inline" onsubmit="return confirm('Delete this risk?');">
                                <button class="btn btn-sm btn-danger" type="submit" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                <?php if (($activeTab === 'controls' && empty($controls)) || ($activeTab === 'kris' && empty($kris)) || (!in_array($activeTab, ['controls', 'kris'], true) && empty($items))): ?>
                <?php if ($activeTab === 'controls'): ?>
                <tr><td colspan="8" class="text-muted text-center">No controls yet. Click "Add Control" to get started.</td></tr>
                <?php elseif ($activeTab === 'kris'): ?>
                <tr><td colspan="9" class="text-muted text-center">No KRIs found. Add some KRIs to get started.</td></tr>
                <?php else: ?>
                <tr><td colspan="<?= $activeTab === 'assessment' ? '8' : '7' ?>" class="text-muted text-center">No risks identified yet. Click "Add Risk" to get started.</td></tr>
                <?php endif; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="text-muted text-sm text-center mt-2">
        <?= $activeTab === 'controls' ? 'List of operational controls' : ($activeTab === 'kris' ? 'List of key risk indicators' : 'List of identified operational risks') ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
</div>

<div id="tab-detail-modal" class="modal-overlay compliance-modal" style="display:none;" aria-hidden="true">
    <div class="modal compliance-edit-modal" role="dialog" aria-labelledby="tab-detail-title" style="width:min(760px,96vw);max-height:92vh;display:flex;flex-direction:column;overflow:hidden;">
        <div class="modal-header">
            <h2 class="modal-title" id="tab-detail-title">Details</h2>
            <button type="button" class="modal-close" id="close-tab-detail-modal" aria-label="Close">&times;</button>
        </div>
        <div id="tab-detail-body" style="overflow:auto;padding:1rem 1.25rem 1rem 1.25rem;flex:1;min-height:0;"></div>
        <div class="modal-footer" style="padding:0.75rem 1.25rem;border-top:1px solid #e5e7eb;background:#fff;display:flex;justify-content:flex-end;gap:0.5rem;">
            <button type="button" class="btn btn-secondary" id="dismiss-tab-detail-modal">Close</button>
        </div>
    </div>
</div>

<?php if ($canOpenRiskModal): ?>
<div id="risk-modal" class="modal-overlay compliance-modal" style="display:none;" aria-hidden="true">
    <div class="modal compliance-edit-modal" role="dialog" aria-labelledby="risk-modal-title" style="width:min(780px,96vw);max-height:92vh;display:flex;flex-direction:column;overflow:hidden;">
        <div class="modal-header">
            <h2 class="modal-title" id="risk-modal-title"><?= $activeTab === 'assessment' ? 'Add Risk Assessment' : ($activeTab === 'controls' ? 'Add New Control' : ($activeTab === 'kris' ? 'Add Key Risk Indicator' : 'Add New Risk')) ?></h2>
            <button type="button" class="modal-close" id="close-risk-modal" aria-label="Close">&times;</button>
        </div>
        <form method="post" action="<?= htmlspecialchars($basePath) ?><?= $activeTab === 'assessment' ? '/itrisk/assessment/create' : ($activeTab === 'controls' ? '/itrisk/control/create' : ($activeTab === 'kris' ? '/itrisk/kri/create' : '/itrisk/create')) ?>" style="display:flex;flex-direction:column;min-height:0;flex:1;">
            <div style="overflow:auto;padding:1rem 1.25rem 0.5rem 1.25rem;flex:1;min-height:0;">
            <?php if ($activeTab === 'controls'): ?>
            <div class="form-group"><label class="form-label">Control Name</label><input type="text" class="form-control" name="control_name" placeholder="Name of the control" required></div>
            <div class="form-group"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" placeholder="Detailed description of the control"></textarea></div>
            <div class="form-row-2">
                <div class="form-group"><label class="form-label">Risk Category</label><select class="form-control" name="risk_category" required><option value="">Select category</option><?php foreach ($controlCatOpts as $x): ?><option value="<?= htmlspecialchars($x) ?>"><?= htmlspecialchars($x) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label class="form-label">Control Type</label><select class="form-control" name="control_type" required><option value="">Select type</option><?php foreach ($controlTypeOpts as $x): ?><option value="<?= htmlspecialchars($x) ?>"><?= htmlspecialchars($x) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-row-2">
                <div class="form-group"><label class="form-label">Frequency</label><select class="form-control" name="frequency" required><option value="">Select frequency</option><?php foreach ($controlFreqOpts as $x): ?><option value="<?= htmlspecialchars($x) ?>"><?= htmlspecialchars($x) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label class="form-label">Control Owner</label><input type="text" class="form-control" name="control_owner" placeholder="Department responsible"></div>
            </div>
            <div class="form-group"><label class="form-label">Documentation</label><textarea class="form-control" name="documentation" rows="2" placeholder="Links to relevant documentation"></textarea></div>
            <div class="form-group"><label class="form-label">Testing Procedure</label><textarea class="form-control" name="testing_procedure" rows="2" placeholder="How this control is tested for effectiveness"></textarea></div>
            <?php elseif ($activeTab === 'kris'): ?>
            <div class="form-group"><label class="form-label">KRI Name</label><input type="text" class="form-control" name="kri_name" placeholder="Enter KRI name" required></div>
            <div class="form-group"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2" placeholder="Enter KRI description"></textarea></div>
            <div class="form-row-2">
                <div class="form-group"><label class="form-label">Measurement Unit</label><input type="text" class="form-control" name="measurement_unit" placeholder="e.g. %, Count, Hours"></div>
                <div class="form-group"><label class="form-label">Frequency</label><select class="form-control" name="frequency" required><option value="">Select frequency</option><?php foreach ($kriFreqOpts as $x): ?><option value="<?= htmlspecialchars($x) ?>"><?= htmlspecialchars($x) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-row-2">
                <div class="form-group"><label class="form-label">Current Value</label><input type="number" step="0.01" class="form-control" name="current_value" placeholder="Enter current value"></div>
                <div class="form-group"><label class="form-label">Threshold Value</label><input type="number" step="0.01" class="form-control" name="threshold_value" placeholder="Enter threshold"></div>
            </div>
            <div class="form-row-2">
                <div class="form-group"><label class="form-label">Status</label><select class="form-control" name="status" required><option value="">Select status</option><?php foreach ($kriStatusOpts as $x): ?><option value="<?= htmlspecialchars($x) ?>"><?= htmlspecialchars($x) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label class="form-label">Owner</label><input type="text" class="form-control" name="owner" placeholder="Enter owner"></div>
            </div>
            <?php else: ?>
            <div class="form-group"><label class="form-label">Risk Name *</label><input type="text" class="form-control" name="risk_name" required></div>
            <div class="form-group"><label class="form-label">Category *</label><select class="form-control" name="category" required><?php foreach ($catOpts as $x): ?><option value="<?= htmlspecialchars($x) ?>"><?= htmlspecialchars($x) ?></option><?php endforeach; ?></select></div>
            <?php if ($activeTab === 'assessment'): ?>
            <div class="form-row-2">
                <div class="form-group"><label class="form-label">Inherent Risk</label><select class="form-control" name="inherent_risk" required><option value="">Select inherent risk</option><?php foreach ($sevOpts as $x): ?><option value="<?= htmlspecialchars($x) ?>"><?= htmlspecialchars($x) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label class="form-label">Residual Risk</label><select class="form-control" name="residual_risk" required><option value="">Select residual risk</option><?php foreach ($sevOpts as $x): ?><option value="<?= htmlspecialchars($x) ?>"><?= htmlspecialchars($x) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-group"><label class="form-label">Owner</label><input type="text" class="form-control" name="owner" placeholder="Enter owner/team"></div>
            <?php else: ?>
            <div class="form-group"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"></textarea></div>
            <div class="form-group"><label class="form-label">Sources</label><input type="text" class="form-control" name="sources"></div>
            <div class="form-group"><label class="form-label">Severity *</label><select class="form-control" name="severity" required><?php foreach ($sevOpts as $x): ?><option value="<?= htmlspecialchars($x) ?>" <?= $x === 'Medium' ? 'selected' : '' ?>><?= htmlspecialchars($x) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Owner *</label><select class="form-control" name="owner_id" required><option value="">Select owner</option><?php foreach ($u as $x): ?><option value="<?= (int) $x['id'] ?>"><?= htmlspecialchars($x['full_name']) ?></option><?php endforeach; ?></select></div>
            <?php endif; ?>
            <div class="form-group"><label class="form-label">Department</label><input type="text" class="form-control" name="department" value="IT"></div>
            <div class="form-group"><label class="form-label">Link Compliance</label><select class="form-control" name="linked_compliance_id"><option value="">None</option><?php foreach ($compliances as $cp): ?><option value="<?= (int) $cp['id'] ?>"><?= htmlspecialchars($cp['compliance_code'] . ' - ' . $cp['title']) ?></option><?php endforeach; ?></select></div>
            <?php endif; ?>
            </div>
            <div class="modal-footer" style="padding:0.75rem 1.25rem;border-top:1px solid #e5e7eb;background:#fff;display:flex;justify-content:flex-end;gap:0.5rem;">
                <button type="button" class="btn btn-secondary" id="cancel-risk-modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><?= $activeTab === 'assessment' ? 'Add Assessment' : ($activeTab === 'controls' ? 'Add Control' : ($activeTab === 'kris' ? 'Add KRI' : 'Add Risk')) ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php if ($canOpenIncidentModal): ?>
<div id="incident-modal" class="modal-overlay compliance-modal" style="display:none;" aria-hidden="true">
    <div class="modal compliance-edit-modal" role="dialog" aria-labelledby="incident-modal-title" style="width:min(760px,96vw);max-height:92vh;display:flex;flex-direction:column;overflow:hidden;">
        <div class="modal-header"><h2 class="modal-title" id="incident-modal-title">Report New Incident</h2><button type="button" class="modal-close" id="close-incident-modal" aria-label="Close">&times;</button></div>
        <form id="incident-form" method="post" action="javascript:void(0)" style="display:flex;flex-direction:column;min-height:0;flex:1;">
            <div style="overflow:auto;padding:1rem 1.25rem 0.5rem 1.25rem;flex:1;min-height:0;">
                <div class="form-group"><label class="form-label">Incident Title</label><input type="text" class="form-control" name="title" placeholder="Brief description of the incident" required></div>
                <div class="form-row-2"><div class="form-group"><label class="form-label">Category</label><select class="form-control" name="category" required><option value="">Select category</option><option>Operational</option><option>Security</option><option>Compliance</option><option>Process</option></select></div><div class="form-group"><label class="form-label">Severity</label><select class="form-control" name="severity" required><option value="">Select severity</option><option>Critical</option><option>High</option><option>Medium</option><option>Low</option></select></div></div>
                <div class="form-group"><label class="form-label">Reported By</label><input type="text" class="form-control" name="reported_by" placeholder="Name of person reporting"></div>
                <div class="form-group"><label class="form-label">Incident Description</label><textarea class="form-control" name="description" rows="3" placeholder="Provide detailed description of the incident"></textarea></div>
                <div class="form-group"><label class="form-label">Impacted Services/Systems</label><input type="text" class="form-control" name="impacted_services" placeholder="List all impacted services"></div>
                <div class="form-group"><label class="form-label">Immediate Actions Taken</label><textarea class="form-control" name="immediate_actions" rows="2" placeholder="Describe any immediate actions taken"></textarea></div>
            </div>
            <div class="modal-footer" style="padding:0.75rem 1.25rem;border-top:1px solid #e5e7eb;background:#fff;display:flex;justify-content:flex-end;gap:0.5rem;"><button type="button" class="btn btn-secondary" id="cancel-incident-modal">Cancel</button><button type="submit" class="btn btn-primary">Report Incident</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php if ($canOpenAnomalyModal): ?>
<div id="anomaly-modal" class="modal-overlay compliance-modal" style="display:none;" aria-hidden="true">
    <div class="modal compliance-edit-modal" role="dialog" aria-labelledby="anomaly-modal-title" style="width:min(760px,96vw);max-height:92vh;display:flex;flex-direction:column;overflow:hidden;">
        <div class="modal-header"><h2 class="modal-title" id="anomaly-modal-title">Add Risk Anomaly</h2><button type="button" class="modal-close" id="close-anomaly-modal" aria-label="Close">&times;</button></div>
        <form id="anomaly-form" method="post" action="javascript:void(0)" style="display:flex;flex-direction:column;min-height:0;flex:1;">
            <div style="overflow:auto;padding:1rem 1.25rem 0.5rem 1.25rem;flex:1;min-height:0;">
                <div class="form-group"><label class="form-label">Type</label><select class="form-control" name="type" required><option value="">Select type</option><option>Transaction</option><option>System Access</option><option>Customer Behavior</option><option>Process</option></select></div>
                <div class="form-group"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" placeholder="Enter anomaly description"></textarea></div>
                <div class="form-row-2"><div class="form-group"><label class="form-label">Severity</label><select class="form-control" name="severity" required><option value="">Select severity</option><option>Critical</option><option>High</option><option>Medium</option><option>Low</option></select></div><div class="form-group"><label class="form-label">Status</label><select class="form-control" name="status" required><option value="">Select status</option><option>Open</option><option>Investigating</option><option>Resolved</option></select></div></div>
                <div class="form-group"><label class="form-label">Confidence</label><select class="form-control" name="confidence" required><option value="">Select confidence</option><option>High</option><option>Medium</option><option>Low</option></select></div>
            </div>
            <div class="modal-footer" style="padding:0.75rem 1.25rem;border-top:1px solid #e5e7eb;background:#fff;display:flex;justify-content:flex-end;gap:0.5rem;"><button type="button" class="btn btn-secondary" id="cancel-anomaly-modal">Cancel</button><button type="submit" class="btn btn-primary">Add Anomaly</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php if ($canOpenComplianceModal): ?>
<div id="compliance-modal" class="modal-overlay compliance-modal" style="display:none;" aria-hidden="true">
    <div class="modal compliance-edit-modal" role="dialog" aria-labelledby="compliance-modal-title" style="width:min(760px,96vw);max-height:92vh;display:flex;flex-direction:column;overflow:hidden;">
        <div class="modal-header"><h2 class="modal-title" id="compliance-modal-title">Add Compliance Requirement</h2><button type="button" class="modal-close" id="close-compliance-modal" aria-label="Close">&times;</button></div>
        <form id="compliance-track-form" method="post" action="javascript:void(0)" style="display:flex;flex-direction:column;min-height:0;flex:1;">
            <div style="overflow:auto;padding:1rem 1.25rem 0.5rem 1.25rem;flex:1;min-height:0;">
                <div class="form-group"><label class="form-label">Related Risk ID</label><input type="text" class="form-control" name="related_risk_id" placeholder="Enter related risk identifier (optional)"></div>
                <div class="form-group"><label class="form-label">Regulation Name</label><input type="text" class="form-control" name="regulation" placeholder="e.g., RBI Master Direction, SEBI Guidelines" required></div>
                <div class="form-group"><label class="form-label">Requirement Description</label><textarea class="form-control" name="description" rows="3" placeholder="Describe the specific compliance requirement"></textarea></div>
                <div class="form-row-2"><div class="form-group"><label class="form-label">Responsible Department</label><input type="text" class="form-control" name="department" placeholder="Enter department name"></div><div class="form-group"><label class="form-label">Due Date</label><input type="date" class="form-control" name="due_date"></div></div>
                <div class="form-group"><label class="form-label">Compliance Status</label><select class="form-control" name="status" required><option value="">Select status</option><option>Compliant</option><option>Non-Compliant</option><option>In Progress</option></select></div>
                <div class="form-group"><label class="form-label">Evidence Location</label><input type="text" class="form-control" name="evidence_location" placeholder="Location of supporting documents/evidence"></div>
                <div class="form-group"><label class="form-label">Assessment Notes</label><textarea class="form-control" name="assessment_notes" rows="2" placeholder="Notes from compliance assessment"></textarea></div>
                <div class="form-group"><label class="form-label">Remediation Plan</label><textarea class="form-control" name="remediation_plan" rows="2" placeholder="Plan to address any compliance gaps"></textarea></div>
            </div>
            <div class="modal-footer" style="padding:0.75rem 1.25rem;border-top:1px solid #e5e7eb;background:#fff;display:flex;justify-content:flex-end;gap:0.5rem;"><button type="button" class="btn btn-secondary" id="cancel-compliance-modal">Cancel</button><button type="submit" class="btn btn-primary">Add Requirement</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php if ($canOpenResilienceModal): ?>
<div id="resilience-modal" class="modal-overlay compliance-modal" style="display:none;" aria-hidden="true">
    <div class="modal compliance-edit-modal" role="dialog" aria-labelledby="resilience-modal-title" style="width:min(760px,96vw);max-height:92vh;display:flex;flex-direction:column;overflow:hidden;">
        <div class="modal-header"><h2 class="modal-title" id="resilience-modal-title">Add Resilience Plan</h2><button type="button" class="modal-close" id="close-resilience-modal" aria-label="Close">&times;</button></div>
        <form id="resilience-form" method="post" action="javascript:void(0)" style="display:flex;flex-direction:column;min-height:0;flex:1;">
            <div style="overflow:auto;padding:1rem 1.25rem 0.5rem 1.25rem;flex:1;min-height:0;">
                <div class="form-group"><label class="form-label">Related Risk ID</label><input type="text" class="form-control" name="related_risk_id" placeholder="Enter related risk identifier (optional)"></div>
                <div class="form-row-2"><div class="form-group"><label class="form-label">Plan Name</label><input type="text" class="form-control" name="plan_name" placeholder="Enter plan name" required></div><div class="form-group"><label class="form-label">Plan Type</label><select class="form-control" name="plan_type" required><option value="">Select plan type</option><option>Business Continuity</option><option>Disaster Recovery</option><option>Incident Response</option><option>Crisis Management</option></select></div></div>
                <div class="form-group"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2" placeholder="Describe the purpose and scope of this resilience plan"></textarea></div>
                <div class="form-row-2"><div class="form-group"><label class="form-label">Recovery Time Objective (hours)</label><input type="number" step="0.1" class="form-control" name="rto_hours" placeholder="e.g., 2"></div><div class="form-group"><label class="form-label">Recovery Point Objective (hours)</label><input type="number" step="0.1" class="form-control" name="rpo_hours" placeholder="e.g., 4"></div></div>
                <div class="form-group"><label class="form-label">Activation Triggers (comma-separated)</label><input type="text" class="form-control" name="activation_triggers" placeholder="e.g., system outage, natural disaster, security breach"></div>
                <div class="form-group"><label class="form-label">Recovery Objectives</label><textarea class="form-control" name="recovery_objectives" rows="2" placeholder="Define what constitutes successful recovery"></textarea></div>
                <div class="form-group"><label class="form-label">Key Personnel (comma-separated)</label><input type="text" class="form-control" name="key_personnel" placeholder="e.g., John Doe - IT Manager, Jane Smith - Operations Head"></div>
                <div class="form-group"><label class="form-label">Critical Resources (comma-separated)</label><input type="text" class="form-control" name="critical_resources" placeholder="e.g., Primary servers, Backup facility, Communication systems"></div>
                <div class="form-group"><label class="form-label">Communication Plan</label><textarea class="form-control" name="communication_plan" rows="2" placeholder="Define communication procedures during activation"></textarea></div>
                <div class="form-row-2"><div class="form-group"><label class="form-label">Testing Schedule</label><input type="text" class="form-control" name="testing_schedule" placeholder="e.g., Quarterly, Semi-annual"></div><div class="form-group"><label class="form-label">Plan Owner</label><input type="text" class="form-control" name="plan_owner" placeholder="Enter responsible person"></div></div>
                <div class="form-group"><label class="form-label">Approval Status</label><select class="form-control" name="status" required><option value="">Select status</option><option>Draft</option><option>Under Review</option><option>Approved</option></select></div>
            </div>
            <div class="modal-footer" style="padding:0.75rem 1.25rem;border-top:1px solid #e5e7eb;background:#fff;display:flex;justify-content:flex-end;gap:0.5rem;"><button type="button" class="btn btn-secondary" id="cancel-resilience-modal">Cancel</button><button type="submit" class="btn btn-primary">Add Plan</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php if ($canOpenLessonsModal): ?>
<div id="lesson-modal" class="modal-overlay compliance-modal" style="display:none;" aria-hidden="true">
    <div class="modal compliance-edit-modal" role="dialog" aria-labelledby="lesson-modal-title" style="width:min(760px,96vw);max-height:92vh;display:flex;flex-direction:column;overflow:hidden;">
        <div class="modal-header"><h2 class="modal-title" id="lesson-modal-title">Add New Lesson</h2><button type="button" class="modal-close" id="close-lesson-modal" aria-label="Close">&times;</button></div>
        <form id="lesson-form" method="post" action="javascript:void(0)" style="display:flex;flex-direction:column;min-height:0;flex:1;">
            <div style="overflow:auto;padding:1rem 1.25rem 0.5rem 1.25rem;flex:1;min-height:0;">
                <div class="form-group"><label class="form-label">Lesson Title</label><input type="text" class="form-control" name="title" placeholder="Brief title for the lesson" required></div>
                <div class="form-row-2"><div class="form-group"><label class="form-label">Category</label><select class="form-control" name="category" required><option value="">Select category</option><option>Incident Response</option><option>Control Failure</option><option>Compliance Gap</option><option>Process Improvement</option></select></div><div class="form-group"><label class="form-label">Impact Level</label><select class="form-control" name="impact" required><option value="">Select impact</option><option>Medium</option><option>Low</option><option>High</option><option>Critical</option></select></div></div>
                <div class="form-group"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2" placeholder="Detailed description of the lesson learned"></textarea></div>
                <div class="form-group"><label class="form-label">What Happened</label><textarea class="form-control" name="what_happened" rows="2" placeholder="Describe what happened during the incident"></textarea></div>
                <div class="form-row-2"><div class="form-group"><label class="form-label">What Went Well</label><textarea class="form-control" name="went_well" rows="2" placeholder="What aspects worked well?"></textarea></div><div class="form-group"><label class="form-label">What Could Improve</label><textarea class="form-control" name="could_improve" rows="2" placeholder="What could be improved?"></textarea></div></div>
                <div class="form-group"><label class="form-label">Root Cause Analysis</label><textarea class="form-control" name="root_cause" rows="2" placeholder="What was the root cause of the issue?"></textarea></div>
                <div class="form-group"><label class="form-label">Preventive Measures</label><textarea class="form-control" name="preventive_measures" rows="2" placeholder="What measures are being implemented to prevent recurrence?"></textarea></div>
                <div class="form-group"><label class="form-label">Process Improvements</label><textarea class="form-control" name="process_improvements" rows="2" placeholder="What process improvements will be made?"></textarea></div>
                <div class="form-row-2"><div class="form-group"><label class="form-label">Documented By</label><input type="text" class="form-control" name="documented_by" placeholder="Person/team documenting this lesson"></div><div class="form-group"><label class="form-label">Implementation Status</label><select class="form-control" name="status" required><option value="">Select status</option><option>Pending</option><option>In Progress</option><option>Completed</option></select></div></div>
            </div>
            <div class="modal-footer" style="padding:0.75rem 1.25rem;border-top:1px solid #e5e7eb;background:#fff;display:flex;justify-content:flex-end;gap:0.5rem;"><button type="button" class="btn btn-secondary" id="cancel-lesson-modal">Cancel</button><button type="submit" class="btn btn-primary">Add Lesson</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
<script>
(function() {
  var uploadTriggers = document.querySelectorAll('.js-itrisk-upload-trigger');
  var fileInput = document.getElementById('itrisk-upload-file');
  if (!uploadTriggers.length || !fileInput) return;
  uploadTriggers.forEach(function(btn) {
    btn.addEventListener('click', function() {
      fileInput.click();
    });
  });
})();
(function() {
  var modal = document.getElementById('tab-detail-modal');
  var titleEl = document.getElementById('tab-detail-title');
  var bodyEl = document.getElementById('tab-detail-body');
  var closeBtn = document.getElementById('close-tab-detail-modal');
  var dismissBtn = document.getElementById('dismiss-tab-detail-modal');
  if (!modal || !titleEl || !bodyEl) return;

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function closeModal() {
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
  }

  function renderDetail(detail) {
    var html = '';
    var subtitle = detail.subtitle ? '<div class="text-muted text-sm" style="margin-top:0.2rem;">' + esc(detail.subtitle) + '</div>' : '';
    html += '<div style="padding-bottom:0.7rem;border-bottom:1px solid #e5e7eb;margin-bottom:0.9rem;">'
      + '<div style="font-size:1.05rem;font-weight:700;color:#0f172a;">' + esc(detail.title || 'Details') + '</div>'
      + subtitle
      + '</div>';

    var sections = Array.isArray(detail.sections) ? detail.sections : [];
    if (!sections.length) {
      bodyEl.innerHTML = html + '<div class="text-muted">No details available.</div>';
      return;
    }

    sections.forEach(function(section) {
      html += '<div style="margin-bottom:1rem;">'
        + '<div style="font-weight:700;margin-bottom:0.45rem;color:#0f172a;">' + esc(section.title || 'Information') + '</div>';
      var fields = Array.isArray(section.fields) ? section.fields : [];
      if (!fields.length) {
        html += '<div class="text-muted text-sm">No data</div></div>';
        return;
      }
      html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0.5rem;">';
      fields.forEach(function(field) {
        html += '<div style="border:1px solid #e2e8f0;border-radius:8px;padding:0.55rem 0.65rem;background:#f8fafc;">'
          + '<div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;font-weight:700;">' + esc(field.label || '') + '</div>'
          + '<div style="font-size:0.95rem;color:#0f172a;margin-top:0.15rem;line-height:1.35;">' + esc(field.value || '—') + '</div>'
          + '</div>';
      });
      html += '</div></div>';
    });
    bodyEl.innerHTML = html;
  }

  function openWithDetail(parsed) {
    titleEl.textContent = parsed.title || 'Details';
    renderDetail(parsed);
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
  }

  document.querySelectorAll('.js-open-tab-detail').forEach(function(el) {
    el.addEventListener('click', function() {
      var raw = el.getAttribute('data-detail');
      if (!raw) return;
      var parsed = null;
      try { parsed = JSON.parse(raw); } catch (e) { return; }
      openWithDetail(parsed);
    });
  });
  window.addEventListener('itrisk-open-tab-detail', function(e) {
    if (!e || !e.detail) return;
    openWithDetail(e.detail);
  });

  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (dismissBtn) dismissBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
})();
(function() {
  var modal = document.getElementById('risk-modal');
  var openers = ['open-risk-modal', 'open-risk-modal-2', 'open-risk-modal-3'];
  function openModal() { if (modal) { modal.style.display = 'flex'; modal.setAttribute('aria-hidden', 'false'); } }
  function closeModal() { if (modal) { modal.style.display = 'none'; modal.setAttribute('aria-hidden', 'true'); } }
  openers.forEach(function(id){ var el = document.getElementById(id); if (el) el.addEventListener('click', openModal); });
  var c1 = document.getElementById('close-risk-modal'); if (c1) c1.addEventListener('click', closeModal);
  var c2 = document.getElementById('cancel-risk-modal'); if (c2) c2.addEventListener('click', closeModal);
  if (modal) { modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); }); }
})();
(function() {
  var modal = document.getElementById('lesson-modal');
  var openers = ['open-lesson-modal', 'open-lesson-modal-2', 'open-lesson-modal-3'];
  function openModal() { if (modal) { modal.style.display = 'flex'; modal.setAttribute('aria-hidden', 'false'); } }
  function closeModal() { if (modal) { modal.style.display = 'none'; modal.setAttribute('aria-hidden', 'true'); } }
  openers.forEach(function(id){ var el = document.getElementById(id); if (el) el.addEventListener('click', openModal); });
  var c1 = document.getElementById('close-lesson-modal'); if (c1) c1.addEventListener('click', closeModal);
  var c2 = document.getElementById('cancel-lesson-modal'); if (c2) c2.addEventListener('click', closeModal);
  if (modal) { modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); }); }
})();
(function() {
  var modal = document.getElementById('compliance-modal');
  var openers = ['open-compliance-modal', 'open-compliance-modal-2', 'open-compliance-modal-3'];
  function openModal() { if (modal) { modal.style.display = 'flex'; modal.setAttribute('aria-hidden', 'false'); } }
  function closeModal() { if (modal) { modal.style.display = 'none'; modal.setAttribute('aria-hidden', 'true'); } }
  openers.forEach(function(id){ var el = document.getElementById(id); if (el) el.addEventListener('click', openModal); });
  var c1 = document.getElementById('close-compliance-modal'); if (c1) c1.addEventListener('click', closeModal);
  var c2 = document.getElementById('cancel-compliance-modal'); if (c2) c2.addEventListener('click', closeModal);
  if (modal) { modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); }); }
})();
(function() {
  var modal = document.getElementById('resilience-modal');
  var openers = ['open-resilience-modal', 'open-resilience-modal-2', 'open-resilience-modal-3'];
  function openModal() { if (modal) { modal.style.display = 'flex'; modal.setAttribute('aria-hidden', 'false'); } }
  function closeModal() { if (modal) { modal.style.display = 'none'; modal.setAttribute('aria-hidden', 'true'); } }
  openers.forEach(function(id){ var el = document.getElementById(id); if (el) el.addEventListener('click', openModal); });
  var c1 = document.getElementById('close-resilience-modal'); if (c1) c1.addEventListener('click', closeModal);
  var c2 = document.getElementById('cancel-resilience-modal'); if (c2) c2.addEventListener('click', closeModal);
  if (modal) { modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); }); }
})();
(function() {
  var modal = document.getElementById('incident-modal');
  var openers = ['open-incident-modal', 'open-incident-modal-2', 'open-incident-modal-3'];
  function openModal() { if (modal) { modal.style.display = 'flex'; modal.setAttribute('aria-hidden', 'false'); } }
  function closeModal() { if (modal) { modal.style.display = 'none'; modal.setAttribute('aria-hidden', 'true'); } }
  openers.forEach(function(id){ var el = document.getElementById(id); if (el) el.addEventListener('click', openModal); });
  var c1 = document.getElementById('close-incident-modal'); if (c1) c1.addEventListener('click', closeModal);
  var c2 = document.getElementById('cancel-incident-modal'); if (c2) c2.addEventListener('click', closeModal);
  if (modal) { modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); }); }
})();
(function() {
  var modal = document.getElementById('anomaly-modal');
  var openers = ['open-anomaly-modal', 'open-anomaly-modal-2', 'open-anomaly-modal-3'];
  function openModal() { if (modal) { modal.style.display = 'flex'; modal.setAttribute('aria-hidden', 'false'); } }
  function closeModal() { if (modal) { modal.style.display = 'none'; modal.setAttribute('aria-hidden', 'true'); } }
  openers.forEach(function(id){ var el = document.getElementById(id); if (el) el.addEventListener('click', openModal); });
  var c1 = document.getElementById('close-anomaly-modal'); if (c1) c1.addEventListener('click', closeModal);
  var c2 = document.getElementById('cancel-anomaly-modal'); if (c2) c2.addEventListener('click', closeModal);
  if (modal) { modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); }); }
})();
// Add-row handlers for tabs whose add forms are local (non-server) so entered data appears in table + detail view.
(function() {
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }
  function badgeClass(value, map, fallback) {
    return map[value] || fallback;
  }
  function buildViewBtn(detailObj) {
    return '<a href="javascript:void(0)" class="btn btn-sm btn-outline-secondary js-open-tab-detail" data-detail="' + esc(JSON.stringify(detailObj)) + '" title="View"><i class="fas fa-eye"></i></a>';
  }
  function bindViewClickFor(el) {
    if (!el) return;
    el.addEventListener('click', function() {
      var modal = document.getElementById('tab-detail-modal');
      var titleEl = document.getElementById('tab-detail-title');
      var bodyEl = document.getElementById('tab-detail-body');
      if (!modal || !titleEl || !bodyEl) return;
      var raw = el.getAttribute('data-detail');
      if (!raw) return;
      var parsed;
      try { parsed = JSON.parse(raw); } catch (e) { return; }
      var evt = new CustomEvent('itrisk-open-tab-detail', { detail: parsed });
      window.dispatchEvent(evt);
    });
  }
  function prependRow(tbodyId, html) {
    var tbody = document.getElementById(tbodyId);
    if (!tbody) return null;
    var first = tbody.querySelector('tr');
    if (first && first.textContent && first.textContent.toLowerCase().indexOf('no ') >= 0) {
      tbody.innerHTML = '';
    }
    tbody.insertAdjacentHTML('afterbegin', html);
    return tbody.firstElementChild;
  }
  function closeModal(modalId) {
    var m = document.getElementById(modalId);
    if (!m) return;
    m.style.display = 'none';
    m.setAttribute('aria-hidden', 'true');
  }
  function currentDateYmd() {
    var d = new Date();
    var m = String(d.getMonth() + 1).padStart(2, '0');
    var day = String(d.getDate()).padStart(2, '0');
    return d.getFullYear() + '-' + m + '-' + day;
  }
  function ensureDetailClick(row) {
    if (!row) return;
    var btn = row.querySelector('.js-open-tab-detail');
    if (btn) bindViewClickFor(btn);
  }

  var incidentForm = document.getElementById('incident-form');
  if (incidentForm) {
    incidentForm.addEventListener('submit', function(e) {
      e.preventDefault();
      var f = new FormData(incidentForm);
      var title = (f.get('title') || '').toString().trim();
      var category = (f.get('category') || '').toString().trim();
      var severity = (f.get('severity') || '').toString().trim();
      if (!title || !category || !severity) return;
      var status = 'Open';
      var reportedBy = (f.get('reported_by') || '').toString().trim() || '—';
      var reportedAt = currentDateYmd();
      var id = 'INC-' + Date.now().toString().slice(-6);
      var detail = {
        title: title,
        subtitle: id,
        sections: [{ title: 'Incident Information', fields: [
          { label: 'Category', value: category }, { label: 'Severity', value: severity }, { label: 'Status', value: status },
          { label: 'Reported By', value: reportedBy }, { label: 'Reported Date', value: reportedAt },
          { label: 'Description', value: (f.get('description') || '').toString().trim() || '—' },
          { label: 'Impacted Services', value: (f.get('impacted_services') || '').toString().trim() || '—' },
          { label: 'Immediate Actions', value: (f.get('immediate_actions') || '').toString().trim() || '—' }
        ]}]
      };
      var row = prependRow('incidents-tbody',
        '<tr><td>' + esc(id) + '</td><td>' + esc(title) + '</td><td>' + esc(category) + '</td><td>' + esc(severity) + '</td><td>' + esc(status) + '</td><td>' + esc(reportedBy) + '</td><td>' + esc(reportedAt) + '</td><td><div style="display:flex;gap:0.35rem;align-items:center;white-space:nowrap;">' + buildViewBtn(detail) + '<a href="javascript:void(0)" class="btn btn-sm btn-secondary" title="Edit"><i class="fas fa-pen"></i></a><button type="button" class="btn btn-sm btn-danger" title="Delete" disabled><i class="fas fa-trash"></i></button></div></td></tr>'
      );
      ensureDetailClick(row);
      incidentForm.reset();
      closeModal('incident-modal');
    });
  }

  var anomalyForm = document.getElementById('anomaly-form');
  if (anomalyForm) {
    anomalyForm.addEventListener('submit', function(e) {
      e.preventDefault();
      var f = new FormData(anomalyForm);
      var type = (f.get('type') || '').toString().trim();
      var severity = (f.get('severity') || '').toString().trim();
      var status = (f.get('status') || '').toString().trim();
      var confidence = (f.get('confidence') || '').toString().trim();
      if (!type || !severity || !status || !confidence) return;
      var id = 'ANO-' + Date.now().toString().slice(-6);
      var detectedOn = currentDateYmd();
      var description = (f.get('description') || '').toString().trim() || '—';
      var detail = { title: id, subtitle: type, sections: [{ title: 'Anomaly Information', fields: [
        { label: 'Description', value: description }, { label: 'Severity', value: severity }, { label: 'Status', value: status },
        { label: 'Confidence', value: confidence }, { label: 'Detected On', value: detectedOn }
      ]}] };
      var sevCls = badgeClass(severity, { Critical: 'badge-danger', High: 'badge-warning', Medium: 'badge-success', Low: 'badge-success' }, 'badge-secondary');
      var stCls = badgeClass(status, { Resolved: 'badge-success', Investigating: 'badge-info', Open: 'badge-primary' }, 'badge-secondary');
      var row = prependRow('anomalies-tbody',
        '<tr><td>' + esc(id) + '</td><td>' + esc(type) + '</td><td>' + esc(description) + '</td><td><span class="badge ' + sevCls + '">' + esc(severity) + '</span></td><td><span class="badge ' + stCls + '">' + esc(status) + '</span></td><td>' + esc(confidence) + '</td><td>' + esc(detectedOn) + '</td><td><div style="display:flex;gap:0.35rem;align-items:center;white-space:nowrap;">' + buildViewBtn(detail) + '<a href="javascript:void(0)" class="btn btn-sm btn-secondary" title="Edit"><i class="fas fa-pen"></i></a><button type="button" class="btn btn-sm btn-danger" title="Delete" disabled><i class="fas fa-trash"></i></button></div></td></tr>'
      );
      ensureDetailClick(row);
      if (typeof window.refreshAnomalyOverview === 'function') window.refreshAnomalyOverview();
      anomalyForm.reset();
      closeModal('anomaly-modal');
    });
  }

  var compForm = document.getElementById('compliance-track-form');
  if (compForm) {
    compForm.addEventListener('submit', function(e) {
      e.preventDefault();
      var f = new FormData(compForm);
      var regulation = (f.get('regulation') || '').toString().trim();
      var status = (f.get('status') || '').toString().trim();
      if (!regulation || !status) return;
      var description = (f.get('description') || '').toString().trim() || '—';
      var department = (f.get('department') || '').toString().trim() || '—';
      var dueDate = (f.get('due_date') || '').toString().trim() || '—';
      var notes = (f.get('assessment_notes') || '').toString().trim() || '—';
      var detail = { title: regulation, subtitle: department, sections: [{ title: 'Requirement Details', fields: [
        { label: 'Related Risk ID', value: (f.get('related_risk_id') || '').toString().trim() || '—' },
        { label: 'Description', value: description }, { label: 'Due Date', value: dueDate }, { label: 'Status', value: status },
        { label: 'Evidence Location', value: (f.get('evidence_location') || '').toString().trim() || '—' },
        { label: 'Assessment Notes', value: notes }, { label: 'Remediation Plan', value: (f.get('remediation_plan') || '').toString().trim() || '—' }
      ]}] };
      var stCls = badgeClass(status, { Compliant: 'badge-success', 'Non-Compliant': 'badge-danger', 'In Progress': 'badge-primary' }, 'badge-secondary');
      var row = prependRow('compliance-track-tbody',
        '<tr><td>' + esc(regulation) + '</td><td>' + esc(description) + '</td><td>' + esc(department) + '</td><td>' + esc(dueDate) + '</td><td><span class="badge ' + stCls + '">' + esc(status) + '</span></td><td>' + esc(notes) + '</td><td><div style="display:flex;gap:0.35rem;align-items:center;white-space:nowrap;">' + buildViewBtn(detail) + '<a href="javascript:void(0)" class="btn btn-sm btn-secondary" title="Edit"><i class="fas fa-pen"></i></a><button type="button" class="btn btn-sm btn-danger" title="Delete" disabled><i class="fas fa-trash"></i></button></div></td></tr>'
      );
      ensureDetailClick(row);
      compForm.reset();
      closeModal('compliance-modal');
    });
  }

  var resilienceForm = document.getElementById('resilience-form');
  if (resilienceForm) {
    resilienceForm.addEventListener('submit', function(e) {
      e.preventDefault();
      var f = new FormData(resilienceForm);
      var planName = (f.get('plan_name') || '').toString().trim();
      var planType = (f.get('plan_type') || '').toString().trim();
      var status = (f.get('status') || '').toString().trim();
      if (!planName || !planType || !status) return;
      var planId = 'RPL-' + Date.now().toString().slice(-6);
      var owner = (f.get('plan_owner') || '').toString().trim() || '—';
      var detail = { title: planName, subtitle: planId, sections: [{ title: 'Plan Details', fields: [
        { label: 'Type', value: planType }, { label: 'Owner', value: owner }, { label: 'Status', value: status },
        { label: 'Description', value: (f.get('description') || '').toString().trim() || '—' },
        { label: 'RTO (Hours)', value: (f.get('rto_hours') || '').toString().trim() || '—' },
        { label: 'RPO (Hours)', value: (f.get('rpo_hours') || '').toString().trim() || '—' },
        { label: 'Activation Triggers', value: (f.get('activation_triggers') || '').toString().trim() || '—' },
        { label: 'Recovery Objectives', value: (f.get('recovery_objectives') || '').toString().trim() || '—' },
        { label: 'Key Personnel', value: (f.get('key_personnel') || '').toString().trim() || '—' },
        { label: 'Critical Resources', value: (f.get('critical_resources') || '').toString().trim() || '—' },
        { label: 'Communication Plan', value: (f.get('communication_plan') || '').toString().trim() || '—' },
        { label: 'Testing Schedule', value: (f.get('testing_schedule') || '').toString().trim() || '—' }
      ]}] };
      var stCls = badgeClass(status, { Approved: 'badge-success', 'Under Review': 'badge-info', Draft: 'badge-warning' }, 'badge-secondary');
      var row = prependRow('resilience-tbody',
        '<tr><td>' + esc(planId) + '</td><td>' + esc(planName) + '</td><td>' + esc(planType) + '</td><td>' + esc(owner) + '</td><td>—</td><td><span class="badge ' + stCls + '">' + esc(status) + '</span></td><td><div style="display:flex;gap:0.35rem;align-items:center;white-space:nowrap;">' + buildViewBtn(detail) + '<a href="javascript:void(0)" class="btn btn-sm btn-secondary" title="Edit"><i class="fas fa-pen"></i></a><button type="button" class="btn btn-sm btn-danger" title="Delete" disabled><i class="fas fa-trash"></i></button></div></td></tr>'
      );
      ensureDetailClick(row);
      resilienceForm.reset();
      closeModal('resilience-modal');
    });
  }

  var lessonForm = document.getElementById('lesson-form');
  if (lessonForm) {
    lessonForm.addEventListener('submit', function(e) {
      e.preventDefault();
      var f = new FormData(lessonForm);
      var title = (f.get('title') || '').toString().trim();
      var category = (f.get('category') || '').toString().trim();
      var impact = (f.get('impact') || '').toString().trim();
      var status = (f.get('status') || '').toString().trim();
      if (!title || !category || !impact || !status) return;
      var documentedBy = (f.get('documented_by') || '').toString().trim() || '—';
      var detail = { title: title, subtitle: 'Category: ' + category, sections: [{ title: 'Lesson Information', fields: [
        { label: 'Impact', value: impact }, { label: 'Status', value: status }, { label: 'Documented By', value: documentedBy },
        { label: 'Description', value: (f.get('description') || '').toString().trim() || '—' },
        { label: 'What Happened', value: (f.get('what_happened') || '').toString().trim() || '—' },
        { label: 'What Went Well', value: (f.get('went_well') || '').toString().trim() || '—' },
        { label: 'What Could Improve', value: (f.get('could_improve') || '').toString().trim() || '—' },
        { label: 'Root Cause', value: (f.get('root_cause') || '').toString().trim() || '—' },
        { label: 'Preventive Measures', value: (f.get('preventive_measures') || '').toString().trim() || '—' },
        { label: 'Process Improvements', value: (f.get('process_improvements') || '').toString().trim() || '—' }
      ]}] };
      var row = prependRow('lessons-tbody',
        '<tr><td>' + esc(title) + '</td><td>' + esc(category) + '</td><td>' + esc(impact) + '</td><td>' + esc(status) + '</td><td>' + esc(documentedBy) + '</td><td><div style="display:flex;gap:0.35rem;align-items:center;white-space:nowrap;">' + buildViewBtn(detail) + '<a href="javascript:void(0)" class="btn btn-sm btn-secondary" title="Edit"><i class="fas fa-pen"></i></a><button type="button" class="btn btn-sm btn-danger" title="Delete" disabled><i class="fas fa-trash"></i></button></div></td></tr>'
      );
      ensureDetailClick(row);
      lessonForm.reset();
      closeModal('lesson-modal');
    });
  }
})();
(function() {
  function parseAnomalyRows() {
    var tbody = document.getElementById('anomalies-tbody');
    if (!tbody) return [];
    var rows = [];
    tbody.querySelectorAll('tr').forEach(function(tr) {
      var tds = tr.querySelectorAll('td');
      if (tds.length < 7) return;
      var type = (tds[1].innerText || '').trim();
      var severity = (tds[3].innerText || '').trim();
      var detectedOnRaw = (tds[6].innerText || '').trim();
      var dateKey = detectedOnRaw.slice(0, 10);
      if (!dateKey) return;
      rows.push({ type: type, severity: severity, dateKey: dateKey });
    });
    return rows;
  }

  function setText(id, value) {
    var el = document.getElementById(id);
    if (el) el.textContent = String(value);
  }

  function renderKpis(rows) {
    var buckets = {
      transaction: { total: 0, critical: 0 },
      system: { total: 0, critical: 0 },
      customer: { total: 0, critical: 0 },
      process: { total: 0, critical: 0 }
    };
    rows.forEach(function(r) {
      var k = r.type === 'Transaction' ? 'transaction'
        : (r.type === 'System Access' ? 'system'
        : (r.type === 'Customer Behavior' ? 'customer' : 'process'));
      buckets[k].total += 1;
      if (r.severity === 'Critical') buckets[k].critical += 1;
    });
    setText('anomaly-kpi-transaction-total', buckets.transaction.total);
    setText('anomaly-kpi-transaction-critical', buckets.transaction.critical);
    setText('anomaly-kpi-system-total', buckets.system.total);
    setText('anomaly-kpi-system-critical', buckets.system.critical);
    setText('anomaly-kpi-customer-total', buckets.customer.total);
    setText('anomaly-kpi-customer-critical', buckets.customer.critical);
    setText('anomaly-kpi-process-total', buckets.process.total);
    setText('anomaly-kpi-process-critical', buckets.process.critical);
  }

  function renderTrend(rows) {
    var host = document.getElementById('anomaly-trend-chart');
    if (!host) return;
    if (!rows.length) {
      host.innerHTML = '<div class="text-muted text-sm" style="position:absolute;left:12px;top:10px;">No anomaly trend data.</div>';
      return;
    }
    var byDate = {};
    rows.forEach(function(r) {
      if (!byDate[r.dateKey]) byDate[r.dateKey] = { Transaction: 0, 'System Access': 0, 'Customer Behavior': 0, Process: 0 };
      var key = (r.type === 'Transaction' || r.type === 'System Access' || r.type === 'Customer Behavior') ? r.type : 'Process';
      byDate[r.dateKey][key] += 1;
    });
    var dates = Object.keys(byDate).sort();
    var series = {
      Transaction: dates.map(function(d){ return byDate[d].Transaction; }),
      'System Access': dates.map(function(d){ return byDate[d]['System Access']; }),
      'Customer Behavior': dates.map(function(d){ return byDate[d]['Customer Behavior']; }),
      Process: dates.map(function(d){ return byDate[d].Process; })
    };
    var width = host.clientWidth || 900;
    var height = host.clientHeight || 180;
    var pad = { l: 40, r: 14, t: 12, b: 28 };
    var plotW = width - pad.l - pad.r;
    var plotH = height - pad.t - pad.b;
    var maxVal = 1;
    Object.keys(series).forEach(function(k){ series[k].forEach(function(v){ if (v > maxVal) maxVal = v; }); });
    maxVal = Math.max(2, maxVal);
    function x(i){ return pad.l + (dates.length <= 1 ? 0 : (i * (plotW / (dates.length - 1)))); }
    function y(v){ return pad.t + (plotH - (v / maxVal) * plotH); }
    function path(vals) {
      if (!vals.length) return '';
      var d = 'M ' + x(0) + ' ' + y(vals[0]);
      for (var i = 1; i < vals.length; i++) d += ' L ' + x(i) + ' ' + y(vals[i]);
      return d;
    }
    var colors = { Transaction: '#ef4444', 'System Access': '#f59e0b', 'Customer Behavior': '#3b82f6', Process: '#10b981' };
    var svg = '<svg viewBox="0 0 ' + width + ' ' + height + '" style="width:100%;height:100%;">';
    for (var t = 0; t <= maxVal; t++) {
      var yy = y(t);
      svg += '<line x1="' + pad.l + '" y1="' + yy + '" x2="' + (width - pad.r) + '" y2="' + yy + '" stroke="#eef2f7"/>';
      svg += '<text x="' + (pad.l - 8) + '" y="' + (yy + 3) + '" text-anchor="end" fill="#94a3b8" font-size="10">' + t + '</text>';
    }
    svg += '<line x1="' + pad.l + '" y1="' + (height - pad.b) + '" x2="' + (width - pad.r) + '" y2="' + (height - pad.b) + '" stroke="#cbd5e1"/>';
    svg += '<line x1="' + pad.l + '" y1="' + pad.t + '" x2="' + pad.l + '" y2="' + (height - pad.b) + '" stroke="#cbd5e1"/>';
    Object.keys(series).forEach(function(name) {
      svg += '<path d="' + path(series[name]) + '" fill="none" stroke="' + colors[name] + '" stroke-width="2"/>';
      series[name].forEach(function(v, i) {
        svg += '<circle cx="' + x(i) + '" cy="' + y(v) + '" r="2.8" fill="' + colors[name] + '" />';
      });
    });
    dates.forEach(function(d, i) {
      if (i === 0 || i === dates.length - 1 || i % 2 === 0) {
        svg += '<text x="' + x(i) + '" y="' + (height - 6) + '" text-anchor="middle" fill="#64748b" font-size="10">' + d.slice(5) + '</text>';
      }
    });
    svg += '<line id="anomaly-trend-hover-line" x1="' + pad.l + '" y1="' + pad.t + '" x2="' + pad.l + '" y2="' + (height - pad.b) + '" stroke="#94a3b8" stroke-dasharray="4 3" style="display:none;"/>';
    svg += '<rect id="anomaly-trend-hit" x="' + pad.l + '" y="' + pad.t + '" width="' + plotW + '" height="' + plotH + '" fill="transparent" />';
    svg += '</svg>';
    host.innerHTML = svg + '<div id="anomaly-trend-tip" style="display:none;position:absolute;z-index:3;background:#111827;color:#fff;border-radius:6px;padding:6px 8px;font-size:11px;line-height:1.35;pointer-events:none;"></div>';

    var hit = host.querySelector('#anomaly-trend-hit');
    var hoverLine = host.querySelector('#anomaly-trend-hover-line');
    var tip = host.querySelector('#anomaly-trend-tip');
    if (!hit || !hoverLine || !tip) return;
    hit.addEventListener('mousemove', function(evt) {
      var rect = host.getBoundingClientRect();
      var px = evt.clientX - rect.left;
      var rel = Math.max(0, Math.min(plotW, px - pad.l));
      var idx = dates.length <= 1 ? 0 : Math.round((rel / plotW) * (dates.length - 1));
      var lineX = x(idx);
      hoverLine.setAttribute('x1', String(lineX));
      hoverLine.setAttribute('x2', String(lineX));
      hoverLine.style.display = 'block';
      tip.style.display = 'block';
      tip.style.left = Math.min(width - 180, Math.max(6, lineX + 8)) + 'px';
      tip.style.top = '8px';
      tip.innerHTML = '<strong>' + dates[idx] + '</strong><br>'
        + 'Txn: ' + series.Transaction[idx] + ' | Sys: ' + series['System Access'][idx] + '<br>'
        + 'Cust: ' + series['Customer Behavior'][idx] + ' | Proc: ' + series.Process[idx];
    });
    hit.addEventListener('mouseleave', function() {
      hoverLine.style.display = 'none';
      tip.style.display = 'none';
    });
  }

  function refreshAnomalyOverview() {
    var rows = parseAnomalyRows();
    renderKpis(rows);
    renderTrend(rows);
  }

  window.refreshAnomalyOverview = refreshAnomalyOverview;
  refreshAnomalyOverview();
})();

(function() {
  var runBtn = document.getElementById('run-anomaly-detection-btn');
  var tbody = document.getElementById('anomalies-tbody');
  if (!runBtn || !tbody) return;

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }
  function ymdNow() {
    var d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }
  function makeViewButton(detailObj) {
    return '<a href="javascript:void(0)" class="btn btn-sm btn-outline-secondary js-detected-view" data-detail="' + esc(JSON.stringify(detailObj)) + '" title="View"><i class="fas fa-eye"></i></a>';
  }
  function bindDetectedView(btn) {
    if (!btn) return;
    btn.addEventListener('click', function() {
      var raw = btn.getAttribute('data-detail');
      if (!raw) return;
      try {
        window.dispatchEvent(new CustomEvent('itrisk-open-tab-detail', { detail: JSON.parse(raw) }));
      } catch (e) {}
    });
  }

  runBtn.addEventListener('click', function() {
    var detectedOn = ymdNow();
    var seeds = [
      {
        type: 'System Access',
        description: 'Multiple privileged login attempts detected outside approved maintenance window.',
        severity: 'High',
        status: 'Investigating',
        confidence: 'High'
      },
      {
        type: 'Transaction',
        description: 'Repeated near-threshold transfers detected across linked accounts.',
        severity: 'Medium',
        status: 'Open',
        confidence: 'Medium'
      }
    ];

    // Remove empty-state row if present.
    var first = tbody.querySelector('tr');
    if (first && /no\s+/i.test(first.textContent || '')) {
      tbody.innerHTML = '';
    }

    seeds.forEach(function(seed, idx) {
      var id = 'ANO-' + String(Date.now() + idx).slice(-6);
      var sevCls = seed.severity === 'Critical' ? 'badge-danger' : (seed.severity === 'High' ? 'badge-warning' : 'badge-success');
      var stCls = seed.status === 'Resolved' ? 'badge-success' : (seed.status === 'Investigating' ? 'badge-info' : 'badge-primary');
      var detail = {
        title: id,
        subtitle: seed.type,
        sections: [{
          title: 'Anomaly Information',
          fields: [
            { label: 'Description', value: seed.description },
            { label: 'Severity', value: seed.severity },
            { label: 'Status', value: seed.status },
            { label: 'Confidence', value: seed.confidence },
            { label: 'Detected On', value: detectedOn }
          ]
        }]
      };
      var html = '<tr>'
        + '<td>' + esc(id) + '</td>'
        + '<td>' + esc(seed.type) + '</td>'
        + '<td>' + esc(seed.description) + '</td>'
        + '<td><span class="badge ' + sevCls + '">' + esc(seed.severity) + '</span></td>'
        + '<td><span class="badge ' + stCls + '">' + esc(seed.status) + '</span></td>'
        + '<td>' + esc(seed.confidence) + '</td>'
        + '<td>' + esc(detectedOn) + '</td>'
        + '<td><div style="display:flex;gap:0.35rem;align-items:center;white-space:nowrap;">'
        + makeViewButton(detail)
        + '<a href="javascript:void(0)" class="btn btn-sm btn-secondary" title="Edit"><i class="fas fa-pen"></i></a>'
        + '<button type="button" class="btn btn-sm btn-danger" title="Delete" disabled><i class="fas fa-trash"></i></button>'
        + '</div></td>'
        + '</tr>';
      tbody.insertAdjacentHTML('afterbegin', html);
      bindDetectedView(tbody.querySelector('.js-detected-view'));
    });

    runBtn.innerHTML = '<i class="fas fa-check"></i> Detection Complete';
    if (typeof window.refreshAnomalyOverview === 'function') window.refreshAnomalyOverview();
    setTimeout(function() {
      runBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Run Detection';
    }, 1400);
  });
})();
(function() {
  var activeTab = <?= json_encode($activeTab ?? '') ?>;

  function closestRow(el) { return el ? el.closest('tr') : null; }
  function getCell(row, idx) {
    if (!row) return '';
    var cells = row.querySelectorAll('td');
    if (!cells[idx]) return '';
    return (cells[idx].innerText || cells[idx].textContent || '').trim();
  }
  function openModalById(id) {
    var m = document.getElementById(id);
    if (!m) return;
    m.style.display = 'flex';
    m.setAttribute('aria-hidden', 'false');
  }
  function detailFromRow(row) {
    if (!row) return null;
    var btn = row.querySelector('.js-open-tab-detail, .js-detected-view');
    if (!btn) return null;
    var raw = btn.getAttribute('data-detail');
    if (!raw) return null;
    try { return JSON.parse(raw); } catch (e) { return null; }
  }
  function setIf(form, name, value) {
    if (!form) return;
    var el = form.querySelector('[name="' + name + '"]');
    if (el) el.value = value || '';
  }
  function normalizeStatus(txt) {
    if (!txt) return '';
    var v = txt.trim();
    if (v === 'In Progress') return 'In Progress';
    if (v === 'Under Review') return 'Under Review';
    if (v === 'Non-Compliant') return 'Non-Compliant';
    return v;
  }
  function removeRow(btn) {
    var row = closestRow(btn);
    if (!row) return;
    if (!confirm('Delete this item?')) return;
    var anomaliesTable = row.closest('#anomalies-tbody');
    row.remove();
    if (anomaliesTable && typeof window.refreshAnomalyOverview === 'function') {
      window.refreshAnomalyOverview();
    }
  }

  // Enable non-working disabled delete buttons for local tabs.
  document.querySelectorAll('button[title="Delete"][disabled]').forEach(function(btn) {
    if (closestRow(btn) && btn.closest('table')) btn.removeAttribute('disabled');
  });

  document.addEventListener('click', function(e) {
    var t = e.target;
    if (!t) return;
    var editBtn = t.closest('a[title="Edit"]');
    if (editBtn && editBtn.getAttribute('href') === 'javascript:void(0)') {
      e.preventDefault();
      var row = closestRow(editBtn);
      var detail = detailFromRow(row);

      // Open + prefill correct modal by current tab.
      if (activeTab === 'incidents') {
        var f1 = document.getElementById('incident-form');
        if (f1) {
          setIf(f1, 'title', getCell(row, 1));
          setIf(f1, 'category', getCell(row, 2));
          setIf(f1, 'severity', getCell(row, 3));
          setIf(f1, 'reported_by', getCell(row, 5));
          if (detail && detail.sections && detail.sections[0] && detail.sections[0].fields) {
            var fields = detail.sections[0].fields;
            fields.forEach(function(x){
              if (x.label === 'Description') setIf(f1, 'description', x.value);
              if (x.label === 'Impacted Services') setIf(f1, 'impacted_services', x.value);
              if (x.label === 'Immediate Actions') setIf(f1, 'immediate_actions', x.value);
            });
          }
        }
        openModalById('incident-modal');
        return;
      }
      if (activeTab === 'anomalies') {
        var f2 = document.getElementById('anomaly-form');
        if (f2) {
          setIf(f2, 'type', getCell(row, 1));
          setIf(f2, 'severity', getCell(row, 3));
          setIf(f2, 'status', getCell(row, 4));
          setIf(f2, 'confidence', getCell(row, 5));
          if (detail && detail.sections && detail.sections[0] && detail.sections[0].fields) {
            detail.sections[0].fields.forEach(function(x){
              if (x.label === 'Description') setIf(f2, 'description', x.value);
            });
          } else {
            setIf(f2, 'description', getCell(row, 2));
          }
        }
        openModalById('anomaly-modal');
        return;
      }
      if (activeTab === 'compliance') {
        var f3 = document.getElementById('compliance-track-form');
        if (f3) {
          setIf(f3, 'regulation', getCell(row, 0));
          setIf(f3, 'description', getCell(row, 1));
          setIf(f3, 'department', getCell(row, 2));
          setIf(f3, 'due_date', getCell(row, 3));
          setIf(f3, 'status', normalizeStatus(getCell(row, 4)));
          setIf(f3, 'assessment_notes', getCell(row, 5));
        }
        openModalById('compliance-modal');
        return;
      }
      if (activeTab === 'resilience') {
        var f4 = document.getElementById('resilience-form');
        if (f4) {
          setIf(f4, 'plan_name', getCell(row, 1));
          setIf(f4, 'plan_type', getCell(row, 2));
          setIf(f4, 'plan_owner', getCell(row, 3));
          setIf(f4, 'status', normalizeStatus(getCell(row, 5)));
        }
        openModalById('resilience-modal');
        return;
      }
      if (activeTab === 'lessons') {
        var f5 = document.getElementById('lesson-form');
        if (f5) {
          setIf(f5, 'title', getCell(row, 0));
          setIf(f5, 'category', getCell(row, 1));
          setIf(f5, 'impact', getCell(row, 2));
          setIf(f5, 'status', normalizeStatus(getCell(row, 3)));
          setIf(f5, 'documented_by', getCell(row, 4));
        }
        openModalById('lesson-modal');
      }
      return;
    }

    var delBtn = t.closest('button[title="Delete"]');
    if (delBtn && delBtn.closest('table') && !delBtn.closest('form')) {
      e.preventDefault();
      removeRow(delBtn);
      return;
    }

    var dlBtn = t.closest('a[title="Download"]');
    if (dlBtn && dlBtn.getAttribute('href') === 'javascript:void(0)') {
      e.preventDefault();
      var row = closestRow(dlBtn);
      if (!row) return;
      var filename = getCell(row, 1) || ('upload-' + Date.now() + '.txt');
      var lines = [];
      row.querySelectorAll('td').forEach(function(td, i) {
        lines.push('Column ' + (i + 1) + ': ' + (td.innerText || '').trim());
      });
      var blob = new Blob([lines.join('\n')], { type: 'text/plain;charset=utf-8' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = filename.replace(/[^\w.\-]+/g, '_') + '.txt';
      document.body.appendChild(a);
      a.click();
      a.remove();
      setTimeout(function(){ URL.revokeObjectURL(a.href); }, 1000);
    }
  });

  // Upload history filters/search.
  var uploadTbody = document.getElementById('upload-history-tbody');
  if (uploadTbody) {
    var uploadCard = uploadTbody.closest('.card');
    var search = uploadCard ? uploadCard.querySelector('input[type="search"]') : null;
    var selects = uploadCard ? uploadCard.querySelectorAll('select.form-control') : [];
    var clearBtn = uploadCard ? uploadCard.querySelector('button.btn-outline.btn-sm') : null;
    function applyUploadFilters() {
      var q = search ? (search.value || '').toLowerCase().trim() : '';
      var typeVal = selects[0] ? (selects[0].value || 'All Types') : 'All Types';
      var statusVal = selects[1] ? (selects[1].value || 'All Status') : 'All Status';
      uploadTbody.querySelectorAll('tr').forEach(function(row) {
        var fileName = getCell(row, 1).toLowerCase();
        var type = getCell(row, 2);
        var status = getCell(row, 5);
        var okQ = !q || fileName.indexOf(q) >= 0;
        var okType = (typeVal === 'All Types') || (type === typeVal);
        var okStatus = (statusVal === 'All Status') || (status === statusVal);
        row.style.display = (okQ && okType && okStatus) ? '' : 'none';
      });
    }
    if (search) search.addEventListener('input', applyUploadFilters);
    selects.forEach(function(s){ s.addEventListener('change', applyUploadFilters); });
    if (clearBtn) clearBtn.addEventListener('click', function() {
      if (search) search.value = '';
      if (selects[0]) selects[0].value = 'All Types';
      if (selects[1]) selects[1].value = 'All Status';
      applyUploadFilters();
    });
  }
})();
// Auto-apply dropdown filters for compact toolbar UX.
(function() {
  var form = document.getElementById('risk-filter-form');
  if (!form) return;
  var auto = form.querySelectorAll('.auto-submit-filter');
  auto.forEach(function(el){ el.addEventListener('change', function(){ form.submit(); }); });
})();
(function() {
  function renderKriChart(targetId, config) {
    var target = document.getElementById(targetId);
    if (!target) return;

    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var actual = config.actual || [];
    var threshold = config.threshold || [];
    var maxVal = config.maxVal || 140;
    var yTicks = config.yTicks || [0, 35, 70, 105, 140];

    var width = target.clientWidth || 900;
    var height = target.clientHeight || 230;
    var pad = { top: 14, right: 16, bottom: 30, left: 44 };
    var plotW = width - pad.left - pad.right;
    var plotH = height - pad.top - pad.bottom;

    function x(i) { return pad.left + (i * (plotW / (months.length - 1))); }
    function y(v) { return pad.top + (plotH - ((v / maxVal) * plotH)); }

    function smoothPath(vals) {
      if (!vals.length) return '';
      var coords = vals.map(function(v, i) { return { x: x(i), y: y(v) }; });
      var d = 'M ' + coords[0].x.toFixed(2) + ' ' + coords[0].y.toFixed(2);
      for (var i = 0; i < coords.length - 1; i++) {
        var p0 = coords[i];
        var p1 = coords[i + 1];
        var cx1 = p0.x + (p1.x - p0.x) * 0.35;
        var cy1 = p0.y;
        var cx2 = p1.x - (p1.x - p0.x) * 0.35;
        var cy2 = p1.y;
        d += ' C ' + cx1.toFixed(2) + ' ' + cy1.toFixed(2) + ', ' + cx2.toFixed(2) + ' ' + cy2.toFixed(2) + ', ' + p1.x.toFixed(2) + ' ' + p1.y.toFixed(2);
      }
      return d;
    }

    var actualPath = smoothPath(actual);
    var thresholdPath = smoothPath(threshold);

    var grid = '';
    yTicks.forEach(function(t) {
      grid += '<line x1="' + pad.left + '" y1="' + y(t) + '" x2="' + (width - pad.right) + '" y2="' + y(t) + '" stroke="' + (t === 0 ? '#cbd5e1' : '#e2e8f0') + '" stroke-dasharray="' + (t === 0 ? '' : '4 4') + '"/>';
      grid += '<text x="' + (pad.left - 8) + '" y="' + (y(t) + 4) + '" text-anchor="end" fill="#64748b" font-size="11">' + t + '</text>';
    });

    var monthTicks = '';
    months.forEach(function(m, i) {
      monthTicks += '<line x1="' + x(i) + '" y1="' + pad.top + '" x2="' + x(i) + '" y2="' + (height - pad.bottom) + '" stroke="#eef2f7" stroke-dasharray="2 4"/>';
      monthTicks += '<text x="' + x(i) + '" y="' + (height - 8) + '" text-anchor="middle" fill="#64748b" font-size="11">' + m + '</text>';
    });

    var dots = '';
    actual.forEach(function(v, i) {
      dots += '<circle data-i="' + i + '" data-a="' + v + '" data-t="' + threshold[i] + '" cx="' + x(i) + '" cy="' + y(v) + '" r="3.5" fill="#2563eb" stroke="#fff" stroke-width="1.5" style="cursor:pointer;" />';
    });

    target.innerHTML = ''
      + '<svg viewBox="0 0 ' + width + ' ' + height + '" style="width:100%;height:100%;">'
      + grid
      + monthTicks
      + '<path d="' + thresholdPath + '" fill="none" stroke="#ef4444" stroke-width="1.6" stroke-dasharray="5 4"/>'
      + '<path d="' + actualPath + '" fill="none" stroke="#2563eb" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>'
      + dots
      + '<line class="kri-active-line" x1="' + x(0) + '" y1="' + pad.top + '" x2="' + x(0) + '" y2="' + (height - pad.bottom) + '" stroke="#cbd5e1" stroke-width="1" style="display:none;"/>'
      + '<circle class="kri-active-dot" cx="' + x(0) + '" cy="' + y(actual[0] || 0) + '" r="5" fill="#2563eb" stroke="#fff" stroke-width="2" style="display:none;"/>'
      + '</svg>'
      + '<div class="kri-chart-tip" style="display:none;position:absolute;z-index:2;background:#fff;border:1px solid #d1d5db;border-radius:6px;padding:8px 10px;font-size:12px;line-height:1.35;box-shadow:0 8px 22px rgba(0,0,0,0.12);min-width:120px;transition:opacity 140ms ease, transform 140ms ease;"></div>';

    var tip = target.querySelector('.kri-chart-tip');
    var circles = target.querySelectorAll('circle[data-i]');
    var activeLine = target.querySelector('.kri-active-line');
    var activeDot = target.querySelector('.kri-active-dot');

    function formatNum(v) {
      var n = Number(v);
      if (!isFinite(n)) return String(v);
      if (Math.abs(n) < 1) return n.toFixed(3).replace(/0+$/, '').replace(/\.$/, '');
      return n.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
    }
    function showPoint(i) {
      var a = Number(actual[i] || 0);
      var t = Number(threshold[i] || 0);
      tip.innerHTML = '<div style="font-weight:600;margin-bottom:4px;">' + months[i] + '</div>'
        + '<div style="color:#2563eb;">Actual : ' + formatNum(a) + '</div>'
        + '<div style="color:#ef4444;">Threshold : ' + formatNum(t) + '</div>';
      tip.style.display = 'block';
      tip.style.opacity = '1';
      tip.style.transform = 'translateY(0)';
      tip.style.left = Math.max(8, Math.min(width - 155, x(i) + 10)) + 'px';
      tip.style.top = Math.max(6, y(a) - 54) + 'px';
      if (activeLine) {
        activeLine.style.display = 'block';
        activeLine.style.transition = 'all 140ms ease';
        activeLine.setAttribute('x1', String(x(i)));
        activeLine.setAttribute('x2', String(x(i)));
      }
      if (activeDot) {
        activeDot.style.display = 'block';
        activeDot.style.transition = 'all 140ms ease';
        activeDot.setAttribute('cx', String(x(i)));
        activeDot.setAttribute('cy', String(y(a)));
      }
    }
    function hidePoint() {
      tip.style.opacity = '0';
      tip.style.transform = 'translateY(-3px)';
      setTimeout(function(){ if (tip.style.opacity === '0') tip.style.display = 'none'; }, 120);
      if (activeLine) activeLine.style.display = 'none';
      if (activeDot) activeDot.style.display = 'none';
    }

    circles.forEach(function(c) {
      c.addEventListener('mouseenter', function() {
        showPoint(parseInt(c.getAttribute('data-i') || '0', 10));
      });
      c.addEventListener('mousemove', function() {
        showPoint(parseInt(c.getAttribute('data-i') || '0', 10));
      });
      c.addEventListener('touchstart', function() {
        showPoint(parseInt(c.getAttribute('data-i') || '0', 10));
      }, { passive: true });
      c.addEventListener('mouseleave', hidePoint);
    });
    target.addEventListener('mouseleave', hidePoint);
  }

  renderKriChart('kri-chart-system', {
    actual: [72, 75, 74, 82, 96, 97.58, 95, 106, 116, 120, 128, 124],
    threshold: [99.5, 99.5, 99.5, 99.5, 99.5, 99.5, 99.5, 99.5, 99.5, 99.5, 99.5, 99.5],
    maxVal: 140,
    yTicks: [0, 35, 70, 105, 140]
  });
  renderKriChart('kri-chart-failure', {
    actual: [0.03, 0.03, 0.05, 0.05, 0.04, 0.05, 0.04, 0.05, 0.05, 0.06, 0.06, 0.07],
    threshold: [0.10, 0.10, 0.10, 0.10, 0.10, 0.10, 0.10, 0.10, 0.10, 0.10, 0.10, 0.10],
    maxVal: 0.11,
    yTicks: [0, 0.025, 0.05, 0.075, 0.1]
  });
})();
</script>
