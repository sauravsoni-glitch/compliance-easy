<?php
$r = $rule;
$basePath = $basePath ?? '';
?>
<div class="am-page">
    <a href="<?= htmlspecialchars($basePath) ?>/authority-matrix" class="text-primary"><i class="fas fa-arrow-left"></i> Back to Authority Matrix</a>
    <h1 class="page-title mt-2"><?= htmlspecialchars($r['compliance_area']) ?></h1>
    <p class="page-subtitle"><?= htmlspecialchars($r['department']) ?> · <?= htmlspecialchars($r['workflow_level'] ?? '') ?> · <?= htmlspecialchars(ucfirst($r['risk_level'] ?? '')) ?> risk</p>

    <div class="card am-detail-sec">
        <h3 class="card-title">Workflow info</h3>
        <div class="doa-detail-grid">
            <div><span class="text-muted text-sm">Department</span><div class="font-weight-600"><?= htmlspecialchars($r['department']) ?></div></div>
            <div><span class="text-muted text-sm">Frequency</span><div class="font-weight-600"><?= htmlspecialchars($r['frequency']) ?></div></div>
            <div><span class="text-muted text-sm">Status</span><div><span class="am-badge am-badge-<?= ($r['status'] ?? '') === 'active' ? 'ok' : 'off' ?>"><?= htmlspecialchars(ucfirst($r['status'] ?? '')) ?></span></div></div>
            <div><span class="text-muted text-sm">Escalation</span><div class="font-weight-600"><?= (int)($r['escalation_days_before'] ?? 2) ?> days before due</div></div>
        </div>
    </div>

    <div class="card am-detail-sec">
        <h3 class="card-title">Reporting structure</h3>
        <div class="am-hierarchy-mini">
            <div class="am-hm-step"><strong>Maker</strong><br><?= htmlspecialchars($r['maker_name'] ?? '—') ?></div>
            <?php if (!empty($r['reviewer_id'])): ?>
            <div class="am-hm-arrow">↓</div>
            <div class="am-hm-step"><strong>Reviewer</strong><br><?= htmlspecialchars(trim($r['reviewer_role_label'] ?? '') ?: ($r['reviewer_name'] ?? '—')) ?></div>
            <?php endif; ?>
            <div class="am-hm-arrow">↓</div>
            <div class="am-hm-step"><strong>Approver</strong><br><?= htmlspecialchars(trim($r['approver_role_label'] ?? '') ?: ($r['approver_name'] ?? '—')) ?></div>
        </div>
        <p class="text-muted text-sm mb-0">Next level approver is set per workflow; inactive users should be escalated per org policy.</p>
    </div>

    <div class="doa-detail-actions">
        <a href="<?= htmlspecialchars($basePath) ?>/authority-matrix/edit/<?= (int)$r['id'] ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Edit Mapping</a>
        <a href="<?= htmlspecialchars($basePath) ?>/authority-matrix/hierarchy/<?= (int)$r['id'] ?>" class="btn btn-secondary"><i class="fas fa-sitemap"></i> View Hierarchy</a>
        <a href="<?= htmlspecialchars($basePath) ?>/authority-matrix" class="btn btn-outline">Back to List</a>
    </div>
</div>
