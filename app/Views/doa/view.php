<?php
$basePath = $basePath ?? '';
$rows = $rows ?? [];
$head = $rows[0] ?? [];
$isAdmin = $isAdmin ?? false;
$ruleSetId = $ruleSetId ?? 0;
?>
<div class="doa-page">
    <a href="<?= htmlspecialchars($basePath) ?>/doa/list" class="text-primary font-weight-600"><i class="fas fa-arrow-left"></i> Back to list</a>
    <h1 class="page-title mt-2"><?= htmlspecialchars($head['rule_name'] ?? 'DOA rule') ?></h1>
    <p class="page-subtitle"><?= htmlspecialchars($head['department'] ?? '') ?> · <?= htmlspecialchars($head['condition_type'] ?? '') ?><?php
        $cv = trim((string)($head['condition_value'] ?? ''));
        if ($cv !== '') {
            echo ' · ' . htmlspecialchars($cv);
        }
    ?> · <?= htmlspecialchars($flowText ?? '') ?></p>

    <?php if (!empty($head['delegation_notes'])): ?>
    <div class="card doa-detail-sec">
        <h3 class="card-title">Notes</h3>
        <p class="mb-0 doa-pre-wrap"><?= htmlspecialchars((string)$head['delegation_notes']) ?></p>
    </div>
    <?php endif; ?>

    <div class="card doa-detail-sec">
        <h3 class="card-title">Levels</h3>
        <ol class="mb-0">
            <?php foreach ($rows as $r): ?>
            <li><strong>L<?= (int)$r['level'] ?></strong> — <?= htmlspecialchars(ucfirst((string)$r['role'])) ?> <span class="text-muted text-sm">(resolved to first active user with this role)</span></li>
            <?php endforeach; ?>
        </ol>
    </div>
    <div class="card doa-detail-sec">
        <h3 class="card-title">Status</h3>
        <p class="mb-0"><span class="badge <?= ($head['status'] ?? '') === 'Active' ? 'badge-success' : 'badge-secondary' ?>"><?= htmlspecialchars($head['status'] ?? '') ?></span></p>
    </div>
    <?php if ($isAdmin): ?>
    <div class="doa-detail-actions">
        <a href="<?= htmlspecialchars($basePath) ?>/doa/edit/<?= (int)$ruleSetId ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Edit</a>
        <form method="post" action="<?= htmlspecialchars($basePath) ?>/doa/delete/<?= (int)$ruleSetId ?>" class="d-inline" onsubmit="return confirm('Delete this rule set?');">
            <button type="submit" class="btn btn-outline text-danger">Delete</button>
        </form>
    </div>
    <?php endif; ?>
</div>
