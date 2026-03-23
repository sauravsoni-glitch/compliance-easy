<?php
$r = $rule;
$basePath = $basePath ?? '';
?>
<div class="am-page">
    <a href="<?= htmlspecialchars($basePath) ?>/authority-matrix/view/<?= (int)$r['id'] ?>" class="text-primary"><i class="fas fa-arrow-left"></i> Back to detail</a>
    <h1 class="page-title mt-2">Approval hierarchy</h1>
    <p class="page-subtitle"><?= htmlspecialchars($r['compliance_area']) ?></p>

    <div class="am-tree">
        <div class="am-tree-node am-tree-maker">
            <div class="am-tree-ico"><i class="fas fa-user"></i></div>
            <div class="am-tree-body">
                <span class="am-tree-tag">Maker</span>
                <strong><?= htmlspecialchars($r['maker_name'] ?? '—') ?></strong>
                <span class="text-muted text-sm">Submits compliance for review</span>
            </div>
        </div>
        <div class="am-tree-line"></div>
        <?php if (!empty($r['reviewer_id'])): ?>
        <div class="am-tree-node am-tree-reviewer">
            <div class="am-tree-ico"><i class="fas fa-user-check"></i></div>
            <div class="am-tree-body">
                <span class="am-tree-tag">Reviewer</span>
                <strong><?= htmlspecialchars(trim($r['reviewer_role_label'] ?? '') ?: ($r['reviewer_name'] ?? '—')) ?></strong>
                <?php if (trim($r['reviewer_role_label'] ?? '') && !empty($r['reviewer_name'])): ?>
                <span class="text-muted text-sm"><?= htmlspecialchars($r['reviewer_name']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="am-tree-line"></div>
        <?php endif; ?>
        <div class="am-tree-node am-tree-approver">
            <div class="am-tree-ico"><i class="fas fa-user-shield"></i></div>
            <div class="am-tree-body">
                <span class="am-tree-tag">Approver</span>
                <strong><?= htmlspecialchars(trim($r['approver_role_label'] ?? '') ?: ($r['approver_name'] ?? '—')) ?></strong>
                <?php if (trim($r['approver_role_label'] ?? '') && !empty($r['approver_name'])): ?>
                <span class="text-muted text-sm"><?= htmlspecialchars($r['approver_name']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="am-tree-line"></div>
        <div class="am-tree-node am-tree-escalation">
            <div class="am-tree-ico"><i class="fas fa-level-up-alt"></i></div>
            <div class="am-tree-body">
                <span class="am-tree-tag">Escalation</span>
                <strong>If pending — <?= (int)($r['escalation_days_before'] ?? 2) ?> days before due</strong>
                <span class="text-muted text-sm">Route to next authority per matrix & DOA limits</span>
            </div>
        </div>
    </div>
</div>
