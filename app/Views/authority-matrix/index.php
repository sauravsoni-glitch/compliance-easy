<?php
$basePath = $basePath ?? '';
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$view = $view ?? 'cards';
$qBase = function (string $tabView) use ($basePath, $filterQ, $filterDept) {
    return $basePath . '/authority-matrix?' . http_build_query(array_filter([
        'q' => $filterQ ?? '',
        'dept' => $filterDept ?? '',
        'view' => $tabView,
    ], fn ($v) => $v !== '' && $v !== null));
};
function am_flow_name(array $row, string $slot): string {
    if ($slot === 'maker') {
        return htmlspecialchars($row['maker_name'] ?? '—');
    }
    if ($slot === 'reviewer') {
        $t = trim($row['reviewer_role_label'] ?? '');

        return htmlspecialchars($t !== '' ? $t : ($row['reviewer_name'] ?? '—'));
    }
    $t = trim($row['approver_role_label'] ?? '');

    return htmlspecialchars($t !== '' ? $t : ($row['approver_name'] ?? '—'));
}
$isSingle = function (array $r) {
    return ($r['workflow_level'] ?? '') === 'Single-Level' || empty($r['reviewer_id']);
};
$riskClass = function ($r) {
    $x = strtolower($r['risk_level'] ?? 'medium');

    return $x === 'high' ? 'am-risk-high' : ($x === 'low' ? 'am-risk-low' : 'am-risk-med');
};
$riskLabel = function ($r) {
    return htmlspecialchars(ucfirst($r['risk_level'] ?? 'Medium')) . ' Risk';
};
?>
<div class="am-page">
    <div class="am-head">
        <div>
            <h1 class="page-title am-title-row">
                Authority Matrix
                <span class="am-info-ico" title="Define compliance workflows and approval chains"><i class="fas fa-info-circle"></i></span>
            </h1>
            <p class="page-subtitle">Define compliance ownership, accountability, and workflow structure across departments.</p>
        </div>
        <div class="am-head-actions">
            <a href="<?= htmlspecialchars($basePath) ?>/authority-matrix/export" class="btn btn-secondary"><i class="fas fa-download"></i> Export</a>
            <a href="<?= htmlspecialchars($basePath) ?>/authority-matrix/add" class="btn btn-primary"><i class="fas fa-plus"></i> Add Rule</a>
        </div>
    </div>
    <?php if ($flashSuccess): ?><div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

    <div class="am-kpi-row">
        <div class="am-kpi am-kpi-red">
            <div class="am-kpi-ico"><i class="fas fa-shield-alt"></i></div>
            <div><div class="am-kpi-val"><?= (int)($total ?? 0) ?></div><div class="am-kpi-lbl">Total Compliance Areas</div></div>
        </div>
        <div class="am-kpi am-kpi-green">
            <div class="am-kpi-ico"><i class="fas fa-check-circle"></i></div>
            <div><div class="am-kpi-val"><?= (int)($active ?? 0) ?></div><div class="am-kpi-lbl">Active Workflows</div></div>
        </div>
        <div class="am-kpi am-kpi-yellow">
            <div class="am-kpi-ico"><i class="fas fa-building"></i></div>
            <div><div class="am-kpi-val"><?= (int)($departmentsCount ?? 0) ?></div><div class="am-kpi-lbl">Departments Covered</div></div>
        </div>
        <a href="<?= htmlspecialchars($qBase('table')) ?>#am-workflow-levels" class="am-kpi am-kpi-red2 am-kpi-link" title="Open all mappings — Level column shows workflow type per rule">
            <div class="am-kpi-ico"><i class="fas fa-layer-group"></i></div>
            <div><div class="am-kpi-val"><?= (int)($workflowLevelsKpi ?? 0) ?></div><div class="am-kpi-lbl">Workflow Levels</div></div>
        </a>
    </div>

    <div class="card am-filter-card">
        <form method="get" action="<?= htmlspecialchars($basePath) ?>/authority-matrix" class="am-filters">
            <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
            <input type="search" name="q" class="form-control am-search" placeholder="Search by compliance area, department, or owner…" value="<?= htmlspecialchars($filterQ ?? '') ?>">
            <select name="dept" class="form-control">
                <option value="">All Departments</option>
                <?php foreach ($departmentOptions ?? [] as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>" <?= ($filterDept ?? '') === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary">Filter</button>
        </form>
    </div>

    <div class="am-view-tabs">
        <a href="<?= htmlspecialchars($qBase('cards')) ?>" class="am-vtab <?= $view === 'cards' ? 'active' : '' ?>"><i class="fas fa-th-large"></i> Workflow cards</a>
        <a href="<?= htmlspecialchars($qBase('table')) ?>" class="am-vtab <?= $view === 'table' ? 'active' : '' ?>"><i class="fas fa-list"></i> All mappings</a>
    </div>

    <?php if ($view === 'cards'): ?>
    <p class="am-count-line text-muted">Showing <?= count($items ?? []) ?> compliance workflows</p>
    <div class="am-card-grid">
        <?php foreach ($items ?? [] as $row): ?>
        <div class="am-wf-card">
            <div class="am-wf-head">
                <h3 class="am-wf-title"><?= htmlspecialchars($row['compliance_area']) ?></h3>
                <div class="am-wf-badges">
                    <span class="am-badge am-badge-type"><?= htmlspecialchars($row['workflow_level'] ?? 'Two-Level') ?></span>
                    <span class="am-badge am-badge-<?= ($row['status'] ?? '') === 'active' ? 'ok' : 'off' ?>"><?= ($row['status'] ?? '') === 'active' ? 'Active' : 'Inactive' ?></span>
                    <span class="am-badge <?= $riskClass($row) ?>"><?= $riskLabel($row) ?></span>
                </div>
            </div>
            <div class="am-wf-meta">
                <span><strong>Department</strong> <?= htmlspecialchars($row['department']) ?></span>
                <span><strong>Frequency</strong> <?= htmlspecialchars($row['frequency']) ?></span>
            </div>
            <div class="am-flow">
                <div class="am-flow-node am-flow-maker">
                    <div class="am-flow-ico"><i class="fas fa-user"></i></div>
                    <div class="am-flow-name"><?= am_flow_name($row, 'maker') ?></div>
                    <div class="am-flow-role">Maker</div>
                </div>
                <?php if (!$isSingle($row)): ?>
                <div class="am-flow-arrow"><i class="fas fa-arrow-right"></i></div>
                <div class="am-flow-node am-flow-reviewer">
                    <div class="am-flow-ico"><i class="fas fa-user-check"></i></div>
                    <div class="am-flow-name"><?= am_flow_name($row, 'reviewer') ?></div>
                    <div class="am-flow-role">Reviewer</div>
                </div>
                <?php endif; ?>
                <div class="am-flow-arrow"><i class="fas fa-arrow-right"></i></div>
                <div class="am-flow-node am-flow-approver">
                    <div class="am-flow-ico"><i class="fas fa-user-shield"></i></div>
                    <div class="am-flow-name"><?= am_flow_name($row, 'approver') ?></div>
                    <div class="am-flow-role">Approver</div>
                </div>
            </div>
            <p class="am-escalate"><i class="far fa-clock"></i> Escalate <strong><?= (int)($row['escalation_days_before'] ?? 2) ?> days</strong> before due if pending</p>
            <div class="am-wf-actions">
                <a href="<?= htmlspecialchars($basePath) ?>/authority-matrix/view/<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline">View</a>
                <a href="<?= htmlspecialchars($basePath) ?>/authority-matrix/edit/<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                <form method="post" action="<?= htmlspecialchars($basePath) ?>/authority-matrix/delete/<?= (int)$row['id'] ?>" class="d-inline" onsubmit="return confirm('Delete this workflow rule?');"><button type="submit" class="btn btn-sm btn-link text-danger p-0 border-0 bg-transparent"><i class="fas fa-trash-alt"></i> Delete</button></form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if (empty($items)): ?>
    <p class="text-muted am-empty">No workflows match. <a href="<?= htmlspecialchars($basePath) ?>/authority-matrix/add">Add a rule</a></p>
    <?php endif; ?>

    <?php else: ?>
    <div class="card" id="am-workflow-levels">
        <div class="table-wrap">
            <table class="data-table am-table">
                <thead>
                    <tr>
                        <th>Compliance area</th>
                        <th>Department</th>
                        <th>Maker</th>
                        <th>Reviewer</th>
                        <th>Approver</th>
                        <th>Level</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items ?? [] as $row): ?>
                    <tr>
                        <td><a href="<?= htmlspecialchars($basePath) ?>/authority-matrix/view/<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['compliance_area']) ?></a></td>
                        <td><?= htmlspecialchars($row['department']) ?></td>
                        <td><?= am_flow_name($row, 'maker') ?></td>
                        <td><?= $isSingle($row) ? '—' : am_flow_name($row, 'reviewer') ?></td>
                        <td><?= am_flow_name($row, 'approver') ?></td>
                        <td><?= htmlspecialchars($row['workflow_level'] ?? '') ?></td>
                        <td><span class="am-badge am-badge-<?= ($row['status'] ?? '') === 'active' ? 'ok' : 'off' ?>"><?= htmlspecialchars(ucfirst($row['status'] ?? '')) ?></span></td>
                        <td>
                            <a href="<?= htmlspecialchars($basePath) ?>/authority-matrix/view/<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline">View</a>
                            <a href="<?= htmlspecialchars($basePath) ?>/authority-matrix/edit/<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                            <form method="post" action="<?= htmlspecialchars($basePath) ?>/authority-matrix/toggle/<?= (int)$row['id'] ?>" class="d-inline"><button type="submit" class="btn btn-sm btn-secondary"><?= ($row['status'] ?? '') === 'active' ? 'Deactivate' : 'Activate' ?></button></form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
