<?php
$r = $rule;
$basePath = $basePath ?? '';
$isAdmin = $isAdmin ?? false;
?>
<div class="doa-page">
    <a href="<?= htmlspecialchars($basePath) ?>/doa" class="text-primary font-weight-600"><i class="fas fa-arrow-left"></i> Back to DOA</a>
    <h1 class="page-title mt-2"><?= htmlspecialchars($r['rule_code'] ?? ('DOA-' . $r['id'])) ?></h1>
    <p class="page-subtitle"><?= htmlspecialchars($r['department']) ?> · L<?= (int)$r['level_order'] ?> · <?= htmlspecialchars($r['designation']) ?></p>

    <div class="card doa-detail-sec">
        <h3 class="card-title">Basic Info</h3>
        <div class="doa-detail-grid">
            <div><span class="text-muted text-sm">Department</span><div class="font-weight-600"><?= htmlspecialchars($r['department']) ?></div></div>
            <div><span class="text-muted text-sm">Role</span><div class="font-weight-600"><?= htmlspecialchars($r['designation']) ?></div></div>
            <div><span class="text-muted text-sm">Approval Type</span><div class="font-weight-600"><?= htmlspecialchars($r['approval_type'] ?? 'General') ?></div></div>
            <div><span class="text-muted text-sm">Status</span><div><span class="doa-st doa-st-<?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars(ucfirst($r['status'])) ?></span></div></div>
        </div>
    </div>

    <div class="card doa-detail-sec">
        <h3 class="card-title">Authority Limit</h3>
        <div class="doa-detail-grid">
            <div><span class="text-muted text-sm">Minimum Amount</span><div class="font-weight-600">₹<?= \App\Controllers\DoaController::formatIndianRupee((float)($r['min_amount'] ?? 0)) ?></div></div>
            <div><span class="text-muted text-sm">Maximum Amount</span><div class="font-weight-600 text-lg"><?= \App\Controllers\DoaController::formatLimit($r) ?></div></div>
        </div>
    </div>

    <div class="card doa-detail-sec">
        <h3 class="card-title">Conditions</h3>
        <p class="mb-0"><?= $r['conditions'] ? nl2br(htmlspecialchars($r['conditions'])) : '<span class="text-muted">No special conditions.</span>' ?></p>
        <?php if (!empty($r['expires_at'])): ?>
        <p class="text-warning text-sm mt-2 mb-0"><i class="far fa-clock"></i> Temporary rule expires <?= date('M d, Y', strtotime($r['expires_at'])) ?></p>
        <?php endif; ?>
    </div>

    <div class="doa-detail-actions">
        <?php if ($isAdmin): ?>
        <a href="<?= htmlspecialchars($basePath) ?>/doa/edit/<?= (int)$r['id'] ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Edit Rule</a>
        <?php endif; ?>
        <a href="<?= htmlspecialchars($basePath) ?>/doa" class="btn btn-secondary">Back to List</a>
    </div>
</div>
