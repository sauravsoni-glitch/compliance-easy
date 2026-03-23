<?php
$m = $member;
$basePath = $basePath ?? '';
?>
<div class="org-page">
    <a href="<?= htmlspecialchars($basePath) ?>/organization" class="text-primary"><i class="fas fa-arrow-left"></i> Back to Organization</a>
    <h1 class="page-title mt-2"><?= htmlspecialchars($m['full_name']) ?></h1>
    <p class="page-subtitle"><?= htmlspecialchars($m['email']) ?></p>
    <div class="card org-detail-card">
        <div class="org-summary-grid">
            <div><span class="text-muted text-sm">Role</span><div class="font-weight-600"><?= htmlspecialchars($m['role_name'] ?? '') ?></div></div>
            <div><span class="text-muted text-sm">Status</span><div><span class="org-status-pill org-st-<?= htmlspecialchars($m['status']) ?>"><?= htmlspecialchars(ucfirst($m['status'])) ?></span></div></div>
            <div><span class="text-muted text-sm">Department</span><div><?= htmlspecialchars($m['department'] ?? '—') ?></div></div>
            <div><span class="text-muted text-sm">Member since</span><div><?= htmlspecialchars(date('M j, Y', strtotime($m['created_at'] ?? 'now'))) ?></div></div>
        </div>
        <?php if ($isAdmin): ?>
        <p class="text-muted text-sm mt-2 mb-0">Manage roles in <a href="<?= htmlspecialchars($basePath) ?>/roles-permissions">Roles & Permissions</a>.</p>
        <?php endif; ?>
    </div>
</div>
