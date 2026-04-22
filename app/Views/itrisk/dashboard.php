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
$compliances = $compliances ?? [];
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<div class="page-header" style="margin-bottom:0.5rem;">
    <div>
        <h1 class="page-title"><?= $activeTab === 'it-dashboard' ? 'IT Dashboard' : 'IT Risk' ?></h1>
        <p class="page-subtitle"><?= $activeTab === 'it-dashboard' ? 'Monitor IT compliance, risk posture, controls, and KRIs from one place.' : 'Identify and classify IT/InfoSec risks across the organization.' ?></p>
    </div>
    <?php if ($activeTab === 'it-dashboard'): ?>
    <a href="<?= htmlspecialchars($basePath) ?>/compliances/create" class="btn btn-primary"><i class="fas fa-plus"></i> Create IT Compliance</a>
    <?php elseif ($canOpenRiskModal): ?>
    <button type="button" class="btn btn-primary" id="open-risk-modal"><i class="fas fa-plus"></i> <?= $activeTab === 'controls' ? 'Add Control' : ($activeTab === 'kris' ? 'Add KRI' : 'Add Risk') ?></button>
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
        <?php elseif ($canOpenRiskModal): ?>
        <button type="button" class="btn btn-primary btn-sm" id="open-risk-modal-2"><i class="fas fa-shield-alt"></i> <?= $activeTab === 'controls' ? 'Add Control' : ($activeTab === 'kris' ? 'Add KRI' : 'Assess Risk') ?></button>
        <?php endif; ?>
    </div>
</div>
<div class="card mt-3">
    <div class="page-header" style="margin-bottom:0.5rem;">
        <div>
            <h3 class="card-title" style="margin-bottom:0.15rem;">
                <?= $activeTab === 'it-dashboard' ? 'IT Compliance Overview' : ($activeTab === 'assessment' ? 'Risk Assessment' : ($activeTab === 'controls' ? 'Control Management' : ($activeTab === 'kris' ? 'Key Risk Indicators' : 'Risk Identification'))) ?>
            </h3>
            <p class="text-muted text-sm mb-0">
                <?= $activeTab === 'it-dashboard'
                    ? 'Track the overall health of IT compliance and operational risk controls.'
                    : ($activeTab === 'assessment'
                    ? 'Analyze and evaluate identified risks across the organization'
                    : ($activeTab === 'controls' ? 'Define and monitor controls to mitigate operational risks' : ($activeTab === 'kris' ? 'Monitor critical operational risk indicators across departments' : 'Identify and classify operational risks across the organization'))) ?>
            </p>
        </div>
        <div style="display:flex;gap:0.5rem;">
            <?php if ($activeTab === 'it-dashboard'): ?>
            <a href="<?= htmlspecialchars($basePath) ?>/itrisk/dashboard?tab=controls" class="btn btn-secondary btn-sm"><i class="fas fa-shield-alt"></i> Controls</a>
            <a href="<?= htmlspecialchars($basePath) ?>/itrisk/dashboard?tab=kris" class="btn btn-primary btn-sm"><i class="fas fa-chart-line"></i> KRIs</a>
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
                            <a href="javascript:void(0)" class="btn btn-sm btn-outline-secondary" title="View"><i class="fas fa-eye"></i></a>
                            <a href="javascript:void(0)" class="btn btn-sm btn-secondary" title="Edit"><i class="fas fa-pen"></i></a>
                            <?php if (($user['role_slug'] ?? '') === 'admin'): ?><button type="button" class="btn btn-sm btn-danger" title="Delete" disabled><i class="fas fa-trash"></i></button><?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php elseif ($activeTab === 'kris'): ?>
                <?php foreach ($kris as $k): ?>
                <tr>
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
                            <a href="javascript:void(0)" class="btn btn-sm btn-outline-secondary" title="View"><i class="fas fa-eye"></i></a>
                            <a href="javascript:void(0)" class="btn btn-sm btn-secondary" title="Edit"><i class="fas fa-pen"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <?php foreach ($items as $r): ?>
                <tr>
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
                            <a href="<?= htmlspecialchars($basePath) ?>/itrisk/view/<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View"><i class="fas fa-eye"></i></a>
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
<script>
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
