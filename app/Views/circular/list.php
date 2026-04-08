<?php
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$basePath = $basePath ?? '';
function ci_status_badge(string $s): array {
    switch ($s) {
        case 'approved':
            return ['Approved & Compliance Created', 'ci-badge-approved'];
        case 'pending_approval':
            return ['Pending Approval', 'ci-badge-pending'];
        case 'ai_analyzed':
            return ['AI Analyzed', 'ci-badge-ai'];
        case 'rejected':
            return ['Rejected', 'ci-badge-reject'];
        case 'uploaded':
            return ['Uploaded', 'ci-badge-upload'];
        default:
            return [str_replace('_', ' ', $s), 'ci-badge-upload'];
    }
}
?>
<div class="ci-page">
    <div class="page-header ci-head">
        <div>
            <h1 class="page-title">Circular Intelligence</h1>
            <p class="page-subtitle">AI-Powered Regulatory Circular Processing &amp; Auto Compliance Creation</p>
        </div>
        <div class="ci-head-actions">
            <?php if (!empty($isAdmin)): ?>
            <a href="<?= htmlspecialchars($basePath) ?>/circular-intelligence/add" class="btn btn-secondary"><i class="fas fa-edit"></i> Add Circular</a>
            <a href="<?= htmlspecialchars($basePath) ?>/circular-intelligence/upload" class="btn btn-primary"><i class="fas fa-plus"></i> Upload New Circular</a>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($flashSuccess): ?><div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

    <div class="ci-kpi-row">
        <div class="ci-kpi ci-kpi-doc">
            <div class="ci-kpi-ico"><i class="fas fa-file-alt"></i></div>
            <div><div class="ci-kpi-val"><?= (int)($totalCirculars ?? 0) ?></div><div class="ci-kpi-lbl">Total Circulars</div></div>
        </div>
        <div class="ci-kpi ci-kpi-warn">
            <div class="ci-kpi-ico"><i class="fas fa-clock"></i></div>
            <div><div class="ci-kpi-val"><?= (int)($pendingApproval ?? 0) ?></div><div class="ci-kpi-lbl">Pending Approval</div></div>
        </div>
        <div class="ci-kpi ci-kpi-ok">
            <div class="ci-kpi-ico"><i class="fas fa-check-circle"></i></div>
            <div><div class="ci-kpi-val"><?= (int)($complianceCreated ?? 0) ?></div><div class="ci-kpi-lbl">Compliance Created</div></div>
        </div>
        <div class="ci-kpi ci-kpi-risk">
            <div class="ci-kpi-ico"><i class="fas fa-exclamation-triangle"></i></div>
            <div><div class="ci-kpi-val"><?= (int)($highImpactCount ?? 0) ?></div><div class="ci-kpi-lbl">High Impact</div></div>
        </div>
    </div>

    <div class="card ci-filter-card">
        <form method="get" action="<?= htmlspecialchars($basePath) ?>/circular-intelligence" class="ci-filters">
            <input type="search" name="q" class="form-control ci-filter-search" placeholder="Search circulars..." value="<?= htmlspecialchars($filterQ ?? '') ?>">
            <select name="authority" class="form-control ci-filter-select">
                <option value="">All Authorities</option>
                <?php foreach (['RBI', 'NHB', 'SEBI', 'Internal'] as $a): ?>
                <option value="<?= $a ?>" <?= ($filterAuth ?? '') === $a ? 'selected' : '' ?>><?= $a ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-control ci-filter-select">
                <option value="">All Statuses</option>
                <?php foreach (['uploaded', 'ai_analyzed', 'pending_approval', 'approved', 'rejected'] as $s): ?>
                <option value="<?= $s ?>" <?= ($filterStatus ?? '') === $s ? 'selected' : '' ?>><?= htmlspecialchars(ci_status_badge($s)[0]) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="impact" class="form-control ci-filter-select">
                <option value="">All Impact</option>
                <option value="high" <?= ($filterImpact ?? '') === 'high' ? 'selected' : '' ?>>High</option>
                <option value="medium" <?= ($filterImpact ?? '') === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="low" <?= ($filterImpact ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
            </select>
            <button type="submit" class="btn btn-secondary">Apply</button>
        </form>
    </div>

    <div class="card ci-table-card">
        <div class="table-wrap">
            <table class="data-table ci-table">
                <thead>
                    <tr>
                        <th>Circular ID</th>
                        <th>Title</th>
                        <th>Authority</th>
                        <th>Circular Date</th>
                        <th>Department</th>
                        <th>Impact</th>
                        <th>Status</th>
                        <th>Linked</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items ?? [] as $row):
                        [$stLabel, $stClass] = ci_status_badge($row['status'] ?? '');
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['circular_code']) ?></strong></td>
                        <td>
                            <a href="<?= htmlspecialchars($basePath) ?>/circular-intelligence/view/<?= (int)$row['id'] ?>" class="ci-title-link"><?= htmlspecialchars(mb_strlen($row['title']) > 55 ? mb_substr($row['title'], 0, 52) . '…' : $row['title']) ?></a>
                            <?php if (!empty($row['reference_no'])): ?><div class="text-muted text-sm"><?= htmlspecialchars($row['reference_no']) ?></div><?php endif; ?>
                        </td>
                        <td><span class="ci-fw-pill"><?= htmlspecialchars($row['authority']) ?></span></td>
                        <td><?= !empty($row['circular_date']) ? date('M d, Y', strtotime($row['circular_date'])) : '—' ?></td>
                        <td><?= htmlspecialchars($row['department'] ?? $row['ai_department'] ?? '—') ?></td>
                        <td><span class="ci-impact ci-impact-<?= htmlspecialchars($row['impact'] ?? 'medium') ?>"><?= htmlspecialchars(ucfirst($row['impact'] ?? 'medium')) ?></span></td>
                        <td><span class="ci-status-pill <?= $stClass ?>"><?= htmlspecialchars($stLabel) ?></span></td>
                        <td><?php if (!empty($row['linked_code'])): ?><a href="<?= htmlspecialchars($basePath) ?>/compliance/view/<?= (int)($row['linked_compliance_id'] ?? 0) ?>"><?= htmlspecialchars($row['linked_code']) ?></a><?php else: ?>—<?php endif; ?></td>
                        <td>
                            <a href="<?= htmlspecialchars($basePath) ?>/circular-intelligence/view/<?= (int)$row['id'] ?>" class="ci-view-link"><i class="fas fa-eye"></i> View</a>
                            <?php if (!empty($isAdmin) && empty($row['linked_compliance_id']) && ($row['status'] ?? '') !== 'rejected'): ?>
                            <form method="post" action="<?= htmlspecialchars($basePath) ?>/circular-intelligence/reanalyze/<?= (int)$row['id'] ?>" class="ci-inline-form" data-app-confirm="Re-run AI analysis?">
                                <button type="submit" class="btn btn-link btn-sm p-0">Re-Analyze</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($items)): ?>
                    <tr><td colspan="9" class="text-muted text-center py-4">No circulars match your filters. Upload or add a circular to begin.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
