<?php
$basePath = $basePath ?? '';
$orgFlowPhase = 'done';
?>
<div class="org-page org-complete-page">
    <?php include __DIR__ . '/_stepper.php'; ?>
    <div class="card org-complete-card">
        <div class="org-complete-ico"><i class="fas fa-check"></i></div>
        <h1 class="org-complete-title">Organization setup complete</h1>
        <p class="org-complete-sub">Organization setup and user invitation completed successfully.</p>
        <a href="<?= htmlspecialchars($basePath) ?>/organization" class="btn btn-primary btn-lg">Return to Organization Profile</a>
    </div>
</div>
